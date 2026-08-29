<?php
/**
 * Édition d'une liste simple : agenda, documents, associations, numéros,
 * services de l'État, commissions.
 *
 * Une entrée est un dépliant, comme un bloc de page : la liste entière tient
 * ainsi sur un écran, et l'on ouvre celle qu'on veut modifier.
 *
 * @var string $liste
 * @var array  $reglages
 * @var array  $donnees
 * @var string[] $medias
 * @var string[] $documents
 * @var App\Core\View $view
 */
use App\Admin\ContenuController;
use App\Admin\Blocs;
use App\Core\Csrf;

$cibles = ['items' => $reglages['nom']] + (array) ($reglages['sous'] ?? []);

/** Deux mots qui disent ce que contient une entrée repliée. */
$resume = static function (array $entree): string {
    foreach (['nom', 'titre', 'libelle'] as $champ) {
        $valeur = trim((string) ($entree[$champ] ?? ''));
        if ($valeur !== '') {
            return mb_strimwidth($valeur, 0, 70, '…');
        }
    }
    return 'entrée sans titre';
};
?>
<p class="bo-intro"><?= e($reglages['aide']) ?></p>

<form class="bo-form" method="post" action="<?= url('/admin/listes/' . $liste) ?>" data-form-page>
  <?= Csrf::champ() ?>

  <?php foreach ($cibles as $cible => $intitule): ?>
    <?php $entrees = (array) ($donnees[$cible] ?? []); ?>
    <section class="bo-zone">
      <div class="bo-zone__tete">
        <h2><?= e($intitule) ?></h2>
        <span class="bo-zone__compte"><?= count($entrees) ?></span>
      </div>

      <div class="bo-blocs">
        <?php foreach ($entrees as $rang => $entree): ?>
          <?php $enLigne = (bool) ($entree['actif'] ?? true); ?>
          <details class="bo-bloc-edit<?= $enLigne ? '' : ' bo-bloc-edit--hors-ligne' ?>" id="entree-<?= e($cible) ?>-<?= $rang ?>">
            <summary class="bo-bloc-edit__tete">
              <span class="bo-etiquette<?= $enLigne ? '' : ' bo-etiquette--alerte' ?>"><?= $enLigne ? 'En ligne' : 'Hors ligne' ?></span>
              <span class="bo-bloc-edit__resume"><?= e($resume($entree)) ?></span>
              <span class="bo-bloc-edit__rang">#<?= $rang + 1 ?></span>
            </summary>

            <div class="bo-bloc-edit__corps">
              <input type="hidden" name="<?= e($cible) ?>[<?= $rang ?>][slug]" value="<?= e((string) ($entree['slug'] ?? '')) ?>">

              <div class="bo-champ bo-champ--case">
                <input id="<?= e($cible) ?>-<?= $rang ?>-actif" type="checkbox"
                       name="<?= e($cible) ?>[<?= $rang ?>][actif]" value="1"<?= $enLigne ? ' checked' : '' ?>>
                <label for="<?= e($cible) ?>-<?= $rang ?>-actif">Publiée sur le site</label>
              </div>

              <?php foreach ($reglages['champs'] as $champ => $nature): ?>
                <?php if (str_starts_with($nature, 'items:')): ?>
                  <?php
                  $sous = ContenuController::SOUS_LISTES[substr($nature, 6)] ?? [];
                  $sousEntrees = (array) ($entree[$champ] ?? []);
                  ?>
                  <fieldset class="bo-sous-champ">
                    <legend><?= e(Blocs::libelle($champ)) ?></legend>
                    <?php for ($j = 0; $j < count($sousEntrees) + 2; $j++): ?>
                      <?php $sousEntree = $sousEntrees[$j] ?? []; ?>
                      <div class="bo-entree">
                        <span class="bo-entree__rang"><?= $j + 1 ?></span>
                        <div class="bo-entree__champs">
                          <?php foreach ($sous as $sousChamp => $sousNature): ?>
                            <?= $view->partial('admin/champ', [
                                'nature'  => $sousNature,
                                'nom'     => "{$cible}[{$rang}][{$champ}][{$j}][{$sousChamp}]",
                                'valeur'  => $sousEntree[$sousChamp] ?? '',
                                'libelle' => Blocs::libelle($sousChamp),
                                'id'      => "{$cible}-{$rang}-{$champ}-{$j}-{$sousChamp}",
                                'medias'  => $medias, 'documents' => $documents,
                            ]) ?>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endfor; ?>
                    <p class="bo-aide">Une entrée entièrement vide n’est pas enregistrée : c’est ainsi qu’on en supprime une.</p>
                  </fieldset>
                <?php else: ?>
                  <?= $view->partial('admin/champ', [
                      'nature'  => $nature,
                      'nom'     => "{$cible}[{$rang}][{$champ}]",
                      'valeur'  => $entree[$champ] ?? '',
                      'libelle' => Blocs::libelle($champ),
                      'id'      => "{$cible}-{$rang}-{$champ}",
                      'medias'  => $medias, 'documents' => $documents,
                  ]) ?>
                <?php endif; ?>
              <?php endforeach; ?>

              <div class="bo-champ bo-champ--case bo-bloc-edit__retrait">
                <input id="<?= e($cible) ?>-<?= $rang ?>-retirer" type="checkbox"
                       name="<?= e($cible) ?>[<?= $rang ?>][retirer]" value="1">
                <label for="<?= e($cible) ?>-<?= $rang ?>-retirer">Supprimer cette entrée au prochain enregistrement</label>
              </div>
            </div>
          </details>
        <?php endforeach; ?>

        <?php if ($entrees === []): ?>
          <p class="bo-vide">Aucune entrée pour l’instant.</p>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <div class="bo-barre-actions">
    <button class="bo-btn" type="submit">Enregistrer</button>
    <a class="bo-btn bo-btn--fantome" href="<?= url('/admin/avance?nom=' . $liste) ?>">Éditeur avancé</a>
  </div>
</form>

<?php foreach ($cibles as $cible => $intitule): ?>
  <div class="bo-ajout-bloc">
    <form method="post" action="<?= url('/admin/listes/' . $liste . '/ajout') ?>" data-ajout-bloc>
      <?= Csrf::champ() ?>
      <input type="hidden" name="cible" value="<?= e($cible) ?>">
      <button class="bo-btn bo-btn--contour" type="submit">Ajouter dans « <?= e($intitule) ?> »</button>
      <p class="bo-aide">L’entrée est ajoutée à la fin, hors ligne, et la saisie en cours est enregistrée en même temps.</p>
    </form>
  </div>
<?php endforeach; ?>
