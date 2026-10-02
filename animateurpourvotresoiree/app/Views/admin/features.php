<?php
use App\Core\Url;
/** @var array $s */
$c = static fn (string $name, bool $on, string $label) => '<label class="switch"><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . '> ' . e($label) . '</label>';
$site = (array) $s['site'];
$mod = (array) $s['moderation'];
$reg = (array) $s['registration'];
$f = (array) $s['features'];
?>
<div class="adm-head"><div><h1>Fonctionnement <span class="serif">du site</span></h1><p>Identité, inscriptions, modération, fonctionnalités, emailing, sauvegardes.</p></div></div>
<form class="form" method="post" action="<?= e(Url::admin('reglages/fonctionnement')) ?>" data-dirty-check>
  <?= csrf_field() ?>
  <div class="box">
    <h2>Identité et mentions légales</h2>
    <div class="form-grid">
      <?php foreach (['name' => 'Nom du site', 'short_name' => 'Nom court (application)', 'baseline' => 'Slogan', 'company' => 'Société éditrice', 'siret' => 'SIRET', 'rcs' => 'RCS', 'address' => 'Adresse', 'director' => 'Directeur de la publication', 'host' => 'Hébergeur (nom, adresse, téléphone)', 'phone' => 'Téléphone (facultatif)'] as $k => $l): ?>
        <div class="field"><label for="st-<?= $k ?>"><?= e($l) ?></label><input id="st-<?= $k ?>" type="text" name="site_<?= $k ?>" value="<?= e((string) ($site[$k] ?? '')) ?>"></div>
      <?php endforeach; ?>
    </div>
    <div class="form-grid mt-1"><?php foreach (['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'] as $net): ?><div class="field"><label for="so-<?= $net ?>"><?= icon($net, 13) ?> <?= ucfirst($net) ?> du site</label><input id="so-<?= $net ?>" type="url" name="social_<?= $net ?>" value="<?= e((string) ($site['socials'][$net] ?? '')) ?>"></div><?php endforeach; ?></div>
  </div>
  <div class="box">
    <h2>Inscriptions des pros</h2>
    <div class="stack"><?= $c('reg_enabled', !empty($reg['enabled']), 'Inscriptions ouvertes') ?><?= $c('reg_auto', !empty($reg['auto_approve']), 'Publication automatique après confirmation de l\'email (sans validation par l\'équipe)') ?><?= $c('reg_siren', !empty($reg['require_siren']), 'SIREN / SIRET obligatoire') ?></div>
    <div class="form-grid mt-1"><div class="field"><label for="rg-z">Départements d'intervention maximum</label><input id="rg-z" type="number" name="reg_zones" min="1" max="101" value="<?= (int) ($reg['max_zones'] ?? 10) ?>"></div><div class="field"><label for="rg-p">Photos maximum par fiche</label><input id="rg-p" type="number" name="reg_photos" min="1" max="40" value="<?= (int) ($reg['max_photos'] ?? 12) ?>"></div></div>
  </div>
  <div class="box">
    <h2>Modération</h2>
    <?php $modes = ['auto' => 'Automatique (tout part, sauf spam évident)', 'hybrid' => 'Hybride (recommandé) : envoi auto si l\'anti-spam est serein', 'manual' => 'Manuelle : tout passe par vous']; ?>
    <div class="form-grid">
      <div class="field"><label for="md-r">Demandes de devis</label><select id="md-r" name="mod_requests"><?php foreach ($modes as $k => $l): ?><option value="<?= $k ?>"<?= ($mod['requests_mode'] ?? 'hybrid') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="md-m">Messages aux pros</label><select id="md-m" name="mod_messages"><?php foreach ($modes as $k => $l): ?><option value="<?= $k ?>"<?= ($mod['messages_mode'] ?? 'hybrid') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="md-v">Avis</label><select id="md-v" name="mod_reviews"><option value="manual"<?= ($mod['reviews_mode'] ?? 'manual') === 'manual' ? ' selected' : '' ?>>Manuelle (recommandé)</option><option value="auto"<?= ($mod['reviews_mode'] ?? '') === 'auto' ? ' selected' : '' ?>>Automatique si l'anti-spam est serein</option></select></div>
    </div>
    <div class="form-grid">
      <div class="field"><label for="md-a">Score max. pour l'envoi automatique</label><input id="md-a" type="number" name="mod_auto" min="0" max="100" value="<?= (int) ($mod['auto_threshold'] ?? 30) ?>"><span class="hint">0 à 100 : en dessous, la demande part seule.</span></div>
      <div class="field"><label for="md-s">Score de rejet (spam)</label><input id="md-s" type="number" name="mod_spam" min="1" max="150" value="<?= (int) ($mod['spam_threshold'] ?? 70) ?>"></div>
      <div class="field"><label for="md-x">Pros destinataires maximum par demande</label><input id="md-x" type="number" name="mod_max" min="1" max="300" value="<?= (int) ($mod['max_recipients'] ?? 40) ?>"></div>
      <div class="field"><label for="md-k">Rayon de diffusion (km)</label><input id="md-k" type="number" name="mod_radius" min="5" max="300" value="<?= (int) ($mod['radius_km'] ?? 50) ?>"></div>
    </div>
  </div>
  <div class="box">
    <h2>Fonctionnalités</h2>
    <div class="stack">
      <?= $c('feat_favorites', !empty($f['favorites']), 'Favoris des visiteurs') ?>
      <?= $c('feat_reviews', !empty($f['reviews']), 'Avis clients') ?>
      <?= $c('feat_phone', !empty($f['phone_reveal']), 'Afficher le téléphone des pros (au clic)') ?>
      <?= $c('feat_map', !empty($f['map']), 'Carte interactive') ?>
      <?= $c('feat_pwa', !empty($f['pwa']), 'Application installable (PWA, mode hors ligne)') ?>
      <?= $c('feat_push', !empty($f['push']), 'Notifications push (pros et administrateurs)') ?>
    </div>
    <div class="form-grid mt-1">
      <div class="field"><label for="ls-p">Pros par page dans les listes</label><input id="ls-p" type="number" name="list_per" min="12" max="60" value="<?= (int) ($s['listing']['per_page'] ?? 24) ?>"></div>
      <div class="field"><label for="ls-r">Rayon de recherche autour d'une ville (km)</label><input id="ls-r" type="number" name="list_radius" min="5" max="200" value="<?= (int) ($s['listing']['radius_km'] ?? 40) ?>"></div>
      <div class="field"><label for="rv-m">Longueur minimale d'un avis</label><input id="rv-m" type="number" name="rev_min" min="10" max="500" value="<?= (int) ($s['reviews']['min_length'] ?? 30) ?>"></div>
      <div class="field"><label for="lk-r">Liens vers les sites des pros</label><select id="lk-r" name="links_rel"><?php foreach (['noopener' => 'Liens normaux (transmettent la popularité)', 'nofollow noopener' => 'nofollow', 'ugc nofollow noopener' => 'ugc + nofollow'] as $k => $l): ?><option value="<?= e($k) ?>"<?= ($s['links']['pro_website_rel'] ?? 'noopener') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    </div>
  </div>
  <div class="box">
    <h2>Emailing</h2>
    <div class="form-grid"><div class="field"><label for="ml-r">Emails envoyés par minute</label><input id="ml-r" type="number" name="mail_rate" min="1" max="600" value="<?= (int) ($s['mailing']['rate_per_minute'] ?? 60) ?>"><span class="hint">Respectez la limite de votre fournisseur SMTP.</span></div></div>
    <div class="stack"><?= $c('mail_opens', !empty($s['mailing']['track_opens']), 'Mesurer les ouvertures des campagnes') ?><?= $c('mail_clicks', !empty($s['mailing']['track_clicks']), 'Mesurer les clics des campagnes') ?></div>
    <div class="field mt-1"><label for="ml-s">Signature des réponses</label><textarea id="ml-s" name="mail_signature" rows="2"><?= e((string) ($s['mailing']['signature'] ?? '')) ?></textarea></div>
  </div>
  <div class="box">
    <h2>Maintenance et sauvegardes</h2>
    <div class="field"><label for="mt-m">Message de maintenance</label><input id="mt-m" type="text" name="maintenance_message" value="<?= e((string) ($s['maintenance']['message'] ?? '')) ?>"><span class="hint">Le mode maintenance s'active dans <a href="<?= e(Url::admin('reglages')) ?>">Configuration</a>.</span></div>
    <div class="form-grid"><div class="field"><label for="bk-k">Sauvegardes automatiques conservées</label><input id="bk-k" type="number" name="backup_keep" min="1" max="90" value="<?= (int) ($s['backup']['keep'] ?? 14) ?>"></div></div>
    <?= $c('backup_media', !empty($s['backup']['include_media']), 'Inclure les photos dans les sauvegardes automatiques (plus volumineux)') ?>
  </div>
  <div class="form-actions"><button class="btn btn-coral" type="submit">Enregistrer</button></div>
</form>
