<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Ai\Assistant;
use App\Ai\KnowledgeBase;
use App\Content\Blocks;
use App\Content\Bootstrap;
use App\Content\Leads;
use App\Content\Media;
use App\Content\Pages;
use App\Content\Reviews;
use App\Content\Settings;
use App\Core\Config;
use App\Core\Installer;
use App\Core\JsonStore;
use App\Core\Logger;
use App\Core\View;
use App\Http\Request;
use App\Http\Response;
use App\I18n\Translator;
use App\Security\Auth;
use App\Security\Csrf;
use App\Security\Sanitizer;
use App\Security\Session;

/**
 * Back-office : tout le site se pilote depuis ces écrans.
 */
final class AdminController
{
    /* ------------------------------------------------------------------ */
    /* Garde d'accès                                                       */
    /* ------------------------------------------------------------------ */
    private static function guard(Request $request): array
    {
        Session::start();
        Response::securityHeaders(true);

        if (Installer::needsSetup()) {
            Response::redirect('/admin/installation');
        }
        $user = Auth::user();
        if ($user === null) {
            Session::set('_intended', $request->path);
            Response::redirect('/admin/login');
        }
        Translator::boot(Settings::str('i18n.default', 'fr'));
        return $user;
    }

    /** Vérifie le jeton CSRF d'un envoi de formulaire. */
    private static function checkToken(Request $request, string $form): bool
    {
        if (!Csrf::check($request->str('_token'), $form)) {
            Session::flash('error', 'Session expirée : votre modification n’a pas été enregistrée. Réessayez.');
            return false;
        }
        return true;
    }

    /** @param array<string,mixed> $data */
    private static function view(string $template, array $user, array $data = []): void
    {
        View::display('admin/' . $template, array_merge([
            'user'      => $user,
            'settings'  => Settings::all(),
            'flash'     => Session::pullFlash(),
            'languages' => Settings::languages(),
            'newLeads'  => Leads::countNew(),
            'active'    => $template,
        ], $data));
    }

    /* ------------------------------------------------------------------ */
    /* Tableau de bord                                                     */
    /* ------------------------------------------------------------------ */
    public static function dashboard(Request $request): void
    {
        $user = self::guard($request);

        $pages = Pages::allForAdmin();
        $published = array_filter($pages, static fn (array $p): bool => ($p['status'] ?? '') === 'published');

        self::view('dashboard', $user, [
            'stats' => [
                'pages'      => count($pages),
                'published'  => count($published),
                'drafts'     => count($pages) - count($published),
                'leads'      => count(Leads::month()),
                'new_leads'  => Leads::countNew(),
                'reviews'    => count(Reviews::manual()['items']),
                'documents'  => count(Media::all(Media::KIND_DOC)),
                'medias'     => count(Media::all(Media::KIND_MEDIA)),
            ],
            'recentLeads' => array_slice(Leads::month(), 0, 6),
            'recentPages' => array_slice($pages, 0, 6),
            'kb'          => KnowledgeBase::stats(),
            'aiReady'     => Assistant::configured(),
            'health'      => self::health(),
        ]);
    }

    /** Contrôles techniques affichés sur le tableau de bord. */
    private static function health(): array
    {
        return [
            'data_writable'   => is_writable(DATA_PATH),
            'backup_writable' => is_writable(DATA_PATH . '/backups'),
            'uploads_writable'=> is_writable(DATA_PATH . '/uploads'),
            'data_outside_web'=> !str_starts_with(realpath(DATA_PATH) ?: '', realpath(PUBLIC_PATH) ?: 'x'),
            'https'           => Session::isHttps(),
            'mail'            => Config::str('mail.transport') !== 'log',
            'ai_key'          => Assistant::configured(),
            'reviews_key'     => Config::str('reviews.api_key') !== '',
            'app_key'         => Config::str('app.key') !== '',
            'debug_off'       => !Config::bool('app.debug', false),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Pages                                                               */
    /* ------------------------------------------------------------------ */
    public static function pages(Request $request): void
    {
        $user = self::guard($request);

        if ($request->isPost() && self::checkToken($request, 'pages')) {
            $action = $request->str('action');

            if ($action === 'delete') {
                $slug = $request->str('slug');
                Session::flash(
                    Pages::delete($slug) ? 'success' : 'error',
                    Pages::delete($slug) ? 'Page supprimée.' : 'Suppression impossible (page d’accueil ou introuvable).'
                );
            } elseif ($action === 'duplicate') {
                $new = Pages::duplicate($request->str('slug'));
                Session::flash($new ? 'success' : 'error', $new ? 'Page dupliquée en brouillon.' : 'Duplication impossible.');
            } elseif ($action === 'reorder') {
                $order = $request->arr('order');
                Pages::reorder(array_map('strval', $order));
                Session::flash('success', 'Ordre du menu enregistré.');
            } elseif ($action === 'rebuild') {
                Pages::rebuildIndex();
                Session::flash('success', 'Index des pages reconstruit.');
            }
            Response::redirect('/admin/pages');
        }

        self::view('pages', $user, ['pages' => Pages::allForAdmin()]);
    }

    public static function pageCreate(Request $request): void
    {
        $user = self::guard($request);
        $page = \App\Core\Schema::normalize(['status' => 'draft'], Pages::pageSchema());
        self::renderEditor($request, $user, $page, null);
    }

    public static function pageEdit(Request $request, array $args): void
    {
        $user = self::guard($request);
        $slug = (string) ($args['slug'] ?? '');
        $page = Pages::findRaw($slug);

        if ($page === null) {
            Session::flash('error', 'Page introuvable.');
            Response::redirect('/admin/pages');
        }
        $page['blocks'] = array_map([Blocks::class, 'normalize'], array_values(array_filter($page['blocks'], 'is_array')));
        self::renderEditor($request, $user, $page, $slug);
    }

    private static function renderEditor(Request $request, array $user, array $page, ?string $originalSlug): void
    {
        if ($request->isPost()) {
            if (!self::checkToken($request, 'page')) {
                Response::redirect($originalSlug ? '/admin/pages/' . $originalSlug : '/admin/pages/nouvelle');
            }

            // Restauration d'une révision antérieure (reprise de contenu).
            if ($request->str('action') === 'restore' && $originalSlug !== null) {
                $ok = JsonStore::restore(Pages::path($originalSlug), $request->str('revision'));
                Pages::rebuildIndex();
                Session::flash($ok ? 'success' : 'error', $ok ? 'Version restaurée.' : 'Restauration impossible.');
                Response::redirect('/admin/pages/' . $originalSlug);
            }

            $payload = self::readPagePayload($request, $page);

            // Ajout d'un bloc : le gabarit est produit côté serveur, puis la page
            // est enregistrée et rechargée avec son formulaire complet.
            $addType = $request->str('add_block');
            if ($addType !== '' && Blocks::exists($addType)) {
                $payload['blocks'][] = Blocks::normalize(['type' => $addType, 'data' => []]);
            }

            $result  = Pages::save($payload, $originalSlug);

            if ($result['ok']) {
                Session::flash('success', 'Page enregistrée.');
                Response::redirect('/admin/pages/' . $result['slug']);
            }
            Session::flash('error', match ($result['error'] ?? '') {
                'slug_taken'   => 'Cette adresse (slug) est déjà utilisée par une autre page.',
                'invalid_slug' => 'L’adresse (slug) est invalide.',
                default        => 'Enregistrement impossible.',
            });
            $page = $payload;
        }

        self::view('page-edit', $user, [
            'page'      => $page,
            'original'  => $originalSlug,
            'catalog'   => Blocks::catalog(),
            'icons'     => \App\Core\Icons::names(),
            'revisions' => $originalSlug !== null ? JsonStore::revisions(Pages::path($originalSlug)) : [],
        ]);
    }

    /** Reconstruit une page complète à partir du formulaire. */
    private static function readPagePayload(Request $request, array $current): array
    {
        $languages = Settings::languages();

        $page = $current;
        $page['slug']      = Sanitizer::slug($request->str('slug'));
        $page['status']    = $request->str('status') === 'published' ? 'published' : 'draft';
        $page['type']      = $request->str('type') === 'post' ? 'post' : 'page';
        $page['home']      = $request->bool('home');
        $page['order']     = $request->int('order', 100);
        $page['in_menu']   = $request->bool('in_menu');
        $page['in_footer'] = $request->bool('in_footer');
        $page['cover']     = Sanitizer::text($request->str('cover'), 300);
        $page['seo']['og_image'] = Sanitizer::text($request->str('og_image'), 300);
        $page['seo']['noindex']  = $request->bool('noindex');

        foreach (['title', 'nav_label', 'excerpt'] as $field) {
            $values = $request->arr($field);
            foreach ($languages as $lang) {
                $page[$field][$lang] = Sanitizer::text((string) ($values[$lang] ?? ''), 300);
            }
        }
        foreach (['title' => 'seo_title', 'description' => 'seo_description'] as $key => $input) {
            $values = $request->arr($input);
            foreach ($languages as $lang) {
                $page['seo'][$key][$lang] = Sanitizer::text((string) ($values[$lang] ?? ''), 320);
            }
        }

        $page['blocks'] = self::readBlocks($request, $languages);
        return $page;
    }

    /** @return array<int,array<string,mixed>> */
    private static function readBlocks(Request $request, array $languages): array
    {
        $incoming = $request->arr('blocks');
        $blocks = [];

        foreach ($incoming as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $type = (string) ($raw['type'] ?? '');
            if (!Blocks::exists($type)) {
                continue;
            }
            $block = Blocks::normalize([
                'id'      => Sanitizer::text((string) ($raw['id'] ?? ''), 32),
                'type'    => $type,
                'enabled' => !empty($raw['enabled']),
                'anchor'  => Sanitizer::slug((string) ($raw['anchor'] ?? '')) === 'page' ? '' : Sanitizer::slug((string) ($raw['anchor'] ?? '')),
                'spacing' => (string) ($raw['spacing'] ?? 'normal'),
                'theme'   => (string) ($raw['theme'] ?? 'light'),
                'data'    => [],
            ]);
            $block['data'] = self::sanitizeBlockData(
                is_array($raw['data'] ?? null) ? $raw['data'] : [],
                Blocks::schemaFor($type),
                $languages
            );
            $blocks[] = $block;
        }
        return $blocks;
    }

    /**
     * Nettoyage récursif des données d'un bloc, guidé par son schéma.
     * Les champs HTML passent par l'assainisseur, le reste par le filtre texte.
     */
    private static function sanitizeBlockData(array $input, array $schema, array $languages): array
    {
        $out = [];

        foreach ($schema as $key => $default) {
            $value = $input[$key] ?? null;

            // Champ multilingue (schéma { fr: '' }).
            if (is_array($default) && isset($default['fr']) && is_string($default['fr'])) {
                $out[$key] = [];
                foreach ($languages as $lang) {
                    $raw = is_array($value) ? (string) ($value[$lang] ?? '') : '';
                    $out[$key][$lang] = str_contains((string) $key, 'html') || $key === 'answer'
                        ? Sanitizer::html($raw)
                        : Sanitizer::text($raw, 3000);
                }
                continue;
            }

            // Liste d'éléments répétables.
            if (is_array($default) && \App\Core\Schema::isList($default)) {
                $out[$key] = [];
                if (!is_array($value)) {
                    continue;
                }
                $itemSchema = self::itemSchemaFor((string) $key);
                foreach (array_values($value) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $clean = self::sanitizeBlockData($item, $itemSchema, $languages);
                    // On ignore les lignes entièrement vides.
                    if (self::isBlank($clean)) {
                        continue;
                    }
                    $out[$key][] = $clean;
                }
                continue;
            }

            $out[$key] = match (true) {
                is_bool($default) => !empty($value),
                is_int($default)  => is_numeric($value) ? (int) $value : $default,
                default           => Sanitizer::text((string) ($value ?? ''), 400),
            };
        }

        return $out;
    }

    /** Schémas des éléments répétables des blocs. */
    private static function itemSchemaFor(string $key): array
    {
        return match ($key) {
            'items' => [
                'icon' => '', 'title' => ['fr' => ''], 'text' => ['fr' => ''], 'url' => '',
                'link_label' => ['fr' => ''], 'value' => 0, 'prefix' => '', 'suffix' => '',
                'label' => ['fr' => ''], 'question' => ['fr' => ''], 'answer' => ['fr' => ''],
                'image' => '', 'alt' => '',
            ],
            'badges', 'bullets' => ['label' => ['fr' => ''], 'text' => ['fr' => '']],
            default => ['text' => ['fr' => '']],
        };
    }

    private static function isBlank(array $data): bool
    {
        foreach ($data as $value) {
            if (is_array($value)) {
                if (!self::isBlank($value)) {
                    return false;
                }
            } elseif (is_string($value) && trim($value) !== '') {
                return false;
            } elseif (is_int($value) && $value !== 0) {
                return false;
            }
        }
        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Réglages                                                            */
    /* ------------------------------------------------------------------ */
    public static function settings(Request $request): void
    {
        $user = self::guard($request);
        $languages = Settings::languages();

        if ($request->isPost() && self::checkToken($request, 'settings')) {
            $current = Settings::all(true);

            $current['site'] = array_merge($current['site'], [
                'name'          => Sanitizer::text($request->str('site_name'), 120),
                'legal_name'    => Sanitizer::text($request->str('legal_name'), 160),
                'email'         => Sanitizer::text($request->str('site_email'), 180),
                'phone'         => Sanitizer::text($request->str('site_phone'), 40),
                'phone_display' => Sanitizer::text($request->str('site_phone_display'), 40),
                'address'       => Sanitizer::text($request->str('site_address'), 180),
                'zip'           => Sanitizer::text($request->str('site_zip'), 12),
                'city'          => Sanitizer::text($request->str('site_city'), 90),
                'map_query'     => Sanitizer::text($request->str('map_query'), 220),
            ]);
            $current['site']['tagline'] = self::multilang($request->arr('tagline'), $languages);
            $current['site']['hours']   = self::multilang($request->arr('hours'), $languages);

            foreach (['instagram', 'linkedin', 'facebook', 'youtube', 'tiktok'] as $network) {
                $current['social'][$network] = self::safeUrl($request->str('social_' . $network));
            }

            $current['cta']['sticky_enabled'] = $request->bool('sticky_enabled');
            $current['cta']['contact']['label']  = self::multilang($request->arr('cta_contact_label'), $languages);
            $current['cta']['contact']['url']    = self::internalUrl($request->str('cta_contact_url'));
            $current['cta']['coaching']['label'] = self::multilang($request->arr('cta_coaching_label'), $languages);
            $current['cta']['coaching']['url']   = self::internalUrl($request->str('cta_coaching_url'));
            $current['cta']['coaching']['halo']  = $request->bool('cta_halo');
            $current['cta']['note']              = self::multilang($request->arr('cta_note'), $languages);

            $current['exit_popup']['enabled']        = $request->bool('popup_enabled');
            $current['exit_popup']['delay_ms']       = max(0, $request->int('popup_delay', 1200));
            $current['exit_popup']['frequency_days'] = max(1, $request->int('popup_frequency', 7));
            $current['exit_popup']['mobile_timeout'] = max(5, $request->int('popup_mobile', 45));
            $current['exit_popup']['capture_email']  = $request->bool('popup_capture');
            $current['exit_popup']['cta_url']        = self::internalUrl($request->str('popup_cta_url'));
            foreach (['eyebrow', 'title', 'text', 'cta_label', 'dismiss'] as $field) {
                $current['exit_popup'][$field] = self::multilang($request->arr('popup_' . $field), $languages);
            }

            $current['forms']['notify_email']    = Sanitizer::text($request->str('notify_email'), 180);
            $current['forms']['success_message'] = self::multilang($request->arr('success_message'), $languages);

            $current['seo']['title_suffix'] = Sanitizer::text($request->str('title_suffix'), 90);
            $current['seo']['description']  = self::multilang($request->arr('seo_description'), $languages);
            $current['seo']['robots']       = $request->bool('indexable') ? 'index,follow' : 'noindex,nofollow';
            $current['seo']['analytics_id'] = Sanitizer::text($request->str('analytics_id'), 30);
            $current['seo']['og_image']     = Sanitizer::text($request->str('og_image'), 300);

            foreach (['siret', 'rcs', 'tva', 'order', 'director', 'host'] as $field) {
                $current['legal'][$field] = Sanitizer::text($request->str('legal_' . $field), 200);
            }

            Settings::save($current);
            Session::flash('success', 'Réglages enregistrés.');
            Response::redirect('/admin/reglages');
        }

        self::view('settings', $user, []);
    }

    /* ------------------------------------------------------------------ */
    /* Apparence (charte graphique)                                        */
    /* ------------------------------------------------------------------ */
    public static function appearance(Request $request): void
    {
        $user = self::guard($request);

        if ($request->isPost() && self::checkToken($request, 'appearance')) {
            $current = Settings::all(true);
            $defaults = Settings::defaults();

            foreach (array_keys($defaults['brand']['colors']) as $key) {
                $value = strtoupper(trim($request->str('color_' . $key)));
                $current['brand']['colors'][$key] = preg_match('/^#[0-9A-F]{6}$/', $value)
                    ? $value
                    : $defaults['brand']['colors'][$key];
            }
            $radius = $request->int('radius', 18);
            $current['brand']['radius'] = max(0, min(40, $radius)) . 'px';
            $current['brand']['motion'] = $request->bool('motion');

            $heading = Sanitizer::text($request->str('font_heading'), 200);
            $body    = Sanitizer::text($request->str('font_body'), 200);
            $google  = $request->str('font_google');
            $current['brand']['fonts']['heading'] = $heading !== '' ? $heading : $defaults['brand']['fonts']['heading'];
            $current['brand']['fonts']['body']    = $body !== '' ? $body : $defaults['brand']['fonts']['body'];
            $current['brand']['fonts']['google']  = str_starts_with($google, 'https://fonts.googleapis.com/')
                ? $google
                : $defaults['brand']['fonts']['google'];

            $current['site']['logo']    = Sanitizer::text($request->str('logo'), 300);
            $current['site']['favicon'] = Sanitizer::text($request->str('favicon'), 300);

            Settings::save($current);
            Session::flash('success', 'Charte graphique enregistrée.');
            Response::redirect('/admin/apparence');
        }

        self::view('appearance', $user, ['defaults' => Settings::defaults()]);
    }

    /* ------------------------------------------------------------------ */
    /* Traductions                                                         */
    /* ------------------------------------------------------------------ */
    public static function translations(Request $request): void
    {
        $user = self::guard($request);
        $available = Config::arr('i18n.available', ['fr', 'en']);

        if ($request->isPost() && self::checkToken($request, 'translations')) {
            if ($request->str('action') === 'languages') {
                $enabled = array_values(array_intersect(
                    array_map('strval', $request->arr('enabled')),
                    $available
                ));
                $default = in_array($request->str('default'), $available, true) ? $request->str('default') : 'fr';
                if (!in_array($default, $enabled, true)) {
                    $enabled[] = $default;
                }
                Settings::patch('i18n', [
                    'default'          => $default,
                    'enabled'          => $enabled,
                    'google_translate' => $request->bool('google_translate'),
                ]);
                Session::flash('success', 'Langues mises à jour.');
            } else {
                $lang = $request->str('lang');
                if (in_array($lang, $available, true)) {
                    $strings = [];
                    foreach ($request->arr('strings') as $key => $value) {
                        $safeKey = preg_replace('/[^a-z0-9._]/i', '', (string) $key) ?? '';
                        if ($safeKey !== '') {
                            $strings[$safeKey] = Sanitizer::text((string) $value, 600);
                        }
                    }
                    JsonStore::write(DATA_PATH . '/i18n/' . $lang . '.json', $strings);
                    Logger::audit('i18n.save', ['lang' => $lang, 'keys' => count($strings)]);
                    Session::flash('success', 'Traductions enregistrées pour « ' . Translator::label($lang) . ' ».');
                }
            }
            Response::redirect('/admin/traductions');
        }

        $lang = $request->str('lang', Settings::str('i18n.default', 'fr'));
        if (!in_array($lang, $available, true)) {
            $lang = 'fr';
        }
        $reference = JsonStore::read(DATA_PATH . '/i18n/' . Settings::str('i18n.default', 'fr') . '.json', [], true);
        $strings   = JsonStore::read(DATA_PATH . '/i18n/' . $lang . '.json', [], true);

        self::view('translations', $user, [
            'available' => $available,
            'editing'   => $lang,
            'reference' => $reference,
            'strings'   => $strings,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Avis                                                                */
    /* ------------------------------------------------------------------ */
    public static function reviews(Request $request): void
    {
        $user = self::guard($request);

        if ($request->isPost() && self::checkToken($request, 'reviews')) {
            if ($request->str('action') === 'refresh') {
                Reviews::clearCache();
                Session::flash('success', 'Cache des avis Google vidé.');
            } else {
                Settings::patch('reviews', [
                    'enabled'     => $request->bool('enabled'),
                    'mode'        => $request->str('mode') === 'manual' ? 'manual' : 'auto',
                    'place_id'    => Sanitizer::text($request->str('place_id'), 200),
                    'min_rating'  => max(1, min(5, $request->int('min_rating', 4))),
                    'max_items'   => max(1, min(20, $request->int('max_items', 9))),
                    'profile_url' => self::safeUrl($request->str('profile_url')),
                ]);
                Reviews::saveManual($request->arr('items'));
                Session::flash('success', 'Avis enregistrés.');
            }
            Response::redirect('/admin/avis');
        }

        self::view('reviews', $user, [
            'manual' => Reviews::manual()['items'],
            'live'   => Reviews::get(9),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Demandes entrantes                                                  */
    /* ------------------------------------------------------------------ */
    public static function leads(Request $request): void
    {
        $user = self::guard($request);
        $month = $request->str('mois', date('Y-m'));

        if ($request->isPost() && self::checkToken($request, 'leads')) {
            $action = $request->str('action');
            if ($action === 'status') {
                Leads::setStatus($month, $request->str('id'), $request->str('status'));
            } elseif ($action === 'delete') {
                Leads::delete($month, $request->str('id'));
                Session::flash('success', 'Demande supprimée.');
            } elseif ($action === 'export') {
                self::exportLeads($month);
            }
            Response::redirect('/admin/demandes?mois=' . urlencode($month));
        }

        self::view('leads', $user, [
            'leads'  => Leads::month($month),
            'months' => Leads::months(),
            'month'  => $month,
        ]);
    }

    private static function exportLeads(string $month): never
    {
        $leads = Leads::month($month);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="demandes-' . preg_replace('/[^0-9\-]/', '', $month) . '.csv"');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
        fputcsv($out, ['Date', 'Source', 'Nom', 'E-mail', 'Téléphone', 'Entreprise', 'Sujet', 'Message', 'Statut'], ';');
        foreach ($leads as $lead) {
            fputcsv($out, [
                $lead['created_at'], $lead['source'], $lead['name'], $lead['email'], $lead['phone'],
                $lead['company'], $lead['subject'], $lead['message'], $lead['status'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    /* ------------------------------------------------------------------ */
    /* Médias & documents                                                  */
    /* ------------------------------------------------------------------ */
    public static function media(Request $request): void
    {
        self::mediaScreen($request, Media::KIND_MEDIA, 'media', '/admin/medias');
    }

    public static function documents(Request $request): void
    {
        self::mediaScreen($request, Media::KIND_DOC, 'documents', '/admin/documents');
    }

    private static function mediaScreen(Request $request, string $kind, string $template, string $redirect): void
    {
        $user = self::guard($request);

        if ($request->isPost() && self::checkToken($request, 'media')) {
            $action = $request->str('action');

            if ($action === 'upload' && isset($_FILES['file'])) {
                $files = self::normalizeFiles($_FILES['file']);
                $ok = 0;
                $errors = [];
                foreach ($files as $file) {
                    $result = Media::store($file, $kind, [
                        'in_kb'  => $request->bool('in_kb'),
                        'public' => $request->bool('public'),
                    ]);
                    if ($result['ok']) {
                        $ok++;
                    } else {
                        $errors[] = (string) $result['error'];
                    }
                }
                if ($ok > 0) {
                    Session::flash('success', $ok . ' fichier(s) téléversé(s).');
                }
                if ($errors) {
                    Session::flash('error', 'Refusé : ' . implode(', ', array_unique($errors)));
                }
            } elseif ($action === 'delete') {
                Media::delete($kind, $request->str('id'));
                Session::flash('success', 'Fichier supprimé.');
            } elseif ($action === 'update') {
                Media::update($kind, $request->str('id'), [
                    'name'   => Sanitizer::text($request->str('name'), 160),
                    'alt'    => Sanitizer::text($request->str('alt'), 200),
                    'in_kb'  => $request->bool('in_kb'),
                    'public' => $request->bool('public'),
                ]);
                Session::flash('success', 'Fichier mis à jour.');
            }
            Response::redirect($redirect);
        }

        self::view($template, $user, ['items' => Media::all($kind), 'kind' => $kind]);
    }

    /** Normalise $_FILES (champ simple ou multiple) en liste homogène. */
    private static function normalizeFiles(array $field): array
    {
        if (!is_array($field['name'])) {
            return [$field];
        }
        $files = [];
        foreach (array_keys($field['name']) as $index) {
            if (($field['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name'     => $field['name'][$index],
                'type'     => $field['type'][$index],
                'tmp_name' => $field['tmp_name'][$index],
                'error'    => $field['error'][$index],
                'size'     => $field['size'][$index],
            ];
        }
        return $files;
    }

    /* ------------------------------------------------------------------ */
    /* Assistant IA                                                        */
    /* ------------------------------------------------------------------ */
    public static function assistant(Request $request): void
    {
        $user = self::guard($request);
        $languages = Settings::languages();
        $test = null;

        if ($request->isPost() && self::checkToken($request, 'assistant')) {
            $action = $request->str('action');

            if ($action === 'rebuild') {
                $count = KnowledgeBase::rebuild();
                Session::flash('success', 'Base de connaissance reconstruite : ' . $count . ' extraits indexés.');
                Response::redirect('/admin/assistant');
            }
            if ($action === 'test') {
                $test = Assistant::ask($request->str('question'), [], Settings::str('i18n.default', 'fr'));
            } else {
                $suggestions = [];
                foreach ($request->arr('suggestions') as $suggestion) {
                    if (is_array($suggestion)) {
                        $clean = self::multilang($suggestion, $languages);
                        if (trim(implode('', $clean)) !== '') {
                            $suggestions[] = $clean;
                        }
                    }
                }
                Settings::patch('chatbot', [
                    'enabled'       => $request->bool('enabled'),
                    'name'          => self::multilang($request->arr('name'), $languages),
                    'welcome'       => self::multilang($request->arr('welcome'), $languages),
                    'placeholder'   => self::multilang($request->arr('placeholder'), $languages),
                    'persona'       => self::multilang($request->arr('persona'), $languages),
                    'handoff_label' => self::multilang($request->arr('handoff_label'), $languages),
                ]);
                // patch fusionne : on écrase explicitement la liste des suggestions.
                $all = Settings::all(true);
                $all['chatbot']['suggestions'] = $suggestions;
                Settings::save($all);

                Session::flash('success', 'Assistant mis à jour.');
                Response::redirect('/admin/assistant');
            }
        }

        self::view('assistant', $user, [
            'kb'      => KnowledgeBase::stats(),
            'aiReady' => Assistant::configured(),
            'model'   => Config::str('ai.model'),
            'test'    => $test,
            'docs'    => Media::all(Media::KIND_DOC),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Utilisateurs                                                        */
    /* ------------------------------------------------------------------ */
    public static function users(Request $request): void
    {
        $user = self::guard($request);

        if ($request->isPost() && self::checkToken($request, 'users')) {
            $action = $request->str('action');

            if ($action === 'create') {
                if (($user['role'] ?? '') !== Auth::ROLE_ADMIN) {
                    Session::flash('error', 'Seul un administrateur peut créer un compte.');
                } else {
                    $result = Auth::createUser(
                        $request->str('name'),
                        $request->str('email'),
                        (string) $request->input('password', ''),
                        $request->str('role')
                    );
                    Session::flash($result['ok'] ? 'success' : 'error', $result['ok']
                        ? 'Compte créé.'
                        : match ($result['error']) {
                            'invalid_email' => 'Adresse e-mail invalide.',
                            'email_taken'   => 'Cette adresse est déjà utilisée.',
                            'too_short'     => 'Mot de passe trop court (10 caractères minimum).',
                            'too_weak'      => 'Le mot de passe doit mêler lettres et chiffres.',
                            default         => 'Création impossible.',
                        });
                }
            } elseif ($action === 'delete') {
                if (($user['role'] ?? '') !== Auth::ROLE_ADMIN || $request->str('id') === (string) $user['id']) {
                    Session::flash('error', 'Suppression refusée.');
                } else {
                    Session::flash(
                        Auth::deleteUser($request->str('id')) ? 'success' : 'error',
                        'Compte supprimé ou dernier compte protégé.'
                    );
                }
            } elseif ($action === 'password') {
                $result = Auth::changePassword(
                    (string) $user['id'],
                    (string) $request->input('current_password', ''),
                    (string) $request->input('new_password', '')
                );
                Session::flash($result['ok'] ? 'success' : 'error', $result['ok']
                    ? 'Mot de passe modifié.'
                    : match ($result['error']) {
                        'invalid_current' => 'Mot de passe actuel incorrect.',
                        'too_short'       => 'Mot de passe trop court (10 caractères minimum).',
                        'too_weak'        => 'Le mot de passe doit mêler lettres et chiffres.',
                        default           => 'Modification impossible.',
                    });
            } elseif ($action === 'toggle' && ($user['role'] ?? '') === Auth::ROLE_ADMIN) {
                $target = Auth::findById($request->str('id'));
                if ($target && (string) $target['id'] !== (string) $user['id']) {
                    Auth::updateUser((string) $target['id'], ['active' => !($target['active'] ?? true)]);
                    Session::flash('success', 'Statut du compte modifié.');
                }
            }
            Response::redirect('/admin/utilisateurs');
        }

        $users = array_map(static function (array $u): array {
            unset($u['password'], $u['reset_hash']);
            return $u;
        }, Auth::all());

        self::view('users', $user, ['users' => $users]);
    }

    /* ------------------------------------------------------------------ */
    /* Maintenance : sauvegardes, restauration, intégrité                  */
    /* ------------------------------------------------------------------ */
    public static function maintenance(Request $request): void
    {
        $user = self::guard($request);

        if ($request->isPost() && self::checkToken($request, 'maintenance')) {
            $action = $request->str('action');

            if ($action === 'restore') {
                $file = self::resolveDataFile($request->str('file'));
                $ok = $file !== null && JsonStore::restore($file, $request->str('revision'));
                Pages::rebuildIndex();
                JsonStore::flushCache();
                Session::flash($ok ? 'success' : 'error', $ok ? 'Version restaurée.' : 'Restauration impossible.');
            } elseif ($action === 'rebuild-index') {
                Pages::rebuildIndex();
                Session::flash('success', 'Index des pages reconstruit.');
            } elseif ($action === 'rebuild-kb') {
                Session::flash('success', KnowledgeBase::rebuild() . ' extraits réindexés.');
            } elseif ($action === 'clear-cache') {
                foreach (glob(DATA_PATH . '/runtime/*.json') ?: [] as $file) {
                    if (!str_contains($file, 'rate-')) {
                        @unlink($file);
                    }
                }
                JsonStore::flushCache();
                Session::flash('success', 'Caches vidés.');
            } elseif ($action === 'export') {
                self::exportBackup();
            } elseif ($action === 'seed') {
                Bootstrap::seedIfEmpty();
                Session::flash('success', 'Contenu de démarrage vérifié.');
            }
            Response::redirect('/admin/maintenance');
        }

        $files = [];
        foreach (self::dataFiles() as $label => $path) {
            $files[$label] = [
                'path'      => $path,
                'exists'    => is_file($path),
                'size'      => is_file($path) ? filesize($path) : 0,
                'modified'  => is_file($path) ? date('d/m/Y H:i', (int) filemtime($path)) : '—',
                'revisions' => JsonStore::revisions($path),
                'key'       => base64_encode(str_replace(DATA_PATH . '/', '', $path)),
            ];
        }

        self::view('maintenance', $user, [
            'files'  => $files,
            'health' => self::health(),
            'logs'   => self::tailLog(STORAGE_PATH . '/logs/audit.log', 30),
        ]);
    }

    /** @return array<string,string> */
    private static function dataFiles(): array
    {
        $files = [
            'Réglages du site'   => DATA_PATH . '/settings.json',
            'Avis'               => DATA_PATH . '/reviews.json',
            'Comptes'            => DATA_PATH . '/users.json',
            'Index des pages'    => DATA_PATH . '/pages-index.json',
            'Documents'          => DATA_PATH . '/documents.json',
            'Médias'             => DATA_PATH . '/medias.json',
        ];
        foreach (glob(Pages::dir() . '/*.json') ?: [] as $page) {
            $files['Page : ' . basename($page, '.json')] = $page;
        }
        return $files;
    }

    /** Résout une clé encodée vers un fichier de données, sans traversée. */
    private static function resolveDataFile(string $key): ?string
    {
        $relative = base64_decode($key, true);
        if ($relative === false || str_contains($relative, '..')) {
            return null;
        }
        $path = DATA_PATH . '/' . ltrim($relative, '/');
        $real = realpath($path);
        $base = realpath(DATA_PATH);
        if ($real === false || $base === false || !str_starts_with($real, $base)) {
            return null;
        }
        return $real;
    }

    /** Archive ZIP de toutes les données (sauvegarde téléchargeable). */
    private static function exportBackup(): never
    {
        if (!class_exists(\ZipArchive::class)) {
            Session::flash('error', 'L’extension ZIP n’est pas disponible sur ce serveur.');
            Response::redirect('/admin/maintenance');
        }
        $tmp = DATA_PATH . '/runtime/export-' . date('Ymd-His') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            Session::flash('error', 'Création de l’archive impossible.');
            Response::redirect('/admin/maintenance');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(DATA_PATH, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $path = $file->getPathname();
            if (!$file->isFile() || str_contains($path, '/runtime/') || str_ends_with($path, '.lock')) {
                continue;
            }
            $zip->addFile($path, 'data/' . ltrim(str_replace(DATA_PATH, '', $path), '/'));
        }
        $zip->close();

        Logger::audit('backup.export');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="sauvegarde-lcal-' . date('Ymd-His') . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /** @return array<int,string> */
    private static function tailLog(string $file, int $lines): array
    {
        if (!is_file($file)) {
            return [];
        }
        $content = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return array_reverse(array_slice($content, -$lines));
    }

    /* ------------------------------------------------------------------ */
    /* Appels AJAX du back-office                                          */
    /* ------------------------------------------------------------------ */
    public static function ajax(Request $request, array $args): never
    {
        Session::start();
        if (!Auth::check()) {
            Response::json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }
        if (!Csrf::check($request->str('_token'), 'admin')) {
            Response::json(['ok' => false, 'error' => 'csrf'], 419);
        }

        $action = (string) ($args['action'] ?? '');

        switch ($action) {
            case 'block-template':
                $type = $request->str('type');
                if (!Blocks::exists($type)) {
                    Response::json(['ok' => false, 'error' => 'unknown_block'], 422);
                }
                Response::json([
                    'ok'    => true,
                    'block' => Blocks::normalize(['type' => $type, 'data' => []]),
                    'label' => Blocks::labelFor($type),
                ]);

            case 'media-list':
                Response::json(['ok' => true, 'items' => Media::all(Media::KIND_MEDIA)]);

            case 'kb-stats':
                Response::json(['ok' => true, 'stats' => KnowledgeBase::stats()]);

            default:
                Response::json(['ok' => false, 'error' => 'unknown_action'], 404);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Utilitaires                                                         */
    /* ------------------------------------------------------------------ */

    /** @param array<string,mixed> $input @return array<string,string> */
    private static function multilang(array $input, array $languages): array
    {
        $out = [];
        foreach ($languages as $lang) {
            $out[$lang] = Sanitizer::text((string) ($input[$lang] ?? ''), 3000);
        }
        return $out;
    }

    private static function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        return preg_match('#^https://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    private static function internalUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '//')) {
            return '/contact';
        }
        return str_starts_with($url, '/') ? Sanitizer::text($url, 200) : '/contact';
    }
}
