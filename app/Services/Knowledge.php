<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Domain\PageRepository;
use App\Domain\TradeRepository;
use App\Storage\Index;
use App\Storage\Json;

/**
 * Base de connaissance locale de l'assistant.
 *
 * Contenu : les pages clés du site, les pages éditoriales, les offres en
 * ligne, les profils de l'annuaire, les employeurs, les fiches métiers en
 * entier, et les documents ajoutés depuis le back-office (PDF, Markdown,
 * JSON). Chaque fragment porte le chemin de sa page : c'est lui que
 * l'assistant transforme en lien dans sa réponse.
 *
 * Elle se reconstruit d'elle-même dès qu'un index du site est plus récent
 * qu'elle : une offre publiée, une fiche corrigée ou un déploiement sont
 * connus de l'assistant à la question suivante.
 */
final class Knowledge
{
    private const CHUNK = 900;     // caractères par fragment
    private const OVERLAP = 120;   // recouvrement, pour ne pas couper une phrase utile

    private static function path(): string
    {
        return Config::path('data') . '/index/knowledge.json';
    }

    public static function docsDir(): string
    {
        $dir = Config::path('data') . '/private/ai';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** Index dont dépend la base : si l'un est plus récent qu'elle, elle se reconstruit. */
    private const SOURCES = ['jobs', 'cv', 'employers', 'pages', 'trades'];

    /** @return array{count:int, generated_at:string} */
    public static function rebuild(): array
    {
        // La base est écrite en français, quelle que soit la langue de la
        // requête qui la reconstruit : c'est l'assistant qui traduit.
        $lang = I18n::lang();
        if ($lang !== 'fr') {
            I18n::boot('fr');
        }
        try {
            return self::build();
        } finally {
            if ($lang !== 'fr') {
                I18n::boot($lang);
            }
        }
    }

    /** @return array{count:int, generated_at:string} */
    private static function build(): array
    {
        $chunks = [];

        // Les pages clés d'abord : « où déposer mon CV ? » doit mener au
        // formulaire, pas seulement au guide qui l'explique.
        foreach (self::siteMap() as [$url, $title, $text, $words]) {
            self::addChunks($chunks, $title, $url, 'page', $text, $words);
        }

        foreach (PageRepository::published('fr') as $page) {
            self::addSections($chunks, (string) $page['title'], '/' . $page['slug'], (string) $page['body']);
        }

        // Offres en ligne seulement : une annonce expirée n'a plus à être proposée.
        foreach (Search::live(Index::load('jobs')) as $job) {
            if (($job['status'] ?? '') !== 'publish') {
                continue;
            }
            $body = implode("\n", array_filter([
                (string) $job['title'],
                'Structure : ' . (string) $job['company'],
                'Lieu : ' . (string) ($job['city'] ?: $job['region']),
                'Contrat : ' . implode(', ', (array) $job['contract']),
                'Rémunération : ' . ((string) $job['salary'] !== '' ? (string) $job['salary'] : 'non précisée'),
                (string) $job['excerpt'],
            ]));
            self::addChunks($chunks, (string) $job['title'], '/offre/' . $job['slug'], 'offre', $body);
        }

        foreach (Index::load('cv') as $cv) {
            $body = implode("\n", array_filter([
                (string) $cv['name'] . ' — ' . (string) $cv['title'],
                'Ville : ' . (string) $cv['city'],
                'Compétences : ' . implode(', ', (array) $cv['skills']),
                (string) $cv['excerpt'],
            ]));
            self::addChunks($chunks, (string) $cv['name'], '/cv/' . $cv['slug'], 'profil', $body);
        }

        // Les fiches employeurs : l'assistant peut ainsi renvoyer vers la page
        // d'une structure, et pas seulement vers ses offres.
        foreach (Index::load('employers') as $employer) {
            $body = implode("\n", array_filter([
                (string) $employer['name'],
                (string) $employer['tagline'],
                (string) $employer['kind'] !== '' ? 'Type : ' . (string) $employer['kind'] : '',
                (string) $employer['city'] !== '' ? 'Ville : ' . (string) $employer['city'] : '',
                ((int) $employer['job_count']) . ' offre(s) publiée(s) sur intermittent.fr',
            ]));
            self::addChunks($chunks, (string) $employer['name'],
                '/employeur/' . $employer['slug'], 'employeur', $body);
        }

        self::addTrades($chunks);

        foreach (self::documents() as $doc) {
            self::addChunks($chunks, (string) $doc['title'], '', 'document', (string) $doc['text']);
        }

        Json::write(self::path(), [
            'generated_at' => date('c'),
            'chunks'       => $chunks,
        ]);

        return ['count' => count($chunks), 'generated_at' => date('c')];
    }

    /**
     * Pages clés du site, décrites en quelques lignes pour que l'assistant
     * envoie tout droit vers le formulaire, la liste ou l'annuaire concerné.
     *
     * @return array<int, array{0:string, 1:string, 2:string, 3:string}> chemin, titre, texte,
     *         mots par lesquels on la cherche
     */
    private static function siteMap(): array
    {
        $trades = count(Trades::published());
        return [
            ['/', 'Accueil d’intermittent.fr',
             'intermittent.fr publie depuis 2005 des offres d’emploi et un annuaire de CV pour les intermittents '
             . 'du spectacle, de l’audiovisuel et de l’événementiel. Le site est gratuit des deux côtés : déposer '
             . 'un CV, publier une annonce, chercher un emploi et recruter ne coûtent rien, sans commission ni abonnement.',
             'site accueil gratuit présentation intermittent'],
            ['/offres', 'Offres d’emploi',
             'Toutes les offres d’emploi du spectacle vivant, du cinéma, de l’audiovisuel et de l’événementiel : '
             . 'techniciens, artistes, production, régie. Recherche par mot-clé, ville, région et type de contrat. '
             . 'Les annonces déposées sur le site côtoient celles de sites partenaires.',
             'offres emploi emplois job jobs travail mission chercher trouver postuler candidater'],
            ['/cv', 'Annuaire des CV',
             'L’annuaire des CV d’intermittents et de professionnels du spectacle : recherche par métier, compétence '
             . 'et ville. Les employeurs contactent les profils par le formulaire du site, sans intermédiaire ; '
             . 'les coordonnées des candidats restent masquées.',
             'annuaire cv profils candidats trouver technicien artiste recruter recrute embaucher'],
            ['/employeurs', 'Les employeurs du spectacle',
             'Les structures qui recrutent sur intermittent.fr : compagnies, théâtres, salles, festivals, prestataires '
             . 'techniques, sociétés de production, agences événementielles, chacune avec ses offres en ligne.',
             'employeurs structures entreprises compagnies recruteurs'],
            ['/deposer-un-cv', 'Déposer un CV',
             'Déposer son CV est gratuit et prend deux minutes : métier principal, expérience, ville, mobilité, '
             . 'compétences, présentation, et fichier CV en PDF ou DOCX de 5 Mo au plus. Le profil rejoint l’annuaire '
             . 'consulté chaque jour par les employeurs du spectacle, et l’on peut choisir de ne pas y figurer. Les '
             . 'coordonnées restent masquées : les employeurs écrivent par le formulaire de contact.',
             'déposer publier mettre cv profil inscrire inscription candidature postuler'],
            ['/deposer-une-annonce', 'Déposer une annonce',
             'Publier une offre d’emploi est gratuit et sans commission : intitulé du poste, structure, lieu, '
             . 'rémunération, date de démarrage, type de contrat, description et adresse de réception des '
             . 'candidatures. Avant publication, l’assistant vérifie que le statut, la rémunération et l’écriture '
             . 'inclusive sont mentionnés. L’annonce apparaît aussi sur la fiche du métier concerné.',
             'déposer publier poster diffuser annonce offre recruter recrute embaucher recrutement'],
            ['/metiers', 'Les métiers du spectacle',
             sprintf('%d fiches métiers du spectacle, de l’audiovisuel et de l’événementiel : missions, journée '
             . 'type, formation et écoles, statut d’intermittent, salaire indicatif et offres d’emploi du moment, '
             . 'métier par métier.', $trades),
             'métiers fiches métier liste familles'],
            ['/ressources', 'Ressources',
             'Les guides d’intermittent.fr : déposer un CV, publier une annonce, questions fréquentes, présentation '
             . 'du site, conditions d’utilisation et confidentialité.',
             'ressources guides aide conseils'],
        ];
    }

    /**
     * Fiches métiers, section par section : « comment devenir régisseur ? »
     * trouve la section formation de la bonne fiche, pas un long fragment où
     * elle se noie. Chaque section garde le lien de sa fiche, et les
     * synonymes du métier — « perchiste » pour le perchman — pèsent dans la
     * recherche sans encombrer le texte.
     */
    private static function addTrades(array &$chunks): void
    {
        $byFamily = [];
        foreach (TradeRepository::all() as $trade) {
            if (($trade['status'] ?? '') !== 'publish') {
                continue;
            }
            $url = '/metiers/' . $trade['slug'];
            $name = (string) $trade['name'];
            $inline = Trades::inline($name);
            $aliases = implode(' ', array_merge([(string) $trade['name_f']], (array) $trade['keywords']));
            $list = static fn(string $label, array $items): string
                => $items === [] ? '' : $label . ' : ' . implode(' ; ', array_map('strval', $items)) . '.';
            $pay = Trades::payLabel((array) $trade['pay']);

            // Titre, textes, et les mots par lesquels on pose la question —
            // « combien gagne » ne figure dans aucune fiche, « salaire » si.
            $sections = [
                [$name . ' — le métier', [
                    (string) $trade['summary'], (string) $trade['intro'],
                    $list('Missions', (array) $trade['missions']),
                ], 'métier fiche missions rôle travail fait'],
                [$name . ' — une journée type', [(string) $trade['day']], 'journée quotidien horaires'],
                [$name . ' — compétences et qualités', [$list('Compétences', (array) $trade['skills'])],
                 'compétences qualités savoir faire aptitudes'],
                ['Comment devenir ' . $inline . ' ?', [
                    (string) $trade['training'], $list('Formations et écoles', (array) $trade['schools']),
                ], 'devenir formation formations diplôme diplômes école écoles études apprendre cursus'],
                [$name . ' — statut et contrat', [
                    (string) $trade['statut'], (string) ($trade['brief']['status'] ?? ''),
                ], 'statut intermittent intermittence contrat cddu annexe chômage salarié'],
                ['Quel salaire pour un ' . $inline . ' ?', [
                    $pay !== '' ? 'Rémunération indicative : ' . $pay . '.' : '',
                    (string) ($trade['pay']['note'] ?? ''),
                    I18n::t('trade.pay_disclaimer'),
                ], 'salaire salaires combien gagne gagner gagnent rémunération payé paie tarif cachet brut'],
                [$name . ' — évolution de carrière', [(string) $trade['career']],
                 'évolution carrière avenir débouchés perspectives'],
            ];
            foreach ((array) $trade['faq'] as $item) {
                $question = trim((string) ($item['q'] ?? ''));
                if ($question !== '') {
                    $sections[] = [$name . ' — ' . $question, [(string) ($item['a'] ?? '')], ''];
                }
            }

            foreach ($sections as [$title, $parts, $words]) {
                $text = implode("\n", array_filter(array_map('trim', $parts), 'strlen'));
                self::addChunks($chunks, $title, $url, 'métier', $text, $aliases . ' ' . $words, $name);
            }
            $byFamily[(string) $trade['family']][$url] = $name;
        }

        // Une famille, ses métiers et leurs liens : « quels métiers dans le
        // son ? » reçoit la liste, chaque nom menant à sa fiche.
        foreach (Trades::families() as $key => $family) {
            $trades = $byFamily[$key] ?? [];
            if ($trades === []) {
                continue;
            }
            $names = [];
            foreach ($trades as $url => $name) {
                $names[] = $name . ' (' . $url . ')';
            }
            $text = trim((string) $family['intro']) . "\nMétiers : " . implode(', ', $names) . '.';
            self::addChunks($chunks, 'Les métiers — ' . $family['name'], '/metiers', 'métier', $text,
                '', 'Les métiers', $trades);
        }
    }

    /** Vrai si un index du site a bougé depuis la dernière construction. */
    private static function stale(array $data): bool
    {
        $built = (int) strtotime((string) ($data['generated_at'] ?? ''));
        foreach (self::SOURCES as $name) {
            if ((int) @filemtime(Index::path($name)) > $built) {
                return true;
            }
        }
        return false;
    }

    /** Documents indexés, ajoutés depuis le back-office. */
    public static function documents(): array
    {
        $out = [];
        foreach (glob(self::docsDir() . '/*.json') ?: [] as $file) {
            $doc = Json::read($file);
            if ($doc !== []) {
                $out[] = $doc;
            }
        }
        usort($out, static fn(array $a, array $b) => strcmp((string) $b['added_at'], (string) $a['added_at']));
        return $out;
    }

    /** Ajoute un document à la base (texte déjà extrait). */
    public static function addDocument(string $title, string $text, string $kind = 'md'): string
    {
        $id = substr(sha1($title . microtime()), 0, 12);
        Json::write(self::docsDir() . '/' . $id . '.json', [
            'id'       => $id,
            'title'    => $title,
            'kind'     => $kind,
            'text'     => Sanitizer::text($text, 200000),
            'chars'    => mb_strlen($text),
            'added_at' => date('c'),
        ]);
        self::rebuild();
        return $id;
    }

    public static function removeDocument(string $id): bool
    {
        $file = self::docsDir() . '/' . preg_replace('/[^a-z0-9]/i', '', $id) . '.json';
        $ok = Json::delete($file);
        if ($ok) {
            self::rebuild();
        }
        return $ok;
    }

    /**
     * Fragments les plus proches de la question.
     * Score simple : fréquence des mots, bonus si le mot est dans le titre.
     *
     * @return array<int, array{title:string,url:string,kind:string,text:string,score:float}>
     */
    public static function search(string $question, int $limit = 5): array
    {
        $data = Json::read(self::path());
        $chunks = $data['chunks'] ?? [];
        if ($chunks === [] || self::stale($data)) {
            self::rebuild();
            $chunks = Json::read(self::path())['chunks'] ?? [];
        }

        // Les mots de deux lettres comptent quand ils nomment quelque chose :
        // « dj », « tv », « 3d ».
        $words = array_values(array_filter(
            explode(' ', Index::haystack([$question])),
            static fn(string $w) => (mb_strlen($w) > 2 || (mb_strlen($w) === 2 && !in_array($w, self::STOP2, true)))
                && !in_array($w, self::STOP, true),
        ));
        if ($words === []) {
            return [];
        }

        // Un mot compte à partir d'un début de mot : « formation » trouve
        // « formations », mais pas « myschoolformation » ; « son » trouve
        // « sonorisation », mais pas « personne ».
        $patterns = [];
        foreach ($words as $word) {
            $patterns[$word] = '/(?<![a-z0-9])' . preg_quote($word, '/') . '/';
        }

        // Pondération IDF : un mot présent partout (« offre », « emploi », « cv »)
        // ne doit pas faire remonter la page la plus longue du site.
        $total = count($chunks);
        $idf = [];
        foreach ($patterns as $word => $pattern) {
            $documents = 0;
            foreach ($chunks as $chunk) {
                if (preg_match($pattern, (string) $chunk['haystack']) === 1) {
                    $documents++;
                }
            }
            $idf[$word] = $documents > 0 ? log(1 + ($total - $documents + 0.5) / ($documents + 0.5)) : 0.0;
        }

        $scored = [];
        foreach ($chunks as $chunk) {
            $score = 0.0;
            $found = 0;
            $haystack = (string) $chunk['haystack'];
            $title = Index::haystack([$chunk['title']]);
            // La longueur est celle du texte cité : les synonymes ajoutés pour
            // la recherche ne doivent pas faire passer un fragment pour long.
            $length = max(1, mb_strlen((string) $chunk['text']));

            foreach ($patterns as $word => $pattern) {
                $hits = preg_match_all($pattern, $haystack);
                if ($hits > 0) {
                    $found++;
                }
                $weight = $idf[$word] ?? 0.0;
                if ($weight <= 0) {
                    continue;
                }
                if ($hits > 0) {
                    // Saturation logarithmique + normalisation par la longueur :
                    // un fragment court et précis bat un long fragment vague.
                    $score += $weight * (1 + log(1 + $hits)) * (600 / ($length + 400));
                }
                if (preg_match($pattern, $title) === 1) {
                    $score += $weight * 2.0;
                }
            }
            if ($score > 0) {
                $chunk['score'] = round($score, 3);
                // Un seul mot rare touché par hasard ne fait pas une réponse :
                // la couverture dit quelle part de la question est vraiment traitée.
                $chunk['coverage'] = round($found / count($words), 3);
                $scored[] = $chunk;
            }
        }

        usort($scored, static fn(array $a, array $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }

    public static function stats(): array
    {
        $data = Json::read(self::path());
        return [
            'chunks'       => count($data['chunks'] ?? []),
            'documents'    => count(self::documents()),
            'generated_at' => (string) ($data['generated_at'] ?? ''),
        ];
    }

    /**
     * Découpe une page éditoriale à ses intertitres : chaque section devient un
     * fragment autonome, donc une réponse citable telle quelle.
     */
    private static function addSections(array &$chunks, string $title, string $url, string $html): void
    {
        // On coupe avant chaque <h2>, en gardant le titre avec son contenu.
        $parts = preg_split('#(?=<h2[^>]*>)#i', $html) ?: [$html];

        foreach ($parts as $part) {
            $heading = preg_match('#<h2[^>]*>(.*?)</h2>#is', $part, $m)
                ? trim(strip_tags($m[1]))
                : '';
            $text = Sanitizer::text($part);
            if (mb_strlen($text) < 40) {
                continue;
            }
            // Une section très longue est re-découpée par taille.
            if (mb_strlen($text) > self::CHUNK * 1.6) {
                self::addChunks($chunks, $heading !== '' ? $title . ' — ' . $heading : $title, $url, 'page', $text,
                    '', $title);
                continue;
            }
            $chunks[] = [
                'title'    => $heading !== '' ? $title . ' — ' . $heading : $title,
                'label'    => $title,
                'url'      => $url,
                'kind'     => 'page',
                'text'     => $text,
                'haystack' => Index::haystack([$title, $heading, $text]),
            ];
        }
    }

    /**
     * @param string                $aliases mots pesés par la recherche, absents du texte cité
     * @param string                $label   nom de la page, pour le lien — le titre peut être celui d'une section
     * @param array<string, string> $links   autres pages que le fragment peut citer : chemin => nom
     */
    private static function addChunks(
        array &$chunks,
        string $title,
        string $url,
        string $kind,
        string $text,
        string $aliases = '',
        string $label = '',
        array $links = [],
    ): void {
        $text = trim((string) preg_replace('/[ \t]+/', ' ', $text));
        if ($text === '') {
            return;
        }

        $length = mb_strlen($text);
        $step = self::CHUNK - self::OVERLAP;
        for ($offset = 0; $offset < $length; $offset += $step) {
            $piece = mb_substr($text, $offset, self::CHUNK);

            // On ne coupe ni en plein mot ni en plein milieu de phrase :
            // un fragment doit rester lisible tel quel s'il est cité.
            if ($offset > 0) {
                $start = self::firstBreak($piece);
                if ($start > 0 && $start < 80) {
                    $piece = mb_substr($piece, $start + 1);
                }
            }
            if ($offset + self::CHUNK < $length) {
                $cut = max(
                    mb_strrpos($piece, '. ') ?: 0,
                    mb_strrpos($piece, "\n") ?: 0,
                );
                if ($cut > self::CHUNK * 0.5) {
                    $piece = mb_substr($piece, 0, $cut + 1);
                }
            }

            $piece = trim($piece);
            if (mb_strlen($piece) < 40) {
                continue;
            }
            $chunk = [
                'title'    => $title,
                'label'    => $label !== '' ? $label : $title,
                'url'      => $url,
                'kind'     => $kind,
                'text'     => $piece,
                'haystack' => Index::haystack([$title, $piece, $aliases]),
            ];
            if ($links !== []) {
                $chunk['links'] = $links;
            }
            $chunks[] = $chunk;
            if ($length <= self::CHUNK) {
                break;
            }
        }
    }

    /** Position du premier séparateur (espace ou retour ligne), ou 0. */
    private static function firstBreak(string $text): int
    {
        $space = mb_strpos($text, ' ');
        $newline = mb_strpos($text, "\n");
        $candidates = array_filter([$space, $newline], static fn($v) => $v !== false);
        return $candidates === [] ? 0 : (int) min($candidates);
    }

    private const STOP = ['les', 'des', 'une', 'pour', 'dans', 'avec', 'sur', 'par', 'que', 'qui',
                          'est', 'sont', 'the', 'and', 'vous', 'nous', 'mon', 'mes', 'comment', 'quoi',
                          'quel', 'quelle', 'quels', 'quelles', 'etre', 'avoir', 'faut', 'peut', 'faire',
                          'elle', 'ils', 'elles', 'ton', 'tes', 'ses', 'leur', 'leurs', 'cette', 'ces'];

    /** Mots de deux lettres sans contenu ; les autres — « dj », « tv » — restent. */
    private const STOP2 = ['de', 'du', 'la', 'le', 'un', 'en', 'et', 'ou', 'au', 'ce', 'il', 'je', 'tu',
                           'on', 'ne', 'se', 'sa', 'ta', 'ma', 'me', 'te', 'es', 'si', 'ni', 'qu', 'est',
                           'to', 'of', 'in', 'is', 'an', 'at', 'on', 'my', 'do', 'be'];
}
