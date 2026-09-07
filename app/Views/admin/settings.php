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
            'publisher_name' => 'Directeur de la publication (obligatoire — LCEN art. 6-III)',
            'phone' => 'Téléphone affiché', 'phone_link' => 'Téléphone (format lien, +33…)',
            'email' => 'E-mail', 'siret' => 'SIRET', 'rcs' => 'Ville RCS', 'vat' => 'N° TVA',
            'insurance' => 'Assurance RCP', 'host' => 'Hébergeur',
            'host_address' => 'Adresse de l’hébergeur (obligatoire — LCEN)',
            'host_phone' => 'Téléphone de l’hébergeur (obligatoire — LCEN)',
            'main_site' => 'Site principal',
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
    <div class="panel__head"><h2>Référencement</h2></div>
    <label class="switch">
      <input type="checkbox" name="site[indexable]" value="1" <?= !empty($settings['site']['indexable']) ? 'checked' : '' ?>>
      <i aria-hidden="true"></i>
      <span><strong>Autoriser l’indexation par les moteurs</strong> — décochez pendant une refonte : le fichier robots.txt interdit alors tout le site</span>
    </label>
  </div>

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
      <div class="field col-pleine">
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
    <div class="panel__head"><h2>Envoi des e-mails</h2></div>
    <?php
    // État réel du transport : dire « mail() prendra le relais » sans
    // vérifier que l'hébergement en est capable ne rend service à personne.
    $smtpPret = Mailer::smtpConfigure();
    $mailDispo = Mailer::mailDisponible();
    $motifMail = $mailDispo ? '' : Mailer::diagnosticMail();
    if ($mailDispo && !$smtpPret) {
        $chemin = trim((string) ini_get('sendmail_path'));
        $binaire = $chemin === '' ? '' : explode(' ', $chemin)[0];
        if ($binaire === '' || !is_executable($binaire)) {
            $mailDispo = false;
            $motifMail = Mailer::diagnosticMail();
        }
    }
    ?>
    <p class="panel__intro">
      Sans serveur SMTP renseigné, le site utilise la fonction <code>mail()</code> de l’hébergeur :
      elle n’est pas disponible partout et ses messages partent souvent en indésirables.
      Renseigner un compte SMTP authentifié fiabilise l’accusé de réception envoyé aux candidats.
      Chaque tentative est tracée dans <strong>E-mails envoyés</strong>, avec son transport et son erreur éventuelle.
    </p>

    <?php if ($smtpPret): ?>
      <div class="flash flash--success">
        <strong>Transport actuel : serveur SMTP</strong> (<?= e((string) ($settings['mail']['smtp_host'] ?? '')) ?>).
        Le repli <code>mail()</code> n’est pas utilisé.
      </div>
    <?php elseif ($mailDispo): ?>
      <div class="flash flash--warn">
        <strong>Transport actuel : fonction <code>mail()</code> de l’hébergeur.</strong>
        Elle est disponible sur ce serveur. Les messages partent, mais sans authentification :
        une partie finit en indésirables. Un compte SMTP reste préférable.
      </div>
    <?php else: ?>
      <div class="flash flash--error">
        <strong>Aucun envoi n’est possible actuellement.</strong>
        <?= e($motifMail) ?>
      </div>
    <?php endif; ?>
    <div class="grid grid--2">
      <div class="field">
        <label for="mail-from">Adresse expéditrice</label>
        <input class="input" id="mail-from" name="mail[from]" type="email" placeholder="no-reply@suisse-immo.fr" value="<?= e((string) ($settings['mail']['from'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="mail-from_name">Nom affiché de l’expéditeur</label>
        <input class="input" id="mail-from_name" name="mail[from_name]" value="<?= e((string) ($settings['mail']['from_name'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="mail-smtp_host">Serveur SMTP <small class="help">vide = fonction mail() de l’hébergeur</small></label>
        <input class="input" id="mail-smtp_host" name="mail[smtp_host]" placeholder="smtp.exemple.fr" value="<?= e((string) ($settings['mail']['smtp_host'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="mail-smtp_port">Port</label>
        <input class="input" id="mail-smtp_port" name="mail[smtp_port]" type="number" min="1" max="65535" value="<?= e((string) ($settings['mail']['smtp_port'] ?? 587)) ?>">
      </div>
      <div class="field">
        <label for="mail-smtp_encryption">Chiffrement</label>
        <select class="select" id="mail-smtp_encryption" name="mail[smtp_encryption]">
          <?php foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'TLS implicite (port 465)', 'none' => 'Aucun (déconseillé)'] as $v => $l): ?>
            <option value="<?= e($v) ?>" <?= ($settings['mail']['smtp_encryption'] ?? 'tls') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="mail-smtp_user">Identifiant SMTP</label>
        <input class="input" id="mail-smtp_user" name="mail[smtp_user]" autocomplete="off" value="<?= e((string) ($settings['mail']['smtp_user'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="mail-smtp_password">Mot de passe SMTP</label>
        <input class="input" id="mail-smtp_password" name="mail[smtp_password]" type="password" autocomplete="new-password"
               placeholder="<?= ($settings['mail']['smtp_password'] ?? '') !== '' ? '•••••••• (inchangé si laissé vide)' : '' ?>" value="">
      </div>
    </div>

    <p class="help" style="margin-top:18px">
      Enregistrez d’abord les réglages, puis lancez un envoi de test : le résultat indique le
      transport réellement employé et, en cas d’échec, le motif exact.
    </p>
  </div>

  <div class="panel">
    <div class="panel__head"><h2>Animations du fond</h2></div>
    <p class="panel__intro">
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
               data-sortie="glow-cycle-out" data-sortie-suffixe=" s">
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

<?php // Formulaire distinct : un formulaire ne peut pas en contenir un autre. ?>
<div class="panel">
  <div class="panel__head"><h2>Tester l’envoi</h2></div>
  <p class="panel__intro">
    Envoie un message réel avec les réglages enregistrés. Le résultat s’affiche en haut de page,
    avec le transport employé et, en cas d’échec, le motif exact renvoyé par le serveur.
  </p>
  <form method="post" action="<?= e(url('admin/reglages/test-email')) ?>">
    <?= Csrf::field() ?>
    <div class="row" style="gap:12px;align-items:flex-end;flex-wrap:wrap">
      <div class="field" style="margin:0;flex:1;min-width:260px">
        <label for="destinataire">Destinataire du test</label>
        <input class="input" id="destinataire" name="destinataire" type="email"
               value="<?= e((string) ($user['email'] ?? '')) ?>">
      </div>
      <button class="btn btn--ghost" type="submit">Envoyer un e-mail de test</button>
    </div>
  </form>
</div>

