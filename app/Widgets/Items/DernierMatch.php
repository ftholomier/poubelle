<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Data\Fiches;
use App\Data\Index;
use App\Front\Site;
use App\Widgets\Context;
use App\Widgets\Frame;
use App\Widgets\Photos;
use App\Widgets\Widget;

/** Dernier match fiché (le même que le bandeau du site : Site::lastMatchCreated) : score, date, compétition, début du récit. */
final class DernierMatch extends Widget
{
    public function key(): string
    {
        return 'dernier-match';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Latest match' : 'Dernier match fiché';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'The latest match on record in the museum: score, date, competition and the start of its story.'
            : 'Le dernier match fiché dans le musée : score, date, compétition et le début de son récit.';
    }

    public function height(): int
    {
        return 540;
    }

    public function data(array $opt, Context $c): ?array
    {
        $m = Site::lastMatchCreated();
        if (!$m) {
            return null;
        }
        $s = Index::get((int) $m['id']);
        $text = (string) ($s['excerpt'] ?? '');
        if ($c->en()) {
            $doc = Fiches::get((int) $m['id']);
            $en = (array) ($doc['i18n']['en'] ?? []);
            $text = (string) (($en['excerpt'] ?? '') ?: ($en['intro'] ?? '') ?: ($en['sections'][0]['html'] ?? '') ?: $text);
        }
        $photo = Photos::safe((string) ($m['image'] ?? ''), 800);
        $score = $m['us'] !== null ? ($m['sh'] ? $m['us'] . '–' . $m['them'] : $m['them'] . '–' . $m['us']) : '';
        return [
            'label' => Site::matchLabel($m),
            'home' => (string) ($m['home'] ?? ''),
            'away' => (string) ($m['away'] ?? ''),
            'score' => $score,
            'date' => (string) $m['date'],
            'competition' => (string) (($m['label'] ?? '') ?: ($m['comp'] ?? '')),
            'round' => (string) ($m['round'] ?? ''),
            'result' => (string) ($m['result'] ?? ''),
            'spectators' => (int) ($m['spectators'] ?? 0) ?: null,
            'text' => self::excerpt($text, 320),
            'href' => (string) $m['path'],
            'image' => $photo ? ['url' => $photo['url'], 'credit' => $photo['credit'], 'license' => $photo['license']] : null,
        ];
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . e($c->tr('Dernier match fiché', 'Latest match on record')) . ' · ' . e($c->en() ? date('j F Y', strtotime($d['date'])) : date_fr($d['date'])) . '</p>';
        $h .= Frame::media($d['image'], $d['label']);
        $h .= '<div class="w-match"><a href="' . e($d['href']) . '" target="_blank" rel="noopener"><span>' . e($d['home']) . '</span><b>' . e($d['score'] !== '' ? $d['score'] : '–') . '</b><span>' . e($d['away']) . '</span></a></div>';
        $h .= '<p class="w-text w-center">' . e(trim($d['competition'] . ($d['round'] !== '' ? ' · ' . $d['round'] : ''))) . ($d['result'] !== '' ? ' <span class="w-res w-res--' . e(strtolower($d['result'])) . '">' . e(match ($d['result']) { 'V' => $c->tr('Victoire', 'Win'), 'N' => $c->tr('Nul', 'Draw'), 'D' => $c->tr('Défaite', 'Defeat'), default => $d['result'] }) . '</span>' : '') . '</p>';
        if ($d['text'] !== '') {
            $h .= '<p class="w-text w-clamp">' . e($d['text']) . '</p>';
        }
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Lire le récit', 'Read the story')) . ' →</a></p>';
    }
}
