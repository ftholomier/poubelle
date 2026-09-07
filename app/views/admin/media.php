<?php
/** @var array $items @var string $kind @var array $settings @var array $user */
use App\Core\View;
$title = 'Médias';
echo View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));
echo View::render('admin/partials/media-screen', [
    'items'   => $items,
    'kind'    => $kind,
    'isDoc'   => false,
    'heading' => 'Médias',
    'intro'   => 'Images du site. Copiez l’adresse d’un fichier pour l’utiliser dans un bloc de page.',
]);
echo View::render('admin/partials/shell-close');
