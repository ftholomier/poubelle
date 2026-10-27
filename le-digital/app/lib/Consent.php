<?php
declare(strict_types=1);

namespace LD;

/**
 * Consentement aux cookies et traceurs (règles de la CNIL) : rien de non essentiel ne se charge avant l'accord du visiteur.
 *
 * Les codes collés dans le back-office (mesure d'audience, widgets d'avis, posts LinkedIn) sont rendus inertes dans un
 * <template data-consent="…"> et activés par le navigateur seulement pour les catégories acceptées. Le choix est gardé
 * 6 mois sur l'appareil du visiteur, modifiable à tout moment (« Gestion des cookies » du pied de page), et journalisé
 * sans donnée identifiante (preuve du consentement).
 */
final class Consent
{
    /** Durée de validité du choix (CNIL : 6 mois recommandés). */
    public const DAYS = 182;

    /** Gestion du consentement active (Contenus › Commun › cookies). Désactivée, les codes tiers se chargent directement. */
    public static function enabled(): bool
    {
        return (bool) (Content::get('global')['cookies']['enabled'] ?? true);
    }

    /** Catégories facultatives réellement utilisées par le site (une catégorie sans code n'est pas proposée). */
    public static function categories(): array
    {
        return array_keys(array_filter([
            'audience' => trim((string) Settings::get('seo.analytics_head', '')) !== '',
            'tiers'    => Reviews::carouselCode() !== '' || Reviews::widgetCode() !== '' || Posts::linkedinCode() !== '',
        ]));
    }

    /** Version du choix : change quand une catégorie apparaît ou disparaît, pour redemander l'accord. */
    public static function version(): string
    {
        return substr(md5(implode(',', self::categories()) . '|1'), 0, 8);
    }

    /** Réglages transmis au navigateur. */
    public static function config(): array
    {
        return ['cats' => self::categories(), 'v' => self::version(), 'days' => self::DAYS];
    }

    /**
     * Code tiers rendu inerte jusqu'à l'accord. $placeholder (HTML) est affiché à la place tant que la catégorie est refusée ;
     * il peut contenir un bouton [data-consent-allow] qui donne l'accord pour cette seule catégorie.
     */
    public static function wrap(string $category, string $code, string $placeholder = ''): string
    {
        if (trim($code) === '' || !self::enabled()) {
            return $code;
        }
        $out = '<template data-consent="' . e($category) . '">' . $code . '</template>';
        if ($placeholder !== '') {
            $out .= '<div class="cc-ph" data-consent-ph="' . e($category) . '">' . $placeholder . '</div>';
        }
        return $out;
    }

    /** Encart affiché à la place d'un contenu tiers refusé, avec un bouton pour l'autoriser. */
    public static function placeholder(string $what): string
    {
        $c = (array) (Content::get('global')['cookies'] ?? []);
        $text = strtr((string) ($c['ph_text'] ?? 'Ce contenu ({what}) est fourni par un service tiers qui peut déposer des cookies.'), ['{what}' => $what]);
        return '<p>' . t($text) . '</p><div class="cc-ph__btns"><button type="button" class="cc-btn cc-btn--red" data-consent-allow="tiers">' . t($c['ph_allow'] ?? 'Afficher ce contenu') . '</button>'
            . '<button type="button" class="cc-link" data-cookie-open>' . t($c['ph_more'] ?? 'Gérer mes cookies') . '</button></div>';
    }

    /** Journal des choix (preuve du consentement) : identifiant aléatoire du navigateur, choix, version, page. */
    public static function log(string $id, array $choices, string $version, string $page): void
    {
        Store::appendLog('consent', [
            'id'      => $id,
            'v'       => $version,
            'choices' => $choices,
            'page'    => mb_substr($page, 0, 200),
            'ua'      => substr(hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 10),
        ]);
    }
}
