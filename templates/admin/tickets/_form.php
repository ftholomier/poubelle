<?php
/** Champs d'un ticket (création et modification). Variables : $t (ticket ou []), $admin. */
use App\Services\Tickets;
$t = $t ?? [];
?>
<label class="f"><span class="f__k">Titre</span><input type="text" name="title" required maxlength="160" value="<?= e($t['title'] ?? '') ?>" placeholder="En quelques mots : « Le bouton Enregistrer ne répond plus sur les fiches match »"></label>
<div class="tk-row">
  <div class="f"><span class="f__k">Type de demande</span>
    <div class="tk-types">
      <?php foreach (Tickets::TYPES as $k => $label): ?>
        <label class="tk-type"><input type="radio" name="type" value="<?= e($k) ?>"<?= ($t['type'] ?? 'bug') === $k ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
      <?php endforeach; ?>
    </div>
  </div>
  <label class="f"><span class="f__k">Priorité</span><select name="priority">
    <?php foreach (Tickets::PRIORITIES as $k => $label): ?><option value="<?= e($k) ?>"<?= ($t['priority'] ?? 'normale') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
  </select></label>
</div>
<label class="f"><span class="f__k">Écran ou page concernés (facultatif)</span><input type="text" name="page" maxlength="300" value="<?= e($t['page'] ?? $page ?? '') ?>" placeholder="/admin/matchs, ou l’adresse d’une page du site"></label>
<label class="f"><span class="f__k">Votre demande</span><textarea name="body" required rows="7" maxlength="10000" placeholder="Ce qui se passe (ou ce que vous voudriez), où, et ce que vous attendiez. Pour un bug : ce que vous avez fait juste avant."><?= e($t['body'] ?? '') ?></textarea></label>
<label class="f"><span class="f__k">Prompt pour l’IA (facultatif)</span><textarea name="prompt" rows="6" maxlength="10000" placeholder="Écrivez la consigne que vous donneriez à l’IA qui développe le musée, comme si vous lui parliez : « Sur la fiche joueur, ajoute sous la photo… ». Soyez précis : c’est vous qui savez exactement ce que vous voulez."><?= e($t['prompt'] ?? '') ?></textarea></label>
