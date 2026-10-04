<?php
/** Espace presse. Variables : $p, $articles, $kit (bool), $kitSize, $figures */
use App\Admin\Base;
use App\Vitrine\Host;
use App\Vitrine\Pages;
use App\Vitrine\Site;

$email = Site::email();
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $p['title'], 'lead' => $p['lead'], 'eyebrow' => 'Médias', 'crumbs' => [[$p['title'], Host::url('/presse/')]]]) ?>
<section class="section">
  <div class="wrap vcols">
    <div class="vcols__main">
      <h2 class="h-2"><?= e($p['about_title'] ?? '') ?></h2>
      <div class="prose mt-20"><?= Pages::rich($p['about'] ?? '') ?></div>
      <h2 class="h-2" style="margin-top:56px">Kit média</h2>
      <ul class="vdocs mt-20">
        <?php if ($kit): ?><li><a class="vdoc" href="<?= e(Host::url('/presse/presentation-sochaux-retro.pdf')) ?>" target="_blank" rel="noopener"><span class="vdoc__ext">PDF</span><span class="vdoc__body"><b>Présentation du projet</b><span>Le musée en ligne et l’association, en 27 pages illustrées.</span><small><?= e(Base::size($kitSize)) ?></small></span><span class="vdoc__go" aria-hidden="true">↓</span></a></li><?php endif; ?>
        <li><a class="vdoc" href="/assets/img/logo-sochaux-retro.png" download="logo-sochaux-retro.png"><span class="vdoc__ext">PNG</span><span class="vdoc__body"><b>Logo Sochaux Rétro</b><span>Fond transparent, pour le web et l’impression légère.</span></span><span class="vdoc__go" aria-hidden="true">↓</span></a></li>
      </ul>
      <h2 class="h-2" style="margin-top:56px">Ils parlent de nous</h2>
      <?php if ($articles): ?>
        <ul class="vpress mt-20">
          <?php foreach ($articles as $a): ?>
            <li><?php $u = trim((string) ($a['url'] ?? '')); ?><<?= $u !== '' ? 'a href="' . e($u) . '" target="_blank" rel="noopener"' : 'div' ?> class="vpress__item">
              <span class="vpress__media"><?= e($a['media'] ?? '') ?><?= !empty($a['date']) ? ' · ' . e(date_fr((string) $a['date'])) : '' ?></span>
              <b><?= e($a['title']) ?></b><?php if (!empty($a['excerpt'])): ?><span><?= e($a['excerpt']) ?></span><?php endif; ?>
            </<?= $u !== '' ? 'a' : 'div' ?>></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted mt-20"><?= e($p['empty_text'] ?? '') ?></p>
      <?php endif; ?>
    </div>
    <aside class="vcols__side">
      <div class="vbox vbox--sticky">
        <h2 class="h-3">Contact presse</h2>
        <p class="mt-20" style="font-size:17px"><?= e($p['contact_text'] ?? '') ?></p>
        <?php if ($email !== ''): ?><p><a class="vmail" href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p><?php endif; ?>
        <a class="btn btn--navy btn--block" href="<?= e(Host::url('/contact/?objet=presse')) ?>">Écrire à l’équipe</a>
        <div class="vfacts vfacts--box mt-40"><?php foreach ($figures as $f): ?><div><b class="num"><?= e($f['n']) ?></b><span><?= e($f['label']) ?> <?= e($f['text']) ?></span></div><?php endforeach; ?></div>
      </div>
    </aside>
  </div>
</section>
