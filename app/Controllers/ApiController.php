<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Content;
use RuntimeException;

/**
 * API JSON en lecture seule. Elle sert déjà le front pour les parties
 * dynamiques (galerie, filtres) et constituera la surface que le back-office
 * viendra compléter en écriture.
 */
final class ApiController
{
    public function __construct(private readonly Content $content)
    {
    }

    /**
     * Collection complète : /api/hebergements
     */
    public function collection(string $name, string $key = 'items'): string
    {
        try {
            $data = $this->content->load($name);
        } catch (RuntimeException) {
            return json_response(['erreur' => 'Ressource introuvable'], 404);
        }

        $items = $data[$key] ?? [];

        return json_response([
            'donnees' => $items,
            'total'   => is_countable($items) ? count($items) : 0,
        ]);
    }

    /**
     * Entrée unique par slug : /api/hebergements/la-carelie
     */
    public function item(string $name, string $slug, string $key = 'items'): string
    {
        try {
            $item = $this->content->find($name, $slug, $key);
        } catch (RuntimeException) {
            return json_response(['erreur' => 'Ressource introuvable'], 404);
        }

        return $item === null
            ? json_response(['erreur' => 'Élément introuvable'], 404)
            : json_response(['donnees' => $item]);
    }

    /**
     * Document brut : /api/tarifs
     */
    public function document(string $name): string
    {
        try {
            return json_response(['donnees' => $this->content->load($name)]);
        } catch (RuntimeException) {
            return json_response(['erreur' => 'Ressource introuvable'], 404);
        }
    }
}
