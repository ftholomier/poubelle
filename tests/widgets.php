<?php
/**
 * Widgets du musée (App\Widgets) : API JSON, iframe, accès libre / réservé / payant / désactivé,
 * clé invalide ou suspendue, domaine non autorisé, site fermé, cache (ETag, 304), mention et lien
 * obligatoires, aucune donnée personnelle, statistiques et clics, photos diffusables seulement.
 * Usage : php tests/widgets.php (code de sortie 1 en cas d'échec).
 * Réglages, partenaires, statistiques et cache des widgets : dans un dossier temporaire, effacé à
 * la fin ; seuls des compteurs de débit (storage/ratelimit) sont écrits ailleurs.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Shield;
use App\Kernel;
use App\Widgets\Cache;
use App\Widgets\Http;
use App\Widgets\Items\CarteLegende;
use App\Widgets\Items\LeSaviezVous;
use App\Widgets\Photos;
use App\Widgets\Registry;
use App\Widgets\Store;

$fail = 0;
$ok = function (bool $c, string $m) use (&$fail) {
    echo ($c ? 'OK   ' : 'FAIL ') . $m . "\n";
    $fail += $c ? 0 : 1;
};

$tmp = sys_get_temp_dir() . '/widgets-test-' . getmypid() . '-' . bin2hex(random_bytes(3));
Store::$dir = $tmp;
// Site ouvert pour les essais (sans rien enregistrer) ; $closed() le ferme le temps d'un essai.
$set = function (array $over) {
    (Closure::bind(function () use ($over) { $v = Settings::all(); foreach ($over as $k => $x) { $v[$k] = $x; } self::$values = $v; }, null, Settings::class))();
};
$set(['waiting.enabled' => false, 'general.front_password' => '']);

$ip = '198.51.100.' . random_int(1, 250);
$get = function (string $path, array $query = [], array $headers = [], string $method = 'GET') use ($ip): Response {
    $server = ['REMOTE_ADDR' => $headers['_ip'] ?? $ip, 'HTTP_USER_AGENT' => $headers['_ua'] ?? 'Mozilla/5.0 (Windows NT 10.0) Chrome/126.0', 'HTTP_HOST' => 'musee.fcsochauxretro.com'];
    foreach ($headers as $k => $v) {
        if ($k[0] !== '_') {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
        }
    }
    return Kernel::handle(new Request($method, $path, $query, [], [], $server, ''));
};
$json = fn (Response $r) => json_decode($r->body, true);
$keys = array_keys(Registry::CLASSES);

try {
    // ------------------------------------------------------------------ catalogue
    $ok($keys === ['ce-jour-la', 'chiffre-du-jour', 'il-y-a', 'carte-legende', 'photo-du-jour', 'le-saviez-vous'], 'registre : les 6 widgets de la phase 1');
    $ok(count(Registry::PLANNED) === 16 && !array_intersect_key(Registry::PLANNED, Registry::CLASSES), '16 widgets à venir, distincts des widgets prêts');
    $r = $get('/api/v1/widgets');
    $c = $json($r);
    $ok($r->status === 200 && count($c['widgets'] ?? []) === 6, 'catalogue : 6 widgets libres par défaut');
    $ok(($r->headers['Access-Control-Allow-Origin'] ?? '') === '*', 'catalogue : CORS ouvert');
    $ok(str_contains((string) ($c['museum']['mention'] ?? ''), 'non officiel') && str_contains((string) ($c['museum']['url'] ?? ''), '/w/clic/'), 'catalogue : mention « non officiel » et lien vers le musée');

    // ------------------------------------------------------------------ chaque widget : API et iframe
    $allJson = '';
    foreach ($keys as $k) {
        $r = $get('/api/v1/widgets/' . $k);
        $d = $json($r);
        $allJson .= $r->body;
        $ok($r->status === 200 && is_array($d['data'] ?? null), "API $k : 200 et des données");
        $ok(str_contains((string) ($d['attribution']['mention'] ?? ''), 'non officiel') && ($d['attribution']['name'] ?? '') === \App\Core\Club::s('site'), "API $k : attribution obligatoire");
        $ok(str_contains($r->headers['Cache-Control'] ?? '', 'max-age=300') && preg_match('/^"[a-f0-9]{32}"$/', $r->headers['ETag'] ?? '') === 1, "API $k : en cache, avec ETag");
        foreach (['clair', 'sombre'] as $theme) {
            $f = $get('/w/' . $k, ['theme' => $theme]);
            $html = $f->body;
            $ok($f->status === 200 && str_contains($html, 'data-theme="' . $theme . '"'), "iframe $k ($theme) : 200");
            $ok(str_contains($html, 'class="w-brand"><img src="' . \App\Widgets\Context::abs((string) \App\Core\Club::s('logo_400'))), "iframe $k ($theme) : logo du musée");
            $ok(str_contains($html, 'Musée indépendant, non officiel'), "iframe $k ($theme) : mention « non officiel »");
            $ok((bool) preg_match('#<a class="w-url" href="https?://[^"]+/w/clic/' . $k . '/\?to=%2F"[^>]*>' . preg_quote(\App\Widgets\Context::host(), '#') . '#', $html), "iframe $k ($theme) : petite adresse du musée cliquable");
        }
        $ok(str_contains($f->headers['Content-Security-Policy'] ?? '', 'frame-ancestors *') && !isset($f->headers['X-Frame-Options']) && !isset($f->headers['Set-Cookie']), "iframe $k : intégrable partout, sans cookie");
        $ok(str_contains($f->body, '/assets/js/widget.js'), "iframe $k : script de hauteur automatique");
    }
    $en = $get('/w/chiffre-du-jour', ['lang' => 'en']);
    $ok(str_contains($en->body, 'lang="en"') && str_contains($en->body, 'Independent, unofficial museum') && str_contains($en->body, 'to=%2Fen%2F'), 'iframe en anglais : mention et liens en anglais');
    \App\Services\I18n::set('fr');
    $compact = $get('/w/ce-jour-la', ['format' => 'compact']);
    $ok(str_contains($compact->body, 'w--compact'), 'format compact');

    // ------------------------------------------------------------------ aucune donnée personnelle
    $ok(!preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/', $allJson), 'aucune adresse e-mail dans les données');
    $ok(!preg_match('/"(email|e-mail|ip|password|token|key_hash|key_enc|contact|adresse|phone|telephone)"\s*:/i', $allJson), 'aucun champ personnel ni secret dans les données');
    $ok(!str_contains($allJson, 'mk_'), 'aucune clé dans les données');

    // ------------------------------------------------------------------ options
    $d = $json($get('/api/v1/widgets/ce-jour-la', ['date' => '05-14', 'nombre' => '1']))['data'];
    $ok($d['date'] === '05-14' && count($d['matches']) <= 1, 'ce jour-là : date et nombre choisis');
    $d = $json($get('/api/v1/widgets/ce-jour-la', ['date' => '13-45', 'nombre' => '99']))['data'];
    $ok($d['date'] === date('m-d') && count($d['matches']) <= 3, 'ce jour-là : options invalides → valeurs par défaut');
    $ilya = $json($get('/api/v1/widgets/il-y-a'))['data']['items'];
    $one = $json($get('/api/v1/widgets/il-y-a', ['ans' => '10']))['data']['items'];
    $ok(array_column($ilya, 'years') === [5, 10, 20, 30] && array_column($one, 'years') === [10], 'il y a : 5, 10, 20, 30 ans, ou un seul écart');
    $leg = CarteLegende::pool()[0] ?? null;
    if ($leg) {
        $slug = basename(rtrim((string) $leg['path'], '/'));
        $d = $json($get('/api/v1/widgets/carte-legende', ['joueur' => $slug]))['data'];
        $ok($d['id'] === (int) $leg['id'] && $d['matches'] > 0 && str_contains((string) $d['href'], rawurlencode((string) $leg['path'])), 'carte de légende : joueur choisi (' . $slug . ')');
    }
    $d = $json($get('/api/v1/widgets/carte-legende', ['joueur' => 'personne-inconnue-xyz']))['data'];
    $ok(in_array($d['id'], array_map(fn ($s) => (int) $s['id'], CarteLegende::pool()), true), 'carte de légende : joueur inconnu → une légende au hasard');
    $facts = array_column(LeSaviezVous::facts('fr'), 'text');
    $d = $json($get('/api/v1/widgets/le-saviez-vous'))['data'];
    $ok(in_array($d['text'], $facts, true) && $facts !== [], 'le saviez-vous : une anecdote vérifiée existante (' . count($facts) . ' en tout), rien d’inventé');

    // ------------------------------------------------------------------ photos diffusables seulement
    $p = $json($get('/api/v1/widgets/photo-du-jour'))['data'];
    $ok($p['credit'] !== '' && str_starts_with((string) $p['image']['url'], 'http'), 'photo du jour : adresse complète et crédit');
    $pool = Photos::pool();
    $ok($pool !== [] && !array_filter($pool, fn ($x) => $x['credit'] === '' || Photos::official($x['credit'])), 'photos : toutes créditées, aucune créditée au club');
    $ok(Photos::official(\App\Core\Club::s('club')) && Photos::official(\App\Core\Club::s('sigle')) && !Photos::official('L’Est Républicain'), 'photos officielles du club écartées');
    $ok(Photos::freeLicense('CC BY-SA 4.0') && Photos::freeLicense('CC0') && Photos::freeLicense('Domaine public') && !Photos::freeLicense('Tous droits réservés') && !Photos::freeLicense(''), 'licences libres reconnues');
    $risky = null;
    foreach (\App\Data\Media::all() as $rel => $m) {
        if (preg_match('/^(DR|AFP)$/i', trim((string) ($m['credit'] ?? '')))) {
            $risky = (string) $rel;
            break;
        }
    }
    $ok($risky === null || Photos::safe($risky) === null, 'photo « DR » ou d’agence : jamais diffusée' . ($risky ? '' : ' (aucune dans ce musée)'));

    // ------------------------------------------------------------------ accès
    $partner = Store::savePartner(null, ['name' => 'Partenaire essai', 'contact' => 'essai', 'domains' => "https://www.exemple.fr/page\nautre.org", 'status' => 'actif', 'offer' => 'gratuite']);
    $key = Store::keyOf($partner);
    $ok(preg_match('/^mk_[a-f0-9]{32}$/', $key) === 1 && $partner['domains'] === ['exemple.fr', 'autre.org'] && !str_contains(json_encode($partner), $key), 'partenaire : clé aléatoire, chiffrée, domaines nettoyés');
    $free = Store::savePartner(null, ['name' => 'Serveur essai', 'status' => 'actif', 'offer' => 'gratuite']);
    $freeKey = Store::keyOf($free);

    Store::saveWidget('chiffre-du-jour', 'reserve', false);
    $r = $get('/api/v1/widgets/chiffre-du-jour');
    $ok($r->status === 401, 'réservé sans clé : 401 (API)');
    $f = $get('/w/chiffre-du-jour');
    $ok($f->status === 401 && str_contains($f->body, 'clé requise') && str_contains($f->body, 'non officiel'), 'réservé sans clé : 401 (iframe, message dans le cadre du musée)');
    $r = $get('/api/v1/widgets/chiffre-du-jour', ['key' => $freeKey]);
    $ok($r->status === 200, 'réservé avec clé sans domaine : 200');
    $r = $get('/api/v1/widgets/chiffre-du-jour', [], ['X-Musee-Key' => $freeKey]);
    $ok($r->status === 200, 'clé dans l’en-tête X-Musee-Key : 200');
    $r = $get('/api/v1/widgets/chiffre-du-jour', ['key' => 'mk_' . str_repeat('0', 32)]);
    $ok($r->status === 401 && str_contains((string) $json($r)['error'], 'invalide'), 'clé invalide : 401');
    $r = $get('/api/v1/widgets/ce-jour-la', ['key' => 'n-importe-quoi']);
    $ok($r->status === 401, 'clé invalide refusée même sur un widget libre');
    $r = $get('/api/v1/widgets/chiffre-du-jour', ['key' => $key], ['Origin' => 'https://pirate.example']);
    $ok($r->status === 403 && str_contains((string) $json($r)['error'], 'pirate.example'), 'domaine non autorisé (Origin) : 403');
    $f = $get('/w/chiffre-du-jour', ['key' => $key], ['Referer' => 'https://pirate.example/page']);
    $ok($f->status === 403, 'domaine non autorisé (Referer, iframe) : 403');
    $r = $get('/api/v1/widgets/chiffre-du-jour', ['key' => $key]);
    $ok($r->status === 403, 'clé limitée sans Origin ni Referer : 403');
    $r = $get('/api/v1/widgets/chiffre-du-jour', ['key' => $key], ['Origin' => 'https://blog.exemple.fr']);
    $ok($r->status === 200 && ($r->headers['Access-Control-Allow-Origin'] ?? '') === 'https://blog.exemple.fr' && str_starts_with($r->headers['Cache-Control'] ?? '', 'private'), 'sous-domaine autorisé : 200, CORS limité à cette origine, cache privé');
    $f = $get('/w/chiffre-du-jour', ['key' => $key], ['Referer' => 'https://www.exemple.fr/actus/']);
    $csp = $f->headers['Content-Security-Policy'] ?? '';
    $ok($f->status === 200 && str_contains($csp, 'frame-ancestors') && str_contains($csp, 'https://exemple.fr') && str_contains($csp, 'https://*.autre.org') && !str_contains($csp, 'frame-ancestors *'), 'iframe d’une clé limitée : intégrable seulement chez le partenaire');
    $ok(str_contains($f->body, 'p=' . $partner['id']) && !str_contains($f->body, $key), 'liens de l’iframe : identifiant public du partenaire, jamais sa clé');
    $cat = $json($get('/api/v1/widgets'));
    $catKey = $json($get('/api/v1/widgets', ['key' => $freeKey]));
    $ok(!in_array('chiffre-du-jour', array_column($cat['widgets'], 'key'), true) && in_array('chiffre-du-jour', array_column($catKey['widgets'], 'key'), true), 'catalogue : widget réservé seulement avec une clé');

    Store::savePartner($free['id'], ['name' => 'Serveur essai', 'status' => 'suspendu', 'offer' => 'gratuite']);
    $r = $get('/api/v1/widgets/chiffre-du-jour', ['key' => $freeKey]);
    $ok($r->status === 403 && str_contains((string) $json($r)['error'], 'suspendue'), 'partenaire suspendu : 403');
    Store::savePartner($free['id'], ['name' => 'Serveur essai', 'status' => 'actif', 'offer' => 'gratuite']);

    Store::saveWidget('chiffre-du-jour', 'payant', false);
    $r = $get('/api/v1/widgets/chiffre-du-jour', ['key' => $freeKey]);
    $ok($r->status === 402, 'payant, partenaire à l’offre gratuite : 402');
    Store::savePartner($free['id'], ['name' => 'Serveur essai', 'status' => 'actif', 'offer' => 'payante', 'until' => date('Y-m-d', strtotime('+30 days'))]);
    $r = $get('/api/v1/widgets/chiffre-du-jour', ['key' => $freeKey]);
    $ok($r->status === 200, 'payant, offre payante en cours : 200');
    Store::savePartner($free['id'], ['name' => 'Serveur essai', 'status' => 'actif', 'offer' => 'payante', 'until' => date('Y-m-d', strtotime('-1 day'))]);
    $r = $get('/api/v1/widgets/chiffre-du-jour', ['key' => $freeKey]);
    $ok($r->status === 402, 'payant, offre échue : 402');
    $r = $get('/api/v1/widgets/chiffre-du-jour');
    $ok($r->status === 401, 'payant sans clé : 401');

    Store::saveWidget('chiffre-du-jour', 'desactive', false);
    $ok($get('/api/v1/widgets/chiffre-du-jour', ['key' => $freeKey])->status === 404 && $get('/w/chiffre-du-jour')->status === 404, 'désactivé : 404, même avec une clé');
    $ok(!in_array('chiffre-du-jour', array_column($json($get('/api/v1/widgets', ['key' => $freeKey]))['widgets'], 'key'), true), 'désactivé : absent du catalogue');
    Store::saveWidget('chiffre-du-jour', 'libre', false);
    $ok($get('/api/v1/widgets/chiffre-du-jour')->status === 200, 'de nouveau libre : 200 (réglage modifiable dans le temps)');

    $old = Store::keyOf(Store::partner($free['id']));
    $new = Store::regenerate($free['id']);
    $ok($new !== $old && $get('/api/v1/widgets/ce-jour-la', ['key' => $old])->status === 401 && $get('/api/v1/widgets/ce-jour-la', ['key' => $new])->status === 200, 'clé régénérée : l’ancienne ne marche plus');

    // ------------------------------------------------------------------ site fermé
    $set(['waiting.enabled' => true]);
    $ok($get('/api/v1/widgets/ce-jour-la')->status === 503 && $get('/w/ce-jour-la')->status === 503, 'site fermé : widgets non servis par défaut');
    Store::saveWidget('ce-jour-la', 'libre', true);
    $ok($get('/api/v1/widgets/ce-jour-la')->status === 200 && $get('/w/ce-jour-la')->status === 200, 'site fermé : servi si « actif même site fermé »');
    $ok(!str_contains($get('/widgets/')->body, 'data-musee='), 'site fermé : la page de démonstration laisse place à la page d’attente');
    $set(['waiting.enabled' => false, 'general.front_password' => 'secret']);
    $ok($get('/api/v1/widgets/il-y-a')->status === 503, 'site protégé par mot de passe : widgets non servis');
    $set(['waiting.enabled' => false, 'general.front_password' => '']);
    Store::saveWidget('ce-jour-la', 'libre', false);

    // ------------------------------------------------------------------ cache
    Cache::clear();
    $a = $get('/api/v1/widgets/il-y-a');
    $b = $get('/api/v1/widgets/il-y-a');
    $ok(count(glob($tmp . '/cache/il-y-a-*.json') ?: []) === 1 && $a->headers['ETag'] === $b->headers['ETag'] && $a->body === $b->body, 'cache : même réponse, même ETag');
    $nm = $get('/api/v1/widgets/il-y-a', [], ['If-None-Match' => $a->headers['ETag']]);
    $ok($nm->status === 304 && $nm->body === '', 'cache : 304 sur If-None-Match');
    $fi = $get('/w/il-y-a');
    $ok($get('/w/il-y-a', [], ['If-None-Match' => $fi->headers['ETag']])->status === 304 && str_contains($fi->headers['Cache-Control'] ?? '', 'max-age=120'), 'iframe : 304 et cache court');
    $pre = $get('/api/v1/widgets', [], ['Origin' => 'https://exemple.fr'], 'OPTIONS');
    $ok($pre->status === 204 && str_contains($pre->headers['Access-Control-Allow-Headers'] ?? '', 'X-Musee-Key'), 'pré-vol CORS (OPTIONS)');
    $ok($get('/api/v1/widgets/il-y-a', [], [], 'POST')->status === 405, 'lecture seule : POST refusé');

    // ------------------------------------------------------------------ statistiques et clics
    $before = Store::stats(1)['total'];
    $get('/w/photo-du-jour');
    $get('/w/photo-du-jour', ['apercu' => '1']);
    $get('/api/v1/widgets/photo-du-jour');
    $click = $get('/w/clic/photo-du-jour/', ['to' => '/chiffres/#geant', 'p' => $partner['id']]);
    $after = Store::stats(1);
    $ok($after['total']['vues'] === $before['vues'] + 1 && $after['total']['api'] === $before['api'] + 1 && $after['total']['clics'] === $before['clics'] + 1, 'statistiques : affichage, appel et clic comptés ; aperçu non compté');
    $ok(($after['partners'][$partner['id']]['clics'] ?? 0) >= 1 && ($after['widgets']['photo-du-jour']['vues'] ?? 0) >= 1, 'statistiques par widget et par partenaire');
    $ok($click->status === 302 && $click->headers['Location'] === '/chiffres/?utm_source=widget&utm_medium=photo-du-jour&utm_campaign=' . $partner['id'] . '#geant', 'clic : redirection vers la page du musée');
    foreach (['//pirate.example/', 'https://pirate.example/', '/\\pirate.example', 'javascript:alert(1)'] as $evil) {
        $loc = $get('/w/clic/photo-du-jour/', ['to' => $evil])->headers['Location'] ?? '';
        $ok(str_starts_with($loc, '/?utm_source=widget'), 'clic : pas de redirection hors du musée (' . $evil . ')');
    }
    $stats = (string) file_get_contents($tmp . '/stats/' . date('Y-m-d') . '.json');
    $ok(!str_contains($stats, $ip) && !str_contains($stats, 'Mozilla'), 'statistiques sans adresse IP ni navigateur');

    // ------------------------------------------------------------------ anti-aspiration et débit
    $curl = fn (array $q) => new Request('GET', '/api/v1/widgets/ce-jour-la', $q, [], [], ['REMOTE_ADDR' => '203.0.113.' . random_int(1, 250), 'HTTP_USER_AGENT' => 'curl/8.4'], '');
    $deny = Shield::check($curl([]));
    $ok($deny !== null && $deny->status === 403, 'Shield : curl sans clé refusé');
    $ok(Shield::check($curl(['key' => $new])) === null, 'Shield : serveur d’un partenaire (curl avec clé) accepté, clé vérifiée par l’API');
    $ok(Shield::check(new Request('GET', '/w/ce-jour-la', [], [], [], ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'GPTBot/1.1'], ''))?->status === 403, 'Shield : robot d’IA refusé sur l’iframe');
    $flood = '192.0.2.' . random_int(1, 250);
    $codes = [];
    for ($i = 0; $i < Http::PER_IP + 3; $i++) {
        $codes[] = $get('/api/v1/widgets/le-saviez-vous', [], ['_ip' => $flood])->status;
    }
    $ok(count(array_filter($codes, fn ($c) => $c === 429)) === 3, 'débit : 429 au-delà de ' . Http::PER_IP . ' requêtes par minute et par IP');

    // ------------------------------------------------------------------ page publique, back-office
    $demo = $get('/widgets/');
    $ok($demo->status === 200 && substr_count($demo->body, 'data-musee=') >= 6 && str_contains($demo->body, '/contact/?objet=partenariat'), 'page /widgets/ : aperçus, code à copier, contact partenaire');
    $ok(str_contains($get('/')->body, 'href="/widgets/"'), 'lien discret vers /widgets/ dans le pied de page');
    $adm = $get('/admin/widgets');
    $ok($adm->status === 302 && str_contains($adm->headers['Location'] ?? '', '/admin/connexion'), 'back-office : écran réservé aux personnes connectées');
    $ok(\App\Admin\Router::adminOnly('/admin/widgets'), 'back-office : écran réservé aux administrateurs');
} finally {
    // Ménage : dossier temporaire des widgets.
    $rm = function (string $d) use (&$rm) {
        foreach (glob($d . '/{,.}[!.,!..]*', GLOB_BRACE) ?: [] as $f) {
            is_dir($f) ? $rm($f) : @unlink($f);
        }
        @rmdir($d);
    };
    $rm($tmp);
}

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
