<?php
declare(strict_types=1);

/**
 * Ce que l'assistant refuse, et à qui.
 *
 * **Pourquoi ce script existe.** Le quota de l'assistant était compté dans
 * `$_SESSION` : trente questions par heure, mais une session par cookie. Un
 * script qui n'en garde aucun repartait de zéro à chaque appel, et la facture
 * Gemini de la mairie n'avait donc aucune borne. Rien dans une page ne le
 * montrait : le site répondait normalement, la bulle fonctionnait, et le
 * défaut ne se serait vu que sur un relevé Google.
 *
 * C'est le cas de `file.php` transposé — **une branche qu'on ne peut
 * atteindre qu'en production doit avoir sa doublure.** Ici la production,
 * c'est une clé Google et des appels facturés ; la doublure tient lieu de
 * Gemini, aucune requête ne sort, et c'est le VRAI code de l'API qui est
 * mesuré : `ApiController::assistant()`, ses trois barrières et leurs
 * compteurs.
 *
 * Quatre propriétés :
 *
 *   1. le quota par adresse ferme au bon rang ;
 *   2. **changer de session ne le rouvre pas** — c'est le défaut d'origine ;
 *   3. le plafond du jour ferme même quand session et adresse sont neuves ;
 *   4. les familles ne se mélangent pas : épuiser le formulaire de contact
 *      ne doit pas fermer l'assistant.
 *
 * Le contenu vit dans un dossier temporaire à lui : rien n'est écrit dans
 * data/, ni lu de la configuration de la machine.
 *
 *     php outils/verifs/quota.php
 *
 * Sort en 1 au premier écart.
 */

$racine = dirname(__DIR__, 2);

// APP_DATA doit être posé AVANT le bootstrap : c'est lui qui fige les chemins.
$bac = sys_get_temp_dir() . '/verif-quota-' . bin2hex(random_bytes(6));
mkdir($bac . '/admin', 0755, true);
mkdir($bac . '/assistant', 0755, true);
putenv('APP_DATA=' . $bac);

require $racine . '/app/bootstrap.php';

use App\Controllers\ApiController;
use App\Core\Antispam;
use App\Core\Assistant;
use App\Core\Content;
use App\Core\Csrf;
use App\Core\Parametres;

/* Tout l'affichage passe par un tampon, du début à la fin.
   Sans lui, le premier echo « envoie les en-têtes » et http_response_code()
   ne peut plus rien poser : le contrôleur répondrait 429 sans que la mesure
   puisse le lire, et l'auditeur conclurait à un défaut qui n'existe pas.
   C'est un piège de mesure, pas un défaut du site — d'où ce tampon plutôt
   qu'un seuil abaissé. */
ob_start();
register_shutdown_function(static function (): void {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
});

$ecarts = [];
$mesures = 0;

function verifier(string $quoi, bool $vrai): void
{
    global $ecarts, $mesures;
    $mesures++;
    printf("  %-62s %s\n", $quoi, $vrai ? 'ok' : 'ECART');
    if (!$vrai) {
        $ecarts[] = $quoi;
    }
}

/** Un Gemini de comptoir : il répond toujours, et ne coûte rien. */
$geminiDeComptoir = static function (string $url, array $entetes, ?string $corps, int $delai): array {
    return [200, json_encode([
        'candidates' => [['content' => ['parts' => [['text' => 'Réponse de doublure.']]]]],
    ], JSON_UNESCAPED_UNICODE)];
};

// --- le site sous mesure -----------------------------------------------------

$parametres = new Parametres($bac . '/admin/parametres.json');
$parametres->enregistrer([
    'assistant' => [
        'actif'        => true,
        'cle'          => 'cle-de-doublure',
        'source_site'  => true,
        // Haut pour la première partie : c'est le quota par adresse qu'on y
        // mesure, et un plafond bas fermerait la journée avant lui.
        'plafond_jour' => 1000,
    ],
    'antispam' => ['secret' => str_repeat('a', 64)],
]);

$content   = new Content($bac, $racine . '/data-modele');
$assistant = new Assistant($parametres, $content, $bac, $bac . '/cache-assistant.json', $geminiDeComptoir);
$antispam  = new Antispam($parametres, $bac . '/antispam-quotas.json');
$api       = new ApiController($content, $assistant, null, null, $parametres, $antispam);

/**
 * Une question posée à l'assistant, comme le navigateur la pose.
 *
 * @return array{code: int, corps: array<string, mixed>}
 */
function poser(ApiController $api, string $question = 'Quels sont les horaires ?'): array
{
    // La requête arrive en JSON : le contrôleur lit php://input, qu'un script
    // ne peut pas remplir. $_POST est son repli documenté, et c'est par là
    // que passe la mesure.
    $_POST = ['question' => $question, '_csrf' => Csrf::jeton()];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::jeton();

    ob_start();
    $sortie = $api->assistant();
    ob_end_clean();

    return ['code' => http_response_code() ?: 200, 'corps' => json_decode($sortie, true) ?: []];
}

/** Repart d'une session neuve, en gardant la même adresse. */
function nouvelleSession(): void
{
    $_SESSION = [];
}

// La même adresse pour tout le monde : c'est la machine qu'on mesure.
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
App\Core\Session::demarrer();

echo "=== 1. le quota par adresse ferme, et la session ne le rouvre pas ===\n";

/* Quarante réponses passent (QUOTA_ADRESSE), la quarante-et-unième non. On
   repart d'une session neuve tous les dix appels : si le compteur était encore
   celui de la session, le refus n'arriverait jamais. */
$acceptees = 0;
$refusee = null;
for ($i = 1; $i <= 60; $i++) {
    if ($i % 10 === 0) {
        nouvelleSession();
    }
    $r = poser($api);
    if ($r['code'] === 200) {
        $acceptees++;
        continue;
    }
    $refusee = $r;
    break;
}

verifier('la 41e question est refusée malgré cinq sessions neuves', $acceptees === 40 && $refusee !== null);
verifier('le refus est un 429, pas une erreur de service', ($refusee['code'] ?? 0) === 429);
verifier(
    'le refus rappelle les numéros d’urgence AVANT tout autre renvoi',
    str_contains((string) ($refusee['corps']['erreur'] ?? ''), '15, le 18 ou le 112')
);
verifier(
    'le refus donne le téléphone de la mairie',
    str_contains((string) ($refusee['corps']['erreur'] ?? ''), '03 84 23 84 87')
);

echo "=== 2. le plafond du jour ferme, adresse et session neuves ===\n";

/* Le compteur du jour a suivi les quarante réponses servies. On abaisse le
   plafond en dessous : une adresse jamais vue et une session neuve doivent
   alors être refusées, alors que ni l'une ni l'autre n'a rien consommé. C'est
   la campagne menée depuis trente machines. */
verifier('le compteur du jour a suivi les réponses servies', $assistant->questionsDuJour() === 40);

$reglages = (array) $parametres->get('assistant', []);
$parametres->enregistrer(['assistant' => ['plafond_jour' => 10] + $reglages]);
$assistantPlafonne = new Assistant($parametres, $content, $bac, $bac . '/cache-assistant.json', $geminiDeComptoir);
$apiPlafonnee = new ApiController($content, $assistantPlafonne, null, null, $parametres, $antispam);
verifier('le plafond du jour est atteint', $assistantPlafonne->plafondAtteint());

$_SERVER['REMOTE_ADDR'] = '198.51.100.42';
nouvelleSession();
$r = poser($apiPlafonnee);
verifier('une adresse neuve est refusée quand la journée est pleine', $r['code'] === 429);
verifier(
    'le motif parle bien de la journée',
    str_contains((string) ($r['corps']['erreur'] ?? ''), 'aujourd’hui')
);

echo "=== 3. les familles de quota ne se mélangent pas ===\n";

$_SERVER['REMOTE_ADDR'] = '192.0.2.15';
for ($i = 0; $i < 5; $i++) {
    $antispam->enregistrerEnvoi(Antispam::FORMULAIRE);
}
verifier('cinq envois de formulaire remplissent le quota du formulaire',
    $antispam->quotaAtteint(Antispam::FORMULAIRE, 5));
verifier('… et laissent l’assistant ouvert pour cette adresse',
    !$antispam->quotaAtteint(Antispam::ASSISTANT, 40));
verifier('… et le rappel aussi',
    !$antispam->quotaAtteint(Antispam::RAPPEL, 3));

echo "=== 4. le plafond désarmé laisse passer ===\n";

$parametres->enregistrer(['assistant' => ['plafond_jour' => 0] + (array) $parametres->get('assistant', [])]);
$assistantSansPlafond = new Assistant($parametres, $content, $bac, $bac . '/cache-assistant.json', $geminiDeComptoir);
verifier('un plafond à 0 ne ferme jamais la journée', !$assistantSansPlafond->plafondAtteint());

// --- ménage ------------------------------------------------------------------

$effacer = static function (string $dossier) use (&$effacer): void {
    foreach ((array) scandir($dossier) as $entree) {
        if ($entree === '.' || $entree === '..') {
            continue;
        }
        $chemin = $dossier . '/' . $entree;
        is_dir($chemin) ? $effacer($chemin) : @unlink($chemin);
    }
    @rmdir($dossier);
};
$effacer($bac);

echo "---\n";
if ($ecarts === []) {
    printf("%d mesure(s), aucune requête sortie, 0 écart.\n", $mesures);
    exit(0);
}
printf("%d mesure(s) — %d écart(s).\n", $mesures, count($ecarts));
exit(1);
