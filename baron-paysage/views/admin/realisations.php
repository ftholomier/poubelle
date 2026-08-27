<?php
/**
 * Réalisations : une catégorie à la fois, on coche les photos, on légende.
 *
 * @var array<string, string> $categories  slug => nom affiché
 * @var string $courante slug de la catégorie affichée
 * @var array $galerie
 * @var array $pageIntro
 * @var string[] $medias toute la médiathèque
 * @var App\Core\View $view
 */
use App\Core\Csrf;

$parGamme = (array) ($galerie['gammes'] ?? []);

// Une entrée est soit un chemin seul, soit un couple chemin + légende : les
// deux formes se croisent tant que tout n'a pas été réenregistré.
$choisies = [];
$legendes = [];
foreach ((array) ($parGamme[$courante] ?? []) as $photo) {
    $chemin = is_array($photo) ? (string) ($photo['image'] ?? '') : (string) $photo;
    if ($chemin === '') {
        continue;
    }
    $choisies[] = $chemin;
    $legendes[$chemin] = is_array($photo) ? (string) ($photo['legende'] ?? '') : '';
}

$nomCourant = (string) ($categories[$courante] ?? '');

// Les photos déjà cochées remontent en tête : on voit d'un coup d'œil ce que
// contient la catégorie, sans avoir à parcourir toute la médiathèque.
$ordre = array_merge($choisies, array_values(array_diff($medias, $choisies)));
?>
<p class="bo-aide">
  Choisissez une catégorie, puis cochez les photos à y montrer. Elles
  apparaissent dans la galerie « Réalisations » du site, où le visiteur les
  filtre par catégorie. Une photo peut appartenir à plusieurs catégories.
  Une fois cochée, elle reçoit sa légende dans la liste du bas.
</p>

<?php if ($categories === []): ?>
  <p class="bo-vide">Aucune catégorie. Publiez une prestation dans <a href="<?= url('/admin/services') ?>">Services</a>.</p>
<?php else: ?>

<nav class="bo-onglets" aria-label="Catégories">
  <?php foreach ($categories as $slug => $nom): ?>
    <?php $n = count((array) ($parGamme[$slug] ?? [])); ?>
    <a class="bo-onglet<?= $slug === $courante ? ' bo-onglet--actif' : '' ?>"
       href="<?= url('/admin/realisations?gamme=' . rawurlencode((string) $slug)) ?>"
       <?= $slug === $courante ? 'aria-current="page"' : '' ?>>
      <?= e($nom) ?>
      <span class="bo-onglet__compte"><?= $n ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<form class="bo-form" method="post" action="<?= url('/admin/realisations') ?>">
  <?= Csrf::champ() ?>
  <input type="hidden" name="gamme" value="<?= e($courante) ?>">

  <div class="bo-bloc">
    <h2><?= e($nomCourant) ?></h2>
    <p class="bo-aide">
      <strong><?= count($choisies) ?></strong> photo(s) cochée(s) sur
      <?= count($medias) ?> dans la médiathèque.
      Les photos de cette gamme sont affichées en premier.
      <?php /* Deux boutons de confort : sur quarante vignettes, cocher à la
               main pour tout retirer est une corvée. */ ?>
      <button type="button" class="bo-btn bo-btn--petit bo-btn--fantome" data-cocher-tout>Tout cocher</button>
      <button type="button" class="bo-btn bo-btn--petit bo-btn--fantome" data-cocher-rien>Tout décocher</button>
    </p>

    <?php if ($medias === []): ?>
      <p class="bo-vide">
        Aucune photo dans la médiathèque.
        <a href="<?= url('/admin/photos') ?>">Ajoutez-en d’abord</a>.
      </p>
    <?php else: ?>
      <ul class="bo-planche">
        <?php foreach ($ordre as $rang => $media): ?>
          <?php $coche = in_array($media, $choisies, true); ?>
          <li>
            <label class="bo-planche__photo<?= $coche ? ' bo-planche__photo--choisie' : '' ?>">
              <input type="checkbox" name="photos[]" value="<?= e($media) ?>"<?= $coche ? ' checked' : '' ?>>
              <img src="<?= image($media, true) ?>" alt="<?= e(basename($media)) ?>" loading="lazy">
              <span class="bo-planche__marque" aria-hidden="true"></span>
              <span class="bo-planche__nom"><?= e(basename($media)) ?></span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($choisies !== []): ?>
      <?php /* Les légendes sont saisies à part de la planche à cocher : un
               champ texte sous chaque vignette rendrait la planche illisible,
               et la légende ne concerne que les photos retenues. */ ?>
      <div class="bo-legendes">
        <h3>Légendes</h3>
        <p class="bo-aide">
          La légende s’affiche sous la photo agrandie, et sur la vignette au
          survol. Laissée vide, la photo prend le nom de sa catégorie.
        </p>
        <?php foreach ($choisies as $chemin): ?>
          <div class="bo-legende">
            <img src="<?= image($chemin, true) ?>" alt="" loading="lazy">
            <label class="bo-champ">
              <span class="bo-legende__nom"><?= e(basename($chemin)) ?></span>
              <input type="text" name="legendes[<?= e($chemin) ?>]"
                     value="<?= e($legendes[$chemin] ?? '') ?>"
                     placeholder="Terrasse en bois exotique type IPE">
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <button class="bo-btn" type="submit">Enregistrer cette catégorie</button>
  </div>
</form>

<?php endif; ?>

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
