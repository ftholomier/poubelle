<?php
use App\Core\Url;
use App\Services\Pages;
/** @var array $items */
?>
<div class="adm-head"><div><h1>Pages</h1><p>Pages institutionnelles (mentions légales, CGU, confidentialité, FAQ…) et pages libres.</p></div><a class="btn btn-sm btn-ink" href="<?= e(Url::admin('pages/nouvelle')) ?>"><?= icon('plus', 16) ?> Nouvelle page</a></div>
<div class="table-wrap"><table class="tbl"><thead><tr><th>Titre</th><th>Adresse</th><th>Statut</th><th>Modifiée</th></tr></thead><tbody>
  <?php foreach ($items as $p): ?><tr><td><a class="t-main" href="<?= e(Url::admin('pages/' . $p['id'])) ?>"><?= e($p['title']) ?></a><?= in_array($p['slug'], Pages::RESERVED, true) ? '<span class="t-sub">page obligatoire</span>' : '' ?></td><td class="small"><a href="<?= e(Url::page($p['slug'])) ?>" target="_blank" rel="noopener">/<?= e($p['slug']) ?>/</a></td><td><span class="status-pill st-<?= $p['status'] === 'published' ? 'active' : 'draft' ?>"><?= $p['status'] === 'published' ? 'Publiée' : 'Brouillon' ?></span></td><td class="small"><?= e(date_fr($p['updated'], 'short')) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
