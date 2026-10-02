<?php
/** Partage & newsletter (maquette « Partage »). Variables : $sample, $days */
use App\Front\Site;
?>
<section class="wrap phead2">
  <span class="eyebrow eyebrow--lg"><?= e(t('Rayonnement · gabarits automatiques')) ?></span>
  <h1 class="h-xl cbig"><?= e(t('Images de partage')) ?><br>&amp; <?= e(t('newsletter')) ?></h1>
  <p class="lead" style="max-width:56ch;color:var(--muted)"><?= e(t('Chaque fiche génère son image (1200 × 630) pour Facebook, WhatsApp et X : photo et score, ou photo et nom. La newsletter « Ce jour-là » part chaque semaine, toute seule.')) ?></p>
</section>
<?php if ($sample): ?>
<section class="wrap pshare">
  <figure><figcaption><?= e(t('Fiche match')) ?></figcaption><img src="/partage/<?= (int) $sample['id'] ?>.png" alt="<?= e(Site::matchLabel($sample)) ?>" width="1200" height="630" loading="lazy"></figure>
  <figure><figcaption><?= e(t('Face-à-face')) ?></figcaption><img src="/partage/face-a-face/<?= e($sample['club'] ?? 'metz') ?>.png" alt="" width="1200" height="630" loading="lazy"></figure>
</section>
<?php endif; ?>
<section class="nlsec" id="newsletter">
  <div class="wrap nlsec__inner">
    <div class="stack" style="gap:14px">
      <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Newsletter hebdomadaire')) ?></span>
      <h2 class="h-section" style="color:var(--cream)"><?= e(t('« Ce jour-là »')) ?></h2>
      <p class="lead" style="max-width:none"><?= e(t('Générée automatiquement à partir des matchs de la semaine, avec le compte à rebours du centenaire et un rappel aux dons.')) ?></p>
      <form class="nlform" data-newsletter>
        <label class="sr-only" for="nlmail"><?= e(t('Votre e-mail')) ?></label>
        <input id="nlmail" type="email" name="email" required placeholder="<?= e(t('Votre e-mail')) ?>" autocomplete="email">
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button type="submit"><?= e(t("S'inscrire")) ?></button>
      </form>
      <p class="nlmsg" data-newsletter-msg role="status"></p>
      <small class="muted" style="color:var(--mist)"><?= e(t('Un e-mail de confirmation vous sera envoyé. Désinscription en un clic à chaque envoi.')) ?></small>
    </div>
    <div class="nlpreview" aria-hidden="true">
      <div class="nlpreview__head"><img src="/assets/img/logo-sochaux-retro.png" alt="" width="42" height="48"><div><b><?= e(t('Ce jour-là')) ?></b><small><?= e(t('Aperçu de la newsletter')) ?></small></div></div>
      <div class="nlpreview__body">
        <?php if ($sample): ?>
          <div class="nlpreview__img"><?php if ($sample['image']): ?><img src="<?= e(img($sample['image'], 640)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></div>
          <span class="eyebrow"><?= e(date_fr($sample['date'])) ?> · <?= e($sample['label'] ?: $sample['comp']) ?></span>
          <b class="nlpreview__t"><?= e(Site::matchLabel($sample)) ?></b>
          <p><?= e(t('Retrouvez la fiche, la composition et les photos de la rencontre dans le musée.')) ?></p>
          <a class="btn btn--navy btn--sm" href="<?= e(url($sample['path'])) ?>" tabindex="-1"><?= e(t('Lire la fiche')) ?></a>
        <?php endif; ?>
        <div class="nlpreview__cd"><b>J-<?= (int) $days ?> <?= e(t('avant les 100 ans')) ?></b><a class="btn btn--navy btn--sm" href="<?= e(url('/faire-un-don/')) ?>" tabindex="-1">♥ <?= e(t('Faire un don')) ?></a></div>
      </div>
    </div>
  </div>
</section>
