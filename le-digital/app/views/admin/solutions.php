<?php /** @var array $solutions @var string $csrf */
$n = count($solutions);
?>
<div class="toolbar">
  <p class="intro">Les solutions présentées sur la page « Mes solutions » (une carte chacune, puis sa page de détail), proposées dans le formulaire de contact et connues de l’assistant IA. Masque celles qui ne sont pas prêtes, ajoute les nouvelles au fil de tes projets.</p>
  <a class="btn btn--red" href="/admin/solutions/nouvelle">+ Ajouter une solution</a>
</div>
<div class="card card--flush">
  <table class="table sol-table">
    <thead><tr><th>Ordre</th><th>Solution</th><th class="hide-sm">Type</th><th>Sur le site</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($solutions as $i => $so): $v = LD\Solutions::view($so); ?>
        <tr>
          <td class="sol-order">
            <form method="post" action="/admin/solutions/<?= e($so['id']) ?>/ordre"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="dir" value="up"><button type="submit" class="iconbtn" aria-label="Monter"<?= $i === 0 ? ' disabled' : '' ?>>↑</button></form>
            <form method="post" action="/admin/solutions/<?= e($so['id']) ?>/ordre"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="dir" value="down"><button type="submit" class="iconbtn" aria-label="Descendre"<?= $i === $n - 1 ? ' disabled' : '' ?>>↓</button></form>
          </td>
          <td><a href="/admin/solutions/<?= e($so['id']) ?>"><b><?= e($v['name'] ?? $so['id']) ?></b></a><br><small class="muted"><?= e(mb_strimwidth((string) (($v['short'] ?? '') ?: ($v['tagline'] ?? '')), 0, 90, '…')) ?></small></td>
          <td class="hide-sm"><small><?= $so['kind'] === 'intensiti' ? 'Bloc Intensiti (textes dans Contenus)' : 'Bloc standard · ' . e(LD\Solutions::THEMES[$so['theme']] ?? '') ?></small></td>
          <td>
            <form method="post" action="/admin/solutions/<?= e($so['id']) ?>/affichage"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button type="submit" class="switch<?= $so['enabled'] ? ' is-on' : '' ?>" aria-pressed="<?= $so['enabled'] ? 'true' : 'false' ?>" title="<?= $so['enabled'] ? 'Masquer sur le site' : 'Afficher sur le site' ?>"><span></span><?= $so['enabled'] ? 'Affichée' : 'Masquée' ?></button>
            </form>
          </td>
          <td class="sol-acts"><a class="link" href="/admin/solutions/<?= e($so['id']) ?>">Modifier</a><?php if ($so['enabled'] || $so['kind'] === 'standard'): ?> · <a class="link" href="<?= e(LD\Solutions::url($so)) ?>" target="_blank" rel="noopener"><?= $so['enabled'] ? 'Voir ↗' : 'Aperçu ↗' ?></a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
