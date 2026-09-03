<?php
/**
 * « En ce moment » — le contenu vivant de la commune, sur toutes les pages.
 *
 * Trois entrées seulement : la dernière actualité, le prochain rendez-vous, le
 * dernier Flash Info. Ce n'est pas une liste, c'est un rappel — l'administré
 * venu chercher les pièces d'une carte d'identité voit d'un coup d'œil qu'il
 * se passe quelque chose, et sait où cliquer.
 *
 * Le bandeau est rendu par hero-page.php, donc sur toutes les pages sauf
 * l'accueil, qui porte déjà la section complète « La vie du village » plus
 * bas. L'afficher aux deux endroits, à trois cents pixels d'écart, aurait fait
 * doublon sans rien gagner.
 *
 * Il se retire de lui-même quand la commune n'a rien à annoncer : ni actualité,
 * ni rendez-vous à venir, ni bulletin. Un bandeau vide sur toutes les pages
 * serait pire que pas de bandeau.
 *
 * @var App\Core\Vivant $vivant
 * @var App\Core\View $view
 */
if (!isset($vivant) || !$vivant->aQuelqueChose()) {
    return;
}

$actu  = $vivant->derniereActualite();
$event = $vivant->prochainEvenement();
$flash = $vivant->dernierFlashInfo();
?>
<section class="en-ce-moment" aria-labelledby="en-ce-moment-titre">
  <div class="conteneur">
    <h2 class="en-ce-moment__intitule" id="en-ce-moment-titre"><?= e(t('En ce moment')) ?></h2>

    <ul class="en-ce-moment__liste">
      <?php if ($actu !== null): ?>
        <li class="en-ce-moment__item">
          <p class="en-ce-moment__quoi"><?= e(t('À la une')) ?></p>
          <?php /* Le lien couvre le titre, pas la carte entière : une zone
                   cliquable sans texte n'est annoncée par rien, et l'on ne sait
                   pas où l'on va. */ ?>
          <p class="en-ce-moment__titre">
            <a href="<?= route('actualites', $actu['slug'] ?? '') ?>"><?= e($actu['titre'] ?? '') ?></a>
          </p>
          <?php if (!empty($actu['date'])): ?>
            <p class="en-ce-moment__quand"><?= e(date_texte((string) $actu['date'])) ?></p>
          <?php endif; ?>
        </li>
      <?php endif; ?>

      <?php if ($event !== null): ?>
        <li class="en-ce-moment__item">
          <p class="en-ce-moment__quoi"><?= e(t('Prochain rendez-vous')) ?></p>
          <p class="en-ce-moment__titre">
            <a href="<?= route('agenda') ?>"><?= e($event['titre'] ?? '') ?></a>
          </p>
          <p class="en-ce-moment__quand">
            <?= e(date_texte((string) ($event['date'] ?? ''), true)) ?><?php
              if (!empty($event['lieu'])): ?> · <?= e($event['lieu']) ?><?php endif; ?>
          </p>
        </li>
      <?php endif; ?>

      <?php if ($flash !== null): ?>
        <li class="en-ce-moment__item">
          <p class="en-ce-moment__quoi"><?= e(t('Dernier Flash Info')) ?></p>
          <p class="en-ce-moment__titre">
            <a href="<?= route('flash-info') ?>"><?= e($flash['titre'] ?? '') ?></a>
          </p>
          <?php if (!empty($flash['date'])): ?>
            <p class="en-ce-moment__quand"><?= e(date_texte((string) $flash['date'])) ?></p>
          <?php endif; ?>
        </li>
      <?php endif; ?>
    </ul>
  </div>
</section>
