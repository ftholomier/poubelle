# -*- coding: utf-8 -*-
"""Genere la page HTML qui contient tous les cartons et incrustations du film.
   Polices et logos sont inlines en base64 : aucun appel reseau, rendu identique
   a celui du site (memes woff2, memes jetons de couleur)."""
import base64, os, json

RACINE = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(
    os.path.abspath(__file__)))), 'public')
SORTIE = os.environ.get('SORTIE') or os.path.join(
    os.path.dirname(os.path.abspath(__file__)), 'build')
os.makedirs(os.path.join(SORTIE, 'html'), exist_ok=True)
os.makedirs(os.path.join(SORTIE, 'cards'), exist_ok=True)
os.makedirs(os.path.join(SORTIE, 'clips'), exist_ok=True)


def b64(chemin, mime):
    with open(chemin, 'rb') as f:
        return 'data:%s;base64,%s' % (mime, base64.b64encode(f.read()).decode())


BRICOLAGE = b64(RACINE + '/assets/fonts/bricolage-40c2cb34.woff2', 'font/woff2')
BRICO_EXT = b64(RACINE + '/assets/fonts/bricolage-fdb6b064.woff2', 'font/woff2')
MANROPE = b64(RACINE + '/assets/fonts/manrope-bcbadccc.woff2', 'font/woff2')
MANR_EXT = b64(RACINE + '/assets/fonts/manrope-7965cb05.woff2', 'font/woff2')
LOGO = b64(RACINE + '/assets/img/ioio-logo.png', 'image/png')
L_CARNOT = b64(RACINE + '/assets/img/ioio-carnot.png', 'image/png')
L_GRANVELLE = b64(RACINE + '/assets/img/ioio-granvelle.png', 'image/png')

# ---------------------------------------------------------------- cartons
CARTONS = {}

CARTONS['intro'] = '''
<div class="plan bg-ink">
  <div class="yoyos">
    <span class="fil" style="left:38%"><i style="animation-delay:.0s"></i></span>
    <span class="fil" style="left:50%;height:46%"><i style="animation-delay:.25s"></i></span>
    <span class="fil" style="left:62%;height:38%"><i style="animation-delay:.5s"></i></span>
  </div>
</div>'''

CARTONS['titre'] = '''
<div class="plan bg-ink centre">
  <div class="pile">
    <h1 class="geant">LE <em>iOiO</em></h1>
    <span class="regle"></span>
    <p class="surtitre">COWORKING &amp; BUREAUX PRIVÉS — BESANÇON</p>
  </div>
</div>'''


def chapitre(num, nom, couleur, logo, lignes):
    return '''
<div class="plan bg-ink chapitre">
  <img class="badge" src="%s" alt="">
  <div class="chap-txt">
    <p class="surtitre" style="color:%s">LIEU %s</p>
    <h2 class="titre-chap" style="color:%s">%s</h2>
    <ul class="details">%s</ul>
  </div>
</div>''' % (logo, couleur, num, couleur,
             nom, ''.join('<li>%s</li>' % l for l in lignes))


CARTONS['chap-carnot'] = chapitre(
    '01', 'CARNOT', '#FFD100', L_CARNOT,
    ['11 avenue Carnot', '70 m² · 4 bureaux privés', 'Cour intérieure, calme absolu'])
CARTONS['chap-granvelle'] = chapitre(
    '02', 'GRANVELLE', '#12B39A', L_GRANVELLE,
    ['3 rue Granvelle', '100 m² · bureaux &amp; open space', 'Face au parc Granvelle'])


def mot(texte, fond, encre, accent):
    return '''
<div class="plan centre" style="background:%s">
  <div class="pile">
    <span class="tiret" style="background:%s"></span>
    <h2 class="mot" style="color:%s">%s</h2>
  </div>
</div>''' % (fond, accent, encre, texte)


CARTONS['mot-charges'] = mot('CHARGES<br>COMPRISES', '#FFD100', '#0E0E0E', '#0E0E0E')
CARTONS['mot-internet'] = mot('INTERNET<br>TRÈS HAUT DÉBIT', '#0E0E0E', '#FFF8EA', '#FFD100')
CARTONS['mot-menage'] = mot('MÉNAGE<br>INCLUS', '#12B39A', '#0E0E0E', '#0E0E0E')
CARTONS['mot-reunion'] = mot('SALLE<br>DE RÉUNION', '#FFF8EA', '#0E0E0E', '#12B39A')
CARTONS['mot-acces'] = mot('ACCÈS<br>24 H / 24', '#0E0E0E', '#FFD100', '#FFF8EA')
CARTONS['mot-humeur'] = mot('BONNE HUMEUR<br>À VOLONTÉ', '#FFD100', '#0E0E0E', '#0E0E0E')

CARTONS['rupture'] = '''
<div class="plan bg-ink centre">
  <h2 class="phrase">TOUT EST COMPRIS<em>.</em></h2>
</div>'''

CARTONS['zero'] = '''
<div class="plan bg-ink centre">
  <div class="pile">
    <h2 class="chiffre-geant">0 €</h2>
    <p class="sous-chiffre">DE FRAIS CACHÉS</p>
  </div>
</div>'''

CARTONS['venez'] = '''
<div class="plan bg-ink centre">
  <h2 class="phrase">VENEZ VOIR<em>.</em></h2>
</div>'''

CARTONS['place'] = '''
<div class="plan bg-ink centre">
  <h2 class="phrase">ON VOUS GARDE<br>UNE PLACE<em>.</em></h2>
</div>'''

CARTONS['url'] = '''
<div class="plan bg-ink centre">
  <h2 class="phrase url">www.ioio.fr</h2>
</div>'''

CARTONS['fin'] = '''
<div class="plan bg-ink fin">
  <img class="logo-fin" src="%s" alt="">
  <div class="fin-txt">
    <p class="fin-url">www.ioio.fr</p>
    <p class="fin-tel">06 09 15 25 73</p>
    <span class="regle"></span>
    <p class="fin-adr">11 avenue Carnot &nbsp;·&nbsp; 3 rue Granvelle<br>25000 Besançon</p>
  </div>
</div>''' % LOGO

# ------------------------------------------------------- incrustations
def phrase_bas(l1, l2=''):
    deux = '<br>%s' % l2 if l2 else ''
    return '''
<div class="plan transparent">
  <div class="voile"></div>
  <div class="phrase-bas"><h3>%s%s</h3></div>
</div>''' % (l1, deux)


def etiquette(titre, note, couleur):
    return '''
<div class="plan transparent">
  <div class="voile voile-fin"></div>
  <div class="tiers">
    <span class="puce" style="background:%s"></span>
    <div>
      <p class="tiers-titre">%s</p>
      <p class="tiers-note">%s</p>
    </div>
  </div>
</div>''' % (couleur, titre, note)


def chiffre(valeur, note):
    return '''
<div class="plan transparent">
  <div class="voile voile-fort"></div>
  <div class="bloc-chiffre">
    <h3 class="chiffre">%s</h3>
    <p class="chiffre-note">%s</p>
  </div>
</div>''' % (valeur, note)


JAUNE, VERT = '#FFD100', '#12B39A'
INCRUSTS = {
    'l01': phrase_bas('Un bureau qui donne envie'),
    'l02': phrase_bas('d’y aller le lundi.'),
    'l03': phrase_bas('Deux adresses'),
    'l04': phrase_bas('au centre-ville de Besançon.'),
    'l05': phrase_bas('Tout le reste est déjà là.'),

    't01': etiquette('Bureau privé', 'Carnot · à partir de 320 € HT/mois', JAUNE),
    't02': etiquette('Salle de réunion', 'Vidéo-projection, comprise dans le loyer', JAUNE),
    't03': etiquette('Espace détente', 'Cuisine toute équipée, douche et WC', JAUNE),
    't04': etiquette('Parties communes', 'Tramway à 50 m, parc Micaud à deux pas', JAUNE),
    't05': etiquette('Accès badge', '7 j/7, 24 h/24', JAUNE),

    't06': etiquette('Poste en open space', 'Granvelle · à partir de 150 € HT/mois', VERT),
    't07': etiquette('Bureau privé', 'Granvelle · à partir de 350 € HT/mois', VERT),
    't08': etiquette('Coin détente', 'Fauteuils, casier à clé, vraie pause', VERT),
    't09': etiquette('Mezzanine', 'Pour les appels et les jours de concentration', VERT),

    'n01': chiffre('2 adresses', 'Carnot &amp; Granvelle · 170 m² au total'),
    'n02': chiffre('21 postes', '7 bureaux privés &amp; 14 postes en open space'),
    'n03': chiffre('150 € HT', 'par mois et par poste, tout compris'),
}

CSS = '''
@font-face{font-family:'Bricolage Grotesque';font-weight:400 800;font-display:block;
  src:url(%s) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+2000-206F,U+20AC;}
@font-face{font-family:'Bricolage Grotesque';font-weight:400 800;font-display:block;
  src:url(%s) format('woff2');unicode-range:U+0100-02BA,U+1E00-1E9F,U+2020,U+20A0-20C0;}
@font-face{font-family:'Manrope';font-weight:400 800;font-display:block;
  src:url(%s) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+2000-206F,U+20AC;}
@font-face{font-family:'Manrope';font-weight:400 800;font-display:block;
  src:url(%s) format('woff2');unicode-range:U+0100-02BA,U+1E00-1E9F,U+2020,U+20A0-20C0;}

:root{--ink:#0E0E0E;--cream:#FFF8EA;--yellow:#FFD100;--teal:#12B39A;--sand:#EDE5D5;}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:transparent;}
.plan{position:relative;width:1920px;height:1080px;overflow:hidden;
  font-family:'Manrope',sans-serif;-webkit-font-smoothing:antialiased;}
.bg-ink{background:var(--ink);}
.transparent{background:transparent;}
.centre{display:flex;align-items:center;justify-content:center;text-align:center;}
.pile{display:flex;flex-direction:column;align-items:center;gap:34px;}

h1,h2,h3{font-family:'Bricolage Grotesque',sans-serif;font-weight:800;
  letter-spacing:-.035em;line-height:.92;text-transform:uppercase;}

/* --- intro : les yoyos de la marque --- */
.yoyos{position:absolute;inset:0;}
.fil{position:absolute;top:0;height:40%%;width:3px;background:var(--cream);opacity:.85;}
.fil i{position:absolute;left:50%%;bottom:-85px;width:170px;height:170px;margin-left:-85px;
  border-radius:50%%;border:46px solid var(--yellow);box-sizing:border-box;}
.fil:nth-child(2) i{width:230px;height:230px;margin-left:-115px;bottom:-115px;border-width:62px;}

/* --- carton titre --- */
.geant{font-size:300px;color:var(--cream);}
.geant em{font-style:normal;color:var(--yellow);text-transform:none;}
.regle{display:block;width:180px;height:7px;background:var(--yellow);}
.surtitre{font-weight:800;font-size:30px;letter-spacing:.34em;color:var(--cream);}

/* --- cartons de chapitre --- */
.chapitre{display:flex;align-items:center;gap:110px;padding:0 150px;}
.badge{width:430px;height:430px;flex:0 0 430px;}
.chap-txt{display:flex;flex-direction:column;gap:26px;}
.titre-chap{font-size:188px;}
.details{list-style:none;display:flex;flex-direction:column;gap:12px;margin-top:10px;}
.details li{font-size:38px;font-weight:500;color:var(--cream);opacity:.92;}
.details li::before{content:'—';color:var(--yellow);margin-right:20px;opacity:.6;}

/* --- cartons mots --- */
.mot{font-size:160px;line-height:.94;}
.tiret{display:block;width:120px;height:9px;}

/* --- phrases plein cadre --- */
.phrase{font-size:168px;color:var(--cream);line-height:.96;}
.phrase em{font-style:normal;color:var(--yellow);}
.phrase.url{font-family:'Manrope',sans-serif;font-weight:800;text-transform:none;
  font-size:150px;color:var(--yellow);letter-spacing:-.02em;}
.chiffre-geant{font-size:360px;color:var(--yellow);}
.sous-chiffre{font-weight:800;font-size:46px;letter-spacing:.3em;color:var(--cream);}

/* --- carton de fin --- */
.fin{display:flex;align-items:center;justify-content:center;gap:120px;}
.logo-fin{width:470px;height:470px;}
.fin-txt{display:flex;flex-direction:column;gap:18px;}
.fin-url{font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:104px;
  color:var(--yellow);letter-spacing:-.03em;}
.fin-tel{font-weight:800;font-size:62px;color:var(--cream);letter-spacing:.02em;}
.fin-txt .regle{margin:16px 0 6px;}
.fin-adr{font-weight:500;font-size:34px;line-height:1.5;color:var(--cream);opacity:.78;}

/* --- incrustations sur photo --- */
.voile{position:absolute;inset:0;
  background:linear-gradient(to top,rgba(14,14,14,.94) 0%%,rgba(14,14,14,.74) 26%%,
    rgba(14,14,14,.12) 56%%,rgba(14,14,14,0) 78%%);}
.voile-fin{background:linear-gradient(to top,rgba(14,14,14,.9) 0%%,rgba(14,14,14,.55) 18%%,
    rgba(14,14,14,0) 44%%);}
.voile-fort{background:linear-gradient(100deg,rgba(14,14,14,.93) 0%%,rgba(14,14,14,.8) 42%%,
    rgba(14,14,14,.18) 78%%,rgba(14,14,14,0) 100%%);}
.phrase-bas{position:absolute;left:140px;bottom:140px;right:200px;}
.phrase-bas h3{font-size:126px;color:var(--cream);line-height:1.02;text-transform:none;
  letter-spacing:-.028em;}
.tiers{position:absolute;left:140px;bottom:132px;display:flex;align-items:center;gap:30px;}
.puce{width:14px;height:92px;flex:0 0 14px;}
.tiers-titre{font-family:'Bricolage Grotesque',sans-serif;font-weight:800;font-size:62px;
  color:var(--cream);letter-spacing:-.02em;}
.tiers-note{font-weight:600;font-size:33px;color:var(--cream);opacity:.76;margin-top:8px;}
.bloc-chiffre{position:absolute;left:140px;top:50%%;transform:translateY(-50%%);max-width:1050px;}
.chiffre{font-size:210px;color:var(--yellow);line-height:.9;}
.chiffre-note{font-weight:600;font-size:42px;color:var(--cream);margin-top:30px;opacity:.9;}
''' % (BRICOLAGE, BRICO_EXT, MANROPE, MANR_EXT)

blocs = []
for nom, html in list(CARTONS.items()) + list(INCRUSTS.items()):
    blocs.append('<div class="cadre" id="%s">%s</div>' % (nom, html))

page = '''<!doctype html><html lang="fr"><head><meta charset="utf-8">
<title>Cartons du film iOiO</title><style>%s</style></head>
<body>%s</body></html>''' % (CSS, '\n'.join(blocs))

with open(os.path.join(SORTIE, 'html', 'cartons.html'), 'w', encoding='utf-8') as f:
    f.write(page)

noms = {'cartons': list(CARTONS), 'incrusts': list(INCRUSTS)}
with open(os.path.join(SORTIE, 'html', 'noms.json'), 'w', encoding='utf-8') as f:
    json.dump(noms, f, ensure_ascii=False, indent=1)

print('page ecrite :', len(page) // 1024, 'Ko —',
      len(CARTONS), 'cartons,', len(INCRUSTS), 'incrustations')
