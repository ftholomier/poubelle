<?php
declare(strict_types=1);

namespace App\Pdf;

use App\Data\Fiches;
use App\Data\Media;
use App\Front\Recit;
use App\Services\GrandsRecits;
use App\Services\Images;

/**
 * Livre « 100 récits du Lion » : les grands récits dans l'ordre chronologique, mis en page pour
 * l'impression (21 × 27 cm, fonds perdus de 3 mm, TrimBox/BleedBox) et personnalisé (nom,
 * dédicace, numéro d'exemplaire).
 *
 * Règle des photos : une image n'est placée que si sa définition suffit à la taille où elle est
 * imprimée (pleine page et bandeau à fonds perdus : 250 dpi ; pleine largeur : 220 ; colonne : 200).
 * Sinon elle est essayée plus petite, ou écartée. Les images de presse ne sont jamais utilisées.
 * Les photos retenues sont recadrées et réduites à 300 dpi (cache storage/cache/livre).
 */
final class Livre
{
    /** Format fini et fonds perdus, en mm */
    public const TRIM_W = 210;
    public const TRIM_H = 270;
    public const BLEED = 3;
    /** Définition minimale (points par pouce à l'impression) selon la taille de la photo */
    public const DPI = ['page' => 250, 'bandeau' => 250, 'large' => 220, 'colonne' => 200];
    private const TARGET_DPI = 300;
    private const MM = 72 / 25.4;

    private Layout $l;
    private float $b;
    private float $W;
    private float $H;
    /** Zone de texte courante (deux colonnes) */
    private float $top;
    private float $bottomY;
    private int $col = 0;
    private float $y = 0;
    private string $runLeft = '';
    private string $runRight = '';
    /** @var list<array{t:string,page:int,level:int}> entrées du sommaire */
    private array $toc = [];
    /** Photos déjà placées dans le livre (nom normalisé et empreinte du fichier) : jamais deux fois la même. */
    private array $used = [];
    /** Part minimale de l'image gardée au recadrage. */
    private const KEEP = 0.62;
    private array $report = ['page' => 0, 'bandeau' => 0, 'large' => 0, 'colonne' => 0, 'ecartees' => 0, 'presse' => 0, 'recits' => 0, 'sans_photo' => []];
    private array $dims = [];
    private string $cache;

    /**
     * @param array{nom?:string,dedicace?:string,signature?:string,numero?:string,depuis?:int,couverture?:string,match?:int,joueurs?:list<int>,naissance?:string,naissance_titre?:string,carnet?:string,photo?:string,photo_legende?:string,maillot_nom?:string,maillot_numero?:string,maillot_style?:string,maillot_image?:string,maillot_devant?:string,qr?:bool,relire?:bool,decennies?:list<int>,limite?:int} $o
     */
    public function __construct(private array $o = [])
    {
        $this->cache = STORAGE_PATH . '/cache/livre';
        if (!is_dir($this->cache)) {
            @mkdir($this->cache, 0775, true);
        }
        $this->dims = json_decode((string) @file_get_contents($this->cache . '/dims.json'), true) ?: [];
    }

    /** Ce qu'a donné la dernière composition (photos par taille, écartées, récits sans photo). */
    public function report(): array
    {
        return $this->report;
    }

    /** Les récits du livre, dans l'ordre chronologique, regroupés par décennie. @return array<int,list<array>> */
    public function recits(): array
    {
        $out = [];
        $n = 0;
        foreach (GrandsRecits::all() as $r) {
            $id = GrandsRecits::existing($r['key']);
            $doc = $id ? Fiches::get($id) : null;
            if (!$doc || ($doc['status'] ?? '') === 'corbeille') {
                continue;
            }
            if (($doc['status'] ?? '') !== 'publie' && empty($this->o['relire'])) {
                continue;
            }
            $dec = (int) (floor((float) $r['year'] / 10) * 10);
            if (!empty($this->o['decennies']) && !in_array($dec, $this->o['decennies'], true)) {
                continue;
            }
            $out[$dec][] = ['n' => ++$n, 'doc' => $doc, 'year' => (float) $r['year']];
            if (!empty($this->o['apercu']) && $n >= 2) {
                break;
            }
            if (!empty($this->o['limite']) && $n >= (int) $this->o['limite']) {
                break;
            }
        }
        ksort($out);
        return $out;
    }

    public function build(): string
    {
        $this->l = $l = new Layout();
        $this->b = self::BLEED * self::MM;
        $this->W = self::TRIM_W * self::MM;
        $this->H = self::TRIM_H * self::MM;
        $l->pw = $this->W + 2 * $this->b;
        $l->ph = $this->H + 2 * $this->b;
        $groups = $this->recits();

        $this->cover($groups);
        $this->blank();
        $this->coverCredit();
        $this->dedication();
        $this->myPhoto((string) ($this->o['photo'] ?? ''));
        $this->myJersey();
        $this->birthMatch();
        $this->myMatch((int) ($this->o['match'] ?? 0), 'MON MATCH', '', isset($this->o['match_feuille']) ? self::fromSheet((array) $this->o['match_feuille']) : null);
        $this->myPlayers(array_slice(array_map('intval', (array) ($this->o['joueurs'] ?? [])), 0, 3));
        $this->seen = $this->carnetMatches();
        $tocFirst = $this->l->pdf->pageCount();
        $count = array_sum(array_map('count', $groups));
        $tocPages = max(1, (int) ceil(($count + 2 * count($groups)) / 40));
        for ($i = 0; $i < $tocPages; $i++) {
            $this->page();
        }
        if ($this->l->pdf->pageCount() % 2 === 1) {
            $this->blank();
        }
        foreach ($groups as $dec => $items) {
            $this->decade($dec, $items);
            foreach ($items as $it) {
                $this->recit($it, $dec);
            }
        }
        $this->tocDraw($tocFirst, $tocPages);
        $this->carnetPage();
        // Cahiers d'impression : un multiple de 4 pages, la 4e de couverture en dernier.
        while (($this->l->pdf->pageCount() + 1) % 4 !== 0) {
            $this->blank();
        }
        $this->back();
        $this->folios();
        @file_put_contents($this->cache . '/dims.json', json_encode($this->dims));
        return $l->pdf->output();
    }

    // ------------------------------------------------------------------ pages

    private function page(): int
    {
        $l = $this->l;
        $l->page = $l->pdf->addPage($l->pw, $l->ph);
        $b = $this->b;
        $l->pdf->boxes($l->page, [$b, $b, $b + $this->W, $b + $this->H], [0, 0, $l->pw, $l->ph]);
        $l->rect(0, 0, $l->pw, $l->ph, 'paper');
        $this->inText = false;
        return $l->page;
    }

    private function blank(): void
    {
        $this->page();
        $this->noFolio[$this->l->page] = true;
    }

    /** Page de droite (numéro impair) ? La couverture est la page 1. */
    private function right(int $page): bool
    {
        return $page % 2 === 0;
    }

    /** Marges du texte (mm) : intérieur plus large côté reliure. @return array{0:float,1:float} x gauche, largeur */
    private function frame(int $page): array
    {
        $inner = 22 * self::MM;
        $outer = 16 * self::MM;
        $x = $this->b + ($this->right($page) ? $inner : $outer);
        return [$x, $this->W - $inner - $outer];
    }

    private array $noFolio = [];
    /** Matchs du carnet du lecteur (id => date d'ajout) et récits tamponnés « J'y étais » */
    private array $seen = [];
    private array $stamped = [];
    /** Le flux de texte d'un récit est en cours sur la page courante */
    private bool $inText = false;
    private array $dark = [];

    private function cover(array $groups): void
    {
        $l = $this->l;
        $this->page();
        $this->noFolio[$l->page] = true;
        $this->dark[$l->page] = true;
        $mm = self::MM;
        $b = $this->b;
        $l->rect(0, 0, $l->pw, $l->ph, 'navy');
        // Photo choisie par le client parmi les photos proposées : nette sur sa zone (250 dpi), puis
        // fondue dans le bleu nuit (dégradé incrusté dans l'image), sous le titre.
        $img = null;
        $rel = (string) ($this->o['couverture'] ?? '');
        if ($rel !== '' && in_array($rel, self::covers(), true) && ($this->coverDpi($rel) ?? 0) >= self::DPI['page']) {
            $img = $this->prepare($rel, $l->pw, self::coverH() + 60 * $mm, 0);
            $this->mark($rel);
        }
        $this->coverPhoto = $img ? $rel : null;
        if ($img) {
            $file = $this->fade($img['file'], self::coverH() / (self::coverH() + 60 * $mm));
            $l->drawImage($l->loadImage($file), 0, 0, $l->pw, self::coverH() + 60 * $mm, true);
        } else {
            $this->stripes(0, 0, $l->pw, $l->ph);
            $l->logo($l->pw - $b - 118 * $mm, $b + 18 * $mm, 330);
        }
        // Cadre jaune intérieur, interrompu en haut par le nom du musée.
        $in = $b + 9 * $mm;
        $l->rect($in, $in, $l->pw - 2 * $in, $l->ph - 2 * $in, null, 'yellow', 1);
        $lab = 'MUSÉE SOCHAUX RÉTRO  ·  ÉDITION PERSONNALISÉE';
        $lw = $l->width($lab, 'display-b', 8.5, 2.2) + 24;
        $l->rect($l->pw / 2 - $lw / 2, $in - 8, $lw, 16, 'navy');
        $l->text($l->pw / 2 - $lw / 2 + 12, $in + 3.2, $lab, 'display-b', 8.5, 'yellow', 2.2);
        $l->logo($in + 8 * $mm, $in + 8 * $mm, 58);
        $yr = '1928 — ' . date('Y');
        $l->text($l->pw - $in - 8 * $mm - $l->width($yr, 'display-b', 11, 2), $in + 8 * $mm + 14, $yr, 'display-b', 11, 'white', 2);
        // Titre : « 100 » géant en relief (contour décalé + aplat jaune), « RÉCITS / DU LION » à côté.
        $base = $l->ph - $b - 70 * $mm;
        $x = $in + 6 * $mm;
        $l->strokeText($x + 7, $base + 7, '100', 'display', 230, 'white', 1.2, -4);
        $w100 = $l->text($x, $base, '100', 'display', 230, 'yellow', -4);
        $tx = $x + $w100 + 8;
        $l->text($tx, $base - 108, 'LES', 'display-b', 16, 'yellow', 3);
        $l->text($tx, $base - 56, 'RÉCITS', 'display', 58, 'white', 0.5);
        $l->text($tx, $base, 'DU LION', 'display', 58, 'white', 0.5);
        // Bande en biais : le sous-titre.
        $y0 = $base + 30;
        $l->polygon([[0, $y0 + 16], [$l->pw, $y0 - 16], [$l->pw, $y0 + 14], [0, $y0 + 46]], 'yellow');
        $sub = 'L’HISTOIRE DU FC SOCHAUX-MONTBÉLIARD, DES ORIGINES À NOS JOURS';
        $cx = $l->pw / 2;
        $cy = $y0 + 15;
        $l->rotated(-3, $cx, $cy, function () use ($l, $sub, $cx, $cy) {
            $l->text($cx - $l->width($sub, 'display-b', 11.5, 1.6) / 2, $cy + 4.5, $sub, 'display-b', 11.5, 'navy', 1.6);
        });
        // Étiquette façon billet de match : nom à gauche, souche numérotée à droite.
        $nom = trim((string) ($this->o['nom'] ?? ''));
        $num = trim((string) ($this->o['numero'] ?? ''));
        if ($nom !== '' || $num !== '') {
            $th = 54;
            $ty = $l->ph - $in - 10 * $mm - $th;
            $nameW = max(150, $l->width(mb_strtoupper($nom), 'display', 22, 0.6) + 34);
            $stub = $num !== '' ? 70 : 0;
            $l->rect($x, $ty, $nameW + $stub, $th, 'cream');
            $l->rect($x, $ty, 5, $th, 'yellow');
            $l->text($x + 16, $ty + 16, 'BILLET D’ENTRÉE · EXEMPLAIRE DE', 'display-b', 7.5, 'muted', 1.8);
            $l->text($x + 16, $ty + 41, $l->fit(mb_strtoupper($nom !== '' ? $nom : 'Supporter'), 'display', 22, $nameW - 24, 0.6), 'display', 22, 'navy', 0.6);
            if ($stub) {
                for ($d = $ty + 3; $d < $ty + $th - 3; $d += 6) {
                    $l->rect($x + $nameW, $d, 1, 3, 'muted');
                }
                $l->text($x + $nameW + 12, $ty + 18, 'N°', 'display-b', 8, 'muted', 1.5);
                $l->text($x + $nameW + 12, $ty + 42, $l->fit($num, 'display', 20, $stub - 16), 'display', 20, 'navy');
            }
        }
    }

    /** Photo de couverture fondue dans le bleu nuit à partir de $from (fraction de la hauteur), haut assombri. */
    private function fade(string $file, float $from): string
    {
        $out = substr($file, 0, -4) . '-couv.jpg';
        if (is_file($out)) {
            return $out;
        }
        $im = @imagecreatefromjpeg($file);
        if (!$im) {
            return $file;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $start = (int) ($h * ($from - 0.28));
        for ($y = 0; $y < $h; $y++) {
            $a = 0.0;
            if ($y >= $start) {
                $t = min(1, ($y - $start) / max(1, $h - $start));
                $a = $t * $t * (3 - 2 * $t);
            } elseif ($y < $h * 0.22) {
                $a = 0.45 * (1 - $y / ($h * 0.22));
            }
            if ($a <= 0.004) {
                continue;
            }
            $col = imagecolorallocatealpha($im, 0x0E, 0x1F, 0x4D, (int) round(127 * (1 - $a)));
            imageline($im, 0, $y, $w - 1, $y, $col);
        }
        imagejpeg($im, $out, 90);
        imagedestroy($im);
        return $out;
    }

    /** Hauteur de la photo de couverture (en haut, à fonds perdus), en points. */
    public static function coverH(): float
    {
        return (self::BLEED + 165) * self::MM;
    }

    public const COVERS = STORAGE_PATH . '/livres/couvertures.json';

    /** Photos proposées aux clients pour la couverture (choisies dans le back-office). @return list<string> */
    public static function covers(): array
    {
        $l = json_decode((string) @file_get_contents(self::COVERS), true);
        return is_array($l) ? array_values(array_filter($l, 'is_string')) : [];
    }

    public static function saveCovers(array $list): void
    {
        @mkdir(dirname(self::COVERS), 0775, true);
        file_put_contents(self::COVERS, json_encode(array_values(array_unique($list)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Définition d'une photo à la taille de la couverture (dpi), ou null si elle ne peut pas y aller (absente, presse). */
    public function coverDpi(string $rel): ?int
    {
        $m = Media::get($rel);
        if (!$m || GrandsRecits::press(($m['credit'] ?? '') . ' ' . ($m['caption'] ?? ''))) {
            return null;
        }
        $d = $this->measure($rel);
        return $d ? (int) round(self::dpi($d, (self::TRIM_W + 2 * self::BLEED) * self::MM, self::coverH())) : null;
    }

    /** Photos des récits assez définies pour la couverture et pas encore proposées. @return list<array{rel:string,dpi:int,caption:string}> */
    public function coverSuggestions(int $max = 24): array
    {
        $out = [];
        $have = array_flip(self::covers());
        foreach ($this->recits() as $items) {
            foreach ($items as $it) {
                foreach ($this->photos($it['doc'], false, false, true) as $p) {
                    if (isset($have[$p['rel']]) || isset($out[$p['rel']])) {
                        continue;
                    }
                    $dpi = $this->coverDpi($p['rel']);
                    if ($dpi !== null && $dpi >= self::DPI['page']) {
                        $out[$p['rel']] = ['rel' => $p['rel'], 'dpi' => $dpi, 'caption' => $this->caption($p)];
                    }
                }
            }
        }
        @file_put_contents($this->cache . '/dims.json', json_encode($this->dims));
        return array_slice(array_values($out), 0, $max);
    }

    /** Verso de la couverture : légende et crédit de la photo choisie. */
    private function coverCredit(): void
    {
        if (!$this->coverPhoto) {
            return;
        }
        $m = Media::get($this->coverPhoto) ?? [];
        $cap = $this->caption(['caption' => (string) ($m['caption'] ?? ''), 'credit' => (string) ($m['credit'] ?? '')]);
        if ($cap === '') {
            return;
        }
        [$x, $w] = $this->frame($this->l->page);
        $lines = $this->l->wrap([Layout::run('En couverture : ' . $cap, 'serif-i', 8.5, 'muted')], $w, 1.3);
        $this->l->drawLines($lines, $x, $this->b + $this->H - 24 * self::MM, $w);
    }

    private ?string $coverPhoto = null;

    private function stripes(float $x, float $y, float $w, float $h): void
    {
        for ($i = -$h; $i < $w; $i += 16) {
            $this->l->line($x + $i, $y + $h, $x + $i + $h * 0.35, $y, 'stripe', 1.6);
        }
    }

    private function dedication(): void
    {
        $l = $this->l;
        $this->page();
        $this->noFolio[$l->page] = true;
        $l->rect(0, 0, $l->pw, $l->ph, 'cream');
        [$x, $w] = $this->frame($l->page);
        $x += 14 * self::MM;
        $w -= 28 * self::MM;
        $nom = trim((string) ($this->o['nom'] ?? ''));
        $ded = trim((string) ($this->o['dedicace'] ?? ''));
        $y = $this->b + 80 * self::MM;
        if ($nom === '' && $ded === '') {
            $l->text($x, $y, 'CE LIVRE APPARTIENT À', 'display-b', 10, 'muted', 2.5);
            $l->line($x, $y + 50, $x + $w, $y + 50, 'muted', 0.6);
        } else {
            $l->text($x, $y, 'POUR TOI', 'display-b', 10, 'muted', 2.5);
            $y += 18;
            if ($nom !== '') {
                $lines = $l->wrap([Layout::run(mb_strtoupper($nom), 'display', 38, 'navy', null, 0.3)], $w, 1.0);
                $y += $l->drawLines($lines, $x, $y, $w) + 8;
            }
            if (($depuis = (int) ($this->o['depuis'] ?? 0)) >= 1928 && $depuis <= (int) date('Y')) {
                $l->text($x, $y + 14, 'SUPPORTER DEPUIS ' . $depuis, 'display-b', 13, 'B48D00', 1.4);
                $y += 22;
            }
            $y += 14;
            if ($ded !== '') {
                $lines = $l->wrap([Layout::run('« ' . $ded . ' »', 'serif-i', 15, 'ink')], $w, 1.55);
                $y += $l->drawLines(array_slice($lines, 0, 14), $x, $y, $w) + 18;
            }
            if (($sig = trim((string) ($this->o['signature'] ?? ''))) !== '') {
                $l->text($x, $y + 10, '— ' . $sig, 'serif', 11, 'muted');
            }
        }
        $yy = $this->b + $this->H - 22 * self::MM;
        $l->line($x, $yy, $x + $w, $yy, 'sand', 0.8);
        $l->text($x, $yy + 14, 'ÉDITION PERSONNALISÉE', 'display-b', 8.5, 'muted', 1.8);
        if (($num = trim((string) ($this->o['numero'] ?? ''))) !== '') {
            $t = 'EXEMPLAIRE N° ' . $num;
            $l->text($x + $w - $l->width($t, 'display-b', 8.5, 1.8), $yy + 14, $t, 'display-b', 8.5, 'muted', 1.8);
        }
    }


    /** Fiche publiée d'un type donné, ou null. */
    private static function fiche(int $id, string $type): ?array
    {
        $d = $id > 0 ? Fiches::get($id) : null;
        return $d && ($d['type'] ?? '') === $type && ($d['status'] ?? '') === 'publie' ? $d : null;
    }


    /** QR code (carrés pleins) de côté $size, coin haut gauche ($x, $y), sur fond blanc. */
    private function qr(string $text, float $x, float $y, float $size): void
    {
        $m = \App\Services\Qr::matrix($text);
        $n = count($m);
        if (!$n) {
            return;
        }
        $q = $size / ($n + 2);
        $this->l->rect($x, $y, $size, $size, 'white');
        foreach ($m as $r => $row) {
            foreach ($row as $c => $on) {
                if ($on) {
                    $this->l->rect($x + ($c + 1) * $q, $y + ($r + 1) * $q, $q + 0.05, $q + 0.05, 'navy');
                }
            }
        }
    }

    /** Fiches des matchs racontés dans un récit (liens vers /matchs/…). @return list<int> */
    private function linked(array $doc): array
    {
        static $byPath = null;
        if ($byPath === null) {
            $byPath = [];
            foreach (\App\Data\Derived::part('matches') as $id => $x) {
                $byPath[(string) $x['path']] = (int) $id;
            }
        }
        $html = implode(' ', array_column((array) ($doc['sections'] ?? []), 'html'));
        preg_match_all('#href="(?:https?://[^/"]+)?(/matchs/[^"]+)"#', $html, $mm);
        return array_values(array_unique(array_filter(array_map(fn ($p) => $byPath[$p] ?? 0, $mm[1] ?? []))));
    }

    /** Matchs cochés dans le carnet du supporter choisi. @return array<int,string> */
    private function carnetMatches(): array
    {
        $id = (string) ($this->o['carnet'] ?? '');
        $c = $id !== '' ? \App\Services\Carnet::get($id) : null;
        return $c ? array_map('strval', (array) ($c['matches'] ?? [])) : [];
    }

    /** Page « Mes matchs au stade » : bilan du carnet et récits tamponnés, avec leurs pages. */
    private function carnetPage(): void
    {
        if (!$this->seen) {
            return;
        }
        $st = \App\Services\Carnet::stats(array_keys($this->seen));
        if (!$st['n']) {
            return;
        }
        $l = $this->l;
        $mm = self::MM;
        if (!$this->right($l->pdf->pageCount())) {
            $this->blank();
        }
        $p = $this->page();
        $this->noFolio[$p] = true;
        $this->dark[$p] = true;
        $l->rect(0, 0, $l->pw, $l->ph, 'navy');
        $this->stripes(0, 0, $l->pw, $l->ph);
        [$x, $w] = $this->frame($p);
        $y = $this->b + 24 * $mm;
        $nom = trim((string) ($this->o['nom'] ?? ''));
        $l->text($x, $y, 'TIRÉ DU CARNET DU SUPPORTER' . ($nom !== '' ? ' DE ' . mb_strtoupper($nom) : ''), 'display-b', 10, 'yellow', 2.4);
        $l->text($x, $y + 62, 'J’Y ÉTAIS', 'display', 52, 'white', 0.4);
        $y += 92;
        $tiles = [[$st['n'], 'match' . ($st['n'] > 1 ? 's' : '') . ' au stade'], [$st['v'], 'victoire' . ($st['v'] > 1 ? 's' : '')], [$st['nul'], 'nul' . ($st['nul'] > 1 ? 's' : '')], [$st['gf'], 'buts sochaliens']];
        $tw = ($w - 3 * 8) / 4;
        foreach ($tiles as $i => [$v, $lab]) {
            $tx = $x + $i * ($tw + 8);
            $l->rect($tx, $y, $tw, 70, $i === 0 ? 'yellow' : 'deep');
            $l->text($tx + 10, $y + 40, (string) $v, 'display', 34, $i === 0 ? 'navy' : 'yellow');
            $l->text($tx + 10, $y + 58, mb_strtoupper($lab), 'display-b', 7.5, $i === 0 ? 'navy' : 'cream', 1.2);
        }
        $y += 92;
        $line = function (string $k, ?array $x2) use ($l, $x, $w, &$y) {
            if (!$x2) {
                return;
            }
            $t = $x2['home'] . ' – ' . $x2['away'] . ' (' . ($x2['sh'] ? $x2['us'] . '-' . $x2['them'] : $x2['them'] . '-' . $x2['us']) . '), ' . date_fr((string) $x2['date']);
            $y += $l->drawLines($l->wrap([Layout::run(mb_strtoupper($k), 'display-b', 9, 'yellow', null, 1.4, 10), Layout::run($t, 'serif', 11, 'white')], $w, 1.4), $x, $y, $w) + 6;
        };
        $line('Premier match', $st['first']);
        $line('Plus belle victoire', $st['best']);
        $line('Dernier match', $st['last']);
        if ($this->stamped) {
            $y += 14;
            $l->text($x, $y, 'DANS CE LIVRE, LES RÉCITS DE TES MATCHS', 'display-b', 9, 'yellow', 1.8);
            $y += 16;
            foreach ($this->stamped as $r) {
                if ($y > $l->ph - $this->b - 30 * $mm) {
                    break;
                }
                $pg = 'p. ' . ($r['page'] + 1);
                $l->text($x, $y + 10, $l->fit($r['t'], 'serif', 11, $w - 50), 'serif', 11, 'cream');
                $l->text($x + $w - $l->width($pg, 'display-b', 10), $y + 10, $pg, 'display-b', 10, 'yellow');
                $y += 16;
            }
        }
    }

    /** Option « Le jour de ta naissance » : le match du FCSM le plus proche de la date donnée. */
    private function birthMatch(): void
    {
        $d = (string) ($this->o['naissance'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return;
        }
        $t = strtotime($d);
        if (!empty($this->o['naissance_feuille'])) {
            // Match tiré des feuilles de l'association (pas encore de fiche au musée).
            $f = (array) $this->o['naissance_feuille'];
            $days = (int) round((strtotime((string) $f['date']) - $t) / 86400);
            $when = $days === 0 ? 'ce jour-là' : ($days > 0 ? $days . ' jour' . ($days > 1 ? 's' : '') . ' plus tard' : abs($days) . ' jour' . (abs($days) > 1 ? 's' : '') . ' plus tôt');
            $this->myMatch(0, (string) ($this->o['naissance_titre'] ?? '') ?: 'Le jour de ta naissance', 'Le ' . date_fr($d) . ' : ' . $when . ', Sochaux jouait ce match.', self::fromSheet($f));
            return;
        }
        $best = null;
        foreach (\App\Data\Derived::part('matches') as $id => $x) {
            if (empty($x['v']) || empty($x['date'])) {
                continue;
            }
            $diff = abs(strtotime((string) $x['date']) - $t);
            if ($best === null || $diff < $best[1]) {
                $best = [(int) $id, $diff, (string) $x['date']];
            }
        }
        if (!$best || $best[1] > 60 * 86400) {
            return;
        }
        $days = (int) round((strtotime($best[2]) - $t) / 86400);
        $when = $days === 0 ? 'Ce jour-là' : ($days > 0 ? ($days === 1 ? 'Le lendemain' : $days . ' jours plus tard') : ($days === -1 ? 'La veille' : abs($days) . ' jours plus tôt'));
        $note = 'Le ' . date_fr($d) . ' : ' . mb_strtolower(mb_substr($when, 0, 1)) . mb_substr($when, 1) . ', Sochaux jouait ce match.';
        $this->myMatch($best[0], (string) ($this->o['naissance_titre'] ?? '') ?: 'Le jour de ta naissance', $note);
    }

    /** Photo du lecteur, assez définie pour la taille d'impression : largeur du cadre en points, ou null. */
    public static function photoFrame(string $file): ?float
    {
        $i = @getimagesize($file);
        if (!$i) {
            return null;
        }
        foreach ([140, 110, 85] as $wmm) {
            $w = $wmm * self::MM;
            if (self::dpi([(int) $i[0], (int) $i[1]], $w, $w * $i[1] / $i[0]) >= self::DPI['colonne']) {
                return $w;
            }
        }
        return null;
    }

    /** Option « Ma photo » : la photo du lecteur, façon photo collée dans un album. */
    private function myPhoto(string $file): void
    {
        if ($file === '' || !is_file($file) || !($fw = self::photoFrame($file))) {
            return;
        }
        $l = $this->l;
        $mm = self::MM;
        $p = $this->page();
        $this->noFolio[$p] = true;
        $l->rect(0, 0, $l->pw, $l->ph, 'cream');
        $i = getimagesize($file);
        $fh = min($fw * $i[1] / $i[0], 150 * $mm);
        $fw = $fh * $i[0] / $i[1];
        $cx = $l->pw / 2;
        $cy = $l->ph / 2 - 20;
        $img = $l->loadImage($file);
        $l->rotated(-2.5, $cx, $cy, function () use ($l, $img, $cx, $cy, $fw, $fh) {
            $l->rect($cx - $fw / 2 - 10 + 4, $cy - $fh / 2 - 10 + 5, $fw + 20, $fh + 34, 'sand');
            $l->rect($cx - $fw / 2 - 10, $cy - $fh / 2 - 10, $fw + 20, $fh + 34, 'white');
            if ($img) {
                $l->drawImage($img, $cx - $fw / 2, $cy - $fh / 2, $fw, $fh);
            }
        });
        foreach ([[-1, 20], [1, -24]] as [$side, $deg]) {
            $tx = $cx + $side * ($fw / 2 - 6);
            $ty = $cy - $fh / 2 - 8;
            $l->rotated($deg, $tx, $ty, function () use ($l, $tx, $ty) {
                $l->rect($tx - 30, $ty - 9, 60, 18, 'butter');
            });
        }
        $cap = trim((string) ($this->o['photo_legende'] ?? ''));
        if ($cap !== '') {
            $w = 140 * $mm;
            $l->drawLines($l->wrap([Layout::run($cap, 'serif-i', 14, 'ink')], $w, 1.4), $cx - $w / 2, $cy + $fh / 2 + 40, $w, 'center');
        }
    }

    /**
     * Maillots du FCSM, relevés sur les photos d'équipe et de maillots du musée (sans sponsor ni logo de
     * marque : seulement les couleurs et les motifs). Pour la photo 3D (shop3d.js, `jerseyGlsl`) : body,
     * sleeve, trim, collar, cuffs, layers ; pour le maillot dessiné de secours : body, shade, sleeve, trim,
     * text (flocage), edge (contour du flocage), band. era : saison ou époque ; ref : photo de référence.
     */
    public const JERSEYS = [
        'annees-30' => ['label' => 'Années 30 · col lacé', 'era' => 'Années 30', 'ref' => '2024/11/1930-06-01-photo-equipe-2.jpg', 'body' => 'F4CC2A', 'shade' => 'D9AE10', 'sleeve' => null, 'trim' => '0E1F4D', 'collar' => 'lace', 'cuffs' => true, 'layers' => [], 'text' => '0E1F4D', 'edge' => 'FFFFFF', 'band' => null],
        'annees-50' => ['label' => 'Années 50 · col polo', 'era' => 'Années 50', 'ref' => '2024/12/312_001.jpg', 'body' => 'F6C400', 'shade' => 'D9A800', 'sleeve' => null, 'trim' => '0E1F4D', 'collar' => 'polo', 'cuffs' => true, 'layers' => [], 'text' => '0E1F4D', 'edge' => 'FFFFFF', 'band' => null],
        '1969' => ['label' => '1969-1970 · bande en damier', 'era' => '1969-1970', 'ref' => '2026/06/01-retro-Peugeot-sur-le-maillot-MICHELIN-1024x683-1.jpg', 'body' => 'F6C400', 'shade' => 'D9A800', 'sleeve' => null, 'trim' => '0E1F4D', 'collar' => 'crew', 'cuffs' => true, 'layers' => [['t' => 'checker', 'y0' => 0.18, 'y1' => 0.42, 'n' => 13, 'color' => '111111', 'front' => true]], 'text' => '0E1F4D', 'edge' => 'FFFFFF', 'band' => '111111'],
        '1970' => ['label' => '1970-1971 · col rond', 'era' => '1970-1971', 'ref' => '2026/06/FC-SOCHAUX-MONTBELIARD-1970-71.jpg', 'body' => 'F7C51E', 'shade' => 'DBA90A', 'sleeve' => null, 'trim' => 'F7C51E', 'collar' => 'crew', 'cuffs' => false, 'layers' => [], 'text' => '0E1F4D', 'edge' => 'FFFFFF', 'band' => null],
        '1978' => ['label' => '1978-1979 · col V bleu', 'era' => '1978-1979', 'ref' => '2026/06/fc-sochaux-1978-79.jpg', 'body' => 'F6C400', 'shade' => 'D9A800', 'sleeve' => null, 'trim' => '1E3FA8', 'collar' => 'v', 'cuffs' => true, 'layers' => [], 'text' => '1E3FA8', 'edge' => 'FFFFFF', 'band' => null],
        '1980' => ['label' => '1979-1980 · manches bleues rayées', 'era' => '1979-1980', 'ref' => '2026/06/yannick-stopyra-fc-sochaux-1980.jpg', 'body' => 'F6C400', 'shade' => 'D9A800', 'sleeve' => '1A2C7A', 'trim' => '1A2C7A', 'collar' => 'v', 'cuffs' => false, 'layers' => [['t' => 'raglan', 'w' => 0.06, 'color' => 'F6C400']], 'text' => '1A2C7A', 'edge' => 'FFFFFF', 'band' => null],
        '1983' => ['label' => '1983-1985 · fines rayures', 'era' => '1983-1985', 'ref' => '2026/06/sochaux-home-football-shirt-1983-1985-s_44935_1.jpg', 'body' => 'F6C400', 'shade' => 'D9A800', 'sleeve' => null, 'trim' => '1E3FA8', 'collar' => 'v', 'cuffs' => true, 'layers' => [['t' => 'hstripes', 'n' => 26, 'w' => 0.07, 'color' => '6F8FD0'], ['t' => 'chevrons', 'n' => 9, 'w' => 0.2, 'color' => '1E3FA8', 'front' => true]], 'text' => '1E3FA8', 'edge' => 'FFFFFF', 'band' => null],
        '1987' => ['label' => '1987-1988 · épaules bleues', 'era' => '1987-1988', 'ref' => '2024/02/Photo-equipe-juillet-1987-Credit-Jean-Luc-Gilme.png', 'body' => 'F6C400', 'shade' => 'D9A800', 'sleeve' => null, 'trim' => '2F4C9A', 'collar' => 'polo', 'cuffs' => false, 'layers' => [['t' => 'yoke', 'y' => 0.45, 'yb' => 0.46, 'torso' => true, 'color' => '4F6DB5']], 'text' => '1E3FA8', 'name' => 'F6C400', 'edge' => 'FFFFFF', 'band' => null],
        '1998' => ['label' => '1998-1999 · flancs bleus', 'era' => '1998-1999', 'ref' => '2026/06/maillot-sochaux-vintage-domicile-1998-1999-aisselle-a-50cm-asics-fc-montbeliard-maillots-de-foot-retro-the-football-market-983_720x.webp', 'body' => 'E9E23A', 'shade' => 'CFC71E', 'sleeve' => null, 'trim' => '1E3FA8', 'collar' => 'v', 'cuffs' => true, 'layers' => [['t' => 'side', 'x' => 0.38, 'color' => '1E3FA8']], 'text' => '1E3FA8', 'edge' => 'FFFFFF', 'band' => null],
        '2004' => ['label' => '2003-2004 · blanc, Coupe de la Ligue', 'era' => '2003-2004', 'ref' => '2026/06/17-avril-2004-Sochaux-remporte-la-Coupe-de-la-ligue.webp', 'body' => 'F4F4F0', 'shade' => 'D8D8D2', 'sleeve' => null, 'trim' => '0E1F4D', 'collar' => 'v', 'cuffs' => true, 'layers' => [['t' => 'side', 'x' => 0.4, 'color' => 'F6C400']], 'text' => '0E1F4D', 'edge' => 'F6C400', 'band' => null],
        '2007' => ['label' => '2006-2007 · flancs noirs, finale de Coupe', 'era' => '2006-2007', 'ref' => '2026/06/le-onze-de-depart-de-sochaux-non-vous-ne-revez-pas-mickael-isabey-n-est-pas-retenu-il-n-est-meme-pas-sur-la-feuille-de-match-photo-alexandre-marchi-1589306142.jpg', 'body' => 'F6C400', 'shade' => 'D9A800', 'sleeve' => null, 'trim' => '151515', 'collar' => 'crew', 'cuffs' => true, 'layers' => [['t' => 'side', 'x' => 0.36, 'color' => '151515']], 'text' => '151515', 'edge' => 'FFFFFF', 'band' => null],
        '2015' => ['label' => '2014-2015 · liserés noirs', 'era' => '2014-2015', 'ref' => '2026/06/FC-Sochaux-2015-maillot-domicile.jpg', 'body' => 'F6C400', 'shade' => 'D9A800', 'sleeve' => null, 'trim' => '151515', 'collar' => 'v', 'cuffs' => true, 'layers' => [], 'text' => '0E1F4D', 'edge' => 'FFFFFF', 'band' => null],
        '2023' => ['label' => '2023-2024 · Sociochaux', 'era' => '2023-2024', 'ref' => '2026/06/fcsm-maillot-sociochaux.jpg', 'body' => 'F6C400', 'shade' => 'D9A800', 'sleeve' => null, 'trim' => '1E3FA8', 'collar' => 'v', 'cuffs' => true, 'layers' => [], 'text' => '0E1F4D', 'edge' => 'FFFFFF', 'band' => null],
        '2026' => ['label' => '2025-2026 · domicile', 'era' => '2025-2026', 'ref' => '2026/05/FCSM-LPF43-2025-2026-1-Michael-Desprez.jpg', 'body' => 'F6C400', 'shade' => 'D9A800', 'sleeve' => null, 'trim' => '0E1F4D', 'collar' => 'crew', 'cuffs' => true, 'layers' => [], 'text' => '0E1F4D', 'edge' => 'FFFFFF', 'band' => null],
        'exterieur' => ['label' => 'Extérieur bleu nuit', 'era' => '', 'ref' => '', 'body' => '14286A', 'shade' => '0B1A45', 'sleeve' => null, 'trim' => 'F6C400', 'collar' => 'v', 'cuffs' => true, 'layers' => [], 'text' => 'F6C400', 'edge' => '0B1A45', 'band' => null],
    ];


    public const JERSEY_STATUS = STORAGE_PATH . '/livres/maillots.json';

    /** Validation des maillots par les historiens : clé => {status: valide|revoir, by, at, note}. */
    public static function jerseyStatus(): array
    {
        $d = json_decode((string) @file_get_contents(self::JERSEY_STATUS), true);
        return is_array($d) ? $d : [];
    }

    public static function setJerseyStatus(string $key, string $status, string $by, string $note = ''): void
    {
        if (!isset(self::JERSEYS[$key])) {
            return;
        }
        $d = self::jerseyStatus();
        $d[$key] = ['status' => $status === 'valide' ? 'valide' : 'revoir', 'by' => $by, 'at' => date('c'), 'note' => mb_substr($note, 0, 500)];
        @mkdir(dirname(self::JERSEY_STATUS), 0775, true);
        file_put_contents(self::JERSEY_STATUS, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /** Maillots proposés aux clients : seulement ceux validés par les historiens. @return array<string,array> */
    public static function jerseysValid(): array
    {
        $st = self::jerseyStatus();
        return array_filter(self::JERSEYS, fn ($k) => ($st[$k]['status'] ?? '') === 'valide', ARRAY_FILTER_USE_KEY);
    }

    /**
     * Option « Ton maillot » : un maillot de collection, vu de dos, floqué au nom et au numéro du lecteur,
     * présenté encadré (cadre bleu nuit, passe-partout crème, plaque gravée).
     */
    private function myJersey(): void
    {
        $name = mb_strtoupper(trim((string) ($this->o['maillot_nom'] ?? '')));
        $num = mb_substr(preg_replace('/\D/', '', (string) ($this->o['maillot_numero'] ?? '')), 0, 2);
        if ($name === '' && $num === '') {
            return;
        }
        $st = self::JERSEYS[(string) ($this->o['maillot_style'] ?? '')] ?? self::JERSEYS['2026'];
        $st['sleeve'] ??= $st['body'];
        $l = $this->l;
        $mm = self::MM;
        $p = $this->page();
        // Maillot photographié en 3D : double page, le devant à gauche, le dos floqué à droite, en grand.
        $back = (string) ($this->o['maillot_image'] ?? '');
        $front = (string) ($this->o['maillot_devant'] ?? '');
        if ($back !== '' && is_file($back)) {
            $this->jerseySpread($st, $name, $num, $front, $back, $p);
            return;
        }
        $this->noFolio[$p] = true;
        $this->dark[$p] = true;
        // Mur sombre, cadre, passe-partout
        $l->rect(0, 0, $l->pw, $l->ph, 'deep');
        $this->stripes(0, 0, $l->pw, $l->ph);
        $fx = $this->b + 18 * $mm;
        $fy = $this->b + 26 * $mm;
        $fw = $this->W - 36 * $mm;
        $fh = 214 * $mm;
        $l->rect($fx + 6, $fy + 8, $fw, $fh, '050C22');
        $l->rect($fx, $fy, $fw, $fh, '1B2A5C');
        $l->rect($fx + 4, $fy + 4, $fw - 8, $fh - 8, '0E1F4D');
        $m = 13 * $mm;
        $l->rect($fx + $m, $fy + $m, $fw - 2 * $m, $fh - 2 * $m, 'F3EDDF');
        $l->line($fx + $m, $fy + $m, $fx + $fw - $m, $fy + $m, 'D9D1BC', 2);
        // Maillot : repère 1000 × 1060 posé au centre du passe-partout
        $k = ($fw - 2 * $m - 30 * $mm) / 1000;
        $ox = $fx + $fw / 2 - 500 * $k;
        $oy = $fy + $m + 16 * $mm;
        $P = function (array $cmds) use ($k, $ox, $oy): array {
            return array_map(function ($c) use ($k, $ox, $oy) {
                $o = [array_shift($c)];
                foreach (array_chunk($c, 2) as [$x, $y]) {
                    $o[] = $ox + $x * $k;
                    $o[] = $oy + $y * $k;
                }
                return $o;
            }, $cmds);
        };
        $body = [['M', 360, 40], ['C', 420, 70, 580, 70, 640, 40], ['L', 800, 92], ['C', 860, 112, 900, 150, 930, 200], ['L', 1000, 380], ['L', 845, 450],
            ['L', 800, 360], ['C', 790, 560, 795, 820, 805, 1010], ['C', 640, 1050, 360, 1050, 195, 1010], ['C', 205, 820, 210, 560, 200, 360],
            ['L', 155, 450], ['L', 0, 380], ['L', 70, 200], ['C', 100, 150, 140, 112, 200, 92], ['L', 360, 40]];
        // Ombre portée sur le passe-partout
        $shadow = array_map(fn ($c) => array_merge([$c[0]], array_map(fn ($v, $i) => $v + ($i % 2 ? 22 : 16), array_slice($c, 1), array_keys(array_slice($c, 1)))), $body);
        $l->path($P($shadow), 'D9D1BC');
        $l->path($P($body), $st['body']);
        // Manches (couleur propre), poignets
        $sleeveL = [['M', 200, 92], ['C', 140, 112, 100, 150, 70, 200], ['L', 0, 380], ['L', 155, 450], ['L', 200, 360], ['C', 205, 250, 205, 160, 200, 92]];
        $sleeveR = [['M', 800, 92], ['C', 860, 112, 900, 150, 930, 200], ['L', 1000, 380], ['L', 845, 450], ['L', 800, 360], ['C', 795, 250, 795, 160, 800, 92]];
        $l->path($P($sleeveL), $st['sleeve']);
        $l->path($P($sleeveR), $st['sleeve']);
        $l->path($P([['M', 0, 380], ['L', 155, 450], ['L', 168, 422], ['L', 12, 352]]), $st['trim']);
        $l->path($P([['M', 1000, 380], ['L', 845, 450], ['L', 832, 422], ['L', 988, 352]]), $st['trim']);
        // Volumes : flancs ombrés, pli central léger
        $l->path($P([['M', 200, 360], ['C', 210, 560, 205, 820, 195, 1010], ['C', 230, 1018, 262, 1024, 290, 1028], ['C', 270, 800, 262, 560, 250, 300], ['L', 200, 360]]), $st['shade']);
        $l->path($P([['M', 800, 360], ['C', 790, 560, 795, 820, 805, 1010], ['C', 770, 1018, 738, 1024, 710, 1028], ['C', 730, 800, 738, 560, 750, 300], ['L', 800, 360]]), $st['shade']);
        if ($st['band']) {
            $l->path($P([['M', 203, 300], ['L', 797, 300], ['L', 797, 380], ['L', 203, 380]]), $st['band']);
        }
        // Col rond côtelé, vu de dos
        $l->path($P([['M', 360, 40], ['C', 420, 70, 580, 70, 640, 40], ['L', 668, 52], ['C', 590, 100, 410, 100, 332, 52]]), $st['trim']);
        // Flocage : nom en arc léger (lettres posées sur une courbe), numéro avec contour
        if ($name !== '') {
            $fs = 40;
            while ($fs > 16 && $l->width($name, 'display', $fs, 3) > 470 * $k) {
                $fs--;
            }
            $chars = mb_str_split($name);
            $tw = $l->width($name, 'display', $fs, 3);
            $cx = $ox + 500 * $k;
            $base = $oy + 225 * $k;
            $x = $cx - $tw / 2;
            foreach ($chars as $ch) {
                $cw = $l->width($ch, 'display', $fs) + 3;
                $mid = $x + $cw / 2 - $cx;
                $dy = ($mid * $mid) / (2600 * $k);
                $ang = rad2deg(atan($mid / (1300 * $k)));
                $l->rotated($ang, $x + $cw / 2, $base + $dy, function () use ($l, $ch, $x, $base, $dy, $fs, $st) {
                    $l->text($x, $base + $dy, $ch, 'display', $fs, $st['text']);
                });
                $x += $cw;
            }
        }
        if ($num !== '') {
            $ns = 330 * $k * 1.0;
            $nw = $l->width($num, 'display', $ns, 6);
            $nx = $ox + 500 * $k - $nw / 2;
            $nb = $oy + 700 * $k;
            $l->strokeText($nx, $nb, $num, 'display', $ns, $st['edge'], 7, 6);
            $l->text($nx, $nb, $num, 'display', $ns, $st['text'], 6);
        }
        $this->plaque($fx, $fy, $fw, $fh, $m, $name, $num);
    }



    /** Double page « Ton maillot » : devant (page de gauche) et dos (page de droite), photos 3D en grand. */
    private function jerseySpread(array $st, string $name, string $num, string $front, string $back, int $p): void
    {
        $l = $this->l;
        $mm = self::MM;
        // La double page commence sur une page de gauche : la page déjà ouverte est réutilisée si elle l'est.
        $this->noFolio[$p] = true;
        if ($this->right($p)) {
            $this->noFolio[$p] = true;
            $p = $this->page();
            $this->noFolio[$p] = true;
        }
        $pages = [[$p, $front, 'LE DEVANT'], [null, $back, 'LE DOS']];
        foreach ($pages as $i => [$pg, $file, $label]) {
            if ($pg === null) {
                $pg = $this->page();
                $this->noFolio[$pg] = true;
            }
            $this->dark[$pg] = true;
            $l->page = $pg;
            $l->rect(0, 0, $l->pw, $l->ph, 'deep');
            $this->stripes(0, 0, $l->pw, $l->ph);
            // Passe-partout qui court sur la double page (côté reliure sans marge)
            $mx = $i === 0 ? $this->b + 14 * $mm : 0;
            $mw = $l->pw - $this->b - 14 * $mm;
            $my = $this->b + 30 * $mm;
            $mh = $this->H - 52 * $mm;
            $l->rect($mx, $my, $mw, $mh, 'F3EDDF');
            if ($file !== '' && is_file($file) && ($jpg = $this->onMat($file))) {
                $img = $l->loadImage($jpg);
                $aw = $mw - 8 * $mm;
                $ah = $mh - 8 * $mm;
                $s2 = min($aw / $img['w'], $ah / $img['h']);
                $l->drawImage($img, $mx + $mw / 2 - $img['w'] * $s2 / 2, $my + 4 * $mm, $img['w'] * $s2, $img['h'] * $s2);
            }
            $tx = $i === 0 ? $this->b + 14 * $mm : $this->b + 4 * $mm;
            $l->text($tx, $this->b + 16 * $mm, $i === 0 ? 'TON MAILLOT' : mb_strtoupper(trim(($name !== '' ? $name : '') . ($num !== '' ? ' · N° ' . $num : ''))), 'display', 22, $i === 0 ? 'yellow' : 'white', 1);
            $l->text($tx, $this->b + 23 * $mm, $label . (($st['era'] ?? '') !== '' ? ' · INSPIRÉ DE ' . mb_strtoupper($st['era']) : ''), 'display-b', 9, 'mist', 2);
        }
        $note = 'Maillot inspiré ' . (($st['era'] ?? '') !== '' ? 'des maillots du FCSM de ' . $st['era'] : 'des couleurs du FCSM') . ' : une évocation libre, pas une reproduction fidèle. Ni sponsor ni équipementier, juste le Lion et ton nom, pour le plaisir.';
        $nl = $l->wrap([Layout::run($note, 'serif-i', 9, 'mist')], $l->pw - $this->b - 18 * $mm, 1.35);
        $l->drawLines($nl, $this->b + 4 * $mm, $this->b + $this->H - 16 * $mm, $l->pw - $this->b - 18 * $mm);
    }

    /** Plaque gravée sous le maillot encadré, et titre de la page. */
    private function plaque(float $fx, float $fy, float $fw, float $fh, float $m, string $name, string $num): void
    {
        $l = $this->l;
        $mm = self::MM;
        $pw = 92 * $mm;
        $px = $fx + $fw / 2 - $pw / 2;
        $py = $fy + $fh - $m - 26 * $mm;
        $l->rect($px + 2, $py + 3, $pw, 16 * $mm, 'B5A882');
        $l->rect($px, $py, $pw, 16 * $mm, 'D8C48A');
        $l->rect($px + 3, $py + 3, $pw - 6, 16 * $mm - 6, null, 'A8935A', 0.8);
        $t1 = mb_strtoupper(trim(($name !== '' ? $name : '') . ($num !== '' ? ' · N° ' . $num : '')), 'UTF-8');
        $t2 = 'FC SOCHAUX-MONTBÉLIARD · MAILLOT DU SUPPORTER';
        $l->text($px + $pw / 2 - $l->width($t1, 'display', 15, 1.5) / 2, $py + 22, $t1, 'display', 15, '3B2F12', 1.5);
        $l->text($px + $pw / 2 - $l->width($t2, 'display-b', 6.5, 1.4) / 2, $py + 36, $t2, 'display-b', 6.5, '5E4C1E', 1.4);
        $l->text($this->b + $this->W / 2 - $l->width('TON MAILLOT', 'display-b', 11, 3) / 2, $this->b + 16 * $mm, 'TON MAILLOT', 'display-b', 11, 'yellow', 3);
    }

    /** Photo 3D du maillot (PNG transparent) posée sur le crème du passe-partout, en JPEG. */
    private function onMat(string $png): ?string
    {
        $out = $this->cache . '/' . md5_file($png) . '-mat.jpg';
        if (is_file($out)) {
            return $out;
        }
        $im = @imagecreatefrompng($png);
        if (!$im) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $bg = imagecreatetruecolor($w, $h);
        imagefill($bg, 0, 0, (int) imagecolorallocate($bg, 0xF3, 0xED, 0xDF));
        imagealphablending($bg, true);
        imagecopy($bg, $im, 0, 0, 0, 0, $w, $h);
        imagejpeg($bg, $out, 92);
        imagedestroy($im);
        imagedestroy($bg);
        return $out;
    }

    /** Option « Mon match » : une page sur le match choisi par le client (données de la fiche, rien d'inventé). */
    private function myMatch(int $id, string $kicker = 'MON MATCH', string $note = '', ?array $data = null): void
    {
        $doc = $data === null ? self::fiche($id, 'match') : ['match' => $data];
        $m = $doc['match'] ?? null;
        if (!$m) {
            return;
        }
        $l = $this->l;
        $mm = self::MM;
        $p = $this->page();
        $this->noFolio[$p] = true;
        $this->dark[$p] = true;
        $l->rect(0, 0, $l->pw, $l->ph, 'navy');
        [$x, $w] = $this->frame($p);
        $y = $this->b + 70 * $mm;
        // Photo du match en haut si elle est assez définie (pleine largeur à fonds perdus)
        $ph = $this->photos($doc)[0] ?? null;
        $img = $ph ? $this->prepare($ph['rel'], $l->pw, $this->b + 100 * $mm, self::DPI['bandeau']) : null;
        if ($img) {
            $this->mark($ph['rel']);
            $l->drawImage($l->loadImage($this->fade($img['file'], 0.86)), 0, 0, $l->pw, $this->b + 100 * $mm, true);
            $y = $this->b + 88 * $mm;
        }
        $l->text($x, $y, mb_strtoupper($kicker), 'display-b', 11, 'yellow', 3);
        $date = (string) ($m['date'] ?? '');
        $comp = (string) ($m['competition_label'] ?? $m['competition'] ?? '');
        $round = (string) ($m['round_text'] ?? '');
        $sub = trim(($date !== '' ? date_fr($date, true) : '') . ' · ' . ($round !== '' && mb_stripos($round, $comp) !== false ? $round : trim($comp . ' ' . $round)), ' ·');
        $l->text($x, $y + 18, $sub, 'serif-i', 12, 'cream');
        $y += 40;
        $sc = $m['score'] ?? [];
        $home = (string) ($m['home']['name'] ?? '');
        $away = (string) ($m['away']['name'] ?? '');
        foreach ([[$home, $sc['home'] ?? ''], [$away, $sc['away'] ?? '']] as [$team, $g]) {
            $t = $l->fit(mb_strtoupper($team), 'display', 40, $w - 70, 0.3);
            $l->text($x, $y + 38, $t, 'display', 40, $team === 'Sochaux' || str_contains($team, 'Sochaux') ? 'yellow' : 'white', 0.3);
            $gs = (string) $g;
            $l->text($x + $w - $l->width($gs, 'display', 48), $y + 40, $gs, 'display', 48, 'white');
            $y += 50;
        }
        if (!empty($sc['pens'])) {
            $l->text($x, $y + 6, 'Tirs au but : ' . (is_array($sc['pens']) ? implode('-', $sc['pens']) : (string) $sc['pens']), 'serif-i', 11, 'cream');
            $y += 16;
        }
        $l->rect($x, $y + 6, $w, 1.2, 'yellow');
        $y += 22;
        $facts = array_filter([
            'Stade' => (string) ($m['stadium'] ?? ''),
            'Spectateurs' => !empty($m['spectators']) ? number_format((int) $m['spectators'], 0, ',', ' ') : '',
            'Arbitre' => (string) ($m['referee'] ?? ''),
            'Buts' => implode(' ; ', array_filter(array_map(fn ($g) => trim(($g['team'] ?? '') . ' : ' . ($g['scorers'] ?? ''), ' :'), (array) ($m['goals'] ?? [])))),
        ]);
        foreach ($facts as $k => $v) {
            $lines = $l->wrap([Layout::run(mb_strtoupper($k) . '   ', 'display-b', 9, 'yellow', null, 1.5), Layout::run($v, 'serif', 11, 'white')], $w, 1.4);
            $y += $l->drawLines($lines, $x, $y, $w) + 4;
        }
        $rows = (array) ($m['lineup']['rows'] ?? []);
        if ($rows) {
            $y += 10;
            $l->text($x, $y + 10, 'LA COMPOSITION SOCHALIENNE', 'display-b', 9, 'yellow', 1.5);
            $y += 18;
            $names = array_map(fn ($r) => trim(($r['position'] ?? '') . ' ' . ($r['name'] ?? '')) . (!empty($r['captain']) ? ' (cap.)' : ''), $rows);
            $half = (int) ceil(count($names) / 2);
            foreach (array_chunk($names, max(1, $half)) as $c => $list) {
                $yy = $y;
                foreach ($list as $n) {
                    if ($yy > $l->ph - $this->b - 30 * $mm) {
                        break;
                    }
                    $l->text($x + $c * ($w / 2), $yy + 10, $l->fit($n, 'serif', 10.5, $w / 2 - 10), 'serif', 10.5, 'cream');
                    $yy += 15;
                }
            }
        }
        if ($note !== '') {
            $l->drawLines($l->wrap([Layout::run($note, 'serif-i', 11, 'cream')], $w, 1.4), $x, $l->ph - $this->b - 34 * $mm, $w);
        }
        $l->text($x, $l->ph - $this->b - 14 * $mm, 'D’après la fiche du match au musée Sochaux Rétro', 'serif-i', 8, 'mist');
    }


    /** Match d'une feuille de match de l'association (pas encore de fiche au musée), au format des fiches. */
    public static function fromSheet(array $f): array
    {
        $rows = array_map(fn ($r) => ['position' => $r['position'] ?? '', 'name' => $r['name'] ?? '', 'captain' => !empty($r['captain'])], (array) ($f['lineup'] ?? []));
        return ['date' => $f['date'] ?? '', 'competition_label' => $f['competition'] ?? '', 'round_text' => $f['round'] ?? '',
            'home' => ['name' => preg_replace('/\s*\(.*\)$/u', '', (string) ($f['home'] ?? ''))], 'away' => ['name' => preg_replace('/\s*\(.*\)$/u', '', (string) ($f['away'] ?? ''))],
            'score' => ['home' => $f['score_home'] ?? '', 'away' => $f['score_away'] ?? ''], 'stadium' => $f['stadium'] ?? '', 'spectators' => $f['spectators'] ?? 0,
            'referee' => $f['referee'] ?? '', 'goals' => array_map(fn ($g) => ['team' => $g['team'] ?? '', 'scorers' => $g['text'] ?? ''], (array) ($f['goals_by_team'] ?? [])),
            'lineup' => ['rows' => $rows]];
    }

    /** Option « Mes joueurs » : jusqu'à trois portraits (photo assez définie, sinon blason), avec leur bilan. */
    private function myPlayers(array $ids): void
    {
        $docs = array_values(array_filter(array_map(fn ($id) => self::fiche($id, 'personne'), $ids)));
        if (!$docs) {
            return;
        }
        $l = $this->l;
        $mm = self::MM;
        $p = $this->page();
        $this->noFolio[$p] = true;
        $l->rect(0, 0, $l->pw, $l->ph, 'cream');
        [$x, $w] = $this->frame($p);
        $y = $this->b + 22 * $mm;
        $l->text($x, $y, 'MES JOUEURS', 'display-b', 11, 'B48D00', 3);
        $l->text($x, $y + 40, count($docs) > 1 ? 'MES LIONCEAUX PRÉFÉRÉS' : 'MON LIONCEAU PRÉFÉRÉ', 'display', 34, 'navy', 0.3);
        $y += 64;
        $n = count($docs);
        $gap = 8 * $mm;
        $cw = ($w - $gap * ($n - 1)) / $n;
        $ph = min($cw * 1.3, 110 * $mm);
        foreach ($docs as $i => $d) {
            $cx = $x + $i * ($cw + $gap);
            // Première photo assez nette ; presse_joueurs : exemplaire privé (cadeau) uniquement, jamais en vente
            $img = null;
            $list = $this->photos($d);
            if (!empty($this->o['presse_joueurs'])) {
                $list = array_merge($list, $this->photos($d, false, true));
            }
            foreach ($list as $photo) {
                if ($img = $this->prepare($photo['rel'], $cw, $ph, self::DPI['colonne'])) {
                    $this->mark($photo['rel']);
                    break;
                }
            }
            $l->rect($cx, $y, $cw, $ph, 'navy');
            if ($img) {
                $l->drawImage($l->loadImage($img['file']), $cx, $y, $cw, $ph, true);
            } else {
                $l->logo($cx + $cw / 2 - 40, $y + $ph / 2 - 50, 100);
            }
            $l->rect($cx, $y + $ph, $cw, 4, 'yellow');
            $name = trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', (string) ($d['title'] ?? '')));
            $yy = $y + $ph + 12;
            $yy += $l->drawLines($l->wrap([Layout::run(mb_strtoupper($name), 'display', 17, 'navy', null, 0.4)], $cw, 1.05), $cx, $yy, $cw) + 4;
            $bil = \App\Services\Bilans::forPerson((int) $d['id']);
            if ($bil && $bil['total']['matches'] > 0) {
                $t = $bil['total'];
                $line = $t['matches'] . ' match' . ($t['matches'] > 1 ? 's' : '') . ' · ' . $t['goals'] . ' but' . ($t['goals'] > 1 ? 's' : '') . ' · ' . $t['seasons'] . ' saison' . ($t['seasons'] > 1 ? 's' : '');
                $l->text($cx, $yy + 10, $line, 'display-b', 9.5, 'B48D00', 0.8);
                $yy += 16;
            }
            $kf = trim((string) ($d['key_figure']['text'] ?? ''));
            if ($kf !== '') {
                $l->drawLines(array_slice($l->wrap([Layout::run($kf, 'serif-i', 9.5, 'ink')], $cw, 1.35), 0, 7), $cx, $yy, $cw);
            }
        }
    }

    /** Ouverture de décennie : une page de droite, photo d'époque à fonds perdus si elle est assez définie. */
    private function decade(int $dec, array $items): void
    {
        $l = $this->l;
        if ($this->right($l->pdf->pageCount())) {
            // La prochaine page serait à droite : on intercale une page de gauche.
        } else {
            $this->blank();
        }
        $this->page();
        $this->noFolio[$l->page] = true;
        $this->dark[$l->page] = true;
        $p = $l->page;
        $label = 'Les années ' . $dec;
        $this->toc[] = ['t' => $label, 'page' => $p, 'level' => 0];
        $l->pdf->outline($label, $p, $l->ph);
        $l->rect(0, 0, $l->pw, $l->ph, 'navy');
        $img = null;
        foreach ($items as $it) {
            foreach ($this->photos($it['doc'], true) as $ph) {
                if ($img = $this->prepare($ph['rel'], $l->pw * 0.5, $l->ph, self::DPI['page'])) {
                    $img += $ph;
                    $this->mark($ph['rel']);
                    break 2;
                }
            }
        }
        if ($img) {
            $l->drawImage($l->loadImage($img['file']), $l->pw * 0.5, 0, $l->pw * 0.5, $l->ph, true);
            $this->report['page']++;
            $cap = $l->wrap([Layout::run($this->caption($img), 'serif-i', 8, 'mist')], $l->pw * 0.5 - 30 * self::MM, 1.3);
            $l->drawLines($cap, $l->pw * 0.5 + 12 * self::MM, $l->ph - $this->b - 16 * self::MM, $l->pw * 0.5 - 30 * self::MM);
        } else {
            $this->stripes($l->pw * 0.5, 0, $l->pw * 0.5, $l->ph);
        }
        $x = $this->b + 22 * self::MM;
        $l->text($x, $this->b + 34 * self::MM, mb_strtoupper($label), 'display-b', 11, 'cream', 3);
        $l->text($x - 4, $this->b + 34 * self::MM + 140, (string) $dec, 'display', 150, 'yellow');
        $y = $this->b + 120 * self::MM;
        $w = $l->pw * 0.5 - $x - 10 * self::MM;
        foreach ($items as $it) {
            $t = $this->title($it['doc']);
            $lines = $l->wrap([Layout::run(mb_strtoupper($t), 'display-b', 11, 'white', null, 0.5)], $w - 30, 1.35);
            if ($y + count($lines) * 15 > $l->ph - $this->b - 20 * self::MM) {
                break;
            }
            $l->text($x, $y + 11, sprintf('%02d', $it['n']), 'display-b', 11, 'yellow', 0.5);
            $y += $l->drawLines($lines, $x + 30, $y, $w - 30) + 3;
        }
    }

    private function recit(array $it, int $dec): void
    {
        $l = $this->l;
        $doc = $it['doc'];
        $title = $this->title($doc);
        $era = Recit::eraOf((string) ($doc['title'] ?? ''));
        [$lead, $blocks, $sources] = $this->content($doc);
        $photos = $this->photos($doc);
        $own = array_flip(array_column($photos, 'rel'));
        foreach ($this->related($doc) as $p) {
            if (!isset($own[$p['rel']])) {
                $photos[] = $p;
            }
        }
        $this->report['recits']++;
        $this->runLeft = 'Les années ' . $dec;
        $this->runRight = $title;
        $placed = 0;

        // Ouverture : photo pleine page à gauche si elle le mérite, sinon bandeau, sinon rien.
        $next = $l->pdf->pageCount();
        $full = null;
        if (!$this->right($next)) {
            foreach ($photos as $k => $p) {
                if ($full = $this->prepare($p['rel'], $l->pw, $l->ph, self::DPI['page'])) {
                    $full += $p;
                    $this->mark($p['rel']);
                    unset($photos[$k]);
                    break;
                }
            }
        }
        if ($full) {
            $this->page();
            $this->noFolio[$l->page] = true;
            $l->drawImage($l->loadImage($full['file']), 0, 0, $l->pw, $l->ph, true);
            $this->captionBox($full, $this->b + 12 * self::MM, $l->ph - $this->b - 20 * self::MM);
            $this->report['page']++;
            $placed++;
        }
        // Récit court qui précède : le suivant commence sur la même page s'il reste de la place
        // (texte précédent fini dans la colonne de gauche, plus de 45 % de la page libre).
        $shared = !$full && $this->inText && $this->col === 0 && $this->bottomY - $this->y > 0.45 * $this->H;
        if ($shared) {
            $p = $l->page;
            [$x, $w] = $this->frame($p);
            $y = $this->y + 8 * self::MM;
            $l->rect($x, $y, $w, 1.2, 'navy');
            $l->rect($x, $y - 1.2, 22, 3.6, 'yellow');
            $y += 8 * self::MM;
        } else {
            $p = $this->page();
            [$x, $w] = $this->frame($p);
            $y = $this->b + 18 * self::MM;
        }
        $this->toc[] = ['t' => $title, 'page' => $p, 'level' => 1, 'n' => $it['n']];
        $l->pdf->outline($title, $p, $l->ph - $y, 1);
        if (!$full && !$shared) {
            foreach ($photos as $k => $ph) {
                $band = $this->prepare($ph['rel'], $l->pw, $this->b + 118 * self::MM, self::DPI['bandeau']);
                if ($band) {
                    $this->mark($ph['rel']);
                    $l->drawImage($l->loadImage($band['file']), 0, 0, $l->pw, $this->b + 118 * self::MM, true);
                    $cap = $this->caption($ph);
                    $y = $this->b + 118 * self::MM + 5;
                    if ($cap !== '') {
                        $y += $l->drawLines($l->wrap([Layout::run($cap, 'serif-i', 7.8, 'muted')], $w, 1.3), $x, $y, $w);
                    }
                    $y += 8 * self::MM;
                    unset($photos[$k]);
                    $this->report['bandeau']++;
                    $placed++;
                    break;
                }
            }
        }
        // Marge droite du titre : QR code vers la page du récit au musée (vidéos, photos, récit lu),
        // et tampon « J'y étais » si un match raconté est dans le carnet du lecteur.
        $stamp = $this->seen && array_intersect($this->linked($doc), array_keys($this->seen));
        $qr = ($this->o['qr'] ?? true) && !empty($doc['path']);
        if ($qr || $stamp) {
            $mw = ($stamp ? 32 : 16) * self::MM;
            $mx = $x + $w - $mw;
            if ($qr) {
                $this->qr(rtrim((string) setting('general.base_url', 'https://musee.fcsochauxretro.com'), '/') . $doc['path'], $x + $w - 16 * self::MM, $y + 16, 16 * self::MM);
            }
            if ($stamp) {
                $this->stamped[] = ['t' => $title, 'page' => $l->page];
                $sx = $mx;
                $sy = $y + 16 + ($qr ? 16 * self::MM + 14 : 0);
                $l->rotated(-8, $sx + 41, $sy + 14, function () use ($l, $sx, $sy) {
                    $l->rect($sx, $sy, 82, 28, null, 'red', 2);
                    $l->rect($sx + 3, $sy + 3, 76, 22, null, 'red', 0.7);
                    $l->text($sx + 41 - $l->width('J’Y ÉTAIS', 'display', 15, 1.5) / 2, $sy + 20, 'J’Y ÉTAIS', 'display', 15, 'red', 1.5);
                });
            }
            $w -= $mw + 4 * self::MM;
        }
        $kick = 'RÉCIT ' . $it['n'] . ($era !== '' ? ' · ' . mb_strtoupper(str_replace('-', '–', $era)) : '');
        $l->text($x, $y + 10, $kick, 'display-b', 10, 'B48D00', 2.2);
        if (($doc['status'] ?? '') !== 'publie' && empty($this->o['sans_bandeau'])) {
            $t = 'À RELIRE';
            $tw = $l->width($t, 'display-b', 8, 1.2) + 10;
            $l->rect($x + $w - $tw, $y, $tw, 14, 'red');
            $l->text($x + $w - $tw + 5, $y + 10.2, $t, 'display-b', 8, 'white', 1.2);
        }
        $y += 18;
        $tl = $l->wrap([Layout::run(mb_strtoupper($title), 'display', 36, 'navy', null, 0.2)], $w, 0.95);
        $y += $l->drawLines($tl, $x, $y, $w) + 10;
        if ($lead !== '') {
            $ll = $l->wrap([Layout::run($lead, 'serif-i', 13, 'ink')], $w - 14, 1.45);
            $h = $l->drawLines($ll, $x + 14, $y, $w - 14);
            $l->rect($x, $y + 2, 3, $h - 4, 'yellow');
            $y += $h + 16;
        }
        [, $w] = $this->frame($l->page);
        // Grande photo sous le titre si l'ouverture n'en a pas (définition « pleine largeur »).
        if (!$placed) {
            foreach ($photos as $k => $ph) {
                $hh = min(100 * self::MM, $this->bottom() - $y - 120);
                if ($hh < 50 * self::MM) {
                    break;
                }
                if ($big = $this->prepare($ph['rel'], $w, $hh, self::DPI['large'])) {
                    $this->mark($ph['rel']);
                    $l->drawImage($l->loadImage($big['file']), $x, $y, $w, $hh, true);
                    $y += $hh + 4;
                    $cap = $this->caption($ph);
                    if ($cap !== '') {
                        $y += $l->drawLines($l->wrap([Layout::run($cap, 'serif-i', 7.8, 'muted')], $w, 1.3), $x, $y, $w);
                    }
                    $y += 12;
                    unset($photos[$k]);
                    $this->report['large']++;
                    $placed++;
                    break;
                }
            }
        }
        $this->columns($y);
        // Texte : intertitres et paragraphes, une photo d'époque après chaque chapitre.
        $chapter = 0;
        foreach ($blocks as $i => $bl) {
            if ($bl['k'] === 'h') {
                $chapter++;
                if ($chapter > 1 && $photos) {
                    $placed += $this->columnPhoto($photos);
                }
                $this->heading($chapter, $bl['t']);
                continue;
            }
            $this->flow($bl['runs'], 9.8, 1.48, 5, $bl['k'] === 'li');
        }
        while ($photos && $placed < GrandsRecits::MAX_PHOTOS) {
            $n = $this->columnPhoto($photos);
            if (!$n) {
                break;
            }
            $placed += $n;
        }
        if ($sources !== []) {
            $this->space(6);
            $this->flow([Layout::run('SOURCES', 'display-b', 7.6, 'muted', null, 1.4)], 7.6, 1.3, 2);
            foreach ($sources as $s) {
                $this->flow([Layout::run($s, 'serif', 7.6, 'muted')], 7.6, 1.32, 2);
            }
        }
        $this->inText = true;
        if (!$placed) {
            $this->report['sans_photo'][] = $title;
        }
        $this->report['ecartees'] += count($photos);
    }

    // ------------------------------------------------------------------ texte en deux colonnes

    private function bottom(): float
    {
        return $this->b + $this->H - 22 * self::MM;
    }

    private function columns(float $top): void
    {
        $this->top = $top;
        $this->bottomY = $this->bottom();
        $this->col = 0;
        $this->y = $top;
    }

    /** @return array{0:float,1:float} x et largeur de la colonne courante */
    private function colBox(): array
    {
        [$x, $w] = $this->frame($this->l->page);
        $gap = 7 * self::MM;
        $cw = ($w - $gap) / 2;
        return [$x + $this->col * ($cw + $gap), $cw];
    }

    /** Place pour $h points, sinon colonne ou page suivante (bandeau courant sur les pages de suite). */
    private function need(float $h): void
    {
        if ($this->y + $h <= $this->bottomY) {
            return;
        }
        if ($this->col === 0) {
            $this->col = 1;
            $this->y = $this->top;
            return;
        }
        $this->page();
        $this->running();
        $this->columns($this->b + 20 * self::MM);
        $this->inText = true;
    }

    private function space(float $h): void
    {
        if ($this->y > $this->top) {
            $this->y = min($this->y + $h, $this->bottomY);
        }
    }

    private function flow(array $runs, float $size, float $lh, float $after, bool $bullet = false): void
    {
        [, $cw] = $this->colBox();
        $indent = $bullet ? 9 : 0;
        $lines = $this->l->wrap($runs, $cw - $indent, $lh);
        foreach ($lines as $i => $ln) {
            $this->need($ln['h']);
            [$x, $cw] = $this->colBox();
            if ($bullet && $i === 0) {
                $this->l->rect($x + 1, $this->y + $ln['h'] / 2 - 1.5, 3, 3, 'yellow');
            }
            $this->l->drawLines([$ln], $x + $indent, $this->y, $cw - $indent, count($lines) > 1 && $i < count($lines) - 1 ? 'left' : 'left');
            $this->y += $ln['h'];
        }
        $this->y += $after;
    }

    private function heading(int $n, string $t): void
    {
        [, $cw] = $this->colBox();
        $lines = $this->l->wrap([Layout::run(self::roman($n) . '  ', 'display-b', 12, 'B48D00', null, 0.6), Layout::run(mb_strtoupper($t), 'display-b', 12, 'navy', null, 0.6)], $cw, 1.2);
        $h = array_sum(array_column($lines, 'h'));
        $this->space(6);
        $this->need($h + 40);
        [$x, $cw] = $this->colBox();
        $this->y += $this->l->drawLines($lines, $x, $this->y, $cw) + 3;
    }

    /** Une photo à la largeur d'une colonne (200 dpi au moins), recadrée en 3:2 au plus haut. */
    private function columnPhoto(array &$photos): int
    {
        [, $cw] = $this->colBox();
        foreach ($photos as $k => $ph) {
            $d = $this->measure($ph['rel']);
            if (!$d) {
                unset($photos[$k]);
                continue;
            }
            $h = min($cw * $d[1] / $d[0], $cw * 1.1);
            $h = max($h, $cw * 0.56);
            $img = $this->prepare($ph['rel'], $cw, $h, self::DPI['colonne']);
            if (!$img) {
                continue;
            }
            $this->mark($ph['rel']);
            $cap = $this->caption($ph);
            $capL = $cap !== '' ? $this->l->wrap([Layout::run($cap, 'serif-i', 7.6, 'muted')], $cw, 1.28) : [];
            $capH = array_sum(array_column($capL, 'h'));
            $this->space(4);
            $this->need($h + $capH + 10);
            [$x, $cw2] = $this->colBox();
            $this->l->drawImage($this->l->loadImage($img['file']), $x, $this->y, $cw2, $h, true);
            $this->y += $h + 3;
            if ($capL) {
                $this->y += $this->l->drawLines($capL, $x, $this->y, $cw2);
            }
            $this->y += 8;
            unset($photos[$k]);
            $this->report['colonne']++;
            return 1;
        }
        return 0;
    }

    private function running(): void
    {
        $l = $this->l;
        $p = $l->page;
        [$x, $w] = $this->frame($p);
        $y = $this->b + 11 * self::MM;
        $t = mb_strtoupper($this->right($p) ? $this->runRight : $this->runLeft);
        $t = $l->fit($t, 'display-b', 8, $w, 1.6);
        $tx = $this->right($p) ? $x + $w - $l->width($t, 'display-b', 8, 1.6) : $x;
        $l->text($tx, $y, $t, 'display-b', 8, 'muted', 1.6);
    }

    private function folios(): void
    {
        $l = $this->l;
        $total = $l->pdf->pageCount();
        if (!empty($this->o['apercu'])) {
            // Extrait feuilleté en boutique : bandeau sur chaque page.
            for ($p = 0; $p < $total; $p++) {
                $l->page = $p;
                $t = 'EXTRAIT · APERÇU DE VOTRE LIVRE · LE LIVRE COMPLET COMPTE ENVIRON 140 PAGES';
                $w = $l->width($t, 'display-b', 7.5, 1.2) + 16;
                $l->rect($l->pw / 2 - $w / 2, $this->b + 2 * self::MM, $w, 14, 'red');
                $l->text($l->pw / 2 - $w / 2 + 8, $this->b + 2 * self::MM + 10, $t, 'display-b', 7.5, 'white', 1.2);
            }
        }
        for ($p = 0; $p < $total; $p++) {
            if (!empty($this->noFolio[$p])) {
                continue;
            }
            $l->page = $p;
            [$x, $w] = $this->frame($p);
            $t = (string) ($p + 1);
            $y = $this->b + $this->H - 11 * self::MM;
            $tx = $this->right($p) ? $x + $w - $l->width($t, 'display-b', 9, 1.2) : $x;
            $l->text($tx, $y, $t, 'display-b', 9, !empty($this->dark[$p]) ? 'mist' : 'muted', 1.2);
        }
    }

    private function tocDraw(int $first, int $pages): void
    {
        $l = $this->l;
        $per = (int) ceil(count($this->toc) / $pages);
        foreach (array_chunk($this->toc, max(1, $per)) as $i => $rows) {
            $l->page = $first + $i;
            [$x, $w] = $this->frame($l->page);
            $y = $this->b + 22 * self::MM;
            if ($i === 0) {
                $l->text($x, $y + 34, 'SOMMAIRE', 'display', 40, 'navy', 0.4);
                $y += 58;
            }
            foreach ($rows as $r) {
                $pg = (string) ($r['page'] + 1);
                if ($r['level'] === 0) {
                    $y += 8;
                    $l->text($x, $y + 12, mb_strtoupper($r['t']), 'display', 13, 'B48D00', 0.8);
                    $l->text($x + $w - $l->width($pg, 'display-b', 10), $y + 12, $pg, 'display-b', 10, 'muted');
                    $y += 20;
                    continue;
                }
                $num = sprintf('%02d', $r['n']);
                $l->text($x, $y + 10, $num, 'display-b', 9.5, 'muted');
                $t = $l->fit($r['t'], 'serif', 10, $w - 70);
                $tw = $l->text($x + 24, $y + 10, $t, 'serif', 10, 'ink');
                $pw = $l->width($pg, 'serif', 10);
                for ($dx = $x + 30 + $tw; $dx < $x + $w - $pw - 8; $dx += 4) {
                    $l->rect($dx, $y + 9, 0.8, 0.8, 'mist');
                }
                $l->text($x + $w - $pw, $y + 10, $pg, 'serif', 10, 'ink');
                $y += 14.5;
            }
        }
    }

    private function back(): void
    {
        $l = $this->l;
        $mm = self::MM;
        $b = $this->b;
        $this->page();
        $this->noFolio[$l->page] = true;
        $l->rect(0, 0, $l->pw, $l->ph, 'navy');
        $this->stripes(0, 0, $l->pw, $l->ph);
        $in = $b + 9 * $mm;
        $l->rect($in, $in, $l->pw - 2 * $in, $l->ph - 2 * $in, null, 'yellow', 1);
        $x = $in + 12 * $mm;
        $w = $l->pw - 2 * $x;
        $y = $in + 22 * $mm;
        // Accroche
        $l->text($x, $y + 34, 'CENT HISTOIRES,', 'display', 40, 'white', 0.4);
        $l->text($x, $y + 74, 'UN SEUL CLUB.', 'display', 40, 'yellow', 0.4);
        $y += 98;
        $txt = 'De la Forge à Bonal, des Peugeot aux socios : cent récits racontent le FC Sochaux-Montbéliard, des premiers pas chez les pros aux titres de champion, des finales de Coupe à l’épopée européenne, jusqu’au sauvetage de 2023 et au-delà. Rassemblés par les historiens bénévoles du musée Sochaux Rétro, d’après les feuilles de match, les bilans et les archives du club, et illustrés par leurs photos.';
        $y += $l->drawLines($l->wrap([Layout::run($txt, 'serif', 12, 'cream')], $w, 1.6), $x, $y, $w) + 26;
        // Frise des décennies
        $decs = array_values(array_map(fn ($t) => (int) preg_replace('/\D/', '', $t['t']), array_filter($this->toc, fn ($t) => $t['level'] === 0)));
        if ($decs) {
            $l->text($x, $y, 'AU FIL DES DÉCENNIES', 'display-b', 9, 'yellow', 2.2);
            $y += 22;
            $l->line($x, $y, $x + $w, $y, 'yellow', 1.2);
            $n = count($decs);
            foreach ($decs as $i => $d) {
                $cx = $x + ($n > 1 ? $i * $w / ($n - 1) : 0);
                $l->rect($cx - 4, $y - 4, 8, 8, 'yellow');
                $t = "'" . substr((string) $d, 2);
                $l->text($cx - $l->width($t, 'display-b', 11) / 2, $y + 20, $t, 'display-b', 11, 'white');
            }
            $y += 44;
        }
        // Bande de photos d'époque (une par décennie, assez définies pour leur petite taille)
        $shots = [];
        foreach ($this->recits() as $items) {
            foreach ($items as $it) {
                foreach ($this->photos($it['doc'], false, false, true) as $ph) {
                    if ($img = $this->prepare($ph['rel'], ($w - 3 * 6) / 4, 30 * $mm, self::DPI['colonne'])) {
                        $shots[] = $img;
                        continue 3;
                    }
                }
            }
        }
        if (count($shots) >= 4) {
            $pick = array_values(array_intersect_key($shots, array_flip(array_map(fn ($i) => (int) round($i * (count($shots) - 1) / 3), [0, 1, 2, 3]))));
            $sw = ($w - 3 * 6) / 4;
            foreach ($pick as $i => $img) {
                $sx = $x + $i * ($sw + 6);
                $l->drawImage($l->loadImage($img['file']), $sx, $y, $sw, 30 * $mm, true);
                $l->rect($sx, $y + 30 * $mm, $sw, 3, 'yellow');
            }
            $y += 30 * $mm + 26;
        }
        // Dédicace de l'exemplaire
        $nom = trim((string) ($this->o['nom'] ?? ''));
        if ($nom !== '') {
            $runs = [Layout::run('Cet exemplaire' . (($num = trim((string) ($this->o['numero'] ?? ''))) !== '' ? ' n° ' . $num : '') . ' a été composé pour ', 'serif-i', 12, 'cream'), Layout::run($nom, 'serif-b', 12, 'yellow')];
            if (($dep = (int) ($this->o['depuis'] ?? 0)) >= 1928) {
                $runs[] = Layout::run(', supporter depuis ' . $dep, 'serif-i', 12, 'cream');
            }
            $runs[] = Layout::run('.', 'serif-i', 12, 'cream');
            $lines = $l->wrap($runs, $w - 20, 1.5);
            $h = array_sum(array_column($lines, 'h')) + 20;
            $l->rect($x, $y, 4, $h, 'yellow');
            $l->drawLines($lines, $x + 16, $y + 10, $w - 20);
        }
        // Pied : blason, adresse, emplacement du code-barres
        $fy = $l->ph - $in - 14 * $mm - 60;
        $l->logo($x, $fy, 64);
        $l->text($x + 60, $fy + 28, 'MUSÉE SOCHAUX RÉTRO', 'display-b', 11, 'white', 2);
        $l->text($x + 60, $fy + 44, 'musee.fcsochauxretro.com', 'serif', 10, 'mist');
        $bw = 44 * $mm;
        $bh = 25 * $mm;
        $l->rect($x + $w - $bw, $fy + 64 - $bh, $bw, $bh, 'white');
        $isbn = trim((string) ($this->o['isbn'] ?? ''));
        $t = $isbn !== '' ? 'ISBN ' . $isbn : 'CODE-BARRES';
        $l->text($x + $w - $bw / 2 - $l->width($t, 'display-b', 8, 1) / 2, $fy + 64 - $bh / 2 + 3, $t, 'display-b', 8, 'muted', 1);
    }

    // ------------------------------------------------------------------ contenu

    private function title(array $doc): string
    {
        $t = trim(Recit::stripEra((string) ($doc['title'] ?? '')));
        $t = trim((string) preg_replace('/\s+\d{4}(\s*[–-]\s*\d{4})?$/u', '', $t));
        return $t !== '' ? $t : (string) ($doc['title'] ?? '');
    }

    /** Chapô, blocs de texte (intertitres h, paragraphes p, puces li) et sources. */
    private function content(array $doc): array
    {
        $lead = trim((string) ($doc['intro'] ?? ''));
        $blocks = [];
        $sources = [];
        foreach ((array) ($doc['sections'] ?? []) as $i => $s) {
            $t = trim((string) ($s['title'] ?? ''));
            $html = (string) ($s['html'] ?? '');
            if ($t === 'Sources') {
                foreach ($this->blocks($html, 7.6) as $bl) {
                    $sources[] = trim(implode('', array_column($bl['runs'], 't')));
                }
                continue;
            }
            if ($t === '') {
                // Chapô en tête de récit (déjà affiché) : le paragraphe identique est sauté.
                $plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
                if ($i === 0 && ($lead === '' || $plain === $lead)) {
                    $lead = $lead ?: $plain;
                    continue;
                }
            } else {
                $blocks[] = ['k' => 'h', 't' => $t];
            }
            foreach ($this->blocks($html, 9.8) as $bl) {
                $blocks[] = $bl;
            }
        }
        return [$lead, $blocks, array_values(array_filter($sources))];
    }

    /** HTML simple → paragraphes en fragments (italique, gras). @return list<array{k:string,runs:list<array>}> */
    private function blocks(string $html, float $size): array
    {
        $out = [];
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        $root = $dom->getElementsByTagName('div')->item(0);
        if (!$root) {
            return [];
        }
        $walk = function (\DOMNode $n, string $font, array &$runs) use (&$walk, $size) {
            foreach ($n->childNodes as $c) {
                if ($c instanceof \DOMText) {
                    $t = preg_replace('/\s+/u', ' ', $c->textContent);
                    if ($t !== '') {
                        $runs[] = Layout::run($t, $font, $size, 'ink');
                    }
                } elseif ($c instanceof \DOMElement) {
                    $tag = strtolower($c->tagName);
                    $f = in_array($tag, ['em', 'i'], true) ? 'serif-i' : (in_array($tag, ['strong', 'b'], true) ? 'serif-b' : $font);
                    if ($tag === 'br') {
                        $runs[] = Layout::run("\n", $font, $size);
                        continue;
                    }
                    $walk($c, $f, $runs);
                }
            }
        };
        $add = function (string $k, \DOMNode $n, string $font = 'serif') use (&$out, $walk) {
            $runs = [];
            $walk($n, $font, $runs);
            if ($runs && trim(implode('', array_column($runs, 't'))) !== '') {
                $runs[0]['t'] = ltrim($runs[0]['t']);
                $out[] = ['k' => $k, 'runs' => $runs];
            }
        };
        foreach ($root->childNodes as $c) {
            if (!$c instanceof \DOMElement) {
                if (trim($c->textContent) !== '') {
                    $add('p', $c);
                }
                continue;
            }
            $tag = strtolower($c->tagName);
            if (in_array($tag, ['ul', 'ol'], true)) {
                foreach ($c->childNodes as $li) {
                    if ($li instanceof \DOMElement) {
                        $add('li', $li);
                    }
                }
            } elseif (preg_match('/^h[2-6]$/', $tag)) {
                $out[] = ['k' => 'h', 't' => trim($c->textContent)];
            } elseif ($tag === 'blockquote') {
                $add('p', $c, 'serif-i');
            } elseif (!in_array($tag, ['figure', 'img', 'iframe', 'script', 'style'], true)) {
                $add('p', $c);
            }
        }
        return $out;
    }

    /** Clés d'identité d'une photo : nom sans variantes WordPress (-scaled, -1024x683, -1…) et empreinte du fichier. */
    private function photoKeys(string $rel): array
    {
        static $md5 = [];
        $base = strtolower((string) pathinfo($rel, PATHINFO_FILENAME));
        $base = (string) preg_replace('/(-scaled|-rotated|-e\d{10,}|-\d+x\d+|-\d{1,2})+$/', '', $base);
        $keys = ['n:' . $base];
        if (!array_key_exists($rel, $md5)) {
            $f = Media::file($rel);
            $md5[$rel] = $f ? (string) @md5_file($f) : '';
        }
        if ($md5[$rel] !== '') {
            $keys[] = 'h:' . $md5[$rel];
        }
        return $keys;
    }

    private function isUsed(string $rel): bool
    {
        foreach ($this->photoKeys($rel) as $k) {
            if (isset($this->used[$k])) {
                return true;
            }
        }
        return false;
    }

    private function mark(string $rel): void
    {
        foreach ($this->photoKeys($rel) as $k) {
            $this->used[$k] = true;
        }
    }

    /**
     * Photos d'autres fiches du musée liées au récit, pour varier les images : fiches citées en lien,
     * joueurs nommés dans le texte, matchs de l'époque contre un adversaire nommé (les plus proches d'abord).
     */
    private function related(array $doc, int $max = 14): array
    {
        static $idx = null;
        if ($idx === null) {
            $idx = ['path' => [], 'people' => [], 'matches' => []];
            foreach (\App\Data\Index::all() as $e) {
                if (empty($e['image'])) {
                    continue;
                }
                $id = (int) $e['id'];
                $idx['path'][(string) ($e['path'] ?? '')] = $id;
                $title = (string) ($e['title'] ?? '');
                if (($e['type'] ?? '') === 'personne') {
                    $name = trim((string) preg_replace('/\s*\(.*$/u', '', $title));
                    if (mb_strlen($name) >= 7 && str_contains($name, ' ')) {
                        $idx['people'][mb_strtolower($name)] = $id;
                    }
                } elseif (($e['type'] ?? '') === 'match' && preg_match('#-(\d{2})-(\d{2})-(\d{4})/$#', (string) $e['path'], $dm)
                    && preg_match('#([^–/]+?)\s*/\s*([^–/]+?)\s*–#u', $title, $tm)) {
                    $opp = stripos($tm[1], 'sochaux') !== false ? $tm[2] : $tm[1];
                    $opp = mb_strtolower(trim($opp));
                    if (mb_strlen($opp) >= 4 && !str_contains($opp, 'sochaux')) {
                        $idx['matches'][] = ['id' => $id, 'opp' => $opp, 'y' => (int) $dm[3] + ((int) $dm[2] >= 7 ? 0.5 : 0)];
                    }
                }
            }
        }
        $html = implode(' ', array_column((array) ($doc['sections'] ?? []), 'html'));
        $text = mb_strtolower(html_entity_decode(strip_tags($html . ' ' . ($doc['intro'] ?? '') . ' ' . ($doc['title'] ?? '')), ENT_QUOTES, 'UTF-8'));
        $ids = [];
        $people = [];
        preg_match_all('#href="(?:https?://musee\.fcsochauxretro\.com)?(/[^"\#?]+/)"#', $html, $mm);
        foreach (array_unique($mm[1] ?? []) as $path) {
            if (isset($idx['path'][$path])) {
                $ids[] = $idx['path'][$path];
            }
        }
        foreach ($idx['people'] as $name => $id) {
            if (str_contains($text, $name)) {
                $ids[] = $id;
                $people[$id] = true;
            }
        }
        $era = Recit::eraOf((string) ($doc['title'] ?? ''));
        $y0 = $y1 = null;
        if (preg_match('/(\d{4})(?:\D+(\d{4}))?/', $era, $ym)) {
            $y0 = (int) $ym[1];
            $y1 = (int) ($ym[2] ?? $ym[1]) + 1;
            $near = [];
            foreach ($idx['matches'] as $m) {
                if ($m['y'] >= $y0 && $m['y'] <= $y1 && str_contains($text, $m['opp'])) {
                    $near[] = $m['id'];
                }
            }
            $ids = array_merge($ids, $near);
        }
        $out = [];
        foreach (array_unique($ids) as $id) {
            $d = $id !== (int) ($doc['id'] ?? 0) ? Fiches::get($id) : null;
            if (!$d || ($d['status'] ?? '') !== 'publie') {
                continue;
            }
            foreach ($this->photos($d) as $p) {
                // Pas d'anachronisme : photo datée hors de l'époque du récit écartée ; pour un joueur
                // (carrière longue), la photo doit être datée et de l'époque.
                $yr = $p['year'] ?? null;
                if ($y0 !== null && ($yr !== null ? ($yr < $y0 - 2 || $yr > $y1 + 2) : isset($people[$id]))) {
                    continue;
                }
                $out[$p['rel']] = $p;
                if (count($out) >= $max) {
                    return array_values($out);
                }
            }
        }
        return array_values($out);
    }

    /** Photos du récit, image principale d'abord ; jamais la presse. @return list<array{rel:string,caption:string,credit:string}> */
    private function photos(array $doc, bool $archivesFirst = false, bool $press = false, bool $reuse = false): array
    {
        $list = [];
        $seen = [];
        $add = function (string $rel, string $cap, string $cred) use (&$list, &$seen, $press, $reuse) {
            if ($rel === '' || isset($seen[$rel]) || \App\Data\Index::isPlaceholderImage($rel) || (!$reuse && $this->isUsed($rel))) {
                return;
            }
            $seen[$rel] = true;
            $m = Media::get($rel) ?? [];
            $cap = $cap !== '' ? $cap : (string) ($m['caption'] ?? '');
            $cred = $cred !== '' ? $cred : (string) ($m['credit'] ?? '');
            if (!$press && GrandsRecits::press($cred . ' ' . $cap)) {
                $this->report['presse']++;
                return;
            }
            $list[] = ['rel' => $rel, 'caption' => $cap, 'credit' => $cred, 'year' => Recit::year($cap . ' ' . $rel)];
        };
        if (!empty($doc['featured_image'])) {
            $add((string) $doc['featured_image'], '', '');
        }
        foreach ((array) ($doc['gallery'] ?? []) as $g) {
            $add((string) ($g['image'] ?? ''), trim((string) ($g['caption'] ?? '')), trim((string) ($g['credit'] ?? '')));
        }
        if ($archivesFirst) {
            usort($list, fn ($a, $b) => ($a['year'] ?? 9999) <=> ($b['year'] ?? 9999));
        }
        return $list;
    }

    private function caption(array $p): string
    {
        $c = trim((string) ($p['caption'] ?? ''));
        $cr = trim((string) ($p['credit'] ?? ''));
        if ($cr !== '' && !str_contains(mb_strtolower($c), mb_strtolower($cr))) {
            $c .= ($c !== '' ? ' · ' : '') . 'Photo ' . $cr;
        }
        return $c;
    }

    private function captionBox(array $p, float $x, float $y): void
    {
        $cap = $this->caption($p);
        if ($cap === '') {
            return;
        }
        $l = $this->l;
        $w = 90 * self::MM;
        $lines = $l->wrap([Layout::run($cap, 'serif-i', 8, 'white')], $w - 16, 1.3);
        $h = array_sum(array_column($lines, 'h')) + 12;
        $l->rect($x, $y - $h, $w, $h, 'navy');
        $l->drawLines($lines, $x + 8, $y - $h + 6, $w - 16);
    }

    // ------------------------------------------------------------------ photos : définition et préparation

    /** Largeur et hauteur de l'image telle qu'elle sera imprimée (recadrage du back-office compris). @return array{0:int,1:int}|null */
    public function measure(string $rel): ?array
    {
        $src = Media::file($rel);
        if (!$src) {
            return null;
        }
        $key = $rel . '|' . (int) @filemtime($src) . '|' . md5(json_encode(Media::get($rel)['edit'] ?? null));
        if (isset($this->dims[$key])) {
            return $this->dims[$key] ?: null;
        }
        $d = null;
        if (empty(Media::get($rel)['edit'])) {
            $i = @getimagesize($src);
            $d = $i ? [(int) $i[0], (int) $i[1]] : null;
        }
        if (!$d) {
            $im = Images::open($src, Media::get($rel)['edit'] ?? null);
            $d = $im ? [imagesx($im), imagesy($im)] : null;
        }
        $this->dims[$key] = $d ?: false;
        return $d;
    }

    /** Points par pouce obtenus si l'image remplit un cadre de $w × $h points (recadrée). */
    public static function dpi(array $d, float $w, float $h): float
    {
        return 72 * min($d[0] / $w, $d[1] / $h);
    }

    /**
     * Image recadrée au format du cadre et réduite à 300 dpi, si sa définition atteint $min dpi.
     * @return array{file:string,dpi:float}|null
     */
    private function prepare(string $rel, float $w, float $h, int $min): ?array
    {
        $d = $this->measure($rel);
        if (!$d) {
            return null;
        }
        $dpi = self::dpi($d, $w, $h);
        if ($dpi < $min) {
            return null;
        }
        // Recadrage trop fort (photo en hauteur dans un bandeau, etc.) : têtes et pieds coupés, on refuse.
        $keep = min(($w / $h) / ($d[0] / $d[1]), ($d[0] / $d[1]) / ($w / $h));
        if ($min > 0 && $keep < self::KEEP) {
            return null;
        }
        // ecran : PDF à lire à l’écran (150 dpi), bien plus léger
        $res = !empty($this->o['ecran']) ? 150 : self::TARGET_DPI;
        $tw = (int) round($w / 72 * $res);
        $th = (int) round($h / 72 * $res);
        $file = $this->cache . '/' . md5($rel . '|' . json_encode($d) . "|$tw|$th") . '.jpg';
        if (!is_file($file)) {
            $src = Media::file($rel);
            $im = $src ? Images::open($src, Media::get($rel)['edit'] ?? null) : null;
            if (!$im) {
                return null;
            }
            $iw = imagesx($im);
            $ih = imagesy($im);
            // Recadrage au format du cadre (un peu au-dessus du centre : les visages)
            $s = min($iw / $tw, $ih / $th);
            $cw = (int) round($tw * $s);
            $ch = (int) round($th * $s);
            $cx = (int) (($iw - $cw) / 2);
            // Vertical : on garde le haut (les têtes) ; horizontal : centré.
            $cy = (int) (($ih - $ch) * 0.12);
            $ow = min($tw, $cw);
            $oh = min($th, $ch);
            $out = imagecreatetruecolor($ow, $oh);
            imagefill($out, 0, 0, (int) imagecolorallocate($out, 255, 255, 255));
            imagecopyresampled($out, $im, 0, 0, $cx, $cy, $ow, $oh, $cw, $ch);
            imagejpeg($out, $file, 90);
            imagedestroy($out);
            imagedestroy($im);
        }
        return ['file' => $file, 'dpi' => round($dpi)];
    }

    private static function roman(int $n): string
    {
        $map = ['X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $r = '';
        foreach ($map as $s => $v) {
            while ($n >= $v) {
                $r .= $s;
                $n -= $v;
            }
        }
        return $r;
    }
}
