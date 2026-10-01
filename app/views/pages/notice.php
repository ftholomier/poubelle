<?php
/**
 * Retour d'un formulaire envoyé sans JavaScript : le message de l'API, mis en
 * page comme le reste du site, et un lien pour revenir là où l'on était.
 *
 * @var bool   $ok
 * @var string $message
 * @var string $back adresse de retour (même site)
 */

use App\I18n;
use App\Router;
use App\Text;

$lang = I18n::lang();
?>
<section class="shell error-page notice-page">
  <div class="kicker kicker--signal"><?= Text::e(I18n::t($ok ? 'notice.okKicker' : 'notice.errorKicker')) ?></div>
  <h1><?= Text::e(I18n::t($ok ? 'notice.okTitle' : 'notice.errorTitle')) ?></h1>
  <p><?= Text::e($message) ?></p>
  <div class="error-page__actions">
    <a class="btn btn--ink btn--lift" href="<?= Text::e($back) ?>"><?= Text::e(I18n::t('notice.back')) ?></a>
    <a class="btn btn--outline" href="<?= Text::e(Router::url('home', $lang)) ?>"><?= Text::e(I18n::t('error.home')) ?></a>
  </div>
</section>
