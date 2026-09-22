<?php
/** Page éditoriale (mentions légales, CGU…). @var array $page */
use App\Core\View;
use App\Services\I18n;
?>
<div class="container" style="max-width:860px">
  <div class="page-head" data-reveal>
    <h1 class="h1-sub"><?= e($page['title']) ?></h1>
    <?php if (($page['updated_at'] ?? '') !== ''): ?>
      <p class="meta" style="color:rgba(255,255,255,.6)">
        Mise à jour <?= e(date('d/m/Y', (int) strtotime((string) $page['updated_at']))) ?>
      </p>
    <?php endif; ?>
  </div>

  <article class="card card-lg prose" style="margin-top:22px" data-reveal>
    <?= $page['body'] /* nettoyé à l'import et à l'enregistrement : voir Sanitizer */ ?>
  </article>

  <?php // Après le texte, et jamais sur les pages légales : on ne fait pas
        // lire des mentions obligatoires à travers une annonce. ?>
  <?php if (!in_array((string) $page['slug'], ['mentions-legales', 'confidentialite', 'cgu'], true)): ?>
    <?= View::partial('partials/ad', ['slot' => 'page_inline']) ?>
  <?php endif; ?>
</div>
