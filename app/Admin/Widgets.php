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
use App\Widgets\Registry;
use App\Widgets\Store;

/**
 * Communauté › Widgets & partenaires (administrateurs) : catalogue avec aperçu en direct, accès de
 * chaque widget, configurateur (code iframe et adresse de l'API à copier), partenaires (clé,
 * domaines, statut, offre) et statistiques (affichages, appels de l'API, clics vers le musée).
 */
final class Widgets extends Base
{
    private const TABS = ['catalogue' => 'Catalogue & configurateur', 'partenaires' => 'Partenaires', 'statistiques' => 'Statistiques'];

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
            'closed' => \App\Front\Seo::closed(),
            'newKey' => Session::pull('widget_new_key'),
            'edit' => $req->str('partenaire') !== '' ? Store::partner($req->str('partenaire')) : null,
        ], ['title' => 'Widgets & partenaires', 'crumb' => 'Communauté', 'nav' => 'widgets', 'tabs' => $tabs, 'scripts' => ['admin/widgets.js']]);
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
                    Store::saveWidget($key, (string) ($p['access'] ?? ''), !empty($p['closed_ok']));
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
                case 'cache':
                    $n = Cache::clear();
                    return self::back('/admin/widgets', 'Cache des widgets vidé (' . $n . ' fichier' . ($n > 1 ? 's' : '') . ').');
            }
        } catch (\InvalidArgumentException $e) {
            return self::back('/admin/widgets?onglet=partenaires' . (($p['id'] ?? '') !== '' ? '&partenaire=' . rawurlencode((string) $p['id']) : ''), null, $e->getMessage());
        }
        return self::back('/admin/widgets', null, 'Action inconnue.');
    }
}
