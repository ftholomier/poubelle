<?php

declare(strict_types=1);

namespace App\Content;

use App\Core\JsonStore;
use App\Core\Logger;

/**
 * Réglages globaux du site (data/settings.json).
 * Toute clé absente est reprise du schéma par défaut : la production
 * ne peut donc jamais casser après une évolution de structure.
 */
final class Settings
{
    private static ?array $cache = null;

    public static function file(): string
    {
        return DATA_PATH . '/settings.json';
    }

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'site' => [
                'name'      => 'Le comptable à lunettes',
                'legal_name'=> 'Romain Lemaire',
                'tagline'   => [
                    'fr' => 'Accompagnement dédié aux entreprises et à ses dirigeants.',
                    'en' => 'Dedicated support for companies and their leaders.',
                ],
                'email'     => 'contact@lecomptablealunettes.fr',
                'phone'     => '+33134430430',
                'phone_display' => '+33 (0)1 34 430 430',
                'address'   => '28 rue de la bretonnerie',
                'zip'       => '95300',
                'city'      => 'Pontoise',
                'country'   => 'France',
                'hours'     => [
                    'fr' => 'Du lundi au vendredi',
                    'en' => 'Monday to Friday',
                ],
                'map_query' => '28 rue de la bretonnerie, 95300 Pontoise',
                'logo'      => '/assets/img/logo.svg',
                'logo_light'=> '/assets/img/logo-light.svg',
                'favicon'   => '/assets/img/favicon.svg',
                'portrait'  => '/assets/img/romain-lemaire.jpg',
            ],

            /*
             * Charte graphique reprise du site existant :
             *  - bordeaux #921732  : couleur du logo « ROMAIN LEMAIRE » ;
             *  - noir #161922      : fond des visuels et des sections sombres ;
             *  - bleu #0089F7      : couleur d'action (boutons, liens) ;
             *  - typographies Space Grotesk (titres) et Poppins (texte).
             */
            'brand' => [
                'colors' => [
                    'ink'        => '#161922',
                    'ink_soft'   => '#242326',
                    'primary'    => '#921732',
                    'primary_dark' => '#7A1129',
                    'accent'     => '#0089F7',
                    'accent_dark'=> '#006EDF',
                    'mint'       => '#32C5FD',
                    'bg'         => '#FFFFFF',
                    'surface'    => '#F7F7F7',
                    'border'     => '#E4E4E6',
                    'muted'      => '#626262',
                ],
                'fonts' => [
                    'heading' => "'Space Grotesk', 'Segoe UI', system-ui, sans-serif",
                    'body'    => "'Poppins', 'Segoe UI', system-ui, sans-serif",
                    'google'  => 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap',
                ],
                'radius'  => '14px',
                'motion'  => true,
            ],

            'social' => [
                'linkedin'  => 'https://www.linkedin.com/in/romainlemaire-officiel/',
                'instagram' => 'https://www.instagram.com/le_comptable_a_lunettes/',
                'facebook'  => '',
                'youtube'   => '',
                'tiktok'    => '',
            ],

            /* Barre d'appel à l'action collée en bas d'écran, sur toutes les pages */
            'cta' => [
                'sticky_enabled' => true,
                'contact' => [
                    'label' => ['fr' => 'Contactez-moi', 'en' => 'Contact me', 'es' => 'Contácteme', 'de' => 'Kontaktieren Sie mich'],
                    'url'   => '/contact',
                ],
                'coaching' => [
                    'label' => ['fr' => 'Demandez un accompagnement', 'en' => 'Request support', 'es' => 'Solicitar acompañamiento', 'de' => 'Begleitung anfragen'],
                    'url'   => '/contact?sujet=accompagnement',
                    'halo'  => true,
                ],
                'note' => [
                    'fr' => 'On collabore !',
                    'en' => 'Let’s work together!',
                ],
            ],

            /* Fenêtre d'intention de sortie */
            'exit_popup' => [
                'enabled'        => true,
                'delay_ms'       => 1200,
                'frequency_days' => 7,
                'mobile_timeout' => 45,
                'eyebrow' => ['fr' => 'Un instant…', 'en' => 'One moment…'],
                'title'   => [
                    'fr' => 'Avant de partir : 30 minutes pour y voir clair',
                    'en' => 'Before you go: 30 minutes to see clearly',
                ],
                'text' => [
                    'fr' => 'Un premier échange gratuit et sans engagement pour faire le point sur votre situation, vos chiffres et vos projets.',
                    'en' => 'A free, no-strings first call to review your situation, your figures and your plans.',
                ],
                'cta_label'  => ['fr' => 'Réserver mon échange', 'en' => 'Book my call'],
                'cta_url'    => '/contact?sujet=accompagnement',
                'dismiss'    => ['fr' => 'Non merci, je poursuis ma visite', 'en' => 'No thanks, I’ll keep browsing'],
                'capture_email' => true,
            ],

            /* Assistant IA (Gemini) */
            'chatbot' => [
                'enabled'  => true,
                'name'     => ['fr' => 'Léo, assistant du cabinet', 'en' => 'Leo, the firm’s assistant'],
                'welcome'  => [
                    'fr' => 'Bonjour 👋 Je réponds à vos questions sur le cabinet, l’accompagnement et les activités du groupe Evolutis. Que puis-je faire pour vous ?',
                    'en' => 'Hello 👋 I answer questions about the firm, our services and support. How can I help?',
                ],
                'placeholder' => ['fr' => 'Posez votre question…', 'en' => 'Ask your question…'],
                'suggestions' => [
                    ['fr' => 'Qui est Romain Lemaire ?', 'en' => 'Who is Romain Lemaire?'],
                    ['fr' => 'Quels accompagnements proposez-vous ?', 'en' => 'What support do you offer?'],
                    ['fr' => 'Comment vous contacter ?', 'en' => 'How can I contact you?'],
                ],
                'persona' => [
                    'fr' => "Tu es l'assistant du site « Le comptable à lunettes » de Romain Lemaire, magistrat-comptable, expert en conseil et stratégie financière, président fondateur du groupe Evolutis Conseil à Pontoise. Tu réponds en français, avec clarté et précision, en 4 phrases maximum. Tu t'appuies exclusivement sur le contexte fourni. Si l'information n'y figure pas, tu le dis simplement et tu invites à utiliser le bouton « Demandez un accompagnement ». Tu ne donnes jamais de conseil fiscal personnalisé engageant : tu orientes vers un échange avec Romain.",
                ],
                'handoff_label' => ['fr' => 'Parler à Romain', 'en' => 'Talk to Romain'],
            ],

            /* Avis Google */
            'reviews' => [
                'enabled'    => true,
                'mode'       => 'auto',   // auto (API + repli manuel) | manual
                'place_id'   => '',
                'min_rating' => 4,
                'max_items'  => 9,
                'profile_url'=> '',
            ],

            /* Multilingue */
            'i18n' => [
                'default'          => 'fr',
                'enabled'          => ['fr', 'en'],
                'google_translate' => true,
                'auto_hreflang'    => true,
            ],

            'seo' => [
                'title_suffix' => ' - Le comptable à lunettes',
                'description'  => [
                    'fr' => 'Passez à l’action, collaborons ! Romain Lemaire, le comptable à lunettes qui accompagne les entreprises et les dirigeants à la réussite de leurs projets.',
                    'en' => 'Let’s work together! Romain Lemaire, the accountant with glasses, supports companies and their leaders towards successful projects.',
                ],
                'og_image'     => '/assets/img/og-default.svg',
                'robots'       => 'index,follow',
                'analytics_id' => '',
            ],

            'legal' => [
                'siret'     => '38858139900028',
                'rcs'       => '',
                'tva'       => '',
                'order'     => 'Le Comptable à Lunettes — Evolutis Conseil',
                'director'  => 'Romain Lemaire',
                'host'      => 'NUXIT',
            ],

            'forms' => [
                'notify_email'  => '',
                'success_message' => [
                    'fr' => 'Merci ! Votre message est bien arrivé. Romain vous répond sous 24 h ouvrées.',
                    'en' => 'Thank you! Your message has been received. Romain will reply within one business day.',
                ],
                'subjects' => [
                    ['value' => 'accompagnement', 'label' => ['fr' => 'Conseil et accompagnement',  'en' => 'Advisory and support']],
                    ['value' => 'transmission',   'label' => ['fr' => 'Transmission d’entreprise',  'en' => 'Business transfer']],
                    ['value' => 'patrimoine',     'label' => ['fr' => 'Structuration patrimoniale', 'en' => 'Wealth structuring']],
                    ['value' => 'domiciliation',  'label' => ['fr' => 'Domiciliation commerciale',  'en' => 'Business address']],
                    ['value' => 'autre',          'label' => ['fr' => 'Autre demande',              'en' => 'Something else']],
                ],
            ],

            'version'    => 1,
            'updated_at' => '',
        ];
    }

    /** @return array<string,mixed> */
    public static function all(bool $fresh = false): array
    {
        if ($fresh || self::$cache === null) {
            self::$cache = JsonStore::read(self::file(), self::defaults(), $fresh);
        }
        return self::$cache;
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        $current = self::all();
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }
        return $current;
    }

    public static function str(string $path, string $default = ''): string
    {
        $value = self::get($path, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    public static function bool(string $path, bool $default = false): bool
    {
        $value = self::get($path, $default);
        return is_bool($value) ? $value : in_array($value, [1, '1', 'true', 'on'], true);
    }

    /** @return array<int|string,mixed> */
    public static function arr(string $path, array $default = []): array
    {
        $value = self::get($path, $default);
        return is_array($value) ? $value : $default;
    }

    /** @param array<string,mixed> $data */
    public static function save(array $data): void
    {
        $data['updated_at'] = date('c');
        JsonStore::write(self::file(), $data);
        self::$cache = null;
        Logger::audit('settings.save');
    }

    /** Fusionne une portion de réglages sans écraser le reste. */
    public static function patch(string $section, array $values): void
    {
        JsonStore::mutate(self::file(), static function (array $data) use ($section, $values): array {
            $existing = is_array($data[$section] ?? null) ? $data[$section] : [];
            $data[$section]   = array_replace_recursive($existing, $values);
            $data['updated_at'] = date('c');
            return $data;
        }, self::defaults());
        self::$cache = null;
        Logger::audit('settings.patch', ['section' => $section]);
    }

    /** @return array<int,string> langues actives, la langue par défaut en tête */
    public static function languages(): array
    {
        $available = \App\Core\Config::arr('i18n.available', ['fr']);
        $enabled   = self::arr('i18n.enabled', ['fr']);
        $enabled   = array_values(array_intersect(array_map('strval', $enabled), $available));
        $default   = self::str('i18n.default', 'fr');
        if (!in_array($default, $enabled, true)) {
            array_unshift($enabled, $default);
        }
        $enabled = array_values(array_unique($enabled));
        usort($enabled, static fn (string $a, string $b): int => ($a === $default ? -1 : ($b === $default ? 1 : 0)));
        return $enabled ?: ['fr'];
    }
}
