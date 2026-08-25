<?php
declare(strict_types=1);

/** Back-office : pilotage du contenu, du tunnel et des candidatures. */
final class AdminController
{
    // ------------------------------------------------------------ session

    public static function login(): void
    {
        if (Auth::check()) { redirect(url('admin')); }
        $error = null;
        if (is_post()) {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $error = 'Session expirée, merci de réessayer.';
            } elseif (!RateLimit::hit('login', 10, 900)) {
                $error = 'Trop de tentatives. Patientez quelques minutes.';
            } elseif (Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                redirect(url('admin'));
            } else {
                $error = 'Identifiants incorrects.';
            }
        }
        echo view('admin/login', ['error' => $error], 'admin/layout-bare');
    }

    public static function logout(): void
    {
        Auth::logout();
        redirect(url('admin/login'));
    }

    // ---------------------------------------------------------- dashboard

    public static function dashboard(): void
    {
        $user = Auth::requireLogin();
        $days = max(7, min(90, (int) ($_GET['days'] ?? 30)));
        $applications = self::applications();
        $submitted = array_values(array_filter($applications, static fn ($a) => ($a['status'] ?? '') !== 'brouillon'));
        $drafts = array_values(array_filter($applications, static fn ($a) => ($a['status'] ?? '') === 'brouillon'));
        $leads = Store::read('leads');

        echo view('admin/dashboard', [
            'user' => $user,
            'nav' => 'dashboard',
            'title' => 'Tableau de bord',
            'days' => $days,
            'stats' => Analytics::summary($days),
            'applications' => array_slice($submitted, 0, 8),
            'total_applications' => count($submitted),
            'total_drafts' => count($drafts),
            'total_leads' => count($leads),
            'week_applications' => count(array_filter($submitted, static fn ($a) => strtotime((string) ($a['submitted_at'] ?? $a['created_at'] ?? '')) > strtotime('-7 days'))),
            'by_stage' => self::countByStage($submitted),
        ], 'admin/layout');
    }

    // ------------------------------------------------------- candidatures

    public static function applicationsList(): void
    {
        $user = Auth::requireLogin();
        $rows = self::applications();
        $stage = (string) ($_GET['stage'] ?? '');
        $q = trim((string) ($_GET['q'] ?? ''));
        $showDrafts = ($_GET['drafts'] ?? '') === '1';

        $rows = array_values(array_filter($rows, static function ($a) use ($stage, $q, $showDrafts) {
            $isDraft = ($a['status'] ?? '') === 'brouillon';
            if ($showDrafts !== $isDraft) { return false; }
            if ($stage !== '' && ($a['stage'] ?? 'nouveau') !== $stage) { return false; }
            if ($q !== '') {
                $hay = mb_strtolower(implode(' ', [$a['name'] ?? '', $a['email'] ?? '', $a['phone'] ?? '', $a['area'] ?? '']));
                if (!str_contains($hay, mb_strtolower($q))) { return false; }
            }
            return true;
        }));

        echo view('admin/applications', [
            'user' => $user,
            'nav' => 'applications',
            'title' => $showDrafts ? 'Candidatures abandonnées' : 'Candidatures',
            'rows' => $rows,
            'stage' => $stage,
            'q' => $q,
            'showDrafts' => $showDrafts,
            'by_stage' => self::countByStage(array_values(array_filter(self::applications(), static fn ($a) => ($a['status'] ?? '') !== 'brouillon'))),
        ], 'admin/layout');
    }

    public static function applicationShow(array $params): void
    {
        $user = Auth::requireLogin();
        $row = Store::find('applications', (string) ($params['id'] ?? ''));
        if ($row === null) { self::adminNotFound(); return; }

        if (is_post() && Csrf::check($_POST['_csrf'] ?? null)) {
            $patch = [];
            if (isset($_POST['stage'])) { $patch['stage'] = (string) $_POST['stage']; }
            if (trim((string) ($_POST['note'] ?? '')) !== '') {
                $notes = $row['notes'] ?? [];
                $notes[] = ['author' => $user['name'] ?: $user['email'], 'text' => mb_substr(trim((string) $_POST['note']), 0, 2000), 'at' => date('c')];
                $patch['notes'] = $notes;
            }
            if ($patch) {
                Store::update('applications', (string) $row['id'], $patch);
                Session::flash('Candidature mise à jour.');
            }
            redirect(url('admin/candidatures/' . $row['id']));
        }

        echo view('admin/application', [
            'user' => $user,
            'nav' => 'applications',
            'title' => (string) ($row['name'] ?: 'Candidature'),
            'row' => Store::find('applications', (string) $row['id']),
        ], 'admin/layout');
    }

    public static function applicationDelete(array $params): void
    {
        Auth::requireLogin();
        if (Csrf::check($_POST['_csrf'] ?? null)) {
            $row = Store::find('applications', (string) ($params['id'] ?? ''));
            if ($row && !empty($row['cv'])) { @unlink(UPLOAD_DIR . '/' . basename((string) $row['cv'])); }
            Store::delete('applications', (string) ($params['id'] ?? ''));
            Session::flash('Candidature supprimée.');
        }
        redirect(url('admin/candidatures'));
    }

    public static function applicationsExport(): void
    {
        Auth::requireLogin();
        $rows = self::applications();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="candidatures-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
        fputcsv($out, ['ID', 'Date', 'Statut', 'Étape', 'Nom', 'E-mail', 'Téléphone', 'Secteur', 'Situation', 'Disponibilité', 'Expérience', 'Objectif', 'Origine', 'Message'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'] ?? '', fr_date((string) ($r['submitted_at'] ?? $r['created_at'] ?? ''), true),
                $r['status'] ?? '', $r['stage'] ?? '', $r['name'] ?? '', $r['email'] ?? '', $r['phone'] ?? '',
                $r['area'] ?? '', $r['situation'] ?? '', $r['availability'] ?? '', $r['experience'] ?? '',
                $r['goal'] ?? '', $r['source'] ?? '', $r['message'] ?? '',
            ], ';');
        }
        fclose($out);
        exit;
    }

    public static function cv(array $params): void
    {
        Auth::requireLogin();
        $row = Store::find('applications', (string) ($params['id'] ?? ''));
        $file = UPLOAD_DIR . '/' . basename((string) ($row['cv'] ?? ''));
        if (!$row || empty($row['cv']) || !is_file($file)) { self::adminNotFound(); return; }
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($file));
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^\w\.\- ]/u', '', (string) ($row['cv_name'] ?: 'cv')) . '"');
        readfile($file);
        exit;
    }

    // -------------------------------------------------------------- leads

    public static function leads(): void
    {
        $user = Auth::requireLogin();
        $rows = Store::read('leads');
        usort($rows, static fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        echo view('admin/leads', ['user' => $user, 'nav' => 'leads', 'title' => 'Messages & captures', 'rows' => $rows], 'admin/layout');
    }

    public static function leadDelete(array $params): void
    {
        Auth::requireLogin();
        if (Csrf::check($_POST['_csrf'] ?? null)) {
            Store::delete('leads', (string) ($params['id'] ?? ''));
            Session::flash('Message supprimé.');
        }
        redirect(url('admin/messages'));
    }

    // ------------------------------------------------------------ contenu

    public static function contentEdit(array $params): void
    {
        $user = Auth::requireLogin();
        $schema = ContentSchema::all();
        $section = (string) ($params['section'] ?? array_key_first($schema));
        if (!isset($schema[$section])) { self::adminNotFound(); return; }

        if (is_post() && Csrf::check($_POST['_csrf'] ?? null)) {
            $spec = $schema[$section];
            $data = Store::read('content');
            if (isset($spec['root'])) {
                $data[$section] = ContentSchema::hydrate($spec['root'], $_POST['data'] ?? null);
            } else {
                $current = is_array($data[$section] ?? null) ? $data[$section] : [];
                foreach ($spec['fields'] as $key => $fieldSpec) {
                    $current[$key] = ContentSchema::hydrate($fieldSpec, $_POST['data'][$key] ?? null);
                }
                $data[$section] = $current;
            }
            Store::write('content', $data);
            Session::flash('Section « ' . $spec['label'] . ' » enregistrée.');
            redirect(url('admin/contenu/' . $section));
        }

        echo view('admin/content', [
            'user' => $user,
            'nav' => 'content',
            'title' => 'Contenu du site',
            'schema' => $schema,
            'section' => $section,
            'spec' => $schema[$section],
            'value' => Store::read('content')[$section] ?? [],
        ], 'admin/layout');
    }

    // --------------------------------------------------------- actualités

    public static function posts(): void
    {
        $user = Auth::requireLogin();
        $rows = Store::read('posts');
        usort($rows, static fn ($a, $b) => strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? '')));
        echo view('admin/posts', ['user' => $user, 'nav' => 'posts', 'title' => 'Actualités', 'rows' => $rows], 'admin/layout');
    }

    public static function postEdit(array $params): void
    {
        $user = Auth::requireLogin();
        $id = (string) ($params['id'] ?? 'nouveau');
        $row = $id === 'nouveau' ? ['id' => '', 'title' => '', 'slug' => '', 'excerpt' => '', 'body' => '', 'category' => 'Marché immobilier', 'author' => 'La rédaction Suisse Immo', 'status' => 'draft', 'published_at' => date('c')] : Store::find('posts', $id);
        if ($row === null) { self::adminNotFound(); return; }

        if (is_post() && Csrf::check($_POST['_csrf'] ?? null)) {
            $title = trim((string) ($_POST['title'] ?? ''));
            $slug = slugify((string) ($_POST['slug'] ?? '') !== '' ? (string) $_POST['slug'] : $title);
            $payload = [
                'title' => $title,
                'slug' => $slug,
                'excerpt' => mb_substr(trim((string) ($_POST['excerpt'] ?? '')), 0, 300),
                'body' => self::sanitizeHtml((string) ($_POST['body'] ?? '')),
                'category' => trim((string) ($_POST['category'] ?? '')),
                'author' => trim((string) ($_POST['author'] ?? '')),
                'status' => ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
                'published_at' => date('c', strtotime((string) ($_POST['published_at'] ?? 'now')) ?: time()),
            ];
            if ($title === '') {
                Session::flash('Le titre est obligatoire.', 'error');
            } elseif ($id === 'nouveau') {
                $new = Store::push('posts', $payload);
                Session::flash('Article créé.');
                redirect(url('admin/actualites/' . $new['id']));
            } else {
                Store::update('posts', $id, $payload);
                Session::flash('Article enregistré.');
                redirect(url('admin/actualites/' . $id));
            }
        }

        echo view('admin/post-edit', ['user' => $user, 'nav' => 'posts', 'title' => $id === 'nouveau' ? 'Nouvel article' : 'Modifier l’article', 'row' => $row], 'admin/layout');
    }

    public static function postDelete(array $params): void
    {
        Auth::requireLogin();
        if (Csrf::check($_POST['_csrf'] ?? null)) {
            Store::delete('posts', (string) ($params['id'] ?? ''));
            Session::flash('Article supprimé.');
        }
        redirect(url('admin/actualites'));
    }

    // ---------------------------------------------------------- réglages

    public static function settings(): void
    {
        $user = Auth::requireLogin();
        if (is_post() && Csrf::check($_POST['_csrf'] ?? null)) {
            $s = Store::read('settings');
            foreach (['site', 'company', 'funnel', 'motion'] as $group) {
                foreach (($_POST[$group] ?? []) as $k => $v) {
                    if (!array_key_exists($k, $s[$group] ?? [])) { continue; }
                    $current = $s[$group][$k];
                    if (is_bool($current)) {
                        $s[$group][$k] = (bool) $v;
                    } elseif (is_int($current)) {
                        $s[$group][$k] = (int) $v;
                    } elseif (is_float($current)) {
                        $s[$group][$k] = (float) $v;
                    } else {
                        $s[$group][$k] = trim((string) $v);
                    }
                }
            }
            // Les cases à cocher absentes valent « faux ».
            foreach (['notify_enabled', 'exit_intent', 'sticky_cta', 'cv_upload'] as $flag) {
                $s['funnel'][$flag] = isset($_POST['funnel'][$flag]);
            }
            $s['motion']['glow'] = isset($_POST['motion']['glow']);
            $s['motion']['glow_cycle'] = max(8, min(180, (int) ($_POST['motion']['glow_cycle'] ?? 34)));
            Store::write('settings', $s);
            Session::flash('Réglages enregistrés.');
            redirect(url('admin/reglages'));
        }
        echo view('admin/settings', ['user' => $user, 'nav' => 'settings', 'title' => 'Réglages', 'settings' => Store::read('settings')], 'admin/layout');
    }

    public static function users(): void
    {
        $user = Auth::requireLogin();
        if (is_post() && Csrf::check($_POST['_csrf'] ?? null)) {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'create') {
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $pass = (string) ($_POST['password'] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) {
                    Session::flash('E-mail invalide ou mot de passe trop court (10 caractères minimum).', 'error');
                } else {
                    Store::push('users', [
                        'name' => trim((string) ($_POST['name'] ?? '')),
                        'email' => $email,
                        'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                        'role' => 'admin',
                        'active' => true,
                    ]);
                    Session::flash('Utilisateur créé.');
                }
            } elseif ($action === 'password') {
                $pass = (string) ($_POST['password'] ?? '');
                if (strlen($pass) < 10) {
                    Session::flash('Mot de passe trop court (10 caractères minimum).', 'error');
                } else {
                    Store::update('users', (string) ($_POST['id'] ?? ''), ['password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'must_change_password' => false]);
                    Session::flash('Mot de passe modifié.');
                }
            } elseif ($action === 'delete') {
                $id = (string) ($_POST['id'] ?? '');
                if ($id === ($user['id'] ?? '')) {
                    Session::flash('Vous ne pouvez pas supprimer votre propre compte.', 'error');
                } elseif (count(Store::read('users')) <= 1) {
                    Session::flash('Il doit rester au moins un administrateur.', 'error');
                } else {
                    Store::delete('users', $id);
                    Session::flash('Utilisateur supprimé.');
                }
            }
            redirect(url('admin/utilisateurs'));
        }
        echo view('admin/users', ['user' => $user, 'nav' => 'users', 'title' => 'Utilisateurs', 'rows' => Store::read('users')], 'admin/layout');
    }

    /**
     * Envoi d'un e-mail depuis le back-office. L'adresse du destinataire
     * est relue depuis l'enregistrement : ce que poste le navigateur ne
     * sert qu'à désigner la fiche, jamais à choisir qui reçoit le message.
     */
    public static function sendEmail(): void
    {
        $user = Auth::requireLogin();
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            Session::flash('Session expirée, merci de réessayer.', 'error');
            redirect(url('admin/candidatures'));
        }

        $type = (string) ($_POST['target_type'] ?? '');
        $id = (string) ($_POST['target_id'] ?? '');
        $collection = $type === 'lead' ? 'leads' : 'applications';
        $back = $type === 'lead' ? url('admin/messages') : url('admin/candidatures/' . $id);

        $row = Store::find($collection, $id);
        if ($row === null) {
            Session::flash('Destinataire introuvable.', 'error');
            redirect($type === 'lead' ? url('admin/messages') : url('admin/candidatures'));
        }

        $to = trim((string) ($row['email'] ?? ''));
        $subject = mb_substr(trim((string) ($_POST['subject'] ?? '')), 0, 180);
        $body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 20000);

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Session::flash('Cette fiche ne comporte pas d’adresse e-mail valide.', 'error');
            redirect($back);
        }
        if ($subject === '' || $body === '') {
            Session::flash('L’objet et le message sont obligatoires.', 'error');
            redirect($back);
        }

        $html = '<p style="white-space:pre-wrap;margin:0">' . nl2br(e($body)) . '</p>';
        $sent = Mailer::send($to, $subject, $html, (string) settings('company.email'));

        if (!empty($_POST['note']) && $type !== 'lead') {
            $notes = (array) ($row['notes'] ?? []);
            $notes[] = [
                'author' => $user['name'] ?: $user['email'],
                'text' => ($sent ? 'E-mail envoyé' : 'E-mail rédigé (envoi serveur en échec)') . ' — « ' . $subject . " »\n\n" . $body,
                'at' => date('c'),
            ];
            Store::update('applications', $id, ['notes' => $notes, 'last_contacted_at' => date('c')]);
        } elseif ($type === 'lead') {
            Store::update('leads', $id, ['status' => 'repondu', 'last_contacted_at' => date('c')]);
        }

        Session::flash($sent
            ? 'Message envoyé à ' . $to . '.'
            : 'Message enregistré, mais le serveur n’a pas pu l’expédier (fonction mail() non configurée). Retrouvez-le dans « E-mails envoyés ».',
            $sent ? 'success' : 'error');
        redirect($back);
    }

    // ------------------------------------------------------------- bot IA

    public static function bot(): void
    {
        $user = Auth::requireLogin();

        if (is_post() && Csrf::check($_POST['_csrf'] ?? null)) {
            $cfg = Bot::config();
            $patch = [
                'enabled' => isset($_POST['enabled']),
                'model' => trim((string) ($_POST['model'] ?? $cfg['model'])),
                'name' => mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 60),
                'role' => mb_substr(trim((string) ($_POST['role'] ?? '')), 0, 120),
                'greeting' => mb_substr(trim((string) ($_POST['greeting'] ?? '')), 0, 500),
                'temperature' => max(0, min(2, (float) ($_POST['temperature'] ?? 0.35))),
                'max_tokens' => max(64, min(4096, (int) ($_POST['max_tokens'] ?? 700))),
                'persona' => mb_substr(trim((string) ($_POST['persona'] ?? '')), 0, 6000),
                'notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 40000),
                'suggestions' => array_values(array_filter(array_map(
                    static fn ($v) => mb_substr(trim((string) $v), 0, 120),
                    preg_split('/\r?\n/', (string) ($_POST['suggestions'] ?? '')) ?: []
                ))),
                'sources' => [
                    'content' => isset($_POST['sources']['content']),
                    'posts' => isset($_POST['sources']['posts']),
                    'company' => isset($_POST['sources']['company']),
                    'documents' => isset($_POST['sources']['documents']),
                    'notes' => isset($_POST['sources']['notes']),
                ],
            ];
            // La clé n'est réécrite que si le champ a été rempli.
            $key = trim((string) ($_POST['api_key'] ?? ''));
            if ($key !== '' && !str_starts_with($key, '••')) {
                $patch['api_key'] = $key;
            }
            if (($_POST['clear_key'] ?? '') === '1') {
                $patch['api_key'] = '';
                $patch['models'] = [];
            }
            Bot::save($patch);
            Session::flash('Configuration du bot enregistrée.');
            redirect(url('admin/bot'));
        }

        $chunks = Bot::knowledge();
        $chars = 0;
        foreach ($chunks as $c) { $chars += mb_strlen($c['text']); }

        echo view('admin/bot', [
            'user' => $user,
            'nav' => 'bot',
            'title' => 'Bot IA',
            'cfg' => Bot::config(),
            'docs' => Bot::documents(),
            'chunks' => count($chunks),
            'chars' => $chars,
            'chats' => array_slice(Store::read('bot-chats'), 0, 25),
        ], 'admin/layout');
    }

    /** Rafraîchit la liste des modèles Gemini (appel AJAX depuis le back). */
    public static function botModels(): void
    {
        Auth::requireLogin();
        Csrf::guard();
        $payload = request_payload();
        $key = trim((string) ($payload['api_key'] ?? ''));
        if ($key !== '' && str_starts_with($key, '••')) { $key = ''; }
        $res = Bot::fetchModels($key !== '' ? $key : null);
        if ($res['ok']) {
            $patch = ['models' => $res['models'], 'models_fetched_at' => date('c')];
            if ($key !== '') { $patch['api_key'] = $key; }
            Bot::save($patch);
        }
        json_out($res, $res['ok'] ? 200 : 422);
    }

    /** Console de test du bot depuis le back-office. */
    public static function botTest(): void
    {
        Auth::requireLogin();
        Csrf::guard();
        $payload = request_payload();
        $res = Bot::ask((string) ($payload['question'] ?? ''), (array) ($payload['history'] ?? []));
        Bot::logConversation((string) ($payload['question'] ?? ''), (string) ($res['answer'] ?? $res['error'] ?? ''), (bool) $res['ok'], 'back-office');
        json_out($res, $res['ok'] ? 200 : 422);
    }

    public static function botDocumentAdd(): void
    {
        Auth::requireLogin();
        if (!Csrf::check($_POST['_csrf'] ?? null)) { redirect(url('admin/bot')); }
        if (empty($_FILES['document']['name'])) {
            Session::flash('Aucun fichier sélectionné.', 'error');
        } else {
            $res = Bot::addDocument($_FILES['document']);
            Session::flash($res['ok']
                ? 'Document « ' . ($res['doc']['name'] ?? '') . ' » ajouté (' . nb($res['doc']['chars'] ?? 0) . ' caractères indexés).'
                : (string) $res['error'], $res['ok'] ? 'success' : 'error');
        }
        redirect(url('admin/bot'));
    }

    public static function botDocumentDelete(array $params): void
    {
        Auth::requireLogin();
        if (Csrf::check($_POST['_csrf'] ?? null)) {
            Bot::deleteDocument((string) ($params['id'] ?? ''));
            Session::flash('Document retiré de la base de connaissances.');
        }
        redirect(url('admin/bot'));
    }

    public static function mails(): void
    {
        $user = Auth::requireLogin();
        echo view('admin/mails', ['user' => $user, 'nav' => 'mails', 'title' => 'Journal des e-mails', 'rows' => array_slice(Store::read('maillog'), 0, 100)], 'admin/layout');
    }

    // ------------------------------------------------------------- outils

    /** @return array<int,array> candidatures, plus récentes d'abord */
    private static function applications(): array
    {
        $rows = Store::read('applications');
        usort($rows, static fn ($a, $b) => strcmp((string) ($b['submitted_at'] ?? $b['created_at'] ?? ''), (string) ($a['submitted_at'] ?? $a['created_at'] ?? '')));
        return $rows;
    }

    private static function countByStage(array $rows): array
    {
        $out = [];
        foreach ((array) settings('pipeline.stages', []) as $s) {
            $out[$s['key']] = 0;
        }
        foreach ($rows as $r) {
            $k = (string) ($r['stage'] ?? 'nouveau');
            $out[$k] = ($out[$k] ?? 0) + 1;
        }
        return $out;
    }

    /** Nettoyage du HTML des articles : balises de mise en forme uniquement. */
    private static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, '<p><br><h2><h3><h4><ul><ol><li><strong><em><b><i><blockquote><a>');
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/href\s*=\s*(["\'])\s*javascript:[^"\']*\1/i', 'href="#"', $html) ?? '';
        return trim($html);
    }

    private static function adminNotFound(): void
    {
        http_response_code(404);
        echo view('admin/404', ['user' => Auth::user(), 'nav' => '', 'title' => 'Introuvable'], 'admin/layout');
    }
}
