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
     * du produit vierge (vide : imprimé en entier, le fond fait partie du dessin).
     */
    public const DEFAULTS = [
        'tshirt' => ['name' => 'T-shirt', 'mockup' => 'tee', 'faces' => ['avant' => ['Avant', 280, 350, 0], 'dos' => ['Dos', 280, 350, 0]], 'colors' => self::TEXTILE, 'sizes' => self::SIZES, 'note' => 'Impression directe sur textile (DTG) ou transfert ; pas de fonds perdus.'],
        'sweat' => ['name' => 'Sweat à capuche', 'mockup' => 'hoodie', 'faces' => ['avant' => ['Avant', 250, 280, 0], 'dos' => ['Dos', 280, 350, 0]], 'colors' => ['Bleu nuit' => '#0E1F4D', 'Noir' => '#1A1A1A', 'Gris chiné' => '#B9BCC2', 'Jaune' => '#F6C400'], 'sizes' => self::SIZES, 'note' => ''],
        'mug' => ['name' => 'Mug céramique', 'mockup' => 'mug', 'faces' => ['tour' => ['Tour complet', 200, 90, 2]], 'colors' => ['Blanc' => '#FFFFFF'], 'sizes' => [], 'note' => 'Sublimation enroulée : la face visible est le centre du dessin, l’anse est aux deux bords.'],
        'mug-emaille' => ['name' => 'Mug émaillé', 'mockup' => 'mug', 'faces' => ['tour' => ['Tour complet', 200, 75, 2]], 'colors' => ['Blanc' => '#FFFFFF'], 'sizes' => [], 'note' => 'Liseré du bord en bleu ou en noir selon le modèle de l’imprimeur.'],
        'tote' => ['name' => 'Tote bag', 'mockup' => 'tote', 'faces' => ['face' => ['Face', 280, 300, 0]], 'colors' => ['Naturel' => '#EFE6D2', 'Noir' => '#1A1A1A', 'Bleu nuit' => '#0E1F4D'], 'sizes' => [], 'note' => ''],
        'casquette' => ['name' => 'Casquette', 'mockup' => 'cap', 'faces' => ['avant' => ['Face avant', 100, 55, 0]], 'colors' => ['Bleu nuit' => '#0E1F4D', 'Noir' => '#1A1A1A', 'Blanc' => '#FFFFFF', 'Jaune' => '#F6C400'], 'sizes' => ['Taille unique'], 'note' => 'Broderie : 6 couleurs au plus, pas de texte de moins de 5 mm de haut, pas de trait fin.'],
        'echarpe' => ['name' => 'Écharpe', 'mockup' => 'scarf', 'faces' => ['recto' => ['Recto', 1400, 180, 5]], 'colors' => [], 'sizes' => [], 'note' => 'Écharpe imprimée en entier (sublimation) : le fond fait partie du dessin, prévoir les franges aux deux bouts.'],
        'poster-a3' => ['name' => 'Poster A3', 'mockup' => 'paper', 'faces' => ['recto' => ['Recto', 297, 420, 3]], 'colors' => [], 'sizes' => [], 'note' => ''],
        'poster-a2' => ['name' => 'Poster A2', 'mockup' => 'paper', 'faces' => ['recto' => ['Recto', 420, 594, 3]], 'colors' => [], 'sizes' => [], 'note' => ''],
        'carte' => ['name' => 'Carte postale', 'mockup' => 'paper', 'faces' => ['recto' => ['Recto', 148, 105, 3], 'verso' => ['Verso', 148, 105, 3]], 'colors' => [], 'sizes' => [], 'note' => ''],
        'sticker' => ['name' => 'Sticker', 'mockup' => 'sticker', 'faces' => ['recto' => ['Recto', 100, 100, 2]], 'colors' => [], 'sizes' => [], 'note' => 'Découpe à la forme : le contour suit le bord du dessin.'],
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
        return [
            'key' => $key, 'name' => (string) ($s['name'] ?? $key), 'mockup' => isset(self::MOCKUPS[$s['mockup'] ?? '']) ? $s['mockup'] : 'paper',
            'faces' => $faces ?: ['recto' => ['label' => 'Recto', 'w' => 100, 'h' => 100, 'bleed' => 0]],
            'colors' => array_map(fn ($c) => Vector::hex($c, '#FFFFFF'), (array) ($s['colors'] ?? [])),
            'sizes' => array_values(array_filter(array_map('strval', (array) ($s['sizes'] ?? [])))),
            'ref' => (string) ($s['ref'] ?? ''), 'note' => (string) ($s['note'] ?? ''),
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
        ];
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
                if (($l['type'] ?? '') === 'text' && ($l['mode'] ?? '') === 'client' && ($l['field'] ?? '') !== '') {
                    $layers[$l['field']][] = $l;
                    $list = (string) ($l['list'] ?? '');
                    $out[$l['field']] ??= ['label' => (string) ($l['label'] ?? $l['field']), 'max' => max(1, (int) ($l['max'] ?? 30)), 'default' => (string) ($l['text'] ?? ''),
                        'list' => $list, 'choices' => [], 'rejected' => []];
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
            if ($v === '') {
                continue;
            }
            if ($f['list'] !== '') {
                in_array($v, $f['choices'], true) ? $ok[$k] = $v : $errors[$k] = 'Choisissez une phrase de la liste.';
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
        $store = ['id' => $model['id'], 'name' => $model['name'], 'support' => $model['support'], 'color' => $model['color'], 'active' => $model['active'], 'updated' => $model['updated'], 'by' => $model['by'], 'faces' => []];
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
        $type = in_array($l['type'] ?? '', ['logo', 'text', 'rect', 'ellipse'], true) ? $l['type'] : 'text';
        $num = fn ($v, float $min, float $max, float $def) => is_numeric($v) ? max($min, min($max, round((float) $v, 2))) : $def;
        $out = ['id' => preg_replace('/[^a-z0-9]/i', '', (string) ($l['id'] ?? '')) ?: bin2hex(random_bytes(3)), 'type' => $type,
            'x' => $num($l['x'] ?? 0, -2000, 3000, 0), 'y' => $num($l['y'] ?? 0, -2000, 3000, 0), 'w' => $num($l['w'] ?? 50, 1, 3000, 50)];
        if ($type === 'logo') {
            $out += ['style' => ($l['style'] ?? '') === 'mono' ? 'mono' : 'couleurs', 'color' => Vector::hex($l['color'] ?? null, '#FDC729')];
        } elseif ($type === 'text') {
            $out += [
                'text' => mb_substr((string) ($l['text'] ?? ''), 0, 600), 'font' => isset(Vector::FONTS[$l['font'] ?? '']) ? $l['font'] : 'display',
                'size' => $num($l['size'] ?? 24, 2, 400, 24), 'color' => Vector::hex($l['color'] ?? null), 'align' => in_array($l['align'] ?? '', ['left', 'center', 'right'], true) ? $l['align'] : 'left',
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
