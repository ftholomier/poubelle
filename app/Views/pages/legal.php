<?php $c = (array) settings('company'); ?>
<?php partial('page-hero', ['eyebrow' => 'Informations légales', 'title' => 'Mentions légales']); ?>

<section class="section" style="padding-top:0">
  <div class="container container--narrow">
    <div class="card" style="margin-bottom:28px">
      <h2 class="h3" style="margin-bottom:18px"><?= e($c['legal_name'] ?? '') ?> (Siège)</h2>
      <dl class="recap" style="border:0;background:transparent;padding:0">
        <div><dt>Raison sociale</dt><dd><?= e($c['legal_name'] ?? '') ?></dd></div>
        <div><dt>Adresse</dt><dd><?= e($c['address'] ?? '') ?>, <?= e($c['zip'] ?? '') ?> <?= e($c['city'] ?? '') ?></dd></div>
        <div><dt>Téléphone</dt><dd><?= e($c['phone'] ?? '') ?></dd></div>
        <div><dt>E-mail</dt><dd><?= e($c['email'] ?? '') ?></dd></div>
        <div><dt>SIRET</dt><dd><?= e($c['siret'] ?? '') ?></dd></div>
        <div><dt>Ville RCS</dt><dd><?= e($c['rcs'] ?? '') ?></dd></div>
        <div><dt>N° TVA Intra</dt><dd><?= e($c['vat'] ?? '') ?></dd></div>
        <div><dt>Forme juridique</dt><dd><?= e($c['form'] ?? '') ?></dd></div>
        <div><dt>Capital social</dt><dd><?= e($c['capital'] ?? '') ?></dd></div>
        <div><dt>Assurance RCP</dt><dd><?= e($c['insurance'] ?? '') ?></dd></div>
        <div><dt>Hébergement</dt><dd><?= e($c['host'] ?? '') ?></dd></div>
      </dl>
    </div>

    <div class="article__body">
      <h2>Loi du 6 janvier 1978 relative à l’informatique et aux libertés</h2>
      <p>Conformément à la loi n° 78-17 du 6 janvier 1978, vous pouvez à tout moment accéder aux informations personnelles vous concernant et détenues, demander leur modification ou leur suppression. Ce droit d’accès peut s’exercer en adressant un courrier électronique par l’intermédiaire du formulaire de contact du site, en indiquant les champs et/ou informations dont la modification est souhaitée.</p>

      <h2>Recueil d’informations à caractère nominatif</h2>
      <p>En vertu de la loi informatique et libertés du 6 janvier 1978, les données à caractère nominatif recueillies auprès des internautes par l’intermédiaire d’un formulaire ou autre ne sauraient, en aucun cas, être transmises, à titre gratuit ou onéreux, à des tierces personnes physiques ou morales.</p>

      <h2>Responsabilité des utilisateurs</h2>
      <ul>
        <li>L’utilisateur s’engage à utiliser les services proposés sur le Site de façon loyale et honnête, et conformément à leur destination.</li>
        <li>L’utilisateur s’engage à utiliser les services proposés sur le Site pour ses seuls besoins et s’interdit d’en faire commerce auprès de tiers.</li>
      </ul>

      <h2>Bases de données</h2>
      <p>L’ensemble des informations relatives aux membres figurant sur le site, ainsi que leurs modalités de consultation, constituent les Bases de Données du Site. Ces bases sont la propriété exclusive du Directeur de la publication et sont protégées par les dispositions du Code de la Propriété Intellectuelle relatives au droit d’auteur et par la Directive européenne du 11 mars 1996 sur la protection juridique des bases de données.</p>
      <p>L’utilisateur s’engage à utiliser ces données dans le strict cadre des services proposés sur le Site et s’interdit notamment de reproduire, traduire, adapter, arranger, transformer, communiquer, représenter et distribuer, de façon permanente ou provisoire, par tout moyen et sous quelque forme que ce soit, tout ou partie des données contenues dans ces bases. Toute utilisation ou exploitation faite en violation des présentes conditions est constitutive d’une atteinte aux droits du Directeur de la publication.</p>
      <p>Le Directeur de la publication, en qualité de producteur des bases de données, interdit l’extraction et la réutilisation de la totalité ou d’une partie quelle qu’elle soit de leur contenu. Il met en œuvre tous les moyens nécessaires afin d’empêcher le vol, l’exploitation préjudiciable ou la destruction des bases de données du Site, sans pouvoir garantir les utilisateurs contre ces infractions dans l’hypothèse où elles seraient causées par un manquement de l’hébergeur à ses obligations.</p>

      <h2>Liens vers le site</h2>
      <ul>
        <li>Tout lien hypertexte de tout autre site avec ce site devra faire l’objet d’une autorisation expresse et préalable du Directeur de la publication.</li>
        <li>Toute demande de lien doit être effectuée en adressant un courrier électronique par l’intermédiaire du formulaire de contact du site.</li>
        <li>Les informations contenues dans ce site internet sont fournies par le Directeur de la publication et protégées.</li>
        <li>La distribution, la modification ou la reproduction partielle ou totale de ce site sont interdites sans accord préalable écrit du Directeur de la publication.</li>
      </ul>

      <h2>Risques et pollutions</h2>
      <p>Les informations sur les risques auxquels les biens proposés par Suisse Immo sont exposés sont disponibles sur le site Géorisques : <a href="<?= e($c['georisques'] ?? '#') ?>" rel="noopener nofollow" target="_blank">www.georisques.gouv.fr</a>.</p>
    </div>
  </div>
</section>
