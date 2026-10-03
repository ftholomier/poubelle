<?php
declare(strict_types=1);

namespace App\Admin;

/**
 * Champs de formulaire du back-office (HTML). Le nom est un chemin pointé
 * (« match.home.name ») ; préfixé de « @ », il est relatif à l'élément de liste
 * qui le contient (répéteurs). Les formulaires sont envoyés en JSON (admin.js).
 */
final class Form
{
    private static int $uid = 0;

    private static function nameAttr(string $name): string
    {
        return str_starts_with($name, '@') ? ' data-field="' . e(substr($name, 1)) . '"' : ' name="' . e($name) . '"';
    }

    private static function id(): string
    {
        return 'f' . (++self::$uid);
    }

    private static function wrap(string $label, string $control, array $o, ?string $for = null): string
    {
        $cls = 'f' . (!empty($o['class']) ? ' ' . $o['class'] : '') . (!empty($o['missing']) ? ' is-missing' : '');
        $k = '<span class="f__k">' . ($for ? '<label for="' . $for . '">' . e($label) . '</label>' : e($label))
            . (!empty($o['required']) ? ' <b aria-hidden="true">*</b>' : '')
            . (!empty($o['hint']) ? ' <i>' . e($o['hint']) . '</i>' : '') . '</span>';
        $help = !empty($o['help']) ? '<span class="f__help">' . $o['help'] . '</span>' : '';
        $attrs = !empty($o['show_if']) ? ' data-show-if="' . e($o['show_if']) . '"' . (isset($o['show_value']) ? ' data-show-value="' . e($o['show_value']) . '"' : '') : '';
        return '<div class="' . $cls . '"' . $attrs . '>' . $k . $control . $help . '</div>';
    }

    private static function attrs(array $o): string
    {
        $a = '';
        foreach (['placeholder', 'maxlength', 'min', 'max', 'step', 'pattern', 'autocomplete', 'inputmode', 'list'] as $k) {
            if (isset($o[$k]) && $o[$k] !== '') {
                $a .= ' ' . $k . '="' . e((string) $o[$k]) . '"';
            }
        }
        if (!empty($o['required'])) {
            $a .= ' required';
        }
        if (!empty($o['readonly'])) {
            $a .= ' readonly';
        }
        if (!empty($o['disabled'])) {
            $a .= ' disabled';
        }
        if (!empty($o['type_data'])) {
            $a .= ' data-type="' . e($o['type_data']) . '"';
        }
        if (!empty($o['ac'])) {
            $a .= ' data-ac="' . e($o['ac']) . '" autocomplete="off"';
        }
        if (!empty($o['ac_id'])) {
            $a .= ' data-ac-id="' . e($o['ac_id']) . '"';
        }
        if (!empty($o['count'])) {
            $a .= ' data-count-max="' . (int) $o['count'] . '"';
        }
        foreach ($o['data'] ?? [] as $k => $v) {
            $a .= ' data-' . e($k) . '="' . e((string) $v) . '"';
        }
        return $a;
    }

    public static function text(string $name, string $label, mixed $value, array $o = []): string
    {
        $id = self::id();
        $type = $o['type'] ?? 'text';
        $ctl = '<input id="' . $id . '" type="' . e($type) . '"' . self::nameAttr($name) . ' value="' . e(is_scalar($value) ? (string) $value : '') . '"' . self::attrs($o) . '>';
        return self::wrap($label, $ctl, $o, $id);
    }

    public static function number(string $name, string $label, mixed $value, array $o = []): string
    {
        $o['type'] = 'text';
        $o['inputmode'] = $o['inputmode'] ?? (($o['decimal'] ?? false) ? 'decimal' : 'numeric');
        $o['type_data'] = ($o['decimal'] ?? false) ? 'number' : 'int';
        return self::text($name, $label, $value === null ? '' : (string) $value, $o);
    }

    /** Texte sur plusieurs lignes : toujours un éditeur visuel (barre réduite). */
    public static function textarea(string $name, string $label, mixed $value, array $o = []): string
    {
        $id = self::id();
        $rows = (int) ($o['rows'] ?? 4);
        $ctl = '<textarea id="' . $id . '" rows="' . $rows . '" data-wysiwyg="mini"' . self::nameAttr($name) . self::attrs($o) . '>' . e(is_scalar($value) ? (string) $value : '') . '</textarea>';
        return self::wrap($label, $ctl, $o, $id);
    }

    /** Texte riche (WYSIWYG). $o['mini'] : barre réduite. */
    public static function html(string $name, string $label, mixed $value, array $o = []): string
    {
        $id = self::id();
        $ctl = '<textarea id="' . $id . '" rows="6" data-wysiwyg="' . (!empty($o['mini']) ? 'mini' : 'full') . '"' . self::nameAttr($name) . self::attrs($o) . '>' . e(is_scalar($value) ? (string) $value : '') . '</textarea>';
        return self::wrap($label, $ctl, $o + ['class' => 'f--full'], $id);
    }

    /** Liste de textes courts (une ligne chacun), réordonnables. */
    public static function lines(string $name, string $label, array $list, array $o = []): string
    {
        $values = array_values(array_filter(array_map(fn ($x) => is_scalar($x) ? (string) $x : '', $list), fn ($x) => $x !== ''));
        $field = fn ($v) => '<input type="text" data-field="_" value="' . e((string) $v) . '"' . (!empty($o['placeholder']) ? ' placeholder="' . e($o['placeholder']) . '"' : '') . ' aria-label="' . e($label) . '">';
        $rep = self::repeater($name, '', $values, fn ($v) => $field($v), ['scalar' => true, 'compact' => true, 'add' => $o['add'] ?? 'Ajouter une ligne']);
        return self::wrap($label, $rep, $o + ['class' => 'f--full']);
    }

    /** @param array<string,string> $options */
    public static function select(string $name, string $label, mixed $value, array $options, array $o = []): string
    {
        $id = self::id();
        $html = '<select id="' . $id . '"' . self::nameAttr($name) . self::attrs($o) . '>';
        if (isset($o['empty'])) {
            $html .= '<option value="">' . e($o['empty']) . '</option>';
        }
        $found = false;
        foreach ($options as $k => $l) {
            $sel = (string) $k === (string) $value;
            $found = $found || $sel;
            $html .= '<option value="' . e((string) $k) . '"' . ($sel ? ' selected' : '') . '>' . e($l) . '</option>';
        }
        if (!$found && $value !== null && $value !== '' && empty($o['strict'])) {
            $html .= '<option value="' . e((string) $value) . '" selected>' . e((string) $value) . '</option>';
        }
        return self::wrap($label, $html . '</select>', $o, $id);
    }

    /** Case à cocher (booléen). */
    public static function toggle(string $name, string $label, bool $checked, array $o = []): string
    {
        $help = !empty($o['help']) ? '<span class="f__help">' . $o['help'] . '</span>' : '';
        return '<div class="f' . (!empty($o['class']) ? ' ' . $o['class'] : '') . '"><label class="toggle"><input type="checkbox"' . self::nameAttr($name) . ($checked ? ' checked' : '') . '><span class="toggle__box"></span><span>' . e($label) . '</span></label>' . $help . '</div>';
    }

    /** Choix exclusif en boutons (segmenté). */
    public static function seg(string $name, string $label, mixed $value, array $options, array $o = []): string
    {
        $grp = 'g' . self::id();
        $html = '<div class="seg" style="--n:' . min(4, max(2, count($options))) . '" role="radiogroup">';
        foreach ($options as $k => $l) {
            $html .= '<label><input type="radio"' . (str_starts_with($name, '@') ? ' data-field="' . e(substr($name, 1)) . '" name="' . $grp . '"' : ' name="' . e($name) . '"') . ' value="' . e((string) $k) . '"' . ((string) $k === (string) $value ? ' checked' : '') . '><span>' . e($l) . '</span></label>';
        }
        return self::wrap($label, $html . '</div>', $o);
    }

    /** Cases multiples → tableau de valeurs. */
    public static function checks(string $name, string $label, array $values, array $options, array $o = []): string
    {
        $html = '<div class="checklist">';
        foreach ($options as $k => $l) {
            $html .= '<label><input type="checkbox"' . self::nameAttr($name) . ' data-multi data-value="' . e((string) $k) . '"' . (in_array((string) $k, array_map('strval', $values), true) ? ' checked' : '') . '> ' . e($l) . '</label>';
        }
        // Champ caché : garantit un tableau vide si rien n'est coché.
        $html .= '<input type="hidden"' . self::nameAttr($name . '__present') . ' value="1">';
        return self::wrap($label, $html . '</div>', $o + ['class' => 'f--full']);
    }

    /** Image de la médiathèque. */
    public static function image(string $name, string $label, ?string $rel, array $o = []): string
    {
        $html = '<div class="imgpick" data-image-field><div class="imgpick__prev">Aucune image</div><div class="stack" style="gap:6px">'
            . '<input type="hidden"' . self::nameAttr($name) . ' value="' . e((string) $rel) . '">'
            . '<span class="xs muted" data-image-name style="overflow-wrap:anywhere"></span>'
            . '<div class="row"><button type="button" class="btn btn--sm" data-image-pick>Choisir…</button><button type="button" class="btn btn--sm btn--ghost" data-image-clear>Retirer</button></div></div></div>';
        return self::wrap($label, $html, $o);
    }

    public static function hidden(string $name, mixed $value, array $o = []): string
    {
        return '<input type="hidden"' . self::nameAttr($name) . ' value="' . e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)) . '"' . self::attrs($o) . '>';
    }

    /**
     * Liste d'éléments réordonnables. $render($item, $index) renvoie le HTML d'un élément
     * (champs en « @relatif »). $index = -1 pour le modèle d'ajout.
     */
    public static function repeater(string $path, string $label, array $items, callable $render, array $o = []): string
    {
        $item = function (array|string $it, int $i) use ($render, $o): string {
            return '<div class="rep__item" data-item>'
                . '<div class="rep__handle" title="Glisser pour déplacer" aria-hidden="true">' . (!empty($o['numbered']) ? '<span data-item-n>' . ($i >= 0 ? sprintf('%02d', $i + 1) : '') . '</span>' : '⋮⋮') . '</div>'
                . '<div class="rep__body">' . $render($it, $i) . '</div>'
                . '<div class="rep__tools"><button type="button" class="iconbtn" data-rep-up title="Monter" aria-label="Monter">↑</button><button type="button" class="iconbtn" data-rep-down title="Descendre" aria-label="Descendre">↓</button>'
                . (!empty($o['dup']) ? '<button type="button" class="iconbtn" data-rep-dup title="Dupliquer" aria-label="Dupliquer">⧉</button>' : '')
                . '<button type="button" class="iconbtn" data-rep-del title="Supprimer" aria-label="Supprimer">✕</button></div></div>';
        };
        $html = '<div class="rep' . (!empty($o['compact']) ? ' rep--compact' : '') . '" data-repeater="' . e($path) . '"' . (!empty($o['scalar']) ? ' data-scalar' : '') . '>';
        foreach (array_values($items) as $i => $it) {
            $html .= $item($it, $i);
        }
        $html .= '<template>' . $item(is_array($o['blank'] ?? null) ? $o['blank'] : (!empty($o['scalar']) ? '' : []), -1) . '</template>';
        $html .= !empty($o['gallery']) ? '<div class="row" data-rep-add-wrap><button type="button" class="rep__add" data-gallery-add style="flex:1">+ ' . e($o['add'] ?? 'Ajouter des images') . '</button></div>' : '<button type="button" class="rep__add" data-rep-add>+ ' . e($o['add'] ?? 'Ajouter') . '</button>';
        $html .= '</div>';
        return $label === '' ? '<div class="f f--full">' . $html . '</div>' : self::wrap($label, $html, ($o['wrap'] ?? []) + ['class' => 'f--full']);
    }
}
