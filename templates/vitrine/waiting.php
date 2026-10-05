<?php
/**
 * Page d'attente du site de l'association (site fermé, ou aperçu depuis le back-office).
 * Volontairement différente de celle du musée (fond bleu nuit, tout centré) : page claire en deux
 * colonnes, photo à gauche avec sa pastille, sa légende et son crédit, « ce qui vous attend », inscription à la lettre,
 * encart du musée en ligne, contact. Réglée dans Site de l'association › Page d'attente.
 * Variables : $w (contenu « attente »), $museumOpen, $teaser, $email, $social, $preview
 */
use App\Data\Media;
use App\Vitrine\Content;
use App\Vitrine\Host;
use App\Vitrine\Site;

$name = Site::name();
$title = trim((string) ($w['title'] ?? '')) ?: 'Notre nouveau site arrive';
$text = safe_html((string) ($w['text'] ?? ''));
$image = (string) ($w['image'] ?? '');
$hasImage = $image !== '' && Media::get($image);
$caption = trim((string) ($w['image_caption'] ?? ''));
$credit = Content::waitingCredit($w);
$badge = trim((string) ($w['badge'] ?? ''));
$items = array_values(array_filter((array) ($w['items'] ?? []), fn ($it) => is_array($it) && trim((string) ($it['title'] ?? '')) !== ''));
$cdDate = str_replace(' ', 'T', (string) ($w['countdown_date'] ?? ''));
if (strlen($cdDate) === 16) {
    $cdDate .= ':00';
}
$countdown = !empty($w['countdown']) && $cdDate !== '' && (int) strtotime(str_replace('T', ' ', $cdDate)) > time();
$desc = mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags($text))), 0, 160);
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e($name) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:image" content="<?= e(Host::abs('/assets/img/vitrine/partage.jpg')) ?>">
<meta name="theme-color" content="#F3EDDF">
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<link rel="stylesheet" href="<?= asset('css/vitrine.css') ?>">
</head>
<body class="vt vwait-page">
<?php if ($preview): ?>
  <p class="vwait__preview" role="note"><b>Aperçu de la page d’attente</b> <?= Site::open() ? '· le site est ouvert : le public ne la voit plus' : '· c’est ce que voient les visiteurs' ?> · <a href="/admin/association/attente">Modifier</a></p>
<?php endif; ?>
<main class="vwait">
  <div class="vwait__photo<?= $hasImage ? '' : ' vwait__photo--none' ?>">
    <?php if ($hasImage): ?><img src="<?= e(img($image, 1600)) ?>" srcset="<?= e(srcset($image, [800, 1200, 1600])) ?>" sizes="(max-width: 900px) 100vw, 44vw" alt="<?= e($caption) ?>" fetchpriority="high"><?php endif; ?>
    <?php if ($badge !== ''): ?><span class="vwait__badge"><?= e($badge) ?></span><?php endif; ?>
    <?php if ($hasImage && ($caption !== '' || $credit !== '')): ?>
      <div class="vwait__legend">
        <?php if ($caption !== ''): ?><span class="vwait__caption" aria-hidden="true"><?= e($caption) ?></span><?php endif; ?>
        <?php if ($credit !== ''): ?><small class="vwait__credit">Photo : <?= e($credit) ?></small><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="vwait__main">
    <div class="vwait__brand"><img src="/assets/img/logo-sochaux-retro.png" alt="" width="133" height="150"><span><b><?= e($name) ?></b><small>L’association</small></span></div>

    <div class="vwait__intro">
      <?php if (trim((string) ($w['eyebrow'] ?? '')) !== ''): ?><span class="eyebrow"><?= e($w['eyebrow']) ?></span><?php endif; ?>
      <h1 class="vwait__title"><?= e($title) ?></h1>
      <?php if (trim(strip_tags($text)) !== ''): ?><div class="prose vwait__text"><?= $text ?></div><?php endif; ?>
    </div>

    <?php if ($countdown): ?>
      <div class="vwait__cd">
        <?php if (trim((string) ($w['countdown_label'] ?? '')) !== ''): ?><span class="eyebrow"><?= e($w['countdown_label']) ?></span><?php endif; ?>
        <?= countdown_html($cdDate) ?>
      </div>
    <?php endif; ?>

    <?php if ($items): ?>
      <section class="vwait__soon" aria-labelledby="vwait-soon">
        <h2 id="vwait-soon" class="vwait__h"><?= e(trim((string) ($w['items_title'] ?? '')) ?: 'Ce qui vous attend') ?></h2>
        <ul>
          <?php foreach ($items as $it): ?>
            <li><span class="vwait__icon" aria-hidden="true"><?= e((string) ($it['icon'] ?? '') ?: '◆') ?></span><span><b><?= e($it['title']) ?></b><?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><span><?= e($it['text']) ?></span><?php endif; ?></span></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if (!empty($w['newsletter'])): ?>
      <section class="vwait__nl" aria-labelledby="vwait-nl">
        <h2 id="vwait-nl" class="vwait__h"><?= e(trim((string) ($w['newsletter_title'] ?? '')) ?: 'Soyez prévenu de l’ouverture') ?></h2>
        <?php if (trim((string) ($w['newsletter_text'] ?? '')) !== ''): ?><p><?= e($w['newsletter_text']) ?></p><?php endif; ?>
        <?= \App\Core\View::partial('vitrine/partials/newsletter-form', ['id' => 'nl-wait', 'back' => $preview ? 'attente' : Site::path(), 'button' => 'Prévenez-moi']) ?>
      </section>
    <?php endif; ?>

    <?php if (!empty($w['museum'])): ?>
      <section class="vwait__museum" aria-labelledby="vwait-museum">
        <span class="eyebrow">Le musée en ligne du FCSM</span>
        <h2 id="vwait-museum" class="vwait__h"><?= $museumOpen ? 'Il est ouvert, et il vous attend' : 'Il ouvre bientôt, lui aussi' ?></h2>
        <p>Près d’un siècle de matchs, de joueurs, de photos et d’archives du FC Sochaux-Montbéliard, gratuitement, pour tous les passionnés.</p>
        <?php if ($teaser): ?>
          <video class="vwait__teaser" controls playsinline preload="none" poster="<?= e(Host::url('/video/teaser.jpg')) ?>" width="1920" height="1080" aria-label="Teaser vidéo du musée">
            <source src="<?= e(Host::url('/video/teaser.mp4')) ?>" type="video/mp4">
          </video>
        <?php endif; ?>
        <?php if ($museumOpen): ?><a class="btn btn--navy" href="<?= e(Host::museum('/')) ?>">Visiter le musée ↗</a><?php endif; ?>
      </section>
    <?php endif; ?>

    <footer class="vwait__foot">
      <?php if ($email !== ''): ?><p>Une question ? Écrivez-nous : <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p><?php endif; ?>
      <?php if ($social): ?>
        <div class="vsocial" aria-label="Réseaux sociaux">
          <?php foreach ($social as $k => [$label, $href]): ?><a href="<?= e($href) ?>" rel="noopener" target="_blank" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= \App\Core\View::partial('vitrine/partials/icon', ['name' => $k]) ?></a><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <p class="vwait__legal">© <?= date('Y') ?> <?= e($name) ?> · <a href="<?= e(Host::url('/mentions-legales/')) ?>">Mentions légales</a> · <a href="<?= e(Host::url('/confidentialite/')) ?>">Confidentialité</a><br>Site développé par <span class="vcredit">Frédéric Tholomier | <a href="https://le-digital.com/" target="_blank" rel="noopener">LE-DIGITAL.com</a></span></p>
    </footer>
  </div>
</main>
<script src="<?= asset('js/site.js') ?>" defer></script>
</body>
</html>
