<?php
declare(strict_types=1);

namespace App\Core;

/** Jetons CSRF liés à la session (espace pro, back-office). */
final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = Crypto::token(24);
        }
        return (string) $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    public static function check(): bool
    {
        Session::resume();
        $sent = (string) (Request::input('_csrf') ?? Request::header('X-CSRF-Token') ?? '');
        $real = (string) ($_SESSION['_csrf'] ?? '');
        return $real !== '' && $sent !== '' && hash_equals($real, $sent);
    }
}
