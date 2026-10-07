<?php
/**
 * Contenus › Trouvailles. Variables : $summary, $list (matchs et propositions), $total, $page, $pages,
 * $status, $origin, $admin, $gemini
 */
use App\Services\Trouvailles as T;

$types = ['complement' => ['Complément', 'ok'], 'divergence' => ['Divergence', 'warn'], 'recit' => ['Récit', 'navy'], 'info' => ['Information', 'info'], 'piste' => ['Piste', 'brouillon']];
$states = ['attente' => 'En attente', 'envoye' => 'Envoyées', 'ecarte' => 'Écartées', 'tout' => 'Toutes'];
$qs = fn (array $p) => '/admin/trouvailles?' . http_build_query(array_filter(['etat' => $status, 'source' => $origin, 'page' => null] + $p, fn ($v) => $v !== null && $v !== ''));
$long = ['recit', 'info', 'composition', 'buteurs', 'piste'];
?>
<p class="alert alert--info" style="margin:0">Le musée fouille les archives en ligne pour chaque match : la <b>presse de l’époque</b> numérisée par la BnF (Gallica : L’Est républicain, Le Petit Comtois, L’Écho des sports, Match l’Intran, Paris-Soir… jusqu’en <?= T::GALLICA_LAST_YEAR ?>) et le <b>web</b> (recherche Google par Gemini). Chaque trouvaille arrive ici comme une <b>proposition</b> : rien n’entre dans une fiche sans votre validation. Vérifiez la source (lien vers la page du journal), corrigez la valeur si besoin, puis <b>Envoyer dans la fiche</b> ou <b>Écarter</b>.</p>

<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= (int) $summary['attente'] ?></b><span>à valider</span><small><?= (int) $summary['matchs'] ?> match<?= $summary['matchs'] > 1 ? 's' : '' ?> concerné<?= $summary['matchs'] > 1 ? 's' : '' ?></small></div>
  <div class="kpi"><b><?= (int) $summary['envoye'] ?></b><span>envoyées</span><small>dans les fiches</small></div>
  <div class="kpi"><b><?= (int) $summary['ecarte'] ?></b><span>écartées</span><small>jamais reproposées</small></div>
  <div class="kpi"><b><?= (int) $summary['searched'] ?></b><span>matchs fouillés</span><small><?= $summary['queue'] ? (int) $summary['queue'] . ' dans la file' . ($summary['running'] ? ', en cours' : ', en pause') : 'file vide' ?></small></div>
</div>

<?php if ($admin): ?>
<section class="card">
  <div class="card__head"><h2 class="card__t">Lancer une recherche</h2><span class="card__note">administrateurs · coût IA suivi dans <a href="/admin/couts-ia">Coûts IA</a> (« Trouvailles »)</span></div>
  <div class="card__body stack" style="gap:14px">
    <?php if (!$gemini): ?><p class="alert" style="margin:0">Il faut une clé Gemini (Réglages › Assistant IA) : c’est elle qui lit les journaux et rédige les propositions.</p><?php endif; ?>
    <form method="post" action="/admin/trouvailles" class="stack" style="gap:10px">
      <?= csrf_field() ?>
      <div class="row" style="gap:12px;flex-wrap:wrap;align-items:flex-end">
        <label class="f" style="margin:0"><span class="f__k">Matchs de</span><input class="input" type="number" name="de" value="1928" min="1900" max="2100" style="width:100px"></label>
        <label class="f" style="margin:0"><span class="f__k">à</span><input class="input" type="number" name="a" value="<?= T::GALLICA_LAST_YEAR ?>" min="1900" max="2100" style="width:100px"></label>
        <div class="checklist">
          <?php foreach (T::SOURCES as $k => $label): ?>
            <label><input type="checkbox" name="sources[]" value="<?= e($k) ?>"<?= in_array($k, $summary['sources'], true) || $k === 'gallica' ? ' checked' : '' ?>> <?= e($label) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="row" style="gap:16px;flex-wrap:wrap">
        <label class="small"><input type="checkbox" name="incomplets" value="1" checked> Seulement les fiches incomplètes (récit, composition, buteurs, affluence, arbitre, stade ou score manquant)</label>
        <label class="small"><input type="checkbox" name="refaire" value="1"> Refouiller les matchs déjà fouillés</label>
      </div>
      <div class="row" style="gap:8px;flex-wrap:wrap">
        <button class="btn" type="submit" name="action" value="essai"<?= $gemini ? '' : ' disabled' ?>>Essai sur 3 matchs</button>
        <button class="btn btn--primary" type="submit" name="action" value="lancer"<?= $gemini ? '' : ' disabled' ?>>Lancer pour toute la période</button>
      </div>
      <p class="xs muted" style="margin:0">Gallica : environ une minute par match (8 journaux lus au plus), presse jusqu’en <?= T::GALLICA_LAST_YEAR ?>. Web : toutes époques, une recherche Google par match. La tâche planifiée fouille quelques matchs à chaque passage ; les propositions arrivent au fil de l’eau.</p>
    </form>
    <?php if ($summary['queue']): $cronAt = (int) ($cron['_last'] ?? 0); $cronOk = $cronAt && $cronAt > time() - 1200; ?>
      <div class="stack" style="gap:8px;padding:14px 16px;border:2px solid var(--navy);background:var(--paper)" data-trv-run data-running="<?= $summary['running'] ? '1' : '0' ?>" data-csrf="<?= e(\App\Core\Session::csrfToken()) ?>">
        <div class="row" style="gap:10px;flex-wrap:wrap;align-items:center">
          <b data-trv-state><?= $summary['running'] ? 'Recherche en cours' : 'Recherche en pause' ?></b>
          <span class="small"><b data-trv-left><?= (int) $summary['queue'] ?></b> match(s) restant(s) · <b data-trv-searched><?= (int) $summary['searched'] ?></b> fouillé(s) · <?= e(implode(', ', array_map(fn ($k) => T::SOURCES[$k] ?? $k, $summary['sources']))) ?></span>
          <form method="post" action="/admin/trouvailles"><?= csrf_field() ?><button class="btn btn--sm" type="submit" name="action" value="<?= $summary['running'] ? 'pause' : 'reprendre' ?>"><?= $summary['running'] ? 'Mettre en pause' : 'Reprendre' ?></button></form>
          <form method="post" action="/admin/trouvailles"><?= csrf_field() ?><button class="btn btn--ghost btn--sm" type="submit" name="action" value="vider">Vider la file</button></form>
        </div>
        <?php if ($summary['running']): ?>
          <p class="small" style="margin:0" data-trv-live>Tant que cette page reste ouverte, elle fait avancer la recherche elle-même, un match à la fois (environ une à deux minutes chacun) : <span data-trv-last>démarrage…</span></p>
          <p class="small" style="margin:0" data-trv-new hidden><b data-trv-found>0</b> nouvelle(s) proposition(s) : <a href="/admin/trouvailles">afficher</a>.</p>
        <?php endif; ?>
        <p class="xs muted" style="margin:0"><?= $cronOk ? 'La tâche planifiée du serveur passe aussi régulièrement (dernier passage ' . e(\App\Admin\Base::ago(date('c', $cronAt))) . ') : la recherche continue page fermée.' : '<b class="ko">La tâche planifiée du serveur ne passe pas</b> (' . ($cronAt ? 'dernier passage ' . e(\App\Admin\Base::ago(date('c', $cronAt))) : 'jamais') . ') : page fermée, la recherche s’arrête. Gardez cette page ouverte, ou vérifiez le cron dans <a href="/admin/taches">Système › Tâches planifiées</a>.' ?></p>
      </div>
    <?php endif; ?>
    <form method="post" action="/admin/trouvailles" class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end">
      <?= csrf_field() ?>
      <label class="f" style="margin:0;flex:1 1 320px"><span class="f__k">Fouiller un match précis</span><input class="input" name="fiche" placeholder="Adresse de la fiche (/matchs/1931-1932/…) ou son numéro"></label>
      <input type="hidden" name="sources[]" value="gallica">
      <label class="small"><input type="checkbox" name="sources[]" value="web"> aussi le web</label>
      <button class="btn" type="submit" name="action" value="match"<?= $gemini ? '' : ' disabled' ?>>Fouiller maintenant</button>
    </form>
    <?php if ($summary['log']): ?>
      <details<?= $summary['queue'] ? ' open' : '' ?>><summary class="small">Journal des recherches</summary><ul class="xs muted" style="margin:6px 0 0"><?php foreach ($summary['log'] as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul></details>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<nav class="tabsbar" style="padding:0">
  <?php foreach ($states as $k => $label): ?><a href="<?= e($qs(['etat' => $k])) ?>"<?= $status === $k ? ' class="is-on"' : '' ?>><?= e($label) ?><?php if (isset($summary[$k])): ?><em><?= (int) $summary[$k] ?></em><?php endif; ?></a><?php endforeach; ?>
  <span style="flex:1"></span>
  <a href="<?= e($qs(['source' => null])) ?>"<?= !$origin ? ' class="is-on"' : '' ?>>Toutes sources</a>
  <?php foreach (T::SOURCES as $k => $label): ?><a href="<?= e($qs(['source' => $k])) ?>"<?= $origin === $k ? ' class="is-on"' : '' ?>><?= $k === 'gallica' ? 'Presse (Gallica)' : 'Web' ?></a><?php endforeach; ?>
</nav>

<?php if (!$list): ?>
  <section class="card card--pad"><p class="muted" style="margin:0"><?= $status === 'attente' ? 'Aucune proposition en attente.' . ($admin ? ' Lancez une recherche ci-dessus.' : ' Les administrateurs lancent les recherches ; les propositions arrivent ici.') : 'Rien ici pour l’instant.' ?></p></section>
<?php endif; ?>

<?php foreach ($list as $m): ?>
  <section class="card" id="m<?= (int) $m['id'] ?>">
    <div class="card__head">
      <h2 class="card__t" style="font-size:18px"><?= e($m['title']) ?></h2>
      <span class="card__note"><a href="/admin/fiche/<?= (int) $m['id'] ?>">Ouvrir la fiche</a> · <a href="<?= e($m['path']) ?>" target="_blank" rel="noopener">Voir sur le site ↗</a></span>
    </div>
    <div class="card__body stack" style="gap:0;padding:0">
      <?php foreach ($m['items'] as $it): [$tl, $tc] = $types[$it['type']] ?? ['Proposition', 'info']; $pending = ($it['status'] ?? 'attente') === 'attente'; ?>
        <form method="post" action="/admin/trouvailles" class="trv" style="display:grid;grid-template-columns:minmax(150px,190px) 1fr auto;gap:14px;padding:14px 18px;border-top:1px solid var(--line, #d8d2c0);align-items:start">
          <?= csrf_field() ?>
          <input type="hidden" name="match" value="<?= (int) $m['id'] ?>"><input type="hidden" name="item" value="<?= e($it['id']) ?>">
          <input type="hidden" name="etat" value="<?= e($status) ?>"><input type="hidden" name="source" value="<?= e((string) $origin) ?>"><input type="hidden" name="page" value="<?= (int) $page ?>">
          <div class="stack" style="gap:6px">
            <b class="small"><?= e(T::FIELDS[$it['field']] ?? $it['field']) ?></b>
            <span><span class="pill pill--<?= e($tc) ?>"><?= e($tl) ?></span></span>
            <span class="xs muted"><?= ($it['origin'] ?? '') === 'web' ? 'Web' : 'Presse (Gallica)' ?></span>
          </div>
          <div class="stack" style="gap:8px;min-width:0">
            <?php if ($it['current'] !== ''): ?><p class="small" style="margin:0"><span class="muted">La fiche dit :</span> <?= e($it['current']) ?></p><?php endif; ?>
            <?php if ($pending): ?>
              <?php if (in_array($it['field'], $long, true)): ?>
                <textarea class="input" name="value" rows="<?= $it['field'] === 'recit' ? 7 : 3 ?>" style="width:100%"><?= e($it['value']) ?></textarea>
              <?php else: ?>
                <input class="input" name="value" value="<?= e($it['value']) ?>" style="max-width:420px">
              <?php endif; ?>
            <?php else: ?>
              <p style="margin:0"><?= nl2br(e((string) ($it['sent'] ?? $it['value']))) ?></p>
              <p class="xs muted" style="margin:0"><?= ($it['status'] ?? '') === 'envoye' ? 'Envoyée' : 'Écartée' ?><?= !empty($it['by']) ? ' par ' . e($it['by']) : '' ?><?= !empty($it['done_at']) ? ' · ' . e(\App\Admin\Base::ago($it['done_at'])) : '' ?></p>
            <?php endif; ?>
            <ul class="xs" style="margin:0;padding-left:18px">
              <?php foreach ($it['sources'] as $s): ?>
                <li><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener"><?= e($s['label']) ?> ↗</a><?php if (!empty($s['snippet'])): ?> <span class="muted">— « <?= e(mb_strimwidth($s['snippet'], 0, 220, '…')) ?> »</span><?php endif; ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="stack" style="gap:6px;min-width:150px">
            <?php if ($pending): ?>
              <button class="btn btn--primary btn--sm" type="submit" name="action" value="accepter"><?= $it['field'] === 'piste' ? 'Noter la source' : 'Envoyer dans la fiche' ?></button>
              <button class="btn btn--ghost btn--sm" type="submit" name="action" value="ecarter">Écarter</button>
            <?php elseif (($it['status'] ?? '') === 'ecarte'): ?>
              <button class="btn btn--ghost btn--sm" type="submit" name="action" value="retablir">Remettre en attente</button>
            <?php endif; ?>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?><?php if ($i === $page): ?><span class="is-on"><?= $i ?></span><?php else: ?><a href="<?= e($qs(['page' => (string) $i])) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?>
  </nav>
<?php endif; ?>
<style>@media (max-width: 760px) { .trv { grid-template-columns: 1fr !important; } }</style>
