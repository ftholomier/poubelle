<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Content;
use App\Core\Csrf;
use App\Core\Langues;
use App\Core\Parametres;
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
    /** Offre gratuite de DeepL : un million de caractères pour la vie du compte. */
    private const DEEPL_QUOTA = 1000000;

    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly Langues $langues,
        private readonly Traducteur $traducteur,
        private readonly TraductionAuto $auto,
        private readonly string $dossierGabarits,
        private readonly Parametres $parametres,
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

        foreach (Contenus::tout() as $nom => $titre) {
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

    /**
     * Clé DeepL : sans elle, la traduction dépend de services gratuits dont
     * le quota se compte par adresse IP — donc partagé, sur un hébergement
     * mutualisé, avec tous les autres sites de la machine.
     */
    public function cleEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        // un copier-coller depuis la documentation embarque parfois l'en-tête
        $cle = trim((string) ($_POST['cle_deepl'] ?? ''));
        $cle = trim(preg_replace('/^DeepL-Auth-Key\s+/i', '', $cle) ?? $cle);

        $gratuits = isset($_POST['gratuits']);

        $tout = $this->parametres->tout();
        $tout['traduction']['cle_deepl'] = $cle;
        $tout['traduction']['gratuits']  = $gratuits;
        $this->parametres->enregistrer($tout);

        if ($cle === '') {
            Session::flash('succes', $gratuits
                ? 'Clé retirée : la traduction passera par les services gratuits.'
                : 'Clé retirée, et les services gratuits ne sont pas autorisés : '
                . 'la traduction automatique est désormais hors service. '
                . 'Les traductions déjà faites restent en place.');
            return $this->rediriger();
        }

        // vérifier tout de suite : autrement la clé n'est jugée qu'au moment
        // où les services gratuits ont déjà échoué, c'est-à-dire au plus
        // mauvais moment pour découvrir qu'elle ne marche pas
        $essai = (new TraductionAuto($cle))->verifierDeepL();
        $this->comptabiliserDeepL((int) $essai['caracteres']);

        if ($essai['ok']) {
            Session::flash('succes', 'Clé DeepL enregistrée et vérifiée : le service répond. '
                . 'Elle ne servira qu\'en dernier recours, si Google et MyMemory refusent.');
        } else {
            Session::flash('erreur', 'Clé enregistrée, mais DeepL l’a refusée. ' . $essai['souci']);
        }

        return $this->rediriger();
    }

    /**
     * Le quota DeepL gratuit ne se recharge jamais : ce que l'on y prend est
     * pris pour de bon, d'où un compteur tenu de notre côté. DeepL expose
     * bien le sien, mais le demander suppose que le service réponde — et
     * c'est précisément quand il ne répond plus qu'on veut savoir où l'on en
     * est.
     */
    private function comptabiliserDeepL(int $caracteres): void
    {
        if ($caracteres <= 0) {
            return;
        }

        $tout = $this->parametres->tout();
        $tout['traduction']['deepl_caracteres'] =
            (int) ($tout['traduction']['deepl_caracteres'] ?? 0) + $caracteres;
        $this->parametres->enregistrer($tout);
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
            'cleDeepL'    => (string) $this->parametres->get('traduction.cle_deepl', ''),
            'gratuitsAutorises' => (bool) $this->parametres->get('traduction.gratuits', false),
            'deepLUtilise' => (int) $this->parametres->get('traduction.deepl_caracteres', 0),
            'deepLQuota'   => self::DEEPL_QUOTA,
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
        $this->comptabiliserDeepL((int) ($resultat['deepl'] ?? 0));

        if ($resultat['textes'] === []) {
            $souci = $resultat['souci'] ?? '';
            Session::flash('erreur', 'Le service de traduction n’a rien renvoyé. '
                . 'Il est peut-être momentanément indisponible, limité en débit, ou le '
                . 'serveur n’a pas accès à Internet. Réessayez plus tard, ou traduisez à '
                . 'la main.'
                . ($souci !== '' ? ' Détail technique : ' . $souci : ''));
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
