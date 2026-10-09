<?php
/**
 * Menus du site : menu principal, Matchs › Explorer, Interactif, pied de page.
 * Variables : $menus (Menus::get()), $saved (déjà enregistrés une fois)
 */
use App\Admin\Form;
use App\Front\Menus;

$tr = fn (string $fields) => '<button type="button" class="btn btn--sm btn--ghost" data-tr="' . e($fields) . '">Traduire en anglais</button>';
$hrefHelp = 'Adresse du site (/palmares/), adresse complète (https://…) ou {association} pour le site de l’association. Un lien vers la boutique ou l’appli disparaît tout seul quand elles sont fermées.';
$link = fn (array $x, bool $desc = false) => '<div class="fgrid">'
    . ($desc ? Form::text('@icon', 'Icône', $x['icon'] ?? '', ['maxlength' => 4, 'placeholder' => '★']) : '')
    . Form::text('@label', 'Libellé', $x['label'] ?? '', ['maxlength' => 120, 'class' => 'f--2', 'proof' => 'title'])
    . Form::text('@label_en', 'Libellé (EN)', $x['label_en'] ?? '', ['maxlength' => 120, 'class' => 'f--2', 'proof' => 'title'])
    . Form::text('@href', 'Lien', $x['href'] ?? '', ['maxlength' => 300, 'class' => 'f--2', 'placeholder' => '/palmares/'])
    . ($desc ? Form::text('@d', 'Phrase', $x['d'] ?? '', ['maxlength' => 160, 'class' => 'f--3']) . Form::text('@d_en', 'Phrase (EN)', $x['d_en'] ?? '', ['maxlength' => 160, 'class' => 'f--3']) : '')
    . Form::toggle('@hidden', 'Masqué', !empty($x['hidden']))
    . '<div class="f" style="justify-content:flex-end">' . $tr($desc ? 'label,d' : 'label') . '</div></div>';
?>
<form class="stack" data-json-form data-tabs-scope data-url="/admin/menus" data-lock="ecran:menus" data-lock-what="les menus" novalidate>
  <div class="row" style="justify-content:space-between">
    <div class="ftabs" data-ftabs role="tablist">
      <?php foreach (['boutons' => 'Boutons du haut', 'nav' => 'Menu principal', 'explore' => 'Matchs › Explorer', 'interactif' => 'Interactif', 'footer' => 'Pied de page'] as $k => $l): ?>
        <button type="button" data-tab="<?= $k ?>" role="tab"><?= e($l) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="row">
      <a class="btn" href="/" target="_blank" rel="noopener">Voir le site ↗</a>
      <button type="submit" class="btn btn--yellow">Enregistrer</button>
    </div>
  </div>

  <div class="fpanel" data-panel="boutons">
    <div class="card card--pad">
      <h2 class="card__t">Boutons du haut</h2>
      <p class="muted">Les trois boutons en haut à droite de chaque page (et dans le menu sur téléphone) : un lien discret, le bouton bleu (avec le panier, affiché seulement si la boutique est ouverte) et le bouton jaune ♥. Leur place et leur style restent fixes ; changez le libellé, le lien, ou masquez-les. <?= e($hrefHelp) ?></p>
      <div class="rep rep--compact" data-repeater="buttons">
        <?php foreach ($menus['buttons'] as $b): ?>
          <div class="rep__item" data-item><div class="rep__body">
            <p class="small" style="margin:0 0 6px"><b><?= e(['contribuer' => 'Lien discret (à côté de la recherche)', 'boutique' => 'Bouton bleu (boutique)', 'don' => 'Bouton jaune ♥ (don)'][$b['key']] ?? '') ?></b></p>
            <?= Form::hidden('@key', $b['key']) . $link($b) ?>
          </div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="fpanel" data-panel="nav">
    <div class="card card--pad">
      <h2 class="card__t">Menu principal</h2>
      <p class="muted">Les entrées du haut du site, dans l’ordre (glisser-déposer). Les entrées marquées « grand menu » ouvrent leur menu déroulant : son contenu suit les rubriques (Éditorial › Rubriques &amp; menus) ou les onglets suivants. Une entrée ajoutée est un simple lien. Les boutons Contribuer, Boutique et Faire un don se règlent dans l’onglet « Boutons du haut ».</p>
      <?= Form::repeater('nav', '', $menus['nav'], fn ($x) => (isset(Menus::MEGA[$x['key'] ?? '']) ? '<p class="small" style="margin:0 0 6px"><span class="pill">grand menu</span> ' . e(Menus::MEGA[$x['key']]) . '</p>' : '')
          . Form::hidden('@key', $x['key'] ?? '') . $link($x), ['compact' => true, 'add' => 'Ajouter un lien au menu']) ?>
    </div>
  </div>

  <div class="fpanel" data-panel="explore">
    <div class="card card--pad">
      <h2 class="card__t">Menu Matchs › colonne « Explorer »</h2>
      <p class="muted"><?= e($hrefHelp) ?></p>
      <?= Form::repeater('matchs_explore', '', $menus['matchs_explore'], fn ($x) => $link($x), ['compact' => true, 'add' => 'Ajouter un lien']) ?>
    </div>
  </div>

  <div class="fpanel" data-panel="interactif">
    <div class="card card--pad">
      <h2 class="card__t">Menu Interactif</h2>
      <p class="muted">Les groupes d’outils du grand menu Interactif (et de la page Interactif), chacun avec son icône, son libellé, sa phrase et son lien. Le groupe « murs de photos » se remplit tout seul.</p>
      <?= Form::repeater('interactif', '', $menus['interactif'], fn ($g) => '<div class="fgrid">'
          . Form::text('@title', 'Titre du groupe', $g['title'] ?? '', ['maxlength' => 120, 'class' => 'f--2', 'proof' => 'title'])
          . Form::text('@title_en', 'Titre (EN)', $g['title_en'] ?? '', ['maxlength' => 120, 'class' => 'f--2'])
          . Form::hidden('@auto', $g['auto'] ?? '') . '</div>'
          . (($g['auto'] ?? '') === 'walls' ? '<p class="small muted" style="margin:6px 0 0">Outils ajoutés automatiquement : les murs de photos ouverts.</p>'
              : Form::repeater('tools', '', (array) ($g['tools'] ?? []), fn ($x) => $link($x, true), ['compact' => true, 'add' => 'Ajouter un outil'])),
          ['add' => 'Ajouter un groupe']) ?>
    </div>
  </div>

  <div class="fpanel" data-panel="footer">
    <div class="card card--pad">
      <h2 class="card__t">Pied de page : colonnes de liens</h2>
      <p class="muted">Jusqu’à 4 colonnes entre le logo et la fiche boutique. <?= e($hrefHelp) ?> Le reste du pied de page (phrase, bandeau, copyright) se règle dans Réglages › Pied de page.</p>
      <?= Form::repeater('footer', '', $menus['footer'], fn ($c) => '<div class="fgrid">'
          . Form::text('@title', 'Titre de la colonne', $c['title'] ?? '', ['maxlength' => 120, 'class' => 'f--2', 'proof' => 'title'])
          . Form::text('@title_en', 'Titre (EN)', $c['title_en'] ?? '', ['maxlength' => 120, 'class' => 'f--2']) . '</div>'
          . Form::repeater('links', '', (array) ($c['links'] ?? []), fn ($x) => $link($x), ['compact' => true, 'add' => 'Ajouter un lien']),
          ['add' => 'Ajouter une colonne']) ?>
    </div>
  </div>
</form>

<?php if ($saved): ?>
<form class="row" data-json-form data-url="/admin/menus" data-confirm="Revenir aux menus de départ ?|Tous les changements faits ici seront effacés.|Revenir aux menus de départ|danger" style="margin-top:18px" novalidate>
  <input type="hidden" name="reset" data-type="json" value="true">
  <button type="submit" class="btn btn--ghost">Revenir aux menus de départ</button>
</form>
<?php endif; ?>
