<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ContentTranslator;
use App\Services\I18n;
use App\Services\StructuredData;
use App\Services\Trades;
use App\Storage\Index;

/**
 * Rubrique « Les métiers » : la mosaïque, puis une fiche par métier.
 *
 * Chaque fiche est écrite pour répondre à ce qu'on tape dans un moteur de
 * recherche — que fait un régisseur son, comment le devenir, combien il gagne —
 * puis ouvre sur ce que le site a de concret : les annonces du moment, les
 * profils disponibles, les métiers voisins.
 *
 * Dans une autre langue, fiches et familles viennent de leur traduction en
 * cache. Une page encore servie en français pour l'essentiel le dit, et sort
 * de l'index des moteurs : mieux vaut attendre sa traduction que proposer un
 * doublon de la version française.
 */
final class TradeController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $groups = array_map([Trades::class, 'localRows'], Trades::byFamily());
        if (!I18n::isPivot()) {
            // L'ordre alphabétique est celui de la langue lue.
            foreach ($groups as &$rows) {
                usort($rows, static fn(array $a, array $b)
                    => strcmp(Index::haystack([$a['name']]), Index::haystack([$b['name']])));
            }
            unset($rows);
        }
        $total = array_sum(array_map('count', $groups));

        $ids = [];
        foreach ($groups as $rows) {
            foreach ($rows as $row) {
                $ids[] = (string) $row['id'];
            }
        }
        $translated = !I18n::isPivot() && I18n::hasTranslations()
            && ContentTranslator::coverage('trade', I18n::lang(), $ids) >= 0.9;

        return $this->page('pages/trades', [
            'groups'   => $groups,
            'families' => Trades::localFamilies(),
            'counts'   => Trades::jobCounts(),
            'total'    => $total,
        ], [
            'title'        => I18n::t('trades.title'),
            'desc'         => I18n::t('trades.meta', $total),
            'path'         => '/metiers',
            'translated'   => $translated,
            'untranslated' => !I18n::isPivot() && !$translated,
            'seo_vars'     => ['{total}' => (string) $total],
            'schema'       => StructuredData::tradeIndex($groups),
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

        // Le rapprochement se fait sur la fiche française : les annonces et les
        // profils sont écrits en français, et ses mots-clés ne se traduisent pas.
        $jobs = Trades::matchingJobs($trade, 6);
        $partners = Trades::partnerJobs($trade, 4, $jobs);
        $profiles = Trades::matchingProfiles($trade, 4);
        $related = Trades::related($trade, 4);
        $siblings = array_values(array_filter(
            Trades::byFamily()[(string) $trade['family']] ?? [],
            static fn(array $row) => $row['slug'] !== $trade['slug'],
        ));

        // Une fiche consultée dans une autre langue est traduite à la volée,
        // puis servie depuis le cache aux suivants. Jamais pour un robot.
        $trade = ContentTranslator::translateOnDemand(
            $trade, 'trade', I18n::lang(), $request->ip(), $request->isBot(),
        );
        $lang = I18n::lang();
        $family = Trades::localFamily((string) $trade['family']);
        $name = (string) $trade['name'];
        $pay = Trades::payLabel((array) $trade['pay']);

        $ownTitle = trim((string) ($trade['seo']['title'] ?? ''));
        $ownDesc = trim((string) ($trade['seo']['description'] ?? ''));

        return $this->page('pages/trade', [
            'trade'    => $trade,
            'family'   => $family,
            'pay'      => $pay,
            'jobs'     => ContentTranslator::applyToRows($jobs, 'job', $lang),
            'partners' => $partners,
            'profiles' => ContentTranslator::applyToRows($profiles, 'cv', $lang),
            'related'  => Trades::localRows($related),
            'siblings' => Trades::localRows($siblings),
            'counts'   => Trades::jobCounts(),
        ], [
            // Le nom du métier ouvre le titre : c'est lui que l'on cherche.
            'title'     => $ownTitle !== '' ? $ownTitle : I18n::t('trade.seo_title', $name),
            'desc'      => $ownDesc !== '' ? $ownDesc : str_excerpt((string) $trade['intro'], 155),
            'own_title' => $ownTitle !== '',
            'own_desc'  => $ownDesc !== '',
            'path'      => '/metiers/' . $trade['slug'],
            'translated'   => !empty($trade['translated']),
            'untranslated' => !I18n::isPivot() && empty($trade['translated']),
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
