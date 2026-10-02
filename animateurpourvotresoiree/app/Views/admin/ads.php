<?php
use App\Core\Url;
use App\Services\Ads;
/** @var array $ads @var array $analytics @var string $client @var string $envClient @var string $adsTxt */
?>
<div class="adm-head"><div><h1>Publicité <span class="serif">AdSense</span></h1><p>Le modèle économique du site : réglez ici votre compte Google AdSense, les emplacements d'annonces, ads.txt et la gestion du consentement.</p></div><a class="btn btn-sm" href="https://www.google.com/adsense/" target="_blank" rel="noopener noreferrer">Ouvrir AdSense ↗</a></div>
<form class="form" method="post" action="<?= e(Url::admin('publicite')) ?>" data-dirty-check>
  <?= csrf_field() ?>
  <div class="box">
    <h2>Compte</h2>
    <label class="switch"><input type="checkbox" name="enabled" value="1"<?= !empty($ads['enabled']) ? ' checked' : '' ?>> Afficher la publicité sur le site</label>
    <div class="form-grid mt-1">
      <div class="field"><label for="ad-client">Identifiant éditeur (ca-pub-…)</label><input id="ad-client" type="text" name="client" value="<?= e((string) ($ads['client'] ?? '')) ?>" placeholder="<?= e($envClient ?: 'ca-pub-0000000000000000') ?>"><span class="hint">Vide = valeur du fichier .env (<?= e($envClient ?: 'non définie') ?>). Utilisé actuellement : <strong><?= e($client ?: '—') ?></strong></span></div>
      <div class="field"><label for="ad-label">Mention au-dessus des annonces</label><input id="ad-label" type="text" name="label" maxlength="40" value="<?= e((string) ($ads['label'] ?? 'Publicité')) ?>"></div>
    </div>
    <div class="stack mt-1">
      <label class="switch"><input type="checkbox" name="auto_ads" value="1"<?= !empty($ads['auto_ads']) ? ' checked' : '' ?>> J'utilise aussi les annonces automatiques (Auto ads, à activer dans AdSense)</label>
      <label class="switch"><input type="checkbox" name="test_mode" value="1"<?= !empty($ads['test_mode']) ? ' checked' : '' ?>> Mode test (data-adtest : aucune impression facturée)</label>
    </div>
  </div>
  <div class="box">
    <h2>Consentement (RGPD)</h2>
    <?php foreach (['google' => ['Message de confidentialité Google (recommandé)', 'Exigé par Google pour les visiteurs européens depuis 2024 (message certifié TCF, gratuit). À activer une fois pour toutes dans AdSense › Confidentialité et messages › Réglementations européennes : créez le message pour ce site, cochez le bouton « Ne pas autoriser » (obligatoire en France, CNIL), puis publiez-le. Sans accord, Google peut encore afficher des annonces limitées, sans cookie. Le lien « Gérer les cookies » du pied de page rouvre ce message.'], 'npa' => ['Annonces non personnalisées, sans message', 'Déconseillé : même non personnalisées, les annonces utilisent des cookies (répétition, fraude), ce qui demande l\'accord des visiteurs européens.'], 'own' => ['Bandeau du site', 'Notre bandeau « Tout accepter / Tout refuser / Personnaliser » : la publicité n\'est chargée qu\'avec l\'accord du visiteur (sans accord, aucune annonce). Revenus moindres qu\'avec le message Google.']] as $k => [$l, $d]): ?>
      <label class="check"><input type="radio" name="cmp" value="<?= $k ?>"<?= ($ads['cmp'] ?? 'google') === $k ? ' checked' : '' ?>> <span><strong><?= e($l) ?></strong><br><span class="small muted"><?= e($d) ?></span></span></label>
    <?php endforeach; ?>
    <p class="small muted mt-1">Pour vérifier que le message Google s'affiche, ouvrez <a href="/?fc=alwaysshow&amp;fctype=gdpr" target="_blank" rel="noopener">le site avec ?fc=alwaysshow&amp;fctype=gdpr</a> à la fin de l'adresse : la fenêtre « Autoriser / Ne pas autoriser » doit apparaître.</p>
  </div>
  <div class="box">
    <h2>Emplacements</h2>
    <p class="small muted">Créez des blocs d'annonces « Display » dans AdSense et collez leur identifiant (data-ad-slot). Les identifiants de l'ancien site sont utilisés par défaut.</p>
    <div class="table-wrap" style="box-shadow:none"><table class="tbl"><thead><tr><th>Actif</th><th>Emplacement</th><th>Identifiant du bloc</th><th>Format</th></tr></thead><tbody>
      <?php foreach (Ads::SLOTS as $key => [$label, $default]): $sl = (array) ($ads['slots'][$key] ?? []); ?>
        <tr><td><label class="switch"><input type="checkbox" name="slots[<?= $key ?>][on]" value="1"<?= !empty($sl['on']) ? ' checked' : '' ?>></label></td>
          <td><strong><?= e($label) ?></strong><?php if ($key === 'listing'): ?><span class="t-sub">toutes les <input type="number" name="slots[listing][every]" min="3" max="30" value="<?= (int) ($sl['every'] ?? 8) ?>" style="width:60px"> fiches</span><?php endif; ?></td>
          <td><input class="input" style="min-height:38px" type="text" name="slots[<?= $key ?>][id]" value="<?= e((string) ($sl['id'] ?? '')) ?>" placeholder="<?= e($default) ?>"></td>
          <td><select class="input" style="min-height:38px" name="slots[<?= $key ?>][format]"><?php foreach (['auto' => 'Adaptatif', 'fluid' => 'Dans l\'article', 'rect' => 'Carré 330×330'] as $f => $fl): ?><option value="<?= $f ?>"<?= ($sl['format'] ?? 'auto') === $f ? ' selected' : '' ?>><?= e($fl) ?></option><?php endforeach; ?></select></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="box">
    <h2>ads.txt</h2>
    <p class="small">Fichier servi à <a href="/ads.txt" target="_blank" rel="noopener">/ads.txt</a>. Laissez vide pour la ligne générée automatiquement :</p>
    <pre class="log"><?= e($adsTxt ?: '(identifiant éditeur manquant)') ?></pre>
    <div class="field"><label for="ad-txt">Contenu personnalisé (autres régies…)</label><textarea id="ad-txt" name="ads_txt" rows="4" class="code"><?= e((string) ($ads['ads_txt'] ?? '')) ?></textarea></div>
  </div>
  <div class="box">
    <h2>Mesure d'audience et codes de suivi</h2>
    <p class="small muted">Le site mesure déjà son audience sans cookie. Google Analytics et Meta Pixel ne sont chargés qu'après consentement du visiteur (le bandeau « Tout accepter / Tout refuser / Personnaliser » s'affiche alors automatiquement). N'ajoutez pas de traceur dans les codes HTML ci-dessous : ils se chargent sans demander l'accord.</p>
    <div class="form-grid">
      <div class="field"><label for="an-ga">Google Analytics 4 (G-…)</label><input id="an-ga" type="text" name="ga4" value="<?= e((string) ($analytics['ga4'] ?? '')) ?>"></div>
      <div class="field"><label for="an-px">Meta Pixel (identifiant)</label><input id="an-px" type="text" name="meta_pixel" value="<?= e((string) ($analytics['meta_pixel'] ?? '')) ?>"></div>
    </div>
    <?php $sa = App\Core\Auth::adminCan('env'); ?>
    <div class="field"><label for="an-head">Code HTML dans &lt;head&gt; (vérifications Search Console…)</label><textarea id="an-head" name="head_html" rows="3" class="code"<?= $sa ? '' : ' disabled' ?>><?= e((string) ($analytics['head_html'] ?? '')) ?></textarea></div>
    <div class="field"><label for="an-body">Code HTML en fin de page</label><textarea id="an-body" name="body_html" rows="3" class="code"<?= $sa ? '' : ' disabled' ?>><?= e((string) ($analytics['body_html'] ?? '')) ?></textarea><span class="hint"><?= $sa ? 'Les balises &lt;script&gt; reçoivent automatiquement l\'autorisation de sécurité (nonce). Réservé au super-administrateur.' : 'Modifiable uniquement par le super-administrateur.' ?></span></div>
  </div>
  <div class="form-actions"><button class="btn btn-coral" type="submit">Enregistrer</button></div>
</form>
