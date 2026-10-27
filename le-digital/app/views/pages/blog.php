<?php
/** @var array $c  @var string|null $theme */
use LD\View;

$posts = LD\Posts::all(true);
$themes = $c['themes'];
$bgs = ['linear-gradient(135deg,#cc1517,#e8402a)', '#161922', '#cf1d1a', '#252436', 'linear-gradient(135deg,#e8402a,#cc1517)'];
$themeBg = static function (string $th) use ($themes, $bgs): string {
    $i = array_search($th, $themes, true);
    return $bgs[$i === false ? 0 : $i % count($bgs)];
};
$active = ($theme ?? '') !== '' && in_array($theme, $themes, true) ? $theme : '*';
$li = LD\Posts::linkedinPlace();
$split = $li === 'split';
$showGrid = $li !== 'replace' && $posts;
$empty = '<p class="rv-empty">Les premiers articles arrivent très vite. En attendant, <a href="/on-prend-rdv">on en parle autour d’un café</a> ?</p>';

// Filtres par thème + articles : grille sur toute la largeur, ou liste compacte dans la colonne de droite (deux colonnes).
ob_start(); ?>
    <div class="filters filters--blog" role="toolbar" aria-label="Filtrer par thème" data-filter="blog">
      <button type="button"<?= $active === '*' ? ' class="is-on" aria-pressed="true"' : ' aria-pressed="false"' ?> data-f="*"><?= t($c['filter_all']) ?></button>
      <?php foreach ($themes as $th): ?><button type="button"<?= $active === $th ? ' class="is-on" aria-pressed="true"' : ' aria-pressed="false"' ?> data-f="<?= e($th) ?>"><?= e($th) ?></button><?php endforeach; ?>
    </div>
    <div class="<?= $split ? 'blog-split__list' : 'af' ?>"<?= $split ? '' : ' style="--min:340px;--gap:20px"' ?> data-filter-list="blog">
      <?php foreach ($posts as $p): ?>
        <a class="post fitem<?= $split ? ' post--row' : '' ?>" href="/blog/<?= e($p['slug']) ?>" data-tag="<?= e($p['theme']) ?>"<?= $active !== '*' && $p['theme'] !== $active ? ' hidden' : '' ?>>
          <div class="post__img" style="background:<?= e($themeBg($p['theme'])) ?>"><?= img_tag($p['image'] ?? '', $p['title'], ['sizes' => $split ? '(max-width: 520px) 92vw, (max-width: 960px) 40vw, 260px' : '(max-width: 700px) 92vw, 400px']) ?: '<span>' . e($p['theme']) . '</span>' ?></div>
          <div class="post__body">
            <div class="post__meta"><span class="post__theme"><?= e($p['theme']) ?></span><span><?= (int) $p['read'] ?> <?= t($c['read_suffix']) ?></span></div>
            <h2 class="post__t"><?= t($p['title']) ?></h2>
            <p class="post__x"><?= t($p['excerpt']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
<?php $grid = ob_get_clean(); ?>
<section class="sec">
  <div class="wrap wrap--list">
    <h1 class="list-title"><?= t($c['title']) ?></h1>
    <?php if ($split): ?>
      <div class="blog-split">
        <div class="blog-split__col"><?= View::partial('linkedin-feed') ?></div>
        <div class="blog-split__col blog-split__col--posts">
          <div class="li-feed__head">
            <h2 class="li-feed__t"><span class="blog-split__ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></span><?= t($c['posts_title']) ?></h2>
          </div>
          <?= $posts ? $grid : $empty ?>
        </div>
      </div>
    <?php else: ?>
      <?php if ($li === 'before' || $li === 'replace'): ?><?= View::partial('linkedin-feed') ?><?php endif; ?>
      <?= $showGrid ? $grid : '' ?>
      <?php if (!$posts && $li === ''): ?><?= $empty ?><?php endif; ?>
      <?php if ($li === 'after'): ?><?= View::partial('linkedin-feed') ?><?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?= View::partial('final-dark', ['title' => $c['final_title'], 'text' => null, 'cta' => $c['final_cta'], 'variant' => 'blog']) ?>
