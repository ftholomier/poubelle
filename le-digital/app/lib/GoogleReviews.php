<?php
declare(strict_types=1);

namespace LD;

/**
 * Synchronisation des avis Google vers les avis du site.
 *
 * Deux modes :
 *  - « places »   : API Places (New) + clé API. Simple, mais Google ne renvoie que 5 avis (les plus pertinents).
 *  - « business » : API Google Business Profile + connexion Google (OAuth). Tous les avis de la fiche, et les réponses de Fred.
 *
 * Les avis importés gardent leurs réglages locaux (thème, publication, mise en avant, activité) d'une synchro à l'autre.
 * Le texte, la note et l'auteur restent ceux de Google (non modifiables), et la liste est rafraîchie chaque jour.
 */
final class GoogleReviews
{
    private const STATE = 'data/google-reviews.json';
    private const PLACES = 'https://places.googleapis.com/v1';
    private const OAUTH_AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const OAUTH_TOKEN = 'https://oauth2.googleapis.com/token';
    private const ACCOUNTS = 'https://mybusinessaccountmanagement.googleapis.com/v1';
    private const BUSINESS_INFO = 'https://mybusinessbusinessinformation.googleapis.com/v1';
    private const REVIEWS_V4 = 'https://mybusiness.googleapis.com/v4';
    private const SCOPE = 'https://www.googleapis.com/auth/business.manage';
    private const STARS = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];

    public static function config(): array
    {
        return (array) Settings::get('google', []) + [
            'mode' => 'off', 'place_id' => '', 'place_name' => '', 'client_id' => '', 'account' => '', 'location' => '',
            'location_name' => '', 'maps_uri' => '', 'review_uri' => '', 'min_rating' => 1, 'hide_empty' => true,
            'auto_publish' => true, 'show_badge' => true,
        ];
    }

    public static function saveConfig(array $cfg): void
    {
        $s = Store::read('content/settings.json', []);
        $s['google'] = $cfg;
        Settings::save($s);
    }

    /** État de la dernière synchro : note moyenne, nombre d'avis, liens Google, erreur éventuelle. */
    public static function state(): array
    {
        return Store::read(self::STATE, []);
    }

    private static function setState(array $changes): void
    {
        Store::update(self::STATE, static function (array &$s) use ($changes): void {
            $s = array_merge($s, $changes);
        });
    }

    public static function enabled(): bool
    {
        return self::config()['mode'] !== 'off';
    }

    /** Lien pour laisser un avis Google (affiché sur la page Avis). */
    public static function writeReviewUrl(): string
    {
        $st = self::state();
        if (!empty($st['write_uri'])) {
            return (string) $st['write_uri'];
        }
        $pid = (string) (($st['place_id'] ?? '') ?: self::config()['place_id']);
        return $pid !== '' ? 'https://search.google.com/local/writereview?placeid=' . rawurlencode($pid) : '';
    }

    /**
     * Note globale à afficher sur le site (badge « ★ 4,9 · 37 avis Google »), ou null si rien à montrer.
     */
    public static function badge(): ?array
    {
        $cfg = self::config();
        $st = self::state();
        if ($cfg['mode'] === 'off' || empty($cfg['show_badge']) || empty($st['rating']) || empty($st['count'])) {
            return null;
        }
        $pid = (string) ($st['place_id'] ?? '');
        return [
            'rating' => (float) $st['rating'],
            'count'  => (int) $st['count'],
            'url'    => (string) (($st['maps_uri'] ?? '') ?: ($pid !== '' ? 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode($pid) : '')),
            'write'  => self::writeReviewUrl(),
        ];
    }

    // ------------------------------------------------------------------ mode « places » (clé API)

    private static function placesHeaders(string $fieldMask): array
    {
        $key = Secrets::get('google_places_key');
        if (!$key) {
            throw new \RuntimeException('Clé API Google Places manquante.');
        }
        return ['X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => $fieldMask];
    }

    private static function googleError(array $r): string
    {
        $msg = $r['json']['error']['message'] ?? ($r['json']['error_description'] ?? ($r['error'] ?: mb_substr(strip_tags($r['body']), 0, 200)));
        return 'Google a répondu ' . $r['status'] . ' : ' . (is_string($msg) ? $msg : json_encode($msg, JSON_UNESCAPED_UNICODE));
    }

    /** Recherche d'établissements (pour trouver son Place ID sans quitter le back-office). */
    public static function searchPlaces(string $query): array
    {
        $r = Http::request('POST', self::PLACES . '/places:searchText', self::placesHeaders('places.id,places.displayName,places.formattedAddress,places.rating,places.userRatingCount,places.googleMapsUri'), [
            'textQuery' => $query, 'languageCode' => 'fr', 'regionCode' => 'FR',
        ], 20);
        if ($r['status'] !== 200) {
            throw new \RuntimeException(self::googleError($r));
        }
        return array_map(static fn($p) => [
            'id'      => (string) ($p['id'] ?? ''),
            'name'    => (string) ($p['displayName']['text'] ?? ''),
            'address' => (string) ($p['formattedAddress'] ?? ''),
            'rating'  => $p['rating'] ?? null,
            'count'   => (int) ($p['userRatingCount'] ?? 0),
        ], $r['json']['places'] ?? []);
    }

    private static function syncPlaces(array $cfg): array
    {
        if ($cfg['place_id'] === '') {
            throw new \RuntimeException('Choisis d’abord ton établissement (Place ID).');
        }
        $r = Http::request('GET', self::PLACES . '/places/' . rawurlencode($cfg['place_id']) . '?languageCode=fr&regionCode=FR', self::placesHeaders('id,displayName,rating,userRatingCount,googleMapsUri,reviews'), null, 25);
        if ($r['status'] !== 200) {
            throw new \RuntimeException(self::googleError($r));
        }
        $j = $r['json'] ?? [];
        $reviews = [];
        foreach ($j['reviews'] ?? [] as $rv) {
            $text = (string) ($rv['originalText']['text'] ?? ($rv['text']['text'] ?? ''));
            $reviews[] = [
                'google_id'    => (string) ($rv['name'] ?? md5(json_encode($rv))),
                'rating'       => (int) ($rv['rating'] ?? 5),
                'text'         => $text,
                'name'         => (string) ($rv['authorAttribution']['displayName'] ?? 'Client Google'),
                'author_url'   => (string) ($rv['authorAttribution']['uri'] ?? ''),
                'author_photo' => (string) ($rv['authorAttribution']['photoUri'] ?? ''),
                'google_uri'   => (string) ($rv['googleMapsUri'] ?? ($j['googleMapsUri'] ?? '')),
                'date'         => (string) ($rv['publishTime'] ?? ''),
                'reply'        => '',
            ];
        }
        return [
            'reviews' => $reviews,
            'state'   => [
                'rating'    => isset($j['rating']) ? round((float) $j['rating'], 1) : null,
                'count'     => (int) ($j['userRatingCount'] ?? count($reviews)),
                'maps_uri'  => (string) ($j['googleMapsUri'] ?? ''),
                'place_id'  => (string) ($j['id'] ?? $cfg['place_id']),
                'name'      => (string) ($j['displayName']['text'] ?? $cfg['place_name']),
                'write_uri' => '',
            ],
        ];
    }

    // ------------------------------------------------------------------ mode « business » (connexion Google OAuth)

    public static function redirectUri(): string
    {
        return abs_url('/admin/avis/google/retour');
    }

    public static function authUrl(string $state): string
    {
        $cfg = self::config();
        return self::OAUTH_AUTH . '?' . http_build_query([
            'client_id'              => $cfg['client_id'],
            'redirect_uri'           => self::redirectUri(),
            'response_type'          => 'code',
            'scope'                  => self::SCOPE,
            'access_type'            => 'offline',
            'prompt'                 => 'consent',
            'include_granted_scopes' => 'true',
            'state'                  => $state,
        ]);
    }

    private static function tokenRequest(array $params): array
    {
        $cfg = self::config();
        $r = Http::request('POST', self::OAUTH_TOKEN, ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query($params + [
            'client_id' => $cfg['client_id'], 'client_secret' => (string) Secrets::get('google_client_secret'),
        ]), 20);
        if ($r['status'] !== 200 || empty($r['json']['access_token'])) {
            throw new \RuntimeException(self::googleError($r));
        }
        $j = $r['json'];
        Store::write('cache/google-token.json', ['access_token' => Crypto::encrypt($j['access_token']), 'expires' => time() + (int) ($j['expires_in'] ?? 3600) - 120]);
        if (!empty($j['refresh_token'])) {
            Secrets::set('google_refresh_token', $j['refresh_token']);
        }
        return $j;
    }

    /** Échange le code reçu après la connexion Google contre des jetons (le jeton de rafraîchissement est chiffré). */
    public static function exchangeCode(string $code): void
    {
        self::tokenRequest(['code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => self::redirectUri()]);
    }

    public static function connected(): bool
    {
        return Secrets::has('google_refresh_token');
    }

    public static function disconnect(): void
    {
        $refresh = Secrets::get('google_refresh_token');
        if ($refresh) {
            Http::request('POST', 'https://oauth2.googleapis.com/revoke', ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query(['token' => $refresh]), 10);
        }
        Secrets::delete('google_refresh_token');
        Store::delete('cache/google-token.json');
        Store::delete('cache/google-locations.json');
    }

    private static function accessToken(): string
    {
        $t = Store::read('cache/google-token.json', []);
        if (!empty($t['access_token']) && ($t['expires'] ?? 0) > time()) {
            $tok = Crypto::decrypt($t['access_token']);
            if ($tok) {
                return $tok;
            }
        }
        $refresh = Secrets::get('google_refresh_token');
        if (!$refresh) {
            throw new \RuntimeException('Compte Google non connecté.');
        }
        return (string) self::tokenRequest(['refresh_token' => $refresh, 'grant_type' => 'refresh_token'])['access_token'];
    }

    private static function gbp(string $url): array
    {
        $r = Http::request('GET', $url, ['Authorization' => 'Bearer ' . self::accessToken()], null, 25);
        if ($r['status'] !== 200) {
            throw new \RuntimeException(self::googleError($r));
        }
        return $r['json'] ?? [];
    }

    /** Établissements accessibles avec le compte Google connecté (liste mise en cache, actualisable). */
    public static function locations(bool $refresh = false): array
    {
        $cache = Store::read('cache/google-locations.json', []);
        if (!$refresh && isset($cache['items'])) {
            return $cache['items'];
        }
        $out = [];
        foreach (self::gbp(self::ACCOUNTS . '/accounts?pageSize=20')['accounts'] ?? [] as $acc) {
            $token = '';
            for ($page = 0; $page < 5; $page++) {
                $j = self::gbp(self::BUSINESS_INFO . '/' . $acc['name'] . '/locations?pageSize=100&readMask=name,title,storefrontAddress,metadata' . ($token ? '&pageToken=' . rawurlencode($token) : ''));
                foreach ($j['locations'] ?? [] as $loc) {
                    $addr = $loc['storefrontAddress'] ?? [];
                    $out[] = [
                        'account'  => (string) $acc['name'],
                        'location' => (string) $loc['name'],
                        'title'    => (string) ($loc['title'] ?? ''),
                        'address'  => trim(implode(' ', (array) ($addr['addressLines'] ?? [])) . ' ' . ($addr['postalCode'] ?? '') . ' ' . ($addr['locality'] ?? '')),
                        'place_id' => (string) ($loc['metadata']['placeId'] ?? ''),
                        'maps_uri' => (string) ($loc['metadata']['mapsUri'] ?? ''),
                        'review_uri' => (string) ($loc['metadata']['newReviewUri'] ?? ''),
                    ];
                }
                $token = (string) ($j['nextPageToken'] ?? '');
                if ($token === '') {
                    break;
                }
            }
        }
        Store::write('cache/google-locations.json', ['items' => $out, 'at' => date('c')]);
        return $out;
    }

    /** Texte d'un avis Business Profile sans la traduction automatique ajoutée par Google. */
    private static function originalComment(string $c): string
    {
        if (preg_match('/\(Original\)\s*(.+)$/su', $c, $m)) {
            return trim($m[1]);
        }
        return trim((string) preg_replace('/\s*\(Translated by Google\).*$/su', '', $c));
    }

    private static function syncBusiness(array $cfg): array
    {
        if ($cfg['account'] === '' || $cfg['location'] === '') {
            throw new \RuntimeException('Choisis l’établissement dont il faut importer les avis.');
        }
        $base = self::REVIEWS_V4 . '/' . $cfg['account'] . '/' . $cfg['location'] . '/reviews?pageSize=50';
        $reviews = [];
        $token = '';
        $j = [];
        for ($page = 0; $page < 40; $page++) {
            $j = self::gbp($base . ($token ? '&pageToken=' . rawurlencode($token) : ''));
            foreach ($j['reviews'] ?? [] as $rv) {
                $reviews[] = [
                    'google_id'    => (string) ($rv['reviewId'] ?? $rv['name'] ?? ''),
                    'rating'       => self::STARS[$rv['starRating'] ?? 'FIVE'] ?? 5,
                    'text'         => self::originalComment((string) ($rv['comment'] ?? '')),
                    'name'         => !empty($rv['reviewer']['isAnonymous']) ? 'Client Google' : (string) ($rv['reviewer']['displayName'] ?? 'Client Google'),
                    'author_url'   => '',
                    'author_photo' => (string) ($rv['reviewer']['profilePhotoUrl'] ?? ''),
                    'google_uri'   => '',
                    'date'         => (string) ($rv['createTime'] ?? ''),
                    'reply'        => self::originalComment((string) ($rv['reviewReply']['comment'] ?? '')),
                ];
            }
            $token = (string) ($j['nextPageToken'] ?? '');
            if ($token === '') {
                break;
            }
        }
        $placeId = (string) $cfg['place_id'];
        return [
            'reviews' => $reviews,
            'state'   => [
                'rating'    => isset($j['averageRating']) ? round((float) $j['averageRating'], 1) : null,
                'count'     => (int) ($j['totalReviewCount'] ?? count($reviews)),
                'maps_uri'  => $cfg['maps_uri'] ?: ($placeId !== '' ? 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode($placeId) : ''),
                'place_id'  => $placeId,
                'name'      => $cfg['location_name'],
                'write_uri' => (string) $cfg['review_uri'],
            ],
        ];
    }

    // ------------------------------------------------------------------ synchronisation

    /** Thème proposé d'après le texte de l'avis (modifiable ensuite dans le back-office). */
    public static function guessTag(string $text): string
    {
        $t = mb_strtolower($text);
        // Mot-clé => poids (les mots propres à un thème comptent plus que les mots communs).
        $map = [
            'Formation'      => ['formation' => 3, 'formateur' => 3, 'cpf' => 3, 'stagiaire' => 2, 'pédagog' => 2, 'apprendre' => 1, 'appris' => 1, 'cours' => 1],
            'Développement'  => ['crm' => 3, 'développ' => 2, 'site' => 2, 'application' => 2, 'logiciel' => 2, 'refonte' => 2, 'intensiti' => 3, 'outil' => 1, 'automatis' => 1],
            'Accompagnement' => ['accompagn' => 3, 'mentor' => 3, 'coach' => 2, 'consulting' => 2, 'conseil' => 1, 'stratégie' => 1, 'suivi' => 1],
        ];
        $best = '';
        $score = 0;
        foreach ($map as $tag => $words) {
            $n = 0;
            foreach ($words as $w => $weight) {
                $n += substr_count($t, $w) * $weight;
            }
            if ($n > $score) {
                [$best, $score] = [$tag, $n];
            }
        }
        return $best;
    }

    /**
     * Récupère les avis chez Google et met à jour les avis du site. Retourne un résumé.
     */
    public static function sync(): array
    {
        $cfg = self::config();
        try {
            $res = match ($cfg['mode']) {
                'places'   => self::syncPlaces($cfg),
                'business' => self::syncBusiness($cfg),
                default    => throw new \RuntimeException('Synchronisation Google désactivée.'),
            };
        } catch (\RuntimeException $e) {
            self::setState(['error' => $e->getMessage(), 'error_at' => date('c')]);
            throw $e;
        }
        return self::import($res, $cfg);
    }

    /**
     * Intègre les avis reçus de Google (['reviews' => [...], 'state' => [...]]) aux avis du site :
     * nouveaux ajoutés, existants mis à jour (réglages locaux conservés), disparus retirés.
     */
    public static function import(array $res, array $cfg): array
    {
        $incoming = array_values(array_filter($res['reviews'], static function ($r) use ($cfg) {
            if ($r['google_id'] === '') {
                return false;
            }
            if ((int) $r['rating'] < (int) $cfg['min_rating']) {
                return false;
            }
            return !($cfg['hide_empty'] && trim($r['text']) === '');
        }));
        $summary = ['added' => 0, 'updated' => 0, 'removed' => 0, 'total' => count($incoming)];
        Store::update('data/reviews.json', static function (array &$all) use ($incoming, $cfg, &$summary): void {
            $byId = [];
            foreach ($incoming as $r) {
                $byId[$r['google_id']] = $r;
            }
            $kept = [];
            foreach ($all as $local) {
                if (($local['source'] ?? '') !== 'google') {
                    $kept[] = $local;
                    continue;
                }
                $gid = (string) ($local['google_id'] ?? '');
                if (!isset($byId[$gid])) {
                    // Avis retiré chez Google (ou plus renvoyé par l'API) : on ne le garde pas.
                    $summary['removed']++;
                    continue;
                }
                $g = $byId[$gid];
                unset($byId[$gid]);
                $kept[] = array_merge($local, self::googleFields($g), ['updated_at' => date('c')]);
                $summary['updated']++;
            }
            foreach ($byId as $g) {
                $kept[] = self::googleFields($g) + [
                    'id'         => 'g' . substr(hash('sha256', $g['google_id']), 0, 10),
                    'source'     => 'google',
                    'activity'   => '',
                    'photo'      => '',
                    'tag'        => self::guessTag($g['text']),
                    'published'  => (bool) $cfg['auto_publish'],
                    'home'       => false,
                    'order'      => 0,
                    'created_at' => $g['date'] ?: date('c'),
                    'updated_at' => date('c'),
                ];
                $summary['added']++;
            }
            $all = $kept;
        });
        self::setState($res['state'] + ['synced_at' => date('c'), 'error' => null, 'mode' => $cfg['mode']]);
        PageCache::clear();
        Store::appendLog('google', ['event' => 'sync', 'mode' => $cfg['mode']] + $summary);
        return $summary;
    }

    /** Champs qui viennent de Google (écrasés à chaque synchro). */
    private static function googleFields(array $g): array
    {
        return [
            'google_id'    => $g['google_id'],
            'rating'       => max(1, min(5, (int) $g['rating'])),
            'text'         => mb_substr(clean_text($g['text'], 5000), 0, 5000),
            'name'         => clean_text($g['name'], 120),
            'author_url'   => filter_var($g['author_url'], FILTER_VALIDATE_URL) ? $g['author_url'] : '',
            'author_photo' => filter_var($g['author_photo'], FILTER_VALIDATE_URL) ? $g['author_photo'] : '',
            'google_uri'   => filter_var($g['google_uri'], FILTER_VALIDATE_URL) ? $g['google_uri'] : '',
            'date'         => $g['date'],
            'reply'        => clean_text($g['reply'], 3000),
        ];
    }

    /** Retire du site tous les avis importés depuis Google (les avis saisis à la main restent). */
    public static function purge(): int
    {
        $n = 0;
        Store::update('data/reviews.json', static function (array &$all) use (&$n): void {
            $before = count($all);
            $all = array_values(array_filter($all, static fn($r) => ($r['source'] ?? '') !== 'google'));
            $n = $before - count($all);
        });
        Store::write(self::STATE, []);
        PageCache::clear();
        return $n;
    }

    /** Synchro automatique (tâche planifiée) : une fois par jour. */
    public static function autoSync(): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        $st = self::state();
        $last = max(strtotime((string) ($st['synced_at'] ?? '')) ?: 0, strtotime((string) ($st['error_at'] ?? '')) ?: 0);
        if ($last > time() - 20 * 3600) {
            return null;
        }
        try {
            $s = self::sync();
            return 'Avis Google : ' . $s['total'] . ' (+' . $s['added'] . ', -' . $s['removed'] . ')';
        } catch (\RuntimeException $e) {
            return 'Avis Google : erreur — ' . $e->getMessage();
        }
    }
}
