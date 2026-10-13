<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Data\Activity;
use App\Widgets\Cache;
use App\Widgets\Context;
use App\Widgets\Pages;
use App\Widgets\Items\HistoireDuDuel;
use App\Widgets\Registry;
use App\Widgets\Screen\Export;
use App\Widgets\Screen\Playlists;
use App\Widgets\Store;

/**
 * Communauté › Widgets & partenaires (administrateurs) : catalogue avec aperçu en direct, accès de
 * chaque widget et ses réglages propres, configurateur (code script, code iframe et adresse de l'API à copier), partenaires (clé,
 * domaines, statut, offre), écrans (playlists du mode écran : liste, création et modification,
 * aperçu plein écran, lien pour la régie, export PNG et vidéo) et statistiques (affichages, appels
 * de l'API, clics vers le musée).
 */
final class Widgets extends Base
{
    private const TABS = ['catalogue' => 'Catalogue & configurateur', 'ecrans' => 'Écrans', 'partenaires' => 'Partenaires', 'statistiques' => 'Statistiques'];

    public static function index(Request $req): Response
    {
        $tab = isset(self::TABS[$req->str('onglet')]) ? $req->str('onglet') : 'catalogue';
        $partners = Store::partners();
        uasort($partners, fn ($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']));
        $widgets = [];
        foreach (Registry::all() as $k => $w) {
            $widgets[$k] = ['w' => $w, 'cfg' => Store::widget($k), 'describe' => $w->describe('fr')];
        }
        $tabs = [];
        foreach (self::TABS as $k => $label) {
            $tabs[] = [$label, '/admin/widgets' . ($k === 'catalogue' ? '' : '?onglet=' . $k), $k === $tab, $k === 'partenaires' ? (string) count($partners) : ''];
        }
        // Configurateur : partenaires (avec leur clé, que seul l'administrateur voit) pour le code à copier.
        $keys = [];
        foreach ($partners as $id => $p) {
            $keys[] = ['id' => $id, 'name' => (string) $p['name'], 'key' => Store::keyOf($p), 'status' => (string) $p['status'], 'paid' => Store::paid($p), 'domains' => (array) $p['domains']];
        }
        return self::html('admin/community/widgets', [
            'tab' => $tab,
            'widgets' => $widgets,
            'planned' => Registry::PLANNED,
            'partners' => $partners,
            'keys' => $keys,
            'stats' => $tab === 'statistiques' ? Store::stats(max(7, min(365, (int) $req->str('jours', '30')))) : null,
            'days' => max(7, min(365, (int) $req->str('jours', '30'))),
            'base' => Context::base(),
            'resize' => Pages::resizeScript(),
            'loader' => Pages::loader(),
            'closed' => \App\Front\Seo::closed(),
            'newKey' => Session::pull('widget_new_key'),
            'edit' => $req->str('partenaire') !== '' ? Store::partner($req->str('partenaire')) : null,
            'screens' => $tab === 'ecrans' ? self::screens($req) : null,
        ], ['title' => 'Widgets & partenaires', 'crumb' => 'Communauté', 'nav' => 'widgets', 'tabs' => $tabs, 'scripts' => ['admin/widgets.js']]);
    }

    /** Onglet Écrans : playlists, playlist en cours de modification, exports, adversaires possibles. */
    private static function screens(Request $req): array
    {
        $all = Playlists::all();
        $sel = $req->str('ecran');
        $edit = null;
        if ($sel === 'nouveau') {
            $edit = ['id' => '', 'name' => '', 'preset' => false, 'slides' => Playlists::presets()['mi-temps']['slides']] + Playlists::defaults();
        } elseif (isset($all[$sel])) {
            $edit = $all[$sel];
        }
        $exports = [];
        foreach ($all as $id => $p) {
            $k = Export::key($id, (string) $p['format'], (string) $p['lang'], (string) $p['adversaire']);
            $exports[$id] = ['key' => $k, 'video' => Export::status($k)];
        }
        return [
            'all' => $all,
            'edit' => $edit,
            'exports' => $exports,
            'types' => array_map(fn ($t) => ['name' => $t[0], 'duration' => $t[1], 'options' => $t[2]], Playlists::TYPES),
            'clubs' => HistoireDuDuel::choices(),
            'access' => Store::widget('playlist')['access'],
            'video' => Export::videoUnavailable(),
        ];
    }

    public static function action(Request $req): Response
    {
        $p = $req->post;
        $action = (string) ($p['action'] ?? '');
        $who = self::actor();
        try {
            switch ($action) {
                case 'acces':
                    $key = (string) ($p['widget'] ?? '');
                    if (!Registry::get($key)) {
                        return self::back('/admin/widgets', null, 'Widget inconnu.');
                    }
                    Store::saveWidget($key, (string) ($p['access'] ?? ''), !empty($p['closed_ok']), isset($p['settings']) && is_array($p['settings']) ? array_map('strval', array_filter($p['settings'], 'is_scalar')) : null);
                    Cache::clear();
                    Activity::log($who, 'a réglé l’accès du widget « ' . $key . ' » : ' . (Store::ACCESS[(string) $p['access']] ?? ''), null);
                    return self::back('/admin/widgets#w-' . $key, 'Accès du widget enregistré.');
                case 'partenaire':
                    $id = (string) ($p['id'] ?? '');
                    $new = $id === '' || !Store::partner($id);
                    $saved = Store::savePartner($new ? null : $id, $p);
                    if ($new) {
                        Session::set('widget_new_key', ['id' => $saved['id'], 'key' => Store::keyOf($saved)]);
                    }
                    Activity::log($who, ($new ? 'a ajouté' : 'a modifié') . ' le partenaire des widgets « ' . $saved['name'] . ' »', null);
                    return self::back('/admin/widgets?onglet=partenaires#p-' . $saved['id'], $new ? 'Partenaire ajouté : sa clé est affichée ci-dessous.' : 'Partenaire enregistré.');
                case 'cle':
                    $id = (string) ($p['id'] ?? '');
                    $key = Store::regenerate($id);
                    Session::set('widget_new_key', ['id' => $id, 'key' => $key]);
                    Activity::log($who, 'a régénéré la clé du partenaire des widgets « ' . (Store::partner($id)['name'] ?? $id) . ' »', null);
                    return self::back('/admin/widgets?onglet=partenaires#p-' . $id, 'Nouvelle clé créée : l’ancienne ne fonctionne plus. Transmettez la nouvelle au partenaire.');
                case 'supprimer':
                    $id = (string) ($p['id'] ?? '');
                    $name = (string) (Store::partner($id)['name'] ?? $id);
                    Store::deletePartner($id);
                    Activity::log($who, 'a supprimé le partenaire des widgets « ' . $name . ' »', null);
                    return self::back('/admin/widgets?onglet=partenaires', 'Partenaire supprimé : sa clé ne fonctionne plus.');
                case 'ecran-supprimer':
                    $id = (string) ($p['id'] ?? '');
                    $pl = Playlists::custom()[$id] ?? null;
                    if (!$pl) {
                        return self::back('/admin/widgets?onglet=ecrans', null, 'Playlist introuvable (les playlists prêtes à l’emploi ne se suppriment pas).');
                    }
                    Playlists::delete($id);
                    if (isset(Playlists::presets()[$id])) {
                        Activity::log($who, 'a rétabli la playlist d’écran « ' . Playlists::presets()[$id]['name'] . ' » d’origine', null);
                        return self::back('/admin/widgets?onglet=ecrans', 'Playlist rétablie dans sa version d’origine.');
                    }
                    Activity::log($who, 'a supprimé la playlist d’écran « ' . $pl['name'] . ' »', null);
                    return self::back('/admin/widgets?onglet=ecrans', 'Playlist supprimée : son adresse d’écran ne fonctionne plus.');
                case 'ecran-dupliquer':
                    $src = Playlists::get((string) ($p['id'] ?? ''));
                    if (!$src) {
                        return self::back('/admin/widgets?onglet=ecrans', null, 'Playlist introuvable.');
                    }
                    $copy = Playlists::save(null, ['name' => $src['name'] . ' (copie)'] + $src);
                    Activity::log($who, 'a créé la playlist d’écran « ' . $copy['name'] . ' »', null);
                    return self::back('/admin/widgets?onglet=ecrans&ecran=' . rawurlencode($copy['id']) . '#editeur', 'Copie créée : modifiez-la ci-dessous.');
                case 'ecran-video':
                    $pl = Playlists::get((string) ($p['id'] ?? ''));
                    if (!$pl) {
                        return self::back('/admin/widgets?onglet=ecrans', null, 'Playlist introuvable.');
                    }
                    try {
                        Export::requestVideo($pl, (string) $pl['format'], (string) $pl['lang'], (string) $pl['adversaire']);
                    } catch (\RuntimeException $e) {
                        return self::back('/admin/widgets?onglet=ecrans#e-' . $pl['id'], null, $e->getMessage());
                    }
                    Activity::log($who, 'a demandé la vidéo de la playlist d’écran « ' . $pl['name'] . ' »', null);
                    return self::back('/admin/widgets?onglet=ecrans#e-' . $pl['id'], 'Vidéo en préparation (une à deux minutes, davantage pour une longue playlist) : rechargez la page pour la télécharger.');
                case 'cache':
                    $n = Cache::clear();
                    return self::back('/admin/widgets', 'Cache des widgets vidé (' . $n . ' fichier' . ($n > 1 ? 's' : '') . ').');
            }
        } catch (\InvalidArgumentException $e) {
            return self::back('/admin/widgets?onglet=partenaires' . (($p['id'] ?? '') !== '' ? '&partenaire=' . rawurlencode((string) $p['id']) : ''), null, $e->getMessage());
        }
        return self::back('/admin/widgets', null, 'Action inconnue.');
    }

    /** Enregistre une playlist (formulaire JSON de l'onglet Écrans). */
    public static function screenSave(Request $req): Response
    {
        $in = $req->json();
        $id = (string) ($in['id'] ?? '');
        if ($id !== '' && !isset(Playlists::custom()[$id]) && !isset(Playlists::presets()[$id])) {
            return Response::json(['ok' => false, 'error' => 'Playlist introuvable.'], 422);
        }
        try {
            $saved = Playlists::save($id !== '' ? $id : null, $in);
        } catch (\InvalidArgumentException $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        Cache::clear();
        Activity::log(self::actor(), ($id === '' ? 'a créé' : 'a modifié') . ' la playlist d’écran « ' . $saved['name'] . ' »', null);
        return Response::json(['ok' => true, 'message' => 'Playlist enregistrée.', 'redirect' => '/admin/widgets?onglet=ecrans&ecran=' . rawurlencode($saved['id']) . '#editeur']);
    }

    /**
     * Contrôle avant diffusion (POST JSON, jeton CSRF en en-tête X-CSRF) : playlist enregistrée (id)
     * ou en cours de modification (slides, adversaire, lang), réglages du lien de la régie (adversaire,
     * lang) et jour (AAAA-MM-JJ, aujourd'hui par défaut) ; chaque slide affichée ou sautée et pourquoi,
     * durée réelle d'une boucle (Slides::check, mêmes calculs que l'écran).
     */
    public static function screenCheck(Request $req): Response
    {
        $in = $req->json();
        $pl = Playlists::get((string) ($in['id'] ?? ''));
        if (isset($in['slides']) && is_array($in['slides'])) {
            $pl = ['id' => (string) ($in['id'] ?? ''), 'name' => (string) ($in['name'] ?? ''), 'adversaire' => Playlists::opponent((string) ($in['playlist_adversaire'] ?? $in['adversaire'] ?? '')),
                'lang' => ($in['playlist_lang'] ?? $in['lang'] ?? 'fr') === 'en' ? 'en' : 'fr', 'slides' => Playlists::cleanSlides($in['slides'])];
        }
        if (!$pl) {
            return Response::json(['ok' => false, 'error' => 'Playlist introuvable.'], 422);
        }
        if (!$pl['slides']) {
            return Response::json(['ok' => false, 'error' => 'Ajoutez au moins une slide.'], 422);
        }
        $raw = trim((string) ($in['adversaire'] ?? ''));
        $adv = Playlists::opponent($raw);
        $lang = in_array($in['lang'] ?? '', ['fr', 'en'], true) ? (string) $in['lang'] : (string) ($pl['lang'] ?? 'fr');
        $day = (string) ($in['date'] ?? '');
        if (!preg_match('/^(\d{4})-(\d\d)-(\d\d)$/', $day, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $day = date('Y-m-d');
        }
        \App\Services\I18n::set($lang);
        $c = new \App\Widgets\Context('playlist', $lang, 'sombre', 'ecran', '', true);
        try {
            $r = \App\Widgets\Screen\Slides::check($pl, $c, $adv, $day);
        } finally {
            \App\Services\I18n::set('fr');
        }
        $o = $r['opponent'];
        $r['opponent']['text'] = match (true) {
            $raw !== '' && $adv === '' => 'Nom d’adversaire refusé (2 à 40 caractères : lettres, chiffres, espace, apostrophe, point, tiret) : adversaire automatique, ' . $o['name'] . '.',
            $o['name'] === '' => 'Aucun adversaire possible.',
            !$o['known'] => $o['name'] . ' : jamais affronté d’après les fiches publiées → version « Première rencontre ».',
            $o['auto'] => 'Automatique : ' . $o['name'] . ' (adversaire du dernier match fiché).',
            default => $o['name'] . ' : déjà affronté → bilan complet du duel.',
        };
        return Response::json(['ok' => true, 'date' => $day, 'lang' => $lang] + $r);
    }

    /** ZIP des images PNG d'une playlist (fabriqué à la demande). */
    public static function screenZip(Request $req): Response
    {
        $pl = Playlists::get($req->str('id'));
        if (!$pl) {
            return self::back('/admin/widgets?onglet=ecrans', null, 'Playlist introuvable.');
        }
        $format = isset(Playlists::FORMATS[$req->str('format')]) ? $req->str('format') : (string) $pl['format'];
        $lang = $req->str('lang') === 'en' ? 'en' : ($req->str('lang') === 'fr' ? 'fr' : (string) $pl['lang']);
        $adv = $req->str('adversaire') !== '' ? Playlists::opponent($req->str('adversaire')) : (string) $pl['adversaire'];
        try {
            $file = Export::zip($pl, $format, $lang, $adv);
        } catch (\Throwable $e) {
            return self::back('/admin/widgets?onglet=ecrans#e-' . $pl['id'], null, 'Images impossibles à préparer : ' . $e->getMessage());
        }
        return self::download($file, 'application/zip', 'ecran-' . basename($file));
    }

    /** Téléchargement d'un export déjà fabriqué (vidéo MP4). */
    public static function screenFile(Request $req): Response
    {
        $file = Export::file($req->str('f'));
        if (!$file) {
            return self::back('/admin/widgets?onglet=ecrans', null, 'Fichier introuvable : refaites l’export.');
        }
        return self::download($file, str_ends_with($file, '.mp4') ? 'video/mp4' : 'application/zip', 'ecran-' . basename($file));
    }

    private static function download(string $file, string $type, string $name): Response
    {
        $res = new Response('', 200, ['Content-Type' => $type, 'Content-Length' => (string) filesize($file), 'Cache-Control' => 'no-store',
            'Content-Disposition' => 'attachment; filename="' . $name . '"']);
        $res->file = $file;
        return $res;
    }
}
