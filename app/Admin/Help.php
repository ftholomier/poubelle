<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Aide du back-office : guide d'utilisation par chapitres (pas à pas, captures d'écran
 * annotées, « Comment faire pour… »), recherche, aide contextuelle depuis chaque écran,
 * version imprimable et PDF.
 *
 * Les chapitres sont des fichiers de app/Resources/aide/chapitres/ ; leur texte HTML peut
 * contenir quelques repères :
 *   [[img:fichier.webp|Légende]]       capture d'écran (app/Resources/aide/img/)
 *   [[astuce|…]] [[attention|…]]       encadrés
 *   [[wp|…]]                           différence avec l'ancien WordPress
 *   [[auto|…]]                         ce que le site fait tout seul
 *   [[ecran:/admin/…|Libellé]]         bouton vers l'écran concerné
 *   [[aide:chapitre#section|Libellé]]  lien vers une autre partie de l'aide
 */
final class Help extends Base
{
    public const DIR = APP_DIR . '/Resources/aide';

    /** Écran du back-office (clé du menu) → partie de l'aide qui l'explique. */
    private const CONTEXT = [
        'dash' => 'prise-en-main#tableau-de-bord',
        'qualite' => 'qualite',
        'journal' => 'administration#journal',
        'matchs' => 'matchs',
        'personnes' => 'personnes',
        'articles' => 'articles',
        'objets' => 'articles#objets',
        'moments' => 'articles#moments',
        'referentiels' => 'qualite#referentiels',
        'medias' => 'medias',
        'accueil' => 'editorial#accueil',
        'rubriques' => 'editorial#rubriques',
        'redirections' => 'editorial#redirections',
        'attente' => 'editorial#attente',
        'interactif' => 'interactif',
        'onze' => 'interactif#onze-album',
        'retro' => 'interactif#retro-direct',
        'souvenirs' => 'interactif#souvenirs',
        'contributions' => 'communaute#contributions',
        'messages' => 'communaute#messages',
        'newsletter' => 'communaute#newsletter',
        'dons' => 'communaute#dons',
        'traductions' => 'anglais',
        'assistant' => 'administration#assistant',
        'couts' => 'administration#couts',
        'audio' => 'administration#audio',
        'utilisateurs' => 'administration#utilisateurs',
        'reglages' => 'administration#reglages',
        'sauvegardes' => 'administration#sauvegardes',
        'taches' => 'administration#taches',
        'profil' => 'prise-en-main#profil',
        'corbeille' => 'fiches#corbeille',
    ];

    /** Captures réservées aux administrateurs (montants des coûts de l'IA). */
    private const ADMIN_FILES = ['couts-ia.webp'];

    /**
     * Chapitres dans l'ordre, par adresse. Pour les autres membres de l'équipe, les parties
     * réservées aux administrateurs (« admin ») et les montants des coûts de l'IA sont retirés.
     * @return array<string,array>
     */
    public static function chapters(): array
    {
        static $cache = [];
        $admin = Auth::isAdmin() ? 1 : 0;
        if (!isset($cache[$admin])) {
            $all = [];
            foreach (glob(self::DIR . '/chapitres/*.php') ?: [] as $file) {
                $c = require $file;
                if (!$admin) {
                    $c['sections'] = array_values(array_map(fn ($s) => ['html' => Tips::withoutCosts((string) $s['html'])] + $s, array_filter($c['sections'], fn ($s) => empty($s['admin']))));
                }
                $all[$c['slug']] = $c;
            }
            $cache[$admin] = $all;
        }
        return $cache[$admin];
    }

    /** Adresse de l'aide qui correspond à un écran (bouton « Aide » de la barre du haut). */
    public static function urlFor(string $nav): string
    {
        $target = self::CONTEXT[$nav] ?? '';
        return '/admin/aide' . ($target !== '' ? '/' . $target : '');
    }

    public static function index(Request $req): Response
    {
        $q = trim(mb_substr($req->str('q'), 0, 80));
        return self::html('admin/aide/index', [
            'chapters' => self::chapters(),
            'q' => $q,
            'results' => $q !== '' ? self::search($q) : [],
            'faq' => self::chapters()['faq'] ?? null,
        ], ['title' => 'Aide', 'crumb' => 'Aide et formation', 'nav' => 'aide']);
    }

    public static function chapter(Request $req, string $slug): ?Response
    {
        $all = self::chapters();
        if (!isset($all[$slug])) {
            return null;
        }
        $keys = array_keys($all);
        $i = array_search($slug, $keys, true);
        return self::html('admin/aide/chapitre', [
            'c' => $all[$slug],
            'prev' => $i > 0 ? $all[$keys[$i - 1]] : null,
            'next' => $i < count($keys) - 1 ? $all[$keys[$i + 1]] : null,
            'chapters' => $all,
        ], ['title' => $all[$slug]['title'], 'crumb' => 'Aide et formation', 'nav' => 'aide']);
    }

    /** Toute l'aide sur une page, mise en forme pour l'impression (et le PDF). */
    public static function printable(Request $req): Response
    {
        return Response::html(\App\Core\View::render('admin/aide/imprimer', ['chapters' => self::chapters(), 'memo' => false]));
    }

    /** Mémo de deux pages (l'essentiel à garder sous la main). */
    public static function memo(Request $req): Response
    {
        return Response::html(\App\Core\View::render('admin/aide/memo', []));
    }

    /** Captures d'écran et PDF de l'aide (réservés aux membres connectés). */
    public static function file(Request $req, string $name): ?Response
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]*\.(webp|png|jpg|pdf)$/', $name) || (in_array($name, self::ADMIN_FILES, true) && !Auth::isAdmin())) {
            return null;
        }
        $path = self::DIR . (str_ends_with($name, '.pdf') ? '/' : '/img/') . $name;
        if (!is_file($path)) {
            return null;
        }
        $res = new Response('', 200, [
            'Content-Type' => match (pathinfo($name, PATHINFO_EXTENSION)) {
                'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', default => 'image/webp',
            },
            'Cache-Control' => 'private, max-age=86400',
        ]);
        if (str_ends_with($name, '.pdf')) {
            $res->headers['Content-Disposition'] = 'inline; filename="' . $name . '"';
        }
        $res->file = $path;
        return $res;
    }

    /**
     * Texte d'un chapitre prêt à afficher (repères remplacés). Pour l'impression et le PDF ($print) :
     * images chargées d'emblée, renvois internes vers les ancres du document, liens d'écran absolus.
     */
    public static function render(string $html, bool $print = false): string
    {
        $html = preg_replace_callback('/\[\[img:([a-z0-9-]+\.(?:webp|png|jpg))\|([^\]]*)\]\]/u', function ($m) use ($print) {
            $src = '/admin/aide/fichier/' . $m[1];
            $file = self::DIR . '/img/' . $m[1];
            if (!is_file($file)) {
                return $print ? '' : '<figure class="aide-fig"><div class="aide-fig__missing">Capture à venir</div><figcaption>' . e($m[2]) . '</figcaption></figure>';
            }
            $size = @getimagesize($file);
            // Captures faites à 1,25× : affichées à leur taille réelle à l'écran, sans agrandissement.
            $dims = $size ? ' width="' . (int) round($size[0] / 1.25) . '" height="' . (int) round($size[1] / 1.25) . '"' : '';
            $img = '<img src="' . e($src) . '" alt="' . e($m[2]) . '"' . $dims . ($print ? '' : ' loading="lazy"') . '>';
            return '<figure class="aide-fig">' . ($print ? $img : '<a href="' . e($src) . '" target="_blank" title="Agrandir">' . $img . '</a>')
                . '<figcaption>' . e($m[2]) . '</figcaption></figure>';
        }, $html) ?? $html;
        // Liens d'abord : un renvoi placé dans un encadré ne doit pas le refermer avant l'heure.
        $html = preg_replace_callback('/\[\[ecran:(\/admin[^|\]]*)\|([^\]]+)\]\]/u', fn ($m) => '<a class="btn btn--sm aide-go" href="' . e(($print ? base_url() : '') . $m[1]) . '">' . e($m[2]) . ' →</a>', $html) ?? $html;
        $html = preg_replace_callback('/\[\[aide:([a-z0-9-]+)(?:#([a-z0-9-]+))?\|([^\]]+)\]\]/u', function ($m) use ($print) {
            $href = $print ? '#' . $m[1] . (($m[2] ?? '') !== '' ? '-' . $m[2] : '') : '/admin/aide/' . $m[1] . (($m[2] ?? '') !== '' ? '#' . $m[2] : '');
            return '<a href="' . e($href) . '">' . e($m[3]) . '</a>';
        }, $html) ?? $html;
        $boxes = ['astuce' => ['Astuce', 'tip'], 'attention' => ['Attention', 'warn'], 'wp' => ['Avant, dans WordPress', 'wp'], 'auto' => ['Automatique', 'auto']];
        $html = preg_replace_callback('/\[\[(astuce|attention|wp|auto)\|(.*?)\]\]/us', function ($m) use ($boxes) {
            [$label, $cls] = $boxes[$m[1]];
            return '<aside class="aide-box aide-box--' . $cls . '"><b>' . e($label) . '</b><div>' . $m[2] . '</div></aside>';
        }, $html) ?? $html;
        return $html;
    }

    /** @return list<array{chapter:array, section:array, snippet:string}> */
    private static function search(string $q): array
    {
        $words = array_values(array_filter(preg_split('/\s+/u', self::fold($q)) ?: [], fn ($w) => mb_strlen($w) >= 2));
        if (!$words) {
            return [];
        }
        $out = [];
        foreach (self::chapters() as $c) {
            foreach ($c['sections'] as $s) {
                $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/\[\[(?:img|ecran|aide):[^|\]]*\|([^\]]*)\]\]|\[\[\w+\|/u', ' $1 ', $s['html']) ?? ''), ENT_QUOTES, 'UTF-8')) ?? '');
                $hay = self::fold($c['title'] . ' ' . $s['title'] . ' ' . $text);
                $score = 0;
                foreach ($words as $w) {
                    if (!str_contains($hay, $w)) {
                        continue 2;
                    }
                    $score += substr_count($hay, $w) + (str_contains(self::fold($s['title']), $w) ? 10 : 0);
                }
                $pos = mb_stripos(self::fold($text), $words[0]);
                $start = max(0, (int) $pos - 70);
                $snippet = ($start > 0 ? '…' : '') . mb_substr($text, $start, 200) . '…';
                $out[] = ['chapter' => $c, 'section' => $s, 'snippet' => $snippet, 'score' => $score];
            }
        }
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($out, 0, 30);
    }

    private static function fold(string $s): string
    {
        $t = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        return $t ? (string) $t->transliterate($s) : mb_strtolower($s);
    }
}
