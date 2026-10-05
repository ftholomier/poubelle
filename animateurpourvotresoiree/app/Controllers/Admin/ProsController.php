<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Pro\DashboardController as ProSpace;
use App\Controllers\Pro\RegisterController;
use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Fs;
use App\Core\HttpException;
use App\Core\Image;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Session;
use App\Core\Str;
use App\Core\Url;
use App\Services\Ai;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Mail;
use App\Services\Pros;
use App\Services\Reviews;
use App\Services\Settings;
use App\Services\Stats;
use App\Services\Store;

/** Gestion des pros : liste filtrable, actions groupées, fiche complète, validation, aperçu. */
final class ProsController extends AdminController
{
    public const SORTS = ['recent' => 'Plus récents', 'ancien' => 'Plus anciens', 'nom' => 'Nom', 'vues' => 'Vues', 'score' => 'Complétude', 'connexion' => 'Dernière connexion', 'maj' => 'Dernière modification'];

    /** @return array<int,array> pros filtrés (index léger) */
    private function filtered(): array
    {
        $status = self::q('statut', 20);
        $cat = self::q('cat', 60);
        $dep = Geo::depCode(self::q('dep', 3));
        $region = self::q('region', 3);
        $source = self::q('source', 20);
        $photos = self::q('photos', 10);
        $login = self::q('connexion', 10);
        $q = Str::norm(self::q('q', 100));
        $digits = preg_replace('/\D/', '', self::q('q', 100)) ?? '';
        $out = [];
        foreach (Store::pros()->iterate() as $id => $p) {
            if ($status === '' ? $p['status'] === 'deleted' : ($status !== 'all' && $p['status'] !== $status)) {
                continue;
            }
            if ($cat !== '' && !in_array($cat, $p['cats'], true)) {
                continue;
            }
            if ($dep !== '' && $p['dep'] !== $dep && !in_array($dep, $p['zones'], true)) {
                continue;
            }
            if ($region !== '' && $p['region'] !== $region) {
                continue;
            }
            if ($source !== '' && ($p['src'] ?? '') !== $source) {
                continue;
            }
            if ($photos === 'none' && $p['photos'] > 0 || $photos === 'some' && $p['photos'] === 0) {
                continue;
            }
            if ($login === 'never' && $p['login_at'] || $login === 'recent' && (!$p['login_at'] || $p['login_at'] < date('c', strtotime('-90 days')))) {
                continue;
            }
            if (self::q('vedette', 5) === '1' && empty($p['featured'])) {
                continue;
            }
            if ($q !== '') {
                $hay = Str::norm($p['name'] . ' ' . $p['email'] . ' ' . $p['login'] . ' ' . $p['city'] . ' ' . $p['first'] . ' ' . $p['last'] . ' ' . $p['cp']);
                $hit = str_contains($hay, $q) || (ctype_digit(self::q('q')) && (int) $id === (int) self::q('q')) || (strlen($digits) >= 6 && str_contains(preg_replace('/\D/', '', $p['phone']) ?? '', $digits));
                if (!$hit) {
                    continue;
                }
            }
            $out[(int) $id] = $p;
        }
        $sort = self::q('tri', 20);
        uasort($out, match ($sort) {
            'ancien' => static fn ($a, $b) => $a['id'] <=> $b['id'],
            'nom' => static fn ($a, $b) => strcasecmp(Str::ascii($a['name']), Str::ascii($b['name'])),
            'vues' => static fn ($a, $b) => $b['views'] <=> $a['views'],
            'score' => static fn ($a, $b) => $b['score'] <=> $a['score'],
            'connexion' => static fn ($a, $b) => strcmp((string) $b['login_at'], (string) $a['login_at']),
            'maj' => static fn ($a, $b) => strcmp((string) $b['updated'], (string) $a['updated']),
            default => static fn ($a, $b) => $b['id'] <=> $a['id'],
        });
        return $out;
    }

    public function index(): Response
    {
        $all = $this->filtered();
        $counts = ['all' => 0];
        foreach (Store::pros()->iterate() as $p) {
            $counts[$p['status']] = ($counts[$p['status']] ?? 0) + 1;
            if ($p['status'] !== 'deleted') {
                $counts['all']++;
            }
        }
        $pg = self::paginate($all, 50);
        return $this->page('pros', $pg + [
            'counts' => $counts,
            'link' => self::pageLink(),
            'filters' => $_GET,
            'aiOn' => Settings::aiOn('classification'),
        ], 'Pros', 'pros');
    }

    public function export(): Response
    {
        $rows = [['id', 'statut', 'nom affiché', 'prénom', 'nom', 'email', 'identifiant', 'téléphone', 'ville', 'code postal', 'département', 'région', 'métiers', 'photos', 'complétude', 'vues', 'note', 'avis', 'inscrit le', 'dernière connexion', 'source', 'fiche']];
        foreach ($this->filtered() as $id => $p) {
            $rows[] = [$id, Pros::STATUSES[$p['status']] ?? $p['status'], $p['name'], $p['first'], $p['last'], $p['email'], $p['login'], $p['phone'], $p['city'], $p['cp'], $p['dep'], Geo::region($p['region'])['name'] ?? $p['region'], implode(', ', array_map([Categories::class, 'name'], $p['cats'])), $p['photos'], $p['score'], $p['views'], $p['rating'], $p['reviews'], substr((string) $p['created'], 0, 10), substr((string) $p['login_at'], 0, 10), $p['src'] ?? '', $p['status'] === 'active' ? Url::abs(Url::pro($p)) : ''];
        }
        $this->audit('Export CSV des pros', ['lignes' => count($rows) - 1]);
        return Response::csv($rows, 'pros-' . date('Y-m-d') . '.csv');
    }

    // ---------------------------------------------------------- actions groupées

    public function bulk(): Response
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) Request::arr('ids')))));
        $action = (string) Request::input('action', '');
        if (!$ids) {
            return $this->done('Aucune fiche sélectionnée.', $this->listUrl(), 'warning');
        }
        if ($action === 'export') {
            $rows = [['id', 'nom', 'email', 'téléphone', 'ville', 'département', 'statut']];
            foreach ($ids as $id) {
                if ($p = Store::pros()->light($id)) {
                    $rows[] = [$id, $p['name'], $p['email'], $p['phone'], $p['city'], $p['dep'], $p['status']];
                }
            }
            return Response::csv($rows, 'selection-pros-' . date('Y-m-d') . '.csv');
        }
        $n = 0;
        foreach ($ids as $id) {
            $pro = Store::pros()->get($id);
            if (!$pro) {
                continue;
            }
            switch ($action) {
                case 'validate':
                    if (($pro['status'] ?? '') !== 'active') {
                        $this->validate($pro);
                        $n++;
                    }
                    break;
                case 'suspend':
                case 'inactive':
                case 'activate':
                    $to = ['suspend' => 'suspended', 'inactive' => 'inactive', 'activate' => 'active'][$action];
                    Pros::save($id, ['status' => $to]);
                    $n++;
                    break;
                case 'delete':
                    Pros::save($id, ['status' => 'deleted', 'deleted_at' => date('c'), 'deleted_reason' => 'back-office']);
                    $n++;
                    break;
                case 'classify':
                    $cats = Ai::classify($pro) ?? Categories::classify(['tags' => implode(' ', (array) ($pro['tags'] ?? [])), 'name' => Pros::displayName($pro), 'tagline' => (string) ($pro['tagline'] ?? ''), 'description' => Str::text((string) ($pro['description'] ?? ''))]);
                    if ($cats) {
                        Pros::save($id, static function (array $p) use ($cats): array {
                            $p['categories'] = $cats;
                            return $p;
                        });
                        $n++;
                    }
                    break;
                case 'feature':
                case 'unfeature':
                    Pros::save($id, ['featured' => $action === 'feature', 'featured_until' => null]);
                    $n++;
                    break;
            }
        }
        $this->audit('Action groupée sur les pros', ['action' => $action, 'fiches' => $n]);
        return $this->done($n . ' fiche(s) traitée(s).', $this->listUrl());
    }

    private function listUrl(): string
    {
        $ref = Request::referer();
        return $ref !== '' && str_contains($ref, Url::admin('pros')) ? (string) parse_url($ref, PHP_URL_PATH) . (parse_url($ref, PHP_URL_QUERY) ? '?' . parse_url($ref, PHP_URL_QUERY) : '') : Url::admin('pros');
    }

    // -------------------------------------------------------------- création

    public function create(): Response
    {
        if (Request::isPost()) {
            $email = Str::email(Request::str('email', 160));
            $name = Sanitizer::line((string) Request::input('display_name', ''), 80);
            $insee = (string) Request::input('insee', '');
            $commune = preg_match('/^[0-9AB]{5}$/', $insee) ? Geo::commune($insee) : null;
            $cats = array_values(array_filter((array) Request::arr('categories'), static fn ($c) => Categories::get((string) $c) !== null));
            if (mb_strlen($name) < 2 || !$commune || !$cats) {
                Session::withInput($_POST);
                return $this->done('Nom, ville (choisie dans la liste) et au moins un métier sont obligatoires.', 'pros/nouveau', 'error');
            }
            if ($email !== '' && (!Str::emailValid($email) || Pros::emailTaken($email))) {
                Session::withInput($_POST);
                return $this->done('Email invalide ou déjà utilisé.', 'pros/nouveau', 'error');
            }
            $pro = Pros::create([
                'status' => Request::input('status') === 'pending' ? 'pending' : 'active',
                'display_name' => $name,
                'company' => $name,
                'first_name' => Str::nameCase(Sanitizer::line((string) Request::input('first_name', ''), 60)),
                'last_name' => Str::nameCase(Sanitizer::line((string) Request::input('last_name', ''), 60)),
                'email' => $email,
                'login' => $email,
                'phone' => Str::phone(Sanitizer::line((string) Request::input('phone', ''), 30)),
                'city' => (string) $commune['n'],
                'postcode' => (string) ($commune['cp'][0] ?? ''),
                'categories' => $cats,
                'zones' => [(string) $commune['d']],
                'tagline' => Sanitizer::line((string) Request::input('tagline', ''), 220),
                'description' => '',
                'photos' => [],
                'videos' => [],
                'socials' => [],
                'tags' => [],
                'accept_requests' => true,
                'password_hash' => Crypto::hashPassword(Crypto::token(24)),
                'must_change_password' => false,
                'email_verified' => $email !== '',
                'settings' => ['notify_requests' => true, 'notify_messages' => true, 'vacation' => false, 'newsletter' => true, 'weekly_report' => true],
                'stats' => ['views' => 0, 'phone_reveals' => 0, 'website_clicks' => 0],
                'rating' => ['avg' => 0, 'count' => 0],
                'source' => 'admin',
                'created_by' => $this->by(),
            ]);
            $this->audit('Fiche pro créée', ['pro' => (int) $pro['id']]);
            if ($email !== '' && Request::bool('invite')) {
                $this->sendAccess($pro, true);
            }
            return $this->done('Fiche créée. Complétez-la ci-dessous.', 'pros/' . $pro['id']);
        }
        return $this->page('pro-new', [], 'Nouveau pro', 'pros');
    }

    // --------------------------------------------------------------- édition

    public function edit(int $id): Response
    {
        $pro = Store::pros()->get($id);
        if (!$pro) {
            throw new HttpException(404);
        }
        $messages = [];
        foreach (array_slice(array_reverse(Store::messages()->ids('pro_id', $id)), 0, 8) as $mid) {
            if ($l = Store::messages()->light($mid)) {
                $messages[] = $l;
            }
        }
        $reqIds = Store::requests()->ids('recipients', $id);
        $reviews = [];
        foreach (array_reverse(Store::reviews()->ids('pro_id', $id)) as $rid) {
            if ($l = Store::reviews()->light($rid)) {
                $reviews[] = $l;
            }
        }
        $series = Stats::proSeries($id, 30);
        return $this->page('pro-edit', [
            'pro' => $pro,
            'commune' => !empty($pro['insee']) ? Geo::commune((string) $pro['insee']) : null,
            'messages' => $messages,
            'msgTotal' => count(Store::messages()->ids('pro_id', $id)),
            'reqTotal' => count($reqIds),
            'reviews' => $reviews,
            'views30' => array_sum(array_column($series, 'pro_view')),
            'series' => $series,
            'aiOn' => Settings::aiOn('classification'),
            'maxPhotos' => (int) Settings::get('registration.max_photos', 12),
        ], Pros::displayName($pro), 'pros');
    }

    public function save(int $id): Response
    {
        $pro = Store::pros()->get($id);
        if (!$pro) {
            throw new HttpException(404);
        }
        $p = [];
        $p['display_name'] = Sanitizer::line((string) Request::input('display_name', ''), 80);
        if (mb_strlen($p['display_name']) < 2) {
            return $this->done('Le nom affiché est obligatoire.', 'pros/' . $id, 'error');
        }
        foreach (['company' => 100, 'tagline' => 220, 'address' => 160, 'price_note' => 80, 'guso' => 30, 'languages' => 80] as $k => $max) {
            $p[$k] = Sanitizer::line((string) Request::input($k, ''), $max);
        }
        $p['first_name'] = Str::nameCase(Sanitizer::line((string) Request::input('first_name', ''), 60));
        $p['last_name'] = Str::nameCase(Sanitizer::line((string) Request::input('last_name', ''), 60));
        $p['description'] = Sanitizer::html((string) Request::raw('description', ''), ['headings' => true]);
        $p['categories'] = array_values(array_unique(array_filter((array) Request::arr('categories'), static fn ($c) => Categories::get((string) $c) !== null)));
        $tags = [];
        foreach (preg_split('/[,;\n]+/', (string) Request::input('tags', '')) ?: [] as $t) {
            $t = Sanitizer::line($t, 40);
            if ($t !== '') {
                $tags[mb_strtolower($t)] = $t;
            }
        }
        $p['tags'] = array_values(array_slice($tags, 0, 15));
        $email = Str::email((string) Request::input('email', ''));
        if ($email !== '' && !Str::emailValid($email)) {
            return $this->done('Email invalide.', 'pros/' . $id, 'error');
        }
        if ($email !== '' && Pros::emailTaken($email, $id)) {
            Session::flash('warning', 'Attention : cet email est aussi utilisé par une autre fiche.');
        }
        $p['email'] = $email;
        $login = Sanitizer::line((string) Request::input('login', ''), 120);
        if ($login !== '' && Pros::loginTaken($login, $id)) {
            return $this->done('Cet identifiant est déjà utilisé par une autre fiche.', 'pros/' . $id, 'error');
        }
        $p['login'] = $login;
        $p['phone'] = Str::phone(Sanitizer::line((string) Request::input('phone', ''), 30));
        $site = Sanitizer::line((string) Request::input('website', ''), 255);
        $p['website'] = $site !== '' ? Str::url($site) : '';
        $socials = [];
        foreach (['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'] as $net) {
            $u = Str::url(Sanitizer::line((string) (Request::arr('socials')[$net] ?? ''), 255));
            if ($u !== '') {
                $socials[$net] = $u;
            }
        }
        $p['socials'] = $socials;
        $p['videos'] = array_values(array_slice(array_filter(preg_split('/\s+/', (string) Request::input('videos', '')) ?: [], static fn ($v) => Pros::video($v) !== null), 0, 6));
        $price = trim((string) Request::input('price_from', ''));
        $p['price_from'] = $price === '' ? null : max(0, min(100000, (int) preg_replace('/\D/', '', $price)));
        $p['zones'] = array_values(array_filter(array_map(static fn ($z) => Geo::depCode((string) $z), (array) Request::arr('zones')), static fn ($z) => Geo::dep($z) !== null));
        $p['all_france'] = Request::bool('all_france');
        $p['accept_requests'] = Request::bool('accept_requests');
        $p['siret'] = preg_replace('/\D/', '', (string) Request::input('siret', '')) ?? '';
        $p['featured'] = Request::bool('featured');
        $p['featured_until'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) Request::input('featured_until', '')) ? (string) Request::input('featured_until') : null;
        $p['admin_notes'] = Sanitizer::text((string) Request::input('admin_notes', ''), 5000);
        $p['seo'] = array_filter([
            'title' => Sanitizer::line((string) Request::input('seo_title', ''), 70),
            'description' => Sanitizer::line((string) Request::input('seo_description', ''), 170),
        ]);
        $status = (string) Request::input('status', $pro['status'] ?? 'pending');
        if (!array_key_exists($status, Pros::STATUSES)) {
            $status = (string) ($pro['status'] ?? 'pending');
        }
        $slug = Str::slug((string) Request::input('slug', ''), 80);
        $commune = null;
        $insee = (string) Request::input('insee', '');
        if (preg_match('/^[0-9AB]{5}$/', $insee) && $insee !== ($pro['insee'] ?? '')) {
            $commune = Geo::commune($insee);
        }
        $lat = Request::input('lat', '');
        $lng = Request::input('lng', '');
        $manualGeo = is_numeric($lat) && is_numeric($lng) && ((float) $lat !== (float) ($pro['lat'] ?? 0) || (float) $lng !== (float) ($pro['lng'] ?? 0));
        $wasActive = ($pro['status'] ?? '') === 'active';
        Pros::save($id, static function (array $cur) use ($p, $status, $slug, $commune, $manualGeo, $lat, $lng, $id): array {
            foreach ($p as $k => $v) {
                $cur[$k] = $v;
            }
            $cur['status'] = $status;
            if ($slug !== '' && $slug !== ($cur['slug'] ?? '')) {
                $cur['slug'] = Pros::uniqueSlug($slug, $id);
            }
            if ($commune) {
                $cur['city'] = $commune['n'];
                $cur['postcode'] = (string) ($commune['cp'][0] ?? '');
                $cur['insee'] = $commune['insee'];
                $cur['dep'] = $commune['d'];
                $cur['region'] = $commune['r'];
                $cur['lat'] = (float) $commune['la'];
                $cur['lng'] = (float) $commune['lo'];
                $cur['geo_precision'] = 'commune';
                $cur['geo_manual'] = true;
            } elseif ($manualGeo) {
                $cur['lat'] = round((float) $lat, 6);
                $cur['lng'] = round((float) $lng, 6);
                $cur['geo_manual'] = true;
                $cur['geo_precision'] = 'manuel';
            }
            return $cur;
        });
        if (!$wasActive && $status === 'active') {
            $fresh = Store::pros()->get($id);
            if ($fresh && !empty($fresh['email']) && Request::bool('notify')) {
                Mail::send((string) $fresh['email'], 'pro_validated', ['prenom' => $fresh['first_name'] ?: Pros::displayName($fresh), 'fiche' => Pros::displayName($fresh)], ['bouton_url' => Url::pro($fresh), 'bouton_label' => 'Voir ma fiche']);
            }
        }
        $this->audit('Fiche pro modifiée', ['pro' => $id, 'statut' => $status]);
        return $this->done('Fiche enregistrée.', 'pros/' . $id);
    }

    // ---------------------------------------------------------------- actions

    public function action(int $id): Response
    {
        $pro = Store::pros()->get($id);
        if (!$pro) {
            throw new HttpException(404);
        }
        $action = (string) Request::input('action', '');
        switch ($action) {
            case 'validate':
                $this->validate($pro);
                return $this->done('Fiche validée et publiée. Le pro a été prévenu par email.', 'pros/' . $id);
            case 'reject':
                $reason = Sanitizer::text((string) Request::input('reason', ''), 1000);
                Pros::save($id, ['status' => 'rejected', 'rejected_reason' => $reason, 'rejected_at' => date('c')]);
                if (!empty($pro['email'])) {
                    Mail::send((string) $pro['email'], 'pro_rejected', ['prenom' => $pro['first_name'] ?: Pros::displayName($pro), 'motif' => $reason !== '' ? $reason : 'Votre fiche ne correspond pas aux critères de l\'annuaire.']);
                }
                $this->audit('Inscription refusée', ['pro' => $id]);
                return $this->done('Inscription refusée, le pro a été prévenu.', 'pros/' . $id, 'warning');
            case 'suspend':
                Pros::save($id, ['status' => 'suspended', 'suspended_at' => date('c')]);
                $this->audit('Fiche suspendue', ['pro' => $id]);
                return $this->done('Fiche suspendue (retirée du site).', 'pros/' . $id, 'warning');
            case 'activate':
                Pros::save($id, ['status' => 'active']);
                $this->audit('Fiche réactivée', ['pro' => $id]);
                return $this->done('Fiche remise en ligne.', 'pros/' . $id);
            case 'impersonate':
                if (in_array($pro['status'] ?? '', ['deleted'], true)) {
                    return $this->done('Impossible : fiche supprimée.', 'pros/' . $id, 'error');
                }
                Session::set('impersonate_from', (int) $this->current()['id']);
                Auth::loginPro($pro);
                $this->audit('Aperçu de l\'espace pro', ['pro' => $id]);
                return $this->redirect('/espace-pro/');
            case 'send-access':
                if (!Str::emailValid((string) ($pro['email'] ?? ''))) {
                    return $this->done('Cette fiche n\'a pas d\'email valide.', 'pros/' . $id, 'error');
                }
                $this->sendAccess($pro, false);
                return $this->done('Lien de (ré)initialisation du mot de passe envoyé à ' . $pro['email'] . '.', 'pros/' . $id);
            case 'resend-verify':
                RegisterController::sendVerification($pro);
                return $this->done('Email de confirmation renvoyé.', 'pros/' . $id);
            case 'verify-email':
                Store::pros()->update($id, ['email_verified' => true, 'email_verified_at' => date('c')]);
                return $this->done('Email marqué comme confirmé.', 'pros/' . $id);
            case 'unlock':
                Store::pros()->update($id, ['locked_until' => null, 'failed_logins' => 0, 'session_version' => (int) ($pro['session_version'] ?? 0) + 1]);
                return $this->done('Compte déverrouillé (sessions fermées).', 'pros/' . $id);
            case 'classify':
                $cats = Ai::classify($pro);
                $src = 'IA';
                if (!$cats) {
                    $cats = Categories::classify(['tags' => implode(' ', (array) ($pro['tags'] ?? [])), 'name' => Pros::displayName($pro), 'tagline' => (string) ($pro['tagline'] ?? ''), 'description' => Str::text((string) ($pro['description'] ?? ''))]);
                    $src = 'mots-clés';
                }
                if (!$cats) {
                    return $this->done('Aucune catégorie détectée.', 'pros/' . $id, 'warning');
                }
                Pros::save($id, static function (array $p) use ($cats): array {
                    $p['categories'] = $cats;
                    return $p;
                });
                return $this->done('Métiers proposés (' . $src . ') : ' . implode(', ', array_map([Categories::class, 'name'], $cats)), 'pros/' . $id);
            case 'geocode':
                Pros::save($id, static function (array $p): array {
                    $p['geo_manual'] = false;
                    $c = Geo::match((string) ($p['city'] ?? ''), (string) ($p['postcode'] ?? ''));
                    if ($c) {
                        $p['insee'] = $c['insee'];
                        $p['dep'] = $c['d'];
                        $p['region'] = $c['r'];
                        $p['lat'] = (float) $c['la'];
                        $p['lng'] = (float) $c['lo'];
                        $p['geo_precision'] = 'commune';
                    }
                    return $p;
                });
                return $this->done('Géolocalisation recalculée.', 'pros/' . $id);
            case 'recompute-reviews':
                Reviews::recompute($id);
                return $this->done('Note moyenne recalculée.', 'pros/' . $id);
            case 'delete':
                Pros::save($id, ['status' => 'deleted', 'deleted_at' => date('c'), 'deleted_reason' => 'back-office']);
                $this->audit('Fiche supprimée (corbeille)', ['pro' => $id]);
                return $this->done('Fiche placée dans la corbeille.', 'pros', 'warning');
            case 'erase':
                if (mb_strtoupper(trim((string) Request::input('confirm', ''))) !== 'EFFACER') {
                    return $this->done('Tapez EFFACER pour confirmer l\'effacement définitif.', 'pros/' . $id, 'error');
                }
                ProSpace::erase($id, 'effacement RGPD par ' . $this->by());
                return $this->done('Données personnelles et photos effacées définitivement.', 'pros', 'warning');
        }
        return $this->done('Action inconnue.', 'pros/' . $id, 'error');
    }

    private function validate(array $pro): void
    {
        $id = (int) $pro['id'];
        Pros::save($id, ['status' => 'active', 'validated_at' => date('c'), 'validated_by' => $this->by()]);
        $fresh = Store::pros()->get($id) ?? $pro;
        if (Str::emailValid((string) ($fresh['email'] ?? ''))) {
            Mail::send((string) $fresh['email'], 'pro_validated', ['prenom' => $fresh['first_name'] ?: Pros::displayName($fresh), 'fiche' => Pros::displayName($fresh)], ['bouton_url' => Url::pro($fresh), 'bouton_label' => 'Voir ma fiche en ligne']);
        }
        $this->audit('Fiche validée', ['pro' => $id]);
    }

    /** Envoie au pro un lien pour choisir son mot de passe (création de compte par l'équipe, accès perdu…). */
    private function sendAccess(array $pro, bool $welcome): void
    {
        $fp = substr(hash_hmac('sha256', (string) ($pro['password_hash'] ?? '') . '|' . (int) ($pro['session_version'] ?? 0), Crypto::key()), 0, 16);
        $token = Crypto::sign(['p' => (int) $pro['id'], 'h' => $fp], 'pwreset', 86400 * 7);
        Mail::send((string) $pro['email'], $welcome ? 'pro_welcome' : 'pro_password_reset', ['prenom' => $pro['first_name'] ?: Pros::displayName($pro)], [
            'bouton_url' => '/reinitialiser/' . $token . '/',
            'bouton_label' => $welcome ? 'Activer mon espace pro' : 'Choisir un nouveau mot de passe',
        ]);
        $this->audit('Lien d\'accès envoyé au pro', ['pro' => (int) $pro['id']]);
    }

    // ----------------------------------------------------------------- photos

    public function photos(int $id): Response
    {
        $pro = Store::pros()->get($id);
        if (!$pro) {
            throw new HttpException(404);
        }
        $photos = array_values((array) ($pro['photos'] ?? []));
        $action = (string) Request::input('action', 'upload');
        if ($action === 'upload') {
            $added = 0;
            foreach (Request::files('photos') as $f) {
                if (Image::validateUpload($f) !== null) {
                    continue;
                }
                $img = Image::store($f['tmp_name'], PUBLIC_PATH . '/media/pros/' . $id);
                $photos[] = ['id' => $img['id'], 'file' => $id . '/' . $img['id'], 'ext' => $img['ext'], 'w' => $img['w'], 'h' => $img['h'], 'alt' => Pros::displayName($pro), 'cover' => $photos === [], 'added_at' => date('c'), 'added_by' => $this->by()];
                $added++;
            }
            $msg = $added . ' photo(s) ajoutée(s).';
        } else {
            $pid = (string) Request::input('photo', '');
            $idx = null;
            foreach ($photos as $i => $ph) {
                if (($ph['id'] ?? '') === $pid) {
                    $idx = $i;
                }
            }
            if ($idx === null) {
                return $this->done('Photo introuvable.', 'pros/' . $id . '#photos', 'error');
            }
            if ($action === 'delete') {
                Image::deleteSet(PUBLIC_PATH . '/media/pros/' . $id, $pid);
                array_splice($photos, $idx, 1);
                if ($photos && !array_filter($photos, static fn ($x) => !empty($x['cover']))) {
                    $photos[0]['cover'] = true;
                }
                $msg = 'Photo supprimée.';
            } else {
                foreach ($photos as $i => $ph) {
                    $photos[$i]['cover'] = $i === $idx;
                }
                $msg = 'Photo principale modifiée.';
            }
        }
        Pros::save($id, static function (array $p) use ($photos): array {
            $p['photos'] = $photos;
            return $p;
        });
        $this->audit('Photos modifiées par l\'équipe', ['pro' => $id, 'action' => $action]);
        return $this->done($msg, 'pros/' . $id . '#photos');
    }
}
