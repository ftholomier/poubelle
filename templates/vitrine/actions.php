<?php
/** Nos actions (rubrique). Variables : $p, $actions */
use App\Vitrine\Host;
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $p['title'], 'lead' => $p['lead'], 'eyebrow' => 'Sochaux Rétro', 'crumbs' => [[$p['title'], Host::url('/nos-actions/')]]]) ?>
<section class="section">
  <div class="wrap">
    <div class="vactions">
      <?php foreach ($actions as $a): ?><?= \App\Core\View::partial('vitrine/partials/action-card', ['a' => $a]) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<section class="vcta-band">
  <div class="wrap vcta-band__in">
    <div><h2 class="h-2">Une idée d’action ?</h2><p>Exposition, rencontre, projet avec une école ou une maison de retraite : parlons-en.</p></div>
    <a class="btn btn--navy" href="<?= e(Host::url('/contact/')) ?>">Nous écrire</a>
  </div>
</section>
