<?php
/**
 * Consentement : les scripts publicitaires ne partent qu'après un « oui »
 * explicite.
 *
 * Deux vues dans un seul bloc. « Choix » pose la question et offre les deux
 * réponses d'un même poids — accepter et refuser coûtent un clic chacune, ce
 * que la CNIL exige. « Réglages » détaille ce que recouvre l'accord et permet
 * de n'en accorder qu'une partie.
 *
 * Le bandeau est toujours présent dans le HTML, masqué par défaut : la page
 * anonyme est mise en cache, elle ne peut donc pas dépendre du visiteur. C'est
 * le script qui décide de l'afficher, d'après le choix déjà mémorisé.
 */
use App\Services\Ads;
use App\Services\I18n;

if (Ads::client() === '') {
    return;   // aucun compte AdSense configuré : rien à demander
}
?>
<?php // Bandeau, pas fenêtre modale : « dialog » forcerait un lecteur d'écran
      // à traiter le reste de la page comme inaccessible. ?>
<aside class="cmp" data-cmp role="region" aria-live="polite"
       aria-label="<?= e(I18n::t('cmp.title')) ?>" hidden>

  <div data-cmp-view="choice">
    <h2 class="cmp-title"><?= e(I18n::t('cmp.title')) ?></h2>
    <p><?= e(I18n::t('cmp.body')) ?>
      <a href="<?= e(I18n::url('/confidentialite')) ?>"><?= e(I18n::t('footer.privacy')) ?></a>
    </p>
    <div class="cmp-actions">
      <button type="button" class="btn btn-sm btn-coral" data-cmp-choice="all">
        <?= e(I18n::t('cmp.accept')) ?>
      </button>
      <button type="button" class="btn btn-sm btn-ink" data-cmp-choice="none">
        <?= e(I18n::t('cmp.refuse')) ?>
      </button>
    </div>
    <button type="button" class="linklike cmp-more" data-cmp-view-to="settings">
      <?= e(I18n::t('cmp.settings')) ?>
    </button>
  </div>

  <div data-cmp-view="settings" hidden>
    <h2 class="cmp-title" tabindex="-1" data-cmp-settings-title>
      <?= e(I18n::t('cmp.settings_title')) ?>
    </h2>

    <div class="cmp-cats">
      <?php // Les cookies nécessaires ne se refusent pas : ils ne servent
            // qu'à faire fonctionner le site, et en sont exemptés. La case est
            // là pour le dire, pas pour être décochée. ?>
      <label class="cmp-cat">
        <input type="checkbox" checked disabled>
        <span>
          <strong><?= e(I18n::t('cmp.cat_needed')) ?></strong>
          <em><?= e(I18n::t('cmp.cat_needed_note')) ?></em>
        </span>
      </label>

      <label class="cmp-cat">
        <input type="checkbox" data-cmp-cat="ads">
        <span>
          <strong><?= e(I18n::t('cmp.cat_ads')) ?></strong>
          <em><?= e(I18n::t('cmp.cat_ads_note')) ?></em>
        </span>
      </label>

      <label class="cmp-cat">
        <input type="checkbox" data-cmp-cat="perso">
        <span>
          <strong><?= e(I18n::t('cmp.cat_perso')) ?></strong>
          <em><?= e(I18n::t('cmp.cat_perso_note')) ?></em>
        </span>
      </label>
    </div>

    <div class="cmp-actions">
      <button type="button" class="btn btn-sm btn-coral" data-cmp-choice="all">
        <?= e(I18n::t('cmp.accept')) ?>
      </button>
      <button type="button" class="btn btn-sm btn-ink" data-cmp-choice="none">
        <?= e(I18n::t('cmp.refuse')) ?>
      </button>
    </div>
    <button type="button" class="linklike cmp-more" data-cmp-save>
      <?= e(I18n::t('cmp.save')) ?>
    </button>
  </div>
</aside>
