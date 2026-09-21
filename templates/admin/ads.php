<?php
/** Emplacements publicitaires. @var array $slots @var string $client @var string $notice */
use App\Core\Csrf;
use App\Services\I18n;
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.ads')) ?></h1>
    <p>Compte AdSense : <?= $client !== ''
        ? e($client)
        : '<em>non configuré — <a href="/admin/cles-api">Clés d’API</a></em>' ?></p>
  </div>
</div>

<?php if ($notice !== ''): ?><div class="notice notice-ok" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($client === ''): ?>
  <div class="notice notice-wait">
    Sans identifiant éditeur, les emplacements affichent le cadre en pointillés de la maquette.
    Il se saisit dans <a href="/admin/cles-api">Clés d’API</a>. Les réglages ci-dessous restent actifs.
  </div>
<?php endif; ?>

<form method="post" class="admin-card">
  <?= Csrf::field('admin-ads') ?>
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Emplacement</th><th>Format</th><th>Identifiant</th><th>Diffusion</th><th>Actif</th></tr></thead>
      <tbody>
        <?php foreach ($slots as $name => $slot): ?>
          <tr>
            <td><span class="t"><?= e($slot['label']) ?></span><br><span class="s"><?= e($name) ?></span></td>
            <td class="s"><?= e($slot['format']) ?></td>
            <td><?php
              if ($slot['slot'] === '') {
                  echo '<span class="state state-neutral">—</span>';
              } else {
                  echo '<span class="state state-ok">' . e($slot['slot']) . '</span>';
                  if ($slot['inherited']) {
                      echo '<br><span class="s">unité par défaut</span>';
                  }
              }
            ?></td>
            <td class="s"><?php
              // Ce que le site contrôle. Le remplissage, lui, dépend d'AdSense.
              if (!$slot['enabled']) {
                  echo '<span class="state state-neutral">désactivé ici</span>';
              } elseif ($client === '') {
                  echo '<span class="state state-wait">identifiant éditeur manquant</span>';
              } elseif ($slot['slot'] === '') {
                  echo '<span class="state state-wait">aucune unité, ni propre ni par défaut</span>';
              } else {
                  echo '<span class="state state-ok">code posé</span>';
              }
            ?></td>
            <td>
              <label class="check">
                <input type="checkbox" name="slot_<?= e($name) ?>" value="1" <?= $slot['enabled'] ? 'checked' : '' ?>>
                <span class="visually-hidden"><?= e($slot['label']) ?></span>
              </label>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button type="submit" class="btn btn-coral" style="margin-top:18px"><?= e(I18n::t('admin.save')) ?></button>
</form>

<div class="admin-card" style="margin-top:22px">
  <h2 style="margin-top:0">« Code posé » et pourtant aucune annonce ?</h2>
  <p class="s">
    Le site ne maîtrise que la pose du code. Lorsqu’AdSense n’a rien à servir, l’emplacement se
    replie de lui-même : la page ne garde pas d’espace vide. Quatre conditions restent du côté
    de Google :
  </p>
  <ol class="s" style="margin:10px 0 0; padding-left:20px; line-height:1.7">
    <li><strong>Le consentement du visiteur.</strong> Tant qu’il n’a pas cliqué « Tout accepter »
        dans le bandeau, aucun script publicitaire n’est chargé et le cadre en pointillés reste
        affiché. Pour vérifier vous-même : effacez les cookies du site, rechargez, acceptez.</li>
    <li><strong>Le domaine doit être approuvé.</strong> AdSense ne diffuse que sur les sites listés
        et validés dans <em>AdSense → Sites</em>. Rien ne s’affiche sur un domaine inconnu, ni en
        local, ni sur une préproduction.</li>
    <li><strong>Une unité neuve met du temps à se remplir.</strong> Comptez de quelques heures à
        48 h après sa création avant les premières impressions.</li>
    <li><strong>Un bloqueur de publicité</strong> dans votre navigateur suffit à tout masquer :
        testez en navigation privée, extensions désactivées.</li>
  </ol>
  <p class="s" style="margin-top:12px">
    Un emplacement laissé vide dans <a href="/admin/cles-api">Clés d’API</a> reprend
    automatiquement l’<strong>unité par défaut</strong> : une seule unité « Display » responsive
    suffit donc à couvrir le site. Renseigner une unité propre à un emplacement reste préférable
    si vous voulez que les statistiques AdSense les distinguent. Seul l’emplacement
    <em>In-feed liste</em> demande une attention particulière : avec une unité « Display »,
    laissez vide la clé de mise en page.
  </p>
</div>
