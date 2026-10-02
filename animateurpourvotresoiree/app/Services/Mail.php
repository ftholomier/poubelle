<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Url;
use App\Core\View;

/**
 * Emails transactionnels (modèles éditables dans le back-office) et file d'envoi.
 * Les variables s'écrivent {{nom}} ; {{lien}} et {{bouton}} sont fournis par le code.
 */
final class Mail
{
    /** Modèles par défaut : clé => [libellé, sujet, corps HTML] */
    public static function defaults(): array
    {
        return [
            'pro_welcome' => ['Bienvenue (inscription pro)', 'Bienvenue sur {{site}} 🎉', '<p>Bonjour {{prenom}},</p><p>Merci pour votre inscription ! Confirmez votre adresse email pour finaliser la création de votre fiche :</p>{{bouton}}<p>Votre fiche sera publiée après une rapide vérification par notre équipe.</p>'],
            'pro_verify_email' => ['Confirmation d\'email (pro)', 'Confirmez votre adresse email', '<p>Bonjour {{prenom}},</p><p>Pour confirmer votre adresse email, cliquez sur le bouton ci-dessous :</p>{{bouton}}'],
            'pro_validated' => ['Fiche validée', 'Votre fiche est en ligne ! 🚀', '<p>Bonjour {{prenom}},</p><p>Bonne nouvelle : votre fiche <strong>{{fiche}}</strong> est maintenant visible sur {{site}}. Vous recevrez par email les demandes de devis de votre secteur.</p>{{bouton}}<p>Astuce : ajoutez des photos et une vidéo pour multiplier vos demandes.</p>'],
            'pro_rejected' => ['Fiche refusée', 'Votre inscription sur {{site}}', '<p>Bonjour {{prenom}},</p><p>Nous ne pouvons pas publier votre fiche en l\'état.</p><p>{{motif}}</p><p>Vous pouvez la corriger depuis votre espace ou nous répondre pour en discuter.</p>'],
            'pro_password_reset' => ['Mot de passe oublié', 'Réinitialisation de votre mot de passe', '<p>Bonjour {{prenom}},</p><p>Vous avez demandé à réinitialiser votre mot de passe. Ce lien est valable 1 heure :</p>{{bouton}}<p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement cet email.</p>'],
            'request_to_pro' => ['Nouvelle demande de devis (au pro)', '🎉 Nouvelle demande : {{evenement}} {{lieu_court}}', '<p>Bonjour {{prenom}},</p><p>Un client recherche un prestataire pour son événement. Voici sa demande :</p>{{details}}<p>Contactez-le rapidement : les premiers à répondre sont souvent retenus !</p>{{bouton}}'],
            'request_confirmation' => ['Confirmation de demande (au client)', 'Votre demande de devis est bien reçue', '<p>Bonjour {{prenom}},</p><p>Merci ! Votre demande a bien été enregistrée.</p>{{details}}<p>{{suite}}</p><p>Vous n\'avez rien d\'autre à faire : les professionnels intéressés vous contacteront directement.</p>'],
            'message_to_pro' => ['Nouveau message (au pro)', '✉️ Nouveau message de {{client}}', '<p>Bonjour {{prenom}},</p><p>Vous avez reçu un message depuis votre fiche :</p>{{details}}{{bouton}}'],
            'message_confirmation' => ['Confirmation de message (au client)', 'Votre message à {{fiche}} est bien parti', '<p>Bonjour {{prenom}},</p><p>Votre message a été transmis à <strong>{{fiche}}</strong>{{suite}}.</p>{{details}}'],
            'review_verify' => ['Confirmation d\'un avis', 'Confirmez votre avis sur {{fiche}}', '<p>Bonjour {{prenom}},</p><p>Merci pour votre avis sur <strong>{{fiche}}</strong> ! Confirmez-le en un clic :</p>{{bouton}}<p>Il sera publié après relecture.</p>'],
            'review_invite' => ['Invitation à laisser un avis', '{{fiche}} vous invite à donner votre avis', '<p>Bonjour,</p><p><strong>{{fiche}}</strong> vous remercie pour votre confiance et serait ravi de connaître votre avis sur sa prestation.</p>{{bouton}}<p>Cela ne prend qu\'une minute et aide d\'autres clients à choisir.</p>'],
            'review_published' => ['Nouvel avis publié (au pro)', '⭐ Nouvel avis sur votre fiche', '<p>Bonjour {{prenom}},</p><p>Un nouvel avis ({{note}}/5) a été publié sur votre fiche :</p><blockquote>{{avis}}</blockquote><p>Vous pouvez y répondre publiquement depuis votre espace.</p>{{bouton}}'],
            'site_contact' => ['Formulaire de contact (à l\'admin)', 'Contact : {{sujet}}', '<p>Message reçu depuis le formulaire de contact :</p>{{details}}'],
            'admin_alert' => ['Alerte administrateur', '[{{site}}] {{titre}}', '<p>{{corps}}</p>{{bouton}}'],
            'admin_digest' => ['Récapitulatif quotidien (admin)', '[{{site}}] Récapitulatif du {{date}}', '{{details}}{{bouton}}'],
            'pro_weekly' => ['Statistiques hebdomadaires (pro)', 'Votre semaine sur {{site}} 📈', '<p>Bonjour {{prenom}},</p><p>Voici l\'activité de votre fiche ces 7 derniers jours :</p>{{details}}{{bouton}}'],
            'account_deleted' => ['Suppression de compte', 'Votre compte a été supprimé', '<p>Bonjour {{prenom}},</p><p>Votre compte et votre fiche ont bien été supprimés. Merci d\'avoir fait partie de {{site}}.</p>'],
        ];
    }

    public static function template(string $key): array
    {
        $d = self::defaults()[$key] ?? [$key, $key, '{{details}}'];
        $custom = Store::doc('email_templates')->get($key, []);
        return [
            'label' => $d[0],
            'subject' => (string) (($custom['subject'] ?? '') ?: $d[1]),
            'body' => (string) (($custom['body'] ?? '') ?: $d[2]),
            'custom' => !empty($custom),
        ];
    }

    /**
     * Construit un email à partir d'un modèle.
     * $vars : variables texte (échappées) ; $raw : fragments HTML déjà sûrs (details, bouton).
     * @return array{subject:string, html:string}
     */
    public static function build(string $key, array $vars = [], array $raw = []): array
    {
        $tpl = self::template($key);
        $vars += ['site' => Settings::siteName(), 'date' => date_fr(date('c'))];
        if (isset($raw['bouton_url'])) {
            $raw['bouton'] = self::button((string) $raw['bouton_url'], (string) ($raw['bouton_label'] ?? 'Ouvrir'));
        }
        $replace = static function (string $s, bool $html) use ($vars, $raw): string {
            return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static function ($m) use ($vars, $raw, $html) {
                $k = $m[1];
                if ($html && isset($raw[$k])) {
                    return (string) $raw[$k];
                }
                if (isset($vars[$k])) {
                    return $html ? e($vars[$k]) : (string) $vars[$k];
                }
                return '';
            }, $s);
        };
        $subject = trim($replace($tpl['subject'], false));
        $body = $replace($tpl['body'], true);
        $html = View::partial('emails/layout', ['subject' => $subject, 'body' => $body, 'footer' => $raw['footer'] ?? '']);
        return ['subject' => $subject, 'html' => $html];
    }

    public static function button(string $url, string $label): string
    {
        return '<p style="margin:26px 0"><a href="' . e(Url::abs($url)) . '" style="display:inline-block;background:#ff4f3a;color:#fff6e8;border:2px solid #1c1233;border-radius:14px;padding:13px 22px;font-weight:800;font-size:16px;text-decoration:none;box-shadow:4px 4px 0 #1c1233">' . e($label) . ' →</a></p>';
    }

    /** Tableau clé/valeur HTML pour les emails. */
    public static function details(array $rows): string
    {
        $h = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border:2px solid #1c1233;border-radius:14px;border-collapse:separate;overflow:hidden;margin:16px 0;background:#ffffff">';
        foreach ($rows as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $h .= '<tr><td style="padding:10px 14px;border-bottom:1px dashed #e4d9c6;font:600 12px/1.4 monospace;text-transform:uppercase;color:#6b5f80;width:34%;vertical-align:top">' . e($label) . '</td><td style="padding:10px 14px;border-bottom:1px dashed #e4d9c6;font-size:15px;line-height:1.5;color:#1c1233">' . nl2br(e($value)) . '</td></tr>';
        }
        return $h . '</table>';
    }

    /** Envoie immédiatement (et met en file en cas d'échec). */
    public static function send(string $to, string $key, array $vars = [], array $raw = [], array $opts = []): bool
    {
        $m = self::build($key, $vars, $raw);
        $res = Mailer::send(['to' => $to, 'subject' => $m['subject'], 'html' => $m['html'], 'reply_to' => $opts['reply_to'] ?? null, 'reply_to_name' => $opts['reply_to_name'] ?? '']);
        if (!$res['ok']) {
            self::queue($to, $m['subject'], $m['html'], ['reply_to' => $opts['reply_to'] ?? null, 'priority' => 2]);
        }
        return $res['ok'];
    }

    /** Met un email en file (envoi par le cron, à débit limité). */
    public static function queue(string $to, string $subject, string $html, array $opts = []): int
    {
        $rec = Store::mailQueue()->insert([
            'status' => 'queued',
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
            'reply_to' => $opts['reply_to'] ?? null,
            'campaign_id' => (int) ($opts['campaign_id'] ?? 0),
            'recipient_ref' => $opts['ref'] ?? null,
            'unsubscribe' => $opts['unsubscribe'] ?? null,
            'priority' => (int) ($opts['priority'] ?? 5),
            'send_after' => $opts['send_after'] ?? date('c'),
            'attempts' => 0,
        ]);
        return (int) $rec['id'];
    }

    /** Traite la file (cron). */
    public static function processQueue(int $max = 0): array
    {
        $max = $max > 0 ? $max : max(5, (int) Settings::get('mailing.rate_per_minute', 60) * 5);
        $col = Store::mailQueue();
        $ids = $col->ids('status', 'queued');
        sort($ids);
        $now = date('c');
        $sent = $failed = 0;
        $todo = [];
        foreach ($ids as $id) {
            $light = $col->light($id);
            if ($light && $light['after'] <= $now) {
                $todo[] = $light;
            }
        }
        usort($todo, static fn ($a, $b) => [$a['prio'], $a['id']] <=> [$b['prio'], $b['id']]);
        $todo = array_slice($todo, 0, $max);
        if (!$todo) {
            return ['sent' => 0, 'failed' => 0];
        }
        $perMinute = max(1, (int) Settings::get('mailing.rate_per_minute', 60));
        $pause = (int) floor(60_000_000 / $perMinute);
        Mailer::batch(static function () use ($todo, $col, &$sent, &$failed, $pause): void {
            foreach ($todo as $i => $light) {
                $m = $col->get((int) $light['id']);
                if (!$m || $m['status'] !== 'queued') {
                    continue;
                }
                $res = Mailer::send(['to' => $m['to'], 'subject' => $m['subject'], 'html' => $m['html'], 'reply_to' => $m['reply_to'] ?? null, 'unsubscribe' => $m['unsubscribe'] ?? null]);
                $attempts = (int) ($m['attempts'] ?? 0) + 1;
                if ($res['ok']) {
                    $sent++;
                    $col->update((int) $m['id'], ['status' => 'sent', 'sent_at' => date('c'), 'attempts' => $attempts, 'html' => '']);
                    if (!empty($m['campaign_id'])) {
                        Mailing::countSent((int) $m['campaign_id']);
                    }
                } else {
                    $failed++;
                    $final = $attempts >= 4;
                    $col->update((int) $m['id'], ['status' => $final ? 'failed' : 'queued', 'attempts' => $attempts, 'error' => $res['error'], 'send_after' => date('c', time() + 300 * $attempts)]);
                    if ($final && !empty($m['campaign_id'])) {
                        Mailing::countFailed((int) $m['campaign_id']);
                    }
                }
                if ($i < count($todo) - 1 && $pause > 0 && PHP_SAPI === 'cli') {
                    usleep(min($pause, 2_000_000));
                }
            }
        });
        if ($failed > 5) {
            Logger::log('mail', 'Échecs d\'envoi en série', ['failed' => $failed], 'error');
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    /** Lien de désinscription signé (RGPD, en-tête List-Unsubscribe). */
    public static function unsubscribeUrl(string $type, int|string $id): string
    {
        return Url::abs('/e/u/' . Crypto::sign(['t' => $type, 'i' => $id], 'unsub'));
    }

    public static function adminEmails(): array
    {
        $list = array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) Settings::get('notifications.emails', '') . ',' . (string) Env::get('ADMIN_ALERT_EMAILS', ''))));
        if (!$list) {
            foreach (Store::admins()->iterate() as $a) {
                if ($a['status'] === 'active' && in_array($a['role'], ['superadmin', 'admin'], true)) {
                    $list[] = $a['email'];
                }
            }
        }
        if (!$list && Env::get('CONTACT_EMAIL')) {
            $list[] = (string) Env::get('CONTACT_EMAIL');
        }
        return array_values(array_unique(array_filter($list, static fn ($e) => \App\Core\Str::emailValid($e))));
    }
}
