<?php

declare(strict_types=1);

/**
 * Formulaires du back-office décrits par un schéma :
 *   ['key' => 'hero.title', 'type' => 'text', 'label' => 'Titre', 'help' => '…']
 * Types : text, textarea, html, image, images, list, number, select, checkbox, repeater,
 *         path, url, email, tel, secret, heading.
 * Le même schéma sert à l'affichage (render) et à l'enregistrement nettoyé (collect).
 */
final class Form
{
    public static function render(array $fields, array $values, string $prefix = 'data'): string
    {
        $h = '';
        foreach ($fields as $f) {
            $h .= self::field($f, $values, $prefix);
        }
        return $h;
    }

    private static function get(array $values, string $key)
    {
        $v = $values;
        foreach (explode('.', $key) as $k) {
            if (!is_array($v) || !array_key_exists($k, $v)) {
                return null;
            }
            $v = $v[$k];
        }
        return $v;
    }

    private static function name(string $prefix, string $key): string
    {
        return $prefix . '[' . implode('][', explode('.', $key)) . ']';
    }

    private static function field(array $f, array $values, string $prefix): string
    {
        $type = $f['type'] ?? 'text';
        if ($type === 'heading') {
            return '<div class="f-head"><h3>' . e($f['label']) . '</h3>' . (!empty($f['help']) ? '<p>' . e($f['help']) . '</p>' : '') . '</div>';
        }
        $key = $f['key'];
        $v = self::get($values, $key);
        $name = self::name($prefix, $key);
        $id = 'f-' . substr(md5($name), 0, 10);
        $label = '<label class="f-label" for="' . $id . '">' . e($f['label'] ?? $key) . (!empty($f['required']) ? ' <b>*</b>' : '') . '</label>';
        $help = !empty($f['help']) ? '<div class="f-help">' . e($f['help']) . '</div>' : '';
        $cls = 'f f--' . $type . (!empty($f['wide']) ? ' f--wide' : '') . (!empty($f['half']) ? ' f--half' : '');
        $req = !empty($f['required']) ? ' required' : '';
        $ph = isset($f['placeholder']) ? ' placeholder="' . e($f['placeholder']) . '"' : '';
        switch ($type) {
            case 'textarea':
                $in = '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($f['rows'] ?? 4) . '"' . $req . $ph . '>' . e((string) $v) . '</textarea>';
                break;
            case 'html':
                $in = '<div class="rte" data-rte><div class="rte__bar">'
                    . '<button type="button" data-cmd="bold" title="Gras"><b>G</b></button>'
                    . '<button type="button" data-cmd="italic" title="Italique"><i>I</i></button>'
                    . '<button type="button" data-cmd="h2" title="Titre">T2</button>'
                    . '<button type="button" data-cmd="h3" title="Sous-titre">T3</button>'
                    . '<button type="button" data-cmd="p" title="Paragraphe">¶</button>'
                    . '<button type="button" data-cmd="ul" title="Liste">• Liste</button>'
                    . '<button type="button" data-cmd="link" title="Lien">Lien</button>'
                    . '<button type="button" data-cmd="unlink" title="Retirer le lien">⨯ Lien</button>'
                    . '<button type="button" data-cmd="image" title="Image">Image</button>'
                    . '<button type="button" data-cmd="clear" title="Effacer la mise en forme">Tx</button>'
                    . '<button type="button" data-cmd="source" title="Code HTML" class="rte__src">HTML</button>'
                    . '</div><div class="rte__ed prose" contenteditable="true"></div>'
                    . '<textarea id="' . $id . '" name="' . e($name) . '" class="rte__ta" rows="12">' . e((string) $v) . '</textarea></div>';
                break;
            case 'image':
                $in = '<div class="img-f" data-image><div class="img-f__pv">' . ((string) $v !== '' ? '<img src="' . e($v) . '" alt="">' : '') . '</div>'
                    . '<div class="img-f__b"><input id="' . $id . '" type="text" name="' . e($name) . '" value="' . e((string) $v) . '" placeholder="/media/…"' . $req . '>'
                    . '<button type="button" class="b b--l" data-pick>Choisir…</button><button type="button" class="b b--l" data-clear>Retirer</button></div></div>';
                break;
            case 'images':
                $list = is_array($v) ? $v : [];
                $in = '<div class="imgs" data-images data-name="' . e($name) . '[]">';
                foreach ($list as $im) {
                    $in .= '<div class="imgs__i"><img src="' . e($im) . '" alt=""><input type="hidden" name="' . e($name) . '[]" value="' . e($im) . '"><span><button type="button" data-up title="Avant">←</button><button type="button" data-down title="Après">→</button><button type="button" data-del title="Retirer">×</button></span></div>';
                }
                $in .= '<button type="button" class="imgs__add" data-pick-multi>+ Ajouter</button></div>';
                break;
            case 'list':
                $txt = is_array($v) ? implode("\n", $v) : (string) $v;
                $in = '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($f['rows'] ?? 4) . '" placeholder="Une ligne par élément">' . e($txt) . '</textarea>';
                break;
            case 'number':
                $in = '<input id="' . $id . '" type="number" name="' . e($name) . '" value="' . e((string) $v) . '"' . (isset($f['min']) ? ' min="' . (int) $f['min'] . '"' : '') . (isset($f['max']) ? ' max="' . (int) $f['max'] . '"' : '') . ' step="' . e((string) ($f['step'] ?? 1)) . '"' . $req . '>';
                break;
            case 'select':
                $in = '<select id="' . $id . '" name="' . e($name) . '"' . $req . '>';
                foreach ($f['options'] as $ov => $ol) {
                    $ov = is_int($ov) && !empty($f['list']) ? $ol : $ov;
                    $in .= '<option value="' . e((string) $ov) . '"' . ((string) $v === (string) $ov ? ' selected' : '') . '>' . e($ol) . '</option>';
                }
                $in .= '</select>';
                break;
            case 'checkbox':
                return '<div class="' . $cls . '"><input type="hidden" name="' . e($name) . '" value="0"><label class="f-check"><input id="' . $id . '" type="checkbox" name="' . e($name) . '" value="1"' . (!empty($v) ? ' checked' : '') . '> <span>' . e($f['label']) . '</span></label>' . $help . '</div>';
            case 'secret':
                $set = !empty($f['is_set']);
                $in = '<input id="' . $id . '" type="password" name="' . e($name) . '" value="" autocomplete="new-password" placeholder="' . ($set ? '•••••••• (enregistré — laisser vide pour garder)' : 'Non réglé') . '">'
                    . ($set ? '<label class="f-check" style="margin-top:6px"><input type="checkbox" name="' . e($prefix . '[_clear][' . $key . ']') . '" value="1"> <span>Effacer la valeur enregistrée</span></label>' : '');
                break;
            case 'repeater':
                $rows = is_array($v) ? array_values($v) : [];
                $in = '<div class="rep" data-repeater data-name="' . e($name) . '">';
                foreach ($rows as $i => $row) {
                    $in .= self::repRow($f, is_array($row) ? $row : ['value' => $row], $name . '[' . $i . ']');
                }
                $in .= '<template>' . self::repRow($f, [], $name . '[__i__]') . '</template>';
                $in .= '<button type="button" class="b b--l" data-rep-add>+ ' . e($f['add'] ?? 'Ajouter') . '</button></div>';
                break;
            default:
                $t = in_array($type, ['url', 'email', 'tel'], true) ? $type : 'text';
                $max = isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '';
                $in = '<input id="' . $id . '" type="' . $t . '" name="' . e($name) . '" value="' . e(is_scalar($v) ? (string) $v : '') . '"' . $max . $req . $ph . '>';
        }
        return '<div class="' . $cls . '">' . $label . $in . $help . '</div>';
    }

    private static function repRow(array $f, array $row, string $name): string
    {
        $h = '<div class="rep__row"><div class="rep__tools"><button type="button" data-rep-up title="Monter">↑</button><button type="button" data-rep-down title="Descendre">↓</button><button type="button" data-rep-del title="Supprimer">×</button></div><div class="rep__fields">';
        foreach ($f['fields'] as $sf) {
            $h .= self::field($sf, $row, $name);
        }
        return $h . '</div></div>';
    }

    /** Valeurs envoyées, nettoyées selon le schéma et fusionnées dans $current. */
    public static function collect(array $fields, array $input, array $current = []): array
    {
        $out = $current;
        foreach ($fields as $f) {
            if (in_array($f['type'] ?? '', ['heading', 'secret'], true) || !empty($f['readonly'])) {
                continue;
            }
            $raw = self::get($input, $f['key']);
            if ($raw === null && ($f['type'] ?? '') !== 'checkbox' && ($f['type'] ?? '') !== 'repeater' && ($f['type'] ?? '') !== 'images') {
                continue;
            }
            $val = self::clean($f, $raw);
            $out = self::set($out, $f['key'], $val);
        }
        return $out;
    }

    private static function clean(array $f, $raw)
    {
        $type = $f['type'] ?? 'text';
        switch ($type) {
            case 'textarea':
                return Sanitizer::text($raw, (int) ($f['max'] ?? 5000));
            case 'html':
                return Sanitizer::html(is_string($raw) ? $raw : '');
            case 'image':
                return Sanitizer::image($raw);
            case 'images':
                return array_values(array_filter(array_map([Sanitizer::class, 'image'], is_array($raw) ? $raw : [])));
            case 'list':
                $lines = is_array($raw) ? $raw : preg_split('/\R/u', (string) $raw);
                return array_values(array_filter(array_map(fn($l) => Sanitizer::line($l, (int) ($f['max'] ?? 300)), $lines ?: []), fn($l) => $l !== ''));
            case 'number':
                $n = is_numeric($raw) ? 0 + $raw : 0;
                if (isset($f['min'])) {
                    $n = max($f['min'], $n);
                }
                if (isset($f['max'])) {
                    $n = min($f['max'], $n);
                }
                return $n;
            case 'select':
                $opts = !empty($f['list']) ? array_values($f['options']) : array_map('strval', array_keys($f['options']));
                return in_array((string) $raw, $opts, true) ? (string) $raw : (string) ($f['default'] ?? reset($opts));
            case 'checkbox':
                return is_array($raw) ? in_array('1', $raw, true) : (string) $raw === '1';
            case 'repeater':
                $rows = [];
                foreach (is_array($raw) ? $raw : [] as $k => $row) {
                    if ($k === '__i__' || !is_array($row)) {
                        continue;
                    }
                    $clean = self::collect($f['fields'], $row, []);
                    if (!empty($f['scalar'])) {
                        $clean = $clean['value'] ?? '';
                        if ($clean === '') {
                            continue;
                        }
                    } elseif (!array_filter($clean, fn($x) => $x !== '' && $x !== [] && $x !== false && $x !== 0)) {
                        continue;
                    }
                    $rows[] = $clean;
                }
                return $rows;
            case 'path':
                return Sanitizer::path($raw);
            case 'url':
                $u = Sanitizer::line($raw, 500);
                return $u === '' || Sanitizer::url($u) ? $u : '';
            case 'email':
                $m = Sanitizer::line($raw, 200);
                return filter_var($m, FILTER_VALIDATE_EMAIL) ? $m : '';
            default:
                return Sanitizer::line($raw, (int) ($f['max'] ?? 300));
        }
    }

    private static function set(array $a, string $key, $val): array
    {
        $keys = explode('.', $key);
        $last = array_pop($keys);
        $ref = &$a;
        foreach ($keys as $k) {
            if (!isset($ref[$k]) || !is_array($ref[$k])) {
                $ref[$k] = [];
            }
            $ref = &$ref[$k];
        }
        $ref[$last] = $val;
        return $a;
    }
}
