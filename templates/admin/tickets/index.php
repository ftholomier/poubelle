<?php
/** Équipe › Tickets : liste, filtres et nouveau ticket. Variables : $list, $counts, $f, $authors, $me, $total, $admin, $page */
use App\Services\Tickets;
$open = array_sum(array_intersect_key($counts, array_flip(Tickets::OPEN)));
$chip = fn (string $k, string $label, int $n = -1) => '<a class="chip' . ($f['etat'] === $k ? ' is-on' : '') . '" href="?' . e(http_build_query(['etat' => $k] + array_filter(['type' => $f['type'], 'qui' => $f['qui']]))) . '">' . e($label) . ($n >= 0 ? ' <b>' . $n . '</b>' : '') . '</a>';
include __DIR__ . '/_style.php';
?>
<div class="kpis">
  <div class="kpi"><b><?= $counts['nouveau'] ?></b><span>nouveaux</span></div>
  <div class="kpi"><b><?= $counts['en-cours'] + $counts['pris'] ?></b><span>pris en compte ou en cours</span></div>
  <div class="kpi"><b><?= $counts['a-verifier'] ?></b><span>à vérifier</span><small>c’est en ligne : l’auteur contrôle</small></div>
  <div class="kpi"><b><?= $counts['termine'] ?></b><span>terminés</span></div>
</div>

<div class="card card--pad" style="margin-top:16px">
  <div class="card__head"><h2 class="card__t">Les tickets de l’équipe</h2><a class="btn btn--yellow" href="#nouveau">+ Nouveau ticket</a></div>
  <div class="chips" style="margin:6px 0 10px">
    <?= $chip('ouverts', 'Ouverts', $open) ?><?= $chip('miens', 'Mes tickets') ?>
    <?php foreach (Tickets::STATES as $k => $label): ?><?= $chip($k, $label, $counts[$k]) ?><?php endforeach; ?>
    <?= $chip('tous', 'Tous', $total) ?>
  </div>
  <form method="get" class="row" style="gap:8px;margin-bottom:8px">
    <input type="hidden" name="etat" value="<?= e($f['etat']) ?>">
    <select name="type"><option value="">Tous les types</option><?php foreach (Tickets::TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <select name="qui"><option value="">Tout le monde</option><?php foreach ($authors as $a): ?><option<?= $f['qui'] === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select>
    <input type="search" name="q" class="in" value="<?= e($f['q']) ?>" placeholder="Chercher dans les tickets" style="max-width:240px">
    <button class="btn btn--sm" type="submit">Filtrer</button>
  </form>
  <?php if ($list): ?>
  <ul class="tk-list">
    <?php foreach ($list as $t): $new = Tickets::unread($t, $me); ?>
    <li class="<?= $new ? 'is-new' : '' ?>">
      <span class="tk-n">n° <?= (int) $t['id'] ?></span>
      <div>
        <div class="tk-t"><?php if ($new): ?><span class="tk-dot" title="Nouveau ou changé depuis votre dernière visite"></span><?php endif; ?><a href="/admin/tickets/<?= (int) $t['id'] ?>"><?= e($t['title']) ?></a></div>
        <div class="tk-meta"><?= e(Tickets::TYPES[$t['type']]) ?> · <?= e($t['author']['name'] ?? '') ?> · ouvert <?= e(\App\Admin\Base::ago($t['created_at'])) ?><?php if ($t['updated_at'] !== $t['created_at']): ?> · mis à jour <?= e(\App\Admin\Base::ago($t['updated_at'])) ?><?php endif; ?><?= $t['comments'] ? ' · 💬 ' . count($t['comments']) : '' ?><?= $t['prompt'] !== '' ? ' · prompt IA' : '' ?><?php if ($t['priority'] !== 'normale'): ?> · <span class="tk-prio--<?= e($t['priority']) ?>"><?= e(Tickets::PRIORITIES[$t['priority']]) ?></span><?php endif; ?></div>
      </div>
      <span class="tk-state tk-state--<?= e($t['status']) ?>"><?= e(Tickets::STATES[$t['status']]) ?></span>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?><p class="empty">Aucun ticket ici.</p><?php endif; ?>
</div>

<div class="card card--pad" id="nouveau" style="margin-top:16px">
  <h2 class="card__t">Nouveau ticket</h2>
  <p class="card__note">Un bug, une idée d’amélioration, une question : décrivez-la ici. <?= $admin ? 'Vous' : 'L’administrateur' ?> en <?= $admin ? 'êtes' : 'est' ?> prévenu par e-mail, et vous recevez un e-mail à chaque réponse ou changement de statut.</p>
  <form method="post" action="/admin/tickets"><?= csrf_field() ?>
    <?php $t = []; include __DIR__ . '/_form.php'; ?>
    <button class="btn btn--navy" type="submit">Envoyer le ticket</button>
  </form>
</div>
