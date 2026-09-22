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
     * Ce que fait le site, pour l'afficher — jamais pour en décider.
     *
     * « slots » : au moins une unité est configurée, les emplacements la
     * portent. « auto » : aucune unité, seul le script est chargé et Google
     * place ce qu'il veut, si les annonces automatiques sont actives sur le
     * compte. Cet état se déduit de la configuration ; aucun réglage ne peut
     * plus empêcher une unité pourtant saisie de s'afficher.
     */
    public static function mode(): string
    {
        foreach (array_keys(self::slots()) as $name) {
            if (self::isLive($name)) {
                return 'slots';
            }
        }
        return 'auto';
    }

    /** Le code collé par l'éditeur, réaffiché tel quel dans le formulaire. */
    public static function snippet(): string
    {
        return (string) (Json::read(self::statePath())['snippet'] ?? '');
    }

    public static function setSnippet(string $snippet): void
    {
        $state = Json::read(self::statePath());
        $state['snippet'] = mb_substr(trim($snippet), 0, 4000);
        Json::write(self::statePath(), $state);
        self::$state = null;
    }

    /**
     * Qui recueille le consentement publicitaire.
     *
     * « google » : le CMP de Google (Confidentialité et messages, ex-Funding
     * Choices). Depuis janvier 2024, AdSense impose pour le trafic européen un
     * CMP certifié IAB TCF v2.2 ; un bandeau maison n'en est pas un, et sans
     * signal TCF Google ne sert pas d'annonce. Ce CMP est affiché par le script
     * AdSense lui-même : il doit donc se charger dès l'ouverture de la page,
     * sinon la fenêtre de consentement n'a jamais lieu d'apparaître. C'est le
     * fonctionnement prévu et certifié par Google : le script se charge, il
     * demande, et rien n'est déposé ni personnalisé avant la réponse.
     *
     * « site » : le bandeau du site, qui conditionne le chargement du script.
     * Respectueux, mais muet pour Google : à réserver aux cas où AdSense n'est
     * pas utilisé, ou hors d'Europe.
     */
    public static function consentMode(): string
    {
        $stored = Json::read(self::statePath())['consent'] ?? null;
        if (is_string($stored)) {
            return $stored === 'site' ? 'site' : 'google';
        }
        return 'google';
    }

    public static function setConsentMode(string $mode): void
    {
        $state = Json::read(self::statePath());
        $state['consent'] = $mode === 'site' ? 'site' : 'google';
        Json::write(self::statePath(), $state);
        self::$state = null;
    }

    /** Le CMP de Google se charge-t-il sur cette page ? */
    public static function googleConsent(): bool
    {
        return self::consentMode() === 'google' && self::client() !== '';
    }

    /** Le script AdSense n'a besoin que du compte : il sert les deux cas. */
    public static function scriptNeeded(): bool
    {
        return self::client() !== '';
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

    /**
     * Une unité configurée est toujours posée.
     *
     * Elle ne dépend d'aucun « mode » : un réglage capable d'empêcher une unité
     * pourtant saisie de s'afficher est un piège, et c'en fut un. Les annonces
     * automatiques de Google ne s'excluent pas des emplacements du site — elles
     * s'y ajoutent, et se règlent dans la console AdSense, pas ici.
     */
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
