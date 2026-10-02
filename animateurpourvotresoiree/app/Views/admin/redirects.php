<?php
use App\Core\Url;
/** @var array $manual @var array $hits @var array $log */
?>
<div class="adm-head"><div><h1>Redirections <span class="serif">& 404</span></h1><p>Les anciennes adresses du site (fiche.php, depresultat.php, articles…) sont redirigées automatiquement. Ajoutez ici vos redirections manuelles.</p></div></div>
<form class="box form mb-2" method="post" action="<?= e(Url::admin('seo/redirections')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <h2>Nouvelle redirection (301)</h2>
  <div class="form-grid"><div class="field"><label for="rd-from">Ancienne adresse</label><input id="rd-from" type="text" name="from" placeholder="/ancienne-page.html" required value="<?= e((string) App\Core\Request::query('from', '')) ?>"></div><div class="field"><label for="rd-to">Nouvelle adresse</label><input id="rd-to" type="text" name="to" placeholder="/dj/rhone-69/" required></div><div class="field"><label for="rd-note">Note</label><input id="rd-note" type="text" name="note" maxlength="120"></div></div>
  <div><button class="btn btn-sm btn-ink" type="submit">Ajouter</button></div>
</form>
<div class="box">
  <h2>Redirections manuelles (<?= count($manual) ?>)</h2>
  <?php if (!$manual): ?><p class="muted small">Aucune redirection manuelle.</p><?php else: ?>
  <div class="table-wrap" style="box-shadow:none"><table class="tbl"><thead><tr><th>Ancienne adresse</th><th>Destination</th><th class="num">Utilisations</th><th>Note</th><th></th></tr></thead><tbody>
    <?php foreach ($manual as $from => $r): ?><tr><td class="small"><code><?= e((string) $from) ?></code></td><td class="small"><a href="<?= e((string) $r['to']) ?>" target="_blank" rel="noopener"><?= e((string) $r['to']) ?></a></td><td class="num"><?= nf((int) ($hits[$from] ?? 0)) ?></td><td class="small"><?= e((string) ($r['note'] ?? '')) ?></td>
      <td class="actions"><form method="post" action="<?= e(Url::admin('seo/redirections')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="from" value="<?= e((string) $from) ?>"><button class="btn btn-xs" type="submit"><?= icon('trash', 12) ?></button></form></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
<div class="box" id="e404">
  <div class="box-head"><h2>Pages introuvables (404)</h2><form method="post" action="<?= e(Url::admin('seo/redirections')) ?>" data-confirm="Vider le journal ?"><?= csrf_field() ?><input type="hidden" name="action" value="clear404"><button class="btn btn-xs" type="submit">Vider</button></form></div>
  <p class="small muted">Adresses demandées qui n'existent pas, les plus fréquentes en premier. Créez une redirection pour récupérer le trafic.</p>
  <?php if (!$log): ?><p class="muted small">Aucune page introuvable enregistrée.</p><?php else: ?>
  <div class="table-wrap" style="box-shadow:none"><table class="tbl"><thead><tr><th>Adresse</th><th class="num">Fois</th><th>Dernière</th><th>Provenance</th><th></th></tr></thead><tbody>
    <?php foreach ($log as $path => $l): ?><tr><td class="small"><code><?= e((string) $path) ?></code></td><td class="num"><?= (int) $l['n'] ?></td><td class="small"><?= e(ago((string) $l['last'])) ?></td><td class="small"><span class="t-ex" style="max-width:260px"><?= e((string) ($l['ref'] ?? '')) ?></span></td>
      <td class="actions"><a class="btn btn-xs" href="?from=<?= rawurlencode((string) $path) ?>#rd-from">Rediriger</a> <form method="post" action="<?= e(Url::admin('seo/redirections')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="ignore404"><input type="hidden" name="from" value="<?= e((string) $path) ?>"><button class="btn btn-xs" type="submit" title="Ignorer">✕</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
