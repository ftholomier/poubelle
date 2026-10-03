<?php
/**
 * Journal de l'assistant IA. Variables : $rows, $total, $months, $month, $q, $filter, $stats, $titles, $enabled, $ready, $model, $embed, $logging, $retention, $index
 */
use App\Admin\Base;
use App\Core\Auth;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$monthLabel = fn ($m) => $m ? $mois[(int) substr($m, 5, 2) - 1] . ' ' . substr($m, 0, 4) : '';
?>
<div class="kpis">
  <div class="kpi<?= $enabled ? ' kpi--yellow' : '' ?>"><b><?= $enabled ? 'En ligne' : 'Inactif' ?></b><span>assistant du site</span><small><?= $ready ? 'modèle : ' . e((string) $model) : 'clé Gemini manquante' ?></small></div>
  <div class="kpi"><b><?= $fmt($stats['questions']) ?></b><span>questions <?= e($monthLabel($month)) ?></span><small><?= $fmt($stats['en']) ?> en anglais · <?= $fmt($stats['errors']) ?> erreur(s)</small></div>
  <div class="kpi"><b><?= $fmt($stats['up']) ?> / <?= $fmt($stats['down']) ?></b><span>avis 👍 / 👎</span><small>donnés par les visiteurs</small></div>
  <div class="kpi"><b><?= $fmt($stats['tokens']) ?></b><span>jetons consommés</span><small>réponse en <?= number_format($stats['ms'] / 1000, 1, ',', ' ') ?> s en moyenne</small></div>
  <div class="kpi"><b><?= $embed ? 'Sémantique' : 'Plein texte' ?></b><span>recherche dans le musée</span><small><?= $index ? 'index du ' . e(Base::ago(date('c', (int) $index['at']))) : 'index non construit' ?></small></div>
</div>
<p class="small muted" style="margin:0"><?= $logging ? 'Les questions sont conservées ' . (int) $retention . ' jours (RGPD) : texte de la question et de la réponse, langue, durée ; jamais l’adresse IP en clair.' : 'La conservation des questions est désactivée : seules des mesures techniques sont gardées.' ?><?= Auth::isAdmin() ? ' <a href="/admin/reglages?groupe=ai">Réglages de l’assistant</a>' : '' ?></p>

<div class="toolbar">
  <form class="toolbar" method="get" action="/admin/assistant">
    <select name="mois" aria-label="Mois" data-autosubmit><?php foreach ($months as $m): ?><option value="<?= e($m) ?>"<?= $m === $month ? ' selected' : '' ?>><?= e($monthLabel($m)) ?></option><?php endforeach; ?><?php if (!$months): ?><option>Aucune question</option><?php endif; ?></select>
    <div class="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher dans les questions…" aria-label="Rechercher"><button type="submit">→</button></div>
    <select name="filtre" aria-label="Filtre" data-autosubmit><?php foreach (['' => 'Toutes', 'negatifs' => 'Avis négatifs', 'positifs' => 'Avis positifs', 'erreurs' => 'Erreurs'] as $k => $l): ?><option value="<?= $k ?>"<?= $filter === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  </form>
  <span class="grow"></span>
  <a class="btn" href="/admin/assistant/export.csv<?= $month ? '?mois=' . e($month) : '' ?>">Export CSV</a>
  <form method="post" action="/admin/assistant"><?= csrf_field() ?><button type="submit" name="action" value="reindexer" class="btn">Mettre à jour l’index</button></form>
  <?php if (Auth::can('destroy') && $month): ?><form method="post" action="/admin/assistant" data-confirm="Effacer le journal de <?= e($monthLabel($month)) ?> ?|Les questions de ce mois seront supprimées définitivement.|Effacer|danger"><?= csrf_field() ?><input type="hidden" name="mois" value="<?= e($month) ?>"><button type="submit" name="action" value="purger" class="btn btn--danger">Effacer ce mois</button></form><?php endif; ?>
</div>

<div class="stack" style="gap:10px">
  <?php foreach ($rows as $r): ?>
    <details class="card">
      <summary class="card__row" style="grid-template-columns:110px minmax(0,1fr) auto;cursor:pointer;list-style:none">
        <span class="xs muted"><?= e(date('d/m H:i', strtotime((string) $r['at']))) ?> · <?= e(strtoupper($r['lang'] ?? 'fr')) ?></span>
        <b style="font-weight:600"><?= e((string) ($r['q'] ?? '(question non conservée)')) ?></b>
        <span><?= empty($r['ok']) ? '<span class="pill pill--ko">Erreur</span>' : '' ?><?= ($r['fb'] ?? 0) > 0 ? '👍' : (($r['fb'] ?? 0) < 0 ? '👎' : '') ?></span>
      </summary>
      <div class="card__body">
        <?php if (!empty($r['a'])): ?><div style="background:var(--cream);border:2px solid var(--navy);padding:12px;font-size:15px;white-space:pre-wrap"><?= e((string) $r['a']) ?></div><?php endif; ?>
        <?php if (!empty($r['err'])): ?><p class="ko small" style="margin:0"><?= e((string) $r['err']) ?></p><?php endif; ?>
        <?php if (!empty($r['src'])): ?><p class="small" style="margin:0">Fiches citées : <?php foreach ($r['src'] as $id): ?><?php if (is_numeric($id) && !empty($titles[(int) $id])): ?><a href="/admin/fiche/<?= (int) $id ?>"><?= e($titles[(int) $id]) ?></a> · <?php else: ?><span class="muted"><?= e((string) $id) ?></span> · <?php endif; ?><?php endforeach; ?></p><?php endif; ?>
        <span class="xs muted"><?= e(implode(' · ', array_filter([!empty($r['page']) ? 'depuis ' . $r['page'] : '', $r['model'] ?? '', isset($r['ms']) ? number_format($r['ms'] / 1000, 1, ',', ' ') . ' s' : '', !empty($r['cached']) ? 'réponse en cache' : '', isset($r['turn']) && $r['turn'] > 1 ? 'échange n° ' . $r['turn'] : '']))) ?></span>
      </div>
    </details>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="empty">Aucune question</div><?php endif; ?>
  <?php if ($total > count($rows)): ?><p class="small muted" style="margin:0"><?= $fmt($total - count($rows)) ?> autres questions : affinez la recherche ou exportez le CSV.</p><?php endif; ?>
</div>
