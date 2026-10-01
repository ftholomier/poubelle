<?php
/** Page d'erreur (404 / 410 / 500). Autonome : servie même si le layout échoue. */

use App\Config;
use App\I18n;
use App\Router;
use App\Text;

$code = (int) ($code ?? 404);
$key = \in_array($code, [404, 410], true) ? (string) $code : '500';
$lang = I18n::lang();
?>
<section class="shell error-page">
  <h1><?= $code ?></h1>
  <h2><?= Text::e(I18n::t('error.' . $key . 'Title')) ?></h2>
  <p><?= Text::e(I18n::t('error.' . $key . 'Text')) ?></p>
  <div class="error-page__actions">
    <a class="btn btn--ink btn--lift" href="<?= Text::e(Router::url('home', $lang)) ?>"><?= Text::e(I18n::t('error.home')) ?></a>
    <a class="btn btn--outline" href="<?= Text::e(Router::url('offices', $lang)) ?>"><?= Text::e(I18n::t('nav.offices')) ?></a>
  </div>
</section>
