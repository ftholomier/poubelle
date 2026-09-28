"""Génère docs/prospection/appel-elu.html : la fiche d'appel pour obtenir un rendez-vous avec un élu
(script, phrases clés, réponses aux objections, suivi), à la charte terricom (A4 portrait). Aucun tarif.
Usage : python3 docs/prospection/generer.py && node scripts/appel-elu.mjs [--apercus <dossier>]
"""
from pathlib import Path

ICI = Path(__file__).parent
HEAD = (ICI.parent / 'dossier' / 'dossier.html').read_text()
POLICES = HEAD[HEAD.index('@font-face'):HEAD.index('@page')]


def pied(n):
    return (f'<div class="foot"><span class="brand">terricom<i>.</i></span><span>Appeler un élu · fiche de prospection</span>'
            f'<span class="num">{n}</span></div>')


pages = []

# 1 · couverture
pages.append(('dark cover', '''
<div class="blob" style="width:150mm;height:150mm;right:-45mm;top:-55mm;background:var(--green)"></div>
<div class="blob" style="width:70mm;height:70mm;left:-25mm;bottom:-30mm;background:#23332C"></div>
<div class="top"><span>terricom.fr</span><span>Document interne · fiche d’appel</span></div>
<div class="logo"><span class="mark"><span>t</span></span><span class="wm">terricom<i>.</i></span></div>
<span class="eyebrow" style="color:var(--amber);margin-top:26mm">Prospection des collectivités</span>
<h1>Appeler un élu, <em>décrocher le rendez-vous.</em></h1>
<p class="lead">Un seul objectif au téléphone : obtenir 30 minutes pour montrer terricom. Pas de vente, pas de prix, pas de démonstration par téléphone. On donne envie, on propose deux créneaux, on raccroche.</p>
<div class="steps">
<div><b>1</b><span>Le script d’appel</span></div>
<div><b>2</b><span>Dix phrases clés</span></div>
<div><b>3</b><span>Les objections</span></div>
<div><b>4</b><span>Après l’appel</span></div>
</div>
<div class="covnote">Qui appeler : le ou la vice-président(e) au développement économique de la communauté de communes, à défaut le président ou la présidente ; pour une commune seule, le maire ou l’adjoint au commerce.</div>
''', False))

# 2 · le script
pages.append(('', '''
<span class="eyebrow">1 · Le script d’appel</span>
<h2>Une minute, <em>quatre temps.</em></h2>
<p class="lead-d">À dire avec vos mots, pas à lire. Les crochets sont à adapter avant l’appel.</p>
<div class="script">
<div class="st1"><span class="k">Se présenter</span>
<p>« Bonjour Monsieur / Madame [Nom], [Prénom Nom], je suis l’un des fondateurs de terricom. Je vous appelle au sujet du commerce et de l’économie locale de [nom de la communauté de communes]. Vous avez une minute ? »</p></div>
<div class="st1"><span class="k">Dire pourquoi</span>
<p>« Nous avons créé une plateforme d’animation économique pour les territoires : une vitrine en ligne pour toutes les entreprises de vos communes, commerçants, artisans, producteurs, et les outils pour que la communauté de communes et chaque mairie les fassent vivre toute l’année. »</p></div>
<div class="st1"><span class="k">Donner envie</span>
<p>« Ce qui plaît aux élus, c’est que tout est déjà prêt : les [nombre] entreprises de votre territoire y sont dès le départ, grâce au registre officiel. Et vous lancez une campagne, par exemple pour Noël, en une phrase. »</p></div>
<div class="st1 go"><span class="k">Demander le rendez-vous</span>
<p>« Je vous propose trente minutes pour vous le montrer, dans vos locaux ou en visio. Seriez-vous disponible [jour] à [heure], ou plutôt [jour] à [heure] ? »</p></div>
</div>
<div class="two">
<div class="box"><h4>Si c’est oui</h4><p>« Parfait. Je vous envoie une confirmation par courriel aujourd’hui. Souhaitez-vous que j’invite aussi votre agent chargé du développement économique ? »</p></div>
<div class="box"><h4>Si vous tombez sur le secrétariat</h4><p>« Je souhaite proposer à [M. / Mme Nom] une présentation de trente minutes d’un outil pour le commerce local de la communauté. Quel est le meilleur moment pour le ou la joindre, ou puis-je vous proposer deux créneaux ? »</p></div>
</div>
<div class="box amber"><h4>Message sur répondeur (20 secondes)</h4><p>« Bonjour [M. / Mme Nom], [Prénom Nom] de terricom. Je souhaite vous présenter en trente minutes une plateforme qui met en vitrine toutes les entreprises de [territoire] et aide la communauté à animer son commerce local. Je vous rappelle [jour] ; vous pouvez aussi me joindre au [numéro]. Belle journée. »</p></div>
''', True))

# 3 · dix phrases clés
phrases = [
    ('Toutes vos entreprises, en vitrine.', 'Chaque commerce, artisan et producteur de vos communes a sa fiche, visible sur la carte, dans la recherche et sur Google.'),
    ('Tout est prêt dès le premier jour.', 'Les entreprises viennent du registre officiel SIRENE, mis à jour chaque mois : aucune saisie pour vos services.'),
    ('Une fiche offerte par la collectivité.', 'Vos commerçants reçoivent un cadeau de leur communauté, pas un abonnement de plus ni un démarchage.'),
    ('Une campagne en une phrase.', 'Noël, fête des mères, quinzaine commerciale : l’assistant prépare la page, la sélection des commerces et le calendrier.'),
    ('Aucune commune laissée de côté.', 'Chaque commune a sa page, et chaque mairie son espace pour agir ; la communauté coordonne.'),
    ('Une politique économique qui se voit.', 'Les habitants voient ce que fait la communauté pour leurs commerces, et les commerçants aussi.'),
    ('Des résultats à présenter au conseil.', 'Visites, recherches, appels, itinéraires, commune par commune : le rapport se génère en un clic.'),
    ('Pas une place de marché de plus.', 'Pas de vente en ligne imposée, aucune commission : terricom amène les habitants à pousser la porte des commerces.'),
    ('Simple pour les commerçants.', 'Ils publient une nouveauté en une phrase ; l’assistant rédige pour leur fiche et leurs réseaux.'),
    ('Nous vous accompagnons.', 'Lancement, invitation des entreprises, animation toute l’année : vous n’êtes pas seuls avec un outil.'),
]
items = ''.join(f'<li><span class="n">{i}</span><div><b>{t}</b><p>{d}</p></div></li>' for i, (t, d) in enumerate(phrases, 1))
pages.append(('', f'''
<span class="eyebrow">2 · Dix phrases clés</span>
<h2>Ce qui fait <em>la force de terricom.</em></h2>
<p class="lead-d">À placer dans la conversation, deux ou trois suffisent. La phrase en gras se retient ; la suite, si l’élu demande.</p>
<ol class="keys">{items}</ol>
<div class="final"><p>Si vous ne deviez en dire qu’une : <b>« Toutes les entreprises de votre territoire en vitrine, et les outils pour les faire vivre toute l’année. »</b></p></div>
''', True))

# 4 · objections + après l'appel
objections = [
    ('« Nous avons déjà un site internet. »', '« terricom ne le remplace pas : c’est la vitrine de toutes vos entreprises, reliée à votre site. Je vous montre la différence en trente minutes. »'),
    ('« Envoyez-moi une documentation. »', '« Avec plaisir, je vous l’envoie aujourd’hui. Elle se comprend beaucoup mieux en la voyant : puis-je vous proposer trente minutes [jour] ou [jour] ? »'),
    ('« Nous n’avons pas de budget. »', '« Je comprends. Le rendez-vous n’engage à rien, et il vous aidera à préparer le prochain budget avec des éléments concrets. »'),
    ('« Nous avons déjà essayé une place de marché, ça n’a pas marché. »', '« C’est justement pour cela que terricom n’en est pas une : aucune vente en ligne imposée aux commerçants, aucune commission. »'),
    ('« Ce n’est pas le bon moment. »', '« Quand serait-il plus opportun ? Je note de vous rappeler à ce moment-là. »'),
    ('(Un maire) « C’est la compétence de la communauté. »', '« Tout à fait, et chaque commune y a son espace. Votre avis compte : puis-je vous le présenter, ou le présenter au vice-président concerné de votre part ? »'),
]
obj = ''.join(f'<div class="obj"><b>{q}</b><p>{r}</p></div>' for q, r in objections)
pages.append(('', f'''
<span class="eyebrow">3 · Les objections</span>
<h2>Une réponse courte, <em>puis revenir au <span style="white-space:nowrap">rendez-vous</span>.</em></h2>
<div class="objs">{obj}</div>
<span class="eyebrow" style="margin-top:3mm">4 · Après l’appel</span>
<div class="two">
<div class="box"><h4>Le courriel de confirmation, le jour même</h4>
<p class="mail"><b>Objet :</b> terricom · notre rendez-vous du [jour]<br><br>Bonjour [Madame / Monsieur Nom],<br><br>Merci pour votre accueil au téléphone. Je vous confirme notre rendez-vous du [jour] à [heure], [lieu ou lien visio], pour vous présenter terricom, la plateforme d’animation économique du territoire.<br><br>Je vous joins un film de présentation de deux minutes ; vous pouvez aussi découvrir terricom sur terricom.fr.<br><br>Bien cordialement,<br>[Prénom Nom] · terricom · [téléphone]</p></div>
<div class="box"><h4>À faire</h4>
<ul class="check">
<li>Noter l’appel dans le suivi commercial (console)</li>
<li>Envoyer le courriel de confirmation</li>
<li>Envoyer l’invitation d’agenda</li>
<li>Préparer la démonstration : nombre d’entreprises et de communes du territoire</li>
<li>Pas de réponse : rappeler sous 3 jours, puis 10 jours</li>
<li>Un refus : noter la raison et la date de relance</li>
</ul></div>
</div>
''', True))

CSS = POLICES + '''
@page { size: A4; margin: 0; }
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body { font-family: 'Instrument', system-ui, sans-serif; color: var(--ink); background: #9a9a9a; font-size: 12.2px; line-height: 1.5; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
@media screen { .page { margin: 12px auto; box-shadow: 0 6px 30px rgba(0,0,0,.25); } }
@media print { body { background: none; } }
.page { width: 210mm; height: 297mm; position: relative; overflow: hidden; background: var(--cream); break-after: page; padding: 16mm 17mm 22mm; display: flex; flex-direction: column; gap: 4mm; }
.page:last-child { break-after: auto; }
.page.dark { background: var(--ink); color: var(--cream); }
.blob { position: absolute; border-radius: 50%; opacity: .9; }
.foot { position: absolute; left: 17mm; right: 17mm; bottom: 9mm; display: flex; justify-content: space-between; align-items: center; font-size: 9px; color: var(--muted); border-top: 1px solid var(--line); padding-top: 2.6mm; }
.foot .brand { font-family: 'Bricolage'; font-weight: 800; font-size: 11px; color: var(--ink); letter-spacing: -0.02em; }
.foot .brand i, .wm i { color: var(--amber); font-style: normal; }
.foot .num { font-weight: 700; color: var(--ink); }
.eyebrow { font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: var(--brick); }
h1, h2, h3, h4 { font-family: 'Bricolage'; letter-spacing: -0.03em; margin: 0; }
h1 em, h2 em { font-style: normal; color: var(--green); }
.dark h1 em { color: var(--amber); }
h2 { font-size: 30px; line-height: 1.02; font-weight: 800; }
.mark { width: 34px; height: 34px; border-radius: 50% 50% 50% 14%; transform: rotate(-45deg); background: var(--amber); display: inline-grid; place-items: center; flex-shrink: 0; }
.mark span { transform: rotate(45deg); font-family: 'Bricolage'; font-weight: 800; font-size: 22px; line-height: 1; color: var(--ink); margin-top: -3px; }
.wm { font-family: 'Bricolage'; font-weight: 800; letter-spacing: -0.05em; line-height: .9; }
.cover .top { position: relative; display: flex; justify-content: space-between; font-size: 10px; color: var(--sage); }
.cover .logo { position: relative; display: flex; align-items: center; gap: 12px; margin-top: 30mm; }
.cover .logo .mark { width: 16mm; height: 16mm; } .cover .logo .mark span { font-size: 38px; }
.cover .logo .wm { font-size: 64px; color: var(--cream); }
.cover h1 { position: relative; font-size: 44px; line-height: 1.02; font-weight: 800; color: var(--cream); max-width: 160mm; margin-top: 3mm; }
.cover .lead { position: relative; font-size: 15px; line-height: 1.5; color: var(--sage2); max-width: 150mm; margin: 4mm 0 0; }
.steps { position: relative; display: grid; grid-template-columns: repeat(4, 1fr); gap: 3mm; margin-top: auto; }
.steps div { border: 1px solid rgba(255,255,255,.25); border-radius: 14px; padding: 4mm; display: flex; flex-direction: column; gap: 2mm; }
.steps b { font-family: 'Bricolage'; font-size: 26px; color: var(--amber); line-height: 1; }
.steps span { font-size: 12px; color: var(--sage2); font-weight: 600; }
.covnote { position: relative; font-size: 11px; color: var(--sage); margin-top: 5mm; max-width: 165mm; }
.lead-d { font-size: 13.5px; line-height: 1.5; color: var(--ink2); margin: 0; }
.script { display: flex; flex-direction: column; gap: 3mm; }
.st1 { background: var(--paper); border: 1px solid var(--line); border-left: 5px solid var(--green); border-radius: 14px; padding: 4mm 5mm; }
.st1.go { border-left-color: var(--amber); background: #FFF6EA; }
.st1 .k { font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--green); }
.st1.go .k { color: var(--brick); }
.st1 p { margin: 1.2mm 0 0; font-size: 13.8px; line-height: 1.5; color: var(--ink); }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
.box { background: var(--paper); border: 1px solid var(--line); border-radius: 14px; padding: 4mm 5mm; }
.box.amber { background: #FBE6C9; border-color: #F0CFA0; }
.box h4 { font-size: 14px; margin-bottom: 1.5mm; }
.box p { margin: 0; font-size: 12px; color: var(--text2); }
.keys { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 1.8mm; }
.keys li { display: grid; grid-template-columns: 30px 1fr; gap: 10px; align-items: start; background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 2.4mm 4mm; }
.keys .n { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 9px; background: var(--ink); color: var(--amber); font-family: 'Bricolage'; font-weight: 800; font-size: 14px; }
.keys b { font-family: 'Bricolage'; font-size: 16px; letter-spacing: -0.02em; }
.keys p { margin: .5mm 0 0; font-size: 11.6px; color: var(--text2); }
.final { background: var(--green); color: var(--cream); border-radius: 16px; padding: 5mm 6mm; }
.final p { margin: 0; font-size: 14px; line-height: 1.5; }
.final b { color: var(--amber); }
.objs { display: grid; grid-template-columns: 1fr 1fr; gap: 3mm; }
.obj { background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 3.5mm 4mm; }
.obj b { font-family: 'Bricolage'; font-size: 13.5px; letter-spacing: -0.01em; }
.obj p { margin: 1mm 0 0; font-size: 11.5px; color: var(--text2); }
.mail { font-size: 11px !important; line-height: 1.45; }
.check { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2mm; }
.check li { font-size: 11.8px; padding-left: 22px; position: relative; color: var(--text2); }
.check li::before { content: ''; position: absolute; left: 0; top: 1px; width: 13px; height: 13px; border: 1.6px solid var(--green); border-radius: 4px; }
'''

html = ['<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>terricom · Appeler un élu</title>', f'<style>{CSS}</style></head><body>']
for i, (cls, corps, numero) in enumerate(pages, start=1):
    html.append(f'<section class="page {cls}">{corps}{pied(i) if numero else ""}</section>')
html.append('</body></html>')
(ICI / 'appel-elu.html').write_text('\n'.join(html))
print(f'{len(pages)} pages → docs/prospection/appel-elu.html')
