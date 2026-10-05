<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Shop\Catalog;
use App\Shop\Mockup;
use App\Shop\Vector;

/**
 * BOUTIQUE (administrateurs) : supports de l'imprimeur, modèles dessinés dans l'éditeur (logo de
 * l'association, textes, formes ; jamais de photo), aperçu sur le produit, fichier d'impression
 * PDF vectoriel. Commandes, paiements et imprimeur viendront ensuite (lots B et C).
 */
final class Shop extends Base
{
    public static function index(Request $req): Response
    {
        $models = Catalog::models();
        return self::html('admin/boutique/index', [
            'models' => $models, 'supports' => Catalog::supports(),
        ], ['title' => 'Boutique', 'crumb' => 'Boutique', 'nav' => 'boutique']);
    }

    // ------------------------------------------------------------------ supports

    public static function supports(Request $req): Response
    {
        return self::html('admin/boutique/supports', ['supports' => Catalog::supports(), 'mockups' => Catalog::MOCKUPS], ['title' => 'Supports', 'crumb' => 'Boutique', 'nav' => 'boutique-supports']);
    }

    /** POST /admin/boutique/supports : un support (nom, faces, couleurs, tailles, référence, actif), ou nouveau. */
    public static function saveSupport(Request $req): Response
    {
        $key = (string) preg_replace('/[^a-z0-9-]/', '', $req->str('key'));
        $faces = [];
        foreach ((array) ($req->post['faces'] ?? []) as $f) {
            $label = trim((string) ($f['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $fk = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(\App\Data\Names::ascii($label))), '-') ?: 'face';
            $faces[$fk] = [$label, (float) ($f['w'] ?? 0), (float) ($f['h'] ?? 0), (float) ($f['bleed'] ?? 0)];
        }
        $colors = [];
        foreach (preg_split('/\R/', $req->str('colors')) ?: [] as $line) {
            if (preg_match('/^\s*(.+?)\s*[:=]\s*(#[0-9a-f]{6})\s*$/i', $line, $m)) {
                $colors[$m[1]] = strtoupper($m[2]);
            }
        }
        $name = trim($req->str('name')) ?: 'Support';
        if (!$faces) {
            return self::back('/admin/boutique/supports', null, 'Il faut au moins une face (libellé, largeur, hauteur).');
        }
        $data = [
            'name' => $name, 'mockup' => $req->str('mockup'), 'faces' => $faces, 'colors' => $colors,
            'sizes' => array_values(array_filter(array_map('trim', explode(',', $req->str('sizes'))))),
            'ref' => trim($req->str('ref')), 'note' => trim($req->str('note')), 'active' => $req->str('active') === '1',
        ];
        $key = Catalog::saveSupport($key, $data, Auth::user());
        Activity::log(self::actor(), 'a modifié le support « ' . $name . ' » de la boutique', ['path' => '/admin/boutique/supports']);
        return self::back('/admin/boutique/supports#s-' . $key, 'Support « ' . $name . ' » enregistré.');
    }

    // ------------------------------------------------------------------ modèles

    public static function models(Request $req): Response
    {
        $previews = [];
        foreach (Catalog::models() as $id => $m) {
            $sup = Catalog::support($m['support']);
            $fk = array_key_first($m['faces']);
            $previews[$id] = Mockup::render($sup['mockup'], (string) $fk, $m['faces'][$fk], $m['color'], self::sample($m))['svg'];
        }
        return self::html('admin/boutique/modeles', ['models' => Catalog::models(), 'supports' => Catalog::supports(), 'previews' => $previews], ['title' => 'Modèles', 'crumb' => 'Boutique', 'nav' => 'boutique-modeles']);
    }

    /** POST /admin/boutique/modeles : nouveau (nom, support), dupliquer, supprimer, activer. */
    public static function modelAction(Request $req): Response
    {
        $action = $req->str('action');
        $id = $req->str('id');
        if ($action === 'new') {
            $sup = Catalog::support($req->str('support'));
            if (!$sup) {
                return self::back('/admin/boutique/modeles', null, 'Support inconnu.');
            }
            $m = Catalog::saveModel(['name' => trim($req->str('name')) ?: 'Nouveau modèle', 'support' => $sup['key'], 'faces' => self::starter($sup)], Auth::user());
            return self::back('/admin/boutique/modeles/' . $m['id'], 'Modèle créé : placez le logo et les textes, puis enregistrez.');
        }
        $m = Catalog::find($id);
        if (!$m) {
            return self::back('/admin/boutique/modeles', null, 'Modèle introuvable.');
        }
        if ($action === 'duplicate') {
            $copy = Catalog::saveModel(['name' => $m['name'] . ' (copie)', 'id' => ''] + $m, Auth::user());
            return self::back('/admin/boutique/modeles/' . $copy['id'], 'Copie créée.');
        }
        if ($action === 'delete') {
            Catalog::deleteModel($id, Auth::user());
            Activity::log(self::actor(), 'a supprimé le modèle « ' . $m['name'] . ' » de la boutique', ['path' => '/admin/boutique/modeles']);
            return self::back('/admin/boutique/modeles', 'Modèle « ' . $m['name'] . ' » supprimé.');
        }
        if ($action === 'toggle') {
            Catalog::saveModel(['active' => !$m['active']] + $m, Auth::user());
            return self::back('/admin/boutique/modeles', $m['active'] ? 'Modèle mis de côté.' : 'Modèle prêt à la vente.');
        }
        return self::back('/admin/boutique/modeles', null, 'Action inconnue.');
    }

    /** Premier dessin d'un nouveau modèle : le logo centré en haut de la première face. */
    private static function starter(array $sup): array
    {
        $fk = array_key_first($sup['faces']);
        $f = $sup['faces'][$fk];
        $lw = min($f['w'] * 0.4, $f['h'] * 0.5 / Vector::LOGO_RATIO);
        return [$fk => ['bg' => $sup['colors'] ? '' : '#0E1F4D', 'layers' => [['id' => 'logo', 'type' => 'logo', 'x' => round(($f['w'] - $lw) / 2, 1), 'y' => round($f['h'] * 0.08, 1), 'w' => round($lw, 1)]]]];
    }

    public static function editor(Request $req, string $id): Response
    {
        $m = Catalog::find($id);
        if (!$m) {
            return self::back('/admin/boutique/modeles', null, 'Modèle introuvable.');
        }
        return self::html('admin/boutique/editeur', [
            'model' => $m, 'support' => Catalog::support($m['support']), 'fonts' => Vector::FONTS, 'palette' => Vector::PALETTE,
        ], ['title' => $m['name'], 'crumb' => 'Boutique › Modèles', 'nav' => 'boutique-modeles', 'scripts' => ['admin/boutique.js']]);
    }

    /** POST /admin/boutique/modeles/{id} (JSON) : enregistre le dessin. */
    public static function saveModel(Request $req, string $id): Response
    {
        $m = Catalog::find($id);
        $in = $req->json();
        if (!$m || !is_array($in['faces'] ?? null)) {
            return self::json(['ok' => false, 'error' => 'Modèle introuvable.'], 404);
        }
        $saved = Catalog::saveModel([
            'id' => $id, 'name' => trim((string) ($in['name'] ?? '')) ?: $m['name'], 'support' => $m['support'],
            'color' => (string) ($in['color'] ?? $m['color']), 'active' => (bool) ($in['active'] ?? $m['active']), 'faces' => $in['faces'],
        ], Auth::user());
        Activity::log(self::actor(), 'a modifié le modèle « ' . $saved['name'] . ' » de la boutique', ['path' => '/admin/boutique/modeles/' . $id]);
        return self::json(['ok' => true, 'model' => $saved]);
    }

    /**
     * POST /admin/boutique/apercu (JSON : support, face, color, design, values) : maquette du
     * produit, fichier d'impression (avec fonds perdus) et boîtes des calques pour l'éditeur.
     */
    public static function preview(Request $req): Response
    {
        $in = $req->json();
        $sup = Catalog::support((string) ($in['support'] ?? ''));
        $fk = (string) ($in['face'] ?? '');
        if (!$sup || !isset($sup['faces'][$fk])) {
            return self::json(['ok' => false], 404);
        }
        $face = $sup['faces'][$fk] + ['bg' => ($in['bg'] ?? '') !== '' ? Vector::hex($in['bg']) : '', 'layers' => array_map([Catalog::class, 'cleanLayer'], array_values(array_filter((array) ($in['layers'] ?? []), 'is_array')))];
        $values = array_map(fn ($v) => mb_substr((string) $v, 0, 200), (array) ($in['values'] ?? []));
        $mock = Mockup::render($sup['mockup'], $fk, $face, (string) ($in['color'] ?? ''), $values);
        $boxes = [];
        $warn = [];
        foreach (Vector::shapes($face['layers'], $values) as $sh) {
            $b = Vector::bbox($sh['d']);
            if ($b) {
                $id = $sh['layer'];
                $boxes[$id] = isset($boxes[$id]) ? [min($boxes[$id][0], $b[0]), min($boxes[$id][1], $b[1]), max($boxes[$id][2], $b[2]), max($boxes[$id][3], $b[3])] : $b;
            }
        }
        foreach ($face['layers'] as $l) {
            $b = $boxes[$l['id']] ?? null;
            if ($b && ($b[0] < -0.5 || $b[1] < -0.5 || $b[2] > $face['w'] + 0.5 || $b[3] > $face['h'] + 0.5) && empty($face['bg'])) {
                $warn[] = 'Un calque (' . ($l['type'] === 'text' ? '« ' . mb_strimwidth((string) $l['text'], 0, 24, '…') . ' »' : $l['type']) . ') dépasse de la zone imprimable.';
            }
            if ($l['type'] === 'text' && $sup['mockup'] === 'cap' && (float) $l['size'] * 25.4 / 72 * 0.7 < 5) {
                $warn[] = 'Casquette brodée : texte trop petit (moins de 5 mm de haut).';
            }
        }
        return self::json(['ok' => true, 'mockup' => $mock['svg'], 'area' => $mock['area'], 'vb' => $mock['vb'],
            'print' => Vector::svg($face, $values, true), 'boxes' => $boxes, 'warn' => array_values(array_unique($warn))]);
    }

    /** GET /admin/boutique/modeles/{id}/pdf : fichier d'impression (toutes les faces dessinées). */
    public static function pdf(Request $req, string $id): Response
    {
        $m = Catalog::find($id);
        if (!$m) {
            return self::back('/admin/boutique/modeles', null, 'Modèle introuvable.');
        }
        $sup = Catalog::support($m['support']);
        $values = self::sample($m);
        foreach (Catalog::fields($m) as $f => $_) {
            if (($v = trim($req->str('v_' . $f))) !== '') {
                $values[$f] = $v;
            }
        }
        $faces = [];
        $colorName = (string) (array_search($m['color'], $sup['colors'], true) ?: '');
        foreach ($m['faces'] as $fk => $f) {
            if ($f['layers'] || $f['bg']) {
                $faces[] = ['name' => $m['name'] . ' · ' . $sup['name'] . ' · ' . $f['label'] . ' · ' . $f['w'] . ' × ' . $f['h'] . ' mm' . ($f['bleed'] ? ' + ' . $f['bleed'] . ' mm de fonds perdus' : '') . ($colorName !== '' ? ' · support ' . $colorName : ''), 'side' => $f, 'values' => $values];
            }
        }
        if (!$faces) {
            return self::back('/admin/boutique/modeles/' . $id, null, 'Rien à imprimer : le modèle est vide.');
        }
        $bytes = Vector::pdf($faces, ['title' => $m['name'], 'cmyk' => $req->str('rvb') !== '1']);
        $name = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(\App\Data\Names::ascii($m['name']))), '-') ?: 'modele';
        return new Response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="impression-' . $name . '.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    /** Valeurs d'exemple des champs du client (le texte par défaut de chaque champ). */
    private static function sample(array $m): array
    {
        return array_map(fn ($f) => $f['default'], Catalog::fields($m));
    }
}
