<?php
/**
 * Masque de saisie des fiches (App\Admin\FicheForm) : un enregistrement sans modification
 * ne change rien (valeurs reprises de l'ancien site comprises), et les vraies modifications
 * sont bien appliquées. Usage : php tests/fiche-form.php (code de sortie 1 en cas d'échec).
 * N'écrit rien : les fiches sont lues puis modifiées en mémoire seulement.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\FicheForm as F;
use App\Data\Fiches;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$apply = function (array $doc, array $in, ?array &$errors = null): array {
    $errors = [];
    return F::apply($doc, $in, $errors);
};

// Personne : décès avec ville, département et coordonnées (325 fiches reprises).
$p = Fiches::get(5944);
$death = $p['personne']['death'];
$in = ['personne' => ['death_date' => F::dateText($death['date']), 'death_place' => $death['place']['text']]];
$eq('décès inchangé : lieu détaillé et texte d’origine gardés', $apply($p, $in)['personne']['death'], $death);
$in['personne']['death_place'] = 'Belfort (90)';
$new = $apply($p, $in)['personne']['death'];
$eq('décès : nouveau lieu enregistré', [$new['place'], $new['date']], [['text' => 'Belfort (90)'], $death['date']]);
$eq('décès : texte refait', $new['text'], 'décédé le ' . $death['date']['text'] . ' à Belfort (90)');
$in = ['personne' => ['death_date' => F::dateText($death['date']), 'death_place' => '']];
$eq('décès : lieu effacé', $apply($p, $in)['personne']['death']['place'], null);

// Naissance : texte d'origine gardé tant que rien ne change.
$p = Fiches::get(4673);
$b = $p['personne']['birth'];
$in = ['personne' => ['birth_date' => F::dateText($b['date']), 'birth_city' => $b['place']['city'], 'birth_department' => (string) $b['place']['department'], 'birth_country' => (string) $b['place']['country'], 'birth_lat' => $b['place']['lat'], 'birth_lng' => $b['place']['lng']]];
$eq('naissance inchangée gardée telle quelle', $apply($p, $in)['personne']['birth'], $b);
$in['personne']['birth_date'] = '20 novembre 1969';
$new = $apply($p, $in)['personne']['birth'];
$eq('naissance : nouvelle date, lieu d’origine gardé', [$new['date']['iso'], $new['place']], ['1969-11-20', $b['place']]);

// Dates reprises non reconnues (« juin 1978 ? ») : gardées si on n'y touche pas, refusées si on les retape mal.
$p = Fiches::get(20642);
$old = $p['personne']['departure'];
$new = $apply($p, ['personne' => ['departure' => F::dateText($old)]], $errors);
$eq('date non reconnue inchangée : gardée, sans erreur', [$new['personne']['departure'], $errors], [$old, []]);
$apply($p, ['personne' => ['departure' => 'été 1979']], $errors);
$eq('date non reconnue saisie : erreur', array_keys($errors), ['personne.departure']);
$new = $apply($p, ['personne' => ['departure' => 'juin 1979 ?']], $errors);
$eq('date incertaine « juin 1979 ? » acceptée', [$new['personne']['departure'], $errors], [['iso' => '1979-06', 'precision' => 'month', 'text' => 'juin 1979 ?'], []]);
$new = $apply($p, ['personne' => ['departure' => 'juin 1979']], $errors);
$eq('date reconnue saisie : enregistrée', [$new['personne']['departure']['iso'], $errors], ['1979-06', []]);

// Lignes de la fiche d'identité : un intertitre sans valeur reste à sa place.
$rows = [['label' => 'Né le', 'value' => '1er janvier 1950'], ['label' => 'Passage comme joueur', 'value' => ''], ['label' => '', 'value' => '']];
$new = $apply($p, ['personne' => ['fiche' => $rows]]);
$eq('lignes : intertitre gardé, ligne vide retirée', $new['personne']['fiche'], [['label' => 'Né le', 'value' => '1er janvier 1950'], ['label' => 'Passage comme joueur', 'value' => '']]);

// Rareté de l'album : vide = automatique.
$eq('rareté « Auto » = null', $apply($p, ['personne' => ['album' => ['in' => false, 'rarity' => '', 'number' => null]]])['personne']['album']['rarity'], null);
$eq('rareté choisie', $apply($p, ['personne' => ['album' => ['in' => true, 'rarity' => 'legende', 'number' => '7']]])['personne']['album'], ['in' => true, 'rarity' => 'legende', 'number' => 7]);

// Ligne hors liste reprise (« E ») : gardée tant qu'on n'y touche pas.
$p = Fiches::get(5963);
$eq('ligne « E » gardée', $apply($p, ['personne' => ['line' => 'E']])['personne']['line'], 'E');
$eq('ligne changée', $apply($p, ['personne' => ['line' => 'D']])['personne']['line'], 'D');
$eq('ligne vidée', $apply($p, ['personne' => ['line' => '']])['personne']['line'], null);

// Champs vides : forme d'origine gardée, champs absents non ajoutés.
$p = Fiches::get(4673);
$new = $apply($p, ['personne' => ['first_match_coached' => '', 'aliases' => [], 'highlight_matches' => []]]);
$eq('null resté null', $new['personne']['first_match_coached'], null);
$eq('listes vides non ajoutées', [array_key_exists('aliases', $new['personne']), array_key_exists('highlight_matches', $new['personne'])], [false, false]);
$eq('enregistrement à blanc : fiche identique', $apply($p, ['personne' => ['first_match_coached' => '', 'aliases' => []]]), $p);

// Score repris « 1-1 (4-5 tab) » : gardé tel quel, refait seulement s'il change.
$m = Fiches::get(11936);
$s = $m['match']['score'];
$eq('prolongation lue « tab »', F::extraKind($s), 'tab');
$in = ['match' => ['score_home' => $s['home'], 'score_away' => $s['away'], 'extra' => 'tab', 'pens_home' => $s['pens']['home'], 'pens_away' => $s['pens']['away'], 'venue' => $m['match']['sochaux_home'] ? 'domicile' : 'exterieur']];
$new = $apply($m, $in)['match'];
$eq('tirs au but inchangés : score et résultat gardés', [$new['score'], $new['score_raw'], $new['result']], [$s, $m['match']['score_raw'], $m['match']['result']]);
$in['match']['pens_home'] = 5;
$in['match']['pens_away'] = 4;
$new = $apply($m, $in)['match'];
$eq('tirs au but modifiés : score refait', [$new['score_raw'], $new['score']['pens']], ['1-1 (5-4 tab)', ['home' => 5, 'away' => 4]]);
$in['match']['pens_home'] = $s['pens']['home'];
$in['match']['pens_away'] = $s['pens']['away'];
$in['match']['venue'] = $m['match']['sochaux_home'] ? 'exterieur' : 'domicile';
$new = $apply($m, $in)['match'];
$eq('domicile/extérieur inversé : résultat recalculé', $new['result'], $m['match']['result'] === 'V' ? 'D' : 'V');
$m = Fiches::get(6203);
$eq('prolongation « a.p » lue « ap »', F::extraKind($m['match']['score']), 'ap');
$s = $m['match']['score'];
$new = $apply($m, ['match' => ['score_home' => $s['home'], 'score_away' => $s['away'], 'extra' => 'ap', 'pens_home' => null, 'pens_away' => null]])['match'];
$eq('« a.p » inchangé gardé', [$new['score'], $new['score_raw']], [$s, $m['match']['score_raw']]);

// Saison d'un amical de fin juin rangé dans la saison suivante.
$m = Fiches::get(17479);
$eq('amical de juin : saison gardée si la date ne change pas', $apply($m, ['match' => ['date' => '1989-06-28']])['match']['season'], '1989-1990');
$new = $apply($m, ['match' => ['date' => '1989-06-27']])['match'];
$eq('amical de juin : jour corrigé, saison gardée', [$new['season'], $new['date_text']], ['1989-1990', 'Mardi 27 juin 1989']);
$eq('date changée de mois : saison recalculée', $apply($m, ['match' => ['date' => '1989-05-20']])['match']['season'], '1988-1989');
$m = Fiches::get(12891);
$eq('date en toutes lettres d’origine gardée (« Dimanche 4 aout 1991 »)', $apply($m, ['match' => ['date' => $m['match']['date']]])['match']['date_text'], $m['match']['date_text']);
// … sauf si elle contredit la date (en-tête resté d'un autre match) : remplacée, comme sur la page.
$m = Fiches::get(290);
$eq('date d’origine contredite (12 août pour le 13) : remplacée à l’enregistrement', $apply($m, ['match' => ['date' => '1994-08-13']])['match']['date_text'], 'Samedi 13 août 1994');

// Texte riche repris (intertitres h5) : gardé tant qu'il n'est pas modifié.
$a = Fiches::get(12899);
$sec = $a['sections'];
$in = ['sections' => array_map(fn ($x) => ['title' => (string) $x['title'], 'html' => str_replace("\n", "\r\n", (string) $x['html'])], $sec)];
$eq('sections inchangées gardées (h5 compris)', $apply($a, $in)['sections'], $sec);
$in['sections'][0]['html'] .= '<p>Ajout.</p>';
$new = $apply($a, $in)['sections'][0]['html'];
$eq('section modifiée : nettoyée (h5 → h4) avec l’ajout', [str_contains($new, '<h5'), str_contains($new, '<p>Ajout.</p>')], [false, true]);

// Date de la fiche saisie à la minute : secondes d'origine gardées.
$m = Fiches::get(2431);
$eq('date à la minute près : inchangée', $apply($m, ['date' => '2024-03-28T09:27'])['date'], $m['date']);
$eq('date modifiée', substr((string) $apply($m, ['date' => '2024-03-28T09:28'])['date'], 0, 16), '2024-03-28T09:28');

// Sous-titre d'article sur plusieurs lignes (bilans de saison).
$a = Fiches::get(16650);
$eq('sous-titre multiligne gardé', $apply($a, ['article' => ['subtitle' => $a['article']['subtitle']]])['article']['subtitle'], $a['article']['subtitle']);

// Images : chemin hors médiathèque refusé, image déjà enregistrée gardée même si son fichier manque.
$eq('chemin avec « .. » refusé', F::media('2024/../../config.php'), null);
$eq('image déjà enregistrée gardée', F::media('2099/01/absente.jpg', ['2099/01/absente.jpg']), '2099/01/absente.jpg');
$eq('image inconnue refusée', F::media('2099/01/absente.jpg'), null);

// 100 moments : planifiés pour leur semaine tant qu'elle n'est pas arrivée.
$mo = ['type' => 'moment', 'status' => 'publie', 'publish_at' => null, 'moment' => ['number' => 100]];
$start = strtotime((string) \App\Core\Settings::get('centenary.moments_start', '2026-06-11'));
$slot = strtotime(date('Y-m-d', strtotime('+693 days', $start)) . ' 08:00');
$new = Fiches::scheduleMoment($mo);
$eq('moment n° 100 « publié » avant sa semaine : planifié', [$new['status'], $new['publish_at']], $slot > time() ? ['planifie', date('c', $slot)] : ['publie', null]);
$mo['moment']['number'] = 1;
$mo['status'] = 'brouillon';
$eq('moment en brouillon : inchangé', Fiches::scheduleMoment($mo)['status'], 'brouillon');

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
