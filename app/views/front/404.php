<?php
/** @var array $settings @var array $menu @var array $footerNav @var string $lang @var array $languages */
use App\Core\View;
$page = null;
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head><?= View::render('front/layout/head', compact('settings', 'lang', 'languages', 'page')) ?></head>
<body class="page page--404">
<?= View::render('front/layout/header', compact('settings', 'menu', 'lang', 'languages', 'page')) ?>
<main id="contenu" class="main">
    <section class="section section--dark section--loose">
        <div class="shell shell--narrow text-center">
            <span class="error-glyph" aria-hidden="true"><?= icon('glasses', '', 56) ?></span>
            <p class="eyebrow eyebrow--light">Erreur 404</p>
            <h1 class="section__title section__title--light"><?= __e('error.404.title') ?></h1>
            <p class="section__text section__text--light"><?= __e('error.404.text') ?></p>
            <a class="btn btn--accent btn--slide btn--halo" href="<?= e(u('/')) ?>">
                <span class="btn__halo" aria-hidden="true"></span>
                <span class="btn__label"><?= __e('error.404.cta') ?></span>
            </a>
        </div>
    </section>
</main>
<?= View::render('front/layout/footer', compact('settings', 'menu', 'footerNav', 'lang')) ?>
<?= View::render('front/layout/sticky-cta', compact('settings')) ?>
<?= View::render('front/layout/chatbot', compact('settings', 'lang')) ?>
<?= View::render('front/layout/scripts', compact('settings', 'lang', 'languages')) ?>
</body>
</html>
