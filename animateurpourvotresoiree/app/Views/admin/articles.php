<?php
use App\Core\Url;
use App\Services\Blog;
/** @var array $items @var string $status @var string $q @var bool $aiOn */
?>
<div class="adm-head">
  <div><h1>Blog</h1><p><?= nf($total) ?> article(s). Les anciennes « actualités » du site ont été migrées avec redirection.</p></div>
  <a class="btn btn-sm btn-ink" href="<?= e(Url::admin('blog/nouveau')) ?>"><?= icon('plus', 16) ?> Nouvel article</a>
</div>
<?php if ($aiOn): ?>
<form class="box mb-2 form" method="post" action="<?= e(Url::admin('blog/nouveau')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="ai-draft">
  <div class="row-wrap"><div class="field grow" style="flex:1 1 360px"><label for="ai-topic">✨ Faire rédiger un brouillon par l'IA</label><input id="ai-topic" type="text" name="topic" maxlength="200" placeholder="Ex. : Comment choisir son DJ de mariage ?" required></div><button class="btn btn-sm btn-lime" type="submit" style="align-self:end">Rédiger</button></div>
  <p class="small muted">Le brouillon n'est jamais publié automatiquement : relisez, vérifiez et illustrez-le.</p></form>
<?php endif; ?>
<form class="filters" method="get" data-autosubmit>
  <div class="field grow"><label for="aq">Recherche</label><input id="aq" type="search" name="q" value="<?= e($q) ?>"></div>
  <div class="field"><label for="ast">Statut</label><select id="ast" name="statut"><option value="">Tous</option><option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Publiés</option><option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Brouillons</option></select></div>
  <button class="btn btn-ink btn-sm" type="submit">Filtrer</button>
</form>
<div class="table-wrap"><table class="tbl"><thead><tr><th></th><th>Titre</th><th>Catégorie</th><th>Statut</th><th>Publication</th><th class="num">Vues</th></tr></thead><tbody>
  <?php foreach ($items as $a): $img = Blog::imageUrl($a['image'], 'sm'); ?><tr>
    <td><?php if ($img): ?><img class="thumb" src="<?= e($img) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb">📝</span><?php endif; ?></td>
    <td><a class="t-main" href="<?= e(Url::admin('blog/' . $a['id'])) ?>"><?= e($a['title']) ?></a><span class="t-sub">/blog/<?= e($a['slug']) ?>/<?= $a['sponsored'] ? ' · sponsorisé' : '' ?><?= $a['legacy'] ? ' · ancienne URL redirigée' : '' ?></span></td>
    <td class="small"><?= e(Blog::CATEGORIES[$a['cat']] ?? $a['cat']) ?></td>
    <td><span class="status-pill st-<?= $a['status'] === 'published' ? 'active' : 'draft' ?>"><?= $a['status'] === 'published' ? 'Publié' : 'Brouillon' ?></span></td>
    <td class="small"><?= e(date_fr($a['published'], 'short')) ?></td>
    <td class="num"><?= nf($a['views']) ?></td>
  </tr><?php endforeach; ?>
  <?php if (!$items): ?><tr><td colspan="6"><div class="empty-sm">Aucun article.</div></td></tr><?php endif; ?>
</tbody></table></div>
<?= App\Core\View::partial('admin/partials/pager', ['page' => $page, 'pages' => $pages, 'link' => $link]) ?>
