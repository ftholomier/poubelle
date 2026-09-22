<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Response;
use App\Core\Security;
use App\Core\View;

/**
 * Page introuvable : renvoyer le visiteur plutôt que le laisser sur un mur.
 *
 * L'exploitant a choisi la redirection automatique. Elle a un coût qu'il faut
 * connaître : Google appelle « soft 404 » une adresse morte qui répond 302 et
 * peut garder l'ancienne URL en index faute d'avoir vu qu'elle n'existait
 * plus. Le réglage `errors.redirect_404` remet la page d'erreur d'un mot.
 *
 * Trois exceptions à la redirection, qui ne relèvent pas du choix mais de la
 * correction :
 *
 *  • une requête d'API doit recevoir son 404, pas une page HTML ;
 *  • un fichier manquant (image, feuille de style, script) aussi, sans quoi
 *    la balise qui l'appelle recevrait la page d'accueil à la place ;
 *  • la cible elle-même, qui bouclerait sur elle-même.
 */
final class NotFound
{
    /**
     * @param string $section rubrique d'où vient la demande, pour le lien de
     *                        retour de la page d'erreur.
     * @param string $path    chemin demandé, tel qu'il est arrivé.
     */
    public static function response(string $section = '/', string $path = '', bool $json = false): Response
    {
        $target = self::target();

        if (!$json && $target !== '' && !self::isFile($path) && rtrim($path, '/') !== rtrim($target, '/')) {
            return Response::redirect($target, 302);
        }

        Security::sendHeaders();
        return Response::html(View::render('pages/error', [
            'code'  => 404,
            'title' => I18n::t('error.404_title'),
            'body'  => I18n::t('error.404_body'),
            'path'  => $section !== '' ? $section : '/',
        ]), 404);
    }

    /** Adresse de repli, dans la langue courante. Vide : pas de redirection. */
    private static function target(): string
    {
        if (!Config::get('errors.redirect_404', true)) {
            return '';
        }
        $to = trim((string) Config::get('errors.redirect_404_to', '/'));
        return I18n::url($to === '' ? '/' : $to);
    }

    /**
     * Le chemin désigne-t-il un fichier ? Une extension courte en fin
     * d'adresse suffit à le dire ; les slugs du site n'en portent pas.
     */
    private static function isFile(string $path): bool
    {
        return preg_match('/\.[A-Za-z0-9]{1,8}$/', rtrim($path, '/')) === 1;
    }
}
