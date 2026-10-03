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

    /** Adresse de l'image d'une vidéo chez son hébergeur (ou de la page qui la donne). */
    public static function sourceUrl(string $provider, string $id): ?string
    {
        $id = rawurlencode($id);
        return match ($provider) {
            'youtube' => "https://i.ytimg.com/vi/$id/hqdefault.jpg",
            'dailymotion' => "https://www.dailymotion.com/thumbnail/video/$id",
            'rutube' => "https://rutube.ru/api/video/$id/thumbnail/?redirect=1",
            'vimeo' => "https://vimeo.com/api/oembed.json?url=" . rawurlencode("https://vimeo.com/$id"),
            default => null,
        };
    }

    public static function file(string $provider, string $id): string
    {
        return Media::ORIGINALS . '/_video/' . $provider . '-' . $id . '.jpg';
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

    private static function fetchImage(string $provider, string $id, callable $fetch): ?string
    {
        $url = self::sourceUrl($provider, $id);
        $body = $url ? $fetch($url) : null;
        if ($provider === 'vimeo' && $body !== null) {
            $thumb = json_decode($body, true)['thumbnail_url'] ?? null;
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
