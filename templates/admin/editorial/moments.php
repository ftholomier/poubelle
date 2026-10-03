<?php
/** Calendrier des 100 moments. Variables : $slots, $stats, $start, $next */
use App\Core\Auth;
use App\Data\Fiches;

$label = ['en-ligne' => ['En ligne', 'ok'], 'pret' => ['Prêt', 'info'], 'brouillon' => ['Brouillon', 'warn'], 'manquant' => ['À écrire', 'ko']];
?>
<div class="kpis">
  <div class="kpi"><b><?= (int) $stats['publies'] ?></b><span>moments en ligne</span><small>révélés au public</small></div>
  <div class="kpi"><b><?= (int) $stats['prets'] ?></b><span>prêts</span><small>publiés, révélés à leur date</small></div>
  <div class="kpi<?= $stats['manquants'] ? ' kpi--yellow' : '' ?>"><b><?= (int) $stats['manquants'] ?></b><span>à écrire</span><small><?= $next ? 'prochain : n° ' . (int) $next['n'] . ' le ' . e(date_num($next['date'])) : 'calendrier terminé' ?></small></div>
  <?php if ($stats['doublons']): ?><div class="kpi kpi--pink"><b><?= (int) $stats['doublons'] ?></b><span>numéros en double</span><small>deux fiches pour le même numéro</small></div><?php endif; ?>
</div>
<p class="small muted" style="margin:0">Un moment est révélé chaque semaine à partir du <b><?= e(date_num($start)) ?></b><?= Auth::isAdmin() ? ' (<a href="/admin/reglages?groupe=centenary">modifier la date</a>)' : '' ?>. Une fiche « Moment » publiée avec un numéro apparaît automatiquement dans sa case le jour venu.</p>
<?php
$sortable = !$stats['doublons'] && array_filter($slots, fn ($x) => !$x['past'] && $x['fiche']);
$future = array_values(array_map(fn ($x) => ['n' => $x['n'], 'date' => $x['date'], 'label' => date_num($x['date'])], array_filter($slots, fn ($x) => !$x['past'])));
?>
<?php if ($sortable): ?>
<p class="small muted" style="margin:0">Pour changer la semaine d’un moment pas encore révélé : <b>↑ / ↓</b> d’une semaine, ou attrapez l’icône <b>quatre flèches</b> et glissez-le plus loin (le <b>trait jaune</b> montre sa future place). Les dates se recalculent ; les moments déjà révélés ne bougent plus.</p>
<div class="savebar" data-moments-bar hidden>
  <span><b>Nouveau calendrier</b> non enregistré : les moments surlignés changent de semaine.</span>
  <button type="button" class="btn btn--ghost btn--sm" data-moments-cancel>Annuler</button>
  <button type="button" class="btn btn--navy btn--sm" data-moments-save>Enregistrer le calendrier</button>
</div>
<?php elseif ($stats['doublons']): ?>
<p class="alert" style="margin:0">Deux fiches portent le même numéro : corrigez le numéro de l’une d’elles pour pouvoir réorganiser le calendrier par glisser-déposer.</p>
<?php endif; ?>
<div class="table">
  <table>
    <thead><tr><th>N°</th><th>Révélation</th><th>Moment</th><th>Année</th><th>État</th><th></th></tr></thead>
    <tbody<?= $sortable ? ' data-sortable data-moments="' . e(json_encode($future)) . '"' : '' ?>>
    <?php foreach ($slots as $s): $f = $s['fiche']; [$l, $c] = $label[$s['state']]; $mv = $sortable && !$s['past']; ?>
      <tr class="<?= $next && $next['n'] === $s['n'] ? 'is-next' : '' ?>"<?= $f ? ' data-href="/admin/fiche/' . (int) $f['id'] . '"' : '' ?><?= $mv ? ' data-sort-item data-id="' . ($f ? (int) $f['id'] : '') . '" data-n0="' . (int) $s['n'] . '"' : '' ?>>
        <td class="t-num"><span class="mvcell"><?= $mv ? \App\Admin\Form::mover('mover--sm') : '' ?><span data-slot-n><?= sprintf('%03d', $s['n']) ?></span></span></td>
        <td class="nowrap<?= $s['past'] ? '' : ' muted' ?>" data-slot-date><?= e(date_num($s['date'])) ?></td>
        <td><?php if ($f): ?><a class="rowlink" href="/admin/fiche/<?= (int) $f['id'] ?>"><?= e($f['title']) ?></a><?php foreach ($s['dups'] as $d): ?><br><span class="ko">Doublon :</span> <a href="/admin/fiche/<?= (int) $d['id'] ?>"><?= e($d['title']) ?></a><?php endforeach; ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td class="t-num"><?= e((string) ($f['mo']['year'] ?? '')) ?></td>
        <td><span class="pill pill--<?= $c ?>"><?= e($l) ?></span><?= $f && $f['status'] !== 'publie' ? ' <span class="xs muted">' . e(Fiches::STATUSES[$f['status']] ?? '') . '</span>' : '' ?></td>
        <td><?php if (!$f): ?><a class="btn btn--sm btn--yellow" data-write href="/admin/fiche/nouvelle/moment?numero=<?= (int) $s['n'] ?>&amp;date=<?= e($s['date']) ?>">+ Écrire</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
