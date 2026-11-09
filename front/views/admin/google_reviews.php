<?php
/** Bloc « Avis Google » de la rubrique Avis clients : clé, recherche de la fiche, état, réglages. */
$hasKey = GoogleReviews::key() !== '';
$d = GoogleReviews::data();
$connected = GoogleReviews::connected() && $d;
$site = Content::site();
$guess = trim(($d['name'] ?? '') !== '' ? $d['name'] . ' ' . ($d['address'] ?? '') : $site['name'] . ' ' . ($site['agencies'][0]['city'] ?? ''));
$mode = (string) Settings::get('google_reviews_mode', 'mix');
$min = (int) Settings::get('google_min_rating', 4);
?>
<div class="card gr">
  <div class="gr__h">
    <span class="gr__g" aria-hidden="true">G</span>
    <div>
      <h2 style="margin:0">Avis Google</h2>
      <p class="bo-muted" style="margin:2px 0 0">Note, nombre d'avis et avis lus directement sur la fiche Google de l'entreprise, mis à jour chaque jour.</p>
    </div>
  </div>

  <?php if ($connected): ?>
    <div class="gr__on">
      <div>
        <b><?= e($d['name']) ?></b><br>
        <span class="bo-muted"><?= e($d['address'] ?? '') ?></span>
      </div>
      <div class="gr__note"><b><?= $d['rating'] !== null ? e(number_format((float) $d['rating'], 1, ',', '')) : '—' ?></b><span>★★★★★</span><small><?= (int) $d['count'] ?> avis sur Google</small></div>
    </div>
    <p class="bo-muted"><?= count($d['reviews'] ?? []) ?> avis récupérés (Google en donne 5 par lecture : les suivants s'ajoutent au fil des jours) · <?= count(GoogleReviews::reviews()) ?> affichés avec les réglages ci-dessous · mis à jour le <?= e(date('d/m/Y à H\hi', (int) strtotime((string) $d['fetched_at']))) ?>.</p>
    <?php if (!empty($d['error'])): ?><p class="bo-warn">Dernière lecture en échec (<?= e(date('d/m H\hi', (int) strtotime((string) ($d['error_at'] ?? 'now')))) ?>) : <?= e($d['error']) ?> — les avis précédents restent affichés.</p><?php endif; ?>
    <div class="quick" style="margin-bottom:6px">
      <button class="b b--l" type="button" data-action="google.refresh">⟳ Actualiser maintenant</button>
      <?php if (!empty($d['maps_url'])): ?><a class="b b--l" href="<?= e($d['maps_url']) ?>" target="_blank" rel="noopener">Voir la fiche sur Google ↗</a><?php endif; ?>
      <button class="b b--danger" type="button" data-action="google.disconnect" data-confirm="Déconnecter la fiche Google ?">Déconnecter</button>
    </div>
    <?php if (!empty($d['reviews'])): ?>
      <details class="gr__list"><summary>Voir les avis récupérés</summary>
        <?php foreach ($d['reviews'] as $r): ?>
          <div class="gr__r<?= (trim((string) $r['text']) === '' || (int) $r['rating'] < $min) ? ' is-off' : '' ?>">
            <b><?= e($r['author']) ?></b> · <?= str_repeat('★', max(0, min(5, (int) $r['rating']))) ?> · <span class="bo-muted"><?= e($r['when']) ?></span>
            <div><?= $r['text'] !== '' ? nl2br(e($r['text'])) : '<span class="bo-muted">(note sans commentaire)</span>' ?></div>
          </div>
        <?php endforeach; ?>
      </details>
    <?php endif; ?>
  <?php endif; ?>

  <form class="bo-form gr__search" data-google-search>
    <div class="f" style="margin:0">
      <label class="f-label" for="gr-q"><?= $connected ? 'Changer de fiche : nom de l\'entreprise, adresse ou lien Google' : 'Trouver la fiche : nom de l\'entreprise, adresse ou lien Google' ?></label>
      <div class="gr__row">
        <input id="gr-q" name="q" value="<?= e($guess) ?>" placeholder="Renov'Est Taillecourt, ou 16 rue de la Croisée 25400 Taillecourt, ou lien Google Maps">
        <button class="b b--y" type="submit">Rechercher la fiche</button>
      </div>
    </div>
    <?php if (!$hasKey): ?>
      <div class="f" style="margin:12px 0 0">
        <label class="f-label" for="gr-key">Clé API Google</label>
        <input id="gr-key" type="password" name="key" autocomplete="new-password" placeholder="Clé Google Cloud avec « Places API (New) » activée" data-google-key>
        <div class="f-help">Google Cloud › API et services › activer « Places API (New) », puis Identifiants › Créer une clé API (à restreindre à cette API). Usage faible : une lecture par jour.</div>
      </div>
    <?php endif; ?>
    <div class="gr__res" data-google-results></div>
  </form>

  <form class="bo-form gr__set" data-api="google.save">
    <div class="f-grid">
      <div class="f f--half"><label class="f-label" for="gr-mode">Avis affichés sur le site</label>
        <select id="gr-mode" name="data[google_reviews_mode]">
          <option value="mix"<?= $mode === 'mix' ? ' selected' : '' ?>>Avis Google, complétés par les avis saisis ci-dessous</option>
          <option value="google"<?= $mode === 'google' ? ' selected' : '' ?>>Avis Google uniquement</option>
          <option value="manual"<?= $mode === 'manual' ? ' selected' : '' ?>>Avis saisis ci-dessous uniquement</option>
        </select></div>
      <div class="f f--half"><label class="f-label" for="gr-min">Note minimale des avis Google affichés</label>
        <select id="gr-min" name="data[google_min_rating]">
          <?php foreach ([5 => '5 étoiles', 4 => '4 étoiles et plus', 3 => '3 étoiles et plus', 1 => 'Tous les avis'] as $v => $l): ?><option value="<?= $v ?>"<?= $min === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select></div>
      <div class="f"><label class="f-label" for="gr-k2">Clé API Google</label>
        <input id="gr-k2" type="password" name="data[google_places_key]" autocomplete="new-password" placeholder="<?= $hasKey ? '•••••••• (enregistrée — laisser vide pour garder)' : 'Non réglée' ?>">
        <?php if ($hasKey): ?><label class="f-check" style="margin-top:6px"><input type="checkbox" name="data[_clear][google_places_key]" value="1"> <span>Effacer la clé enregistrée</span></label><?php endif; ?></div>
    </div>
    <div class="bo-actions"><button class="b b--l" type="submit">Enregistrer ces réglages</button></div>
  </form>
</div>
