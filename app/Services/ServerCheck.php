<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Ce que le site exige du serveur (docs/DEPLOIEMENT.md, § 1) : version de PHP, extensions,
 * dossiers inscriptibles ; et les dernières erreurs du journal PHP du site. Montré aux seuls
 * administrateurs (tableau de bord, Tâches planifiées, page « Arrêt de jeu »), pour régler
 * l'hébergement sans fouiller cPanel.
 */
final class ServerCheck
{
    public const PHP = '8.3.0';

    /** Extension => ce qui ne marche pas sans elle */
    private const EXTENSIONS = [
        'mbstring' => 'textes accentués',
        'intl' => 'recherche, adresses et classement des fiches',
        'gd' => 'vignettes des photos, images de partage',
        'curl' => 'IA Gemini, paiements en ligne',
        'sodium' => 'clés et mots de passe des réglages',
        'openssl' => 'connexions sécurisées, e-mails',
        'zip' => 'sauvegardes',
        'fileinfo' => 'envoi de fichiers',
        'dom' => 'export PDF, nettoyage des textes',
        'zlib' => 'export PDF',
        'ctype' => 'adresses des pages',
        'iconv' => 'adresses des fiches, export PDF',
    ];

    /** @return list<string> réglages à revoir, en phrases courtes (vide : tout est bon) */
    public static function problems(): array
    {
        $out = [];
        if (version_compare(PHP_VERSION, self::PHP, '<')) {
            $out[] = 'PHP ' . PHP_VERSION . ' : le site demande PHP 8.3 (cPanel › Sélectionner une version de PHP).';
        }
        foreach (self::EXTENSIONS as $ext => $use) {
            if (!extension_loaded($ext)) {
                $out[] = "Extension PHP « $ext » absente ($use) : à cocher dans cPanel › Sélectionner une version de PHP › Extensions.";
            }
        }
        if (function_exists('gd_info') && empty(gd_info()['WebP Support'])) {
            $out[] = 'GD sans le format WebP : les vignettes des photos ne peuvent pas être créées.';
        }
        foreach ([STORAGE_PATH, STORAGE_PATH . '/cache', STORAGE_PATH . '/sessions', STORAGE_PATH . '/logs', DATA_PATH, DATA_PATH . '/fiches', PUBLIC_PATH . '/media'] as $dir) {
            if (is_dir($dir) && !is_writable($dir)) {
                $out[] = 'Dossier « ' . self::rel($dir) . ' » non inscriptible par PHP : droits 755 dans le gestionnaire de fichiers de cPanel.';
            }
        }
        return $out;
    }

    /**
     * Dernières erreurs du journal PHP du site (première ligne de chaque erreur, chemins
     * raccourcis), les plus récentes en premier.
     * @return list<string>
     */
    public static function lastErrors(int $n = 8): array
    {
        $files = glob(STORAGE_PATH . '/logs/php-*.log') ?: [];
        rsort($files);
        $out = [];
        foreach ($files as $file) {
            $size = (int) @filesize($file);
            $fp = @fopen($file, 'rb');
            if (!$fp) {
                continue;
            }
            fseek($fp, max(0, $size - 262144));
            $tail = (string) stream_get_contents($fp);
            fclose($fp);
            $lines = array_values(array_filter(explode("\n", $tail), fn ($l) => str_starts_with($l, '[')));
            foreach (array_reverse($lines) as $l) {
                $out[] = mb_strimwidth(str_replace(APP_ROOT . '/', '', $l), 0, 400, '…');
                if (count($out) >= $n) {
                    return $out;
                }
            }
        }
        return $out;
    }

    private static function rel(string $dir): string
    {
        return ltrim(str_replace(APP_ROOT, '', $dir), '/') ?: '.';
    }
}
