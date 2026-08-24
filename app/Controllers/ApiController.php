<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Assistant;
use App\Core\Content;
use App\Core\Csrf;
use App\Core\Session;
use RuntimeException;

/**
 * API JSON en lecture seule. Elle sert déjà le front pour les parties
 * dynamiques (galerie, filtres) et constituera la surface que le back-office
 * viendra compléter en écriture.
 */
final class ApiController
{
    public function __construct(
        private readonly Content $content,
        private readonly ?Assistant $assistant = null,
    ) {
    }

    /**
     * Question posée à l'assistant : /api/assistant
     *
     * La clé d'API et le corpus restent côté serveur ; le navigateur ne parle
     * qu'à ce site. Le nombre de questions est borné par session, faute de
     * quoi l'adresse ferait un moyen commode d'épuiser le quota Gemini de
     * l'entreprise.
     */
    public function assistant(): string
    {
        if ($this->assistant === null || !$this->assistant->actif()) {
            return json_response(['erreur' => 'L’assistant n’est pas disponible.'], 503);
        }
        $charge = json_decode((string) file_get_contents('php://input'), true);
        $charge = is_array($charge) ? $charge : $_POST;

        // La requête est envoyée en JSON : $_POST est vide, le jeton arrive
        // donc dans le corps ou dans un en-tête.
        $jeton = $charge['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Csrf::verifierJeton($jeton)) {
            return json_response(['erreur' => 'Session expirée. Rechargez la page.'], 419);
        }

        $question = trim((string) ($charge['question'] ?? ''));
        if ($question === '') {
            return json_response(['erreur' => 'Écrivez votre question.'], 400);
        }
        if (mb_strlen($question) > Assistant::QUESTION_MAX) {
            return json_response(['erreur' => 'Question trop longue.'], 400);
        }

        if (!$this->quotaDeSession()) {
            return json_response([
                'erreur' => 'Vous avez atteint la limite de questions pour cette session. Appelez-nous, nous répondrons plus vite.',
            ], 429);
        }

        $historique = [];
        foreach ((array) ($charge['historique'] ?? []) as $tour) {
            if (!is_array($tour)) {
                continue;
            }
            $historique[] = [
                'role'  => (string) ($tour['role'] ?? 'user'),
                'texte' => (string) ($tour['texte'] ?? ''),
            ];
        }

        try {
            return json_response($this->assistant->repondre($question, $historique));
        } catch (RuntimeException $e) {
            return json_response(['erreur' => $e->getMessage()], 502);
        }
    }

    /**
     * Compteur glissant sur une heure, gardé en session.
     *
     * Une session est un cookie strictement nécessaire : ce garde-fou ne
     * dépend donc pas du consentement, et rien de plus n'est déposé.
     */
    private function quotaDeSession(): bool
    {
        $maximum = 30;
        $fenetre = 3600;

        Session::demarrer();
        $compteur = $_SESSION['assistant_questions'] ?? ['depuis' => time(), 'n' => 0];

        if (time() - (int) $compteur['depuis'] > $fenetre) {
            $compteur = ['depuis' => time(), 'n' => 0];
        }
        if ((int) $compteur['n'] >= $maximum) {
            return false;
        }

        $compteur['n'] = (int) $compteur['n'] + 1;
        $_SESSION['assistant_questions'] = $compteur;

        return true;
    }

    /**
     * Collection complète : /api/hebergements
     */
    public function collection(string $name, string $key = 'items'): string
    {
        try {
            $data = $this->content->load($name);
        } catch (RuntimeException) {
            return json_response(['erreur' => 'Ressource introuvable'], 404);
        }

        $items = $data[$key] ?? [];

        return json_response([
            'donnees' => $items,
            'total'   => is_countable($items) ? count($items) : 0,
        ]);
    }

    /**
     * Entrée unique par slug : /api/hebergements/la-carelie
     */
    public function item(string $name, string $slug, string $key = 'items'): string
    {
        try {
            $item = $this->content->find($name, $slug, $key);
        } catch (RuntimeException) {
            return json_response(['erreur' => 'Ressource introuvable'], 404);
        }

        return $item === null
            ? json_response(['erreur' => 'Élément introuvable'], 404)
            : json_response(['donnees' => $item]);
    }

    /**
     * Document brut : /api/tarifs
     */
    public function document(string $name): string
    {
        try {
            return json_response(['donnees' => $this->content->load($name)]);
        } catch (RuntimeException) {
            return json_response(['erreur' => 'Ressource introuvable'], 404);
        }
    }
}
