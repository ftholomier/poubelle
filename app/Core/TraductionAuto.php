<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Traduction automatique, sans clé d'API.
 *
 * Elle ne sert qu'une fois, au moment où l'on demande une première version
 * d'une langue : le résultat est enregistré sur disque, relu et corrigé à la
 * main, puis servi depuis les fichiers. Le site public n'appelle jamais ce
 * service — s'il devient indisponible, seul le bouton « Traduire
 * automatiquement » du back-office cesse de fonctionner.
 *
 * Trois services sont tentés dans l'ordre, du moins cher au plus précieux.
 * Les deux premiers sont gratuits et sans inscription — le point d'entrée
 * public de Google Traduction, puis MyMemory — mais comptent par adresse IP :
 * sur un hébergement mutualisé, cette IP est partagée avec d'autres sites et
 * le quota peut être épuisé avant le premier clic (HTTP 429). DeepL ne vient
 * qu'en dernier recours, quand les deux autres ont refusé : son offre
 * gratuite n'accorde qu'un million de caractères pour la vie du compte, pas
 * par mois, et rien ne les recharge. Chaque caractère qui peut être traduit
 * gratuitement ailleurs est un caractère gardé pour le jour où plus rien
 * d'autre ne répond.
 */
final class TraductionAuto
{
    /** Au-delà, l'adresse d'appel devient trop longue et le service refuse. */
    private const LOT_CARACTERES = 1400;

    /** Pause entre deux lots, pour ne pas se faire couper. */
    private const PAUSE_MS = 350;

    private const DELAI = 15;

    /**
     * Une clé gratuite s'adresse à un autre serveur qu'une clé payante, et
     * le serveur qui n'est pas le sien répond 403. Le suffixe « :fx » n'est
     * qu'un indice sur l'ordre à tenter : DeepL ne l'ajoute pas à toutes les
     * clés gratuites, donc les deux serveurs sont essayés.
     */
    private const DEEPL_GRATUIT = 'https://api-free.deepl.com/v2/translate';
    private const DEEPL_PRO     = 'https://api.deepl.com/v2/translate';

    /** Caractères effectivement envoyés à DeepL pendant cette traduction. */
    private int $deepLCaracteres = 0;

    public function __construct(private readonly string $cleDeepL = '')
    {
    }

    /**
     * Traduit une liste de textes.
     *
     * @param array<string, string> $textes clé => texte français
     * @return array{textes: array<string, string>, echecs: int, service: string, souci: string, deepl: int}
     */
    public function traduire(array $textes, string $vers, string $depuis = 'fr'): array
    {
        $textes = array_filter($textes, static fn(string $t): bool => trim($t) !== '');
        if ($textes === []) {
            return ['textes' => [], 'echecs' => 0, 'service' => '—', 'souci' => '', 'deepl' => 0];
        }

        $traduits = [];
        $echecs   = 0;
        $services = [];
        $souci    = '';

        foreach ($this->lots($textes) as $rang => $lot) {
            if ($rang > 0) {
                usleep(self::PAUSE_MS * 1000);
            }

            try {
                [$resultat, $utilise] = $this->traduireLot(array_values($lot), $vers, $depuis);
                // un lot peut basculer sur un autre service que le précédent :
                // annoncer le premier laisserait croire que tout est passé par lui
                $services[$utilise] = true;

                // le service rend les textes dans l'ordre reçu : un décalage
                // attribuerait la traduction d'une phrase à une autre
                if (count($resultat) !== count($lot)) {
                    $echecs += count($lot);
                    continue;
                }
                foreach (array_keys($lot) as $rangCle => $cle) {
                    $traduits[$cle] = $resultat[$rangCle];
                }
            } catch (RuntimeException $e) {
                error_log('Traduction automatique : ' . $e->getMessage());
                // sans cette trace, l'écran ne peut annoncer qu'un échec sans cause
                $souci = $e->getMessage();
                $echecs += count($lot);
            }
        }

        return [
            'textes'  => $traduits,
            'echecs'  => $echecs,
            'service' => $services === [] ? '—' : implode(' puis ', array_keys($services)),
            'souci'   => $souci,
            'deepl'   => $this->deepLCaracteres,
        ];
    }

    /**
     * Vérifie la clé en s'adressant à DeepL seul.
     *
     * La chaîne normale ne l'atteint qu'après le refus des deux services
     * gratuits : la faire jouer ici dirait que la traduction fonctionne, pas
     * que la clé est bonne.
     *
     * @return array{ok: bool, souci: string, caracteres: int}
     */
    public function verifierDeepL(): array
    {
        if ($this->cleDeepL === '') {
            return ['ok' => false, 'souci' => 'Aucune clé renseignée.', 'caracteres' => 0];
        }

        $sonde = 'Bonjour';
        try {
            $this->viaDeepL([$sonde], 'EN-GB', 'fr');
            $this->deepLCaracteres += mb_strlen($sonde);
            return ['ok' => true, 'souci' => '', 'caracteres' => $this->deepLCaracteres];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'souci' => $e->getMessage(), 'caracteres' => 0];
        }
    }

    /**
     * Découpe en lots qui tiennent dans une requête.
     *
     * @param array<string, string> $textes
     * @return array<int, array<string, string>>
     */
    private function lots(array $textes): array
    {
        $lots = [];
        $lot = [];
        $taille = 0;

        foreach ($textes as $cle => $texte) {
            $long = mb_strlen($texte);
            if ($lot !== [] && $taille + $long > self::LOT_CARACTERES) {
                $lots[] = $lot;
                $lot = [];
                $taille = 0;
            }
            $lot[$cle] = $texte;
            $taille += $long;
        }
        if ($lot !== []) {
            $lots[] = $lot;
        }

        return $lots;
    }

    /**
     * @param string[] $textes
     * @return array{0: string[], 1: string}
     */
    private function traduireLot(array $textes, string $vers, string $depuis): array
    {
        try {
            return [$this->viaGoogle($textes, $vers, $depuis), 'Google Traduction'];
        } catch (RuntimeException $google) {
            // un service de secours : certains hébergements bloquent l'un ou l'autre
            try {
                return [$this->viaMyMemory($textes, $vers, $depuis), 'MyMemory'];
            } catch (RuntimeException $secours) {
                return $this->viaDeepLOuEchouer($textes, $vers, $depuis, $google, $secours);
            }
        }
    }

    /**
     * Dernier recours, et seulement là : le quota DeepL ne se recharge pas.
     *
     * @param string[] $textes
     * @return array{0: string[], 1: string}
     */
    private function viaDeepLOuEchouer(
        array $textes,
        string $vers,
        string $depuis,
        RuntimeException $google,
        RuntimeException $secours,
    ): array {
        $gratuits = 'Google Traduction : ' . $google->getMessage()
                  . ' — MyMemory : ' . $secours->getMessage();

        if ($this->cleDeepL === '') {
            throw new RuntimeException($gratuits);
        }

        try {
            $traduits = $this->viaDeepL($textes, $vers, $depuis);
            $this->deepLCaracteres += array_sum(array_map('mb_strlen', $textes));
            return [$traduits, 'DeepL'];
        } catch (RuntimeException $deepL) {
            throw new RuntimeException($gratuits . ' — DeepL : ' . $deepL->getMessage());
        }
    }

    /**
     * DeepL : quota rattaché à la clé, donc insensible à l'IP du serveur.
     *
     * Le service accepte plusieurs textes par requête et les rend dans
     * l'ordre reçu, ce qui évite le repère de découpe imposé par Google.
     *
     * @param string[] $textes
     * @return string[]
     */
    private function viaDeepL(array $textes, string $vers, string $depuis): array
    {
        // le service attend un corps JSON et rend les textes dans l'ordre
        // reçu, ce qui évite le repère de découpe imposé par Google
        $envoi = json_encode([
            'text'        => array_values($textes),
            'source_lang' => strtoupper($depuis),
            'target_lang' => self::cibleDeepL($vers),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $entetes = [
            'Authorization: DeepL-Auth-Key ' . $this->cleDeepL,
            'Content-Type: application/json',
        ];

        $serveurs = str_ends_with($this->cleDeepL, ':fx')
            ? [self::DEEPL_GRATUIT, self::DEEPL_PRO]
            : [self::DEEPL_PRO, self::DEEPL_GRATUIT];

        $reponse = null;
        $refus   = null;
        foreach ($serveurs as $url) {
            try {
                $reponse = $this->appeler($url, $envoi, $entetes);
                break;
            } catch (RuntimeException $e) {
                $refus = $e;
            }
        }
        if ($reponse === null) {
            throw $refus ?? new RuntimeException('DeepL injoignable.');
        }

        $json = json_decode($reponse, true);

        $traductions = $json['translations'] ?? null;
        if (!is_array($traductions) || count($traductions) !== count($textes)) {
            throw new RuntimeException('Réponse inattendue de DeepL.');
        }

        return array_map(static fn(array $t): string => (string) ($t['text'] ?? ''), $traductions);
    }

    /**
     * Deux langues n'existent pour DeepL qu'en variante régionale : demander
     * la langue seule est déprécié et finira par être refusé.
     */
    private static function cibleDeepL(string $vers): string
    {
        $code = strtoupper($vers);

        return match ($code) {
            'EN' => 'EN-GB',
            'PT' => 'PT-PT',
            default => $code,
        };
    }

    /**
     * Point d'entrée public de Google Traduction.
     *
     * Les textes d'un lot sont séparés par un repère que le traducteur laisse
     * intact, ce qui permet de n'envoyer qu'une requête et de redécouper la
     * réponse.
     *
     * @param string[] $textes
     * @return string[]
     */
    private function viaGoogle(array $textes, string $vers, string $depuis): array
    {
        $separateur = "\n@@@\n";
        $reponse = $this->appeler(
            'https://translate.googleapis.com/translate_a/single?' . http_build_query([
                'client' => 'gtx',
                'sl'     => $depuis,
                'tl'     => $vers,
                'dt'     => 't',
                'q'      => implode($separateur, $textes),
            ])
        );

        $json = json_decode($reponse, true);
        if (!is_array($json[0] ?? null)) {
            throw new RuntimeException('Réponse inattendue de Google Traduction.');
        }

        $entier = '';
        foreach ($json[0] as $morceau) {
            $entier .= (string) ($morceau[0] ?? '');
        }

        $parties = preg_split('/\s*@@@\s*/u', $entier) ?: [];

        return array_map(trim(...), $parties);
    }

    /**
     * MyMemory : documenté et sans clé, mais un texte par requête.
     *
     * @param string[] $textes
     * @return string[]
     */
    private function viaMyMemory(array $textes, string $vers, string $depuis): array
    {
        $traduits = [];
        foreach ($textes as $texte) {
            $reponse = $this->appeler(
                'https://api.mymemory.translated.net/get?' . http_build_query([
                    'q'        => $texte,
                    'langpair' => $depuis . '|' . $vers,
                ])
            );
            $json = json_decode($reponse, true);
            $traduit = (string) ($json['responseData']['translatedText'] ?? '');
            if ($traduit === '') {
                throw new RuntimeException('Réponse vide de MyMemory.');
            }
            $traduits[] = html_entity_decode($traduit, ENT_QUOTES, 'UTF-8');
            usleep(200_000);
        }

        return $traduits;
    }

    /**
     * @param string[] $entetes
     */
    private function appeler(string $url, ?string $corpsEnvoye = null, array $entetes = []): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::DELAI,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; MenuiserieTrehant/1.0)',
            ]);
            if ($corpsEnvoye !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $corpsEnvoye);
            }
            if ($entetes !== []) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $entetes);
            }
            $corps = curl_exec($ch);
            $code  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $souci = curl_error($ch);
            curl_close($ch);

            if (!is_string($corps) || $corps === '') {
                throw new RuntimeException($souci !== '' ? $souci : 'Réponse vide (HTTP ' . $code . ').');
            }
            if ($code >= 400) {
                throw new RuntimeException($this->expliquer($code, $corps));
            }
            return $corps;
        }

        $lignes = "User-Agent: Mozilla/5.0 (compatible; MenuiserieTrehant/1.0)\r\n";
        foreach ($entetes as $entete) {
            $lignes .= $entete . "\r\n";
        }
        $options = ['timeout' => self::DELAI, 'header' => $lignes];
        if ($corpsEnvoye !== null) {
            $options['method']  = 'POST';
            if (!preg_grep('/^Content-Type:/i', $entetes)) {
                $lignes .= "Content-Type: application/x-www-form-urlencoded\r\n";
            }
            $options['header']  = $lignes;
            $options['content'] = $corpsEnvoye;
        }
        $contexte = stream_context_create(['http' => $options]);
        $corps = @file_get_contents($url, false, $contexte);
        if (!is_string($corps) || $corps === '') {
            throw new RuntimeException('Service de traduction injoignable depuis le serveur.');
        }

        return $corps;
    }

    /**
     * Les codes que l'on rencontre vraiment ici appellent chacun une action
     * différente de l'utilisateur : le seul numéro ne le lui dit pas.
     *
     * Un même code peut venir du service ou d'un intermédiaire du réseau —
     * le refus d'une clé et le blocage par un pare-feu d'hébergement se
     * répondent tous deux 403. Ce qui les sépare est dans le corps de la
     * réponse : le service se justifie en JSON, l'intermédiaire renvoie une
     * page d'erreur. Il est donc rapporté tel quel plutôt qu'interprété.
     */
    private function expliquer(int $code, string $corps): string
    {
        $dit = $this->citer($corps);

        $lecture = match ($code) {
            401, 403 => 'refus (HTTP ' . $code . ')',
            429      => 'quota momentanément épuisé (HTTP 429) — sans clé, ce quota est '
                      . 'compté par adresse IP et partagé avec les autres sites de '
                      . 'l’hébergement',
            456      => 'quota DeepL du mois épuisé (HTTP 456)',
            default  => 'le service a répondu HTTP ' . $code,
        };

        return $lecture . ($dit !== '' ? ' ; réponse : ' . $dit : '') . '.';
    }

    /**
     * Ce que le serveur a répondu, ramené à une ligne lisible dans un
     * bandeau : le message JSON s'il y en a un, sinon le texte de la page
     * d'erreur débarrassé de son balisage.
     */
    private function citer(string $corps): string
    {
        $json = json_decode($corps, true);
        if (is_array($json)) {
            $message = $json['message'] ?? $json['error'] ?? null;
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        // strip_tags laisse le contenu des styles et des scripts, illisible ici
        $sansCode = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', ' ', $corps) ?? $corps;
        $texte = trim(preg_replace('/\s+/u', ' ', strip_tags($sansCode)) ?? '');

        return mb_strlen($texte) > 160 ? mb_substr($texte, 0, 160) . '…' : $texte;
    }
}
