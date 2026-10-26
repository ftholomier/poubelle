<?php
declare(strict_types=1);

namespace LD\Controllers;

use LD\Auth;
use LD\GoogleReviews;
use LD\Http;
use LD\Reviews;
use LD\Router;
use LD\Secrets;
use LD\Settings;
use LD\Store;

/**
 * Back-office : sources d'avis externes — synchronisation Google et widget d'un service tiers (code à coller).
 * La liste et l'édition des avis restent dans AdminContent.
 */
final class AdminReviews
{
    public static function routes(Router $r): void
    {
        $g = [Admin::class, 'guard'];
        // Déclarées avant « /admin/avis/{id} » (le premier motif qui correspond l'emporte).
        $r->get('/admin/avis/google', $g([self::class, 'google']));
        $r->post('/admin/avis/google', $g([self::class, 'saveGoogle'], true));
        $r->post('/admin/avis/google/synchroniser', $g([self::class, 'sync'], true));
        $r->post('/admin/avis/google/connexion', $g([self::class, 'connect'], true));
        $r->get('/admin/avis/google/retour', $g([self::class, 'callback']));
        $r->post('/admin/avis/google/etablissement', $g([self::class, 'chooseLocation'], true));
        $r->post('/admin/avis/google/deconnecter', $g([self::class, 'disconnect'], true));
        $r->post('/admin/avis/google/retirer', $g([self::class, 'purge'], true));
        $r->get('/admin/api/google/lieux', $g([self::class, 'searchPlaces']));
        $r->get('/admin/avis/widget', $g([self::class, 'widget']));
        $r->post('/admin/avis/widget', $g([self::class, 'saveWidget'], true));
    }

    // ------------------------------------------------------------------ Google

    public static function google(): never
    {
        $cfg = GoogleReviews::config();
        $connected = GoogleReviews::connected();
        $locations = [];
        $locError = '';
        if ($cfg['mode'] === 'business' && $connected) {
            try {
                $locations = GoogleReviews::locations(!empty($_GET['actualiser']));
            } catch (\RuntimeException $e) {
                $locError = $e->getMessage();
            }
        }
        $imported = count(array_filter(Reviews::all(), [Reviews::class, 'isGoogle']));
        Admin::view('reviews-google', [
            'cfg'       => $cfg,
            'state'     => GoogleReviews::state(),
            'connected' => $connected,
            'locations' => $locations,
            'locError'  => $locError,
            'imported'  => $imported,
            'redirect'  => GoogleReviews::redirectUri(),
            'keys'      => [
                'places' => Secrets::masked('google_places_key'),
                'secret' => Secrets::masked('google_client_secret'),
            ],
        ], 'Avis Google');
    }

    public static function saveGoogle(): never
    {
        $p = $_POST;
        $cfg = GoogleReviews::config();
        $mode = in_array($p['mode'] ?? '', ['off', 'places', 'business'], true) ? $p['mode'] : 'off';
        $placeId = trim((string) ($p['place_id'] ?? ''));
        if ($placeId !== '' && !preg_match('/^[A-Za-z0-9_-]{10,300}$/', $placeId)) {
            Admin::back('/admin/avis/google', 'err', 'Ce Place ID ne ressemble pas à un identifiant Google (ex. : ChIJ…).');
        }
        $clientId = trim((string) ($p['client_id'] ?? ''));
        if ($clientId !== '' && !preg_match('/^[A-Za-z0-9._-]{10,200}$/', $clientId)) {
            Admin::back('/admin/avis/google', 'err', 'Identifiant client OAuth invalide.');
        }
        if ($clientId !== $cfg['client_id'] && $cfg['client_id'] !== '' && GoogleReviews::connected()) {
            // Autre application OAuth : l'ancienne connexion ne vaut plus.
            GoogleReviews::disconnect();
        }
        foreach (['google_places_key' => 'places_key', 'google_client_secret' => 'client_secret'] as $secret => $field) {
            $v = trim((string) ($p[$field] ?? ''));
            if ($v !== '') {
                Secrets::set($secret, mb_substr($v, 0, 300));
            }
        }
        $cfg = array_merge($cfg, [
            'mode'         => $mode,
            'place_id'     => $mode === 'places' ? $placeId : $cfg['place_id'],
            'place_name'   => $mode === 'places' ? clean_text((string) ($p['place_name'] ?? ''), 160) : $cfg['place_name'],
            'client_id'    => $clientId,
            'min_rating'   => max(1, min(5, (int) ($p['min_rating'] ?? 1))),
            'hide_empty'   => !empty($p['hide_empty']),
            'auto_publish' => !empty($p['auto_publish']),
            'show_badge'   => !empty($p['show_badge']),
        ]);
        GoogleReviews::saveConfig($cfg);
        if (!empty($p['sync_now']) && self::readyToSync($cfg)) {
            self::runSync();
        }
        Admin::back('/admin/avis/google', 'ok', 'Réglages Google enregistrés.');
    }

    private static function readyToSync(array $cfg): bool
    {
        return match ($cfg['mode']) {
            'places'   => $cfg['place_id'] !== '' && Secrets::has('google_places_key'),
            'business' => $cfg['location'] !== '' && GoogleReviews::connected(),
            default    => false,
        };
    }

    private static function runSync(): never
    {
        try {
            $s = GoogleReviews::sync();
        } catch (\RuntimeException $e) {
            Admin::back('/admin/avis/google', 'err', 'Synchronisation impossible : ' . $e->getMessage());
        }
        $parts = [$s['total'] . ' avis Google sur le site'];
        if ($s['added']) {
            $parts[] = $s['added'] . ' nouveau' . ($s['added'] > 1 ? 'x' : '');
        }
        if ($s['removed']) {
            $parts[] = $s['removed'] . ' retiré' . ($s['removed'] > 1 ? 's' : '');
        }
        Admin::back('/admin/avis/google', 'ok', 'Synchronisation terminée : ' . implode(', ', $parts) . '.');
    }

    public static function sync(): never
    {
        self::runSync();
    }

    /** Recherche d'établissement par nom (mode clé API) pour trouver son Place ID. */
    public static function searchPlaces(): never
    {
        $q = clean_text((string) ($_GET['q'] ?? ''), 200);
        if (mb_strlen($q) < 3) {
            Http::fail('Tape au moins 3 caractères.');
        }
        try {
            Http::sendJson(['ok' => true, 'places' => GoogleReviews::searchPlaces($q)]);
        } catch (\RuntimeException $e) {
            Http::fail($e->getMessage(), 502);
        }
    }

    public static function connect(): never
    {
        $cfg = GoogleReviews::config();
        if ($cfg['client_id'] === '' || !Secrets::has('google_client_secret')) {
            Admin::back('/admin/avis/google', 'err', 'Renseigne d’abord l’identifiant client et le code secret OAuth, puis enregistre.');
        }
        Auth::start();
        $_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));
        Http::redirect(GoogleReviews::authUrl($_SESSION['google_oauth_state']));
    }

    public static function callback(): never
    {
        Auth::start();
        $expected = (string) ($_SESSION['google_oauth_state'] ?? '');
        unset($_SESSION['google_oauth_state']);
        if (!empty($_GET['error'])) {
            Admin::back('/admin/avis/google', 'err', 'Connexion Google annulée (' . clean_text((string) $_GET['error'], 80) . ').');
        }
        if ($expected === '' || !hash_equals($expected, (string) ($_GET['state'] ?? ''))) {
            Admin::back('/admin/avis/google', 'err', 'Lien de retour Google invalide ou expiré : relance la connexion.');
        }
        try {
            GoogleReviews::exchangeCode((string) ($_GET['code'] ?? ''));
            $locations = GoogleReviews::locations(true);
        } catch (\RuntimeException $e) {
            Admin::back('/admin/avis/google', 'err', 'Connexion Google impossible : ' . $e->getMessage());
        }
        if (count($locations) === 1) {
            self::storeLocation($locations[0]);
            Admin::back('/admin/avis/google', 'ok', 'Compte Google connecté : « ' . $locations[0]['title'] . ' » sélectionné. Lance la synchronisation.');
        }
        Admin::back('/admin/avis/google', 'ok', $locations ? 'Compte Google connecté. Choisis maintenant ton établissement.' : 'Compte Google connecté, mais aucun établissement n’a été trouvé sur ce compte.');
    }

    private static function storeLocation(array $l): void
    {
        GoogleReviews::saveConfig(array_merge(GoogleReviews::config(), [
            'account'       => $l['account'],
            'location'      => $l['location'],
            'location_name' => $l['title'],
            'place_id'      => $l['place_id'],
            'maps_uri'      => filter_var($l['maps_uri'] ?? '', FILTER_VALIDATE_URL) ? $l['maps_uri'] : '',
            'review_uri'    => filter_var($l['review_uri'] ?? '', FILTER_VALIDATE_URL) ? $l['review_uri'] : '',
        ]));
    }

    public static function chooseLocation(): never
    {
        $key = (string) ($_POST['location'] ?? '');
        foreach (GoogleReviews::locations() as $l) {
            if ($l['account'] . '|' . $l['location'] === $key) {
                self::storeLocation($l);
                if (!empty($_POST['sync_now'])) {
                    self::runSync();
                }
                Admin::back('/admin/avis/google', 'ok', 'Établissement « ' . $l['title'] . ' » sélectionné.');
            }
        }
        Admin::back('/admin/avis/google', 'err', 'Établissement introuvable : actualise la liste.');
    }

    public static function disconnect(): never
    {
        GoogleReviews::disconnect();
        GoogleReviews::saveConfig(array_merge(GoogleReviews::config(), ['account' => '', 'location' => '', 'location_name' => '', 'maps_uri' => '', 'review_uri' => '']));
        Admin::back('/admin/avis/google', 'ok', 'Compte Google déconnecté. Les avis déjà importés restent sur le site.');
    }

    public static function purge(): never
    {
        $n = GoogleReviews::purge();
        Admin::back('/admin/avis/google', 'ok', $n . ' avis Google retiré' . ($n > 1 ? 's' : '') . ' du site.');
    }

    // ------------------------------------------------------------------ widget tiers

    public static function widget(): never
    {
        Admin::view('reviews-widget', ['w' => (array) Settings::get('reviews_widget', [])], 'Widget d’avis');
    }

    public static function saveWidget(): never
    {
        $code = trim(str_replace("\r\n", "\n", (string) ($_POST['code'] ?? '')));
        if (mb_strlen($code) > 20000) {
            Admin::back('/admin/avis/widget', 'err', 'Code trop long (20 000 caractères maximum).');
        }
        if (preg_match('#<\?(php|=)?#i', $code)) {
            Admin::back('/admin/avis/widget', 'err', 'Le code ne peut contenir que du HTML / JavaScript.');
        }
        $s = Store::read('content/settings.json', []);
        $s['reviews_widget'] = [
            'code' => $code,
            'avis' => in_array($_POST['avis'] ?? '', ['off', 'before', 'after', 'replace'], true) ? $_POST['avis'] : 'after',
            'home' => in_array($_POST['home'] ?? '', ['off', 'after', 'replace'], true) ? $_POST['home'] : 'off',
        ];
        Settings::save($s);
        Admin::back('/admin/avis/widget', 'ok', $code === '' ? 'Widget retiré du site.' : 'Widget enregistré. Il est en ligne.');
    }
}
