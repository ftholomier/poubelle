<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Json;

/**
 * Les sept emplacements AdSense de la maquette.
 * Chacun est activable depuis le back-office ; les scripts ne sont chargés
 * qu'après consentement (voir loadAds() dans app.js).
 */
final class Ads
{
    private static ?array $state = null;

    private static function statePath(): string
    {
        return Config::path('data') . '/private/ads.json';
    }

    /** Unité servie par tout emplacement laissé vide. */
    public static function defaultSlot(): string
    {
        return trim((string) Config::secret('adsense_default_slot', ''));
    }

    /**
     * Deux façons de diffuser, au choix depuis le back-office.
     *
     * « auto » : les annonces automatiques de Google. Le seul réglage est
     * l'identifiant éditeur — aucune unité à créer ni à recopier, Google place
     * les annonces lui-même. Les emplacements dessinés ne sont alors pas posés,
     * puisque Google choisit les siens.
     *
     * « slots » : les sept emplacements de la maquette, chacun servi par une
     * unité AdSense identifiée. Placement maîtrisé, statistiques par
     * emplacement, mais il faut créer les unités.
     */
    public static function mode(): string
    {
        $stored = Json::read(self::statePath())['mode'] ?? null;
        if (is_string($stored)) {
            return $stored === 'slots' ? 'slots' : 'auto';
        }

        // Aucun choix enregistré : on déduit plutôt que d'imposer. Des unités
        // déjà saisies veulent dire des emplacements voulus — les ignorer
        // couperait une configuration qui marchait. Sinon, l'automatique, qui
        // ne demande rien de plus que l'identifiant éditeur.
        $slotIds = array_filter(array_map(
            static fn($v): string => trim((string) $v),
            (array) Config::secret('adsense_slots', []),
        ), 'strlen');

        return ($slotIds !== [] || self::defaultSlot() !== '') ? 'slots' : 'auto';
    }

    public static function setMode(string $mode): void
    {
        $state = Json::read(self::statePath());
        $state['mode'] = $mode === 'slots' ? 'slots' : 'auto';
        Json::write(self::statePath(), $state);
        self::$state = null;
    }

    /** Annonces automatiques : rien d'autre que l'identifiant éditeur. */
    public static function isAuto(): bool
    {
        return self::mode() === 'auto' && self::client() !== '';
    }

    /**
     * Le script AdSense est-il utile sur cette page ?
     * En mode auto il suffit de l'identifiant éditeur ; en mode emplacements
     * il faut au moins une unité effectivement servie.
     */
    public static function scriptNeeded(): bool
    {
        if (self::client() === '') {
            return false;
        }
        if (self::mode() === 'auto') {
            return true;
        }
        foreach (array_keys(self::slots()) as $name) {
            if (self::isLive($name)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string, array{format:string,label:string,enabled:bool,
     *                             slot:string,own:string,inherited:bool}>
     */
    public static function slots(): array
    {
        if (self::$state !== null) {
            return self::$state;
        }

        $configured = (array) Config::get('ads.slots', []);
        $overrides = Json::read(self::statePath());
        $slotIds = (array) Config::secret('adsense_slots', []);
        $default = self::defaultSlot();

        $out = [];
        foreach ($configured as $name => $slot) {
            // Un emplacement sans identifiant propre retombe sur l'unité par
            // défaut : une seule unité display suffit à couvrir le site.
            $own = trim((string) ($slotIds[$name] ?? ''));
            $out[$name] = [
                'format'    => (string) $slot['format'],
                'label'     => (string) $slot['label'],
                'enabled'   => (bool) ($overrides[$name] ?? $slot['enabled']),
                'slot'      => $own !== '' ? $own : $default,
                'own'       => $own,
                'inherited' => $own === '' && $default !== '',
            ];
        }
        return self::$state = $out;
    }

    public static function isEnabled(string $name): bool
    {
        return (bool) (self::slots()[$name]['enabled'] ?? false);
    }

    public static function client(): string
    {
        return (string) Config::get('ads.client', '');
    }

    /** L'unité n'est servie que si le compte et l'identifiant d'emplacement existent. */
    public static function isLive(string $name): bool
    {
        if (self::mode() === 'auto') {
            return false;   // Google place les annonces, pas nous.
        }
        $slot = self::slots()[$name] ?? null;
        return $slot !== null && $slot['enabled'] && self::client() !== '' && $slot['slot'] !== '';
    }

    public static function setEnabled(string $name, bool $enabled): void
    {
        $overrides = Json::read(self::statePath());
        $overrides[$name] = $enabled;
        Json::write(self::statePath(), $overrides);
        self::$state = null;
    }
}
