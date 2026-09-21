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
