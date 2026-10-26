<?php
declare(strict_types=1);

namespace LD\Controllers;

use LD\Router;
use LD\Solutions;

/**
 * Back-office › Solutions : afficher / masquer, ordre, création et modification des solutions présentées sur le site.
 */
final class AdminSolutions
{
    public static function routes(Router $r): void
    {
        $g = [Admin::class, 'guard'];
        $r->get('/admin/solutions', $g([self::class, 'list']));
        $r->get('/admin/solutions/{id}', $g([self::class, 'edit']));
        $r->post('/admin/solutions/{id}', $g([self::class, 'save'], true));
        $r->post('/admin/solutions/{id}/affichage', $g([self::class, 'toggle'], true));
        $r->post('/admin/solutions/{id}/ordre', $g([self::class, 'move'], true));
        $r->post('/admin/solutions/{id}/supprimer', $g([self::class, 'delete'], true));
    }

    public static function list(): never
    {
        Admin::view('solutions', ['solutions' => Solutions::all()], 'Solutions');
    }

    public static function edit(string $id): never
    {
        if ($id === 'intensiti') {
            \LD\Http::redirect('/admin/contenus/solutions');
        }
        $so = $id === 'nouvelle'
            ? ['id' => '', 'kind' => 'standard', 'enabled' => true, 'order' => 0, 'name' => '', 'short' => '', 'tagline' => '', 'text' => '', 'strengths' => [],
               'image' => '', 'logo' => '', 'theme' => 'clair', 'url' => '', 'cta' => '', 'demo_cta' => 'Demander une démo', 'demo_url' => '/on-prend-rdv']
            : (Solutions::find($id) ?? Admin::back('/admin/solutions', 'err', 'Solution introuvable.'));
        Admin::view('solution-edit', ['so' => $so], $id === 'nouvelle' ? 'Nouvelle solution' : 'Solution · ' . $so['name']);
    }

    public static function save(string $id): never
    {
        $p = $_POST;
        $name = clean_text((string) ($p['name'] ?? ''), 80);
        if ($name === '') {
            Admin::back('/admin/solutions/' . $id, 'err', 'Le nom de la solution est obligatoire.');
        }
        $existing = $id !== 'nouvelle' ? Solutions::find($id) : null;
        if ($id !== 'nouvelle' && (!$existing || $existing['kind'] !== 'standard')) {
            Admin::back('/admin/solutions', 'err', 'Solution introuvable.');
        }
        $url = static function (string $v): string {
            $v = trim($v);
            return $v === '' || str_starts_with($v, '/') || filter_var($v, FILTER_VALIDATE_URL) ? mb_substr($v, 0, 300) : 'https://' . ltrim($v, '/');
        };
        $so = Solutions::save([
            'id'        => $existing['id'] ?? '',
            'kind'      => 'standard',
            'enabled'   => !empty($p['enabled']),
            'order'     => (int) ($existing['order'] ?? 0),
            'name'      => $name,
            'short'     => clean_text((string) ($p['short'] ?? ''), 200),
            'tagline'   => clean_text((string) ($p['tagline'] ?? ''), 200),
            'text'      => trim(clean_text((string) ($p['text'] ?? ''), 3000)),
            'strengths' => array_slice(array_values(array_filter(array_map(static fn($l) => clean_text($l, 60), preg_split('/\R/u', (string) ($p['strengths'] ?? '')) ?: []))), 0, 8),
            'image'     => clean_text((string) ($p['image'] ?? ''), 300),
            'logo'      => clean_text((string) ($p['logo'] ?? ''), 300),
            'theme'     => isset(Solutions::THEMES[$p['theme'] ?? '']) ? $p['theme'] : 'clair',
            'url'       => $url((string) ($p['url'] ?? '')),
            'cta'       => clean_text((string) ($p['cta'] ?? ''), 60),
            'demo_cta'  => clean_text((string) ($p['demo_cta'] ?? ''), 60),
            'demo_url'  => $url((string) ($p['demo_url'] ?? '')) ?: '/on-prend-rdv',
        ]);
        Admin::back('/admin/solutions/' . $so['id'], 'ok', $so['enabled'] ? 'Solution enregistrée et affichée sur le site.' : 'Solution enregistrée (masquée sur le site).');
    }

    public static function toggle(string $id): never
    {
        $so = Solutions::find($id) ?? Admin::back('/admin/solutions', 'err', 'Solution introuvable.');
        Solutions::setEnabled($id, !$so['enabled']);
        Admin::back('/admin/solutions', 'ok', Solutions::name($so) . ($so['enabled'] ? ' est masquée sur le site.' : ' est affichée sur le site.'));
    }

    public static function move(string $id): never
    {
        Solutions::move($id, ($_POST['dir'] ?? '') === 'up' ? -1 : 1);
        Admin::back('/admin/solutions', 'ok', 'Ordre mis à jour.');
    }

    public static function delete(string $id): never
    {
        $so = Solutions::find($id);
        if (!$so || $so['kind'] !== 'standard') {
            Admin::back('/admin/solutions', 'err', 'Cette solution ne peut pas être supprimée (masque-la plutôt).');
        }
        Solutions::delete($id);
        Admin::back('/admin/solutions', 'ok', 'Solution « ' . $so['name'] . ' » supprimée.');
    }
}
