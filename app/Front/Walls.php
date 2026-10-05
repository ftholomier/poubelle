<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Data\Index;
use App\Services\I18n;
use App\Services\PhotoWall;

/**
 * Les quatre murs de photos de la rubrique Interactif : un nouveau tirage au hasard à chaque
 * visite (et sans recharger la page : « Nouveau tirage »), filtres par décennie et par
 * photographe, crédit toujours affiché, agrandissement avec lien vers la fiche.
 */
final class Walls
{
    /** Clé => [adresse, nom, phrase du pavé, bouton du nouveau tirage, icône] (textes traduits à l'affichage). */
    public const WALLS = [
        'planche' => ['/interactif/planche-contact/', 'Planche-contact', 'Le film des archives, image par image, avec la loupe du labo.', 'Nouvelle planche', '35'],
        'journal' => ['/interactif/le-lion-illustre/', 'Le Lion illustré', 'Le journal des photos du musée : une nouvelle édition à chaque visite.', 'Édition suivante', 'N°'],
        'vestiaire' => ['/interactif/mur-du-vestiaire/', 'Le mur du vestiaire', 'Des tirages punaisés au carrelage, à déplacer à la main.', 'Nouveau mur', '▢'],
        'mosaique' => ['/interactif/mosaique/', 'La grande mosaïque', 'Des centaines de photos qui dessinent un motif du centenaire.', 'Nouvelle mosaïque', '▩'],
    ];

    /** Pavés de la rubrique Interactif (accueil et méga-menu). @return list<array> */
    public static function tools(): array
    {
        return array_map(fn ($w) => ['icon' => $w[4], 'label' => t($w[1]), 'd' => t($w[2]), 'href' => url($w[0])], array_values(self::WALLS));
    }

    public static function contactSheet(Request $req): Response
    {
        $photos = PhotoWall::draw(36, self::filters($req) + ['landscape' => true, 'width' => 480]);
        return self::page($req, 'planche', [
            'photos' => array_map([self::class, 'view'], $photos),
            'sheet' => random_int(120, 9999),
            'marks' => self::marks(count($photos)),
        ], t('Les archives photo du musée tirées comme une planche-contact de photographe : bandes de film, crédits imprimés dans la marge, loupe et images entourées au crayon gras.'));
    }

    public static function newspaper(Request $req): Response
    {
        $f = self::filters($req);
        $lead = PhotoWall::draw(1, $f + ['landscape' => true, 'minWidth' => 1000]) ?: PhotoWall::draw(1, $f + ['landscape' => true]);
        $taken = array_flip(array_column($lead, 'r'));
        $rest = array_values(array_filter(PhotoWall::draw(16, $f + ['width' => 480]), fn ($p) => !isset($taken[$p['r']])));
        $photos = array_map([self::class, 'view'], array_merge($lead, $rest));
        return self::page($req, 'journal', [
            'lead' => $photos[0] ?? null,
            'side' => array_slice($photos, 1, 2),
            'row' => array_slice($photos, 3, 4),
            'briefs' => array_slice($photos, 7, 6),
            'number' => random_int(1000, 9999),
            'special' => self::special($f),
        ], t('Le Lion illustré, journal des photos du musée Sochaux Rétro : une Une, des photos légendées et créditées, une nouvelle édition à chaque visite.'));
    }

    public static function lockerRoom(Request $req): Response
    {
        $photos = PhotoWall::draw(24, self::filters($req) + ['width' => 480]);
        $prints = [];
        foreach ($photos as $i => $p) {
            $prints[] = self::view($p) + [
                'rot' => random_int(-70, 70) / 10,
                'x' => random_int(-14, 14),
                'y' => random_int(-12, 12),
                'hold' => random_int(0, 2) === 0 ? 'pin' : 'tape',
                'tape' => random_int(-12, 12),
                'z' => random_int(1, 9),
            ];
        }
        return self::page($req, 'vestiaire', ['prints' => $prints],
            t('Le mur du vestiaire : des photos du musée punaisées et scotchées comme dans un vestiaire, à déplacer à la souris. Un nouveau mur à chaque visite.'));
    }

    public static function mosaic(Request $req): Response
    {
        $keys = array_map('strval', array_keys(self::MOTIFS)); // « 1928 » : clé numérique pour PHP
        $motif = $req->str('motif');
        $motif = in_array($motif, $keys, true) ? $motif : $keys[random_int(0, count($keys) - 1)];
        $wide = self::grid($motif, true);
        $narrow = self::grid($motif, false);
        $n = count($wide) * count($wide[0]);
        // Vignettes déjà prêtes seulement : des centaines à fabriquer d'un coup chargeraient le
        // serveur. Tant qu'il y en a trop peu (installation neuve), 40 photos au plus, répétées.
        $photos = PhotoWall::draw($n, self::filters($req) + ['width' => 160, 'ready' => true]);
        if (count($photos) < 40) {
            $photos = PhotoWall::draw(40, self::filters($req) + ['width' => 160]);
        }
        // Pas assez de photos pour ce filtre : elles reviennent plusieurs fois (les cases sont petites).
        $list = $photos;
        while ($photos && count($list) < $n) {
            $list = array_merge($list, $photos);
        }
        $views = array_map([self::class, 'view'], array_slice($list, 0, $n));
        $credits = [];
        foreach (array_slice($photos, 0, $n) as $p) {
            $credits[$p['who']] = ($credits[$p['who']] ?? 0) + 1;
        }
        arsort($credits);
        return self::page($req, 'mosaique', ['wide' => $wide, 'narrow' => $narrow, 'photos' => $views, 'motif' => $motif, 'motifs' => array_combine($keys, array_map('t', array_values(self::MOTIFS))), 'credits' => $credits],
            t('La grande mosaïque : des centaines de photos du musée qui dessinent ensemble un motif du centenaire. Survolez une case pour voir la photo.'));
    }

    // ------------------------------------------------------------------ motifs de la mosaïque

    /** Motif => légende. */
    public const MOTIFS = ['100' => '100 ans du FCSM', 'fcsm' => 'FCSM', '1928' => '1928, l’année de la fondation', '2028' => '2028, l’année du centenaire'];
    /** Texte de chaque motif : sur une ligne pour les grands écrans, sur deux pour les téléphones. */
    private const MOTIF_TEXT = ['100' => [['100'], ['100']], 'fcsm' => [['FCSM'], ['FC', 'SM']], '1928' => [['1928'], ['19', '28']], '2028' => [['2028'], ['20', '28']]];
    /** Chiffres et lettres en 5 × 7 cases (grands écrans). */
    private const FONT_WIDE = [
        '0' => ['.###.', '#...#', '#..##', '#.#.#', '##..#', '#...#', '.###.'], '1' => ['..#..', '.##..', '..#..', '..#..', '..#..', '..#..', '.###.'],
        '2' => ['.###.', '#...#', '....#', '...#.', '..#..', '.#...', '#####'], '8' => ['.###.', '#...#', '#...#', '.###.', '#...#', '#...#', '.###.'],
        '9' => ['.###.', '#...#', '#...#', '.####', '....#', '...#.', '.##..'], 'C' => ['.###.', '#...#', '#....', '#....', '#....', '#...#', '.###.'],
        'F' => ['#####', '#....', '#....', '####.', '#....', '#....', '#....'], 'S' => ['.####', '#....', '#....', '.###.', '....#', '....#', '####.'],
        'M' => ['#...#', '##.##', '#.#.#', '#.#.#', '#...#', '#...#', '#...#'],
    ];
    /** Les mêmes en 3 × 5 cases (téléphones). */
    private const FONT_NARROW = [
        '0' => ['###', '#.#', '#.#', '#.#', '###'], '1' => ['.#.', '##.', '.#.', '.#.', '###'], '2' => ['###', '..#', '###', '#..', '###'],
        '8' => ['###', '#.#', '###', '#.#', '###'], '9' => ['###', '#.#', '###', '..#', '###'], 'C' => ['###', '#..', '#..', '#..', '###'],
        'F' => ['###', '#..', '##.', '#..', '#..'], 'S' => ['###', '#..', '###', '..#', '###'], 'M' => ['#.#', '###', '###', '#.#', '#.#'],
    ];

    /**
     * Grille d'un motif : 24 × 11 cases sur grand écran, 12 de large sur téléphone ; vrai = case
     * du dessin (photo teintée de jaune), faux = fond (teinté de bleu).
     * @return list<list<bool>>
     */
    public static function grid(string $motif, bool $wide): array
    {
        $font = $wide ? self::FONT_WIDE : self::FONT_NARROW;
        [$cw, $ch] = $wide ? [5, 7] : [3, 5];
        $lines = self::MOTIF_TEXT[$motif][$wide ? 0 : 1] ?? ['100'];
        $cols = $wide ? 24 : 12;
        $h = count($lines) * $ch + count($lines) - 1;
        $rows = $wide ? 11 : $h + (count($lines) > 1 ? 2 : 4);
        $grid = array_fill(0, $rows, array_fill(0, $cols, false));
        $top = intdiv($rows - $h, 2);
        foreach ($lines as $l => $line) {
            $chars = str_split($line);
            $w = count($chars) * $cw + count($chars) - 1;
            $left = intdiv($cols - $w, 2);
            foreach ($chars as $c => $char) {
                foreach ($font[$char] ?? [] as $y => $bits) {
                    foreach (str_split($bits) as $x => $bit) {
                        if ($bit === '#') {
                            $grid[$top + $l * ($ch + 1) + $y][$left + $c * ($cw + 1) + $x] = true;
                        }
                    }
                }
            }
        }
        return $grid;
    }

    // ------------------------------------------------------------------ commun

    /** Filtres demandés, gardés seulement s'ils existent. @return array{decade:?int,who:?string} */
    public static function filters(Request $req): array
    {
        $d = (int) $req->str('decennie');
        $who = $req->str('photographe');
        return [
            'decade' => isset(PhotoWall::decades()[$d]) ? $d : null,
            'who' => in_array($who, array_column(PhotoWall::photographers(), 'key'), true) ? $who : null,
        ];
    }

    /** Ce qu'un gabarit affiche d'une photo (vignettes, légende dans la langue, crédit, fiche). */
    public static function view(array $p): array
    {
        $s = $p['f'] ? Index::get((int) $p['f']) : null;
        $en = I18n::isEn();
        $caption = $en && $p['cap_en'] !== '' ? $p['cap_en'] : $p['cap'];
        $alt = ($en && $p['alt_en'] !== '' ? $p['alt_en'] : $p['alt']) ?: $caption ?: ($s['title'] ?? '');
        return [
            'rel' => $p['r'], 'w' => $p['w'], 'h' => $p['h'],
            'src160' => img($p['r'], 160), 'src480' => img($p['r'], 480), 'src800' => img($p['r'], 800), 'src1200' => img($p['r'], 1200), 'full' => img($p['r'], 1600),
            'caption' => $caption, 'alt' => $alt, 'credit' => $p['c'], 'who' => $p['who'], 'year' => $p['y'],
            'title' => (string) ($s['title'] ?? ''),
            'href' => $s ? url($s['path']) : '',
            'type' => $s['type'] ?? '',
            'date' => $s['m']['date'] ?? null,
        ];
    }

    /** Attributs qui ouvrent une photo en grand (légende, crédit, lien vers sa fiche). */
    public static function attrs(array $v, int $i): string
    {
        return 'data-photo="' . $i . '" data-full="' . e($v['full']) . '" data-caption="' . e($v['caption']) . '" data-credit="' . e($v['credit'])
            . '" data-href="' . e($v['href']) . '" data-title="' . e($v['title']) . '"';
    }

    /** Titre d'« édition spéciale » du journal quand un filtre est choisi. */
    private static function special(array $f): string
    {
        $parts = [];
        if ($f['decade']) {
            $parts[] = sprintf(t('les années %d'), $f['decade']);
        }
        if ($f['who']) {
            $parts[] = (string) (array_column(PhotoWall::photographers(), 'name', 'key')[$f['who']] ?? '');
        }
        return implode(' · ', array_filter($parts));
    }

    /**
     * Traits de crayon gras autour de quelques images : au plus un par bande de six, comme le
     * photographe qui marque la meilleure vue de chaque bande (pas toutes les bandes), avec un mot
     * griffonné à côté (« la bonne ! »).
     * @return array<int,array{d:string,note:string}> rang de l'image => tracé SVG (repère 120 × 90) et mot
     */
    private static function marks(int $n): array
    {
        $notes = ['la bonne !', 'celle-là !', 'à tirer !', 'top !'];
        $out = [];
        for ($s = 0; $s < $n; $s += 6) {
            if (random_int(0, 9) >= 6) {
                continue;
            }
            $i = $s + random_int(0, min(5, $n - 1 - $s));
            $out[$i] = ['d' => self::loop(), 'note' => $notes[random_int(0, count($notes) - 1)]];
        }
        return $out;
    }

    /**
     * Boucle tracée à main levée : un peu plus d'un tour, rayon qui ondule doucement, fin qui ne
     * retombe pas sur le début (spirale légère) et repart vers l'extérieur ; courbe lissée.
     */
    public static function loop(): string
    {
        $rnd = fn (float $a, float $b): float => $a + ($b - $a) * random_int(0, 10000) / 10000;
        [$cx, $cy] = [60 + $rnd(-2, 2), 45 + $rnd(-1.5, 1.5)];
        [$rx, $ry] = [55 + $rnd(-2, 1.5), 40 + $rnd(-1.5, 1.5)];
        $start = $rnd(0, 2 * M_PI);
        $turn = 2 * M_PI * (1 + $rnd(.14, .32));
        [$p1, $p2] = [$rnd(0, 2 * M_PI), $rnd(0, 2 * M_PI)];
        $tilt = $rnd(-.07, .07);
        $drift = $rnd(.03, .07) * (random_int(0, 1) ? 1 : -1);
        $pts = [];
        $n = 40;
        for ($k = 0; $k <= $n; $k++) {
            $t = $k / $n;
            $a = $start + $turn * $t;
            $r = 1 + .03 * sin(2 * $a + $p1) + .018 * sin(3 * $a + $p2) + $drift * ($t - .5) + ($t > .9 ? .5 * ($t - .9) : 0);
            [$x, $y] = [$rx * $r * cos($a), $ry * $r * sin($a)];
            $pts[] = [$cx + $x * cos($tilt) - $y * sin($tilt), $cy + $x * sin($tilt) + $y * cos($tilt)];
        }
        // Catmull-Rom → courbes de Bézier : un trait souple, sans angles.
        $f = fn (float $v): string => (string) round($v, 1);
        $d = 'M' . $f($pts[0][0]) . ' ' . $f($pts[0][1]);
        for ($k = 0; $k < $n; $k++) {
            [$a, $b, $c, $e] = [$pts[max(0, $k - 1)], $pts[$k], $pts[$k + 1], $pts[min($n, $k + 2)]];
            $d .= ' C' . $f($b[0] + ($c[0] - $a[0]) / 6) . ' ' . $f($b[1] + ($c[1] - $a[1]) / 6)
                . ' ' . $f($c[0] - ($e[0] - $b[0]) / 6) . ' ' . $f($c[1] - ($e[1] - $b[1]) / 6)
                . ' ' . $f($c[0]) . ' ' . $f($c[1]);
        }
        return $d;
    }

    /** Page complète, ou seulement le mur pour « Nouveau tirage » et les filtres (sans recharger). */
    private static function page(Request $req, string $kind, array $vars, string $description): Response
    {
        $w = self::WALLS[$kind];
        $f = self::filters($req);
        $vars += ['kind' => $kind, 'filters' => $f];
        $counts = PhotoWall::counts($f['decade'], $f['who']);
        if ($req->str('partiel') === '1') {
            // Le mur, et les nombres de photos des filtres pour ce choix (mis à jour par murs.js).
            $res = Response::html(View::partial('interactif/murs/' . $kind, $vars)
                . '<script type="application/json" data-wall-counts>' . json_encode($counts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '</script>');
            $res->headers['Cache-Control'] = 'no-store';
            $res->headers['X-Robots-Tag'] = 'noindex';
            return $res;
        }
        return Pages::render('interactif/murs/page', $vars + [
            'wall' => $w,
            'decades' => PhotoWall::decades(),
            'photographers' => PhotoWall::photographers(),
            'counts' => $counts,
            'others' => array_filter(self::WALLS, fn ($k) => $k !== $kind, ARRAY_FILTER_USE_KEY),
            'count' => count(PhotoWall::photos()),
        ], [
            'title' => t($w[1]) . ' · ' . t('Les murs de photos'),
            'description' => $description,
            'active' => 'interactif',
            'body_class' => 'page-wall page-wall--' . $kind,
            'noindex' => $f['decade'] !== null || $f['who'] !== null,
            'styles' => ['css/mosaic.css', 'css/murs.css'],
            'scripts' => ['js/murs.js'],
        ]);
    }
}
