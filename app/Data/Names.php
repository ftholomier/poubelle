<?php
declare(strict_types=1);

namespace App\Data;

/** Normalisation des noms (clubs, stades, personnes) pour les rapprochements automatiques. */
final class Names
{
    public static function ascii(string $s): string
    {
        if (str_contains($s, '&')) {
            $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        // Texte en ASCII : rien à translittérer.
        if (!preg_match('/[^\x00-\x7F]/', $s)) {
            return strtolower($s);
        }
        // Lettres latines, ponctuation, symboles et émojis : table de correspondance tirée d'ICU
        // (même résultat, sans les 10 ms de démarrage d'ICU, quinze à vingt-cinq fois plus vite) ;
        // autres écritures et signes combinants : ICU lui-même.
        if (($map = self::foldMap()) && preg_match_all('/[^\x00-\x7F]/u', $s, $m)) {
            $sub = [];
            foreach ($m[0] as $c) {
                if (!isset($sub[$c])) {
                    // Hors table (autre écriture, signe combinant : é écrit e + ´, ❤️, 1️⃣) : ICU, seul exact.
                    if (!isset($map[$c])) {
                        return self::icu($s);
                    }
                    $sub[$c] = $map[$c];
                }
            }
            return strtolower(strtr($s, $sub));
        }
        return self::icu($s);
    }

    /**
     * Caractères de la table, sans contexte pour ICU : latin (accents, ligatures), lettres
     * modificatives, ponctuation, exposants, monnaies, symboles, flèches, pictogrammes, lettres
     * mathématiques (« 𝗴𝗿𝗮𝘀 » des réseaux sociaux), sélecteurs de variante, émojis. Les accents
     * « combinants » (é écrit e + ´) n'y sont pas : ICU ne les retire que derrière une lettre.
     */
    private const FOLD_RANGES = [[0x80, 0x24F], [0x2B0, 0x2FF], [0x1D00, 0x1DBF], [0x1E00, 0x1EFF], [0x2000, 0x20CF],
        [0x2100, 0x27BF], [0x2900, 0x2BFF], [0xFE00, 0xFE0F], [0x1D400, 0x1D7FF], [0x1F000, 0x1FAFF]];
    private const FOLD_CACHE = STORAGE_PATH . '/cache/ascii-fold.php';

    private static function icu(string $s): string
    {
        static $tr = null;
        // Translittérateur créé une seule fois (le recréer à chaque appel coûte cher).
        $tr ??= \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        $t = $tr ? $tr->transliterate($s) : false;
        return $t === false ? mb_strtolower($s) : $t;
    }

    /**
     * Table caractère => ASCII minuscule, calculée une fois par ICU pour les caractères de
     * FOLD_RANGES (gardée en cache, refaite si la version d'ICU ou les plages changent). Null sans ICU.
     */
    private static function foldMap(): ?array
    {
        static $map = null;
        if ($map !== null) {
            return $map ?: null;
        }
        $ver = (defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : '') . ':' . md5(json_encode(self::FOLD_RANGES) . ':latin+common');
        $c = is_file(self::FOLD_CACHE) ? @include self::FOLD_CACHE : null;
        if (is_array($c) && ($c['icu'] ?? null) === $ver && is_array($c['map'] ?? null)) {
            return $map = $c['map'];
        }
        $map = [];
        if (!class_exists(\Transliterator::class) || !\Transliterator::create('Any-Latin; Latin-ASCII; Lower()')) {
            return null;
        }
        foreach (self::FOLD_RANGES as [$a, $b]) {
            for ($cp = $a; $cp <= $b; $cp++) {
                $ch = mb_chr($cp, 'UTF-8');
                // Seulement l'écriture latine et les caractères communs à toutes les écritures : une
                // lettre grecque ou bopomofo (ᵝ, ˪) change la ponctuation voisine pour ICU (« ; » → « ? »).
                // Marques combinantes (⃣ du « 1️⃣ ») : dépendent de ce qui précède, laissées à ICU.
                if ($ch !== false && in_array(\IntlChar::getIntPropertyValue($cp, \IntlChar::PROPERTY_SCRIPT), [0, 25], true) && !preg_match('/^\p{M}$/u', $ch)) {
                    $map[$ch] = self::icu($ch);
                }
            }
        }
        try {
            \App\Core\PhpCache::write(self::FOLD_CACHE, ['icu' => $ver, 'map' => $map]);
        } catch (\Throwable) {
            // cache facultatif
        }
        return $map;
    }

    /** @return list<string> */
    public static function tokens(string $s): array
    {
        return array_values(array_filter(preg_split('/[^a-z0-9]+/', self::ascii($s)) ?: [], fn ($t) => $t !== ''));
    }

    public static function slug(string $s): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', self::ascii($s)), '-');
    }

    /** Petits mots et sigles juridiques ignorés pour comparer des clubs. */
    private const CLUB_STOP = ['fc', 'as', 'us', 'sc', 'rc', 'ac', 'ca', 'sco', 'ogc', 'aj', 'ea', 'cs', 'sa', 'js', 'sm', 'la', 'le', 'les',
        'de', 'du', 'd', 'l', 'et', 'en', 'association', 'sportive', 'club', 'football', 'foot',
        'fk', 'sk', 'sv', 'vfb', 'vfl', 'tsv', 'cf', 'cd', 'ud', 'sd', 'afc', 'bsc', 'ssc', 'ss',
        'calcio', 'cfc', 'f', 'c', 's', 'a', 'u', 'r', 'o'];

    /** Alias connus (sigles et surnoms) → nom canonique. */
    private const CLUB_ALIASES = [
        'psg' => 'paris saint germain', 'paris sg' => 'paris saint germain', 'paris st germain' => 'paris saint germain',
        'om' => 'marseille', 'ol' => 'lyon', 'asse' => 'saint etienne', 'st etienne' => 'saint etienne', 'as st etienne' => 'saint etienne',
        'as saint etienne' => 'saint etienne', 'losc' => 'lille', 'lille osc' => 'lille', 'losc lille' => 'lille', 'lille losc' => 'lille',
        'fc metz' => 'metz', 'fc nantes' => 'nantes', 'rc lens' => 'lens', 'aj auxerre' => 'auxerre', 'sm caen' => 'caen', 'stade malherbe caen' => 'caen',
        'toulouse fc' => 'toulouse', 'montpellier hsc' => 'montpellier', 'montpellier herault' => 'montpellier', 'ogc nice' => 'nice',
        'ea guingamp' => 'guingamp', 'en avant guingamp' => 'guingamp', 'fc lorient' => 'lorient', 'as cannes' => 'cannes', 'sc bastia' => 'bastia',
        'le havre ac' => 'le havre', 'hac' => 'le havre', 'havre ac' => 'le havre', 'estac' => 'troyes', 'es troyes ac' => 'troyes', 'troyes ac' => 'troyes',
        'dijon fco' => 'dijon', 'fc mulhouse' => 'mulhouse', 'chamois niortais' => 'niort', 'stade de reims' => 'reims', 'fc gueugnon' => 'gueugnon',
        'lb chateauroux' => 'chateauroux', 'la berrichonne' => 'chateauroux', 'fc grenoble' => 'grenoble', 'grenoble foot 38' => 'grenoble',
        'olympique ales' => 'ales', 'ales' => 'ales', 'rc strasbourg' => 'strasbourg', 'racing club de strasbourg' => 'strasbourg',
        'stade rennais fc' => 'rennes', 'paris fc' => 'paris fc', 'racing club de paris' => 'racing paris', 'racing paris' => 'racing paris',
        'matra racing' => 'racing paris', 'fc rouen' => 'rouen', 'us orleans' => 'orleans', 'orleans' => 'orleans', 'amiens sc' => 'amiens',
        'clermont foot' => 'clermont', 'clermont foot 63' => 'clermont', 'pau fc' => 'pau', 'fc annecy' => 'annecy', 'quevilly rouen' => 'quevilly rouen',
        'tfc' => 'toulouse', 'fcn' => 'nantes', 'rcl' => 'lens', 'rcs' => 'strasbourg', 'racing strasbourg' => 'strasbourg',
        'girondins de bordeaux' => 'bordeaux', 'fc girondins de bordeaux' => 'bordeaux', 'f c girondins de bordeaux' => 'bordeaux',
        'stade rennais' => 'rennes', 'stade de reims' => 'reims', 'stade lavallois' => 'laval', 'stade brestois' => 'brest',
        'nancy lorraine' => 'nancy', 'as nancy lorraine' => 'nancy', 'as nancy' => 'nancy', 'fc nancy' => 'nancy',
        'olympique lyonnais' => 'lyon', 'olympique de marseille' => 'marseille', 'as monaco' => 'monaco',
        'fc sochaux' => 'sochaux', 'fc sochaux montbeliard' => 'sochaux', 'fcsm' => 'sochaux',
        'besancon rc' => 'besancon', 'rcfc besancon' => 'besancon', 'racing besancon' => 'besancon',
        'red star' => 'red star', 'red star 93' => 'red star', 'gazelec ajaccio' => 'gazelec ajaccio', 'gfc ajaccio' => 'gazelec ajaccio',
        'ac ajaccio' => 'ajaccio', 'ajaccio' => 'ajaccio', 'young boys de berne' => 'young boys', 'bsc young boys' => 'young boys',
    ];

    /** Clé de rapprochement d'un club (« FC Metz » ≡ « Metz »). */
    public static function clubKey(string $name): string
    {
        $n = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $name));
        $a = trim(preg_replace('/[^a-z0-9]+/', ' ', self::ascii($n)));
        if (isset(self::CLUB_ALIASES[$a])) {
            $a = self::CLUB_ALIASES[$a];
        }
        // Gazélec / AC Ajaccio et Red Star doivent rester distincts : on garde le nom complet s'il est connu.
        if (in_array($a, ['gazelec ajaccio', 'red star', 'young boys', 'paris saint germain', 'paris fc', 'racing paris', 'quevilly rouen', 'le havre', 'saint etienne'], true)) {
            return str_replace(' ', '-', $a);
        }
        $tok = array_values(array_filter(explode(' ', $a), fn ($t) => $t !== '' && !in_array($t, self::CLUB_STOP, true)));
        if (!$tok) {
            $tok = explode(' ', $a);
        }
        return implode('-', $tok);
    }

    /** Clé de rapprochement d'un stade (« Stade Auguste Bonal » ≡ « Stade Bonal » ≡ « Auguste-Bonal (25) »). */
    public static function stadiumKey(string $name): string
    {
        $n = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $name));
        $tok = array_values(array_filter(self::tokens($n), fn ($t) => !in_array($t, ['stade', 'stadium', 'parc', 'des', 'de', 'du', 'la', 'le', 'l', 'd', 'municipal', 'estadio', 'stadion', 'complexe', 'sportif', 'terrain'], true)));
        $key = implode('-', $tok);
        return match (true) {
            str_contains($key, 'bonal') => 'auguste-bonal',
            default => $key ?: self::slug($n),
        };
    }

    /** Ensemble de mots d'un nom de personne (ordre indifférent : « JEANNIN Mehdi » ≡ « Mehdi Jeannin »). */
    public static function personKey(string $name): string
    {
        $n = preg_replace('/\((c|cap\.?|capitaine|g|a l.essai)\)/iu', '', $name);
        $tok = self::tokens($n);
        sort($tok);
        return implode(' ', $tok);
    }

    /**
     * Lettres d'un nom, triées : insensible à l'ordre des mots, aux espaces, apostrophes et
     * traits d'union (« Rassoul N'Diaye » ≡ « NDIAYE Rassoul », « Petit-Jean » ≡ « Petitjean »).
     */
    public static function letterKey(string $name): string
    {
        $n = preg_replace('/\((c|cap\.?|capitaine|g|a l.essai)\)/iu', '', $name);
        $chars = str_split(implode('', self::tokens((string) $n)));
        sort($chars);
        return implode('', $chars);
    }

    /** Nom de famille probable d'une ligne de composition (« WEISSBECK Gaëtan » → weissbeck). */
    public static function lineupLastName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $upper = array_filter($parts, fn ($p) => mb_strlen($p) > 1 && mb_strtoupper($p) === $p && preg_match('/\p{L}/u', $p));
        $last = $upper ? implode(' ', $upper) : ($parts[0] ?? $name);
        return implode(' ', self::tokens($last));
    }

    public static function display(string $s): string
    {
        // « WEISSBECK Gaëtan » → « Gaëtan Weissbeck »
        $parts = preg_split('/\s+/u', trim($s)) ?: [];
        $upper = [];
        $rest = [];
        foreach ($parts as $p) {
            if (mb_strlen($p) > 1 && mb_strtoupper($p) === $p && preg_match('/\p{L}/u', $p)) {
                $upper[] = mb_convert_case(mb_strtolower($p), MB_CASE_TITLE);
            } else {
                $rest[] = $p;
            }
        }
        return trim(implode(' ', $rest) . ' ' . implode(' ', $upper));
    }
}
