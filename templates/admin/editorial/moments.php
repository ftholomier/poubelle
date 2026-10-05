<?php
/**
 * Calendrier des 100 moments. Variables : $dated (moments validés, dans l'ordre de parution),
 * $undated (à relire, brouillons ; « suggest » : date anniversaire proposée), $stats, $alerts,
 * $pace, $end
 */
$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$state = function (array $r): array {
    if ($r['date'] !== null) {
        return $r['visible'] ? ['En ligne', 'ok'] : ['Planifié', 'info'];
    }
    return $r['status'] === 'relire' ? ['À relire', 'warn'] : ['Brouillon', 'ko'];
};
$aiBadge = function (array $r): string {
    if (!$r['ai']) {
        return '';
    }
    return $r['validated']
        ? ' <span class="xs muted" title="Premier jet rédigé par l’IA, relu et validé">1<sup>er</sup> jet IA · validé par ' . e((string) $r['validated']['by']) . '</span>'
        : ' <span class="pill pill--relire" title="Premier jet rédigé par l’IA : à relire et vérifier avant de valider">1<sup>er</sup> jet IA à relire</span>';
};
$tomorrow = date('Y-m-d', strtotime('+1 day'));
?>
<div class="kpis">
  <div class="kpi"><b><?= (int) $stats['online'] ?></b><span>moments en ligne</span><small>sur 100</small></div>
  <div class="kpi"><b><?= (int) $stats['planned'] ?></b><span>planifiés</span><small>validés, en ligne à leur date</small></div>
  <div class="kpi<?= $stats['review'] ? ' kpi--yellow' : '' ?>"><b><?= (int) $stats['review'] ?></b><span>à relire</span><small><?= $stats['drafts'] ? (int) $stats['drafts'] . ' brouillon' . ($stats['drafts'] > 1 ? 's' : '') . ' en plus' : 'à valider avec une date' ?></small></div>
  <a class="kpi" href="/admin/moments/idees"><b><?= (int) $stats['ideas'] ?></b><span>idées retenues</span><small>boîte à idées →</small></a>
</div>

<p class="small" style="margin:0;max-width:95ch">
  Vous choisissez la <b>date de parution</b> de chaque moment : dans la fiche, statut <b>Planifié</b> et sa date, puis <b>Enregistrer</b>. C’est la validation, une seule suffit.
  Le <b>numéro</b> suit l’ordre des dates et ne change plus une fois le moment en ligne. Sur le site, les cases à venir restent « À venir », sans date.
  <?php if ($pace['left']): ?>Il reste <b><?= (int) $pace['weeks'] ?> semaines</b> jusqu’au centenaire (<?= e(date_fr($end)) ?>) pour <b><?= (int) $pace['left'] ?> moment<?= $pace['left'] > 1 ? 's' : '' ?></b> à dater<?= $pace['weeks'] ? ', soit environ ' . e(str_replace('.', ',', (string) $pace['per_week'])) . ' par semaine' : '' ?>.<?php else: ?>Les 100 moments ont leur date.<?php endif; ?>
</p>

<div class="row">
  <a class="btn btn--yellow" href="/admin/fiche/nouvelle/moment">+ Écrire un moment</a>
  <a class="btn" href="/admin/moments/idees">Boîte à idées (IA)</a>
</div>

<?php if ($alerts): ?>
  <section class="card" id="alertes">
    <div class="card__head"><h2 class="card__t">À surveiller</h2><span class="card__note"><?= count($alerts) ?> point<?= count($alerts) > 1 ? 's' : '' ?></span></div>
    <ul class="card__body small" style="margin:0;padding-left:34px">
      <?php foreach ($alerts as $a): ?><li class="<?= $a['level'] === 'warn' ? 'warn' : '' ?>"><?= e($a['text']) ?></li><?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<section class="card" id="calendrier">
  <div class="card__head"><h2 class="card__t">Calendrier de parution</h2><span class="card__note"><?= count($dated) ?> / 100 moments datés</span></div>
  <div class="table" style="border:0">
    <table>
      <thead><tr><th>N°</th><th>Parution</th><th>Moment</th><th>Année</th><th>État</th></tr></thead>
      <tbody>
      <?php foreach ($dated as $r): [$l, $c] = $state($r); $ts = strtotime($r['date']); ?>
        <tr id="m-<?= (int) $r['id'] ?>">
          <td class="t-num"><?= $r['number'] ? sprintf('%03d', $r['number']) : '<span class="ko" title="Au-delà des 100">—</span>' ?></td>
          <td class="nowrap">
            <?php if ($r['visible']): ?>
              <?= e(date_num(date('Y-m-d', $ts))) ?> <span class="xs muted"><?= e(date('G\hi', $ts)) ?></span>
            <?php else: ?>
              <form method="post" action="/admin/moments/date" class="row" style="gap:6px;flex-wrap:nowrap" title="Changer la date de parution">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="date" name="date" class="in in--sm" value="<?= e(date('Y-m-d', $ts)) ?>" min="<?= e($tomorrow) ?>" max="<?= e($end) ?>" aria-label="Date de parution de « <?= e($r['title']) ?> »" required>
                <input type="time" name="heure" class="in in--sm" value="<?= e(date('H:i', $ts)) ?>" aria-label="Heure de parution" style="width:11em">
                <button type="submit" class="btn btn--sm">Changer</button>
              </form>
            <?php endif; ?>
          </td>
          <td><a class="rowlink" href="/admin/fiche/<?= (int) $r['id'] ?>"><?= e($r['title'] ?: 'Sans titre') ?></a><?= $aiBadge($r) ?></td>
          <td class="t-num"><?= $r['year'] ? (int) $r['year'] : '' ?></td>
          <td><span class="pill pill--<?= $c ?>"><?= e($l) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$dated): ?><tr><td colspan="5" class="muted" style="padding:20px;text-align:center">Aucun moment daté pour l’instant. Ouvrez un moment « À relire » ci-dessous, ou écrivez-en un.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" id="a-dater">
  <div class="card__head"><h2 class="card__t">Moments à dater</h2><span class="card__note">à relire, puis valider avec une date dans la fiche</span></div>
  <div class="table" style="border:0">
    <table>
      <thead><tr><th>Moment</th><th>Année</th><th>Événement</th><th>Date anniversaire proposée</th><th>État</th></tr></thead>
      <tbody>
      <?php foreach ($undated as $r): [$l, $c] = $state($r); $sg = $r['suggest']; ?>
        <tr>
          <td><a class="rowlink" href="/admin/fiche/<?= (int) $r['id'] ?>"><?= e($r['title'] ?: 'Sans titre') ?></a><?= $aiBadge($r) ?></td>
          <td class="t-num"><?= $r['year'] ? (int) $r['year'] : '' ?></td>
          <td class="nowrap small"><?= $r['event'] ? e(date_num($r['event'])) : '<span class="muted">—</span>' ?></td>
          <td class="small"><?= $sg ? e(date_fr($sg['date'])) . ' <span class="xs muted">(' . (int) $sg['years'] . ' ans' . ($sg['taken'] ? ', jour déjà pris' : '') . ')</span>' : '<span class="muted">—</span>' ?></td>
          <td><span class="pill pill--<?= $c ?>"><?= e($l) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$undated): ?><tr><td colspan="5" class="muted" style="padding:20px;text-align:center">Rien à dater. Les premiers jets rédigés depuis la boîte à idées arrivent ici, « À relire ».</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
