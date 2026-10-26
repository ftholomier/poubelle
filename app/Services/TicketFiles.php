<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Pièces jointes des tickets (captures d'écran collées, photos, PDF, documents) : rangées hors du
 * site public (storage/tickets/<n° du ticket>/), sous un nom tiré au hasard (jamais celui envoyé),
 * et servies seulement aux membres connectés du back-office (TicketsAdmin::file). Le type réel de
 * chaque fichier est vérifié (pas seulement son extension) ; ni SVG, ni HTML, ni script.
 */
final class TicketFiles
{
    public const DIR = STORAGE_PATH . '/tickets';
    /** Fichiers par envoi (ticket, modification ou réponse). */
    public const MAX_FILES = 10;
    /** Taille d'un fichier (dans la limite du serveur, voir maxSize()). */
    public const MAX_SIZE = 20 * 1048576;
    /** Extension => types réels acceptés. */
    public const TYPES = [
        'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'], 'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'mp4' => ['video/mp4'], 'mov' => ['video/quicktime'], 'webm' => ['video/webm'],
    ];
    /** Types montrés dans la page (aperçu) ; les autres sont toujours téléchargés. */
    private const INLINE = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf', 'video/mp4', 'video/quicktime', 'video/webm'];

    /** Essais : déplacement du fichier envoyé (move_uploaded_file refuse un fichier qui n'a pas été envoyé). */
    public static ?\Closure $mover = null;

    /** Taille maximale d'un fichier, serveur compris (upload_max_filesize). */
    public static function maxSize(): int
    {
        $ini = (int) (function_exists('ini_parse_quantity') ? @ini_parse_quantity((string) ini_get('upload_max_filesize')) : 0);
        return $ini > 0 ? min(self::MAX_SIZE, $ini) : self::MAX_SIZE;
    }

    /** Taille maximale d'un envoi complet (post_max_size, moins la place des textes). */
    public static function maxTotal(): int
    {
        $ini = (int) (function_exists('ini_parse_quantity') ? @ini_parse_quantity((string) ini_get('post_max_size')) : 0);
        return $ini > 0 ? max(1048576, $ini - 256 * 1024) : self::MAX_FILES * self::MAX_SIZE;
    }

    /**
     * Fichiers envoyés (champ « pj[] ») vérifiés, avant tout enregistrement. Exception claire au
     * premier fichier refusé. @return list<array{tmp:string,name:string,ext:string,mime:string,size:int}>
     */
    public static function check(mixed $up): array
    {
        if (!is_array($up) || !isset($up['name'])) {
            return [];
        }
        $list = [];
        foreach ((array) $up['name'] as $i => $name) {
            $err = (int) (is_array($up['error']) ? ($up['error'][$i] ?? UPLOAD_ERR_NO_FILE) : $up['error']);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $name = self::cleanName((string) $name);
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                throw new \InvalidArgumentException('« ' . $name . ' » est trop lourd pour le serveur (' . self::human(self::maxSize()) . ' au plus par fichier).');
            }
            if ($err !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('« ' . $name . ' » n’a pas été reçu en entier : réessayez.');
            }
            if (count($list) >= self::MAX_FILES) {
                throw new \InvalidArgumentException(self::MAX_FILES . ' pièces jointes au plus par envoi.');
            }
            $tmp = (string) (is_array($up['tmp_name']) ? $up['tmp_name'][$i] : $up['tmp_name']);
            $size = (int) @filesize($tmp);
            if ($size > self::maxSize()) {
                throw new \InvalidArgumentException('« ' . $name . ' » dépasse ' . self::human(self::maxSize()) . '.');
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mime = is_file($tmp) ? ((new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '') : '';
            $ok = isset(self::TYPES[$ext]) && in_array($mime, self::TYPES[$ext], true)
                && (!str_starts_with($mime, 'image/') || @getimagesize($tmp) !== false);
            if (!$ok || $size === 0) {
                throw new \InvalidArgumentException('« ' . $name . ' » n’est pas accepté : images (PNG, JPG, GIF, WebP), PDF, textes, documents Word, Excel ou LibreOffice, ZIP, vidéos MP4, MOV ou WebM.');
            }
            $list[] = ['tmp' => $tmp, 'name' => $name, 'ext' => $ext === 'jpeg' ? 'jpg' : $ext, 'mime' => $mime, 'size' => $size];
        }
        return $list;
    }

    /** Range les fichiers vérifiés pour le ticket $ticket. @return list<array> pièces jointes enregistrées */
    public static function store(int $ticket, array $checked, array $user): array
    {
        if (!$checked) {
            return [];
        }
        $dir = self::DIR . '/' . $ticket;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Dossier des pièces jointes impossible à créer (droits de storage/ ?).');
        }
        $out = [];
        foreach ($checked as $f) {
            $id = bin2hex(random_bytes(8));
            $dest = $dir . '/' . $id . '.' . $f['ext'];
            $moved = self::$mover ? (self::$mover)($f['tmp'], $dest) : @move_uploaded_file($f['tmp'], $dest);
            if (!$moved) {
                throw new \RuntimeException('« ' . $f['name'] . ' » n’a pas pu être enregistré (espace disque ?).');
            }
            @chmod($dest, 0640);
            $out[] = ['id' => $id, 'name' => $f['name'], 'ext' => $f['ext'], 'mime' => $f['mime'], 'size' => $f['size'], 'at' => date('c'),
                'by' => ['id' => (string) ($user['id'] ?? ''), 'name' => (string) ($user['name'] ?? '')]];
        }
        return $out;
    }

    /** Fichier d'une pièce jointe sur le disque (null si absent). */
    public static function path(int $ticket, array $f): ?string
    {
        if (!preg_match('/^[0-9a-f]{16}$/', (string) ($f['id'] ?? '')) || !isset(self::TYPES[(string) ($f['ext'] ?? '')])) {
            return null;
        }
        $p = self::DIR . '/' . $ticket . '/' . $f['id'] . '.' . $f['ext'];
        return is_file($p) ? $p : null;
    }

    /** Pièce jointe $id d'un ticket (demande ou réponses), ou null. */
    public static function find(array $t, string $id): ?array
    {
        foreach (self::all($t) as $f) {
            if (($f['id'] ?? '') === $id) {
                return $f;
            }
        }
        return null;
    }

    /** Toutes les pièces jointes d'un ticket (demande puis réponses). @return list<array> */
    public static function all(array $t): array
    {
        $out = array_values((array) ($t['files'] ?? []));
        foreach ((array) ($t['comments'] ?? []) as $c) {
            array_push($out, ...array_values((array) ($c['files'] ?? [])));
        }
        return $out;
    }

    public static function remove(int $ticket, array $files): void
    {
        foreach ($files as $f) {
            if ($p = self::path($ticket, $f)) {
                @unlink($p);
            }
        }
    }

    /** Ticket supprimé : son dossier de pièces jointes aussi. */
    public static function removeAll(int $ticket): void
    {
        $dir = self::DIR . '/' . $ticket;
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    public static function isImage(array $f): bool
    {
        return str_starts_with((string) ($f['mime'] ?? ''), 'image/');
    }

    /** Montré dans la page (image, PDF, vidéo) plutôt que téléchargé. */
    public static function inline(array $f): bool
    {
        return in_array((string) ($f['mime'] ?? ''), self::INLINE, true);
    }

    public static function human(int $bytes): string
    {
        return $bytes >= 1048576 ? str_replace('.', ',', (string) round($bytes / 1048576, 1)) . ' Mo' : max(1, (int) round($bytes / 1024)) . ' Ko';
    }

    /** Nom affiché : sans chemin ni caractère de contrôle, 120 caractères au plus. */
    private static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F<>:"|?*]+/u', '_', $name));
        return mb_substr($name !== '' ? $name : 'fichier', -120);
    }
}
