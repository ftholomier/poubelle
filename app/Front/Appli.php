<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;

/**
 * « L'appli du musée » (/appli/) : installer le musée sur l'écran d'accueil (bouton sur Android
 * et ordinateur, marche à suivre sur iPhone), ce que l'appli apporte, et les notifications.
 * Seulement le musée : le site de l'association n'est pas une application.
 */
final class Appli
{
    public static function page(Request $req): Response
    {
        if (!Settings::get('app.enabled', true)) {
            return Pages::notFound();
        }
        return Pages::render('appli', [], [
            'title' => t('L’appli du musée'),
            'description' => t('Installez Sochaux Rétro sur l’écran d’accueil de votre téléphone : le musée en plein écran, plus rapide, et lisible même sans réseau.'),
            'active' => '',
            'styles' => ['css/appli.css'],
            'scripts' => ['js/appli.js'],
        ]);
    }
}
