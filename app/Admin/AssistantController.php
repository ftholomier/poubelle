<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Assistant;
use App\Core\Bulle;
use App\Core\Charte;
use App\Core\Csrf;
use App\Core\Parametres;
use App\Core\Session;
use App\Core\TexteRiche;
use App\Core\View;
use Throwable;

/**
 * Écran Assistant IA : activation, clé Gemini, modèle, et les trois sources.
 *
 * La clé d'API est un secret : elle vit dans data/admin/parametres.json,
 * hors git et hors racine web, comme le mot de passe SMTP. Les documents
 * déposés vivent dans data/assistant/, également hors racine web — un dossier
 * ou un tarif interne n'a pas à être téléchargeable par son adresse.
 */
final class AssistantController
{
    public function __construct(
        private readonly View $view,
        private readonly Assistant $assistant,
        private readonly Parametres $parametres,
    ) {
    }

    private function rediriger(): string
    {
        header('Location: ' . url('/admin/assistant'), true, 303);
        return '';
    }

    public function ecran(): string
    {
        return $this->view->render('admin/assistant', [
            'page'       => ['titre' => 'Assistant IA'],
            'reglages'   => $this->parametres->get('assistant', []),
            // La bulle est construite ici plutôt que lue champ par champ dans
            // le gabarit : l'écran montre alors exactement ce que le site
            // sert, correction de contraste comprise.
            'bulle'      => $this->assistant->bulle(),
            'modeles'    => $this->assistant->modeles(),
            'erreur'     => $this->assistant->derniereErreur(),
            'documents'  => $this->assistant->documents(),
            'notes'      => $this->assistant->notesHtml(),
            'mesure'     => $this->assistant->mesureCorpus(),
            'essai'      => Session::flashDonnees('assistant_essai'),
        ], 'admin/layout');
    }

    public function envoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $actuel = $this->parametres->tout();

        $cle = trim((string) ($_POST['cle'] ?? ''));
        // champ laissé vide = on conserve la clé déjà enregistrée
        if ($cle === '') {
            $cle = (string) ($actuel['assistant']['cle'] ?? '');
        }

        $forme = (string) ($_POST['bulle_forme'] ?? '');
        // Le fond vide n'est pas une absence de choix : c'est le choix de
        // suivre la couleur de la commune, pour que la bulle reste accordée
        // au site le jour où l'écran Apparence en change.
        $suitLaCommune = isset($_POST['bulle_fond_commune']);

        $this->parametres->enregistrer(['assistant' => [
            'actif'       => isset($_POST['actif']),
            'cle'         => $cle,
            'modele'      => trim((string) ($_POST['modele'] ?? '')),
            'titre'       => trim((string) ($_POST['titre'] ?? '')),
            'accueil'     => trim((string) ($_POST['accueil'] ?? '')),
            'source_site' => isset($_POST['source_site']),
            'bulle'       => [
                'forme'   => isset(Bulle::FORMES[$forme]) ? $forme : Bulle::FORME_DEFAUT,
                'libelle' => mb_substr(trim((string) ($_POST['bulle_libelle'] ?? '')), 0, Bulle::LIBELLE_MAX),
                'taille'  => self::taille($_POST['bulle_taille'] ?? Bulle::TAILLE_DEFAUT),
                'fond'    => $suitLaCommune ? '' : Charte::normaliser((string) ($_POST['bulle_fond'] ?? '')),
                'texte'   => Charte::normaliser((string) ($_POST['bulle_texte'] ?? Bulle::TEXTE_DEFAUT)),
            ],
        ]] + $actuel);

        Session::flash('succes', 'Assistant enregistré.');
        return $this->rediriger();
    }

    /**
     * Taille de la bulle, bornée à l'écriture.
     *
     * `Bulle` la borne aussi à la lecture — les deux, comme pour le logo :
     * un fichier de paramètres recopié d'un autre site ou modifié à la main
     * ne doit pas pouvoir poser un bouton de trois cents pixels sur le site.
     *
     * @param mixed $valeur
     */
    private static function taille(mixed $valeur): int
    {
        $n = is_numeric($valeur) ? (int) round((float) $valeur) : Bulle::TAILLE_DEFAUT;

        return max(Bulle::TAILLE_MIN, min(Bulle::TAILLE_MAX, $n));
    }

    /** Rafraîchit la liste des modèles, sans attendre l'expiration du cache. */
    public function modeles(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $modeles = $this->assistant->modeles(true);
        $erreur = $this->assistant->derniereErreur();

        if ($modeles === []) {
            Session::flash('erreur', $erreur !== '' ? $erreur : 'Aucun modèle renvoyé par Google.');
        } else {
            Session::flash('succes', count($modeles) . ' modèles disponibles pour cette clé.');
        }

        return $this->rediriger();
    }

    public function notes(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            // Le contenu vient d'un éditeur riche : on ne garde que le
            // balisage de mise en forme, jamais de script ni d'attribut
            // d'événement, même saisi par un administrateur. Le filtre est
            // celui du contenu des pages, avec le profil élargi qui accepte un
            // sous-titre : deux listes blanches auraient divergé, et c'est
            // toujours la moins tenue qui laisse passer quelque chose.
            $this->assistant->enregistrerNotes(
                TexteRiche::nettoyer((string) ($_POST['notes'] ?? ''), true)
            );
            Session::flash('succes', 'Notes enregistrées.');
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    public function documentAjout(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $envoyes = $_FILES['documents'] ?? null;
        if (!is_array($envoyes)) {
            Session::flash('erreur', 'Aucun document reçu.');
            return $this->rediriger();
        }

        $ajoutes = 0;
        $erreurs = [];
        foreach ((array) $envoyes['name'] as $i => $_) {
            if ((int) $envoyes['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            try {
                $this->assistant->ajouterDocument([
                    'name'     => $envoyes['name'][$i],
                    'tmp_name' => $envoyes['tmp_name'][$i],
                    'error'    => $envoyes['error'][$i],
                    'size'     => $envoyes['size'][$i],
                ]);
                $ajoutes++;
            } catch (Throwable $e) {
                $erreurs[] = $envoyes['name'][$i] . ' : ' . $e->getMessage();
            }
        }

        if ($ajoutes > 0) {
            Session::flash('succes', $ajoutes . ' document(s) ajouté(s).');
        }
        if ($erreurs !== []) {
            Session::flash('erreur', implode(' — ', $erreurs));
        }

        return $this->rediriger();
    }

    public function documentSuppression(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $nom = (string) ($_POST['nom'] ?? '');

        if ($this->assistant->supprimerDocument($nom)) {
            Session::flash('succes', 'Document supprimé.');
        } else {
            Session::flash('erreur', 'Ce document n’a pas pu être supprimé.');
        }

        return $this->rediriger();
    }

    /** Pose une question depuis le back-office, pour vérifier le réglage. */
    public function essai(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $question = trim((string) ($_POST['question'] ?? ''));
        if ($question === '') {
            Session::flash('erreur', 'Écrivez une question à poser.');
            return $this->rediriger();
        }

        try {
            $resultat = $this->assistant->repondre($question);
            Session::flashDonnees('assistant_essai', [
                'question' => $question,
                'reponse'  => $resultat['reponse'],
            ]);
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    /**
     * Ne conserve d'un contenu riche que le balisage de mise en forme.
     *
     * strip_tags avec une liste blanche laisse passer les attributs, dont
     * onclick ou un href javascript: — d'où le second passage, qui retire
     * tout attribut sauf href sur les liens.
     */
}
