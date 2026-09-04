<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Publication sur la Page Facebook et le compte Instagram de la commune.
 *
 * Quatre principes, tous hérités de l'assistant, et pour les mêmes raisons.
 *
 * **Tout part du serveur.** Aucun script de Meta n'est chargé dans la page,
 * aucun appel n'est fait depuis le navigateur du visiteur. Une mairie n'a pas
 * le droit de déposer les traceurs d'un tiers chez l'administré, et le
 * back-office lui-même n'a aucune raison de le faire. `traceurs.py` le mesure.
 *
 * **Les jetons sont des secrets.** Ils vivent dans data/admin/parametres.json,
 * hors racine web et hors git, comme le mot de passe SMTP et la clé Gemini.
 * Le secret de l'application n'est jamais réaffiché, seulement remplacé.
 *
 * **Une panne de Meta ne doit pas peser sur le site.** Les appels ont un délai
 * court, ils ne sont faits que depuis le back-office ou la tâche planifiée, et
 * une publication qui échoue est retenue avec son motif plutôt que perdue.
 *
 * **Rien n'est écrit en dur.** L'identifiant et le secret de l'application
 * sont saisis dans le back-office : n'importe quelle commune connecte ses
 * propres comptes, ce qui est le sens de ce dépôt.
 *
 * Ce que Meta impose, et qu'aucun code ne peut contourner :
 *
 *   · l'application doit passer une **revue** avant que les permissions de
 *     publication ne soient accordées. Tant qu'elle n'est pas validée, seuls
 *     les comptes déclarés « testeurs » peuvent publier ;
 *   · Instagram ne publie **rien sans image**, et il télécharge cette image
 *     lui-même : elle doit être accessible en HTTPS depuis l'extérieur. Une
 *     publication Instagram n'est donc pas essayable depuis un poste local ;
 *   · Instagram ne sait pas programmer une publication. C'est pour cela que la
 *     file d'attente est tenue ici plutôt que chez Meta.
 */
final class Reseaux
{
    /** La version est dans le chemin, pas dans un réglage. */
    private const API = 'https://graph.facebook.com/v21.0';
    private const DIALOGUE = 'https://www.facebook.com/v21.0/dialog/oauth';

    private const DELAI_RESEAU = 25;

    /**
     * Les permissions demandées au dialogue de connexion.
     *
     * Elles sont le strict nécessaire, et c'est ce qui fait passer une revue :
     * Meta refuse une demande dont il ne voit pas l'usage à l'écran.
     *
     *   · pages_show_list        — lister les Pages pour en choisir une
     *   · pages_read_engagement  — lire le nom de la Page et son compte lié
     *   · pages_manage_posts     — publier sur la Page
     *   · instagram_basic        — retrouver le compte Instagram de la Page
     *   · instagram_content_publish — publier sur ce compte
     */
    public const PERMISSIONS = [
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_posts',
        'instagram_basic',
        'instagram_content_publish',
    ];

    /** Facebook tronque au-delà ; Instagram refuse au-delà de 2 200. */
    public const TEXTE_MAX = 2000;

    public function __construct(private readonly Parametres $parametres)
    {
    }

    // ------------------------------------------------------------------ état

    public function identifiantApplication(): string
    {
        return trim((string) $this->parametres->get('reseaux.application', ''));
    }

    private function secretApplication(): string
    {
        return trim((string) $this->parametres->get('reseaux.secret', ''));
    }

    public function jetonPage(): string
    {
        return trim((string) $this->parametres->get('reseaux.jeton_page', ''));
    }

    public function pageId(): string
    {
        return trim((string) $this->parametres->get('reseaux.page_id', ''));
    }

    public function pageNom(): string
    {
        return trim((string) $this->parametres->get('reseaux.page_nom', ''));
    }

    public function instagramId(): string
    {
        return trim((string) $this->parametres->get('reseaux.instagram_id', ''));
    }

    public function instagramNom(): string
    {
        return trim((string) $this->parametres->get('reseaux.instagram_nom', ''));
    }

    /** L'application est-elle renseignée, donc la connexion possible ? */
    public function applicationPrete(): bool
    {
        return $this->identifiantApplication() !== '' && $this->secretApplication() !== '';
    }

    public function facebookPret(): bool
    {
        return $this->jetonPage() !== '' && $this->pageId() !== '';
    }

    public function instagramPret(): bool
    {
        return $this->facebookPret() && $this->instagramId() !== '';
    }

    /**
     * Ce qui manque, en clair, pour que l'écran le dise au lieu d'échouer.
     *
     * @return list<string>
     */
    public function manques(): array
    {
        $manques = [];
        if (!$this->applicationPrete()) {
            $manques[] = 'L’identifiant et le secret de l’application Meta ne sont pas renseignés.';
        } elseif (!$this->facebookPret()) {
            $manques[] = 'Aucune Page Facebook n’est connectée.';
        } elseif (!$this->instagramPret()) {
            $manques[] = 'Aucun compte Instagram professionnel n’est rattaché à cette Page : '
                       . 'seule la publication Facebook est possible.';
        }

        return $manques;
    }

    // -------------------------------------------------------------- connexion

    /**
     * L'adresse du dialogue de connexion Meta.
     *
     * Le jeton d'état est tiré au sort et rangé en session : c'est lui qui
     * empêche qu'une adresse de retour fabriquée par un tiers ne fasse
     * connecter la Page de quelqu'un d'autre. Il est vérifié au retour.
     */
    public function urlConnexion(string $retour): string
    {
        if (!$this->applicationPrete()) {
            throw new RuntimeException('Renseignez d’abord l’identifiant et le secret de l’application.');
        }

        $etat = bin2hex(random_bytes(16));
        Session::set('reseaux_etat', $etat);

        return self::DIALOGUE . '?' . http_build_query([
            'client_id'     => $this->identifiantApplication(),
            'redirect_uri'  => $retour,
            'state'         => $etat,
            'response_type' => 'code',
            'scope'         => implode(',', self::PERMISSIONS),
        ]);
    }

    /**
     * Échange le code contre un jeton, et rend les Pages administrées.
     *
     * Le jeton d'utilisateur obtenu ici est de courte durée ; il est aussitôt
     * échangé contre un jeton long. Celui des Pages, lui, n'expire pas tant
     * que le mot de passe du compte ne change pas — c'est celui qu'on garde.
     *
     * @return list<array{id: string, nom: string, jeton: string}>
     */
    public function pagesDisponibles(string $code, string $retour, string $etatRecu): array
    {
        $attendu = (string) Session::get('reseaux_etat', '');
        Session::oublier('reseaux_etat');
        if ($attendu === '' || !hash_equals($attendu, $etatRecu)) {
            throw new RuntimeException(
                'La réponse de Facebook ne correspond pas à la demande partie d’ici. '
                . 'Rien n’a été connecté. Recommencez depuis cet écran.'
            );
        }

        $court = $this->lire('/oauth/access_token', [
            'client_id'     => $this->identifiantApplication(),
            'client_secret' => $this->secretApplication(),
            'redirect_uri'  => $retour,
            'code'          => $code,
        ]);
        $jeton = (string) ($court['access_token'] ?? '');
        if ($jeton === '') {
            throw new RuntimeException('Facebook n’a pas renvoyé de jeton.');
        }

        $long = $this->lire('/oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $this->identifiantApplication(),
            'client_secret'     => $this->secretApplication(),
            'fb_exchange_token' => $jeton,
        ]);
        $jeton = (string) ($long['access_token'] ?? $jeton);

        $comptes = $this->lire('/me/accounts', [
            'access_token' => $jeton,
            'fields'       => 'id,name,access_token',
            'limit'        => 100,
        ]);

        $pages = [];
        foreach ($comptes['data'] ?? [] as $p) {
            if (($p['id'] ?? '') === '' || ($p['access_token'] ?? '') === '') {
                continue;
            }
            $pages[] = [
                'id'    => (string) $p['id'],
                'nom'   => (string) ($p['name'] ?? $p['id']),
                'jeton' => (string) $p['access_token'],
            ];
        }

        if ($pages === []) {
            throw new RuntimeException(
                'Ce compte n’administre aucune Page Facebook. Créez la Page de la '
                . 'commune, ou connectez-vous avec le compte qui l’administre.'
            );
        }

        return $pages;
    }

    /**
     * Retient la Page choisie, et cherche le compte Instagram qui lui est lié.
     *
     * L'absence de compte Instagram n'est pas une erreur : beaucoup de mairies
     * n'en ont pas. Facebook fonctionne seul, et l'écran le dit.
     *
     * @param array{id: string, nom: string, jeton: string} $page
     */
    public function retenirPage(array $page): void
    {
        $instagramId = '';
        $instagramNom = '';
        try {
            $lien = $this->lire('/' . $page['id'], [
                'access_token' => $page['jeton'],
                'fields'       => 'instagram_business_account{id,username}',
            ]);
            $instagramId  = (string) ($lien['instagram_business_account']['id'] ?? '');
            $instagramNom = (string) ($lien['instagram_business_account']['username'] ?? '');
        } catch (RuntimeException) {
            // Un compte Instagram absent ou une permission non encore accordée
            // ne doit pas empêcher de connecter Facebook.
        }

        $this->enregistrer([
            'jeton_page'     => $page['jeton'],
            'page_id'        => $page['id'],
            'page_nom'       => $page['nom'],
            'instagram_id'   => $instagramId,
            'instagram_nom'  => $instagramNom,
            'connecte_le'    => date('c'),
        ]);
    }

    /**
     * La clé qui autorise la tâche planifiée, créée au premier besoin.
     *
     * L'adresse de dépilage n'est pas derrière la session : un cron n'a pas
     * de session. Elle est donc protégée par cette clé, tirée au sort et
     * conservée avec les réglages — au même titre qu'un mot de passe SMTP, et
     * selon le même principe que le secret anti-spam.
     */
    public function cleTache(): string
    {
        $cle = trim((string) $this->parametres->get('reseaux.cle_tache', ''));
        if ($cle !== '') {
            return $cle;
        }

        $cle = bin2hex(random_bytes(24));
        try {
            $this->enregistrer(['cle_tache' => $cle]);
        } catch (RuntimeException $e) {
            // Sur une installation en lecture seule, la clé changerait à
            // chaque requête et la tâche ne pourrait jamais s'authentifier :
            // mieux vaut le dire dans le journal du serveur.
            error_log('Clé de tâche des réseaux non conservée : ' . $e->getMessage());
        }

        return $cle;
    }

    /** La clé reçue est-elle la bonne ? Comparaison à temps constant. */
    public function cleTacheValide(string $recue): bool
    {
        $attendue = trim((string) $this->parametres->get('reseaux.cle_tache', ''));

        return $attendue !== '' && hash_equals($attendue, $recue);
    }

    /** Oublie les comptes connectés, sans toucher à l'application. */
    public function deconnecter(): void
    {
        $this->enregistrer([
            'jeton_page'    => '',
            'page_id'       => '',
            'page_nom'      => '',
            'instagram_id'  => '',
            'instagram_nom' => '',
            'connecte_le'   => '',
        ]);
    }

    /** @param array<string, mixed> $valeurs */
    private function enregistrer(array $valeurs): void
    {
        $tout = $this->parametres->tout();
        $tout['reseaux'] = array_merge($tout['reseaux'] ?? [], $valeurs);
        $this->parametres->enregistrer($tout);
    }

    // ------------------------------------------------------------ publication

    /**
     * Publie sur Facebook. Rend l'identifiant du post.
     *
     * Avec une image, c'est une publication photo ; sans, une publication de
     * lien ou de texte. Les deux ne passent pas par le même point d'entrée
     * chez Meta, et forcer l'un pour l'autre donne un post sans aperçu.
     */
    public function publierFacebook(string $texte, string $imageUrl = '', string $lien = '', int $quand = 0): string
    {
        if (!$this->facebookPret()) {
            throw new RuntimeException('Aucune Page Facebook connectée.');
        }

        $champs = ['access_token' => $this->jetonPage()];
        if ($quand > 0) {
            // Facebook sait attendre lui-même : on lui confie la date plutôt
            // que de garder la publication ici. Il exige entre dix minutes et
            // six mois d'avance, et refuse tout le reste.
            $champs['published'] = 'false';
            $champs['scheduled_publish_time'] = (string) $quand;
        }

        if ($imageUrl !== '') {
            $champs['url'] = $imageUrl;
            $champs['caption'] = mb_substr($texte, 0, self::TEXTE_MAX);
            $reponse = $this->ecrire('/' . $this->pageId() . '/photos', $champs);
        } else {
            $champs['message'] = mb_substr($texte, 0, self::TEXTE_MAX);
            if ($lien !== '') {
                $champs['link'] = $lien;
            }
            $reponse = $this->ecrire('/' . $this->pageId() . '/feed', $champs);
        }

        $id = (string) ($reponse['post_id'] ?? $reponse['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Facebook n’a pas renvoyé d’identifiant de publication.');
        }

        return $id;
    }

    /**
     * Publie sur Instagram. Rend l'identifiant du média.
     *
     * En deux temps, et ce n'est pas un choix : Meta demande d'abord de
     * déposer un conteneur, puis de le publier. Entre les deux, il télécharge
     * l'image lui-même — d'où l'exigence d'une adresse publique.
     */
    public function publierInstagram(string $texte, string $imageUrl): string
    {
        if (!$this->instagramPret()) {
            throw new RuntimeException('Aucun compte Instagram professionnel connecté.');
        }
        if ($imageUrl === '') {
            throw new RuntimeException('Instagram n’accepte aucune publication sans image.');
        }
        if (!str_starts_with($imageUrl, 'https://')) {
            throw new RuntimeException(
                'Instagram télécharge l’image lui-même : elle doit être accessible en '
                . 'HTTPS depuis l’extérieur. Adresse reçue : ' . $imageUrl
            );
        }

        $conteneur = $this->ecrire('/' . $this->instagramId() . '/media', [
            'access_token' => $this->jetonPage(),
            'image_url'    => $imageUrl,
            'caption'      => mb_substr($texte, 0, self::TEXTE_MAX),
        ]);
        $id = (string) ($conteneur['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Instagram n’a pas accepté l’image.');
        }

        $publie = $this->ecrire('/' . $this->instagramId() . '/media_publish', [
            'access_token' => $this->jetonPage(),
            'creation_id'  => $id,
        ]);

        return (string) ($publie['id'] ?? $id);
    }

    /** L'adresse d'un post, pour que l'historique soit cliquable. */
    public function lienPublication(string $reseau, string $id): string
    {
        if ($id === '') {
            return '';
        }

        return $reseau === 'instagram'
            ? 'https://www.instagram.com/' . rawurlencode($this->instagramNom())
            : 'https://www.facebook.com/' . rawurlencode($id);
    }

    // ------------------------------------------------------------------ HTTP

    /**
     * @param array<string, scalar> $champs
     * @return array<string, mixed>
     */
    private function lire(string $chemin, array $champs): array
    {
        return $this->appeler(self::API . $chemin . '?' . http_build_query($champs), null);
    }

    /**
     * @param array<string, scalar> $champs
     * @return array<string, mixed>
     */
    private function ecrire(string $chemin, array $champs): array
    {
        return $this->appeler(self::API . $chemin, http_build_query($champs));
    }

    /** @return array<string, mixed> */
    private function appeler(string $url, ?string $corps): array
    {
        [$code, $reponse] = $this->requete($url, $corps);
        $json = json_decode($reponse, true);
        if (!is_array($json)) {
            throw new RuntimeException('Réponse illisible de Meta (code ' . $code . ').');
        }
        if ($code >= 400 || isset($json['error'])) {
            throw new RuntimeException($this->message($json));
        }

        return $json;
    }

    /**
     * Le message d'erreur de Meta, traduit en quelque chose d'actionnable.
     *
     * Les codes qui reviennent vraiment sont peu nombreux, et le message brut
     * de Meta est en anglais et souvent muet sur la cause. Une secrétaire de
     * mairie doit lire ce qu'elle a à faire, pas un numéro.
     *
     * @param array<string, mixed> $json
     */
    private function message(array $json): string
    {
        $erreur = is_array($json['error'] ?? null) ? $json['error'] : [];
        $code   = (int) ($erreur['code'] ?? 0);
        $brut   = trim((string) ($erreur['message'] ?? 'Erreur inconnue.'));

        $connu = match ($code) {
            190 => 'La connexion à Facebook a expiré ou été révoquée. Reconnectez la Page depuis cet écran.',
            200, 10 => 'L’application n’a pas encore la permission de publier. '
                     . 'Tant que Meta n’a pas validé la revue, seuls les comptes déclarés '
                     . 'testeurs dans l’application peuvent publier.',
            100 => 'Meta a refusé un paramètre de la publication.',
            368, 613 => 'Meta a temporairement bloqué les publications de cette Page. Réessayez plus tard.',
            default => '',
        };

        return $connu !== '' ? $connu . ' (Meta : ' . $brut . ')' : 'Meta : ' . $brut;
    }

    /** @return array{int, string} */
    private function requete(string $url, ?string $corps): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::DELAI_RESEAU,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            if ($corps !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $corps);
            }

            $reponse = curl_exec($ch);
            $code    = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $erreur  = curl_error($ch);
            curl_close($ch);

            if ($reponse === false) {
                throw new RuntimeException('Meta est injoignable : ' . ($erreur ?: 'délai dépassé') . '.');
            }

            return [$code, (string) $reponse];
        }

        $contexte = stream_context_create(['http' => [
            'method'        => $corps !== null ? 'POST' : 'GET',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => $corps ?? '',
            'timeout'       => self::DELAI_RESEAU,
            'ignore_errors' => true,
        ]]);

        $reponse = @file_get_contents($url, false, $contexte);
        if ($reponse === false) {
            throw new RuntimeException('Meta est injoignable (les connexions sortantes sont peut-être bloquées).');
        }

        $code = 0;
        foreach ($http_response_header ?? [] as $entete) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $entete, $m) === 1) {
                $code = (int) $m[1];
            }
        }

        return [$code, (string) $reponse];
    }
}
