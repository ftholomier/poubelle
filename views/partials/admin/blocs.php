<?php
/**
 * Les blocs d'une page ou d'une fiche, en formulaire.
 *
 * Chaque bloc est un dépliant : replié il tient sur une ligne et l'on voit la
 * page entière d'un regard ; déplié il montre ses champs. Une page de quinze
 * blocs tous ouverts serait un mur, et l'on n'y retrouverait rien.
 *
 * Le formulaire d'ajout, lui, est rendu par admin/ajout-bloc.php APRÈS le
 * formulaire principal : un <form> imbriqué dans un autre n'existe pas en
 * HTML, et le navigateur écarte le second sans rien dire — le bouton
 * « ajouter » disparaît alors de la page.
 *
 * @var array  $sections   les blocs à éditer
 * @var string[] $medias
 * @var string[] $documents
 * @var App\Core\View $view
 */
use App\Admin\Blocs;
use App\Core\Csrf;

/** Deux mots qui disent ce que contient un bloc replié. */
$resume = static function (array $bloc): string {
    foreach (['titre', 'intitule', 'texte', 'image_alt'] as $champ) {
        $valeur = trim((string) ($bloc[$champ] ?? ''));
        if ($valeur !== '') {
            return mb_strimwidth($valeur, 0, 70, '…');
        }
    }
    $premier = $bloc['paragraphes'][0] ?? ($bloc['items'][0]['titre'] ?? '');
    return $premier !== '' ? mb_strimwidth((string) $premier, 0, 70, '…') : 'bloc sans titre';
};
?>

<div class="bo-blocs">
  <?php foreach ($sections as $rang => $bloc): ?>
    <?php
    $type = (string) ($bloc['type'] ?? 'texte');
    $modele = Blocs::TYPES[$type] ?? null;
    $idBloc = 'bloc-' . $rang;
    ?>
    <details class="bo-bloc-edit" id="<?= e($idBloc) ?>">
      <summary class="bo-bloc-edit__tete">
        <span class="bo-etiquette"><?= e($modele['nom'] ?? $type) ?></span>
        <span class="bo-bloc-edit__resume"><?= e($resume($bloc)) ?></span>
        <span class="bo-bloc-edit__rang">#<?= $rang + 1 ?></span>
      </summary>

      <div class="bo-bloc-edit__corps">
        <?php if ($modele === null): ?>
          <p class="bo-message bo-message--attention">
            Type de bloc inconnu (<code><?= e($type) ?></code>). Il est conservé tel quel ;
            l’éditeur avancé permet de le corriger.
          </p>
          <input type="hidden" name="bloc[<?= $rang ?>][type]" value="<?= e($type) ?>">
        <?php else: ?>
          <input type="hidden" name="bloc[<?= $rang ?>][type]" value="<?= e($type) ?>">
          <p class="bo-aide bo-aide-bloc"><?= e($modele['aide']) ?></p>

          <?php foreach ($modele['champs'] as $champ => $nature): ?>
            <?php if (str_starts_with($nature, 'items:')): ?>
              <?php
              $sous = Blocs::SOUS_BLOCS[substr($nature, 6)] ?? [];
              $entrees = (array) ($bloc[$champ] ?? []);
              // Deux lignes vides en réserve : ajouter une entrée ne doit pas
              // demander d'enregistrer la page d'abord.
              $total = count($entrees) + 2;
              ?>
              <fieldset class="bo-sous-champ">
                <legend><?= e(Blocs::libelle($champ)) ?></legend>
                <?php for ($j = 0; $j < $total; $j++): ?>
                  <?php $entree = $entrees[$j] ?? []; ?>
                  <div class="bo-entree">
                    <span class="bo-entree__rang"><?= $j + 1 ?></span>
                    <div class="bo-entree__champs">
                      <?php foreach ($sous as $sousChamp => $sousNature): ?>
                        <?php
                        // `lien.libelle` se saisit dans deux champs distincts
                        $segments = explode('.', $sousChamp);
                        $nom = "bloc[$rang][$champ][$j]" . implode('', array_map(static fn($s) => "[$s]", $segments));
                        $valeur = $entree;
                        foreach ($segments as $segment) {
                            $valeur = is_array($valeur) ? ($valeur[$segment] ?? '') : '';
                        }
                        ?>
                        <?= $view->partial('admin/champ', [
                            'nature'  => $sousNature,
                            'nom'     => $nom,
                            'valeur'  => $valeur,
                            'libelle' => Blocs::libelle($sousChamp),
                            'id'      => "b{$rang}-{$champ}-{$j}-" . str_replace('.', '-', $sousChamp),
                            'medias'  => $medias,
                            'documents' => $documents,
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
                  'nom'     => "bloc[$rang][$champ]",
                  'valeur'  => $bloc[$champ] ?? '',
                  'libelle' => Blocs::libelle($champ),
                  'id'      => "b{$rang}-{$champ}",
                  'medias'  => $medias,
                  'documents' => $documents,
              ]) ?>
            <?php endif; ?>
          <?php endforeach; ?>

          <div class="bo-rangee">
            <?= $view->partial('admin/champ', [
                'nature' => 'ligne', 'nom' => "bloc[$rang][id]",
                'valeur' => $bloc['id'] ?? '', 'libelle' => 'Ancre',
                'id' => "b{$rang}-id",
                'aide' => 'Permet de pointer directement cette section : /page#ancre.',
            ]) ?>
            <?= $view->partial('admin/champ', [
                'nature' => 'choix:|blanc|teinte|sombre', 'nom' => "bloc[$rang][fond]",
                'valeur' => $bloc['fond'] ?? '', 'libelle' => 'Fond',
                'id' => "b{$rang}-fond",
            ]) ?>
          </div>

          <div class="bo-champ bo-champ--case bo-bloc-edit__retrait">
            <input id="b<?= $rang ?>-retirer" type="checkbox" name="bloc[<?= $rang ?>][retirer]" value="1">
            <label for="b<?= $rang ?>-retirer">Retirer ce bloc au prochain enregistrement</label>
          </div>
        <?php endif; ?>
      </div>
    </details>
  <?php endforeach; ?>

  <?php if ($sections === []): ?>
    <p class="bo-vide">Cette page n’a encore aucun bloc. Ajoutez-en un ci-dessous.</p>
  <?php endif; ?>
</div>
