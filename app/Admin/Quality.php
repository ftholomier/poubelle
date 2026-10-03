<?php
declare(strict_types=1);

namespace App\Admin;

use App\Data\Derived;
use App\Data\Index;
use App\Data\Media;
use App\Services\Controle;
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
        $codes = [];
        $id = (int) $doc['id'];
        foreach (Derived::get()['quality'] ?? [] as $a) {
            if ((int) $a['id'] === $id) {
                $out[] = [$a['sev'] === 'haute' ? 'ko' : 'warn', $a['msg']];
                $codes[$a['code']] = true;
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
            if (empty($m['date']) && !isset($codes['match-date'])) {
                $out[] = ['ko', 'Date du match non renseignée'];
            }
            if (empty($m['referee']) && !isset($codes['arbitre'])) {
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
        $proof = \App\Services\Proofreader::forFiche($doc);
        if ($proof && $proof['n'] > 0) {
            $out[] = ['warn', $proof['n'] . ' correction' . ($proof['n'] > 1 ? 's' : '') . ' d’orthographe proposée' . ($proof['n'] > 1 ? 's' : '') . ' (bouton « Vérifier l’orthographe »)'];
        }
        $en = Translator::status($doc);
        if ($en === 'stale' && !isset($codes['traduction'])) {
            $out[] = ['warn', 'Version anglaise à revoir (le français a changé)'];
        }
        if (!$out || !array_filter($out, fn ($c) => $c[0] !== 'ok')) {
            $out[] = ['ok', 'Aucune alerte'];
        }
        return $out;
    }

    /** Onglets de l'écran Qualité : libellé, description. */
    public const TABS = [
        'stats' => ['Statistiques et dates', 'Scores, buteurs, compositions, dates'],
        'completer' => ['À compléter', '« xx » de l’ancien site, fiches à venir, vidéos'],
        'liens' => ['Liens joueurs', 'Sans fiche, rapprochements, doublons'],
        'site' => ['Adresses et médias', 'Adresses, rubriques, images, redirections'],
        'orthographe' => ['Orthographe & syntaxe', 'Corrections proposées par le correcteur'],
        'credits' => ['Photos sans crédit', 'Médiathèque'],
        'carto' => ['Lieux de naissance inconnus', 'Carto des origines'],
        'traductions' => ['Traductions à revoir', 'Version anglaise'],
    ];

    /** Onglet des alertes calculées (Derived) selon leur nature ; les autres vont dans « stats ». */
    private const CODE_TABS = [
        'rapproche' => 'liens', 'homonyme' => 'liens',
        'inconnu' => 'completer', 'avenir' => 'completer', 'arbitre' => 'completer', 'video' => 'completer', 'role' => 'completer',
        'titre' => 'site', 'adresse' => 'site', 'rubrique' => 'site', 'image' => 'site', 'fichier' => 'site', 'photos' => 'site',
        'traduction' => 'traductions',
    ];

    /** Onglet de la fiche où se corrige l'alerte (ouvert par le bouton « Corriger »). */
    private const CODE_ANCHORS = [
        'adresse' => 'seo', 'rubrique' => 'seo', 'image' => 'medias', 'video' => 'medias', 'traduction' => 'en', 'avenir' => 'recit',
        'match-date' => 'infos', 'date' => 'infos', 'saison' => 'infos', 'score' => 'infos', 'resultat' => 'infos', 'tab' => 'infos', 'affluence' => 'infos', 'arbitre' => 'infos',
        'compo' => 'compo', 'doublon' => 'compo', 'buts' => 'compo', 'tableau' => 'compo',
        'dates' => 'identite', 'role' => 'identite', 'homonyme' => 'identite', 'stats' => 'stats', 'stats-copie' => 'stats',
    ];

    /**
     * Toutes les alertes, par onglet (listes complètes, les plus graves d'abord). Chaque alerte
     * a une clé stable et indique si elle est nouvelle depuis le dernier contrôle complet.
     * @return array<string,list<array{sev:string,msg:string,id:?int,title:string,url:?string,code:string,tab:string,key:string,new:bool}>>
     */
    public static function all(): array
    {
        $d = Derived::get();
        $out = array_fill_keys(array_keys(self::TABS), []);
        $add = function (string $tab, array $i) use (&$out) {
            $i += ['id' => null, 'code' => '', 'url' => null];
            $i['tab'] = $tab;
            $i['key'] = Controle::key($tab, $i);
            $i['new'] = Controle::isNew($tab, $i['key'], $i);
            $out[$tab][] = $i;
        };
        foreach ($d['quality'] ?? [] as $a) {
            if ($a['code'] === 'nonrelie') {
                continue; // listés plus bas (onglet des liens), avec le bouton de création de fiche
            }
            $id = isset($a['id']) ? (int) $a['id'] : null;
            $s = $id ? Index::get($id) : null;
            // Noms reliés par rapprochement et fiches en double : onglet des liens ; « xx » de l'ancien
            // site, fiches à venir, arbitre, vidéos : « À compléter » ; adresses, rubriques, images : « Adresses et médias ».
            $anchor = self::CODE_ANCHORS[$a['code']] ?? '';
            if ($a['code'] === 'dates' && !preg_match('/naissance|décès/u', (string) $a['msg'])) {
                $anchor = 'carriere'; // arrivée et départ : onglet Carrière
            }
            $add(self::CODE_TABS[$a['code']] ?? 'stats', ['sev' => $a['sev'], 'msg' => $a['msg'], 'id' => $id, 'code' => $a['code'], 'ref' => (string) ($a['ref'] ?? ''),
                'title' => $s['title'] ?? ($a['title'] ?? ('Fiche ' . $id)),
                'url' => $id ? '/admin/fiche/' . $id . ($anchor !== '' ? '#' . $anchor : '') : null]);
        }
        $unlinked = $d['unlinked'] ?? [];
        uasort($unlinked, fn ($a, $b) => count($b['matches']) <=> count($a['matches']));
        foreach ($unlinked as $u) {
            $n = count($u['matches']);
            $add('liens', ['sev' => $n >= 20 ? 'moyenne' : 'basse', 'code' => 'nonrelie', 'msg' => 'Joueur cité dans ' . $n . ' composition' . ($n > 1 ? 's' : '') . ' sans fiche', 'title' => $u['name'], 'url' => '/admin/fiche/nouvelle/personne?nom=' . rawurlencode($u['name'])]);
        }
        // Correcteur d'orthographe (tâche de fond) : fiches avec des corrections proposées.
        foreach (\App\Services\Proofreader::summary()['rows'] as $r) {
            $add('orthographe', [
                'sev' => $r['hi'] >= 3 ? 'haute' : ($r['hi'] > 0 ? 'moyenne' : 'basse'), 'code' => 'orthographe',
                'msg' => ($r['n'] > 1 ? $r['n'] . ' corrections proposées' : '1 correction proposée') . ($r['hi'] ? ' dont ' . $r['hi'] . ' faute' . ($r['hi'] > 1 ? 's' : '') . ' de langue' : ' (ponctuation, typographie)') . ($r['ex'] ? ' · ' . $r['ex'] : ''),
                'id' => $r['id'], 'title' => $r['title'], 'url' => '/admin/fiche/' . $r['id'] . '#correcteur',
            ]);
        }
        foreach (Media::all() as $rel => $m) {
            if (trim((string) ($m['credit'] ?? '')) === '' && preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) $rel)) {
                $add('credits', ['sev' => 'moyenne', 'code' => 'credit', 'msg' => 'Photo sans crédit', 'title' => (string) $rel, 'url' => '/admin/medias?f=' . rawurlencode((string) $rel)]);
            }
        }
        foreach (Index::all() as $s) {
            if ($s['type'] === 'personne' && Index::visible($s) && empty($s['p']['birth_place']) && in_array('joueur', $s['p']['roles'] ?? [], true)) {
                $add('carto', ['sev' => 'basse', 'code' => 'carto', 'msg' => 'Lieu de naissance inconnu', 'id' => (int) $s['id'], 'title' => $s['title'], 'url' => '/admin/fiche/' . (int) $s['id'] . '#identite']);
            }
        }
        // Redirections, référentiels, rubriques, traductions de l'interface.
        foreach (Controle::siteChecks() as $c) {
            $add($c['tab'], $c);
        }
        // Les plus graves d'abord (ordre d'origine gardé à gravité égale).
        $rank = ['haute' => 0, 'moyenne' => 1, 'basse' => 2];
        foreach ($out as $k => $list) {
            $i = 0;
            $keyed = array_map(function ($x) use (&$i, $rank) {
                return [$rank[$x['sev']] ?? 3, $i++, $x];
            }, $list);
            usort($keyed, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $out[$k] = array_column($keyed, 2);
        }
        return $out;
    }
}
