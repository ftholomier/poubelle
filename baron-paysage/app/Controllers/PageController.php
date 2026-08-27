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
            // aperçu de la galerie : les premières photos suffisent, la page
            // « Réalisations » les montre toutes
            'apercu'   => array_slice($this->photosDesGammes(), 0, 8),
        ]);
    }

    public function laSociete(): string
    {
        return $this->rendre('la-societe', 'la-societe', [
            'page' => $this->page('la-societe'),
        ]);
    }

    public function faq(): string
    {
        return $this->rendre('faq', 'faq', [
            'page' => $this->page('faq'),
        ]);
    }

    public function realisations(): string
    {
        return $this->rendre('realisations', 'realisations', [
            'page'       => $this->page('realisations'),
            'items'      => $this->photosDesGammes(),
            'collection' => $this->content->load('realisations'),
        ]);
    }

    /**
     * Toutes les photos de réalisation, catégorie par catégorie.
     *
     * Le contenu stocke une liste de photos par catégorie, chacune avec sa
     * légende. Le nom de la catégorie, qui sert d'étiquette et de filtre,
     * est repris de la fiche de prestation quand il en existe une :
     * renommer une prestation renomme donc son filtre, sans rien avoir à
     * reporter ailleurs.
     *
     * @return array<int, array{image: string, categorie: string, nom: string}>
     */
    private function photosDesGammes(?string $gammeVoulue = null): array
    {
        $galerie = $this->content->load('realisations');
        $gammes  = (array) ($galerie['gammes'] ?? []);
        $noms    = self::categoriesGalerie($this->content);

        $photos = [];
        $vues = [];
        foreach ($gammes as $slug => $images) {
            if ($gammeVoulue !== null && $slug !== $gammeVoulue) {
                continue;
            }
            // une catégorie disparue emporte ses photos : elles n'ont plus
            // de nom à afficher ni, pour une prestation, de page où mener
            if (!isset($noms[$slug])) {
                continue;
            }
            foreach ((array) $images as $photo) {
                // Une entrée est soit un chemin seul, soit un couple
                // chemin + légende. Les deux formes coexistent : le
                // back-office écrit la seconde, la première reste lisible.
                $image   = is_array($photo) ? (string) ($photo['image'] ?? '') : (string) $photo;
                $legende = is_array($photo) ? trim((string) ($photo['legende'] ?? '')) : '';
                if ($image === '') {
                    continue;
                }
                // une même photo rattachée à deux catégories ne doit pas
                // apparaître deux fois dans la galerie générale
                if (isset($vues[$image])) {
                    continue;
                }
                $vues[$image] = true;
                $photos[] = [
                    'image'     => $image,
                    'legende'   => $legende,
                    'categorie' => $noms[$slug],
                    'nom'       => $noms[$slug],
                ];
            }
        }

        return $photos;
    }

    /**
     * Catégories de la galerie : les prestations publiées, plus celles que
     * le contenu déclare lui-même.
     *
     * Toutes les photos de chantier ne se rattachent pas à une prestation —
     * « Le chantier » ou « L'équipe » n'ont pas de page produit. La table
     * `noms` du contenu permet d'en ajouter sans créer de fiche fantôme
     * dans le menu.
     *
     * @return array<string, string> slug => nom affiché
     */
    public static function categoriesGalerie(Content $content): array
    {
        $noms = [];
        foreach ($content->publies('services') as $service) {
            $noms[(string) $service['slug']] = (string) ($service['nom'] ?? '');
        }
        foreach ((array) ($content->load('realisations')['noms'] ?? []) as $slug => $nom) {
            $slug = (string) $slug;
            $nom  = trim((string) $nom);
            if ($slug !== '' && $nom !== '') {
                $noms[$slug] = $nom;
            }
        }

        return $noms;
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
            // Les chantiers cochés pour cette gamme, dans l'ordre du
            // back-office.
            'realisations' => $this->photosDesGammes($slug),
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
            'page' => $this->page('contact'),
        ]);
    }

    /**
     * Page de demande de devis : c'est elle qui porte le formulaire.
     *
     * Contact et devis sont séparés parce qu'ils ne répondent pas à la même
     * intention : trouver une adresse et un numéro d'un côté, engager un
     * projet de l'autre. Mêler les deux dilue la page qui convertit.
     */
    public function devis(): string
    {
        return $this->rendre('devis', 'devis', [
            'page'    => $this->page('devis'),
            'erreurs' => [],
            'valeurs' => [],
        ]);
    }

    /**
     * Traitement du formulaire de demande : validation, puis envoi au
     * destinataire réglé dans Paramètres.
     */
    public function devisEnvoi(): string
    {
        $valeurs = [
            'nom'     => trim((string) ($_POST['nom'] ?? '')),
            'prenom'  => trim((string) ($_POST['prenom'] ?? '')),
            'societe' => trim((string) ($_POST['societe'] ?? '')),
            'email'    => trim((string) ($_POST['email'] ?? '')),
            'tel'      => trim((string) ($_POST['tel'] ?? '')),
            'localite' => trim((string) ($_POST['localite'] ?? '')),
            'message'  => trim((string) ($_POST['message'] ?? '')),
            'sujet'    => trim((string) ($_POST['sujet'] ?? '')),
        ];

        $erreurs = [];
        if ($valeurs['nom'] === '') {
            $erreurs['nom'] = 'Merci d’indiquer votre nom.';
        }
        if (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'Adresse e-mail invalide.';
        }
        if ($valeurs['localite'] === '') {
            $erreurs['localite'] = 'Merci d’indiquer la localité du chantier.';
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
            return $this->rendre('devis', 'devis', [
                'page'    => $this->page('devis'),
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
            return $this->rendre('devis', 'devis', [
                'page'    => $this->page('devis'),
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
            return $this->rendre('devis', 'devis', [
                'page'    => $this->page('devis'),
                'erreurs' => ['envoi' => 'L’envoi a échoué. Merci de réessayer ou de nous appeler.'],
                'valeurs' => $valeurs,
            ]);
        }

        return $this->rendre('contact-confirmation', 'devis', [
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
            'Vous venez de recevoir une nouvelle demande de devis envoyée',
            'depuis le formulaire du site de Baron Paysage.',
            '',
            'Coordonnées de la personne :',
            '',
            '  Nom et prénom        : ' . trim($v['prenom'] . ' ' . $v['nom']),
            '  Société              : ' . ($v['societe'] !== '' ? $v['societe'] : 'non communiquée'),
            '  Adresse électronique : ' . $v['email'],
            '  Numéro de téléphone  : ' . ($v['tel'] !== '' ? $v['tel'] : 'non communiqué'),
            '  Localité du chantier : ' . ($v['localite'] !== '' ? $v['localite'] : 'non précisée'),
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
