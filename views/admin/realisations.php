<?php
/**
 * Réalisations : la galerie, sur un seul écran.
 *
 * Une fiche tient en trois champs — nom, catégorie, photo — donc tout est
 * édité d'un bloc plutôt qu'une page par photo, qui ferait quarante
 * allers-retours pour un simple reclassement.
 *
 * @var array $galerie
 * @var array $pageIntro
 * @var string[] $medias
 * @var App\Core\View $view
 */
use App\Core\Content;
use App\Core\Csrf;

$items = $galerie['items'] ?? [];
$dernier = count($items) - 1;

// Les catégories déjà employées servent de suggestions : la saisie reste
// libre, mais on évite « Carport » et « carports » dans la même galerie.
$categories = [];
foreach ($items as $item) {
    $c = trim((string) ($item['categorie'] ?? ''));
    if ($c !== '' && !in_array($c, $categories, true)) {
        $categories[] = $c;
    }
}
sort($categories);
?>
<p class="bo-aide">
  Les réalisations publiées apparaissent sur la page « Réalisations ».
  Les boutons de filtre de cette page se construisent tout seuls à partir des
  catégories saisies ici : écrivez « Carport » sur trois photos et le filtre
  « Carport » apparaît, videz-les et il disparaît.
</p>

<form class="bo-form" method="post" action="<?= url('/admin/realisations') ?>" enctype="multipart/form-data">
  <?= Csrf::champ() ?>

  <?php if ($items === []): ?>
    <p class="bo-vide">Aucune réalisation pour l’instant. Créez-en une ci-dessous.</p>
  <?php endif; ?>

  <?php if ($categories !== []): ?>
    <datalist id="r-categories">
      <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
    </datalist>
  <?php endif; ?>

  <?php foreach ($items as $rang => $item):
      $slug = (string) $item['slug'];
      $publie = Content::estPublie($item); ?>
    <fieldset<?= $publie ? '' : ' class="bo-ligne--masquee"' ?>>
      <legend><?= e($item['nom']) ?><?= $publie ? '' : ' — hors ligne' ?></legend>

      <div class="bo-rangee">
        <div class="bo-champ">
          <label for="r-n<?= $rang ?>">Nom</label>
          <input id="r-n<?= $rang ?>" type="text" name="nom_<?= e($slug) ?>" value="<?= e($item['nom']) ?>">
        </div>
        <div class="bo-champ">
          <label for="r-c<?= $rang ?>">Catégorie</label>
          <input id="r-c<?= $rang ?>" type="text" name="categorie_<?= e($slug) ?>"
                 value="<?= e($item['categorie'] ?? '') ?>" list="r-categories"
                 placeholder="Pergola">
        </div>
      </div>
      <div class="bo-champ">
        <label for="r-l<?= $rang ?>">Légende</label>
        <input id="r-l<?= $rang ?>" type="text" name="legende_<?= e($slug) ?>" value="<?= e($item['legende'] ?? '') ?>">
        <p class="bo-aide">Facultative. Elle sert aussi de texte de remplacement de l’image ; à défaut, c’est le nom qui est employé.</p>
      </div>
      <div class="bo-champ">
        <label>Photo</label>
        <?= $view->partial('admin/choix-photo', [
            'medias' => $medias, 'nom' => 'image_' . $slug, 'id' => 'rea' . $rang,
            'choisie' => $item['image'] ?? '', 'vide' => 'Aucune',
        ]) ?>
      </div>
    </fieldset>
  <?php endforeach; ?>

  <?php if ($items !== []): ?>
    <button class="bo-btn" type="submit">Enregistrer les réalisations</button>
  <?php endif; ?>
</form>

<?php if ($items !== []): ?>
<div class="bo-bloc">
  <h2>Ordre et publication</h2>
  <ul class="bo-liste">
    <?php foreach ($items as $rang => $item):
        $publie = Content::estPublie($item); ?>
      <li class="bo-ligne<?= $publie ? '' : ' bo-ligne--masquee' ?>">
        <div class="bo-ligne__corps">
          <strong><?= e($item['nom']) ?></strong>
          <span class="bo-ligne__note">
            <?= e($item['categorie'] ?? '') !== '' ? e($item['categorie']) . ' · ' : '' ?><?= $publie ? 'En ligne' : 'Hors ligne' ?>
          </span>
        </div>
        <div class="bo-ligne__actions">
          <form method="post" action="<?= url('/admin/realisations/' . $item['slug'] . '/publication') ?>">
            <?= Csrf::champ() ?>
            <button class="bo-btn bo-btn--petit bo-btn--fantome" type="submit">
              <?= $publie ? 'Retirer du site' : 'Publier' ?>
            </button>
          </form>
          <form method="post" action="<?= url('/admin/realisations/' . $item['slug'] . '/ordre') ?>">
            <?= Csrf::champ() ?>
            <input type="hidden" name="sens" value="haut">
            <button class="bo-btn bo-btn--petit bo-btn--fantome" type="submit"
                    aria-label="Monter <?= e($item['nom']) ?>"<?= $rang === 0 ? ' disabled' : '' ?>>↑</button>
          </form>
          <form method="post" action="<?= url('/admin/realisations/' . $item['slug'] . '/ordre') ?>">
            <?= Csrf::champ() ?>
            <input type="hidden" name="sens" value="bas">
            <button class="bo-btn bo-btn--petit bo-btn--fantome" type="submit"
                    aria-label="Descendre <?= e($item['nom']) ?>"<?= $rang === $dernier ? ' disabled' : '' ?>>↓</button>
          </form>
          <form method="post" action="<?= url('/admin/realisations/' . $item['slug'] . '/supprimer') ?>"
                data-confirmer="Supprimer la réalisation « <?= e($item['nom']) ?> » ?">
            <?= Csrf::champ() ?>
            <button class="bo-btn bo-btn--petit bo-btn--danger" type="submit">Supprimer</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="bo-bloc">
  <h2>Ajouter une réalisation</h2>
  <form class="bo-form bo-form--inline" method="post" action="<?= url('/admin/realisations/creer') ?>">
    <?= Csrf::champ() ?>
    <div class="bo-champ">
      <label for="r-nom" class="bo-visuellement-cache">Nom</label>
      <input id="r-nom" type="text" name="nom" placeholder="Pergola à Vellevans" required>
    </div>
    <div class="bo-champ">
      <label for="r-cat" class="bo-visuellement-cache">Catégorie</label>
      <input id="r-cat" type="text" name="categorie" placeholder="Pergola" list="r-categories">
    </div>
    <button class="bo-btn" type="submit">Créer</button>
  </form>
  <p class="bo-aide">
    Pour ajouter plusieurs photos d’un coup, déposez-les d’abord dans
    <a href="<?= url('/admin/photos') ?>">Photos</a>, puis créez une fiche par photo.
  </p>
</div>

<form class="bo-form" method="post" action="<?= url('/admin/realisations/entete') ?>" enctype="multipart/form-data">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>En-tête de la page « Réalisations »</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="pr-sur">Sur-titre</label>
        <input id="pr-sur" type="text" name="hero_surtitre" value="<?= e($pageIntro['hero']['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="pr-tit">Titre principal (H1)</label>
        <input id="pr-tit" type="text" name="hero_titre" value="<?= e($pageIntro['hero']['titre'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="pr-tex">Texte d’introduction</label>
      <textarea id="pr-tex" name="hero_texte" rows="3"><?= e($pageIntro['hero']['texte'] ?? '') ?></textarea>
    </div>
    <div class="bo-champ">
      <label>Photo du bandeau</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'hero_image', 'id' => 'reahero',
          'choisie' => $pageIntro['hero']['image'] ?? '', 'vide' => '',
      ]) ?>
    </div>
    <div class="bo-champ">
      <label for="pr-int">Chapeau au-dessus de la galerie</label>
      <textarea id="pr-int" name="intro" rows="2"><?= e($galerie['intro'] ?? '') ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="pr-meta">Description pour les moteurs</label>
      <textarea id="pr-meta" name="meta_description" rows="2"><?= e($pageIntro['meta']['description'] ?? '') ?></textarea>
    </div>
  </fieldset>
  <button class="bo-btn" type="submit">Enregistrer l’en-tête</button>
</form>
