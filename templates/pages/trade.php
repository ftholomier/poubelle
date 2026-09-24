<?php
/**
 * Fiche métier.
 *
 * Les titres de section reprennent les questions que l'on pose à un moteur de
 * recherche — que fait un régisseur son, comment le devenir, quel salaire —
 * parce que c'est à elles que la fiche répond.
 *
 * @var array  $trade     fiche complète
 * @var array  $family    sa famille
 * @var string $pay       fourchette mise en forme, ou vide
 * @var array  $jobs      annonces du site correspondantes
 * @var array  $partners  offres partenaires correspondantes
 * @var array  $profiles  profils de l'annuaire correspondants
 * @var array  $related   métiers proches (lignes d'index)
 * @var array  $counts    slug => annonces en ligne
 */
use App\Core\View;
use App\Services\I18n;
use App\Services\Trades;
use App\Support\Icon;

$name = (string) $trade['name'];
$inline = Trades::inline($name);
$de = Trades::de($name) . $inline;          // « de régisseur son », « d’accessoiriste »
$query = (string) (Trades::partnerCriteria($trade)['q'] ?? $name);
$jobsUrl = I18n::url('/offres') . '?q=' . rawurlencode($query);
$cvUrl = I18n::url('/cv') . '?q=' . rawurlencode($query);
$brief = (array) $trade['brief'];
$siblings = array_values(array_filter(
    Trades::byFamily()[(string) $trade['family']] ?? [],
    static fn(array $row) => $row['slug'] !== $trade['slug'],
));
$offers = count($jobs) + count($partners);
?>
<div class="container">
  <a class="back-link" href="<?= e(I18n::url('/metiers')) ?>"><?= Icon::svg('arrow-l', 16) ?><?= e(I18n::t('trade.back')) ?></a>

  <div class="layout-detail">
    <article class="detail-main">
      <div class="card card-lg" data-reveal>
        <header class="trade-head tone-<?= e($family['tone']) ?>">
          <span class="trade-icon trade-icon-lg"><?= Icon::svg($family['icon'], 28, 'currentColor', 2) ?></span>
          <div>
            <p class="trade-kicker">
              <?= e(I18n::t('trade.kicker')) ?> ·
              <a href="<?= e(I18n::url('/metiers')) ?>#famille-<?= e((string) $family['key']) ?>"><?= e($family['name']) ?></a>
            </p>
            <h1 class="h1-sub"><?= e($name) ?></h1>
          </div>
        </header>

        <p class="trade-lede"><?= e((string) $trade['intro']) ?></p>

        <div class="meta-row">
          <?php if ($pay !== ''): ?>
            <span class="tag tag-ink"><?= Icon::svg('euro', 14, '#fff', 2) ?><?= e($pay) ?></span>
          <?php endif; ?>
          <?php if ($offers > 0): ?>
            <a class="tag tag-teal" href="#offres">
              <?= e(count($jobs) + count($partners) === 1 ? I18n::t('trades.job_one') : I18n::t('trades.job_many', $offers)) ?>
            </a>
          <?php endif; ?>
        </div>

        <div class="prose trade-body">
          <?php if ((array) $trade['missions'] !== []): ?>
            <h2><?= e(I18n::t('trade.h_missions', $inline)) ?></h2>
            <ul>
              <?php foreach ((array) $trade['missions'] as $line): ?><li><?= e((string) $line) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if (trim((string) $trade['day']) !== ''): ?>
            <h2><?= e(I18n::t('trade.h_day')) ?></h2>
            <p><?= e((string) $trade['day']) ?></p>
          <?php endif; ?>

          <?php // Au milieu du texte, après les deux sections les plus lues :
                // c'est là que l'annonce est vue sans couper une explication. ?>
          <?= View::partial('partials/ad', ['slot' => 'trade_inline']) ?>

          <?php if ((array) $trade['skills'] !== []): ?>
            <h2><?= e(I18n::t('trade.h_skills')) ?></h2>
            <ul>
              <?php foreach ((array) $trade['skills'] as $line): ?><li><?= e((string) $line) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if (trim((string) $trade['training']) !== ''): ?>
            <h2><?= e(I18n::t('trade.h_training', $inline)) ?></h2>
            <p><?= e((string) $trade['training']) ?></p>
            <?php if ((array) $trade['schools'] !== []): ?>
              <h3><?= e(I18n::t('trade.h_schools')) ?></h3>
              <ul>
                <?php foreach ((array) $trade['schools'] as $line): ?><li><?= e((string) $line) ?></li><?php endforeach; ?>
              </ul>
            <?php endif; ?>
          <?php endif; ?>

          <?php if (trim((string) $trade['statut']) !== ''): ?>
            <h2><?= e(I18n::t('trade.h_status')) ?></h2>
            <p><?= e((string) $trade['statut']) ?></p>
          <?php endif; ?>

          <h2><?= e(I18n::t('trade.h_pay', $inline)) ?></h2>
          <?php if ($pay !== ''): ?>
            <p class="trade-pay"><?= Icon::svg('euro', 18, 'currentColor', 2.2) ?><strong><?= e($pay) ?></strong></p>
          <?php endif; ?>
          <?php if (trim((string) ($trade['pay']['note'] ?? '')) !== ''): ?>
            <p><?= e((string) $trade['pay']['note']) ?></p>
          <?php elseif ($pay === ''): ?>
            <p><?= e(I18n::t('trade.pay_none')) ?></p>
          <?php endif; ?>
          <p class="trade-disclaimer"><?= e(I18n::t('trade.pay_disclaimer')) ?></p>

          <?php if (trim((string) $trade['career']) !== ''): ?>
            <h2><?= e(I18n::t('trade.h_career')) ?></h2>
            <p><?= e((string) $trade['career']) ?></p>
          <?php endif; ?>

          <?php if ((array) $trade['faq'] !== []): ?>
            <h2><?= e(I18n::t('trade.h_faq')) ?></h2>
            <div class="trade-faq">
              <?php foreach ((array) $trade['faq'] as $item): ?>
                <?php if (trim((string) ($item['q'] ?? '')) === '') { continue; } ?>
                <h3><?= e((string) $item['q']) ?></h3>
                <p><?= e((string) ($item['a'] ?? '')) ?></p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </article>

    <aside class="detail-aside">
      <div class="card card-dark trade-brief" data-reveal>
        <h2 class="card-title"><?= e(I18n::t('trade.brief')) ?></h2>
        <dl>
          <?php if ($pay !== ''): ?>
            <dt><?= e(I18n::t('trade.brief_pay')) ?></dt><dd><?= e($pay) ?></dd>
          <?php endif; ?>
          <?php foreach (['status' => 'trade.brief_status', 'training' => 'trade.brief_training',
                          'sectors' => 'trade.brief_sectors'] as $field => $label): ?>
            <?php if (trim((string) ($brief[$field] ?? '')) !== ''): ?>
              <dt><?= e(I18n::t($label)) ?></dt><dd><?= e((string) $brief[$field]) ?></dd>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if (trim((string) $trade['rome']) !== ''): ?>
            <dt><?= e(I18n::t('trade.brief_rome')) ?></dt><dd><?= e((string) $trade['rome']) ?></dd>
          <?php endif; ?>
          <?php if (trim((string) $trade['name_f']) !== '' && $trade['name_f'] !== $trade['name']): ?>
            <dt><?= e(I18n::t('trade.brief_f')) ?></dt><dd><?= e((string) $trade['name_f']) ?></dd>
          <?php endif; ?>
        </dl>
        <a class="btn btn-coral btn-block" href="<?= e($jobsUrl) ?>"><?= e(I18n::t('trade.see_jobs')) ?></a>
        <a class="btn btn-ghost-light btn-block" style="margin-top:9px"
           href="<?= e(I18n::url('/deposer-un-cv')) ?>"><?= e(I18n::t('trade.post_cv')) ?></a>
      </div>

      <?php if ($siblings !== []): ?>
        <div class="card" data-reveal>
          <h2 class="card-title"><?= e(I18n::t('trade.family_more', $family['name'])) ?></h2>
          <ul class="trade-siblings">
            <?php foreach ($siblings as $row): ?>
              <li><a href="<?= e(I18n::url('/metiers/' . $row['slug'])) ?>"><?= e($row['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php // En bas de colonne, loin des boutons : une annonce collée à un
            // bouton récolte des clics par erreur. ?>
      <?= View::partial('partials/ad', ['slot' => 'trade_side']) ?>
    </aside>
  </div>

  <section class="section" id="offres">
    <div class="section-head">
      <h2><?= e(I18n::t('trade.h_jobs', $de)) ?></h2>
      <a class="btn btn-ghost btn-sm" href="<?= e($jobsUrl) ?>"><?= e(I18n::t('trade.all_jobs', $de)) ?> <?= Icon::svg('arrow-r', 15) ?></a>
    </div>

    <?php if ($jobs === [] && $partners === []): ?>
      <div class="card empty">
        <p><?= e(I18n::t('trade.no_jobs', $de)) ?></p>
        <a class="btn btn-coral" href="<?= e(I18n::url('/deposer-un-cv')) ?>"><?= e(I18n::t('trade.post_cv')) ?></a>
      </div>
    <?php else: ?>
      <?php if ($jobs !== []): ?>
        <div class="result-list">
          <?php foreach ($jobs as $job): ?><?= View::partial('partials/job-row', ['job' => $job]) ?><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($partners !== []): ?>
        <h3 class="trade-partners"><?= Icon::svg('globe', 16, '#6B6590', 2) ?> <?= e(I18n::t('trade.h_partners')) ?></h3>
        <div class="result-list">
          <?php foreach ($partners as $job): ?><?= View::partial('partials/job-row', ['job' => $job]) ?><?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <?php if ($profiles !== []): ?>
    <section class="section">
      <div class="section-head">
        <h2><?= e(I18n::t('trade.h_profiles', $de)) ?></h2>
        <a class="btn btn-ghost btn-sm" href="<?= e($cvUrl) ?>"><?= e(I18n::t('trade.all_profiles')) ?> <?= Icon::svg('arrow-r', 15) ?></a>
      </div>
      <div class="grid-cv grid-keep">
        <?php foreach ($profiles as $cv): ?><?= View::partial('partials/cv-card', ['cv' => $cv]) ?><?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($related !== []): ?>
    <section class="section">
      <div class="section-head"><h2><?= e(I18n::t('trade.h_related')) ?></h2></div>
      <div class="grid-trades">
        <?php foreach ($related as $row): ?>
          <?= View::partial('partials/trade-tile', [
                'row' => $row, 'family' => Trades::family((string) $row['family']),
                'count' => $counts[$row['slug']] ?? 0,
              ]) ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <div class="trade-cta">
    <div class="card card-coral-soft" data-reveal>
      <h2><?= e(I18n::t('trade.cta_cv_title', $inline)) ?></h2>
      <p><?= e(I18n::t('trade.cta_cv_note')) ?></p>
      <a class="btn btn-coral" href="<?= e(I18n::url('/deposer-un-cv')) ?>"><?= e(I18n::t('cta.post_cv')) ?></a>
    </div>
    <div class="card card-violet-soft" data-reveal>
      <h2><?= e(I18n::t('trade.cta_job_title')) ?></h2>
      <p><?= e(I18n::t('trade.cta_job_note')) ?></p>
      <a class="btn btn-violet" href="<?= e(I18n::url('/deposer-une-annonce')) ?>"><?= e(I18n::t('cta.post_job')) ?></a>
    </div>
  </div>
</div>
