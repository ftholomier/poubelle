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
<p>Toutes les cinq minutes, le serveur lance les tâches planifiées : publication des fiches programmées, statistiques, traductions, correcteur d’orthographe, newsletter, géolocalisation des stades et lieux de naissance, vignettes des vidéos, assistant IA, synchronisation des dons, plan du site, sauvegarde, effacement des données personnelles trop anciennes (RGPD).</p>
<p>Système › <b>Serveur / tâches planifiées</b> (administrateurs) montre le dernier passage de chacune ; « Lancer » en exécute une tout de suite.</p>
<p>En haut de cette page, le pavé <b>Serveur</b> montre la mémoire du site en deux colonnes, avec une alerte par e-mail aux administrateurs à 90 % (lien sécurisé pour vider directement ; pas d’alerte pour un Redis qui efface lui-même ses entrées une fois plein, où être près du maximum est normal) :</p>
<ul>
<li><b>OPcache</b> (à gauche) : le code et les données lues à chaque page, gardés compilés en mémoire. Jauge (utile, copies périmées, libre), bouton « Vider OPcache », diagnostic technique, et le dépliant <b>« Ce qui occupe OPcache »</b> : les dossiers qui prennent le plus de place, en Mo et en nombre de fichiers, tous sites du compte confondus (un autre site du même hébergement, comme un ancien WordPress, y apparaît aussi).</li>
<li><b>Redis</b> (à droite) : les autres caches (médiathèque, albums, recherche, grosses données calculées). Jauge, bouton « Vider le cache Redis », mot de passe, et le dépliant <b>« Ce qui occupe Redis »</b> : la place prise par chaque dossier de cache de ce musée, en Mo et en nombre d’entrées. Le total reste un peu inférieur au chiffre de la jauge, qui compte aussi le fonctionnement interne de Redis et les autres musées. Une ligne « entrées d’avant le répertoire » peut apparaître après une mise à jour : videz Redis une fois pour que tout soit rangé par dossier. <b>Redis se vide-t-il tout seul ?</b> Oui : chaque entrée s’efface au bout de 24 h, et l’ancienne version d’un cache dès qu’il est refait (une fiche enregistrée refait l’index, les calculs…) ; c’est pourquoi la jauge monte et descend souvent, c’est normal. Une fois plein, le pavé indique ce que fait Redis : soit il efface lui-même les entrées les moins utiles, soit il refuse les nouvelles et le site lit ses fichiers, un peu moins vite, sans rien casser. « Vider » efface aussi les entrées rangées sous l’ancien nom avant une mise à jour.</li>
</ul>
<p>Vider OPcache ou Redis ne perd rien : ce ne sont que des copies, qui se refont toutes seules (les premières pages sont juste un peu plus lentes).</p>
[[img:taches.webp|Les tâches planifiées et leur dernier passage]]
HTML],
        ['id' => 'sauvegardes', 'title' => 'Les sauvegardes', 'html' => <<<'HTML'
<p>Une sauvegarde complète est faite chaque jour : fiches, médiathèque (description), rubriques, réglages, comptes, versions, messages, dons. Les photos elles-mêmes sont ajoutées le dimanche si l’option est cochée. L’hébergeur garde en plus ses propres sauvegardes.</p>
<p>Système › <b>Sauvegardes</b> (administrateurs) : liste, téléchargement, sauvegarde immédiate (une archive contient la clé de chiffrement, les réglages et les comptes). Pour revenir à une sauvegarde complète, le webmestre décompresse l’archive et remplace les dossiers <code>data/</code> et <code>storage/</code> sur le serveur. Une fiche seule se restaure bien plus simplement depuis son onglet Historique.</p>
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
<li>Pour la traduction anglaise et le correcteur d’orthographe, les textes des fiches sont envoyés à Gemini (Google) ; les messages, contributions et dons ne le sont jamais.</li>
<li>Les visiteurs choisissent leurs cookies ; les vidéos ne se chargent qu’avec leur accord.</li>
</ul>
HTML],
    ],
];
