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
        // Sans OPcache, chaque page relit tout le code et les données calculées (plusieurs Mo) : nettement plus lent.
        if (PHP_SAPI !== 'cli' && !(extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN))) {
            $out[] = 'OPcache désactivé : chaque page relit tout le code, le site et le back-office sont nettement plus lents (cPanel › Sélectionner une version de PHP › Extensions : cocher « opcache »).';
        }
        if (($o = self::opcache()) && ($o['full'] || $o['oom'] > 0)) {
            $out[] = 'Mémoire d’OPcache trop petite (' . $o['used'] . ' Mo utilisés sur ' . $o['total'] . ') : le code et les données sont relus et recompilés trop souvent. À augmenter (réglage opcache.memory_consumption, 256 Mo) dans cPanel › Sélectionner une version de PHP › Options, ou auprès de l’hébergeur.';
        }
        if (PHP_SAPI !== 'cli' && !self::finishesEarly()) {
            $out[] = 'PHP ne sait pas terminer une page avant la fin du travail qui la suit (mode ' . PHP_SAPI . ') : après un enregistrement, le recalcul des statistiques fait attendre la personne. Un PHP en mode PHP-FPM ou LiteSpeed (cPanel, ou l’hébergeur) l’évite.';
        }
        if (\App\Core\Settings::get('app.push', true) && !WebPush::available()) {
            $out[] = 'Notifications de l’appli du musée impossibles : il faut les extensions « openssl » (courbe P-256, ECDH, AES-GCM) et « curl » (cPanel › Sélectionner une version de PHP › Extensions).';
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
     * Mémoire d'OPcache (code et caches compilés gardés en mémoire) : null s'il est absent ou que
     * l'hébergeur en cache l'état.
     * @return array{used:int,total:int,full:bool,oom:int,scripts:int,hits:float}|null
     */
    public static function opcache(): ?array
    {
        $st = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        if (!is_array($st) || empty($st['opcache_enabled'])) {
            return null;
        }
        $m = $st['memory_usage'] ?? [];
        $used = (int) ($m['used_memory'] ?? 0) + (int) ($m['wasted_memory'] ?? 0);
        return [
            'used' => (int) round($used / 1048576),
            'total' => (int) round(($used + (int) ($m['free_memory'] ?? 0)) / 1048576),
            'full' => !empty($st['cache_full']),
            'oom' => (int) ($st['opcache_statistics']['oom_restarts'] ?? 0),
            'scripts' => (int) ($st['opcache_statistics']['num_cached_scripts'] ?? 0),
            'hits' => round((float) ($st['opcache_statistics']['opcache_hit_rate'] ?? 0), 1),
        ];
    }

    /** La page part avant le travail fait après elle (recalculs, mesure d'audience) : PHP-FPM ou LiteSpeed. */
    public static function finishesEarly(): bool
    {
        return function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');
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
