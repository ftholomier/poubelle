<?php
/**
 * Liste de documents à télécharger.
 *
 * Un lien de téléchargement doit dire trois choses avant le clic : ce que
 * c'est, quand ça a été publié, et ce que ça pèse. Le poids surtout : une
 * mairie de village se consulte souvent depuis un téléphone en zone blanche,
 * et un bulletin de quatre méga-octets ne se télécharge pas au même moment
 * qu'un compte-rendu de cent kilo-octets.
 *
 * @var array $documents
 * @var App\Core\View $view
 */
?>
<ul class="documents">
  <?php foreach ($documents as $doc): ?>
    <?php
    $fichier = (string) ($doc['fichier'] ?? '');
    if ($fichier === '') {
        continue;
    }
    $chemin = dirname(__DIR__, 2) . '/public/' . ltrim($fichier, '/');
    $octets = is_file($chemin) ? (int) filesize($chemin) : 0;
    ?>
    <li class="document">
      <a class="document__lien" href="<?= url($fichier) ?>" download>
        <span class="document__picto" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'document']) ?></span>
        <span class="document__corps">
          <span class="document__titre"><?= e($doc['titre'] ?? '') ?></span>
          <?php if (!empty($doc['texte'])): ?>
            <span class="document__texte"><?= e($doc['texte']) ?></span>
          <?php endif; ?>
          <span class="document__meta">
            <?php if (!empty($doc['date'])): ?><?= e(date_texte((string) $doc["date"])) ?><span aria-hidden="true"> · </span><?php endif; ?>
            PDF<?php if ($octets > 0): ?><span aria-hidden="true"> · </span><?= e(poids($octets)) ?><?php endif; ?>
          </span>
        </span>
        <span class="document__fleche" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'telecharger']) ?></span>
        <span class="sr-only"> — <?= e(t('télécharger le PDF')) ?><?= $octets > 0 ? ', ' . e(poids($octets)) : '' ?></span>
      </a>
    </li>
  <?php endforeach; ?>
</ul>
