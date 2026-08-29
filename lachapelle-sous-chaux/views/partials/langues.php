<?php
/**
 * Sélecteur de langue. Chaque lien pointe vers la même page dans l'autre
 * langue, pas vers l'accueil : changer de langue ne doit pas faire perdre
 * sa place au visiteur.
 *
 * @var App\Core\Langues $langues
 * @var string $langue
 * @var App\Core\Seo $seo
 * @var string $cle
 * @var array|null $fiche
 */
$publiees = $langues->publiees();
if (count($publiees) < 2) {
    return;   // une seule langue : le sélecteur n'aurait rien à proposer
}
$cle   = $cle ?? 'accueil';
$fiche = $fiche ?? null;
?>
<div class="langues<?= isset($variante) ? ' langues--' . e($variante) : '' ?>">
  <span class="langues__nom" id="langues-nom-<?= e($variante ?? 'entete') ?>"><?= e(t('Langue')) ?></span>
  <ul aria-labelledby="langues-nom-<?= e($variante ?? 'entete') ?>">
    <?php foreach ($publiees as $code => $l): ?>
      <li>
        <a href="<?= e(url($seo->cheminEn($code, $cle, $fiche['slug'] ?? null))) ?>"
           lang="<?= e($code) ?>" hreflang="<?= e($code) ?>"
           <?= $code === $langue ? 'aria-current="true"' : '' ?>><?= e(strtoupper($code)) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
