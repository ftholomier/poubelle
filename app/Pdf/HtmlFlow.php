<?php
declare(strict_types=1);

namespace App\Pdf;

/**
 * Texte des fiches (HTML nettoyé) vers la mise en page PDF : paragraphes, intertitres,
 * gras / italique / liens, listes, citations, tableaux et images.
 */
final class HtmlFlow
{
    private const BLOCKS = ['p', 'div', 'section', 'article', 'header', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'table', 'figure', 'hr', 'pre', 'dl', 'aside'];

    /** @var list<array> fragments du paragraphe en cours */
    private array $buf = [];
    /** @var list<array{src:string,caption:string}> images rencontrées dans le paragraphe en cours */
    private array $pendingImages = [];

    /**
     * @param callable(string):?array $image résout l'adresse d'une image en ressource du PDF
     */
    public function __construct(private Layout $l, private $image, private float $size = 10.5)
    {
    }

    public function render(string $html): void
    {
        $html = trim($html);
        if ($html === '') {
            return;
        }
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="pdf-root">' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $dom->getElementById('pdf-root');
        if ($root) {
            $this->blocks($root);
            $this->flush();
        }
    }

    private function blocks(\DOMNode $node): void
    {
        foreach ($node->childNodes as $c) {
            if ($c instanceof \DOMText) {
                $this->inline($c, []);
                continue;
            }
            if (!$c instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($c->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'video', 'audio', 'object', 'embed', 'form', 'button', 'svg', 'noscript'], true)) {
                continue;
            }
            if (!in_array($tag, self::BLOCKS, true)) {
                $this->inline($c, []);
                continue;
            }
            $this->flush();
            match ($tag) {
                'h1', 'h2', 'h3' => $this->l->h3($this->text($c)),
                'h4', 'h5', 'h6' => $this->l->para([Layout::run($this->text($c), 'serif-b', $this->size + 0.5, 'navy')], ['after' => 3]),
                'ul', 'ol' => $this->list($c, $tag === 'ol'),
                'blockquote' => $this->quote($c),
                'table' => $this->table($c),
                'figure' => $this->figure($c),
                'hr' => $this->rule(),
                'li' => $this->l->bullets([[Layout::run($this->text($c), 'serif', $this->size)]]),
                default => $this->blocks($c),
            };
            $this->flush();
        }
    }

    /** Fragments d'un nœud en ligne (gras, italique, liens…). */
    private function inline(\DOMNode $n, array $st): void
    {
        if ($n instanceof \DOMText) {
            $t = preg_replace('/\s+/u', ' ', $n->nodeValue ?? '') ?? '';
            if ($t !== '') {
                $font = !empty($st['b']) ? 'serif-b' : (!empty($st['i']) ? 'serif-i' : 'serif');
                $this->buf[] = Layout::run($t, $font, $st['s'] ?? $this->size, !empty($st['u']) ? 'blue' : ($st['c'] ?? 'ink'), $st['u'] ?? null);
            }
            return;
        }
        if (!$n instanceof \DOMElement) {
            return;
        }
        $tag = strtolower($n->tagName);
        if ($tag === 'br') {
            $this->buf[] = Layout::run("\n", 'serif', $this->size);
            return;
        }
        if ($tag === 'img') {
            $this->pendingImages[] = ['src' => (string) $n->getAttribute('src'), 'caption' => trim((string) ($n->getAttribute('title') ?: ''))];
            return;
        }
        if (in_array($tag, ['script', 'style', 'iframe', 'svg', 'button'], true)) {
            return;
        }
        if (in_array($tag, self::BLOCKS, true)) {
            // Bloc dans une ligne (div dans un lien…) : traité comme du texte suivi d'un retour
            foreach ($n->childNodes as $c) {
                $this->inline($c, $st);
            }
            $this->buf[] = Layout::run("\n", 'serif', $this->size);
            return;
        }
        $st2 = $st;
        if (in_array($tag, ['strong', 'b'], true)) {
            $st2['b'] = true;
        }
        if (in_array($tag, ['em', 'i', 'cite'], true)) {
            $st2['i'] = true;
        }
        if (in_array($tag, ['small', 'sup', 'sub'], true)) {
            $st2['s'] = ($st['s'] ?? $this->size) * 0.8;
        }
        if ($tag === 'a') {
            $href = trim((string) $n->getAttribute('href'));
            if ($href !== '' && !str_starts_with($href, '#') && !preg_match('#^(javascript|data|vbscript):#i', $href)) {
                $st2['u'] = str_starts_with($href, '/') ? base_url() . $href : $href;
            }
        }
        foreach ($n->childNodes as $c) {
            $this->inline($c, $st2);
        }
    }

    private function flush(): void
    {
        $runs = $this->buf;
        $this->buf = [];
        // Retours à la ligne en début ou fin de paragraphe : ignorés
        while ($runs && trim($runs[0]['t']) === '') {
            array_shift($runs);
        }
        while ($runs && trim(end($runs)['t']) === '') {
            array_pop($runs);
        }
        if ($runs) {
            $runs[0]['t'] = ltrim($runs[0]['t']);
            $this->l->para($runs, ['after' => 7]);
        }
        foreach ($this->pendingImages as $im) {
            $res = ($this->image)($im['src']);
            if ($res) {
                $this->l->figure($res, $im['caption'], ['h' => 280]);
            }
        }
        $this->pendingImages = [];
    }

    private function text(\DOMNode $n): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $n->textContent ?? ''));
    }

    private function list(\DOMNode $list, bool $numbered): void
    {
        $items = [];
        foreach ($list->childNodes as $li) {
            if (!$li instanceof \DOMElement || strtolower($li->tagName) !== 'li') {
                continue;
            }
            $this->buf = [];
            $nested = [];
            foreach ($li->childNodes as $c) {
                if ($c instanceof \DOMElement && in_array(strtolower($c->tagName), ['ul', 'ol'], true)) {
                    $nested[] = $c;
                    continue;
                }
                $this->inline($c, []);
            }
            $runs = $this->buf;
            $this->buf = [];
            while ($runs && trim(end($runs)['t']) === '') {
                array_pop($runs);
            }
            if ($runs) {
                $runs[0]['t'] = ltrim($runs[0]['t']);
                $items[] = $runs;
            }
            foreach ($nested as $nl) {
                foreach ($nl->childNodes as $sub) {
                    if ($sub instanceof \DOMElement && strtolower($sub->tagName) === 'li') {
                        $t = $this->text($sub);
                        if ($t !== '') {
                            $items[] = [Layout::run('– ' . $t, 'serif', $this->size - 0.5, 'muted')];
                        }
                    }
                }
            }
        }
        if ($items) {
            $this->l->bullets($items, ['numbered' => $numbered]);
        }
        $imgs = $this->pendingImages;
        $this->pendingImages = [];
        foreach ($imgs as $im) {
            $res = ($this->image)($im['src']);
            if ($res) {
                $this->l->figure($res, $im['caption'], ['h' => 280]);
            }
        }
    }

    private function quote(\DOMElement $q): void
    {
        $this->buf = [];
        foreach ($q->childNodes as $c) {
            $this->inline($c, ['i' => true]);
            if ($c instanceof \DOMElement && in_array(strtolower($c->tagName), ['p', 'div'], true)) {
                $this->buf[] = Layout::run("\n", 'serif', $this->size);
            }
        }
        $runs = $this->buf;
        $this->buf = [];
        while ($runs && trim(end($runs)['t']) === '') {
            array_pop($runs);
        }
        if ($runs) {
            foreach ($runs as &$r) {
                $r['c'] = $r['u'] ? 'blue' : 'navy';
                $r['s'] = $this->size + 0.5;
            }
            unset($r);
            $this->l->quote($runs);
        }
    }

    private function table(\DOMElement $t): void
    {
        $headers = [];
        $rows = [];
        foreach ($t->getElementsByTagName('tr') as $tr) {
            $cells = [];
            $isHead = false;
            foreach ($tr->childNodes as $cell) {
                if (!$cell instanceof \DOMElement || !in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                    continue;
                }
                $isHead = $isHead || strtolower($cell->tagName) === 'th';
                $cells[] = $this->text($cell);
            }
            if (!$cells) {
                continue;
            }
            $inHead = $tr->parentNode instanceof \DOMElement && strtolower($tr->parentNode->tagName) === 'thead';
            if (!$headers && !$rows && ($inHead || ($isHead && count(array_filter($cells)) === count($cells)))) {
                $headers = $cells;
            } else {
                $rows[] = $cells;
            }
        }
        $this->tableData($headers, $rows);
    }

    /** Tableau de données (cellules texte) : chiffres alignés à droite, ligne « Total » en gras. */
    public function tableData(array $headers, array $rows, string $title = ''): void
    {
        if (!$rows) {
            return;
        }
        $n = max(count($headers), max(array_map('count', $rows)));
        $align = [];
        for ($i = 0; $i < $n; $i++) {
            $vals = array_filter(array_column($rows, $i), fn ($v) => trim((string) $v) !== '');
            $num = $vals && count(array_filter($vals, fn ($v) => preg_match('/^[\d\s.,+\-–%\/()]+$/u', trim((string) $v)))) >= count($vals) * 0.8;
            $align[] = $i > 0 && $num ? 'r' : 'l';
        }
        $out = [];
        foreach ($rows as $r) {
            $total = (bool) preg_match('/^total/iu', trim((string) ($r[0] ?? '')));
            $row = [];
            for ($i = 0; $i < $n; $i++) {
                $row[] = ['t' => (string) ($r[$i] ?? ''), 'b' => $total || $i === 0];
            }
            if ($total) {
                $row['_bg'] = 'butter';
            }
            $out[] = $row;
        }
        if ($title !== '') {
            $this->l->h3($title);
        }
        $this->l->table($headers, $out, ['align' => $align, 'size' => $n > 7 ? 7.6 : 8.6]);
    }

    private function figure(\DOMElement $f): void
    {
        $caption = '';
        foreach ($f->getElementsByTagName('figcaption') as $fc) {
            $caption = $this->text($fc);
        }
        foreach ($f->getElementsByTagName('img') as $img) {
            $res = ($this->image)((string) $img->getAttribute('src'));
            if ($res) {
                $this->l->figure($res, $caption, ['h' => 300]);
            }
            return;
        }
    }

    private function rule(): void
    {
        $this->l->ensure(14);
        $this->l->line($this->l->ml, $this->l->y + 5, $this->l->ml + 60, $this->l->y + 5, 'yellow', 2);
        $this->l->gap(14);
    }
}
