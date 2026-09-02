/**
 * Support de projection — Formation Agence SCFR
 * « Anticipez 2026 : cumul emploi-retraite & facturation électronique »
 * 2 jours / 14 heures — Dole, sessions septembre & octobre 2026
 *
 * Génération : node build/make-deck.js
 */
const pptxgen = require("pptxgenjs");
const L = require("./deck-lib.js");
const { C } = L;

const pres = new pptxgen();
pres.layout = "LAYOUT_WIDE";
pres.author = "Agence SCFR";
pres.company = "Agence SCFR — Organisme de formation certifié Qualiopi";
pres.title = "Anticipez 2026 — Cumul emploi-retraite & facturation électronique";
pres.subject = "Support de projection formateur — 2 jours / 14 h";

/* ══════════════════════════════════════════════════════════ OUVERTURE ══ */

L.coverSlide(pres, {
  eyebrow: "Formation 2 jours · Sessions 09 & 10 · 2026 · Dole",
  titleRuns: [
    { text: "Anticipez ", options: { color: "FFFFFF" } },
    { text: "2026.", options: { color: "E07B22" } },
    { text: "\nDeux réformes,\ndeux journées.", options: { color: "FFFFFF" } },
  ],
  subtitle: "Dirigeants et chefs d'entreprise : sécurisez vos décisions face aux deux échéances qui vont marquer votre gestion.",
  meta: [
    { v: "14 h", k: "d'accompagnement" },
    { v: "12 mois", k: "de SAV inclus" },
    { v: "Dole", k: "repas & café offerts" },
    { v: "Qualiopi", k: "organisme certifié" },
  ],
  notes: "ACCUEIL — 8h30.\n\nRester sur cette slide pendant le café d'accueil.\n\nOuverture (5 min) : se présenter, présenter l'Agence SCFR, rappeler la certification Qualiopi et le SAV 12 mois inclus.\n\nPoint de contexte à donner d'emblée, il installe la crédibilité : nous sommes le 2 septembre 2026. L'obligation de réception des factures électroniques est entrée en vigueur HIER, le 1er septembre. Et la LFSS 2026 a rebattu les cartes de la retraite cet été. Ces deux journées ne sont pas de la théorie : elles portent sur du droit déjà en vigueur.",
});

L.cardsSlide(pres, {
  kicker: "Bienvenue",
  title: "Deux réformes, une même question : que dois-je faire, et quand ?",
  cols: 2,
  cards: [
    { n: "J1", t: "Cumul emploi-retraite", color: C.orange,
      d: "Vous pouvez travailler et toucher votre retraite. Mais les règles changent au 1er janvier 2027, et le régime actuel est plus favorable.\n\nEnjeu : savoir si votre fenêtre de tir se ferme dans 4 mois." },
    { n: "J2", t: "Facturation électronique", color: C.violet,
      d: "Depuis le 1er septembre 2026, vous devez pouvoir recevoir vos factures par voie électronique. En septembre 2027, vous devrez les émettre.\n\nEnjeu : être raccordé, et l'être proprement." },
  ],
  foot: "Le point commun : dans les deux cas, l'inaction a un coût chiffrable. Nous allons le chiffrer.",
  notes: "5 min.\n\nPoser le fil rouge des deux jours : ces deux sujets n'ont rien à voir sur le fond, mais tout à voir sur la méthode. Dans les deux cas il y a une date, un régime avant, un régime après, et une décision à prendre maintenant.\n\nInsister sur le « coût de l'inaction » — c'est ce qui fait tenir l'attention d'un dirigeant pendant deux jours.",
});

L.listSlide(pres, {
  kicker: "Objectifs pédagogiques",
  title: "À l'issue des deux journées, vous saurez…",
  twoCol: true,
  items: [
    { t: "Situer votre position retraite", d: "Identifier votre âge légal, votre durée d'assurance et votre régime après la suspension de la réforme." },
    { t: "Choisir entre cumul intégral et plafonné", d: "Vérifier les 3 conditions, calculer votre plafond, mesurer l'écrêtement." },
    { t: "Chiffrer votre seconde pension", d: "Savoir ce que la reprise d'activité vous rapporte réellement en droits nouveaux." },
    { t: "Arbitrer la date de liquidation", d: "Décider s'il faut liquider avant le 31 décembre 2026 pour sécuriser le régime actuel." },
    { t: "Cartographier vos flux de facturation", d: "Trier vos opérations entre e-invoicing, e-reporting et hors champ." },
    { t: "Choisir votre plateforme agréée", d: "Appliquer une grille de 7 critères et un budget réaliste à votre situation." },
    { t: "Mettre vos factures en conformité", d: "Intégrer les 4 nouvelles mentions et comprendre les 4 statuts du cycle de vie." },
    { t: "Repartir avec un plan d'action daté", d: "Un document personnel, complété pendant la formation, avec des échéances." },
  ],
  foot: "Ces objectifs sont évalués : test de positionnement en entrée, évaluation des acquis en sortie.",
  notes: "3 min.\n\nLire les objectifs à voix haute — c'est une exigence Qualiopi (indicateur 1) et cela cadre les attentes.\n\nAnnoncer les deux évaluations : le test de positionnement de ce matin n'est pas une note, c'est un point de départ. L'évaluation de demain soir mesure le chemin parcouru.",
});

L.cardsSlide(pres, {
  kicker: "Méthode",
  title: "Comment nous allons travailler",
  cols: 4,
  cards: [
    { n: "1", t: "Apports cadrés", d: "Le droit en vigueur, avec ses sources. Jamais d'affirmation sans texte derrière." },
    { n: "2", t: "Exemples chiffrés", d: "Une vingtaine de cas réels, calculés au centime, sur des profils comme les vôtres.", color: C.violet },
    { n: "3", t: "Ateliers", d: "Vous appliquez à votre situation, pas à un cas d'école. Le formateur circule." },
    { n: "4", t: "Plan d'action", d: "Complété au fil de l'eau. Vous repartez avec, il est à vous.", color: C.violet },
  ],
  foot: "Vous interrompez quand vous voulez. Une question posée tôt évite une erreur coûteuse plus tard.",
  notes: "3 min.\n\nDonner les règles du jeu : téléphones en silencieux mais accessibles (certains devront vérifier un chiffre dans leur compta), pauses toutes les 1h30, déjeuner pris en charge.\n\nInsister sur le droit à l'interruption : dans une formation de dirigeants, celui qui n'ose pas poser sa question repart avec son problème.",
});

L.listSlide(pres, {
  kicker: "Tour de table",
  title: "Avant de commencer : où en êtes-vous ?",
  lead: "Chacun se présente en 2 minutes. Notez vos réponses, elles serviront de fil rouge aux ateliers.",
  items: [
    { t: "Votre activité et votre statut juridique", d: "SARL, SAS, entreprise individuelle, micro-entreprise ? Gérant majoritaire ou président ?" },
    { t: "Votre horizon retraite", d: "Vous y pensez pour dans 1 an, 5 ans, 10 ans ? Avez-vous déjà fait un relevé de carrière ?" },
    { t: "Votre situation facturation", d: "Combien de factures émises par mois ? Quel logiciel ? Êtes-vous déjà raccordé à une plateforme ?" },
    { t: "La question que vous voulez voir traitée", d: "Une seule, précise. Le formateur la note et s'engage à y répondre avant la fin des 2 jours." },
  ],
  accent: C.violet,
  foot: "Le formateur note les questions au paperboard. Elles sont toutes reprises en clôture du jour 2.",
  notes: "20 min pour 10-12 personnes. Tenir le chrono : 2 min par personne, pas plus.\n\nÉCRIRE LES QUESTIONS AU PAPERBOARD et les laisser visibles pendant les 2 jours. C'est le dispositif le plus efficace pour l'évaluation à chaud : les stagiaires voient physiquement qu'on a répondu.\n\nDistribuer le test de positionnement (annexe A du livret) à faire remplir en 10 min juste après. Ne pas le corriger en séance : le corriger en fin de J2, en comparaison avec l'évaluation des acquis.",
});

/* ═══════════════════════════════════════════════════════════════ JOUR 1 ══ */

L.daySlide(pres, {
  day: 1,
  title: "Cumul emploi-retraite",
  subtitle: "Travailler en percevant sa retraite : ce qui est possible en 2026, ce qui change au 1er janvier 2027.",
  color: C.orange,
  blocks: [
    { n: "Matin", t: "Le paysage 2026, le cumul intégral, le cumul plafonné" },
    { n: "Après-midi", t: "La seconde pension, les alternatives, la fiscalité" },
    { n: "Fin de journée", t: "La réforme 2027 et votre arbitrage personnel" },
    { n: "Vous repartez avec", t: "Votre position chiffrée et une date de décision" },
  ],
  notes: "9h00 — Lancement du jour 1.\n\nAnnoncer la promesse de la journée en une phrase : « ce soir, vous saurez si vous avez intérêt à liquider votre retraite avant le 31 décembre, et pourquoi ».\n\nC'est le message le plus fort de la journée, il faut le poser dès maintenant pour créer l'attente.",
});

L.timelineSlide(pres, {
  kicker: "Programme",
  title: "Le déroulé du jour 1",
  steps: [
    { date: "9h00", t: "Module 1\nLe paysage retraite 2026", d: "Architecture du système, chiffres clés, et l'onde de choc de la LFSS 2026 : la suspension de la réforme." },
    { date: "10h15", t: "Module 2\nLe cumul intégral", d: "Les 3 conditions cumulatives, ce qu'elles ouvrent, et le piège de la liquidation incomplète." },
    { date: "11h15", t: "Module 3\nLe cumul plafonné", d: "Plafond salarié, plafond indépendant, mécanique de l'écrêtement, cas de la micro-entreprise." },
    { date: "14h00", t: "Modules 4 & 5\nSeconde pension & alternatives", d: "Les droits nouveaux acquis depuis 2023, la retraite progressive à 60 ans, l'arbitrage dividendes." },
    { date: "16h00", t: "Modules 6 & 7\nFiscalité & réforme 2027", d: "IR, CSG, effet de seuil. Puis la réforme du 1er janvier 2027 et votre fenêtre de décision." },
  ],
  foot: "Pauses à 10h15 et 15h45. Déjeuner à 12h30, pris en charge dans le cadre de la formation.",
  notes: "2 min. Ne pas commenter chaque ligne, juste donner le rythme.\n\nSignaler que le module 7 (réforme 2027) est le plus important de la journée et qu'il arrive en fin de parcours parce qu'il suppose tout le reste.",
});

/* ─────────────────────────────────────────── MODULE 1 : le paysage 2026 ── */

L.moduleSlide(pres, {
  num: "1", title: "Le paysage retraite en 2026",
  duration: "1 h 15",
  points: [
    "Comment le système est construit : base, complémentaire, et qui cotise où",
    "Les trois curseurs qui décident de tout : âge légal, durée d'assurance, taux plein",
    "Les chiffres 2026 que vous devez avoir en tête",
    "La suspension de la réforme de 2023 : ce que la LFSS 2026 a changé cet été",
  ],
  notes: "Objectif du module : que personne ne reste bloqué sur le vocabulaire pendant les 6 heures suivantes.\n\nAttention à ne pas y passer plus de 1h15 : c'est le module de mise à niveau, pas le cœur de la journée. Si le groupe est déjà à l'aise, accélérer sur les slides 11-13 et donner plus de temps à la suspension de la réforme.",
});

L.statsSlide(pres, {
  kicker: "Pourquoi maintenant",
  title: "Trois chiffres pour comprendre l'urgence",
  stats: [
    { v: "2,2 M", k: "d'assurés concernés", color: C.orange,
      d: "Les générations 1964 à 1968 voient leurs paramètres de départ modifiés par la suspension votée en décembre 2025." },
    { v: "1er janv. 2027", k: "la bascule", color: C.violet, small: true,
      d: "Le cumul emploi-retraite est entièrement refondu pour toute première liquidation prenant effet à partir de cette date." },
    { v: "4 mois", k: "pour décider", color: C.orangeDeep,
      d: "C'est le temps qu'il reste, au 2 septembre 2026, pour liquider sous le régime actuel et le conserver à vie." },
  ],
  foot: "Ceux qui liquident avant le 1er janvier 2027 conservent intégralement les règles actuelles. C'est une clause de sauvegarde.",
  notes: "5 min. C'est LA slide d'accroche de la journée.\n\nLaisser un silence après « 4 mois pour décider ». Puis : « nous allons passer la journée à déterminer, pour chacun d'entre vous, si cette fenêtre vous concerne — parce que pour certains, elle ne change rien, et pour d'autres, elle vaut plusieurs dizaines de milliers d'euros ».\n\nSource : LFSS 2026, art. 102, loi n° 2025-1403.",
});

L.compareSlide(pres, {
  kicker: "Architecture",
  title: "Votre retraite se joue toujours sur deux étages",
  left: {
    title: "Étage 1 — La retraite de base",
    color: C.orange,
    items: [
      "Régime obligatoire, calculé en TRIMESTRES et en revenu annuel moyen.",
      "Salariés et assimilés salariés (président de SAS) : la CNAV, le régime général.",
      "Artisans, commerçants, gérants majoritaires : la Sécurité sociale des indépendants (SSI), alignée sur le régime général.",
      "Professions libérales : la CNAVPL et ses sections (CIPAV, CARMF, CNBF…).",
      "C'est cet étage qui porte la notion de « taux plein » et l'essentiel des règles de cumul.",
    ],
  },
  right: {
    title: "Étage 2 — La retraite complémentaire",
    color: C.violet,
    items: [
      "Régime obligatoire lui aussi, mais calculé en POINTS, pas en trimestres.",
      "Salariés et assimilés : l'Agirc-Arrco. Valeur du point 2026 : 1,4386 €.",
      "Indépendants SSI : le régime complémentaire des indépendants (RCI).",
      "Libéraux : le régime complémentaire de leur section.",
      "Chaque régime a ses propres règles de cumul : liquider l'un ne liquide pas l'autre.",
    ],
  },
  foot: "Retenez ceci : « avoir liquidé sa retraite » signifie avoir liquidé les DEUX étages, dans TOUS les régimes fréquentés.",
  notes: "8 min.\n\nC'est le socle. Beaucoup de dirigeants croient avoir « pris leur retraite » alors qu'ils n'ont liquidé que le régime général et oublié un régime complémentaire d'une ancienne activité.\n\nDemander à main levée : « qui a déjà demandé son relevé de carrière sur info-retraite.fr ? » — en général moins de la moitié. C'est le premier conseil opérationnel de la journée.",
});

L.tableSlide(pres, {
  kicker: "Votre statut",
  title: "Qui cotise où — et pourquoi ça change tout",
  head: ["Votre statut", "Retraite de base", "Complémentaire", "Cessation d'activité exigée ?"],
  colW: [3.5, 2.8, 2.8, 3.02],
  rows: [
    ["Gérant majoritaire de SARL / EURL", "SSI", "RCI", { text: "Non", color: C.green, bold: true }],
    ["Entrepreneur individuel (artisan, commerçant)", "SSI", "RCI", { text: "Non", color: C.green, bold: true }],
    ["Président de SAS / SASU", "CNAV (régime général)", "Agirc-Arrco", { text: "Oui", color: C.red, bold: true }],
    ["Gérant minoritaire ou égalitaire de SARL", "CNAV (régime général)", "Agirc-Arrco", { text: "Oui", color: C.red, bold: true }],
    ["Profession libérale", "CNAVPL / section", "Complémentaire de section", { text: "Non", color: C.green, bold: true }],
    ["Micro-entrepreneur", "SSI ou CIPAV", "RCI ou section", { text: "Non", color: C.green, bold: true }],
  ],
  foot: "La colonne de droite est décisive : le président de SAS doit rompre son lien salarié pour liquider. Le gérant majoritaire, non.",
  notes: "10 min. Slide très importante — beaucoup de dirigeants ignorent cette asymétrie.\n\nLe point à marteler : seuls les dirigeants relevant du régime général des salariés sont soumis à l'obligation de cesser leur activité pour mettre en œuvre le cumul emploi-retraite. Les dirigeants relevant de la SSI ou de la CIPAV ne le sont pas.\n\nConséquence pratique énorme : un président de SAS qui veut liquider doit organiser la fin de son mandat rémunéré — c'est un acte juridique à préparer plusieurs mois à l'avance (PV d'AG, éventuelle transformation en SARL, ou passage à un mandat non rémunéré). Un gérant majoritaire, lui, continue simplement.",
});

L.statsSlide(pres, {
  kicker: "Chiffres clés 2026",
  title: "Les six nombres à retenir de la journée",
  dark: true,
  stats: [
    { v: "48 060 €", k: "PASS annuel 2026", small: true, d: "Plafond annuel de la Sécurité sociale. Sert de base à presque tous les plafonds de cumul." },
    { v: "2 916,85 €", k: "160 % du SMIC / mois", small: true, color: C.violet, d: "Le plafond du cumul plafonné pour un salarié, en brut mensuel." },
    { v: "24 030 €", k: "50 % du PASS / an", small: true, d: "Le plafond du cumul plafonné pour un indépendant SSI, en revenu net annuel." },
    { v: "2 403 €", k: "5 % du PASS / an", small: true, color: C.violet, d: "Le plafond de la seconde pension de base. Environ 200 € par mois." },
  ],
  foot: "Et deux repères d'âge : 67 ans, le taux plein automatique. 1 803 € bruts, ce qu'il faut gagner pour valider un trimestre en 2026.",
  notes: "6 min. Faire noter ces chiffres — ils reviennent toute la journée.\n\nLe PASS est la clé de voûte : 48 060 € en 2026. Presque tous les plafonds en dérivent (50 % pour les indépendants, 5 % pour la seconde pension).\n\nAstuce mnémotechnique à donner : « le PASS, c'est le mètre-étalon de la Sécu ; le SMIC, c'est celui du salarié ».\n\nRappeler que ces montants sont revalorisés chaque 1er janvier : le livret porte la mention de la date d'arrêté des données.",
});

L.alertSlide(pres, {
  kicker: "Actualité — LFSS 2026",
  title: "La réforme des retraites de 2023 est suspendue",
  body: "Votée le 16 décembre 2025, promulguée fin décembre, la loi de financement de la Sécurité sociale pour 2026 gèle la montée en charge du relèvement de l'âge légal et de la durée d'assurance.",
  points: [
    "S'applique aux pensions prenant effet à compter du 1er septembre 2026 — donc dès maintenant.",
    "L'âge légal est figé à 62 ans et 9 mois jusqu'en janvier 2028.",
    "2,2 millions d'assurés nés entre 1964 et 1968 partent 3 à 6 mois plus tôt que prévu.",
    "Les générations 1964 et 1965 doivent en plus valider 1 à 2 trimestres de moins.",
  ],
  notes: "10 min. Moment fort du module 1.\n\nBeaucoup de stagiaires auront entendu parler de « la suspension » sans savoir si elle les concerne. Le tableau de la slide suivante répond précisément.\n\nÊtre net sur le vocabulaire : c'est une SUSPENSION, pas une abrogation. Le calendrier reprend en janvier 2028. La cible de 64 ans / 172 trimestres reste, elle est simplement décalée.\n\nSi on vous demande « et après 2028 ? » : la réponse honnête est que cela dépendra des lois de financement à venir. Ne pas extrapoler.",
});

L.tableSlide(pres, {
  kicker: "Suspension de la réforme",
  title: "Ce que vous gagnez, génération par génération",
  lead: "Pour les pensions prenant effet à compter du 1er septembre 2026.",
  head: ["Génération", "Calendrier réforme 2023", "Après suspension LFSS 2026", "Ce que vous gagnez"],
  colW: [2.6, 3.3, 3.3, 2.92],
  rows: [
    ["1964", "63 ans / 171 trimestres", { text: "62 ans 9 mois / 170 trim.", bold: true, color: C.green }, "3 mois et 1 trimestre"],
    ["1965 (janv. → mars)", "63 ans 3 mois / 172 trim.", { text: "62 ans 9 mois / 170 trim.", bold: true, color: C.green }, "6 mois et 2 trimestres"],
    ["1965 (avril → déc.)", "63 ans 3 mois / 172 trim.", { text: "63 ans / 171 trimestres", bold: true, color: C.green }, "3 mois et 1 trimestre"],
    ["1966 à 1968", "63 ans 6 mois → 64 ans", { text: "Décalage uniforme de 3 mois", bold: true, color: C.green }, "3 mois"],
    ["1969 et après", "64 ans / 172 trimestres", "Cible inchangée à terme", "—"],
  ],
  foot: "Le taux plein automatique reste à 67 ans, quelle que soit la génération. La suspension n'y touche pas.",
  notes: "10 min. Slide à laisser affichée longtemps.\n\nFaire l'exercice en direct : demander à 2 ou 3 stagiaires leur année de naissance et lire ensemble la ligne correspondante. C'est le moment où la formation devient concrète pour eux.\n\nAttention à une confusion fréquente : gagner 3 mois d'âge légal ne veut pas dire partir au taux plein. Si la durée d'assurance n'est pas atteinte, il y a toujours décote. Le tableau donne DEUX paramètres, pas un.\n\nOrienter vers le relevé de carrière sur info-retraite.fr pour connaître son nombre exact de trimestres validés.",
});

L.cardsSlide(pres, {
  kicker: "Ne pas se tromper",
  title: "Ce que la suspension NE change PAS",
  cols: 3,
  cards: [
    { n: "1", t: "Le taux plein à 67 ans", color: C.gray,
      d: "L'âge d'annulation automatique de la décote reste fixé à 67 ans, pour tout le monde, quelle que soit la carrière." },
    { n: "2", t: "La retraite progressive", color: C.violet,
      d: "Son âge d'accès est autonome depuis le 1er septembre 2025 : 60 ans, quelle que soit la génération. La suspension ne l'affecte pas." },
    { n: "3", t: "Les règles du cumul", color: C.orange,
      d: "La suspension porte sur l'ÂGE et la DURÉE. Elle ne modifie pas les conditions du cumul emploi-retraite — c'est l'article 102 qui s'en charge, au 1er janvier 2027." },
  ],
  foot: "Deux textes, deux logiques : la suspension vous fait partir plus tôt ; la réforme du cumul restreint ce que vous pourrez faire ensuite.",
  notes: "5 min. Slide de clarification, elle évite une confusion qui reviendra sinon toute la journée.\n\nLa LFSS 2026 fait DEUX choses distinctes :\n1. Elle suspend le calendrier de la réforme 2023 (vous partez plus tôt) — effet au 1er septembre 2026.\n2. Elle réforme le cumul emploi-retraite (article 102) — effet au 1er janvier 2027.\n\nCes deux mesures se combinent et peuvent jouer en sens contraire : je pars plus tôt, mais si je pars après le 1er janvier 2027, je cumule moins bien. C'est exactement l'arbitrage du module 7.",
});

/* ──────────────────────────────────── MODULE 2 : le cumul intégral ── */

L.moduleSlide(pres, {
  num: "2", title: "Le cumul emploi-retraite intégral",
  duration: "1 h",
  color: C.violet,
  points: [
    "Ce que le cumul autorise exactement — et ce qu'il n'autorise pas",
    "Les trois conditions cumulatives, sans exception",
    "Le piège numéro un : la pension oubliée",
    "Deux cas pratiques : une présidente de SAS, un dirigeant à carrière multiple",
  ],
  notes: "Cœur de la matinée. Le message central : le cumul intégral n'est pas un droit automatique, c'est le résultat de 3 conditions remplies simultanément. Si une seule manque, on bascule en plafonné, et l'écart est très important.",
});

L.cardsSlide(pres, {
  kicker: "Définition",
  title: "Le cumul emploi-retraite, en une phrase",
  cols: 2,
  cards: [
    { n: "✓", t: "Ce que c'est", color: C.green,
      d: "Le droit de percevoir vos pensions de retraite tout en exerçant une activité professionnelle rémunérée — salariée, non salariée, ou les deux.\n\nVous êtes retraité ET actif. Vous touchez votre pension chaque mois, et vous facturez ou percevez un salaire à côté.\n\nDeux régimes existent : intégral (sans plafond) ou plafonné (avec écrêtement)." },
    { n: "✗", t: "Ce que ce n'est pas", color: C.red,
      d: "Ce n'est pas la retraite progressive : là, vous n'avez pas liquidé, vous touchez une FRACTION de pension en travaillant à temps partiel.\n\nCe n'est pas un report de départ : reporter, c'est ne pas liquider et accumuler une surcote.\n\nCe n'est pas non plus automatique : il faut déclarer sa reprise d'activité à toutes ses caisses." },
  ],
  foot: "La confusion entre cumul et retraite progressive est la plus fréquente. Nous y reviendrons au module 5.",
  notes: "6 min.\n\nPoser le vocabulaire proprement. Trois dispositifs différents, souvent confondus :\n- Le cumul emploi-retraite : j'ai liquidé, je travaille.\n- La retraite progressive : je n'ai pas liquidé, je travaille à temps partiel et je touche une fraction.\n- Le report / la surcote : je n'ai pas liquidé, je travaille à plein, ma future pension augmente.\n\nDemander au groupe lequel des trois les intéresse le plus — cela permet de doser le module 5.",
});

L.listSlide(pres, {
  kicker: "Les conditions",
  title: "Cumul intégral : trois conditions, toutes obligatoires",
  lead: "Ces conditions sont CUMULATIVES. Il en manque une, vous basculez automatiquement en cumul plafonné.",
  items: [
    { t: "Avoir atteint l'âge légal de départ à la retraite", d: "Entre 62 ans et 64 ans selon votre génération — et 62 ans et 9 mois pour beaucoup d'entre vous depuis la suspension. Voir le tableau du module 1." },
    { t: "Bénéficier du taux plein", d: "Soit vous avez validé la durée d'assurance requise pour votre génération, soit vous avez atteint 67 ans, âge auquel le taux plein est automatique quelle que soit la carrière." },
    { t: "Avoir liquidé TOUTES vos pensions", d: "De base ET complémentaires, dans TOUS les régimes français et étrangers auxquels vous avez été affilié, même pour quelques trimestres il y a trente ans." },
  ],
  accent: C.violet,
  foot: "Trois sur trois : cumul intégral, revenus sans aucun plafond. Deux sur trois : cumul plafonné, avec écrêtement.",
  notes: "10 min. Slide à faire noter mot pour mot.\n\nInsister lourdement sur la condition 3 : c'est celle qui fait tomber le plus de dossiers. Un dirigeant qui a été salarié 3 ans en début de carrière a des points Agirc-Arrco. S'il ne les a pas liquidés, il n'est pas en cumul intégral, quels que soient son âge et sa durée d'assurance.\n\nLe cas des pensions étrangères est réel et fréquent en Franche-Comté : proximité de la Suisse. Un stagiaire ayant travaillé en Suisse doit avoir liquidé sa rente AVS. Le signaler explicitement, c'est un point local très pertinent à Dole / Étupes.",
});

L.compareSlide(pres, {
  kicker: "Condition 2",
  title: "Le taux plein : deux chemins pour y arriver",
  left: {
    title: "Par la durée d'assurance",
    color: C.orange,
    dense: true,
    items: [
      "Vous avez validé le nombre de trimestres requis pour votre génération.",
      "Après la suspension : 170 trimestres pour la génération 1964, 170 ou 171 pour 1965, jusqu'à 172 pour les générations suivantes.",
      "Tous régimes de base confondus : les trimestres se cumulent d'un régime à l'autre.",
      "Comptent aussi les trimestres assimilés : chômage indemnisé, maladie, maternité, service national.",
      "Le rachat de trimestres est possible — mais son intérêt doit être calculé, il est rarement rentable après 64 ans.",
    ],
  },
  right: {
    title: "Par l'âge : 67 ans",
    color: C.violet,
    dense: true,
    items: [
      "À 67 ans, le taux plein est acquis AUTOMATIQUEMENT.",
      "Peu importe le nombre de trimestres validés : la décote est annulée.",
      "C'est la porte de sortie des carrières incomplètes : reprise tardive d'activité, expatriation, longues interruptions.",
      "Attention : taux plein ne veut pas dire pension complète. Le montant reste proportionnel aux trimestres validés.",
      "Vous n'aurez pas de décote, mais vous aurez une pension calculée au prorata.",
    ],
  },
  foot: "Cette nuance sauve des dossiers : à 67 ans, un dirigeant à carrière courte accède au cumul INTÉGRAL, donc sans plafond de revenus.",
  notes: "8 min. Point technique mais très rentable.\n\nLa distinction « taux plein » ≠ « pension complète » est mal comprise et pourtant décisive :\n- Taux plein = pas de décote sur le coefficient.\n- Pension complète = tous les trimestres validés.\n\nUn dirigeant de 67 ans avec 120 trimestres a le taux plein (donc accès au cumul intégral, sans plafond de revenus) mais une pension au prorata de 120/172.\n\nC'est souvent la meilleure nouvelle de la journée pour les stagiaires à carrière heurtée : attendre 67 ans débloque le cumul sans plafond.",
});

L.alertSlide(pres, {
  kicker: "Le piège numéro un",
  title: "La pension oubliée fait tomber tout l'édifice",
  body: "« Liquider toutes ses pensions » veut dire toutes. Un régime oublié, et vous n'êtes pas en cumul intégral — même si vous avez 68 ans et une carrière complète.",
  color: C.red,
  points: [
    "Les trimestres de début de carrière : job étudiant, apprentissage, CDD, service national.",
    "Les régimes complémentaires dormants : Agirc-Arrco d'un emploi salarié d'il y a trente ans.",
    "Les régimes de professions antérieures : CIPAV, CARPIMKO, CAVEC, MSA…",
    "Les pensions étrangères : AVS suisse, régimes allemand, luxembourgeois, belge.",
    "La solution : le relevé de carrière tous régimes sur info-retraite.fr, à demander AVANT toute décision.",
  ],
  notes: "8 min. Slide à haute valeur ajoutée.\n\nRaconter le mécanisme : la caisse qui verse la pension principale ne sait pas forcément qu'il existe un régime dormant ailleurs. Le contrôle intervient parfois des années plus tard, avec demande de remboursement d'indus.\n\nÀ Dole / Étupes, insister sur la Suisse : beaucoup de carrières frontalières. Une rente AVS non liquidée bloque le cumul intégral.\n\nCONSEIL OPÉRATIONNEL À DONNER : avant toute décision, se connecter sur info-retraite.fr, télécharger le relevé de carrière tous régimes, et vérifier ligne par ligne. C'est gratuit et cela prend dix minutes. C'est le premier item du plan d'action.",
});

L.caseSlide(pres, {
  num: "1",
  title: "Martine, présidente de SAS : cumul intégral et zéro plafond",
  profil: "Martine, 65 ans, née en 1961.\nPrésidente d'une SAS de conseil en organisation.\nCarrière complète : 172 trimestres validés.",
  situation: "Elle souhaite continuer à diriger son entreprise tout en percevant sa retraite. Sa rémunération de présidente est de 5 500 € bruts par mois. Elle a été salariée pendant 15 ans avant de créer sa société : elle a donc des droits Agirc-Arrco et CNAV au titre de ces deux périodes, puisqu'en tant que présidente de SAS elle est assimilée salariée.",
  analyse: [
    "Âge légal atteint : oui, 65 ans, née en 1961.",
    "Taux plein : oui, 172 trimestres validés, durée requise atteinte.",
    "Liquidation totale : elle doit liquider CNAV + Agirc-Arrco, pour les deux périodes.",
    "Mais elle relève du régime général : la cessation d'activité salariée est OBLIGATOIRE pour liquider.",
    "Elle doit donc mettre fin à son mandat rémunéré de présidente, puis le reprendre.",
  ],
  reponse: "Cumul intégral accessible, sans aucun plafond de revenus — mais au prix d'un montage juridique préalable : rupture du mandat rémunéré, liquidation, puis nouveau mandat. À préparer 4 à 6 mois à l'avance avec l'expert-comptable et le conseil juridique.",
  accent: C.orange,
  notes: "12 min avec échanges.\n\nCas emblématique du président de SAS. Le point dur n'est pas l'éligibilité — elle est acquise — c'est le formalisme.\n\nDétailler le montage : PV d'assemblée constatant la fin du mandat rémunéré, période de cessation effective, demande de liquidation, puis nouveau mandat (rémunéré ou non). L'ordre chronologique compte : la caisse vérifie la réalité de la cessation.\n\nQuestion qui vient toujours : « combien de temps doit durer la cessation ? » — Il n'y a pas de durée légale unique, mais la cessation doit être RÉELLE et vérifiable. Renvoyer à la caisse pour la validation du montage, et ne jamais improviser : c'est typiquement le sujet du SAV 12 mois.\n\nVariante à évoquer : si Martine ne veut pas de ce montage, elle peut transformer sa SAS en SARL et devenir gérante majoritaire — là, aucune cessation n'est exigée. Mais la transformation a un coût et des conséquences sociales lourdes. À arbitrer.",
});

L.caseSlide(pres, {
  num: "2",
  title: "Bernard, gérant majoritaire : le trimestre qui coûte 18 000 €",
  profil: "Bernard, 64 ans et 2 mois, né en 1962.\nGérant majoritaire d'une SARL de travaux publics.\nRevenu de gérance : 4 200 € nets par mois.",
  situation: "Bernard a liquidé sa retraite SSI et son régime complémentaire RCI l'an dernier. Il continue à diriger sa SARL et perçoit sa pension. Il pense être en cumul intégral. Or, entre 1981 et 1984, il a été salarié dans une entreprise de maçonnerie. Il a donc des points Agirc-Arrco qu'il n'a jamais liquidés — il l'avait oublié.",
  analyse: [
    "Âge légal atteint : oui. Taux plein : oui, carrière longue et complète.",
    "Liquidation totale : NON. Les points Agirc-Arrco de 1981-1984 dorment.",
    "Il est donc en cumul PLAFONNÉ, et non intégral.",
    "Plafond SSI applicable : 50 % du PASS, soit 24 030 € de revenu net par an.",
    "Son revenu réel : 50 400 € nets. Dépassement de 26 370 €.",
  ],
  reponse: "L'écrêtement porte sur le dépassement : sa pension de base est réduite à due concurrence, jusqu'à suspension totale. Sur une pension de base de 1 500 €/mois, la perte peut atteindre 18 000 € sur l'année. La solution : liquider immédiatement les points Agirc-Arrco dormants — le stock de points, même faible, débloque le cumul intégral.",
  accent: C.red,
  notes: "12 min. Le cas le plus marquant de la matinée.\n\nMontrer que le montant des droits dormants est SANS IMPORTANCE : ce n'est pas leur valeur qui compte, c'est le fait qu'ils ne soient pas liquidés. Trois années de salariat en 1981 peuvent représenter 40 € de pension par mois — mais elles bloquent l'accès au cumul intégral.\n\nDérouler le calcul au tableau, lentement :\n- Revenu net annuel : 4 200 × 12 = 50 400 €\n- Plafond : 24 030 €\n- Dépassement : 26 370 €\n- Pension de base annuelle : 1 500 × 12 = 18 000 €\n- L'écrêtement absorbe la totalité de la pension de base.\n\nMessage à faire passer : le relevé de carrière n'est pas une formalité administrative, c'est un outil de sécurisation financière. Coût : 0 €. Enjeu ici : 18 000 €/an.\n\nAttention à une nuance : l'écrêtement porte sur la pension de BASE, la complémentaire suit ses propres règles. Ne pas surpromettre, renvoyer au cas par cas.",
});

/* ──────────────────────────────────── MODULE 3 : le cumul plafonné ── */

L.moduleSlide(pres, {
  num: "3", title: "Le cumul emploi-retraite plafonné",
  duration: "1 h 15",
  points: [
    "Quand bascule-t-on en plafonné — et pourquoi c'est rarement un choix",
    "Le plafond du salarié : 2 916,85 € ou votre dernier salaire, au plus favorable",
    "Le plafond de l'indépendant : 24 030 € de revenu net, doublé en ZRR et QPV",
    "La mécanique de l'écrêtement, et la règle qui fait le plus mal : cotiser sans acquérir",
  ],
  notes: "Module technique. L'objectif n'est pas que les stagiaires sachent calculer eux-mêmes au centime, mais qu'ils sachent RECONNAÎTRE qu'ils sont concernés et qu'ils aillent voir leur caisse.\n\nBien distinguer les deux plafonds : salarié (mensuel, en brut) et indépendant (annuel, en net). Ce n'est ni la même base, ni la même périodicité.",
});

L.cardsSlide(pres, {
  kicker: "Le basculement",
  title: "Trois situations qui vous font basculer en plafonné",
  cols: 3,
  cards: [
    { n: "1", t: "Départ avant l'âge légal", color: C.orange,
      d: "Carrière longue, incapacité permanente, inaptitude au travail : vous êtes parti tôt, donc avant l'âge légal. La condition d'âge n'est pas remplie." },
    { n: "2", t: "Pension avec décote", color: C.violet,
      d: "Vous n'avez pas la durée d'assurance requise et vous n'avez pas 67 ans. Vous partez avec minoration : pas de taux plein, donc pas de cumul intégral." },
    { n: "3", t: "Liquidation incomplète", color: C.red,
      d: "Le cas de Bernard. Un régime, même minuscule, non liquidé. C'est de loin la cause la plus fréquente, et la seule qui se corrige facilement." },
  ],
  foot: "Les deux premières situations sont subies. La troisième se répare en quelques semaines : c'est là qu'il faut chercher en priorité.",
  notes: "6 min.\n\nLe message d'action : sur les trois causes, une seule est réparable rapidement. Donc la première chose à vérifier dans un dossier, c'est toujours la complétude de la liquidation.\n\nSi un stagiaire est dans le cas 1 ou 2, il n'y a pas de miracle : il faut alors optimiser DANS le cumul plafonné (moduler le revenu, arbitrer sur la forme de rémunération) ou attendre 67 ans.",
});

L.statsSlide(pres, {
  kicker: "Plafond salarié",
  title: "Si vous êtes salarié ou assimilé salarié",
  lead: "Le total « pensions + revenus d'activité » ne doit pas dépasser le plus FAVORABLE des deux montants suivants :",
  stats: [
    { v: "2 916,85 €", k: "160 % du SMIC brut mensuel", small: true, color: C.orange,
      d: "Montant fixe pour 2026, revalorisé chaque 1er janvier avec le SMIC. C'est le plancher garanti : personne ne peut avoir un plafond inférieur." },
    { v: "Votre moyenne", k: "des 3 derniers mois d'activité", small: true, color: C.violet,
      d: "Moyenne mensuelle des salaires perçus au cours du mois de cessation de votre dernière activité salariée et des 2 mois civils précédents." },
  ],
  foot: "C'est le montant le plus élevé des deux qui s'applique. Un cadre bien rémunéré aura donc un plafond bien supérieur à 2 916,85 €.",
  notes: "8 min.\n\nExpliquer pourquoi il y a deux termes : le législateur a voulu que le plafond ne pénalise pas ceux qui avaient des revenus élevés. D'où le mécanisme « au plus favorable ».\n\nExemple à donner au tableau : un cadre à 6 000 € bruts sur ses 3 derniers mois aura un plafond de 6 000 €, pas de 2 916,85 €. Alors qu'un salarié à 2 200 € bénéficiera du plancher à 2 916,85 €.\n\nAttention au détail de la base : ce sont les salaires du mois de cessation ET des deux mois civils précédents. Si le dernier mois est un mois partiel, cela tire la moyenne vers le bas — un point à surveiller au moment de fixer la date de cessation.",
});

L.statsSlide(pres, {
  kicker: "Plafond indépendant",
  title: "Si vous relevez de la Sécurité sociale des indépendants",
  lead: "Le plafond porte sur votre REVENU NET d'activité, apprécié sur l'année civile — pas sur votre chiffre d'affaires.",
  stats: [
    { v: "24 030 €", k: "50 % du PASS — cas général", small: true, color: C.orange,
      d: "Revenu net annuel maximal en cumul plafonné, pour un artisan, un commerçant ou un gérant majoritaire relevant de la SSI." },
    { v: "48 060 €", k: "100 % du PASS — ZRR et QPV", small: true, color: C.green,
      d: "Le plafond est doublé si l'activité est reprise en zone de revitalisation rurale ou en quartier prioritaire de la politique de la ville." },
  ],
  foot: "En micro-entreprise, le revenu net s'entend APRÈS abattement forfaitaire. Le chiffre d'affaires autorisé est donc bien supérieur au plafond.",
  notes: "8 min.\n\nDeux pièges à désamorcer :\n\n1. REVENU NET ≠ CHIFFRE D'AFFAIRES. C'est la confusion la plus fréquente chez les micro-entrepreneurs. Le calcul détaillé est sur la slide suivante.\n\n2. Le zonage ZRR / QPV mérite une vérification systématique : une partie du Jura et du Doubs est classée. Renvoyer au site de l'ANCT pour vérifier l'adresse exacte de l'établissement. C'est un doublement de plafond, cela vaut le quart d'heure de vérification.\n\nProcédure en cas de dépassement (à annoncer) : la caisse notifie, le retraité a UN MOIS pour présenter ses observations, la suspension prend effet au premier jour du mois suivant la notification, proportionnellement au dépassement.",
});

L.tableSlide(pres, {
  kicker: "Micro-entreprise",
  title: "Du plafond de revenu au chiffre d'affaires réellement autorisé",
  lead: "Le plafond de 24 030 € porte sur le revenu net. En micro, on l'obtient après application de l'abattement forfaitaire.",
  head: ["Nature de l'activité", "Abattement", "Plafond de revenu net", "CA annuel autorisé (arrondi)"],
  colW: [3.6, 2.0, 3.0, 3.72],
  rows: [
    ["Vente de marchandises, hébergement", "71 %", "24 030 €", { text: "≈ 82 800 €", bold: true, color: C.green }],
    ["Prestations de services BIC (artisanal, commercial)", "50 %", "24 030 €", { text: "≈ 48 060 €", bold: true, color: C.green }],
    ["Activités libérales BNC", "34 %", "24 030 €", { text: "≈ 36 400 €", bold: true, color: C.green }],
  ],
  accent: C.violet,
  foot: "Rappel : ces montants restent soumis aux plafonds propres au régime micro — 188 700 € en vente, 77 700 € en services et BNC.",
  notes: "10 min. Slide très demandée par les micro-entrepreneurs.\n\nDérouler le calcul de la 3e ligne au tableau, c'est le plus parlant :\n- Abattement BNC : 34 % → le revenu net représente 66 % du CA.\n- Plafond de revenu net : 24 030 €\n- CA autorisé : 24 030 / 0,66 = 36 409 €\n\nDonc une consultante en micro-BNC en cumul plafonné peut facturer environ 36 400 € par an sans être écrêtée. Beaucoup pensaient être limitées à 24 030 € de CA : c'est 50 % de marge en plus.\n\nRappeler aussi les taux de cotisations 2026 : 22,4 % pour les services BIC, 24,6 % pour le libéral. Et le fait qu'un dépassement des plafonds du régime micro pendant deux années consécutives fait perdre le régime micro-social.",
});

L.alertSlide(pres, {
  kicker: "La règle qui fait le plus mal",
  title: "En cumul plafonné, vous cotisez sans rien acquérir",
  body: "Vous payez les cotisations retraite sur vos revenus d'activité, comme n'importe quel actif. Mais ces cotisations n'ouvrent aucun droit nouveau.",
  color: C.violetDeep,
  points: [
    "Cotisations vieillesse prélevées : oui, au taux normal, sans réduction.",
    "Droits acquis en contrepartie : aucun. Pas de trimestre, pas de point, pas de seconde pension.",
    "La seconde pension est réservée au cumul INTÉGRAL : c'est sa contrepartie exclusive.",
    "Conséquence directe : entre plafonné et intégral, l'écart n'est pas seulement l'écrêtement, c'est aussi la perte des droits nouveaux.",
  ],
  notes: "6 min. Slide qui provoque toujours une réaction.\n\nC'est le point qui fait basculer beaucoup de décisions : passer de plafonné à intégral, ce n'est pas seulement récupérer sa pension écrêtée, c'est aussi ouvrir l'acquisition de droits nouveaux.\n\nQuestion fréquente : « c'est légal de cotiser à fonds perdus ? » — Oui. Les cotisations vieillesse ont un caractère de solidarité, elles ne sont pas assimilables à une épargne individuelle. Ne pas s'engager dans un débat politique : constater la règle et en tirer les conséquences pratiques.\n\nEnchaîner directement sur le module 4 : « justement, voyons ce que le cumul intégral, lui, permet d'acquérir ».",
});

/* ──────────────────────────────────── MODULE 4 : la seconde pension ── */

L.moduleSlide(pres, {
  num: "4", title: "La seconde pension",
  duration: "45 min",
  color: C.violet,
  points: [
    "La rupture de 2023 : travailler à la retraite crée enfin des droits",
    "Les conditions d'ouverture, et le délai de 6 mois chez le dernier employeur",
    "Le plafond de 2 403 € par an : ce que cela représente vraiment",
    "Ce que la seconde pension n'est pas : les cinq limites à connaître",
  ],
  notes: "Module court mais très apprécié. C'est une des rares bonnes nouvelles du dossier retraite.\n\nAttention à ne pas survendre : le plafond de 2 403 €/an est modeste. Le rôle du formateur est de donner l'ordre de grandeur exact pour que personne ne construise une stratégie dessus.",
});

L.compareSlide(pres, {
  kicker: "Avant / après",
  title: "Ce que la réforme de 2023 a changé",
  left: {
    title: "Avant le 1er septembre 2023",
    color: C.gray,
    items: [
      "Le retraité qui reprenait une activité cotisait comme tout le monde.",
      "Ces cotisations n'ouvraient AUCUN droit nouveau, quel que soit le régime de cumul.",
      "La pension liquidée était définitive : rien ne pouvait plus la faire augmenter.",
      "Le dispositif était perçu — à juste titre — comme une cotisation à fonds perdus.",
    ],
  },
  right: {
    title: "Depuis le 1er septembre 2023",
    color: C.violet,
    items: [
      "En cumul INTÉGRAL, les cotisations versées créent des droits nouveaux.",
      "Ces droits donnent lieu à une SECONDE pension de retraite de base.",
      "Elle est liquidée une seule fois, définitivement, à l'arrêt de l'activité.",
      "Certaines caisses complémentaires ouvrent également des droits — à vérifier régime par régime.",
      "En cumul PLAFONNÉ, rien ne change : toujours aucun droit acquis.",
    ],
  },
  foot: "La contrepartie du cumul intégral n'est donc pas seulement l'absence de plafond : c'est aussi l'acquisition de droits.",
  notes: "8 min.\n\nSituer la réforme : loi du 14 avril 2023 portant réforme des retraites, entrée en vigueur au 1er septembre 2023. C'est la contrepartie donnée aux seniors en échange du recul de l'âge légal.\n\nBien marquer que cela ne concerne QUE le cumul intégral. C'est le levier d'argumentation pour convaincre un stagiaire de régulariser une liquidation incomplète.",
});

L.listSlide(pres, {
  kicker: "Le mode d'emploi",
  title: "Comment la seconde pension se construit",
  items: [
    { t: "Vous êtes en cumul intégral", d: "Les 3 conditions du module 2 sont réunies. C'est le préalable absolu : en plafonné, aucun droit ne se constitue." },
    { t: "Vous reprenez ou poursuivez une activité", d: "Salariée ou non salariée. Vous cotisez normalement, sur vos revenus réels." },
    { t: "Le délai de 6 mois si c'est chez votre dernier employeur", d: "Si vous reprenez chez le même employeur, les droits ne se constituent que si la reprise intervient au moins 6 mois après la liquidation. Si vous n'avez jamais cessé, ce délai ne s'applique pas." },
    { t: "Vous liquidez cette seconde pension à l'arrêt définitif", d: "Une seule fois, définitivement. Il n'y a pas de troisième pension : ce qui est cotisé après est de nouveau perdu." },
  ],
  accent: C.violet,
  foot: "Le délai de 6 mois disparaît au 1er janvier 2027 pour les nouvelles liquidations — nous y reviendrons au module 7.",
  notes: "8 min.\n\nLe délai de 6 mois est mal compris. Préciser :\n- Il ne s'applique QUE si vous reprenez chez votre DERNIER employeur.\n- Il ne conditionne pas le droit au cumul lui-même, mais l'ACQUISITION DE DROITS NOUVEAUX.\n- Si vous avez poursuivi votre activité sans interruption parce qu'aucune cessation n'était exigée (cas du gérant majoritaire), vous n'êtes pas concerné.\n\nSignaler aussi : la seconde pension se liquide UNE SEULE FOIS. Un retraité qui reprend, liquide sa seconde pension, puis reprend encore, ne se constitue plus rien.",
});

L.cardsSlide(pres, {
  kicker: "Les limites",
  title: "Ce que la seconde pension n'est pas",
  cols: 3,
  cards: [
    { n: "1", t: "Elle est plafonnée", color: C.orange,
      d: "5 % du PASS, soit 2 403 € bruts par an en 2026. Environ 200 € par mois, quel que soit le montant cotisé au-delà." },
    { n: "2", t: "Elle est nue", color: C.violet,
      d: "Aucune majoration : pas de bonification pour enfants, pas d'accessoires, pas de bonifications de carrière." },
    { n: "3", t: "Elle est unique", color: C.orange,
      d: "Liquidée une fois, définitivement. Une reprise ultérieure d'activité ne créera plus rien." },
    { n: "4", t: "Elle ne rouvre pas le taux plein", color: C.violet,
      d: "Elle ne corrige pas une décote subie sur la première pension. Ce qui est liquidé est figé." },
    { n: "5", t: "Elle dépend du régime", color: C.gray,
      d: "Le plafond de 2 403 € vise la retraite de base. Les règles complémentaires varient : vérifier auprès de chaque caisse." },
    { n: "6", t: "Mais elle change au 01/01/2027", color: C.green,
      d: "Pour les 67 ans et plus, la réforme supprime ce plafond. Les droits acquis en cumul seront intégralement liquidables." },
  ],
  foot: "Ordre de grandeur : 200 € par mois. Utile, jamais structurant. Ne construisez pas votre stratégie retraite dessus.",
  notes: "8 min. Slide d'honnêteté intellectuelle — très importante pour la crédibilité.\n\nLe formateur doit être clair : la seconde pension est un bonus, pas un levier. 2 403 € par an, c'est un peu plus de 200 € par mois avant impôt et prélèvements sociaux.\n\nMais le point 6 est majeur : pour les 67 ans et plus, la réforme du 1er janvier 2027 SUPPRIME ce plafond. C'est l'une des rares améliorations du texte. À relier au module 7.",
});

L.caseSlide(pres, {
  num: "3",
  title: "Chantal, consultante en micro-BNC : ce que le cumul rapporte vraiment",
  profil: "Chantal, 67 ans.\nAncienne cadre RH, retraitée depuis 2 ans.\nMicro-entreprise BNC de conseil en recrutement.",
  situation: "Elle a liquidé l'ensemble de ses pensions à 65 ans, avec une carrière complète : elle est en cumul intégral, sans plafond. Elle facture 34 000 € de chiffre d'affaires par an en micro-BNC. Elle veut savoir ce que ces cotisations lui rapportent en droits nouveaux, et si cela vaut la peine de continuer trois ans de plus.",
  analyse: [
    "Cumul intégral confirmé : aucun plafond de revenus, elle peut facturer librement.",
    "Cotisations micro-BNC : 24,6 % de 34 000 €, soit environ 8 360 € par an.",
    "Elle acquiert des droits nouveaux, plafonnés à 5 % du PASS.",
    "Seconde pension maximale : 2 403 € bruts par an, soit environ 200 € par mois.",
    "Ce plafond est atteint bien avant trois ans de cotisation à ce niveau.",
  ],
  reponse: "Le cumul intégral lui laisse une liberté totale de revenus : c'est là qu'est la vraie valeur. La seconde pension, elle, plafonne vite — environ 200 € par mois. Elle doit décider en fonction de son revenu d'activité, pas de ses droits futurs. Et si elle poursuit au-delà du 1er janvier 2027, elle bénéficiera de la suppression du plafond pour les 67 ans et plus.",
  accent: C.violet,
  notes: "10 min.\n\nCas construit pour recadrer les attentes. Chantal cotise 8 360 € par an pour acquérir au maximum 2 403 € de rente annuelle — le retour sur investissement en tant que tel est mauvais, et il faut le dire.\n\nMais ce n'est pas le bon raisonnement : elle ne travaille pas pour ses droits futurs, elle travaille pour son revenu présent, que le cumul intégral rend illimité. Les droits nouveaux sont un supplément, pas une justification.\n\nAttention à ne pas être approximatif sur le point de la suppression du plafond au 01/01/2027 : la réforme s'applique aux PREMIÈRES liquidations prenant effet à partir de cette date. Chantal ayant liquidé en 2024, sa situation relève des règles actuelles. Le renvoyer au SAV et aux décrets d'application — c'est un point que seuls les décrets trancheront précisément.",
});

/* ──────────────────────────────── MODULE 5 : alternatives et voisins ── */

L.moduleSlide(pres, {
  num: "5", title: "Les dispositifs voisins",
  duration: "45 min",
  points: [
    "La retraite progressive : partir à 60 ans sans liquider",
    "Cumul ou retraite progressive : le comparatif décisionnel",
    "Les revenus qui n'entrent pas dans le plafond : dividendes, loyers, droits d'auteur",
    "L'arbitrage rémunération / dividendes du dirigeant en cumul plafonné",
  ],
  notes: "Module qui ouvre les options. Beaucoup de dirigeants ne connaissent que le cumul et découvrent la retraite progressive.\n\nSurtout, le point sur les dividendes est stratégiquement le plus utile de la journée pour un gérant en cumul plafonné.",
});

L.statsSlide(pres, {
  kicker: "Retraite progressive",
  title: "Travailler moins, toucher une fraction, continuer à cotiser",
  lead: "Depuis le 1er septembre 2025, l'âge d'accès est fixé de façon autonome à 60 ans, quelle que soit votre génération.",
  stats: [
    { v: "60 ans", k: "âge d'accès", color: C.orange,
      d: "Autonome depuis 2025 : il ne suit plus le relèvement de l'âge légal et n'est pas affecté par la suspension." },
    { v: "150", k: "trimestres requis", color: C.violet,
      d: "Soit 37,5 années validées, tous régimes de base confondus. Condition d'accès au dispositif." },
    { v: "40 – 80 %", k: "quotité de travail", color: C.orange,
      d: "L'activité doit rester comprise dans cette fourchette. 50 à 90 % pour les fonctionnaires." },
  ],
  foot: "La fraction de pension versée correspond à la part NON travaillée : à 60 % d'activité, vous percevez 40 % de votre pension.",
  notes: "8 min.\n\nLa retraite progressive est le grand oublié des dispositifs. Ses atouts pour un dirigeant :\n- On ne liquide pas : la pension définitive continue de se construire, sur une base revalorisée.\n- On garde la main sur l'entreprise en réduisant la charge.\n- L'accès est ouvert dès 60 ans, bien avant l'âge légal.\n\nSa contrainte : la quotité de travail doit être mesurable et réduite. Pour un gérant majoritaire, la quotité s'apprécie sur les revenus, pas sur un contrat de travail — c'est un point technique à faire valider par la caisse.\n\nCalcul de la fraction : elle est inversement proportionnelle à la quotité travaillée. 60 % travaillé → 40 % de pension. 80 % → 20 %. 50 % → 50 %.",
});

L.compareSlide(pres, {
  kicker: "Décider",
  title: "Cumul emploi-retraite ou retraite progressive ?",
  left: {
    title: "Choisissez le cumul si…",
    color: C.orange,
    dense: true,
    items: [
      "Vous voulez maintenir votre niveau d'activité, voire l'augmenter.",
      "Vous remplissez les 3 conditions du cumul intégral : pas de plafond.",
      "Vous voulez percevoir 100 % de votre pension immédiatement.",
      "Votre pension définitive est déjà à son maximum : rien à gagner à attendre.",
      "Vous voulez sécuriser le régime actuel avant le 1er janvier 2027.",
    ],
  },
  right: {
    title: "Choisissez la progressive si…",
    color: C.violet,
    dense: true,
    items: [
      "Vous voulez lever le pied sans arrêter : 40 à 80 % d'activité.",
      "Vous avez 60 ans mais pas encore l'âge légal ni le taux plein.",
      "Votre carrière est incomplète : continuer à cotiser améliore réellement la pension définitive.",
      "Vous préparez une transmission progressive de l'entreprise.",
      "Vous voulez tester la réduction d'activité avant de décider.",
    ],
  },
  foot: "Les deux ne sont pas exclusifs dans le temps : on peut faire de la retraite progressive, puis liquider et passer en cumul.",
  notes: "10 min. Slide décisionnelle — la faire commenter par le groupe.\n\nLe cas typique de la retraite progressive : un dirigeant de 61 ans, carrière incomplète, qui veut préparer sa transmission sur 3 ans. Il réduit à 60 %, touche 40 % de pension, continue de cotiser sur 60 % — et sa pension définitive sera meilleure.\n\nLe cas typique du cumul : un dirigeant de 65 ans, carrière complète, qui n'a rien à gagner à attendre et veut sécuriser le régime actuel avant 2027.\n\nDemander au groupe : « qui se reconnaît dans la colonne de gauche ? à droite ? » — cela structure l'atelier de fin de journée.",
});

L.caseSlide(pres, {
  num: "4",
  title: "Karim, gérant de SARL : la transmission en trois ans",
  profil: "Karim, 61 ans, né en 1965 (mai).\nGérant majoritaire d'une SARL de menuiserie, 8 salariés.\n163 trimestres validés — carrière incomplète.",
  situation: "Il veut transmettre son entreprise à son second, sur trois ans, sans partir brutalement. Sa pension actuelle serait décotée : il lui manque 8 trimestres pour atteindre les 171 requis pour sa génération après la suspension. Il ne veut pas liquider dans ces conditions.",
  analyse: [
    "Cumul intégral : impossible aujourd'hui, il n'a ni l'âge légal ni le taux plein.",
    "Cumul plafonné : possible mais peu attractif, avec décote définitive sur la pension.",
    "Retraite progressive : accessible dès 60 ans, il a 163 trimestres (> 150 requis).",
    "Il réduit son activité à 60 %, perçoit 40 % de sa pension, et continue à cotiser.",
    "En trois ans, il valide 12 trimestres supplémentaires : il dépasse les 171 requis.",
  ],
  reponse: "La retraite progressive est la bonne réponse. Elle lui donne un revenu complémentaire immédiat, elle finance la montée en compétence de son successeur, et elle lui permet d'atteindre le taux plein avant de liquider définitivement. Il liquidera à 64 ans sans décote, et pourra alors basculer en cumul intégral.",
  accent: C.orange,
  notes: "12 min. Cas de synthèse du module 5.\n\nPoint technique à souligner : en retraite progressive, on continue à valider des trimestres sur l'activité maintenue. C'est ce qui permet de rattraper une carrière incomplète — c'est le principal avantage face au cumul plafonné, où la décote est définitive.\n\nPoint de vigilance à annoncer : pour un gérant majoritaire, la quotité d'activité ne se mesure pas en heures mais en revenus. Le montage doit être validé en amont par la caisse. Ne jamais réduire sa rémunération sans accord préalable.\n\nSéquence complète à faire visualiser : retraite progressive de 61 à 64 ans → liquidation au taux plein → cumul intégral ensuite. C'est un parcours en trois temps, très adapté aux transmissions.",
});

L.cardsSlide(pres, {
  kicker: "Levier souvent ignoré",
  title: "Les revenus qui n'entrent PAS dans le plafond de cumul",
  cols: 3,
  cards: [
    { n: "1", t: "Les dividendes", color: C.green,
      d: "Ce sont des revenus de capitaux mobiliers, pas des revenus d'activité. Ils n'entrent pas dans le calcul du plafond de cumul." },
    { n: "2", t: "Les revenus fonciers", color: C.green,
      d: "Loyers d'immeubles détenus en direct ou via une SCI à l'IR : hors champ du plafond de cumul." },
    { n: "3", t: "Les revenus de placements", color: C.green,
      d: "Intérêts, plus-values mobilières, rachats d'assurance-vie, rentes de PER : sans incidence sur le plafond." },
  ],
  foot: "Attention : le gérant majoritaire de SARL voit la fraction de dividendes excédant 10 % du capital social requalifiée en revenus d'activité soumis à cotisations.",
  notes: "10 min. Slide à fort intérêt pratique pour un dirigeant en cumul plafonné.\n\nLe levier : si vous êtes plafonné à 24 030 € de revenu net, vous avez intérêt à réduire votre rémunération de gérance et à privilégier la distribution de dividendes — les dividendes n'étant pas des revenus d'activité au sens du plafond.\n\nMAIS trois avertissements impératifs :\n1. Pour un gérant MAJORITAIRE de SARL, la part de dividendes qui excède 10 % du capital social + primes d'émission + apports en compte courant est requalifiée en revenus d'activité et soumise à cotisations SSI. Le levier est donc borné.\n2. Le président de SAS n'a pas cette limite — ses dividendes restent des RCM.\n3. Le « tout dividendes » est à proscrire : la fiscalité (flat tax à 30 %) et l'absence de constitution de droits sociaux en font une stratégie coûteuse à long terme.\n\nConclusion à donner : ce levier se calcule au cas par cas avec l'expert-comptable. Ne jamais l'appliquer mécaniquement. C'est typiquement un sujet du SAV 12 mois.",
});

/* ────────────────────────── MODULE 6 : statuts, fiscalité, cotisations ── */

L.moduleSlide(pres, {
  num: "6", title: "Fiscalité et cotisations du cumul",
  duration: "45 min",
  color: C.violet,
  points: [
    "Comment la pension et les revenus d'activité sont imposés",
    "CSG, CRDS, CASA : les quatre taux et leurs seuils",
    "L'effet de seuil en N+2 : le piège de la première année de cumul",
    "Les cotisations que vous continuez à payer, et à quoi elles servent",
  ],
  notes: "Module technique et un peu ingrat, mais nécessaire : c'est là que se joue l'écart entre le brut annoncé et le net perçu.\n\nLe message à retenir : le cumul augmente le revenu fiscal de référence, et cette hausse se paie deux ans plus tard sur le taux de CSG de la pension.",
});

L.tableSlide(pres, {
  kicker: "Prélèvements sociaux",
  title: "La CSG sur votre pension : quatre situations possibles",
  lead: "Le taux dépend de votre revenu fiscal de référence et de votre nombre de parts. Il est recalculé chaque année.",
  head: ["Situation", "Taux de CSG", "CRDS + CASA", "Part de CSG déductible"],
  colW: [3.9, 2.4, 2.6, 3.22],
  rows: [
    ["Exonération (RFR ≤ 13 047 €, 1 part)", { text: "0 %", bold: true, color: C.green }, "Aucune", "—"],
    ["Taux réduit", { text: "3,8 %", bold: true }, "CRDS 0,5 %", "3,8 % — intégralement déductible"],
    ["Taux médian", { text: "6,6 %", bold: true }, "CRDS 0,5 % + CASA 0,3 %", "4,2 %"],
    ["Taux normal", { text: "8,3 %", bold: true, color: C.red }, "CRDS 0,5 % + CASA 0,3 %", "5,9 %"],
  ],
  accent: C.violet,
  foot: "Seuil d'exonération 2026 pour une personne seule en métropole : revenu fiscal de référence inférieur ou égal à 13 047 €.",
  notes: "8 min.\n\nExpliquer la logique : la CSG sur les pensions est progressive par tranches de RFR, contrairement à la CSG sur les salaires qui est à taux unique.\n\nL'écart entre 0 % et 8,3 % + 0,8 % représente plus de 9 points de pension nette. Sur une pension de 2 000 € par mois, c'est 180 € mensuels.\n\nDonner le réflexe : les seuils sont revalorisés chaque année et dépendent du nombre de parts. Les vérifier sur son avis d'imposition, rubrique « revenu fiscal de référence ».",
});

L.alertSlide(pres, {
  kicker: "Le piège de la première année",
  title: "Votre cumul de 2026 se paiera sur votre pension de 2028",
  body: "Le taux de CSG appliqué à votre pension est déterminé à partir du revenu fiscal de référence de l'avant-dernière année. Une première année de cumul gonfle le RFR — et le taux suit, deux ans plus tard.",
  color: C.violetDeep,
  points: [
    "2026 : vous démarrez votre activité en cumul. Votre RFR augmente fortement.",
    "2027 : vous déclarez ces revenus. Le RFR 2026 est celui qui sera retenu.",
    "2028 : votre taux de CSG passe par exemple de 6,6 % à 8,3 %. Votre pension NETTE baisse.",
    "Votre pension brute, elle, n'a pas bougé d'un centime. C'est un effet purement fiscal.",
    "À anticiper dans le plan de trésorerie : la bonne surprise de 2026 se paie en 2028.",
  ],
  notes: "8 min. Point que presque personne n'anticipe.\n\nDérouler la chronologie lentement, c'est contre-intuitif : le taux de CSG de l'année N est fondé sur le RFR de N-2.\n\nExemple chiffré à donner : pension de 2 000 € bruts par mois. Passage de 6,6 % à 8,3 % = 1,7 point = 34 € par mois = 408 € par an de perte, sans que la pension brute ait changé.\n\nAjouter : le mécanisme fonctionne dans les deux sens. L'année où l'activité cesse, le RFR redescend, et le taux revient à la baisse — deux ans plus tard là aussi.\n\nConseil à donner : lorsqu'on démarre un cumul en cours d'année, il peut être pertinent de décaler le démarrage sur l'année suivante si l'on est juste sous un seuil. À calculer avec l'expert-comptable.",
});

L.listSlide(pres, {
  kicker: "Récapitulatif",
  title: "Ce que vous payez, ce que vous percevez",
  twoCol: true,
  items: [
    { t: "Pension : impôt sur le revenu", d: "Barème progressif, après abattement de 10 % plafonné. La pension est imposable comme un salaire." },
    { t: "Pension : CSG, CRDS, CASA", d: "0 %, 3,8 %, 6,6 % ou 8,3 % de CSG selon le RFR, plus 0,5 % de CRDS et 0,3 % de CASA au-delà du taux réduit." },
    { t: "Revenus d'activité : impôt sur le revenu", d: "Imposés dans leur catégorie propre — traitements et salaires, BIC, BNC ou rémunération de gérance." },
    { t: "Revenus d'activité : cotisations sociales", d: "Au taux plein, sans abattement lié à l'âge ou au statut de retraité. Vous cotisez comme un actif." },
    { t: "Prélèvement à la source", d: "S'applique aux deux flux. Pensez à ajuster votre taux dès le démarrage du cumul pour éviter une régularisation lourde." },
    { t: "Droits acquis en contrepartie", d: "En cumul intégral : la seconde pension, plafonnée à 2 403 € par an. En cumul plafonné : aucun." },
  ],
  accent: C.violet,
  foot: "Le réflexe à prendre dès le premier mois : signaler le changement de situation à l'administration fiscale pour ajuster le taux de prélèvement à la source.",
  notes: "6 min. Slide de synthèse du module 6.\n\nInsister sur le prélèvement à la source : un dirigeant qui démarre un cumul en septembre et ne modifie pas son taux se retrouve avec une régularisation importante en septembre de l'année suivante. La modification se fait en ligne sur impots.gouv.fr, rubrique « Gérer mon prélèvement à la source ».\n\nC'est un item du plan d'action.",
});

/* ──────────────────────── MODULE 7 : la réforme du 1er janvier 2027 ── */

L.moduleSlide(pres, {
  num: "7", title: "La réforme du 1er janvier 2027",
  duration: "1 h",
  points: [
    "Ce que dit l'article 102 de la LFSS 2026",
    "Le basculement : l'âge remplace le taux plein comme critère central",
    "Les trois tranches d'âge et leurs règles",
    "La clause de sauvegarde : votre fenêtre de décision se ferme le 31 décembre",
  ],
  notes: "MODULE LE PLUS IMPORTANT DE LA JOURNÉE. Prévoir large, quitte à raccourcir le module 6.\n\nC'est ici que la formation produit sa valeur : la décision de liquider ou non avant le 31 décembre 2026.\n\nAVERTISSEMENT À DONNER : les décrets d'application ne sont pas tous publiés au 2 septembre 2026. Les grandes lignes sont fixées par la loi, mais certains montants et modalités seront précisés par décret. Le dire explicitement — c'est une question de déontologie, et c'est ce qui justifie le SAV 12 mois.",
});

L.alertSlide(pres, {
  kicker: "LFSS 2026 — article 102",
  title: "Le cumul emploi-retraite est entièrement refondu",
  body: "Loi n° 2025-1403. S'applique à toute première liquidation de retraite de base prenant effet à compter du 1er janvier 2027. Le critère central n'est plus le taux plein, mais l'âge auquel vous reprenez une activité.",
  points: [
    "Avant l'âge légal : le cumul devient quasiment impossible — écrêtement à 100 % des revenus.",
    "De l'âge légal à 67 ans : cumul plafonné à environ 7 000 € par an, écrêtement de 50 % au-delà.",
    "À partir de 67 ans : cumul libre, sans aucun plafond, avec acquisition de droits nouveaux.",
    "Bonne nouvelle : le plafond de la seconde pension est supprimé pour les 67 ans et plus.",
    "Autre assouplissement : le délai de carence de 6 mois chez le dernier employeur disparaît.",
  ],
  notes: "12 min. Le cœur de la journée.\n\nDérouler lentement. Le changement de philosophie est total : aujourd'hui, si vous avez le taux plein, vous cumulez sans plafond, même à 62 ans. Demain, c'est votre âge qui décide, et le taux plein ne suffit plus.\n\nLe grand perdant : le dirigeant de 62-66 ans à carrière complète qui aurait cumulé sans plafond sous le régime actuel, et qui se retrouvera plafonné à environ 7 000 € par an.\n\nLe gagnant : celui qui a 67 ans passés — plus de plafond du tout, et plus de plafond sur la seconde pension.\n\nRÉPÉTER L'AVERTISSEMENT : montants et modalités précisés par décret. Le seuil d'environ 7 000 € est celui qui ressort des travaux parlementaires ; le décret peut l'ajuster. Ne jamais présenter ce chiffre comme définitif.",
});

L.tableSlide(pres, {
  kicker: "Avant / après",
  title: "Le régime actuel comparé au régime 2027",
  lead: "Pour un même profil, selon que la première liquidation prend effet avant ou après le 1er janvier 2027.",
  head: ["Votre situation", "Régime actuel (liquidation ≤ 31/12/2026)", "Régime 2027 (liquidation ≥ 01/01/2027)"],
  colW: [3.6, 4.3, 4.19],
  rows: [
    ["Avant l'âge légal, sans taux plein",
      "Cumul plafonné : 2 916,85 €/mois ou dernier salaire",
      { text: "Cumul quasi impossible : écrêtement 100 %", color: C.red, bold: true }],
    ["Âge légal atteint, taux plein acquis",
      { text: "Cumul INTÉGRAL : aucun plafond", color: C.green, bold: true },
      { text: "Plafonné à ≈ 7 000 €/an, puis −50 %", color: C.red, bold: true }],
    ["Entre l'âge légal et 67 ans, sans taux plein",
      "Cumul plafonné classique",
      { text: "Plafonné à ≈ 7 000 €/an, puis −50 %", color: C.red, bold: true }],
    ["67 ans et plus",
      "Cumul intégral, seconde pension plafonnée à 2 403 €/an",
      { text: "Cumul libre + seconde pension DÉPLAFONNÉE", color: C.green, bold: true }],
    ["Reprise chez le dernier employeur",
      "Délai de 6 mois pour acquérir des droits",
      { text: "Délai supprimé", color: C.green, bold: true }],
  ],
  foot: "La ligne 2 est la plus lourde de conséquences : c'est le profil du dirigeant de 64-66 ans à carrière complète.",
  notes: "12 min. Slide à laisser affichée longtemps, et à commenter ligne par ligne.\n\nFaire chercher aux stagiaires leur ligne. C'est le moment de bascule de la journée : chacun doit identifier s'il est perdant, gagnant ou neutre.\n\nLa ligne 2 mérite un développement : un dirigeant de 65 ans à carrière complète peut aujourd'hui cumuler 80 000 € de revenus avec sa pension entière. Après le 1er janvier 2027, ce même dirigeant serait plafonné à environ 7 000 €, avec un écrêtement de 50 % au-delà. L'écart annuel se chiffre en dizaines de milliers d'euros.\n\nD'où la slide suivante.",
});

L.alertSlide(pres, {
  kicker: "La clause de sauvegarde",
  title: "Liquider avant le 31 décembre 2026 fige le régime actuel à vie",
  body: "Les retraités dont la première pension de base prend effet avant le 1er janvier 2027 conservent intégralement les règles actuelles. Ce n'est pas une tolérance transitoire : c'est un droit acquis, définitif.",
  color: C.orangeDeep,
  points: [
    "Vous avez l'âge légal et le taux plein aujourd'hui ? Liquider avant fin 2026 vous garantit le cumul sans plafond, à vie.",
    "Vous liquidez au 1er janvier 2027 ou après ? Vous entrez dans le nouveau régime, définitivement.",
    "Attention : la date qui compte est la date d'EFFET de la pension, pas la date de dépôt du dossier.",
    "Les caisses demandent un dossier complet 4 à 6 mois avant la date d'effet souhaitée.",
    "Au 2 septembre 2026, il ne reste donc que quelques semaines pour engager la démarche.",
  ],
  notes: "12 min. LE moment décisif de la formation.\n\nInsister sur le point 3 : c'est la date d'EFFET qui compte. Une pension prenant effet au 1er décembre 2026 est sous le régime actuel. Une pension prenant effet au 1er janvier 2027 est sous le nouveau régime. Un mois d'écart, deux régimes différents à vie.\n\nSur le point 4 : les délais d'instruction des caisses sont réels. Pour une date d'effet au 1er décembre 2026, le dossier devait idéalement être déposé en été. Pour une date d'effet au 1er janvier 2027... c'est déjà le nouveau régime.\n\nDONC : dire honnêtement que pour certains stagiaires, la fenêtre est déjà très étroite, voire fermée. Ne pas créer de fausse urgence, mais ne pas non plus laisser croire qu'il reste quatre mois pleins.\n\nMAIS AUSSI : ne pas pousser à liquider. Liquider tôt, c'est parfois liquider avec décote ou renoncer à une surcote. La décision doit être calculée, pas réflexe. C'est exactement l'objet du cas pratique suivant.",
});

L.caseSlide(pres, {
  num: "5",
  title: "Denis, 65 ans : faut-il liquider avant le 31 décembre ?",
  profil: "Denis, 65 ans, né en 1961.\nGérant majoritaire d'une SARL de négoce.\n172 trimestres : carrière complète, taux plein acquis.",
  situation: "Denis prévoyait de liquider sa retraite en 2028, à 67 ans, pour bénéficier d'une surcote. Il compte continuer à diriger son entreprise jusqu'à 70 ans, avec une rémunération de gérance de 4 500 € nets par mois, soit 54 000 € par an. La question qu'il pose : la réforme change-t-elle son calendrier ?",
  analyse: [
    "S'il liquide en 2026 : cumul intégral, aucun plafond. Il perçoit pension + 54 000 € de revenus.",
    "S'il liquide en 2028 à 67 ans : il est dans le nouveau régime, mais il a 67 ans — donc cumul libre aussi.",
    "Entre les deux, il aurait accumulé deux années de surcote sur sa pension définitive.",
    "Le nouveau régime supprime aussi le plafond de la seconde pension au-delà de 67 ans.",
    "Le risque : liquider en 2026 fige une pension sans surcote, pour un bénéfice de cumul qu'il aurait de toute façon à 67 ans.",
  ],
  reponse: "Denis n'est PAS dans l'urgence : à 67 ans, le nouveau régime lui donne un cumul libre, comme aujourd'hui, avec en prime une seconde pension déplafonnée. Attendre 2028 lui rapporte deux ans de surcote. La fenêtre de fin 2026 concerne les 62-66 ans, pas ceux qui atteindront 67 ans avant de liquider.",
  accent: C.green,
  notes: "15 min. Cas contre-intuitif, essentiel pour la crédibilité du formateur.\n\nIl serait facile — et malhonnête — de dire à tout le monde « liquidez avant le 31 décembre ». Ce cas montre que c'est faux pour une partie du public.\n\nLa règle de décision à formuler clairement :\n\n→ La fenêtre de fin 2026 est CRITIQUE pour ceux qui veulent cumuler entre l'âge légal et 67 ans avec des revenus significatifs. Eux perdent le cumul illimité.\n\n→ Elle est NEUTRE pour ceux qui liquideront de toute façon à 67 ans ou après : ils retrouvent le cumul libre dans le nouveau régime, et gagnent même sur la seconde pension déplafonnée.\n\n→ Elle est DÉFAVORABLE pour ceux qui liquideraient prématurément avec décote, juste pour « sécuriser ».\n\nFaire faire l'exercice à chacun sur le plan d'action : dans quelle catégorie suis-je ?\n\nRappeler que le calcul de la surcote (5 % par année supplémentaire au-delà du taux plein, en règle générale) doit être fait précisément par la caisse. Ne pas improviser de chiffre.",
});

L.listSlide(pres, {
  kicker: "Passer à l'action",
  title: "Vos six démarches, dans l'ordre",
  items: [
    { t: "Demander votre relevé de carrière tous régimes", d: "Sur info-retraite.fr, gratuit, immédiat. Vérifiez ligne par ligne qu'aucun régime, français ou étranger, ne manque. C'est le point de départ obligatoire." },
    { t: "Identifier vos régimes dormants et les liquider", d: "Un régime complémentaire non liquidé vous prive du cumul intégral. Contactez chaque caisse concernée sans attendre." },
    { t: "Faire chiffrer vos deux scénarios", d: "Liquidation en 2026 versus liquidation ultérieure. Demandez une estimation officielle à votre caisse et une simulation à votre expert-comptable." },
    { t: "Préparer le formalisme si vous êtes assimilé salarié", d: "Président de SAS ou gérant minoritaire : la cessation d'activité doit être organisée juridiquement, 4 à 6 mois à l'avance." },
    { t: "Déclarer votre reprise d'activité sous un mois", d: "À TOUTES vos caisses, pas seulement la principale. Nom et adresse de l'employeur, date de début, nature du contrat, rémunération brute." },
    { t: "Ajuster votre prélèvement à la source", d: "Dès le premier mois de cumul, sur impots.gouv.fr, pour éviter une régularisation lourde l'année suivante." },
  ],
  foot: "Ces six points sont repris dans votre plan d'action personnalisé, annexe C du livret. Complétez-le maintenant.",
  notes: "10 min, puis ATELIER de 20 min.\n\nDistribuer / faire ouvrir l'annexe C du livret (plan d'action personnalisé). Chaque stagiaire remplit sa colonne « échéance » et « qui fait quoi ».\n\nLe formateur circule et répond individuellement. C'est le moment le plus utile de la journée pour les stagiaires, et celui qui alimente le mieux l'évaluation à chaud.\n\nRappeler le SAV 12 mois : les questions qui ne trouvent pas de réponse en séance peuvent être posées ensuite.",
});

L.quizSlide(pres, {
  num: "1",
  question: "Bernard a 65 ans, une carrière complète, et a liquidé sa retraite SSI et RCI. Il a oublié 3 ans de points Agirc-Arrco. Est-il en cumul intégral ?",
  options: [
    "Oui : il a l'âge et le taux plein, les deux conditions essentielles.",
    "Non : la liquidation de TOUS les régimes est une condition à part entière.",
    "Oui, si les points Agirc-Arrco représentent moins de 100 € par mois.",
    "Cela dépend de son département de résidence.",
  ],
  answer: 1,
  explain: "Les trois conditions sont cumulatives et sans seuil de matérialité. Le montant des droits dormants est indifférent : c'est le fait qu'ils ne soient pas liquidés qui bloque le cumul intégral.",
  notes: "QUIZ DE FIN DE JOURNÉE — 15 min pour les 3 questions.\n\nFaire voter à main levée avant de révéler. C'est plus efficace qu'une correction silencieuse.\n\nLa réponse C est le distracteur intéressant : beaucoup pensent qu'il existe un seuil de tolérance. Il n'y en a pas.",
});

L.quizSlide(pres, {
  num: "2",
  question: "Une consultante en micro-BNC, en cumul plafonné SSI, est limitée à 24 030 € de revenu net. Quel chiffre d'affaires peut-elle facturer ?",
  options: [
    "24 030 €, le plafond s'applique directement au chiffre d'affaires.",
    "Environ 36 400 €, car le revenu net s'obtient après abattement de 34 %.",
    "77 700 €, le plafond du régime micro-BNC.",
    "48 060 €, soit 100 % du PASS.",
  ],
  answer: 1,
  explain: "Le plafond de cumul porte sur le revenu NET. En micro-BNC, l'abattement forfaitaire est de 34 % : le revenu net représente 66 % du CA. Donc 24 030 / 0,66 ≈ 36 400 € de chiffre d'affaires.",
  notes: "Question qui vérifie la compréhension du point le plus contre-intuitif de la journée.\n\nSi plus d'un tiers du groupe se trompe, reprendre le calcul au tableau — c'est un point à valeur financière directe pour les micro-entrepreneurs présents.",
});

L.quizSlide(pres, {
  num: "3",
  question: "Denis, 65 ans, carrière complète, veut travailler jusqu'à 70 ans. Doit-il impérativement liquider avant le 31 décembre 2026 ?",
  options: [
    "Oui, sinon il perd définitivement le cumul sans plafond.",
    "Non : à 67 ans, le nouveau régime lui rend le cumul libre, et il gagne deux ans de surcote.",
    "Oui, la clause de sauvegarde est la seule protection possible.",
    "Non, la réforme de 2027 ne concerne que les salariés.",
  ],
  answer: 1,
  explain: "La fenêtre de fin 2026 est critique pour ceux qui veulent cumuler ENTRE l'âge légal et 67 ans. Au-delà de 67 ans, le nouveau régime rétablit le cumul libre et déplafonne même la seconde pension.",
  notes: "Question la plus importante du quiz : elle vérifie que le message n'a pas été caricaturé en « liquidez tous avant le 31 décembre ».\n\nSi le groupe répond majoritairement A, c'est que le cas pratique 5 n'a pas été assez appuyé. Y revenir.",
});

L.cardsSlide(pres, {
  kicker: "Fin du jour 1",
  title: "Ce que vous retenez de cette journée",
  cols: 4,
  cards: [
    { n: "1", t: "Trois conditions", d: "Âge légal, taux plein, liquidation de TOUS les régimes. Il en manque une : vous êtes plafonné." },
    { n: "2", t: "Deux plafonds", color: C.violet, d: "2 916,85 €/mois pour le salarié. 24 030 €/an de revenu net pour l'indépendant SSI." },
    { n: "3", t: "Une seconde pension", d: "Réservée au cumul intégral, plafonnée à 2 403 €/an. Un bonus, pas une stratégie." },
    { n: "4", t: "Une date", color: C.violet, d: "Le 31 décembre 2026. Critique entre l'âge légal et 67 ans, neutre au-delà." },
  ],
  foot: "Le premier geste, ce soir ou demain matin : votre relevé de carrière tous régimes sur info-retraite.fr. Gratuit, dix minutes.",
  notes: "5 min de clôture.\n\nRedonner le geste unique à faire : le relevé de carrière. Si un stagiaire ne devait retenir qu'une chose de la journée, c'est celle-là.\n\nAnnoncer le jour 2 : « demain, on change complètement de sujet, mais pas de méthode. Une réforme, une date, et une décision à prendre. Sauf que pour la facturation électronique, la date est déjà passée : c'était hier. »\n\nRappeler l'heure de démarrage et que le quiz flash de reprise portera sur le jour 1.",
});

/* ═══════════════════════════════════════════════════════════════ JOUR 2 ══ */

L.daySlide(pres, {
  day: 2,
  title: "Facturation électronique",
  subtitle: "La réception est obligatoire depuis le 1er septembre 2026. L'émission le sera au 1er septembre 2027. Voici comment s'y conformer.",
  color: C.violet,
  blocks: [
    { n: "Matin", t: "Le cadre, le périmètre, l'écosystème des plateformes" },
    { n: "Après-midi", t: "Les formats, les mentions, le cycle de vie" },
    { n: "Fin de journée", t: "Sanctions, archivage et plan de mise en conformité" },
    { n: "Vous repartez avec", t: "Une checklist datée et une plateforme choisie" },
  ],
  notes: "9h00 — Lancement du jour 2.\n\nPhrase d'accroche : « hier, nous avons travaillé sur une échéance à venir. Aujourd'hui, sur une échéance déjà passée. L'obligation de réception est entrée en vigueur le 1er septembre — il y a deux jours. »\n\nCela change la posture : on n'est plus dans l'anticipation, on est dans la mise en conformité immédiate. Le ton doit être plus opérationnel que la veille.",
});

L.quizSlide(pres, {
  num: "Flash",
  question: "Reprise du jour 1 — Quelle est la condition du cumul intégral qui fait tomber le plus de dossiers ?",
  options: [
    "L'âge légal, souvent mal calculé après la suspension de la réforme.",
    "Le taux plein, difficile à atteindre pour les carrières de dirigeants.",
    "La liquidation de TOUS les régimes, y compris les complémentaires dormants et les pensions étrangères.",
    "La cessation d'activité, obligatoire pour tous les dirigeants.",
  ],
  answer: 2,
  explain: "Un régime oublié — trois ans de salariat il y a trente ans, une rente suisse — suffit à faire basculer en cumul plafonné, quel que soit le montant en jeu.",
  notes: "5 min. Réveil du groupe et vérification de la rétention.\n\nLa réponse D est le distracteur à commenter : la cessation d'activité n'est PAS obligatoire pour tous les dirigeants — seulement pour ceux qui relèvent du régime général (président de SAS, gérant minoritaire).\n\nEnchaîner immédiatement sur le programme du jour.",
});

L.timelineSlide(pres, {
  kicker: "Programme",
  title: "Le déroulé du jour 2",
  steps: [
    { date: "9h00", t: "Module 8\nLe cadre et le calendrier", d: "Pourquoi cette réforme, ce qui s'est passé le 1er septembre, et le grand malentendu du PDF par mail." },
    { date: "10h15", t: "Module 9\nLe périmètre", d: "e-invoicing, e-reporting, hors champ : apprendre à trier ses propres flux." },
    { date: "11h15", t: "Module 10\nL'écosystème", d: "Plateformes agréées, PPF, annuaire, opérateurs. Grille de choix en 7 critères et budget réel." },
    { date: "14h00", t: "Modules 11 & 12\nFormats, mentions, cycle de vie", d: "Factur-X, UBL, CII. Les 4 nouvelles mentions et les 4 statuts obligatoires." },
    { date: "16h00", t: "Modules 13 & 14\nRisques et plan d'action", d: "Sanctions relevées par la LF 2026, tolérance DGFiP, archivage, puis votre checklist." },
  ],
  foot: "Pauses à 10h15 et 15h45. Déjeuner à 12h30. Clôture et évaluations à 17h15.",
  notes: "2 min. Même logique que la veille : donner le rythme, pas le détail.\n\nSignaler que le module 10 (choix de la plateforme) est celui qui a le plus d'impact budgétaire immédiat, et que le module 14 est un atelier — prévoir d'y arriver avec de l'énergie.",
});

/* ────────────────────────────── MODULE 8 : le cadre et le calendrier ── */

L.moduleSlide(pres, {
  num: "8", title: "Le cadre et le calendrier",
  duration: "1 h 15",
  color: C.violet,
  points: [
    "Pourquoi l'État impose la facturation électronique : les trois objectifs",
    "Ce qui a changé le 1er septembre 2026, et ce qui changera le 1er septembre 2027",
    "Qui est concerné : la réponse courte est « presque tout le monde »",
    "Le malentendu numéro un : une facture PDF envoyée par mail n'est PAS une facture électronique",
  ],
  notes: "Module de cadrage. L'enjeu : que personne ne reparte en pensant que « ça ne me concerne pas » ou que « j'y suis déjà puisque j'envoie des PDF ».\n\nCes deux croyances sont les plus répandues et les plus coûteuses.",
});

L.cardsSlide(pres, {
  kicker: "Le pourquoi",
  title: "Trois objectifs assumés par l'État",
  cols: 3,
  cards: [
    { n: "1", t: "Lutter contre la fraude à la TVA", color: C.violet,
      d: "L'écart entre la TVA théoriquement due et la TVA réellement collectée se chiffre en milliards. La transmission automatique des données de facturation à l'administration vise à le réduire." },
    { n: "2", t: "Simplifier les déclarations", color: C.orange,
      d: "À terme, le pré-remplissage des déclarations de TVA à partir des données transmises. C'est la contrepartie promise aux entreprises." },
    { n: "3", t: "Piloter l'économie en temps réel", color: C.violet,
      d: "Disposer d'une vision conjoncturelle fine et précoce de l'activité, secteur par secteur, à partir des flux de facturation." },
  ],
  foot: "Bénéfice attendu côté entreprise : moins de saisie, moins d'erreurs, des délais de paiement plus courts et une relance facilitée.",
  notes: "6 min.\n\nNe pas éluder la dimension « contrôle » : les dirigeants la perçoivent très bien et un formateur qui la passe sous silence perd en crédibilité. L'assumer et enchaîner sur les bénéfices concrets.\n\nBénéfices réels à mettre en avant pour une TPE :\n- Fin de la ressaisie comptable : les données arrivent structurées.\n- Traçabilité du paiement : on sait quand le client a reçu, accepté, payé.\n- Relance facilitée grâce aux statuts du cycle de vie.\n- Réduction des litiges sur « je n'ai jamais reçu votre facture ».",
});

L.timelineSlide(pres, {
  kicker: "Calendrier officiel",
  title: "Deux dates, et vous êtes entre les deux",
  lead: "Nous sommes le 2 septembre 2026. La première échéance est derrière vous depuis deux jours.",
  steps: [
    { date: "01/09/2026", done: true, color: C.orangeDeep,
      t: "RÉCEPTION\npour tout le monde",
      d: "Toutes les entreprises assujetties à la TVA établies en France doivent pouvoir recevoir une facture électronique via une plateforme agréée. Micro-entreprises comprises. C'est fait, ou ce n'est pas fait." },
    { date: "01/09/2026", done: true, color: C.orangeDeep,
      t: "ÉMISSION\ngrandes entreprises et ETI",
      d: "Les grandes entreprises et les entreprises de taille intermédiaire doivent émettre leurs factures au format électronique et assurer leur e-reporting." },
    { date: "01/09/2027", color: C.violet,
      t: "ÉMISSION\nPME, TPE et micro",
      d: "L'obligation d'émission et d'e-reporting s'étend à toutes les entreprises. C'est votre échéance à vous : dans 12 mois." },
  ],
  foot: "Conséquence immédiate : vos gros clients et fournisseurs vous envoient déjà des factures électroniques. Vous devez pouvoir les recevoir.",
  notes: "10 min. Slide structurante de la journée.\n\nLe point à marteler : même si votre obligation d'ÉMISSION est en 2027, votre obligation de RÉCEPTION est déjà en vigueur. Et elle a un effet mécanique immédiat : vos clients grands comptes et ETI émettent désormais en électronique. S'ils ne vous trouvent pas dans l'annuaire, leur facture est rejetée — et c'est votre relation commerciale qui trinque.\n\nDemander à main levée : « qui a déjà reçu une facture via une plateforme depuis le 1er septembre ? » — et « qui a choisi sa plateforme ? ». L'écart entre les deux mains levées est le sujet de la journée.\n\nBase légale à citer : ordonnance n° 2021-1190, loi de finances pour 2024 (art. 91), et les décrets et arrêtés d'application.",
});

L.alertSlide(pres, {
  kicker: "Le malentendu numéro un",
  title: "Un PDF envoyé par mail n'est pas une facture électronique",
  body: "C'est la confusion la plus répandue, et elle met en risque de non-conformité des milliers d'entreprises persuadées d'être déjà en règle.",
  color: C.violetDeep,
  points: [
    "Une facture électronique au sens de la réforme comporte un socle de données STRUCTURÉES, lisibles par une machine.",
    "Elle transite obligatoirement par une PLATEFORME AGRÉÉE, pas par votre messagerie.",
    "Elle porte un cycle de vie avec des statuts transmis à l'administration.",
    "Un PDF classique envoyé en pièce jointe ne remplit aucune de ces trois conditions.",
    "En revanche, un Factur-X — qui est un PDF avec un fichier XML embarqué — est bien une facture électronique.",
  ],
  notes: "8 min. Slide décisive.\n\nBeaucoup de dirigeants disent « je suis déjà en électronique, j'envoie des PDF ». Il faut casser cette croyance nettement mais sans humilier.\n\nFormulation utile : « le PDF, c'est une image de facture. La réforme demande une facture que la machine peut lire, pas seulement afficher. »\n\nEnchaîner sur la bonne nouvelle : le Factur-X permet de garder l'apparence d'un PDF classique, avec les données structurées cachées dedans. Visuellement, pour le client, rien ne change. C'est pour cela qu'il est recommandé aux TPE et PME.\n\nÀ ce stade, ne pas entrer dans le détail technique — le module 11 s'en charge.",
});

L.cardsSlide(pres, {
  kicker: "Qui est concerné",
  title: "La réponse courte : presque tout le monde",
  cols: 2,
  cards: [
    { n: "✓", t: "Vous êtes concerné si…", color: C.violet,
      d: "Vous êtes une entreprise ASSUJETTIE à la TVA et ÉTABLIE en France. C'est tout.\n\nCela inclut les micro-entreprises et les auto-entrepreneurs.\n\nCela inclut ceux qui bénéficient de la FRANCHISE EN BASE de TVA : la franchise vous dispense de COLLECTER la TVA, pas des obligations de facturation. Vous restez assujetti.\n\nCela inclut les holdings, les SCI soumises à TVA, les associations assujetties." },
    { n: "✗", t: "Vous n'êtes pas concerné si…", color: C.gray,
      d: "Votre activité relève exclusivement d'opérations exonérées de TVA au titre des articles 261 à 261 E du CGI et dispensées de facturation.\n\nSont notamment visés : les soins médicaux et paramédicaux, l'enseignement et la formation, certaines opérations immobilières, les organismes sans but lucratif, les opérations bancaires, financières et d'assurance.\n\nAttention : c'est l'OPÉRATION qui est hors champ, pas l'entreprise. Une activité mixte est partiellement concernée." },
  ],
  foot: "Le cas le plus fréquent en salle : le micro-entrepreneur en franchise en base, persuadé d'être hors du dispositif. Il ne l'est pas.",
  notes: "10 min.\n\nInsister sur la franchise en base : c'est le contresens le plus fréquent. « Je ne facture pas de TVA donc la réforme TVA ne me concerne pas » — faux. La franchise en base est une dispense de collecte, pas une exclusion du champ. Le micro-entrepreneur reste assujetti, donc concerné, et doit conserver la mention « TVA non applicable, art. 293 B du CGI ».\n\nSur la colonne de droite, insister sur « c'est l'opération qui est hors champ, pas l'entreprise ». Un kinésithérapeute qui fait aussi de la formation professionnelle a deux flux, dont l'un peut être dans le champ.\n\nCe point ouvre naturellement sur le module 9.",
});

/* ───────────────────────────────────────── MODULE 9 : le périmètre ── */

L.moduleSlide(pres, {
  num: "9", title: "e-invoicing, e-reporting, hors champ",
  duration: "1 h",
  points: [
    "Les trois cases dans lesquelles ranger chacune de vos opérations",
    "e-invoicing : le B2B domestique, celui qui passe par les plateformes",
    "e-reporting : le B2C, l'international, et les données de paiement",
    "Deux ateliers de tri : une TPE de menuiserie, une consultante à activité mixte",
  ],
  notes: "Module le plus structurant de la matinée. Si les stagiaires savent trier leurs flux, ils savent ce qu'ils doivent mettre en œuvre.\n\nLe piège classique : croire que « facturation électronique » = « toutes mes factures passent par la plateforme ». Non : seul le B2B domestique passe en e-invoicing. Le reste relève de l'e-reporting, qui est un flux de DONNÉES, pas de factures.",
});

L.cardsSlide(pres, {
  kicker: "Le tri",
  title: "Chaque opération va dans l'une de ces trois cases",
  cols: 3,
  cards: [
    { n: "1", t: "e-invoicing", color: C.violet,
      d: "Vos ventes et achats B2B DOMESTIQUES : entre deux entreprises assujetties à la TVA établies en France.\n\nLa facture elle-même transite par une plateforme agréée, au format structuré.\n\nC'est le cœur de la réforme." },
    { n: "2", t: "e-reporting", color: C.orange,
      d: "Tout ce qui sort du champ e-invoicing :\n\n• vos ventes B2C à des particuliers en France ;\n• vos opérations internationales : livraisons intracommunautaires, exportations, acquisitions, importations ;\n• vos données de PAIEMENT pour les prestations de services.\n\nOn transmet des DONNÉES, pas une facture." },
    { n: "3", t: "Hors champ", color: C.gray,
      d: "Les opérations exonérées de TVA au titre des articles 261 à 261 E du CGI et dispensées de facturation.\n\nSanté, enseignement et formation, certaines opérations immobilières, secteur associatif, banque, finance, assurance.\n\nNi facture électronique, ni e-reporting." },
  ],
  foot: "Une même entreprise a très souvent des flux dans les trois cases. Le tri se fait opération par opération, jamais globalement.",
  notes: "10 min. Slide de référence — les stagiaires y reviendront.\n\nLa distinction essentielle : en e-invoicing, c'est la FACTURE qui circule via la plateforme. En e-reporting, c'est un JEU DE DONNÉES agrégé qui part vers l'administration. Ce ne sont pas les mêmes objets, ni les mêmes contraintes.\n\nFaire noter : « e-invoicing = ma facture voyage ; e-reporting = mes chiffres voyagent ».\n\nDonnées transmises en e-reporting : SIREN, période concernée, montant HT, montant TTC, taux de TVA applicable, nature de l'opération. Et pour les prestataires soumis à la TVA sur les encaissements : le montant et la date d'encaissement.\n\nFréquence : périodique, hebdomadaire ou mensuelle selon la taille de l'entreprise.",
});

L.caseSlide(pres, {
  num: "6",
  title: "Atelier : la menuiserie Vernier trie ses flux",
  profil: "SARL de menuiserie, 6 salariés.\nCA annuel : 780 000 €.\nBasée dans le Jura.",
  situation: "L'entreprise pose des menuiseries pour des promoteurs immobiliers, des collectivités, des particuliers, et vend depuis peu des kits à une entreprise belge. Le gérant veut savoir, flux par flux, ce qui relève de quoi. Il facture environ 340 factures par an.",
  analyse: [
    "Chantiers pour promoteurs français assujettis → e-invoicing. La facture passe par la plateforme.",
    "Marchés publics avec les collectivités → déjà dématérialisés via Chorus Pro, à articuler avec la plateforme.",
    "Pose chez des particuliers → e-reporting. Pas de facture électronique, mais transmission des données.",
    "Ventes de kits à l'entreprise belge → e-reporting international, livraison intracommunautaire.",
    "Achats auprès de ses fournisseurs français assujettis → réception e-invoicing, obligatoire depuis le 1er septembre.",
  ],
  reponse: "Trois régimes coexistent dans une seule entreprise de six salariés. La plateforme agréée doit donc gérer à la fois l'e-invoicing B2B, l'e-reporting B2C et l'e-reporting international — c'est un critère de choix déterminant, à vérifier avant de signer.",
  accent: C.violet,
  notes: "12 min. Atelier participatif : projeter le profil, masquer l'analyse, faire trier par le groupe.\n\nC'est l'exercice qui fait comprendre que le tri est opération par opération. Une entreprise de six salariés peut relever des trois régimes.\n\nSur Chorus Pro : les factures aux entités publiques passent déjà par Chorus Pro depuis 2017-2020 selon la taille. L'articulation avec le nouveau dispositif est un point à vérifier auprès de la plateforme retenue — ne pas affirmer plus que ce que l'on sait, et renvoyer au SAV si la question est posée précisément.\n\nMessage à retenir : quand vous auditionnez une plateforme, ne demandez pas « faites-vous de la facture électronique ». Demandez « gérez-vous mes trois flux : B2B domestique, B2C, et intracommunautaire ? ».",
});

L.caseSlide(pres, {
  num: "7",
  title: "Atelier : Léa, kinésithérapeute et formatrice",
  profil: "Léa, 41 ans.\nCabinet de kinésithérapie en libéral.\nActivité secondaire de formation auprès d'écoles paramédicales.",
  situation: "Elle facture ses soins aux patients et à la Sécurité sociale, et facture des prestations de formation à trois organismes de formation privés, pour 18 000 € par an. Elle est en franchise en base de TVA sur son activité de formation. Elle pense être totalement hors du dispositif.",
  analyse: [
    "Soins paramédicaux → exonérés de TVA au titre de l'article 261, 4, 1° du CGI : hors champ.",
    "Prestations de formation professionnelle → exonérées au titre de l'article 261, 4, 4° si elle dispose de l'attestation ; à vérifier au cas par cas.",
    "Si ses prestations de formation sont exonérées et dispensées de facturation → hors champ également.",
    "Si elles ne le sont pas → e-invoicing B2B, car les organismes clients sont des assujettis établis en France.",
    "Dans tous les cas : elle est assujettie et doit pouvoir RECEVOIR des factures électroniques depuis le 1er septembre.",
  ],
  reponse: "Le tri de ses flux sortants dépend du régime exact de son activité de formation, à confirmer avec son expert-comptable. Mais son obligation de réception, elle, ne fait aucun doute : elle achète du matériel et des services à des fournisseurs français assujettis, qui lui adresseront des factures électroniques.",
  accent: C.orange,
  notes: "12 min.\n\nCas volontairement nuancé : il montre qu'une partie du tri exige une analyse fiscale individuelle, et que le formateur ne doit pas trancher à la place de l'expert-comptable.\n\nLe point ferme, en revanche : l'obligation de RÉCEPTION ne souffre aucune exception dès lors qu'on est assujetti. Même une professionnelle majoritairement exonérée doit pouvoir recevoir les factures de ses fournisseurs.\n\nMessage à faire passer au groupe : « quand vous doutez sur vos flux SORTANTS, faites analyser. Mais sur vos flux ENTRANTS, il n'y a pas de doute : raccordez-vous. »\n\nC'est une bonne transition vers le module 10.",
});

/* ─────────────────────────────────────── MODULE 10 : l'écosystème ── */

L.moduleSlide(pres, {
  num: "10", title: "L'écosystème : plateformes, annuaire, opérateurs",
  duration: "1 h 15",
  color: C.violet,
  points: [
    "PDP devenues Plateformes Agréées : ce que le changement de nom recouvre",
    "Ce que le PPF est devenu — et pourquoi il n'y a plus de solution publique gratuite de dépôt",
    "L'annuaire : le mécanisme qui fait que votre facture arrive, ou pas",
    "Une grille de choix en 7 critères, et les budgets réels du marché",
  ],
  notes: "Module à plus fort impact opérationnel et budgétaire immédiat. C'est celui où les stagiaires prennent des notes.\n\nRester factuel sur les prestataires : citer des ordres de grandeur et des catégories, ne pas faire de recommandation commerciale nominative. L'Agence SCFR est un organisme de formation, pas un revendeur.",
});

L.listSlide(pres, {
  kicker: "Vocabulaire",
  title: "Quatre acteurs à ne pas confondre",
  items: [
    { t: "La Plateforme Agréée (PA), ex-PDP", d: "Immatriculée par l'État pour trois ans. Elle seule peut émettre, recevoir et transmettre les factures et les données à l'administration. Depuis juillet 2025, le terme officiel est « plateforme agréée » : PDP reste employé dans le langage courant." },
    { t: "Le Portail Public de Facturation (PPF)", d: "Il a été supprimé en tant que plateforme gratuite de dépôt. Il conserve deux rôles : l'ANNUAIRE des plateformes agréées, et le CONCENTRATEUR des données destinées à la DGFiP." },
    { t: "L'Opérateur de Dématérialisation (OD)", d: "Un éditeur de logiciel ou un prestataire NON immatriculé. Il peut préparer et mettre en forme vos factures, mais il doit obligatoirement s'adosser à une plateforme agréée pour les transmettre." },
    { t: "L'annuaire", d: "Le référentiel central qui associe chaque SIREN à sa plateforme. C'est lui qui permet à la facture de votre fournisseur de vous trouver. Y être inscrit n'est pas optionnel." },
  ],
  accent: C.violet,
  foot: "La conséquence pratique de la suppression du PPF : toute entreprise doit choisir au moins une plateforme agréée. Il n'y a pas d'option « je passe par l'État gratuitement ».",
  notes: "12 min. Slide très importante : elle corrige une croyance largement répandue.\n\nBeaucoup d'entreprises ont attendu en pensant qu'une solution publique gratuite existerait, comme Chorus Pro pour le secteur public. Ce n'est plus le cas : le PPF ne fait plus office de plateforme de dépôt.\n\nMAIS — nuance importante à donner immédiatement pour ne pas affoler les micro-entrepreneurs : plusieurs plateformes agréées proposent des offres GRATUITES pour les indépendants et les très petits volumes. « Pas de solution publique gratuite » ne veut pas dire « pas de solution gratuite ».\n\nSur l'OD : c'est le cas typique de l'éditeur de logiciel de facturation que vous utilisez déjà. Il n'est peut-être pas immatriculé, mais il a sûrement noué un partenariat avec une PA. Première question à lui poser : « à quelle plateforme agréée êtes-vous adossé ? ».",
});

L.statsSlide(pres, {
  kicker: "État du marché",
  title: "Où en est l'offre au 2 septembre 2026",
  stats: [
    { v: "145", k: "plateformes agréées", color: C.violet,
      d: "Nombre de plateformes immatriculées par la DGFiP à la dernière mise à jour de la liste officielle, le 29 juillet 2026." },
    { v: "16 janv. 2026", k: "première liste publiée", small: true, color: C.orange,
      d: "Date de publication de la première liste officielle des plateformes agréées sur impots.gouv.fr." },
    { v: "3 ans", k: "durée de l'immatriculation", color: C.violet,
      d: "L'agrément est délivré pour trois ans, puis renouvelable. Vérifiez la date d'échéance de celui de votre prestataire." },
  ],
  foot: "La liste officielle et à jour est publiée sur impots.gouv.fr. Ne vous fiez jamais à la seule déclaration commerciale d'un prestataire : vérifiez.",
  notes: "6 min.\n\nLe conseil de vigilance est le plus utile : certains éditeurs communiquent sur « notre solution est conforme » sans être eux-mêmes immatriculés. Ils sont OD, pas PA. Ce n'est pas disqualifiant, mais il faut savoir à quelle PA ils sont adossés.\n\nGeste à faire faire : aller sur impots.gouv.fr, rubrique facturation électronique, et vérifier la présence du prestataire envisagé dans la liste officielle. Cinq minutes.\n\nSignaler que le nombre évolue : 137 en juillet, 145 fin juillet. La liste est vivante.",
});

L.listSlide(pres, {
  kicker: "Méthode",
  title: "Votre grille de choix en sept critères",
  twoCol: true,
  items: [
    { t: "L'immatriculation, d'abord", d: "Vérifiée sur la liste officielle d'impots.gouv.fr. Sinon, c'est un OD : identifiez la PA à laquelle il est adossé." },
    { t: "La couverture de VOS flux", d: "e-invoicing B2B, e-reporting B2C, e-reporting international. Reprenez le tri du module 9 et exigez une réponse sur les trois." },
    { t: "L'intégration à votre logiciel", d: "Compta, ERP, caisse, logiciel métier. Une plateforme non intégrée, c'est de la double saisie tous les jours." },
    { t: "Les formats gérés", d: "Factur-X au minimum. UBL et CII si vous travaillez avec des grands comptes ou à l'international." },
    { t: "L'archivage à valeur probante", d: "Est-il inclus ? Sur quelle durée ? Que se passe-t-il si vous résiliez ? Point très souvent négligé." },
    { t: "Le modèle tarifaire", d: "Abonnement mensuel, facturation au volume, ou devis. Projetez sur votre volume réel de factures, pas sur le tarif d'appel." },
    { t: "Le support et la réversibilité", d: "Support en français, joignable. Et surtout : pouvez-vous partir avec vos données, et à quel coût ?" },
  ],
  accent: C.violet,
  foot: "Bonne nouvelle : les plateformes sont interopérables via l'annuaire. Vous pouvez changer à tout moment, en mettant à jour votre inscription.",
  notes: "12 min. Slide à photographier — le dire au groupe, cela détend et cela circule.\n\nInsister sur le critère 3 (intégration) : c'est celui qui détermine le coût réel. Une plateforme à 9 €/mois non intégrée à votre logiciel de compta vous coûtera plus cher en temps de saisie qu'une plateforme à 40 €/mois intégrée.\n\nInsister sur le critère 5 (archivage) : la plateforme n'est PAS un service d'archivage à valeur probante par défaut. Beaucoup de contrats ne couvrent que la durée de l'abonnement. Or l'obligation de conservation est de 10 ans. À vérifier ligne par ligne dans le contrat.\n\nInsister sur le critère 7 (réversibilité) : question à poser textuellement au commercial — « si je résilie dans deux ans, sous quel format et à quel coût je récupère l'intégralité de mes factures et de leurs statuts ? ». La qualité de la réponse est très discriminante.",
});

L.tableSlide(pres, {
  kicker: "Budget",
  title: "Combien cela va vous coûter, réellement",
  lead: "Ordres de grandeur constatés sur le marché en 2026. Les trois modèles tarifaires coexistent.",
  head: ["Votre profil", "Budget mensuel constaté", "Modèle tarifaire dominant", "Point de vigilance"],
  colW: [2.9, 2.7, 3.2, 3.3],
  rows: [
    ["Micro-entrepreneur, indépendant", { text: "0 à 15 €", bold: true, color: C.green }, "Offres gratuites ou forfait d'entrée", "Vérifier les limites de volume"],
    ["TPE (1 à 10 salariés)", { text: "9 à 50 €", bold: true }, "Abonnement mensuel, souvent par utilisateur", "Coût de l'intégration comptable"],
    ["PME (10 à 250 salariés)", { text: "30 à 150 €", bold: true }, "Abonnement ou facturation au volume", "Paliers de volume et dépassements"],
    ["ETI et grandes entreprises", { text: "Sur devis", bold: true }, "Projet d'intégration ERP", "Coût de projet, pas d'abonnement"],
  ],
  accent: C.violet,
  foot: "Le vrai coût n'est pas l'abonnement : c'est le temps humain. Une plateforme mal intégrée coûte plusieurs heures par mois.",
  notes: "10 min.\n\nDédramatiser sur le budget : pour la grande majorité des stagiaires — TPE et micro — on parle de quelques dizaines d'euros par mois. Ce n'est pas le sujet.\n\nLe sujet, c'est le temps. Reformuler : « à 25 € par mois, la plateforme coûte 300 € par an. Si elle vous fait perdre deux heures par mois de ressaisie, elle vous coûte en réalité 24 heures de votre temps de dirigeant. C'est ça, l'arbitrage. »\n\nSur les trois modèles tarifaires :\n- Abonnement mensuel : le plus courant chez les éditeurs TPE/PME, souvent par utilisateur avec des paliers de fonctionnalités.\n- Facturation au volume : adapté aux structures à forts flux qui préfèrent payer à l'usage.\n- Devis : réservé aux grands comptes, intégration comprise.\n\nMettre en garde sur les paliers de volume : lire les conditions de dépassement avant de signer.",
});

L.caseSlide(pres, {
  num: "8",
  title: "Trois profils, trois choix de plateforme",
  profil: "Trois stagiaires types de cette session.\nMême réforme, trois réponses différentes.",
  situation: "Sophie est micro-entrepreneuse en conseil, 25 factures par an, tenue de compta sur tableur. Marc dirige une TPE de 6 salariés, 340 factures par an, logiciel de compta en place. Nadia dirige une PME de 40 salariés, 2 400 factures par an, avec un ERP métier.",
  analyse: [
    "Sophie : volume négligeable, priorité au coût et à la simplicité → offre gratuite ou d'entrée de gamme.",
    "Marc : priorité absolue à l'intégration avec son logiciel de compta existant → interroger d'abord son éditeur actuel.",
    "Nadia : priorité à l'interfaçage ERP et à la gestion des trois flux → projet d'intégration, budget sur devis.",
    "Pour les trois : vérifier l'immatriculation sur impots.gouv.fr avant toute signature.",
    "Pour les trois : poser la question de l'archivage 10 ans et de la réversibilité.",
  ],
  reponse: "Il n'y a pas de « meilleure plateforme », il y a une plateforme adaptée à un volume, un logiciel et des flux. La première question n'est jamais « quelle plateforme ? » mais « quel est mon volume, quel est mon logiciel, quels sont mes flux ? ».",
  accent: C.violet,
  notes: "12 min. Atelier : demander à chaque stagiaire de se situer par rapport aux trois profils.\n\nLe conseil le plus rentable de la journée pour un dirigeant de TPE : commencer par appeler son éditeur de logiciel de comptabilité actuel et lui poser trois questions —\n1. Êtes-vous plateforme agréée, ou à laquelle êtes-vous adossé ?\n2. Que devient mon abonnement actuel ?\n3. Qu'est-ce que cela change pour ma saisie quotidienne ?\n\nDans une majorité de cas, la solution la plus économique est celle de l'éditeur déjà en place, parce qu'elle évite le coût d'intégration.\n\nNe citer aucun nom de prestataire en recommandation. Si un stagiaire demande « lequel prenez-vous ? », répondre que l'Agence SCFR est organisme de formation et ne fait pas de préconisation commerciale, et renvoyer à la grille des 7 critères.",
});

/* ─────────────────────────────────────────── MODULE 11 : les formats ── */

L.moduleSlide(pres, {
  num: "11", title: "Les formats de facture",
  duration: "45 min",
  points: [
    "EN 16931 : la norme européenne qui met tout le monde d'accord",
    "Factur-X, UBL, CII : trois expressions d'une même sémantique",
    "L'anatomie d'un Factur-X, et pourquoi il est recommandé aux TPE et PME",
    "L'interopérabilité : émettre dans un format, être reçu dans un autre",
  ],
  notes: "Module technique. L'objectif n'est PAS que les stagiaires sachent lire du XML, mais qu'ils sachent quoi demander à leur plateforme et pourquoi Factur-X leur convient.\n\nRester à 20 minutes d'exposé maximum, le reste en questions.",
});

L.cardsSlide(pres, {
  kicker: "Le socle",
  title: "Trois formats, une seule norme",
  cols: 3,
  cards: [
    { n: "1", t: "Factur-X", color: C.orange,
      d: "Format HYBRIDE : un fichier PDF/A-3 lisible par un humain, auquel est attaché un fichier XML en syntaxe CII, lisible par la machine.\n\nUn seul fichier, deux lectures. Votre client ouvre une facture normale ; son logiciel lit directement les données.\n\nRecommandé aux TPE et PME." },
    { n: "2", t: "UBL", color: C.violet,
      d: "Universal Business Language, standard OASIS.\n\nUne syntaxe XML entièrement structurée. Pas de rendu visuel : c'est un fichier de données pur.\n\nTrès répandu chez les éditeurs, dans le e-commerce et à l'international." },
    { n: "3", t: "CII", color: C.violet,
      d: "Cross Industry Invoice, standard UN/CEFACT.\n\nÉgalement une syntaxe XML entièrement structurée, sans rendu visuel.\n\nHistoriquement utilisé dans l'EDI industriel et par les grands comptes. C'est la syntaxe embarquée dans Factur-X." },
  ],
  foot: "Les trois expriment la même norme sémantique européenne : EN 16931. Ils disent la même chose, dans trois grammaires différentes.",
  notes: "10 min.\n\nAnalogie utile : EN 16931, c'est le vocabulaire et la grammaire commune. UBL et CII sont deux manières de l'écrire. Factur-X, c'est du CII glissé dans une enveloppe PDF pour que l'humain puisse lire aussi.\n\nInsister sur l'atout décisif de Factur-X pour une TPE : le client qui n'est pas encore équipé ouvre le PDF et voit une facture normale. Celui qui est équipé exploite le XML. La transition se fait sans casser la relation commerciale.\n\nUne précision technique parfois demandée : Factur-X introduit des PROFILS, qui définissent le niveau de détail du XML embarqué. Ne pas entrer dans le détail des profils en séance — c'est un sujet de plateforme, pas de dirigeant.",
});

L.compareSlide(pres, {
  kicker: "Interopérabilité",
  title: "Vous n'avez pas à vous aligner sur le format de votre client",
  left: {
    title: "Ce que vous craignez",
    color: C.gray,
    items: [
      "« Mon client travaille en UBL, je vais devoir changer de format. »",
      "« Chaque grand compte va m'imposer sa norme. »",
      "« Je vais devoir gérer trois formats en parallèle. »",
      "« Si je me trompe de format, ma facture sera rejetée. »",
    ],
  },
  right: {
    title: "Ce qui se passe réellement",
    color: C.green,
    items: [
      "Vous émettez dans le format de votre choix, en général Factur-X.",
      "Votre plateforme agréée convertit à la volée vers le format attendu par le destinataire.",
      "La conversion se fait SANS PERTE de données, puisque tout repose sur la même norme EN 16931.",
      "Votre client reçoit en UBL ce que vous avez émis en Factur-X, et personne n'a rien fait de particulier.",
      "C'est précisément le service que vous payez à votre plateforme.",
    ],
  },
  foot: "Question à poser à votre plateforme : « gérez-vous la conversion entrante et sortante entre Factur-X, UBL et CII ? » La réponse doit être oui.",
  notes: "10 min. Slide très rassurante — beaucoup d'inquiétudes tombent ici.\n\nLe message : le choix du format n'est PAS un sujet stratégique pour une TPE. C'est un sujet de plateforme. Émettez en Factur-X, votre plateforme s'occupe du reste.\n\nSeule exception à signaler : si vous travaillez avec un grand compte qui impose un profil particulier ou des données complémentaires, il peut y avoir des ajustements. Cela se traite avec la plateforme, pas en interne.\n\nEnchaîner : « en revanche, ce qui est votre sujet, ce sont les DONNÉES que vous devez fournir. Et là, il y a du travail. » → module 12.",
});

/* ──────────────────────── MODULE 12 : mentions et cycle de vie ── */

L.moduleSlide(pres, {
  num: "12", title: "Mentions obligatoires et cycle de vie",
  duration: "1 h",
  color: C.violet,
  points: [
    "Les quatre nouvelles mentions imposées par la réforme",
    "Le SIREN client : le chantier caché de votre base de données",
    "Les quatre statuts obligatoires du cycle de vie",
    "Rejetée ou refusée : deux mots, deux traitements, deux coûts",
  ],
  notes: "Module le plus opérationnel de l'après-midi. C'est ici que les stagiaires comprennent le travail concret à faire avant septembre 2027.\n\nLe message : le vrai chantier n'est pas technique, il est dans la qualité de votre référentiel client.",
});

L.listSlide(pres, {
  kicker: "Les 4 nouvelles mentions",
  title: "Ce que vos factures devront porter en plus",
  lead: "Ces mentions s'ajoutent aux mentions classiques déjà obligatoires. Elles s'imposent au rythme du calendrier d'ÉMISSION : donc au 1er septembre 2027 pour vous.",
  items: [
    { t: "Le numéro SIREN de votre client", d: "Auparavant facultatif, il devient obligatoire. C'est lui qui permet le routage via l'annuaire et le rapprochement automatique des flux. Sans SIREN valide, la facture ne trouve pas son destinataire." },
    { t: "L'adresse de livraison, si elle diffère de l'adresse de facturation", d: "À porter systématiquement dès qu'il y a un écart. Formulation type : « Livraison au 14 rue des Ateliers, 39100 Dole »." },
    { t: "La catégorie de l'opération", d: "Livraison de biens, prestation de services, ou opération mixte. Elle permet d'appliquer les bonnes règles d'exigibilité de la TVA et de traiter correctement les données fiscales." },
    { t: "L'option pour le paiement de la TVA d'après les débits", d: "Si vous avez exercé cette option, elle doit figurer explicitement : « TVA acquittée d'après les débits »." },
  ],
  accent: C.violet,
  foot: "Les mentions classiques restent dues, ainsi que « TVA non applicable, art. 293 B du CGI » pour les entreprises en franchise en base.",
  notes: "12 min.\n\nLes quatre mentions ne sont pas d'égale difficulté :\n- La catégorie d'opération : votre logiciel la génère automatiquement. Non-sujet.\n- L'option TVA sur les débits : vous savez si vous l'avez exercée. Non-sujet.\n- L'adresse de livraison : vous l'avez généralement déjà. Sujet mineur.\n- LE SIREN CLIENT : c'est LE chantier. Slide suivante.\n\nRappeler que ces mentions s'imposent au rythme de l'obligation d'ÉMISSION. Pour un TPE/PME, c'est donc le 1er septembre 2027 — mais le chantier de collecte des SIREN doit commencer maintenant.",
});

L.alertSlide(pres, {
  kicker: "Le chantier caché",
  title: "Combien de SIREN clients manquent dans votre base ?",
  body: "C'est la question à poser à votre comptable ce soir. Dans une base client de TPE constituée au fil des années, le taux de SIREN manquants ou erronés dépasse souvent 30 %.",
  color: C.violetDeep,
  points: [
    "Sans SIREN valide, la facture ne peut pas être routée vers la plateforme du client : elle est rejetée.",
    "Un rejet, c'est un délai de paiement qui s'allonge et une relance à faire.",
    "La collecte prend du temps : il faut contacter les clients un par un pour les dossiers anciens.",
    "Vous avez douze mois avant l'obligation d'émission. Commencez par vos vingt plus gros clients.",
    "Vérification possible en masse sur l'annuaire des entreprises, à partir de la raison sociale.",
  ],
  notes: "10 min. Slide qui déclenche le plus de prises de notes de la journée.\n\nExercice à faire faire immédiatement : « prenez votre téléphone, ouvrez votre logiciel de facturation, et regardez vos dix derniers clients. Combien ont un SIREN renseigné ? »\n\nEn général, le résultat est mauvais et cela crée un déclic beaucoup plus fort qu'un discours.\n\nMéthode de rattrapage à donner :\n1. Extraire la liste des clients actifs des 24 derniers mois.\n2. Trier par chiffre d'affaires décroissant.\n3. Traiter les 20 premiers d'abord — ils font en général 80 % du volume.\n4. Compléter par recherche sur l'annuaire des entreprises pour les autres.\n5. Systématiser la collecte du SIREN à l'ouverture de tout nouveau compte client.\n\nLe point 5 est le plus important : c'est un changement de process commercial, pas une opération ponctuelle.",
});

L.tableSlide(pres, {
  kicker: "Cycle de vie",
  title: "Les quatre statuts obligatoires",
  lead: "Ces statuts sont transmis à l'administration et rythment la vie de chaque facture.",
  head: ["Code", "Statut", "Qui le déclenche", "Ce que cela signifie"],
  colW: [1.3, 2.2, 2.6, 5.99],
  rows: [
    ["200", { text: "Déposée", bold: true }, "Le fournisseur, via sa plateforme", "La facture est déposée. Cela ne garantit pas encore que les contrôles seront conformes."],
    ["213", { text: "Rejetée", bold: true, color: C.red }, "La plateforme", "Rejet TECHNIQUE : format invalide, mention manquante, destinataire introuvable dans l'annuaire, doublon."],
    ["210", { text: "Refusée", bold: true, color: C.red }, "Le client", "Refus COMMERCIAL motivé : le client conteste la facture. Désaccord de fond, pas d'erreur technique."],
    ["212", { text: "Encaissée", bold: true, color: C.green }, "Le fournisseur", "Paiement partiel ou total reçu, avec la date et le montant encaissés."],
  ],
  accent: C.violet,
  foot: "« Rejetée » et « Refusée » sont des statuts terminaux. D'autres statuts, recommandés ou libres, complètent le dispositif.",
  notes: "10 min.\n\nL'apport pratique du cycle de vie pour un dirigeant : vous savez enfin où en est votre facture. Fini le « je ne l'ai jamais reçue ». Le statut fait foi.\n\nBénéfice à mettre en avant : la relance devient objective. Vous voyez qu'une facture est déposée depuis 45 jours et non encaissée : vous relancez avec un élément de preuve.\n\nLe statut « Encaissée » est déclaratif : c'est le fournisseur qui l'indique. Il sert notamment à l'e-reporting des données de paiement pour les prestations de services.",
});

L.compareSlide(pres, {
  kicker: "Ne pas confondre",
  title: "Rejetée ou refusée : deux problèmes, deux réponses",
  left: {
    title: "Rejetée (213) — problème technique",
    color: C.orange,
    dense: true,
    items: [
      "La plateforme a bloqué la facture avant qu'elle n'atteigne le client.",
      "Causes typiques : SIREN client absent ou erroné, format non conforme, mention obligatoire manquante, facture en doublon.",
      "Le client n'a jamais vu la facture. Elle n'existe pas pour lui.",
      "La correction est technique : on répare la donnée et on réémet.",
      "Coût : un délai. Le compteur de paiement n'a même pas démarré.",
    ],
  },
  right: {
    title: "Refusée (210) — désaccord commercial",
    color: C.red,
    dense: true,
    items: [
      "La facture est bien arrivée. Le client la conteste explicitement.",
      "Causes typiques : prestation contestée, montant erroné, commande non conforme, opération inconnue du client.",
      "C'est une décision motivée de l'acheteur, pas une erreur de forme.",
      "La correction est commerciale : on discute, puis on émet un avoir ou une nouvelle facture.",
      "Coût : un litige à traiter, et une trace dans le cycle de vie.",
    ],
  },
  foot: "Un taux de rejets élevé signale un problème de données. Un taux de refus élevé signale un problème de relation client ou de process de commande.",
  notes: "10 min. Distinction à faire retenir absolument.\n\nLa phrase à donner : « rejetée, c'est ma faute et c'est technique. Refusée, c'est un désaccord et c'est commercial. »\n\nIntérêt managérial : ces deux taux deviennent des indicateurs de pilotage. Un dirigeant qui suit son taux de rejet suit en réalité la qualité de son référentiel client. Un dirigeant qui suit son taux de refus suit la qualité de son process commande-livraison-facturation.\n\nRecommandation à donner : demander à sa plateforme un tableau de bord de ces deux taux. C'est un critère de choix supplémentaire, à ajouter à la grille des 7.",
});

/* ────────────────────── MODULE 13 : sanctions, contrôle, archivage ── */

L.moduleSlide(pres, {
  num: "13", title: "Sanctions, contrôle et archivage",
  duration: "45 min",
  points: [
    "Les montants relevés par la loi de finances pour 2026",
    "Le droit à l'erreur : ce qu'il couvre exactement",
    "La tolérance de démarrage annoncée par la DGFiP, et ses limites",
    "L'archivage 10 ans et la piste d'audit fiable",
  ],
  notes: "Module à traiter avec justesse : ni dramatisation, ni minimisation.\n\nLes sanctions ont été relevées, mais l'administration a annoncé une tolérance de démarrage. Les deux informations doivent être données ensemble, sinon on désinforme.",
});

L.tableSlide(pres, {
  kicker: "Loi de finances 2026",
  title: "Les sanctions ont été relevées",
  head: ["Manquement", "Montant avant", "Montant depuis la LF 2026", "Plafond annuel"],
  colW: [4.4, 2.2, 2.9, 2.59],
  rows: [
    ["Défaut d'émission au format électronique", "15 € / facture", { text: "50 € / facture", bold: true, color: C.red }, "15 000 €"],
    ["Défaut de transmission e-reporting", "250 € / transmission", { text: "500 € / transmission", bold: true, color: C.red }, "15 000 €"],
    ["Défaut de raccordement à une plateforme (réception)", "—", { text: "500 € après mise en demeure non suivie sous 3 mois", bold: true, color: C.red }, "+ 1 000 € par trimestre supplémentaire"],
  ],
  accent: C.orangeDeep,
  foot: "Le plafond annuel de 15 000 € s'applique par entreprise et par année civile, aux amendes de facturation et aux pénalités d'e-reporting cumulées.",
  notes: "8 min.\n\nDonner les montants sans dramatiser. Pour une TPE qui émet 300 factures par an, un défaut généralisé plafonne à 15 000 € — c'est significatif mais ce n'est pas une menace existentielle.\n\nLe risque réel n'est pas l'amende, il est ailleurs :\n- Vos gros clients ne pourront plus vous payer si vos factures sont rejetées.\n- Votre TVA déductible sera plus difficile à justifier.\n- Votre trésorerie subit les délais de rejet et de réémission.\n\nC'est cet argument-là qui fait bouger un dirigeant, plus que l'amende.\n\nLe défaut de raccordement mérite d'être souligné : la procédure est graduée (mise en demeure, puis 500 €, puis 1 000 € par trimestre). C'est celui qui concerne le plus directement les stagiaires aujourd'hui, puisque l'obligation de réception est déjà en vigueur.",
});

L.compareSlide(pres, {
  kicker: "Ce qui vous protège",
  title: "Droit à l'erreur et tolérance de démarrage",
  left: {
    title: "Le droit à l'erreur",
    color: C.green,
    dense: true,
    items: [
      "Pas de sanction en cas de PREMIÈRE infraction sur l'année en cours et les trois années précédentes.",
      "À condition qu'elle soit réparée spontanément, ou dans les 30 jours suivant une première demande de l'administration.",
      "C'est un dispositif général, applicable à cette réforme comme aux autres.",
      "Il suppose la bonne foi et la réactivité : la régularisation doit être effective.",
    ],
  },
  right: {
    title: "La tolérance DGFiP de démarrage",
    color: C.violet,
    dense: true,
    items: [
      "L'administration a annoncé qu'elle n'appliquerait pas les sanctions de manière « immédiate, automatique et aveugle » au démarrage.",
      "Condition : des difficultés RÉELLES, DOCUMENTÉES et suivies d'ACTIONS CORRECTIVES.",
      "Critères annoncés : réalité et caractère ponctuel de la difficulté, mesures engagées, proportion du périmètre déjà conforme, rapidité de régularisation.",
      "Un numéro d'assistance dédié est ouvert : 0 806 807 807, du lundi au vendredi de 8h30 à 18h.",
    ],
  },
  foot: "Cette tolérance ne vaut NI report NI suspension de l'obligation. Elle ne couvre ni l'inertie, ni le refus durable, ni le maintien volontaire de circuits parallèles.",
  notes: "10 min. Slide d'équilibre — à traiter avec précision.\n\nLe message en une phrase : « l'administration accompagne ceux qui essaient. Elle ne protège pas ceux qui n'ont rien fait. »\n\nConséquence pratique très concrète à donner : DOCUMENTEZ VOS DÉMARCHES. Gardez trace du devis de la plateforme, de la date de souscription, des échanges avec votre éditeur, du planning de déploiement. En cas de difficulté, c'est cette documentation qui établit votre bonne foi.\n\nC'est un conseil actionnable immédiatement, et il figure dans le plan d'action.\n\nInsister sur le bandeau du bas : la tolérance porte sur les SANCTIONS, pas sur les OBLIGATIONS. L'échéance du 1er septembre 2026 est ferme.",
});

L.cardsSlide(pres, {
  kicker: "Conservation",
  title: "Archivage : ce que la plateforme ne fait pas forcément pour vous",
  cols: 3,
  cards: [
    { n: "1", t: "10 ans et 6 ans", color: C.violet,
      d: "10 ans sur le plan comptable, au titre de l'article L123-22 du Code de commerce.\n\n6 ans sur le plan fiscal, au titre du Livre des procédures fiscales.\n\nEn pratique, retenez 10 ans." },
    { n: "2", t: "La piste d'audit fiable", color: C.orange,
      d: "Article 289 VII du CGI. Vous devez pouvoir démontrer trois choses :\n\n• l'authenticité de l'origine ;\n• l'intégrité du contenu ;\n• la lisibilité.\n\nEt conserver les pièces du cycle : bons de commande, bons de livraison, preuves de paiement." },
    { n: "3", t: "Le piège du contrat", color: C.red,
      d: "Votre plateforme agréée n'est pas, par défaut, un service d'archivage à valeur probante.\n\nBeaucoup de contrats ne couvrent la conservation que pendant la durée de l'abonnement.\n\nQue se passe-t-il si vous résiliez au bout de 3 ans ? Vérifiez, noir sur blanc." },
  ],
  foot: "Question à poser à votre plateforme : « l'archivage à valeur probante est-il inclus, sur quelle durée, et que récupère-t-on en cas de résiliation ? »",
  notes: "8 min.\n\nLe point 3 est le plus utile et le plus souvent négligé. Un dirigeant qui change de plateforme au bout de trois ans peut se retrouver sans accès à ses factures archivées, alors que son obligation de conservation court jusqu'à dix ans.\n\nRappeler que l'obligation de conservation pèse sur L'ENTREPRISE, pas sur le prestataire. Sous-traiter l'archivage ne transfère pas la responsabilité.\n\nRecommandation : conserver en parallèle une copie de ses factures dans son propre système, au moins pour les exercices clos. C'est une précaution de bon sens.",
});

/* ────────────────────────────── MODULE 14 : plan de mise en conformité ── */

L.moduleSlide(pres, {
  num: "14", title: "Votre plan de mise en conformité",
  duration: "1 h",
  color: C.violet,
  points: [
    "Ce qui devait être fait au 1er septembre 2026 : la checklist de rattrapage",
    "La feuille de route sur douze mois, jusqu'au 1er septembre 2027",
    "Les dix erreurs les plus fréquentes, et comment les éviter",
    "Atelier : vous complétez votre plan d'action personnalisé",
  ],
  notes: "Module de clôture, orienté action. Prévoir au moins 25 minutes d'atelier effectif.\n\nC'est le module qui détermine la note de satisfaction : les stagiaires doivent repartir avec quelque chose d'écrit et de daté.",
});

L.listSlide(pres, {
  kicker: "Étape 1 — immédiat",
  title: "Ce qui doit être fait maintenant : la réception",
  lead: "L'obligation est en vigueur depuis le 1er septembre 2026. Si ces cinq points ne sont pas cochés, vous êtes en retard.",
  items: [
    { t: "Vérifier si votre logiciel actuel est déjà raccordé", d: "Appelez votre éditeur de comptabilité ou de facturation. Beaucoup ont noué un partenariat avec une plateforme agréée : vous êtes peut-être déjà couvert sans le savoir." },
    { t: "Choisir votre plateforme agréée", d: "Grille des 7 critères. Vérifiez impérativement l'immatriculation sur la liste officielle d'impots.gouv.fr avant de signer." },
    { t: "Vous inscrire à l'annuaire", d: "C'est ce qui permet à vos fournisseurs de vous trouver. Sans inscription, leurs factures sont rejetées et votre relation fournisseur se dégrade." },
    { t: "Tester une réception réelle", d: "Demandez à un fournisseur déjà équipé de vous adresser une facture. Vérifiez qu'elle arrive, qu'elle est lisible et qu'elle s'intègre à votre compta." },
    { t: "Documenter vos démarches", d: "Devis, date de souscription, échanges avec l'éditeur, planning. C'est ce qui établira votre bonne foi si une difficulté survient." },
  ],
  accent: C.orangeDeep,
  foot: "Si vous n'avez encore rien fait : le point 1 se règle en un appel téléphonique. Faites-le cette semaine.",
  notes: "12 min.\n\nDédramatiser tout en responsabilisant : dans beaucoup de cas, l'éditeur de logiciel a déjà tout préparé et le dirigeant n'en a pas eu conscience. Un appel suffit à le vérifier.\n\nLe point 4 (tester une réception réelle) est celui que personne ne fait et qui évite les mauvaises surprises. Insister.\n\nLe point 5 (documenter) est directement lié à la tolérance DGFiP vue au module 13. Faire le lien explicitement.",
});

L.timelineSlide(pres, {
  kicker: "Étape 2 — douze mois",
  title: "Votre feuille de route jusqu'au 1er septembre 2027",
  steps: [
    { date: "Sept. – déc. 2026", color: C.violet,
      t: "Fiabiliser le référentiel client",
      d: "Collecter les SIREN manquants en commençant par les 20 plus gros clients. Systématiser la collecte à l'ouverture de tout nouveau compte. Vérifier les adresses de livraison." },
    { date: "Janv. – mars 2027", color: C.violet,
      t: "Préparer l'émission",
      d: "Paramétrer les 4 nouvelles mentions dans le logiciel. Choisir le format d'émission, en général Factur-X. Cartographier ses flux e-invoicing, e-reporting et hors champ." },
    { date: "Avril – juin 2027", color: C.orange,
      t: "Tester en conditions réelles",
      d: "Émettre des factures test vers des clients volontaires. Vérifier les statuts du cycle de vie. Mesurer le taux de rejet et corriger les causes." },
    { date: "Juillet – août 2027", color: C.orangeDeep,
      t: "Basculer et former",
      d: "Former les personnes qui facturent. Documenter la procédure interne. Vérifier le dispositif d'archivage. Basculer avant l'échéance, pas le jour même." },
  ],
  foot: "Douze mois, c'est confortable si vous commencez maintenant. C'est très court si vous commencez en juin 2027.",
  notes: "10 min.\n\nLe message central : l'échéance du 1er septembre 2027 paraît lointaine, mais le chantier de fiabilisation des SIREN prend plusieurs mois parce qu'il dépend de la réactivité des clients.\n\nRègle à donner : « basculez en juillet-août 2027, pas le 1er septembre. Vous voulez découvrir vos problèmes pendant que la tolérance existe encore, pas après. »\n\nSur la période avril-juin : le test en conditions réelles est ce qui distingue les entreprises qui passent l'échéance sans heurt de celles qui la subissent.",
});

L.cardsSlide(pres, {
  kicker: "À éviter",
  title: "Les huit erreurs les plus fréquentes",
  cols: 4,
  cards: [
    { n: "1", t: "Attendre 2027", d: "L'obligation de réception est déjà en vigueur. Le retard se prend maintenant, pas dans un an." },
    { n: "2", t: "Croire au PDF", color: C.violet, d: "Un PDF envoyé par mail n'est pas une facture électronique. Cette croyance coûte cher." },
    { n: "3", t: "Attendre le gratuit public", d: "Le PPF n'est plus une plateforme de dépôt. Il n'y aura pas de solution publique gratuite." },
    { n: "4", t: "Ne pas vérifier l'agrément", color: C.violet, d: "Un prestataire peut être OD sans être PA. Vérifiez sur impots.gouv.fr avant de signer." },
    { n: "5", t: "Négliger les SIREN", d: "C'est le premier motif de rejet technique. Le chantier prend des mois." },
    { n: "6", t: "Oublier l'archivage", color: C.violet, d: "10 ans d'obligation, et un contrat de plateforme qui s'arrête à la résiliation." },
    { n: "7", t: "Se croire hors champ", d: "La franchise en base ne dispense pas des obligations de facturation." },
    { n: "8", t: "Basculer au dernier moment", color: C.violet, d: "Le 1er septembre 2027 n'est pas une date de démarrage, c'est une date limite." },
  ],
  foot: "Si vous ne deviez retenir qu'une chose : appelez votre éditeur de logiciel cette semaine. C'est le point de départ de tout.",
  notes: "8 min. Slide de synthèse — la faire commenter par le groupe : « laquelle vous concerne le plus ? »\n\nLes erreurs 1, 2 et 3 sont des erreurs de croyance. Les erreurs 4 à 8 sont des erreurs d'exécution.\n\nLes erreurs de croyance sont les plus dangereuses parce qu'elles empêchent même de commencer. C'est pour cela que le module 8 a insisté dessus.",
});

L.quizSlide(pres, {
  num: "4",
  question: "Un micro-entrepreneur en franchise en base de TVA est-il concerné par la facturation électronique ?",
  options: [
    "Non : il ne collecte pas de TVA, la réforme TVA ne le concerne pas.",
    "Oui : la franchise dispense de collecter la TVA, pas des obligations de facturation. Il reste assujetti.",
    "Uniquement s'il dépasse 25 000 € de chiffre d'affaires.",
    "Uniquement pour ses ventes aux particuliers.",
  ],
  answer: 1,
  explain: "La franchise en base est une dispense de collecte, pas une exclusion du champ. Le micro-entrepreneur doit pouvoir recevoir des factures électroniques depuis le 1er septembre 2026, et les émettre au 1er septembre 2027.",
  notes: "QUIZ DE FIN DE JOURNÉE — 15 min pour les 3 questions.\n\nQuestion la plus importante pour le public micro-entrepreneur présent. Si le groupe se trompe majoritairement, revenir sur le module 8.",
});

L.quizSlide(pres, {
  num: "5",
  question: "Votre facture revient avec le statut « Rejetée » (213). Que s'est-il passé ?",
  options: [
    "Votre client conteste la prestation et refuse de payer.",
    "La plateforme a bloqué la facture pour un motif technique : le client ne l'a jamais vue.",
    "La facture a été payée mais le montant est incorrect.",
    "L'administration fiscale a détecté une anomalie de TVA.",
  ],
  answer: 1,
  explain: "« Rejetée » est un rejet technique par la plateforme : SIREN absent ou erroné, format non conforme, mention manquante, doublon. C'est « Refusée » (210) qui correspond au refus commercial du client.",
  notes: "La confusion rejetée / refusée est la plus fréquente. Si elle persiste, reprendre la slide comparative du module 12.\n\nRappeler l'enjeu managérial : ces deux taux sont des indicateurs de pilotage différents.",
});

L.quizSlide(pres, {
  num: "6",
  question: "La DGFiP a annoncé une tolérance au démarrage. Que signifie-t-elle exactement ?",
  options: [
    "L'obligation de réception est reportée à 2027.",
    "Les sanctions ne seront pas appliquées de façon automatique aux entreprises de bonne foi rencontrant des difficultés réelles et documentées.",
    "Les entreprises de moins de 10 salariés sont exemptées jusqu'en 2028.",
    "Les amendes sont annulées pendant la première année.",
  ],
  answer: 1,
  explain: "La tolérance porte sur les SANCTIONS, pas sur les OBLIGATIONS. Elle ne vaut ni report ni suspension, et ne couvre ni l'inertie ni le refus durable d'entrer dans le dispositif. D'où l'importance de documenter ses démarches.",
  notes: "Question de vigilance : il ne faut surtout pas que les stagiaires repartent en pensant que l'échéance est reportée.\n\nRedire la phrase clé : « l'administration accompagne ceux qui essaient, elle ne protège pas ceux qui n'ont rien fait ».",
});

/* ═════════════════════════════════════════════════════════════ CLÔTURE ══ */

L.cardsSlide(pres, {
  kicker: "Synthèse des deux journées",
  title: "Les six décisions que vous emportez",
  cols: 3,
  cards: [
    { n: "1", t: "Demander votre relevé de carrière", color: C.orange,
      d: "Sur info-retraite.fr. Gratuit, dix minutes. C'est le préalable à toute décision retraite : il révèle les régimes dormants." },
    { n: "2", t: "Trancher la date de liquidation", color: C.orange,
      d: "Avant ou après le 1er janvier 2027 ? Critique entre l'âge légal et 67 ans. Neutre, voire favorable, au-delà de 67 ans." },
    { n: "3", t: "Ajuster votre prélèvement à la source", color: C.orange,
      d: "Dès le premier mois de cumul, pour éviter une régularisation lourde et anticiper l'effet de seuil CSG en N+2." },
    { n: "4", t: "Appeler votre éditeur de logiciel", color: C.violet,
      d: "Une question : à quelle plateforme agréée êtes-vous adossé ? Dans beaucoup de cas, vous êtes déjà couvert." },
    { n: "5", t: "Lancer la collecte des SIREN", color: C.violet,
      d: "Vos vingt plus gros clients d'abord. Puis systématiser à l'ouverture de tout nouveau compte client." },
    { n: "6", t: "Documenter vos démarches", color: C.violet,
      d: "Devis, dates, échanges, planning. C'est ce qui établit votre bonne foi au regard de la tolérance de démarrage." },
  ],
  foot: "Ces six décisions sont reprises dans votre plan d'action personnalisé. Complétez les échéances avant de partir.",
  notes: "10 min.\n\nSlide de synthèse générale. La commenter en reliant à chaque fois au cas pratique correspondant — les stagiaires se souviennent des personnages (Bernard, Denis, Léa) mieux que des règles.\n\nPuis reprendre le paperboard des questions du tour de table du jour 1 et vérifier une par une qu'elles ont trouvé réponse. C'est le geste qui pèse le plus lourd dans l'évaluation à chaud.",
});

L.listSlide(pres, {
  kicker: "Après la formation",
  title: "Votre SAV 12 mois : comment l'utiliser",
  lead: "L'accompagnement ne s'arrête pas ce soir. Il est inclus dans votre inscription, pendant douze mois.",
  items: [
    { t: "Les questions qui appellent une vérification individuelle", d: "Montage de cessation d'activité, arbitrage sur une date de liquidation, régime exact d'une opération exonérée : ce sont des sujets à traiter dossier par dossier." },
    { t: "Les points que les décrets vont préciser", d: "Plusieurs modalités de la réforme du cumul au 1er janvier 2027 relèvent de décrets d'application. Nous vous informerons dès leur publication." },
    { t: "Les difficultés rencontrées à la mise en œuvre", d: "Rejet de facture, refus de raccordement, désaccord avec une plateforme, question de paramétrage : appelez, c'est prévu pour cela." },
    { t: "La revue de votre plan d'action", d: "Un point d'étape est possible dans les mois qui suivent, pour vérifier que les échéances que vous avez fixées ont été tenues." },
  ],
  accent: C.violet,
  foot: "Agence SCFR — 3 rue Émile Beley, 25460 Étupes · Tél. 03 81 94 61 32 · www.scfr.fr",
  notes: "5 min.\n\nInsister sur le point 2 : les décrets d'application de la réforme du cumul ne sont pas tous publiés. C'est une raison honnête et concrète d'utiliser le SAV, et cela protège le formateur d'avoir à trancher aujourd'hui des points qui ne sont pas encore tranchés.\n\nDonner les modalités pratiques de contact et rappeler que le SAV court sur 12 mois à compter de la fin de la formation.",
});

L.listSlide(pres, {
  kicker: "Avant de partir",
  title: "Trois documents à compléter",
  items: [
    { t: "L'évaluation des acquis", d: "Annexe D de votre livret. Vingt questions, quinze minutes. Elle est comparée à votre test de positionnement de la première matinée : c'est votre progression qui est mesurée, pas votre niveau." },
    { t: "Le questionnaire de satisfaction à chaud", d: "Annexe E. Il est anonyme et il est lu. Vos remarques font évoluer le contenu de la session suivante — c'est une exigence de notre certification Qualiopi." },
    { t: "Votre plan d'action personnalisé", d: "Annexe C. Vérifiez que chaque ligne porte une échéance et un responsable. C'est le document que vous emportez et qui servira de base au SAV." },
  ],
  accent: C.orange,
  foot: "La feuille d'émargement de la seconde demi-journée est à signer avant votre départ. L'attestation de fin de formation vous sera adressée sous huit jours.",
  notes: "20 min au total pour les trois documents.\n\nOrdre recommandé : évaluation des acquis d'abord (concentration encore disponible), puis plan d'action, puis satisfaction en dernier.\n\nSur l'évaluation des acquis : bien préciser que ce n'est pas une note et qu'il n'y a pas d'échec. C'est la comparaison avec le test d'entrée qui compte.\n\nNe pas oublier l'émargement — c'est un point de contrôle Qualiopi et il est vite oublié dans la précipitation du départ.",
});

L.closingSlide(pres, {
  title: "Merci pour ces deux journées.",
  lines: [
    "Vous savez désormais si votre fenêtre de liquidation se ferme le 31 décembre.",
    "Vous savez ce que vous devez faire avant le 1er septembre 2027.",
    "Vous repartez avec un plan d'action daté, et douze mois de SAV.",
    "Le premier geste est le plus simple : votre relevé de carrière, et un appel à votre éditeur.",
  ],
  contact: "3 rue Émile Beley\n25460 Étupes\n\nTél. 03 81 94 61 32\nwww.scfr.fr\n\nOrganisme de formation\ncertifié Qualiopi",
  notes: "Clôture — 5 min.\n\nRemercier nommément si possible. Rappeler le SAV et la disponibilité.\n\nNe pas terminer sur une slide administrative : terminer sur cette slide, en redonnant les deux gestes simples. C'est ce que les stagiaires répètent le soir même à leur entourage, et c'est ce qui déclenche le bouche-à-oreille.\n\nRécupérer les feuilles d'émargement et les questionnaires avant le départ.",
});

/* ══════════════════════════════════════════════════════════════ SORTIE ══ */

const out = process.argv[2] || "dist/SCFR-Formation-2026-Support-Formateur.pptx";
pres.writeFile({ fileName: out }).then(() => console.log("✓ Deck généré :", out));
