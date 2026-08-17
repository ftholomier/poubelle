<?php
/**
 * Photos d'un hébergement ou d'un étang.
 *
 * @var string $type          'hebergements' ou 'peche'
 * @var array $item
 * @var array $zones          emplacements avec leur rôle et leurs images
 * @var string[] $mediatheque
 * @var App\Core\Mediatheque $media
 * @var string $retour
 * @var string $apercu
 */
use App\Core\Csrf;

$action = url('/admin/' . $type . '/' . rawurlencode($item['slug']) . '/photos');
?>
<p style="margin-bottom:1.4rem;">
  <a href="<?= url($retour) ?>">← Retour à la fiche</a>
  · <a href="<?= url($apercu) ?>" target="_blank" rel="noopener">Voir la page ↗</a>
</p>

<?php foreach ($zones as $zone): ?>
  <section class="bo-zone">
    <header class="bo-zone__tete">
      <h2><?= e($zone['titre']) ?></h2>
      <p><?= e($zone['aide']) ?></p>
    </header>

    <?php if ($zone['images'] === []): ?>
      <p class="bo-zone__vide">Aucune photo pour l'instant.</p>
    <?php else: ?>
      <div class="bo-zone__photos">
        <?php foreach ($zone['images'] as $i => $chemin): ?>
          <figure class="bo-photo">
            <img src="<?= image($chemin, true) ?>" alt="" loading="lazy">
            <figcaption>
              <span class="bo-photo__role">
                <?= e($zone['roles'][$i] ?? 'Photo ' . ($i + 1) . ' — non utilisée') ?>
              </span>
              <form method="post" action="<?= $action ?>" class="bo-photo__actions">
                <?= Csrf::champ() ?>
                <input type="hidden" name="zone" value="<?= e($zone['cle']) ?>">
                <input type="hidden" name="index" value="<?= $i ?>">
                <?php if (count($zone['images']) > 1): ?>
                  <button name="action" value="monter" title="Monter" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
                  <button name="action" value="descendre" title="Descendre" <?= $i === count($zone['images']) - 1 ? 'disabled' : '' ?>>↓</button>
                <?php endif; ?>
                <button name="action" value="retirer" title="Retirer" class="retirer"
                        onclick="return confirm('Retirer cette photo de cet emplacement ?')">✕</button>
              </form>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <details class="bo-choix">
      <summary>
        <?= $zone['max'] === 1 && $zone['images'] !== [] ? 'Remplacer la photo' : 'Ajouter une photo' ?>
      </summary>

      <form method="post" action="<?= $action ?>" class="bo-choix__corps">
        <?= Csrf::champ() ?>
        <input type="hidden" name="zone" value="<?= e($zone['cle']) ?>">

        <div class="bo-choix__grille">
          <?php foreach ($mediatheque as $n => $chemin): ?>
            <label class="bo-choix__vignette">
              <input type="<?= $zone['max'] === 1 ? 'radio' : 'checkbox' ?>" name="ajout[]" value="<?= e($chemin) ?>">
              <img src="<?= image($chemin, true) ?>" alt="<?= e(basename($chemin)) ?>" loading="lazy">
            </label>
          <?php endforeach; ?>
        </div>

        <button class="bo-btn" name="action" value="ajouter" type="submit">
          <?= $zone['max'] === 1 ? 'Placer la photo choisie' : 'Ajouter les photos choisies' ?>
        </button>
      </form>

      <form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="bo-choix__envoi">
        <?= Csrf::champ() ?>
        <input type="hidden" name="zone" value="<?= e($zone['cle']) ?>">
        <div class="bo-champ">
          <label for="up-<?= e($zone['cle']) ?>">…ou envoyer une nouvelle photo</label>
          <input id="up-<?= e($zone['cle']) ?>" type="file" name="image"
                 accept="image/jpeg,image/png,image/webp" required>
          <span class="aide">Redimensionnée automatiquement (1920 px + vignette).</span>
        </div>
        <button class="bo-btn bo-btn--contour" name="action" value="televerser" type="submit">Envoyer et placer</button>
      </form>
    </details>
  </section>
<?php endforeach; ?>
