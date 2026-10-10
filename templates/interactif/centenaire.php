<?php
/** Centenaire (maquette « Centenaire »). Variables : $target, $moments, $onze, $results, $reveal, $votes */
$pub = count(array_filter($moments, fn ($m) => $m['open']));
$slots = [['G', 50, 88], ['D', 15, 68], ['D', 38, 72], ['D', 62, 72], ['D', 85, 68], ['M', 25, 47], ['M', 50, 51], ['M', 75, 47], ['A', 20, 22], ['A', 50, 16], ['A', 80, 22]];
$posName = ['G' => t('Gardien'), 'D' => t('Défenseur'), 'M' => t('Milieu'), 'A' => t('Attaquant')];
?>
<section class="chero">
  <div class="wrap chero__inner">
    <div class="stack" style="gap:20px">
      <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('14 juin 1928 → 14 juin 2028')) ?></span>
      <h1 class="chero__title"><?= e(t('Cent')) ?><br><span class="yellow"><?= e(t('ans.')) ?></span></h1>
      <p class="lead" style="max-width:40ch"><?= e(t("Jusqu'au centenaire, le musée dévoile un à un 100 moments de l'histoire du club, et vous invite à composer le Onze de légende.")) ?></p>
      <div class="row" style="gap:12px;flex-wrap:wrap">
        <a class="btn btn--yellow" href="#moments"><?= e(t('100 moments')) ?></a>
        <a class="btn btn--ghost-light" href="#onze"><?= e(t('Voter le Onze')) ?></a>
        <a class="btn btn--ghost-light" href="<?= e(url('/interactif/album/')) ?>"><?= e(t("L'album")) ?></a>
      </div>
    </div>
    <?= countdown_html($target) ?>
  </div>
</section>

<section id="moments" class="wrap cmoments">
  <div class="between" style="align-items:flex-end;gap:20px;flex-wrap:wrap">
    <div class="stack" style="gap:10px;flex:1 1 420px;min-width:0"><span class="eyebrow"><?= e(t('Jusqu’au 14 juin 2028')) ?></span><h2 class="h-section"><?= e(t('100 ans, 100 moments')) ?></h2></div>
    <div class="stack" style="gap:6px;min-width:260px"><b class="cmoments__count"><?= $pub ?> / 100 <?= e(t('publiés')) ?></b><div class="abox__bar abox__bar--light"><span style="width:<?= $pub ?>%"></span></div></div>
  </div>
  <?= \App\Core\View::partial('interactif/moments-grid', ['moments' => $moments]) ?>
</section>

<section id="onze" class="conze" data-onze>
  <script type="application/json" data-onze-data><?= json_encode(['cands' => array_map(fn ($c) => [$c['id'], $c['name'], $c['line']], $onze), 'slots' => $slots], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <div class="wrap conze__inner">
    <div class="stack" style="gap:20px">
      <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Vote du centenaire')) ?></span>
      <h2 class="h-section" style="color:var(--cream)"><?= e(t('Composez le Onze de légende')) ?></h2>
      <p class="lead" style="max-width:42ch"><?= e(t('Cliquez un poste sur le terrain, choisissez un joueur. Le Onze du public sera dévoilé le 14 juin 2028.')) ?></p>
      <div class="opick">
        <b class="opick__t" data-onze-title></b>
        <label class="sr-only" for="onzeq"><?= e(t('Chercher un joueur')) ?></label>
        <input id="onzeq" type="search" placeholder="<?= e(t('Chercher un joueur…')) ?>" data-onze-q autocomplete="off">
        <div class="opick__list" data-onze-cands></div>
      </div>
      <div class="row" style="gap:12px;flex-wrap:wrap;align-items:center">
        <button type="button" class="qbtn" data-onze-vote disabled></button>
        <button type="button" class="linkbtn" data-onze-reset><?= e(t('Recommencer')) ?></button>
      </div>
      <div class="oresults" data-onze-results<?= $results || (!empty($reveal) && $votes) ? '' : ' hidden' ?>>
        <b class="oresults__t"><?= e(empty($reveal) ? t('Le Onze du public, en direct') : t('Votes enregistrés')) ?> · <span data-onze-voters><?= (int) $votes ?></span> <?= e(t('votants')) ?></b>
        <?php if (!empty($reveal)): ?><p class="oresults__wait"><?= e(t('Résultats dévoilés le {d}.', ['d' => date_fr($reveal)])) ?></p><?php endif; ?>
        <div data-onze-rows>
          <?php foreach ($results as $r): ?>
            <div class="orow"><span><?= e($r['name']) ?></span><span class="orow__bar"><span style="width:<?= (int) $r['pct'] ?>%"></span></span><b><?= (int) $r['pct'] ?>%</b></div>
          <?php endforeach; ?>
        </div>
        <a class="linkbtn yellow" href="<?= e(url('/faire-un-don/')) ?>">♥ <?= e(t('Soutenir le centenaire')) ?></a>
      </div>
    </div>
    <div class="opitch" data-onze-pitch>
      <span class="pitch__line pitch__line--box"></span><span class="pitch__line pitch__line--half"></span><span class="pitch__line pitch__line--circle"></span><span class="pitch__line pitch__line--area-b"></span>
      <?php foreach ($slots as $i => [$pos, $x, $y]): ?>
        <button type="button" class="oslot<?= $i === 0 ? ' is-cur' : '' ?>" style="left:<?= $x ?>%;top:<?= $y ?>%" data-slot="<?= $i ?>" data-line="<?= $pos ?>">
          <span class="oslot__c"><?= e($pos) ?></span><span class="oslot__l"><?= e($posName[$pos]) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
</section>
