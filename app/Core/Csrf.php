<?php
declare(strict_types=1);

namespace App\Core;

/** Jeton CSRF par formulaire, comparé en temps constant. */
final class Csrf
{
    private const KEY = '_csrf';

    public static function token(string $form = 'default'): string
    {
        $tokens = Session::get(self::KEY, []);
        if (!is_array($tokens)) {
            $tokens = [];
        }
        if (empty($tokens[$form])) {
            $tokens[$form] = bin2hex(random_bytes(32));
            Session::set(self::KEY, $tokens);
        }
        return $tokens[$form];
    }

    public static function field(string $form = 'default'): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token($form)) . '">'
             . '<input type="hidden" name="_form" value="' . e($form) . '">';
    }

    public static function check(Request $request): bool
    {
        $form = $request->input('_form', 'default') ?? 'default';
        $sent = (string) $request->input('_csrf', '');
        $tokens = Session::get(self::KEY, []);
        $expected = is_array($tokens) ? (string) ($tokens[$form] ?? '') : '';

        return $expected !== '' && $sent !== '' && hash_equals($expected, $sent);
    }
}
