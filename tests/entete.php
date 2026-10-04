<?php
/**
 * En-tête des fiches de match (App\Services\MatchText) : date et tour en toutes lettres de l'ancien
 * site écartés quand ils contredisent la fiche (page, audio, assistant), remplacés à l'enregistrement,
 * et garde-fou quand on change l'adversaire ou la date d'une fiche déjà remplie.
 * Usage : php tests/entete.php (code de sortie 1 en cas d'échec). N'écrit rien.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\FicheForm;
use App\Data\Fiches;
use App\Front\Fiche;
use App\Services\FicheAudio;
use App\Services\MatchText as T;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$m = fn (int $id) => Fiches::get($id)['match'];
$ref = fn (?array $i) => $i ? $i['ref'] : null;

// Date en toutes lettres de l'ancien site.
$eq('date d’origine concordante : rien', T::dateIssue($m(12891)), null);
$eq('« Vendredi 21 aout 2026 » pour le 02/10/2026 (Xamax)', [$ref(T::dateIssue($m(22962))), T::dateIssue($m(22962))['sev']], ['ecart', 'moyenne']);
$eq('jour de la semaine faux', $ref(T::dateIssue($m(10448))), 'jour');
$eq('date illisible (« janiver »)', $ref(T::dateIssue($m(14871))), 'illisible');
$eq('fiche sans date en toutes lettres : rien', T::dateIssue(['date' => '2020-01-01', 'date_text' => '']), null);

// Tour en toutes lettres de l'ancien site.
$eq('tour concordant (J32, « 32e journée de Ligue 2 ») : rien', T::roundIssue($m(10040)), null);
$eq('journée de Ligue 2 pour un amical (Xamax)', $ref(T::roundIssue($m(22962))), 'amical');
$eq('1re journée de L1 pour un amical de juillet', $ref(T::roundIssue($m(16765))), 'amical');
$eq('« 38e journée » pour la J3', $ref(T::roundIssue($m(17794))), 'journee');
$eq('une journée d’écart (match en retard) : rien', T::roundIssue($m(14573)), null);
$eq('« de D2 » pour un match de Division 1', $ref(T::roundIssue($m(17973))), 'division');
$eq('autre tour de coupe (16e pour 32e)', $ref(T::roundIssue($m(508))), 'coupe');
$eq('journée de poule de coupe : rien', T::roundIssue(['competition' => 'Coupe de la Ligue', 'competition_label' => 'Coupe de la Ligue', 'round' => 'Phase de groupe', 'round_text' => '2ème journée de poule de coupe de la Ligue']), null);

// Tour affiché d'après la journée saisie.
$lbl = fn (string $r, bool $en = false) => T::roundLabel(['round' => $r, 'competition' => 'Coupe de France', 'competition_label' => 'Coupe de France'], $en);
$eq('journées et tours de coupe en toutes lettres', array_map($lbl, ['J1', 'J07', '1/8e aller', '1/2', '1/4 retour', '1er tour']), ['1re journée', '7e journée', '8e de finale aller', 'demi-finale', 'quart de finale retour', '1er tour']);
$eq('en anglais', array_map(fn ($r) => $lbl($r, true), ['J15', '1/8e', '1/16e retour']), ['Matchday 15', 'Round of 16', 'Round of 32, second leg']);
$eq('« Amical » ne redit pas la compétition', T::roundLabel(['round' => 'Amical', 'competition' => 'Amical', 'competition_label' => 'Amical']), '');

// Page du match (et PDF, Rétro-Direct, souvenirs) : l'en-tête suit les champs saisis.
$x = Fiche::localizeDoc(Fiches::get(22962))['match'];
$eq('Xamax : ni « 3è journée de ligue 2 » ni « 21 aout » sur la page', [$x['round_text'], $x['date_text']], ['', '']);
$x = Fiche::localizeDoc(Fiches::get(10040))['match'];
$eq('fiche concordante : textes d’origine affichés', [$x['round_text'], $x['date_text']], ['32e journée de Ligue 2', 'Vendredi 7 avril 2017']);
$x = T::header(Fiches::get(10040), true)['match'];
$eq('en anglais : journée et date d’après les champs', [$x['round_text'], $x['date_text']], ['Matchday 32', '']);
$new = Fiches::blank('match');
$new['match'] = array_replace($new['match'], ['date' => '2026-10-17', 'competition' => 'Championnat', 'competition_label' => 'Ligue 2', 'round' => 'J10']);
$eq('fiche créée au back-office : la journée saisie s’affiche', T::header($new, false)['match']['round_text'], '10e journée');
$eq('assistant : tour contredit écarté', str_contains(\App\Services\Rag::header(Fiches::get(22962)), 'journée'), false);
$eq('audio : tour contredit non lu (amical de 2012)', str_contains(FicheAudio::template(Fiches::get(16765), 'fr'), 'journée'), false);
$eq('IA : tour contredit non transmis', json_decode(FicheAudio::aiPrompt(Fiches::get(16765), 'fr')[1], true)['match']['tour'], '');

// Enregistrement au back-office : le tour suit la journée saisie, l'en-tête contredit est remplacé.
$apply = function (array $doc, array $in): array {
    $errors = [];
    return FicheForm::apply($doc, $in, $errors)['match'];
};
$d = Fiches::get(10040);
$eq('journée inchangée : tour d’origine gardé', $apply($d, ['match' => ['round' => 'J32']])['round_text'], '32e journée de Ligue 2');
$eq('journée inchangée (J032) : tour d’origine gardé', $apply($d, ['match' => ['round' => 'J032']])['round_text'], '32e journée de Ligue 2');
$eq('journée changée : tour d’origine effacé', $apply($d, ['match' => ['round' => 'J33']])['round_text'], '');
$x = $apply(Fiches::get(22962), ['match' => ['round' => 'Amical', 'date' => '2026-10-02']]);
$eq('Xamax enregistrée : en-tête contredit remplacé', [$x['round_text'], $x['date_text']], ['', 'Vendredi 2 octobre 2026']);

// Garde-fou : adversaire ou date changés sur une fiche déjà remplie.
$doc = Fiches::get(22962);
$with = function (array $doc, array $m): array {
    $doc['match'] = array_replace_recursive($doc['match'], $m);
    return $doc;
};
$s = T::switched($doc, $with($doc, ['away' => ['name' => 'Guingamp']]));
$eq('autre adversaire : à confirmer', $s['changes'] ?? null, ['adversaire Neuchâtel Xamax → Guingamp']);
$eq('le message dit ce que contient la fiche', (bool) preg_match('/des textes, une composition de 21 joueurs, .*12 photos et vidéos/u', $s['text'] ?? ''), true);
$eq('autre date (21/08) : à confirmer', T::switched($doc, $with($doc, ['date' => '2026-08-21']))['changes'] ?? null, ['date 02/10/2026 → 21/08/2026']);
$eq('date corrigée d’un jour : rien', T::switched($doc, $with($doc, ['date' => '2026-10-03'])), null);
$eq('même club autrement écrit : rien', T::switched($doc, $with($doc, ['away' => ['name' => 'Neuchâtel Xamax FCS']])), null);
$psg = Fiches::get(786);
$eq('« Paris SG » → « Paris Saint-Germain » : rien', T::switched($psg, $with($psg, ['home' => ['name' => 'Paris Saint-Germain']])), null);
$blank = Fiches::blank('match');
$blank['match']['away']['name'] = 'Lens';
$eq('fiche encore vide : rien', T::switched($blank, $with($blank, ['away' => ['name' => 'Metz']])), null);
$first = $with($doc, ['away' => ['name' => '']]);
$eq('premier adversaire saisi : rien', T::switched($first, $doc), null);

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
