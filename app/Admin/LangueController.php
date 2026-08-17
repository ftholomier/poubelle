<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Content;
use App\Core\Csrf;
use App\Core\Langues;
use App\Core\Session;
use App\Core\TraductionAuto;
use App\Core\Traducteur;
use App\Core\View;
use RuntimeException;

/**
 * Langues du site et traductions.
 *
 * La traduction automatique ne sert qu'à obtenir une première version : le
 * résultat est enregistré sur disque, relisible et corrigeable ici même. Le
 * site public ne fait jamais appel au service extérieur.
 */
final class LangueController
{
    /**
     * Contenus proposés à la traduction, avec leur nom d'écran.
     */
    private const SOURCES = [
        'site'                    => 'Coordonnées et menu',
        'pages/accueil'           => 'Page d’accueil',
        'hebergements'            => 'Hébergements',
        'pages/hebergements'      => 'Page Hébergements',
        'peche'                   => 'Étangs de pêche',
        'pages/peche'             => 'Page Pêche',
        'pages/boutique'          => 'Boutique',
        'galerie'                 => 'Galerie',
        'pages/galerie'           => 'Page Galerie',
        'pages/contact'           => 'Page Contact',
        'pages/reglement'         => 'Règlement général',
        'pages/mentions-legales'  => 'Mentions légales',
    ];

    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly Langues $langues,
        private readonly Traducteur $traducteur,
        private readonly TraductionAuto $auto,
        private readonly string $dossierGabarits,
    ) {
    }

    private function rediriger(string $chemin = '/admin/langues'): string
    {
        header('Location: ' . url($chemin), true, 303);
        return '';
    }

    /**
     * Tous les textes traduisibles, regroupés par origine.
     *
     * @return array<string, array<string, string>> groupe => (clé => français)
     */
    private function sources(): array
    {
        $groupes = ['Textes des pages (boutons, formulaires)' => Traducteur::interface($this->dossierGabarits)];

        foreach (self::SOURCES as $nom => $titre) {
            try {
                $textes = Traducteur::extraire($this->content->load($nom), $nom);
            } catch (RuntimeException) {
                continue;
            }
            if ($textes !== []) {
                $groupes[$titre] = $textes;
            }
        }

        return $groupes;
    }

    /**
     * @return array<string, string>
     */
    private function toutesLesSources(): array
    {
        return array_merge(...array_values($this->sources()));
    }

    // ------------------------------------------------------------- écrans

    public function ecran(): string
    {
        $sources = $this->toutesLesSources();
        $total = count($sources);

        $etat = [];
        foreach ($this->langues->toutes() as $code => $langue) {
            if ($code === Langues::SOURCE) {
                continue;
            }
            $faits = 0;
            $traduits = $this->traducteur->tout($code);
            foreach (array_keys($sources) as $cle) {
                if (($traduits[$cle] ?? '') !== '') {
                    $faits++;
                }
            }
            $etat[$code] = $langue + [
                'traduits'   => $faits,
                'total'      => $total,
                'pourcent'   => $total > 0 ? (int) round($faits / $total * 100) : 0,
            ];
        }

        return $this->view->render('admin/langues', [
            'page'        => ['titre' => 'Langues'],
            'etatLangues' => $etat,
            'total'       => $total,
            'proposees'   => array_diff_key(Langues::CONNUES, $this->langues->toutes()),
        ], 'admin/layout');
    }

    public function traductions(string $code): string
    {
        if (!$this->langues->existe($code) || $code === Langues::SOURCE) {
            return $this->rediriger();
        }

        return $this->view->render('admin/traductions', [
            'page'     => ['titre' => 'Traduction — ' . $this->langues->nom($code)],
            'code'     => $code,
            'nom'      => $this->langues->nom($code),
            'enLigne'  => $this->langues->estPubliee($code),
            'groupes'  => $this->sources(),
            'traduits' => $this->traducteur->tout($code),
        ], 'admin/layout');
    }

    // ---------------------------------------------------------- écritures

    public function ajouter(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            $code = Langues::normaliserCode((string) ($_POST['code'] ?? ''));
            $nom = $this->langues->ajouter($code, (string) ($_POST['nom'] ?? ''));
            Session::flash('succes', $nom . ' a été ajouté, hors ligne. Lancez la traduction, '
                . 'relisez-la, puis mettez la langue en ligne.');
            return $this->rediriger('/admin/langues/' . rawurlencode($code));
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    public function basculer(string $code): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            $enLigne = $this->langues->basculer($code);
            Session::flash('succes', $this->langues->nom($code) . ($enLigne
                ? ' est en ligne : le site est accessible sur /' . $code . '.'
                : ' est hors ligne : /' . $code . ' ne répond plus.'));
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    public function supprimer(string $code): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            $nom = $this->langues->nom($code);
            $this->langues->supprimer($code);
            $this->traducteur->oublier($code);
            Session::flash('succes', $nom . ' a été supprimé, avec ses traductions.');
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    public function enregistrer(string $code): string
    {
        if (!Csrf::verifier() || !$this->langues->existe($code)) {
            return $this->rediriger();
        }

        $recus = (array) ($_POST['textes'] ?? []);
        $textes = [];
        foreach ($recus as $cle => $valeur) {
            if (is_string($cle) && is_string($valeur)) {
                $textes[$cle] = $valeur;
            }
        }

        try {
            // on repart des traductions existantes : l'écran peut n'en
            // présenter qu'une partie, et le reste ne doit pas disparaître
            $this->traducteur->enregistrer($code, array_merge($this->traducteur->tout($code), $textes));
            Session::flash('succes', 'Traductions enregistrées.');
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger('/admin/langues/' . rawurlencode($code));
    }

    /**
     * Traduction automatique : par défaut ce qui manque seulement, afin de
     * ne pas écraser une relecture faite à la main.
     */
    public function auto(string $code): string
    {
        if (!Csrf::verifier() || !$this->langues->existe($code) || $code === Langues::SOURCE) {
            return $this->rediriger();
        }

        $cible = '/admin/langues/' . rawurlencode($code);
        $tout = ($_POST['portee'] ?? '') === 'tout';
        $dejaFaits = $this->traducteur->tout($code);

        $aFaire = [];
        foreach ($this->toutesLesSources() as $cle => $francais) {
            if ($tout || ($dejaFaits[$cle] ?? '') === '') {
                $aFaire[$cle] = $francais;
            }
        }

        if ($aFaire === []) {
            Session::flash('succes', 'Tout est déjà traduit. Rien à faire.');
            return $this->rediriger($cible);
        }

        // la traduction d'un site entier dépasse le temps d'exécution par
        // défaut de bien des hébergements
        @set_time_limit(300);

        $resultat = $this->auto->traduire($aFaire, $code);

        if ($resultat['textes'] === []) {
            Session::flash('erreur', 'Le service de traduction n’a rien renvoyé. '
                . 'Il est peut-être momentanément indisponible, ou le serveur n’a pas '
                . 'accès à Internet. Réessayez, ou traduisez à la main.');
            return $this->rediriger($cible);
        }

        try {
            $this->traducteur->fusionner($code, $resultat['textes']);
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
            return $this->rediriger($cible);
        }

        $message = count($resultat['textes']) . ' texte(s) traduits via ' . $resultat['service'] . '.';
        if ($resultat['echecs'] > 0) {
            $message .= ' ' . $resultat['echecs'] . ' n’ont pas abouti : relancez pour les reprendre.';
        }
        $message .= ' Relisez avant de mettre la langue en ligne — une traduction automatique'
            . ' se trompe souvent sur les noms propres.';
        Session::flash('succes', $message);

        return $this->rediriger($cible);
    }
}
