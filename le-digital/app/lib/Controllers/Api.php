<?php
declare(strict_types=1);

namespace LD\Controllers;

use LD\Ai;
use LD\AiException;
use LD\Booking;
use LD\Content;
use LD\Guard;
use LD\Http;
use LD\Leads;
use LD\Mailer;
use LD\RateLimit;
use LD\Settings;
use LD\Sms;
use LD\Store;

/**
 * API publique (JSON) : formulaires, rendez-vous, vocaux et fonctionnalités IA du site.
 */
final class Api
{
    // ------------------------------------------------------------------ outils

    private static function email(string $key): string
    {
        $v = Http::str($key, 160);
        return $v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL) ? mb_strtolower($v) : '';
    }

    private static function phone(string $key): string
    {
        $v = Http::str($key, 30);
        $digits = preg_replace('/\D/', '', $v) ?? '';
        return strlen($digits) >= 8 ? $v : '';
    }

    /** Données du simulateur envoyées par le navigateur (bornées). */
    private static function cleanSim(mixed $sim): ?array
    {
        if (!is_array($sim)) {
            return null;
        }
        $top = [];
        foreach (array_slice((array) ($sim['top'] ?? []), 0, 5) as $t) {
            if (is_array($t)) {
                $top[] = [clean_text((string) ($t[0] ?? ''), 60), (float) ($t[1] ?? 0)];
            }
        }
        return ['saved' => round((float) ($sim['saved'] ?? 0), 1), 'top' => $top];
    }

    private static function cleanBrief(mixed $b): ?array
    {
        if (!is_array($b)) {
            return null;
        }
        $out = [];
        foreach (['besoin', 'taches', 'urgence', 'formule'] as $k) {
            $out[$k] = clean_text((string) ($b[$k] ?? ''), 400);
        }
        return array_filter($out) ? $out : null;
    }

    /** Historique de conversation envoyé par l'assistant (16 derniers messages, 1 500 caractères chacun). */
    /** Messages de la discussion envoyés par le navigateur ($max derniers ; 16 suffisent comme contexte pour l'IA). */
    private static function conversation(int $max = 16): array
    {
        $msgs = Http::input('messages', []);
        $out = [];
        foreach (array_slice(is_array($msgs) ? $msgs : [], -$max) as $m) {
            if (!is_array($m)) {
                continue;
            }
            $content = clean_text((string) ($m['content'] ?? ''), 1500);
            if ($content !== '') {
                $out[] = ['role' => ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user', 'content' => $content];
            }
        }
        return $out;
    }

    private static function visitorMail(string $to, string $subject, string $title, string $html, array $attachments = []): void
    {
        if ($to !== '' && Settings::get('mail.confirm_visitor', true)) {
            $reply = (string) (Settings::get('contact.email') ?: Mailer::notifyAddress());
            Mailer::send($to, $subject, Mailer::layout($title, $html), $reply ?: null, $attachments);
        }
    }

    // ------------------------------------------------------------------ formulaires

    public static function contact(): never
    {
        Guard::api();
        RateLimit::enforce('form', 6, 600);
        $name = Http::str('name', 80);
        $email = self::email('email');
        $phone = self::phone('phone');
        if ($name === '') {
            Http::fail('Dis-moi au moins ton prénom 🙂', 422, ['field' => 'name']);
        }
        if ($email === '' && $phone === '') {
            Http::fail('Laisse-moi un email ou un téléphone pour que je puisse te répondre.', 422, ['field' => Http::str('email') !== '' ? 'email' : 'phone']);
        }
        $allowed = Content::get('rdv')['form']['interests'] ?? [];
        $interests = array_values(array_intersect($allowed, array_map('strval', (array) Http::input('interests', []))));
        $lead = Leads::add('contact', [
            'name'  => $name, 'email' => $email, 'phone' => $phone,
            'data'  => array_filter([
                'interests' => $interests,
                'time_text' => Http::str('time_text', 2000),
                'why'       => Http::str('why', 3000),
                'sim'       => self::cleanSim(Http::input('sim')),
                'brief'     => self::cleanBrief(Http::input('brief')),
            ], static fn($v) => $v !== null && $v !== '' && $v !== []),
        ]);
        Leads::notify($lead);
        self::visitorMail($email, 'Bien reçu ' . $name . ' ☕', 'Merci ' . $name . ' !', '<p>J’ai bien reçu ton message. Je reviens vers toi très vite, personnellement, sous 24 h ouvrées.</p><p>À tout de suite autour d’un café ☕<br>Fred</p>');
        Http::sendJson(['ok' => true]);
    }

    public static function callback(): never
    {
        Guard::api();
        RateLimit::enforce('form', 6, 600);
        $phone = self::phone('phone');
        if ($phone === '') {
            Http::fail('Ton numéro semble incomplet.', 422, ['field' => 'phone']);
        }
        $labels = Content::get('rdv')['callback']['moments_label'] ?? ['ce matin', 'ce midi', 'en fin de journée'];
        $i = max(0, min(count($labels) - 1, (int) Http::input('moment', 0)));
        $data = ['moment' => $labels[$i]];
        if (Http::str('source', 20) === 'exit') {
            $data = ['moment' => 'Dès que possible', 'origine' => 'Pop-up de sortie'];
        }
        $lead = Leads::add('rappel', ['phone' => $phone, 'data' => $data]);
        Leads::notify($lead);
        Http::sendJson(['ok' => true, 'moment' => $data['moment']]);
    }

    // ------------------------------------------------------------------ rendez-vous

    public static function slots(): never
    {
        Http::sendJson(['ok' => true, 'days' => Settings::get('booking.enabled') ? Booking::days() : []]);
    }

    public static function book(): never
    {
        Guard::api();
        RateLimit::enforce('booking', 5, 3600);
        if (!Settings::get('booking.enabled')) {
            Http::fail('La réservation en ligne est fermée pour le moment.', 403);
        }
        $date = Http::str('date', 10);
        $time = Http::str('time', 5);
        $modes = array_values(array_filter((array) Settings::get('booking.modes', [])));
        $mode = $modes[max(0, min(count($modes) - 1, (int) Http::input('mode', 0)))] ?? 'En visio';
        $name = Http::str('name', 80);
        $email = self::email('email');
        $phone = self::phone('phone');
        $sms = Sms::enabled() && (bool) Http::input('sms', false);
        if ($name === '') {
            Http::fail('Indique ton prénom pour que Fred sache qui il retrouve 🙂', 422, ['field' => 'name']);
        }
        if ($email === '') {
            Http::fail('Il me faut ton email pour t’envoyer la confirmation.', 422, ['field' => 'email']);
        }
        if ($sms && $phone === '') {
            Http::fail('Pour le rappel SMS, indique ton numéro de téléphone.', 422, ['field' => 'phone']);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time) || !Booking::isAvailable($date, $time)) {
            Http::fail('Oups, ce créneau n’est plus disponible. Choisis-en un autre 🙂', 409, ['days' => Booking::days()]);
        }
        try {
            $b = Booking::book(['date' => $date, 'time' => $time, 'mode' => $mode, 'name' => $name, 'email' => $email, 'phone' => $phone, 'sms' => $sms && $phone !== '']);
        } catch (\RuntimeException $e) {
            Http::fail($e->getMessage(), 409, ['days' => Booking::days()]);
        }
        $label = Booking::label($b);
        $lead = Leads::add('rdv', ['name' => $name, 'email' => $email, 'phone' => $phone, 'data' => [
            'date_label' => $label, 'mode' => $mode, 'sms' => Sms::enabled() ? $b['sms'] : null, 'booking_id' => $b['id'], 'date' => $date, 'time' => $time,
            'sim' => self::cleanSim(Http::input('sim')), 'brief' => self::cleanBrief(Http::input('brief')),
        ]]);
        Leads::notify($lead, [['name' => 'rdv.ics', 'type' => 'text/calendar; method=PUBLISH', 'content' => Booking::ics([$b], true)]]);
        $visio = (string) Settings::get('booking.visio_link', '');
        $address = (string) Settings::get('booking.address', '');
        $extra = str_contains(mb_strtolower($mode), 'visio')
            ? ($visio !== '' ? '<p>Lien visio : <a href="' . e($visio) . '">' . e($visio) . '</a></p>' : '<p>Je t’envoie le lien visio juste avant notre échange.</p>')
            : ($address !== '' ? '<p>Adresse : ' . e($address) . '</p>' : '<p>Je te confirme le lieu de rendez-vous très vite.</p>');
        self::visitorMail($email, 'C’est calé ! ' . $label, 'C’est calé ' . $name . ' ! ☕',
            '<p>On se retrouve <b>' . e($label) . '</b> pour un premier échange gratuit (' . (int) $b['duration'] . ' min).</p>' . $extra
            . ($b['sms'] ? '<p>Tu recevras un rappel par SMS la veille.</p>' : '')
            . '<p>Un empêchement ? <a href="' . e(abs_url('/rdv/annuler/' . $b['id'] . '/' . $b['token'])) . '">Annule ou décale ton créneau</a>.</p><p>À très vite,<br>Fred</p>',
            [['name' => 'rdv-le-digital.ics', 'type' => 'text/calendar; method=PUBLISH', 'content' => Booking::ics([$b])]]);
        Http::sendJson(['ok' => true, 'label' => $label, 'sms' => $b['sms'], 'smsActive' => Sms::enabled(), 'ics' => Booking::ics([$b])]);
    }

    // ------------------------------------------------------------------ vocaux

    public static function voice(): never
    {
        Guard::api();
        RateLimit::enforce('voice', 4, 1800);
        $f = $_FILES['audio'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            Http::fail('Le vocal n’a pas pu être envoyé. Réessaie ?', 422);
        }
        if ($f['size'] > 10 * 1024 * 1024) {
            Http::fail('Ton vocal est trop long.', 413);
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $exts = ['audio/webm' => 'webm', 'video/webm' => 'webm', 'audio/ogg' => 'ogg', 'application/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'video/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/aac' => 'aac'];
        if (!isset($exts[$mime])) {
            Http::fail('Format audio non reconnu.', 415);
        }
        $name = Http::str('name', 80);
        $phone = self::phone('phone');
        if ($phone === '') {
            Http::fail('Laisse ton téléphone pour que Fred puisse te rappeler.', 422, ['field' => 'phone']);
        }
        $file = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $exts[$mime];
        $dest = Store::path('data/voices/' . $file);
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            Http::fail('Enregistrement impossible, réessaie.', 500);
        }
        $transcript = Http::str('transcript', 6000);
        $summary = Http::str('summary', 1200);
        // Durée « m:ss » envoyée par le navigateur (pour le coût de la transcription).
        $secs = preg_match('/^(\d+):(\d{2})$/', Http::str('duration', 10), $dm) ? (int) $dm[1] * 60 + (int) $dm[2] : 0;
        if ($transcript === '' && ($t = Ai::transcribe($dest, str_replace('video/', 'audio/', $mime), (float) $secs))) {
            $transcript = $t;
        }
        if ($summary === '' && $transcript !== '' && str_word_count($transcript) >= 6 && Ai::enabled('voice')) {
            try {
                $summary = Ai::complete('voice', '', [['role' => 'user', 'content' => Ai::fill(Ai::prompt('voice_summary'), ['transcript' => $transcript])]]);
            } catch (AiException) {
            }
        }
        $lead = Leads::add('vocal', ['name' => $name, 'phone' => $phone, 'data' => [
            'duration' => Http::str('duration', 10), 'transcript' => $transcript, 'summary' => $summary, 'audio' => $file, 'mime' => $mime,
        ]]);
        $att = filesize($dest) < 6 * 1024 * 1024 ? [['name' => 'vocal-' . ($name ? slugify($name) : 'prospect') . '.' . $exts[$mime], 'type' => $mime, 'content' => (string) file_get_contents($dest)]] : [];
        Leads::notify($lead, $att);
        Http::sendJson(['ok' => true]);
    }

    // ------------------------------------------------------------------ cookies

    /** Choix de cookies du visiteur, journalisé comme preuve du consentement (aucune donnée identifiante). */
    public static function consent(): never
    {
        Guard::api();
        RateLimit::enforce('consent', 20, 600);
        $id = Http::str('id', 40);
        $sent = (array) Http::input('choices', []);
        $choices = [];
        foreach (\LD\Consent::categories() as $cat) {
            $choices[$cat] = !empty($sent[$cat]);
        }
        \LD\Consent::log(preg_match('/^[a-z0-9-]{8,40}$/i', $id) ? $id : '', $choices, Http::str('v', 12), (string) ($_SERVER['HTTP_REFERER'] ?? ''));
        Http::sendJson(['ok' => true]);
    }

    // ------------------------------------------------------------------ IA

    private static function aiGuard(string $feature, int $max = 20): void
    {
        Guard::api();
        RateLimit::enforce('ai', 60, 600);
        RateLimit::enforce('ai-' . $feature, $max, 600);
    }

    /** Identifiant de la discussion, tiré au hasard par le navigateur. */
    private static function cid(): string
    {
        $cid = Http::str('cid', 40);
        return preg_match('/^[a-z0-9-]{12,40}$/i', $cid) ? $cid : '';
    }

    /**
     * Discussion avec l'assistant. Chaque conversation est enregistrée dans Demandes (« Discussion IA ») et Fred
     * reçoit un e-mail dès le premier échange, puis le récapitulatif à la fin.
     */
    public static function chat(): never
    {
        self::aiGuard('chat', 40);
        $msgs = self::conversation();
        if (!$msgs || end($msgs)['role'] !== 'user') {
            Http::fail('Message vide', 422);
        }
        $text = (string) (Content::get('global')['dock']['error_text'] ?? '');
        $ai = false;
        if (Ai::enabled('chat')) {
            try {
                $text = Ai::complete('chat', Ai::fill(Ai::prompt('chat_system'), ['solutions' => \LD\Solutions::summary()]), $msgs);
                $ai = true;
            } catch (AiException) {
            }
        }
        $cid = self::cid();
        $history = [...self::conversation(80), ['role' => 'assistant', 'content' => $text]];
        Http::sendJsonThen(['ok' => true, 'text' => $text, 'ai' => $ai], static function () use ($cid, $history): void {
            if ($cid !== '') {
                Leads::chat($cid, $history);
            }
            Leads::flushChats();
        });
    }

    /** Fenêtre de discussion fermée ou visiteur parti : récapitulatif de la conversation à Fred (s'il y a du nouveau). */
    public static function chatEnd(): never
    {
        Guard::api();
        RateLimit::enforce('ai-end', 30, 600);
        $cid = self::cid();
        Http::sendJsonThen(['ok' => true], static function () use ($cid): void {
            if ($cid !== '' && ($lead = Leads::chatLead($cid))) {
                Leads::notifyChat($lead);
            }
            Leads::flushChats();
        });
    }

    public static function brief(): never
    {
        self::aiGuard('brief');
        $msgs = self::conversation();
        $conv = implode("\n", array_map(static fn($m) => ($m['role'] === 'user' ? 'Visiteur: ' : 'Assistant: ') . $m['content'], $msgs));
        $b = null;
        if (Ai::enabled('brief') && $conv !== '') {
            try {
                $b = Ai::parseJson(Ai::complete('brief', '', [['role' => 'user', 'content' => Ai::fill(Ai::prompt('brief'), ['conversation' => $conv])]]));
            } catch (AiException) {
            }
        }
        $b = self::cleanBrief($b) ?? ['besoin' => 'À préciser au premier café', 'taches' => '—', 'urgence' => 'moyenne', 'formule' => 'À définir ensemble'];
        $cid = self::cid();
        if ($cid === '') {
            $lead = Leads::add('brief', ['data' => $b + ['conversation' => $msgs]]);
            Leads::notify($lead);
            Http::sendJson(['ok' => true, 'brief' => $b]);
        }
        // Le brief complète la discussion IA déjà enregistrée et part tout de suite à Fred.
        $history = self::conversation(80);
        Http::sendJsonThen(['ok' => true, 'brief' => $b], static function () use ($cid, $history, $b): void {
            $lead = Leads::chat($cid, $history, $b);
            Leads::notifyChat($lead, false, 'Brief de l’assistant', 'Le visiteur a demandé son brief à ton assistant IA : voici son besoin et toute la conversation.');
        });
    }

    public static function voiceSummary(): never
    {
        self::aiGuard('voice');
        $t = Http::str('transcript', 6000);
        if ($t === '' || str_word_count($t) < 6 || !Ai::enabled('voice')) {
            Http::sendJson(['ok' => true, 'summary' => '']);
        }
        try {
            $s = Ai::complete('voice', '', [['role' => 'user', 'content' => Ai::fill(Ai::prompt('voice_summary'), ['transcript' => $t])]]);
            Http::sendJson(['ok' => true, 'summary' => $s]);
        } catch (AiException) {
            Http::sendJson(['ok' => true, 'summary' => '']);
        }
    }

    public static function devis(): never
    {
        self::aiGuard('devis');
        $p = Http::str('prompt', 600);
        if ($p === '') {
            $p = (string) (Content::get('global')['demo_devis']['default_prompt'] ?? '');
        }
        $d = null;
        if (Ai::enabled('devis')) {
            try {
                $d = Ai::parseJson(Ai::complete('devis', '', [['role' => 'user', 'content' => Ai::fill(Ai::prompt('devis'), ['prompt' => $p])]]));
            } catch (AiException) {
            }
        }
        $lines = [];
        foreach (array_slice((array) ($d['lignes'] ?? []), 0, 8) as $l) {
            if (!is_array($l)) {
                continue;
            }
            $lines[] = [
                'd'  => clean_text((string) ($l['d'] ?? ''), 90) ?: 'Prestation',
                'q'  => max(0, min(100000, round((float) ($l['q'] ?? 1), 2))),
                'u'  => clean_text((string) ($l['u'] ?? 'u'), 14) ?: 'u',
                'pu' => max(0, min(1000000, round((float) ($l['pu'] ?? 0), 2))),
            ];
        }
        $ai = $lines !== [];
        if (!$ai) {
            $d = ['client' => 'Client', 'objet' => mb_substr($p, 0, 60), 'tva' => 20, 'conditions' => 'Devis valable 30 jours. Acompte de 30 % à la commande.'];
            $lines = [['d' => 'Prestation principale', 'q' => 1, 'u' => 'forfait', 'pu' => 800], ['d' => 'Fournitures', 'q' => 1, 'u' => 'forfait', 'pu' => 350], ['d' => "Main-d'œuvre", 'q' => 8, 'u' => 'h', 'pu' => 45]];
        }
        $tva = (float) ($d['tva'] ?? 20);
        $devis = [
            'client'     => clean_text((string) ($d['client'] ?? 'Client'), 80) ?: 'Client',
            'objet'      => clean_text((string) ($d['objet'] ?? ''), 120),
            'lignes'     => $lines,
            'tva'        => in_array($tva, [0.0, 2.1, 5.5, 10.0, 20.0], true) ? $tva : 20,
            'conditions' => clean_text((string) ($d['conditions'] ?? ''), 300),
        ];
        $example = Http::str('prompt', 600) === '';
        Http::sendJsonThen(['ok' => true, 'ai' => $ai, 'devis' => $devis], static function () use ($p, $devis, $example): void {
            $ht = array_sum(array_map(static fn($l) => $l['q'] * $l['pu'], $devis['lignes']));
            $total = number_format($ht, 2, ',', ' ') . ' € HT';
            Leads::interaction('demo', [
                'demande' => $p . ($example ? ' (exemple proposé par le site)' : ''),
                'devis'   => trim($devis['objet'] . ' · ' . count($devis['lignes']) . ' ligne(s) · ' . $total, ' ·'),
            ], mb_strimwidth($p, 0, 90, '…') . ' → ' . $total);
        });
    }

    /** Texte lisible d'une page web (titre, description, titres, paragraphes). */
    private static function pageExcerpt(string $html): string
    {
        $parts = [];
        if (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)) {
            $parts[] = 'Titre : ' . trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        }
        if (preg_match('~<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)~i', $html, $m)) {
            $parts[] = 'Description : ' . trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        $body = preg_replace('~<(script|style|noscript|svg|nav|footer)[^>]*>.*?</\1>~is', ' ', $html) ?? '';
        if (preg_match_all('~<h[1-3][^>]*>(.*?)</h[1-3]>~is', $body, $hs)) {
            $titles = array_filter(array_map(static fn($h) => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($h), ENT_QUOTES, 'UTF-8')) ?? ''), $hs[1]));
            if ($titles) {
                $parts[] = 'Titres : ' . implode(' | ', array_slice(array_unique($titles), 0, 15));
            }
        }
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8')) ?? '');
        $parts[] = 'Texte : ' . mb_substr($text, 0, 1800);
        return mb_substr(implode("\n", $parts), 0, 3000);
    }

    public static function analyse(): never
    {
        self::aiGuard('analyse', 10);
        $url = Http::str('url', 200);
        $job = Http::str('job', 200);
        $pain = Http::str('pain', 300);
        $r = null;
        if (Ai::enabled('analyse')) {
            $site = "Tu ne peux pas visiter le site : base-toi sur l'activité et le nom de domaine.";
            if ($url !== '' && ($html = Http::fetchPublicPage($url))) {
                $site = "Voici ce qu'on lit sur la page d'accueil du site (extrait automatique) :\n" . self::pageExcerpt($html) . "\nAppuie au moins un constat sur ce contenu.";
            }
            try {
                $r = Ai::parseJson(Ai::complete('analyse', Ai::prompt('analyse_system'), [['role' => 'user', 'content' => Ai::fill(Ai::prompt('analyse'), [
                    'url' => $url ?: 'pas de site', 'job' => $job ?: 'non précisée', 'pain' => $pain ?: 'non précisée', 'site' => $site,
                ])]]));
            } catch (AiException) {
            }
        }
        $constats = array_values(array_filter(array_map(static fn($c) => clean_text(is_string($c) ? $c : '', 300), (array) ($r['constats'] ?? []))));
        $idees = [];
        foreach ((array) ($r['idees'] ?? []) as $i) {
            if (is_array($i) && !empty($i['t'])) {
                $idees[] = ['t' => clean_text((string) $i['t'], 80), 'd' => clean_text((string) ($i['d'] ?? ''), 300)];
            }
        }
        if (count($constats) < 3 || count($idees) < 3) {
            $constats = ['Tes tâches répétitives méritent un vrai outil.', 'Tes infos sont sans doute éparpillées.', 'Ton site peut travailler davantage pour toi.'];
            $idees = [['t' => 'Devis assistés', 'd' => "L'IA prépare, tu relis et tu envoies."], ['t' => 'Relances automatiques', 'd' => 'Elles partent au bon moment, sans y penser.'], ['t' => 'Assistant sur ton site', 'd' => 'Il répond aux questions fréquentes 24 h/24.']];
        }
        $constats = array_slice($constats, 0, 3);
        $idees = array_slice($idees, 0, 3);
        Http::sendJsonThen(['ok' => true, 'constats' => $constats, 'idees' => $idees], static function () use ($url, $job, $pain, $constats, $idees): void {
            Leads::interaction('analyse', [
                'site' => $url, 'activite' => $job, 'difficulte' => $pain, 'constats' => $constats,
                'idees' => array_map(static fn($i) => $i['t'] . ($i['d'] !== '' ? ' : ' . $i['d'] : ''), $idees),
            ], implode(' · ', array_filter([$url, $job, $pain])) ?: 'sans précision');
        });
    }

    public static function explain(): never
    {
        self::aiGuard('explain');
        $id = Http::str('id', 40);
        $svc = null;
        foreach (Content::get('global')['services'] ?? [] as $s) {
            if ($s['id'] === $id) {
                $svc = $s;
            }
        }
        $fallback = (string) (Content::get('sur-mesure')['blocks']['explain_fallback'] ?? '');
        if (!$svc || !Ai::enabled('explain')) {
            Http::sendJson(['ok' => true, 'text' => $fallback]);
        }
        // Même explication pour tout le monde : on la garde en cache tant que le texte ne change pas.
        $cache = 'cache/ai/explain-' . md5($svc['title'] . '|' . $svc['text'] . '|' . Ai::prompt('explain')) . '.json';
        $hit = Store::read($cache, []);
        if (!empty($hit['text']) && ($hit['at'] ?? 0) > time() - 30 * 86400) {
            Http::sendJson(['ok' => true, 'text' => $hit['text']]);
        }
        try {
            $text = Ai::complete('explain', '', [['role' => 'user', 'content' => Ai::fill(Ai::prompt('explain'), ['title' => $svc['title'], 'text' => $svc['text']])]]);
            Store::write($cache, ['text' => $text, 'at' => time()]);
            Http::sendJson(['ok' => true, 'text' => $text]);
        } catch (AiException) {
            Http::sendJson(['ok' => true, 'text' => $fallback]);
        }
    }
}
