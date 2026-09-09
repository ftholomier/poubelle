<?php
/** Article de l'actu. */

use App\Content;
use App\I18n;
use App\Router;
use App\Text;
use App\View;

$lang = I18n::lang();
$title = Content::i18n($post, 'title', $lang);
$body = Content::i18n($post, 'body', $lang);
?>
<section class="shell section--first" style="padding-top:44px">
  <div class="crumb">
    <a href="<?= Text::e(Router::url('news', $lang)) ?>"><?= Text::e(I18n::t('news.back')) ?></a>
    <span>/</span>
    <span><?= Text::e($title) ?></span>
  </div>

  <article class="article">
    <div class="article__date" style="margin-top:26px"><?= Text::e(I18n::date((string) ($post['date'] ?? ''), IntlDateFormatter::LONG)) ?></div>
    <h1 class="article__title"><?= Text::e($title) ?></h1>
    <?php if (!empty($post['image'])): ?>
      <div class="article__media" style="background:<?= Text::e((string) ($post['color'] ?? '#FFD100')) ?>">
        <?= View::image((string) $post['image'], $title, ['eager' => true, 'sizes' => '(max-width: 880px) 100vw, 760px']) ?>
      </div>
    <?php endif; ?>
    <div class="prose"><?= $body !== '' ? $body : '<p>' . Text::e(Content::i18n($post, 'excerpt', $lang)) . '</p>' ?></div>
  </article>

  <?php if ($related !== []): ?>
  <section class="section--tight section">
    <h2 class="section-title" style="margin:0 0 8px"><?= Text::e(I18n::t('nav.news')) ?></h2>
    <div class="grid grid--news">
      <?php foreach ($related as $i => $item):
          $url = Router::url('post', $lang, ['slug' => (string) ($item['slug'] ?? '')]);
          $itemTitle = Content::i18n($item, 'title', $lang); ?>
        <article class="post-card" data-reveal data-delay="<?= $i * 110 ?>">
          <div class="post-card__media" style="background:<?= Text::e((string) ($item['color'] ?? '#FFD100')) ?>">
            <?= View::image((string) ($item['image'] ?? ''), $itemTitle, ['placeholder' => $itemTitle]) ?>
            <a class="post-card__link" href="<?= Text::e($url) ?>"><span class="sr-only"><?= Text::e($itemTitle) ?></span></a>
          </div>
          <div class="post-card__body">
            <div class="post-card__date"><?= Text::e(I18n::date((string) ($item['date'] ?? ''), IntlDateFormatter::MEDIUM)) ?></div>
            <h3 class="post-card__title"><a href="<?= Text::e($url) ?>"><?= Text::e($itemTitle) ?></a></h3>
            <p class="post-card__text"><?= Text::e(Content::i18n($item, 'excerpt', $lang)) ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</section>
