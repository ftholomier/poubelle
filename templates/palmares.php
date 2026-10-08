<?php
/** Palmarès du FCSM. Variables : $groups (comp, titles, finals), $count (titre, finale) */
?>
<section class="rhead">
  <div class="wrap rhead__inner">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/interactif/')) ?>"><?= e(t('Interactif')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('Palmarès')) ?></span></nav>
    <span class="eyebrow eyebrow--lg" style="color:var(--navy)"><?= e(t('Depuis 1928')) ?></span>
    <h1 class="rhead__title"><?= e(t('Le palmarès')) ?><br><?= e(t('des Lionceaux')) ?></h1>
    <p class="pal__legend"><span><i style="border-color:#1f8a4c"></i><?= e(t('Gagné')) ?></span><span><i style="border-color:#c0392b"></i><?= e(t('Finaliste, vice-champion ou demi-finaliste')) ?></span></p>
    <p class="pal__sum"><b><?= (int) $count['titre'] ?></b> <?= e(t('titres')) ?> · <b><?= (int) $count['finale'] ?></b> <?= e(t('finales et places de vice-champion')) ?></p>
  </div>
</section>
<div class="wrap rbody pal">
  <?php foreach ($groups as $g): ?>
  <section class="pal__g" data-reveal>
    <h2 class="h-2 pal__comp"><?= e(t($g['comp'])) ?> <span class="pal__n"><?= count($g['titles']) ? count($g['titles']) . '×' : '' ?></span></h2>
    <?php
      $all = [];
      foreach ($g['titles'] as $it) { $all[] = $it + ['st' => 'win', 'tag' => preg_match('/^(championnat|division)/i', $g['comp']) ? t('Champion') : t('Vainqueur')]; }
      foreach ($g['finals'] as $it) { $all[] = $it + ['st' => 'lost', 'tag' => t($g['second'])]; }
      foreach ($g['semis'] as $it) { $all[] = $it + ['st' => 'lost', 'tag' => t('Demi-finaliste')]; }
      usort($all, fn ($a, $b) => $a['year'] <=> $b['year']);
    ?>
    <div class="pal__grid">
      <?php foreach ($all as $it): ?>
      <a class="pal__card pal__card--<?= $it['st'] ?>" href="<?= e($it['href'] ?? $it['seasonHref']) ?>">
        <span class="pal__img"><?php if ($it['image']): ?><img src="<?= e(img($it['image'], 480)) ?>" alt="" loading="lazy"><?php else: ?><span class="pal__cup" aria-hidden="true"><?= $it['st'] === 'win' ? '🏆' : '🥈' ?></span><?php endif; ?><span class="pal__tag"><?= e($it['tag']) ?></span></span>
        <span class="pal__year"><?= (int) $it['year'] ?></span>
        <span class="pal__lab"><?= e($it['label'] ?? t('Saison') . ' ' . $it['season']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
  <p class="muted small"><?= e(t('Palmarès de référence (d’après Wikipédia, vérifié par le musée) complété automatiquement par les finales des fiches matchs. Une erreur, un trophée oublié ? Écrivez-nous.')) ?> <a href="<?= e(url('/contact/')) ?>"><?= e(t('Contact')) ?></a></p>
</div>
<style>
.pal{display:flex;flex-direction:column;gap:40px;padding-bottom:60px}
.pal__sum{font-size:20px;margin:6px 0 0;color:var(--navy)}
.pal__comp{display:flex;align-items:baseline;gap:12px;border-bottom:3px solid var(--yellow,#F6C400);padding-bottom:8px}
.pal__n{font-family:var(--display,Impact,sans-serif);color:var(--blue,#1F3FA8)}
.pal__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-top:16px}
.pal__card{display:flex;flex-direction:column;background:var(--paper,#fffdf7);border:2px solid var(--navy,#0E1F4D);box-shadow:5px 5px 0 var(--navy,#0E1F4D);text-decoration:none;color:var(--navy,#0E1F4D);transition:transform .15s}
.pal__card:hover{transform:translate(-2px,-2px)}
.pal__img{display:grid;place-items:center;aspect-ratio:16/10;background:var(--navy,#0E1F4D);overflow:hidden}
.pal__img img{width:100%;height:100%;object-fit:cover}
.pal__cup{font-size:56px}
.pal__year{font-family:var(--display,Impact,sans-serif);font-size:44px;line-height:1;padding:12px 14px 0;color:var(--navy,#0E1F4D)}
.pal__lab{padding:4px 14px 14px;font-size:14px}
.pal__card--win{border-color:#1f8a4c;box-shadow:5px 5px 0 #1f8a4c}
.pal__card--lost{border-color:#c0392b;box-shadow:5px 5px 0 #c0392b}
.pal__img{position:relative}
.pal__tag{position:absolute;left:8px;top:8px;padding:3px 8px;font:700 11px/1.2 var(--display,Impact,sans-serif);letter-spacing:.06em;text-transform:uppercase;color:#fff}
.pal__card--win .pal__tag{background:#1f8a4c}
.pal__card--lost .pal__tag{background:#c0392b}
.pal__legend{display:flex;gap:18px;flex-wrap:wrap;margin:8px 0 0;font-size:15px}
.pal__legend i{display:inline-block;width:14px;height:14px;margin-right:6px;vertical-align:-2px;border:3px solid}
</style>
