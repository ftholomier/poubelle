<?php
declare(strict_types=1);

namespace App\Shop;

use App\Data\Collections;

/**
 * Boutique : supports (produits vierges de l'imprimeur : faces imprimables en mm, fonds perdus,
 * couleurs, tailles) et modèles (dessins posés sur un support, créés dans l'éditeur du
 * back-office). Supports de départ ci-dessous, modifiables et complétables par l'admin
 * (data/collections/boutique-supports.json) ; modèles : data/collections/boutique-modeles.json.
 */
final class Catalog
{
    public const SUPPORTS_FILE = 'boutique-supports';
    public const MODELS_FILE = 'boutique-modeles';

    /** Couleurs de textile courantes (nom => hexadécimal). */
    private const TEXTILE = ['Blanc' => '#FFFFFF', 'Jaune' => '#F6C400', 'Bleu nuit' => '#0E1F4D', 'Noir' => '#1A1A1A', 'Gris chiné' => '#B9BCC2'];
    private const SIZES = ['S', 'M', 'L', 'XL', 'XXL'];

    /**
     * Supports de départ. mockup : forme de l'aperçu (tee, hoodie, mug, tote, cap, scarf, paper,
     * sticker) ; faces : clé => [libellé, largeur, hauteur, fonds perdus] (mm) ; colors : couleurs
     * du produit vierge (vide : imprimé en entier, le fond fait partie du dessin) ; cost : coût de
     * fabrication indicatif chez l'imprimeur (centimes TTC), à ajuster dans Boutique › Supports.
     */
    public const DEFAULTS = [
        'tshirt' => ['name' => 'T-shirt', 'mockup' => 'tee', 'faces' => ['avant' => ['Avant', 280, 350, 0], 'dos' => ['Dos', 280, 350, 0]], 'colors' => self::TEXTILE, 'sizes' => self::SIZES, 'note' => 'Impression directe sur textile (DTG) ou transfert ; pas de fonds perdus.', 'cost' => 900],
        'sweat' => ['name' => 'Sweat à capuche', 'mockup' => 'hoodie', 'faces' => ['avant' => ['Avant', 250, 280, 0], 'dos' => ['Dos', 280, 350, 0]], 'colors' => ['Bleu nuit' => '#0E1F4D', 'Noir' => '#1A1A1A', 'Gris chiné' => '#B9BCC2', 'Jaune' => '#F6C400'], 'sizes' => self::SIZES, 'note' => '', 'cost' => 1800],
        'mug' => ['name' => 'Mug céramique', 'mockup' => 'mug', 'faces' => ['tour' => ['Tour complet', 200, 90, 2]], 'colors' => ['Blanc' => '#FFFFFF', 'Noir' => '#1A1A1A', 'Bleu nuit' => '#0E1F4D', 'Jaune' => '#F6C400'], 'sizes' => [], 'note' => 'Sublimation enroulée : la face visible est le centre du dessin, l’anse est aux deux bords.', 'cost' => 700],
        'mug-emaille' => ['name' => 'Mug émaillé', 'mockup' => 'mug', 'faces' => ['tour' => ['Tour complet', 200, 75, 2]], 'colors' => ['Blanc' => '#FFFFFF'], 'sizes' => [], 'note' => 'Liseré du bord en bleu ou en noir selon le modèle de l’imprimeur.', 'cost' => 900],
        'tote' => ['name' => 'Tote bag', 'mockup' => 'tote', 'faces' => ['face' => ['Face', 280, 300, 0]], 'colors' => ['Naturel' => '#EFE6D2', 'Noir' => '#1A1A1A', 'Bleu nuit' => '#0E1F4D'], 'sizes' => [], 'note' => '', 'cost' => 600],
        'casquette' => ['name' => 'Casquette', 'mockup' => 'cap', 'faces' => ['avant' => ['Face avant', 100, 55, 0]], 'colors' => ['Bleu nuit' => '#0E1F4D', 'Noir' => '#1A1A1A', 'Blanc' => '#FFFFFF', 'Jaune' => '#F6C400'], 'sizes' => ['Taille unique'], 'note' => 'Broderie : 6 couleurs au plus, pas de texte de moins de 5 mm de haut, pas de trait fin.', 'cost' => 900],
        'echarpe' => ['name' => 'Écharpe', 'mockup' => 'scarf', 'faces' => ['recto' => ['Recto', 1400, 180, 5]], 'colors' => [], 'sizes' => [], 'note' => 'Écharpe imprimée en entier (sublimation) : le fond fait partie du dessin, prévoir les franges aux deux bouts.', 'cost' => 1400],
        'poster' => ['name' => 'Poster (A4, A3, A2)', 'mockup' => 'paper', 'faces' => ['recto' => ['Recto', 297, 420, 3]], 'colors' => [], 'sizes' => ['A4', 'A3', 'A2'], 'note' => 'Dessiné en A3 ; en A4 ou en A2, le fichier vectoriel est réduit ou agrandi à l’identique, au format exact (mêmes proportions).', 'cost' => 400],
        'poster-a3' => ['name' => 'Poster A3', 'mockup' => 'paper', 'faces' => ['recto' => ['Recto', 297, 420, 3]], 'colors' => [], 'sizes' => [], 'note' => '', 'cost' => 400],
        'poster-a2' => ['name' => 'Poster A2', 'mockup' => 'paper', 'faces' => ['recto' => ['Recto', 420, 594, 3]], 'colors' => [], 'sizes' => [], 'note' => '', 'cost' => 700],
        'carte' => ['name' => 'Carte postale', 'mockup' => 'paper', 'faces' => ['recto' => ['Recto', 148, 105, 3], 'verso' => ['Verso', 148, 105, 3]], 'colors' => [], 'sizes' => [], 'note' => '', 'cost' => 80],
        'sticker' => ['name' => 'Sticker', 'mockup' => 'sticker', 'faces' => ['recto' => ['Recto', 100, 100, 2]], 'colors' => [], 'sizes' => [], 'note' => 'Découpe à la forme : le contour suit le bord du dessin.', 'cost' => 60],
    ];
    public const MOCKUPS = ['tee' => 'T-shirt', 'hoodie' => 'Sweat', 'mug' => 'Mug', 'tote' => 'Tote bag', 'cap' => 'Casquette', 'scarf' => 'Écharpe', 'paper' => 'Papier (poster, carte)', 'sticker' => 'Sticker'];

    // ------------------------------------------------------------------ supports

    /** @return array<string,array> clé => support complet (avec réglages de l'admin) */
    public static function supports(bool $activeOnly = false): array
    {
        $saved = (array) Collections::get(self::SUPPORTS_FILE, []);
        $out = [];
        foreach (self::DEFAULTS as $k => $d) {
            $out[$k] = self::normalize($k, array_replace($d, (array) ($saved[$k] ?? [])));
        }
        foreach ($saved as $k => $d) {
            if (!isset($out[$k]) && is_array($d) && isset($d['faces'])) {
                $out[$k] = self::normalize((string) $k, $d);
            }
        }
        return $activeOnly ? array_filter($out, fn ($s) => $s['active']) : $out;
    }

    public static function support(string $key): ?array
    {
        return self::supports()[$key] ?? null;
    }

    private static function normalize(string $key, array $s): array
    {
        $faces = [];
        foreach ((array) ($s['faces'] ?? []) as $fk => $f) {
            $f = array_values((array) $f);
            $faces[(string) $fk] = ['label' => (string) ($f[0] ?? $fk), 'w' => max(10.0, (float) ($f[1] ?? 100)), 'h' => max(10.0, (float) ($f[2] ?? 100)), 'bleed' => max(0.0, min(10.0, (float) ($f[3] ?? 0)))];
        }
        $sizes = array_values(array_filter(array_map('strval', (array) ($s['sizes'] ?? []))));
        // Poster vectoriel au format A (A3, A2…) sans format choisi : proposé en A4, A3 et A2,
        // le dessin étant réduit ou agrandi à l'identique (voir scaleFace).
        if (!$sizes && ($s['mockup'] ?? '') === 'paper' && count($faces) === 1) {
            $f = reset($faces);
            if ($f['w'] >= 200 && abs($f['h'] / $f['w'] - M_SQRT2) < 0.01) {
                $sizes = ['A4', 'A3', 'A2'];
            }
        }
        return [
            'key' => $key, 'name' => (string) ($s['name'] ?? $key), 'mockup' => isset(self::MOCKUPS[$s['mockup'] ?? '']) ? $s['mockup'] : 'paper',
            'faces' => $faces ?: ['recto' => ['label' => 'Recto', 'w' => 100, 'h' => 100, 'bleed' => 0]],
            'colors' => array_map(fn ($c) => Vector::hex($c, '#FFFFFF'), (array) ($s['colors'] ?? [])),
            'sizes' => $sizes,
            'ref' => (string) ($s['ref'] ?? ''), 'note' => (string) ($s['note'] ?? ''),
            // Coût de fabrication chez l'imprimeur (centimes TTC par article) : relevés et marge.
            'cost' => max(0, min(100000, (int) ($s['cost'] ?? 0))),
            // Commission de l'imprimeur (% du prix de vente TTC) ; 0 : on retient le coût fixe ci-dessus.
            'rate' => round(max(0.0, min(100.0, (float) ($s['rate'] ?? 0))), 2),
            'active' => (bool) ($s['active'] ?? true), 'custom' => !isset(self::DEFAULTS[$key]),
        ];
    }

    /** Enregistre un support (réglages de l'admin) ; renvoie sa clé. */
    public static function saveSupport(string $key, array $data, ?array $user = null): string
    {
        $saved = (array) Collections::get(self::SUPPORTS_FILE, []);
        $key = $key !== '' ? $key : self::slug((string) ($data['name'] ?? 'support'), array_keys(self::supports()));
        $saved[$key] = $data;
        Collections::save(self::SUPPORTS_FILE, $saved, $user, 'Support de la boutique : ' . ($data['name'] ?? $key));
        return $key;
    }

    // ------------------------------------------------------------------ modèles

    /** @return array<string,array> id => modèle */
    public static function models(): array
    {
        $out = [];
        foreach ((array) Collections::get(self::MODELS_FILE, []) as $m) {
            if (is_array($m) && isset($m['id'])) {
                $out[(string) $m['id']] = self::model($m);
            }
        }
        return $out;
    }

    /** Range les modèles dans l'ordre donné (les absents gardent leur place relative, à la suite). */
    public static function reorder(array $ids, ?array $user = null): void
    {
        $all = array_values(array_filter((array) Collections::get(self::MODELS_FILE, []), 'is_array'));
        $rank = array_flip(array_values($ids));
        $pos = array_keys($all);
        usort($pos, fn ($a, $b) => [$rank[$all[$a]['id'] ?? ''] ?? PHP_INT_MAX, $a] <=> [$rank[$all[$b]['id'] ?? ''] ?? PHP_INT_MAX, $b]);
        Collections::save(self::MODELS_FILE, array_map(fn ($i) => $all[$i], $pos), $user, 'Ordre des modèles de la boutique');
    }

    public static function find(string $id): ?array
    {
        return self::models()[$id] ?? null;
    }

    /** Modèle complété : une face par face du support (vide si non dessinée). */
    public static function model(array $m): array
    {
        $sup = self::support((string) ($m['support'] ?? '')) ?? self::support('tshirt');
        $faces = [];
        foreach ($sup['faces'] as $fk => $f) {
            $d = (array) ($m['faces'][$fk] ?? []);
            $faces[$fk] = $f + ['bg' => ($d['bg'] ?? '') !== '' ? Vector::hex($d['bg']) : '', 'layers' => array_values(array_filter((array) ($d['layers'] ?? []), 'is_array'))];
        }
        $colors = $sup['colors'];
        $color = (string) ($m['color'] ?? '');
        return [
            'id' => (string) $m['id'], 'name' => (string) ($m['name'] ?? 'Modèle'), 'support' => $sup['key'], 'faces' => $faces,
            'color' => $colors ? (in_array($color, $colors, true) ? $color : reset($colors)) : '',
            'active' => (bool) ($m['active'] ?? false), 'updated' => (string) ($m['updated'] ?? ''), 'by' => (string) ($m['by'] ?? ''),
            'sale' => self::sale((array) ($m['sale'] ?? []), $sup),
        ];
    }

    /**
     * Vente d'un modèle : prix (centimes), supplément par taille, description, et choix laissés au
     * client, toujours dans la charte : couleurs du produit (parmi celles du support), couleurs des
     * textes du client (palette), trois tailles de texte, trois positions (haut, centre, bas).
     */
    public static function sale(array $s, array $sup): array
    {
        $extra = [];
        foreach ((array) ($s['extra'] ?? []) as $size => $c) {
            if (in_array($size, $sup['sizes'], true) && (int) $c > 0) {
                $extra[$size] = min(100000, (int) $c);
            }
        }
        // Support imprimé en entier (écharpe, poster…) : pas de couleur de produit, le client choisit la couleur du fond.
        $colors = array_values(array_intersect(array_map('strtoupper', (array) ($s['colors'] ?? [])), $sup['colors'] ?: array_values(Vector::PALETTE)));
        $tcolors = array_values(array_unique(array_filter(array_map(fn ($c) => strtoupper((string) $c), (array) ($s['text_colors'] ?? [])), fn ($c) => in_array($c, array_values(Vector::PALETTE), true))));
        return [
            'price' => max(0, min(100000, (int) ($s['price'] ?? 0))), 'extra' => $extra,
            'desc' => mb_substr(trim((string) ($s['desc'] ?? '')), 0, 600),
            // Imprimé en entier : sans couleur cochée, les fonds de la charte (comme les couleurs d'une casquette).
            'colors' => $colors ?: ($sup['colors'] ? array_values($sup['colors']) : self::FULL_BG), 'text_colors' => array_slice($tcolors, 0, 8),
            'text_sizes' => !empty($s['text_sizes']), 'positions' => !empty($s['positions']),
            // Taux de commission propre au modèle (null : celui du support).
            'rate' => isset($s['rate']) && $s['rate'] !== '' && $s['rate'] !== null ? round(max(0.0, min(100.0, (float) $s['rate'])), 2) : null,
            // Ou une commission fixe en centimes par article (prioritaire sur le taux).
            'fee' => isset($s['fee']) && $s['fee'] !== '' && $s['fee'] !== null ? max(0, min(100000, (int) $s['fee'])) : null,
        ];
    }

    /** Fonds proposés par défaut sur un support imprimé en entier : bleu nuit, jaune, blanc, bleu roi, noir. */
    public const FULL_BG = ['#0E1F4D', '#F6C400', '#FFFFFF', '#1E3FA8', '#111111'];

    /** Couleurs de texte toujours proposées (charte), en plus de celles cochées dans le modèle. */
    public const TEXT_BASE = ['#F6C400', '#0E1F4D', '#FFFFFF'];

    /** Contraste WCAG entre deux couleurs (1 à 21). */
    public static function contrast(string $a, string $b): float
    {
        $lum = function (string $h): float {
            $h = ltrim($h, '#');
            $c = array_map(fn ($i) => hexdec(substr($h, $i, 2)) / 255, [0, 2, 4]);
            $c = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);
            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$x, $y] = [$lum($a), $lum($b)];
        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    /** Couleurs de texte proposées au client : celles du modèle + la charte. */
    public static function textChoices(array $m): array
    {
        return array_values(array_unique(array_merge($m['sale']['text_colors'], self::TEXT_BASE)));
    }

    /** Lisible sur la couleur du produit ? (contraste d'au moins 3). */
    public static function readable(string $text, string $product): bool
    {
        return self::contrast($text, $product) >= 3.0;
    }

    /** Taux de commission de l'imprimeur pour un modèle (%, 0 : coût fixe du support). */
    public static function rate(array $m, array $sup): float
    {
        return ($m['sale']['fee'] ?? null) !== null ? 0.0 : ($m['sale']['rate'] ?? $sup['rate']);
    }

    /** Part de l'imprimeur sur un article vendu à $unit centimes : commission en %, sinon coût fixe. */
    public static function printerShare(array $m, array $sup, int $unit): int
    {
        if (($m['sale']['fee'] ?? null) !== null) {
            return (int) $m['sale']['fee'];
        }
        $r = self::rate($m, $sup);
        return $r > 0 ? (int) round($unit * $r / 100) : (int) $sup['cost'];
    }

    /**
     * Pièce unique : le modèle porte une anecdote tirée par le client (jamais vendue deux fois),
     * ou c'est un poster souvenir (dédicacé et numéroté).
     */
    public static function unique(array $m): bool
    {
        return isset(self::fields($m)[Anecdotes::FIELD]) || Poster::isFor($m);
    }

    /** En vente : prêt, avec un prix, sur un support actif. */
    public static function sellable(array $m): bool
    {
        $sup = self::support($m['support']);
        return $m['active'] && $m['sale']['price'] > 0 && $sup && $sup['active'];
    }

    /** Prix unitaire (centimes) pour une taille. */
    public static function price(array $m, string $size = ''): int
    {
        return $m['sale']['price'] + (int) ($m['sale']['extra'][$size] ?? 0);
    }

    public const TEXT_SIZES = ['s' => [0.85, 'Petit'], 'm' => [1.0, 'Moyen'], 'l' => [1.15, 'Grand']];
    public const POSITIONS = ['haut' => 'En haut', 'centre' => 'Au centre', 'bas' => 'En bas'];

    /**
     * Choix du client appliqués au modèle (couleur du produit, couleur et taille des textes du
     * client, position) ; un choix non proposé est ignoré. @return array{0:array,1:array} [modèle, choix retenus]
     */
    public static function applyOptions(array $m, array $o): array
    {
        $s = $m['sale'];
        $opt = ['color' => $m['color'], 'tcolor' => '', 'tsize' => 'm', 'pos' => '', 'date' => ''];
        // « Ton match » : année, puis mois et jour facultatifs (ou une date déjà composée).
        $y = preg_replace('/\D/', '', (string) ($o['y'] ?? ''));
        $date = (string) ($o['date'] ?? '');
        if (strlen($y) === 4) {
            $mo = (int) ($o['mo'] ?? 0);
            $d = (int) ($o['d'] ?? 0);
            $date = $y . ($mo >= 1 && $mo <= 12 ? sprintf('-%02d', $mo) . ($d >= 1 && $d <= 31 ? sprintf('-%02d', $d) : '') : '');
        }
        if (TonMatch::parse($date)) {
            $opt['date'] = $date;
        }
        $full = !(self::support($m['support'])['colors'] ?? []);
        if ($full) {
            // Imprimé en entier : la « couleur du produit » est le fond du dessin (lisibilité du texte comprise).
            foreach ($m['faces'] as $face) {
                if (($face['bg'] ?? '') !== '') {
                    $opt['color'] = $m['color'] = Vector::hex($face['bg']);
                    break;
                }
            }
        }
        // Poster souvenir : sa charte est fixe (aucun choix de couleur).
        $fixed = Poster::isFor($m);
        $c = $fixed ? '' : strtoupper((string) ($o['color'] ?? ''));
        if ($c !== '' && in_array($c, $s['colors'], true)) {
            $opt['color'] = $m['color'] = $c;
            if ($full) {
                foreach ($m['faces'] as $fk => $face) {
                    $m['faces'][$fk]['bg'] = $c;
                }
            }
        }
        $tc = $fixed ? '' : strtoupper((string) ($o['tcolor'] ?? ''));
        if ($tc !== '' && in_array($tc, self::textChoices($m), true) && self::readable($tc, $m['color'])) {
            $opt['tcolor'] = $tc;
        }
        if ($s['text_sizes'] && isset(self::TEXT_SIZES[$o['tsize'] ?? ''])) {
            $opt['tsize'] = (string) $o['tsize'];
        }
        $pos = (string) ($o['pos'] ?? '');
        if ($s['positions'] && isset(self::POSITIONS[$pos])) {
            $opt['pos'] = $pos;
        }
        $f = self::TEXT_SIZES[$opt['tsize']][0];
        foreach ($m['faces'] as $fk => $face) {
            $client = [];
            foreach ($face['layers'] as $i => $l) {
                if (($l['type'] ?? '') === 'text' && ($l['mode'] ?? '') === 'client') {
                    if ($opt['tcolor'] !== '') {
                        $l['color'] = $opt['tcolor'];
                    } elseif (!self::readable(Vector::hex($l['color'] ?? '#0E1F4D', '#0E1F4D'), $face['bg'] !== '' ? Vector::hex($face['bg'], $m['color']) : $m['color'])) {
                        // Couleur du modèle illisible sur ce produit : la couleur de la charte la plus contrastée.
                        $bg = $face['bg'] !== '' ? Vector::hex($face['bg'], $m['color']) : $m['color'];
                        $cands = self::TEXT_BASE;
                        usort($cands, fn ($a, $b) => self::contrast($b, $bg) <=> self::contrast($a, $bg));
                        $l['color'] = $cands[0];
                    }
                    if ($f !== 1.0) {
                        $l['size'] = round((float) ($l['size'] ?? 24) * $f, 2);
                    }
                    $face['layers'][$i] = $l;
                    $client[] = $i;
                }
            }
            if ($opt['pos'] !== '' && $client) {
                $top = INF;
                $bottom = -INF;
                foreach ($client as $i) {
                    $l = $face['layers'][$i];
                    $top = min($top, (float) $l['y']);
                    $bottom = max($bottom, (float) $l['y'] + ((float) ($l['h'] ?? 0) > 0 ? (float) $l['h'] : Vector::textHeight($l)));
                }
                $gh = $bottom - $top;
                $to = ['haut' => $face['h'] * 0.08, 'centre' => ($face['h'] - $gh) / 2, 'bas' => $face['h'] * 0.92 - $gh][$opt['pos']];
                foreach ($client as $i) {
                    $face['layers'][$i]['y'] = round((float) $face['layers'][$i]['y'] + $to - $top, 2);
                }
            }
            $m['faces'][$fk] = $face;
        }
        return [$m, $opt];
    }

    /**
     * Positions vraiment proposées : celles où les textes du client ne chevauchent pas le reste du
     * dessin (logo, textes fixes) et restent dans la face. @return array<string,string>
     */
    public static function positions(array $m): array
    {
        if (!$m['sale']['positions']) {
            return [];
        }
        $out = [];
        foreach (self::POSITIONS as $k => $label) {
            [$mm] = self::applyOptions($m, ['pos' => $k]);
            $ok = true;
            foreach ($mm['faces'] as $face) {
                $fixed = [];
                $client = [];
                foreach (Vector::shapes($face['layers'], []) as $sh) {
                    $b = Vector::bbox($sh['d']);
                    $l = null;
                    foreach ($face['layers'] as $x) {
                        if ($x['id'] === $sh['layer']) {
                            $l = $x;
                        }
                    }
                    if (!$b || !$l) {
                        continue;
                    }
                    if (($l['mode'] ?? '') === 'client') {
                        $client[] = $l['h'] ?? 0 ? [$l['x'], $l['y'], $l['x'] + $l['w'], $l['y'] + $l['h']] : $b;
                    } else {
                        $fixed[] = $b;
                    }
                }
                foreach ($client as $c) {
                    if ($c[1] < -0.5 || $c[3] > $face['h'] + 0.5) {
                        $ok = false;
                    }
                    foreach ($fixed as $b) {
                        if ($c[0] < $b[2] && $c[2] > $b[0] && $c[1] < $b[3] && $c[3] > $b[1]) {
                            $ok = false;
                        }
                    }
                }
            }
            if ($ok) {
                $out[$k] = $label;
            }
        }
        return count($out) > 1 ? $out : [];
    }

    /** Formats de papier (mm) : un poster dessiné en A3 s'imprime aussi en A4 ou en A2. */
    public const PAPER = ['A4' => [210, 297], 'A3' => [297, 420], 'A2' => [420, 594], 'A1' => [594, 841]];

    /**
     * Face mise au format de papier choisi : tout le dessin (positions, tailles, corps, traits)
     * agrandi ou réduit d'un même facteur. Seulement si la face a les proportions de ce format.
     */
    public static function scaleFace(array $f, string $size): array
    {
        [$pw, $ph] = self::PAPER[$size] ?? [0, 0];
        if (!$pw || abs($f['w'] / $f['h'] - $pw / $ph) > 0.01 || abs($f['w'] - $pw) < 0.5) {
            return $f;
        }
        $k = $pw / $f['w'];
        foreach ($f['layers'] as $i => $l) {
            foreach (['x', 'y', 'w', 'h', 'size', 'min', 'sw', 'r'] as $key) {
                if (isset($l[$key]) && is_numeric($l[$key])) {
                    $l[$key] = round((float) $l[$key] * $k, 3);
                }
            }
            $f['layers'][$i] = $l;
        }
        $f['w'] = $pw;
        $f['h'] = $ph;
        return $f;
    }

    /** Fichier d'impression PDF (toutes les faces dessinées) d'un modèle, avec les réponses du client. */
    public static function printPdf(array $m, array $values, string $title, array $extra = [], string $size = ''): string
    {
        $sup = self::support($m['support']);
        foreach ($m['faces'] as $fk => $f) {
            $m['faces'][$fk] = self::scaleFace($f, $size);
        }
        $faces = [];
        $colorName = (string) (array_search($m['color'], $sup['colors'], true) ?: '');
        foreach ($m['faces'] as $f) {
            if ($f['layers'] || $f['bg']) {
                $faces[] = ['name' => $title . ' · ' . $sup['name'] . ' · ' . $f['label'] . ' · ' . $f['w'] . ' × ' . $f['h'] . ' mm' . ($f['bleed'] ? ' + ' . $f['bleed'] . ' mm de fonds perdus' : '') . ($colorName !== '' ? ' · support ' . $colorName : '') . ($extra ? ' · ' . implode(' · ', $extra) : ''), 'side' => $f, 'values' => $values];
            }
        }
        return $faces ? Vector::pdf($faces, ['title' => $title, 'cmyk' => true]) : '';
    }

    /**
     * Champs que le client remplit (tous les calques « client »). list : identifiant d'une liste
     * de la banque de textes (le client choisit une phrase validée de cette liste) ou '' ;
     * choices : phrases proposées (validées et qui tiennent dans le cadre) ; rejected : trop longues.
     * @return array<string,array{label:string,max:int,default:string,list:string,choices:list<string>,rejected:list<string>}>
     */
    public static function fields(array $model): array
    {
        $out = [];
        $layers = [];
        foreach ($model['faces'] as $f) {
            foreach ($f['layers'] as $l) {
                if (($l['type'] ?? '') === 'poster') {
                    // Poster souvenir : le match ou le joueur (choisi dans les propositions), puis la dédicace.
                    $pj = ($l['kind'] ?? '') === 'joueur';
                    $subject = $pj ? PlayerPoster::FIELD : Poster::FIELD;
                    foreach ($pj ? PlayerPoster::FIELDS : Poster::FIELDS as $pk => $plabel) {
                        $out[$pk] ??= ['label' => $plabel, 'max' => $pk === $subject ? 12 : 30, 'default' => $pk === $subject ? ($pj ? PlayerPoster::SAMPLE : Poster::SAMPLE) : ['poster_prenom' => 'Prénom', 'poster_nom' => 'Nom'][$pk],
                            'list' => '', 'choices' => [], 'rejected' => [], 'auto' => false, 'gen' => false, 'poster' => $pk === $subject ? ($pj ? 'joueur' : 'match') : 'name'];
                    }
                    continue;
                }
                if (($l['type'] ?? '') === 'text' && ($l['mode'] ?? '') === 'client' && ($l['field'] ?? '') !== '') {
                    $layers[$l['field']][] = $l;
                    $list = (string) ($l['list'] ?? '');
                    $out[$l['field']] ??= ['label' => (string) ($l['label'] ?? $l['field']), 'max' => max(1, (int) ($l['max'] ?? 30)), 'default' => (string) ($l['text'] ?? ''),
                        'list' => $list, 'choices' => [], 'rejected' => [], 'auto' => str_starts_with((string) $l['field'], 'match_'),
                        'gen' => $l['field'] === Anecdotes::FIELD];
                }
            }
        }
        // Phrases proposées : validées dans la banque de textes ET qui tiennent sur tous les calques du champ.
        foreach ($out as $k => $f) {
            foreach ($f['list'] !== '' ? Texts::choices($f['list']) : [] as $c) {
                $fit = true;
                foreach ($layers[$k] as $l) {
                    $fit = $fit && Vector::fits($l, [$k => $c]);
                }
                $fit ? $out[$k]['choices'][] = $c : $out[$k]['rejected'][] = $c;
            }
        }
        return $out;
    }

    /**
     * Réponses du client contrôlées avant la commande : phrase d'une liste parmi celles proposées,
     * texte libre dans la limite de caractères et qui tient dans le cadre au corps minimum.
     * @return array{values:array<string,string>,errors:array<string,string>}
     */
    public static function check(array $model, array $values): array
    {
        $ok = [];
        $errors = [];
        foreach (self::fields($model) as $k => $f) {
            $v = trim((string) ($values[$k] ?? ''));
            if ($f['auto']) {
                // Rempli par « Ton match » à partir de la date (jamais saisi par le client).
                continue;
            }
            if ($v === '') {
                continue;
            }
            if ($f['list'] !== '') {
                in_array($v, $f['choices'], true) ? $ok[$k] = $v : $errors[$k] = 'Choisissez une phrase de la liste.';
                continue;
            }
            if (($f['poster'] ?? '') === 'match') {
                Poster::eligible($v) ? $ok[$k] = $v : $errors[$k] = 'Choisissez votre match dans la liste proposée.';
                continue;
            }
            if (($f['poster'] ?? '') === 'joueur') {
                PlayerPoster::eligible($v) ? $ok[$k] = $v : $errors[$k] = 'Choisissez votre joueur dans la liste proposée.';
                continue;
            }
            if (($f['poster'] ?? '') === 'name') {
                $v = (string) preg_replace('/\s+/u', ' ', $v);
                preg_match('/^[\p{L}][\p{L}\p{M} .\'’-]*$/u', $v) && mb_strlen($v) <= $f['max'] ? $ok[$k] = $v : $errors[$k] = 'Lettres, espaces, apostrophes et tirets ; ' . $f['max'] . ' caractères au plus.';
                continue;
            }
            if ($f['gen']) {
                // Anecdote tirée par le site (signée) : jamais un texte saisi ou retouché.
                if (!Anecdotes::valid($v, (string) ($values['_sig_' . $k] ?? ''))) {
                    $errors[$k] = 'Tirez une anecdote avec le bouton « Une anecdote ».';
                    continue;
                }
                $ok[$k] = $v;
                continue;
            }
            if (mb_strlen($v) > $f['max']) {
                $errors[$k] = $f['max'] . ' caractères au plus.';
                continue;
            }
            $fit = true;
            foreach ($model['faces'] as $face) {
                foreach ($face['layers'] as $l) {
                    if (($l['field'] ?? '') === $k && ($l['mode'] ?? '') === 'client') {
                        $fit = $fit && Vector::fits($l, [$k => $v]);
                    }
                }
            }
            $fit ? $ok[$k] = $v : $errors[$k] = 'Texte trop long pour cet emplacement : raccourcissez-le.';
        }
        return ['values' => $ok, 'errors' => $errors];
    }

    public static function saveModel(array $m, ?array $user = null): array
    {
        $all = (array) Collections::get(self::MODELS_FILE, []);
        $m['id'] = (string) ($m['id'] ?? '') !== '' ? (string) $m['id'] : bin2hex(random_bytes(5));
        $m['updated'] = date('c');
        $m['by'] = (string) ($user['name'] ?? '');
        $model = self::model($m);
        $store = ['id' => $model['id'], 'name' => $model['name'], 'support' => $model['support'], 'color' => $model['color'], 'active' => $model['active'], 'updated' => $model['updated'], 'by' => $model['by'], 'sale' => $model['sale'], 'faces' => []];
        foreach ($model['faces'] as $fk => $f) {
            $store['faces'][$fk] = ['bg' => $f['bg'], 'layers' => array_map([self::class, 'cleanLayer'], $f['layers'])];
        }
        $found = false;
        foreach ($all as $i => $old) {
            if (($old['id'] ?? '') === $model['id']) {
                $all[$i] = $store;
                $found = true;
            }
        }
        if (!$found) {
            $all[] = $store;
        }
        Collections::save(self::MODELS_FILE, array_values($all), $user, 'Modèle de la boutique : ' . $model['name']);
        return $model;
    }

    public static function deleteModel(string $id, ?array $user = null): void
    {
        $all = array_values(array_filter((array) Collections::get(self::MODELS_FILE, []), fn ($m) => ($m['id'] ?? '') !== $id));
        Collections::save(self::MODELS_FILE, $all, $user, 'Modèle de la boutique supprimé');
    }

    /** Calque nettoyé : seulement les réglages connus, valeurs bornées. */
    public static function cleanLayer(array $l): array
    {
        $type = in_array($l['type'] ?? '', ['logo', 'text', 'rect', 'ellipse', 'poster'], true) ? $l['type'] : 'text';
        $num = fn ($v, float $min, float $max, float $def) => is_numeric($v) ? max($min, min($max, round((float) $v, 2))) : $def;
        $out = ['id' => preg_replace('/[^a-z0-9]/i', '', (string) ($l['id'] ?? '')) ?: bin2hex(random_bytes(3)), 'type' => $type,
            'x' => $num($l['x'] ?? 0, -2000, 3000, 0), 'y' => $num($l['y'] ?? 0, -2000, 3000, 0), 'w' => $num($l['w'] ?? 50, 1, 3000, 50)];
        if ($type === 'poster') {
            $out += ['h' => $num($l['h'] ?? 420, 20, 3000, 420), 'kind' => ($l['kind'] ?? '') === 'joueur' ? 'joueur' : 'match'];
        } elseif ($type === 'logo') {
            $out += ['style' => ($l['style'] ?? '') === 'mono' ? 'mono' : 'couleurs', 'color' => Vector::hex($l['color'] ?? null, '#FDC729')];
        } elseif ($type === 'text') {
            $out += [
                'text' => mb_substr((string) ($l['text'] ?? ''), 0, 600), 'font' => isset(Vector::FONTS[$l['font'] ?? '']) ? $l['font'] : 'display',
                'size' => $num($l['size'] ?? 24, 2, 400, 24), 'color' => Vector::hex($l['color'] ?? null), 'align' => in_array($l['align'] ?? '', ['left', 'center', 'right'], true) ? $l['align'] : 'left',
                'valign' => in_array($l['valign'] ?? '', ['middle', 'bottom'], true) ? $l['valign'] : 'top',
                'upper' => !empty($l['upper']), 'spacing' => $num($l['spacing'] ?? 0, -100, 600, 0), 'lh' => $num($l['lh'] ?? 1.1, 0.6, 3, 1.1), 'fit' => !empty($l['fit']),
                'mode' => ($l['mode'] ?? '') === 'client' ? 'client' : 'fixed',
                'field' => substr((string) preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($l['field'] ?? ''))), 0, 30),
                'label' => mb_substr((string) ($l['label'] ?? ''), 0, 60), 'max' => (int) $num($l['max'] ?? 30, 1, 200, 30),
                'list' => substr((string) preg_replace('/[^a-z0-9]/', '', (string) ($l['list'] ?? '')), 0, 30),
                'h' => $num($l['h'] ?? 0, 0, 3000, 0), 'min' => $num($l['min'] ?? 10, 2, 400, 10),
            ];
        } else {
            $out += ['h' => $num($l['h'] ?? 20, 0.2, 3000, 20), 'fill' => ($l['fill'] ?? '') !== '' ? Vector::hex($l['fill']) : '', 'stroke' => ($l['stroke'] ?? '') !== '' ? Vector::hex($l['stroke']) : '', 'sw' => $num($l['sw'] ?? 0, 0, 50, 0), 'r' => $num($l['r'] ?? 0, 0, 500, 0)];
        }
        return $out;
    }

    private static function slug(string $name, array $taken): string
    {
        $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(\App\Data\Names::ascii($name))), '-') ?: 'support';
        $k = $s;
        for ($i = 2; in_array($k, $taken, true); $i++) {
            $k = $s . '-' . $i;
        }
        return $k;
    }
}
