<?php
/**
 * Agenda de la commune : les rendez-vous à venir, puis ceux qui viennent de
 * passer.
 *
 * Les rendez-vous passés ne sont pas jetés. Dans un village, la question la
 * plus fréquente après une manifestation est « c'était quand, déjà ? », et
 * l'agenda de l'an dernier sert à préparer celui de cette année.
 *
 * @var array $page
 * @var array $avenir
 * @var array $passes
 * @var App\Core\View $view
 */
$hero = ($page['hero'] ?? []) + ['titre' => $page['titre'] ?? '', 'image' => ''];

/** Mois abrégé pour la pastille de date, en trois lettres. */
$moisCourt = static function (string $iso): string {
    static $abrege = ['JAN', 'FÉV', 'MAR', 'AVR', 'MAI', 'JUIN',
                      'JUIL', 'AOÛT', 'SEPT', 'OCT', 'NOV', 'DÉC'];
    $mois = (int) substr($iso, 5, 2);
    return $abrege[max(1, min(12, $mois)) - 1];
};

/** Un événement sur un jour, ou sur plusieurs : la date se dit autrement. */
$quand = static function (array $e): string {
    $debut = (string) ($e['date'] ?? '');
    $fin   = (string) ($e['fin'] ?? '');
    if ($fin === '' || $fin === $debut) {
        return date_texte($debut, true);
    }
    return t('du') . ' ' . date_texte($debut) . ' ' . t('au') . ' ' . date_texte($fin);
};
?>
<?= $view->partial('hero-page', ['hero' => $hero]) ?>

<?php if (!empty($page['sous_titre'])): ?>
<section class="section section--chapo">
  <div class="conteneur conteneur--etroit">
    <p class="chapo reveler"><?= e($page['sous_titre']) ?></p>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e(t('Agenda')) ?></p>
      <h2 class="titre-section"><?= e(t('Les prochains rendez-vous')) ?></h2>
    </div>

    <?php if ($avenir === []): ?>
      <p class="vide reveler"><?= e(t('Aucun rendez-vous n’est annoncé pour l’instant. Le Flash Info et le panneau d’affichage de la mairie relaient les manifestations dès qu’elles sont fixées.')) ?></p>
    <?php else: ?>
      <ul class="evenements">
        <?php foreach ($avenir as $e): ?>
          <li class="evenement reveler">
            <div class="evenement__date" aria-hidden="true">
              <span class="evenement__jour"><?= e(substr((string) ($e['date'] ?? ''), 8, 2)) ?></span>
              <span class="evenement__mois"><?= e($moisCourt((string) ($e['date'] ?? ''))) ?></span>
            </div>
            <div class="evenement__corps">
              <p class="evenement__quand"><?= e($quand($e)) ?><?php if (!empty($e['heure'])): ?><span aria-hidden="true"> · </span><?= e($e['heure']) ?><?php endif; ?></p>
              <h3 class="evenement__titre"><?= e($e['titre'] ?? '') ?></h3>
              <?php if (!empty($e['texte'])): ?><p class="evenement__texte"><?= e($e['texte']) ?></p><?php endif; ?>
              <?php if (!empty($e['lieu'])): ?>
                <p class="evenement__lieu">
                  <span aria-hidden="true"><?= $view->partial('icones', ['nom' => 'adresse']) ?></span><?= e($e['lieu']) ?>
                </p>
              <?php endif; ?>
              <?php if (!empty($e['organisateur'])): ?>
                <p class="evenement__organisateur"><?= e(t('Organisé par')) ?> <?= e($e['organisateur']) ?></p>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<?php if ($passes !== []): ?>
<section class="section section--teinte">
  <div class="conteneur">
    <div class="section__tete reveler">
      <h2 class="titre-section"><?= e(t('C’est passé')) ?></h2>
      <p class="section__chapo"><?= e(t('Les manifestations récentes, gardées ici parce qu’elles reviennent d’une année sur l’autre.')) ?></p>
    </div>
    <ul class="evenements evenements--passes">
      <?php foreach ($passes as $e): ?>
        <li class="evenement evenement--passe reveler">
          <div class="evenement__corps">
            <p class="evenement__quand"><?= e($quand($e)) ?></p>
            <h3 class="evenement__titre"><?= e($e['titre'] ?? '') ?></h3>
            <?php if (!empty($e['lieu'])): ?><p class="evenement__lieu"><?= e($e['lieu']) ?></p><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => $passes !== [] ? 'teinte' : 'blanc']) ?>

<?= $view->partial('bande-cta') ?>
