<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Memo;
use App\Data\Collections;
use App\Data\Index;
use App\Data\Media;
use App\Data\Names;

/**
 * Photos des quatre murs de la rubrique Interactif (planche-contact, journal, vestiaire,
 * mosaïque) : toute la médiathèque, mais seulement les photos sûres. Une photo est montrée si :
 * - son crédit est renseigné, et ce n'est ni « DR » (droits réservés : auteur inconnu), ni un
 *   crédit « à risque » (agences, presse nationale, télévision, sites web : liste réglable
 *   dans Interactif › Murs de photos), ni une date ou une légende saisie à la place du crédit
 *   (« Saison 1980-1981 », « Sochaux-Strasbourg ») ;
 * - elle illustre au moins une fiche publiée (elle a donc été relue) ;
 * - elle est assez grande (300 px sur son petit côté) et son fichier est sur le serveur ;
 * - personne ne l'a retirée des murs (case de la médiathèque).
 * Chaque photo garde le photographe ou la source, regroupés malgré les graphies (« L'est
 * républicain », « Est Républicain »…), et l'année de sa fiche quand elle est connue.
 */
final class PhotoWall
{
    /** Plus petit côté d'une photo des murs, en pixels : plus petite, elle serait floue. */
    public const MIN_SIDE = 300;
    /** Collection qui garde la liste des crédits exclus (data/collections/murs-photos.json). */
    public const COLLECTION = 'murs-photos';
    /** Largeurs de vignettes dont les murs se servent (préparées d'avance par la tâche planifiée). */
    public const WIDTHS = [160, 480];

    /**
     * Crédits « à risque » écartés d'office : agences photo, presse sportive nationale,
     * télévision, produits (Panini), instances, sites qui ne sont pas les auteurs des photos.
     * Un mot de la liste écarte tout crédit qui le contient (majuscules et accents ignorés).
     */
    public const RISKY = [
        'AFP', 'Agence France Presse', 'Reuters', 'Associated Press', 'Getty', 'Icon Sport', 'IconSport', 'Panoramic',
        'Presse Sports', 'Presse Sport', 'Maxppp', 'Sipa', 'DPPI', 'Abaca', 'Imago', 'Keystone', 'Gamma', 'Rapho',
        'Corbis', 'Roger-Viollet', 'Bestimage', 'Shutterstock', 'Alamy', 'EPA',
        'L’Équipe', 'Equipe', 'France Football', 'FF', 'Onze Mondial', 'Onze', 'But', 'Miroir des sports', 'Miroir Sprint',
        'Miroir', 'So Foot', 'Football Magazine', 'Foot Magazine', 'Kicker', 'Gazzetta', 'Paris Match',
        'Téléfoot', 'TF1', 'France 3', 'Canal+', 'beIN', 'Panini', 'LFP', 'FFF', 'UEFA', 'FIFA',
        'Reuter', 'Léquipe', 'France Foot', 'Mondial', 'Fotoball Magazine', 'L’année du football', 'L’officiel du football',
        'PhotoPQR', 'PQR', 'El Pais', 'El Mundo',
        'Wikipedia', 'Wikimedia', 'footballdatabase', 'Football database', 'Histoire du PSG', 'Musée des gardiens',
        'lemuseedesgardiensdebut', 'Musée des canaris', 'MaLigue2', 'FCSM Story', 'fcsmstory', 'Transfermarkt', 'Transfermarket',
        'Footmercato', 'OM4ever', 'Racing Stub', 'Viadeo', 'Youtube', 'Site internet', 'blog',
    ];

    /** Variantes d'un même photographe ou d'une même source (motif sur l'adresse du crédit => nom affiché). */
    private const ALIASES = [
        '#^(l-)?est-re[a-z]*$|^er$#' => 'L’Est Républicain',
        '#^(fc-)?sochaux(-montbeliard)?$|^fcsm$#' => 'FC Sochaux-Montbéliard',
        '#^(f-|francis-)?reinoso$#' => 'Francis Reinoso',
        '#^(p-|pierre-)?lorius$#' => 'Pierre Lorius',
        '#^(l-|ludovic-)?laude$#' => 'Ludovic Laude',
        '#^(l-|lionel-)vadam$#' => 'Lionel Vadam',
        '#^(r-)?claudin$#' => 'R. Claudin',
        '#^gillme$#' => 'Gillmé',
        '#^michael-desprez$#' => 'Michaël Desprez',
        '#^(c-|christian-)?lemontey$#' => 'Christian Lemontey',
        '#^(e-|eric-)?thiebaut$#' => 'Éric Thiébaut',
        '#^(c-|cedric-)?jacquot$#' => 'Cédric Jacquot',
        '#^sochaux-sprint$#' => 'Sochaux Sprint',
        '#^rouge-memoire$#' => 'Rouge Mémoire',
        '#^nice-matin$#' => 'Nice-Matin',
        '#^midi-libre$#' => 'Midi Libre',
        '#^ouest-france$#' => 'Ouest-France',
        '#^le-progres$#' => 'Le Progrès',
        '#^le-pays$#' => 'Le Pays',
        '#^le-provencal$#' => 'Le Provençal',
        '#^(l-)?est-eclaire?$#' => 'L’Est-Éclair',
        '#^collection-privee$#' => 'Collection privée',
    ];
    /** Raisons d'une photo absente des murs (filtre « murs » de la médiathèque => libellé). */
    public const REASONS = [
        'retiree' => 'retirée à la main',
        'sans-credit' => 'sans crédit',
        'dr' => 'crédit DR',
        'exclu' => 'crédit exclu',
        'sans-auteur' => 'crédit sans auteur',
        'petite' => 'trop petite',
        'non-publiee' => 'dans aucune fiche publiée',
        'absent' => 'fichier absent',
        'pas-photo' => 'pas une photo',
    ];
    private const EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    /** Fiche qui présente le mieux une photo (lien « Voir la fiche ») : un match avant un joueur, etc. */
    private const TYPE_RANK = ['match' => 0, 'personne' => 1, 'article' => 2, 'objet' => 3, 'moment' => 4, 'page' => 5];

    /** @var array{photos:list<array>,stats:array<string,int>}|null */
    private static ?array $pool = null;
    /** @var array<string,string>|null crédit exclu (forme comparable) => libellé saisi */
    private static ?array $risky = null;

    /**
     * Photos montrables, gardées en cache tant que fiches, médiathèque et liste des crédits
     * exclus ne changent pas (5 minutes au plus : publications programmées). Chaque photo :
     * r (chemin), w, h, c (crédit affiché), k (photographe ou source), cap / cap_en (légende),
     * alt / alt_en, f (fiche), y (année, 0 si inconnue), t (vignettes déjà prêtes : 160, 480).
     * @return list<array>
     */
    public static function photos(): array
    {
        return self::pool()['photos'];
    }

    /** Photos montrées (« montrees ») et écartées, par clé de REASONS (écran Interactif › Murs de photos). @return array<string,int> */
    public static function stats(): array
    {
        return self::pool()['stats'];
    }

    private static function pool(): array
    {
        return self::$pool ??= Memo::get('murs-photos', [Index::CACHE, Media::FILE, Media::USAGE, Collections::DIR . '/' . self::COLLECTION . '.json', __FILE__], '', fn () => self::build());
    }

    /** Oublie les photos gardées en mémoire (après un réglage, et pour les tests). */
    public static function forget(): void
    {
        self::$pool = null;
        self::$risky = null;
        Memo::forget();
    }

    private static function build(): array
    {
        $usage = Media::usage();
        if (!$usage && Media::all()) {
            // Usage des médias absent (cache effacé, installation neuve) : sans lui aucune photo
            // n'illustrerait de fiche publiée et les murs seraient vides. Recalculé ici (2 s environ).
            $usage = Media::rebuildUsage();
        }
        $photos = [];
        $stats = [];
        foreach (Media::all() as $rel => $m) {
            $rel = (string) $rel;
            $why = self::reason($rel, $m, $usage);
            if ($why !== null) {
                $k = self::reasonKey($why);
                $stats[$k] = ($stats[$k] ?? 0) + 1;
                continue;
            }
            [$fiche, $year] = self::fiche($usage[$rel] ?? []);
            $year = self::year((string) ($m['date_text'] ?? '')) ?: $year;
            $c = self::credit((string) $m['credit']);
            $t = 0;
            foreach (self::WIDTHS as $i => $w) {
                $t |= is_file(PUBLIC_PATH . "/media/$w/$rel.webp") ? 1 << $i : 0;
            }
            $photos[] = [
                'r' => $rel, 'w' => (int) $m['width'], 'h' => (int) $m['height'], 'c' => $c['name'], 'k' => $c['key'], 'who' => $c['who'],
                'cap' => self::text((string) ($m['caption'] ?? '')), 'cap_en' => self::text((string) ($m['caption_en'] ?? '')),
                'alt' => self::text((string) ($m['alt'] ?? '')), 'alt_en' => self::text((string) ($m['alt_en'] ?? '')),
                'f' => $fiche, 'y' => $year, 't' => $t,
            ];
        }
        $stats['montrees'] = count($photos);
        return ['photos' => $photos, 'stats' => $stats];
    }

    /** Clé de REASONS d'une raison donnée par reason() (« montrees » pour une photo des murs). */
    public static function reasonKey(?string $why): string
    {
        if ($why === null) {
            return 'montrees';
        }
        $why = str_starts_with($why, 'crédit exclu') ? 'crédit exclu' : $why;
        return (string) (array_search($why, self::REASONS, true) ?: 'introuvable');
    }

    /**
     * Pourquoi une photo n'est pas sur les murs (null : elle y est). Montré dans la médiathèque.
     * @param array<string,list<int|string>>|null $usage
     */
    public static function reason(string $rel, ?array $m = null, ?array $usage = null): ?string
    {
        $m ??= Media::get($rel);
        if (!$m) {
            return 'introuvable';
        }
        if (!in_array(strtolower(pathinfo($rel, PATHINFO_EXTENSION)), self::EXT, true)) {
            return 'pas une photo';
        }
        if (!empty($m['nowall'])) {
            return 'retirée à la main';
        }
        $credit = trim((string) ($m['credit'] ?? ''));
        if ($credit === '') {
            return 'sans crédit';
        }
        if (self::isDr($credit)) {
            return 'crédit DR';
        }
        if (($word = self::risky($credit)) !== null) {
            return 'crédit exclu (' . $word . ')';
        }
        if (self::noAuthor(self::credit($credit)['name'])) {
            return 'crédit sans auteur';
        }
        if (min((int) ($m['width'] ?? 0), (int) ($m['height'] ?? 0)) < self::MIN_SIDE) {
            return 'trop petite';
        }
        if (self::fiche(($usage ?? Media::usage())[$rel] ?? [])[0] === 0) {
            return 'dans aucune fiche publiée';
        }
        if (!is_file(Media::ORIGINALS . '/' . $rel)) {
            return 'fichier absent';
        }
        return null;
    }

    /** « DR », « D.R. », « Droits réservés », auteur inconnu : crédit sans auteur. */
    public static function isDr(string $credit): bool
    {
        $t = ' ' . implode(' ', Names::tokens($credit)) . ' ';
        return str_contains($t, ' dr ') || str_contains($t, ' d r ')
            || preg_match('/ (droits? reserves?|tous droits|auteur inconnu|inconnu|anonyme|non communique) /', $t) === 1;
    }

    /**
     * Crédit qui n'est qu'une date ou une légende, sans auteur : « Saison 1980-1981 », « Janvier
     * 1998 », « Sochaux 1997/1998 », « Auxerre-Sochaux » (un match). À lire après credit(), qui a
     * déjà gardé l'auteur d'un crédit précédé de sa légende (« Melisey (Lionel Vadam) »).
     */
    public static function noAuthor(string $name): bool
    {
        $t = ' ' . implode(' ', Names::tokens($name)) . ' ';
        return preg_match('/ (1[89]\d\d|20\d\d|saisons?|annees) /', $t) === 1
            || preg_match('/(^|[^a-z])sochaux\s*-\s*(?!montb)[a-z]|[a-z]\s*-\s*sochaux($|[^a-z])/', Names::ascii($name)) === 1;
    }

    /** Mot de la liste des crédits exclus contenu dans ce crédit (null : aucun), ou « site web ». */
    public static function risky(string $credit): ?string
    {
        $t = ' ' . implode(' ', Names::tokens($credit)) . ' ';
        foreach (self::riskyList() as $form => $label) {
            if (str_contains($t, $form)) {
                return $label;
            }
        }
        // Adresse d'un site : il n'est pas l'auteur de la photo.
        return preg_match('/\b[a-z0-9-]+\.(com|fr|eu|net|org|be|ch|info|tv)\b/i', $credit) ? 'site web' : null;
    }

    /**
     * Photos écartées par chaque crédit de la liste (écran du back-office : ce que retire chaque
     * ligne). @return array<string,int> libellé de la liste => nombre de photos
     */
    public static function excludedHits(): array
    {
        $hits = [];
        foreach (Media::all() as $rel => $m) {
            $c = trim((string) ($m['credit'] ?? ''));
            if ($c !== '' && in_array(strtolower(pathinfo((string) $rel, PATHINFO_EXTENSION)), self::EXT, true) && !self::isDr($c) && ($w = self::risky($c)) !== null) {
                $hits[$w] = ($hits[$w] ?? 0) + 1;
            }
        }
        arsort($hits);
        return $hits;
    }

    /**
     * Enregistre la liste des crédits exclus (une ligne par crédit, 500 au plus ; vide : liste de
     * départ). Écrite directement : rien à recalculer dans les données calculées du musée.
     */
    public static function saveExcluded(array $list): void
    {
        $list = array_values(array_unique(array_filter(array_map(fn ($s) => trim(mb_substr((string) $s, 0, 80)), array_slice($list, 0, 500)), fn ($s) => $s !== '' && Names::tokens($s))));
        JsonStore::write(Collections::DIR . '/' . self::COLLECTION . '.json', ['exclus' => $list ?: self::RISKY]);
        self::forget();
    }

    /** @return list<string> crédits exclus saisis dans le back-office (ou la liste de départ) */
    public static function excluded(): array
    {
        $c = Collections::get(self::COLLECTION, null);
        return is_array($c['exclus'] ?? null) ? array_values(array_filter(array_map('strval', $c['exclus']), fn ($s) => trim($s) !== '')) : self::RISKY;
    }

    /** @return array<string,string> forme comparable (« l equipe ») => libellé */
    private static function riskyList(): array
    {
        if (self::$risky === null) {
            self::$risky = [];
            foreach (self::excluded() as $label) {
                $tokens = Names::tokens($label);
                if ($tokens) {
                    self::$risky[' ' . implode(' ', $tokens) . ' '] = trim($label);
                }
            }
        }
        return self::$risky;
    }

    /**
     * Crédit à afficher et photographe ou source qui le signe (filtre « par photographe ») :
     * préfixes retirés (« Crédit photo : »), légende recopiée dans le crédit écartée
     * (« Finale 1988. L'est républicain » → « L'Est Républicain »), graphies réunies.
     * @return array{name:string,who:string,key:string}
     */
    public static function credit(string $raw): array
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', $raw));
        $s = trim((string) preg_replace('/^(?:(?:cr[ée]dits?(?:\s+photos?)?|photos?|source|by)(?!\p{L})|©|\(c\))\s*[:.\-]?\s*/iu', '', $s));
        // Auteur entre parenthèses après la légende : « Melisey (Lionel Vadam) », « Premier contrat pro (FCSM) ».
        if (preg_match('/\(([^()]{2,40})\)/u', $s, $m) && (self::isName($m[1]) || self::alias($m[1]) !== null)) {
            $s = $m[1];
        }
        // Légende avant le crédit : « Finale 1988. L'est républicain », « Sochaux-Metz 1999-2000 -L'est républicain ».
        if (preg_match('/^(.{12,})\.\s+([^.]{2,60})$/u', $s, $m) || preg_match('/^(.*\b(?:19|20)\d\d\b.*?)\s+-\s*(\p{L}[^-]{1,60})$/u', $s, $m)) {
            $s = $m[2];
        }
        $s = (string) preg_replace('/^(.{4,}?)\s*\1$/u', '$1', $s); // crédit saisi deux fois
        $s = (string) preg_replace('/^(de|par)\s+(?=\p{Lu})/u', '', $s); // « de Christian Manicourt »
        $s = (string) preg_replace('/\s+pour\s+/iu', ' / ', $s); // « C Erkul pour le Progrès »
        $s = trim($s, " \t.,;:-–/");
        // Photographe d'un journal : « L'est républicain Lionel Vadam », « Lionel Vadam L'est républicain ».
        $paper = '(?:l[\'’]\s*)?est\s+r[ée]publi\w*\.?';
        if (preg_match('/^' . $paper . '\s+(.+)$/iu', $s, $m) || preg_match('/^(.+?)\s+' . $paper . '$/iu', $s, $m)) {
            $m[1] = (string) preg_replace('/^(de|par)\s+/iu', '', trim($m[1], ' ./·-')); // « Est Républicain de Christian Manicourt »
            $who = self::isName($m[1]) ? self::canonical($m[1]) : 'L’Est Républicain';
            return ['name' => $who === 'L’Est Républicain' ? $who : $who . ' · L’Est Républicain', 'who' => $who, 'key' => Names::slug($who)];
        }
        // « Joël Le Gall / Ouest France » : le photographe, puis son journal.
        if (preg_match('#^([^/]{3,40}?)\s*/\s*([^/]{2,40})$#u', $s, $m) && (self::isName($m[1]) || preg_match('/^\p{Lu}[\p{L}\'’-]{2,}$/u', $m[1]))) {
            $who = self::canonical($m[1]);
            return ['name' => $who . ' · ' . self::canonical($m[2]), 'who' => $who, 'key' => Names::slug($who)];
        }
        $who = self::canonical($s);
        return ['name' => $who, 'who' => $who, 'key' => Names::slug($who)];
    }

    /** Un nom de personne plausible (pas une phrase de légende) : lettres, deux à cinq mots. */
    private static function isName(string $s): bool
    {
        return (bool) preg_match('/^[\p{L}][\p{L}\'’.-]*(\s+[\p{L}][\p{L}\'’.-]*){1,4}$/u', trim($s));
    }

    /** Nom d'usage d'un photographe ou d'une source (graphies réunies, majuscules rétablies). */
    private static function canonical(string $s): string
    {
        return self::alias($s) ?? (mb_strtolower($s) === $s ? mb_convert_case($s, MB_CASE_TITLE) : $s);
    }

    /** Nom d'usage quand ce crédit est une graphie connue (« l'est republicain », « fcsm »), sinon null. */
    private static function alias(string $s): ?string
    {
        $slug = Names::slug($s);
        foreach (self::ALIASES as $re => $name) {
            if (preg_match($re, $slug)) {
                return $name;
            }
        }
        return null;
    }

    /**
     * Fiche publiée qui présente le mieux la photo, et l'année qu'elle donne (match, objet,
     * moment, bilan de saison ; 0 sinon). @return array{0:int,1:int}
     */
    private static function fiche(array $uses): array
    {
        $best = null;
        $year = 0;
        foreach ($uses as $id) {
            if (!is_int($id) || !($s = Index::get($id)) || !Index::visible($s)) {
                continue;
            }
            $rank = self::TYPE_RANK[$s['type']] ?? 9;
            if ($best === null || $rank < $best[0]) {
                $best = [$rank, $id];
            }
            if (!$year) {
                $year = match ($s['type']) {
                    'match' => (int) substr((string) ($s['m']['date'] ?? ''), 0, 4),
                    'objet' => (int) ($s['o']['year'] ?? 0),
                    'moment' => (int) ($s['mo']['year'] ?? 0),
                    'article' => (int) substr((string) ($s['a']['season'] ?? ''), 0, 4),
                    default => 0,
                };
            }
        }
        return [$best[1] ?? 0, $year >= 1900 && $year <= (int) date('Y') ? $year : 0];
    }

    /** Année lue dans « Date ou époque » de la médiathèque (« Saison 1987-1988 », « Mai 1988 »). */
    private static function year(string $s): int
    {
        return preg_match('/\b(19[2-9]\d|20[0-4]\d)\b/', $s, $m) && (int) $m[1] <= (int) date('Y') ? (int) $m[1] : 0;
    }

    private static function text(string $s): string
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return mb_strlen($s) > 300 ? rtrim(mb_substr($s, 0, 297)) . '…' : $s;
    }

    // ------------------------------------------------------------------ tirage

    /**
     * Photos tirées au hasard, au plus deux d'une même fiche (une fiche riche en photos ne prend
     * pas tout le mur), de préférence parmi celles dont la vignette est prête ; « ready » : ces
     * dernières seulement (la mosaïque en affiche des centaines : aucune à fabriquer).
     * @param array{decade?:?int,who?:?string,landscape?:bool,minWidth?:int,width?:int,ready?:bool} $opt
     * @return list<array>
     */
    public static function draw(int $n, array $opt = []): array
    {
        $list = self::filter(self::photos(), $opt);
        shuffle($list);
        $bit = array_search($opt['width'] ?? 0, self::WIDTHS, true);
        if ($bit !== false && !empty($opt['ready'])) {
            $list = array_values(array_filter($list, fn ($p) => ($p['t'] >> $bit) & 1));
        } elseif ($bit !== false) {
            // Vignettes prêtes d'abord : pas d'attente pendant qu'elles se fabriquent.
            usort($list, fn ($a, $b) => (($b['t'] >> $bit) & 1) <=> (($a['t'] >> $bit) & 1));
        }
        $out = [];
        $perFiche = [];
        foreach ([2, PHP_INT_MAX] as $cap) {
            foreach ($list as $i => $p) {
                if (count($out) >= $n) {
                    break 2;
                }
                if (($perFiche[$p['f']] ?? 0) >= $cap) {
                    continue;
                }
                $perFiche[$p['f']] = ($perFiche[$p['f']] ?? 0) + 1;
                $out[] = $p;
                unset($list[$i]);
            }
        }
        shuffle($out);
        return $out;
    }

    /** @param array{decade?:?int,who?:?string,landscape?:bool,minWidth?:int} $opt */
    public static function filter(array $photos, array $opt): array
    {
        $decade = $opt['decade'] ?? null;
        $who = $opt['who'] ?? null;
        return array_values(array_filter($photos, fn ($p) => ($decade === null || ($p['y'] && intdiv($p['y'], 10) * 10 === $decade))
            && ($who === null || $p['k'] === $who)
            && (empty($opt['landscape']) || $p['w'] >= $p['h'] * 0.95)
            && $p['w'] >= ($opt['minWidth'] ?? 0)));
    }

    /**
     * Photographes et sources proposés au filtre (assez de photos pour un mur), les plus
     * représentés d'abord. @return list<array{key:string,name:string,n:int}>
     */
    public static function photographers(int $min = 8): array
    {
        $count = [];
        $name = [];
        foreach (self::photos() as $p) {
            $count[$p['k']] = ($count[$p['k']] ?? 0) + 1;
            $name[$p['k']] ??= $p['who'];
        }
        $out = [];
        foreach ($count as $k => $n) {
            if ($n >= $min && $k !== '') {
                $out[] = ['key' => (string) $k, 'name' => $name[$k], 'n' => $n];
            }
        }
        usort($out, fn ($a, $b) => $b['n'] <=> $a['n'] ?: strcoll($a['name'], $b['name']));
        return $out;
    }

    /** Code court et stable d'une photo (export PDF d'un tirage : la page envoie les codes des photos montrées). */
    public static function code(string $rel): string
    {
        return substr(md5($rel), 0, 10);
    }

    /**
     * Photos des murs d'après leurs codes, dans l'ordre donné ; un code inconnu (photo retirée des
     * murs entre-temps, code inventé) est ignoré. @param list<string> $codes @return list<array>
     */
    public static function byCodes(array $codes): array
    {
        $map = [];
        foreach (self::photos() as $p) {
            $map[self::code($p['r'])] = $p;
        }
        $out = [];
        foreach ($codes as $c) {
            if (isset($map[$c])) {
                $out[] = $map[$c];
            }
        }
        return $out;
    }

    /** Décennies proposées au filtre (assez de photos datées). @return array<int,int> décennie => nombre */
    public static function decades(int $min = 12): array
    {
        $out = [];
        foreach (self::photos() as $p) {
            if ($p['y']) {
                $d = intdiv($p['y'], 10) * 10;
                $out[$d] = ($out[$d] ?? 0) + 1;
            }
        }
        ksort($out);
        return array_filter($out, fn ($n) => $n >= $min);
    }

    /**
     * Photos de chaque décennie et de chaque photographe proposés, une fois l'autre filtre choisi :
     * les choix qui n'en ont aucune sont grisés. @return array{decades:array<int,int>,who:array<string,int>}
     */
    public static function counts(?int $decade, ?string $who): array
    {
        $d = array_fill_keys(array_keys(self::decades()), 0);
        $w = array_fill_keys(array_column(self::photographers(), 'key'), 0);
        foreach (self::photos() as $p) {
            $pd = $p['y'] ? intdiv($p['y'], 10) * 10 : null;
            if ($pd !== null && isset($d[$pd]) && ($who === null || $p['k'] === $who)) {
                $d[$pd]++;
            }
            if (isset($w[$p['k']]) && ($decade === null || $pd === $decade)) {
                $w[$p['k']]++;
            }
        }
        return ['decades' => $d, 'who' => $w];
    }

    // ------------------------------------------------------------------ vignettes d'avance

    /**
     * Prépare les vignettes des murs qui manquent encore (tâche planifiée), dans la limite de
     * temps donnée : la première visite d'un mur n'attend pas leur fabrication.
     * @return array{done:int,left:int}
     */
    public static function prepare(float $seconds = 60.0): array
    {
        $t0 = microtime(true);
        $done = 0;
        $left = 0;
        foreach (self::photos() as $p) {
            foreach (self::WIDTHS as $w) {
                if (is_file(PUBLIC_PATH . "/media/$w/{$p['r']}.webp")) {
                    continue;
                }
                if (microtime(true) - $t0 > $seconds) {
                    $left++;
                    continue;
                }
                $done += Images::derivative($p['r'], $w) !== null ? 1 : 0;
            }
        }
        return ['done' => $done, 'left' => $left];
    }
}
