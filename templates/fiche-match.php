<?php
/**
 * Fiche match (maquette « Fiche Match »).
 * Variables : $doc, $m, $d, $title, $rows, $pitch, $resume, $crumbs, $h2h, $related, $adjacent
 */

use App\Core\View;
use App\Front\Fiche;

$sh = (bool) ($m['sochaux_home'] ?? false);
$home = $m['home'] ?? ['name' => '', 'level' => null];
$away = $m['away'] ?? ['name' => '', 'level' => null];
$hasTeams = ($home['name'] ?? '') !== '' && ($away['name'] ?? '') !== '';
$hasScore = isset($m['score']['home'], $m['score']['away']);
$compLabel = $m['competition_label'] ?: ($m['competition'] ?? '');
$round = trim((string) ($m['round_text'] ?? ''));
if ($round !== '' && !empty($m['competition_code'])) {
    $round = trim(preg_replace('/\s+(?:de|du|des|en)\s+' . preg_quote((string) $m['competition_code'], '/') . '$/u', '', $round));
}
$tag = trim($compLabel . ($round !== '' && mb_strtolower($round) !== mb_strtolower($compLabel) ? ' · ' . $round : ''), ' ·');
$level = function (array $team) use ($m): string {
    if (!empty($team['level'])) {
        return (string) $team['level'];
    }
    return ($m['competition'] ?? '') === 'Championnat' ? (string) ($m['competition_label'] ?? '') : '';
};
$extra = [];
if (!empty($m['score']['aet'])) {
    $extra[] = t('après prolongation');
}
if (!empty($m['score']['pens'])) {
    $pp = $m['score']['pens'];
    $extra[] = t('tirs au but') . ' : ' . (is_array($pp) ? ($pp['home'] ?? '?') . '-' . ($pp['away'] ?? '?') : $pp);
} elseif (!empty($m['score']['extra']) && !$m['score']['aet']) {
    $extra[] = $m['score']['extra'];
}
$facts = array_values(array_filter([
    ['k' => t('Date'), 'v' => $m['date_text'] ?: ($m['date'] ? date_fr($m['date'], true) : '')],
    ['k' => t('Stade'), 'v' => (string) ($m['stadium'] ?? '')],
    ['k' => t('Spectateurs'), 'v' => !empty($m['spectators']) ? number_format((int) $m['spectators'], 0, ',', ' ') : trim(preg_replace('/\s*spectateurs?\s*$/iu', '', (string) ($m['spectators_text'] ?? '')))],
    ['k' => t('Arbitre'), 'v' => (string) ($m['referee'] ?? '')],
], fn ($f) => $f['v'] !== ''));

// Sections du récit (Avant-match, Résumé, Réactions, Brèves, autres).
$anchors = [];
$timelineDone = false;
$sectionsOut = [];
foreach ($resume['sections'] as $s) {
    $html = trim((string) $s['html']);
    $key = $s['key'];
    $id = match ($key) {
        'avant' => 'avant', 'resume' => 'resume', 'reactions' => 'reactions', 'breves' => 'breves', default => null,
    };
    $hasStruct = match ($key) {
        'resume' => !empty($m['highlights']),
        'reactions' => !empty($m['reactions']),
        'breves' => !empty($m['breves']),
        default => false,
    };
    if ($html === '' && !$hasStruct) {
        continue;
    }
    if ($id && isset($anchors[$id])) {
        $id = null;
    }
    if ($id) {
        $anchors[$id] = match ($key) {
            'avant' => t('Avant-match'), 'resume' => t('Résumé'), 'reactions' => t('Réactions'), 'breves' => t('Brèves'),
        };
    }
    $sectionsOut[] = $s + ['id' => $id, 'struct' => $hasStruct];
}
$hasLineup = !empty($rows);
if ($hasLineup) {
    $anchors['compo'] = t('Composition');
}
$videos = array_values(array_filter(array_map('video_embed', $doc['videos'] ?? [])));
if ($videos) {
    $anchors['video'] = count($videos) > 1 ? t('Vidéos') : t('Vidéo');
}
if (!empty($doc['gallery'])) {
    $anchors['galerie'] = t('Galerie');
}
if (!empty($temoins)) {
    $anchors['ils-y-etaient'] = t('Ils y étaient');
}
$matchName = $hasTeams ? $home['name'] . ' – ' . $away['name'] : $title;
$clean = fn (string $s): string => trim(preg_replace(['/^\s*[«"“]\s*/u', '/\s*[»"”]\s*([.!?…]?)\s*$/u'], ['', '$1'], $s));
?>
<section class="mhero">
  <?php if (!empty($doc['featured_image'])): ?>
  <div class="mhero__bg"><img src="<?= e(img($doc['featured_image'], 1600)) ?>" srcset="<?= e(srcset($doc['featured_image'], [800, 1200, 1600])) ?>" sizes="100vw" alt="" fetchpriority="high"></div>
  <?php endif; ?>
  <div class="mhero__shade">
    <div class="wrap mhero__inner">
      <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>">
        <?php foreach ($crumbs as $c): ?><a href="<?= e($c['href']) ?>"><?= e($c['label']) ?></a><span aria-hidden="true">/</span><?php endforeach; ?>
        <span aria-current="page"><?= e($m['date'] ? date_num($m['date']) : $title) ?></span>
      </nav>
      <?php if ($tag !== ''): ?><span class="mhero__tag"><?= e($tag) ?></span><?php endif; ?>
      <?php if ($hasTeams): ?>
      <h1 class="scoreboard">
        <span class="scoreboard__team scoreboard__team--home<?= $sh ? ' is-sochaux' : '' ?>">
          <span class="scoreboard__name"><?= e($home['name']) ?></span>
          <?php if ($lv = $level($home)): ?><span class="scoreboard__level"><?= e($lv) ?></span><?php endif; ?>
        </span>
        <span class="scoreboard__score" aria-label="<?= e($hasScore ? t('Score') . ' ' . $m['score']['home'] . '-' . $m['score']['away'] : '') ?>">
          <?php if ($hasScore): ?>
            <span class="<?= $sh ? 'is-sochaux' : '' ?>" data-score-in><?= e($m['score']['home']) ?></span><span class="scoreboard__dash">–</span><span class="<?= !$sh ? 'is-sochaux' : '' ?>" data-score-in><?= e($m['score']['away']) ?></span>
          <?php else: ?>
            <span class="scoreboard__vs">–</span>
          <?php endif; ?>
        </span>
        <span class="scoreboard__team scoreboard__team--away<?= !$sh ? ' is-sochaux' : '' ?>">
          <span class="scoreboard__name"><?= e($away['name']) ?></span>
          <?php if ($lv = $level($away)): ?><span class="scoreboard__level"><?= e($lv) ?></span><?php endif; ?>
        </span>
      </h1>
      <?php if ($extra): ?><p class="mhero__extra"><?= e(implode(' · ', $extra)) ?></p><?php endif; ?>
      <?php else: ?>
      <h1 class="h-page mhero__title"><?= e($title) ?></h1>
      <?php endif; ?>
      <?php if ($facts): ?>
      <dl class="mhero__facts">
        <?php foreach ($facts as $f): ?><div><dt><?= e($f['k']) ?></dt><dd><?= e($f['v']) ?></dd></div><?php endforeach; ?>
      </dl>
      <?php endif; ?>
      <div class="mhero__goals">
        <?php foreach ($m['goals'] ?? [] as $g): $isS = \App\Core\Club::isUs((string) $g['team']); ?>
          <span><strong class="<?= $isS ? 'yellow' : '' ?>"><?= e(mb_strtoupper((string) $g['team'])) ?></strong> · <?= e($g['scorers']) ?></span>
        <?php endforeach; ?>
        <?php if (empty($m['goals']) && !empty($m['goals_text'])): ?><span><?= e(t('Buts')) ?> : <?= e($m['goals_text']) ?></span><?php endif; ?>
        <?php foreach ($m['header_extra'] ?? [] as $x): ?><span class="mhero__xline"><?= e($x) ?></span><?php endforeach; ?>
        <?php if ($h2h && $h2h['total']['count'] > 1): ?>
          <a class="mhero__h2h" href="<?= e($h2h['href']) ?>"><?= e(t('Historique {team} × {club}', ['club' => $h2h['name']])) ?> →</a>
        <?php endif; ?>
      </div>
      <div class="hero-actions"><?= View::partial('partials/pdf-button', ['href' => \App\Front\PdfExport::ficheUrl($doc), 'light' => true]) ?><?= !empty($audio) ? View::partial('partials/audio-button', ['audio' => $audio]) : '' ?><?php if (!empty($retro)): ?><a class="btn btn--sm btn--ghost-light pdfbtn" href="<?= e($retro) ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2M9 2h6"/></svg><span><?= e(t('Revivre en direct')) ?></span></a><?php endif; ?></div>
    </div>
  </div>
</section>

<?php if (count($anchors) > 1): ?>
<nav class="anchornav" data-anchornav aria-label="<?= e(t('Sommaire de la fiche')) ?>">
  <div class="wrap anchornav__inner">
    <?php foreach ($anchors as $id => $label): ?><a href="#<?= e($id) ?>"><?= e($label) ?></a><?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>

<div class="wrap fm-grid">
  <article class="fm-main">
    <?php if (!empty($m['event'])): ?>
      <p class="lead fm-event"><?= e($m['event']) ?></p>
    <?php endif; ?>
    <?php if (!empty($doc['intro'])): ?>
      <div class="prose lead"><?= safe_html($doc['intro']) ?></div>
    <?php endif; ?>

    <?php foreach ($sectionsOut as $s): $html = (string) $s['html']; ?>
    <section class="fm-sec"<?= $s['id'] ? ' id="' . e($s['id']) . '"' : '' ?>>
      <?php if (!empty($s['title'])): ?><h2 class="h-section"><?= e($s['title']) ?></h2><?php endif; ?>

      <?php if ($s['key'] === 'resume' && $s['struct'] && !$timelineDone): $timelineDone = true; ?>
        <?php if ($intro = Fiche::resumeIntro($html)): ?><div class="prose"><?= safe_html($intro) ?></div><?php endif; ?>
        <?php $tweets = tweets_by_minute($html); ?>
        <ol class="timeline">
          <?php $prev = [0, 0];
          foreach ($m['highlights'] as $h):
              $txt = trim((string) $h['text']);
              $goal = !empty($h['goal']);
              $isS = false;
              if ($goal && preg_match('/(\d+)\s*-\s*(\d+)/', (string) $h['score'], $sc)) {
                  [$a, $b] = [(int) $sc[1], (int) $sc[2]];
                  $isS = $sh ? $a > $prev[0] : $b > $prev[1];
                  if ($a <= $prev[0] && $b <= $prev[1]) {
                      $gentil = mb_substr(\App\Core\Club::s('gentile'), 0, 6);
                      $isS = ($gentil !== '' && mb_stripos($txt, $gentil) !== false) || \App\Core\Club::isUs($txt);
                  }
                  $prev = [$a, $b];
                  $txt = trim(preg_replace('/\s*\(\s*\d+\s*-\s*\d+\s*\)\s*\.?\s*$/u', '', $txt));
              }
              $min = (string) $h['minute'];
          ?>
          <li class="timeline__item<?= $goal ? ' is-goal' : '' ?><?= $isS ? ' is-sochaux' : '' ?>" data-reveal-x>
            <span class="timeline__min<?= mb_strlen($min) > 3 ? ' is-long' : '' ?>"><?= $min !== '' ? e($min) . "'" : '' ?></span>
            <div class="timeline__body">
              <?php if ($goal): ?><span class="timeline__goal"><span class="ball" aria-hidden="true">⚽</span> <?= e(t('But !')) ?> <?= e($h['score']) ?></span><?php endif; ?>
              <?php // Pictos détectés dans le texte du fait de jeu.
              $tl = mb_strtolower($txt); $tags = [];
              if (preg_match('/expuls|carton rouge|exclu|second (carton )?jaune|deuxi[eè]me (carton )?jaune/u', $tl)) { $tags[] = '<span class="tltag"><span class="cardmark cardmark--r" aria-hidden="true"></span>' . e(t('Carton rouge')) . '</span>'; }
              elseif (preg_match('/averti|carton jaune/u', $tl)) { $tags[] = '<span class="tltag"><span class="cardmark cardmark--y" aria-hidden="true"></span>' . e(t('Carton jaune')) . '</span>'; }
              if (preg_match('/p[ée]nalty|\bs\.\s?p\.|tir au but|tirs au but/u', $tl) && !str_contains($tl, 'point de penalty') && !str_contains($tl, 'point de pénalty')) { $tags[] = '<span class="tltag">⚽ ' . e(t('Penalty')) . '</span>'; }
              if (preg_match('/remplac|c[èe]de sa place|entre en jeu|fait son entr[ée]e|change(ment)? /u', $tl)) { $tags[] = '<span class="tltag"><span class="subarrow subarrow--in" aria-hidden="true">▲</span><span class="subarrow subarrow--out" aria-hidden="true">▼</span> ' . e(t('Changement')) . '</span>'; }
              if (!$goal && preg_match('/poteau|transversale|la barre|montant/u', $tl)) { $tags[] = '<span class="tltag">🥅 ' . e(t('Sur le montant')) . '</span>'; }
              if (!$goal && preg_match('/\b(arr[êe]te|d[ée]tourne|repousse|bloque|capte|claquette|parade|sauve)\b/u', $tl)) { $tags[] = '<span class="tltag">🧤 ' . e(t('Arrêt')) . '</span>'; }
              if (preg_match('/\bcsc\b|contre son camp/u', $tl)) { $tags[] = '<span class="tltag">⚽ ' . e(t('Contre son camp')) . '</span>'; }
              if ($tags): ?><span class="timeline__tags"><?= implode('', $tags) ?></span><?php endif; ?>
              <span class="timeline__text"><?= e($txt) ?></span>
              <?php foreach ($tweets as $ti => $tw): if ($min !== '' && $tw['min'] === preg_replace('/\s+/', '', $min)): unset($tweets[$ti]); ?><?= tweet_embed($tw['id']) ?><?php endif; endforeach; ?>
            </div>
          </li>
          <?php endforeach; ?>
        </ol>
        <?php foreach ($tweets as $tw): ?><?= tweet_embed($tw['id']) ?><?php endforeach; ?>
      <?php elseif ($s['key'] === 'resume' && $s['struct']): ?>
        <?php if ($intro = Fiche::resumeIntro($html)): ?><div class="prose"><?= safe_html($intro) ?></div><?php endif; ?>

      <?php elseif ($s['key'] === 'reactions' && $s['struct']): ?>
        <div class="quotes">
          <?php foreach ($m['reactions'] as $q): ?>
          <figure class="quote" data-reveal>
            <span class="quote__mark" aria-hidden="true">«</span>
            <blockquote><?= str_contains((string) $q['text'], '<') ? $clean(rich_inline((string) $q['text'])) : nl2br(e($clean((string) $q['text']))) ?></blockquote>
            <?php if (!empty($q['who'])): ?><figcaption><?= e($q['who']) ?></figcaption><?php endif; ?>
          </figure>
          <?php endforeach; ?>
        </div>
        <?php $m['reactions'] = []; ?>

      <?php elseif ($s['key'] === 'breves' && $s['struct']): ?>
        <ol class="breves">
          <?php foreach ($m['breves'] as $i => $b): ?>
            <li><span class="breves__n"><?= pad2($i + 1) ?></span><span><?= rich_inline(is_array($b) ? ($b['text'] ?? '') : (string) $b) ?></span></li>
          <?php endforeach; ?>
        </ol>
        <?php $m['breves'] = []; ?>

      <?php else: ?>
        <div class="prose"><?= safe_html($html) ?></div>
      <?php endif; ?>
    </section>
    <?php endforeach; ?>

    <?= View::partial('partials/embeds', ['embeds' => $doc['embeds'] ?? []]) ?>
    <?php foreach ($doc['images'] ?? [] as $im): $cap = trim(($im['caption'] ?? '') . (!empty($im['credit']) ? ' – ' . $im['credit'] : ''), ' –'); ?>
      <figure class="fm-figure" data-gallery>
        <a href="<?= e(img($im['image'], 1600)) ?>" data-lb data-caption="<?= e($cap) ?>"><img src="<?= e(img($im['image'], 800)) ?>" srcset="<?= e(srcset($im['image'], [480, 800, 1200])) ?>" sizes="(max-width: 700px) 100vw, 60vw" alt="<?= e($im['caption'] ?? '') ?>" loading="lazy" decoding="async"></a>
        <?php if ($cap !== ''): ?><figcaption><?= e($cap) ?></figcaption><?php endif; ?>
      </figure>
    <?php endforeach; ?>
    <?= View::partial('partials/videos', ['videos' => $doc['videos'] ?? []]) ?>

    <?php if (isset($etais)): $tm = $temoins ?? []; ?>
    <section class="iye" id="ils-y-etaient" aria-labelledby="iye-t" data-iye data-id="<?= (int) $doc['id'] ?>"
      data-l1="<?= e(t('{n} supporter y était')) ?>" data-ln="<?= e(t('{n} supporters y étaient')) ?>">
      <h2 class="h-section" id="iye-t"><?= e(t('Ils y étaient')) ?></h2>
      <div class="iye__row">
        <p class="iye__n" data-iye-n><?= $etais ? e(t($etais > 1 ? '{n} supporters y étaient' : '{n} supporter y était', ['n' => $etais])) : e(t('Vous étiez au stade ce jour-là ?')) ?></p>
        <button type="button" class="btn btn--yellow" data-iye-btn data-in="<?= e(t('✓ Dans mon carnet')) ?>"><?= e(t('J’y étais !')) ?></button>
        <a class="iye__carnet" href="<?= e(url('/carnet/')) ?>"><?= e(t('Mon carnet du supporter')) ?> →</a>
      </div>
      <form class="iye__mail" data-iye-mail hidden novalidate>
        <p><b><?= e(t('Ajoutez ce match à votre carnet du supporter')) ?></b> <?= e(t(': votre bilan, vos badges et votre porte-bonheur, match après match. Il suffit d’un e-mail pour recevoir le lien personnel de votre carnet.')) ?></p>
        <div class="iye__mailrow">
          <input type="email" name="email" required autocomplete="email" placeholder="<?= e(t('vous@exemple.fr')) ?>" aria-label="<?= e(t('Votre e-mail')) ?>">
          <input type="text" name="website" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
          <button class="btn btn--yellow" type="submit"><?= e(t('Créer mon carnet')) ?></button>
          <button class="linkbtn" type="button" data-iye-count><?= e(t('Non merci, juste compter')) ?></button>
        </div>
        <p class="iye__msg" data-iye-msg role="status" data-sent="<?= e(t('Si cette adresse a déjà un carnet, un lien vient d’y être envoyé : ouvrez-le sur cet appareil, puis touchez de nouveau « J’y étais ! ».')) ?>"></p>
      </form>
      <?php if ($tm): ?>
      <div class="iye__list">
        <?php foreach ($tm as $q): ?>
          <figure class="quote">
            <span class="quote__mark" aria-hidden="true">«</span>
            <blockquote><?= nl2br(e($q['text'])) ?></blockquote>
            <figcaption><?= e($q['name']) ?></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <p class="iye__more"><?= e(t('Un souvenir, une photo, un billet de ce match ?')) ?> <a href="<?= e(url('/souvenir/' . (int) $doc['id'] . '/')) ?>"><?= e(t('Racontez-le au musée')) ?> →</a></p>
    </section>
    <?php endif; ?>
  </article>

  <aside class="fm-aside">
    <?php if (!empty($doc['key_figure']['number']) || !empty($doc['key_figure']['text'])): ?>
    <div class="keyfig" data-reveal>
      <span class="keyfig__n" data-count="<?= e(preg_replace('/\D/', '', (string) $doc['key_figure']['number'])) ?>"><?= e($doc['key_figure']['number']) ?></span>
      <span class="keyfig__t"><?= e($doc['key_figure']['text']) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($hasLineup): ?>
    <div class="compo" id="compo">
      <div class="compo__head">
        <h2 class="h-2"><?= e(t('Composition')) ?></h2>
        <?php if ($pitch): ?><span class="compo__formation"><?= e($pitch['formation']) ?></span><?php endif; ?>
      </div>
      <?php if ($pitch): ?>
      <div class="pitch" role="group" aria-label="<?= e(t('Composition de {team} sur le terrain')) ?>">
        <span class="pitch__line pitch__line--box"></span>
        <span class="pitch__line pitch__line--half"></span>
        <span class="pitch__line pitch__line--circle"></span>
        <span class="pitch__line pitch__line--area-b"></span>
        <span class="pitch__line pitch__line--area-t"></span>
        <?php foreach ($pitch['players'] as $p): ?>
          <<?= $p['href'] ? 'a href="' . e($p['href']) . '"' : 'span' ?> class="pitch__p" style="left:<?= $p['x'] ?>%;top:<?= $p['y'] ?>%">
            <span class="pitch__num"><?= (int) $p['num'] ?></span>
            <span class="pitch__name"><?= e($p['short']) ?><?= !empty($p['captain']) ? ' (c)' : '' ?></span>
          </<?= $p['href'] ? 'a' : 'span' ?>>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php
      $groups = [
          t('Titulaires') => array_filter($rows, fn ($r) => in_array($r['position'], ['G', 'D', 'M', 'A'], true)),
          t('Remplaçants') => array_filter($rows, fn ($r) => $r['position'] === 'R'),
          t('Entraîneur') => array_filter($rows, fn ($r) => $r['position'] === 'E'),
          t('Autres') => array_filter($rows, fn ($r) => !in_array($r['position'], ['G', 'D', 'M', 'A', 'R', 'E'], true)),
      ];
      // Intitulés d'origine du tableau, retrouvés par rôle (certaines feuilles ont une colonne « Numéro »).
      $hdr = $m['lineup']['headers'] ?? [];
      $colLabel = function (string $re, string $default) use ($hdr): string {
          foreach ($hdr as $h) {
              if (preg_match($re, mb_strtolower((string) $h))) {
                  return t((string) $h);
              }
          }
          return t($default);
      };
      $hasNum = (bool) array_filter($rows, fn ($r) => ($r['number'] ?? null) !== null && $r['number'] !== '');
      $extraCols = [];
      foreach ($rows as $r) {
          foreach (array_keys($r['extra'] ?? []) as $k) {
              $extraCols[$k] = true;
          }
      }
      $span = 5 + ($hasNum ? 1 : 0) + count($extraCols);
      // Pictos détectés automatiquement : débuts et dernier match (calculés sur tous les matchs en base).
      $ptot = \App\Data\Derived::part('person_totals');
      $mid = (int) ($doc['id'] ?? 0);
      $oldMatch = !empty($m['date']) && strtotime((string) $m['date']) < strtotime('-1 year');
      // Buts sur penalty : minutes suivies de « s.p. » dans le texte d'origine.
      $penMins = function (string $txt): array {
          preg_match_all("/(\d{1,3}(?:'\s*\+\s*\d+|\+\d+)?)'?\s*(?:\(?\s*s\.?\s*p\.?|\(?p[ée]n\.?)/iu", $txt, $mm);
          return array_map(fn ($x) => preg_replace("/[^0-9+]/", '', $x), $mm[1] ?? []);
      };
      ?>
      <div class="dtable-wrap">
        <table class="dtable lineup">
          <thead><tr>
            <th scope="col"><?= e($colLabel('/^poste/', 'Poste')) ?></th>
            <?php if ($hasNum): ?><th scope="col"><?= e($colLabel('/num[ée]ro/u', 'N°')) ?></th><?php endif; ?>
            <th scope="col"><?= e($colLabel('/nom/', 'Nom et prénom')) ?></th>
            <th scope="col"><?= e($colLabel('/^buts?\b|buteur/', 'Buts')) ?></th>
            <th scope="col"><?= e($colLabel('/rempl|remp\.|changement/', 'Changements')) ?></th>
            <th scope="col"><?= e($colLabel('/carton/', 'Cartons')) ?></th>
            <?php foreach (array_keys($extraCols) as $x): ?><th scope="col"><?= e(t((string) $x)) ?></th><?php endforeach; ?>
          </tr></thead>
          <?php foreach ($groups as $label => $list): if (!$list) continue; ?>
          <tbody>
            <tr class="lineup__group"><th colspan="<?= $span ?>" scope="rowgroup"><?= e($label) ?></th></tr>
            <?php foreach ($list as $r): ?>
            <tr>
              <td class="lineup__pos"><?= e($r['position']) ?></td>
              <?php if ($hasNum): ?><td class="lineup__num"><?= e((string) ($r['number'] ?? '')) ?></td><?php endif; ?>
              <td class="lineup__name">
                <?php if ($r['href']): ?><a href="<?= e($r['href']) ?>"><?= e($r['name']) ?></a><?php else: ?><?= e($r['name']) ?><?php endif; ?>
                <?= !empty($r['captain']) ? '<abbr class="armband" title="' . e(t('Capitaine')) . '">C</abbr>' : '' ?>
                <?php if ($r['position'] === 'G'): ?><span class="lpicto" title="<?= e(t('Gardien de but')) ?>" aria-hidden="true">🧤</span><?php endif; ?>
                <?php $pt = !empty($r['pid']) ? ($ptot[(int) $r['pid']] ?? null) : null; if ($pt && $mid && in_array($r['position'], ['G', 'D', 'M', 'A', 'R'], true)): ?>
                  <?php if ((int) ($pt['first'] ?? 0) === $mid): ?><span class="lbadge lbadge--debut" title="<?= e(t('Premier match avec le club')) ?>">★ <?= e(t('débuts')) ?></span><?php endif; ?>
                  <?php if ($oldMatch && (int) ($pt['last'] ?? 0) === $mid && (int) ($pt['first'] ?? 0) !== $mid): ?><span class="lbadge lbadge--last" title="<?= e(t('Dernier match avec le club')) ?>">⚑ <?= e(t('dernier match')) ?></span><?php endif; ?>
                  <?php if ((int) ($pt['first'] ?? 0) === $mid && (int) ($pt['last'] ?? 0) === $mid && $oldMatch): ?><span class="lbadge lbadge--last" title="<?= e(t('Son seul match avec le club')) ?>"><?= e(t('seul match')) ?></span><?php endif; ?>
                <?php endif; ?>
              </td>
              <td class="lineup__goals"><?php if ($r['goals'] || $r['own_goals']): $pens = $penMins((string) $r['goals_text']); ?><?php foreach ($r['goals'] as $mn): $isPen = in_array(preg_replace('/[^0-9+]/', '', (string) $mn), $pens, true); ?><span class="lev" title="<?= e($isPen ? t('But sur penalty') : t('But')) ?>"><span class="ball" aria-hidden="true">⚽</span><?= $mn !== '' ? e((string) $mn) . "'" : '' ?><?= $isPen ? ' <small>' . e(t('pén.')) . '</small>' : '' ?></span><?php endforeach; ?><?php $ng = count($r['goals']); if ($ng >= 2): ?><span class="lbadge lbadge--hat"><?= e($ng === 2 ? t('doublé') : ($ng === 3 ? t('triplé') : t('quadruplé'))) ?></span><?php endif; ?><?php foreach ($r['own_goals'] as $mn): ?><span class="lev lev--csc" title="<?= e(t('But contre son camp')) ?>"><span class="ball ball--csc" aria-hidden="true">⚽</span><?= $mn !== '' ? e((string) $mn) . "'" : '' ?> <small>csc</small></span><?php endforeach; ?><?php elseif ($r['goals_text'] !== ''): ?><?= (str_contains($r['goals_text'], '⚽') ? '' : '<span class="ball" aria-hidden="true">⚽</span> ') . e($r['goals_text']) ?><?php endif; ?></td>
              <td class="lineup__sub"><?php if (($r['sub_in'] ?? null) !== null || ($r['sub_out'] ?? null) !== null): ?><?php if (($r['sub_in'] ?? null) !== null): ?><span class="lev lev--in" title="<?= e(t('Entrée en jeu')) ?>"><span class="subarrow subarrow--in" aria-hidden="true">▲</span><?= e((string) $r['sub_in']) ?>'</span><?php endif; ?><?php if (($r['sub_out'] ?? null) !== null): ?><span class="lev lev--out" title="<?= e(t('Sortie')) ?>"><span class="subarrow subarrow--out" aria-hidden="true">▼</span><?= e((string) $r['sub_out']) ?>'</span><?php endif; ?><?php else: ?><?= e($r['sub_text']) ?><?php endif; ?></td>
              <td class="lineup__cards">
                <?php foreach ($r['yellow'] as $c): ?><span class="lev" title="<?= e(t('Carton jaune')) ?>"><span class="cardmark cardmark--y" aria-hidden="true"></span><?= $c !== '' ? e((string) $c) . "'" : '' ?></span><?php endforeach; ?>
                <?php foreach ($r['red'] as $c): ?><span class="lev" title="<?= e(t('Carton rouge')) ?>"><span class="cardmark cardmark--r" aria-hidden="true"></span><?= $c !== '' ? e((string) $c) . "'" : '' ?></span><?php endforeach; ?>
                <?php if ($r['yellow'] && $r['red']): ?><small class="muted"><?= e(t('(2e jaune)')) ?></small><?php endif; ?>
                <?= !$r['yellow'] && !$r['red'] ? e($r['cards_text']) : '' ?>
              </td>
              <?php foreach (array_keys($extraCols) as $x): ?><td><?= e((string) ($r['extra'][$x] ?? '')) ?></td><?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php foreach ($m['other_lineups'] ?? [] as $ol): if (empty($ol['rows'])) continue; ?>
    <div class="compo">
      <h2 class="h-3"><?= e($ol['title'] ?? t('Composition')) ?></h2>
      <div class="dtable-wrap">
        <table class="dtable lineup">
          <tbody>
          <?php foreach ($ol['rows'] as $r): ?>
            <tr><td class="lineup__pos"><?= e($r['position']) ?></td><?= ($r['number'] ?? null) !== null ? '<td class="lineup__num">' . e((string) $r['number']) . '</td>' : '' ?><td class="lineup__name"><?= e($r['name']) ?><?= !empty($r['captain']) ? ' (c)' : '' ?></td><td><?= e($r['goals_text']) ?></td><td><?= e($r['sub_text']) ?></td><td><?= e($r['cards_text']) ?></td><?php foreach ($r['extra'] ?? [] as $x): ?><td><?= e((string) $x) ?></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if ($h2h): ?>
    <div class="h2hbox">
      <span class="eyebrow"><?= e(t('Face-à-face')) ?></span>
      <h2 class="h-3"><?= e(\App\Core\Club::team()) ?> × <?= e($h2h['name']) ?></h2>
      <div class="h2hbox__totals">
        <div><b><?= (int) $h2h['total']['count'] ?></b><span><?= e(t('matchs')) ?></span></div>
        <div class="res--V"><b><?= (int) $h2h['total']['v'] ?></b><span><?= e(t('victoires')) ?></span></div>
        <div class="res--N"><b><?= (int) $h2h['total']['n'] ?></b><span><?= e(t('nuls')) ?></span></div>
        <div class="res--D"><b><?= (int) $h2h['total']['d'] ?></b><span><?= e(t('défaites')) ?></span></div>
      </div>
      <?php if ($h2h['last']): ?>
      <ul class="h2hbox__last">
        <?php foreach ($h2h['last'] as $x): ?>
          <li><a href="<?= e(url($x['path'])) ?>"><span class="res res--<?= e($x['result'] ?: 'N') ?>"><?= e($x['result'] ?: '–') ?></span><span><?= e(\App\Front\Site::matchLabel($x)) ?></span><small><?= e(date_num($x['date'])) ?></small></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <a class="btn btn--navy btn--sm" href="<?= e($h2h['href']) ?>"><?= e(t('Tout le face-à-face')) ?> →</a>
    </div>
    <?php endif; ?>
  </aside>
</div>

<?= View::partial('partials/gallery', ['items' => $doc['gallery'] ?? [], 'title' => t('Galerie du match'), 'anchor' => 'galerie', 'context' => $matchName . ($m['date'] ? ', ' . date_num($m['date']) : '')]) ?>

<?php if (!empty($doc['tables'])): ?>
<section class="wrap section fm-tables">
  <?php foreach ($doc['tables'] as $tb): ?>
    <?php if (!empty($tb['title'])): ?><h2 class="h-2"><?= e($tb['title']) ?></h2><?php endif; ?>
    <?= View::partial('partials/table', ['table' => $tb]) ?>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?= View::partial('partials/related', ['related' => $related]) ?>

<?php [$prevM, $nextM] = $adjacent; if ($prevM || $nextM): ?>
<nav class="prevnext" aria-label="<?= e(t('Matchs de la saison')) ?>">
  <div class="wrap prevnext__inner">
    <?php if ($prevM): ?><a href="<?= e(url($prevM['path'])) ?>" rel="prev"><span>← <?= e(t('Match précédent')) ?></span><b><?= e(\App\Front\Site::matchLabel($prevM)) ?></b></a><?php else: ?><span></span><?php endif; ?>
    <a class="prevnext__mid" href="<?= e(url('/matchs/' . $m['season'] . '/')) ?>"><?= e(t('Saison')) ?> <?= e($m['season']) ?></a>
    <?php if ($nextM): ?><a href="<?= e(url($nextM['path'])) ?>" rel="next" class="prevnext__next"><span><?= e(t('Match suivant')) ?> →</span><b><?= e(\App\Front\Site::matchLabel($nextM)) ?></b></a><?php else: ?><span></span><?php endif; ?>
  </div>
</nav>
<?php endif; ?>
