<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Conversations;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

/**
 * Écran Conversations : les échanges du visiteur avec l'assistant.
 *
 * Ces échanges sont des données personnelles : d'où la suppression à l'unité
 * ou au mois, la durée de conservation affichée, et la purge automatique
 * assurée par la brique Conversations.
 */
final class ConversationController
{
    public function __construct(
        private readonly View $view,
        private readonly Conversations $conversations,
    ) {
    }

    private function rediriger(string $chemin = '/admin/conversations'): string
    {
        header('Location: ' . url($chemin), true, 303);
        return '';
    }

    public function ecran(): string
    {
        $mois = $this->conversations->mois();
        $courant = (string) ($_GET['mois'] ?? '');
        if ($courant === '' || !in_array($courant, $mois, true)) {
            $courant = (string) ($mois[0] ?? date('Y-m'));
        }

        $ouverte = null;
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id !== '') {
            $ouverte = $this->conversations->trouver($courant, $id);
            // Ouvrir une conversation vaut lecture : le compteur de non-lues
            // ne sert à rien s'il faut le remettre à zéro à la main.
            if ($ouverte !== null) {
                $this->conversations->marquerLue($courant, $id);
                $ouverte['lu'] = true;
            }
        }

        return $this->view->render('admin/conversations', [
            'page'          => ['titre' => 'Conversations'],
            'mois'          => $mois,
            'courant'       => $courant,
            'conversations' => $this->conversations->duMois($courant),
            'ouverte'       => $ouverte,
            'conservation'  => Conversations::CONSERVATION,
        ], 'admin/layout');
    }

    public function supprimer(): string
    {
        $mois = (string) ($_POST['mois'] ?? '');
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/conversations?mois=' . rawurlencode($mois));
        }

        $this->conversations->supprimer($mois, (string) ($_POST['id'] ?? ''));
        Session::flash('succes', 'Conversation supprimée.');

        return $this->rediriger('/admin/conversations?mois=' . rawurlencode($mois));
    }

    public function viderMois(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $mois = (string) ($_POST['mois'] ?? '');
        $this->conversations->viderMois($mois);
        Session::flash('succes', 'Échanges du mois supprimés.');

        return $this->rediriger();
    }
}
