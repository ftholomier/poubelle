<?php
/** Statuts et documents. Variables : $p, $docs */
use App\Admin\Base;
use App\Vitrine\Documents;
use App\Vitrine\Host;
use App\Vitrine\Pages;
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $p['title'], 'lead' => $p['lead'], 'eyebrow' => 'L’association', 'crumbs' => [['L’association', Host::url('/association/')], [$p['title'], Host::url('/association/statuts-et-documents/')]]]) ?>

<section class="section">
  <div class="wrap vcols">
    <div class="vcols__main">
      <?php if ($docs): ?>
        <ul class="vdocs">
          <?php foreach ($docs as $d): $ext = strtoupper(pathinfo((string) $d['file'], PATHINFO_EXTENSION)); ?>
            <li data-reveal><a class="vdoc" href="<?= e(Documents::url((string) $d['file'])) ?>" target="_blank" rel="noopener">
              <span class="vdoc__ext"><?= e($ext) ?></span>
              <span class="vdoc__body"><b><?= e($d['title']) ?></b><?php if (!empty($d['text'])): ?><span><?= e($d['text']) ?></span><?php endif; ?><small><?= !empty($d['date']) ? e(date_fr((string) $d['date'])) . ' · ' : '' ?><?= e(Base::size(Documents::size((string) $d['file']))) ?></small></span>
              <span class="vdoc__go" aria-hidden="true">↓</span>
            </a></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="prose vnote"><?= Pages::rich($p['empty_text'] ?? '') ?></div>
        <p class="mt-20"><a class="btn btn--navy" href="<?= e(Host::url('/contact/?objet=documents')) ?>">Demander les statuts</a></p>
      <?php endif; ?>
    </div>
    <aside class="vcols__side">
      <div class="vbox"><h2 class="h-3">Fonctionnement</h2><div class="prose mt-20" style="font-size:17px"><?= Pages::rich($p['governance'] ?? '') ?></div></div>
    </aside>
  </div>
</section>
