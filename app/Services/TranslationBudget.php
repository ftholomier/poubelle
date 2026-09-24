<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Audit;
use App\Storage\Json;

/**
 * Plafond de traduction : rester dans la franchise gratuite de Google.
 *
 * Google Cloud Translation offre 500 000 caractères par mois, puis facture
 * au caractère. Chaque envoi passe ici avant de partir : au-delà du plafond,
 * plus rien ne part, et le site sert le français — jamais une facture.
 *
 * Le plafond mensuel se répartit au jour le jour : ce qui reste du mois,
 * divisé par les jours qui restent. Sans cela, la tâche planifiée épuiserait
 * le mois en une nuit et les visiteurs des semaines suivantes n'auraient plus
 * rien. Pour la même raison, les lots — tâche planifiée, bouton du
 * back-office — ne prennent que les trois quarts de la journée : le reste
 * attend les visiteurs qui ouvrent une page dans leur langue.
 */
final class TranslationBudget
{
    /** Franchise mensuelle de Google Cloud Translation, en caractères. */
    public const FREE_MONTHLY = 500000;

    /** Part de la journée que les lots peuvent prendre ; le reste revient aux visiteurs. */
    public const BATCH_SHARE = 0.75;

    private static bool $batch = false;
    private static bool $unmetered = false;
    private static string $refusal = '';

    private static function usageFile(): string
    {
        return Config::path('data') . '/private/translate-usage.json';
    }

    private static function settingsFile(): string
    {
        return Config::path('data') . '/private/translate-budget.json';
    }

    /**
     * Réglages en vigueur : ceux du back-office, sinon ceux de config.php.
     * Un plafond à 0 lève toute limite — la facturation redevient possible.
     *
     * @return array{monthly:int, spread:bool}
     */
    public static function settings(): array
    {
        $saved = Json::read(self::settingsFile());
        return [
            'monthly' => max(0, (int) ($saved['monthly'] ?? Config::get('i18n.budget_monthly', 490000))),
            'spread'  => (bool) ($saved['spread'] ?? Config::get('i18n.budget_spread', true)),
        ];
    }

    public static function save(int $monthly, bool $spread): bool
    {
        return Json::write(self::settingsFile(), ['monthly' => max(0, $monthly), 'spread' => $spread]);
    }

    /**
     * Caractères envoyés ce mois-ci et aujourd'hui. « day_start » retient ce
     * que le mois avait consommé au matin : la part du jour se calcule sur
     * lui, sinon elle rétrécirait à mesure que la journée avance.
     *
     * @return array{month:string, month_chars:int, day:string, day_chars:int, day_start:int, alerted:string}
     */
    public static function usage(): array
    {
        return self::roll(Json::read(self::usageFile()));
    }

    private static function roll(array $usage): array
    {
        $month = date('Y-m');
        $day = date('Y-m-d');
        if (($usage['month'] ?? '') !== $month) {
            $usage = ['alerted' => (string) ($usage['alerted'] ?? '')];
        } elseif (($usage['day'] ?? '') !== $day) {
            $usage['day_chars'] = 0;
            $usage['day_start'] = (int) ($usage['month_chars'] ?? 0);
        }
        return [
            'month'       => $month,
            'month_chars' => (int) ($usage['month_chars'] ?? 0),
            'day'         => $day,
            'day_chars'   => (int) ($usage['day_chars'] ?? 0),
            'day_start'   => (int) ($usage['day_start'] ?? 0),
            'alerted'     => (string) ($usage['alerted'] ?? ''),
        ];
    }

    /** Plafond de la journée, 0 si aucun plafond n'est fixé. */
    public static function dailyLimit(?array $usage = null): int
    {
        $settings = self::settings();
        if ($settings['monthly'] <= 0) {
            return 0;
        }
        $usage ??= self::usage();
        $left = max(0, $settings['monthly'] - $usage['day_start']);
        if (!$settings['spread']) {
            return $left;
        }
        $daysLeft = (int) date('t') - (int) date('j') + 1;
        return intdiv($left, max(1, $daysLeft));
    }

    /** Part de la journée ouverte aux lots. */
    public static function batchLimit(?array $usage = null): int
    {
        return (int) floor(self::dailyLimit($usage) * self::BATCH_SHARE);
    }

    /**
     * L'envoi de $chars caractères tient-il dans le plafond ? Si non, la
     * raison est gardée pour l'écran du back-office.
     */
    public static function allows(int $chars): bool
    {
        if (self::$unmetered || self::settings()['monthly'] <= 0) {
            return true;
        }

        $usage = self::usage();
        $monthly = self::settings()['monthly'];
        if ($usage['month_chars'] + $chars > $monthly) {
            self::$refusal = sprintf('plafond mensuel de %s caractères atteint, reprise le 1er du mois',
                number_format($monthly, 0, ',', ' '));
            self::alertOnce($usage, $monthly);
            return false;
        }

        $limit = self::$batch ? self::batchLimit($usage) : self::dailyLimit($usage);
        // Une pièce plus grosse qu'une journée — une longue page juridique —
        // passe seule, un jour où rien n'est encore parti : sinon elle ne
        // serait jamais traduite. Les jours suivants se partagent le reste.
        $oversized = $usage['day_chars'] === 0 && $chars > $limit;
        if (!$oversized && $usage['day_chars'] + $chars > $limit) {
            self::$refusal = self::$batch
                ? 'part du jour réservée aux lots épuisée, reprise demain'
                : 'plafond du jour atteint, reprise demain';
            return false;
        }
        return true;
    }

    /** Raison du dernier refus, vide s'il n'y en a pas eu. */
    public static function refusal(): string
    {
        return self::$refusal;
    }

    /**
     * Recale le compteur du mois sur le relevé de la console Google : un
     * déploiement en cours de mois, ou une clé qui sert aussi ailleurs, et le
     * site ne voit pas tout ce qui a été consommé. La journée garde son compte.
     */
    public static function adjust(int $monthChars): void
    {
        $monthChars = max(0, $monthChars);
        $handle = @fopen(self::usageFile(), 'c+');
        if ($handle === false) {
            return;
        }
        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $usage = self::roll(is_string($raw) && $raw !== '' ? (array) json_decode($raw, true) : []);
            $usage['month_chars'] = max($monthChars, $usage['day_chars']);
            $usage['day_start'] = max(0, $usage['month_chars'] - $usage['day_chars']);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($usage));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** Compte des caractères réellement facturés par Google. */
    public static function record(int $chars): void
    {
        if ($chars <= 0) {
            return;
        }
        $handle = @fopen(self::usageFile(), 'c+');
        if ($handle === false) {
            return;
        }
        // Deux visiteurs traduisent en même temps : sans verrou, l'un des deux
        // comptes se perdrait, et le plafond serait dépassé en silence.
        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $usage = self::roll(is_string($raw) && $raw !== '' ? (array) json_decode($raw, true) : []);
            $usage['month_chars'] += $chars;
            $usage['day_chars'] += $chars;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($usage));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** Exécute un lot : il laisse aux visiteurs leur part de la journée. */
    public static function batch(callable $run): mixed
    {
        $previous = self::$batch;
        self::$batch = true;
        try {
            return $run();
        } finally {
            self::$batch = $previous;
        }
    }

    /** Hors plafond : le test de la clé au back-office, une poignée de caractères. */
    public static function unmetered(callable $run): mixed
    {
        $previous = self::$unmetered;
        self::$unmetered = true;
        try {
            return $run();
        } finally {
            self::$unmetered = $previous;
        }
    }

    /**
     * Un e-mail par mois, pas un par refus : le plafond mensuel atteint met la
     * traduction à l'arrêt jusqu'au 1er, l'exploitant doit le savoir.
     */
    private static function alertOnce(array $usage, int $monthly): void
    {
        if ($usage['alerted'] === $usage['month']) {
            return;
        }
        $handle = @fopen(self::usageFile(), 'c+');
        if ($handle === false) {
            return;
        }
        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $current = self::roll(is_string($raw) && $raw !== '' ? (array) json_decode($raw, true) : []);
            if ($current['alerted'] === $current['month']) {
                return;
            }
            $current['alerted'] = $current['month'];
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($current));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        Audit::log('translate.budget_reached', ['month' => $usage['month'], 'chars' => $usage['month_chars']]);
        Notifier::notify('translate', 'Plafond de traduction atteint', [
            'Mois'       => $usage['month'],
            'Consommé'   => number_format($usage['month_chars'], 0, ',', ' ') . ' caractères',
            'Plafond'    => number_format($monthly, 0, ',', ' ') . ' caractères',
            'Conséquence' => 'plus aucune traduction jusqu’au 1er du mois ; le site sert le français',
        ], '/admin/traductions');
    }
}
