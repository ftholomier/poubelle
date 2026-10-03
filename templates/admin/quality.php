<?php
/**
 * Qualité. Variables : $all, $cat, $sev, $fresh (nouvelles anomalies seulement, tous onglets), $newCounts,
 * $items, $total, $page, $pages, $last (dernier contrôle complet), $state (base de comparaison), $proof (orthographe : avancement)
 */
use App\Admin\Quality;
use App\Services\Controle;

$labels = Quality::TABS;
$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$newTotal = array_sum($newCounts);
$link = fn (array $p) => '/admin/qualite' . (($p = array_filter($p, fn ($v) => $v !== '' && $v !== null)) ? '?' . http_build_query($p) : '');
$here = $fresh ? ['nouveau' => 1] : ['cat' => $cat];
$when = function (string $iso): string {
    $ts = strtotime($iso);
    return (date('Y-m-d', $ts) === date('Y-m-d') ? 'aujourd’hui' : 'le ' . date_fr(date('Y-m-d', $ts))) . ' à ' . date('G \h i', $ts);
};
?>
<div class="card card--pad ctrl">
  <div class="ctrl__head">
    <div class="ctrl__text">
      <h2 class="card__t">Contrôle complet</h2>
      <?php if ($last): ?>
        <p>Dernier contrôle <?= e($when($last['at'])) ?> par <?= e($last['by']) ?> (<?= e(number_format(($last['ms'] ?? 0) / 1000, 1, ',', ' ')) ?> s) : <?= e(Controle::counts($last)) ?>.</p>
      <?php elseif ($state['since']): ?>
        <p>Les anomalies apparues depuis <?= e(Controle::sinceLabel($state['since'])) ?> sont marquées « Nouveau ». Lancez un contrôle pour tout revérifier et repartir de ce point.</p>
      <?php else: ?>
        <p>Aucun contrôle lancé pour l’instant : le premier sert de point de départ, les suivants signalent les nouvelles anomalies.</p>
      <?php endif; ?>
      <p class="small muted">Le contrôle refait toutes les vérifications sur toutes les fiches (scores, dates, compositions, adresses, images, redirections, traductions…) et signale ce qui est apparu depuis le contrôle précédent. Entre deux contrôles, les alertes se mettent à jour à chaque modification.</p>
    </div>
    <form method="post" action="/admin/qualite/controler" data-busy="Contrôle en cours…"><?= csrf_field() ?><button type="submit" class="btn btn--navy">Contrôler maintenant</button></form>
  </div>
  <?php if ($newTotal || $fresh): ?>
    <div class="row" style="gap:8px;flex-wrap:wrap;align-items:center">
      <a class="chip<?= $fresh ? ' is-on' : '' ?>" href="/admin/qualite?nouveau=1">Nouvelles anomalies <em><?= $fmt($newTotal) ?></em></a>
      <?php if ($fresh): ?><a class="linkbtn" href="/admin/qualite">Toutes les alertes →</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (count($last['history'] ?? []) > 1): ?>
    <details class="small">
      <summary>Contrôles précédents</summary>
      <ul class="ctrl__hist">
        <?php foreach (array_slice($last['history'], 1) as $h): ?>
          <li><?= e(ucfirst($when($h['at']))) ?> par <?= e($h['by']) ?> : <?= $fmt($h['total']) ?> anomalie<?= $h['total'] > 1 ? 's' : '' ?>, <?= $fmt($h['new']) ?> nouvelle<?= $h['new'] > 1 ? 's' : '' ?>, <?= $fmt($h['fixed']) ?> corrigée<?= $h['fixed'] > 1 ? 's' : '' ?></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>
</div>
<div class="kpis">
  <?php foreach ($labels as $k => [$l, $d]): ?>
    <a class="kpi<?= !$fresh && $cat === $k ? ' is-on kpi--yellow' : '' ?>" href="/admin/qualite?cat=<?= e($k) ?>"><b><?= $fmt(count($all[$k])) ?></b><span><?= e($l) ?></span><small><?php if ($newCounts[$k]): ?><span class="pill pill--ko"><?= $fmt($newCounts[$k]) ?> nouvelle<?= $newCounts[$k] > 1 ? 's' : '' ?></span> <?php endif; ?><?= e($d) ?></small></a>
  <?php endforeach; ?>
</div>
<div class="toolbar">
  <div class="chips">
    <?php foreach (['' => 'Toutes', 'haute' => 'Hautes', 'moyenne' => 'Moyennes', 'basse' => 'Basses'] as $k => $l): ?><a class="chip<?= $sev === $k ? ' is-on' : '' ?>" href="<?= e($link($here + ['niveau' => $k])) ?>"><?= e($l) ?></a><?php endforeach; ?>
  </div>
  <span class="small muted"><?= $fresh ? 'Nouvelles anomalies (tous onglets) : ' : '' ?><?= $fmt($total) ?> alerte<?= $total > 1 ? 's' : '' ?><?= ($pages ?? 1) > 1 ? ' (page ' . (int) $page . ' sur ' . (int) $pages . ', les plus graves d’abord)' : '' ?> · recalculées automatiquement à chaque modification</span>
</div>
<?php if (!$fresh && $cat === 'orthographe' && $proof): ?>
  <p class="small muted proofinfo">Le correcteur vérifie en tâche de fond chaque fiche nouvelle ou modifiée : <b><?= number_format($proof['checked'], 0, ',', ' ') ?> / <?= number_format($proof['total'], 0, ',', ' ') ?></b> fiches vérifiées<?= \App\Services\Gemini::ready() ? ', dont ' . number_format($proof['ai'], 0, ',', ' ') . ' avec Gemini (orthographe, accords, syntaxe)' : ' avec les règles de base (clé Gemini non réglée : pas de vérification des accords ni de la syntaxe)' ?>. « Corriger » ouvre la fiche avec le correcteur : vous acceptez ou ignorez chaque correction, puis enregistrez. Les noms propres se protègent dans le <a href="/admin/collection/dictionnaire">dictionnaire du musée</a> (<?= count(\App\Services\Proofreader::dictionary()) ?> mot<?= count(\App\Services\Proofreader::dictionary()) > 1 ? 's' : '' ?>).</p>
<?php endif; ?>
<?php if (!$fresh && $cat === 'completer'): ?>
  <p class="small muted">Les « xx » viennent de l’ancien site (information inconnue au moment de la saisie) : ils sont cachés sur le site public. Complétez l’information si vous la connaissez, ou retirez le « xx ».</p>
<?php endif; ?>
<?php if (!$fresh && $cat === 'liens'): ?>
  <p class="small muted">Un nom mal orthographié dans les compositions se relie à une fiche existante en l’ajoutant dans « Autres graphies dans les compositions » (fiche du joueur, onglet Identité). Les rapprochements automatiques (autre graphie, faute de frappe, nom incomplet) sont listés pour vérification, comme les fiches de personnes portant le même nom (doublon ou homonymes).</p>
<?php endif; ?>
<?php if (!$fresh && $cat === 'site'): ?>
  <p class="small muted">Fiches à la même adresse ou à l’adresse mal formée, rubriques et images supprimées, fichiers abîmés (souvent après un envoi par FTP). Les redirections se corrigent dans Éditorial › Redirections, les adversaires et les stades dans Référentiels.</p>
<?php endif; ?>
<div class="card">
  <?php foreach ($items as $i): ?>
    <div class="card__row qrow">
      <span><span class="sev sev--<?= e($i['sev']) ?>"><?= e(ucfirst($i['sev'])) ?></span></span>
      <span><?php if ($i['new']): ?><span class="pill pill--ko">Nouveau</span> <?php endif; ?><?php if ($fresh): ?><span class="pill"><?= e($labels[$i['tab']][0]) ?></span> <?php endif; ?><?= e($i['msg']) ?></span>
      <span class="muted ellipsis qrow__t"><?= e($i['title']) ?></span>
      <?php if ($i['url']): ?>
        <a class="btn btn--sm" href="<?= e($i['url']) ?>"><?= $i['tab'] === 'liens' && $i['code'] !== 'homonyme' ? (str_contains($i['url'], '/nouvelle/') ? 'Créer la fiche' : 'Vérifier') : 'Corriger' ?></a>
      <?php else: ?>
        <span class="small muted">Sauvegarde</span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$items): ?><div class="empty" style="border:0"><?= $fresh ? 'Aucune nouvelle anomalie · bravo !' : 'Aucune alerte · bravo !' ?></div><?php endif; ?>
</div>
<?php if (($pages ?? 1) > 1): ?>
  <nav class="row" style="gap:8px;flex-wrap:wrap" aria-label="Pages des alertes">
    <?php for ($n = 1; $n <= $pages; $n++): ?>
      <a class="chip<?= $n === $page ? ' is-on' : '' ?>" href="<?= e($link($here + ['niveau' => $sev, 'page' => $n])) ?>"<?= $n === $page ? ' aria-current="page"' : '' ?>>Page <?= $n ?></a>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
