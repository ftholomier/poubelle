<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Protection des formulaires publics contre les envois automatisés.
 *
 * Le choix de fond : trois barrières natives, qui ne dépendent d'aucun tiers.
 * Un captcha externe est un contenu tiers ; ce site en soumet l'affichage au
 * consentement, si bien qu'un visiteur qui refuse les cookies verrait le
 * captcha ne jamais se charger — et le formulaire refuser son envoi. La
 * protection tomberait précisément sur les visiteurs les plus soucieux de
 * leur vie privée. D'où un socle qui fonctionne sans rien demander :
 *
 *   1. le piège : un champ que l'œil ne voit pas et qu'un robot remplit ;
 *   2. l'horloge : un jeton signé qui date l'affichage du formulaire.
 *      Écrire un message prend plus de trois secondes ; un robot poste dans
 *      la seconde. La signature interdit de fabriquer la date soi-même, et
 *      la péremption interdit de rejouer indéfiniment le même jeton ;
 *   3. le quota : passé quelques messages partis dans l'heure depuis la
 *      même adresse, on refuse. C'est ce qui arrête le robot qui aurait
 *      franchi les deux premières barrières, puisqu'un robot ne vaut que
 *      par le nombre. Seuls les messages réellement partis sont comptés :
 *      compter les tentatives refusées enfermerait dehors le visiteur qui
 *      s'est trompé cinq fois de suite dans son adresse.
 *
 * Un quatrième étage, facultatif, s'ajoute si une clé Cloudflare Turnstile
 * est réglée dans Paramètres. Il reste éteint tant qu'aucune clé n'est
 * saisie, et le socle ci-dessus protège seul entre-temps.
 */
final class Antispam
{
    /** Délai minimal entre l'affichage du formulaire et son envoi. */
    private const DELAI_MINIMAL = 3;

    /** Au-delà, le jeton est périmé : la page a dormi trop longtemps. */
    private const DUREE_JETON = 7200;

    /** Messages partis tolérés par adresse et par heure. */
    private const QUOTA_HORAIRE = 5;

    private const VERIFICATION_TURNSTILE = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly Parametres $parametres,
        private readonly string $fichierQuotas,
    ) {
    }

    /**
     * Champs à insérer dans le formulaire, juste avant le bouton d'envoi.
     */
    public function champs(): string
    {
        $instant = (string) time();
        $signature = $this->signer($instant);

        return '<div class="piege" aria-hidden="true">'
            . '<label for="f-site">Ne pas remplir</label>'
            . '<input id="f-site" type="text" name="site" tabindex="-1" autocomplete="off">'
            . '</div>'
            . '<input type="hidden" name="_ouvert" value="' . e($instant . '.' . $signature) . '">';
    }

    /**
     * Le widget Turnstile, ou rien du tout si aucune clé n'est réglée.
     *
     * Le script est chargé sans passer par la barrière de consentement :
     * Turnstile ne dépose pas de cookie et ne sert qu'à la sécurité du
     * formulaire, ce qui le place hors du champ du consentement préalable —
     * à la différence d'un reCAPTCHA, qui profile le visiteur pour le
     * compte d'un tiers. Il reste à le mentionner dans les mentions légales.
     */
    public function widget(): string
    {
        $cle = (string) $this->parametres->get('antispam.cle_site', '');
        if ($cle === '') {
            return '';
        }

        // La politique de sécurité n'accorde Cloudflare qu'aux pages qui
        // chargent réellement le test : elle est construite après le rendu.
        Entetes::autoriserProtecteur();

        return '<div class="cf-turnstile" data-sitekey="' . e($cle) . '" data-language="auto"></div>'
            . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }

    /**
     * Contrôle un envoi. Renvoie null s'il passe, sinon le message à
     * afficher.
     *
     * Le message reste volontairement vague : dire à un robot laquelle des
     * barrières l'a arrêté, c'est lui indiquer par où passer.
     */
    public function verifier(): ?string
    {
        $refus = 'Votre envoi n’a pas pu être vérifié. Merci de réessayer, '
            . 'ou de nous appeler si cela se reproduit.';

        if (trim((string) ($_POST['site'] ?? '')) !== '') {
            return $refus;
        }

        if (!$this->horlogeValide((string) ($_POST['_ouvert'] ?? ''))) {
            return $refus;
        }

        if ($this->comptePourCetteAdresse() >= self::QUOTA_HORAIRE) {
            return 'Vous avez envoyé plusieurs demandes coup sur coup. '
                . 'Merci de patienter une heure, ou de nous appeler.';
        }

        if (!$this->turnstileValide()) {
            return $refus;
        }

        return null;
    }

    /**
     * Le jeton date-t-il d'assez longtemps, et vient-il bien de nous ?
     */
    private function horlogeValide(string $jeton): bool
    {
        $morceaux = explode('.', $jeton, 2);
        if (count($morceaux) !== 2 || !ctype_digit($morceaux[0])) {
            return false;
        }

        [$instant, $signature] = $morceaux;
        if (!hash_equals($this->signer($instant), $signature)) {
            return false;
        }

        $age = time() - (int) $instant;

        return $age >= self::DELAI_MINIMAL && $age <= self::DUREE_JETON;
    }

    /**
     * Consigne un message effectivement parti. À appeler après l'envoi, et
     * seulement là : c'est ce qui distingue le quota d'un compteur d'échecs.
     */
    public function enregistrerEnvoi(): void
    {
        $empreinte = $this->empreinte();
        if ($empreinte === null) {
            return;
        }

        $registre = $this->registre();
        $registre[$empreinte][] = time();
        $this->ecrire($registre);
    }

    /**
     * Messages partis dans l'heure écoulée depuis cette adresse.
     */
    private function comptePourCetteAdresse(): int
    {
        $empreinte = $this->empreinte();

        return $empreinte === null ? 0 : count($this->registre()[$empreinte] ?? []);
    }

    /**
     * L'adresse, hachée.
     *
     * Le registre n'a pas à conserver en clair une donnée qui identifie une
     * personne, alors qu'un condensat suffit à reconnaître deux envois venus
     * du même endroit. Le sel est le secret de signature, donc propre à cette
     * installation : le condensat n'est pas rapprochable d'un autre site.
     */
    private function empreinte(): ?string
    {
        $adresse = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return $adresse === '' ? null : hash_hmac('sha256', $adresse, $this->secret());
    }

    /**
     * Le registre, expurgé de tout ce qui a plus d'une heure — ce qui suffit
     * à l'empêcher de grossir avec le trafic.
     *
     * @return array<string, array<int>>
     */
    private function registre(): array
    {
        $brut = is_file($this->fichierQuotas)
            ? json_decode((string) file_get_contents($this->fichierQuotas), true)
            : null;
        if (!is_array($brut)) {
            return [];
        }

        $maintenant = time();
        $registre = [];
        foreach ($brut as $cle => $instants) {
            $recents = array_values(array_filter(
                (array) $instants,
                static fn($t): bool => is_int($t) && $maintenant - $t < 3600
            ));
            if ($recents !== []) {
                $registre[(string) $cle] = $recents;
            }
        }

        return $registre;
    }

    /**
     * @param array<string, array<int>> $registre
     */
    private function ecrire(array $registre): void
    {
        $dossier = dirname($this->fichierQuotas);
        if (!is_dir($dossier)) {
            $ancien = umask(0);
            @mkdir($dossier, Permissions::DOSSIER, true);
            umask($ancien);
        }
        if (!is_dir($dossier)) {
            return;                       // le quota se perd, l'envoi passe
        }

        $json = json_encode($registre, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        $tmp = $this->fichierQuotas . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $this->fichierQuotas)) {
            @unlink($tmp);
        }
    }

    /**
     * Vrai si Turnstile est éteint, ou s'il a validé la réponse.
     *
     * Une panne du service ne bloque pas le formulaire : les trois barrières
     * natives tiennent toujours, et un visiteur légitime qui ne peut plus
     * écrire coûte plus cher qu'un robot qui passe.
     */
    private function turnstileValide(): bool
    {
        $secret = (string) $this->parametres->get('antispam.cle_secrete', '');
        if ($secret === '' || (string) $this->parametres->get('antispam.cle_site', '') === '') {
            return true;
        }

        $reponse = (string) ($_POST['cf-turnstile-response'] ?? '');
        if ($reponse === '') {
            return false;
        }

        $corps = http_build_query([
            'secret'   => $secret,
            'response' => $reponse,
            'remoteip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);

        $flux = @file_get_contents(self::VERIFICATION_TURNSTILE, false, stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content'       => $corps,
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
        ]));

        if ($flux === false) {
            error_log('Turnstile injoignable : le formulaire passe sur ses seules barrières natives.');
            return true;
        }

        $verdict = json_decode($flux, true);

        return is_array($verdict) && ($verdict['success'] ?? false) === true;
    }

    private function signer(string $charge): string
    {
        return hash_hmac('sha256', $charge, $this->secret());
    }

    /**
     * Secret de signature, créé au premier besoin et conservé avec les
     * réglages — au même titre qu'un mot de passe SMTP, donc hors racine web
     * et hors git.
     */
    private function secret(): string
    {
        $secret = (string) $this->parametres->get('antispam.secret', '');
        if ($secret !== '') {
            return $secret;
        }

        $secret = bin2hex(random_bytes(32));
        $tout = $this->parametres->tout();
        $tout['antispam']['secret'] = $secret;
        try {
            $this->parametres->enregistrer($tout);
            return $secret;
        } catch (Throwable $e) {
            error_log('Secret anti-spam non conservé : ' . $e->getMessage());
        }

        // Repli déterministe. Sur une installation en lecture seule, un
        // secret tiré au hasard changerait à chaque requête : le jeton signé
        // à l'affichage ne serait plus reconnu à l'envoi, et le formulaire
        // refuserait tout le monde. Celui-ci est stable faute d'être
        // imprévisible — il ne scelle qu'une date, pas une session.
        return hash('sha256', 'antispam|' . $this->fichierQuotas . '|' . PHP_VERSION);
    }
}
