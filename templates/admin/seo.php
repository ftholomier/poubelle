<?php
/**
 * Référencement : adresses, titres et descriptions de toutes les pages.
 *
 * @var array    $routes    catalogue des rubriques
 * @var array    $settings  réglages enregistrés
 * @var array    $pages     pages éditoriales
 * @var string   $ogImage
 * @var string   $notice
 * @var string[] $errors
 */
use App\Core\Csrf;
use App\Services\I18n;
use App\Services\Seo;
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.seo')) ?></h1>
    <p><?= e(I18n::t('admin.seo_note')) ?></p>
  </div>
</div>

<?php if ($notice !== ''): ?><div class="notice notice-ok" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($errors !== []): ?>
  <div class="notice notice-err" role="alert" tabindex="-1" data-error-focus>
    <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post">
  <?= Csrf::field('admin-seo') ?>
  <input type="hidden" name="scope" value="routes">

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2>Rubriques du site</h2></div>
    <p class="secret-help" style="margin-top:0">
      L’adresse est celle qui apparaît après la langue : <code>/fr<strong>/offres</strong></code>.
      La modifier met en place une redirection permanente depuis l’ancienne, y compris pour les
      fiches. Un titre vide laisse celui calculé par la page.
    </p>

    <?php foreach ($routes as $key => $meta): ?>
      <?php
      $saved = (array) ($settings[$key] ?? []);
      $path = Seo::routePath($key);
      $former = Seo::formerPaths($key);
      $isPrefix = !empty($meta['prefix']);
      $vars = Seo::PLACEHOLDERS[$key] ?? [];
      ?>
      <div class="secret-row">
        <div class="secret-head">
          <span class="label"><?= e($meta['label']) ?></span>
          <?php if ($isPrefix): ?>
            <span class="state state-neutral">Préfixe de fiche</span>
          <?php endif; ?>
        </div>

        <div class="grid-fields">
          <label class="field">
            <span class="label">Adresse</span>
            <input class="input" type="text" name="routes[<?= e($key) ?>][path]"
                   value="<?= e($path) ?>" spellcheck="false" autocomplete="off"
                   <?= $key === 'home' ? 'readonly' : '' ?>>
            <?php if ($isPrefix): ?>
              <span class="opt">suivi du slug : <code><?= e($path) ?>/mon-annonce</code></span>
            <?php endif; ?>
            <?php if ($former !== []): ?>
              <span class="opt">redirige depuis <?= e(implode(', ', $former)) ?></span>
            <?php endif; ?>
          </label>

          <label class="field">
            <span class="label">Titre</span>
            <input class="input" type="text" name="routes[<?= e($key) ?>][title]"
                   value="<?= e((string) ($saved['title'] ?? '')) ?>" maxlength="180">
            <?php if ($vars !== []): ?>
              <span class="opt">variables : <?= e(implode(' ', $vars)) ?></span>
            <?php endif; ?>
          </label>
        </div>

        <label class="field" style="margin-top:10px">
          <span class="label">Méta description</span>
          <textarea class="input" name="routes[<?= e($key) ?>][description]" rows="2"
                    maxlength="320"><?= e((string) ($saved['description'] ?? '')) ?></textarea>
          <span class="opt">160 caractères environ. Au-delà, Google tronque.</span>
        </label>

        <label class="check" style="margin-top:8px">
          <input type="checkbox" name="routes[<?= e($key) ?>][robots]" value="noindex"
                 <?= ($saved['robots'] ?? '') === 'noindex' ? 'checked' : '' ?>>
          <span>Exclure des moteurs de recherche (noindex)</span>
        </label>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2>Image de partage</h2></div>
    <p class="secret-help" style="margin-top:0">
      Affichée quand un lien du site est partagé sur un réseau social ou une messagerie.
      Format conseillé : 1200 × 630 pixels. Vide : l’image par défaut du site.
    </p>
    <label class="field">
      <span class="label">Adresse de l’image</span>
      <input class="input" type="text" name="og_image" value="<?= e($ogImage) ?>"
             placeholder="/assets/img/og-default.png" spellcheck="false" autocomplete="off">
    </label>
  </div>

  <div class="save-bar">
    <span class="save-bar-note">
      Une adresse modifiée redirige l’ancienne en 301 : les liens existants et le référencement
      acquis suivent.
    </span>
    <button type="submit" class="btn btn-coral"><?= e(I18n::t('admin.save')) ?></button>
  </div>
</form>

<form method="post" style="margin-top:34px">
  <?= Csrf::field('admin-seo') ?>
  <input type="hidden" name="scope" value="pages">

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2>Pages éditoriales</h2></div>
    <p class="secret-help" style="margin-top:0">
      Le titre sert de <code>h1</code> et de nom dans les listes ; le titre de référencement le
      remplace dans l’onglet du navigateur et sur Google. Changer l’adresse d’une page laisse
      une redirection depuis l’ancienne.
    </p>

    <?php foreach ($pages as $page): ?>
      <div class="secret-row">
        <div class="secret-head">
          <span class="label"><?= e($page['title']) ?></span>
          <span class="state <?= $page['status'] === 'publish' ? 'state-ok' : 'state-wait' ?>">
            <?= e(I18n::t($page['status'] === 'publish' ? 'admin.state_publish' : 'admin.state_draft')) ?>
          </span>
        </div>

        <div class="grid-fields">
          <label class="field">
            <span class="label">Titre de la page</span>
            <input class="input" type="text" name="pages[<?= e($page['slug']) ?>][title]"
                   value="<?= e($page['title']) ?>" maxlength="180">
          </label>
          <label class="field">
            <span class="label">Adresse</span>
            <input class="input" type="text" name="pages[<?= e($page['slug']) ?>][slug]"
                   value="<?= e($page['slug']) ?>" spellcheck="false" autocomplete="off">
            <span class="opt">/fr/<?= e($page['slug']) ?></span>
          </label>
        </div>

        <div class="grid-fields" style="margin-top:10px">
          <label class="field">
            <span class="label">Titre de référencement</span>
            <input class="input" type="text" name="pages[<?= e($page['slug']) ?>][seo_title]"
                   value="<?= e($page['seo_title']) ?>" maxlength="180"
                   placeholder="<?= e($page['title']) ?>">
          </label>
          <label class="field">
            <span class="label">Méta description</span>
            <textarea class="input" name="pages[<?= e($page['slug']) ?>][description]" rows="2"
                      maxlength="320"><?= e($page['description']) ?></textarea>
          </label>
        </div>

        <label class="check" style="margin-top:8px">
          <input type="checkbox" name="pages[<?= e($page['slug']) ?>][robots]" value="noindex"
                 <?= $page['robots'] === 'noindex' ? 'checked' : '' ?>>
          <span>Exclure des moteurs de recherche (noindex)</span>
        </label>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="save-bar">
    <span class="save-bar-note">
      Le contenu des pages se modifie dans « Pages &amp; contenus » ; ici, seuls leur nom, leur
      adresse et leurs métas.
    </span>
    <button type="submit" class="btn btn-coral"><?= e(I18n::t('admin.save')) ?></button>
  </div>
</form>
