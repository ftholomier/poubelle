<?php
/**
 * Moteur PDF et images de partage : caractères absents des polices (emoji, lettres stylisées des
 * tweets, symboles des compositions), citations posées dans une liste.
 * Usage : php tests/pdf.php (code 1 si échec).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Front\PdfExport;
use App\Pdf\HtmlFlow;
use App\Pdf\Layout;
use App\Pdf\TrueType;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$font = fn () => new TrueType(APP_DIR . '/Resources/fonts/Newsreader-Regular.ttf');

// 1. Caractères absents de la police
$f = $font();
$eq('lettre stylisée « 𝗣 » imprimée « P »', $f->glyphs('𝗣𝗮𝘁𝗿𝗶𝗰𝗸'), $f->glyphs('Patrick'));
$eq('« ᵉ » imprimé « e »', $f->glyphs('XIXᵉ'), $f->glyphs('XIXe'));
$eq('lettre accentuée présente : inchangée', count($f->glyphs('é')) === 1 && $f->glyphs('é') !== $f->glyphs('e'), true);
foreach (['🟨', '🟥', '⚽', "⚽\u{FE0F}", '🇫🇷', '🦁', '💛💙', '↑', '↓', '🔻'] as $s) {
    $eq("« $s » ignoré (pas de « ? »)", $f->glyphs($s), []);
}
$eq('chiffre emoji « 1️⃣ » imprimé « 1 »', $f->glyphs("1\u{FE0F}\u{20E3}"), $f->glyphs('1'));
$eq('séparateur de ligne U+2028 : une espace', $f->glyphs("a\u{2028}b"), $f->glyphs('a b'));
$eq('écriture absente de la police (漢) : « ? »', $f->glyphs('漢'), $f->glyphs('?'));
$eq('texte d’un tweet', $f->glyphs("📽️⚽️🔥 Le but"), $f->glyphs(' Le but'));

// 2. Texte copiable du PDF : chaque glyphe garde le caractère réellement imprimé
$f = $font();
$f->glyphs('🟨漢𝗣');
[$q] = $f->glyphs('?');
[$p] = $f->glyphs('P');
$eq('le glyphe « ? » se copie « ? » (et non « 漢 »)', $f->used[$q] ?? null, 0x3F);
$eq('le glyphe « P » se copie « P » (et non « 𝗣 »)', $f->used[$p] ?? null, 0x50);

// 3. Compositions : symboles saisis → écriture du back-office
$marks = new ReflectionMethod(PdfExport::class, 'marks');
foreach ([
    "⚽ 73'" => "73'", "⚽\u{FE0F}⚽\u{FE0F} 12' 78'" => "12' 78'", "⚽ 45'+2 s.p." => "45'+2 s.p.",
    "↑ 59' ↓ 66'" => "Entrée 59' Sortie 66'", "↓ 60'↑ 70'" => "Sortie 60' Entrée 70'", "🔺 46'" => "Entrée 46'", "🔻 63'" => "Sortie 63'",
    "🟨 45'+2 🟥 55'" => "J 45'+2 R 55'", "🟨 66'" => "J 66'",
    "Sortie 80'" => "Sortie 80'", "J 20' R 80'" => "J 20' R 80'", '' => '',
] as $in => $out) {
    $eq("composition « $in »", $marks->invoke(null, $in), $out);
}
$fonts = new ReflectionProperty(Layout::class, 'fonts');
$usedChars = function (Layout $l, string $key) use ($fonts): string {
    return implode('', array_map('mb_chr', array_values($fonts->getValue($l)[$key]->used)));
};

// 4. Citation (tweet) posée par l'ancien site entre deux éléments d'une liste : imprimée
$l = new Layout();
(new HtmlFlow($l, fn () => null))->render('<ul><li>Un</li><blockquote><p>Zut</p></blockquote><li>Deux</li></ul>');
$eq('citation dans une liste imprimée (en italique)', str_contains($usedChars($l, 'serif-i'), 'Z'), true);
$eq('éléments de la liste imprimés', str_contains($usedChars($l, 'serif'), 'U') && str_contains($usedChars($l, 'serif'), 'D'), true);
$l = new Layout();
(new HtmlFlow($l, fn () => null))->render('<ol><li>Un</li><blockquote><p>Zut</p></blockquote><li>Deux</li></ol>');
$eq('liste numérotée coupée par une citation : la numérotation continue (« 2. »)', str_contains($usedChars($l, 'display-b'), '2'), true);

// 5. Images de partage (GD) : même règle, sans carré vide à la place d'un emoji
$printable = new ReflectionMethod(App\Front\Share::class, 'printable');
foreach ([
    'JOYEUX ANNIVERSAIRE 𝗣𝗮𝘁𝗿𝗶𝗰𝗸 🦁' => 'JOYEUX ANNIVERSAIRE PATRICK', 'Bravo 𝗣𝗮𝘁𝗿𝗶𝗰𝗸 ⚽️ !' => 'Bravo Patrick !',
    '1️⃣ DERNIER MATCH 🇫🇷' => '1 DERNIER MATCH', 'SOCHAUX × METZ' => 'SOCHAUX × METZ', 'NOS LIONS · ATTAQUANT' => 'NOS LIONS · ATTAQUANT',
] as $in => $out) {
    $eq("image de partage « $in »", $printable->invoke(null, $in), $out);
}

echo $fail ? "$fail échec(s)\n" : "Tout est bon\n";
exit($fail ? 1 : 0);
