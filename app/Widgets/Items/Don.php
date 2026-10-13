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
        return $lang === 'en' ? 'Donate to the museum: any amount, once or monthly, with a button and a QR code to scan.'
            : 'Faire un don au musée : le montant de son choix, une fois ou chaque mois, avec un bouton et un QR code à scanner.';
    }

    public function options(): array
    {
        $yn = ['oui' => 'Oui', 'non' => 'Non'];
        $o = [
            'qr' => ['label' => 'QR code', 'label_en' => 'QR code', 'type' => 'select', 'choices' => $yn, 'default' => 'oui'],
            'montants' => ['label' => 'Montants (dont montant libre)', 'label_en' => 'Amounts (incl. any amount)', 'type' => 'select', 'choices' => $yn, 'default' => 'oui'],
        ];
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
        // Visible dès que le bouton « don » du site est affiché (page don du musée, ou lien externe
        // type HelloAsso), même sans paiement en ligne configuré : la page don présente les moyens.
        return self::$open ?? \App\Front\Menus::button('don') !== null;
    }

    /** Lien du don : la page du musée, ou l'adresse externe du bouton « don » s'il pointe ailleurs. */
    public static function external(): ?string
    {
        $b = \App\Front\Menus::button('don');
        return $b && !empty($b['ext']) && preg_match('#^https?://#i', (string) $b['href']) ? (string) $b['href'] : null;
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

    /** Montants affichés (ceux de la page don, sinon 10, 30, 50 €), sans objectif ni jauge : on donne ce qu'on veut. @return list<int> */
    public static function amounts(): array
    {
        $a = array_values(array_filter(array_map(fn ($t) => (int) ($t['amount'] ?? 0), self::tiers())));
        return array_slice($a ?: [10, 30, 50], 0, 4);
    }

    /** Phrase sous les montants. */
    public static function freeLine(Context $c): string
    {
        return $c->tr('Une fois ou chaque mois, du montant de votre choix : même un petit don compte.', 'Once or every month, any amount you like: even a small gift counts.');
    }

    /** Adresse complète du don pour le QR code, avec la provenance (et le partenaire). */
    public static function qrUrl(Context $c, string $content = 'qr'): string
    {
        if (($ext = self::external()) !== null) {
            return $ext;
        }
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
        $amounts = ($opt['montants'] ?? 'oui') === 'oui' ? self::amounts() : [];
        return [
            'title' => $c->tr('Faire un don', 'Donate'),
            'text' => $c->tr('Le musée vit de bénévolat et de dons : chaque euro aide à numériser et partager les archives du club.', 'The museum runs on volunteers and donations: every euro helps digitise and share the club’s archives.'),
            'amounts' => $amounts,
            'qr' => ($opt['qr'] ?? 'oui') === 'oui' ? self::qrUrl($c) : null,
            'href' => self::external() ?? self::PATH,
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
        if ($d['amounts']) {
            $h .= '<ul class="w-chips">';
            foreach ($d['amounts'] as $n) {
                $h .= '<li>' . e(self::euros((int) $n, $c)) . '</li>';
            }
            $h .= '<li class="is-free">' . e($c->tr('Montant libre', 'Any amount')) . '</li></ul><p class="w-text small">' . e(self::freeLine($c)) . '</p>';
        }
        $h .= '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Faire un don', 'Donate')) . ' →</a></p></div>';
        if ($d['qr']) {
            $h .= '<figure class="w-qr">' . Qr::svg($d['qr'], 4, \App\Core\Club::color('navy')) . '<figcaption>' . e($c->tr('Scannez pour donner', 'Scan to donate')) . '</figcaption></figure>';
        }
        return $h . '</div>';
    }
}
