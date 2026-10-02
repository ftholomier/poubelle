<?php
/** Mots qui défilent (« Le bon pro pour votre mariage / anniversaire… ») ; les lecteurs d'écran lisent la liste. @var string[] $words */
$words = array_values(array_filter(array_map('strval', $words ?? []), static fn ($w) => trim($w) !== ''));
$n = count($words);
if ($n === 0) {
    return;
}
?><span class="rot" aria-hidden="true"><?php foreach ($words as $w): ?><span><?= e($w) ?></span><?php endforeach; ?></span><span class="sr-only"><?= e($n > 1 ? implode(', ', array_slice($words, 0, -1)) . ' ou ' . $words[$n - 1] : $words[0]) ?></span>
