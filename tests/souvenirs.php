<?php
/**
 * Kit souvenirs (App\Services\Souvenirs, App\Front\Kit) et QR code (App\Services\Qr) : match du
 * mois, visages, quiz, témoignages « Ils y étaient », PDF de 4 pages, structure du QR code.
 * Usage : php tests/souvenirs.php (code de sortie 1 en cas d'échec). N'écrit que dans un
 * dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Front\Kit;
use App\Services\Qr;
use App\Services\Souvenirs as S;

$tmp = sys_get_temp_dir() . '/souvenirs-test-' . bin2hex(random_bytes(4));
S::$inbox = "$tmp/inbox";
S::$index = "$tmp/inbox/temoignages.json";
S::$choices = [];
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Mois et match du mois.
$eq('mois valides', [S::validMonth('2026-10'), S::validMonth('2026-13'), S::validMonth('26-10'), S::validMonth('2026-10-01')], [true, false, false, false]);
$eq('mois en toutes lettres', S::monthLabel('2026-10'), 'octobre 2026');
$c = S::candidates('2026-10', 6);
$sc = array_column($c, 'score');
$sorted = $sc;
rsort($sorted);
$eq('candidats : joués en octobre, avant 2026, du plus au moins marquant', count($c) === 6
    && !array_filter($c, fn ($x) => substr((string) $x['dm']['date'], 5, 2) !== '10' || (int) substr((string) $x['dm']['date'], 0, 4) >= 2026)
    && $sc === $sorted, true);
$eq('match du mois : le plus marquant par défaut', S::match('2026-10')['id'], $c[0]['id']);
S::$choices = ['2026-10' => ['match' => $c[3]['id'], 'intro' => 'Un mot des historiens.']];
$eq('match choisi par les historiens', [S::match('2026-10')['id'], S::match('2026-10')['chosen'] ?? false], [$c[3]['id'], true]);
S::$choices = ['2026-10' => ['match' => 999999999]];
$eq('choix invalide : retour au choix automatique', S::match('2026-10')['id'], $c[0]['id']);
S::$choices = ['2026-10' => ['intro' => 'Un mot des historiens.']];

// Contenu du kit.
$k = S::kit('2026-10');
$eq('kit : match, mot d’introduction, récit', [$k['match']['id'], $k['intro'], count($k['story']) > 0 && count($k['story']) <= 8], [$c[0]['id'], 'Un mot des historiens.', true]);
$ids = array_column($k['faces'], 'id');
$eq('visages : six joueurs différents, avec photo', [count($k['faces']), count(array_unique($ids)), (bool) array_filter($k['faces'], fn ($f) => empty($f['image']))], [6, 6, false]);
$eq('visages : le même ordre pour tout le mois', array_column(S::kit('2026-10')['faces'], 'id'), $ids);
$okQuiz = count($k['quiz']) === 6;
foreach ($k['quiz'] as $q) {
    $okQuiz = $okQuiz && count($q['a']) >= 2 && count($q['a']) <= 3 && isset($q['a'][$q['c']]) && count(array_unique($q['a'])) === count($q['a']);
}
$eq('quiz : 6 questions, 2 ou 3 réponses distinctes, la bonne comprise', $okQuiz, true);
$eq('quiz : le premier buteur du match', [str_contains($k['quiz'][0]['q'], 'premier but'), $k['quiz'][0]['a'][$k['quiz'][0]['c']]], [true, 'Pamic']);
$eq('« Racontez-nous » : 4 questions, adresse courte', [count($k['prompts']), (bool) preg_match('#/souvenir/' . $k['match']['id'] . '/$#', $k['contribute'])], [4, true]);

// PDF.
$pdf = Kit::build($k);
$eq('PDF de 4 pages', [substr($pdf, 0, 5), preg_match_all('#/Type\s*/Page[^s]#', $pdf)], ['%PDF-', 4]);

// « Ils y étaient » : seuls les témoignages validés, publiés et rattachés à une fiche.
$write = function (string $ticket, array $c) use ($tmp) {
    @mkdir("$tmp/inbox/contributions/$ticket", 0775, true);
    file_put_contents("$tmp/inbox/contributions/$ticket/contribution.json", json_encode($c + ['ticket' => $ticket, 'type' => 'temoignage', 'name' => 'Jean-Pierre Martin', 'at' => '2026-10-01T10:00:00+02:00']));
};
$write('SR-2026-0101', ['status' => 'valide', 'public' => true, 'fiche_id' => 2431, 'description' => 'La neige à Bonal.', 'handled' => ['at' => '2026-10-02T10:00:00+02:00']]);
$write('SR-2026-0102', ['status' => 'valide', 'public' => true, 'fiche_id' => 2431, 'description' => 'Texte d’origine.', 'public_text' => 'Texte relu.', 'public_name' => 'Marie', 'handled' => ['at' => '2026-10-03T10:00:00+02:00']]);
$write('SR-2026-0103', ['status' => 'nouveau', 'public' => true, 'fiche_id' => 2431, 'description' => 'Pas encore validé.']);
$write('SR-2026-0104', ['status' => 'valide', 'public' => false, 'fiche_id' => 2431, 'description' => 'Non publié.']);
$write('SR-2026-0105', ['status' => 'valide', 'public' => true, 'description' => 'Sans fiche.']);
$eq('index : 2 témoignages publiés', S::reindex(), 2);
$t = S::testimonies(2431);
$eq('témoignages de la fiche, dans l’ordre, signature et texte relus', array_map(fn ($x) => [$x['name'], $x['text']], $t), [['Jean-Pierre M.', 'La neige à Bonal.'], ['Marie', 'Texte relu.']]);
$eq('autre fiche : aucun', S::testimonies(3851), []);
$eq('signature courte', [S::shortName('Jean-Pierre Martin'), S::shortName('Marie'), S::shortName('Anne de la Tour')], ['Jean-Pierre M.', 'Marie', 'Anne de la T.']);

// QR code.
$m = Qr::matrix('https://fcsochauxretro.com/souvenir/3851/');
$n = count($m);
$finder = fn (int $y, int $x) => $m[$y][$x] && $m[$y][$x + 6] && $m[$y + 6][$x] && $m[$y + 6][$x + 6] && $m[$y + 3][$x + 3] && !$m[$y + 1][$x + 1];
$eq('QR : version 3 (29 modules), carrés de repérage, module sombre', [$n, $finder(0, 0), $finder(0, $n - 7), $finder($n - 7, 0), $m[$n - 8][8]], [29, true, true, true, true]);
$eq('QR : toujours le même dessin', Qr::matrix('https://fcsochauxretro.com/souvenir/3851/'), $m);
$eq('QR : SVG', str_starts_with(Qr::svg('Sochaux'), '<svg') && str_contains(Qr::svg('Sochaux'), '<path d="M'), true);
$big = Qr::matrix(str_repeat('x', 213));
$eq('QR : 213 octets au plus (version 10)', count($big), 57);
$err = null;
try {
    Qr::matrix(str_repeat('x', 214));
} catch (\InvalidArgumentException $e) {
    $err = 'trop long';
}
$eq('QR : texte trop long refusé', $err, 'trop long');

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
