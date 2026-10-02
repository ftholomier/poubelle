<?php
declare(strict_types=1);

namespace App\Controllers\Pro;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Session;
use App\Core\Str;
use App\Core\Url;
use App\Services\AntiSpam;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Mail;
use App\Services\Pros;
use App\Services\Seo;
use App\Services\Settings;
use App\Services\Stats;

/** Inscription gratuite des professionnels (validation par email puis par l'équipe). */
final class RegisterController extends Controller
{
    public function form(): Response
    {
        if (Auth::pro()) {
            return $this->redirect('/espace-pro/');
        }
        return $this->view('pro/register', [
            'open' => (bool) Settings::get('registration.enabled', true),
            'meta' => [
                'title' => 'Inscription gratuite pour les pros de l\'animation et de l\'événementiel',
                'description' => 'DJ, magicien, animateur, groupe, photobooth… Créez gratuitement votre fiche en 5 minutes et recevez des demandes de devis de clients près de chez vous. Sans commission.',
                'robots' => 'index, follow',
                'canonical' => Url::abs('/inscription-pro/'),
                'jsonld' => [Seo::breadcrumbs([['Accueil', '/'], ['Espace pros', '/professionnels/'], ['Inscription', '/inscription-pro/']])],
                'ads' => false,
            ],
        ]);
    }

    public function submit(): Response
    {
        if (!Settings::get('registration.enabled', true)) {
            return $this->respond(false, 'Les inscriptions sont momentanément fermées.', [], 403);
        }
        $insee = preg_match('/^[0-9AB]{5}$/', (string) Request::input('insee', '')) ? (string) Request::input('insee') : '';
        $commune = $insee !== '' ? Geo::commune($insee) : null;
        if (!$commune && ($city = Sanitizer::line((string) Request::input('city', ''), 80)) !== '') {
            $found = Geo::search($city, 1);
            $commune = $found ? Geo::commune($found[0]['insee']) : null;
        }
        $in = [
            'display_name' => Sanitizer::line((string) Request::input('display_name', ''), 80),
            'first_name' => Str::nameCase(Sanitizer::line((string) Request::input('first_name', ''), 60)),
            'last_name' => Str::nameCase(Sanitizer::line((string) Request::input('last_name', ''), 60)),
            'email' => Str::email((string) Request::input('email', '')),
            'phone' => Str::phone(Sanitizer::line((string) Request::input('phone', ''), 30)),
            'website' => Str::url(Sanitizer::line((string) Request::input('website', ''), 200)),
            'tagline' => Sanitizer::line((string) Request::input('tagline', ''), 220),
            'siret' => preg_replace('/\D/', '', (string) Request::input('siret', '')) ?? '',
            'categories' => array_slice(array_values(array_unique(array_filter((array) Request::arr('categories'), static fn ($c) => Categories::get((string) $c) !== null))), 0, 3),
        ];
        $password = (string) Request::raw('password', '');
        $errors = [];
        if (mb_strlen($in['display_name']) < 2) {
            $errors['display_name'] = 'Indiquez votre nom de scène ou le nom de votre société.';
        }
        if (mb_strlen($in['first_name']) < 2) {
            $errors['first_name'] = 'Indiquez votre prénom.';
        }
        if (!Str::emailValid($in['email'])) {
            $errors['email'] = 'Adresse email invalide.';
        } elseif (Pros::emailTaken($in['email'])) {
            $errors['email'] = 'Un compte existe déjà avec cet email : connectez-vous ou utilisez « mot de passe oublié ».';
        }
        if (!Str::phoneValid($in['phone'])) {
            $errors['phone'] = 'Indiquez un numéro de téléphone valide (il n\'est affiché qu\'à la demande des clients).';
        }
        if (!$commune) {
            $errors['city'] = 'Choisissez votre ville dans la liste.';
        }
        if (!$in['categories']) {
            $errors['categories'] = 'Choisissez au moins un métier (3 maximum).';
        }
        if (($pe = Auth::passwordError($password, [$in['email']])) !== null) {
            $errors['password'] = $pe;
        }
        if (Settings::get('registration.require_siren') && !preg_match('/^\d{9}(\d{5})?$/', $in['siret'])) {
            $errors['siret'] = 'Indiquez votre numéro SIREN ou SIRET (9 ou 14 chiffres).';
        }
        if (!Request::bool('cgu')) {
            $errors['cgu'] = 'Merci d\'accepter les conditions d\'utilisation.';
        }
        if ($errors) {
            return $this->respond(false, 'Merci de compléter les champs indiqués.', $errors);
        }
        $spam = AntiSpam::evaluate('register', [
            'name' => $in['display_name'] . ' ' . $in['first_name'] . ' ' . $in['last_name'],
            'email' => $in['email'],
            'phone' => $in['phone'],
            'city' => $commune['n'] ?? '',
            'message' => trim($in['tagline'] . ' ' . $in['website']),
        ]);
        if ($spam['blocked']) {
            return $this->respond(false, (string) $spam['message'], [], 429);
        }
        if ($spam['decision'] === 'spam') {
            Logger::log('spam', 'Inscription pro rejetée', ['email' => $in['email'], 'score' => $spam['score']]);
            return $this->respond(true, 'Merci !');
        }
        $company = $in['display_name'];
        $pro = Pros::create([
            'status' => 'pending',
            'display_name' => $company,
            'first_name' => $in['first_name'],
            'last_name' => $in['last_name'],
            'company' => $company,
            'email' => $in['email'],
            'phone' => $in['phone'],
            'address' => '',
            'postcode' => (string) ($commune['cp'][0] ?? ''),
            'city' => (string) $commune['n'],
            'website' => $in['website'],
            'socials' => [],
            'videos' => [],
            'tagline' => $in['tagline'],
            'description' => '',
            'tags' => [],
            'categories' => $in['categories'],
            'zones' => [(string) $commune['d']],
            'all_france' => false,
            'accept_requests' => true,
            'price_from' => null,
            'photos' => [],
            'siret' => $in['siret'],
            'login' => $in['email'],
            'password_hash' => Crypto::hashPassword($password),
            'must_change_password' => false,
            'email_verified' => false,
            'settings' => ['notify_requests' => true, 'notify_messages' => true, 'vacation' => false, 'newsletter' => Request::bool('newsletter'), 'weekly_report' => true],
            'stats' => ['views' => 0, 'phone_reveals' => 0, 'website_clicks' => 0],
            'rating' => ['avg' => 0, 'count' => 0],
            'source' => 'inscription',
            'registration' => ['at' => date('c'), 'ip_hash' => Request::ipHash(), 'ua' => Str::limit(Request::userAgent(), 180, ''), 'spam' => ['score' => $spam['score'], 'decision' => $spam['decision'], 'reasons' => $spam['reasons']]],
            'admin_notes' => $spam['decision'] === 'review' ? 'Inscription signalée par l\'anti-spam (score ' . $spam['score'] . ') : ' . implode(' ; ', $spam['reasons']) : '',
            'last_login_at' => date('c'),
        ]);
        self::sendVerification($pro, 'pro_welcome');
        Stats::hit('register');
        Logger::info('Nouvelle inscription pro', ['pro' => (int) $pro['id']]);
        Auth::loginPro($pro);
        return $this->respond(true, 'Bienvenue !');
    }

    /** Envoie (ou renvoie) l'email de confirmation d'adresse. */
    public static function sendVerification(array $pro, string $template = 'pro_verify_email', ?string $email = null): void
    {
        $email ??= (string) $pro['email'];
        $token = Crypto::sign(['p' => (int) $pro['id'], 'e' => mb_strtolower($email)], 'verify-email', 86400 * 7);
        Mail::send($email, $template, ['prenom' => $pro['first_name'] ?: Pros::displayName($pro)], [
            'bouton_url' => '/verifier-email/' . $token . '/',
            'bouton_label' => 'Confirmer mon adresse email',
        ]);
    }

    private function respond(bool $ok, string $message, array $errors = [], int $status = 422): Response
    {
        if (Request::isAjax()) {
            return Response::json(['ok' => $ok, 'message' => $message, 'error' => $ok ? null : $message, 'errors' => $errors, 'redirect' => $ok ? '/inscription-pro/merci/' : null], $ok ? 200 : $status);
        }
        if (!$ok) {
            Session::flash('error', $message);
            Session::withInput($_POST, $errors);
            return $this->redirect('/inscription-pro/#formulaire');
        }
        return $this->redirect('/inscription-pro/merci/');
    }

    public function thanks(): Response
    {
        $pro = Auth::pro();
        return $this->view('front/thanks', [
            'emoji' => '📬',
            'title' => 'Plus qu\'une étape : confirmez votre email',
            'text' => 'Nous venons de vous envoyer un lien de confirmation' . ($pro ? ' à ' . $pro['email'] : '') . '. Votre fiche sera publiée après une rapide vérification par notre équipe. En attendant, ajoutez vos photos et votre description : une fiche complète reçoit beaucoup plus de demandes !',
            'pro' => null,
            'cta' => $pro ? ['/espace-pro/fiche/', 'Compléter ma fiche'] : ['/connexion/', 'Me connecter'],
            'meta' => ['title' => 'Inscription enregistrée', 'robots' => 'noindex, nofollow', 'ads' => false],
        ]);
    }
}
