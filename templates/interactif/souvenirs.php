<?php
/**
 * Raconte-moi Bonal : le kit souvenirs du mois.
 * Variables : $ym, $month, $kit (Souvenirs::kit), $next (ym, month, c), $past (ym, month, c), $temoins
 */
use App\Front\Kit;
use App\Front\Site;

$c = $kit['match'] ?? null;
$s = $c['s'] ?? null;
$m = $kit['m'] ?? [];
?>
<section class="mhead skhead">
  <div class="wrap mhead__inner" style="padding-bottom:clamp(32px,4vw,56px)">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/interactif/')) ?>"><?= e(t('Interactif')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('Raconte-moi Bonal')) ?></span></nav>
    <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Participer')) ?> · <?= e(t('Kit souvenirs')) ?></span>
    <h1 class="mhead__title"><?= e(t('Raconte-moi Bonal')) ?></h1>
    <p class="mhead__intro"><?= e(t('Chaque mois, un kit à imprimer en gros caractères pour partager les grandes heures du club avec les anciens supporters : en famille, au club des aînés, à la médiathèque, en maison de retraite.')) ?></p>
  </div>
</section>

<div class="wrap skland">
  <?php if ($c && $s): ?>
  <section class="skmain" aria-labelledby="sk-month">
    <div class="skmain__img"><?php if (!empty($s['image']) && !\App\Data\Index::isPlaceholderImage($s['image'])): ?><img src="<?= e(img($s['image'], 800)) ?>" srcset="<?= e(srcset($s['image'], [480, 800, 1200])) ?>" sizes="(max-width: 900px) 100vw, 50vw" alt=""><?php endif; ?></div>
    <div class="skmain__body">
      <span class="eyebrow"><?= e(t('Le kit de {m}', ['m' => $month])) ?></span>
      <h2 class="h-section" id="sk-month"><?= e($c['ago'] > 1 ? t('Il y a {n} ans ce mois-ci', ['n' => $c['ago']]) : t('Ce mois-ci')) ?></h2>
      <p class="skmain__match"><b><?= e(Site::matchLabel($c['dm'])) ?></b><br><?= e(trim(date_fr($c['dm']['date'] ?? null, true) . ' · ' . ($m['competition_label'] ?? ''), ' ·')) ?></p>
      <ol class="skpages">
        <li><b><?= e(t('Le grand match')) ?></b> <?= e(t('le récit, le score, les buteurs, une anecdote')) ?></li>
        <li><b><?= e(t('Vous les reconnaissez ?')) ?></b> <?= e(t('{n} visages de l’époque à retrouver', ['n' => count($kit['faces'])])) ?></li>
        <li><b><?= e(t('Le quiz des anciens')) ?></b> <?= e(t('{n} questions, solutions à l’envers', ['n' => count($kit['quiz'])])) ?></li>
        <li><b><?= e(t('Racontez-nous')) ?></b> <?= e(t('des questions pour faire naître les souvenirs, et comment les envoyer au musée')) ?></li>
      </ol>
      <div class="row" style="gap:12px;flex-wrap:wrap">
        <a class="btn btn--yellow skbtn" href="<?= e(Kit::pdfUrl($ym)) ?>" download><?= e(t('Télécharger le kit (PDF, 4 pages)')) ?></a>
        <a class="btn btn--ghost" href="<?= e(url($s['path'])) ?>"><?= e(t('La fiche du match')) ?></a>
      </div>
      <p class="sknote"><?= e(t('À imprimer en A4, en noir et blanc ou en couleurs. Gratuit, sans inscription.')) ?></p>
    </div>
  </section>
  <?php endif; ?>

  <section class="sksec" aria-labelledby="sk-how">
    <h2 class="h-section" id="sk-how"><?= e(t('Comment l’utiliser')) ?></h2>
    <ol class="sksteps">
      <li><b><?= e(t('Lisez le match à voix haute')) ?></b><span><?= e(t('La date, le stade, le score : laissez venir les souvenirs. « Vous y étiez ? Avec qui ? »')) ?></span></li>
      <li><b><?= e(t('Montrez les visages')) ?></b><span><?= e(t('Chacun écrit ou dit les noms. Les réponses sont en bas de la page, à l’envers.')) ?></span></li>
      <li><b><?= e(t('Jouez au quiz')) ?></b><span><?= e(t('En équipe ou chacun pour soi : les deux premières questions portent sur le match du mois.')) ?></span></li>
      <li><b><?= e(t('Racontez au musée')) ?></b><span><?= e(t('Les souvenirs recueillis (avec l’accord de chacun) peuvent rejoindre la fiche du match, dans « Ils y étaient ».')) ?></span></li>
    </ol>
  </section>

  <?php if ($temoins): ?>
  <section class="sksec" aria-labelledby="sk-temoins">
    <h2 class="h-section" id="sk-temoins"><?= e(t('Ils y étaient')) ?></h2>
    <div class="sktem">
      <?php foreach ($temoins as $t): ?>
        <figure class="quote">
          <span class="quote__mark" aria-hidden="true">«</span>
          <blockquote><?= nl2br(e(mb_strlen($t['text']) > 420 ? rtrim(mb_substr($t['text'], 0, 417)) . '…' : $t['text'])) ?></blockquote>
          <figcaption><?= e($t['name']) ?> · <a href="<?= e(url($t['s']['path'])) ?>#ils-y-etaient"><?= e(Site::matchLabel(\App\Data\Derived::match((int) $t['s']['id']) ?? ['home' => $t['s']['title']])) ?></a></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="sksec skmore" aria-labelledby="sk-more">
    <h2 class="h-3" id="sk-more"><?= e(t('Les autres kits')) ?></h2>
    <ul class="skmonths">
      <?php if ($next['c']): ?><li><a href="<?= e(Kit::pdfUrl($next['ym'])) ?>" download><b><?= e(ucfirst($next['month'])) ?></b> <span><?= e(t('à préparer à l’avance')) ?> · <?= e(Site::matchLabel($next['c']['dm'])) ?></span></a></li><?php endif; ?>
      <?php foreach ($past as $p): ?>
        <li><a href="<?= e(Kit::pdfUrl($p['ym'])) ?>" download><b><?= e(ucfirst($p['month'])) ?></b> <span><?= e(Site::matchLabel($p['c']['dm'])) ?> (<?= e(substr((string) $p['c']['dm']['date'], 0, 4)) ?>)</span></a></li>
      <?php endforeach; ?>
    </ul>
    <p class="sknote"><?= e(t('Un souvenir à partager tout de suite ?')) ?> <a href="<?= e(url('/souvenir/')) ?>"><?= e(t('Racontez-le au musée')) ?> →</a></p>
  </section>
</div>
