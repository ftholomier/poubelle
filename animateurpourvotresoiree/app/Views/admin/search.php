<?php /** @var string $q @var array $groups */ ?>
<div class="adm-head"><div><h1>Recherche</h1><p>Astuce : <kbd>Ctrl</kbd> + <kbd>K</kbd> ouvre la recherche depuis n'importe quelle page.</p></div></div>
<form class="filters" method="get"><div class="field grow"><label for="sq">Rechercher</label><input id="sq" type="search" name="q" value="<?= e($q) ?>" autofocus></div><button class="btn btn-ink btn-sm" type="submit">Rechercher</button></form>
<?php if ($q !== '' && !$groups): ?><div class="empty-sm">Aucun résultat pour « <?= e($q) ?> ».</div><?php endif; ?>
<?php foreach ($groups as $label => $items): ?>
  <div class="box"><h2><?= e($label) ?></h2><ul class="list-rows"><?php foreach ($items as $it): ?><li><span><a class="t-main" href="<?= e($it['url']) ?>"><?= e($it['title']) ?></a><br><span class="muted"><?= e($it['sub']) ?></span></span><span class="muted"><?= e($it['kind']) ?></span></li><?php endforeach; ?></ul></div>
<?php endforeach; ?>
