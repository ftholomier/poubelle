<?php
/** Sauvegardes. Variables : $list, $enabled, $hour, $keep, $disk */
use App\Admin\Base;
use App\Core\Auth;
?>
<div class="toolbar">
  <p class="small muted grow" style="margin:0"><?= $enabled ? 'Sauvegarde automatique chaque jour à partir de ' . (int) $hour . ' h (données, réglages, comptes, journaux) ; les ' . (int) $keep . ' dernières sont conservées.' : 'La sauvegarde automatique est désactivée.' ?> Les photos originales, volumineuses, sont incluses dans une archive à part (« avec photos »).<?= $disk ? ' Espace libre : ' . e(Base::size((int) $disk)) . '.' : '' ?> Pensez à copier régulièrement une sauvegarde hors du serveur.</p>
  <?php if (Auth::isAdmin()): ?>
  <form method="post" action="/admin/sauvegardes" class="row">
    <?= csrf_field() ?>
    <label class="toggle small"><input type="checkbox" name="photos" value="1"><span class="toggle__box"></span><span>avec les photos</span></label>
    <button type="submit" name="action" value="lancer" class="btn btn--navy">Sauvegarder maintenant</button>
  </form>
  <?php else: ?>
  <span class="small muted">Lancer et télécharger une sauvegarde : réservé aux administrateurs (elle contient les clés et les comptes).</span>
  <?php endif; ?>
</div>
<div class="table">
  <table>
    <thead><tr><th>Archive</th><th>Date</th><th>Taille</th><th>Contenu</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $b): ?>
      <tr>
        <td class="small"><?= e($b['file']) ?></td>
        <td class="nowrap"><?= e(date('d/m/Y H:i', strtotime($b['at']))) ?> <span class="xs muted"><?= e(Base::ago($b['at'])) ?></span></td>
        <td class="t-num"><?= e(Base::size($b['size'])) ?></td>
        <td><?= $b['photos'] ? '<span class="pill pill--yellow">Avec photos</span>' : '<span class="pill">Données</span>' ?></td>
        <td class="nowrap">
          <?php if (Auth::isAdmin()): ?><a class="btn btn--sm" href="/admin/sauvegardes/<?= e($b['file']) ?>">Télécharger</a><?php endif; ?>
          <?php if (Auth::can('destroy')): ?><form method="post" action="/admin/sauvegardes" style="display:inline" data-confirm="Supprimer cette sauvegarde ?||Supprimer|danger"><?= csrf_field() ?><input type="hidden" name="file" value="<?= e($b['file']) ?>"><button type="submit" name="action" value="supprimer" class="iconbtn" title="Supprimer" aria-label="Supprimer">✕</button></form><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="5" class="muted" style="padding:24px;text-align:center">Aucune sauvegarde pour l’instant.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<div class="card card--pad">
  <h2 class="card__t card__t--sm">Restaurer</h2>
  <p class="small" style="margin:0">Une archive contient les dossiers <code>data/</code> et <code>storage/</code> du site. Pour revenir à une sauvegarde : décompressez-la puis remplacez ces dossiers sur le serveur (gestionnaire de fichiers o2switch). Une fiche se restaure plus simplement depuis son onglet « Historique ».</p>
</div>
