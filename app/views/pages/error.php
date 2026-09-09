<?php
/** Page d'erreur (404 / 500). Autonome : servie même si le layout échoue. */

use App\Config;
use App\I18n;
use App\Router;
use App\Text;

$code = (int) ($code ?? 404);
$is404 = $code === 404;
$lang = I18n::lang();
?>
<section class="shell error-page">
  <h1><?= $code ?></h1>
  <h2><?= Text::e($is404 ? I18n::t('error.404Title') : I18n::t('error.500Title')) ?></h2>
  <p><?= Text::e($is404 ? I18n::t('error.404Text') : I18n::t('error.500Text')) ?></p>
  <div class="error-page__actions">
    <a class="btn btn--ink btn--lift" href="<?= Text::e(Router::url('home', $lang)) ?>"><?= Text::e(I18n::t('error.home')) ?></a>
    <a class="btn btn--outline" href="<?= Text::e(Router::url('offices', $lang)) ?>"><?= Text::e(I18n::t('nav.offices')) ?></a>
  </div>
</section>
