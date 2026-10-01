<?php
declare(strict_types=1);

// API JSON de l'appli. Toutes les routes passent par ce fichier : /api/?r=<route>

require __DIR__ . '/../../app/bootstrap.php';

set_time_limit(320);
start_session();

$method = $_SERVER['REQUEST_METHOD'];
$route  = $_GET['r'] ?? '';
$id     = (string) ($_GET['id'] ?? '');

// Protection CSRF : en plus du cookie SameSite=Strict, toute écriture doit venir de notre JS.
if ($method !== 'GET' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'visite-immo') {
    send_json(['error' => 'Requête refusée.'], 403);
}

try {
    match ("$method $route") {
        'GET status'      => route_status(),
        'POST setup'      => route_setup(),
        'POST login'      => route_login(),
        'POST logout'     => route_logout(),
        'POST password'   => route_password(),
        'POST profile'    => route_profile(),
        'GET users'       => route_users_list(),
        'POST users'      => route_users_create(),
        'DELETE users'    => route_users_delete($id),
        'GET settings'    => route_settings_get(),
        'POST settings'   => route_settings_save(),
        'POST models'     => route_models(),
        'POST mailtest'   => route_mail_test(),
        'GET logo'        => route_logo_get(),
        'POST logo'       => route_logo_upload(),
        'DELETE logo'     => route_logo_delete(),
        'GET pdf'         => route_pdf($id),
        'POST send'       => route_send($id),
        'GET fields'      => send_json(SECTIONS),
        'GET visits'      => route_visits_list(),
        'POST visits'     => route_visit_create(),
        'GET visit'       => send_json(load_visit(require_user(), $id)),
        'POST visit'      => route_visit_save($id),
        'DELETE visit'    => route_visit_delete($id),
        'POST chunk'      => route_chunk_upload($id),
        'POST generate'   => route_generate($id),
        'GET audio'       => route_audio_stream($id),
        'DELETE audio'    => route_audio_delete($id),
        default           => fail(404, 'Route inconnue.'),
    };
} catch (HttpError $e) {
    send_json(['error' => $e->getMessage()], $e->getCode());
} catch (Throwable $e) {
    error_log((string) $e);
    send_json(['error' => $e->getMessage()], 500);
}

// ---------- Compte ----------

function route_status(): never
{
    global $CONFIG;
    $u = current_user();
    // Filet de sécurité : s'il n'existe plus aucun administrateur, le compte connecté le devient
    if ($u && !in_array('admin', array_column(users(), 'role'), true)) {
        update_json(USERS_FILE, fn (array $users) => array_map(fn ($x) => $x['id'] === $u['id'] ? [...$x, 'role' => 'admin'] : $x, $users));
        $u['role'] = 'admin';
    }
    send_json([
        'setup' => count(users()) === 0,
        'user'  => $u ? public_user($u) : null,
        'demo'  => empty($CONFIG['gemini_api_key']),
        'email' => email_configure(),
        'agence' => $CONFIG['agence'],
        'version' => APP_VERSION,
    ]);
}

function validate_new_user(array $in): array
{
    $login = strtolower(trim((string) ($in['login'] ?? '')));
    $nom = trim((string) ($in['nom'] ?? ''));
    $password = (string) ($in['password'] ?? '');
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $login)) fail(400, 'Identifiant : 3 à 30 caractères (lettres, chiffres, . _ -).');
    if ($nom === '') fail(400, 'Le nom est obligatoire.');
    if (strlen($password) < 8) fail(400, 'Mot de passe : 8 caractères minimum.');
    return [$login, $nom, $password];
}

function new_user(string $login, string $nom, string $password, string $role): array
{
    return [
        'id'       => 'u' . bin2hex(random_bytes(6)),
        'login'    => $login,
        'nom'      => $nom,
        'role'     => $role,
        'hash'     => password_hash($password, PASSWORD_DEFAULT),
        'cree_le'  => date('c'),
    ];
}

/** Premier lancement : création du compte administrateur. */
function route_setup(): never
{
    [$login, $nom, $password] = validate_new_user(json_input());
    $user = null;
    update_json(USERS_FILE, function (array $users) use ($login, $nom, $password, &$user) {
        if ($users) fail(403, "L'appli est déjà configurée.");
        $user = new_user($login, $nom, $password, 'admin');
        return [$user];
    });
    session_regenerate_id(true);
    $_SESSION['uid'] = $user['id'];
    send_json(['user' => public_user($user)]);
}

function route_login(): never
{
    $in = json_input();
    $login = strtolower(trim((string) ($in['login'] ?? '')));
    foreach (users() as $u) {
        if ($u['login'] === $login && password_verify((string) ($in['password'] ?? ''), $u['hash'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = $u['id'];
            send_json(['user' => public_user($u)]);
        }
    }
    sleep(1); // ralentit les essais en série
    fail(401, 'Identifiant ou mot de passe incorrect.');
}

function route_logout(): never
{
    $_SESSION = [];
    session_destroy();
    send_json(['ok' => true]);
}

function route_password(): never
{
    $me = require_user();
    $in = json_input();
    if (!password_verify((string) ($in['ancien'] ?? ''), $me['hash'])) fail(400, 'Mot de passe actuel incorrect.');
    if (strlen((string) ($in['nouveau'] ?? '')) < 8) fail(400, 'Nouveau mot de passe : 8 caractères minimum.');
    update_json(USERS_FILE, fn (array $users) => array_map(
        fn ($u) => $u['id'] === $me['id'] ? [...$u, 'hash' => password_hash($in['nouveau'], PASSWORD_DEFAULT)] : $u,
        $users
    ));
    send_json(['ok' => true]);
}

/** E-mail et téléphone de l'agent : utilisés dans les PDF et comme adresse de réponse des e-mails. */
function validate_contact(array $in): array
{
    $email = trim((string) ($in['email'] ?? ''));
    if ($email !== '' && !valid_email($email)) fail(400, 'Adresse e-mail invalide.');
    return ['email' => $email, 'telephone' => mb_substr(trim((string) ($in['telephone'] ?? '')), 0, 30)];
}

function route_profile(): never
{
    $me = require_user();
    $in = json_input();
    $contact = validate_contact($in);
    $nom = trim((string) ($in['nom'] ?? $me['nom']));
    if ($nom === '') fail(400, 'Le nom est obligatoire.');
    update_json(USERS_FILE, fn (array $users) => array_map(fn ($u) => $u['id'] === $me['id'] ? [...$u, ...$contact, 'nom' => $nom] : $u, $users));
    send_json(public_user([...$me, ...$contact, 'nom' => $nom]));
}

// ---------- Utilisateurs (admin) ----------

function route_users_list(): never
{
    require_admin();
    send_json(array_map('public_user', users()));
}

function route_users_create(): never
{
    require_admin();
    $in = json_input();
    [$login, $nom, $password] = validate_new_user($in);
    $role = ($in['role'] ?? '') === 'admin' ? 'admin' : 'agent';
    $contact = validate_contact($in);
    $user = null;
    update_json(USERS_FILE, function (array $users) use ($login, $nom, $password, $role, $contact, &$user) {
        foreach ($users as $u) if ($u['login'] === $login) fail(400, 'Cet identifiant existe déjà.');
        $user = new_user($login, $nom, $password, $role) + $contact;
        $users[] = $user;
        return $users;
    });
    send_json(public_user($user));
}

function route_users_delete(string $id): never
{
    $me = require_admin();
    if ($id === $me['id']) fail(400, 'Vous ne pouvez pas supprimer votre propre compte.');
    update_json(USERS_FILE, fn (array $users) => array_values(array_filter($users, fn ($u) => $u['id'] !== $id)));
    if (preg_match('/^u[a-f0-9]{12}$/', $id)) rrmdir(DATA_DIR . '/visites/' . $id); // ses visites partent avec lui
    send_json(['ok' => true]);
}

// ---------- Réglages (admin) ----------

function settings_view(): array
{
    global $CONFIG;
    $key = (string) $CONFIG['gemini_api_key'];
    return [
        'cle_configuree'       => $key !== '',
        'cle_apercu'           => $key !== '' ? '…' . substr($key, -4) : '',
        'modele_analyse'       => $CONFIG['modele_analyse'],
        'modele_transcription' => $CONFIG['modele_transcription'],
        'data_dir'             => $CONFIG['data_dir'],
        'data_dir_absolu'      => DATA_DIR,
        'agence'               => $CONFIG['agence'],
        'agence_coordonnees'   => $CONFIG['agence_coordonnees'] ?? '',
        'couleur'              => $CONFIG['couleur'] ?? '#14213d',
        'logo'                 => logo_path() !== null,
        'email_methode'        => $CONFIG['email_methode'] ?? '',
        'email_expediteur'     => $CONFIG['email_expediteur'] ?? '',
        'email_expediteur_nom' => $CONFIG['email_expediteur_nom'] ?? '',
        'smtp_host'            => $CONFIG['smtp_host'] ?? '',
        'smtp_port'            => $CONFIG['smtp_port'] ?? 587,
        'smtp_securite'        => $CONFIG['smtp_securite'] ?? 'tls',
        'smtp_user'            => $CONFIG['smtp_user'] ?? '',
        'smtp_pass_configure'  => !empty($CONFIG['smtp_pass']),
        'email_configure'      => email_configure(),
    ];
}

function route_settings_get(): never
{
    require_admin();
    send_json(settings_view());
}

/** Liste des modèles Gemini, avec la clé saisie (pour la vérifier) ou la clé enregistrée. */
function route_models(): never
{
    global $CONFIG;
    require_admin();
    $key = trim((string) (json_input()['cle'] ?? '')) ?: (string) $CONFIG['gemini_api_key'];
    if ($key === '') fail(400, "Saisissez d'abord une clé API Gemini.");
    try {
        send_json(gemini_models($key));
    } catch (RuntimeException $e) {
        fail(400, $e->getMessage());
    }
}

function route_settings_save(): never
{
    global $CONFIG;
    require_admin();
    $in = json_input();
    $settings = read_json(SETTINGS_FILE, []);

    $cle = trim((string) ($in['cle'] ?? ''));
    if ($cle !== '') $settings['gemini_api_key'] = $cle; // vide = on garde la clé actuelle
    if (!empty($in['supprimer_cle'])) $settings['gemini_api_key'] = '';

    foreach (['modele_analyse', 'modele_transcription'] as $k) {
        if (!isset($in[$k])) continue;
        $model = trim((string) $in[$k]);
        if (!preg_match('/^[A-Za-z0-9._-]{2,80}$/', $model)) fail(400, 'Nom de modèle invalide.');
        $settings[$k] = $model;
    }

    if (isset($in['agence'])) {
        $agence = trim((string) $in['agence']);
        if ($agence === '') fail(400, "Le nom de l'agence est obligatoire.");
        $settings['agence'] = mb_substr($agence, 0, 120);
    }

    if (isset($in['agence_coordonnees'])) $settings['agence_coordonnees'] = mb_substr(trim((string) $in['agence_coordonnees']), 0, 400);
    if (isset($in['couleur'])) {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) $in['couleur'])) fail(400, 'Couleur invalide.');
        $settings['couleur'] = strtolower($in['couleur']);
    }

    // Envoi des e-mails
    if (isset($in['email_methode'])) {
        $methode = in_array($in['email_methode'], ['smtp', 'mail'], true) ? $in['email_methode'] : '';
        $settings['email_methode'] = $methode;
        $exp = trim((string) ($in['email_expediteur'] ?? ''));
        if ($methode !== '' && !valid_email($exp)) fail(400, "Adresse de l'expéditeur invalide.");
        $settings['email_expediteur'] = $exp;
        $settings['email_expediteur_nom'] = mb_substr(trim((string) ($in['email_expediteur_nom'] ?? '')), 0, 80);
        $settings['smtp_host'] = trim((string) ($in['smtp_host'] ?? ''));
        $settings['smtp_port'] = (int) ($in['smtp_port'] ?? 587) ?: 587;
        $settings['smtp_securite'] = in_array($in['smtp_securite'] ?? '', ['ssl', 'tls', 'aucune'], true) ? $in['smtp_securite'] : 'tls';
        $settings['smtp_user'] = trim((string) ($in['smtp_user'] ?? ''));
        if ((string) ($in['smtp_pass'] ?? '') !== '') $settings['smtp_pass'] = (string) $in['smtp_pass']; // vide = on garde
        if ($methode === 'smtp' && $settings['smtp_host'] === '') fail(400, 'Indiquez le serveur SMTP.');
    }

    if (isset($in['data_dir']) && trim((string) $in['data_dir']) !== (string) $CONFIG['data_dir']) {
        move_data_dir(trim((string) $in['data_dir']));
        $settings['data_dir'] = trim((string) $in['data_dir']);
    }

    if (!is_writable(dirname(SETTINGS_FILE))) fail(500, 'Le dossier app/ doit être accessible en écriture pour enregistrer les réglages.');
    write_json(SETTINGS_FILE, $settings);
    @chmod(SETTINGS_FILE, 0600);

    $CONFIG = array_merge($CONFIG, $settings);
    send_json(settings_view());
}

/** Change le dossier de stockage en y déplaçant les données existantes (comptes et visites). */
function move_data_dir(string $path): void
{
    if ($path === '') fail(400, 'Le dossier de stockage est obligatoire.');
    $new = resolve_data_dir($path);
    if (!is_dir($new) && !@mkdir($new, 0770, true)) fail(400, "Impossible de créer le dossier $new.");
    $new = realpath($new);
    $old = realpath(DATA_DIR) ?: DATA_DIR;
    if ($new === $old) return;

    $public = realpath(APP_ROOT . '/public');
    if (str_starts_with($new . '/', $public . '/')) fail(400, 'Le dossier de stockage ne doit pas être dans public/ : il serait accessible depuis le web.');
    if (str_starts_with($new . '/', $old . '/')) fail(400, "Le nouveau dossier ne peut pas être à l'intérieur de l'actuel.");
    if (!is_writable($new)) fail(400, "PHP n'a pas le droit d'écrire dans $new.");
    if (is_file("$new/users.json")) fail(400, 'Ce dossier contient déjà des données Visite Immo.');

    copy_dir($old, $new);
    foreach (array_diff(scandir($old), ['.', '..', '.htaccess']) as $f) {
        is_dir("$old/$f") ? rrmdir("$old/$f") : unlink("$old/$f");
    }
}

function copy_dir(string $from, string $to): void
{
    if (!is_dir($to)) mkdir($to, 0770, true);
    foreach (array_diff(scandir($from), ['.', '..']) as $f) {
        if (str_ends_with($f, '.lock')) continue;
        if (is_dir("$from/$f")) copy_dir("$from/$f", "$to/$f");
        elseif (!copy("$from/$f", "$to/$f")) fail(500, "Copie impossible : $from/$f");
    }
}

// ---------- Logo ----------

function route_logo_get(): never
{
    require_user();
    $path = logo_path() ?? fail(404, 'Aucun logo.');
    header('Content-Type: ' . (str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg'));
    header('Cache-Control: no-cache');
    readfile($path);
    exit;
}

/** Logo de l'agence : PNG ou JPG tels quels ; WebP et GIF convertis en PNG (GD). */
function route_logo_upload(): never
{
    require_admin();
    $f = $_FILES['logo'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) fail(400, "Envoi du logo incomplet.");
    if ($f['size'] > 5 * 1024 * 1024) fail(413, 'Logo trop lourd (5 Mo maximum).');
    $type = (string) (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $dir = DATA_DIR . '/marque';
    if (!is_dir($dir)) mkdir($dir, 0770, true);
    foreach (glob("$dir/logo.*") ?: [] as $old) unlink($old);

    if ($type === 'image/png' || $type === 'image/jpeg') {
        move_uploaded_file($f['tmp_name'], $dir . '/logo.' . ($type === 'image/png' ? 'png' : 'jpg'));
    } elseif (in_array($type, ['image/webp', 'image/gif'], true) && function_exists('imagecreatefromstring')) {
        $img = imagecreatefromstring((string) file_get_contents($f['tmp_name'])) ?: fail(415, 'Image illisible.');
        imagesavealpha($img, true);
        imagepng($img, "$dir/logo.png");
    } else {
        fail(415, 'Format non pris en charge : envoyez un PNG ou un JPG (le SVG n\'est pas accepté par les PDF).');
    }
    // Les PDF n'acceptent pas les PNG entrelacés ni en 16 bits : on les réenregistre si besoin
    if (is_file("$dir/logo.png") && function_exists('imagecreatefrompng')) {
        $data = (string) file_get_contents("$dir/logo.png");
        if (strlen($data) > 28 && (ord($data[28]) === 1 || ord($data[24]) > 8)) { // entrelacé ou 16 bits
            $img = imagecreatefrompng("$dir/logo.png");
            imagesavealpha($img, true);
            imageinterlace($img, false);
            imagepng($img, "$dir/logo.png");
        }
    }
    send_json(['ok' => true]);
}

function route_logo_delete(): never
{
    require_admin();
    foreach (glob(DATA_DIR . '/marque/logo.*') ?: [] as $f) unlink($f);
    send_json(['ok' => true]);
}

// ---------- PDF ----------

function route_pdf(string $id): never
{
    $me = require_user();
    $visit = load_visit($me, $id);
    [$bin, $nom] = build_pdf((string) ($_GET['doc'] ?? ''), $visit, $me);
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($bin));
    header(($_GET['dl'] ?? '') ? 'Content-Disposition: attachment; filename="' . $nom . '"' : 'Content-Disposition: inline; filename="' . $nom . '"');
    header('Cache-Control: private, no-cache');
    echo $bin;
    exit;
}

// ---------- E-mails ----------

function route_mail_test(): never
{
    global $CONFIG;
    $me = require_admin();
    $to = trim((string) (json_input()['to'] ?? ''));
    if (!valid_email($to)) fail(400, 'Adresse de test invalide.');
    $texte = "Bonjour,\n\nCet e-mail confirme que l'envoi depuis Visite Immo fonctionne.\n\nBonne journée,\n{$me['nom']}";
    [$html, $images] = email_html($texte, $me);
    try {
        send_email(['to' => $to, 'sujet' => 'Test d\'envoi · ' . $CONFIG['agence'], 'texte' => $texte, 'html' => $html, 'images' => $images]);
    } catch (RuntimeException $e) {
        fail(502, $e->getMessage());
    }
    send_json(['ok' => true]);
}

/** Envoi d'un ou plusieurs documents PDF en pièces jointes. */
function route_send(string $id): never
{
    $me = require_user();
    $visit = load_visit($me, $id);
    $in = json_input();
    $to = trim((string) ($in['to'] ?? ''));
    if (!valid_email($to)) fail(400, 'Adresse e-mail du destinataire invalide.');
    $docs = array_values(array_intersect(array_keys(PDF_DOCS), (array) ($in['docs'] ?? [])));
    if (!$docs) fail(400, 'Choisissez au moins un document à joindre.');
    $sujet = trim((string) ($in['sujet'] ?? '')) ?: 'Votre visite · ' . titre_bien($visit);
    $texte = trim((string) ($in['message'] ?? ''));
    if ($texte === '') fail(400, 'Le message est vide.');

    $pieces = [];
    foreach ($docs as $doc) {
        [$bin, $nom] = build_pdf($doc, $visit, $me);
        $pieces[] = [$nom, $bin, 'application/pdf'];
    }
    [$html, $images] = email_html($texte, $me);
    $bcc = !empty($in['copie']) && valid_email($me['email'] ?? '') ? [$me['email']] : [];
    try {
        send_email([
            'to' => $to, 'bcc' => $bcc, 'reply_to' => $me['email'] ?? '',
            'sujet' => $sujet, 'texte' => $texte, 'html' => $html, 'images' => $images, 'pieces' => $pieces,
        ]);
    } catch (RuntimeException $e) {
        fail(502, $e->getMessage());
    }

    $visit = update_visit($me, $id, function (array $v) use ($to, $docs, $sujet, $bcc) {
        $v['envois'][] = ['date' => date('c'), 'a' => $to, 'docs' => $docs, 'sujet' => $sujet, 'copie' => (bool) $bcc];
        return $v;
    });
    send_json($visit);
}

// ---------- Visites ----------

function route_visits_list(): never
{
    $me = require_user();
    $list = [];
    foreach (glob(visits_dir($me) . '/*/visite.json') ?: [] as $file) {
        $v = read_json($file, null);
        if ($v) $list[] = visit_summary($v);
    }
    usort($list, fn ($a, $b) => strcmp($b['cree_le'], $a['cree_le']));
    send_json($list);
}

function route_visit_create(): never
{
    $me = require_user();
    $in = json_input();
    if (empty($in['consentement'])) fail(400, "L'accord du vendeur pour l'enregistrement est obligatoire.");

    $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $visit = [
        'id'              => $id,
        'titre'           => trim((string) ($in['titre'] ?? '')),
        'statut'          => 'enregistrement',
        'cree_le'         => date('c'),
        'modifie_le'      => date('c'),
        'consentement_le' => date('c'),
        'morceaux'        => [],
        'audio_supprime'  => false,
        'fiche'           => ['champs' => new stdClass()],
        'titre_annonce'   => '',
        'annonce'         => '',
        'rapport_agent'   => '',
        'rapport_vendeur' => '',
        'genere_le'       => null,
        'erreur'          => null,
    ];
    write_json(visit_dir($me, $id) . '/visite.json', $visit);
    send_json($visit);
}

/** Sauvegarde des modifications faites par l'agent. */
function route_visit_save(string $id): never
{
    $me = require_user();
    $in = json_input();
    $keys = field_keys();
    $visit = update_visit($me, $id, function (array $v) use ($in, $keys) {
        foreach (['titre', 'titre_annonce', 'annonce', 'rapport_agent', 'rapport_vendeur'] as $k) {
            if (isset($in[$k]) && is_string($in[$k])) $v[$k] = $in[$k];
        }
        if (isset($in['champs']) && is_array($in['champs'])) {
            $champs = (array) $v['fiche']['champs'];
            foreach ($in['champs'] as $cle => $valeur) {
                if (!in_array($cle, $keys, true)) continue;
                $valeur = trim((string) $valeur);
                $avant = $champs[$cle]['valeur'] ?? '';
                if ($valeur === $avant) continue;
                if ($valeur === '') unset($champs[$cle]);
                else $champs[$cle] = ['valeur' => $valeur, 'citation' => '', 'source' => 'agent'];
            }
            $v['fiche']['champs'] = $champs ?: new stdClass();
        }
        return $v;
    });
    send_json($visit);
}

function route_visit_delete(string $id): never
{
    $me = require_user();
    load_visit($me, $id); // vérifie l'existence
    rrmdir(visit_dir($me, $id));
    send_json(['ok' => true]);
}

// ---------- Audio ----------

/** Réception d'un morceau d'enregistrement (envoyé toutes les ~3 min pendant la visite) puis transcription. */
function route_chunk_upload(string $id): never
{
    $me = require_user();
    $n = (int) ($_GET['n'] ?? -1);
    $duree = max(0, (int) ($_GET['duree'] ?? 0));
    if ($n < 0 || $n > 999) fail(400, 'Numéro de morceau invalide.');

    $file = $_FILES['audio'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $code = $file['error'] ?? -1;
        fail(400, in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'Morceau trop volumineux pour le serveur.' : "Envoi de l'audio incomplet ($code).");
    }
    if ($file['size'] > 24 * 1024 * 1024) fail(413, 'Morceau trop volumineux (24 Mo max).');

    // Type déclaré par le navigateur, recoupé avec le contenu réel du fichier
    $mime = strtolower(trim(explode(';', (string) ($_POST['type'] ?? $file['type']))[0]));
    if (!isset(AUDIO_TYPES[$mime])) fail(415, "Format audio non pris en charge ($mime).");
    $reel = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!preg_match('#^(audio|video)/|^application/octet-stream$#', $reel)) fail(415, 'Le fichier envoyé n\'est pas un audio.');

    $visit = load_visit($me, $id);
    if (!empty($visit['audio_supprime'])) fail(409, "L'audio de cette visite a été supprimé.");

    $dir = visit_dir($me, $id) . '/audio';
    if (!is_dir($dir)) mkdir($dir, 0770, true);
    $name = sprintf('%03d.%s', $n, AUDIO_TYPES[$mime]);
    move_uploaded_file($file['tmp_name'], "$dir/$name");

    // Transcription immédiate : à la fin de la visite, presque tout est déjà prêt
    $morceau = ['n' => $n, 'fichier' => $name, 'mime' => $mime, 'taille' => $file['size'], 'duree' => $duree, 'transcription' => '', 'statut' => 'transcrit', 'erreur' => null];
    try {
        $morceau['transcription'] = transcribe_audio("$dir/$name", $mime, $n);
    } catch (Throwable $e) {
        $morceau['statut'] = 'erreur'; // l'audio est conservé, on retentera à la génération
        $morceau['erreur'] = $e->getMessage();
    }

    update_visit($me, $id, function (array $v) use ($morceau) {
        $v['morceaux'] = array_values(array_filter($v['morceaux'], fn ($m) => $m['n'] !== $morceau['n']));
        $v['morceaux'][] = $morceau;
        usort($v['morceaux'], fn ($a, $b) => $a['n'] <=> $b['n']);
        if ($v['statut'] === 'enregistrement' || $v['statut'] === 'erreur') $v['statut'] = 'enregistre';
        return $v;
    });
    send_json(['ok' => true, 'n' => $n, 'statut' => $morceau['statut'], 'erreur' => $morceau['erreur']]);
}

function route_audio_stream(string $id): never
{
    $me = require_user();
    $visit = load_visit($me, $id);
    $n = (int) ($_GET['n'] ?? -1);
    $m = current(array_filter($visit['morceaux'], fn ($m) => $m['n'] === $n));
    $path = $m ? visit_dir($me, $id) . '/audio/' . $m['fichier'] : '';
    if (!$m || !is_file($path)) fail(404, 'Audio introuvable.');

    // Prise en charge des requêtes partielles (indispensable pour la lecture sur iPhone)
    $size = filesize($path);
    $start = 0;
    $end = $size - 1;
    if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'] ?? '', $r)) {
        if ($r[1] !== '') $start = (int) $r[1];
        if ($r[2] !== '') $end = min((int) $r[2], $size - 1);
        if ($r[1] === '' && $r[2] !== '') { $start = max(0, $size - (int) $r[2]); $end = $size - 1; }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Type: ' . $m['mime']);
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . ($end - $start + 1));
    header('Cache-Control: private, max-age=3600');
    $fp = fopen($path, 'rb');
    fseek($fp, $start);
    echo fread($fp, $end - $start + 1);
    fclose($fp);
    exit;
}

function route_audio_delete(string $id): never
{
    $me = require_user();
    load_visit($me, $id);
    rrmdir(visit_dir($me, $id) . '/audio');
    $visit = update_visit($me, $id, function (array $v) {
        $v['audio_supprime'] = true;
        return $v;
    });
    send_json($visit);
}

// ---------- Génération de la fiche ----------

function route_generate(string $id): never
{
    $me = require_user();
    $visit = load_visit($me, $id);
    if (!$visit['morceaux']) fail(400, "Aucun enregistrement reçu pour cette visite.");

    // Retente la transcription des morceaux en échec
    $dir = visit_dir($me, $id) . '/audio';
    foreach ($visit['morceaux'] as $i => $m) {
        if ($m['statut'] !== 'erreur' || !is_file("$dir/{$m['fichier']}")) continue;
        try {
            $visit['morceaux'][$i] = [...$m, 'transcription' => transcribe_audio("$dir/{$m['fichier']}", $m['mime'], $m['n']), 'statut' => 'transcrit', 'erreur' => null];
        } catch (Throwable $e) {
            fail(502, 'Transcription impossible pour le moment : ' . $e->getMessage());
        }
    }
    $transcript = full_transcript($visit);
    if (trim($transcript) === '') fail(400, "Rien n'a été entendu dans l'enregistrement.");

    update_visit($me, $id, function (array $v) use ($visit) {
        $v['morceaux'] = $visit['morceaux'];
        $v['statut'] = 'generation';
        $v['erreur'] = null;
        return $v;
    });

    try {
        $result = generate_documents($transcript, $me, $visit['titre']);
    } catch (Throwable $e) {
        update_visit($me, $id, function (array $v) use ($e) {
            $v['statut'] = 'erreur';
            $v['erreur'] = $e->getMessage();
            return $v;
        });
        fail(502, $e->getMessage());
    }

    $visit = update_visit($me, $id, function (array $v) use ($result) {
        // Les champs corrigés à la main par l'agent ne sont jamais écrasés par l'IA
        $champs = array_filter((array) $v['fiche']['champs'], fn ($c) => ($c['source'] ?? '') === 'agent');
        foreach ($result['champs'] ?? [] as $c) {
            if (!isset($champs[$c['cle']]) && trim($c['valeur']) !== '') {
                $champs[$c['cle']] = ['valeur' => trim($c['valeur']), 'citation' => $c['citation'], 'source' => 'ia'];
            }
        }
        $v['fiche']['champs'] = $champs ?: new stdClass();
        foreach (['titre_annonce', 'annonce', 'rapport_agent', 'rapport_vendeur'] as $k) $v[$k] = $result[$k] ?? '';
        if ($v['titre'] === '' && isset($champs['adresse'])) $v['titre'] = $champs['adresse']['valeur'];
        $v['statut'] = 'pret';
        $v['genere_le'] = date('c');
        return $v;
    });
    send_json($visit);
}
