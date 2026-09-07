<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Ai\Assistant;
use App\Ai\KnowledgeBase;
use App\Content\Leads;
use App\Content\Reviews;
use App\Content\Settings;
use App\Core\Config;
use App\Core\Logger;
use App\Http\Request;
use App\Http\Response;
use App\I18n\Translator;
use App\Mail\Mailer;
use App\Security\Csrf;
use App\Security\RateLimiter;
use App\Security\Sanitizer;

/**
 * Points d'entrée JSON consommés par le front.
 */
final class ApiController
{
    /* ------------------------------------------------------------------ */
    /* Formulaire de contact                                               */
    /* ------------------------------------------------------------------ */
    public static function contact(Request $request): never
    {
        self::guard($request);
        Translator::boot($request->str('lang', 'fr'));

        $limit = Config::arr('forms.contact_rate_limit', ['hits' => 5, 'window' => 3600]);
        if (!RateLimiter::hit('contact', $request->ip(), (int) $limit['hits'], (int) $limit['window'])) {
            Response::json(['ok' => false, 'error' => 'rate_limited', 'message' => Translator::t('form.rate_limited')], 429);
        }

        // Pot de miel + délai minimum : filtre l'essentiel des robots.
        if ($request->str('website') !== '') {
            Logger::info('Formulaire rejeté (pot de miel)', ['ip' => $request->ip()]);
            Response::json(['ok' => true, 'message' => Translator::pick(Settings::get('forms.success_message'))]);
        }
        $elapsed = time() - $request->int('started_at', 0);
        if ($request->int('started_at', 0) > 0 && $elapsed < Config::int('forms.min_fill_seconds', 3)) {
            Response::json(['ok' => false, 'error' => 'too_fast'], 422);
        }

        $name    = Sanitizer::text($request->str('name'), 120);
        $email   = mb_strtolower(Sanitizer::text($request->str('email'), 180));
        $phone   = Sanitizer::text($request->str('phone'), 40);
        $company = Sanitizer::text($request->str('company'), 140);
        $subject = Sanitizer::text($request->str('subject'), 60);
        $message = Sanitizer::text($request->str('message'), 4000);

        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors['name'] = Translator::t('form.error_name');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = Translator::t('form.error_email');
        }
        if (mb_strlen($message) < 10) {
            $errors['message'] = Translator::t('form.error_message');
        }
        if (!$request->bool('consent')) {
            $errors['consent'] = Translator::t('form.error_consent');
        }
        if ($errors !== []) {
            Response::json(['ok' => false, 'error' => 'validation', 'errors' => $errors], 422);
        }

        $id = Leads::add([
            'source'  => Leads::SOURCE_CONTACT,
            'name'    => $name,
            'email'   => $email,
            'phone'   => $phone,
            'company' => $company,
            'subject' => $subject,
            'message' => $message,
            'lang'    => Translator::lang(),
            'page'    => Sanitizer::text($request->str('page'), 200),
            'ip'      => $request->ip(),
        ]);

        self::notify($name, $email, $phone, $company, $subject, $message, $id);

        Response::json([
            'ok'      => true,
            'id'      => $id,
            'message' => Translator::pick(Settings::get('forms.success_message')),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Capture rapide (pop-up de sortie, bandeaux CTA)                     */
    /* ------------------------------------------------------------------ */
    public static function lead(Request $request): never
    {
        self::guard($request);
        Translator::boot($request->str('lang', 'fr'));

        $limit = Config::arr('forms.lead_rate_limit', ['hits' => 8, 'window' => 3600]);
        if (!RateLimiter::hit('lead', $request->ip(), (int) $limit['hits'], (int) $limit['window'])) {
            Response::json(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        if ($request->str('website') !== '') {
            Response::json(['ok' => true]);
        }

        $email = mb_strtolower(Sanitizer::text($request->str('email'), 180));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['ok' => false, 'error' => 'invalid_email', 'message' => Translator::t('form.error_email')], 422);
        }

        $id = Leads::add([
            'source'  => Sanitizer::text($request->str('source', Leads::SOURCE_EXIT), 40),
            'name'    => Sanitizer::text($request->str('name'), 120),
            'email'   => $email,
            'phone'   => Sanitizer::text($request->str('phone'), 40),
            'message' => Sanitizer::text($request->str('message'), 1000),
            'subject' => 'accompagnement',
            'lang'    => Translator::lang(),
            'page'    => Sanitizer::text($request->str('page'), 200),
            'ip'      => $request->ip(),
        ]);

        self::notify('', $email, '', '', 'Demande express', $request->str('message'), $id);

        Response::json(['ok' => true, 'id' => $id, 'message' => Translator::t('form.lead_thanks')]);
    }

    /* ------------------------------------------------------------------ */
    /* Assistant IA                                                        */
    /* ------------------------------------------------------------------ */
    public static function chat(Request $request): never
    {
        self::guard($request);
        Translator::boot($request->str('lang', 'fr'));

        if (!Settings::bool('chatbot.enabled', true)) {
            Response::json(['ok' => false, 'error' => 'disabled'], 403);
        }

        $limit = Config::arr('ai.rate_limit', ['hits' => 20, 'window' => 600]);
        if (!RateLimiter::hit('chat', $request->ip(), (int) $limit['hits'], (int) $limit['window'])) {
            Response::json([
                'ok'    => false,
                'error' => 'rate_limited',
                'reply' => Translator::t('chat.rate_limited'),
            ], 429);
        }

        $question = Sanitizer::text($request->str('message'), 1200);
        if ($question === '') {
            Response::json(['ok' => false, 'error' => 'empty'], 422);
        }

        $history = [];
        foreach ($request->arr('history') as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $history[] = [
                'role'    => ($entry['role'] ?? '') === 'assistant' ? 'assistant' : 'user',
                'content' => Sanitizer::text((string) ($entry['content'] ?? ''), 1500),
            ];
        }

        $result = Assistant::ask($question, $history, Translator::lang());

        if (!($result['ok'] ?? false)) {
            Response::json([
                'ok'    => false,
                'error' => $result['error'] ?? 'unavailable',
                'reply' => Translator::t('chat.unavailable'),
            ], 200);
        }

        Response::json([
            'ok'      => true,
            'reply'   => $result['reply'],
            'sources' => $result['sources'] ?? [],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Avis Google                                                         */
    /* ------------------------------------------------------------------ */
    public static function reviews(Request $request): never
    {
        $limit = max(1, min(20, $request->int('limit', 6)));
        Response::json(['ok' => true, 'data' => Reviews::get($limit)]);
    }

    /* ------------------------------------------------------------------ */
    /* Recherche interne (utilisée par l'assistant et la barre de recherche)*/
    /* ------------------------------------------------------------------ */
    public static function search(Request $request): never
    {
        $query = Sanitizer::text($request->str('q'), 200);
        if (mb_strlen($query) < 2) {
            Response::json(['ok' => true, 'results' => []]);
        }
        $results = [];
        foreach (KnowledgeBase::search($query, 6) as $chunk) {
            $results[] = [
                'title'   => $chunk['title'],
                'url'     => $chunk['url'],
                'extract' => mb_substr($chunk['text'], 0, 220),
                'source'  => $chunk['source'],
            ];
        }
        Response::json(['ok' => true, 'results' => $results]);
    }

    public static function health(Request $request): never
    {
        Response::json([
            'ok'      => true,
            'time'    => date('c'),
            'storage' => is_writable(DATA_PATH),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** Contrôles communs : même origine + jeton CSRF. */
    private static function guard(Request $request): void
    {
        if (!$request->isSameOrigin()) {
            Response::json(['ok' => false, 'error' => 'bad_origin'], 403);
        }
        $token = $request->str('_token');
        if (!Csrf::check($token, 'public')) {
            Response::json(['ok' => false, 'error' => 'csrf', 'message' => 'Session expirée, merci de recharger la page.'], 419);
        }
    }

    private static function notify(
        string $name,
        string $email,
        string $phone,
        string $company,
        string $subject,
        string $message,
        string $id
    ): void {
        $to = Settings::str('forms.notify_email') ?: Settings::str('site.email');
        if ($to === '') {
            return;
        }
        $rows = [
            'Nom'        => $name,
            'E-mail'     => $email,
            'Téléphone'  => $phone,
            'Entreprise' => $company,
            'Sujet'      => $subject,
            'Référence'  => $id,
        ];
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#0B1B33">'
            . '<h2 style="margin:0 0 16px">Nouvelle demande depuis le site</h2><table cellpadding="6">';
        foreach ($rows as $label => $value) {
            if (trim($value) !== '') {
                $html .= '<tr><td style="color:#5C6B80">' . htmlspecialchars($label, ENT_QUOTES) . '</td>'
                    . '<td><strong>' . htmlspecialchars($value, ENT_QUOTES) . '</strong></td></tr>';
            }
        }
        $html .= '</table><h3 style="margin:20px 0 8px">Message</h3><p style="white-space:pre-line">'
            . nl2br(htmlspecialchars($message, ENT_QUOTES)) . '</p></div>';

        Mailer::send($to, 'Nouvelle demande — ' . ($subject !== '' ? $subject : 'site web'), $html, $email !== '' ? $email : null);
    }
}
