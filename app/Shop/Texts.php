<?php
declare(strict_types=1);

namespace App\Shop;

use App\Data\Collections;
use App\Services\Gemini;

/**
 * Boutique › Banque de textes : listes de phrases (slogans, anecdotes…) que le client choisit
 * sur un modèle (champ du client « choix dans une liste »). Chaque phrase est validée avant
 * d'être proposée ; l'IA peut en proposer de nouvelles, qui arrivent « à valider ».
 * Stockage : data/collections/boutique-textes.json ; listes de départ :
 * app/Resources/shop/textes-depart.json (slogans et anecdotes du rapport boutique).
 */
final class Texts
{
    public const FILE = 'boutique-textes';
    public const MAX_TEXT = 160;
    /** Faux Gemini des tests : fn (string $system, string $user) => string (réponse JSON). */
    public static $ai = null;

    private const SYSTEM = 'Tu écris des phrases courtes pour des produits dérivés (t-shirts, mugs, posters) de Sochaux Rétro, '
        . 'le musée en ligne des supporters du FC Sochaux-Montbéliard (jaune et bleu, les Lionceaux, le stade Auguste-Bonal, fondé en 1928, centenaire en 2028). '
        . 'Règles : phrases originales, en français, 70 caractères au plus, ton fier, chaleureux ou drôle, jamais agressif envers un autre club ; '
        . 'aucun slogan ou marque existants, aucun nom de joueur vivant, aucune donnée chiffrée dont tu n’es pas certain. '
        . 'Ne répète aucune des phrases déjà dans la liste.';

    /** @return list<array{id:string,name:string,note:string,items:list<array>}> */
    public static function lists(): array
    {
        $all = Collections::get(self::FILE, null);
        if (!is_array($all)) {
            $all = self::starter();
        }
        return array_values(array_map([self::class, 'normalize'], array_filter($all, 'is_array')));
    }

    public static function find(string $id): ?array
    {
        foreach (self::lists() as $l) {
            if ($l['id'] === $id) {
                return $l;
            }
        }
        return null;
    }

    /** Phrases validées d'une liste (celles que le client peut choisir). @return list<string> */
    public static function choices(string $id): array
    {
        $l = self::find($id);
        return $l ? array_values(array_map(fn ($i) => $i['text'], array_filter($l['items'], fn ($i) => $i['ok']))) : [];
    }

    /** Listes pour l'éditeur : id => [name, choices]. */
    public static function forEditor(): array
    {
        $out = [];
        foreach (self::lists() as $l) {
            $out[$l['id']] = ['name' => $l['name'], 'choices' => self::choices($l['id'])];
        }
        return $out;
    }

    public static function save(array $list, ?array $user = null): array
    {
        $all = self::lists();
        $list['id'] = (string) ($list['id'] ?? '') !== '' ? (string) $list['id'] : bin2hex(random_bytes(4));
        $list = self::normalize($list);
        $found = false;
        foreach ($all as $i => $l) {
            if ($l['id'] === $list['id']) {
                $all[$i] = $list;
                $found = true;
            }
        }
        if (!$found) {
            $all[] = $list;
        }
        Collections::save(self::FILE, $all, $user, 'Banque de textes de la boutique : ' . $list['name']);
        return $list;
    }

    public static function delete(string $id, ?array $user = null): void
    {
        Collections::save(self::FILE, array_values(array_filter(self::lists(), fn ($l) => $l['id'] !== $id)), $user, 'Liste de textes supprimée');
    }

    /**
     * Phrases proposées par l'IA pour une liste (ajoutées « à valider », sans doublon).
     * @return int nombre de phrases ajoutées
     */
    public static function suggest(string $id, int $count = 10, string $hint = '', ?array $user = null): int
    {
        $l = self::find($id);
        if (!$l) {
            throw new \RuntimeException('Liste introuvable.');
        }
        $ask = 'Liste « ' . $l['name'] . ' »' . ($l['note'] !== '' ? ' (' . $l['note'] . ')' : '') . ".\n"
            . ($hint !== '' ? 'Consigne : ' . mb_substr($hint, 0, 300) . "\n" : '')
            . 'Propose ' . $count . " nouvelles phrases dans le même esprit. Réponds en JSON : une liste de chaînes.\n"
            . "Phrases déjà dans la liste :\n- " . implode("\n- ", array_map(fn ($i) => $i['text'], $l['items']));
        if (self::$ai !== null) {
            $text = (self::$ai)(self::SYSTEM, $ask);
        } else {
            if (!Gemini::ready()) {
                throw new \RuntimeException('Aucune clé API Gemini : réglez-la dans Système › Réglages › Intelligence artificielle.');
            }
            $text = Gemini::generate([['role' => 'user', 'text' => $ask]], self::SYSTEM, ['for' => 'boutique', 'ref' => 'boutique:textes:' . $id, 'json' => true,
                'schema' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']], 'temperature' => 0.9, 'max_tokens' => 2000])['text'];
        }
        $got = json_decode(trim((string) preg_replace('/^```(?:json)?|```$/m', '', (string) $text)), true);
        $seen = array_flip(array_map(fn ($i) => self::key($i['text']), $l['items']));
        $added = 0;
        foreach (is_array($got) ? $got : [] as $t) {
            $t = trim(str_replace("'", '’', (string) (is_array($t) ? ($t['text'] ?? '') : $t)));
            if ($t === '' || mb_strlen($t) > self::MAX_TEXT || isset($seen[self::key($t)]) || $added >= $count) {
                continue;
            }
            $seen[self::key($t)] = true;
            $l['items'][] = ['text' => $t, 'note' => 'Proposée par l’IA', 'ok' => false];
            $added++;
        }
        if ($added) {
            self::save($l, $user);
        }
        return $added;
    }

    private static function key(string $t): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower(\App\Data\Names::ascii($t)));
    }

    private static function normalize(array $l): array
    {
        $items = [];
        foreach ((array) ($l['items'] ?? []) as $i) {
            $t = trim(mb_substr((string) ($i['text'] ?? ''), 0, self::MAX_TEXT));
            if ($t !== '') {
                $items[] = ['id' => preg_replace('/[^a-z0-9]/', '', (string) ($i['id'] ?? '')) ?: substr(md5($t), 0, 8), 'text' => $t, 'note' => mb_substr(trim((string) ($i['note'] ?? '')), 0, 200), 'ok' => !empty($i['ok'])];
            }
        }
        return ['id' => (string) preg_replace('/[^a-z0-9]/', '', (string) ($l['id'] ?? '')), 'name' => mb_substr(trim((string) ($l['name'] ?? '')), 0, 80) ?: 'Liste', 'note' => mb_substr(trim((string) ($l['note'] ?? '')), 0, 300), 'items' => $items];
    }

    /** Listes de départ : slogans (validés) et anecdotes (à faire valider par un historien). */
    private static function starter(): array
    {
        $src = json_decode((string) @file_get_contents(dirname(__DIR__) . '/Resources/shop/textes-depart.json'), true) ?: [];
        $mk = fn (array $rows, bool $ok) => array_map(fn ($r) => ['text' => $r[0], 'note' => $r[1], 'ok' => $ok], $rows);
        return [
            ['id' => 'slogans', 'name' => 'Slogans', 'note' => 'Phrases courtes pour le devant des t-shirts, les mugs, les tote bags. À vérifier sur la base des marques de l’INPI avant impression.', 'items' => $mk($src['slogans'] ?? [], true)],
            ['id' => 'anecdotes', 'name' => 'Anecdotes « Le saviez-vous ? »', 'note' => 'Faits historiques en une phrase, pour le dos des t-shirts, les cartes et les posters. Un historien valide chaque phrase (source indiquée).', 'items' => $mk($src['anecdotes'] ?? [], false)],
        ];
    }
}
