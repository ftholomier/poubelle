<?php
/**
 * Offres externes.
 * @var array $rows @var array $settings @var string $query @var string $exclude
 * @var bool $filter @var bool $active @var string $notice
 */
use App\Core\Csrf;
use App\Services\I18n;
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.sources')) ?></h1>
    <p><?= e(I18n::t('admin.sources_note')) ?></p>
  </div>
  <form method="post"><?= Csrf::field('admin-sources') ?>
    <button type="submit" name="action" value="clear" class="btn btn-ghost-light btn-sm">
      <?= e(I18n::t('admin.clear_cache')) ?>
    </button>
  </form>
</div>

<?php if ($notice !== ''): ?><div class="notice notice-ok" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if (!$active): ?>
  <div class="notice notice-wait">
    Aucune source n'est configurée : la liste d'offres n'affiche que les annonces déposées sur le site.
    Renseignez les clés dans <code>config/secrets.php</code>.
  </div>
<?php endif; ?>

<form method="post">
  <?= Csrf::field('admin-sources') ?>

<div class="admin-card">
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Source</th><th>Clés</th><th>En cache</th><th>Âge du cache</th><th>Active</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $key => $row): ?>
          <tr>
            <td><span class="t"><?= e($row['name']) ?></span><br><span class="s"><?= e($key) ?></span></td>
            <td>
              <span class="state <?= $row['configured'] ? 'state-ok' : 'state-neutral' ?>">
                <?= e($row['configured'] ? I18n::t('admin.source_ready') : I18n::t('admin.source_missing')) ?>
              </span>
            </td>
            <td class="s"><?= $row['cached'] > 0 ? (int) $row['cached'] . ' offre(s)' : '—' ?></td>
            <td class="s"><?= $row['age'] >= 0 ? (int) floor($row['age'] / 60) . ' min' : '—' ?></td>
            <td>
              <label class="check">
                <input type="checkbox" name="source_<?= e($key) ?>" value="1"
                       <?= $row['enabled'] ? 'checked' : '' ?> <?= $row['configured'] ? '' : 'disabled' ?>>
                <span class="visually-hidden"><?= e($row['name']) ?></span>
              </label>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="admin-card">
  <h2 style="margin-top:0">Ce que l’on demande aux agrégateurs</h2>
  <p class="s">
    Les mots-clés envoyés à Jooble, Adzuna et aux autres — exactement ce que vous taperiez dans
    leur propre moteur. Séparez les métiers par <code>or</code> pour qu’ils soient cherchés
    séparément.
  </p>
  <label class="field" style="margin-top:12px">
    <span class="label" style="font-weight:600">Mots-clés recherchés</span>
    <textarea class="input" name="query" rows="5" spellcheck="false"
              style="font-size:13px"><?= e($query) ?></textarea>
  </label>

  <h2 style="margin-top:26px">Ne garder que le secteur</h2>
  <p class="s">
    Aucun agrégateur ne sait filtrer par branche : « technicien », « production » ou « montage »
    y ramènent autant d’usines que de plateaux. Le tri se fait donc ici, sur l’intitulé et le
    résumé de chaque offre remontée — statut d’intermittent, métiers du plateau, de l’image, du
    son, de la scène et de l’événementiel. Les annonces déposées sur le site ne sont jamais
    filtrées.
  </p>
  <label class="check" style="margin:12px 0">
    <input type="checkbox" name="filter" value="1" <?= $filter ? 'checked' : '' ?>>
    <span>Ne remonter que les offres du spectacle, de l’audiovisuel et de l’événementiel</span>
  </label>

  <label class="field" style="margin-top:10px">
    <span class="label" style="font-weight:600">Mots à écarter en plus</span>
    <span class="s">Séparés par des virgules. Pour bannir un intitulé qui reviendrait sans
      relever du secteur.</span>
    <input class="input" type="text" name="exclude" value="<?= e($exclude) ?>"
           placeholder="croupier, hôtesse de l’air" spellcheck="false">
  </label>

  <div class="save-bar" style="margin-top:20px">
    <span class="save-bar-note">Enregistrer vide le cache : les sources seront réinterrogées.</span>
    <button type="submit" class="btn btn-coral"><?= e(I18n::t('admin.save')) ?></button>
  </div>
</form>

<div class="admin-card">
  <h2>Réglages</h2>
  <p class="muted" style="font-size:14px;margin:6px 0 16px">
    Repris de l'ancienne installation WordPress, modifiables dans <code>config/config.php</code>.
    Les mots-clés et le tri par secteur, eux, se règlent ci-dessus.
  </p>
  <table class="admin-table">
    <tbody>
      <tr><td class="t">Offres externes avant les annonces du site</td>
          <td class="s"><?= (int) ($settings['before'] ?? 0) ?></td></tr>
      <tr><td class="t">Offres externes après</td>
          <td class="s"><?= (int) ($settings['after'] ?? 0) ?></td></tr>
      <tr><td class="t">Durée du cache</td>
          <td class="s"><?= (int) floor((int) ($settings['cache_ttl'] ?? 0) / 60) ?> min</td></tr>
      <tr><td class="t">Pays / lieu par défaut</td>
          <td class="s"><?= e(strtoupper((string) ($settings['country'] ?? ''))) ?> ·
              <?= e((string) ($settings['location'] ?? '')) ?></td></tr>
      <tr><td class="t">Codes ROME (France Travail)</td>
          <td class="s"><?= e(implode(', ', (array) ($settings['france_travail']['rome'] ?? []))) ?></td></tr>
    </tbody>
  </table>
</div>
