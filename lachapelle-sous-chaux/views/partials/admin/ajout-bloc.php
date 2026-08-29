<?php
/**
 * Ajout d'un bloc en fin de page ou de fiche.
 *
 * Formulaire distinct du formulaire d'édition — un <form> imbriqué n'existe
 * pas en HTML — mais il emporte la saisie en cours : admin.js recopie au clic
 * tous les champs du formulaire principal en champs cachés, et le serveur
 * enregistre la page complète avant d'y ajouter le bloc vide. Sans cela,
 * cliquer « ajouter » ferait perdre ce qui vient d'être tapé.
 *
 * @var string $action
 */
use App\Admin\Blocs;
use App\Core\Csrf;
?>
<div class="bo-ajout-bloc">
  <form method="post" action="<?= e($action) ?>" data-ajout-bloc>
    <?= Csrf::champ() ?>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="type-bloc">Ajouter un bloc</label>
        <select id="type-bloc" name="type">
          <?php foreach (Blocs::TYPES as $cle => $modele): ?>
            <option value="<?= e($cle) ?>"><?= e($modele['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="bo-champ">
        <label for="type-bloc-envoi" class="bo-visuellement-cache">Ajouter</label>
        <button id="type-bloc-envoi" class="bo-btn bo-btn--contour" type="submit">Ajouter en fin de page</button>
      </div>
    </div>
    <p class="bo-aide">
      Le bloc est ajouté à la fin, et la saisie en cours est enregistrée en même
      temps. Pour changer l’ordre des blocs, l’Éditeur avancé permet de les
      déplacer dans le JSON.
    </p>
  </form>
</div>
