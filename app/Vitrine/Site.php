<?php
declare(strict_types=1);

namespace App\Vitrine;

use App\Core\Response;
use App\Core\Settings;
use App\Core\View;

/**
 * Éléments communs aux pages du site de l'association : ouverture au public, rendu dans la
 * mise en page, menu, métadonnées (SEO, partage).
 */
final class Site
{
    /** Affichage de l'aperçu du back-office (tout est visible, même fermé). */
    public static bool $preview = false;

    /** Site ouvert au public (Réglages du pavé). */
    public static function open(): bool
    {
        return (bool) Settings::get('vitrine.open', false);
    }

    /** Rien ne doit être indexé : site fermé, masqué aux moteurs, ou aperçu. */
    public static function hidden(): bool
    {
        return self::$preview || !self::open() || (bool) Settings::get('vitrine.noindex', false);
    }

    public static function name(): string
    {
        return trim((string) Settings::get('vitrine.name', 'Sochaux Rétro')) ?: 'Sochaux Rétro';
    }

    public static function tagline(): string
    {
        return trim((string) Settings::get('vitrine.tagline', ''));
    }

    /** E-mail de réception (adhésions, bénévoles, messages) : celui du pavé, sinon celui du musée. */
    public static function email(): string
    {
        $e = trim((string) Settings::get('vitrine.email', ''));
        return $e !== '' ? $e : trim((string) Settings::get('general.contact_email', ''));
    }

    /** Adresse postale : celle du pavé, sinon celle du siège (mentions légales). */
    public static function address(): string
    {
        $a = trim((string) Settings::get('vitrine.address', ''));
        return safe_html($a !== '' ? $a : (string) Settings::get('legal.address', ''));
    }

    /**
     * Page complète dans la mise en page du site.
     * $page : title, full_title, description, image, active, body_class, jsonld, noindex, path, styles, scripts
     */
    public static function render(string $tpl, array $vars, array $page, int $status = 200): Response
    {
        $page['path'] ??= self::path();
        $html = View::render('vitrine/' . $tpl, $vars + ['page' => $page], 'vitrine/layout');
        return Response::html($html, $status);
    }

    /** Adresse de la page affichée, sans le préfixe de l'aperçu ni les paramètres. */
    public static function path(): string
    {
        $p = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        if (Host::isPreview($p)) {
            $p = substr($p, strlen(Host::PREVIEW)) ?: '/';
        }
        return $p;
    }

    /** Menu principal : [clé, libellé, lien, sous-menu [[libellé, lien, externe ?]]]. */
    public static function nav(): array
    {
        $u = fn (string $p) => Host::url($p);
        $actions = [];
        foreach (Content::actions() as $a) {
            $actions[] = [$a['menu'] ?? $a['title'], $u('/nos-actions/' . $a['slug'] . '/'), false];
        }
        return [
            ['association', 'L’association', $u('/association/'), [
                ['Qui sommes-nous', $u('/association/'), false],
                ['L’équipe', $u('/association/equipe/'), false],
                ['Statuts et documents', $u('/association/statuts-et-documents/'), false],
                ['Partenaires', $u('/partenaires/'), false],
                ['Presse', $u('/presse/'), false],
            ]],
            ['actions', 'Nos actions', $u('/nos-actions/'), $actions],
            ['actualites', 'Actualités', $u('/actualites/'), []],
            ['agenda', 'Agenda', $u('/agenda/'), []],
            ['soutenir', 'Nous soutenir', $u('/nous-soutenir/'), [
                ['Adhérer', $u('/nous-soutenir/adherer/'), false],
                ['Devenir bénévole', $u('/nous-soutenir/benevolat/'), false],
                ['Faire un don', Host::museum('/faire-un-don/'), true],
                ['Confier vos archives', Host::museum('/contribuer/'), true],
                ['Devenir partenaire', $u('/partenaires/') . '#devenir-partenaire', false],
            ]],
            ['contact', 'Contact', $u('/contact/'), []],
        ];
    }

    /** Réseaux sociaux de l'association (ceux du musée, plus LinkedIn). @return array<string,array{0:string,1:string}> */
    public static function social(): array
    {
        return array_filter([
            'facebook' => ['Facebook', (string) Settings::get('social.facebook', '')],
            'instagram' => ['Instagram', (string) Settings::get('social.instagram', '')],
            'youtube' => ['YouTube', (string) Settings::get('social.youtube', '')],
            'x' => ['X', (string) Settings::get('social.x', '')],
            'linkedin' => ['LinkedIn', (string) Settings::get('vitrine.linkedin', '')],
        ], fn ($s) => preg_match('#^https?://#i', $s[1]) === 1);
    }

    /** Métadonnées de la page (titre, description, adresse canonique, image de partage). */
    public static function meta(array $p, string $path): array
    {
        $name = self::name();
        $title = trim((string) ($p['title'] ?? ''));
        $full = !empty($p['full_title']) ? (string) $p['full_title'] : ($title === '' ? $name : "$title · $name");
        $desc = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($p['description'] ?? ''))));
        if ($desc === '') {
            $desc = (string) Settings::get('vitrine.seo_description', '');
        }
        $image = (string) ($p['image'] ?? '');
        return [
            'title' => $full,
            'description' => mb_substr($desc, 0, 300),
            'canonical' => Host::abs($p['canonical'] ?? $path),
            'og_image' => preg_match('#^https?://#', $image) ? $image : Host::abs($image !== '' ? $image : '/assets/img/vitrine/partage.jpg'),
            'type' => $p['type'] ?? 'website',
            'jsonld' => $p['jsonld'] ?? null,
            'noindex' => !empty($p['noindex']) || self::hidden(),
            'site' => $name,
        ];
    }

    /** « Samedi 23 janvier 2027 · 14 h 30 » (événement), « 20 mai 2028 » (journée entière). */
    public static function when(array $e): string
    {
        $ts = strtotime((string) $e['start']);
        if (!$ts) {
            return '';
        }
        $s = date_fr(date('Y-m-d', $ts), true);
        if (empty($e['allday']) && date('H:i', $ts) !== '00:00') {
            $s .= ' · ' . date('G', $ts) . ' h ' . date('i', $ts);
            if (!empty($e['end']) && ($te = strtotime((string) $e['end'])) && date('Y-m-d', $te) === date('Y-m-d', $ts)) {
                $s .= ' – ' . date('G', $te) . ' h ' . date('i', $te);
            }
        }
        return $s;
    }

    /** Mois abrégé (pastille de date) : « janv. », « févr. »… */
    public static function monthShort(int $ts): string
    {
        return ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][(int) date('n', $ts) - 1];
    }

    /** Données structurées de l'association (accueil, pages « L'association », contact). */
    public static function organizationLd(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'NGO',
            'name' => self::name(),
            'url' => Host::abs('/'),
            'logo' => Host::abs('/assets/img/logo-sochaux-retro.png'),
            'description' => (string) Settings::get('vitrine.seo_description', ''),
            'email' => self::email() ?: null,
            'sameAs' => array_values(array_map(fn ($s) => $s[1], self::social())) ?: null,
        ]);
    }
}
