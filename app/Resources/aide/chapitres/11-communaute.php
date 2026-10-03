<?php
return [
    'slug' => 'communaute',
    'title' => 'Contributions, messages, newsletter, dons',
    'summary' => 'Traiter ce que proposent les visiteurs, leur répondre, envoyer la lettre « Ce jour-là » et suivre les dons.',
    'sections' => [
        ['id' => 'contributions', 'title' => 'Les contributions des visiteurs', 'html' => <<<'HTML'
<p>Sur le site, « Contribuer au musée » permet de proposer une correction, des photos ou des documents (avec cession de droits). Elles arrivent dans Communauté › <b>Contributions</b>, onglet « À traiter ».</p>
[[img:contribution.webp|Une contribution : fichiers, publication, décision, échanges]]
<ol>
<li>Lisez la proposition et ouvrez les <b>fichiers</b> envoyés.</li>
<li><b>Publier les fichiers</b> : dans la médiathèque, rattachés à une fiche, ou comme objet des réserves (crédit et droits de l’auteur repris).</li>
<li><b>Décision</b> : ✓ Valider, ? Demander une précision (l’auteur reçoit un e-mail), ou Refuser.</li>
<li>Pour une correction, faites-la dans la fiche concernée, puis validez la contribution.</li>
</ol>
<p><b>Un témoignage</b> (souvenir de match) peut être publié sur la fiche du match, dans le bloc <b>« Ils y étaient »</b> : choisissez la fiche du match dans « Fiche corrigée ou enrichie », relisez le <b>texte publié</b> (corrigez les fautes, raccourcissez au besoin) et la <b>signature</b> (prénom et initiale par défaut), laissez « Publier ce souvenir » coché, puis ✓ Valider. Refuser ou supprimer la contribution le retire de la fiche.</p>
[[auto|<p>Sur chaque fiche de match, le bloc « Ils y étaient » compte aussi les visiteurs qui cliquent « J’y étais ! » et invite à raconter son souvenir (formulaire prérempli).</p>]]
HTML],
        ['id' => 'messages', 'title' => 'Les messages du formulaire de contact', 'html' => <<<'HTML'
<p>Communauté › <b>Messages</b> : chaque message indique l’objet choisi par le visiteur (devenir partenaire, confier une archive, signaler une erreur, autre demande). <b>Répondre par e-mail</b> envoie la réponse et la garde dans l’historique ; <b>Suivi</b> : statut (nouveau, lu, traité) et personne chargée de répondre.</p>
[[img:messages.webp|Les messages reçus]]
HTML],
        ['id' => 'newsletter', 'title' => 'La newsletter « Ce jour-là »', 'html' => <<<'HTML'
<p>Une lettre hebdomadaire présente les matchs joués cette semaine-là dans l’histoire. Elle part automatiquement (jour et heure dans Réglages › Newsletter) aux abonnés confirmés.</p>
<p>Communauté › <b>Newsletter</b> : abonnés, aperçu de la prochaine lettre, <b>envoi de test</b> à votre adresse, dernier envoi.</p>
[[img:newsletter.webp|La newsletter : abonnés et prochaine lettre]]
HTML],
        ['id' => 'dons', 'title' => 'Le suivi des dons', 'html' => <<<'HTML'
<p>Communauté › <b>Dons</b> : jauge de la collecte, liste des dons (carte bancaire via Stripe, PayPal, hors ligne), dons mensuels, export CSV pour la comptabilité.</p>
<ul>
<li><b>Enregistrer un don hors ligne</b> (chèque, virement, espèces ; administrateurs) : il compte dans la jauge. Son remboursement éventuel se note aussi par un administrateur.</li>
<li><b>Mur des donateurs</b> : les noms que les donateurs ont accepté d’afficher.</li>
<li>Détail d’un don : paiements reçus, état du don mensuel, note interne.</li>
</ul>
[[img:dons.webp|Le suivi de la collecte]]
[[attention|<p>Les reçus fiscaux sont prêts mais désactivés : un administrateur ne les active (Réglages › Dons) que si l’association y a droit. Ils sont émis et consultés par les administrateurs.</p>]]
HTML],
    ],
];
