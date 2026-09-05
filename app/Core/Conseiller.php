<?php
declare(strict_types=1);

namespace App\Core;

use App\Admin\Contenus;
use RuntimeException;

/**
 * Conseiller du back-office : un regard extérieur sur le site, à la demande.
 *
 * Il ne s'adresse pas aux administrés mais au secrétariat, et c'est toute la
 * différence avec App\Core\Assistant. L'assistant public est enfermé dans le
 * contenu du site et n'a le droit de rien inventer ; le conseiller, lui, est
 * là pour dire ce qui manque — donc pour parler de ce qui n'est pas dans le
 * site. Les deux consignes sont opposées, et c'est pourquoi ce sont deux
 * classes et non un réglage.
 *
 * **Ce qu'il lit.** Le contenu, les réglages de référencement, les comptages
 * de fréquentation, les défauts déjà relevés par le tableau de bord, et les
 * questions posées à l'assistant public. Cette dernière source est la plus
 * utile — elle dit ce que les administrés cherchent et ne trouvent pas — et
 * la seule qui demande une précaution : voir `questionsPosees()`.
 *
 * **Ce qu'il ne fait pas.** Il n'écrit rien. Il propose des textes, que la
 * mairie pose dans un champ d'un clic et enregistre elle-même. Un modèle de
 * langage invente des pièces à fournir avec beaucoup d'aplomb, et ce dépôt
 * s'interdit de publier une règle administrative non vérifiée : le dernier
 * geste reste humain.
 */
final class Conseiller
{
    /** Le bilan est plus long qu'une réponse de conversation. */
    private const JETONS_BILAN = 4000;

    /** Garde-fous sur ce qu'un administrateur peut envoyer. */
    public const QUESTION_MAX = 1200;
    private const HISTORIQUE_MAX = 10;

    /** Au-delà, le contenu du site est coupé : le reste n'apprend plus rien. */
    private const CONTENU_MAX = 120000;

    /** Combien de mois de questions de visiteurs on relit. */
    private const MOIS_RELUS = 6;

    /** Et combien de questions distinctes on retient. */
    private const QUESTIONS_MAX = 150;

    public function __construct(
        private readonly Assistant $assistant,
        private readonly Parametres $parametres,
        private readonly Content $content,
        private readonly Seo $seo,
        private readonly Frequentation $frequentation,
        private readonly Conversations $conversations,
        private readonly string $dossierDonnees,
    ) {
    }

    // ------------------------------------------------------------ disponibilité

    /**
     * Le conseiller est-il allumé ?
     *
     * Deux conditions, et l'interrupteur est bien distinct de celui de
     * l'assistant public : une mairie veut souvent le conseiller d'abord,
     * pour préparer son site, sans exposer encore un robot aux visiteurs.
     * La clé, elle, est la même — en demander deux pour le même compte Google
     * serait une formalité sans contrepartie.
     */
    public function actif(): bool
    {
        return (bool) $this->parametres->get('assistant.conseiller', false)
            && $this->assistant->cle() !== '';
    }

    /**
     * La connexion du conseiller : la clé de l'assistant, son modèle à lui.
     *
     * Une seule clé à renseigner — c'est le même compte Google —, mais deux
     * modèles : le bilan relit cent vingt mille caractères et gagne à un
     * modèle plus capable, quand l'assistant public répond en trois phrases et
     * doit rester rapide. Vide, le réglage suit celui de l'assistant : la
     * mairie qui ne veut pas s'en occuper n'a rien à faire.
     */
    public function connexion(): Connexion
    {
        $modele = trim((string) $this->parametres->get('assistant.conseiller_modele', ''));

        return new Connexion($this->assistant->cle(), $modele !== '' ? $modele : $this->assistant->modele());
    }

    /** Pourquoi il ne s'affiche pas, quand il ne s'affiche pas. */
    public function motifAbsence(): string
    {
        if ($this->assistant->cle() === '') {
            return 'Aucune clé Gemini n’est enregistrée.';
        }
        if (!(bool) $this->parametres->get('assistant.conseiller', false)) {
            return 'Le conseiller est désactivé.';
        }

        return '';
    }

    // ----------------------------------------------------------------- échanges

    /**
     * Répond à une question du secrétariat.
     *
     * @param array<int, array{role: string, texte: string}> $historique
     */
    public function repondre(string $question, array $historique = []): string
    {
        $question = mb_substr(trim($question), 0, self::QUESTION_MAX);
        if ($question === '') {
            throw new RuntimeException('La question est vide.');
        }
        if (!$this->actif()) {
            throw new RuntimeException('Le conseiller n’est pas activé.');
        }

        return $this->assistant->generer(
            $this->consigne(),
            array_merge($this->amorce(), $this->tours($historique), [
                ['role' => 'user', 'parts' => [['text' => $question]]],
            ]),
            ['temperature' => 0.4, 'maxOutputTokens' => 1400],
            $this->connexion()
        );
    }

    /**
     * Une revue complète du site, rendue sous forme de recommandations
     * classées.
     *
     * Lancée à la demande, jamais par une tâche planifiée : une mairie ne
     * doit pas découvrir une facture Google pour des bilans que personne n'a
     * lus.
     *
     * @return array{date: int, recommandations: array<int, array<string, mixed>>}
     */
    public function bilan(): array
    {
        if (!$this->actif()) {
            throw new RuntimeException('Le conseiller n’est pas activé.');
        }

        $demande = <<<'TEXTE'
        Fais la revue complète de ce site et rends une liste de recommandations.

        Classe-les de la plus utile à la moins utile pour une commune de cette
        taille. Une recommandation qui demande un travail de dix minutes et
        rapporte beaucoup passe devant une refonte.

        Ne répète pas les défauts déjà listés sous « DÉFAUTS DÉJÀ MESURÉS » :
        ils sont connus et suivis ailleurs. Ne parle ni d'accessibilité
        technique, ni de contraste, ni de vitesse d'affichage : ces points sont
        mesurés automatiquement à chaque modification et sont à zéro écart.

        Concentre-toi sur ce qu'aucune machine ne sait juger : ce qui manque au
        contenu, ce qui est écrit d'une façon que personne ne comprend, les
        sujets attendus et absents, les pages que personne ne lit et pourquoi,
        les questions que les administrés posent sans trouver de réponse, et la
        façon dont la commune se présente.
        TEXTE;

        $schema = [
            'type'  => 'ARRAY',
            'items' => [
                'type'       => 'OBJECT',
                'properties' => [
                    'titre'    => ['type' => 'STRING'],
                    'urgence'  => ['type' => 'STRING', 'enum' => ['forte', 'moyenne', 'faible']],
                    'domaine'  => ['type' => 'STRING', 'enum' => ['contenu', 'referencement', 'strategie', 'organisation']],
                    'constat'  => ['type' => 'STRING'],
                    'geste'    => ['type' => 'STRING'],
                    'ecran'    => ['type' => 'STRING'],
                ],
                'required' => ['titre', 'urgence', 'domaine', 'constat', 'geste'],
            ],
        ];

        $brut = $this->assistant->generer(
            $this->consigne(),
            array_merge($this->amorce(), [
                ['role' => 'user', 'parts' => [['text' => $demande]]],
            ]),
            [
                'temperature'      => 0.3,
                'maxOutputTokens'  => self::JETONS_BILAN,
                'responseMimeType' => 'application/json',
                'responseSchema'   => $schema,
            ],
            $this->connexion()
        );

        $lu = json_decode($brut, true);
        if (!is_array($lu)) {
            throw new RuntimeException('Le bilan est revenu illisible. Réessayez dans un instant.');
        }

        $bilan = ['date' => time(), 'recommandations' => array_values(array_filter(
            array_map([self::class, 'recommandationValide'], $lu)
        ))];

        $this->enregistrerBilan($bilan);

        return $bilan;
    }

    /**
     * Le modèle rend ce qu'il veut : on ne garde que la forme attendue.
     *
     * @return array<string, string>|null
     */
    private static function recommandationValide(mixed $ligne): ?array
    {
        if (!is_array($ligne) || trim((string) ($ligne['titre'] ?? '')) === '') {
            return null;
        }
        $urgence = (string) ($ligne['urgence'] ?? 'moyenne');
        $domaine = (string) ($ligne['domaine'] ?? 'contenu');

        return [
            'titre'   => mb_substr(trim((string) $ligne['titre']), 0, 160),
            'urgence' => in_array($urgence, ['forte', 'moyenne', 'faible'], true) ? $urgence : 'moyenne',
            'domaine' => in_array($domaine, ['contenu', 'referencement', 'strategie', 'organisation'], true)
                ? $domaine : 'contenu',
            'constat' => mb_substr(trim((string) ($ligne['constat'] ?? '')), 0, 900),
            'geste'   => mb_substr(trim((string) ($ligne['geste'] ?? '')), 0, 900),
            // Une adresse d'écran, ou rien : jamais un lien vers le dehors.
            'ecran'   => self::ecranValide((string) ($ligne['ecran'] ?? '')),
        ];
    }

    /** Un chemin du back-office, ou une chaîne vide. */
    private static function ecranValide(string $valeur): string
    {
        $chemin = (string) (parse_url(trim($valeur), PHP_URL_PATH) ?: '');

        return preg_match('#^/admin[a-z0-9/_-]*$#i', $chemin) === 1 ? $chemin : '';
    }

    // ------------------------------------------------------------------- bilan

    private function fichierBilan(): string
    {
        return $this->dossierDonnees . '/conseiller/bilan.json';
    }

    /** @return array{date: int, recommandations: array<int, array<string, mixed>>}|null */
    public function dernierBilan(): ?array
    {
        $f = $this->fichierBilan();
        if (!is_file($f)) {
            return null;
        }
        $lu = json_decode((string) file_get_contents($f), true);

        return is_array($lu) && isset($lu['recommandations']) ? $lu : null;
    }

    /** @param array<string, mixed> $bilan */
    private function enregistrerBilan(array $bilan): void
    {
        $dossier = dirname($this->fichierBilan());
        if (!is_dir($dossier) && !@mkdir($dossier, Permissions::DOSSIER, true) && !is_dir($dossier)) {
            return;
        }

        // Écriture atomique, comme tout le reste du socle.
        $tmp = $this->fichierBilan() . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $json = json_encode($bilan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $this->fichierBilan())) {
            @unlink($tmp);
            return;
        }
        @chmod($this->fichierBilan(), Permissions::FICHIER);
    }

    // ---------------------------------------------------------------- consigne

    /** Ce que le conseiller est, et ce qu'il n'a pas le droit de faire. */
    private function consigne(): string
    {
        $nom = (string) $this->content->get('site', 'nom', 'la commune');

        return implode("\n", [
            "Tu conseilles le secrétariat de la mairie de $nom sur son site internet. Tu t’adresses à un agent, pas à un administré : tu peux être direct, technique quand il le faut, et dire ce qui ne va pas.",
            '',
            'TON RÔLE : dire ce qui manque, ce qui est mal dit, ce qui ne sert à personne, et par quoi commencer. Le site t’est donné en entier ci-après, avec ses chiffres de fréquentation et les questions que les administrés posent.',
            '',
            'CE QUI FAIT UN BON CONSEIL ICI :',
            '· Il est chiffré ou situé. « La page Urbanisme n’a reçu que 4 visites en trois mois alors que c’est la deuxième démarche du village » vaut cent fois « améliorez votre référencement ».',
            '· Il tient compte de la taille de la commune. Sept cents habitants, un secrétariat ouvert trois jours par semaine : une recommandation qui demande un poste de communicant est une recommandation inutile.',
            '· Il donne le geste exact, et l’écran où le faire. Pas « retravaillez vos métadonnées » mais « Référencement → page Démarches → remplacer la description par : … ».',
            '',
            'CE QUE TU NE FAIS JAMAIS :',
            '· Tu n’inventes AUCUNE règle administrative. Ni pièce à fournir, ni délai, ni seuil, ni tarif. Si un contenu te paraît faux ou périmé, dis de le vérifier auprès de service-public.fr — ne le corrige pas toi-même. C’est la règle la plus importante de ce site : une fiche périmée envoie un administré au guichet avec le mauvais dossier.',
            '· Tu n’affirmes pas qu’un service existe. Une commune de cette taille ne délivre ni carte d’identité ni passeport si elle n’a pas de dispositif de recueil, et les compétences déchets, eau ou école appartiennent souvent à l’intercommunalité.',
            '· Tu ne recommandes rien qui dépose un cookie ou appelle un service tiers sans consentement : le site est celui d’une administration.',
            '· Tu ne parles pas de contraste, d’accessibilité technique, de balises manquantes ni de vitesse d’affichage : tout cela est mesuré automatiquement à chaque modification et se trouve à zéro écart. Le redire ferait perdre du temps.',
            '',
            'PROPOSER UN TEXTE. Quand ta réponse contient un texte que la mairie va recopier tel quel dans un champ — un titre de page, une description de référencement, un paragraphe, une phrase d’accroche —, écris-le dans un bloc de cette forme exacte :',
            '```proposition',
            'le texte, mot pour mot, tel qu’il doit apparaître sur le site',
            '```',
            'Ce bloc s’affiche dans le back-office comme un texte prêt à être copié.',
            '',
            'Le bloc ne contient QUE le texte final. Jamais une consigne, jamais une explication, jamais une phrase qui commence par un verbe à l’infinitif. Contre-exemple à ne pas reproduire : « Ajouter sous le texte une carte Google Maps » n’est pas un texte à coller, c’est un conseil — il s’écrit en phrase normale, hors de tout bloc.',
            'Dis en phrase normale, avant ou après le bloc, où ce texte doit être collé : « à mettre dans la description de la page Contact ».',
            'Repères de longueur : un titre de page vise 60 caractères, une description de référencement entre 120 et 158.',
            '',
            'FORME DE TES RÉPONSES : des phrases courtes, pas de jargon de communicant, pas de liste à rallonge. Trois conseils applicables valent mieux que douze pistes.',
        ]);
    }

    /**
     * Le premier tour de conversation : l'état du site.
     *
     * Il est posé en tour d'utilisateur plutôt que dans la consigne système,
     * comme pour l'assistant public : Gemini met en cache les préfixes
     * identiques, et deux questions de suite ne le repaient pas.
     *
     * @return array<int, array<string, mixed>>
     */
    private function amorce(): array
    {
        return [
            ['role' => 'user', 'parts' => [['text' => "ÉTAT DU SITE\n\n" . $this->etatDuSite()]]],
            ['role' => 'model', 'parts' => [['text' => 'J’ai lu l’état du site. Je conseille à partir de lui.']]],
        ];
    }

    /**
     * @param array<int, array{role: string, texte: string}> $historique
     * @return array<int, array<string, mixed>>
     */
    private function tours(array $historique): array
    {
        $tours = [];
        foreach (array_slice($historique, -self::HISTORIQUE_MAX) as $tour) {
            $texte = trim((string) ($tour['texte'] ?? ''));
            if ($texte === '') {
                continue;
            }
            $tours[] = [
                'role'  => ($tour['role'] ?? '') === 'model' ? 'model' : 'user',
                'parts' => [['text' => mb_substr($texte, 0, 3000)]],
            ];
        }

        return $tours;
    }

    // ------------------------------------------------------------ état du site

    /** Tout ce que le conseiller a le droit de savoir, en un seul texte. */
    public function etatDuSite(): string
    {
        return implode("\n\n", array_filter([
            $this->identite(),
            $this->arborescence(),
            $this->frequentationLue(),
            $this->vieDuSite(),
            $this->questionsPosees(),
            $this->defautsMesures(),
            "=== CONTENU DES PAGES ===\n" . mb_substr($this->assistant->texteDuSite(), 0, self::CONTENU_MAX),
        ]));
    }

    private function identite(): string
    {
        $site = $this->content->load('site');
        $contact = (array) ($site['contact'] ?? []);

        return "=== IDENTITÉ ===\n"
            . 'Commune : ' . (string) ($site['nom'] ?? '') . "\n"
            . 'Accroche : ' . (string) ($site['accroche'] ?? '') . "\n"
            . 'Horaires du secrétariat : ' . (string) ($contact['horaires'] ?? 'non renseignés') . "\n"
            . 'Téléphone : ' . (string) ($contact['telephone'] ?? 'non renseigné');
    }

    /** Les pages, leur adresse, et ce que Google lira d'elles. */
    private function arborescence(): string
    {
        $lignes = ['=== PAGES ET RÉFÉRENCEMENT ===',
                   'chemin | titre affiché par Google (longueur) | description (longueur) | indexée'];

        foreach ($this->seo->pages() as $cle => $page) {
            $meta = $this->seo->meta($cle);
            $titre = (string) ($meta['titre'] ?? '');
            $desc  = (string) ($meta['description'] ?? '');
            $lignes[] = sprintf(
                '%s | %s (%d) | %s (%d) | %s',
                $this->seo->cheminSource($cle),
                $titre !== '' ? $titre : '— vide —',
                mb_strlen($titre),
                $desc !== '' ? $desc : '— vide —',
                mb_strlen($desc),
                ($page['indexer'] ?? true) ? 'oui' : 'NON'
            );
        }

        return implode("\n", $lignes);
    }

    /** Ce que les administrés lisent réellement, et ce qu'ils ne lisent pas. */
    private function frequentationLue(): string
    {
        if (!$this->frequentation->amorcee()) {
            return "=== FRÉQUENTATION ===\nAucun comptage encore : le site vient d’être mis en ligne, "
                . 'ou personne ne l’a visité. Ne fonde aucun conseil sur des chiffres de visite.';
        }

        $phares = $this->frequentation->pagesPhares(15, 90);
        $vues = array_sum($this->frequentation->parJour(90));

        $lignes = ['=== FRÉQUENTATION SUR 90 JOURS ===',
                   'Total des pages vues : ' . $vues,
                   '',
                   'Les plus consultées :'];
        foreach ($phares as $chemin => $nombre) {
            $lignes[] = sprintf('  %-45s %d', $chemin, $nombre);
        }

        /* Les pages absentes du palmarès comptent autant que celles qui y
           sont : une démarche que personne n'ouvre est soit introuvable, soit
           inutile, et les deux se corrigent. */
        $jamais = [];
        foreach (array_keys($this->seo->pages()) as $cle) {
            $chemin = $this->seo->cheminSource($cle);
            if (!isset($phares[$chemin])) {
                $jamais[] = $chemin;
            }
        }
        if ($jamais !== []) {
            $lignes[] = '';
            $lignes[] = 'Hors des quinze premières (donc peu ou pas consultées) : ' . implode(', ', $jamais);
        }

        return implode("\n", $lignes);
    }

    /** Le site est-il vivant ? Dernières publications, agenda, documents. */
    private function vieDuSite(): string
    {
        $lignes = ['=== VIE DU SITE ==='];

        foreach (['actualites' => 'actualité', 'agenda' => 'rendez-vous'] as $collection => $mot) {
            $items = $this->content->publies($collection);
            $dates = [];
            foreach ($items as $item) {
                $d = trim((string) ($item['date'] ?? ''));
                if ($d !== '') {
                    $dates[] = $d;
                }
            }
            rsort($dates);
            $lignes[] = sprintf('%s publiés : %d — dernier daté du %s',
                ucfirst($mot), count($items), $dates[0] ?? 'aucune date');
        }

        $documents = $this->content->load('documents');
        $lignes[] = 'Documents téléchargeables : ' . count((array) ($documents['items'] ?? []));
        $lignes[] = 'Nous sommes le ' . date('d/m/Y') . '.';

        return implode("\n", $lignes);
    }

    /**
     * Ce que les administrés ont demandé à l'assistant public.
     *
     * C'est la source la plus utile du lot : elle dit ce que les gens
     * cherchent et ne trouvent pas. C'est aussi la seule qui contienne des
     * données personnelles — un visiteur laisse spontanément son numéro dans
     * le fil de la discussion, et Conversations::reperer le relève déjà pour
     * la mairie.
     *
     * Trois précautions, donc, avant que quoi que ce soit sorte du serveur :
     * seules les QUESTIONS partent (jamais les réponses, jamais les demandes
     * de rappel), les adresses et les numéros y sont masqués, et les doublons
     * sont écartés. Ce qui est envoyé est une liste de sujets, pas un journal
     * de conversations. La politique de confidentialité le décrit.
     */
    private function questionsPosees(): string
    {
        $questions = [];

        foreach (array_slice($this->conversations->mois(), 0, self::MOIS_RELUS) as $mois) {
            foreach ($this->conversations->duMois($mois) as $conversation) {
                foreach ((array) ($conversation['messages'] ?? []) as $message) {
                    if (($message['role'] ?? '') !== 'visiteur') {
                        continue;
                    }
                    $texte = self::masquer(trim((string) ($message['texte'] ?? '')));
                    // Sous quinze caractères, ce n'est pas une question :
                    // c'est « bonjour », « merci » ou une faute de frappe.
                    if (mb_strlen($texte) < 15) {
                        continue;
                    }
                    $questions[mb_strtolower($texte)] = $texte;
                }
            }
        }

        if ($questions === []) {
            return '';
        }

        return "=== CE QUE LES ADMINISTRÉS DEMANDENT À L’ASSISTANT ===\n"
            . "Questions posées ces derniers mois, sans les réponses, coordonnées masquées.\n"
            . "Une question qui revient et à laquelle le site ne répond pas est une page à écrire.\n\n"
            . implode("\n", array_map(
                static fn(string $q): string => '· ' . $q,
                array_slice(array_values($questions), 0, self::QUESTIONS_MAX)
            ));
    }

    /**
     * Masque ce qui identifie une personne.
     *
     * Les deux motifs sont ceux de Conversations::reperer — une seule vérité
     * sur ce qui ressemble à un numéro ou à une adresse —, plus les suites de
     * chiffres longues, qui attrapent un numéro de sécurité sociale ou de
     * dossier écrit sans séparateur.
     */
    private static function masquer(string $texte): string
    {
        $texte = preg_replace('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', '[adresse masquée]', $texte) ?? $texte;
        $texte = preg_replace('/(?:(?:\+|00)33[\s.\-]?|0)[1-9](?:[\s.\-]?\d{2}){4}/', '[numéro masqué]', $texte) ?? $texte;
        $texte = preg_replace('/\b\d{7,}\b/', '[numéro masqué]', $texte) ?? $texte;

        return mb_substr($texte, 0, 300);
    }

    /**
     * Ce que les contrôles automatiques ont déjà relevé.
     *
     * Donné au conseiller pour qu'il ne le répète pas : rien n'use plus vite
     * la confiance qu'un conseil qui redit ce que l'écran affichait déjà.
     */
    private function defautsMesures(): string
    {
        $lignes = [];

        foreach ($this->seo->pages() as $cle => $_) {
            $meta = $this->seo->meta($cle);
            $titre = mb_strlen((string) ($meta['titre'] ?? ''));
            $desc  = mb_strlen((string) ($meta['description'] ?? ''));
            $chemin = $this->seo->cheminSource($cle);
            if ($titre > 60) {
                $lignes[] = "· $chemin : titre de $titre caractères, coupé par Google vers 60";
            }
            if ($desc > 0 && $desc < 120) {
                $lignes[] = "· $chemin : description de $desc caractères, trop courte";
            }
            if ($desc > 158) {
                $lignes[] = "· $chemin : description de $desc caractères, coupée vers 158";
            }
            if ($desc === 0) {
                $lignes[] = "· $chemin : aucune description";
            }
        }

        if ($lignes === []) {
            return "=== DÉFAUTS DÉJÀ MESURÉS ===\nAucun. Les longueurs de titres et de descriptions sont toutes dans les clous.";
        }

        return "=== DÉFAUTS DÉJÀ MESURÉS ===\n"
            . "Déjà signalés à la mairie sur l’écran Référencement. NE LES RÉPÈTE PAS.\n"
            . implode("\n", $lignes);
    }

    /** Le poids de ce qui part chez Google, pour l'afficher au secrétariat. */
    public function mesure(): int
    {
        return mb_strlen($this->etatDuSite());
    }
}
