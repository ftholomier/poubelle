<?php
/**
 * Avis Google, en carrousel.
 *
 * Les avis arrivent déjà filtrés et mis en cache par App\Core\Avis : rien
 * n'est demandé à Google au moment de l'affichage. Le fragment se retire
 * tout seul quand les avis ne sont pas configurés, désactivés ou
 * momentanément indisponibles — une section vide serait pire que pas de
 * section du tout.
 *
 * Inclus une seule fois, par le gabarit, en dernier bloc de chaque page.
 *
 * Le défilement repose sur le défilement natif du navigateur plutôt que sur
 * une transformation : sans JavaScript la piste reste parcourable, et le
 * glissement du doigt fonctionne sur téléphone sans une ligne de code.
 *
 * @var App\Core\Avis $avis
 * @var App\Core\View $view
 */
$donnees = $avis->donnees();
if ($donnees === null || $donnees['avis'] === []) {
    return;
}

$note   = $donnees['note'];
$total  = $donnees['total'];
$liste  = $donnees['avis'];
$dates  = $avis->datesVisibles();
$pause  = $avis->pause();
$nombre = count($liste);
$totalVisible = $avis->totalVisible() && $total > 0;
?>
<section class="section avis">
  <div class="conteneur">
    <div class="avis__tete reveler">
      <p class="surtitre"><?= e(t('Avis clients')) ?></p>
      <h2 class="titre-section"><?= e(t('Ce que disent nos clients')) ?></h2>

      <?php if ($note > 0): ?>
        <p class="avis__note">
          <span class="avis__chiffre"><?= e(number_format($note, 1, ',', ' ')) ?></span>
          <span class="avis__etoiles" role="img"
                aria-label="<?= e(sprintf(t('Note de %s sur 5'), number_format($note, 1, ',', ' '))) ?>">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <span class="avis__etoile<?= $i <= round($note) ? ' avis__etoile--pleine' : '' ?>">
                <?= $view->partial('icones', ['nom' => 'etoile']) ?>
              </span>
            <?php endfor; ?>
          </span>
          <?php /* La note et les cinq étoiles se suffisent : ajouter « avis
                   Google » à côté du chiffre le faisait lire comme un nombre
                   d'avis. La provenance est déjà dite par le sur-titre. */ ?>
          <?php if ($totalVisible): ?>
            <span class="avis__total"><?= e(sprintf(t('sur %d avis'), $total)) ?></span>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </div>

    <div class="avis__carrousel" data-avis data-pause="<?= e($pause) ?>">
      <?php /* aria-live off : un avis qui défile tout seul ne doit pas être
               annoncé en boucle par un lecteur d'écran. */ ?>
      <ul class="avis__piste" tabindex="0" aria-label="<?= e(t('Avis de nos clients')) ?>">
        <?php foreach ($liste as $rang => $a): ?>
          <li class="avis__carte" data-avis-item="<?= e($rang) ?>">
            <div class="avis__entete">
              <span class="avis__pastille" aria-hidden="true"><?= e($a['initiales']) ?></span>
              <div>
                <p class="avis__auteur"><?= e($a['auteur']) ?></p>
                <?php if ($dates && ($a['horodatage'] ?? 0) > 0): ?>
                  <p class="avis__date">
                    <time datetime="<?= e(date('Y-m-d', $a['horodatage'])) ?>">
                      <?= e(date_fr($a['horodatage'])) ?>
                    </time>
                  </p>
                <?php endif; ?>
              </div>
            </div>

            <p class="avis__etoiles avis__etoiles--carte" role="img"
               aria-label="<?= e(sprintf(t('%d étoiles sur 5'), (int) $a['note'])) ?>">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="avis__etoile<?= $i <= $a['note'] ? ' avis__etoile--pleine' : '' ?>">
                  <?= $view->partial('icones', ['nom' => 'etoile']) ?>
                </span>
              <?php endfor; ?>
            </p>

            <blockquote class="avis__texte"><?= e($a['texte']) ?></blockquote>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php /* Les commandes sont masquées par le script quand tous les avis
               tiennent à l'écran : elles n'auraient alors rien à faire. */ ?>
      <div class="avis__commandes" data-avis-commandes hidden>
        <button class="avis__fleche" type="button" data-avis-prec
                aria-label="<?= e(t('Avis précédent')) ?>"></button>
        <div class="avis__points" data-avis-points>
          <?php for ($i = 0; $i < $nombre; $i++): ?>
            <button class="avis__point" type="button" data-avis-point="<?= e($i) ?>"
                    aria-label="<?= e(sprintf(t('Aller à l’avis %d'), $i + 1)) ?>"></button>
          <?php endfor; ?>
        </div>
        <button class="avis__fleche avis__fleche--suiv" type="button" data-avis-suiv
                aria-label="<?= e(t('Avis suivant')) ?>"></button>
      </div>
    </div>

  </div>
</section>
