<?php
/** Matrice de traduction. @var array $matrix @var bool $available @var string $notice @var bool $noticeOk */
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
