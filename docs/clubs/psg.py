import re,sys
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.boundsPen import BoundsPen
s=open(__import__('os').path.dirname(__file__)+'/../../app/Resources/shop/logo-sochaux-retro.svg').read()
ds=re.findall(r' d="([^"]*)"',s); shield=ds[0]; sp=re.split(r'(?= M)',ds[1]); ring=''.join(sp[:2])
NAVY,RED,WHITE='#004170','#DA291C','#FFFFFF'
f=TTFont(__import__('os').path.dirname(__file__)+'/SairaStencilOne-Regular.ttf'); gs=f.getGlyphSet(); cmap=f.getBestCmap(); upm=f['head'].unitsPerEm
def text(t,size,cx,base,maxw):
    adv=sum(f['hmtx'][cmap[ord(c)]][0] for c in t)*size/upm
    if adv>maxw: size*=maxw/adv; adv=maxw
    x=cx-adv/2; sc=size/upm; out=''
    for c in t:
        g=cmap[ord(c)]; pen=SVGPathPen(gs)
        gs[g].draw(TransformPen(pen,(sc,0,0,-sc,x,base))); out+=pen.getCommands(); x+=f['hmtx'][g][0]*sc
    return out
title=text(sys.argv[1] if len(sys.argv)>1 else 'PSG rétro',230,621,345,700)
year=text('1970',130,621,1172,230)
P=lambda pts:'M'+' L'.join(f'{x},{y}' for x,y in pts)+' Z'
c=621
tower=[
 P([(c,425),(c+13,500),(c-13,500)]),
 P([(c-30,508),(c+30,508),(c+24,532),(c-24,532)]),
 P([(c-20,540),(c+20,540),(c+50,690),(c-50,690)]),
 P([(c-82,698),(c+82,698),(c+72,726),(c-72,726)]),
 P([(c-64,734),(c+64,734),(c+108,838),(c-108,838)]),
 P([(c-156,846),(c+156,846),(c+142,880),(c-142,880)]),
 f'M{c-136},888 L{c+136},888 L{c+245},1012 L{c+150},1012 Q{c},868 {c-150},1012 L{c-245},1012 Z',
]
cuts=[  # facettes en creux (style du lion)
 P([(c-3,548),(c+3,548),(c+3,684),(c-3,684)]),
 P([(c-30,684),(c,600),(c+30,684),(c+20,684),(c,628),(c-20,684)]),
 P([(c-4,742),(c+4,742),(c+4,832),(c-4,832)]),
 P([(c-90,832),(c-40,750),(c-30,750),(c-76,832)]),
 P([(c+90,832),(c+40,750),(c+30,750),(c+76,832)]),
 P([(c-210,1004),(c-120,896),(c-104,896),(c-196,1004)]),
 P([(c+210,1004),(c+120,896),(c+104,896),(c+196,1004)]),
]
svg=f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1242 1407">
<path fill="{NAVY}" fill-rule="evenodd" d="{shield}"/>
<path fill="{RED}" fill-rule="evenodd" d="{ring}"/>
<path fill="{WHITE}" d="{title}"/>
<g transform="translate(621 735) scale(1.22 1.1) translate(-621 -735)"><path fill="{WHITE}" d="{' '.join(tower)}"/>
<path fill="{NAVY}" d="{' '.join(cuts)}"/></g>
<path fill="{WHITE}" d="{year}"/>
</svg>'''
open(sys.argv[2] if len(sys.argv)>2 else 'psg.svg','w').write(svg)
