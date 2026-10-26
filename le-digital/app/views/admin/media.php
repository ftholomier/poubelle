<?php /** @var array $items @var string $csrf */ ?>
<section class="card">
  <div class="dropzone" data-dropzone>
    <input type="file" id="media-files" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
    <p><b>Glisse tes photos ici</b> ou <label for="media-files" class="link">choisis des fichiers</label></p>
    <p class="muted small">JPG, PNG, WebP ou GIF · 12 Mo max · converties automatiquement en WebP optimisé avec plusieurs tailles (chargement rapide sur mobile).</p>
    <div class="dropzone__progress" data-progress hidden></div>
  </div>
</section>
<?php if (!$items): ?>
  <div class="card empty"><p>La médiathèque est vide. Envoie tes photos (portrait, en formation, avec un client…) et les captures de tes solutions.</p></div>
<?php else: ?>
  <div class="media-grid">
    <?php foreach ($items as $m): ?>
      <figure class="media-item">
        <img src="<?= e($m['variants'][480] ?? $m['path']) ?>" alt="<?= e($m['alt'] ?? '') ?>" loading="lazy">
        <figcaption>
          <input value="<?= e($m['alt'] ?? '') ?>" placeholder="Texte alternatif (accessibilité)" data-alt="<?= e($m['id']) ?>" aria-label="Texte alternatif">
          <small class="muted"><?= (int) ($m['w'] ?? 0) ?>×<?= (int) ($m['h'] ?? 0) ?> · <?= number_format(($m['size'] ?? 0) / 1024, 0, ',', ' ') ?> Ko</small>
          <div class="media-item__acts">
            <button type="button" class="link" data-copy-text="<?= e($m['path']) ?>">Copier le lien</button>
            <form method="post" action="/admin/medias/<?= e($m['id']) ?>/supprimer" data-confirm="Supprimer cette image ? Vérifie qu’elle n’est plus utilisée sur le site.">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="link-danger" type="submit">Supprimer</button>
            </form>
          </div>
        </figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
