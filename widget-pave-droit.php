<?php
/**
 * Pavé "A LA UNE" — widget latéral droit toujours visible.
 *
 * Affiche :
 *   - les derniers ARTICLES d'une catégorie (via son slug)
 *   - et/ou des PAGES précises (via leurs slugs)  <-- NOUVEAU
 *
 * Les deux sont facultatifs : on peut n'afficher que des articles,
 * que des pages, ou un mélange des deux.
 *
 * À inclure dans le thème (footer.php), un plugin type "Code Snippets",
 * ou un shortcode.
 */

// ==========================================
// VARIABLES DE CONFIGURATION
// ==========================================

// Configuration de la catégorie (articles)
$category_slug = 'pave-droit'; // Slug de la catégorie à afficher (laissez vide '' pour n'afficher QUE des pages)
$widget_title = 'A LA UNE'; // Titre affiché en haut du widget

// Configuration des pages à afficher via leur slug (NOUVEAU)
$page_slugs = array(); // Ex: array('a-propos', 'contact', 'nos-services'). Laissez array() pour aucune page.
$pages_first = true;   // true = les pages s'affichent AVANT les articles, false = APRÈS

// Configuration de l'affichage
$max_articles = 3; // Nombre maximum d'ARTICLES à afficher (les pages listées sont toujours toutes affichées)
$show_excerpt = true; // Afficher ou non un extrait (true/false)
$excerpt_length = 100; // Longueur de l'extrait en caractères
$title_max_length = 0; // Longueur maximum des titres en caractères (0 = pas de limite)
$show_date = true; // Afficher la date sous chaque élément (mettez false si vous affichez surtout des pages)

// Configuration du design
$background_color = '#000000'; // Couleur de fond (hex)
$background_opacity = 0.6; // Opacité du fond (0 à 1)
$border_radius = '12px'; // Rayon des coins (ex: 12px, 0px)
$border_color = '#2196F3'; // Couleur de la bordure (laissez vide '' pour pas de bordure)
$border_width = '1px'; // Épaisseur de la bordure
$box_shadow = '0 4px 12px rgba(0,0,0,0.15)'; // Ombre du pavé (laissez vide '' pour pas d'ombre)

// Configuration du positionnement
$widget_width = '200px'; // Largeur du widget
$top_position = '50%'; // Position depuis le haut (50% pour centrage vertical)
$right_position = '10px'; // Position depuis la droite
$z_index = 1000; // Z-index pour superposition

// Configuration des couleurs du contenu
$title_color = '#ffffff'; // Couleur des titres
$title_hover_color = '#2196F3'; // Couleur des titres au survol
$text_size = '18px'; // Taille du texte
$title_size = '20px'; // Taille du titre

// Configuration des couleurs spécifiques
$date_color = '#2196F3'; // Couleur de la date
$icon_color = '#2196F3'; // Couleur de l'icône du titre

// Configuration des images
$image_width = '60px'; // Largeur des images de mise en avant
$image_height = '60px'; // Hauteur des images de mise en avant
$image_border_radius = '5px'; // Rayon des coins des images

// ==========================================
// CODE PRINCIPAL
// ==========================================

// ---- Récupération des PAGES par leur slug (NOUVEAU) ----
$pages = array();
foreach ($page_slugs as $slug) {
    $slug = trim($slug);
    if ($slug === '') {
        continue;
    }
    // get_page_by_path() récupère une page à partir de son slug (ou de son chemin "parent/enfant").
    $page = get_page_by_path($slug);
    if ($page && $page->post_status === 'publish') {
        $pages[] = $page;
        echo "<!-- Debug: page '$slug' trouvée (ID: " . $page->ID . ") -->";
    } else {
        echo "<!-- Debug: page '$slug' introuvable ou non publiée -->";
    }
}

// ---- Récupération des ARTICLES de la catégorie ----
$articles = array();
if (!empty($category_slug)) {
    $args = array(
        'category_name' => $category_slug,
        'posts_per_page' => $max_articles,
        'post_status' => 'publish',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false
    );
    $articles = get_posts($args);

    echo "<!-- Debug: " . count($articles) . " articles trouvés dans la catégorie '$category_slug' -->";

    // Vérifier si la catégorie existe
    $category = get_category_by_slug($category_slug);
    if (!$category) {
        echo "<!-- Debug: ERREUR - La catégorie '$category_slug' n'existe pas ! -->";
        // Aide : lister les slugs des catégories disponibles
        $category_list = array();
        foreach (get_categories() as $cat) {
            $category_list[] = $cat->slug . " (ID: " . $cat->term_id . ")";
        }
        echo "<!-- Debug: Catégories disponibles: " . implode(', ', $category_list) . " -->";
    } else {
        echo "<!-- Debug: Catégorie trouvée - ID: " . $category->term_id . ", Nom: " . $category->name . " -->";
        // Requête alternative si la première ne renvoie rien
        if (empty($articles)) {
            $articles = get_posts(array(
                'cat' => $category->term_id,
                'posts_per_page' => $max_articles,
                'post_status' => 'publish'
            ));
            echo "<!-- Debug: Test avec cat ID - " . count($articles) . " articles trouvés -->";
        }
    }
}

// ---- Fusion pages + articles dans une seule liste à afficher ----
$items = $pages_first ? array_merge($pages, $articles) : array_merge($articles, $pages);

echo "<!-- Debug: " . count($items) . " élément(s) à afficher (" . count($pages) . " page(s) + " . count($articles) . " article(s)) -->";

if (!empty($items)) :
    // Conversion couleur hex vers rgba (préfixée + protégée contre la redéfinition si le code est inclus 2 fois)
    if (!function_exists('pave_hex_to_rgba')) {
        function pave_hex_to_rgba($hex, $opacity) {
            $hex = str_replace('#', '', $hex);
            if (strlen($hex) == 3) {
                $hex = str_repeat(substr($hex,0,1), 2).str_repeat(substr($hex,1,1), 2).str_repeat(substr($hex,2,1), 2);
            }
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            return "rgba($r, $g, $b, $opacity)";
        }
    }

    $bg_rgba = pave_hex_to_rgba($background_color, $background_opacity);
?>

<div id="custom-articles-widget" style="
    position: fixed;
    top: <?php echo $top_position; ?>;
    transform: translateY(-50%);
    right: <?php echo $right_position; ?>;
    width: <?php echo $widget_width; ?>;
    background: <?php echo $bg_rgba; ?>;
    backdrop-filter: blur(10px);
    border-radius: <?php echo $border_radius; ?>;
    <?php if (!empty($border_color)): ?>
    border: <?php echo $border_width; ?> solid <?php echo $border_color; ?>;
    <?php endif; ?>
    <?php if (!empty($box_shadow)): ?>
    box-shadow: <?php echo $box_shadow; ?>;
    <?php endif; ?>
    padding: 20px;
    z-index: <?php echo $z_index; ?>;
    max-height: 80vh;
    overflow-y: auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
">

    <div style="margin-bottom: 15px;">
        <h3 style="
            margin: 0 0 15px 0;
            font-size: <?php echo $title_size; ?>;
            font-weight: 600;
            color: <?php echo $title_color; ?>;
            border-bottom: 2px solid <?php echo $title_hover_color; ?>;
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        ">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="flex-shrink: 0; animation: rotate-info 4s linear infinite;">
                <circle cx="12" cy="12" r="10" stroke="<?php echo $icon_color; ?>" stroke-width="2" fill="none"/>
                <path d="M12 16V12M12 8H12.01" stroke="<?php echo $icon_color; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="2" fill="<?php echo $icon_color; ?>" opacity="0.3"/>
            </svg>
            <?php echo $widget_title; ?>
        </h3>
    </div>

    <div class="articles-list">
        <?php foreach ($items as $article):
            setup_postdata($article);
            $permalink = get_permalink($article->ID);
            $original_title = get_the_title($article->ID);

            // Tronquer le titre si nécessaire (mb_* pour gérer correctement les accents)
            if ($title_max_length > 0 && mb_strlen($original_title) > $title_max_length) {
                $title = mb_substr($original_title, 0, $title_max_length) . '...';
            } else {
                $title = $original_title;
            }

            $thumbnail = get_the_post_thumbnail($article->ID, 'thumbnail');
            $excerpt = '';

            if ($show_excerpt) {
                $excerpt = get_the_excerpt($article->ID);
                if (mb_strlen($excerpt) > $excerpt_length) {
                    $excerpt = mb_substr($excerpt, 0, $excerpt_length) . '...';
                }
            }
        ?>

        <div class="article-item" style="
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        ">

            <div class="article-thumbnail" style="
                flex-shrink: 0;
                margin-right: 12px;
                width: <?php echo $image_width; ?>;
                height: <?php echo $image_height; ?>;
                overflow: hidden;
                border-radius: <?php echo $image_border_radius; ?>;
                background: rgba(255,255,255,0.1);
            ">
                <?php if ($thumbnail): ?>
                    <a href="<?php echo $permalink; ?>" style="display: block; width: 100%; height: 100%;">
                        <div style="
                            width: 100%;
                            height: 100%;
                            background-image: url('<?php echo get_the_post_thumbnail_url($article->ID, 'medium'); ?>');
                            background-size: cover;
                            background-position: center;
                        "></div>
                    </a>
                <?php else: ?>
                    <div style="
                        width: 100%;
                        height: 100%;
                        background: linear-gradient(45deg, <?php echo $title_hover_color; ?>22, <?php echo $title_hover_color; ?>44);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">
                        <span style="color: <?php echo $title_hover_color; ?>; font-size: 12px;">📄</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="article-content" style="flex: 1; min-width: 0;">
                <h4 style="
                    margin: 0 0 5px 0;
                    font-size: <?php echo $text_size; ?>;
                    font-weight: 500;
                    line-height: 1.3;
                ">
                    <a href="<?php echo $permalink; ?>" style="
                        color: <?php echo $title_color; ?>;
                        text-decoration: none;
                        display: inline;
                        text-transform: uppercase;
                    ">
                        <?php echo $title; ?>
                    </a>
                </h4>

                <?php if ($show_excerpt && !empty($excerpt)): ?>
                <p style="
                    margin: 0;
                    font-size: 12px;
                    color: <?php echo $title_color; ?>88;
                    line-height: 1.4;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                ">
                    <?php echo $excerpt; ?>
                </p>
                <?php endif; ?>

                <?php if ($show_date): ?>
                <div style="
                    margin-top: 5px;
                    font-size: 11px;
                    color: <?php echo $date_color; ?>;
                ">
                    <?php echo get_the_date('d/m/Y', $article->ID); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php endforeach; ?>
    </div>
</div>

<style>
/* Animations CSS pures pour éviter les conflits JavaScript */
#custom-articles-widget .article-item {
    transition: all 0.3s ease;
}

#custom-articles-widget .article-item:hover {
    transform: translateX(-5px);
    background-color: rgba(255,255,255,0.05);
}

#custom-articles-widget .article-thumbnail a > div {
    transition: transform 0.3s ease;
}

#custom-articles-widget .article-thumbnail:hover a > div {
    transform: scale(1.1);
}

#custom-articles-widget .article-content h4 a {
    transition: color 0.3s ease;
}

#custom-articles-widget .article-content h4 a:hover {
    color: <?php echo $title_hover_color; ?> !important;
}

#custom-articles-widget a[href*="category"] {
    transition: all 0.3s ease;
}

#custom-articles-widget a[href*="category"]:hover {
    background: <?php echo $title_hover_color; ?>dd !important;
    transform: translateY(-1px);
}

/* Animation de rotation pour l'icône */
@keyframes rotate-info {
    0% {
        transform: rotateY(0deg);
    }
    50% {
        transform: rotateY(180deg);
    }
    50%, 100% {
        transform: rotateY(360deg);
    }
    66%, 100% {
        transform: rotateY(360deg);
    }
}

/* Style responsive pour mobile */
@media (max-width: 768px) {
    #custom-articles-widget {
        position: relative !important;
        top: auto !important;
        right: auto !important;
        width: 100% !important;
        margin: 20px 0;
        max-height: none !important;
    }
}

/* Scrollbar personnalisée */
#custom-articles-widget::-webkit-scrollbar {
    width: 4px;
}

#custom-articles-widget::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.1);
    border-radius: 2px;
}

#custom-articles-widget::-webkit-scrollbar-thumb {
    background: <?php echo $title_hover_color; ?>66;
    border-radius: 2px;
}

#custom-articles-widget::-webkit-scrollbar-thumb:hover {
    background: <?php echo $title_hover_color; ?>;
}
</style>


<?php
    wp_reset_postdata();
else:
    // Debug : message si rien à afficher
    echo "<!-- Debug: Aucun élément à afficher. Vérifiez le slug de catégorie ('$category_slug'), les slugs de pages, et que le contenu est bien publié. -->";
endif;
