<?php
/**
 * Page commune des quatre murs de photos : en-tête, filtres (décennie, photographe), nouveau
 * tirage, liens vers les autres murs, puis le mur lui-même (refait sans recharger la page).
 * Un choix sans photo avec l'autre filtre (une décennie où ce photographe n'a rien) est grisé.
 * Bouton « Télécharger en PDF » (planche-contact, journal, mosaïque) : il envoie le formulaire caché
 * du mur ($pdfForm), refait avec chaque tirage.
 * Variables : $kind, $wall, $filters, $decades, $photographers, $counts, $others, $count, $pdfForm, + celles du mur
 */
use App\Core\View;
use App\Front\Walls;

[$path, $name, $intro, $again] = $wall;
$motifs = $motifs ?? null;
?>
<section class="mhead whead">
  <div class="wrap mhead__inner">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/interactif/')) ?>"><?= e(t('Interactif')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t($name)) ?></span></nav>
    <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Les murs de photos')) ?></span>
    <h1 class="mhead__title whead__title"><?= e(t($name)) ?></h1>
    <p class="mhead__intro"><?= e(t($intro)) ?> <?= e(sprintf(t('Un nouveau tirage à chaque visite, parmi %s photos créditées de la médiathèque.'), number_format($count, 0, ',', ' '))) ?></p>
  </div>
</section>

<form class="wtools" method="get" action="<?= e(url($path)) ?>" data-wall-form>
  <div class="wrap wtools__in">
    <label class="wtools__f"><span><?= e(t('Décennie')) ?></span>
      <select name="decennie" data-wall-filter>
        <option value=""><?= e(t('Toutes')) ?></option>
        <?php foreach ($decades as $d => $n): $n = (int) ($counts['decades'][$d] ?? 0); $label = sprintf(t('Années %d'), $d); ?>
          <option value="<?= (int) $d ?>" data-label="<?= e($label) ?>"<?= $filters['decade'] === $d ? ' selected' : ($n ? '' : ' disabled') ?>><?= e($label) ?> (<?= $n ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="wtools__f"><span><?= e(t('Photographe ou source')) ?></span>
      <select name="photographe" data-wall-filter>
        <option value=""><?= e(t('Tous')) ?></option>
        <?php foreach ($photographers as $p): $n = (int) ($counts['who'][$p['key']] ?? 0); ?>
          <option value="<?= e($p['key']) ?>" data-label="<?= e($p['name']) ?>"<?= $filters['who'] === $p['key'] ? ' selected' : ($n ? '' : ' disabled') ?>><?= e($p['name']) ?> (<?= $n ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if ($motifs): ?>
    <label class="wtools__f"><span><?= e(t('Motif')) ?></span>
      <select name="motif" data-wall-filter>
        <?php foreach ($motifs as $k => $label): ?><option value="<?= e((string) $k) ?>"<?= (string) $motif === (string) $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>
    <button type="submit" class="btn btn--navy wtools__again" data-wall-again><span aria-hidden="true">↻</span> <?= e(t($again)) ?></button>
    <?php if (isset(Walls::PDF[$kind])): ?>
      <button type="submit" form="wall-pdf" class="btn btn--ghost wtools__pdf pdfbtn" data-wall-pdf<?= $pdfForm === '' ? ' disabled' : '' ?>><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true"><path d="M12 3v12M6.5 10 12 15.5 17.5 10M4 20h16"/></svg><span><?= e(t('Télécharger en PDF')) ?></span></button>
    <?php endif; ?>
    <nav class="wtools__others" aria-label="<?= e(t('Les autres murs de photos')) ?>">
      <?php foreach ($others as $o): ?><a href="<?= e(url($o[0])) ?>"><?= e(t($o[1])) ?></a><?php endforeach; ?>
    </nav>
  </div>
</form>

<div class="wall wall--<?= e($kind) ?>" data-wall="<?= e($kind) ?>" aria-live="polite">
  <?= View::partial('interactif/murs/' . $kind, get_defined_vars()) ?><?= $pdfForm ?>
</div>
