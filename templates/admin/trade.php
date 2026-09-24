<?php
/**
 * Édition d'une fiche métier.
 *
 * Le formulaire principal n'a que deux boutons, en bas : « Publier » est le
 * premier, donc celui que déclenche la touche Entrée. Restaurer et supprimer
 * vivent dans leurs propres formulaires, plus bas : aucune frappe au clavier
 * ne peut les déclencher par mégarde.
 *
 * @var array      $trade       fiche (éventuellement refusée, à corriger)
 * @var array      $families    catalogue des familles
 * @var array      $all         toutes les fiches (lignes d'index)
 * @var array      $units       unités de rémunération
 * @var int        $matches     annonces en ligne qui correspondent aux mots-clés
 * @var int        $profiles    profils qui correspondent aux mots-clés
 * @var bool       $hasSeed     un texte d'origine existe
 * @var bool       $modified    la fiche diffère de ce texte
 * @var array|null $lock
 * @var array|null $lockHolder
 * @var string     $notice
 * @var string[]   $errors
 */
use App\Core\Csrf;
use App\Services\I18n;

$lines = static fn(array $items): string => implode("\n", array_map('strval', $items));
$faq = array_values((array) $trade['faq']);
$slots = max(6, count($faq) + 1);
$related = array_flip((array) $trade['related']);
$byFamily = [];
foreach ($all as $row) {
    if ($row['id'] !== $trade['id']) {
        $byFamily[(string) $row['family']][] = $row;
    }
}
$defaultTitle = I18n::t('trade.seo_title', (string) $trade['name']);
?>
<div class="admin-head">
  <div>
    <h1><?= e((string) $trade['name']) ?></h1>
    <p>
      <span class="state <?= $trade['status'] === 'publish' ? 'state-ok' : 'state-wait' ?>">
        <?= e($trade['status'] === 'publish' ? I18n::t('admin.state_publish') : I18n::t('admin.state_draft')) ?>
      </span>
      &nbsp;<?= $matches ?> annonce(s) en ligne et <?= $profiles ?> profil(s) correspondent aux mots-clés.
    </p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-ghost-light btn-sm" href="/admin/metiers">Tous les métiers</a>
    <?php if ($trade['status'] === 'publish'): ?>
      <a class="btn btn-ghost-light btn-sm" href="<?= e(I18n::url('/metiers/' . $trade['slug'])) ?>" target="_blank" rel="noopener">Voir la fiche</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($lock === null && $lockHolder !== null): ?>
  <div class="notice notice-wait" role="status">
    <?= e(I18n::t('admin.lock_taken', (string) ($lockHolder['user_name'] ?? '—'), (int) ($lockHolder['age_minutes'] ?? 0))) ?>
  </div>
<?php endif; ?>
<?php if ($notice !== ''): ?><div class="notice notice-ok" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($errors !== []): ?>
  <div class="notice notice-err" role="alert" tabindex="-1" data-error-focus>
    <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post">
  <?= Csrf::field('admin-trade') ?>

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2>Identité</h2></div>
    <div class="grid-fields">
      <label class="field">
        <span class="label">Nom du métier</span>
        <input class="input" type="text" name="name" value="<?= e((string) $trade['name']) ?>" required maxlength="80">
        <span class="opt">Il ouvre le titre de la page et le h1 : c’est le mot-clé principal.</span>
      </label>
      <label class="field">
        <span class="label">Au féminin</span>
        <input class="input" type="text" name="name_f" value="<?= e((string) $trade['name_f']) ?>" maxlength="80">
      </label>
      <label class="field">
        <span class="label">Famille</span>
        <select class="input" name="family">
          <?php foreach ($families as $key => $family): ?>
            <option value="<?= e($key) ?>" <?= $key === $trade['family'] ? 'selected' : '' ?>><?= e($family['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">
        <span class="label">Code ROME <span class="opt">facultatif</span></span>
        <input class="input" type="text" name="rome" value="<?= e((string) $trade['rome']) ?>" maxlength="5"
               placeholder="L1508" spellcheck="false" autocomplete="off">
        <span class="opt">Répertoire des métiers de France Travail. Laisser vide en cas de doute.</span>
      </label>
      <label class="field">
        <span class="label">Adresse</span>
        <input class="input" type="text" name="slug" value="<?= e((string) $trade['slug']) ?>" spellcheck="false" autocomplete="off">
        <span class="opt">/metiers/<?= e((string) $trade['slug']) ?>
          <?php if ((array) $trade['former_slugs'] !== []): ?>
            · redirige depuis <?= e(implode(', ', (array) $trade['former_slugs'])) ?>
          <?php endif; ?>
        </span>
      </label>
    </div>
    <label class="field" style="margin-top:12px">
      <span class="label">Résumé du pavé</span>
      <input class="input" type="text" name="summary" value="<?= e((string) $trade['summary']) ?>" maxlength="140">
      <span class="opt">Une phrase, sous le nom du métier dans la mosaïque. 110 caractères environ.</span>
    </label>
  </div>

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2>Texte de la fiche</h2></div>
    <p class="secret-help" style="margin-top:0">
      Chaque bloc devient une section de la page, sous un titre qui reprend le nom du métier
      (« Que fait un régisseur son ? », « Comment devenir… »). Les listes s’écrivent une ligne par élément.
    </p>
    <label class="field"><span class="label">Introduction</span>
      <textarea class="input" name="intro" rows="4"><?= e((string) $trade['intro']) ?></textarea>
      <span class="opt">Premier paragraphe, et méta description par défaut : placez-y le nom du métier.</span>
    </label>
    <div class="grid-fields" style="margin-top:12px">
      <label class="field"><span class="label">Missions <span class="opt">une par ligne</span></span>
        <textarea class="input" name="missions" rows="7"><?= e($lines((array) $trade['missions'])) ?></textarea>
      </label>
      <label class="field"><span class="label">Compétences et qualités <span class="opt">une par ligne</span></span>
        <textarea class="input" name="skills" rows="7"><?= e($lines((array) $trade['skills'])) ?></textarea>
      </label>
    </div>
    <label class="field" style="margin-top:12px"><span class="label">Une journée type</span>
      <textarea class="input" name="day" rows="5"><?= e((string) $trade['day']) ?></textarea>
    </label>
    <div class="grid-fields" style="margin-top:12px">
      <label class="field"><span class="label">Formation et accès au métier</span>
        <textarea class="input" name="training" rows="6"><?= e((string) $trade['training']) ?></textarea>
      </label>
      <label class="field"><span class="label">Formations et écoles <span class="opt">une par ligne</span></span>
        <textarea class="input" name="schools" rows="6"><?= e($lines((array) $trade['schools'])) ?></textarea>
      </label>
    </div>
    <div class="grid-fields" style="margin-top:12px">
      <label class="field"><span class="label">Statut et contrat</span>
        <textarea class="input" name="statut" rows="5"><?= e((string) $trade['statut']) ?></textarea>
      </label>
      <label class="field"><span class="label">Évolution de carrière</span>
        <textarea class="input" name="career" rows="5"><?= e((string) $trade['career']) ?></textarea>
      </label>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2>Rémunération</h2></div>
    <p class="secret-help" style="margin-top:0">
      Montants bruts, indicatifs. La fiche rappelle que les minima de la convention collective font foi.
      Laisser les deux montants à 0 pour ne pas afficher de chiffre.
    </p>
    <div class="grid-fields">
      <label class="field"><span class="label">Minimum (€)</span>
        <input class="input" type="number" name="pay_min" min="0" step="1" value="<?= (int) ($trade['pay']['min'] ?? 0) ?>">
      </label>
      <label class="field"><span class="label">Maximum (€)</span>
        <input class="input" type="number" name="pay_max" min="0" step="1" value="<?= (int) ($trade['pay']['max'] ?? 0) ?>">
      </label>
      <label class="field"><span class="label">Unité</span>
        <select class="input" name="pay_unit">
          <?php foreach ($units as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $key === ($trade['pay']['unit'] ?? '') ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <label class="field" style="margin-top:12px"><span class="label">Précisions</span>
      <textarea class="input" name="pay_note" rows="3"><?= e((string) ($trade['pay']['note'] ?? '')) ?></textarea>
    </label>
  </div>

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2>Encadré « En bref »</h2></div>
    <div class="grid-fields">
      <label class="field"><span class="label">Statut</span>
        <input class="input" type="text" name="brief_status" value="<?= e((string) ($trade['brief']['status'] ?? '')) ?>" maxlength="90">
      </label>
      <label class="field"><span class="label">Formation</span>
        <input class="input" type="text" name="brief_training" value="<?= e((string) ($trade['brief']['training'] ?? '')) ?>" maxlength="90">
      </label>
      <label class="field"><span class="label">Secteurs</span>
        <input class="input" type="text" name="brief_sectors" value="<?= e((string) ($trade['brief']['sectors'] ?? '')) ?>" maxlength="90">
      </label>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2>Questions fréquentes</h2></div>
    <p class="secret-help" style="margin-top:0">
      Formulées comme on les tape dans un moteur de recherche. Une question sans réponse est refusée ;
      les lignes vides sont ignorées.
    </p>
    <?php for ($i = 0; $i < $slots; $i++): ?>
      <div class="grid-fields" style="margin-top:<?= $i === 0 ? 0 : 12 ?>px">
        <label class="field"><span class="label">Question <?= $i + 1 ?></span>
          <input class="input" type="text" name="faq_q[<?= $i ?>]" value="<?= e((string) ($faq[$i]['q'] ?? '')) ?>" maxlength="160">
        </label>
        <label class="field"><span class="label">Réponse</span>
          <textarea class="input" name="faq_a[<?= $i ?>]" rows="2"><?= e((string) ($faq[$i]['a'] ?? '')) ?></textarea>
        </label>
      </div>
    <?php endfor; ?>
  </div>

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2>Rapprochement avec les annonces</h2></div>
    <p class="secret-help" style="margin-top:0">
      Une annonce, une offre partenaire ou un profil apparaît sur la fiche si son intitulé contient l’un
      de ces mots-clés. Écrivez les féminins et les variantes : « régisseuse son », « régie son ».
      Évitez les mots seuls trop larges comme « technicien ».
    </p>
    <div class="grid-fields">
      <label class="field"><span class="label">Mots-clés <span class="opt">un par ligne</span></span>
        <textarea class="input" name="keywords" rows="6"><?= e($lines((array) $trade['keywords'])) ?></textarea>
      </label>
      <label class="field"><span class="label">Recherche chez les partenaires</span>
        <input class="input" type="text" name="search" value="<?= e((string) $trade['search']) ?>" maxlength="80"
               placeholder="<?= e((string) (((array) $trade['keywords'])[0] ?? $trade['name'])) ?>">
        <span class="opt">Ce qu’on tape chez Jooble, Adzuna ou France Travail pour ce métier. Vide : le premier mot-clé.</span>
      </label>
    </div>

    <h3 style="margin:20px 0 8px;font-size:15px">Métiers proches</h3>
    <p class="secret-help" style="margin-top:0">Cochés, ils s’affichent en bas de la fiche. À défaut, la fiche propose ceux de sa famille.</p>
    <div class="trade-related-pick">
      <?php foreach ($families as $key => $family): ?>
        <?php if (empty($byFamily[$key])) { continue; } ?>
        <fieldset>
          <legend><?= e($family['name']) ?></legend>
          <?php foreach ($byFamily[$key] as $row): ?>
            <label class="check">
              <input type="checkbox" name="related[]" value="<?= e($row['slug']) ?>" <?= isset($related[$row['slug']]) ? 'checked' : '' ?>>
              <span><?= e($row['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2>Référencement</h2></div>
    <p class="secret-help" style="margin-top:0">
      Laissés vides, le titre et la description sont calculés — ou tirés du gabarit de la rubrique
      « Fiche métier » dans Référencement. Remplis ici, ils l’emportent pour cette fiche seulement.
    </p>
    <div class="grid-fields">
      <label class="field"><span class="label">Titre de référencement</span>
        <input class="input" type="text" name="seo_title" value="<?= e((string) ($trade['seo']['title'] ?? '')) ?>"
               maxlength="120" placeholder="<?= e($defaultTitle) ?>">
      </label>
      <label class="field"><span class="label">Méta description</span>
        <textarea class="input" name="seo_description" rows="3" maxlength="320"
                  placeholder="<?= e(str_excerpt((string) $trade['intro'], 155)) ?>"><?= e((string) ($trade['seo']['description'] ?? '')) ?></textarea>
      </label>
    </div>
  </div>

  <div class="save-bar">
    <span class="save-bar-note">
      Publier rend la fiche visible sur le site. Un brouillon reste consultable ici seulement.
    </span>
    <button type="submit" name="action" value="publish" class="btn btn-coral">Publier</button>
    <button type="submit" name="action" value="draft" class="btn btn-ghost-light">Enregistrer en brouillon</button>
  </div>
</form>

<div class="admin-card" style="margin-top:34px">
  <div class="admin-head" style="margin-bottom:12px"><h2>Texte d’origine et suppression</h2></div>
  <?php if ($hasSeed): ?>
    <form method="post" style="margin-bottom:18px">
      <?= Csrf::field('admin-trade') ?>
      <input type="hidden" name="action" value="restore">
      <p class="secret-help" style="margin-top:0">
        <?= $modified
            ? 'Cette fiche a été modifiée. La restauration remet le texte d’origine, sans toucher à son adresse ni à son état de publication.'
            : 'Cette fiche porte encore son texte d’origine.' ?>
      </p>
      <button type="submit" class="btn btn-ghost btn-sm" <?= $modified ? '' : 'disabled' ?>>Restaurer le texte d’origine</button>
    </form>
  <?php endif; ?>

  <form method="post">
    <?= Csrf::field('admin-trade') ?>
    <input type="hidden" name="action" value="delete">
    <p class="secret-help" style="margin-top:0">
      La suppression retire la fiche du site ; un instantané de sauvegarde est pris avant. Pour la masquer
      sans la perdre, enregistrez-la plutôt en brouillon.
    </p>
    <label class="check" style="margin-bottom:10px">
      <input type="checkbox" name="confirm_delete" value="oui">
      <span>Je confirme la suppression de cette fiche</span>
    </label>
    <button type="submit" class="btn btn-ghost btn-sm">Supprimer la fiche</button>
  </form>
</div>
