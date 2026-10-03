<?php
/**
 * Rétro-Direct : un match rejoué en direct (mode live), annoncé (upcoming) ou à revivre en accéléré (replay).
 * Variables : $doc, $m, $s, $entry, $mode, $tl, $data, $rows, $pitch, $ago, $sameDay, $intro, $when,
 *             $gallery, $stats, $etais, $home, $away, $title
 */
use App\Services\RetroDirect;

$sh = !empty($m['sochaux_home']);
$compLabel = $m['competition_label'] ?: ($m['competition'] ?? '');
$round = trim((string) ($m['round_text'] ?? ''));
if ($round !== '' && !empty($m['competition_code'])) {
    $round = trim(preg_replace('/\s+(?:de|du|des|en)\s+' . preg_quote((string) $m['competition_code'], '/') . '$/u', '', $round));
}
$tag = trim($compLabel . ($round !== '' && mb_strtolower($round) !== mb_strtolower($compLabel) ? ' · ' . $round : ''), ' ·');
$agoTxt = $ago > 0 ? ($sameDay ? tn($ago, 'Il y a {n} an jour pour jour', 'Il y a {n} ans jour pour jour') : tn($ago, 'Il y a {n} an', 'Il y a {n} ans')) : '';
$facts = array_values(array_filter([
    ['k' => t('Date'), 'v' => $m['date'] ? date_fr($m['date'], true) : ''],
    ['k' => t('Stade'), 'v' => (string) ($m['stadium'] ?? '')],
    ['k' => t('Spectateurs'), 'v' => !empty($m['spectators']) ? number_format((int) $m['spectators'], 0, ',', ' ') : ''],
], fn ($f) => $f['v'] !== ''));
$starters = array_values(array_filter($rows, fn ($r) => in_array($r['position'], ['G', 'D', 'M', 'A'], true)));
$bench = array_values(array_filter($rows, fn ($r) => $r['position'] === 'R'));
$coach = array_values(array_filter($rows, fn ($r) => $r['position'] === 'E'));
$badge = ['live' => t('En direct'), 'upcoming' => t('Bientôt en direct'), 'replay' => t('En accéléré')][$mode];
$fiche = url($s['path']);
$shareText = $mode === 'upcoming'
    ? t('{match} rejoué en direct {when} sur le site du musée Sochaux Rétro', ['match' => $title, 'when' => $when])
    : t('Je revis {match} minute par minute sur le site du musée Sochaux Rétro', ['match' => $title]);
$clean = fn (string $x): string => trim(preg_replace(['/^\s*[«"“]\s*/u', '/\s*[»"”]\s*([.!?…]?)\s*$/u'], ['', '$1'], $x));
?>
<section class="mhero rdhero" data-rd data-mode="<?= e($mode) ?>">
  <script type="application/json" data-rd-data><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <?php if (!empty($doc['featured_image'])): ?>
  <div class="mhero__bg"><img src="<?= e(img($doc['featured_image'], 1600)) ?>" srcset="<?= e(srcset($doc['featured_image'], [800, 1200, 1600])) ?>" sizes="100vw" alt="" fetchpriority="high"></div>
  <?php endif; ?>
  <div class="mhero__shade rdhero__shade">
    <div class="wrap mhero__inner">
      <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>">
        <a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span>
        <a href="<?= e(url('/interactif/')) ?>"><?= e(t('Interactif')) ?></a><span aria-hidden="true">/</span>
        <a href="<?= e(url('/interactif/retro-direct/')) ?>"><?= e(t('Rétro-Direct')) ?></a><span aria-hidden="true">/</span>
        <span aria-current="page"><?= e($m['date'] ? date_num($m['date']) : $title) ?></span>
      </nav>
      <div class="rdstatus">
        <span class="rdbadge rdbadge--<?= e($mode) ?>" data-rd-badge><?php if ($mode === 'live'): ?><span class="rdlive-dot" aria-hidden="true"></span> <?php endif; ?><?= e($badge) ?></span>
        <span class="rdclock" data-rd-clock aria-hidden="true"></span>
        <span class="rdphase" data-rd-phase></span>
      </div>
      <?php if ($tag !== '' || $agoTxt !== ''): ?><span class="mhero__tag"><?= e(trim($agoTxt . ' · ' . $tag, ' ·')) ?></span><?php endif; ?>
      <h1 class="scoreboard">
        <span class="scoreboard__team scoreboard__team--home<?= $sh ? ' is-sochaux' : '' ?>"><span class="scoreboard__name"><?= e($home) ?></span></span>
        <span class="scoreboard__score rdscore" data-rd-score>
          <span class="<?= $sh ? 'is-sochaux' : '' ?>" data-rd-s="0">–</span><span class="scoreboard__dash" aria-hidden="true">–</span><span class="<?= !$sh ? 'is-sochaux' : '' ?>" data-rd-s="1">–</span>
        </span>
        <span class="scoreboard__team scoreboard__team--away<?= !$sh ? ' is-sochaux' : '' ?>"><span class="scoreboard__name"><?= e($away) ?></span></span>
      </h1>
      <p class="mhero__extra" data-rd-extra hidden></p>
      <p class="sr-only" aria-live="polite" data-rd-say></p>
      <div class="rdbar" data-rd-bar hidden><span class="rdbar__fill" data-rd-bar-fill></span></div>
      <?php if ($facts): ?>
      <dl class="mhero__facts">
        <?php foreach ($facts as $f): ?><div><dt><?= e($f['k']) ?></dt><dd><?= e($f['v']) ?></dd></div><?php endforeach; ?>
      </dl>
      <?php endif; ?>

      <?php if ($mode === 'live'): ?>
      <div class="rdlive" data-rd-live>
        <span class="rdlive__viewers" data-rd-viewers><?= e($stats && $stats['viewers'] ? t($stats['viewers'] > 1 ? '{n} personnes suivent le direct' : '{n} personne suit le direct', ['n' => $stats['viewers']]) : '') ?></span>
        <div class="rdreact" role="group" aria-label="<?= e(t('Réagir')) ?>">
          <?php foreach (RetroDirect::REACTIONS as $k => $emoji): ?>
            <button type="button" class="rdreact__b" data-rd-react="<?= e($k) ?>" aria-label="<?= e(['but' => t('Réagir : but !'), 'bravo' => t('Réagir : bravo'), 'wow' => t('Réagir : incroyable')][$k]) ?>"><span aria-hidden="true"><?= $emoji ?></span> <b data-rd-count="<?= e($k) ?>"><?= (int) ($stats['reactions'][$k] ?? 0) ?></b></button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="wrap rdgridm">
  <div class="rdmain">
    <?php if ($mode === 'upcoming'): ?>
    <div class="rdcount" data-rd-countdown>
      <span class="rdcount__k"><?= e(t('Coup d’envoi')) ?> <?= e($when) ?></span>
      <span class="rdcount__v" data-rd-countdown-v aria-live="off"></span>
      <div class="row" style="gap:12px;flex-wrap:wrap">
        <a class="btn btn--navy" href="<?= e(url('/interactif/retro-direct/agenda.ics') . '?' . http_build_query(['match' => $s['id'], 'date' => $entry['date']])) ?>"><?= e(t('Ajouter à mon agenda')) ?></a>
        <button type="button" class="btn btn--ghost" data-share data-share-title="<?= e(t('Rétro-Direct') . ' : ' . $title) ?>" data-share-text="<?= e($shareText) ?>"><?= e(t('Prévenir mes proches')) ?></button>
      </div>
      <p class="rdcount__note"><?= e(t('Gardez cette page ouverte : le match commencera tout seul à l’heure du coup d’envoi.')) ?></p>
    </div>
    <?php elseif ($mode === 'replay'): ?>
    <div class="rdctrl" data-rd-controls>
      <button type="button" class="qbtn qbtn--sm rdctrl__play" data-rd-play><?= e(t('Lancer le match')) ?></button>
      <div class="rdctrl__speeds" role="radiogroup" aria-label="<?= e(t('Vitesse')) ?>">
        <?php foreach ([1, 10, 60] as $x): ?>
          <button type="button" role="radio" class="rdctrl__speed" data-rd-speed="<?= $x ?>" aria-checked="<?= $x === 10 ? 'true' : 'false' ?>">×<?= $x ?></button>
        <?php endforeach; ?>
      </div>
      <button type="button" class="linkbtn" data-rd-next disabled><?= e(t('Temps fort suivant')) ?> ⏭</button>
      <button type="button" class="linkbtn" data-rd-restart hidden><?= e(t('Revoir depuis le début')) ?></button>
      <p class="rdctrl__note"><?= e(t('×10 : le match en 10 minutes ; ×60 : en moins de 2 minutes. Le score s’affiche au fil des buts.')) ?></p>
    </div>
    <?php endif; ?>

    <section class="rdfeedsec" aria-labelledby="rd-feed-t">
      <h2 class="h-section" id="rd-feed-t"><?= e($mode === 'upcoming' ? t('Avant-match') : t('Le match minute par minute')) ?></h2>
      <ol class="rdfeed" data-rd-feed aria-label="<?= e(t('Temps forts, du plus récent au plus ancien')) ?>"></ol>
      <p class="rdfeed__wait" data-rd-wait<?= $mode === 'upcoming' ? '' : ' hidden' ?>><?= e($mode === 'replay' ? t('Le match n’a pas commencé : lancez-le quand vous voulez.') : t('Les temps forts s’afficheront ici, minute par minute.')) ?></p>

      <div class="rdpre">
        <?php if ($intro !== ''): ?><p class="rdpre__intro"><?= e($intro) ?></p><?php endif; ?>
        <?php if (!empty($m['breves'])): ?>
          <h3 class="h-3"><?= e(t('Avant le coup d’envoi')) ?></h3>
          <ul class="rdpre__breves">
            <?php foreach ($m['breves'] as $b): ?><li><?= rich_inline(is_array($b) ? ($b['text'] ?? '') : (string) $b) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>

    <section class="rdafter" data-rd-after hidden aria-labelledby="rd-after-t">
      <h2 class="h-section" id="rd-after-t"><?= e(t('Après le match')) ?></h2>
      <?php if (!empty($m['reactions'])): ?>
        <div class="quotes">
          <?php foreach ($m['reactions'] as $q): ?>
          <figure class="quote">
            <span class="quote__mark" aria-hidden="true">«</span>
            <blockquote><?= str_contains((string) $q['text'], '<') ? $clean(rich_inline((string) $q['text'])) : nl2br(e($clean((string) $q['text']))) ?></blockquote>
            <?php if (!empty($q['who'])): ?><figcaption><?= e($q['who']) ?></figcaption><?php endif; ?>
          </figure>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="row" style="gap:12px;flex-wrap:wrap">
        <a class="btn btn--navy" href="<?= e($fiche) ?>"><?= e(t('La fiche complète du match')) ?></a>
        <a class="btn btn--ghost" href="<?= e(url('/interactif/retro-direct/')) ?>"><?= e(t('Les autres directs')) ?></a>
      </div>
    </section>
  </div>

  <aside class="rdside">
    <div class="rdetais" data-rd-etais-box>
      <h2 class="h-3"><?= e(t('Vous étiez au stade ?')) ?></h2>
      <p class="rdetais__n" data-rd-etais-n><?= $etais ? e($etais > 1 ? t('{n} supporters y étaient', ['n' => $etais]) : t('{n} supporter y était', ['n' => $etais])) : e(t('Soyez le premier à le dire !')) ?></p>
      <button type="button" class="btn btn--yellow" data-rd-etais data-id="<?= (int) $s['id'] ?>"><?= e(t('J’y étais !')) ?></button>
      <p class="rdetais__more" data-rd-etais-more hidden><?= e(t('Merci ! Une photo, un billet, un souvenir de ce match ?')) ?> <a href="<?= e(url('/contribuer/') . '?' . http_build_query(['fiche' => $title . ' (' . date_num($m['date'] ?? '') . ')', 'type' => 'temoignage'])) ?>"><?= e(t('Racontez-le au musée')) ?></a></p>
    </div>

    <?php if ($starters): ?>
    <div class="compo rdcompo">
      <div class="compo__head">
        <h2 class="h-2"><?= e(t('Composition')) ?></h2>
        <?php if ($pitch): ?><span class="compo__formation"><?= e($pitch['formation']) ?></span><?php endif; ?>
      </div>
      <?php if ($pitch): ?>
      <div class="pitch" role="group" aria-label="<?= e(t('Composition de Sochaux sur le terrain')) ?>">
        <span class="pitch__line pitch__line--box"></span><span class="pitch__line pitch__line--half"></span><span class="pitch__line pitch__line--circle"></span><span class="pitch__line pitch__line--area-b"></span><span class="pitch__line pitch__line--area-t"></span>
        <?php foreach ($pitch['players'] as $p): ?>
          <<?= $p['href'] ? 'a href="' . e($p['href']) . '"' : 'span' ?> class="pitch__p" style="left:<?= $p['x'] ?>%;top:<?= $p['y'] ?>%">
            <span class="pitch__num"><?= (int) $p['num'] ?></span>
            <span class="pitch__name"><?= e($p['short']) ?><?= !empty($p['captain']) ? ' (c)' : '' ?></span>
          </<?= $p['href'] ? 'a' : 'span' ?>>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <ul class="rdcompo__list"><?php foreach ($starters as $r): ?><li><?= $r['href'] ? '<a href="' . e($r['href']) . '">' . e($r['display']) . '</a>' : e($r['display']) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if ($bench): ?><p class="rdcompo__more"><b><?= e(t('Remplaçants')) ?> :</b> <?= implode(', ', array_map(fn ($r) => $r['href'] ? '<a href="' . e($r['href']) . '">' . e($r['display']) . '</a>' : e($r['display']), $bench)) ?></p><?php endif; ?>
      <?php if ($coach): ?><p class="rdcompo__more"><b><?= e(t('Entraîneur')) ?> :</b> <?= implode(', ', array_map(fn ($r) => $r['href'] ? '<a href="' . e($r['href']) . '">' . e($r['display']) . '</a>' : e($r['display']), $coach)) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="rdmore">
      <button type="button" class="btn btn--shadow btn--block" data-share data-share-title="<?= e(t('Rétro-Direct') . ' : ' . $title) ?>" data-share-text="<?= e($shareText) ?>"><?= e(t('Partager')) ?></button>
      <a class="rdmore__fiche" href="<?= e($fiche) ?>"><?= e(t('La fiche du match')) ?> →</a>
      <?php if ($mode !== 'replay'): ?><span class="rdmore__warn"><?= e(t('Attention : elle donne le score final.')) ?></span><?php endif; ?>
    </div>
  </aside>
</div>

<?php if ($gallery): ?>
<template data-rd-photos>
  <div class="rdphotos">
    <?php foreach ($gallery as $g): ?>
      <figure><img src="<?= e(img($g['image'], 480)) ?>" alt="<?= e((string) ($g['caption'] ?? '')) ?>" loading="lazy" decoding="async"><?php if (!empty($g['caption'])): ?><figcaption><?= e((string) $g['caption']) ?></figcaption><?php endif; ?></figure>
    <?php endforeach; ?>
  </div>
</template>
<?php endif; ?>
