<?php
/** @var array $cfg @var array $state @var bool $connected @var array $locations @var string $locError @var int $imported @var string $redirect @var array $keys @var string $csrf */
$ago = static function (?string $iso): string {
    $ts = $iso ? strtotime($iso) : false;
    if (!$ts) {
        return '';
    }
    $d = time() - $ts;
    return $d < 3600 ? 'il y a ' . max(1, intdiv($d, 60)) . ' min' : ($d < 86400 ? 'il y a ' . intdiv($d, 3600) . ' h' : 'le ' . date('d/m/Y à H:i', $ts));
};
$mode = $cfg['mode'];
$canSync = ($mode === 'places' && $cfg['place_id'] !== '' && $keys['places'] !== '') || ($mode === 'business' && $connected && $cfg['location'] !== '');
$currentLoc = $cfg['account'] . '|' . $cfg['location'];
?>
<?= LD\View::render('admin/reviews-tabs', ['on' => 'google']) ?>

<section class="card">
  <div class="card__head">
    <h2><?= google_g(20) ?> Avis Google</h2>
    <?php if ($canSync): ?>
      <form method="post" action="/admin/avis/google/synchroniser"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn btn--red" type="submit">Synchroniser maintenant</button></form>
    <?php endif; ?>
  </div>
  <?php if ($mode === 'off'): ?>
    <p class="intro">Affiche tes avis Google directement sur le site (page Avis, accueil) avec ta note globale. Choisis un mode ci-dessous : les avis sont ensuite <b>rafraîchis automatiquement chaque jour</b> et restent modifiables ici (thème, mise en avant, masquage).</p>
  <?php else: ?>
    <div class="gstat">
      <?php if (!empty($state['rating'])): ?>
        <p class="gstat__big"><b><?= e(number_format((float) $state['rating'], 1, ',', ' ')) ?></b> <span class="gstat__stars" aria-hidden="true"><?= str_repeat('★', (int) round((float) $state['rating'])) ?></span></p>
        <p><b><?= (int) ($state['count'] ?? 0) ?></b> avis sur Google<?= !empty($state['name']) ? ' pour « ' . e($state['name']) . ' »' : '' ?> · <b><?= $imported ?></b> importé<?= $imported > 1 ? 's' : '' ?> sur le site</p>
      <?php else: ?>
        <p><?= $imported ?> avis Google sur le site.</p>
      <?php endif; ?>
      <p class="small muted">
        <?= !empty($state['synced_at']) ? 'Dernière synchronisation ' . e($ago($state['synced_at'])) . ' · ' : '' ?>synchronisation automatique chaque jour (tâche planifiée)
        <?php if (!empty($state['maps_uri'])): ?> · <a href="<?= e($state['maps_uri']) ?>" target="_blank" rel="noopener">Voir la fiche Google ↗</a><?php endif; ?>
      </p>
      <?php if (!empty($state['error']) && (empty($state['synced_at']) || ($state['error_at'] ?? '') > $state['synced_at'])): ?>
        <p class="alert alert--err">Dernière tentative <?= e($ago($state['error_at'] ?? '')) ?> : <?= e($state['error']) ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<form method="post" action="/admin/avis/google" class="settings-form" data-google>
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <section class="card">
    <div class="card__head"><h2>Mode de connexion</h2></div>
    <div class="modes">
      <label class="mode"><input type="radio" name="mode" value="off"<?= $mode === 'off' ? ' checked' : '' ?>><span><b>Désactivé</b><small>Seuls les avis saisis à la main (et le widget éventuel) sont affichés.</small></span></label>
      <label class="mode"><input type="radio" name="mode" value="places"<?= $mode === 'places' ? ' checked' : '' ?>><span><b>Simple — clé API Google</b><small>5 minutes de réglage. Note globale, nombre d’avis et les <b>5 avis</b> que Google juge les plus pertinents (limite de Google).</small></span></label>
      <label class="mode"><input type="radio" name="mode" value="business"<?= $mode === 'business' ? ' checked' : '' ?>><span><b>Complet — ta fiche Google Business Profile</b><small><b>Tous tes avis</b> et tes réponses. Demande une connexion Google et l’accès à l’API Business Profile (gratuit, validation par Google).</small></span></label>
    </div>
  </section>

  <section class="card" data-mode-block="places"<?= $mode === 'places' ? '' : ' hidden' ?>>
    <div class="card__head"><h2>Clé API Google Places</h2></div>
    <div class="form form--cols">
      <label>Clé API<input type="password" name="places_key" autocomplete="new-password" spellcheck="false" placeholder="<?= e($keys['places'] !== '' ? 'Enregistrée : ' . $keys['places'] . ' (laisser vide pour garder)' : 'AIza…') ?>"></label>
      <div class="lbl">Trouver mon établissement
        <span class="gsearch"><input type="search" data-place-q placeholder="Ex. : LE-DIGITAL.com Besançon" aria-label="Nom de l’établissement"><button type="button" class="btn btn--dark" data-place-search>Rechercher</button></span>
        <span class="small muted">Nécessite que la clé soit enregistrée.</span>
      </div>
      <ul class="gresults span2" data-place-results hidden></ul>
      <label>Place ID<input name="place_id" value="<?= e($cfg['place_id']) ?>" placeholder="ChIJ…" spellcheck="false" data-place-id></label>
      <label>Nom de l’établissement<input name="place_name" value="<?= e($cfg['place_name']) ?>" data-place-name></label>
    </div>
    <details class="details">
      <summary>Comment obtenir la clé (gratuit pour cet usage)</summary>
      <ol class="small steps">
        <li>Ouvre <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">console.cloud.google.com</a> et crée un projet (ex. « Site LE-DIGITAL »). Un compte de facturation est demandé par Google, mais une synchro par jour reste largement dans le crédit gratuit mensuel.</li>
        <li>Active l’API <a href="https://console.cloud.google.com/apis/library/places.googleapis.com" target="_blank" rel="noopener"><b>Places API (New)</b></a>.</li>
        <li><i>API et services › Identifiants › Créer des identifiants › Clé API</i>. Dans <i>Restrictions d’API</i>, coche uniquement « Places API (New) ».</li>
        <li>Colle la clé ici, enregistre, puis recherche ton établissement et clique sur « Choisir ».</li>
      </ol>
    </details>
  </section>

  <section class="card" data-mode-block="business"<?= $mode === 'business' ? '' : ' hidden' ?>>
    <div class="card__head"><h2>Application Google (OAuth)</h2></div>
    <div class="form form--cols">
      <label>Identifiant client<input name="client_id" value="<?= e($cfg['client_id']) ?>" spellcheck="false" placeholder="123456789-xxxx.apps.googleusercontent.com"></label>
      <label>Code secret du client<input type="password" name="client_secret" autocomplete="new-password" spellcheck="false" placeholder="<?= e($keys['secret'] !== '' ? 'Enregistré : ' . $keys['secret'] . ' (laisser vide pour garder)' : 'GOCSPX-…') ?>"></label>
      <div class="lbl span2">URI de redirection autorisée (à copier dans Google Cloud)
        <span class="copy"><input readonly value="<?= e($redirect) ?>" aria-label="URI de redirection"><button type="button" class="btn btn--ghost" data-copy>Copier</button></span>
      </div>
    </div>
    <details class="details">
      <summary>Comment créer l’application Google (10 minutes, une seule fois)</summary>
      <ol class="small steps">
        <li>Sur <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">console.cloud.google.com</a>, crée un projet puis active ces 3 API : <a href="https://console.cloud.google.com/apis/library/mybusinessaccountmanagement.googleapis.com" target="_blank" rel="noopener">My Business Account Management API</a>, <a href="https://console.cloud.google.com/apis/library/mybusinessbusinessinformation.googleapis.com" target="_blank" rel="noopener">My Business Business Information API</a> et <a href="https://console.cloud.google.com/apis/library/mybusiness.googleapis.com" target="_blank" rel="noopener">Google My Business API</a>.</li>
        <li>Demande l’accès aux API Business Profile via le <a href="https://support.google.com/business/contact/api_default" target="_blank" rel="noopener">formulaire de Google</a> (gratuit ; réponse sous quelques jours). Tant que l’accès n’est pas accordé, Google répond « quota 0 ».</li>
        <li><i>API et services › Écran de consentement OAuth</i> : type « Externe », ton e-mail, puis <b>publie l’application</b> (« En production ») — sinon Google déconnecte le site au bout de 7 jours.</li>
        <li><i>Identifiants › Créer des identifiants › ID client OAuth › Application Web</i>, ajoute l’URI de redirection ci-dessus, puis colle ici l’identifiant et le code secret.</li>
        <li>Enregistre, clique sur « Se connecter avec Google » (avec le compte qui gère ta fiche) et choisis ton établissement.</li>
      </ol>
    </details>
  </section>

  <section class="card" data-mode-block="places business"<?= $mode === 'off' ? ' hidden' : '' ?>>
    <div class="card__head"><h2>Affichage sur le site</h2></div>
    <div class="form form--cols">
      <label>Importer les avis à partir de
        <select name="min_rating"><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>"<?= (int) $cfg['min_rating'] === $i ? ' selected' : '' ?>><?= $i === 1 ? 'toutes les notes' : str_repeat('★', $i) . ' et plus' ?></option><?php endfor; ?></select>
      </label>
      <div class="form">
        <label class="check"><input type="checkbox" name="hide_empty" value="1"<?= !empty($cfg['hide_empty']) ? ' checked' : '' ?>> Ignorer les avis sans texte (note seule)</label>
        <label class="check"><input type="checkbox" name="auto_publish" value="1"<?= !empty($cfg['auto_publish']) ? ' checked' : '' ?>> Publier automatiquement les nouveaux avis</label>
        <label class="check"><input type="checkbox" name="show_badge" value="1"<?= !empty($cfg['show_badge']) ? ' checked' : '' ?>> Afficher la note Google (★ 4,9 · 37 avis) et « Laisser un avis »</label>
      </div>
    </div>
  </section>

  <div class="actions sticky-actions">
    <button class="btn btn--red" type="submit">Enregistrer</button>
    <button class="btn btn--dark" type="submit" name="sync_now" value="1" data-mode-block="places business"<?= $mode === 'off' ? ' hidden' : '' ?>>Enregistrer et synchroniser</button>
  </div>
</form>

<?php if ($mode === 'business'): ?>
  <section class="card">
    <div class="card__head"><h2>Connexion à ta fiche Google</h2><?php if ($connected): ?><span class="badge badge--ok">Connecté</span><?php endif; ?></div>
    <?php if (!$connected): ?>
      <p class="small">Une fois l’application Google enregistrée ci-dessus, connecte le compte Google qui gère ta fiche d’établissement. Seul un jeton de lecture chiffré est conservé sur le serveur.</p>
      <form method="post" action="/admin/avis/google/connexion"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn btn--google" type="submit"<?= $cfg['client_id'] === '' || $keys['secret'] === '' ? ' disabled' : '' ?>><?= google_g(18) ?> Se connecter avec Google</button></form>
    <?php else: ?>
      <?php if ($locError): ?><p class="alert alert--err"><?= e($locError) ?></p><?php endif; ?>
      <?php if ($locations): ?>
        <form method="post" action="/admin/avis/google/etablissement" class="form">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <label>Établissement dont les avis sont affichés
            <select name="location">
              <?php foreach ($locations as $l): $k = $l['account'] . '|' . $l['location']; ?>
                <option value="<?= e($k) ?>"<?= $k === $currentLoc ? ' selected' : '' ?>><?= e($l['title'] . ($l['address'] !== '' ? ' — ' . $l['address'] : '')) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="actions">
            <button class="btn btn--dark" type="submit">Utiliser cet établissement</button>
            <button class="btn btn--red" type="submit" name="sync_now" value="1">Utiliser et synchroniser</button>
            <a class="link" href="/admin/avis/google?actualiser=1">Actualiser la liste</a>
          </div>
        </form>
      <?php elseif (!$locError): ?>
        <p class="small">Aucun établissement trouvé sur ce compte Google. <a href="/admin/avis/google?actualiser=1">Actualiser</a></p>
      <?php endif; ?>
      <form method="post" action="/admin/avis/google/deconnecter" data-confirm="Déconnecter le compte Google ? Les avis déjà importés restent sur le site."><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="link-danger" type="submit">Déconnecter le compte Google</button></form>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($imported): ?>
  <section class="card">
    <div class="card__head"><h2>Avis importés</h2><a href="/admin/avis">Gérer les avis →</a></div>
    <p class="small">Le texte, la note et l’auteur restent ceux de Google. Dans la liste des avis, tu peux choisir le thème, l’activité affichée, la mise en avant sur l’accueil, ou masquer un avis : ces choix sont conservés à chaque synchronisation.</p>
    <form method="post" action="/admin/avis/google/retirer" data-confirm="Retirer du site tous les avis importés depuis Google ?"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="link-danger" type="submit">Retirer tous les avis Google du site (<?= $imported ?>)</button></form>
  </section>
<?php endif; ?>
