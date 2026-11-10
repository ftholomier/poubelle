<?php
$leads = Leads::all();
$unread = array_filter($leads, fn($l) => $l['status'] === 'nouveau');
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = 0;
}
foreach ($leads as $l) {
    $d = substr((string) $l['at'], 0, 10);
    if (isset($days[$d])) {
        $days[$d]++;
    }
}
$max = max(1, ...array_values($days));
$month = count(array_filter($leads, fn($l) => strtotime($l['at']) > strtotime('-30 days')));
$posts = Content::posts(true);
$botlog = Store::get('botlog', []);
$checks = [
    ['Adresse de réception des demandes', Settings::get('notify_email') !== '', '/admin/emails'],
    ['Assistant IA branché (clé Gemini)', Settings::hasSecret('gemini_key'), '/admin/assistant'],
    ['Fiche Google connectée (avis Google)', GoogleReviews::connected(), '/admin/avis'],
    ['Page Facebook synchronisée', Facebook::configured(), '/admin/facebook'],
    ['Tâche planifiée (synchronisation, vérification des mises à jour)', !empty(Store::get('fb_state', [])['at']) || !empty(Updater::state()['check']), '/admin/sauvegarde'],
];
?>
<div class="kpis">
  <a class="kpi" href="/admin/demandes"><b><?= count($unread) ?></b><span>demande(s) non traitée(s)</span></a>
  <div class="kpi"><b><?= $month ?></b><span>demandes sur 30 jours</span></div>
  <a class="kpi" href="/admin/actus"><b><?= count($posts) ?></b><span>actualités</span></a>
  <a class="kpi" href="/admin/assistant/conversations"><b><?= count($botlog) ?></b><span>conversations avec le bot</span></a>
</div>

<div class="cols">
  <div class="card">
    <h2>Demandes des 14 derniers jours</h2>
    <div class="chart" role="img" aria-label="Nombre de demandes par jour">
      <?php foreach ($days as $d => $n): ?>
        <div class="chart__c" title="<?= e(date('d/m', strtotime($d))) ?> : <?= $n ?>"><i style="height:<?= round($n / $max * 100) ?>%"></i><span><?= e(date('d', strtotime($d))) ?></span></div>
      <?php endforeach; ?>
    </div>
    <h2 style="margin-top:26px">Dernières demandes</h2>
    <?php if (!$leads): ?><p class="bo-muted">Aucune demande pour le moment.</p><?php endif; ?>
    <table class="tbl">
      <?php foreach (array_slice($leads, 0, 8) as $l): ?>
        <tr class="<?= $l['status'] === 'nouveau' ? 'is-new' : '' ?>">
          <td><?= e(date('d/m H:i', strtotime($l['at']))) ?></td>
          <td><span class="tag tag--<?= e($l['type']) ?>"><?= e(Leads::TYPES[$l['type']] ?? $l['type']) ?></span></td>
          <td><a href="/admin/demandes?id=<?= e($l['id']) ?>"><b><?= e($l['nom'] ?: '—') ?></b></a></td>
          <td><?= e($l['tel'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div>
    <a class="card guide-card" href="/admin/guide" data-guide-card data-user="<?= e((string) (Auth::user()['id'] ?? '')) ?>">
      <span class="guide-card__i" aria-hidden="true">?</span>
      <span class="guide-card__t"><b>Guide et formation</b>Tout pour bien utiliser le back-office, pas à pas, avec des captures d'écran.<small data-guide-progress></small></span>
      <span class="guide-card__go" aria-hidden="true">→</span>
    </a>
    <div class="card">
      <h2>Raccourcis</h2>
      <div class="quick">
        <a class="b b--y" href="/admin/actus/nouveau">+ Nouvelle actualité</a>
        <a class="b b--l" href="/admin/accueil">Page d'accueil</a>
        <a class="b b--l" href="/admin/produits">Produits</a>
        <a class="b b--l" href="/admin/realisations">Réalisations</a>
        <a class="b b--l" href="/admin/medias">Médiathèque</a>
      </div>
    </div>
    <?php if (Auth::isAdmin()): ?>
    <div class="card">
      <h2>Mise en service</h2>
      <ul class="checklist">
        <?php foreach ($checks as [$label, $ok, $url]): ?>
          <li class="<?= $ok ? 'ok' : '' ?>"><span><?= $ok ? '✓' : '•' ?></span><a href="<?= e($url) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if (Auth::isAdmin() && ($c = Updater::available())): ?>
      <div class="card card--y">
        <h2>Mise à jour disponible</h2>
        <p><?= e($c['latest']['message'] ?? '') ?></p>
        <a class="b b--k" href="/admin/mises-a-jour">Voir et installer</a>
      </div>
    <?php endif; ?>
  </div>
</div>
