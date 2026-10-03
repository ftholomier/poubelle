<?php
/**
 * Correcteur d'orthographe (App\Services\Proofreader) : texte des champs, règles du musée,
 * contrôle des corrections de Gemini (simulé), mots protégés, cache.
 * Usage : php tests/correcteur.php (code de sortie 1 en cas d'échec). N'écrit que dans un
 * dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Proofreader as P;

$tmp = sys_get_temp_dir() . '/correcteur-test-' . bin2hex(random_bytes(4));
P::$cacheDir = "$tmp/cache";
P::$dir = "$tmp/etat";
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$pairs = fn (array $items) => array_map(fn ($x) => $x['wrong'] . ' → ' . $x['right'], $items);

// Texte d'un champ : blocs séparés, espaces resserrées.
$eq('texte brut', P::norm(P::plain('<p>Les <strong>Lionceaux</strong>&nbsp;gagnent.</p><ul><li>Un</li><li>Deux</li></ul>', true)), "Les Lionceaux gagnent.\nUn\nDeux");
$eq('texte sans balise', P::plain('Martin & Privat', false), 'Martin & Privat');
$eq('retour à la ligne', P::norm(P::plain('Ligne 1<br>Ligne 2', true)), "Ligne 1\nLigne 2");

// Règles du musée.
$r = fn (string $t) => $pairs(P::rules($t));
$eq('mot répété', $r('Il est est transféré au PSG.'), ['est est → est']);
$eq('nous nous', $r('Nous nous sommes qualifiés.'), []);
$eq('chant', $r('Sochaux (clap clap clap) Sochaux'), []);
$eq('xx xx', $r('né le xx xx 1940'), []);
$eq('a à', $r('alors qu’il à à peine 16 ans'), []);
$eq('espace avant virgule', $r('Jacky Colin , de retour'), ['Colin , → Colin,']);
$eq('espace avant point', $r('détournée par Prévot . Le ballon'), ['Prévot . → Prévot.']);
$eq('deux-points puis point', $r("75' : . Sur une attaque"), []);
$eq('virgule collée', $r('servi par Paye,Weissbeck manque'), ['Paye,Weissbeck → Paye, Weissbeck']);
$eq('nom coupé', $r('Bon,iface – Sochaux-Lorient'), []);
$eq('décimale', $r('une moyenne de 1,5 but'), []);
$eq('point collé', $r('au 2nd poteau.Andriatsima s’élève'), ['poteau.Andriatsima → poteau. Andriatsima']);
$eq('adresse web', $r('voir www.fcsochaux.Fr ici'), []);
$eq('apostrophe espacée', $r("contre l' Angleterre B"), ["l' Angleterre → l'Angleterre"]);
$eq('ordinal', $r('le 3ème but et les 16emes de finale'), ['3ème → 3e', '16emes → 16es']);
$eq('siècle', $r('au XXème siècle'), ['XXème → XXe']);
$eq('9è', $r('remonte à la 9è place'), ['9è → 9e']);
$eq('1ème laissé', $r('sa 1ème sélection'), []);
$eq('première', $r('sa 1ère titularisation, les 1eres mi-temps'), ['1ère → 1re', '1eres → 1res']);
$eq('À en tête', $r("A l'extérieur, Nantes compte 2 nuls. A peine rentré, il marque."), ['A → À', 'A → À']);
$eq('A-t-il', $r('A-t-il marqué ?'), []);
$eq('A majuscule en milieu', $r('le groupe A de la poule'), []);

// Contrôle des corrections proposées par Gemini.
$v = fn (array $c, string $t) => $pairs(P::validate($c, $t));
$t = "Les joueurs était contents. L’est républician l'a écrit : il a tout manque à Bonal en 1987.";
$eq('accord', $v(['faux' => 'joueurs était contents', 'juste' => 'joueurs étaient contents', 'type' => 'accord'], $t), ['joueurs était contents → joueurs étaient contents']);
$eq('apostrophe courbe retrouvée', $v(['faux' => "L'est républician", 'juste' => "L'Est Républicain", 'type' => 'orthographe'], $t), ['L’est républician → L’Est Républicain']);
$eq('extrait absent', $v(['faux' => 'joueur était', 'juste' => 'joueurs étaient'], $t), []);
$eq('chiffres intacts', $v(['faux' => 'en 1987', 'juste' => 'en 1988'], $t), []);
$eq('réécriture refusée', $v(['faux' => 'il a tout manque à Bonal', 'juste' => 'le match fut raté de bout en bout'], $t), []);
$eq('extrait ambigu', $v(['faux' => 'a', 'juste' => 'à'], 'il a vu a Bonal'), []);
$eq('mot répété partout', count(P::validate(['faux' => 'républician', 'juste' => 'républicain', 'type' => 'orthographe'], 'L’Est républician et le républician du jour')), 2);
$eq('typographie seule', $v(['faux' => "l'a écrit", 'juste' => 'l’a écrit'], $t), []);
$eq('saut de ligne refusé', $v(['faux' => "contents.\nL", 'juste' => 'contents. L'], $t), []);

// Vérification complète avec un Gemini simulé.
$calls = [];
P::$ai = function (array $jobs) use (&$calls) {
    $calls[] = $jobs;
    $out = [];
    foreach ($jobs as [$contents]) {
        $in = json_decode($contents[0]['text'], true);
        $corr = [];
        foreach ($in['textes'] as $x) {
            if (str_contains($x['texte'], 'tout manque')) {
                $corr[] = ['id' => $x['id'], 'faux' => 'tout manque', 'juste' => 'tout manqué', 'type' => 'accord', 'explication' => 'Participe passé.'];
            }
            if (str_contains($x['texte'], 'Peybernes')) {
                $corr[] = ['id' => $x['id'], 'faux' => 'Peybernes', 'juste' => 'Peyberne', 'type' => 'orthographe', 'explication' => 'Nom mal écrit.'];
            }
            if (str_contains($x['texte'], 'trés')) {
                $corr[] = ['id' => $x['id'], 'faux' => 'trés', 'juste' => 'très', 'type' => 'orthographe', 'explication' => 'Accent grave.'];
            }
        }
        $out[] = ['text' => json_encode(['corrections' => $corr], JSON_UNESCAPED_UNICODE), 'finish' => 'STOP', 'tokens_in' => 0, 'tokens_out' => 0, 'model' => 'essai'];
    }
    return $out;
};
$fields = [
    ['k' => 'a', 'value' => '<p>Corentin Jean a tout manque. Il est est sorti.</p>', 'html' => true],
    ['k' => 'b', 'value' => 'But de Peybernes à la 53e minute', 'html' => false],
    ['k' => 'c', 'value' => 'A trés bientôt', 'html' => false, 'lang' => 'fr', 'kind' => 'title'],
    ['k' => 'd', 'value' => '123', 'html' => false],
];
$res = P::check($fields, ['scope' => 'fiche:1', 'names' => ['Mathieu Peybernes']]);
$eq('moteur', $res['engine'], 'gemini');
$eq('corrections', array_map(fn ($x) => $x['k'] . ' : ' . $x['wrong'] . ' → ' . $x['right'], $res['items']), ['a : tout manque → tout manqué', 'a : est est → est', 'c : A → À', 'c : trés → très']);
$first = $res['items'][0];
$eq('contexte', [$first['before'], $first['after'], $first['nth']], ['Corentin Jean a ', '. Il est est sorti.', 0]);
$eq('un appel groupé', count($calls), 1);
$eq('noms envoyés', in_array('Mathieu Peybernes', json_decode($calls[0][0][0][0]['text'], true)['noms_connus'], true), true);
// Les textes déjà vérifiés ne repartent pas chez Gemini.
P::check($fields, ['scope' => 'fiche:1']);
$eq('cache', count($calls), 1);
// Corrections ignorées.
P::ignore('fiche:1', $res['items'][1]['sig']);
$eq('ignorée', count(P::check($fields, ['scope' => 'fiche:1'])['items']), 3);
$eq('ignorée ailleurs', count(P::check($fields, ['scope' => 'fiche:2'])['items']), 4);
$eq('portée refusée', P::validScope('ecran:/../../etc'), false);
// Sans Gemini : règles seules.
P::$ai = null;
$eq('règles seules', array_column(P::check($fields, ['ai' => false])['items'], 'src'), ['regles', 'regles']);
// Découpage des textes longs.
$long = str_repeat("Une phrase assez longue pour remplir le paragraphe. ", 40) . "\n" . str_repeat('Deuxième paragraphe. ', 30);
$parts = P::chunks($long, 1000);
$eq('découpage', implode('', array_column($parts, 1)) === $long && max(array_map(fn ($x) => mb_strlen($x[1]), $parts)) <= 1000, true);

// Ménage.
$rm = function (string $d) use (&$rm) {
    foreach (glob("$d/*") ?: [] as $f) {
        is_dir($f) ? $rm($f) : unlink($f);
    }
    @rmdir($d);
};
$rm($tmp);
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
