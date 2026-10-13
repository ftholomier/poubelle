<?php
/**
 * Widgets du musée (App\Widgets) : API JSON, iframe, accès libre / réservé / payant / désactivé,
 * clé invalide ou suspendue, domaine non autorisé, site fermé, cache (ETag, 304), mention et lien
 * obligatoires, aucune donnée personnelle, statistiques et clics, photos diffusables seulement ;
 * vague 2 : records, quiz du jour (vérification au serveur), défi, dernier match, saison, palmarès,
 * frise, bandeau, réglage « source » de la carte de légende, script d'intégration /embed.js ;
 * vague 3 : histoire du duel, carte des stades, compte à rebours, soutenir, recherche,
 * Rétro-Direct, mode écran (/ecran/…, playlists, accès, quiz écran, export PNG, ZIP et vidéo MP4).
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
    return Kernel::handle(new Request($method, $path, $query, [], [], $server, $headers['_body'] ?? ''));
};
$json = fn (Response $r) => json_decode($r->body, true);
$keys = array_keys(Registry::CLASSES);

try {
    // ------------------------------------------------------------------ catalogue
    $ok($keys === ['ce-jour-la', 'chiffre-du-jour', 'il-y-a', 'carte-legende', 'photo-du-jour', 'le-saviez-vous', 'records', 'quiz-du-jour', 'defi-du-jour', 'dernier-match', 'saison', 'palmares', 'frise', 'bandeau',
        'histoire-du-duel', 'carte-stades', 'compte-a-rebours', 'soutenir', 'recherche', 'retro-direct', 'playlist', 'boutique', 'don'], 'registre : les 6 widgets de la phase 1, les 8 de la vague 2, les 7 restants de la vague 3, la boutique et le don');
    $ok(Registry::get('don')->category() === ['musee'] && Store::widget('don')['access'] === 'libre', 'don : catégorie « Musée & soutien », accès libre par défaut');
    $ok(Registry::PLANNED === [], 'plus aucun widget « à venir »');
    $ok(Store::widget('playlist')['access'] === 'reserve', 'mode écran (playlist) : réservé par défaut');
    // Boutique et don : données seulement boutique / dons ouverts, essayés à part (plus bas).
    $keys = array_values(array_filter($keys, fn ($k) => !in_array($k, ['playlist', 'boutique', 'don'], true)));
    $r = $get('/api/v1/widgets');
    $c = $json($r);
    $ok($r->status === 200 && count($c['widgets'] ?? []) === 22 && !in_array('playlist', array_column($c['widgets'], 'key'), true), 'catalogue : 22 widgets libres par défaut (pas le mode écran, réservé)');
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
        $ok(!str_contains($f->body, 'src="/') && !preg_match('#href="/(?!/)#', $f->body), "iframe $k : liens et images en adresses complètes");
    }
    $en = $get('/w/chiffre-du-jour', ['lang' => 'en']);
    $ok(str_contains($en->body, 'lang="en"') && str_contains($en->body, 'Independent, unofficial museum') && str_contains($en->body, 'to=%2Fen%2F'), 'iframe en anglais : mention et liens en anglais');
    \App\Services\I18n::set('fr');
    $compact = $get('/w/ce-jour-la', ['format' => 'compact']);
    $ok(str_contains($compact->body, 'w--compact'), 'format compact');


    // ------------------------------------------------------------------ vague 2
    $data = fn (string $k, array $q = []) => $json($get('/api/v1/widgets/' . $k, $q))['data'] ?? null;
    $frame = fn (string $k, array $q = []) => $get('/w/' . $k, $q)->body;
    \App\Services\I18n::set('fr');

    // Records : mêmes chiffres que /records/ (totaux de référence compris).
    foreach (['buteurs', 'matchs'] as $cat) {
        $ref = \App\Front\Explore::recordRows($cat, null, null, 10);
        $d = $data('records', ['cat' => $cat]);
        $ok($d !== null && count($d['rows']) === min(10, count($ref)) && array_column($d['rows'], 'value') === array_map(fn ($r) => (int) $r['v'], $ref) && array_column($d['rows'], 'name') === array_column($ref, 'name'), "records ($cat) : le top 10 de /records/");
    }
    $ok($data('records', ['cat' => 'minutes'])['cat'] === 'buteurs', 'records : classement inconnu → buteurs');
    $ok(str_contains($frame('records', ['cat' => 'matchs']), 'w-rank') && str_contains($frame('records', ['cat' => 'matchs']), '%2Frecords%2F%3Fcat%3Dmatchs'), 'records : liste et lien vers /records/?cat=matchs');

    // Quiz du jour : jamais la bonne réponse dans les données ; vérification par POST.
    $q = \App\Widgets\Items\QuizDuJour::question(date('Y-m-d'), 'fr');
    $raw = $get('/api/v1/widgets/quiz-du-jour')->body;
    $d = json_decode($raw, true)['data'];
    $ok($q !== null && $d['question'] === $q['q'] && $d['choices'] === $q['a'] && !array_intersect_key($d, ['c' => 1, 'reponse' => 1, 'answer' => 1, 'explication' => 1, 'fact' => 1]), 'quiz : question et choix, sans la bonne réponse');
    $ok($q['fact'] === '' || (!str_contains($raw, json_encode($q['fact'])) && !str_contains($frame('quiz-du-jour'), e($q['fact']))), 'quiz : explication absente de l’API et de l’iframe');
    $ok(\App\Widgets\Items\QuizDuJour::question(date('Y-m-d'), 'en')['c'] === $q['c'], 'quiz : même question et même ordre en anglais');
    $post = fn (array $body, array $h = []) => $get('/api/v1/widgets/quiz-du-jour/verifier', [], $h + ['Content-Type' => 'application/json', '_body' => json_encode($body)], 'POST');
    $good = $json($post(['date' => date('Y-m-d'), 'choix' => $q['c']]));
    $bad = $json($post(['date' => date('Y-m-d'), 'choix' => ($q['c'] + 1) % count($q['a'])]));
    $ok($good['correct'] === true && $good['bonne'] === $q['c'] && $good['explication'] === $q['fact'], 'quiz : bonne réponse reconnue, avec l’explication');
    $ok($bad['correct'] === false && $bad['bonne'] === $q['c'] && $bad['reponse'] === $q['a'][$q['c']], 'quiz : mauvaise réponse, la bonne est donnée');
    $ok($post(['date' => '2020-01-01', 'choix' => 0])->status === 400 && $post(['date' => date('Y-m-d'), 'choix' => 99])->status === 400, 'quiz : date passée ou choix inconnu refusés');
    $ok(str_contains($get('/w/quiz-du-jour')->headers['Content-Security-Policy'] ?? '', "connect-src 'self'") && str_contains($frame('quiz-du-jour'), 'data-verify='), 'quiz : vérification depuis l’iframe permise (connect-src) ');
    Store::saveWidget('quiz-du-jour', 'reserve', false);
    $ok($post(['date' => date('Y-m-d'), 'choix' => 0])->status === 401 && $post(['date' => date('Y-m-d'), 'choix' => 0, 'key' => 'mk_' . str_repeat('0', 32)])->status === 401, 'quiz réservé : vérification refusée sans clé');
    Store::saveWidget('quiz-du-jour', 'libre', false);

    // Défi du jour : le défi existant, sans aucun pseudo.
    $d = $data('defi-du-jour');
    $ok($d['questions'] === \App\Services\DailyQuiz::COUNT && $d['players'] === \App\Services\DailyQuiz::players(date('Y-m-d')) && !isset($d['pseudo']) && !str_contains(json_encode($d), 'pseudo'), 'défi du jour : 10 questions, joueurs du jour, aucun pseudo');
    $ok(str_contains($frame('defi-du-jour'), '%2Finteractif%2Fdefi%2F'), 'défi du jour : lien vers le défi du musée');

    // Dernier match fiché : celui du bandeau du site.
    $last = \App\Front\Site::lastMatchCreated();
    $d = $data('dernier-match');
    $ok($last && str_contains((string) $d['href'], rawurlencode((string) $last['path'])) && $d['date'] === $last['date'] && mb_strlen($d['text']) <= 321, 'dernier match : le dernier fiché, avec le début du récit');

    // Saison : choisie, ou en cours / dernière par défaut.
    $seasons = array_keys(array_filter(\App\Data\Derived::part('seasons'), fn ($x) => !empty($x['matches'])));
    $pick = $seasons[intdiv(count($seasons), 2)] ?? null;
    if ($pick) {
        $d = $data('saison', ['saison' => $pick]);
        $ok($d['season'] === $pick && $d['wins'] + $d['draws'] + $d['defeats'] <= $d['matches'] && count($d['scorers']) <= 5, "saison choisie ($pick) : bilan et buteurs");
    }
    $ok($data('saison', ['saison' => 'n-importe'])['season'] === \App\Widgets\Items\Saison::defaultSeason(), 'saison : valeur invalide → saison par défaut');
    $titled = null;
    foreach (\App\Front\Palmares::data()['groups'] as $g) {
        foreach ($g['titles'] as $t) {
            if (isset(\App\Data\Derived::part('seasons')[$t['season']])) {
                $titled = [$t['season'], $g['comp']];
            }
        }
    }
    if ($titled) {
        $ok(in_array($titled[1], array_column($data('saison', ['saison' => $titled[0]])['titles'], 'competition'), true), 'saison : titres de la saison (' . $titled[0] . ', ' . $titled[1] . ')');
    }

    // Palmarès : mêmes données que /palmares/, en liste ou en bandeau.
    $pal = \App\Front\Palmares::data();
    $d = $data('palmares');
    $ok($d['total'] === $pal['count']['titre'] && array_sum(array_column($d['groups'], 'count')) === $pal['count']['titre'], 'palmarès : autant de titres que /palmares/');
    $ok(str_contains($frame('palmares', ['affichage' => 'bandeau']), 'w-cups') && str_contains($frame('palmares'), 'w-palm'), 'palmarès : bandeau ou liste');

    // Frise : les dates de la frise publique, qui défilent.
    $events = array_filter((array) \App\Data\Collections::get('frise', \App\Data\Seeds::frise()), fn ($e) => is_array($e) && (int) ($e['year'] ?? 0) > 0 && trim((string) ($e['title'] ?? '')) !== '');
    $d = $data('frise');
    $years = array_map(fn ($e) => (int) $e['year'], $events);
    sort($years);
    $ok(array_column($d['events'], 'year') === $years, 'frise : toutes les dates de la frise publique, dans l’ordre');
    $ok(preg_match_all('/<li[ >]/', $frame('frise')) === 2 * count($events) && str_contains($frame('frise'), 'prefers-reduced-motion'), 'frise : défilement continu (liste doublée), arrêt si moins d’animations');

    // Bandeau : mêmes messages que le site.
    $ticker = \App\Front\Site::ticker();
    $d = $data('bandeau');
    $ok(array_column($d['messages'], 'text') === array_column($ticker, 'v'), 'bandeau : les messages du bandeau du site');
    $bf = $frame('bandeau');
    $ok(str_contains($bf, 'w-tk__track') && str_contains($bf, 'Musée indépendant, non officiel') && str_contains($bf, 'class="w-url"') && \App\Widgets\Registry::get('bandeau')->height() <= 70, 'bandeau : défilement CSS, hauteur fixe, mention et lien');

    // Carte de légende : réglage « source » (proposition à valider).
    $legends = array_filter(\App\Data\Index::published('personne'), fn ($s) => !empty($s['p']['legend']) && in_array('joueur', (array) ($s['p']['roles'] ?? []), true));
    $ok(Store::widget('carte-legende')['settings'] === ['source' => 'capes'], 'carte de légende : repli « plus capés » activé par défaut');
    $pool = array_map(fn ($s) => (int) $s['id'], CarteLegende::pool());
 $ok($pool !== [] && !array_diff($pool, array_map(fn ($s) => (int) $s['id'], $legends ?: CarteLegende::mostCapped(24))), 'carte de légende : légendes cochées, sinon les plus capés (' . count($legends) . ' légende(s) cochée(s))');
    Store::saveWidget('carte-legende', 'libre', false, ['source' => 'legendes']);
    $ok(Store::widget('carte-legende')['settings']['source'] === 'legendes' && ($legends ? $data('carte-legende') !== null : CarteLegende::pool() === [] && $data('carte-legende') === null), 'carte de légende : « seulement les légendes cochées » (rien à montrer sans légende)');
    Store::saveWidget('carte-legende', 'libre', false, ['source' => 'n-importe']);
    $ok(Store::widget('carte-legende')['settings']['source'] === 'capes', 'carte de légende : réglage inconnu → valeur par défaut');
    Store::saveWidget('carte-legende', 'libre', false);
    $ok(Store::widget('carte-legende')['settings']['source'] === 'capes', 'réglage gardé quand seul l’accès change');

    // Script d'intégration.
    $js = $get('/embed.js');
    $src = (string) file_get_contents(__DIR__ . '/../public/assets/js/embed.js');
    $ok($js->status === 200 && str_contains($js->headers['Content-Type'] ?? '', 'javascript') && $js->body === $src && ($js->headers['Access-Control-Allow-Origin'] ?? '') === '*' && !isset($js->headers['Set-Cookie']), '/embed.js : servi par le musée, sans cookie');
    $ok($get('/embed.js', [], ['If-None-Match' => $js->headers['ETag']])->status === 304, '/embed.js : 304 sur If-None-Match');
    $min = preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m', '#\s+#'], ['', '', ' '], $src);
    $ok(strlen($min) < 5120, '/embed.js : moins de 5 Ko sans commentaires ni espaces (' . strlen($min) . ' octets)');
    $ok(str_contains($src, 'sandbox') && str_contains($src, 'e.origin !== origin') && str_contains($src, 'IntersectionObserver') && !preg_match('/document\.cookie|localStorage/', $src), '/embed.js : iframe isolée, origine vérifiée, chargement paresseux, aucun cookie');
    $demo = $get('/widgets/')->body;
    $ok(str_contains($demo, 'data-musee-widget=&quot;records&quot;') && str_contains($demo, '/embed.js&quot; async'), 'page /widgets/ : code script et code iframe');

    // ------------------------------------------------------------------ vague 3 : widgets
    $ip3 = '203.0.113.' . random_int(1, 250);
    $g3 = fn (string $path, array $q = [], array $h = [], string $m = 'GET') => $get($path, $q, $h + ['_ip' => $ip3], $m);
    $d3 = fn (string $k, array $q = []) => $json($g3('/api/v1/widgets/' . $k, $q))['data'] ?? null;
    \App\Services\I18n::set('fr');

    // Histoire du duel : mêmes chiffres que la page face-à-face.
    $clubs = \App\Data\Derived::part('clubs');
    uasort($clubs, fn ($a, $b) => $b['count'] <=> $a['count']);
    $self = \App\Data\Names::clubKey(\App\Core\Club::team());
    $opp = (string) array_key_first(array_diff_key($clubs, [$self => 1]));
    $ref = \App\Front\Explore::bilanData($clubs[$opp]['matches'])['t'];
    $d = $d3('histoire-du-duel', ['adversaire' => $opp]);
    $ok($d !== null && $d['club'] === $opp && $d['matches'] === $ref['count'] && [$d['wins'], $d['draws'], $d['defeats'], $d['goals_for'], $d['goals_against']] === [$ref['V'], $ref['N'], $ref['D'], $ref['gf'], $ref['ga']], "histoire du duel ($opp) : bilan identique au face-à-face");
    $ok(str_contains((string) $d['href'], rawurlencode('/face-a-face/' . $opp . '/')) && $d['last']['date'] !== '' && ($d['biggest_win'] === null || $d['biggest_win']['result'] === 'V'), 'histoire du duel : dernier match, plus large victoire, lien vers le face-à-face');
    $ok($d['top_scorer'] === null || ($d['top_scorer']['goals'] > 0 && str_contains((string) $d['top_scorer']['href'], '%2F')), 'histoire du duel : meilleur buteur du duel (fiche publiée)' . ($d['top_scorer'] ? ' : ' . $d['top_scorer']['name'] . ', ' . $d['top_scorer']['goals'] . ' buts' : ''));
    $last = \App\Front\Site::lastMatchCreated();
    $def = $d3('histoire-du-duel');
    $ok($def !== null && ($last && isset($clubs[$last['club'] ?? '']) ? $def['club'] === $last['club'] : true), 'histoire du duel : par défaut, l’adversaire du dernier match fiché');
    // Adversaire jamais affronté : version « Première rencontre », jamais le bilan du dernier adversaire.
    $neuf = $d3('histoire-du-duel', ['adversaire' => 'Club Inexistant XYZ']);
    $ok($neuf !== null && $neuf['first_meeting'] === true && $neuf['club'] === null && $neuf['opponent'] === 'Club Inexistant XYZ' && ($neuf['club'] ?? '') !== $def['club'] && !isset($neuf['last']), 'histoire du duel : adversaire jamais affronté → « Première rencontre », jamais l’adversaire du dernier match');
    $ok($neuf['rank'] === count(array_filter(\App\Data\Derived::part('clubs'), fn ($x) => $x['count'] > 0)) + (isset($clubs[$self]) ? 0 : 1) && $neuf['firsts']['total'] > 0 && ($neuf['latest_first'] === null || $neuf['latest_first']['date'] !== ''), 'première rencontre : rang du nouvel adversaire, bilan des premières rencontres (' . $neuf['firsts']['total'] . '), dernière première rencontre');
    $slug = $d3('histoire-du-duel', ['adversaire' => 'club-inexistant-xyz']);
    $ok($slug['first_meeting'] === true && $slug['opponent'] === 'Club Inexistant Xyz', 'histoire du duel : adresse inconnue (« club-inexistant-xyz ») → première rencontre, nom lisible');
    $ok($d3('histoire-du-duel', ['adversaire' => $self]) === null || $d3('histoire-du-duel', ['adversaire' => $self])['club'] !== $self, 'histoire du duel : le club lui-même n’est jamais son propre adversaire');
    $ok($d3('histoire-du-duel', ['adversaire' => '"><script>alert(1)</script>'])['club'] === $def['club'], 'histoire du duel : saisie invalide (balises) → adversaire automatique');
    $fw = $g3('/w/histoire-du-duel', ['adversaire' => 'Saint-Ouen & l\'Étoile'])->body;
    $ok(str_contains($fw, 'Première rencontre') && str_contains($fw, 'Saint-Ouen &amp; l&#039;Étoile') && !str_contains($fw, 'Saint-Ouen & l\'Étoile'), 'histoire du duel : nom libre échappé dans le widget');
    $ok(\App\Widgets\Items\HistoireDuDuel::cleanName(str_repeat('a', 41)) === null && \App\Widgets\Items\HistoireDuDuel::cleanName('  Paris   FC ') === 'Paris FC' && \App\Widgets\Items\HistoireDuDuel::cleanName('12') === null, 'nom libre : longueur bornée, espaces resserrés, au moins une lettre');
    // Doublons regroupés (alias du référentiel) : l'ancienne clé mène au club regroupé.
    $alias = \App\Widgets\Items\HistoireDuDuel::known('angers-d2');
    $ok(\App\Core\Club::id() !== 'sochaux' || ($alias === 'angers' && !isset(\App\Data\Derived::part('clubs')['angers-d2']) && \App\Widgets\Items\HistoireDuDuel::known('Evian TG') === 'evian' && \App\Widgets\Items\HistoireDuDuel::known('Quevilly RM') === 'quevilly'), 'doublons d’adversaires : « Angers- D2 », « Evian TG », « Quevilly RM » regroupés par alias');
    $ok(\App\Core\Club::id() !== 'sochaux' || (($r301 = $g3('/face-a-face/angers-d2/'))->status === 301 && str_contains((string) ($r301->headers['Location'] ?? ''), '/face-a-face/angers/')), 'doublons d’adversaires : ancienne adresse du face-à-face redirigée (301)' . ($r301->status !== 301 ? ' — ' . $r301->status . ' ' . json_encode($r301->headers['Location'] ?? null) : ''));
    $tot = 0;
    foreach (\App\Data\Derived::part('clubs') as $x) {
        $tot += $x['count'];
    }
    $ok($tot === count(array_filter(\App\Data\Derived::part('matches'), fn ($m) => ($m['club'] ?? '') !== '' && ($m['club'] ?? '') !== $self)) || $tot > 0, 'face-à-face : compteurs cohérents après regroupement (' . $tot . ' matchs)');

    // Carte des stades : stades géolocalisés, carte dessinée par le musée (aucun service extérieur).
    $d = $d3('carte-stades');
    $ok($d !== null && count($d['points']) + $d['outside'] === $d['stadiums'] && count($d['top']) <= 5 && $d['map']['land'] !== [], 'carte des stades : ' . $d['stadiums'] . ' stades, fond de carte (' . count($d['map']['land']) . ' contours)');
    $ok(array_column($d['top'], 'matches') === array_values(array_reverse(array_values(array_unique(array_merge(array_column($d['top'], 'matches')))))) || array_column($d['top'], 'matches') == (function ($a) { rsort($a); return $a; })(array_column($d['top'], 'matches')), 'carte des stades : les plus fréquentés d’abord');
    $f = $g3('/w/carte-stades')->body;
    $ok(str_contains($f, '<svg class="w-map"') && !preg_match('#(src|href)="https?://(?!' . preg_quote(\App\Widgets\Context::host(), '#') . ')#', $f), 'carte des stades : carte SVG, rien chargé hors du musée');
    $ok($d3('carte-stades', ['zone' => 'europe'])['zone'] === 'europe' && $d3('carte-stades', ['zone' => 'lune'])['zone'] === 'france', 'carte des stades : zone France ou Europe');


    // Compte à rebours : date choisie, sinon centenaire (s'il est mis en avant), sinon fondation.
    $future = date('Y-m-d', strtotime('+40 days'));
    $d = $d3('compte-a-rebours', ['date' => $future, 'titre' => 'Jubilé']);
    $ok($d['date'] === $future && $d['title'] === 'Jubilé' && $d['days'] === 40, 'compte à rebours : date et titre choisis (J-40)');
    $d = $d3('compte-a-rebours', ['date' => '2001-01-01']);
    $ok($d['date'] > date('Y-m-d') && (\App\Front\Site::centenaryCountdown() ? $d['date'] === \App\Front\Site::centenaryDate() : str_ends_with($d['date'], substr((string) \App\Core\Club::s('founded_date'), 4))), 'compte à rebours : date passée → ' . (\App\Front\Site::centenaryCountdown() ? 'centenaire' : 'anniversaire de la fondation'));
    $ok(str_contains($g3('/w/compte-a-rebours')->body, 'data-countdown='), 'compte à rebours : décompte animé dans le widget');

    // Soutenir : seulement les liens ouverts.
    $open = \App\Widgets\Items\Soutenir::open();
    $d = $d3('soutenir');
    $ok(($open === [] && $d === null) || array_column($d['links'], 'kind') === array_keys($open), 'soutenir : liens ouverts seulement (' . implode(', ', array_keys($open)) . ')');
    $ok(!isset($open['don']) || \App\Front\Donations::methods() !== [], 'soutenir : « don » seulement avec les dons ouverts et un moyen de paiement');

    // Boutique : rien si fermée ou sans objet en vente ; un objet choisi, sinon trois par jour.
    $B = \App\Widgets\Items\Boutique::class;
    $B::$open = false;
    \App\Widgets\Cache::clear();
    $ok($d3('boutique') === null && str_contains($g3('/w/boutique')->body, 'Rien à montrer'), 'boutique : rien à montrer quand elle est fermée');
    $B::$open = true;
    \App\Widgets\Cache::clear();
    $sell = $B::products();
    $d = $d3('boutique');
    if (!$sell) {
        $ok($d === null, 'boutique : rien à montrer sans objet en vente');
    } else {
        $ids = array_column($sell, 'id');
        $ok(count($d['items']) === min($B::CARDS, count($sell)) && !array_diff(array_column($d['items'], 'id'), $ids) && count(array_unique(array_column($d['items'], 'id'))) === count($d['items']), 'boutique : objets en vente qui défilent sur la carte, sans doublon');
        $ok($d3('boutique')['items'] === $d['items'], 'boutique : même sélection dans la journée');
        $it = $d['items'][0];
        $ok(str_contains($it['href'], '/w/clic/boutique/') && str_contains(rawurldecode($it['href']), '/boutique/' . $it['id'] . '/'), 'boutique : lien compté vers la fiche du produit');
        $ok(str_starts_with($it['image'], 'http') || str_starts_with($it['image'], 'data:image/svg'), 'boutique : aperçu du produit en adresse complète');
        $m = \App\Shop\Catalog::find($it['id']);
        $ok($it['price'] === $m['sale']['price'] && str_contains($it['price_label'], \App\Shop\Orders::money($m['sale']['price'])) && ($m['sale']['extra'] === [] || str_starts_with($it['price_label'], 'à partir de')), 'boutique : prix « à partir de »');
        $last = end($sell);
        $one = $d3('boutique', ['produit' => $last['id'], 'affichage' => 'grille']);
        $ok(count($one['items']) === 1 && $one['items'][0]['id'] === $last['id'] && $one['layout'] === 'grille', 'boutique : un seul objet choisi, en grille');
        $ok(count($d3('boutique', ['produit' => 'inconnu-42'])['items']) === min($B::CARDS, count($sell)) && $d3('boutique', ['affichage' => 'mur'])['layout'] === 'carte', 'boutique : objet inconnu → sélection du jour ; affichage inconnu → carte');
        $ok(count($d3('boutique', ['affichage' => 'grille'])['items']) === min(3, count($sell)), 'boutique : grille de trois objets');
        $en = $json($g3('/api/v1/widgets/boutique', ['lang' => 'en']))['data'];
        $ok($m['sale']['extra'] === [] || str_starts_with($en['items'][0]['price_label'], 'from'), 'boutique : libellés en anglais');
        foreach (['clair', 'sombre'] as $theme) {
            $f = $g3('/w/boutique', ['theme' => $theme])->body;
            $ok(str_contains($f, 'class="w-flip"') && str_contains($f, 'w-flip__card is-on') && (count($sell) < 2 || str_contains($f, 'data-flip')) && str_contains($f, 'non officiel') && !str_contains($f, 'src="/') && !preg_match('#href="/(?!/)#', $f), "iframe boutique ($theme) : carte qui se retourne, mention, adresses complètes");
        }
        $sl = \App\Widgets\Screen\Slides::one(['type' => 'boutique', 'opt' => []], new \App\Widgets\Context('boutique'));
        $ok($sl !== null && $sl['layout'] === 'support' && count($sl['items']) === count($d['items']), 'boutique : slide du mode écran (QR codes)');
    }
    $B::$open = null;
    \App\Widgets\Cache::clear();

    // Faire un don : rien si les dons sont fermés ; jauge, bouton compté, QR code selon les options.
    $D = \App\Widgets\Items\Don::class;
    $ok(($D::isOpen() === isset(\App\Widgets\Items\Soutenir::open()['don'])), 'don : même règle d’ouverture que Soutenir');
    $D::$open = false;
    \App\Widgets\Cache::clear();
    $ok($d3('don') === null && str_contains($g3('/w/don')->body, 'Rien à montrer'), 'don : rien à montrer quand les dons sont fermés');
    $ok(\App\Widgets\Screen\Slides::one(['type' => 'don', 'opt' => []], new \App\Widgets\Context('playlist')) === null, 'don : slide écran sautée quand les dons sont fermés');
    $D::$open = true;
    \App\Widgets\Cache::clear();
    $d = $d3('don');
    $gg = \App\Front\Donations::gauge();
    $ok($d !== null && str_contains($d['href'], '/w/clic/don/') && str_contains(rawurldecode($d['href']), 'to=/faire-un-don/'), 'don : bouton « Faire un don » en lien compté vers /faire-un-don/');
    $ok(is_string($d['qr']) && str_starts_with($d['qr'], \App\Widgets\Context::base() . '/faire-un-don/?') && str_contains($d['qr'], 'utm_source=widget') && str_contains($d['qr'], 'utm_medium=don'), 'don : QR code vers l’adresse complète du don, avec la provenance');
    $ok($gg['goal'] > 0 ? ($d['gauge']['raised'] === $gg['raised'] && $d['gauge']['goal'] === $gg['goal'] && (float) $d['gauge']['pct'] === (float) $gg['pct']) : $d['gauge'] === null, 'don : jauge identique à la page « Faire un don » (absente sans objectif)');
    $ok($d3('don', ['jauge' => 'non'])['gauge'] === null && $d3('don', ['qr' => 'non'])['qr'] === null && $d3('don', ['qr' => 'peut-etre'])['qr'] !== null, 'don : options jauge et QR (valeur inconnue → oui)');
    $ok($d['amounts'] === [] && ($D::tiers() === [] || count($d3('don', ['montants' => 'oui'])['amounts']) === min(4, count($D::tiers()))), 'don : montants suggérés seulement sur demande');
    $ok(array_key_exists('montants', Registry::get('don')->options()) === ($D::tiers() !== []), 'don : option « montants » seulement si la page en propose');
    $ok(!empty($json($g3('/api/v1/widgets'))['widgets'][array_search('don', array_column($json($g3('/api/v1/widgets'))['widgets'], 'key'))]['customizable']), 'don : pastille « Personnalisable » (options)');
    foreach (['clair', 'sombre'] as $theme) {
        $f = $g3('/w/don', ['theme' => $theme])->body;
        $ok(str_contains($f, 'data-theme="' . $theme . '"') && str_contains($f, '<figure class="w-qr"><svg') && str_contains($f, 'Scannez pour donner') && str_contains($f, 'non officiel') && str_contains($f, 'class="w-url"') && !preg_match('#href="/(?!/)#', $f), "iframe don ($theme) : QR code en SVG, légende, mention et lien du musée");
    }
    $ok(!str_contains($g3('/w/don', ['qr' => 'non'])->body, '<svg') && str_contains($g3('/w/don', ['qr' => 'non'])->body, 'w-btn'), 'iframe don : sans QR code (qr=non), bouton toujours là');
    $ok(str_contains($g3('/w/don', ['lang' => 'en'])->body, 'Scan to donate') && str_contains($d3('don', ['lang' => 'en'])['qr'], '/en/faire-un-don/'), 'don : en anglais (« Donate », « Scan to donate »)');
    \App\Services\I18n::set('fr');
    $sl = \App\Widgets\Screen\Slides::one(['type' => 'don', 'opt' => []], new \App\Widgets\Context('playlist', 'fr', 'sombre', 'ecran'));
    $ok($sl !== null && $sl['layout'] === 'don' && str_contains($sl['qr'], '/w/clic/playlist/') && ($gg['goal'] > 0) === ($sl['gauge'] !== null), 'don : slide du mode écran (grand QR code, jauge)');
    $sh = \App\Widgets\Screen\Page::slide($sl + ['type' => 'don', 'duration' => 12], new \App\Widgets\Context('playlist', 'fr', 'sombre', 'ecran'));
    $ok(str_contains($sh, 'ec-l--don') && str_contains($sh, '<figure class="ec-qr"><svg') && str_contains($sh, 'Scannez pour donner'), 'don : slide HTML avec QR code et légende');
    $okp = true;
    foreach ([false, true] as $vert) {
        $sz = @getimagesizefromstring(\App\Widgets\Screen\Image::png($sl + ['type' => 'don', 'duration' => 12, 'n' => 1], new \App\Widgets\Context('playlist', 'fr', 'sombre', 'ecran'), $vert));
        $okp = $okp && $sz && [$sz[0], $sz[1]] === ($vert ? [1080, 1920] : [1920, 1080]);
    }
    $ok($okp, 'don : slide en image PNG (paysage et vertical)');
    $ok(in_array('don', array_column(\App\Widgets\Screen\Playlists::presets()['buvette']['slides'] ?? [], 'type'), true) && !in_array('don', array_column(\App\Widgets\Screen\Playlists::presets()['mi-temps']['slides'] ?? [], 'type'), true), 'don : dans la playlist « Buvette » (pas à la mi-temps, déjà « Soutenir »)');
    $D::$open = null;
    \App\Widgets\Cache::clear();

    // Recherche : champ qui ouvre la recherche du musée (lien compté), formulaire permis à ce widget seul.
    $r = $g3('/w/recherche');
    $ok(str_contains($r->body, 'data-search="') && str_contains($r->body, '%2Frecherche%2F%3Fq%3D__Q__') && str_contains($r->body, 'target="_blank"'), 'recherche : formulaire vers la recherche du musée, nouvel onglet');
    $ok(str_contains($r->headers['Content-Security-Policy'] ?? '', "form-action 'self'") && str_contains($g3('/w/records')->headers['Content-Security-Policy'] ?? '', "form-action 'none'"), 'recherche : envoi de formulaire permis à ce seul widget');
    $loc = $g3('/w/clic/recherche/', ['to' => '/recherche/?q=Bonal%20' . rawurlencode('épopée')])->headers['Location'] ?? '';
    $ok(str_starts_with($loc, '/recherche/?q=Bonal%20') && str_contains($loc, 'utm_medium=recherche'), 'recherche : passage par le lien compté puis la recherche du musée');

    // Rétro-Direct : direct du jour, prochain, sinon classique ; jamais le score final.
    \App\Services\RetroDirect::$entries = [];
    \App\Widgets\Cache::clear();
    $d = $d3('retro-direct');
    $ok($d === null || $d['state'] === 'classique', 'rétro-direct : sans programme, un classique à revivre');
    $cl = \App\Services\RetroDirect::classics(1)[0] ?? null;
    if ($cl) {
        \App\Services\RetroDirect::$entries = [['id' => $cl['id'], 'date' => date('Y-m-d'), 'time' => '23:59', 'intro' => 'Ce soir, on rejoue.']];
        \App\Widgets\Cache::clear();
        $d = $d3('retro-direct');
        $ok($d['state'] === 'aujourdhui' && $d['time'] === '23:59' && $d['intro'] === 'Ce soir, on rejoue.' && !array_key_exists('score', $d), 'rétro-direct : le direct du jour, sans le score');
        \App\Services\RetroDirect::$entries = [['id' => $cl['id'], 'date' => date('Y-m-d', strtotime('+3 days')), 'time' => '20:00', 'intro' => '']];
        \App\Widgets\Cache::clear();
        $ok($d3('retro-direct')['state'] === 'avenir', 'rétro-direct : sinon le prochain au programme');
    }
    \App\Services\RetroDirect::$entries = null;
    \App\Widgets\Cache::clear();

    // ------------------------------------------------------------------ vague 3 : mode écran
    $P = \App\Widgets\Screen\Playlists::class;
    $presets = $P::presets();
    $ok(array_keys($presets) === ['avant-match', 'mi-temps', 'buvette'], 'écrans : 3 playlists prêtes à l’emploi');
    $ok($P::duration($presets['mi-temps']) >= 120 && $P::duration($presets['mi-temps']) <= 180, 'écrans : mi-temps entre 2 et 3 minutes (' . $P::duration($presets['mi-temps']) . ' s)');
    $types = array_column($presets['avant-match']['slides'], 'type');
    $ok(!array_diff(['histoire-du-duel', 'ce-jour-la', 'carte-legende', 'chiffre-du-jour', 'le-saviez-vous', 'quiz'], $types), 'écrans : l’avant-match a duel, ce jour-là, légende, chiffre, saviez-vous, quiz');
    $ok(!array_diff(['photo', 'frise', 'records'], array_column($presets['buvette']['slides'], 'type')), 'écrans : la buvette a photos, frise, records');

    $scr = Store::savePartner(null, ['name' => 'Régie essai', 'domains' => 'stade-essai.fr', 'status' => 'actif', 'offer' => 'gratuite']);
    $scrKey = Store::keyOf($scr);
    $r = $g3('/ecran/avant-match');
    $ok($r->status === 401 && str_contains($r->body, 'clé requise') && str_contains($r->body, 'non officiel') && str_contains($r->body, 'ec-foot'), 'écran réservé sans clé : 401, message plein écran aux couleurs du musée');
    $r = $g3('/ecran/avant-match', ['key' => $scrKey, 'adversaire' => $opp]);
    $html = $r->body;
    $c3 = new \App\Widgets\Context('playlist', 'fr', 'sombre', 'ecran', $scr['id']);
    $n = count(\App\Widgets\Screen\Slides::build($presets['avant-match'] + $P::defaults(), $c3, $opp));
    $ok($r->status === 200 && substr_count($html, '<section class="ec-slide') === $n && $n >= 10, "écran avant-match avec clé (limitée à un domaine, sans page d'origine) : 200, $n slides");
    $ok(str_contains($html, 'class="ec-foot"><img src="' . \App\Widgets\Context::abs((string) \App\Core\Club::s('logo_400'))) && str_contains($html, 'Musée indépendant, non officiel') && str_contains($html, '<span class="ec-url">' . \App\Widgets\Context::host()), 'écran : logo, mention « non officiel » et adresse du musée toujours en bas');
    $ok(str_contains($html, \App\Core\Club::color('navy')) && str_contains($html, 'cursor:none') && str_contains($html, 'overflow:hidden') && !preg_match('/<(audio|video)\b/', $html), 'écran : charte du club, ni souris ni défilement, aucun son');
    $ok(str_contains($html, 'data-reload="30"') && str_contains($html, '/ecran/sw.js') && str_contains($html, '/assets/js/ecran.js'), 'écran : rechargement automatique et service worker (hors connexion)');
    $ok(str_contains($html, 'ec-l--duel') && str_contains($html, '× ' . e(\App\Front\Fiche::clubName($opp))), 'écran : histoire du duel contre l’adversaire du jour');
    $ok(!str_contains($html, $scrKey) && !str_contains($html, 'mk_') && !isset($r->headers['Set-Cookie']), 'écran : jamais la clé dans la page, aucun cookie');
    $ok(!preg_match('#(src|href)="https?://(?!' . preg_quote(\App\Widgets\Context::host(), '#') . ')#', $html) && str_contains($r->headers['Content-Security-Policy'] ?? '', "default-src 'none'") && str_contains($r->headers['Content-Security-Policy'] ?? '', "worker-src 'self'"), 'écran : rien chargé hors du musée, CSP stricte');
    $ok(!preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/', $html), 'écran : aucune adresse e-mail');
    $ok(str_contains($g3('/ecran/mi-temps', ['key' => $scrKey, 'format' => 'vertical'])->body, 'class="ec ec--vertical"') && str_contains($g3('/ecran/mi-temps', ['key' => $scrKey, 'format' => 'vertical'])->body, 'data-w="1080" data-h="1920"'), 'écran : format vertical 9:16');
    $en = $g3('/ecran/mi-temps', ['key' => $scrKey, 'lang' => 'en'])->body;
    $ok(str_contains($en, 'lang="en"') && str_contains($en, 'Independent, unofficial museum'), 'écran : en anglais');
    \App\Services\I18n::set('fr');
    $ok(str_contains($g3('/ecran/buvette', ['key' => $scrKey, 'horloge' => '1'])->body, 'data-clock') && !str_contains($g3('/ecran/buvette', ['key' => $scrKey])->body, 'data-clock'), 'écran : horloge facultative');
    $ok($g3('/ecran/inconnue', ['key' => $scrKey])->status === 404, 'écran : playlist inconnue → 404');
    $sw = $g3('/ecran/sw.js');
    $ok($sw->status === 200 && str_contains($sw->headers['Content-Type'] ?? '', 'javascript') && str_contains($sw->body, "startsWith('/ecran/')"), 'écran : service worker /ecran/sw.js');
    Store::savePartner($scr['id'], ['name' => 'Régie essai', 'domains' => 'stade-essai.fr', 'status' => 'suspendu', 'offer' => 'gratuite']);
    $ok($g3('/ecran/avant-match', ['key' => $scrKey])->status === 403, 'écran : partenaire suspendu → 403');
    Store::savePartner($scr['id'], ['name' => 'Régie essai', 'domains' => 'stade-essai.fr', 'status' => 'actif', 'offer' => 'gratuite']);
    Store::saveWidget('playlist', 'payant', false);
    $ok($g3('/ecran/avant-match', ['key' => $scrKey])->status === 402, 'écran payant, offre gratuite → 402');
    Store::saveWidget('playlist', 'libre', false);
    $ok($g3('/ecran/avant-match')->status === 200, 'écran en accès libre : sans clé');
    Store::saveWidget('playlist', 'desactive', false);
    $ok($g3('/ecran/avant-match', ['key' => $scrKey])->status === 404, 'écran désactivé → 404');
    Store::saveWidget('playlist', 'reserve', false);
    $before = Store::stats(1)['widgets']['playlist']['vues'] ?? 0;
    $g3('/ecran/mi-temps', ['key' => $scrKey]);
    $ok((Store::stats(1)['widgets']['playlist']['vues'] ?? 0) === $before + 1, 'écran : affichage compté (widget « playlist », par partenaire)');

    // Quiz écran : question, compte à rebours, réponse et explication, QR code ; jamais la question du quiz du jour.
    $q = \App\Widgets\Screen\Slides::quiz(date('Y-m-d'), 'fr', 0, 'mi-temps');
    $mt = $g3('/ecran/mi-temps', ['key' => $scrKey])->body;
    $ok($q !== null && str_contains($mt, e($q['q'])) && str_contains($mt, 'class="is-good"><em>' . chr(65 + $q['c']) . '</em>') && str_contains($mt, 'data-answer=') && str_contains($mt, 'data-timer'), 'quiz écran : question, compte à rebours, bonne réponse révélée ensuite');
    $daily = \App\Widgets\Items\QuizDuJour::question(date('Y-m-d'), 'fr');
    $ok($daily === null || $q['q'] !== $daily['q'], 'quiz écran : jamais la question du quiz du jour (sa réponse reste secrète)');
    $ok(substr_count($mt, '<svg xmlns="http://www.w3.org/2000/svg"') >= 1 && str_contains($mt, 'Plus de questions'), 'quiz écran : QR code vers le quiz du musée');
    $ok(\App\Widgets\Screen\Slides::quiz(date('Y-m-d'), 'fr', 1, 'avant-match')['q'] !== \App\Widgets\Screen\Slides::quiz(date('Y-m-d'), 'fr', 0, 'avant-match')['q'] || count((array) \App\Data\Collections::get('quiz', \App\Data\Seeds::quiz())) < 3, 'quiz écran : deux quiz d’une playlist, deux questions');

    // Playlists personnalisées
    $pl = $P::save(null, ['name' => 'Écran géant', 'format' => 'vertical', 'lang' => 'en', 'adversaire' => $opp, 'clock' => true, 'reload' => 2, 'slides' => [
        ['type' => 'records', 'duration' => 3, 'opt' => ['cat' => 'matchs', 'adversaire' => 'x']], ['type' => 'inconnu', 'duration' => 10], ['type' => 'histoire-du-duel', 'duration' => 999, 'opt' => ['adversaire' => 'NANTES!!']], ['type' => 'quiz', 'duration' => 20]]]);
    $ok($pl['id'] === 'ecran-geant' && $pl['format'] === 'vertical' && $pl['reload'] === 5 && count($pl['slides']) === 3, 'playlist personnalisée : adresse, format, rechargement borné, type inconnu écarté');
    $ok($pl['slides'][0] === ['type' => 'records', 'duration' => 3, 'opt' => ['cat' => 'matchs']] && $pl['slides'][1]['duration'] === $P::MAX && $pl['slides'][1]['opt'] === [], 'playlist personnalisée : durées bornées, options permises et bien formées seulement');
    $ok($P::save(null, ['name' => 'Avant-match', 'slides' => [['type' => 'quiz']]])['id'] === 'avant-match-2' && $P::get('avant-match')['preset'] === true, 'playlist personnalisée : ne remplace jamais une playlist prête à l’emploi');
    $err = [];
    foreach ([['name' => '', 'slides' => [['type' => 'quiz']]], ['name' => 'Vide', 'slides' => []]] as $bad) {
        try {
            $P::save(null, $bad);
        } catch (\InvalidArgumentException $e) {
            $err[] = $e->getMessage();
        }
    }
    $ok(count($err) === 2, 'playlist personnalisée : nom et au moins une slide obligatoires');
    $r = $g3('/ecran/ecran-geant', ['key' => $scrKey]);
    $ok($r->status === 200 && str_contains($r->body, 'ec--vertical') && str_contains($r->body, 'lang="en"') && str_contains($r->body, 'data-clock') && str_contains($r->body, 'data-reload="5"'), 'playlist personnalisée : servie avec ses réglages');
    $P::delete('ecran-geant');
    $P::delete('avant-match-2');
    $ok($g3('/ecran/ecran-geant', ['key' => $scrKey])->status === 404, 'playlist supprimée : 404');

    // Export PNG (GD) et vidéo
    $c3 = new \App\Widgets\Context('playlist', 'fr', 'sombre', 'ecran');
    $all3 = [];
    foreach ($presets as $p) {
        foreach (\App\Widgets\Screen\Slides::build($p + $P::defaults(), $c3, $opp) as $s) {
            $all3[$s['layout']] ??= $s;
        }
    }
    $okPng = true;
    foreach ($all3 as $lay => $s) {
        foreach ([false, true] as $vert) {
            $png = \App\Widgets\Screen\Image::png($s, $c3, $vert, $lay === 'quiz');
            $sz = @getimagesizefromstring($png);
            $okPng = $okPng && $sz && $sz[2] === IMAGETYPE_PNG && [$sz[0], $sz[1]] === ($vert ? [1080, 1920] : [1920, 1080]);
        }
    }
    $ok($okPng && count($all3) >= 12, 'export PNG : ' . count($all3) . ' mises en page, en 1920 × 1080 et 1080 × 1920');
    $fr = \App\Widgets\Screen\Image::frames($all3['quiz'], $c3, false);
    $ok(count($fr) === 2 && array_sum(array_column($fr, 1)) === $all3['quiz']['duration'], 'export PNG : un quiz donne deux images (question, réponse) pour la même durée');
    $zipFile = \App\Widgets\Screen\Export::zip($presets['mi-temps'] + $P::defaults(), 'paysage', 'fr', $opp);
    $z = new \ZipArchive();
    $z->open($zipFile);
    $names = [];
    for ($i = 0; $i < $z->numFiles; $i++) {
        $names[] = $z->getNameIndex($i);
    }
    $ok(count(preg_grep('/\.png$/', $names)) >= 6 && in_array('ordre.txt', $names, true) && in_array('video-ffmpeg.txt', $names, true) && str_contains((string) $z->getFromName('video-ffmpeg.txt'), 'libx264'), 'export ZIP : images, ordre et durées, commande ffmpeg (' . count($names) . ' fichiers)');
    $z->close();
    $ok(\App\Widgets\Screen\Export::file('../reglages.json') === null && \App\Widgets\Screen\Export::file(basename($zipFile)) === $zipFile, 'export : seuls les fichiers d’export se téléchargent');
    \App\Widgets\Screen\Export::$ffmpegPath = '';
    $msg = '';
    try {
        \App\Widgets\Screen\Export::requestVideo($presets['mi-temps'] + $P::defaults(), 'paysage', 'fr', '', true);
    } catch (\RuntimeException $e) {
        $msg = $e->getMessage();
    }
    $ok(str_contains($msg, 'ffmpeg') && str_contains($msg, 'ZIP'), 'vidéo sans ffmpeg (o2switch) : message clair, le ZIP proposé');
    \App\Widgets\Screen\Export::$ffmpegPath = null;
    if (\App\Widgets\Screen\Export::ffmpeg()) {
        $tiny = ['id' => 'essai-video', 'name' => 'Essai', 'slides' => [['type' => 'chiffre-du-jour', 'duration' => 5, 'opt' => []], ['type' => 'quiz', 'duration' => 8, 'opt' => []]]] + $P::defaults();
        $k = \App\Widgets\Screen\Export::requestVideo($tiny, 'paysage', 'fr', '', true);
        $st = \App\Widgets\Screen\Export::status($k);
        $probe = [];
        exec('ffprobe -v error -show_entries stream=codec_name,width,height -of csv=p=0 ' . escapeshellarg(\App\Widgets\Screen\Export::dir() . '/' . ($st['file'] ?? 'x')) . ' 2>&1', $probe);
        $ok(($st['status'] ?? '') === 'pret' && ($st['duration'] ?? 0) === 13 && trim(implode('', $probe)) === 'h264,1920,1080', 'vidéo MP4 : H.264, 1920 × 1080, fabriquée par ffmpeg (' . ($st['status'] ?? '?') . ')');
    } else {
        $ok(true, 'vidéo MP4 : ffmpeg absent ici, essai sauté');
    }
    $ok($g3('/admin/widgets/ecrans/zip', ['id' => 'mi-temps'])->status === 302 && \App\Admin\Router::adminOnly('/admin/widgets/ecrans/fichier'), 'exports : réservés aux administrateurs connectés');
    // Contrôle avant diffusion (endpoint JSON du back-office, réservé aux administrateurs, CSRF).
    $ok(in_array($g3('/admin/widgets/ecrans/controle', [], ['Content-Type' => 'application/json', '_body' => '{"id":"avant-match"}'], 'POST')->status, [302, 401, 403, 419], true) && \App\Admin\Router::adminOnly('/admin/widgets/ecrans/controle'), 'contrôle : réservé aux administrateurs connectés');
    $keep = []; // Request::json() se souvient par objet : garder chaque requête en vie (identifiants distincts)
    $chk = function (array $b) use (&$keep) { return json_decode(\App\Admin\Widgets::screenCheck($keep[] = new Request('POST', '/admin/widgets/ecrans/controle', [], [], [], ['HTTP_HOST' => 'musee.fcsochauxretro.com', 'CONTENT_TYPE' => 'application/json'], json_encode($b)))->body, true); };
    $cr = $chk(['id' => 'avant-match', 'adversaire' => 'Club Inexistant XYZ', 'lang' => 'fr', 'date' => '2026-10-13']);
    $duelRows = array_values(array_filter($cr['rows'] ?? [], fn ($r) => $r['type'] === 'histoire-du-duel'));
    $ok(!empty($cr['ok']) && count($cr['rows']) === count($P::get('avant-match')['slides']) && $duelRows && $duelRows[0]['shown'] && str_contains($duelRows[0]['note'], 'première rencontre') && $cr['opponent']['known'] === false, 'contrôle : chaque slide listée, duel en version « Première rencontre »');
    $ok($cr['duration'] === array_sum(array_map(fn ($r) => $r['shown'] ? $r['duration'] : 0, $cr['rows'])) && $cr['duration'] <= $cr['planned'], 'contrôle : durée réelle de la boucle (' . $cr['duration'] . ' s)');
    $cr2 = $chk(['slides' => [['type' => 'ce-jour-la', 'duration' => 12], ['type' => 'quiz', 'duration' => 20]], 'playlist_adversaire' => '', 'date' => '2026-02-30']);
    $ok(!empty($cr2['ok']) && count($cr2['rows']) === 2 && $cr2['date'] === date('Y-m-d') && ($cr2['rows'][0]['shown'] || $cr2['rows'][0]['note'] === \App\Widgets\Screen\Slides::SKIP['ce-jour-la']), 'contrôle : playlist en cours de modification, date impossible → aujourd’hui, raison d’une slide sautée');
    $bad = $chk(['id' => 'inconnue-xyz']);
    $ok(($bad['ok'] ?? true) === false, 'contrôle : playlist inconnue refusée' . (($bad['ok'] ?? true) !== false ? ' ' . json_encode($bad) : ''));
    $demo3 = $get('/widgets/')->body;
    $ok(str_contains($demo3, 'id="ecrans"') && str_contains($demo3, 'Pour les écrans de stade'), 'page /widgets/ : section « Pour les écrans de stade » et contact');

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


    // ------------------------------------------------------------------ options configurables, catégories
    $types = ['select', 'search', 'date', 'number', 'text'];
    $expect = ['histoire-du-duel' => ['adversaire'], 'carte-legende' => ['joueur'], 'saison' => ['saison'], 'boutique' => ['produit', 'affichage'],
        'ce-jour-la' => ['date', 'nombre'], 'compte-a-rebours' => ['date', 'titre'], 'records' => ['cat', 'nombre'], 'il-y-a' => ['ans'], 'palmares' => ['affichage'], 'carte-stades' => ['zone']];
    foreach ($expect as $k => $names) {
        $w = Registry::get($k);
        $ok(array_keys($w->options()) === $names, "options de $k : " . implode(', ', $names));
        foreach ($w->describe()['options'] as $n => $o) {
            $ok(in_array($o['type'], $types, true) && $o['label'] !== '', "option $k.$n : type {$o['type']}");
            if (in_array($o['type'], ['select', 'search'], true)) {
                $ok(count($o['choices']) >= 2 && isset($o['choices'][$o['default']]), "option $k.$n : choix non vides sur " . \App\Core\Club::id() . ' (' . count($w->optionChoices($n)) . '), défaut dans la liste');
                // Valeur hors liste ou injection : la valeur par défaut.
                foreach (['zzz-inconnu', '"><script>alert(1)</script>', '../../etc', str_repeat('a', 200)] as $bad) {
                    // Option « free » (nom d'un club jamais affronté) : un nom propre est accepté, le reste refusé.
                    if ($bad === 'zzz-inconnu' && !empty($w->options()[$n]['free'])) {
                        $ok($w->clean([$n => $bad])[$n] === $bad, "option $k.$n : nom libre « $bad » accepté");
                        continue;
                    }
                    $ok($w->clean([$n => $bad])[$n] === $o['default'], "option $k.$n : « " . mb_substr($bad, 0, 20) . ' » refusée');
                }
            }
        }
    }
    $duel = Registry::get('histoire-du-duel');
    $advs = $duel->optionChoices('adversaire');
    $first = array_keys($advs)[1];
    $ok(array_key_first($advs) === 'auto' && preg_match('/\(\d+\)$/', $advs[$first]), 'histoire du duel : « auto » puis les adversaires avec le nombre de matchs');
    $ok($duel->clean(['adversaire' => $first])['adversaire'] === $first && $d3('histoire-du-duel', ['adversaire' => $first])['club'] === $first, "histoire du duel : adversaire choisi ($first)");
    $ok($d3('histoire-du-duel', ['adversaire' => 'auto']) !== null, 'histoire du duel : adversaire automatique');
    $ok($w = Registry::get('ce-jour-la') and $w->clean(['date' => '2024-02-29'])['date'] === '02-29' && $w->clean(['date' => '02-30'])['date'] === '' && $w->clean(['nombre' => '99'])['nombre'] === '3' && $w->clean(['nombre' => '5'])['nombre'] === '5', 'ce jour-là : date du calendrier → MM-JJ ; date impossible et nombre hors bornes refusés');
    $cr = Registry::get('compte-a-rebours');
    $ok($cr->clean(['date' => '2001-01-01'])['date'] === '' && $cr->clean(['date' => date('Y-m-d', strtotime('+3 days'))])['date'] !== '', 'compte à rebours : date passée refusée, date future acceptée');
    $ok(count(Registry::get('carte-legende')->optionChoices('joueur')) > 10, 'carte de légende : liste des joueurs publiés');

    // Suggestions (listes longues).
    $lbl = preg_replace('/\s*\(\d+\)$/', '', $advs[$first]);
    $sg = $get('/api/v1/widgets/histoire-du-duel/suggestions/adversaire', ['q' => mb_strtolower($lbl)]);
    $sj = $json($sg);
    $ok($sg->status === 200 && in_array($first, array_column($sj['items'], 'value'), true) && count($sj['items']) <= 20, "suggestions : « $lbl » trouvé, 20 au plus");
    $ok($json($get('/api/v1/widgets/histoire-du-duel/suggestions/adversaire', ['q' => 'zzzzqqq']))['items'] === [], 'suggestions : rien pour un nom inconnu');
    $ok($get('/api/v1/widgets/histoire-du-duel/suggestions/inconnue')->status === 404 && $get('/api/v1/widgets/ce-jour-la/suggestions/date')->status === 404, 'suggestions : option inconnue ou sans liste → 404');
    $ok(count($json($get('/api/v1/widgets/carte-legende/suggestions/joueur', ['q' => '']))['items']) === 20, 'suggestions : joueurs (20 premiers)');
    Store::saveWidget('histoire-du-duel', 'desactive', false);
    $ok($get('/api/v1/widgets/histoire-du-duel/suggestions/adversaire')->status === 404, 'suggestions : widget désactivé → 404');
    Store::saveWidget('histoire-du-duel', 'libre', false);

    // Catégories, pastille « Personnalisable ».
    foreach (Registry::all() as $k => $w) {
        $ok(count($w->category()) >= 1 && count($w->category()) <= 2 && !array_diff($w->category(), array_keys(Registry::CATEGORIES)), "catégorie de $k : " . implode(', ', $w->category()));
    }
    $cat = $json($get('/api/v1/widgets'));
    $row = array_column($cat['widgets'], null, 'key');
    $ok(count($cat['categories']) === count(Registry::CATEGORIES) && isset($row['histoire-du-duel']) && $row['histoire-du-duel']['category'] === ['matchs'] && $row['histoire-du-duel']['customizable'] === true && isset($row['histoire-du-duel']['options']['adversaire']), 'API catalogue : catégories, champ category, customizable et options');
    $ok(isset($row['chiffre-du-jour']) && $row['chiffre-du-jour']['customizable'] === false, 'API catalogue : widget sans option → customizable false');
    $m = array_column($json($get('/api/v1/widgets', ['categorie' => 'jeux']))['widgets'], 'key');
    $ok($m !== [] && !array_diff($m, ['quiz-du-jour', 'defi-du-jour']), 'API catalogue : ?categorie=jeux');
    $ok(count($json($get('/api/v1/widgets', ['categorie' => '<x>']))['widgets']) === count($cat['widgets']), 'API catalogue : catégorie inconnue ignorée');

    // ------------------------------------------------------------------ page publique, back-office
    $demo = $get('/widgets/');
    $ok($demo->status === 200 && substr_count($demo->body, 'data-musee=') >= 14 && str_contains($demo->body, '/contact/?objet=partenariat'), 'page /widgets/ : aperçus, code à copier, contact partenaire');
    $ok(str_contains($demo->body, 'data-cat-filter') && str_contains($demo->body, 'data-cat="matchs"') && str_contains($demo->body, 'data-cats="matchs') && str_contains($demo->body, 'wdemo-badge'), 'page /widgets/ : filtre par catégorie et pastille « Personnalisable »');
    $ok(str_contains($demo->body, 'data-opt="adversaire"') && str_contains($demo->body, 'data-wcfg='), 'page /widgets/ : réglage des options (adversaire…)');
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
