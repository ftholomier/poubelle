<?php
/**
 * Page d'attente et ouverture du site : le public ne voit que la page d'attente, l'équipe connectée
 * voit le site (avec un bandeau), rien n'est indexé tant que le site est fermé, teaser vidéo
 * secret tant qu'il n'est pas activé. Réglages et connexion simulés en mémoire : rien n'est écrit.
 * Usage : php tests/attente.php (code 1 si échec).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
// Sortie gardée jusqu'à la fin : l'écran du mot de passe ouvre une session (sinon avertissement
// « en-têtes déjà envoyés » dans le journal) ; elle est supprimée à la fin. Le relevé des pages
// introuvables (storage/404.json), complété par les essais de 404, est remis en état.
ob_start();
$log404 = STORAGE_PATH . '/404.json';
$log404Before = is_file($log404) ? (string) file_get_contents($log404) : null;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Kernel;
use App\Services\I18n;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$values = new ReflectionProperty(Settings::class, 'values');
$current = new ReflectionProperty(Auth::class, 'current');
$loaded = new ReflectionProperty(Auth::class, 'loaded');
$settings = function (array $v) use ($values) {
    $values->setValue(null, $v);
};
$login = function (bool $on) use ($current, $loaded) {
    $current->setValue(null, $on ? ['id' => 'essai', 'name' => 'Essai', 'email' => 'essai@example.org', 'role' => 'user', 'status' => 'active'] : null);
    $loaded->setValue(null, true);
};
$get = function (string $path, array $query = [], array $server = []): Response {
    I18n::set('fr');
    return Kernel::handle(new Request('GET', $path, $query, [], [], $server + ['HTTP_HOST' => 'musee.fcsochauxretro.com', 'REQUEST_URI' => $path, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'test-attente'], ''));
};
$closed = ['waiting.enabled' => true];
$open = ['waiting.enabled' => false];

// 1. Installation neuve : site fermé, pas indexé, teaser secret ; adresse du musée par défaut
$d = Settings::defaults();
$eq('installation neuve : page d’attente active', $d['waiting.enabled'], true);
$eq('installation neuve : teaser désactivé', $d['waiting.teaser'], false);
$eq('installation neuve : réglage « masquer aux moteurs » décoché', $d['general.noindex'], false);
$eq('adresse du site par défaut : le sous-domaine du musée', $d['general.base_url'], 'https://musee.fcsochauxretro.com');
$settings([]);
$login(false);
$r = $get('/');
$eq('aucun réglage enregistré : le public voit la page d’attente (503)', $r->status, 503);
$eq('aucun réglage enregistré : robots.txt interdit tout', str_contains($get('/robots.txt')->body, "Disallow: /\n"), true);

// 2. Site fermé, visiteur anonyme
$settings($closed);
$r = $get('/');
$eq('public : page d’attente', [$r->status, str_contains($r->body, 'class="waiting"')], [503, true]);
$eq('public : page d’attente jamais indexée ni gardée en cache', [$r->headers['X-Robots-Tag'] ?? null, $r->headers['Cache-Control'] ?? null], ['noindex, nofollow', 'no-store']);
$eq('public : aucun lien vers le back-office sur la page d’attente', str_contains($r->body, '/admin'), false);
$r = $get('/matchs/1990-1991/');
$eq('public : une page profonde donne aussi la page d’attente', [$r->status, str_contains($r->body, 'class="waiting"')], [503, true]);
$r = $get('/en/matchs/');
$eq('public : version anglaise fermée aussi', [$r->status, str_contains($r->body, 'class="waiting"')], [503, true]);
$r = $get('/mentions-legales/');
$eq('public : pages légales lisibles mais non indexées', [$r->status, $r->headers['X-Robots-Tag'] ?? null], [200, 'noindex, nofollow']);
$r = $get('/api/recherche', ['q' => 'paille']);
$eq('public : API fermée', $r->status, 503);
$r = $get('/robots.txt');
$eq('robots.txt : tout est interdit aux moteurs', [trim($r->body), $r->headers['X-Robots-Tag'] ?? null], ["User-agent: *\nDisallow: /", 'noindex, nofollow']);
$r = $get('/sitemap.xml');
$eq('plan du site : page d’attente pour le public', $r->status, 503);
$eq('public : pas de bandeau d’équipe sur la page d’attente', str_contains($get('/')->body, 'team-bar'), false);

// 3. Site fermé, membre de l'équipe connecté : le vrai site, avec un bandeau, jamais indexé ni en cache
$login(true);
$r = $get('/');
$eq('équipe connectée : le vrai site', [$r->status, str_contains($r->body, 'id="contenu"')], [200, true]);
$eq('équipe connectée : bandeau « site fermé au public »', str_contains($r->body, 'class="team-bar"') && str_contains($r->body, 'Site fermé au public</b> (page d’attente)'), true);
$eq('équipe connectée : lien « Voir ce que voit le public »', str_contains($r->body, 'href="/?apercu-attente=1"'), true);
$eq('équipe connectée : page non indexée et gardée dans aucun cache', [$r->headers['X-Robots-Tag'] ?? null, $r->headers['Cache-Control'] ?? null], ['noindex, nofollow', 'private, no-store']);
$r = $get('/matchs/1990-1991/');
$eq('équipe connectée : pages profondes visibles', [$r->status, str_contains($r->body, 'class="team-bar"')], [200, true]);
$r = $get('/en/matchs/');
$eq('équipe connectée : version anglaise visible', $r->status, 200);
$r = $get('/', ['apercu-attente' => '1']);
$eq('équipe connectée : aperçu de la page d’attente', [$r->status, str_contains($r->body, 'class="waiting"')], [503, true]);
$r = $get('/robots.txt');
$eq('robots.txt fermé même pour l’équipe connectée', str_contains($r->body, "Disallow: /\n"), true);

// 4. Mot de passe d'accès (sans page d'attente)
$settings($open + ['general.front_password' => 'essai-mot-de-passe']);
$login(false);
$r = $get('/');
$eq('mot de passe : le public voit l’écran du mot de passe, non indexé', [$r->status, $r->headers['X-Robots-Tag'] ?? null], [401, 'noindex, nofollow']);
$login(true);
$r = $get('/');
$eq('mot de passe : l’équipe connectée voit le site, avec le bandeau', [$r->status, str_contains($r->body, 'Site fermé au public</b> (mot de passe d’accès)')], [200, true]);
$eq('mot de passe : pas de lien vers l’aperçu de la page d’attente', str_contains($r->body, 'apercu-attente'), false);

// 5. Site ouvert
$settings($open);
$login(false);
$r = $get('/');
$eq('ouvert : le public voit le site, indexable', [$r->status, $r->headers['X-Robots-Tag'] ?? null], [200, null]);
$r = $get('/robots.txt');
$eq('ouvert : robots.txt avec le plan du site', !str_contains($r->body, "Disallow: /\n") && str_contains($r->body, 'Sitemap: '), true);
$login(true);
$eq('ouvert : pas de bandeau pour l’équipe', str_contains($get('/')->body, 'team-bar'), false);
$settings($open + ['general.noindex' => true]);
$login(false);
$r = $get('/');
$eq('ouvert mais masqué aux moteurs : visible, non indexé', [$r->status, $r->headers['X-Robots-Tag'] ?? null], [200, 'noindex, nofollow']);
$eq('ouvert mais masqué aux moteurs : robots.txt interdit tout', str_contains($get('/robots.txt')->body, "Disallow: /\n"), true);
$login(true);
$eq('ouvert mais masqué : pas de bandeau « fermé au public »', str_contains($get('/')->body, 'team-bar'), false);

// 6. Teaser vidéo : secret tant que le site est fermé et que la page d'attente ne le montre pas
$file = APP_DIR . '/Resources/video/teaser.mp4';
$size = (int) filesize($file);
$eq('le teaser est livré hors du dossier public', [is_file($file), is_file(PUBLIC_PATH . '/video/teaser.mp4')], [true, false]);
$eq('teaser de l’accueil activé par défaut', Settings::defaults()['home.teaser'], true);
$settings($closed);
$login(false);
$r = $get('/');
$eq('site fermé : teaser absent de la page d’attente', str_contains($r->body, 'teaser'), false);
$r = $get('/video/teaser.mp4');
$eq('site fermé : vidéo introuvable pour le public (page d’attente à la place)', [$r->status, $r->file], [503, null]);
$r = $get('/video/teaser.jpg');
$eq('site fermé : image introuvable pour le public', $r->file, null);
$login(true);
$r = $get('/');
$eq('site fermé : l’équipe voit le teaser sur l’accueil', str_contains($r->body, '<section id="teaser"') && str_contains($r->body, '<source src="/video/teaser.mp4"'), true);
$r = $get('/video/teaser.mp4');
$eq('site fermé : vidéo servie à l’équipe, jamais en cache', [$r->status, $r->headers['Content-Type'] ?? null, $r->headers['Cache-Control'] ?? null], [200, 'video/mp4', 'private, no-store']);
$settings($open);
$login(false);
$r = $get('/');
$eq('site ouvert : teaser sur l’accueil pour le public, servi par Apache (adresse versionnée)', str_contains($r->body, '<section id="teaser"') && (bool) preg_match('#poster="/assets/video/teaser-[0-9a-f]{10}\.jpg"#', $r->body) && (bool) preg_match('#<source src="/assets/video/teaser-[0-9a-f]{10}\.mp4"#', $r->body), true);
$r = $get('/video/teaser.mp4');
$eq('site ouvert : vidéo servie au public, gardée en cache', [$r->status, $r->headers['Cache-Control'] ?? null], [200, 'public, max-age=86400']);
$settings($open + ['home.teaser' => false]);
$r = $get('/');
$eq('teaser de l’accueil décoché : absent de l’accueil', str_contains($r->body, 'id="teaser"'), false);
$r = $get('/video/teaser.mp4');
$eq('teaser décoché partout, site ouvert : vidéo introuvable (404)', [$r->status, $r->file], [404, null]);
$login(true);
$r = $get('/video/teaser.mp4');
$eq('teaser décoché : toujours visible par l’équipe (aperçu)', [$r->status, $r->headers['Cache-Control'] ?? null], [200, 'private, no-store']);
$settings($closed);
$r = $get('/', ['apercu-attente' => '1', 'teaser' => '1']);
$eq('aperçu de l’équipe avec le teaser, sans l’activer', [$r->status, str_contains($r->body, '<source src="/video/teaser.mp4" type="video/mp4">')], [503, true]);
$login(false);
$r = $get('/', ['apercu-attente' => '1', 'teaser' => '1']);
$eq('« aperçu avec le teaser » sans être connecté : rien de plus', str_contains($r->body, 'teaser'), false);
$settings($closed + ['waiting.teaser' => true]);
$r = $get('/');
$eq('teaser activé : sur la page d’attente, avec son affiche, servi par Apache', (bool) preg_match('#poster="/assets/video/teaser-[0-9a-f]{10}\.jpg"#', $r->body) && (bool) preg_match('#<source src="/assets/video/teaser-[0-9a-f]{10}\.mp4"#', $r->body), true);
$r = $get('/video/teaser.mp4');
$eq('teaser activé : vidéo servie au public, non indexée', [$r->status, $r->headers['Content-Type'] ?? null, $r->headers['Accept-Ranges'] ?? null, $r->headers['Content-Length'] ?? null, $r->headers['X-Robots-Tag'] ?? null], [200, 'video/mp4', 'bytes', (string) $size, 'noindex, nofollow']);
$r = $get('/video/teaser.jpg');
$eq('teaser activé : affiche servie', [$r->status, $r->headers['Content-Type'] ?? null], [200, 'image/jpeg']);
$r = $get('/video/teaser.mp4', [], ['HTTP_RANGE' => 'bytes=0-99']);
$eq('lecture par morceaux : début', [$r->status, $r->headers['Content-Range'] ?? null, $r->headers['Content-Length'] ?? null, $r->range], [206, "bytes 0-99/$size", '100', [0, 99]]);
$r = $get('/video/teaser.mp4', [], ['HTTP_RANGE' => 'bytes=1000-']);
$eq('lecture par morceaux : jusqu’à la fin', [$r->status, $r->headers['Content-Range'] ?? null], [206, 'bytes 1000-' . ($size - 1) . "/$size"]);
$r = $get('/video/teaser.mp4', [], ['HTTP_RANGE' => 'bytes=-500']);
$eq('lecture par morceaux : les derniers octets', [$r->status, $r->range], [206, [$size - 500, $size - 1]]);
$r = $get('/video/teaser.mp4', [], ['HTTP_RANGE' => 'bytes=' . ($size + 10) . '-']);
$eq('lecture par morceaux : demande hors du fichier → 416', [$r->status, $r->headers['Content-Range'] ?? null], [416, "bytes */$size"]);
$r = $get('/video/teaser.mp4', [], ['HTTP_RANGE' => 'bytes=0-9,20-29']);
$eq('plusieurs morceaux demandés : fichier entier', [$r->status, $r->range], [200, null]);
// Octets réellement envoyés (dans un processus à part : l'envoi vide les tampons de sortie)
$code = 'require ' . var_export(APP_ROOT . '/app/bootstrap.php', true) . '; App\Core\Response::media(' . var_export($file, true) . ', "video/mp4", "bytes=1234-5677")->send();';
$out = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>/dev/null');
$eq('lecture par morceaux : les bons octets sont envoyés', md5($out), md5((string) file_get_contents($file, false, null, 1234, 4444)));

$values->setValue(null, null);
$current->setValue(null, null);
$loaded->setValue(null, false);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}
if ($log404Before === null) {
    @unlink($log404);
} else {
    file_put_contents($log404, $log404Before);
}
echo $fail ? "$fail échec(s)\n" : "Tout est bon\n";
ob_end_flush();
exit($fail ? 1 : 0);
