<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Security;

/**
 * Publicité Google AdSense : emplacements configurables dans le back-office
 * (identifiant éditeur, blocs d'annonces, annonces automatiques, ads.txt).
 */
final class Ads
{
    /** Emplacements du site : clé => [libellé, identifiant de bloc par défaut (ancien site)] */
    public const SLOTS = [
        'home_mid' => ['Accueil — entre les pros et les occasions', '6990896186'],
        'listing' => ['Listes de pros — entre les résultats', '1955809887'],
        'pro_side' => ['Fiche pro — colonne de droite', '9768928712'],
        'pro_bottom' => ['Fiche pro — bas de page', '2501820981'],
        'blog_inline' => ['Article — dans le texte', '1955809887'],
        'blog_bottom' => ['Article — fin d\'article', '2501820981'],
    ];

    /** Identifiant éditeur : réglage du back-office, sinon ADSENSE_CLIENT du fichier .env. */
    public static function client(): string
    {
        $c = trim((string) Settings::get('ads.client', ''));
        return $c !== '' ? $c : trim((string) Env::get('ADSENSE_CLIENT', ''));
    }

    public static function enabled(): bool
    {
        return (bool) Settings::get('ads.enabled', false) && self::client() !== '';
    }

    /** En développement on affiche des emplacements factices pour visualiser la mise en page. */
    public static function demo(): bool
    {
        return Env::get('APP_ENV') === 'development' && (bool) Settings::get('ads.enabled', false);
    }

    public static function headScript(): string
    {
        if (!self::enabled() || self::demo() || Settings::get('ads.cmp', 'google') === 'own') {
            return ''; // avec notre propre bandeau, le script est chargé après le choix du visiteur
        }
        $npa = Settings::get('ads.cmp', 'google') === 'npa' ? ' data-npa-on-unknown-consent="true"' : '';
        return '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . e(self::client()) . '" crossorigin="anonymous"' . $npa . Security::attr() . '></script>';
    }

    public static function slot(string $key, string $class = ''): string
    {
        if (!self::enabled() && !self::demo()) {
            return '';
        }
        $cfg = Settings::get('ads.slots.' . $key, []);
        if (empty($cfg['on'])) {
            return '';
        }
        $label = e((string) Settings::get('ads.label', 'Publicité'));
        $id = trim((string) ($cfg['id'] ?? '')) ?: (self::SLOTS[$key][1] ?? '');
        if (self::demo() || $id === '') {
            return '<div class="ad ' . e($class) . '"><div class="ad-box is-demo" data-label="' . $label . '">Emplacement publicitaire « ' . e(self::SLOTS[$key][0] ?? $key) . ' »</div></div>';
        }
        $format = (string) ($cfg['format'] ?? 'auto');
        $fmt = $format === 'fluid' ? ' data-ad-format="fluid" data-ad-layout="in-article"' : ($format === 'rect' ? '' : ' data-ad-format="auto" data-full-width-responsive="true"');
        $test = Settings::get('ads.test_mode') ? ' data-adtest="on"' : '';
        $style = $format === 'rect' ? 'display:inline-block;width:330px;height:330px' : 'display:block';
        return '<div class="ad ' . e($class) . '"><div class="ad-box" data-label="' . $label . '"><ins class="adsbygoogle" style="' . $style . '" data-ad-client="' . e(self::client()) . '" data-ad-slot="' . e($id) . '"' . $fmt . $test . '></ins></div></div>';
    }

    public static function adsTxt(): string
    {
        $custom = trim((string) Settings::get('ads.ads_txt', ''));
        if ($custom !== '') {
            return $custom . "\n";
        }
        $client = self::client();
        if ($client === '') {
            return '';
        }
        return 'google.com, ' . str_replace('ca-', '', $client) . ", DIRECT, f08c47fec0942fa0\n";
    }
}
