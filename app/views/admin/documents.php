<?php
/** @var array $items @var string $kind @var array $settings @var array $user */
use App\Core\View;
$title = 'Documents IA';
echo View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));
echo View::render('admin/partials/media-screen', [
    'items'   => $items,
    'kind'    => $kind,
    'isDoc'   => true,
    'heading' => 'Documents pour l’assistant',
    'intro'   => 'Le texte de ces documents alimente les réponses de l’assistant. Ils restent privés sauf mention contraire.',
]);
echo View::render('admin/partials/shell-close');
