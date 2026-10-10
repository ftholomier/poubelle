<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Memo;
use App\Core\Settings;
use App\Data\Categories;
use App\Data\Collections;
use App\Data\Derived;
use App\Data\Index;
use App\Services\I18n;

/**
 * Éléments communs à toutes les pages : menus et méga-menus, bandeau défilant,
 * métadonnées SEO.
 */
final class Site
{
    public const C_MATCHS = 'matchs-fc-sochaux-retro-fcsm';
    public const C_LIONS = 'nos-lions-fc-sochaux-retro-fcsm';
    public const C_JOUEURS = 'joueurs-fc-sochaux-retro-fcsm';
    public const C_ENTRAINEURS = 'entraineurs-fc-sochaux-retro-fcsm';
    public const C_DIRIGEANTS = 'dirigeants-fc-sochaux-retro-fcsm';
    public const C_PERSONNAGES = 'personnages-emblematiques-fc-sochaux-retro-fcsm';

    private static ?array $counts = null;

    /** Nombre de fiches visibles par rubrique (sous-rubriques comprises). */
    public static function count(string $slug): int
    {
        // Compteurs des menus gardés en cache tant que les fiches et les rubriques ne changent pas.
        self::$counts ??= Memo::get('compteurs-rubriques', [Index::CACHE, Categories::FILE, __FILE__], '', function () {
            $counts = [];
            $direct = [];
            foreach (Index::published() as $s) {
                foreach ($s['categories'] as $c) {
                    $direct[$c][$s['id']] = true;
                }
            }
            foreach (Categories::all() as $cs => $c) {
                $ids = [];
                foreach (Categories::descendants($cs, true) as $d) {
                    $ids += $direct[$d] ?? [];
                }
                $counts[$cs] = count($ids);
            }
            return $counts;
        });
        $n = self::$counts[$slug] ?? 0;
        // « L'équipe de Sochaux Rétro » montre les membres de l'équipe (écran Équipe), pas des
        // fiches : ils comptent pour elle et pour sa rubrique parente.
        if ($slug === 'lequipe-de-sochaux-retro' || in_array('lequipe-de-sochaux-retro', Categories::descendants($slug, false), true)) {
            static $team = null;
            $team ??= count(\App\Vitrine\Content::team());
            $n += $team;
        }
        return $n;
    }

    public static function catUrl(string $slug): string
    {
        return url(Categories::get($slug)['path'] ?? '/');
    }

    /** Entrées du menu principal + contenu des méga-menus. */
    public static function nav(string $active = ''): array
    {
        $decades = [];
        foreach (Categories::children(self::C_MATCHS) as $c) {
            if (preg_match('/^annees-(\d+)/', $c['slug'], $m)) {
                $full = strlen($m[1]) === 2 ? (int) ('19' . $m[1]) : (int) $m[1];
                $decades[$full] = ['short' => strlen($m[1]) === 2 ? "'" . $m[1] : $m[1], 'full' => (string) $full, 'href' => url($c['path']), 'count' => self::count($c['slug'])];
            }
        }
        ksort($decades);
        $comps = [['label' => t('Championnat'), 'href' => url('/matchs/') . '?f=championnat']];
        foreach (Categories::children(self::C_MATCHS) as $c) {
            if (!preg_match('/^annees-/', $c['slug'])) {
                $comps[] = ['label' => t(Categories::label($c['slug'])), 'href' => url($c['path']), 'count' => self::count($c['slug'])];
            }
        }

        $lionsCols = [];
        foreach ([self::C_JOUEURS => 'Tous les joueurs', self::C_ENTRAINEURS => 'Tous les entraîneurs', self::C_DIRIGEANTS => 'Tous les dirigeants', self::C_PERSONNAGES => 'Toutes les personnes'] as $slug => $all) {
            $c = Categories::get($slug);
            if (!$c) {
                continue;
            }
            $subs = array_map(fn ($ch) => ['label' => t(Categories::label($ch['slug'])), 'href' => url($ch['path']), 'count' => self::count($ch['slug'])], Categories::children($slug));
            $lionsCols[] = ['title' => t(Categories::label($slug)), 'all' => ['label' => t($all), 'href' => url($c['path']), 'count' => self::count($slug)], 'subs' => $subs];
        }

        $simple = function (string $slug) {
            $c = Categories::get($slug);
            if (!$c) {
                return null;
            }
            return ['title' => t(Categories::label($slug)), 'all' => ['label' => t('Tout voir'), 'href' => url($c['path']), 'count' => self::count($slug)],
                'subs' => array_map(fn ($ch) => ['label' => t(Categories::label($ch['slug'])), 'href' => url($ch['path']), 'count' => self::count($ch['slug'])], Categories::children($slug)),
                'desc' => $c['description'] ?? ''];
        };

        // Entrées du menu principal : ordre, libellés et visibilité réglés dans Éditorial › Menus.
        $menus = Menus::get();
        $megas = [
            'matchs' => ['kind' => 'matchs', 'competitions' => $comps, 'decades' => array_values($decades), 'explore' => Menus::links($menus['matchs_explore'])],
            'nos-lions' => ['kind' => 'columns', 'cols' => $lionsCols, 'all' => ['label' => t('Tous les Lions'), 'href' => url('/nos-lions/')]],
            'interactif' => ['kind' => 'interactif', 'groups' => self::interactiveTools()],
        ];
        $items = [];
        foreach ($menus['nav'] as $i => $n) {
            if (!empty($n['hidden']) || ($h = Menus::href((string) ($n['href'] ?? ''))) === null) {
                continue;
            }
            $key = (string) ($n['key'] ?? '') ?: 'lien-' . $i;
            $mega = $megas[$key] ?? null;
            if (!$mega && in_array($key, ['supporters', 'infrastructures', 'symboles'], true)) {
                $col = $simple($key);
                $mega = $col ? ['kind' => 'list', 'col' => $col] : null;
            }
            $items[] = ['key' => $key, 'label' => Menus::text($n), 'href' => $h[0], 'mega' => $mega];
        }
        foreach ($items as &$it) {
            $it['active'] = $it['key'] === $active;
        }
        return $items;
    }

    /** Outils de la rubrique INTERACTIF (3 groupes validés). */
    public static function interactiveTools(): array
    {
        $out = [];
        foreach (Menus::get()['interactif'] as $g) {
            if (($g['auto'] ?? '') === 'walls') {
                $tools = Walls::tools();
            } else {
                $tools = [];
                foreach ((array) ($g['tools'] ?? []) as $x) {
                    $href = (string) ($x['href'] ?? '');
                    if (!empty($x['hidden']) || ($href === '/grands-recits/' && !Categories::get('grands-recits')) || ($h = Menus::href($href)) === null) {
                        continue;
                    }
                    $tools[] = ['icon' => (string) ($x['icon'] ?? '•'), 'label' => Menus::text($x), 'd' => Menus::text($x, 'd'), 'href' => $h[0]];
                }
            }
            if ($tools) {
                $out[] = ['title' => Menus::text($g, 'title'), 'tools' => $tools];
            }
        }
        return $out;
    }

    /** Bandeau « En direct du musée » : messages automatiques + messages manuels. */
    public static function ticker(): array
    {
        $conf = Collections::get('ticker', ['auto' => ['jour' => true, 'centenaire' => true, 'dernier' => true, 'retro' => true], 'messages' => self::defaultTickerMessages()]);
        $out = [];
        $auto = $conf['auto'] ?? [];
        // Rétro-Direct en cours, ou prochain dans les 7 jours.
        if (($auto['retro'] ?? true) && ($r = \App\Services\RetroDirect::next()) && $r['start'] < time() + 7 * 86400) {
            $live = $r['state'] === 'direct';
            $year = substr((string) ($r['s']['m']['date'] ?? ''), 0, 4);
            $out[] = ['k' => $live ? t('En direct') . ' · ' . t('Rétro-Direct') : t('Rétro-Direct') . ' · ' . Retro::when((int) $r['start']),
                'v' => ($r['s']['m']['home'] ?? '') . ' – ' . ($r['s']['m']['away'] ?? '') . " ($year)", 'href' => \App\Services\RetroDirect::url($r['s'])];
        }
        if (($auto['jour'] ?? true) && ($m = Derived::onThisDay()[0] ?? null)) {
            $out[] = ['k' => t('Ce jour-là') . ' · ' . self::dayMonth(), 'v' => self::matchLabel($m), 'href' => url($m['path'])];
        }
        if ($auto['centenaire'] ?? true) {
            $out[] = ['k' => t('Centenaire'), 'v' => 'J-' . self::daysToCentenary() . ' ' . t('avant les 100 ans'), 'href' => url('/centenaire/')];
        }
        if (($auto['dernier'] ?? true) && ($last = self::lastMatchCreated())) {
            $out[] = ['k' => t('Dernier match fiché'), 'v' => self::matchLabel($last), 'href' => url($last['path'])];
        }
        foreach ($conf['messages'] ?? [] as $msg) {
            if (!empty($msg['on'])) {
                $msg = Collections::loc($msg, ['k', 'v']);
                $out[] = ['k' => $msg['k'], 'v' => $msg['v'], 'href' => url($msg['href'] ?? '/')];
            }
        }
        return $out;
    }

    public static function defaultTickerMessages(): array
    {
        return [
            ['k' => 'Nouveau', 'v' => 'La carto des stades et des origines', 'href' => '/interactif/carto/', 'on' => true],
            ['k' => 'Vote', 'v' => 'Composez le Onze de légende', 'href' => '/centenaire/#onze', 'on' => true],
            ['k' => 'Contribuer', 'v' => 'Vos archives enrichissent le musée', 'href' => '/contribuer/', 'on' => true],
        ];
    }

    public static function dayMonth(?int $ts = null): string
    {
        $months = I18n::isEn()
            ? ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
            : ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $ts ??= time();
        return I18n::isEn() ? $months[(int) date('n', $ts) - 1] . ' ' . date('j', $ts) : date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1];
    }

    public static function centenaryDate(): string
    {
        return (string) Settings::get('home.centenary_date', '2028-06-14');
    }

    public static function daysToCentenary(): int
    {
        return max(0, (int) ceil((strtotime(self::centenaryDate() . ' 00:00:00') - time()) / 86400));
    }

    /** « Sochaux – Neuchâtel Xamax 2-1 » */
    public static function matchLabel(array $m): string
    {
        if (!empty($m['event']) && empty($m['home'])) {
            return $m['event'];
        }
        $score = isset($m['us']) && $m['us'] !== null
            ? ($m['sh'] ? $m['us'] . '-' . $m['them'] : $m['them'] . '-' . $m['us'])
            : '';
        return trim(($m['home'] ?? '') . ' – ' . ($m['away'] ?? '') . ' ' . $score);
    }

    private static function lastMatchCreated(): ?array
    {
        $id = Memo::get('dernier-match-fiche', [Index::CACHE, __FILE__], '', function () {
            $best = null;
            foreach (Index::published('match') as $s) {
                if (!$best || strcmp((string) $s['date'], (string) $best['date']) > 0) {
                    $best = $s;
                }
            }
            return $best['id'] ?? 0;
        });
        return $id ? Derived::match((int) $id) : null;
    }

    // ------------------------------------------------------------------ SEO

    /**
     * @param array{title?:string,description?:string,image?:?string,type?:string,canonical?:string,jsonld?:array,noindex?:bool} $p
     */
    /** Fiche affichée : sa traduction anglaise existe-t-elle ? (null = page d'interface, bilingue) */
    public static ?bool $enAvailable = null;

    public static function meta(array $p, string $path): array
    {
        $enOk = self::$enAvailable ?? true;
        $untranslated = !$enOk && I18n::isEn();
        $site = (string) Settings::get('general.site_name', 'Sochaux Rétro');
        $base = base_url();
        $title = trim($p['title'] ?? '');
        $full = !empty($p['full_title']) ? (string) $p['full_title'] : ($title === '' ? "$site — " . t('Le musée en ligne du FCSM') : "$title | $site");
        $canonical = $p['canonical'] ?? I18n::switchUrl($path, $untranslated ? I18n::DEFAULT : I18n::lang());
        return [
            'title' => $full,
            'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($p['description'] ?? '')))) ?: t("L'histoire du FC Sochaux-Montbéliard depuis 1928 : matchs, joueurs, entraîneurs, dirigeants, supporters, stades et symboles."), 0, 300),
            'canonical' => $base . $canonical,
            'og_image' => $base . ($p['image'] ?? '/assets/img/partage-defaut.png'),
            'type' => $p['type'] ?? 'website',
            'alternates' => array_merge(
                array_map(fn ($l) => ['lang' => $l, 'href' => $base . I18n::switchUrl($path, $l)], $enOk ? I18n::enabled() : [I18n::DEFAULT]),
                [['lang' => 'x-default', 'href' => $base . I18n::switchUrl($path, I18n::DEFAULT)]]
            ),
            'jsonld' => $p['jsonld'] ?? null,
            'noindex' => ($p['noindex'] ?? false) || $untranslated,
            'untranslated' => $untranslated,
            'site' => $site,
        ];
    }
}
