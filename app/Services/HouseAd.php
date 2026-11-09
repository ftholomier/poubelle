<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Audit;
use App\Storage\Json;

/**
 * Bannière maison : une image et un lien choisis au back-office, affichés
 * sous l'en-tête de toutes les pages publiques, en plus d'AdSense.
 *
 * Deux images : une large pour l'ordinateur, une plus haute pour le mobile
 * (facultative : à défaut, la large est servie partout).
 */
final class HouseAd
{
    /** Formats conseillés, en pixels. */
    public const SIZES = ['desktop' => [1200, 150], 'mobile' => [640, 160]];
    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    private const MAX_BYTES = 1024 * 1024;

    private static ?array $cache = null;

    private static function file(): string
    {
        return Config::path('data') . '/private/house-ad.json';
    }

    private static function dir(): string
    {
        return Config::path('data') . '/uploads/promo';
    }

    /** @return array{enabled:bool, url:string, alt:string, desktop:string, mobile:string} */
    public static function settings(): array
    {
        if (self::$cache === null) {
            $s = Json::read(self::file());
            self::$cache = ['enabled' => (bool) ($s['enabled'] ?? false), 'url' => (string) ($s['url'] ?? ''),
                            'alt' => (string) ($s['alt'] ?? ''), 'desktop' => (string) ($s['desktop'] ?? ''),
                            'mobile' => (string) ($s['mobile'] ?? '')];
        }
        return self::$cache;
    }

    /** Ce qu'il faut afficher, ou null si la bannière est coupée ou incomplète. */
    public static function current(): ?array
    {
        $s = self::settings();
        return $s['enabled'] && $s['desktop'] !== '' && $s['url'] !== '' ? $s : null;
    }

    public static function src(string $name): string
    {
        return '/media/promo/' . rawurlencode($name);
    }

    /** Chemin relatif sous data/uploads d'une image en service, sinon null. */
    public static function path(string $id): ?string
    {
        $s = self::settings();
        foreach (['desktop', 'mobile'] as $k) {
            if ($s[$k] !== '' && pathinfo($s[$k], PATHINFO_FILENAME) === $id) {
                return 'promo/' . $s[$k];
            }
        }
        return null;
    }

    /**
     * @param array<string, array> $files entrées de $_FILES (desktop, mobile)
     * @return array<int, string> erreurs
     */
    public static function save(bool $enabled, string $url, string $alt, array $files, bool $dropMobile, int $userId): array
    {
        $s = self::settings();
        $errors = [];
        $url = trim($url);
        if ($url !== '' && (filter_var($url, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $url))) {
            $errors[] = 'Le lien doit être une adresse complète, commençant par https://';
            $url = $s['url'];
        }
        foreach (['desktop', 'mobile'] as $k) {
            $f = $files[$k] ?? null;
            if (!is_array($f) || (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $stored = self::store($f);
            if ($stored === '' || str_starts_with($stored, '!')) {
                $errors[] = ($k === 'desktop' ? 'Image ordinateur : ' : 'Image mobile : ') . ltrim($stored ?: '!envoi impossible.', '!');
                continue;
            }
            self::remove($s[$k]);
            $s[$k] = $stored;
        }
        if ($dropMobile && ($files['mobile']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            self::remove($s['mobile']);
            $s['mobile'] = '';
        }
        $s['url'] = $url;
        $s['alt'] = mb_substr(trim($alt), 0, 120);
        $s['enabled'] = $enabled;
        Json::write(self::file(), $s);
        self::$cache = $s;
        Audit::log('house_ad.updated', ['enabled' => $enabled, 'url' => $url], $userId);
        return $errors;
    }

    /** @return string nom du fichier, ou « !message » en cas de refus */
    private static function store(array $f): string
    {
        if ((int) $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            return '!envoi interrompu.';
        }
        if ((int) $f['size'] > self::MAX_BYTES) {
            return '!fichier trop lourd (1 Mo maximum).';
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $f['tmp_name']);
        if (!isset(self::MIMES[$mime])) {
            return '!format refusé (JPG, PNG, WebP ou GIF).';
        }
        if (!is_dir(self::dir())) {
            @mkdir(self::dir(), 0775, true);
        }
        // Nom neuf à chaque envoi : le cache des navigateurs ne garde pas l'ancienne.
        $name = bin2hex(random_bytes(8)) . '.' . self::MIMES[$mime];
        if (!@move_uploaded_file((string) $f['tmp_name'], self::dir() . '/' . $name)) {
            return '!enregistrement impossible.';
        }
        @chmod(self::dir() . '/' . $name, 0644);
        return $name;
    }

    private static function remove(string $name): void
    {
        if ($name !== '' && preg_match('/^[a-f0-9]{16}\.\w+$/', $name) === 1) {
            @unlink(self::dir() . '/' . $name);
        }
    }

    /** @return array{0:int,1:int} dimensions réelles d'une image en service */
    public static function dims(string $name): array
    {
        $info = $name !== '' ? @getimagesize(self::dir() . '/' . $name) : false;
        return $info ? [(int) $info[0], (int) $info[1]] : [0, 0];
    }
}
