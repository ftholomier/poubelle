<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Settings;
use App\Data\Fiches;
use App\Data\Index;

/**
 * Traduction anglaise des contenus avec Gemini. Le français reste la référence :
 * la traduction est stockée dans la fiche (i18n.en), avec l'empreinte du texte
 * source. Une traduction corrigée à la main (« _manual ») n'est jamais écrasée ;
 * si le français change ensuite, elle est signalée « à revoir » dans le back-office.
 */
final class Translator
{
    private const FAILS = STORAGE_PATH . '/i18n-fails.json';
    private const BATCH = 5000;

    public static function enabled(): bool
    {
        return Gemini::ready() && in_array('en', I18n::enabled(), true);
    }

    /** Textes traduisibles d'une fiche : chemin => texte (HTML ou brut). */
    public static function source(array $doc): array
    {
        $src = [];
        $add = function (string $path, mixed $v) use (&$src): void {
            if (is_string($v) && trim(html_entity_decode(strip_tags($v), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '' && preg_match('/\p{L}{2}/u', strip_tags($v))) {
                $src[$path] = $v;
            }
        };
        $add('title', $doc['title'] ?? '');
        $add('intro', $doc['intro'] ?? '');
        foreach ($doc['sections'] ?? [] as $i => $s) {
            $add("sections.$i.title", $s['title'] ?? '');
            $add("sections.$i.html", $s['html'] ?? '');
        }
        $add('key_figure.text', $doc['key_figure']['text'] ?? '');
        $add('seo.title', $doc['seo']['title'] ?? '');
        $add('seo.description', $doc['seo']['description'] ?? '');
        foreach (['highlights', 'reactions'] as $k) {
            foreach ($doc['match'][$k] ?? [] as $i => $h) {
                $add("match.$k.$i.text", is_array($h) ? ($h['text'] ?? '') : $h);
            }
        }
        foreach ($doc['match']['breves'] ?? [] as $i => $b) {
            $add("match.breves.$i", is_array($b) ? ($b['text'] ?? '') : $b);
        }
        if (!empty($doc['personne'])) {
            $p = $doc['personne'];
            $add('personne.subtitle', $p['subtitle'] ?? '');
            $add('personne.nickname_text', $p['nickname_text'] ?? '');
            foreach ($p['fiche'] ?? [] as $i => $r) {
                $add("personne.fiche.$i.label", $r['label'] ?? '');
                $add("personne.fiche.$i.value", $r['value'] ?? '');
            }
            foreach (['honours', 'then'] as $k) {
                foreach ($p[$k] ?? [] as $i => $v) {
                    $add("personne.$k.$i", $v);
                }
            }
        }
        foreach (['heading', 'subtitle'] as $k) {
            $add("article.$k", $doc['article'][$k] ?? '');
        }
        foreach ($doc['gallery'] ?? [] as $i => $g) {
            $add("gallery.$i", $g['caption'] ?? '');
        }
        foreach ($doc['objet'] ?? [] as $k => $v) {
            if (in_array($k, ['description', 'provenance', 'material'], true)) {
                $add("objet.$k", $v);
            }
        }
        return $src;
    }

    public static function hash(array $doc): string
    {
        return substr(hash('sha256', json_encode(self::source($doc), JSON_UNESCAPED_UNICODE)), 0, 20);
    }

    /** État de la traduction : none, auto, manual, stale (le français a changé depuis). */
    public static function status(array $doc): string
    {
        $en = $doc['i18n']['en'] ?? [];
        if (empty($en['title'])) {
            return 'none';
        }
        if (($en['_src'] ?? '') !== self::hash($doc)) {
            return 'stale';
        }
        return !empty($en['_manual']) ? 'manual' : 'auto';
    }

    /** Traduit une fiche et l'enregistre. Renvoie « ok », « à jour », « manuelle » ou un message d'erreur. */
    public static function translateFiche(int $id, bool $force = false, ?array $user = null): string
    {
        $doc = Fiches::get($id);
        if (!$doc) {
            return 'fiche introuvable';
        }
        $status = self::status($doc);
        if (!$force && $status === 'auto') {
            return 'à jour';
        }
        if (!$force && $status === 'manual') {
            return 'manuelle';
        }
        if (!$force && $status === 'stale' && !empty($doc['i18n']['en']['_manual'])) {
            return 'manuelle';
        }
        $src = self::source($doc);
        if (!$src) {
            return 'rien à traduire';
        }
        // Traduction automatique : une fiche ouverte par un historien attend le passage suivant.
        if ($user === null && isset(EditLock::fiches()[$id])) {
            return 'en cours de modification';
        }
        AiCosts::$ref = "fiche:$id";
        try {
            $out = self::translateMap($src);
        } catch (\Throwable $e) {
            self::fail($id, $e->getMessage());
            return 'erreur : ' . $e->getMessage();
        } finally {
            AiCosts::$ref = '';
        }
        $en = self::build($doc, $out);
        $en['_src'] = self::hash($doc);
        $en['_at'] = date('c');
        $en['_by'] = $user['name'] ?? 'Gemini';
        $en['_model'] = Gemini::model();
        $en['_manual'] = false;
        // La fiche a pu être enregistrée pendant l'appel à Gemini : on repart de la version
        // actuelle (rien n'est écrasé) et on renonce si son texte français a changé.
        $fresh = Fiches::fresh($id);
        if (!$fresh || self::hash($fresh) !== $en['_src']) {
            return 'fiche modifiée pendant la traduction (elle sera retraduite)';
        }
        $fresh['i18n']['en'] = $en;
        Fiches::save($fresh, $user ?? ['name' => 'Traduction automatique'], 'Traduction anglaise (Gemini)');
        return 'ok';
    }

    /** Assemble le bloc i18n.en (structures complètes, textes traduits). */
    public static function build(array $doc, array $tr): array
    {
        $en = [];
        $get = fn (string $path, mixed $fallback = '') => $tr[$path] ?? $fallback;
        $en['title'] = $get('title', $doc['title'] ?? '');
        if (isset($tr['intro'])) {
            $en['intro'] = $tr['intro'];
        }
        foreach ($doc['sections'] ?? [] as $i => $s) {
            $en['sections'][$i] = ['title' => $get("sections.$i.title", $s['title'] ?? ''), 'html' => $get("sections.$i.html", $s['html'] ?? '')] + $s;
        }
        if (isset($tr['key_figure.text'])) {
            $en['key_figure'] = ['text' => $tr['key_figure.text']] + ($doc['key_figure'] ?? []);
        }
        if (isset($tr['seo.title']) || isset($tr['seo.description'])) {
            $en['seo'] = ['title' => $get('seo.title', $doc['seo']['title'] ?? ''), 'description' => $get('seo.description', $doc['seo']['description'] ?? '')];
        }
        foreach (['highlights', 'reactions'] as $k) {
            foreach ($doc['match'][$k] ?? [] as $i => $h) {
                if (isset($tr["match.$k.$i.text"])) {
                    $en['match'][$k][$i] = is_array($h) ? ['text' => $tr["match.$k.$i.text"]] + $h : $tr["match.$k.$i.text"];
                } else {
                    $en['match'][$k][$i] = $h;
                }
            }
        }
        foreach ($doc['match']['breves'] ?? [] as $i => $b) {
            $en['match']['breves'][$i] = $tr["match.breves.$i"] ?? $b;
        }
        if (!empty($doc['personne'])) {
            $p = $doc['personne'];
            foreach (['subtitle', 'nickname_text'] as $k) {
                if (isset($tr["personne.$k"])) {
                    $en['personne'][$k] = $tr["personne.$k"];
                }
            }
            foreach ($p['fiche'] ?? [] as $i => $r) {
                $en['personne']['fiche'][$i] = ['label' => $get("personne.fiche.$i.label", $r['label'] ?? ''), 'value' => $get("personne.fiche.$i.value", $r['value'] ?? '')] + $r;
            }
            foreach (['honours', 'then'] as $k) {
                foreach ($p[$k] ?? [] as $i => $v) {
                    $en['personne'][$k][$i] = $tr["personne.$k.$i"] ?? $v;
                }
            }
        }
        foreach (['heading', 'subtitle'] as $k) {
            if (isset($tr["article.$k"])) {
                $en['article'][$k] = $tr["article.$k"];
            }
        }
        foreach ($doc['gallery'] ?? [] as $i => $g) {
            $en['gallery'][$i] = $tr["gallery.$i"] ?? '';
        }
        foreach (['description', 'provenance', 'material'] as $k) {
            if (isset($tr["objet.$k"])) {
                $en['objet'][$k] = $tr["objet.$k"];
            }
        }
        return $en;
    }

    /**
     * Traduit une table « clé => texte » par lots (le HTML est conservé).
     * @return array<string,string>
     */
    public static function translateMap(array $src): array
    {
        $out = [];
        $batch = [];
        $size = 0;
        $flush = function () use (&$batch, &$size, &$out): void {
            if (!$batch) {
                return;
            }
            $keys = array_keys($batch);
            $payload = [];
            foreach ($keys as $n => $k) {
                $payload['t' . $n] = $batch[$k];
            }
            $r = self::call($payload);
            foreach ($keys as $n => $k) {
                $v = $r['t' . $n] ?? null;
                if (is_string($v) && trim($v) !== '') {
                    $out[$k] = self::clean($v, $batch[$k]);
                }
            }
            $batch = [];
            $size = 0;
        };
        foreach ($src as $k => $text) {
            $len = mb_strlen($text);
            if ($size + $len > self::BATCH && $batch) {
                $flush();
            }
            $batch[$k] = $text;
            $size += $len;
        }
        $flush();
        return $out;
    }

    /** Libellés d'interface manquants (écran « Traductions EN ») : français => anglais. */
    public static function strings(array $fr): array
    {
        $src = [];
        foreach (array_values($fr) as $i => $s) {
            $src["s$i"] = $s;
        }
        $tr = self::translateMap($src);
        $out = [];
        foreach (array_values($fr) as $i => $s) {
            if (isset($tr["s$i"])) {
                $out[$s] = $tr["s$i"];
            }
        }
        return $out;
    }

    private static function call(array $payload): array
    {
        $system = "Tu es traducteur professionnel du français vers l'anglais britannique pour le musée en ligne du FC Sochaux-Montbéliard (club de football français fondé en 1928).\n"
            . "Traduis chaque valeur de l'objet JSON fourni et renvoie UNIQUEMENT un objet JSON avec exactement les mêmes clés.\n"
            . "Règles :\n"
            . "- Conserve à l'identique les balises HTML, leurs attributs, les liens, les entités et les retours à la ligne.\n"
            . "- Ne traduis pas les noms propres : personnes, clubs, stades, villes, ni « FCSM », « Lionceaux », « Bonal ».\n"
            . "- Garde les noms officiels des compétitions françaises (Coupe de France, Coupe de la Ligue, Coupe Gambardella, Division 1, Division 2, Ligue 1, Ligue 2) ; « Coupe UEFA » devient « UEFA Cup », « Coupe d'Europe » devient « European Cup ».\n"
            . "- Conserve scores, dates, minutes (33') et chiffres. Vocabulaire du football britannique (pitch, match, fixture, kick-off, extra time, penalties).\n"
            . "- Style naturel et fidèle, sans ajout ni résumé. Un texte déjà en anglais reste tel quel.";
        $g = Gemini::generate([['role' => 'user', 'text' => json_encode($payload, JSON_UNESCAPED_UNICODE)]], $system, [
            'temperature' => 0.2,
            'max_tokens' => 8192,
            'json' => true,
            'timeout' => 90,
            'for' => 'traduction',
        ]);
        $text = trim($g['text']);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text);
        $data = json_decode((string) $text, true);
        if (!is_array($data)) {
            throw new \RuntimeException('réponse de traduction illisible');
        }
        return $data;
    }

    /** Garde-fous : HTML assaini, et retour au texte source si des balises ont disparu. */
    private static function clean(string $tr, string $src): string
    {
        if ($src !== strip_tags($src)) {
            $tagsSrc = preg_match_all('/<(a|img|table|ul|ol|li|strong|em|b|i)\b/i', $src);
            $tagsTr = preg_match_all('/<(a|img|table|ul|ol|li|strong|em|b|i)\b/i', $tr);
            if ($tagsTr < $tagsSrc * 0.8) {
                return $src;
            }
            return \App\Admin\Html::clean($tr);
        }
        return trim(strip_tags($tr));
    }

    private static function fail(int $id, string $msg): void
    {
        JsonStore::update(self::FAILS, function ($f) use ($id, $msg) {
            $f = $f ?: [];
            $f[$id] = ['at' => time(), 'msg' => mb_substr($msg, 0, 200), 'n' => (int) ($f[$id]['n'] ?? 0) + 1];
            return $f;
        }, []);
    }

    /**
     * Tâche planifiée : traduit les fiches publiées sans traduction (ou dont le
     * français a changé et dont la traduction est automatique).
     * Priorité : à la une, légendes, puis fiches les plus récemment modifiées.
     */
    public static function run(int $max = 10): array
    {
        if (!self::enabled() || !Settings::get('translation.auto_translate', true)) {
            return ['done' => 0, 'todo' => 0];
        }
        $fails = JsonStore::read(self::FAILS, []) ?: [];
        $cands = [];
        foreach (Index::all() as $id => $s) {
            if (!Index::visible($s)) {
                continue;
            }
            $f = $fails[$id] ?? null;
            if ($f && $f['at'] > time() - 86400 * min(7, $f['n'])) {
                continue; // nouvel essai plus tard
            }
            $prio = ($s['a_la_une'] ? 3 : 0) + (!empty($s['p']['legend']) ? 2 : 0) + (!$s['has_en'] ? 1 : 0);
            $cands[$id] = [$prio, (string) ($s['modified'] ?? '')];
        }
        uasort($cands, fn ($a, $b) => [$b[0], $b[1]] <=> [$a[0], $a[1]]);
        $done = 0;
        $todo = 0;
        foreach (array_keys($cands) as $id) {
            $doc = Fiches::get((int) $id);
            if (!$doc) {
                continue;
            }
            $st = self::status($doc);
            if ($st === 'auto' || $st === 'manual' || ($st === 'stale' && !empty($doc['i18n']['en']['_manual']))) {
                continue;
            }
            $todo++;
            if ($done >= $max) {
                continue;
            }
            $r = self::translateFiche((int) $id);
            if ($r === 'ok') {
                $done++;
            } elseif (str_contains($r, 'quota')) {
                break;
            }
        }
        return ['done' => $done, 'todo' => $todo - $done];
    }
}
