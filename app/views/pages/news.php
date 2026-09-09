<?php
/** L'actu : liste des articles publiés. */

use App\Content;
use App\I18n;
use App\Router;
use App\Text;
use App\View;

$lang = I18n::lang();
$palette = ['#FFD100', '#12B39A', '#EDE5D5'];
?>
<section class="shell section--first" style="padding-top:60px">
  <div class="kicker"><?= Text::e(Content::text($page, 'kicker')) ?></div>
  <h1 class="offices-title"><?= Text::e(Content::text($page, 'title')) ?></h1>

  <?php if ($posts === []): ?>
    <p class="empty-note"><?= Text::e(I18n::t('news.empty')) ?></p>
  <?php else: ?>
  <div class="grid grid--news">
    <?php foreach ($posts as $i => $post):
        $url = Router::url('post', $lang, ['slug' => (string) ($post['slug'] ?? '')]);
        $title = Content::i18n($post, 'title', $lang); ?>
      <article class="post-card" data-reveal data-delay="<?= $i * 110 ?>">
        <div class="post-card__media" style="background:<?= Text::e((string) ($post['color'] ?? $palette[$i % 3])) ?>">
          <?= View::image((string) ($post['image'] ?? ''), $title, ['placeholder' => $title]) ?>
          <a class="post-card__link" href="<?= Text::e($url) ?>"><span class="sr-only"><?= Text::e($title) ?></span></a>
        </div>
        <div class="post-card__body">
          <div class="post-card__date"><?= Text::e(I18n::date((string) ($post['date'] ?? ''), IntlDateFormatter::MEDIUM)) ?></div>
          <h2 class="post-card__title"><a href="<?= Text::e($url) ?>"><?= Text::e($title) ?></a></h2>
          <p class="post-card__text"><?= Text::e(Content::i18n($post, 'excerpt', $lang)) ?></p>
          <a class="link-underline link-underline--yellow link-underline--sm post-card__cta" href="<?= Text::e($url) ?>"><?= Text::e(I18n::t('news.read')) ?></a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
