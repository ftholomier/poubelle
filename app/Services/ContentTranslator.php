<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Domain\CvRepository;
use App\Domain\JobRepository;
use App\Domain\PageRepository;
use App\Domain\TradeRepository;
use App\Storage\Audit;
use App\Storage\Json;

/**
 * Traduction des fiches — offres d'emploi, profils, fiches métiers.
 *
 * Les pages éditoriales passent par PageRepository ; ici on traite les fiches,
 * qui changent bien plus souvent. Deux règles :
 *
 *  • Le cache est indexé sur une empreinte du texte source. Une annonce
 *    modifiée est donc retraduite, et une annonce inchangée ne l'est jamais
 *    deux fois — l'API se facture au caractère.
 *  • Une traduction absente ne masque jamais la fiche : on sert le français.
 */
final class ContentTranslator
{
    /** Champs traduits, par type de fiche. */
    private const FIELDS = [
        'job' => ['title', 'description', 'conditions'],
        'cv'  => ['title', 'summary'],
    ];

    /**
     * Types dont les textes ne tiennent pas en quelques champs : une fiche
     * métier porte des listes, des questions fréquentes, un encadré. Chaque
     * texte y a une adresse — « missions.2 », « faq.1.a » — qui le remet à sa
     * place une fois traduit. Voir segments().
     */
    private const STRUCTURED = ['trade', 'family'];

    /** Réglage du bloc i18n de config.php qui active chaque type. */
    private const SETTINGS = [
        'job'    => 'translate_jobs',
        'cv'     => 'translate_cv',
        'trade'  => 'translate_trades',
        'family' => 'translate_trades',
    ];

    /** Types de fiches, dans l'ordre de l'écran du back-office. */
    public const TYPES = ['family', 'trade', 'job', 'cv'];

    public static function enabled(string $type): bool
    {
        $setting = self::SETTINGS[$type] ?? '';
        return $setting !== '' && (bool) Config::get('i18n.' . $setting, false);
    }

    /**
     * Textes à traduire d'une fiche structurée, par adresse.
     *
     * @return array<string, string>
     */
    private static function segments(array $record, string $type): array
    {
        $out = [];
        if ($type === 'family') {
            $out['name'] = (string) ($record['name'] ?? '');
            $out['intro'] = (string) ($record['intro'] ?? '');
        } elseif ($type === 'trade') {
            foreach (['name', 'name_f', 'summary', 'intro', 'day', 'training', 'statut', 'career'] as $field) {
                $out[$field] = (string) ($record[$field] ?? '');
            }
            foreach (['missions', 'skills', 'schools'] as $list) {
                foreach (array_values((array) ($record[$list] ?? [])) as $i => $line) {
                    $out[$list . '.' . $i] = (string) $line;
                }
            }
            foreach (array_values((array) ($record['faq'] ?? [])) as $i => $item) {
                $out['faq.' . $i . '.q'] = (string) ($item['q'] ?? '');
                $out['faq.' . $i . '.a'] = (string) ($item['a'] ?? '');
            }
            foreach (['status', 'training', 'sectors'] as $field) {
                $out['brief.' . $field] = (string) ($record['brief'][$field] ?? '');
            }
            $out['pay.note'] = (string) ($record['pay']['note'] ?? '');
            // Titre et description écrits au back-office : sans eux, la page
            // anglaise garderait un titre français dans Google.
            $out['seo.title'] = (string) ($record['seo']['title'] ?? '');
            $out['seo.description'] = (string) ($record['seo']['description'] ?? '');
        }
        return array_filter(array_map('trim', $out), 'strlen');
    }

    /** Remet un texte traduit à son adresse, si elle existe encore dans la fiche. */
    private static function place(array $record, string $path, string $value): array
    {
        $keys = explode('.', $path);
        $leaf = array_pop($keys);
        $node = &$record;
        foreach ($keys as $key) {
            $key = ctype_digit($key) ? (int) $key : $key;
            if (!isset($node[$key]) || !is_array($node[$key])) {
                return $record;
            }
            $node = &$node[$key];
        }
        $leaf = ctype_digit($leaf) ? (int) $leaf : $leaf;
        if (array_key_exists($leaf, $node) && is_string($node[$leaf])) {
            $node[$leaf] = $value;
        }
        unset($node);
        return $record;
    }

    private static function dir(string $type, string $lang): string
    {
        $path = Config::path('data') . '/i18n/' . $type . '/' . preg_replace('/[^a-z]/', '', $lang);
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        return $path;
    }

    private static function file(string $type, string $lang, string $id): string
    {
        return self::dir($type, $lang) . '/' . preg_replace('/[^a-z0-9_-]/i', '', $id) . '.json';
    }

    /** Empreinte du texte source : sert à détecter qu'une fiche a changé. */
    public static function fingerprint(array $record, string $type): string
    {
        if (in_array($type, self::STRUCTURED, true)) {
            return substr(sha1((string) json_encode(self::segments($record, $type))), 0, 16);
        }
        $parts = [];
        foreach (self::FIELDS[$type] ?? [] as $field) {
            $parts[] = (string) ($record[$field] ?? '');
        }
        $parts[] = implode('|', (array) ($record['requirements'] ?? []));
        return substr(sha1(implode("\x1f", $parts)), 0, 16);
    }

    /**
     * Applique la traduction en cache à une fiche, si elle existe et si elle
     * correspond encore au texte source. Sinon renvoie la fiche telle quelle.
     */
    public static function apply(array $record, string $type, string $lang): array
    {
        if ($lang === 'fr' || !I18n::isSupported($lang)) {
            return $record;
        }

        $cached = Json::read(self::file($type, $lang, (string) $record['id']));
        if ($cached === [] || ($cached['source'] ?? '') !== self::fingerprint($record, $type)) {
            return $record;
        }

        foreach ((array) ($cached['values'] ?? []) as $path => $text) {
            if (trim((string) $text) !== '') {
                $record = self::place($record, (string) $path, (string) $text);
            }
        }
        foreach (self::FIELDS[$type] ?? [] as $field) {
            if (($cached['fields'][$field] ?? '') !== '') {
                $record[$field] = $cached['fields'][$field];
            }
        }
        if (!empty($cached['requirements'])) {
            $record['requirements'] = (array) $cached['requirements'];
        }
        $record['translated'] = true;

        return $record;
    }

    /** Vrai si la fiche dispose d'une traduction à jour dans cette langue. */
    public static function isFresh(array $record, string $type, string $lang): bool
    {
        if ($lang === 'fr') {
            return true;
        }
        $cached = Json::read(self::file($type, $lang, (string) $record['id']));
        return $cached !== [] && ($cached['source'] ?? '') === self::fingerprint($record, $type);
    }

    /**
     * Traduit une fiche et met le résultat en cache.
     * Renvoie false si l'API est indisponible ou a échoué.
     */
    public static function translate(array $record, string $type, string $lang): bool
    {
        if ($lang === 'fr' || !Translator::available() || !I18n::isSupported($lang)) {
            return false;
        }

        if (in_array($type, self::STRUCTURED, true)) {
            $segments = self::segments($record, $type);
            if ($segments === []) {
                return false;
            }
            $translated = Translator::translate(array_values($segments), $lang);
            if (count($translated) !== count($segments)) {
                Audit::log('i18n.record_failed', ['type' => $type, 'id' => $record['id'], 'lang' => $lang]);
                return false;
            }
            return self::store($record, $type, $lang, array_combine(array_keys($segments), $translated));
        }

        // Les segments sont envoyés en un seul appel : moins d'aller-retours,
        // et l'API facture au caractère, pas à la requête.
        $segments = [];
        $map = [];
        foreach (self::FIELDS[$type] ?? [] as $field) {
            $value = trim((string) ($record[$field] ?? ''));
            if ($value !== '') {
                $map[] = ['field', $field];
                $segments[] = $value;
            }
        }
        foreach ((array) ($record['requirements'] ?? []) as $i => $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $map[] = ['requirement', $i];
                $segments[] = $line;
            }
        }
        if ($segments === []) {
            return false;
        }

        $translated = Translator::translate($segments, $lang);
        if (count($translated) !== count($segments)) {
            Audit::log('i18n.record_failed', ['type' => $type, 'id' => $record['id'], 'lang' => $lang]);
            return false;
        }

        $fields = [];
        $requirements = [];
        foreach ($map as $i => [$kind, $key]) {
            if ($kind === 'field') {
                $fields[$key] = $translated[$i];
            } else {
                $requirements[$key] = $translated[$i];
            }
        }
        ksort($requirements);

        // L'extrait des listes est dérivé du texte traduit : sinon une page
        // en anglais afficherait des titres traduits et des résumés français.
        $long = (string) ($fields['description'] ?? $fields['summary'] ?? '');

        return Json::write(self::file($type, $lang, (string) $record['id']), [
            'id'           => $record['id'],
            'lang'         => $lang,
            'source'       => self::fingerprint($record, $type),
            'fields'       => $fields,
            'requirements' => array_values($requirements),
            'excerpt'      => $long !== '' ? str_excerpt($long, 180) : '',
            'at'           => date('c'),
        ]);
    }

    /**
     * Met en cache la traduction d'une fiche structurée.
     *
     * @param array<string, string> $values adresse => texte traduit
     */
    private static function store(array $record, string $type, string $lang, array $values): bool
    {
        return Json::write(self::file($type, $lang, (string) $record['id']), [
            'id'     => $record['id'],
            'lang'   => $lang,
            'source' => self::fingerprint($record, $type),
            'values' => $values,
            'at'     => date('c'),
        ]);
    }

    /**
     * Fiches à traduire d'un type : seulement ce qui est visible, inutile de
     * payer pour un brouillon ou une annonce expirée.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function records(string $type): array
    {
        $records = match ($type) {
            'job'    => JobRepository::all(),
            'cv'     => CvRepository::all(),
            'trade'  => TradeRepository::all(),
            'family' => array_values(array_map(
                static fn(array $family): array => $family + ['id' => $family['key'], 'status' => 'publish'],
                Trades::families(),
            )),
            default  => [],
        };
        return array_values(array_filter($records,
            static fn(array $record): bool => ($record['status'] ?? '') === 'publish'));
    }

    /** Ce qui ne périme pas, traduit en premier, langue par langue. */
    private const DURABLE = ['family', 'trade'];

    /** Ce qui passe — les offres expirent —, traduit une fois le durable fait. */
    private const PERISHABLE = ['job', 'cv'];

    /**
     * Traduit ce qui manque, dans l'ordre où cela sert le plus, jusqu'au
     * plafond du jour ou au nombre de fiches demandé.
     *
     *  1. Le durable, langue par langue, l'anglais d'abord : textes
     *     d'interface, pages éditoriales, familles et fiches métiers. Une
     *     langue complète vaut mieux que six à moitié.
     *  2. Le périssable ensuite : offres, et CV s'ils sont activés. D'ici là,
     *     une offre ouverte par un visiteur se traduit à la volée, sur la part
     *     de la journée qui lui est réservée.
     *
     * Le lot laisse aux visiteurs leur part du budget, et s'arrête net au
     * premier refus : ce qui reste attend la prochaine exécution.
     *
     * @return array{strings:int, pages:int, done:int, skipped:int, failed:int, remaining:int, stopped:string}
     */
    public static function translateSite(int $max = 120): array
    {
        $result = ['strings' => 0, 'pages' => 0, 'done' => 0, 'skipped' => 0, 'failed' => 0,
                   'remaining' => 0, 'stopped' => ''];
        if (!Translator::available()) {
            return $result;
        }

        $result = TranslationBudget::batch(static function () use ($max, $result): array {
            $languages = array_values(array_filter(
                array_keys(I18n::languages()),
                static fn(string $lang): bool => $lang !== 'fr',
            ));

            foreach ([self::DURABLE, self::PERISHABLE] as $types) {
                foreach ($languages as $lang) {
                    if ($types === self::DURABLE && !Translator::blocked()) {
                        $result['strings'] += I18n::refresh($lang);
                        $result['pages'] += self::translatePages($lang);
                    }
                    foreach ($types as $type) {
                        if (!self::enabled($type)) {
                            continue;
                        }
                        foreach (self::records($type) as $record) {
                            if (self::isFresh($record, $type, $lang)) {
                                $result['skipped']++;
                                continue;
                            }
                            // Budget épuisé ou plafond de fiches atteint : on
                            // compte ce qui reste, pour dire où l'on en est.
                            if (Translator::blocked() || $result['done'] + $result['failed'] >= $max) {
                                $result['remaining']++;
                                continue;
                            }
                            if (self::translate($record, $type, $lang)) {
                                $result['done']++;
                            } elseif (Translator::blocked()) {
                                $result['remaining']++;
                            } else {
                                $result['failed']++;
                            }
                        }
                    }
                }
            }
            return $result;
        });

        if (Translator::blocked()) {
            $result['stopped'] = Translator::lastError();
        }
        Audit::log('i18n.records_translated', [
            'strings' => $result['strings'], 'pages' => $result['pages'], 'done' => $result['done'],
            'failed' => $result['failed'], 'remaining' => $result['remaining'],
        ]);
        return $result;
    }

    /** Pages éditoriales publiées, dans une langue : celles dont la traduction manque ou a vieilli. */
    private static function translatePages(string $lang): int
    {
        $done = 0;
        foreach (PageRepository::published('fr') as $page) {
            if (Translator::blocked()) {
                break;
            }
            $states = PageRepository::translationStates((string) $page['slug']);
            if (($states[$lang]['state'] ?? '') === 'fresh') {
                continue;
            }
            $translated = Translator::translatePage($page, $lang);
            if ($translated !== null) {
                PageRepository::save($translated, $lang);
                $done++;
            }
        }
        return $done;
    }

    /**
     * Ce que le site a envoyé à Google ce mois-ci, d'après ses propres caches.
     *
     * Le compteur du plafond n'existe pas avant la version qui l'apporte : sans
     * ce relevé, il partirait de zéro et laisserait traduire un mois déjà bien
     * entamé. On compte le texte source de chaque traduction mise en cache ce
     * mois-ci. L'interface n'a pas de date par chaîne : son fichier est compté
     * en entier s'il a bougé ce mois-ci — mieux vaut surestimer que payer.
     *
     * @return array{month:int, today:int, parts: array<string, int>}
     */
    public static function sentThisMonth(): array
    {
        $month = date('Y-m');
        $today = date('Y-m-d');
        $parts = ['interface' => 0, 'pages' => 0, 'family' => 0, 'trade' => 0, 'job' => 0, 'cv' => 0];
        $todayChars = 0;
        $pivot = require Config::path('root') . '/app/Services/lang/fr.php';
        $pages = PageRepository::published('fr');

        foreach (array_keys(I18n::languages()) as $lang) {
            if ($lang === 'fr') {
                continue;
            }

            $file = I18n::cachePath($lang);
            if (is_file($file) && date('Y-m', (int) filemtime($file)) === $month) {
                $size = 0;
                foreach (array_intersect_key($pivot, Json::read($file)) as $text) {
                    $size += mb_strlen((string) $text);
                }
                $parts['interface'] += $size;
                $todayChars += date('Y-m-d', (int) filemtime($file)) === $today ? $size : 0;
            }

            foreach ($pages as $page) {
                $translated = PageRepository::find((string) $page['slug'], $lang);
                $at = (string) ($translated['updated_at'] ?? '');
                if ($translated === null || !empty($translated['fallback']) || substr($at, 0, 7) !== $month) {
                    continue;
                }
                $size = mb_strlen((string) $page['body'] . $page['title'] . $page['excerpt']
                    . ($page['seo']['title'] ?? '') . ($page['seo']['description'] ?? ''));
                $parts['pages'] += $size;
                $todayChars += substr($at, 0, 10) === $today ? $size : 0;
            }

            foreach (self::TYPES as $type) {
                $dir = Config::path('data') . '/i18n/' . $type . '/' . preg_replace('/[^a-z]/', '', $lang);
                foreach (glob($dir . '/*.json') ?: [] as $cacheFile) {
                    $cached = Json::read($cacheFile);
                    $at = (string) ($cached['at'] ?? '');
                    if (substr($at, 0, 7) !== $month) {
                        continue;
                    }
                    $size = self::sourceSize($type, $cached);
                    $parts[$type] += $size;
                    $todayChars += substr($at, 0, 10) === $today ? $size : 0;
                }
            }
        }

        return ['month' => array_sum($parts), 'today' => $todayChars, 'parts' => $parts];
    }

    /**
     * Taille du texte source d'une traduction en cache. La fiche française
     * fait foi ; supprimée depuis, on se rabat sur la longueur traduite.
     */
    private static function sourceSize(string $type, array $cached): int
    {
        $id = (string) ($cached['id'] ?? '');
        $record = match ($type) {
            'job'    => JobRepository::find($id),
            'cv'     => CvRepository::find($id),
            'trade'  => TradeRepository::find($id),
            'family' => isset(Trades::families()[$id]) ? Trades::families()[$id] + ['id' => $id] : null,
            default  => null,
        };
        if ($record !== null) {
            if (in_array($type, self::STRUCTURED, true)) {
                return mb_strlen(implode('', self::segments($record, $type)));
            }
            $text = '';
            foreach (self::FIELDS[$type] ?? [] as $field) {
                $text .= (string) ($record[$field] ?? '');
            }
            return mb_strlen($text . implode('', array_map('strval', (array) ($record['requirements'] ?? []))));
        }
        $text = implode('', array_map('strval', (array) ($cached['values'] ?? [])))
              . implode('', array_map('strval', (array) ($cached['fields'] ?? [])))
              . implode('', array_map('strval', (array) ($cached['requirements'] ?? [])));
        return mb_strlen($text);
    }

    /**
     * Ce qui reste à traduire, en caractères : de quoi dire à l'exploitant
     * combien de jours le budget demandera.
     *
     * @return array{durable:int, perishable:int, langs: array<string, array{durable:int, perishable:int}>}
     */
    public static function pending(): array
    {
        $pivot = require Config::path('root') . '/app/Services/lang/fr.php';
        $out = ['durable' => 0, 'perishable' => 0, 'langs' => []];

        foreach (array_keys(I18n::languages()) as $lang) {
            if ($lang === 'fr') {
                continue;
            }
            $durable = 0;
            foreach (array_diff_key($pivot, Json::read(I18n::cachePath($lang))) as $text) {
                $durable += mb_strlen((string) $text);
            }
            foreach (PageRepository::published('fr') as $page) {
                $states = PageRepository::translationStates((string) $page['slug']);
                if (($states[$lang]['state'] ?? '') !== 'fresh') {
                    $durable += mb_strlen((string) $page['body'] . $page['title'] . $page['excerpt']);
                }
            }
            $perishable = 0;
            foreach ([...self::DURABLE, ...self::PERISHABLE] as $type) {
                if (!self::enabled($type)) {
                    continue;
                }
                foreach (self::records($type) as $record) {
                    if (self::isFresh($record, $type, $lang)) {
                        continue;
                    }
                    $size = in_array($type, self::STRUCTURED, true)
                        ? mb_strlen(implode('', self::segments($record, $type)))
                        : mb_strlen(implode('', array_map(
                            static fn(string $f): string => (string) ($record[$f] ?? ''), self::FIELDS[$type],
                        )) . implode('', (array) ($record['requirements'] ?? [])));
                    if (in_array($type, self::DURABLE, true)) {
                        $durable += $size;
                    } else {
                        $perishable += $size;
                    }
                }
            }
            $out['langs'][$lang] = ['durable' => $durable, 'perishable' => $perishable];
            $out['durable'] += $durable;
            $out['perishable'] += $perishable;
        }
        return $out;
    }

    /**
     * Traduction à la demande, sur une page de détail consultée dans une
     * langue non encore traduite. Bornée par IP pour ne pas transformer un
     * robot d'indexation en facture.
     */
    public static function translateOnDemand(
        array $record,
        string $type,
        string $lang,
        string $ip,
        bool $isBot = false,
    ): array {
        if ($lang === 'fr') {
            return $record;
        }

        // Une traduction déjà en cache se sert toujours : l'API n'est
        // nécessaire que pour la produire, pas pour la relire.
        if (self::isFresh($record, $type, $lang)) {
            return self::apply($record, $type, $lang);
        }

        // Un robot parcourt six variantes de chaque fiche : le laisser
        // déclencher des traductions revient à payer pour des pages que
        // personne ne lit. Il reçoit la version française, signalée comme
        // telle et en noindex.
        if ($isBot) {
            return $record;
        }

        if (!self::enabled($type) || !Translator::available()) {
            return $record;
        }
        if (RateLimit::hit('translate-on-demand', $ip, 12, 3600) > 0) {
            return $record;
        }

        return self::translate($record, $type, $lang)
            ? self::apply($record, $type, $lang)
            : $record;
    }

    /**
     * Applique la traduction à une ligne d'index (liste d'offres ou de CV).
     * Les lignes d'index ne portent que titre et extrait : c'est tout ce qu'il
     * faut traduire pour une carte.
     */
    public static function applyToRow(array $row, string $type, string $lang): array
    {
        if ($lang === 'fr') {
            return $row;
        }
        $cached = Json::read(self::file($type, $lang, (string) ($row['id'] ?? '')));
        if ($cached === []) {
            return $row;
        }
        // Fiche structurée : une ligne d'index ne porte que quelques champs
        // simples — nom, résumé — qu'on remplace s'ils ont leur traduction.
        foreach ((array) ($cached['values'] ?? []) as $path => $text) {
            if (!str_contains((string) $path, '.') && array_key_exists($path, $row) && trim((string) $text) !== '') {
                $row[$path] = (string) $text;
            }
        }
        if (($cached['fields']['title'] ?? '') !== '') {
            $row['title'] = $cached['fields']['title'];
        }
        if (($cached['excerpt'] ?? '') !== '') {
            $row['excerpt'] = $cached['excerpt'];
        }
        return $row;
    }

    /** @param array<int, array> $rows */
    public static function applyToRows(array $rows, string $type, string $lang): array
    {
        if ($lang === 'fr') {
            return $rows;
        }
        return array_map(static fn(array $r) => self::applyToRow($r, $type, $lang), $rows);
    }

    /**
     * Part des fiches d'une liste qui ont une traduction dans cette langue.
     * Sert à dire si une page faite de fiches — la mosaïque des métiers — est
     * vraiment traduite, ou servie en français pour l'essentiel.
     *
     * @param string[] $ids
     */
    public static function coverage(string $type, string $lang, array $ids): float
    {
        if ($lang === 'fr' || $ids === []) {
            return 1.0;
        }
        $found = 0;
        foreach ($ids as $id) {
            if (is_file(self::file($type, $lang, (string) $id))) {
                $found++;
            }
        }
        return $found / count($ids);
    }

    /** État de la traduction, pour l'écran du back-office. */
    public static function stats(): array
    {
        $out = [];
        foreach (self::TYPES as $type) {
            $published = self::records($type);
            $row = ['enabled' => self::enabled($type), 'total' => count($published), 'langs' => []];

            foreach (array_keys(I18n::languages()) as $lang) {
                if ($lang === 'fr') {
                    continue;
                }
                $fresh = 0;
                foreach ($published as $record) {
                    if (self::isFresh($record, $type, $lang)) {
                        $fresh++;
                    }
                }
                $row['langs'][$lang] = $fresh;
            }
            $out[$type] = $row;
        }
        return $out;
    }
}
