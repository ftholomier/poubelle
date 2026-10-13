<?php
declare(strict_types=1);

namespace App\Widgets;

/**
 * Contexte d'un affichage : langue, thème, format, partenaire éventuel (identifiant public, jamais
 * la clé), et les adresses : liens vers le musée (comptés comme clics), images en adresse complète.
 */
final class Context
{
    public function __construct(
        public readonly string $widget,
        public readonly string $lang = 'fr',
        public readonly string $theme = 'clair',
        public readonly string $format = 'standard',
        public readonly string $partner = '',
        public readonly bool $preview = false,
    ) {
    }

    public function en(): bool
    {
        return $this->lang === 'en';
    }

    /** Texte français ou anglais selon la langue du widget. */
    public function tr(string $fr, string $en): string
    {
        return $this->lang === 'en' ? $en : $fr;
    }

    /** Adresse publique du musée, sans barre finale. */
    public static function base(): string
    {
        return rtrim(base_url() ?: (string) \App\Core\Club::s('base_url'), '/');
    }

    /** Adresse complète d'un chemin du musée. */
    public static function abs(string $path): string
    {
        return preg_match('#^https?://#', $path) ? $path : self::base() . '/' . ltrim($path, '/');
    }

    /** Chemin du musée dans la langue du widget (« /en/… » en anglais). */
    public function path(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ($this->lang === 'en' && !str_starts_with($path, '/en/') && $path !== '/en') {
            return $path === '/' ? '/en/' : '/en' . $path;
        }
        return $path;
    }

    /**
     * Lien vers une page du musée, compté comme clic (/w/clic/<widget>/?to=…) ; adresse complète
     * pour fonctionner dans l'iframe comme dans les données de l'API.
     */
    public function link(string $path): string
    {
        $q = ['to' => $this->path($path)];
        if ($this->partner !== '') {
            $q['p'] = $this->partner;
        }
        if ($this->preview) {
            return self::abs($this->path($path));
        }
        return self::abs('/w/clic/' . $this->widget . '/?' . http_build_query($q));
    }

    /** Hôte du musée affiché en petit dans chaque widget (« musee.fcsochauxretro.com »). */
    public static function host(): string
    {
        return (string) (parse_url(self::base(), PHP_URL_HOST) ?: \App\Core\Club::s('domain'));
    }
}
