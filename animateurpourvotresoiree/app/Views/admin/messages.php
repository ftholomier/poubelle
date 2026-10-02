<?php
use App\Core\Url;
use App\Services\Leads;
/** @var array $items @var array $counts @var array $filters @var array $names */
$f = static fn (string $k) => (string) ($filters[$k] ?? '');
$tabUrl = static function (string $s) use ($filters): string { $q = $filters; unset($q['page']); $q['statut'] = $s; if ($s === '') { unset($q['statut']); } return '?' . http_build_query($q); };
?>
<div class="adm-head"><div><h1>Messages <span class="serif">aux pros</span></h1><p>Messages envoyés depuis les fiches. Les messages douteux attendent votre validation.</p></div></div>
<div class="tabs">
  <a href="<?= e($tabUrl('')) ?>"<?= $f('statut') === '' ? ' class="on"' : '' ?>>Tous <em><?= nf($counts[''] ?? 0) ?></em></a>
  <?php foreach (Leads::MESSAGE_STATUSES as $k => $l): ?><a href="<?= e($tabUrl($k)) ?>"<?= $f('statut') === $k ? ' class="on"' : '' ?>><?= e($l) ?> <em><?= nf($counts[$k] ?? 0) ?></em></a><?php endforeach; ?>
</div>
<form class="filters" method="get">
  <?php foreach (['statut', 'pro'] as $k): if ($f($k) !== ''): ?><input type="hidden" name="<?= $k ?>" value="<?= e($f($k)) ?>"><?php endif; endforeach; ?>
  <div class="field grow"><label for="mq">Recherche</label><input id="mq" type="search" name="q" value="<?= e($f('q')) ?>" placeholder="Nom, email, texte, n°…"></div>
  <button class="btn btn-ink btn-sm" type="submit">Filtrer</button>
</form>
<div class="table-wrap">
  <table class="tbl">
    <thead><tr><th>Date</th><th>De</th><th>Pour</th><th>Message</th><th>Statut</th><th class="num">Spam</th></tr></thead>
    <tbody>
      <?php foreach ($items as $m): ?>
        <tr>
          <td class="small"><?= e(date_fr($m['created'], 'short')) ?></td>
          <td><a class="t-main" href="<?= e(Url::admin('messages/' . $m['id'])) ?>"><?= e($m['name']) ?></a><span class="t-sub"><?= e($m['email']) ?></span></td>
          <td class="small"><a href="<?= e(Url::admin('pros/' . $m['pro'])) ?>"><?= e($names[$m['pro']] ?? ('#' . $m['pro'])) ?></a></td>
          <td><span class="t-ex"><?= e($m['excerpt']) ?></span></td>
          <td><span class="status-pill st-<?= e($m['status']) ?>"><?= e(Leads::MESSAGE_STATUSES[$m['status']] ?? $m['status']) ?></span><?= $m['read'] ? '<span class="t-sub">lu par le pro</span>' : '' ?></td>
          <td class="num"><span class="score <?= $m['score'] >= 70 ? 'hi' : ($m['score'] >= 30 ? 'mid' : 'lo') ?>"><?= (int) $m['score'] ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="6"><div class="empty-sm">Aucun message.</div></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?= App\Core\View::partial('admin/partials/pager', ['page' => $page, 'pages' => $pages, 'link' => $link]) ?>
