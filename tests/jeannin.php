<?php
/**
 * Orthographe « Jeannin » (DataFixes::jeannin, ticket du 04/11/2026) : « Jeanin » corrigé partout
 * (compositions, textes, légendes), bonne graphie, prénom, « Jeanine », adresses et noms de fichiers
 * jamais touchés. Rien n'est écrit. Usage : php tests/jeannin.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\DataFixes as D;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

$eq('texte, composition, capitales', [D::jeannin('Medhi Jeanin s’interpose'), D::jeannin('JEANIN Medhi'), D::jeannin('mais Jeanin repousse.'), D::jeannin('(Jeanin)')], ['Medhi Jeannin s’interpose', 'JEANNIN Medhi', 'mais Jeannin repousse.', '(Jeannin)']);
$eq('bonne graphie et prénom inchangés', [D::jeannin('Medhi Jeannin'), D::jeannin('Mehdi Jeannin'), D::jeannin('JEANNIN')], ['Medhi Jeannin', 'Mehdi Jeannin', 'JEANNIN']);
$eq('autres mots inchangés', [D::jeannin('Jeanine Dupont'), D::jeannin('Jeannine Perrin')], ['Jeanine Dupont', 'Jeannine Perrin']);
$eq('noms de fichiers et adresses inchangés', [D::jeannin('2024/08/jeanin-23-24.jpeg'), D::jeannin('jeanin.jpg'), D::jeannin('/joueurs/jeanin/')], ['2024/08/jeanin-23-24.jpeg', 'jeanin.jpg', '/joueurs/jeanin/']);

// Fiche entière : textes corrigés, adresse, image et ancien contenu jamais touchés.
$doc = ['id' => 1, 'path' => '/matchs/x-jeanin/', 'featured_image' => '2024/08/jeanin-23-24.jpeg', 'legacy' => ['html' => 'Jeanin'],
    'match' => ['lineup' => ['rows' => [['position' => 'G', 'name' => 'JEANIN Medhi']]], 'highlights' => [['minute' => '6', 'text' => 'Jeanin sort au-devant.']]],
    'gallery' => [['image' => '2024/08/jeanin-23-24.jpeg', 'caption' => 'Medhi Jeanin – FC Zurich']]];
$new = (new ReflectionMethod(D::class, 'mapText'))->invoke(null, $doc, fn ($s) => D::jeannin($s));
$eq('fiche : composition, temps fort, légende corrigés', [$new['match']['lineup']['rows'][0]['name'], $new['match']['highlights'][0]['text'], $new['gallery'][0]['caption']], ['JEANNIN Medhi', 'Jeannin sort au-devant.', 'Medhi Jeannin – FC Zurich']);
$eq('fiche : adresse, image, ancien contenu inchangés', [$new['path'], $new['featured_image'], $new['gallery'][0]['image'], $new['legacy']['html']], ['/matchs/x-jeanin/', '2024/08/jeanin-23-24.jpeg', '2024/08/jeanin-23-24.jpeg', 'Jeanin']);

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
