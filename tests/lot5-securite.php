<?php
/**
 * Lot 5 de la revue de code (sécurité, robustesse) : droits du back-office (collection des dons,
 * guide PDF), carnet du supporter (première ouverture par le lien, adresse des liens envoyés),
 * adresses en « // », safe_html, jeton CSP du cache des pages, quiz du club-house (pseudos refusés,
 * joueurs par connexion, inscriptions fermées), Request::json, commentaire radio (verrou), widgets
 * (QR code du don par partenaire, débit par IP, clé écran, aperçu jamais gardé, licences NC,
 * statistiques en journal, ménage du cache).
 * Usage : php tests/lot5-securite.php (code de sortie 1 en cas d'échec).
 * N'écrit que dans des dossiers temporaires (et des compteurs de débit, storage/ratelimit) ; les
 * réglages ne sont modifiés qu'en mémoire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\JsonStore;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Kernel;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$set = function (array $over) {
    (Closure::bind(function () use ($over) { $v = Settings::all(); foreach ($over as $k => $x) { $v[$k] = $x; } self::$values = $v; }, null, Settings::class))();
};
$as = function (?array $u) {
    (new ReflectionProperty(Auth::class, 'current'))->setValue(null, $u);
    (new ReflectionProperty(Auth::class, 'loaded'))->setValue(null, true);
};
$admin = ['id' => 'essai-a', 'name' => 'Essai', 'email' => 'essai@example.org', 'role' => 'admin', 'status' => 'active'];
$historien = ['role' => 'user', 'id' => 'essai-u'] + $admin;
$tmp = sys_get_temp_dir() . '/lot5-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
$set(['waiting.enabled' => false, 'general.front_password' => '', 'general.base_url' => 'https://musee.fcsochauxretro.com']);

try {
    // ------------------------------------------------------------------ 1. collection des dons
    $as($historien);
    $edit = \App\Admin\Collections::edit(new Request('GET', '/admin/collection/dons', [], [], [], [], ''), 'dons');
    $save = \App\Admin\Collections::save(new Request('POST', '/admin/collection/dons', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"data":{}}'), 'dons');
    $eq('collection des dons : historien refusé (écran 403, enregistrement 403)', [$edit->status, $save->status, \App\Admin\Router::adminOnly('/admin/collection/dons')], [403, 403, true]);
    $eq('autres collections (quiz, comparateur de maillots) : toujours ouvertes à l’équipe', [\App\Admin\Collections::adminOnly('quiz'), \App\Admin\Collections::adminOnly('maillots'), \App\Admin\Router::adminOnly('/admin/collection/quiz')], [false, false, false]);

    // ------------------------------------------------------------------ 2. guide PDF
    $file = fn (string $n) => \App\Admin\Help::file(new Request('GET', '/admin/aide/fichier/' . $n, [], [], [], [], ''), $n);
    $eq('guide PDF complet : refusé à l’historien, mémo ouvert', [$file('guide-sochaux-retro.pdf'), $file('memo-sochaux-retro.pdf') instanceof Response], [null, true]);
    $idx = (string) \App\Core\View::render('admin/aide/index', ['chapters' => [], 'q' => '', 'results' => [], 'faq' => null]);
    $eq('écran Aide de l’historien : « Guide complet » mène à la version imprimable filtrée', [str_contains($idx, 'guide-sochaux-retro.pdf'), str_contains($idx, 'href="/admin/aide/imprimer" target="_blank"><b>Guide complet')], [false, true]);
    $as($admin);
    $idxA = (string) \App\Core\View::render('admin/aide/index', ['chapters' => [], 'q' => '', 'results' => [], 'faq' => null]);
    $eq('administrateur : guide PDF servi et proposé', [$file('guide-sochaux-retro.pdf') instanceof Response, str_contains($idxA, 'guide-sochaux-retro.pdf')], [true, true]);
    $as(null);

    // ------------------------------------------------------------------ 3. carnet du supporter
    \App\Services\Carnet::$dir = $tmp . '/carnets';
    $C = \App\Services\Carnet::class;
    $r = $C::create('victime@example.org');
    $eq('création : jeton de l’appareil et lien de l’e-mail distincts', [$r['credential'] !== $r['link'], $C::open($r['credential'])['id'] ?? null, $C::open($r['link'])['id'] ?? null], [true, $r['carnet']['id'], $r['carnet']['id']]);
    $c = $C::openFromLink((string) $r['link']);
    $eq('première ouverture par le lien : confirmé, l’appareil qui a créé le carnet (peut-être un autre) n’y a plus accès', [$c['confirmed'] ?? null, $C::open($r['credential']), $C::open($r['link'])['id'] ?? null], [true, null, $r['carnet']['id']]);
    $other = $C::newCredential($r['carnet']['id']);
    $C::openFromLink((string) $other);
    $eq('carnet déjà confirmé : un nouveau lien n’en déconnecte pas les autres appareils', [$C::open($r['link'])['id'] ?? null, $C::open((string) $other)['id'] ?? null], [$r['carnet']['id'], $r['carnet']['id']]);
    $eq('lien faux : rien d’ouvert, rien de révoqué', [$C::openFromLink($r['carnet']['id'] . '.' . str_repeat('0', 32)), $C::open($r['link']) !== null], [null, true]);
    $_SERVER['HTTP_HOST'] = 'pirate.example';
    $eq('liens envoyés par e-mail : adresse des réglages, jamais l’en-tête Host', $C::mailBase(), 'https://musee.fcsochauxretro.com');
    $set(['general.base_url' => '']);
    $eq('adresse du site non réglée : lien non envoyé (pas d’adresse tirée de Host)', [$C::mailBase(), $C::sendLink($c, (string) $r['link'])], ['', false]);
    $set(['general.base_url' => 'https://musee.fcsochauxretro.com']);
    unset($_SERVER['HTTP_HOST']);
    $src = (string) file_get_contents(APP_DIR . '/Services/Carnet.php');
    $eq('e-mails du carnet : HTML brut passé à Mailer::send (qui met en page), plus de double mise en page', [str_contains($src, 'Mailer::layout('), str_contains((string) file_get_contents(APP_DIR . '/Services/Mailer.php'), 'base64_encode(self::layout($subject, $html))')], [false, true]);

    // ------------------------------------------------------------------ 4. adresses en « // » (/api/vu)
    $vuIp = '198.51.100.' . random_int(1, 250);
    $vu = fn (string $p) => Kernel::handle(new Request('POST', '/api/vu', [], [], [], ['REMOTE_ADDR' => $vuIp, 'CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/126 Lighthouse (robot : jamais compté dans l’audience)'], json_encode(['p' => $p])));
    $left = fn () => RateLimiter::remaining('vu', $vuIp, 120, 60);
    $l0 = $left();
    foreach (['//pirate.example/', '/\\pirate.example', '/a\\b'] as $bad) {
        $vu($bad);
    }
    $l1 = $left();
    $vu('/joueurs/');
    $eq('/api/vu : « //hôte » et « \\ » refusés avant tout comptage ; une adresse du site acceptée', [$l1 === $l0, $left() === $l1 - 1], [true, true]);

    // ------------------------------------------------------------------ 5. safe_html
    $h = fn (string $s) => safe_html($s);
    $eq('safe_html : « &#106avascript: », entités répétées, tabulation, &colon; → lien neutralisé', [$h('<a href="&#106avascript:alert(1)">x</a>'), $h('<a href="java&#x09;script:alert(1)">x</a>'), $h('<a href="javascript&colon;alert(1)">x</a>'), $h('<a href="&#0000106&#0000097vascript:x">x</a>'), $h('<a href="data:text/html,x">x</a>')], array_fill(0, 5, '<a href="#">x</a>'));
    $eq('safe_html : https, mailto, tel, /, # et adresse relative gardés', [$h('<a href="https://x.fr/?a=1&amp;b=2">x</a>'), $h('<a href="mailto:a@b.fr">x</a>'), $h('<a href="tel:+33">x</a>'), $h('<a href="/fiche/">x</a>'), $h('<a href="#n">x</a>'), $h('<a href="page.html">x</a>')],
        ['<a href="https://x.fr/?a=1&amp;b=2">x</a>', '<a href="mailto:a@b.fr">x</a>', '<a href="tel:+33">x</a>', '<a href="/fiche/">x</a>', '<a href="#n">x</a>', '<a href="page.html">x</a>']);

    // ------------------------------------------------------------------ 6. jeton CSP des pages en cache
    $renonce = new ReflectionMethod(\App\Core\PageCache::class, 'renonce');
    $old = 'AncienJetonFige123';
    $headers = ['Content-Security-Policy' => "default-src 'self'; script-src 'self' 'nonce-$old'", 'Content-Type' => 'text/html; charset=utf-8'];
    $body = $renonce->invokeArgs(null, ['<script nonce="' . $old . '">x()</script><p>texte ' . $old . '</p>', &$headers]);
    $eq('cache des pages : jeton CSP neuf dans l’en-tête et les nonce="…", texte intact', [str_contains($headers['Content-Security-Policy'], "'nonce-" . csp_nonce() . "'"), str_contains($body, 'nonce="' . csp_nonce() . '"'), str_contains($body, 'texte ' . $old), csp_nonce() !== $old], [true, true, true, true]);

    // ------------------------------------------------------------------ 8. quiz du club-house
    $Q = \App\Services\QuizLive::class;
    $Q::$dir = $tmp . '/quizlive';
    $eq('pseudos injurieux refusés (fr, en, lettres collées, chiffres, espacés)', array_map(fn ($n) => $Q::nameAllowed($n), ['Connard', 'JeSuisUnConnard', 'c0nn4rd', 'Fuuuck', 'Sale con', 'f.d.p', 'Bitch', 'Nique ta mère', 'S.a.l.o.p.e']), array_fill(0, 9, false));
    $eq('pseudos ordinaires acceptés (Conrad, Bonal, Retard, Zizou, Constance)', array_map(fn ($n) => $Q::nameAllowed($n), ['Conrad', 'Bonal 1928', 'Retard', 'Zizou', 'Constance', 'Lionceau25']), array_fill(0, 6, true));
    $g = $Q::create(3, 20, 'fr', 'site');
    $eq('pseudo injurieux : inscription refusée', is_string($Q::join($g['code'], 'Connard')), true);
    $joins = [];
    for ($i = 0; $i < $Q::MAX_PER_IP + 1; $i++) {
        $joins[] = $Q::join($g['code'], 'Joueur ' . $i, null, null, 'ip-distante');
    }
    $eq('connexion distante : ' . $Q::MAX_PER_IP . ' joueurs au plus par partie', [count(array_filter($joins, 'is_array')), is_string(end($joins))], [$Q::MAX_PER_IP, true]);
    $Q::hostSeen($g['code'], 'ip-salle');
    $salle = [];
    for ($i = 0; $i < $Q::MAX_PER_IP + 2; $i++) {
        $salle[] = $Q::join($g['code'], 'Salle ' . $i, null, null, 'ip-salle');
    }
    $eq('connexion du grand écran (la salle) : pas de limite par adresse', count(array_filter($salle, 'is_array')), $Q::MAX_PER_IP + 2);
    $api = fn (array $in) => Kernel::handle(new Request('POST', '/api/quiz-live', [], [], [], ['REMOTE_ADDR' => '203.0.113.' . random_int(1, 250), 'CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/126'], json_encode($in)));
    $eq('fermer les inscriptions : réservé à l’animateur (clé de la partie)', $api(['action' => 'verrouiller', 'code' => $g['code'], 'key' => 'mauvaise', 'locked' => true])->status, 404);
    $lock = json_decode($api(['action' => 'verrouiller', 'code' => $g['code'], 'key' => $g['key'], 'locked' => true])->body, true);
    $r = json_decode($api(['action' => 'rejoindre', 'code' => $g['code'], 'name' => 'Retardataire'])->body, true);
    $eq('inscriptions fermées par l’animateur : plus personne ne rejoint', [$lock['locked'] ?? null, isset($r['error']), $Q::join($g['code'], 'Autre') !== null && is_string($Q::join($g['code'], 'Autre'))], [true, true, true]);
    $api(['action' => 'verrouiller', 'code' => $g['code'], 'key' => $g['key'], 'locked' => false]);
    $eq('inscriptions rouvertes', is_array($Q::join($g['code'], 'Retardataire')), true);

    // ------------------------------------------------------------------ 9. Request::json
    $eq('corps JSON scalaire (« 1 », « "x" », « true ») : [] (pas d’erreur de type)', array_map(fn ($b) => (new Request('POST', '/api/quiz-live', [], [], [], ['CONTENT_TYPE' => 'application/json'], $b))->json(), ['1', '"x"', 'true', 'null', '[1]']), [[], [], [], [], [1]]);
    $eq('/api/quiz-live avec un corps scalaire : réponse, pas d’erreur 500', Kernel::handle(new Request('POST', '/api/quiz-live', [], [], [], ['REMOTE_ADDR' => '203.0.113.7', 'CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/126'], '42'))->status !== 500, true);

    // ------------------------------------------------------------------ 12. commentaire radio : verrou
    $R = \App\Services\RetroRadio::class;
    $R::$dir = $tmp . '/radio';
    $R::$media = $tmp . '/media';
    $R::$lockWait = 0.5;
    $R::request(4242, 'fr');
    $hold = fopen($R::$dir . '/4242-fr.json.work', 'c');
    flock($hold, LOCK_EX); // une étape de fabrication en cours
    $err = [];
    foreach (['delete', 'request'] as $m) {
        try {
            $m === 'delete' ? $R::delete(4242, 'fr') : $R::request(4242, 'fr');
        } catch (\RuntimeException $e) {
            $err[] = $m;
        }
    }
    $eq('étape en cours : suppression et nouvelle demande attendent le verrou (rien effacé sous ses pieds)', [$err, $R::get(4242, 'fr') !== null], [['delete', 'request'], true]);
    flock($hold, LOCK_UN);
    fclose($hold);
    $R::delete(4242, 'fr');
    $eq('verrou libre : suppression faite, fichier de verrou gardé', [$R::get(4242, 'fr'), is_file($R::$dir . '/4242-fr.json.work')], [null, true]);

    // ------------------------------------------------------------------ 7. widgets
    \App\Widgets\Store::$dir = $tmp . '/widgets';
    $S = \App\Widgets\Store::class;
    \App\Widgets\Items\Don::$open = true;
    $wip = '192.0.2.' . random_int(1, 250);
    $get = function (string $path, array $q = [], array $server = []) use ($wip): Response {
        return Kernel::handle(new Request('GET', $path, $q, [], [], $server + ['REMOTE_ADDR' => $wip, 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/126.0', 'HTTP_HOST' => 'musee.fcsochauxretro.com'], ''));
    };
    $pa = $S::savePartner(null, ['name' => 'Partenaire A', 'status' => 'actif', 'offer' => 'gratuite']);
    $pb = $S::savePartner(null, ['name' => 'Partenaire B', 'status' => 'actif', 'offer' => 'gratuite']);
    $qr = fn (?array $p) => json_decode($get('/api/v1/widgets/don', $p ? ['key' => $S::keyOf($p)] : [])->body, true)['data']['qr'] ?? '';
    [$qa, $qb, $q0] = [$qr($pa), $qr($pb), $qr(null)];
    $eq('a. QR code du don : propre à chaque partenaire, même après le cache (rien de A chez B ni sans clé)', [str_contains($qa, 'utm_campaign=' . $pa['id']), str_contains($qb, 'utm_campaign=' . $pb['id']), str_contains($qb, $pa['id']), str_contains($q0, 'utm_campaign')], [true, true, false, false]);
    $cached = array_map(fn ($f) => json_decode((string) file_get_contents($f), true)['data']['qr'] ?? null, glob($tmp . '/widgets/cache/don-*.json') ?: []);
    $eq('a. cache du don : le QR code n’y est pas (calculé à chaque appel)', $cached !== [] && !array_filter($cached, 'is_string'), true);
    $old = $tmp . '/widgets/cache/vieux-' . bin2hex(random_bytes(3)) . '.json';
    file_put_contents($old, '{}');
    touch($old, strtotime('-2 days'));
    $eq('b. cache des widgets : entrées des jours passés effacées par le ménage', [\App\Widgets\Cache::prune() >= 1, is_file($old)], [true, false]);
    $eq('c. débit : avec une clé, aussi compté par adresse IP', RateLimiter::remaining('widgets-ip-cle', $wip, \App\Widgets\Http::PER_IP_KEY, 60) < \App\Widgets\Http::PER_IP_KEY, true);
    // Clé écran : distincte et secrète ; partenaire d'avant (sans clé écran) : clé publique acceptée pendant la transition.
    $S::saveWidget('playlist', 'reserve', false);
    JsonStore::update($tmp . '/widgets/partenaires.json', function ($all) use ($pb) {
        unset($all[$pb['id']]['screen_hash'], $all[$pb['id']]['screen_enc'], $all[$pb['id']]['screen_end']);
        return $all;
    }, []);
    $pbOld = $S::partner($pb['id']);
    $scr = fn (string $key) => $get('/ecran/mi-temps', ['key' => $key, 'apercu' => '1'])->status;
    $eq('c. /ecran/ : clé écran acceptée, clé publique refusée, clé inconnue refusée', [$scr($S::screenKeyOf($pa)), $scr($S::keyOf($pa)), $scr('ek_' . str_repeat('0', 32))], [200, 401, 401]);
    $eq('c. transition : partenaire sans clé écran, sa clé publique ouvre encore l’écran (jusqu’au ' . $S::SCREEN_LEGACY_UNTIL . ')', [$S::screenLegacy($pbOld), $scr($S::keyOf($pbOld))], [date('Y-m-d') <= $S::SCREEN_LEGACY_UNTIL, date('Y-m-d') <= $S::SCREEN_LEGACY_UNTIL ? 200 : 401]);
    $ek = $S::regenerateScreen($pb['id']);
    $eq('c. clé écran créée : la clé publique n’ouvre plus l’écran, la clé écran oui', [$scr($S::keyOf($pbOld)), $scr($ek)], [401, 200]);
    // Aperçu de l'équipe connectée : jamais gardé (ni public ni sans Vary: Cookie).
    $as($admin);
    $pv = $get('/w/chiffre-du-jour', [], ['HTTP_COOKIE' => 'sr_session=essai']);
    $pvApi = $get('/api/v1/widgets/chiffre-du-jour', [], ['HTTP_COOKIE' => 'sr_session=essai']);
    $as(null);
    $eq('f. aperçu de l’équipe connectée : no-store (iframe et API)', [$pv->headers['Cache-Control'] ?? '', $pvApi->headers['Cache-Control'] ?? ''], ['no-store', 'no-store']);
    $eq('g. licences « NC » refusées (BY-NC, BY-NC-ND, BY-NC-SA), BY et BY-SA acceptées', array_map([\App\Widgets\Photos::class, 'freeLicense'], ['CC BY-NC 4.0', 'CC BY-NC-ND 4.0', 'CC-BY-NC-SA', 'CC BY 4.0', 'CC BY-SA 4.0', 'CC0']), [false, false, false, true, true, true]);
    // Statistiques : journal en ajout, regroupé pour les jours passés.
    $S::hit('vues', 'don', 'libre');
    $S::hit('clics', 'don', $pa['id']);
    $S::hit('vues', 'don', '../evil');
    $y = date('Y-m-d', strtotime('-1 day'));
    file_put_contents($tmp . "/widgets/stats/$y.log", "vues\tdon\tlibre\nvues\tdon\tlibre\n");
    $st = $S::stats(2);
    $eq('h. statistiques : journal du jour en ajout (pas de .json réécrit), jour passé regroupé puis journal effacé', [is_file($tmp . '/widgets/stats/' . date('Y-m-d') . '.log'), $st['days'][$y]['vues'], $st['partners'][$pa['id']]['clics'] ?? 0, is_file($tmp . "/widgets/stats/$y.log"), is_file($tmp . "/widgets/stats/$y.json"), isset($st['partners']['../evil'])], [true, 2, 1, false, true, false]);
} finally {
    exec('rm -rf ' . escapeshellarg($tmp));
}

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
