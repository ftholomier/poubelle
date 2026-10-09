<?php
declare(strict_types=1);

// Point d'entrée commun : configuration, réponses JSON, session, stockage fichiers.

define('APP_VERSION', '17'); // à garder identique à APP_VERSION dans public/js/app.js
define('APP_ROOT', dirname(__DIR__));
define('SETTINGS_FILE', getenv('VI_SETTINGS') ?: __DIR__ . '/settings.json'); // réglages faits dans l'appli (VI_SETTINGS : tests)

// Valeurs par défaut (config.php si présent, sinon config.sample.php), remplacées par les réglages de l'appli
$configFile = file_exists(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : __DIR__ . '/config.sample.php';
$CONFIG = array_merge(require $configFile, is_file(SETTINGS_FILE) ? (json_decode((string) file_get_contents(SETTINGS_FILE), true) ?: []) : []);

/** Chemin absolu du dossier de stockage (les chemins relatifs partent du dossier de l'appli). */
function resolve_data_dir(string $path): string
{
    $path = rtrim(trim($path), '/\\');
    $absolu = str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[/\\\\]#', $path);
    return $absolu ? $path : APP_ROOT . '/' . $path;
}

define('DATA_DIR', resolve_data_dir(getenv('VI_DATA_DIR') ?: $CONFIG['data_dir'])); // VI_DATA_DIR : tests
define('USERS_FILE', DATA_DIR . '/users.json');

/** Mentions légales de l'agence, exigées sur le mandat (Paramètres → Identité de l'agence). */
const AGENCE_LEGAL = ['raison_sociale', 'siege', 'siret', 'carte_numero', 'carte_delivree_par', 'garant', 'rcp'];

/**
 * En-têtes de sécurité envoyés par toutes les pages et l'API :
 *  - nosniff : un fichier déposé n'est jamais interprété autrement que son type déclaré ;
 *  - Referrer-Policy : les liens personnels (/espace/?t=…) ne fuient pas vers les sites externes (cartes, Google) ;
 *  - pas d'affichage dans un cadre d'un autre site (signature au doigt, connexion : protection contre le clickjacking) ;
 *  - micro et caméra réservés au site lui-même.
 */
function entetes_securite(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(self), microphone=(self), geolocation=(), payment=()');
    if (est_https()) header('Strict-Transport-Security: max-age=31536000');
}

/** Politique de sécurité du contenu des pages clients (espace, page du bien) : nos scripts seulement. */
function csp_pages_clients(array $scriptsEnLigne = []): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    $hash = implode(' ', array_map(fn ($js) => "'sha256-" . base64_encode(hash('sha256', $js, true)) . "'", $scriptsEnLigne));
    header("Content-Security-Policy: default-src 'self'; script-src 'self' $hash; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:"
        . "; connect-src 'self'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'");
}

/** HTTPS, y compris derrière un proxy ou un hébergeur qui termine le TLS. */
function est_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

entetes_securite();

/** Sources d'une valeur que l'IA ne doit jamais écraser : saisie, dictée, lue dans un document, donnée publique. */
const SOURCES_VALIDEES = ['agent', 'dialogue', 'document', 'public'];

// Formats audio acceptés (type MIME => extension)
const AUDIO_TYPES = ['audio/webm' => 'webm', 'audio/mp4' => 'm4a', 'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/wav' => 'wav'];

/**
 * Adresse d'un service extérieur. Surchargeable par variable d'environnement (VI_API_<NOM>, utilisé par les tests)
 * ou par le réglage api_<nom>. Permet de pointer vers un service de test sans toucher au code.
 */
function api_base(string $nom, string $defaut): string
{
    global $CONFIG;
    return rtrim((string) (getenv('VI_API_' . strtoupper($nom)) ?: ($CONFIG['api_' . $nom] ?? $defaut)), '/');
}

require __DIR__ . '/fields.php';
require __DIR__ . '/ai.php';
require __DIR__ . '/pdf.php';
require __DIR__ . '/mandat.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/store.php';
// Modules : ceux qui fournissent des fonctions d'enregistrement d'abord, puis les autres par ordre alphabétique
$modules = glob(__DIR__ . '/modules/*.php') ?: [];
foreach (['dossier', 'signature', 'espace', 'dictee'] as $m) {
    require __DIR__ . "/modules/$m.php";
    $modules = array_diff($modules, [__DIR__ . "/modules/$m.php"]);
}
foreach ($modules as $module) require $module;
unset($modules, $module, $m);

// ---------- Réponses ----------

class HttpError extends Exception {}

function fail(int $status, string $message): never
{
    throw new HttpError($message, $status);
}

function send_json(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    entete_cout_ia();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Compteur du coût de l'IA, joint à chaque réponse (en-tête X-Cout-IA, euros du mois en cours) pour l'afficher en
 * direct en haut à droite : total de l'agence pour un administrateur, ses propres dépenses pour un agent.
 */
function entete_cout_ia(): void
{
    if (headers_sent() || empty($_SESSION['uid']) || !defined('DATA_DIR')) return;
    try {
        $c = read_json(DATA_DIR . '/couts/' . date('Y-m') . '.json', []);
        $u = current_user();
        if (!$u) return;
        $euros = $u['role'] === 'admin' ? ($c['total'] ?? 0) : ($c['par_agent'][$u['id']] ?? 0);
        header('X-Cout-IA: ' . round((float) $euros, 5) . ';' . ($u['role'] === 'admin' ? 'agence' : 'moi'));
    } catch (Throwable) {
        // le compteur ne doit jamais empêcher une réponse
    }
}

function json_input(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '{}', true);
    return is_array($data) ? $data : [];
}

// ---------- Fichiers JSON (avec verrou pour éviter les écritures concurrentes) ----------

function read_json(string $file, mixed $default = []): mixed
{
    if (!is_file($file)) return $default;
    $data = json_decode((string) file_get_contents($file), true);
    return $data ?? $default;
}

function write_json(string $file, mixed $data): void
{
    if (!is_dir(dirname($file))) mkdir(dirname($file), 0770, true);
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    rename($tmp, $file); // atomique : jamais de fichier à moitié écrit
}

/** Lit, modifie et réécrit un fichier JSON sous verrou exclusif. */
function update_json(string $file, callable $fn, mixed $default = []): mixed
{
    if (!is_dir(dirname($file))) mkdir(dirname($file), 0770, true);
    $lock = fopen($file . '.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        $data = $fn(read_json($file, $default));
        write_json($file, $data);
        return $data;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
        $p = "$dir/$f";
        is_dir($p) ? rrmdir($p) : unlink($p);
    }
    rmdir($dir);
}

// ---------- Session & utilisateurs ----------

function start_session(): void
{
    session_name('visite_immo');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path'     => '/',
        'secure'   => est_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
    ini_set('session.use_strict_mode', '1'); // refuse un identifiant de session inventé (fixation de session)
    session_start();
}

function users(): array
{
    return read_json(USERS_FILE, []);
}

function public_user(array $u): array
{
    return ['id' => $u['id'], 'login' => $u['login'], 'nom' => $u['nom'], 'role' => $u['role'], 'email' => $u['email'] ?? '', 'telephone' => $u['telephone'] ?? '', 'cr_auto' => $u['cr_auto'] ?? true];
}

function current_user(): ?array
{
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return null;
    foreach (users() as $u) if ($u['id'] === $id) return $u;
    return null; // compte supprimé entre-temps
}

function require_user(): array
{
    $user = current_user() ?? fail(401, 'Session expirée, reconnectez-vous.');
    // Libère le verrou de session : les envois audio et la génération peuvent tourner en parallèle
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    return $user;
}

function require_admin(): array
{
    $u = require_user();
    if ($u['role'] !== 'admin') fail(403, 'Réservé aux administrateurs.');
    return $u;
}

// ---------- Visites ----------

function valid_id(string $id): bool
{
    return (bool) preg_match('/^[a-z0-9-]{6,40}$/', $id);
}

function visits_dir(array $user): string
{
    return DATA_DIR . '/visites/' . $user['id'];
}

function visit_dir(array $user, string $id): string
{
    if (!valid_id($id)) fail(400, 'Identifiant de visite invalide.');
    return visits_dir($user) . '/' . $id;
}

function load_visit(array $user, string $id): array
{
    $file = visit_dir($user, $id) . '/visite.json';
    if (!is_file($file)) fail(404, 'Visite introuvable.');
    $v = read_json($file);
    $v['agent'] ??= $user['id'];
    return $v;
}

function update_visit(array $user, string $id, callable $fn): array
{
    $file = visit_dir($user, $id) . '/visite.json';
    if (!is_file($file)) fail(404, 'Visite introuvable.');
    return update_json($file, function (array $v) use ($fn) {
        $v = $fn($v);
        $v['modifie_le'] = date('c');
        return $v;
    });
}

/** Version allégée pour la liste des visites. */
function visit_summary(array $v): array
{
    $champs = $v['fiche']['champs'] ?? [];
    return [
        'id'         => $v['id'],
        'titre'      => $v['titre'] ?: ($champs['adresse']['valeur'] ?? 'Visite sans titre'),
        'statut'     => $v['statut'],
        'cree_le'    => $v['cree_le'],
        'duree'      => array_sum(array_column($v['morceaux'], 'duree')),
        'audio'      => !empty($v['morceaux']) && empty($v['audio_supprime']),
        'type_bien'  => $champs['type_bien']['valeur'] ?? null,
        'ville'      => $champs['ville']['valeur'] ?? null,
        'prix'       => $champs['prix_souhaite']['valeur'] ?? null,
        'completude' => completude($champs),
        'etape'      => function_exists('etape_dossier') ? etape_dossier($v) : null,
        'photo'      => $v['photos'][0]['fichier'] ?? null,
        'suivi'      => function_exists('suivi_resume') ? suivi_resume($v) : null,
    ];
}

function full_transcript(array $v): string
{
    $parts = [];
    foreach ($v['morceaux'] as $m) {
        if (($m['transcription'] ?? '') !== '') $parts[] = trim($m['transcription']);
    }
    return implode("\n\n", $parts);
}

// ---------- Logo de l'agence ----------

function uploaded_logo_path(): ?string
{
    foreach (['png', 'jpg'] as $ext) {
        if (is_file(DATA_DIR . "/marque/logo.$ext")) return DATA_DIR . "/marque/logo.$ext";
    }
    return null;
}

/** Logo utilisé dans les PDF et les e-mails : celui de l'agence, sinon le logo Synapse fourni. */
function logo_path(): ?string
{
    return uploaded_logo_path() ?? __DIR__ . '/assets/synapse-logo.png';
}
