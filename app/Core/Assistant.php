<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Assistant de discussion adossé à Gemini.
 *
 * Trois principes commandent cette classe.
 *
 * **L'appel part du serveur, jamais du navigateur.** La clé d'API resterait
 * sinon lisible dans le code de la page, et le visiteur ouvrirait une
 * connexion vers Google — donc un traceur tiers à soumettre au consentement.
 * Ici, le navigateur ne parle qu'à ce site.
 *
 * **Le modèle ne répond que sur trois sources** : le contenu du site, les
 * documents déposés dans le back-office, et le texte saisi à la main. Rien
 * n'est laissé à ses connaissances générales. Trois garde-fous s'y emploient :
 * le corpus est passé en entier dans la requête, la consigne système
 * l'interdit explicitement, et aucun outil de recherche n'est activé.
 *
 * **Une panne de Google ne doit pas peser sur le site.** Les appels ont un
 * délai court, la liste des modèles est mise en cache, et un échec est
 * horodaté pour ne pas être retenté à chaque visite.
 */
final class Assistant
{
    /** Racine de l'API. La version est dans le chemin, pas dans un réglage. */
    private const API = 'https://generativelanguage.googleapis.com/v1beta';

    /** Délai réseau. Une réponse demande plus de temps qu'un simple GET. */
    private const DELAI_RESEAU = 30;
    private const DELAI_RESEAU_LISTE = 8;

    /** Fraîcheur de la liste des modèles, et repos après un échec. */
    private const FRAICHEUR_MODELES = 86400;   // 24 h
    private const REPOS_APRES_ECHEC = 1800;    // 30 min

    /** Garde-fous sur ce qu'un visiteur peut envoyer. */
    public const QUESTION_MAX = 800;
    private const HISTORIQUE_MAX = 12;         // tours conservés dans le contexte

    /** Documents : ce qu'on accepte, et jusqu'où. */
    public const TYPES_DOCUMENTS = ['pdf', 'docx', 'txt', 'md'];
    public const DOCUMENT_MAX = 12582912;      // 12 Mio par fichier
    private const CORPUS_INLINE_MAX = 18874368; // 18 Mio de PDF joints, en tout

    /** Modèle proposé tant que la liste n'a pas été récupérée. */
    public const MODELE_DEFAUT = 'gemini-2.5-flash';

    public function __construct(
        private readonly Parametres $parametres,
        private readonly Content $content,
        private readonly string $dossierDonnees,
        private readonly string $fichierCache,
    ) {
    }

    // ------------------------------------------------------------ disponibilité

    /** Le visiteur doit-il voir la bulle de discussion ? */
    public function actif(): bool
    {
        return (bool) $this->parametres->get('assistant.actif', false)
            && $this->cle() !== '';
    }

    public function cle(): string
    {
        return trim((string) $this->parametres->get('assistant.cle', ''));
    }

    public function modele(): string
    {
        $m = trim((string) $this->parametres->get('assistant.modele', ''));
        return $m !== '' ? $m : self::MODELE_DEFAUT;
    }

    public function titre(): string
    {
        $t = trim((string) $this->parametres->get('assistant.titre', ''));
        return $t !== '' ? $t : 'Une question ?';
    }

    /**
     * La bulle telle que la mairie l'a réglée : forme, couleurs, libellé,
     * taille. Le gabarit ne lit jamais les paramètres lui-même — c'est ici que
     * le bornage et la résolution de contraste ont lieu, une fois.
     */
    public function bulle(): Bulle
    {
        return Bulle::depuis($this->parametres, $this->titre());
    }

    public function accueil(): string
    {
        $a = trim((string) $this->parametres->get('assistant.accueil', ''));
        return $a !== '' ? $a : 'Posez votre question sur les démarches ou la vie de la commune : je réponds à partir du contenu du site.';
    }

    // ------------------------------------------------------------------ modèles

    /**
     * Liste des modèles disponibles pour la clé enregistrée.
     *
     * Elle est interrogée chez Google plutôt qu'écrite en dur : les modèles
     * apparaissent et disparaissent au fil des mois, et une liste figée dans
     * le code obligerait à livrer une mise à jour pour en essayer un nouveau.
     *
     * @return array<int, array{id: string, nom: string}>
     */
    public function modeles(bool $forcer = false): array
    {
        $cache = $this->lireCache();

        if (!$forcer && isset($cache['modeles'], $cache['modeles_le'])
            && (time() - (int) $cache['modeles_le']) < self::FRAICHEUR_MODELES) {
            return $cache['modeles'];
        }
        if (!$forcer && isset($cache['echec_le'])
            && (time() - (int) $cache['echec_le']) < self::REPOS_APRES_ECHEC) {
            return $cache['modeles'] ?? [];
        }

        try {
            $modeles = $this->interrogerModeles();
        } catch (RuntimeException $e) {
            $cache['echec_le'] = time();
            $cache['echec'] = $e->getMessage();
            $this->ecrireCache($cache);
            return $cache['modeles'] ?? [];
        }

        unset($cache['echec_le'], $cache['echec']);
        $cache['modeles'] = $modeles;
        $cache['modeles_le'] = time();
        $this->ecrireCache($cache);

        return $modeles;
    }

    /** Dernier message d'erreur retenu, pour l'afficher dans le back-office. */
    public function derniereErreur(): string
    {
        return (string) ($this->lireCache()['echec'] ?? '');
    }

    /** @return array<int, array{id: string, nom: string}> */
    private function interrogerModeles(): array
    {
        $cle = $this->cle();
        if ($cle === '') {
            throw new RuntimeException('Aucune clé d’API enregistrée.');
        }

        [$code, $corps] = $this->requete(
            self::API . '/models?pageSize=200&key=' . rawurlencode($cle),
            ['Accept: application/json'],
            null,
            self::DELAI_RESEAU_LISTE
        );

        $json = json_decode($corps, true);
        if ($code !== 200 || !is_array($json)) {
            throw new RuntimeException($this->messageErreur($code, is_array($json) ? $json : []));
        }

        $modeles = [];
        foreach ($json['models'] ?? [] as $m) {
            // seuls les modèles qui savent produire du texte nous intéressent
            if (!in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) {
                continue;
            }
            $id = preg_replace('#^models/#', '', (string) ($m['name'] ?? '')) ?? '';
            if ($id === '') {
                continue;
            }
            $modeles[] = [
                'id'  => $id,
                'nom' => trim((string) ($m['displayName'] ?? $id)),
            ];
        }

        usort($modeles, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));

        return $modeles;
    }

    // ------------------------------------------------------------------ réponse

    /**
     * Répond à une question, en s'appuyant uniquement sur le corpus.
     *
     * @param array<int, array{role: string, texte: string}> $historique
     * @return array{reponse: string}
     */
    public function repondre(string $question, array $historique = []): array
    {
        $question = trim($question);
        if ($question === '') {
            throw new RuntimeException('La question est vide.');
        }
        if (mb_strlen($question) > self::QUESTION_MAX) {
            $question = mb_substr($question, 0, self::QUESTION_MAX);
        }
        if (!$this->actif()) {
            throw new RuntimeException('L’assistant n’est pas activé.');
        }

        [$texte, $fichiers] = $this->corpus();
        if (trim($texte) === '' && $fichiers === []) {
            throw new RuntimeException('Aucune source n’est renseignée : l’assistant n’a rien à lire.');
        }

        // Le corpus est joint au premier tour de la conversation plutôt qu'à
        // la consigne système : Gemini met en cache les préfixes identiques,
        // et l'ensemble des visiteurs partage ainsi le même début de requête.
        $contenus = [[
            'role'  => 'user',
            'parts' => array_merge(
                [['text' => "SOURCES AUTORISÉES\n\n" . $texte]],
                $fichiers
            ),
        ], [
            'role'  => 'model',
            'parts' => [['text' => 'J’ai lu ces sources. Je répondrai uniquement à partir d’elles.']],
        ]];

        foreach (array_slice($historique, -self::HISTORIQUE_MAX) as $tour) {
            $role = ($tour['role'] ?? '') === 'model' ? 'model' : 'user';
            $t = trim((string) ($tour['texte'] ?? ''));
            if ($t === '') {
                continue;
            }
            $contenus[] = ['role' => $role, 'parts' => [['text' => mb_substr($t, 0, 2000)]]];
        }

        $contenus[] = ['role' => 'user', 'parts' => [['text' => $question]]];

        return ['reponse' => $this->generer($this->consigne(), $contenus)];
    }

    /**
     * Un appel au modèle, et le texte qu'il rend.
     *
     * Sorti de `repondre()` parce qu'il a deux appelants : l'assistant des
     * visiteurs, et le conseiller du back-office (App\Core\Conseiller). Un
     * seul chemin réseau, une seule traduction des erreurs de Google — deux
     * copies auraient divergé au premier message d'erreur ajouté.
     *
     * @param array<int, array<string, mixed>> $contenus tours de conversation
     *                                                   au format de l'API
     * @param array<string, mixed> $reglages surcharges de generationConfig
     */
    public function generer(string $consigne, array $contenus, array $reglages = []): string
    {
        $charge = [
            'systemInstruction' => ['parts' => [['text' => $consigne]]],
            'contents' => $contenus,
            'generationConfig' => $reglages + [
                'temperature'     => 0.2,
                'maxOutputTokens' => 900,
            ],
            // Aucun outil déclaré : le modèle n'a ni recherche web ni
            // exécution de code. C'est le troisième garde-fou qui l'enferme
            // dans les sources fournies.
        ];

        [$code, $corps] = $this->requete(
            self::API . '/models/' . rawurlencode($this->modele()) . ':generateContent?key=' . rawurlencode($this->cle()),
            ['Content-Type: application/json'],
            json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            self::DELAI_RESEAU
        );

        $json = json_decode($corps, true);
        if ($code !== 200 || !is_array($json)) {
            throw new RuntimeException($this->messageErreur($code, is_array($json) ? $json : []));
        }

        $reponse = '';
        foreach ($json['candidates'][0]['content']['parts'] ?? [] as $part) {
            $reponse .= (string) ($part['text'] ?? '');
        }
        $reponse = trim($reponse);

        if ($reponse === '') {
            $motif = (string) ($json['candidates'][0]['finishReason'] ?? '');
            throw new RuntimeException($motif === 'MAX_TOKENS'
                ? 'La réponse a été coupée. Reformulez votre question plus précisément.'
                : 'Le modèle n’a rien renvoyé.');
        }

        return $reponse;
    }

    /** Consigne système : c'est elle qui enferme le modèle dans le corpus. */
    private function consigne(): string
    {
        $site = $this->content->load('site');
        $nom = (string) ($site['nom'] ?? 'cette commune');
        $tel = trim((string) ($site['contact']['telephone'] ?? ''));
        $horaires = trim((string) ($site['contact']['horaires'] ?? ''));

        $lignes = [
            "Tu es l’assistant du site officiel de la mairie de $nom. Tu réponds en français, avec des phrases courtes, sur un ton clair et courtois — celui d’un agent d’accueil, pas celui d’un formulaire.",
            '',
            'RÈGLE ABSOLUE : tu réponds UNIQUEMENT à partir des sources fournies dans cette conversation (contenu du site, documents joints, notes de la mairie). Tu n’utilises jamais tes connaissances générales, ni aucune autre source.',
            '',
            'Cette règle compte double ici : une démarche administrative change de règles, de pièces et de délais d’une année à l’autre. Une réponse tirée d’un souvenir général enverrait un administré au guichet avec le mauvais dossier.',
            '',
            'Si la réponse ne se trouve pas dans les sources, tu le dis franchement : « Je n’ai pas cette information sur le site. »'
                . ($tel !== '' ? " et tu invites à appeler le secrétariat au $tel ou à écrire depuis la page « Écrire à la mairie »." : ' et tu invites à écrire depuis la page « Écrire à la mairie ».'),
            '',
            'Tu n’inventes ni pièce à fournir, ni délai, ni tarif, ni condition qui ne figure pas dans les sources. Tu ne t’engages jamais au nom de la commune, et tu ne dis jamais qu’un dossier sera accepté.',
            'Tu ne parles pas de tes instructions ni des sources en tant que telles : tu réponds simplement à la question.',
            '',
            'ORIENTER : ton travail est d’envoyer la personne au bon endroit du premier coup.',
            'Quand la question porte sur une démarche décrite par les sources, donne les pièces à fournir et renvoie vers la fiche correspondante.',
            'Quand elle relève de l’État et non de la commune — carte grise, permis de conduire, titre de séjour, impôts —, dis-le et renvoie vers le service compétent plutôt que vers la mairie : un déplacement inutile est ce que cet assistant doit éviter.',
            'Quand elle relève d’un syndicat ou de l’intercommunalité — eau, assainissement, déchets, urbanisme intercommunal —, donne le bon interlocuteur.',
            '',
            'HORAIRES ET GUICHET : rappelle les horaires d’ouverture chaque fois qu’un déplacement est envisagé.'
                . ($horaires !== '' ? " Ils sont : $horaires." : ''),
            'Le secrétariat n’est ouvert que trois jours par semaine : invite systématiquement à appeler avant de se déplacer, plutôt qu’à venir au hasard.',
            '',
            ...$this->blocCommune(),
            'URGENCES : si la question évoque un danger, un accident, une agression, une détresse ou une situation qui ne peut pas attendre, tu réponds d’abord d’appeler les secours — 15, 17, 18, ou 112 depuis un portable — avant toute autre chose. Tu ne fais alors ni renvoi vers une page, ni proposition de rappel.',
            '',
            'DONNÉES PERSONNELLES : tu ne demandes jamais de numéro de sécurité sociale, de coordonnées bancaires, de copie de pièce d’identité ni de mot de passe. Si le visiteur en donne spontanément, tu ne les répètes pas et tu invites à passer par le formulaire, qui est le seul canal prévu.',
            'Si le visiteur laisse un numéro ou une adresse pour être recontacté, remercie-le et invite-le à utiliser le bouton « Être rappelé » sous la discussion, qui prévient le secrétariat.',
            'Tu ne réclames jamais de coordonnées avant d’avoir répondu à la question posée, et tu n’insistes pas si le visiteur ne veut pas les donner.',
            '',
            'NEUTRALITÉ : tu ne prends pas parti sur les décisions du conseil municipal, tu ne commentes pas la vie politique et tu ne réponds pas aux questions d’opinion. Tu rappelles alors que les séances du conseil sont publiques et que les comptes-rendus sont en ligne.',
        ];

        return implode("\n", $lignes);
    }

    /**
     * Le paragraphe de consignes propres à la commune, prêt à être inséré.
     *
     * @return string[] vide s'il n'y en a aucune : la consigne se referme
     *                  alors sans titre orphelin
     */
    private function blocCommune(): array
    {
        $propres = $this->consignesCommune();
        if ($propres === []) {
            return [];
        }

        return array_merge(
            ['PROPRE À CETTE COMMUNE — ce qu’elle ne fait pas, et qu’il faut dire tout de suite :'],
            $propres,
            ['']
        );
    }

    /**
     * Les règles de service propres à la commune, une par ligne.
     *
     * Elles étaient écrites dans la consigne ci-dessus, et c'était un défaut
     * de fond : « la mairie n'est pas équipée d'un dispositif de recueil » est
     * vrai d'Angeot et faux d'une commune voisine. Le socle servant de modèle,
     * la mairie suivante aurait hérité d'un assistant qui affirme un service
     * qu'elle rend, ou nie un service qu'elle ne rend pas — exactement ce que
     * ce dépôt s'interdit. Elles vivent donc dans un contenu, modifiable
     * depuis le back-office comme le reste.
     *
     * @return string[]
     */
    public function consignesCommune(): array
    {
        $lignes = [];
        foreach ((array) $this->content->get('assistant', 'consignes', []) as $ligne) {
            $ligne = trim((string) $ligne);
            if ($ligne !== '') {
                $lignes[] = $ligne;
            }
        }

        return $lignes;
    }

    /**
     * Enregistre les consignes propres à la commune.
     *
     * @param string[] $lignes
     */
    public function enregistrerConsignes(array $lignes): void
    {
        $propres = [];
        foreach ($lignes as $ligne) {
            $ligne = trim((string) $ligne);
            if ($ligne !== '') {
                // Une consigne est une phrase, pas un document : la borne
                // évite qu'un copier-coller de page entière parte à chaque
                // question et consomme le contexte du modèle.
                $propres[] = mb_substr($ligne, 0, 400);
            }
        }

        $this->content->save('assistant', ['consignes' => $propres]);
    }

    // ------------------------------------------------------------------- corpus

    /**
     * Assemble les trois sources autorisées.
     *
     * @return array{0: string, 1: array<int, array<string, mixed>>} texte, et
     *         pièces jointes au format attendu par l'API
     */
    public function corpus(): array
    {
        $morceaux = [];
        $fichiers = [];

        if ((bool) $this->parametres->get('assistant.source_site', true)) {
            $morceaux[] = "=== CONTENU DU SITE ===\n" . $this->texteDuSite();
        }

        $notes = trim($this->notes());
        if ($notes !== '') {
            $morceaux[] = "=== NOTES DE LA MAIRIE ===\n" . $notes;
        }

        $poidsInline = 0;
        foreach ($this->documents() as $doc) {
            $chemin = $this->dossierDocuments() . '/' . $doc['fichier'];
            if (!is_file($chemin)) {
                continue;
            }

            if ($doc['extension'] === 'pdf') {
                // Gemini lit nativement le PDF : l'envoyer tel quel évite une
                // extraction de texte maison, qui perdrait les tableaux et
                // rendrait les PDF scannés illisibles.
                $poids = (int) filesize($chemin);
                if ($poidsInline + $poids > self::CORPUS_INLINE_MAX) {
                    continue;
                }
                $poidsInline += $poids;
                $fichiers[] = ['inline_data' => [
                    'mime_type' => 'application/pdf',
                    'data'      => base64_encode((string) file_get_contents($chemin)),
                ]];
                continue;
            }

            $texte = $doc['extension'] === 'docx'
                ? $this->texteDuDocx($chemin)
                : (string) file_get_contents($chemin);

            if (trim($texte) !== '') {
                $morceaux[] = '=== DOCUMENT : ' . $doc['nom'] . " ===\n" . $texte;
            }
        }

        return [implode("\n\n", $morceaux), $fichiers];
    }

    /**
     * Le contenu éditorial du site, aplati en texte.
     *
     * On repart des fichiers JSON plutôt que d'aspirer les pages rendues :
     * c'est la même information sans le balisage, sans la navigation répétée
     * sur chaque page, et sans requête HTTP vers soi-même.
     */
    public function texteDuSite(): string
    {
        $morceaux = [];

        foreach (Seo::PAGES as $cle => $_) {
            // Une page déclarée mais dont le contenu n'existe pas encore ne
            // doit pas priver l'assistant de tout le reste du site.
            try {
                $page = $this->content->load('pages/' . $cle);
            } catch (\RuntimeException) {
                continue;
            }
            if ($page !== []) {
                $morceaux[] = $this->aplatir($page);
            }
        }
        foreach (['site', 'demarches', 'actualites', 'agenda', 'associations', 'numeros', 'services-etat', 'conseil', 'commissions'] as $collection) {
            $donnees = $this->content->load($collection);
            if ($donnees !== []) {
                $morceaux[] = $this->aplatir($donnees);
            }
        }

        return trim(implode("\n", $morceaux));
    }

    /**
     * Aplatit une structure JSON en lignes de texte.
     *
     * Les clés techniques sont écartées : un chemin d'image ou une adresse
     * n'apprend rien au modèle et consomme du contexte.
     */
    private function aplatir(mixed $valeur, string $prefixe = ''): string
    {
        // « numero » n'est pas écarté : sur ce site il porte les numéros
        // d'urgence et les téléphones des services, qui sont précisément ce
        // qu'on demande à l'assistant.
        static $ignorees = ['image', 'images', 'photo', 'icone', 'slug', 'url', 'lien',
                            'logo', 'carte', 'reseaux', 'meta', 'actif'];

        if (is_scalar($valeur)) {
            $t = trim((string) $valeur);
            return $t === '' || $t === '1' || $t === '' ? '' : $t;
        }
        if (!is_array($valeur)) {
            return '';
        }

        $lignes = [];
        foreach ($valeur as $cle => $sous) {
            if (is_string($cle) && in_array($cle, $ignorees, true)) {
                continue;
            }
            $t = $this->aplatir($sous, $prefixe);
            if ($t !== '') {
                $lignes[] = is_string($cle) && !is_numeric($cle) && is_scalar($sous)
                    ? ucfirst(str_replace('_', ' ', $cle)) . ' : ' . $t
                    : $t;
            }
        }

        return implode("\n", $lignes);
    }

    /**
     * Texte d'un .docx.
     *
     * Un .docx est une archive zip dont word/document.xml porte le texte :
     * l'extraire ne demande que ZipArchive, présent partout, là où lire un
     * .doc binaire aurait demandé une bibliothèque.
     */
    private function texteDuDocx(string $chemin): string
    {
        if (!class_exists(\ZipArchive::class)) {
            return '';
        }

        $zip = new \ZipArchive();
        if ($zip->open($chemin) !== true) {
            return '';
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === '') {
            return '';
        }

        // les fins de paragraphe deviennent des retours à la ligne, le reste
        // du balisage disparaît
        $xml = preg_replace('#</w:p>#', "\n", $xml) ?? $xml;
        $xml = preg_replace('#<w:tab[^>]*/>#', "\t", $xml) ?? $xml;

        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    // ---------------------------------------------------------------- documents

    public function dossierDocuments(): string
    {
        return $this->dossierDonnees . '/assistant/documents';
    }

    /** @return array<int, array{fichier: string, nom: string, extension: string, poids: int}> */
    public function documents(): array
    {
        $dossier = $this->dossierDocuments();
        if (!is_dir($dossier)) {
            return [];
        }

        $liste = [];
        foreach (scandir($dossier) ?: [] as $entree) {
            if ($entree === '.' || $entree === '..' || str_starts_with($entree, '.')) {
                continue;
            }
            $extension = strtolower(pathinfo($entree, PATHINFO_EXTENSION));
            if (!in_array($extension, self::TYPES_DOCUMENTS, true)) {
                continue;
            }
            $liste[] = [
                'fichier'   => $entree,
                'nom'       => $entree,
                'extension' => $extension,
                'poids'     => (int) filesize($dossier . '/' . $entree),
            ];
        }

        usort($liste, static fn(array $a, array $b): int => strcasecmp($a['nom'], $b['nom']));

        return $liste;
    }

    /** @param array{name?: string, tmp_name?: string, error?: int, size?: int} $fichier */
    public function ajouterDocument(array $fichier): string
    {
        if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('L’envoi du document a échoué.');
        }
        if ((int) ($fichier['size'] ?? 0) > self::DOCUMENT_MAX) {
            throw new RuntimeException('Le document dépasse ' . (int) (self::DOCUMENT_MAX / 1048576) . ' Mo.');
        }

        $nom = (string) ($fichier['name'] ?? '');
        $extension = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
        if (!in_array($extension, self::TYPES_DOCUMENTS, true)) {
            throw new RuntimeException('Formats acceptés : ' . implode(', ', self::TYPES_DOCUMENTS) . '.');
        }

        $dossier = $this->dossierDocuments();
        if (!is_dir($dossier) && !@mkdir($dossier, 0755, true) && !is_dir($dossier)) {
            throw new RuntimeException('Impossible de créer ' . $dossier . '.');
        }

        // Le nom est reconstruit : un nom de fichier venu du navigateur peut
        // porter des séparateurs de chemin ou des caractères que le système
        // de fichiers refuse.
        $base = Seo::normaliser(pathinfo($nom, PATHINFO_FILENAME)) ?: 'document';
        $cible = $base . '.' . $extension;
        $i = 2;
        while (is_file($dossier . '/' . $cible)) {
            $cible = $base . '-' . $i++ . '.' . $extension;
        }

        if (!@move_uploaded_file((string) $fichier['tmp_name'], $dossier . '/' . $cible)) {
            throw new RuntimeException('Impossible d’enregistrer le document.');
        }
        @chmod($dossier . '/' . $cible, 0644);

        return $cible;
    }

    public function supprimerDocument(string $nom): bool
    {
        $nom = basename($nom);
        $chemin = $this->dossierDocuments() . '/' . $nom;

        return is_file($chemin) && @unlink($chemin);
    }

    // -------------------------------------------------------------------- notes

    private function fichierNotes(): string
    {
        return $this->dossierDonnees . '/assistant/notes.html';
    }

    /** Le texte saisi à la main dans le back-office, balisage compris. */
    public function notesHtml(): string
    {
        $f = $this->fichierNotes();

        return is_file($f) ? (string) file_get_contents($f) : '';
    }

    /** Le même texte, débarrassé de son balisage, pour être envoyé au modèle. */
    public function notes(): string
    {
        $html = $this->notesHtml();
        if ($html === '') {
            return '';
        }

        $html = preg_replace('#</(p|div|li|h[1-6]|tr)>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }

    public function enregistrerNotes(string $html): void
    {
        $dossier = dirname($this->fichierNotes());
        if (!is_dir($dossier) && !@mkdir($dossier, 0755, true) && !is_dir($dossier)) {
            throw new RuntimeException('Impossible de créer ' . $dossier . '.');
        }

        // Écriture atomique, comme tout le reste du socle : jamais en place.
        $temporaire = $this->fichierNotes() . '.tmp';
        if (@file_put_contents($temporaire, $html, LOCK_EX) === false
            || !@rename($temporaire, $this->fichierNotes())) {
            @unlink($temporaire);
            throw new RuntimeException('Impossible d’enregistrer les notes.');
        }
        @chmod($this->fichierNotes(), 0644);
    }

    /** Taille du corpus, en caractères — repère utile dans le back-office. */
    public function mesureCorpus(): array
    {
        [$texte, $fichiers] = $this->corpus();

        return ['caracteres' => mb_strlen($texte), 'documents' => count($fichiers)];
    }

    // -------------------------------------------------------------------- cache

    /** @return array<string, mixed> */
    private function lireCache(): array
    {
        if (!is_file($this->fichierCache)) {
            return [];
        }
        $lu = json_decode((string) file_get_contents($this->fichierCache), true);

        return is_array($lu) ? $lu : [];
    }

    /** @param array<string, mixed> $donnees */
    private function ecrireCache(array $donnees): void
    {
        $dossier = dirname($this->fichierCache);
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0755, true);
        }
        $temporaire = $this->fichierCache . '.tmp';
        if (@file_put_contents($temporaire, json_encode($donnees, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
            @rename($temporaire, $this->fichierCache);
        }
    }

    // ------------------------------------------------------------------- réseau

    /** @param string[] $entetes @return array{0:int, 1:string} */
    private function requete(string $url, array $entetes, ?string $corpsEnvoye, int $delai): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $entetes,
                CURLOPT_TIMEOUT        => $delai,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            if ($corpsEnvoye !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $corpsEnvoye);
            }

            $reponse = curl_exec($ch);
            $code    = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $erreur  = curl_error($ch);
            curl_close($ch);

            if ($reponse === false) {
                throw new RuntimeException('Le service est injoignable : ' . ($erreur ?: 'délai dépassé') . '.');
            }

            return [$code, (string) $reponse];
        }

        $contexte = stream_context_create(['http' => [
            'method'        => $corpsEnvoye !== null ? 'POST' : 'GET',
            'header'        => implode("\r\n", $entetes),
            'content'       => $corpsEnvoye ?? '',
            'timeout'       => $delai,
            'ignore_errors' => true,
        ]]);

        $reponse = @file_get_contents($url, false, $contexte);
        if ($reponse === false) {
            throw new RuntimeException('Le service est injoignable (les connexions sortantes sont peut-être bloquées).');
        }

        $code = 0;
        foreach ($http_response_header ?? [] as $entete) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $entete, $m) === 1) {
                $code = (int) $m[1];
            }
        }

        return [$code, (string) $reponse];
    }

    /** @param array<string, mixed> $json */
    private function messageErreur(int $code, array $json): string
    {
        $detail = trim((string) ($json['error']['message'] ?? ''));

        return match (true) {
            $code === 400 && str_contains($detail, 'API key') => 'La clé d’API est refusée. Vérifiez-la.',
            $code === 403 => 'Accès refusé : la clé n’a pas les droits, ou l’API n’est pas activée sur le projet.',
            $code === 404 => 'Ce modèle n’existe pas ou n’est pas accessible avec cette clé.',
            $code === 429 => 'Quota dépassé pour le moment. Réessayez dans quelques minutes.',
            $code >= 500  => 'Le service est momentanément indisponible.',
            default       => $detail !== '' ? $detail : 'Erreur ' . $code . '.',
        };
    }
}
