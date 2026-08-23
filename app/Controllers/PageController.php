<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Content;
use App\Core\Mailer;
use App\Core\Parametres;
use App\Core\Seo;
use App\Core\View;
use RuntimeException;
use Throwable;

/**
 * Rendu des pages publiques. Chaque page tire son contenu de /data, jamais
 * du gabarit : le back-office n'a donc qu'à écrire du JSON.
 */
final class PageController
{
    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly Parametres $parametres,
        private readonly Mailer $mailer,
        private readonly Seo $seo,
    ) {
    }

    /**
     * Rendu d'une page publique, avec le contexte dont le gabarit a besoin
     * pour ses balises : quelle page du référencement, quelle fiche.
     *
     * @param array<string, mixed> $donnees
     * @param array<string, mixed>|null $fiche
     */
    private function rendre(string $gabarit, string $cleSeo, array $donnees, ?array $fiche = null): string
    {
        return $this->view->render($gabarit, $donnees + [
            'seoCle'  => $cleSeo,
            'seoItem' => $fiche,
        ]);
    }

    public function accueil(): string
    {
        return $this->rendre('accueil', 'accueil', [
            'page'     => $this->page('accueil'),
            'services' => $this->content->publies('services'),
            'valeurs'  => $this->content->publies('valeurs'),
        ]);
    }

    public function laSociete(): string
    {
        return $this->rendre('la-societe', 'la-societe', [
            'page' => $this->page('la-societe'),
        ]);
    }

    public function services(): string
    {
        return $this->rendre('services', 'nos-services', [
            'page'  => $this->page('nos-services'),
            'items' => $this->content->publies('services'),
        ]);
    }

    public function service(string $slug): string
    {
        $item = $this->content->find('services', $slug);
        // désactivé = invisible, y compris par son adresse directe
        if ($item === null || !Content::estPublie($item)) {
            return $this->introuvable();
        }

        return $this->rendre('service', 'nos-services', [
            'page'   => ['titre' => $item['nom'] ?? '', 'meta' => $item['meta'] ?? []],
            'item'   => $item,
            'autres' => array_values(array_filter(
                $this->content->publies('services'),
                static fn(array $s): bool => ($s['slug'] ?? '') !== $slug
            )),
        ], $item);
    }

    public function valeurs(): string
    {
        return $this->rendre('valeurs', 'nos-valeurs', [
            'page'  => $this->page('nos-valeurs'),
            'items' => $this->content->publies('valeurs'),
        ]);
    }

    /**
     * Page de contenu pilotée par data/pages/<slug>.json.
     */
    public function simple(string $slug, string $gabarit = 'simple'): string
    {
        $page = $this->page($slug);
        if ($page === null) {
            return $this->introuvable();
        }

        return $this->rendre($gabarit, $slug, ['page' => $page]);
    }

    public function contact(): string
    {
        return $this->rendre('contact', 'contact', [
            'page'    => $this->page('contact'),
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
            'societe' => trim((string) ($_POST['societe'] ?? '')),
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
        // Le consentement conditionne l'usage des données : sans lui, on ne
        // traite pas la demande.
        if (($_POST['consentement'] ?? '') === '') {
            $erreurs['consentement'] = 'Merci d’accepter le traitement de votre demande.';
        }
        // Piège à robots : un champ masqué qui doit rester vide.
        if (($_POST['site'] ?? '') !== '') {
            $erreurs['site'] = 'Envoi refusé.';
        }

        if ($erreurs !== []) {
            http_response_code(422);
            return $this->rendre('contact', 'contact', [
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
            return $this->rendre('contact', 'contact', [
                'page'    => $this->page('contact'),
                'erreurs' => ['envoi' => 'Le formulaire n’est pas encore configuré. '
                    . 'Merci de nous joindre par téléphone en attendant.'],
                'valeurs' => $valeurs,
            ]);
        }

        try {
            $this->mailer->envoyer(
                $destinataire,
                'Nouvelle demande depuis le site' . ($valeurs['sujet'] !== '' ? ' — ' . $valeurs['sujet'] : ''),
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
            return $this->rendre('contact', 'contact', [
                'page'    => $this->page('contact'),
                'erreurs' => ['envoi' => 'L’envoi a échoué. Merci de réessayer ou de nous appeler.'],
                'valeurs' => $valeurs,
            ]);
        }

        return $this->rendre('contact-confirmation', 'contact', [
            'page'    => ['titre' => 'Demande envoyée', 'meta' => ['robots' => 'noindex']],
            'valeurs' => $valeurs,
        ]);
    }

    /**
     * @param array<string, string> $v
     */
    private function corpsDemande(array $v): string
    {
        // Rédigé en phrases complètes : un corps réduit à des étiquettes
        // courtes (« Nom », « Message ») se lit aussi bien en anglais, et les
        // messageries proposent alors de le traduire.
        $lignes = [
            'Bonjour,',
            '',
            'Vous venez de recevoir une nouvelle demande envoyée depuis le',
            'formulaire de contact du site du Menuiserie Tréhant.',
            '',
            'Coordonnées de la personne :',
            '',
            '  Nom et prénom        : ' . trim($v['prenom'] . ' ' . $v['nom']),
            '  Société              : ' . ($v['societe'] !== '' ? $v['societe'] : 'non communiquée'),
            '  Adresse électronique : ' . $v['email'],
            '  Numéro de téléphone  : ' . ($v['tel'] !== '' ? $v['tel'] : 'non communiqué'),
            '  Objet de la demande  : ' . ($v['sujet'] !== '' ? $v['sujet'] : 'non précisé'),
            '',
            'Voici le message qu’elle vous a laissé :',
            '',
            $v['message'],
            '',
            '—',
            'Demande reçue le ' . date('d/m/Y') . ' à ' . date('H\hi') . '.',
            'Vous pouvez répondre directement à cet e-mail : votre réponse',
            'parviendra à la personne qui vous a écrit.',
        ];
        return implode("\n", $lignes) . "\n";
    }

    public function introuvable(): string
    {
        // Une fiche renommée reste captée par /nos-services/{slug} : la route
        // de redirection, déclarée après, ne serait jamais atteinte. On
        // consulte donc la table avant de conclure à une page absente.
        $chemin = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
        $cible  = $this->seo->cible($chemin);
        if ($cible !== null) {
            http_response_code(301);
            header('Location: ' . lien($cible));
            return '';
        }

        http_response_code(404);
        return $this->rendre('erreur', 'accueil', [
            'page'  => ['titre' => 'Page introuvable', 'meta' => ['robots' => 'noindex']],
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
