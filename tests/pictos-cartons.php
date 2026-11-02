<?php
/**
 * Pictos « Carton rouge » / « Carton jaune » du minute par minute (card_taken(), app/helpers.php) :
 * carton donné seulement, jamais une sanction évoquée (ticket Brest–Sochaux 2010 : « Son intervention
 * aurait pu lui valoir une exclusion mais l'arbitre laisse jouer »). Rien n'est écrit.
 * Usage : php tests/pictos-cartons.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$c = fn (string $t) => card_taken(mb_strtolower($t));

// 1. Cartons donnés.
$eq('expulsé, carton rouge, rouge direct, exclu', [$c('Perquis est expulsé pour un second avertissement.'), $c('Carton rouge pour Pedretti !'), $c('Dodé prend un rouge direct pour un pied haut.'), $c('Exclu, Mikic laisse Sochaux à dix.')], ['r', 'r', 'r', 'r']);
$eq('second jaune, 2e jaune', [$c('Le Brestois écope d’un second jaune.'), $c('2e jaune, donc rouge. Sochaux va finir à 9.'), $c('2e carton jaune. Explusion')], ['r', 'r', 'r']);
$eq('carton jaune, averti', [$c('Carton jaune pour Bréchet.'), $c('Sotoca, Cahuzac et Rocchia sont avertis.')], ['y', 'y']);
$eq('« pas », « aurait » ailleurs dans la phrase : carton gardé', [$c('Isabey, qui n’avait pas été averti, est expulsé.'), $c('Alors qu’il aurait pu égaliser, Pagis est expulsé.'), $c('L’arbitre expulse l’Espagnol mais pas le défenseur varois !')], ['r', 'r', 'r']);
$eq('prénom Clément : carton gardé', $c('Il est déséquilibré par Clément qui est exclu.'), 'r');

// 2. Sanction évoquée, pas donnée : pas de picto rouge.
$eq('ticket : « aurait pu lui valoir une exclusion »', $c('Son intervention aurait pu lui valoir une exclusion mais l’arbitre laisse jouer...'), null);
$eq('« aurait pu être expulsé » après des avertissements : jaune', $c('Sotoca et Diedhiou sont avertis. Le Sochalien aurait pu être expulsé.'), 'y');
$eq('sanction évitée, frôlée, réclamée, méritée', [$c('Daf fait faute et évite le carton rouge de peu.'), $c('Le gardien frôle l’exclusion.'), $c('Le public réclame l’expulsion de Roux.'), $c('Mikic aurait mérité un carton jaune.'), $c('Roux échappe à l’expulsion.')], [null, null, null, null, null]);
$eq('sanction niée', [$c('Le gardien mulhousien n’est pas expulsé...'), $c('Sans être expulsé, Perquis sort sur blessure.'), $c('L’expulsion est évitée de justesse.')], [null, null, null]);
$eq('pas de rouge mais un jaune', $c('Pas de carton rouge pour le Brestois, simplement averti.'), 'y');
$eq('question', $c('Expulsion ? L’arbitre laisse jouer.'), null);

// 3. La fiche du ticket (Brest–Sochaux, 13/11/2010) : pas de carton rouge à la 76e.
$doc = \App\Data\Fiches::fresh(3645);
if ($doc) {
    $req = new \App\Core\Request('GET', '/', [], [], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1'], '');
    $html = \App\Front\Fiche::show($req, $doc)->body;
    preg_match('#<li class="timeline__item[^"]*"[^>]*>\s*<span class="timeline__min[^"]*">76\'</span>(.*?)</li>#s', $html, $m);
    $eq('Brest–Sochaux 2010, 76e (Perquis) : pas de picto carton rouge', [isset($m[1]), str_contains($m[1] ?? '', 'cardmark--r')], [true, false]);
}

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
