<?php
/** Médiathèque › Crédits dans les légendes. Variables : $rows (chemin => old, caption, credit) */
?>
<div class="card card--pad">
  <h2 class="card__t card__t--sm">Crédits écrits dans les légendes</h2>
  <p class="small">Ces photos n’ont pas de crédit, mais leur légende en contient un (« Gomel Photo Jean-Yves Caddedu »). Le crédit passe dans son champ (nom seul), la légende garde le reste. Une légende qui ne contenait que le crédit devient vide. Cochez « Ne pas toucher » pour écarter un cas douteux.</p>
  <?php if (!$rows): ?>
    <p class="alert alert--ok" style="margin:0">Aucune légende à découper : tous les crédits sont rangés.</p>
  <?php else: ?>
  <form method="post" action="/admin/medias/credits" data-confirm="Découper <?= count($rows) ?> légendes ?|Le crédit passe dans son champ ; les photos cochées « Ne pas toucher » restent telles quelles.|Appliquer">
    <?= csrf_field() ?>
    <p><button class="btn btn--yellow">Appliquer à <?= count($rows) ?> photo<?= count($rows) > 1 ? 's' : '' ?></button></p>
    <div class="table"><table>
      <thead><tr><th>Ne pas toucher</th><th></th><th>Légende actuelle</th><th>Nouvelle légende</th><th>Crédit</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $rel => $r): ?>
        <tr>
          <td><input type="checkbox" class="check" name="skip[]" value="<?= e($rel) ?>" aria-label="Ne pas toucher"></td>
          <td><img class="thumb" src="<?= e(img($rel, 160)) ?>" alt="" loading="lazy"></td>
          <td class="small muted"><?= e($r['old']) ?></td>
          <td class="small"><?= $r['caption'] !== '' ? e($r['caption']) : '<span class="muted">(vide)</span>' ?></td>
          <td class="small"><b><?= e($r['credit']) ?></b></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p><button class="btn btn--yellow">Appliquer à <?= count($rows) ?> photo<?= count($rows) > 1 ? 's' : '' ?></button></p>
  </form>
  <?php endif; ?>
</div>
