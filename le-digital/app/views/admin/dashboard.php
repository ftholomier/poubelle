<?php
/** @var array $checks @var array $latest @var array $upcoming @var array $aiToday @var int $aiWeek @var array $counts @var ?string $cron */
use LD\Leads;

$done = count(array_filter($checks, static fn($c) => $c[1]));
$update = LD\Updater::available();
$lastMail = LD\Mailer::recent(1)[0] ?? null;
$mailIssue = LD\Mailer::notifyAddress() === '' ? 'aucune adresse ne reçoit les demandes.' : ($lastMail && empty($lastMail['ok']) ? 'le dernier e-mail n’est pas parti (' . ($lastMail['error'] ?? 'erreur') . ').' : '');
?>
<?php if ($mailIssue !== ''): ?>
  <a class="maj-banner maj-banner--err" href="/admin/parametres#emails"><span class="status status--new">E-mails</span> <b>Attention : <?= e($mailIssue) ?></b> <span>Voir le diagnostic →</span></a>
<?php endif; ?>
<?php if ($update): ?>
  <a class="maj-banner" href="/admin/mises-a-jour"><span class="status status--new">Mise à jour disponible</span> <b><?= e($update['latest']['message'] ?? 'Nouvelle version du site') ?></b> <span>Installer en un clic →</span></a>
<?php endif; ?>
<div class="kpis">
  <a class="kpi kpi--red" href="/admin/demandes?statut=new"><b><?= (int) $counts['new'] ?></b><span>nouvelle<?= $counts['new'] > 1 ? 's' : '' ?> demande<?= $counts['new'] > 1 ? 's' : '' ?></span></a>
  <a class="kpi" href="/admin/rendez-vous"><b><?= count($upcoming) ?></b><span>RdV à venir</span></a>
  <a class="kpi" href="/admin/demandes?type=vocal"><b><?= (int) ($counts['types']['vocal'] ?? 0) ?></b><span>vocaux reçus</span></a>
  <a class="kpi" href="/admin/ia/couts"><b><?= e(number_format(LD\AiCosts::monthUsd() * LD\AiCosts::rate(), 2, ',', ' ')) ?> €</b><span>coût IA ce mois · <?= (int) $aiToday['calls'] ?> appels aujourd’hui</span></a>
</div>

<div class="grid2">
  <section class="card">
    <div class="card__head"><h2>Dernières demandes</h2><a href="/admin/demandes">Tout voir →</a></div>
    <?php if (!$latest): ?>
      <p class="muted">Pas encore de demande. Elles arriveront ici (formulaire, RdV, rappels, vocaux, briefs de l’assistant), et par e-mail.</p>
    <?php else: ?>
      <ul class="list">
        <?php foreach ($latest as $l): ?>
          <li><a href="/admin/demandes/<?= e($l['id']) ?>">
            <span class="pill pill--<?= e($l['type']) ?>"><?= e(Leads::TYPES[$l['type']] ?? $l['type']) ?></span>
            <span class="list__main"><b><?= e($l['name'] ?: ($l['phone'] ?: 'Anonyme')) ?></b><small><?= e(date('d/m H:i', strtotime($l['created_at']))) ?></small></span>
            <?php if ($l['status'] === 'new'): ?><span class="dot" title="Nouveau"></span><?php endif; ?>
          </a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card__head"><h2>Prochains rendez-vous</h2><a href="/admin/rendez-vous">Agenda →</a></div>
    <?php if (!$upcoming): ?>
      <p class="muted">Aucun rendez-vous réservé pour le moment.</p>
    <?php else: ?>
      <ul class="list">
        <?php foreach ($upcoming as $b): ?>
          <li><div class="list__row"><span class="list__date"><b><?= e(date('d/m', strtotime($b['date']))) ?></b><small><?= e($b['time']) ?></small></span><span class="list__main"><b><?= e($b['name']) ?></b><small><?= e($b['mode']) ?> · <?= e($b['phone'] ?: $b['email']) ?></small></span></div></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <div class="card__head"><h2>Checklist de mise en ligne</h2><span class="muted"><?= $done ?>/<?= count($checks) ?></span></div>
  <div class="progress"><div style="width:<?= round($done / max(1, count($checks)) * 100) ?>%"></div></div>
  <ul class="checks">
    <?php foreach ($checks as [$label, $ok, $href]): ?>
      <li class="<?= $ok ? 'is-ok' : '' ?>"><span class="checks__ic"><?= $ok ? '✓' : '' ?></span><a href="<?= e($href) ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <p class="muted small">Tant qu’un élément manque, le site affiche l’emplacement réservé de la maquette (encadré pointillé « à fournir »).</p>
</section>

<section class="card">
  <div class="card__head"><h2>Raccourcis</h2></div>
  <div class="shortcuts">
    <a href="/admin/contenus/home">Modifier l’accueil</a>
    <a href="/admin/blog/nouveau">Écrire un article</a>
    <a href="/admin/avis/nouveau">Ajouter un avis</a>
    <a href="/admin/medias">Envoyer des photos</a>
    <a href="/admin/ia">Clés API & modèles IA</a>
    <a href="/admin/sauvegarde">Télécharger une sauvegarde</a>
  </div>
  <p class="muted small">Tâche planifiée (rappels de RdV) : <?= $cron ? 'dernier passage le ' . e(date('d/m/Y à H:i', strtotime($cron))) : 'jamais lancée — voir Paramètres › Tâches planifiées' ?>.</p>
</section>
