<?php
/**
 * Médiathèque. Variables : $items, $total, $page, $pages, $counts, $folders, $filter, $folder, $sort, $q, $query, $canDelete
 */
use App\Admin\Medias;

$qs = function (array $over) use ($query): string {
    $p = array_filter(array_merge($query, $over), fn ($v) => $v !== null && $v !== '');
    return $p ? '?' . http_build_query($p) : '';
};
$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
?>
<div class="drop" data-media-drop tabindex="0" role="button" aria-label="Importer des fichiers">
  <b>Glissez ici vos photos et documents</b>
  <span class="small muted">ou <label class="linkbtn" style="cursor:pointer">choisissez des fichiers<input type="file" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" multiple hidden data-media-up></label> · JPG, PNG, WebP, GIF ou PDF · 25 Mo max par fichier</span>
  <span class="small" data-media-progress hidden></span>
</div>

<div class="chips">
  <?php foreach (Medias::FILTERS as $k => $l): ?>
    <a class="chip<?= $filter === $k ? ' is-on' : '' ?>" href="/admin/medias<?= e($qs(['filtre' => $k ?: null, 'page' => null])) ?>"><?= e($l) ?> <em>· <?= $fmt($counts[$k] ?? 0) ?></em></a>
  <?php endforeach; ?>
</div>

<form class="toolbar" method="get" action="/admin/medias">
  <?php if ($filter !== ''): ?><input type="hidden" name="filtre" value="<?= e($filter) ?>"><?php endif; ?>
  <div class="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom de fichier, légende, crédit…" aria-label="Rechercher un média"><button type="submit" aria-label="Rechercher">→</button></div>
  <select name="dossier" aria-label="Dossier" onchange="this.form.submit()">
    <option value="">Tous les dossiers</option>
    <?php foreach ($folders as $f => $n): ?><option value="<?= e((string) $f) ?>"<?= $folder === (string) $f ? ' selected' : '' ?>><?= e($f === 'contributions' ? 'Contributions' : (string) $f) ?> (<?= $fmt($n) ?>)</option><?php endforeach; ?>
  </select>
  <select name="tri" aria-label="Tri" onchange="this.form.submit()">
    <?php foreach (['recent' => 'Plus récents', 'ancien' => 'Plus anciens', 'nom' => 'Nom de fichier'] as $k => $l): ?><option value="<?= e($k) ?>"<?= $sort === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
  </select>
  <span class="small muted"><?= $fmt($total) ?> média<?= $total > 1 ? 's' : '' ?></span>
  <span class="grow"></span>
  <button type="button" class="btn btn--sm" data-msel-page>Tout sélectionner sur la page</button>
</form>

<div class="bulk" data-mbulk hidden>
  <span data-mbulk-count></span>
  <button type="button" data-mbulk-edit>Crédit, droits, légende…</button>
  <button type="button" data-mbulk-none>Tout désélectionner</button>
</div>

<div class="thumbs" data-media-grid>
  <?php foreach ($items as $it): ?>
    <div class="mcardx<?= $it['credit'] === '' && !$it['pdf'] ? ' is-bad' : '' ?>" data-media="<?= e(json_encode($it, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
      <button type="button" class="mcardx__img" data-medit title="Modifier" style="all:unset;cursor:pointer;display:block;position:relative;aspect-ratio:4/3;background:var(--sand);border-bottom:2px solid var(--navy);overflow:hidden">
        <img src="<?= e($it['thumb']) ?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:<?= $it['pdf'] ? 'contain;padding:18px' : 'cover' ?>">
        <?php if ($it['missing']): ?><span class="mcardx__cover" style="background:var(--pink)">Fichier absent</span><?php elseif (!$it['used_count']): ?><span class="mcardx__cover" style="background:var(--paper)">Inutilisé</span><?php endif; ?>
      </button>
      <label class="mtile__check" title="Sélectionner"><input type="checkbox" class="check" data-msel value="<?= e($it['file']) ?>" aria-label="Sélectionner <?= e($it['name']) ?>"></label>
      <div class="mcardx__body">
        <b style="overflow-wrap:anywhere;font-size:13px"><?= e($it['name']) ?></b>
        <span class="<?= $it['caption'] === '' ? 'muted' : '' ?>" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= e($it['caption'] ?: 'Sans légende') ?></span>
        <span><?= $it['credit'] !== '' ? '© ' . e($it['credit']) : ($it['pdf'] ? '<span class="muted">Document</span>' : '<span class="ko">⚠ Sans crédit</span>') ?></span>
        <span class="xs muted"><?= $it['used_count'] ? 'Utilisé dans ' . (int) $it['used_count'] . ' contenu' . ($it['used_count'] > 1 ? 's' : '') : 'Utilisé nulle part' ?></span>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php if (!$items): ?><div class="empty">Aucun média ne correspond</div><?php endif; ?>

<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pagination">
    <?php if ($page > 1): ?><a href="/admin/medias<?= e($qs(['page' => $page - 1])) ?>">←</a><?php endif; ?>
    <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?><a class="<?= $i === $page ? 'is-on' : '' ?>" href="/admin/medias<?= e($qs(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?>
    <?php if ($page < $pages): ?><a href="/admin/medias<?= e($qs(['page' => $page + 1])) ?>">→</a><?php endif; ?>
    <span class="xs muted" style="border:0;background:none">page <?= $page ?> / <?= $pages ?></span>
  </nav>
<?php endif; ?>
<script type="application/json" id="media-conf"><?= json_encode(['canDelete' => $canDelete], JSON_HEX_TAG) ?></script>
