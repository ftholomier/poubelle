<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Domain\CvRepository;
use App\Domain\JobRepository;
use App\Storage\Audit;
use App\Storage\Json;

/**
 * Traduction des fiches — offres d'emploi et profils.
 *
 * Les pages éditoriales passent par PageRepository ; ici on traite ce qui est
 * déposé par les utilisateurs, et qui change bien plus souvent. Deux règles :
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

    public static function enabled(string $type): bool
    {
        return (bool) Config::get('i18n.translate_' . ($type === 'job' ? 'jobs' : 'cv'), false);
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
     * Traduit tout ce qui manque. Le plafond évite qu'un clic déclenche des
     * milliers d'appels et une facture inattendue.
     *
     * @return array{done:int, skipped:int, failed:int}
     */
    public static function translateMissing(?string $onlyLang = null, int $max = 120): array
    {
        $done = $skipped = $failed = $remaining = 0;
        if (!Translator::available()) {
            return ['done' => 0, 'skipped' => 0, 'failed' => 0, 'remaining' => 0];
        }

        $languages = $onlyLang !== null ? [$onlyLang] : array_keys(I18n::languages());
        $budget = true;

        foreach (['job' => JobRepository::all(), 'cv' => CvRepository::all()] as $type => $records) {
            if (!self::enabled($type)) {
                continue;
            }
            foreach ($records as $record) {
                // On ne traduit que ce qui est visible : inutile de payer pour
                // un brouillon ou une annonce expirée.
                if (($record['status'] ?? '') !== 'publish') {
                    continue;
                }
                foreach ($languages as $lang) {
                    if ($lang === 'fr') {
                        continue;
                    }
                    if (self::isFresh($record, $type, $lang)) {
                        $skipped++;
                        continue;
                    }
                    // Plafond atteint : on continue de compter ce qui reste,
                    // pour annoncer combien de relances sont nécessaires.
                    if (!$budget || $done + $failed >= $max) {
                        $budget = false;
                        $remaining++;
                        continue;
                    }
                    if (self::translate($record, $type, $lang)) {
                        $done++;
                    } else {
                        $failed++;
                    }
                }
            }
        }

        Audit::log('i18n.records_translated',
            ['done' => $done, 'failed' => $failed, 'remaining' => $remaining]);
        return ['done' => $done, 'skipped' => $skipped, 'failed' => $failed, 'remaining' => $remaining];
    }

    /**
     * Traduction à la demande, sur une page de détail consultée dans une
     * langue non encore traduite. Bornée par IP pour ne pas transformer un
     * robot d'indexation en facture.
     */
    public static function translateOnDemand(array $record, string $type, string $lang, string $ip): array
    {
        if ($lang === 'fr') {
            return $record;
        }

        // Une traduction déjà en cache se sert toujours : l'API n'est
        // nécessaire que pour la produire, pas pour la relire.
        if (self::isFresh($record, $type, $lang)) {
            return self::apply($record, $type, $lang);
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

    /** État de la traduction, pour l'écran du back-office. */
    public static function stats(): array
    {
        $out = [];
        foreach (['job' => JobRepository::all(), 'cv' => CvRepository::all()] as $type => $records) {
            $published = array_values(array_filter($records,
                static fn(array $r) => ($r['status'] ?? '') === 'publish'));
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
