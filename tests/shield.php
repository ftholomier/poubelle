<?php
/** Anti-aspiration : robots d'IA et aspirateurs refusés, navigateurs et vrais moteurs acceptés, débit limité, robots.txt. */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Core\Shield;

$fail = 0;
$ok = function (bool $c, string $m) use (&$fail) { echo ($c ? '  ok  ' : '  ÉCHEC ') . $m . "\n"; $fail += $c ? 0 : 1; };
$req = fn (string $path, string $ua, string $ip = '203.0.113.7', array $extra = []) => new Request('GET', $path, [], [], [], ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $ua, 'HTTP_HOST' => 'musee.fcsochauxretro.com'] + $extra, '');
$chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

foreach (['GPTBot/1.1', 'Mozilla/5.0 (compatible; ClaudeBot/1.0)', 'CCBot/2.0', 'python-requests/2.31', 'Wget/1.21', 'curl/8.4', 'Mozilla/5.0 HeadlessChrome/120', 'HTTrack 3.0', ''] as $ua) {
    $r = Shield::check($req('/matchs/', $ua, '198.51.100.' . random_int(1, 250)));
    $ok($r !== null && $r->status === 403, 'refusé : « ' . ($ua ?: '(vide)') . ' »');
}
$ok(Shield::check($req('/robots.txt', 'GPTBot/1.1')) === null, 'robots.txt reste lisible par GPTBot');
$ok(Shield::check($req('/matchs/', $chrome)) === null, 'navigateur accepté');
$ok(Shield::check($req('/', 'facebookexternalhit/1.1')) === null, 'aperçu Facebook accepté');
$r = Shield::check($req('/', 'Mozilla/5.0 (compatible; Googlebot/2.1)', '198.51.100.250'));
$ok($r !== null && $r->status === 403, 'faux Googlebot refusé');
$r = Shield::check($req('/media/full/2024/x.jpg', $chrome, '203.0.113.9', ['HTTP_REFERER' => 'https://pilleur.example/page']));
$ok($r !== null && $r->status === 403, 'grand format affiché sur un autre site : refusé');
$ok(Shield::check($req('/media/full/2024/x.jpg', $chrome, '203.0.113.10', ['HTTP_REFERER' => 'https://musee.fcsochauxretro.com/matchs/'])) === null, 'grand format depuis le musée : accepté');
$ip = '192.0.2.' . random_int(1, 250);
$blocked = 0;
for ($i = 0; $i < Shield::PAGES_MIN + 5; $i++) {
    $r = Shield::check($req('/joueurs/', $chrome, $ip));
    $blocked += $r && $r->status === 429 ? 1 : 0;
}
$ok($blocked === 5, 'débit : freiné au-delà de ' . Shield::PAGES_MIN . ' pages par minute (' . $blocked . ' refus)');
$robots = \App\Front\Seo::robots()->body;
$ok(str_contains($robots, "User-agent: GPTBot") && str_contains($robots, "User-agent: Google-Extended"), 'robots.txt interdit les robots d’IA');
$ok(array_sum(array_map('array_sum', Shield::stats())) > 0, 'blocages comptés pour les Statistiques');
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
