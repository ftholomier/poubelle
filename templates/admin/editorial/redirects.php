<?php
/** Redirections 301 et adresses introuvables. Variables : $tab, $q, $rows, $total, et $page, $pages, $all */
$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$back = '/admin/redirections' . ($tab === 'introuvables' ? '?onglet=introuvables' : '');
?>
<?php if ($tab === 'redirections'): ?>
  <form class="card card--pad" method="post" action="/admin/redirections">
    <?= csrf_field() ?><input type="hidden" name="action" value="ajouter"><input type="hidden" name="back" value="<?= e($back) ?>">
    <h2 class="card__t card__t--sm">Nouvelle redirection</h2>
    <div class="fgrid">
      <label class="f f--2"><span class="f__k">Ancienne adresse</span><input type="text" name="from" required placeholder="/2015/03/sochaux-metz-1988/ ou https://www.fcsochauxretro.com/…"></label>
      <label class="f f--2"><span class="f__k">Nouvelle adresse</span><input type="text" name="to" required placeholder="Tapez le titre d’une fiche ou une adresse" data-ac="fiches" data-ac-value="url"></label>
    </div>
    <div class="row"><button type="submit" class="btn btn--navy">Créer la redirection 301</button><span class="f__help">Les anciennes adresses WordPress (<?= $fmt($all) ?> au total) sont déjà redirigées automatiquement vers les nouvelles fiches.</span></div>
  </form>
  <form class="toolbar" method="get" action="/admin/redirections">
    <div class="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher une adresse…" aria-label="Rechercher"><button type="submit">→</button></div>
    <span class="small muted"><?= $fmt($total) ?> redirection<?= $total > 1 ? 's' : '' ?></span>
  </form>
  <div class="table">
    <table>
      <thead><tr><th>Ancienne adresse</th><th></th><th>Nouvelle adresse</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="ellipsis small" style="max-width:420px" title="<?= e($r['from']) ?>"><?= e($r['from']) ?></td>
          <td class="muted">→</td>
          <td class="ellipsis small" style="max-width:420px"><a href="<?= e($r['to']) ?>" target="_blank" rel="noopener"><?= e($r['to']) ?></a></td>
          <td><form method="post" action="/admin/redirections" data-confirm="Supprimer cette redirection ?|<?= e($r['from']) ?> ne sera plus redirigée (page introuvable).|Supprimer|danger"><?= csrf_field() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="from" value="<?= e($r['from']) ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button type="submit" class="iconbtn" title="Supprimer" aria-label="Supprimer">✕</button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4" class="muted" style="padding:24px;text-align:center">Aucune redirection.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pagination">
      <?php for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++): ?><a class="<?= $i === $page ? 'is-on' : '' ?>" href="/admin/redirections?<?= e(http_build_query(array_filter(['q' => $q, 'page' => $i]))) ?>"><?= $i ?></a><?php endfor; ?>
      <span class="xs muted" style="border:0;background:none">page <?= $page ?> / <?= $pages ?></span>
    </nav>
  <?php endif; ?>
<?php else: ?>
  <p class="small muted" style="margin:0">Adresses demandées par les visiteurs (ou des liens externes) qui n’existent pas sur le site. Redirigez les plus fréquentes vers la bonne fiche : une suggestion est proposée quand une fiche ressemble à l’adresse.</p>
  <div class="toolbar">
    <form class="toolbar" method="get" action="/admin/redirections"><input type="hidden" name="onglet" value="introuvables"><div class="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Filtrer…" aria-label="Filtrer"><button type="submit">→</button></div></form>
    <span class="small muted"><?= $fmt($total) ?> adresse<?= $total > 1 ? 's' : '' ?></span>
    <span class="grow"></span>
    <form method="post" action="/admin/redirections" data-confirm="Vider la liste ?|Les adresses introuvables enregistrées seront oubliées.|Vider"><?= csrf_field() ?><input type="hidden" name="action" value="vider"><input type="hidden" name="back" value="<?= e($back) ?>"><button type="submit" class="btn btn--sm">Vider la liste</button></form>
  </div>
  <div class="table">
    <table>
      <thead><tr><th>Adresse introuvable</th><th>Vues</th><th>Dernière</th><th>Provenance</th><th>Rediriger vers</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="ellipsis small" style="max-width:340px" title="<?= e($r['path']) ?>"><?= e($r['path']) ?></td>
          <td class="t-num"><?= (int) $r['n'] ?></td>
          <td class="xs nowrap"><?= e(\App\Admin\Base::ago($r['last'] ?? null)) ?></td>
          <td class="xs ellipsis" style="max-width:160px"><?= e((string) ($r['ref'] ?? '')) ?: '<span class="muted">—</span>' ?></td>
          <td>
            <form class="row" style="gap:4px;flex-wrap:nowrap" method="post" action="/admin/redirections">
              <?= csrf_field() ?><input type="hidden" name="action" value="ajouter"><input type="hidden" name="from" value="<?= e($r['path']) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
              <input class="in in--sm" type="text" name="to" value="<?= e($r['suggest']['path'] ?? '') ?>" placeholder="Titre d’une fiche ou /adresse/" data-ac="fiches" data-ac-value="url" aria-label="Rediriger <?= e($r['path']) ?> vers" style="width:260px" required>
              <button type="submit" class="btn btn--sm btn--navy">301</button>
            </form>
            <?php if (!empty($r['suggest'])): ?><span class="xs muted">Suggestion : <?= e($r['suggest']['title']) ?></span><?php endif; ?>
          </td>
          <td><form method="post" action="/admin/redirections"><?= csrf_field() ?><input type="hidden" name="action" value="ignorer"><input type="hidden" name="from" value="<?= e($r['path']) ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button type="submit" class="iconbtn" title="Ignorer" aria-label="Ignorer">✕</button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted" style="padding:24px;text-align:center">Aucune adresse introuvable enregistrée. 🎉</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
