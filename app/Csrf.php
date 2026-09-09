<?php
declare(strict_types=1);

namespace App;

/** Jeton CSRF en session, un par formulaire logique. */
final class Csrf
{
    public static function token(string $form = 'default'): string
    {
        Session::start();
        $tokens = Session::get('_csrf', []);
        if (!\is_array($tokens)) {
            $tokens = [];
        }
        if (empty($tokens[$form])) {
            $tokens[$form] = bin2hex(random_bytes(32));
            Session::set('_csrf', $tokens);
        }
        return $tokens[$form];
    }

    public static function check(?string $token, string $form = 'default'): bool
    {
        Session::start();
        $tokens = Session::get('_csrf', []);
        $expected = \is_array($tokens) ? ($tokens[$form] ?? '') : '';
        return \is_string($token) && $token !== '' && \is_string($expected) && $expected !== ''
            && hash_equals($expected, $token);
    }

    public static function field(string $form = 'default'): string
    {
        return '<input type="hidden" name="csrf" value="' . Text::e(self::token($form)) . '">';
    }
}
