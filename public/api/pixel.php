<?php
declare(strict_types=1);

/**
 * Pixel de présence : un navigateur qui affiche réellement le formulaire
 * demande cette image. Un script qui poste directement, jamais. L'absence de
 * cet appel compte dans la note de suspicion, sans jamais bloquer à elle seule.
 *
 * Aucune donnée personnelle n'est enregistrée : uniquement le nonce du
 * formulaire, effacé par le ménage quotidien.
 */

require __DIR__ . '/../../app/bootstrap.php';

use App\Spam;

$nonce = (string) ($_GET['t'] ?? '');
if ($nonce !== '') {
    Spam::markPixel($nonce);
}

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

// GIF transparent de 1×1 pixel. La longueur est calculée, jamais écrite en dur :
// une valeur fausse coupe la réponse et le navigateur signale une erreur.
$gif = (string) base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
header('Content-Length: ' . \strlen($gif));
echo $gif;
