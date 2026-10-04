<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Data\Categories;
use App\Data\Derived;
use App\Data\Index;
use App\Data\Media;
use App\Data\Names;
use App\Services\I18n;

/** Affichage d'une fiche (match, personne, article, page, objet, moment). */
final class Fiche
{
    public static function show(Request $req, array $doc): Response
    {
        Site::$enAvailable = !empty($doc['i18n']['en']['title']);
        $raw = $doc;
        $doc = self::localize($doc);
        return match ($doc['type']) {
            'match' => self::match($req, $doc, $raw),
            'personne' => self::person($req, $doc, $raw),
            default => self::article($req, $doc),
        };
    }

    /** Fiche dans la langue de la page (export PDF compris). */
    public static function localizeDoc(array $doc): array
    {
        return self::localize($doc);
    }

    /**
     * Fiche prête à afficher : traduction anglaise s'il y a lieu, en-tête d'un match conforme à ses
     * champs (date et tour de l'ancien site écartés s'ils les contredisent : MatchText::header),
     * « xx » de l'ancien site retirés (Unknown).
     */
    private static function localize(array $doc): array
    {
        return Unknown::doc(\App\Services\MatchText::header(self::translated($doc)));
    }

    /** Remplace les champs par leur traduction anglaise quand elle existe. */
    private static function translated(array $doc): array
    {
        if (!I18n::isEn() || empty($doc['i18n']['en']['title'])) {
            return $doc;
        }
        $en = $doc['i18n']['en'];
        foreach (['title', 'intro', 'sections', 'key_figure', 'seo'] as $k) {
            if (!empty($en[$k])) {
                $doc[$k] = is_array($en[$k]) && is_array($doc[$k] ?? null) && $k !== 'sections' ? $en[$k] + $doc[$k] : $en[$k];
            }
        }
        foreach (['highlights', 'reactions', 'breves'] as $k) {
            if (!empty($en['match'][$k]) && isset($doc['match'])) {
                $doc['match'][$k] = $en['match'][$k];
            }
        }
        if (isset($doc['personne'])) {
            foreach (['subtitle', 'nickname_text', 'fiche', 'honours', 'then'] as $k) {
                if (!empty($en['personne'][$k])) {
                    $doc['personne'][$k] = $en['personne'][$k];
                }
            }
        }
        foreach (['article', 'objet'] as $t) {
            if (!empty($en[$t]) && isset($doc[$t])) {
                $doc[$t] = $en[$t] + $doc[$t];
            }
        }
        if (!empty($en['gallery']) && is_array($en['gallery'])) {
            foreach ($doc['gallery'] as $i => &$g) {
                if (!empty($en['gallery'][$i])) {
                    $g['caption'] = $en['gallery'][$i];
                }
            }
            unset($g);
        }
        return $doc;
    }

    // ================================================================== MATCH

    public static function match(Request $req, array $doc, ?array $raw = null): Response
    {
        $v = self::withAudio(self::matchData($doc), $raw ?? $doc);
        // « Revivre en direct » (Rétro-Direct) quand la fiche a assez de temps forts datés.
        $s = Index::get((int) $doc['id']);
        $v['vars']['retro'] = $s && \App\Services\RetroDirect::playable($doc) ? \App\Services\RetroDirect::url($s) : null;
        // « Ils y étaient » : supporters présents au stade (« J'y étais ! ») et témoignages publiés.
        $v['vars']['etais'] = \App\Services\RetroDirect::etais((int) $doc['id']);
        $v['vars']['temoins'] = \App\Services\Souvenirs::testimonies((int) $doc['id']);
        $v['page']['styles'] = array_merge($v['page']['styles'] ?? [], ['css/souvenirs.css']);
        $v['page']['scripts'] = array_merge($v['page']['scripts'] ?? [], ['js/etais.js']);
        return Pages::render('fiche-match', $v['vars'], $v['page']);
    }

    /**
     * Bouton « Écouter » (explication audio de la fiche) : données et script de la page. La fiche telle
     * qu'enregistrée (même empreinte qu'au back-office : texte de l'IA et voix enregistrée reconnus).
     */
    private static function withAudio(array $v, array $doc): array
    {
        $a = \App\Services\FicheAudio::forPage($doc, I18n::isEn() ? 'en' : 'fr');
        $v['vars']['audio'] = $a;
        if ($a) {
            $v['page']['scripts'] = array_merge($v['page']['scripts'] ?? [], ['js/audio.js']);
        }
        return $v;
    }

    /** Données de la fiche (page du site et export PDF). @return array{vars:array,page:array} */
    public static function matchData(array $doc): array
    {
        $m = $doc['match'];
        $d = Derived::match((int) $doc['id']) ?? [];
        $links = [];
        foreach (Derived::lineupLinks((int) $doc['id']) as $a) {
            $links[$a[8] . '|' . $a[0]] = $a[0];
        }
        $rows = self::lineupRows($doc, $m['lineup']['rows'] ?? []);
        $pitch = self::pitch($rows);
        $resume = self::splitResume($doc['sections']);
        $club = $d['club'] ?? null;
        $catSlug = Categories::primaryOf($doc['categories']);
        $decadeCat = null;
        foreach ($doc['categories'] as $c) {
            if (preg_match('/^annees-/', $c)) {
                $decadeCat = $c;
            }
        }
        $crumbs = [['label' => t('Accueil'), 'href' => url('/')], ['label' => t('Matchs'), 'href' => url('/matchs/')]];
        if ($decadeCat) {
            $crumbs[] = ['label' => t(Categories::label($decadeCat)), 'href' => Site::catUrl($decadeCat)];
        }
        if (!empty($m['season'])) {
            $crumbs[] = ['label' => t('Saison') . ' ' . $m['season'], 'href' => url('/matchs/' . $m['season'] . '/')];
        }

        $home = $m['home']['name'] ?? '';
        $away = $m['away']['name'] ?? '';
        $title = $home && $away ? "$home – $away" : ($m['event'] ?: $doc['title']);
        $scoreTxt = isset($m['score']['home']) ? $m['score']['home'] . '-' . $m['score']['away'] . (!empty($m['score']['extra']) ? ' ' . $m['score']['extra'] : '') : '';
        $seoTitle = $doc['seo']['title'] ?: trim($title . ($scoreTxt ? " ($scoreTxt)" : '') . ', ' . ($m['competition_label'] ?: $m['competition']) . ($m['date'] ? ', ' . date_fr($m['date']) : ''));
        $desc = $doc['seo']['description'] ?: trim(sprintf('%s : %s%s, %s. %s', $title, $scoreTxt ? "$scoreTxt, " : '', $m['competition_label'] ?: $m['competition'], $m['date'] ? date_fr($m['date']) : '', excerpt($resume['all_text'] ?: implode(' ', array_column($doc['sections'], 'html')), 150)));

        $base = base_url();
        $jsonld = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => $title . ($scoreTxt ? " $scoreTxt" : ''),
            'sport' => 'Football',
            'startDate' => $m['date'],
            'location' => $m['stadium'] ? ['@type' => 'Place', 'name' => $m['stadium']] : null,
            'homeTeam' => ['@type' => 'SportsTeam', 'name' => $home],
            'awayTeam' => ['@type' => 'SportsTeam', 'name' => $away],
            'image' => $doc['featured_image'] ? $base . img($doc['featured_image'], 1200) : null,
            'url' => $base . url($doc['path']),
            'eventStatus' => 'https://schema.org/EventScheduled',
        ];

        [$vars, $page] = [[
            'doc' => $doc,
            'm' => $m,
            'd' => $d,
            'title' => $title,
            'rows' => $rows,
            'pitch' => $pitch,
            'resume' => $resume,
            'crumbs' => $crumbs,
            'h2h' => $club ? self::headToHead($club, (int) $doc['id'], $m['date'] ?? null) : null,
            'related' => self::relatedForMatch($doc, $d, $rows),
            'adjacent' => self::adjacentMatches($doc),
        ], [
            'title' => $seoTitle,
            'description' => $desc,
            'image' => '/partage/' . $doc['id'] . '.png',
            'type' => 'article',
            'active' => 'matchs',
            'jsonld' => array_filter($jsonld),
            'body_class' => 'page-match',
            'styles' => ['css/fiche.css'],
        ]];
        return ['vars' => $vars, 'page' => $page];
    }

    /** Lignes de composition enrichies : lien vers la fiche du joueur, nom affiché. */
    public static function lineupRows(array $doc, array $rows): array
    {
        $apps = Derived::lineupLinks((int) $doc['id']);
        $out = [];
        foreach ($rows as $i => $r) {
            $pid = $r['person_id'] ?? null;
            if (!$pid) {
                // Rapprochement calculé : même poste et même nom
                foreach ($apps as $a) {
                    if ($a[8] === strtoupper((string) $r['position'])) {
                        $s = Index::get($a[0]);
                        if ($s && Names::personKey($s['p']['name'] ?? '') === Names::personKey((string) $r['name'])) {
                            $pid = $a[0];
                            break;
                        }
                        if ($s && Names::lineupLastName((string) $r['name']) === implode(' ', Names::tokens($s['p']['last'] ?? ''))) {
                            $pid = $a[0];
                            break;
                        }
                    }
                }
            }
            $s = $pid ? Index::get((int) $pid) : null;
            $out[] = $r + [
                'pid' => $s ? (int) $pid : null,
                'href' => $s && Index::visible($s) ? url($s['path']) : null,
                'display' => Names::display((string) $r['name']),
                'short' => mb_strtoupper(Names::lineupLastName((string) $r['name']) ?: (string) $r['name']),
                'n' => $i + 1,
            ];
        }
        return $out;
    }

    /** Placement des titulaires sur le terrain (formation déduite des postes G / D / M / A). */
    public static function pitch(array $rows): ?array
    {
        $starters = array_values(array_filter($rows, fn ($r) => in_array($r['position'], ['G', 'D', 'M', 'A'], true)));
        if (count($starters) < 7 || count($starters) > 12) {
            return null;
        }
        $lines = ['G' => [], 'D' => [], 'M' => [], 'A' => []];
        foreach ($starters as $r) {
            $lines[$r['position']][] = $r;
        }
        $y = ['G' => 90, 'D' => 72, 'M' => 50, 'A' => 23];
        $placed = [];
        $n = 1;
        foreach (['G', 'D', 'M', 'A'] as $pos) {
            $count = count($lines[$pos]);
            foreach ($lines[$pos] as $i => $r) {
                $x = $count === 1 ? 50 : 16 + (68 * $i / ($count - 1));
                // Légère courbe : les joueurs du centre sont un peu plus reculés (comme la maquette).
                $curve = $count >= 3 ? (abs($x - 50) < 20 ? ($pos === 'A' ? -5 : 3) : 0) : 0;
                $seq = $n++;
                $placed[] = $r + ['x' => round($x, 1), 'y' => $y[$pos] + $curve, 'num' => is_numeric($r['number'] ?? null) ? (int) $r['number'] : $seq];
            }
        }
        $formation = count($lines['D']) . '-' . count($lines['M']) . '-' . count($lines['A']);
        return ['players' => $placed, 'formation' => $formation];
    }

    /**
     * Résumé : temps forts minute par minute + paragraphes hors minute (gardés en texte).
     * @return array{intro_html:string, highlights:list<array>, has_timeline:bool, all_text:string, sections:list<array>}
     */
    public static function splitResume(array $sections): array
    {
        $out = ['intro_html' => '', 'highlights' => [], 'has_timeline' => false, 'all_text' => '', 'sections' => []];
        foreach ($sections as $s) {
            $title = mb_strtolower((string) $s['title']);
            $key = match (true) {
                (bool) preg_match('/avant/u', $title) => 'avant',
                (bool) preg_match('/r[ée]sum[ée]/u', $title) => 'resume',
                (bool) preg_match('/r[ée]action/u', $title) => 'reactions',
                (bool) preg_match('/br[èe]ve/u', $title) => 'breves',
                default => 'autre',
            };
            $out['sections'][] = $s + ['key' => $key];
            $out['all_text'] .= ' ' . plain((string) $s['html']);
        }
        return $out;
    }

    /** Paragraphes d'un résumé qui ne sont pas des temps forts (« 19' : … »). */
    public static function resumeIntro(string $html): string
    {
        $parts = preg_split('#(?=<p[\s>])|(?=<li[\s>])#i', $html) ?: [$html];
        $intro = '';
        foreach ($parts as $p) {
            $txt = trim(html_entity_decode(strip_tags($p), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($txt === '') {
                continue;
            }
            if (preg_match("/^\d{1,3}\s*(?:['’]\s*)?(?:\+\s*\d{1,2}\s*['’]?)?\s*[:\-–]/u", $txt)) {
                break;
            }
            $intro .= $p;
        }
        return $intro;
    }

    /** Bilan face à cet adversaire (global et à la date du match). */
    public static function headToHead(string $club, int $mid, ?string $date): ?array
    {
        $dd = Derived::get();
        $g = $dd['clubs'][$club] ?? null;
        if (!$g) {
            return null;
        }
        $before = ['v' => 0, 'n' => 0, 'd' => 0];
        $prev = [];
        foreach ($g['matches'] as $id) {
            $x = $dd['matches'][$id];
            if ($id === $mid || ($date && strcmp((string) $x['date'], $date) >= 0)) {
                continue;
            }
            if ($x['result']) {
                $before[strtolower($x['result'])]++;
            }
            $prev[] = $x;
        }
        return [
            'club' => $club,
            'name' => self::clubName($club),
            'total' => ['v' => $g['v'], 'n' => $g['n'], 'd' => $g['d'], 'count' => $g['count']],
            'before' => $before,
            'last' => array_slice(array_reverse($prev), 0, 5),
            'href' => url('/face-a-face/' . $club . '/'),
        ];
    }

    public static function clubName(string $club): string
    {
        foreach (\App\Data\Collections::get('clubs', []) as $c) {
            if ($c['id'] === $club) {
                return $c['name'];
            }
        }
        return ucwords(str_replace('-', ' ', $club));
    }

    private static function adjacentMatches(array $doc): array
    {
        $season = $doc['match']['season'] ?? null;
        if (!$season) {
            return [null, null];
        }
        $list = Derived::get()['seasons'][$season]['matches'] ?? [];
        $i = array_search((int) $doc['id'], $list, true);
        if ($i === false) {
            return [null, null];
        }
        $dd = Derived::get()['matches'];
        return [isset($list[$i - 1]) ? $dd[$list[$i - 1]] : null, isset($list[$i + 1]) ? $dd[$list[$i + 1]] : null];
    }

    private static function relatedForMatch(array $doc, array $d, array $rows): array
    {
        $out = [];
        [$prev, $next] = self::adjacentMatches($doc);
        foreach ([$next, $prev] as $x) {
            if ($x && $x['v']) {
                $s = Index::get($x['id']);
                $out[] = ['kind' => t('Match') . ' · ' . ($x['date'] ? date('d/m/Y', strtotime($x['date'])) : ''), 'title' => Site::matchLabel($x), 'href' => url($x['path']), 'image' => $s['image'] ?? null];
            }
        }
        foreach ($rows as $r) {
            if (count($out) >= 4) {
                break;
            }
            if ($r['href'] && ($s = Index::byPath(preg_replace('#^/en#', '', $r['href']))) && $s['image']) {
                $out[] = ['kind' => t('Nos Lions'), 'title' => $s['p']['name'] ?? $s['title'], 'href' => $r['href'], 'image' => $s['image']];
            }
        }
        return array_slice($out, 0, 3);
    }

    // ================================================================== PERSONNE

    public static function person(Request $req, array $doc, ?array $raw = null): Response
    {
        $v = self::withAudio(self::personData($doc), $raw ?? $doc);
        // Le Fil jaune : coéquipiers d'après les compositions, liens vers la constellation et la recherche.
        $fj = \App\Services\FilJaune::player((int) $doc['id']);
        if ($fj) {
            $top = array_slice(\App\Services\FilJaune::teammates($fj['id']), 0, 3);
            $v['vars']['filjaune'] = $fj + ['top' => array_map(fn ($t) => \App\Services\FilJaune::player($t['id']) + ['n' => $t['n']], $top)];
            $v['page']['styles'] = array_merge($v['page']['styles'] ?? [], ['css/filjaune.css']);
        }
        return Pages::render('fiche-personne', $v['vars'], $v['page']);
    }

    /** Données de la fiche (page du site et export PDF). @return array{vars:array,page:array} */
    public static function personData(array $doc): array
    {
        $p = $doc['personne'];
        $id = (int) $doc['id'];
        $dd = Derived::get();
        $tot = $dd['person_totals'][$id] ?? null;
        $matches = Derived::personMatches($id);
        $playerMatches = array_values(array_filter($matches, fn ($x) => $x['role'] === 'player'));
        $coachMatches = array_values(array_filter($matches, fn ($x) => $x['role'] === 'coach'));
        $statsLong = self::statsLong($p['stats'] ?? null);
        $roles = $p['roles'] ?: ['joueur'];
        $mainRole = $roles[0];
        $isCoachOnly = $mainRole === 'entraineur' || ($coachMatches && !$playerMatches && !in_array('joueur', $roles, true));

        // Grands chiffres : calculés depuis les compositions, sinon tirés du tableau de statistiques.
        $statTotals = self::statsTotals($p['stats'] ?? null);
        if ($isCoachOnly && $coachMatches) {
            $big = [
                ['v' => (string) count($coachMatches), 'k' => t('matchs dirigés')],
                ['v' => (string) $tot['v'], 'k' => t('victoires')],
                ['v' => (string) ($tot['n'] ?? 0), 'k' => t('nuls')],
                ['v' => (string) ($tot['d'] ?? 0), 'k' => t('défaites')],
            ];
        } else {
            $big = [
                ['v' => (string) max($tot['matches'] ?? 0, $statTotals['matches'] ?? 0) ?: '–', 'k' => t('matchs')],
                ['v' => (string) max($tot['goals'] ?? 0, $statTotals['goals'] ?? 0), 'k' => t('buts')],
                ['v' => (string) max($tot['seasons'] ?? 0, $statTotals['seasons'] ?? 0) ?: '–', 'k' => t('saisons')],
            ];
        }

        $highlights = self::personHighlights($doc, $matches);
        $rubric = self::personRubric($doc);
        $crumbs = [['label' => t('Accueil'), 'href' => url('/')], ['label' => t('Nos Lions'), 'href' => url('/nos-lions/')]];
        if ($rubric) {
            $crumbs[] = ['label' => t(Categories::label($rubric)), 'href' => Site::catUrl($rubric)];
        }
        [$prev, $next] = self::adjacentPersons($doc, $rubric);

        $name = $p['display_name'] ?: $doc['title'];
        $years = self::personYears($p);
        $roleLabel = self::roleLabel($p);
        $seoTitle = $doc['seo']['title'] ?: trim("$name – " . $roleLabel . ' ' . t('du FC Sochaux-Montbéliard') . ($years ? " ($years)" : ''));
        $desc = $doc['seo']['description'] ?: Unknown::line(trim(implode(' ', array_filter([
            sentence($name . ($p['position'] ? ', ' . $p['position'] : '') . ($years ? ' ' . t('au FCSM') . " ($years)" : '')),
            (string) ($p['birth']['text'] ?? '') !== '' ? sentence(ucfirst((string) $p['birth']['text'])) : '',
            $tot && $tot['matches'] ? $tot['matches'] . ' ' . t('matchs') . ', ' . $tot['goals'] . ' ' . t('buts') . '.' : '',
            excerpt(implode(' ', array_column($doc['sections'], 'html')), 120),
        ]))));
        $base = base_url();
        $jsonld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $name,
            'birthDate' => ($p['birth']['date']['precision'] ?? '') === 'day' ? $p['birth']['date']['iso'] : null,
            'birthPlace' => (string) ($p['birth']['place']['text'] ?? '') !== '' ? ['@type' => 'Place', 'name' => (string) $p['birth']['place']['text']] : null,
            'deathDate' => ($p['death']['date']['precision'] ?? '') === 'day' ? $p['death']['date']['iso'] : null,
            'height' => $p['height_cm'] ? ['@type' => 'QuantitativeValue', 'value' => $p['height_cm'], 'unitCode' => 'CMT'] : null,
            'jobTitle' => $roleLabel,
            'affiliation' => ['@type' => 'SportsTeam', 'name' => 'FC Sochaux-Montbéliard'],
            'image' => $doc['featured_image'] ? $base . img($doc['featured_image'], 800) : null,
            'url' => $base . url($doc['path']),
        ]);

        [$vars, $page] = [[
            'doc' => $doc,
            'p' => $p,
            'tot' => $tot,
            'big' => $big,
            'statsLong' => $statsLong,
            'matches' => $matches,
            'playerMatches' => $playerMatches,
            'coachMatches' => $coachMatches,
            'highlights' => $highlights,
            'crumbs' => $crumbs,
            'prev' => $prev,
            'next' => $next,
            'roleLabel' => $roleLabel,
            'years' => $years,
            'rubric' => $rubric,
            'related' => self::relatedForPerson($doc, $matches),
            'albumNo' => self::albumNumber($id),
        ], [
            'title' => $seoTitle,
            'description' => $desc,
            'image' => '/partage/' . $doc['id'] . '.png',
            'type' => 'profile',
            'active' => 'nos-lions',
            'jsonld' => $jsonld,
            'body_class' => 'page-person',
            'styles' => ['css/fiche.css'],
            'scripts' => ['js/fiche.js'],
        ]];
        return ['vars' => $vars, 'page' => $page];
    }

    /** Numéro de la carte de l'album du centenaire (null si la personne n'y figure pas). */
    private static function albumNumber(int $id): ?int
    {
        foreach (Interactive::albumCards() as $c) {
            if ($c['id'] === $id) {
                return $c['n'];
            }
        }
        return null;
    }

    public static function personYears(array $p): string
    {
        $from = $p['arrival']['iso'] ?? ($p['arrival_coach']['iso'] ?? ($p['trial']['iso'] ?? null));
        $to = $p['departure_coach']['iso'] ?? ($p['departure']['iso'] ?? null);
        $a = $from ? substr((string) $from, 0, 4) : null;
        $b = $to ? substr((string) $to, 0, 4) : null;
        if ($a && $b) {
            return $a === $b ? $a : "$a-$b";
        }
        return $a ? "$a" : '';
    }

    /** Carte du joueur : « Né le 12/03/1950 », « Né en 1925 », « Né en décembre 1955 » ; rien sans date connue. */
    public static function birthRow(array $p): ?array
    {
        $d = is_array($p['birth']['date'] ?? null) ? $p['birth']['date'] : [];
        $iso = (string) ($d['iso'] ?? '');
        $text = trim((string) ($d['text'] ?? ''));
        return match ($d['precision'] ?? null) {
            'day' => $iso !== '' ? [t('Né le'), date_num($iso)] : null,
            'month' => $iso !== '' ? [t('Né en'), (string) preg_replace('/^1er |^\d+ /u', '', date_fr(substr($iso, 0, 7) . '-01'))] : null,
            'year' => $iso !== '' ? [t('Né en'), substr($iso, 0, 4)] : null,
            default => preg_match('/^\d/', $text) ? [t('Né le'), $text] : null,
        };
    }

    /**
     * Fiche d'identité (ordre et libellés d'origine) : lignes à afficher, intertitres repérés
     * (« Carrière de joueur »). Page du site et export PDF.
     * @return list<array{label:string,value:string,sub:bool}>
     */
    public static function idRows(array $p, string $name): array
    {
        $rows = [];
        foreach (is_array($p['fiche'] ?? null) ? $p['fiche'] : [] as $r) {
            $v = Unknown::line(trim((string) ($r['value'] ?? '')));
            $label = trim((string) ($r['label'] ?? ''));
            if ($v === '' || ($label === '' && mb_strtolower($v) === mb_strtolower($name))) {
                continue;
            }
            $sub = $label === '' && mb_strlen($v) < 40 && preg_match('/passage|p[ée]riode|carri[èe]re|joueur|entra[iî]neur|dirigeant/iu', $v) && !preg_match('/\d/', $v);
            $rows[] = ['label' => $label, 'value' => $v, 'sub' => (bool) $sub];
        }
        return $rows;
    }

    public static function roleLabel(array $p): string
    {
        $roles = $p['roles'] ?? ['joueur'];
        if (($roles[0] ?? '') === 'joueur') {
            return $p['position'] ? ucfirst((string) $p['position']) : t('Joueur');
        }
        return match ($roles[0] ?? '') {
            'entraineur' => t('Entraîneur'),
            'dirigeant' => $p['subtitle'] ? strtok((string) $p['subtitle'], ' ') : t('Dirigeant'),
            'personnage' => t('Personnage emblématique'),
            default => t('Joueur'),
        };
    }

    private static function personRubric(array $doc): ?string
    {
        $prio = [Site::C_JOUEURS, Site::C_ENTRAINEURS, Site::C_DIRIGEANTS, Site::C_PERSONNAGES];
        foreach ($prio as $slug) {
            if (in_array($slug, $doc['categories'], true)) {
                return $slug;
            }
        }
        foreach ($doc['categories'] as $c) {
            foreach ($prio as $slug) {
                if (in_array($c, Categories::descendants($slug, false), true)) {
                    return $slug;
                }
            }
        }
        return null;
    }

    private static function adjacentPersons(array $doc, ?string $rubric): array
    {
        if (!$rubric) {
            return [null, null];
        }
        $list = array_values(array_filter(Index::inCategory($rubric), fn ($s) => $s['type'] === 'personne'));
        usort($list, fn ($a, $b) => strcoll(Index::sortName($a), Index::sortName($b)));
        foreach ($list as $i => $s) {
            if ($s['id'] === (int) $doc['id']) {
                return [$list[$i - 1] ?? null, $list[$i + 1] ?? null];
            }
        }
        return [null, null];
    }

    /** Tableau de statistiques (une colonne par compétition) → lignes Saison / Compétition / MJ / Buts. */
    public static function statsLong(?array $stats): ?array
    {
        if (!$stats || empty($stats['headers']) || empty($stats['rows'])) {
            return null;
        }
        $h = $stats['headers'];
        $pairs = [];
        for ($i = 1; $i < count($h); $i++) {
            $label = trim((string) $h[$i]);
            $low = mb_strtolower($label);
            if ($low === 'buts' || str_starts_with($low, 'total')) {
                continue;
            }
            $goalCol = isset($h[$i + 1]) && mb_strtolower(trim((string) $h[$i + 1])) === 'buts' ? $i + 1 : null;
            $pairs[] = ['label' => $label, 'm' => $i, 'g' => $goalCol];
        }
        $totM = $totG = null;
        foreach ($h as $i => $label) {
            $low = mb_strtolower((string) $label);
            if (str_starts_with($low, 'total match')) {
                $totM = $i;
            }
            if (str_starts_with($low, 'total but')) {
                $totG = $i;
            }
        }
        $rows = [];
        foreach ($stats['rows'] as $r) {
            $season = trim((string) ($r[0] ?? ''));
            $isTotal = (bool) preg_match('/^total/iu', $season);
            foreach ($pairs as $pc) {
                $mj = trim((string) ($r[$pc['m']] ?? ''));
                $g = $pc['g'] !== null ? trim((string) ($r[$pc['g']] ?? '')) : '';
                if (($mj === '' || $mj === '-' || $mj === '–') && ($g === '' || $g === '-' || $g === '–')) {
                    continue;
                }
                if ($mj === '0' && ($g === '0' || $g === '')) {
                    continue;
                }
                $rows[] = ['season' => $season, 'comp' => $pc['label'], 'mj' => $mj, 'g' => $g, 'total' => $isTotal];
            }
            if ($totM !== null && !$isTotal) {
                // Les totaux par saison restent disponibles dans la vue « tableau d'origine ».
            }
        }
        return ['rows' => $rows, 'raw' => $stats, 'total_cols' => [$totM, $totG]];
    }

    public static function statsTotals(?array $stats): array
    {
        if (!$stats || empty($stats['rows'])) {
            return [];
        }
        $h = array_map(fn ($x) => mb_strtolower((string) $x), $stats['headers']);
        $iM = $iG = null;
        foreach ($h as $i => $x) {
            if (str_starts_with($x, 'total match')) {
                $iM = $i;
            }
            if (str_starts_with($x, 'total but')) {
                $iG = $i;
            }
        }
        $last = end($stats['rows']);
        $seasons = count(array_filter($stats['rows'], fn ($r) => preg_match('/\d{4}/', (string) ($r[0] ?? ''))));
        $isTotal = $last && preg_match('/^total/iu', (string) ($last[0] ?? ''));
        return [
            'matches' => $isTotal && $iM !== null ? (int) $last[$iM] : 0,
            'goals' => $isTotal && $iG !== null ? (int) $last[$iG] : 0,
            'seasons' => $seasons,
        ];
    }

    /** Matchs marquants : choisis dans le back-office, sinon premier match, premier but, dernier match. */
    private static function personHighlights(array $doc, array $matches): array
    {
        $p = $doc['personne'];
        $out = [];
        foreach ($p['highlight_matches'] ?? [] as $mid) {
            if ($x = Derived::match((int) $mid)) {
                $out[] = ['label' => t('Match marquant'), 'm' => $x];
            }
        }
        if ($out) {
            return $out;
        }
        $byDate = [];
        foreach (Derived::get()['matches'] as $x) {
            if ($x['date'] && $x['v']) {
                $byDate[$x['date']][] = $x;
            }
        }
        $find = function (?string $ref) use ($byDate): ?array {
            if (!$ref || !preg_match('#(\d{1,2})/(\d{1,2})/(\d{4})#', $ref, $mm)) {
                return null;
            }
            $date = sprintf('%04d-%02d-%02d', $mm[3], $mm[2], $mm[1]);
            return $byDate[$date][0] ?? null;
        };
        foreach ([['first_match', 'Premier match'], ['first_goal', 'Premier but'], ['last_match', 'Dernier match'], ['first_match_coached', 'Premier match dirigé'], ['last_match_coached', 'Dernier match dirigé']] as [$k, $label]) {
            if (!empty($p[$k])) {
                $x = $find($p[$k]);
                // Modèle de l'ancien site jamais rempli (« Sochaux - xx du xx/xx/2025 : x-x ») : vidé par Unknown.
                if ($x || !Unknown::has((string) $p[$k])) {
                    $out[] = ['label' => t($label), 'm' => $x, 'text' => $p[$k]];
                }
            }
        }
        // Les buts : matchs où il a marqué plusieurs fois
        $multi = array_filter($matches, fn ($x) => ($x['goals'] ?? 0) >= 2);
        usort($multi, fn ($a, $b) => $b['goals'] <=> $a['goals']);
        foreach (array_slice($multi, 0, 2) as $x) {
            $out[] = ['label' => $x['goals'] . ' ' . t('buts'), 'm' => $x];
        }
        return array_slice($out, 0, 5);
    }

    private static function relatedForPerson(array $doc, array $matches): array
    {
        $out = [];
        $seen = [(int) $doc['id'] => true];
        // Coéquipiers les plus fréquents
        $mates = [];
        foreach ($matches as $x) {
            foreach (Derived::lineupLinks((int) $x['id']) as $a) {
                if (!isset($seen[$a[0]])) {
                    $mates[$a[0]] = ($mates[$a[0]] ?? 0) + 1;
                }
            }
        }
        arsort($mates);
        foreach (array_keys($mates) as $pid) {
            $s = Index::get($pid);
            if ($s && Index::visible($s) && $s['image']) {
                $out[] = ['kind' => t('Coéquipier'), 'title' => $s['p']['name'], 'href' => url($s['path']), 'image' => $s['image']];
            }
            if (count($out) >= 3) {
                break;
            }
        }
        if (count($out) < 3) {
            $cat = $doc['categories'][0] ?? null;
            foreach ($cat ? Index::inCategory($cat) : [] as $s) {
                if (!isset($seen[$s['id']]) && $s['image'] && $s['type'] === 'personne') {
                    $seen[$s['id']] = true;
                    $out[] = ['kind' => t('Nos Lions'), 'title' => $s['p']['name'], 'href' => url($s['path']), 'image' => $s['image']];
                }
                if (count($out) >= 3) {
                    break;
                }
            }
        }
        return $out;
    }

    // ================================================================== ARTICLE / PAGE

    public static function article(Request $req, array $doc): Response
    {
        $v = self::withAudio(self::articleData($doc), $doc);
        return Pages::render('fiche-article', $v['vars'], $v['page']);
    }

    /** Données de la fiche (page du site et export PDF). @return array{vars:array,page:array} */
    public static function articleData(array $doc): array
    {
        $cat = Categories::primaryOf($doc['categories']);
        $root = $cat ? Categories::root($cat) : null;
        $crumbs = [['label' => t('Accueil'), 'href' => url('/')]];
        foreach ($cat ? Categories::trail($cat) : [] as $c) {
            if (!empty($c['path'])) {
                $crumbs[] = ['label' => t(Categories::label($c['slug'])), 'href' => url($c['path'])];
            }
        }
        $active = match ($root) {
            'infrastructures', 'symboles', 'supporters' => $root,
            Site::C_MATCHS => 'matchs',
            Site::C_LIONS => 'nos-lions',
            default => '',
        };
        $related = [];
        if ($cat) {
            foreach (Index::inCategory($cat) as $s) {
                if ($s['id'] !== (int) $doc['id'] && count($related) < 3) {
                    $related[] = ['kind' => t(Categories::label($cat)), 'title' => Pages::shortTitle($s), 'href' => url($s['path']), 'image' => $s['image']];
                }
            }
        }
        $season = $doc['article']['season'] ?? null;
        [$vars, $page] = [[
            'doc' => $doc,
            'crumbs' => $crumbs,
            'kicker' => $cat ? t(Categories::label($cat)) : ($doc['type'] === 'page' ? '' : t('Le musée')),
            'related' => $related,
            'seasonLink' => $season ? url("/matchs/$season/") : null,
        ], [
            'title' => $doc['seo']['title'] ?: $doc['title'],
            'description' => $doc['seo']['description'] ?: excerpt(implode(' ', array_column($doc['sections'], 'html')), 180),
            'image' => '/partage/' . $doc['id'] . '.png',
            'type' => 'article',
            'active' => $active,
            'body_class' => 'page-article',
            'styles' => ['css/fiche.css'],
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $doc['title'],
                'datePublished' => $doc['date'],
                'dateModified' => $doc['modified'],
                'author' => ['@type' => 'Organization', 'name' => 'Sochaux Rétro'],
                'image' => $doc['featured_image'] ? base_url() . img($doc['featured_image'], 1200) : null,
            ],
        ]];
        return ['vars' => $vars, 'page' => $page];
    }
}
