<?php

declare(strict_types=1);

/**
 * Avis Google de l'entreprise, via l'API officielle Google Places (New) — repris d'Intensiti.
 *
 *  - search()  : trouve la fiche Google par le nom de l'entreprise, son adresse, un lien
 *                Google Maps ou un lien de recherche Google collé tel quel ;
 *  - refresh() : note, nombre d'avis et avis de la fiche choisie, relus au plus une fois par
 *                jour (tâche planifiée, ou juste après l'envoi d'une page). En cas d'échec, la
 *                dernière version connue reste affichée.
 *
 * Google ne renvoie que 5 avis par lecture : tous ceux déjà vus sont gardés (60 au plus), pour
 * en afficher davantage au fil du temps.
 */
final class GoogleReviews
{
    private const API = 'https://places.googleapis.com/v1/';
    private const TTL = 86400;
    /** Garde-fou : nombre maximal de lectures de la fiche par jour. */
    private const DAILY_CAP = 40;

    /** Essais : réponses HTTP simulées (méthode, url, corps) => [code, corps]. */
    public static ?Closure $http = null;

    public static function key(): string
    {
        return Settings::secret('google_places_key');
    }

    public static function placeId(): string
    {
        return trim((string) Settings::get('google_place_id', ''));
    }

    public static function connected(): bool
    {
        return self::key() !== '' && self::placeId() !== '';
    }

    /** Dernières données lues chez Google (sans appel réseau). */
    public static function data(): array
    {
        $d = Store::get('google_reviews', []);
        return ($d['place_id'] ?? '') === self::placeId() ? $d : [];
    }

    /** Texte de recherche tiré d'un lien Google (Maps ou recherche) ou laissé tel quel. */
    public static function queryFrom(string $q): string
    {
        $q = trim($q);
        if (preg_match('~google\.[a-z.]+/maps/place/([^/@?]+)~i', $q, $m)) {
            return trim(str_replace('+', ' ', rawurldecode($m[1])));
        }
        if (preg_match('~^https?://(www\.)?google\.[a-z.]+/search\?~i', $q)) {
            parse_str((string) parse_url($q, PHP_URL_QUERY), $args);
            if (!empty($args['q']) && is_string($args['q'])) {
                return trim($args['q']);
            }
        }
        return $q;
    }

    /**
     * Établissements correspondant à un nom ou une adresse.
     * @return array{results?:array,error?:string}
     */
    public static function search(string $query, string $key = ''): array
    {
        $query = self::queryFrom($query);
        if ($query === '') {
            return ['results' => []];
        }
        $res = self::call('POST', 'places:searchText', 'places.id,places.displayName,places.formattedAddress,places.rating,places.userRatingCount,places.googleMapsUri',
            ['textQuery' => mb_substr($query, 0, 200), 'languageCode' => 'fr', 'regionCode' => 'FR', 'pageSize' => 8], $key);
        if (isset($res['error'])) {
            return $res;
        }
        return ['query' => $query, 'results' => array_map(fn($p) => [
            'place_id' => (string) ($p['id'] ?? ''),
            'name' => (string) ($p['displayName']['text'] ?? ''),
            'address' => (string) ($p['formattedAddress'] ?? ''),
            'rating' => isset($p['rating']) ? (float) $p['rating'] : null,
            'count' => (int) ($p['userRatingCount'] ?? 0),
            'maps_url' => (string) ($p['googleMapsUri'] ?? ''),
        ], (array) ($res['places'] ?? []))];
    }

    /** Lit la fiche chez Google (au plus une fois par jour, sauf $force). */
    public static function refresh(bool $force = false): array
    {
        $placeId = self::placeId();
        if (!preg_match('/^[A-Za-z0-9_-]{10,300}$/', $placeId)) {
            return ['error' => 'Aucune fiche Google choisie.'];
        }
        $last = self::data();
        if (!$force && $last && strtotime((string) ($last['fetched_at'] ?? '')) > time() - self::TTL) {
            return $last;
        }
        $cap = Store::get('google_calls', []);
        $day = date('Ymd');
        if ((int) ($cap[$day] ?? 0) >= self::DAILY_CAP) {
            return $last ?: ['error' => 'Limite journalière de lectures atteinte : réessayez demain.'];
        }
        Store::put('google_calls', [$day => (int) ($cap[$day] ?? 0) + 1]);

        $res = self::call('GET', 'places/' . $placeId . '?languageCode=fr', 'id,displayName,formattedAddress,rating,userRatingCount,reviews,googleMapsUri');
        if (isset($res['error'])) {
            if ($last) {
                // Dernière version connue gardée, nouvel essai plus tard
                $last['error'] = $res['error'];
                $last['error_at'] = date('c');
                Store::put('google_reviews', $last);
                return $last;
            }
            return $res;
        }
        $fresh = array_map(fn($r) => [
            'rating' => (int) ($r['rating'] ?? 0),
            'text' => trim((string) ($r['text']['text'] ?? $r['originalText']['text'] ?? '')),
            'author' => (string) ($r['authorAttribution']['displayName'] ?? 'Client Google'),
            'photo' => (string) ($r['authorAttribution']['photoUri'] ?? ''),
            'when' => (string) ($r['relativePublishTimeDescription'] ?? ''),
            'date' => (string) ($r['publishTime'] ?? ''),
        ], (array) ($res['reviews'] ?? []));
        $data = [
            'place_id' => $placeId,
            'name' => (string) ($res['displayName']['text'] ?? ''),
            'address' => (string) ($res['formattedAddress'] ?? ''),
            'rating' => isset($res['rating']) ? (float) $res['rating'] : null,
            'count' => (int) ($res['userRatingCount'] ?? 0),
            'maps_url' => (string) ($res['googleMapsUri'] ?? ''),
            'review_url' => 'https://search.google.com/local/writereview?placeid=' . rawurlencode($placeId),
            'fetched_at' => date('c'),
            'error' => null,
        ];
        [$data['reviews'], $data['history']] = self::remember($last['history'] ?? [], $fresh);
        Store::put('google_reviews', $data);
        return $data;
    }

    /** Tâche planifiée : relecture si la dernière date de plus de 24 h. */
    public static function refreshIfDue(): string
    {
        if (!self::connected()) {
            return 'non connectés';
        }
        $d = self::refresh();
        return isset($d['error']) && !isset($d['place_id']) ? 'erreur : ' . $d['error'] : (string) count($d['reviews'] ?? []) . ' avis';
    }

    /** Pages publiques : relecture après l'envoi de la page si les avis datent (sans ralentir le visiteur). */
    public static function refreshAfterResponse(): void
    {
        if (!self::connected()) {
            return;
        }
        $d = self::data();
        if ($d && strtotime((string) ($d['fetched_at'] ?? '')) > time() - self::TTL - 3600) {
            return;
        }
        if (!function_exists('fastcgi_finish_request') && !function_exists('litespeed_finish_request')) {
            return; // la tâche planifiée s'en charge
        }
        register_shutdown_function(static function (): void {
            function_exists('fastcgi_finish_request') ? fastcgi_finish_request() : litespeed_finish_request();
            try {
                self::refresh();
            } catch (Throwable $e) {
                error_log('[renovest] avis Google : ' . $e->getMessage());
            }
        });
    }

    /** Avis Google au format du site, filtrés (texte présent, note minimale réglée). */
    public static function reviews(): array
    {
        $min = (int) Settings::get('google_min_rating', 4);
        $out = [];
        foreach ((array) (self::data()['reviews'] ?? []) as $r) {
            if (trim((string) $r['text']) === '' || (int) $r['rating'] < $min) {
                continue;
            }
            $out[] = ['name' => $r['author'], 'text' => $r['text'], 'rating' => (int) $r['rating'], 'avatar' => $r['photo'],
                'where' => trim('Avis Google' . ($r['when'] !== '' ? ' · ' . $r['when'] : '')), 'source' => 'Google', 'visible' => true];
        }
        return $out;
    }

    /**
     * Garde tous les avis déjà vus (60 au plus) : les 5 du jour d'abord, puis les plus récents.
     * @return array{0: array, 1: array} [avis à afficher, historique]
     */
    private static function remember(array $hist, array $fresh): array
    {
        $id = fn(array $r) => sha1(($r['author'] ?? '') . '|' . (($r['date'] ?? '') ?: mb_substr((string) ($r['text'] ?? ''), 0, 60)));
        foreach ($fresh as $r) {
            $hist[$id($r)] = $r;
        }
        uasort($hist, fn($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
        $hist = array_slice($hist, 0, 60, true);
        $freshIds = array_map($id, $fresh);
        $others = array_values(array_filter($hist, fn($r, $k) => !in_array($k, $freshIds, true), ARRAY_FILTER_USE_BOTH));
        return [array_merge($fresh, $others), $hist];
    }

    private static function call(string $method, string $path, string $fields, ?array $body = null, string $key = ''): array
    {
        $key = $key !== '' ? $key : self::key();
        if ($key === '') {
            return ['error' => 'Clé API Google non réglée.'];
        }
        $url = self::API . $path;
        $payload = $body !== null ? (string) json_encode($body, JSON_UNESCAPED_UNICODE) : null;
        if (self::$http) {
            [$code, $raw] = (self::$http)($method, $url, $payload);
        } else {
            if (!function_exists('curl_init')) {
                return ['error' => 'Extension PHP cURL absente sur le serveur.'];
            }
            $ch = curl_init($url);
            $headers = ['X-Goog-Api-Key: ' . $key, 'X-Goog-FieldMask: ' . $fields];
            if ($payload !== null) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                return ['error' => 'Google injoignable : ' . $err];
            }
        }
        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            return ['error' => 'Google : réponse illisible (code ' . $code . ').'];
        }
        if ($code >= 400) {
            $msg = (string) ($json['error']['message'] ?? '');
            if (str_contains($msg, 'API key not valid')) {
                return ['error' => 'Clé API Google refusée : vérifiez-la dans Google Cloud.'];
            }
            if ($code === 403) {
                return ['error' => 'Google refuse l\'accès : activez « Places API (New) » pour cette clé dans Google Cloud' . ($msg !== '' ? ' (' . $msg . ')' : '') . '.'];
            }
            return ['error' => 'Google : ' . ($msg !== '' ? $msg : 'erreur ' . $code)];
        }
        return $json;
    }
}
