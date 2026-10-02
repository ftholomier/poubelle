<?php
use App\Core\Url;
use App\Services\Chart;
use App\Services\Leads;
use App\Services\Pros;

/** @var array $pro @var array $series @var array $last30 @var array $prev30 @var array $requests @var array $messages @var int $score @var array $tips */
$trend = static function (int $now, int $before): string {
    if ($before === 0) {
        return $now > 0 ? '<em class="up">nouveau</em>' : '';
    }
    $d = (int) round(($now - $before) / $before * 100);
    return '<em class="' . ($d >= 0 ? 'up' : 'down') . '">' . ($d >= 0 ? '+' : '') . $d . ' %</em>';
};
$first = $pro['first_name'] ?: Pros::displayName($pro);
?>
<div class="pro-head">
  <div>
    <p class="mono muted small">Espace pro</p>
    <h1 class="h2">Salut <?= e($first) ?> <span class="wave" aria-hidden="true">👋</span></h1>
  </div>
  <div class="row-wrap">
    <a class="btn btn-sm" href="/espace-pro/fiche/"><?= icon('edit', 16) ?> Modifier ma fiche</a>
    <?php if (($pro['status'] ?? '') === 'active'): ?><a class="btn btn-sm btn-ink" href="<?= e(Url::pro($pro)) ?>" target="_blank" rel="noopener"><?= icon('eye', 16) ?> Voir ma fiche</a><?php endif; ?>
  </div>
</div>

<div class="kpis mt-2">
  <div class="kpi"><span>Vues de la fiche · 30 j</span><b><?= nf($last30['pro_view']) ?></b><?= $trend($last30['pro_view'], $prev30['pro_view']) ?><?= Chart::spark(array_column($series, 'pro_view')) ?></div>
  <div class="kpi"><span>Demandes de devis · 30 j</span><b><?= nf($last30['requests']) ?></b></div>
  <div class="kpi"><span>Messages reçus · 30 j</span><b><?= nf($last30['messages']) ?></b></div>
  <div class="kpi"><span>Numéros affichés · 30 j</span><b><?= nf($last30['phone']) ?></b><?= $trend($last30['phone'], $prev30['phone']) ?></div>
  <div class="kpi"><span>Clics vers votre site · 30 j</span><b><?= nf($last30['site']) ?></b><?= $trend($last30['site'], $prev30['site']) ?></div>
</div>

<div class="pro-cols mt-3">
  <div class="stack">
    <div class="box">
      <div class="box-head"><h2>Dernières demandes de devis</h2><a class="link small" href="/espace-pro/demandes/">Tout voir</a></div>
      <?php if (!$requests): ?>
        <p class="muted">Aucune demande pour l'instant. <?php $deps = array_values(array_filter(array_unique(array_merge([(string) ($pro['dep'] ?? '')], array_map('strval', (array) ($pro['zones'] ?? [])))))); ?>Les demandes des clients de votre secteur<?= $deps ? ' (' . e(implode(', ', array_map(static fn ($z) => (App\Services\Geo::dep($z)['name'] ?? $z) . ' ' . $z, array_slice($deps, 0, 6)))) . (count($deps) > 6 ? '…' : '') . ')' : '' ?> arriveront ici et par email.</p>
      <?php else: ?>
        <ul class="inbox">
          <?php foreach ($requests as $r): ?>
            <li><a href="/espace-pro/demandes/<?= (int) $r['id'] ?>/">
              <b><?= e(Leads::EVENT_TYPES[$r['type']] ?? 'Événement') ?><?= $r['city'] ? ' · ' . e($r['city']) : '' ?></b>
              <span class="muted small"><?= $r['date'] ? 'le ' . e(date_fr($r['date'], 'short')) . ' · ' : '' ?>reçue <?= e(ago($r['created'])) ?></span>
              <span class="excerpt"><?= e($r['excerpt']) ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="box">
      <div class="box-head"><h2>Derniers messages</h2><a class="link small" href="/espace-pro/messages/">Tout voir</a></div>
      <?php if (!$messages): ?>
        <p class="muted">Aucun message pour l'instant.</p>
      <?php else: ?>
        <ul class="inbox">
          <?php foreach ($messages as $m): ?>
            <li class="<?= $m['read'] ? '' : 'unread' ?>"><a href="/espace-pro/messages/<?= (int) $m['id'] ?>/">
              <b><?= e($m['name']) ?></b><span class="muted small"><?= e(ago($m['created'])) ?></span>
              <span class="excerpt"><?= e($m['excerpt']) ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="box">
      <div class="box-head"><h2>Vues de votre fiche</h2><a class="link small" href="/espace-pro/statistiques/">Statistiques détaillées</a></div>
      <?= Chart::bars(array_map(static fn ($d) => $d['pro_view'], $series), ['height' => 180, 'title' => 'Vues de la fiche sur 30 jours']) ?>
    </div>
  </div>
  <aside class="stack">
    <div class="box">
      <h2>Complétude de la fiche</h2>
      <div class="gauge" style="--p:<?= (int) $score ?>"><span><?= (int) $score ?> %</span></div>
      <?php if ($tips): ?>
        <ul class="list-tips">
          <?php foreach (array_slice($tips, 0, 4) as $t): ?><li><?= e($t) ?></li><?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="small">Bravo, votre fiche est au top ! 🏆</p>
      <?php endif; ?>
      <a class="btn btn-coral btn-sm btn-block mt-2" href="/espace-pro/fiche/">Compléter ma fiche</a>
    </div>
    <div class="box box-cream">
      <h2>Note moyenne</h2>
      <?php $avg = (float) ($pro['rating']['avg'] ?? 0); $n = (int) ($pro['rating']['count'] ?? 0); ?>
      <p class="rating-line"><b><?= $n ? number_format($avg, 1, ',', '') : '–' ?></b> <span class="c-coral"><?= str_repeat('★', (int) round($avg)) ?></span> <span class="muted small"><?= $n ?> avis</span></p>
      <a class="btn btn-sm btn-block" href="/espace-pro/avis/">Inviter mes clients à donner leur avis</a>
    </div>
    <div class="box">
      <h2>Restez réactif 📲</h2>
      <p class="small">Installez l'application et activez les notifications : vous êtes prévenu à chaque nouvelle demande.</p>
      <div class="row-wrap">
        <button type="button" class="btn btn-sm install-btn" data-install><?= icon('download', 16) ?> Installer l'appli</button>
        <a class="btn btn-sm btn-ink" href="/espace-pro/compte/#notifications"><?= icon('bell', 16) ?> Notifications</a>
      </div>
    </div>
  </aside>
</div>
