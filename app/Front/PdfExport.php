<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Data\Categories;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;
use App\Pdf\HtmlFlow;
use App\Pdf\Layout;
use App\Services\I18n;
use App\Services\Images;

/**
 * Export PDF : un vrai document mis en page aux couleurs du musée (et non une impression
 * de la page web), pour les fiches (matchs, personnes, articles, objets, moments) et les pages
 * de synthèse (saisons, face-à-face, bilans). Fichiers mis en cache tant que rien ne change.
 */
final class PdfExport
{
    /** À augmenter quand la mise en page change (les PDF en cache sont alors refaits). */
    public const VERSION = '2';
    private const DIR = STORAGE_PATH . '/cache/pdf';

    // ================================================================== adresses

    public static function ficheUrl(array $doc): string
    {
        return url('/pdf/fiche/' . (int) $doc['id'] . '.pdf');
    }

    public static function seasonUrl(string $season): string
    {
        return url('/pdf/saison/' . $season . '.pdf');
    }

    public static function opponentUrl(string $club): string
    {
        return url('/pdf/face-a-face/' . $club . '.pdf');
    }

    public static function bilanUrl(string $key): string
    {
        return url('/pdf/bilan/' . $key . '.pdf');
    }

    public static function recordsUrl(): string
    {
        return url('/pdf/records.pdf');
    }

    // ================================================================== points d'entrée

    public static function fiche(Request $req, int $id): ?Response
    {
        $doc = Fiches::get($id);
        if (!$doc || ($doc['status'] ?? '') === 'corbeille' || Pages::isListingRedirect($doc)) {
            return null;
        }
        $visible = Fiches::isVisible($doc);
        if (!$visible && !Auth::user()) {
            return null;
        }
        $doc = Fiche::localizeDoc($doc);
        $key = 'fiche-' . $id . '-' . I18n::lang() . '-' . self::stamp((string) ($doc['modified'] ?? ''));
        return self::respond($req, $key, 'sochaux-retro-' . ($doc['slug'] ?: $id), fn () => self::buildFiche($doc), $visible);
    }

    public static function season(Request $req, string $season): ?Response
    {
        if (!preg_match('/^\d{4}-\d{4}$/', $season)) {
            return null;
        }
        $v = Explore::seasonData($season);
        if (!$v) {
            return null;
        }
        return self::respond($req, 'saison-' . $season . '-' . I18n::lang() . '-' . self::stamp(), 'sochaux-retro-saison-' . $season, fn () => self::buildSeason($season, $v['vars']));
    }

    public static function opponent(Request $req, string $club): ?Response
    {
        if (!preg_match('/^[a-z0-9-]{1,80}$/', $club)) {
            return null;
        }
        $v = Explore::opponentData($club);
        if (!$v) {
            return null;
        }
        $name = Fiche::clubName($club);
        return self::respond($req, 'h2h-' . $club . '-' . I18n::lang() . '-' . self::stamp(), 'sochaux-retro-face-a-face-' . $club, fn () => self::buildH2h($v['vars'], t('Face-à-face'), 'Sochaux × ' . $name, self::opponentUrl($club), url('/face-a-face/' . $club . '/')));
    }

    public static function bilan(Request $req, string $key): ?Response
    {
        if (!preg_match('/^[a-z0-9-]{1,80}$/', $key)) {
            return null;
        }
        $v = Explore::bilanPage($key);
        if (!$v) {
            return null;
        }
        $title = trim(html_entity_decode(strip_tags((string) $v['vars']['titleHtml']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return self::respond($req, 'bilan-' . $key . '-' . I18n::lang() . '-' . self::stamp(), 'sochaux-retro-bilan-' . $key, fn () => self::buildH2h($v['vars'], t('Bilan'), $title, self::bilanUrl($key), url('/bilans/' . $key . '/')));
    }

    public static function records(Request $req): Response
    {
        return self::respond($req, 'records-' . I18n::lang() . '-' . self::stamp(), 'sochaux-retro-livre-des-records', fn () => self::buildRecords());
    }

    // ================================================================== cache et réponse

    /** Empreinte : version de la mise en page + données calculées (statistiques, liens entre fiches). */
    private static function stamp(string $extra = ''): string
    {
        return substr(sha1(self::VERSION . '|' . Derived::built() . '|' . $extra . '|' . base_url()), 0, 12);
    }

    /** PDF envoyé en téléchargement : repris du cache s'il existe, sinon fabriqué (limité par adresse IP pour les visiteurs). */
    public static function respond(Request $req, string $key, string $name, callable $build, bool $cache = true): Response
    {
        $file = self::DIR . '/' . preg_replace('/[^a-z0-9._-]/i', '-', $key) . '.pdf';
        if ($cache && is_file($file)) {
            $bytes = (string) file_get_contents($file);
        } else {
            // Fabrication limitée par adresse IP (les PDF déjà prêts, eux, sont servis sans limite).
            if (!Auth::user() && !RateLimiter::hit('pdf', $req->ip(), 40, 600)) {
                return new Response(t('Trop de demandes de PDF en peu de temps : réessayez dans quelques minutes.'), 429, ['Content-Type' => 'text/plain; charset=utf-8', 'Retry-After' => '600']);
            }
            @set_time_limit(120);
            $bytes = $build();
            if ($cache) {
                if (!is_dir(self::DIR)) {
                    @mkdir(self::DIR, 0775, true);
                }
                $tmp = $file . '.' . bin2hex(random_bytes(3)) . '.tmp';
                if (@file_put_contents($tmp, $bytes) !== false) {
                    @rename($tmp, $file);
                }
                self::gc();
            }
        }
        $name = trim((string) preg_replace('/[^a-z0-9-]+/', '-', strtolower(Names::ascii($name))), '-') ?: 'sochaux-retro';
        return new Response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $name . '.pdf"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => $cache ? 'public, max-age=3600' : 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Ménage : les PDF de plus de 30 jours (de temps en temps). */
    private static function gc(): void
    {
        if (random_int(1, 40) !== 1) {
            return;
        }
        // Un fichier peut disparaître entre-temps (PDF fabriqué en même temps par une autre demande).
        foreach (glob(self::DIR . '/*.pdf') ?: [] as $f) {
            if ((@filemtime($f) ?: time()) < time() - 30 * 86400) {
                @unlink($f);
            }
        }
        foreach (glob(self::DIR . '/*.tmp') ?: [] as $f) {
            if ((@filemtime($f) ?: time()) < time() - 3600) {
                @unlink($f);
            }
        }
    }

    // ================================================================== briques communes

    public static function layout(string $path, string $running, string $title, string $subject): Layout
    {
        $l = new Layout();
        $l->url = base_url() . url($path);
        $l->running = $running;
        $l->tagline = t('Le musée en ligne du FC Sochaux-Montbéliard');
        $l->exported = t('Document exporté le {d}', ['d' => date_fr(date('Y-m-d'))]);
        $l->pageWord = t('Page');
        $l->pdf->lang = I18n::isEn() ? 'en-GB' : 'fr-FR';
        $l->pdf->info = [
            'Title' => $title . ' · Sochaux Rétro',
            'Author' => 'Sochaux Rétro',
            'Subject' => $subject,
            'Keywords' => 'FC Sochaux-Montbéliard, FCSM, Sochaux Rétro, ' . $title,
            'Creator' => 'Sochaux Rétro (' . preg_replace('#^https?://#', '', base_url()) . ')',
        ];
        return $l;
    }

    /** Surtitre, grand titre (réduit s'il est long) et sous-titre. */
    public static function titleBlock(Layout $l, string $eyebrow, string $title, string $sub = '', string $pre = ''): void
    {
        if ($eyebrow !== '') {
            $l->para([Layout::run(mb_strtoupper($eyebrow), 'display-b', 9.6, 'blue', null, 1.3)], ['after' => 3]);
        }
        if ($pre !== '') {
            $l->para([Layout::run(mb_strtoupper($pre), 'display-b', 19, 'muted', null, 0.5)], ['lh' => 1.0, 'after' => 0]);
        }
        $t = mb_strtoupper($title);
        $size = 44;
        while ($size > 22 && count($l->wrap([Layout::run($t, 'display', $size, 'navy', null, 0.2)], $l->cw(), 1.0)) > ($size > 30 ? 2 : 3)) {
            $size -= 3;
        }
        $l->para([Layout::run($t, 'display', $size, 'navy', null, 0.2)], ['lh' => 0.98, 'after' => 5]);
        if ($sub !== '') {
            $l->para([Layout::run($sub, 'serif-i', 12, 'muted')], ['after' => 12]);
        } else {
            $l->gap(8);
        }
    }

    /** Grands chiffres dans des cartouches (matchs, buts, victoires…). @param list<array{0:string,1:string,2?:bool}> $items */
    public static function bigNumbers(Layout $l, array $items): void
    {
        $items = array_values(array_filter($items, fn ($i) => $i[0] !== '' && $i[0] !== '–'));
        $n = count($items);
        if (!$n) {
            return;
        }
        $gap = 8;
        $w = ($l->cw() - $gap * ($n - 1)) / $n;
        $h = 56;
        $l->ensure($h + 14);
        foreach ($items as $i => $it) {
            $x = $l->ml + $i * ($w + $gap);
            $l->rect($x + 3, $l->y + 3, $w, $h, 'navy');
            $l->rect($x, $l->y, $w, $h, !empty($it[2]) ? 'yellow' : 'paper', 'navy', 1.2);
            $size = 27;
            while ($size > 12 && $l->width($it[0], 'display', $size) > $w - 16) {
                $size -= 2;
            }
            $l->text($x + 9, $l->y + 31, $it[0], 'display', $size, 'navy');
            $l->text($x + 9, $l->y + 46, $l->fit(mb_strtoupper($it[1]), 'display-b', 7.4, $w - 14, 0.7), 'display-b', 7.4, 'muted', 0.7);
        }
        $l->y += $h + 18;
    }

    /** Photo d'un média du musée (déclinaison à la taille utile). */
    private static function photo(Layout $l, ?string $rel, int $width = 1200): ?array
    {
        if (!$rel) {
            return null;
        }
        $file = Images::derivative($rel, $width);
        return $file ? $l->loadImage($file) : null;
    }

    /** Image citée dans un texte (adresse /media/… ou ancienne adresse WordPress). */
    private static function imageFromSrc(Layout $l, string $src): ?array
    {
        $path = (string) (parse_url($src, PHP_URL_PATH) ?: '');
        if (preg_match('#^/media/(?:\d+|full)/(.+)$#', $path, $m)) {
            $rel = rawurldecode($m[1]);
            if (str_ends_with($rel, '.webp') && !\App\Data\Media::file($rel)) {
                $rel = substr($rel, 0, -5);
            }
        } elseif (preg_match('#/wp-content/uploads/(.+)$#', $path, $m)) {
            $rel = rawurldecode($m[1]);
        } else {
            return null;
        }
        return self::photo($l, $rel, 1200);
    }

    private static function flow(Layout $l, float $size = 10.5): HtmlFlow
    {
        return new HtmlFlow($l, fn (string $src) => self::imageFromSrc($l, $src), $size);
    }

    private static function abs(?string $path): ?string
    {
        return $path ? (str_starts_with($path, 'http') ? $path : base_url() . $path) : null;
    }

    /** Galerie, vidéos et tableaux de données d'une fiche. */
    private static function extras(Layout $l, array $doc, string $galleryTitle): void
    {
        $tables = array_values(array_filter($doc['tables'] ?? [], fn ($t) => !empty($t['rows'])));
        foreach ($tables as $i => $tb) {
            $title = trim((string) ($tb['title'] ?? ''));
            $l->h2($title !== '' ? $title : t('Tableau') . ($i ? ' ' . ($i + 1) : ''));
            self::flow($l)->tableData(array_map('strval', $tb['headers'] ?? []), array_map(fn ($r) => array_map(fn ($c) => (string) $c, (array) $r), $tb['rows']));
        }
        $items = [];
        foreach ($doc['gallery'] ?? [] as $g) {
            $img = self::photo($l, $g['image'] ?? null, 640);
            if ($img) {
                $cap = trim(($g['caption'] ?? '') . (!empty($g['credit']) ? ' – ' . $g['credit'] : ''), ' –');
                $items[] = ['img' => $img, 'caption' => $cap];
            }
        }
        if ($items) {
            $l->h2($galleryTitle);
            $l->gallery($items, count($items) === 4 ? 2 : 3);
        }
        $videos = array_values(array_filter(array_map('video_embed', $doc['videos'] ?? [])));
        if ($videos) {
            $l->h2(count($videos) > 1 ? t('Vidéos') : t('Vidéo'));
            $list = [];
            foreach ($videos as $v) {
                $u = (string) ($v['link'] ?? '');
                $u = str_starts_with($u, '/') ? base_url() . $u : $u;
                if ($u === '') {
                    continue;
                }
                $list[] = [Layout::run(trim((string) ($v['title'] ?? '')) !== '' ? $v['title'] . ' : ' : '', 'serif-b'), Layout::run($u, 'serif', 10.5, 'blue', $u)];
            }
            $l->bullets($list);
        }
    }

    /** Pastille de résultat (V, N, D) pour les tableaux de matchs. */
    private static function resultCell(?string $r): array
    {
        $r = $r ?: '–';
        return ['t' => $r === '–' ? $r : t($r), 'b' => true, 'c' => match ($r) { 'V' => 'green', 'D' => 'red', default => 'muted' }];
    }

    private static function score(array $x): string
    {
        return $x['us'] !== null ? ($x['sh'] ? $x['us'] . '-' . $x['them'] : $x['them'] . '-' . $x['us']) : '–';
    }

    /** Tableau de matchs (date, compétition, affiche, score, résultat). */
    private static function matchTable(Layout $l, array $list, bool $withSeason = false): void
    {
        $rows = [];
        foreach ($list as $x) {
            $name = trim(($x['home'] ?? '') . ' – ' . ($x['away'] ?? ''), ' –') ?: (string) ($x['event'] ?? $x['title'] ?? '');
            $rows[] = [
                date_num($x['date']),
                comp_round($x['label'] ?: $x['comp'], $x['round'] ?? null),
                ['t' => $name, 'u' => self::abs(url($x['path']))],
                ['t' => self::score($x) . (!empty($x['extra']) ? ' ' . $x['extra'] : ''), 'b' => true],
                self::resultCell($x['result'] ?? null),
            ];
        }
        $l->table([t('Date'), t('Compétition'), t('Match'), t('Score'), t('Rés.')], $rows, ['align' => ['l', 'l', 'l', 'c', 'c'], 'size' => 8.2]);
    }

    // ================================================================== fiches

    public static function buildFiche(array $doc): string
    {
        return match ($doc['type']) {
            'match' => self::buildMatch($doc),
            'personne' => self::buildPerson($doc),
            default => self::buildArticle($doc),
        };
    }

    private static function buildMatch(array $doc): string
    {
        $v = Fiche::matchData($doc)['vars'];
        $m = $v['m'];
        $rows = $v['rows'];
        $home = (string) ($m['home']['name'] ?? '');
        $away = (string) ($m['away']['name'] ?? '');
        $hasTeams = $home !== '' && $away !== '';
        $hasScore = isset($m['score']['home'], $m['score']['away']);
        $score = $hasScore ? $m['score']['home'] . ' – ' . $m['score']['away'] : '';
        $compLabel = $m['competition_label'] ?: ($m['competition'] ?? '');
        $date = I18n::isEn() && $m['date'] ? date_fr($m['date'], true) : ($m['date_text'] ?: ($m['date'] ? date_fr($m['date'], true) : ''));
        $running = trim($v['title'] . ($hasScore ? ' ' . $m['score']['home'] . '-' . $m['score']['away'] : '') . ($m['date'] ? ' · ' . date_num($m['date']) : ''));
        $l = self::layout($doc['path'], $running, $running, t('Fiche match') . ' · ' . $compLabel);
        $l->newPage();
        $l->masthead(t('Fiche match'));
        $round = trim((string) ($m['round_text'] ?? ''));
        self::titleBlock($l, implode(' · ', array_filter([$compLabel, $round !== '' && mb_strtolower($round) !== mb_strtolower($compLabel) ? $round : null, !empty($m['season']) ? t('Saison') . ' ' . $m['season'] : null])), $hasTeams ? $home . ' – ' . $away : $v['title'], trim($date . (!empty($m['stadium']) ? ' · ' . $m['stadium'] : ''), ' ·'));

        if ($hasTeams) {
            $extra = [];
            if (!empty($m['score']['aet'])) {
                $extra[] = t('après prolongation');
            }
            if (!empty($m['score']['pens'])) {
                $pp = $m['score']['pens'];
                $extra[] = t('tirs au but') . ' ' . (is_array($pp) ? ($pp['home'] ?? '?') . '-' . ($pp['away'] ?? '?') : $pp);
            } elseif (!empty($m['score']['extra']) && empty($m['score']['aet'])) {
                $extra[] = (string) $m['score']['extra'];
            }
            self::scoreboard($l, $home, $away, $hasScore ? $score : '–', (string) ($m['result'] ?? ''), implode(' · ', $extra), (bool) ($m['sochaux_home'] ?? false));
        }

        $coach = '';
        foreach ($rows as $r) {
            if ($r['position'] === 'E') {
                $coach = $r['display'] ?? $r['name'];
            }
        }
        $goals = [];
        foreach ($m['goals'] ?? [] as $g) {
            $goals[] = $g['team'] . ' : ' . $g['scorers'];
        }
        $l->facts([
            [t('Date'), $date],
            [t('Stade'), (string) ($m['stadium'] ?? '')],
            [t('Spectateurs'), !empty($m['spectators']) ? number_format((int) $m['spectators'], 0, ',', ' ') : trim((string) preg_replace('/\s*spectateurs?\s*$/iu', '', (string) ($m['spectators_text'] ?? '')))],
            [t('Arbitre'), (string) ($m['referee'] ?? '')],
            [t('Compétition'), $compLabel],
            [t('Entraîneur'), $coach],
        ]);
        if ($goals || !empty($m['goals_text']) || !empty($m['header_extra'])) {
            $runs = [Layout::run(mb_strtoupper(t('Buts')), 'display-b', 8.5, 'muted', null, 0.9, 8), Layout::run($goals ? implode(' · ', $goals) : self::marks((string) ($m['goals_text'] ?? '')), 'serif-b', 10.5, 'navy')];
            foreach ($m['header_extra'] ?? [] as $x) {
                $runs[] = Layout::run("\n" . $x, 'serif-i', 10, 'muted');
            }
            $l->para($runs, ['after' => 12]);
        }
        $img = self::photo($l, $doc['featured_image'] ?? null);
        if ($img) {
            $l->figure($img, '', ['h' => 250]);
        }
        if (!empty($doc['key_figure']['number']) || !empty($doc['key_figure']['text'])) {
            $l->keyFigure((string) ($doc['key_figure']['number'] ?? ''), (string) ($doc['key_figure']['text'] ?? ''));
        }
        if (!empty($m['event']) && $hasTeams) {
            $l->para([Layout::run((string) $m['event'], 'serif-i', 12, 'navy')], ['after' => 10]);
        }
        if (!empty($doc['intro'])) {
            self::flow($l, 11.5)->render((string) $doc['intro']);
        }

        // Récit : avant-match, résumé minute par minute, réactions, brèves…
        $timelineDone = false;
        foreach ($v['resume']['sections'] as $s) {
            $html = trim((string) $s['html']);
            $key = $s['key'];
            $struct = match ($key) {
                'resume' => !empty($m['highlights']),
                'reactions' => !empty($m['reactions']),
                'breves' => !empty($m['breves']),
                default => false,
            };
            if ($html === '' && !$struct) {
                continue;
            }
            $l->h2(trim((string) $s['title']) !== '' ? (string) $s['title'] : match ($key) {
                'avant' => t('Avant-match'), 'resume' => t('Résumé'), 'reactions' => t('Réactions'), 'breves' => t('Brèves'), default => t('Récit'),
            });
            if ($key === 'resume' && $struct && !$timelineDone) {
                $timelineDone = true;
                if ($intro = Fiche::resumeIntro($html)) {
                    self::flow($l)->render($intro);
                }
                self::timeline($l, $m['highlights'], (bool) ($m['sochaux_home'] ?? false));
            } elseif ($key === 'reactions' && $struct) {
                foreach ($m['reactions'] as $q) {
                    $txt = trim((string) preg_replace(['/^\s*[«"“]\s*/u', '/\s*[»"”]\s*([.!?…]?)\s*$/u'], ['', '$1'], strip_tags((string) $q['text'])));
                    $l->quote([Layout::run('« ' . $txt . ' »', 'serif-i', 11, 'navy')], trim((string) ($q['who'] ?? '')) ?: null);
                }
                $m['reactions'] = [];
            } elseif ($key === 'breves' && $struct) {
                $l->bullets(array_map(fn ($b) => [Layout::run(trim(strip_tags(is_array($b) ? ($b['text'] ?? '') : (string) $b)))], $m['breves']), ['numbered' => true]);
                $m['breves'] = [];
            } elseif (!($key === 'resume' && $struct)) {
                self::flow($l)->render($html);
            }
        }
        foreach ($doc['images'] ?? [] as $im) {
            $img = self::photo($l, $im['image'] ?? null);
            if ($img) {
                $l->figure($img, trim(($im['caption'] ?? '') . (!empty($im['credit']) ? ' – ' . $im['credit'] : ''), ' –'), ['h' => 280]);
            }
        }

        // Composition
        if ($rows) {
            $l->h2(t('Composition'));
            if ($v['pitch']) {
                self::pitch($l, $v['pitch']);
            }
            self::lineupTable($l, $rows, $m);
        }
        foreach ($m['other_lineups'] ?? [] as $ol) {
            if (empty($ol['rows'])) {
                continue;
            }
            $l->h3((string) ($ol['title'] ?? t('Composition')));
            $tr = [];
            foreach ($ol['rows'] as $r) {
                $tr[] = [['t' => (string) ($r['position'] ?? ''), 'b' => true], (string) ($r['number'] ?? ''), (string) ($r['name'] ?? '') . (!empty($r['captain']) ? ' (c)' : ''), self::marks((string) ($r['goals_text'] ?? '')), self::marks((string) ($r['sub_text'] ?? '')), self::marks((string) ($r['cards_text'] ?? ''))];
            }
            $l->table([t('Poste'), t('N°'), t('Joueur'), t('Buts'), t('Changements'), t('Cartons')], $tr, ['align' => ['c', 'c', 'l', 'l', 'l', 'l'], 'size' => 8.2]);
        }

        // Face-à-face
        $h = $v['h2h'];
        if ($h && $h['total']['count'] > 1) {
            $l->h2(t('Face-à-face') . ' : Sochaux × ' . $h['name']);
            self::bigNumbers($l, [[(string) $h['total']['count'], t('matchs')], [(string) $h['total']['v'], t('victoires'), true], [(string) $h['total']['n'], t('nuls')], [(string) $h['total']['d'], t('défaites')]]);
            if ($h['last']) {
                $l->h3(t('Les rencontres précédentes'));
                self::matchTable($l, $h['last']);
            }
            $l->para([Layout::run(t('Tout le face-à-face') . ' : ', 'serif-b', 10, 'navy'), Layout::run(base_url() . $h['href'], 'serif', 10, 'blue', base_url() . $h['href'])], ['after' => 8]);
        }
        self::extras($l, $doc, t('Galerie du match'));
        return $l->finish();
    }

    /** Tableau d'affichage : équipes, score, résultat. */
    private static function scoreboard(Layout $l, string $home, string $away, string $score, string $result, string $extra, bool $sochauxHome): void
    {
        $h = 78;
        $l->ensure($h + 20);
        $y = $l->y;
        $l->rect($l->ml + 5, $y + 5, $l->cw(), $h, 'yellow');
        $l->rect($l->ml, $y, $l->cw(), $h, 'navy');
        $cx = $l->ml + $l->cw() / 2;
        $sw = 128;
        $l->rect($cx - $sw / 2, $y + 11, $sw, $h - 22, 'yellow');
        $ss = 33;
        $tw = $l->width($score, 'display', $ss, 0.5);
        $base = $y + ($extra !== '' ? $h / 2 + 6 : $h / 2 + $l->cap('display', $ss) / 2);
        $l->text($cx - $tw / 2, $base, $score, 'display', $ss, 'navy', 0.5);
        if ($extra !== '') {
            $e = $l->fit(mb_strtoupper($extra), 'display-b', 7, $sw - 10, 0.5);
            $l->text($cx - $l->width($e, 'display-b', 7, 0.5) / 2, $y + $h - 17, $e, 'display-b', 7, 'navy', 0.5);
        }
        $max = ($l->cw() - $sw) / 2 - 26;
        $size = 21;
        while ($size > 12 && max($l->width(mb_strtoupper($home), 'display', $size, 0.4), $l->width(mb_strtoupper($away), 'display', $size, 0.4)) > $max) {
            $size -= 1;
        }
        $hn = $l->fit(mb_strtoupper($home), 'display', $size, $max, 0.4);
        $an = $l->fit(mb_strtoupper($away), 'display', $size, $max, 0.4);
        $nb = $y + $h / 2 + $l->cap('display', $size) / 2;
        $l->text($cx - $sw / 2 - 14 - $l->width($hn, 'display', $size, 0.4), $nb, $hn, 'display', $size, $sochauxHome ? 'yellow' : 'cream', 0.4);
        $l->text($cx + $sw / 2 + 14, $nb, $an, 'display', $size, $sochauxHome ? 'cream' : 'yellow', 0.4);
        if (in_array($result, ['V', 'N', 'D'], true)) {
            $label = mb_strtoupper(match ($result) { 'V' => t('Victoire'), 'N' => t('Match nul'), default => t('Défaite') });
            $lw = $l->width($label, 'display', 8.5, 1) + 16;
            $l->rect($l->ml + 12, $y - 9, $lw, 18, match ($result) { 'V' => 'green', 'D' => 'red', default => 'muted' });
            $l->text($l->ml + 20, $y + 3.2, $label, 'display', 8.5, 'white', 1);
        }
        $l->y += $h + 22;
    }

    /** Minute par minute : buts surlignés. */
    private static function timeline(Layout $l, array $highlights, bool $sh): void
    {
        $rows = [];
        foreach ($highlights as $hl) {
            $txt = trim((string) ($hl['text'] ?? ''));
            $goal = !empty($hl['goal']);
            if ($goal) {
                $txt = trim((string) preg_replace('/\s*\(\s*\d+\s*-\s*\d+\s*\)\s*\.?\s*$/u', '', $txt));
            }
            $min = trim((string) ($hl['minute'] ?? ''));
            $row = [
                ['t' => $min !== '' ? $min . "'" : '', 'b' => true, 'c' => 'blue'],
                $goal ? ['runs' => [Layout::run(mb_strtoupper(t('But !')) . ' ' . ($hl['score'] ?? ''), 'display', 8.6, 'navy', null, 0.6, 7), Layout::run($txt, 'serif-b', 8.8, 'ink')]] : $txt,
            ];
            if ($goal) {
                $row['_bg'] = 'butter';
            }
            $rows[] = $row;
        }
        $l->table([t('Min.'), t('Action')], $rows, ['widths' => [0.09, 0.91], 'align' => ['c', 'l'], 'size' => 8.8, 'zebra' => false]);
    }

    /** Terrain vu de côté : titulaires placés selon leur poste. */
    private static function pitch(Layout $l, array $pitch): void
    {
        $w = $l->cw();
        $h = 214;
        $l->ensure($h + 30);
        $x0 = $l->ml;
        $y0 = $l->y;
        $l->rect($x0 + 4, $y0 + 4, $w, $h, 'yellow');
        $l->rect($x0, $y0, $w, $h, 'navy');
        // Bandes de tonte
        for ($i = 0; $i < 10; $i += 2) {
            $l->rect($x0 + $i * $w / 10, $y0, $w / 10, $h, 'stripe');
        }
        $lc = 'mist';
        $l->rect($x0 + 8, $y0 + 8, $w - 16, $h - 16, null, $lc, 1);
        $l->line($x0 + $w / 2, $y0 + 8, $x0 + $w / 2, $y0 + $h - 8, $lc, 1);
        $l->rect($x0 + 8, $y0 + $h / 2 - 48, 54, 96, null, $lc, 1);
        $l->rect($x0 + $w - 62, $y0 + $h / 2 - 48, 54, 96, null, $lc, 1);
        $l->rect($x0 + $w / 2 - 1.5, $y0 + $h / 2 - 1.5, 3, 3, $lc);
        foreach ($pitch['players'] as $p) {
            // y : 90 = gardien (à gauche) … 23 = attaquants (à droite) ; x : position en largeur
            $px = $x0 + 30 + (90 - (float) $p['y']) / 67 * ($w - 150);
            $py = $y0 + 22 + ((float) $p['x'] - 16) / 68 * ($h - 44);
            $l->rect($px - 9, $py - 9, 18, 18, 'yellow', 'deep', 1.2);
            $num = (string) $p['num'];
            $l->text($px - $l->width($num, 'display', 10) / 2, $py + $l->cap('display', 10) / 2, $num, 'display', 10, 'navy');
            // Nom à droite du numéro (évite les chevauchements dans une ligne de cinq)
            $name = $l->fit(($p['short'] ?? '') . (!empty($p['captain']) ? ' (c)' : ''), 'display-b', 8, 92, 0.3);
            $l->rect($px + 11, $py - 6.5, $l->width($name, 'display-b', 8, 0.3) + 8, 13, 'deep');
            $l->text($px + 15, $py + $l->cap('display-b', 8) / 2, $name, 'display-b', 8, 'cream', 0.3);
        }
        $f = mb_strtoupper(t('Formation')) . ' ' . $pitch['formation'];
        $l->text($x0 + $w - 14 - $l->width($f, 'display-b', 8, 0.8), $y0 + $h - 14, $f, 'display-b', 8, 'yellow', 0.8);
        $l->y += $h + 16;
    }

    /**
     * Buts, remplacements et cartons tels que saisis (« ⚽ 12' », « ↑ 46' ↓ 80' », « 🟨 47' ») :
     * ces symboles manquent aux polices du PDF ; ils deviennent l'écriture en lettres du
     * back-office (« 12' », « Entrée 46' Sortie 80' », « J 47' »).
     */
    private static function marks(string $s): string
    {
        $s = strtr($s, [
            "\u{FE0F}" => '', '⚽' => ' ',
            '↑' => ' Entrée ', '🔺' => ' Entrée ', '⬆' => ' Entrée ',
            '↓' => ' Sortie ', '🔻' => ' Sortie ', '⬇' => ' Sortie ',
            '🟨' => ' J ', '🟥' => ' R ',
        ]);
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    private static function lineupTable(Layout $l, array $rows, array $m): void
    {
        $groups = [
            t('Titulaires') => array_filter($rows, fn ($r) => in_array($r['position'], ['G', 'D', 'M', 'A'], true)),
            t('Remplaçants') => array_filter($rows, fn ($r) => $r['position'] === 'R'),
            t('Entraîneur') => array_filter($rows, fn ($r) => $r['position'] === 'E'),
            t('Autres') => array_filter($rows, fn ($r) => !in_array($r['position'], ['G', 'D', 'M', 'A', 'R', 'E'], true)),
        ];
        $hasNum = (bool) array_filter($rows, fn ($r) => ($r['number'] ?? null) !== null && $r['number'] !== '');
        $out = [];
        foreach ($groups as $label => $list) {
            if (!$list) {
                continue;
            }
            $row = [['t' => mb_strtoupper($label), 'b' => true, 'c' => 'navy']];
            $row = array_merge($row, array_fill(0, $hasNum ? 5 : 4, ''));
            $row['_bg'] = 'sand';
            $out[] = $row;
            foreach ($list as $r) {
                $cards = self::marks((string) ($r['cards_text'] ?? ''));
                if ($cards === '' && ($r['yellow'] || $r['red'])) {
                    $cards = trim(($r['yellow'] ? 'J ' . implode("', ", $r['yellow']) . "'" : '') . ' ' . ($r['red'] ? 'R ' . implode("', ", $r['red']) . "'" : ''));
                }
                $cells = [['t' => (string) $r['position'], 'b' => true, 'c' => 'blue']];
                if ($hasNum) {
                    $cells[] = (string) ($r['number'] ?? '');
                }
                $cells[] = ['t' => (string) $r['name'] . (!empty($r['captain']) ? ' (c)' : ''), 'u' => self::abs($r['href'] ?? null), 'b' => !empty($r['goals_text'])];
                $cells[] = self::marks((string) ($r['goals_text'] ?? ''));
                $cells[] = self::marks((string) ($r['sub_text'] ?? ''));
                $cells[] = $cards;
                $out[] = $cells;
            }
        }
        $headers = [t('Poste')];
        if ($hasNum) {
            $headers[] = t('N°');
        }
        array_push($headers, t('Joueur'), t('Buts'), t('Changements'), t('Cartons'));
        $l->table($headers, $out, ['align' => $hasNum ? ['c', 'c', 'l', 'l', 'l', 'l'] : ['c', 'l', 'l', 'l', 'l'], 'size' => 8.4, 'zebra' => false]);
    }

    private static function buildPerson(array $doc): string
    {
        $v = Fiche::personData($doc)['vars'];
        $p = $v['p'];
        $name = $p['display_name'] ?: $doc['title'];
        $first = trim((string) ($p['first_name'] ?? ''));
        $last = trim((string) ($p['last_name'] ?? ''));
        if ($first === '' && $last === '') {
            $parts = preg_split('/\s+/u', $name) ?: [$name];
            $last = (string) array_pop($parts);
            $first = implode(' ', $parts);
        }
        $years = (string) $v['years'];
        $role = (string) $v['roleLabel'];
        $isPlayer = in_array('joueur', $p['roles'] ?: ['joueur'], true);
        $kind = $isPlayer ? t('Fiche joueur') : match ($p['roles'][0] ?? '') {
            'entraineur' => t('Fiche entraîneur'), 'dirigeant' => t('Fiche dirigeant'), default => t('Fiche portrait'),
        };
        $l = self::layout($doc['path'], $name . ($years ? ' · ' . $years : ''), $name, $kind . ' · ' . $role);
        $l->newPage();
        $l->masthead($kind);

        // Photo à droite du titre, comme une carte de collection
        $img = self::photo($l, $doc['featured_image'] ?? null, 800);
        $top = $l->y;
        $photoBottom = $top;
        if ($img) {
            $pw = 150;
            $ph = 196;
            $px = Layout::W - $l->mr - $pw;
            $l->rect($px + 6, $top + 6, $pw, $ph, 'navy');
            $l->rect($px - 5, $top - 5, $pw + 10, $ph + 10, 'yellow', 'navy', 1.2);
            $l->drawImage($img, $px, $top, $pw, $ph, true, 0.2);
            $l->rect($px, $top, $pw, $ph, null, 'navy', 1);
            $photoBottom = $top + $ph + 18;
            $l->mr += $pw + 22;
        }
        self::titleBlock($l, implode(' · ', array_filter([t('Nos Lions'), $role, $years])), $last ?: $name, '', $first);
        if (!empty($p['nickname'])) {
            $l->para([Layout::run('« ' . $p['nickname'] . ' »', 'serif-i', 13, 'blue')], ['after' => 10]);
        }
        $big = array_values(array_filter($v['big'], fn ($b) => $b['v'] !== '' && $b['v'] !== '–' && $b['v'] !== '0'));
        if (count($big) >= 2) {
            self::bigNumbers($l, array_map(fn ($b, $i) => [(string) $b['v'], (string) $b['k'], $i === 0], $big, array_keys($big)));
        }
        $lead = trim((string) ($doc['intro'] ?? ''));
        if ($lead !== '') {
            self::flow($l, 11.5)->render($lead);
        } elseif ((string) ($p['subtitle'] ?? '') !== '') {
            $l->para([Layout::run((string) $p['subtitle'], 'serif-i', 11.5, 'navy')], ['after' => 10]);
        } elseif ((string) ($p['birth']['text'] ?? '') !== '') {
            $l->para([Layout::run(sentence(ucfirst((string) $p['birth']['text'])), 'serif-i', 11.5, 'navy')], ['after' => 10]);
        }
        if ($img) {
            $l->mr -= 150 + 22;
            $l->y = max($l->y, $photoBottom);
        }

        // Fiche d'identité (libellés d'origine)
        $idRows = [];
        foreach (Fiche::idRows($p, $name) as $r) {
            if ($r['sub']) {
                $idRows[] = [['t' => mb_strtoupper($r['value']), 'b' => true, 'c' => 'navy'], '', '_bg' => 'sand'];
            } elseif ($r['label'] !== '') {
                $idRows[] = [['t' => $r['label'], 'b' => true, 'c' => 'muted'], $r['value']];
            } else {
                $idRows[] = ['', ['t' => $r['value'], 'c' => 'ink']];
            }
        }
        if ($idRows) {
            $l->h2(t("Fiche d'identité"));
            $l->table([], $idRows, ['widths' => [0.34, 0.66], 'size' => 9.4]);
        }
        if (!empty($doc['key_figure']['number']) || !empty($doc['key_figure']['text'])) {
            $l->keyFigure((string) ($doc['key_figure']['number'] ?? ''), (string) ($doc['key_figure']['text'] ?? ''));
        }
        if (!empty($p['honours'])) {
            $l->h2(t('Palmarès'));
            $l->bullets(array_map(fn ($h) => [Layout::run((string) $h)], $p['honours']));
        }
        if (!empty($p['then'])) {
            $l->h2(t('Après Sochaux'));
            $l->bullets(array_map(fn ($h) => [Layout::run((string) $h)], $p['then']));
        }
        foreach ($doc['sections'] ?? [] as $s) {
            if (trim(strip_tags((string) $s['html'], '<img>')) === '') {
                continue;
            }
            $l->h2(trim((string) ($s['title'] ?? '')) !== '' ? (string) $s['title'] : t('Portrait'));
            self::flow($l)->render((string) $s['html']);
        }
        $sl = $v['statsLong'];
        if ($sl && $sl['rows']) {
            $title = $sl['raw']['title'] && !preg_match('/^statistiques?$/iu', (string) $sl['raw']['title']) ? (string) $sl['raw']['title'] : t('Saison par saison');
            $l->h2($title);
            $rows = [];
            $lastSeason = null;
            foreach ($sl['rows'] as $r) {
                $row = [['t' => $r['season'] !== $lastSeason ? $r['season'] : '', 'b' => true], (string) $r['comp'], (string) $r['mj'], (string) $r['g']];
                $lastSeason = $r['season'];
                if ($r['total']) {
                    $row['_bg'] = 'butter';
                    $row[0]['b'] = true;
                }
                $rows[] = $row;
            }
            $l->table([t('Saison'), t('Compétition'), t('MJ'), t('Buts')], $rows, ['align' => ['l', 'l', 'r', 'r'], 'size' => 8.6, 'zebra' => false]);
            $l->para([Layout::run(t('MJ : matchs joués.'), 'serif-i', 8.5, 'muted')], ['after' => 8]);
        }
        if ($v['highlights']) {
            $l->h2(t('Ses matchs marquants'));
            $items = [];
            foreach ($v['highlights'] as $h) {
                $x = $h['m'] ?? null;
                $items[] = $x
                    ? [Layout::run(mb_strtoupper((string) $h['label']), 'display-b', 8.6, 'blue', null, 0.6, 7), Layout::run(Site::matchLabel($x), 'serif-b', 10.5, 'navy', self::abs(url($x['path']))), Layout::run(' · ' . ($x['label'] ?: $x['comp']) . ' · ' . date_num($x['date']), 'serif', 10, 'muted')]
                    : [Layout::run(mb_strtoupper((string) $h['label']), 'display-b', 8.6, 'blue', null, 0.6, 7), Layout::run((string) ($h['text'] ?? ''))];
            }
            $l->bullets($items);
        }
        $tot = $v['tot'];
        foreach (['joueur' => $v['playerMatches'], 'entraineur' => $v['coachMatches']] as $k => $list) {
            if (!$list) {
                continue;
            }
            $coach = $k === 'entraineur';
            $l->h2($coach ? t('Ses matchs sur le banc') : t('Tous ses matchs'));
            if (!$coach && $tot) {
                self::bigNumbers($l, array_values(array_filter([
                    $tot['matches'] ? [(string) $tot['matches'], t('matchs'), true] : null,
                    $tot['matches'] ? [(string) $tot['goals'], t('buts')] : null,
                    !empty($tot['minutes']) ? [number_format((int) $tot['minutes'], 0, ',', ' '), t('minutes')] : null,
                    !empty($tot['yellow']) ? [(string) $tot['yellow'], t('jaunes')] : null,
                    !empty($tot['red']) ? [(string) $tot['red'], t('rouges')] : null,
                ])));
            }
            $rows = [];
            foreach ($list as $x) {
                $label = trim($x['home'] . ' – ' . $x['away'], ' –') ?: (string) ($x['event'] ?? $x['title']);
                $row = [date_num($x['date']), ['t' => $label, 'u' => self::abs(url($x['path']))], comp_round($x['label'] ?: $x['comp'], $x['round'] ?? null), ['t' => self::score($x), 'b' => true], self::resultCell($x['result'] ?? null)];
                if (!$coach) {
                    $row[] = $x['goals'] ? ['t' => (string) $x['goals'], 'b' => true] : '';
                    $row[] = trim(($x['minutes'] ? $x['minutes'] . "'" : '') . (($x['pos'] ?? '') === 'R' ? ' (' . t('entré') . ')' : '') . (!empty($x['captain']) ? ' (c)' : ''));
                }
                $rows[] = $row;
            }
            $headers = [t('Date'), t('Match'), t('Compétition'), t('Score'), t('Rés.')];
            if (!$coach) {
                array_push($headers, t('Buts'), t('Min.'));
            }
            $l->table($headers, $rows, ['align' => $coach ? ['l', 'l', 'l', 'c', 'c'] : ['l', 'l', 'l', 'c', 'c', 'c', 'l'], 'size' => 7.8]);
        }
        self::extras($l, $doc, t('Galerie'));
        return $l->finish();
    }

    private static function buildArticle(array $doc): string
    {
        $v = Fiche::articleData($doc)['vars'];
        $kind = match (true) {
            $doc['type'] === 'objet' => t('Objet des réserves'),
            $doc['type'] === 'moment' => t('100 ans, 100 moments'),
            $doc['type'] === 'page' => t('Page'),
            ($doc['article']['kind'] ?? '') === 'bilan_saison' => t('Bilan de saison'),
            default => t('Article'),
        };
        $kicker = (string) ($v['kicker'] ?? '');
        $l = self::layout($doc['path'], (string) $doc['title'], (string) $doc['title'], $kind . ($kicker !== '' ? ' · ' . $kicker : ''));
        $l->newPage();
        $l->masthead($kind);
        $sub = trim((string) ($doc['article']['subtitle'] ?? ''));
        $heading = trim((string) ($doc['article']['heading'] ?? ''));
        if ($heading !== '' && mb_strtolower($heading) !== mb_strtolower((string) $doc['title']) && mb_strtolower($heading) !== mb_strtolower($kicker)) {
            $sub = trim($heading . ($sub !== '' ? ' · ' . $sub : ''));
        }
        self::titleBlock($l, $kicker, (string) $doc['title'], $sub);
        if ($doc['type'] === 'moment' && !empty($doc['moment'])) {
            $l->facts([[t('Moment'), !empty($doc['moment']['number']) ? 'N° ' . $doc['moment']['number'] . ' / 100' : ''], [t('Année'), (string) ($doc['moment']['year'] ?? '')]], 2);
        }
        if ($doc['type'] === 'objet' && !empty($doc['objet'])) {
            $o = $doc['objet'];
            $cols = Pages::reserves();
            $colName = '';
            foreach ($cols as $c) {
                if (($c['slug'] ?? null) === ($o['collection'] ?? null)) {
                    $colName = (string) ($c['name'] ?? '');
                }
            }
            $l->facts([[t('Collection'), $colName ?: (string) ($o['collection'] ?? '')], [t('Date'), trim((string) ($o['date_text'] ?? '')) ?: (string) ($o['year'] ?? '')], [t('Provenance'), (string) ($o['origin'] ?? '')], [t('Crédit'), (string) ($o['credit'] ?? '')]], 2);
        }
        $img = self::photo($l, $doc['featured_image'] ?? null);
        if ($img) {
            $l->figure($img, '', ['h' => 270]);
        }
        $header = (string) ($doc['article']['header_html'] ?? '');
        if (($doc['article']['kind'] ?? '') === 'bilan_saison') {
            if ($header === '') {
                $header = (string) preg_replace('#^\s*<h[1-6][^>]*>.*?</h[1-6]>#is', '', (string) ($doc['legacy']['header_html'] ?? ''), 1);
            }
            if (trim(strip_tags($header)) !== '') {
                $l->h2(t('La saison en chiffres'));
                self::flow($l)->render($header);
            }
        }
        if (trim((string) ($doc['intro'] ?? '')) !== '') {
            self::flow($l, 12)->render((string) $doc['intro']);
        }
        if (!empty($doc['key_figure']['number']) || !empty($doc['key_figure']['text'])) {
            $l->keyFigure((string) ($doc['key_figure']['number'] ?? ''), (string) ($doc['key_figure']['text'] ?? ''));
        }
        foreach ($doc['sections'] ?? [] as $s) {
            $html = trim((string) $s['html']);
            if ($html === '' && empty($s['title'])) {
                continue;
            }
            if (!empty($s['title'])) {
                $l->h2((string) $s['title']);
            }
            self::flow($l)->render($html);
        }
        foreach ($doc['images'] ?? [] as $im) {
            $img = self::photo($l, $im['image'] ?? null);
            if ($img) {
                $l->figure($img, trim(($im['caption'] ?? '') . (!empty($im['credit']) ? ' – ' . $im['credit'] : ''), ' –'), ['h' => 280]);
            }
        }
        $linked = array_merge($doc['moment']['linked'] ?? [], $doc['objet']['linked'] ?? []);
        $items = [];
        foreach (array_unique(array_map('intval', $linked)) as $id) {
            $s = Index::get($id);
            if ($s && Index::visible($s)) {
                $items[] = [Layout::run(Pages::shortTitle($s), 'serif-b', 10.5, 'navy', self::abs(url($s['path']))), Layout::run(' · ' . Pages::kindLabel($s), 'serif', 10, 'muted')];
            }
        }
        if ($items) {
            $l->h2(t('Fiches liées'));
            $l->bullets($items);
        }
        if (!empty($v['seasonLink'])) {
            $l->para([Layout::run(t('Tous les matchs de la saison') . ' : ', 'serif-b', 10, 'navy'), Layout::run(base_url() . $v['seasonLink'], 'serif', 10, 'blue', base_url() . $v['seasonLink'])], ['after' => 8]);
        }
        self::extras($l, $doc, t('Galerie'));
        return $l->finish();
    }

    // ================================================================== pages de synthèse

    private static function buildSeason(string $season, array $v): string
    {
        $label = substr($season, 0, 4) . '–' . substr($season, 7, 2);
        $l = self::layout('/matchs/' . $season . '/', t('Saison') . ' ' . $season, t('Saison') . ' ' . $season, t('Résultats, effectif et buteurs'));
        $l->newPage();
        $l->masthead(t('Saison'));
        $division = '';
        foreach ($v['sums'] as $s) {
            if (!empty($s['title'])) {
                $division = (string) $s['title'];
            }
        }
        self::titleBlock($l, 'FC Sochaux-Montbéliard', t('Saison') . ' ' . $label, $division);
        self::bigNumbers($l, array_map(fn ($s) => [(string) $s['v'], (string) $s['k'], !empty($s['yellow'])], array_values(array_filter($v['sums'], fn ($s) => empty($s['title'])))));
        if ($v['coaches']) {
            $runs = [Layout::run(mb_strtoupper(count($v['coaches']) > 1 ? t('Entraîneurs') : t('Entraîneur')), 'display-b', 8.6, 'muted', null, 0.9, 8)];
            foreach ($v['coaches'] as $i => $c) {
                $runs[] = Layout::run(($i ? ', ' : '') . $c['name'], 'serif-b', 10.5, 'navy', self::abs($c['href']));
                $runs[] = Layout::run(' (' . $c['n'] . ' ' . t('matchs') . ')', 'serif', 10, 'muted');
            }
            $l->para($runs, ['after' => 10]);
        }
        if ($v['bilan']) {
            $b = Fiches::get((int) $v['bilan']['id']);
            if ($b) {
                $b = Fiche::localizeDoc($b);
                $l->h2(t('Bilan de la saison'));
                if (trim((string) ($b['intro'] ?? '')) !== '') {
                    self::flow($l, 11)->render((string) $b['intro']);
                }
                foreach ($b['sections'] ?? [] as $s) {
                    if (trim(strip_tags((string) $s['html'])) === '') {
                        continue;
                    }
                    if (!empty($s['title'])) {
                        $l->h3((string) $s['title']);
                    }
                    self::flow($l)->render((string) $s['html']);
                }
            }
        }
        if ($v['matches']) {
            $l->h2(t('Les résultats'));
            self::matchTable($l, $v['matches']);
        }
        if ($v['scorers']) {
            $l->h2(t('Les buteurs'));
            $l->table([t('Joueur'), t('Buts')], array_map(fn ($s) => [['t' => $s['name'], 'u' => self::abs($s['href']), 'b' => true], (string) $s['g']], $v['scorers']), ['align' => ['l', 'r'], 'widths' => [0.8, 0.2]]);
        }
        if ($v['squad']) {
            $l->h2(t("L'effectif"));
            $lines = ['G' => t('Gardien'), 'D' => t('Défenseur'), 'M' => t('Milieu'), 'A' => t('Attaquant')];
            $l->table([t('Joueur'), t('Poste'), t('Matchs'), t('Buts')], array_map(fn ($p) => [['t' => $p['name'], 'u' => self::abs($p['href'])], $lines[$p['line']] ?? '', (string) $p['mj'], $p['goals'] ? (string) $p['goals'] : ''], $v['squad']), ['align' => ['l', 'l', 'r', 'r'], 'widths' => [0.5, 0.24, 0.13, 0.13]]);
            $l->para([Layout::run(t('Matchs joués et buts calculés depuis les compositions des fiches matchs du musée.'), 'serif-i', 8.5, 'muted')]);
        }
        return $l->finish();
    }

    /** Livre des records : les 25 premiers de chaque classement (matchs officiels). */
    private static function buildRecords(): string
    {
        $title = t('Le livre des records');
        $l = self::layout('/records/', $title, $title, t('Les records du FC Sochaux-Montbéliard'));
        $l->newPage();
        $l->masthead(t('Records'));
        self::titleBlock($l, t('Les records · calculés automatiquement'), $title, t('Calculés depuis les fiches matchs du musée (matchs officiels, hors matchs amicaux).'));
        foreach (Explore::RECORDS as $k => [, $heading, $unit]) {
            $rows = Explore::recordRows($k, null, null, 25);
            if (!$rows) {
                continue;
            }
            $l->h2(t($heading));
            $out = [];
            foreach ($rows as $i => $r) {
                $out[] = [
                    ['t' => (string) ($i + 1), 'b' => true, 'c' => $i < 3 ? 'blue' : 'muted'],
                    ['t' => (string) $r['name'], 'u' => self::abs($r['href'] ?? null), 'b' => $i < 3],
                    (string) ($r['meta'] ?? ''),
                    ['t' => (string) $r['v'], 'b' => true],
                ];
            }
            $l->table(['#', t('Nom'), t('Détails'), t($unit)], $out, ['align' => ['c', 'l', 'l', 'r'], 'widths' => [0.07, 0.37, 0.4, 0.16]]);
        }
        return $l->finish();
    }

    /** Face-à-face ou bilan (compétition, stade) : totaux, faits marquants, parcours, liste des matchs. */
    private static function buildH2h(array $v, string $kind, string $title, string $pdfUrl, string $pagePath): string
    {
        $t = $v['t'];
        $l = self::layout($pagePath, $title, $title, $kind);
        $l->newPage();
        $l->masthead($kind);
        self::titleBlock($l, (string) $v['eyebrow'], $title, t('{n} matchs fichés dans le musée', ['n' => $t['count']]));
        self::bigNumbers($l, [[(string) $t['count'], t('matchs')], [(string) $t['V'], t('victoires'), true], [(string) $t['N'], t('nuls')], [(string) $t['D'], t('défaites')], [$t['gf'] . '-' . $t['ga'], t('buts pour / contre')]]);
        // Répartition victoires / nuls / défaites
        if ($t['V'] + $t['N'] + $t['D'] > 0) {
            $l->ensure(40);
            $x = $l->ml;
            $w = $l->cw();
            foreach ([['pV', 'green', t('victoires')], ['pN', 'muted', t('nuls')], ['pD', 'red', t('défaites')]] as [$k, $c, $lab]) {
                $sw = $w * (float) $t[$k] / 100;
                if ($sw <= 0) {
                    continue;
                }
                $l->rect($x, $l->y, $sw, 14, $c);
                $txt = round((float) $t[$k]) . ' % ' . $lab;
                if ($l->width($txt, 'display-b', 7.6, 0.5) < $sw - 8) {
                    $l->text($x + 5, $l->y + 10, $txt, 'display-b', 7.6, 'white', 0.5);
                }
                $x += $sw;
            }
            $l->y += 26;
        }
        if ($v['highlights']) {
            $l->h2(t('Les faits marquants'));
            $l->table([], array_map(fn ($h) => [['t' => mb_strtoupper((string) $h['k']), 'b' => true, 'c' => 'blue'], ['t' => (string) $h['score'], 'b' => true, 'c' => 'navy'], ['t' => (string) $h['desc'], 'u' => self::abs($h['href'])]], $v['highlights']), ['widths' => [0.28, 0.14, 0.58], 'size' => 9.2]);
        }
        if (!empty($v['groups'])) {
            $by = ($v['groupBy'] ?? 'season') === 'decade';
            $l->h2($by ? t('Décennie par décennie') : t('Saison par saison'));
            $rows = [];
            foreach ($v['groups'] as $g) {
                $last = $g['last'] ?? null;
                $rows[] = [
                    ['t' => $by ? decade_label((int) $g['key']) : (string) $g['key'], 'b' => true],
                    (string) $g['n'], (string) $g['V'], (string) $g['N'], (string) $g['D'], $g['gf'] . '-' . $g['ga'],
                    $last ? ['t' => comp_round($last['label'] ?: $last['comp'], $last['round'] ?? null) . ' · ' . Site::matchLabel($last), 'u' => self::abs(url($last['path']))] : '',
                ];
            }
            $l->table([$by ? t('Décennie') : t('Saison'), t('M'), t('V'), t('N'), t('D'), t('Buts'), $by ? t('Dernier match') : t('Dernier tour')], $rows, ['align' => ['l', 'r', 'r', 'r', 'r', 'c', 'l'], 'size' => 8]);
        }
        if ($v['list']) {
            $l->h2(t('Tous les matchs'));
            self::matchTable($l, $v['list']);
        }
        return $l->finish();
    }
}
