<?php
declare(strict_types=1);

/** API JSON consommée par le front (tunnel, simulateur, mesure). */
final class ApiController
{
    /**
     * Contenu public : permet de piloter le front sans rechargement.
     * Seules les clés dont le navigateur a besoin sont exposées — le
     * bloc « funnel » contient aussi l'adresse interne de notification.
     */
    public static function content(): void
    {
        $funnel = (array) settings('funnel', []);
        json_out([
            'ok' => true,
            'simulator' => content('simulator'),
            'apply' => content('apply'),
            'funnel' => array_intersect_key($funnel, array_flip([
                'response_delay', 'exit_intent', 'exit_intent_title',
                'exit_intent_text', 'sticky_cta', 'sticky_cta_label', 'cv_upload',
            ])),
        ]);
    }

    public static function posts(): void
    {
        $rows = array_map(static fn ($p) => [
            'slug' => $p['slug'] ?? '',
            'title' => $p['title'] ?? '',
            'excerpt' => $p['excerpt'] ?? '',
            'category' => $p['category'] ?? '',
            'published_at' => $p['published_at'] ?? '',
            'url' => url('actualites/' . ($p['slug'] ?? '')),
        ], SiteController::published());
        json_out(['ok' => true, 'count' => count($rows), 'items' => $rows]);
    }

    /** Calcul serveur du simulateur (le front calcule aussi, en miroir). */
    public static function simulate(): void
    {
        $p = request_payload();
        $sim = (array) content('simulator', []);
        $price = max(0.0, (float) ($p['price'] ?? 0));
        $sales = max(0, (int) ($p['sales'] ?? 0));
        json_out(['ok' => true] + self::compute($sim, $price, $sales));
    }

    public static function compute(array $sim, float $price, int $sales): array
    {
        $feeRate = (float) ($sim['agency_fee_rate'] ?? 4.5);
        $tiers = $sim['tiers'] ?? [];
        $rate = 0.0;
        $tierName = '';
        foreach ($tiers as $t) {
            if ($sales >= (int) ($t['from'] ?? 0) && $sales <= (int) ($t['to'] ?? 9999)) {
                $rate = (float) ($t['rate'] ?? 0);
                $tierName = (string) ($t['name'] ?? '');
            }
        }
        if ($rate === 0.0 && $tiers) {
            $last = end($tiers);
            $rate = (float) ($last['rate'] ?? 0);
            $tierName = (string) ($last['name'] ?? '');
        }
        $feePerSale = $price * $feeRate / 100;
        $gross = $feePerSale * $sales;
        $net = $gross * $rate / 100;
        return [
            'tier' => $tierName,
            'rate' => $rate,
            'fee_per_sale' => round($feePerSale),
            'gross_year' => round($gross),
            'net_year' => round($net),
            'net_month' => round($net / 12),
        ];
    }

    /**
     * Enregistrement d'un évènement d'interaction.
     *
     * Le jeton est exigé, et seuls les évènements réellement produits par
     * l'interface sont acceptés : les étapes du tunnel, les candidatures
     * et les messages sont journalisés par le serveur au moment où ils
     * ont lieu. Les accepter ici permettrait de fabriquer de fausses
     * conversions dans le tableau de bord.
     */
    public static function track(): void
    {
        Csrf::guard();
        $p = request_payload();
        $event = (string) ($p['event'] ?? '');
        if (!in_array($event, Analytics::CLIENT_EVENTS, true)) {
            json_out(['ok' => false, 'error' => 'Évènement non recevable.'], 422);
        }
        if (!RateLimit::hit('track', 300, 600)) {
            json_out(['ok' => false], 429);
        }
        Analytics::track($event, [
            'page' => (string) ($p['page'] ?? ''),
            'source' => (string) ($p['source'] ?? ''),
        ]);
        json_out(['ok' => true]);
    }

    /**
     * Capture progressive : chaque étape validée du tunnel est
     * enregistrée, même si le visiteur abandonne ensuite.
     */
    public static function applyStep(): void
    {
        Csrf::guard();
        $p = request_payload();
        // Même piège que sur l'envoi final : sans lui, un robot remplit la
        // liste des candidatures de brouillons fantômes.
        if (trim((string) ($p['website'] ?? '')) !== '') {
            json_out(['ok' => true, 'draft_id' => '', 'step' => (int) ($p['step'] ?? 1)]);
        }
        if (!RateLimit::hit('apply_step', 60, 900)) {
            json_out(['ok' => false, 'error' => 'Trop de requêtes, réessayez dans quelques minutes.'], 429);
        }
        $draftId = preg_replace('/[^a-z0-9\-]/i', '', (string) ($p['draft_id'] ?? '')) ?: Store::uid('drf-');
        $step = max(1, min(4, (int) ($p['step'] ?? 1)));
        $fields = self::sanitizeApplication($p);

        Store::mutate('applications', static function (array $rows) use ($draftId, $step, $fields): array {
            foreach ($rows as $i => $row) {
                if (($row['id'] ?? '') === $draftId) {
                    $rows[$i] = array_replace($row, array_filter($fields, static fn ($v) => $v !== ''), [
                        'id' => $draftId,
                        'status' => $row['status'] ?? 'brouillon',
                        'max_step' => max((int) ($row['max_step'] ?? 0), $step),
                        'updated_at' => date('c'),
                    ]);
                    return $rows;
                }
            }
            $rows[] = array_replace($fields, [
                'id' => $draftId,
                'status' => 'brouillon',
                'stage' => 'nouveau',
                'max_step' => $step,
                'visitor' => visitor_hash(),
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ]);
            return $rows;
        });

        Analytics::track('funnel_step_' . $step, ['page' => '/candidater', 'source' => (string) ($fields['source'] ?? '')]);
        json_out(['ok' => true, 'draft_id' => $draftId, 'step' => $step]);
    }

    /** Soumission finale de la candidature. */
    public static function apply(): void
    {
        Csrf::guard();
        $p = request_payload();

        // Pièges anti-robot : champ caché + délai minimal de remplissage.
        if (trim((string) ($p['website'] ?? '')) !== '') {
            json_out(['ok' => true, 'redirect' => url('merci')]);
        }
        $elapsed = (int) ($p['elapsed'] ?? 0);
        if ($elapsed > 0 && $elapsed < 3) {
            json_out(['ok' => false, 'error' => 'Formulaire envoyé trop vite. Merci de réessayer.'], 422);
        }
        if (!RateLimit::hit('apply', (int) settings('security.max_applications_per_hour', 5), 3600)) {
            json_out(['ok' => false, 'error' => 'Vous avez déjà envoyé plusieurs candidatures. Contactez-nous directement par téléphone.'], 429);
        }

        $fields = self::sanitizeApplication($p);
        $errors = [];
        if ($fields['name'] === '') { $errors['name'] = 'Votre nom est requis.'; }
        if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Adresse e-mail invalide.'; }
        if (strlen(preg_replace('/\D/', '', $fields['phone']) ?? '') < 9) { $errors['phone'] = 'Numéro de téléphone invalide.'; }
        if ($fields['area'] === '') { $errors['area'] = 'Indiquez votre secteur souhaité.'; }
        if (empty($p['consent'])) { $errors['consent'] = 'Merci d’accepter le traitement de vos données.'; }
        if ($errors) {
            json_out(['ok' => false, 'errors' => $errors, 'error' => 'Merci de corriger les champs signalés.'], 422);
        }

        $cv = self::handleUpload();
        if (isset($cv['error'])) {
            json_out(['ok' => false, 'errors' => ['cv' => $cv['error']], 'error' => $cv['error']], 422);
        }

        $draftId = preg_replace('/[^a-z0-9\-]/i', '', (string) ($p['draft_id'] ?? '')) ?: null;
        $record = array_replace($fields, [
            'status' => 'nouveau',
            'stage' => 'nouveau',
            'cv' => $cv['file'] ?? '',
            'cv_name' => $cv['name'] ?? '',
            'visitor' => visitor_hash(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
            'submitted_at' => date('c'),
            'notes' => [],
        ]);

        $saved = null;
        if ($draftId !== null && Store::find('applications', $draftId) !== null) {
            $saved = Store::update('applications', $draftId, $record);
        }
        if ($saved === null) {
            $saved = Store::push('applications', $record);
        }

        Analytics::track('application', ['page' => '/candidater', 'source' => $fields['source']]);
        self::notifyApplication($saved ?? $record);

        json_out(['ok' => true, 'redirect' => url('merci'), 'id' => $saved['id'] ?? '']);
    }

    /** Message de contact / capture rapide (pop-in de sortie). */
    public static function lead(): void
    {
        Csrf::guard();
        $p = request_payload();
        if (trim((string) ($p['website'] ?? '')) !== '') {
            json_out(['ok' => true]);
        }
        if (!RateLimit::hit('lead', (int) settings('security.max_leads_per_hour', 8), 3600)) {
            json_out(['ok' => false, 'error' => 'Trop de messages envoyés. Réessayez plus tard.'], 429);
        }
        $name = self::str($p['name'] ?? '', 120);
        $email = self::str($p['email'] ?? '', 160);
        $phone = self::str($p['phone'] ?? '', 40);
        $message = self::text($p['message'] ?? '', 4000);
        $origin = self::str($p['origin'] ?? 'contact', 40);

        $errors = [];
        if ($name === '') { $errors['name'] = 'Votre nom est requis.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Adresse e-mail invalide.'; }
        if ($origin === 'contact' && mb_strlen($message) < 5) { $errors['message'] = 'Votre message est un peu court.'; }
        if ($errors) {
            json_out(['ok' => false, 'errors' => $errors, 'error' => 'Merci de corriger les champs signalés.'], 422);
        }

        $lead = Store::push('leads', [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $message,
            'origin' => $origin,
            'status' => 'nouveau',
            'visitor' => visitor_hash(),
        ]);
        Analytics::track('lead', ['page' => $origin]);

        $to = (string) settings('funnel.notify_email', settings('company.email'));
        Mailer::send(
            $to,
            'Nouveau message — ' . $name,
            '<h2 style="margin:0 0 12px">Nouveau message (' . e($origin) . ')</h2>'
            . '<p><strong>Nom :</strong> ' . e($name) . '<br>'
            . '<strong>E-mail :</strong> ' . e($email) . '<br>'
            . '<strong>Téléphone :</strong> ' . e($phone ?: '—') . '</p>'
            . '<p style="white-space:pre-wrap">' . e($message) . '</p>',
            $email
        );

        json_out(['ok' => true, 'id' => $lead['id']]);
    }

    /** Dialogue public avec l'assistant. */
    public static function botChat(): void
    {
        Csrf::guard();
        if (!Bot::isReady()) {
            json_out(['ok' => false, 'error' => 'L’assistant est momentanément indisponible. Écrivez-nous via le formulaire de contact.'], 503);
        }
        if (!RateLimit::hit('bot', 25, 900)) {
            json_out(['ok' => false, 'error' => 'Vous avez posé beaucoup de questions d’affilée. Reprenons dans quelques minutes — ou candidatez directement.'], 429);
        }
        $p = request_payload();
        $question = (string) ($p['question'] ?? '');
        $history = [];
        foreach ((array) ($p['history'] ?? []) as $turn) {
            if (!is_array($turn)) { continue; }
            $history[] = ['role' => (string) ($turn['role'] ?? 'user'), 'text' => (string) ($turn['text'] ?? '')];
        }
        $res = Bot::ask($question, $history);
        Bot::logConversation($question, (string) ($res['answer'] ?? $res['error'] ?? ''), (bool) $res['ok'], 'site');
        if (!$res['ok']) {
            // Le motif exact (clé refusée, quota, modèle inconnu) renseigne sur
            // la configuration : il reste au journal et à la console de test.
            json_out(['ok' => false, 'error' => 'L’assistant est momentanément indisponible. Écrivez-nous via le formulaire de contact.'], 503);
        }
        unset($res['used'], $res['model']);   // détail interne, inutile côté visiteur
        json_out($res, 200);
    }

    // ---------------------------------------------------------------- outils

    private static function sanitizeApplication(array $p): array
    {
        return [
            'name' => self::str($p['name'] ?? '', 120),
            'email' => strtolower(self::str($p['email'] ?? '', 160)),
            'phone' => self::str($p['phone'] ?? '', 40),
            'area' => self::str($p['area'] ?? '', 160),
            'situation' => self::choice($p['situation'] ?? '', 'apply.situations'),
            'availability' => self::choice($p['availability'] ?? '', 'apply.availabilities'),
            'experience' => self::choice($p['experience'] ?? '', 'apply.experiences'),
            'goal' => self::text($p['goal'] ?? '', 2000),
            'source' => self::choice($p['source'] ?? '', 'apply.sources'),
            'message' => self::text($p['message'] ?? '', 4000),
            'simulation' => is_array($p['simulation'] ?? null) ? array_map(static fn ($v) => is_scalar($v) ? $v : '', $p['simulation']) : [],
        ];
    }

    /**
     * Champ à liste fermée.
     *
     * Ces valeurs viennent de boutons radio ou de listes déroulantes : rien
     * n'empêche d'en poster d'autres. Une valeur absente de la liste éditée
     * dans le back-office est écartée, ce qui évite de retrouver du texte
     * arbitraire dans l'export et les e-mails.
     */
    private static function choice(mixed $v, string $chemin): string
    {
        $valeur = self::str($v, 160);
        if ($valeur === '') {
            return '';
        }
        $options = array_map(static fn ($o) => (string) $o, (array) content($chemin, []));
        return in_array($valeur, $options, true) ? $valeur : '';
    }

    /** Champ mono-ligne : retours à la ligne et tabulations deviennent des espaces. */
    private static function str(mixed $v, int $max): string
    {
        $v = is_scalar($v) ? (string) $v : '';
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v) ?? '';
        $v = preg_replace('/[\r\n\t]+/u', ' ', $v) ?? '';
        $v = preg_replace('/ {2,}/u', ' ', $v) ?? '';
        return mb_substr(trim($v), 0, $max);
    }

    /** Champ multi-lignes : les sauts de ligne sont conservés, normalisés. */
    private static function text(mixed $v, int $max): string
    {
        $v = is_scalar($v) ? (string) $v : '';
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v) ?? '';
        $v = str_replace(["\r\n", "\r"], "\n", $v);
        $v = preg_replace('/\n{3,}/u', "\n\n", $v) ?? '';
        return mb_substr(trim($v), 0, $max);
    }

    /** @return array{file?:string,name?:string,error?:string} */
    private static function handleUpload(): array
    {
        if (!settings('funnel.cv_upload', true) || empty($_FILES['cv']['name'])) {
            return [];
        }
        $f = $_FILES['cv'];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return ['error' => 'Le fichier n’a pas pu être envoyé.'];
        }
        if (($f['size'] ?? 0) > UPLOAD_MAX_BYTES) {
            return ['error' => 'CV trop volumineux (5 Mo maximum).'];
        }
        $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        if (!array_key_exists($ext, UPLOAD_ALLOWED)) {
            return ['error' => 'Formats acceptés : PDF, DOC, DOCX.'];
        }
        if (!is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
            return ['error' => 'Le fichier n’a pas pu être envoyé.'];
        }
        // L'extension est déclarative : on vérifie le contenu réel. Un
        // script renommé « cv.pdf » serait sinon stocké tel quel.
        if (!self::mimeAutorise((string) $f['tmp_name'], $ext)) {
            return ['error' => 'Ce fichier n’est pas un PDF ni un document Word valide.'];
        }
        if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0775, true); }
        $stored = Store::uid('cv-') . '.' . $ext;
        if (!@move_uploaded_file((string) $f['tmp_name'], UPLOAD_DIR . '/' . $stored)) {
            return ['error' => 'Impossible d’enregistrer le fichier.'];
        }
        @chmod(UPLOAD_DIR . '/' . $stored, 0644);
        return ['file' => $stored, 'name' => mb_substr((string) $f['name'], 0, 120)];
    }

    /** Le type réel du fichier correspond-il à l'extension annoncée ? */
    private static function mimeAutorise(string $chemin, string $ext): bool
    {
        $attendu = UPLOAD_ALLOWED[$ext] ?? '';
        if (!class_exists('finfo')) {
            // Sans l'extension fileinfo, on retombe sur la signature du
            // fichier : elle suffit à écarter un script déguisé.
            $tete = (string) @file_get_contents($chemin, false, null, 0, 8);
            return $ext === 'pdf'
                ? str_starts_with($tete, '%PDF-')
                : (str_starts_with($tete, "PK\x03\x04") || str_starts_with($tete, "\xD0\xCF\x11\xE0"));
        }
        $reel = (string) (new finfo(FILEINFO_MIME_TYPE))->file($chemin);
        $tolerés = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
            ],
        ];
        return in_array($reel, $tolerés[$ext] ?? [$attendu], true);
    }

    private static function notifyApplication(array $a): void
    {
        $to = (string) settings('funnel.notify_email', settings('company.email'));
        $rows = [
            'Nom' => $a['name'] ?? '',
            'E-mail' => $a['email'] ?? '',
            'Téléphone' => $a['phone'] ?? '',
            'Secteur souhaité' => $a['area'] ?? '',
            'Situation' => $a['situation'] ?? '',
            'Disponibilité' => $a['availability'] ?? '',
            'Expérience immobilier' => $a['experience'] ?? '',
            'Objectif' => $a['goal'] ?? '',
            'Nous a connus par' => $a['source'] ?? '',
            'CV' => $a['cv_name'] ?? '—',
        ];
        $html = '<h2 style="margin:0 0 12px">Nouvelle candidature</h2><table cellpadding="6" style="font-size:14px">';
        foreach ($rows as $k => $v) {
            $html .= '<tr><td style="color:#8d99ae">' . e($k) . '</td><td><strong>' . e((string) ($v ?: '—')) . '</strong></td></tr>';
        }
        $html .= '</table>';
        if (!empty($a['message'])) {
            $html .= '<p style="white-space:pre-wrap;margin-top:16px">' . e((string) $a['message']) . '</p>';
        }
        Mailer::send($to, 'Nouvelle candidature — ' . ($a['name'] ?? ''), $html, (string) ($a['email'] ?? ''));

        // Accusé de réception au candidat.
        if (filter_var($a['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            Mailer::send(
                (string) $a['email'],
                'Votre candidature chez Suisse Immo',
                '<h2 style="margin:0 0 12px">Merci ' . e(explode(' ', (string) $a['name'])[0]) . ' !</h2>'
                . '<p>Nous avons bien reçu votre candidature pour devenir agent commercial immobilier indépendant chez Suisse Immo.</p>'
                . '<p>Notre équipe étudie votre profil et revient vers vous sous <strong>' . e((string) settings('funnel.response_delay', '48 heures ouvrées')) . '</strong> pour fixer votre rendez-vous stratégique.</p>'
                . '<p>À très vite,<br>L’équipe Suisse Immo</p>'
            );
        }
    }
}
