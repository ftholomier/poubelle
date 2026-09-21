<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Audit;

/**
 * Réception de fichiers.
 * Le type MIME réel est vérifié avec finfo (pas l'extension envoyée, pas le
 * Content-Type déclaré), le fichier est renommé et stocké hors de /public.
 */
final class Upload
{
    /**
     * @param  array $file  une entrée de $_FILES
     * @return array{ok:bool,error:string,path:string,name:string,size:int}
     */
    public static function store(array $file, string $kind, string $id, string $allow = 'cv'): array
    {
        $fail = static fn(string $message): array
            => ['ok' => false, 'error' => $message, 'path' => '', 'name' => '', 'size' => 0];

        $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code === UPLOAD_ERR_NO_FILE) {
            return $fail('');   // pas de fichier : ce n'est pas une erreur en soi
        }
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return $fail(I18n::t('form.err_file_size'));
        }
        if ($code !== UPLOAD_ERR_OK) {
            Audit::log('upload.error', ['code' => $code, 'kind' => $kind]);
            return $fail(I18n::t('form.error'));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return $fail(I18n::t('form.error'));
        }

        $size = (int) ($file['size'] ?? 0);
        $max = (int) Config::get('uploads.max_bytes', 5 * 1024 * 1024);
        if ($size <= 0 || $size > $max) {
            return $fail(I18n::t('form.err_file_size'));
        }

        // Le type réel prime sur ce que le navigateur annonce.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $allowed = (array) Config::get($allow === 'image' ? 'uploads.image_mimes' : 'uploads.cv_mimes', []);

        if (!isset($allowed[$mime])) {
            Audit::log('upload.rejected', ['mime' => $mime, 'kind' => $kind]);
            return $fail(I18n::t('form.err_file_type'));
        }

        $extension = $allowed[$mime];
        $relative = $kind . '/' . preg_replace('/[^a-z0-9_-]/i', '', $id) . '.' . $extension;
        $target = Config::path('data') . '/uploads/' . $relative;

        if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
            return $fail(I18n::t('form.error'));
        }
        if (!@move_uploaded_file($tmp, $target)) {
            Audit::log('upload.move_failed', ['kind' => $kind]);
            return $fail(I18n::t('form.error'));
        }
        @chmod($target, 0644);

        return [
            'ok'    => true,
            'error' => '',
            'path'  => $relative,
            'name'  => self::safeName((string) ($file['name'] ?? 'document.' . $extension)),
            'size'  => $size,
        ];
    }

    /** Nom d'affichage assaini : jamais utilisé comme chemin. */
    private static function safeName(string $name): string
    {
        $name = basename($name);
        $name = (string) preg_replace('/[^\w\s.\-()]+/u', '', $name);
        return mb_substr(trim($name), 0, 120) ?: 'document';
    }

    public static function delete(string $relative): void
    {
        if ($relative === '') {
            return;
        }
        $path = Config::path('data') . '/uploads/' . $relative;
        $base = realpath(Config::path('data') . '/uploads');
        $real = realpath($path);
        // Garde-fou : on ne supprime que sous data/uploads.
        if ($base !== false && $real !== false && str_starts_with($real, $base)) {
            @unlink($real);
        }
    }
}
