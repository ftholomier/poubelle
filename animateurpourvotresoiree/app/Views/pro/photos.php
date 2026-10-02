<?php
use App\Services\Pros;

/** @var array $pro @var int $max */
$photos = array_values((array) ($pro['photos'] ?? []));
?>
<div class="pro-head">
  <div><p class="mono muted small">Photos & vidéos</p><h1 class="h2">Montrez <span class="serif c-coral">l'ambiance</span></h1></div>
  <span class="tag"><?= count($photos) ?> / <?= (int) $max ?> photos</span>
</div>
<p class="lead mt-1">Les fiches avec au moins 3 photos reçoivent beaucoup plus de demandes. Formats JPG, PNG ou WebP, 10 Mo maximum par photo : elles sont automatiquement optimisées.</p>

<?php if (count($photos) < $max): ?>
<form class="box dropzone mt-2" method="post" action="/espace-pro/photos/" enctype="multipart/form-data" data-photo-upload>
  <?= csrf_field() ?>
  <label class="dz-label">
    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
    <span class="dz-ico"><?= icon('upload', 34) ?></span>
    <b>Glissez vos photos ici</b>
    <span class="muted small">ou cliquez pour les choisir (plusieurs possibles)</span>
  </label>
  <div class="dz-progress hidden"><i></i></div>
  <button class="btn btn-ink btn-sm" type="submit">Envoyer</button>
</form>
<?php endif; ?>

<?php if (!$photos): ?>
  <div class="empty">Aucune photo pour l'instant 📷</div>
<?php else: ?>
  <ul class="photo-grid mt-3">
    <?php foreach ($photos as $i => $ph): ?>
      <li class="photo-item<?= !empty($ph['cover']) ? ' is-cover' : '' ?>">
        <img src="<?= e(Pros::photo($ph, 'sm')) ?>" alt="<?= e($ph['alt'] ?? '') ?>" loading="lazy">
        <?php if (!empty($ph['cover'])): ?><span class="tag cover-tag">★ Principale</span><?php endif; ?>
        <form method="post" action="/espace-pro/photos/action" class="photo-tools">
          <?= csrf_field() ?>
          <input type="hidden" name="photo" value="<?= e($ph['id']) ?>">
          <div class="row-wrap">
            <?php if (empty($ph['cover'])): ?><button class="btn btn-xs" name="action" value="cover" title="Mettre en photo principale">★</button><?php endif; ?>
            <?php if ($i > 0): ?><button class="btn btn-xs" name="action" value="left" title="Déplacer avant" aria-label="Déplacer avant">←</button><?php endif; ?>
            <?php if ($i < count($photos) - 1): ?><button class="btn btn-xs" name="action" value="right" title="Déplacer après" aria-label="Déplacer après">→</button><?php endif; ?>
            <button class="btn btn-xs" name="action" value="delete" title="Supprimer" aria-label="Supprimer" data-confirm-click="Supprimer cette photo ?"><?= icon('trash', 14) ?></button>
          </div>
          <div class="alt-row"><input type="text" name="alt" maxlength="120" value="<?= e($ph['alt'] ?? '') ?>" aria-label="Légende de la photo" placeholder="Légende (ex. DJ au mariage de…)"><button class="btn btn-xs" name="action" value="alt">OK</button></div>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<div class="box mt-3">
  <h2>Vidéos</h2>
  <p class="small">Ajoutez vos vidéos YouTube ou Vimeo depuis <a href="/espace-pro/fiche/#f-videos">votre fiche</a> : elles s'affichent sans ralentir la page (lecture au clic).</p>
</div>
