<?php $c = (array) settings('company'); ?>
<?php partial('page-hero', ['eyebrow' => 'RGPD', 'title' => 'Politique de confidentialité', 'lead' => 'Ce que nous collectons, pourquoi, pendant combien de temps, et comment reprendre la main sur vos données.']); ?>

<section class="section" style="padding-top:0">
  <div class="container container--narrow">
    <div class="article__body">
      <h2>Responsable du traitement</h2>
      <p><?= e($c['legal_name'] ?? '') ?>, <?= e($c['form'] ?? '') ?> au capital de <?= e($c['capital'] ?? '') ?>, <?= e($c['address'] ?? '') ?>, <?= e($c['zip'] ?? '') ?> <?= e($c['city'] ?? '') ?> — SIRET <?= e($c['siret'] ?? '') ?>. Contact : <a href="mailto:<?= e($c['email'] ?? '') ?>"><?= e($c['email'] ?? '') ?></a>.</p>

      <h2>Données collectées</h2>
      <ul>
        <li><strong>Formulaire de candidature :</strong> nom, e-mail, téléphone, secteur souhaité, situation professionnelle, disponibilité, expérience, objectif, message et, si vous le joignez, votre CV.</li>
        <li><strong>Formulaire de contact et pop-in :</strong> nom, e-mail, téléphone facultatif, message.</li>
        <li><strong>Mesure d’audience interne :</strong> pages consultées et étapes du parcours de candidature, associées à une empreinte technique irréversible (non nominative). Aucun cookie publicitaire, aucun traceur tiers.</li>
      </ul>

      <h2>Finalités et bases légales</h2>
      <ul>
        <li>Étudier votre candidature et vous recontacter — <em>mesures précontractuelles prises à votre demande</em>.</li>
        <li>Répondre à vos messages — <em>intérêt légitime</em>.</li>
        <li>Améliorer l’ergonomie du site — <em>intérêt légitime</em>, sur des données non nominatives.</li>
      </ul>

      <h2>Enregistrement des étapes intermédiaires</h2>
      <p>Le formulaire de candidature enregistre les informations que vous avez déjà saisies dès qu’une étape est validée, afin de ne pas vous les redemander et de pouvoir vous recontacter si vous avez indiqué vos coordonnées. Un dossier incomplet est traité comme une candidature abandonnée et supprimé dans les mêmes délais que les autres.</p>

      <h2>Durée de conservation</h2>
      <p>Candidatures : 24 mois à compter du dernier contact. Messages de contact : 12 mois. Journal d’audience : 12 mois glissants. Les CV sont supprimés en même temps que la candidature associée.</p>

      <h2>Destinataires</h2>
      <p>Vos données sont traitées exclusivement par les équipes de <?= e($c['legal_name'] ?? 'Suisse Immo') ?> en charge du recrutement. Elles ne sont ni vendues, ni louées, ni transmises à des tiers, et sont hébergées en France chez <?= e($c['host'] ?? '') ?>.</p>

      <h2>Vos droits</h2>
      <p>Vous disposez d’un droit d’accès, de rectification, d’effacement, de limitation, d’opposition et de portabilité. Écrivez à <a href="mailto:<?= e($c['email'] ?? '') ?>"><?= e($c['email'] ?? '') ?></a> ou par courrier au siège social. Vous pouvez également introduire une réclamation auprès de la CNIL (<a href="https://www.cnil.fr" target="_blank" rel="noopener nofollow">www.cnil.fr</a>).</p>

      <h2>Cookies</h2>
      <p>Le site n’utilise aucun cookie publicitaire ni de mesure d’audience tierce. Seul un cookie de session technique est déposé pour sécuriser les formulaires (protection anti-CSRF) ; il expire à la fermeture du navigateur.</p>
    </div>
  </div>
</section>
