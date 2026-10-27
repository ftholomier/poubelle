<?php
/** @var string $on  onglet actif : posts|linkedin */
$cur = static fn(string $tab) => $on === $tab ? ' class="is-on" aria-current="page"' : '';
$liCode = LD\Posts::linkedinCode() !== '';
?>
<nav class="tabs tabs--section" aria-label="Blog">
  <a href="/admin/blog"<?= $cur('posts') ?>>Articles <small><?= count(LD\Posts::all()) ?></small></a>
  <a href="/admin/blog/ia"<?= $cur('ai') ?>>✨ Écrire avec l’IA</a>
  <a href="/admin/blog/linkedin"<?= $cur('linkedin') ?>>Posts LinkedIn <small><?= !$liCode ? 'aucun' : (LD\Posts::linkedinPlace() !== '' ? 'en ligne' : 'masqué') ?></small></a>
</nav>
