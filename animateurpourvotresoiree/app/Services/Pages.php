<?php
declare(strict_types=1);

namespace App\Services;

/** Pages institutionnelles éditables (mentions légales, CGU, confidentialité, FAQ…). */
final class Pages
{
    public const RESERVED = ['mentions-legales', 'cgu', 'confidentialite', 'charte-qualite', 'faq', 'qui-sommes-nous', 'cookies'];

    public static function bySlug(string $slug): ?array
    {
        foreach (Store::pages()->iterate() as $row) {
            if ($row['slug'] === $slug && $row['status'] === 'published') {
                return Store::pages()->get((int) $row['id']);
            }
        }
        return null;
    }

    public static function seedIfEmpty(): int
    {
        $col = Store::pages();
        $existing = [];
        foreach ($col->iterate() as $row) {
            $existing[$row['slug']] = true;
        }
        $n = 0;
        foreach (self::defaults() as $p) {
            if (!isset($existing[$p['slug']])) {
                $col->insert($p + ['status' => 'published', 'seo' => ['title' => '', 'description' => '']]);
                $n++;
            }
        }
        return $n;
    }

    /**
     * Formulaire d'avis simplifié (une note et un texte, sans email) : les CGU et la FAQ installées avec l'ancien texte
     * par défaut sont mises à jour ; une page déjà réécrite à la main n'est pas touchée.
     */
    public static function upgradeReviewTexts(): int
    {
        $map = [
            'Les avis sont vérifiés par email puis modérés.' => 'Les avis sont relus avant publication (un seul avis par client et par professionnel).',
            'Chaque avis est confirmé par email puis relu avant publication.' => 'Chaque avis est relu avant publication, et un client ne peut noter un professionnel qu\'une fois.',
        ];
        $n = 0;
        foreach (iterator_to_array(Store::pages()->iterate()) as $id => $row) {
            if (!in_array($row['slug'], ['cgu', 'faq'], true)) {
                continue;
            }
            $page = Store::pages()->get((int) $id);
            $body = (string) ($page['body'] ?? '');
            $new = strtr($body, $map);
            if ($page && $new !== $body) {
                Store::pages()->update((int) $id, ['body' => $new]);
                $n++;
            }
        }
        return $n;
    }

    public static function defaults(): array
    {
        $s = static fn (string $k) => e((string) Settings::get('site.' . $k));
        $site = e(Settings::siteName());
        $mail = e((string) \App\Core\Env::get('CONTACT_EMAIL', 'contact@animateurpourvotresoiree.com'));
        return [
            [
                'slug' => 'mentions-legales',
                'title' => 'Mentions légales',
                'body' => "<h2>Éditeur du site</h2><p><strong>{$site}</strong> est un service édité par la société <strong>{$s('company')}</strong>.<br>SIRET : {$s('siret')} — {$s('rcs')}<br>Siège : {$s('address')}<br>Directeur de la publication : {$s('director')}<br>Contact : <a href=\"mailto:{$mail}\">{$mail}</a></p>"
                    . "<h2>Hébergement</h2><p>{$s('host')}</p>"
                    . "<h2>Propriété intellectuelle</h2><p>La structure du site, ses textes, son logo et sa charte graphique sont la propriété de l'éditeur. Les contenus publiés par les professionnels (textes, photos, vidéos) restent la propriété de leurs auteurs, qui garantissent en détenir les droits.</p>"
                    . "<h2>Responsabilité</h2><p>{$site} est un annuaire : il met en relation des particuliers et des entreprises avec des professionnels de l'animation et de l'événementiel. L'éditeur n'est pas partie aux contrats conclus entre clients et prestataires et ne perçoit aucune commission. Il ne saurait être tenu responsable de l'exécution des prestations, des prix pratiqués ni des déclarations des professionnels.</p>"
                    . "<h2>Données personnelles</h2><p>Voir notre <a href=\"/confidentialite/\">politique de confidentialité</a>.</p>",
            ],
            [
                'slug' => 'cgu',
                'title' => "Conditions générales d'utilisation",
                'body' => "<h2>1. Objet</h2><p>Les présentes conditions encadrent l'utilisation du site {$site} par les visiteurs et par les professionnels inscrits.</p>"
                    . "<h2>2. Service gratuit</h2><p>La consultation de l'annuaire, le dépôt de demandes de devis et l'inscription des professionnels sont gratuits. Le site est financé par la publicité.</p>"
                    . "<h2>3. Demandes de devis et messages</h2><p>Les demandes déposées par les visiteurs sont contrôlées (filtrage automatique et, si besoin, vérification manuelle) avant leur transmission aux professionnels concernés. Le visiteur accepte que ses coordonnées soient communiquées aux professionnels sélectionnés afin qu'ils puissent lui répondre.</p>"
                    . "<h2>4. Obligations des professionnels</h2><p>Le professionnel s'engage à exercer légalement son activité (statut déclaré, assurances, déclarations sociales et fiscales), à publier des informations exactes, à répondre loyalement aux demandes reçues et à respecter la <a href=\"/charte-qualite/\">charte qualité</a>. L'éditeur peut suspendre ou supprimer toute fiche ne respectant pas ces règles.</p>"
                    . "<h2>5. Avis clients</h2><p>Les avis sont relus avant publication (un seul avis par client et par professionnel). Ils doivent relater une expérience réelle, sans propos injurieux ni données personnelles. Le professionnel dispose d'un droit de réponse public.</p>"
                    . "<h2>6. Responsabilité</h2><p>{$site} n'intervient pas dans la relation contractuelle entre le client et le professionnel et ne peut être tenu responsable des prestations réalisées, de leur prix ou de leur annulation.</p>"
                    . "<h2>7. Droit applicable</h2><p>Les présentes conditions sont soumises au droit français.</p>",
            ],
            [
                'slug' => 'confidentialite',
                'title' => 'Politique de confidentialité',
                'body' => "<p>Le responsable du traitement est la société {$s('company')}, éditrice de {$site}. Contact : <a href=\"mailto:{$mail}\">{$mail}</a>.</p>"
                    . "<h2>Données collectées</h2><ul><li><strong>Demandes de devis et messages</strong> : nom, email, téléphone, ville et détails de l'événement, transmis aux professionnels concernés pour qu'ils vous répondent.</li><li><strong>Comptes professionnels</strong> : identité, coordonnées, informations publiées sur la fiche.</li><li><strong>Avis</strong> : nom affiché et email (non publié) pour la vérification.</li><li><strong>Statistiques</strong> : mesures d'audience anonymisées (adresse IP tronquée et chiffrée).</li></ul>"
                    . "<h2>Bases légales et durées</h2><p>Exécution du service demandé (mise en relation), intérêt légitime (sécurité, lutte contre le spam), consentement (cookies publicitaires). Les demandes et messages sont conservés tant qu'ils sont utiles à la relation, et au plus 3 ans après le dernier contact pour les prospects, sauf obligation légale contraire.</p>"
                    . "<h2>Publicité et cookies</h2><p>Le site affiche des annonces Google AdSense. Google et ses partenaires peuvent utiliser des cookies pour diffuser des annonces personnalisées, uniquement avec votre consentement recueilli via la fenêtre de choix affichée lors de votre première visite. Vous pouvez modifier vos choix à tout moment via le lien « Gérer les cookies » en bas de page.</p>"
                    . "<h2>Vos droits</h2><p>Vous disposez d'un droit d'accès, de rectification, d'effacement, d'opposition, de limitation et de portabilité. Les professionnels peuvent exporter ou supprimer leurs données depuis leur espace. Pour toute demande : <a href=\"mailto:{$mail}\">{$mail}</a>. Vous pouvez saisir la CNIL (cnil.fr).</p>",
            ],
            [
                'slug' => 'charte-qualite',
                'title' => 'Charte qualité des professionnels',
                'body' => "<p>Pour que chaque fête soit réussie, les professionnels référencés s'engagent à :</p><ul><li>exercer leur activité de manière déclarée et assurée (SIREN, GUSO ou statut adapté) ;</li><li>présenter honnêtement leurs prestations, tarifs, matériel et photos ;</li><li>répondre rapidement à chaque demande reçue, même pour la décliner ;</li><li>établir un devis ou un contrat clair avant toute prestation ;</li><li>respecter les clients, les lieux, les horaires et la législation (droits d'auteur, niveau sonore, sécurité) ;</li><li>ne jamais utiliser les coordonnées des clients à d'autres fins que leur demande.</li></ul><p>Le non-respect de la charte peut entraîner la suspension de la fiche.</p>",
            ],
            [
                'slug' => 'faq',
                'title' => 'Questions fréquentes',
                'body' => "<h2>Le service est-il gratuit ?</h2><p>Oui : la recherche, les demandes de devis et l'inscription des professionnels sont gratuites. Aucune commission n'est prélevée sur les prestations.</p>"
                    . "<h2>Comment fonctionne la demande de devis ?</h2><p>Vous décrivez votre événement en une minute. Votre demande est vérifiée puis transmise aux professionnels du secteur correspondant, qui vous contactent directement.</p>"
                    . "<h2>Les avis sont-ils fiables ?</h2><p>Chaque avis est relu avant publication, et un client ne peut noter un professionnel qu'une fois. Les avis « client vérifié » proviennent d'une invitation envoyée par le professionnel à son client.</p>"
                    . "<h2>Je suis un professionnel, comment apparaître dans l'annuaire ?</h2><p>Créez votre fiche gratuitement depuis la page <a href=\"/inscription-pro/\">Inscription pro</a>. Elle est publiée après une rapide vérification.</p>"
                    . "<h2>J'avais un compte sur l'ancien site, que devient-il ?</h2><p>Votre fiche a été conservée. Connectez-vous avec votre identifiant ou votre email et votre mot de passe habituel ; vous pourrez ensuite le changer.</p>",
            ],
            [
                'slug' => 'qui-sommes-nous',
                'title' => 'Qui sommes-nous ?',
                'body' => "<p>Depuis 2001, {$site} référence les professionnels de l'animation et de l'événementiel partout en France : DJ, orchestres, magiciens, animateurs pour enfants, photobooths, humoristes, casino, sonorisation…</p><p>Notre mission est simple : aider chacun à trouver le bon pro pour sa fête, et aider les pros à remplir leur agenda. Sans commission, sans intermédiaire.</p>",
            ],
        ];
    }
}
