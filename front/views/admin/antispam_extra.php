<?php
/** Rubrique Anti-spam : bilan des 30 derniers jours, test d'un message, fonctionnement. */
$st = Antispam::stats(30);
$spamWaiting = count(array_filter(Leads::all(), fn($l) => ($l['status'] ?? '') === 'spam'));
$forms = ['devis' => 'Devis', 'contact' => 'Contact', 'rappel' => 'Rappel', 'vocal' => 'Message vocal', 'vocal-resume' => 'Résumé du vocal', 'bot' => 'Assistant'];
$quota = Store::get('botquota', []);
?>
<div class="cols">
  <div class="card">
    <h2>Bilan des 30 derniers jours</h2>
    <div class="kpis kpis--2">
      <div class="kpi"><b><?= (int) $st['total'] ?></b><span>envois de robots arrêtés ou mis de côté</span></div>
      <a class="kpi" href="/admin/demandes?statut=spam"><b><?= $spamWaiting ?></b><span>demande(s) dans le dossier Spam →</span></a>
    </div>
    <?php if ($st['total']): ?>
      <table class="tbl" style="margin-top:14px">
        <thead><tr><th>Raison</th><th class="r">Nombre</th></tr></thead>
        <tbody>
          <?php foreach ($st['reasons'] as $k => $n): ?><tr><td><?= e(Antispam::REASONS[$k] ?? $k) ?></td><td class="r"><b><?= (int) $n ?></b></td></tr><?php endforeach; ?>
        </tbody>
      </table>
      <h3 style="margin-top:20px">Derniers événements</h3>
      <table class="tbl">
        <thead><tr><th>Date</th><th>Formulaire</th><th>Raison</th></tr></thead>
        <tbody>
          <?php foreach ($st['last'] as $ev): ?>
            <tr><td><?= e(date('d/m H:i', strtotime($ev['at']))) ?></td><td><?= e($forms[$ev['form']] ?? $ev['form']) ?></td><td><?= e(Antispam::REASONS[$ev['reason']] ?? $ev['reason']) ?><?= !empty($ev['score']) ? ' · score ' . (int) $ev['score'] : '' ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="bo-muted" style="margin-top:12px">Aucun robot arrêté pour le moment.</p>
    <?php endif; ?>
    <p class="bo-muted" style="margin-top:14px">Assistant : <?= ($quota['day'] ?? '') === date('Y-m-d') ? (int) ($quota['n'] ?? 0) : 0 ?> question(s) aujourd'hui<?= (int) Settings::get('bot_daily_max', 400) > 0 ? ' sur ' . (int) Settings::get('bot_daily_max', 400) . ' autorisées' : '' ?>.</p>
  </div>

  <div>
    <form class="card bo-form" data-antispam-test>
      <h2>Tester un message</h2>
      <p class="bo-muted">Collez un message reçu (ou inventé) pour voir s'il serait mis en spam avec les réglages enregistrés, et pourquoi. Seul le contenu est jugé ici.</p>
      <div class="f-grid">
        <div class="f f--half"><label class="f-label" for="as-n">Nom</label><input id="as-n" name="nom"></div>
        <div class="f f--half"><label class="f-label" for="as-t">Téléphone</label><input id="as-t" name="tel"></div>
        <div class="f"><label class="f-label" for="as-e">E-mail</label><input id="as-e" name="email"></div>
        <div class="f"><label class="f-label" for="as-m">Message</label><textarea id="as-m" name="msg" rows="6"></textarea></div>
      </div>
      <div class="bo-actions"><button class="b b--l" type="submit">Analyser</button></div>
      <div class="as-res" data-antispam-result hidden></div>
    </form>

    <div class="card">
      <h2>Comment ça marche</h2>
      <div class="bo-small">
        <p><b>1. Les robots évidents sont arrêtés net</b> : champ piège invisible rempli, formulaire envoyé depuis un autre site, assistant appelé sans passer par la page. Le robot croit que son envoi a réussi ; rien n'est enregistré.</p>
        <p><b>2. Les envois douteux reçoivent un score</b> : formulaire rempli en quelques secondes, aucun clic ni frappe sur la page, logiciel automatique, liens, mots de spam, message en anglais ou en alphabet étranger, e-mail jetable, numéro fantaisiste, message en double… Au-delà du seuil, la demande va dans le statut <b>Spam</b>, sans e-mail : rien n'est perdu, et un clic sur « Ce n'est pas un spam » la récupère et envoie l'e-mail.</p>
        <p><b>3. Chaque visiteur est limité</b> à quelques envois par quart d'heure, et l'assistant à un nombre de questions par jour.</p>
        <p class="bo-muted">Les spams sont effacés automatiquement au bout de <?= Leads::SPAM_DAYS ?> jours.</p>
      </div>
    </div>
  </div>
</div>
