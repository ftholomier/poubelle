<?php

declare(strict_types=1);

namespace App\Security;

final class Csrf
{
    private const KEY = '_csrf_tokens';
    private const MAX = 12;

    public static function token(string $form = 'default'): string
    {
        Session::start();
        $tokens = Session::get(self::KEY, []);
        if (!is_array($tokens)) {
            $tokens = [];
        }
        if (empty($tokens[$form]) || !is_string($tokens[$form])) {
            $tokens[$form] = bin2hex(random_bytes(32));
        }
        if (count($tokens) > self::MAX) {
            $tokens = array_slice($tokens, -self::MAX, null, true);
        }
        Session::set(self::KEY, $tokens);
        return $tokens[$form];
    }

    public static function field(string $form = 'default'): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token($form), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function check(?string $token, string $form = 'default'): bool
    {
        Session::start();
        $tokens = Session::get(self::KEY, []);
        $expected = is_array($tokens) ? ($tokens[$form] ?? null) : null;
        if (!is_string($expected) || !is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }

    /** Invalide un jeton après usage sensible (login, reset). */
    public static function rotate(string $form = 'default'): void
    {
        Session::start();
        $tokens = Session::get(self::KEY, []);
        if (is_array($tokens)) {
            unset($tokens[$form]);
            Session::set(self::KEY, $tokens);
        }
    }
}
