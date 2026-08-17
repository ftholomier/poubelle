<?php
/**
 * Avis Google.
 *
 * Les avis arrivent déjà filtrés et mis en cache par App\Core\Avis : rien
 * n'est demandé à Google au moment de l'affichage. Le fragment se retire
 * tout seul quand les avis ne sont pas configurés, désactivés ou
 * momentanément indisponibles — une section vide serait pire que pas de
 * section du tout.
 *
 * @var App\Core\Avis $avis
 * @var App\Core\View $view
 * @var string|null $fond   'clair' (défaut) ou 'teinte'
 */
$donnees = $avis->donnees();
if ($donnees === null || $donnees['avis'] === []) {
    return;
}

$note  = $donnees['note'];
$total = $donnees['total'];
?>
<section class="section avis <?= ($fond ?? 'clair') === 'teinte' ? 'section--teinte' : '' ?>">
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
          <?php if ($total > 0): ?>
            <span class="avis__total"><?= e(sprintf(t('sur %d avis Google'), $total)) ?></span>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </div>

    <ul class="avis__liste">
      <?php foreach ($donnees['avis'] as $a): ?>
        <li class="avis__carte reveler">
          <div class="avis__entete">
            <span class="avis__pastille" aria-hidden="true"><?= e($a['initiales']) ?></span>
            <div>
              <p class="avis__auteur"><?= e($a['auteur']) ?></p>
              <?php if (($a['horodatage'] ?? 0) > 0): ?>
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

    <?php if ($donnees['url'] !== ''): ?>
      <p class="avis__source reveler">
        <a class="lien-fleche" href="<?= e($donnees['url']) ?>" target="_blank" rel="noopener nofollow">
          <?= e(t('Voir tous les avis sur Google')) ?>
        </a>
      </p>
    <?php endif; ?>
  </div>
</section>
