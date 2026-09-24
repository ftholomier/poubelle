<?php
/**
 * Matrice de traduction.
 *
 * @var array  $matrix
 * @var bool   $available
 * @var string $notice
 * @var bool   $noticeOk
 * @var array  $budget   réglages du plafond, consommation, parts du jour
 * @var array  $pending  caractères restant à traduire
 */
use App\Core\Csrf;
use App\Services\I18n;

$cls = ['source' => 'state-neutral', 'fresh' => 'state-ok', 'stale' => 'state-wait', 'missing' => 'state-err'];
$lbl = ['source' => 'admin.state_source', 'fresh' => 'admin.state_fresh',
        'stale' => 'admin.state_stale', 'missing' => 'admin.state_missing'];
$languages = I18n::languages();
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.translations')) ?></h1>
    <p>Le français est la langue pivot. Une modification de la source rend les traductions obsolètes.</p>
  </div>
  <?php if ($available): ?>
    <form method="post"><?= Csrf::field('admin-i18n') ?>
      <input type="hidden" name="action" value="translate">
      <button type="submit" class="btn btn-coral btn-sm"><?= e(I18n::t('admin.translate_all')) ?></button>
    </form>
  <?php endif; ?>
</div>

<?php if ($notice !== ''): ?>
  <div class="notice <?= ($noticeOk ?? true) ? 'notice-ok' : 'notice-err' ?>" role="status">
    <?= e($notice) ?>
    <?php if (!($noticeOk ?? true)): ?>
      <br>
      Un refus porte presque toujours sur le projet Google, pas sur le site : l’API
      <em>Cloud Translation</em> n’est pas activée, la clé est restreinte à une autre API,
      ou la facturation n’est pas active sur le projet. Le bouton
      <strong>Tester la connexion</strong> de <a href="/admin/cles-api">Clés d’API</a> renvoie
      le message de Google en un clic.
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php if (!$available): ?>
  <div class="notice notice-wait"><?= e(I18n::t('admin.translate_unavailable')) ?></div>
<?php endif; ?>

<?php
$n = static fn(int $v): string => number_format($v, 0, ',', ' ');
$usage = $budget['usage'];
$monthly = (int) $budget['monthly'];
$pct = static fn(int $used, int $limit): int => $limit > 0 ? (int) min(100, round($used * 100 / $limit)) : 0;
// Au rythme régulier des lots — leur part d'un plafond mensuel —, combien
// de mois pour le durable restant. La part du jour, qui grossit en fin de
// mois quand il reste du budget, fausserait l'estimation.
$perMonth = (int) floor($monthly * \App\Services\TranslationBudget::BATCH_SHARE);
$months = $perMonth > 0 ? $pending['durable'] / $perMonth : 0.0;
?>
<div class="admin-card">
  <div class="admin-head" style="margin-bottom:12px"><h2>Plafond de traduction</h2></div>
  <p class="secret-help" style="margin-top:0">
    Google offre <?= $n(\App\Services\TranslationBudget::FREE_MONTHLY) ?> caractères par mois, puis facture
    environ 20 $ le million. Chaque envoi est compté avant de partir : au-delà du plafond, plus rien ne part,
    et le site sert le français. Réparti sur le mois, le plafond donne une part par jour ; la tâche planifiée
    et le bouton « Tout traduire » en prennent les trois quarts, le reste attend les visiteurs qui ouvrent une
    page dans leur langue.
  </p>

  <?php if ($monthly > 0): ?>
    <div class="budget-meters">
      <div class="budget-meter">
        <div class="budget-meter-head">
          <span>Ce mois-ci</span>
          <strong><?= $n($usage['month_chars']) ?> / <?= $n($monthly) ?> caractères</strong>
        </div>
        <div class="budget-bar"><span style="width:<?= $pct($usage['month_chars'], $monthly) ?>%"></span></div>
      </div>
      <div class="budget-meter">
        <div class="budget-meter-head">
          <span>Aujourd’hui</span>
          <strong><?= $n($usage['day_chars']) ?> / <?= $n((int) $budget['daily']) ?> caractères,
            dont <?= $n((int) $budget['batch']) ?> pour les lots</strong>
        </div>
        <div class="budget-bar"><span style="width:<?= $pct($usage['day_chars'], (int) $budget['daily']) ?>%"></span></div>
      </div>
    </div>
  <?php else: ?>
    <div class="notice notice-wait" style="margin:0 0 14px">
      Aucun plafond : la traduction n’est pas limitée, et Google facture au-delà de sa franchise.
      Ce mois-ci : <?= $n($usage['month_chars']) ?> caractères envoyés.
    </div>
  <?php endif; ?>

  <form method="post" class="budget-form">
    <?= Csrf::field('admin-i18n') ?>
    <input type="hidden" name="action" value="budget">
    <div>
      <label class="label" for="f-monthly">Plafond mensuel, en caractères</label>
      <input class="input" type="text" inputmode="numeric" id="f-monthly" name="monthly"
             value="<?= e($n($monthly)) ?>" autocomplete="off">
      <span class="opt">0 : sans plafond. <?= $n(490000) ?> laisse une marge sous la franchise gratuite.</span>
    </div>
    <div>
      <label class="label" for="f-measured">Déjà consommé ce mois-ci selon Google</label>
      <input class="input" type="text" inputmode="numeric" id="f-measured" name="measured" value=""
             placeholder="<?= e($n($usage['month_chars'])) ?>" autocomplete="off">
      <span class="opt">Facultatif : le chiffre de la console Google Cloud recale le compteur du site.</span>
    </div>
    <label class="check">
      <input type="checkbox" name="spread" value="1" <?= $budget['spread'] ? 'checked' : '' ?>>
      <span>Répartir sur les jours du mois, plutôt que tout dépenser dès les premiers jours</span>
    </label>
    <button type="submit" class="btn btn-coral btn-sm">Enregistrer</button>
  </form>

  <p class="muted" style="font-size:13.5px;margin:16px 0 0">
    Reste à traduire : <strong><?= $n($pending['durable']) ?></strong> caractères pour l’interface, les pages
    et les fiches métiers, puis <strong><?= $n($pending['perishable']) ?></strong> pour les offres en ligne.
    <?php if ($months > 0): ?>
      Au rythme du plafond, comptez environ
      <?= $months >= 1.5 ? e($n((int) round($months)) . ' mois') : e($n((int) max(1, ceil($months * 30))) . ' jour(s)') ?>
      pour le premier ensemble ; les langues se complètent l’une après l’autre, l’anglais d’abord.
    <?php endif; ?>
  </p>
</div>

<div class="admin-card">
  <h2>Annonces, profils et fiches métiers</h2>
  <p class="muted" style="font-size:14px;margin:6px 0 16px">
    Une fiche modifiée est automatiquement remise en file : la traduction en cache
    est indexée sur le texte source. Une fiche consultée dans une langue non encore
    traduite est traduite à la volée, puis servie depuis le cache aux suivants.
  </p>
  <div class="table-scroll">
    <table class="admin-table">
      <thead>
        <tr><th>Type</th><th>Publiées</th>
          <?php foreach ($languages as $code => $meta): if ($code === 'fr') continue; ?>
            <th><?= e(strtoupper((string) $code)) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach (['job' => 'Offres d’emploi', 'cv' => 'CV', 'trade' => 'Fiches métiers',
                        'family' => 'Familles de métiers'] as $type => $label): ?>
          <?php $row = $records[$type] ?? ['enabled' => false, 'total' => 0, 'langs' => []]; ?>
          <tr>
            <td><span class="t"><?= e($label) ?></span><br>
              <span class="state <?= $row['enabled'] ? 'state-ok' : 'state-neutral' ?>">
                <?= $row['enabled'] ? 'Traduction active' : 'Désactivée' ?>
              </span>
            </td>
            <td class="s"><?= (int) $row['total'] ?></td>
            <?php foreach ($languages as $code => $meta): if ($code === 'fr') continue; ?>
              <?php $n = (int) ($row['langs'][$code] ?? 0); $total = (int) $row['total']; ?>
              <td>
                <span class="state <?= $total > 0 && $n >= $total ? 'state-ok' : ($n > 0 ? 'state-wait' : 'state-neutral') ?>">
                  <?= $n ?>/<?= $total ?>
                </span>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="muted" style="font-size:13px;margin-top:14px">
    Les CV sont exclus par défaut : ce sont des textes personnels, et un CV se lit
    d’ordinaire dans sa langue. Réglable par <code>i18n.translate_cv</code>. Les fiches
    métiers et leurs familles sont traduites, réglage <code>i18n.translate_trades</code> :
    environ 4 000 caractères par fiche et par langue, soit près de 1,5 million de caractères
    pour les soixante fiches dans les six langues, une fois pour toutes.
  </p>
</div>

<div class="admin-card">
  <h2>Pages éditoriales</h2>
  <div class="table-scroll">
    <table class="admin-table">
      <thead>
        <tr><th>Page</th><?php foreach ($languages as $code => $meta): ?>
          <th><?= e(strtoupper((string) $code)) ?></th><?php endforeach; ?></tr>
      </thead>
      <tbody>
        <?php foreach ($matrix as $row): ?>
          <tr>
            <td><span class="t"><?= e(str_excerpt((string) $row['title'], 46)) ?></span><br>
                <span class="s">/<?= e($row['slug']) ?></span></td>
            <?php foreach ($languages as $code => $meta): $state = $row['states'][$code]['state'] ?? 'missing'; ?>
              <td><span class="state <?= e($cls[$state] ?? 'state-neutral') ?>"><?= e(I18n::t($lbl[$state] ?? 'admin.state_missing')) ?></span></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
