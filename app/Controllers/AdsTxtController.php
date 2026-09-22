<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\Ads;

/**
 * /ads.txt — la liste des régies autorisées à vendre l'inventaire du site.
 *
 * Google exige ce fichier à la racine du domaine : sans lui, AdSense signale
 * « revenus menacés » et peut restreindre la diffusion. Il est produit à
 * partir de l'identifiant éditeur saisi au back-office, pour qu'il ne puisse
 * pas se désynchroniser d'une modification du compte.
 *
 * f08c47fec0942fa0 est l'identifiant de certification de Google, identique
 * pour tous les éditeurs AdSense.
 */
final class AdsTxtController extends Controller
{
    private const GOOGLE_TAG_ID = 'f08c47fec0942fa0';

    public function txt(Request $request, array $params): Response
    {
        $client = Ads::client();

        // Sans identifiant éditeur, un fichier vide vaut mieux qu'une ligne
        // fausse : Google traite l'absence de ligne comme « aucune régie ».
        $body = $client === ''
            ? "# Aucun identifiant éditeur configuré.\n"
            : sprintf("google.com, %s, DIRECT, %s\n",
                // ads.txt attend « pub-… », le compte s'écrit « ca-pub-… ».
                str_starts_with($client, 'ca-') ? substr($client, 3) : $client,
                self::GOOGLE_TAG_ID);

        return Response::text($body)
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
