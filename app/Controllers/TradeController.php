<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\I18n;
use App\Services\StructuredData;
use App\Services\Trades;

/**
 * Rubrique « Les métiers » : la mosaïque, puis une fiche par métier.
 *
 * Chaque fiche est écrite pour répondre à ce qu'on tape dans un moteur de
 * recherche — que fait un régisseur son, comment le devenir, combien il gagne —
 * puis ouvre sur ce que le site a de concret : les annonces du moment, les
 * profils disponibles, les métiers voisins.
 */
final class TradeController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $groups = Trades::byFamily();
        $total = array_sum(array_map('count', $groups));

        return $this->page('pages/trades', [
            'groups'   => $groups,
            'families' => Trades::families(),
            'counts'   => Trades::jobCounts(),
            'total'    => $total,
        ], [
            'title'    => I18n::t('trades.title'),
            'desc'     => I18n::t('trades.meta', $total),
            'path'     => '/metiers',
            'seo_vars' => ['{total}' => (string) $total],
            'schema'   => StructuredData::tradeIndex($groups),
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $trade = Trades::findBySlug($slug);

        if ($trade === null || ($trade['status'] ?? '') !== 'publish') {
            // Fiche renommée : l'ancienne adresse suit, en permanente.
            $current = Trades::currentSlug($slug);
            if ($current !== '') {
                return Response::redirect(I18n::url('/metiers/' . $current), 301);
            }
            return $this->notFound('/metiers');
        }

        $family = Trades::family((string) $trade['family']);
        $jobs = Trades::matchingJobs($trade, 6);
        $name = (string) $trade['name'];
        $pay = Trades::payLabel((array) $trade['pay']);

        $ownTitle = trim((string) ($trade['seo']['title'] ?? ''));
        $ownDesc = trim((string) ($trade['seo']['description'] ?? ''));

        return $this->page('pages/trade', [
            'trade'    => $trade,
            'family'   => $family,
            'pay'      => $pay,
            'jobs'     => $jobs,
            'partners' => Trades::partnerJobs($trade, 4, $jobs),
            'profiles' => Trades::matchingProfiles($trade, 4),
            'related'  => Trades::related($trade, 4),
            'counts'   => Trades::jobCounts(),
        ], [
            // Le nom du métier ouvre le titre : c'est lui que l'on cherche.
            'title'     => $ownTitle !== '' ? $ownTitle : I18n::t('trade.seo_title', $name),
            'desc'      => $ownDesc !== '' ? $ownDesc : str_excerpt((string) $trade['intro'], 155),
            'own_title' => $ownTitle !== '',
            'own_desc'  => $ownDesc !== '',
            'path'      => '/metiers/' . $trade['slug'],
            'ogType'    => 'article',
            'seo_vars'  => [
                '{metier}'   => $name,
                '{metier_f}' => (string) $trade['name_f'],
                '{famille}'  => (string) $family['name'],
                '{salaire}'  => $pay,
            ],
            'schema'    => StructuredData::trade($trade, $family),
        ]);
    }
}
