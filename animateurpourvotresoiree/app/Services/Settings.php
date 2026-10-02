<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

/**
 * Réglages éditables dans le back-office (storage/data/settings.json),
 * fusionnés avec les valeurs par défaut ci-dessous. Les secrets restent dans config/.env.
 */
final class Settings
{
    private static ?array $all = null;

    public static function defaults(): array
    {
        return [
            'site' => [
                'name' => 'Animateur Pour Votre Soirée',
                'short_name' => 'APVS',
                'baseline' => "L'annuaire des pros de l'animation et de l'événementiel",
                'founded' => 2001,
                'company' => 'ASSIDU SARL',
                'siret' => '818 574 077 00011',
                'rcs' => 'RCS de Besançon',
                'address' => '11 rue du Chenois, 25260 Lougres',
                'director' => 'Frédéric Tholomier',
                'host' => '',
                'phone' => '',
                'socials' => ['facebook' => '', 'instagram' => '', 'tiktok' => '', 'youtube' => '', 'linkedin' => ''],
            ],
            'home' => [
                'badge' => '{nb} pros prêts à mettre le feu',
                'title_1' => 'La fête,',
                'title_2' => "c'est",
                'title_em' => 'leur',
                'title_3' => 'métier.',
                'subtitle' => "DJ, magiciens, animateurs enfants, groupes live, photobooths… L'annuaire des pros de l'animation et de l'événementiel, partout en France.",
                'popular' => [
                    ['label' => 'DJ mariage', 'url' => '/dj/'],
                    ['label' => 'Magicien', 'url' => '/magicien/'],
                    ['label' => 'Photobooth', 'url' => '/photobooth/'],
                    ['label' => 'Clown', 'url' => '/animation-enfants/'],
                ],
                'hero_image_1' => '',
                'hero_image_1_alt' => 'DJ en soirée',
                'hero_image_2' => '',
                'hero_image_2_alt' => 'Magicien et enfants',
                'sticker_show' => true,
                'sticker_1' => 'DEVIS',
                'sticker_2' => 'GRATUIT',
                'sticker_3' => 'en 24h',
                'testimonial_use_reviews' => true,
                'testimonial_text' => 'Des milliers de fêtes réussies depuis 2001.',
                'testimonial_author' => 'animateurpourvotresoirée',
                'ticker' => ['DJ', 'Magiciens', 'Karaoké', 'Groupes live', 'Photobooth', 'Clowns', 'Casino', 'Mascottes'],
                'pros_title' => 'Les pros qui',
                'pros_title_em' => 'mettent le feu',
                'events_title' => 'Quelle est',
                'events_title_em' => "l'occasion",
                'how_title' => 'Trois étapes,',
                'how_title_em' => 'zéro prise de tête',
                'steps' => [
                    ['t' => 'Cherchez', 'd' => 'Filtrez par métier, ville et date. Profils vérifiés, vidéos et vrais avis clients.'],
                    ['t' => 'Demandez des devis', 'd' => 'Un seul formulaire, plusieurs pros contactés. Réponses en moins de 24h, gratuitement.'],
                    ['t' => 'Faites la fête', 'd' => "Vous choisissez, vous réservez, vous profitez. Le reste, c'est leur affaire."],
                ],
                'join_kicker' => "Pour les pros de l'animation",
                'join_title' => "Votre agenda mérite d'être",
                'join_title_em' => 'plein',
                'join_text' => 'Créez votre fiche gratuite en 5 minutes et recevez des demandes de clients près de chez vous.',
                'stat1_value' => '{demandes}+',
                'stat1_label' => 'demandes de devis depuis 2003',
                'stat2_value' => '0 €',
                'stat2_label' => 'de commission, inscription 100 % gratuite',
                'featured_count' => 8,
                'local_links' => true,
            ],
            'seo' => [
                'home' => ['title' => 'Animateur pour votre soirée : DJ, magiciens, groupes | Devis gratuit', 'description' => "L'annuaire des pros de l'animation depuis 2001 : {nb} DJ, magiciens, groupes live, animateurs enfants et photobooths partout en France. Devis gratuits, sans commission."],
                'category' => ['title' => '{Many} : {nb} pros pour mariage, anniversaire et soirée', 'description' => "Trouvez {an_one} près de chez vous : {nb} {many} référencés partout en France pour mariage, anniversaire ou soirée d'entreprise. Devis gratuit en 24h."],
                'category_region' => ['title' => '{Many} {in} : {nb} pros pour vos événements', 'description' => "{nb} {many} {in} pour mariage, anniversaire et soirée d'entreprise. Comparez les profils, les avis et demandez vos devis gratuits."],
                'category_dep' => ['title' => '{Many} {in} ({code}) : {nb} pros disponibles', 'description' => "Trouvez {an_one} {in} ({code}) : {nb} professionnels pour mariage, anniversaire, soirée privée ou d'entreprise. Devis gratuit et sans engagement."],
                'category_city' => ['title' => '{Many} {in} ({code}) : les pros près de chez vous', 'description' => "Besoin d'{one} {in} ? {nb} pros de l'animation interviennent {in} et autour. Profils vérifiés, avis clients, devis gratuit en 24h."],
                'all' => ['title' => 'Animateurs, DJ et artistes pour vos événements partout en France', 'description' => "{nb} professionnels de l'animation et de l'événementiel : DJ, groupes, magiciens, animateurs enfants, photobooths. Devis gratuit en 24h."],
                'all_region' => ['title' => 'Animateurs et DJ {in} : {nb} pros de l\'événementiel', 'description' => "Les pros de l'animation {in} : DJ, orchestres, magiciens, animateurs enfants, photobooths. {nb} profils et des devis gratuits."],
                'all_dep' => ['title' => 'Animateurs et DJ {in} ({code}) : {nb} pros', 'description' => "Trouvez votre animateur {in} ({code}) : {nb} DJ, groupes, magiciens et animateurs pour mariage, anniversaire et soirée. Devis gratuit."],
                'all_city' => ['title' => 'Animateur, DJ et artistes {in} ({code})', 'description' => "Les pros de l'animation qui interviennent {in} ({code}) : DJ, groupes, magiciens, animateurs enfants. Demandez vos devis gratuits."],
                'occasion' => ['title' => '{occasion} : DJ, groupes, animateurs | Devis gratuit', 'description' => "{occasion} : trouvez les bons pros parmi {nb} DJ, groupes, magiciens et animateurs partout en France. Devis gratuit et sans engagement."],
                'occasion_dep' => ['title' => '{occasion} {in} ({code}) : {nb} pros', 'description' => "{occasion} {in} ({code}) : {nb} pros disponibles (DJ, groupes, animateurs, photobooths). Comparez et demandez vos devis gratuits."],
                'pro' => ['title' => '{name} – {cat} {in_city} ({code})', 'description' => '{tagline} {Cat} {in_city} ({code}). Demandez un devis gratuit à {name}.'],
                'search' => ['title' => 'Trouver un pro de l\'animation près de chez vous', 'description' => 'Recherchez parmi {nb} DJ, magiciens, groupes et animateurs : par métier, par ville, sur la carte.'],
                'blog' => ['title' => 'Le blog : idées et conseils pour réussir votre fête', 'description' => "Organisation de mariage, anniversaire, soirée d'entreprise : nos conseils, idées d'animation et guides pratiques."],
                'suffix' => ' | animateurpourvotresoiree.com',
                'og_image' => '',
                'noindex_empty' => true,
            ],
            'listing' => ['per_page' => 24, 'radius_km' => 40, 'map' => true],
            'moderation' => [
                'requests_mode' => 'hybrid',
                'messages_mode' => 'hybrid',
                'reviews_mode' => 'manual',
                'auto_threshold' => 30,
                'spam_threshold' => 70,
                'max_recipients' => 40,
                'radius_km' => 50,
            ],
            'registration' => [
                'enabled' => true,
                'auto_approve' => false,
                'require_siren' => false,
                'max_zones' => 10,
                'max_photos' => 12,
            ],
            'reviews' => ['enabled' => true, 'min_length' => 30],
            'antispam' => [
                'honeypot' => true,
                'min_seconds' => 4,
                'pow' => true,
                'pow_difficulty' => 15,
                'turnstile' => false,
                'max_links' => 2,
                'check_mx' => true,
                'block_disposable' => true,
                'ai_scoring' => true,
                'rates' => [
                    'devis' => [5, 3600], 'contact' => [8, 3600], 'review' => [4, 86400],
                    'register' => [3, 3600], 'chat' => [40, 3600], 'site_contact' => [5, 3600], 'reveal' => [30, 3600],
                ],
                'block' => ['ips' => [], 'emails' => [], 'domains' => [], 'words' => []],
            ],
            'ai' => [
                'enabled' => false,
                'roles' => ['assistant' => true, 'moderation' => true, 'seo' => true, 'classification' => true],
                'temperature' => 0.5,
                'daily_limit' => 1500,
                'assistant_name' => 'Confetti',
                'assistant_greeting' => "Salut ! Je suis Confetti 🎉 Dites-moi ce que vous fêtez et où : je vous trouve les bons pros.",
                'assistant_prompt' => '',
            ],
            'ads' => [
                'enabled' => true,
                'auto_ads' => false,
                'cmp' => 'google',
                'test_mode' => false,
                'label' => 'Publicité',
                'slots' => [
                    'home_mid' => ['on' => true, 'id' => '', 'format' => 'auto'],
                    'listing' => ['on' => true, 'id' => '', 'format' => 'auto', 'every' => 8],
                    'pro_side' => ['on' => true, 'id' => '', 'format' => 'auto'],
                    'pro_bottom' => ['on' => true, 'id' => '', 'format' => 'auto'],
                    'blog_inline' => ['on' => true, 'id' => '', 'format' => 'fluid'],
                    'blog_bottom' => ['on' => true, 'id' => '', 'format' => 'auto'],
                ],
                'ads_txt' => '',
            ],
            'analytics' => ['ga4' => '', 'meta_pixel' => '', 'head_html' => '', 'body_html' => ''],
            'notifications' => [
                'digest_hour' => 8,
                'emails' => '',
                'types' => [
                    'pro_registered' => ['email' => 'instant', 'push' => true],
                    'pro_updated' => ['email' => 'digest', 'push' => false],
                    'request_pending' => ['email' => 'instant', 'push' => true],
                    'request_new' => ['email' => 'digest', 'push' => false],
                    'message_pending' => ['email' => 'instant', 'push' => true],
                    'review_pending' => ['email' => 'instant', 'push' => true],
                    'contact' => ['email' => 'instant', 'push' => true],
                    'spam' => ['email' => 'digest', 'push' => false],
                    'error' => ['email' => 'instant', 'push' => true],
                    'security' => ['email' => 'instant', 'push' => true],
                    'system' => ['email' => 'instant', 'push' => true],
                    'campaign' => ['email' => 'off', 'push' => true],
                    'ai' => ['email' => 'digest', 'push' => false],
                ],
            ],
            'mailing' => ['rate_per_minute' => 60, 'track_opens' => true, 'track_clicks' => true, 'signature' => "L'équipe Animateur Pour Votre Soirée"],
            'features' => ['favorites' => true, 'reviews' => true, 'pwa' => true, 'push' => true, 'phone_reveal' => true, 'map' => true],
            'links' => ['pro_website_rel' => 'noopener'],
            'maintenance' => ['message' => 'Le site fait peau neuve, revenez dans quelques minutes 🎉'],
            'backup' => ['keep' => 7, 'include_media' => false],
        ];
    }

    public static function all(): array
    {
        if (self::$all === null) {
            self::$all = Store::doc('settings', self::defaults())->all();
            if (self::$all['site']['name'] === '' || self::$all['site']['name'] === null) {
                self::$all['site']['name'] = (string) Env::get('APP_NAME', 'Animateur Pour Votre Soirée');
            }
        }
        return self::$all;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $v = self::all();
        foreach (explode('.', $key) as $k) {
            if (!is_array($v) || !array_key_exists($k, $v)) {
                return $default;
            }
            $v = $v[$k];
        }
        return $v;
    }

    public static function set(string $key, mixed $value): void
    {
        Store::doc('settings', self::defaults())->set($key, $value);
        self::$all = null;
    }

    public static function merge(array $values): void
    {
        Store::doc('settings', self::defaults())->merge($values);
        self::$all = null;
    }

    public static function reset(): void
    {
        self::$all = null;
    }

    public static function siteName(): string
    {
        return (string) self::get('site.name', 'Animateur Pour Votre Soirée');
    }

    public static function aiOn(string $role): bool
    {
        return (bool) self::get('ai.enabled') && (bool) self::get('ai.roles.' . $role) && (string) Env::get('GEMINI_API_KEY', '') !== '';
    }
}
