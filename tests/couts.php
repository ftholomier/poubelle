<?php
/**
 * Coûts de l'IA (App\Services\AiCosts, écran Système › Coûts IA) : barème daté, calcul du coût,
 * cumuls, niveau gratuit, budget, remboursements, relevé PDF et détail CSV.
 * Usage : php tests/couts.php (code de sortie 1 en cas d'échec). N'écrit que dans un dossier
 * temporaire ; les réglages sont simulés en mémoire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\Costs;
use App\Core\Request;
use App\Core\Settings;
use App\Services\AiCosts as C;

$tmp = sys_get_temp_dir() . '/couts-test-' . bin2hex(random_bytes(4));
C::$dir = $tmp;
$settings = new ReflectionProperty(Settings::class, 'values');
$set = fn (array $v) => $settings->setValue(null, $v);
$set(['couts.eur_rate' => 0.9]);
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = is_float($exp) ? abs((float) $got - $exp) < 1e-9 : $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Barème : début d'identifiant le plus long, ligne datée la plus récente en vigueur.
$p = fn (string $m, ?string $d = null) => [C::price($m, $d)['prefix'], C::price($m, $d)['in'], C::price($m, $d)['out']];
$eq('3.1 Flash-Lite', $p('gemini-3.1-flash-lite'), ['gemini-3.1-flash-lite', 0.25, 1.5]);
$eq('version datée du modèle', $p('models/gemini-3.1-flash-lite-preview-09-2026'), ['gemini-3.1-flash-lite', 0.25, 1.5]);
$eq('Flash-Lite avant Flash', $p('gemini-2.5-flash-lite'), ['gemini-2.5-flash-lite', 0.1, 0.4]);
$eq('2.5 Flash', $p('gemini-2.5-flash'), ['gemini-2.5-flash', 0.3, 2.5]);
$eq('tarif de lancement', $p('gemini-3.8-flash', '2026-12-31'), ['gemini-3.8-flash', 0.75, 3.75]);
$eq('hausse au 1er janvier', $p('gemini-3.8-flash', '2027-01-01'), ['gemini-3.8-flash', 1.5, 7.5]);
$eq('modèle inconnu : tarif par défaut', [C::price('mistral-large')['known'], C::price('mistral-large')['in']], [false, 0.5]);

// Coût : entrée hors cache, entrée lue dans le cache, sortie et réflexion au tarif de sortie.
$lite = C::price('gemini-3.1-flash-lite');
$eq('coût d’un million de jetons', C::cost(['in' => 1_000_000, 'cached' => 200_000, 'out' => 100_000, 'think' => 50_000], $lite), 0.43);
$eq('jetons de réflexion facturés', C::cost(['in' => 0, 'out' => 0, 'think' => 1_000_000], $lite), 1.5);
$eq('lecture de usageMetadata', C::usage(['usageMetadata' => ['promptTokenCount' => 1200, 'cachedContentTokenCount' => 200, 'candidatesTokenCount' => 300, 'thoughtsTokenCount' => 90, 'toolUsePromptTokenCount' => 10]]), ['in' => 1210, 'cached' => 200, 'out' => 300, 'think' => 90, 'est' => false, 'search' => 0]);
$eq('recherches Google comptées', C::usage(['candidates' => [['groundingMetadata' => ['webSearchQueries' => ['a', 'b', 'c']]]]])['search'], 3);
$eq('réponse sans usage', C::usage([])['in'], 0);

// Montants lisibles.
$eq('zéro', C::fmt(0.0), "0\u{a0}€");
$eq('fraction de centime', C::fmt(0.0005), "0,050\u{a0}centime");
$eq('demi-centime', C::fmt(0.005), "0,50\u{a0}centime");
$eq('centimes', C::fmt(0.123), "0,123\u{a0}€");
$eq('euros', C::fmt(3.4231), "3,42\u{a0}€");
$eq('milliers', C::fmt(1234.5), "1\u{a0}234,50\u{a0}€");
$eq('conversion au taux réglé', C::eur(10.0), 9.0);

// Enregistrement des appels et cumuls.
$ym = date('Y-m');
C::record('correcteur', 'gemini-3.1-flash-lite', ['in' => 4000, 'out' => 500, 'think' => 300], 'fiche:22054');
C::record('assistant', 'gemini-3.1-flash-lite', ['in' => 6000, 'cached' => 2000, 'out' => 400]);
C::record('index', 'gemini-embedding-001', ['in' => 10000, 'est' => true]);
C::record('traduction', 'modele-inconnu', ['in' => 1000, 'out' => 1000]);
C::record('assistant', 'gemini-3.1-flash-lite', ['in' => 0, 'out' => 0]); // rien de facturé : ignoré
C::record('nimporte', 'gemini-3.1-flash-lite', ['in' => 100]);
$m = C::month($ym);
$expected = (4000 * 0.25 + 800 * 1.5 + 4000 * 0.25 + 2000 * 0.025 + 400 * 1.5 + 10000 * 0.15 + 1000 * 0.5 + 1000 * 3 + 100 * 0.25) / 1e6;
$eq('appels du mois', $m['calls'], 5);
$eq('coût du mois', round($m['usd'], 7), round($expected, 7));
$eq('cumul du jour', $m['days'][date('d')]['calls'], 5);
$eq('par usage', array_keys($m['uses']), ['correcteur', 'assistant', 'index', 'traduction', 'autre']);
$eq('jetons de réflexion cumulés', $m['th'], 300);
$eq('par modèle', array_keys($m['models']), ['gemini-3.1-flash-lite', 'gemini-embedding-001', 'modele-inconnu']);
$lines = C::lines($ym);
$eq('détail des appels', count($lines), 5);
$eq('fiche concernée', $lines[0]['r'], 'fiche:22054');
$eq('demandé par', $lines[0]['u'], 'Tâche automatique');
$eq('jetons estimés signalés', $lines[2]['e'] ?? null, 1);
$eq('modèle absent du barème signalé', $lines[3]['x'] ?? null, 1);
$eq('usage inconnu rangé dans « Autre »', $lines[4]['f'], 'autre');
$eq('coût de la requête en cours', [C::request()['calls'], round(C::request()['usd'], 7)], [5, round($expected, 7)]);
$eq('derniers appels seulement', array_column(C::lines($ym, 2), 'f'), ['traduction', 'autre']);
C::$ref = 'fiche:10148';
C::record('traduction', 'gemini-3.1-flash-lite', ['in' => 10, 'out' => 10]);
C::$ref = '';
$eq('fiche posée par l’appelant', C::lines($ym, 1)[0]['r'], 'fiche:10148');

// Fin d'un gros fichier sans le lire en entier.
$big = "$tmp/2001-01.jsonl";
$fp = fopen($big, 'w');
for ($i = 1; $i <= 3000; $i++) {
    fwrite($fp, json_encode(['at' => '2001-01-01T00:00:00+00:00', 'f' => 'assistant', 'm' => 'x', 'in' => $i, 'c' => 0, 'out' => 0, 'th' => 0, 'usd' => 0]) . "\n");
}
fclose($fp);
$tail = C::lines('2001-01', 30);
$eq('30 dernières lignes', [count($tail), $tail[0]['in'], $tail[29]['in']], [30, 2971, 3000]);
$eq('mois invalide', C::lines('../x'), []);

// Niveau gratuit : compté à 0 €, coût évité gardé à part.
$set(['couts.eur_rate' => 0.9, 'couts.free_tier' => true]);
$before = C::month($ym)['usd'];
C::record('assistant', 'gemini-3.1-flash-lite', ['in' => 1_000_000]);
$last = C::lines($ym, 1)[0];
$eq('gratuit : rien de facturé', [$last['usd'], $last['g'], round(C::month($ym)['usd'], 7)], [0, 0.25, round($before, 7)]);

// Budget du mois et mise en pause.
$spent = C::eur(C::month($ym)['usd']);
$set(['couts.eur_rate' => 0.9, 'couts.monthly_budget' => 0]);
$eq('sans budget : jamais en pause', [C::budget()['over'], C::paused('correcteur')], [false, false]);
$set(['couts.eur_rate' => 0.9, 'couts.monthly_budget' => $spent / 2]);
$eq('budget dépassé', [C::budget()['over'], C::budget()['pct']], [true, 200]);
$eq('tâches en pause, assistant non', [C::paused('correcteur'), C::paused('traduction'), C::paused('assistant')], [true, true, false]);
$set(['couts.eur_rate' => 0.9, 'couts.monthly_budget' => $spent / 2, 'couts.pause_assistant' => true, 'couts.pause_tasks' => false]);
$eq('assistant en pause, tâches non', [C::paused('correcteur'), C::paused('assistant')], [false, true]);
$set(['couts.eur_rate' => 0.9]);

// Remboursements : mois terminés et mois en cours.
\App\Core\JsonStore::update("$tmp/totaux.json", function ($t) {
    $t['2026-08'] = ['calls' => 120, 'usd' => 10.0, 'in' => 900000, 'out' => 90000, 'th' => 10000, 'days' => ['14' => ['calls' => 120, 'usd' => 10.0, 'in' => 900000, 'out' => 90000, 'th' => 10000]], 'uses' => ['assistant' => ['calls' => 120, 'usd' => 10.0, 'in' => 900000, 'out' => 90000, 'th' => 10000]], 'models' => ['gemini-3.1-flash-lite' => ['calls' => 120, 'usd' => 10.0, 'in' => 900000, 'out' => 90000, 'th' => 10000]]];
    ksort($t);
    return $t;
}, []);
$due = C::toReimburse(false);
$eq('à rembourser (mois terminés)', [$due['months'], round($due['eur'], 2)], [['2026-08'], 9.0]);
$eq('mois en cours compris', C::toReimburse()['months'], ['2026-08', $ym]);
C::markReimbursed('2026-08', 9.0, 'virement du 3 septembre', ['name' => 'Trésorier']);
$eq('remboursement noté', [C::toReimburse(false)['months'], C::reimbursements()['2026-08']['eur'], C::reimbursements()['2026-08']['by']], [[], 9.0, 'Trésorier']);
C::unmarkReimbursed('2026-08');
$eq('remboursement annulé', C::toReimburse(false)['months'], ['2026-08']);

// Barème modifié dans le back-office, puis tarifs de Google rétablis.
$n = C::savePrices([
    ['prefix' => 'gemini-3.1-flash-lite', 'in' => '0,30', 'out' => '1,8', 'cached' => '0.03', 'from' => ''],
    ['prefix' => 'gemini-3.1-flash-lite', 'in' => '0,40', 'out' => '2', 'cached' => '0', 'from' => '2027-03-01'],
    ['prefix' => 'pas un modèle !', 'in' => 1, 'out' => 1],
    ['prefix' => 'gemini-x', 'in' => '-3', 'out' => 'abc', 'from' => 'demain'],
]);
$eq('lignes invalides ignorées', $n, 3);
$eq('nouveau tarif', $p('gemini-3.1-flash-lite', '2026-10-03'), ['gemini-3.1-flash-lite', 0.3, 1.8]);
$eq('hausse programmée', $p('gemini-3.1-flash-lite', '2027-03-01'), ['gemini-3.1-flash-lite', 0.4, 2.0]);
$eq('valeurs négatives ramenées à 0', [C::price('gemini-x')['in'], C::price('gemini-x')['out'], C::price('gemini-x')['from']], [0.0, 0.0, '']);
$eq('barème personnalisé', C::customPrices(), true);
C::resetPrices();
$eq('tarifs de Google rétablis', [C::customPrices(), $p('gemini-3.1-flash-lite')], [false, ['gemini-3.1-flash-lite', 0.25, 1.5]]);

// Écran Coûts IA, relevé PDF et détail CSV.
$live = Costs::live();
$eq('écran : appels du mois', $live['month']['calls'], '7 appels');
$eq('écran : derniers appels, plus récent en tête', [count($live['recent']), $live['recent'][0]['use']], [7, 'Assistant du site']);
$eq('écran : lien vers la fiche', $live['recent'][1]['refUrl'], '/admin/fiche/10148');
$eq('écran : à rembourser', $live['due']['has'], true);
$req = new Request('GET', '/', [], [], [], [], '');
$pdf = Costs::statement($req, '2026-08.pdf');
$eq('relevé PDF', [$pdf?->headers['Content-Type'], substr((string) $pdf?->body, 0, 5)], ['application/pdf', '%PDF-']);
$eq('relevé du mois en cours', substr(Costs::statementPdf($ym), 0, 5), '%PDF-');
$eq('relevé d’un mois inconnu', Costs::statement($req, '2020-01.pdf'), null);
$eq('relevé : adresse invalide', Costs::statement($req, '../../settings.json'), null);
$csv = Costs::csv($req, "$ym.csv");
$rows = explode("\r\n", trim(substr((string) $csv?->body, 3)));
$eq('CSV : BOM pour Excel', substr((string) $csv?->body, 0, 3), "\xEF\xBB\xBF");
$eq('CSV : en-tête', explode(';', $rows[0])[0], 'Date');
$eq('CSV : une ligne par appel', count($rows), 1 + 7);
$eq('CSV : remarques', [str_contains($rows[3], 'jetons estimés'), str_contains($rows[4], 'tarif par défaut'), str_contains($rows[7], 'niveau gratuit')], [true, true, true]);

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
