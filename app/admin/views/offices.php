<?php
/** Bureaux & dispos : le tableau qui pilote la page « Nos bureaux » du site. */

use App\Admin;
use App\Config;
use App\Csrf;
use App\I18n;
use App\Offices;
use App\Router;
use App\Text;
use App\View;

echo View::admin('_layout_start', get_defined_vars());

$editingOffice = null;
foreach ($offices as $office) {
    if ((string) ($office['id'] ?? '') === $editing && $editing !== '') {
        $editingOffice = $office;
    }
}
$showForm = $creating || $editingOffice !== null;
$statusColors = ['available' => '#3DDC97', 'soon' => '#FFCC00', 'rented' => '#DCDFE4'];
$statusLabels = ['available' => 'Disponible', 'soon' => 'Bientôt libre', 'rented' => 'Loué'];
$typeLabels = ['private' => 'Bureau privé', 'openspace' => 'Openspace', 'meeting' => 'Salle de réunion'];
?>
<div class="screen">
  <?php if ($showForm):
      $o = $editingOffice ?? [];
      $en = (array) ($o['i18n']['en'] ?? []); ?>
    <section class="panel" style="margin-top:24px">
      <div class="panel__head">
        <h2><?= $editingOffice === null ? 'Nouveau bureau' : 'Modifier ' . Text::e((string) $o['name']) ?></h2>
        <a class="link-underline" href="<?= Text::e(Router::adminUrl('offices')) ?>">Annuler</a>
      </div>
      <form class="panel__body" method="post" action="<?= Text::e(Router::adminUrl()) ?>">
        <?= Csrf::field('admin') ?>
        <input type="hidden" name="action" value="office-save">
        <input type="hidden" name="id" value="<?= Text::e((string) ($o['id'] ?? '')) ?>">

        <div class="grid-2">
          <div>
            <label class="label" for="o-name">NOM DU BUREAU</label>
            <input class="field" id="o-name" type="text" name="o[name]" required value="<?= Text::e((string) ($o['name'] ?? '')) ?>" placeholder="Bureau privé — n° 08">
          </div>
          <?php if (Offices::multiSite()): ?>
          <div>
            <label class="label" for="o-site">ESPACE</label>
            <select class="field" id="o-site" name="o[site]">
              <?php foreach ((array) ($settings['sites'] ?? Config::SITES) as $site):
                  $id = \is_array($site) ? (string) $site['id'] : (string) $site;
                  $label = \is_array($site) ? (string) ($site['shortName'] ?? $id) : ucfirst($id); ?>
                <option value="<?= Text::e($id) ?>" <?= ($o['site'] ?? '') === $id ? 'selected' : '' ?>><?= Text::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php else: ?>
            <input type="hidden" name="o[site]" value="<?= Text::e(Config::SITES[0]) ?>">
          <?php endif; ?>
          <div>
            <label class="label" for="o-type">TYPE</label>
            <select class="field" id="o-type" name="o[type]">
              <?php foreach ($typeLabels as $value => $label): ?>
                <option value="<?= Text::e($value) ?>" <?= ($o['type'] ?? '') === $value ? 'selected' : '' ?>><?= Text::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="o-area">SURFACE / FORMULE</label>
            <input class="field" id="o-area" type="text" name="o[area]" value="<?= Text::e((string) ($o['area'] ?? '')) ?>" placeholder="13 m²">
          </div>
          <div>
            <label class="label" for="o-capacity">CAPACITÉ</label>
            <input class="field" id="o-capacity" type="text" name="o[capacity]" value="<?= Text::e((string) ($o['capacity'] ?? '')) ?>" placeholder="2 personnes">
          </div>
          <div>
            <label class="label" for="o-price">TARIF NORMAL HT / MOIS (€)</label>
            <input class="field" id="o-price" type="number" min="0" step="5" name="o[price]" value="<?= (int) ($o['price'] ?? 0) ?>">
          </div>
          <div>
            <label class="label" for="o-promo">TARIF PROMO HT / MOIS (€)</label>
            <input class="field" id="o-promo" type="number" min="0" step="5" name="o[pricePromo]"
                   value="<?= (int) ($o['pricePromo'] ?? 0) > 0 ? (int) $o['pricePromo'] : '' ?>" placeholder="vide = pas de promotion">
            <div class="hint">Renseigné, c'est lui qui s'affiche, et le tarif normal apparaît barré à côté. Laissez vide pour revenir au tarif normal.</div>
          </div>
          <div>
            <label class="label" for="o-status">DISPONIBILITÉ</label>
            <select class="field" id="o-status" name="o[status]">
              <?php foreach ($statusLabels as $value => $label): ?>
                <option value="<?= Text::e($value) ?>" <?= ($o['status'] ?? '') === $value ? 'selected' : '' ?>><?= Text::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="o-from">LIBRE À PARTIR DU</label>
            <input class="field" id="o-from" type="date" name="o[availableFrom]" value="<?= Text::e((string) ($o['availableFrom'] ?? '')) ?>">
          </div>
          <div>
            <label class="label" for="o-order">ORDRE D'AFFICHAGE</label>
            <input class="field" id="o-order" type="number" min="1" name="o[order]" value="<?= (int) ($o['order'] ?? \count($offices) + 1) ?>">
          </div>
          <div>
            <label class="label" for="o-color">COULEUR DE VIGNETTE</label>
            <div style="display:flex;gap:10px;align-items:center">
              <input class="field" id="o-color" type="text" name="o[color]" value="<?= Text::e((string) ($o['color'] ?? '#FFCC00')) ?>" data-color-text>
              <input type="color" value="<?= Text::e(preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($o['color'] ?? '')) === 1 ? (string) $o['color'] : '#FFCC00') ?>" data-color-picker aria-label="Couleur" style="width:52px;height:52px;border:2px solid #101820;border-radius:12px;background:none;cursor:pointer;padding:2px">
            </div>
          </div>
        </div>

        <div style="display:flex;gap:22px;flex-wrap:wrap;margin-top:18px">
          <label class="check"><input type="checkbox" name="o[enabled]" value="1" <?= ($o['enabled'] ?? true) ? 'checked' : '' ?>><span>Visible sur le site</span></label>
          <label class="check"><input type="checkbox" name="o[featured]" value="1" <?= !empty($o['featured']) ? 'checked' : '' ?>><span>Mis en avant</span></label>
        </div>

        <label class="label" style="margin-top:18px" for="o-badge">BANDEAU SUR LA PHOTO</label>
        <input class="field" id="o-badge" type="text" name="o[badge]" maxlength="160" value="<?= Text::e((string) ($o['badge'] ?? '')) ?>" placeholder="Dernier bureau privé disponible — à réserver en urgence !">
        <div class="hint">Affiché sur la vignette et en haut de la fiche tant que le bureau n’est pas réservé. Vide : pas de bandeau.</div>

        <label class="label" style="margin-top:18px" for="o-desc">DESCRIPTION</label>
        <textarea class="field" id="o-desc" name="o[description]" rows="4"><?= Text::e((string) ($o['description'] ?? '')) ?></textarea>

        <label class="label" style="margin-top:18px" for="o-features">POINTS FORTS (UN PAR LIGNE)</label>
        <textarea class="field" id="o-features" name="o[features]" rows="4"><?= Text::e(implode("\n", array_map('strval', (array) ($o['features'] ?? [])))) ?></textarea>

        <?php
        $photos = array_values(array_filter(array_map('strval', (array) ($o['photos'] ?? []))));
        echo View::admin('_field', [
            'field' => ['label' => 'Photos du bureau', 'type' => 'media', 'path' => 'photos'],
            'value' => $photos,
            'name' => 'o[photos]',
            'media' => $media,
        ]); ?>

        <h3 style="margin:26px 0 0;font:700 17px/1 'Jost',sans-serif;padding-top:18px;border-top:2px solid rgba(16,24,32,.12)">Version anglaise</h3>
        <div class="grid-2" style="margin-top:12px">
          <div>
            <label class="label" for="o-enname">NOM (EN)</label>
            <input class="field" id="o-enname" type="text" name="o[en_name]" value="<?= Text::e((string) ($en['name'] ?? '')) ?>">
          </div>
          <div>
            <label class="label" for="o-enarea">SURFACE (EN)</label>
            <input class="field" id="o-enarea" type="text" name="o[en_area]" value="<?= Text::e((string) ($en['area'] ?? '')) ?>">
          </div>
          <div>
            <label class="label" for="o-encap">CAPACITÉ (EN)</label>
            <input class="field" id="o-encap" type="text" name="o[en_capacity]" value="<?= Text::e((string) ($en['capacity'] ?? '')) ?>">
          </div>
          <div>
            <label class="label" for="o-enbadge">BANDEAU (EN)</label>
            <input class="field" id="o-enbadge" type="text" name="o[en_badge]" value="<?= Text::e((string) ($en['badge'] ?? '')) ?>">
          </div>
        </div>
        <label class="label" style="margin-top:18px" for="o-endesc">DESCRIPTION (EN)</label>
        <textarea class="field" id="o-endesc" name="o[en_description]" rows="3"><?= Text::e((string) ($en['description'] ?? '')) ?></textarea>
        <label class="label" style="margin-top:18px" for="o-enfeat">POINTS FORTS (EN, UN PAR LIGNE)</label>
        <textarea class="field" id="o-enfeat" name="o[en_features]" rows="3"><?= Text::e(implode("\n", array_map('strval', (array) ($en['features'] ?? [])))) ?></textarea>
        <div class="hint">Laissé vide, le site affiche le texte français : aucune page ne reste blanche.</div>

        <div class="form-actions">
          <button class="btn btn--ink" type="submit"><?= $editingOffice === null ? 'Ajouter au catalogue' : 'Enregistrer' ?></button>
          <a class="btn btn--outline" href="<?= Text::e(Router::adminUrl('offices')) ?>">Annuler</a>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <section class="panel" style="margin-top:<?= $showForm ? '18' : '24' ?>px">
    <div class="panel__scroll">
      <div class="row row--head row--offices">
        <span>BUREAU</span><span>ESPACE</span><span>SURFACE</span><span>TARIF HT/MOIS</span><span>DISPONIBILITÉ</span><span style="text-align:right">ACTIONS</span>
      </div>

      <?php foreach ($offices as $office):
          $id = (string) ($office['id'] ?? '');
          $status = (string) ($office['status'] ?? 'available');
          $enabled = ($office['enabled'] ?? true) === true;
          $cover = (string) (($office['photos'] ?? [])[0] ?? ''); ?>
        <div class="row row--offices" style="<?= $enabled ? '' : 'opacity:.5' ?>">
          <div style="display:flex;align-items:center;gap:11px;min-width:0">
            <?php if ($cover !== ''): ?>
              <img class="swatch" style="width:34px;height:34px;object-fit:cover" src="<?= Text::e(Config::basePath() . $cover) ?>" alt="" loading="lazy">
            <?php else: ?>
              <span class="swatch" style="background:<?= Text::e((string) ($office['color'] ?? '#FFCC00')) ?>"></span>
            <?php endif; ?>
            <span style="min-width:0">
              <span class="row__title"><?= Text::e((string) ($office['name'] ?? '')) ?></span>
              <span class="row__sub"><?= Text::e($typeLabels[(string) ($office['type'] ?? 'private')] ?? '') ?><?= $enabled ? '' : ' · masqué' ?></span>
            </span>
          </div>
          <span class="row__value"><?= Text::e(Offices::siteLabel((string) ($office['site'] ?? ''))) ?></span>
          <span class="row__value"><?= Text::e((string) ($office['area'] ?? '')) ?></span>
          <?php $full = (int) ($office['price'] ?? 0);
                $promo = (int) ($office['pricePromo'] ?? 0);
                $onSale = $promo > 0 && $full > 0 && $promo < $full; ?>
          <span class="price">
            <?php if ($onSale): ?>
              <s class="price__old"><?= $full ?> €</s>
              <strong><?= $promo ?></strong><span>€</span>
            <?php else: ?>
              <strong><?= $full ?></strong><span>€</span>
            <?php endif; ?>
          </span>

          <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
            <?= Csrf::field('admin') ?>
            <input type="hidden" name="action" value="office-status">
            <input type="hidden" name="id" value="<?= Text::e($id) ?>">
            <button class="status-btn" type="submit" style="background:<?= Text::e($statusColors[$status] ?? '#DCDFE4') ?>" title="Cliquer pour faire tourner le statut"><?= Text::e($statusLabels[$status] ?? $status) ?></button>
          </form>

          <div class="row__actions">
            <a class="btn btn--sm btn--outline" href="<?= Text::e(Router::adminUrl('offices', ['edit' => $id])) ?>">Modifier</a>
            <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
              <?= Csrf::field('admin') ?>
              <input type="hidden" name="action" value="office-toggle">
              <input type="hidden" name="id" value="<?= Text::e($id) ?>">
              <button class="btn btn--sm btn--outline" type="submit"><?= $enabled ? 'Masquer' : 'Afficher' ?></button>
            </form>
            <form method="post" action="<?= Text::e(Router::adminUrl()) ?>" data-confirm="Supprimer définitivement ce bureau du catalogue ?">
              <?= Csrf::field('admin') ?>
              <input type="hidden" name="action" value="office-delete">
              <input type="hidden" name="id" value="<?= Text::e($id) ?>">
              <button class="btn btn--sm btn--danger" type="submit">Supprimer</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>

      <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:18px 22px">
        <div class="muted" style="max-width:560px">
          Cliquez sur une disponibilité pour la faire tourner (Disponible → Bientôt libre → Loué). L'écriture se fait dans
          <strong>content/offices.json</strong> ; le front lit le même fichier, donc la page « Nos bureaux », les cartes de l'accueil
          et le compteur « places restantes » suivent immédiatement.
        </div>
        <a class="btn btn--ink" href="<?= Text::e(Router::adminUrl('offices', ['new' => 1])) ?>">+ Ajouter un bureau</a>
      </div>
    </div>
  </section>
</div>
<?= View::admin('_layout_end') ?>
