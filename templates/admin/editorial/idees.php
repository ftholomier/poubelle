<?php
/**
 * Boîte à idées des 100 moments. Variables : $list (idées affichées, avec sources_info, suggest,
 * fiche_info), $counts (par état), $total, $state, $decade, $coverage, $runs, $ready (IA disponible)
 */
use App\Services\MomentIdeas as Ideas;

$pills = ['proposee' => ['Proposée', 'info'], 'retenue' => ['Retenue', 'ok'], 'redigee' => ['Rédigée', 'navy'], 'ecartee' => ['Écartée', 'ko']];
$types = ['match' => 'Match', 'personne' => 'Personne', 'article' => 'Article', 'page' => 'Page', 'objet' => 'Objet'];
$qs = fn (array $over): string => '/admin/moments/idees' . (($p = array_filter(array_merge(['etat' => $state === 'a-trier' ? null : $state, 'decennie' => $decade], $over), fn ($v) => $v !== null && $v !== '')) ? '?' . http_build_query($p) : '');
$maxCov = max(1, ...array_map(fn ($c) => max($c['ideas'], $c['dated']), $coverage));
$active = $counts['proposee'] + $counts['retenue'];
?>
<p class="small" style="margin:0;max-width:100ch">
  <b>1.</b> L’IA lit les fiches publiées du musée et propose des idées de moments, chacune appuyée sur ses fiches sources (une idée sans source est écartée d’office : elle n’invente rien).
  <b>2.</b> Vous retenez, modifiez ou écartez (votre raison lui est rappelée), et ajoutez vos propres idées.
  <b>3.</b> Sur une idée retenue, <b>Premier jet</b> : l’IA écrit le récit d’après les seules fiches sources et crée la fiche « À relire », avec les points à vérifier.
  <b>4.</b> Vous relisez, corrigez, et choisissez la date de parution : c’est la validation. L’IA ne date ni ne publie jamais rien, et rien de tout cela n’est indiqué sur le site.
</p>

<section class="card card--pad" id="ia" data-ideas-tools>
  <div class="row" style="gap:10px;align-items:flex-end">
    <button type="button" class="btn btn--navy" data-idea-act="sommaire"<?= $ready ? '' : ' disabled' ?> title="L’IA lit le catalogue du musée, époque par époque (une minute environ)"><?= $total ? 'Proposer d’autres idées (IA)' : 'Proposer le sommaire (IA)' ?></button>
    <form class="row grow" style="gap:8px;flex-wrap:nowrap;min-width:280px" data-idea-piste>
      <input type="text" name="piste" class="in" maxlength="200" placeholder="Une piste précise : « les années 1930 », « les supporters », « Bernard Genghini »…" aria-label="Piste à explorer"<?= $ready ? '' : ' disabled' ?>>
      <button type="submit" class="btn"<?= $ready ? '' : ' disabled' ?>>Demander</button>
    </form>
    <button type="button" class="btn btn--yellow" data-idea-new>+ Ajouter une idée</button>
  </div>
  <?php if (!$ready): ?><p class="alert" style="margin:10px 0 0">Aucune clé API Gemini : l’IA ne peut rien proposer (Système › Réglages › Intelligence artificielle). Vous pouvez ajouter vos idées.</p><?php endif; ?>
  <?php if ($runs): ?>
    <p class="xs muted" style="margin:10px 0 0">Dernières demandes : <?= implode(' · ', array_map(fn ($r) => e(date_num(substr((string) $r['at'], 0, 10))) . ' ' . e(['sommaire' => 'sommaire', 'piste' => 'piste « ' . $r['request'] . ' »', 'autre' => 'autre idée'][$r['kind']] ?? $r['kind']) . ' : ' . (int) $r['added'] . ' idée' . ((int) $r['added'] > 1 ? 's' : '') . ' (' . e((string) $r['by']) . ')', $runs)) ?></p>
  <?php endif; ?>
</section>

<section class="card" id="couverture">
  <div class="card__head"><h2 class="card__t">Couverture par décennie</h2><span class="card__note">idées en cours (hors écartées) et moments datés : les trous se voient</span></div>
  <div class="table" style="border:0">
    <table style="min-width:0">
      <thead><tr><th>Décennie</th><th class="right">Idées</th><th class="right">Retenues</th><th class="right">Rédigées</th><th class="right">Datées</th><th style="width:40%"></th></tr></thead>
      <tbody>
      <?php foreach ($coverage as $d => $c): ?>
        <tr>
          <td><a class="rowlink" href="<?= e($qs(['decennie' => $decade === $d ? null : $d, 'etat' => 'toutes'])) ?>">Années <?= (int) $d ?></a></td>
          <td class="right t-num"><?= (int) $c['ideas'] ?></td>
          <td class="right t-num"><?= (int) $c['kept'] ?></td>
          <td class="right t-num"><?= (int) $c['written'] ?></td>
          <td class="right t-num"><?= (int) $c['dated'] ?></td>
          <td><span class="meter meter--y" title="<?= (int) $c['ideas'] ?> idée(s)"><i style="width:<?= round(100 * $c['ideas'] / $maxCov) ?>%"></i></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="chips">
  <a class="chip<?= $state === 'a-trier' ? ' is-on' : '' ?>" href="<?= e($qs(['etat' => null])) ?>">À trier <em>· <?= (int) $active ?></em></a>
  <?php foreach (Ideas::STATES as $k => $l): ?><a class="chip<?= $state === $k ? ' is-on' : '' ?>" href="<?= e($qs(['etat' => $k])) ?>"><?= e($l) ?>s <em>· <?= (int) ($counts[$k] ?? 0) ?></em></a><?php endforeach; ?>
  <a class="chip<?= $state === 'toutes' ? ' is-on' : '' ?>" href="<?= e($qs(['etat' => 'toutes'])) ?>">Toutes <em>· <?= (int) $total ?></em></a>
  <?php if ($decade !== null): ?><a class="chip is-on" href="<?= e($qs(['decennie' => null])) ?>">Années <?= (int) $decade ?> ✕</a><?php endif; ?>
</div>

<div class="ideas" data-ideas>
  <?php foreach ($list as $i): $f = $i['fiche_info']; [$pl, $pc] = $pills[$i['state']] ?? ['', 'info']; ?>
    <article class="idea is-<?= e($i['state']) ?>" data-idea="<?= e(json_encode(['id' => $i['id'], 'title' => $i['title'], 'year' => $i['year'], 'date' => $i['date'], 'why' => $i['why'], 'theme' => $i['theme'], 'image' => $i['image'], 'sources' => array_map(fn ($s) => ['id' => $s['id'], 'title' => $s['title']], $i['sources_info'])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
      <div class="idea__img"><?php if ($i['image']): ?><img src="<?= e(img($i['image'], 480)) ?>" alt="" loading="lazy"><?php if (($cr = trim((string) (\App\Data\Media::get($i['image'])['credit'] ?? ''))) !== ''): ?><small>© <?= e($cr) ?></small><?php endif; ?><?php endif; ?></div>
      <div class="idea__body">
        <div class="idea__meta"><span class="pill pill--<?= e($pc) ?>"><?= e($pl) ?></span><span class="pill"><?= e(Ideas::THEMES[$i['theme']] ?? 'Autre') ?></span><b><?= $i['date'] ? e(date_num($i['date'])) : ($i['year'] ? (int) $i['year'] : 'année ?') ?></b><span class="xs muted"><?= $i['origin'] === 'ia' ? 'proposée par l’IA' . ($i['request'] !== '' ? ' (piste « ' . e($i['request']) . ' »)' : '') : 'ajoutée par ' . e((string) $i['by']) ?></span></div>
        <h3 class="idea__t"><?= e($i['title']) ?></h3>
        <?php if ($i['why'] !== ''): ?><p class="idea__why"><?= e($i['why']) ?></p><?php endif; ?>
        <?php if ($i['sources_info']): ?>
          <ul class="idea__src"><?php foreach ($i['sources_info'] as $s): ?><li><span class="xs muted"><?= e($types[$s['type']] ?? $s['type']) ?></span> <a href="/admin/fiche/<?= (int) $s['id'] ?>" target="_blank" rel="noopener"><?= e($s['title']) ?></a></li><?php endforeach; ?></ul>
        <?php else: ?><p class="xs warn" style="margin:0">Aucune fiche source : ajoutez-en une avant de demander un premier jet.</p><?php endif; ?>
        <?php if ($i['suggest'] && $i['state'] !== 'ecartee' && !($f && $f['date'] !== null)): ?><p class="xs muted" style="margin:0">Date anniversaire : <?= e(date_fr($i['suggest']['date'])) ?> (<?= (int) $i['suggest']['years'] ?> ans<?= $i['suggest']['taken'] ? ', jour déjà pris' : '' ?>)</p><?php endif; ?>
        <?php if ($i['state'] === 'ecartee' && $i['reason'] !== ''): ?><p class="xs" style="margin:0"><b>Écartée :</b> <?= e($i['reason']) ?></p><?php endif; ?>
        <?php if ($f): ?><p class="small" style="margin:0">Fiche : <a href="/admin/fiche/<?= (int) $f['id'] ?>"><?= e($f['title']) ?></a> · <?= $f['date'] !== null ? ($f['visible'] ? 'en ligne' : 'planifiée le ' . e(date_num(substr($f['date'], 0, 10)))) . ($f['number'] ? ' (n° ' . (int) $f['number'] . ')' : '') : ($f['status'] === 'relire' ? 'à relire' : 'brouillon') ?></p><?php endif; ?>
      </div>
      <div class="idea__acts">
        <?php if ($i['state'] === 'proposee'): ?>
          <button type="button" class="btn btn--sm btn--navy" data-idea-act="retenir">Retenir</button>
          <button type="button" class="btn btn--sm" data-idea-edit>Modifier</button>
          <button type="button" class="btn btn--sm" data-idea-act="autre"<?= $ready ? '' : ' disabled' ?> title="Écarte cette idée et en demande une autre de la même époque">Autre idée</button>
          <button type="button" class="btn btn--sm btn--ghost" data-idea-act="ecarter">Écarter</button>
        <?php elseif ($i['state'] === 'retenue'): ?>
          <button type="button" class="btn btn--sm btn--navy" data-idea-act="rediger"<?= $ready && $i['sources_info'] ? '' : ' disabled' ?> title="L’IA rédige le récit d’après les fiches sources et crée la fiche « À relire » (une demi-minute environ)">Premier jet (IA)</button>
          <a class="btn btn--sm" href="/admin/fiche/nouvelle/moment?idee=<?= e($i['id']) ?>" title="Écrire le récit vous-même, la fiche préremplie">Écrire moi-même</a>
          <button type="button" class="btn btn--sm" data-idea-edit>Modifier</button>
          <button type="button" class="btn btn--sm btn--ghost" data-idea-act="ecarter">Écarter</button>
        <?php elseif ($i['state'] === 'redigee'): ?>
          <?php if ($f): ?><a class="btn btn--sm btn--navy" href="/admin/fiche/<?= (int) $f['id'] ?>">Ouvrir la fiche</a><?php else: ?><span class="xs muted">Fiche mise à la corbeille.</span> <button type="button" class="btn btn--sm" data-idea-act="retenir">Retenir à nouveau</button><?php endif; ?>
        <?php else: ?>
          <button type="button" class="btn btn--sm" data-idea-act="proposer">Remettre dans les propositions</button>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php if (!$list): ?><div class="empty"><?= $total ? 'Aucune idée pour ce choix.' : 'Aucune idée pour l’instant : demandez le sommaire à l’IA, ou ajoutez la vôtre.' ?></div><?php endif; ?>
<script type="application/json" id="ideas-conf"><?= json_encode(['themes' => Ideas::THEMES], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
