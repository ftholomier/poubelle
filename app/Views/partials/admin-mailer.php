<?php
/**
 * Composeur d'e-mail du back-office.
 * L'envoi passe par le serveur : rien ne dépend du client mail du poste,
 * et chaque message part au journal (et en note pour une candidature).
 */
$company = (array) settings('company');
$delay = (string) settings('funnel.response_delay', '48 heures ouvrées');
$signature = "\n\nBien à vous,\n" . ($company['legal_name'] ?? 'Suisse Immo')
    . "\n" . ($company['phone'] ?? '') . " — " . ($company['email'] ?? '');

$templates = [
    'rdv' => [
        'label' => 'Invitation au rendez-vous stratégique',
        'subject' => 'Votre candidature chez Suisse Immo — proposons-nous un rendez-vous ?',
        'body' => "Bonjour {prenom},\n\nMerci pour votre candidature au poste d’agent commercial immobilier indépendant chez Suisse Immo.\n\nVotre profil nous intéresse et nous aimerions en discuter de vive voix lors d’un rendez-vous stratégique d’environ 45 minutes. Nous y aborderons votre projet, le secteur de {secteur}, notre modèle de rémunération et les modalités concrètes de démarrage.\n\nQuelles disponibilités auriez-vous dans les prochains jours ? Vous pouvez aussi nous joindre directement au " . ($company['phone'] ?? '') . "." . $signature,
    ],
    'relance' => [
        'label' => 'Relance — candidature non finalisée',
        'subject' => 'Votre candidature Suisse Immo est presque terminée',
        'body' => "Bonjour {prenom},\n\nVous avez commencé à remplir notre formulaire de candidature pour devenir agent commercial immobilier indépendant, sans aller au bout.\n\nSi c’est un simple manque de temps, sachez qu’il ne reste qu’une étape. Et si vous avez une question ou une hésitation, répondez simplement à ce message : nous y répondrons sans détour.\n\nLe secteur de {secteur} nous intéresse particulièrement en ce moment." . $signature,
    ],
    'infos' => [
        'label' => 'Demande d’informations complémentaires',
        'subject' => 'Votre candidature Suisse Immo — quelques précisions',
        'body' => "Bonjour {prenom},\n\nNous étudions votre candidature et souhaiterions quelques précisions avant d’aller plus loin :\n\n- \n- \n\nMerci d’avance pour votre retour." . $signature,
    ],
    'refus' => [
        'label' => 'Réponse négative',
        'subject' => 'Votre candidature chez Suisse Immo',
        'body' => "Bonjour {prenom},\n\nNous avons étudié votre candidature avec attention. Nous ne sommes malheureusement pas en mesure d’y donner suite dans l’immédiat, le secteur de {secteur} n’étant pas ouvert au recrutement en ce moment.\n\nNous conservons votre dossier et reviendrons vers vous si la situation évolue.\n\nNous vous souhaitons une pleine réussite dans votre projet." . $signature,
    ],
    'reponse' => [
        'label' => 'Réponse à un message',
        'subject' => 'Votre message à Suisse Immo',
        'body' => "Bonjour {prenom},\n\nMerci pour votre message.\n" . $signature,
    ],
    'libre' => ['label' => 'Message libre', 'subject' => '', 'body' => "Bonjour {prenom},\n" . $signature],
];
?>
<div class="mailer" id="mailer" hidden>
  <div class="mailer__backdrop" data-mailer-close></div>
  <div class="mailer__box" role="dialog" aria-modal="true" aria-labelledby="mailer-title">
    <form method="post" action="<?= e(url('admin/email')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="target_type" id="mailer-type" value="">
      <input type="hidden" name="target_id" id="mailer-id" value="">

      <header class="mailer__head">
        <h2 id="mailer-title">Écrire à <span id="mailer-name">—</span></h2>
        <button class="mailer__close" type="button" data-mailer-close aria-label="Fermer"><?= icon('close') ?></button>
      </header>

      <div class="mailer__body">
        <div class="field">
          <label>Destinataire</label>
          <div class="mailer__to" id="mailer-to">—</div>
          <small class="help">L’adresse est relue depuis la fiche au moment de l’envoi : elle ne peut pas être modifiée ici.</small>
        </div>

        <div class="field">
          <label for="mailer-template">Modèle</label>
          <select class="select" id="mailer-template">
            <?php foreach ($templates as $key => $t): ?>
              <option value="<?= e($key) ?>"><?= e($t['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="help"><code>{prenom}</code> et <code>{secteur}</code> sont remplacés automatiquement.</small>
        </div>

        <div class="field">
          <label for="mailer-subject">Objet</label>
          <input class="input" id="mailer-subject" name="subject" maxlength="180" required>
        </div>

        <div class="field">
          <label for="mailer-message">Message</label>
          <textarea class="textarea" id="mailer-message" name="body" rows="14" required></textarea>
          <small class="help">Texte brut : la mise en forme et l’en-tête Suisse Immo sont ajoutés à l’envoi.</small>
        </div>

        <label class="switch">
          <input type="checkbox" name="note" value="1" checked><i aria-hidden="true"></i>
          <span>Consigner l’envoi dans le suivi interne</span>
        </label>
      </div>

      <footer class="mailer__foot">
        <span class="mailer__hint">Répondre-à : <?= e($company['email'] ?? '') ?></span>
        <span class="row" style="gap:8px">
          <button class="btn btn--ghost btn--sm" type="button" data-mailer-close>Annuler</button>
          <button class="btn" type="submit"><?= icon('send') ?> Envoyer</button>
        </span>
      </footer>
    </form>
  </div>
</div>

<script id="mailer-templates" type="application/json"><?= json_encode($templates, JSON_UNESCAPED_UNICODE) ?></script>
