<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\JsonStore;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Data\Collections;
use App\Services\Mailer;

/**
 * Communauté : contact (formulaire selon la demande), contributions (4 étapes, fichiers,
 * file de validation), newsletter « Ce jour-là » (double validation), page Partage.
 * Antispam : jeton CSRF, champ piège, durée minimale de saisie, limites par adresse IP.
 */
final class Community
{
    public const INBOX = STORAGE_PATH . '/inbox';
    public const REASONS = ['partenariat' => 'Devenir partenaire', 'archive' => 'Confier une archive', 'erreur' => 'Signaler une erreur', 'autre' => 'Autre demande'];
    public const TYPES = ['correction' => ['✎', 'Une correction', 'Une date, un score, un nom à rectifier.'], 'photo' => ['▣', 'Une photo', "Au stade, à l'entraînement, en déplacement."], 'document' => ['▤', 'Un document', 'Billet, programme, affiche, coupure de presse.'], 'temoignage' => ['❝', 'Un témoignage', 'Un souvenir de match, une anecdote.']];
    private const UPLOAD_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'tif', 'tiff'];
    private const UPLOAD_MAX = 25 * 1024 * 1024;
    /** Fichiers reçus en attente de tri, au total : au-delà, les envois avec fichiers sont refusés (disque de l'hébergement). */
    private const INBOX_MAX = 3 * 1024 * 1024 * 1024;

    // ------------------------------------------------------------------ contact

    public static function contact(Request $req): Response
    {
        $reason = isset(self::REASONS[$req->str('objet')]) ? $req->str('objet') : 'partenariat';
        return Pages::render('community/contact', [
            'reason' => $reason,
            'partners' => Collections::get('partenaires', []),
            'flash' => Session::pull('flash_contact'),
            'old' => Session::pull('old_contact', []),
        ], [
            'title' => t('Contact et partenaires'),
            'description' => t('Un partenariat, une archive à confier, une erreur à signaler ? Écrivez à l’équipe de Sochaux Rétro.'),
            'styles' => ['css/community.css'],
            'scripts' => ['js/community.js'],
        ]);
    }

    public static function contactSend(Request $req): Response
    {
        $back = url('/contact/');
        $f = fn (string $k, int $max = 200) => trim(mb_substr((string) ($req->post[$k] ?? ''), 0, $max));
        $data = [
            'reason' => isset(self::REASONS[$f('reason')]) ? $f('reason') : 'autre',
            'name' => $f('name', 120),
            'email' => $f('email', 160),
            'org' => $f('org', 160),
            'phone' => $f('phone', 40),
            'message' => $f('message', 8000),
            'page' => $f('page', 300),
        ];
        $err = self::guard($req, 'contact', 5) ?? (match (true) {
            $data['name'] === '' => t('Merci d’indiquer votre nom.'),
            !filter_var($data['email'], FILTER_VALIDATE_EMAIL) => t('Merci d’indiquer une adresse e-mail valide.'),
            mb_strlen($data['message']) < 10 => t('Votre message est un peu court.'),
            substr_count(mb_strtolower($data['message']), 'http') > 4 => t('Votre message contient trop de liens.'),
            empty($req->post['consent']) => t('Merci d’accepter que nous utilisions vos coordonnées pour vous répondre.'),
            default => null,
        });
        if ($err) {
            Session::set('flash_contact', ['type' => 'error', 'msg' => $err]);
            Session::set('old_contact', $data);
            return Response::redirect($back . '?objet=' . $data['reason'] . '#formulaire');
        }
        $id = date('YmdHis') . '-' . bin2hex(random_bytes(3));
        JsonStore::write(self::INBOX . "/messages/$id.json", $data + ['id' => $id, 'at' => date('c'), 'status' => 'nouveau', 'lang' => \App\Services\I18n::lang()]);
        $to = (string) Settings::get('general.contact_email', '');
        if ($to !== '') {
            Mailer::send($to, '[Contact] ' . self::REASONS[$data['reason']] . ' · ' . $data['name'],
                '<p><b>' . e(self::REASONS[$data['reason']]) . '</b></p><p>' . e($data['name']) . ($data['org'] ? ' · ' . e($data['org']) : '') . '<br>' . e($data['email']) . ($data['phone'] ? ' · ' . e($data['phone']) : '') . '</p><p>' . nl2br(e($data['message'])) . '</p><p><a href="' . e(base_url()) . '/admin/messages/' . e($id) . '">Ouvrir dans le back-office</a></p>',
                $data['email']);
        }
        Session::set('flash_contact', ['type' => 'ok', 'msg' => t('Message envoyé ✓ Nous vous répondons dans les plus brefs délais.')]);
        return Response::redirect($back . '?objet=' . $data['reason'] . '#formulaire');
    }

    // ------------------------------------------------------------------ contributions

    public static function contribute(Request $req): Response
    {
        return Pages::render('community/contribuer', [
            'types' => self::TYPES,
            'flash' => Session::pull('flash_contrib'),
            'ticket' => preg_match('/^SR-\d{4}-\d{4,}$/', $req->str('merci')) ? $req->str('merci') : null,
            'fiche' => mb_substr($req->str('fiche'), 0, 200),
            'type' => isset(self::TYPES[$req->str('type')]) ? $req->str('type') : '',
        ], [
            'title' => t('Contribuer au musée'),
            'description' => t('Une correction, une photo, un document, un témoignage : enrichissez le musée en ligne du FCSM.'),
            'styles' => ['css/community.css'],
            'scripts' => ['js/community.js'],
        ]);
    }

    public static function contributeSend(Request $req): Response
    {
        $back = url('/contribuer/');
        $f = fn (string $k, int $max = 300) => trim(mb_substr((string) ($req->post[$k] ?? ''), 0, $max));
        $data = [
            'type' => isset(self::TYPES[$f('type')]) ? $f('type') : '',
            'fiche' => $f('fiche', 300),
            'date' => $f('date', 120),
            'place' => $f('place', 160),
            'description' => $f('description', 10000),
            'name' => $f('name', 120),
            'email' => $f('email', 160),
            'credit' => $f('credit', 200),
        ];
        $err = self::guard($req, 'contrib', 6) ?? (match (true) {
            $data['type'] === '' => t('Choisissez ce que vous souhaitez proposer.'),
            mb_strlen($data['description']) < 10 && empty($req->files['files']['name'][0]) => t('Décrivez votre contribution en quelques mots.'),
            $data['name'] === '' => t('Merci d’indiquer votre nom.'),
            !filter_var($data['email'], FILTER_VALIDATE_EMAIL) => t('Merci d’indiquer une adresse e-mail valide.'),
            empty($req->post['rights']) => t('Merci de confirmer que vous détenez les droits sur ces documents.'),
            default => null,
        });
        $files = [];
        if (!$err && !empty($req->files['files']['name']) && is_array($req->files['files']['name'])) {
            $up = $req->files['files'];
            $count = 0;
            foreach ($up['name'] as $i => $name) {
                if (($up['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if (++$count > 10) {
                    $err = t('Dix fichiers au maximum par envoi.');
                    break;
                }
                $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $up['tmp_name'][$i]) ?: '';
                if (($up['error'][$i] ?? 1) !== UPLOAD_ERR_OK || !in_array($ext, self::UPLOAD_EXT, true) || !preg_match('#^(image/(jpeg|png|gif|webp|tiff)|application/pdf)$#', $mime)) {
                    $err = t('Le fichier « {f} » n’est pas accepté (JPG, PNG, WebP, TIFF ou PDF).', ['f' => mb_substr((string) $name, 0, 60)]);
                    break;
                }
                if ((int) $up['size'][$i] > self::UPLOAD_MAX) {
                    $err = t('Le fichier « {f} » dépasse 25 Mo.', ['f' => mb_substr((string) $name, 0, 60)]);
                    break;
                }
                $files[] = ['tmp' => (string) $up['tmp_name'][$i], 'name' => preg_replace('/[^\w.\- ]+/u', '_', (string) $name), 'ext' => $ext, 'mime' => $mime, 'size' => (int) $up['size'][$i]];
            }
        }
        if (!$err && $files && self::inboxSize() + array_sum(array_column($files, 'size')) > self::INBOX_MAX) {
            error_log('[contributions] boîte de dépôt pleine (' . self::INBOX_MAX . ' octets) : envoi refusé');
            $err = t('La boîte de dépôt du musée est pleine pour le moment : réessayez dans quelques jours, ou écrivez-nous depuis la page Contact.');
        }
        if ($err) {
            Session::set('flash_contrib', ['type' => 'error', 'msg' => $err, 'old' => $data]);
            return Response::redirect($back . '#formulaire');
        }
        $year = date('Y');
        $n = JsonStore::update(self::INBOX . '/contributions/counter.json', fn ($c) => ['year' => $year, 'n' => (($c['year'] ?? '') === $year ? (int) $c['n'] : 0) + 1], [])['n'];
        $ticket = sprintf('SR-%s-%04d', $year, $n);
        $dir = self::INBOX . "/contributions/$ticket";
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $stored = [];
        foreach ($files as $k => $file) {
            $dest = sprintf('%s/%02d-%s.%s', $dir, $k + 1, bin2hex(random_bytes(4)), $file['ext']);
            if (move_uploaded_file($file['tmp'], $dest) || (PHP_SAPI === 'cli' && rename($file['tmp'], $dest))) {
                $stored[] = ['file' => basename($dest), 'name' => $file['name'], 'mime' => $file['mime'], 'size' => $file['size']];
            }
        }
        JsonStore::write("$dir/contribution.json", $data + ['ticket' => $ticket, 'at' => date('c'), 'status' => 'nouveau', 'files' => $stored, 'lang' => \App\Services\I18n::lang()]);
        Mailer::send($data['email'], t('Votre contribution {t} est bien arrivée', ['t' => $ticket]),
            '<p>' . e(t('Merci {n} !', ['n' => $data['name']])) . '</p><p>' . e(t('Votre contribution a rejoint la file de validation de l’équipe. Vous recevrez un e-mail dès sa publication.')) . '</p><p><b>' . e(t('N° de suivi')) . ' : ' . e($ticket) . '</b></p>');
        if ($to = (string) Settings::get('general.contact_email', '')) {
            Mailer::send($to, "[Contribution $ticket] " . self::TYPES[$data['type']][1] . ' · ' . $data['name'], '<p>' . e(self::TYPES[$data['type']][1]) . ' — ' . count($stored) . ' fichier(s)</p><p>' . nl2br(e($data['description'])) . '</p><p><a href="' . e(base_url()) . '/admin/contributions/' . e($ticket) . '">Ouvrir dans le back-office</a></p>', $data['email']);
        }
        return Response::redirect($back . '?merci=' . $ticket . '#formulaire');
    }

    /** Contrôles communs : CSRF, champ piège, durée de saisie, limite par IP. Renvoie un message d'erreur ou null. */
    private static function guard(Request $req, string $bucket, int $perHour): ?string
    {
        if (!Session::checkCsrf((string) ($req->post['_csrf'] ?? ''))) {
            return t('Votre session a expiré : merci de renvoyer le formulaire.');
        }
        if (trim((string) ($req->post['website'] ?? '')) !== '') {
            return t('Envoi refusé.');
        }
        // Horodatage signé par le serveur : absent ou falsifié = robot ; trop rapide = robot.
        $age = form_ts_age((string) ($req->post['_ts'] ?? ''));
        if ($age === null || $age < 3) {
            return t('Merci de prendre le temps de remplir le formulaire.');
        }
        if ($age > 172800) {
            return t('Le formulaire a expiré : rechargez la page puis renvoyez-le.');
        }
        if (!RateLimiter::hit($bucket, $req->ip(), $perHour, 3600)) {
            return t('Trop d’envois depuis votre connexion : réessayez dans une heure.');
        }
        return null;
    }

    // ------------------------------------------------------------------ newsletter

    private const SUBS = STORAGE_PATH . '/newsletter/subscribers.json';

    public static function newsletter(Request $req): Response
    {
        return self::sharePage($req);
    }

    public static function newsletterApi(Request $req): Response
    {
        $d = $req->json() ?: $req->post;
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        if (trim((string) ($d['website'] ?? '')) !== '') {
            return Response::json(['ok' => true]); // robot : on ne dit rien
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::json(['ok' => false, 'error' => t('Adresse e-mail invalide.')], 422);
        }
        if (!RateLimiter::hit('newsletter', $req->ip(), 10, 3600)) {
            return Response::json(['ok' => false, 'error' => t('Trop de demandes, réessayez plus tard.')], 429);
        }
        $key = hash('sha256', $email);
        $token = bin2hex(random_bytes(16));
        $state = JsonStore::update(self::SUBS, function ($all) use ($key, $email, $token) {
            $all = $all ?: [];
            $cur = $all[$key] ?? null;
            if ($cur && $cur['status'] === 'active') {
                return $all;
            }
            $all[$key] = ['email' => $email, 'status' => 'pending', 'token' => $token, 'created' => $cur['created'] ?? date('c'), 'requested' => date('c'), 'lang' => \App\Services\I18n::lang()];
            return $all;
        }, []);
        if (($state[$key]['status'] ?? '') === 'active') {
            return Response::json(['ok' => true, 'message' => t('Vous êtes déjà inscrit(e). Merci !')]);
        }
        $link = base_url() . url('/newsletter/confirmer/' . $state[$key]['token'] . '/');
        Mailer::send($email, t('Confirmez votre inscription à « Ce jour-là »'),
            '<p>' . e(t('Bonjour,')) . '</p><p>' . e(t('Pour recevoir chaque semaine la newsletter « Ce jour-là » du musée Sochaux Rétro, confirmez votre adresse :')) . '</p><p><a href="' . e($link) . '" style="display:inline-block;background:#0E1F4D;color:#F6C400;padding:12px 18px;text-decoration:none;font-weight:bold">' . e(t('Je confirme mon inscription')) . '</a></p><p style="font-size:13px;color:#3A4A75">' . e(t('Si vous n’êtes pas à l’origine de cette demande, ignorez simplement ce message.')) . '</p>');
        return Response::json(['ok' => true, 'message' => t('Presque fini ! Confirmez votre inscription grâce au lien reçu par e-mail.')]);
    }

    /**
     * Lien de confirmation : la page affiche un bouton, l'inscription n'est validée qu'en le
     * pressant (les messageries ouvrent seules les liens des e-mails pour les analyser).
     */
    public static function newsletterConfirm(Request $req, string $token): Response
    {
        $sub = self::subscriberByToken($token);
        if (!$sub || ($sub['status'] ?? '') === 'unsubscribed') {
            return self::message(t('Lien invalide ou expiré'), t('Ce lien de confirmation n’est plus valable. Vous pouvez vous réinscrire depuis la page Partage & newsletter.'));
        }
        if (($sub['status'] ?? '') === 'active') {
            return self::message(t('Inscription confirmée !'), t('Vous recevrez la newsletter « Ce jour-là » chaque semaine. À très vite au musée !'));
        }
        if ($req->method !== 'POST' || !Session::checkCsrf((string) ($req->post['_csrf'] ?? ''))) {
            return self::message(t('Confirmez votre inscription'), t('Un dernier clic pour recevoir chaque semaine la newsletter « Ce jour-là » du musée.'), t('Je confirme mon inscription'));
        }
        JsonStore::update(self::SUBS, function ($all) use ($token) {
            foreach ($all ?: [] as $k => $s) {
                if (hash_equals((string) $s['token'], $token) && ($s['status'] ?? '') !== 'unsubscribed') {
                    $all[$k]['status'] = 'active';
                    $all[$k]['confirmed'] = date('c');
                }
            }
            return $all ?: [];
        }, []);
        return self::message(t('Inscription confirmée !'), t('Vous recevrez la newsletter « Ce jour-là » chaque semaine. À très vite au musée !'));
    }

    /** Lien de désinscription : bouton à presser (pas de désinscription par la simple ouverture du lien). */
    public static function newsletterUnsubscribe(Request $req, string $token): Response
    {
        $sub = self::subscriberByToken($token);
        if (!$sub) {
            return self::message(t('Lien invalide'), t('Ce lien de désinscription n’est pas valable.'));
        }
        if (($sub['status'] ?? '') === 'unsubscribed') {
            return self::message(t('Désinscription effectuée'), t('Vous ne recevrez plus la newsletter. Votre adresse a été effacée.'));
        }
        if ($req->method !== 'POST' || !Session::checkCsrf((string) ($req->post['_csrf'] ?? ''))) {
            return self::message(t('Se désinscrire de la newsletter'), t('Vous ne recevrez plus la newsletter « Ce jour-là » et votre adresse sera effacée.'), t('Me désinscrire'));
        }
        JsonStore::update(self::SUBS, function ($all) use ($token) {
            foreach ($all ?: [] as $k => $s) {
                if (hash_equals((string) $s['token'], $token)) {
                    // Désinscription : l'adresse est effacée (RGPD), seule l'empreinte reste pour mémoire.
                    $all[$k] = ['email' => null, 'status' => 'unsubscribed', 'token' => $s['token'], 'created' => $s['created'] ?? null, 'unsubscribed' => date('c')];
                }
            }
            return $all ?: [];
        }, []);
        return self::message(t('Désinscription effectuée'), t('Vous ne recevrez plus la newsletter. Votre adresse a été effacée.'));
    }

    private static function subscriberByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        foreach (JsonStore::read(self::SUBS, []) ?: [] as $s) {
            if (hash_equals((string) ($s['token'] ?? ''), $token)) {
                return $s;
            }
        }
        return null;
    }

    /** Taille totale des fichiers reçus (contributions pas encore supprimées). */
    private static function inboxSize(): int
    {
        $n = 0;
        $dir = self::INBOX . '/contributions';
        if (is_dir($dir)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                $n += (int) $f->getSize();
            }
        }
        return $n;
    }

    /** Abonnés actifs (pour l'envoi). */
    public static function subscribers(): array
    {
        return array_values(array_filter(JsonStore::read(self::SUBS, []), fn ($s) => ($s['status'] ?? '') === 'active' && !empty($s['email'])));
    }

    private static function message(string $title, string $text, ?string $action = null): Response
    {
        return Pages::render('community/message', ['title' => $title, 'text' => $text, 'action' => $action], ['title' => $title, 'noindex' => true, 'styles' => ['css/community.css']]);
    }

    // ------------------------------------------------------------------ partage & newsletter

    public static function sharePage(Request $req): Response
    {
        $sample = \App\Data\Derived::onThisDay()[0] ?? null;
        return Pages::render('community/partage', ['sample' => $sample, 'days' => Site::daysToCentenary()], [
            'title' => t('Partage et newsletter « Ce jour-là »'),
            'description' => t('Chaque fiche du musée a son image de partage, et la newsletter « Ce jour-là » vous raconte chaque semaine les matchs du passé.'),
            'styles' => ['css/community.css'],
            'scripts' => ['js/community.js'],
        ]);
    }
}
