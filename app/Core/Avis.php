<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Avis Google d'un établissement.
 *
 * Les avis sont récupérés **par le serveur** puis conservés dans un fichier
 * de cache. Le navigateur du visiteur ne contacte jamais Google : pas de
 * script tiers, pas de cookie déposé, rien à soumettre au consentement, et
 * une page qui s'affiche à la même vitesse que si les avis étaient écrits en
 * dur. C'est la raison d'être de cette classe — un widget Google embarqué
 * aurait été plus court à écrire mais aurait cassé ces quatre propriétés.
 *
 * Les photos de profil des auteurs ne sont volontairement pas reprises :
 * elles sont hébergées par Google, et les afficher rétablirait l'appel au
 * domaine tiers que tout le reste s'applique à éviter. Les initiales de
 * l'auteur en tiennent lieu.
 */
final class Avis
{
    /** Au-delà, le cache est rafraîchi à la première visite qui le constate. */
    private const FRAICHEUR = 43200; // 12 h

    /**
     * Temps de repos après un échec, avant de retenter.
     *
     * Sans lui, une clé révoquée ou un quota dépassé ferait retenter l'appel
     * à chaque affichage de page : le délai réseau s'ajouterait alors au
     * temps de réponse de tout le site, pour un résultat déjà connu.
     */
    private const REPOS_APRES_ECHEC = 1800; // 30 min

    /**
     * Délai avant de renoncer à joindre Google. Court : la page ne doit pas
     * attendre un service extérieur, le cache précédent prend le relais.
     */
    private const DELAI_RESEAU = 6;

    /** Nombre d'avis renvoyés par Google (l'API n'en donne pas davantage). */
    private const MAX_AVIS = 5;

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly Parametres $parametres,
        private readonly string $fichierCache,
    ) {
    }

    // ------------------------------------------------------------ disponibilité

    /** La récupération est-elle configurée ? */
    public function configure(): bool
    {
        return $this->cle() !== '' && $this->placeId() !== '';
    }

    /** L'affichage des avis sur le site est-il demandé ? */
    public function actif(): bool
    {
        return (bool) $this->parametres->get('avis.actif', false);
    }

    private function cle(): string
    {
        return trim((string) $this->parametres->get('avis.cle_api', ''));
    }

    private function placeId(): string
    {
        return trim((string) $this->parametres->get('avis.place_id', ''));
    }

    private function noteMini(): float
    {
        return (float) $this->parametres->get('avis.note_mini', 4);
    }

    /**
     * Temps d'affichage d'un avis avant de passer au suivant, en secondes.
     *
     * Borné : en deçà de trois secondes un avis n'a pas le temps d'être lu,
     * au-delà de trente le défilement ne se remarque plus. 0 arrête le
     * défilement automatique — les flèches et le glissement restent actifs.
     */
    public function pause(): int
    {
        $pause = (int) $this->parametres->get('avis.pause', 6);

        return $pause <= 0 ? 0 : max(3, min(30, $pause));
    }

    /** La date de parution est-elle affichée sous le nom de l'auteur ? */
    public function datesVisibles(): bool
    {
        return (bool) $this->parametres->get('avis.dates', true);
    }

    /**
     * Le nombre total d'avis accompagne-t-il la note moyenne ?
     *
     * Une société qui débute affiche un 5,0 flatteur mais un total modeste :
     * pouvoir ne montrer que la note évite de souligner le second.
     */
    public function totalVisible(): bool
    {
        return (bool) $this->parametres->get('avis.total', true);
    }

    // ------------------------------------------------------------------ lecture

    /**
     * Avis prêts à l'affichage, cache rafraîchi si nécessaire.
     *
     * Ne lève jamais d'exception : une panne réseau ou une clé révoquée doit
     * faire disparaître le bloc d'avis, pas la page qui le contient.
     *
     * @return array{note:float, total:int, avis:array<int, array<string, mixed>>, url:string, recupere:int, erreur:string}|null
     */
    public function donnees(): ?array
    {
        if (!$this->actif() || !$this->configure()) {
            return null;
        }

        $cache = $this->lireCache();

        if ($this->aRafraichir($cache)) {
            try {
                $cache = $this->rafraichir();
            } catch (RuntimeException $e) {
                // Cache périmé mais exploitable : mieux vaut des avis d'hier
                // qu'un trou dans la page.
                $this->noterEchec($cache, $e->getMessage());
                if ($cache === null) {
                    return null;
                }
                $cache['erreur'] = $e->getMessage();
            }
        }

        return $this->filtrer($cache);
    }

    /**
     * @param array<string, mixed>|null $cache
     */
    private function aRafraichir(?array $cache): bool
    {
        if ($cache === null) {
            return true;
        }

        $maintenant = time();

        // Un échec récent tient lieu de réponse : on n'y revient qu'après le
        // temps de repos, quitte à servir des avis un peu plus vieux.
        $dernierEchec = (int) ($cache['echec_le'] ?? 0);
        if ($dernierEchec > 0 && ($maintenant - $dernierEchec) < self::REPOS_APRES_ECHEC) {
            return false;
        }

        return ($maintenant - (int) ($cache['recupere'] ?? 0)) > self::FRAICHEUR;
    }

    /**
     * Mémorise l'échec pour espacer les tentatives suivantes.
     *
     * @param array<string, mixed>|null $cache
     */
    private function noterEchec(?array $cache, string $message): void
    {
        if ($cache === null) {
            // Rien à conserver : sans avis déjà enregistrés, il n'y a pas de
            // fichier de cache à marquer, et la section reste absente.
            return;
        }

        $cache['echec_le'] = time();
        $cache['erreur']   = $message;

        try {
            $this->ecrireCache($cache);
        } catch (RuntimeException) {
            // Le cache non inscriptible est déjà signalé ailleurs ; ne pas
            // faire échouer l'affichage de la page pour autant.
        }
    }

    /**
     * Ce que le back-office affiche : l'état réel du cache, erreur comprise,
     * sans filtrage ni repli silencieux.
     *
     * @return array<string, mixed>|null
     */
    public function etatCache(): ?array
    {
        return $this->lireCache();
    }

    /**
     * @param array<string, mixed> $cache
     * @return array{note:float, total:int, avis:array<int, array<string, mixed>>, url:string, recupere:int, erreur:string}
     */
    private function filtrer(array $cache): array
    {
        $mini = $this->noteMini();
        $avis = array_values(array_filter(
            $cache['avis'] ?? [],
            static fn(array $a): bool => (float) ($a['note'] ?? 0) >= $mini && trim((string) ($a['texte'] ?? '')) !== ''
        ));

        return [
            'note'     => (float) ($cache['note'] ?? 0),
            'total'    => (int) ($cache['total'] ?? 0),
            'avis'     => $avis,
            'url'      => (string) ($cache['url'] ?? ''),
            'recupere' => (int) ($cache['recupere'] ?? 0),
            'erreur'   => (string) ($cache['erreur'] ?? ''),
        ];
    }

    // ------------------------------------------------------------ rafraîchissement

    /**
     * Interroge Google et réécrit le cache.
     *
     * @return array<string, mixed>
     * @throws RuntimeException si Google est injoignable ou refuse la requête
     */
    public function rafraichir(): array
    {
        if (!$this->configure()) {
            throw new RuntimeException('Clé d’API ou identifiant de fiche manquant.');
        }

        $donnees = $this->interroger($this->cle(), $this->placeId());
        $donnees['recupere'] = time();
        $donnees['erreur']   = '';

        $this->ecrireCache($donnees);

        return $donnees;
    }

    /**
     * Appel à l'API Places (New). Le masque de champs est obligatoire : sans
     * lui Google refuse la requête.
     *
     * @return array<string, mixed>
     */
    private function interroger(string $cle, string $placeId): array
    {
        $champs = 'id,displayName,rating,userRatingCount,googleMapsUri,reviews';
        $url    = 'https://places.googleapis.com/v1/places/' . rawurlencode($placeId)
            . '?languageCode=fr&regionCode=FR';

        [$code, $corps] = $this->requete($url, [
            'X-Goog-Api-Key: ' . $cle,
            'X-Goog-FieldMask: ' . $champs,
        ]);

        $json = json_decode($corps, true);

        if ($code !== 200 || !is_array($json)) {
            throw new RuntimeException($this->messageErreur($code, is_array($json) ? $json : []));
        }

        return [
            'nom'   => (string) ($json['displayName']['text'] ?? ''),
            'note'  => round((float) ($json['rating'] ?? 0), 1),
            'total' => (int) ($json['userRatingCount'] ?? 0),
            'url'   => (string) ($json['googleMapsUri'] ?? ''),
            'avis'  => $this->normaliser($json['reviews'] ?? []),
        ];
    }

    /**
     * Réduit la réponse de Google à ce que le gabarit affiche. Tout le reste
     * (photos d'auteur, traductions, identifiants internes) est écarté ici
     * plutôt que dans la vue : le cache reste lisible et léger.
     *
     * @param mixed $brut
     * @return array<int, array{auteur:string, initiales:string, note:float, texte:string, date:string, horodatage:int}>
     */
    private function normaliser(mixed $brut): array
    {
        if (!is_array($brut)) {
            return [];
        }

        $avis = [];
        foreach (array_slice($brut, 0, self::MAX_AVIS) as $a) {
            if (!is_array($a)) {
                continue;
            }

            $auteur = trim((string) ($a['authorAttribution']['displayName'] ?? ''));
            $texte  = trim((string) ($a['originalText']['text'] ?? $a['text']['text'] ?? ''));
            $date   = (string) ($a['publishTime'] ?? '');

            if ($texte === '') {
                continue;
            }

            $avis[] = [
                'auteur'     => $auteur !== '' ? $auteur : 'Avis Google',
                'initiales'  => $this->initiales($auteur),
                'note'       => (float) ($a['rating'] ?? 0),
                'texte'      => $texte,
                'date'       => $date,
                'horodatage' => $date !== '' ? (int) strtotime($date) : 0,
            ];
        }

        return $avis;
    }

    /**
     * Initiales affichées en médaillon, à la place de la photo Google.
     */
    private function initiales(string $nom): string
    {
        $mots = preg_split('/\s+/u', trim($nom), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lettres = '';
        foreach (array_slice($mots, 0, 2) as $mot) {
            $lettres .= mb_strtoupper(mb_substr($mot, 0, 1, 'UTF-8'), 'UTF-8');
        }
        return $lettres !== '' ? $lettres : '★';
    }

    // ---------------------------------------------------------- recherche de fiche

    /**
     * Retrouve l'identifiant d'une fiche à partir de son nom et de sa ville.
     *
     * Le Place ID est introuvable pour qui ne connaît pas les outils Google :
     * cette recherche évite d'aller le chercher à la main.
     *
     * @return array<int, array{id:string, nom:string, adresse:string, note:float, total:int}>
     */
    public function rechercher(string $texte): array
    {
        $cle = $this->cle();
        if ($cle === '') {
            throw new RuntimeException('Renseignez d’abord la clé d’API, puis relancez la recherche.');
        }
        if (trim($texte) === '') {
            throw new RuntimeException('Indiquez le nom de l’établissement, avec sa ville.');
        }

        [$code, $corps] = $this->requete(
            'https://places.googleapis.com/v1/places:searchText',
            [
                'X-Goog-Api-Key: ' . $cle,
                'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.rating,places.userRatingCount',
                'Content-Type: application/json',
            ],
            json_encode(
                ['textQuery' => $texte, 'languageCode' => 'fr', 'regionCode' => 'FR'],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )
        );

        $json = json_decode($corps, true);
        if ($code !== 200 || !is_array($json)) {
            throw new RuntimeException($this->messageErreur($code, is_array($json) ? $json : []));
        }

        $resultats = [];
        foreach ($json['places'] ?? [] as $p) {
            if (!is_array($p) || ($p['id'] ?? '') === '') {
                continue;
            }
            $resultats[] = [
                'id'      => (string) $p['id'],
                'nom'     => (string) ($p['displayName']['text'] ?? ''),
                'adresse' => (string) ($p['formattedAddress'] ?? ''),
                'note'    => round((float) ($p['rating'] ?? 0), 1),
                'total'   => (int) ($p['userRatingCount'] ?? 0),
            ];
        }

        return $resultats;
    }

    // ------------------------------------------------------------------- réseau

    /**
     * Requête HTTP, cURL si disponible, flux PHP sinon.
     *
     * @param string[] $entetes
     * @return array{0:int, 1:string} code HTTP et corps de la réponse
     */
    private function requete(string $url, array $entetes, ?string $corpsEnvoye = null): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $entetes,
                CURLOPT_TIMEOUT        => self::DELAI_RESEAU,
                CURLOPT_CONNECTTIMEOUT => self::DELAI_RESEAU,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            if ($corpsEnvoye !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $corpsEnvoye);
            }

            $reponse = curl_exec($ch);
            $code    = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $erreur  = curl_error($ch);
            curl_close($ch);

            if ($reponse === false) {
                throw new RuntimeException('Google est injoignable : ' . ($erreur ?: 'délai dépassé') . '.');
            }

            return [$code, (string) $reponse];
        }

        $contexte = stream_context_create([
            'http' => [
                'method'        => $corpsEnvoye !== null ? 'POST' : 'GET',
                'header'        => implode("\r\n", $entetes),
                'content'       => $corpsEnvoye ?? '',
                'timeout'       => self::DELAI_RESEAU,
                'ignore_errors' => true,
            ],
        ]);

        $reponse = @file_get_contents($url, false, $contexte);
        if ($reponse === false) {
            throw new RuntimeException('Google est injoignable (les connexions sortantes sont peut-être bloquées).');
        }

        $code = 0;
        foreach ($http_response_header ?? [] as $entete) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $entete, $m) === 1) {
                $code = (int) $m[1];
            }
        }

        return [$code, $reponse];
    }

    /**
     * Traduit le refus de Google en une phrase qui dit quoi corriger.
     *
     * @param array<string, mixed> $json
     */
    private function messageErreur(int $code, array $json): string
    {
        $detail = trim((string) ($json['error']['message'] ?? ''));

        // Google renvoie 400 aussi bien pour une clé invalide que pour un
        // identifiant de fiche erroné : sans regarder le détail, le message
        // désignerait le mauvais coupable et enverrait chercher au mauvais
        // endroit.
        if (stripos($detail, 'api key') !== false) {
            return 'Clé d’API refusée par Google. Vérifiez qu’elle est correctement recopiée, '
                . 'que l’API « Places API (New) » est activée sur le projet, et que la '
                . 'facturation y est bien associée. (' . $detail . ')';
        }

        $conseil = match ($code) {
            400 => 'Requête refusée : l’identifiant de fiche (Place ID) est probablement erroné.',
            403 => 'Accès refusé : vérifiez que l’API « Places API (New) » est activée sur le projet '
                 . 'Google, et que la clé n’est pas restreinte à d’autres adresses IP ou domaines.',
            404 => 'Fiche introuvable : l’identifiant (Place ID) ne correspond à aucun établissement.',
            429 => 'Quota Google dépassé pour aujourd’hui. Les avis déjà enregistrés restent affichés.',
            0   => 'Aucune réponse de Google (connexion sortante bloquée ou délai dépassé).',
            default => 'Google a répondu une erreur ' . $code . '.',
        };

        return $detail !== '' ? $conseil . ' (' . $detail . ')' : $conseil;
    }

    // -------------------------------------------------------------------- cache

    /**
     * @return array<string, mixed>|null
     */
    private function lireCache(): ?array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        if (!is_file($this->fichierCache)) {
            return null;
        }

        $lu = json_decode((string) file_get_contents($this->fichierCache), true);

        return $this->cache = is_array($lu) ? $lu : null;
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private function ecrireCache(array $donnees): void
    {
        $dossier = dirname($this->fichierCache);
        if (!is_dir($dossier)) {
            $ancien = umask(0);
            @mkdir($dossier, Permissions::DOSSIER, true);
            umask($ancien);
        }

        $json = json_encode(
            $donnees,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        // Écriture atomique : une lecture concurrente ne doit jamais tomber
        // sur un fichier à moitié écrit.
        $tmp = $this->fichierCache . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $this->fichierCache)) {
            @unlink($tmp);
            throw new RuntimeException('Le cache des avis n’a pas pu être écrit.');
        }

        $this->cache = $donnees;
    }
}
