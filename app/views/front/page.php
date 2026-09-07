<?php
/**
 * Gabarit principal du site public.
 * @var array $settings
 * @var array $page
 * @var array $menu
 * @var array $footerNav
 * @var array $languages
 * @var string $lang
 * @var array $reviews
 * @var bool $isHome
 */

use App\Content\Blocks;
use App\Content\Settings;
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="ltr" data-lang="<?= e($lang) ?>">
<head>
<?= View::render('front/layout/head', compact('settings', 'lang', 'languages', 'page')) ?>
</head>
<body class="page page--<?= e($page['slug']) ?><?= $isHome ? ' page--home' : '' ?>">

<?= View::render('front/layout/header', compact('settings', 'menu', 'lang', 'languages', 'page')) ?>

<main id="contenu" class="main">
    <?php foreach ($page['blocks'] as $index => $block):
        if (empty($block['enabled']) || !empty($block['_unknown']) || !Blocks::exists((string) $block['type'])) {
            continue;
        }
        $template = 'front/blocks/' . preg_replace('/[^a-z_]/', '', (string) $block['type']);
        if (!is_file(VIEW_PATH . '/' . $template . '.php')) {
            continue;
        }
        echo View::render($template, [
            'block'    => $block,
            'data'     => $block['data'],
            'settings' => $settings,
            'reviews'  => $reviews,
            'lang'     => $lang,
            'index'    => $index,
        ]);
    endforeach; ?>
</main>

<?= View::render('front/layout/footer', compact('settings', 'menu', 'footerNav', 'lang')) ?>
<?= View::render('front/layout/sticky-cta', compact('settings')) ?>
<?= View::render('front/layout/chatbot', compact('settings', 'lang')) ?>
<?= View::render('front/layout/exit-popup', compact('settings')) ?>
<?= View::render('front/layout/scripts', compact('settings', 'lang', 'languages')) ?>

</body>
</html>
