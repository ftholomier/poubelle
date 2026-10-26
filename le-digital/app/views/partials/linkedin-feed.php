<?php
/* Fil de posts LinkedIn : code d'une application externe collé dans le back-office (Blog › Posts LinkedIn), inséré tel quel. */
$title = trim((string) LD\Settings::get('linkedin_feed.title', ''));
$profile = linkedin_url();
?>
<section class="li-feed"<?= $title !== '' ? ' aria-labelledby="li-feed-title"' : ' aria-label="Posts LinkedIn"' ?>>
  <?php if ($title !== '' || $profile !== '#'): ?>
    <div class="li-feed__head">
      <?php if ($title !== ''): ?><h2 class="li-feed__t" id="li-feed-title"><span class="li-feed__in" aria-hidden="true">in</span><?= t($title) ?></h2><?php endif; ?>
      <?php if ($profile !== '#'): ?><a class="li-feed__follow" href="<?= e($profile) ?>" target="_blank" rel="noopener">Me suivre sur LinkedIn ↗</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="li-feed__code"><?= LD\Posts::linkedinCode() ?></div>
</section>
