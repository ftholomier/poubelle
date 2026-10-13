<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Front\Pages;
use App\Widgets\Context;
use App\Widgets\Frame;
use App\Widgets\Photos;
use App\Widgets\Widget;

/** Il y a 5, 10, 20, 30 ans : le fait marquant de la date, N ans plus tôt (Pages::yearsAgo, celui de l'accueil). */
final class IlYA extends Widget
{
    public const YEARS = [5, 10, 20, 30];

    public function key(): string
    {
        return 'il-y-a';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Years ago' : 'Il y a 5, 10, 20, 30 ans';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'What happened at the club 5, 10, 20 and 30 years ago, with tabs.'
            : 'Le fait marquant du club il y a 5, 10, 20 et 30 ans, avec des onglets.';
    }

    public function options(): array
    {
        return ['ans' => ['label' => 'Écart', 'label_en' => 'Gap', 'type' => 'select', 'default' => 'tous',
            'choices' => ['tous' => 'Tous (onglets)', '5' => '5 ans', '10' => '10 ans', '20' => '20 ans', '30' => '30 ans']]];
    }

    public function height(): int
    {
        return 480;
    }

    public function data(array $opt, Context $c): ?array
    {
        $items = [];
        foreach (Pages::yearsAgo(self::YEARS) as $it) {
            if ($opt['ans'] !== 'tous' && (int) $it['n'] !== (int) $opt['ans']) {
                continue;
            }
            $photo = Photos::safe((string) ($it['image'] ?? ''), 800);
            $href = (string) ($it['href'] ?? '');
            $href = preg_replace('#^/en(?=/)#', '', $href) ?? $href; // chemin neutre : la langue est remise au lien
            $items[] = [
                'years' => (int) $it['n'],
                'date' => (string) $it['date'],
                'kind' => (string) $it['kind'],
                'title' => (string) $it['title'],
                'text' => trim(strip_tags((string) $it['text'])),
                'score' => $it['score'] ?? null,
                'href' => $href !== '' && str_starts_with($href, '/') ? $href : '/',
                'image' => $photo ? ['url' => $photo['url'], 'credit' => $photo['credit'], 'license' => $photo['license']] : null,
            ];
        }
        return $items ? ['items' => $items] : null;
    }

    public function body(array $d, Context $c): string
    {
        $one = count($d['items']) === 1;
        $inputs = $labels = $panes = $css = '';
        foreach ($d['items'] as $i => $it) {
            $n = (int) $it['years'];
            $pane = '<p class="w-eyebrow">' . e($c->en() ? $n . ' years ago' : 'Il y a ' . $n . ' ans') . ' · ' . e($it['kind']) . '</p>'
                . Frame::media($it['image'], $it['title'])
                . '<p class="w-title"><a href="' . e($it['href']) . '" target="_blank" rel="noopener">' . e($it['title']) . '</a>' . ($it['score'] ? ' <span class="w-score">' . e($it['score']) . '</span>' : '') . '</p>'
                . ($it['text'] !== '' ? '<p class="w-text w-clamp">' . e($it['text']) . '</p>' : '');
            if ($one) {
                return $pane;
            }
            $inputs .= '<input class="w-tabin" type="radio" name="ilya" id="ilya-' . $n . '"' . ($i === 0 ? ' checked' : '') . '>';
            $labels .= '<label for="ilya-' . $n . '">' . e($c->en() ? $n . ' yrs' : $n . ' ans') . '</label>';
            $panes .= '<section class="w-pane w-pane-' . $n . '">' . $pane . '</section>';
            $css .= '#ilya-' . $n . ':checked~.w-panes .w-pane-' . $n . '{display:block}#ilya-' . $n . ':checked~.w-tabbar label[for=ilya-' . $n . ']{background:var(--accent);color:var(--on-accent);border-color:var(--accent)}';
        }
        return '<style>' . $css . '</style><div class="w-tabs">' . $inputs . '<div class="w-tabbar" role="tablist">' . $labels . '</div><div class="w-panes">' . $panes . '</div></div>';
    }
}
