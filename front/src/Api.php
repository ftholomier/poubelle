<?php

declare(strict_types=1);

/**
 * API JSON publique du site.
 *
 *  POST /api/devis       demande de devis (formulaire en 3 étapes)
 *  POST /api/contact     formulaire de contact
 *  POST /api/rappel      demande de rappel (popup de sortie)
 *  POST /api/bot         assistant du site
 *  POST /api/vocal       message vocal (bouton rouge de la barre CTA)
 *  POST /api/vocal/resume résumé IA (Gemini) d'un vocal avant envoi
 *  GET  /api/token       jeton de formulaire
 *  GET  /api/site        coordonnées publiques
 *  GET  /api/produits    produits
 *  GET  /api/actus       actualités (site + Facebook, mélangées par date)
 *  GET  /api/actus/{id}  une actualité
 *  GET  /api/realisations, /api/avis
 *  GET  /api/cron?key=…  tâches planifiées (synchronisation Facebook, vérification des mises à jour)
 */
final class Api
{
    public static function handle(string $path, string $method): void
    {
        security_headers();
        $route = substr($path, 4); // après « /api »
        if ($method === 'POST') {
            match ($route) {
                '/devis' => self::devis(),
                '/contact' => self::contact(),
                '/rappel' => self::rappel(),
                '/bot' => self::bot(),
                '/vocal' => self::vocal(),
                '/vocal/resume' => self::vocalSummary(),
                default => Http::error('Adresse inconnue', 404),
            };
            return;
        }
        if ($method !== 'GET') {
            Http::error('Méthode non autorisée', 405);
        }
        header('Access-Control-Allow-Origin: *');
        switch (true) {
            case $route === '/token':
                Http::json(['ok' => true, 'token' => Http::formToken()]);
            case $route === '/site':
                $s = Content::site();
                Http::json(['ok' => true, 'site' => array_intersect_key($s, array_flip(['name', 'legal_name', 'tagline', 'phone', 'fax', 'sav_phone', 'email', 'hours', 'facebook', 'instagram', 'agencies', 'zones']))]);
            case $route === '/produits':
                Http::json(['ok' => true, 'produits' => array_map(fn($p) => self::pubProduct($p), Content::products())]);
            case $route === '/actus':
                $cat = (string) ($_GET['cat'] ?? '');
                $list = array_values(array_filter(Content::posts(), fn($p) => $cat === '' || ($p['cat'] ?? '') === $cat));
                $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));
                Http::json(['ok' => true, 'total' => count($list), 'actus' => array_map(fn($p) => self::pubPost($p, false), array_slice($list, 0, $limit))]);
            case (bool) preg_match('#^/actus/([\w-]+)$#', $route, $m):
                $p = Content::post($m[1]);
                if (!$p || empty($p['visible'])) {
                    Http::error('Actualité introuvable', 404);
                }
                Http::json(['ok' => true, 'actu' => self::pubPost($p, true)]);
            case $route === '/realisations':
                Http::json(['ok' => true, 'realisations' => array_map(fn($r) => ['image' => Http::abs($r['src']), 'title' => $r['title'] ?? '', 'place' => $r['place'] ?? ''], Content::realisations())]);
            case $route === '/avis':
                Http::json(['ok' => true, 'note' => Content::rating(), 'avis' => array_map(fn($a) => ['name' => $a['name'], 'text' => $a['text'], 'rating' => $a['rating'], 'source' => $a['source'] ?? ''], Content::reviews())]);
            case $route === '/cron':
                self::cron();
        }
        Http::error('Adresse inconnue', 404);
    }

    private static function pubProduct(array $p): array
    {
        return ['id' => $p['id'], 'title' => $p['title'], 'url' => Http::abs($p['path']), 'summary' => $p['summary'], 'image' => Http::abs($p['image'])];
    }

    private static function pubPost(array $p, bool $full): array
    {
        $o = ['id' => $p['id'], 'title' => plain((string) $p['title']), 'date' => $p['date'], 'category' => $p['cat'] ?? '',
            'url' => Http::abs($p['path']), 'excerpt' => Content::postExcerpt($p, 200),
            'images' => array_map(fn($i) => str_starts_with($i, 'http') ? $i : Http::abs($i), $p['images'] ?? [])];
        if ($full) {
            $o['content'] = $p['content'] ?? '';
        }
        return $o;
    }

    /**
     * Barrages anti-robot communs. Robot certain (champ piège rempli, envoi depuis un autre site) :
     * faux « merci », rien n'est enregistré. Puis jeton signé et débit par IP.
     * Le reste (rapidité, contenu…) est noté par Antispam::check() : au-delà du seuil, la demande va dans « Spam ».
     */
    private static function guard(array $in, string $bucket, string $back, int $max = 5): void
    {
        if (Http::str($in, 'site') !== '') {
            Antispam::log($bucket, 'honeypot');
            self::done($back); // on fait semblant
        }
        if (Antispam::enabled() && !Antispam::originOk()) {
            Antispam::log($bucket, 'origin');
            self::done($back);
        }
        if (!Http::checkFormToken(Http::str($in, 'token', 80), 0)) {
            Http::error('Le formulaire a expiré : rechargez la page puis réessayez.', 422);
        }
        if (!Http::rateLimit($bucket, $max, 900)) {
            Http::error('Trop d\'envois en peu de temps : réessayez dans quelques minutes ou appelez-nous.', 429);
        }
    }

    /**
     * Assistant et résumé du vocal : ils n'existent qu'en JavaScript, donc un appel sans la preuve
     * de la page (ou venu d'un autre site) est forcément un robot.
     */
    private static function jsOnlyGuard(array $in, string $bucket): void
    {
        if (!Antispam::enabled()) {
            return;
        }
        if (!Antispam::originOk()) {
            Antispam::log($bucket, 'origin');
            Http::error('Requête refusée.', 403);
        }
        if (!Antispam::proofOk(Http::str($in, 'pf', 20), Http::str($in, 'token', 80))) {
            Antispam::log($bucket, 'proof');
            Http::error('Rechargez la page puis réessayez.', 403);
        }
    }

    private static function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
    }

    private static function done(string $back): void
    {
        if (self::wantsJson()) {
            Http::json(['ok' => true]);
        }
        Http::redirect($back . '?envoye=1', 303);
    }

    private static function phoneOk(string $t): bool
    {
        return (bool) preg_match('/^\+?[0-9 .\-()]{8,22}$/', $t) && strlen(preg_replace('/\D/', '', $t)) >= 9;
    }

    private static function types(array $in): array
    {
        $allowed = Content::devisChoices();
        $t = array_values(array_filter((array) ($in['types'] ?? []), fn($x) => is_string($x) && in_array($x, $allowed, true)));
        return array_slice(array_unique($t), 0, 20);
    }

    private static function devis(): void
    {
        $in = Http::input();
        self::guard($in, 'devis', Content::path('devis'));
        $nom = Http::str($in, 'nom', 120);
        $tel = Http::str($in, 'tel', 30);
        $email = Http::str($in, 'email', 160);
        $errors = [];
        if ($nom === '') {
            $errors['nom'] = 'Indiquez votre nom et prénom.';
        }
        if (!self::phoneOk($tel)) {
            $errors['tel'] = 'Indiquez un numéro de téléphone valide.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse e-mail invalide.';
        }
        $cp = Http::str($in, 'cp', 10);
        if ($cp !== '' && !preg_match('/^\d{5}$/', $cp)) {
            $errors['cp'] = 'Code postal à 5 chiffres.';
        }
        if ($errors) {
            Http::error(reset($errors), 422, ['fields' => $errors]);
        }
        $f = Content::ui()['devis_form'] ?? [];
        $qty = Http::str($in, 'qty', 60);
        $delai = Http::str($in, 'delai', 60);
        $spam = Antispam::check($in, 'devis', ['nom' => $nom, 'email' => $email, 'tel' => $tel, 'msg' => Http::str($in, 'msg', 2000)], 6);
        Leads::add([
            'type' => 'devis', 'nom' => $nom, 'tel' => $tel, 'email' => $email, 'cp' => $cp,
            'types' => self::types($in),
            'qty' => in_array($qty, $f['qty'] ?? [], true) ? $qty : '',
            'delai' => in_array($delai, $f['delai'] ?? [], true) ? $delai : '',
            'msg' => Http::str($in, 'msg', 2000),
            'page' => Http::str($in, 'page', 300),
        ], [], $spam);
        self::done(Content::path('devis'));
    }

    private static function contact(): void
    {
        $in = Http::input();
        self::guard($in, 'contact', Content::path('contact'));
        $nom = Http::str($in, 'nom', 120);
        $email = Http::str($in, 'email', 160);
        $tel = Http::str($in, 'tel', 30);
        $errors = [];
        if ($nom === '') {
            $errors['nom'] = 'Indiquez votre nom et prénom.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Indiquez une adresse e-mail valide.';
        }
        if ($tel !== '' && !self::phoneOk($tel)) {
            $errors['tel'] = 'Numéro de téléphone invalide.';
        }
        if ($errors) {
            Http::error(reset($errors), 422, ['fields' => $errors]);
        }
        $objet = Http::str($in, 'objet', 20) === 'Devis' ? 'Devis' : 'Message';
        $spam = Antispam::check($in, 'contact', ['nom' => $nom, 'email' => $email, 'tel' => $tel, 'msg' => Http::str($in, 'msg', 2000)], 5);
        Leads::add([
            'type' => $objet === 'Devis' ? 'devis' : 'contact', 'objet' => $objet,
            'nom' => $nom, 'tel' => $tel, 'email' => $email,
            'types' => $objet === 'Devis' ? self::types($in) : [],
            'msg' => Http::str($in, 'msg', 2000),
            'page' => Http::str($in, 'page', 300),
        ], [], $spam);
        self::done(Content::path('contact'));
    }

    private static function rappel(): void
    {
        $in = Http::input();
        self::guard($in, 'rappel', '/', 3);
        $tel = Http::str($in, 'tel', 30);
        if (!self::phoneOk($tel)) {
            Http::error('Indiquez un numéro de téléphone valide.', 422, ['fields' => ['tel' => 'Numéro invalide']]);
        }
        $spam = Antispam::check($in, 'rappel', ['nom' => Http::str($in, 'nom', 120), 'tel' => $tel], 2);
        Leads::add(['type' => 'rappel', 'nom' => Http::str($in, 'nom', 120), 'tel' => $tel, 'page' => Http::str($in, 'page', 300)], [], $spam);
        Http::json(['ok' => true]);
    }

    /** Message vocal : fichier audio + transcription du navigateur + coordonnées. */
    private static function vocal(): void
    {
        if (!Voice::enabled()) {
            Http::error('Messages vocaux désactivés', 404);
        }
        $in = $_POST;
        if (Http::str($in, 'site') !== '') {
            Antispam::log('vocal', 'honeypot');
            Http::json(['ok' => true]);
        }
        if (Antispam::enabled() && !Antispam::originOk()) {
            Antispam::log('vocal', 'origin');
            Http::json(['ok' => true]);
        }
        if (!Http::checkFormToken(Http::str($in, 'token', 80), 0)) {
            Http::error('Session expirée : rechargez la page puis réessayez.', 422);
        }
        $tel = Http::str($in, 'tel', 30);
        if (!self::phoneOk($tel)) {
            Http::error('Laissez votre numéro de téléphone pour que nous puissions vous rappeler.', 422, ['fields' => ['tel' => 'Numéro invalide']]);
        }
        if (!Http::rateLimit('vocal', 4, 1800)) {
            Http::error('Trop de messages en peu de temps : appelez-nous au ' . Content::site()['phone'] . '.', 429);
        }
        try {
            [$file, $mime] = Voice::store($_FILES['audio'] ?? []);
        } catch (RuntimeException $e) {
            Http::error($e->getMessage(), 422);
        }
        $transcript = Sanitizer::text($in['transcript'] ?? '', 6000);
        $summary = Sanitizer::text($in['summary'] ?? '', 1500);
        $spam = Antispam::check($in, 'vocal', ['nom' => Http::str($in, 'nom', 120), 'tel' => $tel, 'msg' => $transcript], 3);
        // Pas d'appel à l'IA (payant) pour un envoi classé en spam
        if ($transcript === '' && !$spam['spam']) {
            $transcript = Voice::transcribe($file);
        }
        if ($summary === '' && $transcript !== '' && !$spam['spam']) {
            $summary = Voice::summary($transcript);
        }
        $duration = preg_match('/^\d{1,2}:\d{2}$/', Http::str($in, 'duration', 8)) ? Http::str($in, 'duration', 8) : '';
        $path = (string) Voice::file($file);
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $nom = Http::str($in, 'nom', 120);
        // Fichier joint à l'e-mail si l'option est cochée (page E-mails) et qu'il reste léger ;
        // sinon l'e-mail part quand même, avec la transcription, et dit où écouter le message.
        $att = Settings::get('voice_attach', true) && filesize($path) < Leads::VOICE_ATTACH_MAX
            ? [['name' => 'vocal-' . ($nom !== '' ? slugify($nom) : 'client') . '-' . date('Ymd-Hi') . '.' . $ext, 'type' => $mime, 'content' => (string) file_get_contents($path)]]
            : [];
        Leads::add([
            'type' => 'vocal', 'nom' => $nom, 'tel' => $tel,
            'audio' => $file, 'mime' => $mime, 'duration' => $duration,
            'msg' => $transcript, 'summary' => $summary,
            'page' => Http::str($in, 'page', 300),
        ], $spam['spam'] ? [] : $att, $spam);
        Http::json(['ok' => true]);
    }

    /** Résumé IA affiché au visiteur avant l'envoi de son vocal. */
    private static function vocalSummary(): void
    {
        $in = Http::input();
        self::jsOnlyGuard($in, 'vocal-resume');
        if (!Http::checkFormToken(Http::str($in, 'token', 80), 0)) {
            Http::error('Session expirée : rechargez la page.', 422);
        }
        if (!Http::rateLimit('vocal-resume', 10, 600)) {
            Http::json(['ok' => true, 'summary' => '']);
        }
        Http::json(['ok' => true, 'summary' => Voice::summary(Http::str($in, 'transcript', 6000))]);
    }

    private static function bot(): void
    {
        if (empty(Content::ui()['bot']['enabled'])) {
            Http::error('Assistant désactivé', 404);
        }
        $in = Http::input();
        self::jsOnlyGuard($in, 'bot');
        if (!Http::checkFormToken(Http::str($in, 'token', 80), 0)) {
            Http::error('Session expirée : rechargez la page.', 422);
        }
        if (!Http::rateLimit('bot', 30, 600)) {
            Http::error('Vous avez posé beaucoup de questions : appelez-nous au ' . Content::site()['phone'] . ' pour aller plus vite.', 429);
        }
        if (!Antispam::botQuotaOk()) {
            Antispam::log('bot', 'quota');
            Http::error('L\'assistant est très sollicité aujourd\'hui : appelez-nous au ' . Content::site()['phone'] . ' ou demandez votre devis en ligne.', 429);
        }
        $history = is_array($in['messages'] ?? null) ? $in['messages'] : [];
        $r = Bot::reply($history, Http::str($in, 'session', 40));
        Http::json(['ok' => true, 'text' => $r['text']]);
    }

    /** Tâches planifiées : à appeler toutes les heures par le planificateur de l'hébergeur. */
    private static function cron(): void
    {
        $key = (string) ($_GET['key'] ?? '');
        if (!hash_equals(substr(hash_hmac('sha256', 'cron', app_secret()), 0, 32), $key)) {
            Http::error('Clé invalide', 403);
        }
        $out = ['ok' => true];
        try {
            $out['facebook'] = Facebook::syncIfDue();
        } catch (Throwable $e) {
            $out['facebook'] = 'erreur : ' . $e->getMessage();
        }
        try {
            $out['google'] = GoogleReviews::refreshIfDue();
        } catch (Throwable $e) {
            $out['google'] = 'erreur : ' . $e->getMessage();
        }
        try {
            Updater::checkIfDue();
            $out['updates'] = Updater::available() !== null ? 'mise à jour disponible' : 'à jour';
        } catch (Throwable $e) {
            $out['updates'] = 'erreur : ' . $e->getMessage();
        }
        Http::json($out);
    }

    public static function cronKey(): string
    {
        return substr(hash_hmac('sha256', 'cron', app_secret()), 0, 32);
    }
}
