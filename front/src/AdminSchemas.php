<?php

declare(strict_types=1);

/** Schémas des formulaires du back-office (voir Form). */
final class AdminSchemas
{
    private static function seo(): array
    {
        return [
            ['type' => 'heading', 'label' => 'Référencement (Google)'],
            ['key' => 'seo.title', 'label' => 'Titre de la page (balise title)', 'max' => 120, 'wide' => true],
            ['key' => 'seo.description', 'type' => 'textarea', 'label' => 'Description (meta description)', 'rows' => 2, 'max' => 320, 'wide' => true],
        ];
    }

    public static function home(): array
    {
        return array_merge([
            ['type' => 'heading', 'label' => 'Héros (vidéo plein écran)'],
            ['key' => 'hero.badge', 'label' => 'Pastille', 'help' => 'Affichée au-dessus du titre (écrans assez hauts).'],
            ['key' => 'hero.title', 'label' => 'Titre (H1)', 'required' => true],
            ['key' => 'hero.highlight', 'label' => 'Mot en jaune dans le titre', 'half' => true],
            ['key' => 'hero.text', 'type' => 'textarea', 'label' => 'Paragraphe', 'rows' => 3, 'wide' => true],
            ['key' => 'hero.checks', 'type' => 'list', 'label' => 'Ligne des ✓', 'rows' => 3, 'help' => 'Une ligne par élément.'],
            ['key' => 'hero.video', 'label' => 'Vidéo (MP4)', 'help' => 'Chemin d\'un fichier du site (/media/…, /uploads/…) ou adresse https. Muette, en boucle.'],
            ['key' => 'hero.poster', 'type' => 'image', 'label' => 'Image affichée avant la vidéo'],
            ['type' => 'heading', 'label' => 'Compteurs'],
            ['key' => 'stats', 'type' => 'repeater', 'label' => 'Chiffres clés', 'add' => 'Ajouter un chiffre', 'wide' => true, 'fields' => [
                ['key' => 'prefix', 'label' => 'Avant', 'max' => 4],
                ['key' => 'value', 'type' => 'number', 'label' => 'Valeur', 'min' => 0, 'max' => 100000],
                ['key' => 'suffix', 'label' => 'Après', 'max' => 10],
                ['key' => 'label', 'label' => 'Libellé', 'max' => 120],
            ]],
            ['type' => 'heading', 'label' => 'Section « Nos produits »'],
            ['key' => 'products.eyebrow', 'label' => 'Sur-titre', 'half' => true],
            ['key' => 'products.link', 'label' => 'Lien vers la page produits', 'half' => true],
            ['key' => 'products.title', 'label' => 'Titre', 'wide' => true],
            ['type' => 'heading', 'label' => 'Notre philosophie'],
            ['key' => 'philosophy.title', 'label' => 'Titre de la section'],
            ['key' => 'philosophy.items', 'type' => 'repeater', 'label' => 'Engagements', 'add' => 'Ajouter', 'wide' => true, 'fields' => [
                ['key' => 'title', 'label' => 'Titre', 'max' => 60],
                ['key' => 'text', 'type' => 'textarea', 'label' => 'Texte', 'rows' => 2],
            ]],
            ['type' => 'heading', 'label' => 'Bloc « Présentation » (fond gris + photo)'],
            ['key' => 'presentation.eyebrow', 'label' => 'Sur-titre', 'half' => true],
            ['key' => 'presentation.button', 'label' => 'Bouton', 'half' => true],
            ['key' => 'presentation.title', 'label' => 'Titre', 'wide' => true],
            ['key' => 'presentation.text', 'type' => 'textarea', 'label' => 'Texte', 'rows' => 4, 'wide' => true],
            ['key' => 'presentation.image', 'type' => 'image', 'label' => 'Photo'],
            ['type' => 'heading', 'label' => 'Avis et actualités'],
            ['key' => 'reviews.eyebrow', 'label' => 'Sur-titre des avis', 'half' => true],
            ['key' => 'reviews.title', 'label' => 'Titre des avis', 'half' => true],
            ['key' => 'news.eyebrow', 'label' => 'Sur-titre des actus', 'half' => true],
            ['key' => 'news.title', 'label' => 'Titre des actus', 'half' => true],
            ['type' => 'heading', 'label' => 'Bloc final (noir)', 'help' => 'Repris en bas des pages intérieures.'],
            ['key' => 'cta.title', 'label' => 'Titre', 'wide' => true],
            ['key' => 'cta.text', 'type' => 'textarea', 'label' => 'Texte', 'rows' => 2, 'wide' => true],
            ['key' => 'cta.button', 'label' => 'Bouton devis'],
        ], self::seo());
    }

    public static function page(string $key): array
    {
        $base = [
            ['key' => 'title', 'label' => 'Titre (H1 et menu)', 'required' => true, 'half' => true],
            ['key' => 'path', 'type' => 'path', 'label' => 'Adresse', 'help' => 'Adresse d\'origine de renovest.fr conservée pour le référencement.', 'half' => true],
            ['key' => 'eyebrow', 'label' => 'Sur-titre du bandeau', 'half' => true],
            ['key' => 'lead', 'type' => 'textarea', 'label' => 'Chapeau du bandeau', 'rows' => 2, 'wide' => true],
        ];
        $specific = match ($key) {
            'produits' => [
                ['key' => 'h1', 'label' => 'Titre affiché (H1)', 'wide' => true],
                ['key' => 'lead', 'type' => 'html', 'label' => 'Introduction', 'wide' => true],
                ['key' => 'offer_title', 'label' => 'Titre de la liste des produits', 'wide' => true],
                ['key' => 'why', 'type' => 'html', 'label' => 'Bloc « Pourquoi faire appel à nous »', 'wide' => true],
            ],
            'realisations' => [
                ['key' => 'content', 'type' => 'html', 'label' => 'Texte au-dessus de la galerie (facultatif)', 'wide' => true],
                ['key' => 'button', 'label' => 'Bouton sous la galerie'],
            ],
            'actu' => [
                ['key' => 'facebook_text', 'label' => 'Lien Facebook', 'half' => true],
                ['key' => 'facebook_title', 'label' => 'Sous-texte du lien Facebook', 'half' => true],
            ],
            'contact' => [
                ['key' => 'form_title', 'label' => 'Titre du formulaire'],
            ],
            'devis' => [
                ['key' => 'h1', 'label' => 'Titre affiché (H1)', 'wide' => true],
                ['key' => 'includes_title', 'label' => 'Titre du bloc noir'],
                ['key' => 'includes', 'type' => 'list', 'label' => 'Points du bloc noir', 'rows' => 4],
                ['key' => 'talk_title', 'label' => 'Titre du bloc téléphone'],
                ['key' => 'confirm_title', 'label' => 'Confirmation : titre'],
                ['key' => 'confirm_text', 'type' => 'textarea', 'label' => 'Confirmation : texte', 'rows' => 2, 'wide' => true],
            ],
            'avis' => [],
            default => [
                ['key' => 'content', 'type' => 'html', 'label' => 'Contenu', 'wide' => true],
                ['key' => 'image', 'type' => 'image', 'label' => 'Image sous le texte (facultative)'],
            ],
        };
        if ($key === 'produits') {
            $base = array_values(array_filter($base, fn($f) => ($f['key'] ?? '') !== 'lead'));
        }
        return array_merge($base, $specific, self::seo());
    }

    public static function product(): array
    {
        $cats = [];
        foreach (Store::get('gallery')['filters'] ?? [] as $f) {
            $cats[$f['id']] = $f['label'];
        }
        $choices = Content::devisChoices();
        return array_merge([
            ['key' => 'title', 'label' => 'Nom du produit', 'required' => true, 'half' => true],
            ['key' => 'path', 'type' => 'path', 'label' => 'Adresse de la page', 'help' => 'Laisser vide pour la créer à partir du nom.', 'half' => true],
            ['key' => 'visible', 'type' => 'checkbox', 'label' => 'Afficher sur le site'],
            ['key' => 'h1', 'label' => 'Titre de la page (H1)', 'wide' => true],
            ['key' => 'offer_title', 'label' => 'Titre dans la liste des produits', 'half' => true],
            ['key' => 'summary', 'type' => 'textarea', 'label' => 'Résumé (cartes, liste, chapeau)', 'rows' => 3, 'wide' => true],
            ['key' => 'bullets', 'type' => 'list', 'label' => 'Points forts (facultatif, page « Nos produits »)', 'rows' => 3],
            ['key' => 'image', 'type' => 'image', 'label' => 'Photo principale'],
            ['key' => 'icon', 'type' => 'image', 'label' => 'Pictogramme'],
            ['key' => 'gallery_cat', 'type' => 'select', 'label' => 'Gamme dans la galerie photos', 'options' => $cats ?: ['' => '—']],
            ['key' => 'devis_choice', 'type' => 'select', 'label' => 'Choix présélectionné dans le devis', 'options' => array_combine($choices, $choices) ?: ['' => '—']],
            ['key' => 'content', 'type' => 'html', 'label' => 'Contenu de la page', 'wide' => true],
        ], self::seo());
    }

    public static function gallery(): array
    {
        $cats = [];
        foreach (Store::get('gallery')['filters'] ?? [] as $f) {
            $cats[$f['id']] = $f['label'];
        }
        return [
            ['key' => 'filters', 'type' => 'repeater', 'label' => 'Gammes (filtres)', 'add' => 'Ajouter une gamme', 'wide' => true, 'fields' => [
                ['key' => 'id', 'label' => 'Identifiant', 'max' => 40],
                ['key' => 'label', 'label' => 'Libellé', 'max' => 60],
            ]],
            ['key' => 'items', 'type' => 'repeater', 'label' => 'Photos', 'add' => 'Ajouter une photo', 'wide' => true, 'fields' => [
                ['key' => 'src', 'type' => 'image', 'label' => 'Photo'],
                ['key' => 'cat', 'type' => 'select', 'label' => 'Gamme', 'options' => $cats],
            ]],
        ];
    }

    public static function realisations(): array
    {
        return [
            ['key' => 'items', 'type' => 'repeater', 'label' => 'Photos de chantiers', 'add' => 'Ajouter une photo', 'wide' => true, 'fields' => [
                ['key' => 'src', 'type' => 'image', 'label' => 'Photo'],
                ['key' => 'title', 'label' => 'Titre (facultatif)', 'max' => 120],
                ['key' => 'place', 'label' => 'Lieu (facultatif)', 'max' => 80],
            ]],
        ];
    }

    public static function post(): array
    {
        $cats = Content::categories();
        return array_merge([
            ['key' => 'title', 'label' => 'Titre', 'required' => true, 'wide' => true],
            ['key' => 'date', 'label' => 'Date de publication (AAAA-MM-JJ)', 'half' => true, 'placeholder' => date('Y-m-d')],
            ['key' => 'cat', 'type' => 'select', 'label' => 'Catégorie', 'options' => array_combine($cats, $cats), 'half' => true],
            ['key' => 'visible', 'type' => 'checkbox', 'label' => 'Publiée sur le site'],
            ['key' => 'path', 'type' => 'path', 'label' => 'Adresse', 'help' => 'Laisser vide pour la créer à partir du titre.', 'wide' => true],
            ['key' => 'images', 'type' => 'images', 'label' => 'Images (la première sert de vignette)', 'wide' => true],
            ['key' => 'lead', 'type' => 'textarea', 'label' => 'Chapeau (facultatif)', 'rows' => 2, 'wide' => true],
            ['key' => 'content', 'type' => 'html', 'label' => 'Texte', 'wide' => true],
            ['key' => 'excerpt', 'type' => 'textarea', 'label' => 'Extrait des cartes (facultatif, sinon début du texte)', 'rows' => 2, 'wide' => true],
            ['key' => 'link', 'type' => 'url', 'label' => 'Lien de la publication Facebook', 'wide' => true],
        ], self::seo());
    }

    public static function reviews(): array
    {
        return [
            ['type' => 'heading', 'label' => 'Note affichée', 'help' => 'Laisser vide pour calculer la moyenne et le nombre à partir des avis ci-dessous.'],
            ['key' => 'rating_value', 'label' => 'Note (ex. 4,9)', 'half' => true],
            ['key' => 'rating_count', 'label' => 'Nombre d\'avis', 'half' => true],
            ['key' => 'rating_source', 'label' => 'Libellé (ex. avis Google)', 'half' => true],
            ['key' => 'reviews_url', 'type' => 'url', 'label' => 'Lien vers tous les avis (fiche Google)', 'half' => true],
            ['key' => 'items', 'type' => 'repeater', 'label' => 'Avis', 'add' => 'Ajouter un avis', 'wide' => true, 'fields' => [
                ['key' => 'name', 'label' => 'Nom', 'max' => 80],
                ['key' => 'rating', 'type' => 'select', 'label' => 'Note', 'options' => ['5' => '★★★★★', '4' => '★★★★', '3' => '★★★', '2' => '★★', '1' => '★']],
                ['key' => 'where', 'label' => 'Sous le nom (ex. Audincourt · Fenêtres)', 'max' => 80],
                ['key' => 'source', 'label' => 'Source', 'max' => 30],
                ['key' => 'text', 'type' => 'textarea', 'label' => 'Avis', 'rows' => 3],
                ['key' => 'visible', 'type' => 'checkbox', 'label' => 'Affiché'],
            ]],
        ];
    }

    public static function menu(): array
    {
        $pages = [];
        foreach (Content::pages() as $k => $p) {
            $pages[$k] = $p['title'] . ' (' . $p['path'] . ')';
        }
        return [
            ['key' => 'items', 'type' => 'repeater', 'label' => 'Entrées du menu', 'add' => 'Ajouter une entrée', 'wide' => true, 'fields' => [
                ['key' => 'key', 'type' => 'select', 'label' => 'Page', 'options' => $pages],
                ['key' => 'label', 'label' => 'Libellé', 'max' => 40],
                ['key' => 'visible', 'type' => 'checkbox', 'label' => 'Visible'],
            ]],
        ];
    }

    public static function site(): array
    {
        return [
            ['type' => 'heading', 'label' => 'Entreprise'],
            ['key' => 'name', 'label' => 'Nom', 'required' => true, 'half' => true],
            ['key' => 'legal_name', 'label' => 'Raison sociale', 'half' => true],
            ['key' => 'tagline', 'label' => 'Phrase du pied de page', 'wide' => true],
            ['key' => 'siret', 'label' => 'SIRET', 'half' => true],
            ['key' => 'publisher', 'label' => 'Directeur de la publication', 'half' => true],
            ['type' => 'heading', 'label' => 'Contact'],
            ['key' => 'phone', 'type' => 'tel', 'label' => 'Téléphone', 'required' => true, 'half' => true],
            ['key' => 'sav_phone', 'type' => 'tel', 'label' => 'Service technique / SAV', 'half' => true],
            ['key' => 'fax', 'label' => 'Fax', 'half' => true],
            ['key' => 'email', 'type' => 'email', 'label' => 'E-mail affiché', 'half' => true],
            ['key' => 'hours', 'label' => 'Horaires', 'half' => true],
            ['type' => 'heading', 'label' => 'Agences'],
            ['key' => 'agencies', 'type' => 'repeater', 'label' => 'Agences', 'add' => 'Ajouter une agence', 'wide' => true, 'fields' => [
                ['key' => 'name', 'label' => 'Nom (ex. Agence de Taillecourt)'],
                ['key' => 'city', 'label' => 'Ville (titre de la carte)'],
                ['key' => 'street', 'label' => 'Adresse'],
                ['key' => 'zip', 'label' => 'Code postal', 'max' => 10],
                ['key' => 'town', 'label' => 'Commune'],
                ['key' => 'country', 'label' => 'Pays (facultatif)'],
                ['key' => 'phone', 'label' => 'Téléphone (si différent)'],
                ['key' => 'hours', 'label' => 'Horaires (si différents)'],
            ]],
            ['key' => 'map_embed', 'type' => 'url', 'label' => 'Carte Google (adresse « intégrer une carte »)', 'wide' => true],
            ['key' => 'zones', 'type' => 'list', 'label' => 'Zone d\'intervention', 'rows' => 5, 'help' => 'Une commune par ligne.'],
            ['type' => 'heading', 'label' => 'Réseaux et crédits'],
            ['key' => 'facebook', 'type' => 'url', 'label' => 'Page Facebook', 'half' => true],
            ['key' => 'instagram', 'type' => 'url', 'label' => 'Instagram', 'half' => true],
            ['key' => 'credit.label', 'label' => 'Crédit « Propulsé par »', 'half' => true],
            ['key' => 'credit.url', 'type' => 'url', 'label' => 'Lien du crédit', 'half' => true],
            ['key' => 'news_categories', 'type' => 'list', 'label' => 'Catégories des actualités', 'rows' => 3],
        ];
    }

    public static function ui(): array
    {
        return [
            ['type' => 'heading', 'label' => 'Bandeau supérieur et header'],
            ['key' => 'topbar.items', 'type' => 'list', 'label' => 'Bandeau : à gauche (après le téléphone)', 'rows' => 2],
            ['key' => 'topbar.right', 'type' => 'list', 'label' => 'Bandeau : à droite', 'rows' => 2],
            ['key' => 'topbar.mobile', 'label' => 'Bandeau : texte sur mobile', 'half' => true],
            ['key' => 'header_button', 'label' => 'Bouton du header', 'half' => true],
            ['key' => 'header_button_mobile', 'label' => 'Bouton du header (mobile)', 'half' => true],
            ['type' => 'heading', 'label' => 'Barre CTA fixe (bas de l\'écran)'],
            ['key' => 'cta_bar.enabled', 'type' => 'checkbox', 'label' => 'Afficher la barre CTA'],
            ['key' => 'cta_bar.contact', 'label' => 'Bouton contact', 'half' => true],
            ['key' => 'cta_bar.devis', 'label' => 'Bouton devis', 'half' => true],
            ['type' => 'heading', 'label' => 'Message vocal (bouton rouge de la barre CTA)', 'help' => 'Le visiteur enregistre un message au micro ; il arrive dans Demandes et par e-mail (transcription, résumé et fichier audio joint selon le réglage de la page E-mails). Avec une clé Gemini, l\'IA le résume.'],
            ['key' => 'voice.enabled', 'type' => 'checkbox', 'label' => 'Afficher le bouton « vocal »'],
            ['key' => 'voice.label', 'label' => 'Libellé du bouton', 'half' => true],
            ['key' => 'voice.max_seconds', 'type' => 'number', 'label' => 'Durée maximale (secondes)', 'min' => 15, 'max' => 300, 'half' => true],
            ['key' => 'voice.questions', 'type' => 'list', 'label' => 'Questions affichées pendant l\'enregistrement (une toutes les 15 s)', 'rows' => 5],
            ['key' => 'voice.title', 'label' => 'Titre du panneau', 'half' => true],
            ['key' => 'voice.finish', 'label' => 'Bouton pour arrêter', 'half' => true],
            ['key' => 'voice.send', 'label' => 'Bouton d\'envoi', 'half' => true],
            ['key' => 'voice.again', 'label' => 'Bouton pour recommencer', 'half' => true],
            ['key' => 'voice.summary_label', 'label' => 'Titre du résumé IA', 'half' => true],
            ['key' => 'voice.no_transcript', 'label' => 'Texte si pas de transcription', 'wide' => true],
            ['key' => 'voice.sent_title', 'label' => 'Confirmation : titre ({name} = prénom)', 'half' => true],
            ['key' => 'voice.sent_text', 'type' => 'textarea', 'label' => 'Confirmation : texte', 'rows' => 2, 'wide' => true],
            ['key' => 'voice.error_title', 'label' => 'Micro refusé : titre', 'half' => true],
            ['key' => 'voice.error_text', 'type' => 'textarea', 'label' => 'Micro refusé : texte', 'rows' => 2, 'wide' => true],
            ['key' => 'voice.summary_prompt', 'type' => 'textarea', 'label' => 'Consigne de résumé pour Gemini ({transcript} = message)', 'rows' => 3, 'wide' => true],
            ['type' => 'heading', 'label' => 'Assistant (bot en bas à droite)'],
            ['key' => 'bot.enabled', 'type' => 'checkbox', 'label' => 'Afficher l\'assistant'],
            ['key' => 'bot.name', 'label' => 'Nom', 'half' => true],
            ['key' => 'bot.status', 'label' => 'Statut', 'half' => true],
            ['key' => 'bot.welcome', 'type' => 'textarea', 'label' => 'Message d\'accueil', 'rows' => 2, 'wide' => true],
            ['key' => 'bot.placeholder', 'label' => 'Texte du champ', 'half' => true],
            ['key' => 'bot.devis', 'label' => 'Bouton devis', 'half' => true],
            ['key' => 'bot.quick', 'type' => 'repeater', 'label' => 'Réponses rapides', 'add' => 'Ajouter une réponse', 'wide' => true, 'fields' => [
                ['key' => 'q', 'label' => 'Question (bouton)', 'max' => 80],
                ['key' => 'a', 'type' => 'textarea', 'label' => 'Réponse', 'rows' => 2],
            ]],
            ['key' => 'bot.ask', 'label' => '« Poser une question » : message ({produit} = nom)', 'wide' => true],
            ['key' => 'bot.ask_answer', 'type' => 'textarea', 'label' => '« Poser une question » : réponse', 'rows' => 2, 'wide' => true],
            ['key' => 'bot.fallback', 'type' => 'textarea', 'label' => 'Réponse par défaut (sans IA)', 'rows' => 2, 'wide' => true],
            ['type' => 'heading', 'label' => 'Bulle d\'accroche (après inactivité, une fois par visite)'],
            ['key' => 'teaser.enabled', 'type' => 'checkbox', 'label' => 'Activer la bulle'],
            ['key' => 'teaser.delay', 'type' => 'number', 'label' => 'Délai d\'inactivité (secondes)', 'min' => 5, 'max' => 600, 'half' => true],
            ['key' => 'teaser.title', 'label' => 'Titre', 'half' => true],
            ['key' => 'teaser.text', 'type' => 'textarea', 'label' => 'Texte', 'rows' => 2, 'wide' => true],
            ['key' => 'teaser.call', 'label' => 'Bouton rappel', 'half' => true],
            ['key' => 'teaser.chat', 'label' => 'Bouton discuter', 'half' => true],
            ['type' => 'heading', 'label' => 'Popup de sortie'],
            ['key' => 'exit.enabled', 'type' => 'checkbox', 'label' => 'Activer la popup de sortie'],
            ['key' => 'exit.mode', 'type' => 'select', 'label' => 'Fréquence', 'options' => ['session' => 'Une fois par session (production)', 'always' => 'À chaque sortie (démonstration)'], 'half' => true],
            ['key' => 'exit.eyebrow', 'label' => 'Sur-titre', 'half' => true],
            ['key' => 'exit.title', 'label' => 'Titre', 'wide' => true],
            ['key' => 'exit.text', 'type' => 'textarea', 'label' => 'Texte', 'rows' => 2, 'wide' => true],
            ['key' => 'exit.placeholder', 'label' => 'Exemple dans le champ', 'half' => true],
            ['key' => 'exit.button', 'label' => 'Bouton', 'half' => true],
            ['key' => 'exit.dismiss', 'label' => 'Lien de refus', 'wide' => true],
            ['key' => 'exit.image', 'type' => 'image', 'label' => 'Photo'],
            ['type' => 'heading', 'label' => 'Formulaire de devis (3 étapes)'],
            ['key' => 'devis_form.steps', 'type' => 'list', 'label' => 'Étapes (3 lignes)', 'rows' => 3],
            ['key' => 'devis_form.step1_title', 'label' => 'Étape 1 : titre', 'half' => true],
            ['key' => 'devis_form.step1_text', 'label' => 'Étape 1 : consigne', 'half' => true],
            ['key' => 'devis_form.step2_title', 'label' => 'Étape 2 : titre', 'half' => true],
            ['key' => 'devis_form.step3_title', 'label' => 'Étape 3 : titre', 'half' => true],
            ['key' => 'devis_form.qty', 'type' => 'list', 'label' => 'Nombre d\'éléments (choix)', 'rows' => 4],
            ['key' => 'devis_form.delai', 'type' => 'list', 'label' => 'Échéance (choix)', 'rows' => 4],
            ['key' => 'devis_form.legal', 'label' => 'Mention sous le bouton', 'wide' => true],
            ['type' => 'heading', 'label' => 'Fabricants partenaires', 'help' => 'Bandeau de logos sous les compteurs de l\'accueil, masqué tant qu\'aucun logo n\'est ajouté.'],
            ['key' => 'partners.title', 'label' => 'Titre', 'half' => true],
            ['key' => 'partners.logos', 'type' => 'repeater', 'label' => 'Logos', 'add' => 'Ajouter un logo', 'wide' => true, 'fields' => [
                ['key' => 'image', 'type' => 'image', 'label' => 'Logo'],
                ['key' => 'name', 'label' => 'Nom'],
                ['key' => 'url', 'type' => 'url', 'label' => 'Lien (facultatif)'],
            ]],
            ['type' => 'heading', 'label' => 'Animations'],
            ['key' => 'cursor', 'type' => 'checkbox', 'label' => 'Curseur personnalisé (rond jaune, ordinateur uniquement)'],
        ];
    }

    public static function bot(): array
    {
        $models = Gemini::cachedModels();
        $groups = ['' => 'Automatique (modèle recommandé' . (($r = Gemini::recommended($models)) !== '' ? ' : ' . $r : '') . ')'];
        foreach ($models as $m) {
            $g = $m['stable'] ? 'Modèles stables' : 'Préversions et modèles expérimentaux';
            $groups[$g][$m['id']] = $m['name'] . ' — ' . $m['id'];
        }
        return [
            ['key' => 'gemini_key', 'type' => 'secret', 'label' => 'Clé API Gemini (Google)', 'is_set' => Settings::hasSecret('gemini_key'), 'wide' => true,
                'attrs' => ['data-gemini-key' => '1'],
                'help' => 'Créée gratuitement sur aistudio.google.com (Get API key). Dès qu\'elle est saisie, la liste des modèles disponibles se charge. Stockée hors du dossier public, jamais affichée.'],
            ['key' => 'bot_model', 'type' => 'select', 'label' => 'Modèle Gemini', 'options' => $groups, 'free' => true, 'wide' => true,
                'attrs' => ['data-gemini-models' => '1', 'data-has-key' => Settings::hasSecret('gemini_key') ? '1' : '0', 'data-count' => (string) count($models)],
                'help' => 'Liste lue en direct chez Google pour votre clé (modèles capables de discuter). « Tester l\'assistant » ci-dessous confirme que le modèle choisi répond.'],
            ['key' => 'bot_log', 'type' => 'checkbox', 'label' => 'Garder un journal des conversations (rubrique « Conversations du bot »)'],
            ['key' => 'bot_extra', 'type' => 'textarea', 'label' => 'Informations complémentaires pour l\'assistant', 'rows' => 6, 'wide' => true, 'help' => 'Informations validées que l\'assistant peut donner en plus du contenu du site (prix indicatifs, délais, marques…). Il n\'invente rien d\'autre.'],
        ];
    }

    public static function facebook(): array
    {
        $cats = Content::categories();
        return [
            ['key' => 'fb_page_id', 'label' => 'Identifiant de la page Facebook', 'half' => true, 'help' => 'Numéro de la page (Paramètres de la page › Informations).'],
            ['key' => 'fb_token', 'type' => 'secret', 'label' => 'Jeton d\'accès de la page (Page access token)', 'is_set' => Settings::hasSecret('fb_token'), 'wide' => true, 'help' => 'Jeton longue durée avec les autorisations pages_read_engagement et pages_manage_posts (pour publier).'],
            ['key' => 'fb_default_cat', 'type' => 'select', 'label' => 'Catégorie par défaut des posts importés', 'options' => array_combine($cats, $cats), 'half' => true],
            ['key' => 'fb_rules', 'type' => 'textarea', 'label' => 'Règles par hashtag', 'rows' => 5, 'half' => true, 'help' => 'Une règle par ligne : #hashtag=Catégorie'],
            ['key' => 'fb_auto_hours', 'type' => 'number', 'label' => 'Synchronisation automatique toutes les (heures, 0 = jamais)', 'min' => 0, 'max' => 168, 'half' => true],
            ['key' => 'fb_publish_default', 'type' => 'checkbox', 'label' => 'Cocher par défaut « Publier aussi sur Facebook » pour les nouvelles actus'],
        ];
    }

    public static function mail(): array
    {
        return [
            ['key' => 'notify_email', 'type' => 'email', 'label' => 'Recevoir les demandes à', 'half' => true],
            ['key' => 'notify_cc', 'label' => 'Copie à (facultatif, séparées par des virgules)', 'half' => true],
            ['type' => 'heading', 'label' => 'Messages vocaux', 'help' => 'Les vocaux arrivent dans Demandes et par e-mail, aux mêmes adresses que les autres demandes, avec la transcription et le résumé IA.'],
            ['key' => 'voice_attach', 'type' => 'checkbox', 'label' => 'Joindre le fichier audio aux e-mails des vocaux', 'help' => 'Environ 250 Ko par minute de message (5 minutes au maximum). Décochée : l\'e-mail ne contient que la transcription et le résumé, et l\'audio s\'écoute dans le back-office (Demandes).'],
            ['type' => 'heading', 'label' => 'Serveur SMTP (facultatif)', 'help' => 'Laisser vide pour utiliser l\'envoi du serveur (fonction mail de PHP).'],
            ['key' => 'smtp_host', 'label' => 'Serveur', 'half' => true],
            ['key' => 'smtp_port', 'type' => 'number', 'label' => 'Port', 'min' => 1, 'max' => 65535, 'half' => true],
            ['key' => 'smtp_secure', 'type' => 'select', 'label' => 'Chiffrement', 'options' => ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Aucun'], 'half' => true],
            ['key' => 'smtp_user', 'label' => 'Identifiant', 'half' => true],
            ['key' => 'smtp_pass', 'type' => 'secret', 'label' => 'Mot de passe', 'is_set' => Settings::hasSecret('smtp_pass'), 'half' => true],
        ];
    }
}
