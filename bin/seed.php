<?php
declare(strict_types=1);

/**
 * Contenus de départ (pages, actualités, avis).
 * Usage : php bin/seed.php [--force]
 * Sans --force, les fichiers déjà présents ne sont pas touchés.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Config;
use App\Store;

$force = \in_array('--force', $argv ?? [], true);

/** Écrit un fichier de contenu s'il est absent (ou si --force). */
$put = static function (string $relative, array $data) use ($force): void {
    if (!$force && Store::exists($relative)) {
        echo "· {$relative} (déjà présent)\n";
        return;
    }
    Store::write($relative, $data, 'seed');
    echo ($force ? '↻' : '+') . " {$relative}\n";
};

// ---------------------------------------------------------------- Accueil

$put('pages/home.fr.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'home', 'lang' => 'fr', 'status' => 'published', 'nav' => 'Accueil',
    'seo' => [
        'title' => 'Coworking et bureaux tout compris à Besançon',
        'description' => "Bureaux privés et postes en open space à Besançon centre. Charges, fibre, ménage, salle de réunion et café compris. Deux adresses : Carnot et Granvelle.",
        'ogImage' => '/media/espace-carnot.webp',
    ],
    'hero' => [
        'badge' => '{count} POSTES DISPONIBLES CE MOIS',
        'title1' => 'Un bureau qui donne',
        'title2' => "envie d'y aller",
        'highlight' => 'le lundi.',
        'text' => "Bureaux privés et postes en open space à Besançon centre. Vous signez, vous posez vos affaires, vous travaillez. Charges, fibre, ménage, salle de réunion et café : tout est dedans.",
        'ctaPrimary' => ['label' => 'Voir les bureaux libres', 'route' => 'offices'],
        'ctaSecondary' => ['label' => 'Réserver une visite', 'route' => 'contact'],
        'stats' => [
            ['value' => '170 m²', 'label' => 'sur 2 adresses'],
            ['value' => '7j/7', 'label' => 'accès 24h/24 par badge'],
            ['value' => '0 €', 'label' => 'de frais cachés'],
        ],
        'slides' => ['/media/p1.jpg', '/media/p2.jpg', '/media/p3.jpg', '/media/p4.jpg', '/media/p5.jpg', '/media/p6.jpg', '/media/p7.jpg'],
        'cardPrice' => ['label' => 'À PARTIR DE', 'note' => 'HT/mois'],
        'cardBadge' => ['kicker' => 'CLÉ EN MAIN', 'line1' => 'Tout inclus,', 'line2' => 'zéro frais caché'],
    ],
    'places' => ['kicker' => 'NOS ESPACES', 'title' => 'Deux adresses, deux ambiances', 'cta' => 'Visiter les 2 espaces'],
    'availability' => ['kicker' => 'DISPONIBILITÉS', 'title' => 'Ce qui est libre, maintenant'],
    'steps' => [
        'title' => 'Installé en 3 étapes',
        'items' => [
            ['n' => '1', 'title' => 'Vous visitez', 'text' => "20 minutes sur place, café compris. On vous montre les deux espaces et les postes libres.", 'color' => '#FFFFFF'],
            ['n' => '2', 'title' => 'Vous signez', 'text' => "Un contrat de prestation de services simple, sans bail commercial ni frais de dossier.", 'color' => '#FFD100'],
            ['n' => '3', 'title' => 'Vous travaillez', 'text' => "Badge remis, fibre branchée, café chaud. Vous pouvez entrer sous 48 h.", 'color' => '#12B39A'],
        ],
    ],
    'reviews' => ['title' => 'Ils y sont bien'],
    'band' => [
        'title' => 'Il reste {count} places. Pas 40.',
        'text' => "Visite en 20 minutes, contrat en prestation de services, entrée possible sous 48 h.",
        'cta1' => 'Réserver mon bureau',
        'cta2' => 'Parler à un humain',
    ],
    'faq' => [
        'title' => 'Questions fréquentes',
        'items' => [
            ['q' => "Y a-t-il un engagement de durée ?", 'a' => "Non. Le iOiO fonctionne en contrat de prestation de services, avec un préavis court. Vous partez quand votre activité change."],
            ['q' => "Qu'est-ce qui est vraiment compris dans le prix ?", 'a' => "Charges, eau, électricité, chauffage, ménage, fibre très haut débit, mobilier, salle de réunion, cuisine équipée et café. Il n'y a pas de frais additionnels."],
            ['q' => "Puis-je venir seulement quelques jours par semaine ?", 'a' => "Oui, un poste nomade 3 jours par semaine existe à Granvelle à 90 € HT/mois, dans la limite des places disponibles."],
            ['q' => "Puis-je recevoir mes clients ?", 'a' => "Bien sûr. Carnot dispose d'une salle de réunion équipée d'un vidéoprojecteur ; Granvelle a un espace bar et un coin canapé pour les rendez-vous informels."],
        ],
    ],
]);

$put('pages/home.en.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'home', 'lang' => 'en', 'status' => 'published', 'nav' => 'Home',
    'seo' => [
        'title' => 'All-inclusive coworking and offices in Besançon',
        'description' => "Private offices and dedicated desks in central Besançon. Bills, fibre, cleaning, meeting room and coffee included. Two addresses: Carnot and Granvelle.",
        'ogImage' => '/media/espace-carnot.webp',
    ],
    'hero' => [
        'badge' => '{count} DESKS AVAILABLE THIS MONTH',
        'title1' => 'An office you actually',
        'title2' => 'want to walk into',
        'highlight' => 'on a Monday.',
        'text' => "Private offices and dedicated desks in central Besançon. Sign, drop your things, get to work. Bills, fibre, cleaning, meeting room and coffee are all in.",
        'ctaPrimary' => ['label' => 'See available offices', 'route' => 'offices'],
        'ctaSecondary' => ['label' => 'Book a viewing', 'route' => 'contact'],
        'stats' => [
            ['value' => '170 sqm', 'label' => 'across 2 addresses'],
            ['value' => '7/7', 'label' => '24/7 badge access'],
            ['value' => '€0', 'label' => 'hidden fees'],
        ],
        'cardPrice' => ['label' => 'FROM', 'note' => 'excl. VAT/mo'],
        'cardBadge' => ['kicker' => 'TURNKEY', 'line1' => 'All inclusive,', 'line2' => 'zero hidden fees'],
    ],
    'places' => ['kicker' => 'OUR SPACES', 'title' => 'Two addresses, two moods', 'cta' => 'Tour both spaces'],
    'availability' => ['kicker' => 'AVAILABILITY', 'title' => 'What is free, right now'],
    'steps' => [
        'title' => 'Moved in, in 3 steps',
        'items' => [
            ['n' => '1', 'title' => 'You visit', 'text' => "20 minutes on site, coffee included. We show you both spaces and the free desks.", 'color' => '#FFFFFF'],
            ['n' => '2', 'title' => 'You sign', 'text' => "A simple service contract, no commercial lease, no admin fees.", 'color' => '#FFD100'],
            ['n' => '3', 'title' => 'You work', 'text' => "Badge handed over, fibre plugged in, coffee hot. You can move in within 48 h.", 'color' => '#12B39A'],
        ],
    ],
    'reviews' => ['title' => 'They feel good here'],
    'band' => [
        'title' => '{count} spots left. Not 40.',
        'text' => "20-minute viewing, service-contract lease, move in within 48 h.",
        'cta1' => 'Book my office',
        'cta2' => 'Talk to a human',
    ],
    'faq' => [
        'title' => 'Frequently asked',
        'items' => [
            ['q' => "Is there a minimum term?", 'a' => "No. The iOiO works with a service contract and a short notice period. You leave when your business changes."],
            ['q' => "What is really included in the price?", 'a' => "Bills, water, electricity, heating, cleaning, high-speed fibre, furniture, meeting room, fitted kitchen and coffee. There are no additional fees."],
            ['q' => "Can I come only a few days a week?", 'a' => "Yes, a nomad desk three days a week is available at Granvelle for €90 excl. VAT per month, subject to availability."],
            ['q' => "Can I welcome my clients?", 'a' => "Of course. Carnot has a meeting room with a projector; Granvelle has a bar area and a sofa corner for informal meetings."],
        ],
    ],
]);

// ------------------------------------------------------------- Nos espaces

$spacesFr = [
    '_schema' => Config::SCHEMA,
    'slug' => 'spaces', 'lang' => 'fr', 'status' => 'published', 'nav' => 'Nos espaces',
    'seo' => [
        'title' => 'Nos espaces — Carnot et Granvelle',
        'description' => "170 m² sur deux adresses au centre de Besançon : bureaux privés avenue Carnot, coworking rue Granvelle. Tout compris, accès 24h/24.",
        'ogImage' => '/media/espace-granvelle.webp',
    ],
    'kicker' => 'NOS ESPACES · 170 M² · BESANÇON CENTRE',
    'title' => "Plus qu'un simple bureau : un endroit où l'on se sent bien.",
    'text' => "Location clé en main sous forme de prestation de services. Vous signez, vous posez vos affaires, c'est parti.",
    'spaces' => [
        [
            'id' => 'carnot',
            'bg' => '#FFF8EA',
            'tag' => 'BUREAUX PRIVÉS · 70 M²',
            'text' => "Une pépinière à taille humaine dans une cour intérieure, à deux pas du parc Micaud. 4 bureaux privés de 9 à 12 m², une salle de réunion et un espace détente entièrement refaits. Calme absolu, accès 7j/7 24h/24.",
            'cta' => 'Voir les bureaux Carnot',
            'access' => ['Tram à 50 m', 'Boucle à 300 m', 'Parking à 500 m', 'Gare Viotte à 800 m', '2 restaurants à 50 m', 'Supermarché à 200 m'],
            'photos' => ['/media/espace-carnot.webp', '/media/p1.jpg', '/media/p5.jpg'],
            'amenities' => [
                ['title' => 'Bureau privé', 'text' => "De 9 à 12 m², fermé, au calme, accès badge 7j/7 24h/24.", 'color' => '#FFD100'],
                ['title' => 'Fibre', 'text' => "Très haut débit en filaire et en wifi, sans supplément.", 'color' => '#12B39A'],
                ['title' => 'Mobilier', 'text' => "Bureau, fauteuil, chaises visiteurs et rangement fermé.", 'color' => '#FFD100'],
                ['title' => 'Salle de réunion', 'text' => "Partagée, équipée d'un système de vidéoprojection.", 'color' => '#EDE5D5'],
                ['title' => 'Détente', 'text' => "Cuisine équipée (cafetière, frigo, micro-ondes), douche et wc.", 'color' => '#12B39A'],
                ['title' => 'Et tout le reste', 'text' => "Charges, eau, électricité, chauffage et ménage compris.", 'color' => '#FFD100'],
            ],
        ],
        [
            'id' => 'granvelle',
            'bg' => '#FFFFFF',
            'tag' => 'COWORKING · 100 M²',
            'text' => "Face au parc Granvelle, dans une rue au calme au cœur de la boucle. Bureaux privés ou postes dédiés en open space, casier à clé, table bar de travail et coin canapé. Un lieu cocooning où l'on se sent bien pour travailler.",
            'cta' => 'Voir les postes Granvelle',
            'access' => ['Face au parc Granvelle', 'Bus et tram', 'Parking Mairie', 'Parking Chamars', 'Au centre de la boucle'],
            'photos' => ['/media/espace-granvelle.webp', '/media/p6.jpg', '/media/p7.jpg'],
            'amenities' => [
                ['title' => 'Poste dédié', 'text' => "Votre bureau attitré en open space, avec casier à clé.", 'color' => '#12B39A'],
                ['title' => 'Wifi fibre', 'text' => "Très haut débit partout dans l'espace.", 'color' => '#FFD100'],
                ['title' => 'Mobilier', 'text' => "Bureau, fauteuil ergonomique et rangement personnel.", 'color' => '#FFD100'],
                ['title' => 'Cuisine', 'text' => "Cafetière, frigo, micro-ondes, wc.", 'color' => '#EDE5D5'],
                ['title' => 'Business / détente', 'text' => "Table bar de travail, canapé et fauteuils pour vos rendez-vous.", 'color' => '#12B39A'],
                ['title' => 'Et tout le reste', 'text' => "Charges, eau, électricité, chauffage et ménage compris.", 'color' => '#FFD100'],
            ],
        ],
    ],
];
$put('pages/spaces.fr.json', $spacesFr);

$put('pages/spaces.en.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'spaces', 'lang' => 'en', 'status' => 'published', 'nav' => 'Our spaces',
    'seo' => [
        'title' => 'Our spaces — Carnot and Granvelle',
        'description' => "170 sqm across two addresses in central Besançon: private offices on avenue Carnot, coworking on rue Granvelle. All inclusive, 24/7 access.",
        'ogImage' => '/media/espace-granvelle.webp',
    ],
    'kicker' => 'OUR SPACES · 170 SQM · CENTRAL BESANÇON',
    'title' => 'More than an office: a place that feels good.',
    'text' => "Turnkey rental as a service contract. Sign, drop your things, done.",
    'spaces' => [
        [
            'id' => 'carnot',
            'tag' => 'PRIVATE OFFICES · 70 SQM',
            'text' => "A human-sized business hub in an inner courtyard, a stone's throw from Micaud park. 4 private offices from 9 to 12 sqm, a meeting room and a fully refurbished lounge. Absolute quiet, 24/7 access.",
            'cta' => 'See the Carnot offices',
            'access' => ['Tram 50 m away', 'City loop 300 m', 'Car park 500 m', 'Viotte station 800 m', '2 restaurants 50 m', 'Supermarket 200 m'],
            'amenities' => [
                ['title' => 'Private office', 'text' => "From 9 to 12 sqm, closed, quiet, 24/7 badge access."],
                ['title' => 'Fibre', 'text' => "High-speed wired and wifi, at no extra cost."],
                ['title' => 'Furniture', 'text' => "Desk, armchair, visitor chairs and closed storage."],
                ['title' => 'Meeting room', 'text' => "Shared, fitted with a video projection system."],
                ['title' => 'Lounge', 'text' => "Fitted kitchen (coffee machine, fridge, microwave), shower and toilets."],
                ['title' => 'And all the rest', 'text' => "Bills, water, electricity, heating and cleaning included."],
            ],
        ],
        [
            'id' => 'granvelle',
            'tag' => 'COWORKING · 100 SQM',
            'text' => "Facing Granvelle park, on a quiet street in the heart of the city loop. Private offices or dedicated open-space desks, key lockers, a bar work table and a sofa corner. A cocooning place that makes work feel good.",
            'cta' => 'See the Granvelle desks',
            'access' => ['Facing Granvelle park', 'Bus and tram', 'Mairie car park', 'Chamars car park', 'Centre of the loop'],
            'amenities' => [
                ['title' => 'Dedicated desk', 'text' => "Your own desk in the open space, with a key locker."],
                ['title' => 'Fibre wifi', 'text' => "High-speed throughout the space."],
                ['title' => 'Furniture', 'text' => "Desk, ergonomic chair and personal storage."],
                ['title' => 'Kitchen', 'text' => "Coffee machine, fridge, microwave, toilets."],
                ['title' => 'Business / lounge', 'text' => "Bar work table, sofa and armchairs for your meetings."],
                ['title' => 'And all the rest', 'text' => "Bills, water, electricity, heating and cleaning included."],
            ],
        ],
    ],
]);

// ------------------------------------------------------------- Nos bureaux

$put('pages/offices.fr.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'offices', 'lang' => 'fr', 'status' => 'published', 'nav' => 'Nos bureaux',
    'seo' => [
        'title' => 'Nos bureaux disponibles à Besançon',
        'description' => "Bureaux privés et postes en open space à louer à Besançon. Tarifs HT par mois, tout compris. Disponibilités mises à jour en temps réel.",
        'ogImage' => '/media/espace-carnot.webp',
    ],
    'kicker' => 'NOS BUREAUX',
    'title' => 'Choisissez votre bureau',
    'text' => "Tarifs HT par mois, tout compris. Disponibilités mises à jour depuis le back-office : ce que vous voyez est réel.",
    'included' => ['Charges et fluides', 'Ménage', 'Fibre très haut débit', 'Mobilier moderne', 'Salle de réunion', 'Cuisine équipée', 'Bonne humeur', 'Accès badge 24h/24'],
]);

$put('pages/offices.en.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'offices', 'lang' => 'en', 'status' => 'published', 'nav' => 'Offices',
    'seo' => [
        'title' => 'Our available offices in Besançon',
        'description' => "Private offices and dedicated desks to rent in Besançon. Monthly prices excl. VAT, all inclusive. Availability updated in real time.",
        'ogImage' => '/media/espace-carnot.webp',
    ],
    'kicker' => 'OUR OFFICES',
    'title' => 'Pick your office',
    'text' => "Monthly prices excl. VAT, all inclusive. Availability is updated from the back office: what you see is real.",
    'included' => ['Bills and utilities', 'Cleaning', 'High-speed fibre', 'Modern furniture', 'Meeting room', 'Fitted kitchen', 'Good vibes', '24/7 badge access'],
]);

// ------------------------------------------------------------------ L'actu

$put('pages/news.fr.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'news', 'lang' => 'fr', 'status' => 'published', 'nav' => "L'actu",
    'seo' => ['title' => "L'actu du iOiO", 'description' => "Les nouvelles des espaces Carnot et Granvelle : libérations, aménagements, vie du lieu.", 'ogImage' => '/media/p2.jpg'],
    'kicker' => "L'ACTU DU iOiO",
    'title' => 'Ce qui se passe ici',
]);
$put('pages/news.en.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'news', 'lang' => 'en', 'status' => 'published', 'nav' => 'News',
    'seo' => ['title' => 'iOiO news', 'description' => "News from the Carnot and Granvelle spaces: desks freeing up, refits, life on site.", 'ogImage' => '/media/p2.jpg'],
    'kicker' => 'iOiO NEWS',
    'title' => 'What is going on here',
]);

// ----------------------------------------------------------------- Contact

$put('pages/contact.fr.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'contact', 'lang' => 'fr', 'status' => 'published', 'nav' => 'Contact',
    'seo' => ['title' => 'Contact et visite', 'description' => "Réservez une visite de 20 minutes au iOiO Carnot ou Granvelle, café compris.", 'ogImage' => '/media/p4.jpg'],
    'kicker' => 'CONTACT',
    'title' => 'On se rencontre ?',
    'text' => "Une visite dure 20 minutes, café compris. Dites-nous ce dont vous avez besoin, on vous propose deux créneaux.",
    'needs' => ['Un bureau privé', 'Un poste en open space', 'Juste une visite'],
]);
$put('pages/contact.en.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'contact', 'lang' => 'en', 'status' => 'published', 'nav' => 'Contact',
    'seo' => ['title' => 'Contact and viewings', 'description' => "Book a 20-minute viewing at iOiO Carnot or Granvelle, coffee included.", 'ogImage' => '/media/p4.jpg'],
    'kicker' => 'CONTACT',
    'title' => 'Shall we meet?',
    'text' => "A viewing takes 20 minutes, coffee included. Tell us what you need and we offer two slots.",
    'needs' => ['A private office', 'A dedicated desk', 'Just a viewing'],
]);

// -------------------------------------------------------- Mentions légales

$legalBlocks = [
    ['title' => 'Éditeur du site', 'text' => "Le iOiO — [forme juridique] au capital de [montant] €\n[Adresse du siège], 25000 Besançon\nSIRET [numéro] — RCS Besançon [numéro] — TVA intracommunautaire [numéro]\nResponsable de la publication : [nom]\nContact : [email] — [téléphone]"],
    ['title' => 'Hébergement', 'text' => "Site hébergé par [hébergeur], [adresse], [téléphone].\nHébergement mutualisé PHP 8.x, données stockées en fichiers JSON sur le serveur, aucune base de données."],
    ['title' => 'Propriété intellectuelle', 'text' => "La marque iOiO, le logo « votre bureau au bout du fil », les textes et les photographies des espaces Carnot et Granvelle sont la propriété du iOiO. Toute reproduction, même partielle, est interdite sans accord écrit."],
    ['title' => 'Données personnelles (RGPD)', 'text' => "Les formulaires de contact, de réservation et de rappel de disponibilités collectent nom, email, téléphone et message dans le seul but de répondre à la demande. Les données sont conservées 24 mois, ne sont ni vendues ni transmises à des tiers, et sont stockées en fichiers JSON sur le serveur. Droit d'accès, de rectification et de suppression : [email]."],
    ['title' => 'Assistant IA', 'text' => "L'assistant du site s'appuie sur l'API Gemini. Les questions posées sont envoyées à Google pour génération de la réponse ; aucune donnée de compte n'est transmise. L'assistant ne répond qu'à partir des pages du site et des documents publiés au back-office : ses réponses n'ont pas de valeur contractuelle."],
    ['title' => 'Cookies et traduction', 'text' => "Le site n'utilise que des cookies techniques (langue choisie, fermeture de la fenêtre de rappel). La traduction des contenus longs est assurée par Google Traduction ; les pages clés sont traduites manuellement."],
    ['title' => 'Avis clients', 'text' => "Les avis affichés sont issus de la fiche Google Business Profile du iOiO et récupérés via l'API Google Places. Ils ne sont ni filtrés ni modifiés."],
];
$put('pages/legal.fr.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'legal', 'lang' => 'fr', 'status' => 'published', 'nav' => 'Mentions légales',
    'seo' => ['title' => 'Mentions légales', 'description' => "Mentions légales du site du iOiO, coworking à Besançon.", 'noindex' => true],
    'kicker' => 'INFORMATIONS LÉGALES',
    'title' => 'Mentions légales',
    'text' => "Les champs entre crochets sont à compléter au back-office avant mise en production.",
    'blocks' => $legalBlocks,
]);
$put('pages/legal.en.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'legal', 'lang' => 'en', 'status' => 'published', 'nav' => 'Legal notice',
    'seo' => ['title' => 'Legal notice', 'description' => "Legal notice for the iOiO website, coworking in Besançon.", 'noindex' => true],
    'kicker' => 'LEGAL INFORMATION',
    'title' => 'Legal notice',
    'text' => "Fields in brackets must be completed in the back office before going live.",
    'blocks' => $legalBlocks,
]);

$privacyBlocks = [
    ['title' => 'Responsable de traitement', 'text' => "Le iOiO — [forme juridique], [adresse du siège], 25000 Besançon.\nContact : [email]."],
    ['title' => 'Données collectées', 'text' => "Formulaire de contact : nom, email, téléphone, besoin, message.\nFormulaire de réservation : nom, email, téléphone, date d'entrée souhaitée, bureau concerné.\nFenêtre de rappel des disponibilités : email uniquement.\nAssistant IA : la question posée, sans donnée de compte."],
    ['title' => 'Finalités et base légale', 'text' => "Les données servent uniquement à répondre à votre demande et à organiser une visite (mesure précontractuelle et intérêt légitime). Aucune prospection commerciale automatisée, aucune revente."],
    ['title' => 'Durée de conservation', 'text' => "24 mois à compter du dernier échange, puis suppression automatique du fichier de demandes."],
    ['title' => 'Destinataires et sous-traitants', 'text' => "Les demandes restent sur le serveur d'hébergement, en fichiers JSON, hors racine web. Les questions posées à l'assistant sont transmises à Google (API Gemini) pour générer la réponse. Les avis affichés proviennent de l'API Google Places."],
    ['title' => 'Vos droits', 'text' => "Accès, rectification, suppression, limitation et opposition : écrivez à [email]. Réponse sous un mois. Réclamation possible auprès de la CNIL."],
    ['title' => 'Cookies', 'text' => "Cookies techniques uniquement : langue choisie (12 mois), cookie de session anti-spam posé par les formulaires du site et l'assistant (durée de la visite), session du back-office, mémorisation de la fermeture de la fenêtre de rappel (durée de la session). Aucun cookie publicitaire, aucune mesure d'audience nominative."],
];
$put('pages/privacy.fr.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'privacy', 'lang' => 'fr', 'status' => 'published', 'nav' => 'Politique de confidentialité',
    'seo' => ['title' => 'Politique de confidentialité', 'description' => "Traitement des données personnelles sur le site du iOiO.", 'noindex' => true],
    'kicker' => 'DONNÉES PERSONNELLES',
    'title' => 'Politique de confidentialité',
    'text' => "Ce que nous collectons, pourquoi, combien de temps, et comment y accéder.",
    'blocks' => $privacyBlocks,
]);
$put('pages/privacy.en.json', [
    '_schema' => Config::SCHEMA,
    'slug' => 'privacy', 'lang' => 'en', 'status' => 'published', 'nav' => 'Privacy policy',
    'seo' => ['title' => 'Privacy policy', 'description' => "How personal data is handled on the iOiO website.", 'noindex' => true],
    'kicker' => 'PERSONAL DATA',
    'title' => 'Privacy policy',
    'text' => "What we collect, why, for how long, and how to access it.",
    'blocks' => $privacyBlocks,
]);

// -------------------------------------------------------------- Actualités

$put('posts.json', [
    '_schema' => Config::SCHEMA,
    'posts' => [
        [
            'slug' => 'carnot-03-se-libere',
            'status' => 'published',
            'date' => '2026-09-02',
            'title' => 'Carnot 03 se libère : 11 m² au calme',
            'excerpt' => "Le bureau 03 de l'espace Carnot est disponible immédiatement. Cour intérieure, fibre filaire, salle de réunion à deux portes.",
            'body' => "<p>Le bureau 03 de l'espace Carnot vient de se libérer. 11 m² fermés, une fenêtre sur la cour intérieure, le mobilier neuf déjà installé.</p><p>Le loyer reste à <strong>320 € HT par mois, tout compris</strong> : charges, ménage, fibre filaire, salle de réunion et café. L'entrée peut se faire sous 48 h après la visite.</p><p>Comme toujours, la visite dure 20 minutes et le café est offert.</p>",
            'image' => '/media/p3.jpg',
            'color' => '#FFD100',
            'i18n' => ['en' => [
                'title' => 'Carnot 03 is free: 11 sqm, nice and quiet',
                'excerpt' => "Office 03 in the Carnot space is available right now. Inner courtyard, wired fibre, meeting room two doors away.",
                'body' => "<p>Office 03 in the Carnot space has just been freed up. 11 closed sqm, a window onto the inner courtyard, new furniture already in place.</p><p>The rent stays at <strong>€320 excl. VAT per month, all inclusive</strong>: bills, cleaning, wired fibre, meeting room and coffee. You can move in within 48 h of the viewing.</p><p>As always, the viewing takes 20 minutes and the coffee is on us.</p>",
            ]],
        ],
        [
            'slug' => 'casiers-coin-bar-granvelle',
            'status' => 'published',
            'date' => '2026-08-18',
            'title' => 'Nouveaux casiers et coin bar à Granvelle',
            'excerpt' => "L'espace détente de Granvelle a été réaménagé cet été : table bar plus grande, nouveaux casiers à clé et machine à café remplacée.",
            'body' => "<p>L'été a servi à refaire l'espace détente de Granvelle. La table bar de travail est plus grande, avec des prises à chaque place.</p><p>Chaque poste dédié dispose désormais de son <strong>casier à clé</strong>, et la machine à café a été remplacée par un modèle à grains.</p><p>Le coin canapé reste réservé aux rendez-vous informels : on y reçoit un client sans réserver la salle de réunion.</p>",
            'image' => '/media/p6.jpg',
            'color' => '#12B39A',
            'i18n' => ['en' => [
                'title' => 'New lockers and bar corner at Granvelle',
                'excerpt' => "The Granvelle lounge was refitted this summer: a bigger bar table, new key lockers and a new coffee machine.",
                'body' => "<p>The summer was spent refitting the Granvelle lounge. The bar work table is bigger, with power sockets at every seat.</p><p>Every dedicated desk now comes with its own <strong>key locker</strong>, and the coffee machine has been replaced with a bean-to-cup model.</p><p>The sofa corner stays free for informal meetings: you can welcome a client without booking the meeting room.</p>",
            ]],
        ],
        [
            'slug' => 'loyers-tout-compris',
            'status' => 'published',
            'date' => '2026-07-04',
            'title' => 'Pourquoi nos loyers restent tout compris',
            'excerpt' => "Pas de refacturation de charges, pas de frais de dossier, pas de surprise en fin d'année. On explique le modèle en trois minutes.",
            'body' => "<p>Un bureau à 300 € qui devient 380 € une fois les charges refacturées, ce n'est pas notre modèle. Au iOiO, le prix affiché est le prix payé.</p><p>Sont compris : charges, eau, électricité, chauffage, ménage, fibre, mobilier, salle de réunion, cuisine équipée et café. Il n'y a <strong>ni frais de dossier, ni dépôt de garantie lourd, ni régularisation annuelle</strong>.</p><p>Le contrat est une prestation de services, pas un bail commercial : le préavis est court et vous partez quand votre activité change.</p>",
            'image' => '/media/p7.jpg',
            'color' => '#FFD100',
            'i18n' => ['en' => [
                'title' => 'Why our rents stay all-inclusive',
                'excerpt' => "No rebilled charges, no admin fees, no year-end surprise. Here is the model in three minutes.",
                'body' => "<p>An office at €300 that becomes €380 once charges are rebilled is not our model. At the iOiO, the price shown is the price paid.</p><p>Included: bills, water, electricity, heating, cleaning, fibre, furniture, meeting room, fitted kitchen and coffee. There are <strong>no admin fees, no heavy deposit and no annual adjustment</strong>.</p><p>The contract is a service agreement, not a commercial lease: notice is short and you leave when your business changes.</p>",
            ]],
        ],
    ],
]);

// ------------------------------------------------------------------- Avis

$put('reviews.json', [
    '_schema' => Config::SCHEMA,
    'rating' => 5.0,
    'count' => 10,
    'url' => '',
    'reviews' => [
        ['name' => 'Amandine V.', 'initials' => 'AV', 'rating' => 5, 'enabled' => true, 'at' => '2026-08-20', 'text' => "Un espace de coworking moderne, chaleureux et idéalement situé. On s'y sent vraiment comme à la maison !"],
        ['name' => 'Laurent B.', 'initials' => 'LB', 'rating' => 5, 'enabled' => true, 'at' => '2026-07-12', 'text' => "Situé au centre ville, très pratique, un coworking sympa et convivial... que je conseille !"],
        ['name' => 'Gwendoline D.', 'initials' => 'GD', 'rating' => 5, 'enabled' => true, 'at' => '2026-06-28', 'text' => "Un coworking où je me sens à l'aise pour travailler. Bien situé, agréable, avec une ambiance qui donne envie de revenir."],
        ['name' => 'Nikita S.', 'initials' => 'NS', 'rating' => 5, 'enabled' => true, 'at' => '2026-05-15', 'text' => "Super endroit pour bosser en plein centre de Besançon. Calme, lumineux et très agréable."],
    ],
]);

$put('requests.json', ['_schema' => Config::SCHEMA, 'requests' => []]);

echo "\nContenus de départ en place.\n";
