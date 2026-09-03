<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Antispam;
use App\Core\Content;
use App\Core\Mailer;
use App\Core\Parametres;
use App\Core\Seo;
use App\Core\View;
use App\Core\Vivant;
use RuntimeException;
use Throwable;

/**
 * Rendu des pages publiques. Chaque page tire son contenu de /data, jamais
 * du gabarit : le back-office n'a donc qu'à écrire du JSON.
 *
 * La plupart des pages de la mairie passent par simple() : leur contenu est
 * une suite de blocs typés que views/pages/simple.php sait rendre. Seules les
 * pages dont la mise en forme dépend d'une donnée structurée — le trombinoscope
 * du conseil, la liste filtrable des démarches, la médiathèque de documents —
 * ont leur propre méthode et leur propre vue.
 */
final class PageController
{
    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly Parametres $parametres,
        private readonly Mailer $mailer,
        private readonly Seo $seo,
        private readonly Antispam $antispam,
        private readonly Vivant $vivant,
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

    // ------------------------------------------------------------- accueil

    public function accueil(): string
    {
        return $this->rendre('accueil', 'accueil', [
            'page'       => $this->page('accueil'),
            'demarches'  => array_slice($this->content->publies('demarches'), 0, 6),
            'actualites' => $this->vivant->actualites(3),
            'agenda'     => $this->vivant->agenda(3),
        ]);
    }

    // -------------------------------------------------- page à blocs typés

    /**
     * Page de contenu pilotée par data/pages/<cle>.json.
     *
     * C'est le rendu par défaut : hero, puis une suite de blocs. Une page
     * ajoutée plus tard n'a besoin que de son fichier JSON, de son entrée
     * dans Seo::PAGES et d'une ligne de route.
     */
    public function simple(string $cle, string $gabarit = 'simple'): string
    {
        $page = $this->page($cle);
        if ($page === null) {
            return $this->introuvable();
        }

        return $this->rendre($gabarit, $cle, ['page' => $page]);
    }

    // -------------------------------------------------------- vie municipale

    public function conseilMunicipal(): string
    {
        return $this->rendre('conseil-municipal', 'conseil-municipal', [
            'page'    => $this->page('conseil-municipal'),
            'conseil' => $this->content->load('conseil'),
        ]);
    }

    public function commissions(): string
    {
        return $this->rendre('commissions', 'commissions', [
            'page'        => $this->page('commissions'),
            'commissions' => $this->content->publies('commissions'),
            'comites'     => $this->content->publies('commissions', 'comites'),
        ]);
    }

    /**
     * Page de documents téléchargeables : comptes-rendus, budgets, bulletins.
     *
     * Une seule vue pour trois pages, parce qu'elles ne diffèrent que par le
     * jeu de fichiers listés. Le classement se fait sur la date, décroissante :
     * le dernier compte-rendu est celui qu'on vient chercher.
     */
    public function documents(string $cle, string $famille): string
    {
        $tous = $this->content->publies('documents');
        $liste = array_values(array_filter(
            $tous,
            static fn(array $d): bool => ($d['famille'] ?? '') === $famille
        ));
        usort($liste, static fn(array $a, array $b): int => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        return $this->rendre('documents', $cle, [
            'page'      => $this->page($cle),
            'documents' => $liste,
        ]);
    }

    // ------------------------------------------------------------ démarches

    public function demarches(): string
    {
        $items = $this->content->publies('demarches');

        // Le filtre passe par l'adresse et non par le seul JavaScript : la
        // sélection est alors partageable, s'ajoute aux favoris, survit à un
        // rechargement et fonctionne sans script. Le JavaScript ne fait
        // qu'éviter l'aller-retour au serveur.
        $famille = trim((string) ($_GET['famille'] ?? ''));
        $connues = [];
        foreach ($items as $item) {
            $connues[(string) ($item['famille'] ?? 'autres')] = true;
        }
        if (!isset($connues[$famille])) {
            $famille = '';
        }

        return $this->rendre('demarches', 'demarches', [
            'page'    => $this->page('demarches'),
            'items'   => $items,
            'famille' => $famille,
        ]);
    }

    public function demarche(string $slug): string
    {
        $item = $this->content->find('demarches', $slug);
        // dépubliée = invisible, y compris par son adresse directe
        if ($item === null || !Content::estPublie($item)) {
            return $this->introuvable();
        }

        return $this->rendre('demarche', 'demarches', [
            'page'   => ['titre' => $item['nom'] ?? '', 'meta' => $item['meta'] ?? []],
            'item'   => $item,
            'autres' => array_values(array_filter(
                $this->content->publies('demarches'),
                static fn(array $d): bool => ($d['slug'] ?? '') !== $slug
                    && ($d['famille'] ?? '') === ($item['famille'] ?? '')
            )),
        ], $item);
    }

    public function servicesEtat(): string
    {
        return $this->rendre('services-etat', 'services-etat', [
            'page'  => $this->page('services-etat'),
            'items' => $this->content->publies('services-etat'),
        ]);
    }

    // -------------------------------------------------------------- village

    public function associations(): string
    {
        return $this->rendre('associations', 'associations', [
            'page'  => $this->page('associations'),
            'items' => $this->content->publies('associations'),
        ]);
    }

    public function actualites(): string
    {
        return $this->rendre('actualites', 'actualites', [
            'page'  => $this->page('actualites'),
            'items' => $this->vivant->actualites(),
        ]);
    }

    public function actualite(string $slug): string
    {
        $item = $this->content->find('actualites', $slug);
        if ($item === null || !Content::estPublie($item)) {
            return $this->introuvable();
        }

        $autres = array_values(array_filter(
            $this->vivant->actualites(),
            static fn(array $a): bool => ($a['slug'] ?? '') !== $slug
        ));

        return $this->rendre('actualite', 'actualites', [
            'page'   => ['titre' => $item['titre'] ?? '', 'meta' => $item['meta'] ?? []],
            'item'   => $item,
            'autres' => array_slice($autres, 0, 3),
        ], $item);
    }

    public function agenda(): string
    {
        return $this->rendre('agenda', 'agenda', [
            'page'    => $this->page('agenda'),
            'avenir'  => $this->vivant->agenda(),
            'passes'  => $this->vivant->agendaPasses(),
        ]);
    }

    // --------------------------------------------------------- vie pratique

    public function numerosUtiles(): string
    {
        return $this->rendre('numeros-utiles', 'numeros-utiles', [
            'page'      => $this->page('numeros-utiles'),
            'rubriques' => $this->content->publies('numeros'),
        ]);
    }

    /**
     * Plan du site : la table des pages du référencement, groupée comme le
     * menu. Il se construit tout seul — une page ajoutée à Seo::PAGES y
     * apparaît sans qu'on y pense.
     */
    public function planDuSite(): string
    {
        return $this->rendre('plan-du-site', 'plan-du-site', [
            'page'       => $this->page('plan-du-site'),
            'menu'       => $this->content->menu($this->seo->basesCollections()),
            'demarches'  => $this->content->publies('demarches'),
            'actualites' => $this->vivant->actualites(),
        ]);
    }

    // ------------------------------------------------------------- contact

    public function contact(): string
    {
        return $this->rendre('contact', 'contact', [
            'page'     => $this->page('contact'),
            'erreurs'  => [],
            'valeurs'  => [],
            'antispam' => $this->antispam,
        ]);
    }

    /**
     * Traitement du formulaire de contact.
     *
     * Il ne demande que de quoi rappeler. La demande en ligne, elle, réclame
     * l'objet du dossier et l'adresse dans la commune, parce qu'elle ouvre un
     * dossier : poser ici les mêmes questions découragerait celui qui veut
     * seulement joindre le secrétariat.
     */
    public function contactEnvoi(): string
    {
        $valeurs = [
            'prenom'  => trim((string) ($_POST['prenom'] ?? '')),
            'nom'     => trim((string) ($_POST['nom'] ?? '')),
            'tel'     => trim((string) ($_POST['tel'] ?? '')),
            'email'   => trim((string) ($_POST['email'] ?? '')),
            'message' => trim((string) ($_POST['message'] ?? '')),
        ];

        $erreurs = [];
        if ($valeurs['prenom'] === '') {
            $erreurs['prenom'] = 'Merci d’indiquer votre prénom.';
        }
        if ($valeurs['nom'] === '') {
            $erreurs['nom'] = 'Merci d’indiquer votre nom.';
        }
        if (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'Adresse e-mail invalide.';
        }
        if (mb_strlen($valeurs['message']) < 10) {
            $erreurs['message'] = 'Votre message est trop court.';
        }
        if (($_POST['consentement'] ?? '') === '') {
            $erreurs['consentement'] = 'Merci d’accepter le traitement de votre demande.';
        }
        $refus = $this->antispam->verifier();
        if ($refus !== null) {
            $erreurs['envoi'] = $refus;
        }

        if ($erreurs !== []) {
            return $this->contactEnErreur($erreurs, $valeurs, 422);
        }

        $destinataire = $this->destinataire();
        if ($destinataire === '') {
            error_log('Formulaire de contact : ni destinataire dans Paramètres, ni e-mail dans Coordonnées.');
            return $this->contactEnErreur(
                ['envoi' => 'Le formulaire n’est pas encore configuré. '
                    . 'Merci de joindre la mairie par téléphone en attendant.'],
                $valeurs,
                500
            );
        }

        try {
            $this->mailer->envoyer(
                $destinataire,
                'Nouveau message depuis le site de la commune',
                $this->corpsMessage($valeurs),
                $valeurs['email'],
                trim($valeurs['prenom'] . ' ' . $valeurs['nom'])
            );
        } catch (Throwable $e) {
            error_log('Envoi du formulaire de contact impossible : ' . $e->getMessage());
            return $this->contactEnErreur(
                ['envoi' => 'L’envoi a échoué. Merci de réessayer ou d’appeler la mairie.'],
                $valeurs,
                500
            );
        }

        // Le quota ne compte que ce qui est parti : un envoi raté ne doit pas
        // consommer le droit de réessayer.
        $this->antispam->enregistrerEnvoi();

        return $this->rendre('contact-confirmation', 'contact', [
            'page'    => ['titre' => 'Message envoyé', 'meta' => ['robots' => 'noindex']],
            'valeurs' => $valeurs,
            'reponse' => t('Votre message est bien arrivé au secrétariat de mairie. '
                . 'Il vous sera répondu sous quelques jours ouvrés.'),
        ]);
    }

    /**
     * @param array<string, string> $erreurs
     * @param array<string, string> $valeurs
     */
    private function contactEnErreur(array $erreurs, array $valeurs, int $code): string
    {
        http_response_code($code);

        return $this->rendre('contact', 'contact', [
            'page'     => $this->page('contact'),
            'erreurs'  => $erreurs,
            'valeurs'  => $valeurs,
            'antispam' => $this->antispam,
        ]);
    }

    // ------------------------------------------------------ demande en ligne

    /**
     * Demande adressée à la mairie : acte d'état civil, signalement de voirie,
     * réservation de la salle des fêtes, inscription scolaire.
     *
     * Séparée du contact parce qu'elle n'a pas la même intention : trouver un
     * numéro et des horaires d'un côté, ouvrir un dossier de l'autre. Mêler
     * les deux allongerait le formulaire de qui veut seulement poser une
     * question.
     */
    public function demande(): string
    {
        return $this->rendre('demande', 'demande', [
            'page'     => $this->page('demande'),
            'erreurs'  => [],
            'valeurs'  => [],
            'antispam' => $this->antispam,
        ]);
    }

    public function demandeEnvoi(): string
    {
        $valeurs = [
            'nom'      => trim((string) ($_POST['nom'] ?? '')),
            'prenom'   => trim((string) ($_POST['prenom'] ?? '')),
            'email'    => trim((string) ($_POST['email'] ?? '')),
            'tel'      => trim((string) ($_POST['tel'] ?? '')),
            'adresse'  => trim((string) ($_POST['adresse'] ?? '')),
            'sujet'    => trim((string) ($_POST['sujet'] ?? '')),
            'message'  => trim((string) ($_POST['message'] ?? '')),
        ];

        $erreurs = [];
        if ($valeurs['nom'] === '') {
            $erreurs['nom'] = 'Merci d’indiquer votre nom.';
        }
        if (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'Adresse e-mail invalide.';
        }
        if ($valeurs['sujet'] === '') {
            $erreurs['sujet'] = 'Merci de choisir l’objet de votre demande.';
        }
        if (mb_strlen($valeurs['message']) < 10) {
            $erreurs['message'] = 'Votre demande est trop courte pour être traitée.';
        }
        // Le consentement conditionne l'usage des données : sans lui, la
        // demande n'est pas traitée.
        if (($_POST['consentement'] ?? '') === '') {
            $erreurs['consentement'] = 'Merci d’accepter le traitement de votre demande.';
        }
        // Piège à robots, horloge signée, quota par adresse : le détail est
        // dans Antispam, qui garde les deux formulaires du site.
        $refus = $this->antispam->verifier();
        if ($refus !== null) {
            $erreurs['envoi'] = $refus;
        }

        if ($erreurs !== []) {
            return $this->demandeEnErreur($erreurs, $valeurs, 422);
        }

        $destinataire = $this->destinataire();
        if ($destinataire === '') {
            error_log('Demande en ligne : ni destinataire dans Paramètres, ni e-mail dans Coordonnées.');
            return $this->demandeEnErreur(
                ['envoi' => 'Le formulaire n’est pas encore configuré. '
                    . 'Merci de joindre la mairie par téléphone en attendant.'],
                $valeurs,
                500
            );
        }

        try {
            $this->mailer->envoyer(
                $destinataire,
                'Demande en ligne — ' . ($valeurs['sujet'] !== '' ? $valeurs['sujet'] : 'objet non précisé'),
                $this->corpsDemande($valeurs),
                $valeurs['email'],
                trim($valeurs['prenom'] . ' ' . $valeurs['nom'])
            );

            $copie = (string) $this->parametres->get('contact.copie');
            if ($copie !== '') {
                try {
                    $this->mailer->envoyer(
                        $copie,
                        'Copie — demande en ligne',
                        $this->corpsDemande($valeurs),
                        $valeurs['email'],
                        trim($valeurs['prenom'] . ' ' . $valeurs['nom'])
                    );
                } catch (Throwable $e) {
                    // la copie n'est pas critique : la demande principale est partie
                    error_log('Copie de la demande non envoyée : ' . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            error_log('Envoi de la demande en ligne impossible : ' . $e->getMessage());
            return $this->demandeEnErreur(
                ['envoi' => 'L’envoi a échoué. Merci de réessayer ou d’appeler la mairie.'],
                $valeurs,
                500
            );
        }

        $this->antispam->enregistrerEnvoi();

        return $this->rendre('contact-confirmation', 'demande', [
            'page'    => ['titre' => 'Demande envoyée', 'meta' => ['robots' => 'noindex']],
            'valeurs' => $valeurs,
            'reponse' => t('Votre demande est bien arrivée au secrétariat de mairie. '
                . 'Elle sera traitée aux heures d’ouverture, et vous serez recontacté '
                . 'si une pièce manque au dossier.'),
        ]);
    }

    /**
     * @param array<string, string> $erreurs
     * @param array<string, string> $valeurs
     */
    private function demandeEnErreur(array $erreurs, array $valeurs, int $code): string
    {
        http_response_code($code);

        return $this->rendre('demande', 'demande', [
            'page'     => $this->page('demande'),
            'erreurs'  => $erreurs,
            'valeurs'  => $valeurs,
            'antispam' => $this->antispam,
        ]);
    }

    // ----------------------------------------------------------- messages

    /**
     * @param array<string, string> $v
     */
    private function corpsMessage(array $v): string
    {
        $lignes = [
            'Bonjour,',
            '',
            'Vous venez de recevoir un message envoyé depuis le formulaire de',
            'contact du site de la commune d’Angeot.',
            '',
            'Coordonnées de la personne :',
            '',
            '  Nom et prénom        : ' . trim($v['prenom'] . ' ' . $v['nom']),
            '  Adresse électronique : ' . $v['email'],
            '  Numéro de téléphone  : ' . ($v['tel'] !== '' ? $v['tel'] : 'non communiqué'),
            '',
            'Voici son message :',
            '',
            $v['message'],
            '',
            '—',
            'Message reçu le ' . date('d/m/Y') . ' à ' . date('H\hi') . '.',
            'Vous pouvez répondre directement à cet e-mail : votre réponse',
            'parviendra à la personne qui vous a écrit.',
        ];

        return implode("\n", $lignes) . "\n";
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
            'Vous venez de recevoir une demande adressée à la mairie depuis le',
            'formulaire en ligne du site de la commune.',
            '',
            'Coordonnées de la personne :',
            '',
            '  Nom et prénom        : ' . trim($v['prenom'] . ' ' . $v['nom']),
            '  Adresse électronique : ' . $v['email'],
            '  Numéro de téléphone  : ' . ($v['tel'] !== '' ? $v['tel'] : 'non communiqué'),
            '  Adresse dans la commune : ' . ($v['adresse'] !== '' ? $v['adresse'] : 'non précisée'),
            '  Objet de la demande  : ' . ($v['sujet'] !== '' ? $v['sujet'] : 'non précisé'),
            '',
            'Voici le détail de sa demande :',
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

    // ------------------------------------------------------------- erreurs

    public function introuvable(): string
    {
        // Une fiche renommée reste captée par /demarches/{slug} : la route de
        // redirection, déclarée pour l'ancienne adresse, ne serait pas atteinte
        // si le slug d'origine n'existait plus. On consulte donc la table du
        // back-office avant de conclure à une page absente.
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

    // -------------------------------------------------------------- outils




    /**
     * Adresse qui reçoit les formulaires. À défaut de destinataire dédié réglé
     * dans Paramètres, l'e-mail public de la mairie suffit : le site fonctionne
     * ainsi dès la première visite, sans réglage préalable.
     */
    private function destinataire(): string
    {
        return (string) $this->parametres->get('contact.destinataire')
            ?: (string) $this->content->get('site', 'contact.email', '');
    }

    /**
     * @return array<mixed>|null
     */
    private function page(string $cle): ?array
    {
        try {
            return $this->content->load('pages/' . $cle);
        } catch (RuntimeException) {
            return null;
        }
    }
}
