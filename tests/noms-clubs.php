<?php
/**
 * Noms imposés des adversaires (profil du club, « club_display ») et rapprochement des clubs
 * (Names::clubKey) : toutes les façons d'écrire Rennes et Marseille donnent un seul club dans les
 * statistiques et le nom voulu sur les fiches. Lecture seule.
 * Usage : php tests/noms-clubs.php (code 1 si échec).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Fiches;
use App\Data\Names;

$ok = 0;
$ko = 0;
$t = function (string $label, $got, $exp) use (&$ok, &$ko) {
    if ($got === $exp) {
        $ok++;
        return;
    }
    $ko++;
    echo "ÉCHEC $label\n  obtenu: " . json_encode($got, JSON_UNESCAPED_UNICODE) . "\n  attendu: " . json_encode($exp, JSON_UNESCAPED_UNICODE) . "\n";
};
$match = fn (string $home, string $title = '') => ['type' => 'match', 'title' => $title ?: "J1 – $home / Sochaux – D1 – 01/01/1990 – 1-0",
    'match' => ['home' => ['name' => $home], 'away' => ['name' => 'Sochaux']]];

foreach (['Rennes', 'Stade Rennes UC', 'Stade Rennes FC', 'Stade Rennais', 'Stade Rennais FC', 'Stade Rennais UC', 'Stade rennais F.C.', 'SRFC'] as $n) {
    $t("clé « $n »", Names::clubKey($n), 'rennes');
    $d = Fiches::clubDisplayNames($match($n));
    $t("nom « $n »", [$d['match']['home']['name'], $d['title']], ['Stade Rennais FC', 'J1 – Stade Rennais FC / Sochaux – D1 – 01/01/1990 – 1-0']);
}
foreach (['Marseille', 'Ol. Marseille', 'OM', 'Olympique de Marseille'] as $n) {
    $t("Marseille « $n »", Fiches::clubDisplayNames($match($n))['match']['home']['name'], 'Olympique de Marseille');
}
$t('précision gardée', Fiches::clubDisplayNames($match('Rennes (B)'))['match']['home']['name'], 'Stade Rennais FC (B)');
$t('réserve « Rennes B » à part', [Names::clubKey('Rennes B'), Fiches::clubDisplayNames($match('Rennes B'))['match']['home']['name']], ['rennes-b', 'Rennes B']);
$t('titre déjà au bon nom : pas de « FC FC »', Fiches::clubDisplayNames($match('Stade Rennais', 'Stade Rennais FC – Sochaux'))['title'], 'Stade Rennais FC – Sochaux');
$t('Sochaux et autres clubs inchangés', array_column(array_column([Fiches::clubDisplayNames($match('Nantes'))['match']], 'home'), 'name'), ['Nantes']);
$t('autre type de fiche inchangé', Fiches::clubDisplayNames(['type' => 'personne', 'title' => 'Rennes']), ['type' => 'personne', 'title' => 'Rennes']);

// Nancy (ticket) : « FC Nancy » laissé tel quel jusqu'en 1963, « AS Nancy-Lorraine » à partir de 1964.
$nancy = function (string $name, string $date) use ($match) {
    $d = $match($name, "J5 – $name / Sochaux – D1 – $date – 2-1");
    $d['match']['date'] = $date;
    $d = Fiches::clubDisplayNames($d);
    return [$d['match']['home']['name'], $d['title']];
};
$t('Nancy, 1970 : AS Nancy-Lorraine (équipe et titre)', $nancy('Nancy', '1970-08-29'), ['AS Nancy-Lorraine', 'J5 – AS Nancy-Lorraine / Sochaux – D1 – 1970-08-29 – 2-1']);
$t('Nancy, 1er janvier 1964 : AS Nancy-Lorraine', $nancy('Nancy', '1964-01-01')[0], 'AS Nancy-Lorraine');
$t('FC Nancy, 1960 : laissé tel quel', $nancy('FC Nancy', '1960-03-13'), ['FC Nancy', 'J5 – FC Nancy / Sochaux – D1 – 1960-03-13 – 2-1']);
$t('Nancy, 1963 : laissé tel quel', $nancy('Nancy', '1963-12-31')[0], 'Nancy');
$t('Nancy sans date : laissé tel quel', Fiches::clubDisplayNames($match('Nancy'))['match']['home']['name'], 'Nancy');
$t('même club pour les statistiques', [Names::clubKey('FC Nancy'), Names::clubKey('AS Nancy-Lorraine')], ['nancy', 'nancy']);

echo "$ok réussis, $ko échecs\n";
exit($ko ? 1 : 0);
