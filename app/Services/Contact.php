<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Request;
use App\Storage\Audit;

/**
 * Mise en relation : candidature à une offre, message à un candidat.
 *
 * Deux règles tiennent tout le service. Aucune adresse n'apparaît jamais dans
 * une page : le site relaie, l'expéditeur figure en `Reply-To`, et le
 * destinataire répond normalement. Et rien n'est conservé : ni le message, ni
 * le CV joint, ni l'adresse de l'expéditeur — le journal ne retient qu'un
 * compteur. C'est ce qui permet de rendre le service sans reprendre les
 * 21 709 candidatures de l'ancien site, volontairement laissées de côté.
 */
final class Contact
{
    /**
     * @return array{ok:bool, error:string}
     */
    public static function apply(Request $request, array $job): array
    {
        $to = trim((string) ($job['apply']['email'] ?? ''));
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return self::fail(I18n::t('apply.err_closed'));
        }

        $checked = self::common($request, 'apply');
        if ($checked['error'] !== '') {
            return $checked;
        }

        $attachments = [];
        if (!empty($request->files['cv_file']['name'])) {
            $file = Upload::read(
                $request->files['cv_file'],
                'cv',
                (int) Config::get('contact.attachment_max', 3 * 1024 * 1024),
            );
            if (!$file['ok'] && $file['error'] !== '') {
                return self::fail($file['error']);
            }
            if ($file['ok']) {
                $attachments[] = ['name' => $file['name'], 'mime' => $file['mime'], 'content' => $file['content']];
            }
        }

        $site = (string) Config::get('site.name');
        $link = rtrim((string) Config::get('site.url'), '/') . I18n::url('/offre/' . $job['slug'], 'fr');

        $body = "Une candidature vous a été adressée via {$site}.\n\n"
              . 'Offre : ' . $job['title'] . "\n"
              . $link . "\n\n"
              . "Candidat : " . $checked['name'] . "\n"
              . 'Répondre à : ' . $checked['email'] . "\n"
              . ($checked['phone'] !== '' ? 'Téléphone : ' . $checked['phone'] . "\n" : '')
              . ($attachments !== [] ? "CV joint : " . $attachments[0]['name'] . "\n" : '')
              . "\n----- Message -----\n\n"
              . $checked['message'] . "\n\n"
              . "-----\n"
              . "Répondez directement à ce message : votre réponse partira au candidat.\n"
              . "{$site} ne conserve ni ce message ni le CV joint.\n";

        $sent = Mailer::send(
            $to,
            'Candidature — ' . $job['title'],
            $body,
            $checked['email'],
            $attachments,
            true,   // rien de ce message ne doit survivre à l'envoi
        );

        if (!$sent) {
            Audit::log('apply.failed', ['job' => $job['id'], 'error' => Mailer::lastError()]);
            return self::fail(I18n::t('apply.err_send'));
        }

        // Le journal ne retient que le fait, jamais le contenu ni le candidat.
        Audit::log('apply.sent', ['job' => $job['id'], 'with_cv' => $attachments !== []]);
        Notifier::notify('application', 'Candidature envoyée', [
            'Offre'      => (string) $job['title'],
            'Employeur'  => (string) ($job['company']['name'] ?? ''),
            'CV joint'   => $attachments !== [],
        ], I18n::url('/offre/' . $job['slug'], 'fr'));

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Message à un candidat de l'annuaire. Son adresse n'est jamais exposée,
     * et il peut refuser d'être contacté depuis son dépôt de CV.
     *
     * @return array{ok:bool, error:string}
     */
    public static function message(Request $request, array $cv): array
    {
        if (!self::reachable($cv)) {
            return self::fail(I18n::t('cv.err_closed'));
        }

        $checked = self::common($request, 'contact');
        if ($checked['error'] !== '') {
            return $checked;
        }

        $site = (string) Config::get('site.name');
        $link = rtrim((string) Config::get('site.url'), '/') . I18n::url('/cv/' . $cv['slug'], 'fr');

        $body = "Un employeur vous a écrit via {$site}.\n\n"
              . 'Votre profil : ' . $link . "\n\n"
              . 'De : ' . $checked['name'] . ($checked['company'] !== '' ? ' (' . $checked['company'] . ')' : '') . "\n"
              . 'Répondre à : ' . $checked['email'] . "\n"
              . ($checked['phone'] !== '' ? 'Téléphone : ' . $checked['phone'] . "\n" : '')
              . "\n----- Message -----\n\n"
              . $checked['message'] . "\n\n"
              . "-----\n"
              . "Répondez directement à ce message. Votre adresse n'a jamais été affichée sur le site.\n"
              . "Pour ne plus être contacté, modifiez votre fiche depuis {$site}.\n";

        $sent = Mailer::send(
            (string) $cv['contact']['email'],
            'Un employeur vous contacte — ' . ($cv['title'] ?: $cv['name']),
            $body,
            $checked['email'],
            [],
            true,
        );

        if (!$sent) {
            Audit::log('contact.failed', ['cv' => $cv['id'], 'error' => Mailer::lastError()]);
            return self::fail(I18n::t('apply.err_send'));
        }

        Audit::log('contact.sent', ['cv' => $cv['id']]);
        Notifier::notify('contact', 'Message envoyé à un candidat', [
            'Profil' => (string) ($cv['title'] ?: $cv['name']),
            'Ville'  => (string) ($cv['location']['city'] ?? ''),
        ], I18n::url('/cv/' . $cv['slug'], 'fr'));

        return ['ok' => true, 'error' => ''];
    }

    /** Un candidat est joignable s'il a une adresse et n'a pas refusé. */
    public static function reachable(array $cv): bool
    {
        $email = trim((string) ($cv['contact']['email'] ?? ''));
        return $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && ($cv['contact']['form'] ?? true) !== false
            && ($cv['status'] ?? '') === 'publish';
    }

    /**
     * Contrôles partagés : limite de débit, piège à robots, champs obligatoires.
     *
     * @return array{ok:bool,error:string,name:string,email:string,phone:string,company:string,message:string}
     */
    private static function common(Request $request, string $bucket): array
    {
        $out = [
            'ok' => false, 'error' => '', 'name' => '', 'email' => '',
            'phone' => '', 'company' => '', 'message' => '',
        ];

        $max = (int) Config::get('contact.max_per_hour', 5);
        if (RateLimit::hit($bucket, $request->ip(), $max, 3600) > 0) {
            $out['error'] = I18n::t('form.err_rate');
            return $out;
        }

        $verdict = SpamGuard::inspect($request, [
            'title'       => (string) $request->input('name', ''),
            'description' => (string) $request->input('message', ''),
        ], $bucket);

        if ($verdict['action'] === 'reject') {
            Audit::log($bucket . '.blocked', ['reasons' => $verdict['reasons']]);
            $out['error'] = I18n::t('form.err_spam');
            return $out;
        }

        $name    = trim((string) $request->input('name', ''));
        $email   = trim((string) $request->input('email', ''));
        $phone   = trim((string) $request->input('phone', ''));
        $company = trim((string) $request->input('company', ''));
        $message = Sanitizer::text(
            (string) $request->input('message', ''),
            (int) Config::get('contact.message_max', 4000),
        );

        if ($name === '' || mb_strlen($name) > 120) {
            $out['error'] = I18n::t('apply.err_name');
            return $out;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $out['error'] = I18n::t('form.err_email');
            return $out;
        }
        if (mb_strlen(trim($message)) < 20) {
            $out['error'] = I18n::t('apply.err_message');
            return $out;
        }

        return [
            'ok' => true, 'error' => '', 'name' => $name, 'email' => $email,
            'phone' => mb_substr($phone, 0, 40), 'company' => mb_substr($company, 0, 120),
            'message' => $message,
        ];
    }

    private static function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
