<div class="card">
  <h2>Tester l'envoi</h2>
  <p class="bo-muted">Envoie un e-mail de test à l'adresse de réception enregistrée (<?= e((string) Settings::get('notify_email')) ?>).</p>
  <button class="b b--l" type="button" data-action="mail.test">Envoyer un e-mail de test</button>
</div>
