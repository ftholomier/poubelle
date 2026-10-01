<?php
/**
 * Bandeau d'appel « N'attendez plus ! » : le même bloc de contact que l'ancien
 * site affichait en bas de chaque page. Son texte se saisit une seule fois,
 * dans la page Accueil (bloc « band ») ; une page peut le remplacer par le sien.
 *
 * @var array $band  kicker, title, text, cta1, cta2 (facultatif)
 * @var array $fill  jetons remplacés dans le titre ({count}…)
 */

use App\Content;
use App\I18n;
use App\Router;
use App\Text;
use App\View;

$lang = I18n::lang();
$band = isset($band) && \is_array($band) && $band !== [] ? $band : (array) (Content::page('home', $lang)['band'] ?? []);
if (Content::text($band, 'title') === '') {
    return;
}
$phone = (string) ($settings['contact']['phone'] ?? '');
$tel = (string) preg_replace('/[^0-9+]/', '', $phone);
?>
<section class="shell section">
  <div class="band" data-reveal>
    <div class="band__bubble band__bubble--1" aria-hidden="true"></div>
    <div class="band__bubble band__bubble--2" aria-hidden="true"></div>
    <div class="band__inner">
      <div class="kicker kicker--signal kicker--light"><?= Text::e(Content::text($band, 'kicker')) ?></div>
      <h2 class="band__title"><?= Text::e(View::fill(Content::text($band, 'title'), (array) ($fill ?? []))) ?></h2>
      <p class="band__text"><?= Text::e(Content::text($band, 'text')) ?></p>
      <div class="band__actions">
        <a class="btn btn--yellow-paper btn--lift" href="<?= Text::e(Router::url('contact', $lang)) ?>" data-track="band_contact"><?= Text::e(Content::text($band, 'cta1')) ?></a>
        <?php if ($tel !== ''): ?>
          <a class="btn btn--outline-paper" href="tel:<?= Text::e($tel) ?>" data-track="band_phone">
            <span class="btn__phone" aria-hidden="true"></span><?= Text::e(Content::text($band, 'cta2', $phone)) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
