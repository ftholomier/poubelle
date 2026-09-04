<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Assistant;
use App\Core\Content;
use App\Core\Conversations;
use App\Core\Mailer;
use App\Core\Parametres;
use App\Core\Csrf;
use App\Core\Session;
use RuntimeException;
use Throwable;

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
        private readonly ?Conversations $conversations = null,
        private readonly ?Mailer $mailer = null,
        private readonly ?Parametres $parametres = null,
    ) {
    }

    /**
     * Question posée à l'assistant : /api/assistant
     *
     * La clé d'API et le corpus restent côté serveur ; le navigateur ne parle
     * qu'à ce site. Le nombre de questions est borné par session, faute de
     * quoi l'adresse ferait un moyen commode d'épuiser le quota Gemini de
     * la mairie.
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
            $resultat = $this->assistant->repondre($question, $historique);
        } catch (RuntimeException $e) {
            return json_response(['erreur' => $e->getMessage()], 502);
        }

        // Le journal est tenu après coup : un échec d'écriture ne doit pas
        // priver le visiteur d'une réponse déjà obtenue.
        try {
            $this->conversations?->ajouter(
                (string) ($charge['conversation'] ?? ''),
                $question,
                $resultat['reponse'],
                (string) ($charge['page'] ?? '')
            );
        } catch (Throwable $e) {
            error_log('Assistant : conversation non enregistrée — ' . $e->getMessage());
        }

        return json_response($resultat);
    }

    /**
     * Demande de rappel laissée dans l'assistant : /api/assistant/contact
     *
     * Elle est enregistrée dans la conversation ET envoyée par e-mail. Un
     * numéro laissé dans une bulle de discussion que personne ne relit ne
     * vaut rien : c'est l'e-mail qui déclenche le rappel.
     */
    public function assistantContact(): string
    {
        if ($this->assistant === null || !$this->assistant->actif()) {
            return json_response(['erreur' => 'L’assistant n’est pas disponible.'], 503);
        }

        $charge = json_decode((string) file_get_contents('php://input'), true);
        $charge = is_array($charge) ? $charge : $_POST;

        $jeton = $charge['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Csrf::verifierJeton($jeton)) {
            return json_response(['erreur' => 'Session expirée. Rechargez la page.'], 419);
        }

        $contact = [
            'nom'       => mb_substr(trim((string) ($charge['nom'] ?? '')), 0, 80),
            'telephone' => mb_substr(trim((string) ($charge['telephone'] ?? '')), 0, 30),
            'email'     => mb_substr(trim((string) ($charge['email'] ?? '')), 0, 120),
            'message'   => mb_substr(trim((string) ($charge['message'] ?? '')), 0, 600),
        ];

        if ($contact['telephone'] === '' && $contact['email'] === '') {
            return json_response(['erreur' => 'Laissez au moins un téléphone ou un e-mail.'], 400);
        }
        if ($contact['email'] !== '' && !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
            return json_response(['erreur' => 'Cette adresse e-mail semble incorrecte.'], 400);
        }
        if (!$this->quotaDeSession()) {
            return json_response(['erreur' => 'Trop de demandes. Appelez-nous directement.'], 429);
        }

        try {
            $this->conversations?->contact((string) ($charge['conversation'] ?? ''), array_filter($contact));
        } catch (Throwable $e) {
            error_log('Assistant : demande de rappel non enregistrée — ' . $e->getMessage());
        }

        $this->prevenir($contact, (array) ($charge['historique'] ?? []));

        return json_response(['message' => 'Merci, nous vous rappelons rapidement.']);
    }

    /** Envoie la demande de rappel au destinataire réglé dans Paramètres. */
    private function prevenir(array $contact, array $historique): void
    {
        if ($this->mailer === null || $this->parametres === null) {
            return;
        }

        $site = $this->content->load('site');
        $destinataire = (string) $this->parametres->get('contact.destinataire')
            ?: (string) ($site['contact']['email'] ?? '');

        if ($destinataire === '') {
            error_log('Assistant : ni destinataire dans Paramètres, ni e-mail dans Coordonnées.');
            return;
        }

        $lignes = ['Demande de rappel laissée dans l’assistant du site.', ''];
        foreach (['nom' => 'Nom', 'telephone' => 'Téléphone', 'email' => 'E-mail', 'message' => 'Message'] as $cle => $libelle) {
            if (($contact[$cle] ?? '') !== '') {
                $lignes[] = $libelle . ' : ' . $contact[$cle];
            }
        }

        // Les derniers échanges accompagnent la demande : ils disent ce que le
        // visiteur cherchait, et donc par quoi commencer le rappel.
        $derniers = array_slice($historique, -6);
        if ($derniers !== []) {
            $lignes[] = '';
            $lignes[] = '--- Fin de la conversation ---';
            foreach ($derniers as $tour) {
                if (!is_array($tour)) {
                    continue;
                }
                $qui = ($tour['role'] ?? '') === 'model' ? 'Assistant' : 'Visiteur';
                $lignes[] = $qui . ' : ' . mb_substr((string) ($tour['texte'] ?? ''), 0, 500);
            }
        }

        try {
            $this->mailer->envoyer(
                $destinataire,
                'Demande de rappel — assistant du site',
                implode("\n", $lignes),
                $contact['email']
            );
        } catch (Throwable $e) {
            error_log('Assistant : e-mail de rappel non envoyé — ' . $e->getMessage());
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
            /* `publies()` et non `load()` : c'est le même filtre que les pages
               du site, et il manquait ici. Une actualité dépubliée dans le
               back-office disparaissait de la page mais restait lisible sur
               /api/actualites — une fiche de démarche en cours de rédaction
               aussi. L'API est publique et sans authentification : ce qu'elle
               rend doit être exactement ce que le site montre. */
            $items = $this->content->publies($name, $key);
        } catch (RuntimeException) {
            return json_response(['erreur' => 'Ressource introuvable'], 404);
        }

        return json_response([
            'donnees' => $items,
            'total'   => count($items),
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

        // Un élément dépublié est introuvable, et non « trouvé mais caché » :
        // répondre 404 ne dit pas à un curieux qu'il existe.
        return $item === null || !Content::estPublie($item)
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
