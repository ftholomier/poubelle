<?php
/** Liste des pages modifiables. Variables : $rows */
use App\Admin\Base;
?>
<div class="toolbar"><p class="small muted grow" style="margin:0">Les textes de chaque page du site de l’association. Les pages en liste (actualités, agenda, équipe…) ont leur propre onglet.</p><a class="btn" href="/apercu-association/" target="_blank" rel="noopener">Aperçu du site ↗</a></div>
<div class="table">
  <table>
    <thead><tr><th>Page</th><th>Contenu</th><th>À vérifier</th><th>Dernière modification</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr data-href="/admin/association/page/<?= e($r['key']) ?>">
        <td><a class="rowlink" href="/admin/association/page/<?= e($r['key']) ?>"><b><?= e($r['label']) ?></b></a></td>
        <td><?= $r['default'] ? '<span class="pill">Texte de départ</span>' : '<span class="pill pill--ok">Modifié</span>' ?></td>
        <td><?= $r['verify'] ? '<span class="pill pill--warn">' . (int) $r['verify'] . ' point' . ($r['verify'] > 1 ? 's' : '') . '</span>' : '<span class="muted">—</span>' ?></td>
        <td class="small"><?= $r['versions'] ? e($r['versions'][0]['by']) . ' · ' . e(Base::ago($r['versions'][0]['at'])) : '<span class="muted">—</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
