<?php
declare(strict_types=1);

namespace App\Admin;

use App\Data\Derived;
use App\Data\Index;
use App\Data\Media;
use App\Services\Translator;

/**
 * Contrôle qualité : vérifications d'une fiche (panneau de l'éditeur) et liste
 * globale des alertes (écran « Qualité »).
 */
final class Quality
{
    /** @return list<array{0:string,1:string}> [niveau ok|warn|ko, message] */
    public static function forDoc(array $doc): array
    {
        $out = [];
        $id = (int) $doc['id'];
        foreach (Derived::get()['quality'] ?? [] as $a) {
            if ((int) $a['id'] === $id) {
                $out[] = [$a['sev'] === 'haute' ? 'ko' : 'warn', $a['msg']];
            }
        }
        if ($doc['type'] === 'match') {
            $m = $doc['match'];
            $s = $m['score'] ?? null;
            $us = $s ? ($m['sochaux_home'] ? $s['home'] : $s['away']) : null;
            $goals = 0;
            foreach ($m['lineup']['rows'] ?? [] as $r) {
                $goals += count($r['goals'] ?? []);
            }
            if ($s && $m['lineup']['rows'] && $goals === $us) {
                $out[] = ['ok', 'Score cohérent avec les buteurs de la composition'];
            }
            if (empty($m['date'])) {
                $out[] = ['ko', 'Date du match non renseignée'];
            }
            if (empty($m['referee'])) {
                $out[] = ['warn', 'Arbitre non renseigné'];
            }
            if (empty($m['stadium'])) {
                $out[] = ['warn', 'Stade non renseigné'];
            }
            if (empty($m['lineup']['rows'])) {
                $out[] = ['warn', 'Composition non saisie'];
            }
        }
        if ($doc['type'] === 'personne') {
            $p = $doc['personne'];
            if (empty($p['birth']['date'])) {
                $out[] = ['warn', 'Date de naissance non renseignée'];
            }
            if (empty($p['birth']['place']['city'])) {
                $out[] = ['warn', 'Lieu de naissance non renseigné (absent de la carto des origines)'];
            }
        }
        $noCredit = 0;
        foreach ($doc['gallery'] ?? [] as $g) {
            if (trim((string) ($g['credit'] ?? '')) === '' && trim((string) (Media::get($g['image'])['credit'] ?? '')) === '') {
                $noCredit++;
            }
        }
        if ($noCredit) {
            $out[] = ['warn', $noCredit . ' photo' . ($noCredit > 1 ? 's' : '') . ' sans crédit'];
        }
        if (empty($doc['featured_image'])) {
            $out[] = ['warn', 'Pas d’image à la une (mosaïques et partage)'];
        }
        if (trim((string) ($doc['seo']['description'] ?? '')) === '' && in_array($doc['type'], ['article', 'page'], true)) {
            $out[] = ['warn', 'Description pour Google vide (un extrait sera utilisé)'];
        }
        $en = Translator::status($doc);
        if ($en === 'stale') {
            $out[] = ['warn', 'Version anglaise à revoir (le français a changé)'];
        }
        if (!$out || !array_filter($out, fn ($c) => $c[0] !== 'ok')) {
            $out[] = ['ok', 'Aucune alerte'];
        }
        return $out;
    }

    /**
     * Toutes les alertes, par catégorie.
     * @return array<string,list<array{sev:string,msg:string,id:?int,title:string,url:string}>>
     */
    public static function all(): array
    {
        $d = Derived::get();
        $out = ['stats' => [], 'liens' => [], 'credits' => [], 'carto' => [], 'traductions' => []];
        foreach ($d['quality'] ?? [] as $a) {
            $s = Index::get((int) $a['id']);
            $out['stats'][] = ['sev' => $a['sev'], 'msg' => $a['msg'], 'id' => (int) $a['id'], 'title' => $s['title'] ?? ('Fiche ' . $a['id']), 'url' => '/admin/fiche/' . (int) $a['id']];
        }
        $unlinked = $d['unlinked'] ?? [];
        uasort($unlinked, fn ($a, $b) => count($b['matches']) <=> count($a['matches']));
        foreach (array_slice($unlinked, 0, 300, true) as $u) {
            $n = count($u['matches']);
            $out['liens'][] = ['sev' => $n >= 20 ? 'moyenne' : 'basse', 'msg' => 'Joueur cité dans ' . $n . ' composition' . ($n > 1 ? 's' : '') . ' sans fiche', 'id' => null, 'title' => $u['name'], 'url' => '/admin/fiche/nouvelle/personne?nom=' . rawurlencode($u['name'])];
        }
        foreach (Media::all() as $rel => $m) {
            if (trim((string) ($m['credit'] ?? '')) === '' && preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) $rel)) {
                $out['credits'][] = ['sev' => 'moyenne', 'msg' => 'Photo sans crédit', 'id' => null, 'title' => (string) $rel, 'url' => '/admin/medias?f=' . rawurlencode((string) $rel)];
                if (count($out['credits']) >= 500) {
                    break;
                }
            }
        }
        foreach (Index::all() as $s) {
            if ($s['type'] === 'personne' && Index::visible($s) && empty($s['p']['birth_place']) && in_array('joueur', $s['p']['roles'] ?? [], true)) {
                $out['carto'][] = ['sev' => 'basse', 'msg' => 'Lieu de naissance inconnu', 'id' => (int) $s['id'], 'title' => $s['title'], 'url' => '/admin/fiche/' . (int) $s['id'] . '#identite'];
            }
        }
        return $out;
    }
}
