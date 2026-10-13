<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Data\Derived;
use App\Data\Index;
use App\Data\Names;
use App\Front\Fiche;
use App\Front\Site;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Histoire du duel (avant-match) : le bilan complet contre un adversaire, avec les mêmes données
 * que la page /face-a-face/<club>/ (Derived « clubs » et « matches », matchs publiés seulement) :
 * victoires, nuls, défaites, buts, plus large victoire, meilleur buteur du duel (buts marqués
 * contre cet adversaire, d'après les fiches), dernier match et première rencontre.
 * Adversaire choisi (?adversaire=<slug du club>, celui de l'adresse du face-à-face, ou son nom) ; par
 * défaut (« auto ») l'adversaire du dernier match fiché. Club jamais affronté, tapé en toutes lettres
 * (?adversaire=Paris%20FC) : version « Première rencontre » (jamais le bilan d'un autre club).
 */
final class HistoireDuDuel extends Widget
{
    public function key(): string
    {
        return 'histoire-du-duel';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Head-to-head history' : 'Histoire du duel';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'The full record against an opponent: wins, draws, defeats, goals, biggest win, top scorer of the fixture and last meeting.'
            : 'Le bilan complet contre un adversaire : victoires, nuls, défaites, buts, plus large victoire, meilleur buteur du duel et dernier match.';
    }

    public function options(): array
    {
        return ['adversaire' => ['label' => 'Adversaire', 'label_en' => 'Opponent', 'type' => 'search', 'default' => 'auto', 'free' => true,
            'choices' => fn () => ['auto' => 'Automatique : adversaire du dernier match fiché'] + self::choices(),
            'help' => 'Tapez le nom du club : la liste propose les adversaires déjà affrontés (nombre de matchs entre parenthèses). Un club jamais affronté se tape en toutes lettres (« Paris FC ») : version « Première rencontre ».',
            'help_en' => 'Type the club’s name: the list offers the opponents already met (number of matches). A club never met can be typed in full (“Paris FC”): “First meeting” version.']];
    }

    public function height(): int
    {
        return 560;
    }

    /**
     * Nom de club saisi librement (adversaire jamais affronté) : espaces resserrés, seulement lettres,
     * chiffres, espace, apostrophe, point, tiret et « & », 2 à 40 caractères, au moins une lettre ;
     * sinon null. Le résultat est encore échappé à l'affichage.
     */
    public static function cleanName(string $v): ?string
    {
        $v = trim((string) preg_replace('/\s+/u', ' ', $v));
        if ($v === '' || !preg_match('/^[\p{L}\p{N} \'’.&-]{2,40}$/u', $v) || !preg_match('/\p{L}/u', $v)) {
            return null;
        }
        return $v;
    }

    /**
     * Adversaire demandé : ['club' => clé du face-à-face ou null, 'name' => nom affiché].
     * Clé connue, ancienne clé, nom ou variante du référentiel : ce club ; « auto », vide ou saisie
     * invalide : l'adversaire du dernier match fiché ; nom jamais affronté : club null (version
     * « Première rencontre »), jamais l'adversaire du dernier match. Null : notre propre club.
     * @return array{club:?string,name:string}|null
     */
    public static function target(string $v): ?array
    {
        $name = self::cleanName($v === 'auto' ? '' : $v);
        if ($name === null) {
            $k = self::resolve('');
            return $k === null ? null : ['club' => $k, 'name' => Fiche::clubName($k)];
        }
        $self = Names::clubKey(\App\Core\Club::team());
        $k = self::known($name);
        if ($k !== null) {
            return $k === $self ? null : ['club' => $k, 'name' => Fiche::clubName($k)];
        }
        if (\App\Core\Club::isUs($name) || Names::clubKey($name) === $self) {
            return null;
        }
        // Saisie en adresse (« paris-fc ») : nom lisible ; sinon le nom tel que tapé.
        $label = preg_match('/^[a-z0-9-]+$/', $name) ? ucwords(str_replace('-', ' ', $name)) : $name;
        return ['club' => null, 'name' => $label];
    }

    /** Clé d'un adversaire déjà affronté d'après sa clé, son nom ou une variante (référentiel), sinon null. */
    public static function known(string $v): ?string
    {
        $clubs = Derived::part('clubs');
        if (isset($clubs[$v])) {
            return $v;
        }
        $key = Names::clubKey(str_replace('-', ' ', $v));
        if ($key === '') {
            return null;
        }
        if (isset($clubs[$key])) {
            return $key;
        }
        foreach (\App\Data\Collections::get('clubs', []) as $c) {
            if (!isset($clubs[$c['id'] ?? ''])) {
                continue;
            }
            foreach (array_merge([(string) $c['name']], (array) ($c['aliases'] ?? [])) as $n) {
                if (Names::clubKey((string) $n) === $key) {
                    return (string) $c['id'];
                }
            }
        }
        // Dernier essai : sans les mots génériques en tête de nom (« Racing Club de Lens » ≡ « Lens »).
        $short = implode('-', array_filter(explode('-', $key), fn ($w) => !in_array($w, ['racing', 'stade', 'olympique', 'sporting', 'union', 'entente'], true)));
        return $short !== '' && $short !== $key && isset($clubs[$short]) ? $short : null;
    }

    /**
     * Clé du club adverse (adresse du face-à-face), ancienne clé, nom ou variante. Vide : l'adversaire
     * du dernier match fiché. Inconnue : null — jamais l'adversaire du dernier match (voir target()).
     */
    public static function resolve(string $slug): ?string
    {
        $clubs = Derived::part('clubs');
        $self = Names::clubKey(\App\Core\Club::team());
        if ($slug === '') {
            $last = Site::lastMatchCreated();
            $slug = (string) ($last['club'] ?? '');
            if ($slug === '' || !isset($clubs[$slug])) {
                uasort($clubs, fn ($x, $y) => $y['count'] <=> $x['count']);
                $slug = (string) (array_key_first(array_diff_key($clubs, [$self => 1])) ?? '');
            }
            return $slug !== '' && $slug !== $self && isset($clubs[$slug]) ? $slug : null;
        }
        $k = self::known($slug);
        return $k !== null && $k !== $self ? $k : null;
    }

    /** Adversaires possibles (back-office) : clé => nom, par ordre alphabétique. @return array<string,string> */
    public static function choices(): array
    {
        // Tous les adversaires affrontés, par ordre alphabétique (sans tenir compte des accents),
        // avec le nombre de matchs entre parenthèses.
        $self = Names::clubKey(\App\Core\Club::team());
        $out = [];
        foreach (Derived::part('clubs') as $k => $c) {
            if ($k !== $self && (int) $c['count'] > 0) {
                $out[(string) $k] = Fiche::clubName((string) $k) . ' (' . (int) $c['count'] . ')';
            }
        }
        $key = fn (string $l) => strtolower((string) (function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $l) : $l));
        uasort($out, fn ($x, $y) => strnatcmp($key($x), $key($y)));
        return $out;
    }

    /** Matchs publiés contre un club (les plus récents d'abord). @return list<array> */
    private static function meetings(string $club): array
    {
        $M = Derived::part('matches');
        $list = [];
        foreach ((array) (Derived::part('clubs')[$club]['matches'] ?? []) as $id) {
            $x = $M[$id] ?? null;
            if ($x && $x['v']) {
                $list[] = $x + ['id' => (int) $id];
            }
        }
        usort($list, fn ($x, $y) => strcmp((string) $y['date'], (string) $x['date']));
        return $list;
    }

    /** Résumé d'un match pour l'affichage. */
    private static function summary(array $x): array
    {
        return ['label' => Site::matchLabel($x), 'home' => (string) $x['home'], 'away' => (string) $x['away'],
            'score' => $x['us'] !== null ? ($x['sh'] ? $x['us'] . '-' . $x['them'] : $x['them'] . '-' . $x['us']) : '', 'date' => (string) $x['date'],
            'competition' => (string) (($x['label'] ?? '') ?: ($x['comp'] ?? '')), 'result' => (string) ($x['result'] ?? ''), 'href' => self::local((string) $x['path'])];
    }

    /**
     * Version « Première rencontre » (aucun match publié contre ce club) : rien d'inventé, seulement
     * ce que disent les fiches : le rang de ce nouvel adversaire parmi les clubs différents affrontés,
     * le bilan des premières rencontres de l'histoire du club, la dernière « première rencontre »
     * fichée et les clubs déjà affrontés dont le nom partage un mot distinctif (« Lausanne »…).
     */
    private static function firstMeeting(string $name): array
    {
        $self = Names::clubKey(\App\Core\Club::team());
        $firsts = ['V' => 0, 'N' => 0, 'D' => 0];
        $n = 0;
        $latest = null;
        $generic = ['stade', 'racing', 'olympique', 'union', 'sporting', 'sport', 'sports', 'club', 'football', 'real', 'athletic', 'atletico', 'saint', 'sainte',
            'etoile', 'entente', 'star', 'city', 'united', 'association', 'olympic', 'dynamo', 'dinamo', 'sparta', 'young', 'boys', 'inter', 'ville', 'nord', 'ouest', 'sud'];
        $words = array_values(array_filter(explode('-', Names::clubKey($name)), fn ($w) => strlen($w) >= 4 && !in_array($w, $generic, true)));
        $same = [];
        foreach (Derived::part('clubs') as $k => $cl) {
            if ($k === $self) {
                continue;
            }
            $list = self::meetings((string) $k);
            if (!$list) {
                continue;
            }
            $n++;
            $dated = array_values(array_filter($list, fn ($x) => (string) $x['date'] !== ''));
            if ($dated) {
                $first = $dated[count($dated) - 1];
                if (isset($firsts[$first['result'] ?? ''])) {
                    $firsts[$first['result']]++;
                }
                if (!$latest || strcmp((string) $first['date'], (string) $latest['date']) > 0) {
                    $latest = $first;
                }
            }
            if ($words && array_intersect($words, explode('-', (string) $k))) {
                $t = ['V' => 0, 'N' => 0, 'D' => 0];
                foreach ($list as $x) {
                    if (isset($t[$x['result'] ?? ''])) {
                        $t[$x['result']]++;
                    }
                }
                $same[] = ['name' => Fiche::clubName((string) $k), 'matches' => count($list), 'wins' => $t['V'], 'draws' => $t['N'], 'defeats' => $t['D'], 'href' => '/face-a-face/' . $k . '/'];
            }
        }
        usort($same, fn ($a, $b) => $b['matches'] <=> $a['matches']);
        return [
            'first_meeting' => true,
            'club' => null,
            'opponent' => $name,
            'team' => \App\Core\Club::team(),
            'rank' => $n + 1,
            'opponents' => $n,
            'firsts' => ['wins' => $firsts['V'], 'draws' => $firsts['N'], 'defeats' => $firsts['D'], 'total' => array_sum($firsts)],
            'latest_first' => $latest ? self::summary($latest) : null,
            'namesakes' => array_slice($same, 0, 2),
            'href' => '/face-a-face/',
        ];
    }

    public function data(array $opt, Context $c): ?array
    {
        $target = self::target((string) $opt['adversaire']);
        if ($target === null) {
            return null;
        }
        if ($target['club'] === null) {
            return self::firstMeeting($target['name']);
        }
        $club = $target['club'];
        $list = self::meetings($club);
        if (!$list) {
            return self::firstMeeting($target['name']);
        }
        $t = ['V' => 0, 'N' => 0, 'D' => 0, 'gf' => 0, 'ga' => 0];
        $best = null;
        $first = null;
        foreach ($list as $x) {
            if (isset($t[$x['result'] ?? ''])) {
                $t[$x['result']]++;
            }
            if ($x['us'] !== null) {
                $t['gf'] += (int) $x['us'];
                $t['ga'] += (int) $x['them'];
                $d = (int) $x['us'] - (int) $x['them'];
                if ($x['result'] === 'V' && (!$best || $d > $best['_d'] || ($d === $best['_d'] && $x['us'] > $best['us']))) {
                    $best = $x + ['_d' => $d];
                }
            }
            if ($x['date'] && (!$first || strcmp((string) $x['date'], (string) $first['date']) < 0)) {
                $first = $x;
            }
        }
        // Meilleur buteur du duel : buts marqués dans ces matchs (fiches liées seulement).
        $goals = [];
        $S = Derived::part('scorers');
        foreach ($list as $x) {
            foreach ((array) ($S[$x['id']] ?? []) as $s) {
                $pid = (int) ($s[0] ?? 0);
                if ($pid > 0) {
                    $goals[$pid] ??= ['n' => 0, 'name' => (string) $s[1]];
                    $goals[$pid]['n'] += max(1, count((array) ($s[2] ?? [])));
                }
            }
        }
        uasort($goals, fn ($a, $b) => $b['n'] <=> $a['n']);
        $scorer = null;
        foreach ($goals as $pid => $g) {
            $p = Index::get($pid);
            if ($p && Index::visible($p)) {
                $scorer = ['name' => $g['name'], 'goals' => $g['n'], 'href' => self::local((string) $p['path'])];
                break;
            }
        }
        $match = fn (array $x) => self::summary($x);
        return [
            'first_meeting' => false,
            'club' => $club,
            'opponent' => Fiche::clubName($club),
            'team' => \App\Core\Club::team(),
            'matches' => count($list),
            'wins' => $t['V'],
            'draws' => $t['N'],
            'defeats' => $t['D'],
            'goals_for' => $t['gf'],
            'goals_against' => $t['ga'],
            'biggest_win' => $best ? $match($best) : null,
            'top_scorer' => $scorer,
            'last' => $match($list[0]),
            'first_year' => $first ? (int) substr((string) $first['date'], 0, 4) : null,
            'href' => '/face-a-face/' . $club . '/',
        ];
    }

    public function body(array $d, Context $c): string
    {
        if (!empty($d['first_meeting'])) {
            return self::firstBody($d, $c);
        }
        $date = fn (string $iso) => $c->en() ? date('j F Y', strtotime($iso)) : date_fr($iso);
        $h = '<p class="w-eyebrow">' . e($c->tr('L’histoire du duel', 'Head-to-head')) . ($d['first_year'] ? ' · ' . e($c->tr('depuis ', 'since ')) . $d['first_year'] : '') . '</p>';
        $h .= '<p class="w-title"><a href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($d['team']) . ' × ' . e($d['opponent']) . '</a></p>';
        $h .= '<div class="w-stats w-stats--4"><span class="is-key"><b>' . $d['matches'] . '</b>' . e($c->tr('matchs', 'matches')) . '</span><span><b>' . $d['wins'] . '</b>' . e($c->tr('victoires', 'wins')) . '</span><span><b>' . $d['draws'] . '</b>' . e($c->tr('nuls', 'draws')) . '</span><span><b>' . $d['defeats'] . '</b>' . e($c->tr('défaites', 'defeats')) . '</span></div>';
        $total = max(1, $d['wins'] + $d['draws'] + $d['defeats']);
        $h .= '<div class="w-bar" role="img" aria-label="' . e($d['wins'] . ' V, ' . $d['draws'] . ' N, ' . $d['defeats'] . ' D') . '"><i class="w-bar__v" style="width:' . round(100 * $d['wins'] / $total, 1) . '%"></i><i class="w-bar__n" style="width:' . round(100 * $d['draws'] / $total, 1) . '%"></i><i class="w-bar__d" style="width:' . round(100 * $d['defeats'] / $total, 1) . '%"></i></div>';
        $h .= '<p class="w-text w-center small">' . e($c->tr('Buts : ' . $d['goals_for'] . ' marqués, ' . $d['goals_against'] . ' encaissés', 'Goals: ' . $d['goals_for'] . ' scored, ' . $d['goals_against'] . ' conceded')) . '</p><ul class="w-list w-list--tight">';
        if ($d['biggest_win']) {
            $b = $d['biggest_win'];
            $h .= '<li><span class="w-kicker w-inline">' . e($c->tr('Plus large victoire', 'Biggest win')) . '</span> <a href="' . e($b['href']) . '" target="_blank" rel="noopener">' . e($b['label']) . '</a> <small class="w-score">' . e($date($b['date'])) . '</small></li>';
        }
        if ($d['top_scorer']) {
            $s = $d['top_scorer'];
            $h .= '<li><span class="w-kicker w-inline">' . e($c->tr('Meilleur buteur du duel', 'Top scorer of the fixture')) . '</span> <a href="' . e($s['href']) . '" target="_blank" rel="noopener">' . e($s['name']) . '</a> <b class="w-right">' . $s['goals'] . '</b></li>';
        }
        $l = $d['last'];
        $h .= '<li><span class="w-kicker w-inline">' . e($c->tr('Dernier match', 'Last meeting')) . '</span> <a href="' . e($l['href']) . '" target="_blank" rel="noopener">' . e($l['label']) . '</a> <small class="w-score">' . e($date($l['date'])) . '</small></li></ul>';
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Tout le face-à-face', 'The full head-to-head')) . ' →</a></p>';
    }

    /** Première rencontre : texte, puis les faits du musée (échappés). */
    private static function firstBody(array $d, Context $c): string
    {
        $date = fn (string $iso) => $c->en() ? date('j F Y', strtotime($iso)) : date_fr($iso);
        $h = '<p class="w-eyebrow">' . e($c->tr('Première rencontre', 'First meeting')) . '</p>';
        $h .= '<p class="w-title">' . e($d['team']) . ' × ' . e($d['opponent']) . '</p>';
        $h .= '<p class="w-text w-center">' . e($c->tr('Premier match officiel entre les deux clubs : aucune rencontre dans les archives du musée.', 'First official match between the two clubs: no meeting in the museum’s archives.')) . '</p>';
        $f = $d['firsts'];
        $h .= '<div class="w-stats w-stats--4"><span class="is-key"><b>' . (int) $d['opponents'] . '</b>' . e($c->tr('adversaires déjà affrontés', 'opponents already met')) . '</span><span><b>' . $f['wins'] . '</b>' . e($c->tr('victoires', 'wins')) . '</span><span><b>' . $f['draws'] . '</b>' . e($c->tr('nuls', 'draws')) . '</span><span><b>' . $f['defeats'] . '</b>' . e($c->tr('défaites', 'defeats')) . '</span></div>';
        $h .= '<p class="w-text w-center small">' . e($c->tr('Bilan de ' . $d['team'] . ' lors de ses ' . $f['total'] . ' premières rencontres fichées', $d['team'] . '’s record in their ' . $f['total'] . ' first meetings on record')) . '</p><ul class="w-list w-list--tight">';
        if ($d['latest_first']) {
            $l = $d['latest_first'];
            $h .= '<li><span class="w-kicker w-inline">' . e($c->tr('Dernière première rencontre', 'Latest first meeting')) . '</span> <a href="' . e($l['href']) . '" target="_blank" rel="noopener">' . e($l['label']) . '</a> <small class="w-score">' . e($date($l['date'])) . '</small></li>';
        }
        foreach ($d['namesakes'] as $n) {
            $h .= '<li><span class="w-kicker w-inline">' . e($c->tr('Déjà affronté', 'Already met')) . '</span> <a href="' . e($n['href']) . '" target="_blank" rel="noopener">' . e($n['name']) . '</a> <small class="w-score">' . e($n['matches'] . ' ' . $c->tr('matchs', 'matches') . ' : ' . $n['wins'] . ' V, ' . $n['draws'] . ' N, ' . $n['defeats'] . ' D') . '</small></li>';
        }
        return $h . '</ul><p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Tous les face-à-face', 'All head-to-heads')) . ' →</a></p>';
    }
}
