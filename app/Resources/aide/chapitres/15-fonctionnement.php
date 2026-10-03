<?php
return [
    'slug' => 'fonctionnement',
    'title' => 'Fonctionnement du site et mises à jour',
    'summary' => 'Ce qui se passe quand vous enregistrez, les tâches automatiques, les sauvegardes, les mises à jour du site.',
    'sections' => [
        ['id' => 'enregistrement', 'title' => 'Quand vous enregistrez une fiche', 'html' => <<<'HTML'
<ol>
<li>La fiche est écrite et une version est ajoutée à son historique.</li>
<li>La recherche, les menus et les mosaïques la prennent en compte aussitôt.</li>
<li>Les données calculées (statistiques des joueurs, saisons, face-à-face, records, carte) sont recalculées dans la foulée, en quelques secondes.</li>
<li>Les images de la fiche (vignettes, image de partage) sont préparées à la première visite.</li>
</ol>
[[auto|<p>Le site n’utilise pas de base de données : tout est rangé dans des fichiers, ce qui le rend simple à sauvegarder et à déplacer.</p>]]
HTML],
        ['id' => 'taches', 'title' => 'Les tâches automatiques', 'html' => <<<'HTML'
<p>Toutes les cinq minutes, le serveur lance les tâches planifiées : publication des fiches programmées, statistiques, traductions, newsletter, géolocalisation des stades et lieux de naissance, vignettes des vidéos, assistant IA, synchronisation des dons, plan du site, sauvegarde, effacement des données personnelles trop anciennes (RGPD).</p>
<p>Système › <b>Tâches planifiées</b> montre le dernier passage de chacune ; « Lancer » en exécute une tout de suite.</p>
[[img:taches.webp|Les tâches planifiées et leur dernier passage]]
HTML],
        ['id' => 'sauvegardes', 'title' => 'Les sauvegardes', 'html' => <<<'HTML'
<p>Une sauvegarde complète est faite chaque jour : fiches, médiathèque (description), rubriques, réglages, comptes, versions, messages, dons. Les photos elles-mêmes sont ajoutées le dimanche si l’option est cochée. L’hébergeur garde en plus ses propres sauvegardes.</p>
<p>Système › <b>Sauvegardes</b> : liste, téléchargement, sauvegarde immédiate (téléchargement et sauvegarde immédiate réservés aux administrateurs : une archive contient la clé de chiffrement, les réglages et les comptes). Pour revenir à une sauvegarde complète, le webmestre décompresse l’archive et remplace les dossiers <code>data/</code> et <code>storage/</code> sur le serveur. Une fiche seule se restaure bien plus simplement depuis son onglet Historique.</p>
[[img:sauvegardes.webp|Les sauvegardes]]
[[astuce|<p>Téléchargez une sauvegarde de temps en temps et gardez-la ailleurs que sur le serveur (disque externe, espace de stockage du club).</p>]]
HTML],
        ['id' => 'mises-a-jour', 'title' => 'Les mises à jour du site', 'html' => <<<'HTML'
<p>Les évolutions du site (nouvelles fonctions, corrections) sont installées par le webmestre, sans interrompre votre travail : vos fiches, photos et réglages ne sont jamais touchés par une mise à jour.</p>
<p>Pendant une intervention importante, un administrateur peut activer la <b>page d’attente</b> ; vous continuez à travailler dans le back-office.</p>
HTML],
        ['id' => 'securite', 'title' => 'Sécurité et données personnelles', 'html' => <<<'HTML'
<ul>
<li>Chaque membre a son propre compte ; ne partagez pas votre mot de passe.</li>
<li>Les messages, contributions et dons contiennent des données personnelles : ne les exportez que pour les besoins du musée.</li>
<li>Les questions posées à l’assistant IA sont conservées pour une durée limitée, puis effacées automatiquement.</li>
<li>Les visiteurs choisissent leurs cookies ; les vidéos ne se chargent qu’avec leur accord.</li>
</ul>
HTML],
    ],
];
