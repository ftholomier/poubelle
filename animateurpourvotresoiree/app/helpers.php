<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;
use App\Core\Str;
use App\Core\Url;

/** Échappement HTML (à utiliser pour toute donnée affichée). */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Échappement pour un attribut JSON (data-*). */
function ej(mixed $value): string
{
    return e(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
}

/** JSON sûr à insérer dans une balise <script>. */
function js(mixed $value): string
{
    return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function url(string $path = '/', array $query = []): string
{
    return Url::to($path, $query);
}

function asset(string $path): string
{
    return Url::asset($path);
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

/** Ancienne valeur d'un champ de formulaire (après erreur de validation). */
function old(string $key, mixed $default = ''): mixed
{
    $old = Session::active() ? Session::old()['input'] : [];
    return $old[$key] ?? $default;
}

function field_error(string $key): string
{
    $errors = Session::active() ? Session::old()['errors'] : [];
    return isset($errors[$key]) ? '<span class="field-error">' . e($errors[$key]) . '</span>' : '';
}

/** Nombre au format français : 2 480 */
function nf(int|float|string|null $n, int $dec = 0): string
{
    return number_format((float) $n, $dec, ',', "\u{202F}");
}

function plural(int|float $n, string $one, ?string $many = null): string
{
    return Str::plural($n, $one, $many);
}

/** Date au format français. */
function date_fr(?string $date, string $format = 'd MMMM yyyy'): string
{
    if (!$date) {
        return '';
    }
    $ts = is_numeric($date) ? (int) $date : strtotime($date);
    if (!$ts) {
        return (string) $date;
    }
    static $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    static $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    return match ($format) {
        'short' => date('d/m/Y', $ts),
        'datetime' => date('d/m/Y à H:i', $ts),
        'time' => date('H:i', $ts),
        'month' => $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts),
        'long' => $days[(int) date('w', $ts)] . ' ' . (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts),
        default => (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts),
    };
}

/** « il y a 3 h », « hier », « le 12 mars » */
function ago(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return '';
    }
    $d = time() - $ts;
    if ($d < 60) {
        return "à l'instant";
    }
    if ($d < 3600) {
        return 'il y a ' . intdiv($d, 60) . ' min';
    }
    if ($d < 86400) {
        return 'il y a ' . intdiv($d, 3600) . ' h';
    }
    if ($d < 172800) {
        return 'hier';
    }
    if ($d < 30 * 86400) {
        return 'il y a ' . intdiv($d, 86400) . ' j';
    }
    return 'le ' . date_fr($date);
}

/** Icône SVG en ligne (jeu d'icônes maison, trait 2px). */
function icon(string $name, int $size = 18, string $class = ''): string
{
    return App\Services\Icons::svg($name, $size, $class);
}

function setting(string $key, mixed $default = null): mixed
{
    return App\Services\Settings::get($key, $default);
}

function is_admin_path(): bool
{
    $admin = '/' . trim((string) Env::get('ADMIN_PATH', 'gestion'), '/');
    $p = App\Core\Request::path();
    return $p === $admin || str_starts_with($p, $admin . '/');
}
