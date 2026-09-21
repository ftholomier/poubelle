<?php
declare(strict_types=1);

namespace Bin;

/** Utilitaires de conversion WordPress -> modèle intermittent.fr. */
final class Wp
{
    /** Titre de brouillon fait de 10 lettres minuscules = spam d'octobre 2025. */
    public static function isSpamTitle(string $title): bool
    {
        return (bool) preg_match('/^[a-z]{10}$/', trim($title));
    }

    /** Nettoie le HTML WordPress en conservant une mise en forme simple. */
    public static function cleanHtml(string $html): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = preg_replace('/<!--\s*\/?wp:[^>]*-->/', '', $html) ?? $html;
        $html = preg_replace('/\[\/?[a-z_]+[^\]]*\]/i', '', $html) ?? $html;   // shortcodes
        $html = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $html);
        $html = strip_tags($html, '<p><br><strong><em><ul><ol><li><h2><h3><h4><a><blockquote>');
        $html = preg_replace('/\n{3,}/', "\n\n", $html) ?? $html;
        return trim($html);
    }

    /** Texte brut, pour les extraits et l'index de recherche. */
    public static function toText(string $html): string
    {
        $text = self::cleanHtml($html);
        $text = preg_replace('/<\/(p|h[2-4]|li|blockquote)>/', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        return trim((string) preg_replace('/\n{3,}/', "\n\n", $text));
    }

    /** Transforme un texte en liste de puces (section « Profil recherché »). */
    public static function toBullets(string $text): array
    {
        $out = [];
        foreach (preg_split('/\n+/', self::toText($text)) ?: [] as $line) {
            $line = trim(ltrim(trim($line), "-–—•*·\t "));
            if ($line !== '' && mb_strlen($line) > 2) {
                $out[] = $line;
            }
        }
        return $out;
    }

    /** Désérialise une méta WordPress sans jamais lever d'erreur. */
    public static function unserialize(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }
        $data = @unserialize($value, ['allowed_classes' => false]);
        return is_array($data) ? $data : [];
    }

    /** Première entrée d'une méta de type `a:1:{i:0;s:N:"...";}`. */
    public static function firstOf(mixed $value): string
    {
        $data = self::unserialize($value);
        $first = reset($data);
        return is_string($first) ? $first : (is_string($value) ? $value : '');
    }

    public static function date(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '' || str_starts_with($raw, '0000')) {
            return '';
        }
        $ts = strtotime($raw);
        return $ts === false ? '' : date('c', $ts);
    }

    public static function bool(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on'], true);
    }

    public static function email(mixed $value): string
    {
        $raw = trim((string) $value);
        return filter_var($raw, FILTER_VALIDATE_EMAIL) ? strtolower($raw) : '';
    }

    public static function url(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . ltrim($raw, '/');
        }
        return filter_var($raw, FILTER_VALIDATE_URL) ? $raw : '';
    }

    /** Les 18 régions, par code interne. */
    private const REGIONS = [
        'IDF' => 'Île-de-France',            'ARA' => 'Auvergne-Rhône-Alpes',
        'PAC' => "Provence-Alpes-Côte d'Azur", 'OCC' => 'Occitanie',
        'NAQ' => 'Nouvelle-Aquitaine',       'HDF' => 'Hauts-de-France',
        'GES' => 'Grand Est',                'PDL' => 'Pays de la Loire',
        'BRE' => 'Bretagne',                 'NOR' => 'Normandie',
        'BFC' => 'Bourgogne-Franche-Comté',  'CVL' => 'Centre-Val de Loire',
        'COR' => 'Corse',                    'GUA' => 'Guadeloupe',
        'MTQ' => 'Martinique',               'GUF' => 'Guyane',
        'REU' => 'La Réunion',               'MAY' => 'Mayotte',
    ];

    /** Département (code INSEE) -> région. Couvre codes postaux et « (73) ». */
    private const DEPARTMENTS = [
        '01'=>'ARA','02'=>'HDF','03'=>'ARA','04'=>'PAC','05'=>'PAC','06'=>'PAC','07'=>'ARA','08'=>'GES',
        '09'=>'OCC','10'=>'GES','11'=>'OCC','12'=>'OCC','13'=>'PAC','14'=>'NOR','15'=>'ARA','16'=>'NAQ',
        '17'=>'NAQ','18'=>'CVL','19'=>'NAQ','20'=>'COR','2A'=>'COR','2B'=>'COR','21'=>'BFC','22'=>'BRE',
        '23'=>'NAQ','24'=>'NAQ','25'=>'BFC','26'=>'ARA','27'=>'NOR','28'=>'CVL','29'=>'BRE','30'=>'OCC',
        '31'=>'OCC','32'=>'OCC','33'=>'NAQ','34'=>'OCC','35'=>'BRE','36'=>'CVL','37'=>'CVL','38'=>'ARA',
        '39'=>'BFC','40'=>'NAQ','41'=>'CVL','42'=>'ARA','43'=>'ARA','44'=>'PDL','45'=>'CVL','46'=>'OCC',
        '47'=>'NAQ','48'=>'OCC','49'=>'PDL','50'=>'NOR','51'=>'GES','52'=>'GES','53'=>'PDL','54'=>'GES',
        '55'=>'GES','56'=>'BRE','57'=>'GES','58'=>'BFC','59'=>'HDF','60'=>'HDF','61'=>'NOR','62'=>'HDF',
        '63'=>'ARA','64'=>'NAQ','65'=>'OCC','66'=>'OCC','67'=>'GES','68'=>'GES','69'=>'ARA','70'=>'BFC',
        '71'=>'BFC','72'=>'PDL','73'=>'ARA','74'=>'ARA','75'=>'IDF','76'=>'NOR','77'=>'IDF','78'=>'IDF',
        '79'=>'NAQ','80'=>'HDF','81'=>'OCC','82'=>'OCC','83'=>'PAC','84'=>'PAC','85'=>'PDL','86'=>'NAQ',
        '87'=>'NAQ','88'=>'GES','89'=>'BFC','90'=>'BFC','91'=>'IDF','92'=>'IDF','93'=>'IDF','94'=>'IDF',
        '95'=>'IDF','971'=>'GUA','972'=>'MTQ','973'=>'GUF','974'=>'REU','976'=>'MAY',
    ];

    /**
     * Libellés reconnus -> région. Couvre les régions actuelles, celles d'avant
     * 2016 (les CV du site datent de 2005 à 2026), les sigles et les départements
     * les plus cités. Clés déjà normalisées (minuscules, sans accent).
     */
    private const ALIASES = [
        'ile de france'=>'IDF','idf'=>'IDF','region parisienne'=>'IDF','paris'=>'IDF','seine saint denis'=>'IDF',
        'hauts de seine'=>'IDF','val de marne'=>'IDF','val d oise'=>'IDF','seine et marne'=>'IDF','yvelines'=>'IDF','essonne'=>'IDF',
        'auvergne rhone alpes'=>'ARA','rhone alpes'=>'ARA','auvergne'=>'ARA','savoie'=>'ARA','haute savoie'=>'ARA',
        'isere'=>'ARA','ardeche'=>'ARA','drome'=>'ARA','lyon'=>'ARA','grenoble'=>'ARA','puy de dome'=>'ARA',
        'provence alpes cote d azur'=>'PAC','paca'=>'PAC','provence'=>'PAC','cote d azur'=>'PAC','bouches du rhone'=>'PAC',
        'var'=>'PAC','vaucluse'=>'PAC','alpes maritimes'=>'PAC','marseille'=>'PAC','nice'=>'PAC',
        'occitanie'=>'OCC','languedoc roussillon'=>'OCC','languedoc'=>'OCC','midi pyrenees'=>'OCC','herault'=>'OCC',
        'gard'=>'OCC','haute garonne'=>'OCC','hautes pyrenees'=>'OCC','aude'=>'OCC','toulouse'=>'OCC','montpellier'=>'OCC','gers'=>'OCC',
        'nouvelle aquitaine'=>'NAQ','aquitaine'=>'NAQ','limousin'=>'NAQ','poitou charentes'=>'NAQ','gironde'=>'NAQ',
        'dordogne'=>'NAQ','charente'=>'NAQ','landes'=>'NAQ','bordeaux'=>'NAQ','limoges'=>'NAQ','pyrenees atlantiques'=>'NAQ',
        'hauts de france'=>'HDF','nord pas de calais'=>'HDF','picardie'=>'HDF','nord'=>'HDF','pas de calais'=>'HDF',
        'somme'=>'HDF','oise'=>'HDF','aisne'=>'HDF','lille'=>'HDF',
        'grand est'=>'GES','alsace'=>'GES','lorraine'=>'GES','champagne ardenne'=>'GES','moselle'=>'GES',
        'bas rhin'=>'GES','haut rhin'=>'GES','meurthe et moselle'=>'GES','marne'=>'GES','strasbourg'=>'GES','nancy'=>'GES',
        'pays de la loire'=>'PDL','loire atlantique'=>'PDL','maine et loire'=>'PDL','vendee'=>'PDL','sarthe'=>'PDL',
        'mayenne'=>'PDL','nantes'=>'PDL','angers'=>'PDL',
        'bretagne'=>'BRE','ille et vilaine'=>'BRE','finistere'=>'BRE','morbihan'=>'BRE','cotes d armor'=>'BRE',
        'rennes'=>'BRE','brest'=>'BRE',
        'normandie'=>'NOR','basse normandie'=>'NOR','haute normandie'=>'NOR','calvados'=>'NOR','manche'=>'NOR',
        'seine maritime'=>'NOR','eure'=>'NOR','orne'=>'NOR','rouen'=>'NOR','caen'=>'NOR',
        'bourgogne franche comte'=>'BFC','bourgogne'=>'BFC','franche comte'=>'BFC','cote d or'=>'BFC','doubs'=>'BFC',
        'saone et loire'=>'BFC','yonne'=>'BFC','jura'=>'BFC','nievre'=>'BFC','dijon'=>'BFC','besancon'=>'BFC','belfort'=>'BFC',
        'centre val de loire'=>'CVL','centre'=>'CVL','indre et loire'=>'CVL','loiret'=>'CVL','loir et cher'=>'CVL',
        'eure et loir'=>'CVL','cher'=>'CVL','indre'=>'CVL','tours'=>'CVL','orleans'=>'CVL',
        'corse'=>'COR','corse du sud'=>'COR','haute corse'=>'COR','ajaccio'=>'COR','bastia'=>'COR',
        'guadeloupe'=>'GUA','martinique'=>'MTQ','guyane'=>'GUF','la reunion'=>'REU','reunion'=>'REU','mayotte'=>'MAY',
        // Villes principales, pour les fiches qui ne mentionnent ni région ni code postal.
        'cergy'=>'IDF','versailles'=>'IDF','montreuil'=>'IDF','saint denis'=>'IDF','boulogne'=>'IDF',
        'nanterre'=>'IDF','creteil'=>'IDF','vincennes'=>'IDF','ivry'=>'IDF','pantin'=>'IDF','malakoff'=>'IDF',
        'montrouge'=>'IDF','aubervilliers'=>'IDF','levallois'=>'IDF','issy'=>'IDF','courbevoie'=>'IDF',
        'argenteuil'=>'IDF','melun'=>'IDF','evry'=>'IDF','meaux'=>'IDF','saint ouen'=>'IDF','bagnolet'=>'IDF',
        'annecy'=>'ARA','chambery'=>'ARA','valence'=>'ARA','clermont ferrand'=>'ARA','villeurbanne'=>'ARA',
        'saint etienne'=>'ARA','bourg en bresse'=>'ARA','vienne'=>'ARA','aubenas'=>'ARA',
        'avignon'=>'PAC','cannes'=>'PAC','antibes'=>'PAC','arles'=>'PAC','toulon'=>'PAC','aix en provence'=>'PAC',
        'gap'=>'PAC','martigues'=>'PAC','aubagne'=>'PAC',
        'nimes'=>'OCC','perpignan'=>'OCC','beziers'=>'OCC','narbonne'=>'OCC','albi'=>'OCC','carcassonne'=>'OCC',
        'tarbes'=>'OCC','rodez'=>'OCC','sete'=>'OCC','auch'=>'OCC','montauban'=>'OCC',
        'pau'=>'NAQ','la rochelle'=>'NAQ','angouleme'=>'NAQ','bayonne'=>'NAQ','niort'=>'NAQ','perigueux'=>'NAQ',
        'agen'=>'NAQ','biarritz'=>'NAQ','poitiers'=>'NAQ','brive'=>'NAQ',
        'roubaix'=>'HDF','tourcoing'=>'HDF','dunkerque'=>'HDF','calais'=>'HDF','arras'=>'HDF','beauvais'=>'HDF',
        'compiegne'=>'HDF','valenciennes'=>'HDF','amiens'=>'HDF','douai'=>'HDF',
        'reims'=>'GES','metz'=>'GES','mulhouse'=>'GES','colmar'=>'GES','troyes'=>'GES','epinal'=>'GES',
        'charleville'=>'GES','thionville'=>'GES','verdun'=>'GES',
        'le mans'=>'PDL','saint nazaire'=>'PDL','laval'=>'PDL','cholet'=>'PDL','la roche sur yon'=>'PDL',
        'les sables d olonne'=>'PDL','saumur'=>'PDL',
        'quimper'=>'BRE','lorient'=>'BRE','vannes'=>'BRE','saint malo'=>'BRE','saint brieuc'=>'BRE',
        'lannion'=>'BRE','pleumeur bodou'=>'BRE','concarneau'=>'BRE',
        'le havre'=>'NOR','cherbourg'=>'NOR','evreux'=>'NOR','alencon'=>'NOR','dieppe'=>'NOR','lisieux'=>'NOR',
        'chalon'=>'BFC','nevers'=>'BFC','auxerre'=>'BFC','macon'=>'BFC','montbeliard'=>'BFC','sens'=>'BFC',
        'le creusot'=>'BFC','montceau les mines'=>'BFC',
        'bourges'=>'CVL','blois'=>'CVL','chartres'=>'CVL','chateauroux'=>'CVL','montlouis sur loire'=>'CVL',
        'vierzon'=>'CVL','dreux'=>'CVL',
        'porto vecchio'=>'COR','corte'=>'COR','calvi'=>'COR',
    ];

    /** Détectés pour ne pas les ranger de force dans une région française. */
    private const ABROAD = [
        'belgique'=>'BE','belgium'=>'BE','bruxelles'=>'BE','suisse'=>'CH','switzerland'=>'CH','geneve'=>'CH',
        'luxembourg'=>'LU','allemagne'=>'DE','germany'=>'DE','berlin'=>'DE','espagne'=>'ES','spain'=>'ES',
        'barcelone'=>'ES','madrid'=>'ES','italie'=>'IT','italy'=>'IT','rome'=>'IT','milan'=>'IT',
        'royaume uni'=>'GB','angleterre'=>'GB','london'=>'GB','londres'=>'GB','canada'=>'CA','montreal'=>'CA',
        'quebec'=>'CA','maroc'=>'MA','morocco'=>'MA','casablanca'=>'MA','tunisie'=>'TN','tunis'=>'TN',
        'algerie'=>'DZ','senegal'=>'SN','liban'=>'LB','beyrouth'=>'LB','beirut'=>'LB','portugal'=>'PT',
        'lisbonne'=>'PT','pays bas'=>'NL','amsterdam'=>'NL','etats unis'=>'US','usa'=>'US','new york'=>'US',
    ];

    /** Minuscules, sans accent, séparateurs ramenés à l'espace. */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','í'=>'i','ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
            'ÿ'=>'y','ñ'=>'n','œ'=>'oe','æ'=>'ae',
        ]);
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Extrait ville, région et pays d'un champ libre.
     * Gère « FRANCE PARIS », « ILE DE FRANCE », « Savoie (73) », « 75011 Paris »,
     * « Region PACA », « Limousin ( Nouvelle Aquitaine) », « Belgique ».
     *
     * @return array{city:string,region:string,country:string}
     */
    public static function parseLocation(string $raw): array
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $raw));
        if ($clean === '') {
            return ['city' => '', 'region' => '', 'country' => ''];
        }

        $flat = self::normalize($clean);
        $padded = ' ' . $flat . ' ';

        // 1. Hors de France : on n'invente pas de région.
        foreach (self::ABROAD as $needle => $code) {
            if (str_contains($padded, ' ' . $needle . ' ')) {
                return ['city' => self::prettyCity($clean), 'region' => '', 'country' => $code];
            }
        }

        // 2. Libellé reconnu. Le plus long gagne : « ile de france » avant « france ».
        $best = '';
        foreach (self::ALIASES as $needle => $code) {
            if (strlen($needle) > strlen($best) && str_contains($padded, ' ' . $needle . ' ')) {
                $best = $needle;
            }
        }
        if ($best !== '') {
            return [
                'city'    => self::prettyCity($clean),
                'region'  => self::REGIONS[self::ALIASES[$best]],
                'country' => 'FR',
            ];
        }

        // 3. Code postal ou numéro de département entre parenthèses.
        $code = '';
        if (preg_match('/\b(\d{5})\b/', $clean, $m)) {
            $code = str_starts_with($m[1], '97') ? substr($m[1], 0, 3) : substr($m[1], 0, 2);
        } elseif (preg_match('/\(\s*(2[ab]|\d{2,3})\s*\)/i', $clean, $m)) {
            $code = strtoupper($m[1]);
        } elseif (preg_match('/\b(2[ab]|\d{2})\b\s*$/i', $clean, $m)) {
            $code = strtoupper($m[1]);
        }
        if ($code !== '' && isset(self::DEPARTMENTS[$code])) {
            return [
                'city'    => self::prettyCity($clean),
                'region'  => self::REGIONS[self::DEPARTMENTS[$code]],
                'country' => 'FR',
            ];
        }

        return ['city' => self::prettyCity($clean), 'region' => '', 'country' => ''];
    }

    /** « FRANCE PARIS » -> « Paris » ; « Noisy-le-Sec (93) » -> « Noisy-le-Sec ». */
    public static function prettyCity(string $raw): string
    {
        $clean = $raw;
        $clean = (string) preg_replace('/\b(france|fra|fr|région|region|dept\.?|département)\b/iu', ' ', $clean);
        $clean = (string) preg_replace('/\(\s*[^)]*\s*\)/', ' ', $clean);   // « (73) », « ( Nouvelle Aquitaine) »
        $clean = (string) preg_replace('/\b\d{2,5}\b/', ' ', $clean);
        // Séparateurs devenus orphelins après les retraits : « Noisy-le-Sec , , » -> « Noisy-le-Sec »
        $clean = (string) preg_replace('/(\s*[,;\/]\s*){2,}/u', ', ', $clean);
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean), " ,;-/\t");
        if ($clean === '') {
            return '';
        }
        if (mb_strtoupper($clean) === $clean || mb_strtolower($clean) === $clean) {
            $clean = mb_convert_case(mb_strtolower($clean), MB_CASE_TITLE, 'UTF-8');
        }
        return mb_substr($clean, 0, 70);
    }

    /**
     * Découpe une liste de compétences saisie librement.
     * Les séparateurs observés sur le site : virgule, point-virgule, barre oblique,
     * retour à la ligne, puce.
     *
     * @return string[]
     */
    public static function splitSkills(string $raw): array
    {
        $raw = (string) preg_replace('/\((?:[^)]*)\)/u', ' ', $raw);   // « (Vérification VI, …) »
        $parts = preg_split('#[,;/\n•·|]+#u', $raw) ?: [];
        $out = [];

        foreach ($parts as $part) {
            $skill = trim($part, " \t\n\r.-–—:");
            $skill = (string) preg_replace('/\s+/u', ' ', $skill);
            if ($skill === '' || mb_strlen($skill) < 2 || mb_strlen($skill) > 48) {
                continue;
            }
            $skill = trim((string) preg_replace('/\\s+(etc|and|autres)\\.?$/iu', '', $skill));
            if ($skill === '' || in_array(mb_strtolower($skill), ['etc', 'etc.', 'and', 'et', 'ou', 'autres'], true)) {
                continue;
            }
            // Une phrase n'est pas une compétence.
            if (str_word_count($skill, 0, 'àâäéèêëîïôöùûüçÀÂÄÉÈÊËÎÏÔÖÙÛÜÇ-') > 6) {
                continue;
            }
            $out[mb_strtolower($skill)] = $skill;
        }
        return array_values($out);
    }

    /** Estime des années d'expérience à partir du texte libre du CV. */
    public static function guessYears(string $text): int
    {
        if (preg_match('/(\d{1,2})\s*(?:ans|années|years)\b/iu', $text, $m)) {
            return min(50, (int) $m[1]);
        }
        if (preg_match('/\bdepuis\s+(19|20)(\d{2})\b/iu', $text, $m)) {
            return max(0, min(50, (int) date('Y') - (int) ($m[1] . $m[2])));
        }
        return 0;
    }
}
