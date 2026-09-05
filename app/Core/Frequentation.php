<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Combien de pages ont été vues, et lesquelles. Rien d'autre.
 *
 * **Pourquoi ne pas se contenter de Google Analytics.** Le socle sait le
 * brancher, mais il ne se charge qu'après accord du visiteur : sur un site de
 * mairie, la moitié des gens refusent, et le chiffre obtenu ne dit plus rien
 * de la fréquentation réelle. Surtout, il oblige la commune à confier le
 * parcours de ses administrés à un tiers pour savoir si sa page « Déchets »
 * est lue. Ce compteur-ci répond à la question sans rien envoyer nulle part.
 *
 * **Ce qui est enregistré, et c'est tout :** une date, un chemin, un nombre.
 * Pas d'adresse IP, pas de cookie, pas d'identifiant, pas d'agent utilisateur,
 * pas de référent, pas d'horaire à la seconde. Rien qui permette de suivre
 * quelqu'un d'une page à l'autre, ni d'un jour au lendemain. C'est ce qui
 * range cette mesure dans l'exemption de consentement prévue par la CNIL pour
 * la mesure d'audience strictement nécessaire — et c'est aussi pour cela
 * qu'elle ne peut pas dire « combien de visiteurs », seulement « combien de
 * pages vues ». La nuance est dite à l'écran plutôt que maquillée.
 *
 * Un fichier par mois, une ligne par jour. Sur une commune de sept cents
 * habitants, un mois pèse quelques kilo-octets ; treize mois sont gardés, de
 * quoi comparer un mois à celui de l'an dernier, puis le plus vieux s'efface.
 */
final class Frequentation
{
    /** Treize mois : le mois courant, et le même mois l'an dernier. */
    private const MOIS_GARDES = 13;

    /** Au-delà, on cesse de détailler par page : un mois ne doit pas enfler. */
    private const PAGES_MAX = 400;

    public function __construct(private readonly string $dossier)
    {
    }

    // ------------------------------------------------------------- écriture

    /**
     * Compte une page vue. Ne lève jamais : un compteur n'a pas le droit
     * d'empêcher une page de s'afficher.
     */
    public function noter(string $chemin): void
    {
        try {
            $this->incrementer($this->normaliser($chemin));
        } catch (\Throwable $e) {
            error_log('Fréquentation : ' . $e->getMessage());
        }
    }

    /**
     * Le chemin ramené à ce qui a un sens dans un tableau de bord.
     *
     * Les paramètres d'URL sont retirés — ils ne changent pas la page, et
     * garder « ?utm_source=… » remplirait le fichier de variantes d'une même
     * adresse. Un chemin trop long est écarté plutôt que tronqué : c'est du
     * bruit, ou une sonde.
     */
    private function normaliser(string $chemin): string
    {
        $chemin = (string) (parse_url($chemin, PHP_URL_PATH) ?: '/');
        $chemin = '/' . trim($chemin, '/');

        return $chemin === '/' || strlen($chemin) <= 120 ? $chemin : '';
    }

    private function incrementer(string $chemin): void
    {
        if ($chemin === '') {
            return;
        }

        $fichier = $this->fichierDuMois(date('Y-m'));
        $dossier = dirname($fichier);
        if (!is_dir($dossier) && !@mkdir($dossier, 0755, true) && !is_dir($dossier)) {
            return;
        }

        /* Lecture, incrément et écriture sous un même verrou : deux visiteurs
           à la même seconde s'annuleraient l'un l'autre sans lui. Le fichier
           est ouvert en « c+ » — créé s'il manque, sans être vidé — puis
           réécrit en place. Pas de temporaire ici : la perte d'un compteur de
           visites n'a pas la gravité d'un contenu tronqué, et un temporaire
           par page vue userait le disque pour rien. */
        $poignee = @fopen($fichier, 'c+');
        if ($poignee === false) {
            return;
        }

        try {
            if (!flock($poignee, LOCK_EX)) {
                return;
            }

            $brut = stream_get_contents($poignee);
            $mois = is_string($brut) && $brut !== '' ? json_decode($brut, true) : [];
            $mois = is_array($mois) ? $mois : [];

            $jour = date('Y-m-d');
            $mois[$jour] ??= ['_total' => 0];
            $mois[$jour]['_total'] = (int) ($mois[$jour]['_total'] ?? 0) + 1;

            // Le détail par page s'arrête à PAGES_MAX : le total, lui, continue
            // de compter. Mieux vaut un détail incomplet qu'un fichier qui
            // grossit sans fin sur une adresse sondée en boucle.
            if (isset($mois[$jour][$chemin]) || count($mois[$jour]) <= self::PAGES_MAX) {
                $mois[$jour][$chemin] = (int) ($mois[$jour][$chemin] ?? 0) + 1;
            }

            ftruncate($poignee, 0);
            rewind($poignee);
            fwrite($poignee, (string) json_encode($mois, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            fflush($poignee);
        } finally {
            flock($poignee, LOCK_UN);
            fclose($poignee);
        }

        $this->oublierLesVieuxMois();
    }

    private function oublierLesVieuxMois(): void
    {
        // Une fois par jour suffit : le témoin évite de balayer le dossier à
        // chaque page vue.
        $temoin = $this->dossier . '/.purge';
        if (is_file($temoin) && (string) @file_get_contents($temoin) === date('Y-m-d')) {
            return;
        }
        @file_put_contents($temoin, date('Y-m-d'));

        $limite = date('Y-m', strtotime('-' . self::MOIS_GARDES . ' months'));
        foreach (glob($this->dossier . '/*.json') ?: [] as $fichier) {
            if (basename($fichier, '.json') < $limite) {
                @unlink($fichier);
            }
        }
    }

    // -------------------------------------------------------------- lecture

    /**
     * Les pages vues jour par jour, du plus ancien au plus récent.
     *
     * Les jours sans visite sont rendus à zéro : une courbe trouée mentirait
     * sur la forme de la fréquentation.
     *
     * @return array<string, int> date => pages vues
     */
    public function parJour(int $jours = 30): array
    {
        $mois = [];
        $serie = [];
        for ($i = $jours - 1; $i >= 0; $i--) {
            $jour = date('Y-m-d', strtotime('-' . $i . ' days'));
            $cle = substr($jour, 0, 7);
            $mois[$cle] ??= $this->lireMois($cle);
            $serie[$jour] = (int) ($mois[$cle][$jour]['_total'] ?? 0);
        }

        return $serie;
    }

    /**
     * Les pages les plus vues sur la période.
     *
     * @return array<string, int> chemin => pages vues
     */
    public function pagesPhares(int $combien = 6, int $jours = 30): array
    {
        $cumul = [];
        $mois = [];
        for ($i = $jours - 1; $i >= 0; $i--) {
            $jour = date('Y-m-d', strtotime('-' . $i . ' days'));
            $cle = substr($jour, 0, 7);
            $mois[$cle] ??= $this->lireMois($cle);
            foreach ($mois[$cle][$jour] ?? [] as $chemin => $vues) {
                if ($chemin === '_total') {
                    continue;
                }
                $cumul[$chemin] = ($cumul[$chemin] ?? 0) + (int) $vues;
            }
        }

        arsort($cumul);

        return array_slice($cumul, 0, $combien, true);
    }

    /** Y a-t-il de quoi tracer quelque chose ? */
    public function amorcee(): bool
    {
        return (glob($this->dossier . '/*.json') ?: []) !== [];
    }

    /** @return array<string, array<string, int>> */
    private function lireMois(string $cle): array
    {
        $fichier = $this->fichierDuMois($cle);
        if (!is_file($fichier)) {
            return [];
        }
        $json = json_decode((string) @file_get_contents($fichier), true);

        return is_array($json) ? $json : [];
    }

    private function fichierDuMois(string $cle): string
    {
        return $this->dossier . '/' . $cle . '.json';
    }
}
