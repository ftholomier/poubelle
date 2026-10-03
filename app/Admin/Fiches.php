<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Data\Categories;
use App\Data\Derived;
use App\Data\Fiches as Store;
use App\Data\Index;
use App\Data\Paths;
use App\Data\Redirects;
use App\Services\Search;
use App\Services\Translator;

/**
 * Fiches dans le back-office : listes (filtres, recherche, tri, actions groupées),
 * masque de saisie par type, enregistrement, versions et restauration, corbeille.
 */
final class Fiches extends Base
{
    public const LISTS = [
        'matchs' => ['types' => ['match'], 'title' => 'Matchs', 'new' => 'match', 'label' => 'match'],
        'personnes' => ['types' => ['personne'], 'title' => 'Personnes', 'new' => 'personne', 'label' => 'personne'],
        'articles' => ['types' => ['article', 'page'], 'title' => 'Articles & pages', 'new' => 'article', 'label' => 'article'],
        'objets' => ['types' => ['objet'], 'title' => 'Objets des réserves', 'new' => 'objet', 'label' => 'objet'],
    ];
    private const PER_PAGE = 50;
    private const TYPE_LIST = ['match' => 'matchs', 'personne' => 'personnes', 'article' => 'articles', 'page' => 'articles', 'objet' => 'objets', 'moment' => 'moments'];

    // ------------------------------------------------------------------ listes

    public static function list(Request $req, string $slug): Response
    {
        $conf = self::LISTS[$slug];
        $status = $req->str('statut');
        $q = Search::norm($req->str('q'));
        $all = array_filter(Index::all(), fn ($s) => in_array($s['type'], $conf['types'], true));
        $counts = ['tous' => 0];
        foreach ($all as $s) {
            if ($s['status'] === 'corbeille') {
                continue;
            }
            $counts['tous']++;
            $counts[$s['status']] = ($counts[$s['status']] ?? 0) + 1;
        }
        $quality = [];
        foreach (Derived::get()['quality'] ?? [] as $a) {
            $quality[(int) $a['id']][] = $a;
        }
        $totals = Derived::get()['person_totals'] ?? [];
        $geo = \App\Data\Collections::get('geo', []);
        // Filtres
        $items = array_filter($all, function ($s) use ($status, $q, $req, $slug) {
            if ($s['status'] === 'corbeille' || ($status !== '' && $s['status'] !== $status)) {
                return false;
            }
            if ($q !== '' && !str_contains(Search::norm($s['title'] . ' ' . ($s['p']['name'] ?? '') . ' ' . ($s['m']['home'] ?? '') . ' ' . ($s['m']['away'] ?? '') . ' ' . $s['path']), $q) && (string) $s['id'] !== $q) {
                return false;
            }
            if ($slug === 'matchs') {
                if (($v = $req->str('saison')) !== '' && ($s['m']['season'] ?? '') !== $v) {
                    return false;
                }
                if (($v = $req->str('comp')) !== '' && ($s['m']['competition'] ?? '') !== $v) {
                    return false;
                }
            }
            if ($slug === 'personnes') {
                if (($v = $req->str('role')) !== '' && !in_array($v, $s['p']['roles'] ?? [], true)) {
                    return false;
                }
                if ($req->str('filtre') === 'sans-naissance' && !empty($s['p']['birth_place'])) {
                    return false;
                }
                if ($req->str('filtre') === 'album' && empty($s['p']['album']['in'])) {
                    return false;
                }
                if ($req->str('filtre') === 'legendes' && empty($s['p']['legend'])) {
                    return false;
                }
            }
            if ($req->str('cat') !== '' && !in_array($req->str('cat'), $s['categories'], true)) {
                return false;
            }
            return true;
        });
        // Tri
        $sort = $req->str('tri', $slug === 'matchs' ? 'date' : ($slug === 'personnes' ? 'nom' : 'modifie'));
        uasort($items, match ($sort) {
            'date' => fn ($a, $b) => strcmp((string) ($b['m']['date'] ?? $b['date']), (string) ($a['m']['date'] ?? $a['date'])),
            'date-asc' => fn ($a, $b) => strcmp((string) ($a['m']['date'] ?? $a['date']), (string) ($b['m']['date'] ?? $b['date'])),
            'nom' => fn ($a, $b) => strnatcasecmp(Index::sortName($a), Index::sortName($b)),
            'titre' => fn ($a, $b) => strnatcasecmp($a['title'], $b['title']),
            'matchs' => fn ($a, $b) => ($totals[$b['id']]['matches'] ?? 0) <=> ($totals[$a['id']]['matches'] ?? 0),
            default => fn ($a, $b) => strcmp((string) ($b['modified'] ?? ''), (string) ($a['modified'] ?? '')),
        });
        $total = count($items);
        $page = max(1, (int) $req->str('page', '1'));
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $rows = array_slice($items, ($page - 1) * self::PER_PAGE, self::PER_PAGE, true);
        $seasons = [];
        $comps = [];
        if ($slug === 'matchs') {
            foreach ($all as $s) {
                if (!empty($s['m']['season'])) {
                    $seasons[$s['m']['season']] = true;
                }
                if (!empty($s['m']['competition'])) {
                    $comps[$s['m']['competition']] = true;
                }
            }
            krsort($seasons);
            ksort($comps);
        }
        return self::html('admin/fiches/list', [
            'slug' => $slug, 'conf' => $conf, 'rows' => $rows, 'total' => $total, 'counts' => $counts, 'status' => $status,
            'q' => $req->str('q'), 'page' => $page, 'pages' => $pages, 'sort' => $sort, 'quality' => $quality, 'totals' => $totals, 'geo' => $geo,
            'seasons' => array_keys($seasons), 'comps' => array_keys($comps), 'query' => $req->query,
        ], ['title' => $conf['title'], 'crumb' => 'Contenus', 'nav' => $slug]);
    }

    public static function trashList(Request $req): Response
    {
        $rows = array_filter(Index::all(), fn ($s) => $s['status'] === 'corbeille');
        uasort($rows, fn ($a, $b) => strcmp((string) ($b['modified'] ?? ''), (string) ($a['modified'] ?? '')));
        return self::html('admin/fiches/trash', ['rows' => $rows], ['title' => 'Corbeille', 'crumb' => 'Contenus', 'nav' => 'corbeille']);
    }

    /** Actions groupées depuis une liste. */
    public static function bulk(Request $req): Response
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($req->post['ids'] ?? []))));
        $action = (string) ($req->post['action'] ?? '');
        $back = (string) ($req->post['back'] ?? '/admin');
        if (!str_starts_with($back, '/admin')) {
            $back = '/admin';
        }
        if (!$ids) {
            return self::back($back, null, 'Aucune fiche sélectionnée.');
        }
        $user = self::actor();
        $n = 0;
        Store::batch(function () use ($ids, $action, $user, &$n) {
            foreach ($ids as $id) {
                $doc = Store::get($id);
                if (!$doc) {
                    continue;
                }
                switch ($action) {
                    case 'publie':
                    case 'brouillon':
                    case 'relire':
                        if ($doc['status'] !== $action && $doc['status'] !== 'corbeille') {
                            $doc['status'] = $action;
                            Store::save($doc, $user, \App\Data\Fiches::STATUSES[$action] . ' (action groupée)');
                            $n++;
                        }
                        break;
                    case 'corbeille':
                        Store::trash($id, $user);
                        $n++;
                        break;
                    case 'sortir':
                        Store::untrash($id, $user);
                        $n++;
                        break;
                    case 'supprimer':
                        if (Auth::can('destroy') && $doc['status'] === 'corbeille') {
                            Store::destroy($id, $user);
                            $n++;
                        }
                        break;
                    case 'traduire':
                        @set_time_limit(300);
                        if (Translator::translateFiche($id, true, $user) === 'ok') {
                            $n++;
                        }
                        break;
                }
            }
        });
        return self::back($back, $n . ' fiche' . ($n > 1 ? 's' : '') . ' traitée' . ($n > 1 ? 's' : '') . '.');
    }

    // ------------------------------------------------------------------ masque de saisie

    public static function create(Request $req, string $type): Response
    {
        if (!isset(Store::TYPES[$type])) {
            return self::back('/admin', null, 'Type de fiche inconnu.');
        }
        $doc = Store::blank($type);
        if ($type === 'match' && ($c = $req->str('adversaire')) !== '') {
            $doc['match']['away']['name'] = $c;
        }
        if ($type === 'moment' && ctype_digit($req->str('numero')) && (int) $req->str('numero') <= 100) {
            $doc['moment']['number'] = (int) $req->str('numero');
            $doc['publish_at'] = $req->str('date') !== '' && strtotime($req->str('date')) ? date('c', strtotime($req->str('date') . ' 08:00')) : null;
        }
        if ($type === 'personne') {
            $role = $req->str('role');
            if (isset(FicheForm::ROLES[$role])) {
                $doc['personne']['roles'] = [$role];
            }
            if (($n = $req->str('nom')) !== '') {
                $parts = preg_split('/\s+/u', trim($n), 2);
                $doc['personne']['first_name'] = count($parts) > 1 ? $parts[0] : '';
                $doc['personne']['last_name'] = count($parts) > 1 ? $parts[1] : $parts[0];
                $doc['personne']['display_name'] = trim($n);
            }
        }
        return self::editor($doc, true);
    }

    public static function edit(Request $req, int $id): Response
    {
        $doc = Store::get($id);
        if (!$doc) {
            return self::html('admin/message', ['title' => 'Fiche introuvable', 'text' => "Aucune fiche n° $id.", 'back' => '/admin'], ['title' => 'Fiche introuvable'], 404);
        }
        return self::editor($doc, false);
    }

    private static function editor(array $doc, bool $isNew): Response
    {
        $list = self::TYPE_LIST[$doc['type']] ?? 'articles';
        $title = $isNew ? 'Nouvelle fiche · ' . mb_strtolower(Store::TYPES[$doc['type']]) : ($doc['title'] ?: 'Sans titre');
        $versions = $isNew ? [] : Store::versions((int) $doc['id']);
        return self::html('admin/fiches/edit', [
            'doc' => $doc,
            'isNew' => $isNew,
            'versions' => $versions,
            'checks' => $isNew ? [] : Quality::forDoc($doc),
            'auto' => $isNew ? [] : self::autoLinks($doc),
            'enStatus' => $isNew ? 'none' : Translator::status($doc),
            'lineup' => $doc['type'] === 'match' ? \App\Front\Fiche::lineupRows($doc, $doc['match']['lineup']['rows'] ?? []) : [],
            'list' => $list,
        ], [
            'title' => $title,
            'crumb_html' => 'Contenus › <a href="/admin/' . e($list === 'moments' ? 'moments' : $list) . '">' . e(self::LISTS[$list]['title'] ?? 'Moments') . '</a>',
            'nav' => $list === 'moments' ? 'moments' : $list,
        ]);
    }

    /** Pages recalculées automatiquement à partir de la fiche. */
    private static function autoLinks(array $doc): array
    {
        $out = [];
        if ($doc['type'] === 'match') {
            $m = $doc['match'];
            if (!empty($m['opponent_club'])) {
                $out[] = ['Face-à-face Sochaux × ' . ($m['sochaux_home'] ? $m['away']['name'] : $m['home']['name']), '/face-a-face/' . $m['opponent_club'] . '/'];
            }
            if (!empty($m['season'])) {
                $out[] = ['Saison ' . $m['season'] . ' : résultats et buteurs', '/matchs/' . $m['season'] . '/'];
            }
            $n = count(array_filter($m['lineup']['rows'] ?? [], fn ($r) => !empty($r['name'])));
            if ($n) {
                $out[] = [$n . ' fiches joueurs : « Tous ses matchs »', null];
            }
            if (!empty($m['stadium'])) {
                $out[] = ['Carto : ' . $m['stadium'], '/interactif/carto/'];
            }
            $out[] = ['Records et bilans recalculés', '/records/'];
        } elseif ($doc['type'] === 'personne') {
            $t = Derived::get()['person_totals'][(int) $doc['id']] ?? null;
            if ($t) {
                $out[] = [$t['matches'] . ' matchs, ' . $t['goals'] . ' buts reliés depuis les compositions', null];
            }
            if (!empty($doc['personne']['birth']['place']['city'])) {
                $out[] = ['Carto des origines : ' . $doc['personne']['birth']['place']['city'], '/interactif/carto/'];
            }
            if (!empty($doc['personne']['album']['in'])) {
                $out[] = ['Carte de l’album du centenaire', '/interactif/album/'];
            }
        }
        if (!empty($doc['path']) && Store::isVisible($doc)) {
            $out[] = ['Image de partage générée', '/partage/' . (int) $doc['id'] . '.png'];
        }
        return $out;
    }

    /** Enregistrement (JSON). */
    public static function save(Request $req, int $id): Response
    {
        $in = $req->json();
        if (!$in) {
            return self::json(['ok' => false, 'error' => 'Données illisibles.'], 400);
        }
        $isNew = $id === 0;
        if ($isNew) {
            $type = (string) ($in['_type'] ?? '');
            if (!isset(Store::TYPES[$type])) {
                return self::json(['ok' => false, 'error' => 'Type de fiche inconnu.'], 422);
            }
            $doc = Store::blank($type);
        } else {
            $doc = Store::get($id);
            if (!$doc) {
                return self::json(['ok' => false, 'error' => 'Fiche introuvable.'], 404);
            }
            // Modification simultanée ?
            $seen = (string) ($in['_modified'] ?? '');
            if ($seen !== '' && $seen !== (string) ($doc['modified'] ?? '') && empty($in['_force'])) {
                $last = Store::versions($id)[0] ?? null;
                return self::json(['ok' => false, 'conflict' => 'Cette fiche a été modifiée par ' . ($last['by'] ?? 'quelqu’un') . ' ' . self::ago($doc['modified'] ?? null) . ' pendant que vous la modifiiez.', 'modified' => $doc['modified']], 409);
            }
        }
        $before = $doc;
        $errors = [];
        $doc = FicheForm::apply($doc, $in, $errors);
        if ($errors) {
            return self::json(['ok' => false, 'error' => reset($errors), 'field' => array_key_first($errors), 'errors' => $errors], 422);
        }
        // Adresse : automatique tant que la fiche n'a jamais été publiée, sinon modifiable à la main.
        $wasPublished = !$isNew && (($before['status'] ?? '') === 'publie' || !empty($before['published_once']));
        $typed = trim((string) ($in['path'] ?? ''));
        if ($typed !== '' && $typed !== ($before['path'] ?? '')) {
            $typed = '/' . trim(strtolower($typed), '/') . '/';
            if ($err = Paths::check($typed)) {
                return self::json(['ok' => false, 'error' => $err, 'field' => 'path'], 422);
            }
            $doc['path'] = Paths::unique($typed, (int) $doc['id']);
        } elseif ($isNew || !$wasPublished || empty($doc['path'])) {
            $doc['path'] = Paths::unique(Paths::suggest($doc), (int) ($doc['id'] ?: -1));
        }
        $doc['slug'] = basename(rtrim($doc['path'], '/'));
        if ($doc['status'] === 'publie') {
            $doc['published_once'] = true;
            if (empty($before['published_once']) && ($before['status'] ?? '') !== 'publie') {
                $doc['date'] = date('c');
            }
        }
        $message = Html::line($in['_message'] ?? '', 160);
        $saved = Store::save($doc, self::actor(), $message);
        if (!$isNew && ($before['path'] ?? '') !== '' && $before['path'] !== $saved['path'] && $wasPublished) {
            Redirects::add($before['path'], $saved['path']);
        }
        $v = Store::versions((int) $saved['id'])[0] ?? null;
        return self::json([
            'ok' => true,
            'id' => $saved['id'],
            'modified' => $saved['modified'],
            'message' => $isNew ? 'Fiche créée' : ($v ? 'Version v' . $v['n'] . ' enregistrée' : 'Aucune modification'),
            'savedLabel' => 'Enregistré · v' . ($v['n'] ?? 1) . ' · ' . (self::actor()['name'] ?? ''),
            'redirect' => $isNew ? '/admin/fiche/' . $saved['id'] : null,
            'reload' => !$isNew && (($before['status'] ?? '') !== $saved['status'] || ($before['path'] ?? '') !== $saved['path']),
        ]);
    }

    public static function trash(Request $req, int $id): Response
    {
        Store::trash($id, self::actor());
        return self::back('/admin/fiche/' . $id, 'Fiche mise à la corbeille. Elle n’est plus visible sur le site.');
    }

    public static function untrash(Request $req, int $id): Response
    {
        Store::untrash($id, self::actor());
        return self::back('/admin/fiche/' . $id, 'Fiche sortie de la corbeille.');
    }

    public static function destroy(Request $req, int $id): Response
    {
        if ($deny = self::denyUnlessAdmin()) {
            return $deny;
        }
        $doc = Store::get($id);
        if (!$doc || $doc['status'] !== 'corbeille') {
            return self::back('/admin/fiche/' . $id, null, 'Seule une fiche à la corbeille peut être supprimée définitivement.');
        }
        Store::destroy($id, self::actor());
        return self::back('/admin/corbeille', 'Fiche supprimée définitivement (une copie reste dans l’historique des versions).');
    }

    /** Détail d'une version (JSON) : différences avec la précédente. */
    public static function version(Request $req, int $id, int $n): Response
    {
        foreach (Store::versions($id) as $v) {
            if ((int) $v['n'] === $n) {
                return self::json(['ok' => true, 'version' => $v]);
            }
        }
        return self::json(['ok' => false], 404);
    }

    public static function restore(Request $req, int $id, int $n): Response
    {
        if (!Auth::can('restore')) {
            return self::back('/admin/fiche/' . $id . '#historique', null, 'La restauration de versions est réservée aux administrateurs.');
        }
        $doc = Store::restore($id, $n, self::actor());
        return $doc ? self::back('/admin/fiche/' . $id . '#historique', "Version v$n restaurée : une nouvelle version a été créée, elle-même réversible.") : self::back('/admin/fiche/' . $id, null, 'Version introuvable.');
    }

    public static function translate(Request $req, int $id): Response
    {
        if (!Translator::enabled()) {
            return self::back('/admin/fiche/' . $id . '#en', null, 'Traduction impossible : la clé Gemini n’est pas réglée (Réglages › Assistant IA).');
        }
        @set_time_limit(300);
        $r = Translator::translateFiche($id, true, self::actor());
        return $r === 'ok' ? self::back('/admin/fiche/' . $id . '#en', 'Traduction anglaise générée. Relisez-la et corrigez-la si besoin.') : self::back('/admin/fiche/' . $id . '#en', null, 'Traduction : ' . $r);
    }

    /** Aperçu « comme sur le site » d'une fiche, même non publiée (données en cours de saisie si envoyées). */
    public static function preview(Request $req, int $id): Response
    {
        $doc = Store::get($id);
        if (!$doc) {
            return Response::notFound();
        }
        if ($req->method === 'POST' && ($json = (string) ($req->post['data'] ?? '')) !== '') {
            $in = json_decode($json, true);
            if (is_array($in)) {
                $err = [];
                $doc = FicheForm::apply($doc, $in, $err);
            }
        }
        \App\Services\I18n::set($req->str('lang') === 'en' ? 'en' : 'fr');
        $res = \App\Front\Fiche::show(new Request('GET', $doc['path'] ?: '/', [], [], [], $req->server, ''), $doc);
        $res->body = str_replace('<body', '<body data-preview', $res->body);
        $res->body = preg_replace('#<main id="contenu">#', '<div style="position:sticky;top:0;z-index:200;background:#F6C400;border-bottom:2px solid #0E1F4D;padding:8px 16px;font-family:Arial,sans-serif;font-weight:bold;text-align:center">Aperçu — ' . e(\App\Data\Fiches::STATUSES[$doc['status']] ?? $doc['status']) . ' · ce n’est pas la page publique</div><main id="contenu">', $res->body, 1);
        $res->headers['X-Robots-Tag'] = 'noindex';
        return $res;
    }

    /** Nombre de fiches par statut pour un ou plusieurs types. */
    public static function counts(array $types): array
    {
        $c = [];
        foreach (Index::all() as $s) {
            if (in_array($s['type'], $types, true)) {
                $c[$s['status']] = ($c[$s['status']] ?? 0) + 1;
            }
        }
        return $c;
    }

    public static function catOptions(): array
    {
        $out = [];
        foreach (Categories::all() as $slug => $c) {
            if (!empty($c['technical']) || !empty($c['season'])) {
                continue;
            }
            $trail = array_map(fn ($t) => Categories::label($t['slug'] ?? $t), Categories::trail($slug));
            $out[$slug] = implode(' › ', $trail ?: [Categories::label($slug)]);
        }
        asort($out);
        return $out;
    }
}
