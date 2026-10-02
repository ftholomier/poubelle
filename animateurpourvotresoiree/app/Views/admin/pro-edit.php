<?php
use App\Core\Url;
use App\Services\Categories;
use App\Services\Chart;
use App\Services\Geo;
use App\Services\Pros;
use App\Services\Reviews;

/** @var array $pro @var ?array $commune @var array $messages @var array $reviews */
$id = (int) $pro['id'];
$status = $pro['status'] ?? 'pending';
$act = static fn (string $action, string $label, string $class = 'btn-sm btn-block', string $confirm = ''): string => '<form method="post" action="' . e(Url::admin('pros/' . $id . '/action')) . '"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '"><button class="btn ' . e($class) . '" type="submit">' . $label . '</button></form>';
$legacy = (array) ($pro['legacy'] ?? []);
?>
<p><a class="link small" href="<?= e(Url::admin('pros')) ?>">← Pros</a></p>
<div class="adm-head">
  <div class="pro-mini">
    <?php $cover = null; foreach ((array) ($pro['photos'] ?? []) as $ph) { if (!empty($ph['cover'])) { $cover = $ph; } } $cover ??= $pro['photos'][0] ?? null; ?>
    <?php if ($cover): ?><img src="<?= e(Pros::photo($cover, 'sm')) ?>" alt=""><?php else: ?><span class="ph" style="--c:<?= e(Categories::color($pro['categories'][0] ?? null)) ?>"><?= e(App\Core\Str::initials(Pros::displayName($pro))) ?></span><?php endif; ?>
    <div><h1><?= e(Pros::displayName($pro)) ?></h1><p>#<?= $id ?> · <span class="status-pill st-<?= e($status) ?>"><?= e(Pros::STATUSES[$status] ?? $status) ?></span> · inscrit <?= e(date_fr((string) ($pro['created_at'] ?? ''), 'month')) ?> · <?= e(($pro['source'] ?? '') === 'legacy' ? 'ancien site' : (($pro['source'] ?? '') === 'admin' ? 'créé par l\'équipe' : 'inscription en ligne')) ?></p></div>
  </div>
  <div class="row-wrap">
    <?php if ($status === 'active'): ?><a class="btn btn-sm" href="<?= e(Url::pro($pro)) ?>" target="_blank" rel="noopener"><?= icon('eye', 16) ?> Voir la fiche</a><?php endif; ?>
    <?= $act('impersonate', icon('user', 16) . ' Ouvrir son espace', 'btn-sm btn-ink') ?>
  </div>
</div>

<div class="adm-cols">
  <form class="form" method="post" action="<?= e(Url::admin('pros/' . $id)) ?>" data-dirty-check>
    <?= csrf_field() ?>
    <div class="box">
      <h2>Identité</h2>
      <div class="form-grid">
        <div class="field"><label for="e-name">Nom affiché *</label><input id="e-name" type="text" name="display_name" maxlength="80" required value="<?= e(Pros::displayName($pro)) ?>"></div>
        <div class="field"><label for="e-co">Raison sociale</label><input id="e-co" type="text" name="company" maxlength="100" value="<?= e((string) ($pro['company'] ?? '')) ?>"></div>
        <div class="field"><label for="e-fn">Prénom</label><input id="e-fn" type="text" name="first_name" maxlength="60" value="<?= e((string) ($pro['first_name'] ?? '')) ?>"></div>
        <div class="field"><label for="e-ln">Nom</label><input id="e-ln" type="text" name="last_name" maxlength="60" value="<?= e((string) ($pro['last_name'] ?? '')) ?>"></div>
        <div class="field"><label for="e-em">Email</label><input id="e-em" type="email" name="email" value="<?= e((string) ($pro['email'] ?? '')) ?>"><span class="hint"><?= !empty($pro['email_verified']) ? '✓ confirmé' : 'non confirmé' ?><?= !empty($pro['email_pending']) ? ' · changement en attente : ' . e($pro['email_pending']) : '' ?></span></div>
        <div class="field"><label for="e-login">Identifiant de connexion</label><input id="e-login" type="text" name="login" maxlength="120" value="<?= e((string) ($pro['login'] ?? '')) ?>"></div>
        <div class="field"><label for="e-ph">Téléphone</label><input id="e-ph" type="tel" name="phone" value="<?= e((string) ($pro['phone'] ?? '')) ?>"></div>
        <div class="field"><label for="e-siret">SIRET</label><input id="e-siret" type="text" name="siret" value="<?= e((string) ($pro['siret'] ?? '')) ?>"><?php if (!empty($pro['siret'])): ?><span class="hint"><a href="https://annuaire-entreprises.data.gouv.fr/etablissement/<?= e((string) $pro['siret']) ?>" target="_blank" rel="noopener noreferrer">Vérifier sur l'annuaire des entreprises ↗</a></span><?php endif; ?></div>
      </div>
    </div>

    <div class="box">
      <h2>Présentation</h2>
      <div class="field"><label for="e-tag">Accroche</label><input id="e-tag" type="text" name="tagline" maxlength="220" value="<?= e((string) ($pro['tagline'] ?? '')) ?>"></div>
      <div class="field"><label for="e-desc">Description</label><textarea id="e-desc" name="description" data-editor="basic" rows="10"><?= e((string) ($pro['description'] ?? '')) ?></textarea></div>
      <fieldset class="field"><legend class="label">Métiers (le premier est le métier principal)</legend>
        <div class="choice-grid"><?php foreach (Categories::all(true) as $s => $c): ?><label class="choice"><input type="checkbox" name="categories[]" value="<?= e($s) ?>"<?= in_array($s, (array) ($pro['categories'] ?? []), true) ? ' checked' : '' ?>><span><?= e($c['emoji'] . ' ' . $c['name']) ?></span></label><?php endforeach; ?></div>
      </fieldset>
      <div class="field"><label for="e-tags">Mots-clés</label><input id="e-tags" type="text" name="tags" value="<?= e(implode(', ', (array) ($pro['tags'] ?? []))) ?>"></div>
      <div class="form-grid">
        <div class="field"><label for="e-price">Prix de départ (€)</label><input id="e-price" type="number" name="price_from" min="0" value="<?= e((string) ($pro['price_from'] ?? '')) ?>"></div>
        <div class="field"><label for="e-pnote">Précision tarif</label><input id="e-pnote" type="text" name="price_note" maxlength="80" value="<?= e((string) ($pro['price_note'] ?? '')) ?>"></div>
        <div class="field"><label for="e-lang">Langues</label><input id="e-lang" type="text" name="languages" maxlength="80" value="<?= e((string) ($pro['languages'] ?? '')) ?>"></div>
        <div class="field"><label for="e-guso">GUSO / licence</label><input id="e-guso" type="text" name="guso" maxlength="30" value="<?= e((string) ($pro['guso'] ?? '')) ?>"></div>
      </div>
    </div>

    <div class="box">
      <h2>Localisation</h2>
      <div class="form-grid">
        <div class="field autocomplete"><label for="e-city">Ville</label><input id="e-city" type="text" name="city_label" autocomplete="off" data-commune-input value="<?= e($commune ? Geo::label($commune) : (string) ($pro['city'] ?? '')) ?>"><input type="hidden" name="insee" data-commune-insee value="<?= e((string) ($pro['insee'] ?? '')) ?>">
          <span class="hint">Précision : <?= e((string) ($pro['geo_precision'] ?? '?')) ?><?= !empty($pro['geo_manual']) ? ' (manuelle)' : '' ?></span></div>
        <div class="field"><label for="e-addr">Adresse (privée)</label><input id="e-addr" type="text" name="address" maxlength="160" value="<?= e((string) ($pro['address'] ?? '')) ?>"></div>
        <div class="field"><label for="e-lat">Latitude</label><input id="e-lat" type="text" name="lat" value="<?= e((string) ($pro['lat'] ?? '')) ?>" inputmode="decimal"></div>
        <div class="field"><label for="e-lng">Longitude</label><input id="e-lng" type="text" name="lng" value="<?= e((string) ($pro['lng'] ?? '')) ?>" inputmode="decimal"></div>
      </div>
      <div class="field"><label>Départements d'intervention</label>
        <select name="zones[]" multiple data-multi aria-label="Départements d'intervention"><?php foreach (Geo::departements() as $code => $d): ?><option value="<?= e($code) ?>"<?= in_array((string) $code, array_map('strval', (array) ($pro['zones'] ?? [])), true) ? ' selected' : '' ?>><?= e($code . ' — ' . $d['name']) ?></option><?php endforeach; ?></select></div>
      <label class="switch"><input type="checkbox" name="all_france" value="1"<?= !empty($pro['all_france']) ? ' checked' : '' ?>> Se déplace dans toute la France</label>
    </div>

    <div class="box">
      <h2>Liens et vidéos</h2>
      <div class="form-grid">
        <div class="field"><label for="e-web">Site web</label><input id="e-web" type="url" name="website" value="<?= e((string) ($pro['website'] ?? '')) ?>"></div>
        <?php foreach (['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'] as $net): ?><div class="field"><label for="e-<?= $net ?>"><?= icon($net, 13) ?> <?= ucfirst($net) ?></label><input id="e-<?= $net ?>" type="url" name="socials[<?= $net ?>]" value="<?= e((string) ($pro['socials'][$net] ?? '')) ?>"></div><?php endforeach; ?>
      </div>
      <div class="field"><label for="e-vid">Vidéos YouTube / Vimeo (une par ligne)</label><textarea id="e-vid" name="videos" rows="3"><?= e(implode("\n", (array) ($pro['videos'] ?? []))) ?></textarea></div>
    </div>

    <div class="box">
      <h2>Publication & référencement</h2>
      <div class="form-grid">
        <div class="field"><label for="e-status">Statut</label><select id="e-status" name="status"><?php foreach (Pros::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="e-slug">Adresse de la fiche (slug)</label><input id="e-slug" type="text" name="slug" value="<?= e((string) ($pro['slug'] ?? '')) ?>"><span class="hint">L'ancienne adresse sera redirigée automatiquement.</span></div>
      </div>
      <label class="check"><input type="checkbox" name="notify" value="1" checked> <span class="small">Si la fiche passe « En ligne », prévenir le pro par email</span></label>
      <label class="switch"><input type="checkbox" name="accept_requests" value="1"<?= ($pro['accept_requests'] ?? true) !== false ? ' checked' : '' ?>> Reçoit les demandes de devis groupées</label>
      <div class="row-wrap"><label class="switch"><input type="checkbox" name="featured" value="1"<?= !empty($pro['featured']) ? ' checked' : '' ?>> ⭐ Mettre en avant (accueil et résultats)</label>
        <div class="field" style="max-width:200px"><label for="e-fu">jusqu'au</label><input id="e-fu" type="date" name="featured_until" value="<?= e((string) ($pro['featured_until'] ?? '')) ?>"></div></div>
      <div class="field"><label for="e-st">Titre SEO personnalisé</label><input id="e-st" type="text" name="seo_title" maxlength="70" value="<?= e((string) ($pro['seo']['title'] ?? '')) ?>" data-seo-count="60" placeholder="Laisser vide = modèle automatique"></div>
      <div class="field"><label for="e-sd">Meta description personnalisée</label><input id="e-sd" type="text" name="seo_description" maxlength="170" value="<?= e((string) ($pro['seo']['description'] ?? '')) ?>" data-seo-count="160"></div>
      <div class="field"><label for="e-notes">Notes internes (jamais visibles par le pro)</label><textarea id="e-notes" name="admin_notes" rows="4"><?= e((string) ($pro['admin_notes'] ?? '')) ?></textarea></div>
    </div>
    <div class="form-actions"><button class="btn btn-coral" type="submit"><?= icon('check', 16) ?> Enregistrer la fiche</button></div>
  </form>

  <aside>
    <div class="box">
      <h2>Actions</h2>
      <div class="stack">
        <?php if ($status !== 'active'): ?><?= $act('validate', '✅ Valider et publier', 'btn-sm btn-block btn-lime') ?><?php endif; ?>
        <?php if ($status === 'pending'): ?>
          <form method="post" action="<?= e(Url::admin('pros/' . $id . '/action')) ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><textarea name="reason" rows="2" class="input" placeholder="Motif du refus (envoyé au pro)"></textarea><button class="btn btn-sm btn-block" type="submit">Refuser l'inscription</button></form>
        <?php endif; ?>
        <?php if ($status === 'active'): ?><?= $act('suspend', 'Suspendre', 'btn-sm btn-block', 'Retirer la fiche du site ?') ?><?php endif; ?>
        <?= $act('send-access', 'Envoyer un lien de mot de passe') ?>
        <?php if (empty($pro['email_verified'])): ?><?= $act('resend-verify', 'Renvoyer la confirmation d\'email') ?><?= $act('verify-email', 'Marquer l\'email comme confirmé') ?><?php endif; ?>
        <?php if (!empty($pro['locked_until'])): ?><?= $act('unlock', 'Déverrouiller le compte') ?><?php endif; ?>
        <?= $act('classify', '✨ Reclasser les métiers' . ($aiOn ? ' (IA)' : '')) ?>
        <?= $act('geocode', 'Recalculer la géolocalisation') ?>
        <?= $act('recompute-reviews', 'Recalculer la note') ?>
        <?php if ($status !== 'deleted'): ?><?= $act('delete', 'Supprimer (corbeille)', 'btn-sm btn-block', 'Placer cette fiche dans la corbeille ?') ?><?php endif; ?>
        <details><summary class="small muted">Effacement définitif (RGPD)</summary>
          <form method="post" action="<?= e(Url::admin('pros/' . $id . '/action')) ?>" class="stack mt-1"><?= csrf_field() ?><input type="hidden" name="action" value="erase"><input class="input" type="text" name="confirm" placeholder="Tapez EFFACER"><button class="btn btn-sm btn-block" type="submit">Effacer données et photos</button></form>
        </details>
      </div>
    </div>
    <div class="box">
      <h2>Activité</h2>
      <ul class="list-rows">
        <li><span>Vues (30 j)</span><b><?= nf($views30) ?></b></li>
        <li><span>Vues (total)</span><b><?= nf((int) ($pro['stats']['views'] ?? 0)) ?></b></li>
        <li><span>Numéros affichés</span><b><?= nf((int) ($pro['stats']['phone_reveals'] ?? 0)) ?></b></li>
        <li><span>Clics vers son site</span><b><?= nf((int) ($pro['stats']['website_clicks'] ?? 0)) ?></b></li>
        <li><span>Messages reçus</span><a href="<?= e(Url::admin('messages?pro=' . $id)) ?>"><b><?= nf($msgTotal) ?></b></a></li>
        <li><span>Demandes reçues</span><a href="<?= e(Url::admin('demandes?pro=' . $id)) ?>"><b><?= nf($reqTotal) ?></b></a></li>
        <li><span>Note</span><b><?= !empty($pro['rating']['count']) ? number_format((float) $pro['rating']['avg'], 1, ',', '') . ' (' . (int) $pro['rating']['count'] . ')' : '—' ?></b></li>
        <li><span>Complétude</span><b><?= Pros::completeness($pro) ?> %</b></li>
        <li><span>Dernière connexion</span><span><?= !empty($pro['last_login_at']) ? e(ago((string) $pro['last_login_at'])) : 'jamais' ?></span></li>
      </ul>
      <?= Chart::spark(array_column($series, 'pro_view')) ?>
    </div>
    <div class="box" id="photos">
      <h2>Photos (<?= count((array) ($pro['photos'] ?? [])) ?>/<?= (int) $maxPhotos ?>)</h2>
      <div class="thumbs-adm">
        <?php foreach ((array) ($pro['photos'] ?? []) as $ph): ?>
          <figure class="<?= !empty($ph['cover']) ? 'is-cover' : '' ?>"><img src="<?= e(Pros::photo($ph, 'sm')) ?>" alt="<?= e($ph['alt'] ?? '') ?>" loading="lazy">
            <figcaption>
              <?php if (empty($ph['cover'])): ?><form method="post" action="<?= e(Url::admin('pros/' . $id . '/photos')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="cover"><input type="hidden" name="photo" value="<?= e($ph['id']) ?>"><button class="btn btn-xs" type="submit" title="Photo principale">★</button></form><?php endif; ?>
              <form method="post" action="<?= e(Url::admin('pros/' . $id . '/photos')) ?>" data-confirm="Supprimer cette photo ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="photo" value="<?= e($ph['id']) ?>"><button class="btn btn-xs" type="submit" title="Supprimer"><?= icon('trash', 12) ?></button></form>
            </figcaption></figure>
        <?php endforeach; ?>
      </div>
      <form class="mt-2" method="post" action="<?= e(Url::admin('pros/' . $id . '/photos')) ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="small"> <button class="btn btn-xs btn-ink" type="submit">Ajouter</button></form>
    </div>
    <div class="box">
      <div class="box-head"><h2>Derniers messages</h2><a class="link small" href="<?= e(Url::admin('messages?pro=' . $id)) ?>">Tous</a></div>
      <?php if (!$messages): ?><p class="muted small">Aucun message.</p><?php else: ?><ul class="list-rows"><?php foreach ($messages as $m): ?><li><span class="small"><a href="<?= e(Url::admin('messages/' . $m['id'])) ?>"><?= e($m['name']) ?></a><br><span class="muted"><?= e(App\Core\Str::limit($m['excerpt'], 70)) ?></span></span><span class="muted"><?= e(date_fr($m['created'], 'short')) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <?php if ($reviews): ?>
    <div class="box"><h2>Avis</h2><ul class="list-rows"><?php foreach (array_slice($reviews, 0, 6) as $r): ?><li><span class="small"><span class="c-coral"><?= str_repeat('★', $r['rating']) ?></span> <?= e($r['author']) ?><br><span class="muted"><?= e($r['excerpt']) ?></span></span><span class="status-pill st-<?= e($r['status']) ?>"><?= e(Reviews::STATUSES[$r['status']] ?? $r['status']) ?></span></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if ($legacy): ?>
    <details class="box"><summary><b>Données de l'ancien site</b></summary>
      <ul class="list-rows small mt-1">
        <?php foreach (['debut' => 'Début d\'adhésion', 'fin' => 'Fin d\'adhésion', 'receptiondevis' => 'Recevait les devis', 'gratuit' => 'Compte gratuit', 'somme' => 'Montant payé', 'type_paiement' => 'Type de paiement', 'contrat' => 'Contrat', 'site_original' => 'Site saisi', 'urlsite' => 'Mini-site', 'parrain' => 'Parrain', 'num_facture' => 'Facture'] as $k => $l): if (!isset($legacy[$k]) || $legacy[$k] === '' || $legacy[$k] === null) { continue; } ?>
          <li><span class="muted"><?= e($l) ?></span><span><?= e(is_scalar($legacy[$k]) ? (string) $legacy[$k] : json_encode($legacy[$k])) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </details>
    <?php endif; ?>
  </aside>
</div>
