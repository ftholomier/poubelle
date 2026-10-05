<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\JsonStore;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Data\Collections as Store;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Media;
use App\Data\Seeds;
use App\Front\Donations;
use App\Front\Interactive;

/**
 * Contenus des outils interactifs et des pages éditoriales : quiz, frise, maillots,
 * épopées et lieux de la carte, partenaires, page « Faire un don ». Un schéma par
 * collection décrit les champs (avec leur version anglaise) ; l'écran et
 * l'enregistrement sont génériques. Plus : hub « Interactif », Onze de légende, album.
 */
final class Collections extends Base
{
    /**
     * Champs : [libellé, type, options]. Types : text, int, image, bool, select, latlng, answers, href.
     * « en » => true ajoute la version anglaise (champ « {nom}_en ») avec un bouton de traduction.
     */
    public static function schemas(): array
    {
        return [
            'quiz' => [
                'label' => 'Quiz supporters', 'front' => '/interactif/quiz/', 'item' => 'Question', 'title' => 'q',
                'help' => 'Six questions sont tirées au hasard à chaque partie parmi les questions affichées. Les questions proposées au lancement sont « à valider » par un historien.',
                'default' => fn () => Seeds::quiz(),
                'fields' => [
                    'q' => ['Question', 'text', ['max' => 200, 'full' => true, 'en' => true]],
                    'a' => ['Réponses (4)', 'answers', ['en' => true]],
                    'c' => ['Bonne réponse', 'select', ['options' => ['0' => 'Réponse A', '1' => 'Réponse B', '2' => 'Réponse C', '3' => 'Réponse D']]],
                    'fact' => ['Le saviez-vous ? (après la réponse)', 'text', ['max' => 300, 'full' => true, 'en' => true]],
                    'active' => ['Affichée dans le quiz', 'bool', ['default' => true]],
                    'validated' => ['Validée par un historien', 'bool', []],
                ],
            ],
            'frise' => [
                'label' => 'Frise chronologique', 'front' => '/interactif/frise/', 'item' => 'Date', 'title' => 'title', 'sort' => 'year',
                'help' => 'Les dates sont triées automatiquement par année.',
                'default' => fn () => Seeds::frise(),
                'fields' => [
                    'year' => ['Année', 'int', ['min' => 1900, 'max' => 2100]],
                    'title' => ['Titre', 'text', ['max' => 120, 'en' => true]],
                    'text' => ['Texte', 'text', ['max' => 400, 'full' => true, 'en' => true]],
                    'tone' => ['Mise en valeur', 'select', ['options' => ['' => 'Normale', 'y' => 'Jaune (titre, trophée)', 'n' => 'Marine (date clé)']]],
                    'image' => ['Image', 'image', []],
                    'href' => ['Lien (fiche, rubrique…)', 'href', []],
                    'validated' => ['Validée par un historien', 'bool', []],
                ],
            ],
            'maillots' => [
                'label' => 'Comparateur de maillots', 'front' => '/interactif/maillots/', 'item' => 'Époque', 'title' => 'label',
                'help' => 'Une photo par époque, de préférence cadrée de la même façon (maillot de face, fond neutre).',
                'default' => fn () => Seeds::maillots(),
                'fields' => [
                    'era' => ['Année de référence', 'text', ['max' => 10]],
                    'label' => ['Intitulé', 'text', ['max' => 60, 'en' => true]],
                    'image' => ['Photo du maillot', 'image', []],
                    'text' => ['Description', 'text', ['max' => 300, 'full' => true, 'en' => true]],
                ],
            ],
            'epopees' => [
                'label' => 'Carte : grandes épopées', 'front' => '/interactif/carto/', 'item' => 'Étape', 'title' => 't',
                'help' => 'Finales et grands matchs européens placés sur la carte. Sans coordonnées, l’étape n’apparaît pas.',
                'default' => fn () => Seeds::epopees(),
                'fields' => [
                    'y' => ['Année', 'int', ['min' => 1928, 'max' => 2100]],
                    's' => ['Saison', 'text', ['max' => 9, 'placeholder' => '2006-2007']],
                    'type' => ['Catégorie', 'text', ['max' => 60, 'en' => true, 'placeholder' => 'Finales nationales']],
                    't' => ['Lieu', 'text', ['max' => 80, 'placeholder' => 'Stade de France']],
                    'h' => ['Compétition / titre', 'text', ['max' => 100, 'en' => true]],
                    'p' => ['Texte', 'text', ['max' => 300, 'full' => true, 'en' => true]],
                    'll' => ['Coordonnées', 'latlng', []],
                    'win' => ['Victoire', 'bool', []],
                    'href' => ['Lien vers la fiche', 'href', []],
                    'validated' => ['Validée par un historien', 'bool', []],
                ],
            ],
            'lieux' => [
                'label' => 'Carte : lieux du club', 'front' => '/interactif/carto/', 'item' => 'Lieu', 'title' => 'n',
                'help' => 'Stades d’entraînement, sièges, lieux de mémoire… affichés sur la carte.',
                'default' => fn () => Seeds::lieux(),
                'fields' => [
                    'n' => ['Nom', 'text', ['max' => 100, 'en' => true]],
                    't' => ['Type', 'text', ['max' => 40, 'en' => true, 'placeholder' => 'Stade, Patrimoine…']],
                    'd' => ['Description', 'text', ['max' => 300, 'full' => true, 'en' => true]],
                    'll' => ['Coordonnées', 'latlng', []],
                    'href' => ['Lien', 'href', []],
                    'validated' => ['Validé par un historien', 'bool', []],
                ],
            ],
            'partenaires' => [
                'label' => 'Partenaires', 'front' => '/contact/', 'item' => 'Partenaire', 'title' => 'name',
                'help' => 'Logos affichés sur la page Contact & partenaires, dans cet ordre.',
                'default' => fn () => [],
                'fields' => [
                    'name' => ['Nom', 'text', ['max' => 100]],
                    'logo' => ['Logo', 'image', []],
                    'url' => ['Site web', 'href', []],
                ],
            ],
            'dictionnaire' => [
                'label' => 'Dictionnaire du musée', 'front' => '', 'item' => 'Mot', 'title' => 'mot', 'sort_alpha' => 'mot',
                'hub' => false, 'no_tr' => true, 'no_proof' => true, 'nav' => 'qualite', 'crumb_html' => 'Pilotage › <a href="/admin/qualite?cat=orthographe">Qualité</a>',
                'help' => 'Mots que le correcteur d’orthographe ne doit jamais corriger : noms propres, surnoms, mots du club ou du patois. Les noms des joueurs, clubs et stades du musée sont déjà reconnus.',
                'default' => fn () => [],
                'fields' => [
                    'mot' => ['Mot ou expression', 'text', ['max' => 80, 'placeholder' => 'Lionceaux']],
                    'note' => ['Remarque', 'text', ['max' => 160, 'full' => true, 'placeholder' => 'Surnom des joueurs du centre de formation']],
                ],
            ],
            'dons' => [
                'label' => 'Page « Faire un don »', 'front' => '/faire-un-don/', 'object' => true,
                'help' => 'Textes de la page de dons. Les montants, la jauge et les moyens de paiement se règlent dans Réglages › Dons.',
                'default' => fn () => Donations::defaultContent(),
                'fields' => [
                    'title' => ['Titre (« | » = retour à la ligne)', 'text', ['max' => 120, 'en' => true, 'full' => true]],
                    'lead' => ['Accroche', 'text', ['max' => 400, 'en' => true, 'full' => true]],
                    'wall_text' => ['Texte du mur des donateurs', 'text', ['max' => 160, 'en' => true, 'full' => true]],
                    'default_tier' => ['Montant présélectionné', 'select', ['options' => ['0' => '1er palier', '1' => '2e palier', '2' => '3e palier', '3' => '4e palier']]],
                ],
                'lists' => [
                    'tiers' => ['Paliers de don', 'Palier', [
                        'amount' => ['Montant (€)', 'int', ['min' => 1, 'max' => 100000]],
                        'impact' => ['Ce que le don permet', 'text', ['max' => 140, 'en' => true, 'full' => true]],
                    ]],
                    'steps' => ['Ce que finance votre don', 'Étape', [
                        'title' => ['Titre', 'text', ['max' => 60, 'en' => true]],
                        'text' => ['Texte', 'text', ['max' => 300, 'en' => true, 'full' => true]],
                        'image' => ['Image', 'image', []],
                    ]],
                ],
            ],
        ];
    }

    public static function edit(Request $req, string $name): Response
    {
        $schema = self::schemas()[$name] ?? null;
        if (!$schema) {
            return self::html('admin/message', ['title' => 'Contenu introuvable', 'text' => 'Ce contenu n’existe pas.', 'back' => '/admin/interactif'], ['title' => 'Introuvable'], 404);
        }
        $data = Store::get($name, null);
        $isDefault = $data === null;
        $data ??= ($schema['default'])();
        if (!empty($schema['object'])) {
            $data += ($schema['default'])();
        }
        $versions = JsonStore::read(STORAGE_PATH . "/versions/collections/$name/index.json", []) ?: [];
        return self::html('admin/collections/edit', ['name' => $name, 'schema' => $schema, 'data' => $data, 'isDefault' => $isDefault, 'versions' => array_reverse(array_slice($versions, -8))], [
            'title' => $schema['label'], 'crumb_html' => $schema['crumb_html'] ?? 'Interactif › <a href="/admin/interactif">Quiz, frise, carte…</a>', 'nav' => $schema['nav'] ?? ($name === 'dons' ? 'dons' : 'interactif'),
            'tips' => $name === 'dictionnaire' ? 'dictionnaire' : null,
        ]);
    }

    public static function save(Request $req, string $name): Response
    {
        $schema = self::schemas()[$name] ?? null;
        if (!$schema) {
            return self::json(['error' => 'Contenu inconnu.'], 404);
        }
        if ($locked = self::lockedJson("collection:$name", 'ce contenu')) {
            return $locked;
        }
        $in = $req->json();
        if (!empty($schema['object'])) {
            $out = self::cleanItem((array) ($in['data'] ?? []), $schema['fields']);
            foreach ($schema['lists'] ?? [] as $key => [, , $fields]) {
                $out[$key] = [];
                foreach ((array) ($in['data'][$key] ?? []) as $it) {
                    $clean = self::cleanItem((array) $it, $fields);
                    if (self::filled($clean, $fields)) {
                        $out[$key][] = $clean;
                    }
                }
            }
        } else {
            $out = [];
            foreach ((array) ($in['items'] ?? []) as $it) {
                $clean = self::cleanItem((array) $it, $schema['fields']);
                if (self::filled($clean, $schema['fields'])) {
                    $out[] = $clean;
                }
            }
            if (!empty($schema['sort'])) {
                $k = $schema['sort'];
                usort($out, fn ($a, $b) => (int) ($a[$k] ?? 0) <=> (int) ($b[$k] ?? 0));
            }
            if (!empty($schema['sort_alpha'])) {
                $k = $schema['sort_alpha'];
                usort($out, fn ($a, $b) => strcmp(\App\Data\Paths::slug((string) ($a[$k] ?? '')), \App\Data\Paths::slug((string) ($b[$k] ?? ''))));
            }
        }
        Store::save($name, $out, self::actor(), 'Modification de ' . Store::label($name));
        if (in_array($name, ['epopees', 'lieux'], true)) {
            \App\Services\Geo::forget();
        }
        return self::json(['ok' => true, 'message' => $schema['label'] . ' : enregistré.', 'modified' => date('c'), 'savedLabel' => 'Enregistré à ' . date('H:i'), 'reload' => !empty($schema['sort']) || !empty($schema['sort_alpha'])]);
    }

    /** Un élément contient-il autre chose que des cases à cocher ? */
    private static function filled(array $item, array $fields): bool
    {
        foreach ($fields as $k => [, $type]) {
            if ($type === 'bool' || $type === 'select') {
                continue;
            }
            $v = $item[$k] ?? null;
            if ($v !== null && $v !== '' && $v !== [] && $v !== ['', '', '', '']) {
                return true;
            }
        }
        return false;
    }

    private static function cleanItem(array $in, array $fields): array
    {
        $out = [];
        foreach ($fields as $k => [$label, $type, $o]) {
            $v = $in[$k] ?? null;
            $out[$k] = match ($type) {
                'int' => ($v === null || $v === '') ? null : max((int) ($o['min'] ?? PHP_INT_MIN), min((int) ($o['max'] ?? PHP_INT_MAX), (int) $v)),
                'bool' => (bool) $v,
                'select' => isset($o['options'][(string) $v]) ? (ctype_digit((string) $v) ? (int) $v : (string) $v) : (string) array_key_first($o['options']),
                'image' => ($r = Html::line($v, 300)) !== '' && Media::get($r) ? $r : null,
                'href' => self::href($v),
                'latlng' => self::latlng($in[$k] ?? null),
                'answers' => array_map(fn ($a) => Html::line($a, 120), array_pad(array_slice(array_values((array) $v), 0, 4), 4, '')),
                default => Html::line($v, (int) ($o['max'] ?? 300)),
            };
            if (!empty($o['en'])) {
                $ve = $in[$k . '_en'] ?? null;
                $out[$k . '_en'] = $type === 'answers'
                    ? array_map(fn ($a) => Html::line($a, 120), array_pad(array_slice(array_values((array) $ve), 0, 4), 4, ''))
                    : Html::line($ve, (int) ($o['max'] ?? 300));
                if ($type === 'answers' && !array_filter($out[$k . '_en'])) {
                    $out[$k . '_en'] = [];
                }
            }
        }
        if (isset($out['a'])) {
            // Réponses laissées vides : retirées, en gardant l'alignement FR/EN et la bonne réponse.
            $keep = array_keys(array_filter($out['a'], fn ($x) => $x !== ''));
            $pick = fn (array $list) => array_values(array_map(fn ($i) => $list[$i] ?? '', $keep));
            $correct = array_search((int) ($out['c'] ?? 0), $keep, true);
            $out['a'] = $pick($out['a']);
            if (!empty($out['a_en'])) {
                $out['a_en'] = $pick($out['a_en']);
            }
            $out['c'] = $correct === false ? 0 : (int) $correct;
        }
        return $out;
    }

    private static function href(mixed $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $v)) {
            return mb_substr($v, 0, 300);
        }
        return '/' . ltrim(mb_substr($v, 0, 300), '/');
    }

    private static function latlng(mixed $v): ?array
    {
        if (!is_array($v)) {
            return null;
        }
        $lat = str_replace(',', '.', trim((string) ($v['lat'] ?? $v[0] ?? '')));
        $lng = str_replace(',', '.', trim((string) ($v['lng'] ?? $v[1] ?? '')));
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return null;
        }
        return [round((float) $lat, 6), round((float) $lng, 6)];
    }

    // ------------------------------------------------------------------ hub « Interactif »

    public static function hub(Request $req): Response
    {
        $cards = [];
        foreach (self::schemas() as $name => $s) {
            if ($name === 'dons' || ($s['hub'] ?? true) === false) {
                continue;
            }
            $data = Store::get($name, null) ?? ($s['default'])();
            $n = count($data);
            $todo = isset($s['fields']['validated']) ? count(array_filter($data, fn ($x) => empty($x['validated']))) : 0;
            $noEn = 0;
            foreach ($data as $it) {
                foreach ($s['fields'] as $k => [, , $o]) {
                    if (!empty($o['en']) && !empty($it[$k]) && empty($it[$k . '_en'])) {
                        $noEn++;
                        break;
                    }
                }
            }
            $cards[] = ['name' => $name, 'label' => $s['label'], 'front' => $s['front'], 'count' => $n, 'item' => $s['item'], 'todo' => $todo, 'noEn' => $noEn, 'default' => Store::get($name, null) === null];
        }
        $moments = Interactive::moments100();
        $album = count(array_filter(Index::published('personne'), fn ($s) => !empty($s['p']['album']['in'])));
        return self::html('admin/collections/hub', [
            'cards' => $cards, 'voters' => Interactive::onzeVoters(), 'album' => $album,
            'momentsOpen' => count(array_filter($moments, fn ($m) => $m['open'])),
            'stades' => count(Store::get('stades', [])), 'stadesMissing' => count(array_filter(Store::get('stades', []), fn ($x) => empty($x['lat']))),
            'retro' => count(array_filter(\App\Services\RetroDirect::program(null, false), fn ($e) => $e['state'] !== 'termine')),
            'walls' => count(\App\Services\PhotoWall::photos()),
        ], ['title' => 'Quiz, frise, carte…', 'crumb' => 'Interactif', 'nav' => 'interactif']);
    }

    // ------------------------------------------------------------------ Onze de légende

    public static function onze(Request $req): Response
    {
        $results = Interactive::onzeResults();
        $raw = JsonStore::read(STORAGE_PATH . '/votes/onze.json', []) ?: [];
        $lines = [];
        foreach (['G' => 'Gardiens', 'D' => 'Défenseurs', 'M' => 'Milieux', 'A' => 'Attaquants'] as $l => $label) {
            $list = $raw['lines'][$l] ?? [];
            arsort($list);
            $rows = [];
            foreach (array_slice($list, 0, 12, true) as $id => $n) {
                $s = Index::get((int) $id);
                $rows[] = ['id' => (int) $id, 'name' => $s['p']['name'] ?? "Fiche $id", 'n' => (int) $n];
            }
            $lines[$l] = ['label' => $label, 'rows' => $rows];
        }
        $cands = Interactive::onzeCandidates();
        $byLine = array_count_values(array_column($cands, 'line'));
        return self::html('admin/collections/onze', [
            'results' => $results, 'voters' => Interactive::onzeVoters(), 'lines' => $lines, 'candidates' => count($cands), 'byLine' => $byLine,
            'reveal' => (string) Settings::get('centenary.onze_reveal', ''), 'canReset' => Auth::can('destroy'),
            'noLine' => count(array_filter(Index::published('personne'), fn ($s) => in_array('joueur', $s['p']['roles'], true) && empty($s['p']['line']))),
        ], ['title' => 'Onze de légende', 'crumb' => 'Interactif', 'nav' => 'onze', 'tabs' => [['Onze de légende', '/admin/onze', true], ['Album du centenaire', '/admin/album', false]]]);
    }

    public static function onzeSave(Request $req): Response
    {
        $action = (string) ($req->post['action'] ?? '');
        if ($action === 'date') {
            $d = (string) ($req->post['reveal'] ?? '');
            Settings::save(['centenary.onze_reveal' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '']);
            \App\Data\Activity::log(self::actor(), 'a modifié la date de dévoilement du Onze', ['title' => $d]);
            return self::back('/admin/onze', 'Date de dévoilement enregistrée.');
        }
        if ($action === 'reinitialiser') {
            if (!Auth::can('destroy')) {
                return self::back('/admin/onze', null, 'Réservé aux administrateurs.');
            }
            $file = STORAGE_PATH . '/votes/onze.json';
            if (is_file($file)) {
                copy($file, STORAGE_PATH . '/votes/onze-' . date('Ymd-His') . '.json');
            }
            JsonStore::write($file, ['voters' => 0, 'lines' => ['G' => [], 'D' => [], 'M' => [], 'A' => []], 'seen' => []]);
            \App\Data\Activity::log(self::actor(), 'a remis à zéro les votes du Onze', null);
            return self::back('/admin/onze', 'Votes remis à zéro (une copie des anciens résultats est conservée).');
        }
        return self::back('/admin/onze', null, 'Action inconnue.');
    }

    // ------------------------------------------------------------------ album du centenaire

    public static function album(Request $req): Response
    {
        $tot = Derived::part('person_totals');
        $in = [];
        foreach (Index::all() as $s) {
            if ($s['type'] === 'personne' && $s['status'] !== 'corbeille' && !empty($s['p']['album']['in'])) {
                $in[] = ['id' => $s['id'], 'name' => $s['p']['name'], 'image' => $s['image'], 'number' => $s['p']['album']['number'] ?? null, 'rarity' => $s['p']['album']['rarity'] ?? '', 'matches' => $tot[$s['id']]['matches'] ?? 0, 'status' => $s['status']];
            }
        }
        usort($in, fn ($a, $b) => ($a['number'] ?? 9999) <=> ($b['number'] ?? 9999) ?: strcoll($a['name'], $b['name']));
        $auto = !$in ? Interactive::albumCards() : [];
        return self::html('admin/collections/album', ['cards' => $in, 'auto' => $auto], ['title' => 'Album du centenaire', 'crumb' => 'Interactif', 'nav' => 'onze', 'tabs' => [['Onze de légende', '/admin/onze', false], ['Album du centenaire', '/admin/album', true]]]);
    }

    public static function albumSave(Request $req): Response
    {
        if ($locked = self::lockedJson('collection:album', 'l’album')) {
            return $locked;
        }
        $in = $req->json();
        $user = self::actor();
        $wanted = [];
        foreach ((array) ($in['cards'] ?? []) as $i => $c) {
            $id = (int) ($c['id'] ?? 0);
            if ($id > 0 && !isset($wanted[$id])) {
                $rarity = in_array($c['rarity'] ?? '', ['legende', 'actuel', 'classique'], true) ? $c['rarity'] : null;
                $wanted[$id] = ['in' => true, 'number' => $i + 1, 'rarity' => $rarity];
            }
        }
        $changed = 0;
        Fiches::batch(function () use ($wanted, $user, &$changed) {
            foreach (Index::all() as $s) {
                if ($s['type'] !== 'personne') {
                    continue;
                }
                $cur = $s['p']['album'] ?? ['in' => false];
                $new = $wanted[$s['id']] ?? ['in' => false, 'number' => null, 'rarity' => null];
                if ((bool) ($cur['in'] ?? false) === $new['in'] && (!$new['in'] || (($cur['number'] ?? null) === $new['number'] && ($cur['rarity'] ?? null) === $new['rarity']))) {
                    continue;
                }
                $doc = Fiches::get((int) $s['id']);
                if (!$doc) {
                    continue;
                }
                $doc['personne']['album'] = $new;
                Fiches::save($doc, $user, $new['in'] ? 'Album : carte n° ' . $new['number'] : 'Retirée de l’album');
                $changed++;
            }
        });
        return self::json(['ok' => true, 'message' => $changed ? "$changed fiche(s) mise(s) à jour." : 'Aucune modification.', 'modified' => date('c'), 'savedLabel' => 'Enregistré à ' . date('H:i'), 'reload' => $changed > 0]);
    }
}
