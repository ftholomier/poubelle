<?php
/** Corbeille. Variables : $rows */
use App\Admin\Base;
use App\Core\Auth;
use App\Data\Fiches;
?>
<p class="alert">Les fiches à la corbeille ne sont plus visibles sur le site. Vous pouvez les en sortir à tout moment<?= Auth::can('destroy') ? ' ; la suppression définitive est réservée aux administrateurs' : '' ?>.</p>
<form method="post" action="/admin/fiches/lot" data-bulk-form data-confirm="Appliquer l’action ?||Appliquer">
  <?= csrf_field() ?>
  <input type="hidden" name="back" value="/admin/corbeille">
  <div class="bulk" data-bulk-bar hidden><span data-bulk-count></span><button type="submit" name="action" value="sortir">Sortir de la corbeille</button><?php if (Auth::can('destroy')): ?><button type="submit" name="action" value="supprimer">Supprimer définitivement</button><?php endif; ?></div>
  <div class="table"><table>
    <thead><tr><th style="width:44px"><input type="checkbox" class="check" data-check-all aria-label="Tout sélectionner"></th><th>Titre</th><th>Type</th><th>Mise à la corbeille</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $s): ?>
        <tr data-href="/admin/fiche/<?= (int) $s['id'] ?>"><td><input type="checkbox" class="check" name="ids[]" value="<?= (int) $s['id'] ?>"></td><td><a class="rowlink" href="/admin/fiche/<?= (int) $s['id'] ?>"><?= e($s['title']) ?></a></td><td><?= e(Fiches::TYPES[$s['type']] ?? $s['type']) ?></td><td class="xs muted"><?= e(Base::ago($s['modified'] ?? null)) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4" style="padding:28px;text-align:center" class="muted">La corbeille est vide.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</form>
