<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Fiches;
use App\Data\Media;

/**
 * Vignettes des vidéos (YouTube, Dailymotion, Vimeo, Rutube), copiées une fois sur le serveur
 * (storage/media/originals/_video/{hébergeur}-{id}.jpg) : le lecteur affiché avant l'accord
 * du visiteur montre l'image de la vidéo sans contacter l'hébergeur.
 * Un échec est retenté au plus tôt une semaine plus tard.
 */
final class VideoThumbs
{
    private const STATE = STORAGE_PATH . '/cache/video-thumbs.json';
    private const RETRY = 7 * 86400;
    public const GONE = STORAGE_PATH . '/videos-indisponibles.json';
    /** Vidéos lisibles seulement chez l'hébergeur (YouTube : lecture hors YouTube interdite par la chaîne, LFP…). */
    public const BLOCKED = STORAGE_PATH . '/videos-bloquees.json';
    private const CHECKED = STORAGE_PATH . '/cache/videos-verifiees.json';

    /** Adresse de l'image d'une vidéo chez son hébergeur (ou de la page qui la donne). */
    public static function sourceUrl(string $provider, string $id): ?string
    {
        $id = rawurlencode($id);
        return match ($provider) {
            'youtube' => "https://i.ytimg.com/vi/$id/hqdefault.jpg",
            'dailymotion' => "https://api.dailymotion.com/video/$id?fields=thumbnail_720_url,thumbnail_url",
            'rutube' => "https://rutube.ru/api/video/$id/thumbnail/?redirect=1",
            'vimeo' => "https://vimeo.com/api/oembed.json?url=" . rawurlencode("https://vimeo.com/$id"),
            default => null,
        };
    }

    public static function file(string $provider, string $id): string
    {
        // Nom de fichier sûr, quoi que contienne l'identifiant enregistré.
        return Media::ORIGINALS . '/_video/' . preg_replace('/[^\w-]/', '', $provider) . '-' . preg_replace('/[^\w-]/', '', $id) . '.jpg';
    }

    /**
     * @param ?callable(string):?string $fetch téléchargement (remplaçable pour les essais)
     * @return array{faites:int, echecs:int, restantes:int}
     */
    public static function run(int $max = 100, ?callable $fetch = null): array
    {
        $fetch ??= [self::class, 'download'];
        $state = JsonStore::read(self::STATE, []) ?: [];
        $done = ['faites' => 0, 'echecs' => 0, 'restantes' => 0];
        $seen = [];
        foreach (Fiches::all() as $doc) {
            foreach ($doc['videos'] ?? [] as $v) {
                $embed = video_embed($v);
                if (!$embed || !$embed['id'] || !self::sourceUrl($embed['provider'], (string) $embed['id'])) {
                    continue;
                }
                $key = $embed['provider'] . '-' . $embed['id'];
                if (isset($seen[$key]) || is_file(self::file($embed['provider'], (string) $embed['id']))) {
                    continue;
                }
                $seen[$key] = true;
                if (time() - (int) ($state[$key] ?? 0) < self::RETRY) {
                    continue;
                }
                if ($done['faites'] + $done['echecs'] >= $max) {
                    $done['restantes']++;
                    continue;
                }
                $data = self::fetchImage($embed['provider'], (string) $embed['id'], $fetch);
                if ($data !== null) {
                    $file = self::file($embed['provider'], (string) $embed['id']);
                    if (!is_dir(dirname($file))) {
                        mkdir(dirname($file), 0775, true);
                    }
                    file_put_contents($file, $data, LOCK_EX);
                    unset($state[$key]);
                    $done['faites']++;
                } else {
                    $state[$key] = time();
                    $done['echecs']++;
                }
            }
        }
        JsonStore::write(self::STATE, $state);
        return $done;
    }

    /** Vidéos retirées chez l'hébergeur (supprimées, privées, intégration interdite) : clé « hébergeur-id » => motif. */
    public static function gone(): array
    {
        static $gone = null;
        return $gone ??= array_map(fn ($x) => (string) ($x['why'] ?? ''), (array) (JsonStore::read(self::GONE, []) ?: []));
    }

    /** Vidéos YouTube lisibles seulement sur YouTube : clé « youtube-id » => motif. */
    public static function blocked(): array
    {
        static $b = null;
        return $b ??= array_map(fn ($x) => (string) ($x['why'] ?? ''), (array) (JsonStore::read(self::BLOCKED, []) ?: []));
    }

    /**
     * Disponibilité des vidéos, revue chaque semaine (une vidéo revenue est retirée des listes ; une
     * erreur réseau ne change rien) :
     * - Dailymotion (API publique) : supprimée, privée ou non publiée → masquée (« gone »).
     * - YouTube (oEmbed, puis le lecteur intégré lui-même, comme le site l'affiche) : supprimée →
     *   masquée ; lecture interdite hors YouTube par la chaîne (vidéos de la LFP : le lecteur affiche
     *   un écran noir) → montrée en image avec un lien « Regarder sur YouTube » (« blocked »).
     * @param ?callable(string):array{0:int,1:?string} $get requête (remplaçable pour les essais)
     * @return array{verifiees:int, indisponibles:int, bloquees:int}
     */
    public static function check(int $max = 60, ?callable $get = null, float $budget = 60): array
    {
        $get ??= [self::class, 'request'];
        $end = microtime(true) + $budget;
        $gone = JsonStore::read(self::GONE, []) ?: [];
        $blocked = JsonStore::read(self::BLOCKED, []) ?: [];
        $seen = JsonStore::read(self::CHECKED, []) ?: [];
        $n = 0;
        $keys = [];
        foreach (Fiches::all() as $doc) {
            foreach ($doc['videos'] ?? [] as $v) {
                $e = video_embed($v);
                if (in_array($e['provider'] ?? '', ['dailymotion', 'youtube'], true) && $e['id']) {
                    $keys[$e['provider'] . '-' . $e['id']] = [$e['provider'], (string) $e['id']];
                }
            }
        }
        foreach ($keys as $key => [$provider, $id]) {
            if ($n >= $max || microtime(true) > $end || time() - (int) ($seen[$key] ?? 0) < self::RETRY) {
                continue;
            }
            $r = $provider === 'youtube' ? self::youtubeState($id, $get) : self::dailymotionState($id, $get);
            if ($r === null) {
                continue; // réseau ou hébergeur en panne, réponse illisible : on réessaiera
            }
            $n++;
            $seen[$key] = time();
            unset($gone[$key], $blocked[$key]);
            if ($r[0] === 'gone') {
                $gone[$key] = ['why' => $r[1], 'at' => date('c')];
            } elseif ($r[0] === 'blocked') {
                $blocked[$key] = ['why' => $r[1], 'at' => date('c')];
            }
        }
        $gone = array_intersect_key($gone, $keys);
        $blocked = array_intersect_key($blocked, $keys);
        JsonStore::write(self::GONE, $gone);
        JsonStore::write(self::BLOCKED, $blocked);
        JsonStore::write(self::CHECKED, array_intersect_key($seen, $keys));
        return ['verifiees' => $n, 'indisponibles' => count($gone), 'bloquees' => count($blocked)];
    }

    /** Dailymotion : ['ok'], ['gone', motif] ou null (réessayer). */
    private static function dailymotionState(string $id, callable $get): ?array
    {
        [$code, $body] = $get('https://api.dailymotion.com/video/' . rawurlencode($id) . '?fields=id,private,status,allow_embed');
        if ($code === 0 || $code >= 500 || $code === 429) {
            return null;
        }
        $j = json_decode((string) $body, true) ?: [];
        $why = match (true) {
            $code === 404 || $code === 410 => 'supprimée',
            $code === 403 || !empty($j['private']) => 'privée',
            isset($j['status']) && $j['status'] !== 'published' => 'indisponible (' . $j['status'] . ')',
            default => null,
        };
        return $why ? ['gone', $why] : ['ok'];
    }

    /**
     * YouTube : ['ok'], ['gone', motif], ['blocked', motif] ou null (réessayer). oEmbed dit si la vidéo
     * existe ; le lecteur intégré (youtube-nocookie, celui du site) dit si elle s'y lit : « OK », ou
     * « UNPLAYABLE » / « LOGIN_REQUIRED » quand la chaîne réserve la lecture à YouTube (oEmbed répond
     * pourtant « intégrable » pour les vidéos de la LFP).
     */
    private static function youtubeState(string $id, callable $get): ?array
    {
        [$code] = $get('https://www.youtube.com/oembed?format=json&url=' . rawurlencode('https://www.youtube.com/watch?v=' . $id));
        if ($code === 0 || $code >= 500 || $code === 429) {
            return null;
        }
        if ($code === 400 || $code === 404) {
            return ['gone', 'supprimée'];
        }
        if ($code === 401 || $code === 403) {
            return ['blocked', 'lecture réservée à YouTube'];
        }
        [$c2, $page] = $get('https://www.youtube-nocookie.com/embed/' . rawurlencode($id));
        if ($c2 !== 200 || !is_string($page) || !preg_match('/[Pp]layabilityStatus\\\\?"\s*:\s*\{\s*\\\\?"status\\\\?"\s*:\s*\\\\?"([A-Z_]+)/', $page, $m)) {
            return null;
        }
        return match ($m[1]) {
            'OK' => ['ok'],
            'ERROR' => ['gone', 'supprimée'],
            'UNPLAYABLE', 'LOGIN_REQUIRED', 'AGE_CHECK_REQUIRED', 'CONTENT_CHECK_REQUIRED' => ['blocked', 'lecture réservée à YouTube'],
            default => null,
        };
    }

    /** @return array{0:int,1:?string} code HTTP (0 = pas de réponse) et contenu */
    private static function request(string $url): array
    {
        $ch = curl_init($url);
        // Référent du site : le lecteur intégré de YouTube répond comme il le ferait pour nos visiteurs.
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'SochauxRetro/1.0 (' . base_url() . ')', CURLOPT_REFERER => base_url() . '/']);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        usleep(200000);
        return [$code, is_string($body) ? $body : null];
    }

    private static function fetchImage(string $provider, string $id, callable $fetch): ?string
    {
        $url = self::sourceUrl($provider, $id);
        $body = $url ? $fetch($url) : null;
        if (in_array($provider, ['vimeo', 'dailymotion'], true) && $body !== null) {
            $j = json_decode($body, true) ?: [];
            $thumb = $j['thumbnail_720_url'] ?? $j['thumbnail_url'] ?? null;
            $body = is_string($thumb) && str_starts_with($thumb, 'https://') ? $fetch($thumb) : null;
        }
        // Une vraie image, pas une page d'erreur ni l'image grise « vidéo indisponible » (120 px).
        $info = $body !== null ? @getimagesizefromstring($body) : false;
        return $info && $info[0] >= 200 ? $body : null;
    }

    private static function download(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'SochauxRetro/1.0 (' . base_url() . ')',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        usleep(200000);
        return $code === 200 && is_string($body) && $body !== '' && strlen($body) < 5_000_000 ? $body : null;
    }
}
