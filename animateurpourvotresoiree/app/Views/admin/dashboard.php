<?php
use App\Core\Url;
use App\Services\Chart;
use App\Services\Leads;
use App\Services\Pros;

/** @var array $pros @var array $donut @var array $req @var array $msg @var array $traffic @var array $leads @var array $health */
$trend = static function (int $now, int $prev): string {
    if ($prev <= 0) {
        return '';
    }
    $d = (int) round(($now - $prev) / $prev * 100);
    return '<em class="' . ($d >= 0 ? 'up' : 'down') . '">' . ($d >= 0 ? '+' : '') . $d . ' %</em>';
};
$hour = (int) date('G');
$hello = $hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir');
$me = App\Core\Auth::admin();
?>
<div class="adm-head">
  <div><h1><?= e($hello) ?> <?= e(explode(' ', (string) (($me['name'] ?? '') ?: 'à vous'))[0]) ?> <span class="serif">👋</span></h1>
    <p><?= e(date_fr(date('c'), 'long')) ?> · voici l'activité du site.</p></div>
  <div class="row-wrap">
    <a class="btn btn-sm" href="<?= e(Url::admin('pros/nouveau')) ?>"><?= icon('plus', 16) ?> Ajouter un pro</a>
    <a class="btn btn-sm btn-ink" href="<?= e(Url::admin('emailing/nouvelle')) ?>"><?= icon('megaphone', 16) ?> Nouvelle campagne</a>
  </div>
</div>

<div class="kpis">
  <a class="kpi<?= $pros['pending'] ? ' warn' : '' ?>" href="<?= e(Url::admin('pros?statut=pending')) ?>"><span>Pros à valider</span><b><?= nf($pros['pending']) ?></b><?= icon('users', 20) ?></a>
  <a class="kpi<?= count($req['pending']) ? ' warn' : '' ?>" href="<?= e(Url::admin('demandes?statut=pending')) ?>"><span>Devis à modérer</span><b><?= nf(count($req['pending'])) ?><?= count($req['pending']) >= 8 ? '+' : '' ?></b><?= icon('inbox', 20) ?></a>
  <a class="kpi<?= $reviewsPending ? ' warn' : '' ?>" href="<?= e(Url::admin('avis?statut=pending')) ?>"><span>Avis à modérer</span><b><?= nf($reviewsPending) ?></b><?= icon('star', 20) ?></a>
  <a class="kpi good" href="<?= e(Url::admin('pros?statut=active')) ?>"><span>Pros en ligne</span><b><?= nf($pros['active']) ?></b><span class="small">+<?= nf($pros['new30']) ?> inscrits en 30 j</span></a>
  <div class="kpi"><span>Demandes de devis · 30 j</span><b><?= nf($req['now']) ?></b><?= $trend($req['now'], $req['prev']) ?><?= Chart::spark($leads['Demandes de devis']) ?></div>
  <div class="kpi"><span>Messages aux pros · 30 j</span><b><?= nf($msg['now']) ?></b><?= $trend($msg['now'], $msg['prev']) ?><?= Chart::spark($leads['Messages aux pros'], '#8f7bff') ?></div>
  <div class="kpi"><span>Visiteurs · 30 j</span><b><?= nf($uniq30) ?></b><?= $trend($uniq30, $uniqPrev) ?><?= Chart::spark($traffic['Visiteurs uniques'], '#5fd3ff') ?></div>
  <div class="kpi"><span>Pages vues · 30 j</span><b><?= nf($pv30) ?></b><?= Chart::spark($traffic['Pages vues'], '#1c1233') ?></div>
</div>

<div class="adm-grid mt-3">
  <div class="box"><h2>Audience (30 jours)</h2><?= Chart::lines($traffic, ['height' => 220, 'area' => true]) ?></div>
  <div class="box"><h2>Contacts générés (30 jours)</h2><?= Chart::lines($leads, ['height' => 220, 'colors' => ['#ff4f3a', '#8f7bff']]) ?></div>
</div>

<div class="adm-cols mt-3">
  <div>
    <div class="box">
      <div class="box-head"><h2>Demandes de devis à modérer</h2><a class="link small" href="<?= e(Url::admin('demandes?statut=pending')) ?>">Tout voir</a></div>
      <?php if (!$req['pending']): ?><p class="muted">Rien à modérer 🎉</p><?php else: ?>
        <ul class="list-rows">
          <?php foreach ($req['pending'] as $r): ?>
            <li><span><a class="t-main" href="<?= e(Url::admin('demandes/' . $r['id'])) ?>"><?= e(($r['name'] ?: $r['email']) . ' — ' . ($r['city'] ?: '?')) ?></a><br><span class="muted"><?= e(Leads::EVENT_TYPES[$r['type']] ?? '') ?> · <?= e(ago($r['created'])) ?> · <?= e(App\Core\Str::limit($r['excerpt'], 90)) ?></span></span>
              <span class="score <?= $r['score'] >= 70 ? 'hi' : ($r['score'] >= 30 ? 'mid' : 'lo') ?>" title="Score anti-spam"><?= (int) $r['score'] ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="box">
      <div class="box-head"><h2>Dernières inscriptions</h2><a class="link small" href="<?= e(Url::admin('pros?tri=recent')) ?>">Tout voir</a></div>
      <?php if (!$latestPros): ?><p class="muted">Aucune inscription récente.</p><?php else: ?>
        <ul class="list-rows">
          <?php foreach ($latestPros as $p): ?>
            <li><span><a class="t-main" href="<?= e(Url::admin('pros/' . $p['id'])) ?>"><?= e($p['name']) ?></a><br><span class="muted"><?= e($p['city']) ?> · <?= e(implode(', ', array_map([App\Services\Categories::class, 'name'], array_slice($p['cats'], 0, 2)))) ?> · <?= e(ago($p['created'])) ?></span></span>
              <span class="status-pill st-<?= e($p['status']) ?>"><?= e(Pros::STATUSES[$p['status']] ?? $p['status']) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="box">
      <h2>Historique des demandes de devis (depuis l'ancien site)</h2>
      <?php $months = array_slice($monthly['requests'], -36, null, true); ?>
      <?= $months ? Chart::bars($months, ['height' => 200, 'label_every' => 6, 'color' => '#ffd23f']) : '<p class="muted">Pas encore de données.</p>' ?>
      <p class="small muted">Total historique : <?= nf(array_sum($monthly['requests'])) ?> demandes depuis <?= e((string) array_key_first($monthly['requests'] ?: [date('Y-m') => 0])) ?>.</p>
    </div>
  </div>
  <aside>
    <div class="box">
      <h2>État du site</h2>
      <div class="health">
        <?php foreach ($health as [$label, $state, $info]): ?><div class="<?= e($state) ?>"><i></i><span><strong><?= e($label) ?></strong><br><span class="muted small"><?= e($info) ?></span></span></div><?php endforeach; ?>
      </div>
    </div>
    <div class="box">
      <h2>Pros en ligne par métier</h2>
      <?= Chart::donut($donut, ['unit' => 'pros']) ?>
    </div>
    <div class="box">
      <div class="box-head"><h2>Dernières alertes</h2><a class="link small" href="<?= e(Url::admin('notifications')) ?>">Tout voir</a></div>
      <ul class="timeline">
        <?php foreach ($notifications as $n): ?><li><a href="<?= e($n['link'] ?: Url::admin('notifications')) ?>"><?= e($n['title']) ?></a><small><?= e(ago($n['created'])) ?></small></li><?php endforeach; ?>
        <?php if (!$notifications): ?><li class="muted">Aucune alerte.</li><?php endif; ?>
      </ul>
    </div>
    <div class="box">
      <h2>Recherches des visiteurs (ce mois)</h2>
      <?php if (!$searches): ?><p class="muted small">Pas encore de recherches enregistrées.</p><?php else: ?>
        <ul class="list-rows"><?php foreach ($searches as $q => $n): ?><li><span><?= e((string) $q) ?></span><b><?= (int) $n ?></b></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </aside>
</div>
