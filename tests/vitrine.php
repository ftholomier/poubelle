<?php
/**
 * Site de l'association (www) : adresses, page d'attente, aperçu, anciennes adresses vers le musée,
 * contenus « à vérifier », agenda iCal, formulaires (antispam), adhésions (webhook Stripe signé,
 * PayPal, purges RGPD), documents, nettoyage des saisies du pavé d'administration.
 * Usage : php tests/vitrine.php (code de sortie 1 en cas d'échec).
 * Les fichiers touchés (adhésions, bénévoles, journaux d'e-mails, événements des dons…) sont
 * sauvegardés au début et remis en place à la fin ; les réglages ne sont modifiés qu'en mémoire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

// Session ouverte avant tout affichage (jetons des formulaires), détruite à la fin.
\App\Core\Session::start();

use App\Core\Request;
use App\Core\Settings;
use App\Vitrine\Content;
use App\Vitrine\Documents;
use App\Vitrine\Forms;
use App\Vitrine\Host;
use App\Vitrine\Membership;
use App\Vitrine\Site;
use App\Vitrine\Store;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Sauvegarde de tout ce que le test peut écrire, remis en place à la fin (même en cas d'erreur).
$tmp = sys_get_temp_dir() . '/vitrine-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
$guarded = [STORAGE_PATH . '/vitrine', DATA_PATH . '/vitrine', STORAGE_PATH . '/dons/events.json', STORAGE_PATH . '/mail/' . date('Y-m') . '.jsonl', STORAGE_PATH . '/versions/vitrine'];
$saved = [];
foreach ($guarded as $i => $p) {
    $saved[$i] = file_exists($p);
    if ($saved[$i]) {
        exec('cp -a ' . escapeshellarg($p) . ' ' . escapeshellarg("$tmp/$i"));
    }
}
register_shutdown_function(function () use ($guarded, $saved, $tmp) {
    foreach ($guarded as $i => $p) {
        exec('rm -rf ' . escapeshellarg($p));
        foreach (glob($p . '.lock') ?: [] as $l) {
            @unlink($l);
        }
        if ($saved[$i]) {
            exec('cp -a ' . escapeshellarg("$tmp/$i") . ' ' . escapeshellarg($p));
        }
    }
    exec('rm -rf ' . escapeshellarg($tmp));
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
});

// Réglages en mémoire seulement (jamais écrits sur le disque).
$values = new ReflectionProperty(Settings::class, 'values');
$setting = function (array $changes) use ($values) {
    $values->setValue(null, $changes + Settings::all());
};
$setting(['general.base_url' => 'https://musee.fcsochauxretro.com', 'vitrine.base_url' => 'https://www.fcsochauxretro.com', 'vitrine.aliases' => 'fcsochauxretro.com', 'vitrine.open' => false]);
$req = function (string $host, string $path, string $method = 'GET', array $query = [], array $post = [], string $body = '', array $server = []) {
    $_SERVER['REQUEST_URI'] = $path . ($query ? '?' . http_build_query($query) : '');
    $_SERVER['HTTP_HOST'] = $host;
    \App\Vitrine\Host::$prefix = '';
    Site::$preview = false;
    Store::forget();
    return \App\Kernel::handle(new Request($method, $path, $query, $post, [], ['HTTP_HOST' => $host, 'REQUEST_URI' => $_SERVER['REQUEST_URI'], 'REMOTE_ADDR' => '127.0.0.9'] + $server, $body));
};

// ---------------------------------------------------------------- adresses
$eq('adresse du site, domaine, alias', [Host::base(), Host::host(), Host::aliases()], ['https://www.fcsochauxretro.com', 'www.fcsochauxretro.com', ['fcsochauxretro.com']]);
$mk = fn ($h) => new Request('GET', '/', [], [], [], ['HTTP_HOST' => $h], '');
$eq('www et le domaine nu : site de l’association ; musée et inconnu : musée', [Host::matches($mk('www.fcsochauxretro.com')), Host::matches($mk('WWW.FCSOCHAUXRETRO.COM:443')), Host::matches($mk('fcsochauxretro.com')), Host::matches($mk('musee.fcsochauxretro.com')), Host::matches($mk('exemple.com')), Host::matches($mk('www.fcsochauxretro.com/x'))], [true, true, true, false, false, false]);
$setting(['vitrine.base_url' => 'https://musee.fcsochauxretro.com']);
$eq('même adresse que le musée : jamais le site de l’association', Host::matches($mk('musee.fcsochauxretro.com')), false);
$setting(['vitrine.base_url' => 'https://www.fcsochauxretro.com']);
$eq('adresse de l’aperçu', [Host::isPreview('/apercu-association'), Host::isPreview('/apercu-association/contact/'), Host::isPreview('/apercu-associations/')], [true, true, false]);

// ---------------------------------------------------------------- site fermé
$r = $req('www.fcsochauxretro.com', '/');
$eq('fermé : page d’attente 503, non indexée', [$r->status, $r->headers['X-Robots-Tag'] ?? '', str_contains($r->body, 'Le nouveau site de l’association arrive')], [503, 'noindex, nofollow', true]);
$eq('fermé : pages légales servies', [$req('www.fcsochauxretro.com', '/mentions-legales/')->status, $req('www.fcsochauxretro.com', '/confidentialite/')->status], [200, 200]);
$r = $req('www.fcsochauxretro.com', '/matchs/');
$eq('fermé : ancienne adresse WordPress connue du musée → musée (301)', [$r->status, $r->headers['Location'] ?? ''], [301, 'https://musee.fcsochauxretro.com/matchs/']);
$r = $req('www.fcsochauxretro.com', '/robots.txt');
$eq('fermé : robots.txt interdit tout', trim($r->body), "User-agent: *\nDisallow: /");
$r = $req('fcsochauxretro.com', '/contact/', 'GET', ['objet' => 'presse']);
$eq('domaine nu → www (301, chemin et paramètres gardés)', [$r->status, $r->headers['Location'] ?? ''], [301, 'https://www.fcsochauxretro.com/contact/?objet=presse']);
$r = $req('www.fcsochauxretro.com', '/admin/association');
$eq('back-office demandé sur www → musée', [$r->status, $r->headers['Location'] ?? ''], [302, 'https://musee.fcsochauxretro.com/admin/association']);
$r = $req('musee.fcsochauxretro.com', '/apercu-association/');
$eq('aperçu sans être connecté : connexion du back-office', [$r->status, str_starts_with($r->headers['Location'] ?? '', '/admin/connexion?r=')], [302, true]);

// ---------------------------------------------------------------- site ouvert
$setting(['vitrine.open' => true]);
$pages = ['/', '/association/', '/association/equipe/', '/association/statuts-et-documents/', '/nos-actions/', '/actualites/', '/agenda/', '/nous-soutenir/', '/nous-soutenir/adherer/', '/nous-soutenir/benevolat/', '/partenaires/', '/presse/', '/contact/', '/plan-du-site/', '/cookies/'];
foreach (Content::actions() as $a) {
    $pages[] = '/nos-actions/' . $a['slug'] . '/';
}
$bad = [];
foreach ($pages as $p) {
    $r = $req('www.fcsochauxretro.com', $p);
    if ($r->status !== 200 || !str_contains($r->body, '</html>') || isset($r->headers['X-Robots-Tag'])) {
        $bad[] = "$p ($r->status)";
    }
}
$eq('ouvert : ' . count($pages) . ' pages en 200, indexables', $bad, []);
$home = $req('www.fcsochauxretro.com', '/')->body;
$eq('accueil : canonique www, image de partage, données structurées', [str_contains($home, '<link rel="canonical" href="https://www.fcsochauxretro.com/">'), str_contains($home, 'og:image" content="https://www.fcsochauxretro.com/assets/img/vitrine/partage.jpg'), str_contains($home, '"@type":"NGO"')], [true, true, true]);
$eq('accueil : lien vers le musée, adhésion, aucune adresse de l’aperçu', [str_contains($home, 'href="https://musee.fcsochauxretro.com/"'), str_contains($home, 'href="/nous-soutenir/adherer/"'), str_contains($home, 'apercu-association')], [true, true, false]);
$eq('ouvert : sans barre finale → avec (301)', $req('www.fcsochauxretro.com', '/association')->headers['Location'] ?? '', '/association/');
$eq('ouvert : adresse inconnue partout → 404 du site', $req('www.fcsochauxretro.com', '/nexiste-pas-du-tout/')->status, 404);
$eq('ouvert : action inconnue → 404', $req('www.fcsochauxretro.com', '/nos-actions/inconnue/')->status, 404);
$r = $req('www.fcsochauxretro.com', '/sitemap.xml');
$eq('plan du site XML : adresses www, aucune actualité « à vérifier »', [substr_count($r->body, '<loc>https://www.fcsochauxretro.com/') > 20, str_contains($r->body, 'le-musee-en-ligne-ouvre-ses-portes'), str_contains($r->body, 'musee.fcsochauxretro.com')], [true, false, false]);

// ---------------------------------------------------------------- contenus « à vérifier »
$news = array_column(Content::news(), 'slug');
$eq('actualité « à vérifier » : invisible du public', [in_array('le-musee-en-ligne-ouvre-ses-portes', $news, true), in_array('100-moments-pour-100-ans', $news, true)], [false, true]);
$eq('page d’une actualité « à vérifier » : 404', $req('www.fcsochauxretro.com', '/actualites/le-musee-en-ligne-ouvre-ses-portes/')->status, 404);
$eq('événements d’exemple « à vérifier » : invisibles, centenaire présent', [count(array_filter(Content::events(), fn ($e) => $e['kind'] === 'asso')), (bool) array_filter(Content::events(), fn ($e) => $e['kind'] === 'centenaire')], [0, true]);
Site::$preview = true;
$eq('aperçu : actualités et événements à vérifier visibles', [count(Content::news()), count(array_filter(Content::events(), fn ($e) => $e['kind'] === 'asso'))], [6, 3]);
Site::$preview = false;
$eq('équipe : aucun nom inventé, rien n’est affiché', [Content::team(), count(Content::poles()) >= 4], [[], true]);
$eq('partenaires et presse : vides (rien d’inventé)', [Content::partners(), Content::press()], [[], []]);
$eq('chiffres : « 11000+ » → « 11 000+ »', Content::figures()[0]['n'], '11 000+');
$eq('tarifs d’exemple, marqués « à valider »', [array_column(Content::tariffs(), 'amount'), !empty(Store::get('tarifs')['a_verifier'])], [[15, 10, 25, 50], true]);

// ---------------------------------------------------------------- agenda iCal
$r = $req('www.fcsochauxretro.com', '/agenda/agenda.ics');
$lines = explode("\r\n", rtrim($r->body, "\r\n"));
$eq('iCal : type, enveloppe, lignes de 75 octets au plus', [$r->headers['Content-Type'], $lines[0], end($lines), max(array_map('strlen', $lines)) <= 75], ['text/calendar; charset=UTF-8', 'BEGIN:VCALENDAR', 'END:VCALENDAR', true]);
$eq('iCal : le centenaire en journée entière', str_contains($r->body, 'DTSTART;VALUE=DATE:' . str_replace('-', '', (string) Settings::get('home.centenary_date'))), true);

// ---------------------------------------------------------------- aperçu (préfixe des liens)
Host::$prefix = Host::PREVIEW;
$eq('aperçu : liens internes préfixés, musée et ressources intacts', [Host::url('/agenda/'), \App\Vitrine\Pages::link('/contact/')[0], \App\Vitrine\Pages::link('musee:/contribuer/')[0], \App\Vitrine\Pages::rich('<p><a href="/agenda/">a</a> <a href="/media/1.jpg">b</a> <a href="https://x.fr/">c</a></p>')],
    ['/apercu-association/agenda/', '/apercu-association/contact/', 'https://musee.fcsochauxretro.com/contribuer/', '<p><a href="/apercu-association/agenda/">a</a> <a href="/media/1.jpg">b</a> <a href="https://x.fr/">c</a></p>']);
Host::$prefix = '';
$eq('liens saisis : réseau social réglé, adresse invalide ignorée', [\App\Vitrine\Pages::link('social:youtube')[0], \App\Vitrine\Pages::link('javascript:alert(1)')[0], \App\Vitrine\Pages::link('//evil.example')[0]], [(string) Settings::get('social.youtube'), '', '']);

// ---------------------------------------------------------------- formulaires (antispam)
$_SERVER['REMOTE_ADDR'] = '127.0.0.9';
$r = $req('www.fcsochauxretro.com', '/contact/', 'POST', [], ['name' => 'X', 'email' => 'x@example.org', 'message' => 'Bonjour, ceci est un essai.', 'consent' => '1']);
$eq('contact sans jeton CSRF : refusé, rien d’enregistré', [$r->status, $_SESSION['vt_flash_contact']['type'] ?? ''], [303, 'error']);
$r = $req('www.fcsochauxretro.com', '/newsletter/', 'POST', [], ['email' => 'robot@example.org', 'website' => 'http://spam', '_ts' => form_ts(), 'back' => '/agenda/', 'anchor' => 'nl-agenda']);
$eq('newsletter : robot (champ piège) → réponse neutre, sans inscription', $r->headers['Location'] ?? '', '/agenda/?nl=ok&f=nl-agenda#nl-agenda');
$r = $req('www.fcsochauxretro.com', '/newsletter/', 'POST', [], ['email' => 'trop.vite@example.org', '_ts' => form_ts(), 'back' => '//evil.example/', 'anchor' => 'x"><script>']);
$eq('newsletter : envoi instantané refusé, retour et ancre nettoyés', $r->headers['Location'] ?? '', '/?nl=vite&f=nl-foot#nl-foot');

// ---------------------------------------------------------------- adhésions : paiement confirmé par webhook Stripe signé
$a = ['id' => Membership::newId(), 'created' => date('c'), 'updated' => date('c'), 'year' => (int) date('Y'), 'tariff' => 'individuel', 'label' => 'Adhésion individuelle', 'amount' => 1500,
    'status' => 'pending', 'provider' => 'stripe', 'mode' => 'test', 'member' => ['first' => 'Essai', 'last' => 'Webhook', 'email' => 'essai.webhook@example.org', 'phone' => '', 'address' => '', 'zip' => '', 'city' => '', 'family' => ''],
    'newsletter' => false, 'source' => '', 'ext' => ['stripe_session' => 'cs_test_essai'], 'payments' => [], 'ip' => '', 'origin' => 'site'];
Membership::put($a);
$secret = 'whsec_' . bin2hex(random_bytes(12));
$setting(['donations.stripe_webhook_secret' => $secret]);
$stripe = function (array $event) use ($req, $secret) {
    $payload = json_encode($event);
    $t = time();
    return $req('musee.fcsochauxretro.com', '/api/dons/stripe/webhook', 'POST', [], [], $payload, ['HTTP_STRIPE_SIGNATURE' => 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $payload, $secret), 'CONTENT_TYPE' => 'application/json']);
};
$session = ['id' => 'cs_test_essai', 'object' => 'checkout.session', 'payment_status' => 'paid', 'status' => 'complete', 'amount_total' => 1500, 'payment_intent' => 'pi_test_essai', 'client_reference_id' => $a['id'], 'metadata' => ['adhesion' => $a['id']], 'mode' => 'payment'];
$r = $req('musee.fcsochauxretro.com', '/api/dons/stripe/webhook', 'POST', [], [], json_encode(['id' => 'evt_faux', 'type' => 'checkout.session.completed', 'data' => ['object' => $session]]), ['HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=00', 'CONTENT_TYPE' => 'application/json']);
$eq('webhook à la signature fausse : refusé, adhésion inchangée', [$r->status, Membership::get($a['id'])['status']], [400, 'pending']);
$r = $stripe(['id' => 'evt_essai_' . bin2hex(random_bytes(4)), 'type' => 'checkout.session.completed', 'data' => ['object' => $session]]);
$got = Membership::get($a['id']);
$eq('webhook signé : adhésion payée, paiement enregistré', [$r->status, $got['status'], count($got['payments']), $got['payments'][0]['ref'] ?? ''], [200, 'paid', 1, 'pi_test_essai']);
$stripe(['id' => 'evt_essai_' . bin2hex(random_bytes(4)), 'type' => 'checkout.session.completed', 'data' => ['object' => $session]]);
$eq('même paiement reçu deux fois : compté une seule fois', count(Membership::get($a['id'])['payments']), 1);
$stripe(['id' => 'evt_essai_' . bin2hex(random_bytes(4)), 'type' => 'charge.refunded', 'data' => ['object' => ['id' => 'ch_essai', 'payment_intent' => 'pi_test_essai', 'refunded' => true]]]);
$eq('remboursement chez Stripe : adhésion « Remboursée »', Membership::get($a['id'])['status'], 'refunded');
$b = ['id' => Membership::newId(), 'provider' => 'paypal', 'status' => 'pending', 'ext' => ['paypal_order' => 'ORDER-ESSAI'], 'payments' => [], 'member' => ['first' => 'Pay', 'last' => 'Pal', 'email' => '']] + $a;
Membership::put($b);
Membership::paypalCaptureEvent(['id' => 'CAPTURE-ESSAI', 'custom_id' => $b['id'], 'status' => 'COMPLETED', 'amount' => ['value' => '15.00']]);
$eq('capture PayPal (webhook) : adhésion payée', [Membership::get($b['id'])['status'], Membership::get($b['id'])['payments'][0]['amount'] ?? 0], ['paid', 1500]);
$eq('identifiants d’adhésion', [Membership::validId($a['id']), Membership::validId('D261004-abcdef'), Membership::validId('A261004-ABCDEF'), Membership::validId('../A261004-abcdef')], [true, false, false, false]);

// ---------------------------------------------------------------- durées de conservation (RGPD)
$old = fn (array $x) => array_replace_recursive($a, ['id' => Membership::newId(), 'payments' => []], $x);
$c1 = $old(['status' => 'abandoned', 'created' => date('c', strtotime('-40 days'))]);
$c2 = $old(['status' => 'paid', 'year' => (int) date('Y') - 4, 'member' => ['email' => 'ancien@example.org', 'address' => '1 rue du Stade']]);
$c3 = $old(['status' => 'paid', 'year' => (int) date('Y') - 11]);
$c4 = $old(['status' => 'abandoned', 'created' => date('c', strtotime('-5 days'))]);
foreach ([$c1, $c2, $c3, $c4] as $x) {
    Membership::put($x);
}
Membership::purge();
$eq('purge : abandonnée depuis 40 jours et adhésion de plus de 10 ans effacées, récente gardée', [Membership::get($c1['id']), Membership::get($c3['id']) !== null, Membership::get($c4['id']) !== null], [null, false, true]);
$k2 = Membership::get($c2['id']);
$eq('purge : après 3 ans, coordonnées effacées, nom et montant gardés', [$k2['member']['email'], $k2['member']['address'], $k2['member']['last'], $k2['amount']], ['', '', 'Webhook', 1500]);
\App\Core\JsonStore::write(Forms::VOLUNTEERS, ['B200101-aaaaaa' => ['id' => 'B200101-aaaaaa', 'at' => date('c', strtotime('-3 years')), 'status' => 'nouveau'], 'B200101-bbbbbb' => ['id' => 'B200101-bbbbbb', 'at' => date('c', strtotime('-3 years')), 'status' => 'actif'], 'B200101-cccccc' => ['id' => 'B200101-cccccc', 'at' => date('c'), 'status' => 'nouveau']]);
Forms::purgeVolunteers();
$eq('purge des bénévoles : sans suite depuis 2 ans effacés, actifs et récents gardés', array_keys(\App\Core\JsonStore::read(Forms::VOLUNTEERS, [])), ['B200101-bbbbbb', 'B200101-cccccc']);

// ---------------------------------------------------------------- documents
$eq('documents : noms suspects refusés', [Documents::path('../config/settings.php'), Documents::path('statuts.php'), Documents::path('.htaccess'), Documents::path('a/b.pdf')], [null, null, null, null]);
$eq('dossier de presse livré avec le site', [Documents::path('presentation-sochaux-retro.pdf') !== null, $req('www.fcsochauxretro.com', '/presse/presentation-sochaux-retro.pdf')->status], [true, 200]);

// ---------------------------------------------------------------- saisies du pavé (nettoyage)
$clean = new ReflectionMethod(\App\Admin\Association::class, 'cleanItem');
$fields = \App\Admin\Association::schemas()['agenda']['fields'];
$item = $clean->invoke(null, ['title' => '<b>Expo</b> au stade', 'body' => '<p onclick="x()">Texte<script>alert(1)</script></p>', 'start' => '2027-03-12T20:00', 'link' => 'javascript:alert(1)', 'slug' => 'Expo à Bonal !', 'image' => '../../etc/passwd'], $fields);
$eq('saisie : balises retirées du titre, script et événements retirés du texte', [$item['title'], str_contains($item['body'], 'script') || str_contains($item['body'], 'onclick')], ['Expo au stade', false]);
$eq('saisie : date, lien dangereux neutralisé, adresse propre, image inconnue refusée', [$item['start'], $item['link'], $item['slug'], $item['image']], ['2027-03-12 20:00', '/javascript:alert(1)', 'expo-a-bonal', null]);
$uniq = new ReflectionMethod(\App\Admin\Association::class, 'uniqueSlugs');
$eq('adresses : remplies d’après le titre et uniques', array_column($uniq->invoke(null, [['title' => 'Assemblée générale', 'slug' => ''], ['title' => 'Assemblée générale', 'slug' => ''], ['title' => 'Autre', 'slug' => 'assemblee-generale']], $fields, 'title'), 'slug'), ['assemblee-generale', 'assemblee-generale-2', 'assemblee-generale-3']);
$pf = \App\Admin\Association::pageFields(Store::defaults('pages')['association']);
$eq('champs des pages déduits du contenu', [$pf['story'][1], $pf['image'][1], $pf['missions'][1], $pf['a_verifier'][1], $pf['title'][1]], ['html', 'image', 'list', 'lines', 'text']);
$checks = array_column(\App\Admin\Association::checklist(), 1);
$eq('tableau de bord : tarifs, équipe et documents à vérifier', [(bool) preg_grep('/Tarifs d’adhésion/', $checks), (bool) preg_grep('/^Équipe/', $checks), (bool) preg_grep('/Statuts et documents/', $checks)], [true, true, true]);
$eq('pavé réservé aux administrateurs (routeur)', [\App\Admin\Router::adminOnly('/admin/association'), \App\Admin\Router::adminOnly('/admin/association/adhesions/export.csv'), \App\Admin\Router::adminOnly('/admin/associations')], [true, true, false]);

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
