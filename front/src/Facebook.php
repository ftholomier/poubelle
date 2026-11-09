<?php

declare(strict_types=1);

/**
 * Synchronisation avec la page Facebook (API Graph, jeton d'accès de la page).
 *
 *  - Import : les publications de la page deviennent des actualités du site, mélangées aux
 *    autres par date, sans distinction visuelle. Catégorie par défaut ou règle par hashtag,
 *    image téléchargée dans public/uploads/fb, extrait tiré du texte.
 *  - Publication : une actu créée dans le back-office peut être publiée sur la page.
 */
final class Facebook
{
    public const GRAPH = 'https://graph.facebook.com/v21.0/';

    public static function configured(): bool
    {
        return trim((string) Settings::get('fb_page_id', '')) !== '' && Settings::hasSecret('fb_token');
    }

    /** Synchronisation automatique (tâche planifiée) si le délai réglé est écoulé. */
    public static function syncIfDue(): string
    {
        $h = (int) Settings::get('fb_auto_hours', 6);
        if (!self::configured() || $h <= 0) {
            return 'désactivée';
        }
        $last = (int) (Store::get('fb_state', [])['last'] ?? 0);
        if ($last > time() - $h * 3600) {
            return 'pas encore due';
        }
        $r = self::sync();
        return $r['added'] . ' ajoutée(s), ' . $r['updated'] . ' mise(s) à jour';
    }

    /** Importe les dernières publications de la page. */
    public static function sync(int $limit = 25): array
    {
        if (!self::configured()) {
            throw new RuntimeException('Renseignez l\'identifiant de la page et le jeton d\'accès.');
        }
        $page = rawurlencode(trim((string) Settings::get('fb_page_id')));
        $fields = 'id,message,created_time,permalink_url,full_picture,attachments{media,subattachments{media}}';
        $j = self::call('GET', $page . '/posts?fields=' . rawurlencode($fields) . '&limit=' . $limit);
        $ignored = Store::get('fb_ignored', []);
        $added = 0;
        $updated = 0;
        $rules = self::rules();
        $default = (string) Settings::get('fb_default_cat', 'Agence');
        foreach ((array) ($j['data'] ?? []) as $fb) {
            $fbId = (string) ($fb['id'] ?? '');
            $msg = trim((string) ($fb['message'] ?? ''));
            if ($fbId === '' || in_array($fbId, $ignored, true) || ($msg === '' && empty($fb['full_picture']))) {
                continue;
            }
            $existing = null;
            foreach (Store::get('posts') as $p) {
                if (($p['fb_id'] ?? '') === $fbId) {
                    $existing = $p;
                }
            }
            if ($existing && ($existing['source'] ?? '') !== 'facebook') {
                continue; // publiée depuis le site : déjà présente
            }
            $images = [];
            foreach (self::pictures($fb) as $u) {
                $local = Media::fetchRemote($u, 'fb');
                if ($local !== '') {
                    $images[] = $local;
                }
                if (count($images) >= 6) {
                    break;
                }
            }
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $msg) ?: [])));
            $title = $lines ? excerpt((string) $lines[0], 110) : 'Publication du ' . date_fr(substr((string) $fb['created_time'], 0, 10));
            $body = '';
            foreach ($lines as $l) {
                $body .= '<p>' . self::linkify(e($l)) . '</p>';
            }
            $cat = $default;
            foreach ($rules as $tag => $c) {
                if (mb_stripos($msg, $tag) !== false) {
                    $cat = $c;
                    break;
                }
            }
            $post = [
                'title' => $title,
                'date' => substr((string) ($fb['created_time'] ?? date('c')), 0, 10),
                'content' => $body,
                'excerpt' => excerpt(implode(' ', array_slice($lines, 1)) ?: $msg, 220),
                'images' => $images ?: ($existing['images'] ?? []),
                'link' => (string) ($fb['permalink_url'] ?? ''),
            ];
            if ($existing) {
                // Les réglages faits dans le back-office (catégorie, visibilité, adresse) sont gardés
                Store::update('posts', function ($all) use ($existing, $post) {
                    foreach ($all as &$p) {
                        if ($p['id'] === $existing['id']) {
                            $p = array_replace($p, $post);
                        }
                    }
                    return $all;
                });
                $updated++;
                continue;
            }
            $post += [
                'id' => 'fb-' . preg_replace('/[^0-9_]/', '', $fbId),
                'fb_id' => $fbId,
                'cat' => $cat,
                'source' => 'facebook',
                'visible' => true,
                'seo' => ['title' => '', 'description' => ''],
            ];
            $post['path'] = Content::uniquePath($post['title'], $post['id']);
            Store::update('posts', function ($all) use ($post) {
                $all[] = $post;
                return $all;
            });
            $added++;
        }
        Store::put('fb_state', ['last' => time(), 'added' => $added, 'updated' => $updated, 'at' => date('c')]);
        return ['added' => $added, 'updated' => $updated];
    }

    /** Publie une actualité du site sur la page Facebook. Retourne l'identifiant Facebook. */
    public static function publish(array $post): string
    {
        if (!self::configured()) {
            throw new RuntimeException('page Facebook non configurée');
        }
        $page = rawurlencode(trim((string) Settings::get('fb_page_id')));
        $text = plain((string) $post['title']) . "\n\n" . (trim((string) ($post['lead'] ?? '')) !== '' ? $post['lead'] . "\n\n" : '') . plain((string) ($post['content'] ?? ''));
        $url = Http::abs($post['path']);
        $img = Content::postImage($post);
        if ($img !== '') {
            $j = self::call('POST', $page . '/photos', ['url' => str_starts_with($img, 'http') ? $img : Http::abs($img), 'caption' => trim($text) . "\n\n" . $url]);
            return (string) ($j['post_id'] ?? $j['id'] ?? '');
        }
        $j = self::call('POST', $page . '/feed', ['message' => trim($text), 'link' => $url]);
        return (string) ($j['id'] ?? '');
    }

    private static function pictures(array $fb): array
    {
        $out = [];
        foreach ((array) ($fb['attachments']['data'] ?? []) as $a) {
            foreach ((array) ($a['subattachments']['data'] ?? []) as $s) {
                if (!empty($s['media']['image']['src'])) {
                    $out[] = $s['media']['image']['src'];
                }
            }
            if (!empty($a['media']['image']['src'])) {
                $out[] = $a['media']['image']['src'];
            }
        }
        if (!$out && !empty($fb['full_picture'])) {
            $out[] = $fb['full_picture'];
        }
        return array_values(array_unique($out));
    }

    /** Règles « #hashtag=Catégorie ». */
    private static function rules(): array
    {
        $cats = Content::categories();
        $out = [];
        foreach (preg_split('/\R/u', (string) Settings::get('fb_rules', '')) ?: [] as $l) {
            if (preg_match('/^\s*(#[\p{L}\p{N}_]+)\s*=\s*(.+?)\s*$/u', $l, $m) && in_array($m[2], $cats, true)) {
                $out[$m[1]] = $m[2];
            }
        }
        return $out;
    }

    private static function linkify(string $escaped): string
    {
        return (string) preg_replace('#(https?://[^\s<]+)#', '<a href="$1" target="_blank" rel="noopener">$1</a>', $escaped);
    }

    private static function call(string $method, string $path, array $params = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extension PHP cURL absente.');
        }
        $token = Settings::secret('fb_token');
        $url = self::GRAPH . $path;
        $ch = curl_init();
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]];
        if ($method === 'POST') {
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        } else {
            $opts[CURLOPT_URL] = $url;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Facebook injoignable : ' . $err);
        }
        $j = json_decode((string) $raw, true);
        if ($code !== 200 || !is_array($j)) {
            $m = is_array($j) ? (string) ($j['error']['message'] ?? '') : '';
            throw new RuntimeException('Facebook a refusé la demande' . ($m !== '' ? ' : ' . $m : ' (code ' . $code . ')'));
        }
        return $j;
    }
}
