<?php
/** Les quatre façons de soutenir l'association (accueil, Nous soutenir). */
use App\Vitrine\Host;

$cards = [
    ['★', 'Adhérer', 'Devenez membre de l’association et participez à sa vie.', Host::url('/nous-soutenir/adherer/'), 'J’adhère', false, 'yellow'],
    ['✎', 'Devenir bénévole', 'Historien, scanneur, monteur, rédacteur : donnez un peu de votre temps.', Host::url('/nous-soutenir/benevolat/'), 'Je me propose', false, ''],
    ['♥', 'Faire un don', 'Financez la numérisation et la mise en ligne des archives du club.', Host::museum('/faire-un-don/'), 'Je donne', true, ''],
    ['▤', 'Confier vos archives', 'Photos, billets, programmes : un scan suffit, l’original reste chez vous.', Host::museum('/contribuer/'), 'Je partage', true, ''],
];
?>
<div class="vsupport">
  <?php foreach ($cards as [$icon, $title, $text, $href, $btn, $ext, $tone]): ?>
    <a class="vsupport__card<?= $tone ? ' vsupport__card--' . $tone : '' ?>" href="<?= e($href) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?> data-reveal>
      <span class="vsupport__icon" aria-hidden="true"><?= e($icon) ?></span>
      <h3><?= e($title) ?></h3>
      <p><?= e($text) ?></p>
      <span class="vsupport__btn"><?= e($btn) ?> <?= $ext ? '↗' : '→' ?></span>
    </a>
  <?php endforeach; ?>
</div>
