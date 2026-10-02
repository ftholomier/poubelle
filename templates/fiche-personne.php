<?php
/**
 * Fiche personne : joueur, entraîneur, dirigeant, personnage emblématique (maquette « Fiche Joueur »).
 * Variables : $doc, $p, $tot, $big, $statsLong, $matches, $playerMatches, $coachMatches, $highlights,
 *             $crumbs, $prev, $next, $roleLabel, $years, $rubric, $related
 */

use App\Core\View;
use App\Front\Site;

$name = $p['display_name'] ?: $doc['title'];
$first = trim((string) ($p['first_name'] ?? ''));
$last = trim((string) ($p['last_name'] ?? ''));
if ($first === '' && $last === '') {
    $parts = preg_split('/\s+/u', $name);
    $last = (string) array_pop($parts);
    $first = implode(' ', $parts);
}
$roles = $p['roles'] ?: ['joueur'];
$isPlayer = in_array('joueur', $roles, true);
$lineLabels = ['G' => t('Gardien'), 'D' => t('Défenseur'), 'M' => t('Milieu'), 'A' => t('Attaquant')];
$cardRole = $isPlayer
    ? ($lineLabels[$p['line'] ?? ''] ?? ($p['position'] ? strtok((string) $p['position'], ' ') : t('Joueur')))
    : $roleLabel;
$caps = null;
foreach ($p['international'] ?? [] as $intl) {
    if (preg_match('/(\d+)\s*s[ée]lection/u', (string) $intl, $mm)) {
        $caps = ($caps ?? 0) + (int) $mm[1];
    }
}
$birth = $p['birth']['date']['text'] ?? '';
if (($p['birth']['date']['precision'] ?? '') === 'day') {
    $birth = date_num($p['birth']['date']['iso']);
}
$cardNo = !empty($p['album']['in']) && !empty($p['album']['number']) ? (string) $p['album']['number'] : null;
$cardRows = array_values(array_filter([
    [t('Poste'), $isPlayer ? ($p['position'] ? ucfirst((string) $p['position']) : $cardRole) : $roleLabel],
    [t('Né le'), $birth],
    [t('Au club'), $years ? str_replace('-', ' – ', $years) : ''],
    $isPlayer ? [t('Matchs'), (string) ($big[0]['v'] ?? '')] : [t('Matchs dirigés'), (string) ($tot['coached'] ?? '')],
    $isPlayer ? [t('Buts'), (string) ($big[1]['v'] ?? '')] : null,
    $caps ? [t('Sélections'), (string) $caps] : null,
    !empty($p['death']['date']['text']) ? [t('Décès'), ($p['death']['date']['precision'] ?? '') === 'day' ? date_num($p['death']['date']['iso']) : $p['death']['date']['text']] : null,
], fn ($r) => $r && $r[1] !== '' && $r[1] !== '0' && $r[1] !== '–'));

// Récit : sections non vides (les sections vides restent modifiables dans le back-office).
$story = array_values(array_filter($doc['sections'] ?? [], fn ($s) => trim(strip_tags((string) $s['html'], '<img><iframe>')) !== ''));
$lead = trim((string) ($doc['intro'] ?? ''));

// Fiche d'identité (ordre et libellés d'origine conservés).
$idRows = [];
foreach ($p['fiche'] ?? [] as $r) {
    $v = trim((string) $r['value']);
    if ($v === '' || (!$r['label'] && mb_strtolower($v) === mb_strtolower($name))) {
        continue;
    }
    $isSub = !$r['label'] && mb_strlen($v) < 40 && preg_match('/passage|p[ée]riode|carri[èe]re|joueur|entra[iî]neur|dirigeant/iu', $v) && !preg_match('/\d/', $v);
    $idRows[] = ['label' => $r['label'], 'value' => $v, 'sub' => $isSub];
}

$playerList = $playerMatches;
$tabs = array_filter([
    'joueur' => $playerMatches ? t('Comme joueur') : null,
    'entraineur' => $coachMatches ? t('Comme entraîneur') : null,
]);
$careerTotals = $tot ? array_values(array_filter([
    $tot['matches'] ? ['v' => $tot['matches'], 'k' => t('matchs')] : null,
    $tot['matches'] ? ['v' => $tot['goals'], 'k' => t('buts')] : null,
    $tot['matches'] && $tot['minutes'] ? ['v' => number_format((int) $tot['minutes'], 0, ',', ' '), 'k' => t('minutes')] : null,
    $tot['yellow'] ? ['v' => $tot['yellow'], 'k' => t('jaunes')] : null,
    $tot['red'] ? ['v' => $tot['red'], 'k' => t('rouges')] : null,
    $tot['coached'] ? ['v' => $tot['coached'], 'k' => t('matchs dirigés')] : null,
])) : [];
$prevLabel = match ($rubric) {
    Site::C_ENTRAINEURS => [t('Entraîneur précédent'), t('Entraîneur suivant')],
    Site::C_DIRIGEANTS => [t('Dirigeant précédent'), t('Dirigeant suivant')],
    Site::C_PERSONNAGES => [t('Personnage précédent'), t('Personnage suivant')],
    default => [t('Joueur précédent'), t('Joueur suivant')],
};
$rowsHtml = function (array $list, bool $coach) {
    ob_start();
    foreach ($list as $i => $x):
        $score = $x['us'] !== null ? ($x['sh'] ? $x['us'] . '-' . $x['them'] : $x['them'] . '-' . $x['us']) : '–';
        ?>
        <tr<?= $i >= 25 ? ' data-more hidden' : '' ?>>
          <td class="date"><a href="<?= e(url($x['path'])) ?>"><?= e(date_num($x['date'])) ?></a></td>
          <td class="m"><a href="<?= e(url($x['path'])) ?>"><b><?= e(trim($x['home'] . ' – ' . $x['away'], ' –') ?: ($x['event'] ?? $x['title'])) ?></b></a><small><?= e($x['label'] ?: $x['comp']) ?><?= !empty($x['round']) ? ' · ' . e($x['round']) : '' ?></small></td>
          <td class="score"><?php if ($x['result']): ?><span class="res res--<?= e($x['result']) ?>"><?= e($x['result']) ?></span> <?php endif; ?><?= e($score) ?><?= !empty($x['extra']) ? ' <small>' . e($x['extra']) . '</small>' : '' ?></td>
          <?php if (!$coach): ?>
          <td class="c"><?= $x['goals'] ? '<b>' . (int) $x['goals'] . '</b>' : '–' ?></td>
          <td class="c"><?= str_repeat('<span class="cardmark cardmark--y"></span>', (int) $x['yellow']) . str_repeat('<span class="cardmark cardmark--r"></span>', (int) $x['red']) ?: '' ?></td>
          <td class="min"><?= $x['minutes'] ? (int) $x['minutes'] . "'" : '' ?><?= $x['pos'] === 'R' ? ' <span class="role">(' . e(t('entré')) . ')</span>' : '' ?><?= $x['captain'] ? ' <span class="role">(c)</span>' : '' ?></td>
          <?php endif; ?>
        </tr>
    <?php endforeach;
    return (string) ob_get_clean();
};
?>
<section class="phero">
  <div class="wrap phero__inner">
    <div class="phero__cardcol">
      <button type="button" class="pcard" data-flip aria-pressed="false" aria-label="<?= e(t('Retourner la carte de {name}', ['name' => $name])) ?>">
        <span class="pcard__in">
          <span class="pcard__face pcard__front">
            <span class="pcard__top">
              <span class="pcard__no"><?= $cardNo ? 'N° ' . e($cardNo) : e($years) ?></span>
              <img src="/assets/img/logo-sochaux-retro.png" alt="" width="42" height="42">
            </span>
            <span class="pcard__photo">
              <?php if (!empty($doc['featured_image'])): ?>
                <img src="<?= e(img($doc['featured_image'], 640)) ?>" srcset="<?= e(srcset($doc['featured_image'], [480, 640, 800])) ?>" sizes="380px" alt="<?= e($name) ?>" fetchpriority="high">
              <?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?>
            </span>
            <span class="pcard__name"><b><?= e($last ?: $name) ?></b><span><?= e(mb_strtoupper((string) $cardRole)) ?></span></span>
          </span>
          <span class="pcard__face pcard__back">
            <b><?= e($name) ?></b>
            <?php foreach ($cardRows as [$k, $v]): ?>
              <span class="pcard__row"><span><?= e($k) ?></span><b><?= e($v) ?></b></span>
            <?php endforeach; ?>
            <span class="pcard__foot"><?= $cardNo ? e(t('Carte n° {n}', ['n' => $cardNo])) . ' · ' : '' ?><?= e(t('Collection Nos Lions')) ?></span>
          </span>
        </span>
      </button>
      <span class="phero__hint" aria-hidden="true">↻ <?= e(t('Cliquez pour retourner la carte')) ?></span>
    </div>
    <div class="phero__text">
      <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>">
        <?php foreach ($crumbs as $c): ?><a href="<?= e($c['href']) ?>"><?= e($c['label']) ?></a><span aria-hidden="true">/</span><?php endforeach; ?>
        <span aria-current="page"><?= e($name) ?></span>
      </nav>
      <span class="phero__kicker"><?= e(t('Nos Lions')) ?> · <?= e($roleLabel) ?><?= $years ? ' · ' . e($years) : '' ?></span>
      <h1 class="phero__name"><?php if ($first !== ''): ?><?= e($first) ?><br><?php endif; ?><span><?= e($last ?: $name) ?></span></h1>
      <?php if (!empty($p['nickname'])): ?><p class="phero__nick">« <?= e($p['nickname']) ?> »</p><?php endif; ?>
      <?php $bigShown = array_values(array_filter($big, fn ($b) => $b['v'] !== '' && $b['v'] !== '–' && $b['v'] !== '0'));
      if (count($bigShown) >= 2): ?>
      <div class="phero__big" style="--n:<?= count($bigShown) ?>">
        <?php foreach ($bigShown as $b): ?><div data-reveal><b data-count><?= e($b['v']) ?></b><span><?= e($b['k']) ?></span></div><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($lead !== ''): ?>
        <div class="phero__lead"><?= safe_html($lead) ?></div>
      <?php elseif (!empty($p['subtitle'])): ?>
        <p class="phero__lead"><?= e($p['subtitle']) ?></p>
      <?php elseif (!empty($p['birth']['text'])): ?>
        <p class="phero__lead"><?= e(ucfirst((string) $p['birth']['text'])) ?>.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="wrap pbody">
  <?php if ($story): ?>
  <div class="pbody__story">
    <?php foreach ($story as $s): ?>
      <section class="fm-sec">
        <?php if (!empty($s['title'])): ?><h2 class="h-section"><?= e($s['title']) ?></h2><?php endif; ?>
        <div class="prose"><?= safe_html($s['html']) ?></div>
      </section>
    <?php endforeach; ?>
    <?= View::partial('partials/embeds', ['embeds' => $doc['embeds'] ?? []]) ?>
  </div>
  <?php endif; ?>
  <div class="pbody__aside"<?= !$story ? ' style="grid-column:1/-1;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));align-items:start"' : '' ?>>
    <?php if ($idRows): ?>
    <div class="idcard">
      <div class="idcard__head"><?= e(t("Fiche d'identité")) ?></div>
      <dl>
        <?php foreach ($idRows as $r): ?>
          <?php if ($r['sub']): ?>
            <div class="idcard__sub"><?= e($r['value']) ?></div>
          <?php elseif ($r['label']): ?>
            <div class="idcard__row"><dt><?= e($r['label']) ?></dt><dd><?= e($r['value']) ?></dd></div>
          <?php else: ?>
            <div class="idcard__line"><?= e($r['value']) ?></div>
          <?php endif; ?>
        <?php endforeach; ?>
      </dl>
    </div>
    <?php endif; ?>
    <div style="display:flex;flex-direction:column;gap:28px">
      <?php if (!empty($doc['key_figure']['number']) || !empty($doc['key_figure']['text'])): ?>
      <div class="keyfig" data-reveal>
        <span class="keyfig__n" data-count><?= e($doc['key_figure']['number']) ?></span>
        <span class="keyfig__t"><?= e($doc['key_figure']['text']) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($p['honours'])): ?>
        <div class="stack"><h2 class="h-3"><?= e(t('Palmarès')) ?></h2><ul class="plist"><?php foreach ($p['honours'] as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <?php if (!empty($p['then'])): ?>
        <div class="stack"><h2 class="h-3"><?= e(t('Après Sochaux')) ?></h2><ul class="plist"><?php foreach ($p['then'] as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <?php if (!$story): ?><?= View::partial('partials/embeds', ['embeds' => $doc['embeds'] ?? []]) ?><?php endif; ?>
    </div>
  </div>
</div>

<?php if ($statsLong || $highlights): ?>
<section class="wrap pstats">
  <?php if ($statsLong && $statsLong['rows']): ?>
  <div class="pstats__col" data-statsbox>
    <h2 class="h-section"><?= e($statsLong['raw']['title'] && !preg_match('/^statistiques?$/iu', (string) $statsLong['raw']['title']) ? $statsLong['raw']['title'] : t('Saison par saison')) ?></h2>
    <div class="dtable-wrap" data-stats-long>
      <table class="dtable stable">
        <thead><tr><th scope="col"><?= e(t('Saison')) ?></th><th scope="col"><?= e(t('Compétition')) ?></th><th scope="col" class="n"><?= e(t('MJ')) ?></th><th scope="col" class="n"><?= e(t('Buts')) ?></th></tr></thead>
        <tbody>
          <?php $lastSeason = null; foreach ($statsLong['rows'] as $r): $newSeason = $r['season'] !== $lastSeason; $lastSeason = $r['season']; ?>
          <tr class="<?= $r['total'] ? 'total' : '' ?><?= $newSeason ? ' is-first' : '' ?>">
            <td class="season"><?= $newSeason ? e($r['season']) : '' ?></td>
            <td><?= e($r['comp']) ?></td>
            <td class="n"><?= e($r['mj']) ?></td>
            <td class="n"><?= e($r['g']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="dtable-wrap" data-stats-raw hidden>
      <?= View::partial('partials/table', ['table' => $statsLong['raw']]) ?>
    </div>
    <span class="pstats__note"><?= e(t('MJ : matchs joués.')) ?> <button type="button" class="ptoggle" data-stats-toggle data-alt="<?= e(t('Vue saison par saison')) ?>"><?= e(t("Voir le tableau d'origine")) ?></button></span>
  </div>
  <?php endif; ?>
  <?php if ($highlights): ?>
  <div class="pstats__col">
    <h2 class="h-section"><?= e(t('Ses matchs marquants')) ?></h2>
    <?php foreach ($highlights as $h): $x = $h['m'] ?? null; $s = $x ? \App\Data\Index::get((int) $x['id']) : null; ?>
      <<?= $x ? 'a href="' . e(url($x['path'])) . '"' : 'div' ?> class="hlmatch" data-reveal>
        <span class="hlmatch__img"><?php if ($s && $s['image']): ?><img src="<?= e(img($s['image'], 160)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></span>
        <span class="hlmatch__txt">
          <span class="hlmatch__meta"><?= e($h['label']) ?><?= $x ? ' · ' . e($x['label'] ?: $x['comp']) . ' · ' . e(date_num($x['date'])) : '' ?></span>
          <span class="hlmatch__t"><?= e($x ? Site::matchLabel($x) : ($h['text'] ?? '')) ?></span>
          <?php if ($x && !empty($h['text']) && preg_match('/but\s+à\s+la\s+\d+/iu', (string) $h['text'], $bm)): ?><span class="hlmatch__x"><?= e(ucfirst($bm[0])) ?>'</span><?php endif; ?>
        </span>
        <?php if ($x): ?><span class="hlmatch__go" aria-hidden="true">→</span><?php endif; ?>
      </<?= $x ? 'a' : 'div' ?>>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($tabs): ?>
<section class="wrap pmatches" id="matchs">
  <div class="pmatches__head">
    <div class="stack" style="gap:6px">
      <h2 class="h-section"><?= e($isPlayer || !$coachMatches ? t('Tous ses matchs') : t('Ses matchs sur le banc')) ?></h2>
      <span class="muted"><?= e(t('Reliés automatiquement depuis les compositions des fiches matchs.')) ?></span>
    </div>
    <?php if ($careerTotals): ?>
    <div class="pmatches__totals">
      <?php foreach ($careerTotals as $c): ?><div><b><?= e($c['v']) ?></b><span><?= e($c['k']) ?></span></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if (count($tabs) > 1): ?>
  <div class="chips" role="tablist">
    <?php $i = 0; foreach ($tabs as $k => $label): ?>
      <button type="button" class="chip<?= $i++ === 0 ? ' is-on' : '' ?>" role="tab" data-mtab="<?= e($k) ?>"><?= e($label) ?></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php foreach (['joueur' => $playerMatches, 'entraineur' => $coachMatches] as $k => $list): if (!$list) continue; $coach = $k === 'entraineur'; ?>
  <div class="dtable-wrap" data-mpanel="<?= e($k) ?>"<?= $k === 'entraineur' && $playerMatches ? ' hidden' : '' ?>>
    <table class="dtable mtable">
      <thead><tr>
        <th scope="col"><?= e(t('Date')) ?></th><th scope="col"><?= e(t('Match')) ?></th><th scope="col"><?= e(t('Score')) ?></th>
        <?php if (!$coach): ?><th scope="col" class="c"><?= e(t('Buts')) ?></th><th scope="col" class="c"><?= e(t('Cartons')) ?></th><th scope="col" class="min"><?= e(t('Min.')) ?></th><?php endif; ?>
      </tr></thead>
      <tbody><?= $rowsHtml($list, $coach) ?></tbody>
    </table>
    <?php if (count($list) > 25): ?>
      <button type="button" class="btn btn--navy btn--sm pmatches__more" data-more-btn><?= e(t('Afficher les {n} autres matchs', ['n' => count($list) - 25])) ?></button>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php $videosHtml = View::partial('partials/videos', ['videos' => $doc['videos'] ?? [], 'anchor' => 'video']); if (trim($videosHtml) !== ''): ?>
<div class="wrap" style="padding-bottom:clamp(48px,6vw,88px)"><?= $videosHtml ?></div>
<?php endif; ?>

<?= View::partial('partials/gallery', ['items' => $doc['gallery'] ?? [], 'title' => t('Galerie'), 'anchor' => 'galerie', 'context' => $name]) ?>

<?php if (!empty($doc['tables'])): ?>
<section class="wrap section fm-tables">
  <?php foreach ($doc['tables'] as $tb): ?>
    <?php if (!empty($tb['title'])): ?><h2 class="h-2"><?= e($tb['title']) ?></h2><?php endif; ?>
    <?= View::partial('partials/table', ['table' => $tb]) ?>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?= View::partial('partials/related', ['related' => $related]) ?>

<?php if ($prev || $next): ?>
<nav class="prevnext" aria-label="<?= e(t('Navigation entre les fiches')) ?>">
  <div class="wrap prevnext__inner">
    <?php if ($prev): ?><a href="<?= e(url($prev['path'])) ?>" rel="prev"><span>← <?= e($prevLabel[0]) ?></span><b><?= e($prev['p']['name'] ?? $prev['title']) ?></b></a><?php else: ?><span></span><?php endif; ?>
    <?php if ($rubric): ?><a class="prevnext__mid" href="<?= e(Site::catUrl($rubric)) ?>"><?= e(t(\App\Data\Categories::label($rubric))) ?></a><?php else: ?><span></span><?php endif; ?>
    <?php if ($next): ?><a href="<?= e(url($next['path'])) ?>" rel="next" class="prevnext__next"><span><?= e($prevLabel[1]) ?> →</span><b><?= e($next['p']['name'] ?? $next['title']) ?></b></a><?php else: ?><span></span><?php endif; ?>
  </div>
</nav>
<?php endif; ?>
