<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use App\Data\Collections;
use App\Data\Index;
use App\Data\Names;

/**
 * Géolocalisation des stades et des lieux de naissance (carte du musée).
 *
 * 1. Répertoire intégré de stades et de villes fréquents (positions vérifiées) ;
 * 2. sinon Nominatim (OpenStreetMap), par la tâche planifiée, une requête par seconde
 *    au plus, avec l'adresse de contact du site, résultats mis en cache ;
 * 3. correction manuelle possible dans le back-office (référentiels).
 */
final class Geo
{
    /** Stades connus : motif (nom normalisé) => [lat, lng, ville, code pays]. */
    private const STADIUMS = [
        'bonal' => [47.5119, 6.8116, 'Montbéliard', 'FR'],
        'stade de france' => [48.9245, 2.3602, 'Saint-Denis', 'FR'],
        'parc des princes' => [48.8414, 2.2530, 'Paris', 'FR'],
        'colombes' => [48.9289, 2.2475, 'Colombes', 'FR'],
        'yves du manoir' => [48.9289, 2.2475, 'Colombes', 'FR'],
        'velodrome' => [43.2698, 5.3959, 'Marseille', 'FR'],
        'gerland' => [45.7238, 4.8322, 'Lyon', 'FR'],
        'geoffroy guichard' => [45.4608, 4.3903, 'Saint-Étienne', 'FR'],
        'beaujoire' => [47.2560, -1.5245, 'Nantes', 'FR'],
        'lescure' => [44.8290, -0.5980, 'Bordeaux', 'FR'],
        'chaban delmas' => [44.8290, -0.5980, 'Bordeaux', 'FR'],
        'bollaert' => [50.4329, 2.8150, 'Lens', 'FR'],
        'meinau' => [48.5598, 7.7550, 'Strasbourg', 'FR'],
        'saint symphorien' => [49.1098, 6.1594, 'Metz', 'FR'],
        'marcel picot' => [48.6953, 6.2108, 'Nancy', 'FR'],
        'abbe deschamps' => [47.7870, 3.5887, 'Auxerre', 'FR'],
        'route de lorient' => [48.1075, -1.7130, 'Rennes', 'FR'],
        'louis ii' => [43.7276, 7.4155, 'Monaco', 'MC'],
        'mosson' => [43.6222, 3.8120, 'Montpellier', 'FR'],
        'gaston gerard' => [47.3245, 5.0682, 'Dijon', 'FR'],
    ];

    /** Villes fréquentes : nom normalisé => [lat, lng, code pays]. */
    private const CITIES = [
        'montbeliard' => [47.5097, 6.7983, 'FR'], 'sochaux' => [47.5085, 6.8270, 'FR'], 'audincourt' => [47.4833, 6.8500, 'FR'],
        'belfort' => [47.6383, 6.8628, 'FR'], 'besancon' => [47.2378, 6.0241, 'FR'], 'mulhouse' => [47.7508, 7.3359, 'FR'],
        'troyes' => [48.2973, 4.0744, 'FR'], 'paris' => [48.8566, 2.3522, 'FR'], 'lyon' => [45.7640, 4.8357, 'FR'],
        'marseille' => [43.2965, 5.3698, 'FR'], 'strasbourg' => [48.5734, 7.7521, 'FR'], 'nancy' => [48.6921, 6.1844, 'FR'],
        'lille' => [50.6292, 3.0573, 'FR'], 'geneve' => [46.2044, 6.1432, 'CH'], 'bale' => [47.5596, 7.5886, 'CH'],
        'bruxelles' => [50.8503, 4.3517, 'BE'], 'amsterdam' => [52.3676, 4.9041, 'NL'], 'belgrade' => [44.7866, 20.4489, 'RS'],
        'zagreb' => [45.8150, 15.9819, 'HR'], 'varsovie' => [52.2297, 21.0122, 'PL'], 'copenhague' => [55.6761, 12.5683, 'DK'],
        'lisbonne' => [38.7223, -9.1393, 'PT'], 'madrid' => [40.4168, -3.7038, 'ES'], 'dakar' => [14.7167, -17.4677, 'SN'],
        'abidjan' => [5.3600, -4.0083, 'CI'], 'douala' => [4.0511, 9.7679, 'CM'], 'yaounde' => [3.8480, 11.5021, 'CM'],
        'bamako' => [12.6392, -8.0029, 'ML'], 'ouagadougou' => [12.3714, -1.5197, 'BF'], 'conakry' => [9.6412, -13.5784, 'GN'],
        'kinshasa' => [-4.4419, 15.2663, 'CD'], 'accra' => [5.6037, -0.1870, 'GH'], 'casablanca' => [33.5731, -7.5898, 'MA'],
        'alger' => [36.7538, 3.0588, 'DZ'], 'oran' => [35.6971, -0.6308, 'DZ'], 'tunis' => [36.8065, 10.1815, 'TN'],
        'buenos aires' => [-34.6037, -58.3816, 'AR'], 'montevideo' => [-34.9011, -56.1645, 'UY'], 'sao paulo' => [-23.5505, -46.6333, 'BR'],
        'rio de janeiro' => [-22.9068, -43.1729, 'BR'], 'sarajevo' => [43.8563, 18.4131, 'BA'], 'monaco' => [43.7384, 7.4246, 'MC'],
    ];

    /** Code pays ISO alpha-2 → numérique (fonds de carte) et nom français, via ICU. */
    public static function country(string $cc): array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            $rb = \ResourceBundle::create('supplementalData', 'ICUDATA', false);
            foreach ($rb ? $rb->get('codeMappings') : [] as $row) {
                if ($row instanceof \ResourceBundle && $row->count() >= 2) {
                    $map[(string) $row->get(0)] = (string) $row->get(1);
                }
            }
        }
        $cc = strtoupper($cc);
        return ['iso' => $map[$cc] ?? null, 'name' => \Locale::getDisplayRegion('-' . $cc, 'fr') ?: $cc];
    }

    /** Position d'un lieu de naissance (cache, répertoire, puis Nominatim si $online). */
    public static function place(string $city, string $country = '', bool $online = false): ?array
    {
        $key = trim($city . '|' . $country, '|');
        if ($key === '') {
            return null;
        }
        $cache = Collections::get('geo', []);
        if (isset($cache[$key])) {
            return $cache[$key]['lat'] !== null ? $cache[$key] : null;
        }
        $hit = null;
        $k = Search::norm($city);
        if (isset(self::CITIES[$k])) {
            [$lat, $lng, $cc] = self::CITIES[$k];
            $hit = ['lat' => $lat, 'lng' => $lng, 'cc' => $cc, 'source' => 'repertoire'];
        } elseif ($online) {
            $hit = self::nominatim($city . ($country !== '' ? ', ' . $country : ''));
        } else {
            return null;
        }
        $entry = $hit ? $hit + self::country($hit['cc']) + ['city' => $city, 'country' => $country ?: self::country($hit['cc'])['name'], 'at' => date('c')]
            : ['lat' => null, 'lng' => null, 'city' => $city, 'country' => $country, 'at' => date('c'), 'source' => 'introuvable'];
        $cache[$key] = $entry;
        Collections::save('geo', $cache, null, 'Géolocalisation : ' . $key);
        return $entry['lat'] !== null ? $entry : null;
    }

    /** Position d'un stade (répertoire puis Nominatim). */
    public static function stadium(array $st, bool $online = false): ?array
    {
        $n = Search::norm($st['name'] . ' ' . implode(' ', $st['aliases'] ?? []));
        foreach (self::STADIUMS as $pattern => [$lat, $lng, $city, $cc]) {
            if (str_contains($n, $pattern)) {
                return ['lat' => $lat, 'lng' => $lng, 'city' => $city, 'cc' => $cc];
            }
        }
        if (!$online) {
            return null;
        }
        $where = trim(($st['city'] ?? '') . ' ' . (preg_match('/^\d{2,3}$/', (string) ($st['department'] ?? '')) ? '' : ($st['department'] ?? '')));
        $q = $st['name'] . ($where !== '' ? ', ' . $where : '') . (preg_match('/^\d{2,3}$/', (string) ($st['department'] ?? '')) ? ', France' : '');
        return self::nominatim($q, 'stadium');
    }

    private static float $last = 0;

    private static function nominatim(string $q, string $kind = ''): ?array
    {
        if (!Settings::get('map.geocoding', true)) {
            return null;
        }
        // Politique d'usage d'OpenStreetMap : 1 requête par seconde, identification du site.
        $wait = 1.05 - (microtime(true) - self::$last);
        if ($wait > 0) {
            usleep((int) ($wait * 1e6));
        }
        self::$last = microtime(true);
        $contact = (string) Settings::get('general.contact_email', '');
        $ua = 'SochauxRetro/1.0 (' . base_url() . ($contact !== '' ? '; ' . $contact : '') . ')';
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query(['q' => $q, 'format' => 'jsonv2', 'addressdetails' => 1, 'limit' => 1, 'accept-language' => 'fr']);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => $ua, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$body) {
            return null;
        }
        $r = json_decode((string) $body, true)[0] ?? null;
        if (!$r) {
            return null;
        }
        return [
            'lat' => round((float) $r['lat'], 5),
            'lng' => round((float) $r['lon'], 5),
            'cc' => strtoupper((string) ($r['address']['country_code'] ?? '')),
            'city' => $r['address']['city'] ?? ($r['address']['town'] ?? ($r['address']['village'] ?? null)),
            'source' => 'nominatim',
        ];
    }

    /**
     * Tâche planifiée : complète les positions manquantes (stades puis lieux de naissance).
     * @return array{stades:int, lieux:int, restants:int}
     */
    public static function run(int $max = 40, bool $online = true): array
    {
        $done = ['stades' => 0, 'lieux' => 0, 'restants' => 0];
        $stades = Collections::get('stades', []);
        $changed = false;
        foreach ($stades as &$st) {
            if (isset($st['lat']) && $st['lat'] !== null) {
                continue;
            }
            if ($max <= 0) {
                $done['restants']++;
                continue;
            }
            $hit = self::stadium($st, $online);
            if ($hit) {
                $st['lat'] = $hit['lat'];
                $st['lng'] = $hit['lng'];
                $st['city'] = $st['city'] ?: (string) ($hit['city'] ?? '');
                $st['country'] = $st['country'] ?: (string) ($hit['cc'] ?? '');
                $changed = true;
                $done['stades']++;
            }
            if (($hit['source'] ?? '') === 'nominatim' || !$hit) {
                $max--;
            }
        }
        unset($st);
        if ($changed) {
            Collections::save('stades', $stades, null, 'Géolocalisation automatique des stades');
        }
        $seen = Collections::get('geo', []);
        foreach (Index::published('personne') as $s) {
            $city = trim((string) ($s['p']['birth_place'] ?? ''));
            if ($city === '') {
                continue;
            }
            $key = trim($city . '|' . ($s['p']['birth_country'] ?? ''), '|');
            if (isset($seen[$key])) {
                continue;
            }
            if ($max <= 0) {
                $done['restants']++;
                continue;
            }
            $known = isset(self::CITIES[Search::norm($city)]);
            if (self::place($city, (string) ($s['p']['birth_country'] ?? ''), $online)) {
                $done['lieux']++;
            }
            $seen[$key] = true;
            if (!$known) {
                $max--;
            }
        }
        @unlink(STORAGE_PATH . '/cache/carte.json');
        return $done;
    }
}
