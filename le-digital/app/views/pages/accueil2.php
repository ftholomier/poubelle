<?php
/**
 * Accueil v2 (/accueil2) : héros + film animé, avis, trois blocs « ce que je fais » (sur mesure, formation, IA),
 * assistant IA, FAQ, mur d'avis et appel final. Textes éditables (Contenus › Accueil v2).
 * @var array $c contenu de la page  @var array $g contenus communs  @var array $s paramètres
 */
use LD\View;

$h = $c['hero'];
$d = $c['dev'];
$f = $c['formation'];
$a = $c['auto'];
$b = $c['bot'];
$featured = LD\Reviews::featured(3);
$all = LD\Reviews::featured(15);
$wall = count($all) > 3 ? array_slice($all, 3) : [];
$wall = count($wall) >= 3 ? $wall : (count($all) >= 3 ? $all : []);

// Petites icônes des maquettes (traits, couleur héritée).
$ic = static function (string $k, int $s = 18): string {
    $p = [
        'Devis'    => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4M10 12h5M10 16h5"/>',
        'Relance'  => '<path d="M20 12a8 8 0 11-2.4-5.7"/><path d="M20 4v5h-5"/>',
        'Emails'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'Facture'  => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'Rapport'  => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'Résumé'   => '<path d="M4 5h16M4 10h16M4 15h10"/>',
        'Contenu'  => '<path d="M4 20l4-1 11-11-3-3L5 16z"/>',
        'Planning' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'Clients'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0113 0M16 4.5a3.5 3.5 0 010 7M18 14a6 6 0 013.5 6"/>',
        'Stock'    => '<path d="M3 8l9-5 9 5v8l-9 5-9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'Factures' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'Relances' => '<path d="M20 12a8 8 0 11-2.4-5.7"/><path d="M20 4v5h-5"/>',
        'Rapports' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    ];
    return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$k] ?? $p['Résumé']) . '</svg>';
};
$check = icon('check16', 14, 'currentColor', 2.6);

// Courbes du parcours de formation (montée progressive), lissées en courbes de Bézier.
$xs = [40, 160, 400, 640, 880, 960];
$curves = [[340, 290, 215, 150, 85, 62], [345, 315, 255, 185, 120, 100], [350, 335, 300, 230, 150, 122]];
$path = static function (array $ys) use ($xs): string {
    $pts = array_map(null, $xs, $ys);
    $dd = 'M' . $pts[0][0] . ' ' . $pts[0][1];
    for ($i = 0; $i < count($pts) - 1; $i++) {
        $p0 = $pts[max(0, $i - 1)];
        [$x1, $y1] = $pts[$i];
        [$x2, $y2] = $pts[$i + 1];
        $p3 = $pts[min(count($pts) - 1, $i + 2)];
        $dd .= sprintf(' C%.1f %.1f %.1f %.1f %d %d', $x1 + ($x2 - $p0[0]) / 6, $y1 + ($y2 - $p0[1]) / 6, $x2 - ($p3[0] - $x1) / 6, $y2 - ($p3[1] - $y1) / 6, $x2, $y2);
    }
    return $dd;
};
$notes = [[5, 9, -7], [57, 5, 6], [30, 31, 3], [68, 40, -9], [7, 55, 5], [43, 66, -4]];
?>
<div class="a2">

<section class="a2-hero" aria-labelledby="a2-title">
  <div class="a2-panel a2-panel--hero">
    <div class="a2-hero__glow" aria-hidden="true"></div>
    <div class="a2-hero__grid" aria-hidden="true"></div>
    <div class="a2-hero__in">
      <p class="a2-proof">
        <span class="a2-proof__avs" aria-hidden="true">
          <img src="<?= e(site_avatar()) ?>" alt="" width="30" height="30">
          <?php foreach (array_slice($all, 0, 4) as $rv): ?><span><?= e(mb_strtoupper(mb_substr((string) ($rv['name'] ?? ''), 0, 1))) ?></span><?php endforeach; ?>
        </span>
        <?= t($h['proof']) ?>
      </p>
      <h1 class="a2-h1" id="a2-title"><?= t($h['title']) ?> <span class="a2-accent"><?= t($h['title_accent']) ?></span></h1>
      <p class="a2-hero__p"><?= t($h['text']) ?></p>
      <ul class="a2-points">
        <?php foreach ($h['points'] as $pt): ?><li><span class="a2-points__ic" aria-hidden="true"><?= $check ?></span><?= t($pt) ?></li><?php endforeach; ?>
      </ul>
      <div class="a2-hero__cta">
        <a class="btn btn-red btn-xl btn-lift a2-cta" href="/on-prend-rdv"><?= t($h['cta']) ?> <span class="a2-cta__arr" aria-hidden="true">→</span></a>
        <p class="a2-note"><span class="dot-green" aria-hidden="true"></span><?= t($h['note']) ?></p>
      </div>

      <div class="a2-film" data-a2-film>
        <div class="a2-film__screen">
          <div class="a2-scene a2-scene--chaos is-on" data-scene>
            <?php foreach ($h['film_notes'] as $i => $n): [$x, $y, $r] = $notes[$i % count($notes)]; $low = mb_strtolower((string) $n); ?>
              <span class="a2-sticky<?= str_contains($low, '.xls') ? ' a2-sticky--xls' : (str_contains($low, 'mail') ? ' a2-sticky--mail' : '') ?>" style="left:<?= $x ?>%;top:<?= $y ?>%;--r:<?= $r ?>deg;--i:<?= $i ?>"><?= t($n) ?></span>
            <?php endforeach; ?>
            <span class="a2-badge-n" style="left:84%;top:16%">47</span>
            <span class="a2-badge-n a2-badge-n--2" style="left:22%;top:80%">12</span>
          </div>
          <div class="a2-scene a2-scene--build" data-scene>
            <div class="a2-win">
              <div class="a2-win__bar"><i></i><i></i><i></i><span><?= t($h['film_window']) ?></span></div>
              <div class="a2-win__body">
                <div class="a2-win__side"><?php foreach (['Accueil', 'Clients', 'Devis', 'Planning', 'Factures'] as $k => $it): ?><span style="--d:<?= 120 + $k * 90 ?>ms"<?= $k === 2 ? ' class="is-on"' : '' ?>><?= e($it) ?></span><?php endforeach; ?></div>
                <div class="a2-win__main">
                  <b class="a2-b" style="--d:300ms">Bonjour 👋</b>
                  <div class="a2-win__kpis"><?php foreach ([['Devis', '12'], ['Relances', '34'], ['Clients', '86']] as $k => [$l, $v]): ?><span class="a2-b" style="--d:<?= 450 + $k * 120 ?>ms"><small><?= e($l) ?></small><b><?= e($v) ?></b></span><?php endforeach; ?></div>
                  <svg class="a2-win__chart a2-b" style="--d:850ms" viewBox="0 0 300 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 70 C40 66 60 50 90 52 S150 30 180 34 S240 12 300 10" /><path class="a2-win__area" d="M0 70 C40 66 60 50 90 52 S150 30 180 34 S240 12 300 10 V80 H0z"/></svg>
                </div>
              </div>
            </div>
          </div>
          <div class="a2-scene a2-scene--ia" data-scene>
            <div class="a2-aicards">
              <?php foreach ($h['film_ai'] as $k => $it): ?>
                <div class="a2-aicard" style="--d:<?= 200 + $k * 380 ?>ms">
                  <span class="a2-aicard__ic" aria-hidden="true"><?= icon('spark1', 16, 'currentColor', 2) ?></span>
                  <span class="a2-aicard__t"><b><?= t($it['t']) ?></b><small><?= t($it['s']) ?></small></span>
                  <span class="a2-aicard__ok"><span class="a2-aicard__btn"><?= t($h['film_validate']) ?></span><span class="a2-aicard__done" aria-hidden="true"><?= $check ?></span></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="a2-scene a2-scene--train" data-scene>
            <div class="a2-train">
              <div class="a2-ring" aria-hidden="true"><svg viewBox="0 0 120 120"><circle cx="60" cy="60" r="52"/><circle class="a2-ring__v" cx="60" cy="60" r="52"/></svg><img src="<?= e(site_avatar()) ?>" alt="" width="84" height="84"></div>
              <ul class="a2-train__list"><?php foreach ($h['film_train'] as $k => $it): ?><li style="--d:<?= 500 + $k * 520 ?>ms"><span aria-hidden="true"><?= $check ?></span><?= t($it) ?></li><?php endforeach; ?></ul>
            </div>
          </div>
          <?php foreach ($h['film'] as $k => $sc): ?><p class="a2-cap" data-cap="<?= $k ?>"<?= $k ? ' hidden' : '' ?>><?= t($sc['caption']) ?></p><?php endforeach; ?>
        </div>
        <div class="a2-film__bar">
          <button class="a2-film__play" type="button" data-a2-play aria-label="Mettre en pause l’animation"><span class="a2-film__pause" aria-hidden="true"></span></button>
          <div class="a2-film__segs">
            <?php foreach ($h['film'] as $k => $sc): ?><button type="button" data-go="<?= $k ?>" aria-label="Étape <?= $k + 1 ?> : <?= e($sc['caption']) ?>"><i></i></button><?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if ($featured): ?>
      <div class="a2-quotes" aria-label="Avis clients">
        <?php foreach ($featured as $i => $rv): ?><?= View::partial('review', ['r' => $rv, 'variant' => 'a2', 'delay' => $i * 90]) ?><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="a2-sec" id="sur-mesure" aria-labelledby="a2-dev">
  <div class="a2-frame">
    <div class="a2-head a2-head--split" data-reveal>
      <div>
        <span class="a2-tag"><?= t($d['tag']) ?></span>
        <h2 class="a2-h2" id="a2-dev"><?= t($d['title']) ?></h2>
        <p class="a2-lead"><?= t($d['text']) ?></p>
      </div>
      <a class="btn btn-red btn-lg btn-lift a2-cta" href="/sur-mesure-ia"><?= t($d['cta']) ?> <span class="a2-cta__arr" aria-hidden="true">→</span></a>
    </div>
    <div class="a2-bento">
      <article class="a2-cell" data-reveal>
        <div class="a2-vis a2-orbit" data-a2-orbit data-toast="<?= e($d['orbit_toast']) ?>">
          <div class="a2-orbit__ring a2-orbit__ring--1" aria-hidden="true"></div>
          <div class="a2-orbit__ring a2-orbit__ring--2" aria-hidden="true"></div>
          <div class="a2-orbit__spin">
            <?php $n = count($d['orbit']); foreach ($d['orbit'] as $k => $it): ?>
              <span class="a2-orbit__it" style="--a:<?= round(360 / max(1, $n) * $k, 2) ?>deg;--r:<?= $k % 2 ? '31cqw' : '25cqw' ?>"><span><?= $ic($it, 15) ?><?= t($it) ?></span></span>
            <?php endforeach; ?>
          </div>
          <div class="a2-orbit__core" aria-hidden="true"><img src="<?= e(site_logo(true)) ?>" alt="" width="92"></div>
          <p class="a2-orbit__toast" data-a2-toast aria-live="off"><span aria-hidden="true"><?= $check ?></span><span data-a2-toast-t><?= t($d['orbit_toast']) ?></span></p>
        </div>
        <h3 class="a2-h3"><?= t($d['card1_title']) ?></h3>
        <p class="a2-p"><?= t($d['card1_text']) ?></p>
      </article>
      <article class="a2-cell" data-reveal data-delay="120">
        <div class="a2-vis a2-dash" data-a2-inview>
          <div class="a2-dash__win">
            <div class="a2-dash__bar"><i></i><i></i><i></i><span>🔒 <?= t($d['dash_url']) ?></span><em><?= t($d['dash_label']) ?></em></div>
            <div class="a2-dash__body">
              <div class="a2-dash__kpis">
                <?php foreach ($d['dash_kpis'] as $kp): ?><div><small><?= t($kp['l']) ?></small><b><?php if (is_numeric($kp['v'])): ?><span data-count-to="<?= e($kp['v']) ?>"><?= e($kp['v']) ?></span><?php else: ?><?= t($kp['v']) ?><?php endif; ?></b></div><?php endforeach; ?>
              </div>
              <div class="a2-dash__chart">
                <small><?= t($d['dash_chart']) ?></small>
                <svg viewBox="0 0 400 120" preserveAspectRatio="none" aria-hidden="true">
                  <defs><linearGradient id="a2g1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#cc1517" stop-opacity=".22"/><stop offset="1" stop-color="#cc1517" stop-opacity="0"/></linearGradient></defs>
                  <path class="a2-dash__area" d="M0 100 C30 92 50 70 80 76 S130 40 160 52 S220 30 250 38 S320 12 350 20 S390 8 400 6 V120 H0z" fill="url(#a2g1)"/>
                  <path class="a2-dash__line" d="M0 100 C30 92 50 70 80 76 S130 40 160 52 S220 30 250 38 S320 12 350 20 S390 8 400 6"/>
                </svg>
              </div>
              <ul class="a2-dash__rows">
                <?php foreach ($d['dash_rows'] as $k => $rw): ?><li style="--d:<?= 900 + $k * 160 ?>ms"><span><?= t($rw['t']) ?></span><em><?= $check ?><?= t($rw['s']) ?></em></li><?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
        <h3 class="a2-h3"><?= t($d['card2_title']) ?></h3>
        <p class="a2-p"><?= t($d['card2_text']) ?></p>
      </article>
    </div>
    <ul class="a2-badges" data-reveal>
      <?php foreach ($d['badges'] as $bd): ?><li><span aria-hidden="true"><?= $check ?></span><?= t($bd) ?></li><?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="a2-sec a2-sec--tint" id="formation" aria-labelledby="a2-form">
  <div class="a2-frame">
    <div class="a2-head a2-head--center" data-reveal>
      <span class="a2-tag"><?= t($f['tag']) ?></span>
      <h2 class="a2-h2" id="a2-form"><?= t($f['title']) ?></h2>
      <p class="a2-lead"><?= t($f['text']) ?></p>
      <a class="btn btn-red btn-lg btn-lift a2-cta" href="/formation"><?= t($f['cta']) ?> <span class="a2-cta__arr" aria-hidden="true">→</span></a>
    </div>
    <div class="a2-chart" data-a2-chart data-a2-inview data-reveal="scale">
      <svg viewBox="0 0 1000 380" preserveAspectRatio="none" aria-hidden="true">
        <defs>
          <linearGradient id="a2g2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e8402a" stop-opacity=".18"/><stop offset="1" stop-color="#e8402a" stop-opacity="0"/></linearGradient>
          <clipPath id="a2clip"><rect class="a2-chart__reveal" x="0" y="0" width="1000" height="380"/></clipPath>
        </defs>
        <?php foreach ([80, 160, 240, 320] as $gy): ?><line class="a2-chart__grid" x1="40" x2="960" y1="<?= $gy ?>" y2="<?= $gy ?>"/><?php endforeach; ?>
        <g clip-path="url(#a2clip)">
          <path d="<?= e($path($curves[0])) ?> V380 H40z" fill="url(#a2g2)"/>
          <?php foreach ($curves as $k => $cv): ?><path class="a2-chart__c a2-chart__c--<?= $k ?>" d="<?= e($path($cv)) ?>"/><?php endforeach; ?>
        </g>
      </svg>
      <?php foreach ($f['milestones'] as $k => $ms): $xi = $k + 1; ?>
        <div class="a2-chart__ms" data-ms="<?= $k ?>" style="left:<?= $xs[$xi] / 10 ?>%">
          <?php foreach ($curves as $ci => $cv): ?><i class="a2-chart__dot a2-chart__dot--<?= $ci ?>" style="top:<?= round($cv[$xi] / 380 * 100, 2) ?>%"></i><?php endforeach; ?>
          <span class="a2-chart__x"><?= t($ms['t']) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="a2-tip" data-a2-tip>
        <small><?= t($f['chart_label']) ?></small>
        <b data-a2-tip-t><?= t($f['milestones'][0]['t'] ?? '') ?></b>
        <span data-a2-tip-d><?= t($f['milestones'][0]['d'] ?? '') ?></span>
        <ul><?php foreach ($f['curves'] as $k => $cn): ?><li><i class="a2-chart__key a2-chart__key--<?= $k ?>"></i><?= t($cn) ?></li><?php endforeach; ?></ul>
      </div>
      <script type="application/json" data-a2-ms><?= json_script($f['milestones']) ?></script>
    </div>
    <div class="a2-cols3">
      <?php foreach ($f['blocks'] as $k => $bl): ?>
        <div class="a2-col" data-reveal data-delay="<?= $k * 100 ?>"><span class="a2-col__n"><?= sprintf('%02d', $k + 1) ?></span><h3 class="a2-h3"><?= t($bl['t']) ?></h3><p class="a2-p"><?= t($bl['d']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="a2-sec" id="automatisation" aria-labelledby="a2-auto">
  <div class="a2-frame">
    <div class="a2-head a2-head--center" data-reveal>
      <span class="a2-tag"><?= t($a['tag']) ?></span>
      <h2 class="a2-h2" id="a2-auto"><?= t($a['title']) ?></h2>
      <p class="a2-lead"><?= t($a['text']) ?></p>
      <a class="btn btn-red btn-lg btn-lift a2-cta" href="/sur-mesure-ia#auto"><?= t($a['cta']) ?> <span class="a2-cta__arr" aria-hidden="true">→</span></a>
    </div>
    <div class="a2-tasks" data-a2-tasks data-reveal="scale">
      <div class="a2-tasks__bar"><i></i><i></i><i></i><span><?= t($h['film_window']) ?></span></div>
      <div class="a2-tasks__grid">
        <?php foreach ($a['tasks'] as $k => $tk): ?>
          <div class="a2-task" data-task>
            <span class="a2-task__ic" aria-hidden="true"><?= $ic($tk['k'], 20) ?></span>
            <small><?= t($tk['k']) ?></small>
            <b><?= t($tk['t']) ?></b>
            <span class="a2-task__st"><span class="a2-task__todo"><?= t($a['task_before']) ?></span><span class="a2-task__done"><?= $check ?><?= t($a['task_after']) ?></span></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="a2-bento">
      <article class="a2-cell" data-reveal>
        <div class="a2-vis a2-flow" aria-hidden="true">
          <?php foreach ([0, 1] as $col): $list = $col ? array_reverse($a['tasks']) : $a['tasks']; ?>
            <div class="a2-flow__col<?= $col ? ' a2-flow__col--2' : '' ?>"><div class="a2-flow__track">
              <?php foreach (array_merge($list, $list) as $tk): ?><span><?= $ic($tk['k'], 16) ?><?= t($tk['t']) ?><em><?= $check ?></em></span><?php endforeach; ?>
            </div></div>
          <?php endforeach; ?>
        </div>
        <h3 class="a2-h3"><?= t($a['card1_title']) ?></h3>
        <p class="a2-p"><?= t($a['card1_text']) ?></p>
      </article>
      <article class="a2-cell" data-reveal data-delay="120">
        <div class="a2-vis a2-rows" data-a2-inview>
          <p class="a2-rows__label"><span class="a2-live" aria-hidden="true"></span><?= t($a['detected']) ?></p>
          <?php foreach ($a['rows'] as $k => $rw): ?>
            <div class="a2-row" style="--d:<?= 200 + $k * 260 ?>ms"><s><?= t($rw['before']) ?></s><span><?= $check ?><?= t($rw['after']) ?></span></div>
          <?php endforeach; ?>
        </div>
        <h3 class="a2-h3"><?= t($a['card2_title']) ?></h3>
        <p class="a2-p"><?= t($a['card2_text']) ?></p>
      </article>
    </div>
  </div>
</section>

<section class="a2-sec a2-sec--tint" id="assistant" aria-labelledby="a2-bot">
  <div class="a2-frame">
    <div class="a2-head a2-head--split" data-reveal>
      <div>
        <span class="a2-tag"><?= t($b['tag']) ?></span>
        <h2 class="a2-h2" id="a2-bot"><?= t($b['title']) ?></h2>
        <p class="a2-lead"><?= t($b['text']) ?></p>
      </div>
      <button class="btn btn-dark btn-lg btn-lift a2-cta" type="button" data-open-bot><?= t($b['cta']) ?> <span class="a2-cta__arr" aria-hidden="true">→</span></button>
    </div>
    <div class="a2-ask" data-reveal="scale">
      <form class="a2-ask__bar" data-a2-ask>
        <span class="a2-ask__ic" aria-hidden="true"><?= icon('spark', 22, 'currentColor', 1.8) ?></span>
        <label class="sr-only" for="a2-q"><?= t($b['placeholder']) ?></label>
        <input id="a2-q" name="q" maxlength="300" autocomplete="off" placeholder="<?= e($b['placeholder']) ?>" data-typer="<?= json_attr($b['questions']) ?>">
        <button class="btn btn-red btn-md" type="submit"><?= t($b['send']) ?> <?= icon('send', 16, 'currentColor', 2.4) ?></button>
      </form>
      <div class="a2-ask__chips"><?php foreach ($b['questions'] as $q): ?><button type="button" data-q="<?= e($q) ?>"><?= t($q) ?></button><?php endforeach; ?></div>
      <div class="a2-ask__demo" data-a2-inview data-a2-demo>
        <p class="a2-msg a2-msg--you"><span>Toi</span><?= t($b['demo_q']) ?></p>
        <p class="a2-msg a2-msg--ai"><span><img src="<?= e(site_avatar()) ?>" alt="" width="26" height="26"> Assistant de Fred</span><em data-a2-type="<?= e(nbsp($b['demo_a'])) ?>"><?= t($b['demo_a']) ?></em></p>
      </div>
    </div>
  </div>
</section>

<section class="a2-bottom" aria-label="Questions, avis et contact">
  <div class="a2-panel a2-panel--dark">
    <div class="a2-hero__glow a2-hero__glow--b" aria-hidden="true"></div>
    <div class="a2-faq" id="faq">
      <div class="a2-faq__head" data-reveal>
        <span class="a2-tag a2-tag--lt"><?= t($c['faq']['tag']) ?></span>
        <h2 class="a2-h2"><?= t($c['faq']['title']) ?></h2>
        <p><?= t($c['faq']['text']) ?> <a href="/on-prend-rdv#formulaire"><?= t($c['faq']['link']) ?></a></p>
      </div>
      <div class="a2-faq__list" data-reveal data-delay="100">
        <?php foreach ($c['faq']['items'] as $k => $qa): ?>
          <details class="a2-qa"<?= $k === 0 ? ' open' : '' ?>><summary><?= t($qa['q']) ?><span class="a2-qa__pm" aria-hidden="true"></span></summary><p><?= t($qa['a']) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($wall): ?>
      <div class="a2-wall" id="avis">
        <div class="a2-head a2-head--center" data-reveal>
          <span class="a2-tag a2-tag--lt"><?= t($c['wall']['tag']) ?></span>
          <h2 class="a2-h2"><?= t($c['wall']['title']) ?></h2>
          <p class="a2-lead a2-lead--dk"><?= t($c['wall']['text']) ?></p>
        </div>
        <div class="a2-wall__grid<?= count($wall) > 6 ? ' a2-wall__grid--fade' : '' ?>">
          <?php foreach (array_slice($wall, 0, 9) as $i => $rv): ?><?= View::partial('review', ['r' => $rv, 'variant' => 'a2', 'delay' => 0]) ?><?php endforeach; ?>
        </div>
        <div class="a2-wall__more"><a class="btn btn-white btn-md" href="/avis"><?= t($c['wall']['cta']) ?></a></div>
      </div>
    <?php endif; ?>

    <div class="a2-final">
      <div class="a2-final__txt" data-reveal>
        <h2 class="a2-h2 a2-h2--final"><?= t($c['final']['title']) ?></h2>
        <p class="a2-lead a2-lead--dk"><?= t($c['final']['text']) ?></p>
        <a class="btn btn-red btn-xl btn-lift a2-cta" href="/on-prend-rdv"><?= t($c['final']['cta']) ?> <span class="a2-cta__arr" aria-hidden="true">→</span></a>
      </div>
      <div class="a2-screen" data-a2-inview data-reveal="scale" aria-hidden="true">
        <div class="a2-screen__in">
          <div class="a2-screen__head"><img src="<?= e(site_avatar()) ?>" alt="" width="44" height="44"><span><b><?= t($c['final']['screen_title']) ?></b><small><?= t($c['final']['screen_sub']) ?></small></span></div>
          <div class="a2-screen__slots"><?php foreach ($c['final']['slots'] as $k => $sl): ?><span<?= $k === 2 ? ' class="is-pick"' : '' ?>><?= t($sl) ?></span><?php endforeach; ?></div>
          <p class="a2-screen__ok"><?= t($c['final']['booked']) ?></p>
        </div>
        <div class="a2-screen__foot"></div>
      </div>
    </div>
  </div>
</section>

</div>
