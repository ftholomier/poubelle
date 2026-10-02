<?php
use App\Core\Url;
/** @var array $all @var array $suggest @var ?array $current @var bool $aiOn @var string $label @var int $count */
?>
<div class="adm-head"><div><h1>Pages <span class="serif">locales</span></h1><p>Personnalisez le titre, la description et le texte d'introduction des pages « métier × lieu » (sinon un texte est généré automatiquement à partir des vraies données).</p></div></div>
<div class="adm-cols">
  <div>
    <?php if ($current): ?>
    <form class="box form" method="post" action="<?= e(Url::admin('seo/pages-locales')) ?>">
      <?= csrf_field() ?><input type="hidden" name="path" value="<?= e((string) $current['path']) ?>"><input type="hidden" name="label" value="<?= e($label) ?>"><input type="hidden" name="count" value="<?= (int) $count ?>">
      <div class="box-head"><h2><?= e((string) $current['path']) ?></h2><a class="link small" href="<?= e((string) $current['path']) ?>" target="_blank" rel="noopener">Voir la page ↗</a></div>
      <div class="field"><label for="ld-title">Titre (balise title)</label><input id="ld-title" type="text" name="title" maxlength="80" value="<?= e((string) ($current['title'] ?? '')) ?>" data-seo-count="60" placeholder="Vide = gabarit automatique"></div>
      <div class="field"><label for="ld-desc">Meta description</label><input id="ld-desc" type="text" name="description" maxlength="170" value="<?= e((string) ($current['description'] ?? '')) ?>" data-seo-count="160"></div>
      <div class="field"><label for="ld-intro">Texte d'introduction</label><textarea id="ld-intro" name="intro" rows="7"><?= e((string) ($current['intro'] ?? '')) ?></textarea></div>
      <div class="row-wrap">
        <button class="btn btn-sm btn-coral" type="submit" name="action" value="save">Enregistrer</button>
        <?php if ($aiOn): ?><button class="btn btn-sm btn-lime" type="submit" name="action" value="ai">✨ Proposer avec l'IA</button><?php endif; ?>
        <?php if (!empty($current['intro']) || !empty($current['title'])): ?><button class="btn btn-sm" type="submit" name="action" value="delete" data-confirm-click="Revenir au texte automatique ?">Revenir à l'automatique</button><?php endif; ?>
      </div>
    </form>
    <?php endif; ?>
    <div class="box">
      <h2>Textes personnalisés (<?= count($all) ?>)</h2>
      <?php if (!$all): ?><p class="muted small">Aucun texte personnalisé pour l'instant.</p><?php else: ?>
      <ul class="list-rows"><?php foreach ($all as $t): if (!is_array($t)) { continue; } ?><li><a href="?path=<?= rawurlencode((string) ($t['path'] ?? '')) ?>"><?= e((string) ($t['path'] ?? '?')) ?></a><span class="muted"><?= !empty($t['ai']) ? '✨ IA · ' : '' ?><?= e(ago((string) ($t['updated_at'] ?? ''))) ?></span></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </div>
  <aside>
    <div class="box">
      <h2>Pages prioritaires</h2>
      <p class="small muted">Combinaisons métier × département avec le plus de pros, sans texte personnalisé.</p>
      <ul class="list-rows"><?php foreach ($suggest as $s): ?><li><a href="?path=<?= rawurlencode($s['path']) ?>&amp;label=<?= rawurlencode($s['label']) ?>&amp;count=<?= (int) $s['count'] ?>"><?= e($s['label']) ?></a><b><?= (int) $s['count'] ?></b></li><?php endforeach; ?></ul>
      <form class="form mt-2" method="get"><div class="field"><label for="ld-path">Autre page (adresse)</label><input id="ld-path" type="text" name="path" placeholder="/magicien/gironde-33/bordeaux/"></div><button class="btn btn-xs" type="submit">Ouvrir</button></form>
    </div>
  </aside>
</div>
