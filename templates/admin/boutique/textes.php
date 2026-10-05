<?php
/**
 * Boutique › Banque de textes : listes de phrases que le client choisit sur un modèle (champ du
 * client « choix dans une liste »). Seules les phrases validées lui sont proposées.
 * Variables : $lists, $ai (clé Gemini réglée)
 */
?>
<link rel="stylesheet" href="<?= asset('admin/boutique.css') ?>">
<p class="small" style="margin:0 0 12px;max-width:95ch">Les phrases que le client pourra choisir sur un modèle : dans l’éditeur, un « champ du client » réglé sur « Choix dans une liste » lui propose les phrases <b>validées</b> de la liste choisie. Ajoutez-en à la main ou demandez-en à l’IA : ses propositions arrivent « à valider » et ne sont jamais proposées avant votre accord.</p>
<?php foreach ($lists as $l): $okN = count(array_filter($l['items'], fn ($i) => $i['ok'])); ?>
<section class="card card--pad shoplist" id="l-<?= e($l['id']) ?>" style="margin-bottom:16px">
  <form method="post" action="/admin/boutique/textes">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($l['id']) ?>">
    <div class="card__head"><h2 class="card__t"><?= e($l['name']) ?></h2><span class="card__note"><?= $okN ?> validée<?= $okN > 1 ? 's' : '' ?> · <?= count($l['items']) - $okN ?> à valider</span></div>
    <div class="fgrid fgrid--2">
      <label class="f"><span class="f__k">Nom de la liste</span><input class="in" name="name" value="<?= e($l['name']) ?>" required></label>
      <label class="f"><span class="f__k">Usage (aide aussi l’IA)</span><input class="in" name="note" value="<?= e($l['note']) ?>"></label>
    </div>
    <table class="tbl shoptexts"><thead><tr><th>Phrase</th><th>Note, source</th><th>Validée</th><th>Retirer</th></tr></thead><tbody>
    <?php foreach ($l['items'] as $n => $i): ?>
      <tr class="<?= $i['ok'] ? '' : 'is-todo' ?>">
        <td><input type="hidden" name="items[<?= $n ?>][id]" value="<?= e($i['id']) ?>"><input class="in in--sm" name="items[<?= $n ?>][text]" value="<?= e($i['text']) ?>" maxlength="160"></td>
        <td><input class="in in--sm" name="items[<?= $n ?>][note]" value="<?= e($i['note']) ?>"></td>
        <td class="c"><label class="toggle"><input type="checkbox" name="items[<?= $n ?>][ok]" value="1"<?= $i['ok'] ? ' checked' : '' ?>><span class="toggle__box"></span></label></td>
        <td class="c"><label class="toggle"><input type="checkbox" name="items[<?= $n ?>][del]" value="1"><span class="toggle__box"></span></label></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
    <label class="f" style="margin-top:10px"><span class="f__k">Ajouter des phrases (une par ligne, validées d’office)</span><textarea class="in" name="add" rows="2"></textarea></label>
    <div class="row" style="justify-content:flex-end;margin-top:8px"><button class="btn btn--navy btn--sm" name="action" value="save">Enregistrer la liste</button></div>
  </form>
  <form method="post" action="/admin/boutique/textes" class="row shoptexts__ai" data-busy="L’IA écrit…">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($l['id']) ?>"><input type="hidden" name="action" value="ai">
    <label class="f" style="flex:1 1 320px;margin:0"><span class="f__k">Consigne pour l’IA (facultatif)</span><input class="in in--sm" name="hint" placeholder="ex. humour sur la météo de Bonal, phrases pour le centenaire…"></label>
    <label class="f" style="margin:0;width:90px"><span class="f__k">Nombre</span><input class="in in--sm" type="number" name="count" value="10" min="1" max="30"></label>
    <button class="btn btn--yellow btn--sm"<?= $ai ? '' : ' disabled title="Réglez d’abord la clé Gemini (Système › Réglages)"' ?>>Proposer des phrases avec l’IA</button>
  </form>
  <form method="post" action="/admin/boutique/textes" style="margin-top:10px" data-confirm="Supprimer la liste ?|La liste « <?= e($l['name']) ?> » sera supprimée ; les modèles qui l’utilisent afficheront leur texte d’exemple.|Supprimer|danger">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($l['id']) ?>"><input type="hidden" name="action" value="delete">
    <button class="btn btn--ghost btn--sm">Supprimer la liste</button>
  </form>
</section>
<?php endforeach; ?>
<form method="post" action="/admin/boutique/textes" class="card card--pad">
  <?= csrf_field() ?><input type="hidden" name="action" value="new">
  <h2 class="card__t card__t--sm">Nouvelle liste</h2>
  <div class="row" style="gap:10px;flex-wrap:wrap;align-items:end">
    <label class="f" style="flex:1 1 220px;margin:0"><span class="f__k">Nom</span><input class="in" name="name" placeholder="ex. Phrases du centenaire" required></label>
    <label class="f" style="flex:2 1 320px;margin:0"><span class="f__k">Usage</span><input class="in" name="note" placeholder="ex. pour le dos des t-shirts 2028"></label>
    <button class="btn btn--navy btn--sm">Créer la liste</button>
  </div>
</form>
