<?php
$labels = [
    'site' => [
        'title' => 'Identité du site',
        'fields' => [
            'name' => 'Nom du site', 'tagline' => 'Accroche', 'url' => 'URL publique',
            'base_path' => 'Sous-dossier d’installation (vide si à la racine)',
            'meta_title' => 'Titre SEO', 'meta_description' => 'Description SEO',
        ],
    ],
    'company' => [
        'title' => 'Société (mentions légales)',
        'fields' => [
            'legal_name' => 'Raison sociale', 'form' => 'Forme juridique', 'capital' => 'Capital social',
            'address' => 'Adresse', 'zip' => 'Code postal', 'city' => 'Ville',
            'phone' => 'Téléphone affiché', 'phone_link' => 'Téléphone (format lien, +33…)',
            'email' => 'E-mail', 'siret' => 'SIRET', 'rcs' => 'Ville RCS', 'vat' => 'N° TVA',
            'insurance' => 'Assurance RCP', 'host' => 'Hébergeur', 'main_site' => 'Site principal',
            'georisques' => 'Lien Géorisques',
        ],
    ],
];
$textareas = ['meta_description'];
?>
<div class="topbar"><h1>Réglages</h1></div>

<form method="post" data-dirty-guard>
  <?= Csrf::field() ?>

  <?php foreach ($labels as $group => $meta): ?>
    <div class="panel">
      <div class="panel__head"><h2><?= e($meta['title']) ?></h2></div>
      <div class="grid grid--2">
        <?php foreach ($meta['fields'] as $key => $label): ?>
          <div class="field" <?= in_array($key, $textareas, true) ? 'style="grid-column:1/-1"' : '' ?>>
            <label for="<?= e($group . '-' . $key) ?>"><?= e($label) ?></label>
            <?php if (in_array($key, $textareas, true)): ?>
              <textarea class="textarea" id="<?= e($group . '-' . $key) ?>" name="<?= e($group) ?>[<?= e($key) ?>]" rows="2"><?= e((string) ($settings[$group][$key] ?? '')) ?></textarea>
            <?php else: ?>
              <input class="input" id="<?= e($group . '-' . $key) ?>" name="<?= e($group) ?>[<?= e($key) ?>]" value="<?= e((string) ($settings[$group][$key] ?? '')) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="panel">
    <div class="panel__head"><h2>Tunnel de conversion</h2></div>
    <div class="grid grid--2">
      <div class="field">
        <label for="notify_email">E-mail de notification des candidatures</label>
        <input class="input" id="notify_email" name="funnel[notify_email]" type="email" value="<?= e((string) ($settings['funnel']['notify_email'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="response_delay">Délai de réponse annoncé</label>
        <input class="input" id="response_delay" name="funnel[response_delay]" value="<?= e((string) ($settings['funnel']['response_delay'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="sticky_cta_label">Libellé de la barre CTA flottante</label>
        <input class="input" id="sticky_cta_label" name="funnel[sticky_cta_label]" value="<?= e((string) ($settings['funnel']['sticky_cta_label'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="exit_intent_title">Titre de la pop-in de sortie</label>
        <input class="input" id="exit_intent_title" name="funnel[exit_intent_title]" value="<?= e((string) ($settings['funnel']['exit_intent_title'] ?? '')) ?>">
      </div>
      <div class="field" style="grid-column:1/-1">
        <label for="exit_intent_text">Texte de la pop-in de sortie</label>
        <textarea class="textarea" id="exit_intent_text" name="funnel[exit_intent_text]" rows="2"><?= e((string) ($settings['funnel']['exit_intent_text'] ?? '')) ?></textarea>
      </div>
    </div>

    <div style="margin-top:8px;padding-top:16px;border-top:1px solid var(--line)">
      <?php foreach ([
          'notify_enabled' => 'Envoyer les e-mails de notification et les accusés de réception',
          'sticky_cta' => 'Afficher la barre CTA flottante',
          'exit_intent' => 'Afficher la pop-in de sortie',
          'cv_upload' => 'Autoriser le dépôt de CV dans le tunnel',
      ] as $key => $label): ?>
        <label class="switch">
          <input type="checkbox" name="funnel[<?= e($key) ?>]" value="1" <?= !empty($settings['funnel'][$key]) ? 'checked' : '' ?>>
          <i aria-hidden="true"></i>
          <span><?= e($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel__head"><h2>Animations du fond</h2></div>
    <p style="font-size:.86rem;color:var(--muted);margin-bottom:16px">
      Les halos colorés placés derrière les blocs du site peuvent dériver lentement.
      Le mouvement n’utilise que <code>transform</code> : il ne déclenche aucun recalcul de mise en page
      et reste automatiquement désactivé pour les visiteurs qui ont demandé à leur système de réduire les animations.
    </p>

    <label class="switch">
      <input type="checkbox" name="motion[glow]" value="1" id="glow-toggle" <?= !empty($settings['motion']['glow']) ? 'checked' : '' ?>><i aria-hidden="true"></i>
      <span><strong>Animer les halos</strong> — sur l’ensemble du site</span>
    </label>

    <div class="field" style="margin-top:18px" id="glow-speed">
      <label for="glow_cycle">Vitesse du mouvement</label>
      <small class="help">
        Durée d’un cycle complet. Plus la valeur est basse, plus le déplacement est rapide.
        Les autres halos dérivent sur des durées légèrement différentes, pour que la boucle ne se laisse pas deviner.
      </small>
      <div class="row" style="gap:14px;align-items:center">
        <span style="font-size:.78rem;color:var(--muted);white-space:nowrap">Rapide · 8 s</span>
        <input type="range" id="glow_cycle" name="motion[glow_cycle]" class="range"
               min="8" max="180" step="2"
               value="<?= e((string) ($settings['motion']['glow_cycle'] ?? 34)) ?>"
               oninput="document.getElementById('glow-cycle-out').textContent = this.value + ' s'">
        <span style="font-size:.78rem;color:var(--muted);white-space:nowrap">180 s · Très lent</span>
        <output id="glow-cycle-out" class="badge" style="min-width:64px;justify-content:center"><?= e((string) ($settings['motion']['glow_cycle'] ?? 34)) ?> s</output>
      </div>
      <small class="help">Repères : 15 s pour un mouvement bien visible, 35 s pour une respiration discrète, 90 s pour un fond presque immobile.</small>
    </div>
  </div>

  <div class="row" style="margin-top:18px">
    <button class="btn" type="submit">Enregistrer les réglages</button>
  </div>
</form>
