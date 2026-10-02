<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Crypto;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Session;
use App\Core\Str;
use App\Services\AntiSpam;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Leads;
use App\Services\Pros;
use App\Services\Seo;
use App\Services\Stats;
use App\Services\Store;

/** Demande de devis (appel d'offres) : diffusée aux pros du secteur ou aux favoris choisis. */
final class DevisController extends Controller
{
    public function form(): Response
    {
        $targets = [];
        $ids = array_filter(array_map('intval', explode(',', (string) Request::query('pros', (string) Request::query('pro', '')))));
        $index = Pros::publicIndex();
        foreach (array_slice($ids, 0, 20) as $id) {
            if (isset($index[$id])) {
                $targets[$id] = $index[$id];
            }
        }
        $insee = Request::str('insee', 5);
        $commune = $insee !== '' ? Geo::commune($insee) : null;
        if (!$commune && ($ville = Request::str('ville', 80)) !== '') {
            $found = Geo::search($ville, 1);
            $commune = $found ? Geo::commune($found[0]['insee']) : null;
        }
        $occ = Request::str('occasion', 40);
        $type = '';
        foreach (['mariage', 'anniversaire', 'entreprise', 'soiree'] as $t) {
            if ($occ !== '' && str_contains(Str::norm($occ), Str::norm($t === 'soiree' ? 'soir' : $t))) {
                $type = $t;
            }
        }
        $prefill = [
            'cat' => Categories::get(Request::str('cat', 60)) ? Request::str('cat', 60) : '',
            'commune' => $commune,
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', Request::str('date', 10)) ? Request::str('date', 10) : '',
            'guests' => Request::str('invites', 20),
            'details' => Request::str('details', 800),
            'type' => $type,
            'source' => Request::str('src', 20),
        ];
        Stats::hit('pv');
        return $this->view('front/devis', [
            'targets' => $targets,
            'prefill' => $prefill,
            'meta' => [
                'title' => 'Demande de devis gratuite : DJ, animateur, groupe, magicien',
                'description' => 'Décrivez votre événement en 1 minute : votre demande est transmise gratuitement aux pros de l\'animation de votre secteur, qui vous répondent directement.',
                'jsonld' => [Seo::breadcrumbs([['Accueil', '/'], ['Demande de devis', '/devis/']])],
                'robots' => Request::queryString() !== '' ? 'noindex, follow' : 'index, follow',
                'canonical' => \App\Core\Url::abs('/devis/'),
            ],
        ]);
    }

    public function submit(): Response
    {
        $in = [
            'event_type' => array_key_exists((string) Request::input('event_type', ''), Leads::EVENT_TYPES) ? (string) Request::input('event_type') : 'autre',
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) Request::input('date', '')) ? (string) Request::input('date') : '',
            'date_flexible' => Request::bool('date_flexible'),
            'insee' => preg_match('/^[0-9AB]{5}$/', (string) Request::input('insee', '')) ? (string) Request::input('insee') : '',
            'city' => Sanitizer::line((string) Request::input('city', ''), 80),
            'place' => Sanitizer::line((string) Request::input('place', ''), 120),
            'guests' => Sanitizer::line((string) Request::input('guests', ''), 30),
            'budget' => Sanitizer::line((string) Request::input('budget', ''), 40),
            'categories' => array_values(array_filter((array) Request::arr('categories'), static fn ($c) => Categories::get((string) $c) !== null)),
            'message' => Sanitizer::text((string) Request::input('message', ''), 5000),
            'client_type' => in_array(Request::input('client_type'), ['particulier', 'societe', 'association'], true) ? (string) Request::input('client_type') : 'particulier',
            'first_name' => Str::nameCase(Sanitizer::line((string) Request::input('first_name', ''), 60)),
            'last_name' => Str::nameCase(Sanitizer::line((string) Request::input('last_name', ''), 60)),
            'company' => Sanitizer::line((string) Request::input('company', ''), 100),
            'email' => Str::email((string) Request::input('email', '')),
            'phone' => Str::phone(Sanitizer::line((string) Request::input('phone', ''), 30)),
            'target_pros' => array_slice(array_filter(array_map('intval', (array) Request::arr('pros'))), 0, 20),
            'source' => in_array(Request::input('source'), ['assistant', 'favoris', 'form'], true) ? (string) Request::input('source') : 'form',
        ];
        $errors = [];
        if (!$in['insee'] && $in['city'] !== '') {
            $found = Geo::search($in['city'], 1);
            if ($found) {
                $in['insee'] = $found[0]['insee'];
            }
        }
        if (!$in['insee']) {
            $errors['city'] = 'Indiquez la ville de l\'événement (choisissez-la dans la liste).';
        }
        if ($in['date'] !== '' && $in['date'] < date('Y-m-d')) {
            $errors['date'] = 'La date est déjà passée.';
        }
        if (mb_strlen($in['message']) < 20) {
            $errors['message'] = 'Décrivez votre besoin en quelques phrases (20 caractères minimum).';
        }
        if (mb_strlen($in['first_name']) < 2) {
            $errors['first_name'] = 'Indiquez votre prénom.';
        }
        if (!Str::emailValid($in['email'])) {
            $errors['email'] = 'Adresse email invalide.';
        }
        if ($in['phone'] !== '' && !Str::phoneValid($in['phone'])) {
            $errors['phone'] = 'Numéro de téléphone invalide.';
        }
        if (!Request::bool('consent')) {
            $errors['consent'] = 'Merci d\'accepter la transmission de votre demande aux professionnels.';
        }
        if (!$in['categories']) {
            $in['categories'] = Categories::detect($in['message']);
        }
        if ($errors) {
            return $this->respond(false, 'Merci de compléter les champs indiqués.', $errors);
        }
        $spam = AntiSpam::evaluate('devis', ['name' => $in['first_name'] . ' ' . $in['last_name'], 'email' => $in['email'], 'phone' => $in['phone'], 'city' => $in['city'], 'message' => $in['message'], 'event' => Leads::EVENT_TYPES[$in['event_type']] ?? '']);
        if ($spam['blocked']) {
            return $this->respond(false, (string) $spam['message'], [], 429);
        }
        if ($spam['decision'] === 'spam') {
            // Rejet silencieux : le robot reçoit la même réponse qu'un humain.
            Leads::createRequest($in, $spam);
            return $this->respond(true, 'Merci !', [], 200, Crypto::sign(['id' => 0], 'devis', 86400));
        }
        $req = Leads::createRequest($in, $spam);
        return $this->respond(true, 'Votre demande est bien enregistrée !', [], 200, Crypto::sign(['id' => (int) $req['id']], 'devis', 86400));
    }

    private function respond(bool $ok, string $message, array $errors, int $status = 422, ?string $token = null): Response
    {
        $redirect = $ok ? '/devis/merci/?t=' . rawurlencode((string) $token) : null;
        if (Request::isAjax()) {
            return Response::json(['ok' => $ok, 'message' => $message, 'error' => $ok ? null : $message, 'errors' => $errors, 'redirect' => $redirect], $ok ? 200 : $status);
        }
        if (!$ok) {
            Session::flash('error', $message);
            Session::withInput($_POST, $errors);
            return $this->redirect('/devis/#formulaire');
        }
        return $this->redirect((string) $redirect);
    }

    public function thanks(): Response
    {
        $t = Crypto::verify((string) Request::query('t', ''), 'devis');
        $req = $t && $t['id'] > 0 ? Store::requests()->get((int) $t['id']) : null;
        return $this->view('front/devis-merci', [
            'req' => $req,
            'meta' => ['title' => 'Demande envoyée', 'robots' => 'noindex, nofollow'],
        ]);
    }
}
