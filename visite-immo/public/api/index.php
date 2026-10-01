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
        'GET users'       => route_users_list(),
        'POST users'      => route_users_create(),
        'DELETE users'    => route_users_delete($id),
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
    send_json([
        'setup' => count(users()) === 0,
        'user'  => $u ? public_user($u) : null,
        'demo'  => [
            'transcription' => empty($CONFIG['openai_api_key']),
            'analyse'       => empty($CONFIG['anthropic_api_key']),
        ],
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
    $user = null;
    update_json(USERS_FILE, function (array $users) use ($login, $nom, $password, $role, &$user) {
        foreach ($users as $u) if ($u['login'] === $login) fail(400, 'Cet identifiant existe déjà.');
        $user = new_user($login, $nom, $password, $role);
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
