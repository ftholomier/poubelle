<?php
/**
 * Paramètres : envoi des e-mails, compte administrateur, diagnostic.
 *
 * @var array $parametres
 * @var string $identifiant
 * @var array $diagnostic
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
               placeholder="contact@etang-fourchu.com">
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
        <input id="p-hote" type="text" name="hote" value="<?= e($smtp['hote']) ?>" placeholder="mail.etang-fourchu.com">
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
               autocomplete="off" placeholder="contact@etang-fourchu.com">
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
               placeholder="contact@etang-fourchu.com">
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
    <div class="bo-champ">
      <label for="p-mdp-actuel">Mot de passe actuel</label>
      <input id="p-mdp-actuel" type="password" name="mot_de_passe_actuel" required autocomplete="current-password">
      <span class="aide">Exigé pour toute modification du compte.</span>
    </div>
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
