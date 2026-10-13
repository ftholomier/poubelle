<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Front\Donations;
use App\Services\Qr;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Faire un don : accroche courte, jauge de la collecte (mêmes chiffres que la page « Faire un
 * don » : collecté, objectif, pourcentage, donateurs ; seulement si un objectif est fixé, comme
 * sur le site), bouton vers /faire-un-don/ (lien compté) et QR code (SVG en ligne, calculé au
 * serveur) vers l'adresse complète du don, avec la provenance (utm_source=widget,
 * utm_medium=don, utm_content=qr). Montants suggérés de la page en option. Rien à montrer si les
 * dons sont fermés : même règle que Soutenir (dons activés, moyen de paiement prêt, bouton « don »
 * affiché sur le site).
 */
final class Don extends Widget
{
    /** Essais seulement : force l'ouverture (true) ou la fermeture (false) des dons. */
    public static ?bool $open = null;

    public const PATH = '/faire-un-don/';

    public function key(): string
    {
        return 'don';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Donate' : 'Faire un don';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Donate to the museum: the fundraising gauge, a button and a QR code to scan.'
            : 'Faire un don au musée : la jauge de la collecte, un bouton et un QR code à scanner.';
    }

    public function options(): array
    {
        $yn = ['oui' => 'Oui', 'non' => 'Non'];
        $o = [
            'qr' => ['label' => 'QR code', 'label_en' => 'QR code', 'type' => 'select', 'choices' => $yn, 'default' => 'oui'],
            'jauge' => ['label' => 'Jauge de la collecte', 'label_en' => 'Fundraising gauge', 'type' => 'select', 'choices' => $yn, 'default' => 'oui'],
        ];
        // Montants suggérés : seulement si la page « Faire un don » en propose.
        if (self::tiers() !== []) {
            $o['montants'] = ['label' => 'Montants suggérés', 'label_en' => 'Suggested amounts', 'type' => 'select', 'choices' => $yn, 'default' => 'non'];
        }
        return $o;
    }

    public function height(): int
    {
        return 520;
    }

    public function ttl(): int
    {
        return 300;
    }

    public static function isOpen(): bool
    {
        return self::$open ?? isset(Soutenir::open()['don']);
    }

    /** Montants suggérés de la page « Faire un don » (dans la langue courante). @return list<array{amount:int,impact:string}> */
    public static function tiers(): array
    {
        try {
            return Donations::content()['tiers'];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Adresse complète du don pour le QR code, avec la provenance (et le partenaire). */
    public static function qrUrl(Context $c, string $content = 'qr'): string
    {
        $q = ['utm_source' => 'widget', 'utm_medium' => 'don', 'utm_content' => $content] + ($c->partner !== '' ? ['utm_campaign' => $c->partner] : []);
        return Context::abs($c->path(self::PATH)) . '?' . http_build_query($q);
    }

    /** Jauge telle que la page publique la montre (null sans objectif). */
    public static function gauge(): ?array
    {
        $g = Donations::gauge();
        return $g['goal'] > 0 ? $g : null;
    }

    public function data(array $opt, Context $c): ?array
    {
        if (!self::isOpen()) {
            return null;
        }
        $g = ($opt['jauge'] ?? 'oui') === 'oui' ? self::gauge() : null;
        $amounts = ($opt['montants'] ?? 'non') === 'oui' ? array_slice(self::tiers(), 0, 4) : [];
        return [
            'title' => $c->tr('Faire un don', 'Donate'),
            'text' => $c->tr('Le musée vit de bénévolat et de dons : chaque euro aide à numériser et partager les archives du club.', 'The museum runs on volunteers and donations: every euro helps digitise and share the club’s archives.'),
            'gauge' => $g ? ['raised' => $g['raised'], 'goal' => $g['goal'], 'pct' => $g['pct'], 'donors' => $g['donors'], 'label' => $c->en() ? t($g['label']) : $g['label']] : null,
            'amounts' => $amounts,
            'qr' => ($opt['qr'] ?? 'oui') === 'oui' ? self::qrUrl($c) : null,
            'href' => self::PATH,
        ];
    }

    public static function euros(int $n, Context $c): string
    {
        return $c->en() ? '€' . number_format($n, 0, '.', ',') : number_format($n, 0, ',', "\u{202f}") . "\u{a0}€";
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . e($c->tr('Soutenir le musée', 'Support the museum')) . '</p>'
            . '<div class="w-don' . ($d['qr'] ? ' w-don--qr' : '') . '"><div class="w-don__main">'
            . '<h2 class="w-title">' . e($d['title']) . '</h2><p class="w-text">' . e($d['text']) . '</p>';
        if ($g = $d['gauge']) {
            $pct = $c->en() ? (string) $g['pct'] : str_replace('.', ',', (string) $g['pct']);
            $h .= '<div class="w-gauge"><p class="w-gauge__nums"><b>' . e(self::euros($g['raised'], $c)) . '</b> ' . e($c->tr('sur', 'of')) . ' ' . e(self::euros($g['goal'], $c)) . '</p>'
                . '<div class="w-gauge__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . e((string) $g['pct']) . '" aria-label="' . e($c->tr('Avancement de la collecte', 'Fundraising progress')) . '"><i style="width:' . e((string) $g['pct']) . '%"></i></div>'
                . '<p class="w-gauge__note">' . e($pct . ' % · ' . $g['label']) . '</p></div>';
        }
        if ($d['amounts']) {
            $h .= '<ul class="w-amounts">';
            foreach ($d['amounts'] as $t) {
                $h .= '<li><b>' . e(self::euros((int) $t['amount'], $c)) . '</b><span>' . e($t['impact']) . '</span></li>';
            }
            $h .= '</ul>';
        }
        $h .= '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Faire un don', 'Donate')) . ' →</a></p></div>';
        if ($d['qr']) {
            $h .= '<figure class="w-qr">' . Qr::svg($d['qr'], 4, \App\Core\Club::color('navy')) . '<figcaption>' . e($c->tr('Scannez pour donner', 'Scan to donate')) . '</figcaption></figure>';
        }
        return $h . '</div>';
    }
}
