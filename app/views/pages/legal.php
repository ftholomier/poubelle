<?php
/** Mentions légales et politique de confidentialité (même gabarit). */

use App\Content;
use App\Text;
?>
<section class="shell shell--legal section--first" style="padding-top:60px">
  <div class="kicker"><?= Text::e(Content::text($page, 'kicker')) ?></div>
  <h1 class="legal-title"><?= Text::e(Content::text($page, 'title')) ?></h1>
  <p class="legal-lead"><?= Text::e(Content::text($page, 'text')) ?></p>

  <div class="legal-blocks">
    <?php foreach (Content::list($page, 'blocks') as $block): ?>
      <section class="legal-block" data-reveal>
        <h2><?= Text::e((string) ($block['title'] ?? '')) ?></h2>
        <p><?= Text::e((string) ($block['text'] ?? '')) ?></p>
      </section>
    <?php endforeach; ?>
  </div>
</section>
