<?php
/** Carnet : saisie rapide par saison. Variables : $c (carnet ou null), $all (saison => nb), $season, $matches, $mine */
use App\Core\View;
$mineSet = array_flip($mine);
?>
<?= View::partial('carnet/_head', ['title' => t('Saison {s}', ['s' => $season]), 'intro' => t('Cochez les matchs que vous avez vus au stade, puis enregistrez.'), 'crumb' => t('Mon carnet')]) ?>
<div class="wrap cnwrap">
  <form class="cnseason" method="get" action="<?= e(url('/carnet/saisons/')) ?>">
    <label for="cn-s"><?= e(t('Saison')) ?></label>
    <select id="cn-s" name="saison" data-cn-season><?php foreach ($all as $k => $n): ?><option value="<?= e($k) ?>" <?= $k === $season ? 'selected' : '' ?>><?= e($k) ?> (<?= (int) $n ?>)</option><?php endforeach; ?></select>
    <noscript><button class="btn btn--sm">OK</button></noscript>
    <a class="cnback" href="<?= e(url('/carnet/')) ?>">← <?= e(t('Mon carnet')) ?></a>
  </form>
  <?php if (!$c): ?>
    <section class="cncard"><h2 class="cncard__t"><?= e(t('Créez d’abord votre carnet')) ?></h2><?= View::partial('carnet/_email', ['mode' => 'creer', 'mid' => 0]) ?></section>
  <?php endif; ?>
  <form class="cnpick" data-cn-lot data-ids="<?= e(implode(',', array_column($matches, 'id'))) ?>">
    <div class="cnpick__tools"><button type="button" class="linkbtn" data-cn-all="1"><?= e(t('Tout cocher')) ?></button> · <button type="button" class="linkbtn" data-cn-all="0"><?= e(t('Tout décocher')) ?></button> · <label class="cncheck"><input type="checkbox" data-cn-home> <?= e(t('Domicile seulement')) ?></label></div>
    <ul class="cnpick__list">
      <?php foreach ($matches as $x): ?>
        <li data-home="<?= $x['sh'] ? 1 : 0 ?>"><label><input type="checkbox" value="<?= (int) $x['id'] ?>" <?= isset($mineSet[$x['id']]) ? 'checked' : '' ?> <?= $c ? '' : 'disabled' ?>>
          <span class="cnpick__d"><?= e(date_num((string) $x['date'])) ?></span>
          <span class="cnpick__m"><?= e($x['home'] . ' ' . $x['us'] . '–' . $x['them'] . ' ' . $x['away']) ?></span>
          <span class="cnpick__c"><?= e(implode(' ', array_unique(array_filter([(string) $x['label'], (string) $x['round']])))) ?></span>
          <span class="cnres cnres--<?= e(strtolower((string) $x['result'])) ?>"><?= e($x['result']) ?></span></label></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($c): ?><div class="cnpick__save"><button class="btn btn--yellow" type="submit"><?= e(t('Enregistrer dans mon carnet')) ?></button><p class="cnmail__msg" data-cn-msg role="status"></p></div><?php endif; ?>
  </form>
</div>
