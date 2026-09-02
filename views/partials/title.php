<?php

use App\View;

/**
 * Titre à plusieurs lignes, alternant plein et contour.
 *
 * « outlineFrom » donne le rang de la première ligne tracée en contour :
 * c'est l'effet de titre à deux traitements des sites d'agence.
 *
 * @var list<string> $lines
 * @var string       $class
 * @var int|null     $outlineFrom
 */

$lines = (array) ($lines ?? []);
$outlineFrom = $outlineFrom ?? null;
?>
<?php foreach ($lines as $index => $line): ?>
    <span class="title__line<?= $outlineFrom !== null && $index >= $outlineFrom ? ' title__line--outline' : '' ?>"
          data-reveal="words"><?= View::e($line) ?></span>
<?php endforeach; ?>
