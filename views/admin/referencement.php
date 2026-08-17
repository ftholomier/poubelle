<?php
/**
 * Écran Référencement : adresses, balises, indexation, redirections.
 *
 * @var array $general
 * @var array $pages
 * @var array $fiches
 * @var array $redirections
 * @var string[] $photos
 * @var array $limites
 */
use App\Core\Csrf;

$libelles = ['hebergements' => 'Hébergements', 'peche' => 'Étangs de pêche'];
?>

<nav class="bo-onglets-liens">
  <a href="#general">Réglages généraux</a>
  <a href="#pages">Pages du site</a>
  <a href="#fiches">Fiches</a>
  <a href="#redirections">Redirections</a>
  <a href="<?= url('/sitemap.xml') ?>" target="_blank" rel="noopener">sitemap.xml ↗</a>
  <a href="<?= url('/robots.txt') ?>" target="_blank" rel="noopener">robots.txt ↗</a>
</nav>

<!-- ------------------------------------------------------ réglages généraux -->
<section id="general" class="bo-zone">
  <h2>Réglages généraux</h2>
  <form class="bo-form" method="post" action="<?= url('/admin/referencement/general') ?>">
    <?= Csrf::champ() ?>

    <div class="bo-champ bo-champ--case">
      <label for="indexer-site">
        <input id="indexer-site" type="checkbox" name="indexer_site" value="1"<?= $general['indexer_site'] ? ' checked' : '' ?>>
        Autoriser les moteurs de recherche à référencer le site
      </label>
      <span class="aide">Décochez pendant les travaux : robots.txt interdit alors tout le site.
        N’oubliez pas de recocher à la mise en ligne, sinon le site restera invisible sur Google.</span>
    </div>

    <div class="bo-champ">
      <span class="bo-etiquette-champ">Image de partage par défaut</span>
      <span class="aide">Visuel affiché quand un lien du site est partagé sur Facebook, WhatsApp
        ou dans un e-mail. Une photo large (paysage) donne le meilleur rendu.</span>
      <details class="bo-repli">
        <summary>Choisir l’image de partage</summary>
        <?= $view->partial('admin/choix-photo', [
              'medias'  => $photos, 'nom' => 'image_partage', 'id' => 'partage',
              'choisie' => $general['image_partage'],
              'vide'    => 'Grande photo de la page d’accueil',
            ]) ?>
      </details>
    </div>

    <button class="bo-btn" type="submit">Enregistrer</button>
  </form>
</section>

<!-- ------------------------------------------------------------------ pages -->
<section id="pages" class="bo-zone">
  <h2>Pages du site</h2>
  <p class="bo-intro">Chaque page a son adresse et ses balises. Laissez le titre ou la description
    vides pour garder ceux de la page. Changer une adresse crée automatiquement une redirection
    permanente (301) depuis l’ancienne : aucun lien existant ne se casse.</p>

  <?php foreach ($pages as $cle => $p): ?>
    <details class="bo-seo" id="page-<?= e($cle) ?>">
      <summary>
        <span class="bo-seo__nom"><?= e($p['nom']) ?></span>
        <code class="bo-seo__url"><?= e($p['chemin']) ?></code>
        <?php if (!$p['indexer']): ?><span class="bo-etiquette">non indexée</span><?php endif; ?>
        <?php if ($p['alertes'] !== []): ?>
          <span class="bo-etiquette bo-etiquette--alerte"><?= count($p['alertes']) ?> à revoir</span>
        <?php endif; ?>
      </summary>

      <div class="bo-seo__apercu">
        <p class="bo-seo__apercu-titre"><?= e($p['effectif']['titre']) ?></p>
        <p class="bo-seo__apercu-url"><?= e(url($p['chemin'])) ?></p>
        <p class="bo-seo__apercu-desc"><?= e($p['effectif']['description']) ?></p>
      </div>

      <?php foreach ($p['alertes'] as $alerte): ?>
        <p class="bo-message bo-message--alerte"><?= e($alerte) ?></p>
      <?php endforeach; ?>

      <form class="bo-form" method="post"
            action="<?= url('/admin/referencement/page/' . rawurlencode($cle)) ?>">
        <?= Csrf::champ() ?>

        <div class="bo-champ">
          <label for="slug-<?= e($cle) ?>">Adresse de la page</label>
          <?php if ($cle === 'accueil'): ?>
            <input id="slug-<?= e($cle) ?>" type="text" value="/" disabled>
            <span class="aide">La page d’accueil est servie à la racine du site : son adresse
              n’est pas modifiable.</span>
          <?php else: ?>
            <div class="bo-prefixe">
              <span><?= e(rtrim(url('/'), '/')) ?>/</span>
              <input id="slug-<?= e($cle) ?>" type="text" name="slug" value="<?= e($p['slug']) ?>"
                     required data-slug spellcheck="false" autocapitalize="off" autocomplete="off"
                     aria-describedby="aide-slug-<?= e($cle) ?>">
            </div>
            <span class="aide" id="aide-slug-<?= e($cle) ?>">Écrivez comme vous parlez —
              « Hébergements Territoire de Belfort » devient
              <code>hebergements-territoire-de-belfort</code>. Accents, majuscules,
              espaces et ponctuation sont convertis à l’enregistrement.
              <?php if (isset($p['collection'])): ?>
                Cette adresse préfixe aussi celle des fiches
                (<code><?= e($p['chemin']) ?>/nom-de-la-fiche</code>).
              <?php endif; ?>
            </span>
          <?php endif; ?>
        </div>

        <div class="bo-champ">
          <label for="titre-<?= e($cle) ?>">Titre pour les moteurs</label>
          <input id="titre-<?= e($cle) ?>" type="text" name="titre" value="<?= e($p['titre']) ?>"
                 maxlength="120" data-compteur="<?= (int) $limites['titre_max'] ?>"
                 placeholder="<?= e($p['effectif']['titre']) ?>">
          <span class="aide">Vide = titre de la page suivi du nom du site.
            Visez <?= (int) $limites['titre_max'] ?> caractères au plus.</span>
        </div>

        <div class="bo-champ">
          <label for="desc-<?= e($cle) ?>">Description</label>
          <textarea id="desc-<?= e($cle) ?>" name="description" rows="3" maxlength="320"
                    data-compteur="<?= (int) $limites['description_max'] ?>"
                    placeholder="<?= e($p['effectif']['description']) ?>"><?= e($p['description']) ?></textarea>
          <span class="aide">Le texte affiché sous le titre dans Google. Entre
            <?= (int) $limites['description_min'] ?> et <?= (int) $limites['description_max'] ?> caractères.</span>
        </div>

        <div class="bo-champ">
          <span class="bo-etiquette-champ">Image de partage de cette page</span>
          <details class="bo-repli">
            <summary>Choisir une image propre à cette page</summary>
            <?= $view->partial('admin/choix-photo', [
                  'medias'  => $photos, 'nom' => 'image', 'id' => 'img-' . $cle,
                  'choisie' => $p['image'],
                  'vide'    => 'Image de partage par défaut',
                ]) ?>
          </details>
        </div>

        <div class="bo-champ bo-champ--case">
          <label for="idx-<?= e($cle) ?>">
            <input id="idx-<?= e($cle) ?>" type="checkbox" name="indexer" value="1"<?= $p['indexer'] ? ' checked' : '' ?>>
            Faire apparaître cette page dans les moteurs de recherche
          </label>
          <span class="aide">Décochée, la page reste accessible mais sort du plan du site
            et demande aux moteurs de ne pas l’afficher.</span>
        </div>

        <button class="bo-btn" type="submit">Enregistrer</button>
      </form>
    </details>
  <?php endforeach; ?>
</section>

<!-- ----------------------------------------------------------------- fiches -->
<section id="fiches" class="bo-zone">
  <h2>Fiches</h2>
  <p class="bo-intro">Hébergements et étangs ont chacun leur adresse. La renommer crée là aussi
    une redirection permanente depuis l’ancienne.</p>

  <?php foreach ($fiches as $cle => $liste): ?>
    <h3 class="bo-sous-titre"><?= e($libelles[$cle] ?? $cle) ?></h3>
    <?php foreach ($liste as $f): ?>
      <details class="bo-seo">
        <summary>
          <span class="bo-seo__nom"><?= e($f['nom']) ?></span>
          <code class="bo-seo__url"><?= e($f['chemin']) ?></code>
          <?php if (!$f['en_ligne']): ?><span class="bo-etiquette">hors ligne</span><?php endif; ?>
          <?php if ($f['alertes'] !== []): ?>
            <span class="bo-etiquette bo-etiquette--alerte"><?= count($f['alertes']) ?> à revoir</span>
          <?php endif; ?>
        </summary>

        <div class="bo-seo__apercu">
          <p class="bo-seo__apercu-titre"><?= e($f['effectif']['titre']) ?></p>
          <p class="bo-seo__apercu-url"><?= e(url($f['chemin'])) ?></p>
          <p class="bo-seo__apercu-desc"><?= e($f['effectif']['description']) ?></p>
        </div>

        <?php foreach ($f['alertes'] as $alerte): ?>
          <p class="bo-message bo-message--alerte"><?= e($alerte) ?></p>
        <?php endforeach; ?>

        <form class="bo-form" method="post"
              action="<?= url('/admin/referencement/fiche/' . rawurlencode($cle) . '/' . rawurlencode($f['slug'])) ?>">
          <?= Csrf::champ() ?>

          <div class="bo-champ">
            <label for="fs-<?= e($cle . '-' . $f['slug']) ?>">Adresse de la fiche</label>
            <div class="bo-prefixe">
              <span><?= e(rtrim(url($pages[$cle]['chemin']), '/')) ?>/</span>
              <input id="fs-<?= e($cle . '-' . $f['slug']) ?>" type="text" name="slug"
                     value="<?= e($f['slug']) ?>" required data-slug
                     spellcheck="false" autocapitalize="off" autocomplete="off">
            </div>
            <span class="aide">Accents, majuscules et espaces sont convertis à
              l’enregistrement.</span>
          </div>

          <div class="bo-champ">
            <label for="fd-<?= e($cle . '-' . $f['slug']) ?>">Description</label>
            <textarea id="fd-<?= e($cle . '-' . $f['slug']) ?>" name="description" rows="3"
                      maxlength="320" data-compteur="<?= (int) $limites['description_max'] ?>"
                      placeholder="<?= e($f['effectif']['description']) ?>"><?= e($f['description']) ?></textarea>
          </div>

          <button class="bo-btn" type="submit">Enregistrer</button>
        </form>
      </details>
    <?php endforeach; ?>
  <?php endforeach; ?>
</section>

<!-- ---------------------------------------------------------- redirections -->
<section id="redirections" class="bo-zone">
  <h2>Redirections permanentes</h2>
  <p class="bo-intro">Une redirection 301 envoie une ancienne adresse vers la nouvelle et transmet
    aux moteurs le référencement acquis. Celles créées par un changement d’adresse apparaissent ici
    automatiquement ; vous pouvez en ajouter d’autres, par exemple pour une adresse imprimée
    sur un dépliant.</p>

  <?php if ($redirections === []): ?>
    <p class="bo-vide">Aucune redirection enregistrée pour l’instant.</p>
  <?php else: ?>
    <table class="bo-tableau">
      <thead><tr><th>Ancienne adresse</th><th>Redirige vers</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($redirections as $depuis => $vers): ?>
          <tr>
            <td><code><?= e($depuis) ?></code></td>
            <td><code><?= e($vers) ?></code></td>
            <td class="bo-tableau__action">
              <form method="post" action="<?= url('/admin/referencement/redirection/retrait') ?>"
                    onsubmit="return confirm('Supprimer la redirection depuis <?= e($depuis) ?> ? Cette adresse renverra alors une page introuvable.')">
                <?= Csrf::champ() ?>
                <input type="hidden" name="depuis" value="<?= e($depuis) ?>">
                <button class="bo-lien-danger" type="submit">Supprimer</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <form class="bo-form" method="post" action="<?= url('/admin/referencement/redirection') ?>">
    <?= Csrf::champ() ?>
    <fieldset>
      <legend>Ajouter une redirection</legend>
      <div class="bo-rangee">
        <div class="bo-champ">
          <label for="r-depuis">Ancienne adresse</label>
          <input id="r-depuis" type="text" name="depuis" required placeholder="/ancienne-page">
        </div>
        <div class="bo-champ">
          <label for="r-vers">Redirige vers</label>
          <input id="r-vers" type="text" name="vers" required placeholder="/hebergements">
        </div>
      </div>
      <button class="bo-btn" type="submit">Ajouter</button>
    </fieldset>
  </form>
</section>
