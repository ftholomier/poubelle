<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Content;
use App\Core\Csrf;
use App\Core\Mediatheque;
use App\Core\Session;
use App\Core\View;
use RuntimeException;

/**
 * Photos des hébergements et des étangs.
 *
 * Les gabarits publics lisent les images à des emplacements précis : cet
 * écran expose donc chaque emplacement avec son rôle réel, plutôt qu'un
 * simple tas d'images.
 */
final class PhotoController
{
    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly Mediatheque $mediatheque,
    ) {
    }

    private function rediriger(string $chemin): string
    {
        header('Location: ' . url($chemin), true, 303);
        return '';
    }

    // ---------------------------------------------------------------- écrans

    public function hebergement(string $slug): string
    {
        $item = $this->content->find('hebergements', $slug);
        if ($item === null) {
            return $this->rediriger('/admin/hebergements');
        }

        return $this->view->render('admin/photos', [
            'page'        => ['titre' => 'Photos — ' . $item['nom']],
            'type'        => 'hebergements',
            'item'        => $item,
            'zones'       => $this->zonesHebergement($item),
            'mediatheque' => $this->mediatheque->lister(),
            'media'       => $this->mediatheque,
            'retour'      => '/admin/hebergements/' . rawurlencode($slug),
            'apercu'      => '/hebergements/' . rawurlencode($slug),
        ], 'admin/layout');
    }

    public function etang(string $slug): string
    {
        $item = $this->content->find('peche', $slug);
        if ($item === null) {
            return $this->rediriger('/admin/peche');
        }

        return $this->view->render('admin/photos', [
            'page'        => ['titre' => 'Photos — ' . $item['nom']],
            'type'        => 'peche',
            'item'        => $item,
            'zones'       => $this->zonesEtang($item),
            'mediatheque' => $this->mediatheque->lister(),
            'media'       => $this->mediatheque,
            'retour'      => '/admin/peche/' . rawurlencode($slug),
            'apercu'      => '/peche/' . rawurlencode($slug),
        ], 'admin/layout');
    }

    // --------------------------------------------------------------- actions

    public function envoi(string $type, string $slug): string
    {
        $fichier = $type === 'hebergements' ? 'hebergements' : 'peche';
        $cible = '/admin/' . $type . '/' . rawurlencode($slug) . '/photos';

        if (!Csrf::verifier()) {
            return $this->rediriger($cible);
        }

        $donnees = $this->content->load($fichier);
        $index = null;
        foreach ($donnees['items'] as $i => $item) {
            if (($item['slug'] ?? '') === $slug) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return $this->rediriger('/admin/' . $type);
        }
        $item = $donnees['items'][$index];

        $action = (string) ($_POST['action'] ?? '');
        $zone   = (string) ($_POST['zone'] ?? '');

        // envoi d'une nouvelle image : elle rejoint la médiathèque, et la
        // zone choisie si une zone est indiquée
        if ($action === 'televerser') {
            try {
                $chemin = $this->mediatheque->televerser($_FILES['image'] ?? []);
            } catch (RuntimeException $e) {
                Session::flash('erreur', $e->getMessage());
                return $this->rediriger($cible);
            }
            if ($zone === '') {
                Session::flash('succes', 'Photo ajoutée à la médiathèque.');
                return $this->rediriger($cible);
            }
            $action = 'ajouter';
            $_POST['ajout'] = [$chemin];
        }

        $liste = $this->lireZone($item, $zone);
        if ($liste === null) {
            return $this->rediriger($cible);
        }

        switch ($action) {
            case 'ajouter':
                $ajouts = array_values(array_filter(
                    (array) ($_POST['ajout'] ?? []),
                    fn($c) => is_string($c) && $this->mediatheque->existe($c)
                ));
                if ($ajouts === []) {
                    Session::flash('erreur', 'Aucune photo sélectionnée.');
                    return $this->rediriger($cible);
                }

                $max = $this->maxZone($zone);
                if ($max === 1) {
                    // emplacement unique : la nouvelle photo remplace l'ancienne
                    $liste = [end($ajouts)];
                    $message = 'Photo remplacée.';
                    break;
                }

                $liste = array_values(array_unique([...$liste, ...$ajouts]));
                $ignorees = 0;
                if ($max !== null && count($liste) > $max) {
                    $ignorees = count($liste) - $max;
                    $liste = array_slice($liste, 0, $max);
                }
                $message = (count($ajouts) > 1 ? count($ajouts) . ' photos ajoutées.' : 'Photo ajoutée.')
                    . ($ignorees > 0 ? " Emplacement complet : {$ignorees} photo(s) non retenue(s)." : '');
                break;

            case 'retirer':
                $i = (int) ($_POST['index'] ?? -1);
                if (!isset($liste[$i])) {
                    return $this->rediriger($cible);
                }
                unset($liste[$i]);
                $liste = array_values($liste);
                $message = 'Photo retirée.';
                break;

            case 'monter':
            case 'descendre':
                $i = (int) ($_POST['index'] ?? -1);
                $j = $action === 'monter' ? $i - 1 : $i + 1;
                if (!isset($liste[$i], $liste[$j])) {
                    return $this->rediriger($cible);
                }
                [$liste[$i], $liste[$j]] = [$liste[$j], $liste[$i]];
                $message = 'Ordre mis à jour.';
                break;

            default:
                return $this->rediriger($cible);
        }

        $donnees['items'][$index] = $this->ecrireZone($item, $zone, $liste);
        $this->content->save($fichier, $donnees);
        Session::flash('succes', $message);

        return $this->rediriger($cible);
    }

    // ----------------------------------------------------------------- zones

    /**
     * Emplacements d'un hébergement, avec le rôle réel de chaque position
     * tel que les gabarits publics les consomment.
     *
     * @param array<mixed> $item
     * @return array<int, array{cle:string, titre:string, aide:string, max:int|null, roles:string[], images:string[]}>
     */
    private function zonesHebergement(array $item): array
    {
        $zones = [[
            'cle'    => 'principales',
            'titre'  => 'Photos principales',
            'aide'   => 'Ces trois photos alimentent la fiche et la liste des hébergements.',
            'max'    => null,
            'roles'  => [
                'Grande photo de la fiche + vignette dans la liste',
                'Photo de la première section',
                'Petite photo superposée dans la liste',
            ],
            'images' => $item['images_avant'] ?? [],
        ]];

        foreach ($item['sections'] ?? [] as $i => $section) {
            $zones[] = [
                'cle'    => 'section-' . $i,
                'titre'  => 'Section « ' . $section['titre'] . ' »',
                'aide'   => 'La 1re photo s\'affiche en grand, la 2e en médaillon.',
                'max'    => 2,
                'roles'  => ['Grande photo', 'Médaillon'],
                'images' => $section['images'] ?? [],
            ];
        }

        $zones[] = [
            'cle'    => 'secours',
            'titre'  => 'Photo de secours',
            'aide'   => 'Utilisée si les photos principales viennent à manquer.',
            'max'    => 1,
            'roles'  => ['Photo de repli'],
            'images' => $item['image'] !== '' ? [$item['image']] : [],
        ];

        return $zones;
    }

    /**
     * @param array<mixed> $item
     * @return array<int, array{cle:string, titre:string, aide:string, max:int|null, roles:string[], images:string[]}>
     */
    private function zonesEtang(array $item): array
    {
        return [
            [
                'cle'    => 'principale',
                'titre'  => 'Photo principale',
                'aide'   => 'En bandeau sur la fiche et en vignette dans la liste des étangs.',
                'max'    => 1,
                'roles'  => ['Bandeau + liste'],
                'images' => $item['image'] !== '' ? [$item['image']] : [],
            ],
            [
                'cle'    => 'secondaire',
                'titre'  => 'Photo secondaire',
                'aide'   => 'Affichée dans le corps de la fiche.',
                'max'    => 1,
                'roles'  => ['Corps de la fiche'],
                'images' => $item['image_secondaire'] !== '' ? [$item['image_secondaire']] : [],
            ],
        ];
    }

    /**
     * Nombre de photos qu'un emplacement peut retenir (null = illimité).
     */
    private function maxZone(string $zone): ?int
    {
        return match (true) {
            in_array($zone, ['secours', 'principale', 'secondaire'], true) => 1,
            str_starts_with($zone, 'section-') => 2,
            default => null,
        };
    }

    /**
     * @param array<mixed> $item
     * @return string[]|null null si la zone n'existe pas
     */
    private function lireZone(array $item, string $zone): ?array
    {
        if ($zone === 'principales') {
            return $item['images_avant'] ?? [];
        }
        if ($zone === 'secours' || $zone === 'principale') {
            return ($item['image'] ?? '') !== '' ? [$item['image']] : [];
        }
        if ($zone === 'secondaire') {
            return ($item['image_secondaire'] ?? '') !== '' ? [$item['image_secondaire']] : [];
        }
        if (preg_match('/^section-(\d+)$/', $zone, $m)) {
            $i = (int) $m[1];
            return isset($item['sections'][$i]) ? ($item['sections'][$i]['images'] ?? []) : null;
        }
        return null;
    }

    /**
     * @param array<mixed> $item
     * @param string[] $liste
     * @return array<mixed>
     */
    private function ecrireZone(array $item, string $zone, array $liste): array
    {
        if ($zone === 'principales') {
            $item['images_avant'] = $liste;
        } elseif ($zone === 'secours' || $zone === 'principale') {
            $item['image'] = $liste[0] ?? '';
        } elseif ($zone === 'secondaire') {
            $item['image_secondaire'] = $liste[0] ?? '';
        } elseif (preg_match('/^section-(\d+)$/', $zone, $m)) {
            $item['sections'][(int) $m[1]]['images'] = $liste;
        }
        return $item;
    }
}
