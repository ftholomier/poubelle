<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\JsonStore;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Data\Activity;
use App\Data\Media;
use App\Vitrine\Content;
use App\Vitrine\Documents;
use App\Vitrine\Forms;
use App\Vitrine\Host;
use App\Vitrine\Membership;
use App\Vitrine\Site;
use App\Vitrine\Stats;
use App\Vitrine\Store;

/**
 * Pavé « Site de l'association » du back-office, réservé aux administrateurs (contrôlé par le
 * routeur : /admin/association…) : tableau de bord (ouverture, points à vérifier, demandes),
 * contenus (pages, actions, actualités, agenda, équipe, partenaires, presse, documents,
 * tarifs), adhésions, bénévoles, réglages.
 */
final class Association extends Base
{
    public const TABS = ['pages' => 'Pages', 'actions' => 'Actions', 'actualites' => 'Actualités', 'agenda' => 'Agenda', 'equipe' => 'Équipe', 'partenaires' => 'Partenaires', 'presse' => 'Presse', 'documents' => 'Documents', 'tarifs' => 'Tarifs d’adhésion'];

    /**
     * Contenus en liste (ou objet avec listes). Champs : [libellé, type, options]. Types : text,
     * long, html, int, bool, select, image, date, datetime, link, slug, file.
     */
    public static function schemas(): array
    {
        $verify = [
            'a_verifier' => ['À vérifier (contenu d’exemple)', 'bool', ['help' => 'Coché : invisible du public pour une actualité ou un événement, signalé au tableau de bord sinon.']],
            'a_verifier_note' => ['Ce qui reste à vérifier', 'text', ['max' => 300, 'full' => true]],
        ];
        $poles = ['' => '—'];
        foreach (Store::get('equipe')['poles'] ?? [] as $p) {
            if (!empty($p['key'])) {
                $poles[(string) $p['key']] = (string) ($p['title'] ?? $p['key']);
            }
        }
        $linkHelp = '« /page/ » : ce site · « musee:/page/ » : le musée en ligne · « https://… » : autre site · « social:youtube » : réseau social réglé.';
        return [
            'actions' => [
                'label' => 'Actions', 'item' => 'Action', 'title' => 'title', 'front' => '/nos-actions/',
                'help' => 'Une page par action, dans l’ordre du menu « Nos actions ». ' . $linkHelp,
                'fields' => [
                    'title' => ['Titre', 'text', ['max' => 120, 'full' => true]],
                    'menu' => ['Nom court (menu)', 'text', ['max' => 60]],
                    'slug' => ['Adresse : /nos-actions/…/', 'slug', ['max' => 60]],
                    'icon' => ['Pictogramme (1 à 3 signes)', 'text', ['max' => 3]],
                    'image' => ['Image', 'image', []],
                    'excerpt' => ['Résumé (cartes)', 'text', ['max' => 220, 'full' => true]],
                    'lead' => ['Accroche (haut de la page)', 'long', ['max' => 420]],
                    'body' => ['Texte de la page', 'html', []],
                    'cta_label' => ['Bouton jaune : texte', 'text', ['max' => 60]],
                    'cta_url' => ['Bouton jaune : lien', 'link', []],
                    'cta2_label' => ['Second bouton : texte', 'text', ['max' => 60]],
                    'cta2_url' => ['Second bouton : lien', 'link', []],
                    'stats' => ['Chiffres du musée dans la colonne', 'bool', []],
                    'videos' => ['Dernières vidéos YouTube sous le texte', 'bool', []],
                    'countdown' => ['Compte à rebours du centenaire', 'bool', []],
                    'hidden' => ['Masquer cette action', 'bool', []],
                ] + $verify,
            ],
            'actualites' => [
                'label' => 'Actualités', 'item' => 'Actualité', 'title' => 'title', 'front' => '/actualites/', 'sort' => ['date', 'desc'],
                'help' => 'Triées par date, la plus récente en premier. Une actualité datée dans le futur paraît ce jour-là ; un brouillon ou une actualité « à vérifier » reste invisible du public.',
                'fields' => [
                    'title' => ['Titre', 'text', ['max' => 140, 'full' => true]],
                    'date' => ['Date de publication', 'date', []],
                    'slug' => ['Adresse : /actualites/…/', 'slug', ['max' => 80]],
                    'image' => ['Image', 'image', []],
                    'excerpt' => ['Chapeau (cartes, Google)', 'long', ['max' => 300]],
                    'body' => ['Texte', 'html', []],
                    'draft' => ['Brouillon (invisible du public)', 'bool', []],
                ] + $verify,
            ],
            'agenda' => [
                'label' => 'Agenda', 'item' => 'Événement', 'title' => 'title', 'front' => '/agenda/', 'sort' => ['start', 'asc'],
                'help' => 'Les Rétro-Direct programmés au musée et le centenaire s’ajoutent seuls à l’agenda (Réglages). Un événement passé reste visible dans « Déjà passés ».',
                'fields' => [
                    'title' => ['Titre', 'text', ['max' => 140, 'full' => true]],
                    'start' => ['Début', 'datetime', []],
                    'end' => ['Fin (facultatif)', 'datetime', []],
                    'slug' => ['Adresse : /agenda/…/', 'slug', ['max' => 80]],
                    'place' => ['Lieu', 'text', ['max' => 160]],
                    'address' => ['Adresse du lieu', 'text', ['max' => 200]],
                    'price' => ['Tarif', 'text', ['max' => 80, 'placeholder' => 'Gratuit']],
                    'image' => ['Image', 'image', []],
                    'excerpt' => ['Résumé', 'long', ['max' => 300]],
                    'body' => ['Texte', 'html', []],
                    'link' => ['Bouton : lien (inscription, billetterie…)', 'link', []],
                    'link_label' => ['Bouton : texte', 'text', ['max' => 60, 'placeholder' => 'S’inscrire']],
                    'draft' => ['Brouillon (invisible du public)', 'bool', []],
                ] + $verify,
            ],
            'equipe' => [
                'label' => 'Équipe', 'object' => true, 'front' => '/association/equipe/',
                'help' => 'Un membre n’apparaît sur le site que si son nom est renseigné. Photo : avec l’accord de la personne.',
                'fields' => [],
                'lists' => [
                    'members' => ['Membres', 'Membre', [
                        'name' => ['Nom', 'text', ['max' => 80]],
                        'role' => ['Rôle', 'text', ['max' => 80]],
                        'pole' => ['Pôle', 'select', ['options' => $poles]],
                        'photo' => ['Photo', 'image', []],
                        'text' => ['Quelques mots', 'text', ['max' => 240, 'full' => true]],
                        'hidden' => ['Masquer', 'bool', []],
                    ]],
                    'poles' => ['Pôles de bénévoles', 'Pôle', [
                        'title' => ['Nom du pôle', 'text', ['max' => 60]],
                        'key' => ['Code', 'slug', ['max' => 30]],
                        'icon' => ['Pictogramme', 'text', ['max' => 3]],
                        'text' => ['Description', 'text', ['max' => 240, 'full' => true]],
                    ]],
                ],
            ],
            'partenaires' => [
                'label' => 'Partenaires', 'item' => 'Partenaire', 'title' => 'name', 'front' => '/partenaires/',
                'help' => 'Logos affichés sur la page Partenaires et en bas de l’accueil, dans cet ordre.',
                'fields' => [
                    'name' => ['Nom', 'text', ['max' => 100]],
                    'kind' => ['Catégorie', 'text', ['max' => 60, 'placeholder' => 'Mécène, média, institution…']],
                    'logo' => ['Logo', 'image', []],
                    'url' => ['Site web', 'link', []],
                    'text' => ['Présentation', 'long', ['max' => 400]],
                    'hidden' => ['Masquer', 'bool', []],
                ],
            ],
            'presse' => [
                'label' => 'Presse', 'item' => 'Article', 'title' => 'title', 'front' => '/presse/', 'sort' => ['date', 'desc'],
                'help' => 'Revue de presse : articles, reportages et émissions consacrés à l’association. La liste est masquée tant qu’elle est vide.',
                'fields' => [
                    'title' => ['Titre de l’article', 'text', ['max' => 160, 'full' => true]],
                    'media' => ['Média', 'text', ['max' => 80]],
                    'date' => ['Date', 'date', []],
                    'url' => ['Lien', 'link', []],
                    'excerpt' => ['Extrait', 'long', ['max' => 300]],
                    'hidden' => ['Masquer', 'bool', []],
                ],
            ],
            'documents' => [
                'label' => 'Documents', 'item' => 'Document', 'title' => 'title', 'front' => '/association/statuts-et-documents/',
                'help' => 'Statuts, comptes rendus d’assemblée générale, bulletin d’adhésion… PDF, image ou ZIP, 20 Mo au plus. Les documents sont publics.',
                'fields' => [
                    'title' => ['Titre', 'text', ['max' => 140, 'full' => true]],
                    'file' => ['Fichier', 'file', []],
                    'date' => ['Date', 'date', []],
                    'text' => ['Description', 'text', ['max' => 240, 'full' => true]],
                    'hidden' => ['Masquer', 'bool', []],
                ],
            ],
            'tarifs' => [
                'label' => 'Tarifs d’adhésion', 'object' => true, 'front' => '/nous-soutenir/adherer/',
                'help' => 'Formules proposées sur la page Adhérer, le bulletin à imprimer et le paiement en ligne. Le code identifie la formule dans la liste des adhésions.',
                'fields' => [
                    'a_verifier' => ['Montants d’exemple, encore à valider par le bureau', 'bool', []],
                ],
                'lists' => [
                    'items' => ['Formules', 'Formule', [
                        'label' => ['Nom de la formule', 'text', ['max' => 60]],
                        'key' => ['Code', 'slug', ['max' => 30]],
                        'amount' => ['Montant (€)', 'int', ['min' => 1, 'max' => 5000]],
                        'text' => ['Précision', 'text', ['max' => 160, 'full' => true]],
                        'free' => ['Montant libre à partir de ce prix', 'bool', []],
                        'hidden' => ['Masquer', 'bool', []],
                    ]],
                ],
            ],
        ];
    }

    /**
     * Champ d'un éditeur du pavé. $p : préfixe (« @ » dans une liste, « data. » pour un objet).
     * Types : text, long, html, int, bool, select, image, date, datetime, link, slug, file, lines.
     */
    public static function field(string $p, string $k, array $spec, array $item): string
    {
        [$label, $type, $o] = $spec + [2 => []];
        $v = $item[$k] ?? ($o['default'] ?? null);
        $cls = !empty($o['full']) ? 'f--full' : '';
        $help = isset($o['help']) ? e((string) $o['help']) : null;
        return match ($type) {
            'int' => Form::number($p . $k, $label, $v, ['min' => $o['min'] ?? null, 'max' => $o['max'] ?? null, 'class' => $cls]),
            'bool' => Form::toggle($p . $k, $label, (bool) $v, ['help' => $help]),
            'select' => Form::select($p . $k, $label, (string) ($v ?? ''), $o['options'] ?? [], ['strict' => true]),
            'image' => Form::image($p . $k, $label, is_string($v) && $v !== '' ? $v : null),
            'html' => Form::html($p . $k, $label, (string) ($v ?? ''), []),
            'long' => Form::textarea($p . $k, $label, (string) ($v ?? ''), ['plain' => true, 'rows' => 3, 'class' => 'f--full', 'maxlength' => $o['max'] ?? 600, 'proof' => true]),
            'date' => Form::text($p . $k, $label, (string) ($v ?? ''), ['type' => 'date', 'class' => $cls]),
            'datetime' => Form::text($p . $k, $label, str_replace(' ', 'T', (string) ($v ?? '')), ['type' => 'datetime-local', 'class' => $cls]),
            'link' => Form::text($p . $k, $label, (string) ($v ?? ''), ['placeholder' => '/page/, musee:/page/ ou https://…', 'maxlength' => 300, 'class' => $cls]),
            'slug' => Form::text($p . $k, $label, (string) ($v ?? ''), ['maxlength' => $o['max'] ?? 80, 'placeholder' => 'rempli d’après le titre', 'hint' => 'lettres, chiffres, tirets', 'class' => $cls]),
            'file' => self::fileField($p . $k, $label, (string) ($v ?? '')),
            'lines' => Form::lines($p . $k, $label, is_array($v) ? $v : [], ['add' => 'Ajouter un point']),
            default => Form::text($p . $k, $label, (string) ($v ?? ''), ['maxlength' => $o['max'] ?? 300, 'placeholder' => $o['placeholder'] ?? '', 'class' => $cls, 'proof' => true]),
        };
    }

    /** Fichier déposé (documents) : nom du fichier, bouton d'envoi, lien d'aperçu. */
    private static function fileField(string $name, string $label, string $file): string
    {
        $exists = $file !== '' && Documents::path($file) !== null;
        $attr = str_starts_with($name, '@') ? ' data-field="' . e(substr($name, 1)) . '"' : ' name="' . e($name) . '"';
        return '<div class="f" data-asso-file><span class="f__k">' . e($label) . '</span>'
            . '<input type="hidden"' . $attr . ' value="' . e($exists ? $file : '') . '" data-asso-file-value>'
            . '<div class="row" style="gap:8px"><span class="small" data-asso-file-name style="overflow-wrap:anywhere">' . ($exists ? e($file) . ' · ' . e(self::size(Documents::size($file))) : '<span class="muted">Aucun fichier</span>') . '</span>'
            . '<button type="button" class="btn btn--sm" data-asso-upload>' . ($exists ? 'Remplacer…' : 'Déposer un fichier…') . '</button>'
            . ($exists ? '<a class="btn btn--sm btn--ghost" href="' . e(Host::PREVIEW . '/documents/' . rawurlencode($file)) . '" target="_blank" rel="noopener" data-asso-file-link>Ouvrir ↗</a>' : '')
            . '</div></div>';
    }

    /** Onglets de l'écran Contenus. */
    private static function tabs(string $on): array
    {
        $tabs = [];
        foreach (self::TABS as $k => $l) {
            $tabs[] = [$l, '/admin/association/contenus/' . $k, $k === $on];
        }
        return $tabs;
    }

    private static function meta(string $title, string $nav, array $more = []): array
    {
        return $more + ['title' => $title, 'crumb_html' => 'Site de l’association › <a href="/admin/association">Tableau de bord</a>', 'nav' => $nav];
    }

    // ------------------------------------------------------------------ tableau de bord

    public static function index(Request $req): Response
    {
        $year = (int) date('Y');
        $all = Membership::all();
        $vol = JsonStore::read(Forms::VOLUNTEERS, []) ?: [];
        $msgs = array_values(array_filter(Community::messageList(), fn ($m) => ($m['site'] ?? '') === 'association'));
        $stats = Stats::summary(30);
        $paid = array_filter($all, fn ($a) => $a['status'] === 'paid' && (int) ($a['year'] ?? 0) === $year);
        usort($all, fn ($a, $b) => strcmp((string) $b['created'], (string) $a['created']));
        uasort($vol, fn ($a, $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
        return self::html('admin/association/index', [
            'open' => Site::open(),
            'base' => Host::base(),
            'aliases' => Host::aliases(),
            'checks' => self::checklist(),
            'kpi' => [
                'members' => count($paid),
                'amount' => array_sum(array_map(fn ($a) => (int) $a['amount'], $paid)),
                'waiting' => count(array_filter($all, fn ($a) => $a['status'] === 'offline')),
                'volunteers' => count(array_filter($vol, fn ($v) => ($v['status'] ?? 'nouveau') === 'nouveau')),
                'messages' => count(array_filter($msgs, fn ($m) => ($m['status'] ?? 'nouveau') === 'nouveau')),
                'views' => $stats['total'],
            ],
            'stats' => $stats,
            'latest' => array_slice($all, 0, 5),
            'vol' => array_slice(array_values($vol), 0, 5),
            'msgs' => array_slice($msgs, 0, 5),
            'year' => $year,
        ], self::meta('Site de l’association', 'asso', ['crumb' => 'Site de l’association', 'crumb_html' => null]));
    }

    /** POST /admin/association/ouverture : ouvrir ou fermer le site au public. */
    public static function toggle(Request $req): Response
    {
        $open = ($req->post['open'] ?? '') === '1';
        Settings::save(['vitrine.open' => $open]);
        Activity::log(self::actor(), $open ? 'a ouvert au public' : 'a fermé au public', ['title' => 'Site de l’association', 'path' => '']);
        return self::back('/admin/association', $open ? 'Le site de l’association est ouvert au public.' : 'Le site de l’association est fermé : les visiteurs voient la page d’attente.');
    }

    /**
     * Points à vérifier avant (et après) l'ouverture : [gravité (haute, moyenne, info), texte,
     * bouton, lien]. Les contenus d'exemple « à vérifier », l'équipe, les mentions légales, les
     * réglages indispensables.
     */
    public static function checklist(): array
    {
        $c = [];
        $add = function (string $sev, string $text, string $btn, string $href) use (&$c) {
            $c[] = [$sev, $text, $btn, $href];
        };
        foreach (Content::pageKeys() as $key => $label) {
            $p = Content::page($key);
            foreach ((array) ($p['a_verifier'] ?? []) as $v) {
                if (is_string($v) && trim($v) !== '') {
                    $add('moyenne', 'Page « ' . $label . ' » : ' . $v, 'Modifier', '/admin/association/page/' . $key);
                }
            }
        }
        foreach (Store::get('actions') as $a) {
            if (!empty($a['a_verifier']) && empty($a['hidden'])) {
                $add('moyenne', 'Action « ' . ($a['title'] ?? '') . ' » : ' . ($a['a_verifier_note'] ?? 'contenu d’exemple à vérifier'), 'Modifier', '/admin/association/contenus/actions');
            }
        }
        $news = array_filter(Store::get('actualites'), fn ($n) => !empty($n['a_verifier']));
        if ($news) {
            $add('info', count($news) . ' actualité(s) d’exemple « à vérifier », invisibles du public : à corriger puis décocher, ou à supprimer', 'Actualités', '/admin/association/contenus/actualites');
        }
        $ev = array_filter(Store::get('agenda'), fn ($e) => !empty($e['a_verifier']));
        if ($ev) {
            $add('info', count($ev) . ' événement(s) d’exemple « à vérifier », invisibles du public : vraies dates et lieux, ou suppression', 'Agenda', '/admin/association/contenus/agenda');
        }
        if (!empty(Store::get('tarifs')['a_verifier'])) {
            $add('haute', 'Tarifs d’adhésion : montants d’exemple (15, 10, 25 et 50 €) à valider par le bureau', 'Tarifs', '/admin/association/contenus/tarifs');
        }
        if (!Content::team()) {
            $add('moyenne', 'Équipe : aucun nom renseigné, la page « L’équipe » ne présente que les pôles', 'Équipe', '/admin/association/contenus/equipe');
        }
        if (!Content::documents()) {
            $add('moyenne', 'Statuts et documents : aucun document déposé (statuts, compte rendu d’AG)', 'Documents', '/admin/association/contenus/documents');
        }
        if (Site::email() === '') {
            $add('haute', 'E-mail de réception (adhésions, bénévoles, messages) non réglé', 'Réglages', '/admin/association/reglages');
        }
        $legal = array_filter(['statut juridique' => 'legal.status', 'adresse du siège' => 'legal.address', 'n° RNA ou SIREN' => 'legal.registration', 'directeur de la publication' => 'legal.director'], fn ($k) => trim(strip_tags((string) Settings::get($k, ''))) === '');
        if ($legal) {
            $add('haute', 'Mentions légales à compléter : ' . implode(', ', array_keys($legal)), 'Mentions légales', '/admin/reglages?groupe=legal');
        }
        if (!Membership::methods()) {
            $add('info', 'Adhésion en ligne indisponible (dons désactivés ou clés Stripe / PayPal absentes) : seul le règlement par chèque est proposé', 'Réglages des dons', '/admin/reglages?groupe=donations');
        }
        if ((string) Settings::get('mail.from_email', '') === '' && (string) Settings::get('general.contact_email', '') === '') {
            $add('haute', 'Aucune adresse d’expédition des e-mails : confirmations d’adhésion et réponses ne partiront pas', 'E-mail', '/admin/reglages?groupe=mail');
        }
        $order = ['haute' => 0, 'moyenne' => 1, 'info' => 2];
        usort($c, fn ($a, $b) => $order[$a[0]] <=> $order[$b[0]]);
        return $c;
    }

    // ------------------------------------------------------------------ contenus

    public static function contents(Request $req, string $name): Response
    {
        if ($name === 'pages') {
            $rows = [];
            foreach (Content::pageKeys() as $key => $label) {
                $rows[] = ['key' => $key, 'label' => $label, 'default' => Store::isDefault("page-$key"), 'verify' => count(array_filter((array) (Content::page($key)['a_verifier'] ?? []))), 'versions' => Store::versions("page-$key", 1)];
            }
            return self::html('admin/association/pages', ['rows' => $rows], self::meta('Contenus · Pages', 'asso-contenus', ['tabs' => self::tabs('pages')]));
        }
        $schema = self::schemas()[$name] ?? null;
        if (!$schema) {
            return self::html('admin/message', ['title' => 'Contenu introuvable', 'text' => 'Ce contenu n’existe pas.', 'back' => '/admin/association/contenus/pages'], ['title' => 'Introuvable', 'nav' => 'asso-contenus'], 404);
        }
        $data = Store::get($name);
        if (!empty($schema['object'])) {
            $data += Store::defaults($name);
        }
        return self::html('admin/association/edit', [
            'name' => $name, 'schema' => $schema, 'data' => $data, 'isDefault' => Store::isDefault($name), 'versions' => Store::versions($name),
        ], self::meta('Contenus · ' . $schema['label'], 'asso-contenus', ['tabs' => self::tabs($name), 'scripts' => ['admin/association.js']]));
    }

    public static function contentsSave(Request $req, string $name): Response
    {
        $schema = self::schemas()[$name] ?? null;
        if (!$schema) {
            return self::json(['error' => 'Contenu inconnu.'], 404);
        }
        if ($locked = self::lockedJson('ecran:asso-' . $name, 'ce contenu')) {
            return $locked;
        }
        $in = $req->json();
        if (!empty($schema['object'])) {
            $out = self::cleanItem((array) ($in['data'] ?? []), $schema['fields']);
            foreach ($schema['lists'] ?? [] as $key => [, , $fields]) {
                $out[$key] = [];
                foreach ((array) ($in['data'][$key] ?? []) as $it) {
                    $clean = self::cleanItem((array) $it, $fields);
                    if (self::filled($clean, $fields)) {
                        $out[$key][] = $clean;
                    }
                }
                $out[$key] = self::uniqueSlugs($out[$key], $fields, $key === 'items' ? 'label' : 'title');
            }
        } else {
            $out = [];
            foreach ((array) ($in['items'] ?? []) as $it) {
                $clean = self::cleanItem((array) $it, $schema['fields']);
                if (self::filled($clean, $schema['fields'])) {
                    $out[] = $clean;
                }
            }
            $out = self::uniqueSlugs($out, $schema['fields'], (string) ($schema['title'] ?? 'title'));
            if (!empty($schema['sort'])) {
                [$k, $dir] = $schema['sort'];
                usort($out, fn ($a, $b) => $dir === 'desc' ? strcmp((string) ($b[$k] ?? ''), (string) ($a[$k] ?? '')) : strcmp((string) ($a[$k] ?? ''), (string) ($b[$k] ?? '')));
            }
        }
        Store::save($name, $out, self::actor(), $schema['label']);
        return self::json(['ok' => true, 'message' => $schema['label'] . ' : enregistré.', 'modified' => date('c'), 'savedLabel' => 'Enregistré à ' . date('H:i'), 'reload' => !empty($schema['sort'])]);
    }

    /** POST …/contenus/{nom}/depart et …/page/{clé}/depart : revient au contenu livré avec le site. */
    public static function reset(Request $req, string $name): Response
    {
        $file = Store::DIR . '/' . $name . '.json';
        $valid = isset(self::schemas()[$name]) || (str_starts_with($name, 'page-') && isset(Content::pageKeys()[substr($name, 5)]));
        if ($valid && is_file($file)) {
            @unlink($file);
            Store::forget();
            Activity::log(self::actor(), 'a remis le contenu de départ', ['title' => 'Site de l’association · ' . $name, 'path' => '']);
        }
        $back = str_starts_with($name, 'page-') ? '/admin/association/page/' . substr($name, 5) : '/admin/association/contenus/' . $name;
        return self::back($back, 'Contenu de départ rétabli (votre version reste dans l’historique).');
    }

    /** Texte d'une page : champs déduits du contenu de départ. */
    public static function pageEdit(Request $req, string $key): Response
    {
        $def = Store::defaults('pages')[$key] ?? null;
        if (!$def) {
            return self::html('admin/message', ['title' => 'Page introuvable', 'text' => 'Cette page n’existe pas.', 'back' => '/admin/association/contenus/pages'], ['title' => 'Introuvable', 'nav' => 'asso-contenus'], 404);
        }
        return self::html('admin/association/page', [
            'key' => $key, 'label' => (string) ($def['_label'] ?? $key), 'fields' => self::pageFields($def), 'data' => Content::page($key),
            'isDefault' => Store::isDefault("page-$key"), 'versions' => Store::versions("page-$key"), 'front' => self::pageFront($key),
        ], self::meta('Page · ' . ($def['_label'] ?? $key), 'asso-contenus', ['tabs' => self::tabs('pages')]));
    }

    public static function pageSave(Request $req, string $key): Response
    {
        $def = Store::defaults('pages')[$key] ?? null;
        if (!$def) {
            return self::json(['error' => 'Page inconnue.'], 404);
        }
        if ($locked = self::lockedJson('ecran:asso-page-' . $key, 'cette page')) {
            return $locked;
        }
        $in = (array) ($req->json()['data'] ?? []);
        $out = [];
        foreach (self::pageFields($def) as $k => [$label, $type, $sub]) {
            $v = $in[$k] ?? null;
            $out[$k] = match ($type) {
                'html' => Html::clean(is_string($v) ? $v : ''),
                'image' => ($r = Html::line($v, 300)) !== '' && Media::get($r) ? $r : '',
                'lines' => array_values(array_filter(array_map(fn ($x) => Html::line($x, 300), is_array($v) ? $v : []))),
                'list' => array_values(array_filter(array_map(fn ($it) => self::cleanItem(is_array($it) ? $it : [], $sub), is_array($v) ? $v : []), fn ($it) => self::filled($it, $sub))),
                'long' => Html::line($v, 600),
                default => Html::line($v, 300),
            };
        }
        Store::save("page-$key", $out, self::actor(), 'Page « ' . ($def['_label'] ?? $key) . ' »');
        return self::json(['ok' => true, 'message' => 'Page enregistrée.', 'modified' => date('c'), 'savedLabel' => 'Enregistré à ' . date('H:i')]);
    }

    /** Libellés des champs de pages. */
    private const LABELS = [
        'title' => 'Titre', 'lead' => 'Accroche', 'image' => 'Photo du haut de page', 'intro' => 'Introduction',
        'hero_eyebrow' => 'Grand bandeau : sur-titre', 'hero_title' => 'Grand bandeau : titre', 'hero_lead' => 'Grand bandeau : texte', 'hero_image' => 'Grand bandeau : photo',
        'intro_title' => 'Présentation : titre', 'intro_text' => 'Présentation : texte', 'museum_title' => 'Le musée : titre', 'museum_text' => 'Le musée : texte',
        'centenary_title' => 'Centenaire : titre', 'centenary_text' => 'Centenaire : texte', 'support_title' => 'Nous soutenir : titre', 'support_text' => 'Nous soutenir : texte',
        'newsletter_title' => 'Newsletter : titre', 'newsletter_text' => 'Newsletter : texte',
        'story_title' => 'Notre histoire : titre', 'story' => 'Notre histoire : texte', 'missions_title' => 'Missions : titre', 'missions' => 'Missions',
        'values_title' => 'Valeurs : titre', 'values' => 'Valeurs', 'independence' => 'Encadré sous les valeurs',
        'empty_text' => 'Texte affiché tant que la liste est vide', 'poles_title' => 'Pôles : titre', 'join_title' => 'Appel à rejoindre : titre', 'join_text' => 'Appel à rejoindre : texte',
        'governance' => 'Encadré « Fonctionnement »', 'why_title' => 'Pourquoi nous soutenir : titre', 'why' => 'Pourquoi nous soutenir : texte',
        'benefits_title' => 'Avantages : titre', 'benefits' => 'Avantages des adhérents', 'validity' => 'Durée de validité (sous les formules)',
        'paper_title' => 'Bulletin papier : titre', 'paper_text' => 'Bulletin papier : texte', 'form_title' => 'Formulaire : titre', 'form_text' => 'Formulaire : texte',
        'become_title' => 'Devenir partenaire : titre', 'become_text' => 'Devenir partenaire : texte', 'offers' => 'Formules de partenariat',
        'about_title' => 'L’association en bref : titre', 'about' => 'L’association en bref : texte', 'contact_text' => 'Contact presse : texte',
        'a_verifier' => 'Points à vérifier (contenu d’exemple)', 'icon' => 'Pictogramme', 'text' => 'Texte',
    ];

    /** Champs d'une page, déduits de son contenu de départ : [libellé, type, sous-champs]. */
    public static function pageFields(array $def): array
    {
        $out = [];
        foreach ($def as $k => $v) {
            if ($k === '_label') {
                continue;
            }
            $label = self::LABELS[$k] ?? ucfirst(str_replace('_', ' ', (string) $k));
            if ($k === 'a_verifier') {
                $out[$k] = [$label, 'lines', []];
            } elseif (is_array($v)) {
                $sub = [];
                foreach (array_keys((array) ($v[0] ?? [])) as $sk) {
                    $sub[$sk] = [self::LABELS[$sk] ?? ucfirst((string) $sk), $sk === 'text' ? 'text' : 'text', ['max' => $sk === 'text' ? 400 : ($sk === 'icon' ? 3 : 80), 'full' => $sk === 'text']];
                }
                $out[$k] = [$label, 'list', $sub];
            } elseif (preg_match('/image$/', (string) $k)) {
                $out[$k] = [$label, 'image', []];
            } elseif (is_string($v) && preg_match('#<(p|h2|h3|ul)\b#', $v)) {
                $out[$k] = [$label, 'html', []];
            } else {
                $out[$k] = [$label, is_string($v) && mb_strlen($v) > 90 ? 'long' : 'text', []];
            }
        }
        return $out;
    }

    private static function pageFront(string $key): string
    {
        return [
            'accueil' => '/', 'association' => '/association/', 'equipe' => '/association/equipe/', 'documents' => '/association/statuts-et-documents/',
            'actions' => '/nos-actions/', 'actualites' => '/actualites/', 'agenda' => '/agenda/', 'soutenir' => '/nous-soutenir/', 'adherer' => '/nous-soutenir/adherer/',
            'benevolat' => '/nous-soutenir/benevolat/', 'partenaires' => '/partenaires/', 'presse' => '/presse/', 'contact' => '/contact/',
        ][$key] ?? '/';
    }

    /** Un élément contient-il autre chose que des cases à cocher ? */
    private static function filled(array $item, array $fields): bool
    {
        foreach ($fields as $k => [, $type]) {
            if (in_array($type, ['bool', 'select'], true)) {
                continue;
            }
            $v = $item[$k] ?? null;
            if ($v !== null && $v !== '' && $v !== []) {
                return true;
            }
        }
        return false;
    }

    private static function cleanItem(array $in, array $fields): array
    {
        $out = [];
        foreach ($fields as $k => [, $type, $o]) {
            $v = $in[$k] ?? null;
            $out[$k] = match ($type) {
                'int' => ($v === null || $v === '') ? null : max((int) ($o['min'] ?? PHP_INT_MIN), min((int) ($o['max'] ?? PHP_INT_MAX), (int) $v)),
                'bool' => (bool) $v,
                'select' => isset($o['options'][(string) $v]) ? (string) $v : (string) array_key_first($o['options']),
                'image' => ($r = Html::line($v, 300)) !== '' && Media::get($r) ? $r : null,
                'html' => Html::clean(is_string($v) ? $v : ''),
                'long' => Html::line($v, (int) ($o['max'] ?? 600)),
                'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '',
                'datetime' => preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/', (string) $v, $m) ? $m[1] . ' ' . $m[2] : '',
                'link' => self::link($v),
                'slug' => slugify(Html::line($v, (int) ($o['max'] ?? 80))),
                'file' => Documents::path(Html::line($v, 130)) !== null ? Html::line($v, 130) : '',
                default => Html::line($v, (int) ($o['max'] ?? 300)),
            };
        }
        return $out;
    }

    /** Lien saisi : « /page/ », « musee:/page/ », « social:réseau », « https://… » ; sinon vide. */
    private static function link(mixed $v): string
    {
        $v = trim(Html::line($v, 300));
        if ($v === '') {
            return '';
        }
        if (preg_match('#^https?://\S+$#i', $v) || preg_match('#^musee:/[^\s]*$#', $v) || preg_match('#^social:(facebook|instagram|youtube|x|linkedin)$#', $v)) {
            return $v;
        }
        if (preg_match('#^www\.\S+$#i', $v)) {
            return 'https://' . $v;
        }
        return '/' . ltrim($v, '/');
    }

    /** Adresses (slugs) remplies d'après le titre et rendues uniques dans la liste. */
    private static function uniqueSlugs(array $items, array $fields, string $titleKey): array
    {
        $slugKey = null;
        foreach ($fields as $k => [, $type]) {
            if ($type === 'slug') {
                $slugKey = $k;
                break;
            }
        }
        if ($slugKey === null) {
            return $items;
        }
        $seen = [];
        foreach ($items as &$it) {
            $base = (string) ($it[$slugKey] ?? '') !== '' ? (string) $it[$slugKey] : slugify((string) ($it[$titleKey] ?? $it['title'] ?? $it['label'] ?? 'element'));
            $base = substr($base !== '' ? $base : 'element', 0, 80);
            $s = $base;
            for ($i = 2; isset($seen[$s]); $i++) {
                $s = $base . '-' . $i;
            }
            $seen[$s] = true;
            $it[$slugKey] = $s;
        }
        return $items;
    }

    /** POST /admin/association/documents/envoi : dépôt d'un fichier public (multipart). */
    public static function upload(Request $req): Response
    {
        $f = $req->files['file'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return self::json(['error' => 'Aucun fichier reçu (20 Mo au plus).'], 422);
        }
        $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $f['tmp_name']) ?: '';
        $okMime = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'], 'zip' => ['application/zip', 'application/x-zip-compressed']];
        if (!isset($okMime[$ext]) || !in_array($mime, $okMime[$ext], true)) {
            return self::json(['error' => 'Format refusé : PDF, JPG, PNG, WebP ou ZIP.'], 422);
        }
        if ((int) $f['size'] > Documents::MAX) {
            return self::json(['error' => 'Fichier trop lourd : 20 Mo au plus.'], 422);
        }
        if (!is_dir(Documents::DIR)) {
            mkdir(Documents::DIR, 0775, true);
        }
        $name = Documents::freeName((string) $f['name'], $ext === 'jpeg' ? 'jpg' : $ext);
        $dest = Documents::DIR . '/' . $name;
        if (!move_uploaded_file((string) $f['tmp_name'], $dest) && !(PHP_SAPI === 'cli' && rename((string) $f['tmp_name'], $dest))) {
            return self::json(['error' => 'Enregistrement du fichier impossible.'], 500);
        }
        Activity::log(self::actor(), 'a déposé un document', ['title' => 'Site de l’association · ' . $name, 'path' => '']);
        return self::json(['ok' => true, 'file' => $name, 'size' => self::size((int) filesize($dest)), 'url' => Host::PREVIEW . '/documents/' . rawurlencode($name)]);
    }

    // ------------------------------------------------------------------ adhésions

    public static function memberships(Request $req): Response
    {
        $year = (int) ($req->str('annee') ?: date('Y'));
        $status = $req->str('statut');
        $all = Membership::all();
        $years = array_values(array_unique(array_merge([(int) date('Y')], array_map(fn ($a) => (int) ($a['year'] ?? 0), $all))));
        rsort($years);
        $rows = array_values(array_filter($all, fn ($a) => (int) ($a['year'] ?? 0) === $year && ($status === '' || $a['status'] === $status)));
        usort($rows, fn ($a, $b) => strcmp((string) $b['created'], (string) $a['created']));
        $paid = array_filter($all, fn ($a) => $a['status'] === 'paid' && (int) ($a['year'] ?? 0) === $year);
        return self::html('admin/association/adhesions', [
            'rows' => $rows, 'year' => $year, 'years' => $years, 'status' => $status,
            'count' => count($paid), 'amount' => array_sum(array_map(fn ($a) => (int) $a['amount'], $paid)),
            'tariffs' => Content::tariffs(), 'online' => Membership::methods(),
        ], self::meta('Adhésions', 'asso-adhesions'));
    }

    public static function membership(Request $req, string $id): Response
    {
        $a = Membership::validId($id) ? Membership::get($id) : null;
        if (!$a) {
            return self::html('admin/message', ['title' => 'Adhésion introuvable', 'text' => 'Cette adhésion n’existe pas (ou plus).', 'back' => '/admin/association/adhesions'], ['title' => 'Introuvable', 'nav' => 'asso-adhesions'], 404);
        }
        return self::html('admin/association/adhesion', ['a' => $a], self::meta('Adhésion · ' . trim(($a['member']['first'] ?? '') . ' ' . ($a['member']['last'] ?? '')), 'asso-adhesions', ['crumb_html' => 'Site de l’association › <a href="/admin/association/adhesions">Adhésions</a>']));
    }

    /** Actions sur une adhésion : payée (règlement reçu), annulée, note, e-mail de bienvenue, suppression. */
    public static function membershipAction(Request $req, string $id): Response
    {
        $a = Membership::validId($id) ? Membership::get($id) : null;
        if (!$a) {
            return self::back('/admin/association/adhesions', null, 'Adhésion introuvable.');
        }
        $back = '/admin/association/adhesions/' . $id;
        $do = $req->str('do');
        $who = self::actor()['name'] ?? '';
        switch ($do) {
            case 'paye':
                $provider = isset(Membership::PROVIDERS[$req->str('provider')]) ? $req->str('provider') : 'cheque';
                $ref = Html::line($req->post['ref'] ?? '', 60) ?: 'manuel-' . date('YmdHis');
                $done = Membership::update($id, function ($x) use ($provider, $ref, $who) {
                    $x['payments'][] = ['ref' => $ref, 'amount' => (int) $x['amount'], 'at' => date('c'), 'by' => $who];
                    $x['status'] = 'paid';
                    $x['paid_at'] = date('c');
                    if (!in_array($x['provider'], ['stripe', 'paypal'], true)) {
                        $x['provider'] = $provider;
                    }
                    return $x;
                });
                if ($done && !empty($req->post['welcome'])) {
                    Membership::welcome($done);
                }
                Activity::log(self::actor(), 'a enregistré le règlement d’une adhésion', ['title' => trim($a['member']['first'] . ' ' . $a['member']['last']), 'path' => '']);
                return self::back($back, 'Règlement enregistré : adhésion payée.');
            case 'annuler':
                Membership::update($id, fn ($x) => ['status' => 'canceled'] + $x);
                return self::back($back, 'Adhésion annulée.');
            case 'note':
                Membership::update($id, fn ($x) => ['note' => Html::text($req->post['note'] ?? '', 2000)] + $x);
                return self::back($back, 'Note enregistrée.');
            case 'bienvenue':
                Membership::welcome($a);
                return self::back($back, 'E-mail de bienvenue renvoyé à ' . $a['member']['email'] . '.');
            case 'supprimer':
                Membership::delete($id);
                Activity::log(self::actor(), 'a supprimé une adhésion', ['title' => $id, 'path' => '']);
                return self::back('/admin/association/adhesions?annee=' . (int) $a['year'], 'Adhésion supprimée.');
        }
        return self::back($back);
    }

    /** POST /admin/association/adhesions : adhésion reçue sur papier (chèque, espèces, virement, HelloAsso). */
    public static function membershipAdd(Request $req): Response
    {
        $f = fn (string $k, int $max = 160) => Html::line($req->post[$k] ?? '', $max);
        $t = Content::tariff($f('tariff', 40));
        $amount = (int) preg_replace('/\D/', '', $f('amount', 8));
        $m = ['first' => $f('first', 60), 'last' => $f('last', 80), 'email' => mb_strtolower($f('email')), 'phone' => $f('phone', 30), 'address' => $f('address'), 'zip' => $f('zip', 12), 'city' => $f('city', 80), 'family' => $f('family', 300)];
        if (!$t || $m['last'] === '' || ($m['email'] !== '' && !filter_var($m['email'], FILTER_VALIDATE_EMAIL))) {
            return self::back('/admin/association/adhesions#ajouter', null, 'Formule, nom et (s’il est saisi) e-mail valide sont nécessaires.');
        }
        $provider = isset(Membership::PROVIDERS[$f('provider', 20)]) ? $f('provider', 20) : 'cheque';
        $paid = !empty($req->post['paid']);
        $a = [
            'id' => Membership::newId(), 'created' => date('c'), 'updated' => date('c'),
            'year' => max(2000, min(2100, (int) ($f('year', 4) ?: date('Y')))),
            'tariff' => $t['key'], 'label' => (string) $t['label'], 'amount' => max((int) $t['amount'], $amount) * 100,
            'status' => $paid ? 'paid' : 'offline', 'provider' => $provider, 'mode' => 'live', 'member' => $m,
            'newsletter' => false, 'source' => 'Saisie dans le back-office', 'ext' => [],
            'payments' => $paid ? [['ref' => 'manuel-' . date('YmdHis'), 'amount' => max((int) $t['amount'], $amount) * 100, 'at' => date('c'), 'by' => self::actor()['name'] ?? '']] : [],
            'paid_at' => $paid ? date('c') : null, 'origin' => 'back-office',
        ];
        Membership::put($a);
        if ($paid && $m['email'] !== '' && !empty($req->post['welcome'])) {
            Membership::welcome($a);
        }
        Activity::log(self::actor(), 'a ajouté une adhésion', ['title' => trim($m['first'] . ' ' . $m['last']), 'path' => '']);
        return self::back('/admin/association/adhesions/' . $a['id'], 'Adhésion ajoutée.');
    }

    public static function membershipsExport(Request $req): Response
    {
        $year = (int) ($req->str('annee') ?: date('Y'));
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Référence', 'Année', 'Statut', 'Formule', 'Montant (€)', 'Règlement', 'Prénom', 'Nom', 'E-mail', 'Téléphone', 'Adresse', 'Code postal', 'Ville', 'Famille', 'Newsletter', 'Créée le', 'Payée le'], ';');
        foreach (Membership::all() as $a) {
            if ((int) ($a['year'] ?? 0) !== $year) {
                continue;
            }
            $m = $a['member'];
            fputcsv($out, csv_safe([
                $a['id'], $a['year'], Membership::STATUS[$a['status']] ?? $a['status'], $a['label'], number_format($a['amount'] / 100, 2, ',', ''), Membership::PROVIDERS[$a['provider']] ?? $a['provider'],
                $m['first'] ?? '', $m['last'] ?? '', $m['email'] ?? '', $m['phone'] ?? '', $m['address'] ?? '', $m['zip'] ?? '', $m['city'] ?? '', $m['family'] ?? '',
                !empty($a['newsletter']) ? 'oui' : 'non', date('d/m/Y', strtotime((string) $a['created'])), !empty($a['paid_at']) ? date('d/m/Y', strtotime((string) $a['paid_at'])) : '',
            ]), ';');
        }
        rewind($out);
        Activity::log(self::actor(), 'a exporté les adhésions', ['title' => (string) $year, 'path' => '']);
        return new Response((string) stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="adhesions-' . $year . '-' . date('Ymd') . '.csv"', 'Cache-Control' => 'no-store']);
    }

    // ------------------------------------------------------------------ bénévoles

    public static function volunteers(Request $req): Response
    {
        $status = $req->str('statut');
        $all = JsonStore::read(Forms::VOLUNTEERS, []) ?: [];
        uasort($all, fn ($a, $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
        $rows = array_values(array_filter($all, fn ($v) => $status === '' ? ($v['status'] ?? 'nouveau') !== 'archive' : ($v['status'] ?? 'nouveau') === $status));
        $poles = array_column(Content::poles(), 'title', 'key');
        return self::html('admin/association/benevoles', ['rows' => $rows, 'status' => $status, 'poles' => $poles, 'counts' => array_count_values(array_map(fn ($v) => $v['status'] ?? 'nouveau', $all))], self::meta('Bénévoles', 'asso-benevoles'));
    }

    public static function volunteersAction(Request $req): Response
    {
        $id = $req->str('id');
        $do = $req->str('do');
        $back = '/admin/association/benevoles' . ($req->str('statut') !== '' ? '?statut=' . rawurlencode($req->str('statut')) : '');
        if (!preg_match('/^B\d{6}-[a-f0-9]{6}$/', $id)) {
            return self::back($back, null, 'Proposition introuvable.');
        }
        JsonStore::update(Forms::VOLUNTEERS, function ($all) use ($id, $do, $req) {
            $all = $all ?: [];
            if (!isset($all[$id])) {
                return $all;
            }
            if ($do === 'supprimer') {
                unset($all[$id]);
            } elseif (isset(Forms::VOLUNTEER_STATUS[$do])) {
                $all[$id]['status'] = $do;
            } elseif ($do === 'note') {
                $all[$id]['note'] = Html::text($req->post['note'] ?? '', 2000);
            }
            return $all;
        }, []);
        return self::back($back, $do === 'supprimer' ? 'Proposition supprimée.' : 'Enregistré.');
    }

    public static function volunteersExport(Request $req): Response
    {
        $poles = array_column(Content::poles(), 'title', 'key');
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Reçue le', 'Statut', 'Prénom', 'Nom', 'E-mail', 'Téléphone', 'Ville', 'Pôles', 'Compétences', 'Disponibilité', 'Message', 'Note'], ';');
        foreach (JsonStore::read(Forms::VOLUNTEERS, []) ?: [] as $v) {
            fputcsv($out, csv_safe([
                date('d/m/Y', strtotime((string) $v['at'])), Forms::VOLUNTEER_STATUS[$v['status'] ?? 'nouveau'] ?? '', $v['first'] ?? '', $v['last'] ?? '', $v['email'] ?? '', $v['phone'] ?? '', $v['city'] ?? '',
                implode(', ', array_map(fn ($k) => $poles[$k] ?? $k, (array) ($v['poles'] ?? []))), $v['skills'] ?? '', Forms::AVAILABILITY[$v['availability'] ?? ''] ?? '', $v['message'] ?? '', $v['note'] ?? '',
            ]), ';');
        }
        rewind($out);
        Activity::log(self::actor(), 'a exporté les bénévoles', ['title' => 'Site de l’association', 'path' => '']);
        return new Response((string) stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="benevoles-' . date('Ymd') . '.csv"', 'Cache-Control' => 'no-store']);
    }

    // ------------------------------------------------------------------ réglages

    public static function settings(Request $req): Response
    {
        $schema = Settings::schema()['vitrine'];
        $values = [];
        foreach ($schema['fields'] as $k => $f) {
            $values[$k] = Settings::get("vitrine.$k");
        }
        return self::html('admin/system/settings', ['group' => 'vitrine', 'schema' => $schema, 'values' => $values, 'options' => [], 'status' => ['base' => Host::base(), 'open' => Site::open()]],
            self::meta('Réglages', 'asso-reglages'));
    }
}
