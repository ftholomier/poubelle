<?php
declare(strict_types=1);

// Point d'entrée commun : configuration, réponses JSON, session, stockage fichiers.

define('APP_VERSION', '9'); // à garder identique à APP_VERSION dans public/js/app.js
define('APP_ROOT', dirname(__DIR__));
define('SETTINGS_FILE', __DIR__ . '/settings.json'); // réglages faits dans l'appli

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

define('DATA_DIR', resolve_data_dir($CONFIG['data_dir']));
define('USERS_FILE', DATA_DIR . '/users.json');

/** Mentions légales de l'agence, exigées sur le mandat (Paramètres → Identité de l'agence). */
const AGENCE_LEGAL = ['raison_sociale', 'siege', 'siret', 'carte_numero', 'carte_delivree_par', 'garant', 'rcp'];

// Formats audio acceptés (type MIME => extension)
const AUDIO_TYPES = ['audio/webm' => 'webm', 'audio/mp4' => 'm4a', 'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/wav' => 'wav'];

require __DIR__ . '/fields.php';
require __DIR__ . '/ai.php';
require __DIR__ . '/pdf.php';
require __DIR__ . '/mailer.php';

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
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
    session_start();
}

function users(): array
{
    return read_json(USERS_FILE, []);
}

function public_user(array $u): array
{
    return ['id' => $u['id'], 'login' => $u['login'], 'nom' => $u['nom'], 'role' => $u['role'], 'email' => $u['email'] ?? '', 'telephone' => $u['telephone'] ?? ''];
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
    return read_json($file);
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
