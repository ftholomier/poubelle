<?php

declare(strict_types=1);

namespace App\Content;

use App\Core\JsonStore;

/**
 * Contenu de démarrage — reprise fidèle du site existant
 * (lecomptablealunettes.fr) : même arborescence, mêmes textes, mêmes visuels,
 * mêmes témoignages et mêmes chiffres. Tout reste modifiable au back-office.
 *
 * Arborescence d'origine :
 *   Accueil · Qui suis-je ? · Accompagnement · Actu · Contactez-moi
 *   + Mentions légales (pied de page)
 */
final class Bootstrap
{
    public static function seedIfEmpty(): void
    {
        if (glob(Pages::dir() . '/*.json')) {
            return;
        }
        self::seed();
    }

    public static function seed(): void
    {
        foreach (self::pages() as $page) {
            Pages::save($page);
        }
        if (!is_file(DATA_PATH . '/reviews.json')) {
            Reviews::saveManual(self::reviews());
        }
        if (!is_file(Settings::file())) {
            Settings::save(Settings::defaults());
        }
        self::seedDictionaries();
    }

    /* ------------------------------------------------------------------ */

    private static function block(string $type, array $data, array $options = []): array
    {
        return array_merge([
            'id'      => bin2hex(random_bytes(6)),
            'type'    => $type,
            'enabled' => true,
            'anchor'  => '',
            'spacing' => 'normal',
            'theme'   => 'light',
            'data'    => $data,
        ], $options);
    }

    /** Bandeau d'appel à l'action repris du site : « Passez à l'action, collaborons ! » */
    private static function ctaBand(): array
    {
        return self::block('cta_band', [
            'title' => ['fr' => 'Passez à l’action, collaborons !', 'en' => 'Take action — let’s work together!'],
            'text'  => [
                'fr' => 'Romain Lemaire, le comptable à lunettes qui accompagne les entreprises et les dirigeants à la réussite de leurs projets.',
                'en' => 'Romain Lemaire, the accountant with glasses, supports companies and their leaders towards successful projects.',
            ],
            'primary_label'   => ['fr' => 'Demandez un accompagnement', 'en' => 'Request support'],
            'primary_url'     => '/contact?sujet=accompagnement',
            'secondary_label' => ['fr' => 'Contactez-moi', 'en' => 'Contact me'],
            'secondary_url'   => '/contact',
            'variant' => 'dark',
        ], ['theme' => 'dark']);
    }

    /** Les quatre palmes d'or, reprises du site. */
    private static function palmes(): array
    {
        return self::block('logos', [
            'title' => [
                'fr' => 'Meilleur cabinet de transaction de France — 2020, 2021, 2022 & 2023',
                'en' => 'Best transaction firm in France — 2020, 2021, 2022 & 2023',
            ],
            'items' => [
                ['image' => '/assets/img/marque/palme-2020.jpg', 'alt' => 'Palme d’or 2020'],
                ['image' => '/assets/img/marque/palme-2021.jpg', 'alt' => 'Palme d’or 2021'],
                ['image' => '/assets/img/marque/palme-2022.jpg', 'alt' => 'Palme d’or 2022'],
                ['image' => '/assets/img/marque/palme-2023.jpg', 'alt' => 'Palme d’or 2023'],
            ],
        ], ['theme' => 'surface']);
    }

    /** @return array<int,array<string,mixed>> */
    private static function pages(): array
    {
        return array_merge([
            self::homePage(),
            self::quiSuisJePage(),
            self::accompagnementPage(),
            self::actuPage(),
            self::contactPage(),
            self::mentionsPage(),
        ], self::posts());
    }

    /* ------------------------------------------------------------------ */
    /* Accueil                                                             */
    /* ------------------------------------------------------------------ */
    private static function homePage(): array
    {
        return [
            'slug' => 'accueil', 'home' => true, 'order' => 10, 'in_menu' => true,
            'nav_label' => ['fr' => 'Accueil', 'en' => 'Home'],
            'title'     => ['fr' => 'Accueil', 'en' => 'Home'],
            'seo' => [
                'title' => [
                    'fr' => 'Le comptable à lunettes — Romain Lemaire',
                    'en' => 'The accountant with glasses — Romain Lemaire',
                ],
                'description' => [
                    'fr' => 'Passez à l’action, collaborons ! Romain Lemaire, le comptable à lunettes qui accompagne les entreprises et les dirigeants à la réussite de leurs projets.',
                    'en' => 'Let’s work together! Romain Lemaire supports companies and their leaders towards successful projects.',
                ],
            ],
            'blocks' => [
                self::block('hero', [
                    'eyebrow'   => [
                        'fr' => 'Magistrat · Comptable · Expert en conseil · Stratégie financière',
                        'en' => 'Judge · Accountant · Advisory expert · Financial strategy',
                    ],
                    'title'     => ['fr' => 'Romain Lemaire', 'en' => 'Romain Lemaire'],
                    'highlight' => ['fr' => 'Le comptable à lunettes', 'en' => 'The accountant with glasses'],
                    'text' => [
                        'fr' => 'Accompagnement dédié aux entreprises et à ses dirigeants. On collabore !',
                        'en' => 'Dedicated support for companies and their leaders. Let’s work together!',
                    ],
                    'primary_label'   => ['fr' => 'Contactez-moi', 'en' => 'Contact me'],
                    'primary_url'     => '/contact',
                    'secondary_label' => ['fr' => 'Qui suis-je ?', 'en' => 'Who am I?'],
                    'secondary_url'   => '/qui-suis-je',
                    'image'     => '/assets/img/romain-lemaire.jpg',
                    'image_alt' => ['fr' => 'Romain Lemaire, le comptable à lunettes', 'en' => 'Romain Lemaire'],
                    'badges' => [
                        ['label' => ['fr' => 'Magistrat depuis 2008', 'en' => 'Judge since 2008']],
                        ['label' => ['fr' => 'Groupe Evolutis Conseil', 'en' => 'Evolutis Conseil group']],
                        ['label' => ['fr' => '4 palmes d’or', 'en' => '4 golden palms']],
                    ],
                ], ['theme' => 'dark', 'spacing' => 'loose']),

                self::block('stats', [
                    'title' => ['fr' => '', 'en' => ''],
                    'items' => [
                        ['value' => 30, 'suffix' => '', 'label' => [
                            'fr' => 'nombre d’années d’expérience à accompagner les chefs d’entreprise',
                            'en' => 'years of experience supporting business leaders',
                        ]],
                        ['value' => 1000, 'prefix' => '+', 'label' => [
                            'fr' => 'nombre de chefs d’entreprise accompagné',
                            'en' => 'business leaders supported',
                        ]],
                        ['value' => 4, 'suffix' => '', 'label' => [
                            'fr' => 'nombre de palme d’or remporté comme meilleur cabinet de transaction de France',
                            'en' => 'golden palms won as best transaction firm in France',
                        ]],
                    ],
                ], ['theme' => 'surface', 'spacing' => 'tight']),

                self::block('media_text', [
                    'eyebrow' => ['fr' => 'On collabore !', 'en' => 'Let’s work together!'],
                    'title'   => [
                        'fr' => 'Votre interlocuteur B2B, B2C mais surtout H2H',
                        'en' => 'Your B2B, B2C and above all H2H contact',
                    ],
                    'html' => [
                        'fr' => '<p class="lead">Romain Lemaire, votre interlocuteur B2B, B2C mais surtout H2H, de l’Humain à l’Humain !</p>'
                            . '<p>Magistrat–comptable, expert en conseil et stratégie financière, président fondateur du groupe Evolutis Conseil, j’accompagne les entreprises et leurs dirigeants à la réussite de leurs projets.</p>',
                        'en' => '<p class="lead">Romain Lemaire, your B2B, B2C and above all H2H contact — human to human!</p>'
                            . '<p>Judge–accountant, advisory and financial strategy expert, founding president of the Evolutis Conseil group.</p>',
                    ],
                    'image'     => '/assets/img/romain-lemaire-video.jpg',
                    'image_alt' => ['fr' => 'Romain Lemaire, expert en finance', 'en' => 'Romain Lemaire, finance expert'],
                    'side' => 'right',
                    'bullets' => [
                        ['text' => ['fr' => 'Spécialisé dans le conseil et l’accompagnement des entreprises et de ses dirigeants', 'en' => 'Specialised in advising and supporting companies and their leaders']],
                        ['text' => ['fr' => 'Formation d’expertise comptable à l’École Nationale de Commerce', 'en' => 'Accountancy training at the École Nationale de Commerce']],
                        ['text' => ['fr' => 'Magistrat depuis 2008, diplômé en droit de la Sorbonne', 'en' => 'Judge since 2008, law graduate of the Sorbonne']],
                    ],
                    'cta_label' => ['fr' => 'Qui suis-je ?', 'en' => 'Who am I?'],
                    'cta_url'   => '/qui-suis-je',
                ]),

                self::palmes(),

                self::block('reviews', [
                    'eyebrow' => ['fr' => 'Ils me font confiance', 'en' => 'They trust me'],
                    'title'   => ['fr' => 'Ce que disent les dirigeants accompagnés', 'en' => 'What supported leaders say'],
                    'text'    => ['fr' => '', 'en' => ''],
                    'limit'   => 6,
                ]),

                self::ctaBand(),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Qui suis-je ?                                                       */
    /* ------------------------------------------------------------------ */
    private static function quiSuisJePage(): array
    {
        return [
            'slug' => 'qui-suis-je', 'order' => 20,
            'nav_label' => ['fr' => 'Qui suis-je ?', 'en' => 'Who am I?'],
            'title'     => ['fr' => 'Qui suis-je ?', 'en' => 'Who am I?'],
            'seo' => [
                'title' => [
                    'fr' => 'Romain Lemaire — Conseil, accompagnement d’entreprise et stratégie financière',
                    'en' => 'Romain Lemaire — Advisory, business support and financial strategy',
                ],
                'description' => [
                    'fr' => 'Romain Lemaire, président fondateur du groupe Evolutis Conseil : magistrat–comptable, expert en conseil et stratégie financière, transmission d’entreprises.',
                    'en' => 'Romain Lemaire, founding president of the Evolutis Conseil group: judge–accountant, advisory and financial strategy expert.',
                ],
            ],
            'blocks' => [
                self::block('hero', [
                    'eyebrow'   => ['fr' => 'Romain Lemaire', 'en' => 'Romain Lemaire'],
                    'title'     => ['fr' => 'Président fondateur du groupe', 'en' => 'Founding president of the'],
                    'highlight' => ['fr' => 'Evolutis Conseil', 'en' => 'Evolutis Conseil group'],
                    'text' => [
                        'fr' => 'Magistrat–comptable, expert en conseil et stratégie financière. Transmission d’entreprises.',
                        'en' => 'Judge–accountant, advisory and financial strategy expert. Business transfers.',
                    ],
                    'primary_label'   => ['fr' => 'Contactez-moi', 'en' => 'Contact me'],
                    'primary_url'     => '/contact',
                    'secondary_label' => ['fr' => 'Mon accompagnement', 'en' => 'My support'],
                    'secondary_url'   => '/accompagnement',
                    'image'     => '/assets/img/romain-lemaire.jpg',
                    'image_alt' => ['fr' => 'Romain Lemaire', 'en' => 'Romain Lemaire'],
                ], ['theme' => 'dark', 'spacing' => 'loose']),

                self::block('services', [
                    'eyebrow' => ['fr' => 'Pourquoi ?', 'en' => 'Why?'],
                    'title'   => ['fr' => 'Un parcours au service des dirigeants', 'en' => 'A career serving business leaders'],
                    'text'    => ['fr' => '', 'en' => ''],
                    'columns' => 3,
                    'items' => [
                        [
                            'icon' => 'handshake',
                            'title' => ['fr' => 'Conseil et accompagnement', 'en' => 'Advisory and support'],
                            'text'  => [
                                'fr' => 'Spécialisé dans le conseil et l’accompagnement des entreprises et de ses dirigeants.',
                                'en' => 'Specialised in advising and supporting companies and their leaders.',
                            ],
                        ],
                        [
                            'icon' => 'calculator',
                            'title' => ['fr' => 'Expertise comptable', 'en' => 'Accountancy'],
                            'text'  => [
                                'fr' => 'Formation d’expertise comptable à l’École Nationale de Commerce.',
                                'en' => 'Accountancy training at the École Nationale de Commerce.',
                            ],
                        ],
                        [
                            'icon' => 'shield',
                            'title' => ['fr' => 'Magistrat et juriste', 'en' => 'Judge and lawyer'],
                            'text'  => [
                                'fr' => 'Magistrat depuis 2008. Diplômé en droit de la Sorbonne.',
                                'en' => 'Judge since 2008. Law graduate of the Sorbonne.',
                            ],
                        ],
                    ],
                ]),

                self::block('richtext', [
                    'title' => ['fr' => 'Transmission d’entreprises', 'en' => 'Business transfers'],
                    'html'  => [
                        'fr' => '<p class="lead">Société reconnue depuis 4 années consécutives par la profession comptable comme meilleur cabinet de transaction de France.</p>'
                            . '<p><strong>2020, 2021, 2022 &amp; 2023</strong></p>',
                        'en' => '<p class="lead">A firm recognised for four consecutive years by the accountancy profession as the best transaction firm in France.</p>'
                            . '<p><strong>2020, 2021, 2022 &amp; 2023</strong></p>',
                    ],
                    'narrow' => true,
                ]),

                self::palmes(),
                self::block('reviews', [
                    'eyebrow' => ['fr' => 'Ils me font confiance', 'en' => 'They trust me'],
                    'title'   => ['fr' => 'Ce que disent les dirigeants accompagnés', 'en' => 'What supported leaders say'],
                    'limit'   => 6,
                ]),
                self::ctaBand(),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Accompagnement                                                      */
    /* ------------------------------------------------------------------ */
    private static function accompagnementPage(): array
    {
        return [
            'slug' => 'accompagnement', 'order' => 30,
            'nav_label' => ['fr' => 'Accompagnement', 'en' => 'Support'],
            'title'     => ['fr' => 'Accompagnement', 'en' => 'Support'],
            'seo' => [
                'title' => [
                    'fr' => 'Accompagnement des entreprises et de leurs dirigeants — Groupe Evolutis',
                    'en' => 'Support for companies and their leaders — Evolutis group',
                ],
                'description' => [
                    'fr' => 'Le groupe Evolutis Conseil, créé en 1992, regroupe les activités de services aux entreprises et à leurs dirigeants : conseil, transmission, patrimoine, domiciliation.',
                    'en' => 'The Evolutis Conseil group, founded in 1992, brings together services for companies and their leaders.',
                ],
            ],
            'blocks' => [
                self::block('hero', [
                    'eyebrow'   => ['fr' => 'Groupe Evolutis', 'en' => 'Evolutis group'],
                    'title'     => ['fr' => 'Des services aux entreprises', 'en' => 'Services for companies'],
                    'highlight' => ['fr' => 'et à leurs dirigeants', 'en' => 'and their leaders'],
                    'text' => [
                        'fr' => 'Le groupe Evolutis Conseil a été créé en 1992 et a accompagné des milliers d’entrepreneurs vers la réussite de leurs projets.',
                        'en' => 'The Evolutis Conseil group was founded in 1992 and has supported thousands of entrepreneurs.',
                    ],
                    'primary_label'   => ['fr' => 'Demandez un accompagnement', 'en' => 'Request support'],
                    'primary_url'     => '/contact?sujet=accompagnement',
                    'secondary_label' => ['fr' => 'Contactez-moi', 'en' => 'Contact me'],
                    'secondary_url'   => '/contact',
                    'variant' => 'centered',
                ], ['theme' => 'dark']),

                self::block('services', [
                    'eyebrow' => ['fr' => 'Nos activités', 'en' => 'Our activities'],
                    'title'   => [
                        'fr' => 'L’ensemble des sociétés du groupe Evolutis de Romain Lemaire',
                        'en' => 'All the companies of Romain Lemaire’s Evolutis group',
                    ],
                    'text' => [
                        'fr' => 'Regroupant les activités de services aux entreprises et à ses dirigeants.',
                        'en' => 'Bringing together services for companies and their leaders.',
                    ],
                    'columns' => 3,
                    'items' => [
                        ['icon' => 'handshake', 'title' => ['fr' => 'Conseil et accompagnement', 'en' => 'Advisory and support'],
                         'text' => ['fr' => 'Conseil et accompagnements des dirigeants et des entreprises.', 'en' => 'Advice and support for leaders and companies.']],
                        ['icon' => 'growth', 'title' => ['fr' => 'Transmission', 'en' => 'Business transfer'],
                         'text' => ['fr' => 'Évaluation, achat-vente d’entreprises, mise en relation entre les parties.', 'en' => 'Valuation, purchase and sale of businesses, introductions between parties.']],
                        ['icon' => 'shield', 'title' => ['fr' => 'Structuration patrimoniale', 'en' => 'Wealth structuring'],
                         'text' => ['fr' => 'Structuration patrimoniale et assistance juridique.', 'en' => 'Wealth structuring and legal assistance.']],
                        ['icon' => 'building', 'title' => ['fr' => 'Domiciliation commerciale', 'en' => 'Business address'],
                         'text' => ['fr' => 'Agrément préfectoral depuis 2010, permettant aux entreprises de fixer leur siège social à l’adresse du centre d’affaires et non au domicile du dirigeant.', 'en' => 'Prefectoral approval since 2010, letting companies register at the business centre rather than the director’s home.']],
                        ['icon' => 'compass', 'title' => ['fr' => 'Représentant fiscal', 'en' => 'Tax representative'],
                         'text' => ['fr' => 'Représentant fiscal pour sociétés étrangères.', 'en' => 'Tax representative for foreign companies.']],
                        ['icon' => 'document', 'title' => ['fr' => 'Secrétariat administratif', 'en' => 'Administrative support'],
                         'text' => ['fr' => 'Secrétariat administratif au service des dirigeants.', 'en' => 'Administrative support for company directors.']],
                        ['icon' => 'target', 'title' => ['fr' => 'Ressources humaines', 'en' => 'Human resources'],
                         'text' => ['fr' => 'Gestion en ressources humaines, dont assistance en procédure prud’homale.', 'en' => 'HR management, including employment tribunal assistance.']],
                        ['icon' => 'clock', 'title' => ['fr' => 'Agence immobilière', 'en' => 'Estate agency'],
                         'text' => ['fr' => 'Activité d’agence immobilière du groupe.', 'en' => 'The group’s estate agency activity.']],
                    ],
                ]),

                self::palmes(),
                self::ctaBand(),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Actu                                                                */
    /* ------------------------------------------------------------------ */
    private static function actuPage(): array
    {
        return [
            'slug' => 'actu', 'order' => 40,
            'nav_label' => ['fr' => 'Actu', 'en' => 'News'],
            'title'     => ['fr' => 'Actu', 'en' => 'News'],
            'seo' => ['description' => [
                'fr' => 'Actualités, publications et parutions presse de Romain Lemaire, le comptable à lunettes.',
                'en' => 'News, publications and press coverage of Romain Lemaire.',
            ]],
            'blocks' => [
                self::block('hero', [
                    'eyebrow'   => ['fr' => 'Publications & presse', 'en' => 'Publications & press'],
                    'title'     => ['fr' => 'Actu', 'en' => 'News'],
                    'highlight' => ['fr' => '', 'en' => ''],
                    'text'      => ['fr' => 'Les parutions et publications du cabinet.', 'en' => 'The firm’s publications and press coverage.'],
                    'primary_label' => ['fr' => 'Contactez-moi', 'en' => 'Contact me'],
                    'primary_url'   => '/contact',
                    'variant' => 'centered',
                ], ['theme' => 'dark']),
                self::block('posts', [
                    'eyebrow' => ['fr' => '', 'en' => ''],
                    'title'   => ['fr' => 'Dernières publications', 'en' => 'Latest publications'],
                    'limit'   => 12,
                ]),
                self::ctaBand(),
            ],
        ];
    }

    /** Articles repris de la rubrique « Actu ». @return array<int,array<string,mixed>> */
    private static function posts(): array
    {
        $items = [
            ['prevention-des-defaillances-2024', '2024-11-26',
             'Prévention des défaillances — Lutte contre l’exercice illégal et les fraudes — 2024',
             'Prévention des défaillances, lutte contre l’exercice illégal de la profession et contre les fraudes.'],
            ['ensemble-pour-agir-2023', '2024-05-22',
             'Ensemble Pour Agir — 2023 — Belles Vues Finances',
             'Livre blanc « Ensemble Pour Agir », publié avec Belles Vues Finances.'],
            ['entreprendre-2022', '2024-02-20',
             'Entreprendre — 2022 — Belles Vues Finances',
             'Parution dans le magazine Entreprendre, avec Belles Vues Finances.'],
            ['editions-legislatives-2022', '2024-02-20',
             'Éditions Législatives — 2022 — Belles Vues Finances',
             'Parution aux Éditions Législatives, avec Belles Vues Finances.'],
            ['forbes-2023', '2024-02-19',
             'Forbes — 2023 — Belles Vues Finances',
             'Article Forbes consacré à la transmission d’entreprise, avec Belles Vues Finances.'],
            ['challenges-2023', '2022-07-26',
             'Challenges — 2023 — Evolutis Conseil',
             'Parution dans Challenges du 14 décembre 2023, consacrée à Evolutis Conseil.'],
        ];

        $pages = [];
        $order = 100;
        foreach ($items as [$slug, $date, $title, $excerpt]) {
            $pages[] = [
                'slug' => $slug, 'type' => 'post', 'order' => $order += 10,
                'in_menu' => false,
                'published_at' => $date . 'T09:00:00+01:00',
                'nav_label' => ['fr' => $title],
                'title'     => ['fr' => $title],
                'excerpt'   => ['fr' => $excerpt],
                'cover'     => $slug === 'forbes-2023' ? '/assets/img/article-forbes.jpg' : '',
                'seo'       => ['description' => ['fr' => $excerpt]],
                'blocks' => [
                    self::block('richtext', [
                        'title' => ['fr' => $title],
                        'html'  => ['fr' => '<p class="lead">' . $excerpt . '</p>'
                            . '<p>Contenu de l’article à compléter depuis le back-office.</p>'],
                        'narrow' => true,
                    ]),
                    self::ctaBand(),
                ],
            ];
        }
        return $pages;
    }

    /* ------------------------------------------------------------------ */
    /* Contactez-moi                                                       */
    /* ------------------------------------------------------------------ */
    private static function contactPage(): array
    {
        return [
            'slug' => 'contact', 'order' => 50,
            'nav_label' => ['fr' => 'Contactez-moi', 'en' => 'Contact me'],
            'title'     => ['fr' => 'Contactez-moi', 'en' => 'Contact me'],
            'seo' => [
                'title' => [
                    'fr' => 'Romain Lemaire — Expert comptable finance — Le comptable à lunettes',
                    'en' => 'Romain Lemaire — Accounting and finance expert',
                ],
                'description' => [
                    'fr' => 'Contactez Romain Lemaire : 28 rue de la bretonnerie, 95300 Pontoise. +33 (0)1 34 430 430.',
                    'en' => 'Contact Romain Lemaire: 28 rue de la bretonnerie, 95300 Pontoise, France.',
                ],
            ],
            'blocks' => [
                self::block('hero', [
                    'eyebrow'   => ['fr' => 'Pourquoi ?', 'en' => 'Why?'],
                    'title'     => ['fr' => 'On collabore !', 'en' => 'Let’s work together!'],
                    'highlight' => ['fr' => '', 'en' => ''],
                    'text' => [
                        'fr' => 'Spécialisé dans le conseil et l’accompagnement des entreprises et de ses dirigeants. Formation d’expertise comptable à l’École Nationale de Commerce. Magistrat depuis 2008, diplômé en droit de la Sorbonne.',
                        'en' => 'Specialised in advising and supporting companies and their leaders. Accountancy training at the École Nationale de Commerce. Judge since 2008, law graduate of the Sorbonne.',
                    ],
                    'primary_label'   => ['fr' => 'Demandez un accompagnement', 'en' => 'Request support'],
                    'primary_url'     => '#contact',
                    'secondary_label' => ['fr' => 'Appelez-moi', 'en' => 'Call me'],
                    'secondary_url'   => 'tel:+33134430430',
                    'variant' => 'centered',
                ], ['theme' => 'dark']),

                self::block('contact', [
                    'eyebrow' => ['fr' => 'Contact', 'en' => 'Contact'],
                    'title'   => ['fr' => 'Parlons de votre projet', 'en' => 'Let’s talk about your project'],
                    'text'    => [
                        'fr' => 'Romain Lemaire, votre interlocuteur B2B, B2C mais surtout H2H, de l’Humain à l’Humain !',
                        'en' => 'Romain Lemaire, your B2B, B2C and above all H2H contact — human to human!',
                    ],
                    'show_map'  => true,
                    'show_info' => true,
                ], ['anchor' => 'contact']),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Mentions légales                                                    */
    /* ------------------------------------------------------------------ */
    private static function mentionsPage(): array
    {
        $fr = <<<'HTML'
<h2>Éditeur du site</h2>
<p><strong>Directeur de la publication :</strong> Romain Lemaire<br>
<strong>Dénomination :</strong> Le Comptable à Lunettes - Evolutis Conseil<br>
<strong>Numéro Siret :</strong> 38858139900028<br>
<strong>Hébergeur :</strong> NUXIT</p>

<h2>Loi du 6 janvier 1978 relative à l’informatique et aux libertés</h2>
<p>Conformément à la loi n° 78-17 du 6 janvier 1978, vous pouvez à tout moment accéder aux informations personnelles vous concernant et détenues, demander leur modification ou leur suppression.</p>
<p>Ce droit d’accès peut s’exercer selon les modalités suivantes : en adressant un courrier électronique par l’intermédiaire du formulaire de contact du site en indiquant les champs et/ou informations dont la modification est souhaitée.</p>

<h2>Recueil d’informations à caractère nominatif</h2>
<p>En vertu de la loi informatique et libertés du 6 janvier 1978, les données à caractère nominatif recueillies auprès des internautes par l’intermédiaire d’un formulaire ou autre ne sauraient, en aucun cas, être transmises, à titre gratuit ou onéreux, à des tierces personnes physiques ou morales.</p>

<h2>Responsabilité des utilisateurs</h2>
<ul>
<li>L’utilisateur s’engage à utiliser les services proposés sur le Site, de façon loyale et honnête, et conformément à leur destination.</li>
<li>L’utilisateur s’engage à utiliser les services proposés sur le Site pour ses seuls besoins et s’interdit d’en faire commerce auprès de tiers.</li>
</ul>

<h2>Bases de données</h2>
<ul>
<li>Constitution : l’ensemble des informations relatives aux membres figurant sur le site, ainsi que leurs modalités de consultation, constituent les Bases de Données du Site.</li>
<li>Ces bases de données sont de la propriété exclusive du Directeur de la publication et sont protégées par les dispositions du Code de la Propriété Intellectuelle relatives au droit d’auteur et par les dispositions de la Directive Européenne du 11 mars 1996 sur la protection juridique des bases de données.</li>
<li>Dans ces conditions, l’Utilisateur s’engage à utiliser ces données dans le strict cadre des services proposés sur le Site et s’interdit notamment de reproduire, traduire, adapter, arranger, transformer, communiquer, représenter et distribuer, de façon permanente ou provisoire, par tout moyen et sous quelque forme que ce soit tout ou partie des données contenues dans ces bases.</li>
<li>Toute utilisation ou exploitation faite en violation des présentes conditions est constitutive d’une atteinte aux droits du Directeur de la publication, réprimée en application des conventions internationales et des règles relatives au droit d’auteur visées au présent paragraphe.</li>
<li>Par ailleurs, le Directeur de la publication en qualité de producteur des bases de données interdit l’extraction et la réutilisation de la totalité ou d’une partie quelle qu’elle soit, du contenu de celles-ci.</li>
<li>Le Directeur de la publication met en œuvre tous les moyens nécessaires afin d’empêcher le vol, l’exploitation préjudiciable ou la destruction des bases de données du Site. Cependant, le Directeur de la publication ne peut garantir les utilisateurs contre ces infractions dans l’hypothèse où elles seraient causées par un manquement de l’Hébergeur à ses obligations.</li>
</ul>

<h2>Liens vers le site</h2>
<ul>
<li>Tout lien hypertexte de tout autre site avec ce site devra faire l’objet d’une autorisation expresse et préalable du Directeur de la publication.</li>
<li>Toute demande de lien doit être effectuée en adressant un courrier électronique par l’intermédiaire du formulaire de contact du site.</li>
<li>Les informations contenues dans ce site internet sont fournies par le Directeur de la publication et protégées.</li>
<li>La distribution, la modification ou la reproduction partielle ou en totalité de ce site sont interdites sans accord préalable écrit auprès du Directeur de la publication.</li>
</ul>
HTML;

        return [
            'slug' => 'mentions-legales', 'order' => 900, 'in_menu' => false, 'in_footer' => true,
            'nav_label' => ['fr' => 'Mentions légales', 'en' => 'Legal notice'],
            'title'     => ['fr' => 'Mentions légales', 'en' => 'Legal notice'],
            'seo'       => ['noindex' => true],
            'blocks' => [
                self::block('richtext', [
                    'title'  => ['fr' => 'Mentions légales', 'en' => 'Legal notice'],
                    'html'   => ['fr' => $fr, 'en' => $fr],
                    'narrow' => true,
                ]),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Témoignages repris du site (notes sur 10 converties en étoiles)     */
    /* ------------------------------------------------------------------ */
    private static function reviews(): array
    {
        return [
            ['author' => 'CN', 'role' => '', 'rating' => 5, 'date' => '10/10',
             'text' => 'Je fais appel au cabinet AFR depuis un moment maintenant, et je recommande sans hésiter. Ce qui me frappe avant tout, c’est la combinaison rare entre expertise technique solide et véritable accompagnement stratégique. On ne se sent pas simplement « géré » comme un dossier parmi d’autres, mais réellement conseillé avec une vision à long terme. L’équipe est à la fois professionnelle, bienveillante et très disponible.'],
            ['author' => 'M. F', 'role' => '', 'rating' => 5, 'date' => '10/10',
             'text' => 'Nous avons fait appel à vos services, pour la seconde fois, dans le cadre de la cession de notre fonds de commerce. Nous sommes extrêmement satisfait de votre action et recommandons vivement votre cabinet.'],
            ['author' => 'M. Kerlan', 'role' => 'Cogedis', 'rating' => 5, 'date' => '09/10',
             'text' => 'Dossier rapide à mener et aboutissement du dossier.'],
            ['author' => 'M. Defranoux', 'role' => 'Tripally', 'rating' => 5, 'date' => '10/10',
             'text' => 'Super rendez-vous. Romain Lemaire a su trouver toutes les réponses à nos questions. Je recommande.'],
            ['author' => 'Me B.', 'role' => '', 'rating' => 5, 'date' => '10/10',
             'text' => 'Nous avons sollicité Romain Lemaire afin de nous conseiller sur la structuration de nos investissements immobiliers. Les conseils prodigués sont toujours aussi pertinents et percutants, prenant en compte les considérations aussi bien comptables, fiscales et patrimoniales.'],
            ['author' => 'M V.', 'role' => '', 'rating' => 4, 'date' => '08/10',
             'text' => 'Grande compétence et réactivité.'],
            ['author' => 'Me P.', 'role' => '', 'rating' => 5, 'date' => '10/10',
             'text' => 'Monsieur Lemaire et son équipe sont à l’écoute et réactifs. Il répond précisément aux questions posées avec toutes les explications nécessaires.'],
            ['author' => 'M. Cossard', 'role' => 'Design & Production', 'rating' => 5, 'date' => '10/10',
             'text' => 'Monsieur Romain Lemaire est toujours de bon conseil sans chercher à être complaisant. Il est capable d’avoir du recul et avec diplomatie vous amener à une meilleure compréhension de votre situation.'],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Dictionnaires d'interface                                           */
    /* ------------------------------------------------------------------ */
    public static function seedDictionaries(): void
    {
        $dictionaries = [
            'fr' => [
                'nav.menu' => 'Menu', 'nav.close' => 'Fermer', 'nav.open' => 'Ouvrir le menu',
                'skip.content' => 'Aller au contenu principal',
                'lang.switch' => 'Choisir la langue', 'lang.auto' => 'Traduction automatique',
                'cta.contact' => 'Contactez-moi', 'cta.coaching' => 'Demandez un accompagnement',
                'form.name' => 'Nom et prénom', 'form.email' => 'Adresse e-mail', 'form.phone' => 'Téléphone',
                'form.company' => 'Entreprise', 'form.subject' => 'Votre demande', 'form.message' => 'Votre message',
                'form.send' => 'Envoyer ma demande', 'form.sending' => 'Envoi en cours…',
                'form.consent' => 'J’accepte d’être recontacté au sujet de ma demande.',
                'form.required' => 'Ce champ est obligatoire.',
                'form.error' => 'Une erreur est survenue. Merci de réessayer dans un instant.',
                'form.error_name' => 'Merci d’indiquer votre nom.',
                'form.error_email' => 'Cette adresse e-mail semble incorrecte.',
                'form.error_message' => 'Décrivez votre demande en quelques mots.',
                'form.error_consent' => 'Merci d’accepter d’être recontacté.',
                'form.rate_limited' => 'Trop de demandes envoyées. Réessayez dans quelques minutes.',
                'form.lead_thanks' => 'C’est noté ! Vous recevez une réponse très vite.',
                'chat.title' => 'Assistant du cabinet', 'chat.send' => 'Envoyer',
                'chat.launcher' => 'Une question ?',
                'chat.placeholder' => 'Posez votre question…',
                'chat.unavailable' => 'L’assistant est momentanément indisponible. Utilisez le bouton « Contactez-moi », je vous réponds personnellement.',
                'chat.rate_limited' => 'Vous avez posé beaucoup de questions ! Passons à l’action : demandez un accompagnement.',
                'chat.sources' => 'Sources',
                'chat.disclaimer' => 'Réponses générées à partir du contenu du site. Elles ne remplacent pas un conseil personnalisé.',
                'reviews.title' => 'Ils me font confiance', 'reviews.google' => 'avis',
                'reviews.see_all' => 'Voir tous les avis',
                'footer.legal' => 'Mentions légales', 'footer.privacy' => 'Confidentialité',
                'footer.sitemap' => 'Plan du site', 'footer.rights' => 'Tous droits réservés',
                'footer.follow' => 'Me suivre', 'footer.navigation' => 'Navigation', 'footer.contact' => 'Contact',
                'error.404.title' => 'Cette page a disparu de mon champ de vision',
                'error.404.text' => 'Le lien est peut-être ancien, ou l’adresse mal recopiée. Reprenons depuis l’accueil.',
                'error.404.cta' => 'Retour à l’accueil',
                'posts.read' => 'Lire la suite', 'posts.empty' => 'Les premières publications arrivent bientôt.',
                'popup.close' => 'Fermer la fenêtre',
                'a11y.top' => 'Revenir en haut de page',
            ],
            'en' => [
                'nav.menu' => 'Menu', 'nav.close' => 'Close', 'nav.open' => 'Open menu',
                'skip.content' => 'Skip to main content',
                'lang.switch' => 'Choose language', 'lang.auto' => 'Machine translation',
                'cta.contact' => 'Contact me', 'cta.coaching' => 'Request support',
                'form.name' => 'Full name', 'form.email' => 'E-mail address', 'form.phone' => 'Phone',
                'form.company' => 'Company', 'form.subject' => 'Your request', 'form.message' => 'Your message',
                'form.send' => 'Send my request', 'form.sending' => 'Sending…',
                'form.consent' => 'I agree to be contacted about my request.',
                'form.required' => 'This field is required.',
                'form.error' => 'Something went wrong. Please try again shortly.',
                'form.error_name' => 'Please tell me your name.',
                'form.error_email' => 'This e-mail address looks incorrect.',
                'form.error_message' => 'Describe your request in a few words.',
                'form.error_consent' => 'Please agree to be contacted.',
                'form.rate_limited' => 'Too many requests sent. Try again in a few minutes.',
                'form.lead_thanks' => 'Noted! You will hear back very soon.',
                'chat.title' => 'Firm assistant', 'chat.send' => 'Send',
                'chat.launcher' => 'A question?',
                'chat.placeholder' => 'Ask your question…',
                'chat.unavailable' => 'The assistant is temporarily unavailable. Use “Contact me” and I will reply personally.',
                'chat.rate_limited' => 'That is a lot of questions! Let’s take action: request support.',
                'chat.sources' => 'Sources',
                'chat.disclaimer' => 'Answers generated from the site content. They do not replace tailored advice.',
                'reviews.title' => 'They trust me', 'reviews.google' => 'reviews',
                'reviews.see_all' => 'See all reviews',
                'footer.legal' => 'Legal notice', 'footer.privacy' => 'Privacy',
                'footer.sitemap' => 'Sitemap', 'footer.rights' => 'All rights reserved',
                'footer.follow' => 'Follow me', 'footer.navigation' => 'Navigation', 'footer.contact' => 'Contact',
                'error.404.title' => 'This page has gone out of focus',
                'error.404.text' => 'The link may be old, or the address mistyped. Let’s start again from the home page.',
                'error.404.cta' => 'Back to home',
                'posts.read' => 'Read more', 'posts.empty' => 'The first publications are coming soon.',
                'popup.close' => 'Close the dialog',
                'a11y.top' => 'Back to top',
            ],
        ];

        foreach ($dictionaries as $lang => $strings) {
            $file = DATA_PATH . '/i18n/' . $lang . '.json';
            if (!is_file($file)) {
                JsonStore::write($file, $strings, false);
            }
        }
    }
}
