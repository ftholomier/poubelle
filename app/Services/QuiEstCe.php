<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Club;
use App\Core\JsonStore;
use App\Core\Memo;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Media;
use App\Data\Names;

/**
 * « Qui est-ce ? » : retrouver le joueur mystère parmi 24 cartes en posant des questions par oui
 * ou par non (poste, pied, origines, époque, carrière au club, taille, nom). Les réponses viennent
 * des fiches du musée : une question n'est proposée que si la réponse est connue pour toutes les
 * cartes encore debout (jamais de « peut-être »), et les cartes qui ne collent pas tombent d'elles-mêmes.
 *
 * Trois façons de jouer :
 * - duel : deux joueurs, chacun son joueur mystère à trouver, chacun son tour (code à 5 chiffres ou lien) ;
 * - solo : contre le musée ;
 * - défi du jour : mêmes cartes et même mystère pour tout le monde, résultat à partager.
 * Une mauvaise proposition = carton jaune (la carte tombe) ; la deuxième = carton rouge, partie perdue.
 *
 * Une partie = storage/qui-est-ce/{id}.json ; le joueur à trouver n'est jamais envoyé avant la fin.
 * Pas de websocket : en duel, chaque téléphone interroge l'état toutes les 2 s. Partie effacée
 * 48 h après la dernière activité.
 */
final class QuiEstCe
{
    /** Dossier des parties (remplacé par les tests). */
    public static string $dir = STORAGE_PATH . '/qui-est-ce';
    /** Cartes jouables (remplacées par les tests). @var array<int,array>|null */
    public static ?array $pool = null;
    public const CARDS = 24;
    /** Matchs au club pour entrer dans le jeu (joueurs assez connus). */
    public const MIN_MATCHES = 10;
    public const THEMES = ['tous', 'avant-2000', 'depuis-2000', 'legendes'];
    /** Adversaire muet depuis (s) : l'autre peut demander la victoire par forfait. */
    public const AWAY = 300;
    /** Cartons avant l'expulsion (partie perdue). */
    public const CARDS_OUT = 2;
    private const LINES = ['G', 'D', 'M', 'A'];
    /** Pays de naissance « en France » (métropole et outre-mer). */
    private const FRANCE = ['France', 'Guyane', 'Guadeloupe', 'Martinique', 'La Réunion', 'Réunion', 'Mayotte', 'Nouvelle-Calédonie',
        'Polynésie française', 'Saint-Pierre-et-Miquelon', 'Wallis-et-Futuna', 'Saint-Martin', 'Saint-Barthélemy'];
    /** Seuils proposés (les deux qui coupent le mieux les cartes debout). */
    private const STEPS = [
        'debut' => [1935, 1940, 1945, 1950, 1955, 1960, 1965, 1970, 1975, 1980, 1985, 1990, 1995, 2000, 2005, 2010, 2015, 2020, 2025],
        'ne' => [1905, 1910, 1915, 1920, 1925, 1930, 1935, 1940, 1945, 1950, 1955, 1960, 1965, 1970, 1975, 1980, 1985, 1990, 1995, 2000, 2005],
        'matchs' => [20, 30, 50, 75, 100, 150, 200, 250, 300, 400, 500],
        'buts' => [5, 10, 20, 30, 50, 75, 100],
        'saisons' => [2, 3, 4, 5, 6, 8, 10, 12, 15],
        'taille' => [170, 172, 175, 178, 180, 182, 185, 188, 190, 193],
    ];

    private static function file(string $id): string
    {
        return self::$dir . '/' . $id . '.json';
    }

    /** Duel : code à 5 chiffres (à dicter) ; solo et défi : 12 caractères. */
    public static function validId(string $id): bool
    {
        return (bool) preg_match('/^(\d{5}|[a-f0-9]{12})$/', $id);
    }

    // ------------------------------------------------------------------ cartes

    /** @return array<int,array> cartes jouables : joueurs publiés, 10 matchs au moins, vraie photo, poste connu */
    public static function pool(): array
    {
        return self::$pool ??= Memo::get('qui-est-ce-cartes', [Index::CACHE, __FILE__], Derived::built(), fn () => self::build());
    }

    private static function build(): array
    {
        $tot = Derived::part('person_totals');
        $matches = Derived::part('matches');
        $region = (array) Club::get('region', []);
        $depts = array_map('strval', (array) ($region['departments'] ?? []));
        $out = [];
        foreach (Index::published('personne') as $id => $s) {
            $p = (array) ($s['p'] ?? []);
            $t = $tot[$id] ?? null;
            if (!in_array('joueur', (array) ($p['roles'] ?? []), true) || !$t || (int) ($t['matches'] ?? 0) < self::MIN_MATCHES
                || !in_array($p['line'] ?? null, self::LINES, true) || !($photo = self::portrait($s['image'] ?? null))) {
                continue;
            }
            $debut = (int) substr((string) ($matches[$t['first'] ?? 0]['date'] ?? ''), 0, 4);
            $end = (int) substr((string) ($matches[$t['last'] ?? 0]['date'] ?? ''), 0, 4);
            if ($debut < 1900) {
                continue;
            }
            $pp = (array) (Fiches::get((int) $id)['personne'] ?? []);
            $country = trim((string) ($p['birth_country'] ?? '')) ?: null;
            $birth = $p['birth_year'] ? (int) $p['birth_year'] : null;
            // Né en Algérie avant l'indépendance : « né en France ? » n'a pas de réponse sûre.
            $france = $country === null || ($country === 'Algérie' && ($birth === null || $birth < 1963)) ? null : in_array($country, self::FRANCE, true);
            $dept = strtoupper(trim((string) ($pp['birth']['place']['department'] ?? '')));
            $name = trim((string) ($p['name'] ?? '')) ?: (string) $s['title'];
            $last = trim((string) ($p['last'] ?? '')) ?: (string) preg_replace('/^.*\s/u', '', $name);
            $height = (int) ($pp['height_cm'] ?? 0);
            $out[(int) $id] = [
                'id' => (int) $id,
                'name' => $name,
                'last' => $last,
                'path' => (string) $s['path'],
                'photo' => $photo,
                'line' => $p['line'],
                'foot' => self::foot((string) ($pp['foot'] ?? '')),
                'height' => $height >= 150 && $height <= 210 ? $height : null,
                'birth' => $birth,
                'place' => trim((string) ($p['birth_place'] ?? '')) ?: null,
                'country' => $country,
                'france' => $france,
                // Région du club (Franche-Comté pour Sochaux) : sûre hors de France, ou avec le département.
                'region' => !$depts || $france === null ? null : ($france ? (preg_match('/^(\d{2,3}|2[AB])$/', $dept) ? in_array($dept, $depts, true) : null) : false),
                'formed' => (bool) ($p['formed'] ?? false),
                'intl' => (bool) ($p['intl'] ?? false),
                'coach' => (int) ($t['coached'] ?? 0) > 0,
                'matches' => (int) $t['matches'],
                'goals' => (int) ($t['goals'] ?? 0),
                'seasons' => max(1, (int) ($t['seasons'] ?? 1)),
                'debut' => $debut,
                'end' => max($debut, $end),
            ];
        }
        return $out;
    }

    /** Photo de face (portrait, pas une photo d'équipe) et propre à la fiche, sinon null. */
    private static function portrait(mixed $image): ?string
    {
        if (!is_string($image) || $image === '' || Index::isPlaceholderImage($image)) {
            return null;
        }
        $m = Media::get($image);
        $w = (int) ($m['width'] ?? 0);
        $h = (int) ($m['height'] ?? 0);
        return $w > 0 && $h >= 0.8 * $w ? $image : null;
    }

    private static function foot(string $f): ?string
    {
        $f = Names::ascii($f);
        return match (true) {
            str_starts_with($f, 'gauch') => 'g',
            str_starts_with($f, 'droit') => 'd',
            str_starts_with($f, 'ambi') => 'a',
            default => null,
        };
    }

    /** Cartes d'un thème. @return array<int,array> */
    public static function themePool(string $theme): array
    {
        $pool = self::pool();
        return match ($theme) {
            'avant-2000' => array_filter($pool, fn ($c) => $c['debut'] < 2000),
            'depuis-2000' => array_filter($pool, fn ($c) => $c['debut'] >= 2000),
            'legendes' => (function () use ($pool) {
                uasort($pool, fn ($a, $b) => $b['matches'] <=> $a['matches']);
                return array_slice($pool, 0, self::CARDS * 3, true);
            })(),
            default => $pool,
        };
    }

    /** Thèmes jouables (assez de cartes). @return array<string,string> clé => libellé */
    public static function themes(): array
    {
        $labels = ['tous' => t('Toutes les époques'), 'avant-2000' => t('Avant 2000'), 'depuis-2000' => t('Depuis 2000'), 'legendes' => t('Les plus capés')];
        return array_filter($labels, fn ($k) => count(self::themePool($k)) >= self::CARDS, ARRAY_FILTER_USE_KEY);
    }

    /**
     * 24 cartes du thème, toutes les lignes représentées (3 gardiens, 7 défenseurs, 7 milieux,
     * 7 attaquants si possible), rangées par ligne puis par nom. @return list<array>
     */
    public static function board(string $theme, ?\Random\Randomizer $rnd = null): array
    {
        $rnd ??= new \Random\Randomizer();
        $pool = self::themePool($theme);
        $ids = $pool ? $rnd->shuffleArray(array_keys($pool)) : [];
        $by = array_fill_keys(self::LINES, []);
        foreach ($ids as $id) {
            $by[$pool[$id]['line']][] = $id;
        }
        $pick = [];
        foreach (['G' => 3, 'D' => 7, 'M' => 7, 'A' => 7] as $l => $n) {
            array_push($pick, ...array_slice($by[$l], 0, $n));
        }
        // Ligne trop courte : complétée par d'autres joueurs.
        foreach ($ids as $id) {
            if (count($pick) >= self::CARDS) {
                break;
            }
            if (!in_array($id, $pick, true)) {
                $pick[] = $id;
            }
        }
        $cards = array_map(fn ($id) => $pool[$id], $pick);
        usort($cards, fn ($a, $b) => [array_search($a['line'], self::LINES, true), Names::ascii($a['last'])] <=> [array_search($b['line'], self::LINES, true), Names::ascii($b['last'])]);
        return $cards;
    }

    // ------------------------------------------------------------------ questions

    /** Réponse d'une question pour une carte (null : inconnue, ou question invalide). */
    public static function answer(string $k, array $c): ?bool
    {
        if (preg_match('/^poste:([GDMA])$/', $k, $m)) {
            return $c['line'] === $m[1];
        }
        if (preg_match('/^(debut|ne)<(\d{4})$/', $k, $m)) {
            $v = $m[1] === 'debut' ? $c['debut'] : $c['birth'];
            return $v === null ? null : $v < (int) $m[2];
        }
        if (preg_match('/^(matchs|buts|saisons|taille)>=(\d{1,3})$/', $k, $m)) {
            $v = ['matchs' => $c['matches'], 'buts' => $c['goals'], 'saisons' => $c['seasons'], 'taille' => $c['height']][$m[1]];
            return $v === null ? null : $v >= (int) $m[2];
        }
        if (preg_match('/^nom<=([A-Z])$/', $k, $m)) {
            $i = self::initial($c['last']);
            return $i === null ? null : $i <= $m[1];
        }
        return match ($k) {
            'gaucher' => $c['foot'] === null ? null : $c['foot'] === 'g',
            'france' => $c['france'],
            'region' => $c['region'],
            'forme' => $c['formed'],
            'inter' => $c['intl'],
            'entraineur' => $c['coach'],
            default => null,
        };
    }

    private static function initial(string $last): ?string
    {
        $i = strtoupper(substr(Names::ascii($last), 0, 1));
        return preg_match('/^[A-Z]$/', $i) ? $i : null;
    }

    /** Libellé d'une question (langue de la page). */
    public static function text(string $k): string
    {
        if (preg_match('/^poste:([GDMA])$/', $k, $m)) {
            return ['G' => t('Est-il gardien de but ?'), 'D' => t('Est-il défenseur ?'), 'M' => t('Est-il milieu de terrain ?'), 'A' => t('Est-il attaquant ?')][$m[1]];
        }
        if (preg_match('/^(\w+)(<|>=|<=)(\w+)$/', $k, $m)) {
            $n = (int) $m[3];
            return match ($m[1]) {
                'debut' => t('A-t-il débuté avec {sigle_le} avant {y} ?', ['y' => $n]),
                'ne' => t('Est-il né avant {y} ?', ['y' => $n]),
                'matchs' => t('A-t-il joué au moins {n} matchs avec {sigle_le} ?', ['n' => $n]),
                'buts' => t('A-t-il marqué au moins {n} buts avec {sigle_le} ?', ['n' => $n]),
                'saisons' => t('A-t-il joué au moins {n} saisons au club ?', ['n' => $n]),
                'taille' => t('Mesure-t-il au moins {h} ?', ['h' => number_format($n / 100, 2, I18n::lang() === 'en' ? '.' : ',', '') . ' m']),
                'nom' => t('Son nom commence-t-il par une lettre de A à {l} ?', ['l' => $m[3]]),
                default => $k,
            };
        }
        $region = (array) Club::get('region', []);
        return match ($k) {
            'gaucher' => t('Est-il gaucher ?'),
            'france' => t('Est-il né en France ?'),
            'region' => t('Est-il né {region} ?', ['region' => (string) (I18n::lang() === 'en' ? ($region['in_en'] ?? $region['in'] ?? '') : ($region['in'] ?? ''))]),
            'forme' => t('A-t-il été formé au club ?'),
            'inter' => t('A-t-il été international ?'),
            'entraineur' => t('A-t-il aussi entraîné {sigle_le} ?'),
            'buts>=1' => t('A-t-il marqué au moins un but avec {sigle_le} ?'),
            default => $k,
        };
    }

    /** Groupe d'une question (onglets de la page). */
    private static function group(string $k): string
    {
        return match (true) {
            str_starts_with($k, 'poste:') || $k === 'gaucher' || str_starts_with($k, 'taille') => 'profil',
            in_array($k, ['france', 'region'], true) || str_starts_with($k, 'ne<') => 'origines',
            str_starts_with($k, 'nom') => 'nom',
            default => 'club',
        };
    }

    /**
     * Questions utiles pour ces cartes : réponse connue pour chacune, et qui en départagent au
     * moins deux. Seuils (années, matchs, buts, taille…) : les deux qui coupent le mieux.
     * @return list<array{k:string,t:string,g:string}>
     */
    public static function questions(array $cards): array
    {
        $cards = array_values($cards);
        $n = count($cards);
        if ($n < 2) {
            return [];
        }
        $splits = function (string $k) use ($cards): ?int {
            $yes = 0;
            foreach ($cards as $c) {
                $a = self::answer($k, $c);
                if ($a === null) {
                    return null;
                }
                $yes += $a ? 1 : 0;
            }
            return $yes > 0 && $yes < count($cards) ? $yes : null;
        };
        $keys = [];
        foreach (['poste:G', 'poste:D', 'poste:M', 'poste:A', 'gaucher'] as $k) {
            $keys[] = $k;
        }
        $best = function (string $attr, string $op) use ($splits, $n): array {
            $scored = [];
            foreach (self::STEPS[$attr] as $v) {
                $k = $attr . $op . $v;
                if (($yes = $splits($k)) !== null) {
                    $scored[$k] = [abs($yes - $n / 2), $v];
                }
            }
            asort($scored);
            $two = array_slice($scored, 0, 2, true);
            uasort($two, fn ($a, $b) => $a[1] <=> $b[1]);
            return array_keys($two);
        };
        array_push($keys, ...$best('taille', '>='));
        array_push($keys, 'france', 'region', ...$best('ne', '<'));
        array_push($keys, ...$best('debut', '<'));
        array_push($keys, ...$best('saisons', '>='));
        array_push($keys, ...$best('matchs', '>='));
        $keys[] = 'buts>=1';
        array_push($keys, ...array_slice($best('buts', '>='), -1));
        array_push($keys, 'forme', 'inter', 'entraineur');
        // Nom : la lettre qui coupe le mieux (et une autre).
        $letters = [];
        foreach (range('A', 'Y') as $l) {
            if (($yes = $splits('nom<=' . $l)) !== null) {
                $letters['nom<=' . $l] = [abs($yes - $n / 2), $l];
            }
        }
        asort($letters);
        $two = array_slice($letters, 0, 2, true);
        uasort($two, fn ($a, $b) => $a[1] <=> $b[1]);
        array_push($keys, ...array_keys($two));
        $out = [];
        foreach (array_unique($keys) as $k) {
            if ($splits($k) !== null) {
                $out[] = ['k' => $k, 't' => self::text($k), 'g' => self::group($k)];
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ parties

    /**
     * Nouvelle partie. Duel : le créateur attend son adversaire (code à 5 chiffres) ; solo et défi :
     * la partie commence tout de suite. @return array{id:string,pid:string,tok:string}|string erreur
     */
    public static function create(string $mode, string $theme, string $lang, string $name = ''): array|string
    {
        $mode = in_array($mode, ['duel', 'solo', 'defi'], true) ? $mode : 'solo';
        $theme = $mode === 'defi' || !in_array($theme, self::THEMES, true) ? 'tous' : $theme;
        if ($mode === 'duel') {
            $name = QuizLive::cleanName($name);
            if (mb_strlen($name) < 2) {
                return t('Choisissez un pseudo de 2 à 20 caractères.');
            }
            if (!QuizLive::nameAllowed($name)) {
                return t('Ce pseudo n’est pas accepté : choisissez-en un autre.');
            }
        }
        $date = null;
        if ($mode === 'defi') {
            $daily = self::daily();
            [$cards, $target, $date] = [$daily['cards'], (int) $daily['target'], $daily['date']];
        } else {
            $cards = self::board($theme);
            $target = count($cards) ? (int) $cards[array_rand($cards)]['id'] : 0;
        }
        if (count($cards) < self::CARDS) {
            return t('Pas encore assez de joueurs avec photo pour jouer.');
        }
        do {
            $id = $mode === 'duel' ? (string) random_int(10000, 99999) : bin2hex(random_bytes(6));
        } while (is_file(self::file($id)));
        $pid = bin2hex(random_bytes(5));
        $tok = bin2hex(random_bytes(12));
        $ids = array_column($cards, 'id');
        $g = ['id' => $id, 'mode' => $mode, 'theme' => $theme, 'lang' => $lang === 'en' ? 'en' : 'fr', 'created' => time(), 'date' => $date,
            'cards' => $cards, 'round' => 1, 'phase' => $mode === 'duel' ? 'attente' : 'jeu', 'turn' => $mode === 'duel' ? null : $pid,
            'players' => [$pid => self::seat($name, $tok, $ids, $mode === 'duel' ? 0 : $target)], 'log' => [], 'end' => null, 'v' => 1];
        JsonStore::write(self::file($id), $g);
        return ['id' => $id, 'pid' => $pid, 'tok' => $tok];
    }

    private static function seat(string $name, string $tok, array $ids, int $target): array
    {
        return ['name' => $name, 'tok' => hash('sha256', $tok), 'target' => $target, 'up' => $ids, 'asked' => 0, 'yellow' => 0, 'wins' => 0, 'seen' => time()];
    }

    /** Cartes et mystère du défi du jour, les mêmes pour tous (gardés pour la journée). */
    public static function daily(?string $date = null): array
    {
        $date ??= DailyQuiz::today();
        return JsonStore::update(self::$dir . '/defi-' . $date . '.json', function ($d) use ($date) {
            if (is_array($d) && count($d['cards'] ?? []) >= self::CARDS) {
                return $d;
            }
            $rnd = new \Random\Randomizer(new \Random\Engine\Mt19937(crc32(Club::id() . '|qui-est-ce|' . $date)));
            $cards = self::board('tous', $rnd);
            return ['date' => $date, 'cards' => $cards, 'target' => $cards ? (int) $cards[$rnd->getInt(0, count($cards) - 1)]['id'] : 0];
        });
    }

    public static function get(string $id): ?array
    {
        if (!self::validId($id)) {
            return null;
        }
        $g = JsonStore::read(self::file($id));
        return is_array($g) ? $g : null;
    }

    public static function player(?array $g, string $pid, string $tok): ?array
    {
        $p = $g['players'][$pid] ?? null;
        return $p && $tok !== '' && hash_equals((string) $p['tok'], hash('sha256', $tok)) ? $p : null;
    }

    /** Duel : le deuxième joueur s'assoit ; chacun reçoit le joueur mystère de l'autre à trouver. */
    public static function join(string $id, string $name): array|string
    {
        $name = QuizLive::cleanName($name);
        if (mb_strlen($name) < 2) {
            return t('Choisissez un pseudo de 2 à 20 caractères.');
        }
        if (!QuizLive::nameAllowed($name)) {
            return t('Ce pseudo n’est pas accepté : choisissez-en un autre.');
        }
        $pid = bin2hex(random_bytes(5));
        $tok = bin2hex(random_bytes(12));
        $err = null;
        JsonStore::update(self::file($id), function ($g) use ($pid, $tok, &$name, &$err) {
            if (!is_array($g) || $g['mode'] !== 'duel') {
                $err = t('Aucune partie avec ce code.');
                return $g;
            }
            if ($g['phase'] !== 'attente' || count($g['players']) >= 2) {
                $err = t('Cette partie a déjà ses deux joueurs.');
                return $g;
            }
            $first = (string) array_key_first($g['players']);
            if (mb_strtolower($g['players'][$first]['name']) === mb_strtolower($name)) {
                $name = mb_substr($name, 0, 18) . ' 2';
            }
            $g['players'][$pid] = self::seat($name, $tok, array_column($g['cards'], 'id'), 0);
            self::deal($g);
            $g['turn'] = random_int(0, 1) ? $first : $pid;
            $g['phase'] = 'jeu';
            $g['v']++;
            return $g;
        });
        return $err ?? ['pid' => $pid, 'tok' => $tok, 'name' => $name];
    }

    /** Duel : deux joueurs mystère différents, un pour chacun. */
    private static function deal(array &$g): void
    {
        $ids = array_column($g['cards'], 'id');
        shuffle($ids);
        $i = 0;
        foreach ($g['players'] as $pid => $_) {
            $g['players'][$pid]['target'] = (int) $ids[$i++];
        }
    }

    private static function other(array $g, string $pid): ?string
    {
        foreach ($g['players'] as $k => $_) {
            if ((string) $k !== $pid) {
                return (string) $k;
            }
        }
        return null;
    }

    private static function card(array $g, int $id): ?array
    {
        foreach ($g['cards'] as $c) {
            if ((int) $c['id'] === $id) {
                return $c;
            }
        }
        return null;
    }

    /** Cartes encore debout d'un joueur. @return list<array> */
    private static function standing(array $g, string $pid): array
    {
        $up = array_flip(array_map('intval', $g['players'][$pid]['up'] ?? []));
        return array_values(array_filter($g['cards'], fn ($c) => isset($up[(int) $c['id']])));
    }

    /**
     * Action d'un joueur dans sa partie (fichier verrouillé) : $fn reçoit la partie et le joueur,
     * renvoie un message d'erreur ou null. @return string|null erreur
     */
    private static function act(string $id, string $pid, string $tok, callable $fn): ?string
    {
        $err = null;
        JsonStore::update(self::file($id), function ($g) use ($pid, $tok, $fn, &$err) {
            if (!is_array($g) || !self::player($g, $pid, $tok)) {
                $err = t('Cette partie n’existe plus.');
                return $g;
            }
            $err = $fn($g);
            if ($err === null) {
                $g['players'][$pid]['seen'] = time();
                $g['v']++;
            }
            return $g;
        });
        return $err;
    }

    /** Le joueur peut jouer maintenant (partie en cours, à son tour) ? */
    private static function myTurn(array $g, string $pid): ?string
    {
        if ($g['phase'] !== 'jeu') {
            return $g['phase'] === 'attente' ? t('Votre adversaire n’est pas encore arrivé.') : t('La partie est terminée.');
        }
        return $g['turn'] === $pid ? null : t('Ce n’est pas votre tour.');
    }

    /** Poser une question : réponse d'après le joueur mystère, les cartes qui ne collent pas tombent. */
    public static function ask(string $id, string $pid, string $tok, string $k): ?string
    {
        return self::act($id, $pid, $tok, function (array &$g) use ($pid, $k) {
            if ($e = self::myTurn($g, $pid)) {
                return $e;
            }
            if (!in_array($k, array_column(self::questions(self::standing($g, $pid)), 'k'), true)) {
                return t('Cette question n’est plus utile : choisissez-en une autre.');
            }
            $target = self::card($g, (int) $g['players'][$pid]['target']);
            $a = $target ? self::answer($k, $target) : null;
            if ($a === null) {
                return t('Cette question n’est plus utile : choisissez-en une autre.');
            }
            $g['players'][$pid]['up'] = array_column(array_filter(self::standing($g, $pid), fn ($c) => self::answer($k, $c) === $a), 'id');
            $g['players'][$pid]['asked']++;
            $g['log'][] = ['by' => $pid, 'q' => $k, 'a' => $a, 'left' => count($g['players'][$pid]['up'])];
            $g['turn'] = $g['mode'] === 'duel' ? self::other($g, $pid) : $pid;
            return null;
        });
    }

    /** « C'est lui ! » : gagné, ou carton jaune (la carte tombe) ; au deuxième carton, partie perdue. */
    public static function guess(string $id, string $pid, string $tok, int $card): ?string
    {
        return self::act($id, $pid, $tok, function (array &$g) use ($pid, $card) {
            if ($e = self::myTurn($g, $pid)) {
                return $e;
            }
            $p = &$g['players'][$pid];
            if (!in_array($card, array_map('intval', $p['up']), true)) {
                return t('Cette carte est déjà tombée.');
            }
            $ok = $card === (int) $p['target'];
            $g['log'][] = ['by' => $pid, 'guess' => $card, 'ok' => $ok];
            $opp = $g['mode'] === 'duel' ? self::other($g, $pid) : null;
            if ($ok) {
                $p['wins']++;
                self::finish($g, $pid, 'trouve');
                return null;
            }
            $p['yellow']++;
            $p['up'] = array_values(array_filter(array_map('intval', $p['up']), fn ($c) => $c !== $card));
            if ($p['yellow'] >= self::CARDS_OUT) {
                if ($opp !== null) {
                    $g['players'][$opp]['wins']++;
                }
                self::finish($g, $opp, 'rouge');
                return null;
            }
            $g['turn'] = $opp ?? $pid;
            return null;
        });
    }

    private static function finish(array &$g, ?string $winner, string $how): void
    {
        $g['phase'] = 'fin';
        $g['turn'] = null;
        $g['end'] = ['winner' => $winner, 'how' => $how, 'at' => time()];
    }

    /** Abandon : la partie s'arrête, l'adversaire (duel) gagne. */
    public static function giveUp(string $id, string $pid, string $tok): ?string
    {
        return self::act($id, $pid, $tok, function (array &$g) use ($pid) {
            if ($g['phase'] === 'fin') {
                return t('La partie est terminée.');
            }
            $opp = $g['mode'] === 'duel' && $g['phase'] === 'jeu' ? self::other($g, $pid) : null;
            if ($opp !== null) {
                $g['players'][$opp]['wins']++;
            }
            self::finish($g, $opp, 'abandon');
            return null;
        });
    }

    /** Duel : l'adversaire ne donne plus signe de vie depuis 5 minutes, victoire par forfait. */
    public static function forfeit(string $id, string $pid, string $tok): ?string
    {
        return self::act($id, $pid, $tok, function (array &$g) use ($pid) {
            $opp = $g['mode'] === 'duel' && $g['phase'] === 'jeu' ? self::other($g, $pid) : null;
            if ($opp === null || (int) $g['players'][$opp]['seen'] > time() - self::AWAY) {
                return t('Votre adversaire est toujours là.');
            }
            $g['players'][$pid]['wins']++;
            self::finish($g, $pid, 'forfait');
            return null;
        });
    }

    /** Duel terminé : revanche avec de nouvelles cartes ; le perdant commence. */
    public static function rematch(string $id, string $pid, string $tok): ?string
    {
        return self::act($id, $pid, $tok, function (array &$g) use ($pid) {
            if ($g['mode'] !== 'duel' || $g['phase'] !== 'fin' || count($g['players']) < 2) {
                return t('La revanche n’est possible qu’à la fin d’un duel.');
            }
            $g['cards'] = self::board($g['theme']);
            $ids = array_column($g['cards'], 'id');
            foreach ($g['players'] as $k => $p) {
                $g['players'][$k] = ['up' => $ids, 'asked' => 0, 'yellow' => 0] + $p;
                $g['players'][$k]['up'] = $ids;
                $g['players'][$k]['asked'] = 0;
                $g['players'][$k]['yellow'] = 0;
            }
            self::deal($g);
            $winner = $g['end']['winner'] ?? null;
            $g['turn'] = $winner !== null ? self::other($g, (string) $winner) : $pid;
            $g['round']++;
            $g['log'] = [];
            $g['end'] = null;
            $g['phase'] = 'jeu';
            return null;
        });
    }

    /** Passage du joueur (interrogation de l'état) : noté toutes les 20 s au plus, pour le forfait. */
    public static function seen(string $id, string $pid): void
    {
        $g = self::get($id);
        if (!$g || !isset($g['players'][$pid]) || (int) $g['players'][$pid]['seen'] > time() - 20) {
            return;
        }
        JsonStore::update(self::file($id), function ($g) use ($pid) {
            if (is_array($g) && isset($g['players'][$pid])) {
                $g['players'][$pid]['seen'] = time();
            }
            return $g;
        });
    }

    // ------------------------------------------------------------------ état envoyé au joueur

    /**
     * Ce que voit un joueur : ses cartes debout, son tour, les questions possibles, le journal.
     * Le joueur à trouver n'est donné qu'à la fin. $round : manche déjà affichée (cartes non renvoyées).
     */
    public static function state(array $g, string $pid, int $round = 0): array
    {
        $me = $g['players'][$pid];
        $opp = $g['mode'] === 'duel' ? self::other($g, $pid) : null;
        $o = $opp !== null ? $g['players'][$opp] : null;
        $name = fn (?string $who) => $who === null ? '' : (string) ($g['players'][$who]['name'] ?? '');
        $log = [];
        foreach ($g['log'] as $l) {
            $c = isset($l['guess']) ? self::card($g, (int) $l['guess']) : null;
            $log[] = ['me' => $l['by'] === $pid, 'who' => $name($l['by']),
                't' => isset($l['q']) ? self::text($l['q']) : t('C’est {n} ?', ['n' => $c['name'] ?? '?']),
                'a' => isset($l['q']) ? ($l['a'] ? t('Oui') : t('Non')) : ($l['ok'] ? t('Bien vu !') : t('Raté : carton jaune')),
                'ok' => isset($l['q']) ? (bool) $l['a'] : (bool) $l['ok'], 'guess' => isset($l['guess']), 'left' => $l['left'] ?? null];
        }
        $playing = $g['phase'] === 'jeu' && $g['turn'] === $pid;
        $s = [
            'id' => $g['id'], 'mode' => $g['mode'], 'theme' => $g['theme'], 'date' => $g['date'] ?? null, 'phase' => $g['phase'], 'round' => (int) $g['round'], 'v' => (int) $g['v'],
            'up' => array_map('intval', $me['up']), 'asked' => (int) $me['asked'], 'yellow' => (int) $me['yellow'], 'name' => $me['name'],
            'turn' => $g['phase'] === 'jeu' ? ($playing ? 'me' : 'opp') : null,
            'questions' => $playing ? self::questions(self::standing($g, $pid)) : [],
            'log' => $log,
        ];
        if ((int) $g['round'] !== $round) {
            $s['cards'] = array_map(fn ($c) => self::publicCard($c), $g['cards']);
        }
        if ($g['mode'] === 'duel') {
            $s['opp'] = $o ? ['name' => $o['name'], 'up' => count($o['up']), 'asked' => (int) $o['asked'], 'yellow' => (int) $o['yellow'],
                'away' => (int) $o['seen'] <= time() - self::AWAY] : null;
            // Mon joueur mystère : celui que l'adversaire doit trouver.
            $s['mine'] = $o ? (int) $o['target'] : null;
            $s['score'] = [(int) $me['wins'], (int) ($o['wins'] ?? 0)];
        }
        if ($g['phase'] === 'fin') {
            $w = $g['end']['winner'] ?? null;
            $s['end'] = ['how' => $g['end']['how'], 'won' => $w === $pid, 'lost' => $w !== null && $w !== $pid, 'target' => (int) $me['target'],
                'by' => $name($w)];
        }
        return $s;
    }

    /** Carte telle qu'affichée (photo, nom, années) avec sa fiche détaillée (bouton « i »). */
    private static function publicCard(array $c): array
    {
        $line = ['G' => t('Gardien'), 'D' => t('Défenseur'), 'M' => t('Milieu'), 'A' => t('Attaquant')][$c['line']] ?? '';
        $facts = [$line];
        if ($c['birth']) {
            $facts[] = $c['place'] || $c['country'] ? t('né en {y} à {p}', ['y' => $c['birth'], 'p' => trim(($c['place'] ?? '') . (($c['country'] ?? 'France') !== 'France' && $c['country'] ? ' (' . $c['country'] . ')' : ''))]) : t('né en {y}', ['y' => $c['birth']]);
        }
        if ($c['height']) {
            $facts[] = number_format($c['height'] / 100, 2, I18n::lang() === 'en' ? '.' : ',', '') . ' m';
        }
        if ($c['foot']) {
            $facts[] = ['g' => t('gaucher'), 'd' => t('droitier'), 'a' => t('ambidextre')][$c['foot']];
        }
        $facts[] = tn($c['matches'], '{n} match', '{n} matchs') . ', ' . tn($c['goals'], '{n} but', '{n} buts');
        if ($c['formed']) {
            $facts[] = t('formé au club');
        }
        if ($c['intl']) {
            $facts[] = t('international');
        }
        return ['id' => (int) $c['id'], 'name' => $c['name'], 'last' => $c['last'], 'line' => $c['line'], 'img' => img($c['photo'], 320),
            'years' => $c['debut'] === $c['end'] ? (string) $c['debut'] : $c['debut'] . '–' . $c['end'], 'href' => url($c['path']), 'facts' => implode(' · ', $facts)];
    }

    /** Ménage du cron : parties sans activité depuis 48 h, défis du jour de plus de 3 jours. */
    public static function purge(): int
    {
        $n = 0;
        foreach (glob(self::$dir . '/*.json') ?: [] as $f) {
            if ((@filemtime($f) ?: time()) < time() - (str_starts_with(basename($f), 'defi-') ? 3 * 86400 : 2 * 86400)) {
                @unlink($f);
                @unlink($f . '.lock');
                $n++;
            }
        }
        return $n;
    }
}
