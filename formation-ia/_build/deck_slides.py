#!/usr/bin/env python3
"""Contenu du deck : l'enchaînement des séquences des deux journées."""
from deck import *  # noqa: F403

# ═══════════════════════════════════════════ OUVERTURE
s_cover()

s = s_content(
    "Programme", "Ce que nous allons faire ensemble", accent=INK, tsize=38,
    notes="Annoncez le ratio : plus de la moitié du temps, ce sont eux qui manipulent. "
          "Prévenez aussi que la journée 1 est plus dense en explications et la journée 2 "
          "plus dense en pratique — pour que personne ne décroche le premier après-midi.")
rect(s, M, 2.35, 5.9, 0.05, fill=J1)
txt(s, M, 2.55, 5.6, 0.4, "JOUR 1 — COMPRENDRE & DIALOGUER", size=12, font=MONO,
    bold=True, color=J1, spc=150)
bullets(s, M, 3.05, 5.6, [
    "L'IA sans le brouillard : *ce que c'est, ce que ce n'est pas*",
    "Soixante-dix ans d'histoire en quarante-cinq minutes",
    "Premier contact et *chasse aux hallucinations*",
    "L'art de parler aux machines : la méthode *C.O.P.A.I.N.*",
    "Cartographie des modèles : lequel choisir, et pourquoi",
    "Données, droit et cadre légal",
], accent=J1, size=15.5)
rect(s, M + 6.5, 2.35, 5.1, 0.05, fill=J2)
txt(s, M + 6.5, 2.55, 5.0, 0.4, "JOUR 2 — PRODUIRE & AUTOMATISER", size=12, font=MONO,
    bold=True, color=J2, spc=150)
bullets(s, M + 6.5, 3.05, 5.0, [
    "Le laboratoire : *six outils, six effets*",
    "Fabriquer son propre assistant",
    "Agents et automatisation",
    "La grille : *quoi automatiser en premier*",
    "Le futur, en trois horizons",
    "Mon plan à trente jours",
], accent=J2, size=15.5)

s_content(
    "Règles du jeu", "Trois règles, et on n'en parle plus",
    items=[
        "*On teste tout.* Rien de ce qui est dit ici ne vaut une minute passée à l'essayer vous-même. Vos ordinateurs restent ouverts.",
        "*Se tromper fait partie du programme.* Une IA qui vous répond n'importe quoi est un moment pédagogique, pas un échec. Signalez-le, on l'analyse ensemble.",
        "*Aucune question n'est bête.* Celle que vous n'osez pas poser, la moitié de la salle se la pose aussi. Coupez-moi quand vous voulez.",
    ], accent=INK, isize=18,
    right=("Ce qu'on ne fera pas",
           "Pas de mathématiques.\nPas d'installation compliquée.\nPas de vente d'outil.\n\n"
           "Et aucune donnée confidentielle ne sera saisie pendant les ateliers."),
    notes="Posez ces trois règles debout, en deux minutes. La troisième est la plus importante : "
          "un débutant qui n'ose pas demander ce qu'est un token décrochera pour la journée.")

s_content(
    "Tour de table", "Où en êtes-vous, ce matin ?",
    items=[
        "Votre prénom, votre métier, et *votre usage actuel de l'IA en un seul mot*.",
        "À main levée : qui l'utilise déjà ? Qui s'en sert *tous les jours* ? Qui paie un abonnement ?",
        "Et la question qui compte : *qu'est-ce qui vous a amené ici aujourd'hui ?*",
    ], accent=INK, isize=18,
    right=("Pour le formateur",
           "Notez au tableau les trois métiers les plus représentés.\n\n"
           "Tous les exemples de la journée seront tirés de ces trois-là."),
    notes="Cinq minutes maximum, chronomètre en main. L'objectif n'est pas de faire connaissance "
          "mais de calibrer votre niveau de langage pour les deux jours. Notez aussi qui est réticent : "
          "vous irez le chercher au premier atelier.")

s_atelier(
    1, 15, "La première hallucination, dans les dix premières minutes",
    ["Je demande à l'IA, devant vous, une courte biographie de l'un d'entre vous — "
     "en ne lui donnant que le prénom et le métier.",
     "On lit le résultat à voix haute.",
     "La personne concernée corrige : qu'est-ce qui est vrai, qu'est-ce qui est inventé ?",
     "On recommence avec deux autres participants."],
    "Combien d'affirmations fausses ? Étaient-elles *plausibles* ? "
    "C'est exactement le problème : l'IA n'écrit pas n'importe quoi, elle écrit quelque chose "
    "de crédible. C'est ce qui la rend utile — et dangereuse.",
    accent=INK, wash=WASH,
    notes="Le brise-glace le plus efficace de la formation. Il fait rire, il détend, et il pose "
          "le sujet des hallucinations par l'expérience plutôt que par un avertissement que "
          "personne n'écoute. Choisissez d'abord quelqu'un qui a l'air à l'aise.")

# ═══════════════════════════════════════════ SÉQUENCE 2 — L'IA SANS LE BROUILLARD
s_section(2, "JOUR 1", "L'IA sans le brouillard",
          "Comprendre ce qu'il y a derrière le mot, et pourquoi cette machine invente.", J1, J1W)

s_content(
    "Séquence 2", "Le mot « IA » ne veut rien dire tout seul",
    big="Six technologies différentes se cachent derrière trois lettres.",
    items=[
        "Quand un journal écrit « l'IA va supprimer des emplois », de quoi parle-t-il exactement ?",
        "Quand votre banque dit « nous utilisons l'IA », est-ce la même chose que ChatGPT ?",
        "Quand un fournisseur vous vend « une solution IA », que vous vend-il réellement ?",
    ], accent=J1,
    notes="Trois questions, posées à la salle, sans donner la réponse. Elles installent le besoin "
          "du schéma qui suit. Ne répondez pas : enchaînez sur l'infographie.")

s_image("01-poupees-russes.png", "poupées russes",
        "Prenez le temps : c'est le schéma le plus important de la journée 1. Faites-le lire de "
        "l'extérieur vers l'intérieur. Insistez sur le fait qu'un thermostat intelligent est une IA — "
        "cela désamorce beaucoup de fantasmes.")

s_statement(
    "Un LLM ne comprend pas\nce que vous lui dites.",
    "Il calcule, mot après mot, la suite la plus probable. C'est tout. "
    "Et c'est déjà suffisant pour faire quatre-vingts pour cent de ce qu'on lui demande.",
    accent=J1,
    notes="Marquez un silence après avoir dit cette phrase. C'est le pivot de toute la formation : "
          "tout ce qui suit — les hallucinations, l'art du prompt, les limites — en découle.")

s_content(
    "Démonstration", "Faisons tourner un LLM à trois neurones",
    items=[
        "Sortez votre téléphone et ouvrez n'importe quelle application où vous écrivez du texte.",
        "Tapez « Je vais » — puis *n'acceptez plus que les mots proposés par le clavier*. "
        "Une dizaine de fois de suite.",
        "Lisez votre phrase à voix haute.",
    ], accent=J1, isize=18,
    right=("Ce qu'on vient de faire",
           "Exactement ce que fait un grand modèle de langage, avec un vocabulaire minuscule "
           "et aucune mémoire du contexte.\n\n"
           "La phrase est grammaticalement correcte — et vide de sens."),
    notes="Cinq minutes, et c'est le moment où les visages se détendent. Faites lire deux ou trois "
          "phrases : elles sont souvent drôles. La bascule pédagogique se joue ici, pas dans les slides.")

s_image("02-comment-marche-un-llm.png", "fonctionnement",
        "Trois étapes, trois arrêts. Sur l'étape 2, insistez sur le mot « probable » et non « vrai ». "
        "Sur l'étape 3, faites remarquer que le modèle relit toute la phrase à chaque mot — c'est ce "
        "qui explique les temps de réponse et les coûts.")

s_content(
    "Vocabulaire", "Quatre mots qui reviendront sans arrêt",
    items=[
        "*Token* — un morceau de mot, l'unité que le modèle manipule et celle qui vous est facturée. "
        "Environ quatre caractères en français.",
        "*Fenêtre de contexte* — tout ce que le modèle peut garder sous les yeux en une fois : "
        "votre conversation et vos documents. Au-delà, il oublie le début.",
        "*Température* — le réglage qui décide s'il prend toujours le mot le plus probable "
        "ou s'il ose des choix moins attendus. Basse pour du juridique, haute pour du créatif.",
        "*Hallucination* — une affirmation fausse énoncée avec assurance. Ce n'est pas un bug : "
        "c'est le fonctionnement normal d'une machine à produire du plausible.",
    ], accent=J1, isize=16.5,
    notes="Ne faites pas apprendre ces mots : faites-les reconnaître. Ils reviendront dans les "
          "ateliers, c'est là qu'ils s'ancreront. Le seul qui compte vraiment aujourd'hui est le dernier.")

s_content(
    "Séquence 2", "Pourquoi il invente — et pourquoi il ne le sait pas",
    big="Ce n'est pas une base de données. Il n'y a rien à consulter.",
    items=[
        "Le modèle ne stocke pas des faits : il a appris des *régularités statistiques* du langage. "
        "« Le président de la République française est… » a une suite très probable ; "
        "« le numéro de téléphone du service client de… » aussi, et elle sera inventée.",
        "Il n'a aucun moyen interne de distinguer ce qu'il sait de ce qu'il complète. "
        "*L'assurance du ton est constante*, que la réponse soit juste ou fausse.",
        "Les outils modernes corrigent partiellement en allant chercher des sources réelles. "
        "Regardez toujours *s'il y a des liens cliquables* : sans source, pas de vérification.",
    ], accent=J1, isize=16.5,
    notes="C'est LA slide à ne pas bâcler. Reliez-la à l'atelier 1 du matin : la biographie inventée "
          "n'était pas un dysfonctionnement, c'était la machine faisant exactement son travail.")

# ═══════════════════════════════════════════ SÉQUENCE 3 — HISTOIRE
s_section(3, "JOUR 1", "Soixante-dix ans en quarante-cinq minutes",
          "L'IA n'est pas née en 2022. Comprendre d'où elle vient évite deux erreurs de jugement.", J1, J1W)

s_image("03-frise-histoire.png", "frise",
        "Racontez, ne lisez pas. Trois arrêts : Dartmouth en 1956 pour la promesse initiale, "
        "les deux hivers pour la prudence, 2017 pour le vrai point de bascule.")

s_content(
    "Acte I · 1950-2011", "Soixante ans de promesses, et deux hivers",
    items=[
        "*1950* — Turing pose la question et propose un test pour y répondre. Rien de la technique "
        "actuelle n'existe encore.",
        "*1956* — La conférence de Dartmouth invente l'expression « intelligence artificielle ». "
        "On annonce la machine pensante pour dans vingt ans. On l'annoncera encore trois fois.",
        "*1974-1980 et 1987-1993* — Les deux hivers. Les résultats ne suivent pas, les financements "
        "s'effondrent, le mot « IA » devient infréquentable dans les demandes de subvention.",
        "*1997* — Deep Blue bat Kasparov. La machine calcule mieux que l'homme ; elle ne comprend "
        "toujours rien à ce qu'elle fait.",
    ], accent=INK3, isize=16,
    notes="La fonction de cet acte est de vacciner contre l'emballement. Dites-le franchement : "
          "on a déjà annoncé deux fois que tout allait changer, et deux fois ce fut faux.")

s_content(
    "Acte II · 2012-2021", "La bascule technique, hors du regard du public",
    items=[
        "*2012* — Les réseaux de neurones profonds écrasent la concurrence en reconnaissance "
        "d'images. Le deep learning devient sérieux.",
        "*2016* — AlphaGo bat le champion du monde de go, un jeu qu'on croyait inaccessible aux "
        "machines pour des décennies.",
        "*2017* — Publication de « Attention Is All You Need », qui décrit l'architecture Transformer. "
        "*Huit pages qui rendent possible absolument tout ce qui suit.* Le T de ChatGPT vient de là.",
        "*2018-2021* — Les premiers grands modèles de langage sortent des laboratoires. "
        "Presque personne, hors du secteur, n'en entend parler.",
    ], accent=J1, isize=16,
    notes="Si vous ne devez retenir qu'une date à faire retenir, c'est 2017. Notez que dix ans "
          "séparent la percée technique de sa diffusion : c'est le délai habituel, et il explique "
          "pourquoi l'impression de soudaineté est une illusion.")

s_content(
    "Acte III · 2022-2026", "L'irruption dans le quotidien",
    items=[
        "*Novembre 2022* — ChatGPT. Cent millions d'utilisateurs en deux mois : l'adoption la plus "
        "rapide de l'histoire du logiciel.",
        "*2023* — Le multimodal. Les modèles lisent des images et des documents, entendent, parlent.",
        "*2024* — Les agents. Le modèle cesse de répondre pour commencer à agir : il navigue, "
        "exécute, enchaîne des tâches.",
        "*2025-2026* — Le raisonnement progresse, et l'IA s'installe dans les outils de bureau — "
        "souvent sans qu'on l'ait demandée.",
    ], accent=J2, isize=16,
    notes="Terminez sur le dernier point : beaucoup de participants ont déjà de l'IA dans leur "
          "messagerie ou leur suite bureautique sans le savoir. Demandez qui a remarqué un bouton "
          "nouveau ces derniers mois.")

s_statement(
    "Soixante ans pour devenir crédible.\nQuatre ans pour devenir quotidienne.",
    "Ce n'est pas la technologie qui a accéléré : c'est sa diffusion. "
    "Ce décalage explique pourquoi tout le monde a l'impression d'être en retard.",
    accent=J2,
    notes="Slide de respiration avant la pause ou l'atelier. Laissez la phrase à l'écran pendant "
          "le débat qui suivra forcément.")

# ═══════════════════════════════════════════ SÉQUENCE 4 — PREMIER CONTACT
s_section(4, "JOUR 1", "Premier contact",
          "Assez parlé. On ouvre les outils, et on va délibérément les faire échouer.", J1, J1W)

s_atelier(
    2, 20, "La même question, deux modèles différents",
    ["Ouvrez deux outils parmi ChatGPT, Claude, Gemini et Le Chat.",
     "Posez à chacun *exactement la même question*, tirée de votre métier réel.",
     "Comparez : le ton, la longueur, la structure, ce qui est proposé spontanément.",
     "Notez la réponse que vous préférez, et surtout *pourquoi*."],
    "Les préférences vont diverger dans la salle, et c'est le point. "
    "Il n'y a pas de meilleur modèle dans l'absolu : il y a celui dont le style "
    "correspond à ce que vous attendiez.",
    accent=J1, wash=J1W,
    notes="Circulez pendant l'atelier et repérez deux réponses très différentes à projeter au "
          "débrief. Si un participant est bloqué sur la création de compte, mettez-le en binôme "
          "immédiatement plutôt que de dépanner.")

s_atelier(
    3, 30, "Chasse aux hallucinations",
    ["Demandez à l'IA des informations *vérifiables* : votre entreprise, une réglementation de "
     "votre secteur, un chiffre de marché, la biographie d'une personne connue de vous.",
     "Insistez, demandez des détails, des dates, des sources.",
     "*Vérifiez chaque affirmation* — moteur de recherche, site officiel, votre propre connaissance.",
     "Comptez : combien d'erreurs, sur combien d'affirmations ?"],
    "On additionne les erreurs de toute la salle au tableau. Puis la vraie question : "
    "*qu'est-ce qui les rendait crédibles ?* Elles étaient précises, bien écrites, et souvent "
    "accompagnées de faux détails rassurants.",
    accent=J1, wash=J1W,
    livrable="Une « carte de confiance » construite collectivement : ce qu'on délègue les yeux fermés, "
             "ce qu'on relit, ce qu'on ne demande jamais.",
    notes="C'est l'atelier le plus important de la journée 1. Ne le raccourcissez pas si vous avez "
          "pris du retard : coupez plutôt dans l'histoire. Un participant qui a vérifié lui-même "
          "une erreur ne l'oubliera plus.")

s_content(
    "Débrief", "Ce qu'il fait très bien, ce qu'il fait très mal",
    accent=J1, tsize=38,
    notes="Construisez ce tableau AVEC la salle plutôt que de le projeter. Ce qui est écrit ici "
          "est votre filet de sécurité si le groupe sèche.")
s = prs.slides[-1]
txt(s, M, 2.5, 5.6, 0.3, "CE QU'ON PEUT LUI CONFIER", size=10.5, font=MONO, bold=True, color=J1, spc=140)
bullets(s, M, 2.9, 5.6, [
    "Reformuler, résumer, traduire *un texte que vous lui fournissez*",
    "Structurer des idées, faire un plan, sortir d'une page blanche",
    "Changer le ton, la longueur, le niveau de langue",
    "Expliquer un concept que vous pourrez vérifier ailleurs",
    "Générer des variantes et des idées à trier",
], accent=J1, size=15.5)
txt(s, M + 6.5, 2.5, 5.0, 0.3, "CE QU'IL NE FAUT PAS LUI CONFIER", size=10.5, font=MONO,
    bold=True, color=J2, spc=140)
bullets(s, M + 6.5, 2.9, 5.0, [
    "Un chiffre, une date, une référence *qu'il produit lui-même*",
    "Un calcul dont le résultat vous engage",
    "Une citation, un texte de loi, une jurisprudence",
    "Une décision : la signature reste humaine",
    "Quoi que ce soit de confidentiel — on y revient ce soir",
], accent=J2, size=15.5)

# ═══════════════════════════════════════════ SÉQUENCE 5 — COPAIN
s_section(5, "JOUR 1", "L'art de parler aux machines",
          "Le même modèle, la même question — et deux résultats sans rapport. "
          "Toute la différence est dans la demande.", J1, J1W)

s = s_content(
    "Séquence 5", "Le prompt paresseux, et ce qu'il produit",
    accent=J1, tsize=38,
    notes="Faites l'exercice en direct sur le vidéoprojecteur : tapez vraiment le prompt paresseux, "
          "montrez la réponse tiède, puis la version complète. La comparaison à l'écran vaut "
          "tous les arguments.")
card(s, M, 2.5, 5.6, 1.5, "Ce qu'on tape d'habitude",
     "« Écris-moi une offre d'emploi pour un comptable. »", INK3, WASH, size=17)
txt(s, M, 4.25, 5.6, 1.6,
    "Résultat : générique, interchangeable, plein de « rejoignez une équipe dynamique ». "
    "Utilisable nulle part sans réécriture complète.", size=16, color=INK2, spacing=1.25)
card(s, M + 6.5, 2.5, 5.0, 3.4, "Ce qui manque à la machine",
     "Qui je suis.\nCe que je veux exactement.\nÀ qui ça s'adresse.\n"
     "Sous quelle forme.\nCe qu'elle doit éviter.\n\n"
     "Six informations que vous avez en tête — et qu'elle n'a pas.", J1, J1W, size=16)

s_image("04-methode-copain.png", "copain",
        "Le schéma central de la journée. Construisez-le lettre par lettre avec la salle plutôt que "
        "de le projeter d'un coup : demandez à un participant de donner son cas, et remplissez "
        "les six lignes en direct.")

s_content(
    "C.O.P.A.I.N.", "Les trois premières lettres",
    items=[
        "*C — Contexte.* Où sommes-nous ? Qui parle ? Dans quelle situation ? La machine ne sait "
        "rien de vous. Une ligne suffit : « Je suis responsable RH dans une PME industrielle de 60 personnes. »",
        "*O — Objectif.* Qu'est-ce que je veux obtenir, précisément ? Un verbe d'action, pas une "
        "intention vague. « Rédige », « compare », « classe », « corrige » — pas « aide-moi avec ».",
        "*P — Persona.* Qui doit-il être ? Assigner un rôle change le vocabulaire, le niveau de "
        "détail et les réflexes professionnels mobilisés. « Agis en recruteur du secteur industriel. »",
    ], accent=J1, isize=16.5,
    notes="Une lettre, un exemple, on avance. Ne théorisez pas : les participants retiendront "
          "l'acronyme par l'atelier, pas par l'explication.")

s_content(
    "C.O.P.A.I.N.", "Les trois dernières",
    items=[
        "*A — Audience.* À qui la réponse s'adresse-t-elle ? Un texte pour des experts et un texte "
        "pour des débutants n'ont rien en commun. « Elle sera lue par des candidats juniors, bac+2. »",
        "*I — Instructions.* Sous quelle forme, exactement ? Longueur, structure, ton, format de "
        "sortie. C'est la lettre la plus rentable : « 400 mots, trois parties titrées, tutoiement. »",
        "*N — Négations.* Qu'est-ce qu'il ne doit surtout pas faire ? Les interdits sont souvent "
        "plus efficaces que les consignes. « Pas de jargon RH, et pas de “équipe dynamique”. »",
    ], accent=J1, isize=16.5,
    notes="Insistez sur le N : c'est la lettre que tout le monde oublie et celle qui supprime "
          "le plus de réécriture. Faites donner trois interdits par la salle.")

s_content(
    "Séquence 5", "Cinq techniques qui changent tout",
    items=[
        "*Donnez des exemples.* Deux ou trois échantillons de ce que vous voulez valent dix lignes "
        "de description. C'est la technique la plus efficace, et la moins utilisée.",
        "*Imposez le format de sortie.* « Réponds sous forme de tableau à trois colonnes » évite "
        "un aller-retour sur deux.",
        "*Demandez le raisonnement.* « Procède étape par étape et montre-moi ton raisonnement » "
        "améliore nettement les tâches d'analyse et de calcul.",
        "*Découpez.* Une grande demande donne un résultat moyen. Trois demandes enchaînées donnent "
        "trois bons résultats.",
        "*Itérez.* Le premier jet est une proposition, pas une livraison. Dites ce qui ne va pas "
        "et redemandez — c'est le N de « négocier ».",
    ], accent=J1, isize=15.5,
    notes="Choisissez-en deux à démontrer en direct selon le public : « donnez des exemples » "
          "et « imposez le format » sont les plus universelles.")

s_statement(
    "« Pose-moi les questions\ndont tu as besoin\navant de commencer. »",
    "Une seule phrase, ajoutée à la fin de n'importe quelle demande. Elle transforme un monologue "
    "en entretien, et améliore le résultat plus sûrement que n'importe quelle astuce.",
    accent=J1,
    notes="Démontrez-le tout de suite, sur un cas de la salle. C'est souvent le moment de la "
          "formation dont les participants reparlent le lendemain matin.")

s_atelier(
    4, 35, "Avant / après : mesurez l'écart vous-même",
    ["Choisissez *une tâche réelle* que vous devez faire cette semaine.",
     "Écrivez le prompt que vous auriez écrit ce matin. Lancez. *Notez le résultat sur 10.*",
     "Réécrivez la demande avec les six lettres, et la phrase de relance en dernière ligne.",
     "Lancez à nouveau. Notez sur 10. *Écrivez les deux notes au tableau.*"],
    "On calcule la moyenne des « avant » et la moyenne des « après » pour toute la salle. "
    "L'écart est votre meilleur argument, et il tient sur une ligne.",
    accent=J1, wash=J1W,
    livrable="Un prompt C.O.P.A.I.N. testé, réutilisable dès demain sur une vraie tâche.",
    notes="Passez dans les rangs : la faute la plus fréquente est d'oublier l'Audience et les "
          "Négations. Gardez deux exemples spectaculaires pour la restitution.")

# ═══════════════════════════════════════════ SÉQUENCE 6 — CARTOGRAPHIE
s_section(6, "JOUR 1", "Cartographie des modèles",
          "Il en sort un nouveau tous les mois. Voici comment choisir sans avoir à suivre l'actualité.",
          J1, J1W)

s_image("06-cartographie-llm.png", "cartographie",
        "Commencez par le bloc barré en bas à gauche : dites franchement que tout ce qui est "
        "rayé sera faux dans six mois, et que c'est pour cela qu'on ne construira pas le choix "
        "là-dessus.")

s_content(
    "Séquence 6", "Les quatre questions, dans cet ordre",
    items=[
        "*Où partent mes données ?* Elle décide plus souvent que toutes les autres réunies. "
        "Si la réponse est bloquante, les trois suivantes ne se posent même pas.",
        "*Écrit-il bien dans ma langue ?* Testez sur vos propres textes, jamais sur un classement. "
        "Un modèle premier au benchmark peut produire un français plat et scolaire.",
        "*Est-ce déjà dans les outils que j'utilise ?* L'outil qu'on ouvre par réflexe bat "
        "systématiquement l'outil parfait qu'on oublie d'ouvrir.",
        "*Combien à mon volume réel ?* Raisonnez en abonnements mensuels par personne. "
        "Le prix au million de tokens ne veut rien dire pour un utilisateur.",
    ], accent=J1, isize=16,
    notes="Faites voter la salle : « qui pense que la question 1 est bloquante chez vous ? ». "
          "Dans une PME, la réponse est souvent non — dans le public ou la santé, souvent oui.")

s_content(
    "Séquence 6", "Quatre familles, quatre usages",
    accent=J1, tsize=38,
    notes="Ne citez des noms de modèles que si on vous le demande, et prévenez à chaque fois que "
          "la version aura changé. L'important est la famille, pas le nom.")
s = prs.slides[-1]
for i, (n, who, what) in enumerate([
    ("Les généralistes", "OpenAI · Anthropic · Google · xAI",
     "Les plus polyvalents, le plus d'outils autour. Le choix par défaut au quotidien. "
     "Hébergement hors UE sauf offre entreprise."),
    ("Le souverain européen", "Mistral · Kyutai",
     "Hébergement en Europe, excellent français, engagement écrit sur le pays où sont stockées "
     "les données. La réponse quand la question 1 bloque."),
    ("Les modèles ouverts", "Llama · Mistral ouverts · Qwen · DeepSeek",
     "Installables chez vous : les données ne sortent jamais, l'usage ne se facture pas au message. "
     "Demande une compétence technique réelle."),
    ("Les spécialisés", "Transcription · documents scannés · traduction · image · voix",
     "Une tâche, très bien faite, pour une fraction du prix. Souvent la meilleure réponse à un "
     "besoin précis et répétitif."),
]):
    x = M + (i % 2) * 5.95
    y = 2.5 + (i // 2) * 2.05
    col = [J1, J1, J1, J1][i]
    rect(s, x, y, 5.6, 0.04, fill=col)
    txt(s, x, y + 0.18, 5.5, 0.4, n, size=21, font=DISP, bold=True, color=INK)
    txt(s, x, y + 0.62, 5.5, 0.25, who.upper(), size=9.5, font=MONO, bold=True, color=col, spc=120)
    txt(s, x, y + 0.95, 5.5, 1.0, what, size=14.5, color=INK2, spacing=1.22)

s_atelier(
    5, 25, "Trois collègues vous demandent conseil",
    ["Par groupes de trois. Chaque groupe reçoit une personne :",
     "*Sophie*, assistante RH : elle doit trier 80 candidatures et rédiger les réponses.",
     "*Marc*, artisan à son compte : il veut refaire ses devis et le texte de son site.",
     "*Nadia*, agent en mairie : elle doit résumer les comptes rendus du conseil municipal.",
     "Posez-vous les quatre questions *à sa place*, puis dites-lui quelle famille d'outils lui "
     "convient. *Trois minutes pour raconter.*"],
    "Les trois groupes ne donneront pas le même conseil, et c'est le résultat attendu. "
    "Faites dire *quelle question a tranché* : pour Sophie ce sont les données personnelles, "
    "pour Marc le prix, pour Nadia le lieu d'hébergement.",
    accent=J1, wash=J1W,
    notes="Si le groupe vient d'une seule entreprise, remplacez Sophie, Marc et Nadia par trois "
          "collègues réels de leur maison. C'est encore plus efficace. Personne n'a besoin de "
          "connaître l'informatique pour faire cet atelier : ils conseillent quelqu'un, c'est tout.")

# ═══════════════════════════════════════════ SÉQUENCE 7 — CADRE
s_section(7, "JOUR 1", "Données, droit et cadre",
          "La séquence qu'on rogne toujours quand on a pris du retard. "
          "C'est celle qui lève les freins — et celle qui vous protège.", J1, J1W)

s_image("05-trois-feux.png", "trois feux",
        "Faites-leur classer trois documents qu'ils ont réellement sous la main. "
        "Le débat sur ce qui est orange et ce qui est rouge est le cœur de la séquence.")

s_content(
    "Confidentialité", "Ce que devient ce que vous collez",
    items=[
        "Dans une offre *grand public*, vos conversations peuvent être conservées, relues par des "
        "humains pour le contrôle qualité, et servir à améliorer le modèle. C'est écrit dans les "
        "conditions d'utilisation, et c'est souvent désactivable dans les réglages.",
        "Dans une offre *entreprise*, l'éditeur s'engage contractuellement à ne pas entraîner ses "
        "modèles sur vos données. C'est la différence principale entre les deux, bien plus que "
        "les fonctionnalités.",
        "*Le réflexe à prendre aujourd'hui* : ouvrez les paramètres de votre outil et cherchez "
        "l'option d'amélioration du modèle. Désactivez-la. Deux minutes, tout de suite.",
    ], accent=FLAG, isize=16.5,
    right=("À faire vérifier par le client",
           "Quel outil est officiellement autorisé chez vous ?\n\n"
           "Beaucoup d'organisations n'ont jamais tranché — et l'absence de réponse "
           "pousse les équipes vers les comptes personnels."),
    notes="Faites-le vraiment faire, écran par écran. C'est une action concrète, immédiate, "
          "et elle rassure les participants les plus inquiets.")

s_content(
    "Cadre légal", "Ce que la loi vous demande déjà",
    big="Former vos équipes à l'IA est une obligation européenne depuis février 2025.",
    items=[
        "*L'article 4 du règlement européen sur l'IA* impose aux organisations qui déploient des "
        "systèmes d'IA de veiller à ce que leurs équipes sachent s'en servir. Cela vaut pour les "
        "salariés, mais aussi pour les prestataires qui travaillent pour eux.",
        "La loi demande de faire des efforts, pas d'atteindre un résultat mesurable. "
        "*Cette formation y répond* : conservez l'attestation de participation.",
        "*Le RGPD continue de s'appliquer intégralement.* Saisir des données personnelles dans un "
        "outil tiers est un transfert de données, avec tout ce que cela implique.",
    ], accent=FLAG, isize=16,
    notes="Passage très apprécié des dirigeants et des fonctions support. Dites clairement que "
          "l'obligation est peu connue des PME et que la formation coche la case.")

s_content(
    "Cadre légal", "Droit d'auteur, biais, responsabilité",
    items=[
        "*Les images générées* : le statut juridique reste discuté en Europe, et les conditions "
        "varient d'un outil à l'autre. Pour un usage commercial, vérifiez la licence de l'outil — "
        "et méfiez-vous des styles imitant un artiste identifiable.",
        "*Les biais* : le modèle a appris sur des textes existants, avec leurs stéréotypes. "
        "Sur du recrutement, de l'évaluation ou de l'orientation, la vigilance doit être maximale.",
        "*La responsabilité ne se délègue pas.* Un texte signé de votre nom vous engage, "
        "qu'il ait été écrit par vous ou proposé par une machine. « C'est l'IA qui l'a écrit » "
        "n'est pas une défense.",
    ], accent=FLAG, isize=16,
    notes="Terminez sur la responsabilité : c'est la phrase que les participants répéteront "
          "à leurs collègues, et elle vaut mieux que dix interdits.")

s_atelier(
    6, 15, "Votre charte personnelle, en cinq lignes",
    ["Sur une feuille, écrivez cinq phrases qui commencent par *« Je »*.",
     "Deux choses que vous vous autorisez désormais.",
     "Deux choses que vous ne ferez jamais.",
     "Une chose que vous devez aller vérifier auprès de votre organisation."],
    "Trois volontaires lisent leur charte à voix haute. On repère les points communs : "
    "ils forment, sans le dire, la charte de l'équipe.",
    accent=FLAG, wash=FLAGW,
    livrable="Une charte personnelle écrite, signée, emportée. C'est le premier livrable de la formation.",
    notes="Quinze minutes, dont cinq de lecture. Ne sautez pas la lecture à voix haute : "
          "l'engagement public vaut dix fois l'engagement écrit.")

s_content(
    "Fin de journée", "Pour ce soir, si vous voulez",
    big="Quinze minutes, une vraie tâche, et notez ce qui a raté.",
    items=[
        "Prenez *une seule tâche* de votre quotidien et faites-la avec l'IA, en appliquant "
        "les six lettres.",
        "Notez sur votre téléphone : *ce qui a bien marché*, et surtout *ce qui a échoué*.",
        "Demain matin, on commence par vos échecs. Ce sont eux qui font avancer le groupe.",
    ], accent=J1, isize=17,
    notes="Facultatif, et dites-le. Mais annoncez que la journée 2 commencera par ce tour de table : "
          "en pratique, les trois quarts le feront.")

# ═══════════════════════════════════════════ JOUR 2
s_section(8, "JOUR 2", "Ce que vous avez tenté hier soir",
          "Les échecs racontés valent mieux que les réussites : ils donnent les cas concrets "
          "de la journée.", J2, J2W)

s_content(
    "Réveil", "Tour de table express",
    items=[
        "Qu'avez-vous essayé ? Sur quelle tâche ?",
        "Qu'est-ce qui a marché du premier coup ?",
        "*Qu'est-ce qui a échoué, et qu'avez-vous fait ensuite ?*",
        "Qui a abandonné en cours de route — et pourquoi ?",
    ], accent=J2, isize=18,
    right=("Pour le formateur",
           "Notez deux ou trois échecs au tableau.\n\n"
           "Vous les reprendrez comme cas d'application dans l'atelier « assistant » "
           "de fin de matinée."),
    notes="Trente minutes, c'est beaucoup et c'est voulu. Ce tour de table rattrape ceux qui "
          "ont décroché la veille et donne le ton pratique de la journée.")

s_section(9, "JOUR 2", "Le laboratoire",
          "Six outils, six effets. Cinq minutes de démonstration, dix minutes de manipulation, "
          "on tourne.", J2, J2W)

s_tool(
    "NotebookLM", "Google · gratuit · le meilleur rapport effet / difficulté",
    "Vous déposez vos documents — PDF, notes, pages web, vidéos — et l'outil ne répond plus "
    "qu'à partir de ceux-là. Chaque affirmation renvoie au passage source.",
    ["Chacun charge *trois documents de son métier*",
     "Poser trois questions, vérifier les sources citées",
     "Générer la synthèse, puis la carte mentale",
     "Lancer le podcast à deux voix et écouter trente secondes"],
    "Vos documents sont hébergés chez Google. Appliquez la règle des trois feux : "
    "en salle, on ne charge que du vert.",
    accent=J2,
    notes="C'est la démonstration dont ils parleront le soir à la maison. Prévoyez du temps : "
          "la génération du podcast prend quelques minutes, lancez-la avant d'expliquer le reste.")

s_tool(
    "Génération et retouche d'image", "Gemini · Nano Banana",
    "Modifier une photo réelle en décrivant le changement en français : supprimer un élément, "
    "changer un fond, meubler une pièce vide, décliner un visuel en plusieurs formats.",
    ["Chacun part d'une *photo qu'il a sur son téléphone*",
     "Supprimer un élément gênant de l'arrière-plan",
     "Essayer un aménagement : pièce vide, mobilier ajouté",
     "Comparer avec ce qu'aurait coûté la même retouche"],
    "Les contenus générés portent une marque invisible. Et un visuel modifié qui documente "
    "un bien ou un produit engage votre responsabilité commerciale.",
    accent=J2,
    notes="L'atelier le plus visuel de la journée. Le home staging fait toujours son effet auprès "
          "des métiers de l'immobilier, du commerce et de la communication.")

s_tool(
    "Le mode vocal", "ChatGPT · Gemini · sur mobile",
    "Une conversation à voix haute, mains libres, avec interruption possible. "
    "Change complètement le rapport à l'outil : on cesse de rédiger une requête, on parle.",
    ["Deux minutes en binôme, casque ou téléphone à la main",
     "Faire préparer un entretien, ou traduire en direct",
     "Essayer de *l'interrompre* en pleine phrase",
     "Comparer avec ce qu'on aurait tapé au clavier"],
    "Tout ce qui est dit est transmis au fournisseur, comme du texte. "
    "Le réflexe de confidentialité s'oublie plus vite à l'oral.",
    accent=J2,
    notes="Court, dix minutes maximum. C'est surtout un changement de perception : beaucoup de "
          "participants n'ont jamais essayé et découvrent un usage en mobilité.")

s_tool(
    "Suno", "Génération musicale · le moment de détente",
    "Décrivez un style, une ambiance, des paroles — et obtenez un morceau complet en trois minutes. "
    "Jingle, générique de podcast, musique d'attente téléphonique.",
    ["Créer *le jingle de votre entreprise* en une phrase",
     "Écouter deux ou trois productions de la salle",
     "Faire varier un seul paramètre et réécouter",
     "Discuter : qu'est-ce qu'on en ferait vraiment ?"],
    "Sur quoi le modèle a-t-il appris ? Que possédez-vous exactement ? "
    "Lisez les conditions avant tout usage commercial ou diffusé.",
    accent=J2,
    notes="Le moment de rire de la journée, et une transition naturelle vers la question des "
          "droits d'auteur. Ne dépassez pas dix minutes.")

s_tool(
    "Perplexity et le navigateur Comet", "Recherche sourcée · agent de navigation",
    "La différence entre un modèle qui génère et un outil qui cherche puis cite. "
    "Chaque affirmation renvoie à une page réelle que vous pouvez ouvrir.",
    ["Reposer *la question de l'atelier hallucinations d'hier*",
     "Ouvrir les sources citées et les vérifier vraiment",
     "Comparer avec la réponse obtenue hier sans sources",
     "Essayer une recherche que vous feriez habituellement"],
    "Une source citée n'est pas une source lue. L'outil se trompe aussi en résumant "
    "une page correcte.",
    accent=J2,
    notes="Placez cet outil après NotebookLM : les deux reposent sur la même idée — ancrer la "
          "réponse dans des documents réels — et le rapprochement fait comprendre le principe.")

s_tool(
    "Vidéo générative", "Sora · Veo — démonstration seule",
    "Produire une séquence vidéo à partir d'une description écrite, avec son. "
    "Trop lent et trop coûteux pour un atelier, mais indispensable à montrer.",
    ["Projeter deux exemples de trente secondes",
     "Montrer un *deepfake* de qualité courante",
     "Expliquer le filigrane invisible des contenus générés",
     "Question ouverte : à quoi reconnaît-on encore le faux ?"],
    "C'est ici que se joue la question de la confiance. Prévenez que la réponse à « comment "
    "reconnaître le faux » deviendra bientôt : on ne pourra plus, il faudra vérifier la source.",
    accent=J2,
    notes="Ne lancez pas de génération en direct : trop long, trop aléatoire. Préparez vos "
          "exemples à l'avance et vérifiez qu'ils passent bien sur la sono de la salle.")

# ═══════════════════════════════════════════ SÉQUENCE 10 — ASSISTANT
s_section(10, "JOUR 2", "Fabriquer son propre assistant",
          "Passer du prompt jetable à l'outil qu'on rouvre tous les matins.", J2, J2W)

s_content(
    "Séquence 10", "Trois façons de s'y prendre, du même effort au même endroit",
    items=[
        "*Le prompt jetable.* Vous réécrivez tout à chaque fois. Efficace une fois, ruineux en temps "
        "quand la tâche revient chaque semaine.",
        "*Le prompt enregistré.* Vous gardez vos meilleures demandes dans un document, "
        "et vous copiez-collez. C'est déjà quatre-vingts pour cent du gain, pour zéro technique.",
        "*L'assistant configuré.* Vous paramétrez une fois un outil qui connaît votre contexte, "
        "votre ton et vos documents — et vous ne redonnez plus jamais les consignes.",
    ], accent=J2, isize=17,
    right=("La bonne surprise",
           "Configurer un assistant demande exactement les mêmes six lettres "
           "que le prompt d'hier.\n\n"
           "Vous savez déjà le faire — il ne reste qu'à le ranger au bon endroit."),
    notes="Reliez explicitement à C.O.P.A.I.N. : les instructions d'un assistant sont un prompt "
          "COPAIN qu'on écrit une fois pour toutes. C'est ce lien qui rend l'atelier accessible.")

s_content(
    "Séquence 10", "Ce qu'on met dans un assistant",
    items=[
        "*Un nom et une mission en une phrase.* « Tu m'aides à rédiger les comptes rendus de "
        "réunion du comité de direction. »",
        "*Le contexte permanent* : qui vous êtes, votre organisation, votre secteur, votre public.",
        "*Le ton et le format attendus*, décrits précisément. C'est ce qui évite de recorriger "
        "chaque sortie.",
        "*Des fichiers de référence* : vos modèles, vos chartes, trois exemples de ce que vous "
        "considérez comme un bon résultat. Les exemples valent mieux que les consignes.",
        "*Les interdits* : les formulations bannies, les sujets à ne pas aborder, ce qu'il ne doit "
        "jamais inventer.",
    ], accent=J2, isize=15.5,
    notes="Montrez la création en direct, écran partagé, sur un cas donné par la salle. "
          "Cinq minutes suffisent — l'important est qu'ils voient que ce n'est pas technique.")

s_atelier(
    7, 45, "Créez le vôtre, puis cassez celui du voisin",
    ["Choisissez *une tâche que vous refaites chaque semaine*.",
     "Créez un assistant : nom, mission, contexte, ton, format, interdits.",
     "Ajoutez au moins *un fichier de référence* ou trois exemples de bon résultat.",
     "*Échangez avec votre voisin* : chacun essaie de faire échouer l'assistant de l'autre.",
     "Corrigez le vôtre avec ce que le test a révélé."],
    "Qu'est-ce qui a cassé les assistants ? Presque toujours la même chose : "
    "un contexte trop maigre, ou aucun exemple de sortie attendue. "
    "*Les cas limites trouvés par le voisin sont la vraie valeur de l'atelier.*",
    accent=J2, wash=J2W,
    livrable="Un assistant fonctionnel, testé par un pair, utilisable dès le lendemain matin.",
    notes="L'atelier phare de la journée 2. Prévoyez un assistant en salle si vous êtes seul : "
          "à douze participants, vous ne dépannerez pas tout le monde. Gardez dix minutes "
          "pour la restitution croisée.")

# ═══════════════════════════════════════════ SÉQUENCE 11 — AGENTS
s_section(11, "JOUR 2", "Agents et automatisation",
          "Le modèle cesse de répondre et commence à agir. "
          "À partir d'ici, la question devient : jusqu'où le laisser faire ?", J2, J2W)

s_image("07-echelle-agents.png", "échelle",
        "Faites situer chaque participant : « à quelle marche êtes-vous ce matin ? ». "
        "La plupart sont entre 1 et 2 après l'atelier précédent — dites-le, c'est rassurant.")

s_content(
    "Séquence 11", "Quatre ingrédients, et un agent apparaît",
    items=[
        "*Un objectif* formulé en une phrase, qui n'est plus une question mais une mission.",
        "*Des outils* : lire une boîte mail, écrire dans un tableur, appeler un service, "
        "chercher sur le web. Sans outils, un modèle ne peut rien faire d'autre que parler.",
        "*Une mémoire* : ce qu'il a déjà fait, ce qu'il a appris en route, ce qui a échoué.",
        "*Une boucle* : il agit, observe le résultat, corrige, recommence — jusqu'à atteindre "
        "l'objectif ou renoncer.",
    ], accent=J2, isize=17,
    right=("Le mot à retenir",
           "Un agent n'est pas un modèle plus intelligent.\n\n"
           "C'est le même modèle, à qui l'on a donné des mains et l'autorisation de s'en servir."),
    notes="Simplifiez sans complexe : quatre cases suffisent à des débutants. "
          "Le schéma exhaustif de l'IA agentique est illisible en salle.")

s_content(
    "Démonstration", "Un scénario d'automatisation, de bout en bout",
    big="Un mail arrive → l'IA le classe → elle prépare un brouillon → l'équipe est notifiée.",
    items=[
        "On regarde le scénario se construire *bloc par bloc* : le déclencheur, la condition, "
        "l'appel au modèle, l'action finale.",
        "On l'exécute en direct sur une boîte mail de démonstration.",
        "*On regarde, on n'installe rien.* Faire installer l'outil à douze débutants coûterait "
        "la séquence entière — et le vrai travail n'est pas là.",
    ], accent=J2, isize=17,
    right=("Ce qu'il faut retenir",
           "Ces outils sont accessibles sans savoir programmer.\n\n"
           "Mais ils demandent de savoir décrire précisément un processus — "
           "et c'est cette compétence-là qui manque, pas la technique."),
    notes="Préparez le scénario à l'avance et testez-le le matin même. Une démonstration qui "
          "plante sur une intégration détruit la crédibilité de toute la séquence.")

s_content(
    "Séquence 11", "Et pendant ce temps, le logiciel se décrit tout seul",
    items=[
        "On appelle cela le *développement assisté* : on décrit en français ce qu'on veut, "
        "et une application fonctionnelle est produite, puis corrigée par itérations.",
        "Ce qui prenait des semaines de développement se prototype en une heure. "
        "*Le prototype n'est pas un produit* : la mise en production, la sécurité et la maintenance "
        "restent un métier.",
        "Pourquoi vous le montrer ? Parce que cela change ce que vous pouvez *demander* : "
        "l'idée qu'on n'osait pas proposer parce que « ça coûterait trop cher à développer » "
        "mérite d'être reposée.",
    ], accent=J2, isize=16.5,
    notes="Cinq minutes de démonstration, pas plus. Le message n'est pas « devenez développeur » "
          "mais « le coût d'essayer une idée a chuté ».")

s_image("08-grille-automatisation.png", "grille",
        "Projetez la grille pendant tout l'atelier qui suit. Les participants ont la même "
        "en page dédiée dans leur cahier d'exercices.")

s_atelier(
    8, 35, "Cartographiez vos tâches avant de choisir un outil",
    ["Listez *tout ce que vous avez fait la semaine dernière*, sans trier. Visez trente lignes.",
     "Placez chaque tâche sur la grille : fréquence en abscisse, temps passé en ordonnée.",
     "La taille du point indique la répétitivité : *presque identique à chaque fois* = gros point.",
     "Entourez les *trois tâches les plus à droite et les plus hautes*.",
     "Chiffrez-les : combien d'heures par mois, réellement ?"],
    "On compare les chiffres. Le total des heures identifiées dans la salle est le message "
    "à emporter chez soi — et c'est le seul argument qui parle à une direction.",
    accent=J2, wash=J2W,
    livrable="Une carte des trois tâches automatisables, chiffrée en heures par mois.",
    notes="Insistez au démarrage : ne pas se demander si c'est techniquement faisable. "
          "Cette question tue l'exercice en deux minutes. Le comment viendra après.")

s_statement(
    "Un agent qui se trompe\nse trompe vite, et à grande échelle.",
    "À partir de la marche 3, rien de sensible ne part sans validation humaine, "
    "et tout ce qu'il fait doit rester consultable après coup. "
    "Ce n'est pas de la prudence excessive : c'est la condition pour pouvoir y aller.",
    accent=J2,
    notes="Slide de clôture de séquence. Le dernier argument est important : les garde-fous "
          "ne freinent pas le déploiement, ils l'autorisent.")

# ═══════════════════════════════════════════ SÉQUENCE 12 — FUTUR & PLAN
s_section(12, "JOUR 2", "Le futur, et votre plan",
          "Trois horizons, avec le niveau d'incertitude annoncé pour chacun. "
          "Puis ce que vous faites lundi matin.", J2, J2W)

s_content(
    "Horizon 1 · Douze mois", "Ce dont on peut être à peu près sûr",
    items=[
        "*Les agents s'installent dans les outils que vous utilisez déjà* : messagerie, "
        "traitement de texte, tableur, navigateur. Vous n'aurez rien à installer — ils apparaîtront.",
        "*La qualité du français continue de progresser*, et l'écart entre les grands modèles "
        "se resserre sur les usages courants.",
        "*Le coût baisse encore.* Ce qui était réservé aux abonnements payants passe "
        "progressivement dans les offres de base.",
        "*Ce qui ne changera pas* : la nécessité de vérifier, et la responsabilité humaine.",
    ], accent=J2, isize=16,
    notes="Annoncez le niveau de confiance : élevé. Ces quatre points sont des prolongements de "
          "tendances déjà engagées, pas des paris.")

s_content(
    "Horizon 2 · Trois ans", "Ce qui va se déplacer dans les métiers",
    items=[
        "*Ce qui disparaît* : les tâches de production de premier jet — mise en forme, "
        "rédaction standard, saisie, tri, traduction de routine.",
        "*Ce qui se transforme* : le travail se déplace vers la formulation de la demande, "
        "la vérification et l'arbitrage. On produit moins, on valide davantage.",
        "*Ce qui prend de la valeur* : la relation, le jugement, la responsabilité, "
        "la connaissance fine d'un métier et d'un contexte — tout ce qui ne s'écrit pas dans un prompt.",
        "*Ce qui apparaît* : des rôles d'assemblage et de supervision, dans à peu près tous "
        "les services.",
    ], accent=J2, isize=16,
    notes="Ton juste : ni catastrophiste ni promotionnel. Beaucoup de participants ont une "
          "inquiétude réelle sur leur emploi. La reconnaître explicitement vaut mieux que "
          "l'esquiver — et l'honnêteté vous fait gagner la salle.")

s_content(
    "Horizon 3 · Dix ans", "Là, personne ne sait",
    items=[
        "Les spécialistes eux-mêmes sont en désaccord profond, et les prévisions passées "
        "se sont trompées *dans les deux sens* : trop optimistes en 1960, beaucoup trop "
        "pessimistes en 2015.",
        "Ce qui se discute vraiment : jusqu'où va le raisonnement, ce qui restera hors de portée, "
        "et surtout *qui décide* de la manière dont ces systèmes sont déployés.",
        "*Le seul conseil solide à dix ans* : entretenir sa capacité à apprendre vite. "
        "Ce n'est pas une formule — c'est ce qui a protégé les gens à chaque vague technologique "
        "précédente.",
    ], accent=J2, isize=16.5,
    notes="Dites explicitement « je ne sais pas ». C'est plus crédible que n'importe quelle "
          "courbe, et cela vous distingue de tous les discours qu'ils entendent par ailleurs.")

s_atelier(
    9, 30, "Mon plan à trente jours",
    ["Sur la carte qui vous a été remise, écrivez :",
     "*Trois usages* que vous mettez en place dès la semaine prochaine — précis, pas « utiliser l'IA ».",
     "*Un outil* que vous décidez de maîtriser vraiment, plutôt que d'en essayer dix.",
     "*Une chose que vous ne ferez pas*, tirée de votre charte d'hier.",
     "*Une personne* que vous embarquez avec vous — sinon vous serez seul dans trois semaines."],
    "Chacun lit à voix haute *son unique outil* et *sa personne à embarquer*. "
    "Cela crée des binômes dans la salle, et c'est ce qui fait survivre les acquis "
    "à la reprise du travail.",
    accent=J2, wash=J2W,
    livrable="Une carte format A5, remplie, emportée — le dernier livrable de la formation.",
    notes="Le tour de lecture final est essentiel : c'est là que se nouent les engagements croisés. "
          "Ne le sacrifiez pas si vous avez du retard, coupez plutôt l'horizon 3.")

s_content(
    "Séquence 12", "Rester à jour sans y passer ses soirées",
    items=[
        "*Une heure par semaine, pas plus.* Le domaine produit plus d'actualité qu'un humain "
        "n'en peut absorber, et l'essentiel de cette actualité ne vous concerne pas.",
        "*Deux ou trois sources maximum*, choisies pour leur capacité à trier — une lettre "
        "d'information de synthèse vaut mieux que dix comptes à suivre.",
        "*Le meilleur apprentissage reste l'usage.* Une heure passée sur une vraie tâche vous "
        "apprendra plus que dix articles sur le dernier modèle sorti.",
        "*Retestez tous les six mois* ce qui n'avait pas marché. La moitié de vos échecs "
        "d'aujourd'hui seront résolus par une mise à jour que personne ne vous annoncera.",
    ], accent=J2, isize=16,
    notes="Le dernier point est celui qu'on oublie systématiquement. Donnez un exemple concret "
          "d'une limite qui a sauté au cours des douze derniers mois.")

# ═══════════════════════════════════════════ CLÔTURE
s = s_content(
    "Clôture", "Quiz final — et une petite boucle",
    accent=INK, tsize=38,
    notes="Générez le quiz devant eux à partir de vos propres supports : ils viennent de passer "
          "deux jours à apprendre l'outil qui fabrique leur examen. C'est la meilleure "
          "démonstration possible, et elle ne coûte rien.")
card(s, M, 2.5, 5.6, 2.3, "Comment on le fabrique",
     "Je charge les supports de ces deux jours dans NotebookLM, devant vous, "
     "et je lui demande de générer un questionnaire de dix questions.\n\n"
     "Temps nécessaire : deux minutes.", J1, J1W, size=16)
card(s, M + 6.5, 2.5, 5.0, 2.3, "Ce que ça démontre",
     "Vous savez maintenant faire exactement cela.\n\n"
     "Pour vos propres formations, vos procédures internes, "
     "l'intégration d'un nouvel arrivant.", J2, J2W, size=16)
txt(s, M, 5.15, 11.2, 0.8,
    "*La question qui compte vraiment, avant de se quitter :* qu'est-ce que vous allez faire "
    "dès demain matin ?", size=19, color=INK2, spacing=1.3)

s = s_content(
    "Clôture", "Ce que vous emportez",
    accent=INK, tsize=38,
    notes="Distribuez le kit maintenant et pas avant : remis en début de formation, il détourne "
          "l'attention pendant les séquences. Rappelez l'attestation et l'article 4.")
bullets(s, M, 2.5, 5.6, [
    "*Le manuel du participant* — tout le contenu, les exercices, les liens",
    "*Le cahier d'exercices* — les fiches remplies pendant les deux jours",
    "*Le mémo de poche* — C.O.P.A.I.N. et la règle des trois feux",
    "*Les huit infographies* — imprimables et projetables",
], accent=INK, size=16.5)
card(s, M + 6.5, 2.5, 5.0, 2.6, "Et vos quatre livrables",
     "Votre charte personnelle.\nVotre assistant configuré.\n"
     "Votre carte des tâches automatisables.\nVotre plan à trente jours.\n\n"
     "Plus l'attestation de participation, qui documente votre conformité à l'article 4.",
     J1, J1W, size=15.5)

s = sl()
rect(s, 0, 0, SW, SH, fill=INK)
rect(s, M, 2.6, 1.6, 0.06, fill=J2)
txt(s, M, 3.1, 10.5, 1.4, "Merci — et à vous de jouer.", size=48, font=DISP,
    bold=True, color=WHITE, spacing=0.98)
txt(s, M, 4.5, 9.5, 1.2,
    "Le seul indicateur qui compte n'est pas ce que vous avez compris aujourd'hui,\n"
    "mais ce que vous ferez différemment lundi matin.",
    size=19, color=RGBColor(0xA8, 0xB4, 0xBC), spacing=1.35)
note(s, "Restez dix minutes après la fin : c'est là que se posent les questions individuelles, "
        "et souvent que se décident les missions de suite.")

# ═══════════════════════════════════════════ ÉCRITURE
out = BUILD.parent / "01-slides" / "formation-ia-2-jours.pptx"
out.parent.mkdir(parents=True, exist_ok=True)
prs.save(out)
print(f"  {out.relative_to(BUILD.parent)}  ({len(prs.slides.__iter__.__self__._sldIdLst)} slides)")
