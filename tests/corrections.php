<?php
/**
 * Corrections de données ponctuelles (App\Services\DataFixes) : matchs d'avant-saison 2002-2009
 * rangés dans la saison qui commence (saison, rubrique « Classement & SEO », adresse redirigée),
 * une seule fois ; les autres matchs ne bougent pas. Fiches d'essai créées puis supprimées ;
 * rubriques, redirections et journal des corrections remis en état.
 * Usage : php tests/corrections.php (code 1 si échec).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Categories;
use App\Data\Fiches;
use App\Data\Redirects;
use App\Services\DataFixes;
use App\Services\FcsmStory;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

$saved = [];
foreach ([Categories::FILE, Redirects::FILE, DataFixes::FILE] as $f) {
    $saved[$f] = is_file($f) ? (string) file_get_contents($f) : null;
}
$ids = [];
$make = function (string $date, string $season, string $status = 'publie') use (&$ids): int {
    $doc = Fiches::blank('match');
    $doc['title'] = 'Essai avant-saison ' . $date;
    $doc['status'] = $status;
    $doc['match']['date'] = $date;
    $doc['match']['season'] = $season;
    $doc['match']['home'] = ['name' => 'Sochaux', 'level' => null];
    $doc['match']['away'] = ['name' => 'Essai FC', 'level' => null];
    $doc['categories'] = [$season, 'amical-fc-sochaux-retro-fcsm'];
    $doc['path'] = '/matchs/' . $season . '/essai-avant-saison-' . $date . '/';
    $id = (int) Fiches::save($doc, ['name' => 'Essai'])['id'];
    $ids[] = $id;
    return $id;
};

try {
    // Règle des imports : la saison change au 1er juillet, comme sur le reste du site.
    $eq('import : un match de juillet appartient à la saison qui commence', [FcsmStory::seasonFor('2005-07-20'), FcsmStory::seasonFor('2005-06-20'), FcsmStory::seasonFor('2005-08-02')], ['2005-2006', '2004-2005', '2005-2006']);

    Categories::ensureSeasons();
    Categories::forget();
    $a = $make('2005-07-20', '2004-2005');
    $b = $make('2002-06-29', '2001-2002');           // Intertoto 2002 : juin compris
    $c = $make('2004-06-05', '2003-2004');           // juin ordinaire : fin de saison, ne bouge pas
    $d = $make('2012-07-14', '2011-2012');           // hors de la liste demandée : ne bouge pas
    $e = $make('2009-07-31', '2009-2010');           // déjà bien rangé
    @unlink(DataFixes::FILE);
    $out = DataFixes::run();
    $doc = Fiches::fresh($a);
    $eq('juillet 2005 : saison 2005-2006', $doc['match']['season'], '2005-2006');
    $eq('juillet 2005 : rubrique de saison remplacée, les autres gardées', $doc['categories'], ['amical-fc-sochaux-retro-fcsm', '2005-2006']);
    $eq('juillet 2005 : adresse dans la nouvelle saison', $doc['path'], '/matchs/2005-2006/essai-avant-saison-2005-07-20/');
    $eq('juillet 2005 : ancienne adresse redirigée', Redirects::find('/matchs/2004-2005/essai-avant-saison-2005-07-20/'), '/matchs/2005-2006/essai-avant-saison-2005-07-20/');
    $eq('juin 2002 (Intertoto) : saison 2002-2003', Fiches::fresh($b)['match']['season'], '2002-2003');
    $eq('juin 2004 : reste en fin de saison 2003-2004', [Fiches::fresh($c)['match']['season'], Fiches::fresh($c)['path']], ['2003-2004', '/matchs/2003-2004/essai-avant-saison-2004-06-05/']);
    $eq('juillet 2012 (hors liste) : ne bouge pas', Fiches::fresh($d)['match']['season'], '2011-2012');
    $done = json_decode((string) file_get_contents(DataFixes::FILE), true);
    $eq('compte rendu : les deux fiches déplacées seulement', array_values(array_intersect($done['2026-10-avant-saison-2002-2009']['ids'] ?? [], $ids)), [$a, $b]);
    $eq('compte rendu de la tâche', (bool) preg_match('/rangé\(s\) dans la bonne saison/u', (string) $out), true);
    // Une seule fois : remise à la main dans l'ancienne saison, la correction ne repasse pas.
    $x = Fiches::fresh($a);
    $x['match']['season'] = '2004-2005';
    Fiches::save($x, ['name' => 'Essai']);
    $eq('deuxième passage : rien à faire', DataFixes::run(), null);
    $eq('deuxième passage : la fiche n’est pas retouchée', Fiches::fresh($a)['match']['season'], '2004-2005');
} finally {
    foreach ($ids as $id) {
        Fiches::destroy($id, ['name' => 'Essai']);
    }
    foreach ($saved as $f => $content) {
        $content === null ? @unlink($f) : file_put_contents($f, $content);
    }
    Categories::forget();
    \App\Data\Index::rebuild();
    \App\Services\Search::rebuild();
}

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
