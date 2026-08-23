<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Avis;
use App\Core\Csrf;
use App\Core\Parametres;
use App\Core\Session;
use App\Core\View;
use Throwable;

/**
 * Écran Avis Google : clé d'API, fiche de l'établissement, actualisation.
 *
 * La clé d'API est un secret : elle vit dans data/admin/parametres.json,
 * hors git et hors racine web, comme le mot de passe SMTP.
 */
final class AvisController
{
    public function __construct(
        private readonly View $view,
        private readonly Avis $avis,
        private readonly Parametres $parametres,
    ) {
    }

    private function rediriger(): string
    {
        header('Location: ' . url('/admin/avis'), true, 303);
        return '';
    }

    public function ecran(): string
    {
        return $this->view->render('admin/avis', [
            'page'       => ['titre' => 'Avis Google'],
            'reglages'   => $this->parametres->get('avis', []),
            'configure'  => $this->avis->configure(),
            'cache'      => $this->avis->etatCache(),
            'resultats'  => Session::flashDonnees('avis_resultats'),
        ], 'admin/layout');
    }

    public function envoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $actuel = $this->parametres->tout();

        $cle = trim((string) ($_POST['cle_api'] ?? ''));
        // champ laissé vide = on conserve la clé déjà enregistrée
        if ($cle === '') {
            $cle = (string) ($actuel['avis']['cle_api'] ?? '');
        }

        $pause = (int) ($_POST['pause'] ?? 6);

        $actuel['avis'] = [
            'actif'     => isset($_POST['actif']),
            'cle_api'   => $cle,
            'place_id'  => trim((string) ($_POST['place_id'] ?? '')),
            'note_mini' => max(1, min(5, (int) ($_POST['note_mini'] ?? 4))),
            // 0 conserve son sens : défilement automatique à l'arrêt
            'pause'     => $pause <= 0 ? 0 : max(3, min(30, $pause)),
            'dates'     => isset($_POST['dates']),
            'total'     => isset($_POST['total']),
        ];

        try {
            $this->parametres->enregistrer($actuel);
            Session::flash('succes', 'Réglages des avis enregistrés.');
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
            return $this->rediriger();
        }

        // Enchaîner sur une récupération évite au client de se demander si
        // ça marche : il voit tout de suite les avis, ou le message d'erreur
        // qui dit quoi corriger.
        if ($actuel['avis']['actif'] && $actuel['avis']['place_id'] !== '' && $cle !== '') {
            return $this->actualiser();
        }

        return $this->rediriger();
    }

    public function actualiser(): string
    {
        try {
            $donnees = $this->avis->rafraichir();
            $nombre = count($donnees['avis'] ?? []);
            Session::flash('succes', $nombre > 0
                ? $nombre . ' avis récupérés depuis Google, note moyenne '
                  . number_format((float) $donnees['note'], 1, ',', ' ') . '/5.'
                : 'Google a répondu, mais cette fiche ne comporte aucun avis rédigé.');
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    /**
     * Recherche la fiche de l'établissement à partir de son nom : le
     * Place ID est introuvable pour qui ne connaît pas les outils Google.
     */
    public function rechercher(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            $resultats = $this->avis->rechercher(trim((string) ($_POST['recherche'] ?? '')));
            if ($resultats === []) {
                Session::flash('erreur', 'Aucun établissement trouvé. Précisez le nom et la ville.');
            } else {
                Session::flashDonnees('avis_resultats', $resultats);
            }
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }
}
