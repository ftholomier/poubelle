<?php
/**
 * Paramètres : envoi des e-mails, compte administrateur, diagnostic.
 *
 * @var array $parametres
 * @var string $identifiant
 * @var array $diagnostic
 * @var array $droits
 * @var string $destinataireEffectif
 * @var string|null $trace
 */
use App\Core\Csrf;

$smtp = $parametres['smtp'];
$contact = $parametres['contact'];
?>

<form class="bo-form" method="post" action="<?= url('/admin/parametres/messagerie') ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Destinataire des demandes</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="p-dest">Adresse qui reçoit les messages du formulaire</label>
        <input id="p-dest" type="email" name="destinataire" value="<?= e($contact['destinataire']) ?>"
               placeholder="contact@baron-paysage.com">
        <span class="aide">
          <?php if ($contact['destinataire'] === '' && $destinataireEffectif !== ''): ?>
            Vide : les demandes partent vers <strong><?= e($destinataireEffectif) ?></strong>,
            l'e-mail saisi dans <em>Coordonnées</em>.
          <?php elseif ($contact['destinataire'] === ''): ?>
            Vide, et aucun e-mail dans <em>Coordonnées</em> : le formulaire ne peut rien envoyer.
          <?php else: ?>
            Laissez vide pour utiliser l'e-mail saisi dans <em>Coordonnées</em>.
          <?php endif; ?>
        </span>
      </div>
      <div class="bo-champ">
        <label for="p-copie">Copie (facultatif)</label>
        <input id="p-copie" type="email" name="copie" value="<?= e($contact['copie']) ?>">
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Serveur d'envoi (SMTP)</legend>
    <div class="bo-champ bo-champ--case">
      <label for="p-actif">
        <input id="p-actif" type="checkbox" name="actif" <?= $smtp['actif'] ? 'checked' : '' ?>>
        Envoyer les e-mails via SMTP
      </label>
      <span class="aide">Décoché, le site utilise la fonction <code>mail()</code> de PHP — souvent
        filtrée par les messageries. Le SMTP est vivement recommandé.</span>
    </div>

    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="p-hote">Serveur</label>
        <input id="p-hote" type="text" name="hote" value="<?= e($smtp['hote']) ?>" placeholder="mail.baron-paysage.com">
      </div>
      <div class="bo-champ">
        <label for="p-port">Port</label>
        <input id="p-port" type="number" name="port" min="1" max="65535" value="<?= e($smtp['port']) ?>">
      </div>
      <div class="bo-champ">
        <label for="p-sec">Chiffrement</label>
        <select id="p-sec" name="securite">
          <?php foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'aucune' => 'Aucun'] as $v => $l): ?>
            <option value="<?= $v ?>"<?= $smtp['securite'] === $v ? ' selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="p-id">Identifiant</label>
        <input id="p-id" type="text" name="identifiant" value="<?= e($smtp['identifiant']) ?>"
               autocomplete="off" placeholder="contact@baron-paysage.com">
      </div>
      <div class="bo-champ">
        <label for="p-mdp">Mot de passe</label>
        <input id="p-mdp" type="password" name="mot_de_passe" autocomplete="new-password"
               placeholder="<?= $smtp['mot_de_passe'] !== '' ? '•••••••• (inchangé)' : '' ?>">
        <span class="aide">Laissez vide pour conserver le mot de passe enregistré.</span>
      </div>
    </div>

    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="p-exp">Adresse expéditrice</label>
        <input id="p-exp" type="email" name="expediteur" value="<?= e($smtp['expediteur']) ?>"
               placeholder="contact@baron-paysage.com">
        <span class="aide">Doit appartenir au domaine du serveur d'envoi, sinon les messages partent en indésirables.</span>
      </div>
      <div class="bo-champ">
        <label for="p-nom">Nom affiché</label>
        <input id="p-nom" type="text" name="nom_expediteur" value="<?= e($smtp['nom_expediteur']) ?>">
      </div>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>

<form class="bo-form" method="post" action="<?= url('/admin/parametres/test') ?>" style="margin-top:1.4rem;">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>Tester l'envoi</legend>
    <div class="bo-champ">
      <label for="p-test">Envoyer un message de test à</label>
      <input id="p-test" type="email" name="destinataire_test" value="<?= e($contact['destinataire']) ?>" required>
    </div>
    <button class="bo-btn bo-btn--contour" type="submit">Envoyer le test</button>
    <?php if ($trace): ?>
      <details class="bo-trace">
        <summary>Détail de la dernière tentative</summary>
        <pre><?= e($trace) ?></pre>
      </details>
    <?php endif; ?>
  </fieldset>
</form>

<form class="bo-form" method="post" action="<?= url('/admin/parametres/compte') ?>" style="margin-top:1.4rem;">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>Compte administrateur</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="p-compte-id">Identifiant</label>
        <input id="p-compte-id" type="text" name="identifiant" value="<?= e($identifiant) ?>" required minlength="3" autocomplete="username">
      </div>
      <div class="bo-champ">
        <label for="p-nouveau">Nouveau mot de passe</label>
        <input id="p-nouveau" type="password" name="nouveau_mot_de_passe" autocomplete="new-password">
        <span class="aide">Vide = inchangé. Sinon 12 caractères minimum.</span>
      </div>
      <div class="bo-champ">
        <label for="p-conf">Confirmation</label>
        <input id="p-conf" type="password" name="confirmation" autocomplete="new-password">
      </div>
    </div>
    <button class="bo-btn" type="submit">Mettre à jour le compte</button>
  </fieldset>
</form>

<section class="bo-zone" style="margin-top:1.4rem;">
  <header class="bo-zone__tete">
    <h2>Mesure d'audience</h2>
    <p>Le script de mesure n'est chargé que pour les visiteurs qui l'ont accepté dans
       le bandeau cookies. Laissez le champ vide et aucun traceur n'est déposé.</p>
  </header>

  <form class="bo-form" method="post" action="<?= url('/admin/parametres/mesure') ?>">
    <?= Csrf::champ() ?>
    <div class="bo-champ">
      <label for="p-mesure">Identifiant Google Analytics</label>
      <input id="p-mesure" type="text" name="mesure_identifiant"
             value="<?= e($parametres['mesure']['identifiant'] ?? '') ?>"
             placeholder="G-XXXXXXXXXX" spellcheck="false" autocomplete="off">
      <span class="aide">Se trouve dans Google Analytics, rubrique
        <em>Administration → Flux de données</em>. L'adresse IP des visiteurs est
        anonymisée. Vide = aucune mesure, et la catégorie reste sans effet dans
        le bandeau cookies.</span>
    </div>
    <button class="bo-btn" type="submit">Enregistrer</button>
  </form>
</section>

<section class="bo-zone" style="margin-top:1.4rem;">
  <header class="bo-zone__tete">
    <h2>Protection des formulaires</h2>
    <p>Les deux formulaires du site sont déjà protégés sans aucun réglage : un champ
       piège invisible, un jeton d'horloge qui refuse les envois postés en moins de
       trois secondes, et un plafond de cinq messages par heure et par visiteur.
       Turnstile n'est qu'un étage supplémentaire, à ajouter si du courrier
       indésirable passait malgré tout.</p>
  </header>

  <form class="bo-form" method="post" action="<?= url('/admin/parametres/antispam') ?>">
    <?= Csrf::champ() ?>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="p-ts-site">Clé du site (Turnstile)</label>
        <input id="p-ts-site" type="text" name="antispam_cle_site"
               value="<?= e($parametres['antispam']['cle_site'] ?? '') ?>"
               placeholder="0x4AAAAAAA…" spellcheck="false" autocomplete="off">
      </div>
      <div class="bo-champ">
        <label for="p-ts-secret">Clé du serveur (Turnstile)</label>
        <input id="p-ts-secret" type="password" name="antispam_cle_secrete"
               value="<?= e($parametres['antispam']['cle_secrete'] ?? '') ?>"
               spellcheck="false" autocomplete="off">
      </div>
    </div>
    <span class="aide">Les deux clés se créent gratuitement sur
      <em>dash.cloudflare.com → Turnstile</em>. Turnstile a été retenu plutôt que
      reCAPTCHA parce qu'il ne dépose pas de cookie et ne profile pas le visiteur :
      reCAPTCHA, lui, est un traceur soumis au consentement, et il aurait donc cessé
      de fonctionner — donc bloqué le formulaire — pour tout visiteur refusant les
      cookies. Pensez à mentionner Cloudflare dans les mentions légales une fois les
      clés saisies. Les deux champs vides = Turnstile éteint.</span>
    <button class="bo-btn" type="submit">Enregistrer</button>
  </form>
</section>

<section class="bo-zone" style="margin-top:1.4rem;">
  <header class="bo-zone__tete">
    <h2>Droits d'accès</h2>
    <p>Cible : <code>0755</code> pour les dossiers, <code>0644</code> pour les fichiers,
       <code>0640</code> pour ceux qui contiennent un secret. Utile après un transfert
       FTP ou une mise à jour, qui ne restaurent pas les droits.</p>
  </header>

  <?php if ($droits['anomalies'] === []): ?>
    <p class="bo-zone__vide"><?= e($droits['examines']) ?> éléments vérifiés, aucune anomalie.</p>
  <?php else: ?>
    <ul class="bo-droits">
      <?php foreach ($droits['anomalies'] as $a): ?>
        <li>
          <code class="mode"><?= e($a['mode']) ?></code>
          <span class="chemin"><?= e($a['chemin']) ?></span>
          <span class="probleme"><?= e($a['probleme']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($droits['tronque']): ?>
      <p class="bo-zone__vide">Liste tronquée — la réparation traite l'ensemble.</p>
    <?php endif; ?>
  <?php endif; ?>

  <form method="post" action="<?= url('/admin/parametres/droits') ?>" style="margin-top:1.1rem;">
    <?= Csrf::champ() ?>
    <button class="bo-btn bo-btn--contour" type="submit">Réparer les droits</button>
  </form>
</section>

<section class="bo-diag">
  <h2>Diagnostic du serveur</h2>
  <table>
    <tbody>
      <?php foreach ($diagnostic as $c): ?>
        <tr>
          <th scope="row"><?= e($c['libelle']) ?></th>
          <td><?= e($c['valeur']) ?></td>
          <td class="etat">
            <?php if ($c['ok'] === true): ?><span class="ok">OK</span>
            <?php elseif ($c['ok'] === false): ?><span class="ko">À corriger</span>
            <?php else: ?><span class="neutre">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
