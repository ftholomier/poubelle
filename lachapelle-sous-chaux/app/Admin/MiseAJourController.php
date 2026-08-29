<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Csrf;
use App\Core\Deploiement;
use App\Core\Session;
use App\Core\View;
use Throwable;

/**
 * Écran Mises à jour : compare la version installée au dépôt, applique le
 * code, restaure le contenu si besoin.
 */
final class MiseAJourController
{
    public function __construct(
        private readonly View $view,
        private readonly Deploiement $deploiement,
    ) {
    }

    private function rediriger(): string
    {
        header('Location: ' . url('/admin/mises-a-jour'), true, 303);
        return '';
    }

    public function ecran(): string
    {
        $etat = $this->deploiement->etat();
        $verification = null;

        // la vérification interroge le réseau : uniquement sur demande
        if ($etat['ok'] && Session::get('maj_verification') !== null) {
            $verification = Session::get('maj_verification');
            Session::oublier('maj_verification');
        }

        return $this->view->render('admin/mises-a-jour', [
            'page'         => ['titre' => 'Mises à jour'],
            'etat'         => $etat,
            'version'      => $etat['depot'] ? $this->deploiement->versionInstallee() : null,
            'branche'      => $etat['depot'] ? $this->deploiement->branche() : '',
            'depotUrl'     => $etat['depot'] ? $this->deploiement->depot() : '',
            'verification' => $verification,
            'journal'      => Session::flash('maj_journal'),
            'sauvegardes'  => $this->deploiement->sauvegardes(),
        ], 'admin/layout');
    }

    public function verifier(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            Session::set('maj_verification', $this->deploiement->verifier());
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    public function appliquer(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            $journal = $this->deploiement->appliquer();
            Session::flash('maj_journal', implode("\n", $journal));
            Session::flash('succes', 'Mise à jour appliquée. Le contenu et les photos n’ont pas été touchés.');
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    public function sauvegarder(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            $archive = $this->deploiement->sauvegarder();
            Session::flash('succes', 'Sauvegarde créée : ' . basename($archive));
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    public function restaurer(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            $this->deploiement->restaurer((string) ($_POST['archive'] ?? ''));
            Session::flash('succes', 'Contenu et photos restaurés depuis la sauvegarde.');
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }
}
