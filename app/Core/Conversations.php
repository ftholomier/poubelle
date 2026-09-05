<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Journal des échanges avec l'assistant.
 *
 * Un fichier par mois plutôt qu'un par conversation : un site vitrine
 * n'échange pas des milliers de messages, et un répertoire qui grossit d'un
 * fichier par visiteur devient vite impossible à parcourir en FTP. Le mois
 * suffit à borner la taille des lectures, et la purge se fait alors en
 * supprimant des fichiers entiers.
 *
 * Ces échanges sont des données personnelles : le visiteur peut y laisser son
 * nom et son téléphone. D'où la conservation bornée (voir CONSERVATION), la
 * purge automatique au fil des écritures, et le rangement hors racine web.
 */
final class Conversations
{
    /** Au-delà, les échanges sont effacés. */
    public const CONSERVATION = 12;   // mois

    /** Garde-fous : au-delà, on cesse d'enregistrer plutôt que de gonfler. */
    private const MESSAGES_MAX = 60;
    private const TEXTE_MAX = 4000;

    public function __construct(private readonly string $dossier)
    {
    }

    // ------------------------------------------------------------------ écriture

    /**
     * Ajoute un tour d'échange à une conversation, qu'elle crée au besoin.
     *
     * @param string $id identifiant fourni par le navigateur
     */
    public function ajouter(string $id, string $question, string $reponse, string $page = ''): void
    {
        $id = self::identifiantValide($id);
        $mois = date('Y-m');
        $tout = $this->lireMois($mois);

        $rang = null;
        foreach ($tout as $i => $c) {
            if (($c['id'] ?? '') === $id) {
                $rang = $i;
                break;
            }
        }

        if ($rang === null) {
            $tout[] = [
                'id'       => $id,
                'debut'    => time(),
                'derniere' => time(),
                'lu'       => false,
                'pages'    => [],
                'contact'  => [],
                'messages' => [],
            ];
            $rang = count($tout) - 1;
        }

        $c = $tout[$rang];
        $c['derniere'] = time();
        $c['lu'] = false;

        /* Ce que le navigateur envoie, borné à ce que c'est : un chemin. Il
           arrivait tel quel du corps de la requête, sans longueur maximale —
           un journal de conversations n'a pas à porter le texte qu'on veut
           bien y mettre. */
        $page = mb_substr((string) (parse_url($page, PHP_URL_PATH) ?: ''), 0, 200);
        if ($page !== '' && !in_array($page, $c['pages'], true)) {
            $c['pages'][] = $page;
        }

        if (count($c['messages']) < self::MESSAGES_MAX) {
            $c['messages'][] = ['role' => 'visiteur', 'texte' => mb_substr($question, 0, self::TEXTE_MAX), 'le' => time()];
            $c['messages'][] = ['role' => 'robot', 'texte' => mb_substr($reponse, 0, self::TEXTE_MAX), 'le' => time()];
        }

        // Un numéro ou une adresse écrits au fil de la discussion valent
        // demande de rappel : les relever ici évite qu'ils se perdent dans le
        // fil, là où l'exploitant ne les verrait pas.
        $c['contact'] = array_merge($c['contact'], self::reperer($question));

        $tout[$rang] = $c;
        $this->ecrireMois($mois, $tout);
        $this->purger();
    }

    /** Enregistre une demande de rappel explicite. */
    public function contact(string $id, array $contact): void
    {
        $id = self::identifiantValide($id);
        $mois = date('Y-m');
        $tout = $this->lireMois($mois);

        foreach ($tout as $i => $c) {
            if (($c['id'] ?? '') === $id) {
                $tout[$i]['contact'] = array_merge($c['contact'] ?? [], $contact);
                $tout[$i]['derniere'] = time();
                $tout[$i]['lu'] = false;
                $this->ecrireMois($mois, $tout);
                return;
            }
        }

        // demande envoyée sans qu'aucune question n'ait été posée
        $tout[] = [
            'id' => $id, 'debut' => time(), 'derniere' => time(), 'lu' => false,
            'pages' => [], 'contact' => $contact, 'messages' => [],
        ];
        $this->ecrireMois($mois, $tout);
    }

    // ------------------------------------------------------------------ lecture

    /** @return string[] mois disponibles, du plus récent au plus ancien */
    public function mois(): array
    {
        if (!is_dir($this->dossier)) {
            return [];
        }
        $mois = [];
        foreach (glob($this->dossier . '/*.json') ?: [] as $f) {
            $mois[] = basename($f, '.json');
        }
        rsort($mois);

        return $mois;
    }

    /**
     * Conversations d'un mois, la plus récente en tête.
     *
     * @return array<int, array<string, mixed>>
     */
    public function duMois(string $mois): array
    {
        $tout = $this->lireMois($mois);
        usort($tout, static fn(array $a, array $b): int => ($b['derniere'] ?? 0) <=> ($a['derniere'] ?? 0));

        return $tout;
    }

    /** @return array<string, mixed>|null */
    public function trouver(string $mois, string $id): ?array
    {
        foreach ($this->lireMois($mois) as $c) {
            if (($c['id'] ?? '') === $id) {
                return $c;
            }
        }

        return null;
    }

    /** Nombre de conversations jamais ouvertes dans le back-office. */
    public function nonLues(): int
    {
        $n = 0;
        foreach ($this->mois() as $mois) {
            foreach ($this->lireMois($mois) as $c) {
                if (!($c['lu'] ?? false)) {
                    $n++;
                }
            }
        }

        return $n;
    }

    // ------------------------------------------------------------------ gestion

    public function marquerLue(string $mois, string $id): void
    {
        $tout = $this->lireMois($mois);
        foreach ($tout as $i => $c) {
            if (($c['id'] ?? '') === $id) {
                $tout[$i]['lu'] = true;
                $this->ecrireMois($mois, $tout);
                return;
            }
        }
    }

    public function supprimer(string $mois, string $id): bool
    {
        $tout = $this->lireMois($mois);
        $reste = array_values(array_filter($tout, static fn(array $c): bool => ($c['id'] ?? '') !== $id));

        if (count($reste) === count($tout)) {
            return false;
        }
        $this->ecrireMois($mois, $reste);

        return true;
    }

    public function viderMois(string $mois): bool
    {
        $f = $this->fichier($mois);

        return is_file($f) && @unlink($f);
    }

    /**
     * Efface les mois qui dépassent la durée de conservation.
     *
     * Appelée à chaque écriture : la purge n'a ainsi besoin d'aucune tâche
     * planifiée, que bien des hébergements mutualisés ne proposent pas.
     */
    public function purger(): void
    {
        $limite = date('Y-m', strtotime('-' . self::CONSERVATION . ' months'));
        foreach ($this->mois() as $mois) {
            if ($mois < $limite) {
                @unlink($this->fichier($mois));
            }
        }
    }

    // ------------------------------------------------------------------ interne

    /**
     * Relève un téléphone français ou une adresse électronique dans un texte.
     *
     * @return array<string, string>
     */
    private static function reperer(string $texte): array
    {
        $trouve = [];

        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $texte, $m) === 1) {
            $trouve['email'] = $m[0];
        }
        // 0X XX XX XX XX, avec ou sans séparateurs, ou en +33
        if (preg_match('/(?:(?:\+|00)33[\s.\-]?|0)[1-9](?:[\s.\-]?\d{2}){4}/', $texte, $m) === 1) {
            $trouve['telephone'] = trim($m[0]);
        }

        return $trouve;
    }

    /** L'identifiant vient du navigateur : il ne sert jamais tel quel. */
    private static function identifiantValide(string $id): string
    {
        $id = preg_replace('/[^a-z0-9\-]/i', '', $id) ?? '';

        return $id !== '' ? substr($id, 0, 40) : bin2hex(random_bytes(8));
    }

    private function fichier(string $mois): string
    {
        // le mois vient d'une adresse : jamais concaténé sans contrôle
        $mois = preg_match('/^\d{4}-\d{2}$/', $mois) === 1 ? $mois : date('Y-m');

        return $this->dossier . '/' . $mois . '.json';
    }

    /** @return array<int, array<string, mixed>> */
    private function lireMois(string $mois): array
    {
        $f = $this->fichier($mois);
        if (!is_file($f)) {
            return [];
        }
        $lu = json_decode((string) file_get_contents($f), true);

        return is_array($lu) ? $lu : [];
    }

    /** @param array<int, array<string, mixed>> $donnees */
    private function ecrireMois(string $mois, array $donnees): void
    {
        if (!is_dir($this->dossier) && !@mkdir($this->dossier, 0755, true) && !is_dir($this->dossier)) {
            throw new RuntimeException('Impossible de créer ' . $this->dossier . '.');
        }

        // Écriture atomique, comme partout ailleurs : fichier temporaire puis
        // rename(), jamais en place — deux visiteurs peuvent écrire en même
        // temps.
        $f = $this->fichier($mois);
        $temporaire = $f . '.' . getmypid() . '.tmp';
        $json = json_encode(array_values($donnees), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($json === false || @file_put_contents($temporaire, $json, LOCK_EX) === false || !@rename($temporaire, $f)) {
            @unlink($temporaire);
            throw new RuntimeException('Impossible d’enregistrer la conversation.');
        }
        @chmod($f, 0644);
    }
}
