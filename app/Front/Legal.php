<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Data\Fiches;
use App\Data\Index;

/**
 * Pages légales : mentions légales, confidentialité (RGPD), cookies.
 * Texte de base rédigé à partir du fonctionnement réel du site et des réglages
 * « Mentions légales ». Si l'équipe crée une page à la même adresse dans le
 * back-office, c'est elle qui s'affiche.
 */
final class Legal
{
    public const PAGES = [
        'mentions' => ['/mentions-legales/', 'Mentions légales', 'Legal notice'],
        'confidentialite' => ['/confidentialite/', 'Politique de confidentialité', 'Privacy policy'],
        'cookies' => ['/cookies/', 'Cookies et traceurs', 'Cookies and trackers'],
    ];

    public static function page(Request $req, string $key): Response
    {
        [$path, $fr, $en] = self::PAGES[$key];
        $s = Index::byPath($path);
        if ($s && Index::visible($s) && ($doc = Fiches::get((int) $s['id']))) {
            return Fiche::show($req, $doc);
        }
        $g = fn (string $k, string $d = '') => trim((string) Settings::get('legal.' . $k, $d));
        $email = $g('email') ?: (string) Settings::get('general.contact_email', '');
        $vars = [
            'key' => $key,
            'publisher' => $g('publisher', 'Sochaux Rétro'),
            'status' => $g('status'),
            'address' => safe_html($g('address')),
            'registration' => $g('registration'),
            'director' => $g('director'),
            'email' => $email,
            'phone' => $g('phone'),
            'host' => safe_html($g('host')),
            'privacy' => $g('privacy_contact') ?: $email,
            'extra' => safe_html($g('extra')),
            'site' => (string) Settings::get('general.site_name', 'Sochaux Rétro'),
            'base' => base_url(),
            'aiDays' => (int) Settings::get('ai.log_retention_days', 365),
            'aiOn' => (bool) Settings::get('ai.enabled', false),
            'donations' => (bool) Settings::get('donations.enabled', false),
            'receipts' => (bool) Settings::get('donations.tax_receipts', false),
            'analytics' => (string) Settings::get('privacy.analytics_id', '') !== '',
            'updated' => '2026-10-03',
        ];
        $title = \App\Services\I18n::isEn() ? $en : $fr;
        return Pages::render('legal/' . $key, $vars + ['title' => $title], [
            'title' => $title,
            'description' => $title . ' — ' . $vars['site'],
            'styles' => ['css/legal.css'],
            'body_class' => 'page-legal',
        ]);
    }
}
