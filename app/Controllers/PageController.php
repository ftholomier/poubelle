<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Content;
use App\Core\Mailer;
use App\Core\Parametres;
use App\Core\View;
use RuntimeException;
use Throwable;

/**
 * Rendu des pages éditoriales. Chaque page tire son contenu de /data,
 * jamais du gabarit : le futur back-office n'aura donc qu'à écrire du JSON.
 */
final class PageController
{
    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly Parametres $parametres,
        private readonly Mailer $mailer,
    ) {
    }

    public function accueil(): string
    {
        return $this->view->render('accueil', [
            'page'         => $this->page('accueil'),
            'hebergements' => $this->content->load('hebergements')['items'] ?? [],
            'etangs'       => $this->content->load('peche')['items'] ?? [],
        ]);
    }

    /**
     * Page de contenu pilotée par data/pages/<slug>.json.
     * $gabarit permet d'utiliser une vue dédiée (ex. boutique).
     */
    public function simple(string $slug, string $gabarit = 'simple'): string
    {
        $page = $this->page($slug);
        if ($page === null) {
            return $this->introuvable();
        }

        return $this->view->render($gabarit, ['page' => $page]);
    }

    public function hebergements(): string
    {
        return $this->view->render('hebergements', [
            'page'  => $this->page('hebergements'),
            'items' => $this->content->load('hebergements')['items'] ?? [],
        ]);
    }

    public function hebergement(string $slug): string
    {
        $item = $this->content->find('hebergements', $slug);
        if ($item === null) {
            return $this->introuvable();
        }

        return $this->view->render('hebergement', [
            'page' => ['titre' => $item['nom'] ?? '', 'meta' => $item['meta'] ?? []],
            'item' => $item,
        ]);
    }

    public function peche(): string
    {
        return $this->view->render('peche', [
            'page'  => $this->page('peche'),
            'items' => $this->content->load('peche')['items'] ?? [],
        ]);
    }

    public function etang(string $slug): string
    {
        $item = $this->content->find('peche', $slug);
        if ($item === null) {
            return $this->introuvable();
        }

        return $this->view->render('etang', [
            'page' => ['titre' => $item['nom'] ?? '', 'meta' => $item['meta'] ?? []],
            'item' => $item,
        ]);
    }

    public function galerie(): string
    {
        return $this->view->render('galerie', [
            'page'   => $this->page('galerie'),
            'medias' => $this->content->load('galerie')['medias'] ?? [],
        ]);
    }

    public function contact(): string
    {
        return $this->view->render('contact', [
            'page'   => $this->page('contact'),
            'erreurs' => [],
            'valeurs' => [],
        ]);
    }

    /**
     * Traitement du formulaire de demande : validation, puis envoi au
     * destinataire réglé dans Paramètres.
     */
    public function contactEnvoi(): string
    {
        $valeurs = [
            'nom'     => trim((string) ($_POST['nom'] ?? '')),
            'prenom'  => trim((string) ($_POST['prenom'] ?? '')),
            'email'   => trim((string) ($_POST['email'] ?? '')),
            'tel'     => trim((string) ($_POST['tel'] ?? '')),
            'message' => trim((string) ($_POST['message'] ?? '')),
            'sujet'   => trim((string) ($_POST['sujet'] ?? '')),
        ];

        $erreurs = [];
        if ($valeurs['nom'] === '') {
            $erreurs['nom'] = 'Merci d’indiquer votre nom.';
        }
        if (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'Adresse e-mail invalide.';
        }
        if (mb_strlen($valeurs['message']) < 10) {
            $erreurs['message'] = 'Votre message est trop court.';
        }
        // Piège à robots : un champ masqué qui doit rester vide.
        if (($_POST['site'] ?? '') !== '') {
            $erreurs['site'] = 'Envoi refusé.';
        }

        if ($erreurs !== []) {
            http_response_code(422);
            return $this->view->render('contact', [
                'page'    => $this->page('contact'),
                'erreurs' => $erreurs,
                'valeurs' => $valeurs,
            ]);
        }

        // à défaut de destinataire dédié, l'e-mail public du site suffit :
        // le formulaire fonctionne ainsi sans aucun réglage préalable
        $destinataire = (string) $this->parametres->get('contact.destinataire')
            ?: (string) $this->content->get('site', 'contact.email', '');

        if ($destinataire === '') {
            error_log('Formulaire de contact : ni destinataire dans Paramètres, ni e-mail dans Coordonnées.');
            http_response_code(500);
            return $this->view->render('contact', [
                'page'    => $this->page('contact'),
                'erreurs' => ['envoi' => 'Le formulaire n’est pas encore configuré. '
                    . 'Merci de nous joindre par téléphone en attendant.'],
                'valeurs' => $valeurs,
            ]);
        }

        try {
            $this->mailer->envoyer(
                $destinataire,
                'Demande depuis le site — ' . ($valeurs['sujet'] !== '' ? $valeurs['sujet'] : 'Contact'),
                $this->corpsDemande($valeurs),
                $valeurs['email'],
                trim($valeurs['prenom'] . ' ' . $valeurs['nom'])
            );

            $copie = (string) $this->parametres->get('contact.copie');
            if ($copie !== '') {
                try {
                    $this->mailer->envoyer(
                        $copie,
                        'Copie — demande depuis le site',
                        $this->corpsDemande($valeurs),
                        $valeurs['email'],
                        trim($valeurs['prenom'] . ' ' . $valeurs['nom'])
                    );
                } catch (Throwable $e) {
                    // la copie n'est pas critique : la demande principale est partie
                    error_log('Copie du formulaire non envoyée : ' . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            error_log('Envoi du formulaire de contact impossible : ' . $e->getMessage());
            http_response_code(500);
            return $this->view->render('contact', [
                'page'    => $this->page('contact'),
                'erreurs' => ['envoi' => 'L’envoi a échoué. Merci de réessayer ou de nous appeler.'],
                'valeurs' => $valeurs,
            ]);
        }

        return $this->view->render('contact-confirmation', [
            'page'    => ['titre' => 'Demande envoyée'],
            'valeurs' => $valeurs,
        ]);
    }

    /**
     * @param array<string, string> $v
     */
    private function corpsDemande(array $v): string
    {
        $lignes = [
            'Nouvelle demande depuis le site Étang Fourchu.',
            '',
            'Nom      : ' . trim($v['prenom'] . ' ' . $v['nom']),
            'E-mail   : ' . $v['email'],
            'Téléphone: ' . ($v['tel'] !== '' ? $v['tel'] : '—'),
            'Sujet    : ' . ($v['sujet'] !== '' ? $v['sujet'] : '—'),
            '',
            'Message :',
            $v['message'],
            '',
            '---',
            'Reçue le ' . date('d/m/Y à H:i'),
        ];
        return implode("\n", $lignes) . "\n";
    }

    public function introuvable(): string
    {
        http_response_code(404);
        return $this->view->render('erreur', [
            'page'  => ['titre' => 'Page introuvable'],
            'code'  => 404,
            'titre' => 'Cette page n’existe pas',
        ]);
    }

    /**
     * @return array<mixed>|null
     */
    private function page(string $slug): ?array
    {
        try {
            return $this->content->load('pages/' . $slug);
        } catch (RuntimeException) {
            return null;
        }
    }
}
