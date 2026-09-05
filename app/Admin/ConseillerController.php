<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Conseiller;
use App\Core\Csrf;
use App\Core\Session;
use RuntimeException;
use Throwable;

/**
 * Le conseiller du back-office, côté requêtes.
 *
 * Ces adresses répondent en JSON à la pastille, et non en pages : elles ne
 * rendent aucun gabarit et ne posent aucun message éphémère. Elles sont
 * derrière la même protection que le reste du back-office — voir `$protege`
 * dans app/routes-admin.php —, plus le jeton CSRF, parce qu'un appel au
 * modèle coûte de l'argent : sans jeton, une page tierce ouverte dans un
 * autre onglet ferait dépenser le quota Google de la mairie.
 */
final class ConseillerController
{
    /**
     * Appels au modèle autorisés par heure et par session.
     *
     * L'administrateur est de confiance, mais un script qui boucle ne l'est
     * pas, et la facture est celle de la commune. Trente questions à l'heure
     * est très au-dessus de ce qu'un secrétariat fait à la main.
     */
    private const QUOTA_HEURE = 30;

    public function __construct(private readonly Conseiller $conseiller)
    {
    }

    /** Une question posée depuis la pastille. */
    public function question(): string
    {
        if (!$this->recevable()) {
            return json_response(['erreur' => 'Requête non vérifiée.'], 403);
        }

        $charge = $this->charge();
        $historique = [];
        foreach ((array) ($charge['historique'] ?? []) as $tour) {
            if (is_array($tour)) {
                $historique[] = [
                    'role'  => (string) ($tour['role'] ?? 'user'),
                    'texte' => (string) ($tour['texte'] ?? ''),
                ];
            }
        }

        try {
            $reponse = $this->conseiller->repondre(
                (string) ($charge['question'] ?? ''),
                $historique
            );
        } catch (RuntimeException $e) {
            return json_response(['erreur' => $e->getMessage()], 502);
        }

        return json_response(['reponse' => $reponse]);
    }

    /** La revue complète, lancée par le bouton « Faire le bilan ». */
    public function bilan(): string
    {
        if (!$this->recevable()) {
            return json_response(['erreur' => 'Requête non vérifiée.'], 403);
        }

        try {
            return json_response($this->conseiller->bilan());
        } catch (Throwable $e) {
            return json_response(['erreur' => $e->getMessage()], 502);
        }
    }

    /** Le dernier bilan connu, sans rien redemander à Google. */
    public function dernierBilan(): string
    {
        $bilan = $this->conseiller->dernierBilan();

        return json_response($bilan ?? ['date' => 0, 'recommandations' => []]);
    }

    // ------------------------------------------------------------------ outils

    /** @return array<string, mixed> */
    private function charge(): array
    {
        $brut = (string) file_get_contents('php://input');
        $lu = json_decode($brut, true);

        return is_array($lu) ? $lu : [];
    }

    /**
     * Jeton valide et quota non dépassé.
     *
     * Le jeton est lu dans l'en-tête plutôt que dans le corps : la pastille
     * envoie du JSON, et un formulaire caché n'aurait servi qu'à le
     * transporter.
     */
    private function recevable(): bool
    {
        $jeton = (string) ($_SERVER['HTTP_X_CSRF'] ?? '');

        return Csrf::verifierJeton($jeton) && $this->quota();
    }

    private function quota(): bool
    {
        $heure = (int) date('YmdH');
        $compte = (array) Session::get('_conseiller_quota', []);
        if (($compte['heure'] ?? 0) !== $heure) {
            $compte = ['heure' => $heure, 'appels' => 0];
        }
        if ($compte['appels'] >= self::QUOTA_HEURE) {
            return false;
        }
        $compte['appels']++;
        Session::set('_conseiller_quota', $compte);

        return true;
    }
}
