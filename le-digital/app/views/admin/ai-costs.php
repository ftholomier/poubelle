<?php
/**
 * Tableau de bord des coûts IA.
 * @var array $periods @var string $period @var string $from @var string $to @var array $report @var float $today @var float $month
 * @var array $set @var array $models @var ?int $catalogDate @var int $catalogCount @var string $csrf
 */
use LD\Ai;
use LD\AiCosts;

$rate = AiCosts::rate();
$eur = static function (float $usd) use ($rate): string {
    $v = $usd * $rate;
    return number_format($v, $v > 0 && $v < 1 ? 4 : 2, ',', ' ') . ' €';
};
$usd = static fn(float $v): string => number_format($v, $v > 0 && $v < 1 ? 4 : 2, ',', ' ') . ' $';
$n = static fn(int $v): string => number_format($v, 0, ',', ' ');
$labels = array_map(static fn($f) => $f['label'], Ai::FEATURES) + [
    'transcription' => 'Transcription des vocaux', 'blog' => 'Rédaction d’articles (texte)', 'blog_image' => 'Images d’articles (Nano Banana)', 'autre' => 'Autres appels',
];
$r = $report;
$days = (int) date('j');
$projection = $month / max(1, $days) * (int) date('t');
$budget = (float) ($set['budget'] ?? 0);
// Graphique : par jour (ou par mois au-delà de 62 jours).
$bars = [];
foreach ($r['days'] as $day => $v) {
    $k = count($r['days']) > 62 ? substr($day, 0, 7) : $day;
    $bars[$k] = ($bars[$k] ?? 0) + $v;
}
$max = max([0.000001, ...array_values($bars)]);
$monthsFr = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$barLabel = static fn(string $k) => strlen($k) === 7 ? $monthsFr[(int) substr($k, 5, 2) - 1] : date('d/m', strtotime($k));
$priceTxt = static function (?array $p) use ($usd): string {
    if (!$p) {
        return 'Tarif inconnu';
    }
    $t = [];
    if ($p['in'] > 0 || $p['out'] > 0) {
        $t[] = rtrim(rtrim(number_format($p['in'], 3, ',', ''), '0'), ',') . ' $ / ' . rtrim(rtrim(number_format($p['out'], 3, ',', ''), '0'), ',') . ' $ par M jetons';
    }
    if ($p['image']) {
        $t[] = implode(' · ', array_map(static fn($s, $v) => ($s === '*' ? '' : $s . ' ') . $usd((float) $v) . '/image', array_keys($p['image']), $p['image']));
    }
    if ($p['minute']) {
        $t[] = $usd((float) $p['minute']) . '/min d’audio';
    }
    return implode(' · ', $t) ?: 'Gratuit';
};
?>
<?= LD\View::render('admin/ai-tabs', ['on' => 'costs']) ?>

<nav class="tabs" aria-label="Période">
  <?php foreach ($periods as $k => $label): ?><a href="/admin/ia/couts?periode=<?= e($k) ?>"<?= $k === $period ? ' class="is-on" aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</nav>

<div class="kpis">
  <div class="kpi kpi--red"><b><?= e($eur($r['usd'])) ?></b><span><?= e($periods[$period]) ?> · <?= e($usd($r['usd'])) ?></span></div>
  <div class="kpi"><b><?= e($eur($today)) ?></b><span>aujourd’hui</span></div>
  <div class="kpi"><b><?= e($eur($projection)) ?></b><span>projection fin de mois (<?= e($eur($month)) ?> à ce jour)</span></div>
  <div class="kpi"><b><?= $n($r['calls']) ?></b><span>appels IA · <?= $r['calls'] ? e($eur($r['usd'] / $r['calls'])) . ' en moyenne' : 'aucun' ?><?= $r['errors'] ? ' · ' . $n($r['errors']) . ' en erreur' : '' ?></span></div>
</div>

<?php if ($budget > 0): $pct = min(100, $month * $rate / $budget * 100); ?>
  <section class="card">
    <div class="card__head"><h2>Budget du mois</h2><span class="badge<?= $pct >= 100 ? '' : ' badge--ok' ?>"><?= e(number_format($pct, 0)) ?> %</span></div>
    <div class="aic-budget"><i style="width:<?= e(number_format($pct, 1, '.', '')) ?>%"<?= $pct >= 80 ? ' class="is-high"' : '' ?>></i></div>
    <p class="small muted"><?= e($eur($month)) ?> dépensés sur <?= e(number_format($budget, 2, ',', ' ')) ?> € · un e-mail te prévient dès que le budget est atteint.</p>
  </section>
<?php endif; ?>

<?php if ($r['unknown']): ?>
  <p class="maj-banner maj-banner--err"><span class="status status--new">Tarif inconnu</span> <b><?= e(implode(', ', array_keys($r['unknown']))) ?></b> <span>Saisis son prix plus bas pour l’inclure au total.</span></p>
<?php endif; ?>

<section class="card">
  <div class="card__head"><h2>Dépenses <?= count($bars) > 31 && strlen(array_key_first($bars) ?? '') === 7 ? 'par mois' : 'par jour' ?></h2><span class="small muted"><?= e(date('d/m/Y', strtotime($from))) ?> → <?= e(date('d/m/Y', strtotime($to))) ?></span></div>
  <div class="aic-chart" role="img" aria-label="Dépenses IA sur la période">
    <?php $step = (int) ceil(count($bars) / 12); $i = 0; foreach ($bars as $k => $v): ?>
      <div class="aic-bar" title="<?= e($barLabel($k) . ' : ' . $eur($v)) ?>"><i style="height:<?= e(number_format(max($v > 0 ? 2 : 0, $v / $max * 100), 1, '.', '')) ?>%"></i><?php if ($i++ % $step === 0): ?><span><?= e($barLabel($k)) ?></span><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
</section>

<div class="grid2">
  <section class="card card--flush">
    <div class="card__head"><h2>Par fonctionnalité</h2></div>
    <?php if (!$r['features']): ?><p class="muted" style="padding:0 20px 20px">Aucun appel sur la période.</p><?php else: ?>
    <table class="table">
      <thead><tr><th>Fonctionnalité</th><th>Appels</th><th class="hide-sm">Jetons</th><th>Coût</th></tr></thead>
      <tbody>
        <?php foreach ($r['features'] as $f => $s): ?>
          <tr><td><?= e($labels[$f] ?? $f) ?><br><small class="muted"><?= e($eur($s['usd'] / max(1, $s['calls']))) ?> par appel</small></td><td><?= $n($s['calls']) ?></td><td class="hide-sm"><small><?= $n($s['in']) ?> envoyés<br><?= $n($s['out']) ?> reçus<?= $s['images'] ? '<br>' . $n($s['images']) . ' image(s)' : '' ?></small></td><td><b class="nowrap"><?= e($eur($s['usd'])) ?></b></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
  <section class="card card--flush">
    <div class="card__head"><h2>Par modèle</h2></div>
    <?php if (!$r['models']): ?><p class="muted" style="padding:0 20px 20px">Aucun appel sur la période.</p><?php else: ?>
    <table class="table">
      <thead><tr><th>Modèle</th><th>Appels</th><th>Coût</th></tr></thead>
      <tbody>
        <?php foreach ($r['models'] as $key => $m): $p = AiCosts::price($m['provider'], $m['model']); ?>
          <tr><td><b><?= e($m['model']) ?></b><br><small class="muted"><?= e(Ai::PROVIDERS[$m['provider']]['label'] ?? $m['provider']) ?> · <?= e($priceTxt($p)) ?></small></td><td><?= $n($m['calls']) ?></td><td><b class="nowrap"><?= e($eur($m['usd'])) ?></b></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>

<form method="post" action="/admin/ia/couts" class="settings-form">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="periode" value="<?= e($period) ?>">
  <section class="card">
    <div class="card__head"><h2>Tarifs utilisés</h2><span class="small muted">en dollars US par million de jetons</span></div>
    <p class="small">Tarifs officiels relevés le <?= e(AiCosts::PRICES_DATE) ?> sur les pages d’Anthropic et de Google (hausses annoncées appliquées à leur date) ; pour les autres fournisseurs, catalogue public d’OpenRouter<?= $catalogDate ? ' (' . $n($catalogCount) . ' modèles, actualisé le ' . e(date('d/m/Y', $catalogDate)) . ')' : '' ?>. Un tarif saisi ici remplace le tarif automatique, y compris pour l’historique.</p>
    <table class="table aic-prices">
      <thead><tr><th>Modèle</th><th>Tarif retenu</th><th>Entrée</th><th>Sortie</th><th>Image</th></tr></thead>
      <tbody>
        <?php foreach ($models as $key): [$prov, $model] = array_pad(explode(':', $key, 2), 2, ''); $p = AiCosts::price($prov, $model); $o = $set['overrides'][$key] ?? []; ?>
          <tr>
            <td><b><?= e($model) ?></b><br><small class="muted"><?= e(Ai::PROVIDERS[$prov]['label'] ?? $prov) ?></small></td>
            <td><small><?= e($priceTxt($p)) ?></small><br><span class="badge<?= $p && $p['src'] !== 'catalog' ? ' badge--ok' : '' ?>"><?= e($p ? AiCosts::SOURCES[$p['src']] . ($p['approx'] ? ' · approx.' : '') : 'À saisir') ?></span></td>
            <td><input type="text" inputmode="decimal" name="price[<?= e($key) ?>][in]" value="<?= e(isset($o['in']) ? (string) $o['in'] : '') ?>" placeholder="auto" size="6" aria-label="Prix d’entrée de <?= e($model) ?>"></td>
            <td><input type="text" inputmode="decimal" name="price[<?= e($key) ?>][out]" value="<?= e(isset($o['out']) ? (string) $o['out'] : '') ?>" placeholder="auto" size="6" aria-label="Prix de sortie de <?= e($model) ?>"></td>
            <td><input type="text" inputmode="decimal" name="price[<?= e($key) ?>][image]" value="<?= e(isset($o['image']) ? (string) $o['image'] : '') ?>" placeholder="auto" size="6" aria-label="Prix par image de <?= e($model) ?>"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="form form--cols">
      <label>Taux de conversion (1 $ = … €)<input type="text" inputmode="decimal" name="rate" value="<?= e((string) $rate) ?>"></label>
      <label>Budget IA mensuel (€, 0 = aucun)<input type="text" inputmode="decimal" name="budget" value="<?= e((string) ($set['budget'] ?? 0)) ?>"></label>
    </div>
    <p class="small muted">Les montants sont des estimations calculées d’après les jetons consommés par le site et les tarifs publics (hors remises, crédits gratuits et taxes) : ta facture du fournisseur fait foi.</p>
  </section>
  <div class="actions sticky-actions">
    <button class="btn btn--red" type="submit">Enregistrer</button>
    <button class="btn btn--ghost" type="submit" formaction="/admin/ia/couts/tarifs">Actualiser le catalogue public des tarifs</button>
  </div>
</form>
