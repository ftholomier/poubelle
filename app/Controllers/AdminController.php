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
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            // Deux compteurs : par adresse IP seule, et par compte visé.
            // L'empreinte visiteur inclut le User-Agent, qu'un attaquant
            // change à chaque essai : elle ne convient pas ici.
            $byIp = RateLimit::hit('login', 20, 900, 'ip:' . hash('sha256', client_ip()));
            $byAccount = RateLimit::hit('login', 8, 900, 'compte:' . hash('sha256', $email));

            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $error = 'Session expirée, merci de réessayer.';
            } elseif (!$byIp || !$byAccount) {
                $error = 'Trop de tentatives de connexion. Réessayez dans un quart d’heure.';
            } elseif (Auth::attempt($email, (string) ($_POST['password'] ?? ''))) {
                redirect(url(Auth::mustChangePassword() ? 'admin/premiere-connexion' : 'admin'));
            } else {
                $error = 'Identifiants incorrects.';
            }
        }
        echo view('admin/login', ['error' => $error], 'admin/layout-bare');
    }

    public static function logout(): void
    {
        // En POST avec jeton : un simple <img src="/admin/logout"> déposé
        // ailleurs suffirait sinon à déconnecter l'équipe à distance.
        if (is_post() && Csrf::check($_POST['_csrf'] ?? null)) {
            Auth::logout();
        }
        redirect(url('admin/login'));
    }

    /**
     * Changement de mot de passe imposé à la première connexion.
     * Toutes les autres routes du back-office y renvoient tant qu'il n'a
     * pas eu lieu : le mot de passe d'installation circule en clair dans
     * data/PREMIERE-CONNEXION.txt.
     */
    /**
     * Demande de réinitialisation.
     *
     * Le message affiché est le même que l'adresse existe ou non : ce
     * formulaire ne doit pas permettre de dresser la liste des comptes.
     */
    public static function forgotPassword(): void
    {
        if (Auth::check()) {
            redirect(url('admin'));
        }
        $error = null;
        $envoye = false;

        if (is_post()) {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            // Deux compteurs, comme pour la connexion : par adresse IP pour
            // freiner l'envoi en masse, par compte pour qu'une personne ne
            // soit pas inondée de messages.
            $parIp = RateLimit::hit('reset', 10, 3600, 'ip:' . hash('sha256', client_ip()));
            $parCompte = RateLimit::hit('reset', 4, 3600, 'compte:' . hash('sha256', $email));

            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $error = 'Session expirée, merci de réessayer.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Adresse e-mail invalide.';
            } elseif (!$parIp || !$parCompte) {
                $error = 'Trop de demandes envoyées. Réessayez dans une heure.';
            } else {
                PasswordReset::demander($email);
                $envoye = true;
            }
        }

        echo view('admin/forgot-password', [
            'error' => $error,
            'envoye' => $envoye,
        ], 'admin/layout-bare');
    }

    /** Saisie du nouveau mot de passe, depuis le lien reçu par e-mail. */
    public static function resetPassword(): void
    {
        // Explicitement POST puis GET : $_REQUEST inclurait aussi les
        // cookies, qu'un tiers peut poser sur le domaine.
        $jeton = (string) ($_POST['jeton'] ?? $_GET['jeton'] ?? '');
        $error = null;
        $valide = PasswordReset::trouver($jeton) !== null;

        if ($valide && is_post()) {
            $pass = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirm'] ?? '');
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $error = 'Session expirée, merci de réessayer.';
            } elseif (mb_strlen($pass) < PasswordReset::LONGUEUR_MIN) {
                $error = 'Choisissez un mot de passe d’au moins ' . PasswordReset::LONGUEUR_MIN . ' caractères.';
            } elseif ($pass !== $confirm) {
                $error = 'Les deux saisies ne correspondent pas.';
            } elseif (!PasswordReset::consommer($jeton, $pass)) {
                $error = 'Ce lien n’est plus valable. Demandez-en un nouveau.';
                $valide = false;
            } else {
                // La session éventuellement ouverte ici ne doit pas survivre
                // à un changement demandé depuis un autre appareil.
                Auth::logout();
                @unlink(DATA_DIR . '/' . PasswordReset::FICHIER_REPLI);
                Session::flash('Mot de passe enregistré. Vous pouvez vous connecter.');
                redirect(url('admin/login'));
            }
        }

        echo view('admin/reset-password', [
            'error' => $error,
            'valide' => $valide,
            'jeton' => $jeton,
        ], 'admin/layout-bare');
    }

    public static function firstLogin(): void
    {
        $user = Auth::requireLogin();
        if (!Auth::mustChangePassword()) {
            redirect(url('admin'));
        }
        $error = null;

        if (is_post()) {
            $pass = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirm'] ?? '');
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $error = 'Session expirée, merci de réessayer.';
            } elseif (mb_strlen($pass) < 12) {
                $error = 'Choisissez un mot de passe d’au moins 12 caractères.';
            } elseif ($pass !== $confirm) {
                $error = 'Les deux saisies ne correspondent pas.';
            } else {
                Store::update('users', (string) ($user['id'] ?? ''), [
                    'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                    'must_change_password' => false,
                    'password_faible' => false,
                ]);
                @unlink(DATA_DIR . '/PREMIERE-CONNEXION.txt');
                Session::start();
                $_SESSION['admin']['must_change'] = false;
                Session::flash('Mot de passe enregistré. Le fichier d’installation a été supprimé.');
                redirect(url('admin'));
            }
        }

        echo view('admin/first-login', ['error' => $error, 'user' => $user], 'admin/layout-bare');
    }

    // ---------------------------------------------------------- dashboard

    /**
     * Garde commune aux écrans du back-office : session valide, puis
     * changement de mot de passe imposé le cas échéant.
     */
    private static function guard(): array
    {
        // Les écrans du back-office affichent des données personnelles :
        // aucun intermédiaire, ni le cache disque du navigateur, ne doit
        // en conserver une copie après la déconnexion.
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        $user = Auth::requireLogin();
        if (Auth::mustChangePassword()) {
            redirect(url('admin/premiere-connexion'));
        }
        return $user;
    }

    public static function dashboard(): void
    {
        $user = self::guard();
        Housekeeping::maybeRun();   // filet quotidien, même sans trafic public
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

    /**
     * Découpe une liste en pages.
     *
     * Les listes du back-office affichaient l'intégralité de la
     * collection : au bout de quelques milliers de candidatures, la page
     * devient impossible à charger. La pagination borne la mémoire comme
     * le temps de rendu.
     *
     * @param array<int,array> $rows
     * @return array{rows:array<int,array>,page:int,pages:int,total:int,par:int}
     */
    private static function paginer(array $rows, int $par = 50): array
    {
        $total = count($rows);
        $pages = max(1, (int) ceil($total / $par));
        $page = max(1, min($pages, (int) ($_GET['p'] ?? 1)));
        return [
            'rows' => array_slice($rows, ($page - 1) * $par, $par),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'par' => $par,
        ];
    }

    // ------------------------------------------------------- candidatures

    public static function applicationsList(): void
    {
        $user = self::guard();
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

        $pager = self::paginer($rows);

        echo view('admin/applications', [
            'user' => $user,
            'nav' => 'applications',
            'title' => $showDrafts ? 'Candidatures abandonnées' : 'Candidatures',
            'rows' => $pager['rows'],
            'pager' => $pager,
            'stage' => $stage,
            'q' => $q,
            'showDrafts' => $showDrafts,
            'by_stage' => self::countByStage(array_values(array_filter(self::applications(), static fn ($a) => ($a['status'] ?? '') !== 'brouillon'))),
        ], 'admin/layout');
    }

    public static function applicationShow(array $params): void
    {
        $user = self::guard();
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
        self::guard();
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
        self::guard();
        $rows = self::applications();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="candidatures-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
        fputcsv($out, ['ID', 'Date', 'Statut', 'Étape', 'Nom', 'E-mail', 'Téléphone', 'Secteur', 'Situation', 'Disponibilité', 'Expérience', 'Objectif', 'Origine', 'Message'], ';');
        foreach ($rows as $r) {
            fputcsv($out, array_map('csv_safe', [
                $r['id'] ?? '', fr_date((string) ($r['submitted_at'] ?? $r['created_at'] ?? ''), true),
                $r['status'] ?? '', $r['stage'] ?? '', $r['name'] ?? '', $r['email'] ?? '', $r['phone'] ?? '',
                $r['area'] ?? '', $r['situation'] ?? '', $r['availability'] ?? '', $r['experience'] ?? '',
                $r['goal'] ?? '', $r['source'] ?? '', $r['message'] ?? '',
            ]), ';');
        }
        fclose($out);
        exit;
    }

    public static function cv(array $params): void
    {
        self::guard();
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
        $user = self::guard();
        $rows = Store::read('leads');
        usort($rows, static fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        $pager = self::paginer($rows);
        echo view('admin/leads', ['user' => $user, 'nav' => 'leads', 'title' => 'Messages & captures', 'rows' => $pager['rows'], 'pager' => $pager], 'admin/layout');
    }

    public static function leadDelete(array $params): void
    {
        self::guard();
        if (Csrf::check($_POST['_csrf'] ?? null)) {
            Store::delete('leads', (string) ($params['id'] ?? ''));
            Session::flash('Message supprimé.');
        }
        redirect(url('admin/messages'));
    }

    // ------------------------------------------------------------ contenu

    public static function contentEdit(array $params): void
    {
        $user = self::guard();
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
        $user = self::guard();
        $rows = Store::read('posts');
        usort($rows, static fn ($a, $b) => strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? '')));
        $pager = self::paginer($rows, 30);
        echo view('admin/posts', ['user' => $user, 'nav' => 'posts', 'title' => 'Actualités', 'rows' => $pager['rows'], 'pager' => $pager], 'admin/layout');
    }

    public static function postEdit(array $params): void
    {
        $user = self::guard();
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
        self::guard();
        if (Csrf::check($_POST['_csrf'] ?? null)) {
            Store::delete('posts', (string) ($params['id'] ?? ''));
            Session::flash('Article supprimé.');
        }
        redirect(url('admin/actualites'));
    }

    // ---------------------------------------------------------- réglages

    public static function settings(): void
    {
        $user = self::guard();
        if (is_post() && Csrf::check($_POST['_csrf'] ?? null)) {
            $s = Store::read('settings');
            foreach (['site', 'company', 'funnel', 'motion', 'mail'] as $group) {
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
            // Une case décochée n'est pas envoyée par le navigateur : elle vaut
            // « faux ». Mais seul un groupe réellement présent dans la requête
            // est remis à zéro, sinon un formulaire partiel désactiverait en
            // silence des options qu'il n'affiche même pas.
            if (isset($_POST['site'])) {
                $s['site']['indexable'] = isset($_POST['site']['indexable']);
            }
            if (isset($_POST['funnel'])) {
                foreach (['notify_enabled', 'exit_intent', 'sticky_cta', 'cv_upload'] as $flag) {
                    $s['funnel'][$flag] = isset($_POST['funnel'][$flag]);
                }
            }
            // Un champ mot de passe vide signifie « ne change rien » : sans
            // cette exception, ouvrir puis enregistrer les réglages effacerait
            // l'authentification SMTP.
            if (isset($_POST['mail']) && trim((string) ($_POST['mail']['smtp_password'] ?? '')) === '') {
                $s['mail']['smtp_password'] = (string) (Store::read('settings')['mail']['smtp_password'] ?? '');
            }
            if (isset($_POST['motion'])) {
                $s['motion']['glow'] = isset($_POST['motion']['glow']);
                $s['motion']['glow_cycle'] = max(8, min(180, (int) ($_POST['motion']['glow_cycle'] ?? $s['motion']['glow_cycle'] ?? 34)));
            }
            Store::write('settings', $s);
            Session::flash('Réglages enregistrés.');
            redirect(url('admin/reglages'));
        }
        echo view('admin/settings', ['user' => $user, 'nav' => 'settings', 'title' => 'Réglages', 'settings' => Store::read('settings')], 'admin/layout');
    }

    /**
     * Envoi d'un e-mail de test depuis les réglages.
     *
     * Un envoi qui ne part pas ne se voit qu'au moment où un candidat
     * n'est pas rappelé : ce bouton donne le verdict tout de suite, avec
     * le transport réellement employé et le motif exact de l'échec.
     */
    public static function mailTest(): void
    {
        $user = self::guard();
        if (!is_post() || !Csrf::check($_POST['_csrf'] ?? null)) {
            redirect(url('admin/reglages'));
        }

        $destinataire = strtolower(trim((string) ($_POST['destinataire'] ?? '')));
        if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            $destinataire = (string) ($user['email'] ?? '');
        }
        if (!RateLimit::hit('mailtest', 10, 3600, 'compte:' . hash('sha256', (string) ($user['id'] ?? '')))) {
            Session::flash('Trop d’essais d’envoi. Réessayez dans une heure.', 'error');
            redirect(url('admin/reglages'));
        }

        $transport = Mailer::smtpConfigure()
            ? 'le serveur SMTP ' . settings('mail.smtp_host', '')
            : 'la fonction mail() de l’hébergeur';

        $parti = Mailer::send(
            $destinataire,
            'Test d’envoi — back-office Suisse Immo',
            '<h2 style="margin:0 0 12px">L’envoi fonctionne</h2>'
            . '<p>Ce message a été envoyé depuis le back-office par ' . e($transport) . '.</p>'
            . '<p style="font-size:13px;color:#8d99ae">Envoyé le ' . e(date('d/m/Y à H:i')) . '.</p>',
            null,
            true
        );

        if ($parti) {
            Session::flash('E-mail de test envoyé à ' . $destinataire . ' par ' . $transport . '. Vérifiez la réception, indésirables compris.');
        } else {
            $journal = Store::read('maillog');
            $motif = (string) ($journal[0]['error'] ?? 'motif inconnu');
            Session::flash('Échec de l’envoi par ' . $transport . ' — ' . $motif, 'error');
        }
        redirect(url('admin/reglages'));
    }

    public static function users(): void
    {
        $user = self::guard();
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
                    Store::update('users', (string) ($_POST['id'] ?? ''), [
                        'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                        'must_change_password' => false,
                        'password_faible' => mb_strlen($pass) < 12,
                    ]);
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
        $user = self::guard();
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
        $user = self::guard();

        if (is_post() && Csrf::check($_POST['_csrf'] ?? null)) {
            $cfg = Bot::config();
            // Le nom du modèle entre dans l'URL appelée : une valeur
            // fantaisiste est refusée plutôt qu'enregistrée.
            $modele = Bot::modeleValide((string) ($_POST['model'] ?? $cfg['model']));
            if ($modele === null) {
                Session::flash('Nom de modèle invalide : gardez celui proposé par la liste.', 'error');
                redirect(url('admin/bot'));
            }
            $patch = [
                'enabled' => isset($_POST['enabled']),
                'model' => $modele,
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
        self::guard();
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
        self::guard();
        Csrf::guard();
        $payload = request_payload();
        $res = Bot::ask((string) ($payload['question'] ?? ''), (array) ($payload['history'] ?? []));
        Bot::logConversation((string) ($payload['question'] ?? ''), (string) ($res['answer'] ?? $res['error'] ?? ''), (bool) $res['ok'], 'back-office');
        json_out($res, $res['ok'] ? 200 : 422);
    }

    public static function botDocumentAdd(): void
    {
        self::guard();
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
        self::guard();
        if (Csrf::check($_POST['_csrf'] ?? null)) {
            Bot::deleteDocument((string) ($params['id'] ?? ''));
            Session::flash('Document retiré de la base de connaissances.');
        }
        redirect(url('admin/bot'));
    }

    public static function mails(): void
    {
        $user = self::guard();
        $pager = self::paginer(Store::read('maillog'), 50);
        echo view('admin/mails', ['user' => $user, 'nav' => 'mails', 'title' => 'Journal des e-mails', 'rows' => $pager['rows'], 'pager' => $pager], 'admin/layout');
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

    /** Balises conservées dans le corps d'un article. */
    private const HTML_ALLOWED = ['p', 'br', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'blockquote', 'a'];
    /** Schémas d'URL autorisés sur un lien. */
    private const HREF_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Nettoyage du HTML des articles par liste blanche stricte.
     *
     * strip_tags() ne filtre que les balises : il laisse passer tous les
     * attributs de celles qu'il conserve (style, href="data:…", target…).
     * On repasse donc par l'arbre DOM : balise inconnue déballée en
     * gardant son texte, aucun attribut sauf href sur les liens, et
     * seulement vers un schéma sûr.
     */
    private static function sanitizeHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // Le préfixe force l'UTF-8 ; le corps est isolé pour ne pas récupérer
        // le <html><body> ajouté automatiquement par la bibliothèque.
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="si-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('si-root');
        if ($root === null) {
            return '';
        }
        self::sanitizeNode($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    /** Parcours récursif : nettoie ou déballe chaque élément. */
    private static function sanitizeNode(DOMNode $node): void
    {
        // Copie : la liste vivante change pendant qu'on retire des nœuds.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $child->parentNode?->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            // Un <script> ou un <style> part avec son contenu ; toute autre
            // balise inconnue est déballée, son texte étant légitime.
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input'], true)) {
                $child->parentNode?->removeChild($child);
                continue;
            }
            if (!in_array($tag, self::HTML_ALLOWED, true)) {
                self::sanitizeNode($child);
                while ($child->firstChild !== null) {
                    $child->parentNode?->insertBefore($child->firstChild, $child);
                }
                $child->parentNode?->removeChild($child);
                continue;
            }

            // Tous les attributs sautent, href sur <a> est réévalué ensuite.
            $href = $tag === 'a' ? (string) $child->getAttribute('href') : '';
            foreach (iterator_to_array($child->attributes ?? []) as $attr) {
                $child->removeAttribute($attr->nodeName);
            }
            if ($tag === 'a') {
                $safe = self::safeHref($href);
                if ($safe === null) {
                    // Lien inexploitable : on garde le texte, pas l'ancre.
                    self::sanitizeNode($child);
                    while ($child->firstChild !== null) {
                        $child->parentNode?->insertBefore($child->firstChild, $child);
                    }
                    $child->parentNode?->removeChild($child);
                    continue;
                }
                $child->setAttribute('href', $safe);
                if (str_starts_with($safe, 'http')) {
                    $child->setAttribute('rel', 'noopener nofollow');
                    $child->setAttribute('target', '_blank');
                }
            }
            self::sanitizeNode($child);
        }
    }

    /** @return string|null l'URL si son schéma est autorisé, null sinon */
    private static function safeHref(string $href): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // Les caractères de contrôle servent à masquer « javascript: ».
        $href = preg_replace('/[\x00-\x20]/u', '', $href) ?? '';
        if ($href === '') {
            return null;
        }
        if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return $href;
        }
        if (!preg_match('#^([a-z][a-z0-9+.-]*):#i', $href, $m)) {
            return $href;   // relatif
        }
        return in_array(strtolower($m[1]), self::HREF_SCHEMES, true) ? $href : null;
    }

    private static function adminNotFound(): void
    {
        http_response_code(404);
        echo view('admin/404', ['user' => Auth::user(), 'nav' => '', 'title' => 'Introuvable'], 'admin/layout');
    }
}
