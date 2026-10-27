<?php
/** @var array $s @var array $secrets @var string $cronUrl @var string $cronCmd @var ?string $cron @var string $csrf */
$v = static fn(string $path) => e(array_get($s, $path, ''));
$chk = static fn(string $path) => array_get($s, $path) ? ' checked' : '';
$secretField = static function (string $name, string $label) use ($secrets): string {
    $masked = $secrets[$name] ?? '';
    return '<label>' . e($label) . '<input type="password" name="secret[' . e($name) . ']" autocomplete="new-password" placeholder="' . e($masked !== '' ? 'Enregistrée : ' . $masked . ' (laisser vide pour garder)' : 'Non renseignée') . '"></label>'
        . ($masked !== '' ? '<label class="check small"><input type="checkbox" name="secret_delete[' . e($name) . ']" value="1"> Supprimer cette clé</label>' : '');
};
$redirects = implode("\n", array_map(static fn($r) => $r['from'] . ' => ' . $r['to'], $s['redirects'] ?? []));
?>
<nav class="anchors"><a href="#identite">Identité</a><a href="#coordonnees">Coordonnées</a><a href="#emails">E-mails</a><a href="#sms">SMS</a><a href="#seo">SEO</a><a href="#perf">Performance</a><a href="#redirections">Redirections</a><a href="#cron">Tâches planifiées</a><a href="#sauvegarde">Sauvegarde</a></nav>
<form method="post" class="settings-form">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

  <section class="card" id="identite">
    <div class="card__head"><h2>Identité du site</h2></div>
    <div class="form form--cols">
      <label>Nom du site<input name="site[name]" value="<?= $v('site.name') ?>"></label>
      <label>Adresse du site (URL canonique)<input name="site[url]" type="url" value="<?= $v('site.url') ?>" placeholder="https://www.le-digital.com"></label>
      <label>Nom affiché (pied de page, articles)<input name="site[owner]" value="<?= $v('site.owner') ?>"></label>
      <label class="check"><input type="checkbox" name="features[animations]" value="1"<?= $chk('features.animations') ?>> Animations du site (bulles, apparitions, compteurs)</label>
      <div><span class="lbl">Logo (fond clair)</span><div class="imgfield" data-imgfield><input type="hidden" name="site[logo]" value="<?= $v('site.logo') ?>"></div></div>
      <div><span class="lbl">Logo (fond sombre)</span><div class="imgfield imgfield--dark" data-imgfield><input type="hidden" name="site[logo_white]" value="<?= $v('site.logo_white') ?>"></div></div>
      <div><span class="lbl">Photo de l’assistant (ronde, visage)</span><div class="imgfield" data-imgfield><input type="hidden" name="site[avatar]" value="<?= $v('site.avatar') ?>"></div></div>
      <div><span class="lbl">Image de partage (réseaux sociaux)</span><div class="imgfield" data-imgfield><input type="hidden" name="site[og_image]" value="<?= $v('site.og_image') ?>"></div></div>
    </div>
  </section>

  <section class="card" id="coordonnees">
    <div class="card__head"><h2>Coordonnées</h2></div>
    <div class="form form--cols">
      <label>Numéro direct<input name="contact[phone]" type="tel" value="<?= $v('contact.phone') ?>" placeholder="06 12 34 56 78"></label>
      <label>E-mail public<input name="contact[email]" type="email" value="<?= $v('contact.email') ?>"></label>
      <label>Profil LinkedIn<input name="contact[linkedin]" type="url" value="<?= $v('contact.linkedin') ?>" placeholder="https://www.linkedin.com/in/…"></label>
      <label>SIRET<input name="contact[siret]" value="<?= $v('contact.siret') ?>"></label>
      <label>Adresse<input name="contact[address]" value="<?= $v('contact.address') ?>"></label>
      <label>Code postal / Ville<span class="duo"><input name="contact[postal_code]" value="<?= $v('contact.postal_code') ?>" aria-label="Code postal"><input name="contact[city]" value="<?= $v('contact.city') ?>" aria-label="Ville"></span></label>
    </div>
  </section>

  <section class="card" id="emails">
    <div class="card__head"><h2>E-mails</h2><button type="button" class="btn btn--ghost" data-test-email>Envoyer un e-mail de test</button></div>
    <div class="form form--cols">
      <label>Recevoir les demandes à<input name="mail[notify_email]" type="email" value="<?= $v('mail.notify_email') ?>"></label>
      <label>Mode d’envoi
        <select name="mail[mode]">
          <option value="mail"<?= ($s['mail']['mode'] ?? '') === 'mail' ? ' selected' : '' ?>>Fonction mail() de l’hébergement</option>
          <option value="smtp"<?= ($s['mail']['mode'] ?? '') === 'smtp' ? ' selected' : '' ?>>Serveur SMTP (recommandé : Brevo, OVH, Gmail…)</option>
          <option value="off"<?= ($s['mail']['mode'] ?? '') === 'off' ? ' selected' : '' ?>>Désactivé</option>
        </select>
      </label>
      <label>Adresse d’expédition<input name="mail[from_email]" type="email" value="<?= $v('mail.from_email') ?>" placeholder="contact@le-digital.com"></label>
      <label>Nom d’expéditeur<input name="mail[from_name]" value="<?= $v('mail.from_name') ?>"></label>
      <label class="check"><input type="checkbox" name="mail[notify]" value="1"<?= $chk('mail.notify') ?>> M’envoyer un e-mail à chaque demande (avec le vocal en pièce jointe)</label>
      <label class="check"><input type="checkbox" name="mail[confirm_visitor]" value="1"<?= $chk('mail.confirm_visitor') ?>> Envoyer une confirmation au visiteur (RdV avec fichier agenda)</label>
      <label>Serveur SMTP<input name="mail[smtp_host]" value="<?= $v('mail.smtp_host') ?>" placeholder="smtp-relay.brevo.com"></label>
      <label>Port / sécurité<span class="duo"><input name="mail[smtp_port]" type="number" value="<?= $v('mail.smtp_port') ?>" aria-label="Port"><select name="mail[smtp_secure]" aria-label="Sécurité"><?php foreach (['tls' => 'STARTTLS', 'ssl' => 'SSL', 'none' => 'Aucune'] as $k => $l): ?><option value="<?= $k ?>"<?= ($s['mail']['smtp_secure'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></span></label>
      <label>Identifiant SMTP<input name="mail[smtp_user]" value="<?= $v('mail.smtp_user') ?>" autocomplete="off"></label>
      <?= $secretField('smtp_password', 'Mot de passe SMTP') ?>
    </div>
    <div class="maildiag">
      <h3>Diagnostic des e-mails</h3>
      <ul class="maildiag__list">
        <?php foreach (LD\Mailer::diagnostics() as [$label, $ok, $detail]): ?>
          <li class="<?= $ok === true ? 'is-ok' : ($ok === false ? 'is-ko' : 'is-info') ?>"><span class="maildiag__ic" aria-hidden="true"><?= $ok === true ? '✓' : ($ok === false ? '!' : 'i') ?></span><span><b><?= e($label) ?></b><br><small><?= e((string) $detail) ?></small></span></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($recent = LD\Mailer::recent(8)): ?>
        <details class="details"><summary>Derniers envois</summary>
          <table class="table table--sm"><tbody>
            <?php foreach ($recent as $m): ?><tr><td><small><?= e(date('d/m H:i', strtotime((string) $m['at']))) ?></small></td><td><small><?= e(mb_strimwidth((string) ($m['subject'] ?? ''), 0, 60, '…')) ?></small><br><small class="muted"><?= e((string) ($m['to'] ?? '')) ?></small></td><td><?= !empty($m['ok']) ? '<span class="status status--done">Parti</span>' : '<span class="status status--new">Échec</span><br><small>' . e((string) ($m['error'] ?? '')) . '</small>' ?></td></tr><?php endforeach; ?>
          </tbody></table>
        </details>
      <?php endif; ?>
      <details class="details"><summary>Les e-mails n’arrivent pas ? La solution la plus fiable : SMTP</summary>
        <ol class="small steps">
          <li>Crée une boîte e-mail sur ton domaine chez ton hébergeur (ex. <code>contact@<?= e(LD\Mailer::domain()) ?></code>) ou utilise Brevo (gratuit jusqu’à 300 e-mails/jour).</li>
          <li>Ici : <i>Mode d’envoi</i> = <b>Serveur SMTP</b>, puis le serveur (ex. <code>mail.<?= e(LD\Mailer::domain()) ?></code> ou <code>smtp-relay.brevo.com</code>), port 587 + STARTTLS (ou 465 + SSL), l’adresse complète en identifiant et son mot de passe.</li>
          <li><i>Adresse d’expédition</i> : la même boîte. Enregistre, puis clique sur « Envoyer un e-mail de test ».</li>
          <li>Avec la fonction mail(), l’hébergeur doit figurer dans l’enregistrement SPF du domaine, sinon Gmail et Outlook rejettent ou classent en indésirables.</li>
        </ol>
      </details>
    </div>
  </section>

  <section class="card" id="sms">
    <div class="card__head"><h2>SMS (rappel de RdV la veille)</h2><?php if (!empty($s['sms']['enabled'])): ?><button type="button" class="btn btn--ghost" data-test-sms>Envoyer un SMS de test</button><?php endif; ?></div>
    <label class="check"><input type="checkbox" name="sms[enabled]" value="1" data-shows="#sms-fields"<?= $chk('sms.enabled') ?>> Activer les SMS</label>
    <p class="small muted">Désactivés, la case « M’envoyer un rappel par SMS la veille » disparaît de la prise de RdV et aucun SMS ne part (le rappel par e-mail reste). Tes réglages ci-dessous sont gardés. Activés, la case n’apparaît sur le site qu’une fois un fournisseur configuré.</p>
    <div class="form form--cols" id="sms-fields"<?= !empty($s['sms']['enabled']) ? '' : ' hidden' ?>>
      <label>Fournisseur
        <select name="sms[provider]">
          <?php foreach (['none' => 'Aucun (rappel par e-mail uniquement)', 'brevo' => 'Brevo', 'twilio' => 'Twilio'] as $k => $l): ?><option value="<?= $k ?>"<?= ($s['sms']['provider'] ?? 'none') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Nom d’expéditeur (11 caractères max)<input name="sms[sender]" maxlength="11" value="<?= $v('sms.sender') ?>"></label>
      <?= $secretField('brevo_api_key', 'Clé API Brevo') ?>
      <label>Twilio — Account SID<input name="sms[twilio_sid]" value="<?= $v('sms.twilio_sid') ?>"></label>
      <?= $secretField('twilio_auth_token', 'Twilio — Auth Token') ?>
      <label>Twilio — Numéro d’envoi<input name="sms[twilio_from]" value="<?= $v('sms.twilio_from') ?>" placeholder="+33…"></label>
      <label class="span2">Texte du rappel <small class="muted">{time} {mode} {name}</small><input name="sms[reminder_text]" value="<?= $v('sms.reminder_text') ?>"></label>
    </div>
  </section>

  <section class="card" id="seo">
    <div class="card__head"><h2>Référencement & mesure d’audience</h2></div>
    <div class="form form--cols">
      <label>Suffixe des titres de page<input name="seo[title_suffix]" value="<?= $v('seo.title_suffix') ?>"></label>
      <label>Code de vérification Google Search Console<input name="seo[google_verification]" value="<?= $v('seo.google_verification') ?>"></label>
      <label class="span2">Code de mesure d’audience (inséré dans &lt;head&gt; : Plausible, Matomo, Google Analytics…) — chargé seulement après l’accord du visiteur (bandeau cookies)<textarea name="seo[analytics_head]" rows="3" class="mono"><?= $v('seo.analytics_head') ?></textarea></label>
      <label class="check span2"><input type="checkbox" name="seo[noindex]" value="1"<?= $chk('seo.noindex') ?>> Masquer le site aux moteurs de recherche (pré-production)</label>
    </div>
    <p class="muted small">Le sitemap (<a href="/sitemap.xml" target="_blank">/sitemap.xml</a>), le robots.txt, les balises Open Graph et les données structurées (entreprise locale, articles) sont générés automatiquement.</p>
  </section>

  <section class="card" id="perf">
    <div class="card__head"><h2>Performance</h2></div>
    <div class="form form--cols">
      <label class="check"><input type="checkbox" name="performance[page_cache]" value="1"<?= $chk('performance.page_cache') ?>> Cache des pages (vidé automatiquement à chaque modification)</label>
      <label>Durée du cache (secondes)<input type="number" name="performance[cache_ttl]" min="60" value="<?= $v('performance.cache_ttl') ?>"></label>
      <label class="check"><input type="checkbox" name="debug" value="1"<?= $chk('debug') ?>> Mode débogage (affiche les erreurs PHP — à désactiver en production)</label>
    </div>
  </section>

  <section class="card" id="redirections">
    <div class="card__head"><h2>Redirections 301</h2></div>
    <p class="small muted">Pour conserver le référencement des anciennes adresses (ex. ancien site WordPress). Une redirection par ligne : <code>/ancienne-page => /nouvelle-page</code></p>
    <textarea name="redirects" rows="5" class="mono" placeholder="/formation-cpf => /formation"><?= e($redirects) ?></textarea>
  </section>

  <div class="savebar"><span class="savebar__state">Paramètres du site</span><button class="btn btn--red" type="submit">Enregistrer les paramètres</button></div>
</form>

<section class="card" id="cron">
  <div class="card__head"><h2>Tâches planifiées</h2></div>
  <p class="small">Les rappels de rendez-vous (e-mail<?= LD\Sms::enabled() ? ' + SMS' : '' ?> la veille) partent grâce à une tâche lancée <b>toutes les heures</b>. Dernier passage : <?= $cron ? e(date('d/m/Y à H:i', strtotime($cron))) : '<b>jamais</b>' ?>.</p>
  <p class="small">Commande « cron » (hébergement avec accès SSH) :</p>
  <div class="copy"><input readonly value="0 * * * * <?= e($cronCmd) ?>" aria-label="Commande cron"><button type="button" class="btn btn--ghost" data-copy>Copier</button></div>
  <p class="small">Ou adresse à appeler toutes les heures (tâche planifiée de ton hébergeur, cron-job.org…) :</p>
  <div class="copy"><input readonly value="<?= e($cronUrl) ?>" aria-label="URL de la tâche planifiée"><button type="button" class="btn btn--ghost" data-copy>Copier</button></div>
</section>

<section class="card" id="sauvegarde">
  <div class="card__head"><h2>Sauvegarde & cache</h2></div>
  <p class="small">Tout le site tient dans des fichiers JSON : la sauvegarde contient les contenus, les demandes, les rendez-vous, les avis, les articles, les clés (chiffrées) et les images envoyées.</p>
  <div class="actions">
    <a class="btn btn--dark" href="/admin/sauvegarde">Télécharger une sauvegarde (.zip)</a>
    <form method="post" action="/admin/cache/vider"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn btn--ghost" type="submit">Vider le cache</button></form>
  </div>
</section>
