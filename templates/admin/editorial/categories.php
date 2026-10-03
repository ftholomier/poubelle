<?php
/** Arborescence des rubriques (méga-menus). Variables : $tree */
use App\Data\Categories;
?>
<p class="small muted" style="margin:0">Les rubriques reprennent à l’identique l’arborescence de l’ancien site et forment les méga-menus. Cliquez sur une rubrique pour modifier son libellé (FR/EN), son texte d’introduction, sa position dans le menu et l’ordre de ses fiches.</p>
<div class="table">
  <table>
    <thead><tr><th>Rubrique</th><th>Libellé EN</th><th>Adresse</th><th>Fiches</th><th>Position</th><th>Ordre des fiches</th><th>Menu</th></tr></thead>
    <tbody>
    <?php foreach ($tree as $c): ?>
      <tr data-href="/admin/rubriques?rubrique=<?= e(rawurlencode($c['slug'])) ?>">
        <td style="padding-left:<?= 12 + 24 * $c['depth'] ?>px"><?= $c['depth'] ? '<span class="muted">└</span> ' : '' ?><a class="rowlink" href="/admin/rubriques?rubrique=<?= e(rawurlencode($c['slug'])) ?>"<?= $c['depth'] === 0 ? ' style="font-family:var(--display);font-weight:900;text-transform:uppercase"' : '' ?>><?= e(Categories::label($c['slug'])) ?></a></td>
        <td class="small"><?= !empty($c['label_en']) ? e($c['label_en']) : '<span class="muted">auto</span>' ?></td>
        <td class="xs"><a href="<?= e($c['path']) ?>" target="_blank" rel="noopener"><?= e($c['path']) ?></a></td>
        <td class="t-num"><?= (int) $c['count'] ?></td>
        <td class="t-num"><?= $c['position'] !== null ? (int) $c['position'] : '<span class="muted">—</span>' ?></td>
        <td class="small"><?= !empty($c['order']) ? '<span class="pill pill--yellow">Manuel</span>' : '<span class="muted">Automatique</span>' ?></td>
        <td><?= !empty($c['technical']) ? '<span class="pill pill--corbeille">Masquée</span>' : '<span class="ok">✓</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
