<?php
/** Photothèque : ajout, textes alternatifs, légendes, suppression. */

use App\Admin;
use App\Ai\Docs;
use App\Config;
use App\Csrf;
use App\Media;
use App\Router;
use App\Text;
use App\View;

echo View::admin('_layout_start', get_defined_vars());
?>
<div class="screen">
  <section class="panel panel--pad" style="margin-top:24px">
    <form method="post" action="<?= Text::e(Router::adminUrl()) ?>" enctype="multipart/form-data">
      <?= Csrf::field('admin') ?>
      <input type="hidden" name="action" value="media-upload">
      <div class="dropzone">
        <div class="dropzone__title">Ajoutez des photos des espaces et des bureaux</div>
        <div class="dropzone__text">JPEG, PNG ou WebP · 12 Mo maximum par fichier · conversion WebP et dérivés 1600 / 800 / 400 px générés automatiquement, avec largeur et hauteur enregistrées pour éviter tout décalage de mise en page.</div>
        <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
      </div>
      <div class="grid-2" style="margin-top:16px">
        <div>
          <label class="label" for="alt">TEXTE ALTERNATIF PAR DÉFAUT</label>
          <input class="field" id="alt" type="text" name="alt" placeholder="Bureau privé de l'espace Carnot">
          <div class="hint">Décrit la photo pour les lecteurs d'écran et pour Google. Modifiable ensuite photo par photo.</div>
        </div>
        <div style="display:flex;align-items:flex-end">
          <button class="btn btn--ink btn--block" type="submit">Envoyer les photos</button>
        </div>
      </div>
    </form>
  </section>

  <?php if ($media === []): ?>
    <p class="muted" style="margin-top:24px">Aucune photo pour l'instant.</p>
  <?php else: ?>
  <div class="media-grid">
    <?php foreach ($media as $item):
        $path = (string) ($item['path'] ?? '');
        $en = (array) ($item['i18n']['en'] ?? []); ?>
      <div class="media-card">
        <img class="media-card__img" src="<?= Text::e(Config::basePath() . $path) ?>" alt="<?= Text::e((string) ($item['alt'] ?? '')) ?>" loading="lazy">
        <div class="media-card__body">
          <div class="media-card__name"><?= Text::e((string) ($item['name'] ?? basename($path))) ?></div>
          <div class="media-card__meta">
            <?= (int) ($item['width'] ?? 0) ?>×<?= (int) ($item['height'] ?? 0) ?> px ·
            <?= Text::e(Docs::humanSize((int) ($item['bytes'] ?? 0))) ?> ·
            <?= Text::e(Admin::humanDate((string) ($item['at'] ?? ''))) ?>
          </div>

          <form class="media-card__form" method="post" action="<?= Text::e(Router::adminUrl()) ?>">
            <?= Csrf::field('admin') ?>
            <input type="hidden" name="action" value="media-update">
            <input type="hidden" name="path" value="<?= Text::e($path) ?>">
            <input class="field field--sm" type="text" name="alt" value="<?= Text::e((string) ($item['alt'] ?? '')) ?>" placeholder="Texte alternatif (FR)">
            <input class="field field--sm" type="text" name="caption" value="<?= Text::e((string) ($item['caption'] ?? '')) ?>" placeholder="Légende (FR)">
            <input class="field field--sm" type="text" name="alt_en" value="<?= Text::e((string) ($en['alt'] ?? '')) ?>" placeholder="Alt (EN)">
            <input class="field field--sm" type="text" name="caption_en" value="<?= Text::e((string) ($en['caption'] ?? '')) ?>" placeholder="Légende (EN)">
            <button class="btn btn--sm btn--ink" type="submit">Enregistrer</button>
          </form>

          <form method="post" action="<?= Text::e(Router::adminUrl()) ?>" data-confirm="Supprimer cette photo et ses dérivés ?" style="margin-top:8px">
            <?= Csrf::field('admin') ?>
            <input type="hidden" name="action" value="media-delete">
            <input type="hidden" name="path" value="<?= Text::e($path) ?>">
            <button class="btn btn--sm btn--danger btn--block" type="submit">Supprimer</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?= View::admin('_layout_end') ?>
