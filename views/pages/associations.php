<?php
/**
 * Les associations du village.
 *
 * Chaque fiche porte les contacts tels que l'association les donne : un
 * prénom, un nom et un numéro de fixe. C'est ainsi qu'on se joint dans un
 * village de sept cents habitants, et remplacer cela par un formulaire
 * générique éloignerait le nouvel arrivant de la personne qui décroche.
 *
 * @var array $page
 * @var array $items
 * @var App\Core\View $view
 */
$hero = ($page['hero'] ?? []) + ['titre' => $page['titre'] ?? '', 'image' => ''];
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<?php if (!empty($page['sous_titre'])): ?>
<section class="section section--chapo">
  <div class="conteneur conteneur--etroit">
    <p class="chapo reveler"><?= e($page['sous_titre']) ?></p>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="conteneur">
    <ul class="associations">
      <?php foreach ($items as $asso): ?>
        <li class="association reveler" id="<?= e($asso['slug'] ?? '') ?>">
          <span class="association__picto" aria-hidden="true">
            <?= $view->partial('icones', ['nom' => $asso['icone'] ?? 'association']) ?>
          </span>
          <div class="association__corps">
            <h2 class="association__nom"><?= e($asso['nom'] ?? '') ?></h2>
            <?php /* La photo est facultative : une association qui n'en fournit
                     pas garde une fiche de texte, sans trou dans la grille. */ ?>
            <?php if (!empty($asso['image'])): ?>
              <img class="association__photo" src="<?= image($asso['image']) ?>"
                   alt="<?= e($asso['image_alt'] ?? '') ?>" loading="lazy">
            <?php endif; ?>
            <?php if (!empty($asso['objet'])): ?>
              <p class="association__objet"><?= e($asso['objet']) ?></p>
            <?php endif; ?>
            <?php foreach ($asso['paragraphes'] ?? [] as $p): ?><p><?= e($p) ?></p><?php endforeach; ?>

            <?php if (!empty($asso['rendez_vous'])): ?>
              <p class="association__rdv">
                <span aria-hidden="true"><?= $view->partial('icones', ['nom' => 'agenda']) ?></span>
                <?= e($asso['rendez_vous']) ?>
              </p>
            <?php endif; ?>

            <?php if (!empty($asso['contacts'])): ?>
              <ul class="association__contacts">
                <?php foreach ($asso['contacts'] as $c): ?>
                  <li>
                    <span class="association__contact-nom"><?= e($c['nom'] ?? '') ?></span>
                    <?php if (!empty($c['role'])): ?>
                      <span class="association__contact-role"><?= e($c['role']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($c['tel'])): ?>
                      <a href="<?= e(tel_lien($c['tel'])) ?>"><?= e($c['tel']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($c['email'])): ?>
                      <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => 'blanc']) ?>

<?= $view->partial('bande-cta') ?>
