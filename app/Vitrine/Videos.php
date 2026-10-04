<?php
declare(strict_types=1);

namespace App\Vitrine;

use App\Core\JsonStore;
use App\Core\Response;
use App\Core\Settings;

/**
 * Dernières vidéos de la chaîne YouTube de l'association, d'après son flux public (RSS, sans
 * clé d'API). La tâche planifiée relit le flux toutes les 6 heures et copie les vignettes sur
 * le serveur : les pages ne contactent jamais YouTube (rien n'est chargé chez Google avant un
 * clic du visiteur).
 */
final class Videos
{
    private const CACHE = STORAGE_PATH . '/vitrine/videos.json';
    private const THUMBS = STORAGE_PATH . '/vitrine/videos';
    private const EVERY = 6 * 3600;

    /** Vidéos en cache : [id, title, date, url]. @return list<array{id:string,title:string,date:string,url:string}> */
    public static function latest(int $n = 6): array
    {
        $c = JsonStore::read(self::CACHE, []) ?: [];
        $out = [];
        foreach ($c['videos'] ?? [] as $v) {
            if (is_file(self::THUMBS . '/' . $v['id'] . '.jpg')) {
                $out[] = $v;
            }
            if (count($out) >= $n) {
                break;
            }
        }
        return $out;
    }

    /** Lien de la chaîne (réseaux sociaux du musée). */
    public static function channelUrl(): string
    {
        $u = (string) Settings::get('social.youtube', '');
        return preg_match('#^https://(www\.)?youtube\.com/#i', $u) ? $u : '';
    }

    /** Tâche planifiée : relit le flux (toutes les 6 heures au plus) et copie les vignettes manquantes. */
    public static function refresh(bool $force = false, ?callable $fetch = null): ?string
    {
        $fetch ??= [self::class, 'download'];
        $c = JsonStore::read(self::CACHE, []) ?: [];
        if (!$force && time() - (int) ($c['at'] ?? 0) < self::EVERY) {
            return null;
        }
        $channel = self::channelId($c, $fetch);
        if ($channel === null) {
            JsonStore::write(self::CACHE, ['at' => time(), 'error' => 'chaîne introuvable'] + $c);
            return 'chaîne YouTube introuvable (Réglages du site de l’association)';
        }
        $xml = $fetch('https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode($channel));
        $videos = $xml !== null ? self::parse($xml) : [];
        if (!$videos) {
            JsonStore::write(self::CACHE, ['at' => time(), 'channel' => $channel, 'error' => 'flux illisible'] + $c);
            return 'flux YouTube illisible';
        }
        if (!is_dir(self::THUMBS)) {
            mkdir(self::THUMBS, 0775, true);
        }
        $keep = [];
        foreach (array_slice($videos, 0, 12) as $v) {
            $file = self::THUMBS . '/' . $v['id'] . '.jpg';
            $keep[] = basename($file);
            if (!is_file($file)) {
                $img = $fetch('https://i.ytimg.com/vi/' . rawurlencode($v['id']) . '/hqdefault.jpg');
                $info = $img !== null ? @getimagesizefromstring($img) : false;
                if ($info && $info[0] >= 200) {
                    file_put_contents($file, $img, LOCK_EX);
                }
            }
        }
        // Vignettes des vidéos sorties de la liste : effacées.
        foreach (glob(self::THUMBS . '/*.jpg') ?: [] as $f) {
            if (!in_array(basename($f), $keep, true)) {
                @unlink($f);
            }
        }
        JsonStore::write(self::CACHE, ['at' => time(), 'channel' => $channel, 'videos' => array_slice($videos, 0, 12)]);
        return count($videos) . ' vidéo(s) dans le flux';
    }

    /** Identifiant « UC… » : réglé, dans l'adresse de la chaîne, ou lu sur sa page publique. */
    private static function channelId(array $cache, callable $fetch): ?string
    {
        $set = trim((string) Settings::get('vitrine.youtube_channel', ''));
        if (preg_match('/^UC[\w-]{22}$/', $set)) {
            return $set;
        }
        $url = self::channelUrl();
        if ($url === '') {
            return null;
        }
        if (preg_match('#/channel/(UC[\w-]{22})#', $url, $m)) {
            return $m[1];
        }
        if (!empty($cache['channel']) && ($cache['channel_from'] ?? '') === $url) {
            return (string) $cache['channel'];
        }
        $html = $fetch($url);
        if ($html !== null && (preg_match('#<link rel="canonical" href="https://www\.youtube\.com/channel/(UC[\w-]{22})"#', $html, $m) || preg_match('/"(?:externalId|channelId)":"(UC[\w-]{22})"/', $html, $m))) {
            JsonStore::update(self::CACHE, fn ($c) => ['channel' => $m[1], 'channel_from' => $url] + ($c ?: []), []);
            return $m[1];
        }
        return null;
    }

    /** Flux Atom de YouTube → vidéos (plus récente d'abord). */
    public static function parse(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($prev);
        if (!$doc) {
            return [];
        }
        $out = [];
        foreach ($doc->entry as $e) {
            $yt = $e->children('http://www.youtube.com/xml/schemas/2015');
            $id = (string) ($yt->videoId ?? '');
            if (!preg_match('/^[\w-]{11}$/', $id)) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'title' => mb_substr(trim((string) $e->title), 0, 200),
                'date' => substr((string) $e->published, 0, 10),
                'url' => 'https://www.youtube.com/watch?v=' . $id,
            ];
        }
        usort($out, fn ($a, $b) => strcmp($b['date'], $a['date']));
        return $out;
    }

    /** GET /videos/miniature/{id}.jpg : vignette copiée sur le serveur. */
    public static function thumb(string $id): ?Response
    {
        if (!preg_match('/^[\w-]{11}$/', $id) || !is_file($f = self::THUMBS . "/$id.jpg")) {
            return null;
        }
        $res = new Response('', 200, ['Content-Type' => 'image/jpeg', 'Content-Length' => (string) filesize($f), 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
        $res->file = $f;
        return $res;
    }

    private static function download(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SochauxRetro/1.0; +' . Host::base() . ')',
            CURLOPT_HTTPHEADER => ['Accept-Language: fr-FR,fr;q=0.9'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return is_string($body) && $code >= 200 && $code < 300 && $body !== '' ? $body : null;
    }
}
