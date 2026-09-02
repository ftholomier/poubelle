<?php

declare(strict_types=1);

namespace App;

/**
 * Rendu des gabarits PHP, avec gabarit englobant optionnel.
 */
final class View
{
    /**
     * @param array<string,mixed> $data
     */
    public static function render(string $template, array $data = [], string $layout = 'layout'): void
    {
        $content = self::capture($template, $data);

        if ($layout === '') {
            echo $content;
            return;
        }

        echo self::capture($layout, $data + ['content' => $content]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function capture(string $template, array $data = []): string
    {
        $file = APP_VIEWS . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Gabarit introuvable : views/{$template}.php");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    /** Échappement HTML — à utiliser sur toute donnée insérée dans une page. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Insertion d'un objet JSON dans une balise script, sans risque de fermeture prématurée.
     */
    public static function json(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    /**
     * URL d'un fichier statique, suffixée par sa date de modification
     * pour que le navigateur récupère bien la dernière version.
     */
    public static function asset(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $file = APP_PUBLIC . $path;

        return is_file($file) ? $path . '?v=' . filemtime($file) : $path;
    }

    /**
     * Insère un fichier SVG de public/ directement dans la page.
     *
     * Un logo posé dans une balise « img » est une image close : ni
     * « currentColor » ni les variables de charte ne l'atteignent, et il faut
     * autant de fichiers que de fonds. Inséré dans le document, le même tracé
     * suit la couleur du texte et la couleur dominante du site.
     */
    public static function inlineSvg(string $path): string
    {
        $file = APP_PUBLIC . '/' . ltrim($path, '/');
        if (!is_file($file) || !str_ends_with($file, '.svg')) {
            return '';
        }

        return (string) file_get_contents($file);
    }

    /**
     * Carte d'imports des modules JavaScript.
     *
     * Le suffixe de version posé par asset() ne s'applique qu'au fichier
     * appelé depuis la page. Les modules qu'il importe à son tour — « ./ui.js »,
     * « ./particles/ParticleField.js » — gardent une URL nue, que le navigateur
     * met en cache sans jamais pouvoir l'invalider. Après une mise à jour, il
     * exécute donc un point d'entrée récent avec des dépendances périmées :
     * les modules se chargent, mais il leur manque ce que le nouveau code
     * attend, et la page s'arrête au premier appel manquant.
     *
     * Cette carte associe chaque module à son URL versionnée. Le navigateur
     * applique la correspondance après avoir résolu les chemins relatifs, si
     * bien qu'aucun fichier JavaScript n'a besoin d'être modifié.
     *
     * @return string le JSON à placer dans une balise <script type="importmap">
     */
    public static function importMap(): string
    {
        $root = APP_PUBLIC . '/assets/js';
        if (!is_dir($root)) {
            return '{"imports":{}}';
        }

        $imports = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'js') {
                continue;
            }
            $url = '/assets/js/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $imports[$url] = $url . '?v=' . $file->getMTime();
        }

        ksort($imports);

        return (string) json_encode(
            ['imports' => $imports],
            JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }
}
