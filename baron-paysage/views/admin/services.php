<?php
/**
 * Liste des services.
 *
 * @var array $services
 * @var array $pageIntro
 */
use App\Core\Content;
use App\Core\Csrf;

$items = $services['items'] ?? [];
$dernier = count($items) - 1;
?>
<div class="bo-bloc">
  <h2>Vos services</h2>
  <p class="bo-aide">
    Chaque service a sa propre page, et apparaît dans le sous-menu de la
    rubrique « Nos services ». L’ordre ci-dessous est celui du site.
  </p>

  <?php if ($items === []): ?>
    <p class="bo-vide">Aucun service pour l’instant. Créez-en un ci-dessous.</p>
  <?php else: ?>
    <ul class="bo-liste">
      <?php foreach ($items as $rang => $item):
          $publie = Content::estPublie($item); ?>
        <li class="bo-ligne<?= $publie ? '' : ' bo-ligne--masquee' ?>">
          <div class="bo-ligne__corps">
            <strong><?= e($item['nom']) ?></strong>
            <span class="bo-ligne__note">
              /<?= e($item['slug']) ?>
              <?= $publie ? '' : ' — hors ligne' ?>
            </span>
            <?php if (($item['resume'] ?? '') !== ''): ?>
              <p><?= e($item['resume']) ?></p>
            <?php endif; ?>
          </div>

          <div class="bo-ligne__actions">
            <a class="bo-btn bo-btn--petit" href="<?= url('/admin/services/' . $item['slug']) ?>">Modifier</a>

            <form method="post" action="<?= url('/admin/services/' . $item['slug'] . '/publication') ?>">
              <?= Csrf::champ() ?>
              <button class="bo-btn bo-btn--petit bo-btn--fantome" type="submit">
                <?= $publie ? 'Retirer du site' : 'Publier' ?>
              </button>
            </form>

            <form method="post" action="<?= url('/admin/services/' . $item['slug'] . '/ordre') ?>">
              <?= Csrf::champ() ?>
              <input type="hidden" name="sens" value="haut">
              <button class="bo-btn bo-btn--petit bo-btn--fantome" type="submit"
                      aria-label="Monter <?= e($item['nom']) ?>"<?= $rang === 0 ? ' disabled' : '' ?>>↑</button>
            </form>
            <form method="post" action="<?= url('/admin/services/' . $item['slug'] . '/ordre') ?>">
              <?= Csrf::champ() ?>
              <input type="hidden" name="sens" value="bas">
              <button class="bo-btn bo-btn--petit bo-btn--fantome" type="submit"
                      aria-label="Descendre <?= e($item['nom']) ?>"<?= $rang === $dernier ? ' disabled' : '' ?>>↓</button>
            </form>

            <form method="post" action="<?= url('/admin/services/' . $item['slug'] . '/supprimer') ?>"
                  data-confirmer="Supprimer « <?= e($item['nom']) ?> » ? La version précédente restera restaurable depuis l’Éditeur avancé.">
              <?= Csrf::champ() ?>
              <button class="bo-btn bo-btn--petit bo-btn--danger" type="submit">Supprimer</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="bo-bloc">
  <h2>Ajouter un service</h2>
  <form class="bo-form bo-form--inline" method="post" action="<?= url('/admin/services/creer') ?>">
    <?= Csrf::champ() ?>
    <div class="bo-champ">
      <label for="s-nom" class="bo-visuellement-cache">Nom du service</label>
      <input id="s-nom" type="text" name="nom" placeholder="Fiscalité" required>
    </div>
    <button class="bo-btn" type="submit">Créer</button>
  </form>
  <p class="bo-aide">Le service est créé hors ligne : complétez sa fiche, puis publiez-la.</p>
</div>

<form class="bo-form" method="post" action="<?= url('/admin/services/entete') ?>">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>En-tête de la page « Nos services »</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="p-sur">Sur-titre</label>
        <input id="p-sur" type="text" name="hero_surtitre" value="<?= e($pageIntro['hero']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="p-tit">Titre principal (H1)</label>
        <input id="p-tit" type="text" name="hero_titre" value="<?= e($pageIntro['hero']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="p-tex">Texte d’introduction</label>
      <textarea id="p-tex" name="hero_texte" rows="3"><?= e($pageIntro['hero']['texte']) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="p-meta">Description affichée dans Google</label>
      <textarea id="p-meta" name="meta_description" rows="2"><?= e($pageIntro['meta']['description'] ?? '') ?></textarea>
    </div>
  </fieldset>
  <button class="bo-btn" type="submit">Enregistrer l’en-tête</button>
</form>
