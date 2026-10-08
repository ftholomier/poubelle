<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Pdf\Livre;

/**
 * Contenus › Maillots du livre : chaque modèle de maillot (Livre::JERSEYS) en 3D, devant et dos, à côté de
 * sa photo de référence. Les historiens le valident ou le renvoient avec une remarque ; seuls les maillots
 * validés sont proposés pour le livre.
 */
final class Jerseys extends Base
{
    public static function index(Request $req): Response
    {
        return self::html('admin/fiches/maillots', ['jerseys' => Livre::JERSEYS, 'status' => Livre::jerseyStatus()],
            ['title' => 'Maillots du livre', 'crumb' => 'Contenus', 'nav' => 'maillots', 'scripts' => ['admin/livre.js']]);
    }

    public static function action(Request $req): Response
    {
        $key = $req->str('key');
        $status = $req->str('status');
        if (!isset(Livre::JERSEYS[$key]) || !in_array($status, ['valide', 'revoir'], true)) {
            return self::back('/admin/maillots', null, 'Maillot inconnu.');
        }
        $user = self::actor();
        Livre::setJerseyStatus($key, $status, (string) ($user['name'] ?? $user['email'] ?? ''), trim((string) ($req->post['note'] ?? '')));
        $label = Livre::JERSEYS[$key]['label'];
        Activity::log($user, ($status === 'valide' ? 'a validé' : 'a renvoyé') . ' le maillot « ' . $label . ' »', null);
        return self::back('/admin/maillots#m-' . $key, $status === 'valide' ? 'Maillot « ' . $label . ' » validé : il est proposé pour le livre.' : 'Maillot « ' . $label . ' » à revoir : il n’est plus proposé.');
    }
}
