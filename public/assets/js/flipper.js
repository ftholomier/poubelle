(()=>{function Od(s,t=10){let e=[];for(let n=0;n<s.length-1;n++){let i=s[Math.max(n-1,0)],r=s[n],a=s[n+1],o=s[Math.min(n+2,s.length-1)];for(let l=0;l<t;l++){let c=l/t,h=c*c,u=h*c;e.push([0,1,2].map(d=>.5*(2*r[d]+(-i[d]+a[d])*c+(2*i[d]-5*r[d]+4*a[d]-o[d])*h+(-i[d]+3*r[d]-3*a[d]+o[d])*u)))}}return e.push(s[s.length-1].slice()),e}var Ul=[[132,280,70],[200,308,62],[270,337,54],[330,362,46],[378,376,40],[388,402,34],[388,442,28],[380,500,20],[360,550,12],[334,582,6],[313,604,2],[302,618,0]];function yh(){let s=[{id:"cadre-g",pts:[[3,292],[3,708]],r:3,e:.42,h:3.6,neon:!0},{id:"bas-g",pts:[[2,722],[30,744],[56,752],[162,810]],r:3,e:.4,h:2.4,neon:!0},{id:"bas-d",pts:[[367,722],[339,744],[313,752],[207,810]],r:3,e:.4,h:2.4,neon:!0},{id:"lanceur-int",pts:[[366,874],[366,482],[362,466],[352,454],[339,447]],r:3,e:.42,h:2.6,neon:!0},{id:"lanceur-ext",pts:[[396,874],[396,470],[392,446],[382,426],[368,404],[358,380],[358,362]],r:3,e:.42,h:3.6,neon:!0},{id:"une-deux-d",pts:[[303,404],[320,352],[324,346],[348,350],[358,362]],r:3,e:.45,h:2.2,neon:!1},{id:"ballon-long-g",pts:[[258,385],[284,334],[281.4,327]],r:3,e:.45,h:2.2,neon:!1},{id:"coin-fer",pts:[[259.4,373.6],[258,385]],r:3,e:.45,h:1.2,neon:!1},{id:"fer-a-cheval",pts:[[224,360],[244.8,331.3],[253.6,321.1],[266.7,310.8],[279.9,316.7],[281.4,327],[259.4,373.6]],r:2.5,e:.45,h:1.6,wire:!0},{id:"fer-haut",pts:[[266.7,310.8],[262,288]],r:3,e:.45,h:1.2,neon:!1},{id:"une-deux-c-g",pts:[[177,279],[180,344]],r:2.5,e:.45,h:1.6,wire:!0},{id:"une-deux-c-d",pts:[[226,300],[224,360]],r:2.5,e:.45,h:1.6,wire:!0},{id:"acces-une-deux",pts:[[163,284],[177,279]],r:3,e:.45,h:1.2,neon:!1},{id:"acces-g",pts:[[128,284],[130,264]],r:3,e:.45,h:2,neon:!1},{id:"acces-d",pts:[[163,284],[161,264]],r:3,e:.45,h:2,neon:!1},{id:"poche-acces",pts:[[112,273],[128,283]],r:3,e:.45,h:1.6,neon:!1},{id:"poche-attaquant",pts:[[82,276],[82,240],[88,232],[106,232],[112,240],[112,273]],r:3,e:.4,h:2.4,neon:!0},{id:"centre-d",pts:[[115,356],[98,312],[82,276]],r:3,e:.45,h:2.2,neon:!1},{id:"centre-g",pts:[[70,372],[60,330],[54,289]],r:3,e:.45,h:2.2,neon:!1},{id:"passe-g",pts:[[22,289],[22,250]],r:3,e:.45,h:2.2,neon:!1},{id:"passe-d",pts:[[54,289],[54,250]],r:3,e:.45,h:2.2,neon:!1},{id:"coin-g",pts:[[3,292],[22,289]],r:3,e:.45,h:2.2,neon:!1},{id:"trick",pts:[[8,376],[9,398],[19,404],[29,401],[34,386]],r:2.5,e:.35,h:1.6,wire:!0},{id:"inlane-g",pts:[[32,671],[125,744]],r:3,e:.45,h:2.2,neon:!0},{id:"inlane-d",pts:[[337,671],[244,744]],r:3,e:.45,h:2.2,neon:!0},{id:"plot-g",pts:[[32,671],[32,594],[42,584],[52,594],[52,671]],r:3,e:.45,h:2.4,neon:!0},{id:"plot-d",pts:[[317,671],[317,594],[327,584],[337,594],[337,671]],r:3,e:.45,h:2.4,neon:!0},{id:"mini",pts:[[140,78],[319,78],[319,240],[245,299],[212.6,299],[140,240],[140,78]],r:3,e:.45,h:0,block:!0}],t=[{x:72,y:410,r:15},{x:35,y:440,r:15},{x:100,y:480,r:15}],e=[{side:"L",a:[88,588],b:[84,668],c:[114,684],screen:[96,630]},{side:"R",a:[281,588],b:[285,668],c:[255,684],screen:[273,630]}],n={a:[334,454],b:[315,504]},i=Math.PI/180,r=[{side:"L",px:129.4,py:754.3,len:47,r1:10,r2:5,rest:34*i,up:-24*i},{side:"R",px:239.5,py:754.3,len:47,r1:10,r2:5,rest:146*i,up:204*i},{side:"L",px:8,py:716,len:42,r1:7,r2:4.5,rest:34*i,up:-22*i,tackle:!0},{side:"R",px:361,py:716,len:42,r1:7,r2:4.5,rest:146*i,up:202*i,tackle:!0},{side:"L",px:170,py:259,len:48,r1:7,r2:4.5,rest:31*i,up:-25*i,lv:1,mini:!0},{side:"R",px:290,py:261,len:48,r1:7,r2:4.5,rest:149*i,up:205*i,lv:1,mini:!0}],a=[{tag:"inlane",ref:0,x:67,y:622,r:9},{tag:"inlane",ref:1,x:302,y:622,r:9},{tag:"outlane",ref:0,x:17,y:640,r:9},{tag:"outlane",ref:1,x:352,y:640,r:9},{tag:"onetwo",ref:0,x:202,y:318,r:12},{tag:"onetwo",ref:1,x:332,y:376,r:11},{tag:"lock",ref:0,x:97,y:254,r:8},{tag:"lock",ref:1,x:262.3,y:337,r:8},{tag:"lock",ref:2,x:343,y:492,r:8},{tag:"trick",x:18.7,y:391,r:8},{tag:"trap",x:34,y:494,r:8}],o={x1:140,x2:319,y1:78,y2:299,walls:[{pts:[[140,126],[140,78],[319,78],[319,236],[290,254.5]],r:3,e:.45},{pts:[[140,147],[140,236],[170,254.5]],r:3,e:.45},{pts:[[181,78],[181,98]],r:2,e:.1,net:!0},{pts:[[277,78],[277,98]],r:2,e:.1,net:!0}],posts:[{x:181,y:98,r:3},{x:277,y:98,r:3},{x:145,y:232,r:4},{x:313,y:232,r:4}],goal:{x1:183,x2:275,y:95,net:80},keeper:{y:118,hw:7,r:5,min:201,max:258},rows:[{line:"def",y:157,offs:[-66,-22,22,66],amp:16,w:1.3,phase:0},{line:"mil",y:183,offs:[-66,-22,22,66],amp:16,w:1.7,phase:2.1},{line:"att",y:208,offs:[-24,24],amp:26,w:1.1,phase:4}],player:{hw:3,r:3},center:229.5,holes:[{x:162,y:114,r:9},{x:295,y:114,r:9}],drainY:292,drop:{x1:190,x2:214,y:312}},l=[Di("passe",[[39,285,0],[39,240,12],[39,195,26],[40,165,36],[46,148,42],[58,140,45],[72,146,48],[90,165,51],[106,195,53],[117,218,55],[124,230,56]],{a:[22,283],b:[54,283]},{lv:1,x:152,y:226,vx:150,vy:70},1.3),Di("dribble",[[138,137,56],[124,130,58],[106,122,60],[84,115,60],[56,112,58],[34,114,54],[20,124,50],[13,150,46],[13,200,40],[13,271,32],[12,340,24],[27,362,20],[12,392,17],[7,450,13],[7,560,8],[20,600,4],[50,612,1],[64,618,0]],{a:[140,147],b:[140,126],lv:1},null,1.3,9),Di("centre",[[92,362,0],[86,332,10],[78,290,26],[70,240,46],[66,190,66],[66,150,82],[70,113,92],[80,85,98],[93,69,102],[122,40,106],[172,25,108],[260,19,110],[347,22,108],[372,45,104],[378,90,98],[378,150,90],[376,210,78],[370,270,60],[360,318,36],[352,348,16],[342,368,0]],{a:[70,372],b:[115,356]},null,1.85),Di("ballon-long",[[280,394,0],[292,366,8],[305,333,18],[316,300,28],[326,262,40],[334,215,52],[340,165,64],[342,125,74],[336,90,82],[318,64,88],[290,52,90],[250,48,92],[200,48,92],[160,50,90],[134,62,88],[120,84,84],[116,110,80],[116,125,78],[116,170,76],[117,220,74],[120,262,72],...Ul],{a:[258,385],b:[303,404]},null,1.85,17),Di("acces",[[146,284,0],[148,270,22],[152,258,42],[156,248,56]],{a:[128,282],b:[163,282]},{lv:1,x:158,y:232,vx:70,vy:40},1.6),Di("trou-g",[[162,114,56],[140,112,62],[124,114,70],[117,140,74],[117,220,72],[120,262,71],...Ul],null,null,1,0),Di("trou-d",[[295,114,56],[320,116,58],[343,124,58],[343,200,54],[344,255,50],[352,290,47],[368,330,44],...Ul.slice(4)],null,null,1,0)];return{walls:s,bumpers:t,slings:e,kicker:n,flippers:r,sensors:a,ramps:l,mini:o,block:{x1:140,x2:319,y1:78,y2:299},plunger:{x:381,top:800,ball:790},gate:{a:[339,447],b:[384,428],n:[-.39,-.92]},drainY:818,deco:{hexes:[[161.9,592.6],[207.3,592.6],[121.8,622.5],[161.9,629.8],[207.3,629.8],[248.3,622.5],[127.7,662.9],[163.7,668.8],[205,668.8],[240.4,662.9]],hexLine:["att","att","mil","mil","mil","mil","def","def","def","def"],goalie:[184.8,747],circle:{x:184.8,y:596,r:43},box:{x1:121.8,x2:247.7,y1:709.5,y2:804},dots:[[156.4,335],[201.4,352.5],[130.6,373],[148.7,390.3],[168,401.7],[274,420.5],[317.4,433],[263.2,443.3],[257.4,464.4],[148.2,540.5],[146.7,561],[39,305]],smallHexes:[[41.9,328.2],[160.5,357.5],[195,376.5],[308.6,456],[42,609],[327,609],[28.7,697],[340.5,697]],pentagons:[[130.6,314.5],[227.8,394],[303.9,555]],chevrons:[[114.5,291.6,-.6],[247.7,376.5,.6],[323.8,521.5,.35]],modes:[[172.2,480.5],[175,501.3]],lamp:[64.7,531.7],plate:{x1:26,x2:66,y1:472,y2:516},mults:[["10X",543],["8X",582],["6X",622.5],["4X",663],["2X",703],["HOLD",741]],clock:[88,841,23],combo:{x1:270,x2:350,y1:829,y2:841},jerseys:{x:325,y:781,dx:13.2,dy:15.4}}}}function Di(s,t,e,n,i,r=null){let o=Od(t,8),l=0,c=o.map((u,d)=>(d&&(l+=Math.hypot(u[0]-o[d-1][0],u[1]-o[d-1][1])),{x:u[0],y:u[1],h:Math.max(0,u[2]),s:l})),h=c.reduce((u,d,f)=>d.h>c[u].h?f:u,0);return{name:s,pts:c,length:l,apex:h,mouth:e?{lv:0,...e}:null,end:n,width:i,wire:r===null?null:r*8}}var Nl=1/1e3,vh=1500,kd=1600,zd=.25,Fl=3600,Mh=.1,Hd=.1,Vd=.65,Gd=45,Wd=26,Xd=42,qd=20,Yd=60,Zd=10,Jd=70;function hi(s,t,e,n,i={}){let r=e-s,a=n-t,o=Math.hypot(r,a)||1;return{k:"seg",ax:s,ay:t,bx:e,by:n,dx:r/o,dy:a/o,len:o,nx:-a/o,ny:r/o,r:3,e:.45,kick:0,on:!0,lv:0,vx:0,...i}}function Sh(s,t,e,n){let i=hi(s[0],s[1],t[0],t[1],n);return(e[0]-s[0])*i.nx+(e[1]-s[1])*i.ny<0&&(i.nx=-i.nx,i.ny=-i.ny),i.kn=[i.nx,i.ny],i}var $s=class{constructor(t,e={}){this.t=t,this.speed=e.speed??Vd,this.balls=[],this.segs=[],this.circs=[],this.sensors=[],this.time=0,this.acc=0,this.on=()=>{},this.nextId=1,this.keeperSkill=0;for(let a of t.walls)this.polyline(a.pts,{r:a.r,e:a.e,tag:"wall"});if(t.bumpers.forEach((a,o)=>this.circs.push({k:"circ",x:a.x,y:a.y,r:a.r,e:.5,kick:760,tag:"bumper",ref:o,on:!0,lv:0})),t.slings.forEach((a,o)=>{let l=a.side==="L"?[400,a.a[1]]:[0,a.a[1]];this.segs.push(Sh(a.a,a.c,l,{r:4,e:.7,tag:"sling",ref:o,kick:620}),hi(a.a[0],a.a[1],a.b[0],a.b[1],{r:4,e:.6,tag:"rubber"}),hi(a.b[0],a.b[1],a.c[0],a.c[1],{r:4,e:.6,tag:"rubber"}))}),t.kicker){let a=t.kicker;this.segs.push(Sh(a.a,a.b,[200,480],{r:4,e:.7,tag:"kicker",kick:560}));for(let o of[a.a,a.b])this.circs.push({k:"circ",x:o[0],y:o[1],r:4,e:.6,kick:0,tag:"post",on:!0,lv:0})}let n=t.plunger;this.segs.push(hi(367,n.top,395,n.top,{r:1,e:.2,tag:"plunger"}));let i=t.gate;this.segs.push(hi(i.a[0],i.a[1],i.b[0],i.b[1],{r:2,e:.3,tag:"gate",oneway:i.n}));for(let a of t.sensors)this.sensors.push({...a,inside:new Set,lv:0});let r=t.mini;if(this.players=[],r){for(let l of r.walls)this.polyline(l.pts,{r:l.r,e:l.e,tag:l.net?"net":"wall",lv:1,mu:l.net?.4:Mh});for(let l of r.posts)this.circs.push({k:"circ",x:l.x,y:l.y,r:l.r,e:.6,kick:0,tag:"goalpost",on:!0,lv:1});this.rows=r.rows.map((l,c)=>({...l,x:r.center,vx:0,idx:c})),this.rows.forEach((l,c)=>l.offs.forEach((h,u)=>{let d=hi(r.center+h-r.player.hw,l.y,r.center+h+r.player.hw,l.y,{r:r.player.r,e:.5,tag:"player",lv:1,ref:this.players.length,row:c,off:h});this.players.push({seg:d,line:l.line,row:c,k:u}),this.segs.push(d)}));let a=r.keeper,o=(a.min+a.max)/2;this.keeper=hi(o-a.hw,a.y,o+a.hw,a.y,{r:a.r,e:.55,tag:"keeper",lv:1,x:o,min:a.min,max:a.max,hw:a.hw}),this.segs.push(this.keeper),r.holes.forEach((l,c)=>this.sensors.push({tag:"hole",ref:c,x:l.x,y:l.y,r:l.r,inside:new Set,lv:1}))}this.flippers=t.flippers.map((a,o)=>({lv:0,...a,id:o,ang:a.rest,w:0,hit:0,sweep:0,pressed:!1})),this.lv=[0,1].map(a=>({segs:this.segs.filter(o=>o.lv===a),circs:this.circs.filter(o=>o.lv===a),flippers:this.flippers.filter(o=>o.lv===a),sensors:this.sensors.filter(o=>o.lv===a),mouths:t.ramps.map((o,l)=>({r:o,id:l})).filter(o=>o.r.mouth&&o.r.mouth.lv===a).map(o=>({id:o.id,...this.mouth(o.r)}))}))}mouth(t){let{a:e,b:n}=t.mouth,i=n[0]-e[0],r=n[1]-e[1],a=Math.hypot(i,r),o=-r/a,l=i/a,c=t.pts,h=Math.min(4,c.length-1);return(c[h].x-c[0].x)*o+(c[h].y-c[0].y)*l<0&&(o=-o,l=-l),{ax:e[0],ay:e[1],ux:i/a,uy:r/a,len:a,nx:o,ny:l,lv:t.mouth.lv}}polyline(t,e){for(let n=0;n<t.length-1;n++)this.segs.push(hi(t[n][0],t[n][1],t[n+1][0],t[n+1][1],e))}addBall(t,e,n=0,i=0,r=0){let a={id:this.nextId++,x:t,y:e,vx:n,vy:i,px:t,py:e,r:10,h:r?56:0,hv:0,lv:r,state:"play",rs:0,rv:0,still:0,hit:0,lowAt:this.time};return this.balls.push(a),a}removeBall(t){this.balls=this.balls.filter(e=>e!==t);for(let e of this.sensors)e.inside.delete(t)}setFlipper(t,e){for(let n of this.flippers)n.side===t&&(e&&!n.pressed&&n.sweep++,n.pressed=e)}nudge(t,e){for(let n of this.balls)n.state==="play"&&(n.vx+=t,n.vy+=e)}onPlunger(t){return t.state==="play"&&t.lv===0&&t.x>366&&t.y>this.t.plunger.ball-12&&Math.abs(t.vy)<80}launch(t){let e=0;for(let n of this.balls)this.onPlunger(n)&&(n.vy=-(950+750*Math.max(0,Math.min(1,t))),n.vx=0,e++,this.on("launch",n,t));return e}hold(t,e,n){t.state="held",t.x=e,t.y=n,t.vx=t.vy=0}release(t,e,n){t.state="play",t.vx=e,t.vy=n,t.still=0,t.lowAt=this.time}place(t,e,n,i){for(let r of this.sensors)r.inside.delete(t);t.lv=e,t.x=t.px=n,t.y=t.py=i,t.scored=!1,e&&(t.h=56,t.hv=0)}eject(t,e,n,i,r,a=0){this.place(t,a,e,n),this.release(t,i,r)}enterRamp(t,e,n=null){let i=this.t.ramps[e],r=i.pts,a=r[1].x-r[0].x,o=r[1].y-r[0].y,l=Math.hypot(a,o)||1;for(let c of this.sensors)c.inside.delete(t);t.ramp=e,t.state="ramp",t.rs=0,t.rv=n??Math.max(0,(t.vx*a+t.vy*o)/l),t.apex=!1}update(t){for(this.acc=Math.min(this.acc+t*this.speed,.1);this.acc>=Nl;)this.step(Nl),this.acc-=Nl}step(t){this.time+=t;for(let i of this.flippers){let r=i.pressed?i.up:i.rest,a=Math.sign(r-i.ang),o=(i.pressed?Gd:Wd)/this.speed,l=i.ang+a*o*t;a===0||Math.sign(r-l)!==a?(i.w=0,i.hit=0,i.ang=r):(i.w=a*o,i.hit=a*(i.pressed?Xd:qd),i.ang=l)}this.keeper&&(this.keeperStep(t),this.rowsStep());let e=this.t.mini;for(let i of this.balls){if(i.state==="ramp"){this.rampStep(i,t);continue}if(i.state!=="play")continue;let r=this.lv[i.lv];i.px=i.x,i.py=i.y,i.lv===0&&i.y>690&&(i.lowAt=this.time),i.lv===0&&i.h>0&&(i.hv+=3e3*t,i.h=Math.max(0,i.h-i.hv*t),i.h||(i.hv=0)),i.vy+=vh*t;let a=1-.06*t;i.vx*=a,i.vy*=a,i.x+=i.vx*t,i.y+=i.vy*t;for(let c of r.flippers)this.hitFlipper(i,c);for(let c of r.segs)c.on&&this.hitSeg(i,c);for(let c of r.circs)c.on&&this.hitCirc(i,c);let o=Math.hypot(i.vx,i.vy);if(o>Fl&&(i.vx*=Fl/o,i.vy*=Fl/o),this.sense(i,r),i.state!=="play")continue;if(i.lv===1){if(i.y<e.goal.y&&i.x>e.goal.x1&&i.x<e.goal.x2&&!i.scored&&(i.scored=!0,this.on("goal",i)),i.state==="play"&&i.y>e.drainY){let c=e.drop;this.place(i,0,Math.max(c.x1,Math.min(c.x2,i.x)),c.y),i.h=56,i.hv=0,i.vy=Math.max(i.vy,120),i.vx*=.5,this.on("mini-out",i)}}else i.y>this.t.drainY&&(i.state="gone",this.on("drain",i));let l=r.flippers.some(c=>c.pressed&&Math.hypot(i.x-c.px,i.y-c.py)<c.len+24);i.still=o<6&&!this.onPlunger(i)&&!l?i.still+t:0,i.still>1.2&&(i.vx+=(Math.random()<.5?-1:1)*(80+Math.random()*120),i.vy-=150,i.still=0)}let n=this.balls.filter(i=>i.state==="play");for(let i=0;i<n.length;i++)for(let r=i+1;r<n.length;r++){let a=n[i],o=n[r];if(a.lv!==o.lv)continue;let l=o.x-a.x,c=o.y-a.y,h=Math.hypot(l,c);if(h>0&&h<a.r+o.r){let u=l/h,d=c/h,f=(a.r+o.r-h)/2;a.x-=u*f,a.y-=d*f,o.x+=u*f,o.y+=d*f;let p=(o.vx-a.vx)*u+(o.vy-a.vy)*d;if(p<0){let y=-1.9*p/2;a.vx-=y*u,a.vy-=y*d,o.vx+=y*u,o.vy+=y*d}}}}keeperStep(t){let e=this.keeper,n=(e.min+e.max)/2,i=(e.max-e.min)/2,r=n+i*Math.sin(this.time*(1.8+this.keeperSkill)),a=this.balls.find(c=>c.state==="play"&&c.lv===1&&c.vy<0);a&&this.keeperSkill>0&&(r+=(Math.max(e.min,Math.min(e.max,a.x))-r)*Math.min(.6,this.keeperSkill));let o=Jd*(1+this.keeperSkill),l=Math.max(-o*t,Math.min(o*t,r-e.x));e.vx=l/t,e.x+=l,e.ax=e.x-e.hw,e.bx=e.x+e.hw}rowsStep(){let t=this.t.mini,e=t.player.hw;for(let n of this.rows)n.x=t.center+n.amp*Math.sin(this.time*n.w+n.phase),n.vx=n.amp*n.w*Math.cos(this.time*n.w+n.phase);for(let n of this.players){let i=n.seg,r=this.rows[n.row];i.ax=r.x+i.off-e,i.bx=r.x+i.off+e,i.vx=r.vx}}resetPlayers(t=null){for(let e of this.players)(!t||e.line===t)&&(e.seg.on=!0)}bounce(t,e,n,i,r,a=0,o=0,l=null){let c=t.x-e,h=t.y-n,u=c*c+h*h,d=t.r+i;if(u>=d*d)return!1;let f=Math.sqrt(u);if(f<1e-6?([c,h]=l||[0,-1],f=0):(c/=f,h/=f),r.oneway&&t.vx*r.oneway[0]+t.vy*r.oneway[1]>0)return!1;t.x=e+c*d,t.y=n+h*d;let p=t.vx-a,y=t.vy-o,g=p*c+y*h;if(g>=0)return!1;let m=p-g*c,S=y-g*h,T=r.e??.45,v=Math.hypot(m,S);if(v>0){let P=Math.max(0,1-(r.mu??Mh)*(1+T)*-g/v);m*=P,S*=P}let b=m-g*T*c,w=S-g*T*h;if(r.kick&&-g>Yd&&(!r.kn||c*r.kn[0]+h*r.kn[1]>.5)&&this.time-(t.lowAt??this.time)<Zd){let P=(Math.random()-.5)*.2,_=r.kick*(.9+Math.random()*.2),E=Math.cos(P),C=Math.sin(P);b+=(c*E-h*C)*_,w+=(c*C+h*E)*_}return t.vx=b+a,t.vy=w+o,-g}hitSeg(t,e){let n=Math.max(0,Math.min(e.len,(t.x-e.ax)*e.dx+(t.y-e.ay)*e.dy)),i=e.ax+e.dx*n,r=e.ay+e.dy*n,a=this.bounce(t,i,r,e.r,e,e.vx||0,0,[e.nx,e.ny]);if(a!==!1){if(e.tag==="keeper"&&a>40){this.on("save",t,a);return}if(e.tag==="player"&&a>40){this.on("player",e.ref,t,a);return}a>40&&this.on("hit",e.tag,t,a,e.ref)}}hitCirc(t,e){let n=this.bounce(t,e.x,e.y,e.r,e);n!==!1&&n>20&&this.on(e.tag==="bumper"?"bumper":"hit",e.tag==="bumper"?e.ref:e.tag,t,n)}hitFlipper(t,e){let n=e.px+Math.cos(e.ang)*e.len,i=e.py+Math.sin(e.ang)*e.len,r=(n-e.px)/e.len,a=(i-e.py)/e.len,o=Math.max(0,Math.min(e.len,(t.x-e.px)*r+(t.y-e.py)*a)),l=e.px+r*o,c=e.py+a*o,h=e.r1+(e.r2-e.r1)*o/e.len,u=e.id+":"+e.sweep,d=e.w!==0&&t.flip===u,f=d?0:e.hit,p=-f*(c-e.py),y=f*(l-e.px),g=this.bounce(t,l,c,h,d?{e:0,mu:0}:{e:.32,mu:Hd},p,y,[a,-r]);g===!1||d||(t.lowAt=this.time,e.w!==0&&(t.flip=u),g>50&&this.on("flip-hit",e.side,t,g,e.mini?"mini":e.tackle?"tackle":""))}sense(t,e){for(let n of e.sensors){let i=(t.x-n.x)**2+(t.y-n.y)**2<n.r*n.r;if(i&&!n.inside.has(t)?(n.inside.add(t),this.on("sensor",n.tag,t,n.ref)):i||n.inside.delete(t),t.state!=="play")return}for(let n of e.mouths){let i=(t.px-n.ax)*n.nx+(t.py-n.ay)*n.ny,r=(t.x-n.ax)*n.nx+(t.y-n.ay)*n.ny;if(!(i<0&&r>=0))continue;let a=(t.x-n.ax)*n.ux+(t.y-n.ay)*n.uy;if(a<-2||a>n.len+2)continue;let o=t.vx*n.nx+t.vy*n.ny;if(o>150||n.lv===1){this.enterRamp(t,n.id),this.on("ramp-in",t,o,n.id);return}}}rampStep(t,e){let n=t.ramp||0,i=this.t.ramps[n],r=i.pts,a=i.length,o=1;for(;o<r.length-1&&r[o].s<t.rs;)o++;let l=r[o-1],c=r[o],h=c.s-l.s||1,u=Math.max(0,Math.min(1,(t.rs-l.s)/h)),d=(c.y-l.y)/h,f=(c.h-l.h)/h;if(t.rv+=(vh*zd*d-kd*f-.2*t.rv)*e,t.rs+=t.rv*e,t.x=l.x+(c.x-l.x)*u,t.y=l.y+(c.y-l.y)*u,t.h=l.h+(c.h-l.h)*u,!t.apex&&o-1>=i.apex&&(t.apex=!0,this.on("ramp-apex",t,n)),t.rs>=a){let p=r[r.length-1],y=r[r.length-2],g=Math.hypot(p.x-y.x,p.y-y.y)||1;if(i.end){let m=Math.max(.7,Math.min(1.3,t.rv/420));t.state="play",this.place(t,i.end.lv,i.end.x,i.end.y),t.vx=i.end.vx*m,t.vy=i.end.vy*m,t.still=0,t.lowAt=this.time}else{let m=Math.max(260,Math.min(t.rv,700));t.state="play",t.h=p.h,t.hv=0,t.lv=0,t.x=t.px=p.x,t.y=t.py=p.y,t.vx=(p.x-y.x)/g*m,t.vy=(p.y-y.y)/g*m}this.on("ramp-done",t,n)}else if(t.rs<=0&&t.rv<=0){let p=r[1],y=Math.hypot(p.x-r[0].x,p.y-r[0].y)||1,g=Math.max(-t.rv,120),m=i.mouth?i.mouth.lv:0;t.state="play",t.lv=m,t.h=m?56:0,t.hv=0,t.x=t.px=r[0].x-(p.x-r[0].x)/y*3,t.y=t.py=r[0].y-(p.y-r[0].y)/y*3,t.vx=-(p.x-r[0].x)/y*g,t.vy=-(p.y-r[0].y)/y*g,this.on("ramp-fail",t,n)}}};var re={bumper:1e3,sling:110,pass:25e3,ramp:5e4,onetwo:25e3,onetwoBoth:1e5,lockLit:1e5,lock:25e3,goal:25e4,jackpot:1e6,superJackpot:5e6,rampJackpot:25e4,combo:25e4,hole:25e3,trick:5e4,trickJackpot:5e5,trap:15e3,tackle:1e4,player:5e3,save:5e3,skill:5e5,win:1e6,draw:25e4,cup:5e6,outlane:1e4},Ui=["r8","r4","r2","final"],Bl=["att","mil","def"],fa=["att","att","mil","mil","mil","mil","def","def","def","def"],bh=s=>s<2?8+s:s<6?4+(s-2):s-6,is=[1,2,4,6,8,10],pa=["jackpot","super","skill","extra","time"],Ks=["r2","r3","r4"],Qs=2700,$d=30,Eh=5,Kd=8,Qd=50,jd=25,wh=10,Th=10,tf=6,ma=class{constructor(t,e,n={},i={}){this.p=t,this.t=e,this.cfg={balls:3,text:{},facts:[],opps:[{name:"",ini:""}],...n},this.cfg.opps.length||(this.cfg.opps=[{name:"",ini:""}]),this.on={fx:()=>{},over:()=>{},...i},this.state="attract",this.players=[],this.cur=0,this.msgs=[],this.timers=[],this.time=0,this.tilt=0,this.tilted=!1,this.multiball=!1,t.on=(...r)=>this.event(...r)}tr(t,e={}){return String(this.cfg.text[t]??t).replace(/\{(\w+)\}/g,(n,i)=>e[i]??"")}get pl(){return this.players[this.cur]}start(t=1){t=Math.max(1,Math.min(4,t|0)),this.players=Array.from({length:t},(e,n)=>({n:n+1,score:0,ball:1,extra:0,extraGiven:!1,done:new Set,bonus:{ramps:0,passes:0,bumpers:0,goals:0},mult:0,hold:!1,combo:0,comboAt:0,combos:0,attackUntil:0,superLit:!1,lion:0,elim:new Set,lit:[!1,!1,!1],locked:[!1,!1,!1],mode:0,round:0,opp:(Math.floor(Math.random()*this.cfg.opps.length)+n)%this.cfg.opps.length,home:0,away:0,half:1,clock:Qs,goals:[],matches:[],cups:0,goalsTotal:0})),this.lastOneTwo=null,this.cur=0,this.state="play",this.msgs=[],this.timers=[],this.serve(!0)}serve(t,e=!1){for(let i of[...this.p.balls])i.state==="gone"&&this.p.removeBall(i);let n=this.p.addBall(this.t.plunger.x,this.t.plunger.ball);return t&&(this.tilt=0,this.tilted=!1,this.multiball=!1,this.saveUntil=0,this.saveArmed=!0,this.lionUntil=0,this.skill=Math.floor(Math.random()*Ks.length),this.skillArmed=!0,this.skillUntil=1/0,this.waiting=!0,this.sync(),this.on.fx("opp",this.opp()),this.say(this.players.length>1?this.tr("player",{n:this.pl.n}):this.tr("ball",{n:this.pl.ball,t:this.cfg.balls}),this.tr("launchHint"),2500)),e&&this.after(.7,()=>this.p.launch(.86)),n}opp(t=this.pl){return this.cfg.opps[t.opp%this.cfg.opps.length]}sync(){let t=this.pl;this.p.resetPlayers();for(let e of t.elim)this.p.players[bh(e)].seg.on=!1;this.p.keeperSkill=Math.min(.6,t.home*.12)}after(t,e){this.timers.push({at:this.time+t,fn:e})}say(t,e="",n=2e3,i=""){this.msgs.push({big:t,small:e,ms:n,kind:i}),this.msgs.length>4&&this.msgs.splice(0,this.msgs.length-4)}add(t){if(this.state!=="play"||this.tilted)return;let e=this.time<this.lionUntil?2:1;this.pl.score+=t*e}input(t,e){this.state==="play"&&(t==="L"||t==="R")&&(this.p.setFlipper(t,e&&!this.tilted),e&&!this.tilted&&(this.on.fx("flip",t),this.waiting&&(this.skill=(this.skill+(t==="L"?Ks.length-1:1))%Ks.length)))}nudge(t=0){this.state!=="play"||this.tilted||(this.p.nudge(t*90,-70),this.on.fx("nudge"),this.tilt+=1,this.tilt>3.2?(this.tilted=!0,this.p.setFlipper("L",!1),this.p.setFlipper("R",!1),this.say("TILT",this.tr("tiltLost"),3e3,"tilt"),this.on.fx("tilt")):this.tilt>2&&this.say(this.tr("warning"),"",900,"warn"))}launch(t){this.state==="play"&&this.p.launch(t)}update(t){this.time+=t,this.tilt=Math.max(0,this.tilt-t*.6),(this.state==="play"||this.state==="bonus")&&this.p.update(t);let e=this.pl;this.state==="play"&&e&&!this.waiting&&!this.tilted&&this.p.balls.some(i=>i.state==="play"||i.state==="ramp")&&(e.clock-=t*$d,e.clock<=0&&this.whistle()),e&&e.combo>0&&this.time-e.comboAt>Kd&&(e.combo--,e.comboAt=this.time),this.skillArmed&&this.time>this.skillUntil&&(this.skillArmed=!1);let n=this.timers.filter(i=>i.at<=this.time);this.timers=this.timers.filter(i=>i.at>this.time);for(let i of n)i.fn();this.msgs.length&&(this.msgs[0].ms-=t*1e3,this.msgs[0].ms<=0&&this.msgs.shift())}clock(t=this.pl){let e=Math.max(0,Math.ceil(t?t.clock:Qs));return String(Math.floor(e/60)).padStart(2,"0")+":"+String(e%60).padStart(2,"0")}minute(t=this.pl){return Math.min(90,Math.floor((t.half-1)*45+(Qs-Math.max(0,t.clock))/60)+1)+"'"}whistle(){let t=this.pl;if(t.half===1){t.half=2,t.clock=Qs,this.say(this.tr("half"),this.tr("scoreLine",{h:t.home,a:t.away,o:this.opp().ini||this.opp().name}),2400,"half"),this.on.fx("half");return}let e=t.home-t.away,n=this.opp(),i=Ui[Math.min(t.round,Ui.length-1)],r="loss";e>0?r="win":e===0&&(r="draw"),t.matches.push({opp:n.name||n.ini,ini:n.ini,home:t.home,away:t.away,round:i,result:r,goals:t.goals.slice()});let a=this.tr("scoreLine",{h:t.home,a:t.away,o:n.ini||n.name});r==="win"?(this.add(re.win*e),t.done.add("victoire"),t.round>=Ui.length-1?(t.cups++,t.done.add("coupe"),this.add(re.cup),t.round=0,this.after(2.6,()=>this.say(this.tr("cupWon"),this.tr("cupWonSub"),3e3,"cupwon")),this.on.fx("cupwon")):(t.round++,a=this.tr("nextRound",{r:this.roundName(t.round)}))):r==="draw"?(this.add(re.draw),a=this.tr("replay")):(t.round=0,a=this.tr("out")),this.say(this.tr(r),a,2600,r),this.on.fx(r),r!=="draw"&&(t.opp=(t.opp+1)%this.cfg.opps.length),t.home=0,t.away=0,t.half=1,t.clock=Qs,t.goals=[],t.elim=new Set,t.lit=[!1,!1,!1],this.sync(),this.after(2.8,()=>{this.say(this.tr("kickoff"),this.tr("vs",{r:this.roundName(t.round),o:this.opp().name||this.opp().ini}),2400,"match"),this.on.fx("opp",this.opp())})}roundName(t){return this.tr(Ui[Math.min(t,Ui.length-1)])}event(t,e,n,i,r){if(this.state!=="play")return;let a=this.pl;switch(t){case"launch":this.waiting=!1,this.msgs=this.msgs.filter(o=>o.small!==this.tr("launchHint")),this.saveArmed&&(this.saveUntil=this.time+wh,this.saveArmed=!1),this.skillArmed&&this.skillUntil===1/0&&(this.skillUntil=this.time+Th),e.launchedAt=this.time,this.on.fx("launch",e);break;case"sensor":this.sensor(e,n,i);break;case"bumper":this.add(re.bumper),a.bonus.bumpers++,this.on.fx("bumper",e),++a.lion>=Qd&&this.time>=this.lionUntil&&(a.lion=0,a.done.add("lion"),this.lionUntil=this.time+jd,this.say(this.tr("lion"),this.tr("lionSub"),2600,"lion"),this.on.fx("roar"));break;case"hit":e==="sling"||e==="kicker"?(this.add(re.sling),this.on.fx(e,r)):this.on.fx("hit",i);break;case"flip-hit":this.on.fx("flip-hit",i),r==="tackle"&&this.time-(this.tackleAt??-9)>2&&(this.tackleAt=this.time,this.add(re.tackle),a.done.add("tacle"),this.say(this.tr("tackle"),"",900,"warn"),this.on.fx("tackle"));break;case"player":this.add(re.player),this.on.fx("player",e);break;case"save":this.add(re.save),this.on.fx("save"),this.time-(this.saveMsgAt??-9)>2.5&&(this.saveMsgAt=this.time,this.say(this.tr("keeper"),"",1100,"warn"));break;case"goal":this.goal(e);break;case"mini-out":this.say(this.tr("missed"),"",1200,"warn"),this.on.fx("missed");break;case"ramp-in":this.on.fx("ramp-in",i);break;case"ramp-done":this.ramp(n);break;case"ramp-fail":this.on.fx("ramp-fail");break;case"drain":this.drain(e);break}}sensor(t,e,n){let i=this.pl;if(t==="inlane")i.attackUntil=this.time+tf,this.say(this.tr("attack"),this.tr("attackSub"),1400,"pass"),this.on.fx("lane",n);else if(t==="outlane")this.add(re.outlane),i.superLit||(i.superLit=!0,this.say(this.tr("superLit"),"",1600,"jackpot")),this.on.fx("outlane",n);else if(t==="onetwo"){if(e.vy>0||n===1&&this.time-(e.launchedAt??-9)<2)return;this.add(re.onetwo),this.on.fx("onetwo",n);let r=this.lastOneTwo;this.lastOneTwo={ref:n,at:this.time},r&&r.ref!==n&&this.time-r.at<5&&(this.add(re.onetwoBoth),this.say(this.tr("onetwo"),"+ "+this.fmt(re.onetwoBoth),1400,"skill")),this.pass()}else if(t==="lock")this.lock(e,n);else if(t==="trick")this.trick(e);else if(t==="trap"){let r=this.t.sensors.find(a=>a.tag==="trap");this.p.hold(e,r.x,r.y),this.add(re.trap),this.on.fx("trap"),this.after(.6,()=>{e.state==="held"&&this.p.release(e,260,-950)})}else if(t==="hole"){this.add(this.multiball?re.rampJackpot:re.hole),this.on.fx("hole",n);let r=this.t.mini.holes[n];this.p.hold(e,r.x,r.y),this.after(.7,()=>{e.state==="held"&&(e.state="play",this.p.enterRamp(e,n?6:5,520))})}}pass(){let t=this.pl;t.bonus.passes++,this.add(re.pass);let e=this.time<t.attackUntil?2:1;for(let n=0;n<e;n++)this.eliminate();t.combo++,t.comboAt=this.time,this.on.fx("pass",t.combo),t.combo>=Eh&&(t.combo=0,t.combos++,t.done.add("combo"),this.add(re.combo*t.combos),this.after(.5,()=>this.say("COMBO !","+ "+this.fmt(re.combo*t.combos),1800,"skill")),this.on.fx("combo"))}eliminate(){let t=this.pl,e=fa.findIndex((r,a)=>!t.elim.has(a));if(e<0)return;t.elim.add(e),this.p.players[bh(e)].seg.on=!1,this.on.fx("eliminate",e);let n=fa[e],i=Bl.indexOf(n);fa.every((r,a)=>r!==n||t.elim.has(a))&&!t.locked[i]?(t.lit[i]=!0,this.after(.4,()=>this.say(this.tr("lockLit",{l:this.tr("line_"+n)}),this.tr("lockLitSub"),1800,"lock")),this.on.fx("lock-lit",i)):this.after(.4,()=>this.say(this.tr("eliminated"),this.tr(t.elim.size===9?"leftOne":"left",{n:10-t.elim.size}),1e3,"pass"))}lock(t,e){let n=this.pl,i=this.t.sensors.find(a=>a.tag==="lock"&&a.ref===e);this.p.hold(t,i.x,i.y);let r=[[0,320],[-130,300],[-90,320]][e];if(n.lit[e]&&!this.multiball){if(n.lit[e]=!1,n.locked[e]=!0,this.add(re.lockLit),n.done.add("lock"),this.on.fx("locked",e),n.locked.every(a=>a)){this.say(this.tr("multiball"),this.tr("multiballSub"),3e3,"multiball"),this.after(1,()=>this.startMultiball(t));return}this.say(this.tr("locked",{l:this.tr("line_"+Bl[e])}),this.tr("locks",{n:n.locked.filter(a=>a).length}),2200,"lock"),this.after(1.2,()=>{this.p.removeBall(t),this.serve(!1),this.waiting=!0});return}this.add(re.lock),this.on.fx("kickout",e),this.after(.8,()=>{t.state==="held"&&this.p.release(t,r[0],r[1])})}startMultiball(t){let e=this.pl;this.multiball=!0,e.done.add("multiball"),e.jackpotsAtStart=e.jackpots||0,this.saveUntil=this.time+wh+3,this.on.fx("multiball");let n=[[0,320],[-130,300],[-90,320]];Bl.forEach((i,r)=>{let a=this.t.sensors.find(l=>l.tag==="lock"&&l.ref===r);if(t.state==="held"&&t.x===a.x&&t.y===a.y){this.p.release(t,n[r][0],n[r][1]);return}let o=this.p.addBall(a.x,a.y);this.p.hold(o,a.x,a.y),this.after(.4+r*.5,()=>this.p.release(o,n[r][0],n[r][1]))}),e.locked=[!1,!1,!1]}ramp(t){let e=this.pl,n=this.t.ramps[t].name;if(!(t>=5)){if(e.bonus.ramps++,this.add(re.ramp),this.on.fx("ramp",t),this.shot("r"+t),n==="acces"){this.say(this.tr("mini"),this.tr("miniSub"),1600,"pass");return}this.multiball&&(n==="centre"||n==="ballon-long")&&(this.add(re.rampJackpot),this.say("JACKPOT",this.fmt(re.rampJackpot),1400,"jackpot"),this.on.fx("jackpot")),n==="passe"&&this.say(this.tr("mini"),this.tr("miniSub"),1600,"pass"),this.pass()}}shot(t){this.skillArmed&&(this.skillArmed=!1,Ks[this.skill]===t&&(this.add(re.skill),this.pl.done.add("skill"),this.say(this.tr("skill"),"+ 500 000",2200,"skill"),this.on.fx("skill")))}goal(t){let e=this.pl,n=this.t.mini;this.p.hold(t,Math.max(n.goal.x1+8,Math.min(n.goal.x2-8,t.x)),n.goal.net),e.home++,e.goalsTotal++,e.bonus.goals++,e.goals.push(this.minute()),e.done.add("but");let i=re.goal*e.goalsTotal,r=this.tr("scoreLine",{h:e.home,a:e.away,o:this.opp().ini||this.opp().name})+" \xB7 "+this.minute();this.multiball&&(e.jackpots=(e.jackpots||0)+1,e.done.add("jackpot"),i+=re.jackpot*e.jackpots,r="JACKPOT "+this.fmt(re.jackpot*e.jackpots)),e.superLit&&(e.superLit=!1,e.done.add("super"),i+=re.superJackpot,r="SUPER JACKPOT "+this.fmt(re.superJackpot),this.on.fx("super")),this.add(i),e.mult<is.length-1&&e.mult++,e.mult===is.length-1&&(e.hold=!0),this.say(this.tr("goal"),r,3200,"goal"),this.on.fx("goal",e.home),e.elim=new Set,this.after(2.6,()=>{this.sync(),t.state==="held"&&(t.state="play",this.p.place(t,1,n.holes[1].x,n.holes[1].y),this.p.enterRamp(t,6,520),this.on.fx("tunnel"))})}trick(t){let e=this.pl,n=this.t.sensors.find(o=>o.tag==="trick");this.p.hold(t,n.x,n.y);let i=pa[e.mode%pa.length];e.mode++,this.add(re.trick);let r="";i==="jackpot"?(this.add(re.trickJackpot),r="JACKPOT "+this.fmt(re.trickJackpot)):i==="super"?(e.superLit=!0,r=this.tr("superLit")):i==="skill"?(this.skillArmed=!0,this.skillUntil=this.time+Th,r=this.tr("skillLit")):i==="extra"?e.extraGiven?(this.add(re.trickJackpot),r="JACKPOT "+this.fmt(re.trickJackpot)):(e.extraGiven=!0,e.extra++,e.done.add("extra"),r=this.tr("extraBall"),this.on.fx("extra")):(e.clock+=300,r=this.tr("moreTime")),e.done.add("trick"),this.say(this.tr("trick"),r,2e3,"skill");let a=this.cfg.facts;a.length&&this.after(2,()=>this.say(this.tr("fact"),a[Math.floor(Math.random()*a.length)],4200,"fact")),this.on.fx("trick",i),this.after(1.4,()=>{if(t.state==="held"){let h=Math.hypot(20,-108);this.p.release(t,20/h*1300,-108/h*1300)}})}drain(t){this.p.removeBall(t),this.on.fx("drain");let e=this.p.balls.filter(n=>n.state!=="gone");if(!this.tilted&&this.time<this.saveUntil&&!this.multiball){this.say(this.tr("saved"),"",1800,"save"),this.serve(!1,!0);return}if(e.length>0){this.multiball&&e.length===1&&this.endMultiball();return}this.multiball&&this.endMultiball(),this.endBall()}endMultiball(){this.multiball=!1,this.say(this.tr("multiballEnd"),"",1600,"pass"),this.pl.elim=new Set,this.sync()}endBall(){let t=this.pl;this.p.setFlipper("L",!1),this.p.setFlipper("R",!1),t.away++,t.goalsAgainst=(t.goalsAgainst||0)+1,this.say(this.tr("awayGoal",{o:this.opp().name||this.opp().ini}),this.tr("scoreLine",{h:t.home,a:t.away,o:this.opp().ini||this.opp().name}),2e3,"away"),this.on.fx("awaygoal");let e=t.bonus,n=is[t.mult],i=this.tilted?0:(e.ramps*2e4+e.passes*1e4+e.bumpers*100+e.goals*5e4)*n;this.tilted||(t.score+=i),t.bonus={ramps:0,passes:0,bumpers:0,goals:0},this.state="bonus",this.after(2,()=>this.say(this.tr("bonus"),this.fmt(i)+(n>1?" (\xD7"+n+")":""),2200,"bonus")),this.on.fx("bonus"),this.after(4.4,()=>{if(this.state="play",t.hold||(t.mult=0),t.hold=!1,t.extra>0&&!this.tilted){t.extra--,this.say(this.tr("again"),"",1800,"extra"),this.serve(!0);return}t.ball++;for(let r=1;r<=this.players.length;r++){let a=(this.cur+r)%this.players.length;if(this.players[a].ball<=this.cfg.balls){this.cur=a,this.serve(!0);return}}this.state="over",this.on.over(this.players.map(r=>({n:r.n,score:r.score,done:[...r.done],cups:r.cups,matches:r.matches.slice(),current:{opp:this.opp(r).name||this.opp(r).ini,home:r.home,away:r.away,round:Ui[Math.min(r.round,Ui.length-1)]}})))})}fmt(t){return String(Math.round(t)).replace(/\B(?=(\d{3})+(?!\d))/g," ")}match(){let t=this.pl;return t?{home:t.home,away:t.away,clock:this.clock(),half:t.half,opp:this.opp(),round:this.roundName(t.round)}:null}screen(){let t=this.msgs[0];if(t)return{big:t.big,small:t.small,kind:t.kind};let e=this.pl;if(!e)return{big:"",small:""};let n;return this.waiting?n=this.tr("launchHint"):this.multiball?n=this.tr("jackpotHint"):e.lit.some(i=>i)?n=this.tr("lockHint"):n=this.tr("vs",{r:this.roundName(e.round),o:this.opp().name||this.opp().ini}),{big:this.fmt(e.score),small:n,kind:"score"}}lamps(){let t=this.pl;return t?{hexes:fa.map((e,n)=>!t.elim.has(n)),lit:t.lit.slice(),locked:t.locked.slice(),combo:t.combo,comboMax:Eh,mult:t.mult,hold:t.hold,superLit:t.superLit,attack:this.time<t.attackUntil,mode:pa[t.mode%pa.length],skill:this.waiting||this.skillArmed?Ks[this.skill]:"",multiball:this.multiball,save:this.time<this.saveUntil,lion:this.time<this.lionUntil,tilted:this.tilted,home:t.home,away:t.away,clock:this.clock(),half:t.half,ball:Math.min(t.ball,this.cfg.balls),balls:this.cfg.balls,round:t.round}:null}};var su=0,bc=1,ru=2;var qi=1,au=2,Fs=3,wi=0,We=1,He=2,_n=0,Bs=1,Yi=2,Ec=3,wc=4,ou=5;var Zi=100,lu=101,cu=102,hu=103,uu=104,du=200,fu=201,pu=202,mu=203,Tc=204,Ac=205,gu=206,xu=207,_u=208,yu=209,vu=210,Mu=211,Su=212,bu=213,Eu=214,Ga=0,Wa=1,Xa=2,bs=3,qa=4,Ya=5,Za=6,Ja=7,Eo=0,wu=1,Tu=2,Ln=0,Wr=1,Xr=2,qr=3,Ji=4,Yr=5,Zr=6,Jr=7;var Rc=300,Ti=301,$i=302,wo=303,To=304,$r=306,zn=1e3,On=1001,$a=1002,ze=1003,Au=1004;var Kr=1005;var Ge=1006,Ao=1007;var Ai=1008;var rn=1009,Cc=1010,Pc=1011,Os=1012,Ro=1013,Dn=1014,yn=1015,Le=1016,Co=1017,Po=1018,ks=1020,Ic=35902,Lc=35899,Dc=1021,Uc=1022,vn=1023,Hn=1026,Ri=1027,Io=1028,Lo=1029,Ci=1030,Do=1031;var Uo=1033,Qr=33776,jr=33777,ta=33778,ea=33779,No=35840,Fo=35841,Bo=35842,Oo=35843,ko=36196,zo=37492,Ho=37496,Vo=37488,Go=37489,na=37490,Wo=37491,Xo=37808,qo=37809,Yo=37810,Zo=37811,Jo=37812,$o=37813,Ko=37814,Qo=37815,jo=37816,tl=37817,el=37818,nl=37819,il=37820,sl=37821,rl=36492,al=36494,ol=36495,ll=36283,cl=36284,ia=36285,hl=36286;var ur=2300,Ka=2301,Ha=2302,uc=2303,dc=2400,fc=2401,pc=2402;var Ru=3200;var sa=0,Cu=1,ii="",Be="srgb",dr="srgb-linear",fr="linear",ce="srgb";var Va=7680;var Pu=519,Iu=512,Lu=513,Du=514,ul=515,Uu=516,Nu=517,dl=518,Fu=519,Nc=35044;var Fc="300 es",Cn=2e3,Es=2001;function ef(s){for(let t=s.length-1;t>=0;--t)if(s[t]>=65535)return!0;return!1}function nf(s){return ArrayBuffer.isView(s)&&!(s instanceof DataView)}function pr(s){return document.createElementNS("http://www.w3.org/1999/xhtml",s)}function Bu(){let s=pr("canvas");return s.style.display="block",s}var Ah={},ws=null;function mr(...s){let t="THREE."+s.shift();ws?ws("log",t,...s):console.log(t,...s)}function Ou(s){let t=s[0];if(typeof t=="string"&&t.startsWith("TSL:")){let e=s[1];e&&e.isStackTrace?s[0]+=" "+e.getLocation():s[1]='Stack trace not available. Enable "THREE.Node.captureStackTrace" to capture stack traces.'}return s}function qt(...s){s=Ou(s);let t="THREE."+s.shift();if(ws)ws("warn",t,...s);else{let e=s[0];e&&e.isStackTrace?console.warn(e.getError(t)):console.warn(t,...s)}}function Xt(...s){s=Ou(s);let t="THREE."+s.shift();if(ws)ws("error",t,...s);else{let e=s[0];e&&e.isStackTrace?console.error(e.getError(t)):console.error(t,...s)}}function ki(...s){let t=s.join(" ");t in Ah||(Ah[t]=!0,qt(...s))}function ku(s,t,e){return new Promise(function(n,i){function r(){switch(s.clientWaitSync(t,s.SYNC_FLUSH_COMMANDS_BIT,0)){case s.WAIT_FAILED:i();break;case s.TIMEOUT_EXPIRED:setTimeout(r,e);break;default:n()}}setTimeout(r,e)})}var zu={[Ga]:Wa,[Xa]:Za,[qa]:Ja,[bs]:Ya,[Wa]:Ga,[Za]:Xa,[Ja]:qa,[Ya]:bs},Vn=class{addEventListener(t,e){this._listeners===void 0&&(this._listeners={});let n=this._listeners;n[t]===void 0&&(n[t]=[]),n[t].indexOf(e)===-1&&n[t].push(e)}hasEventListener(t,e){let n=this._listeners;return n===void 0?!1:n[t]!==void 0&&n[t].indexOf(e)!==-1}removeEventListener(t,e){let n=this._listeners;if(n===void 0)return;let i=n[t];if(i!==void 0){let r=i.indexOf(e);r!==-1&&i.splice(r,1)}}dispatchEvent(t){let e=this._listeners;if(e===void 0)return;let n=e[t.type];if(n!==void 0){t.target=this;let i=n.slice(0);for(let r=0,a=i.length;r<a;r++)i[r].call(this,t);t.target=null}}},Ye=["00","01","02","03","04","05","06","07","08","09","0a","0b","0c","0d","0e","0f","10","11","12","13","14","15","16","17","18","19","1a","1b","1c","1d","1e","1f","20","21","22","23","24","25","26","27","28","29","2a","2b","2c","2d","2e","2f","30","31","32","33","34","35","36","37","38","39","3a","3b","3c","3d","3e","3f","40","41","42","43","44","45","46","47","48","49","4a","4b","4c","4d","4e","4f","50","51","52","53","54","55","56","57","58","59","5a","5b","5c","5d","5e","5f","60","61","62","63","64","65","66","67","68","69","6a","6b","6c","6d","6e","6f","70","71","72","73","74","75","76","77","78","79","7a","7b","7c","7d","7e","7f","80","81","82","83","84","85","86","87","88","89","8a","8b","8c","8d","8e","8f","90","91","92","93","94","95","96","97","98","99","9a","9b","9c","9d","9e","9f","a0","a1","a2","a3","a4","a5","a6","a7","a8","a9","aa","ab","ac","ad","ae","af","b0","b1","b2","b3","b4","b5","b6","b7","b8","b9","ba","bb","bc","bd","be","bf","c0","c1","c2","c3","c4","c5","c6","c7","c8","c9","ca","cb","cc","cd","ce","cf","d0","d1","d2","d3","d4","d5","d6","d7","d8","d9","da","db","dc","dd","de","df","e0","e1","e2","e3","e4","e5","e6","e7","e8","e9","ea","eb","ec","ed","ee","ef","f0","f1","f2","f3","f4","f5","f6","f7","f8","f9","fa","fb","fc","fd","fe","ff"];var Ol=Math.PI/180,gr=180/Math.PI;function ti(){let s=Math.random()*4294967295|0,t=Math.random()*4294967295|0,e=Math.random()*4294967295|0,n=Math.random()*4294967295|0;return(Ye[s&255]+Ye[s>>8&255]+Ye[s>>16&255]+Ye[s>>24&255]+"-"+Ye[t&255]+Ye[t>>8&255]+"-"+Ye[t>>16&15|64]+Ye[t>>24&255]+"-"+Ye[e&63|128]+Ye[e>>8&255]+"-"+Ye[e>>16&255]+Ye[e>>24&255]+Ye[n&255]+Ye[n>>8&255]+Ye[n>>16&255]+Ye[n>>24&255]).toLowerCase()}function se(s,t,e){return Math.max(t,Math.min(e,s))}function sf(s,t){return(s%t+t)%t}function kl(s,t,e){return(1-e)*s+e*t}function Bn(s,t){switch(t.constructor){case Float32Array:return s;case Uint32Array:return s/4294967295;case Uint16Array:return s/65535;case Uint8Array:case Uint8ClampedArray:return s/255;case Int32Array:return Math.max(s/2147483647,-1);case Int16Array:return Math.max(s/32767,-1);case Int8Array:return Math.max(s/127,-1);default:throw new Error("THREE.MathUtils: Invalid component type.")}}function me(s,t){switch(t.constructor){case Float32Array:return s;case Uint32Array:return Math.round(s*4294967295);case Uint16Array:return Math.round(s*65535);case Uint8Array:case Uint8ClampedArray:return Math.round(s*255);case Int32Array:return Math.round(s*2147483647);case Int16Array:return Math.round(s*32767);case Int8Array:return Math.round(s*127);default:throw new Error("THREE.MathUtils: Invalid component type.")}}var Vc=class Vc{constructor(t=0,e=0){this.x=t,this.y=e}get width(){return this.x}set width(t){this.x=t}get height(){return this.y}set height(t){this.y=t}set(t,e){return this.x=t,this.y=e,this}setScalar(t){return this.x=t,this.y=t,this}setX(t){return this.x=t,this}setY(t){return this.y=t,this}setComponent(t,e){switch(t){case 0:this.x=e;break;case 1:this.y=e;break;default:throw new Error("THREE.Vector2: index is out of range: "+t)}return this}getComponent(t){switch(t){case 0:return this.x;case 1:return this.y;default:throw new Error("THREE.Vector2: index is out of range: "+t)}}clone(){return new this.constructor(this.x,this.y)}copy(t){return this.x=t.x,this.y=t.y,this}add(t){return this.x+=t.x,this.y+=t.y,this}addScalar(t){return this.x+=t,this.y+=t,this}addVectors(t,e){return this.x=t.x+e.x,this.y=t.y+e.y,this}addScaledVector(t,e){return this.x+=t.x*e,this.y+=t.y*e,this}sub(t){return this.x-=t.x,this.y-=t.y,this}subScalar(t){return this.x-=t,this.y-=t,this}subVectors(t,e){return this.x=t.x-e.x,this.y=t.y-e.y,this}multiply(t){return this.x*=t.x,this.y*=t.y,this}multiplyScalar(t){return this.x*=t,this.y*=t,this}divide(t){return this.x/=t.x,this.y/=t.y,this}divideScalar(t){return this.multiplyScalar(1/t)}applyMatrix3(t){let e=this.x,n=this.y,i=t.elements;return this.x=i[0]*e+i[3]*n+i[6],this.y=i[1]*e+i[4]*n+i[7],this}min(t){return this.x=Math.min(this.x,t.x),this.y=Math.min(this.y,t.y),this}max(t){return this.x=Math.max(this.x,t.x),this.y=Math.max(this.y,t.y),this}clamp(t,e){return this.x=se(this.x,t.x,e.x),this.y=se(this.y,t.y,e.y),this}clampScalar(t,e){return this.x=se(this.x,t,e),this.y=se(this.y,t,e),this}clampLength(t,e){let n=this.length();return this.divideScalar(n||1).multiplyScalar(se(n,t,e))}floor(){return this.x=Math.floor(this.x),this.y=Math.floor(this.y),this}ceil(){return this.x=Math.ceil(this.x),this.y=Math.ceil(this.y),this}round(){return this.x=Math.round(this.x),this.y=Math.round(this.y),this}roundToZero(){return this.x=Math.trunc(this.x),this.y=Math.trunc(this.y),this}negate(){return this.x=-this.x,this.y=-this.y,this}dot(t){return this.x*t.x+this.y*t.y}cross(t){return this.x*t.y-this.y*t.x}lengthSq(){return this.x*this.x+this.y*this.y}length(){return Math.sqrt(this.x*this.x+this.y*this.y)}manhattanLength(){return Math.abs(this.x)+Math.abs(this.y)}normalize(){return this.divideScalar(this.length()||1)}angle(){return Math.atan2(-this.y,-this.x)+Math.PI}angleTo(t){let e=Math.sqrt(this.lengthSq()*t.lengthSq());if(e===0)return Math.PI/2;let n=this.dot(t)/e;return Math.acos(se(n,-1,1))}distanceTo(t){return Math.sqrt(this.distanceToSquared(t))}distanceToSquared(t){let e=this.x-t.x,n=this.y-t.y;return e*e+n*n}manhattanDistanceTo(t){return Math.abs(this.x-t.x)+Math.abs(this.y-t.y)}setLength(t){return this.normalize().multiplyScalar(t)}lerp(t,e){return this.x+=(t.x-this.x)*e,this.y+=(t.y-this.y)*e,this}lerpVectors(t,e,n){return this.x=t.x+(e.x-t.x)*n,this.y=t.y+(e.y-t.y)*n,this}equals(t){return t.x===this.x&&t.y===this.y}fromArray(t,e=0){return this.x=t[e],this.y=t[e+1],this}toArray(t=[],e=0){return t[e]=this.x,t[e+1]=this.y,t}fromBufferAttribute(t,e){return this.x=t.getX(e),this.y=t.getY(e),this}rotateAround(t,e){let n=Math.cos(e),i=Math.sin(e),r=this.x-t.x,a=this.y-t.y;return this.x=r*n-a*i+t.x,this.y=r*i+a*n+t.y,this}random(){return this.x=Math.random(),this.y=Math.random(),this}*[Symbol.iterator](){yield this.x,yield this.y}};Vc.prototype.isVector2=!0;var rt=Vc,pn=class{constructor(t=0,e=0,n=0,i=1){this.isQuaternion=!0,this._x=t,this._y=e,this._z=n,this._w=i}static slerpFlat(t,e,n,i,r,a,o){let l=n[i+0],c=n[i+1],h=n[i+2],u=n[i+3],d=r[a+0],f=r[a+1],p=r[a+2],y=r[a+3];if(u!==y||l!==d||c!==f||h!==p){let g=l*d+c*f+h*p+u*y;g<0&&(d=-d,f=-f,p=-p,y=-y,g=-g);let m=1-o;if(g<.9995){let S=Math.acos(g),T=Math.sin(S);m=Math.sin(m*S)/T,o=Math.sin(o*S)/T,l=l*m+d*o,c=c*m+f*o,h=h*m+p*o,u=u*m+y*o}else{l=l*m+d*o,c=c*m+f*o,h=h*m+p*o,u=u*m+y*o;let S=1/Math.sqrt(l*l+c*c+h*h+u*u);l*=S,c*=S,h*=S,u*=S}}t[e]=l,t[e+1]=c,t[e+2]=h,t[e+3]=u}static multiplyQuaternionsFlat(t,e,n,i,r,a){let o=n[i],l=n[i+1],c=n[i+2],h=n[i+3],u=r[a],d=r[a+1],f=r[a+2],p=r[a+3];return t[e]=o*p+h*u+l*f-c*d,t[e+1]=l*p+h*d+c*u-o*f,t[e+2]=c*p+h*f+o*d-l*u,t[e+3]=h*p-o*u-l*d-c*f,t}get x(){return this._x}set x(t){this._x=t,this._onChangeCallback()}get y(){return this._y}set y(t){this._y=t,this._onChangeCallback()}get z(){return this._z}set z(t){this._z=t,this._onChangeCallback()}get w(){return this._w}set w(t){this._w=t,this._onChangeCallback()}set(t,e,n,i){return this._x=t,this._y=e,this._z=n,this._w=i,this._onChangeCallback(),this}clone(){return new this.constructor(this._x,this._y,this._z,this._w)}copy(t){return this._x=t.x,this._y=t.y,this._z=t.z,this._w=t.w,this._onChangeCallback(),this}setFromEuler(t,e=!0){let n=t._x,i=t._y,r=t._z,a=t._order,o=Math.cos,l=Math.sin,c=o(n/2),h=o(i/2),u=o(r/2),d=l(n/2),f=l(i/2),p=l(r/2);switch(a){case"XYZ":this._x=d*h*u+c*f*p,this._y=c*f*u-d*h*p,this._z=c*h*p+d*f*u,this._w=c*h*u-d*f*p;break;case"YXZ":this._x=d*h*u+c*f*p,this._y=c*f*u-d*h*p,this._z=c*h*p-d*f*u,this._w=c*h*u+d*f*p;break;case"ZXY":this._x=d*h*u-c*f*p,this._y=c*f*u+d*h*p,this._z=c*h*p+d*f*u,this._w=c*h*u-d*f*p;break;case"ZYX":this._x=d*h*u-c*f*p,this._y=c*f*u+d*h*p,this._z=c*h*p-d*f*u,this._w=c*h*u+d*f*p;break;case"YZX":this._x=d*h*u+c*f*p,this._y=c*f*u+d*h*p,this._z=c*h*p-d*f*u,this._w=c*h*u-d*f*p;break;case"XZY":this._x=d*h*u-c*f*p,this._y=c*f*u-d*h*p,this._z=c*h*p+d*f*u,this._w=c*h*u+d*f*p;break;default:qt("Quaternion: .setFromEuler() encountered an unknown order: "+a)}return e===!0&&this._onChangeCallback(),this}setFromAxisAngle(t,e){let n=e/2,i=Math.sin(n);return this._x=t.x*i,this._y=t.y*i,this._z=t.z*i,this._w=Math.cos(n),this._onChangeCallback(),this}setFromRotationMatrix(t){let e=t.elements,n=e[0],i=e[4],r=e[8],a=e[1],o=e[5],l=e[9],c=e[2],h=e[6],u=e[10],d=n+o+u;if(d>0){let f=.5/Math.sqrt(d+1);this._w=.25/f,this._x=(h-l)*f,this._y=(r-c)*f,this._z=(a-i)*f}else if(n>o&&n>u){let f=2*Math.sqrt(1+n-o-u);this._w=(h-l)/f,this._x=.25*f,this._y=(i+a)/f,this._z=(r+c)/f}else if(o>u){let f=2*Math.sqrt(1+o-n-u);this._w=(r-c)/f,this._x=(i+a)/f,this._y=.25*f,this._z=(l+h)/f}else{let f=2*Math.sqrt(1+u-n-o);this._w=(a-i)/f,this._x=(r+c)/f,this._y=(l+h)/f,this._z=.25*f}return this._onChangeCallback(),this}setFromUnitVectors(t,e){let n=t.dot(e)+1;return n<1e-8?(n=0,Math.abs(t.x)>Math.abs(t.z)?(this._x=-t.y,this._y=t.x,this._z=0,this._w=n):(this._x=0,this._y=-t.z,this._z=t.y,this._w=n)):(this._x=t.y*e.z-t.z*e.y,this._y=t.z*e.x-t.x*e.z,this._z=t.x*e.y-t.y*e.x,this._w=n),this.normalize()}angleTo(t){return 2*Math.acos(Math.abs(se(this.dot(t),-1,1)))}rotateTowards(t,e){let n=this.angleTo(t);if(n===0)return this;let i=Math.min(1,e/n);return this.slerp(t,i),this}identity(){return this.set(0,0,0,1)}invert(){return this.conjugate()}conjugate(){return this._x*=-1,this._y*=-1,this._z*=-1,this._onChangeCallback(),this}dot(t){return this._x*t._x+this._y*t._y+this._z*t._z+this._w*t._w}lengthSq(){return this._x*this._x+this._y*this._y+this._z*this._z+this._w*this._w}length(){return Math.sqrt(this._x*this._x+this._y*this._y+this._z*this._z+this._w*this._w)}normalize(){let t=this.length();return t===0?(this._x=0,this._y=0,this._z=0,this._w=1):(t=1/t,this._x=this._x*t,this._y=this._y*t,this._z=this._z*t,this._w=this._w*t),this._onChangeCallback(),this}multiply(t){return this.multiplyQuaternions(this,t)}premultiply(t){return this.multiplyQuaternions(t,this)}multiplyQuaternions(t,e){let n=t._x,i=t._y,r=t._z,a=t._w,o=e._x,l=e._y,c=e._z,h=e._w;return this._x=n*h+a*o+i*c-r*l,this._y=i*h+a*l+r*o-n*c,this._z=r*h+a*c+n*l-i*o,this._w=a*h-n*o-i*l-r*c,this._onChangeCallback(),this}slerp(t,e){let n=t._x,i=t._y,r=t._z,a=t._w,o=this.dot(t);o<0&&(n=-n,i=-i,r=-r,a=-a,o=-o);let l=1-e;if(o<.9995){let c=Math.acos(o),h=Math.sin(c);l=Math.sin(l*c)/h,e=Math.sin(e*c)/h,this._x=this._x*l+n*e,this._y=this._y*l+i*e,this._z=this._z*l+r*e,this._w=this._w*l+a*e,this._onChangeCallback()}else this._x=this._x*l+n*e,this._y=this._y*l+i*e,this._z=this._z*l+r*e,this._w=this._w*l+a*e,this.normalize();return this}slerpQuaternions(t,e,n){return this.copy(t).slerp(e,n)}random(){let t=2*Math.PI*Math.random(),e=2*Math.PI*Math.random(),n=Math.random(),i=Math.sqrt(1-n),r=Math.sqrt(n);return this.set(i*Math.sin(t),i*Math.cos(t),r*Math.sin(e),r*Math.cos(e))}equals(t){return t._x===this._x&&t._y===this._y&&t._z===this._z&&t._w===this._w}fromArray(t,e=0){return this._x=t[e],this._y=t[e+1],this._z=t[e+2],this._w=t[e+3],this._onChangeCallback(),this}toArray(t=[],e=0){return t[e]=this._x,t[e+1]=this._y,t[e+2]=this._z,t[e+3]=this._w,t}fromBufferAttribute(t,e){return this._x=t.getX(e),this._y=t.getY(e),this._z=t.getZ(e),this._w=t.getW(e),this._onChangeCallback(),this}toJSON(){return this.toArray()}_onChange(t){return this._onChangeCallback=t,this}_onChangeCallback(){}*[Symbol.iterator](){yield this._x,yield this._y,yield this._z,yield this._w}},Gc=class Gc{constructor(t=0,e=0,n=0){this.x=t,this.y=e,this.z=n}set(t,e,n){return n===void 0&&(n=this.z),this.x=t,this.y=e,this.z=n,this}setScalar(t){return this.x=t,this.y=t,this.z=t,this}setX(t){return this.x=t,this}setY(t){return this.y=t,this}setZ(t){return this.z=t,this}setComponent(t,e){switch(t){case 0:this.x=e;break;case 1:this.y=e;break;case 2:this.z=e;break;default:throw new Error("THREE.Vector3: index is out of range: "+t)}return this}getComponent(t){switch(t){case 0:return this.x;case 1:return this.y;case 2:return this.z;default:throw new Error("THREE.Vector3: index is out of range: "+t)}}clone(){return new this.constructor(this.x,this.y,this.z)}copy(t){return this.x=t.x,this.y=t.y,this.z=t.z,this}add(t){return this.x+=t.x,this.y+=t.y,this.z+=t.z,this}addScalar(t){return this.x+=t,this.y+=t,this.z+=t,this}addVectors(t,e){return this.x=t.x+e.x,this.y=t.y+e.y,this.z=t.z+e.z,this}addScaledVector(t,e){return this.x+=t.x*e,this.y+=t.y*e,this.z+=t.z*e,this}sub(t){return this.x-=t.x,this.y-=t.y,this.z-=t.z,this}subScalar(t){return this.x-=t,this.y-=t,this.z-=t,this}subVectors(t,e){return this.x=t.x-e.x,this.y=t.y-e.y,this.z=t.z-e.z,this}multiply(t){return this.x*=t.x,this.y*=t.y,this.z*=t.z,this}multiplyScalar(t){return this.x*=t,this.y*=t,this.z*=t,this}multiplyVectors(t,e){return this.x=t.x*e.x,this.y=t.y*e.y,this.z=t.z*e.z,this}applyEuler(t){return this.applyQuaternion(Rh.setFromEuler(t))}applyAxisAngle(t,e){return this.applyQuaternion(Rh.setFromAxisAngle(t,e))}applyMatrix3(t){let e=this.x,n=this.y,i=this.z,r=t.elements;return this.x=r[0]*e+r[3]*n+r[6]*i,this.y=r[1]*e+r[4]*n+r[7]*i,this.z=r[2]*e+r[5]*n+r[8]*i,this}applyNormalMatrix(t){return this.applyMatrix3(t).normalize()}applyMatrix4(t){let e=this.x,n=this.y,i=this.z,r=t.elements,a=1/(r[3]*e+r[7]*n+r[11]*i+r[15]);return this.x=(r[0]*e+r[4]*n+r[8]*i+r[12])*a,this.y=(r[1]*e+r[5]*n+r[9]*i+r[13])*a,this.z=(r[2]*e+r[6]*n+r[10]*i+r[14])*a,this}applyQuaternion(t){let e=this.x,n=this.y,i=this.z,r=t.x,a=t.y,o=t.z,l=t.w,c=2*(a*i-o*n),h=2*(o*e-r*i),u=2*(r*n-a*e);return this.x=e+l*c+a*u-o*h,this.y=n+l*h+o*c-r*u,this.z=i+l*u+r*h-a*c,this}project(t){return this.applyMatrix4(t.matrixWorldInverse).applyMatrix4(t.projectionMatrix)}unproject(t){return this.applyMatrix4(t.projectionMatrixInverse).applyMatrix4(t.matrixWorld)}transformDirection(t){let e=this.x,n=this.y,i=this.z,r=t.elements;return this.x=r[0]*e+r[4]*n+r[8]*i,this.y=r[1]*e+r[5]*n+r[9]*i,this.z=r[2]*e+r[6]*n+r[10]*i,this.normalize()}divide(t){return this.x/=t.x,this.y/=t.y,this.z/=t.z,this}divideScalar(t){return this.multiplyScalar(1/t)}min(t){return this.x=Math.min(this.x,t.x),this.y=Math.min(this.y,t.y),this.z=Math.min(this.z,t.z),this}max(t){return this.x=Math.max(this.x,t.x),this.y=Math.max(this.y,t.y),this.z=Math.max(this.z,t.z),this}clamp(t,e){return this.x=se(this.x,t.x,e.x),this.y=se(this.y,t.y,e.y),this.z=se(this.z,t.z,e.z),this}clampScalar(t,e){return this.x=se(this.x,t,e),this.y=se(this.y,t,e),this.z=se(this.z,t,e),this}clampLength(t,e){let n=this.length();return this.divideScalar(n||1).multiplyScalar(se(n,t,e))}floor(){return this.x=Math.floor(this.x),this.y=Math.floor(this.y),this.z=Math.floor(this.z),this}ceil(){return this.x=Math.ceil(this.x),this.y=Math.ceil(this.y),this.z=Math.ceil(this.z),this}round(){return this.x=Math.round(this.x),this.y=Math.round(this.y),this.z=Math.round(this.z),this}roundToZero(){return this.x=Math.trunc(this.x),this.y=Math.trunc(this.y),this.z=Math.trunc(this.z),this}negate(){return this.x=-this.x,this.y=-this.y,this.z=-this.z,this}dot(t){return this.x*t.x+this.y*t.y+this.z*t.z}lengthSq(){return this.x*this.x+this.y*this.y+this.z*this.z}length(){return Math.sqrt(this.x*this.x+this.y*this.y+this.z*this.z)}manhattanLength(){return Math.abs(this.x)+Math.abs(this.y)+Math.abs(this.z)}normalize(){return this.divideScalar(this.length()||1)}setLength(t){return this.normalize().multiplyScalar(t)}lerp(t,e){return this.x+=(t.x-this.x)*e,this.y+=(t.y-this.y)*e,this.z+=(t.z-this.z)*e,this}lerpVectors(t,e,n){return this.x=t.x+(e.x-t.x)*n,this.y=t.y+(e.y-t.y)*n,this.z=t.z+(e.z-t.z)*n,this}cross(t){return this.crossVectors(this,t)}crossVectors(t,e){let n=t.x,i=t.y,r=t.z,a=e.x,o=e.y,l=e.z;return this.x=i*l-r*o,this.y=r*a-n*l,this.z=n*o-i*a,this}projectOnVector(t){let e=t.lengthSq();if(e===0)return this.set(0,0,0);let n=t.dot(this)/e;return this.copy(t).multiplyScalar(n)}projectOnPlane(t){return zl.copy(this).projectOnVector(t),this.sub(zl)}reflect(t){return this.sub(zl.copy(t).multiplyScalar(2*this.dot(t)))}angleTo(t){let e=Math.sqrt(this.lengthSq()*t.lengthSq());if(e===0)return Math.PI/2;let n=this.dot(t)/e;return Math.acos(se(n,-1,1))}distanceTo(t){return Math.sqrt(this.distanceToSquared(t))}distanceToSquared(t){let e=this.x-t.x,n=this.y-t.y,i=this.z-t.z;return e*e+n*n+i*i}manhattanDistanceTo(t){return Math.abs(this.x-t.x)+Math.abs(this.y-t.y)+Math.abs(this.z-t.z)}setFromSpherical(t){return this.setFromSphericalCoords(t.radius,t.phi,t.theta)}setFromSphericalCoords(t,e,n){let i=Math.sin(e)*t;return this.x=i*Math.sin(n),this.y=Math.cos(e)*t,this.z=i*Math.cos(n),this}setFromCylindrical(t){return this.setFromCylindricalCoords(t.radius,t.theta,t.y)}setFromCylindricalCoords(t,e,n){return this.x=t*Math.sin(e),this.y=n,this.z=t*Math.cos(e),this}setFromMatrixPosition(t){let e=t.elements;return this.x=e[12],this.y=e[13],this.z=e[14],this}setFromMatrixScale(t){let e=this.setFromMatrixColumn(t,0).length(),n=this.setFromMatrixColumn(t,1).length(),i=this.setFromMatrixColumn(t,2).length();return this.x=e,this.y=n,this.z=i,this}setFromMatrixColumn(t,e){return this.fromArray(t.elements,e*4)}setFromMatrix3Column(t,e){return this.fromArray(t.elements,e*3)}setFromEuler(t){return this.x=t._x,this.y=t._y,this.z=t._z,this}setFromColor(t){return this.x=t.r,this.y=t.g,this.z=t.b,this}equals(t){return t.x===this.x&&t.y===this.y&&t.z===this.z}fromArray(t,e=0){return this.x=t[e],this.y=t[e+1],this.z=t[e+2],this}toArray(t=[],e=0){return t[e]=this.x,t[e+1]=this.y,t[e+2]=this.z,t}fromBufferAttribute(t,e){return this.x=t.getX(e),this.y=t.getY(e),this.z=t.getZ(e),this}random(){return this.x=Math.random(),this.y=Math.random(),this.z=Math.random(),this}randomDirection(){let t=Math.random()*Math.PI*2,e=Math.random()*2-1,n=Math.sqrt(1-e*e);return this.x=n*Math.cos(t),this.y=e,this.z=n*Math.sin(t),this}*[Symbol.iterator](){yield this.x,yield this.y,yield this.z}};Gc.prototype.isVector3=!0;var D=Gc,zl=new D,Rh=new pn,Wc=class Wc{constructor(t,e,n,i,r,a,o,l,c){this.elements=[1,0,0,0,1,0,0,0,1],t!==void 0&&this.set(t,e,n,i,r,a,o,l,c)}set(t,e,n,i,r,a,o,l,c){let h=this.elements;return h[0]=t,h[1]=i,h[2]=o,h[3]=e,h[4]=r,h[5]=l,h[6]=n,h[7]=a,h[8]=c,this}identity(){return this.set(1,0,0,0,1,0,0,0,1),this}copy(t){let e=this.elements,n=t.elements;return e[0]=n[0],e[1]=n[1],e[2]=n[2],e[3]=n[3],e[4]=n[4],e[5]=n[5],e[6]=n[6],e[7]=n[7],e[8]=n[8],this}extractBasis(t,e,n){return t.setFromMatrix3Column(this,0),e.setFromMatrix3Column(this,1),n.setFromMatrix3Column(this,2),this}setFromMatrix4(t){let e=t.elements;return this.set(e[0],e[4],e[8],e[1],e[5],e[9],e[2],e[6],e[10]),this}multiply(t){return this.multiplyMatrices(this,t)}premultiply(t){return this.multiplyMatrices(t,this)}multiplyMatrices(t,e){let n=t.elements,i=e.elements,r=this.elements,a=n[0],o=n[3],l=n[6],c=n[1],h=n[4],u=n[7],d=n[2],f=n[5],p=n[8],y=i[0],g=i[3],m=i[6],S=i[1],T=i[4],v=i[7],b=i[2],w=i[5],P=i[8];return r[0]=a*y+o*S+l*b,r[3]=a*g+o*T+l*w,r[6]=a*m+o*v+l*P,r[1]=c*y+h*S+u*b,r[4]=c*g+h*T+u*w,r[7]=c*m+h*v+u*P,r[2]=d*y+f*S+p*b,r[5]=d*g+f*T+p*w,r[8]=d*m+f*v+p*P,this}multiplyScalar(t){let e=this.elements;return e[0]*=t,e[3]*=t,e[6]*=t,e[1]*=t,e[4]*=t,e[7]*=t,e[2]*=t,e[5]*=t,e[8]*=t,this}determinant(){let t=this.elements,e=t[0],n=t[1],i=t[2],r=t[3],a=t[4],o=t[5],l=t[6],c=t[7],h=t[8];return e*a*h-e*o*c-n*r*h+n*o*l+i*r*c-i*a*l}invert(){let t=this.elements,e=t[0],n=t[1],i=t[2],r=t[3],a=t[4],o=t[5],l=t[6],c=t[7],h=t[8],u=h*a-o*c,d=o*l-h*r,f=c*r-a*l,p=e*u+n*d+i*f;if(p===0)return this.set(0,0,0,0,0,0,0,0,0);let y=1/p;return t[0]=u*y,t[1]=(i*c-h*n)*y,t[2]=(o*n-i*a)*y,t[3]=d*y,t[4]=(h*e-i*l)*y,t[5]=(i*r-o*e)*y,t[6]=f*y,t[7]=(n*l-c*e)*y,t[8]=(a*e-n*r)*y,this}transpose(){let t,e=this.elements;return t=e[1],e[1]=e[3],e[3]=t,t=e[2],e[2]=e[6],e[6]=t,t=e[5],e[5]=e[7],e[7]=t,this}getNormalMatrix(t){return this.setFromMatrix4(t).invert().transpose()}transposeIntoArray(t){let e=this.elements;return t[0]=e[0],t[1]=e[3],t[2]=e[6],t[3]=e[1],t[4]=e[4],t[5]=e[7],t[6]=e[2],t[7]=e[5],t[8]=e[8],this}setUvTransform(t,e,n,i,r,a,o){let l=Math.cos(r),c=Math.sin(r);return this.set(n*l,n*c,-n*(l*a+c*o)+a+t,-i*c,i*l,-i*(-c*a+l*o)+o+e,0,0,1),this}scale(t,e){return ki("Matrix3: .scale() is deprecated. Use .makeScale() instead."),this.premultiply(Hl.makeScale(t,e)),this}rotate(t){return ki("Matrix3: .rotate() is deprecated. Use .makeRotation() instead."),this.premultiply(Hl.makeRotation(-t)),this}translate(t,e){return ki("Matrix3: .translate() is deprecated. Use .makeTranslation() instead."),this.premultiply(Hl.makeTranslation(t,e)),this}makeTranslation(t,e){return t.isVector2?this.set(1,0,t.x,0,1,t.y,0,0,1):this.set(1,0,t,0,1,e,0,0,1),this}makeRotation(t){let e=Math.cos(t),n=Math.sin(t);return this.set(e,-n,0,n,e,0,0,0,1),this}makeScale(t,e){return this.set(t,0,0,0,e,0,0,0,1),this}equals(t){let e=this.elements,n=t.elements;for(let i=0;i<9;i++)if(e[i]!==n[i])return!1;return!0}fromArray(t,e=0){for(let n=0;n<9;n++)this.elements[n]=t[n+e];return this}toArray(t=[],e=0){let n=this.elements;return t[e]=n[0],t[e+1]=n[1],t[e+2]=n[2],t[e+3]=n[3],t[e+4]=n[4],t[e+5]=n[5],t[e+6]=n[6],t[e+7]=n[7],t[e+8]=n[8],t}clone(){return new this.constructor().fromArray(this.elements)}};Wc.prototype.isMatrix3=!0;var $t=Wc,Hl=new $t,Ch=new $t().set(.4123908,.3575843,.1804808,.212639,.7151687,.0721923,.0193308,.1191948,.9505322),Ph=new $t().set(3.2409699,-1.5373832,-.4986108,-.9692436,1.8759675,.0415551,.0556301,-.203977,1.0569715);function rf(){let s={enabled:!0,workingColorSpace:dr,spaces:{},convert:function(i,r,a){return this.enabled===!1||r===a||!r||!a||(this.spaces[r].transfer===ce&&(i.r=ei(i.r),i.g=ei(i.g),i.b=ei(i.b)),this.spaces[r].primaries!==this.spaces[a].primaries&&(i.applyMatrix3(this.spaces[r].toXYZ),i.applyMatrix3(this.spaces[a].fromXYZ)),this.spaces[a].transfer===ce&&(i.r=Ss(i.r),i.g=Ss(i.g),i.b=Ss(i.b))),i},workingToColorSpace:function(i,r){return this.convert(i,this.workingColorSpace,r)},colorSpaceToWorking:function(i,r){return this.convert(i,r,this.workingColorSpace)},getPrimaries:function(i){return this.spaces[i].primaries},getTransfer:function(i){return i===ii?fr:this.spaces[i].transfer},getToneMappingMode:function(i){return this.spaces[i].outputColorSpaceConfig.toneMappingMode||"standard"},getLuminanceCoefficients:function(i,r=this.workingColorSpace){return i.fromArray(this.spaces[r].luminanceCoefficients)},define:function(i){Object.assign(this.spaces,i)},_getMatrix:function(i,r,a){return i.copy(this.spaces[r].toXYZ).multiply(this.spaces[a].fromXYZ)},_getDrawingBufferColorSpace:function(i){return this.spaces[i].outputColorSpaceConfig.drawingBufferColorSpace},_getUnpackColorSpace:function(i=this.workingColorSpace){return this.spaces[i].workingColorSpaceConfig.unpackColorSpace},fromWorkingColorSpace:function(i,r){return ki("ColorManagement: .fromWorkingColorSpace() has been renamed to .workingToColorSpace()."),s.workingToColorSpace(i,r)},toWorkingColorSpace:function(i,r){return ki("ColorManagement: .toWorkingColorSpace() has been renamed to .colorSpaceToWorking()."),s.colorSpaceToWorking(i,r)}},t=[.64,.33,.3,.6,.15,.06],e=[.2126,.7152,.0722],n=[.3127,.329];return s.define({[dr]:{primaries:t,whitePoint:n,transfer:fr,toXYZ:Ch,fromXYZ:Ph,luminanceCoefficients:e,workingColorSpaceConfig:{unpackColorSpace:Be},outputColorSpaceConfig:{drawingBufferColorSpace:Be}},[Be]:{primaries:t,whitePoint:n,transfer:ce,toXYZ:Ch,fromXYZ:Ph,luminanceCoefficients:e,outputColorSpaceConfig:{drawingBufferColorSpace:Be}}}),s}var ne=rf();function ei(s){return s<.04045?s*.0773993808:Math.pow(s*.9478672986+.0521327014,2.4)}function Ss(s){return s<.0031308?s*12.92:1.055*Math.pow(s,.41666)-.055}var ss,Qa=class{static getDataURL(t,e="image/png"){if(/^data:/i.test(t.src)||typeof HTMLCanvasElement>"u")return t.src;let n;if(t instanceof HTMLCanvasElement)n=t;else{ss===void 0&&(ss=pr("canvas")),ss.width=t.width,ss.height=t.height;let i=ss.getContext("2d");t instanceof ImageData?i.putImageData(t,0,0):i.drawImage(t,0,0,t.width,t.height),n=ss}return n.toDataURL(e)}static sRGBToLinear(t){if(typeof HTMLImageElement<"u"&&t instanceof HTMLImageElement||typeof HTMLCanvasElement<"u"&&t instanceof HTMLCanvasElement||typeof ImageBitmap<"u"&&t instanceof ImageBitmap){let e=pr("canvas");e.width=t.width,e.height=t.height;let n=e.getContext("2d");n.drawImage(t,0,0,t.width,t.height);let i=n.getImageData(0,0,t.width,t.height),r=i.data;for(let a=0;a<r.length;a++)r[a]=ei(r[a]/255)*255;return n.putImageData(i,0,0),e}else if(t.data){let e=t.data.slice(0);for(let n=0;n<e.length;n++)e instanceof Uint8Array||e instanceof Uint8ClampedArray?e[n]=Math.floor(ei(e[n]/255)*255):e[n]=ei(e[n]);return{data:e,width:t.width,height:t.height}}else return qt("ImageUtils.sRGBToLinear(): Unsupported image type. No color space conversion applied."),t}},af=0,Ts=class{constructor(t=null){this.isTextureSource=!0,Object.defineProperty(this,"id",{value:af++}),this.uuid=ti(),this.data=t,this.dataReady=!0,this.version=0}getSize(t){let e=this.data;return typeof HTMLVideoElement<"u"&&e instanceof HTMLVideoElement?t.set(e.videoWidth,e.videoHeight,0):typeof VideoFrame<"u"&&e instanceof VideoFrame?t.set(e.displayWidth,e.displayHeight,0):e!==null?t.set(e.width,e.height,e.depth||0):t.set(0,0,0),t}set needsUpdate(t){t===!0&&this.version++}toJSON(t){let e=t===void 0||typeof t=="string";if(!e&&t.images[this.uuid]!==void 0)return t.images[this.uuid];let n={uuid:this.uuid,url:""},i=this.data;if(i!==null){let r;if(Array.isArray(i)){r=[];for(let a=0,o=i.length;a<o;a++)i[a].isDataTexture?r.push(Vl(i[a].image)):r.push(Vl(i[a]))}else r=Vl(i);n.url=r}return e||(t.images[this.uuid]=n),n}};function Vl(s){return typeof HTMLImageElement<"u"&&s instanceof HTMLImageElement||typeof HTMLCanvasElement<"u"&&s instanceof HTMLCanvasElement||typeof ImageBitmap<"u"&&s instanceof ImageBitmap?Qa.getDataURL(s):s.data?{data:Array.from(s.data),width:s.width,height:s.height,type:s.data.constructor.name}:(qt("Texture: Unable to serialize Texture."),{})}var of=0,Gl=new D,nn=class s extends Vn{constructor(t=s.DEFAULT_IMAGE,e=s.DEFAULT_MAPPING,n=On,i=On,r=Ge,a=Ai,o=vn,l=rn,c=s.DEFAULT_ANISOTROPY,h=ii){super(),this.isTexture=!0,Object.defineProperty(this,"id",{value:of++}),this.uuid=ti(),this.name="",this.source=new Ts(t),this.mipmaps=[],this.mapping=e,this.channel=0,this.wrapS=n,this.wrapT=i,this.magFilter=r,this.minFilter=a,this.anisotropy=c,this.format=o,this.internalFormat=null,this.type=l,this.offset=new rt(0,0),this.repeat=new rt(1,1),this.center=new rt(0,0),this.rotation=0,this.matrixAutoUpdate=!0,this.matrix=new $t,this.generateMipmaps=!0,this.premultiplyAlpha=!1,this.flipY=!0,this.unpackAlignment=4,this.colorSpace=h,this.userData={},this.updateRanges=[],this.version=0,this.onUpdate=null,this.renderTarget=null,this.isRenderTargetTexture=!1,this.isArrayTexture=!!(t&&t.depth&&t.depth>1),this.pmremVersion=0,this.normalized=!1}get width(){return this.source.getSize(Gl).x}get height(){return this.source.getSize(Gl).y}get depth(){return this.source.getSize(Gl).z}get image(){return this.source.data}set image(t){this.source.data=t}updateMatrix(){this.matrix.setUvTransform(this.offset.x,this.offset.y,this.repeat.x,this.repeat.y,this.rotation,this.center.x,this.center.y)}addUpdateRange(t,e){this.updateRanges.push({start:t,count:e})}clearUpdateRanges(){this.updateRanges.length=0}clone(){return new this.constructor().copy(this)}copy(t){return this.name=t.name,this.source=t.source,this.mipmaps=t.mipmaps.slice(0),this.mapping=t.mapping,this.channel=t.channel,this.wrapS=t.wrapS,this.wrapT=t.wrapT,this.magFilter=t.magFilter,this.minFilter=t.minFilter,this.anisotropy=t.anisotropy,this.format=t.format,this.internalFormat=t.internalFormat,this.type=t.type,this.normalized=t.normalized,this.offset.copy(t.offset),this.repeat.copy(t.repeat),this.center.copy(t.center),this.rotation=t.rotation,this.matrixAutoUpdate=t.matrixAutoUpdate,this.matrix.copy(t.matrix),this.generateMipmaps=t.generateMipmaps,this.premultiplyAlpha=t.premultiplyAlpha,this.flipY=t.flipY,this.unpackAlignment=t.unpackAlignment,this.colorSpace=t.colorSpace,this.renderTarget=t.renderTarget,this.isRenderTargetTexture=t.isRenderTargetTexture,this.isArrayTexture=t.isArrayTexture,this.userData=JSON.parse(JSON.stringify(t.userData)),this.needsUpdate=!0,this}setValues(t){for(let e in t){let n=t[e];if(n===void 0){qt(`Texture.setValues(): parameter '${e}' has value of undefined.`);continue}let i=this[e];if(i===void 0){qt(`Texture.setValues(): property '${e}' does not exist.`);continue}i&&n&&i.isVector2&&n.isVector2||i&&n&&i.isVector3&&n.isVector3||i&&n&&i.isMatrix3&&n.isMatrix3?i.copy(n):this[e]=n}}toJSON(t){let e=t===void 0||typeof t=="string";if(!e&&t.textures[this.uuid]!==void 0)return t.textures[this.uuid];let n={metadata:{version:4.7,type:"Texture",generator:"Texture.toJSON"},uuid:this.uuid,name:this.name,image:this.source.toJSON(t).uuid,mapping:this.mapping,channel:this.channel,repeat:[this.repeat.x,this.repeat.y],offset:[this.offset.x,this.offset.y],center:[this.center.x,this.center.y],rotation:this.rotation,wrap:[this.wrapS,this.wrapT],format:this.format,internalFormat:this.internalFormat,type:this.type,normalized:this.normalized,colorSpace:this.colorSpace,minFilter:this.minFilter,magFilter:this.magFilter,anisotropy:this.anisotropy,flipY:this.flipY,generateMipmaps:this.generateMipmaps,premultiplyAlpha:this.premultiplyAlpha,unpackAlignment:this.unpackAlignment};return Object.keys(this.userData).length>0&&(n.userData=this.userData),e||(t.textures[this.uuid]=n),n}dispose(){this.dispatchEvent({type:"dispose"})}transformUv(t){if(this.mapping!==Rc)return t;if(t.applyMatrix3(this.matrix),t.x<0||t.x>1)switch(this.wrapS){case zn:t.x=t.x-Math.floor(t.x);break;case On:t.x=t.x<0?0:1;break;case $a:Math.abs(Math.floor(t.x)%2)===1?t.x=Math.ceil(t.x)-t.x:t.x=t.x-Math.floor(t.x);break}if(t.y<0||t.y>1)switch(this.wrapT){case zn:t.y=t.y-Math.floor(t.y);break;case On:t.y=t.y<0?0:1;break;case $a:Math.abs(Math.floor(t.y)%2)===1?t.y=Math.ceil(t.y)-t.y:t.y=t.y-Math.floor(t.y);break}return this.flipY&&(t.y=1-t.y),t}set needsUpdate(t){t===!0&&(this.version++,this.source.needsUpdate=!0)}set needsPMREMUpdate(t){t===!0&&this.pmremVersion++}};nn.DEFAULT_IMAGE=null;nn.DEFAULT_MAPPING=Rc;nn.DEFAULT_ANISOTROPY=1;var Xc=class Xc{constructor(t=0,e=0,n=0,i=1){this.x=t,this.y=e,this.z=n,this.w=i}get width(){return this.z}set width(t){this.z=t}get height(){return this.w}set height(t){this.w=t}set(t,e,n,i){return this.x=t,this.y=e,this.z=n,this.w=i,this}setScalar(t){return this.x=t,this.y=t,this.z=t,this.w=t,this}setX(t){return this.x=t,this}setY(t){return this.y=t,this}setZ(t){return this.z=t,this}setW(t){return this.w=t,this}setComponent(t,e){switch(t){case 0:this.x=e;break;case 1:this.y=e;break;case 2:this.z=e;break;case 3:this.w=e;break;default:throw new Error("THREE.Vector4: index is out of range: "+t)}return this}getComponent(t){switch(t){case 0:return this.x;case 1:return this.y;case 2:return this.z;case 3:return this.w;default:throw new Error("THREE.Vector4: index is out of range: "+t)}}clone(){return new this.constructor(this.x,this.y,this.z,this.w)}copy(t){return this.x=t.x,this.y=t.y,this.z=t.z,this.w=t.w!==void 0?t.w:1,this}add(t){return this.x+=t.x,this.y+=t.y,this.z+=t.z,this.w+=t.w,this}addScalar(t){return this.x+=t,this.y+=t,this.z+=t,this.w+=t,this}addVectors(t,e){return this.x=t.x+e.x,this.y=t.y+e.y,this.z=t.z+e.z,this.w=t.w+e.w,this}addScaledVector(t,e){return this.x+=t.x*e,this.y+=t.y*e,this.z+=t.z*e,this.w+=t.w*e,this}sub(t){return this.x-=t.x,this.y-=t.y,this.z-=t.z,this.w-=t.w,this}subScalar(t){return this.x-=t,this.y-=t,this.z-=t,this.w-=t,this}subVectors(t,e){return this.x=t.x-e.x,this.y=t.y-e.y,this.z=t.z-e.z,this.w=t.w-e.w,this}multiply(t){return this.x*=t.x,this.y*=t.y,this.z*=t.z,this.w*=t.w,this}multiplyScalar(t){return this.x*=t,this.y*=t,this.z*=t,this.w*=t,this}applyMatrix4(t){let e=this.x,n=this.y,i=this.z,r=this.w,a=t.elements;return this.x=a[0]*e+a[4]*n+a[8]*i+a[12]*r,this.y=a[1]*e+a[5]*n+a[9]*i+a[13]*r,this.z=a[2]*e+a[6]*n+a[10]*i+a[14]*r,this.w=a[3]*e+a[7]*n+a[11]*i+a[15]*r,this}divide(t){return this.x/=t.x,this.y/=t.y,this.z/=t.z,this.w/=t.w,this}divideScalar(t){return this.multiplyScalar(1/t)}setAxisAngleFromQuaternion(t){this.w=2*Math.acos(t.w);let e=Math.sqrt(1-t.w*t.w);return e<1e-4?(this.x=1,this.y=0,this.z=0):(this.x=t.x/e,this.y=t.y/e,this.z=t.z/e),this}setAxisAngleFromRotationMatrix(t){let e,n,i,r,l=t.elements,c=l[0],h=l[4],u=l[8],d=l[1],f=l[5],p=l[9],y=l[2],g=l[6],m=l[10];if(Math.abs(h-d)<.01&&Math.abs(u-y)<.01&&Math.abs(p-g)<.01){if(Math.abs(h+d)<.1&&Math.abs(u+y)<.1&&Math.abs(p+g)<.1&&Math.abs(c+f+m-3)<.1)return this.set(1,0,0,0),this;e=Math.PI;let T=(c+1)/2,v=(f+1)/2,b=(m+1)/2,w=(h+d)/4,P=(u+y)/4,_=(p+g)/4;return T>v&&T>b?T<.01?(n=0,i=.707106781,r=.707106781):(n=Math.sqrt(T),i=w/n,r=P/n):v>b?v<.01?(n=.707106781,i=0,r=.707106781):(i=Math.sqrt(v),n=w/i,r=_/i):b<.01?(n=.707106781,i=.707106781,r=0):(r=Math.sqrt(b),n=P/r,i=_/r),this.set(n,i,r,e),this}let S=Math.sqrt((g-p)*(g-p)+(u-y)*(u-y)+(d-h)*(d-h));return Math.abs(S)<.001&&(S=1),this.x=(g-p)/S,this.y=(u-y)/S,this.z=(d-h)/S,this.w=Math.acos((c+f+m-1)/2),this}setFromMatrixPosition(t){let e=t.elements;return this.x=e[12],this.y=e[13],this.z=e[14],this.w=e[15],this}min(t){return this.x=Math.min(this.x,t.x),this.y=Math.min(this.y,t.y),this.z=Math.min(this.z,t.z),this.w=Math.min(this.w,t.w),this}max(t){return this.x=Math.max(this.x,t.x),this.y=Math.max(this.y,t.y),this.z=Math.max(this.z,t.z),this.w=Math.max(this.w,t.w),this}clamp(t,e){return this.x=se(this.x,t.x,e.x),this.y=se(this.y,t.y,e.y),this.z=se(this.z,t.z,e.z),this.w=se(this.w,t.w,e.w),this}clampScalar(t,e){return this.x=se(this.x,t,e),this.y=se(this.y,t,e),this.z=se(this.z,t,e),this.w=se(this.w,t,e),this}clampLength(t,e){let n=this.length();return this.divideScalar(n||1).multiplyScalar(se(n,t,e))}floor(){return this.x=Math.floor(this.x),this.y=Math.floor(this.y),this.z=Math.floor(this.z),this.w=Math.floor(this.w),this}ceil(){return this.x=Math.ceil(this.x),this.y=Math.ceil(this.y),this.z=Math.ceil(this.z),this.w=Math.ceil(this.w),this}round(){return this.x=Math.round(this.x),this.y=Math.round(this.y),this.z=Math.round(this.z),this.w=Math.round(this.w),this}roundToZero(){return this.x=Math.trunc(this.x),this.y=Math.trunc(this.y),this.z=Math.trunc(this.z),this.w=Math.trunc(this.w),this}negate(){return this.x=-this.x,this.y=-this.y,this.z=-this.z,this.w=-this.w,this}dot(t){return this.x*t.x+this.y*t.y+this.z*t.z+this.w*t.w}lengthSq(){return this.x*this.x+this.y*this.y+this.z*this.z+this.w*this.w}length(){return Math.sqrt(this.x*this.x+this.y*this.y+this.z*this.z+this.w*this.w)}manhattanLength(){return Math.abs(this.x)+Math.abs(this.y)+Math.abs(this.z)+Math.abs(this.w)}normalize(){return this.divideScalar(this.length()||1)}setLength(t){return this.normalize().multiplyScalar(t)}lerp(t,e){return this.x+=(t.x-this.x)*e,this.y+=(t.y-this.y)*e,this.z+=(t.z-this.z)*e,this.w+=(t.w-this.w)*e,this}lerpVectors(t,e,n){return this.x=t.x+(e.x-t.x)*n,this.y=t.y+(e.y-t.y)*n,this.z=t.z+(e.z-t.z)*n,this.w=t.w+(e.w-t.w)*n,this}equals(t){return t.x===this.x&&t.y===this.y&&t.z===this.z&&t.w===this.w}fromArray(t,e=0){return this.x=t[e],this.y=t[e+1],this.z=t[e+2],this.w=t[e+3],this}toArray(t=[],e=0){return t[e]=this.x,t[e+1]=this.y,t[e+2]=this.z,t[e+3]=this.w,t}fromBufferAttribute(t,e){return this.x=t.getX(e),this.y=t.getY(e),this.z=t.getZ(e),this.w=t.getW(e),this}random(){return this.x=Math.random(),this.y=Math.random(),this.z=Math.random(),this.w=Math.random(),this}*[Symbol.iterator](){yield this.x,yield this.y,yield this.z,yield this.w}};Xc.prototype.isVector4=!0;var Se=Xc,ja=class extends Vn{constructor(t=1,e=1,n={}){super(),n=Object.assign({generateMipmaps:!1,internalFormat:null,minFilter:Ge,depthBuffer:!0,stencilBuffer:!1,resolveColorBuffer:!0,resolveDepthBuffer:!0,resolveStencilBuffer:!0,storeMultisampledColorBuffer:!0,storeMultisampledDepthBuffer:!0,storeMultisampledStencilBuffer:!0,depthTexture:null,samples:0,count:1,depth:1,multiview:!1,useArrayDepthTexture:!1},n),this.isRenderTarget=!0,this.width=t,this.height=e,this.depth=n.depth,this.scissor=new Se(0,0,t,e),this.scissorTest=!1,this.viewport=new Se(0,0,t,e),this.textures=[];let i={width:t,height:e,depth:n.depth},r=new nn(i),a=n.count;for(let o=0;o<a;o++)this.textures[o]=r.clone(),this.textures[o].isRenderTargetTexture=!0,this.textures[o].renderTarget=this;this._setTextureOptions(n),this.depthBuffer=n.depthBuffer,this.stencilBuffer=n.stencilBuffer,this.resolveColorBuffer=n.resolveColorBuffer,this.resolveDepthBuffer=n.resolveDepthBuffer,this.resolveStencilBuffer=n.resolveStencilBuffer,this.storeMultisampledColorBuffer=n.storeMultisampledColorBuffer,this.storeMultisampledDepthBuffer=n.storeMultisampledDepthBuffer,this.storeMultisampledStencilBuffer=n.storeMultisampledStencilBuffer,this._depthTexture=null,this.depthTexture=n.depthTexture,this.samples=n.samples,this.multiview=n.multiview,this.useArrayDepthTexture=n.useArrayDepthTexture}_setTextureOptions(t={}){let e={minFilter:Ge,generateMipmaps:!1,flipY:!1,internalFormat:null};t.mapping!==void 0&&(e.mapping=t.mapping),t.wrapS!==void 0&&(e.wrapS=t.wrapS),t.wrapT!==void 0&&(e.wrapT=t.wrapT),t.wrapR!==void 0&&(e.wrapR=t.wrapR),t.magFilter!==void 0&&(e.magFilter=t.magFilter),t.minFilter!==void 0&&(e.minFilter=t.minFilter),t.format!==void 0&&(e.format=t.format),t.type!==void 0&&(e.type=t.type),t.anisotropy!==void 0&&(e.anisotropy=t.anisotropy),t.colorSpace!==void 0&&(e.colorSpace=t.colorSpace),t.flipY!==void 0&&(e.flipY=t.flipY),t.generateMipmaps!==void 0&&(e.generateMipmaps=t.generateMipmaps),t.internalFormat!==void 0&&(e.internalFormat=t.internalFormat);for(let n=0;n<this.textures.length;n++)this.textures[n].setValues(e)}get texture(){return this.textures[0]}set texture(t){this.textures[0]=t}set depthTexture(t){this._depthTexture!==null&&this._depthTexture.renderTarget===this&&(this._depthTexture.renderTarget=null),t!==null&&t.renderTarget===null&&(t.renderTarget=this),this._depthTexture=t}get depthTexture(){return this._depthTexture}setSize(t,e,n=1){if(this.width!==t||this.height!==e||this.depth!==n){this.width=t,this.height=e,this.depth=n;for(let i=0,r=this.textures.length;i<r;i++)this.textures[i].image.width=t,this.textures[i].image.height=e,this.textures[i].image.depth=n,this.textures[i].isData3DTexture!==!0&&(this.textures[i].isArrayTexture=this.textures[i].image.depth>1);this.dispose()}this.viewport.set(0,0,t,e),this.scissor.set(0,0,t,e)}clone(){return new this.constructor().copy(this)}copy(t){this.width=t.width,this.height=t.height,this.depth=t.depth,this.scissor.copy(t.scissor),this.scissorTest=t.scissorTest,this.viewport.copy(t.viewport),this.textures.length=0;for(let e=0,n=t.textures.length;e<n;e++){this.textures[e]=t.textures[e].clone(),this.textures[e].isRenderTargetTexture=!0,this.textures[e].renderTarget=this;let i=Object.assign({},t.textures[e].image);this.textures[e].source=new Ts(i)}if(this.depthBuffer=t.depthBuffer,this.stencilBuffer=t.stencilBuffer,this.resolveColorBuffer=t.resolveColorBuffer,this.resolveDepthBuffer=t.resolveDepthBuffer,this.resolveStencilBuffer=t.resolveStencilBuffer,this.storeMultisampledColorBuffer=t.storeMultisampledColorBuffer,this.storeMultisampledDepthBuffer=t.storeMultisampledDepthBuffer,this.storeMultisampledStencilBuffer=t.storeMultisampledStencilBuffer,t.depthTexture!==null)if(t.depthTexture.renderTarget===t){let e=t.depthTexture.clone();e.renderTarget=null,this.depthTexture=e}else this.depthTexture=t.depthTexture;return this.samples=t.samples,this.multiview=t.multiview,this.useArrayDepthTexture=t.useArrayDepthTexture,this}dispose(){this.dispatchEvent({type:"dispose"})}},Te=class extends ja{constructor(t=1,e=1,n={}){super(t,e,n),this.isWebGLRenderTarget=!0}},xr=class extends nn{constructor(t=null,e=1,n=1,i=1){super(null),this.isDataArrayTexture=!0,this.image={data:t,width:e,height:n,depth:i},this.magFilter=ze,this.minFilter=ze,this.wrapR=On,this.generateMipmaps=!1,this.flipY=!1,this.unpackAlignment=1,this.layerUpdates=new Set}copy(t){return super.copy(t),this.wrapR=t.wrapR,this}addLayerUpdate(t){this.layerUpdates.add(t)}clearLayerUpdates(){this.layerUpdates.clear()}};var to=class extends nn{constructor(t=null,e=1,n=1,i=1){super(null),this.isData3DTexture=!0,this.image={data:t,width:e,height:n,depth:i},this.magFilter=ze,this.minFilter=ze,this.wrapR=On,this.generateMipmaps=!1,this.flipY=!1,this.unpackAlignment=1}copy(t){return super.copy(t),this.wrapR=t.wrapR,this}};var bo=class bo{constructor(t,e,n,i,r,a,o,l,c,h,u,d,f,p,y,g){this.elements=[1,0,0,0,0,1,0,0,0,0,1,0,0,0,0,1],t!==void 0&&this.set(t,e,n,i,r,a,o,l,c,h,u,d,f,p,y,g)}set(t,e,n,i,r,a,o,l,c,h,u,d,f,p,y,g){let m=this.elements;return m[0]=t,m[4]=e,m[8]=n,m[12]=i,m[1]=r,m[5]=a,m[9]=o,m[13]=l,m[2]=c,m[6]=h,m[10]=u,m[14]=d,m[3]=f,m[7]=p,m[11]=y,m[15]=g,this}identity(){return this.set(1,0,0,0,0,1,0,0,0,0,1,0,0,0,0,1),this}clone(){return new bo().fromArray(this.elements)}copy(t){let e=this.elements,n=t.elements;return e[0]=n[0],e[1]=n[1],e[2]=n[2],e[3]=n[3],e[4]=n[4],e[5]=n[5],e[6]=n[6],e[7]=n[7],e[8]=n[8],e[9]=n[9],e[10]=n[10],e[11]=n[11],e[12]=n[12],e[13]=n[13],e[14]=n[14],e[15]=n[15],this}copyPosition(t){let e=this.elements,n=t.elements;return e[12]=n[12],e[13]=n[13],e[14]=n[14],this}setFromMatrix3(t){let e=t.elements;return this.set(e[0],e[3],e[6],0,e[1],e[4],e[7],0,e[2],e[5],e[8],0,0,0,0,1),this}extractBasis(t,e,n){return this.determinantAffine()===0?(t.set(1,0,0),e.set(0,1,0),n.set(0,0,1),this):(t.setFromMatrixColumn(this,0),e.setFromMatrixColumn(this,1),n.setFromMatrixColumn(this,2),this)}makeBasis(t,e,n){return this.set(t.x,e.x,n.x,0,t.y,e.y,n.y,0,t.z,e.z,n.z,0,0,0,0,1),this}extractRotation(t){if(t.determinantAffine()===0)return this.identity();let e=this.elements,n=t.elements,i=1/rs.setFromMatrixColumn(t,0).length(),r=1/rs.setFromMatrixColumn(t,1).length(),a=1/rs.setFromMatrixColumn(t,2).length();return e[0]=n[0]*i,e[1]=n[1]*i,e[2]=n[2]*i,e[3]=0,e[4]=n[4]*r,e[5]=n[5]*r,e[6]=n[6]*r,e[7]=0,e[8]=n[8]*a,e[9]=n[9]*a,e[10]=n[10]*a,e[11]=0,e[12]=0,e[13]=0,e[14]=0,e[15]=1,this}makeRotationFromEuler(t){let e=this.elements,n=t.x,i=t.y,r=t.z,a=Math.cos(n),o=Math.sin(n),l=Math.cos(i),c=Math.sin(i),h=Math.cos(r),u=Math.sin(r);if(t.order==="XYZ"){let d=a*h,f=a*u,p=o*h,y=o*u;e[0]=l*h,e[4]=-l*u,e[8]=c,e[1]=f+p*c,e[5]=d-y*c,e[9]=-o*l,e[2]=y-d*c,e[6]=p+f*c,e[10]=a*l}else if(t.order==="YXZ"){let d=l*h,f=l*u,p=c*h,y=c*u;e[0]=d+y*o,e[4]=p*o-f,e[8]=a*c,e[1]=a*u,e[5]=a*h,e[9]=-o,e[2]=f*o-p,e[6]=y+d*o,e[10]=a*l}else if(t.order==="ZXY"){let d=l*h,f=l*u,p=c*h,y=c*u;e[0]=d-y*o,e[4]=-a*u,e[8]=p+f*o,e[1]=f+p*o,e[5]=a*h,e[9]=y-d*o,e[2]=-a*c,e[6]=o,e[10]=a*l}else if(t.order==="ZYX"){let d=a*h,f=a*u,p=o*h,y=o*u;e[0]=l*h,e[4]=p*c-f,e[8]=d*c+y,e[1]=l*u,e[5]=y*c+d,e[9]=f*c-p,e[2]=-c,e[6]=o*l,e[10]=a*l}else if(t.order==="YZX"){let d=a*l,f=a*c,p=o*l,y=o*c;e[0]=l*h,e[4]=y-d*u,e[8]=p*u+f,e[1]=u,e[5]=a*h,e[9]=-o*h,e[2]=-c*h,e[6]=f*u+p,e[10]=d-y*u}else if(t.order==="XZY"){let d=a*l,f=a*c,p=o*l,y=o*c;e[0]=l*h,e[4]=-u,e[8]=c*h,e[1]=d*u+y,e[5]=a*h,e[9]=f*u-p,e[2]=p*u-f,e[6]=o*h,e[10]=y*u+d}return e[3]=0,e[7]=0,e[11]=0,e[12]=0,e[13]=0,e[14]=0,e[15]=1,this}makeRotationFromQuaternion(t){return this.compose(lf,t,cf)}lookAt(t,e,n){let i=this.elements;return on.subVectors(t,e),on.lengthSq()===0&&(on.z=1),on.normalize(),ui.crossVectors(n,on),ui.lengthSq()===0&&(Math.abs(n.z)===1?on.x+=1e-4:on.z+=1e-4,on.normalize(),ui.crossVectors(n,on)),ui.normalize(),ga.crossVectors(on,ui),i[0]=ui.x,i[4]=ga.x,i[8]=on.x,i[1]=ui.y,i[5]=ga.y,i[9]=on.y,i[2]=ui.z,i[6]=ga.z,i[10]=on.z,this}multiply(t){return this.multiplyMatrices(this,t)}premultiply(t){return this.multiplyMatrices(t,this)}multiplyMatrices(t,e){let n=t.elements,i=e.elements,r=this.elements,a=n[0],o=n[4],l=n[8],c=n[12],h=n[1],u=n[5],d=n[9],f=n[13],p=n[2],y=n[6],g=n[10],m=n[14],S=n[3],T=n[7],v=n[11],b=n[15],w=i[0],P=i[4],_=i[8],E=i[12],C=i[1],I=i[5],U=i[9],k=i[13],L=i[2],B=i[6],W=i[10],X=i[14],st=i[3],Z=i[7],Q=i[11],it=i[15];return r[0]=a*w+o*C+l*L+c*st,r[4]=a*P+o*I+l*B+c*Z,r[8]=a*_+o*U+l*W+c*Q,r[12]=a*E+o*k+l*X+c*it,r[1]=h*w+u*C+d*L+f*st,r[5]=h*P+u*I+d*B+f*Z,r[9]=h*_+u*U+d*W+f*Q,r[13]=h*E+u*k+d*X+f*it,r[2]=p*w+y*C+g*L+m*st,r[6]=p*P+y*I+g*B+m*Z,r[10]=p*_+y*U+g*W+m*Q,r[14]=p*E+y*k+g*X+m*it,r[3]=S*w+T*C+v*L+b*st,r[7]=S*P+T*I+v*B+b*Z,r[11]=S*_+T*U+v*W+b*Q,r[15]=S*E+T*k+v*X+b*it,this}multiplyScalar(t){let e=this.elements;return e[0]*=t,e[4]*=t,e[8]*=t,e[12]*=t,e[1]*=t,e[5]*=t,e[9]*=t,e[13]*=t,e[2]*=t,e[6]*=t,e[10]*=t,e[14]*=t,e[3]*=t,e[7]*=t,e[11]*=t,e[15]*=t,this}determinant(){let t=this.elements,e=t[0],n=t[4],i=t[8],r=t[12],a=t[1],o=t[5],l=t[9],c=t[13],h=t[2],u=t[6],d=t[10],f=t[14],p=t[3],y=t[7],g=t[11],m=t[15],S=l*f-c*d,T=o*f-c*u,v=o*d-l*u,b=a*f-c*h,w=a*d-l*h,P=a*u-o*h;return e*(y*S-g*T+m*v)-n*(p*S-g*b+m*w)+i*(p*T-y*b+m*P)-r*(p*v-y*w+g*P)}determinantAffine(){let t=this.elements,e=t[0],n=t[4],i=t[8],r=t[1],a=t[5],o=t[9],l=t[2],c=t[6],h=t[10];return e*(a*h-o*c)-n*(r*h-o*l)+i*(r*c-a*l)}transpose(){let t=this.elements,e;return e=t[1],t[1]=t[4],t[4]=e,e=t[2],t[2]=t[8],t[8]=e,e=t[6],t[6]=t[9],t[9]=e,e=t[3],t[3]=t[12],t[12]=e,e=t[7],t[7]=t[13],t[13]=e,e=t[11],t[11]=t[14],t[14]=e,this}setPosition(t,e,n){let i=this.elements;return t.isVector3?(i[12]=t.x,i[13]=t.y,i[14]=t.z):(i[12]=t,i[13]=e,i[14]=n),this}invert(){let t=this.elements,e=t[0],n=t[1],i=t[2],r=t[3],a=t[4],o=t[5],l=t[6],c=t[7],h=t[8],u=t[9],d=t[10],f=t[11],p=t[12],y=t[13],g=t[14],m=t[15],S=e*o-n*a,T=e*l-i*a,v=e*c-r*a,b=n*l-i*o,w=n*c-r*o,P=i*c-r*l,_=h*y-u*p,E=h*g-d*p,C=h*m-f*p,I=u*g-d*y,U=u*m-f*y,k=d*m-f*g,L=S*k-T*U+v*I+b*C-w*E+P*_;if(L===0)return this.set(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);let B=1/L;return t[0]=(o*k-l*U+c*I)*B,t[1]=(i*U-n*k-r*I)*B,t[2]=(y*P-g*w+m*b)*B,t[3]=(d*w-u*P-f*b)*B,t[4]=(l*C-a*k-c*E)*B,t[5]=(e*k-i*C+r*E)*B,t[6]=(g*v-p*P-m*T)*B,t[7]=(h*P-d*v+f*T)*B,t[8]=(a*U-o*C+c*_)*B,t[9]=(n*C-e*U-r*_)*B,t[10]=(p*w-y*v+m*S)*B,t[11]=(u*v-h*w-f*S)*B,t[12]=(o*E-a*I-l*_)*B,t[13]=(e*I-n*E+i*_)*B,t[14]=(y*T-p*b-g*S)*B,t[15]=(h*b-u*T+d*S)*B,this}scale(t){let e=this.elements,n=t.x,i=t.y,r=t.z;return e[0]*=n,e[4]*=i,e[8]*=r,e[1]*=n,e[5]*=i,e[9]*=r,e[2]*=n,e[6]*=i,e[10]*=r,e[3]*=n,e[7]*=i,e[11]*=r,this}getMaxScaleOnAxis(){let t=this.elements,e=t[0]*t[0]+t[1]*t[1]+t[2]*t[2],n=t[4]*t[4]+t[5]*t[5]+t[6]*t[6],i=t[8]*t[8]+t[9]*t[9]+t[10]*t[10];return Math.sqrt(Math.max(e,n,i))}makeTranslation(t,e,n){return t.isVector3?this.set(1,0,0,t.x,0,1,0,t.y,0,0,1,t.z,0,0,0,1):this.set(1,0,0,t,0,1,0,e,0,0,1,n,0,0,0,1),this}makeRotationX(t){let e=Math.cos(t),n=Math.sin(t);return this.set(1,0,0,0,0,e,-n,0,0,n,e,0,0,0,0,1),this}makeRotationY(t){let e=Math.cos(t),n=Math.sin(t);return this.set(e,0,n,0,0,1,0,0,-n,0,e,0,0,0,0,1),this}makeRotationZ(t){let e=Math.cos(t),n=Math.sin(t);return this.set(e,-n,0,0,n,e,0,0,0,0,1,0,0,0,0,1),this}makeRotationAxis(t,e){let n=Math.cos(e),i=Math.sin(e),r=1-n,a=t.x,o=t.y,l=t.z,c=r*a,h=r*o;return this.set(c*a+n,c*o-i*l,c*l+i*o,0,c*o+i*l,h*o+n,h*l-i*a,0,c*l-i*o,h*l+i*a,r*l*l+n,0,0,0,0,1),this}makeScale(t,e,n){return this.set(t,0,0,0,0,e,0,0,0,0,n,0,0,0,0,1),this}makeShear(t,e,n,i,r,a){return this.set(1,n,r,0,t,1,a,0,e,i,1,0,0,0,0,1),this}compose(t,e,n){let i=this.elements,r=e._x,a=e._y,o=e._z,l=e._w,c=r+r,h=a+a,u=o+o,d=r*c,f=r*h,p=r*u,y=a*h,g=a*u,m=o*u,S=l*c,T=l*h,v=l*u,b=n.x,w=n.y,P=n.z;return i[0]=(1-(y+m))*b,i[1]=(f+v)*b,i[2]=(p-T)*b,i[3]=0,i[4]=(f-v)*w,i[5]=(1-(d+m))*w,i[6]=(g+S)*w,i[7]=0,i[8]=(p+T)*P,i[9]=(g-S)*P,i[10]=(1-(d+y))*P,i[11]=0,i[12]=t.x,i[13]=t.y,i[14]=t.z,i[15]=1,this}decompose(t,e,n){let i=this.elements;t.x=i[12],t.y=i[13],t.z=i[14];let r=this.determinantAffine();if(r===0)return n.set(1,1,1),e.identity(),this;let a=rs.set(i[0],i[1],i[2]).length(),o=rs.set(i[4],i[5],i[6]).length(),l=rs.set(i[8],i[9],i[10]).length();r<0&&(a=-a),wn.copy(this);let c=1/a,h=1/o,u=1/l;return wn.elements[0]*=c,wn.elements[1]*=c,wn.elements[2]*=c,wn.elements[4]*=h,wn.elements[5]*=h,wn.elements[6]*=h,wn.elements[8]*=u,wn.elements[9]*=u,wn.elements[10]*=u,e.setFromRotationMatrix(wn),n.x=a,n.y=o,n.z=l,this}makePerspective(t,e,n,i,r,a,o=Cn,l=!1){let c=this.elements,h=2*r/(e-t),u=2*r/(n-i),d=(e+t)/(e-t),f=(n+i)/(n-i),p,y;if(l)p=r/(a-r),y=a*r/(a-r);else if(o===Cn)p=-(a+r)/(a-r),y=-2*a*r/(a-r);else if(o===Es)p=-a/(a-r),y=-a*r/(a-r);else throw new Error("THREE.Matrix4.makePerspective(): Invalid coordinate system: "+o);return c[0]=h,c[4]=0,c[8]=d,c[12]=0,c[1]=0,c[5]=u,c[9]=f,c[13]=0,c[2]=0,c[6]=0,c[10]=p,c[14]=y,c[3]=0,c[7]=0,c[11]=-1,c[15]=0,this}makeOrthographic(t,e,n,i,r,a,o=Cn,l=!1){let c=this.elements,h=2/(e-t),u=2/(n-i),d=-(e+t)/(e-t),f=-(n+i)/(n-i),p,y;if(l)p=1/(a-r),y=a/(a-r);else if(o===Cn)p=-2/(a-r),y=-(a+r)/(a-r);else if(o===Es)p=-1/(a-r),y=-r/(a-r);else throw new Error("THREE.Matrix4.makeOrthographic(): Invalid coordinate system: "+o);return c[0]=h,c[4]=0,c[8]=0,c[12]=d,c[1]=0,c[5]=u,c[9]=0,c[13]=f,c[2]=0,c[6]=0,c[10]=p,c[14]=y,c[3]=0,c[7]=0,c[11]=0,c[15]=1,this}equals(t){let e=this.elements,n=t.elements;for(let i=0;i<16;i++)if(e[i]!==n[i])return!1;return!0}fromArray(t,e=0){for(let n=0;n<16;n++)this.elements[n]=t[n+e];return this}toArray(t=[],e=0){let n=this.elements;return t[e]=n[0],t[e+1]=n[1],t[e+2]=n[2],t[e+3]=n[3],t[e+4]=n[4],t[e+5]=n[5],t[e+6]=n[6],t[e+7]=n[7],t[e+8]=n[8],t[e+9]=n[9],t[e+10]=n[10],t[e+11]=n[11],t[e+12]=n[12],t[e+13]=n[13],t[e+14]=n[14],t[e+15]=n[15],t}};bo.prototype.isMatrix4=!0;var fe=bo,rs=new D,wn=new fe,lf=new D(0,0,0),cf=new D(1,1,1),ui=new D,ga=new D,on=new D,Ih=new fe,Lh=new pn,mn=class s{constructor(t=0,e=0,n=0,i=s.DEFAULT_ORDER){this.isEuler=!0,this._x=t,this._y=e,this._z=n,this._order=i}get x(){return this._x}set x(t){this._x=t,this._onChangeCallback()}get y(){return this._y}set y(t){this._y=t,this._onChangeCallback()}get z(){return this._z}set z(t){this._z=t,this._onChangeCallback()}get order(){return this._order}set order(t){this._order=t,this._onChangeCallback()}set(t,e,n,i=this._order){return this._x=t,this._y=e,this._z=n,this._order=i,this._onChangeCallback(),this}clone(){return new this.constructor(this._x,this._y,this._z,this._order)}copy(t){return this._x=t._x,this._y=t._y,this._z=t._z,this._order=t._order,this._onChangeCallback(),this}setFromRotationMatrix(t,e=this._order,n=!0){let i=t.elements,r=i[0],a=i[4],o=i[8],l=i[1],c=i[5],h=i[9],u=i[2],d=i[6],f=i[10];switch(e){case"XYZ":this._y=Math.asin(se(o,-1,1)),Math.abs(o)<.9999999?(this._x=Math.atan2(-h,f),this._z=Math.atan2(-a,r)):(this._x=Math.atan2(d,c),this._z=0);break;case"YXZ":this._x=Math.asin(-se(h,-1,1)),Math.abs(h)<.9999999?(this._y=Math.atan2(o,f),this._z=Math.atan2(l,c)):(this._y=Math.atan2(-u,r),this._z=0);break;case"ZXY":this._x=Math.asin(se(d,-1,1)),Math.abs(d)<.9999999?(this._y=Math.atan2(-u,f),this._z=Math.atan2(-a,c)):(this._y=0,this._z=Math.atan2(l,r));break;case"ZYX":this._y=Math.asin(-se(u,-1,1)),Math.abs(u)<.9999999?(this._x=Math.atan2(d,f),this._z=Math.atan2(l,r)):(this._x=0,this._z=Math.atan2(-a,c));break;case"YZX":this._z=Math.asin(se(l,-1,1)),Math.abs(l)<.9999999?(this._x=Math.atan2(-h,c),this._y=Math.atan2(-u,r)):(this._x=0,this._y=Math.atan2(o,f));break;case"XZY":this._z=Math.asin(-se(a,-1,1)),Math.abs(a)<.9999999?(this._x=Math.atan2(d,c),this._y=Math.atan2(o,r)):(this._x=Math.atan2(-h,f),this._y=0);break;default:qt("Euler: .setFromRotationMatrix() encountered an unknown order: "+e)}return this._order=e,n===!0&&this._onChangeCallback(),this}setFromQuaternion(t,e,n){return Ih.makeRotationFromQuaternion(t),this.setFromRotationMatrix(Ih,e,n)}setFromVector3(t,e=this._order){return this.set(t.x,t.y,t.z,e)}reorder(t){return Lh.setFromEuler(this),this.setFromQuaternion(Lh,t)}equals(t){return t._x===this._x&&t._y===this._y&&t._z===this._z&&t._order===this._order}fromArray(t){return this._x=t[0],this._y=t[1],this._z=t[2],t[3]!==void 0&&(this._order=t[3]),this._onChangeCallback(),this}toArray(t=[],e=0){return t[e]=this._x,t[e+1]=this._y,t[e+2]=this._z,t[e+3]=this._order,t}_onChange(t){return this._onChangeCallback=t,this}_onChangeCallback(){}*[Symbol.iterator](){yield this._x,yield this._y,yield this._z,yield this._order}};mn.DEFAULT_ORDER="XYZ";var _r=class{constructor(){this.mask=1}set(t){this.mask=(1<<t|0)>>>0}enable(t){this.mask|=1<<t|0}enableAll(){this.mask=-1}toggle(t){this.mask^=1<<t|0}disable(t){this.mask&=~(1<<t|0)}disableAll(){this.mask=0}test(t){return(this.mask&t.mask)!==0}isEnabled(t){return(this.mask&(1<<t|0))!==0}},hf=0,Dh=new D,as=new pn,Zn=new fe,xa=new D,js=new D,uf=new D,df=new pn,Uh=new D(1,0,0),Nh=new D(0,1,0),Fh=new D(0,0,1),Bh={type:"added"},ff={type:"removed"},os={type:"childadded",child:null},Wl={type:"childremoved",child:null},Re=class s extends Vn{constructor(){super(),this.isObject3D=!0,Object.defineProperty(this,"id",{value:hf++}),this.uuid=ti(),this.name="",this.type="Object3D",this.parent=null,this.children=[],this.up=s.DEFAULT_UP.clone();let t=new D,e=new mn,n=new pn,i=new D(1,1,1);function r(){n.setFromEuler(e,!1)}function a(){e.setFromQuaternion(n,void 0,!1)}e._onChange(r),n._onChange(a),Object.defineProperties(this,{position:{configurable:!0,enumerable:!0,value:t},rotation:{configurable:!0,enumerable:!0,value:e},quaternion:{configurable:!0,enumerable:!0,value:n},scale:{configurable:!0,enumerable:!0,value:i},modelViewMatrix:{value:new fe},normalMatrix:{value:new $t}}),this.matrix=new fe,this.matrixWorld=new fe,this.matrixAutoUpdate=s.DEFAULT_MATRIX_AUTO_UPDATE,this.matrixWorldAutoUpdate=s.DEFAULT_MATRIX_WORLD_AUTO_UPDATE,this.matrixWorldNeedsUpdate=!1,this.layers=new _r,this.visible=!0,this.castShadow=!1,this.receiveShadow=!1,this.frustumCulled=!0,this.renderOrder=0,this.animations=[],this.customDepthMaterial=void 0,this.customDistanceMaterial=void 0,this.static=!1,this.userData={},this.pivot=null}onBeforeShadow(){}onAfterShadow(){}onBeforeRender(){}onAfterRender(){}applyMatrix4(t){this.matrixAutoUpdate&&this.updateMatrix(),this.matrix.premultiply(t),this.matrix.decompose(this.position,this.quaternion,this.scale)}applyQuaternion(t){return this.quaternion.premultiply(t),this}setRotationFromAxisAngle(t,e){this.quaternion.setFromAxisAngle(t,e)}setRotationFromEuler(t){this.quaternion.setFromEuler(t,!0)}setRotationFromMatrix(t){this.quaternion.setFromRotationMatrix(t)}setRotationFromQuaternion(t){this.quaternion.copy(t)}rotateOnAxis(t,e){return as.setFromAxisAngle(t,e),this.quaternion.multiply(as),this}rotateOnWorldAxis(t,e){return as.setFromAxisAngle(t,e),this.quaternion.premultiply(as),this}rotateX(t){return this.rotateOnAxis(Uh,t)}rotateY(t){return this.rotateOnAxis(Nh,t)}rotateZ(t){return this.rotateOnAxis(Fh,t)}translateOnAxis(t,e){return Dh.copy(t).applyQuaternion(this.quaternion),this.position.add(Dh.multiplyScalar(e)),this}translateX(t){return this.translateOnAxis(Uh,t)}translateY(t){return this.translateOnAxis(Nh,t)}translateZ(t){return this.translateOnAxis(Fh,t)}localToWorld(t){return this.updateWorldMatrix(!0,!1),t.applyMatrix4(this.matrixWorld)}worldToLocal(t){return this.updateWorldMatrix(!0,!1),t.applyMatrix4(Zn.copy(this.matrixWorld).invert())}lookAt(t,e,n){t.isVector3?xa.copy(t):xa.set(t,e,n);let i=this.parent;this.updateWorldMatrix(!0,!1),js.setFromMatrixPosition(this.matrixWorld),this.isCamera||this.isLight?Zn.lookAt(js,xa,this.up):Zn.lookAt(xa,js,this.up),this.quaternion.setFromRotationMatrix(Zn),i&&(Zn.extractRotation(i.matrixWorld),as.setFromRotationMatrix(Zn),this.quaternion.premultiply(as.invert()))}add(t){if(arguments.length>1){for(let e=0;e<arguments.length;e++)this.add(arguments[e]);return this}return t===this?(Xt("Object3D.add: object can't be added as a child of itself.",t),this):(t&&t.isObject3D?(t.removeFromParent(),t.parent=this,this.children.push(t),t.dispatchEvent(Bh),os.child=t,this.dispatchEvent(os),os.child=null):Xt("Object3D.add: object not an instance of THREE.Object3D.",t),this)}remove(t){if(arguments.length>1){for(let n=0;n<arguments.length;n++)this.remove(arguments[n]);return this}let e=this.children.indexOf(t);return e!==-1&&(t.parent=null,this.children.splice(e,1),t.dispatchEvent(ff),Wl.child=t,this.dispatchEvent(Wl),Wl.child=null),this}removeFromParent(){let t=this.parent;return t!==null&&t.remove(this),this}clear(){return this.remove(...this.children)}attach(t){return this.updateWorldMatrix(!0,!1),Zn.copy(this.matrixWorld).invert(),t.parent!==null&&(t.parent.updateWorldMatrix(!0,!1),Zn.multiply(t.parent.matrixWorld)),t.applyMatrix4(Zn),t.removeFromParent(),t.parent=this,this.children.push(t),t.updateWorldMatrix(!1,!0),t.dispatchEvent(Bh),os.child=t,this.dispatchEvent(os),os.child=null,this}getObjectById(t){return this.getObjectByProperty("id",t)}getObjectByName(t){return this.getObjectByProperty("name",t)}getObjectByProperty(t,e){if(this[t]===e)return this;for(let n=0,i=this.children.length;n<i;n++){let a=this.children[n].getObjectByProperty(t,e);if(a!==void 0)return a}}getObjectsByProperty(t,e,n=[]){this[t]===e&&n.push(this);let i=this.children;for(let r=0,a=i.length;r<a;r++)i[r].getObjectsByProperty(t,e,n);return n}getWorldPosition(t){return this.updateWorldMatrix(!0,!1),t.setFromMatrixPosition(this.matrixWorld)}getWorldQuaternion(t){return this.updateWorldMatrix(!0,!1),this.matrixWorld.decompose(js,t,uf),t}getWorldScale(t){return this.updateWorldMatrix(!0,!1),this.matrixWorld.decompose(js,df,t),t}getWorldDirection(t){this.updateWorldMatrix(!0,!1);let e=this.matrixWorld.elements;return t.set(e[8],e[9],e[10]).normalize()}raycast(){}intersectsFrustum(){}traverse(t){t(this);let e=this.children;for(let n=0,i=e.length;n<i;n++)e[n].traverse(t)}traverseVisible(t){if(this.visible===!1)return;t(this);let e=this.children;for(let n=0,i=e.length;n<i;n++)e[n].traverseVisible(t)}traverseAncestors(t){let e=this.parent;e!==null&&(t(e),e.traverseAncestors(t))}updateMatrix(){this.matrix.compose(this.position,this.quaternion,this.scale);let t=this.pivot;if(t!==null){let e=t.x,n=t.y,i=t.z,r=this.matrix.elements;r[12]+=e-r[0]*e-r[4]*n-r[8]*i,r[13]+=n-r[1]*e-r[5]*n-r[9]*i,r[14]+=i-r[2]*e-r[6]*n-r[10]*i}this.matrixWorldNeedsUpdate=!0}updateMatrixWorld(t){this.matrixAutoUpdate&&this.updateMatrix(),(this.matrixWorldNeedsUpdate||t)&&(this.matrixWorldAutoUpdate===!0&&(this.parent===null?this.matrixWorld.copy(this.matrix):this.matrixWorld.multiplyMatrices(this.parent.matrixWorld,this.matrix)),this.matrixWorldNeedsUpdate=!1,t=!0);let e=this.children;for(let n=0,i=e.length;n<i;n++)e[n].updateMatrixWorld(t)}updateWorldMatrix(t,e,n=!1){let i=this.parent;if(t===!0&&i!==null&&i.updateWorldMatrix(!0,!1),this.matrixAutoUpdate&&this.updateMatrix(),(this.matrixWorldNeedsUpdate||n)&&(this.matrixWorldAutoUpdate===!0&&(this.parent===null?this.matrixWorld.copy(this.matrix):this.matrixWorld.multiplyMatrices(this.parent.matrixWorld,this.matrix)),this.matrixWorldNeedsUpdate=!1,n=!0),e===!0){let r=this.children;for(let a=0,o=r.length;a<o;a++)r[a].updateWorldMatrix(!1,!0,n)}}toJSON(t){let e=t===void 0||typeof t=="string",n={};e&&(t={geometries:{},materials:{},textures:{},images:{},shapes:{},skeletons:{},animations:{},nodes:{}},n.metadata={version:4.7,type:"Object",generator:"Object3D.toJSON"});let i={};i.uuid=this.uuid,i.type=this.type,i.name=this.name,i.castShadow=this.castShadow,i.receiveShadow=this.receiveShadow,i.visible=this.visible,i.frustumCulled=this.frustumCulled,i.renderOrder=this.renderOrder,i.static=this.static,i.matrixAutoUpdate=this.matrixAutoUpdate,Object.keys(this.userData).length>0&&(i.userData=this.userData),i.layers=this.layers.mask,i.matrix=this.matrix.toArray(),i.up=this.up.toArray(),this.pivot!==null&&(i.pivot=this.pivot.toArray()),this.morphTargetDictionary!==void 0&&(i.morphTargetDictionary=Object.assign({},this.morphTargetDictionary)),this.morphTargetInfluences!==void 0&&(i.morphTargetInfluences=this.morphTargetInfluences.slice()),this.isInstancedMesh&&(i.type="InstancedMesh",i.count=this.count,i.instanceMatrix=this.instanceMatrix.toJSON(),this.instanceColor!==null&&(i.instanceColor=this.instanceColor.toJSON())),this.isBatchedMesh&&(i.type="BatchedMesh",i.perObjectFrustumCulled=this.perObjectFrustumCulled,i.sortObjects=this.sortObjects,i.drawRanges=this._drawRanges,i.reservedRanges=this._reservedRanges,i.geometryInfo=this._geometryInfo.map(o=>({...o,boundingBox:o.boundingBox?o.boundingBox.toJSON():void 0,boundingSphere:o.boundingSphere?o.boundingSphere.toJSON():void 0})),i.instanceInfo=this._instanceInfo.map(o=>({...o})),i.availableInstanceIds=this._availableInstanceIds.slice(),i.availableGeometryIds=this._availableGeometryIds.slice(),i.nextIndexStart=this._nextIndexStart,i.nextVertexStart=this._nextVertexStart,i.geometryCount=this._geometryCount,i.maxInstanceCount=this._maxInstanceCount,i.maxVertexCount=this._maxVertexCount,i.maxIndexCount=this._maxIndexCount,i.geometryInitialized=this._geometryInitialized,i.matricesTexture=this._matricesTexture.toJSON(t),i.indirectTexture=this._indirectTexture.toJSON(t),this._colorsTexture!==null&&(i.colorsTexture=this._colorsTexture.toJSON(t)),this.boundingSphere!==null&&(i.boundingSphere=this.boundingSphere.toJSON()),this.boundingBox!==null&&(i.boundingBox=this.boundingBox.toJSON()));function r(o,l){return o[l.uuid]===void 0&&(o[l.uuid]=l.toJSON(t)),l.uuid}if(this.isScene)this.background&&(this.background.isColor?i.background=this.background.toJSON():this.background.isTexture&&(i.background=this.background.toJSON(t).uuid)),this.environment&&this.environment.isTexture&&this.environment.isRenderTargetTexture!==!0&&(i.environment=this.environment.toJSON(t).uuid);else if(this.isMesh||this.isLine||this.isPoints){i.geometry=r(t.geometries,this.geometry);let o=this.geometry.parameters;if(o!==void 0&&o.shapes!==void 0){let l=o.shapes;if(Array.isArray(l))for(let c=0,h=l.length;c<h;c++){let u=l[c];r(t.shapes,u)}else r(t.shapes,l)}}if(this.isSkinnedMesh&&(i.bindMode=this.bindMode,i.bindMatrix=this.bindMatrix.toArray(),this.skeleton!==void 0&&(r(t.skeletons,this.skeleton),i.skeleton=this.skeleton.uuid)),this.material!==void 0)if(Array.isArray(this.material)){let o=[];for(let l=0,c=this.material.length;l<c;l++)o.push(r(t.materials,this.material[l]));i.material=o}else i.material=r(t.materials,this.material);if(this.children.length>0){i.children=[];for(let o=0;o<this.children.length;o++)i.children.push(this.children[o].toJSON(t).object)}if(this.animations.length>0){i.animations=[];for(let o=0;o<this.animations.length;o++){let l=this.animations[o];i.animations.push(r(t.animations,l))}}if(e){let o=a(t.geometries),l=a(t.materials),c=a(t.textures),h=a(t.images),u=a(t.shapes),d=a(t.skeletons),f=a(t.animations),p=a(t.nodes);o.length>0&&(n.geometries=o),l.length>0&&(n.materials=l),c.length>0&&(n.textures=c),h.length>0&&(n.images=h),u.length>0&&(n.shapes=u),d.length>0&&(n.skeletons=d),f.length>0&&(n.animations=f),p.length>0&&(n.nodes=p)}return n.object=i,n;function a(o){let l=[];for(let c in o){let h=o[c];delete h.metadata,l.push(h)}return l}}clone(t){return new this.constructor().copy(this,t)}copy(t,e=!0){if(this.name=t.name,this.up.copy(t.up),this.position.copy(t.position),this.rotation.order=t.rotation.order,this.quaternion.copy(t.quaternion),this.scale.copy(t.scale),this.pivot=t.pivot!==null?t.pivot.clone():null,this.matrix.copy(t.matrix),this.matrixWorld.copy(t.matrixWorld),this.matrixAutoUpdate=t.matrixAutoUpdate,this.matrixWorldAutoUpdate=t.matrixWorldAutoUpdate,this.matrixWorldNeedsUpdate=t.matrixWorldNeedsUpdate,this.layers.mask=t.layers.mask,this.visible=t.visible,this.castShadow=t.castShadow,this.receiveShadow=t.receiveShadow,this.frustumCulled=t.frustumCulled,this.renderOrder=t.renderOrder,this.static=t.static,this.animations=t.animations.slice(),this.userData=JSON.parse(JSON.stringify(t.userData)),e===!0)for(let n=0;n<t.children.length;n++){let i=t.children[n];this.add(i.clone())}return this}dispose(){this.dispatchEvent({type:"dispose"})}};Re.DEFAULT_UP=new D(0,1,0);Re.DEFAULT_MATRIX_AUTO_UPDATE=!0;Re.DEFAULT_MATRIX_WORLD_AUTO_UPDATE=!0;var Je=class extends Re{constructor(){super(),this.isGroup=!0,this.type="Group"}},pf={type:"move"},As=class{constructor(){this._targetRay=null,this._grip=null,this._hand=null}getHandSpace(){return this._hand===null&&(this._hand=new Je,this._hand.matrixAutoUpdate=!1,this._hand.visible=!1,this._hand.joints={},this._hand.inputState={pinching:!1}),this._hand}getTargetRaySpace(){return this._targetRay===null&&(this._targetRay=new Je,this._targetRay.matrixAutoUpdate=!1,this._targetRay.visible=!1,this._targetRay.hasLinearVelocity=!1,this._targetRay.linearVelocity=new D,this._targetRay.hasAngularVelocity=!1,this._targetRay.angularVelocity=new D),this._targetRay}getGripSpace(){return this._grip===null&&(this._grip=new Je,this._grip.matrixAutoUpdate=!1,this._grip.visible=!1,this._grip.hasLinearVelocity=!1,this._grip.linearVelocity=new D,this._grip.hasAngularVelocity=!1,this._grip.angularVelocity=new D,this._grip.eventsEnabled=!1),this._grip}dispatchEvent(t){return this._targetRay!==null&&this._targetRay.dispatchEvent(t),this._grip!==null&&this._grip.dispatchEvent(t),this._hand!==null&&this._hand.dispatchEvent(t),this}connect(t){if(t&&t.hand){let e=this._hand;if(e)for(let n of t.hand.values())this._getHandJoint(e,n)}return this.dispatchEvent({type:"connected",data:t}),this}disconnect(t){return this.dispatchEvent({type:"disconnected",data:t}),this._targetRay!==null&&(this._targetRay.visible=!1),this._grip!==null&&(this._grip.visible=!1),this._hand!==null&&(this._hand.visible=!1),this}update(t,e,n){let i=null,r=null,a=null,o=this._targetRay,l=this._grip,c=this._hand;if(t&&e.session.visibilityState!=="visible-blurred"){if(c&&t.hand){a=!0;for(let y of t.hand.values()){let g=e.getJointPose(y,n),m=this._getHandJoint(c,y);g!==null&&(m.matrix.fromArray(g.transform.matrix),m.matrix.decompose(m.position,m.rotation,m.scale),m.matrixWorldNeedsUpdate=!0,m.jointRadius=g.radius),m.visible=g!==null}let h=c.joints["index-finger-tip"],u=c.joints["thumb-tip"],d=h.position.distanceTo(u.position),f=.02,p=.005;c.inputState.pinching&&d>f+p?(c.inputState.pinching=!1,this.dispatchEvent({type:"pinchend",handedness:t.handedness,target:this})):!c.inputState.pinching&&d<=f-p&&(c.inputState.pinching=!0,this.dispatchEvent({type:"pinchstart",handedness:t.handedness,target:this}))}else l!==null&&t.gripSpace&&(r=e.getPose(t.gripSpace,n),r!==null&&(l.matrix.fromArray(r.transform.matrix),l.matrix.decompose(l.position,l.rotation,l.scale),l.matrixWorldNeedsUpdate=!0,r.linearVelocity?(l.hasLinearVelocity=!0,l.linearVelocity.copy(r.linearVelocity)):l.hasLinearVelocity=!1,r.angularVelocity?(l.hasAngularVelocity=!0,l.angularVelocity.copy(r.angularVelocity)):l.hasAngularVelocity=!1,l.eventsEnabled&&l.dispatchEvent({type:"gripUpdated",data:t,target:this})));o!==null&&(i=e.getPose(t.targetRaySpace,n),i===null&&r!==null&&(i=r),i!==null&&(o.matrix.fromArray(i.transform.matrix),o.matrix.decompose(o.position,o.rotation,o.scale),o.matrixWorldNeedsUpdate=!0,i.linearVelocity?(o.hasLinearVelocity=!0,o.linearVelocity.copy(i.linearVelocity)):o.hasLinearVelocity=!1,i.angularVelocity?(o.hasAngularVelocity=!0,o.angularVelocity.copy(i.angularVelocity)):o.hasAngularVelocity=!1,this.dispatchEvent(pf)))}return o!==null&&(o.visible=i!==null),l!==null&&(l.visible=r!==null),c!==null&&(c.visible=a!==null),this}_getHandJoint(t,e){if(t.joints[e.jointName]===void 0){let n=new Je;n.matrixAutoUpdate=!1,n.visible=!1,t.joints[e.jointName]=n,t.add(n)}return t.joints[e.jointName]}},Hu={aliceblue:15792383,antiquewhite:16444375,aqua:65535,aquamarine:8388564,azure:15794175,beige:16119260,bisque:16770244,black:0,blanchedalmond:16772045,blue:255,blueviolet:9055202,brown:10824234,burlywood:14596231,cadetblue:6266528,chartreuse:8388352,chocolate:13789470,coral:16744272,cornflowerblue:6591981,cornsilk:16775388,crimson:14423100,cyan:65535,darkblue:139,darkcyan:35723,darkgoldenrod:12092939,darkgray:11119017,darkgreen:25600,darkgrey:11119017,darkkhaki:12433259,darkmagenta:9109643,darkolivegreen:5597999,darkorange:16747520,darkorchid:10040012,darkred:9109504,darksalmon:15308410,darkseagreen:9419919,darkslateblue:4734347,darkslategray:3100495,darkslategrey:3100495,darkturquoise:52945,darkviolet:9699539,deeppink:16716947,deepskyblue:49151,dimgray:6908265,dimgrey:6908265,dodgerblue:2003199,firebrick:11674146,floralwhite:16775920,forestgreen:2263842,fuchsia:16711935,gainsboro:14474460,ghostwhite:16316671,gold:16766720,goldenrod:14329120,gray:8421504,green:32768,greenyellow:11403055,grey:8421504,honeydew:15794160,hotpink:16738740,indianred:13458524,indigo:4915330,ivory:16777200,khaki:15787660,lavender:15132410,lavenderblush:16773365,lawngreen:8190976,lemonchiffon:16775885,lightblue:11393254,lightcoral:15761536,lightcyan:14745599,lightgoldenrodyellow:16448210,lightgray:13882323,lightgreen:9498256,lightgrey:13882323,lightpink:16758465,lightsalmon:16752762,lightseagreen:2142890,lightskyblue:8900346,lightslategray:7833753,lightslategrey:7833753,lightsteelblue:11584734,lightyellow:16777184,lime:65280,limegreen:3329330,linen:16445670,magenta:16711935,maroon:8388608,mediumaquamarine:6737322,mediumblue:205,mediumorchid:12211667,mediumpurple:9662683,mediumseagreen:3978097,mediumslateblue:8087790,mediumspringgreen:64154,mediumturquoise:4772300,mediumvioletred:13047173,midnightblue:1644912,mintcream:16121850,mistyrose:16770273,moccasin:16770229,navajowhite:16768685,navy:128,oldlace:16643558,olive:8421376,olivedrab:7048739,orange:16753920,orangered:16729344,orchid:14315734,palegoldenrod:15657130,palegreen:10025880,paleturquoise:11529966,palevioletred:14381203,papayawhip:16773077,peachpuff:16767673,peru:13468991,pink:16761035,plum:14524637,powderblue:11591910,purple:8388736,rebeccapurple:6697881,red:16711680,rosybrown:12357519,royalblue:4286945,saddlebrown:9127187,salmon:16416882,sandybrown:16032864,seagreen:3050327,seashell:16774638,sienna:10506797,silver:12632256,skyblue:8900331,slateblue:6970061,slategray:7372944,slategrey:7372944,snow:16775930,springgreen:65407,steelblue:4620980,tan:13808780,teal:32896,thistle:14204888,tomato:16737095,turquoise:4251856,violet:15631086,wheat:16113331,white:16777215,whitesmoke:16119285,yellow:16776960,yellowgreen:10145074},di={h:0,s:0,l:0},_a={h:0,s:0,l:0};function Xl(s,t,e){return e<0&&(e+=1),e>1&&(e-=1),e<1/6?s+(t-s)*6*e:e<1/2?t:e<2/3?s+(t-s)*6*(2/3-e):s}var Ft=class{constructor(t,e,n){return this.isColor=!0,this.r=1,this.g=1,this.b=1,this.set(t,e,n)}set(t,e,n){if(e===void 0&&n===void 0){let i=t;i&&i.isColor?this.copy(i):typeof i=="number"?this.setHex(i):typeof i=="string"&&this.setStyle(i)}else this.setRGB(t,e,n);return this}setScalar(t){return this.r=t,this.g=t,this.b=t,this}setHex(t,e=Be){return t=Math.floor(t),this.r=(t>>16&255)/255,this.g=(t>>8&255)/255,this.b=(t&255)/255,ne.colorSpaceToWorking(this,e),this}setRGB(t,e,n,i=ne.workingColorSpace){return this.r=t,this.g=e,this.b=n,ne.colorSpaceToWorking(this,i),this}setHSL(t,e,n,i=ne.workingColorSpace){if(t=sf(t,1),e=se(e,0,1),n=se(n,0,1),e===0)this.r=this.g=this.b=n;else{let r=n<=.5?n*(1+e):n+e-n*e,a=2*n-r;this.r=Xl(a,r,t+1/3),this.g=Xl(a,r,t),this.b=Xl(a,r,t-1/3)}return ne.colorSpaceToWorking(this,i),this}setStyle(t,e=Be){function n(r){r!==void 0&&parseFloat(r)<1&&qt("Color: Alpha component of "+t+" will be ignored.")}let i;if(i=/^(\w+)\(([^\)]*)\)/.exec(t)){let r,a=i[1],o=i[2];switch(a){case"rgb":case"rgba":if(r=/^\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*(\d*\.?\d+)\s*)?$/.exec(o))return n(r[4]),this.setRGB(Math.min(255,parseInt(r[1],10))/255,Math.min(255,parseInt(r[2],10))/255,Math.min(255,parseInt(r[3],10))/255,e);if(r=/^\s*(\d+)\%\s*,\s*(\d+)\%\s*,\s*(\d+)\%\s*(?:,\s*(\d*\.?\d+)\s*)?$/.exec(o))return n(r[4]),this.setRGB(Math.min(100,parseInt(r[1],10))/100,Math.min(100,parseInt(r[2],10))/100,Math.min(100,parseInt(r[3],10))/100,e);break;case"hsl":case"hsla":if(r=/^\s*(\d*\.?\d+)\s*,\s*(\d*\.?\d+)\%\s*,\s*(\d*\.?\d+)\%\s*(?:,\s*(\d*\.?\d+)\s*)?$/.exec(o))return n(r[4]),this.setHSL(parseFloat(r[1])/360,parseFloat(r[2])/100,parseFloat(r[3])/100,e);break;default:qt("Color: Unknown color model "+t)}}else if(i=/^\#([A-Fa-f\d]+)$/.exec(t)){let r=i[1],a=r.length;if(a===3)return this.setRGB(parseInt(r.charAt(0),16)/15,parseInt(r.charAt(1),16)/15,parseInt(r.charAt(2),16)/15,e);if(a===6)return this.setHex(parseInt(r,16),e);qt("Color: Invalid hex color "+t)}else if(t&&t.length>0)return this.setColorName(t,e);return this}setColorName(t,e=Be){let n=Hu[t.toLowerCase()];return n!==void 0?this.setHex(n,e):qt("Color: Unknown color "+t),this}clone(){return new this.constructor(this.r,this.g,this.b)}copy(t){return this.r=t.r,this.g=t.g,this.b=t.b,this}copySRGBToLinear(t){return this.r=ei(t.r),this.g=ei(t.g),this.b=ei(t.b),this}copyLinearToSRGB(t){return this.r=Ss(t.r),this.g=Ss(t.g),this.b=Ss(t.b),this}convertSRGBToLinear(){return this.copySRGBToLinear(this),this}convertLinearToSRGB(){return this.copyLinearToSRGB(this),this}getHex(t=Be){return ne.workingToColorSpace(Ze.copy(this),t),Math.round(se(Ze.r*255,0,255))*65536+Math.round(se(Ze.g*255,0,255))*256+Math.round(se(Ze.b*255,0,255))}getHexString(t=Be){return("000000"+this.getHex(t).toString(16)).slice(-6)}getHSL(t,e=ne.workingColorSpace){ne.workingToColorSpace(Ze.copy(this),e);let n=Ze.r,i=Ze.g,r=Ze.b,a=Math.max(n,i,r),o=Math.min(n,i,r),l,c,h=(o+a)/2;if(o===a)l=0,c=0;else{let u=a-o;switch(c=h<=.5?u/(a+o):u/(2-a-o),a){case n:l=(i-r)/u+(i<r?6:0);break;case i:l=(r-n)/u+2;break;case r:l=(n-i)/u+4;break}l/=6}return t.h=l,t.s=c,t.l=h,t}getRGB(t,e=ne.workingColorSpace){return ne.workingToColorSpace(Ze.copy(this),e),t.r=Ze.r,t.g=Ze.g,t.b=Ze.b,t}getStyle(t=Be){ne.workingToColorSpace(Ze.copy(this),t);let e=Ze.r,n=Ze.g,i=Ze.b;return t!==Be?`color(${t} ${e.toFixed(3)} ${n.toFixed(3)} ${i.toFixed(3)})`:`rgb(${Math.round(e*255)},${Math.round(n*255)},${Math.round(i*255)})`}offsetHSL(t,e,n){return this.getHSL(di),this.setHSL(di.h+t,di.s+e,di.l+n)}add(t){return this.r+=t.r,this.g+=t.g,this.b+=t.b,this}addColors(t,e){return this.r=t.r+e.r,this.g=t.g+e.g,this.b=t.b+e.b,this}addScalar(t){return this.r+=t,this.g+=t,this.b+=t,this}sub(t){return this.r=Math.max(0,this.r-t.r),this.g=Math.max(0,this.g-t.g),this.b=Math.max(0,this.b-t.b),this}multiply(t){return this.r*=t.r,this.g*=t.g,this.b*=t.b,this}multiplyScalar(t){return this.r*=t,this.g*=t,this.b*=t,this}lerp(t,e){return this.r+=(t.r-this.r)*e,this.g+=(t.g-this.g)*e,this.b+=(t.b-this.b)*e,this}lerpColors(t,e,n){return this.r=t.r+(e.r-t.r)*n,this.g=t.g+(e.g-t.g)*n,this.b=t.b+(e.b-t.b)*n,this}lerpHSL(t,e){this.getHSL(di),t.getHSL(_a);let n=kl(di.h,_a.h,e),i=kl(di.s,_a.s,e),r=kl(di.l,_a.l,e);return this.setHSL(n,i,r),this}setFromVector3(t){return this.r=t.x,this.g=t.y,this.b=t.z,this}applyMatrix3(t){let e=this.r,n=this.g,i=this.b,r=t.elements;return this.r=r[0]*e+r[3]*n+r[6]*i,this.g=r[1]*e+r[4]*n+r[7]*i,this.b=r[2]*e+r[5]*n+r[8]*i,this}equals(t){return t.r===this.r&&t.g===this.g&&t.b===this.b}fromArray(t,e=0){return this.r=t[e],this.g=t[e+1],this.b=t[e+2],this}toArray(t=[],e=0){return t[e]=this.r,t[e+1]=this.g,t[e+2]=this.b,t}fromBufferAttribute(t,e){return this.r=t.getX(e),this.g=t.getY(e),this.b=t.getZ(e),this}toJSON(){return this.getHex()}*[Symbol.iterator](){yield this.r,yield this.g,yield this.b}},Ze=new Ft;Ft.NAMES=Hu;var yr=class s{constructor(t,e=1,n=1e3){this.isFog=!0,this.name="",this.color=new Ft(t),this.near=e,this.far=n}clone(){return new s(this.color,this.near,this.far)}toJSON(){return{type:"Fog",name:this.name,color:this.color.getHex(),near:this.near,far:this.far}}},zi=class extends Re{constructor(){super(),this.isScene=!0,this.type="Scene",this.background=null,this.environment=null,this.fog=null,this.backgroundBlurriness=0,this.backgroundIntensity=1,this.backgroundRotation=new mn,this.environmentIntensity=1,this.environmentRotation=new mn,this.overrideMaterial=null,typeof __THREE_DEVTOOLS__<"u"&&__THREE_DEVTOOLS__.dispatchEvent(new CustomEvent("observe",{detail:this}))}copy(t,e){return super.copy(t,e),t.background!==null&&(this.background=t.background.clone()),t.environment!==null&&(this.environment=t.environment.clone()),t.fog!==null&&(this.fog=t.fog.clone()),this.backgroundBlurriness=t.backgroundBlurriness,this.backgroundIntensity=t.backgroundIntensity,this.backgroundRotation.copy(t.backgroundRotation),this.environmentIntensity=t.environmentIntensity,this.environmentRotation.copy(t.environmentRotation),t.overrideMaterial!==null&&(this.overrideMaterial=t.overrideMaterial.clone()),this.matrixAutoUpdate=t.matrixAutoUpdate,this}toJSON(t){let e=super.toJSON(t);return this.fog!==null&&(e.object.fog=this.fog.toJSON()),e.object.backgroundBlurriness=this.backgroundBlurriness,e.object.backgroundIntensity=this.backgroundIntensity,e.object.backgroundRotation=this.backgroundRotation.toArray(),e.object.environmentIntensity=this.environmentIntensity,e.object.environmentRotation=this.environmentRotation.toArray(),e}},Tn=new D,Jn=new D,ql=new D,$n=new D,ls=new D,cs=new D,Oh=new D,Yl=new D,Zl=new D,Jl=new D,$l=new Se,Kl=new Se,Ql=new Se,jn=class s{constructor(t=new D,e=new D,n=new D){this.a=t,this.b=e,this.c=n}static getNormal(t,e,n,i){i.subVectors(n,e),Tn.subVectors(t,e),i.cross(Tn);let r=i.lengthSq();return r>0?i.multiplyScalar(1/Math.sqrt(r)):i.set(0,0,0)}static getBarycoord(t,e,n,i,r){Tn.subVectors(i,e),Jn.subVectors(n,e),ql.subVectors(t,e);let a=Tn.dot(Tn),o=Tn.dot(Jn),l=Tn.dot(ql),c=Jn.dot(Jn),h=Jn.dot(ql),u=a*c-o*o;if(u===0)return r.set(0,0,0),null;let d=1/u,f=(c*l-o*h)*d,p=(a*h-o*l)*d;return r.set(1-f-p,p,f)}static containsPoint(t,e,n,i){return this.getBarycoord(t,e,n,i,$n)===null?!1:$n.x>=0&&$n.y>=0&&$n.x+$n.y<=1}static getInterpolation(t,e,n,i,r,a,o,l){return this.getBarycoord(t,e,n,i,$n)===null?(l.x=0,l.y=0,"z"in l&&(l.z=0),"w"in l&&(l.w=0),null):(l.setScalar(0),l.addScaledVector(r,$n.x),l.addScaledVector(a,$n.y),l.addScaledVector(o,$n.z),l)}static getInterpolatedAttribute(t,e,n,i,r,a){return $l.setScalar(0),Kl.setScalar(0),Ql.setScalar(0),$l.fromBufferAttribute(t,e),Kl.fromBufferAttribute(t,n),Ql.fromBufferAttribute(t,i),a.setScalar(0),a.addScaledVector($l,r.x),a.addScaledVector(Kl,r.y),a.addScaledVector(Ql,r.z),a}static isFrontFacing(t,e,n,i){return Tn.subVectors(n,e),Jn.subVectors(t,e),Tn.cross(Jn).dot(i)<0}set(t,e,n){return this.a.copy(t),this.b.copy(e),this.c.copy(n),this}setFromPointsAndIndices(t,e,n,i){return this.a.copy(t[e]),this.b.copy(t[n]),this.c.copy(t[i]),this}setFromAttributeAndIndices(t,e,n,i){return this.a.fromBufferAttribute(t,e),this.b.fromBufferAttribute(t,n),this.c.fromBufferAttribute(t,i),this}clone(){return new this.constructor().copy(this)}copy(t){return this.a.copy(t.a),this.b.copy(t.b),this.c.copy(t.c),this}getArea(){return Tn.subVectors(this.c,this.b),Jn.subVectors(this.a,this.b),Tn.cross(Jn).length()*.5}getMidpoint(t){return t.addVectors(this.a,this.b).add(this.c).multiplyScalar(1/3)}getNormal(t){return s.getNormal(this.a,this.b,this.c,t)}getPlane(t){return t.setFromCoplanarPoints(this.a,this.b,this.c)}getBarycoord(t,e){return s.getBarycoord(t,this.a,this.b,this.c,e)}getInterpolation(t,e,n,i,r){return s.getInterpolation(t,this.a,this.b,this.c,e,n,i,r)}containsPoint(t){return s.containsPoint(t,this.a,this.b,this.c)}isFrontFacing(t){return s.isFrontFacing(this.a,this.b,this.c,t)}intersectsBox(t){return t.intersectsTriangle(this)}closestPointToPoint(t,e){let n=this.a,i=this.b,r=this.c,a,o;ls.subVectors(i,n),cs.subVectors(r,n),Yl.subVectors(t,n);let l=ls.dot(Yl),c=cs.dot(Yl);if(l<=0&&c<=0)return e.copy(n);Zl.subVectors(t,i);let h=ls.dot(Zl),u=cs.dot(Zl);if(h>=0&&u<=h)return e.copy(i);let d=l*u-h*c;if(d<=0&&l>=0&&h<=0)return a=l/(l-h),e.copy(n).addScaledVector(ls,a);Jl.subVectors(t,r);let f=ls.dot(Jl),p=cs.dot(Jl);if(p>=0&&f<=p)return e.copy(r);let y=f*c-l*p;if(y<=0&&c>=0&&p<=0)return o=c/(c-p),e.copy(n).addScaledVector(cs,o);let g=h*p-f*u;if(g<=0&&u-h>=0&&f-p>=0)return Oh.subVectors(r,i),o=(u-h)/(u-h+(f-p)),e.copy(i).addScaledVector(Oh,o);let m=1/(g+y+d);return a=y*m,o=d*m,e.copy(n).addScaledVector(ls,a).addScaledVector(cs,o)}equals(t){return t.a.equals(this.a)&&t.b.equals(this.b)&&t.c.equals(this.c)}},Gn=class{constructor(t=new D(1/0,1/0,1/0),e=new D(-1/0,-1/0,-1/0)){this.isBox3=!0,this.min=t,this.max=e}set(t,e){return this.min.copy(t),this.max.copy(e),this}setFromArray(t){this.makeEmpty();for(let e=0,n=t.length;e<n;e+=3)this.expandByPoint(An.fromArray(t,e));return this}setFromBufferAttribute(t){this.makeEmpty();for(let e=0,n=t.count;e<n;e++)this.expandByPoint(An.fromBufferAttribute(t,e));return this}setFromPoints(t){this.makeEmpty();for(let e=0,n=t.length;e<n;e++)this.expandByPoint(t[e]);return this}setFromCenterAndSize(t,e){let n=An.copy(e).multiplyScalar(.5);return this.min.copy(t).sub(n),this.max.copy(t).add(n),this}setFromObject(t,e=!1){return this.makeEmpty(),this.expandByObject(t,e)}clone(){return new this.constructor().copy(this)}copy(t){return this.min.copy(t.min),this.max.copy(t.max),this}makeEmpty(){return this.min.x=this.min.y=this.min.z=1/0,this.max.x=this.max.y=this.max.z=-1/0,this}isEmpty(){return this.max.x<this.min.x||this.max.y<this.min.y||this.max.z<this.min.z}getCenter(t){return this.isEmpty()?t.set(0,0,0):t.addVectors(this.min,this.max).multiplyScalar(.5)}getSize(t){return this.isEmpty()?t.set(0,0,0):t.subVectors(this.max,this.min)}expandByPoint(t){return this.min.min(t),this.max.max(t),this}expandByVector(t){return this.min.sub(t),this.max.add(t),this}expandByScalar(t){return this.min.addScalar(-t),this.max.addScalar(t),this}expandByObject(t,e=!1){t.updateWorldMatrix(!1,!1);let n=t.geometry;if(n!==void 0){let r=n.getAttribute("position");if(e===!0&&r!==void 0&&t.isInstancedMesh!==!0)for(let a=0,o=r.count;a<o;a++)t.isMesh===!0?t.getVertexPosition(a,An):An.fromBufferAttribute(r,a),An.applyMatrix4(t.matrixWorld),this.expandByPoint(An);else t.boundingBox!==void 0?(t.boundingBox===null&&t.computeBoundingBox(),ya.copy(t.boundingBox)):(n.boundingBox===null&&n.computeBoundingBox(),ya.copy(n.boundingBox)),ya.applyMatrix4(t.matrixWorld),this.union(ya)}let i=t.children;for(let r=0,a=i.length;r<a;r++)this.expandByObject(i[r],e);return this}containsPoint(t){return t.x>=this.min.x&&t.x<=this.max.x&&t.y>=this.min.y&&t.y<=this.max.y&&t.z>=this.min.z&&t.z<=this.max.z}containsBox(t){return this.min.x<=t.min.x&&t.max.x<=this.max.x&&this.min.y<=t.min.y&&t.max.y<=this.max.y&&this.min.z<=t.min.z&&t.max.z<=this.max.z}getParameter(t,e){return e.set((t.x-this.min.x)/(this.max.x-this.min.x),(t.y-this.min.y)/(this.max.y-this.min.y),(t.z-this.min.z)/(this.max.z-this.min.z))}intersectsBox(t){return t.max.x>=this.min.x&&t.min.x<=this.max.x&&t.max.y>=this.min.y&&t.min.y<=this.max.y&&t.max.z>=this.min.z&&t.min.z<=this.max.z}intersectsSphere(t){return this.clampPoint(t.center,An),An.distanceToSquared(t.center)<=t.radius*t.radius}intersectsPlane(t){let e,n;return t.normal.x>0?(e=t.normal.x*this.min.x,n=t.normal.x*this.max.x):(e=t.normal.x*this.max.x,n=t.normal.x*this.min.x),t.normal.y>0?(e+=t.normal.y*this.min.y,n+=t.normal.y*this.max.y):(e+=t.normal.y*this.max.y,n+=t.normal.y*this.min.y),t.normal.z>0?(e+=t.normal.z*this.min.z,n+=t.normal.z*this.max.z):(e+=t.normal.z*this.max.z,n+=t.normal.z*this.min.z),e<=-t.constant&&n>=-t.constant}intersectsTriangle(t){if(this.isEmpty())return!1;this.getCenter(tr),va.subVectors(this.max,tr),hs.subVectors(t.a,tr),us.subVectors(t.b,tr),ds.subVectors(t.c,tr),fi.subVectors(us,hs),pi.subVectors(ds,us),Ni.subVectors(hs,ds);let e=[0,-fi.z,fi.y,0,-pi.z,pi.y,0,-Ni.z,Ni.y,fi.z,0,-fi.x,pi.z,0,-pi.x,Ni.z,0,-Ni.x,-fi.y,fi.x,0,-pi.y,pi.x,0,-Ni.y,Ni.x,0];return!jl(e,hs,us,ds,va)||(e=[1,0,0,0,1,0,0,0,1],!jl(e,hs,us,ds,va))?!1:(Ma.crossVectors(fi,pi),e=[Ma.x,Ma.y,Ma.z],jl(e,hs,us,ds,va))}clampPoint(t,e){return e.copy(t).clamp(this.min,this.max)}distanceToPoint(t){return this.clampPoint(t,An).distanceTo(t)}getBoundingSphere(t){return this.isEmpty()?t.makeEmpty():(this.getCenter(t.center),t.radius=this.getSize(An).length()*.5),t}intersect(t){return this.min.max(t.min),this.max.min(t.max),this.isEmpty()&&this.makeEmpty(),this}union(t){return this.min.min(t.min),this.max.max(t.max),this}applyMatrix4(t){return this.isEmpty()?this:(Kn[0].set(this.min.x,this.min.y,this.min.z).applyMatrix4(t),Kn[1].set(this.min.x,this.min.y,this.max.z).applyMatrix4(t),Kn[2].set(this.min.x,this.max.y,this.min.z).applyMatrix4(t),Kn[3].set(this.min.x,this.max.y,this.max.z).applyMatrix4(t),Kn[4].set(this.max.x,this.min.y,this.min.z).applyMatrix4(t),Kn[5].set(this.max.x,this.min.y,this.max.z).applyMatrix4(t),Kn[6].set(this.max.x,this.max.y,this.min.z).applyMatrix4(t),Kn[7].set(this.max.x,this.max.y,this.max.z).applyMatrix4(t),this.setFromPoints(Kn),this)}translate(t){return this.min.add(t),this.max.add(t),this}equals(t){return t.min.equals(this.min)&&t.max.equals(this.max)}toJSON(){return{min:this.min.toArray(),max:this.max.toArray()}}fromJSON(t){return this.min.fromArray(t.min),this.max.fromArray(t.max),this}},Kn=[new D,new D,new D,new D,new D,new D,new D,new D],An=new D,ya=new Gn,hs=new D,us=new D,ds=new D,fi=new D,pi=new D,Ni=new D,tr=new D,va=new D,Ma=new D,Fi=new D;function jl(s,t,e,n,i){for(let r=0,a=s.length-3;r<=a;r+=3){Fi.fromArray(s,r);let o=i.x*Math.abs(Fi.x)+i.y*Math.abs(Fi.y)+i.z*Math.abs(Fi.z),l=t.dot(Fi),c=e.dot(Fi),h=n.dot(Fi);if(Math.max(-Math.max(l,c,h),Math.min(l,c,h))>o)return!1}return!0}var Ie=new D,Sa=new rt,mf=0,ke=class extends Vn{constructor(t,e,n=!1){if(super(),Array.isArray(t))throw new TypeError("THREE.BufferAttribute: array should be a Typed Array.");this.isBufferAttribute=!0,Object.defineProperty(this,"id",{value:mf++}),this.name="",this.array=t,this.itemSize=e,this.count=t!==void 0?t.length/e:0,this.normalized=n,this.usage=Nc,this.updateRanges=[],this.gpuType=yn,this.version=0}onUploadCallback(){}set needsUpdate(t){t===!0&&this.version++}setUsage(t){return this.usage=t,this}addUpdateRange(t,e){this.updateRanges.push({start:t,count:e})}clearUpdateRanges(){this.updateRanges.length=0}copy(t){return this.name=t.name,this.array=new t.array.constructor(t.array),this.itemSize=t.itemSize,this.count=t.count,this.normalized=t.normalized,this.usage=t.usage,this.gpuType=t.gpuType,this}copyAt(t,e,n){t*=this.itemSize,n*=e.itemSize;for(let i=0,r=this.itemSize;i<r;i++)this.array[t+i]=e.array[n+i];return this}copyArray(t){return this.array.set(t),this}applyMatrix3(t){if(this.itemSize===2)for(let e=0,n=this.count;e<n;e++)Sa.fromBufferAttribute(this,e),Sa.applyMatrix3(t),this.setXY(e,Sa.x,Sa.y);else if(this.itemSize===3)for(let e=0,n=this.count;e<n;e++)Ie.fromBufferAttribute(this,e),Ie.applyMatrix3(t),this.setXYZ(e,Ie.x,Ie.y,Ie.z);return this}applyMatrix4(t){for(let e=0,n=this.count;e<n;e++)Ie.fromBufferAttribute(this,e),Ie.applyMatrix4(t),this.setXYZ(e,Ie.x,Ie.y,Ie.z);return this}applyNormalMatrix(t){for(let e=0,n=this.count;e<n;e++)Ie.fromBufferAttribute(this,e),Ie.applyNormalMatrix(t),this.setXYZ(e,Ie.x,Ie.y,Ie.z);return this}transformDirection(t){for(let e=0,n=this.count;e<n;e++)Ie.fromBufferAttribute(this,e),Ie.transformDirection(t),this.setXYZ(e,Ie.x,Ie.y,Ie.z);return this}set(t,e=0){return this.array.set(t,e),this}getComponent(t,e){let n=this.array[t*this.itemSize+e];return this.normalized&&(n=Bn(n,this.array)),n}setComponent(t,e,n){return this.normalized&&(n=me(n,this.array)),this.array[t*this.itemSize+e]=n,this}getX(t){let e=this.array[t*this.itemSize];return this.normalized&&(e=Bn(e,this.array)),e}setX(t,e){return this.normalized&&(e=me(e,this.array)),this.array[t*this.itemSize]=e,this}getY(t){let e=this.array[t*this.itemSize+1];return this.normalized&&(e=Bn(e,this.array)),e}setY(t,e){return this.normalized&&(e=me(e,this.array)),this.array[t*this.itemSize+1]=e,this}getZ(t){let e=this.array[t*this.itemSize+2];return this.normalized&&(e=Bn(e,this.array)),e}setZ(t,e){return this.normalized&&(e=me(e,this.array)),this.array[t*this.itemSize+2]=e,this}getW(t){let e=this.array[t*this.itemSize+3];return this.normalized&&(e=Bn(e,this.array)),e}setW(t,e){return this.normalized&&(e=me(e,this.array)),this.array[t*this.itemSize+3]=e,this}setXY(t,e,n){return t*=this.itemSize,this.normalized&&(e=me(e,this.array),n=me(n,this.array)),this.array[t+0]=e,this.array[t+1]=n,this}setXYZ(t,e,n,i){return t*=this.itemSize,this.normalized&&(e=me(e,this.array),n=me(n,this.array),i=me(i,this.array)),this.array[t+0]=e,this.array[t+1]=n,this.array[t+2]=i,this}setXYZW(t,e,n,i,r){return t*=this.itemSize,this.normalized&&(e=me(e,this.array),n=me(n,this.array),i=me(i,this.array),r=me(r,this.array)),this.array[t+0]=e,this.array[t+1]=n,this.array[t+2]=i,this.array[t+3]=r,this}onUpload(t){return this.onUploadCallback=t,this}clone(){return new this.constructor(this.array,this.itemSize).copy(this)}toJSON(){let t={itemSize:this.itemSize,type:this.array.constructor.name,array:Array.from(this.array),normalized:this.normalized};return t.name=this.name,t.usage=this.usage,t.gpuType=this.gpuType,t}dispose(){this.dispatchEvent({type:"dispose"})}};var vr=class extends ke{constructor(t,e,n){super(new Uint16Array(t),e,n)}};var Mr=class extends ke{constructor(t,e,n){super(new Uint32Array(t),e,n)}};var Zt=class extends ke{constructor(t,e,n){super(new Float32Array(t),e,n)}},gf=new Gn,er=new D,tc=new D,gi=class{constructor(t=new D,e=-1){this.isSphere=!0,this.center=t,this.radius=e}set(t,e){return this.center.copy(t),this.radius=e,this}setFromPoints(t,e){let n=this.center;e!==void 0?n.copy(e):gf.setFromPoints(t).getCenter(n);let i=0;for(let r=0,a=t.length;r<a;r++)i=Math.max(i,n.distanceToSquared(t[r]));return this.radius=Math.sqrt(i),this}copy(t){return this.center.copy(t.center),this.radius=t.radius,this}isEmpty(){return this.radius<0}makeEmpty(){return this.center.set(0,0,0),this.radius=-1,this}containsPoint(t){return t.distanceToSquared(this.center)<=this.radius*this.radius}distanceToPoint(t){return t.distanceTo(this.center)-this.radius}intersectsSphere(t){let e=this.radius+t.radius;return t.center.distanceToSquared(this.center)<=e*e}intersectsBox(t){return t.intersectsSphere(this)}intersectsPlane(t){return Math.abs(t.distanceToPoint(this.center))<=this.radius}clampPoint(t,e){let n=this.center.distanceToSquared(t);return e.copy(t),n>this.radius*this.radius&&(e.sub(this.center).normalize(),e.multiplyScalar(this.radius).add(this.center)),e}getBoundingBox(t){return this.isEmpty()?(t.makeEmpty(),t):(t.set(this.center,this.center),t.expandByScalar(this.radius),t)}applyMatrix4(t){return this.center.applyMatrix4(t),this.radius=this.radius*t.getMaxScaleOnAxis(),this}translate(t){return this.center.add(t),this}expandByPoint(t){if(this.isEmpty())return this.center.copy(t),this.radius=0,this;er.subVectors(t,this.center);let e=er.lengthSq();if(e>this.radius*this.radius){let n=Math.sqrt(e),i=(n-this.radius)*.5;this.center.addScaledVector(er,i/n),this.radius+=i}return this}union(t){return t.isEmpty()?this:this.isEmpty()?(this.copy(t),this):(this.center.equals(t.center)===!0?this.radius=Math.max(this.radius,t.radius):(tc.subVectors(t.center,this.center).setLength(t.radius),this.expandByPoint(er.copy(t.center).add(tc)),this.expandByPoint(er.copy(t.center).sub(tc))),this)}equals(t){return t.center.equals(this.center)&&t.radius===this.radius}clone(){return new this.constructor().copy(this)}toJSON(){return{radius:this.radius,center:this.center.toArray()}}fromJSON(t){return this.radius=t.radius,this.center.fromArray(t.center),this}},xf=0,fn=new fe,ec=new Re,fs=new D,ln=new Gn,nr=new Gn,Fe=new D,xe=class s extends Vn{constructor(){super(),this.isBufferGeometry=!0,Object.defineProperty(this,"id",{value:xf++}),this.uuid=ti(),this.name="",this.type="BufferGeometry",this.index=null,this.indirect=null,this.indirectOffset=0,this.attributes={},this.morphAttributes={},this.morphTargetsRelative=!1,this.groups=[],this.boundingBox=null,this.boundingSphere=null,this.drawRange={start:0,count:1/0},this.userData={},this._transformed=!1}getIndex(){return this.index}setIndex(t){return Array.isArray(t)?this.index=new(ef(t)?Mr:vr)(t,1):this.index=t,this}setIndirect(t,e=0){return this.indirect=t,this.indirectOffset=e,this}getIndirect(){return this.indirect}getAttribute(t){return this.attributes[t]}setAttribute(t,e){return this.attributes[t]=e,this}deleteAttribute(t){return delete this.attributes[t],this}hasAttribute(t){return this.attributes[t]!==void 0}addGroup(t,e,n=0){this.groups.push({start:t,count:e,materialIndex:n})}clearGroups(){this.groups=[]}setDrawRange(t,e){this.drawRange.start=t,this.drawRange.count=e}applyMatrix4(t){let e=this.attributes.position;e!==void 0&&(e.applyMatrix4(t),e.needsUpdate=!0);let n=this.attributes.normal;if(n!==void 0){let r=new $t().getNormalMatrix(t);n.applyNormalMatrix(r),n.needsUpdate=!0}let i=this.attributes.tangent;return i!==void 0&&(i.transformDirection(t),i.needsUpdate=!0),this.boundingBox!==null&&this.computeBoundingBox(),this.boundingSphere!==null&&this.computeBoundingSphere(),this._transformed=!0,this}applyQuaternion(t){return fn.makeRotationFromQuaternion(t),this.applyMatrix4(fn),this}rotateX(t){return fn.makeRotationX(t),this.applyMatrix4(fn),this}rotateY(t){return fn.makeRotationY(t),this.applyMatrix4(fn),this}rotateZ(t){return fn.makeRotationZ(t),this.applyMatrix4(fn),this}translate(t,e,n){return fn.makeTranslation(t,e,n),this.applyMatrix4(fn),this}scale(t,e,n){return fn.makeScale(t,e,n),this.applyMatrix4(fn),this}lookAt(t){return ec.lookAt(t),ec.updateMatrix(),this.applyMatrix4(ec.matrix),this}center(){return this.computeBoundingBox(),this.boundingBox.getCenter(fs).negate(),this.translate(fs.x,fs.y,fs.z),this}setFromPoints(t){let e=this.getAttribute("position");if(e===void 0){let n=[];for(let i=0,r=t.length;i<r;i++){let a=t[i];n.push(a.x,a.y,a.z||0)}this.setAttribute("position",new Zt(n,3))}else{let n=Math.min(t.length,e.count);for(let i=0;i<n;i++){let r=t[i];e.setXYZ(i,r.x,r.y,r.z||0)}t.length>e.count&&qt("BufferGeometry: Buffer size too small for points data. Use .dispose() and create a new geometry."),e.needsUpdate=!0}return this}computeBoundingBox(){this.boundingBox===null&&(this.boundingBox=new Gn);let t=this.attributes.position,e=this.morphAttributes.position;if(t&&t.isGLBufferAttribute){Xt("BufferGeometry.computeBoundingBox(): GLBufferAttribute requires a manual bounding box.",this),this.boundingBox.set(new D(-1/0,-1/0,-1/0),new D(1/0,1/0,1/0));return}if(t!==void 0){if(this.boundingBox.setFromBufferAttribute(t),e)for(let n=0,i=e.length;n<i;n++){let r=e[n];ln.setFromBufferAttribute(r),this.morphTargetsRelative?(Fe.addVectors(this.boundingBox.min,ln.min),this.boundingBox.expandByPoint(Fe),Fe.addVectors(this.boundingBox.max,ln.max),this.boundingBox.expandByPoint(Fe)):(this.boundingBox.expandByPoint(ln.min),this.boundingBox.expandByPoint(ln.max))}}else this.boundingBox.makeEmpty();(isNaN(this.boundingBox.min.x)||isNaN(this.boundingBox.min.y)||isNaN(this.boundingBox.min.z))&&Xt('BufferGeometry.computeBoundingBox(): Computed min/max have NaN values. The "position" attribute is likely to have NaN values.',this)}computeBoundingSphere(){this.boundingSphere===null&&(this.boundingSphere=new gi);let t=this.attributes.position,e=this.morphAttributes.position;if(t&&t.isGLBufferAttribute){Xt("BufferGeometry.computeBoundingSphere(): GLBufferAttribute requires a manual bounding sphere.",this),this.boundingSphere.set(new D,1/0);return}if(t){let n=this.boundingSphere.center;if(ln.setFromBufferAttribute(t),e)for(let r=0,a=e.length;r<a;r++){let o=e[r];nr.setFromBufferAttribute(o),this.morphTargetsRelative?(Fe.addVectors(ln.min,nr.min),ln.expandByPoint(Fe),Fe.addVectors(ln.max,nr.max),ln.expandByPoint(Fe)):(ln.expandByPoint(nr.min),ln.expandByPoint(nr.max))}ln.getCenter(n);let i=0;for(let r=0,a=t.count;r<a;r++)Fe.fromBufferAttribute(t,r),i=Math.max(i,n.distanceToSquared(Fe));if(e)for(let r=0,a=e.length;r<a;r++){let o=e[r],l=this.morphTargetsRelative;for(let c=0,h=o.count;c<h;c++)Fe.fromBufferAttribute(o,c),l&&(fs.fromBufferAttribute(t,c),Fe.add(fs)),i=Math.max(i,n.distanceToSquared(Fe))}this.boundingSphere.radius=Math.sqrt(i),isNaN(this.boundingSphere.radius)&&Xt('BufferGeometry.computeBoundingSphere(): Computed radius is NaN. The "position" attribute is likely to have NaN values.',this)}}computeTangents(){let t=this.index,e=this.attributes;if(t===null||e.position===void 0||e.normal===void 0||e.uv===void 0){Xt("BufferGeometry: .computeTangents() failed. Missing required attributes (index, position, normal or uv)");return}let n=e.position,i=e.normal,r=e.uv,a=this.getAttribute("tangent");(a===void 0||a.count!==n.count)&&(a=new ke(new Float32Array(4*n.count),4),this.setAttribute("tangent",a));let o=[],l=[];for(let _=0;_<n.count;_++)o[_]=new D,l[_]=new D;let c=new D,h=new D,u=new D,d=new rt,f=new rt,p=new rt,y=new D,g=new D;function m(_,E,C){c.fromBufferAttribute(n,_),h.fromBufferAttribute(n,E),u.fromBufferAttribute(n,C),d.fromBufferAttribute(r,_),f.fromBufferAttribute(r,E),p.fromBufferAttribute(r,C),h.sub(c),u.sub(c),f.sub(d),p.sub(d);let I=1/(f.x*p.y-p.x*f.y);isFinite(I)&&(y.copy(h).multiplyScalar(p.y).addScaledVector(u,-f.y).multiplyScalar(I),g.copy(u).multiplyScalar(f.x).addScaledVector(h,-p.x).multiplyScalar(I),o[_].add(y),o[E].add(y),o[C].add(y),l[_].add(g),l[E].add(g),l[C].add(g))}let S=this.groups;S.length===0&&(S=[{start:0,count:t.count}]);for(let _=0,E=S.length;_<E;++_){let C=S[_],I=C.start,U=C.count;for(let k=I,L=I+U;k<L;k+=3)m(t.getX(k+0),t.getX(k+1),t.getX(k+2))}let T=new D,v=new D,b=new D,w=new D;function P(_){b.fromBufferAttribute(i,_),w.copy(b);let E=o[_];T.copy(E),T.sub(b.multiplyScalar(b.dot(E))).normalize(),v.crossVectors(w,E);let I=v.dot(l[_])<0?-1:1;a.setXYZW(_,T.x,T.y,T.z,I)}for(let _=0,E=S.length;_<E;++_){let C=S[_],I=C.start,U=C.count;for(let k=I,L=I+U;k<L;k+=3)P(t.getX(k+0)),P(t.getX(k+1)),P(t.getX(k+2))}this._transformed=!0}computeVertexNormals(){let t=this.index,e=this.getAttribute("position");if(e!==void 0){let n=this.getAttribute("normal");if(n===void 0||n.count!==e.count)n=new ke(new Float32Array(e.count*3),3),this.setAttribute("normal",n);else for(let d=0,f=n.count;d<f;d++)n.setXYZ(d,0,0,0);let i=new D,r=new D,a=new D,o=new D,l=new D,c=new D,h=new D,u=new D;if(t)for(let d=0,f=t.count;d<f;d+=3){let p=t.getX(d+0),y=t.getX(d+1),g=t.getX(d+2);i.fromBufferAttribute(e,p),r.fromBufferAttribute(e,y),a.fromBufferAttribute(e,g),h.subVectors(a,r),u.subVectors(i,r),h.cross(u),o.fromBufferAttribute(n,p),l.fromBufferAttribute(n,y),c.fromBufferAttribute(n,g),o.add(h),l.add(h),c.add(h),n.setXYZ(p,o.x,o.y,o.z),n.setXYZ(y,l.x,l.y,l.z),n.setXYZ(g,c.x,c.y,c.z)}else for(let d=0,f=e.count;d<f;d+=3)i.fromBufferAttribute(e,d+0),r.fromBufferAttribute(e,d+1),a.fromBufferAttribute(e,d+2),h.subVectors(a,r),u.subVectors(i,r),h.cross(u),n.setXYZ(d+0,h.x,h.y,h.z),n.setXYZ(d+1,h.x,h.y,h.z),n.setXYZ(d+2,h.x,h.y,h.z);this.normalizeNormals(),n.needsUpdate=!0}}normalizeNormals(){let t=this.attributes.normal;for(let e=0,n=t.count;e<n;e++)Fe.fromBufferAttribute(t,e),Fe.normalize(),t.setXYZ(e,Fe.x,Fe.y,Fe.z)}toNonIndexed(){function t(o,l){let c=o.array,h=o.itemSize,u=o.normalized,d=new c.constructor(l.length*h),f=0,p=0;for(let y=0,g=l.length;y<g;y++){o.isInterleavedBufferAttribute?f=l[y]*o.data.stride+o.offset:f=l[y]*h;for(let m=0;m<h;m++)d[p++]=c[f++]}return new ke(d,h,u)}if(this.index===null)return qt("BufferGeometry.toNonIndexed(): BufferGeometry is already non-indexed."),this;let e=new s,n=this.index.array,i=this.attributes;for(let o in i){let l=i[o],c=t(l,n);e.setAttribute(o,c)}let r=this.morphAttributes;for(let o in r){let l=[],c=r[o];for(let h=0,u=c.length;h<u;h++){let d=c[h],f=t(d,n);l.push(f)}e.morphAttributes[o]=l}e.morphTargetsRelative=this.morphTargetsRelative;let a=this.groups;for(let o=0,l=a.length;o<l;o++){let c=a[o];e.addGroup(c.start,c.count,c.materialIndex)}return e}toJSON(){let t={metadata:{version:4.7,type:"BufferGeometry",generator:"BufferGeometry.toJSON"}};if(t.uuid=this.uuid,t.type=this.parameters!==void 0&&this._transformed===!0?"BufferGeometry":this.type,t.name=this.name,Object.keys(this.userData).length>0&&(t.userData=this.userData),this.parameters!==void 0&&this._transformed!==!0){let l=this.parameters;for(let c in l)l[c]!==void 0&&(t[c]=l[c]);return t}t.data={attributes:{}};let e=this.index;e!==null&&(t.data.index={type:e.array.constructor.name,array:Array.prototype.slice.call(e.array)});let n=this.attributes;for(let l in n){let c=n[l];t.data.attributes[l]=c.toJSON(t.data)}let i={},r=!1;for(let l in this.morphAttributes){let c=this.morphAttributes[l],h=[];for(let u=0,d=c.length;u<d;u++){let f=c[u];h.push(f.toJSON(t.data))}h.length>0&&(i[l]=h,r=!0)}r&&(t.data.morphAttributes=i,t.data.morphTargetsRelative=this.morphTargetsRelative);let a=this.groups;a.length>0&&(t.data.groups=JSON.parse(JSON.stringify(a)));let o=this.boundingSphere;return o!==null&&(t.data.boundingSphere=o.toJSON()),t}clone(){return new this.constructor().copy(this)}copy(t){this.index=null,this.attributes={},this.morphAttributes={},this.groups=[],this.boundingBox=null,this.boundingSphere=null;let e={};this.name=t.name;let n=t.index;n!==null&&this.setIndex(n.clone());let i=t.attributes;for(let c in i){let h=i[c];this.setAttribute(c,h.clone(e))}let r=t.morphAttributes;for(let c in r){let h=[],u=r[c];for(let d=0,f=u.length;d<f;d++)h.push(u[d].clone(e));this.morphAttributes[c]=h}this.morphTargetsRelative=t.morphTargetsRelative;let a=t.groups;for(let c=0,h=a.length;c<h;c++){let u=a[c];this.addGroup(u.start,u.count,u.materialIndex)}let o=t.boundingBox;o!==null&&(this.boundingBox=o.clone());let l=t.boundingSphere;return l!==null&&(this.boundingSphere=l.clone()),this.drawRange.start=t.drawRange.start,this.drawRange.count=t.drawRange.count,this.userData=t.userData,this._transformed=t._transformed,this}dispose(){this.dispatchEvent({type:"dispose"})}},Sr=class{constructor(t,e){this.isInterleavedBuffer=!0,this.array=t,this.stride=e,this.count=t!==void 0?t.length/e:0,this.usage=Nc,this.updateRanges=[],this.version=0,this.uuid=ti()}onUploadCallback(){}set needsUpdate(t){t===!0&&this.version++}setUsage(t){return this.usage=t,this}addUpdateRange(t,e){this.updateRanges.push({start:t,count:e})}clearUpdateRanges(){this.updateRanges.length=0}copy(t){return this.array=new t.array.constructor(t.array),this.count=t.count,this.stride=t.stride,this.usage=t.usage,this}copyAt(t,e,n){t*=this.stride,n*=e.stride;for(let i=0,r=this.stride;i<r;i++)this.array[t+i]=e.array[n+i];return this}set(t,e=0){return this.array.set(t,e),this}clone(t){t.arrayBuffers===void 0&&(t.arrayBuffers={}),this.array.buffer._uuid===void 0&&(this.array.buffer._uuid=ti()),t.arrayBuffers[this.array.buffer._uuid]===void 0&&(t.arrayBuffers[this.array.buffer._uuid]=this.array.slice(0).buffer);let e=new this.array.constructor(t.arrayBuffers[this.array.buffer._uuid]),n=new this.constructor(e,this.stride);return n.setUsage(this.usage),n}onUpload(t){return this.onUploadCallback=t,this}toJSON(t){t.arrayBuffers===void 0&&(t.arrayBuffers={}),this.array.buffer._uuid===void 0&&(this.array.buffer._uuid=ti()),t.arrayBuffers[this.array.buffer._uuid]===void 0&&(t.arrayBuffers[this.array.buffer._uuid]=Array.from(new Uint32Array(this.array.buffer)));let e={uuid:this.uuid,buffer:this.array.buffer._uuid,type:this.array.constructor.name,stride:this.stride};return e.usage=this.usage,e}},en=new D,Rs=class s{constructor(t,e,n,i=!1){this.isInterleavedBufferAttribute=!0,this.name="",this.data=t,this.itemSize=e,this.offset=n,this.normalized=i}get count(){return this.data.count}get array(){return this.data.array}set needsUpdate(t){this.data.needsUpdate=t}applyMatrix4(t){for(let e=0,n=this.data.count;e<n;e++)en.fromBufferAttribute(this,e),en.applyMatrix4(t),this.setXYZ(e,en.x,en.y,en.z);return this}applyNormalMatrix(t){for(let e=0,n=this.count;e<n;e++)en.fromBufferAttribute(this,e),en.applyNormalMatrix(t),this.setXYZ(e,en.x,en.y,en.z);return this}transformDirection(t){for(let e=0,n=this.count;e<n;e++)en.fromBufferAttribute(this,e),en.transformDirection(t),this.setXYZ(e,en.x,en.y,en.z);return this}getComponent(t,e){let n=this.array[t*this.data.stride+this.offset+e];return this.normalized&&(n=Bn(n,this.array)),n}setComponent(t,e,n){return this.normalized&&(n=me(n,this.array)),this.data.array[t*this.data.stride+this.offset+e]=n,this}setX(t,e){return this.normalized&&(e=me(e,this.array)),this.data.array[t*this.data.stride+this.offset]=e,this}setY(t,e){return this.normalized&&(e=me(e,this.array)),this.data.array[t*this.data.stride+this.offset+1]=e,this}setZ(t,e){return this.normalized&&(e=me(e,this.array)),this.data.array[t*this.data.stride+this.offset+2]=e,this}setW(t,e){return this.normalized&&(e=me(e,this.array)),this.data.array[t*this.data.stride+this.offset+3]=e,this}getX(t){let e=this.data.array[t*this.data.stride+this.offset];return this.normalized&&(e=Bn(e,this.array)),e}getY(t){let e=this.data.array[t*this.data.stride+this.offset+1];return this.normalized&&(e=Bn(e,this.array)),e}getZ(t){let e=this.data.array[t*this.data.stride+this.offset+2];return this.normalized&&(e=Bn(e,this.array)),e}getW(t){let e=this.data.array[t*this.data.stride+this.offset+3];return this.normalized&&(e=Bn(e,this.array)),e}setXY(t,e,n){return t=t*this.data.stride+this.offset,this.normalized&&(e=me(e,this.array),n=me(n,this.array)),this.data.array[t+0]=e,this.data.array[t+1]=n,this}setXYZ(t,e,n,i){return t=t*this.data.stride+this.offset,this.normalized&&(e=me(e,this.array),n=me(n,this.array),i=me(i,this.array)),this.data.array[t+0]=e,this.data.array[t+1]=n,this.data.array[t+2]=i,this}setXYZW(t,e,n,i,r){return t=t*this.data.stride+this.offset,this.normalized&&(e=me(e,this.array),n=me(n,this.array),i=me(i,this.array),r=me(r,this.array)),this.data.array[t+0]=e,this.data.array[t+1]=n,this.data.array[t+2]=i,this.data.array[t+3]=r,this}clone(t){if(t===void 0){mr("InterleavedBufferAttribute.clone(): Cloning an interleaved buffer attribute will de-interleave buffer data.");let e=[];for(let n=0;n<this.count;n++){let i=n*this.data.stride+this.offset;for(let r=0;r<this.itemSize;r++)e.push(this.data.array[i+r])}return new ke(new this.array.constructor(e),this.itemSize,this.normalized)}else return t.interleavedBuffers===void 0&&(t.interleavedBuffers={}),t.interleavedBuffers[this.data.uuid]===void 0&&(t.interleavedBuffers[this.data.uuid]=this.data.clone(t)),new s(t.interleavedBuffers[this.data.uuid],this.itemSize,this.offset,this.normalized)}toJSON(t){if(t===void 0){mr("InterleavedBufferAttribute.toJSON(): Serializing an interleaved buffer attribute will de-interleave buffer data.");let e=[];for(let n=0;n<this.count;n++){let i=n*this.data.stride+this.offset;for(let r=0;r<this.itemSize;r++)e.push(this.data.array[i+r])}return{itemSize:this.itemSize,type:this.array.constructor.name,array:e,normalized:this.normalized}}else return t.interleavedBuffers===void 0&&(t.interleavedBuffers={}),t.interleavedBuffers[this.data.uuid]===void 0&&(t.interleavedBuffers[this.data.uuid]=this.data.toJSON(t)),{isInterleavedBufferAttribute:!0,itemSize:this.itemSize,data:this.data.uuid,offset:this.offset,normalized:this.normalized}}},nc=new D,_f=new D,yf=new $t,Rn=class{constructor(t=new D(1,0,0),e=0){this.isPlane=!0,this.normal=t,this.constant=e}set(t,e){return this.normal.copy(t),this.constant=e,this}setComponents(t,e,n,i){return this.normal.set(t,e,n),this.constant=i,this}setFromNormalAndCoplanarPoint(t,e){return this.normal.copy(t),this.constant=-e.dot(this.normal),this}setFromCoplanarPoints(t,e,n){let i=nc.subVectors(n,e).cross(_f.subVectors(t,e)).normalize();return this.setFromNormalAndCoplanarPoint(i,t),this}copy(t){return this.normal.copy(t.normal),this.constant=t.constant,this}normalize(){let t=1/this.normal.length();return this.normal.multiplyScalar(t),this.constant*=t,this}negate(){return this.constant*=-1,this.normal.negate(),this}distanceToPoint(t){return this.normal.dot(t)+this.constant}distanceToSphere(t){return this.distanceToPoint(t.center)-t.radius}projectPoint(t,e){return e.copy(t).addScaledVector(this.normal,-this.distanceToPoint(t))}intersectLine(t,e,n=!0){let i=t.delta(nc),r=this.normal.dot(i);if(r===0)return this.distanceToPoint(t.start)===0?e.copy(t.start):null;let a=-(t.start.dot(this.normal)+this.constant)/r;return n===!0&&(a<0||a>1)?null:e.copy(t.start).addScaledVector(i,a)}intersectsLine(t){let e=this.distanceToPoint(t.start),n=this.distanceToPoint(t.end);return e<0&&n>0||n<0&&e>0}intersectsBox(t){return t.intersectsPlane(this)}intersectsSphere(t){return t.intersectsPlane(this)}coplanarPoint(t){return t.copy(this.normal).multiplyScalar(-this.constant)}applyMatrix4(t,e){let n=e||yf.getNormalMatrix(t),i=this.coplanarPoint(nc).applyMatrix4(t),r=this.normal.applyMatrix3(n).normalize();return this.constant=-i.dot(r),this}translate(t){return this.constant-=t.dot(this.normal),this}equals(t){return t.normal.equals(this.normal)&&t.constant===this.constant}clone(){return new this.constructor().copy(this)}toJSON(){return{normal:this.normal.toArray(),constant:this.constant}}fromJSON(t){return this.normal.fromArray(t.normal),this.constant=t.constant,this}},vf=0,Wn=class extends Vn{constructor(){super(),this.isMaterial=!0,Object.defineProperty(this,"id",{value:vf++}),this.uuid=ti(),this.name="",this.type="Material",this.blending=Bs,this.side=wi,this.vertexColors=!1,this.opacity=1,this.transparent=!1,this.alphaHash=!1,this.blendSrc=Tc,this.blendDst=Ac,this.blendEquation=Zi,this.blendSrcAlpha=null,this.blendDstAlpha=null,this.blendEquationAlpha=null,this.blendColor=new Ft(0,0,0),this.blendAlpha=0,this.depthFunc=bs,this.depthTest=!0,this.depthWrite=!0,this.stencilWriteMask=255,this.stencilFunc=Pu,this.stencilRef=0,this.stencilFuncMask=255,this.stencilFail=Va,this.stencilZFail=Va,this.stencilZPass=Va,this.stencilWrite=!1,this.clippingPlanes=null,this.clipIntersection=!1,this.clipShadows=!1,this.shadowSide=null,this.colorWrite=!0,this.precision=null,this.polygonOffset=!1,this.polygonOffsetFactor=0,this.polygonOffsetUnits=0,this.dithering=!1,this.alphaToCoverage=!1,this.premultipliedAlpha=!1,this.forceSinglePass=!1,this.allowOverride=!0,this.visible=!0,this.toneMapped=!0,this.userData={},this.version=0,this._alphaTest=0}get alphaTest(){return this._alphaTest}set alphaTest(t){this._alphaTest>0!=t>0&&this.version++,this._alphaTest=t}onBeforeRender(){}onBeforeCompile(){}customProgramCacheKey(){return this.onBeforeCompile.toString()}setValues(t){if(t!==void 0)for(let e in t){let n=t[e];if(n===void 0){qt(`Material: parameter '${e}' has value of undefined.`);continue}let i=this[e];if(i===void 0){qt(`Material: '${e}' is not a property of THREE.${this.type}.`);continue}i&&i.isColor?i.set(n):i&&i.isVector2&&n&&n.isVector2||i&&i.isEuler&&n&&n.isEuler||i&&i.isVector3&&n&&n.isVector3?i.copy(n):this[e]=n}}toJSON(t){let e=t===void 0||typeof t=="string";e&&(t={textures:{},images:{}});let n={metadata:{version:4.7,type:"Material",generator:"Material.toJSON"}};n.uuid=this.uuid,n.type=this.type,n.blending=this.blending,n.side=this.side,n.shadowSide=this.shadowSide,n.vertexColors=this.vertexColors,n.opacity=this.opacity,n.transparent=this.transparent,n.blendSrc=this.blendSrc,n.blendDst=this.blendDst,n.blendEquation=this.blendEquation,n.blendSrcAlpha=this.blendSrcAlpha,n.blendDstAlpha=this.blendDstAlpha,n.blendEquationAlpha=this.blendEquationAlpha,n.blendColor=this.blendColor.getHex(),n.blendAlpha=this.blendAlpha,n.depthFunc=this.depthFunc,n.depthTest=this.depthTest,n.depthWrite=this.depthWrite,n.colorWrite=this.colorWrite,n.clipIntersection=this.clipIntersection,n.clipShadows=this.clipShadows,n.stencilWriteMask=this.stencilWriteMask,n.stencilFunc=this.stencilFunc,n.stencilRef=this.stencilRef,n.stencilFuncMask=this.stencilFuncMask,n.stencilFail=this.stencilFail,n.stencilZFail=this.stencilZFail,n.stencilZPass=this.stencilZPass,n.stencilWrite=this.stencilWrite,n.polygonOffset=this.polygonOffset,n.polygonOffsetFactor=this.polygonOffsetFactor,n.polygonOffsetUnits=this.polygonOffsetUnits,n.dithering=this.dithering,n.alphaTest=this.alphaTest,n.alphaHash=this.alphaHash,n.alphaToCoverage=this.alphaToCoverage,n.premultipliedAlpha=this.premultipliedAlpha,n.forceSinglePass=this.forceSinglePass,n.allowOverride=this.allowOverride,n.visible=this.visible,n.toneMapped=this.toneMapped,n.name=this.name,this.color&&this.color.isColor&&(n.color=this.color.getHex()),this.roughness!==void 0&&(n.roughness=this.roughness),this.metalness!==void 0&&(n.metalness=this.metalness),this.sheen!==void 0&&(n.sheen=this.sheen),this.sheenColor&&this.sheenColor.isColor&&(n.sheenColor=this.sheenColor.getHex()),this.sheenRoughness!==void 0&&(n.sheenRoughness=this.sheenRoughness),this.emissive&&this.emissive.isColor&&(n.emissive=this.emissive.getHex()),this.emissiveIntensity!==void 0&&(n.emissiveIntensity=this.emissiveIntensity),this.specular&&this.specular.isColor&&(n.specular=this.specular.getHex()),this.specularIntensity!==void 0&&(n.specularIntensity=this.specularIntensity),this.specularColor&&this.specularColor.isColor&&(n.specularColor=this.specularColor.getHex()),this.shininess!==void 0&&(n.shininess=this.shininess),this.clearcoat!==void 0&&(n.clearcoat=this.clearcoat),this.clearcoatRoughness!==void 0&&(n.clearcoatRoughness=this.clearcoatRoughness),this.clearcoatMap&&this.clearcoatMap.isTexture&&(n.clearcoatMap=this.clearcoatMap.toJSON(t).uuid),this.clearcoatRoughnessMap&&this.clearcoatRoughnessMap.isTexture&&(n.clearcoatRoughnessMap=this.clearcoatRoughnessMap.toJSON(t).uuid),this.clearcoatNormalMap&&this.clearcoatNormalMap.isTexture&&(n.clearcoatNormalMap=this.clearcoatNormalMap.toJSON(t).uuid,n.clearcoatNormalScale=this.clearcoatNormalScale.toArray()),this.sheenColorMap&&this.sheenColorMap.isTexture&&(n.sheenColorMap=this.sheenColorMap.toJSON(t).uuid),this.sheenRoughnessMap&&this.sheenRoughnessMap.isTexture&&(n.sheenRoughnessMap=this.sheenRoughnessMap.toJSON(t).uuid),this.dispersion!==void 0&&(n.dispersion=this.dispersion),this.retroreflectivity!==void 0&&(n.retroreflectivity=this.retroreflectivity),this.iridescence!==void 0&&(n.iridescence=this.iridescence),this.iridescenceIOR!==void 0&&(n.iridescenceIOR=this.iridescenceIOR),this.iridescenceThicknessRange!==void 0&&(n.iridescenceThicknessRange=this.iridescenceThicknessRange),this.iridescenceMap&&this.iridescenceMap.isTexture&&(n.iridescenceMap=this.iridescenceMap.toJSON(t).uuid),this.iridescenceThicknessMap&&this.iridescenceThicknessMap.isTexture&&(n.iridescenceThicknessMap=this.iridescenceThicknessMap.toJSON(t).uuid),this.anisotropy!==void 0&&(n.anisotropy=this.anisotropy),this.anisotropyRotation!==void 0&&(n.anisotropyRotation=this.anisotropyRotation),this.anisotropyMap&&this.anisotropyMap.isTexture&&(n.anisotropyMap=this.anisotropyMap.toJSON(t).uuid),this.map&&this.map.isTexture&&(n.map=this.map.toJSON(t).uuid),this.matcap&&this.matcap.isTexture&&(n.matcap=this.matcap.toJSON(t).uuid),this.alphaMap&&this.alphaMap.isTexture&&(n.alphaMap=this.alphaMap.toJSON(t).uuid),this.lightMap&&this.lightMap.isTexture&&(n.lightMap=this.lightMap.toJSON(t).uuid,n.lightMapIntensity=this.lightMapIntensity),this.aoMap&&this.aoMap.isTexture&&(n.aoMap=this.aoMap.toJSON(t).uuid,n.aoMapIntensity=this.aoMapIntensity),this.bumpMap&&this.bumpMap.isTexture&&(n.bumpMap=this.bumpMap.toJSON(t).uuid,n.bumpScale=this.bumpScale),this.normalMap&&this.normalMap.isTexture&&(n.normalMap=this.normalMap.toJSON(t).uuid,n.normalMapType=this.normalMapType,n.normalScale=this.normalScale.toArray()),this.displacementMap&&this.displacementMap.isTexture&&(n.displacementMap=this.displacementMap.toJSON(t).uuid,n.displacementScale=this.displacementScale,n.displacementBias=this.displacementBias),this.roughnessMap&&this.roughnessMap.isTexture&&(n.roughnessMap=this.roughnessMap.toJSON(t).uuid),this.metalnessMap&&this.metalnessMap.isTexture&&(n.metalnessMap=this.metalnessMap.toJSON(t).uuid),this.emissiveMap&&this.emissiveMap.isTexture&&(n.emissiveMap=this.emissiveMap.toJSON(t).uuid),this.specularMap&&this.specularMap.isTexture&&(n.specularMap=this.specularMap.toJSON(t).uuid),this.specularIntensityMap&&this.specularIntensityMap.isTexture&&(n.specularIntensityMap=this.specularIntensityMap.toJSON(t).uuid),this.specularColorMap&&this.specularColorMap.isTexture&&(n.specularColorMap=this.specularColorMap.toJSON(t).uuid),this.envMap&&this.envMap.isTexture&&(n.envMap=this.envMap.toJSON(t).uuid,this.combine!==void 0&&(n.combine=this.combine)),this.envMapRotation!==void 0&&(n.envMapRotation=this.envMapRotation.toArray()),this.envMapIntensity!==void 0&&(n.envMapIntensity=this.envMapIntensity),this.reflectivity!==void 0&&(n.reflectivity=this.reflectivity),this.refractionRatio!==void 0&&(n.refractionRatio=this.refractionRatio),this.gradientMap&&this.gradientMap.isTexture&&(n.gradientMap=this.gradientMap.toJSON(t).uuid),this.transmission!==void 0&&(n.transmission=this.transmission),this.transmissionMap&&this.transmissionMap.isTexture&&(n.transmissionMap=this.transmissionMap.toJSON(t).uuid),this.thickness!==void 0&&(n.thickness=this.thickness),this.thicknessMap&&this.thicknessMap.isTexture&&(n.thicknessMap=this.thicknessMap.toJSON(t).uuid),this.attenuationDistance!==void 0&&(n.attenuationDistance=this.attenuationDistance),this.attenuationColor!==void 0&&(n.attenuationColor=this.attenuationColor.getHex()),this.size!==void 0&&(n.size=this.size),this.sizeAttenuation!==void 0&&(n.sizeAttenuation=this.sizeAttenuation),Array.isArray(this.clippingPlanes)&&this.clippingPlanes.length>0&&(n.clippingPlanes=this.clippingPlanes.map(r=>r.toJSON())),this.rotation!==void 0&&(n.rotation=this.rotation),this.depthPacking!==void 0&&(n.depthPacking=this.depthPacking),this.linewidth!==void 0&&(n.linewidth=this.linewidth),this.linecap!==void 0&&(n.linecap=this.linecap),this.linejoin!==void 0&&(n.linejoin=this.linejoin),this.dashSize!==void 0&&(n.dashSize=this.dashSize),this.gapSize!==void 0&&(n.gapSize=this.gapSize),this.scale!==void 0&&(n.scale=this.scale),this.wireframe!==void 0&&(n.wireframe=this.wireframe),this.wireframeLinewidth!==void 0&&(n.wireframeLinewidth=this.wireframeLinewidth),this.wireframeLinecap!==void 0&&(n.wireframeLinecap=this.wireframeLinecap),this.wireframeLinejoin!==void 0&&(n.wireframeLinejoin=this.wireframeLinejoin),this.flatShading!==void 0&&(n.flatShading=this.flatShading),this.fog!==void 0&&(n.fog=this.fog),Object.keys(this.userData).length>0&&(n.userData=this.userData);function i(r){let a=[];for(let o in r){let l=r[o];delete l.metadata,a.push(l)}return a}if(e){let r=i(t.textures),a=i(t.images);r.length>0&&(n.textures=r),a.length>0&&(n.images=a)}return n}fromJSON(t,e){if(t.uuid!==void 0&&(this.uuid=t.uuid),t.name!==void 0&&(this.name=t.name),t.color!==void 0&&this.color!==void 0&&this.color.setHex(t.color),t.roughness!==void 0&&(this.roughness=t.roughness),t.metalness!==void 0&&(this.metalness=t.metalness),t.sheen!==void 0&&(this.sheen=t.sheen),t.sheenColor!==void 0&&(this.sheenColor=new Ft().setHex(t.sheenColor)),t.sheenRoughness!==void 0&&(this.sheenRoughness=t.sheenRoughness),t.emissive!==void 0&&this.emissive!==void 0&&this.emissive.setHex(t.emissive),t.specular!==void 0&&this.specular!==void 0&&this.specular.setHex(t.specular),t.specularIntensity!==void 0&&(this.specularIntensity=t.specularIntensity),t.specularColor!==void 0&&this.specularColor!==void 0&&this.specularColor.setHex(t.specularColor),t.shininess!==void 0&&(this.shininess=t.shininess),t.clearcoat!==void 0&&(this.clearcoat=t.clearcoat),t.clearcoatRoughness!==void 0&&(this.clearcoatRoughness=t.clearcoatRoughness),t.dispersion!==void 0&&(this.dispersion=t.dispersion),t.retroreflectivity!==void 0&&(this.retroreflectivity=t.retroreflectivity),t.iridescence!==void 0&&(this.iridescence=t.iridescence),t.iridescenceIOR!==void 0&&(this.iridescenceIOR=t.iridescenceIOR),t.iridescenceThicknessRange!==void 0&&(this.iridescenceThicknessRange=t.iridescenceThicknessRange),t.transmission!==void 0&&(this.transmission=t.transmission),t.thickness!==void 0&&(this.thickness=t.thickness),t.attenuationDistance!==void 0&&(this.attenuationDistance=t.attenuationDistance),t.attenuationColor!==void 0&&this.attenuationColor!==void 0&&this.attenuationColor.setHex(t.attenuationColor),t.anisotropy!==void 0&&(this.anisotropy=t.anisotropy),t.anisotropyRotation!==void 0&&(this.anisotropyRotation=t.anisotropyRotation),t.fog!==void 0&&(this.fog=t.fog),t.flatShading!==void 0&&(this.flatShading=t.flatShading),t.blending!==void 0&&(this.blending=t.blending),t.combine!==void 0&&(this.combine=t.combine),t.side!==void 0&&(this.side=t.side),t.shadowSide!==void 0&&(this.shadowSide=t.shadowSide),t.opacity!==void 0&&(this.opacity=t.opacity),t.transparent!==void 0&&(this.transparent=t.transparent),t.alphaTest!==void 0&&(this.alphaTest=t.alphaTest),t.alphaHash!==void 0&&(this.alphaHash=t.alphaHash),t.depthFunc!==void 0&&(this.depthFunc=t.depthFunc),t.depthTest!==void 0&&(this.depthTest=t.depthTest),t.depthWrite!==void 0&&(this.depthWrite=t.depthWrite),t.colorWrite!==void 0&&(this.colorWrite=t.colorWrite),t.clippingPlanes!==void 0&&(this.clippingPlanes=t.clippingPlanes.map(n=>new Rn().fromJSON(n))),t.clipIntersection!==void 0&&(this.clipIntersection=t.clipIntersection),t.clipShadows!==void 0&&(this.clipShadows=t.clipShadows),t.depthPacking!==void 0&&(this.depthPacking=t.depthPacking),t.blendSrc!==void 0&&(this.blendSrc=t.blendSrc),t.blendDst!==void 0&&(this.blendDst=t.blendDst),t.blendEquation!==void 0&&(this.blendEquation=t.blendEquation),t.blendSrcAlpha!==void 0&&(this.blendSrcAlpha=t.blendSrcAlpha),t.blendDstAlpha!==void 0&&(this.blendDstAlpha=t.blendDstAlpha),t.blendEquationAlpha!==void 0&&(this.blendEquationAlpha=t.blendEquationAlpha),t.blendColor!==void 0&&this.blendColor!==void 0&&this.blendColor.setHex(t.blendColor),t.blendAlpha!==void 0&&(this.blendAlpha=t.blendAlpha),t.stencilWriteMask!==void 0&&(this.stencilWriteMask=t.stencilWriteMask),t.stencilFunc!==void 0&&(this.stencilFunc=t.stencilFunc),t.stencilRef!==void 0&&(this.stencilRef=t.stencilRef),t.stencilFuncMask!==void 0&&(this.stencilFuncMask=t.stencilFuncMask),t.stencilFail!==void 0&&(this.stencilFail=t.stencilFail),t.stencilZFail!==void 0&&(this.stencilZFail=t.stencilZFail),t.stencilZPass!==void 0&&(this.stencilZPass=t.stencilZPass),t.stencilWrite!==void 0&&(this.stencilWrite=t.stencilWrite),t.wireframe!==void 0&&(this.wireframe=t.wireframe),t.wireframeLinewidth!==void 0&&(this.wireframeLinewidth=t.wireframeLinewidth),t.wireframeLinecap!==void 0&&(this.wireframeLinecap=t.wireframeLinecap),t.wireframeLinejoin!==void 0&&(this.wireframeLinejoin=t.wireframeLinejoin),t.rotation!==void 0&&(this.rotation=t.rotation),t.linewidth!==void 0&&(this.linewidth=t.linewidth),t.linecap!==void 0&&(this.linecap=t.linecap),t.linejoin!==void 0&&(this.linejoin=t.linejoin),t.dashSize!==void 0&&(this.dashSize=t.dashSize),t.gapSize!==void 0&&(this.gapSize=t.gapSize),t.scale!==void 0&&(this.scale=t.scale),t.polygonOffset!==void 0&&(this.polygonOffset=t.polygonOffset),t.polygonOffsetFactor!==void 0&&(this.polygonOffsetFactor=t.polygonOffsetFactor),t.polygonOffsetUnits!==void 0&&(this.polygonOffsetUnits=t.polygonOffsetUnits),t.dithering!==void 0&&(this.dithering=t.dithering),t.alphaToCoverage!==void 0&&(this.alphaToCoverage=t.alphaToCoverage),t.premultipliedAlpha!==void 0&&(this.premultipliedAlpha=t.premultipliedAlpha),t.forceSinglePass!==void 0&&(this.forceSinglePass=t.forceSinglePass),t.allowOverride!==void 0&&(this.allowOverride=t.allowOverride),t.visible!==void 0&&(this.visible=t.visible),t.toneMapped!==void 0&&(this.toneMapped=t.toneMapped),t.userData!==void 0&&(this.userData=t.userData),t.vertexColors!==void 0&&(typeof t.vertexColors=="number"?this.vertexColors=t.vertexColors>0:this.vertexColors=t.vertexColors),t.size!==void 0&&(this.size=t.size),t.sizeAttenuation!==void 0&&(this.sizeAttenuation=t.sizeAttenuation),t.map!==void 0&&(this.map=e[t.map]||null),t.matcap!==void 0&&(this.matcap=e[t.matcap]||null),t.alphaMap!==void 0&&(this.alphaMap=e[t.alphaMap]||null),t.bumpMap!==void 0&&(this.bumpMap=e[t.bumpMap]||null),t.bumpScale!==void 0&&(this.bumpScale=t.bumpScale),t.normalMap!==void 0&&(this.normalMap=e[t.normalMap]||null),t.normalMapType!==void 0&&(this.normalMapType=t.normalMapType),t.normalScale!==void 0){let n=t.normalScale;Array.isArray(n)===!1&&(n=[n,n]),this.normalScale=new rt().fromArray(n)}return t.displacementMap!==void 0&&(this.displacementMap=e[t.displacementMap]||null),t.displacementScale!==void 0&&(this.displacementScale=t.displacementScale),t.displacementBias!==void 0&&(this.displacementBias=t.displacementBias),t.roughnessMap!==void 0&&(this.roughnessMap=e[t.roughnessMap]||null),t.metalnessMap!==void 0&&(this.metalnessMap=e[t.metalnessMap]||null),t.emissiveMap!==void 0&&(this.emissiveMap=e[t.emissiveMap]||null),t.emissiveIntensity!==void 0&&(this.emissiveIntensity=t.emissiveIntensity),t.specularMap!==void 0&&(this.specularMap=e[t.specularMap]||null),t.specularIntensityMap!==void 0&&(this.specularIntensityMap=e[t.specularIntensityMap]||null),t.specularColorMap!==void 0&&(this.specularColorMap=e[t.specularColorMap]||null),t.envMap!==void 0&&(this.envMap=e[t.envMap]||null),t.envMapRotation!==void 0&&this.envMapRotation.fromArray(t.envMapRotation),t.envMapIntensity!==void 0&&(this.envMapIntensity=t.envMapIntensity),t.reflectivity!==void 0&&(this.reflectivity=t.reflectivity),t.refractionRatio!==void 0&&(this.refractionRatio=t.refractionRatio),t.lightMap!==void 0&&(this.lightMap=e[t.lightMap]||null),t.lightMapIntensity!==void 0&&(this.lightMapIntensity=t.lightMapIntensity),t.aoMap!==void 0&&(this.aoMap=e[t.aoMap]||null),t.aoMapIntensity!==void 0&&(this.aoMapIntensity=t.aoMapIntensity),t.gradientMap!==void 0&&(this.gradientMap=e[t.gradientMap]||null),t.clearcoatMap!==void 0&&(this.clearcoatMap=e[t.clearcoatMap]||null),t.clearcoatRoughnessMap!==void 0&&(this.clearcoatRoughnessMap=e[t.clearcoatRoughnessMap]||null),t.clearcoatNormalMap!==void 0&&(this.clearcoatNormalMap=e[t.clearcoatNormalMap]||null),t.clearcoatNormalScale!==void 0&&(this.clearcoatNormalScale=new rt().fromArray(t.clearcoatNormalScale)),t.iridescenceMap!==void 0&&(this.iridescenceMap=e[t.iridescenceMap]||null),t.iridescenceThicknessMap!==void 0&&(this.iridescenceThicknessMap=e[t.iridescenceThicknessMap]||null),t.transmissionMap!==void 0&&(this.transmissionMap=e[t.transmissionMap]||null),t.thicknessMap!==void 0&&(this.thicknessMap=e[t.thicknessMap]||null),t.anisotropyMap!==void 0&&(this.anisotropyMap=e[t.anisotropyMap]||null),t.sheenColorMap!==void 0&&(this.sheenColorMap=e[t.sheenColorMap]||null),t.sheenRoughnessMap!==void 0&&(this.sheenRoughnessMap=e[t.sheenRoughnessMap]||null),this}clone(){return new this.constructor().copy(this)}copy(t){this.name=t.name,this.blending=t.blending,this.side=t.side,this.vertexColors=t.vertexColors,this.opacity=t.opacity,this.transparent=t.transparent,this.blendSrc=t.blendSrc,this.blendDst=t.blendDst,this.blendEquation=t.blendEquation,this.blendSrcAlpha=t.blendSrcAlpha,this.blendDstAlpha=t.blendDstAlpha,this.blendEquationAlpha=t.blendEquationAlpha,this.blendColor.copy(t.blendColor),this.blendAlpha=t.blendAlpha,this.depthFunc=t.depthFunc,this.depthTest=t.depthTest,this.depthWrite=t.depthWrite,this.stencilWriteMask=t.stencilWriteMask,this.stencilFunc=t.stencilFunc,this.stencilRef=t.stencilRef,this.stencilFuncMask=t.stencilFuncMask,this.stencilFail=t.stencilFail,this.stencilZFail=t.stencilZFail,this.stencilZPass=t.stencilZPass,this.stencilWrite=t.stencilWrite;let e=t.clippingPlanes,n=null;if(e!==null){let i=e.length;n=new Array(i);for(let r=0;r!==i;++r)n[r]=e[r].clone()}return this.clippingPlanes=n,this.clipIntersection=t.clipIntersection,this.clipShadows=t.clipShadows,this.shadowSide=t.shadowSide,this.colorWrite=t.colorWrite,this.precision=t.precision,this.polygonOffset=t.polygonOffset,this.polygonOffsetFactor=t.polygonOffsetFactor,this.polygonOffsetUnits=t.polygonOffsetUnits,this.dithering=t.dithering,this.alphaTest=t.alphaTest,this.alphaHash=t.alphaHash,this.alphaToCoverage=t.alphaToCoverage,this.premultipliedAlpha=t.premultipliedAlpha,this.forceSinglePass=t.forceSinglePass,this.allowOverride=t.allowOverride,this.visible=t.visible,this.toneMapped=t.toneMapped,this.userData=JSON.parse(JSON.stringify(t.userData)),this}dispose(){this.dispatchEvent({type:"dispose"})}set needsUpdate(t){t===!0&&this.version++}},Cs=class extends Wn{constructor(t){super(),this.isSpriteMaterial=!0,this.type="SpriteMaterial",this.color=new Ft(16777215),this.map=null,this.alphaMap=null,this.rotation=0,this.sizeAttenuation=!0,this.transparent=!0,this.fog=!0,this.setValues(t)}copy(t){return super.copy(t),this.color.copy(t.color),this.map=t.map,this.alphaMap=t.alphaMap,this.rotation=t.rotation,this.sizeAttenuation=t.sizeAttenuation,this.fog=t.fog,this}},ps,ir=new D,ms=new D,gs=new D,xs=new rt,sr=new rt,Vu=new fe,ba=new D,rr=new D,Ea=new D,kh=new rt,ic=new rt,zh=new rt,br=class extends Re{constructor(t=new Cs){if(super(),this.isSprite=!0,this.type="Sprite",ps===void 0){ps=new xe;let e=new Float32Array([-.5,-.5,0,0,0,.5,-.5,0,1,0,.5,.5,0,1,1,-.5,.5,0,0,1]),n=new Sr(e,5);ps.setIndex([0,1,2,0,2,3]),ps.setAttribute("position",new Rs(n,3,0,!1)),ps.setAttribute("uv",new Rs(n,2,3,!1))}this.geometry=ps,this.material=t,this.center=new rt(.5,.5),this.count=1}intersectsFrustum(t){return t.intersectsSprite(this)}raycast(t,e){t.camera===null&&Xt('Sprite: "Raycaster.camera" needs to be set in order to raycast against sprites.'),ms.setFromMatrixScale(this.matrixWorld),Vu.copy(t.camera.matrixWorld),this.modelViewMatrix.multiplyMatrices(t.camera.matrixWorldInverse,this.matrixWorld),gs.setFromMatrixPosition(this.modelViewMatrix),t.camera.isPerspectiveCamera&&this.material.sizeAttenuation===!1&&ms.multiplyScalar(-gs.z);let n=this.material.rotation,i,r;n!==0&&(r=Math.cos(n),i=Math.sin(n));let a=this.center;wa(ba.set(-.5,-.5,0),gs,a,ms,i,r),wa(rr.set(.5,-.5,0),gs,a,ms,i,r),wa(Ea.set(.5,.5,0),gs,a,ms,i,r),kh.set(0,0),ic.set(1,0),zh.set(1,1);let o=t.ray.intersectTriangle(ba,rr,Ea,!1,ir);if(o===null&&(wa(rr.set(-.5,.5,0),gs,a,ms,i,r),ic.set(0,1),o=t.ray.intersectTriangle(ba,Ea,rr,!1,ir),o===null))return;let l=t.ray.origin.distanceTo(ir);l<t.near||l>t.far||e.push({distance:l,point:ir.clone(),uv:jn.getInterpolation(ir,ba,rr,Ea,kh,ic,zh,new rt),face:null,object:this})}copy(t,e){return super.copy(t,e),t.center!==void 0&&this.center.copy(t.center),this.material=t.material,this}};function wa(s,t,e,n,i,r){xs.subVectors(s,e).addScalar(.5).multiply(n),i!==void 0?(sr.x=r*xs.x-i*xs.y,sr.y=i*xs.x+r*xs.y):sr.copy(xs),s.copy(t),s.x+=sr.x,s.y+=sr.y,s.applyMatrix4(Vu)}var Qn=new D,sc=new D,Ta=new D,Aa=new D,eo=class{constructor(t=new D,e=new D(0,0,-1)){this.origin=t,this.direction=e}set(t,e){return this.origin.copy(t),this.direction.copy(e),this}copy(t){return this.origin.copy(t.origin),this.direction.copy(t.direction),this}at(t,e){return e.copy(this.origin).addScaledVector(this.direction,t)}lookAt(t){return this.direction.copy(t).sub(this.origin).normalize(),this}recast(t){return this.origin.copy(this.at(t,Qn)),this}closestPointToPoint(t,e){e.subVectors(t,this.origin);let n=e.dot(this.direction);return n<0?e.copy(this.origin):e.copy(this.origin).addScaledVector(this.direction,n)}distanceToPoint(t){return Math.sqrt(this.distanceSqToPoint(t))}distanceSqToPoint(t){let e=Qn.subVectors(t,this.origin).dot(this.direction);return e<0?this.origin.distanceToSquared(t):(Qn.copy(this.origin).addScaledVector(this.direction,e),Qn.distanceToSquared(t))}distanceSqToSegment(t,e,n,i){sc.copy(t).add(e).multiplyScalar(.5),Ta.copy(e).sub(t).normalize(),Aa.copy(this.origin).sub(sc);let r=t.distanceTo(e)*.5,a=-this.direction.dot(Ta),o=Aa.dot(this.direction),l=-Aa.dot(Ta),c=Aa.lengthSq(),h=Math.abs(1-a*a),u,d,f,p;if(h>0)if(u=a*l-o,d=a*o-l,p=r*h,u>=0)if(d>=-p)if(d<=p){let y=1/h;u*=y,d*=y,f=u*(u+a*d+2*o)+d*(a*u+d+2*l)+c}else d=r,u=Math.max(0,-(a*d+o)),f=-u*u+d*(d+2*l)+c;else d=-r,u=Math.max(0,-(a*d+o)),f=-u*u+d*(d+2*l)+c;else d<=-p?(u=Math.max(0,-(-a*r+o)),d=u>0?-r:Math.min(Math.max(-r,-l),r),f=-u*u+d*(d+2*l)+c):d<=p?(u=0,d=Math.min(Math.max(-r,-l),r),f=d*(d+2*l)+c):(u=Math.max(0,-(a*r+o)),d=u>0?r:Math.min(Math.max(-r,-l),r),f=-u*u+d*(d+2*l)+c);else d=a>0?-r:r,u=Math.max(0,-(a*d+o)),f=-u*u+d*(d+2*l)+c;return n&&n.copy(this.origin).addScaledVector(this.direction,u),i&&i.copy(sc).addScaledVector(Ta,d),f}intersectSphere(t,e){if(t.radius<0)return null;Qn.subVectors(t.center,this.origin);let n=Qn.dot(this.direction),i=Qn.dot(Qn)-n*n,r=t.radius*t.radius;if(i>r)return null;let a=Math.sqrt(r-i),o=n-a,l=n+a;return l<0?null:o<0?this.at(l,e):this.at(o,e)}intersectsSphere(t){return t.radius<0?!1:this.distanceSqToPoint(t.center)<=t.radius*t.radius}distanceToPlane(t){let e=t.normal.dot(this.direction);if(e===0)return t.distanceToPoint(this.origin)===0?0:null;let n=-(this.origin.dot(t.normal)+t.constant)/e;return n>=0?n:null}intersectPlane(t,e){let n=this.distanceToPlane(t);return n===null?null:this.at(n,e)}intersectsPlane(t){let e=t.distanceToPoint(this.origin);return e===0||t.normal.dot(this.direction)*e<0}intersectBox(t,e){let n,i,r,a,o,l,c=1/this.direction.x,h=1/this.direction.y,u=1/this.direction.z,d=this.origin;return c>=0?(n=(t.min.x-d.x)*c,i=(t.max.x-d.x)*c):(n=(t.max.x-d.x)*c,i=(t.min.x-d.x)*c),h>=0?(r=(t.min.y-d.y)*h,a=(t.max.y-d.y)*h):(r=(t.max.y-d.y)*h,a=(t.min.y-d.y)*h),n>a||r>i||((r>n||isNaN(n))&&(n=r),(a<i||isNaN(i))&&(i=a),u>=0?(o=(t.min.z-d.z)*u,l=(t.max.z-d.z)*u):(o=(t.max.z-d.z)*u,l=(t.min.z-d.z)*u),n>l||o>i)||((o>n||n!==n)&&(n=o),(l<i||i!==i)&&(i=l),i<0)?null:this.at(n>=0?n:i,e)}intersectsBox(t){return this.intersectBox(t,Qn)!==null}intersectTriangle(t,e,n,i,r){let a=this.origin,o=this.direction,l=o.x,c=o.y,h=o.z,u=t.x-a.x,d=t.y-a.y,f=t.z-a.z,p=e.x-a.x,y=e.y-a.y,g=e.z-a.z,m=n.x-a.x,S=n.y-a.y,T=n.z-a.z,v=Math.abs(l),b=Math.abs(c),w=Math.abs(h),P,_,E,C,I,U,k,L,B,W,X,st;if(v>=b&&v>=w?(E=l,U=u,B=p,st=m,l>=0?(P=c,_=h,C=d,I=f,k=y,L=g,W=S,X=T):(P=h,_=c,C=f,I=d,k=g,L=y,W=T,X=S)):b>=w?(E=c,U=d,B=y,st=S,c>=0?(P=h,_=l,C=f,I=u,k=g,L=p,W=T,X=m):(P=l,_=h,C=u,I=f,k=p,L=g,W=m,X=T)):(E=h,U=f,B=g,st=T,h>=0?(P=l,_=c,C=u,I=d,k=p,L=y,W=m,X=S):(P=c,_=l,C=d,I=u,k=y,L=p,W=S,X=m)),E===0)return null;let Z=P/E,Q=_/E,it=1/E,nt=C-Z*U,ct=I-Q*U,Yt=k-Z*B,Jt=L-Q*B,Qt=W-Z*st,J=X-Q*st,$=Qt*Jt-J*Yt,mt=nt*J-ct*Qt,Lt=Yt*ct-Jt*nt;if(i){if($<0||mt<0||Lt<0)return null}else if(($<0||mt<0||Lt<0)&&($>0||mt>0||Lt>0))return null;let gt=$+mt+Lt;if(gt===0)return null;let Bt=it*($*U+mt*B+Lt*st);return(gt>0?Bt<0:Bt>0)?null:this.at(Bt/gt,r)}applyMatrix4(t){return this.origin.applyMatrix4(t),this.direction.transformDirection(t),this}equals(t){return t.origin.equals(this.origin)&&t.direction.equals(this.direction)}clone(){return new this.constructor().copy(this)}},$e=class extends Wn{constructor(t){super(),this.isMeshBasicMaterial=!0,this.type="MeshBasicMaterial",this.color=new Ft(16777215),this.map=null,this.lightMap=null,this.lightMapIntensity=1,this.aoMap=null,this.aoMapIntensity=1,this.specularMap=null,this.alphaMap=null,this.envMap=null,this.envMapRotation=new mn,this.combine=Eo,this.reflectivity=1,this.refractionRatio=.98,this.wireframe=!1,this.wireframeLinewidth=1,this.wireframeLinecap="round",this.wireframeLinejoin="round",this.fog=!0,this.setValues(t)}copy(t){return super.copy(t),this.color.copy(t.color),this.map=t.map,this.lightMap=t.lightMap,this.lightMapIntensity=t.lightMapIntensity,this.aoMap=t.aoMap,this.aoMapIntensity=t.aoMapIntensity,this.specularMap=t.specularMap,this.alphaMap=t.alphaMap,this.envMap=t.envMap,this.envMapRotation.copy(t.envMapRotation),this.combine=t.combine,this.reflectivity=t.reflectivity,this.refractionRatio=t.refractionRatio,this.wireframe=t.wireframe,this.wireframeLinewidth=t.wireframeLinewidth,this.wireframeLinecap=t.wireframeLinecap,this.wireframeLinejoin=t.wireframeLinejoin,this.fog=t.fog,this}},Hh=new fe,Bi=new eo,Ra=new gi,Vh=new D,Ca=new D,Pa=new D,Ia=new D,rc=new D,La=new D,Gh=new D,Da=new D,ut=class extends Re{constructor(t=new xe,e=new $e){super(),this.isMesh=!0,this.type="Mesh",this.geometry=t,this.material=e,this.morphTargetDictionary=void 0,this.morphTargetInfluences=void 0,this.count=1,this.updateMorphTargets()}copy(t,e){return super.copy(t,e),t.morphTargetInfluences!==void 0&&(this.morphTargetInfluences=t.morphTargetInfluences.slice()),t.morphTargetDictionary!==void 0&&(this.morphTargetDictionary=Object.assign({},t.morphTargetDictionary)),this.material=Array.isArray(t.material)?t.material.slice():t.material,this.geometry=t.geometry,this}updateMorphTargets(){let e=this.geometry.morphAttributes,n=Object.keys(e);if(n.length>0){let i=e[n[0]];if(i!==void 0){this.morphTargetInfluences=[],this.morphTargetDictionary={};for(let r=0,a=i.length;r<a;r++){let o=i[r].name||String(r);this.morphTargetInfluences.push(0),this.morphTargetDictionary[o]=r}}}}getVertexPosition(t,e){let n=this.geometry,i=n.attributes.position,r=n.morphAttributes.position,a=n.morphTargetsRelative;e.fromBufferAttribute(i,t);let o=this.morphTargetInfluences;if(r&&o){La.set(0,0,0);for(let l=0,c=r.length;l<c;l++){let h=o[l],u=r[l];h!==0&&(rc.fromBufferAttribute(u,t),a?La.addScaledVector(rc,h):La.addScaledVector(rc.sub(e),h))}e.add(La)}return e}intersectsFrustum(t){return t.intersectsObject(this)}raycast(t,e){let n=this.geometry,i=this.material,r=this.matrixWorld;i!==void 0&&(n.boundingSphere===null&&n.computeBoundingSphere(),Ra.copy(n.boundingSphere),Ra.applyMatrix4(r),Bi.copy(t.ray).recast(t.near),!(Ra.containsPoint(Bi.origin)===!1&&(Bi.intersectSphere(Ra,Vh)===null||Bi.origin.distanceToSquared(Vh)>(t.far-t.near)**2))&&(Hh.copy(r).invert(),Bi.copy(t.ray).applyMatrix4(Hh),!(n.boundingBox!==null&&Bi.intersectsBox(n.boundingBox)===!1)&&this._computeIntersections(t,e,Bi)))}_computeIntersections(t,e,n){let i,r=this.geometry,a=this.material,o=r.index,l=r.attributes.position,c=r.attributes.uv,h=r.attributes.uv1,u=r.attributes.normal,d=r.groups,f=r.drawRange;if(o!==null)if(Array.isArray(a))for(let p=0,y=d.length;p<y;p++){let g=d[p],m=a[g.materialIndex],S=Math.max(g.start,f.start),T=Math.min(o.count,Math.min(g.start+g.count,f.start+f.count));for(let v=S,b=T;v<b;v+=3){let w=o.getX(v),P=o.getX(v+1),_=o.getX(v+2);i=Ua(this,m,t,n,c,h,u,w,P,_),i&&(i.faceIndex=Math.floor(v/3),i.face.materialIndex=g.materialIndex,e.push(i))}}else{let p=Math.max(0,f.start),y=Math.min(o.count,f.start+f.count);for(let g=p,m=y;g<m;g+=3){let S=o.getX(g),T=o.getX(g+1),v=o.getX(g+2);i=Ua(this,a,t,n,c,h,u,S,T,v),i&&(i.faceIndex=Math.floor(g/3),e.push(i))}}else if(l!==void 0)if(Array.isArray(a))for(let p=0,y=d.length;p<y;p++){let g=d[p],m=a[g.materialIndex],S=Math.max(g.start,f.start),T=Math.min(l.count,Math.min(g.start+g.count,f.start+f.count));for(let v=S,b=T;v<b;v+=3){let w=v,P=v+1,_=v+2;i=Ua(this,m,t,n,c,h,u,w,P,_),i&&(i.faceIndex=Math.floor(v/3),i.face.materialIndex=g.materialIndex,e.push(i))}}else{let p=Math.max(0,f.start),y=Math.min(l.count,f.start+f.count);for(let g=p,m=y;g<m;g+=3){let S=g,T=g+1,v=g+2;i=Ua(this,a,t,n,c,h,u,S,T,v),i&&(i.faceIndex=Math.floor(g/3),e.push(i))}}}};function Mf(s,t,e,n,i,r,a,o){let l;if(t.side===We?l=n.intersectTriangle(a,r,i,!0,o):l=n.intersectTriangle(i,r,a,t.side===wi,o),l===null)return null;Da.copy(o),Da.applyMatrix4(s.matrixWorld);let c=e.ray.origin.distanceTo(Da);return c<e.near||c>e.far?null:{distance:c,point:Da.clone(),object:s}}function Ua(s,t,e,n,i,r,a,o,l,c){s.getVertexPosition(o,Ca),s.getVertexPosition(l,Pa),s.getVertexPosition(c,Ia);let h=Mf(s,t,e,n,Ca,Pa,Ia,Gh);if(h){let u=new D;jn.getBarycoord(Gh,Ca,Pa,Ia,u),i&&(h.uv=jn.getInterpolatedAttribute(i,o,l,c,u,new rt)),r&&(h.uv1=jn.getInterpolatedAttribute(r,o,l,c,u,new rt)),a&&(h.normal=jn.getInterpolatedAttribute(a,o,l,c,u,new D),h.normal.dot(n.direction)>0&&h.normal.multiplyScalar(-1));let d={a:o,b:l,c,normal:new D,materialIndex:0};jn.getNormal(Ca,Pa,Ia,d.normal),h.face=d,h.barycoord=u}return h}var Er=class extends nn{constructor(t=null,e=1,n=1,i,r,a,o,l,c=ze,h=ze,u,d){super(null,a,o,l,c,h,i,r,u,d),this.isDataTexture=!0,this.image={data:t,width:e,height:n},this.generateMipmaps=!1,this.flipY=!1,this.unpackAlignment=1}};var xi=class extends ke{constructor(t,e,n,i=1){super(t,e,n),this.isInstancedBufferAttribute=!0,this.meshPerAttribute=i}copy(t){return super.copy(t),this.meshPerAttribute=t.meshPerAttribute,this}toJSON(){let t=super.toJSON();return t.meshPerAttribute=this.meshPerAttribute,t.isInstancedBufferAttribute=!0,t}},_s=new fe,Wh=new fe,Na=[],Xh=new Gn,Sf=new fe,ar=new ut,or=new gi,Hi=class extends ut{constructor(t,e,n){super(t,e),this.isInstancedMesh=!0,this.instanceMatrix=new xi(new Float32Array(n*16),16),this.instanceColor=null,this.morphTexture=null,this.count=n,this.boundingBox=null,this.boundingSphere=null;for(let i=0;i<n;i++)this.setMatrixAt(i,Sf)}computeBoundingBox(){let t=this.geometry,e=this.count;this.boundingBox===null&&(this.boundingBox=new Gn),t.boundingBox===null&&t.computeBoundingBox(),this.boundingBox.makeEmpty();for(let n=0;n<e;n++)this.getMatrixAt(n,_s),Xh.copy(t.boundingBox).applyMatrix4(_s),this.boundingBox.union(Xh)}computeBoundingSphere(){let t=this.geometry,e=this.count;this.boundingSphere===null&&(this.boundingSphere=new gi),t.boundingSphere===null&&t.computeBoundingSphere(),this.boundingSphere.makeEmpty();for(let n=0;n<e;n++)this.getMatrixAt(n,_s),or.copy(t.boundingSphere).applyMatrix4(_s),this.boundingSphere.union(or)}copy(t,e){return super.copy(t,e),this.instanceMatrix.copy(t.instanceMatrix),t.morphTexture!==null&&(this.morphTexture=t.morphTexture.clone()),t.instanceColor!==null&&(this.instanceColor=t.instanceColor.clone()),this.count=t.count,t.boundingBox!==null&&(this.boundingBox=t.boundingBox.clone()),t.boundingSphere!==null&&(this.boundingSphere=t.boundingSphere.clone()),this}getColorAt(t,e){return this.instanceColor===null?e.setRGB(1,1,1):e.fromArray(this.instanceColor.array,t*3)}getMatrixAt(t,e){return e.fromArray(this.instanceMatrix.array,t*16)}getMorphAt(t,e){let n=e.morphTargetInfluences,i=this.morphTexture.source.data.data,r=n.length+1,a=t*r+1;for(let o=0;o<n.length;o++)n[o]=i[a+o]}raycast(t,e){let n=this.matrixWorld,i=this.count;if(ar.geometry=this.geometry,ar.material=this.material,ar.material!==void 0&&(this.boundingSphere===null&&this.computeBoundingSphere(),or.copy(this.boundingSphere),or.applyMatrix4(n),t.ray.intersectsSphere(or)!==!1))for(let r=0;r<i;r++){this.getMatrixAt(r,_s),Wh.multiplyMatrices(n,_s),ar.matrixWorld=Wh,ar.raycast(t,Na);for(let a=0,o=Na.length;a<o;a++){let l=Na[a];l.instanceId=r,l.object=this,e.push(l)}Na.length=0}}setColorAt(t,e){return this.instanceColor===null&&(this.instanceColor=new xi(new Float32Array(this.instanceMatrix.count*3).fill(1),3)),e.toArray(this.instanceColor.array,t*3),this}setMatrixAt(t,e){return e.toArray(this.instanceMatrix.array,t*16),this}setMorphAt(t,e){let n=e.morphTargetInfluences,i=n.length+1;this.morphTexture===null&&(this.morphTexture=new Er(new Float32Array(i*this.count),i,this.count,Io,yn));let r=this.morphTexture.source.data.data,a=0;for(let c=0;c<n.length;c++)a+=n[c];let o=this.geometry.morphTargetsRelative?1:1-a,l=i*t;return r[l]=o,r.set(n,l+1),this}updateMorphTargets(){}dispose(){super.dispose(),this.morphTexture!==null&&(this.morphTexture.dispose(),this.morphTexture=null)}},Oi=new gi,bf=new rt(.5,.5),Fa=new D,Ps=class{constructor(t=new Rn,e=new Rn,n=new Rn,i=new Rn,r=new Rn,a=new Rn){this.planes=[t,e,n,i,r,a]}set(t,e,n,i,r,a){let o=this.planes;return o[0].copy(t),o[1].copy(e),o[2].copy(n),o[3].copy(i),o[4].copy(r),o[5].copy(a),this}copy(t){let e=this.planes;for(let n=0;n<6;n++)e[n].copy(t.planes[n]);return this}setFromProjectionMatrix(t,e=Cn,n=!1){let i=this.planes,r=t.elements,a=r[0],o=r[1],l=r[2],c=r[3],h=r[4],u=r[5],d=r[6],f=r[7],p=r[8],y=r[9],g=r[10],m=r[11],S=r[12],T=r[13],v=r[14],b=r[15];if(i[0].setComponents(c-a,f-h,m-p,b-S).normalize(),i[1].setComponents(c+a,f+h,m+p,b+S).normalize(),i[2].setComponents(c+o,f+u,m+y,b+T).normalize(),i[3].setComponents(c-o,f-u,m-y,b-T).normalize(),n)i[4].setComponents(l,d,g,v).normalize(),i[5].setComponents(c-l,f-d,m-g,b-v).normalize();else if(i[4].setComponents(c-l,f-d,m-g,b-v).normalize(),e===Cn)i[5].setComponents(c+l,f+d,m+g,b+v).normalize();else if(e===Es)i[5].setComponents(l,d,g,v).normalize();else throw new Error("THREE.Frustum.setFromProjectionMatrix(): Invalid coordinate system: "+e);return this}intersectsObject(t){if(t.boundingSphere!==void 0)t.boundingSphere===null&&t.computeBoundingSphere(),Oi.copy(t.boundingSphere).applyMatrix4(t.matrixWorld);else{let e=t.geometry;e.boundingSphere===null&&e.computeBoundingSphere(),Oi.copy(e.boundingSphere).applyMatrix4(t.matrixWorld)}return this.intersectsSphere(Oi)}intersectsSprite(t){Oi.center.set(0,0,0);let e=bf.distanceTo(t.center);return Oi.radius=.7071067811865476+e,Oi.applyMatrix4(t.matrixWorld),this.intersectsSphere(Oi)}intersectsSphere(t){let e=this.planes,n=t.center,i=-t.radius;for(let r=0;r<6;r++)if(e[r].distanceToPoint(n)<i)return!1;return!0}intersectsBox(t){let e=this.planes;for(let n=0;n<6;n++){let i=e[n];if(Fa.x=i.normal.x>0?t.max.x:t.min.x,Fa.y=i.normal.y>0?t.max.y:t.min.y,Fa.z=i.normal.z>0?t.max.z:t.min.z,i.distanceToPoint(Fa)<0)return!1}return!0}containsPoint(t){let e=this.planes;for(let n=0;n<6;n++)if(e[n].distanceToPoint(t)<0)return!1;return!0}clone(){return new this.constructor().copy(this)}};var wr=class extends nn{constructor(t=[],e=Ti,n,i,r,a,o,l,c,h){super(t,e,n,i,r,a,o,l,c,h),this.isCubeTexture=!0,this.flipY=!1}get images(){return this.image}set images(t){this.image=t}},Vi=class extends nn{constructor(t,e,n,i,r,a,o,l,c){super(t,e,n,i,r,a,o,l,c),this.isCanvasTexture=!0,this.needsUpdate=!0}};var _i=class extends nn{constructor(t,e,n=Dn,i,r,a,o=ze,l=ze,c,h=Hn,u=1){if(h!==Hn&&h!==Ri)throw new Error("THREE.DepthTexture: format must be either THREE.DepthFormat or THREE.DepthStencilFormat");let d={width:t,height:e,depth:u};super(d,i,r,a,o,l,h,n,c),this.isDepthTexture=!0,this.flipY=!1,this.generateMipmaps=!1,this.compareFunction=null}copy(t){return super.copy(t),this.source=new Ts(Object.assign({},t.image)),this.compareFunction=t.compareFunction,this}toJSON(t){let e=super.toJSON(t);return e.compareFunction=this.compareFunction,e}},no=class extends _i{constructor(t,e=Dn,n=Ti,i,r,a=ze,o=ze,l,c=Hn){let h={width:t,height:t,depth:1},u=[h,h,h,h,h,h];super(t,t,e,n,i,r,a,o,l,c),this.image=u,this.isCubeDepthTexture=!0,this.isCubeTexture=!0}get images(){return this.image}set images(t){this.image=t}},Tr=class extends nn{constructor(t=null){super(),this.sourceTexture=t,this.isExternalTexture=!0}copy(t){return super.copy(t),this.sourceTexture=t.sourceTexture,this}},he=class s extends xe{constructor(t=1,e=1,n=1,i=1,r=1,a=1){super(),this.type="BoxGeometry",this.parameters={width:t,height:e,depth:n,widthSegments:i,heightSegments:r,depthSegments:a};let o=this;i=Math.floor(i),r=Math.floor(r),a=Math.floor(a);let l=[],c=[],h=[],u=[],d=0,f=0;p("z","y","x",-1,-1,n,e,t,a,r,0),p("z","y","x",1,-1,n,e,-t,a,r,1),p("x","z","y",1,1,t,n,e,i,a,2),p("x","z","y",1,-1,t,n,-e,i,a,3),p("x","y","z",1,-1,t,e,n,i,r,4),p("x","y","z",-1,-1,t,e,-n,i,r,5),this.setIndex(l),this.setAttribute("position",new Zt(c,3)),this.setAttribute("normal",new Zt(h,3)),this.setAttribute("uv",new Zt(u,2));function p(y,g,m,S,T,v,b,w,P,_,E){let C=v/P,I=b/_,U=v/2,k=b/2,L=w/2,B=P+1,W=_+1,X=0,st=0,Z=new D;for(let Q=0;Q<W;Q++){let it=Q*I-k;for(let nt=0;nt<B;nt++){let ct=nt*C-U;Z[y]=ct*S,Z[g]=it*T,Z[m]=L,c.push(Z.x,Z.y,Z.z),Z[y]=0,Z[g]=0,Z[m]=w>0?1:-1,h.push(Z.x,Z.y,Z.z),u.push(nt/P),u.push(1-Q/_),X+=1}}for(let Q=0;Q<_;Q++)for(let it=0;it<P;it++){let nt=d+it+B*Q,ct=d+it+B*(Q+1),Yt=d+(it+1)+B*(Q+1),Jt=d+(it+1)+B*Q;l.push(nt,ct,Jt),l.push(ct,Yt,Jt),st+=6}o.addGroup(f,st,E),f+=st,d+=X}}copy(t){return super.copy(t),this.parameters=Object.assign({},t.parameters),this}static fromJSON(t){return new s(t.width,t.height,t.depth,t.widthSegments,t.heightSegments,t.depthSegments)}},gn=class s extends xe{constructor(t=1,e=1,n=4,i=8,r=1){super(),this.type="CapsuleGeometry",this.parameters={radius:t,height:e,capSegments:n,radialSegments:i,heightSegments:r},e=Math.max(0,e),n=Math.max(1,Math.floor(n)),i=Math.max(3,Math.floor(i)),r=Math.max(1,Math.floor(r));let a=[],o=[],l=[],c=[],h=e/2,u=Math.PI/2*t,d=e,f=2*u+d,p=n*2+r,y=i+1,g=new D,m=new D;for(let S=0;S<=p;S++){let T=0,v=0,b=0,w=0;if(S<=n){let E=S/n,C=E*Math.PI/2;v=-h-t*Math.cos(C),b=t*Math.sin(C),w=-t*Math.cos(C),T=E*u}else if(S<=n+r){let E=(S-n)/r;v=-h+E*e,b=t,w=0,T=u+E*d}else{let E=(S-n-r)/n,C=E*Math.PI/2;v=h+t*Math.sin(C),b=t*Math.cos(C),w=t*Math.sin(C),T=u+d+E*u}let P=Math.max(0,Math.min(1,T/f)),_=0;S===0?_=.5/i:S===p&&(_=-.5/i);for(let E=0;E<=i;E++){let C=E/i,I=C*Math.PI*2,U=Math.sin(I),k=Math.cos(I);m.x=-b*k,m.y=v,m.z=b*U,o.push(m.x,m.y,m.z),g.set(-b*k,w,b*U),g.normalize(),l.push(g.x,g.y,g.z),c.push(C+_,P)}if(S>0){let E=(S-1)*y;for(let C=0;C<i;C++){let I=E+C,U=E+C+1,k=S*y+C,L=S*y+C+1;a.push(I,U,k),a.push(U,L,k)}}}this.setIndex(a),this.setAttribute("position",new Zt(o,3)),this.setAttribute("normal",new Zt(l,3)),this.setAttribute("uv",new Zt(c,2))}copy(t){return super.copy(t),this.parameters=Object.assign({},t.parameters),this}static fromJSON(t){return new s(t.radius,t.height,t.capSegments,t.radialSegments,t.heightSegments)}},xn=class s extends xe{constructor(t=1,e=32,n=0,i=Math.PI*2){super(),this.type="CircleGeometry",this.parameters={radius:t,segments:e,thetaStart:n,thetaLength:i},e=Math.max(3,e);let r=[],a=[],o=[],l=[],c=new D,h=new rt;a.push(0,0,0),o.push(0,0,1),l.push(.5,.5);for(let u=0,d=3;u<=e;u++,d+=3){let f=n+u/e*i;c.x=t*Math.cos(f),c.y=t*Math.sin(f),a.push(c.x,c.y,c.z),o.push(0,0,1),h.x=(a[d]/t+1)/2,h.y=(a[d+1]/t+1)/2,l.push(h.x,h.y)}for(let u=1;u<=e;u++)r.push(u,u+1,0);this.setIndex(r),this.setAttribute("position",new Zt(a,3)),this.setAttribute("normal",new Zt(o,3)),this.setAttribute("uv",new Zt(l,2))}copy(t){return super.copy(t),this.parameters=Object.assign({},t.parameters),this}static fromJSON(t){return new s(t.radius,t.segments,t.thetaStart,t.thetaLength)}},Ee=class s extends xe{constructor(t=1,e=1,n=1,i=32,r=1,a=!1,o=0,l=Math.PI*2){super(),this.type="CylinderGeometry",this.parameters={radiusTop:t,radiusBottom:e,height:n,radialSegments:i,heightSegments:r,openEnded:a,thetaStart:o,thetaLength:l};let c=this;i=Math.floor(i),r=Math.floor(r);let h=[],u=[],d=[],f=[],p=0,y=[],g=n/2,m=0;S(),a===!1&&(t>0&&T(!0),e>0&&T(!1)),this.setIndex(h),this.setAttribute("position",new Zt(u,3)),this.setAttribute("normal",new Zt(d,3)),this.setAttribute("uv",new Zt(f,2));function S(){let v=new D,b=new D,w=0,P=(e-t)/n;for(let _=0;_<=r;_++){let E=[],C=_/r,I=C*(e-t)+t;for(let U=0;U<=i;U++){let k=U/i,L=k*l+o,B=Math.sin(L),W=Math.cos(L);b.x=I*B,b.y=-C*n+g,b.z=I*W,u.push(b.x,b.y,b.z),v.set(B,P,W).normalize(),d.push(v.x,v.y,v.z),f.push(k,1-C),E.push(p++)}y.push(E)}for(let _=0;_<i;_++)for(let E=0;E<r;E++){let C=y[E][_],I=y[E+1][_],U=y[E+1][_+1],k=y[E][_+1];(t>0||E!==0)&&(h.push(C,I,k),w+=3),(e>0||E!==r-1)&&(h.push(I,U,k),w+=3)}c.addGroup(m,w,0),m+=w}function T(v){let b=p,w=new rt,P=new D,_=0,E=v===!0?t:e,C=v===!0?1:-1;for(let U=1;U<=i;U++)u.push(0,g*C,0),d.push(0,C,0),f.push(.5,.5),p++;let I=p;for(let U=0;U<=i;U++){let L=U/i*l+o,B=Math.cos(L),W=Math.sin(L);P.x=E*W,P.y=g*C,P.z=E*B,u.push(P.x,P.y,P.z),d.push(0,C,0),w.x=B*.5+.5,w.y=W*.5*C+.5,f.push(w.x,w.y),p++}for(let U=0;U<i;U++){let k=b+U,L=I+U;v===!0?h.push(L,L+1,k):h.push(L+1,L,k),_+=3}c.addGroup(m,_,v===!0?1:2),m+=_}}copy(t){return super.copy(t),this.parameters=Object.assign({},t.parameters),this}static fromJSON(t){return new s(t.radiusTop,t.radiusBottom,t.height,t.radialSegments,t.heightSegments,t.openEnded,t.thetaStart,t.thetaLength)}};var cn=class{constructor(){this.type="Curve",this.arcLengthDivisions=200,this.needsUpdate=!1,this.cacheArcLengths=null}getPoint(){qt("Curve: .getPoint() not implemented.")}getPointAt(t,e){let n=this.getUtoTmapping(t);return this.getPoint(n,e)}getPoints(t=5){let e=[];for(let n=0;n<=t;n++)e.push(this.getPoint(n/t));return e}getSpacedPoints(t=5){let e=[];for(let n=0;n<=t;n++)e.push(this.getPointAt(n/t));return e}getLength(){let t=this.getLengths();return t[t.length-1]}getLengths(t=this.arcLengthDivisions){if(this.cacheArcLengths&&this.cacheArcLengths.length===t+1&&!this.needsUpdate)return this.cacheArcLengths;this.needsUpdate=!1;let e=[],n,i=this.getPoint(0),r=0;e.push(0);for(let a=1;a<=t;a++)n=this.getPoint(a/t),r+=n.distanceTo(i),e.push(r),i=n;return this.cacheArcLengths=e,e}updateArcLengths(){this.needsUpdate=!0,this.getLengths()}getUtoTmapping(t,e=null){let n=this.getLengths(),i=0,r=n.length,a;e?a=e:a=t*n[r-1];let o=0,l=r-1,c;for(;o<=l;)if(i=Math.floor(o+(l-o)/2),c=n[i]-a,c<0)o=i+1;else if(c>0)l=i-1;else{l=i;break}if(i=l,n[i]===a)return i/(r-1);let h=n[i],d=n[i+1]-h,f=(a-h)/d;return(i+f)/(r-1)}getTangent(t,e){let i=t-1e-4,r=t+1e-4;i<0&&(i=0),r>1&&(r=1);let a=this.getPoint(i),o=this.getPoint(r),l=e||(a.isVector2?new rt:new D);return l.copy(o).sub(a).normalize(),l}getTangentAt(t,e){let n=this.getUtoTmapping(t);return this.getTangent(n,e)}computeFrenetFrames(t,e=!1){let n=new D,i=[],r=[],a=[],o=new D,l=new fe;for(let f=0;f<=t;f++){let p=f/t;i[f]=this.getTangentAt(p,new D)}r[0]=new D,a[0]=new D;let c=Number.MAX_VALUE,h=Math.abs(i[0].x),u=Math.abs(i[0].y),d=Math.abs(i[0].z);h<=c&&(c=h,n.set(1,0,0)),u<=c&&(c=u,n.set(0,1,0)),d<=c&&n.set(0,0,1),o.crossVectors(i[0],n).normalize(),r[0].crossVectors(i[0],o),a[0].crossVectors(i[0],r[0]);for(let f=1;f<=t;f++){if(r[f]=r[f-1].clone(),a[f]=a[f-1].clone(),o.crossVectors(i[f-1],i[f]),o.length()>Number.EPSILON){o.normalize();let p=Math.acos(se(i[f-1].dot(i[f]),-1,1));r[f].applyMatrix4(l.makeRotationAxis(o,p))}a[f].crossVectors(i[f],r[f])}if(e===!0){let f=Math.acos(se(r[0].dot(r[t]),-1,1));f/=t,i[0].dot(o.crossVectors(r[0],r[t]))>0&&(f=-f);for(let p=1;p<=t;p++)r[p].applyMatrix4(l.makeRotationAxis(i[p],f*p)),a[p].crossVectors(i[p],r[p])}return{tangents:i,normals:r,binormals:a}}clone(){return new this.constructor().copy(this)}copy(t){return this.arcLengthDivisions=t.arcLengthDivisions,this}toJSON(){let t={metadata:{version:4.7,type:"Curve",generator:"Curve.toJSON"}};return t.arcLengthDivisions=this.arcLengthDivisions,t.type=this.type,t}fromJSON(t){return this.arcLengthDivisions=t.arcLengthDivisions,this}},Is=class extends cn{constructor(t=0,e=0,n=1,i=1,r=0,a=Math.PI*2,o=!1,l=0){super(),this.isEllipseCurve=!0,this.type="EllipseCurve",this.aX=t,this.aY=e,this.xRadius=n,this.yRadius=i,this.aStartAngle=r,this.aEndAngle=a,this.aClockwise=o,this.aRotation=l}getPoint(t,e=new rt){let n=e,i=Math.PI*2,r=this.aEndAngle-this.aStartAngle,a=Math.abs(r)<Number.EPSILON;for(;r<0;)r+=i;for(;r>i;)r-=i;r<Number.EPSILON&&(a?r=0:r=i),this.aClockwise===!0&&!a&&(r===i?r=-i:r=r-i);let o=this.aStartAngle+t*r,l=this.aX+this.xRadius*Math.cos(o),c=this.aY+this.yRadius*Math.sin(o);if(this.aRotation!==0){let h=Math.cos(this.aRotation),u=Math.sin(this.aRotation),d=l-this.aX,f=c-this.aY;l=d*h-f*u+this.aX,c=d*u+f*h+this.aY}return n.set(l,c)}copy(t){return super.copy(t),this.aX=t.aX,this.aY=t.aY,this.xRadius=t.xRadius,this.yRadius=t.yRadius,this.aStartAngle=t.aStartAngle,this.aEndAngle=t.aEndAngle,this.aClockwise=t.aClockwise,this.aRotation=t.aRotation,this}toJSON(){let t=super.toJSON();return t.aX=this.aX,t.aY=this.aY,t.xRadius=this.xRadius,t.yRadius=this.yRadius,t.aStartAngle=this.aStartAngle,t.aEndAngle=this.aEndAngle,t.aClockwise=this.aClockwise,t.aRotation=this.aRotation,t}fromJSON(t){return super.fromJSON(t),this.aX=t.aX,this.aY=t.aY,this.xRadius=t.xRadius,this.yRadius=t.yRadius,this.aStartAngle=t.aStartAngle,this.aEndAngle=t.aEndAngle,this.aClockwise=t.aClockwise,this.aRotation=t.aRotation,this}},io=class extends Is{constructor(t,e,n,i,r,a){super(t,e,n,n,i,r,a),this.isArcCurve=!0,this.type="ArcCurve"}};function Bc(){let s=0,t=0,e=0,n=0;function i(r,a,o,l){s=r,t=o,e=-3*r+3*a-2*o-l,n=2*r-2*a+o+l}return{initCatmullRom:function(r,a,o,l,c){i(a,o,c*(o-r),c*(l-a))},initNonuniformCatmullRom:function(r,a,o,l,c,h,u){let d=(a-r)/c-(o-r)/(c+h)+(o-a)/h,f=(o-a)/h-(l-a)/(h+u)+(l-o)/u;d*=h,f*=h,i(a,o,d,f)},calc:function(r){let a=r*r,o=a*r;return s+t*r+e*a+n*o}}}var qh=new D,Yh=new D,ac=new Bc,oc=new Bc,lc=new Bc,Pn=class extends cn{constructor(t=[],e=!1,n="centripetal",i=.5){super(),this.isCatmullRomCurve3=!0,this.type="CatmullRomCurve3",this.points=t,this.closed=e,this.curveType=n,this.tension=i}getPoint(t,e=new D){let n=e,i=this.points,r=i.length,a=(r-(this.closed?0:1))*t,o=Math.floor(a),l=a-o;this.closed?o+=o>0?0:(Math.floor(Math.abs(o)/r)+1)*r:l===0&&o===r-1&&(o=r-2,l=1);let c,h;this.closed||o>0?c=i[(o-1)%r]:(Yh.subVectors(i[0],i[1]).add(i[0]),c=Yh);let u=i[o%r],d=i[(o+1)%r];if(this.closed||o+2<r?h=i[(o+2)%r]:(qh.subVectors(i[r-1],i[r-2]).add(i[r-1]),h=qh),this.curveType==="centripetal"||this.curveType==="chordal"){let f=this.curveType==="chordal"?.5:.25,p=Math.pow(c.distanceToSquared(u),f),y=Math.pow(u.distanceToSquared(d),f),g=Math.pow(d.distanceToSquared(h),f);y<1e-4&&(y=1),p<1e-4&&(p=y),g<1e-4&&(g=y),ac.initNonuniformCatmullRom(c.x,u.x,d.x,h.x,p,y,g),oc.initNonuniformCatmullRom(c.y,u.y,d.y,h.y,p,y,g),lc.initNonuniformCatmullRom(c.z,u.z,d.z,h.z,p,y,g)}else this.curveType==="catmullrom"&&(ac.initCatmullRom(c.x,u.x,d.x,h.x,this.tension),oc.initCatmullRom(c.y,u.y,d.y,h.y,this.tension),lc.initCatmullRom(c.z,u.z,d.z,h.z,this.tension));return n.set(ac.calc(l),oc.calc(l),lc.calc(l)),n}copy(t){super.copy(t),this.points=[];for(let e=0,n=t.points.length;e<n;e++){let i=t.points[e];this.points.push(i.clone())}return this.closed=t.closed,this.curveType=t.curveType,this.tension=t.tension,this}toJSON(){let t=super.toJSON();t.points=[];for(let e=0,n=this.points.length;e<n;e++){let i=this.points[e];t.points.push(i.toArray())}return t.closed=this.closed,t.curveType=this.curveType,t.tension=this.tension,t}fromJSON(t){super.fromJSON(t),this.points=[];for(let e=0,n=t.points.length;e<n;e++){let i=t.points[e];this.points.push(new D().fromArray(i))}return this.closed=t.closed,this.curveType=t.curveType,this.tension=t.tension,this}};function Zh(s,t,e,n,i){let r=(n-t)*.5,a=(i-e)*.5,o=s*s,l=s*o;return(2*e-2*n+r+a)*l+(-3*e+3*n-2*r-a)*o+r*s+e}function Ef(s,t){let e=1-s;return e*e*t}function wf(s,t){return 2*(1-s)*s*t}function Tf(s,t){return s*s*t}function cr(s,t,e,n){return Ef(s,t)+wf(s,e)+Tf(s,n)}function Af(s,t){let e=1-s;return e*e*e*t}function Rf(s,t){let e=1-s;return 3*e*e*s*t}function Cf(s,t){return 3*(1-s)*s*s*t}function Pf(s,t){return s*s*s*t}function hr(s,t,e,n,i){return Af(s,t)+Rf(s,e)+Cf(s,n)+Pf(s,i)}var Ar=class extends cn{constructor(t=new rt,e=new rt,n=new rt,i=new rt){super(),this.isCubicBezierCurve=!0,this.type="CubicBezierCurve",this.v0=t,this.v1=e,this.v2=n,this.v3=i}getPoint(t,e=new rt){let n=e,i=this.v0,r=this.v1,a=this.v2,o=this.v3;return n.set(hr(t,i.x,r.x,a.x,o.x),hr(t,i.y,r.y,a.y,o.y)),n}copy(t){return super.copy(t),this.v0.copy(t.v0),this.v1.copy(t.v1),this.v2.copy(t.v2),this.v3.copy(t.v3),this}toJSON(){let t=super.toJSON();return t.v0=this.v0.toArray(),t.v1=this.v1.toArray(),t.v2=this.v2.toArray(),t.v3=this.v3.toArray(),t}fromJSON(t){return super.fromJSON(t),this.v0.fromArray(t.v0),this.v1.fromArray(t.v1),this.v2.fromArray(t.v2),this.v3.fromArray(t.v3),this}},so=class extends cn{constructor(t=new D,e=new D,n=new D,i=new D){super(),this.isCubicBezierCurve3=!0,this.type="CubicBezierCurve3",this.v0=t,this.v1=e,this.v2=n,this.v3=i}getPoint(t,e=new D){let n=e,i=this.v0,r=this.v1,a=this.v2,o=this.v3;return n.set(hr(t,i.x,r.x,a.x,o.x),hr(t,i.y,r.y,a.y,o.y),hr(t,i.z,r.z,a.z,o.z)),n}copy(t){return super.copy(t),this.v0.copy(t.v0),this.v1.copy(t.v1),this.v2.copy(t.v2),this.v3.copy(t.v3),this}toJSON(){let t=super.toJSON();return t.v0=this.v0.toArray(),t.v1=this.v1.toArray(),t.v2=this.v2.toArray(),t.v3=this.v3.toArray(),t}fromJSON(t){return super.fromJSON(t),this.v0.fromArray(t.v0),this.v1.fromArray(t.v1),this.v2.fromArray(t.v2),this.v3.fromArray(t.v3),this}},Rr=class extends cn{constructor(t=new rt,e=new rt){super(),this.isLineCurve=!0,this.type="LineCurve",this.v1=t,this.v2=e}getPoint(t,e=new rt){let n=e;return t===1?n.copy(this.v2):(n.copy(this.v2).sub(this.v1),n.multiplyScalar(t).add(this.v1)),n}getPointAt(t,e){return this.getPoint(t,e)}getTangent(t,e=new rt){return e.subVectors(this.v2,this.v1).normalize()}getTangentAt(t,e){return this.getTangent(t,e)}copy(t){return super.copy(t),this.v1.copy(t.v1),this.v2.copy(t.v2),this}toJSON(){let t=super.toJSON();return t.v1=this.v1.toArray(),t.v2=this.v2.toArray(),t}fromJSON(t){return super.fromJSON(t),this.v1.fromArray(t.v1),this.v2.fromArray(t.v2),this}},ro=class extends cn{constructor(t=new D,e=new D){super(),this.isLineCurve3=!0,this.type="LineCurve3",this.v1=t,this.v2=e}getPoint(t,e=new D){let n=e;return t===1?n.copy(this.v2):(n.copy(this.v2).sub(this.v1),n.multiplyScalar(t).add(this.v1)),n}getPointAt(t,e){return this.getPoint(t,e)}getTangent(t,e=new D){return e.subVectors(this.v2,this.v1).normalize()}getTangentAt(t,e){return this.getTangent(t,e)}copy(t){return super.copy(t),this.v1.copy(t.v1),this.v2.copy(t.v2),this}toJSON(){let t=super.toJSON();return t.v1=this.v1.toArray(),t.v2=this.v2.toArray(),t}fromJSON(t){return super.fromJSON(t),this.v1.fromArray(t.v1),this.v2.fromArray(t.v2),this}},Cr=class extends cn{constructor(t=new rt,e=new rt,n=new rt){super(),this.isQuadraticBezierCurve=!0,this.type="QuadraticBezierCurve",this.v0=t,this.v1=e,this.v2=n}getPoint(t,e=new rt){let n=e,i=this.v0,r=this.v1,a=this.v2;return n.set(cr(t,i.x,r.x,a.x),cr(t,i.y,r.y,a.y)),n}copy(t){return super.copy(t),this.v0.copy(t.v0),this.v1.copy(t.v1),this.v2.copy(t.v2),this}toJSON(){let t=super.toJSON();return t.v0=this.v0.toArray(),t.v1=this.v1.toArray(),t.v2=this.v2.toArray(),t}fromJSON(t){return super.fromJSON(t),this.v0.fromArray(t.v0),this.v1.fromArray(t.v1),this.v2.fromArray(t.v2),this}},Pr=class extends cn{constructor(t=new D,e=new D,n=new D){super(),this.isQuadraticBezierCurve3=!0,this.type="QuadraticBezierCurve3",this.v0=t,this.v1=e,this.v2=n}getPoint(t,e=new D){let n=e,i=this.v0,r=this.v1,a=this.v2;return n.set(cr(t,i.x,r.x,a.x),cr(t,i.y,r.y,a.y),cr(t,i.z,r.z,a.z)),n}copy(t){return super.copy(t),this.v0.copy(t.v0),this.v1.copy(t.v1),this.v2.copy(t.v2),this}toJSON(){let t=super.toJSON();return t.v0=this.v0.toArray(),t.v1=this.v1.toArray(),t.v2=this.v2.toArray(),t}fromJSON(t){return super.fromJSON(t),this.v0.fromArray(t.v0),this.v1.fromArray(t.v1),this.v2.fromArray(t.v2),this}},Ir=class extends cn{constructor(t=[]){super(),this.isSplineCurve=!0,this.type="SplineCurve",this.points=t}getPoint(t,e=new rt){let n=e,i=this.points,r=(i.length-1)*t,a=Math.floor(r),o=r-a,l=i[a===0?a:a-1],c=i[a],h=i[a>i.length-2?i.length-1:a+1],u=i[a>i.length-3?i.length-1:a+2];return n.set(Zh(o,l.x,c.x,h.x,u.x),Zh(o,l.y,c.y,h.y,u.y)),n}copy(t){super.copy(t),this.points=[];for(let e=0,n=t.points.length;e<n;e++){let i=t.points[e];this.points.push(i.clone())}return this}toJSON(){let t=super.toJSON();t.points=[];for(let e=0,n=this.points.length;e<n;e++){let i=this.points[e];t.points.push(i.toArray())}return t}fromJSON(t){super.fromJSON(t),this.points=[];for(let e=0,n=t.points.length;e<n;e++){let i=t.points[e];this.points.push(new rt().fromArray(i))}return this}},ao=Object.freeze({__proto__:null,ArcCurve:io,CatmullRomCurve3:Pn,CubicBezierCurve:Ar,CubicBezierCurve3:so,EllipseCurve:Is,LineCurve:Rr,LineCurve3:ro,QuadraticBezierCurve:Cr,QuadraticBezierCurve3:Pr,SplineCurve:Ir}),oo=class extends cn{constructor(){super(),this.type="CurvePath",this.curves=[],this.autoClose=!1}add(t){this.curves.push(t)}closePath(){let t=this.curves[0].getPoint(0),e=this.curves[this.curves.length-1].getPoint(1);if(!t.equals(e)){let n=t.isVector2===!0?"LineCurve":"LineCurve3";this.curves.push(new ao[n](e,t))}return this}getPoint(t,e){let n=t*this.getLength(),i=this.getCurveLengths(),r=0;for(;r<i.length;){if(i[r]>=n){let a=i[r]-n,o=this.curves[r],l=o.getLength(),c=l===0?0:1-a/l;return o.getPointAt(c,e)}r++}return null}getLength(){let t=this.getCurveLengths();return t[t.length-1]}updateArcLengths(){this.needsUpdate=!0,this.cacheLengths=null,this.getCurveLengths()}getCurveLengths(){if(this.cacheLengths&&this.cacheLengths.length===this.curves.length)return this.cacheLengths;let t=[],e=0;for(let n=0,i=this.curves.length;n<i;n++)e+=this.curves[n].getLength(),t.push(e);return this.cacheLengths=t,t}getSpacedPoints(t=40){let e=[];for(let n=0;n<=t;n++)e.push(this.getPoint(n/t));return this.autoClose&&e.push(e[0]),e}getPoints(t=12){let e=[],n;for(let i=0,r=this.curves;i<r.length;i++){let a=r[i],o=a.isEllipseCurve?t*2:a.isLineCurve||a.isLineCurve3?1:a.isSplineCurve?t*a.points.length:t,l=a.getPoints(o);for(let c=0;c<l.length;c++){let h=l[c];n&&n.equals(h)||(e.push(h),n=h)}}return this.autoClose&&e.length>1&&!e[e.length-1].equals(e[0])&&e.push(e[0]),e}copy(t){super.copy(t),this.curves=[];for(let e=0,n=t.curves.length;e<n;e++){let i=t.curves[e];this.curves.push(i.clone())}return this.autoClose=t.autoClose,this}toJSON(){let t=super.toJSON();t.autoClose=this.autoClose,t.curves=[];for(let e=0,n=this.curves.length;e<n;e++){let i=this.curves[e];t.curves.push(i.toJSON())}return t}fromJSON(t){super.fromJSON(t),this.autoClose=t.autoClose,this.curves=[];for(let e=0,n=t.curves.length;e<n;e++){let i=t.curves[e];this.curves.push(new ao[i.type]().fromJSON(i))}return this}},Lr=class extends oo{constructor(t){super(),this.type="Path",this.currentPoint=new rt,t&&this.setFromPoints(t)}setFromPoints(t){this.moveTo(t[0].x,t[0].y);for(let e=1,n=t.length;e<n;e++)this.lineTo(t[e].x,t[e].y);return this}moveTo(t,e){return this.currentPoint.set(t,e),this}lineTo(t,e){let n=new Rr(this.currentPoint.clone(),new rt(t,e));return this.curves.push(n),this.currentPoint.set(t,e),this}quadraticCurveTo(t,e,n,i){let r=new Cr(this.currentPoint.clone(),new rt(t,e),new rt(n,i));return this.curves.push(r),this.currentPoint.set(n,i),this}bezierCurveTo(t,e,n,i,r,a){let o=new Ar(this.currentPoint.clone(),new rt(t,e),new rt(n,i),new rt(r,a));return this.curves.push(o),this.currentPoint.set(r,a),this}splineThru(t){let e=[this.currentPoint.clone()].concat(t),n=new Ir(e);return this.curves.push(n),this.currentPoint.copy(t[t.length-1]),this}arc(t,e,n,i,r,a){let o=this.currentPoint.x,l=this.currentPoint.y;return this.absarc(t+o,e+l,n,i,r,a),this}absarc(t,e,n,i,r,a){return this.absellipse(t,e,n,n,i,r,a),this}ellipse(t,e,n,i,r,a,o,l){let c=this.currentPoint.x,h=this.currentPoint.y;return this.absellipse(t+c,e+h,n,i,r,a,o,l),this}absellipse(t,e,n,i,r,a,o,l){let c=new Is(t,e,n,i,r,a,o,l);if(this.curves.length>0){let u=c.getPoint(0);u.equals(this.currentPoint)||this.lineTo(u.x,u.y)}this.curves.push(c);let h=c.getPoint(1);return this.currentPoint.copy(h),this}copy(t){return super.copy(t),this.currentPoint.copy(t.currentPoint),this}toJSON(){let t=super.toJSON();return t.currentPoint=this.currentPoint.toArray(),t}fromJSON(t){return super.fromJSON(t),this.currentPoint.fromArray(t.currentPoint),this}},In=class extends Lr{constructor(t){super(t),this.uuid=ti(),this.type="Shape",this.holes=[]}getPointsHoles(t){let e=[];for(let n=0,i=this.holes.length;n<i;n++)e[n]=this.holes[n].getPoints(t);return e}extractPoints(t){return{shape:this.getPoints(t),holes:this.getPointsHoles(t)}}copy(t){super.copy(t),this.holes=[];for(let e=0,n=t.holes.length;e<n;e++){let i=t.holes[e];this.holes.push(i.clone())}return this}toJSON(){let t=super.toJSON();t.uuid=this.uuid,t.holes=[];for(let e=0,n=this.holes.length;e<n;e++){let i=this.holes[e];t.holes.push(i.toJSON())}return t}fromJSON(t){super.fromJSON(t),this.uuid=t.uuid,this.holes=[];for(let e=0,n=t.holes.length;e<n;e++){let i=t.holes[e];this.holes.push(new Lr().fromJSON(i))}return this}};function If(s,t,e=2){let n=t&&t.length,i=n?t[0]*e:s.length,r=Gu(s,0,i,e,!0),a=[];if(!r||r.next===r.prev)return a;let o,l,c;if(n&&(r=Ff(s,t,r,e)),s.length>80*e){o=s[0],l=s[1];let h=o,u=l;for(let d=e;d<i;d+=e){let f=s[d],p=s[d+1];f<o&&(o=f),p<l&&(l=p),f>h&&(h=f),p>u&&(u=p)}c=Math.max(h-o,u-l),c=c!==0?32767/c:0}return Dr(r,a,e,o,l,c,0),a}function Gu(s,t,e,n,i){let r;if(i===Yf(s,t,e,n)>0)for(let a=t;a<e;a+=n)r=Jh(a/n|0,s[a],s[a+1],r);else for(let a=e-n;a>=t;a-=n)r=Jh(a/n|0,s[a],s[a+1],r);return r&&Ls(r,r.next)&&(Nr(r),r=r.next),r}function Gi(s,t){if(!s)return s;t||(t=s);let e=s,n;do if(n=!1,!e.steiner&&(Ls(e,e.next)||be(e.prev,e,e.next)===0)){if(Nr(e),e=t=e.prev,e===e.next)break;n=!0}else e=e.next;while(n||e!==t);return t}function Dr(s,t,e,n,i,r,a){if(!s)return;!a&&r&&Hf(s,n,i,r);let o=s;for(;s.prev!==s.next;){let l=s.prev,c=s.next;if(r?Df(s,n,i,r):Lf(s)){t.push(l.i,s.i,c.i),Nr(s),s=c.next,o=c.next;continue}if(s=c,s===o){a?a===1?(s=Uf(Gi(s),t),Dr(s,t,e,n,i,r,2)):a===2&&Nf(s,t,e,n,i,r):Dr(Gi(s),t,e,n,i,r,1);break}}}function Lf(s){let t=s.prev,e=s,n=s.next;if(be(t,e,n)>=0)return!1;let i=t.x,r=e.x,a=n.x,o=t.y,l=e.y,c=n.y,h=Math.min(i,r,a),u=Math.min(o,l,c),d=Math.max(i,r,a),f=Math.max(o,l,c),p=n.next;for(;p!==t;){if(p.x>=h&&p.x<=d&&p.y>=u&&p.y<=f&&lr(i,o,r,l,a,c,p.x,p.y)&&be(p.prev,p,p.next)>=0)return!1;p=p.next}return!0}function Df(s,t,e,n){let i=s.prev,r=s,a=s.next;if(be(i,r,a)>=0)return!1;let o=i.x,l=r.x,c=a.x,h=i.y,u=r.y,d=a.y,f=Math.min(o,l,c),p=Math.min(h,u,d),y=Math.max(o,l,c),g=Math.max(h,u,d),m=mc(f,p,t,e,n),S=mc(y,g,t,e,n),T=s.prevZ,v=s.nextZ;for(;T&&T.z>=m&&v&&v.z<=S;){if(T.x>=f&&T.x<=y&&T.y>=p&&T.y<=g&&T!==i&&T!==a&&lr(o,h,l,u,c,d,T.x,T.y)&&be(T.prev,T,T.next)>=0||(T=T.prevZ,v.x>=f&&v.x<=y&&v.y>=p&&v.y<=g&&v!==i&&v!==a&&lr(o,h,l,u,c,d,v.x,v.y)&&be(v.prev,v,v.next)>=0))return!1;v=v.nextZ}for(;T&&T.z>=m;){if(T.x>=f&&T.x<=y&&T.y>=p&&T.y<=g&&T!==i&&T!==a&&lr(o,h,l,u,c,d,T.x,T.y)&&be(T.prev,T,T.next)>=0)return!1;T=T.prevZ}for(;v&&v.z<=S;){if(v.x>=f&&v.x<=y&&v.y>=p&&v.y<=g&&v!==i&&v!==a&&lr(o,h,l,u,c,d,v.x,v.y)&&be(v.prev,v,v.next)>=0)return!1;v=v.nextZ}return!0}function Uf(s,t){let e=s;do{let n=e.prev,i=e.next.next;!Ls(n,i)&&Xu(n,e,e.next,i)&&Ur(n,i)&&Ur(i,n)&&(t.push(n.i,e.i,i.i),Nr(e),Nr(e.next),e=s=i),e=e.next}while(e!==s);return Gi(e)}function Nf(s,t,e,n,i,r){let a=s;do{let o=a.next.next;for(;o!==a.prev;){if(a.i!==o.i&&Wf(a,o)){let l=qu(a,o);a=Gi(a,a.next),l=Gi(l,l.next),Dr(a,t,e,n,i,r,0),Dr(l,t,e,n,i,r,0);return}o=o.next}a=a.next}while(a!==s)}function Ff(s,t,e,n){let i=[];for(let r=0,a=t.length;r<a;r++){let o=t[r]*n,l=r<a-1?t[r+1]*n:s.length,c=Gu(s,o,l,n,!1);c===c.next&&(c.steiner=!0),i.push(Gf(c))}i.sort(Bf);for(let r=0;r<i.length;r++)e=Of(i[r],e);return e}function Bf(s,t){let e=s.x-t.x;if(e===0&&(e=s.y-t.y,e===0)){let n=(s.next.y-s.y)/(s.next.x-s.x),i=(t.next.y-t.y)/(t.next.x-t.x);e=n-i}return e}function Of(s,t){let e=kf(s,t);if(!e)return t;let n=qu(e,s);return Gi(n,n.next),Gi(e,e.next)}function kf(s,t){let e=t,n=s.x,i=s.y,r=-1/0,a;if(Ls(s,e))return e;do{if(Ls(s,e.next))return e.next;if(i<=e.y&&i>=e.next.y&&e.next.y!==e.y){let u=e.x+(i-e.y)*(e.next.x-e.x)/(e.next.y-e.y);if(u<=n&&u>r&&(r=u,a=e.x<e.next.x?e:e.next,u===n))return a}e=e.next}while(e!==t);if(!a)return null;let o=a,l=a.x,c=a.y,h=1/0;e=a;do{if(n>=e.x&&e.x>=l&&n!==e.x&&Wu(i<c?n:r,i,l,c,i<c?r:n,i,e.x,e.y)){let u=Math.abs(i-e.y)/(n-e.x);Ur(e,s)&&(u<h||u===h&&(e.x>a.x||e.x===a.x&&zf(a,e)))&&(a=e,h=u)}e=e.next}while(e!==o);return a}function zf(s,t){return be(s.prev,s,t.prev)<0&&be(t.next,s,s.next)<0}function Hf(s,t,e,n){let i=s;do i.z===0&&(i.z=mc(i.x,i.y,t,e,n)),i.prevZ=i.prev,i.nextZ=i.next,i=i.next;while(i!==s);i.prevZ.nextZ=null,i.prevZ=null,Vf(i)}function Vf(s){let t,e=1;do{let n=s,i;s=null;let r=null;for(t=0;n;){t++;let a=n,o=0;for(let c=0;c<e&&(o++,a=a.nextZ,!!a);c++);let l=e;for(;o>0||l>0&&a;)o!==0&&(l===0||!a||n.z<=a.z)?(i=n,n=n.nextZ,o--):(i=a,a=a.nextZ,l--),r?r.nextZ=i:s=i,i.prevZ=r,r=i;n=a}r.nextZ=null,e*=2}while(t>1);return s}function mc(s,t,e,n,i){return s=(s-e)*i|0,t=(t-n)*i|0,s=(s|s<<8)&16711935,s=(s|s<<4)&252645135,s=(s|s<<2)&858993459,s=(s|s<<1)&1431655765,t=(t|t<<8)&16711935,t=(t|t<<4)&252645135,t=(t|t<<2)&858993459,t=(t|t<<1)&1431655765,s|t<<1}function Gf(s){let t=s,e=s;do(t.x<e.x||t.x===e.x&&t.y<e.y)&&(e=t),t=t.next;while(t!==s);return e}function Wu(s,t,e,n,i,r,a,o){return(i-a)*(t-o)>=(s-a)*(r-o)&&(s-a)*(n-o)>=(e-a)*(t-o)&&(e-a)*(r-o)>=(i-a)*(n-o)}function lr(s,t,e,n,i,r,a,o){return!(s===a&&t===o)&&Wu(s,t,e,n,i,r,a,o)}function Wf(s,t){return s.next.i!==t.i&&s.prev.i!==t.i&&!Xf(s,t)&&(Ur(s,t)&&Ur(t,s)&&qf(s,t)&&(be(s.prev,s,t.prev)||be(s,t.prev,t))||Ls(s,t)&&be(s.prev,s,s.next)>0&&be(t.prev,t,t.next)>0)}function be(s,t,e){return(t.y-s.y)*(e.x-t.x)-(t.x-s.x)*(e.y-t.y)}function Ls(s,t){return s.x===t.x&&s.y===t.y}function Xu(s,t,e,n){let i=Oa(be(s,t,e)),r=Oa(be(s,t,n)),a=Oa(be(e,n,s)),o=Oa(be(e,n,t));return!!(i!==r&&a!==o||i===0&&Ba(s,e,t)||r===0&&Ba(s,n,t)||a===0&&Ba(e,s,n)||o===0&&Ba(e,t,n))}function Ba(s,t,e){return t.x<=Math.max(s.x,e.x)&&t.x>=Math.min(s.x,e.x)&&t.y<=Math.max(s.y,e.y)&&t.y>=Math.min(s.y,e.y)}function Oa(s){return s>0?1:s<0?-1:0}function Xf(s,t){let e=s;do{if(e.i!==s.i&&e.next.i!==s.i&&e.i!==t.i&&e.next.i!==t.i&&Xu(e,e.next,s,t))return!0;e=e.next}while(e!==s);return!1}function Ur(s,t){return be(s.prev,s,s.next)<0?be(s,t,s.next)>=0&&be(s,s.prev,t)>=0:be(s,t,s.prev)<0||be(s,s.next,t)<0}function qf(s,t){let e=s,n=!1,i=(s.x+t.x)/2,r=(s.y+t.y)/2;do e.y>r!=e.next.y>r&&e.next.y!==e.y&&i<(e.next.x-e.x)*(r-e.y)/(e.next.y-e.y)+e.x&&(n=!n),e=e.next;while(e!==s);return n}function qu(s,t){let e=gc(s.i,s.x,s.y),n=gc(t.i,t.x,t.y),i=s.next,r=t.prev;return s.next=t,t.prev=s,e.next=i,i.prev=e,n.next=e,e.prev=n,r.next=n,n.prev=r,n}function Jh(s,t,e,n){let i=gc(s,t,e);return n?(i.next=n.next,i.prev=n,n.next.prev=i,n.next=i):(i.prev=i,i.next=i),i}function Nr(s){s.next.prev=s.prev,s.prev.next=s.next,s.prevZ&&(s.prevZ.nextZ=s.nextZ),s.nextZ&&(s.nextZ.prevZ=s.prevZ)}function gc(s,t,e){return{i:s,x:t,y:e,prev:null,next:null,z:0,prevZ:null,nextZ:null,steiner:!1}}function Yf(s,t,e,n){let i=0;for(let r=t,a=e-n;r<e;r+=n)i+=(s[a]-s[r])*(s[r+1]+s[a+1]),a=r;return i}var xc=class{static triangulate(t,e,n=2){return If(t,e,n)}},kn=class s{static area(t){let e=t.length,n=0;for(let i=e-1,r=0;r<e;i=r++)n+=t[i].x*t[r].y-t[r].x*t[i].y;return n*.5}static isClockWise(t){return s.area(t)<0}static triangulateShape(t,e){let n=[],i=[],r=[];$h(t),Kh(n,t);let a=t.length;e.forEach($h);for(let l=0;l<e.length;l++)i.push(a),a+=e[l].length,Kh(n,e[l]);let o=xc.triangulate(n,i);for(let l=0;l<o.length;l+=3)r.push(o.slice(l,l+3));return r}};function $h(s){let t=s.length;t>2&&s[t-1].equals(s[0])&&s.pop()}function Kh(s,t){for(let e=0;e<t.length;e++)s.push(t[e].x),s.push(t[e].y)}var ni=class s extends xe{constructor(t=new In([new rt(.5,.5),new rt(-.5,.5),new rt(-.5,-.5),new rt(.5,-.5)]),e={}){super(),this.type="ExtrudeGeometry",this.parameters={shapes:t,options:e},t=Array.isArray(t)?t:[t];let n=this,i=[],r=[];for(let o=0,l=t.length;o<l;o++){let c=t[o];a(c)}this.setAttribute("position",new Zt(i,3)),this.setAttribute("uv",new Zt(r,2)),this.computeVertexNormals();function a(o){let l=[],c=e.curveSegments!==void 0?e.curveSegments:12,h=e.steps!==void 0?e.steps:1,u=e.depth!==void 0?e.depth:1,d=e.bevelEnabled!==void 0?e.bevelEnabled:!0,f=e.bevelThickness!==void 0?e.bevelThickness:.2,p=e.bevelSize!==void 0?e.bevelSize:f-.1,y=e.bevelOffset!==void 0?e.bevelOffset:0,g=e.bevelSegments!==void 0?e.bevelSegments:3,m=e.extrudePath,S=e.UVGenerator!==void 0?e.UVGenerator:Zf,T,v=!1,b,w,P,_;if(m){T=m.getSpacedPoints(h),v=!0,d=!1;let j=m.isCatmullRomCurve3?m.closed:!1;b=m.computeFrenetFrames(h,j),w=new D,P=new D,_=new D}d||(g=0,f=0,p=0,y=0);let E=o.extractPoints(c),C=E.shape,I=E.holes;if(!kn.isClockWise(C)){C=C.reverse();for(let j=0,ot=I.length;j<ot;j++){let ht=I[j];kn.isClockWise(ht)&&(I[j]=ht.reverse())}}function k(j){let ht=10000000000000001e-36,dt=j[0];for(let pt=1;pt<=j.length;pt++){let Ht=pt%j.length,kt=j[Ht],Gt=kt.x-dt.x,z=kt.y-dt.y,A=Gt*Gt+z*z,Tt=Math.max(Math.abs(kt.x),Math.abs(kt.y),Math.abs(dt.x),Math.abs(dt.y)),Et=ht*Tt*Tt;if(A<=Et){j.splice(Ht,1),pt--;continue}dt=kt}}k(C),I.forEach(k);let L=I.length,B=C;for(let j=0;j<L;j++){let ot=I[j];C=C.concat(ot)}function W(j,ot,ht){return ot||Xt("ExtrudeGeometry: vec does not exist"),j.clone().addScaledVector(ot,ht)}let X=C.length;function st(j,ot,ht){let dt,pt,Ht,kt=j.x-ot.x,Gt=j.y-ot.y,z=ht.x-j.x,A=ht.y-j.y,Tt=kt*kt+Gt*Gt,Et=kt*A-Gt*z;if(Math.abs(Et)>Number.EPSILON){let R=Math.sqrt(Tt),x=Math.sqrt(z*z+A*A),O=ot.x-Gt/R,G=ot.y+kt/R,q=ht.x-A/x,lt=ht.y+z/x,ft=((q-O)*A-(lt-G)*z)/(kt*A-Gt*z);dt=O+kt*ft-j.x,pt=G+Gt*ft-j.y;let K=dt*dt+pt*pt;if(K<=2)return new rt(dt,pt);Ht=Math.sqrt(K/2)}else{let R=!1;kt>Number.EPSILON?z>Number.EPSILON&&(R=!0):kt<-Number.EPSILON?z<-Number.EPSILON&&(R=!0):Math.sign(Gt)===Math.sign(A)&&(R=!0),R?(dt=-Gt,pt=kt,Ht=Math.sqrt(Tt)):(dt=kt,pt=Gt,Ht=Math.sqrt(Tt/2))}return new rt(dt/Ht,pt/Ht)}let Z=[];for(let j=0,ot=B.length,ht=ot-1,dt=j+1;j<ot;j++,ht++,dt++)ht===ot&&(ht=0),dt===ot&&(dt=0),Z[j]=st(B[j],B[ht],B[dt]);let Q=[],it,nt=Z.concat();for(let j=0,ot=L;j<ot;j++){let ht=I[j];it=[];for(let dt=0,pt=ht.length,Ht=pt-1,kt=dt+1;dt<pt;dt++,Ht++,kt++)Ht===pt&&(Ht=0),kt===pt&&(kt=0),it[dt]=st(ht[dt],ht[Ht],ht[kt]);Q.push(it),nt=nt.concat(it)}let ct;if(g===0)ct=kn.triangulateShape(B,I);else{let j=[],ot=[];for(let ht=0;ht<g;ht++){let dt=ht/g,pt=f*Math.cos(dt*Math.PI/2),Ht=p*Math.sin(dt*Math.PI/2)+y;for(let kt=0,Gt=B.length;kt<Gt;kt++){let z=W(B[kt],Z[kt],Ht);mt(z.x,z.y,-pt),dt===0&&j.push(z)}for(let kt=0,Gt=L;kt<Gt;kt++){let z=I[kt];it=Q[kt];let A=[];for(let Tt=0,Et=z.length;Tt<Et;Tt++){let R=W(z[Tt],it[Tt],Ht);mt(R.x,R.y,-pt),dt===0&&A.push(R)}dt===0&&ot.push(A)}}ct=kn.triangulateShape(j,ot)}let Yt=ct.length,Jt=p+y;for(let j=0;j<X;j++){let ot=d?W(C[j],nt[j],Jt):C[j];v?(P.copy(b.normals[0]).multiplyScalar(ot.x),w.copy(b.binormals[0]).multiplyScalar(ot.y),_.copy(T[0]).add(P).add(w),mt(_.x,_.y,_.z)):mt(ot.x,ot.y,0)}for(let j=1;j<=h;j++)for(let ot=0;ot<X;ot++){let ht=d?W(C[ot],nt[ot],Jt):C[ot];v?(P.copy(b.normals[j]).multiplyScalar(ht.x),w.copy(b.binormals[j]).multiplyScalar(ht.y),_.copy(T[j]).add(P).add(w),mt(_.x,_.y,_.z)):mt(ht.x,ht.y,u/h*j)}for(let j=g-1;j>=0;j--){let ot=j/g,ht=f*Math.cos(ot*Math.PI/2),dt=p*Math.sin(ot*Math.PI/2)+y;for(let pt=0,Ht=B.length;pt<Ht;pt++){let kt=W(B[pt],Z[pt],dt);mt(kt.x,kt.y,u+ht)}for(let pt=0,Ht=I.length;pt<Ht;pt++){let kt=I[pt];it=Q[pt];for(let Gt=0,z=kt.length;Gt<z;Gt++){let A=W(kt[Gt],it[Gt],dt);v?mt(A.x,A.y+T[h-1].y,T[h-1].x+ht):mt(A.x,A.y,u+ht)}}}Qt(),J();function Qt(){let j=i.length/3;if(d){let ot=0,ht=X*ot;for(let dt=0;dt<Yt;dt++){let pt=ct[dt];Lt(pt[2]+ht,pt[1]+ht,pt[0]+ht)}ot=h+g*2,ht=X*ot;for(let dt=0;dt<Yt;dt++){let pt=ct[dt];Lt(pt[0]+ht,pt[1]+ht,pt[2]+ht)}}else{for(let ot=0;ot<Yt;ot++){let ht=ct[ot];Lt(ht[2],ht[1],ht[0])}for(let ot=0;ot<Yt;ot++){let ht=ct[ot];Lt(ht[0]+X*h,ht[1]+X*h,ht[2]+X*h)}}n.addGroup(j,i.length/3-j,0)}function J(){let j=i.length/3,ot=0;$(B,ot),ot+=B.length;for(let ht=0,dt=I.length;ht<dt;ht++){let pt=I[ht];$(pt,ot),ot+=pt.length}n.addGroup(j,i.length/3-j,1)}function $(j,ot){let ht=j.length;for(;--ht>=0;){let dt=ht,pt=ht-1;pt<0&&(pt=j.length-1);for(let Ht=0,kt=h+g*2;Ht<kt;Ht++){let Gt=X*Ht,z=X*(Ht+1),A=ot+dt+Gt,Tt=ot+pt+Gt,Et=ot+pt+z,R=ot+dt+z;gt(A,Tt,Et,R)}}}function mt(j,ot,ht){l.push(j),l.push(ot),l.push(ht)}function Lt(j,ot,ht){Bt(j),Bt(ot),Bt(ht);let dt=i.length/3,pt=S.generateTopUV(n,i,dt-3,dt-2,dt-1);ae(pt[0]),ae(pt[1]),ae(pt[2])}function gt(j,ot,ht,dt){Bt(j),Bt(ot),Bt(dt),Bt(ot),Bt(ht),Bt(dt);let pt=i.length/3,Ht=S.generateSideWallUV(n,i,pt-6,pt-3,pt-2,pt-1);ae(Ht[0]),ae(Ht[1]),ae(Ht[3]),ae(Ht[1]),ae(Ht[2]),ae(Ht[3])}function Bt(j){i.push(l[j*3+0]),i.push(l[j*3+1]),i.push(l[j*3+2])}function ae(j){r.push(j.x),r.push(j.y)}}}copy(t){return super.copy(t),this.parameters=Object.assign({},t.parameters),this}toJSON(){let t=super.toJSON(),e=this.parameters.shapes,n=this.parameters.options;return Jf(e,n,t)}static fromJSON(t,e){let n=[];for(let r=0,a=t.shapes.length;r<a;r++){let o=e[t.shapes[r]];n.push(o)}let i=t.options.extrudePath;return i!==void 0&&(t.options.extrudePath=new ao[i.type]().fromJSON(i)),new s(n,t.options)}},Zf={generateTopUV:function(s,t,e,n,i){let r=t[e*3],a=t[e*3+1],o=t[n*3],l=t[n*3+1],c=t[i*3],h=t[i*3+1];return[new rt(r,a),new rt(o,l),new rt(c,h)]},generateSideWallUV:function(s,t,e,n,i,r){let a=t[e*3],o=t[e*3+1],l=t[e*3+2],c=t[n*3],h=t[n*3+1],u=t[n*3+2],d=t[i*3],f=t[i*3+1],p=t[i*3+2],y=t[r*3],g=t[r*3+1],m=t[r*3+2];return Math.abs(o-h)<Math.abs(a-c)?[new rt(a,1-l),new rt(c,1-u),new rt(d,1-p),new rt(y,1-m)]:[new rt(o,1-l),new rt(h,1-u),new rt(f,1-p),new rt(g,1-m)]}};function Jf(s,t,e){if(e.shapes=[],Array.isArray(s))for(let n=0,i=s.length;n<i;n++){let r=s[n];e.shapes.push(r.uuid)}else e.shapes.push(s.uuid);return e.options=Object.assign({},t),t.extrudePath!==void 0&&(e.options.extrudePath=t.extrudePath.toJSON()),e}var Ke=class s extends xe{constructor(t=1,e=1,n=1,i=1){super(),this.type="PlaneGeometry",this.parameters={width:t,height:e,widthSegments:n,heightSegments:i};let r=t/2,a=e/2,o=Math.floor(n),l=Math.floor(i),c=o+1,h=l+1,u=t/o,d=e/l,f=[],p=[],y=[],g=[];for(let m=0;m<h;m++){let S=m*d-a;for(let T=0;T<c;T++){let v=T*u-r;p.push(v,-S,0),y.push(0,0,1),g.push(T/o),g.push(1-m/l)}}for(let m=0;m<l;m++)for(let S=0;S<o;S++){let T=S+c*m,v=S+c*(m+1),b=S+1+c*(m+1),w=S+1+c*m;f.push(T,v,w),f.push(v,b,w)}this.setIndex(f),this.setAttribute("position",new Zt(p,3)),this.setAttribute("normal",new Zt(y,3)),this.setAttribute("uv",new Zt(g,2))}copy(t){return super.copy(t),this.parameters=Object.assign({},t.parameters),this}static fromJSON(t){return new s(t.width,t.height,t.widthSegments,t.heightSegments)}},Fr=class s extends xe{constructor(t=.5,e=1,n=32,i=1,r=0,a=Math.PI*2){super(),this.type="RingGeometry",this.parameters={innerRadius:t,outerRadius:e,thetaSegments:n,phiSegments:i,thetaStart:r,thetaLength:a},n=Math.max(3,n),i=Math.max(1,i);let o=[],l=[],c=[],h=[],u=t,d=(e-t)/i,f=new D,p=new rt;for(let y=0;y<=i;y++){for(let g=0;g<=n;g++){let m=r+g/n*a;f.x=u*Math.cos(m),f.y=u*Math.sin(m),l.push(f.x,f.y,f.z),c.push(0,0,1),p.x=(f.x/e+1)/2,p.y=(f.y/e+1)/2,h.push(p.x,p.y)}u+=d}for(let y=0;y<i;y++){let g=y*(n+1);for(let m=0;m<n;m++){let S=m+g,T=S,v=S+n+1,b=S+n+2,w=S+1;o.push(T,v,w),o.push(v,b,w)}}this.setIndex(o),this.setAttribute("position",new Zt(l,3)),this.setAttribute("normal",new Zt(c,3)),this.setAttribute("uv",new Zt(h,2))}copy(t){return super.copy(t),this.parameters=Object.assign({},t.parameters),this}static fromJSON(t){return new s(t.innerRadius,t.outerRadius,t.thetaSegments,t.phiSegments,t.thetaStart,t.thetaLength)}},Br=class s extends xe{constructor(t=new In([new rt(0,.5),new rt(-.5,-.5),new rt(.5,-.5)]),e=12){super(),this.type="ShapeGeometry",this.parameters={shapes:t,curveSegments:e};let n=[],i=[],r=[],a=[],o=0,l=0;if(Array.isArray(t)===!1)c(t);else for(let h=0;h<t.length;h++)c(t[h]),this.addGroup(o,l,h),o+=l,l=0;this.setIndex(n),this.setAttribute("position",new Zt(i,3)),this.setAttribute("normal",new Zt(r,3)),this.setAttribute("uv",new Zt(a,2));function c(h){let u=i.length/3,d=h.extractPoints(e),f=d.shape,p=d.holes;kn.isClockWise(f)===!1&&(f=f.reverse());for(let g=0,m=p.length;g<m;g++){let S=p[g];kn.isClockWise(S)===!0&&(p[g]=S.reverse())}let y=kn.triangulateShape(f,p);for(let g=0,m=p.length;g<m;g++){let S=p[g];f=f.concat(S)}for(let g=0,m=f.length;g<m;g++){let S=f[g];i.push(S.x,S.y,0),r.push(0,0,1),a.push(S.x,S.y)}for(let g=0,m=y.length;g<m;g++){let S=y[g],T=S[0]+u,v=S[1]+u,b=S[2]+u;n.push(T,v,b),l+=3}}}copy(t){return super.copy(t),this.parameters=Object.assign({},t.parameters),this}toJSON(){let t=super.toJSON(),e=this.parameters.shapes;return $f(e,t)}static fromJSON(t,e){let n=[];for(let i=0,r=t.shapes.length;i<r;i++){let a=e[t.shapes[i]];n.push(a)}return new s(n,t.curveSegments)}};function $f(s,t){if(t.shapes=[],Array.isArray(s))for(let e=0,n=s.length;e<n;e++){let i=s[e];t.shapes.push(i.uuid)}else t.shapes.push(s.uuid);return t}var sn=class s extends xe{constructor(t=1,e=32,n=16,i=0,r=Math.PI*2,a=0,o=Math.PI){super(),this.type="SphereGeometry",this.parameters={radius:t,widthSegments:e,heightSegments:n,phiStart:i,phiLength:r,thetaStart:a,thetaLength:o},e=Math.max(3,Math.floor(e)),n=Math.max(2,Math.floor(n));let l=Math.min(a+o,Math.PI),c=0,h=[],u=new D,d=new D,f=[],p=[],y=[],g=[];for(let m=0;m<=n;m++){let S=[],T=m/n,v=a+T*o,b=t*Math.cos(v),w=Math.sqrt(t*t-b*b),P=0;m===0&&a===0?P=.5/e:m===n&&l===Math.PI&&(P=-.5/e);for(let _=0;_<=e;_++){let E=_/e,C=i+E*r;u.x=-w*Math.cos(C),u.y=b,u.z=w*Math.sin(C),p.push(u.x,u.y,u.z),d.copy(u).normalize(),y.push(d.x,d.y,d.z),g.push(E+P,1-T),S.push(c++)}h.push(S)}for(let m=0;m<n;m++)for(let S=0;S<e;S++){let T=h[m][S+1],v=h[m][S],b=h[m+1][S],w=h[m+1][S+1];(m!==0||a>0)&&f.push(T,v,w),(m!==n-1||l<Math.PI)&&f.push(v,b,w)}this.setIndex(f),this.setAttribute("position",new Zt(p,3)),this.setAttribute("normal",new Zt(y,3)),this.setAttribute("uv",new Zt(g,2))}copy(t){return super.copy(t),this.parameters=Object.assign({},t.parameters),this}static fromJSON(t){return new s(t.radius,t.widthSegments,t.heightSegments,t.phiStart,t.phiLength,t.thetaStart,t.thetaLength)}};var Wi=class s extends xe{constructor(t=1,e=.4,n=12,i=48,r=Math.PI*2,a=0,o=Math.PI*2){super(),this.type="TorusGeometry",this.parameters={radius:t,tube:e,radialSegments:n,tubularSegments:i,arc:r,thetaStart:a,thetaLength:o},n=Math.floor(n),i=Math.floor(i);let l=[],c=[],h=[],u=[],d=new D,f=new D,p=new D;for(let y=0;y<=n;y++){let g=a+y/n*o;for(let m=0;m<=i;m++){let S=m/i*r;f.x=(t+e*Math.cos(g))*Math.cos(S),f.y=(t+e*Math.cos(g))*Math.sin(S),f.z=e*Math.sin(g),c.push(f.x,f.y,f.z),d.x=t*Math.cos(S),d.y=t*Math.sin(S),p.subVectors(f,d).normalize(),h.push(p.x,p.y,p.z),u.push(m/i),u.push(y/n)}}for(let y=1;y<=n;y++)for(let g=1;g<=i;g++){let m=(i+1)*y+g-1,S=(i+1)*(y-1)+g-1,T=(i+1)*(y-1)+g,v=(i+1)*y+g;l.push(m,S,v),l.push(S,T,v)}this.setIndex(l),this.setAttribute("position",new Zt(c,3)),this.setAttribute("normal",new Zt(h,3)),this.setAttribute("uv",new Zt(u,2))}copy(t){return super.copy(t),this.parameters=Object.assign({},t.parameters),this}static fromJSON(t){return new s(t.radius,t.tube,t.radialSegments,t.tubularSegments,t.arc,t.thetaStart,t.thetaLength)}};var Xn=class s extends xe{constructor(t=new Pr(new D(-1,-1,0),new D(-1,1,0),new D(1,1,0)),e=64,n=1,i=8,r=!1){super(),this.type="TubeGeometry",this.parameters={path:t,tubularSegments:e,radius:n,radialSegments:i,closed:r};let a=t.computeFrenetFrames(e,r);this.tangents=a.tangents,this.normals=a.normals,this.binormals=a.binormals;let o=new D,l=new D,c=new rt,h=new D,u=[],d=[],f=[],p=[];y(),this.setIndex(p),this.setAttribute("position",new Zt(u,3)),this.setAttribute("normal",new Zt(d,3)),this.setAttribute("uv",new Zt(f,2));function y(){for(let T=0;T<e;T++)g(T);g(r===!1?e:0),S(),m()}function g(T){h=t.getPointAt(T/e,h);let v=a.normals[T],b=a.binormals[T];for(let w=0;w<=i;w++){let P=w/i*Math.PI*2,_=Math.sin(P),E=-Math.cos(P);l.x=E*v.x+_*b.x,l.y=E*v.y+_*b.y,l.z=E*v.z+_*b.z,l.normalize(),d.push(l.x,l.y,l.z),o.x=h.x+n*l.x,o.y=h.y+n*l.y,o.z=h.z+n*l.z,u.push(o.x,o.y,o.z)}}function m(){for(let T=1;T<=e;T++)for(let v=1;v<=i;v++){let b=(i+1)*(T-1)+(v-1),w=(i+1)*T+(v-1),P=(i+1)*T+v,_=(i+1)*(T-1)+v;p.push(b,w,_),p.push(w,P,_)}}function S(){for(let T=0;T<=e;T++)for(let v=0;v<=i;v++)c.x=T/e,c.y=v/i,f.push(c.x,c.y)}}copy(t){return super.copy(t),this.parameters=Object.assign({},t.parameters),this}toJSON(){let t=super.toJSON();return t.path=this.parameters.path.toJSON(),t}static fromJSON(t){return new s(new ao[t.path.type]().fromJSON(t.path),t.tubularSegments,t.radius,t.radialSegments,t.closed)}};function Ki(s){let t={};for(let e in s){t[e]={};for(let n in s[e]){let i=s[e][n];if(Qh(i))i.isRenderTargetTexture?(qt("UniformsUtils: Textures of render targets cannot be cloned via cloneUniforms() or mergeUniforms()."),t[e][n]=null):t[e][n]=i.clone();else if(Array.isArray(i))if(Qh(i[0])){let r=[];for(let a=0,o=i.length;a<o;a++)r[a]=i[a].clone();t[e][n]=r}else t[e][n]=i.slice();else t[e][n]=i}}return t}function Qe(s){let t={};for(let e=0;e<s.length;e++){let n=Ki(s[e]);for(let i in n)t[i]=n[i]}return t}function Qh(s){return s&&(s.isColor||s.isMatrix3||s.isMatrix4||s.isVector2||s.isVector3||s.isVector4||s.isTexture||s.isQuaternion)}function Kf(s){let t=[];for(let e=0;e<s.length;e++)t.push(s[e].clone());return t}function Oc(s){let t=s.getRenderTarget();return t===null?s.outputColorSpace:t.isXRRenderTarget===!0?t.texture.colorSpace:ne.workingColorSpace}var si={clone:Ki,merge:Qe},Qf=`void main() {
	gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 );
}`,jf=`void main() {
	gl_FragColor = vec4( 1.0, 0.0, 0.0, 1.0 );
}`,Ce=class extends Wn{constructor(t){super(),this.isShaderMaterial=!0,this.type="ShaderMaterial",this.defines={},this.uniforms={},this.uniformsGroups=[],this.vertexShader=Qf,this.fragmentShader=jf,this.linewidth=1,this.wireframe=!1,this.wireframeLinewidth=1,this.fog=!1,this.lights=!1,this.clipping=!1,this.forceSinglePass=!0,this.extensions={clipCullDistance:!1,multiDraw:!1},this.defaultAttributeValues={color:[1,1,1],uv:[0,0],uv1:[0,0]},this.index0AttributeName=void 0,this.uniformsNeedUpdate=!1,this.glslVersion=null,t!==void 0&&this.setValues(t)}copy(t){return super.copy(t),this.fragmentShader=t.fragmentShader,this.vertexShader=t.vertexShader,this.uniforms=Ki(t.uniforms),this.uniformsGroups=Kf(t.uniformsGroups),this.defines=Object.assign({},t.defines),this.wireframe=t.wireframe,this.wireframeLinewidth=t.wireframeLinewidth,this.fog=t.fog,this.lights=t.lights,this.clipping=t.clipping,this.extensions=Object.assign({},t.extensions),this.glslVersion=t.glslVersion,this.defaultAttributeValues=Object.assign({},t.defaultAttributeValues),this.index0AttributeName=t.index0AttributeName,this.uniformsNeedUpdate=t.uniformsNeedUpdate,this}toJSON(t){let e=super.toJSON(t);e.glslVersion=this.glslVersion,e.uniforms={};for(let i in this.uniforms){let a=this.uniforms[i].value;a&&a.isTexture?e.uniforms[i]={type:"t",value:a.toJSON(t).uuid}:a&&a.isColor?e.uniforms[i]={type:"c",value:a.getHex()}:a&&a.isVector2?e.uniforms[i]={type:"v2",value:a.toArray()}:a&&a.isVector3?e.uniforms[i]={type:"v3",value:a.toArray()}:a&&a.isVector4?e.uniforms[i]={type:"v4",value:a.toArray()}:a&&a.isMatrix3?e.uniforms[i]={type:"m3",value:a.toArray()}:a&&a.isMatrix4?e.uniforms[i]={type:"m4",value:a.toArray()}:e.uniforms[i]={value:a}}Object.keys(this.defines).length>0&&(e.defines=this.defines),e.vertexShader=this.vertexShader,e.fragmentShader=this.fragmentShader,e.lights=this.lights,e.clipping=this.clipping;let n={};for(let i in this.extensions)this.extensions[i]===!0&&(n[i]=!0);return Object.keys(n).length>0&&(e.extensions=n),e}fromJSON(t,e){if(super.fromJSON(t,e),t.uniforms!==void 0)for(let n in t.uniforms){let i=t.uniforms[n];switch(this.uniforms[n]={},i.type){case"t":this.uniforms[n].value=e[i.value]||null;break;case"c":this.uniforms[n].value=new Ft().setHex(i.value);break;case"v2":this.uniforms[n].value=new rt().fromArray(i.value);break;case"v3":this.uniforms[n].value=new D().fromArray(i.value);break;case"v4":this.uniforms[n].value=new Se().fromArray(i.value);break;case"m3":this.uniforms[n].value=new $t().fromArray(i.value);break;case"m4":this.uniforms[n].value=new fe().fromArray(i.value);break;default:this.uniforms[n].value=i.value}}if(t.defines!==void 0&&(this.defines=t.defines),t.vertexShader!==void 0&&(this.vertexShader=t.vertexShader),t.fragmentShader!==void 0&&(this.fragmentShader=t.fragmentShader),t.glslVersion!==void 0&&(this.glslVersion=t.glslVersion),t.extensions!==void 0)for(let n in t.extensions)this.extensions[n]=t.extensions[n];return t.lights!==void 0&&(this.lights=t.lights),t.clipping!==void 0&&(this.clipping=t.clipping),this}},Ds=class extends Ce{constructor(t){super(t),this.isRawShaderMaterial=!0,this.type="RawShaderMaterial"}},Vt=class extends Wn{constructor(t){super(),this.isMeshStandardMaterial=!0,this.type="MeshStandardMaterial",this.defines={STANDARD:""},this.color=new Ft(16777215),this.roughness=1,this.metalness=0,this.map=null,this.lightMap=null,this.lightMapIntensity=1,this.aoMap=null,this.aoMapIntensity=1,this.emissive=new Ft(0),this.emissiveIntensity=1,this.emissiveMap=null,this.bumpMap=null,this.bumpScale=1,this.normalMap=null,this.normalMapType=sa,this.normalScale=new rt(1,1),this.displacementMap=null,this.displacementScale=1,this.displacementBias=0,this.roughnessMap=null,this.metalnessMap=null,this.alphaMap=null,this.envMap=null,this.envMapRotation=new mn,this.envMapIntensity=1,this.wireframe=!1,this.wireframeLinewidth=1,this.wireframeLinecap="round",this.wireframeLinejoin="round",this.flatShading=!1,this.fog=!0,this.setValues(t)}copy(t){return super.copy(t),this.defines={STANDARD:""},this.color.copy(t.color),this.roughness=t.roughness,this.metalness=t.metalness,this.map=t.map,this.lightMap=t.lightMap,this.lightMapIntensity=t.lightMapIntensity,this.aoMap=t.aoMap,this.aoMapIntensity=t.aoMapIntensity,this.emissive.copy(t.emissive),this.emissiveMap=t.emissiveMap,this.emissiveIntensity=t.emissiveIntensity,this.bumpMap=t.bumpMap,this.bumpScale=t.bumpScale,this.normalMap=t.normalMap,this.normalMapType=t.normalMapType,this.normalScale.copy(t.normalScale),this.displacementMap=t.displacementMap,this.displacementScale=t.displacementScale,this.displacementBias=t.displacementBias,this.roughnessMap=t.roughnessMap,this.metalnessMap=t.metalnessMap,this.alphaMap=t.alphaMap,this.envMap=t.envMap,this.envMapRotation.copy(t.envMapRotation),this.envMapIntensity=t.envMapIntensity,this.wireframe=t.wireframe,this.wireframeLinewidth=t.wireframeLinewidth,this.wireframeLinecap=t.wireframeLinecap,this.wireframeLinejoin=t.wireframeLinejoin,this.flatShading=t.flatShading,this.fog=t.fog,this}};var Xi=class extends Wn{constructor(t){super(),this.isMeshLambertMaterial=!0,this.type="MeshLambertMaterial",this.color=new Ft(16777215),this.map=null,this.lightMap=null,this.lightMapIntensity=1,this.aoMap=null,this.aoMapIntensity=1,this.emissive=new Ft(0),this.emissiveIntensity=1,this.emissiveMap=null,this.bumpMap=null,this.bumpScale=1,this.normalMap=null,this.normalMapType=sa,this.normalScale=new rt(1,1),this.displacementMap=null,this.displacementScale=1,this.displacementBias=0,this.specularMap=null,this.alphaMap=null,this.envMap=null,this.envMapRotation=new mn,this.combine=Eo,this.reflectivity=1,this.envMapIntensity=1,this.refractionRatio=.98,this.wireframe=!1,this.wireframeLinewidth=1,this.wireframeLinecap="round",this.wireframeLinejoin="round",this.flatShading=!1,this.fog=!0,this.setValues(t)}copy(t){return super.copy(t),this.color.copy(t.color),this.map=t.map,this.lightMap=t.lightMap,this.lightMapIntensity=t.lightMapIntensity,this.aoMap=t.aoMap,this.aoMapIntensity=t.aoMapIntensity,this.emissive.copy(t.emissive),this.emissiveMap=t.emissiveMap,this.emissiveIntensity=t.emissiveIntensity,this.bumpMap=t.bumpMap,this.bumpScale=t.bumpScale,this.normalMap=t.normalMap,this.normalMapType=t.normalMapType,this.normalScale.copy(t.normalScale),this.displacementMap=t.displacementMap,this.displacementScale=t.displacementScale,this.displacementBias=t.displacementBias,this.specularMap=t.specularMap,this.alphaMap=t.alphaMap,this.envMap=t.envMap,this.envMapRotation.copy(t.envMapRotation),this.combine=t.combine,this.reflectivity=t.reflectivity,this.envMapIntensity=t.envMapIntensity,this.refractionRatio=t.refractionRatio,this.wireframe=t.wireframe,this.wireframeLinewidth=t.wireframeLinewidth,this.wireframeLinecap=t.wireframeLinecap,this.wireframeLinejoin=t.wireframeLinejoin,this.flatShading=t.flatShading,this.fog=t.fog,this}},lo=class extends Wn{constructor(t){super(),this.isMeshDepthMaterial=!0,this.type="MeshDepthMaterial",this.depthPacking=Ru,this.map=null,this.alphaMap=null,this.displacementMap=null,this.displacementScale=1,this.displacementBias=0,this.wireframe=!1,this.wireframeLinewidth=1,this.setValues(t)}copy(t){return super.copy(t),this.depthPacking=t.depthPacking,this.map=t.map,this.alphaMap=t.alphaMap,this.displacementMap=t.displacementMap,this.displacementScale=t.displacementScale,this.displacementBias=t.displacementBias,this.wireframe=t.wireframe,this.wireframeLinewidth=t.wireframeLinewidth,this}},co=class extends Wn{constructor(t){super(),this.isMeshDistanceMaterial=!0,this.type="MeshDistanceMaterial",this.map=null,this.alphaMap=null,this.displacementMap=null,this.displacementScale=1,this.displacementBias=0,this.setValues(t)}copy(t){return super.copy(t),this.map=t.map,this.alphaMap=t.alphaMap,this.displacementMap=t.displacementMap,this.displacementScale=t.displacementScale,this.displacementBias=t.displacementBias,this}};function ys(s,t){return!s||s.constructor===t?s:typeof t.BYTES_PER_ELEMENT=="number"?new t(s):Array.prototype.slice.call(s)}function cc(s){return s!==void 0&&s.inTangents!==void 0&&s.outTangents!==void 0}var yi=class{constructor(t,e,n,i){this.parameterPositions=t,this._cachedIndex=0,this.resultBuffer=i!==void 0?i:new e.constructor(n),this.sampleValues=e,this.valueSize=n,this.settings=null,this.DefaultSettings_={}}evaluate(t){let e=this.parameterPositions,n=this._cachedIndex,i=e[n],r=e[n-1];n:{t:{let a;e:{i:if(!(t<i)){for(let o=n+2;;){if(i===void 0){if(t<r)break i;return n=e.length,this._cachedIndex=n,this.copySampleValue_(n-1)}if(n===o)break;if(r=i,i=e[++n],t<i)break t}a=e.length;break e}if(!(t>=r)){let o=e[1];t<o&&(n=2,r=o);for(let l=n-2;;){if(r===void 0)return this._cachedIndex=0,this.copySampleValue_(0);if(n===l)break;if(i=r,r=e[--n-1],t>=r)break t}a=n,n=0;break e}break n}for(;n<a;){let o=n+a>>>1;t<e[o]?a=o:n=o+1}if(i=e[n],r=e[n-1],r===void 0)return this._cachedIndex=0,this.copySampleValue_(0);if(i===void 0)return n=e.length,this._cachedIndex=n,this.copySampleValue_(n-1)}this._cachedIndex=n,this.intervalChanged_(n,r,i)}return this.interpolate_(n,r,t,i)}getSettings_(){return this.settings||this.DefaultSettings_}copySampleValue_(t){let e=this.resultBuffer,n=this.sampleValues,i=this.valueSize,r=t*i;for(let a=0;a!==i;++a)e[a]=n[r+a];return e}interpolate_(){throw new Error("THREE.Interpolant: Call to abstract method.")}intervalChanged_(){}},ho=class extends yi{constructor(t,e,n,i){super(t,e,n,i),this._weightPrev=-0,this._offsetPrev=-0,this._weightNext=-0,this._offsetNext=-0,this.DefaultSettings_={endingStart:dc,endingEnd:dc}}intervalChanged_(t,e,n){let i=this.parameterPositions,r=t-2,a=t+1,o=i[r],l=i[a];if(o===void 0)switch(this.getSettings_().endingStart){case fc:r=t,o=2*e-n;break;case pc:r=i.length-2,o=e+i[r]-i[r+1];break;default:r=t,o=n}if(l===void 0)switch(this.getSettings_().endingEnd){case fc:a=t,l=2*n-e;break;case pc:a=1,l=n+i[1]-i[0];break;default:a=t-1,l=e}let c=(n-e)*.5,h=this.valueSize;this._weightPrev=c/(e-o),this._weightNext=c/(l-n),this._offsetPrev=r*h,this._offsetNext=a*h}interpolate_(t,e,n,i){let r=this.resultBuffer,a=this.sampleValues,o=this.valueSize,l=t*o,c=l-o,h=this._offsetPrev,u=this._offsetNext,d=this._weightPrev,f=this._weightNext,p=(n-e)/(i-e),y=p*p,g=y*p,m=-d*g+2*d*y-d*p,S=(1+d)*g+(-1.5-2*d)*y+(-.5+d)*p+1,T=(-1-f)*g+(1.5+f)*y+.5*p,v=f*g-f*y;for(let b=0;b!==o;++b)r[b]=m*a[h+b]+S*a[c+b]+T*a[l+b]+v*a[u+b];return r}},uo=class extends yi{constructor(t,e,n,i){super(t,e,n,i)}interpolate_(t,e,n,i){let r=this.resultBuffer,a=this.sampleValues,o=this.valueSize,l=t*o,c=l-o,h=(n-e)/(i-e),u=1-h;for(let d=0;d!==o;++d)r[d]=a[c+d]*u+a[l+d]*h;return r}},fo=class extends yi{constructor(t,e,n,i){super(t,e,n,i)}interpolate_(t){return this.copySampleValue_(t-1)}},po=class extends yi{interpolate_(t,e,n,i){let r=this.resultBuffer,a=this.sampleValues,o=this.valueSize,l=t*o,c=l-o,h=this.inTangents,u=this.outTangents;if(!h||!u){let p=(n-e)/(i-e),y=1-p;for(let g=0;g!==o;++g)r[g]=a[c+g]*y+a[l+g]*p;return r}let d=o*2,f=t-1;for(let p=0;p!==o;++p){let y=a[c+p],g=a[l+p],m=f*d+p*2,S=u[m],T=u[m+1],v=t*d+p*2,b=h[v],w=h[v+1],P=ep(n,e,S,b,i);r[p]=Yu(P,y,T,w,g)}return r}};function Yu(s,t,e,n,i){let r=1-s;return r*r*r*t+3*r*r*s*e+3*r*s*s*n+s*s*s*i}function tp(s,t,e,n,i){let r=1-s;return 3*r*r*(e-t)+6*r*s*(n-e)+3*s*s*(i-n)}function ep(s,t,e,n,i){let r=(s-t)/(i-t);for(let a=0;a<8;a++){let o=Yu(r,t,e,n,i)-s;if(Math.abs(o)<1e-10)break;let l=tp(r,t,e,n,i);if(Math.abs(l)<1e-10)break;r=Math.max(0,Math.min(1,r-o/l))}return r}var hn=class{constructor(t,e,n,i){if(t===void 0)throw new Error("THREE.KeyframeTrack: track name is undefined");if(e===void 0||e.length===0)throw new Error("THREE.KeyframeTrack: no keyframes in track named "+t);this.name=t,this.times=ys(e,this.TimeBufferType),this.values=ys(n,this.ValueBufferType),this.setInterpolation(i||this.DefaultInterpolation)}static toJSON(t){let e=t.constructor,n;if(e.toJSON!==this.toJSON)n=e.toJSON(t);else{n={name:t.name,times:ys(t.times,Array),values:ys(t.values,Array)};let i=t.getInterpolation();i!==t.DefaultInterpolation&&(n.interpolation=i),cc(t.settings)&&(n.settings={inTangents:ys(t.settings.inTangents,Array),outTangents:ys(t.settings.outTangents,Array)})}return n.type=t.ValueTypeName,n}InterpolantFactoryMethodDiscrete(t){return new fo(this.times,this.values,this.getValueSize(),t)}InterpolantFactoryMethodLinear(t){return new uo(this.times,this.values,this.getValueSize(),t)}InterpolantFactoryMethodSmooth(t){return new ho(this.times,this.values,this.getValueSize(),t)}InterpolantFactoryMethodBezier(t){let e=new po(this.times,this.values,this.getValueSize(),t);return this.settings&&(e.inTangents=this.settings.inTangents,e.outTangents=this.settings.outTangents),e}setInterpolation(t){let e;switch(t){case ur:e=this.InterpolantFactoryMethodDiscrete;break;case Ka:e=this.InterpolantFactoryMethodLinear;break;case Ha:e=this.InterpolantFactoryMethodSmooth;break;case uc:e=this.InterpolantFactoryMethodBezier;break}if(e===void 0){let n="unsupported interpolation for "+this.ValueTypeName+" keyframe track named "+this.name;if(this.createInterpolant===void 0)if(t!==this.DefaultInterpolation)this.setInterpolation(this.DefaultInterpolation);else throw new Error(n);return qt("KeyframeTrack:",n),this}return this.createInterpolant=e,this}getInterpolation(){switch(this.createInterpolant){case this.InterpolantFactoryMethodDiscrete:return ur;case this.InterpolantFactoryMethodLinear:return Ka;case this.InterpolantFactoryMethodSmooth:return Ha;case this.InterpolantFactoryMethodBezier:return uc}}getValueSize(){return this.values.length/this.times.length}shift(t){if(t!==0){let e=this.times;for(let n=0,i=e.length;n!==i;++n)e[n]+=t}return this}scale(t){if(t!==1){let e=this.times;for(let n=0,i=e.length;n!==i;++n)e[n]*=t;cc(this.settings)&&(jh(this.settings.inTangents,t),jh(this.settings.outTangents,t))}return this}trim(t,e){let n=this.times,i=n.length,r=0,a=i-1;for(;r!==i&&n[r]<t;)++r;for(;a!==-1&&n[a]>e;)--a;if(++a,r!==0||a!==i){r>=a&&(a=Math.max(a,1),r=a-1);let o=this.getValueSize();this.times=n.slice(r,a),this.values=this.values.slice(r*o,a*o)}return this}validate(){let t=!0,e=this.getValueSize();e-Math.floor(e)!==0&&(Xt("KeyframeTrack: Invalid value size in track.",this),t=!1);let n=this.times,i=this.values,r=n.length;r===0&&(Xt("KeyframeTrack: Track is empty.",this),t=!1);let a=null;for(let o=0;o!==r;o++){let l=n[o];if(typeof l=="number"&&isNaN(l)){Xt("KeyframeTrack: Time is not a valid number.",this,o,l),t=!1;break}if(a!==null&&a>l){Xt("KeyframeTrack: Out of order keys.",this,o,l,a),t=!1;break}a=l}if(i!==void 0&&nf(i))for(let o=0,l=i.length;o!==l;++o){let c=i[o];if(isNaN(c)){Xt("KeyframeTrack: Value is not a valid number.",this,o,c),t=!1;break}}return t}optimize(){let t=this.times.slice(),e=this.values.slice(),n=this.getValueSize(),i=this.getInterpolation()===Ha,r=t.length-1,a=1;for(let o=1;o<r;++o){let l=!1,c=t[o],h=t[o+1];if(c!==h&&(o!==1||c!==t[0]))if(i)l=!0;else{let u=o*n,d=u-n,f=u+n;for(let p=0;p!==n;++p){let y=e[u+p];if(y!==e[d+p]||y!==e[f+p]){l=!0;break}}}if(l){if(o!==a){t[a]=t[o];let u=o*n,d=a*n;for(let f=0;f!==n;++f)e[d+f]=e[u+f]}++a}}if(r>0){t[a]=t[r];for(let o=r*n,l=a*n,c=0;c!==n;++c)e[l+c]=e[o+c];++a}return a!==t.length?(this.times=t.slice(0,a),this.values=e.slice(0,a*n)):(this.times=t,this.values=e),this}clone(){let t=this.times.slice(),e=this.values.slice(),n=this.constructor,i=new n(this.name,t,e);return i.createInterpolant=this.createInterpolant,cc(this.settings)&&(i.settings={inTangents:this.settings.inTangents.slice(),outTangents:this.settings.outTangents.slice()}),i}};function jh(s,t){for(let e=0,n=s.length;e!==n;e+=2)s[e]*=t}hn.prototype.ValueTypeName="";hn.prototype.TimeBufferType=Float32Array;hn.prototype.ValueBufferType=Float32Array;hn.prototype.DefaultInterpolation=Ka;var vi=class extends hn{constructor(t,e,n){super(t,e,n)}};vi.prototype.ValueTypeName="bool";vi.prototype.ValueBufferType=Array;vi.prototype.DefaultInterpolation=ur;vi.prototype.InterpolantFactoryMethodLinear=void 0;vi.prototype.InterpolantFactoryMethodSmooth=void 0;var mo=class extends hn{constructor(t,e,n,i){super(t,e,n,i)}};mo.prototype.ValueTypeName="color";var go=class extends hn{constructor(t,e,n,i){super(t,e,n,i)}};go.prototype.ValueTypeName="number";var xo=class extends yi{constructor(t,e,n,i){super(t,e,n,i)}interpolate_(t,e,n,i){let r=this.resultBuffer,a=this.sampleValues,o=this.valueSize,l=(n-e)/(i-e),c=t*o;for(let h=c+o;c!==h;c+=4)pn.slerpFlat(r,0,a,c-o,a,c,l);return r}},Or=class extends hn{constructor(t,e,n,i){super(t,e,n,i)}InterpolantFactoryMethodLinear(t){return new xo(this.times,this.values,this.getValueSize(),t)}};Or.prototype.ValueTypeName="quaternion";Or.prototype.InterpolantFactoryMethodSmooth=void 0;var Mi=class extends hn{constructor(t,e,n){super(t,e,n)}};Mi.prototype.ValueTypeName="string";Mi.prototype.ValueBufferType=Array;Mi.prototype.DefaultInterpolation=ur;Mi.prototype.InterpolantFactoryMethodLinear=void 0;Mi.prototype.InterpolantFactoryMethodSmooth=void 0;var _o=class extends hn{constructor(t,e,n,i){super(t,e,n,i)}};_o.prototype.ValueTypeName="vector";var yo=class{constructor(t,e,n){let i=this,r=!1,a=0,o=0,l,c=[];this.onStart=void 0,this.onLoad=t,this.onProgress=e,this.onError=n,this._abortController=null,this.itemStart=function(h){o++,r===!1&&i.onStart!==void 0&&i.onStart(h,a,o),r=!0},this.itemEnd=function(h){a++,i.onProgress!==void 0&&i.onProgress(h,a,o),a===o&&(r=!1,i.onLoad!==void 0&&i.onLoad())},this.itemError=function(h){i.onError!==void 0&&i.onError(h)},this.resolveURL=function(h){return h=h.normalize("NFC"),l?l(h):h},this.setURLModifier=function(h){return l=h,this},this.addHandler=function(h,u){return c.push(h,u),this},this.removeHandler=function(h){let u=c.indexOf(h);return u!==-1&&c.splice(u,2),this},this.getHandler=function(h){for(let u=0,d=c.length;u<d;u+=2){let f=c[u],p=c[u+1];if(f.global&&(f.lastIndex=0),f.test(h))return p}return null},this.abort=function(){return this.abortController.abort(),this._abortController=null,this}}get abortController(){return this._abortController||(this._abortController=new AbortController),this._abortController}},Zu=new yo,vo=class{constructor(t){this.manager=t!==void 0?t:Zu,this.crossOrigin="anonymous",this.withCredentials=!1,this.path="",this.resourcePath="",this.requestHeader={},typeof __THREE_DEVTOOLS__<"u"&&__THREE_DEVTOOLS__.dispatchEvent(new CustomEvent("observe",{detail:this}))}load(){}loadAsync(t,e){let n=this;return new Promise(function(i,r){n.load(t,i,e,r)})}parse(){}setCrossOrigin(t){return this.crossOrigin=t,this}setWithCredentials(t){return this.withCredentials=t,this}setPath(t){return this.path=t,this}setResourcePath(t){return this.resourcePath=t,this}setRequestHeader(t){return this.requestHeader=t,this}abort(){return this}};vo.DEFAULT_MATERIAL_NAME="__DEFAULT";var Si=class extends Re{constructor(t,e=1){super(),this.isLight=!0,this.type="Light",this.color=new Ft(t),this.intensity=e}copy(t,e){return super.copy(t,e),this.color.copy(t.color),this.intensity=t.intensity,this}toJSON(t){let e=super.toJSON(t);return e.object.color=this.color.getHex(),e.object.intensity=this.intensity,e}},kr=class extends Si{constructor(t,e,n){super(t,n),this.isHemisphereLight=!0,this.type="HemisphereLight",this.position.copy(Re.DEFAULT_UP),this.updateMatrix(),this.groundColor=new Ft(e)}copy(t,e){return super.copy(t,e),this.groundColor.copy(t.groundColor),this}toJSON(t){let e=super.toJSON(t);return e.object.groundColor=this.groundColor.getHex(),e}},hc=new fe,tu=new D,eu=new D,Us=class{constructor(t){this.camera=t,this.intensity=1,this.bias=0,this.biasNode=null,this.normalBias=0,this.radius=1,this.blurSamples=8,this.mapSize=new rt(512,512),this.mapType=rn,this.map=null,this.mapPass=null,this.matrix=new fe,this.autoUpdate=!0,this.needsUpdate=!1,this._frustum=new Ps,this._frameExtents=new rt(1,1),this._viewportCount=1,this._viewports=[new Se(0,0,1,1)]}getViewportCount(){return this._viewportCount}getCamera(){return this.camera}getFrustum(){return this._frustum}updateMatrices(t){let e=this.camera;tu.setFromMatrixPosition(t.matrixWorld),e.position.copy(tu),eu.setFromMatrixPosition(t.target.matrixWorld),e.lookAt(eu),e.updateMatrixWorld(),this._updateMatrix(e,this.matrix,this._frustum)}_updateMatrix(t,e,n,i){hc.multiplyMatrices(t.projectionMatrix,t.matrixWorldInverse),n.setFromProjectionMatrix(hc,t.coordinateSystem,t.reversedDepth);let r=this._frameExtents,a=i?i.z/r.x:1,o=i?i.w/r.y:1,l=i?i.x/r.x:0,c=i?i.y/r.y:0;t.coordinateSystem===Es||t.reversedDepth?e.set(.5*a,0,0,.5*a+l,0,.5*o,0,.5*o+c,0,0,1,0,0,0,0,1):e.set(.5*a,0,0,.5*a+l,0,.5*o,0,.5*o+c,0,0,.5,.5,0,0,0,1),e.multiply(hc)}getViewport(t){return this._viewports[t]}getFrameExtents(){return this._frameExtents}dispose(){this.map&&this.map.dispose(),this.mapPass&&this.mapPass.dispose()}copy(t){return this.camera=t.camera.clone(),this.intensity=t.intensity,this.bias=t.bias,this.radius=t.radius,this.autoUpdate=t.autoUpdate,this.needsUpdate=t.needsUpdate,this.normalBias=t.normalBias,this.blurSamples=t.blurSamples,this.mapSize.copy(t.mapSize),this.biasNode=t.biasNode,this}clone(){return new this.constructor().copy(this)}toJSON(){let t={};return t.intensity=this.intensity,t.bias=this.bias,t.normalBias=this.normalBias,t.radius=this.radius,t.blurSamples=this.blurSamples,t.mapSize=this.mapSize.toArray(),t.camera=this.camera.toJSON(!1).object,delete t.camera.matrix,t}},ka=new D,za=new pn,Fn=new D,zr=class extends Re{constructor(){super(),this.isCamera=!0,this.type="Camera",this.matrixWorldInverse=new fe,this.projectionMatrix=new fe,this.projectionMatrixInverse=new fe,this.coordinateSystem=Cn,this._reversedDepth=!1}get reversedDepth(){return this._reversedDepth}copy(t,e){return super.copy(t,e),this.matrixWorldInverse.copy(t.matrixWorldInverse),this.projectionMatrix.copy(t.projectionMatrix),this.projectionMatrixInverse.copy(t.projectionMatrixInverse),this.coordinateSystem=t.coordinateSystem,this}getWorldDirection(t){return super.getWorldDirection(t).negate()}updateMatrixWorld(t){super.updateMatrixWorld(t),this.matrixWorld.decompose(ka,za,Fn),Fn.x===1&&Fn.y===1&&Fn.z===1?this.matrixWorldInverse.copy(this.matrixWorld).invert():this.matrixWorldInverse.compose(ka,za,Fn.set(1,1,1)).invert()}updateWorldMatrix(t,e,n=!1){super.updateWorldMatrix(t,e,n),this.matrixWorld.decompose(ka,za,Fn),Fn.x===1&&Fn.y===1&&Fn.z===1?this.matrixWorldInverse.copy(this.matrixWorld).invert():this.matrixWorldInverse.compose(ka,za,Fn.set(1,1,1)).invert()}clone(){return new this.constructor().copy(this)}},mi=new D,nu=new rt,iu=new rt,Oe=class extends zr{constructor(t=50,e=1,n=.1,i=2e3){super(),this.isPerspectiveCamera=!0,this.type="PerspectiveCamera",this.fov=t,this.zoom=1,this.near=n,this.far=i,this.focus=10,this.aspect=e,this.view=null,this.filmGauge=35,this.filmOffset=0,this.updateProjectionMatrix()}copy(t,e){return super.copy(t,e),this.fov=t.fov,this.zoom=t.zoom,this.near=t.near,this.far=t.far,this.focus=t.focus,this.aspect=t.aspect,this.view=t.view===null?null:Object.assign({},t.view),this.filmGauge=t.filmGauge,this.filmOffset=t.filmOffset,this}setFocalLength(t){let e=.5*this.getFilmHeight()/t;this.fov=gr*2*Math.atan(e),this.updateProjectionMatrix()}getFocalLength(){let t=Math.tan(Ol*.5*this.fov);return .5*this.getFilmHeight()/t}getEffectiveFOV(){return gr*2*Math.atan(Math.tan(Ol*.5*this.fov)/this.zoom)}getFilmWidth(){return this.filmGauge*Math.min(this.aspect,1)}getFilmHeight(){return this.filmGauge/Math.max(this.aspect,1)}getViewBounds(t,e,n){mi.set(-1,-1,.5).applyMatrix4(this.projectionMatrixInverse),e.set(mi.x,mi.y).multiplyScalar(-t/mi.z),mi.set(1,1,.5).applyMatrix4(this.projectionMatrixInverse),n.set(mi.x,mi.y).multiplyScalar(-t/mi.z)}getViewSize(t,e){return this.getViewBounds(t,nu,iu),e.subVectors(iu,nu)}setViewOffset(t,e,n,i,r,a){this.aspect=t/e,this.view===null&&(this.view={enabled:!0,fullWidth:1,fullHeight:1,offsetX:0,offsetY:0,width:1,height:1}),this.view.enabled=!0,this.view.fullWidth=t,this.view.fullHeight=e,this.view.offsetX=n,this.view.offsetY=i,this.view.width=r,this.view.height=a,this.updateProjectionMatrix()}clearViewOffset(){this.view!==null&&(this.view.enabled=!1),this.updateProjectionMatrix()}updateProjectionMatrix(){let t=this.near,e=t*Math.tan(Ol*.5*this.fov)/this.zoom,n=2*e,i=this.aspect*n,r=-.5*i,a=this.view;if(this.view!==null&&this.view.enabled){let l=a.fullWidth,c=a.fullHeight;r+=a.offsetX*i/l,e-=a.offsetY*n/c,i*=a.width/l,n*=a.height/c}let o=this.filmOffset;o!==0&&(r+=t*o/this.getFilmWidth()),this.projectionMatrix.makePerspective(r,r+i,e,e-n,t,this.far,this.coordinateSystem,this.reversedDepth),this.projectionMatrixInverse.copy(this.projectionMatrix).invert()}toJSON(t){let e=super.toJSON(t);return e.object.fov=this.fov,e.object.zoom=this.zoom,e.object.near=this.near,e.object.far=this.far,e.object.focus=this.focus,e.object.aspect=this.aspect,this.view!==null&&(e.object.view=Object.assign({},this.view)),e.object.filmGauge=this.filmGauge,e.object.filmOffset=this.filmOffset,e}},_c=class extends Us{constructor(){super(new Oe(50,1,.5,500)),this.isSpotLightShadow=!0,this.focus=1,this.aspect=1}updateMatrices(t){let e=this.camera,n=gr*2*t.angle*this.focus,i=this.mapSize.width/this.mapSize.height*this.aspect,r=t.distance||e.far;(n!==e.fov||i!==e.aspect||r!==e.far)&&(e.fov=n,e.aspect=i,e.far=r,e.updateProjectionMatrix()),super.updateMatrices(t)}copy(t){return super.copy(t),this.focus=t.focus,this.aspect=t.aspect,this}toJSON(){let t=super.toJSON();return t.focus=this.focus,t.aspect=this.aspect,t}},Hr=class extends Si{constructor(t,e,n=0,i=Math.PI/3,r=0,a=2){super(t,e),this.isSpotLight=!0,this.type="SpotLight",this.position.copy(Re.DEFAULT_UP),this.updateMatrix(),this.target=new Re,this.distance=n,this.angle=i,this.penumbra=r,this.decay=a,this.map=null,this.shadow=new _c}get power(){return this.intensity*Math.PI}set power(t){this.intensity=t/Math.PI}dispose(){super.dispose(),this.shadow.dispose()}copy(t,e){return super.copy(t,e),this.distance=t.distance,this.angle=t.angle,this.penumbra=t.penumbra,this.decay=t.decay,this.target=t.target.clone(),this.map=t.map,this.shadow=t.shadow.clone(),this}toJSON(t){let e=super.toJSON(t);return e.object.distance=this.distance,e.object.angle=this.angle,e.object.decay=this.decay,e.object.penumbra=this.penumbra,e.object.target=this.target.uuid,this.map&&this.map.isTexture&&(e.object.map=this.map.toJSON(t).uuid),e.object.shadow=this.shadow.toJSON(),e}},yc=class extends Us{constructor(){super(new Oe(90,1,.5,500)),this.isPointLightShadow=!0}},bi=class extends Si{constructor(t,e,n=0,i=2){super(t,e),this.isPointLight=!0,this.type="PointLight",this.distance=n,this.decay=i,this.shadow=new yc}get power(){return this.intensity*4*Math.PI}set power(t){this.intensity=t/(4*Math.PI)}dispose(){super.dispose(),this.shadow.dispose()}copy(t,e){return super.copy(t,e),this.distance=t.distance,this.decay=t.decay,this.shadow=t.shadow.clone(),this}toJSON(t){let e=super.toJSON(t);return e.object.distance=this.distance,e.object.decay=this.decay,e.object.shadow=this.shadow.toJSON(),e}},Ei=class extends zr{constructor(t=-1,e=1,n=1,i=-1,r=.1,a=2e3){super(),this.isOrthographicCamera=!0,this.type="OrthographicCamera",this.zoom=1,this.view=null,this.left=t,this.right=e,this.top=n,this.bottom=i,this.near=r,this.far=a,this.updateProjectionMatrix()}copy(t,e){return super.copy(t,e),this.left=t.left,this.right=t.right,this.top=t.top,this.bottom=t.bottom,this.near=t.near,this.far=t.far,this.zoom=t.zoom,this.view=t.view===null?null:Object.assign({},t.view),this}setViewOffset(t,e,n,i,r,a){this.view===null&&(this.view={enabled:!0,fullWidth:1,fullHeight:1,offsetX:0,offsetY:0,width:1,height:1}),this.view.enabled=!0,this.view.fullWidth=t,this.view.fullHeight=e,this.view.offsetX=n,this.view.offsetY=i,this.view.width=r,this.view.height=a,this.updateProjectionMatrix()}clearViewOffset(){this.view!==null&&(this.view.enabled=!1),this.updateProjectionMatrix()}updateProjectionMatrix(){let t=(this.right-this.left)/(2*this.zoom),e=(this.top-this.bottom)/(2*this.zoom),n=(this.right+this.left)/2,i=(this.top+this.bottom)/2,r=n-t,a=n+t,o=i+e,l=i-e;if(this.view!==null&&this.view.enabled){let c=(this.right-this.left)/this.view.fullWidth/this.zoom,h=(this.top-this.bottom)/this.view.fullHeight/this.zoom;r+=c*this.view.offsetX,a=r+c*this.view.width,o-=h*this.view.offsetY,l=o-h*this.view.height}this.projectionMatrix.makeOrthographic(r,a,o,l,this.near,this.far,this.coordinateSystem,this.reversedDepth),this.projectionMatrixInverse.copy(this.projectionMatrix).invert()}toJSON(t){let e=super.toJSON(t);return e.object.zoom=this.zoom,e.object.left=this.left,e.object.right=this.right,e.object.top=this.top,e.object.bottom=this.bottom,e.object.near=this.near,e.object.far=this.far,this.view!==null&&(e.object.view=Object.assign({},this.view)),e}},vc=class extends Us{constructor(){super(new Ei(-5,5,5,-5,.5,500)),this.isDirectionalLightShadow=!0}},Ns=class extends Si{constructor(t,e){super(t,e),this.isDirectionalLight=!0,this.type="DirectionalLight",this.position.copy(Re.DEFAULT_UP),this.updateMatrix(),this.target=new Re,this.shadow=new vc}dispose(){super.dispose(),this.shadow.dispose()}copy(t){return super.copy(t),this.target=t.target.clone(),this.shadow=t.shadow.clone(),this}toJSON(t){let e=super.toJSON(t);return e.object.shadow=this.shadow.toJSON(),e.object.target=this.target.uuid,e}},Vr=class extends Si{constructor(t,e){super(t,e),this.isAmbientLight=!0,this.type="AmbientLight"}};var vs=-90,Ms=1,Mo=class extends Re{constructor(t,e,n){super(),this.type="CubeCamera",this.renderTarget=n,this.coordinateSystem=null,this.activeMipmapLevel=0;let i=new Oe(vs,Ms,t,e);i.layers=this.layers,this.add(i);let r=new Oe(vs,Ms,t,e);r.layers=this.layers,this.add(r);let a=new Oe(vs,Ms,t,e);a.layers=this.layers,this.add(a);let o=new Oe(vs,Ms,t,e);o.layers=this.layers,this.add(o);let l=new Oe(vs,Ms,t,e);l.layers=this.layers,this.add(l);let c=new Oe(vs,Ms,t,e);c.layers=this.layers,this.add(c)}updateCoordinateSystem(){let t=this.coordinateSystem,e=this.children.concat(),[n,i,r,a,o,l]=e;for(let c of e)this.remove(c);if(t===Cn)n.up.set(0,1,0),n.lookAt(1,0,0),i.up.set(0,1,0),i.lookAt(-1,0,0),r.up.set(0,0,-1),r.lookAt(0,1,0),a.up.set(0,0,1),a.lookAt(0,-1,0),o.up.set(0,1,0),o.lookAt(0,0,1),l.up.set(0,1,0),l.lookAt(0,0,-1);else if(t===Es)n.up.set(0,-1,0),n.lookAt(-1,0,0),i.up.set(0,-1,0),i.lookAt(1,0,0),r.up.set(0,0,1),r.lookAt(0,1,0),a.up.set(0,0,-1),a.lookAt(0,-1,0),o.up.set(0,-1,0),o.lookAt(0,0,1),l.up.set(0,-1,0),l.lookAt(0,0,-1);else throw new Error("THREE.CubeCamera.updateCoordinateSystem(): Invalid coordinate system: "+t);for(let c of e)this.add(c),c.updateMatrixWorld()}update(t,e){this.parent===null&&this.updateMatrixWorld();let{renderTarget:n,activeMipmapLevel:i}=this;this.coordinateSystem!==t.coordinateSystem&&(this.coordinateSystem=t.coordinateSystem,this.updateCoordinateSystem());let[r,a,o,l,c,h]=this.children,u=t.getRenderTarget(),d=t.getActiveCubeFace(),f=t.getActiveMipmapLevel(),p=t.xr.enabled;t.xr.enabled=!1;let y=n.texture.generateMipmaps;n.texture.generateMipmaps=!1;let g=!1;t.isWebGLRenderer===!0?g=t.state.buffers.depth.getReversed():g=t.reversedDepthBuffer,t.setRenderTarget(n,0,i),g&&t.autoClear===!1&&t.clearDepth(),t.render(e,r),t.setRenderTarget(n,1,i),g&&t.autoClear===!1&&t.clearDepth(),t.render(e,a),t.setRenderTarget(n,2,i),g&&t.autoClear===!1&&t.clearDepth(),t.render(e,o),t.setRenderTarget(n,3,i),g&&t.autoClear===!1&&t.clearDepth(),t.render(e,l),t.setRenderTarget(n,4,i),g&&t.autoClear===!1&&t.clearDepth(),t.render(e,c),n.texture.generateMipmaps=y,t.setRenderTarget(n,5,i),g&&t.autoClear===!1&&t.clearDepth(),t.render(e,h),t.setRenderTarget(u,d,f),t.xr.enabled=p,n.texture.needsPMREMUpdate=!0}},So=class extends Oe{constructor(t=[]){super(),this.isArrayCamera=!0,this.isMultiViewCamera=!1,this.cameras=t}},Gr=class{constructor(){this._previousTime=0,this._currentTime=0,this._startTime=performance.now(),this._delta=0,this._elapsed=0,this._timescale=1,this._document=null,this._pageVisibilityHandler=null}connect(t){this._document=t,t.hidden!==void 0&&(this._pageVisibilityHandler=np.bind(this),t.addEventListener("visibilitychange",this._pageVisibilityHandler,!1))}disconnect(){this._pageVisibilityHandler!==null&&(this._document.removeEventListener("visibilitychange",this._pageVisibilityHandler),this._pageVisibilityHandler=null),this._document=null}getDelta(){return this._delta/1e3}getElapsed(){return this._elapsed/1e3}getTimescale(){return this._timescale}setTimescale(t){return this._timescale=t,this}reset(){return this._currentTime=performance.now()-this._startTime,this}dispose(){this.disconnect()}update(t){return this._pageVisibilityHandler!==null&&this._document.hidden===!0?this._delta=0:(this._previousTime=this._currentTime,this._currentTime=(t!==void 0?t:performance.now())-this._startTime,this._delta=(this._currentTime-this._previousTime)*this._timescale,this._elapsed+=this._delta),this}};function np(){this._document.hidden===!1&&this.reset()}var kc="\\[\\]\\.:\\/",ip=new RegExp("["+kc+"]","g"),zc="[^"+kc+"]",sp="[^"+kc.replace("\\.","")+"]",rp=/((?:WC+[\/:])*)/.source.replace("WC",zc),ap=/(WCOD+)?/.source.replace("WCOD",sp),op=/(?:\.(WC+)(?:\[(.+)\])?)?/.source.replace("WC",zc),lp=/\.(WC+)(?:\[(.+)\])?/.source.replace("WC",zc),cp=new RegExp("^"+rp+ap+op+lp+"$"),hp=["material","materials","bones","map"],Mc=class{constructor(t,e,n){let i=n||Me.parseTrackName(e);this._targetGroup=t,this._bindings=t.subscribe_(e,i)}getValue(t,e){this.bind();let n=this._targetGroup.nCachedObjects_,i=this._bindings[n];i!==void 0&&i.getValue(t,e)}setValue(t,e){let n=this._bindings;for(let i=this._targetGroup.nCachedObjects_,r=n.length;i!==r;++i)n[i].setValue(t,e)}bind(){let t=this._bindings;for(let e=this._targetGroup.nCachedObjects_,n=t.length;e!==n;++e)t[e].bind()}unbind(){let t=this._bindings;for(let e=this._targetGroup.nCachedObjects_,n=t.length;e!==n;++e)t[e].unbind()}},Me=class s{constructor(t,e,n){this.path=e,this.parsedPath=n||s.parseTrackName(e),this.node=s.findNode(t,this.parsedPath.nodeName),this.rootNode=t,this.getValue=this._getValue_unbound,this.setValue=this._setValue_unbound}static create(t,e,n){return t&&t.isAnimationObjectGroup?new s.Composite(t,e,n):new s(t,e,n)}static sanitizeNodeName(t){return t.replace(/\s/g,"_").replace(ip,"")}static parseTrackName(t){let e=cp.exec(t);if(e===null)throw new Error("THREE.PropertyBinding: Cannot parse trackName: "+t);let n={nodeName:e[2],objectName:e[3],objectIndex:e[4],propertyName:e[5],propertyIndex:e[6]},i=n.nodeName&&n.nodeName.lastIndexOf(".");if(i!==void 0&&i!==-1){let r=n.nodeName.substring(i+1);hp.indexOf(r)!==-1&&(n.nodeName=n.nodeName.substring(0,i),n.objectName=r)}if(n.propertyName===null||n.propertyName.length===0)throw new Error("THREE.PropertyBinding: can not parse propertyName from trackName: "+t);return n}static findNode(t,e){if(e===void 0||e===""||e==="."||e===-1||e===t.name||e===t.uuid)return t;if(t.skeleton){let n=t.skeleton.getBoneByName(e);if(n!==void 0)return n}if(t.children){let n=function(r){for(let a=0;a<r.length;a++){let o=r[a];if(o.name===e||o.uuid===e)return o;let l=n(o.children);if(l)return l}return null},i=n(t.children);if(i)return i}return null}_getValue_unavailable(){}_setValue_unavailable(){}_getValue_direct(t,e){t[e]=this.targetObject[this.propertyName]}_getValue_array(t,e){let n=this.resolvedProperty;for(let i=0,r=n.length;i!==r;++i)t[e++]=n[i]}_getValue_arrayElement(t,e){t[e]=this.resolvedProperty[this.propertyIndex]}_getValue_toArray(t,e){this.resolvedProperty.toArray(t,e)}_setValue_direct(t,e){this.targetObject[this.propertyName]=t[e]}_setValue_direct_setNeedsUpdate(t,e){this.targetObject[this.propertyName]=t[e],this.targetObject.needsUpdate=!0}_setValue_direct_setMatrixWorldNeedsUpdate(t,e){this.targetObject[this.propertyName]=t[e],this.targetObject.matrixWorldNeedsUpdate=!0}_setValue_array(t,e){let n=this.resolvedProperty;for(let i=0,r=n.length;i!==r;++i)n[i]=t[e++]}_setValue_array_setNeedsUpdate(t,e){let n=this.resolvedProperty;for(let i=0,r=n.length;i!==r;++i)n[i]=t[e++];this.targetObject.needsUpdate=!0}_setValue_array_setMatrixWorldNeedsUpdate(t,e){let n=this.resolvedProperty;for(let i=0,r=n.length;i!==r;++i)n[i]=t[e++];this.targetObject.matrixWorldNeedsUpdate=!0}_setValue_arrayElement(t,e){this.resolvedProperty[this.propertyIndex]=t[e]}_setValue_arrayElement_setNeedsUpdate(t,e){this.resolvedProperty[this.propertyIndex]=t[e],this.targetObject.needsUpdate=!0}_setValue_arrayElement_setMatrixWorldNeedsUpdate(t,e){this.resolvedProperty[this.propertyIndex]=t[e],this.targetObject.matrixWorldNeedsUpdate=!0}_setValue_fromArray(t,e){this.resolvedProperty.fromArray(t,e)}_setValue_fromArray_setNeedsUpdate(t,e){this.resolvedProperty.fromArray(t,e),this.targetObject.needsUpdate=!0}_setValue_fromArray_setMatrixWorldNeedsUpdate(t,e){this.resolvedProperty.fromArray(t,e),this.targetObject.matrixWorldNeedsUpdate=!0}_getValue_unbound(t,e){this.bind(),this.getValue(t,e)}_setValue_unbound(t,e){this.bind(),this.setValue(t,e)}bind(){let t=this.node,e=this.parsedPath,n=e.objectName,i=e.propertyName,r=e.propertyIndex;if(t||(t=s.findNode(this.rootNode,e.nodeName),this.node=t),this.getValue=this._getValue_unavailable,this.setValue=this._setValue_unavailable,!t){qt("PropertyBinding: No target node found for track: "+this.path+".");return}if(n){let c=e.objectIndex;switch(n){case"materials":if(!t.material){Xt("PropertyBinding: Can not bind to material as node does not have a material.",this);return}if(!t.material.materials){Xt("PropertyBinding: Can not bind to material.materials as node.material does not have a materials array.",this);return}t=t.material.materials;break;case"bones":if(!t.skeleton){Xt("PropertyBinding: Can not bind to bones as node does not have a skeleton.",this);return}t=t.skeleton.bones;for(let h=0;h<t.length;h++)if(t[h].name===c){c=h;break}break;case"map":if("map"in t){t=t.map;break}if(!t.material){Xt("PropertyBinding: Can not bind to material as node does not have a material.",this);return}if(!t.material.map){Xt("PropertyBinding: Can not bind to material.map as node.material does not have a map.",this);return}t=t.material.map;break;default:if(t[n]===void 0){Xt("PropertyBinding: Can not bind to objectName of node undefined.",this);return}t=t[n]}if(c!==void 0){if(t[c]===void 0){Xt("PropertyBinding: Trying to bind to objectIndex of objectName, but is undefined.",this,t);return}t=t[c]}}let a=t[i];if(a===void 0){let c=e.nodeName;Xt("PropertyBinding: Trying to update property for track: "+c+"."+i+" but it wasn't found.",t);return}let o=this.Versioning.None;this.targetObject=t,t.isMaterial===!0?o=this.Versioning.NeedsUpdate:t.isObject3D===!0&&(o=this.Versioning.MatrixWorldNeedsUpdate);let l=this.BindingType.Direct;if(r!==void 0){if(i==="morphTargetInfluences"){if(!t.geometry){Xt("PropertyBinding: Can not bind to morphTargetInfluences because node does not have a geometry.",this);return}if(!t.geometry.morphAttributes){Xt("PropertyBinding: Can not bind to morphTargetInfluences because node does not have a geometry.morphAttributes.",this);return}t.morphTargetDictionary[r]!==void 0&&(r=t.morphTargetDictionary[r])}l=this.BindingType.ArrayElement,this.resolvedProperty=a,this.propertyIndex=r}else a.fromArray!==void 0&&a.toArray!==void 0?(l=this.BindingType.HasFromToArray,this.resolvedProperty=a):Array.isArray(a)?(l=this.BindingType.EntireArray,this.resolvedProperty=a):this.propertyName=i;this.getValue=this.GetterByBindingType[l],this.setValue=this.SetterByBindingTypeAndVersioning[l][o]}unbind(){this.node=null,this.getValue=this._getValue_unbound,this.setValue=this._setValue_unbound}};Me.Composite=Mc;Me.prototype.BindingType={Direct:0,EntireArray:1,ArrayElement:2,HasFromToArray:3};Me.prototype.Versioning={None:0,NeedsUpdate:1,MatrixWorldNeedsUpdate:2};Me.prototype.GetterByBindingType=[Me.prototype._getValue_direct,Me.prototype._getValue_array,Me.prototype._getValue_arrayElement,Me.prototype._getValue_toArray];Me.prototype.SetterByBindingTypeAndVersioning=[[Me.prototype._setValue_direct,Me.prototype._setValue_direct_setNeedsUpdate,Me.prototype._setValue_direct_setMatrixWorldNeedsUpdate],[Me.prototype._setValue_array,Me.prototype._setValue_array_setNeedsUpdate,Me.prototype._setValue_array_setMatrixWorldNeedsUpdate],[Me.prototype._setValue_arrayElement,Me.prototype._setValue_arrayElement_setNeedsUpdate,Me.prototype._setValue_arrayElement_setMatrixWorldNeedsUpdate],[Me.prototype._setValue_fromArray,Me.prototype._setValue_fromArray_setNeedsUpdate,Me.prototype._setValue_fromArray_setMatrixWorldNeedsUpdate]];var m_=new Float32Array(1);var qc=class qc{constructor(t,e,n,i){this.elements=[1,0,0,1],t!==void 0&&this.set(t,e,n,i)}identity(){return this.set(1,0,0,1),this}fromArray(t,e=0){for(let n=0;n<4;n++)this.elements[n]=t[n+e];return this}set(t,e,n,i){let r=this.elements;return r[0]=t,r[2]=e,r[1]=n,r[3]=i,this}};qc.prototype.isMatrix2=!0;var Sc=qc;function Hc(s,t,e,n){let i=up(n);switch(e){case Dc:return s*t;case Io:return s*t/i.components*i.byteLength;case Lo:return s*t/i.components*i.byteLength;case Ci:return s*t*2/i.components*i.byteLength;case Do:return s*t*2/i.components*i.byteLength;case Uc:return s*t*3/i.components*i.byteLength;case vn:return s*t*4/i.components*i.byteLength;case Uo:return s*t*4/i.components*i.byteLength;case Qr:case jr:return Math.floor((s+3)/4)*Math.floor((t+3)/4)*8;case ta:case ea:return Math.floor((s+3)/4)*Math.floor((t+3)/4)*16;case Fo:case Oo:return Math.max(s,16)*Math.max(t,8)/4;case No:case Bo:return Math.max(s,8)*Math.max(t,8)/2;case ko:case zo:case Vo:case Go:return Math.floor((s+3)/4)*Math.floor((t+3)/4)*8;case Ho:case na:case Wo:return Math.floor((s+3)/4)*Math.floor((t+3)/4)*16;case Xo:return Math.floor((s+3)/4)*Math.floor((t+3)/4)*16;case qo:return Math.floor((s+4)/5)*Math.floor((t+3)/4)*16;case Yo:return Math.floor((s+4)/5)*Math.floor((t+4)/5)*16;case Zo:return Math.floor((s+5)/6)*Math.floor((t+4)/5)*16;case Jo:return Math.floor((s+5)/6)*Math.floor((t+5)/6)*16;case $o:return Math.floor((s+7)/8)*Math.floor((t+4)/5)*16;case Ko:return Math.floor((s+7)/8)*Math.floor((t+5)/6)*16;case Qo:return Math.floor((s+7)/8)*Math.floor((t+7)/8)*16;case jo:return Math.floor((s+9)/10)*Math.floor((t+4)/5)*16;case tl:return Math.floor((s+9)/10)*Math.floor((t+5)/6)*16;case el:return Math.floor((s+9)/10)*Math.floor((t+7)/8)*16;case nl:return Math.floor((s+9)/10)*Math.floor((t+9)/10)*16;case il:return Math.floor((s+11)/12)*Math.floor((t+9)/10)*16;case sl:return Math.floor((s+11)/12)*Math.floor((t+11)/12)*16;case rl:case al:case ol:return Math.ceil(s/4)*Math.ceil(t/4)*16;case ll:case cl:return Math.ceil(s/4)*Math.ceil(t/4)*8;case ia:case hl:return Math.ceil(s/4)*Math.ceil(t/4)*16}throw new Error(`Unable to determine texture byte length for ${e} format.`)}function up(s){switch(s){case rn:case Cc:return{byteLength:1,components:1};case Os:case Pc:case Le:return{byteLength:2,components:1};case Co:case Po:return{byteLength:2,components:4};case Dn:case Ro:case yn:return{byteLength:4,components:1};case Ic:case Lc:return{byteLength:4,components:3}}throw new Error(`THREE.TextureUtils: Unknown texture type ${s}.`)}typeof __THREE_DEVTOOLS__<"u"&&__THREE_DEVTOOLS__.dispatchEvent(new CustomEvent("register",{detail:{revision:"186"}}));typeof window<"u"&&(window.__THREE__?qt("WARNING: Multiple instances of Three.js being imported."):window.__THREE__="186");function gd(){let s=null,t=!1,e=null,n=null;function i(r,a){n=s.requestAnimationFrame(i),e(r,a)}return{start:function(){t!==!0&&e!==null&&s!==null&&(n=s.requestAnimationFrame(i),t=!0)},stop:function(){s!==null&&s.cancelAnimationFrame(n),t=!1},setAnimationLoop:function(r){e=r},setContext:function(r){s=r}}}function gp(s){let t=new WeakMap;function e(o,l){let c=o.array,h=o.usage,u=c.byteLength,d=s.createBuffer();s.bindBuffer(l,d),s.bufferData(l,c,h),o.onUploadCallback();let f;if(c instanceof Float32Array)f=s.FLOAT;else if(typeof Float16Array<"u"&&c instanceof Float16Array)f=s.HALF_FLOAT;else if(c instanceof Uint16Array)o.isFloat16BufferAttribute?f=s.HALF_FLOAT:f=s.UNSIGNED_SHORT;else if(c instanceof Int16Array)f=s.SHORT;else if(c instanceof Uint32Array)f=s.UNSIGNED_INT;else if(c instanceof Int32Array)f=s.INT;else if(c instanceof Int8Array)f=s.BYTE;else if(c instanceof Uint8Array)f=s.UNSIGNED_BYTE;else if(c instanceof Uint8ClampedArray)f=s.UNSIGNED_BYTE;else throw new Error("THREE.WebGLAttributes: Unsupported buffer data format: "+c);return{buffer:d,type:f,bytesPerElement:c.BYTES_PER_ELEMENT,version:o.version,size:u}}function n(o,l,c){let h=l.array,u=l.updateRanges;if(s.bindBuffer(c,o),u.length===0)s.bufferSubData(c,0,h);else{u.sort((f,p)=>f.start-p.start);let d=0;for(let f=1;f<u.length;f++){let p=u[d],y=u[f];y.start<=p.start+p.count+1?p.count=Math.max(p.count,y.start+y.count-p.start):(++d,u[d]=y)}u.length=d+1;for(let f=0,p=u.length;f<p;f++){let y=u[f];s.bufferSubData(c,y.start*h.BYTES_PER_ELEMENT,h,y.start,y.count)}l.clearUpdateRanges()}l.onUploadCallback()}function i(o){return o.isInterleavedBufferAttribute&&(o=o.data),t.get(o)}function r(o){o.isInterleavedBufferAttribute&&(o=o.data);let l=t.get(o);l&&(s.deleteBuffer(l.buffer),t.delete(o))}function a(o,l){if(o.isInterleavedBufferAttribute&&(o=o.data),o.isGLBufferAttribute){let h=t.get(o);(!h||h.version<o.version)&&t.set(o,{buffer:o.buffer,type:o.type,bytesPerElement:o.elementSize,version:o.version});return}let c=t.get(o);if(c===void 0)t.set(o,e(o,l));else if(c.version<o.version){if(c.size!==o.array.byteLength)throw new Error("THREE.WebGLAttributes: The size of the buffer attribute's array buffer does not match the original size. Resizing buffer attributes is not supported.");n(c.buffer,o,l),c.version=o.version}}return{get:i,remove:r,update:a}}var xp=`#ifdef USE_ALPHAHASH
	if ( diffuseColor.a < getAlphaHashThreshold( vPosition ) ) discard;
#endif`,_p=`#ifdef USE_ALPHAHASH
	const float ALPHA_HASH_SCALE = 0.05;
	float hash2D( vec2 value ) {
		return fract( 1.0e4 * sin( 17.0 * value.x + 0.1 * value.y ) * ( 0.1 + abs( sin( 13.0 * value.y + value.x ) ) ) );
	}
	float hash3D( vec3 value ) {
		return hash2D( vec2( hash2D( value.xy ), value.z ) );
	}
	float getAlphaHashThreshold( vec3 position ) {
		float maxDeriv = max(
			length( dFdx( position.xyz ) ),
			length( dFdy( position.xyz ) )
		);
		float pixScale = 1.0 / ( ALPHA_HASH_SCALE * maxDeriv );
		vec2 pixScales = vec2(
			exp2( floor( log2( pixScale ) ) ),
			exp2( ceil( log2( pixScale ) ) )
		);
		vec2 alpha = vec2(
			hash3D( floor( pixScales.x * position.xyz ) ),
			hash3D( floor( pixScales.y * position.xyz ) )
		);
		float lerpFactor = fract( log2( pixScale ) );
		float x = ( 1.0 - lerpFactor ) * alpha.x + lerpFactor * alpha.y;
		float a = min( lerpFactor, 1.0 - lerpFactor );
		vec3 cases = vec3(
			x * x / ( 2.0 * a * ( 1.0 - a ) ),
			( x - 0.5 * a ) / ( 1.0 - a ),
			1.0 - ( ( 1.0 - x ) * ( 1.0 - x ) / ( 2.0 * a * ( 1.0 - a ) ) )
		);
		float threshold = ( x < ( 1.0 - a ) )
			? ( ( x < a ) ? cases.x : cases.y )
			: cases.z;
		return clamp( threshold , 1.0e-6, 1.0 );
	}
#endif`,yp=`#ifdef USE_ALPHAMAP
	diffuseColor.a *= texture2D( alphaMap, vAlphaMapUv ).g;
#endif`,vp=`#ifdef USE_ALPHAMAP
	uniform sampler2D alphaMap;
#endif`,Mp=`#ifdef USE_ALPHATEST
	#ifdef ALPHA_TO_COVERAGE
	diffuseColor.a = smoothstep( alphaTest, alphaTest + fwidth( diffuseColor.a ), diffuseColor.a );
	if ( diffuseColor.a == 0.0 ) discard;
	#else
	if ( diffuseColor.a < alphaTest ) discard;
	#endif
#endif`,Sp=`#ifdef USE_ALPHATEST
	uniform float alphaTest;
#endif`,bp=`#ifdef USE_AOMAP
	float ambientOcclusion = ( texture2D( aoMap, vAoMapUv ).r - 1.0 ) * aoMapIntensity + 1.0;
	reflectedLight.indirectDiffuse *= ambientOcclusion;
	#if defined( USE_CLEARCOAT ) 
		clearcoatSpecularIndirect *= ambientOcclusion;
	#endif
	#if defined( USE_SHEEN ) 
		sheenSpecularIndirect *= ambientOcclusion;
	#endif
	#if defined( USE_ENVMAP ) && defined( STANDARD )
		float dotNV = saturate( dot( geometryNormal, geometryViewDir ) );
		reflectedLight.indirectSpecular *= computeSpecularOcclusion( dotNV, ambientOcclusion, material.roughness );
	#endif
#endif`,Ep=`#ifdef USE_AOMAP
	uniform sampler2D aoMap;
	uniform float aoMapIntensity;
#endif`,wp=`#ifdef USE_BATCHING
	#if ! defined( GL_ANGLE_multi_draw )
	#define gl_DrawID _gl_DrawID
	uniform int _gl_DrawID;
	#endif
	uniform highp sampler2D batchingTexture;
	uniform highp usampler2D batchingIdTexture;
	mat4 getBatchingMatrix( const in float i ) {
		int size = textureSize( batchingTexture, 0 ).x;
		int j = int( i ) * 4;
		int x = j % size;
		int y = j / size;
		vec4 v1 = texelFetch( batchingTexture, ivec2( x, y ), 0 );
		vec4 v2 = texelFetch( batchingTexture, ivec2( x + 1, y ), 0 );
		vec4 v3 = texelFetch( batchingTexture, ivec2( x + 2, y ), 0 );
		vec4 v4 = texelFetch( batchingTexture, ivec2( x + 3, y ), 0 );
		return mat4( v1, v2, v3, v4 );
	}
	float getIndirectIndex( const in int i ) {
		int size = textureSize( batchingIdTexture, 0 ).x;
		int x = i % size;
		int y = i / size;
		return float( texelFetch( batchingIdTexture, ivec2( x, y ), 0 ).r );
	}
#endif
#ifdef USE_BATCHING_COLOR
	uniform sampler2D batchingColorTexture;
	vec4 getBatchingColor( const in float i ) {
		int size = textureSize( batchingColorTexture, 0 ).x;
		int j = int( i );
		int x = j % size;
		int y = j / size;
		return texelFetch( batchingColorTexture, ivec2( x, y ), 0 );
	}
#endif`,Tp=`#ifdef USE_BATCHING
	mat4 batchingMatrix = getBatchingMatrix( getIndirectIndex( gl_DrawID ) );
#endif`,Ap=`vec3 transformed = vec3( position );
#ifdef USE_ALPHAHASH
	vPosition = vec3( position );
#endif`,Rp=`vec3 objectNormal = vec3( normal );
#ifdef USE_TANGENT
	vec3 objectTangent = vec3( tangent.xyz );
#endif`,Cp=`float G_BlinnPhong_Implicit( ) {
	return 0.25;
}
float D_BlinnPhong( const in float shininess, const in float dotNH ) {
	return RECIPROCAL_PI * ( shininess * 0.5 + 1.0 ) * pow( dotNH, shininess );
}
vec3 BRDF_BlinnPhong( const in vec3 lightDir, const in vec3 viewDir, const in vec3 normal, const in vec3 specularColor, const in float shininess ) {
	vec3 halfDir = normalize( lightDir + viewDir );
	float dotNH = saturate( dot( normal, halfDir ) );
	float dotVH = saturate( dot( viewDir, halfDir ) );
	vec3 F = F_Schlick( specularColor, 1.0, dotVH );
	float G = G_BlinnPhong_Implicit( );
	float D = D_BlinnPhong( shininess, dotNH );
	return F * ( G * D );
} // validated`,Pp=`#ifdef USE_IRIDESCENCE
	const mat3 XYZ_TO_REC709 = mat3(
		 3.2404542, -0.9692660,  0.0556434,
		-1.5371385,  1.8760108, -0.2040259,
		-0.4985314,  0.0415560,  1.0572252
	);
	vec3 Fresnel0ToIor( vec3 fresnel0 ) {
		vec3 sqrtF0 = sqrt( fresnel0 );
		return ( vec3( 1.0 ) + sqrtF0 ) / ( vec3( 1.0 ) - sqrtF0 );
	}
	vec3 IorToFresnel0( vec3 transmittedIor, float incidentIor ) {
		return pow2( ( transmittedIor - vec3( incidentIor ) ) / ( transmittedIor + vec3( incidentIor ) ) );
	}
	float IorToFresnel0( float transmittedIor, float incidentIor ) {
		return pow2( ( transmittedIor - incidentIor ) / ( transmittedIor + incidentIor ));
	}
	vec3 evalSensitivity( float OPD, vec3 shift ) {
		float phase = 2.0 * PI * OPD * 1.0e-9;
		vec3 val = vec3( 5.4856e-13, 4.4201e-13, 5.2481e-13 );
		vec3 pos = vec3( 1.6810e+06, 1.7953e+06, 2.2084e+06 );
		vec3 var = vec3( 4.3278e+09, 9.3046e+09, 6.6121e+09 );
		vec3 xyz = val * sqrt( 2.0 * PI * var ) * cos( pos * phase + shift ) * exp( - pow2( phase ) * var );
		xyz.x += 9.7470e-14 * sqrt( 2.0 * PI * 4.5282e+09 ) * cos( 2.2399e+06 * phase + shift[ 0 ] ) * exp( - 4.5282e+09 * pow2( phase ) );
		xyz /= 1.0685e-7;
		vec3 rgb = XYZ_TO_REC709 * xyz;
		return rgb;
	}
	vec3 evalIridescence( float outsideIOR, float eta2, float cosTheta1, float thinFilmThickness, vec3 baseF0 ) {
		vec3 I;
		float iridescenceIOR = mix( outsideIOR, eta2, smoothstep( 0.0, 0.03, thinFilmThickness ) );
		float sinTheta2Sq = pow2( outsideIOR / iridescenceIOR ) * ( 1.0 - pow2( cosTheta1 ) );
		float cosTheta2Sq = 1.0 - sinTheta2Sq;
		if ( cosTheta2Sq < 0.0 ) {
			return vec3( 1.0 );
		}
		float cosTheta2 = sqrt( cosTheta2Sq );
		float R0 = IorToFresnel0( iridescenceIOR, outsideIOR );
		float R12 = F_Schlick( R0, 1.0, cosTheta1 );
		float T121 = 1.0 - R12;
		float phi12 = 0.0;
		if ( iridescenceIOR < outsideIOR ) phi12 = PI;
		float phi21 = PI - phi12;
		vec3 baseIOR = Fresnel0ToIor( clamp( baseF0, 0.0, 0.9999 ) );		vec3 R1 = IorToFresnel0( baseIOR, iridescenceIOR );
		vec3 R23 = F_Schlick( R1, 1.0, cosTheta2 );
		vec3 phi23 = vec3( 0.0 );
		if ( baseIOR[ 0 ] < iridescenceIOR ) phi23[ 0 ] = PI;
		if ( baseIOR[ 1 ] < iridescenceIOR ) phi23[ 1 ] = PI;
		if ( baseIOR[ 2 ] < iridescenceIOR ) phi23[ 2 ] = PI;
		float OPD = 2.0 * iridescenceIOR * thinFilmThickness * cosTheta2;
		vec3 phi = vec3( phi21 ) + phi23;
		vec3 R123 = clamp( R12 * R23, 1e-5, 0.9999 );
		vec3 r123 = sqrt( R123 );
		vec3 Rs = pow2( T121 ) * R23 / ( vec3( 1.0 ) - R123 );
		vec3 C0 = R12 + Rs;
		I = C0;
		vec3 Cm = Rs - T121;
		for ( int m = 1; m <= 2; ++ m ) {
			Cm *= r123;
			vec3 Sm = 2.0 * evalSensitivity( float( m ) * OPD, float( m ) * phi );
			I += Cm * Sm;
		}
		return max( I, vec3( 0.0 ) );
	}
#endif`,Ip=`#ifdef USE_BUMPMAP
	uniform sampler2D bumpMap;
	uniform float bumpScale;
	vec2 dHdxy_fwd() {
		vec2 dSTdx = dFdx( vBumpMapUv );
		vec2 dSTdy = dFdy( vBumpMapUv );
		float Hll = bumpScale * texture2D( bumpMap, vBumpMapUv ).x;
		float dBx = bumpScale * texture2D( bumpMap, vBumpMapUv + dSTdx ).x - Hll;
		float dBy = bumpScale * texture2D( bumpMap, vBumpMapUv + dSTdy ).x - Hll;
		return vec2( dBx, dBy );
	}
	vec3 perturbNormalArb( vec3 surf_pos, vec3 surf_norm, vec2 dHdxy, float faceDirection ) {
		vec3 vSigmaX = normalize( dFdx( surf_pos.xyz ) );
		vec3 vSigmaY = normalize( dFdy( surf_pos.xyz ) );
		vec3 vN = surf_norm;
		vec3 R1 = cross( vSigmaY, vN );
		vec3 R2 = cross( vN, vSigmaX );
		float fDet = dot( vSigmaX, R1 ) * faceDirection;
		vec3 vGrad = sign( fDet ) * ( dHdxy.x * R1 + dHdxy.y * R2 );
		return normalize( abs( fDet ) * surf_norm - vGrad );
	}
#endif`,Lp=`#if NUM_CLIPPING_PLANES > 0
	vec4 plane;
	#ifdef ALPHA_TO_COVERAGE
		float distanceToPlane, distanceGradient;
		float clipOpacity = 1.0;
		#pragma unroll_loop_start
		for ( int i = 0; i < UNION_CLIPPING_PLANES; i ++ ) {
			plane = clippingPlanes[ i ];
			distanceToPlane = - dot( vClipPosition, plane.xyz ) + plane.w;
			distanceGradient = fwidth( distanceToPlane ) / 2.0;
			clipOpacity *= smoothstep( - distanceGradient, distanceGradient, distanceToPlane );
			if ( clipOpacity == 0.0 ) discard;
		}
		#pragma unroll_loop_end
		#if UNION_CLIPPING_PLANES < NUM_CLIPPING_PLANES
			float unionClipOpacity = 1.0;
			#pragma unroll_loop_start
			for ( int i = UNION_CLIPPING_PLANES; i < NUM_CLIPPING_PLANES; i ++ ) {
				plane = clippingPlanes[ i ];
				distanceToPlane = - dot( vClipPosition, plane.xyz ) + plane.w;
				distanceGradient = fwidth( distanceToPlane ) / 2.0;
				unionClipOpacity *= 1.0 - smoothstep( - distanceGradient, distanceGradient, distanceToPlane );
			}
			#pragma unroll_loop_end
			clipOpacity *= 1.0 - unionClipOpacity;
		#endif
		diffuseColor.a *= clipOpacity;
		if ( diffuseColor.a == 0.0 ) discard;
	#else
		#pragma unroll_loop_start
		for ( int i = 0; i < UNION_CLIPPING_PLANES; i ++ ) {
			plane = clippingPlanes[ i ];
			if ( dot( vClipPosition, plane.xyz ) > plane.w ) discard;
		}
		#pragma unroll_loop_end
		#if UNION_CLIPPING_PLANES < NUM_CLIPPING_PLANES
			bool clipped = true;
			#pragma unroll_loop_start
			for ( int i = UNION_CLIPPING_PLANES; i < NUM_CLIPPING_PLANES; i ++ ) {
				plane = clippingPlanes[ i ];
				clipped = ( dot( vClipPosition, plane.xyz ) > plane.w ) && clipped;
			}
			#pragma unroll_loop_end
			if ( clipped ) discard;
		#endif
	#endif
#endif`,Dp=`#if NUM_CLIPPING_PLANES > 0
	varying vec3 vClipPosition;
	uniform vec4 clippingPlanes[ NUM_CLIPPING_PLANES ];
#endif`,Up=`#if NUM_CLIPPING_PLANES > 0
	varying vec3 vClipPosition;
#endif`,Np=`#if NUM_CLIPPING_PLANES > 0
	vClipPosition = - mvPosition.xyz;
#endif`,Fp=`#if defined( USE_COLOR ) || defined( USE_COLOR_ALPHA )
	diffuseColor *= vColor;
#endif`,Bp=`#if defined( USE_COLOR ) || defined( USE_COLOR_ALPHA )
	varying vec4 vColor;
#endif`,Op=`#if defined( USE_COLOR ) || defined( USE_COLOR_ALPHA ) || defined( USE_INSTANCING_COLOR ) || defined( USE_BATCHING_COLOR )
	varying vec4 vColor;
#endif`,kp=`#if defined( USE_COLOR ) || defined( USE_COLOR_ALPHA ) || defined( USE_INSTANCING_COLOR ) || defined( USE_BATCHING_COLOR )
	vColor = vec4( 1.0 );
#endif
#ifdef USE_COLOR_ALPHA
	vColor *= color;
#elif defined( USE_COLOR )
	vColor.rgb *= color;
#endif
#ifdef USE_INSTANCING_COLOR
	vColor.rgb *= instanceColor.rgb;
#endif
#ifdef USE_BATCHING_COLOR
	vColor *= getBatchingColor( getIndirectIndex( gl_DrawID ) );
#endif`,zp=`#define PI 3.141592653589793
#define PI2 6.283185307179586
#define PI_HALF 1.5707963267948966
#define RECIPROCAL_PI 0.3183098861837907
#define RECIPROCAL_PI2 0.15915494309189535
#define EPSILON 1e-6
#ifndef saturate
#define saturate( a ) clamp( a, 0.0, 1.0 )
#endif
#define whiteComplement( a ) ( 1.0 - saturate( a ) )
float pow2( const in float x ) { return x*x; }
vec3 pow2( const in vec3 x ) { return x*x; }
float pow3( const in float x ) { return x*x*x; }
float pow4( const in float x ) { float x2 = x*x; return x2*x2; }
float max3( const in vec3 v ) { return max( max( v.x, v.y ), v.z ); }
float average( const in vec3 v ) { return dot( v, vec3( 0.3333333 ) ); }
highp float rand( const in vec2 uv ) {
	const highp float a = 12.9898, b = 78.233, c = 43758.5453;
	highp float dt = dot( uv.xy, vec2( a,b ) ), sn = mod( dt, PI );
	return fract( sin( sn ) * c );
}
#ifdef HIGH_PRECISION
	float precisionSafeLength( vec3 v ) { return length( v ); }
#else
	float precisionSafeLength( vec3 v ) {
		float maxComponent = max3( abs( v ) );
		return length( v / maxComponent ) * maxComponent;
	}
#endif
struct IncidentLight {
	vec3 color;
	vec3 direction;
	bool visible;
};
struct ReflectedLight {
	vec3 directDiffuse;
	vec3 directSpecular;
	vec3 indirectDiffuse;
	vec3 indirectSpecular;
};
#ifdef USE_ALPHAHASH
	varying vec3 vPosition;
#endif
vec3 transformDirection( in vec3 dir, in mat4 matrix ) {
	return normalize( ( matrix * vec4( dir, 0.0 ) ).xyz );
}
#define inverseTransformDirection transformDirectionByInverseViewMatrix
vec3 transformNormalByInverseViewMatrix( in vec3 normal, in mat4 viewMatrix ) {
	return normalize( ( vec4( normal, 0.0 ) * viewMatrix ).xyz );
}
vec3 transformDirectionByInverseViewMatrix( in vec3 dir, in mat4 viewMatrix ) {
	return normalize( ( vec4( dir, 0.0 ) * viewMatrix ).xyz );
}
bool isPerspectiveMatrix( mat4 m ) {
	return m[ 2 ][ 3 ] == - 1.0;
}
vec2 equirectUv( in vec3 dir ) {
	float u = atan( dir.z, dir.x ) * RECIPROCAL_PI2 + 0.5;
	float v = asin( clamp( dir.y, - 1.0, 1.0 ) ) * RECIPROCAL_PI + 0.5;
	return vec2( u, v );
}
vec3 BRDF_Lambert( const in vec3 diffuseColor ) {
	return RECIPROCAL_PI * diffuseColor;
}
vec3 F_Schlick( const in vec3 f0, const in float f90, const in float dotVH ) {
	float fresnel = exp2( ( - 5.55473 * dotVH - 6.98316 ) * dotVH );
	return f0 * ( 1.0 - fresnel ) + ( f90 * fresnel );
}
float F_Schlick( const in float f0, const in float f90, const in float dotVH ) {
	float fresnel = exp2( ( - 5.55473 * dotVH - 6.98316 ) * dotVH );
	return f0 * ( 1.0 - fresnel ) + ( f90 * fresnel );
} // validated`,Hp=`#ifdef ENVMAP_TYPE_CUBE_UV
	#define cubeUV_minMipLevel 4.0
	#define cubeUV_minTileSize 16.0
	float getFace( vec3 direction ) {
		vec3 absDirection = abs( direction );
		float face = - 1.0;
		if ( absDirection.x > absDirection.z ) {
			if ( absDirection.x > absDirection.y )
				face = direction.x > 0.0 ? 0.0 : 3.0;
			else
				face = direction.y > 0.0 ? 1.0 : 4.0;
		} else {
			if ( absDirection.z > absDirection.y )
				face = direction.z > 0.0 ? 2.0 : 5.0;
			else
				face = direction.y > 0.0 ? 1.0 : 4.0;
		}
		return face;
	}
	vec2 getUV( vec3 direction, float face ) {
		vec2 uv;
		if ( face == 0.0 ) {
			uv = vec2( direction.z, direction.y ) / abs( direction.x );
		} else if ( face == 1.0 ) {
			uv = vec2( - direction.x, - direction.z ) / abs( direction.y );
		} else if ( face == 2.0 ) {
			uv = vec2( - direction.x, direction.y ) / abs( direction.z );
		} else if ( face == 3.0 ) {
			uv = vec2( - direction.z, direction.y ) / abs( direction.x );
		} else if ( face == 4.0 ) {
			uv = vec2( - direction.x, direction.z ) / abs( direction.y );
		} else {
			uv = vec2( direction.x, direction.y ) / abs( direction.z );
		}
		return 0.5 * ( uv + 1.0 );
	}
	vec3 bilinearCubeUV( sampler2D envMap, vec3 direction, float mipInt ) {
		float face = getFace( direction );
		float filterInt = max( cubeUV_minMipLevel - mipInt, 0.0 );
		mipInt = max( mipInt, cubeUV_minMipLevel );
		float faceSize = exp2( mipInt );
		highp vec2 uv = getUV( direction, face ) * ( faceSize - 2.0 ) + 1.0;
		if ( face > 2.0 ) {
			uv.y += faceSize;
			face -= 3.0;
		}
		uv.x += face * faceSize;
		uv.x += filterInt * 3.0 * cubeUV_minTileSize;
		uv.y += 4.0 * ( exp2( CUBEUV_MAX_MIP ) - faceSize );
		uv.x *= CUBEUV_TEXEL_WIDTH;
		uv.y *= CUBEUV_TEXEL_HEIGHT;
		#ifdef texture2DGradEXT
			return texture2DGradEXT( envMap, uv, vec2( 0.0 ), vec2( 0.0 ) ).rgb;
		#else
			return texture2D( envMap, uv ).rgb;
		#endif
	}
	#define cubeUV_r0 1.0
	#define cubeUV_m0 - 2.0
	#define cubeUV_r1 0.8
	#define cubeUV_m1 - 1.0
	#define cubeUV_r4 0.4
	#define cubeUV_m4 2.0
	#define cubeUV_r5 0.305
	#define cubeUV_m5 3.0
	#define cubeUV_r6 0.21
	#define cubeUV_m6 4.0
	float roughnessToMip( float roughness ) {
		float mip = 0.0;
		if ( roughness >= cubeUV_r1 ) {
			mip = ( cubeUV_r0 - roughness ) * ( cubeUV_m1 - cubeUV_m0 ) / ( cubeUV_r0 - cubeUV_r1 ) + cubeUV_m0;
		} else if ( roughness >= cubeUV_r4 ) {
			mip = ( cubeUV_r1 - roughness ) * ( cubeUV_m4 - cubeUV_m1 ) / ( cubeUV_r1 - cubeUV_r4 ) + cubeUV_m1;
		} else if ( roughness >= cubeUV_r5 ) {
			mip = ( cubeUV_r4 - roughness ) * ( cubeUV_m5 - cubeUV_m4 ) / ( cubeUV_r4 - cubeUV_r5 ) + cubeUV_m4;
		} else if ( roughness >= cubeUV_r6 ) {
			mip = ( cubeUV_r5 - roughness ) * ( cubeUV_m6 - cubeUV_m5 ) / ( cubeUV_r5 - cubeUV_r6 ) + cubeUV_m5;
		} else {
			mip = - 2.0 * log2( 1.16 * roughness );		}
		return mip;
	}
	vec4 textureCubeUV( sampler2D envMap, vec3 sampleDir, float roughness ) {
		float mip = clamp( roughnessToMip( roughness ), cubeUV_m0, CUBEUV_MAX_MIP );
		float mipF = fract( mip );
		float mipInt = floor( mip );
		vec3 color0 = bilinearCubeUV( envMap, sampleDir, mipInt );
		if ( mipF == 0.0 ) {
			return vec4( color0, 1.0 );
		} else {
			vec3 color1 = bilinearCubeUV( envMap, sampleDir, mipInt + 1.0 );
			return vec4( mix( color0, color1, mipF ), 1.0 );
		}
	}
#endif`,Vp=`vec3 transformedNormal = objectNormal;
#ifdef USE_TANGENT
	vec3 transformedTangent = objectTangent;
#endif
#ifdef USE_BATCHING
	mat3 bm = mat3( batchingMatrix );
	transformedNormal /= vec3( dot( bm[ 0 ], bm[ 0 ] ), dot( bm[ 1 ], bm[ 1 ] ), dot( bm[ 2 ], bm[ 2 ] ) );
	transformedNormal = bm * transformedNormal;
	#ifdef USE_TANGENT
		transformedTangent = bm * transformedTangent;
	#endif
#endif
#ifdef USE_INSTANCING
	mat3 im = mat3( instanceMatrix );
	transformedNormal /= vec3( dot( im[ 0 ], im[ 0 ] ), dot( im[ 1 ], im[ 1 ] ), dot( im[ 2 ], im[ 2 ] ) );
	transformedNormal = im * transformedNormal;
	#ifdef USE_TANGENT
		transformedTangent = im * transformedTangent;
	#endif
#endif
transformedNormal = normalMatrix * transformedNormal;
#ifdef FLIP_SIDED
	transformedNormal = - transformedNormal;
#endif
#ifdef USE_TANGENT
	transformedTangent = ( modelViewMatrix * vec4( transformedTangent, 0.0 ) ).xyz;
#endif`,Gp=`#ifdef USE_DISPLACEMENTMAP
	uniform sampler2D displacementMap;
	uniform float displacementScale;
	uniform float displacementBias;
#endif`,Wp=`#ifdef USE_DISPLACEMENTMAP
	transformed += normalize( objectNormal ) * ( texture2D( displacementMap, vDisplacementMapUv ).x * displacementScale + displacementBias );
#endif`,Xp=`#ifdef USE_EMISSIVEMAP
	vec4 emissiveColor = texture2D( emissiveMap, vEmissiveMapUv );
	#ifdef DECODE_VIDEO_TEXTURE_EMISSIVE
		emissiveColor = sRGBTransferEOTF( emissiveColor );
	#endif
	totalEmissiveRadiance *= emissiveColor.rgb;
#endif`,qp=`#ifdef USE_EMISSIVEMAP
	uniform sampler2D emissiveMap;
#endif`,Yp="gl_FragColor = linearToOutputTexel( gl_FragColor );",Zp=`vec4 LinearTransferOETF( in vec4 value ) {
	return value;
}
vec4 sRGBTransferEOTF( in vec4 value ) {
	return vec4( mix( pow( value.rgb * 0.9478672986 + vec3( 0.0521327014 ), vec3( 2.4 ) ), value.rgb * 0.0773993808, vec3( lessThanEqual( value.rgb, vec3( 0.04045 ) ) ) ), value.a );
}
vec4 sRGBTransferOETF( in vec4 value ) {
	return vec4( mix( pow( value.rgb, vec3( 0.41666 ) ) * 1.055 - vec3( 0.055 ), value.rgb * 12.92, vec3( lessThanEqual( value.rgb, vec3( 0.0031308 ) ) ) ), value.a );
}`,Jp=`#ifdef USE_ENVMAP
	#ifdef ENV_WORLDPOS
		vec3 cameraToFrag;
		if ( isOrthographic ) {
			cameraToFrag = normalize( vec3( - viewMatrix[ 0 ][ 2 ], - viewMatrix[ 1 ][ 2 ], - viewMatrix[ 2 ][ 2 ] ) );
		} else {
			cameraToFrag = normalize( vWorldPosition - cameraPosition );
		}
		vec3 worldNormal = transformNormalByInverseViewMatrix( normal, viewMatrix );
		#ifdef ENVMAP_MODE_REFLECTION
			vec3 reflectVec = reflect( cameraToFrag, worldNormal );
		#else
			vec3 reflectVec = refract( cameraToFrag, worldNormal, refractionRatio );
		#endif
	#else
		vec3 reflectVec = vReflect;
	#endif
	#ifdef ENVMAP_TYPE_CUBE
		vec4 envColor = textureCube( envMap, envMapRotation * reflectVec );
		#ifdef ENVMAP_BLENDING_MULTIPLY
			outgoingLight = mix( outgoingLight, outgoingLight * envColor.xyz, specularStrength * reflectivity );
		#elif defined( ENVMAP_BLENDING_MIX )
			outgoingLight = mix( outgoingLight, envColor.xyz, specularStrength * reflectivity );
		#elif defined( ENVMAP_BLENDING_ADD )
			outgoingLight += envColor.xyz * specularStrength * reflectivity;
		#endif
	#endif
#endif`,$p=`#ifdef USE_ENVMAP
	uniform float envMapIntensity;
	uniform mat3 envMapRotation;
	#ifdef ENVMAP_TYPE_CUBE
		uniform samplerCube envMap;
	#else
		uniform sampler2D envMap;
	#endif
#endif`,Kp=`#ifdef USE_ENVMAP
	uniform float reflectivity;
	#if defined( USE_BUMPMAP ) || defined( USE_NORMALMAP ) || defined( PHONG ) || defined( LAMBERT )
		#define ENV_WORLDPOS
	#endif
	#ifdef ENV_WORLDPOS
		varying vec3 vWorldPosition;
		uniform float refractionRatio;
	#else
		varying vec3 vReflect;
	#endif
#endif`,Qp=`#ifdef USE_ENVMAP
	#if defined( USE_BUMPMAP ) || defined( USE_NORMALMAP ) || defined( PHONG ) || defined( LAMBERT )
		#define ENV_WORLDPOS
	#endif
	#ifdef ENV_WORLDPOS
		
		varying vec3 vWorldPosition;
	#else
		varying vec3 vReflect;
		uniform float refractionRatio;
	#endif
#endif`,jp=`#ifdef USE_ENVMAP
	#ifdef ENV_WORLDPOS
		vWorldPosition = worldPosition.xyz;
	#else
		vec3 cameraToVertex;
		if ( isOrthographic ) {
			cameraToVertex = normalize( vec3( - viewMatrix[ 0 ][ 2 ], - viewMatrix[ 1 ][ 2 ], - viewMatrix[ 2 ][ 2 ] ) );
		} else {
			cameraToVertex = normalize( worldPosition.xyz - cameraPosition );
		}
		vec3 worldNormal = transformNormalByInverseViewMatrix( transformedNormal, viewMatrix );
		#ifdef ENVMAP_MODE_REFLECTION
			vReflect = reflect( cameraToVertex, worldNormal );
		#else
			vReflect = refract( cameraToVertex, worldNormal, refractionRatio );
		#endif
	#endif
#endif`,tm=`#ifdef USE_FOG
	vFogDepth = - mvPosition.z;
#endif`,em=`#ifdef USE_FOG
	varying float vFogDepth;
#endif`,nm=`#ifdef USE_FOG
	#ifdef FOG_EXP2
		float fogFactor = 1.0 - exp( - fogDensity * fogDensity * vFogDepth * vFogDepth );
	#else
		float fogFactor = smoothstep( fogNear, fogFar, vFogDepth );
	#endif
	gl_FragColor.rgb = mix( gl_FragColor.rgb, fogColor, fogFactor );
#endif`,im=`#ifdef USE_FOG
	uniform vec3 fogColor;
	varying float vFogDepth;
	#ifdef FOG_EXP2
		uniform float fogDensity;
	#else
		uniform float fogNear;
		uniform float fogFar;
	#endif
#endif`,sm=`#ifdef USE_GRADIENTMAP
	uniform sampler2D gradientMap;
#endif
vec3 getGradientIrradiance( vec3 normal, vec3 lightDirection ) {
	float dotNL = dot( normal, lightDirection );
	vec2 coord = vec2( dotNL * 0.5 + 0.5, 0.0 );
	#ifdef USE_GRADIENTMAP
		return vec3( texture2D( gradientMap, coord ).r );
	#else
		vec2 fw = fwidth( coord ) * 0.5;
		return mix( vec3( 0.7 ), vec3( 1.0 ), smoothstep( 0.7 - fw.x, 0.7 + fw.x, coord.x ) );
	#endif
}`,rm=`#ifdef USE_LIGHTMAP
	uniform sampler2D lightMap;
	uniform float lightMapIntensity;
#endif`,am=`LambertMaterial material;
material.diffuseColor = diffuseColor.rgb;
material.specularStrength = specularStrength;`,om=`varying vec3 vViewPosition;
struct LambertMaterial {
	vec3 diffuseColor;
	float specularStrength;
};
void RE_Direct_Lambert( const in IncidentLight directLight, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in LambertMaterial material, inout ReflectedLight reflectedLight ) {
	float dotNL = saturate( dot( geometryNormal, directLight.direction ) );
	vec3 irradiance = dotNL * directLight.color;
	reflectedLight.directDiffuse += irradiance * BRDF_Lambert( material.diffuseColor );
}
void RE_IndirectDiffuse_Lambert( const in vec3 irradiance, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in LambertMaterial material, inout ReflectedLight reflectedLight ) {
	reflectedLight.indirectDiffuse += irradiance * BRDF_Lambert( material.diffuseColor );
}
#define RE_Direct				RE_Direct_Lambert
#define RE_IndirectDiffuse		RE_IndirectDiffuse_Lambert`,lm=`uniform bool receiveShadow;
uniform vec3 ambientLightColor;
#if defined( USE_LIGHT_PROBES )
	uniform vec3 lightProbe[ 9 ];
#endif
vec3 shGetIrradianceAt( in vec3 normal, in vec3 shCoefficients[ 9 ] ) {
	float x = normal.x, y = normal.y, z = normal.z;
	vec3 result = shCoefficients[ 0 ] * 0.886227;
	result += shCoefficients[ 1 ] * 2.0 * 0.511664 * y;
	result += shCoefficients[ 2 ] * 2.0 * 0.511664 * z;
	result += shCoefficients[ 3 ] * 2.0 * 0.511664 * x;
	result += shCoefficients[ 4 ] * 2.0 * 0.429043 * x * y;
	result += shCoefficients[ 5 ] * 2.0 * 0.429043 * y * z;
	result += shCoefficients[ 6 ] * ( 0.743125 * z * z - 0.247708 );
	result += shCoefficients[ 7 ] * 2.0 * 0.429043 * x * z;
	result += shCoefficients[ 8 ] * 0.429043 * ( x * x - y * y );
	return result;
}
vec3 getLightProbeIrradiance( const in vec3 lightProbe[ 9 ], const in vec3 normal ) {
	vec3 worldNormal = transformNormalByInverseViewMatrix( normal, viewMatrix );
	vec3 irradiance = shGetIrradianceAt( worldNormal, lightProbe );
	return irradiance;
}
vec3 getAmbientLightIrradiance( const in vec3 ambientLightColor ) {
	vec3 irradiance = ambientLightColor;
	return irradiance;
}
float getDistanceAttenuation( const in float lightDistance, const in float cutoffDistance, const in float decayExponent ) {
	float distanceFalloff = 1.0 / max( pow( lightDistance, decayExponent ), 0.01 );
	if ( cutoffDistance > 0.0 ) {
		distanceFalloff *= pow2( saturate( 1.0 - pow4( lightDistance / cutoffDistance ) ) );
	}
	return distanceFalloff;
}
float getSpotAttenuation( const in float coneCosine, const in float penumbraCosine, const in float angleCosine ) {
	return smoothstep( coneCosine, penumbraCosine, angleCosine );
}
#if NUM_SUN_LIGHTS > 0
	struct SunLight {
		vec3 direction;
		vec3 color;
	};
	uniform SunLight sunLights[ NUM_SUN_LIGHTS ];
	void getSunLightInfo( const in SunLight sunLight, out IncidentLight light ) {
		light.color = sunLight.color;
		light.direction = sunLight.direction;
		light.visible = true;
	}
#endif
#if NUM_DIR_LIGHTS > 0
	struct DirectionalLight {
		vec3 direction;
		vec3 color;
	};
	uniform DirectionalLight directionalLights[ NUM_DIR_LIGHTS ];
	void getDirectionalLightInfo( const in DirectionalLight directionalLight, out IncidentLight light ) {
		light.color = directionalLight.color;
		light.direction = directionalLight.direction;
		light.visible = true;
	}
#endif
#if NUM_POINT_LIGHTS > 0
	struct PointLight {
		vec3 position;
		vec3 color;
		float distance;
		float decay;
	};
	uniform PointLight pointLights[ NUM_POINT_LIGHTS ];
	void getPointLightInfo( const in PointLight pointLight, const in vec3 geometryPosition, out IncidentLight light ) {
		vec3 lVector = pointLight.position - geometryPosition;
		light.direction = normalize( lVector );
		float lightDistance = length( lVector );
		light.color = pointLight.color;
		light.color *= getDistanceAttenuation( lightDistance, pointLight.distance, pointLight.decay );
		light.visible = ( light.color != vec3( 0.0 ) );
	}
#endif
#if NUM_SPOT_LIGHTS > 0
	struct SpotLight {
		vec3 position;
		vec3 direction;
		vec3 color;
		float distance;
		float decay;
		float coneCos;
		float penumbraCos;
	};
	uniform SpotLight spotLights[ NUM_SPOT_LIGHTS ];
	void getSpotLightInfo( const in SpotLight spotLight, const in vec3 geometryPosition, out IncidentLight light ) {
		vec3 lVector = spotLight.position - geometryPosition;
		light.direction = normalize( lVector );
		float angleCos = dot( light.direction, spotLight.direction );
		float spotAttenuation = getSpotAttenuation( spotLight.coneCos, spotLight.penumbraCos, angleCos );
		if ( spotAttenuation > 0.0 ) {
			float lightDistance = length( lVector );
			light.color = spotLight.color * spotAttenuation;
			light.color *= getDistanceAttenuation( lightDistance, spotLight.distance, spotLight.decay );
			light.visible = ( light.color != vec3( 0.0 ) );
		} else {
			light.color = vec3( 0.0 );
			light.visible = false;
		}
	}
#endif
#if NUM_RECT_AREA_LIGHTS > 0
	struct RectAreaLight {
		vec3 color;
		vec3 position;
		vec3 halfWidth;
		vec3 halfHeight;
	};
	uniform sampler2D ltc_1;	uniform sampler2D ltc_2;
	uniform RectAreaLight rectAreaLights[ NUM_RECT_AREA_LIGHTS ];
#endif
#if NUM_HEMI_LIGHTS > 0
	struct HemisphereLight {
		vec3 direction;
		vec3 skyColor;
		vec3 groundColor;
	};
	uniform HemisphereLight hemisphereLights[ NUM_HEMI_LIGHTS ];
	vec3 getHemisphereLightIrradiance( const in HemisphereLight hemiLight, const in vec3 normal ) {
		float dotNL = dot( normal, hemiLight.direction );
		float hemiDiffuseWeight = 0.5 * dotNL + 0.5;
		vec3 irradiance = mix( hemiLight.groundColor, hemiLight.skyColor, hemiDiffuseWeight );
		return irradiance;
	}
#endif
#include <lightprobes_pars_fragment>`,cm=`#ifdef USE_ENVMAP
	vec3 getIBLIrradiance( const in vec3 normal ) {
		#ifdef ENVMAP_TYPE_CUBE_UV
			vec3 worldNormal = transformNormalByInverseViewMatrix( normal, viewMatrix );
			vec4 envMapColor = textureCubeUV( envMap, envMapRotation * worldNormal, 1.0 );
			return PI * envMapColor.rgb * envMapIntensity;
		#else
			return vec3( 0.0 );
		#endif
	}
	vec3 getIBLRadiance( const in vec3 viewDir, const in vec3 normal, const in float roughness ) {
		#ifdef ENVMAP_TYPE_CUBE_UV
			vec3 reflectVec = reflect( - viewDir, normal );
			reflectVec = normalize( mix( reflectVec, normal, pow4( roughness ) ) );
			reflectVec = transformDirectionByInverseViewMatrix( reflectVec, viewMatrix );
			vec4 envMapColor = textureCubeUV( envMap, envMapRotation * reflectVec, roughness );
			return envMapColor.rgb * envMapIntensity;
		#else
			return vec3( 0.0 );
		#endif
	}
	#ifdef USE_RETROREFLECTION
		vec3 getIBLRetroRadiance( const in vec3 viewDir, const in vec3 normal, const in float roughness ) {
			#ifdef ENVMAP_TYPE_CUBE_UV
				vec3 retroVec = normalize( mix( viewDir, normal, pow4( roughness ) ) );
				retroVec = transformDirectionByInverseViewMatrix( retroVec, viewMatrix );
				vec4 envMapColor = textureCubeUV( envMap, envMapRotation * retroVec, roughness );
				return envMapColor.rgb * envMapIntensity;
			#else
				return vec3( 0.0 );
			#endif
		}
	#endif
	#ifdef USE_ANISOTROPY
		vec3 getIBLAnisotropyRadiance( const in vec3 viewDir, const in vec3 normal, const in float roughness, const in vec3 bitangent, const in float anisotropy ) {
			#ifdef ENVMAP_TYPE_CUBE_UV
				vec3 bentNormal = cross( bitangent, viewDir );
				bentNormal = normalize( cross( bentNormal, bitangent ) );
				bentNormal = normalize( mix( bentNormal, normal, pow2( pow2( 1.0 - anisotropy * ( 1.0 - roughness ) ) ) ) );
				return getIBLRadiance( viewDir, bentNormal, roughness );
			#else
				return vec3( 0.0 );
			#endif
		}
		#ifdef USE_RETROREFLECTION
			vec3 getIBLAnisotropyRetroRadiance( const in vec3 viewDir, const in vec3 normal, const in float roughness, const in vec3 bitangent, const in float anisotropy ) {
				#ifdef ENVMAP_TYPE_CUBE_UV
					vec3 bentNormal = cross( bitangent, viewDir );
					bentNormal = normalize( cross( bentNormal, bitangent ) );
					bentNormal = normalize( mix( bentNormal, normal, pow2( pow2( 1.0 - anisotropy * ( 1.0 - roughness ) ) ) ) );
					return getIBLRetroRadiance( viewDir, bentNormal, roughness );
				#else
					return vec3( 0.0 );
				#endif
			}
		#endif
	#endif
#endif`,hm=`ToonMaterial material;
material.diffuseColor = diffuseColor.rgb;`,um=`varying vec3 vViewPosition;
struct ToonMaterial {
	vec3 diffuseColor;
};
void RE_Direct_Toon( const in IncidentLight directLight, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in ToonMaterial material, inout ReflectedLight reflectedLight ) {
	vec3 irradiance = getGradientIrradiance( geometryNormal, directLight.direction ) * directLight.color;
	reflectedLight.directDiffuse += irradiance * BRDF_Lambert( material.diffuseColor );
}
void RE_IndirectDiffuse_Toon( const in vec3 irradiance, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in ToonMaterial material, inout ReflectedLight reflectedLight ) {
	reflectedLight.indirectDiffuse += irradiance * BRDF_Lambert( material.diffuseColor );
}
#define RE_Direct				RE_Direct_Toon
#define RE_IndirectDiffuse		RE_IndirectDiffuse_Toon`,dm=`BlinnPhongMaterial material;
material.diffuseColor = diffuseColor.rgb;
material.specularColor = specular;
material.specularShininess = shininess;
material.specularStrength = specularStrength;`,fm=`varying vec3 vViewPosition;
struct BlinnPhongMaterial {
	vec3 diffuseColor;
	vec3 specularColor;
	float specularShininess;
	float specularStrength;
};
void RE_Direct_BlinnPhong( const in IncidentLight directLight, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in BlinnPhongMaterial material, inout ReflectedLight reflectedLight ) {
	float dotNL = saturate( dot( geometryNormal, directLight.direction ) );
	vec3 irradiance = dotNL * directLight.color;
	reflectedLight.directDiffuse += irradiance * BRDF_Lambert( material.diffuseColor );
	reflectedLight.directSpecular += irradiance * BRDF_BlinnPhong( directLight.direction, geometryViewDir, geometryNormal, material.specularColor, material.specularShininess ) * material.specularStrength;
}
void RE_IndirectDiffuse_BlinnPhong( const in vec3 irradiance, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in BlinnPhongMaterial material, inout ReflectedLight reflectedLight ) {
	reflectedLight.indirectDiffuse += irradiance * BRDF_Lambert( material.diffuseColor );
}
#define RE_Direct				RE_Direct_BlinnPhong
#define RE_IndirectDiffuse		RE_IndirectDiffuse_BlinnPhong`,pm=`PhysicalMaterial material;
material.diffuseColor = diffuseColor.rgb;
material.diffuseContribution = diffuseColor.rgb * ( 1.0 - metalnessFactor );
material.metalness = metalnessFactor;
vec3 dxy = max( abs( dFdx( nonPerturbedNormal ) ), abs( dFdy( nonPerturbedNormal ) ) );
float geometryRoughness = max( max( dxy.x, dxy.y ), dxy.z );
material.roughness = max( roughnessFactor, 0.0525 );material.roughness += geometryRoughness;
material.roughness = min( material.roughness, 1.0 );
#ifdef IOR
	material.ior = ior;
	#ifdef USE_SPECULAR
		float specularIntensityFactor = specularIntensity;
		vec3 specularColorFactor = specularColor;
		#ifdef USE_SPECULAR_COLORMAP
			specularColorFactor *= texture2D( specularColorMap, vSpecularColorMapUv ).rgb;
		#endif
		#ifdef USE_SPECULAR_INTENSITYMAP
			specularIntensityFactor *= texture2D( specularIntensityMap, vSpecularIntensityMapUv ).a;
		#endif
		material.specularF90 = mix( specularIntensityFactor, 1.0, metalnessFactor );
	#else
		float specularIntensityFactor = 1.0;
		vec3 specularColorFactor = vec3( 1.0 );
		material.specularF90 = 1.0;
	#endif
	material.specularColor = min( pow2( ( material.ior - 1.0 ) / ( material.ior + 1.0 ) ) * specularColorFactor, vec3( 1.0 ) ) * specularIntensityFactor;
	material.specularColorBlended = mix( material.specularColor, diffuseColor.rgb, metalnessFactor );
#else
	material.specularColor = vec3( 0.04 );
	material.specularColorBlended = mix( material.specularColor, diffuseColor.rgb, metalnessFactor );
	material.specularF90 = 1.0;
#endif
#ifdef USE_CLEARCOAT
	material.clearcoat = clearcoat;
	material.clearcoatRoughness = clearcoatRoughness;
	material.clearcoatF0 = vec3( 0.04 );
	material.clearcoatF90 = 1.0;
	#ifdef USE_CLEARCOATMAP
		material.clearcoat *= texture2D( clearcoatMap, vClearcoatMapUv ).x;
	#endif
	#ifdef USE_CLEARCOAT_ROUGHNESSMAP
		material.clearcoatRoughness *= texture2D( clearcoatRoughnessMap, vClearcoatRoughnessMapUv ).y;
	#endif
	material.clearcoat = saturate( material.clearcoat );	material.clearcoatRoughness = max( material.clearcoatRoughness, 0.0525 );
	material.clearcoatRoughness += geometryRoughness;
	material.clearcoatRoughness = min( material.clearcoatRoughness, 1.0 );
#endif
#ifdef USE_DISPERSION
	material.dispersion = dispersion;
#endif
#ifdef USE_RETROREFLECTION
	material.retroreflectivity = retroreflectivity;
#endif
#ifdef USE_IRIDESCENCE
	material.iridescence = iridescence;
	material.iridescenceIOR = iridescenceIOR;
	#ifdef USE_IRIDESCENCEMAP
		material.iridescence *= texture2D( iridescenceMap, vIridescenceMapUv ).r;
	#endif
	#ifdef USE_IRIDESCENCE_THICKNESSMAP
		material.iridescenceThickness = (iridescenceThicknessMaximum - iridescenceThicknessMinimum) * texture2D( iridescenceThicknessMap, vIridescenceThicknessMapUv ).g + iridescenceThicknessMinimum;
	#else
		material.iridescenceThickness = iridescenceThicknessMaximum;
	#endif
#endif
#ifdef USE_SHEEN
	material.sheenColor = sheenColor;
	#ifdef USE_SHEEN_COLORMAP
		material.sheenColor *= texture2D( sheenColorMap, vSheenColorMapUv ).rgb;
	#endif
	material.sheenRoughness = clamp( sheenRoughness, 0.0001, 1.0 );
	#ifdef USE_SHEEN_ROUGHNESSMAP
		material.sheenRoughness *= texture2D( sheenRoughnessMap, vSheenRoughnessMapUv ).a;
	#endif
#endif
#ifdef USE_ANISOTROPY
	#ifdef USE_ANISOTROPYMAP
		mat2 anisotropyMat = mat2( anisotropyVector.x, anisotropyVector.y, - anisotropyVector.y, anisotropyVector.x );
		vec3 anisotropyPolar = texture2D( anisotropyMap, vAnisotropyMapUv ).rgb;
		vec2 anisotropyV = anisotropyMat * normalize( 2.0 * anisotropyPolar.rg - vec2( 1.0 ) ) * anisotropyPolar.b;
	#else
		vec2 anisotropyV = anisotropyVector;
	#endif
	material.anisotropy = length( anisotropyV );
	if( material.anisotropy == 0.0 ) {
		anisotropyV = vec2( 1.0, 0.0 );
	} else {
		anisotropyV /= material.anisotropy;
		material.anisotropy = saturate( material.anisotropy );
	}
	material.alphaT = mix( pow2( material.roughness ), 1.0, pow2( material.anisotropy ) );
	material.anisotropyT = tbn[ 0 ] * anisotropyV.x + tbn[ 1 ] * anisotropyV.y;
	material.anisotropyB = tbn[ 1 ] * anisotropyV.x - tbn[ 0 ] * anisotropyV.y;
#endif`,mm=`uniform sampler2D dfgLUT;
struct PhysicalMaterial {
	vec3 diffuseColor;
	vec3 diffuseContribution;
	vec3 specularColor;
	vec3 specularColorBlended;
	float roughness;
	float metalness;
	float specularF90;
	float dispersion;
	vec2 dfg;
	vec3 multiScatteringCompensation;
	#ifdef USE_RETROREFLECTION
		float retroreflectivity;
	#endif
	#ifdef USE_CLEARCOAT
		float clearcoat;
		float clearcoatRoughness;
		vec3 clearcoatF0;
		float clearcoatF90;
	#endif
	#ifdef USE_IRIDESCENCE
		float iridescence;
		float iridescenceIOR;
		float iridescenceThickness;
		vec3 iridescenceFresnel;
		vec3 iridescenceF0Dielectric;
		vec3 iridescenceF0Metallic;
	#endif
	#ifdef USE_SHEEN
		vec3 sheenColor;
		float sheenRoughness;
	#endif
	#ifdef IOR
		float ior;
	#endif
	#ifdef USE_TRANSMISSION
		float transmission;
		float transmissionAlpha;
		float thickness;
		float attenuationDistance;
		vec3 attenuationColor;
	#endif
	#ifdef USE_ANISOTROPY
		float anisotropy;
		float alphaT;
		vec3 anisotropyT;
		vec3 anisotropyB;
	#endif
};
vec3 clearcoatSpecularDirect = vec3( 0.0 );
vec3 clearcoatSpecularIndirect = vec3( 0.0 );
vec3 sheenSpecularDirect = vec3( 0.0 );
vec3 sheenSpecularIndirect = vec3(0.0 );
vec3 Schlick_to_F0( const in vec3 f, const in float f90, const in float dotVH ) {
    float x = clamp( 1.0 - dotVH, 0.0, 1.0 );
    float x2 = x * x;
    float x5 = clamp( x * x2 * x2, 0.0, 0.9999 );
    return ( f - vec3( f90 ) * x5 ) / ( 1.0 - x5 );
}
float V_GGX_SmithCorrelated( const in float alpha, const in float dotNL, const in float dotNV ) {
	float a2 = pow2( alpha );
	float gv = dotNL * sqrt( a2 + ( 1.0 - a2 ) * pow2( dotNV ) );
	float gl = dotNV * sqrt( a2 + ( 1.0 - a2 ) * pow2( dotNL ) );
	return 0.5 / max( gv + gl, EPSILON );
}
float D_GGX( const in float alpha, const in float dotNH ) {
	float a2 = pow2( alpha );
	float denom = pow2( dotNH ) * ( a2 - 1.0 ) + 1.0;
	return RECIPROCAL_PI * a2 / pow2( denom );
}
#ifdef USE_ANISOTROPY
	float V_GGX_SmithCorrelated_Anisotropic( const in float alphaT, const in float alphaB, const in float dotTV, const in float dotBV, const in float dotTL, const in float dotBL, const in float dotNV, const in float dotNL ) {
		float gv = dotNL * length( vec3( alphaT * dotTV, alphaB * dotBV, dotNV ) );
		float gl = dotNV * length( vec3( alphaT * dotTL, alphaB * dotBL, dotNL ) );
		return 0.5 / max( gv + gl, EPSILON );
	}
	float D_GGX_Anisotropic( const in float alphaT, const in float alphaB, const in float dotNH, const in float dotTH, const in float dotBH ) {
		float a2 = alphaT * alphaB;
		highp vec3 v = vec3( alphaB * dotTH, alphaT * dotBH, a2 * dotNH );
		highp float v2 = dot( v, v );
		float w2 = a2 / v2;
		return RECIPROCAL_PI * a2 * pow2 ( w2 );
	}
#endif
#ifdef USE_CLEARCOAT
	vec3 BRDF_GGX_Clearcoat( const in vec3 lightDir, const in vec3 viewDir, const in vec3 normal, const in PhysicalMaterial material) {
		vec3 f0 = material.clearcoatF0;
		float f90 = material.clearcoatF90;
		float roughness = material.clearcoatRoughness;
		float alpha = pow2( roughness );
		vec3 halfDir = normalize( lightDir + viewDir );
		float dotNL = saturate( dot( normal, lightDir ) );
		float dotNV = saturate( dot( normal, viewDir ) );
		float dotNH = saturate( dot( normal, halfDir ) );
		float dotVH = saturate( dot( viewDir, halfDir ) );
		vec3 F = F_Schlick( f0, f90, dotVH );
		float V = V_GGX_SmithCorrelated( alpha, dotNL, dotNV );
		float D = D_GGX( alpha, dotNH );
		return F * ( V * D );
	}
#endif
vec3 BRDF_GGX( const in vec3 lightDir, const in vec3 viewDir, const in vec3 normal, const in PhysicalMaterial material ) {
	vec3 f0 = material.specularColorBlended;
	float f90 = material.specularF90;
	float roughness = material.roughness;
	float alpha = pow2( roughness );
	vec3 halfDir = normalize( lightDir + viewDir );
	float dotNL = saturate( dot( normal, lightDir ) );
	float dotNV = saturate( dot( normal, viewDir ) );
	float dotNH = saturate( dot( normal, halfDir ) );
	float dotVH = saturate( dot( viewDir, halfDir ) );
	vec3 F = F_Schlick( f0, f90, dotVH );
	#ifdef USE_IRIDESCENCE
		F = mix( F, material.iridescenceFresnel, material.iridescence );
	#endif
	#ifdef USE_ANISOTROPY
		float dotTL = dot( material.anisotropyT, lightDir );
		float dotTV = dot( material.anisotropyT, viewDir );
		float dotTH = dot( material.anisotropyT, halfDir );
		float dotBL = dot( material.anisotropyB, lightDir );
		float dotBV = dot( material.anisotropyB, viewDir );
		float dotBH = dot( material.anisotropyB, halfDir );
		float V = V_GGX_SmithCorrelated_Anisotropic( material.alphaT, alpha, dotTV, dotBV, dotTL, dotBL, dotNV, dotNL );
		float D = D_GGX_Anisotropic( material.alphaT, alpha, dotNH, dotTH, dotBH );
	#else
		float V = V_GGX_SmithCorrelated( alpha, dotNL, dotNV );
		float D = D_GGX( alpha, dotNH );
	#endif
	return F * ( V * D );
}
vec2 LTC_Uv( const in vec3 N, const in vec3 V, const in float roughness ) {
	const float LUT_SIZE = 64.0;
	const float LUT_SCALE = ( LUT_SIZE - 1.0 ) / LUT_SIZE;
	const float LUT_BIAS = 0.5 / LUT_SIZE;
	float dotNV = saturate( dot( N, V ) );
	vec2 uv = vec2( roughness, sqrt( 1.0 - dotNV ) );
	uv = uv * LUT_SCALE + LUT_BIAS;
	return uv;
}
float LTC_ClippedSphereFormFactor( const in vec3 f ) {
	float l = length( f );
	return max( ( l * l + f.z ) / ( l + 1.0 ), 0.0 );
}
vec3 LTC_EdgeVectorFormFactor( const in vec3 v1, const in vec3 v2 ) {
	float x = dot( v1, v2 );
	float y = abs( x );
	float a = 0.8543985 + ( 0.4965155 + 0.0145206 * y ) * y;
	float b = 3.4175940 + ( 4.1616724 + y ) * y;
	float v = a / b;
	float theta_sintheta = ( x > 0.0 ) ? v : 0.5 * inversesqrt( max( 1.0 - x * x, 1e-7 ) ) - v;
	return cross( v1, v2 ) * theta_sintheta;
}
vec3 LTC_Evaluate( const in vec3 N, const in vec3 V, const in vec3 P, const in mat3 mInv, const in vec3 rectCoords[ 4 ] ) {
	vec3 v1 = rectCoords[ 1 ] - rectCoords[ 0 ];
	vec3 v2 = rectCoords[ 3 ] - rectCoords[ 0 ];
	vec3 lightNormal = cross( v1, v2 );
	if( dot( lightNormal, P - rectCoords[ 0 ] ) < 0.0 ) return vec3( 0.0 );
	vec3 T1, T2;
	T1 = normalize( V - N * dot( V, N ) );
	T2 = - cross( N, T1 );
	mat3 mat = mInv * transpose( mat3( T1, T2, N ) );
	vec3 coords[ 4 ];
	coords[ 0 ] = mat * ( rectCoords[ 0 ] - P );
	coords[ 1 ] = mat * ( rectCoords[ 1 ] - P );
	coords[ 2 ] = mat * ( rectCoords[ 2 ] - P );
	coords[ 3 ] = mat * ( rectCoords[ 3 ] - P );
	coords[ 0 ] = normalize( coords[ 0 ] );
	coords[ 1 ] = normalize( coords[ 1 ] );
	coords[ 2 ] = normalize( coords[ 2 ] );
	coords[ 3 ] = normalize( coords[ 3 ] );
	vec3 vectorFormFactor = vec3( 0.0 );
	vectorFormFactor += LTC_EdgeVectorFormFactor( coords[ 0 ], coords[ 1 ] );
	vectorFormFactor += LTC_EdgeVectorFormFactor( coords[ 1 ], coords[ 2 ] );
	vectorFormFactor += LTC_EdgeVectorFormFactor( coords[ 2 ], coords[ 3 ] );
	vectorFormFactor += LTC_EdgeVectorFormFactor( coords[ 3 ], coords[ 0 ] );
	float result = LTC_ClippedSphereFormFactor( vectorFormFactor );
	return vec3( result );
}
#if defined( USE_SHEEN )
float D_Charlie( float roughness, float dotNH ) {
	float alpha = pow2( roughness );
	float invAlpha = 1.0 / alpha;
	float cos2h = dotNH * dotNH;
	float sin2h = max( 1.0 - cos2h, 0.0078125 );
	return ( 2.0 + invAlpha ) * pow( sin2h, invAlpha * 0.5 ) / ( 2.0 * PI );
}
float V_Neubelt( float dotNV, float dotNL ) {
	return saturate( 1.0 / ( 4.0 * ( dotNL + dotNV - dotNL * dotNV ) ) );
}
vec3 BRDF_Sheen( const in vec3 lightDir, const in vec3 viewDir, const in vec3 normal, vec3 sheenColor, const in float sheenRoughness ) {
	vec3 halfDir = normalize( lightDir + viewDir );
	float dotNL = saturate( dot( normal, lightDir ) );
	float dotNV = saturate( dot( normal, viewDir ) );
	float dotNH = saturate( dot( normal, halfDir ) );
	float D = D_Charlie( sheenRoughness, dotNH );
	float V = V_Neubelt( dotNV, dotNL );
	return sheenColor * ( D * V );
}
#endif
float IBLSheenBRDF( const in vec3 normal, const in vec3 viewDir, const in float roughness ) {
	float dotNV = saturate( dot( normal, viewDir ) );
	float r2 = roughness * roughness;
	float rInv = 1.0 / ( roughness + 0.1 );
	float a = -1.9362 + 1.0678 * roughness + 0.4573 * r2 - 0.8469 * rInv;
	float b = -0.6014 + 0.5538 * roughness - 0.4670 * r2 - 0.1255 * rInv;
	float DG = exp( a * dotNV + b );
	return saturate( DG );
}
vec3 EnvironmentBRDF( const in vec3 normal, const in vec3 viewDir, const in vec3 specularColor, const in float specularF90, const in float roughness ) {
	float dotNV = saturate( dot( normal, viewDir ) );
	vec2 fab = texture2D( dfgLUT, vec2( roughness, dotNV ) ).rg;
	return specularColor * fab.x + specularF90 * fab.y;
}
#ifdef USE_IRIDESCENCE
void computeMultiscatteringIridescence( const in vec2 fab, const in vec3 specularColor, const in float specularF90, const in float iridescence, const in vec3 iridescenceF0, inout vec3 singleScatter, inout vec3 multiScatter ) {
#else
void computeMultiscattering( const in vec2 fab, const in vec3 specularColor, const in float specularF90, inout vec3 singleScatter, inout vec3 multiScatter ) {
#endif
	#ifdef USE_IRIDESCENCE
		vec3 Fr = mix( specularColor, iridescenceF0, iridescence );
	#else
		vec3 Fr = specularColor;
	#endif
	vec3 FssEss = Fr * fab.x + specularF90 * fab.y;
	float Ess = fab.x + fab.y;
	float Ems = 1.0 - Ess;
	vec3 Favg = Fr + ( 1.0 - Fr ) * 0.047619;	vec3 Fms = FssEss * Favg / ( 1.0 - Ems * Favg );
	singleScatter += FssEss;
	multiScatter += Fms * Ems;
}
#if NUM_RECT_AREA_LIGHTS > 0
	void RE_Direct_RectArea_Physical( const in RectAreaLight rectAreaLight, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in PhysicalMaterial material, inout ReflectedLight reflectedLight ) {
		vec3 normal = geometryNormal;
		vec3 viewDir = geometryViewDir;
		vec3 position = geometryPosition;
		vec3 lightPos = rectAreaLight.position;
		vec3 halfWidth = rectAreaLight.halfWidth;
		vec3 halfHeight = rectAreaLight.halfHeight;
		vec3 lightColor = rectAreaLight.color;
		float roughness = material.roughness;
		vec3 rectCoords[ 4 ];
		rectCoords[ 0 ] = lightPos + halfWidth - halfHeight;		rectCoords[ 1 ] = lightPos - halfWidth - halfHeight;
		rectCoords[ 2 ] = lightPos - halfWidth + halfHeight;
		rectCoords[ 3 ] = lightPos + halfWidth + halfHeight;
		vec2 uv = LTC_Uv( normal, viewDir, roughness );
		vec4 t1 = texture2D( ltc_1, uv );
		vec4 t2 = texture2D( ltc_2, uv );
		mat3 mInv = mat3(
			vec3( t1.x, 0, t1.y ),
			vec3(    0, 1,    0 ),
			vec3( t1.z, 0, t1.w )
		);
		vec3 fresnel = ( material.specularColorBlended * t2.x + ( material.specularF90 - material.specularColorBlended ) * t2.y );
		reflectedLight.directSpecular += lightColor * fresnel * LTC_Evaluate( normal, viewDir, position, mInv, rectCoords );
		reflectedLight.directDiffuse += lightColor * material.diffuseContribution * LTC_Evaluate( normal, viewDir, position, mat3( 1.0 ), rectCoords );
		#ifdef USE_CLEARCOAT
			vec3 Ncc = geometryClearcoatNormal;
			vec2 uvClearcoat = LTC_Uv( Ncc, viewDir, material.clearcoatRoughness );
			vec4 t1Clearcoat = texture2D( ltc_1, uvClearcoat );
			vec4 t2Clearcoat = texture2D( ltc_2, uvClearcoat );
			mat3 mInvClearcoat = mat3(
				vec3( t1Clearcoat.x, 0, t1Clearcoat.y ),
				vec3(             0, 1,             0 ),
				vec3( t1Clearcoat.z, 0, t1Clearcoat.w )
			);
			vec3 fresnelClearcoat = material.clearcoatF0 * t2Clearcoat.x + ( material.clearcoatF90 - material.clearcoatF0 ) * t2Clearcoat.y;
			clearcoatSpecularDirect += lightColor * fresnelClearcoat * LTC_Evaluate( Ncc, viewDir, position, mInvClearcoat, rectCoords );
		#endif
	}
#endif
void RE_Direct_Physical( const in IncidentLight directLight, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in PhysicalMaterial material, inout ReflectedLight reflectedLight ) {
	float dotNL = saturate( dot( geometryNormal, directLight.direction ) );
	vec3 irradiance = dotNL * directLight.color;
	#ifdef USE_CLEARCOAT
		float dotNLcc = saturate( dot( geometryClearcoatNormal, directLight.direction ) );
		vec3 ccIrradiance = dotNLcc * directLight.color;
		clearcoatSpecularDirect += ccIrradiance * BRDF_GGX_Clearcoat( directLight.direction, geometryViewDir, geometryClearcoatNormal, material );
	#endif
	#ifdef USE_SHEEN
 
 		sheenSpecularDirect += irradiance * BRDF_Sheen( directLight.direction, geometryViewDir, geometryNormal, material.sheenColor, material.sheenRoughness );
 
 		float sheenAlbedoV = IBLSheenBRDF( geometryNormal, geometryViewDir, material.sheenRoughness );
 		float sheenAlbedoL = IBLSheenBRDF( geometryNormal, directLight.direction, material.sheenRoughness );
 
 		float sheenEnergyComp = 1.0 - max3( material.sheenColor ) * max( sheenAlbedoV, sheenAlbedoL );
 
 		irradiance *= sheenEnergyComp;
 
 	#endif
	vec3 specularBRDF = BRDF_GGX( directLight.direction, geometryViewDir, geometryNormal, material );
	#ifdef USE_RETROREFLECTION
		vec3 retroViewDir = reflect( - geometryViewDir, geometryNormal );
		vec3 retroSpecularBRDF = BRDF_GGX( directLight.direction, retroViewDir, geometryNormal, material );
		specularBRDF = mix( specularBRDF, retroSpecularBRDF, saturate( material.retroreflectivity ) );
	#endif
	reflectedLight.directSpecular += irradiance * specularBRDF * material.multiScatteringCompensation;
	vec3 halfDir = normalize( directLight.direction + geometryViewDir );
	float dotVH = saturate( dot( geometryViewDir, halfDir ) );
	vec3 F = F_Schlick( material.specularColor, material.specularF90, dotVH );
	#ifdef USE_RETROREFLECTION
		vec3 retroHalfDir = normalize( directLight.direction + retroViewDir );
		float dotRetroVH = saturate( dot( retroViewDir, retroHalfDir ) );
		vec3 retroF = F_Schlick( material.specularColor, material.specularF90, dotRetroVH );
		F = mix( F, retroF, saturate( material.retroreflectivity ) );
	#endif
	reflectedLight.directDiffuse += irradiance * BRDF_Lambert( material.diffuseContribution ) * ( 1.0 - F );
}
void RE_IndirectDiffuse_Physical( const in vec3 irradiance, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in PhysicalMaterial material, inout ReflectedLight reflectedLight ) {
	vec3 singleScattering = vec3( 0.0 );
	vec3 multiScattering = vec3( 0.0 );
	#ifdef USE_IRIDESCENCE
		computeMultiscatteringIridescence( material.dfg, material.specularColor, material.specularF90, material.iridescence, material.iridescenceF0Dielectric, singleScattering, multiScattering );
	#else
		computeMultiscattering( material.dfg, material.specularColor, material.specularF90, singleScattering, multiScattering );
	#endif
	vec3 diffuse = irradiance * BRDF_Lambert( material.diffuseContribution ) * ( 1.0 - singleScattering - multiScattering );
	#ifdef USE_SHEEN
		float sheenAlbedo = IBLSheenBRDF( geometryNormal, geometryViewDir, material.sheenRoughness );
		sheenSpecularIndirect += irradiance * material.sheenColor * sheenAlbedo * RECIPROCAL_PI;
		float sheenEnergyComp = 1.0 - max3( material.sheenColor ) * sheenAlbedo;
		diffuse *= sheenEnergyComp;
	#endif
	reflectedLight.indirectDiffuse += diffuse;
}
void RE_IndirectSpecular_Physical( const in vec3 radiance, const in vec3 irradiance, const in vec3 clearcoatRadiance, const in vec3 geometryPosition, const in vec3 geometryNormal, const in vec3 geometryViewDir, const in vec3 geometryClearcoatNormal, const in PhysicalMaterial material, inout ReflectedLight reflectedLight) {
	#ifdef USE_CLEARCOAT
		clearcoatSpecularIndirect += clearcoatRadiance * EnvironmentBRDF( geometryClearcoatNormal, geometryViewDir, material.clearcoatF0, material.clearcoatF90, material.clearcoatRoughness );
	#endif
	#ifdef USE_SHEEN
		sheenSpecularIndirect += irradiance * material.sheenColor * IBLSheenBRDF( geometryNormal, geometryViewDir, material.sheenRoughness ) * RECIPROCAL_PI;
 	#endif
	vec3 singleScatteringDielectric = vec3( 0.0 );
	vec3 multiScatteringDielectric = vec3( 0.0 );
	vec3 singleScatteringMetallic = vec3( 0.0 );
	vec3 multiScatteringMetallic = vec3( 0.0 );
	#ifdef USE_IRIDESCENCE
		computeMultiscatteringIridescence( material.dfg, material.specularColor, material.specularF90, material.iridescence, material.iridescenceF0Dielectric, singleScatteringDielectric, multiScatteringDielectric );
		computeMultiscatteringIridescence( material.dfg, material.diffuseColor, material.specularF90, material.iridescence, material.iridescenceF0Metallic, singleScatteringMetallic, multiScatteringMetallic );
	#else
		computeMultiscattering( material.dfg, material.specularColor, material.specularF90, singleScatteringDielectric, multiScatteringDielectric );
		computeMultiscattering( material.dfg, material.diffuseColor, material.specularF90, singleScatteringMetallic, multiScatteringMetallic );
	#endif
	vec3 singleScattering = mix( singleScatteringDielectric, singleScatteringMetallic, material.metalness );
	vec3 multiScattering = mix( multiScatteringDielectric, multiScatteringMetallic, material.metalness );
	vec3 totalScatteringDielectric = singleScatteringDielectric + multiScatteringDielectric;
	vec3 diffuse = material.diffuseContribution * ( 1.0 - totalScatteringDielectric );
	vec3 cosineWeightedIrradiance = irradiance * RECIPROCAL_PI;
	vec3 indirectSpecular = radiance * singleScattering;
	indirectSpecular += multiScattering * cosineWeightedIrradiance;
	vec3 indirectDiffuse = diffuse * cosineWeightedIrradiance;
	#ifdef USE_SHEEN
		float sheenAlbedo = IBLSheenBRDF( geometryNormal, geometryViewDir, material.sheenRoughness );
		float sheenEnergyComp = 1.0 - max3( material.sheenColor ) * sheenAlbedo;
		indirectSpecular *= sheenEnergyComp;
		indirectDiffuse *= sheenEnergyComp;
	#endif
	reflectedLight.indirectSpecular += indirectSpecular;
	reflectedLight.indirectDiffuse += indirectDiffuse;
}
#define RE_Direct				RE_Direct_Physical
#define RE_Direct_RectArea		RE_Direct_RectArea_Physical
#define RE_IndirectDiffuse		RE_IndirectDiffuse_Physical
#define RE_IndirectSpecular		RE_IndirectSpecular_Physical
float computeSpecularOcclusion( const in float dotNV, const in float ambientOcclusion, const in float roughness ) {
	return saturate( pow( dotNV + ambientOcclusion, exp2( - 16.0 * roughness - 1.0 ) ) - 1.0 + ambientOcclusion );
}`,gm=`
vec3 geometryPosition = - vViewPosition;
vec3 geometryNormal = normal;
vec3 geometryViewDir = ( isOrthographic ) ? vec3( 0, 0, 1 ) : normalize( vViewPosition );
vec3 geometryClearcoatNormal = vec3( 0.0 );
#ifdef USE_CLEARCOAT
	geometryClearcoatNormal = clearcoatNormal;
#endif
#ifdef USE_IRIDESCENCE
	float dotNVi = saturate( dot( normal, geometryViewDir ) );
	if ( material.iridescenceThickness == 0.0 ) {
		material.iridescence = 0.0;
	} else {
		material.iridescence = saturate( material.iridescence );
	}
	if ( material.iridescence > 0.0 ) {
		vec3 iridescenceFresnelDielectric = evalIridescence( 1.0, material.iridescenceIOR, dotNVi, material.iridescenceThickness, material.specularColor );
		vec3 iridescenceFresnelMetallic = evalIridescence( 1.0, material.iridescenceIOR, dotNVi, material.iridescenceThickness, material.diffuseColor );
		material.iridescenceFresnel = mix( iridescenceFresnelDielectric, iridescenceFresnelMetallic, material.metalness );
		material.iridescenceF0Dielectric = Schlick_to_F0( iridescenceFresnelDielectric, 1.0, dotNVi );
		material.iridescenceF0Metallic = Schlick_to_F0( iridescenceFresnelMetallic, 1.0, dotNVi );
	}
#endif
#ifdef STANDARD
	float dotNVms = saturate( dot( geometryNormal, geometryViewDir ) );
	material.dfg = texture2D( dfgLUT, vec2( material.roughness, dotNVms ) ).rg;
	#if ( NUM_SUN_LIGHTS > 0 || NUM_DIR_LIGHTS > 0 || NUM_POINT_LIGHTS > 0 || NUM_SPOT_LIGHTS > 0 )
		float EssMs = material.dfg.x + material.dfg.y;
		material.multiScatteringCompensation = 1.0 + material.specularColorBlended * ( 1.0 / EssMs - 1.0 );
	#endif
#endif
IncidentLight directLight;
#if ( NUM_POINT_LIGHTS > 0 ) && defined( RE_Direct )
	PointLight pointLight;
	#if defined( USE_SHADOWMAP ) && NUM_POINT_LIGHT_SHADOWS > 0
	PointLightShadow pointLightShadow;
	#endif
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_POINT_LIGHTS; i ++ ) {
		pointLight = pointLights[ i ];
		getPointLightInfo( pointLight, geometryPosition, directLight );
		#if defined( USE_SHADOWMAP ) && ( UNROLLED_LOOP_INDEX < NUM_POINT_LIGHT_SHADOWS ) && ( defined( SHADOWMAP_TYPE_PCF ) || defined( SHADOWMAP_TYPE_BASIC ) )
		pointLightShadow = pointLightShadows[ i ];
		directLight.color *= ( directLight.visible && receiveShadow ) ? getPointShadow( pointShadowMap[ i ], pointLightShadow.shadowMapSize, pointLightShadow.shadowIntensity, pointLightShadow.shadowBias, pointLightShadow.shadowRadius, vPointShadowCoord[ i ], pointLightShadow.shadowCameraNear, pointLightShadow.shadowCameraFar ) : 1.0;
		#endif
		RE_Direct( directLight, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
	}
	#pragma unroll_loop_end
#endif
#if ( NUM_SPOT_LIGHTS > 0 ) && defined( RE_Direct )
	SpotLight spotLight;
	vec4 spotColor;
	vec3 spotLightCoord;
	bool inSpotLightMap;
	#if defined( USE_SHADOWMAP ) && NUM_SPOT_LIGHT_SHADOWS > 0
	SpotLightShadow spotLightShadow;
	#endif
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_SPOT_LIGHTS; i ++ ) {
		spotLight = spotLights[ i ];
		getSpotLightInfo( spotLight, geometryPosition, directLight );
		#if ( UNROLLED_LOOP_INDEX < NUM_SPOT_LIGHT_SHADOWS_WITH_MAPS )
		#define SPOT_LIGHT_MAP_INDEX UNROLLED_LOOP_INDEX
		#elif ( UNROLLED_LOOP_INDEX < NUM_SPOT_LIGHT_SHADOWS )
		#define SPOT_LIGHT_MAP_INDEX NUM_SPOT_LIGHT_MAPS
		#else
		#define SPOT_LIGHT_MAP_INDEX ( UNROLLED_LOOP_INDEX - NUM_SPOT_LIGHT_SHADOWS + NUM_SPOT_LIGHT_SHADOWS_WITH_MAPS )
		#endif
		#if ( SPOT_LIGHT_MAP_INDEX < NUM_SPOT_LIGHT_MAPS )
			spotLightCoord = vSpotLightCoord[ i ].xyz / vSpotLightCoord[ i ].w;
			inSpotLightMap = all( lessThan( abs( spotLightCoord * 2. - 1. ), vec3( 1.0 ) ) );
			spotColor = texture2D( spotLightMap[ SPOT_LIGHT_MAP_INDEX ], spotLightCoord.xy );
			directLight.color = inSpotLightMap ? directLight.color * spotColor.rgb : directLight.color;
		#endif
		#undef SPOT_LIGHT_MAP_INDEX
		#if defined( USE_SHADOWMAP ) && ( UNROLLED_LOOP_INDEX < NUM_SPOT_LIGHT_SHADOWS )
		spotLightShadow = spotLightShadows[ i ];
		directLight.color *= ( directLight.visible && receiveShadow ) ? getShadow( spotShadowMap[ i ], spotLightShadow.shadowMapSize, spotLightShadow.shadowIntensity, spotLightShadow.shadowBias, spotLightShadow.shadowRadius, vSpotLightCoord[ i ] ) : 1.0;
		#endif
		RE_Direct( directLight, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
	}
	#pragma unroll_loop_end
#endif
#if ( NUM_SUN_LIGHTS > 0 ) && defined( RE_Direct )
	SunLight sunLight;
	#if defined( USE_SHADOWMAP ) && NUM_SUN_LIGHT_SHADOWS > 0
	SunLightShadow sunLightShadow;
	#endif
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_SUN_LIGHTS; i ++ ) {
		sunLight = sunLights[ i ];
		getSunLightInfo( sunLight, directLight );
		#if defined( USE_SHADOWMAP ) && ( UNROLLED_LOOP_INDEX < NUM_SUN_LIGHT_SHADOWS )
		sunLightShadow = sunLightShadows[ i ];
		directLight.color *= ( directLight.visible && receiveShadow ) ? getSunShadow( sunShadowMap[ i ], sunLightShadow, UNROLLED_LOOP_INDEX ) : 1.0;
		#endif
		RE_Direct( directLight, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
	}
	#pragma unroll_loop_end
#endif
#if ( NUM_DIR_LIGHTS > 0 ) && defined( RE_Direct )
	DirectionalLight directionalLight;
	#if defined( USE_SHADOWMAP ) && NUM_DIR_LIGHT_SHADOWS > 0
	DirectionalLightShadow directionalLightShadow;
	#endif
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_DIR_LIGHTS; i ++ ) {
		directionalLight = directionalLights[ i ];
		getDirectionalLightInfo( directionalLight, directLight );
		#if defined( USE_SHADOWMAP ) && ( UNROLLED_LOOP_INDEX < NUM_DIR_LIGHT_SHADOWS )
		directionalLightShadow = directionalLightShadows[ i ];
		directLight.color *= ( directLight.visible && receiveShadow ) ? getShadow( directionalShadowMap[ i ], directionalLightShadow.shadowMapSize, directionalLightShadow.shadowIntensity, directionalLightShadow.shadowBias, directionalLightShadow.shadowRadius, vDirectionalShadowCoord[ i ] ) : 1.0;
		#endif
		RE_Direct( directLight, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
	}
	#pragma unroll_loop_end
#endif
#if ( NUM_RECT_AREA_LIGHTS > 0 ) && defined( RE_Direct_RectArea )
	RectAreaLight rectAreaLight;
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_RECT_AREA_LIGHTS; i ++ ) {
		rectAreaLight = rectAreaLights[ i ];
		RE_Direct_RectArea( rectAreaLight, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
	}
	#pragma unroll_loop_end
#endif
#if defined( RE_IndirectDiffuse )
	vec3 iblIrradiance = vec3( 0.0 );
	vec3 irradiance = getAmbientLightIrradiance( ambientLightColor );
	#if defined( USE_LIGHT_PROBES )
		irradiance += getLightProbeIrradiance( lightProbe, geometryNormal );
	#endif
	#if ( NUM_HEMI_LIGHTS > 0 )
		#pragma unroll_loop_start
		for ( int i = 0; i < NUM_HEMI_LIGHTS; i ++ ) {
			irradiance += getHemisphereLightIrradiance( hemisphereLights[ i ], geometryNormal );
		}
		#pragma unroll_loop_end
	#endif
	#ifdef USE_LIGHT_PROBES_GRID
		vec3 probeWorldPos = ( ( vec4( geometryPosition, 1.0 ) - viewMatrix[ 3 ] ) * viewMatrix ).xyz;
		vec3 probeWorldNormal = transformNormalByInverseViewMatrix( geometryNormal, viewMatrix );
		irradiance += getLightProbeGridIrradiance( probeWorldPos, probeWorldNormal );
	#endif
#endif
#if defined( RE_IndirectSpecular )
	vec3 radiance = vec3( 0.0 );
	vec3 clearcoatRadiance = vec3( 0.0 );
#endif`,xm=`#if defined( RE_IndirectDiffuse )
	#ifdef USE_LIGHTMAP
		vec4 lightMapTexel = texture2D( lightMap, vLightMapUv );
		vec3 lightMapIrradiance = lightMapTexel.rgb * lightMapIntensity;
		irradiance += lightMapIrradiance;
	#endif
	#if defined( USE_ENVMAP ) && defined( ENVMAP_TYPE_CUBE_UV )
		#if defined( STANDARD ) || defined( LAMBERT ) || defined( PHONG )
			iblIrradiance += getIBLIrradiance( geometryNormal );
		#endif
	#endif
#endif
#if defined( USE_ENVMAP ) && defined( RE_IndirectSpecular )
	#ifdef USE_ANISOTROPY
		vec3 iblRadiance = getIBLAnisotropyRadiance( geometryViewDir, geometryNormal, material.roughness, material.anisotropyB, material.anisotropy );
	#else
		vec3 iblRadiance = getIBLRadiance( geometryViewDir, geometryNormal, material.roughness );
	#endif
	#ifdef USE_RETROREFLECTION
		#ifdef USE_ANISOTROPY
			vec3 retroIBLRadiance = getIBLAnisotropyRetroRadiance( geometryViewDir, geometryNormal, material.roughness, material.anisotropyB, material.anisotropy );
		#else
			vec3 retroIBLRadiance = getIBLRetroRadiance( geometryViewDir, geometryNormal, material.roughness );
		#endif
		iblRadiance = mix( iblRadiance, retroIBLRadiance, saturate( material.retroreflectivity ) );
	#endif
	radiance += iblRadiance;
	#ifdef USE_CLEARCOAT
		clearcoatRadiance += getIBLRadiance( geometryViewDir, geometryClearcoatNormal, material.clearcoatRoughness );
	#endif
#endif`,_m=`#if defined( RE_IndirectDiffuse )
	#if defined( LAMBERT ) || defined( PHONG )
		irradiance += iblIrradiance;
	#endif
	RE_IndirectDiffuse( irradiance, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
#endif
#if defined( RE_IndirectSpecular )
	RE_IndirectSpecular( radiance, iblIrradiance, clearcoatRadiance, geometryPosition, geometryNormal, geometryViewDir, geometryClearcoatNormal, material, reflectedLight );
#endif`,ym=`#ifdef USE_LIGHT_PROBES_GRID
uniform highp sampler3D probesSH;
uniform vec3 probesMin;
uniform vec3 probesMax;
uniform vec3 probesResolution;
vec3 getLightProbeGridIrradiance( vec3 worldPos, vec3 worldNormal ) {
	vec3 res = probesResolution;
	vec3 gridRange = probesMax - probesMin;
	vec3 resMinusOne = res - 1.0;
	vec3 probeSpacing = gridRange / resMinusOne;
	vec3 samplePos = worldPos + worldNormal * probeSpacing * 0.5;
	vec3 uvw = clamp( ( samplePos - probesMin ) / gridRange, 0.0, 1.0 );
	uvw = uvw * resMinusOne / res + 0.5 / res;
	float nz          = res.z;
	float paddedSlices = nz + 2.0;
	float atlasDepth  = 7.0 * paddedSlices;
	float uvZBase     = uvw.z * nz + 1.0;
	vec4 s0 = texture( probesSH, vec3( uvw.xy, ( uvZBase                       ) / atlasDepth ) );
	vec4 s1 = texture( probesSH, vec3( uvw.xy, ( uvZBase +       paddedSlices   ) / atlasDepth ) );
	vec4 s2 = texture( probesSH, vec3( uvw.xy, ( uvZBase + 2.0 * paddedSlices   ) / atlasDepth ) );
	vec4 s3 = texture( probesSH, vec3( uvw.xy, ( uvZBase + 3.0 * paddedSlices   ) / atlasDepth ) );
	vec4 s4 = texture( probesSH, vec3( uvw.xy, ( uvZBase + 4.0 * paddedSlices   ) / atlasDepth ) );
	vec4 s5 = texture( probesSH, vec3( uvw.xy, ( uvZBase + 5.0 * paddedSlices   ) / atlasDepth ) );
	vec4 s6 = texture( probesSH, vec3( uvw.xy, ( uvZBase + 6.0 * paddedSlices   ) / atlasDepth ) );
	vec3 c0 = s0.xyz;
	vec3 c1 = vec3( s0.w, s1.xy );
	vec3 c2 = vec3( s1.zw, s2.x );
	vec3 c3 = s2.yzw;
	vec3 c4 = s3.xyz;
	vec3 c5 = vec3( s3.w, s4.xy );
	vec3 c6 = vec3( s4.zw, s5.x );
	vec3 c7 = s5.yzw;
	vec3 c8 = s6.xyz;
	float x = worldNormal.x, y = worldNormal.y, z = worldNormal.z;
	vec3 result = c0 * 0.886227;
	result += c1 * 2.0 * 0.511664 * y;
	result += c2 * 2.0 * 0.511664 * z;
	result += c3 * 2.0 * 0.511664 * x;
	result += c4 * 2.0 * 0.429043 * x * y;
	result += c5 * 2.0 * 0.429043 * y * z;
	result += c6 * ( 0.743125 * z * z - 0.247708 );
	result += c7 * 2.0 * 0.429043 * x * z;
	result += c8 * 0.429043 * ( x * x - y * y );
	return max( result, vec3( 0.0 ) );
}
#endif`,vm=`#if defined( USE_LOGARITHMIC_DEPTH_BUFFER )
	gl_FragDepth = vIsPerspective == 0.0 ? gl_FragCoord.z : log2( vFragDepth ) * logDepthBufFC * 0.5;
#endif`,Mm=`#if defined( USE_LOGARITHMIC_DEPTH_BUFFER )
	uniform float logDepthBufFC;
	varying float vFragDepth;
	varying float vIsPerspective;
#endif`,Sm=`#ifdef USE_LOGARITHMIC_DEPTH_BUFFER
	varying float vFragDepth;
	varying float vIsPerspective;
#endif`,bm=`#ifdef USE_LOGARITHMIC_DEPTH_BUFFER
	vFragDepth = 1.0 + gl_Position.w;
	vIsPerspective = float( isPerspectiveMatrix( projectionMatrix ) );
#endif`,Em=`#ifdef USE_MAP
	vec4 sampledDiffuseColor = texture2D( map, vMapUv );
	#ifdef DECODE_VIDEO_TEXTURE
		sampledDiffuseColor = sRGBTransferEOTF( sampledDiffuseColor );
	#endif
	diffuseColor *= sampledDiffuseColor;
#endif`,wm=`#ifdef USE_MAP
	uniform sampler2D map;
#endif`,Tm=`#if defined( USE_MAP ) || defined( USE_ALPHAMAP )
	#if defined( USE_POINTS_UV )
		vec2 uv = vUv;
	#else
		vec2 uv = ( uvTransform * vec3( gl_PointCoord.x, 1.0 - gl_PointCoord.y, 1 ) ).xy;
	#endif
#endif
#ifdef USE_MAP
	diffuseColor *= texture2D( map, uv );
#endif
#ifdef USE_ALPHAMAP
	diffuseColor.a *= texture2D( alphaMap, uv ).g;
#endif`,Am=`#if defined( USE_POINTS_UV )
	varying vec2 vUv;
#else
	#if defined( USE_MAP ) || defined( USE_ALPHAMAP )
		uniform mat3 uvTransform;
	#endif
#endif
#ifdef USE_MAP
	uniform sampler2D map;
#endif
#ifdef USE_ALPHAMAP
	uniform sampler2D alphaMap;
#endif`,Rm=`float metalnessFactor = metalness;
#ifdef USE_METALNESSMAP
	vec4 texelMetalness = texture2D( metalnessMap, vMetalnessMapUv );
	metalnessFactor *= texelMetalness.b;
#endif`,Cm=`#ifdef USE_METALNESSMAP
	uniform sampler2D metalnessMap;
#endif`,Pm=`#ifdef USE_INSTANCING_MORPH
	float morphTargetInfluences[ MORPHTARGETS_COUNT ];
	float morphTargetBaseInfluence = texelFetch( morphTexture, ivec2( 0, gl_InstanceID ), 0 ).r;
	for ( int i = 0; i < MORPHTARGETS_COUNT; i ++ ) {
		morphTargetInfluences[i] =  texelFetch( morphTexture, ivec2( i + 1, gl_InstanceID ), 0 ).r;
	}
#endif`,Im=`#if defined( USE_MORPHCOLORS )
	vColor *= morphTargetBaseInfluence;
	for ( int i = 0; i < MORPHTARGETS_COUNT; i ++ ) {
		#if defined( USE_COLOR_ALPHA )
			if ( morphTargetInfluences[ i ] != 0.0 ) vColor += getMorph( gl_VertexID, i, 2 ) * morphTargetInfluences[ i ];
		#elif defined( USE_COLOR )
			if ( morphTargetInfluences[ i ] != 0.0 ) vColor += getMorph( gl_VertexID, i, 2 ).rgb * morphTargetInfluences[ i ];
		#endif
	}
#endif`,Lm=`#ifdef USE_MORPHNORMALS
	objectNormal *= morphTargetBaseInfluence;
	for ( int i = 0; i < MORPHTARGETS_COUNT; i ++ ) {
		if ( morphTargetInfluences[ i ] != 0.0 ) objectNormal += getMorph( gl_VertexID, i, 1 ).xyz * morphTargetInfluences[ i ];
	}
#endif`,Dm=`#ifdef USE_MORPHTARGETS
	#ifndef USE_INSTANCING_MORPH
		uniform float morphTargetBaseInfluence;
		uniform float morphTargetInfluences[ MORPHTARGETS_COUNT ];
	#endif
	uniform sampler2DArray morphTargetsTexture;
	uniform ivec2 morphTargetsTextureSize;
	vec4 getMorph( const in int vertexIndex, const in int morphTargetIndex, const in int offset ) {
		int texelIndex = vertexIndex * MORPHTARGETS_TEXTURE_STRIDE + offset;
		int y = texelIndex / morphTargetsTextureSize.x;
		int x = texelIndex - y * morphTargetsTextureSize.x;
		ivec3 morphUV = ivec3( x, y, morphTargetIndex );
		return texelFetch( morphTargetsTexture, morphUV, 0 );
	}
#endif`,Um=`#ifdef USE_MORPHTARGETS
	transformed *= morphTargetBaseInfluence;
	for ( int i = 0; i < MORPHTARGETS_COUNT; i ++ ) {
		if ( morphTargetInfluences[ i ] != 0.0 ) transformed += getMorph( gl_VertexID, i, 0 ).xyz * morphTargetInfluences[ i ];
	}
#endif`,Nm=`float faceDirection = gl_FrontFacing ? 1.0 : - 1.0;
#ifdef FLAT_SHADED
	vec3 fdx = dFdx( vViewPosition );
	vec3 fdy = dFdy( vViewPosition );
	vec3 normal = normalize( cross( fdx, fdy ) );
#else
	vec3 normal = normalize( vNormal );
	#ifdef DOUBLE_SIDED
		normal *= faceDirection;
	#endif
#endif
#if defined( USE_NORMALMAP_TANGENTSPACE ) || defined( USE_CLEARCOAT_NORMALMAP ) || defined( USE_ANISOTROPY )
	#ifdef USE_TANGENT
		mat3 tbn = mat3( normalize( vTangent ), normalize( vBitangent ), normal );
	#else
		mat3 tbn = getTangentFrame( - vViewPosition, normal,
		#if defined( USE_NORMALMAP )
			vNormalMapUv
		#elif defined( USE_CLEARCOAT_NORMALMAP )
			vClearcoatNormalMapUv
		#else
			vUv
		#endif
		);
	#endif
	#ifdef DOUBLE_SIDED
		tbn[0] *= faceDirection;
		tbn[1] *= faceDirection;
	#endif
#endif
#ifdef USE_CLEARCOAT_NORMALMAP
	#ifdef USE_TANGENT
		mat3 tbn2 = mat3( normalize( vTangent ), normalize( vBitangent ), normal );
	#else
		mat3 tbn2 = getTangentFrame( - vViewPosition, normal, vClearcoatNormalMapUv );
	#endif
	#ifdef DOUBLE_SIDED
		tbn2[0] *= faceDirection;
		tbn2[1] *= faceDirection;
	#endif
#endif
vec3 nonPerturbedNormal = normal;`,Fm=`#ifdef USE_NORMALMAP_OBJECTSPACE
	normal = texture2D( normalMap, vNormalMapUv ).xyz * 2.0 - 1.0;
	#ifdef FLIP_SIDED
		normal = - normal;
	#endif
	#ifdef DOUBLE_SIDED
		normal = normal * faceDirection;
	#endif
	normal = normalize( normalMatrix * normal );
#elif defined( USE_NORMALMAP_TANGENTSPACE )
	vec3 mapN = texture2D( normalMap, vNormalMapUv ).xyz * 2.0 - 1.0;
	#if defined( USE_PACKED_NORMALMAP )
		mapN = vec3( mapN.xy, sqrt( saturate( 1.0 - dot( mapN.xy, mapN.xy ) ) ) );
	#endif
	mapN.xy *= normalScale;
	normal = normalize( tbn * mapN );
#elif defined( USE_BUMPMAP )
	normal = perturbNormalArb( - vViewPosition, normal, dHdxy_fwd(), faceDirection );
#endif`,Bm=`#ifndef FLAT_SHADED
	varying vec3 vNormal;
	#ifdef USE_TANGENT
		varying vec3 vTangent;
		varying vec3 vBitangent;
	#endif
#endif`,Om=`#ifndef FLAT_SHADED
	varying vec3 vNormal;
	#ifdef USE_TANGENT
		varying vec3 vTangent;
		varying vec3 vBitangent;
	#endif
#endif`,km=`#ifndef FLAT_SHADED
	vNormal = normalize( transformedNormal );
	#ifdef USE_TANGENT
		vTangent = normalize( transformedTangent );
		vBitangent = normalize( cross( vNormal, vTangent ) * tangent.w );
		#ifdef FLIP_SIDED
			vBitangent = - vBitangent;
		#endif
	#endif
#endif`,zm=`#ifdef USE_NORMALMAP
	uniform sampler2D normalMap;
	uniform vec2 normalScale;
#endif
#ifdef USE_NORMALMAP_OBJECTSPACE
	uniform mat3 normalMatrix;
#endif
#if ! defined ( USE_TANGENT ) && ( defined ( USE_NORMALMAP_TANGENTSPACE ) || defined ( USE_CLEARCOAT_NORMALMAP ) || defined( USE_ANISOTROPY ) )
	mat3 getTangentFrame( vec3 eye_pos, vec3 surf_norm, vec2 uv ) {
		vec3 q0 = dFdx( eye_pos.xyz );
		vec3 q1 = dFdy( eye_pos.xyz );
		vec2 st0 = dFdx( uv.st );
		vec2 st1 = dFdy( uv.st );
		vec3 N = surf_norm;
		vec3 q1perp = cross( q1, N );
		vec3 q0perp = cross( N, q0 );
		vec3 T = q1perp * st0.x + q0perp * st1.x;
		vec3 B = q1perp * st0.y + q0perp * st1.y;
		float det = max( dot( T, T ), dot( B, B ) );
		float scale = ( det == 0.0 ) ? 0.0 : inversesqrt( det );
		return mat3( T * scale, B * scale, N );
	}
#endif`,Hm=`#ifdef USE_CLEARCOAT
	vec3 clearcoatNormal = nonPerturbedNormal;
#endif`,Vm=`#ifdef USE_CLEARCOAT_NORMALMAP
	vec3 clearcoatMapN = texture2D( clearcoatNormalMap, vClearcoatNormalMapUv ).xyz * 2.0 - 1.0;
	clearcoatMapN.xy *= clearcoatNormalScale;
	clearcoatNormal = normalize( tbn2 * clearcoatMapN );
#endif`,Gm=`#ifdef USE_CLEARCOATMAP
	uniform sampler2D clearcoatMap;
#endif
#ifdef USE_CLEARCOAT_NORMALMAP
	uniform sampler2D clearcoatNormalMap;
	uniform vec2 clearcoatNormalScale;
#endif
#ifdef USE_CLEARCOAT_ROUGHNESSMAP
	uniform sampler2D clearcoatRoughnessMap;
#endif`,Wm=`#ifdef USE_IRIDESCENCEMAP
	uniform sampler2D iridescenceMap;
#endif
#ifdef USE_IRIDESCENCE_THICKNESSMAP
	uniform sampler2D iridescenceThicknessMap;
#endif`,Xm=`#ifdef OPAQUE
diffuseColor.a = 1.0;
#endif
#ifdef USE_TRANSMISSION
diffuseColor.a *= material.transmissionAlpha;
#endif
gl_FragColor = vec4( outgoingLight, diffuseColor.a );`,qm=`vec3 packNormalToRGB( const in vec3 normal ) {
	return normalize( normal ) * 0.5 + 0.5;
}
vec3 unpackRGBToNormal( const in vec3 rgb ) {
	return 2.0 * rgb.xyz - 1.0;
}
const float PackUpscale = 256. / 255.;const float UnpackDownscale = 255. / 256.;const float ShiftRight8 = 1. / 256.;
const float Inv255 = 1. / 255.;
const vec4 PackFactors = vec4( 1.0, 256.0, 256.0 * 256.0, 256.0 * 256.0 * 256.0 );
const vec2 UnpackFactors2 = vec2( UnpackDownscale, 1.0 / PackFactors.g );
const vec3 UnpackFactors3 = vec3( UnpackDownscale / PackFactors.rg, 1.0 / PackFactors.b );
const vec4 UnpackFactors4 = vec4( UnpackDownscale / PackFactors.rgb, 1.0 / PackFactors.a );
vec4 packDepthToRGBA( const in float v ) {
	if( v <= 0.0 )
		return vec4( 0., 0., 0., 0. );
	if( v >= 1.0 )
		return vec4( 1., 1., 1., 1. );
	float vuf;
	float af = modf( v * PackFactors.a, vuf );
	float bf = modf( vuf * ShiftRight8, vuf );
	float gf = modf( vuf * ShiftRight8, vuf );
	return vec4( vuf * Inv255, gf * PackUpscale, bf * PackUpscale, af );
}
vec3 packDepthToRGB( const in float v ) {
	if( v <= 0.0 )
		return vec3( 0., 0., 0. );
	if( v >= 1.0 )
		return vec3( 1., 1., 1. );
	float vuf;
	float bf = modf( v * PackFactors.b, vuf );
	float gf = modf( vuf * ShiftRight8, vuf );
	return vec3( vuf * Inv255, gf * PackUpscale, bf );
}
vec2 packDepthToRG( const in float v ) {
	if( v <= 0.0 )
		return vec2( 0., 0. );
	if( v >= 1.0 )
		return vec2( 1., 1. );
	float vuf;
	float gf = modf( v * 256., vuf );
	return vec2( vuf * Inv255, gf );
}
float unpackRGBAToDepth( const in vec4 v ) {
	return dot( v, UnpackFactors4 );
}
float unpackRGBToDepth( const in vec3 v ) {
	return dot( v, UnpackFactors3 );
}
float unpackRGToDepth( const in vec2 v ) {
	return v.r * UnpackFactors2.r + v.g * UnpackFactors2.g;
}
vec4 pack2HalfToRGBA( const in vec2 v ) {
	vec4 r = vec4( v.x, fract( v.x * 255.0 ), v.y, fract( v.y * 255.0 ) );
	return vec4( r.x - r.y / 255.0, r.y, r.z - r.w / 255.0, r.w );
}
vec2 unpackRGBATo2Half( const in vec4 v ) {
	return vec2( v.x + ( v.y / 255.0 ), v.z + ( v.w / 255.0 ) );
}
float viewZToOrthographicDepth( const in float viewZ, const in float near, const in float far ) {
	return ( viewZ + near ) / ( near - far );
}
float orthographicDepthToViewZ( const in float depth, const in float near, const in float far ) {
	#ifdef USE_REVERSED_DEPTH_BUFFER
	
		return depth * ( far - near ) - far;
	#else
		return depth * ( near - far ) - near;
	#endif
}
float viewZToPerspectiveDepth( const in float viewZ, const in float near, const in float far ) {
	return ( ( near + viewZ ) * far ) / ( ( far - near ) * viewZ );
}
float perspectiveDepthToViewZ( const in float depth, const in float near, const in float far ) {
	
	#ifdef USE_REVERSED_DEPTH_BUFFER
		return ( near * far ) / ( ( near - far ) * depth - near );
	#else
		return ( near * far ) / ( ( far - near ) * depth - far );
	#endif
}`,Ym=`#ifdef PREMULTIPLIED_ALPHA
	gl_FragColor.rgb *= gl_FragColor.a;
#endif`,Zm=`vec4 mvPosition = vec4( transformed, 1.0 );
#ifdef USE_BATCHING
	mvPosition = batchingMatrix * mvPosition;
#endif
#ifdef USE_INSTANCING
	mvPosition = instanceMatrix * mvPosition;
#endif
mvPosition = modelViewMatrix * mvPosition;
gl_Position = projectionMatrix * mvPosition;`,Jm=`#ifdef DITHERING
	gl_FragColor.rgb = dithering( gl_FragColor.rgb );
#endif`,$m=`#ifdef DITHERING
	vec3 dithering( vec3 color ) {
		float grid_position = rand( gl_FragCoord.xy );
		vec3 dither_shift_RGB = vec3( 0.25 / 255.0, -0.25 / 255.0, 0.25 / 255.0 );
		dither_shift_RGB = mix( 2.0 * dither_shift_RGB, -2.0 * dither_shift_RGB, grid_position );
		return color + dither_shift_RGB;
	}
#endif`,Km=`float roughnessFactor = roughness;
#ifdef USE_ROUGHNESSMAP
	vec4 texelRoughness = texture2D( roughnessMap, vRoughnessMapUv );
	roughnessFactor *= texelRoughness.g;
#endif`,Qm=`#ifdef USE_ROUGHNESSMAP
	uniform sampler2D roughnessMap;
#endif`,jm=`#if NUM_SPOT_LIGHT_COORDS > 0
	varying vec4 vSpotLightCoord[ NUM_SPOT_LIGHT_COORDS ];
#endif
#if NUM_SPOT_LIGHT_MAPS > 0
	uniform sampler2D spotLightMap[ NUM_SPOT_LIGHT_MAPS ];
#endif
#ifdef USE_SHADOWMAP
	#if NUM_SUN_LIGHT_SHADOWS > 0
		#define SUN_LIGHT_CASCADES 2
		#if defined( SHADOWMAP_TYPE_PCF )
			uniform sampler2DShadow sunShadowMap[ NUM_SUN_LIGHT_SHADOWS ];
		#else
			uniform sampler2D sunShadowMap[ NUM_SUN_LIGHT_SHADOWS ];
		#endif
		uniform mat4 sunShadowMatrix[ NUM_SUN_LIGHT_SHADOWS * SUN_LIGHT_CASCADES ];
		uniform vec4 sunShadowCascade[ NUM_SUN_LIGHT_SHADOWS * SUN_LIGHT_CASCADES ];
		varying vec4 vSunShadowWorldPosition;
		varying vec3 vSunShadowWorldNormal;
		struct SunLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
		};
		uniform SunLightShadow sunLightShadows[ NUM_SUN_LIGHT_SHADOWS ];
	#endif
	#if NUM_DIR_LIGHT_SHADOWS > 0
		#if defined( SHADOWMAP_TYPE_PCF )
			uniform sampler2DShadow directionalShadowMap[ NUM_DIR_LIGHT_SHADOWS ];
		#else
			uniform sampler2D directionalShadowMap[ NUM_DIR_LIGHT_SHADOWS ];
		#endif
		varying vec4 vDirectionalShadowCoord[ NUM_DIR_LIGHT_SHADOWS ];
		struct DirectionalLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
		};
		uniform DirectionalLightShadow directionalLightShadows[ NUM_DIR_LIGHT_SHADOWS ];
	#endif
	#if NUM_SPOT_LIGHT_SHADOWS > 0
		#if defined( SHADOWMAP_TYPE_PCF )
			uniform sampler2DShadow spotShadowMap[ NUM_SPOT_LIGHT_SHADOWS ];
		#else
			uniform sampler2D spotShadowMap[ NUM_SPOT_LIGHT_SHADOWS ];
		#endif
		struct SpotLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
		};
		uniform SpotLightShadow spotLightShadows[ NUM_SPOT_LIGHT_SHADOWS ];
	#endif
	#if NUM_POINT_LIGHT_SHADOWS > 0
		#if defined( SHADOWMAP_TYPE_PCF )
			uniform samplerCubeShadow pointShadowMap[ NUM_POINT_LIGHT_SHADOWS ];
		#elif defined( SHADOWMAP_TYPE_BASIC )
			uniform samplerCube pointShadowMap[ NUM_POINT_LIGHT_SHADOWS ];
		#endif
		varying vec4 vPointShadowCoord[ NUM_POINT_LIGHT_SHADOWS ];
		struct PointLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
			float shadowCameraNear;
			float shadowCameraFar;
		};
		uniform PointLightShadow pointLightShadows[ NUM_POINT_LIGHT_SHADOWS ];
	#endif
	#if defined( SHADOWMAP_TYPE_PCF )
		float interleavedGradientNoise( vec2 position ) {
			return fract( 52.9829189 * fract( dot( position, vec2( 0.06711056, 0.00583715 ) ) ) );
		}
		vec2 vogelDiskSample( int sampleIndex, int samplesCount, float phi ) {
			const float goldenAngle = 2.399963229728653;
			float r = sqrt( ( float( sampleIndex ) + 0.5 ) / float( samplesCount ) );
			float theta = float( sampleIndex ) * goldenAngle + phi;
			return vec2( cos( theta ), sin( theta ) ) * r;
		}
	#endif
	#if defined( SHADOWMAP_TYPE_PCF )
		float getShadow( sampler2DShadow shadowMap, vec2 shadowMapSize, float shadowIntensity, float shadowBias, float shadowRadius, vec4 shadowCoord ) {
			float shadow = 1.0;
			shadowCoord.xyz /= shadowCoord.w;
			shadowCoord.z += shadowBias;
			bool inFrustum = shadowCoord.x >= 0.0 && shadowCoord.x <= 1.0 && shadowCoord.y >= 0.0 && shadowCoord.y <= 1.0;
			bool frustumTest = inFrustum && shadowCoord.z <= 1.0;
			if ( frustumTest ) {
				vec2 texelSize = vec2( 1.0 ) / shadowMapSize;
				float radius = shadowRadius * texelSize.x;
				float phi = interleavedGradientNoise( gl_FragCoord.xy ) * PI2;
				shadow = (
					texture( shadowMap, vec3( shadowCoord.xy + vogelDiskSample( 0, 5, phi ) * radius, shadowCoord.z ) ) +
					texture( shadowMap, vec3( shadowCoord.xy + vogelDiskSample( 1, 5, phi ) * radius, shadowCoord.z ) ) +
					texture( shadowMap, vec3( shadowCoord.xy + vogelDiskSample( 2, 5, phi ) * radius, shadowCoord.z ) ) +
					texture( shadowMap, vec3( shadowCoord.xy + vogelDiskSample( 3, 5, phi ) * radius, shadowCoord.z ) ) +
					texture( shadowMap, vec3( shadowCoord.xy + vogelDiskSample( 4, 5, phi ) * radius, shadowCoord.z ) )
				) * 0.2;
			}
			return mix( 1.0, shadow, shadowIntensity );
		}
	#elif defined( SHADOWMAP_TYPE_VSM )
		float getShadow( sampler2D shadowMap, vec2 shadowMapSize, float shadowIntensity, float shadowBias, float shadowRadius, vec4 shadowCoord ) {
			float shadow = 1.0;
			shadowCoord.xyz /= shadowCoord.w;
			#ifdef USE_REVERSED_DEPTH_BUFFER
				shadowCoord.z -= shadowBias;
			#else
				shadowCoord.z += shadowBias;
			#endif
			bool inFrustum = shadowCoord.x >= 0.0 && shadowCoord.x <= 1.0 && shadowCoord.y >= 0.0 && shadowCoord.y <= 1.0;
			bool frustumTest = inFrustum && shadowCoord.z <= 1.0;
			if ( frustumTest ) {
				vec2 distribution = texture2D( shadowMap, shadowCoord.xy ).rg;
				float mean = distribution.x;
				float variance = distribution.y * distribution.y;
				#ifdef USE_REVERSED_DEPTH_BUFFER
					float hard_shadow = step( mean, shadowCoord.z );
				#else
					float hard_shadow = step( shadowCoord.z, mean );
				#endif
				
				if ( hard_shadow == 1.0 ) {
					shadow = 1.0;
				} else {
					variance = max( variance, 0.0000001 );
					float d = shadowCoord.z - mean;
					float p_max = variance / ( variance + d * d );
					p_max = clamp( ( p_max - 0.3 ) / 0.65, 0.0, 1.0 );
					shadow = max( hard_shadow, p_max );
				}
			}
			return mix( 1.0, shadow, shadowIntensity );
		}
	#else
		float getShadow( sampler2D shadowMap, vec2 shadowMapSize, float shadowIntensity, float shadowBias, float shadowRadius, vec4 shadowCoord ) {
			float shadow = 1.0;
			shadowCoord.xyz /= shadowCoord.w;
			#ifdef USE_REVERSED_DEPTH_BUFFER
				shadowCoord.z -= shadowBias;
			#else
				shadowCoord.z += shadowBias;
			#endif
			bool inFrustum = shadowCoord.x >= 0.0 && shadowCoord.x <= 1.0 && shadowCoord.y >= 0.0 && shadowCoord.y <= 1.0;
			bool frustumTest = inFrustum && shadowCoord.z <= 1.0;
			if ( frustumTest ) {
				float depth = texture2D( shadowMap, shadowCoord.xy ).r;
				#ifdef USE_REVERSED_DEPTH_BUFFER
					shadow = step( depth, shadowCoord.z );
				#else
					shadow = step( shadowCoord.z, depth );
				#endif
			}
			return mix( 1.0, shadow, shadowIntensity );
		}
	#endif
	#if NUM_SUN_LIGHT_SHADOWS > 0
		float getSunShadow(
			#if defined( SHADOWMAP_TYPE_PCF )
				sampler2DShadow shadowMap,
			#else
				sampler2D shadowMap,
			#endif
			SunLightShadow sunLightShadow,
			int shadowIndex
		) {
			vec4 shadowWorldPosition = vec4( vSunShadowWorldPosition.xyz + vSunShadowWorldNormal * sunLightShadow.shadowNormalBias, 1.0 );
			float viewDepth = vSunShadowWorldPosition.w;
			int cascadeOffset = shadowIndex * SUN_LIGHT_CASCADES;
			float shadow = 1.0;
			for ( int i = SUN_LIGHT_CASCADES - 1; i >= 0; i -- ) {
				vec4 cascade = sunShadowCascade[ cascadeOffset + i ];
				if ( viewDepth >= cascade.x && viewDepth < cascade.y ) {
					float cascadeShadow = getShadow(
						shadowMap,
						sunLightShadow.shadowMapSize,
						sunLightShadow.shadowIntensity,
						sunLightShadow.shadowBias,
						sunLightShadow.shadowRadius,
						sunShadowMatrix[ cascadeOffset + i ] * shadowWorldPosition
					);
					shadow = mix( cascadeShadow, shadow, smoothstep( cascade.z, cascade.y, viewDepth ) );
				}
			}
			return shadow;
		}
	#endif
	#if NUM_POINT_LIGHT_SHADOWS > 0
	#if defined( SHADOWMAP_TYPE_PCF )
	float getPointShadow( samplerCubeShadow shadowMap, vec2 shadowMapSize, float shadowIntensity, float shadowBias, float shadowRadius, vec4 shadowCoord, float shadowCameraNear, float shadowCameraFar ) {
		float shadow = 1.0;
		vec3 lightToPosition = shadowCoord.xyz;
		vec3 bd3D = normalize( lightToPosition );
		vec3 absVec = abs( lightToPosition );
		float viewSpaceZ = max( max( absVec.x, absVec.y ), absVec.z );
		if ( viewSpaceZ - shadowCameraFar <= 0.0 && viewSpaceZ - shadowCameraNear >= 0.0 ) {
			#ifdef USE_REVERSED_DEPTH_BUFFER
				float dp = ( shadowCameraNear * ( shadowCameraFar - viewSpaceZ ) ) / ( viewSpaceZ * ( shadowCameraFar - shadowCameraNear ) );
				dp -= shadowBias;
			#else
				float dp = ( shadowCameraFar * ( viewSpaceZ - shadowCameraNear ) ) / ( viewSpaceZ * ( shadowCameraFar - shadowCameraNear ) );
				dp += shadowBias;
			#endif
			float texelSize = shadowRadius / shadowMapSize.x;
			vec3 absDir = abs( bd3D );
			vec3 tangent = absDir.x > absDir.z ? vec3( 0.0, 1.0, 0.0 ) : vec3( 1.0, 0.0, 0.0 );
			tangent = normalize( cross( bd3D, tangent ) );
			vec3 bitangent = cross( bd3D, tangent );
			float phi = interleavedGradientNoise( gl_FragCoord.xy ) * PI2;
			vec2 sample0 = vogelDiskSample( 0, 5, phi );
			vec2 sample1 = vogelDiskSample( 1, 5, phi );
			vec2 sample2 = vogelDiskSample( 2, 5, phi );
			vec2 sample3 = vogelDiskSample( 3, 5, phi );
			vec2 sample4 = vogelDiskSample( 4, 5, phi );
			shadow = (
				texture( shadowMap, vec4( bd3D + ( tangent * sample0.x + bitangent * sample0.y ) * texelSize, dp ) ) +
				texture( shadowMap, vec4( bd3D + ( tangent * sample1.x + bitangent * sample1.y ) * texelSize, dp ) ) +
				texture( shadowMap, vec4( bd3D + ( tangent * sample2.x + bitangent * sample2.y ) * texelSize, dp ) ) +
				texture( shadowMap, vec4( bd3D + ( tangent * sample3.x + bitangent * sample3.y ) * texelSize, dp ) ) +
				texture( shadowMap, vec4( bd3D + ( tangent * sample4.x + bitangent * sample4.y ) * texelSize, dp ) )
			) * 0.2;
		}
		return mix( 1.0, shadow, shadowIntensity );
	}
	#elif defined( SHADOWMAP_TYPE_BASIC )
	float getPointShadow( samplerCube shadowMap, vec2 shadowMapSize, float shadowIntensity, float shadowBias, float shadowRadius, vec4 shadowCoord, float shadowCameraNear, float shadowCameraFar ) {
		float shadow = 1.0;
		vec3 lightToPosition = shadowCoord.xyz;
		vec3 absVec = abs( lightToPosition );
		float viewSpaceZ = max( max( absVec.x, absVec.y ), absVec.z );
		if ( viewSpaceZ - shadowCameraFar <= 0.0 && viewSpaceZ - shadowCameraNear >= 0.0 ) {
			float dp = ( shadowCameraFar * ( viewSpaceZ - shadowCameraNear ) ) / ( viewSpaceZ * ( shadowCameraFar - shadowCameraNear ) );
			dp += shadowBias;
			vec3 bd3D = normalize( lightToPosition );
			float depth = textureCube( shadowMap, bd3D ).r;
			#ifdef USE_REVERSED_DEPTH_BUFFER
				depth = 1.0 - depth;
			#endif
			shadow = step( dp, depth );
		}
		return mix( 1.0, shadow, shadowIntensity );
	}
	#endif
	#endif
#endif`,t0=`#if NUM_SPOT_LIGHT_COORDS > 0
	uniform mat4 spotLightMatrix[ NUM_SPOT_LIGHT_COORDS ];
	varying vec4 vSpotLightCoord[ NUM_SPOT_LIGHT_COORDS ];
#endif
#ifdef USE_SHADOWMAP
	#if NUM_SUN_LIGHT_SHADOWS > 0
		varying vec4 vSunShadowWorldPosition;
		varying vec3 vSunShadowWorldNormal;
	#endif
	#if NUM_DIR_LIGHT_SHADOWS > 0
		uniform mat4 directionalShadowMatrix[ NUM_DIR_LIGHT_SHADOWS ];
		varying vec4 vDirectionalShadowCoord[ NUM_DIR_LIGHT_SHADOWS ];
		struct DirectionalLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
		};
		uniform DirectionalLightShadow directionalLightShadows[ NUM_DIR_LIGHT_SHADOWS ];
	#endif
	#if NUM_SPOT_LIGHT_SHADOWS > 0
		struct SpotLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
		};
		uniform SpotLightShadow spotLightShadows[ NUM_SPOT_LIGHT_SHADOWS ];
	#endif
	#if NUM_POINT_LIGHT_SHADOWS > 0
		uniform mat4 pointShadowMatrix[ NUM_POINT_LIGHT_SHADOWS ];
		varying vec4 vPointShadowCoord[ NUM_POINT_LIGHT_SHADOWS ];
		struct PointLightShadow {
			float shadowIntensity;
			float shadowBias;
			float shadowNormalBias;
			float shadowRadius;
			vec2 shadowMapSize;
			float shadowCameraNear;
			float shadowCameraFar;
		};
		uniform PointLightShadow pointLightShadows[ NUM_POINT_LIGHT_SHADOWS ];
	#endif
#endif`,e0=`#if ( defined( USE_SHADOWMAP ) && ( NUM_DIR_LIGHT_SHADOWS > 0 || NUM_SUN_LIGHT_SHADOWS > 0 || NUM_POINT_LIGHT_SHADOWS > 0 ) ) || ( NUM_SPOT_LIGHT_COORDS > 0 )
	#ifdef HAS_NORMAL
		vec3 shadowWorldNormal = transformNormalByInverseViewMatrix( transformedNormal, viewMatrix );
	#else
		vec3 shadowWorldNormal = vec3( 0.0 );
	#endif
	vec4 shadowWorldPosition;
#endif
#if defined( USE_SHADOWMAP )
	#if NUM_SUN_LIGHT_SHADOWS > 0
		vSunShadowWorldPosition = vec4( worldPosition.xyz, - mvPosition.z );
		vSunShadowWorldNormal = shadowWorldNormal;
	#endif
	#if NUM_DIR_LIGHT_SHADOWS > 0
		#pragma unroll_loop_start
		for ( int i = 0; i < NUM_DIR_LIGHT_SHADOWS; i ++ ) {
			shadowWorldPosition = worldPosition + vec4( shadowWorldNormal * directionalLightShadows[ i ].shadowNormalBias, 0 );
			vDirectionalShadowCoord[ i ] = directionalShadowMatrix[ i ] * shadowWorldPosition;
		}
		#pragma unroll_loop_end
	#endif
	#if NUM_POINT_LIGHT_SHADOWS > 0
		#pragma unroll_loop_start
		for ( int i = 0; i < NUM_POINT_LIGHT_SHADOWS; i ++ ) {
			shadowWorldPosition = worldPosition + vec4( shadowWorldNormal * pointLightShadows[ i ].shadowNormalBias, 0 );
			vPointShadowCoord[ i ] = pointShadowMatrix[ i ] * shadowWorldPosition;
		}
		#pragma unroll_loop_end
	#endif
#endif
#if NUM_SPOT_LIGHT_COORDS > 0
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_SPOT_LIGHT_COORDS; i ++ ) {
		shadowWorldPosition = worldPosition;
		#if ( defined( USE_SHADOWMAP ) && UNROLLED_LOOP_INDEX < NUM_SPOT_LIGHT_SHADOWS )
			shadowWorldPosition.xyz += shadowWorldNormal * spotLightShadows[ i ].shadowNormalBias;
		#endif
		vSpotLightCoord[ i ] = spotLightMatrix[ i ] * shadowWorldPosition;
	}
	#pragma unroll_loop_end
#endif`,n0=`float getShadowMask() {
	float shadow = 1.0;
	#ifdef USE_SHADOWMAP
	#if NUM_SUN_LIGHT_SHADOWS > 0
	SunLightShadow sunLight;
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_SUN_LIGHT_SHADOWS; i ++ ) {
		sunLight = sunLightShadows[ i ];
		shadow *= receiveShadow ? getSunShadow( sunShadowMap[ i ], sunLight, UNROLLED_LOOP_INDEX ) : 1.0;
	}
	#pragma unroll_loop_end
	#endif
	#if NUM_DIR_LIGHT_SHADOWS > 0
	DirectionalLightShadow directionalLight;
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_DIR_LIGHT_SHADOWS; i ++ ) {
		directionalLight = directionalLightShadows[ i ];
		shadow *= receiveShadow ? getShadow( directionalShadowMap[ i ], directionalLight.shadowMapSize, directionalLight.shadowIntensity, directionalLight.shadowBias, directionalLight.shadowRadius, vDirectionalShadowCoord[ i ] ) : 1.0;
	}
	#pragma unroll_loop_end
	#endif
	#if NUM_SPOT_LIGHT_SHADOWS > 0
	SpotLightShadow spotLight;
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_SPOT_LIGHT_SHADOWS; i ++ ) {
		spotLight = spotLightShadows[ i ];
		shadow *= receiveShadow ? getShadow( spotShadowMap[ i ], spotLight.shadowMapSize, spotLight.shadowIntensity, spotLight.shadowBias, spotLight.shadowRadius, vSpotLightCoord[ i ] ) : 1.0;
	}
	#pragma unroll_loop_end
	#endif
	#if NUM_POINT_LIGHT_SHADOWS > 0 && ( defined( SHADOWMAP_TYPE_PCF ) || defined( SHADOWMAP_TYPE_BASIC ) )
	PointLightShadow pointLight;
	#pragma unroll_loop_start
	for ( int i = 0; i < NUM_POINT_LIGHT_SHADOWS; i ++ ) {
		pointLight = pointLightShadows[ i ];
		shadow *= receiveShadow ? getPointShadow( pointShadowMap[ i ], pointLight.shadowMapSize, pointLight.shadowIntensity, pointLight.shadowBias, pointLight.shadowRadius, vPointShadowCoord[ i ], pointLight.shadowCameraNear, pointLight.shadowCameraFar ) : 1.0;
	}
	#pragma unroll_loop_end
	#endif
	#endif
	return shadow;
}`,i0=`#ifdef USE_SKINNING
	mat4 boneMatX = getBoneMatrix( skinIndex.x );
	mat4 boneMatY = getBoneMatrix( skinIndex.y );
	mat4 boneMatZ = getBoneMatrix( skinIndex.z );
	mat4 boneMatW = getBoneMatrix( skinIndex.w );
#endif`,s0=`#ifdef USE_SKINNING
	uniform mat4 bindMatrix;
	uniform mat4 bindMatrixInverse;
	uniform highp sampler2D boneTexture;
	mat4 getBoneMatrix( const in float i ) {
		int size = textureSize( boneTexture, 0 ).x;
		int j = int( i ) * 4;
		int x = j % size;
		int y = j / size;
		vec4 v1 = texelFetch( boneTexture, ivec2( x, y ), 0 );
		vec4 v2 = texelFetch( boneTexture, ivec2( x + 1, y ), 0 );
		vec4 v3 = texelFetch( boneTexture, ivec2( x + 2, y ), 0 );
		vec4 v4 = texelFetch( boneTexture, ivec2( x + 3, y ), 0 );
		return mat4( v1, v2, v3, v4 );
	}
#endif`,r0=`#ifdef USE_SKINNING
	vec4 skinVertex = bindMatrix * vec4( transformed, 1.0 );
	vec4 skinned = vec4( 0.0 );
	skinned += boneMatX * skinVertex * skinWeight.x;
	skinned += boneMatY * skinVertex * skinWeight.y;
	skinned += boneMatZ * skinVertex * skinWeight.z;
	skinned += boneMatW * skinVertex * skinWeight.w;
	transformed = ( bindMatrixInverse * skinned ).xyz;
#endif`,a0=`#ifdef USE_SKINNING
	mat4 skinMatrix = mat4( 0.0 );
	skinMatrix += skinWeight.x * boneMatX;
	skinMatrix += skinWeight.y * boneMatY;
	skinMatrix += skinWeight.z * boneMatZ;
	skinMatrix += skinWeight.w * boneMatW;
	skinMatrix = bindMatrixInverse * skinMatrix * bindMatrix;
	objectNormal = vec4( skinMatrix * vec4( objectNormal, 0.0 ) ).xyz;
	#ifdef USE_TANGENT
		objectTangent = vec4( skinMatrix * vec4( objectTangent, 0.0 ) ).xyz;
	#endif
#endif`,o0=`float specularStrength;
#ifdef USE_SPECULARMAP
	vec4 texelSpecular = texture2D( specularMap, vSpecularMapUv );
	specularStrength = texelSpecular.r;
#else
	specularStrength = 1.0;
#endif`,l0=`#ifdef USE_SPECULARMAP
	uniform sampler2D specularMap;
#endif`,c0=`#if defined( TONE_MAPPING )
	gl_FragColor.rgb = toneMapping( gl_FragColor.rgb );
#endif`,h0=`#ifndef saturate
#define saturate( a ) clamp( a, 0.0, 1.0 )
#endif
uniform float toneMappingExposure;
vec3 LinearToneMapping( vec3 color ) {
	return saturate( toneMappingExposure * color );
}
vec3 ReinhardToneMapping( vec3 color ) {
	color *= toneMappingExposure;
	return saturate( color / ( vec3( 1.0 ) + color ) );
}
vec3 CineonToneMapping( vec3 color ) {
	color *= toneMappingExposure;
	color = max( vec3( 0.0 ), color - 0.004 );
	return pow( ( color * ( 6.2 * color + 0.5 ) ) / ( color * ( 6.2 * color + 1.7 ) + 0.06 ), vec3( 2.2 ) );
}
vec3 RRTAndODTFit( vec3 v ) {
	vec3 a = v * ( v + 0.0245786 ) - 0.000090537;
	vec3 b = v * ( 0.983729 * v + 0.4329510 ) + 0.238081;
	return a / b;
}
vec3 ACESFilmicToneMapping( vec3 color ) {
	const mat3 ACESInputMat = mat3(
		vec3( 0.59719, 0.07600, 0.02840 ),		vec3( 0.35458, 0.90834, 0.13383 ),
		vec3( 0.04823, 0.01566, 0.83777 )
	);
	const mat3 ACESOutputMat = mat3(
		vec3(  1.60475, -0.10208, -0.00327 ),		vec3( -0.53108,  1.10813, -0.07276 ),
		vec3( -0.07367, -0.00605,  1.07602 )
	);
	color *= toneMappingExposure / 0.6;
	color = ACESInputMat * color;
	color = RRTAndODTFit( color );
	color = ACESOutputMat * color;
	return saturate( color );
}
const mat3 LINEAR_REC2020_TO_LINEAR_SRGB = mat3(
	vec3( 1.6605, - 0.1246, - 0.0182 ),
	vec3( - 0.5876, 1.1329, - 0.1006 ),
	vec3( - 0.0728, - 0.0083, 1.1187 )
);
const mat3 LINEAR_SRGB_TO_LINEAR_REC2020 = mat3(
	vec3( 0.6274, 0.0691, 0.0164 ),
	vec3( 0.3293, 0.9195, 0.0880 ),
	vec3( 0.0433, 0.0113, 0.8956 )
);
vec3 agxDefaultContrastApprox( vec3 x ) {
	vec3 x2 = x * x;
	vec3 x4 = x2 * x2;
	return + 15.5 * x4 * x2
		- 40.14 * x4 * x
		+ 31.96 * x4
		- 6.868 * x2 * x
		+ 0.4298 * x2
		+ 0.1191 * x
		- 0.00232;
}
vec3 AgXToneMapping( vec3 color ) {
	const mat3 AgXInsetMatrix = mat3(
		vec3( 0.856627153315983, 0.137318972929847, 0.11189821299995 ),
		vec3( 0.0951212405381588, 0.761241990602591, 0.0767994186031903 ),
		vec3( 0.0482516061458583, 0.101439036467562, 0.811302368396859 )
	);
	const mat3 AgXOutsetMatrix = mat3(
		vec3( 1.1271005818144368, - 0.1413297634984383, - 0.14132976349843826 ),
		vec3( - 0.11060664309660323, 1.157823702216272, - 0.11060664309660294 ),
		vec3( - 0.016493938717834573, - 0.016493938717834257, 1.2519364065950405 )
	);
	const float AgxMinEv = - 12.47393;	const float AgxMaxEv = 4.026069;
	color *= toneMappingExposure;
	color = LINEAR_SRGB_TO_LINEAR_REC2020 * color;
	color = AgXInsetMatrix * color;
	color = max( color, 1e-10 );	color = log2( color );
	color = ( color - AgxMinEv ) / ( AgxMaxEv - AgxMinEv );
	color = clamp( color, 0.0, 1.0 );
	color = agxDefaultContrastApprox( color );
	color = AgXOutsetMatrix * color;
	color = pow( max( vec3( 0.0 ), color ), vec3( 2.2 ) );
	color = LINEAR_REC2020_TO_LINEAR_SRGB * color;
	color = clamp( color, 0.0, 1.0 );
	return color;
}
vec3 NeutralToneMapping( vec3 color ) {
	const float StartCompression = 0.8 - 0.04;
	const float Desaturation = 0.15;
	color *= toneMappingExposure;
	float x = min( color.r, min( color.g, color.b ) );
	float offset = x < 0.08 ? x - 6.25 * x * x : 0.04;
	color -= offset;
	float peak = max( color.r, max( color.g, color.b ) );
	if ( peak < StartCompression ) return color;
	float d = 1. - StartCompression;
	float newPeak = 1. - d * d / ( peak + d - StartCompression );
	color *= newPeak / peak;
	float g = 1. - 1. / ( Desaturation * ( peak - newPeak ) + 1. );
	return mix( color, vec3( newPeak ), g );
}
vec3 CustomToneMapping( vec3 color ) { return color; }`,u0=`#ifdef USE_TRANSMISSION
	material.transmission = transmission;
	material.transmissionAlpha = 1.0;
	material.thickness = thickness;
	material.attenuationDistance = attenuationDistance;
	material.attenuationColor = attenuationColor;
	#ifdef USE_TRANSMISSIONMAP
		material.transmission *= texture2D( transmissionMap, vTransmissionMapUv ).r;
	#endif
	#ifdef USE_THICKNESSMAP
		material.thickness *= texture2D( thicknessMap, vThicknessMapUv ).g;
	#endif
	vec3 pos = vWorldPosition;
	vec3 v = normalize( cameraPosition - pos );
	vec3 n = transformNormalByInverseViewMatrix( normal, viewMatrix );
	vec4 transmitted = getIBLVolumeRefraction(
		n, v, material.roughness, material.diffuseContribution, material.specularColorBlended, material.specularF90,
		pos, modelMatrix, viewMatrix, projectionMatrix, material.dispersion, material.ior, material.thickness,
		material.attenuationColor, material.attenuationDistance );
	material.transmissionAlpha = mix( material.transmissionAlpha, transmitted.a, material.transmission );
	totalDiffuse = mix( totalDiffuse, transmitted.rgb, material.transmission );
#endif`,d0=`#ifdef USE_TRANSMISSION
	uniform float transmission;
	uniform float thickness;
	uniform float attenuationDistance;
	uniform vec3 attenuationColor;
	#ifdef USE_TRANSMISSIONMAP
		uniform sampler2D transmissionMap;
	#endif
	#ifdef USE_THICKNESSMAP
		uniform sampler2D thicknessMap;
	#endif
	uniform vec2 transmissionSamplerSize;
	uniform sampler2D transmissionSamplerMap;
	uniform mat4 modelMatrix;
	uniform mat4 projectionMatrix;
	varying vec3 vWorldPosition;
	float w0( float a ) {
		return ( 1.0 / 6.0 ) * ( a * ( a * ( - a + 3.0 ) - 3.0 ) + 1.0 );
	}
	float w1( float a ) {
		return ( 1.0 / 6.0 ) * ( a *  a * ( 3.0 * a - 6.0 ) + 4.0 );
	}
	float w2( float a ){
		return ( 1.0 / 6.0 ) * ( a * ( a * ( - 3.0 * a + 3.0 ) + 3.0 ) + 1.0 );
	}
	float w3( float a ) {
		return ( 1.0 / 6.0 ) * ( a * a * a );
	}
	float g0( float a ) {
		return w0( a ) + w1( a );
	}
	float g1( float a ) {
		return w2( a ) + w3( a );
	}
	float h0( float a ) {
		return - 1.0 + w1( a ) / ( w0( a ) + w1( a ) );
	}
	float h1( float a ) {
		return 1.0 + w3( a ) / ( w2( a ) + w3( a ) );
	}
	vec4 bicubic( sampler2D tex, vec2 uv, vec4 texelSize, float lod ) {
		uv = uv * texelSize.zw + 0.5;
		vec2 iuv = floor( uv );
		vec2 fuv = fract( uv );
		float g0x = g0( fuv.x );
		float g1x = g1( fuv.x );
		float h0x = h0( fuv.x );
		float h1x = h1( fuv.x );
		float h0y = h0( fuv.y );
		float h1y = h1( fuv.y );
		vec2 p0 = ( vec2( iuv.x + h0x, iuv.y + h0y ) - 0.5 ) * texelSize.xy;
		vec2 p1 = ( vec2( iuv.x + h1x, iuv.y + h0y ) - 0.5 ) * texelSize.xy;
		vec2 p2 = ( vec2( iuv.x + h0x, iuv.y + h1y ) - 0.5 ) * texelSize.xy;
		vec2 p3 = ( vec2( iuv.x + h1x, iuv.y + h1y ) - 0.5 ) * texelSize.xy;
		return g0( fuv.y ) * ( g0x * textureLod( tex, p0, lod ) + g1x * textureLod( tex, p1, lod ) ) +
			g1( fuv.y ) * ( g0x * textureLod( tex, p2, lod ) + g1x * textureLod( tex, p3, lod ) );
	}
	vec4 textureBicubic( sampler2D sampler, vec2 uv, float lod ) {
		vec2 fLodSize = vec2( textureSize( sampler, int( lod ) ) );
		vec2 cLodSize = vec2( textureSize( sampler, int( lod + 1.0 ) ) );
		vec2 fLodSizeInv = 1.0 / fLodSize;
		vec2 cLodSizeInv = 1.0 / cLodSize;
		vec4 fSample = bicubic( sampler, uv, vec4( fLodSizeInv, fLodSize ), floor( lod ) );
		vec4 cSample = bicubic( sampler, uv, vec4( cLodSizeInv, cLodSize ), ceil( lod ) );
		return mix( fSample, cSample, fract( lod ) );
	}
	vec3 getVolumeTransmissionRay( const in vec3 n, const in vec3 v, const in float thickness, const in float ior, const in mat4 modelMatrix ) {
		vec3 refractionVector = refract( - v, normalize( n ), 1.0 / ior );
		vec3 modelScale;
		modelScale.x = length( vec3( modelMatrix[ 0 ].xyz ) );
		modelScale.y = length( vec3( modelMatrix[ 1 ].xyz ) );
		modelScale.z = length( vec3( modelMatrix[ 2 ].xyz ) );
		return normalize( refractionVector ) * thickness * modelScale;
	}
	float applyIorToRoughness( const in float roughness, const in float ior ) {
		return roughness * clamp( ior * 2.0 - 2.0, 0.0, 1.0 );
	}
	vec4 getTransmissionSample( const in vec2 fragCoord, const in float roughness, const in float ior ) {
		float lod = log2( transmissionSamplerSize.x ) * applyIorToRoughness( roughness, ior );
		return textureBicubic( transmissionSamplerMap, fragCoord.xy, lod );
	}
	vec3 volumeAttenuation( const in float transmissionDistance, const in vec3 attenuationColor, const in float attenuationDistance ) {
		if ( isinf( attenuationDistance ) ) {
			return vec3( 1.0 );
		} else {
			vec3 attenuationCoefficient = -log( attenuationColor ) / attenuationDistance;
			vec3 transmittance = exp( - attenuationCoefficient * transmissionDistance );			return transmittance;
		}
	}
	vec4 getIBLVolumeRefraction( const in vec3 n, const in vec3 v, const in float roughness, const in vec3 diffuseColor,
		const in vec3 specularColor, const in float specularF90, const in vec3 position, const in mat4 modelMatrix,
		const in mat4 viewMatrix, const in mat4 projMatrix, const in float dispersion, const in float ior, const in float thickness,
		const in vec3 attenuationColor, const in float attenuationDistance ) {
		vec4 transmittedLight;
		vec3 transmittance;
		#ifdef USE_DISPERSION
			float halfSpread = ( ior - 1.0 ) * 0.025 * dispersion;
			vec3 iors = vec3( ior - halfSpread, ior, ior + halfSpread );
			for ( int i = 0; i < 3; i ++ ) {
				vec3 transmissionRay = getVolumeTransmissionRay( n, v, thickness, iors[ i ], modelMatrix );
				vec3 refractedRayExit = position + transmissionRay;
				vec4 ndcPos = projMatrix * viewMatrix * vec4( refractedRayExit, 1.0 );
				vec2 refractionCoords = ndcPos.xy / ndcPos.w;
				refractionCoords += 1.0;
				refractionCoords /= 2.0;
				vec4 transmissionSample = getTransmissionSample( refractionCoords, roughness, iors[ i ] );
				transmittedLight[ i ] = transmissionSample[ i ];
				transmittedLight.a += transmissionSample.a;
				transmittance[ i ] = diffuseColor[ i ] * volumeAttenuation( length( transmissionRay ), attenuationColor, attenuationDistance )[ i ];
			}
			transmittedLight.a /= 3.0;
		#else
			vec3 transmissionRay = getVolumeTransmissionRay( n, v, thickness, ior, modelMatrix );
			vec3 refractedRayExit = position + transmissionRay;
			vec4 ndcPos = projMatrix * viewMatrix * vec4( refractedRayExit, 1.0 );
			vec2 refractionCoords = ndcPos.xy / ndcPos.w;
			refractionCoords += 1.0;
			refractionCoords /= 2.0;
			transmittedLight = getTransmissionSample( refractionCoords, roughness, ior );
			transmittance = diffuseColor * volumeAttenuation( length( transmissionRay ), attenuationColor, attenuationDistance );
		#endif
		vec3 attenuatedColor = transmittance * transmittedLight.rgb;
		vec3 F = EnvironmentBRDF( n, v, specularColor, specularF90, roughness );
		float transmittanceFactor = ( transmittance.r + transmittance.g + transmittance.b ) / 3.0;
		return vec4( ( 1.0 - F ) * attenuatedColor, 1.0 - ( 1.0 - transmittedLight.a ) * transmittanceFactor );
	}
#endif`,f0=`#if defined( USE_UV ) || defined( USE_ANISOTROPY )
	varying vec2 vUv;
#endif
#ifdef USE_MAP
	varying vec2 vMapUv;
#endif
#ifdef USE_ALPHAMAP
	varying vec2 vAlphaMapUv;
#endif
#ifdef USE_LIGHTMAP
	varying vec2 vLightMapUv;
#endif
#ifdef USE_AOMAP
	varying vec2 vAoMapUv;
#endif
#ifdef USE_BUMPMAP
	varying vec2 vBumpMapUv;
#endif
#ifdef USE_NORMALMAP
	varying vec2 vNormalMapUv;
#endif
#ifdef USE_EMISSIVEMAP
	varying vec2 vEmissiveMapUv;
#endif
#ifdef USE_METALNESSMAP
	varying vec2 vMetalnessMapUv;
#endif
#ifdef USE_ROUGHNESSMAP
	varying vec2 vRoughnessMapUv;
#endif
#ifdef USE_ANISOTROPYMAP
	varying vec2 vAnisotropyMapUv;
#endif
#ifdef USE_CLEARCOATMAP
	varying vec2 vClearcoatMapUv;
#endif
#ifdef USE_CLEARCOAT_NORMALMAP
	varying vec2 vClearcoatNormalMapUv;
#endif
#ifdef USE_CLEARCOAT_ROUGHNESSMAP
	varying vec2 vClearcoatRoughnessMapUv;
#endif
#ifdef USE_IRIDESCENCEMAP
	varying vec2 vIridescenceMapUv;
#endif
#ifdef USE_IRIDESCENCE_THICKNESSMAP
	varying vec2 vIridescenceThicknessMapUv;
#endif
#ifdef USE_SHEEN_COLORMAP
	varying vec2 vSheenColorMapUv;
#endif
#ifdef USE_SHEEN_ROUGHNESSMAP
	varying vec2 vSheenRoughnessMapUv;
#endif
#ifdef USE_SPECULARMAP
	varying vec2 vSpecularMapUv;
#endif
#ifdef USE_SPECULAR_COLORMAP
	varying vec2 vSpecularColorMapUv;
#endif
#ifdef USE_SPECULAR_INTENSITYMAP
	varying vec2 vSpecularIntensityMapUv;
#endif
#ifdef USE_TRANSMISSIONMAP
	uniform mat3 transmissionMapTransform;
	varying vec2 vTransmissionMapUv;
#endif
#ifdef USE_THICKNESSMAP
	uniform mat3 thicknessMapTransform;
	varying vec2 vThicknessMapUv;
#endif`,p0=`#if defined( USE_UV ) || defined( USE_ANISOTROPY )
	varying vec2 vUv;
#endif
#ifdef USE_MAP
	uniform mat3 mapTransform;
	varying vec2 vMapUv;
#endif
#ifdef USE_ALPHAMAP
	uniform mat3 alphaMapTransform;
	varying vec2 vAlphaMapUv;
#endif
#ifdef USE_LIGHTMAP
	uniform mat3 lightMapTransform;
	varying vec2 vLightMapUv;
#endif
#ifdef USE_AOMAP
	uniform mat3 aoMapTransform;
	varying vec2 vAoMapUv;
#endif
#ifdef USE_BUMPMAP
	uniform mat3 bumpMapTransform;
	varying vec2 vBumpMapUv;
#endif
#ifdef USE_NORMALMAP
	uniform mat3 normalMapTransform;
	varying vec2 vNormalMapUv;
#endif
#ifdef USE_DISPLACEMENTMAP
	uniform mat3 displacementMapTransform;
	varying vec2 vDisplacementMapUv;
#endif
#ifdef USE_EMISSIVEMAP
	uniform mat3 emissiveMapTransform;
	varying vec2 vEmissiveMapUv;
#endif
#ifdef USE_METALNESSMAP
	uniform mat3 metalnessMapTransform;
	varying vec2 vMetalnessMapUv;
#endif
#ifdef USE_ROUGHNESSMAP
	uniform mat3 roughnessMapTransform;
	varying vec2 vRoughnessMapUv;
#endif
#ifdef USE_ANISOTROPYMAP
	uniform mat3 anisotropyMapTransform;
	varying vec2 vAnisotropyMapUv;
#endif
#ifdef USE_CLEARCOATMAP
	uniform mat3 clearcoatMapTransform;
	varying vec2 vClearcoatMapUv;
#endif
#ifdef USE_CLEARCOAT_NORMALMAP
	uniform mat3 clearcoatNormalMapTransform;
	varying vec2 vClearcoatNormalMapUv;
#endif
#ifdef USE_CLEARCOAT_ROUGHNESSMAP
	uniform mat3 clearcoatRoughnessMapTransform;
	varying vec2 vClearcoatRoughnessMapUv;
#endif
#ifdef USE_SHEEN_COLORMAP
	uniform mat3 sheenColorMapTransform;
	varying vec2 vSheenColorMapUv;
#endif
#ifdef USE_SHEEN_ROUGHNESSMAP
	uniform mat3 sheenRoughnessMapTransform;
	varying vec2 vSheenRoughnessMapUv;
#endif
#ifdef USE_IRIDESCENCEMAP
	uniform mat3 iridescenceMapTransform;
	varying vec2 vIridescenceMapUv;
#endif
#ifdef USE_IRIDESCENCE_THICKNESSMAP
	uniform mat3 iridescenceThicknessMapTransform;
	varying vec2 vIridescenceThicknessMapUv;
#endif
#ifdef USE_SPECULARMAP
	uniform mat3 specularMapTransform;
	varying vec2 vSpecularMapUv;
#endif
#ifdef USE_SPECULAR_COLORMAP
	uniform mat3 specularColorMapTransform;
	varying vec2 vSpecularColorMapUv;
#endif
#ifdef USE_SPECULAR_INTENSITYMAP
	uniform mat3 specularIntensityMapTransform;
	varying vec2 vSpecularIntensityMapUv;
#endif
#ifdef USE_TRANSMISSIONMAP
	uniform mat3 transmissionMapTransform;
	varying vec2 vTransmissionMapUv;
#endif
#ifdef USE_THICKNESSMAP
	uniform mat3 thicknessMapTransform;
	varying vec2 vThicknessMapUv;
#endif`,m0=`#if defined( USE_UV ) || defined( USE_ANISOTROPY )
	vUv = vec3( uv, 1 ).xy;
#endif
#ifdef USE_MAP
	vMapUv = ( mapTransform * vec3( MAP_UV, 1 ) ).xy;
#endif
#ifdef USE_ALPHAMAP
	vAlphaMapUv = ( alphaMapTransform * vec3( ALPHAMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_LIGHTMAP
	vLightMapUv = ( lightMapTransform * vec3( LIGHTMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_AOMAP
	vAoMapUv = ( aoMapTransform * vec3( AOMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_BUMPMAP
	vBumpMapUv = ( bumpMapTransform * vec3( BUMPMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_NORMALMAP
	vNormalMapUv = ( normalMapTransform * vec3( NORMALMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_DISPLACEMENTMAP
	vDisplacementMapUv = ( displacementMapTransform * vec3( DISPLACEMENTMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_EMISSIVEMAP
	vEmissiveMapUv = ( emissiveMapTransform * vec3( EMISSIVEMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_METALNESSMAP
	vMetalnessMapUv = ( metalnessMapTransform * vec3( METALNESSMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_ROUGHNESSMAP
	vRoughnessMapUv = ( roughnessMapTransform * vec3( ROUGHNESSMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_ANISOTROPYMAP
	vAnisotropyMapUv = ( anisotropyMapTransform * vec3( ANISOTROPYMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_CLEARCOATMAP
	vClearcoatMapUv = ( clearcoatMapTransform * vec3( CLEARCOATMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_CLEARCOAT_NORMALMAP
	vClearcoatNormalMapUv = ( clearcoatNormalMapTransform * vec3( CLEARCOAT_NORMALMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_CLEARCOAT_ROUGHNESSMAP
	vClearcoatRoughnessMapUv = ( clearcoatRoughnessMapTransform * vec3( CLEARCOAT_ROUGHNESSMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_IRIDESCENCEMAP
	vIridescenceMapUv = ( iridescenceMapTransform * vec3( IRIDESCENCEMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_IRIDESCENCE_THICKNESSMAP
	vIridescenceThicknessMapUv = ( iridescenceThicknessMapTransform * vec3( IRIDESCENCE_THICKNESSMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_SHEEN_COLORMAP
	vSheenColorMapUv = ( sheenColorMapTransform * vec3( SHEEN_COLORMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_SHEEN_ROUGHNESSMAP
	vSheenRoughnessMapUv = ( sheenRoughnessMapTransform * vec3( SHEEN_ROUGHNESSMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_SPECULARMAP
	vSpecularMapUv = ( specularMapTransform * vec3( SPECULARMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_SPECULAR_COLORMAP
	vSpecularColorMapUv = ( specularColorMapTransform * vec3( SPECULAR_COLORMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_SPECULAR_INTENSITYMAP
	vSpecularIntensityMapUv = ( specularIntensityMapTransform * vec3( SPECULAR_INTENSITYMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_TRANSMISSIONMAP
	vTransmissionMapUv = ( transmissionMapTransform * vec3( TRANSMISSIONMAP_UV, 1 ) ).xy;
#endif
#ifdef USE_THICKNESSMAP
	vThicknessMapUv = ( thicknessMapTransform * vec3( THICKNESSMAP_UV, 1 ) ).xy;
#endif`,g0=`#if defined( USE_ENVMAP ) || defined( DISTANCE ) || defined ( USE_SHADOWMAP ) || defined ( USE_TRANSMISSION ) || NUM_SPOT_LIGHT_COORDS > 0
	vec4 worldPosition = vec4( transformed, 1.0 );
	#ifdef USE_BATCHING
		worldPosition = batchingMatrix * worldPosition;
	#endif
	#ifdef USE_INSTANCING
		worldPosition = instanceMatrix * worldPosition;
	#endif
	worldPosition = modelMatrix * worldPosition;
#endif`,x0=`varying vec2 vUv;
uniform mat3 uvTransform;
void main() {
	vUv = ( uvTransform * vec3( uv, 1 ) ).xy;
	gl_Position = vec4( position.xy, 1.0, 1.0 );
}`,_0=`uniform sampler2D t2D;
uniform float backgroundIntensity;
varying vec2 vUv;
void main() {
	vec4 texColor = texture2D( t2D, vUv );
	#ifdef DECODE_VIDEO_TEXTURE
		texColor = vec4( mix( pow( texColor.rgb * 0.9478672986 + vec3( 0.0521327014 ), vec3( 2.4 ) ), texColor.rgb * 0.0773993808, vec3( lessThanEqual( texColor.rgb, vec3( 0.04045 ) ) ) ), texColor.w );
	#endif
	texColor.rgb *= backgroundIntensity;
	gl_FragColor = texColor;
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
}`,y0=`varying vec3 vWorldDirection;
#include <common>
void main() {
	vWorldDirection = transformDirection( position, modelMatrix );
	#include <begin_vertex>
	#include <project_vertex>
	gl_Position.z = gl_Position.w;
}`,v0=`#ifdef ENVMAP_TYPE_CUBE
	uniform samplerCube envMap;
#elif defined( ENVMAP_TYPE_CUBE_UV )
	uniform sampler2D envMap;
#endif
uniform float backgroundBlurriness;
uniform float backgroundIntensity;
uniform mat3 backgroundRotation;
varying vec3 vWorldDirection;
#include <cube_uv_reflection_fragment>
void main() {
	#ifdef ENVMAP_TYPE_CUBE
		vec4 texColor = textureCube( envMap, backgroundRotation * vWorldDirection );
	#elif defined( ENVMAP_TYPE_CUBE_UV )
		vec4 texColor = textureCubeUV( envMap, backgroundRotation * vWorldDirection, backgroundBlurriness );
	#else
		vec4 texColor = vec4( 0.0, 0.0, 0.0, 1.0 );
	#endif
	texColor.rgb *= backgroundIntensity;
	gl_FragColor = texColor;
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
}`,M0=`varying vec3 vWorldDirection;
#include <common>
void main() {
	vWorldDirection = transformDirection( position, modelMatrix );
	#include <begin_vertex>
	#include <project_vertex>
	gl_Position.z = gl_Position.w;
}`,S0=`uniform samplerCube tCube;
uniform float tFlip;
uniform float opacity;
varying vec3 vWorldDirection;
void main() {
	vec4 texColor = textureCube( tCube, vec3( tFlip * vWorldDirection.x, vWorldDirection.yz ) );
	gl_FragColor = texColor;
	gl_FragColor.a *= opacity;
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
}`,b0=`#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
varying vec2 vHighPrecisionZW;
void main() {
	#include <uv_vertex>
	#include <batching_vertex>
	#include <skinbase_vertex>
	#include <morphinstance_vertex>
	#ifdef USE_DISPLACEMENTMAP
		#include <beginnormal_vertex>
		#include <morphnormal_vertex>
		#include <skinnormal_vertex>
	#endif
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	vHighPrecisionZW = gl_Position.zw;
}`,E0=`#if DEPTH_PACKING == 3200
	uniform float opacity;
#endif
#include <common>
#include <packing>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
varying vec2 vHighPrecisionZW;
void main() {
	vec4 diffuseColor = vec4( 1.0 );
	#include <clipping_planes_fragment>
	#if DEPTH_PACKING == 3200
		diffuseColor.a = opacity;
	#endif
	#include <map_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <logdepthbuf_fragment>
	#ifdef USE_REVERSED_DEPTH_BUFFER
		float fragCoordZ = vHighPrecisionZW[ 0 ] / vHighPrecisionZW[ 1 ];
	#else
		float fragCoordZ = 0.5 * vHighPrecisionZW[ 0 ] / vHighPrecisionZW[ 1 ] + 0.5;
	#endif
	#if DEPTH_PACKING == 3200
		gl_FragColor = vec4( vec3( 1.0 - fragCoordZ ), opacity );
	#elif DEPTH_PACKING == 3201
		gl_FragColor = packDepthToRGBA( fragCoordZ );
	#elif DEPTH_PACKING == 3202
		gl_FragColor = vec4( packDepthToRGB( fragCoordZ ), 1.0 );
	#elif DEPTH_PACKING == 3203
		gl_FragColor = vec4( packDepthToRG( fragCoordZ ), 0.0, 1.0 );
	#endif
}`,w0=`#define DISTANCE
varying vec3 vWorldPosition;
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <batching_vertex>
	#include <skinbase_vertex>
	#include <morphinstance_vertex>
	#ifdef USE_DISPLACEMENTMAP
		#include <beginnormal_vertex>
		#include <morphnormal_vertex>
		#include <skinnormal_vertex>
	#endif
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <worldpos_vertex>
	#include <clipping_planes_vertex>
	vWorldPosition = worldPosition.xyz;
}`,T0=`#define DISTANCE
uniform vec3 referencePosition;
uniform float nearDistance;
uniform float farDistance;
varying vec3 vWorldPosition;
#include <common>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( 1.0 );
	#include <clipping_planes_fragment>
	#include <map_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	float dist = length( vWorldPosition - referencePosition );
	dist = ( dist - nearDistance ) / ( farDistance - nearDistance );
	dist = saturate( dist );
	gl_FragColor = vec4( dist, 0.0, 0.0, 1.0 );
}`,A0=`varying vec3 vWorldDirection;
#include <common>
void main() {
	vWorldDirection = transformDirection( position, modelMatrix );
	#include <begin_vertex>
	#include <project_vertex>
}`,R0=`uniform sampler2D tEquirect;
varying vec3 vWorldDirection;
#include <common>
void main() {
	vec3 direction = normalize( vWorldDirection );
	vec2 sampleUV = equirectUv( direction );
	gl_FragColor = texture2D( tEquirect, sampleUV );
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
}`,C0=`uniform float scale;
attribute float lineDistance;
varying float vLineDistance;
#include <common>
#include <uv_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <morphtarget_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	vLineDistance = scale * lineDistance;
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	#include <fog_vertex>
}`,P0=`uniform vec3 diffuse;
uniform float opacity;
uniform float dashSize;
uniform float totalSize;
varying float vLineDistance;
#include <common>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <fog_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	if ( mod( vLineDistance, totalSize ) > dashSize ) {
		discard;
	}
	vec3 outgoingLight = vec3( 0.0 );
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	outgoingLight = diffuseColor.rgb;
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
}`,I0=`#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <envmap_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <batching_vertex>
	#if defined ( USE_ENVMAP ) || defined ( USE_SKINNING )
		#include <beginnormal_vertex>
		#include <morphnormal_vertex>
		#include <skinbase_vertex>
		#include <skinnormal_vertex>
		#include <defaultnormal_vertex>
	#endif
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	#include <worldpos_vertex>
	#include <envmap_vertex>
	#include <fog_vertex>
}`,L0=`uniform vec3 diffuse;
uniform float opacity;
#ifndef FLAT_SHADED
	varying vec3 vNormal;
#endif
#include <common>
#include <dithering_pars_fragment>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <aomap_pars_fragment>
#include <lightmap_pars_fragment>
#include <envmap_common_pars_fragment>
#include <envmap_pars_fragment>
#include <fog_pars_fragment>
#include <specularmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <specularmap_fragment>
	ReflectedLight reflectedLight = ReflectedLight( vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ) );
	#ifdef USE_LIGHTMAP
		vec4 lightMapTexel = texture2D( lightMap, vLightMapUv );
		reflectedLight.indirectDiffuse += lightMapTexel.rgb * lightMapIntensity * RECIPROCAL_PI;
	#else
		reflectedLight.indirectDiffuse += vec3( 1.0 );
	#endif
	#include <aomap_fragment>
	reflectedLight.indirectDiffuse *= diffuseColor.rgb;
	vec3 outgoingLight = reflectedLight.indirectDiffuse;
	#include <envmap_fragment>
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
	#include <dithering_fragment>
}`,D0=`#define LAMBERT
varying vec3 vViewPosition;
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <envmap_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <normal_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <shadowmap_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <normal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	vViewPosition = - mvPosition.xyz;
	#include <worldpos_vertex>
	#include <envmap_vertex>
	#include <shadowmap_vertex>
	#include <fog_vertex>
}`,U0=`#define LAMBERT
uniform vec3 diffuse;
uniform vec3 emissive;
uniform float opacity;
#include <common>
#include <dithering_pars_fragment>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <aomap_pars_fragment>
#include <lightmap_pars_fragment>
#include <emissivemap_pars_fragment>
#include <cube_uv_reflection_fragment>
#include <envmap_common_pars_fragment>
#include <envmap_pars_fragment>
#include <envmap_physical_pars_fragment>
#include <fog_pars_fragment>
#include <bsdfs>
#include <lights_pars_begin>
#include <normal_pars_fragment>
#include <lights_lambert_pars_fragment>
#include <shadowmap_pars_fragment>
#include <bumpmap_pars_fragment>
#include <normalmap_pars_fragment>
#include <specularmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	ReflectedLight reflectedLight = ReflectedLight( vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ) );
	vec3 totalEmissiveRadiance = emissive;
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <specularmap_fragment>
	#include <normal_fragment_begin>
	#include <normal_fragment_maps>
	#include <emissivemap_fragment>
	#include <lights_lambert_fragment>
	#include <lights_fragment_begin>
	#include <lights_fragment_maps>
	#include <lights_fragment_end>
	#include <aomap_fragment>
	vec3 outgoingLight = reflectedLight.directDiffuse + reflectedLight.indirectDiffuse + totalEmissiveRadiance;
	#include <envmap_fragment>
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
	#include <dithering_fragment>
}`,N0=`#define MATCAP
varying vec3 vViewPosition;
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <color_pars_vertex>
#include <displacementmap_pars_vertex>
#include <fog_pars_vertex>
#include <normal_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <normal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	#include <fog_vertex>
	vViewPosition = - mvPosition.xyz;
}`,F0=`#define MATCAP
uniform vec3 diffuse;
uniform float opacity;
uniform sampler2D matcap;
varying vec3 vViewPosition;
#include <common>
#include <dithering_pars_fragment>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <fog_pars_fragment>
#include <normal_pars_fragment>
#include <bumpmap_pars_fragment>
#include <normalmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <normal_fragment_begin>
	#include <normal_fragment_maps>
	vec3 viewDir = normalize( vViewPosition );
	vec3 x = normalize( vec3( viewDir.z, 0.0, - viewDir.x ) );
	vec3 y = cross( viewDir, x );
	vec2 uv = vec2( dot( x, normal ), dot( y, normal ) ) * 0.495 + 0.5;
	#ifdef USE_MATCAP
		vec4 matcapColor = texture2D( matcap, uv );
	#else
		vec4 matcapColor = vec4( vec3( mix( 0.2, 0.8, uv.y ) ), 1.0 );
	#endif
	vec3 outgoingLight = diffuseColor.rgb * matcapColor.rgb;
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
	#include <dithering_fragment>
}`,B0=`#define NORMAL
#if defined( FLAT_SHADED ) || defined( USE_BUMPMAP ) || defined( USE_NORMALMAP_TANGENTSPACE )
	varying vec3 vViewPosition;
#endif
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <normal_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphinstance_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <normal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
#if defined( FLAT_SHADED ) || defined( USE_BUMPMAP ) || defined( USE_NORMALMAP_TANGENTSPACE )
	vViewPosition = - mvPosition.xyz;
#endif
}`,O0=`#define NORMAL
uniform float opacity;
#if defined( FLAT_SHADED ) || defined( USE_BUMPMAP ) || defined( USE_NORMALMAP_TANGENTSPACE )
	varying vec3 vViewPosition;
#endif
#include <uv_pars_fragment>
#include <normal_pars_fragment>
#include <bumpmap_pars_fragment>
#include <normalmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( 0.0, 0.0, 0.0, opacity );
	#include <clipping_planes_fragment>
	#include <logdepthbuf_fragment>
	#include <normal_fragment_begin>
	#include <normal_fragment_maps>
	gl_FragColor = vec4( normalize( normal ) * 0.5 + 0.5, diffuseColor.a );
	#ifdef OPAQUE
		gl_FragColor.a = 1.0;
	#endif
}`,k0=`#define PHONG
varying vec3 vViewPosition;
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <envmap_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <normal_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <shadowmap_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphcolor_vertex>
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphinstance_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <normal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	vViewPosition = - mvPosition.xyz;
	#include <worldpos_vertex>
	#include <envmap_vertex>
	#include <shadowmap_vertex>
	#include <fog_vertex>
}`,z0=`#define PHONG
uniform vec3 diffuse;
uniform vec3 emissive;
uniform vec3 specular;
uniform float shininess;
uniform float opacity;
#include <common>
#include <dithering_pars_fragment>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <aomap_pars_fragment>
#include <lightmap_pars_fragment>
#include <emissivemap_pars_fragment>
#include <cube_uv_reflection_fragment>
#include <envmap_common_pars_fragment>
#include <envmap_pars_fragment>
#include <envmap_physical_pars_fragment>
#include <fog_pars_fragment>
#include <bsdfs>
#include <lights_pars_begin>
#include <normal_pars_fragment>
#include <lights_phong_pars_fragment>
#include <shadowmap_pars_fragment>
#include <bumpmap_pars_fragment>
#include <normalmap_pars_fragment>
#include <specularmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	ReflectedLight reflectedLight = ReflectedLight( vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ) );
	vec3 totalEmissiveRadiance = emissive;
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <specularmap_fragment>
	#include <normal_fragment_begin>
	#include <normal_fragment_maps>
	#include <emissivemap_fragment>
	#include <lights_phong_fragment>
	#include <lights_fragment_begin>
	#include <lights_fragment_maps>
	#include <lights_fragment_end>
	#include <aomap_fragment>
	vec3 outgoingLight = reflectedLight.directDiffuse + reflectedLight.indirectDiffuse + reflectedLight.directSpecular + reflectedLight.indirectSpecular + totalEmissiveRadiance;
	#include <envmap_fragment>
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
	#include <dithering_fragment>
}`,H0=`#define STANDARD
varying vec3 vViewPosition;
#ifdef USE_TRANSMISSION
	varying vec3 vWorldPosition;
#endif
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <normal_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <shadowmap_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <normal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	vViewPosition = - mvPosition.xyz;
	#include <worldpos_vertex>
	#include <shadowmap_vertex>
	#include <fog_vertex>
#ifdef USE_TRANSMISSION
	vWorldPosition = worldPosition.xyz;
#endif
}`,V0=`#define STANDARD
#ifdef PHYSICAL
	#define IOR
	#define USE_SPECULAR
#endif
uniform vec3 diffuse;
uniform vec3 emissive;
uniform float roughness;
uniform float metalness;
uniform float opacity;
#ifdef IOR
	uniform float ior;
#endif
#ifdef USE_SPECULAR
	uniform float specularIntensity;
	uniform vec3 specularColor;
	#ifdef USE_SPECULAR_COLORMAP
		uniform sampler2D specularColorMap;
	#endif
	#ifdef USE_SPECULAR_INTENSITYMAP
		uniform sampler2D specularIntensityMap;
	#endif
#endif
#ifdef USE_CLEARCOAT
	uniform float clearcoat;
	uniform float clearcoatRoughness;
#endif
#ifdef USE_DISPERSION
	uniform float dispersion;
#endif
#ifdef USE_RETROREFLECTION
	uniform float retroreflectivity;
#endif
#ifdef USE_IRIDESCENCE
	uniform float iridescence;
	uniform float iridescenceIOR;
	uniform float iridescenceThicknessMinimum;
	uniform float iridescenceThicknessMaximum;
#endif
#ifdef USE_SHEEN
	uniform vec3 sheenColor;
	uniform float sheenRoughness;
	#ifdef USE_SHEEN_COLORMAP
		uniform sampler2D sheenColorMap;
	#endif
	#ifdef USE_SHEEN_ROUGHNESSMAP
		uniform sampler2D sheenRoughnessMap;
	#endif
#endif
#ifdef USE_ANISOTROPY
	uniform vec2 anisotropyVector;
	#ifdef USE_ANISOTROPYMAP
		uniform sampler2D anisotropyMap;
	#endif
#endif
varying vec3 vViewPosition;
#include <common>
#include <dithering_pars_fragment>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <aomap_pars_fragment>
#include <lightmap_pars_fragment>
#include <emissivemap_pars_fragment>
#include <iridescence_fragment>
#include <cube_uv_reflection_fragment>
#include <envmap_common_pars_fragment>
#include <envmap_physical_pars_fragment>
#include <fog_pars_fragment>
#include <lights_pars_begin>
#include <normal_pars_fragment>
#include <lights_physical_pars_fragment>
#include <transmission_pars_fragment>
#include <shadowmap_pars_fragment>
#include <bumpmap_pars_fragment>
#include <normalmap_pars_fragment>
#include <clearcoat_pars_fragment>
#include <iridescence_pars_fragment>
#include <roughnessmap_pars_fragment>
#include <metalnessmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	ReflectedLight reflectedLight = ReflectedLight( vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ) );
	vec3 totalEmissiveRadiance = emissive;
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <roughnessmap_fragment>
	#include <metalnessmap_fragment>
	#include <normal_fragment_begin>
	#include <normal_fragment_maps>
	#include <clearcoat_normal_fragment_begin>
	#include <clearcoat_normal_fragment_maps>
	#include <emissivemap_fragment>
	#include <lights_physical_fragment>
	#include <lights_fragment_begin>
	#include <lights_fragment_maps>
	#include <lights_fragment_end>
	#include <aomap_fragment>
	vec3 totalDiffuse = reflectedLight.directDiffuse + reflectedLight.indirectDiffuse;
	vec3 totalSpecular = reflectedLight.directSpecular + reflectedLight.indirectSpecular;
	#include <transmission_fragment>
	vec3 outgoingLight = totalDiffuse + totalSpecular + totalEmissiveRadiance;
	#ifdef USE_SHEEN
 
		outgoingLight = outgoingLight + sheenSpecularDirect + sheenSpecularIndirect;
 
 	#endif
	#ifdef USE_CLEARCOAT
		float dotNVcc = saturate( dot( geometryClearcoatNormal, geometryViewDir ) );
		vec3 Fcc = F_Schlick( material.clearcoatF0, material.clearcoatF90, dotNVcc );
		outgoingLight = outgoingLight * ( 1.0 - material.clearcoat * Fcc ) + ( clearcoatSpecularDirect + clearcoatSpecularIndirect ) * material.clearcoat;
	#endif
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
	#include <dithering_fragment>
}`,G0=`#define TOON
varying vec3 vViewPosition;
#include <common>
#include <batching_pars_vertex>
#include <uv_pars_vertex>
#include <displacementmap_pars_vertex>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <normal_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <shadowmap_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <normal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <displacementmap_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	vViewPosition = - mvPosition.xyz;
	#include <worldpos_vertex>
	#include <shadowmap_vertex>
	#include <fog_vertex>
}`,W0=`#define TOON
uniform vec3 diffuse;
uniform vec3 emissive;
uniform float opacity;
#include <common>
#include <dithering_pars_fragment>
#include <color_pars_fragment>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <aomap_pars_fragment>
#include <lightmap_pars_fragment>
#include <emissivemap_pars_fragment>
#include <gradientmap_pars_fragment>
#include <fog_pars_fragment>
#include <bsdfs>
#include <lights_pars_begin>
#include <normal_pars_fragment>
#include <lights_toon_pars_fragment>
#include <shadowmap_pars_fragment>
#include <bumpmap_pars_fragment>
#include <normalmap_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	ReflectedLight reflectedLight = ReflectedLight( vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ), vec3( 0.0 ) );
	vec3 totalEmissiveRadiance = emissive;
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <color_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	#include <normal_fragment_begin>
	#include <normal_fragment_maps>
	#include <emissivemap_fragment>
	#include <lights_toon_fragment>
	#include <lights_fragment_begin>
	#include <lights_fragment_maps>
	#include <lights_fragment_end>
	#include <aomap_fragment>
	vec3 outgoingLight = reflectedLight.directDiffuse + reflectedLight.indirectDiffuse + totalEmissiveRadiance;
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
	#include <dithering_fragment>
}`,X0=`uniform float size;
uniform float scale;
#include <common>
#include <color_pars_vertex>
#include <fog_pars_vertex>
#include <morphtarget_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
#ifdef USE_POINTS_UV
	varying vec2 vUv;
	uniform mat3 uvTransform;
#endif
void main() {
	#ifdef USE_POINTS_UV
		vUv = ( uvTransform * vec3( uv, 1 ) ).xy;
	#endif
	#include <color_vertex>
	#include <morphinstance_vertex>
	#include <morphcolor_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <project_vertex>
	gl_PointSize = size;
	#ifdef USE_SIZEATTENUATION
		bool isPerspective = isPerspectiveMatrix( projectionMatrix );
		if ( isPerspective ) gl_PointSize *= ( scale / - mvPosition.z );
	#endif
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	#include <worldpos_vertex>
	#include <fog_vertex>
}`,q0=`uniform vec3 diffuse;
uniform float opacity;
#include <common>
#include <color_pars_fragment>
#include <map_particle_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <fog_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	vec3 outgoingLight = vec3( 0.0 );
	#include <logdepthbuf_fragment>
	#include <map_particle_fragment>
	#include <color_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	outgoingLight = diffuseColor.rgb;
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
}`,Y0=`#include <common>
#include <batching_pars_vertex>
#include <fog_pars_vertex>
#include <morphtarget_pars_vertex>
#include <skinning_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <shadowmap_pars_vertex>
void main() {
	#include <batching_vertex>
	#include <beginnormal_vertex>
	#include <morphinstance_vertex>
	#include <morphnormal_vertex>
	#include <skinbase_vertex>
	#include <skinnormal_vertex>
	#include <defaultnormal_vertex>
	#include <begin_vertex>
	#include <morphtarget_vertex>
	#include <skinning_vertex>
	#include <project_vertex>
	#include <logdepthbuf_vertex>
	#include <worldpos_vertex>
	#include <shadowmap_vertex>
	#include <fog_vertex>
}`,Z0=`uniform vec3 color;
uniform float opacity;
#include <common>
#include <fog_pars_fragment>
#include <bsdfs>
#include <lights_pars_begin>
#include <logdepthbuf_pars_fragment>
#include <shadowmap_pars_fragment>
#include <shadowmask_pars_fragment>
void main() {
	#include <logdepthbuf_fragment>
	gl_FragColor = vec4( color, opacity * ( 1.0 - getShadowMask() ) );
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
	#include <premultiplied_alpha_fragment>
}`,J0=`uniform float rotation;
uniform vec2 center;
#include <common>
#include <uv_pars_vertex>
#include <fog_pars_vertex>
#include <logdepthbuf_pars_vertex>
#include <clipping_planes_pars_vertex>
void main() {
	#include <uv_vertex>
	vec4 mvPosition = modelViewMatrix[ 3 ];
	vec2 scale = vec2( length( modelMatrix[ 0 ].xyz ), length( modelMatrix[ 1 ].xyz ) );
	#ifndef USE_SIZEATTENUATION
		bool isPerspective = isPerspectiveMatrix( projectionMatrix );
		if ( isPerspective ) scale *= - mvPosition.z;
	#endif
	vec2 alignedPosition = ( position.xy - ( center - vec2( 0.5 ) ) ) * scale;
	vec2 rotatedPosition;
	rotatedPosition.x = cos( rotation ) * alignedPosition.x - sin( rotation ) * alignedPosition.y;
	rotatedPosition.y = sin( rotation ) * alignedPosition.x + cos( rotation ) * alignedPosition.y;
	mvPosition.xy += rotatedPosition;
	gl_Position = projectionMatrix * mvPosition;
	#include <logdepthbuf_vertex>
	#include <clipping_planes_vertex>
	#include <fog_vertex>
}`,$0=`uniform vec3 diffuse;
uniform float opacity;
#include <common>
#include <uv_pars_fragment>
#include <map_pars_fragment>
#include <alphamap_pars_fragment>
#include <alphatest_pars_fragment>
#include <alphahash_pars_fragment>
#include <fog_pars_fragment>
#include <logdepthbuf_pars_fragment>
#include <clipping_planes_pars_fragment>
void main() {
	vec4 diffuseColor = vec4( diffuse, opacity );
	#include <clipping_planes_fragment>
	vec3 outgoingLight = vec3( 0.0 );
	#include <logdepthbuf_fragment>
	#include <map_fragment>
	#include <alphamap_fragment>
	#include <alphatest_fragment>
	#include <alphahash_fragment>
	outgoingLight = diffuseColor.rgb;
	#include <opaque_fragment>
	#include <tonemapping_fragment>
	#include <colorspace_fragment>
	#include <fog_fragment>
}`,ee={alphahash_fragment:xp,alphahash_pars_fragment:_p,alphamap_fragment:yp,alphamap_pars_fragment:vp,alphatest_fragment:Mp,alphatest_pars_fragment:Sp,aomap_fragment:bp,aomap_pars_fragment:Ep,batching_pars_vertex:wp,batching_vertex:Tp,begin_vertex:Ap,beginnormal_vertex:Rp,bsdfs:Cp,iridescence_fragment:Pp,bumpmap_pars_fragment:Ip,clipping_planes_fragment:Lp,clipping_planes_pars_fragment:Dp,clipping_planes_pars_vertex:Up,clipping_planes_vertex:Np,color_fragment:Fp,color_pars_fragment:Bp,color_pars_vertex:Op,color_vertex:kp,common:zp,cube_uv_reflection_fragment:Hp,defaultnormal_vertex:Vp,displacementmap_pars_vertex:Gp,displacementmap_vertex:Wp,emissivemap_fragment:Xp,emissivemap_pars_fragment:qp,colorspace_fragment:Yp,colorspace_pars_fragment:Zp,envmap_fragment:Jp,envmap_common_pars_fragment:$p,envmap_pars_fragment:Kp,envmap_pars_vertex:Qp,envmap_physical_pars_fragment:cm,envmap_vertex:jp,fog_vertex:tm,fog_pars_vertex:em,fog_fragment:nm,fog_pars_fragment:im,gradientmap_pars_fragment:sm,lightmap_pars_fragment:rm,lights_lambert_fragment:am,lights_lambert_pars_fragment:om,lights_pars_begin:lm,lights_toon_fragment:hm,lights_toon_pars_fragment:um,lights_phong_fragment:dm,lights_phong_pars_fragment:fm,lights_physical_fragment:pm,lights_physical_pars_fragment:mm,lights_fragment_begin:gm,lights_fragment_maps:xm,lights_fragment_end:_m,lightprobes_pars_fragment:ym,logdepthbuf_fragment:vm,logdepthbuf_pars_fragment:Mm,logdepthbuf_pars_vertex:Sm,logdepthbuf_vertex:bm,map_fragment:Em,map_pars_fragment:wm,map_particle_fragment:Tm,map_particle_pars_fragment:Am,metalnessmap_fragment:Rm,metalnessmap_pars_fragment:Cm,morphinstance_vertex:Pm,morphcolor_vertex:Im,morphnormal_vertex:Lm,morphtarget_pars_vertex:Dm,morphtarget_vertex:Um,normal_fragment_begin:Nm,normal_fragment_maps:Fm,normal_pars_fragment:Bm,normal_pars_vertex:Om,normal_vertex:km,normalmap_pars_fragment:zm,clearcoat_normal_fragment_begin:Hm,clearcoat_normal_fragment_maps:Vm,clearcoat_pars_fragment:Gm,iridescence_pars_fragment:Wm,opaque_fragment:Xm,packing:qm,premultiplied_alpha_fragment:Ym,project_vertex:Zm,dithering_fragment:Jm,dithering_pars_fragment:$m,roughnessmap_fragment:Km,roughnessmap_pars_fragment:Qm,shadowmap_pars_fragment:jm,shadowmap_pars_vertex:t0,shadowmap_vertex:e0,shadowmask_pars_fragment:n0,skinbase_vertex:i0,skinning_pars_vertex:s0,skinning_vertex:r0,skinnormal_vertex:a0,specularmap_fragment:o0,specularmap_pars_fragment:l0,tonemapping_fragment:c0,tonemapping_pars_fragment:h0,transmission_fragment:u0,transmission_pars_fragment:d0,uv_pars_fragment:f0,uv_pars_vertex:p0,uv_vertex:m0,worldpos_vertex:g0,background_vert:x0,background_frag:_0,backgroundCube_vert:y0,backgroundCube_frag:v0,cube_vert:M0,cube_frag:S0,depth_vert:b0,depth_frag:E0,distance_vert:w0,distance_frag:T0,equirect_vert:A0,equirect_frag:R0,linedashed_vert:C0,linedashed_frag:P0,meshbasic_vert:I0,meshbasic_frag:L0,meshlambert_vert:D0,meshlambert_frag:U0,meshmatcap_vert:N0,meshmatcap_frag:F0,meshnormal_vert:B0,meshnormal_frag:O0,meshphong_vert:k0,meshphong_frag:z0,meshphysical_vert:H0,meshphysical_frag:V0,meshtoon_vert:G0,meshtoon_frag:W0,points_vert:X0,points_frag:q0,shadow_vert:Y0,shadow_frag:Z0,sprite_vert:J0,sprite_frag:$0},St={common:{diffuse:{value:new Ft(16777215)},opacity:{value:1},map:{value:null},mapTransform:{value:new $t},alphaMap:{value:null},alphaMapTransform:{value:new $t},alphaTest:{value:0}},specularmap:{specularMap:{value:null},specularMapTransform:{value:new $t}},envmap:{envMap:{value:null},envMapRotation:{value:new $t},reflectivity:{value:1},ior:{value:1.5},refractionRatio:{value:.98},dfgLUT:{value:null}},aomap:{aoMap:{value:null},aoMapIntensity:{value:1},aoMapTransform:{value:new $t}},lightmap:{lightMap:{value:null},lightMapIntensity:{value:1},lightMapTransform:{value:new $t}},bumpmap:{bumpMap:{value:null},bumpMapTransform:{value:new $t},bumpScale:{value:1}},normalmap:{normalMap:{value:null},normalMapTransform:{value:new $t},normalScale:{value:new rt(1,1)}},displacementmap:{displacementMap:{value:null},displacementMapTransform:{value:new $t},displacementScale:{value:1},displacementBias:{value:0}},emissivemap:{emissiveMap:{value:null},emissiveMapTransform:{value:new $t}},metalnessmap:{metalnessMap:{value:null},metalnessMapTransform:{value:new $t}},roughnessmap:{roughnessMap:{value:null},roughnessMapTransform:{value:new $t}},gradientmap:{gradientMap:{value:null}},fog:{fogDensity:{value:25e-5},fogNear:{value:1},fogFar:{value:2e3},fogColor:{value:new Ft(16777215)}},lights:{ambientLightColor:{value:[]},lightProbe:{value:[]},sunLights:{value:[],properties:{direction:{},color:{}}},sunLightShadows:{value:[],properties:{shadowIntensity:1,shadowBias:{},shadowNormalBias:{},shadowRadius:{},shadowMapSize:{}}},sunShadowMatrix:{value:[]},sunShadowCascade:{value:[]},directionalLights:{value:[],properties:{direction:{},color:{}}},directionalLightShadows:{value:[],properties:{shadowIntensity:1,shadowBias:{},shadowNormalBias:{},shadowRadius:{},shadowMapSize:{}}},directionalShadowMatrix:{value:[]},spotLights:{value:[],properties:{color:{},position:{},direction:{},distance:{},coneCos:{},penumbraCos:{},decay:{}}},spotLightShadows:{value:[],properties:{shadowIntensity:1,shadowBias:{},shadowNormalBias:{},shadowRadius:{},shadowMapSize:{}}},spotLightMap:{value:[]},spotLightMatrix:{value:[]},pointLights:{value:[],properties:{color:{},position:{},decay:{},distance:{}}},pointLightShadows:{value:[],properties:{shadowIntensity:1,shadowBias:{},shadowNormalBias:{},shadowRadius:{},shadowMapSize:{},shadowCameraNear:{},shadowCameraFar:{}}},pointShadowMatrix:{value:[]},hemisphereLights:{value:[],properties:{direction:{},skyColor:{},groundColor:{}}},rectAreaLights:{value:[],properties:{color:{},position:{},width:{},height:{}}},ltc_1:{value:null},ltc_2:{value:null},probesSH:{value:null},probesMin:{value:new D},probesMax:{value:new D},probesResolution:{value:new D}},points:{diffuse:{value:new Ft(16777215)},opacity:{value:1},size:{value:1},scale:{value:1},map:{value:null},alphaMap:{value:null},alphaMapTransform:{value:new $t},alphaTest:{value:0},uvTransform:{value:new $t}},sprite:{diffuse:{value:new Ft(16777215)},opacity:{value:1},center:{value:new rt(.5,.5)},rotation:{value:0},map:{value:null},mapTransform:{value:new $t},alphaMap:{value:null},alphaMapTransform:{value:new $t},alphaTest:{value:0}}},Yn={basic:{uniforms:Qe([St.common,St.specularmap,St.envmap,St.aomap,St.lightmap,St.fog]),vertexShader:ee.meshbasic_vert,fragmentShader:ee.meshbasic_frag},lambert:{uniforms:Qe([St.common,St.specularmap,St.envmap,St.aomap,St.lightmap,St.emissivemap,St.bumpmap,St.normalmap,St.displacementmap,St.fog,St.lights,{emissive:{value:new Ft(0)},envMapIntensity:{value:1}}]),vertexShader:ee.meshlambert_vert,fragmentShader:ee.meshlambert_frag},phong:{uniforms:Qe([St.common,St.specularmap,St.envmap,St.aomap,St.lightmap,St.emissivemap,St.bumpmap,St.normalmap,St.displacementmap,St.fog,St.lights,{emissive:{value:new Ft(0)},specular:{value:new Ft(1118481)},shininess:{value:30},envMapIntensity:{value:1}}]),vertexShader:ee.meshphong_vert,fragmentShader:ee.meshphong_frag},standard:{uniforms:Qe([St.common,St.envmap,St.aomap,St.lightmap,St.emissivemap,St.bumpmap,St.normalmap,St.displacementmap,St.roughnessmap,St.metalnessmap,St.fog,St.lights,{emissive:{value:new Ft(0)},roughness:{value:1},metalness:{value:0},envMapIntensity:{value:1}}]),vertexShader:ee.meshphysical_vert,fragmentShader:ee.meshphysical_frag},toon:{uniforms:Qe([St.common,St.aomap,St.lightmap,St.emissivemap,St.bumpmap,St.normalmap,St.displacementmap,St.gradientmap,St.fog,St.lights,{emissive:{value:new Ft(0)}}]),vertexShader:ee.meshtoon_vert,fragmentShader:ee.meshtoon_frag},matcap:{uniforms:Qe([St.common,St.bumpmap,St.normalmap,St.displacementmap,St.fog,{matcap:{value:null}}]),vertexShader:ee.meshmatcap_vert,fragmentShader:ee.meshmatcap_frag},points:{uniforms:Qe([St.points,St.fog]),vertexShader:ee.points_vert,fragmentShader:ee.points_frag},dashed:{uniforms:Qe([St.common,St.fog,{scale:{value:1},dashSize:{value:1},totalSize:{value:2}}]),vertexShader:ee.linedashed_vert,fragmentShader:ee.linedashed_frag},depth:{uniforms:Qe([St.common,St.displacementmap]),vertexShader:ee.depth_vert,fragmentShader:ee.depth_frag},normal:{uniforms:Qe([St.common,St.bumpmap,St.normalmap,St.displacementmap,{opacity:{value:1}}]),vertexShader:ee.meshnormal_vert,fragmentShader:ee.meshnormal_frag},sprite:{uniforms:Qe([St.sprite,St.fog]),vertexShader:ee.sprite_vert,fragmentShader:ee.sprite_frag},background:{uniforms:{uvTransform:{value:new $t},t2D:{value:null},backgroundIntensity:{value:1}},vertexShader:ee.background_vert,fragmentShader:ee.background_frag},backgroundCube:{uniforms:{envMap:{value:null},backgroundBlurriness:{value:0},backgroundIntensity:{value:1},backgroundRotation:{value:new $t}},vertexShader:ee.backgroundCube_vert,fragmentShader:ee.backgroundCube_frag},cube:{uniforms:{tCube:{value:null},tFlip:{value:-1},opacity:{value:1}},vertexShader:ee.cube_vert,fragmentShader:ee.cube_frag},equirect:{uniforms:{tEquirect:{value:null}},vertexShader:ee.equirect_vert,fragmentShader:ee.equirect_frag},distance:{uniforms:Qe([St.common,St.displacementmap,{referencePosition:{value:new D},nearDistance:{value:1},farDistance:{value:1e3}}]),vertexShader:ee.distance_vert,fragmentShader:ee.distance_frag},shadow:{uniforms:Qe([St.lights,St.fog,{color:{value:new Ft(0)},opacity:{value:1}}]),vertexShader:ee.shadow_vert,fragmentShader:ee.shadow_frag}};Yn.physical={uniforms:Qe([Yn.standard.uniforms,{clearcoat:{value:0},clearcoatMap:{value:null},clearcoatMapTransform:{value:new $t},clearcoatNormalMap:{value:null},clearcoatNormalMapTransform:{value:new $t},clearcoatNormalScale:{value:new rt(1,1)},clearcoatRoughness:{value:0},clearcoatRoughnessMap:{value:null},clearcoatRoughnessMapTransform:{value:new $t},dispersion:{value:0},retroreflectivity:{value:0},iridescence:{value:0},iridescenceMap:{value:null},iridescenceMapTransform:{value:new $t},iridescenceIOR:{value:1.3},iridescenceThicknessMinimum:{value:100},iridescenceThicknessMaximum:{value:400},iridescenceThicknessMap:{value:null},iridescenceThicknessMapTransform:{value:new $t},sheen:{value:0},sheenColor:{value:new Ft(0)},sheenColorMap:{value:null},sheenColorMapTransform:{value:new $t},sheenRoughness:{value:1},sheenRoughnessMap:{value:null},sheenRoughnessMapTransform:{value:new $t},transmission:{value:0},transmissionMap:{value:null},transmissionMapTransform:{value:new $t},transmissionSamplerSize:{value:new rt},transmissionSamplerMap:{value:null},thickness:{value:0},thicknessMap:{value:null},thicknessMapTransform:{value:new $t},attenuationDistance:{value:0},attenuationColor:{value:new Ft(0)},specularColor:{value:new Ft(1,1,1)},specularColorMap:{value:null},specularColorMapTransform:{value:new $t},specularIntensity:{value:1},specularIntensityMap:{value:null},specularIntensityMapTransform:{value:new $t},anisotropyVector:{value:new rt},anisotropyMap:{value:null},anisotropyMapTransform:{value:new $t}}]),vertexShader:ee.meshphysical_vert,fragmentShader:ee.meshphysical_frag};var fl={r:0,b:0,g:0},K0=new fe,xd=new $t;xd.set(-1,0,0,0,1,0,0,0,1);function Q0(s,t,e,n,i,r){let a=new Ft(0),o=i===!0?0:1,l,c,h=null,u=0,d=null;function f(S){let T=S.isScene===!0?S.background:null;if(T&&T.isTexture){let v=S.backgroundBlurriness>0;T=t.get(T,v)}return T}function p(S){let T=!1,v=f(S);v===null?g(a,o):v&&v.isColor&&(g(v,1),T=!0);let b=s.xr.getEnvironmentBlendMode();b==="additive"?e.buffers.color.setClear(0,0,0,1,r):b==="alpha-blend"&&e.buffers.color.setClear(0,0,0,0,r),(s.autoClear||T)&&(e.buffers.depth.setTest(!0),e.buffers.depth.setMask(!0),e.buffers.color.setMask(!0),s.clear(s.autoClearColor,s.autoClearDepth,s.autoClearStencil))}function y(S,T){let v=f(T);v&&(v.isCubeTexture||v.mapping===$r)?(c===void 0&&(c=new ut(new he(1,1,1),new Ce({name:"BackgroundCubeMaterial",uniforms:Ki(Yn.backgroundCube.uniforms),vertexShader:Yn.backgroundCube.vertexShader,fragmentShader:Yn.backgroundCube.fragmentShader,side:We,depthTest:!1,depthWrite:!1,fog:!1,allowOverride:!1})),c.geometry.deleteAttribute("normal"),c.geometry.deleteAttribute("uv"),c.onBeforeRender=function(b,w,P){this.matrixWorld.copyPosition(P.matrixWorld)},Object.defineProperty(c.material,"envMap",{get:function(){return this.uniforms.envMap.value}}),n.update(c)),c.material.uniforms.envMap.value=v,c.material.uniforms.backgroundBlurriness.value=T.backgroundBlurriness,c.material.uniforms.backgroundIntensity.value=T.backgroundIntensity,c.material.uniforms.backgroundRotation.value.setFromMatrix4(K0.makeRotationFromEuler(T.backgroundRotation)).transpose(),v.isCubeTexture&&v.isRenderTargetTexture===!1&&c.material.uniforms.backgroundRotation.value.premultiply(xd),c.material.toneMapped=ne.getTransfer(v.colorSpace)!==ce,(h!==v||u!==v.version||d!==s.toneMapping)&&(c.material.needsUpdate=!0,h=v,u=v.version,d=s.toneMapping),c.layers.enableAll(),S.unshift(c,c.geometry,c.material,0,0,null)):v&&v.isTexture&&(l===void 0&&(l=new ut(new Ke(2,2),new Ce({name:"BackgroundMaterial",uniforms:Ki(Yn.background.uniforms),vertexShader:Yn.background.vertexShader,fragmentShader:Yn.background.fragmentShader,side:wi,depthTest:!1,depthWrite:!1,fog:!1,allowOverride:!1})),l.geometry.deleteAttribute("normal"),Object.defineProperty(l.material,"map",{get:function(){return this.uniforms.t2D.value}}),n.update(l)),l.material.uniforms.t2D.value=v,l.material.uniforms.backgroundIntensity.value=T.backgroundIntensity,l.material.toneMapped=ne.getTransfer(v.colorSpace)!==ce,v.matrixAutoUpdate===!0&&v.updateMatrix(),l.material.uniforms.uvTransform.value.copy(v.matrix),(h!==v||u!==v.version||d!==s.toneMapping)&&(l.material.needsUpdate=!0,h=v,u=v.version,d=s.toneMapping),l.layers.enableAll(),S.unshift(l,l.geometry,l.material,0,0,null))}function g(S,T){S.getRGB(fl,Oc(s)),e.buffers.color.setClear(fl.r,fl.g,fl.b,T,r)}function m(){c!==void 0&&(c.geometry.dispose(),c.material.dispose(),c=void 0),l!==void 0&&(l.geometry.dispose(),l.material.dispose(),l=void 0)}return{getClearColor:function(){return a},setClearColor:function(S,T=1){a.set(S),o=T,g(a,o)},getClearAlpha:function(){return o},setClearAlpha:function(S){o=S,g(a,o)},render:p,addToRenderList:y,dispose:m}}function j0(s,t){let e=s.getParameter(s.MAX_VERTEX_ATTRIBS),n={},i=d(null),r=i,a=!1;function o(I,U,k,L,B){let W=!1,X=u(I,L,k,U);r!==X&&(r=X,c(r.object)),W=f(I,L,k,B),W&&p(I,L,k,B),B!==null&&t.update(B,s.ELEMENT_ARRAY_BUFFER),(W||a)&&(a=!1,v(I,U,k,L),B!==null&&s.bindBuffer(s.ELEMENT_ARRAY_BUFFER,t.get(B).buffer))}function l(){return s.createVertexArray()}function c(I){return s.bindVertexArray(I)}function h(I){return s.deleteVertexArray(I)}function u(I,U,k,L){let B=L.wireframe===!0,W=n[U.id];W===void 0&&(W={},n[U.id]=W);let X=I.isInstancedMesh===!0?I.id:0,st=W[X];st===void 0&&(st={},W[X]=st);let Z=st[k.id];Z===void 0&&(Z={},st[k.id]=Z);let Q=Z[B];return Q===void 0&&(Q=d(l()),Z[B]=Q),Q}function d(I){let U=[],k=[],L=[];for(let B=0;B<e;B++)U[B]=0,k[B]=0,L[B]=0;return{geometry:null,program:null,wireframe:!1,newAttributes:U,enabledAttributes:k,attributeDivisors:L,object:I,attributes:{},index:null}}function f(I,U,k,L){let B=r.attributes,W=U.attributes,X=0,st=k.getAttributes();for(let Z in st)if(st[Z].location>=0){let it=B[Z],nt=W[Z];if(nt===void 0&&(Z==="instanceMatrix"&&I.instanceMatrix&&(nt=I.instanceMatrix),Z==="instanceColor"&&I.instanceColor&&(nt=I.instanceColor)),it===void 0||it.attribute!==nt||nt&&it.data!==nt.data)return!0;X++}return r.attributesNum!==X||r.index!==L}function p(I,U,k,L){let B={},W=U.attributes,X=0,st=k.getAttributes();for(let Z in st)if(st[Z].location>=0){let it=W[Z];it===void 0&&(Z==="instanceMatrix"&&I.instanceMatrix&&(it=I.instanceMatrix),Z==="instanceColor"&&I.instanceColor&&(it=I.instanceColor));let nt={};nt.attribute=it,it&&it.data&&(nt.data=it.data),B[Z]=nt,X++}r.attributes=B,r.attributesNum=X,r.index=L}function y(){let I=r.newAttributes;for(let U=0,k=I.length;U<k;U++)I[U]=0}function g(I){m(I,0)}function m(I,U){let k=r.newAttributes,L=r.enabledAttributes,B=r.attributeDivisors;k[I]=1,L[I]===0&&(s.enableVertexAttribArray(I),L[I]=1),B[I]!==U&&(s.vertexAttribDivisor(I,U),B[I]=U)}function S(){let I=r.newAttributes,U=r.enabledAttributes;for(let k=0,L=U.length;k<L;k++)U[k]!==I[k]&&(s.disableVertexAttribArray(k),U[k]=0)}function T(I,U,k,L,B,W,X){X===!0?s.vertexAttribIPointer(I,U,k,B,W):s.vertexAttribPointer(I,U,k,L,B,W)}function v(I,U,k,L){y();let B=L.attributes,W=k.getAttributes(),X=U.defaultAttributeValues;for(let st in W){let Z=W[st];if(Z.location>=0){let Q=B[st];if(Q===void 0&&(st==="instanceMatrix"&&I.instanceMatrix&&(Q=I.instanceMatrix),st==="instanceColor"&&I.instanceColor&&(Q=I.instanceColor)),Q!==void 0){let it=Q.normalized,nt=Q.itemSize,ct=t.get(Q);if(ct===void 0)continue;let Yt=ct.buffer,Jt=ct.type,Qt=ct.bytesPerElement,J=Jt===s.INT||Jt===s.UNSIGNED_INT||Q.gpuType===Ro;if(Q.isInterleavedBufferAttribute){let $=Q.data,mt=$.stride,Lt=Q.offset;if($.isInstancedInterleavedBuffer){for(let gt=0;gt<Z.locationSize;gt++)m(Z.location+gt,$.meshPerAttribute);I.isInstancedMesh!==!0&&L._maxInstanceCount===void 0&&(L._maxInstanceCount=$.meshPerAttribute*$.count)}else for(let gt=0;gt<Z.locationSize;gt++)g(Z.location+gt);s.bindBuffer(s.ARRAY_BUFFER,Yt);for(let gt=0;gt<Z.locationSize;gt++)T(Z.location+gt,nt/Z.locationSize,Jt,it,mt*Qt,(Lt+nt/Z.locationSize*gt)*Qt,J)}else{if(Q.isInstancedBufferAttribute){for(let $=0;$<Z.locationSize;$++)m(Z.location+$,Q.meshPerAttribute);I.isInstancedMesh!==!0&&L._maxInstanceCount===void 0&&(L._maxInstanceCount=Q.meshPerAttribute*Q.count)}else for(let $=0;$<Z.locationSize;$++)g(Z.location+$);s.bindBuffer(s.ARRAY_BUFFER,Yt);for(let $=0;$<Z.locationSize;$++)T(Z.location+$,nt/Z.locationSize,Jt,it,nt*Qt,nt/Z.locationSize*$*Qt,J)}}else if(X!==void 0){let it=X[st];if(it!==void 0)switch(it.length){case 2:s.vertexAttrib2fv(Z.location,it);break;case 3:s.vertexAttrib3fv(Z.location,it);break;case 4:s.vertexAttrib4fv(Z.location,it);break;default:s.vertexAttrib1fv(Z.location,it)}}}}S()}function b(){E();for(let I in n){let U=n[I];for(let k in U){let L=U[k];for(let B in L){let W=L[B];for(let X in W)h(W[X].object),delete W[X];delete L[B]}}delete n[I]}}function w(I){if(n[I.id]===void 0)return;let U=n[I.id];for(let k in U){let L=U[k];for(let B in L){let W=L[B];for(let X in W)h(W[X].object),delete W[X];delete L[B]}}delete n[I.id]}function P(I){for(let U in n){let k=n[U];for(let L in k){let B=k[L];if(B[I.id]===void 0)continue;let W=B[I.id];for(let X in W)h(W[X].object),delete W[X];delete B[I.id]}}}function _(I){for(let U in n){let k=n[U],L=I.isInstancedMesh===!0?I.id:0,B=k[L];if(B!==void 0){for(let W in B){let X=B[W];for(let st in X)h(X[st].object),delete X[st];delete B[W]}delete k[L],Object.keys(k).length===0&&delete n[U]}}}function E(){C(),a=!0,r!==i&&(r=i,c(r.object))}function C(){i.geometry=null,i.program=null,i.wireframe=!1}return{setup:o,reset:E,resetDefaultState:C,dispose:b,releaseStatesOfGeometry:w,releaseStatesOfObject:_,releaseStatesOfProgram:P,initAttributes:y,enableAttribute:g,disableUnusedAttributes:S}}function tg(s,t,e){let n;function i(l){n=l}function r(l,c){s.drawArrays(n,l,c),e.update(c,n,1)}function a(l,c,h){h!==0&&(s.drawArraysInstanced(n,l,c,h),e.update(c,n,h))}function o(l,c,h){if(h===0)return;t.get("WEBGL_multi_draw").multiDrawArraysWEBGL(n,l,0,c,0,h);let d=0;for(let f=0;f<h;f++)d+=c[f];e.update(d,n,1)}this.setMode=i,this.render=r,this.renderInstances=a,this.renderMultiDraw=o}function eg(s,t,e,n){let i;function r(){if(i!==void 0)return i;if(t.has("EXT_texture_filter_anisotropic")===!0){let P=t.get("EXT_texture_filter_anisotropic");i=s.getParameter(P.MAX_TEXTURE_MAX_ANISOTROPY_EXT)}else i=0;return i}function a(P){return!(P!==vn&&n.convert(P)!==s.getParameter(s.IMPLEMENTATION_COLOR_READ_FORMAT))}function o(P){let _=P===Le&&(t.has("EXT_color_buffer_half_float")||t.has("EXT_color_buffer_float"));return!(P!==rn&&P!==yn&&!_&&n.convert(P)!==s.getParameter(s.IMPLEMENTATION_COLOR_READ_TYPE))}function l(P){if(P==="highp"){if(s.getShaderPrecisionFormat(s.VERTEX_SHADER,s.HIGH_FLOAT).precision>0&&s.getShaderPrecisionFormat(s.FRAGMENT_SHADER,s.HIGH_FLOAT).precision>0)return"highp";P="mediump"}return P==="mediump"&&s.getShaderPrecisionFormat(s.VERTEX_SHADER,s.MEDIUM_FLOAT).precision>0&&s.getShaderPrecisionFormat(s.FRAGMENT_SHADER,s.MEDIUM_FLOAT).precision>0?"mediump":"lowp"}let c=e.precision!==void 0?e.precision:"highp",h=l(c);h!==c&&(qt("WebGLRenderer:",c,"not supported, using",h,"instead."),c=h);let u=e.logarithmicDepthBuffer===!0,d=e.reversedDepthBuffer===!0&&t.has("EXT_clip_control");e.reversedDepthBuffer===!0&&d===!1&&qt("WebGLRenderer: Unable to use reversed depth buffer due to missing EXT_clip_control extension. Fallback to default depth buffer.");let f=s.getParameter(s.MAX_TEXTURE_IMAGE_UNITS),p=s.getParameter(s.MAX_VERTEX_TEXTURE_IMAGE_UNITS),y=s.getParameter(s.MAX_TEXTURE_SIZE),g=s.getParameter(s.MAX_CUBE_MAP_TEXTURE_SIZE),m=s.getParameter(s.MAX_VERTEX_ATTRIBS),S=s.getParameter(s.MAX_VERTEX_UNIFORM_VECTORS),T=s.getParameter(s.MAX_VARYING_VECTORS),v=s.getParameter(s.MAX_FRAGMENT_UNIFORM_VECTORS),b=s.getParameter(s.MAX_SAMPLES),w=s.getParameter(s.SAMPLES);return{isWebGL2:!0,getMaxAnisotropy:r,getMaxPrecision:l,textureFormatReadable:a,textureTypeReadable:o,precision:c,logarithmicDepthBuffer:u,reversedDepthBuffer:d,maxTextures:f,maxVertexTextures:p,maxTextureSize:y,maxCubemapSize:g,maxAttributes:m,maxVertexUniforms:S,maxVaryings:T,maxFragmentUniforms:v,maxSamples:b,samples:w}}function ng(s){let t=this,e=null,n=0,i=!1,r=!1,a=new Rn,o=new $t,l={value:null,needsUpdate:!1};this.uniform=l,this.numPlanes=0,this.numIntersection=0,this.init=function(u,d){let f=u.length!==0||d||n!==0||i;return i=d,n=u.length,f},this.beginShadows=function(){r=!0,h(null)},this.endShadows=function(){r=!1},this.setGlobalState=function(u,d){e=h(u,d,0)},this.setState=function(u,d,f){let p=u.clippingPlanes,y=u.clipIntersection,g=u.clipShadows,m=s.get(u);if(!i||p===null||p.length===0||r&&!g)r?h(null):c();else{let S=r?0:n,T=S*4,v=m.clippingState||null;l.value=v,v=h(p,d,T,f);for(let b=0;b!==T;++b)v[b]=e[b];m.clippingState=v,this.numIntersection=y?this.numPlanes:0,this.numPlanes+=S}};function c(){l.value!==e&&(l.value=e,l.needsUpdate=n>0),t.numPlanes=n,t.numIntersection=0}function h(u,d,f,p){let y=u!==null?u.length:0,g=null;if(y!==0){if(g=l.value,p!==!0||g===null){let m=f+y*4,S=d.matrixWorldInverse;o.getNormalMatrix(S),(g===null||g.length<m)&&(g=new Float32Array(m));for(let T=0,v=f;T!==y;++T,v+=4)a.copy(u[T]).applyMatrix4(S,o),a.normal.toArray(g,v),g[v+3]=a.constant}l.value=g,l.needsUpdate=!0}return t.numPlanes=y,t.numIntersection=0,g}}var Hs=4,ig=6,sg=20,rg=256,ra=new Ei,Ju=new Ft,Yc=null,Zc=0,Jc=0,$c=!1,ag=new D,Qi=new D,Gs=class{constructor(t){this._renderer=t,this._pingPongRenderTarget=null,this._lodMax=0,this._cubeSize=0,this._sizeLods=[],this._lodMeshes=[],this._backgroundBox=null,this._cubemapMaterial=null,this._equirectMaterial=null,this._blurMaterial=null,this._ggxMaterial=null}fromScene(t,e=0,n=.1,i=100,r={}){let{size:a=256,position:o=ag}=r;Yc=this._renderer.getRenderTarget(),Zc=this._renderer.getActiveCubeFace(),Jc=this._renderer.getActiveMipmapLevel(),$c=this._renderer.xr.enabled,this._renderer.xr.enabled=!1,this._setSize(a);let l=this._allocateTargets();return l.depthBuffer=!0,this._sceneToCubeUV(t,n,i,l,o),e>0&&this._blur(l,0,0,e),this._applyPMREM(l),this._cleanup(l),l}fromEquirectangular(t,e=null){return this._fromTexture(t,e)}fromCubemap(t,e=null){return this._fromTexture(t,e)}compileCubemapShader(){this._cubemapMaterial===null&&(this._cubemapMaterial=Qu(),this._compileMaterial(this._cubemapMaterial))}compileEquirectangularShader(){this._equirectMaterial===null&&(this._equirectMaterial=Ku(),this._compileMaterial(this._equirectMaterial))}dispose(){this._dispose(),this._cubemapMaterial!==null&&this._cubemapMaterial.dispose(),this._equirectMaterial!==null&&this._equirectMaterial.dispose(),this._backgroundBox!==null&&(this._backgroundBox.geometry.dispose(),this._backgroundBox.material.dispose())}_setSize(t){this._lodMax=Math.floor(Math.log2(t)),this._cubeSize=Math.pow(2,this._lodMax)}_dispose(){this._blurMaterial!==null&&this._blurMaterial.dispose(),this._ggxMaterial!==null&&this._ggxMaterial.dispose(),this._pingPongRenderTarget!==null&&this._pingPongRenderTarget.dispose();for(let t=0;t<this._lodMeshes.length;t++)this._lodMeshes[t].geometry.dispose()}_cleanup(t){this._renderer.setRenderTarget(Yc,Zc,Jc),this._renderer.xr.enabled=$c,t.scissorTest=!1,zs(t,0,0,t.width,t.height)}_fromTexture(t,e){t.mapping===Ti||t.mapping===$i?this._setSize(t.image.length===0?16:t.image[0].width||t.image[0].image.width):this._setSize(t.image.width/4),Yc=this._renderer.getRenderTarget(),Zc=this._renderer.getActiveCubeFace(),Jc=this._renderer.getActiveMipmapLevel(),$c=this._renderer.xr.enabled,this._renderer.xr.enabled=!1;let n=e||this._allocateTargets();return this._textureToCubeUV(t,n),this._applyPMREM(n),this._cleanup(n),n}_allocateTargets(){let t=3*Math.max(this._cubeSize,112),e=4*this._cubeSize,n={magFilter:Ge,minFilter:Ge,generateMipmaps:!1,type:Le,format:vn,colorSpace:dr,depthBuffer:!1},i=$u(t,e,n);if(this._pingPongRenderTarget===null||this._pingPongRenderTarget.width!==t||this._pingPongRenderTarget.height!==e){this._pingPongRenderTarget!==null&&this._dispose(),this._pingPongRenderTarget=$u(t,e,n);let{_lodMax:r}=this;({lodMeshes:this._lodMeshes,sizeLods:this._sizeLods}=og(r)),this._blurMaterial=cg(r,t,e),this._ggxMaterial=lg(r,t,e)}return i}_compileMaterial(t){let e=new ut(new xe,t);this._renderer.compile(e,ra)}_sceneToCubeUV(t,e,n,i,r){let l=new Oe(90,1,e,n),c=[1,-1,1,1,1,1],h=[1,1,1,-1,-1,-1],u=this._renderer,d=u.autoClear,f=u.toneMapping;u.getClearColor(Ju),u.toneMapping=Ln,u.autoClear=!1,u.state.buffers.depth.getReversed()&&(u.setRenderTarget(i),u.clearDepth(),u.setRenderTarget(null)),this._backgroundBox===null&&(this._backgroundBox=new ut(new he,new $e({name:"PMREM.Background",side:We,depthWrite:!1,depthTest:!1})));let y=this._backgroundBox,g=y.material,m=!1,S=t.background;S?S.isColor&&(g.color.copy(S),t.background=null,m=!0):(g.color.copy(Ju),m=!0);for(let T=0;T<6;T++){let v=T%3;v===0?(l.up.set(0,c[T],0),l.position.set(r.x,r.y,r.z),l.lookAt(r.x+h[T],r.y,r.z)):v===1?(l.up.set(0,0,c[T]),l.position.set(r.x,r.y,r.z),l.lookAt(r.x,r.y+h[T],r.z)):(l.up.set(0,c[T],0),l.position.set(r.x,r.y,r.z),l.lookAt(r.x,r.y,r.z+h[T]));let b=this._cubeSize;zs(i,v*b,T>2?b:0,b,b),u.setRenderTarget(i),m&&u.render(y,l),u.render(t,l)}u.toneMapping=f,u.autoClear=d,t.background=S}_textureToCubeUV(t,e){let n=this._renderer,i=t.mapping===Ti||t.mapping===$i;i?(this._cubemapMaterial===null&&(this._cubemapMaterial=Qu()),this._cubemapMaterial.uniforms.flipEnvMap.value=t.isRenderTargetTexture===!1?-1:1):this._equirectMaterial===null&&(this._equirectMaterial=Ku());let r=i?this._cubemapMaterial:this._equirectMaterial,a=this._lodMeshes[0];a.material=r;let o=r.uniforms;o.envMap.value=t;let l=this._cubeSize;zs(e,0,0,3*l,2*l),n.setRenderTarget(e),n.render(a,ra)}_applyPMREM(t){let e=this._renderer,n=e.autoClear;e.autoClear=!1;let i=this._lodMeshes.length;for(let r=1;r<i;r++)this._applyGGXFilter(t,r-1,r);e.autoClear=n}_applyGGXFilter(t,e,n){let i=this._renderer,r=this._pingPongRenderTarget,a=this._ggxMaterial,o=this._lodMeshes[n];o.material=a;let l=a.uniforms,c=n/(this._lodMeshes.length-1),h=e/(this._lodMeshes.length-1),u=Math.sqrt(c*c-h*h),d=c*1.25,f=u*d,{_lodMax:p}=this,y=this._sizeLods[n],g=3*y*(n>p-Hs?n-p+Hs:0),m=4*(this._cubeSize-y);l.envMap.value=t.texture,l.roughness.value=f,l.mipInt.value=p-e,zs(r,g,m,3*y,2*y),i.setRenderTarget(r),i.render(o,ra),l.envMap.value=r.texture,l.roughness.value=0,l.mipInt.value=p-n,zs(t,g,m,3*y,2*y),i.setRenderTarget(t),i.render(o,ra)}_blur(t,e,n,i){let r=this._pingPongRenderTarget,a=Math.min(i,Math.PI)/Math.SQRT2;this._blurPass(t,r,e,n,a),this._blurPass(r,t,n,n,a)}_blurPass(t,e,n,i,r){let a=this._renderer,o=this._blurMaterial,l=this._lodMeshes[i];l.material=o;let c=o.uniforms;c.envMap.value=t.texture,c.sigma.value=r,c.mipInt.value=this._lodMax-n;let h=this._sizeLods[i],u=3*h*(i>this._lodMax-Hs?i-this._lodMax+Hs:0),d=4*(this._cubeSize-h);zs(e,u,d,3*h,2*h),a.setRenderTarget(e),a.render(l,ra)}};function og(s){let t=[],e=[],n=s,i=s-Hs+1+ig;for(let r=0;r<i;r++){let a=Math.pow(2,n);t.push(a);let o=1/(a-2),l=-o,c=1+o,h=[l,l,c,l,c,c,l,l,c,c,l,c],u=6,d=6,f=3,p=new Float32Array(f*d*u),y=new Float32Array(f*d*u);for(let m=0;m<u;m++){let S=m%3*2/3-1,T=m>2?0:-1,v=[S,T,0,S+2/3,T,0,S+2/3,T+1,0,S,T,0,S+2/3,T+1,0,S,T+1,0];p.set(v,f*d*m);for(let b=0;b<d;b++){let w=h[b*2]*2-1,P=h[b*2+1]*2-1;m===0?Qi.set(1,P,w):m===1?Qi.set(-w,1,-P):m===2?Qi.set(-w,P,1):m===3?Qi.set(-1,P,-w):m===4?Qi.set(-w,-1,P):Qi.set(w,P,-1),Qi.toArray(y,(m*d+b)*f)}}let g=new xe;g.setAttribute("position",new ke(p,f)),g.setAttribute("outputDirection",new ke(y,f)),e.push(new ut(g,null)),n>Hs&&n--}return{lodMeshes:e,sizeLods:t}}function $u(s,t,e){let n=new Te(s,t,e);return n.texture.mapping=$r,n.texture.name="PMREM.cubeUv",n.scissorTest=!0,n}function zs(s,t,e,n,i){s.viewport.set(t,e,n,i),s.scissor.set(t,e,n,i)}function lg(s,t,e){return new Ce({name:"PMREMGGXConvolution",defines:{GGX_SAMPLES:rg,CUBEUV_TEXEL_WIDTH:1/t,CUBEUV_TEXEL_HEIGHT:1/e,CUBEUV_MAX_MIP:`${s}.0`},uniforms:{envMap:{value:null},roughness:{value:0},mipInt:{value:0}},vertexShader:xl(),fragmentShader:`

			precision highp float;
			precision highp int;

			varying vec3 vOutputDirection;

			uniform sampler2D envMap;
			uniform float roughness;
			uniform float mipInt;

			#define ENVMAP_TYPE_CUBE_UV
			#include <cube_uv_reflection_fragment>

			#define PI 3.14159265359

			// Van der Corput radical inverse
			float radicalInverse_VdC(uint bits) {
				bits = (bits << 16u) | (bits >> 16u);
				bits = ((bits & 0x55555555u) << 1u) | ((bits & 0xAAAAAAAAu) >> 1u);
				bits = ((bits & 0x33333333u) << 2u) | ((bits & 0xCCCCCCCCu) >> 2u);
				bits = ((bits & 0x0F0F0F0Fu) << 4u) | ((bits & 0xF0F0F0F0u) >> 4u);
				bits = ((bits & 0x00FF00FFu) << 8u) | ((bits & 0xFF00FF00u) >> 8u);
				return float(bits) * 2.3283064365386963e-10; // / 0x100000000
			}

			// Hammersley sequence
			vec2 hammersley(uint i, uint N) {
				return vec2(float(i) / float(N), radicalInverse_VdC(i));
			}

			// GGX VNDF importance sampling (Eric Heitz 2018)
			// "Sampling the GGX Distribution of Visible Normals"
			// https://jcgt.org/published/0007/04/01/
			vec3 importanceSampleGGX_VNDF(vec2 Xi, vec3 V, float roughness) {
				float alpha = roughness * roughness;

				// Section 4.1: Orthonormal basis
				vec3 T1 = vec3(1.0, 0.0, 0.0);
				vec3 T2 = cross(V, T1);

				// Section 4.2: Parameterization of projected area
				float r = sqrt(Xi.x);
				float phi = 2.0 * PI * Xi.y;
				float t1 = r * cos(phi);
				float t2 = r * sin(phi);
				float s = 0.5 * (1.0 + V.z);
				t2 = (1.0 - s) * sqrt(1.0 - t1 * t1) + s * t2;

				// Section 4.3: Reprojection onto hemisphere
				vec3 Nh = t1 * T1 + t2 * T2 + sqrt(max(0.0, 1.0 - t1 * t1 - t2 * t2)) * V;

				// Section 3.4: Transform back to ellipsoid configuration
				return normalize(vec3(alpha * Nh.x, alpha * Nh.y, max(0.0, Nh.z)));
			}

			void main() {
				vec3 N = normalize(vOutputDirection);
				vec3 V = N; // Assume view direction equals normal for pre-filtering

				vec3 prefilteredColor = vec3(0.0);
				float totalWeight = 0.0;

				// For very low roughness, just sample the environment directly
				if (roughness < 0.001) {
					gl_FragColor = vec4(bilinearCubeUV(envMap, N, mipInt), 1.0);
					return;
				}

				// Tangent space basis for VNDF sampling
				vec3 up = abs(N.z) < 0.999 ? vec3(0.0, 0.0, 1.0) : vec3(1.0, 0.0, 0.0);
				vec3 tangent = normalize(cross(up, N));
				vec3 bitangent = cross(N, tangent);

				for(uint i = 0u; i < uint(GGX_SAMPLES); i++) {
					vec2 Xi = hammersley(i, uint(GGX_SAMPLES));

					// For PMREM, V = N, so in tangent space V is always (0, 0, 1)
					vec3 H_tangent = importanceSampleGGX_VNDF(Xi, vec3(0.0, 0.0, 1.0), roughness);

					// Transform H back to world space
					vec3 H = normalize(tangent * H_tangent.x + bitangent * H_tangent.y + N * H_tangent.z);
					vec3 L = normalize(2.0 * dot(V, H) * H - V);

					float NdotL = max(dot(N, L), 0.0);

					if(NdotL > 0.0) {
						// Sample environment at fixed mip level
						// VNDF importance sampling handles the distribution filtering
						vec3 sampleColor = bilinearCubeUV(envMap, L, mipInt);

						// Weight by NdotL for the split-sum approximation
						// VNDF PDF naturally accounts for the visible microfacet distribution
						prefilteredColor += sampleColor * NdotL;
						totalWeight += NdotL;
					}
				}

				if (totalWeight > 0.0) {
					prefilteredColor = prefilteredColor / totalWeight;
				}

				gl_FragColor = vec4(prefilteredColor, 1.0);
			}
		`,blending:_n,depthTest:!1,depthWrite:!1})}function cg(s,t,e){return new Ce({name:"SphericalGaussianBlur",defines:{SAMPLES:sg,CUBEUV_TEXEL_WIDTH:1/t,CUBEUV_TEXEL_HEIGHT:1/e,CUBEUV_MAX_MIP:`${s}.0`},uniforms:{envMap:{value:null},sigma:{value:0},mipInt:{value:0}},vertexShader:xl(),fragmentShader:`

			precision highp float;
			precision highp int;

			varying vec3 vOutputDirection;

			uniform sampler2D envMap;
			uniform float sigma;
			uniform float mipInt;

			#define ENVMAP_TYPE_CUBE_UV
			#include <cube_uv_reflection_fragment>

			#define PI 3.14159265359
			#define GOLDEN_ANGLE 2.39996322973

			void main() {

				if ( sigma == 0.0 ) {

					gl_FragColor = vec4( bilinearCubeUV( envMap, vOutputDirection, mipInt ), 1.0 );
					return;

				}

				vec3 outputDirection = normalize( vOutputDirection );

				vec3 up = abs( outputDirection.z ) < 0.999 ? vec3( 0.0, 0.0, 1.0 ) : vec3( 1.0, 0.0, 0.0 );
				vec3 tangent = normalize( cross( up, outputDirection ) );
				vec3 bitangent = cross( outputDirection, tangent );

				// Truncate the kernel at three standard deviations or at the antipode.
				float thetaMax = min( 3.0 * sigma, PI );
				float truncation = 1.0 - exp( - 0.5 * thetaMax * thetaMax / ( sigma * sigma ) );

				vec3 accumColor = vec3( 0.0 );
				float accumWeight = 0.0;

				for ( int i = 0; i < SAMPLES; i ++ ) {

					// Stratified inverse-CDF sampling of the Gaussian, placed on a golden-angle spiral.
					float stratum = ( float( i ) + 0.5 ) / float( SAMPLES );
					float theta = sigma * sqrt( - 2.0 * log( 1.0 - stratum * truncation ) );
					float phi = float( i ) * GOLDEN_ANGLE;

					vec3 offset = cos( phi ) * tangent + sin( phi ) * bitangent;
					vec3 sampleDirection = cos( theta ) * outputDirection + sin( theta ) * offset;

					// Correct the planar sample density to solid angle.
					float weight = sin( theta ) / theta;

					accumColor += weight * bilinearCubeUV( envMap, sampleDirection, mipInt );
					accumWeight += weight;

				}

				gl_FragColor = vec4( accumColor / accumWeight, 1.0 );

			}
		`,blending:_n,depthTest:!1,depthWrite:!1})}function Ku(){return new Ce({name:"EquirectangularToCubeUV",uniforms:{envMap:{value:null}},vertexShader:xl(),fragmentShader:`

			precision mediump float;
			precision mediump int;

			varying vec3 vOutputDirection;

			uniform sampler2D envMap;

			#include <common>

			void main() {

				vec3 outputDirection = normalize( vOutputDirection );
				vec2 uv = equirectUv( outputDirection );

				gl_FragColor = vec4( texture2D ( envMap, uv ).rgb, 1.0 );

			}
		`,blending:_n,depthTest:!1,depthWrite:!1})}function Qu(){return new Ce({name:"CubemapToCubeUV",uniforms:{envMap:{value:null},flipEnvMap:{value:-1}},vertexShader:xl(),fragmentShader:`

			precision mediump float;
			precision mediump int;

			uniform float flipEnvMap;

			varying vec3 vOutputDirection;

			uniform samplerCube envMap;

			void main() {

				gl_FragColor = textureCube( envMap, vec3( flipEnvMap * vOutputDirection.x, vOutputDirection.yz ) );

			}
		`,blending:_n,depthTest:!1,depthWrite:!1})}function xl(){return`

		precision mediump float;
		precision mediump int;

		attribute vec3 outputDirection;

		varying vec3 vOutputDirection;

		void main() {

			vOutputDirection = outputDirection;
			gl_Position = vec4( position, 1.0 );

		}
	`}var ml=class extends Te{constructor(t=1,e={}){super(t,t,e),this.isWebGLCubeRenderTarget=!0;let n={width:t,height:t,depth:1},i=[n,n,n,n,n,n];this.texture=new wr(i),this._setTextureOptions(e),this.texture.isRenderTargetTexture=!0}fromEquirectangularTexture(t,e){this.texture.type=e.type,this.texture.colorSpace=e.colorSpace,this.texture.generateMipmaps=e.generateMipmaps,this.texture.minFilter=e.minFilter,this.texture.magFilter=e.magFilter;let n={uniforms:{tEquirect:{value:null}},vertexShader:`

				varying vec3 vWorldDirection;

				vec3 transformDirection( in vec3 dir, in mat4 matrix ) {

					return normalize( ( matrix * vec4( dir, 0.0 ) ).xyz );

				}

				void main() {

					vWorldDirection = transformDirection( position, modelMatrix );

					#include <begin_vertex>
					#include <project_vertex>

				}
			`,fragmentShader:`

				uniform sampler2D tEquirect;

				varying vec3 vWorldDirection;

				#include <common>

				void main() {

					vec3 direction = normalize( vWorldDirection );

					vec2 sampleUV = equirectUv( direction );

					gl_FragColor = texture2D( tEquirect, sampleUV );

				}
			`},i=new he(5,5,5),r=new Ce({name:"CubemapFromEquirect",uniforms:Ki(n.uniforms),vertexShader:n.vertexShader,fragmentShader:n.fragmentShader,side:We,blending:_n});r.uniforms.tEquirect.value=e;let a=new ut(i,r),o=e.minFilter;return e.minFilter===Ai&&(e.minFilter=Ge),new Mo(1,10,this).update(t,a),e.minFilter=o,a.geometry.dispose(),a.material.dispose(),this}clear(t,e=!0,n=!0,i=!0){let r=t.getRenderTarget();for(let a=0;a<6;a++)t.setRenderTarget(this,a),t.clear(e,n,i);t.setRenderTarget(r)}};function hg(s){let t=new WeakMap,e=new WeakMap,n=null;function i(d,f=!1){return d==null?null:f?a(d):r(d)}function r(d){if(d&&d.isTexture){let f=d.mapping;if(f===wo||f===To)if(t.has(d)){let p=t.get(d).texture;return o(p,d.mapping)}else{let p=d.image;if(p&&p.height>0){let y=new ml(p.height);return y.fromEquirectangularTexture(s,d),t.set(d,y),d.addEventListener("dispose",c),o(y.texture,d.mapping)}else return null}}return d}function a(d){if(d&&d.isTexture){let f=d.mapping,p=f===wo||f===To,y=f===Ti||f===$i;if(p||y){let g=e.get(d),m=g!==void 0?g.texture.pmremVersion:0;if(d.isRenderTargetTexture&&d.pmremVersion!==m)return n===null&&(n=new Gs(s)),g=p?n.fromEquirectangular(d,g):n.fromCubemap(d,g),g.texture.pmremVersion=d.pmremVersion,e.set(d,g),g.texture;if(g!==void 0)return g.texture;{let S=d.image;return p&&S&&S.height>0||y&&S&&l(S)?(n===null&&(n=new Gs(s)),g=p?n.fromEquirectangular(d):n.fromCubemap(d),g.texture.pmremVersion=d.pmremVersion,e.set(d,g),d.addEventListener("dispose",h),g.texture):null}}}return d}function o(d,f){return f===wo?d.mapping=Ti:f===To&&(d.mapping=$i),d}function l(d){let f=0,p=6;for(let y=0;y<p;y++)d[y]!==void 0&&f++;return f===p}function c(d){let f=d.target;f.removeEventListener("dispose",c);let p=t.get(f);p!==void 0&&(t.delete(f),p.dispose())}function h(d){let f=d.target;f.removeEventListener("dispose",h);let p=e.get(f);p!==void 0&&(e.delete(f),p.dispose())}function u(){t=new WeakMap,e=new WeakMap,n!==null&&(n.dispose(),n=null)}return{get:i,dispose:u}}function ug(s){let t={};function e(n){if(t[n]!==void 0)return t[n];let i=s.getExtension(n);return t[n]=i,i}return{has:function(n){return e(n)!==null},init:function(){e("EXT_color_buffer_float"),e("WEBGL_clip_cull_distance"),e("OES_texture_float_linear"),e("EXT_color_buffer_half_float"),e("WEBGL_multisampled_render_to_texture"),e("WEBGL_render_shared_exponent")},get:function(n){let i=e(n);return i===null&&ki("WebGLRenderer: "+n+" extension not supported."),i}}}function dg(s,t,e,n){let i={},r=new WeakMap;function a(u){let d=u.target;d.index!==null&&t.remove(d.index);for(let p in d.attributes)t.remove(d.attributes[p]);d.removeEventListener("dispose",a),delete i[d.id];let f=r.get(d);f&&(t.remove(f),r.delete(d)),n.releaseStatesOfGeometry(d),d.isInstancedBufferGeometry===!0&&delete d._maxInstanceCount,e.memory.geometries--}function o(u,d){return i[d.id]===!0||(d.addEventListener("dispose",a),i[d.id]=!0,e.memory.geometries++),d}function l(u){let d=u.attributes;for(let f in d)t.update(d[f],s.ARRAY_BUFFER)}function c(u){let d=[],f=u.index,p=u.attributes.position,y=0;if(p===void 0)return;if(f!==null){let S=f.array;y=f.version;for(let T=0,v=S.length;T<v;T+=3){let b=S[T+0],w=S[T+1],P=S[T+2];d.push(b,w,w,P,P,b)}}else{let S=p.array;y=p.version;for(let T=0,v=S.length/3-1;T<v;T+=3){let b=T+0,w=T+1,P=T+2;d.push(b,w,w,P,P,b)}}let g=new(p.count>=65535?Mr:vr)(d,1);g.version=y;let m=r.get(u);m&&t.remove(m),r.set(u,g)}function h(u){let d=r.get(u);if(d){let f=u.index;f!==null&&d.version<f.version&&c(u)}else c(u);return r.get(u)}return{get:o,update:l,getWireframeAttribute:h}}function fg(s,t,e){let n;function i(u){n=u}let r,a;function o(u){r=u.type,a=u.bytesPerElement}function l(u,d){s.drawElements(n,d,r,u*a),e.update(d,n,1)}function c(u,d,f){f!==0&&(s.drawElementsInstanced(n,d,r,u*a,f),e.update(d,n,f))}function h(u,d,f){if(f===0)return;t.get("WEBGL_multi_draw").multiDrawElementsWEBGL(n,d,0,r,u,0,f);let y=0;for(let g=0;g<f;g++)y+=d[g];e.update(y,n,1)}this.setMode=i,this.setIndex=o,this.render=l,this.renderInstances=c,this.renderMultiDraw=h}function pg(s){let t={geometries:0,textures:0},e={frame:0,calls:0,triangles:0,points:0,lines:0};function n(r,a,o){switch(e.calls++,a){case s.TRIANGLES:e.triangles+=o*(r/3);break;case s.LINES:e.lines+=o*(r/2);break;case s.LINE_STRIP:e.lines+=o*(r-1);break;case s.LINE_LOOP:e.lines+=o*r;break;case s.POINTS:e.points+=o*r;break;default:Xt("WebGLInfo: Unknown draw mode:",a);break}}function i(){e.calls=0,e.triangles=0,e.points=0,e.lines=0}return{memory:t,render:e,programs:null,autoReset:!0,reset:i,update:n}}function mg(s,t,e){let n=new WeakMap,i=new Se;function r(a,o,l){let c=a.morphTargetInfluences,h=o.morphAttributes.position||o.morphAttributes.normal||o.morphAttributes.color,u=h!==void 0?h.length:0,d=n.get(o);if(d===void 0||d.count!==u){let E=function(){P.dispose(),n.delete(o),o.removeEventListener("dispose",E)};d!==void 0&&d.texture.dispose();let f=o.morphAttributes.position!==void 0,p=o.morphAttributes.normal!==void 0,y=o.morphAttributes.color!==void 0,g=o.morphAttributes.position||[],m=o.morphAttributes.normal||[],S=o.morphAttributes.color||[],T=0;f===!0&&(T=1),p===!0&&(T=2),y===!0&&(T=3);let v=o.attributes.position.count*T,b=1;v>t.maxTextureSize&&(b=Math.ceil(v/t.maxTextureSize),v=t.maxTextureSize);let w=new Float32Array(v*b*4*u),P=new xr(w,v,b,u);P.type=yn,P.needsUpdate=!0;let _=T*4;for(let C=0;C<u;C++){let I=g[C],U=m[C],k=S[C],L=v*b*4*C;for(let B=0;B<I.count;B++){let W=B*_;f===!0&&(i.fromBufferAttribute(I,B),w[L+W+0]=i.x,w[L+W+1]=i.y,w[L+W+2]=i.z,w[L+W+3]=0),p===!0&&(i.fromBufferAttribute(U,B),w[L+W+4]=i.x,w[L+W+5]=i.y,w[L+W+6]=i.z,w[L+W+7]=0),y===!0&&(i.fromBufferAttribute(k,B),w[L+W+8]=i.x,w[L+W+9]=i.y,w[L+W+10]=i.z,w[L+W+11]=k.itemSize===4?i.w:1)}}d={count:u,texture:P,size:new rt(v,b)},n.set(o,d),o.addEventListener("dispose",E)}if(a.isInstancedMesh===!0&&a.morphTexture!==null)l.getUniforms().setValue(s,"morphTexture",a.morphTexture,e);else{let f=0;for(let y=0;y<c.length;y++)f+=c[y];let p=o.morphTargetsRelative?1:1-f;l.getUniforms().setValue(s,"morphTargetBaseInfluence",p),l.getUniforms().setValue(s,"morphTargetInfluences",c)}l.getUniforms().setValue(s,"morphTargetsTexture",d.texture,e),l.getUniforms().setValue(s,"morphTargetsTextureSize",d.size)}return{update:r}}function gg(s,t,e,n,i){let r=new WeakMap;function a(c){let h=i.render.frame,u=c.geometry,d=t.get(c,u);if(r.get(d)!==h&&(t.update(d),r.set(d,h)),c.isInstancedMesh&&(c.hasEventListener("dispose",l)===!1&&c.addEventListener("dispose",l),r.get(c)!==h&&(e.update(c.instanceMatrix,s.ARRAY_BUFFER),c.instanceColor!==null&&e.update(c.instanceColor,s.ARRAY_BUFFER),r.set(c,h))),c.isSkinnedMesh){let f=c.skeleton;r.get(f)!==h&&(f.update(),r.set(f,h))}return d}function o(){r=new WeakMap}function l(c){let h=c.target;h.removeEventListener("dispose",l),n.releaseStatesOfObject(h),e.remove(h.instanceMatrix),h.instanceColor!==null&&e.remove(h.instanceColor)}return{update:a,dispose:o}}var xg={[Wr]:"LINEAR_TONE_MAPPING",[Xr]:"REINHARD_TONE_MAPPING",[qr]:"CINEON_TONE_MAPPING",[Ji]:"ACES_FILMIC_TONE_MAPPING",[Zr]:"AGX_TONE_MAPPING",[Jr]:"NEUTRAL_TONE_MAPPING",[Yr]:"CUSTOM_TONE_MAPPING"};function _g(s,t,e,n,i,r){let a=new Te(t,e,{type:s,depthBuffer:i,stencilBuffer:r,samples:n?4:0,storeMultisampledDepthBuffer:!1,storeMultisampledStencilBuffer:!1,resolveDepthBuffer:!1,resolveStencilBuffer:!1}),o=null,l=null,c=new xe;c.setAttribute("position",new Zt([-1,3,0,-1,-1,0,3,-1,0],3)),c.setAttribute("uv",new Zt([0,2,0,0,2,0],2));let h=new Ds({uniforms:{tDiffuse:{value:null}},vertexShader:`
			precision highp float;

			uniform mat4 modelViewMatrix;
			uniform mat4 projectionMatrix;

			attribute vec3 position;
			attribute vec2 uv;

			varying vec2 vUv;

			void main() {
				vUv = uv;
				gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 );
			}`,fragmentShader:`
			precision highp float;

			uniform sampler2D tDiffuse;

			varying vec2 vUv;

			#include <tonemapping_pars_fragment>
			#include <colorspace_pars_fragment>

			void main() {
				gl_FragColor = texture2D( tDiffuse, vUv );

				#ifdef LINEAR_TONE_MAPPING
					gl_FragColor.rgb = LinearToneMapping( gl_FragColor.rgb );
				#elif defined( REINHARD_TONE_MAPPING )
					gl_FragColor.rgb = ReinhardToneMapping( gl_FragColor.rgb );
				#elif defined( CINEON_TONE_MAPPING )
					gl_FragColor.rgb = CineonToneMapping( gl_FragColor.rgb );
				#elif defined( ACES_FILMIC_TONE_MAPPING )
					gl_FragColor.rgb = ACESFilmicToneMapping( gl_FragColor.rgb );
				#elif defined( AGX_TONE_MAPPING )
					gl_FragColor.rgb = AgXToneMapping( gl_FragColor.rgb );
				#elif defined( NEUTRAL_TONE_MAPPING )
					gl_FragColor.rgb = NeutralToneMapping( gl_FragColor.rgb );
				#elif defined( CUSTOM_TONE_MAPPING )
					gl_FragColor.rgb = CustomToneMapping( gl_FragColor.rgb );
				#endif

				#ifdef SRGB_TRANSFER
					gl_FragColor = sRGBTransferOETF( gl_FragColor );
				#endif
			}`,depthTest:!1,depthWrite:!1}),u=new ut(c,h),d=new Ei(-1,1,1,-1,0,1),f=null,p=null,y=!1,g,m=null,S=[],T=!1;this.setSize=function(v,b){a.setSize(v,b),o!==null&&o.setSize(v,b),l!==null&&l.setSize(v,b);for(let w=0;w<S.length;w++){let P=S[w];P.setSize&&P.setSize(v,b)}},this.setEffects=function(v){S=v,T=S.length>0&&S[0].isRenderPass===!0;let b=a.width,w=a.height;S.length>0&&o===null&&(o=new Te(b,w,{type:Le,depthBuffer:!1,stencilBuffer:!1}),l=new Te(b,w,{type:Le,depthBuffer:!1,stencilBuffer:!1}));for(let P=0;P<S.length;P++){let _=S[P];_.setSize&&_.setSize(b,w)}},this.begin=function(v,b){if(y||v.toneMapping===Ln&&S.length===0)return!1;if(m=b,b!==null){let w=b.width,P=b.height;(a.width!==w||a.height!==P)&&this.setSize(w,P)}return T===!1&&v.setRenderTarget(a),g=v.toneMapping,v.toneMapping=Ln,!0},this.hasRenderPass=function(){return T},this.end=function(v,b){v.toneMapping=g,y=!0;let w=a,P=o;for(let _=0;_<S.length;_++){let E=S[_];E.enabled!==!1&&(E.render(v,P,w,b),E.needsSwap!==!1&&(w=P,P=P===o?l:o))}if(f!==v.outputColorSpace||p!==v.toneMapping){f=v.outputColorSpace,p=v.toneMapping,h.defines={},ne.getTransfer(f)===ce&&(h.defines.SRGB_TRANSFER="");let _=xg[p];_&&(h.defines[_]=""),h.needsUpdate=!0}h.uniforms.tDiffuse.value=w.texture,v.setRenderTarget(m),v.render(u,d),m=null,y=!1},this.isCompositing=function(){return y},this.dispose=function(){a.dispose(),o!==null&&o.dispose(),l!==null&&l.dispose(),c.dispose(),h.dispose()}}var _d=new nn,jc=new _i(1,1),yd=new xr,vd=new to,Md=new wr,ju=[],td=[],ed=new Float32Array(16),nd=new Float32Array(9),id=new Float32Array(4);function Ws(s,t,e){let n=s[0];if(n<=0||n>0)return s;let i=t*e,r=ju[i];if(r===void 0&&(r=new Float32Array(i),ju[i]=r),t!==0){n.toArray(r,0);for(let a=1,o=0;a!==t;++a)o+=e,s[a].toArray(r,o)}return r}function De(s,t){if(s.length!==t.length)return!1;for(let e=0,n=s.length;e<n;e++)if(s[e]!==t[e])return!1;return!0}function Ue(s,t){for(let e=0,n=t.length;e<n;e++)s[e]=t[e]}function _l(s,t){let e=td[t];e===void 0&&(e=new Int32Array(t),td[t]=e);for(let n=0;n!==t;++n)e[n]=s.allocateTextureUnit();return e}function yg(s,t){let e=this.cache;e[0]!==t&&(s.uniform1f(this.addr,t),e[0]=t)}function vg(s,t){let e=this.cache;if(t.x!==void 0)(e[0]!==t.x||e[1]!==t.y)&&(s.uniform2f(this.addr,t.x,t.y),e[0]=t.x,e[1]=t.y);else{if(De(e,t))return;s.uniform2fv(this.addr,t),Ue(e,t)}}function Mg(s,t){let e=this.cache;if(t.x!==void 0)(e[0]!==t.x||e[1]!==t.y||e[2]!==t.z)&&(s.uniform3f(this.addr,t.x,t.y,t.z),e[0]=t.x,e[1]=t.y,e[2]=t.z);else if(t.r!==void 0)(e[0]!==t.r||e[1]!==t.g||e[2]!==t.b)&&(s.uniform3f(this.addr,t.r,t.g,t.b),e[0]=t.r,e[1]=t.g,e[2]=t.b);else{if(De(e,t))return;s.uniform3fv(this.addr,t),Ue(e,t)}}function Sg(s,t){let e=this.cache;if(t.x!==void 0)(e[0]!==t.x||e[1]!==t.y||e[2]!==t.z||e[3]!==t.w)&&(s.uniform4f(this.addr,t.x,t.y,t.z,t.w),e[0]=t.x,e[1]=t.y,e[2]=t.z,e[3]=t.w);else{if(De(e,t))return;s.uniform4fv(this.addr,t),Ue(e,t)}}function bg(s,t){let e=this.cache,n=t.elements;if(n===void 0){if(De(e,t))return;s.uniformMatrix2fv(this.addr,!1,t),Ue(e,t)}else{if(De(e,n))return;id.set(n),s.uniformMatrix2fv(this.addr,!1,id),Ue(e,n)}}function Eg(s,t){let e=this.cache,n=t.elements;if(n===void 0){if(De(e,t))return;s.uniformMatrix3fv(this.addr,!1,t),Ue(e,t)}else{if(De(e,n))return;nd.set(n),s.uniformMatrix3fv(this.addr,!1,nd),Ue(e,n)}}function wg(s,t){let e=this.cache,n=t.elements;if(n===void 0){if(De(e,t))return;s.uniformMatrix4fv(this.addr,!1,t),Ue(e,t)}else{if(De(e,n))return;ed.set(n),s.uniformMatrix4fv(this.addr,!1,ed),Ue(e,n)}}function Tg(s,t){let e=this.cache;e[0]!==t&&(s.uniform1i(this.addr,t),e[0]=t)}function Ag(s,t){let e=this.cache;if(t.x!==void 0)(e[0]!==t.x||e[1]!==t.y)&&(s.uniform2i(this.addr,t.x,t.y),e[0]=t.x,e[1]=t.y);else{if(De(e,t))return;s.uniform2iv(this.addr,t),Ue(e,t)}}function Rg(s,t){let e=this.cache;if(t.x!==void 0)(e[0]!==t.x||e[1]!==t.y||e[2]!==t.z)&&(s.uniform3i(this.addr,t.x,t.y,t.z),e[0]=t.x,e[1]=t.y,e[2]=t.z);else{if(De(e,t))return;s.uniform3iv(this.addr,t),Ue(e,t)}}function Cg(s,t){let e=this.cache;if(t.x!==void 0)(e[0]!==t.x||e[1]!==t.y||e[2]!==t.z||e[3]!==t.w)&&(s.uniform4i(this.addr,t.x,t.y,t.z,t.w),e[0]=t.x,e[1]=t.y,e[2]=t.z,e[3]=t.w);else{if(De(e,t))return;s.uniform4iv(this.addr,t),Ue(e,t)}}function Pg(s,t){let e=this.cache;e[0]!==t&&(s.uniform1ui(this.addr,t),e[0]=t)}function Ig(s,t){let e=this.cache;if(t.x!==void 0)(e[0]!==t.x||e[1]!==t.y)&&(s.uniform2ui(this.addr,t.x,t.y),e[0]=t.x,e[1]=t.y);else{if(De(e,t))return;s.uniform2uiv(this.addr,t),Ue(e,t)}}function Lg(s,t){let e=this.cache;if(t.x!==void 0)(e[0]!==t.x||e[1]!==t.y||e[2]!==t.z)&&(s.uniform3ui(this.addr,t.x,t.y,t.z),e[0]=t.x,e[1]=t.y,e[2]=t.z);else{if(De(e,t))return;s.uniform3uiv(this.addr,t),Ue(e,t)}}function Dg(s,t){let e=this.cache;if(t.x!==void 0)(e[0]!==t.x||e[1]!==t.y||e[2]!==t.z||e[3]!==t.w)&&(s.uniform4ui(this.addr,t.x,t.y,t.z,t.w),e[0]=t.x,e[1]=t.y,e[2]=t.z,e[3]=t.w);else{if(De(e,t))return;s.uniform4uiv(this.addr,t),Ue(e,t)}}function Ug(s,t,e){let n=this.cache,i=e.allocateTextureUnit();n[0]!==i&&(s.uniform1i(this.addr,i),n[0]=i);let r;this.type===s.SAMPLER_2D_SHADOW?(jc.compareFunction=e.isReversedDepthBuffer()?dl:ul,r=jc):r=_d,e.setTexture2D(t||r,i)}function Ng(s,t,e){let n=this.cache,i=e.allocateTextureUnit();n[0]!==i&&(s.uniform1i(this.addr,i),n[0]=i),e.setTexture3D(t||vd,i)}function Fg(s,t,e){let n=this.cache,i=e.allocateTextureUnit();n[0]!==i&&(s.uniform1i(this.addr,i),n[0]=i),e.setTextureCube(t||Md,i)}function Bg(s,t,e){let n=this.cache,i=e.allocateTextureUnit();n[0]!==i&&(s.uniform1i(this.addr,i),n[0]=i),e.setTexture2DArray(t||yd,i)}function Og(s){switch(s){case 5126:return yg;case 35664:return vg;case 35665:return Mg;case 35666:return Sg;case 35674:return bg;case 35675:return Eg;case 35676:return wg;case 5124:case 35670:return Tg;case 35667:case 35671:return Ag;case 35668:case 35672:return Rg;case 35669:case 35673:return Cg;case 5125:return Pg;case 36294:return Ig;case 36295:return Lg;case 36296:return Dg;case 35678:case 36198:case 36298:case 36306:case 35682:return Ug;case 35679:case 36299:case 36307:return Ng;case 35680:case 36300:case 36308:case 36293:return Fg;case 36289:case 36303:case 36311:case 36292:return Bg}}function kg(s,t){s.uniform1fv(this.addr,t)}function zg(s,t){let e=Ws(t,this.size,2);s.uniform2fv(this.addr,e)}function Hg(s,t){let e=Ws(t,this.size,3);s.uniform3fv(this.addr,e)}function Vg(s,t){let e=Ws(t,this.size,4);s.uniform4fv(this.addr,e)}function Gg(s,t){let e=Ws(t,this.size,4);s.uniformMatrix2fv(this.addr,!1,e)}function Wg(s,t){let e=Ws(t,this.size,9);s.uniformMatrix3fv(this.addr,!1,e)}function Xg(s,t){let e=Ws(t,this.size,16);s.uniformMatrix4fv(this.addr,!1,e)}function qg(s,t){s.uniform1iv(this.addr,t)}function Yg(s,t){s.uniform2iv(this.addr,t)}function Zg(s,t){s.uniform3iv(this.addr,t)}function Jg(s,t){s.uniform4iv(this.addr,t)}function $g(s,t){s.uniform1uiv(this.addr,t)}function Kg(s,t){s.uniform2uiv(this.addr,t)}function Qg(s,t){s.uniform3uiv(this.addr,t)}function jg(s,t){s.uniform4uiv(this.addr,t)}function tx(s,t,e){let n=this.cache,i=t.length,r=_l(e,i);De(n,r)||(s.uniform1iv(this.addr,r),Ue(n,r));let a;this.type===s.SAMPLER_2D_SHADOW?a=jc:a=_d;for(let o=0;o!==i;++o)e.setTexture2D(t[o]||a,r[o])}function ex(s,t,e){let n=this.cache,i=t.length,r=_l(e,i);De(n,r)||(s.uniform1iv(this.addr,r),Ue(n,r));for(let a=0;a!==i;++a)e.setTexture3D(t[a]||vd,r[a])}function nx(s,t,e){let n=this.cache,i=t.length,r=_l(e,i);De(n,r)||(s.uniform1iv(this.addr,r),Ue(n,r));for(let a=0;a!==i;++a)e.setTextureCube(t[a]||Md,r[a])}function ix(s,t,e){let n=this.cache,i=t.length,r=_l(e,i);De(n,r)||(s.uniform1iv(this.addr,r),Ue(n,r));for(let a=0;a!==i;++a)e.setTexture2DArray(t[a]||yd,r[a])}function sx(s){switch(s){case 5126:return kg;case 35664:return zg;case 35665:return Hg;case 35666:return Vg;case 35674:return Gg;case 35675:return Wg;case 35676:return Xg;case 5124:case 35670:return qg;case 35667:case 35671:return Yg;case 35668:case 35672:return Zg;case 35669:case 35673:return Jg;case 5125:return $g;case 36294:return Kg;case 36295:return Qg;case 36296:return jg;case 35678:case 36198:case 36298:case 36306:case 35682:return tx;case 35679:case 36299:case 36307:return ex;case 35680:case 36300:case 36308:case 36293:return nx;case 36289:case 36303:case 36311:case 36292:return ix}}var th=class{constructor(t,e,n){this.id=t,this.addr=n,this.cache=[],this.type=e.type,this.setValue=Og(e.type)}},eh=class{constructor(t,e,n){this.id=t,this.addr=n,this.cache=[],this.type=e.type,this.size=e.size,this.setValue=sx(e.type)}},nh=class{constructor(t){this.id=t,this.seq=[],this.map={}}setValue(t,e,n){let i=this.seq;for(let r=0,a=i.length;r!==a;++r){let o=i[r];o.setValue(t,e[o.id],n)}}},Kc=/(\w+)(\])?(\[|\.)?/g;function sd(s,t){s.seq.push(t),s.map[t.id]=t}function rx(s,t,e){let n=s.name,i=n.length;for(Kc.lastIndex=0;;){let r=Kc.exec(n),a=Kc.lastIndex,o=r[1],l=r[2]==="]",c=r[3];if(l&&(o=o|0),c===void 0||c==="["&&a+2===i){sd(e,c===void 0?new th(o,s,t):new eh(o,s,t));break}else{let u=e.map[o];u===void 0&&(u=new nh(o),sd(e,u)),e=u}}}var Vs=class{constructor(t,e){this.seq=[],this.map={};let n=t.getProgramParameter(e,t.ACTIVE_UNIFORMS);for(let a=0;a<n;++a){let o=t.getActiveUniform(e,a),l=t.getUniformLocation(e,o.name);rx(o,l,this)}let i=[],r=[];for(let a of this.seq)a.type===t.SAMPLER_2D_SHADOW||a.type===t.SAMPLER_CUBE_SHADOW||a.type===t.SAMPLER_2D_ARRAY_SHADOW?i.push(a):r.push(a);i.length>0&&(this.seq=i.concat(r))}setValue(t,e,n,i){let r=this.map[e];r!==void 0&&r.setValue(t,n,i)}setOptional(t,e,n){let i=e[n];i!==void 0&&this.setValue(t,n,i)}static upload(t,e,n,i){for(let r=0,a=e.length;r!==a;++r){let o=e[r],l=n[o.id];l.needsUpdate!==!1&&o.setValue(t,l.value,i)}}static seqWithValue(t,e){let n=[];for(let i=0,r=t.length;i!==r;++i){let a=t[i];a.id in e&&n.push(a)}return n}};function rd(s,t,e){let n=s.createShader(t);return s.shaderSource(n,e),s.compileShader(n),n}var ax=37297,ox=0;function lx(s,t){let e=s.split(`
`),n=[],i=Math.max(t-6,0),r=Math.min(t+6,e.length);for(let a=i;a<r;a++){let o=a+1;n.push(`${o===t?">":" "} ${o}: ${e[a]}`)}return n.join(`
`)}var ad=new $t;function cx(s){ne._getMatrix(ad,ne.workingColorSpace,s);let t=`mat3( ${ad.elements.map(e=>e.toFixed(4))} )`;switch(ne.getTransfer(s)){case fr:return[t,"LinearTransferOETF"];case ce:return[t,"sRGBTransferOETF"];default:return qt("WebGLProgram: Unsupported color space: ",s),[t,"LinearTransferOETF"]}}function od(s,t,e){let n=s.getShaderParameter(t,s.COMPILE_STATUS),r=(s.getShaderInfoLog(t)||"").trim();if(n&&r==="")return"";let a=/ERROR: 0:(\d+)/.exec(r);if(a){let o=parseInt(a[1]);return e.toUpperCase()+`

`+r+`

`+lx(s.getShaderSource(t),o)}else return r}function hx(s,t){let e=cx(t);return[`vec4 ${s}( vec4 value ) {`,`	return ${e[1]}( vec4( value.rgb * ${e[0]}, value.a ) );`,"}"].join(`
`)}var ux={[Wr]:"Linear",[Xr]:"Reinhard",[qr]:"Cineon",[Ji]:"ACESFilmic",[Zr]:"AgX",[Jr]:"Neutral",[Yr]:"Custom"};function dx(s,t){let e=ux[t];return e===void 0?(qt("WebGLProgram: Unsupported toneMapping:",t),"vec3 "+s+"( vec3 color ) { return LinearToneMapping( color ); }"):"vec3 "+s+"( vec3 color ) { return "+e+"ToneMapping( color ); }"}var pl=new D;function fx(){ne.getLuminanceCoefficients(pl);let s=pl.x.toFixed(4),t=pl.y.toFixed(4),e=pl.z.toFixed(4);return["float luminance( const in vec3 rgb ) {",`	const vec3 weights = vec3( ${s}, ${t}, ${e} );`,"	return dot( weights, rgb );","}"].join(`
`)}function px(s){return[s.extensionClipCullDistance?"#extension GL_ANGLE_clip_cull_distance : require":"",s.extensionMultiDraw?"#extension GL_ANGLE_multi_draw : require":""].filter(oa).join(`
`)}function mx(s){let t=[];for(let e in s){let n=s[e];n!==!1&&t.push("#define "+e+" "+n)}return t.join(`
`)}function gx(s,t){let e={},n=s.getProgramParameter(t,s.ACTIVE_ATTRIBUTES);for(let i=0;i<n;i++){let r=s.getActiveAttrib(t,i),a=r.name,o=1;r.type===s.FLOAT_MAT2&&(o=2),r.type===s.FLOAT_MAT3&&(o=3),r.type===s.FLOAT_MAT4&&(o=4),e[a]={type:r.type,location:s.getAttribLocation(t,a),locationSize:o}}return e}function oa(s){return s!==""}function ld(s,t){let e=t.numSpotLightShadows+t.numSpotLightMaps-t.numSpotLightShadowsWithMaps;return s.replace(/NUM_SUN_LIGHTS/g,t.numSunLights).replace(/NUM_DIR_LIGHTS/g,t.numDirLights).replace(/NUM_SPOT_LIGHTS/g,t.numSpotLights).replace(/NUM_SPOT_LIGHT_MAPS/g,t.numSpotLightMaps).replace(/NUM_SPOT_LIGHT_COORDS/g,e).replace(/NUM_RECT_AREA_LIGHTS/g,t.numRectAreaLights).replace(/NUM_POINT_LIGHTS/g,t.numPointLights).replace(/NUM_HEMI_LIGHTS/g,t.numHemiLights).replace(/NUM_SUN_LIGHT_SHADOWS/g,t.numSunLightShadows).replace(/NUM_DIR_LIGHT_SHADOWS/g,t.numDirLightShadows).replace(/NUM_SPOT_LIGHT_SHADOWS_WITH_MAPS/g,t.numSpotLightShadowsWithMaps).replace(/NUM_SPOT_LIGHT_SHADOWS/g,t.numSpotLightShadows).replace(/NUM_POINT_LIGHT_SHADOWS/g,t.numPointLightShadows)}function cd(s,t){return s.replace(/NUM_CLIPPING_PLANES/g,t.numClippingPlanes).replace(/UNION_CLIPPING_PLANES/g,t.numClippingPlanes-t.numClipIntersection)}var xx=/^[ \t]*#include +<([\w\d./]+)>/gm;function ih(s){return s.replace(xx,yx)}var _x=new Map;function yx(s,t){let e=ee[t];if(e===void 0){let n=_x.get(t);if(n!==void 0)e=ee[n],qt('WebGLRenderer: Shader chunk "%s" has been deprecated. Use "%s" instead.',t,n);else throw new Error("THREE.WebGLProgram: Can not resolve #include <"+t+">")}return ih(e)}var vx=/#pragma unroll_loop_start\s+for\s*\(\s*int\s+i\s*=\s*(\d+)\s*;\s*i\s*<\s*(\d+)\s*;\s*i\s*\+\+\s*\)\s*{([\s\S]+?)}\s+#pragma unroll_loop_end/g;function hd(s){return s.replace(vx,Mx)}function Mx(s,t,e,n){let i="";for(let r=parseInt(t);r<parseInt(e);r++)i+=n.replace(/\[\s*i\s*\]/g,"[ "+r+" ]").replace(/UNROLLED_LOOP_INDEX/g,r);return i}function ud(s){let t=`precision ${s.precision} float;
	precision ${s.precision} int;
	precision ${s.precision} sampler2D;
	precision ${s.precision} samplerCube;
	precision ${s.precision} sampler3D;
	precision ${s.precision} sampler2DArray;
	precision ${s.precision} sampler2DShadow;
	precision ${s.precision} samplerCubeShadow;
	precision ${s.precision} sampler2DArrayShadow;
	precision ${s.precision} isampler2D;
	precision ${s.precision} isampler3D;
	precision ${s.precision} isamplerCube;
	precision ${s.precision} isampler2DArray;
	precision ${s.precision} usampler2D;
	precision ${s.precision} usampler3D;
	precision ${s.precision} usamplerCube;
	precision ${s.precision} usampler2DArray;
	`;return s.precision==="highp"?t+=`
#define HIGH_PRECISION`:s.precision==="mediump"?t+=`
#define MEDIUM_PRECISION`:s.precision==="lowp"&&(t+=`
#define LOW_PRECISION`),t}var Sx={[qi]:"SHADOWMAP_TYPE_PCF",[Fs]:"SHADOWMAP_TYPE_VSM"};function bx(s){return Sx[s.shadowMapType]||"SHADOWMAP_TYPE_BASIC"}var Ex={[Ti]:"ENVMAP_TYPE_CUBE",[$i]:"ENVMAP_TYPE_CUBE",[$r]:"ENVMAP_TYPE_CUBE_UV"};function wx(s){return s.envMap===!1?"ENVMAP_TYPE_CUBE":Ex[s.envMapMode]||"ENVMAP_TYPE_CUBE"}var Tx={[$i]:"ENVMAP_MODE_REFRACTION"};function Ax(s){return s.envMap===!1?"ENVMAP_MODE_REFLECTION":Tx[s.envMapMode]||"ENVMAP_MODE_REFLECTION"}var Rx={[Eo]:"ENVMAP_BLENDING_MULTIPLY",[wu]:"ENVMAP_BLENDING_MIX",[Tu]:"ENVMAP_BLENDING_ADD"};function Cx(s){return s.envMap===!1?"ENVMAP_BLENDING_NONE":Rx[s.combine]||"ENVMAP_BLENDING_NONE"}function Px(s){let t=s.envMapCubeUVHeight;if(t===null)return null;let e=Math.log2(t)-2,n=1/t;return{texelWidth:1/(3*Math.max(Math.pow(2,e),112)),texelHeight:n,maxMip:e}}function Ix(s,t,e,n){let i=s.getContext(),r=e.defines,a=e.vertexShader,o=e.fragmentShader,l=bx(e),c=wx(e),h=Ax(e),u=Cx(e),d=Px(e),f=px(e),p=mx(r),y=i.createProgram(),g,m,S=e.glslVersion?"#version "+e.glslVersion+`
`:"";e.isRawShaderMaterial?(g=["#define SHADER_TYPE "+e.shaderType,"#define SHADER_NAME "+e.shaderName,p].filter(oa).join(`
`),g.length>0&&(g+=`
`),m=["#define SHADER_TYPE "+e.shaderType,"#define SHADER_NAME "+e.shaderName,p].filter(oa).join(`
`),m.length>0&&(m+=`
`)):(g=[ud(e),"#define SHADER_TYPE "+e.shaderType,"#define SHADER_NAME "+e.shaderName,p,e.extensionClipCullDistance?"#define USE_CLIP_DISTANCE":"",e.batching?"#define USE_BATCHING":"",e.batchingColor?"#define USE_BATCHING_COLOR":"",e.instancing?"#define USE_INSTANCING":"",e.instancingColor?"#define USE_INSTANCING_COLOR":"",e.instancingMorph?"#define USE_INSTANCING_MORPH":"",e.useFog&&e.fog?"#define USE_FOG":"",e.useFog&&e.fogExp2?"#define FOG_EXP2":"",e.map?"#define USE_MAP":"",e.envMap?"#define USE_ENVMAP":"",e.envMap?"#define "+h:"",e.lightMap?"#define USE_LIGHTMAP":"",e.aoMap?"#define USE_AOMAP":"",e.bumpMap?"#define USE_BUMPMAP":"",e.normalMap?"#define USE_NORMALMAP":"",e.normalMapObjectSpace?"#define USE_NORMALMAP_OBJECTSPACE":"",e.normalMapTangentSpace?"#define USE_NORMALMAP_TANGENTSPACE":"",e.displacementMap?"#define USE_DISPLACEMENTMAP":"",e.emissiveMap?"#define USE_EMISSIVEMAP":"",e.anisotropy?"#define USE_ANISOTROPY":"",e.anisotropyMap?"#define USE_ANISOTROPYMAP":"",e.clearcoatMap?"#define USE_CLEARCOATMAP":"",e.clearcoatRoughnessMap?"#define USE_CLEARCOAT_ROUGHNESSMAP":"",e.clearcoatNormalMap?"#define USE_CLEARCOAT_NORMALMAP":"",e.iridescenceMap?"#define USE_IRIDESCENCEMAP":"",e.iridescenceThicknessMap?"#define USE_IRIDESCENCE_THICKNESSMAP":"",e.specularMap?"#define USE_SPECULARMAP":"",e.specularColorMap?"#define USE_SPECULAR_COLORMAP":"",e.specularIntensityMap?"#define USE_SPECULAR_INTENSITYMAP":"",e.roughnessMap?"#define USE_ROUGHNESSMAP":"",e.metalnessMap?"#define USE_METALNESSMAP":"",e.alphaMap?"#define USE_ALPHAMAP":"",e.alphaHash?"#define USE_ALPHAHASH":"",e.transmission?"#define USE_TRANSMISSION":"",e.transmissionMap?"#define USE_TRANSMISSIONMAP":"",e.thicknessMap?"#define USE_THICKNESSMAP":"",e.sheenColorMap?"#define USE_SHEEN_COLORMAP":"",e.sheenRoughnessMap?"#define USE_SHEEN_ROUGHNESSMAP":"",e.mapUv?"#define MAP_UV "+e.mapUv:"",e.alphaMapUv?"#define ALPHAMAP_UV "+e.alphaMapUv:"",e.lightMapUv?"#define LIGHTMAP_UV "+e.lightMapUv:"",e.aoMapUv?"#define AOMAP_UV "+e.aoMapUv:"",e.emissiveMapUv?"#define EMISSIVEMAP_UV "+e.emissiveMapUv:"",e.bumpMapUv?"#define BUMPMAP_UV "+e.bumpMapUv:"",e.normalMapUv?"#define NORMALMAP_UV "+e.normalMapUv:"",e.displacementMapUv?"#define DISPLACEMENTMAP_UV "+e.displacementMapUv:"",e.metalnessMapUv?"#define METALNESSMAP_UV "+e.metalnessMapUv:"",e.roughnessMapUv?"#define ROUGHNESSMAP_UV "+e.roughnessMapUv:"",e.anisotropyMapUv?"#define ANISOTROPYMAP_UV "+e.anisotropyMapUv:"",e.clearcoatMapUv?"#define CLEARCOATMAP_UV "+e.clearcoatMapUv:"",e.clearcoatNormalMapUv?"#define CLEARCOAT_NORMALMAP_UV "+e.clearcoatNormalMapUv:"",e.clearcoatRoughnessMapUv?"#define CLEARCOAT_ROUGHNESSMAP_UV "+e.clearcoatRoughnessMapUv:"",e.iridescenceMapUv?"#define IRIDESCENCEMAP_UV "+e.iridescenceMapUv:"",e.iridescenceThicknessMapUv?"#define IRIDESCENCE_THICKNESSMAP_UV "+e.iridescenceThicknessMapUv:"",e.sheenColorMapUv?"#define SHEEN_COLORMAP_UV "+e.sheenColorMapUv:"",e.sheenRoughnessMapUv?"#define SHEEN_ROUGHNESSMAP_UV "+e.sheenRoughnessMapUv:"",e.specularMapUv?"#define SPECULARMAP_UV "+e.specularMapUv:"",e.specularColorMapUv?"#define SPECULAR_COLORMAP_UV "+e.specularColorMapUv:"",e.specularIntensityMapUv?"#define SPECULAR_INTENSITYMAP_UV "+e.specularIntensityMapUv:"",e.transmissionMapUv?"#define TRANSMISSIONMAP_UV "+e.transmissionMapUv:"",e.thicknessMapUv?"#define THICKNESSMAP_UV "+e.thicknessMapUv:"",e.vertexTangents&&e.flatShading===!1?"#define USE_TANGENT":"",e.vertexNormals?"#define HAS_NORMAL":"",e.vertexColors?"#define USE_COLOR":"",e.vertexAlphas?"#define USE_COLOR_ALPHA":"",e.vertexUv1s?"#define USE_UV1":"",e.vertexUv2s?"#define USE_UV2":"",e.vertexUv3s?"#define USE_UV3":"",e.pointsUvs?"#define USE_POINTS_UV":"",e.flatShading?"#define FLAT_SHADED":"",e.skinning?"#define USE_SKINNING":"",e.morphTargets?"#define USE_MORPHTARGETS":"",e.morphNormals&&e.flatShading===!1?"#define USE_MORPHNORMALS":"",e.morphColors?"#define USE_MORPHCOLORS":"",e.morphTargetsCount>0?"#define MORPHTARGETS_TEXTURE_STRIDE "+e.morphTextureStride:"",e.morphTargetsCount>0?"#define MORPHTARGETS_COUNT "+e.morphTargetsCount:"",e.doubleSided?"#define DOUBLE_SIDED":"",e.flipSided?"#define FLIP_SIDED":"",e.shadowMapEnabled?"#define USE_SHADOWMAP":"",e.shadowMapEnabled?"#define "+l:"",e.sizeAttenuation?"#define USE_SIZEATTENUATION":"",e.numLightProbes>0?"#define USE_LIGHT_PROBES":"",e.logarithmicDepthBuffer?"#define USE_LOGARITHMIC_DEPTH_BUFFER":"",e.reversedDepthBuffer?"#define USE_REVERSED_DEPTH_BUFFER":"","uniform mat4 modelMatrix;","uniform mat4 modelViewMatrix;","uniform mat4 projectionMatrix;","uniform mat4 viewMatrix;","uniform mat3 normalMatrix;","uniform vec3 cameraPosition;","uniform bool isOrthographic;","#ifdef USE_INSTANCING","	attribute mat4 instanceMatrix;","#endif","#ifdef USE_INSTANCING_COLOR","	attribute vec3 instanceColor;","#endif","#ifdef USE_INSTANCING_MORPH","	uniform sampler2D morphTexture;","#endif","attribute vec3 position;","attribute vec3 normal;","attribute vec2 uv;","#ifdef USE_UV1","	attribute vec2 uv1;","#endif","#ifdef USE_UV2","	attribute vec2 uv2;","#endif","#ifdef USE_UV3","	attribute vec2 uv3;","#endif","#ifdef USE_TANGENT","	attribute vec4 tangent;","#endif","#if defined( USE_COLOR_ALPHA )","	attribute vec4 color;","#elif defined( USE_COLOR )","	attribute vec3 color;","#endif","#ifdef USE_SKINNING","	attribute vec4 skinIndex;","	attribute vec4 skinWeight;","#endif",`
`].filter(oa).join(`
`),m=[ud(e),"#define SHADER_TYPE "+e.shaderType,"#define SHADER_NAME "+e.shaderName,p,e.useFog&&e.fog?"#define USE_FOG":"",e.useFog&&e.fogExp2?"#define FOG_EXP2":"",e.alphaToCoverage?"#define ALPHA_TO_COVERAGE":"",e.map?"#define USE_MAP":"",e.matcap?"#define USE_MATCAP":"",e.envMap?"#define USE_ENVMAP":"",e.envMap?"#define "+c:"",e.envMap?"#define "+h:"",e.envMap?"#define "+u:"",d?"#define CUBEUV_TEXEL_WIDTH "+d.texelWidth:"",d?"#define CUBEUV_TEXEL_HEIGHT "+d.texelHeight:"",d?"#define CUBEUV_MAX_MIP "+d.maxMip+".0":"",e.lightMap?"#define USE_LIGHTMAP":"",e.aoMap?"#define USE_AOMAP":"",e.bumpMap?"#define USE_BUMPMAP":"",e.normalMap?"#define USE_NORMALMAP":"",e.normalMapObjectSpace?"#define USE_NORMALMAP_OBJECTSPACE":"",e.normalMapTangentSpace?"#define USE_NORMALMAP_TANGENTSPACE":"",e.packedNormalMap?"#define USE_PACKED_NORMALMAP":"",e.emissiveMap?"#define USE_EMISSIVEMAP":"",e.anisotropy?"#define USE_ANISOTROPY":"",e.anisotropyMap?"#define USE_ANISOTROPYMAP":"",e.clearcoat?"#define USE_CLEARCOAT":"",e.clearcoatMap?"#define USE_CLEARCOATMAP":"",e.clearcoatRoughnessMap?"#define USE_CLEARCOAT_ROUGHNESSMAP":"",e.clearcoatNormalMap?"#define USE_CLEARCOAT_NORMALMAP":"",e.dispersion?"#define USE_DISPERSION":"",e.retroreflection?"#define USE_RETROREFLECTION":"",e.iridescence?"#define USE_IRIDESCENCE":"",e.iridescenceMap?"#define USE_IRIDESCENCEMAP":"",e.iridescenceThicknessMap?"#define USE_IRIDESCENCE_THICKNESSMAP":"",e.specularMap?"#define USE_SPECULARMAP":"",e.specularColorMap?"#define USE_SPECULAR_COLORMAP":"",e.specularIntensityMap?"#define USE_SPECULAR_INTENSITYMAP":"",e.roughnessMap?"#define USE_ROUGHNESSMAP":"",e.metalnessMap?"#define USE_METALNESSMAP":"",e.alphaMap?"#define USE_ALPHAMAP":"",e.alphaTest?"#define USE_ALPHATEST":"",e.alphaHash?"#define USE_ALPHAHASH":"",e.sheen?"#define USE_SHEEN":"",e.sheenColorMap?"#define USE_SHEEN_COLORMAP":"",e.sheenRoughnessMap?"#define USE_SHEEN_ROUGHNESSMAP":"",e.transmission?"#define USE_TRANSMISSION":"",e.transmissionMap?"#define USE_TRANSMISSIONMAP":"",e.thicknessMap?"#define USE_THICKNESSMAP":"",e.vertexTangents&&e.flatShading===!1?"#define USE_TANGENT":"",e.vertexColors||e.instancingColor?"#define USE_COLOR":"",e.vertexAlphas||e.batchingColor?"#define USE_COLOR_ALPHA":"",e.vertexUv1s?"#define USE_UV1":"",e.vertexUv2s?"#define USE_UV2":"",e.vertexUv3s?"#define USE_UV3":"",e.pointsUvs?"#define USE_POINTS_UV":"",e.gradientMap?"#define USE_GRADIENTMAP":"",e.flatShading?"#define FLAT_SHADED":"",e.doubleSided?"#define DOUBLE_SIDED":"",e.flipSided?"#define FLIP_SIDED":"",e.shadowMapEnabled?"#define USE_SHADOWMAP":"",e.shadowMapEnabled?"#define "+l:"",e.premultipliedAlpha?"#define PREMULTIPLIED_ALPHA":"",e.numLightProbes>0?"#define USE_LIGHT_PROBES":"",e.numLightProbeGrids>0?"#define USE_LIGHT_PROBES_GRID":"",e.decodeVideoTexture?"#define DECODE_VIDEO_TEXTURE":"",e.decodeVideoTextureEmissive?"#define DECODE_VIDEO_TEXTURE_EMISSIVE":"",e.logarithmicDepthBuffer?"#define USE_LOGARITHMIC_DEPTH_BUFFER":"",e.reversedDepthBuffer?"#define USE_REVERSED_DEPTH_BUFFER":"","uniform mat4 viewMatrix;","uniform vec3 cameraPosition;","uniform bool isOrthographic;",e.toneMapping!==Ln?"#define TONE_MAPPING":"",e.toneMapping!==Ln?ee.tonemapping_pars_fragment:"",e.toneMapping!==Ln?dx("toneMapping",e.toneMapping):"",e.dithering?"#define DITHERING":"",e.opaque?"#define OPAQUE":"",ee.colorspace_pars_fragment,hx("linearToOutputTexel",e.outputColorSpace),fx(),e.useDepthPacking?"#define DEPTH_PACKING "+e.depthPacking:"",`
`].filter(oa).join(`
`)),a=ih(a),a=ld(a,e),a=cd(a,e),o=ih(o),o=ld(o,e),o=cd(o,e),a=hd(a),o=hd(o),e.isRawShaderMaterial!==!0&&(S=`#version 300 es
`,g=[f,"#define attribute in","#define varying out","#define texture2D texture"].join(`
`)+`
`+g,m=["#define varying in",e.glslVersion===Fc?"":"layout(location = 0) out highp vec4 pc_fragColor;",e.glslVersion===Fc?"":"#define gl_FragColor pc_fragColor","#define gl_FragDepthEXT gl_FragDepth","#define texture2D texture","#define textureCube texture","#define texture2DProj textureProj","#define texture2DLodEXT textureLod","#define texture2DProjLodEXT textureProjLod","#define textureCubeLodEXT textureLod","#define texture2DGradEXT textureGrad","#define texture2DProjGradEXT textureProjGrad","#define textureCubeGradEXT textureGrad"].join(`
`)+`
`+m);let T=S+g+a,v=S+m+o,b=rd(i,i.VERTEX_SHADER,T),w=rd(i,i.FRAGMENT_SHADER,v);i.attachShader(y,b),i.attachShader(y,w),e.index0AttributeName!==void 0?i.bindAttribLocation(y,0,e.index0AttributeName):e.hasPositionAttribute===!0&&i.bindAttribLocation(y,0,"position"),i.linkProgram(y);function P(I){if(s.debug.checkShaderErrors){let U=i.getProgramInfoLog(y)||"",k=i.getShaderInfoLog(b)||"",L=i.getShaderInfoLog(w)||"",B=U.trim(),W=k.trim(),X=L.trim(),st=!0,Z=!0;if(i.getProgramParameter(y,i.LINK_STATUS)===!1)if(st=!1,typeof s.debug.onShaderError=="function")s.debug.onShaderError(i,y,b,w);else{let Q=od(i,b,"vertex"),it=od(i,w,"fragment");Xt("WebGLProgram: Shader Error "+i.getError()+" - VALIDATE_STATUS "+i.getProgramParameter(y,i.VALIDATE_STATUS)+`

Material Name: `+I.name+`
Material Type: `+I.type+`

Program Info Log: `+B+`
`+Q+`
`+it)}else B!==""?qt("WebGLProgram: Program Info Log:",B):(W===""||X==="")&&(Z=!1);Z&&(I.diagnostics={runnable:st,programLog:B,vertexShader:{log:W,prefix:g},fragmentShader:{log:X,prefix:m}})}i.deleteShader(b),i.deleteShader(w),_=new Vs(i,y),E=gx(i,y)}let _;this.getUniforms=function(){return _===void 0&&P(this),_};let E;this.getAttributes=function(){return E===void 0&&P(this),E};let C=e.rendererExtensionParallelShaderCompile===!1;return this.isReady=function(){return C===!1&&(C=i.getProgramParameter(y,ax)),C},this.destroy=function(){n.releaseStatesOfProgram(this),i.deleteProgram(y),this.program=void 0},this.type=e.shaderType,this.name=e.shaderName,this.id=ox++,this.cacheKey=t,this.usedTimes=1,this.program=y,this.vertexShader=b,this.fragmentShader=w,this}var Lx=0,sh=class{constructor(){this.shaderCache=new Map,this.materialCache=new Map}update(t,e,n){let i=this._getShaderCacheForMaterial(t);return i.has(e)===!1&&(i.add(e),e.usedTimes++),i.has(n)===!1&&(i.add(n),n.usedTimes++),this}remove(t){let e=this.materialCache.get(t);for(let n of e)n.usedTimes--,n.usedTimes===0&&this.shaderCache.delete(n.code);return this.materialCache.delete(t),this}getVertexShaderStage(t){return this._getShaderStage(t.vertexShader)}getFragmentShaderStage(t){return this._getShaderStage(t.fragmentShader)}dispose(){this.shaderCache.clear(),this.materialCache.clear()}_getShaderCacheForMaterial(t){let e=this.materialCache,n=e.get(t);return n===void 0&&(n=new Set,e.set(t,n)),n}_getShaderStage(t){let e=this.shaderCache,n=e.get(t);return n===void 0&&(n=new rh(t),e.set(t,n)),n}},rh=class{constructor(t){this.id=Lx++,this.code=t,this.usedTimes=0}};function Dx(s){return s===Ci||s===na||s===ia}function Ux(s,t,e,n,i,r){let a=new _r,o=new sh,l=new Set,c=[],h=new Map,u=n.logarithmicDepthBuffer,d=n.precision,f={MeshDepthMaterial:"depth",MeshDistanceMaterial:"distance",MeshNormalMaterial:"normal",MeshBasicMaterial:"basic",MeshLambertMaterial:"lambert",MeshPhongMaterial:"phong",MeshToonMaterial:"toon",MeshStandardMaterial:"physical",MeshPhysicalMaterial:"physical",MeshMatcapMaterial:"matcap",LineBasicMaterial:"basic",LineDashedMaterial:"dashed",PointsMaterial:"points",ShadowMaterial:"shadow",SpriteMaterial:"sprite"};function p(_){return l.add(_),_===0?"uv":`uv${_}`}function y(_,E,C,I,U,k){let L=I.fog,B=U.geometry,W=_.isMeshStandardMaterial||_.isMeshLambertMaterial||_.isMeshPhongMaterial?I.environment:null,X=_.isMeshStandardMaterial||_.isMeshLambertMaterial&&!_.envMap||_.isMeshPhongMaterial&&!_.envMap,st=t.get(_.envMap||W,X),Z=st&&st.mapping===$r?st.image.height:null,Q=f[_.type];_.precision!==null&&(d=n.getMaxPrecision(_.precision),d!==_.precision&&qt("WebGLProgram.getParameters:",_.precision,"not supported, using",d,"instead."));let it=B.morphAttributes.position||B.morphAttributes.normal||B.morphAttributes.color,nt=it!==void 0?it.length:0,ct=0;B.morphAttributes.position!==void 0&&(ct=1),B.morphAttributes.normal!==void 0&&(ct=2),B.morphAttributes.color!==void 0&&(ct=3);let Yt,Jt,Qt,J;if(Q){let _e=Yn[Q];Yt=_e.vertexShader,Jt=_e.fragmentShader}else{Yt=_.vertexShader,Jt=_.fragmentShader;let _e=o.getVertexShaderStage(_),ue=o.getFragmentShaderStage(_);o.update(_,_e,ue),Qt=_e.id,J=ue.id}let $=s.getRenderTarget(),mt=s.state.buffers.depth.getReversed(),Lt=U.isInstancedMesh===!0,gt=U.isBatchedMesh===!0,Bt=!!_.map,ae=!!_.matcap,j=!!st,ot=!!_.aoMap,ht=!!_.lightMap,dt=!!_.bumpMap&&_.wireframe===!1,pt=!!_.normalMap,Ht=!!_.displacementMap,kt=!!_.emissiveMap,Gt=!!_.metalnessMap,z=!!_.roughnessMap,A=_.anisotropy>0,Tt=_.clearcoat>0,Et=_.dispersion>0,R=_.retroreflectivity>0,x=_.iridescence>0,O=_.sheen>0,G=_.transmission>0,q=A&&!!_.anisotropyMap,lt=Tt&&!!_.clearcoatMap,ft=Tt&&!!_.clearcoatNormalMap,K=Tt&&!!_.clearcoatRoughnessMap,et=x&&!!_.iridescenceMap,xt=x&&!!_.iridescenceThicknessMap,Ut=O&&!!_.sheenColorMap,_t=O&&!!_.sheenRoughnessMap,yt=!!_.specularMap,Ot=!!_.specularColorMap,Wt=!!_.specularIntensityMap,jt=G&&!!_.transmissionMap,F=G&&!!_.thicknessMap,vt=!!_.gradientMap,tt=!!_.alphaMap,Mt=_.alphaTest>0,At=!!_.alphaHash,at=!!_.extensions,zt=Ln;_.toneMapped&&($===null||$.isXRRenderTarget===!0)&&(zt=s.toneMapping);let Dt={shaderID:Q,shaderType:_.type,shaderName:_.name,vertexShader:Yt,fragmentShader:Jt,defines:_.defines,customVertexShaderID:Qt,customFragmentShaderID:J,isRawShaderMaterial:_.isRawShaderMaterial===!0,glslVersion:_.glslVersion,precision:d,batching:gt,batchingColor:gt&&U._colorsTexture!==null,instancing:Lt,instancingColor:Lt&&U.instanceColor!==null,instancingMorph:Lt&&U.morphTexture!==null,outputColorSpace:$===null?s.outputColorSpace:$.isXRRenderTarget===!0?$.texture.colorSpace:ne.workingColorSpace,alphaToCoverage:!!_.alphaToCoverage,map:Bt,matcap:ae,envMap:j,envMapMode:j&&st.mapping,envMapCubeUVHeight:Z,aoMap:ot,lightMap:ht,bumpMap:dt,normalMap:pt,displacementMap:Ht,emissiveMap:kt,normalMapObjectSpace:pt&&_.normalMapType===Cu,normalMapTangentSpace:pt&&_.normalMapType===sa,packedNormalMap:pt&&_.normalMapType===sa&&Dx(_.normalMap.format),metalnessMap:Gt,roughnessMap:z,anisotropy:A,anisotropyMap:q,clearcoat:Tt,clearcoatMap:lt,clearcoatNormalMap:ft,clearcoatRoughnessMap:K,dispersion:Et,retroreflection:R,iridescence:x,iridescenceMap:et,iridescenceThicknessMap:xt,sheen:O,sheenColorMap:Ut,sheenRoughnessMap:_t,specularMap:yt,specularColorMap:Ot,specularIntensityMap:Wt,transmission:G,transmissionMap:jt,thicknessMap:F,gradientMap:vt,opaque:_.transparent===!1&&_.blending===Bs&&_.alphaToCoverage===!1,alphaMap:tt,alphaTest:Mt,alphaHash:At,combine:_.combine,mapUv:Bt&&p(_.map.channel),aoMapUv:ot&&p(_.aoMap.channel),lightMapUv:ht&&p(_.lightMap.channel),bumpMapUv:dt&&p(_.bumpMap.channel),normalMapUv:pt&&p(_.normalMap.channel),displacementMapUv:Ht&&p(_.displacementMap.channel),emissiveMapUv:kt&&p(_.emissiveMap.channel),metalnessMapUv:Gt&&p(_.metalnessMap.channel),roughnessMapUv:z&&p(_.roughnessMap.channel),anisotropyMapUv:q&&p(_.anisotropyMap.channel),clearcoatMapUv:lt&&p(_.clearcoatMap.channel),clearcoatNormalMapUv:ft&&p(_.clearcoatNormalMap.channel),clearcoatRoughnessMapUv:K&&p(_.clearcoatRoughnessMap.channel),iridescenceMapUv:et&&p(_.iridescenceMap.channel),iridescenceThicknessMapUv:xt&&p(_.iridescenceThicknessMap.channel),sheenColorMapUv:Ut&&p(_.sheenColorMap.channel),sheenRoughnessMapUv:_t&&p(_.sheenRoughnessMap.channel),specularMapUv:yt&&p(_.specularMap.channel),specularColorMapUv:Ot&&p(_.specularColorMap.channel),specularIntensityMapUv:Wt&&p(_.specularIntensityMap.channel),transmissionMapUv:jt&&p(_.transmissionMap.channel),thicknessMapUv:F&&p(_.thicknessMap.channel),alphaMapUv:tt&&p(_.alphaMap.channel),vertexTangents:!!B.attributes.tangent&&(pt||A),vertexNormals:!!B.attributes.normal,vertexColors:_.vertexColors,vertexAlphas:_.vertexColors===!0&&!!B.attributes.color&&B.attributes.color.itemSize===4,pointsUvs:U.isPoints===!0&&!!B.attributes.uv&&(Bt||tt),fog:!!L,useFog:_.fog===!0,fogExp2:!!L&&L.isFogExp2,flatShading:_.wireframe===!1&&(_.flatShading===!0||B.attributes.normal===void 0&&pt===!1&&(_.isMeshLambertMaterial||_.isMeshPhongMaterial||_.isMeshStandardMaterial||_.isMeshPhysicalMaterial)),sizeAttenuation:_.sizeAttenuation===!0,logarithmicDepthBuffer:u,reversedDepthBuffer:mt,skinning:U.isSkinnedMesh===!0,hasPositionAttribute:B.attributes.position!==void 0,morphTargets:B.morphAttributes.position!==void 0,morphNormals:B.morphAttributes.normal!==void 0,morphColors:B.morphAttributes.color!==void 0,morphTargetsCount:nt,morphTextureStride:ct,numSunLights:E.sun.length,numDirLights:E.directional.length,numPointLights:E.point.length,numSpotLights:E.spot.length,numSpotLightMaps:E.spotLightMap.length,numRectAreaLights:E.rectArea.length,numHemiLights:E.hemi.length,numSunLightShadows:E.sunShadowMap.length,numDirLightShadows:E.directionalShadowMap.length,numPointLightShadows:E.pointShadowMap.length,numSpotLightShadows:E.spotShadowMap.length,numSpotLightShadowsWithMaps:E.numSpotLightShadowsWithMaps,numLightProbes:E.numLightProbes,numLightProbeGrids:k.length,numClippingPlanes:r.numPlanes,numClipIntersection:r.numIntersection,dithering:_.dithering,shadowMapEnabled:s.shadowMap.enabled&&C.length>0,shadowMapType:s.shadowMap.type,toneMapping:zt,decodeVideoTexture:Bt&&_.map.isVideoTexture===!0&&ne.getTransfer(_.map.colorSpace)===ce,decodeVideoTextureEmissive:kt&&_.emissiveMap.isVideoTexture===!0&&ne.getTransfer(_.emissiveMap.colorSpace)===ce,premultipliedAlpha:_.premultipliedAlpha,doubleSided:_.side===He,flipSided:_.side===We,useDepthPacking:_.depthPacking>=0,depthPacking:_.depthPacking||0,index0AttributeName:_.index0AttributeName,extensionClipCullDistance:at&&_.extensions.clipCullDistance===!0&&e.has("WEBGL_clip_cull_distance"),extensionMultiDraw:(at&&_.extensions.multiDraw===!0||gt)&&e.has("WEBGL_multi_draw"),rendererExtensionParallelShaderCompile:e.has("KHR_parallel_shader_compile"),customProgramCacheKey:_.customProgramCacheKey()};return Dt.vertexUv1s=l.has(1),Dt.vertexUv2s=l.has(2),Dt.vertexUv3s=l.has(3),l.clear(),Dt}function g(_){let E=[];if(_.shaderID?E.push(_.shaderID):(E.push(_.customVertexShaderID),E.push(_.customFragmentShaderID)),_.defines!==void 0)for(let C in _.defines)E.push(C),E.push(_.defines[C]);return _.isRawShaderMaterial===!1&&(m(E,_),S(E,_),E.push(s.outputColorSpace)),E.push(_.customProgramCacheKey),E.join()}function m(_,E){_.push(E.precision),_.push(E.outputColorSpace),_.push(E.envMapMode),_.push(E.envMapCubeUVHeight),_.push(E.mapUv),_.push(E.alphaMapUv),_.push(E.lightMapUv),_.push(E.aoMapUv),_.push(E.bumpMapUv),_.push(E.normalMapUv),_.push(E.displacementMapUv),_.push(E.emissiveMapUv),_.push(E.metalnessMapUv),_.push(E.roughnessMapUv),_.push(E.anisotropyMapUv),_.push(E.clearcoatMapUv),_.push(E.clearcoatNormalMapUv),_.push(E.clearcoatRoughnessMapUv),_.push(E.iridescenceMapUv),_.push(E.iridescenceThicknessMapUv),_.push(E.sheenColorMapUv),_.push(E.sheenRoughnessMapUv),_.push(E.specularMapUv),_.push(E.specularColorMapUv),_.push(E.specularIntensityMapUv),_.push(E.transmissionMapUv),_.push(E.thicknessMapUv),_.push(E.combine),_.push(E.fogExp2),_.push(E.sizeAttenuation),_.push(E.morphTargetsCount),_.push(E.morphAttributeCount),_.push(E.numSunLights),_.push(E.numDirLights),_.push(E.numPointLights),_.push(E.numSpotLights),_.push(E.numSpotLightMaps),_.push(E.numHemiLights),_.push(E.numRectAreaLights),_.push(E.numSunLightShadows),_.push(E.numDirLightShadows),_.push(E.numPointLightShadows),_.push(E.numSpotLightShadows),_.push(E.numSpotLightShadowsWithMaps),_.push(E.numLightProbes),_.push(E.shadowMapType),_.push(E.toneMapping),_.push(E.numClippingPlanes),_.push(E.numClipIntersection),_.push(E.depthPacking)}function S(_,E){a.disableAll(),E.instancing&&a.enable(0),E.instancingColor&&a.enable(1),E.instancingMorph&&a.enable(2),E.matcap&&a.enable(3),E.envMap&&a.enable(4),E.normalMapObjectSpace&&a.enable(5),E.normalMapTangentSpace&&a.enable(6),E.clearcoat&&a.enable(7),E.iridescence&&a.enable(8),E.alphaTest&&a.enable(9),E.vertexColors&&a.enable(10),E.vertexAlphas&&a.enable(11),E.vertexUv1s&&a.enable(12),E.vertexUv2s&&a.enable(13),E.vertexUv3s&&a.enable(14),E.vertexTangents&&a.enable(15),E.anisotropy&&a.enable(16),E.alphaHash&&a.enable(17),E.batching&&a.enable(18),E.dispersion&&a.enable(19),E.retroreflection&&a.enable(24),E.batchingColor&&a.enable(20),E.gradientMap&&a.enable(21),E.packedNormalMap&&a.enable(22),E.vertexNormals&&a.enable(23),_.push(a.mask),a.disableAll(),E.fog&&a.enable(0),E.useFog&&a.enable(1),E.flatShading&&a.enable(2),E.logarithmicDepthBuffer&&a.enable(3),E.reversedDepthBuffer&&a.enable(4),E.skinning&&a.enable(5),E.morphTargets&&a.enable(6),E.morphNormals&&a.enable(7),E.morphColors&&a.enable(8),E.premultipliedAlpha&&a.enable(9),E.shadowMapEnabled&&a.enable(10),E.doubleSided&&a.enable(11),E.flipSided&&a.enable(12),E.useDepthPacking&&a.enable(13),E.dithering&&a.enable(14),E.transmission&&a.enable(15),E.sheen&&a.enable(16),E.opaque&&a.enable(17),E.pointsUvs&&a.enable(18),E.decodeVideoTexture&&a.enable(19),E.decodeVideoTextureEmissive&&a.enable(20),E.alphaToCoverage&&a.enable(21),E.numLightProbeGrids>0&&a.enable(22),E.hasPositionAttribute&&a.enable(23),_.push(a.mask)}function T(_){let E=f[_.type],C;if(E){let I=Yn[E];C=si.clone(I.uniforms)}else C=_.uniforms;return C}function v(_,E){let C=h.get(E);return C!==void 0?++C.usedTimes:(C=new Ix(s,E,_,i),c.push(C),h.set(E,C)),C}function b(_){if(--_.usedTimes===0){let E=c.indexOf(_);c[E]=c[c.length-1],c.pop(),h.delete(_.cacheKey),_.destroy()}}function w(_){o.remove(_)}function P(){o.dispose()}return{getParameters:y,getProgramCacheKey:g,getUniforms:T,acquireProgram:v,releaseProgram:b,releaseShaderCache:w,programs:c,dispose:P}}function Nx(){let s=new WeakMap;function t(a){return s.has(a)}function e(a){let o=s.get(a);return o===void 0&&(o={},s.set(a,o)),o}function n(a){s.delete(a)}function i(a,o,l){s.get(a)[o]=l}function r(){s=new WeakMap}return{has:t,get:e,remove:n,update:i,dispose:r}}function Fx(s,t){return s.groupOrder!==t.groupOrder?s.groupOrder-t.groupOrder:s.renderOrder!==t.renderOrder?s.renderOrder-t.renderOrder:s.material.id!==t.material.id?s.material.id-t.material.id:s.materialVariant!==t.materialVariant?s.materialVariant-t.materialVariant:s.z!==t.z?s.z-t.z:s.id-t.id}function dd(s,t){return s.groupOrder!==t.groupOrder?s.groupOrder-t.groupOrder:s.renderOrder!==t.renderOrder?s.renderOrder-t.renderOrder:s.z!==t.z?t.z-s.z:s.id-t.id}function fd(){let s=[],t=0,e=[],n=[],i=[];function r(){t=0,e.length=0,n.length=0,i.length=0}function a(d){let f=0;return d.isInstancedMesh&&(f+=2),d.isSkinnedMesh&&(f+=1),f}function o(d,f,p,y,g,m){let S=s[t];return S===void 0?(S={id:d.id,object:d,geometry:f,material:p,materialVariant:a(d),groupOrder:y,renderOrder:d.renderOrder,z:g,group:m},s[t]=S):(S.id=d.id,S.object=d,S.geometry=f,S.material=p,S.materialVariant=a(d),S.groupOrder=y,S.renderOrder=d.renderOrder,S.z=g,S.group=m),t++,S}function l(d,f,p,y,g,m,S){S.reversedDepth===!0&&(g=-g);let T=o(d,f,p,y,g,m);p.transmission>0?n.push(T):p.transparent===!0?i.push(T):e.push(T)}function c(d,f,p,y,g,m){let S=o(d,f,p,y,g,m);p.transmission>0?n.unshift(S):p.transparent===!0?i.unshift(S):e.unshift(S)}function h(d,f){e.length>1&&e.sort(d||Fx),n.length>1&&n.sort(f||dd),i.length>1&&i.sort(f||dd)}function u(){for(let d=t,f=s.length;d<f;d++){let p=s[d];if(p.id===null)break;p.id=null,p.object=null,p.geometry=null,p.material=null,p.group=null}}return{opaque:e,transmissive:n,transparent:i,init:r,push:l,unshift:c,finish:u,sort:h}}function Bx(){let s=new WeakMap;function t(n,i){let r=s.get(n),a;return r===void 0?(a=new fd,s.set(n,[a])):i>=r.length?(a=new fd,r.push(a)):a=r[i],a}function e(){s=new WeakMap}return{get:t,dispose:e}}function Ox(){let s={};return{get:function(t){if(s[t.id]!==void 0)return s[t.id];let e;switch(t.type){case"SunLight":case"DirectionalLight":e={direction:new D,color:new Ft};break;case"SpotLight":e={position:new D,direction:new D,color:new Ft,distance:0,coneCos:0,penumbraCos:0,decay:0};break;case"PointLight":e={position:new D,color:new Ft,distance:0,decay:0};break;case"HemisphereLight":e={direction:new D,skyColor:new Ft,groundColor:new Ft};break;case"RectAreaLight":e={color:new Ft,position:new D,halfWidth:new D,halfHeight:new D};break}return s[t.id]=e,e}}}function kx(){let s={};return{get:function(t){if(s[t.id]!==void 0)return s[t.id];let e;switch(t.type){case"SunLight":case"DirectionalLight":e={shadowIntensity:1,shadowBias:0,shadowNormalBias:0,shadowRadius:1,shadowMapSize:new rt};break;case"SpotLight":e={shadowIntensity:1,shadowBias:0,shadowNormalBias:0,shadowRadius:1,shadowMapSize:new rt};break;case"PointLight":e={shadowIntensity:1,shadowBias:0,shadowNormalBias:0,shadowRadius:1,shadowMapSize:new rt,shadowCameraNear:1,shadowCameraFar:1e3};break}return s[t.id]=e,e}}}var zx=0;function Hx(s,t){return(t.castShadow?2:0)-(s.castShadow?2:0)+(t.map?1:0)-(s.map?1:0)}function Vx(s){let t=new Ox,e=kx(),n={version:0,hash:{sunLength:-1,directionalLength:-1,pointLength:-1,spotLength:-1,rectAreaLength:-1,hemiLength:-1,numSunShadows:-1,numDirectionalShadows:-1,numPointShadows:-1,numSpotShadows:-1,numSpotMaps:-1,numLightProbes:-1},ambient:[0,0,0],probe:[],sun:[],sunShadow:[],sunShadowMap:[],sunShadowMatrix:[],sunShadowCascade:[],directional:[],directionalShadow:[],directionalShadowMap:[],directionalShadowMatrix:[],spot:[],spotLightMap:[],spotShadow:[],spotShadowMap:[],spotLightMatrix:[],rectArea:[],rectAreaLTC1:null,rectAreaLTC2:null,point:[],pointShadow:[],pointShadowMap:[],pointShadowMatrix:[],hemi:[],numSpotLightShadowsWithMaps:0,numLightProbes:0};for(let c=0;c<9;c++)n.probe.push(new D);let i=new D,r=new fe,a=new fe;function o(c){let h=0,u=0,d=0;for(let U=0;U<9;U++)n.probe[U].set(0,0,0);let f=0,p=0,y=0,g=0,m=0,S=0,T=0,v=0,b=0,w=0,P=0,_=0,E=0,C=0;c.sort(Hx);for(let U=0,k=c.length;U<k;U++){let L=c[U],B=L.color,W=L.intensity,X=L.distance,st=null;if(L.shadow&&L.shadow.map&&(L.shadow.map.texture.format===Ci?st=L.shadow.map.texture:st=L.shadow.map.depthTexture||L.shadow.map.texture),L.isAmbientLight)h+=B.r*W,u+=B.g*W,d+=B.b*W;else if(L.isLightProbe){for(let Z=0;Z<9;Z++)n.probe[Z].addScaledVector(L.sh.coefficients[Z],W);C++}else if(L.isSunLight){let Z=t.get(L);if(Z.color.copy(L.color).multiplyScalar(L.intensity),L.castShadow){let Q=L.shadow,it=e.get(L);it.shadowIntensity=Q.intensity,it.shadowBias=Q.bias,it.shadowNormalBias=Q.normalBias,it.shadowRadius=Q.radius,it.shadowMapSize.copy(Q.mapSize).multiply(Q.getFrameExtents()),n.sunShadow[p]=it,n.sunShadowMap[p]=st;let nt=Q.getViewportCount();for(let ct=0;ct<nt;ct++)n.sunShadowMatrix[y+ct]=Q.getMatrix(ct),n.sunShadowCascade[y+ct]=Q._cascadeData[ct];y+=nt,p++}n.sun[f]=Z,f++}else if(L.isDirectionalLight){let Z=t.get(L);if(Z.color.copy(L.color).multiplyScalar(L.intensity),L.castShadow){let Q=L.shadow,it=e.get(L);it.shadowIntensity=Q.intensity,it.shadowBias=Q.bias,it.shadowNormalBias=Q.normalBias,it.shadowRadius=Q.radius,it.shadowMapSize=Q.mapSize,n.directionalShadow[g]=it,n.directionalShadowMap[g]=st,n.directionalShadowMatrix[g]=L.shadow.matrix,b++}n.directional[g]=Z,g++}else if(L.isSpotLight){let Z=t.get(L);Z.position.setFromMatrixPosition(L.matrixWorld),Z.color.copy(B).multiplyScalar(W),Z.distance=X,Z.coneCos=Math.cos(L.angle),Z.penumbraCos=Math.cos(L.angle*(1-L.penumbra)),Z.decay=L.decay,n.spot[S]=Z;let Q=L.shadow;if(L.map&&(n.spotLightMap[_]=L.map,_++,Q.updateMatrices(L),L.castShadow&&E++),n.spotLightMatrix[S]=Q.matrix,L.castShadow){let it=e.get(L);it.shadowIntensity=Q.intensity,it.shadowBias=Q.bias,it.shadowNormalBias=Q.normalBias,it.shadowRadius=Q.radius,it.shadowMapSize=Q.mapSize,n.spotShadow[S]=it,n.spotShadowMap[S]=st,P++}S++}else if(L.isRectAreaLight){let Z=t.get(L);Z.color.copy(B).multiplyScalar(W),Z.halfWidth.set(L.width*.5,0,0),Z.halfHeight.set(0,L.height*.5,0),n.rectArea[T]=Z,T++}else if(L.isPointLight){let Z=t.get(L);if(Z.color.copy(L.color).multiplyScalar(L.intensity),Z.distance=L.distance,Z.decay=L.decay,L.castShadow){let Q=L.shadow,it=e.get(L);it.shadowIntensity=Q.intensity,it.shadowBias=Q.bias,it.shadowNormalBias=Q.normalBias,it.shadowRadius=Q.radius,it.shadowMapSize=Q.mapSize,it.shadowCameraNear=Q.camera.near,it.shadowCameraFar=Q.camera.far,n.pointShadow[m]=it,n.pointShadowMap[m]=st,n.pointShadowMatrix[m]=L.shadow.matrix,w++}n.point[m]=Z,m++}else if(L.isHemisphereLight){let Z=t.get(L);Z.skyColor.copy(L.color).multiplyScalar(W),Z.groundColor.copy(L.groundColor).multiplyScalar(W),n.hemi[v]=Z,v++}}T>0&&(s.has("OES_texture_float_linear")===!0?(n.rectAreaLTC1=St.LTC_FLOAT_1,n.rectAreaLTC2=St.LTC_FLOAT_2):(n.rectAreaLTC1=St.LTC_HALF_1,n.rectAreaLTC2=St.LTC_HALF_2)),n.ambient[0]=h,n.ambient[1]=u,n.ambient[2]=d;let I=n.hash;(I.sunLength!==f||I.directionalLength!==g||I.pointLength!==m||I.spotLength!==S||I.rectAreaLength!==T||I.hemiLength!==v||I.numSunShadows!==p||I.numDirectionalShadows!==b||I.numPointShadows!==w||I.numSpotShadows!==P||I.numSpotMaps!==_||I.numLightProbes!==C)&&(n.sun.length=f,n.directional.length=g,n.spot.length=S,n.rectArea.length=T,n.point.length=m,n.hemi.length=v,n.sunShadow.length=p,n.sunShadowMap.length=p,n.sunShadowMatrix.length=y,n.sunShadowCascade.length=y,n.directionalShadow.length=b,n.directionalShadowMap.length=b,n.directionalShadowMatrix.length=b,n.pointShadow.length=w,n.pointShadowMap.length=w,n.pointShadowMatrix.length=w,n.spotShadow.length=P,n.spotShadowMap.length=P,n.spotLightMatrix.length=P+_-E,n.spotLightMap.length=_,n.numSpotLightShadowsWithMaps=E,n.numLightProbes=C,I.sunLength=f,I.directionalLength=g,I.pointLength=m,I.spotLength=S,I.rectAreaLength=T,I.hemiLength=v,I.numSunShadows=p,I.numDirectionalShadows=b,I.numPointShadows=w,I.numSpotShadows=P,I.numSpotMaps=_,I.numLightProbes=C,n.version=zx++)}function l(c,h){let u=0,d=0,f=0,p=0,y=0,g=0,m=h.matrixWorldInverse;for(let S=0,T=c.length;S<T;S++){let v=c[S];if(v.isSunLight){let b=n.sun[u];b.direction.setFromMatrixPosition(v.matrixWorld),b.direction.transformDirection(m),u++}else if(v.isDirectionalLight){let b=n.directional[d];b.direction.setFromMatrixPosition(v.matrixWorld),i.setFromMatrixPosition(v.target.matrixWorld),b.direction.sub(i),b.direction.transformDirection(m),d++}else if(v.isSpotLight){let b=n.spot[p];b.position.setFromMatrixPosition(v.matrixWorld),b.position.applyMatrix4(m),b.direction.setFromMatrixPosition(v.matrixWorld),i.setFromMatrixPosition(v.target.matrixWorld),b.direction.sub(i),b.direction.transformDirection(m),p++}else if(v.isRectAreaLight){let b=n.rectArea[y];b.position.setFromMatrixPosition(v.matrixWorld),b.position.applyMatrix4(m),a.identity(),r.copy(v.matrixWorld),r.premultiply(m),a.extractRotation(r),b.halfWidth.set(v.width*.5,0,0),b.halfHeight.set(0,v.height*.5,0),b.halfWidth.applyMatrix4(a),b.halfHeight.applyMatrix4(a),y++}else if(v.isPointLight){let b=n.point[f];b.position.setFromMatrixPosition(v.matrixWorld),b.position.applyMatrix4(m),f++}else if(v.isHemisphereLight){let b=n.hemi[g];b.direction.setFromMatrixPosition(v.matrixWorld),b.direction.transformDirection(m),g++}}}return{setup:o,setupView:l,state:n}}function pd(s){let t=new Vx(s),e=[],n=[],i=[];function r(d){u.camera=d,e.length=0,n.length=0,i.length=0}function a(d){e.push(d)}function o(d){n.push(d)}function l(d){i.push(d)}function c(){t.setup(e)}function h(d){t.setupView(e,d)}let u={lightsArray:e,shadowsArray:n,lightProbeGridArray:i,camera:null,lights:t,transmissionRenderTarget:{},textureUnits:0};return{init:r,state:u,setupLights:c,setupLightsView:h,pushLight:a,pushShadow:o,pushLightProbeGrid:l}}function Gx(s){let t=new WeakMap;function e(i,r=0){let a=t.get(i),o;return a===void 0?(o=new pd(s),t.set(i,[o])):r>=a.length?(o=new pd(s),a.push(o)):o=a[r],o}function n(){t=new WeakMap}return{get:e,dispose:n}}var Wx=`void main() {
	gl_Position = vec4( position, 1.0 );
}`,Xx=`uniform sampler2D shadow_pass;
uniform vec2 resolution;
uniform float radius;
void main() {
	const float samples = float( VSM_SAMPLES );
	float mean = 0.0;
	float squared_mean = 0.0;
	float uvStride = samples <= 1.0 ? 0.0 : 2.0 / ( samples - 1.0 );
	float uvStart = samples <= 1.0 ? 0.0 : - 1.0;
	for ( float i = 0.0; i < samples; i ++ ) {
		float uvOffset = uvStart + i * uvStride;
		#ifdef HORIZONTAL_PASS
			vec2 distribution = texture2D( shadow_pass, ( gl_FragCoord.xy + vec2( uvOffset, 0.0 ) * radius ) / resolution ).rg;
			mean += distribution.x;
			squared_mean += distribution.y * distribution.y + distribution.x * distribution.x;
		#else
			float depth = texture2D( shadow_pass, ( gl_FragCoord.xy + vec2( 0.0, uvOffset ) * radius ) / resolution ).r;
			mean += depth;
			squared_mean += depth * depth;
		#endif
	}
	mean = mean / samples;
	squared_mean = squared_mean / samples;
	float std_dev = sqrt( max( 0.0, squared_mean - mean * mean ) );
	gl_FragColor = vec4( mean, std_dev, 0.0, 1.0 );
}`,qx=[new D(1,0,0),new D(-1,0,0),new D(0,1,0),new D(0,-1,0),new D(0,0,1),new D(0,0,-1)],Yx=[new D(0,-1,0),new D(0,-1,0),new D(0,0,1),new D(0,0,-1),new D(0,-1,0),new D(0,-1,0)],md=new fe,aa=new D,Qc=new D;function Zx(s,t,e){let n=new Ps,i=new rt,r=new rt,a=new Se,o=new lo,l=new co,c={},h=e.maxTextureSize,u={[wi]:We,[We]:wi,[He]:He},d=new Ce({defines:{VSM_SAMPLES:8},uniforms:{shadow_pass:{value:null},resolution:{value:new rt},radius:{value:4}},vertexShader:Wx,fragmentShader:Xx}),f=d.clone();f.defines.HORIZONTAL_PASS=1;let p=new xe;p.setAttribute("position",new ke(new Float32Array([-1,-1,.5,3,-1,.5,-1,3,.5]),3));let y=new ut(p,d),g=this;this.enabled=!1,this.autoUpdate=!0,this.needsUpdate=!1,this.type=qi;let m=this.type;this.render=function(w,P,_){if(g.enabled===!1||g.autoUpdate===!1&&g.needsUpdate===!1||w.length===0)return;this.type===au&&(qt("WebGLShadowMap: PCFSoftShadowMap has been removed. Using PCFShadowMap instead."),this.type=qi);let E=s.getRenderTarget(),C=s.getActiveCubeFace(),I=s.getActiveMipmapLevel(),U=s.state;U.setBlending(_n),U.buffers.depth.getReversed()===!0?U.buffers.color.setClear(0,0,0,0):U.buffers.color.setClear(1,1,1,1),U.buffers.depth.setTest(!0),U.setScissorTest(!1);let k=m!==this.type;k&&P.traverse(function(L){L.material&&(Array.isArray(L.material)?L.material.forEach(B=>B.needsUpdate=!0):L.material.needsUpdate=!0)});for(let L=0,B=w.length;L<B;L++){let W=w[L],X=W.shadow;if(X===void 0){qt("WebGLShadowMap:",W,"has no shadow.");continue}if(X.autoUpdate===!1&&X.needsUpdate===!1)continue;i.copy(X.mapSize);let st=X.getFrameExtents();i.multiply(st),r.copy(X.mapSize),(i.x>h||i.y>h)&&(i.x>h&&(r.x=Math.floor(h/st.x),i.x=r.x*st.x,X.mapSize.x=r.x),i.y>h&&(r.y=Math.floor(h/st.y),i.y=r.y*st.y,X.mapSize.y=r.y));let Z=s.state.buffers.depth.getReversed();if(X.camera._reversedDepth=Z,X.map===null||k===!0){if(X.map!==null&&(X.map.depthTexture!==null&&(X.map.depthTexture.dispose(),X.map.depthTexture=null),X.map.dispose()),this.type===Fs){if(W.isPointLight){qt("WebGLShadowMap: VSM shadow maps are not supported for PointLights. Use PCF or BasicShadowMap instead.");continue}X.map=new Te(i.x,i.y,{format:Ci,type:Le,minFilter:Ge,magFilter:Ge,generateMipmaps:!1}),X.map.texture.name=W.name+".shadowMap",X.map.depthTexture=new _i(i.x,i.y,yn),X.map.depthTexture.name=W.name+".shadowMapDepth",X.map.depthTexture.format=Hn,X.map.depthTexture.compareFunction=null,X.map.depthTexture.minFilter=ze,X.map.depthTexture.magFilter=ze}else W.isPointLight?(X.map=new ml(i.x),X.map.depthTexture=new no(i.x,Dn)):(X.map=new Te(i.x,i.y),X.map.depthTexture=new _i(i.x,i.y,Dn)),X.map.depthTexture.name=W.name+".shadowMap",X.map.depthTexture.format=Hn,this.type===qi?(X.map.depthTexture.compareFunction=Z?dl:ul,X.map.depthTexture.minFilter=Ge,X.map.depthTexture.magFilter=Ge):(X.map.depthTexture.compareFunction=null,X.map.depthTexture.minFilter=ze,X.map.depthTexture.magFilter=ze);X.camera.updateProjectionMatrix()}X.map.isWebGLCubeRenderTarget!==!0&&(X.map.width!==i.x||X.map.height!==i.y)&&X.map.setSize(i.x,i.y);let Q=X.map.isWebGLCubeRenderTarget?6:X.getViewportCount();W.isPointLight!==!0&&X.updateMatrices(W,_);for(let it=0;it<Q;it++){let nt=X.getCamera(it);if(W.isPointLight){let ct=X.camera,Yt=X.matrix,Jt=W.distance||ct.far;Jt!==ct.far&&(ct.far=Jt,ct.updateProjectionMatrix()),aa.setFromMatrixPosition(W.matrixWorld),ct.position.copy(aa),Qc.copy(ct.position),Qc.add(qx[it]),ct.up.copy(Yx[it]),ct.lookAt(Qc),ct.updateMatrixWorld(),Yt.makeTranslation(-aa.x,-aa.y,-aa.z),md.multiplyMatrices(ct.projectionMatrix,ct.matrixWorldInverse),X._frustum.setFromProjectionMatrix(md,ct.coordinateSystem,ct.reversedDepth)}if(X.map.isWebGLCubeRenderTarget)s.setRenderTarget(X.map,it),s.clear();else{it===0&&(s.setRenderTarget(X.map),s.clear());let ct=X.getViewport(it);a.set(r.x*ct.x,r.y*ct.y,r.x*ct.z,r.y*ct.w),U.viewport(a)}n=X.getFrustum(it),v(P,_,nt,W,this.type)}X.isPointLightShadow!==!0&&this.type===Fs&&S(X,_),X.needsUpdate=!1}m=this.type,g.needsUpdate=!1,s.setRenderTarget(E,C,I)};function S(w,P){let _=t.update(y);d.defines.VSM_SAMPLES!==w.blurSamples&&(d.defines.VSM_SAMPLES=w.blurSamples,f.defines.VSM_SAMPLES=w.blurSamples,d.needsUpdate=!0,f.needsUpdate=!0),w.mapPass===null?w.mapPass=new Te(i.x,i.y,{format:Ci,type:Le}):(w.mapPass.width!==w.map.width||w.mapPass.height!==w.map.height)&&w.mapPass.setSize(w.map.width,w.map.height),d.uniforms.shadow_pass.value=w.map.depthTexture,d.uniforms.resolution.value.set(w.map.width,w.map.height),d.uniforms.radius.value=w.radius,s.setRenderTarget(w.mapPass),s.clear(),s.renderBufferDirect(P,null,_,d,y,null),f.uniforms.shadow_pass.value=w.mapPass.texture,f.uniforms.resolution.value.set(w.map.width,w.map.height),f.uniforms.radius.value=w.radius,s.setRenderTarget(w.map),s.clear(),s.renderBufferDirect(P,null,_,f,y,null)}function T(w,P,_,E){let C=null,I=_.isPointLight===!0?w.customDistanceMaterial:w.customDepthMaterial;if(I!==void 0)C=I;else if(C=_.isPointLight===!0?l:o,s.localClippingEnabled&&P.clipShadows===!0&&Array.isArray(P.clippingPlanes)&&P.clippingPlanes.length!==0||P.displacementMap&&P.displacementScale!==0||P.alphaMap&&P.alphaTest>0||P.map&&P.alphaTest>0||P.alphaToCoverage===!0){let U=C.uuid,k=P.uuid,L=c[U];L===void 0&&(L={},c[U]=L);let B=L[k];B===void 0&&(B=C.clone(),L[k]=B,P.addEventListener("dispose",b)),C=B}if(C.visible=P.visible,C.wireframe=P.wireframe,E===Fs?C.side=P.shadowSide!==null?P.shadowSide:P.side:C.side=P.shadowSide!==null?P.shadowSide:u[P.side],C.alphaMap=P.alphaMap,C.alphaTest=P.alphaToCoverage===!0?.5:P.alphaTest,C.map=P.map,C.clipShadows=P.clipShadows,C.clippingPlanes=P.clippingPlanes,C.clipIntersection=P.clipIntersection,C.displacementMap=P.displacementMap,C.displacementScale=P.displacementScale,C.displacementBias=P.displacementBias,C.wireframeLinewidth=P.wireframeLinewidth,C.linewidth=P.linewidth,_.isPointLight===!0&&C.isMeshDistanceMaterial===!0){let U=s.properties.get(C);U.light=_}return C}function v(w,P,_,E,C){if(w.visible===!1)return;if(w.layers.test(P.layers)&&(w.isMesh||w.isLine||w.isPoints)&&(w.castShadow||w.receiveShadow&&C===Fs)&&(!w.frustumCulled||w.intersectsFrustum(n))){w.modelViewMatrix.multiplyMatrices(_.matrixWorldInverse,w.matrixWorld);let k=t.update(w),L=w.material;if(Array.isArray(L)){let B=k.groups;for(let W=0,X=B.length;W<X;W++){let st=B[W],Z=L[st.materialIndex];if(Z&&Z.visible){let Q=T(w,Z,E,C);w.onBeforeShadow(s,w,P,_,k,Q,st),s.renderBufferDirect(_,null,k,Q,w,st),w.onAfterShadow(s,w,P,_,k,Q,st)}}}else if(L.visible){let B=T(w,L,E,C);w.onBeforeShadow(s,w,P,_,k,B,null),s.renderBufferDirect(_,null,k,B,w,null),w.onAfterShadow(s,w,P,_,k,B,null)}}let U=w.children;for(let k=0,L=U.length;k<L;k++)v(U[k],P,_,E,C)}function b(w){w.target.removeEventListener("dispose",b);for(let _ in c){let E=c[_],C=w.target.uuid;C in E&&(E[C].dispose(),delete E[C])}}}function Jx(s,t){function e(){let F=!1,vt=new Se,tt=null,Mt=new Se(0,0,0,0);return{setMask:function(At){tt!==At&&!F&&(s.colorMask(At,At,At,At),tt=At)},setLocked:function(At){F=At},setClear:function(At,at,zt,Dt,_e){_e===!0&&(At*=Dt,at*=Dt,zt*=Dt),vt.set(At,at,zt,Dt),Mt.equals(vt)===!1&&(s.clearColor(At,at,zt,Dt),Mt.copy(vt))},reset:function(){F=!1,tt=null,Mt.set(-1,0,0,0)}}}function n(){let F=!1,vt=!1,tt=null,Mt=null,At=null;return{setReversed:function(at){if(vt!==at){let zt=t.get("EXT_clip_control");at?zt.clipControlEXT(zt.LOWER_LEFT_EXT,zt.ZERO_TO_ONE_EXT):zt.clipControlEXT(zt.LOWER_LEFT_EXT,zt.NEGATIVE_ONE_TO_ONE_EXT),vt=at;let Dt=At;At=null,this.setClear(Dt)}},getReversed:function(){return vt},setTest:function(at){at?$(s.DEPTH_TEST):mt(s.DEPTH_TEST)},setMask:function(at){tt!==at&&!F&&(s.depthMask(at),tt=at)},setFunc:function(at){if(vt&&(at=zu[at]),Mt!==at){switch(at){case Ga:s.depthFunc(s.NEVER);break;case Wa:s.depthFunc(s.ALWAYS);break;case Xa:s.depthFunc(s.LESS);break;case bs:s.depthFunc(s.LEQUAL);break;case qa:s.depthFunc(s.EQUAL);break;case Ya:s.depthFunc(s.GEQUAL);break;case Za:s.depthFunc(s.GREATER);break;case Ja:s.depthFunc(s.NOTEQUAL);break;default:s.depthFunc(s.LEQUAL)}Mt=at}},setLocked:function(at){F=at},setClear:function(at){At!==at&&(At=at,vt&&(at=1-at),s.clearDepth(at))},reset:function(){F=!1,tt=null,Mt=null,At=null,vt=!1}}}function i(){let F=!1,vt=null,tt=null,Mt=null,At=null,at=null,zt=null,Dt=null,_e=null;return{setTest:function(ue){F||(ue?$(s.STENCIL_TEST):mt(s.STENCIL_TEST))},setMask:function(ue){vt!==ue&&!F&&(s.stencilMask(ue),vt=ue)},setFunc:function(ue,En,Un){(tt!==ue||Mt!==En||At!==Un)&&(s.stencilFunc(ue,En,Un),tt=ue,Mt=En,At=Un)},setOp:function(ue,En,Un){(at!==ue||zt!==En||Dt!==Un)&&(s.stencilOp(ue,En,Un),at=ue,zt=En,Dt=Un)},setLocked:function(ue){F=ue},setClear:function(ue){_e!==ue&&(s.clearStencil(ue),_e=ue)},reset:function(){F=!1,vt=null,tt=null,Mt=null,At=null,at=null,zt=null,Dt=null,_e=null}}}let r=new e,a=new n,o=new i,l=new WeakMap,c=new WeakMap,h={},u={},d={},f=new WeakMap,p=[],y=null,g=!1,m=null,S=null,T=null,v=null,b=null,w=null,P=null,_=new Ft(0,0,0),E=0,C=!1,I=null,U=null,k=null,L=null,B=null,W=s.getParameter(s.MAX_COMBINED_TEXTURE_IMAGE_UNITS),X=!1,st=0,Z=s.getParameter(s.VERSION);Z.indexOf("WebGL")!==-1?(st=parseFloat(/^WebGL (\d)/.exec(Z)[1]),X=st>=1):Z.indexOf("OpenGL ES")!==-1&&(st=parseFloat(/^OpenGL ES (\d)/.exec(Z)[1]),X=st>=2);let Q=null,it={},nt=s.getParameter(s.SCISSOR_BOX),ct=s.getParameter(s.VIEWPORT),Yt=new Se().fromArray(nt),Jt=new Se().fromArray(ct);function Qt(F,vt,tt,Mt){let At=new Uint8Array(4),at=s.createTexture();s.bindTexture(F,at),s.texParameteri(F,s.TEXTURE_MIN_FILTER,s.NEAREST),s.texParameteri(F,s.TEXTURE_MAG_FILTER,s.NEAREST);for(let zt=0;zt<tt;zt++)F===s.TEXTURE_3D||F===s.TEXTURE_2D_ARRAY?s.texImage3D(vt,0,s.RGBA,1,1,Mt,0,s.RGBA,s.UNSIGNED_BYTE,At):s.texImage2D(vt+zt,0,s.RGBA,1,1,0,s.RGBA,s.UNSIGNED_BYTE,At);return at}let J={};J[s.TEXTURE_2D]=Qt(s.TEXTURE_2D,s.TEXTURE_2D,1),J[s.TEXTURE_CUBE_MAP]=Qt(s.TEXTURE_CUBE_MAP,s.TEXTURE_CUBE_MAP_POSITIVE_X,6),J[s.TEXTURE_2D_ARRAY]=Qt(s.TEXTURE_2D_ARRAY,s.TEXTURE_2D_ARRAY,1,1),J[s.TEXTURE_3D]=Qt(s.TEXTURE_3D,s.TEXTURE_3D,1,1),r.setClear(0,0,0,1),a.setClear(1),o.setClear(0),$(s.DEPTH_TEST),a.setFunc(bs),dt(!1),pt(bc),$(s.CULL_FACE),ot(_n);function $(F){h[F]!==!0&&(s.enable(F),h[F]=!0)}function mt(F){h[F]!==!1&&(s.disable(F),h[F]=!1)}function Lt(F,vt){return d[F]!==vt?(s.bindFramebuffer(F,vt),d[F]=vt,F===s.DRAW_FRAMEBUFFER&&(d[s.FRAMEBUFFER]=vt),F===s.FRAMEBUFFER&&(d[s.DRAW_FRAMEBUFFER]=vt),!0):!1}function gt(F,vt){let tt=p,Mt=!1;if(F){tt=f.get(vt),tt===void 0&&(tt=[],f.set(vt,tt));let At=F.textures;if(tt.length!==At.length||tt[0]!==s.COLOR_ATTACHMENT0){for(let at=0,zt=At.length;at<zt;at++)tt[at]=s.COLOR_ATTACHMENT0+at;tt.length=At.length,Mt=!0}}else tt[0]!==s.BACK&&(tt[0]=s.BACK,Mt=!0);Mt&&s.drawBuffers(tt)}function Bt(F){return y!==F?(s.useProgram(F),y=F,!0):!1}let ae={[Zi]:s.FUNC_ADD,[lu]:s.FUNC_SUBTRACT,[cu]:s.FUNC_REVERSE_SUBTRACT};ae[hu]=s.MIN,ae[uu]=s.MAX;let j={[du]:s.ZERO,[fu]:s.ONE,[pu]:s.SRC_COLOR,[Tc]:s.SRC_ALPHA,[vu]:s.SRC_ALPHA_SATURATE,[_u]:s.DST_COLOR,[gu]:s.DST_ALPHA,[mu]:s.ONE_MINUS_SRC_COLOR,[Ac]:s.ONE_MINUS_SRC_ALPHA,[yu]:s.ONE_MINUS_DST_COLOR,[xu]:s.ONE_MINUS_DST_ALPHA,[Mu]:s.CONSTANT_COLOR,[Su]:s.ONE_MINUS_CONSTANT_COLOR,[bu]:s.CONSTANT_ALPHA,[Eu]:s.ONE_MINUS_CONSTANT_ALPHA};function ot(F,vt,tt,Mt,At,at,zt,Dt,_e,ue){if(F===_n){g===!0&&(mt(s.BLEND),g=!1);return}if(g===!1&&($(s.BLEND),g=!0),F!==ou){if(F!==m||ue!==C){if((S!==Zi||b!==Zi)&&(s.blendEquation(s.FUNC_ADD),S=Zi,b=Zi),ue)switch(F){case Bs:s.blendFuncSeparate(s.ONE,s.ONE_MINUS_SRC_ALPHA,s.ONE,s.ONE_MINUS_SRC_ALPHA);break;case Yi:s.blendFunc(s.ONE,s.ONE);break;case Ec:s.blendFuncSeparate(s.ZERO,s.ONE_MINUS_SRC_COLOR,s.ZERO,s.ONE);break;case wc:s.blendFuncSeparate(s.DST_COLOR,s.ONE_MINUS_SRC_ALPHA,s.ZERO,s.ONE);break;default:Xt("WebGLState: Invalid blending: ",F);break}else switch(F){case Bs:s.blendFuncSeparate(s.SRC_ALPHA,s.ONE_MINUS_SRC_ALPHA,s.ONE,s.ONE_MINUS_SRC_ALPHA);break;case Yi:s.blendFuncSeparate(s.SRC_ALPHA,s.ONE,s.ONE,s.ONE);break;case Ec:Xt("WebGLState: SubtractiveBlending requires material.premultipliedAlpha = true");break;case wc:Xt("WebGLState: MultiplyBlending requires material.premultipliedAlpha = true");break;default:Xt("WebGLState: Invalid blending: ",F);break}T=null,v=null,w=null,P=null,_.set(0,0,0),E=0,m=F,C=ue}return}At=At||vt,at=at||tt,zt=zt||Mt,(vt!==S||At!==b)&&(s.blendEquationSeparate(ae[vt],ae[At]),S=vt,b=At),(tt!==T||Mt!==v||at!==w||zt!==P)&&(s.blendFuncSeparate(j[tt],j[Mt],j[at],j[zt]),T=tt,v=Mt,w=at,P=zt),(Dt.equals(_)===!1||_e!==E)&&(s.blendColor(Dt.r,Dt.g,Dt.b,_e),_.copy(Dt),E=_e),m=F,C=!1}function ht(F,vt){F.side===He?mt(s.CULL_FACE):$(s.CULL_FACE);let tt=F.side===We;vt&&(tt=!tt),dt(tt),F.blending===Bs&&F.transparent===!1?ot(_n):ot(F.blending,F.blendEquation,F.blendSrc,F.blendDst,F.blendEquationAlpha,F.blendSrcAlpha,F.blendDstAlpha,F.blendColor,F.blendAlpha,F.premultipliedAlpha),a.setFunc(F.depthFunc),a.setTest(F.depthTest),a.setMask(F.depthWrite),r.setMask(F.colorWrite);let Mt=F.stencilWrite;o.setTest(Mt),Mt&&(o.setMask(F.stencilWriteMask),o.setFunc(F.stencilFunc,F.stencilRef,F.stencilFuncMask),o.setOp(F.stencilFail,F.stencilZFail,F.stencilZPass)),kt(F.polygonOffset,F.polygonOffsetFactor,F.polygonOffsetUnits),F.alphaToCoverage===!0?$(s.SAMPLE_ALPHA_TO_COVERAGE):mt(s.SAMPLE_ALPHA_TO_COVERAGE)}function dt(F){I!==F&&(F?s.frontFace(s.CW):s.frontFace(s.CCW),I=F)}function pt(F){F!==su?($(s.CULL_FACE),F!==U&&(F===bc?s.cullFace(s.BACK):F===ru?s.cullFace(s.FRONT):s.cullFace(s.FRONT_AND_BACK))):mt(s.CULL_FACE),U=F}function Ht(F){F!==k&&(X&&s.lineWidth(F),k=F)}function kt(F,vt,tt){F?($(s.POLYGON_OFFSET_FILL),(L!==vt||B!==tt)&&(L=vt,B=tt,a.getReversed()&&(vt=-vt),s.polygonOffset(vt,tt))):mt(s.POLYGON_OFFSET_FILL)}function Gt(F){F?$(s.SCISSOR_TEST):mt(s.SCISSOR_TEST)}function z(F){F===void 0&&(F=s.TEXTURE0+W-1),Q!==F&&(s.activeTexture(F),Q=F)}function A(F,vt,tt){tt===void 0&&(Q===null?tt=s.TEXTURE0+W-1:tt=Q);let Mt=it[tt];Mt===void 0&&(Mt={type:void 0,texture:void 0},it[tt]=Mt),(Mt.type!==F||Mt.texture!==vt)&&(Q!==tt&&(s.activeTexture(tt),Q=tt),s.bindTexture(F,vt||J[F]),Mt.type=F,Mt.texture=vt)}function Tt(){let F=it[Q];F!==void 0&&F.type!==void 0&&(s.bindTexture(F.type,null),F.type=void 0,F.texture=void 0)}function Et(){try{s.compressedTexImage2D(...arguments)}catch(F){Xt("WebGLState:",F)}}function R(){try{s.compressedTexImage3D(...arguments)}catch(F){Xt("WebGLState:",F)}}function x(){try{s.texSubImage2D(...arguments)}catch(F){Xt("WebGLState:",F)}}function O(){try{s.texSubImage3D(...arguments)}catch(F){Xt("WebGLState:",F)}}function G(){try{s.compressedTexSubImage2D(...arguments)}catch(F){Xt("WebGLState:",F)}}function q(){try{s.compressedTexSubImage3D(...arguments)}catch(F){Xt("WebGLState:",F)}}function lt(){try{s.texStorage2D(...arguments)}catch(F){Xt("WebGLState:",F)}}function ft(){try{s.texStorage3D(...arguments)}catch(F){Xt("WebGLState:",F)}}function K(){try{s.texImage2D(...arguments)}catch(F){Xt("WebGLState:",F)}}function et(){try{s.texImage3D(...arguments)}catch(F){Xt("WebGLState:",F)}}function xt(F){return u[F]!==void 0?u[F]:s.getParameter(F)}function Ut(F,vt){u[F]!==vt&&(s.pixelStorei(F,vt),u[F]=vt)}function _t(F){Yt.equals(F)===!1&&(s.scissor(F.x,F.y,F.z,F.w),Yt.copy(F))}function yt(F){Jt.equals(F)===!1&&(s.viewport(F.x,F.y,F.z,F.w),Jt.copy(F))}function Ot(F,vt){let tt=c.get(vt);tt===void 0&&(tt=new WeakMap,c.set(vt,tt));let Mt=tt.get(F);Mt===void 0&&(Mt=s.getUniformBlockIndex(vt,F.name),tt.set(F,Mt))}function Wt(F,vt){let Mt=c.get(vt).get(F);l.get(vt)!==Mt&&(s.uniformBlockBinding(vt,Mt,F.__bindingPointIndex),l.set(vt,Mt))}function jt(){s.disable(s.BLEND),s.disable(s.CULL_FACE),s.disable(s.DEPTH_TEST),s.disable(s.POLYGON_OFFSET_FILL),s.disable(s.SCISSOR_TEST),s.disable(s.STENCIL_TEST),s.disable(s.SAMPLE_ALPHA_TO_COVERAGE),s.blendEquation(s.FUNC_ADD),s.blendFunc(s.ONE,s.ZERO),s.blendFuncSeparate(s.ONE,s.ZERO,s.ONE,s.ZERO),s.blendColor(0,0,0,0),s.colorMask(!0,!0,!0,!0),s.clearColor(0,0,0,0),s.depthMask(!0),s.depthFunc(s.LESS),a.setReversed(!1),s.clearDepth(1),s.stencilMask(4294967295),s.stencilFunc(s.ALWAYS,0,4294967295),s.stencilOp(s.KEEP,s.KEEP,s.KEEP),s.clearStencil(0),s.cullFace(s.BACK),s.frontFace(s.CCW),s.polygonOffset(0,0),s.activeTexture(s.TEXTURE0),s.bindFramebuffer(s.FRAMEBUFFER,null),s.bindFramebuffer(s.DRAW_FRAMEBUFFER,null),s.bindFramebuffer(s.READ_FRAMEBUFFER,null),s.useProgram(null),s.lineWidth(1),s.scissor(0,0,s.canvas.width,s.canvas.height),s.viewport(0,0,s.canvas.width,s.canvas.height),s.pixelStorei(s.PACK_ALIGNMENT,4),s.pixelStorei(s.UNPACK_ALIGNMENT,4),s.pixelStorei(s.UNPACK_FLIP_Y_WEBGL,!1),s.pixelStorei(s.UNPACK_PREMULTIPLY_ALPHA_WEBGL,!1),s.pixelStorei(s.UNPACK_COLORSPACE_CONVERSION_WEBGL,s.BROWSER_DEFAULT_WEBGL),s.pixelStorei(s.PACK_ROW_LENGTH,0),s.pixelStorei(s.PACK_SKIP_PIXELS,0),s.pixelStorei(s.PACK_SKIP_ROWS,0),s.pixelStorei(s.UNPACK_ROW_LENGTH,0),s.pixelStorei(s.UNPACK_IMAGE_HEIGHT,0),s.pixelStorei(s.UNPACK_SKIP_PIXELS,0),s.pixelStorei(s.UNPACK_SKIP_ROWS,0),s.pixelStorei(s.UNPACK_SKIP_IMAGES,0),h={},u={},Q=null,it={},d={},f=new WeakMap,p=[],y=null,g=!1,m=null,S=null,T=null,v=null,b=null,w=null,P=null,_=new Ft(0,0,0),E=0,C=!1,I=null,U=null,k=null,L=null,B=null,Yt.set(0,0,s.canvas.width,s.canvas.height),Jt.set(0,0,s.canvas.width,s.canvas.height),r.reset(),a.reset(),o.reset()}return{buffers:{color:r,depth:a,stencil:o},enable:$,disable:mt,bindFramebuffer:Lt,drawBuffers:gt,useProgram:Bt,setBlending:ot,setMaterial:ht,setFlipSided:dt,setCullFace:pt,setLineWidth:Ht,setPolygonOffset:kt,setScissorTest:Gt,activeTexture:z,bindTexture:A,unbindTexture:Tt,compressedTexImage2D:Et,compressedTexImage3D:R,texImage2D:K,texImage3D:et,pixelStorei:Ut,getParameter:xt,updateUBOMapping:Ot,uniformBlockBinding:Wt,texStorage2D:lt,texStorage3D:ft,texSubImage2D:x,texSubImage3D:O,compressedTexSubImage2D:G,compressedTexSubImage3D:q,scissor:_t,viewport:yt,reset:jt}}function $x(s,t,e,n,i,r,a){let o=t.has("WEBGL_multisampled_render_to_texture")?t.get("WEBGL_multisampled_render_to_texture"):null,l=typeof navigator>"u"?!1:/OculusBrowser/g.test(navigator.userAgent),c=new rt,h=new WeakMap,u=new Set,d,f=new WeakMap,p=!1;try{p=typeof OffscreenCanvas<"u"&&new OffscreenCanvas(1,1).getContext("2d")!==null}catch{}function y(R,x){return p?new OffscreenCanvas(R,x):pr("canvas")}function g(R,x,O){let G=1,q=Et(R);if((q.width>O||q.height>O)&&(G=O/Math.max(q.width,q.height)),G<1)if(typeof HTMLImageElement<"u"&&R instanceof HTMLImageElement||typeof HTMLCanvasElement<"u"&&R instanceof HTMLCanvasElement||typeof ImageBitmap<"u"&&R instanceof ImageBitmap||typeof VideoFrame<"u"&&R instanceof VideoFrame){let lt=Math.floor(G*q.width),ft=Math.floor(G*q.height);d===void 0&&(d=y(lt,ft));let K=x?y(lt,ft):d;return K.width=lt,K.height=ft,K.getContext("2d").drawImage(R,0,0,lt,ft),qt("WebGLRenderer: Texture has been resized from ("+q.width+"x"+q.height+") to ("+lt+"x"+ft+")."),K}else return"data"in R&&qt("WebGLRenderer: Image in DataTexture is too big ("+q.width+"x"+q.height+")."),R;return R}function m(R){return R.generateMipmaps}function S(R){s.generateMipmap(R)}function T(R){return R.isWebGLCubeRenderTarget?s.TEXTURE_CUBE_MAP:R.isWebGL3DRenderTarget?s.TEXTURE_3D:R.isWebGLArrayRenderTarget||R.isCompressedArrayTexture?s.TEXTURE_2D_ARRAY:s.TEXTURE_2D}function v(R,x,O,G,q,lt=!1){if(R!==null){if(s[R]!==void 0)return s[R];qt("WebGLRenderer: Attempt to use non-existing WebGL internal format '"+R+"'")}let ft;G&&(ft=t.get("EXT_texture_norm16"),ft||qt("WebGLRenderer: Unable to use normalized textures without EXT_texture_norm16 extension"));let K=x;if(x===s.RED&&(O===s.FLOAT&&(K=s.R32F),O===s.HALF_FLOAT&&(K=s.R16F),O===s.UNSIGNED_BYTE&&(K=s.R8),O===s.UNSIGNED_SHORT&&ft&&(K=ft.R16_EXT),O===s.SHORT&&ft&&(K=ft.R16_SNORM_EXT)),x===s.RED_INTEGER&&(O===s.UNSIGNED_BYTE&&(K=s.R8UI),O===s.UNSIGNED_SHORT&&(K=s.R16UI),O===s.UNSIGNED_INT&&(K=s.R32UI),O===s.BYTE&&(K=s.R8I),O===s.SHORT&&(K=s.R16I),O===s.INT&&(K=s.R32I)),x===s.RG&&(O===s.FLOAT&&(K=s.RG32F),O===s.HALF_FLOAT&&(K=s.RG16F),O===s.UNSIGNED_BYTE&&(K=s.RG8),O===s.UNSIGNED_SHORT&&ft&&(K=ft.RG16_EXT),O===s.SHORT&&ft&&(K=ft.RG16_SNORM_EXT)),x===s.RG_INTEGER&&(O===s.UNSIGNED_BYTE&&(K=s.RG8UI),O===s.UNSIGNED_SHORT&&(K=s.RG16UI),O===s.UNSIGNED_INT&&(K=s.RG32UI),O===s.BYTE&&(K=s.RG8I),O===s.SHORT&&(K=s.RG16I),O===s.INT&&(K=s.RG32I)),x===s.RGB_INTEGER&&(O===s.UNSIGNED_BYTE&&(K=s.RGB8UI),O===s.UNSIGNED_SHORT&&(K=s.RGB16UI),O===s.UNSIGNED_INT&&(K=s.RGB32UI),O===s.BYTE&&(K=s.RGB8I),O===s.SHORT&&(K=s.RGB16I),O===s.INT&&(K=s.RGB32I)),x===s.RGBA_INTEGER&&(O===s.UNSIGNED_BYTE&&(K=s.RGBA8UI),O===s.UNSIGNED_SHORT&&(K=s.RGBA16UI),O===s.UNSIGNED_INT&&(K=s.RGBA32UI),O===s.BYTE&&(K=s.RGBA8I),O===s.SHORT&&(K=s.RGBA16I),O===s.INT&&(K=s.RGBA32I)),x===s.RGB&&(O===s.UNSIGNED_SHORT&&ft&&(K=ft.RGB16_EXT),O===s.SHORT&&ft&&(K=ft.RGB16_SNORM_EXT),O===s.UNSIGNED_INT_5_9_9_9_REV&&(K=s.RGB9_E5),O===s.UNSIGNED_INT_10F_11F_11F_REV&&(K=s.R11F_G11F_B10F)),x===s.RGBA){let et=lt?fr:ne.getTransfer(q);O===s.FLOAT&&(K=s.RGBA32F),O===s.HALF_FLOAT&&(K=s.RGBA16F),O===s.UNSIGNED_BYTE&&(K=et===ce?s.SRGB8_ALPHA8:s.RGBA8),O===s.UNSIGNED_SHORT&&ft&&(K=ft.RGBA16_EXT),O===s.SHORT&&ft&&(K=ft.RGBA16_SNORM_EXT),O===s.UNSIGNED_SHORT_4_4_4_4&&(K=s.RGBA4),O===s.UNSIGNED_SHORT_5_5_5_1&&(K=s.RGB5_A1)}return(K===s.R16F||K===s.R32F||K===s.RG16F||K===s.RG32F||K===s.RGBA16F||K===s.RGBA32F)&&t.get("EXT_color_buffer_float"),K}function b(R,x){let O;return R?x===null||x===Dn||x===ks?O=s.DEPTH24_STENCIL8:x===yn?O=s.DEPTH32F_STENCIL8:x===Os&&(O=s.DEPTH24_STENCIL8,qt("DepthTexture: 16 bit depth attachment is not supported with stencil. Using 24-bit attachment.")):x===null||x===Dn||x===ks?O=s.DEPTH_COMPONENT24:x===yn?O=s.DEPTH_COMPONENT32F:x===Os&&(O=s.DEPTH_COMPONENT16),O}function w(R,x){return m(R)===!0||R.isFramebufferTexture&&R.minFilter!==ze&&R.minFilter!==Ge?Math.log2(Math.max(x.width,x.height))+1:R.mipmaps!==void 0&&R.mipmaps.length>0?R.mipmaps.length:R.isCompressedTexture&&Array.isArray(R.image)?x.mipmaps.length:1}function P(R){let x=R.target;x.removeEventListener("dispose",P),E(x),x.isVideoTexture&&h.delete(x),x.isHTMLTexture&&u.delete(x)}function _(R){let x=R.target;x.removeEventListener("dispose",_),I(x)}function E(R){let x=n.get(R);if(x.__webglInit===void 0)return;let O=R.source,G=f.get(O);if(G){let q=G[x.__cacheKey];q.usedTimes--,q.usedTimes===0&&C(R),Object.keys(G).length===0&&f.delete(O)}n.remove(R)}function C(R){let x=n.get(R);s.deleteTexture(x.__webglTexture);let O=R.source,G=f.get(O);delete G[x.__cacheKey],a.memory.textures--}function I(R){let x=n.get(R);if(R.depthTexture&&(R.depthTexture.dispose(),n.remove(R.depthTexture)),R.isWebGLCubeRenderTarget)for(let G=0;G<6;G++){if(Array.isArray(x.__webglFramebuffer[G]))for(let q=0;q<x.__webglFramebuffer[G].length;q++)s.deleteFramebuffer(x.__webglFramebuffer[G][q]);else s.deleteFramebuffer(x.__webglFramebuffer[G]);x.__webglDepthbuffer&&s.deleteRenderbuffer(x.__webglDepthbuffer[G])}else{if(Array.isArray(x.__webglFramebuffer))for(let G=0;G<x.__webglFramebuffer.length;G++)s.deleteFramebuffer(x.__webglFramebuffer[G]);else s.deleteFramebuffer(x.__webglFramebuffer);if(x.__webglDepthbuffer&&s.deleteRenderbuffer(x.__webglDepthbuffer),x.__webglMultisampledFramebuffer&&s.deleteFramebuffer(x.__webglMultisampledFramebuffer),x.__webglColorRenderbuffer)for(let G=0;G<x.__webglColorRenderbuffer.length;G++)x.__webglColorRenderbuffer[G]&&s.deleteRenderbuffer(x.__webglColorRenderbuffer[G]);x.__webglDepthRenderbuffer&&s.deleteRenderbuffer(x.__webglDepthRenderbuffer)}let O=R.textures;for(let G=0,q=O.length;G<q;G++){let lt=n.get(O[G]);lt.__webglTexture&&(s.deleteTexture(lt.__webglTexture),a.memory.textures--),n.remove(O[G])}n.remove(R)}let U=0;function k(){U=0}function L(){return U}function B(R){U=R}function W(){let R=U;return R>=i.maxTextures&&qt("WebGLTextures: Trying to use "+(R+1)+" texture units while this GPU supports only "+i.maxTextures),U+=1,R}function X(R){let x=[];return x.push(R.wrapS),x.push(R.wrapT),x.push(R.wrapR||0),x.push(R.magFilter),x.push(R.minFilter),x.push(R.anisotropy),x.push(R.internalFormat),x.push(R.format),x.push(R.type),x.push(R.generateMipmaps),x.push(R.premultiplyAlpha),x.push(R.flipY),x.push(R.unpackAlignment),x.push(R.colorSpace),x.join()}function st(R,x){let O=n.get(R);if(R.isVideoTexture&&A(R),R.isRenderTargetTexture===!1&&R.isExternalTexture!==!0&&R.version>0&&O.__version!==R.version){let G=R.image;if(G===null)qt("WebGLRenderer: Texture marked for update but no image data found.");else if(G.complete===!1)qt("WebGLRenderer: Texture marked for update but image is incomplete");else{mt(O,R,x);return}}else R.isExternalTexture&&(O.__webglTexture=R.sourceTexture?R.sourceTexture:null);e.bindTexture(s.TEXTURE_2D,O.__webglTexture,s.TEXTURE0+x)}function Z(R,x){let O=n.get(R);if(R.isRenderTargetTexture===!1&&R.version>0&&O.__version!==R.version){mt(O,R,x);return}else R.isExternalTexture&&(O.__webglTexture=R.sourceTexture?R.sourceTexture:null);e.bindTexture(s.TEXTURE_2D_ARRAY,O.__webglTexture,s.TEXTURE0+x)}function Q(R,x){let O=n.get(R);if(R.isRenderTargetTexture===!1&&R.version>0&&O.__version!==R.version){mt(O,R,x);return}e.bindTexture(s.TEXTURE_3D,O.__webglTexture,s.TEXTURE0+x)}function it(R,x){let O=n.get(R);if(R.isCubeDepthTexture!==!0&&R.version>0&&O.__version!==R.version){Lt(O,R,x);return}e.bindTexture(s.TEXTURE_CUBE_MAP,O.__webglTexture,s.TEXTURE0+x)}let nt={[zn]:s.REPEAT,[On]:s.CLAMP_TO_EDGE,[$a]:s.MIRRORED_REPEAT},ct={[ze]:s.NEAREST,[Au]:s.NEAREST_MIPMAP_NEAREST,[Kr]:s.NEAREST_MIPMAP_LINEAR,[Ge]:s.LINEAR,[Ao]:s.LINEAR_MIPMAP_NEAREST,[Ai]:s.LINEAR_MIPMAP_LINEAR},Yt={[Iu]:s.NEVER,[Fu]:s.ALWAYS,[Lu]:s.LESS,[ul]:s.LEQUAL,[Du]:s.EQUAL,[dl]:s.GEQUAL,[Uu]:s.GREATER,[Nu]:s.NOTEQUAL};function Jt(R,x){if(x.type===yn&&t.has("OES_texture_float_linear")===!1&&(x.magFilter===Ge||x.magFilter===Ao||x.magFilter===Kr||x.magFilter===Ai||x.minFilter===Ge||x.minFilter===Ao||x.minFilter===Kr||x.minFilter===Ai)&&qt("WebGLRenderer: Unable to use linear filtering with floating point textures. OES_texture_float_linear not supported on this device."),s.texParameteri(R,s.TEXTURE_WRAP_S,nt[x.wrapS]),s.texParameteri(R,s.TEXTURE_WRAP_T,nt[x.wrapT]),(R===s.TEXTURE_3D||R===s.TEXTURE_2D_ARRAY)&&s.texParameteri(R,s.TEXTURE_WRAP_R,nt[x.wrapR]),s.texParameteri(R,s.TEXTURE_MAG_FILTER,ct[x.magFilter]),s.texParameteri(R,s.TEXTURE_MIN_FILTER,ct[x.minFilter]),x.compareFunction&&(s.texParameteri(R,s.TEXTURE_COMPARE_MODE,s.COMPARE_REF_TO_TEXTURE),s.texParameteri(R,s.TEXTURE_COMPARE_FUNC,Yt[x.compareFunction])),t.has("EXT_texture_filter_anisotropic")===!0){if(x.magFilter===ze||x.minFilter!==Kr&&x.minFilter!==Ai||x.type===yn&&t.has("OES_texture_float_linear")===!1)return;if(x.anisotropy>1||n.get(x).__currentAnisotropy){let O=t.get("EXT_texture_filter_anisotropic");s.texParameterf(R,O.TEXTURE_MAX_ANISOTROPY_EXT,Math.min(x.anisotropy,i.getMaxAnisotropy())),n.get(x).__currentAnisotropy=x.anisotropy}}}function Qt(R,x){let O=!1;R.__webglInit===void 0&&(R.__webglInit=!0,x.addEventListener("dispose",P));let G=x.source,q=f.get(G);q===void 0&&(q={},f.set(G,q));let lt=X(x);if(lt!==R.__cacheKey){q[lt]===void 0&&(q[lt]={texture:s.createTexture(),usedTimes:0},a.memory.textures++,O=!0),q[lt].usedTimes++;let ft=q[R.__cacheKey];ft!==void 0&&(q[R.__cacheKey].usedTimes--,ft.usedTimes===0&&C(x)),R.__cacheKey=lt,R.__webglTexture=q[lt].texture}return O}function J(R,x,O){return Math.floor(Math.floor(R/O)/x)}function $(R,x,O,G){let lt=R.updateRanges;if(lt.length===0)e.texSubImage2D(s.TEXTURE_2D,0,0,0,x.width,x.height,O,G,x.data);else{lt.sort((Ut,_t)=>Ut.start-_t.start);let ft=0;for(let Ut=1;Ut<lt.length;Ut++){let _t=lt[ft],yt=lt[Ut],Ot=_t.start+_t.count,Wt=J(yt.start,x.width,4),jt=J(_t.start,x.width,4);yt.start<=Ot+1&&Wt===jt&&J(yt.start+yt.count-1,x.width,4)===Wt?_t.count=Math.max(_t.count,yt.start+yt.count-_t.start):(++ft,lt[ft]=yt)}lt.length=ft+1;let K=e.getParameter(s.UNPACK_ROW_LENGTH),et=e.getParameter(s.UNPACK_SKIP_PIXELS),xt=e.getParameter(s.UNPACK_SKIP_ROWS);e.pixelStorei(s.UNPACK_ROW_LENGTH,x.width);for(let Ut=0,_t=lt.length;Ut<_t;Ut++){let yt=lt[Ut],Ot=Math.floor(yt.start/4),Wt=Math.ceil(yt.count/4),jt=Ot%x.width,F=Math.floor(Ot/x.width),vt=Wt,tt=1;e.pixelStorei(s.UNPACK_SKIP_PIXELS,jt),e.pixelStorei(s.UNPACK_SKIP_ROWS,F),e.texSubImage2D(s.TEXTURE_2D,0,jt,F,vt,tt,O,G,x.data)}R.clearUpdateRanges(),e.pixelStorei(s.UNPACK_ROW_LENGTH,K),e.pixelStorei(s.UNPACK_SKIP_PIXELS,et),e.pixelStorei(s.UNPACK_SKIP_ROWS,xt)}}function mt(R,x,O){let G=s.TEXTURE_2D;(x.isDataArrayTexture||x.isCompressedArrayTexture)&&(G=s.TEXTURE_2D_ARRAY),x.isData3DTexture&&(G=s.TEXTURE_3D);let q=Qt(R,x),lt=x.source;e.bindTexture(G,R.__webglTexture,s.TEXTURE0+O);let ft=n.get(lt);if(lt.version!==ft.__version||q===!0){if(e.activeTexture(s.TEXTURE0+O),(typeof ImageBitmap<"u"&&x.image instanceof ImageBitmap)===!1){let tt=ne.getPrimaries(ne.workingColorSpace),Mt=x.colorSpace===ii?null:ne.getPrimaries(x.colorSpace),At=x.colorSpace===ii||tt===Mt?s.NONE:s.BROWSER_DEFAULT_WEBGL;e.pixelStorei(s.UNPACK_FLIP_Y_WEBGL,x.flipY),e.pixelStorei(s.UNPACK_PREMULTIPLY_ALPHA_WEBGL,x.premultiplyAlpha),e.pixelStorei(s.UNPACK_COLORSPACE_CONVERSION_WEBGL,At)}e.pixelStorei(s.UNPACK_ALIGNMENT,x.unpackAlignment);let et=g(x.image,!1,i.maxTextureSize);et=Tt(x,et);let xt=r.convert(x.format,x.colorSpace),Ut=r.convert(x.type),_t=v(x.internalFormat,xt,Ut,x.normalized,x.colorSpace,x.isVideoTexture);Jt(G,x);let yt,Ot=x.mipmaps,Wt=x.isVideoTexture!==!0,jt=ft.__version===void 0||q===!0,F=lt.dataReady,vt=w(x,et);if(x.isDepthTexture)_t=b(x.format===Ri,x.type),jt&&(Wt?e.texStorage2D(s.TEXTURE_2D,1,_t,et.width,et.height):e.texImage2D(s.TEXTURE_2D,0,_t,et.width,et.height,0,xt,Ut,null));else if(x.isDataTexture)if(Ot.length>0){Wt&&jt&&e.texStorage2D(s.TEXTURE_2D,vt,_t,Ot[0].width,Ot[0].height);for(let tt=0,Mt=Ot.length;tt<Mt;tt++)yt=Ot[tt],Wt?F&&e.texSubImage2D(s.TEXTURE_2D,tt,0,0,yt.width,yt.height,xt,Ut,yt.data):e.texImage2D(s.TEXTURE_2D,tt,_t,yt.width,yt.height,0,xt,Ut,yt.data);x.generateMipmaps=!1}else Wt?(jt&&e.texStorage2D(s.TEXTURE_2D,vt,_t,et.width,et.height),F&&$(x,et,xt,Ut)):e.texImage2D(s.TEXTURE_2D,0,_t,et.width,et.height,0,xt,Ut,et.data);else if(x.isCompressedTexture)if(x.isCompressedArrayTexture){Wt&&jt&&e.texStorage3D(s.TEXTURE_2D_ARRAY,vt,_t,Ot[0].width,Ot[0].height,et.depth);for(let tt=0,Mt=Ot.length;tt<Mt;tt++)if(yt=Ot[tt],x.format!==vn)if(xt!==null)if(Wt){if(F)if(x.layerUpdates.size>0){let At=Hc(yt.width,yt.height,x.format,x.type);for(let at of x.layerUpdates){let zt=yt.data.subarray(at*At/yt.data.BYTES_PER_ELEMENT,(at+1)*At/yt.data.BYTES_PER_ELEMENT);e.compressedTexSubImage3D(s.TEXTURE_2D_ARRAY,tt,0,0,at,yt.width,yt.height,1,xt,zt)}}else e.compressedTexSubImage3D(s.TEXTURE_2D_ARRAY,tt,0,0,0,yt.width,yt.height,et.depth,xt,yt.data)}else e.compressedTexImage3D(s.TEXTURE_2D_ARRAY,tt,_t,yt.width,yt.height,et.depth,0,yt.data,0,0);else qt("WebGLRenderer: Attempt to load unsupported compressed texture format in .uploadTexture()");else Wt?F&&e.texSubImage3D(s.TEXTURE_2D_ARRAY,tt,0,0,0,yt.width,yt.height,et.depth,xt,Ut,yt.data):e.texImage3D(s.TEXTURE_2D_ARRAY,tt,_t,yt.width,yt.height,et.depth,0,xt,Ut,yt.data);x.layerUpdates.size>0&&x.clearLayerUpdates()}else{Wt&&jt&&e.texStorage2D(s.TEXTURE_2D,vt,_t,Ot[0].width,Ot[0].height);for(let tt=0,Mt=Ot.length;tt<Mt;tt++)yt=Ot[tt],x.format!==vn?xt!==null?Wt?F&&e.compressedTexSubImage2D(s.TEXTURE_2D,tt,0,0,yt.width,yt.height,xt,yt.data):e.compressedTexImage2D(s.TEXTURE_2D,tt,_t,yt.width,yt.height,0,yt.data):qt("WebGLRenderer: Attempt to load unsupported compressed texture format in .uploadTexture()"):Wt?F&&e.texSubImage2D(s.TEXTURE_2D,tt,0,0,yt.width,yt.height,xt,Ut,yt.data):e.texImage2D(s.TEXTURE_2D,tt,_t,yt.width,yt.height,0,xt,Ut,yt.data)}else if(x.isDataArrayTexture)if(Wt){if(jt&&e.texStorage3D(s.TEXTURE_2D_ARRAY,vt,_t,et.width,et.height,et.depth),F)if(x.layerUpdates.size>0){let tt=Hc(et.width,et.height,x.format,x.type);for(let Mt of x.layerUpdates){let At=et.data.subarray(Mt*tt/et.data.BYTES_PER_ELEMENT,(Mt+1)*tt/et.data.BYTES_PER_ELEMENT);e.texSubImage3D(s.TEXTURE_2D_ARRAY,0,0,0,Mt,et.width,et.height,1,xt,Ut,At)}x.clearLayerUpdates()}else e.texSubImage3D(s.TEXTURE_2D_ARRAY,0,0,0,0,et.width,et.height,et.depth,xt,Ut,et.data)}else e.texImage3D(s.TEXTURE_2D_ARRAY,0,_t,et.width,et.height,et.depth,0,xt,Ut,et.data);else if(x.isData3DTexture)Wt?(jt&&e.texStorage3D(s.TEXTURE_3D,vt,_t,et.width,et.height,et.depth),F&&e.texSubImage3D(s.TEXTURE_3D,0,0,0,0,et.width,et.height,et.depth,xt,Ut,et.data)):e.texImage3D(s.TEXTURE_3D,0,_t,et.width,et.height,et.depth,0,xt,Ut,et.data);else if(x.isFramebufferTexture){if(jt)if(Wt)e.texStorage2D(s.TEXTURE_2D,vt,_t,et.width,et.height);else{let tt=et.width,Mt=et.height;for(let At=0;At<vt;At++)e.texImage2D(s.TEXTURE_2D,At,_t,tt,Mt,0,xt,Ut,null),tt>>=1,Mt>>=1}}else if(x.isHTMLTexture){if("texElementImage2D"in s){let tt=s.canvas;if(tt.hasAttribute("layoutsubtree")||tt.setAttribute("layoutsubtree","true"),et.parentNode!==tt){tt.appendChild(et),u.add(x),tt.onpaint=Mt=>{let At=Mt.changedElements;for(let at of u)At.includes(at.image)&&(at.needsUpdate=!0)},tt.requestPaint();return}if(s.texElementImage2D.length===3)s.texElementImage2D(s.TEXTURE_2D,s.RGBA8,et);else{let At=s.RGBA,at=s.RGBA,zt=s.UNSIGNED_BYTE;s.texElementImage2D(s.TEXTURE_2D,0,At,at,zt,et)}s.texParameteri(s.TEXTURE_2D,s.TEXTURE_MIN_FILTER,s.LINEAR),s.texParameteri(s.TEXTURE_2D,s.TEXTURE_WRAP_S,s.CLAMP_TO_EDGE),s.texParameteri(s.TEXTURE_2D,s.TEXTURE_WRAP_T,s.CLAMP_TO_EDGE)}}else if(Ot.length>0){if(Wt&&jt){let tt=Et(Ot[0]);e.texStorage2D(s.TEXTURE_2D,vt,_t,tt.width,tt.height)}for(let tt=0,Mt=Ot.length;tt<Mt;tt++)yt=Ot[tt],Wt?F&&e.texSubImage2D(s.TEXTURE_2D,tt,0,0,xt,Ut,yt):e.texImage2D(s.TEXTURE_2D,tt,_t,xt,Ut,yt);x.generateMipmaps=!1}else if(Wt){if(jt){let tt=Et(et);e.texStorage2D(s.TEXTURE_2D,vt,_t,tt.width,tt.height)}F&&e.texSubImage2D(s.TEXTURE_2D,0,0,0,xt,Ut,et)}else e.texImage2D(s.TEXTURE_2D,0,_t,xt,Ut,et);m(x)&&S(G),ft.__version=lt.version,x.onUpdate&&x.onUpdate(x)}R.__version=x.version}function Lt(R,x,O){if(x.image.length!==6)return;let G=Qt(R,x),q=x.source;e.bindTexture(s.TEXTURE_CUBE_MAP,R.__webglTexture,s.TEXTURE0+O);let lt=n.get(q);if(q.version!==lt.__version||G===!0){e.activeTexture(s.TEXTURE0+O);let ft=ne.getPrimaries(ne.workingColorSpace),K=x.colorSpace===ii?null:ne.getPrimaries(x.colorSpace),et=x.colorSpace===ii||ft===K?s.NONE:s.BROWSER_DEFAULT_WEBGL;e.pixelStorei(s.UNPACK_FLIP_Y_WEBGL,x.flipY),e.pixelStorei(s.UNPACK_PREMULTIPLY_ALPHA_WEBGL,x.premultiplyAlpha),e.pixelStorei(s.UNPACK_ALIGNMENT,x.unpackAlignment),e.pixelStorei(s.UNPACK_COLORSPACE_CONVERSION_WEBGL,et);let xt=x.isCompressedTexture||x.image[0].isCompressedTexture,Ut=x.image[0]&&x.image[0].isDataTexture,_t=[];for(let at=0;at<6;at++)!xt&&!Ut?_t[at]=g(x.image[at],!0,i.maxCubemapSize):_t[at]=Ut?x.image[at].image:x.image[at],_t[at]=Tt(x,_t[at]);let yt=_t[0],Ot=r.convert(x.format,x.colorSpace),Wt=r.convert(x.type),jt=v(x.internalFormat,Ot,Wt,x.normalized,x.colorSpace),F=x.isVideoTexture!==!0,vt=lt.__version===void 0||G===!0,tt=q.dataReady,Mt=w(x,yt);Jt(s.TEXTURE_CUBE_MAP,x);let At;if(xt){F&&vt&&e.texStorage2D(s.TEXTURE_CUBE_MAP,Mt,jt,yt.width,yt.height);for(let at=0;at<6;at++){At=_t[at].mipmaps;for(let zt=0;zt<At.length;zt++){let Dt=At[zt];x.format!==vn?Ot!==null?F?tt&&e.compressedTexSubImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+at,zt,0,0,Dt.width,Dt.height,Ot,Dt.data):e.compressedTexImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+at,zt,jt,Dt.width,Dt.height,0,Dt.data):qt("WebGLRenderer: Attempt to load unsupported compressed texture format in .setTextureCube()"):F?tt&&e.texSubImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+at,zt,0,0,Dt.width,Dt.height,Ot,Wt,Dt.data):e.texImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+at,zt,jt,Dt.width,Dt.height,0,Ot,Wt,Dt.data)}}}else{if(At=x.mipmaps,F&&vt){At.length>0&&Mt++;let at=Et(_t[0]);e.texStorage2D(s.TEXTURE_CUBE_MAP,Mt,jt,at.width,at.height)}for(let at=0;at<6;at++)if(Ut){F?tt&&e.texSubImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+at,0,0,0,_t[at].width,_t[at].height,Ot,Wt,_t[at].data):e.texImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+at,0,jt,_t[at].width,_t[at].height,0,Ot,Wt,_t[at].data);for(let zt=0;zt<At.length;zt++){let _e=At[zt].image[at].image;F?tt&&e.texSubImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+at,zt+1,0,0,_e.width,_e.height,Ot,Wt,_e.data):e.texImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+at,zt+1,jt,_e.width,_e.height,0,Ot,Wt,_e.data)}}else{F?tt&&e.texSubImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+at,0,0,0,Ot,Wt,_t[at]):e.texImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+at,0,jt,Ot,Wt,_t[at]);for(let zt=0;zt<At.length;zt++){let Dt=At[zt];F?tt&&e.texSubImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+at,zt+1,0,0,Ot,Wt,Dt.image[at]):e.texImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+at,zt+1,jt,Ot,Wt,Dt.image[at])}}}m(x)&&S(s.TEXTURE_CUBE_MAP),lt.__version=q.version,x.onUpdate&&x.onUpdate(x)}R.__version=x.version}function gt(R,x,O,G,q,lt){let ft=r.convert(O.format,O.colorSpace),K=r.convert(O.type),et=v(O.internalFormat,ft,K,O.normalized,O.colorSpace),xt=n.get(x),Ut=n.get(O);if(Ut.__renderTarget=x,!xt.__hasExternalTextures){let _t=Math.max(1,x.width>>lt),yt=Math.max(1,x.height>>lt);q===s.TEXTURE_3D||q===s.TEXTURE_2D_ARRAY?e.texImage3D(q,lt,et,_t,yt,x.depth,0,ft,K,null):e.texImage2D(q,lt,et,_t,yt,0,ft,K,null)}e.bindFramebuffer(s.FRAMEBUFFER,R),z(x)?o.framebufferTexture2DMultisampleEXT(s.FRAMEBUFFER,G,q,Ut.__webglTexture,0,Gt(x)):(q===s.TEXTURE_2D||q>=s.TEXTURE_CUBE_MAP_POSITIVE_X&&q<=s.TEXTURE_CUBE_MAP_NEGATIVE_Z)&&s.framebufferTexture2D(s.FRAMEBUFFER,G,q,Ut.__webglTexture,lt),e.bindFramebuffer(s.FRAMEBUFFER,null)}function Bt(R,x,O){if(s.bindRenderbuffer(s.RENDERBUFFER,R),x.depthBuffer){let G=x.depthTexture,q=G&&G.isDepthTexture?G.type:null,lt=b(x.stencilBuffer,q),ft=x.stencilBuffer?s.DEPTH_STENCIL_ATTACHMENT:s.DEPTH_ATTACHMENT;z(x)?o.renderbufferStorageMultisampleEXT(s.RENDERBUFFER,Gt(x),lt,x.width,x.height):O?s.renderbufferStorageMultisample(s.RENDERBUFFER,Gt(x),lt,x.width,x.height):s.renderbufferStorage(s.RENDERBUFFER,lt,x.width,x.height),s.framebufferRenderbuffer(s.FRAMEBUFFER,ft,s.RENDERBUFFER,R)}else{let G=x.textures;for(let q=0;q<G.length;q++){let lt=G[q],ft=r.convert(lt.format,lt.colorSpace),K=r.convert(lt.type),et=v(lt.internalFormat,ft,K,lt.normalized,lt.colorSpace);z(x)?o.renderbufferStorageMultisampleEXT(s.RENDERBUFFER,Gt(x),et,x.width,x.height):O?s.renderbufferStorageMultisample(s.RENDERBUFFER,Gt(x),et,x.width,x.height):s.renderbufferStorage(s.RENDERBUFFER,et,x.width,x.height)}}s.bindRenderbuffer(s.RENDERBUFFER,null)}function ae(R,x,O){let G=x.isWebGLCubeRenderTarget===!0;if(e.bindFramebuffer(s.FRAMEBUFFER,R),!(x.depthTexture&&x.depthTexture.isDepthTexture))throw new Error("THREE.WebGLTextures: renderTarget.depthTexture must be an instance of THREE.DepthTexture.");let q=n.get(x.depthTexture);if(q.__renderTarget=x,(!q.__webglTexture||x.depthTexture.image.width!==x.width||x.depthTexture.image.height!==x.height)&&(x.depthTexture.image.width=x.width,x.depthTexture.image.height=x.height,x.depthTexture.needsUpdate=!0),G){if(q.__webglInit===void 0&&(q.__webglInit=!0,x.depthTexture.addEventListener("dispose",P)),q.__webglTexture===void 0){q.__webglTexture=s.createTexture(),e.bindTexture(s.TEXTURE_CUBE_MAP,q.__webglTexture),Jt(s.TEXTURE_CUBE_MAP,x.depthTexture);let xt=r.convert(x.depthTexture.format),Ut=r.convert(x.depthTexture.type),_t;x.depthTexture.format===Hn?_t=s.DEPTH_COMPONENT24:x.depthTexture.format===Ri&&(_t=s.DEPTH24_STENCIL8);for(let yt=0;yt<6;yt++)s.texImage2D(s.TEXTURE_CUBE_MAP_POSITIVE_X+yt,0,_t,x.width,x.height,0,xt,Ut,null)}}else st(x.depthTexture,0);let lt=q.__webglTexture,ft=Gt(x),K=G?s.TEXTURE_CUBE_MAP_POSITIVE_X+O:s.TEXTURE_2D,et=x.depthTexture.format===Ri?s.DEPTH_STENCIL_ATTACHMENT:s.DEPTH_ATTACHMENT;if(x.depthTexture.format===Hn)z(x)?o.framebufferTexture2DMultisampleEXT(s.FRAMEBUFFER,et,K,lt,0,ft):s.framebufferTexture2D(s.FRAMEBUFFER,et,K,lt,0);else if(x.depthTexture.format===Ri)z(x)?o.framebufferTexture2DMultisampleEXT(s.FRAMEBUFFER,et,K,lt,0,ft):s.framebufferTexture2D(s.FRAMEBUFFER,et,K,lt,0);else throw new Error("THREE.WebGLTextures: Unknown depthTexture format.")}function j(R){let x=n.get(R),O=R.isWebGLCubeRenderTarget===!0;if(x.__boundDepthTexture!==R.depthTexture){let G=R.depthTexture;if(x.__depthDisposeCallback&&x.__depthDisposeCallback(),G){let q=()=>{delete x.__boundDepthTexture,delete x.__depthDisposeCallback,G.removeEventListener("dispose",q)};G.addEventListener("dispose",q),x.__depthDisposeCallback=q}x.__boundDepthTexture=G}if(R.depthTexture&&!x.__autoAllocateDepthBuffer)if(O)for(let G=0;G<6;G++)ae(x.__webglFramebuffer[G],R,G);else{let G=R.texture.mipmaps;G&&G.length>0?ae(x.__webglFramebuffer[0],R,0):ae(x.__webglFramebuffer,R,0)}else if(O){x.__webglDepthbuffer=[];for(let G=0;G<6;G++)if(e.bindFramebuffer(s.FRAMEBUFFER,x.__webglFramebuffer[G]),x.__webglDepthbuffer[G]===void 0)x.__webglDepthbuffer[G]=s.createRenderbuffer(),Bt(x.__webglDepthbuffer[G],R,!1);else{let q=R.stencilBuffer?s.DEPTH_STENCIL_ATTACHMENT:s.DEPTH_ATTACHMENT,lt=x.__webglDepthbuffer[G];s.bindRenderbuffer(s.RENDERBUFFER,lt),s.framebufferRenderbuffer(s.FRAMEBUFFER,q,s.RENDERBUFFER,lt)}}else{let G=R.texture.mipmaps;if(G&&G.length>0?e.bindFramebuffer(s.FRAMEBUFFER,x.__webglFramebuffer[0]):e.bindFramebuffer(s.FRAMEBUFFER,x.__webglFramebuffer),x.__webglDepthbuffer===void 0)x.__webglDepthbuffer=s.createRenderbuffer(),Bt(x.__webglDepthbuffer,R,!1);else{let q=R.stencilBuffer?s.DEPTH_STENCIL_ATTACHMENT:s.DEPTH_ATTACHMENT,lt=x.__webglDepthbuffer;s.bindRenderbuffer(s.RENDERBUFFER,lt),s.framebufferRenderbuffer(s.FRAMEBUFFER,q,s.RENDERBUFFER,lt)}}e.bindFramebuffer(s.FRAMEBUFFER,null)}function ot(R,x,O){let G=n.get(R);x!==void 0&&gt(G.__webglFramebuffer,R,R.texture,s.COLOR_ATTACHMENT0,s.TEXTURE_2D,0),O!==void 0&&j(R)}function ht(R){let x=R.texture,O=n.get(R),G=n.get(x);R.addEventListener("dispose",_);let q=R.textures,lt=R.isWebGLCubeRenderTarget===!0,ft=q.length>1;if(ft||(G.__webglTexture===void 0&&(G.__webglTexture=s.createTexture()),G.__version=x.version,a.memory.textures++),lt){O.__webglFramebuffer=[];for(let K=0;K<6;K++)if(x.mipmaps&&x.mipmaps.length>0){O.__webglFramebuffer[K]=[];for(let et=0;et<x.mipmaps.length;et++)O.__webglFramebuffer[K][et]=s.createFramebuffer()}else O.__webglFramebuffer[K]=s.createFramebuffer()}else{if(x.mipmaps&&x.mipmaps.length>0){O.__webglFramebuffer=[];for(let K=0;K<x.mipmaps.length;K++)O.__webglFramebuffer[K]=s.createFramebuffer()}else O.__webglFramebuffer=s.createFramebuffer();if(ft)for(let K=0,et=q.length;K<et;K++){let xt=n.get(q[K]);xt.__webglTexture===void 0&&(xt.__webglTexture=s.createTexture(),a.memory.textures++)}if(R.samples>0&&z(R)===!1){O.__webglMultisampledFramebuffer=s.createFramebuffer(),O.__webglColorRenderbuffer=[],e.bindFramebuffer(s.FRAMEBUFFER,O.__webglMultisampledFramebuffer);for(let K=0;K<q.length;K++){let et=q[K];O.__webglColorRenderbuffer[K]=s.createRenderbuffer(),s.bindRenderbuffer(s.RENDERBUFFER,O.__webglColorRenderbuffer[K]);let xt=r.convert(et.format,et.colorSpace),Ut=r.convert(et.type),_t=v(et.internalFormat,xt,Ut,et.normalized,et.colorSpace,R.isXRRenderTarget===!0),yt=Gt(R);s.renderbufferStorageMultisample(s.RENDERBUFFER,yt,_t,R.width,R.height),s.framebufferRenderbuffer(s.FRAMEBUFFER,s.COLOR_ATTACHMENT0+K,s.RENDERBUFFER,O.__webglColorRenderbuffer[K])}s.bindRenderbuffer(s.RENDERBUFFER,null),R.depthBuffer&&(O.__webglDepthRenderbuffer=s.createRenderbuffer(),Bt(O.__webglDepthRenderbuffer,R,!0)),e.bindFramebuffer(s.FRAMEBUFFER,null)}}if(lt){e.bindTexture(s.TEXTURE_CUBE_MAP,G.__webglTexture),Jt(s.TEXTURE_CUBE_MAP,x);for(let K=0;K<6;K++)if(x.mipmaps&&x.mipmaps.length>0)for(let et=0;et<x.mipmaps.length;et++)gt(O.__webglFramebuffer[K][et],R,x,s.COLOR_ATTACHMENT0,s.TEXTURE_CUBE_MAP_POSITIVE_X+K,et);else gt(O.__webglFramebuffer[K],R,x,s.COLOR_ATTACHMENT0,s.TEXTURE_CUBE_MAP_POSITIVE_X+K,0);m(x)&&S(s.TEXTURE_CUBE_MAP),e.unbindTexture()}else if(ft){for(let K=0,et=q.length;K<et;K++){let xt=q[K],Ut=n.get(xt),_t=s.TEXTURE_2D;(R.isWebGL3DRenderTarget||R.isWebGLArrayRenderTarget)&&(_t=R.isWebGL3DRenderTarget?s.TEXTURE_3D:s.TEXTURE_2D_ARRAY),e.bindTexture(_t,Ut.__webglTexture),Jt(_t,xt),gt(O.__webglFramebuffer,R,xt,s.COLOR_ATTACHMENT0+K,_t,0),m(xt)&&S(_t)}e.unbindTexture()}else{let K=s.TEXTURE_2D;if((R.isWebGL3DRenderTarget||R.isWebGLArrayRenderTarget)&&(K=R.isWebGL3DRenderTarget?s.TEXTURE_3D:s.TEXTURE_2D_ARRAY),e.bindTexture(K,G.__webglTexture),Jt(K,x),x.mipmaps&&x.mipmaps.length>0)for(let et=0;et<x.mipmaps.length;et++)gt(O.__webglFramebuffer[et],R,x,s.COLOR_ATTACHMENT0,K,et);else gt(O.__webglFramebuffer,R,x,s.COLOR_ATTACHMENT0,K,0);m(x)&&S(K),e.unbindTexture()}R.depthBuffer&&j(R)}function dt(R){let x=R.textures;for(let O=0,G=x.length;O<G;O++){let q=x[O];if(m(q)){let lt=T(R),ft=n.get(q).__webglTexture;e.bindTexture(lt,ft),S(lt),e.unbindTexture()}}}let pt=[],Ht=[];function kt(R){if(R.samples>0){if(z(R)===!1){let x=R.textures,O=R.width,G=R.height,q=s.COLOR_BUFFER_BIT,lt=R.stencilBuffer?s.DEPTH_STENCIL_ATTACHMENT:s.DEPTH_ATTACHMENT,ft=n.get(R),K=x.length>1;if(K)for(let xt=0;xt<x.length;xt++)e.bindFramebuffer(s.FRAMEBUFFER,ft.__webglMultisampledFramebuffer),s.framebufferRenderbuffer(s.FRAMEBUFFER,s.COLOR_ATTACHMENT0+xt,s.RENDERBUFFER,null),e.bindFramebuffer(s.FRAMEBUFFER,ft.__webglFramebuffer),s.framebufferTexture2D(s.DRAW_FRAMEBUFFER,s.COLOR_ATTACHMENT0+xt,s.TEXTURE_2D,null,0);e.bindFramebuffer(s.READ_FRAMEBUFFER,ft.__webglMultisampledFramebuffer);let et=R.texture.mipmaps;et&&et.length>0?e.bindFramebuffer(s.DRAW_FRAMEBUFFER,ft.__webglFramebuffer[0]):e.bindFramebuffer(s.DRAW_FRAMEBUFFER,ft.__webglFramebuffer);for(let xt=0;xt<x.length;xt++){if(R.resolveDepthBuffer&&(R.depthBuffer&&(q|=s.DEPTH_BUFFER_BIT),R.stencilBuffer&&R.resolveStencilBuffer&&(q|=s.STENCIL_BUFFER_BIT)),K){s.framebufferRenderbuffer(s.READ_FRAMEBUFFER,s.COLOR_ATTACHMENT0,s.RENDERBUFFER,ft.__webglColorRenderbuffer[xt]);let Ut=n.get(x[xt]).__webglTexture;s.framebufferTexture2D(s.DRAW_FRAMEBUFFER,s.COLOR_ATTACHMENT0,s.TEXTURE_2D,Ut,0)}s.blitFramebuffer(0,0,O,G,0,0,O,G,q,s.NEAREST),l===!0&&(pt.length=0,Ht.length=0,pt.push(s.COLOR_ATTACHMENT0+xt),R.depthBuffer&&R.storeMultisampledDepthBuffer===!1&&(pt.push(lt),Ht.push(lt),s.invalidateFramebuffer(s.DRAW_FRAMEBUFFER,Ht)),s.invalidateFramebuffer(s.READ_FRAMEBUFFER,pt))}if(e.bindFramebuffer(s.READ_FRAMEBUFFER,null),e.bindFramebuffer(s.DRAW_FRAMEBUFFER,null),K)for(let xt=0;xt<x.length;xt++){e.bindFramebuffer(s.FRAMEBUFFER,ft.__webglMultisampledFramebuffer),s.framebufferRenderbuffer(s.FRAMEBUFFER,s.COLOR_ATTACHMENT0+xt,s.RENDERBUFFER,ft.__webglColorRenderbuffer[xt]);let Ut=n.get(x[xt]).__webglTexture;e.bindFramebuffer(s.FRAMEBUFFER,ft.__webglFramebuffer),s.framebufferTexture2D(s.DRAW_FRAMEBUFFER,s.COLOR_ATTACHMENT0+xt,s.TEXTURE_2D,Ut,0)}e.bindFramebuffer(s.DRAW_FRAMEBUFFER,ft.__webglMultisampledFramebuffer)}else if(R.depthBuffer&&R.storeMultisampledDepthBuffer===!1&&l){let x=R.stencilBuffer?s.DEPTH_STENCIL_ATTACHMENT:s.DEPTH_ATTACHMENT;s.invalidateFramebuffer(s.DRAW_FRAMEBUFFER,[x])}}}function Gt(R){return Math.min(i.maxSamples,R.samples)}function z(R){let x=n.get(R);return R.samples>0&&t.has("WEBGL_multisampled_render_to_texture")===!0&&x.__useRenderToTexture!==!1}function A(R){let x=a.render.frame;h.get(R)!==x&&(h.set(R,x),R.update())}function Tt(R,x){let O=R.colorSpace,G=R.format,q=R.type;return R.isCompressedTexture===!0||R.isVideoTexture===!0||O!==dr&&O!==ii&&(ne.getTransfer(O)===ce?(G!==vn||q!==rn)&&qt("WebGLTextures: sRGB encoded textures have to use RGBAFormat and UnsignedByteType."):Xt("WebGLTextures: Unsupported texture color space:",O)),x}function Et(R){return typeof HTMLImageElement<"u"&&R instanceof HTMLImageElement?(c.width=R.naturalWidth||R.width,c.height=R.naturalHeight||R.height):typeof VideoFrame<"u"&&R instanceof VideoFrame?(c.width=R.displayWidth,c.height=R.displayHeight):(c.width=R.width,c.height=R.height),c}this.allocateTextureUnit=W,this.resetTextureUnits=k,this.getTextureUnits=L,this.setTextureUnits=B,this.setTexture2D=st,this.setTexture2DArray=Z,this.setTexture3D=Q,this.setTextureCube=it,this.rebindTextures=ot,this.setupRenderTarget=ht,this.updateRenderTargetMipmap=dt,this.updateMultisampleRenderTarget=kt,this.setupDepthRenderbuffer=j,this.setupFrameBufferTexture=gt,this.useMultisampledRTT=z,this.isReversedDepthBuffer=function(){return e.buffers.depth.getReversed()}}function Kx(s,t){function e(n,i=ii){let r,a=ne.getTransfer(i);if(n===rn)return s.UNSIGNED_BYTE;if(n===Co)return s.UNSIGNED_SHORT_4_4_4_4;if(n===Po)return s.UNSIGNED_SHORT_5_5_5_1;if(n===Ic)return s.UNSIGNED_INT_5_9_9_9_REV;if(n===Lc)return s.UNSIGNED_INT_10F_11F_11F_REV;if(n===Cc)return s.BYTE;if(n===Pc)return s.SHORT;if(n===Os)return s.UNSIGNED_SHORT;if(n===Ro)return s.INT;if(n===Dn)return s.UNSIGNED_INT;if(n===yn)return s.FLOAT;if(n===Le)return s.HALF_FLOAT;if(n===Dc)return s.ALPHA;if(n===Uc)return s.RGB;if(n===vn)return s.RGBA;if(n===Hn)return s.DEPTH_COMPONENT;if(n===Ri)return s.DEPTH_STENCIL;if(n===Io)return s.RED;if(n===Lo)return s.RED_INTEGER;if(n===Ci)return s.RG;if(n===Do)return s.RG_INTEGER;if(n===Uo)return s.RGBA_INTEGER;if(n===Qr||n===jr||n===ta||n===ea)if(a===ce)if(r=t.get("WEBGL_compressed_texture_s3tc_srgb"),r!==null){if(n===Qr)return r.COMPRESSED_SRGB_S3TC_DXT1_EXT;if(n===jr)return r.COMPRESSED_SRGB_ALPHA_S3TC_DXT1_EXT;if(n===ta)return r.COMPRESSED_SRGB_ALPHA_S3TC_DXT3_EXT;if(n===ea)return r.COMPRESSED_SRGB_ALPHA_S3TC_DXT5_EXT}else return null;else if(r=t.get("WEBGL_compressed_texture_s3tc"),r!==null){if(n===Qr)return r.COMPRESSED_RGB_S3TC_DXT1_EXT;if(n===jr)return r.COMPRESSED_RGBA_S3TC_DXT1_EXT;if(n===ta)return r.COMPRESSED_RGBA_S3TC_DXT3_EXT;if(n===ea)return r.COMPRESSED_RGBA_S3TC_DXT5_EXT}else return null;if(n===No||n===Fo||n===Bo||n===Oo)if(r=t.get("WEBGL_compressed_texture_pvrtc"),r!==null){if(n===No)return r.COMPRESSED_RGB_PVRTC_4BPPV1_IMG;if(n===Fo)return r.COMPRESSED_RGB_PVRTC_2BPPV1_IMG;if(n===Bo)return r.COMPRESSED_RGBA_PVRTC_4BPPV1_IMG;if(n===Oo)return r.COMPRESSED_RGBA_PVRTC_2BPPV1_IMG}else return null;if(n===ko||n===zo||n===Ho||n===Vo||n===Go||n===na||n===Wo)if(r=t.get("WEBGL_compressed_texture_etc"),r!==null){if(n===ko||n===zo)return a===ce?r.COMPRESSED_SRGB8_ETC2:r.COMPRESSED_RGB8_ETC2;if(n===Ho)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ETC2_EAC:r.COMPRESSED_RGBA8_ETC2_EAC;if(n===Vo)return r.COMPRESSED_R11_EAC;if(n===Go)return r.COMPRESSED_SIGNED_R11_EAC;if(n===na)return r.COMPRESSED_RG11_EAC;if(n===Wo)return r.COMPRESSED_SIGNED_RG11_EAC}else return null;if(n===Xo||n===qo||n===Yo||n===Zo||n===Jo||n===$o||n===Ko||n===Qo||n===jo||n===tl||n===el||n===nl||n===il||n===sl)if(r=t.get("WEBGL_compressed_texture_astc"),r!==null){if(n===Xo)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_4x4_KHR:r.COMPRESSED_RGBA_ASTC_4x4_KHR;if(n===qo)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_5x4_KHR:r.COMPRESSED_RGBA_ASTC_5x4_KHR;if(n===Yo)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_5x5_KHR:r.COMPRESSED_RGBA_ASTC_5x5_KHR;if(n===Zo)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_6x5_KHR:r.COMPRESSED_RGBA_ASTC_6x5_KHR;if(n===Jo)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_6x6_KHR:r.COMPRESSED_RGBA_ASTC_6x6_KHR;if(n===$o)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_8x5_KHR:r.COMPRESSED_RGBA_ASTC_8x5_KHR;if(n===Ko)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_8x6_KHR:r.COMPRESSED_RGBA_ASTC_8x6_KHR;if(n===Qo)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_8x8_KHR:r.COMPRESSED_RGBA_ASTC_8x8_KHR;if(n===jo)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_10x5_KHR:r.COMPRESSED_RGBA_ASTC_10x5_KHR;if(n===tl)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_10x6_KHR:r.COMPRESSED_RGBA_ASTC_10x6_KHR;if(n===el)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_10x8_KHR:r.COMPRESSED_RGBA_ASTC_10x8_KHR;if(n===nl)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_10x10_KHR:r.COMPRESSED_RGBA_ASTC_10x10_KHR;if(n===il)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_12x10_KHR:r.COMPRESSED_RGBA_ASTC_12x10_KHR;if(n===sl)return a===ce?r.COMPRESSED_SRGB8_ALPHA8_ASTC_12x12_KHR:r.COMPRESSED_RGBA_ASTC_12x12_KHR}else return null;if(n===rl||n===al||n===ol)if(r=t.get("EXT_texture_compression_bptc"),r!==null){if(n===rl)return a===ce?r.COMPRESSED_SRGB_ALPHA_BPTC_UNORM_EXT:r.COMPRESSED_RGBA_BPTC_UNORM_EXT;if(n===al)return r.COMPRESSED_RGB_BPTC_SIGNED_FLOAT_EXT;if(n===ol)return r.COMPRESSED_RGB_BPTC_UNSIGNED_FLOAT_EXT}else return null;if(n===ll||n===cl||n===ia||n===hl)if(r=t.get("EXT_texture_compression_rgtc"),r!==null){if(n===ll)return r.COMPRESSED_RED_RGTC1_EXT;if(n===cl)return r.COMPRESSED_SIGNED_RED_RGTC1_EXT;if(n===ia)return r.COMPRESSED_RED_GREEN_RGTC2_EXT;if(n===hl)return r.COMPRESSED_SIGNED_RED_GREEN_RGTC2_EXT}else return null;return n===ks?s.UNSIGNED_INT_24_8:s[n]!==void 0?s[n]:null}return{convert:e}}var Qx=`
void main() {

	gl_Position = vec4( position, 1.0 );

}`,jx=`
uniform sampler2DArray depthColor;
uniform float depthWidth;
uniform float depthHeight;

void main() {

	vec2 coord = vec2( gl_FragCoord.x / depthWidth, gl_FragCoord.y / depthHeight );

	if ( coord.x >= 1.0 ) {

		gl_FragDepth = texture( depthColor, vec3( coord.x - 1.0, coord.y, 1 ) ).r;

	} else {

		gl_FragDepth = texture( depthColor, vec3( coord.x, coord.y, 0 ) ).r;

	}

}`,ah=class{constructor(){this.texture=null,this.mesh=null,this.depthNear=0,this.depthFar=0}init(t,e){if(this.texture===null){let n=new Tr(t.texture);(t.depthNear!==e.depthNear||t.depthFar!==e.depthFar)&&(this.depthNear=t.depthNear,this.depthFar=t.depthFar),this.texture=n}}getMesh(t){if(this.texture!==null&&this.mesh===null){let e=t.cameras[0].viewport,n=new Ce({vertexShader:Qx,fragmentShader:jx,uniforms:{depthColor:{value:this.texture},depthWidth:{value:e.z},depthHeight:{value:e.w}}});this.mesh=new ut(new Ke(20,20),n)}return this.mesh}reset(){this.texture=null,this.mesh=null}getDepthTexture(){return this.texture}},oh=class extends Vn{constructor(t,e){super();let n=this,i=null,r=1,a=null,o="local-floor",l=1,c=null,h=null,u=null,d=null,f=null,p=null,y=typeof XRWebGLBinding<"u",g=new ah,m={},S=e.getContextAttributes(),T=null,v=null,b=[],w=[],P=new rt,_=null,E=null,C=new Oe;C.viewport=new Se;let I=new Oe;I.viewport=new Se;let U=[C,I],k=new So,L=null,B=null;this.cameraAutoUpdate=!0,this.enabled=!1,this.isPresenting=!1,this.getController=function(J){let $=b[J];return $===void 0&&($=new As,b[J]=$),$.getTargetRaySpace()},this.getControllerGrip=function(J){let $=b[J];return $===void 0&&($=new As,b[J]=$),$.getGripSpace()},this.getHand=function(J){let $=b[J];return $===void 0&&($=new As,b[J]=$),$.getHandSpace()};function W(J){let $=w.indexOf(J.inputSource);if($===-1)return;let mt=b[$];mt!==void 0&&(mt.update(J.inputSource,J.frame,c||a),mt.dispatchEvent({type:J.type,data:J.inputSource}))}function X(){i.removeEventListener("select",W),i.removeEventListener("selectstart",W),i.removeEventListener("selectend",W),i.removeEventListener("squeeze",W),i.removeEventListener("squeezestart",W),i.removeEventListener("squeezeend",W),i.removeEventListener("end",X),i.removeEventListener("inputsourceschange",st);for(let J=0;J<b.length;J++){let $=w[J];$!==null&&(w[J]=null,b[J].disconnect($))}L=null,B=null,g.reset();for(let J in m)delete m[J];if(t.setRenderTarget(T),f=null,d=null,u=null,i=null,v=null,Qt.stop(),n.isPresenting=!1,t.setPixelRatio(_),t.setSize(P.width,P.height,!1),E!==null){let J=E.camera;J.fov=E.fov,J.zoom=E.zoom,J.updateProjectionMatrix(),E=null}n.dispatchEvent({type:"sessionend"})}this.setFramebufferScaleFactor=function(J){r=J,n.isPresenting===!0&&qt("WebXRManager: Cannot change framebuffer scale while presenting.")},this.setReferenceSpaceType=function(J){o=J,n.isPresenting===!0&&qt("WebXRManager: Cannot change reference space type while presenting.")},this.getReferenceSpace=function(){return c||a},this.setReferenceSpace=function(J){c=J},this.getBaseLayer=function(){return d!==null?d:f},this.getBinding=function(){return u===null&&y&&(u=new XRWebGLBinding(i,e)),u},this.getFrame=function(){return p},this.getSession=function(){return i},this.setSession=async function(J){if(i=J,i!==null){if(T=t.getRenderTarget(),i.addEventListener("select",W),i.addEventListener("selectstart",W),i.addEventListener("selectend",W),i.addEventListener("squeeze",W),i.addEventListener("squeezestart",W),i.addEventListener("squeezeend",W),i.addEventListener("end",X),i.addEventListener("inputsourceschange",st),S.xrCompatible!==!0&&await e.makeXRCompatible(),_=t.getPixelRatio(),t.getSize(P),y&&"createProjectionLayer"in XRWebGLBinding.prototype){let mt=null,Lt=null,gt=null;S.depth&&(gt=S.stencil?e.DEPTH24_STENCIL8:e.DEPTH_COMPONENT24,mt=S.stencil?Ri:Hn,Lt=S.stencil?ks:Dn);let Bt={colorFormat:e.RGBA8,depthFormat:gt,scaleFactor:r};u=this.getBinding(),d=u.createProjectionLayer(Bt),i.updateRenderState({layers:[d]}),t.setPixelRatio(1),t.setSize(d.textureWidth,d.textureHeight,!1),v=new Te(d.textureWidth,d.textureHeight,{format:vn,type:rn,depthTexture:new _i(d.textureWidth,d.textureHeight,Lt,void 0,void 0,void 0,void 0,void 0,void 0,mt),stencilBuffer:S.stencil,colorSpace:t.outputColorSpace,samples:S.antialias?4:0,resolveDepthBuffer:d.ignoreDepthValues===!1,resolveStencilBuffer:d.ignoreDepthValues===!1,storeMultisampledDepthBuffer:d.ignoreDepthValues===!1,storeMultisampledStencilBuffer:d.ignoreDepthValues===!1})}else{let mt={antialias:S.antialias,alpha:!0,depth:S.depth,stencil:S.stencil,framebufferScaleFactor:r};f=new XRWebGLLayer(i,e,mt),i.updateRenderState({baseLayer:f}),t.setPixelRatio(1),t.setSize(f.framebufferWidth,f.framebufferHeight,!1),v=new Te(f.framebufferWidth,f.framebufferHeight,{format:vn,type:rn,colorSpace:t.outputColorSpace,stencilBuffer:S.stencil,resolveDepthBuffer:f.ignoreDepthValues===!1,resolveStencilBuffer:f.ignoreDepthValues===!1,storeMultisampledDepthBuffer:f.ignoreDepthValues===!1,storeMultisampledStencilBuffer:f.ignoreDepthValues===!1})}v.isXRRenderTarget=!0,this.setFoveation(l),c=null,a=await i.requestReferenceSpace(o),Qt.setContext(i),Qt.start(),n.isPresenting=!0,n.dispatchEvent({type:"sessionstart"})}},this.getEnvironmentBlendMode=function(){if(i!==null)return i.environmentBlendMode},this.getDepthTexture=function(){return g.getDepthTexture()};function st(J){for(let $=0;$<J.removed.length;$++){let mt=J.removed[$],Lt=w.indexOf(mt);Lt>=0&&(w[Lt]=null,b[Lt].disconnect(mt))}for(let $=0;$<J.added.length;$++){let mt=J.added[$],Lt=w.indexOf(mt);if(Lt===-1){for(let Bt=0;Bt<b.length;Bt++)if(Bt>=w.length){w.push(mt),Lt=Bt;break}else if(w[Bt]===null){w[Bt]=mt,Lt=Bt;break}if(Lt===-1)break}let gt=b[Lt];gt&&gt.connect(mt)}}let Z=new D,Q=new D;function it(J,$,mt){Z.setFromMatrixPosition($.matrixWorld),Q.setFromMatrixPosition(mt.matrixWorld);let Lt=Z.distanceTo(Q),gt=$.projectionMatrix.elements,Bt=mt.projectionMatrix.elements,ae=gt[14]/(gt[10]-1),j=gt[14]/(gt[10]+1),ot=(gt[9]+1)/gt[5],ht=(gt[9]-1)/gt[5],dt=(gt[8]-1)/gt[0],pt=(Bt[8]+1)/Bt[0],Ht=ae*dt,kt=ae*pt,Gt=Lt/(-dt+pt),z=Gt*-dt;if($.matrixWorld.decompose(J.position,J.quaternion,J.scale),J.translateX(z),J.translateZ(Gt),J.matrixWorld.compose(J.position,J.quaternion,J.scale),J.matrixWorldInverse.copy(J.matrixWorld).invert(),gt[10]===-1)J.projectionMatrix.copy($.projectionMatrix),J.projectionMatrixInverse.copy($.projectionMatrixInverse);else{let A=ae+Gt,Tt=j+Gt,Et=Ht-z,R=kt+(Lt-z),x=ot*j/Tt*A,O=ht*j/Tt*A;J.projectionMatrix.makePerspective(Et,R,x,O,A,Tt),J.projectionMatrixInverse.copy(J.projectionMatrix).invert()}}function nt(J,$){$===null?J.matrixWorld.copy(J.matrix):J.matrixWorld.multiplyMatrices($.matrixWorld,J.matrix),J.matrixWorldInverse.copy(J.matrixWorld).invert()}this.updateCamera=function(J){if(i===null)return;let $=J.near,mt=J.far;g.texture!==null&&(g.depthNear>0&&($=g.depthNear),g.depthFar>0&&(mt=g.depthFar)),k.near=I.near=C.near=$,k.far=I.far=C.far=mt,(L!==k.near||B!==k.far)&&(i.updateRenderState({depthNear:k.near,depthFar:k.far}),L=k.near,B=k.far),k.layers.mask=J.layers.mask|6,C.layers.mask=k.layers.mask&-5,I.layers.mask=k.layers.mask&-3;let Lt=J.parent,gt=k.cameras;nt(k,Lt);for(let Bt=0;Bt<gt.length;Bt++)nt(gt[Bt],Lt);gt.length===2?it(k,C,I):k.projectionMatrix.copy(C.projectionMatrix),E===null&&J.isPerspectiveCamera&&(E={camera:J,fov:J.fov,zoom:J.zoom}),ct(J,k,Lt)};function ct(J,$,mt){mt===null?J.matrix.copy($.matrixWorld):(J.matrix.copy(mt.matrixWorld),J.matrix.invert(),J.matrix.multiply($.matrixWorld)),J.matrix.decompose(J.position,J.quaternion,J.scale),J.updateMatrixWorld(!0),J.projectionMatrix.copy($.projectionMatrix),J.projectionMatrixInverse.copy($.projectionMatrixInverse),J.isPerspectiveCamera&&(J.fov=gr*2*Math.atan(1/J.projectionMatrix.elements[5]),J.zoom=1)}this.getCamera=function(){return k},this.getFoveation=function(){if(!(d===null&&f===null))return l},this.setFoveation=function(J){l=J,d!==null&&(d.fixedFoveation=J),f!==null&&f.fixedFoveation!==void 0&&(f.fixedFoveation=J)},this.hasDepthSensing=function(){return g.texture!==null},this.getDepthSensingMesh=function(){return g.getMesh(k)},this.getCameraTexture=function(J){return m[J]};let Yt=null;function Jt(J,$){if(h=$.getViewerPose(c||a),p=$,h!==null){let mt=h.views;f!==null&&(t.setRenderTargetFramebuffer(v,f.framebuffer),t.setRenderTarget(v));let Lt=!1;mt.length!==k.cameras.length&&(k.cameras.length=0,Lt=!0);for(let j=0;j<mt.length;j++){let ot=mt[j],ht=null;if(f!==null)ht=f.getViewport(ot);else{let pt=u.getViewSubImage(d,ot);ht=pt.viewport,j===0&&(t.setRenderTargetTextures(v,pt.colorTexture,pt.depthStencilTexture),t.setRenderTarget(v))}let dt=U[j];dt===void 0&&(dt=new Oe,dt.layers.enable(j),dt.viewport=new Se,U[j]=dt),dt.matrix.fromArray(ot.transform.matrix),dt.matrix.decompose(dt.position,dt.quaternion,dt.scale),dt.projectionMatrix.fromArray(ot.projectionMatrix),dt.projectionMatrixInverse.copy(dt.projectionMatrix).invert(),dt.viewport.set(ht.x,ht.y,ht.width,ht.height),j===0&&(k.matrix.copy(dt.matrix),k.matrix.decompose(k.position,k.quaternion,k.scale)),Lt===!0&&k.cameras.push(dt)}let gt=i.enabledFeatures;if(gt&&gt.includes("depth-sensing")&&i.depthUsage=="gpu-optimized"&&y){u=n.getBinding();let j=u.getDepthInformation(mt[0]);j&&j.isValid&&j.texture&&g.init(j,i.renderState)}if(gt&&gt.includes("camera-access")&&y){t.state.unbindTexture(),u=n.getBinding();for(let j=0;j<mt.length;j++){let ot=mt[j].camera;if(ot){let ht=m[ot];ht||(ht=new Tr,m[ot]=ht);let dt=u.getCameraImage(ot);ht.sourceTexture=dt}}}}for(let mt=0;mt<b.length;mt++){let Lt=w[mt],gt=b[mt];Lt!==null&&gt!==void 0&&gt.update(Lt,$,c||a)}Yt&&Yt(J,$),$.detectedPlanes&&n.dispatchEvent({type:"planesdetected",data:$}),p=null}let Qt=new gd;Qt.setAnimationLoop(Jt),this.setAnimationLoop=function(J){Yt=J},this.dispose=function(){}}},t_=new fe,Sd=new $t;Sd.set(-1,0,0,0,1,0,0,0,1);function e_(s,t){function e(g,m){g.matrixAutoUpdate===!0&&g.updateMatrix(),m.value.copy(g.matrix)}function n(g,m){m.color.getRGB(g.fogColor.value,Oc(s)),m.isFog?(g.fogNear.value=m.near,g.fogFar.value=m.far):m.isFogExp2&&(g.fogDensity.value=m.density)}function i(g,m,S,T,v){m.isNodeMaterial?m.uniformsNeedUpdate=!1:m.isMeshBasicMaterial?r(g,m):m.isMeshLambertMaterial?(r(g,m),m.envMap&&(g.envMapIntensity.value=m.envMapIntensity)):m.isMeshToonMaterial?(r(g,m),u(g,m)):m.isMeshPhongMaterial?(r(g,m),h(g,m),m.envMap&&(g.envMapIntensity.value=m.envMapIntensity)):m.isMeshStandardMaterial?(r(g,m),d(g,m),m.isMeshPhysicalMaterial&&f(g,m,v)):m.isMeshMatcapMaterial?(r(g,m),p(g,m)):m.isMeshDepthMaterial?r(g,m):m.isMeshDistanceMaterial?(r(g,m),y(g,m)):m.isMeshNormalMaterial?r(g,m):m.isLineBasicMaterial?(a(g,m),m.isLineDashedMaterial&&o(g,m)):m.isPointsMaterial?l(g,m,S,T):m.isSpriteMaterial?c(g,m):m.isShadowMaterial?(g.color.value.copy(m.color),g.opacity.value=m.opacity):m.isShaderMaterial&&(m.uniformsNeedUpdate=!1)}function r(g,m){g.opacity.value=m.opacity,m.color&&g.diffuse.value.copy(m.color),m.emissive&&g.emissive.value.copy(m.emissive).multiplyScalar(m.emissiveIntensity),m.map&&(g.map.value=m.map,e(m.map,g.mapTransform)),m.alphaMap&&(g.alphaMap.value=m.alphaMap,e(m.alphaMap,g.alphaMapTransform)),m.bumpMap&&(g.bumpMap.value=m.bumpMap,e(m.bumpMap,g.bumpMapTransform),g.bumpScale.value=m.bumpScale,m.side===We&&(g.bumpScale.value*=-1)),m.normalMap&&(g.normalMap.value=m.normalMap,e(m.normalMap,g.normalMapTransform),g.normalScale.value.copy(m.normalScale),m.side===We&&g.normalScale.value.negate()),m.displacementMap&&(g.displacementMap.value=m.displacementMap,e(m.displacementMap,g.displacementMapTransform),g.displacementScale.value=m.displacementScale,g.displacementBias.value=m.displacementBias),m.emissiveMap&&(g.emissiveMap.value=m.emissiveMap,e(m.emissiveMap,g.emissiveMapTransform)),m.specularMap&&(g.specularMap.value=m.specularMap,e(m.specularMap,g.specularMapTransform)),m.alphaTest>0&&(g.alphaTest.value=m.alphaTest);let S=t.get(m),T=S.envMap,v=S.envMapRotation;T&&(g.envMap.value=T,g.envMapRotation.value.setFromMatrix4(t_.makeRotationFromEuler(v)).transpose(),T.isCubeTexture&&T.isRenderTargetTexture===!1&&g.envMapRotation.value.premultiply(Sd),g.reflectivity.value=m.reflectivity,g.ior.value=m.ior,g.refractionRatio.value=m.refractionRatio),m.lightMap&&(g.lightMap.value=m.lightMap,g.lightMapIntensity.value=m.lightMapIntensity,e(m.lightMap,g.lightMapTransform)),m.aoMap&&(g.aoMap.value=m.aoMap,g.aoMapIntensity.value=m.aoMapIntensity,e(m.aoMap,g.aoMapTransform))}function a(g,m){g.diffuse.value.copy(m.color),g.opacity.value=m.opacity,m.map&&(g.map.value=m.map,e(m.map,g.mapTransform))}function o(g,m){g.dashSize.value=m.dashSize,g.totalSize.value=m.dashSize+m.gapSize,g.scale.value=m.scale}function l(g,m,S,T){g.diffuse.value.copy(m.color),g.opacity.value=m.opacity,g.size.value=m.size*S,g.scale.value=T*.5,m.map&&(g.map.value=m.map,e(m.map,g.uvTransform)),m.alphaMap&&(g.alphaMap.value=m.alphaMap,e(m.alphaMap,g.alphaMapTransform)),m.alphaTest>0&&(g.alphaTest.value=m.alphaTest)}function c(g,m){g.diffuse.value.copy(m.color),g.opacity.value=m.opacity,g.rotation.value=m.rotation,m.map&&(g.map.value=m.map,e(m.map,g.mapTransform)),m.alphaMap&&(g.alphaMap.value=m.alphaMap,e(m.alphaMap,g.alphaMapTransform)),m.alphaTest>0&&(g.alphaTest.value=m.alphaTest)}function h(g,m){g.specular.value.copy(m.specular),g.shininess.value=Math.max(m.shininess,1e-4)}function u(g,m){m.gradientMap&&(g.gradientMap.value=m.gradientMap)}function d(g,m){g.metalness.value=m.metalness,m.metalnessMap&&(g.metalnessMap.value=m.metalnessMap,e(m.metalnessMap,g.metalnessMapTransform)),g.roughness.value=m.roughness,m.roughnessMap&&(g.roughnessMap.value=m.roughnessMap,e(m.roughnessMap,g.roughnessMapTransform)),m.envMap&&(g.envMapIntensity.value=m.envMapIntensity)}function f(g,m,S){g.ior.value=m.ior,m.sheen>0&&(g.sheenColor.value.copy(m.sheenColor).multiplyScalar(m.sheen),g.sheenRoughness.value=m.sheenRoughness,m.sheenColorMap&&(g.sheenColorMap.value=m.sheenColorMap,e(m.sheenColorMap,g.sheenColorMapTransform)),m.sheenRoughnessMap&&(g.sheenRoughnessMap.value=m.sheenRoughnessMap,e(m.sheenRoughnessMap,g.sheenRoughnessMapTransform))),m.clearcoat>0&&(g.clearcoat.value=m.clearcoat,g.clearcoatRoughness.value=m.clearcoatRoughness,m.clearcoatMap&&(g.clearcoatMap.value=m.clearcoatMap,e(m.clearcoatMap,g.clearcoatMapTransform)),m.clearcoatRoughnessMap&&(g.clearcoatRoughnessMap.value=m.clearcoatRoughnessMap,e(m.clearcoatRoughnessMap,g.clearcoatRoughnessMapTransform)),m.clearcoatNormalMap&&(g.clearcoatNormalMap.value=m.clearcoatNormalMap,e(m.clearcoatNormalMap,g.clearcoatNormalMapTransform),g.clearcoatNormalScale.value.copy(m.clearcoatNormalScale),m.side===We&&g.clearcoatNormalScale.value.negate())),m.dispersion>0&&(g.dispersion.value=m.dispersion),m.retroreflectivity>0&&(g.retroreflectivity.value=m.retroreflectivity),m.iridescence>0&&(g.iridescence.value=m.iridescence,g.iridescenceIOR.value=m.iridescenceIOR,g.iridescenceThicknessMinimum.value=m.iridescenceThicknessRange[0],g.iridescenceThicknessMaximum.value=m.iridescenceThicknessRange[1],m.iridescenceMap&&(g.iridescenceMap.value=m.iridescenceMap,e(m.iridescenceMap,g.iridescenceMapTransform)),m.iridescenceThicknessMap&&(g.iridescenceThicknessMap.value=m.iridescenceThicknessMap,e(m.iridescenceThicknessMap,g.iridescenceThicknessMapTransform))),m.transmission>0&&(g.transmission.value=m.transmission,g.transmissionSamplerMap.value=S.texture,g.transmissionSamplerSize.value.set(S.width,S.height),m.transmissionMap&&(g.transmissionMap.value=m.transmissionMap,e(m.transmissionMap,g.transmissionMapTransform)),g.thickness.value=m.thickness,m.thicknessMap&&(g.thicknessMap.value=m.thicknessMap,e(m.thicknessMap,g.thicknessMapTransform)),g.attenuationDistance.value=m.attenuationDistance,g.attenuationColor.value.copy(m.attenuationColor)),m.anisotropy>0&&(g.anisotropyVector.value.set(m.anisotropy*Math.cos(m.anisotropyRotation),m.anisotropy*Math.sin(m.anisotropyRotation)),m.anisotropyMap&&(g.anisotropyMap.value=m.anisotropyMap,e(m.anisotropyMap,g.anisotropyMapTransform))),g.specularIntensity.value=m.specularIntensity,g.specularColor.value.copy(m.specularColor),m.specularColorMap&&(g.specularColorMap.value=m.specularColorMap,e(m.specularColorMap,g.specularColorMapTransform)),m.specularIntensityMap&&(g.specularIntensityMap.value=m.specularIntensityMap,e(m.specularIntensityMap,g.specularIntensityMapTransform))}function p(g,m){m.matcap&&(g.matcap.value=m.matcap)}function y(g,m){let S=t.get(m).light;g.referencePosition.value.setFromMatrixPosition(S.matrixWorld),g.nearDistance.value=S.shadow.camera.near,g.farDistance.value=S.shadow.camera.far}return{refreshFogUniforms:n,refreshMaterialUniforms:i}}function n_(s,t,e,n){let i={},r={},a=[],o=s.getParameter(s.MAX_UNIFORM_BUFFER_BINDINGS);function l(v,b){let w=b.program;n.uniformBlockBinding(v,w)}function c(v,b){let w=i[v.id];w===void 0&&(g(v),w=h(v),i[v.id]=w,v.addEventListener("dispose",S));let P=b.program;n.updateUBOMapping(v,P);let _=t.render.frame;r[v.id]!==_&&(d(v),r[v.id]=_)}function h(v){let b=u();v.__bindingPointIndex=b;let w=s.createBuffer(),P=v.__size,_=v.usage;return s.bindBuffer(s.UNIFORM_BUFFER,w),s.bufferData(s.UNIFORM_BUFFER,P,_),s.bindBuffer(s.UNIFORM_BUFFER,null),s.bindBufferBase(s.UNIFORM_BUFFER,b,w),w}function u(){for(let v=0;v<o;v++)if(a.indexOf(v)===-1)return a.push(v),v;return Xt("WebGLRenderer: Maximum number of simultaneously usable uniforms groups reached."),0}function d(v){let b=i[v.id],w=v.uniforms,P=v.__cache;s.bindBuffer(s.UNIFORM_BUFFER,b);for(let _=0,E=w.length;_<E;_++){let C=w[_];if(Array.isArray(C))for(let I=0,U=C.length;I<U;I++)f(C[I],_,I,P);else f(C,_,0,P)}s.bindBuffer(s.UNIFORM_BUFFER,null)}function f(v,b,w,P){if(y(v,b,w,P)===!0){let _=v.__offset,E=v.value;if(Array.isArray(E)){let C=0;for(let I=0;I<E.length;I++){let U=E[I],k=m(U);p(U,v.__data,C),typeof U!="number"&&typeof U!="boolean"&&!U.isMatrix3&&!ArrayBuffer.isView(U)&&(C+=k.storage/Float32Array.BYTES_PER_ELEMENT)}}else p(E,v.__data,0);s.bufferSubData(s.UNIFORM_BUFFER,_,v.__data)}}function p(v,b,w){typeof v=="number"||typeof v=="boolean"?b[0]=v:v.isMatrix3?(b[0]=v.elements[0],b[1]=v.elements[1],b[2]=v.elements[2],b[3]=0,b[4]=v.elements[3],b[5]=v.elements[4],b[6]=v.elements[5],b[7]=0,b[8]=v.elements[6],b[9]=v.elements[7],b[10]=v.elements[8],b[11]=0):ArrayBuffer.isView(v)?b.set(new v.constructor(v.buffer,v.byteOffset,b.length)):v.toArray(b,w)}function y(v,b,w,P){let _=v.value,E=b+"_"+w;if(P[E]===void 0)return typeof _=="number"||typeof _=="boolean"?P[E]=_:ArrayBuffer.isView(_)?P[E]=_.slice():P[E]=_.clone(),!0;{let C=P[E];if(typeof _=="number"||typeof _=="boolean"){if(C!==_)return P[E]=_,!0}else{if(ArrayBuffer.isView(_))return!0;if(C.equals(_)===!1)return C.copy(_),!0}}return!1}function g(v){let b=v.uniforms,w=0,P=16;for(let E=0,C=b.length;E<C;E++){let I=Array.isArray(b[E])?b[E]:[b[E]];for(let U=0,k=I.length;U<k;U++){let L=I[U],B=Array.isArray(L.value)?L.value:[L.value];for(let W=0,X=B.length;W<X;W++){let st=B[W],Z=m(st),Q=w%P,it=Q%Z.boundary,nt=Q+it;w+=it,nt!==0&&P-nt<Z.storage&&(w+=P-nt),L.__data=new Float32Array(Z.storage/Float32Array.BYTES_PER_ELEMENT),L.__offset=w,w+=Z.storage}}}let _=w%P;return _>0&&(w+=P-_),v.__size=w,v.__cache={},this}function m(v){let b={boundary:0,storage:0};return typeof v=="number"||typeof v=="boolean"?(b.boundary=4,b.storage=4):v.isVector2?(b.boundary=8,b.storage=8):v.isVector3||v.isColor?(b.boundary=16,b.storage=12):v.isVector4?(b.boundary=16,b.storage=16):v.isMatrix3?(b.boundary=48,b.storage=48):v.isMatrix4?(b.boundary=64,b.storage=64):v.isTexture?qt("WebGLRenderer: Texture samplers can not be part of an uniforms group."):ArrayBuffer.isView(v)?(b.boundary=16,b.storage=v.byteLength):qt("WebGLRenderer: Unsupported uniform value type.",v),b}function S(v){let b=v.target;b.removeEventListener("dispose",S);let w=a.indexOf(b.__bindingPointIndex);a.splice(w,1),s.deleteBuffer(i[b.id]),delete i[b.id],delete r[b.id]}function T(){for(let v in i)s.deleteBuffer(i[v]);a=[],i={},r={}}return{bind:l,update:c,dispose:T}}var i_=new Uint16Array([12469,15057,12620,14925,13266,14620,13807,14376,14323,13990,14545,13625,14713,13328,14840,12882,14931,12528,14996,12233,15039,11829,15066,11525,15080,11295,15085,10976,15082,10705,15073,10495,13880,14564,13898,14542,13977,14430,14158,14124,14393,13732,14556,13410,14702,12996,14814,12596,14891,12291,14937,11834,14957,11489,14958,11194,14943,10803,14921,10506,14893,10278,14858,9960,14484,14039,14487,14025,14499,13941,14524,13740,14574,13468,14654,13106,14743,12678,14818,12344,14867,11893,14889,11509,14893,11180,14881,10751,14852,10428,14812,10128,14765,9754,14712,9466,14764,13480,14764,13475,14766,13440,14766,13347,14769,13070,14786,12713,14816,12387,14844,11957,14860,11549,14868,11215,14855,10751,14825,10403,14782,10044,14729,9651,14666,9352,14599,9029,14967,12835,14966,12831,14963,12804,14954,12723,14936,12564,14917,12347,14900,11958,14886,11569,14878,11247,14859,10765,14828,10401,14784,10011,14727,9600,14660,9289,14586,8893,14508,8533,15111,12234,15110,12234,15104,12216,15092,12156,15067,12010,15028,11776,14981,11500,14942,11205,14902,10752,14861,10393,14812,9991,14752,9570,14682,9252,14603,8808,14519,8445,14431,8145,15209,11449,15208,11451,15202,11451,15190,11438,15163,11384,15117,11274,15055,10979,14994,10648,14932,10343,14871,9936,14803,9532,14729,9218,14645,8742,14556,8381,14461,8020,14365,7603,15273,10603,15272,10607,15267,10619,15256,10631,15231,10614,15182,10535,15118,10389,15042,10167,14963,9787,14883,9447,14800,9115,14710,8665,14615,8318,14514,7911,14411,7507,14279,7198,15314,9675,15313,9683,15309,9712,15298,9759,15277,9797,15229,9773,15166,9668,15084,9487,14995,9274,14898,8910,14800,8539,14697,8234,14590,7790,14479,7409,14367,7067,14178,6621,15337,8619,15337,8631,15333,8677,15325,8769,15305,8871,15264,8940,15202,8909,15119,8775,15022,8565,14916,8328,14804,8009,14688,7614,14569,7287,14448,6888,14321,6483,14088,6171,15350,7402,15350,7419,15347,7480,15340,7613,15322,7804,15287,7973,15229,8057,15148,8012,15046,7846,14933,7611,14810,7357,14682,7069,14552,6656,14421,6316,14251,5948,14007,5528,15356,5942,15356,5977,15353,6119,15348,6294,15332,6551,15302,6824,15249,7044,15171,7122,15070,7050,14949,6861,14818,6611,14679,6349,14538,6067,14398,5651,14189,5311,13935,4958,15359,4123,15359,4153,15356,4296,15353,4646,15338,5160,15311,5508,15263,5829,15188,6042,15088,6094,14966,6001,14826,5796,14678,5543,14527,5287,14377,4985,14133,4586,13869,4257,15360,1563,15360,1642,15358,2076,15354,2636,15341,3350,15317,4019,15273,4429,15203,4732,15105,4911,14981,4932,14836,4818,14679,4621,14517,4386,14359,4156,14083,3795,13808,3437,15360,122,15360,137,15358,285,15355,636,15344,1274,15322,2177,15281,2765,15215,3223,15120,3451,14995,3569,14846,3567,14681,3466,14511,3305,14344,3121,14037,2800,13753,2467,15360,0,15360,1,15359,21,15355,89,15346,253,15325,479,15287,796,15225,1148,15133,1492,15008,1749,14856,1882,14685,1886,14506,1783,14324,1608,13996,1398,13702,1183]),qn=null;function s_(){return qn===null&&(qn=new Er(i_,16,16,Ci,Le),qn.name="DFG_LUT",qn.minFilter=Ge,qn.magFilter=Ge,qn.wrapS=On,qn.wrapT=On,qn.generateMipmaps=!1,qn.needsUpdate=!0),qn}var gl=class{constructor(t={}){let{canvas:e=Bu(),context:n=null,depth:i=!0,stencil:r=!1,alpha:a=!1,antialias:o=!1,premultipliedAlpha:l=!0,preserveDrawingBuffer:c=!1,powerPreference:h="default",failIfMajorPerformanceCaveat:u=!1,reversedDepthBuffer:d=!1,outputBufferType:f=rn}=t;this.isWebGLRenderer=!0;let p;if(n!==null){if(typeof WebGLRenderingContext<"u"&&n instanceof WebGLRenderingContext)throw new Error("THREE.WebGLRenderer: WebGL 1 is not supported since r163.");p=n.getContextAttributes().alpha}else p=a;let y=f,g=new Set([Uo,Do,Lo]),m=new Set([rn,Dn,Os,ks,Co,Po]),S=new Uint32Array(4),T=new Int32Array(4),v=new D,b=null,w=null,P=[],_=[],E=null;this.domElement=e,this.debug={checkShaderErrors:!0,diagnostics:{keywords:!1},onShaderError:null},this.autoClear=!0,this.autoClearColor=!0,this.autoClearDepth=!0,this.autoClearStencil=!0,this.sortObjects=!0,this.clippingPlanes=[],this.localClippingEnabled=!1,this.toneMapping=Ln,this.toneMappingExposure=1,this.transmissionResolutionScale=1;let C=this,I=!1,U=null,k=null,L=null,B=null;this._outputColorSpace=Be;let W=0,X=0,st=null,Z=-1,Q=null,it=new Se,nt=new Se,ct=null,Yt=new Ft(0),Jt=0,Qt=e.width,J=e.height,$=1,mt=null,Lt=null,gt=new Se(0,0,Qt,J),Bt=new Se(0,0,Qt,J),ae=!1,j=new Ps,ot=!1,ht=!1,dt=new fe,pt=new D,Ht=new Se,kt={background:null,fog:null,environment:null,overrideMaterial:null,isScene:!0},Gt=!1;function z(){return st===null?$:1}let A=n;function Tt(M,N){return e.getContext(M,N)}let Et,R,x,O,G,q,lt,ft,K,et,xt,Ut,_t,yt,Ot,Wt,jt,F,vt,tt,Mt,At,at;try{let M={alpha:!0,depth:i,stencil:r,antialias:o,premultipliedAlpha:l,preserveDrawingBuffer:c,powerPreference:h,failIfMajorPerformanceCaveat:u};if("setAttribute"in e&&e.setAttribute("data-engine",`three.js r${"186"}`),e.addEventListener("webglcontextlost",_e,!1),e.addEventListener("webglcontextrestored",ue,!1),e.addEventListener("webglcontextcreationerror",En,!1),A===null){let N="webgl2";if(A=Tt(N,M),A===null)throw Tt(N)?new Error("THREE.WebGLRenderer: Error creating WebGL context with your selected attributes."):new Error("THREE.WebGLRenderer: Error creating WebGL context.")}zt()}catch(M){throw e.removeEventListener("webglcontextlost",_e,!1),e.removeEventListener("webglcontextrestored",ue,!1),e.removeEventListener("webglcontextcreationerror",En,!1),Xt("WebGLRenderer: "+M.message),M}function zt(){Et=new ug(A),Et.init(),Mt=new Kx(A,Et),R=new eg(A,Et,t,Mt),x=new Jx(A,Et),R.reversedDepthBuffer&&d&&x.buffers.depth.setReversed(!0),k=A.createFramebuffer(),L=A.createFramebuffer(),B=A.createFramebuffer(),O=new pg(A),G=new Nx,q=new $x(A,Et,x,G,R,Mt,O),lt=new hg(C),ft=new gp(A),At=new j0(A,ft),K=new dg(A,ft,O,At),et=new gg(A,K,ft,At,O),F=new mg(A,R,q),Ot=new ng(G),xt=new Ux(C,lt,Et,R,At,Ot),Ut=new e_(C,G),_t=new Bx,yt=new Gx(Et),jt=new Q0(C,lt,x,et,p,l),Wt=new Zx(C,et,R),at=new n_(A,O,R,x),vt=new tg(A,Et,O),tt=new fg(A,Et,O),O.programs=xt.programs,C.capabilities=R,C.extensions=Et,C.properties=G,C.renderLists=_t,C.shadowMap=Wt,C.state=x,C.info=O}y!==rn&&(E=new _g(y,e.width,e.height,o,i,r));let Dt=new oh(C,A);this.xr=Dt,this.getContext=function(){return A},this.getContextAttributes=function(){return A.getContextAttributes()},this.forceContextLoss=function(){let M=Et.get("WEBGL_lose_context");M&&M.loseContext()},this.forceContextRestore=function(){let M=Et.get("WEBGL_lose_context");M&&M.restoreContext()},this.getPixelRatio=function(){return $},this.setPixelRatio=function(M){M!==void 0&&($=M,this.setSize(Qt,J,!1))},this.getSize=function(M){return M.set(Qt,J)},this.setSize=function(M,N,Y=!0){if(Dt.isPresenting){qt("WebGLRenderer: Can't change size while VR device is presenting.");return}Qt=M,J=N,e.width=Math.floor(M*$),e.height=Math.floor(N*$),Y===!0&&(e.style.width=M+"px",e.style.height=N+"px"),E!==null&&E.setSize(e.width,e.height),this.setViewport(0,0,M,N)},this.getDrawingBufferSize=function(M){return M.set(Qt*$,J*$).floor()},this.setDrawingBufferSize=function(M,N,Y){Qt=M,J=N,$=Y,e.width=Math.floor(M*Y),e.height=Math.floor(N*Y),this.setViewport(0,0,M,N)},this.setEffects=function(M){if(y===rn){Xt("WebGLRenderer: setEffects() requires outputBufferType set to HalfFloatType or FloatType.");return}if(M){for(let N=0;N<M.length;N++)if(M[N].isOutputPass===!0){qt("WebGLRenderer: OutputPass is not needed in setEffects(). Tone mapping and color space conversion are applied automatically.");break}}E.setEffects(M||[])},this.getCurrentViewport=function(M){return M.copy(it)},this.getViewport=function(M){return M.copy(gt)},this.setViewport=function(M,N,Y,H){M.isVector4?gt.set(M.x,M.y,M.z,M.w):gt.set(M,N,Y,H),x.viewport(it.copy(gt).multiplyScalar($).round())},this.getScissor=function(M){return M.copy(Bt)},this.setScissor=function(M,N,Y,H){M.isVector4?Bt.set(M.x,M.y,M.z,M.w):Bt.set(M,N,Y,H),x.scissor(nt.copy(Bt).multiplyScalar($).round())},this.getScissorTest=function(){return ae},this.setScissorTest=function(M){x.setScissorTest(ae=M)},this.setOpaqueSort=function(M){mt=M},this.setTransparentSort=function(M){Lt=M},this.getClearColor=function(M){return M.copy(jt.getClearColor())},this.setClearColor=function(){jt.setClearColor(...arguments)},this.getClearAlpha=function(){return jt.getClearAlpha()},this.setClearAlpha=function(){jt.setClearAlpha(...arguments)},this.clear=function(M=!0,N=!0,Y=!0){let H=0;if(M){let V=!1;if(st!==null){let wt=st.texture.format;V=g.has(wt)}if(V){let wt=st.texture.type,Ct=m.has(wt),bt=jt.getClearColor(),Pt=jt.getClearAlpha(),Nt=bt.r,te=bt.g,ie=bt.b;Ct?(S[0]=Nt,S[1]=te,S[2]=ie,S[3]=Pt,A.clearBufferuiv(A.COLOR,0,S)):(T[0]=Nt,T[1]=te,T[2]=ie,T[3]=Pt,A.clearBufferiv(A.COLOR,0,T))}else H|=A.COLOR_BUFFER_BIT}N&&(H|=A.DEPTH_BUFFER_BIT,this.state.buffers.depth.setMask(!0)),Y&&(H|=A.STENCIL_BUFFER_BIT,this.state.buffers.stencil.setMask(4294967295)),H!==0&&A.clear(H)},this.clearColor=function(){this.clear(!0,!1,!1)},this.clearDepth=function(){this.clear(!1,!0,!1)},this.clearStencil=function(){this.clear(!1,!1,!0)},this.setNodesHandler=function(M){M.setRenderer(this),U=M},this.dispose=function(){e.removeEventListener("webglcontextlost",_e,!1),e.removeEventListener("webglcontextrestored",ue,!1),e.removeEventListener("webglcontextcreationerror",En,!1),jt.dispose(),_t.dispose(),yt.dispose(),G.dispose(),lt.dispose(),et.dispose(),At.dispose(),at.dispose(),xt.dispose(),Dt.dispose(),Dt.removeEventListener("sessionstart",hh),Dt.removeEventListener("sessionend",uh),Li.stop()};function _e(M){M.preventDefault(),mr("WebGLRenderer: Context Lost."),I=!0}function ue(){mr("WebGLRenderer: Context Restored."),I=!1;let M=O.autoReset,N=Wt.enabled,Y=Wt.autoUpdate,H=Wt.needsUpdate,V=Wt.type;zt(),O.autoReset=M,Wt.enabled=N,Wt.autoUpdate=Y,Wt.needsUpdate=H,Wt.type=V}function En(M){Xt("WebGLRenderer: A WebGL context could not be created. Reason: ",M.statusMessage)}function Un(M){let N=M.target;N.removeEventListener("dispose",Un),Id(N)}function Id(M){Ld(M),G.remove(M)}function Ld(M){let N=G.get(M).programs;N!==void 0&&(N.forEach(function(Y){xt.releaseProgram(Y)}),M.isShaderMaterial&&xt.releaseShaderCache(M))}this.renderBufferDirect=function(M,N,Y,H,V,wt){N===null&&(N=kt);let Ct=V.isMesh&&V.matrixWorld.determinantAffine()<0,bt=Nd(M,N,Y,H,V);x.setMaterial(H,Ct);let Pt=Y.index,Nt=1;if(H.wireframe===!0){if(Pt=K.getWireframeAttribute(Y),Pt===void 0)return;Nt=2}let te=Y.drawRange,ie=Y.attributes.position,It=te.start*Nt,de=(te.start+te.count)*Nt;wt!==null&&(It=Math.max(It,wt.start*Nt),de=Math.min(de,(wt.start+wt.count)*Nt)),Pt!==null?(It=Math.max(It,0),de=Math.min(de,Pt.count)):ie!=null&&(It=Math.max(It,0),de=Math.min(de,ie.count));let Pe=de-It;if(Pe<0||Pe===1/0)return;At.setup(V,H,bt,Y,Pt);let ve,ge=vt;if(Pt!==null&&(ve=ft.get(Pt),ge=tt,ge.setIndex(ve)),V.isMesh)H.wireframe===!0?(x.setLineWidth(H.wireframeLinewidth*z()),ge.setMode(A.LINES)):ge.setMode(A.TRIANGLES);else if(V.isLine){let qe=H.linewidth;qe===void 0&&(qe=1),x.setLineWidth(qe*z()),V.isLineSegments?ge.setMode(A.LINES):V.isLineLoop?ge.setMode(A.LINE_LOOP):ge.setMode(A.LINE_STRIP)}else V.isPoints?ge.setMode(A.POINTS):V.isSprite&&ge.setMode(A.TRIANGLES);if(V.isBatchedMesh)if(Et.get("WEBGL_multi_draw"))ge.renderMultiDraw(V._multiDrawStarts,V._multiDrawCounts,V._multiDrawCount);else{let qe=V._multiDrawStarts,Rt=V._multiDrawCounts,tn=V._multiDrawCount,oe=Pt?ft.get(Pt).bytesPerElement:1,dn=G.get(H).currentProgram.getUniforms();for(let Nn=0;Nn<tn;Nn++)dn.setValue(A,"_gl_DrawID",Nn),ge.render(qe[Nn]/oe,Rt[Nn])}else if(V.isInstancedMesh)ge.renderInstances(It,Pe,V.count);else if(Y.isInstancedBufferGeometry){let qe=Y._maxInstanceCount!==void 0?Y._maxInstanceCount:1/0,Rt=Math.min(Y.instanceCount,qe);ge.renderInstances(It,Pe,Rt)}else ge.render(It,Pe)};function ch(M,N,Y,H){U!==null&&M.isNodeMaterial&&U.setObject(H,M),ot===!0&&Ot.setState(M,Y,!1),M.transparent===!0&&M.side===He&&M.forceSinglePass===!1?(M.side=We,M.needsUpdate=!0,da(M,N,H),M.side=wi,M.needsUpdate=!0,da(M,N,H),M.side=He):da(M,N,H)}this.compile=function(M,N,Y=null){Y===null&&(Y=M),U!==null&&U.renderStart(M,N,Y),w=yt.get(Y),w.init(N),_.push(w),Y.traverseVisible(function(V){V.isLight&&V.layers.test(N.layers)&&(w.pushLight(V),V.castShadow&&w.pushShadow(V))}),M!==Y&&M.traverseVisible(function(V){V.isLight&&V.layers.test(N.layers)&&(w.pushLight(V),V.castShadow&&w.pushShadow(V))}),w.setupLights(),U!==null&&U.updateLights(w.state.lightsArray),ht=this.localClippingEnabled,ot=Ot.init(this.clippingPlanes,ht),ot===!0&&Ot.setGlobalState(this.clippingPlanes,N),U!==null&&Wt.render(w.state.shadowsArray,Y,N);let H=new Set;return M.traverse(function(V){if(!(V.isMesh||V.isPoints||V.isLine||V.isSprite))return;let wt=V.material;if(wt)if(Array.isArray(wt))for(let Ct=0;Ct<wt.length;Ct++){let bt=wt[Ct];ch(bt,Y,N,V),H.add(bt)}else ch(wt,Y,N,V),H.add(wt)}),w=_.pop(),U!==null&&U.renderEnd(),H},this.compileAsync=function(M,N,Y=null){let H=this.compile(M,N,Y);return new Promise(V=>{function wt(){if(H.forEach(function(Ct){let Pt=G.get(Ct).currentProgram;(Pt===void 0||Pt.isReady())&&H.delete(Ct)}),H.size===0){V(M);return}setTimeout(wt,10)}Et.get("KHR_parallel_shader_compile")!==null?wt():setTimeout(wt,10)})};let Ll=null;function Dd(M){Ll&&Ll(M)}function hh(){Li.stop()}function uh(){Li.start()}let Li=new gd;Li.setAnimationLoop(Dd),typeof self<"u"&&Li.setContext(self),this.setAnimationLoop=function(M){Ll=M,Dt.setAnimationLoop(M),M===null?Li.stop():Li.start()},Dt.addEventListener("sessionstart",hh),Dt.addEventListener("sessionend",uh),this.render=function(M,N){if(N!==void 0&&N.isCamera!==!0){Xt("WebGLRenderer.render: camera is not an instance of THREE.Camera.");return}if(I===!0)return;U!==null&&U.renderStart(M,N);let Y=Dt.enabled===!0&&Dt.isPresenting===!0,H=E!==null&&(st===null||Y)&&E.begin(C,st);if(M.matrixWorldAutoUpdate===!0&&M.updateMatrixWorld(),N.parent===null&&N.matrixWorldAutoUpdate===!0&&N.updateMatrixWorld(),Dt.enabled===!0&&Dt.isPresenting===!0&&(E===null||E.isCompositing()===!1)&&(Dt.cameraAutoUpdate===!0&&Dt.updateCamera(N),N=Dt.getCamera()),M.isScene===!0&&M.onBeforeRender(C,M,N,st),w=yt.get(M,_.length),w.init(N),w.state.textureUnits=q.getTextureUnits(),_.push(w),dt.multiplyMatrices(N.projectionMatrix,N.matrixWorldInverse),j.setFromProjectionMatrix(dt,Cn,N.reversedDepth),ht=this.localClippingEnabled,ot=Ot.init(this.clippingPlanes,ht),b=_t.get(M,P.length),b.init(),P.push(b),Dt.enabled===!0&&Dt.isPresenting===!0){let Ct=C.xr.getDepthSensingMesh();Ct!==null&&Dl(Ct,N,-1/0,C.sortObjects)}Dl(M,N,0,C.sortObjects),b.finish(),U!==null&&U.updateLights(w.state.lightsArray),C.sortObjects===!0&&b.sort(mt,Lt),Gt=Dt.enabled===!1||Dt.isPresenting===!1||Dt.hasDepthSensing()===!1,Gt&&jt.addToRenderList(b,M),this.info.render.frame++,this.info.autoReset===!0&&this.info.reset(),ot===!0&&Ot.beginShadows();let V=w.state.shadowsArray;if(Wt.render(V,M,N),ot===!0&&Ot.endShadows(),(H&&E.hasRenderPass())===!1){let Ct=b.opaque,bt=b.transmissive;if(w.setupLights(),N.isArrayCamera){let Pt=N.cameras;if(bt.length>0)for(let Nt=0,te=Pt.length;Nt<te;Nt++){let ie=Pt[Nt];fh(Ct,bt,M,ie)}Gt&&jt.render(M);for(let Nt=0,te=Pt.length;Nt<te;Nt++){let ie=Pt[Nt];dh(b,M,ie,ie.viewport)}}else bt.length>0&&fh(Ct,bt,M,N),Gt&&jt.render(M),dh(b,M,N)}st!==null&&X===0&&(q.updateMultisampleRenderTarget(st),q.updateRenderTargetMipmap(st)),H&&E.end(C),M.isScene===!0&&M.onAfterRender(C,M,N),At.resetDefaultState(),Z=-1,Q=null,_.pop(),_.length>0?(w=_[_.length-1],q.setTextureUnits(w.state.textureUnits),ot===!0&&Ot.setGlobalState(C.clippingPlanes,w.state.camera)):w=null,P.pop(),P.length>0?b=P[P.length-1]:b=null,U!==null&&U.renderEnd()};function Dl(M,N,Y,H){if(M.visible===!1)return;if(M.layers.test(N.layers)){if(M.isGroup)Y=M.renderOrder;else if(M.isLOD)M.autoUpdate===!0&&M.update(N);else if(M.isLightProbeGrid)w.pushLightProbeGrid(M);else if(M.isLight)w.pushLight(M),M.castShadow&&w.pushShadow(M);else if(M.isSprite){if(!M.frustumCulled||M.intersectsFrustum(j)){H&&Ht.setFromMatrixPosition(M.matrixWorld).applyMatrix4(dt);let Ct=et.update(M),bt=M.material;bt.visible&&b.push(M,Ct,bt,Y,Ht.z,null,N)}}else if((M.isMesh||M.isLine||M.isPoints)&&(!M.frustumCulled||M.intersectsFrustum(j))){let Ct=et.update(M),bt=M.material;if(H&&(M.boundingSphere!==void 0?(M.boundingSphere===null&&M.computeBoundingSphere(),Ht.copy(M.boundingSphere.center)):(Ct.boundingSphere===null&&Ct.computeBoundingSphere(),Ht.copy(Ct.boundingSphere.center)),Ht.applyMatrix4(M.matrixWorld).applyMatrix4(dt)),Array.isArray(bt)){let Pt=Ct.groups;for(let Nt=0,te=Pt.length;Nt<te;Nt++){let ie=Pt[Nt],It=bt[ie.materialIndex];It&&It.visible&&b.push(M,Ct,It,Y,Ht.z,ie,N)}}else bt.visible&&b.push(M,Ct,bt,Y,Ht.z,null,N)}}let wt=M.children;for(let Ct=0,bt=wt.length;Ct<bt;Ct++)Dl(wt[Ct],N,Y,H)}function dh(M,N,Y,H){let{opaque:V,transmissive:wt,transparent:Ct}=M;w.setupLightsView(Y),ot===!0&&Ot.setGlobalState(C.clippingPlanes,Y),H&&x.viewport(it.copy(H)),V.length>0&&ua(V,N,Y),wt.length>0&&ua(wt,N,Y),Ct.length>0&&ua(Ct,N,Y),x.buffers.depth.setTest(!0),x.buffers.depth.setMask(!0),x.buffers.color.setMask(!0),x.setPolygonOffset(!1)}function fh(M,N,Y,H){if((Y.isScene===!0?Y.overrideMaterial:null)!==null)return;if(w.state.transmissionRenderTarget[H.id]===void 0){let It=Et.has("EXT_color_buffer_half_float")||Et.has("EXT_color_buffer_float");w.state.transmissionRenderTarget[H.id]=new Te(1,1,{generateMipmaps:!0,type:It?Le:rn,minFilter:Ai,samples:Math.max(4,R.samples),stencilBuffer:r,resolveDepthBuffer:!1,resolveStencilBuffer:!1,storeMultisampledDepthBuffer:!1,storeMultisampledStencilBuffer:!1,colorSpace:ne.workingColorSpace})}let wt=w.state.transmissionRenderTarget[H.id],Ct=H.viewport||it;wt.setSize(Ct.z*C.transmissionResolutionScale,Ct.w*C.transmissionResolutionScale);let bt=C.getRenderTarget(),Pt=C.getActiveCubeFace(),Nt=C.getActiveMipmapLevel();C.setRenderTarget(wt),C.getClearColor(Yt),Jt=C.getClearAlpha(),Jt<1&&C.setClearColor(16777215,.5),C.clear(),Gt&&jt.render(Y);let te=C.toneMapping;C.toneMapping=Ln;let ie=H.viewport;if(H.viewport!==void 0&&(H.viewport=void 0),w.setupLightsView(H),ot===!0&&Ot.setGlobalState(C.clippingPlanes,H),ua(M,Y,H),q.updateMultisampleRenderTarget(wt),q.updateRenderTargetMipmap(wt),Et.has("WEBGL_multisampled_render_to_texture")===!1){let It=!1;for(let de=0,Pe=N.length;de<Pe;de++){let ve=N[de],{object:ge,geometry:qe,material:Rt,group:tn}=ve;if(Rt.side===He&&ge.layers.test(H.layers)){let oe=Rt.side;Rt.side=We,Rt.needsUpdate=!0,ph(ge,Y,H,qe,Rt,tn),Rt.side=oe,Rt.needsUpdate=!0,It=!0}}It===!0&&(q.updateMultisampleRenderTarget(wt),q.updateRenderTargetMipmap(wt))}C.setRenderTarget(bt,Pt,Nt),C.setClearColor(Yt,Jt),ie!==void 0&&(H.viewport=ie),C.toneMapping=te}function ua(M,N,Y){let H=N.isScene===!0?N.overrideMaterial:null;for(let V=0,wt=M.length;V<wt;V++){let Ct=M[V],{object:bt,geometry:Pt,group:Nt}=Ct,te=Ct.material;te.allowOverride===!0&&H!==null&&(te=H),bt.layers.test(Y.layers)&&ph(bt,N,Y,Pt,te,Nt)}}function ph(M,N,Y,H,V,wt){U!==null&&V.isNodeMaterial&&U.setObject(M,V),M.onBeforeRender(C,N,Y,H,V,wt),M.modelViewMatrix.multiplyMatrices(Y.matrixWorldInverse,M.matrixWorld),M.normalMatrix.getNormalMatrix(M.modelViewMatrix),V.onBeforeRender(C,N,Y,H,M,wt),V.transparent===!0&&V.side===He&&V.forceSinglePass===!1?(V.side=We,V.needsUpdate=!0,C.renderBufferDirect(Y,N,H,V,M,wt),V.side=wi,V.needsUpdate=!0,C.renderBufferDirect(Y,N,H,V,M,wt),V.side=He):C.renderBufferDirect(Y,N,H,V,M,wt),M.onAfterRender(C,N,Y,H,V,wt)}function da(M,N,Y){N.isScene!==!0&&(N=kt);let H=G.get(M),V=w.state.lights,wt=w.state.shadowsArray,Ct=V.state.version,bt=xt.getParameters(M,V.state,wt,N,Y,w.state.lightProbeGridArray),Pt=xt.getProgramCacheKey(bt),Nt=H.programs;H.environment=M.isMeshStandardMaterial||M.isMeshLambertMaterial||M.isMeshPhongMaterial?N.environment:null,H.fog=N.fog;let te=M.isMeshStandardMaterial||M.isMeshLambertMaterial&&!M.envMap||M.isMeshPhongMaterial&&!M.envMap;H.envMap=lt.get(M.envMap||H.environment,te),H.envMapRotation=H.environment!==null&&M.envMap===null?N.environmentRotation:M.envMapRotation,Nt===void 0&&(M.addEventListener("dispose",Un),Nt=new Map,H.programs=Nt);let ie=Nt.get(Pt);if(ie!==void 0){if(H.currentProgram===ie&&H.lightsStateVersion===Ct)return gh(M,bt),ie}else bt.uniforms=xt.getUniforms(M),U!==null&&M.isNodeMaterial&&U.build(M,Y,bt),M.onBeforeCompile(bt,C),ie=xt.acquireProgram(bt,Pt),Nt.set(Pt,ie),H.uniforms=bt.uniforms;let It=H.uniforms;return(!M.isShaderMaterial&&!M.isRawShaderMaterial||M.clipping===!0)&&(It.clippingPlanes=Ot.uniform),gh(M,bt),H.needsLights=Bd(M),H.lightsStateVersion=Ct,H.needsLights&&(It.ambientLightColor.value=V.state.ambient,It.lightProbe.value=V.state.probe,It.sunLights.value=V.state.sun,It.sunLightShadows.value=V.state.sunShadow,It.directionalLights.value=V.state.directional,It.directionalLightShadows.value=V.state.directionalShadow,It.spotLights.value=V.state.spot,It.spotLightShadows.value=V.state.spotShadow,It.rectAreaLights.value=V.state.rectArea,It.ltc_1.value=V.state.rectAreaLTC1,It.ltc_2.value=V.state.rectAreaLTC2,It.pointLights.value=V.state.point,It.pointLightShadows.value=V.state.pointShadow,It.hemisphereLights.value=V.state.hemi,It.sunShadowMatrix.value=V.state.sunShadowMatrix,It.sunShadowCascade.value=V.state.sunShadowCascade,It.directionalShadowMatrix.value=V.state.directionalShadowMatrix,It.spotLightMatrix.value=V.state.spotLightMatrix,It.spotLightMap.value=V.state.spotLightMap,It.pointShadowMatrix.value=V.state.pointShadowMatrix),H.lightProbeGrid=w.state.lightProbeGridArray.length>0,H.currentProgram=ie,H.uniformsList=null,ie}function mh(M){if(M.uniformsList===null){let N=M.currentProgram.getUniforms();M.uniformsList=Vs.seqWithValue(N.seq,M.uniforms)}return M.uniformsList}function gh(M,N){let Y=G.get(M);Y.outputColorSpace=N.outputColorSpace,Y.batching=N.batching,Y.batchingColor=N.batchingColor,Y.instancing=N.instancing,Y.instancingColor=N.instancingColor,Y.instancingMorph=N.instancingMorph,Y.skinning=N.skinning,Y.morphTargets=N.morphTargets,Y.morphNormals=N.morphNormals,Y.morphColors=N.morphColors,Y.morphTargetsCount=N.morphTargetsCount,Y.numClippingPlanes=N.numClippingPlanes,Y.numIntersection=N.numClipIntersection,Y.vertexAlphas=N.vertexAlphas,Y.vertexTangents=N.vertexTangents,Y.toneMapping=N.toneMapping}function Ud(M,N){if(M.length===0)return null;if(M.length===1)return M[0].texture!==null?M[0]:null;v.setFromMatrixPosition(N.matrixWorld);for(let Y=0,H=M.length;Y<H;Y++){let V=M[Y];if(V.texture!==null&&V.boundingBox.containsPoint(v))return V}return null}function Nd(M,N,Y,H,V){N.isScene!==!0&&(N=kt),q.resetTextureUnits();let wt=N.fog,Ct=H.isMeshStandardMaterial||H.isMeshLambertMaterial||H.isMeshPhongMaterial?N.environment:null,bt=st===null?C.outputColorSpace:st.isXRRenderTarget===!0?st.texture.colorSpace:ne.workingColorSpace,Pt=H.isMeshStandardMaterial||H.isMeshLambertMaterial&&!H.envMap||H.isMeshPhongMaterial&&!H.envMap,Nt=lt.get(H.envMap||Ct,Pt),te=H.vertexColors===!0&&!!Y.attributes.color&&Y.attributes.color.itemSize===4,ie=!!Y.attributes.tangent&&(!!H.normalMap||H.anisotropy>0),It=!!Y.morphAttributes.position,de=!!Y.morphAttributes.normal,Pe=!!Y.morphAttributes.color,ve=Ln;H.toneMapped&&(st===null||st.isXRRenderTarget===!0)&&(ve=C.toneMapping);let ge=Y.morphAttributes.position||Y.morphAttributes.normal||Y.morphAttributes.color,qe=ge!==void 0?ge.length:0,Rt=G.get(H),tn=w.state.lights;if(ot===!0&&(ht===!0||M!==Q)){let ye=M===Q&&H.id===Z;Ot.setState(H,M,ye)}let oe=!1;H.version===Rt.__version?(Rt.needsLights&&Rt.lightsStateVersion!==tn.state.version||Rt.outputColorSpace!==bt||V.isBatchedMesh&&Rt.batching===!1||!V.isBatchedMesh&&Rt.batching===!0||V.isBatchedMesh&&Rt.batchingColor===!0&&V._colorsTexture===null||V.isBatchedMesh&&Rt.batchingColor===!1&&V._colorsTexture!==null||V.isInstancedMesh&&Rt.instancing===!1||!V.isInstancedMesh&&Rt.instancing===!0||V.isSkinnedMesh&&Rt.skinning===!1||!V.isSkinnedMesh&&Rt.skinning===!0||V.isInstancedMesh&&Rt.instancingColor===!0&&V.instanceColor===null||V.isInstancedMesh&&Rt.instancingColor===!1&&V.instanceColor!==null||V.isInstancedMesh&&Rt.instancingMorph===!0&&V.morphTexture===null||V.isInstancedMesh&&Rt.instancingMorph===!1&&V.morphTexture!==null||Rt.envMap!==Nt||H.fog===!0&&Rt.fog!==wt||Rt.numClippingPlanes!==void 0&&(Rt.numClippingPlanes!==Ot.numPlanes||Rt.numIntersection!==Ot.numIntersection)||Rt.vertexAlphas!==te||Rt.vertexTangents!==ie||Rt.morphTargets!==It||Rt.morphNormals!==de||Rt.morphColors!==Pe||Rt.toneMapping!==ve||Rt.morphTargetsCount!==qe||!!Rt.lightProbeGrid!=w.state.lightProbeGridArray.length>0)&&(oe=!0):(oe=!0,Rt.__version=H.version);let dn=Rt.currentProgram;oe===!0&&(dn=da(H,N,V),U&&H.isNodeMaterial&&U.onUpdateProgram(H,dn,Rt));let Nn=!1,oi=!1,ji=!1,pe=dn.getUniforms(),Ae=Rt.uniforms;if(x.useProgram(dn.program)&&(Nn=!0,oi=!0,ji=!0),H.id!==Z&&(Z=H.id,oi=!0),Rt.needsLights){let ye=Ud(w.state.lightProbeGridArray,V);Rt.lightProbeGrid!==ye&&(Rt.lightProbeGrid=ye,oi=!0)}if(Nn||Q!==M){x.buffers.depth.getReversed()&&M.reversedDepth!==!0&&(M._reversedDepth=!0,M.updateProjectionMatrix()),pe.setValue(A,"projectionMatrix",M.projectionMatrix),pe.setValue(A,"viewMatrix",M.matrixWorldInverse);let ci=pe.map.cameraPosition;ci!==void 0&&ci.setValue(A,pt.setFromMatrixPosition(M.matrixWorld)),R.logarithmicDepthBuffer&&pe.setValue(A,"logDepthBufFC",2/(Math.log(M.far+1)/Math.LN2)),(H.isMeshPhongMaterial||H.isMeshToonMaterial||H.isMeshLambertMaterial||H.isMeshBasicMaterial||H.isMeshStandardMaterial||H.isShaderMaterial)&&pe.setValue(A,"isOrthographic",M.isOrthographicCamera===!0),Q!==M&&(Q=M,oi=!0,ji=!0)}if(Rt.needsLights&&(tn.state.sunShadowMap.length>0&&pe.setValue(A,"sunShadowMap",tn.state.sunShadowMap,q),tn.state.directionalShadowMap.length>0&&pe.setValue(A,"directionalShadowMap",tn.state.directionalShadowMap,q),tn.state.spotShadowMap.length>0&&pe.setValue(A,"spotShadowMap",tn.state.spotShadowMap,q),tn.state.pointShadowMap.length>0&&pe.setValue(A,"pointShadowMap",tn.state.pointShadowMap,q)),V.isSkinnedMesh){pe.setOptional(A,V,"bindMatrix"),pe.setOptional(A,V,"bindMatrixInverse");let ye=V.skeleton;ye&&(ye.boneTexture===null&&ye.computeBoneTexture(),pe.setValue(A,"boneTexture",ye.boneTexture,q))}V.isBatchedMesh&&(pe.setOptional(A,V,"batchingTexture"),pe.setValue(A,"batchingTexture",V._matricesTexture,q),pe.setOptional(A,V,"batchingIdTexture"),pe.setValue(A,"batchingIdTexture",V._indirectTexture,q),pe.setOptional(A,V,"batchingColorTexture"),V._colorsTexture!==null&&pe.setValue(A,"batchingColorTexture",V._colorsTexture,q));let li=Y.morphAttributes;if((li.position!==void 0||li.normal!==void 0||li.color!==void 0)&&F.update(V,Y,dn),(oi||Rt.receiveShadow!==V.receiveShadow)&&(Rt.receiveShadow=V.receiveShadow,pe.setValue(A,"receiveShadow",V.receiveShadow)),(H.isMeshStandardMaterial||H.isMeshLambertMaterial||H.isMeshPhongMaterial)&&H.envMap===null&&N.environment!==null&&(Ae.envMapIntensity.value=N.environmentIntensity),Ae.dfgLUT!==void 0&&(Ae.dfgLUT.value=s_()),oi){if(pe.setValue(A,"toneMappingExposure",C.toneMappingExposure),Rt.needsLights&&Fd(Ae,ji),wt&&H.fog===!0&&Ut.refreshFogUniforms(Ae,wt),Ut.refreshMaterialUniforms(Ae,H,$,J,w.state.transmissionRenderTarget[M.id]),Rt.needsLights&&Rt.lightProbeGrid){let ye=Rt.lightProbeGrid;Ae.probesSH.value=ye.texture,Ae.probesMin.value.copy(ye.boundingBox.min),Ae.probesMax.value.copy(ye.boundingBox.max),Ae.probesResolution.value.copy(ye.resolution)}Vs.upload(A,mh(Rt),Ae,q)}if(H.isShaderMaterial&&H.uniformsNeedUpdate===!0&&(Vs.upload(A,mh(Rt),Ae,q),H.uniformsNeedUpdate=!1),H.isSpriteMaterial&&pe.setValue(A,"center",V.center),pe.setValue(A,"modelViewMatrix",V.modelViewMatrix),pe.setValue(A,"normalMatrix",V.normalMatrix),pe.setValue(A,"modelMatrix",V.matrixWorld),H.uniformsGroups!==void 0){let ye=H.uniformsGroups;for(let ci=0,ts=ye.length;ci<ts;ci++){let _h=ye[ci];at.update(_h,dn),at.bind(_h,dn)}}return dn}function Fd(M,N){M.ambientLightColor.needsUpdate=N,M.lightProbe.needsUpdate=N,M.sunLights.needsUpdate=N,M.sunLightShadows.needsUpdate=N,M.directionalLights.needsUpdate=N,M.directionalLightShadows.needsUpdate=N,M.pointLights.needsUpdate=N,M.pointLightShadows.needsUpdate=N,M.spotLights.needsUpdate=N,M.spotLightShadows.needsUpdate=N,M.rectAreaLights.needsUpdate=N,M.hemisphereLights.needsUpdate=N}function Bd(M){return M.isMeshLambertMaterial||M.isMeshToonMaterial||M.isMeshPhongMaterial||M.isMeshStandardMaterial||M.isShadowMaterial||M.isShaderMaterial&&M.lights===!0}this.getActiveCubeFace=function(){return W},this.getActiveMipmapLevel=function(){return X},this.getRenderTarget=function(){return st},this.setRenderTargetTextures=function(M,N,Y){let H=G.get(M);H.__autoAllocateDepthBuffer=M.resolveDepthBuffer===!1,H.__autoAllocateDepthBuffer===!1&&(H.__useRenderToTexture=!1),G.get(M.texture).__webglTexture=N,G.get(M.depthTexture).__webglTexture=H.__autoAllocateDepthBuffer?void 0:Y,H.__hasExternalTextures=!0},this.setRenderTargetFramebuffer=function(M,N){let Y=G.get(M);Y.__webglFramebuffer=N,Y.__useDefaultFramebuffer=N===void 0},this.setRenderTarget=function(M,N=0,Y=0){st=M,W=N,X=Y;let H=null,V=!1,wt=!1;if(M){let bt=G.get(M);if(bt.__useDefaultFramebuffer!==void 0){x.bindFramebuffer(A.FRAMEBUFFER,bt.__webglFramebuffer),it.copy(M.viewport),nt.copy(M.scissor),ct=M.scissorTest,x.viewport(it),x.scissor(nt),x.setScissorTest(ct),Z=-1;return}else if(bt.__webglFramebuffer===void 0)q.setupRenderTarget(M);else if(bt.__hasExternalTextures)q.rebindTextures(M,G.get(M.texture).__webglTexture,G.get(M.depthTexture).__webglTexture);else if(M.depthBuffer){let te=M.depthTexture;if(bt.__boundDepthTexture!==te){if(te!==null&&G.has(te)&&(M.width!==te.image.width||M.height!==te.image.height))throw new Error("THREE.WebGLRenderer: Attached DepthTexture is initialized to the incorrect size.");q.setupDepthRenderbuffer(M)}}let Pt=M.texture;(Pt.isData3DTexture||Pt.isDataArrayTexture||Pt.isCompressedArrayTexture)&&(wt=!0);let Nt=G.get(M).__webglFramebuffer;M.isWebGLCubeRenderTarget?(Array.isArray(Nt[N])?H=Nt[N][Y]:H=Nt[N],V=!0):M.samples>0&&q.useMultisampledRTT(M)===!1?H=G.get(M).__webglMultisampledFramebuffer:Array.isArray(Nt)?H=Nt[Y]:H=Nt,it.copy(M.viewport),nt.copy(M.scissor),ct=M.scissorTest}else it.copy(gt).multiplyScalar($).floor(),nt.copy(Bt).multiplyScalar($).floor(),ct=ae;if(Y!==0&&(H=k),x.bindFramebuffer(A.FRAMEBUFFER,H)&&x.drawBuffers(M,H),x.viewport(it),x.scissor(nt),x.setScissorTest(ct),V){let bt=G.get(M.texture);A.framebufferTexture2D(A.FRAMEBUFFER,A.COLOR_ATTACHMENT0,A.TEXTURE_CUBE_MAP_POSITIVE_X+N,bt.__webglTexture,Y)}else if(wt){let bt=N;for(let Pt=0;Pt<M.textures.length;Pt++){let Nt=G.get(M.textures[Pt]);A.framebufferTextureLayer(A.FRAMEBUFFER,A.COLOR_ATTACHMENT0+Pt,Nt.__webglTexture,Y,bt)}}else if(M!==null&&Y!==0){let bt=G.get(M.texture);A.framebufferTexture2D(A.FRAMEBUFFER,A.COLOR_ATTACHMENT0,A.TEXTURE_2D,bt.__webglTexture,Y)}Z=-1};function xh(M){let N=G.get(M);return(N.__readFormat!==M.format||N.__readType!==M.type)&&(N.__readFormat=M.format,N.__readType=M.type,N.__formatReadable=R.textureFormatReadable(M.format),N.__typeReadable=R.textureTypeReadable(M.type)),N}this.readRenderTargetPixels=function(M,N,Y,H,V,wt,Ct,bt=0){if(!(M&&M.isWebGLRenderTarget)){Xt("WebGLRenderer.readRenderTargetPixels: renderTarget is not THREE.WebGLRenderTarget.");return}let Pt=G.get(M).__webglFramebuffer;if(M.isWebGLCubeRenderTarget&&Ct!==void 0&&(Pt=Pt[Ct]),Pt){x.bindFramebuffer(A.FRAMEBUFFER,Pt);try{let Nt=M.textures[bt],te=Nt.format,ie=Nt.type;M.textures.length>1&&A.readBuffer(A.COLOR_ATTACHMENT0+bt);let It=xh(Nt);if(It.__formatReadable===!1){Xt("WebGLRenderer.readRenderTargetPixels: renderTarget is not in RGBA or implementation defined format.");return}if(It.__typeReadable===!1){Xt("WebGLRenderer.readRenderTargetPixels: renderTarget is not in UnsignedByteType or implementation defined type.");return}N>=0&&N<=M.width-H&&Y>=0&&Y<=M.height-V&&A.readPixels(N,Y,H,V,Mt.convert(te),Mt.convert(ie),wt)}finally{let Nt=st!==null?G.get(st).__webglFramebuffer:null;x.bindFramebuffer(A.FRAMEBUFFER,Nt)}}},this.readRenderTargetPixelsAsync=async function(M,N,Y,H,V,wt,Ct,bt=0){if(!(M&&M.isWebGLRenderTarget))throw new Error("THREE.WebGLRenderer.readRenderTargetPixels: renderTarget is not THREE.WebGLRenderTarget.");let Pt=G.get(M).__webglFramebuffer;if(M.isWebGLCubeRenderTarget&&Ct!==void 0&&(Pt=Pt[Ct]),Pt)if(N>=0&&N<=M.width-H&&Y>=0&&Y<=M.height-V){x.bindFramebuffer(A.FRAMEBUFFER,Pt);let Nt=M.textures[bt],te=Nt.format,ie=Nt.type;M.textures.length>1&&A.readBuffer(A.COLOR_ATTACHMENT0+bt);let It=xh(Nt);if(It.__formatReadable===!1)throw new Error("THREE.WebGLRenderer.readRenderTargetPixelsAsync: renderTarget is not in RGBA or implementation defined format.");if(It.__typeReadable===!1)throw new Error("THREE.WebGLRenderer.readRenderTargetPixelsAsync: renderTarget is not in UnsignedByteType or implementation defined type.");let de=A.createBuffer();A.bindBuffer(A.PIXEL_PACK_BUFFER,de),A.bufferData(A.PIXEL_PACK_BUFFER,wt.byteLength,A.STREAM_READ),A.readPixels(N,Y,H,V,Mt.convert(te),Mt.convert(ie),0),A.bindBuffer(A.PIXEL_PACK_BUFFER,null);let Pe=st!==null?G.get(st).__webglFramebuffer:null;x.bindFramebuffer(A.FRAMEBUFFER,Pe);let ve=A.fenceSync(A.SYNC_GPU_COMMANDS_COMPLETE,0);return A.flush(),await ku(A,ve,4),A.bindBuffer(A.PIXEL_PACK_BUFFER,de),A.getBufferSubData(A.PIXEL_PACK_BUFFER,0,wt),A.bindBuffer(A.PIXEL_PACK_BUFFER,null),A.deleteBuffer(de),A.deleteSync(ve),wt}else throw new Error("THREE.WebGLRenderer.readRenderTargetPixelsAsync: requested read bounds are out of range.")},this.copyFramebufferToTexture=function(M,N=null,Y=0){let H=Math.pow(2,-Y),V=Math.floor(M.image.width*H),wt=Math.floor(M.image.height*H),Ct=N!==null?N.x:0,bt=N!==null?N.y:0;q.setTexture2D(M,0),A.copyTexSubImage2D(A.TEXTURE_2D,Y,0,0,Ct,bt,V,wt),x.unbindTexture()},this.copyTextureToTexture=function(M,N,Y=null,H=null,V=0,wt=0){let Ct,bt,Pt,Nt,te,ie,It,de,Pe,ve=M.isCompressedTexture?M.mipmaps[wt]:M.image;if(Y!==null)Ct=Y.max.x-Y.min.x,bt=Y.max.y-Y.min.y,Pt=Y.isBox3?Y.max.z-Y.min.z:1,Nt=Y.min.x,te=Y.min.y,ie=Y.isBox3?Y.min.z:0;else{let Ae=Math.pow(2,-V);Ct=Math.floor(ve.width*Ae),bt=Math.floor(ve.height*Ae),M.isDataArrayTexture?Pt=ve.depth:M.isData3DTexture?Pt=Math.floor(ve.depth*Ae):Pt=1,Nt=0,te=0,ie=0}H!==null?(It=H.x,de=H.y,Pe=H.z):(It=0,de=0,Pe=0);let ge=Mt.convert(N.format),qe=Mt.convert(N.type),Rt;N.isData3DTexture?(q.setTexture3D(N,0),Rt=A.TEXTURE_3D):N.isDataArrayTexture||N.isCompressedArrayTexture?(q.setTexture2DArray(N,0),Rt=A.TEXTURE_2D_ARRAY):(q.setTexture2D(N,0),Rt=A.TEXTURE_2D),x.activeTexture(A.TEXTURE0),x.pixelStorei(A.UNPACK_FLIP_Y_WEBGL,N.flipY),x.pixelStorei(A.UNPACK_PREMULTIPLY_ALPHA_WEBGL,N.premultiplyAlpha),x.pixelStorei(A.UNPACK_ALIGNMENT,N.unpackAlignment);let tn=x.getParameter(A.UNPACK_ROW_LENGTH),oe=x.getParameter(A.UNPACK_IMAGE_HEIGHT),dn=x.getParameter(A.UNPACK_SKIP_PIXELS),Nn=x.getParameter(A.UNPACK_SKIP_ROWS),oi=x.getParameter(A.UNPACK_SKIP_IMAGES);x.pixelStorei(A.UNPACK_ROW_LENGTH,ve.width),x.pixelStorei(A.UNPACK_IMAGE_HEIGHT,ve.height),x.pixelStorei(A.UNPACK_SKIP_PIXELS,Nt),x.pixelStorei(A.UNPACK_SKIP_ROWS,te),x.pixelStorei(A.UNPACK_SKIP_IMAGES,ie);let ji=M.isDataArrayTexture||M.isData3DTexture,pe=N.isDataArrayTexture||N.isData3DTexture;if(M.isDepthTexture){let Ae=G.get(M),li=G.get(N),ye=G.get(Ae.__renderTarget),ci=G.get(li.__renderTarget);x.bindFramebuffer(A.READ_FRAMEBUFFER,ye.__webglFramebuffer),x.bindFramebuffer(A.DRAW_FRAMEBUFFER,ci.__webglFramebuffer);for(let ts=0;ts<Pt;ts++)ji&&(A.framebufferTextureLayer(A.READ_FRAMEBUFFER,A.COLOR_ATTACHMENT0,G.get(M).__webglTexture,V,ie+ts),A.framebufferTextureLayer(A.DRAW_FRAMEBUFFER,A.COLOR_ATTACHMENT0,G.get(N).__webglTexture,wt,Pe+ts)),A.blitFramebuffer(Nt,te,Ct,bt,It,de,Ct,bt,A.DEPTH_BUFFER_BIT,A.NEAREST);x.bindFramebuffer(A.READ_FRAMEBUFFER,null),x.bindFramebuffer(A.DRAW_FRAMEBUFFER,null)}else if(V!==0||M.isRenderTargetTexture||G.has(M)){let Ae=G.get(M),li=G.get(N);x.bindFramebuffer(A.READ_FRAMEBUFFER,L),x.bindFramebuffer(A.DRAW_FRAMEBUFFER,B);for(let ye=0;ye<Pt;ye++)ji?A.framebufferTextureLayer(A.READ_FRAMEBUFFER,A.COLOR_ATTACHMENT0,Ae.__webglTexture,V,ie+ye):A.framebufferTexture2D(A.READ_FRAMEBUFFER,A.COLOR_ATTACHMENT0,A.TEXTURE_2D,Ae.__webglTexture,V),pe?A.framebufferTextureLayer(A.DRAW_FRAMEBUFFER,A.COLOR_ATTACHMENT0,li.__webglTexture,wt,Pe+ye):A.framebufferTexture2D(A.DRAW_FRAMEBUFFER,A.COLOR_ATTACHMENT0,A.TEXTURE_2D,li.__webglTexture,wt),V!==0?A.blitFramebuffer(Nt,te,Ct,bt,It,de,Ct,bt,A.COLOR_BUFFER_BIT,A.NEAREST):pe?A.copyTexSubImage3D(Rt,wt,It,de,Pe+ye,Nt,te,Ct,bt):A.copyTexSubImage2D(Rt,wt,It,de,Nt,te,Ct,bt);x.bindFramebuffer(A.READ_FRAMEBUFFER,null),x.bindFramebuffer(A.DRAW_FRAMEBUFFER,null)}else pe?M.isDataTexture||M.isData3DTexture?A.texSubImage3D(Rt,wt,It,de,Pe,Ct,bt,Pt,ge,qe,ve.data):N.isCompressedArrayTexture?A.compressedTexSubImage3D(Rt,wt,It,de,Pe,Ct,bt,Pt,ge,ve.data):A.texSubImage3D(Rt,wt,It,de,Pe,Ct,bt,Pt,ge,qe,ve):M.isDataTexture?A.texSubImage2D(A.TEXTURE_2D,wt,It,de,Ct,bt,ge,qe,ve.data):M.isCompressedTexture?A.compressedTexSubImage2D(A.TEXTURE_2D,wt,It,de,ve.width,ve.height,ge,ve.data):A.texSubImage2D(A.TEXTURE_2D,wt,It,de,Ct,bt,ge,qe,ve);x.pixelStorei(A.UNPACK_ROW_LENGTH,tn),x.pixelStorei(A.UNPACK_IMAGE_HEIGHT,oe),x.pixelStorei(A.UNPACK_SKIP_PIXELS,dn),x.pixelStorei(A.UNPACK_SKIP_ROWS,Nn),x.pixelStorei(A.UNPACK_SKIP_IMAGES,oi),wt===0&&N.generateMipmaps&&A.generateMipmap(Rt),x.unbindTexture()},this.initRenderTarget=function(M){G.get(M).__webglFramebuffer===void 0&&q.setupRenderTarget(M)},this.initTexture=function(M){M.isCubeTexture?q.setTextureCube(M,0):M.isData3DTexture?q.setTexture3D(M,0):M.isDataArrayTexture||M.isCompressedArrayTexture?q.setTexture2DArray(M,0):q.setTexture2D(M,0),x.unbindTexture()},this.resetState=function(){W=0,X=0,st=null,x.reset(),At.reset()},typeof __THREE_DEVTOOLS__<"u"&&__THREE_DEVTOOLS__.dispatchEvent(new CustomEvent("observe",{detail:this}))}get coordinateSystem(){return Cn}get outputColorSpace(){return this._outputColorSpace}set outputColorSpace(t){this._outputColorSpace=t;let e=this.getContext();e.drawingBufferColorSpace=ne._getDrawingBufferColorSpace(t),e.unpackColorSpace=ne._getUnpackColorSpace()}};var yl=class extends zi{constructor(){super(),this.name="RoomEnvironment",this.position.y=-3.5;let t=new he;t.deleteAttribute("uv");let e=new Vt({side:We}),n=new Vt,i=new bi(16777215,900,28,2);i.position.set(.418,16.199,.3),this.add(i);let r=new ut(t,e);r.position.set(-.757,13.219,.717),r.scale.set(31.713,28.305,28.591),this.add(r);let a=new Hi(t,n,6),o=new Re;o.position.set(-10.906,2.009,1.846),o.rotation.set(0,-.195,0),o.scale.set(2.328,7.905,4.651),o.updateMatrix(),a.setMatrixAt(0,o.matrix),o.position.set(-5.607,-.754,-.758),o.rotation.set(0,.994,0),o.scale.set(1.97,1.534,3.955),o.updateMatrix(),a.setMatrixAt(1,o.matrix),o.position.set(6.167,.857,7.803),o.rotation.set(0,.561,0),o.scale.set(3.927,6.285,3.687),o.updateMatrix(),a.setMatrixAt(2,o.matrix),o.position.set(-2.017,.018,6.124),o.rotation.set(0,.333,0),o.scale.set(2.002,4.566,2.064),o.updateMatrix(),a.setMatrixAt(3,o.matrix),o.position.set(2.291,-.756,-2.621),o.rotation.set(0,-.286,0),o.scale.set(1.546,1.552,1.496),o.updateMatrix(),a.setMatrixAt(4,o.matrix),o.position.set(-2.193,-.369,-5.547),o.rotation.set(0,.516,0),o.scale.set(3.875,3.487,2.986),o.updateMatrix(),a.setMatrixAt(5,o.matrix),this.add(a);let l=new ut(t,Xs(50));l.position.set(-16.116,14.37,8.208),l.scale.set(.1,2.428,2.739),this.add(l);let c=new ut(t,Xs(50));c.position.set(-16.109,18.021,-8.207),c.scale.set(.1,2.425,2.751),this.add(c);let h=new ut(t,Xs(17));h.position.set(14.904,12.198,-1.832),h.scale.set(.15,4.265,6.331),this.add(h);let u=new ut(t,Xs(43));u.position.set(-.462,8.89,14.52),u.scale.set(4.38,5.441,.088),this.add(u);let d=new ut(t,Xs(20));d.position.set(3.235,11.486,-12.541),d.scale.set(2.5,2,.1),this.add(d);let f=new ut(t,Xs(100));f.position.set(0,20,0),f.scale.set(1,.1,1),this.add(f)}dispose(){let t=new Set;this.traverse(e=>{e.isMesh&&(t.add(e.geometry),t.add(e.material))});for(let e of t)e.dispose()}};function Xs(s){return new Xi({color:0,emissive:16777215,emissiveIntensity:s})}var qs={name:"CopyShader",uniforms:{tDiffuse:{value:null},opacity:{value:1}},vertexShader:`

		varying vec2 vUv;

		void main() {

			vUv = uv;
			gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 );

		}`,fragmentShader:`

		uniform float opacity;

		uniform sampler2D tDiffuse;

		varying vec2 vUv;

		void main() {

			vec4 texel = texture2D( tDiffuse, vUv );
			gl_FragColor = opacity * texel;


		}`};var un=class{constructor(){this.isPass=!0,this.enabled=!0,this.needsSwap=!0,this.clear=!1,this.renderToScreen=!1}setSize(){}render(){console.error("THREE.Pass: .render() must be implemented in derived pass.")}dispose(){}},r_=new Ei(-1,1,1,-1,0,1),lh=class extends xe{constructor(){super(),this.setAttribute("position",new Zt([-1,3,0,-1,-1,0,3,-1,0],3)),this.setAttribute("uv",new Zt([0,2,0,0,2,0],2))}},a_=new lh,Pi=class{constructor(t){this._mesh=new ut(a_,t)}dispose(){this._mesh.geometry.dispose()}render(t){t.render(this._mesh,r_)}get material(){return this._mesh.material}set material(t){this._mesh.material=t}};var vl=class extends un{constructor(t,e="tDiffuse"){super(),this.textureID=e,this.uniforms=null,this.material=null,t instanceof Ce?(this.uniforms=t.uniforms,this.material=t):t&&(this.uniforms=si.clone(t.uniforms),this.material=new Ce({name:t.name!==void 0?t.name:"unspecified",defines:Object.assign({},t.defines),uniforms:this.uniforms,vertexShader:t.vertexShader,fragmentShader:t.fragmentShader})),this._fsQuad=new Pi(this.material)}render(t,e,n){this.uniforms[this.textureID]&&(this.uniforms[this.textureID].value=n.texture),this._fsQuad.material=this.material,this.renderToScreen?(t.setRenderTarget(null),this._fsQuad.render(t)):(t.setRenderTarget(e),this.clear&&t.clear(t.autoClearColor,t.autoClearDepth,t.autoClearStencil),this._fsQuad.render(t))}dispose(){this.material.dispose(),this._fsQuad.dispose()}};var la=class extends un{constructor(t,e){super(),this.scene=t,this.camera=e,this.clear=!0,this.needsSwap=!1,this.inverse=!1}render(t,e,n){let i=t.getContext(),r=t.state;r.buffers.color.setMask(!1),r.buffers.depth.setMask(!1),r.buffers.color.setLocked(!0),r.buffers.depth.setLocked(!0);let a,o;this.inverse?(a=0,o=1):(a=1,o=0),r.buffers.stencil.setTest(!0),r.buffers.stencil.setOp(i.REPLACE,i.REPLACE,i.REPLACE),r.buffers.stencil.setFunc(i.ALWAYS,a,4294967295),r.buffers.stencil.setClear(o),r.buffers.stencil.setLocked(!0),t.setRenderTarget(n),this.clear&&t.clear(),t.render(this.scene,this.camera),t.setRenderTarget(e),this.clear&&t.clear(),t.render(this.scene,this.camera),r.buffers.color.setLocked(!1),r.buffers.depth.setLocked(!1),r.buffers.color.setMask(!0),r.buffers.depth.setMask(!0),r.buffers.stencil.setLocked(!1),r.buffers.stencil.setFunc(i.EQUAL,1,4294967295),r.buffers.stencil.setOp(i.KEEP,i.KEEP,i.KEEP),r.buffers.stencil.setLocked(!0)}},Ml=class extends un{constructor(){super(),this.needsSwap=!1}render(t){t.state.buffers.stencil.setLocked(!1),t.state.buffers.stencil.setTest(!1)}};var Sl=class{constructor(t,e){if(this.renderer=t,this._pixelRatio=t.getPixelRatio(),e===void 0){let n=t.getSize(new rt);this._width=n.width,this._height=n.height,e=new Te(this._width*this._pixelRatio,this._height*this._pixelRatio,{type:Le}),e.texture.name="EffectComposer.rt1"}else this._width=e.width,this._height=e.height;this.renderTarget1=e,this.renderTarget2=e.clone(),this.renderTarget2.texture.name="EffectComposer.rt2",this.writeBuffer=this.renderTarget1,this.readBuffer=this.renderTarget2,this.renderToScreen=!0,this.passes=[],this.copyPass=new vl(qs),this.copyPass.material.blending=_n,this.timer=new Gr}swapBuffers(){let t=this.readBuffer;this.readBuffer=this.writeBuffer,this.writeBuffer=t}addPass(t){this.passes.push(t),t.setSize(this._width*this._pixelRatio,this._height*this._pixelRatio)}insertPass(t,e){this.passes.splice(e,0,t),t.setSize(this._width*this._pixelRatio,this._height*this._pixelRatio)}removePass(t){let e=this.passes.indexOf(t);e!==-1&&this.passes.splice(e,1)}isLastEnabledPass(t){for(let e=t+1;e<this.passes.length;e++)if(this.passes[e].enabled)return!1;return!0}render(t){this.timer.update(),t===void 0&&(t=this.timer.getDelta());let e=this.renderer.getRenderTarget(),n=!1;for(let i=0,r=this.passes.length;i<r;i++){let a=this.passes[i];if(a.enabled!==!1){if(a.renderToScreen=this.renderToScreen&&this.isLastEnabledPass(i),a.render(this.renderer,this.writeBuffer,this.readBuffer,t,n),a.needsSwap){if(n){let o=this.renderer.getContext(),l=this.renderer.state.buffers.stencil;l.setFunc(o.NOTEQUAL,1,4294967295),this.copyPass.render(this.renderer,this.writeBuffer,this.readBuffer,t),l.setFunc(o.EQUAL,1,4294967295)}this.swapBuffers()}la!==void 0&&(a instanceof la?n=!0:a instanceof Ml&&(n=!1))}}this.renderer.setRenderTarget(e)}reset(t){if(t===void 0){let e=this.renderer.getSize(new rt);this._pixelRatio=this.renderer.getPixelRatio(),this._width=e.width,this._height=e.height,t=this.renderTarget1.clone(),t.setSize(this._width*this._pixelRatio,this._height*this._pixelRatio)}this.renderTarget1.dispose(),this.renderTarget2.dispose(),this.renderTarget1=t,this.renderTarget2=t.clone(),this.writeBuffer=this.renderTarget1,this.readBuffer=this.renderTarget2}setSize(t,e){this._width=t,this._height=e;let n=this._width*this._pixelRatio,i=this._height*this._pixelRatio;this.renderTarget1.setSize(n,i),this.renderTarget2.setSize(n,i);for(let r=0;r<this.passes.length;r++)this.passes[r].setSize(n,i)}setPixelRatio(t){this._pixelRatio=t,this.setSize(this._width,this._height)}dispose(){this.renderTarget1.dispose(),this.renderTarget2.dispose(),this.copyPass.dispose()}};var bl=class extends un{constructor(t,e,n=null,i=null,r=null){super(),this.scene=t,this.camera=e,this.overrideMaterial=n,this.clearColor=i,this.clearAlpha=r,this.clear=!0,this.clearDepth=!1,this.needsSwap=!1,this.isRenderPass=!0,this._oldClearColor=new Ft}render(t,e,n){let i=t.autoClear;t.autoClear=!1;let r,a;this.overrideMaterial!==null&&(a=this.scene.overrideMaterial,this.scene.overrideMaterial=this.overrideMaterial),this.clearColor!==null&&(t.getClearColor(this._oldClearColor),t.setClearColor(this.clearColor,t.getClearAlpha())),this.clearAlpha!==null&&(r=t.getClearAlpha(),t.setClearAlpha(this.clearAlpha)),this.clearDepth==!0&&t.clearDepth(),t.setRenderTarget(this.renderToScreen?null:n),this.clear===!0&&t.clear(t.autoClearColor,t.autoClearDepth,t.autoClearStencil),t.render(this.scene,this.camera),this.clearColor!==null&&t.setClearColor(this._oldClearColor),this.clearAlpha!==null&&t.setClearAlpha(r),this.overrideMaterial!==null&&(this.scene.overrideMaterial=a),t.autoClear=i}};var Ed={name:"LuminosityHighPassShader",uniforms:{tDiffuse:{value:null},luminosityThreshold:{value:1},smoothWidth:{value:1},defaultColor:{value:new Ft(0)},defaultOpacity:{value:0}},vertexShader:`

		varying vec2 vUv;

		void main() {

			vUv = uv;

			gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 );

		}`,fragmentShader:`

		uniform sampler2D tDiffuse;
		uniform vec3 defaultColor;
		uniform float defaultOpacity;
		uniform float luminosityThreshold;
		uniform float smoothWidth;

		varying vec2 vUv;

		void main() {

			vec4 texel = texture2D( tDiffuse, vUv );

			float v = luminance( texel.xyz );

			vec4 outputColor = vec4( defaultColor.rgb, defaultOpacity );

			float alpha = smoothstep( luminosityThreshold, luminosityThreshold + smoothWidth, v );

			gl_FragColor = mix( outputColor, texel, alpha );

		}`};var Ys=class s extends un{constructor(t,e=1,n,i){super(),this.strength=e,this.radius=n,this.threshold=i,this.resolution=t!==void 0?new rt(t.x,t.y):new rt(256,256),this.clearColor=new Ft(0,0,0),this.needsSwap=!1,this.renderTargetsHorizontal=[],this.renderTargetsVertical=[],this.nMips=5;let r=Math.round(this.resolution.x/2),a=Math.round(this.resolution.y/2);this.renderTargetBright=new Te(r,a,{type:Le,depthBuffer:!1}),this.renderTargetBright.texture.name="UnrealBloomPass.bright",this.renderTargetBright.texture.generateMipmaps=!1;for(let h=0;h<this.nMips;h++){let u=new Te(r,a,{type:Le,depthBuffer:!1});u.texture.name="UnrealBloomPass.h"+h,u.texture.generateMipmaps=!1,this.renderTargetsHorizontal.push(u);let d=new Te(r,a,{type:Le,depthBuffer:!1});d.texture.name="UnrealBloomPass.v"+h,d.texture.generateMipmaps=!1,this.renderTargetsVertical.push(d),r=Math.round(r/2),a=Math.round(a/2)}let o=Ed;this.highPassUniforms=si.clone(o.uniforms),this.highPassUniforms.luminosityThreshold.value=i,this.highPassUniforms.smoothWidth.value=.01,this.materialHighPassFilter=new Ce({uniforms:this.highPassUniforms,vertexShader:o.vertexShader,fragmentShader:o.fragmentShader}),this.separableBlurMaterials=[];let l=[6,10,14,18,22];r=Math.round(this.resolution.x/2),a=Math.round(this.resolution.y/2);for(let h=0;h<this.nMips;h++)this.separableBlurMaterials.push(this._getSeparableBlurMaterial(l[h])),this.separableBlurMaterials[h].uniforms.invSize.value=new rt(1/r,1/a),r=Math.round(r/2),a=Math.round(a/2);this.compositeMaterial=this._getCompositeMaterial(this.nMips),this.compositeMaterial.uniforms.blurTexture1.value=this.renderTargetsVertical[0].texture,this.compositeMaterial.uniforms.blurTexture2.value=this.renderTargetsVertical[1].texture,this.compositeMaterial.uniforms.blurTexture3.value=this.renderTargetsVertical[2].texture,this.compositeMaterial.uniforms.blurTexture4.value=this.renderTargetsVertical[3].texture,this.compositeMaterial.uniforms.blurTexture5.value=this.renderTargetsVertical[4].texture,this.compositeMaterial.uniforms.bloomStrength.value=e,this.compositeMaterial.uniforms.bloomRadius.value=.1;let c=[1,.8,.6,.4,.2];this.compositeMaterial.uniforms.bloomFactors.value=c,this.bloomTintColors=[new D(1,1,1),new D(1,1,1),new D(1,1,1),new D(1,1,1),new D(1,1,1)],this.compositeMaterial.uniforms.bloomTintColors.value=this.bloomTintColors,this.copyUniforms=si.clone(qs.uniforms),this.blendMaterial=new Ce({uniforms:this.copyUniforms,vertexShader:qs.vertexShader,fragmentShader:qs.fragmentShader,premultipliedAlpha:!0,blending:Yi,depthTest:!1,depthWrite:!1,transparent:!0}),this._oldClearColor=new Ft,this._oldClearAlpha=1,this._basic=new $e,this._fsQuad=new Pi(null)}dispose(){for(let t=0;t<this.renderTargetsHorizontal.length;t++)this.renderTargetsHorizontal[t].dispose();for(let t=0;t<this.renderTargetsVertical.length;t++)this.renderTargetsVertical[t].dispose();this.renderTargetBright.dispose();for(let t=0;t<this.separableBlurMaterials.length;t++)this.separableBlurMaterials[t].dispose();this.compositeMaterial.dispose(),this.blendMaterial.dispose(),this._basic.dispose(),this._fsQuad.dispose()}setSize(t,e){let n=Math.round(t/2),i=Math.round(e/2);this.renderTargetBright.setSize(n,i);for(let r=0;r<this.nMips;r++)this.renderTargetsHorizontal[r].setSize(n,i),this.renderTargetsVertical[r].setSize(n,i),this.separableBlurMaterials[r].uniforms.invSize.value=new rt(1/n,1/i),n=Math.round(n/2),i=Math.round(i/2)}render(t,e,n,i,r){t.getClearColor(this._oldClearColor),this._oldClearAlpha=t.getClearAlpha();let a=t.autoClear;t.autoClear=!1,t.setClearColor(this.clearColor,0),r&&t.state.buffers.stencil.setTest(!1),this.renderToScreen&&(this._fsQuad.material=this._basic,this._basic.map=n.texture,t.setRenderTarget(null),t.clear(),this._fsQuad.render(t)),this.highPassUniforms.tDiffuse.value=n.texture,this.highPassUniforms.luminosityThreshold.value=this.threshold,this._fsQuad.material=this.materialHighPassFilter,t.setRenderTarget(this.renderTargetBright),t.clear(),this._fsQuad.render(t);let o=this.renderTargetBright;for(let l=0;l<this.nMips;l++)this._fsQuad.material=this.separableBlurMaterials[l],this.separableBlurMaterials[l].uniforms.colorTexture.value=o.texture,this.separableBlurMaterials[l].uniforms.direction.value=s.BlurDirectionX,t.setRenderTarget(this.renderTargetsHorizontal[l]),t.clear(),this._fsQuad.render(t),this.separableBlurMaterials[l].uniforms.colorTexture.value=this.renderTargetsHorizontal[l].texture,this.separableBlurMaterials[l].uniforms.direction.value=s.BlurDirectionY,t.setRenderTarget(this.renderTargetsVertical[l]),t.clear(),this._fsQuad.render(t),o=this.renderTargetsVertical[l];this._fsQuad.material=this.compositeMaterial,this.compositeMaterial.uniforms.bloomStrength.value=this.strength,this.compositeMaterial.uniforms.bloomRadius.value=this.radius,this.compositeMaterial.uniforms.bloomTintColors.value=this.bloomTintColors,t.setRenderTarget(this.renderTargetsHorizontal[0]),t.clear(),this._fsQuad.render(t),this._fsQuad.material=this.blendMaterial,this.copyUniforms.tDiffuse.value=this.renderTargetsHorizontal[0].texture,r&&t.state.buffers.stencil.setTest(!0),this.renderToScreen?(t.setRenderTarget(null),this._fsQuad.render(t)):(t.setRenderTarget(n),this._fsQuad.render(t)),t.setClearColor(this._oldClearColor,this._oldClearAlpha),t.autoClear=a}_getSeparableBlurMaterial(t){let e=[],n=t/3;for(let a=0;a<t;a++)e.push(.39894*Math.exp(-.5*a*a/(n*n))/n);let i=[],r=[];for(let a=1;a<t;a+=2){let o=e[a],l=a+1<t?e[a+1]:0,c=o+l;i.push((a*o+(a+1)*l)/c),r.push(c)}return new Ce({defines:{KERNEL_PAIRS:i.length},uniforms:{colorTexture:{value:null},invSize:{value:new rt(.5,.5)},direction:{value:new rt(.5,.5)},centerWeight:{value:e[0]},gaussianOffsets:{value:i},gaussianWeights:{value:r}},vertexShader:`

				varying vec2 vUv;

				void main() {

					vUv = uv;
					gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 );

				}`,fragmentShader:`

				#include <common>

				varying vec2 vUv;

				uniform sampler2D colorTexture;
				uniform vec2 invSize;
				uniform vec2 direction;
				uniform float centerWeight;
				uniform float gaussianOffsets[KERNEL_PAIRS];
				uniform float gaussianWeights[KERNEL_PAIRS];

				void main() {

					vec3 diffuseSum = texture2D( colorTexture, vUv ).rgb * centerWeight;

					for ( int i = 0; i < KERNEL_PAIRS; i ++ ) {

						vec2 uvOffset = direction * invSize * gaussianOffsets[ i ];
						vec3 sample1 = texture2D( colorTexture, vUv + uvOffset ).rgb;
						vec3 sample2 = texture2D( colorTexture, vUv - uvOffset ).rgb;
						diffuseSum += ( sample1 + sample2 ) * gaussianWeights[ i ];

					}

					gl_FragColor = vec4( diffuseSum, 1.0 );

				}`})}_getCompositeMaterial(t){return new Ce({defines:{NUM_MIPS:t},uniforms:{blurTexture1:{value:null},blurTexture2:{value:null},blurTexture3:{value:null},blurTexture4:{value:null},blurTexture5:{value:null},bloomStrength:{value:1},bloomFactors:{value:null},bloomTintColors:{value:null},bloomRadius:{value:0}},vertexShader:`

				varying vec2 vUv;

				void main() {

					vUv = uv;
					gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 );

				}`,fragmentShader:`

				varying vec2 vUv;

				uniform sampler2D blurTexture1;
				uniform sampler2D blurTexture2;
				uniform sampler2D blurTexture3;
				uniform sampler2D blurTexture4;
				uniform sampler2D blurTexture5;
				uniform float bloomStrength;
				uniform float bloomRadius;
				uniform float bloomFactors[NUM_MIPS];
				uniform vec3 bloomTintColors[NUM_MIPS];

				float lerpBloomFactor( const in float factor ) {

					float mirrorFactor = 1.2 - factor;
					return mix( factor, mirrorFactor, bloomRadius );

				}

				void main() {

					// 3.0 for backwards compatibility with previous alpha-based intensity
					vec3 bloom = 3.0 * bloomStrength * (
						lerpBloomFactor( bloomFactors[ 0 ] ) * bloomTintColors[ 0 ] * texture2D( blurTexture1, vUv ).rgb +
						lerpBloomFactor( bloomFactors[ 1 ] ) * bloomTintColors[ 1 ] * texture2D( blurTexture2, vUv ).rgb +
						lerpBloomFactor( bloomFactors[ 2 ] ) * bloomTintColors[ 2 ] * texture2D( blurTexture3, vUv ).rgb +
						lerpBloomFactor( bloomFactors[ 3 ] ) * bloomTintColors[ 3 ] * texture2D( blurTexture4, vUv ).rgb +
						lerpBloomFactor( bloomFactors[ 4 ] ) * bloomTintColors[ 4 ] * texture2D( blurTexture5, vUv ).rgb
					);

					float bloomAlpha = max( bloom.r, max( bloom.g, bloom.b ) );
					gl_FragColor = vec4( bloom, bloomAlpha );

				}`})}};Ys.BlurDirectionX=new rt(1,0);Ys.BlurDirectionY=new rt(0,1);var ca={name:"OutputShader",uniforms:{tDiffuse:{value:null},toneMappingExposure:{value:1}},vertexShader:`
		precision highp float;

		uniform mat4 modelViewMatrix;
		uniform mat4 projectionMatrix;

		attribute vec3 position;
		attribute vec2 uv;

		varying vec2 vUv;

		void main() {

			vUv = uv;
			gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 );

		}`,fragmentShader:`

		precision highp float;

		uniform sampler2D tDiffuse;

		#include <tonemapping_pars_fragment>
		#include <colorspace_pars_fragment>

		varying vec2 vUv;

		void main() {

			gl_FragColor = texture2D( tDiffuse, vUv );

			// tone mapping

			#ifdef LINEAR_TONE_MAPPING

				gl_FragColor.rgb = LinearToneMapping( gl_FragColor.rgb );

			#elif defined( REINHARD_TONE_MAPPING )

				gl_FragColor.rgb = ReinhardToneMapping( gl_FragColor.rgb );

			#elif defined( CINEON_TONE_MAPPING )

				gl_FragColor.rgb = CineonToneMapping( gl_FragColor.rgb );

			#elif defined( ACES_FILMIC_TONE_MAPPING )

				gl_FragColor.rgb = ACESFilmicToneMapping( gl_FragColor.rgb );

			#elif defined( AGX_TONE_MAPPING )

				gl_FragColor.rgb = AgXToneMapping( gl_FragColor.rgb );

			#elif defined( NEUTRAL_TONE_MAPPING )

				gl_FragColor.rgb = NeutralToneMapping( gl_FragColor.rgb );

			#elif defined( CUSTOM_TONE_MAPPING )

				gl_FragColor.rgb = CustomToneMapping( gl_FragColor.rgb );

			#endif

			// color space

			#ifdef SRGB_TRANSFER

				gl_FragColor = sRGBTransferOETF( gl_FragColor );

			#endif

		}`};var El=class extends un{constructor(){super(),this.isOutputPass=!0,this.uniforms=si.clone(ca.uniforms),this.material=new Ds({name:ca.name,uniforms:this.uniforms,vertexShader:ca.vertexShader,fragmentShader:ca.fragmentShader}),this._fsQuad=new Pi(this.material),this._outputColorSpace=null,this._toneMapping=null}render(t,e,n){this.uniforms.tDiffuse.value=n.texture,this.uniforms.toneMappingExposure.value=t.toneMappingExposure,(this._outputColorSpace!==t.outputColorSpace||this._toneMapping!==t.toneMapping)&&(this._outputColorSpace=t.outputColorSpace,this._toneMapping=t.toneMapping,this.material.defines={},ne.getTransfer(this._outputColorSpace)===ce&&(this.material.defines.SRGB_TRANSFER=""),this._toneMapping===Wr?this.material.defines.LINEAR_TONE_MAPPING="":this._toneMapping===Xr?this.material.defines.REINHARD_TONE_MAPPING="":this._toneMapping===qr?this.material.defines.CINEON_TONE_MAPPING="":this._toneMapping===Ji?this.material.defines.ACES_FILMIC_TONE_MAPPING="":this._toneMapping===Zr?this.material.defines.AGX_TONE_MAPPING="":this._toneMapping===Jr?this.material.defines.NEUTRAL_TONE_MAPPING="":this._toneMapping===Yr&&(this.material.defines.CUSTOM_TONE_MAPPING=""),this.material.needsUpdate=!0),this.renderToScreen===!0?(t.setRenderTarget(null),this._fsQuad.render(t)):(t.setRenderTarget(e),this.clear&&t.clear(t.autoClearColor,t.autoClearDepth,t.autoClearStencil),this._fsQuad.render(t))}dispose(){this.material.dispose(),this._fsQuad.dispose()}};function Td(s,t=!1){let e=s[0].index!==null,n=new Set(Object.keys(s[0].attributes)),i=new Set(Object.keys(s[0].morphAttributes)),r={},a={},o=s[0].morphTargetsRelative,l=new xe,c=0;for(let h=0;h<s.length;++h){let u=s[h],d=0;if(e!==(u.index!==null))return console.error("THREE.BufferGeometryUtils: .mergeGeometries() failed with geometry at index "+h+". All geometries must have compatible attributes; make sure index attribute exists among all geometries, or in none of them."),null;for(let f in u.attributes){if(!n.has(f))return console.error("THREE.BufferGeometryUtils: .mergeGeometries() failed with geometry at index "+h+'. All geometries must have compatible attributes; make sure "'+f+'" attribute exists among all geometries, or in none of them.'),null;r[f]===void 0&&(r[f]=[]),r[f].push(u.attributes[f]),d++}if(d!==n.size)return console.error("THREE.BufferGeometryUtils: .mergeGeometries() failed with geometry at index "+h+". Make sure all geometries have the same number of attributes."),null;if(o!==u.morphTargetsRelative)return console.error("THREE.BufferGeometryUtils: .mergeGeometries() failed with geometry at index "+h+". .morphTargetsRelative must be consistent throughout all geometries."),null;for(let f in u.morphAttributes){if(!i.has(f))return console.error("THREE.BufferGeometryUtils: .mergeGeometries() failed with geometry at index "+h+".  .morphAttributes must be consistent throughout all geometries."),null;a[f]===void 0&&(a[f]=[]),a[f].push(u.morphAttributes[f])}if(t){let f;if(e)f=u.index.count;else if(u.attributes.position!==void 0)f=u.attributes.position.count;else return console.error("THREE.BufferGeometryUtils: .mergeGeometries() failed with geometry at index "+h+". The geometry must have either an index or a position attribute"),null;l.addGroup(c,f,h),c+=f}}if(e){let h=0,u=[];for(let d=0;d<s.length;++d){let f=s[d].index;for(let p=0;p<f.count;++p)u.push(f.getX(p)+h);h+=s[d].attributes.position.count}l.setIndex(u)}for(let h in r){let u=wd(r[h]);if(!u)return console.error("THREE.BufferGeometryUtils: .mergeGeometries() failed while trying to merge the "+h+" attribute."),null;l.setAttribute(h,u)}for(let h in a){let u=a[h][0].length;if(u!==0){l.morphAttributes=l.morphAttributes||{},l.morphAttributes[h]=[];for(let d=0;d<u;++d){let f=[];for(let y=0;y<a[h].length;++y)f.push(a[h][y][d]);let p=wd(f);if(!p)return console.error("THREE.BufferGeometryUtils: .mergeGeometries() failed while trying to merge the "+h+" morphAttribute."),null;l.morphAttributes[h].push(p)}}}return l}function wd(s){let t,e,n,i=-1,r=0;for(let c=0;c<s.length;++c){let h=s[c];if(t===void 0&&(t=h.array.constructor),t!==h.array.constructor)return console.error("THREE.BufferGeometryUtils: .mergeAttributes() failed. BufferAttribute.array must be of consistent array types across matching attributes."),null;if(e===void 0&&(e=h.itemSize),e!==h.itemSize)return console.error("THREE.BufferGeometryUtils: .mergeAttributes() failed. BufferAttribute.itemSize must be consistent across matching attributes."),null;if(n===void 0&&(n=h.normalized),n!==h.normalized)return console.error("THREE.BufferGeometryUtils: .mergeAttributes() failed. BufferAttribute.normalized must be consistent across matching attributes."),null;if(i===-1&&(i=h.gpuType),i!==h.gpuType)return console.error("THREE.BufferGeometryUtils: .mergeAttributes() failed. BufferAttribute.gpuType must be consistent across matching attributes."),null;r+=h.count*e}let a=new t(r),o=new ke(a,e,n),l=0;for(let c=0;c<s.length;++c){let h=s[c];if(h.isInterleavedBufferAttribute){let u=l/e;for(let d=0,f=h.count;d<f;d++)for(let p=0;p<e;p++){let y=h.getComponent(d,p);o.setComponent(d+u,p,y)}}else a.set(h.array,l);l+=h.count*e}return i!==void 0&&(o.gpuType=i),o}var o_='"Big Shoulders Display", "Arial Narrow", sans-serif';function ri(s,t){let e=document.createElement("canvas");return e.width=s,e.height=t,[e,e.getContext("2d")]}function ai(s,t=4){let e=new Vi(s);return e.colorSpace=Be,e.anisotropy=t,e}function Ii(s,t,e,n,i,r,a={}){if(s.save(),s.translate(e,n),a.rot&&s.rotate(a.rot),s.font=`${a.weight||800} ${i}px ${o_}`,"letterSpacing"in s&&(s.letterSpacing=(a.ls||0)+"px"),s.textAlign=a.align||"center",s.textBaseline="middle",s.fillStyle=r,a.glow&&(s.shadowColor=a.glow,s.shadowBlur=a.blur||12),a.max){let o=s.measureText(t).width;o>a.max&&s.scale(a.max/o,1)}s.fillText(t,0,0),s.restore()}function ha(s,t,e,n,i){if(!t)return;let r=i*(t.width/t.height||.88);s.drawImage(t,e-r/2,n-i/2,r,i)}var wl=class{constructor(t,e,n){this.scene=t,this.cfg=e,this.box=n,this.ground=n.ground,this.root=new Je,t.add(this.root),this.hype=0,this.time=0,this.sky(),this.pitch(),this.boards(),this.stands(),this.towers(),this.screenBuild(),this.figures=[this.figure(-1),this.figure(1)],this.bench()}sky(){let[t,e]=ri(16,256),n=e.createLinearGradient(0,0,0,256);n.addColorStop(0,"#01030a"),n.addColorStop(.55,"#06112e"),n.addColorStop(1,"#0d1f4a"),e.fillStyle=n,e.fillRect(0,0,16,256),this.scene.background=ai(t),this.scene.fog=new yr(330526,170,420)}pitch(){let[t,e]=ri(512,512);for(let p=0;p<8;p++)e.fillStyle=p%2?"#2f7f37":"#378f40",e.fillRect(0,p*64,512,64);for(let p=0;p<9e3;p++)e.fillStyle=`rgba(${Math.random()<.5?"0,0,0":"255,255,255"},${.03+Math.random()*.04})`,e.fillRect(Math.random()*512,Math.random()*512,1.5,3);let n=ai(t,8);n.wrapS=n.wrapT=zn,n.repeat.set(10,10);let i=new ut(new Ke(420,420),new Vt({map:n,roughness:.95}));i.rotation.x=-Math.PI/2,i.position.y=this.ground,i.receiveShadow=!0,this.root.add(i);let r=this.box,a=new $e({color:14674143,transparent:!0,opacity:.55}),o=(p,y,g,m)=>{let S=new ut(new Ke(g,m),a);S.rotation.x=-Math.PI/2,S.position.set(p,this.ground+.02,y),this.root.add(S)},l=9,c=r.x1-l,h=r.x2+l,u=r.z1-l,d=r.z2+l;o((c+h)/2,u,h-c,.5),o(c,(u+d)/2,.5,d-u),o(h,(u+d)/2,.5,d-u);let f=new ut(new Fr(17.5,18,64,1,Math.PI,Math.PI),a);f.rotation.x=-Math.PI/2,f.position.set(0,this.ground+.02,u),this.root.add(f)}boards(){let t=this.cfg,e=t.colors.yellow,n=t.colors.navy,[i,r]=ri(2048,128),a=[t.site,t.title,t.short,t.site,t.title,t.short],o=2048/a.length;a.forEach((g,m)=>{r.fillStyle=m%2?n:e,r.fillRect(m*o,0,o,128),Ii(r,String(g||"").toUpperCase(),m*o+o/2,66,74,m%2?e:n,{weight:900,ls:4,max:o-40})});let l=ai(i);l.wrapS=zn;let c=new Vt({color:659230,roughness:.6}),h=this.box,u=3.2,d=this.ground+u/2,f=(g,m,S,T,v)=>{let b=l.clone();b.needsUpdate=!0,b.repeat.set(v,1),this.boardTexs=(this.boardTexs||[]).concat(b);let w=new ut(new he(g,u,.6),[c,c,c,c,new Vt({map:b,emissiveMap:b,emissive:16777215,emissiveIntensity:.9,roughness:.5}),c]);w.position.set(m,d,S),w.rotation.y=T,this.root.add(w)},p=h.z1-30,y=52;f(2*y,0,p,0,2),f(h.z2-p+20,-y,(p+h.z2+20)/2,Math.PI/2,2),f(h.z2-p+20,y,(p+h.z2+20)/2,-Math.PI/2,2)}stands(){let t=this.cfg,e=this.box,n=new Vt({color:2765122,roughness:.9}),i=new Vt({color:new Ft(t.colors.yellow).multiplyScalar(.6),roughness:.7}),r=new Vt({color:new Ft(t.colors.blue).multiplyScalar(.6),roughness:.7}),a=14,o=2.6,l=1.35,c=[],h=[{len:150,base:[0,e.z1-36],dir:[0,-1],ry:0},{len:170,base:[-58,(e.z1+e.z2)/2-10],dir:[-1,0],ry:Math.PI/2},{len:170,base:[58,(e.z1+e.z2)/2-10],dir:[1,0],ry:-Math.PI/2}];for(let u of h){for(let m=0;m<a;m++){let S=m*o,T=this.ground+1+m*l,v=u.base[0]+u.dir[0]*S,b=u.base[1]+u.dir[1]*S,w=new ut(new he(u.dir[0]?o:u.len,1+m*l+1,u.dir[0]?u.len:o),n);w.position.set(v,this.ground+(1+m*l+1)/2-.5,b),this.root.add(w);let P=new ut(new he(u.dir[0]?.9:u.len,.7,u.dir[0]?u.len:.9),m%4<2?i:r);P.position.set(v,T+.9,b),this.root.add(P);for(let _=0;_<u.len/1.45;_++){if(Math.random()<.12)continue;let E=-u.len/2+.8+_*1.45+(Math.random()-.5)*.3;c.push([v+(u.dir[0]?0:E),T+1.2,b+(u.dir[0]?E:0),u.ry])}}let d=new ut(new he(u.dir[0]?.5:u.len,3,u.dir[0]?u.len:.5),n);d.position.set(u.base[0]-u.dir[0]*1.6,this.ground+1.5,u.base[1]-u.dir[1]*1.6),this.root.add(d),this.banners(u);let f=a*o,p=this.ground+a*l+9,y=new ut(new he(u.dir[0]?f*.8:u.len+8,.8,u.dir[0]?u.len+8:f*.8),new Vt({color:725024,roughness:.7}));y.position.set(u.base[0]+u.dir[0]*f*.62,p,u.base[1]+u.dir[1]*f*.62),this.root.add(y);let g=new ut(new he(u.dir[0]?.4:u.len+8,.4,u.dir[0]?u.len+8:.4),new $e({color:16774876}));g.position.set(u.base[0]+u.dir[0]*f*.22,p-.6,u.base[1]+u.dir[1]*f*.22),this.root.add(g)}this.crowd(c)}crowd(t){let e=new he(.95,1.5,.6);e.translate(0,.75,0);let n=new sn(.36,8,6);n.translate(0,1.85,0);let i=(p,y,g,m)=>{let S=p.attributes.position.count,T=new Float32Array(S*3);for(let v=0;v<S;v++)T.set([y,g,m],v*3);p.setAttribute("color",new ke(T,3))};i(e,1,1,1),i(n,.85,.66,.52);let r=Td([e.toNonIndexed(),n.toNonIndexed()]);this.crowdU={uTime:{value:0},uHype:{value:0}};let a=new Xi({vertexColors:!0});a.onBeforeCompile=p=>{p.uniforms.uTime=this.crowdU.uTime,p.uniforms.uHype=this.crowdU.uHype,p.vertexShader=p.vertexShader.replace("#include <common>",`#include <common>
attribute float aPhase;
uniform float uTime;
uniform float uHype;`).replace("#include <begin_vertex>",`#include <begin_vertex>
  float ph = aPhase * 6.2831;
  transformed.y += abs(sin(uTime * (2.0 + uHype * 6.0) + ph)) * (0.08 + uHype * 1.1) * step(0.35, fract(aPhase * 7.13) + uHype);
  transformed.x += sin(uTime * 1.3 + ph) * 0.05;`)};let o=new Hi(r,a,t.length),l=new Float32Array(t.length),c=[this.cfg.colors.yellow,this.cfg.colors.yellow,this.cfg.colors.blue,this.cfg.colors.blue,this.cfg.colors.navy,"#e8e8e8","#20242c","#b8bcc4"].map(p=>new Ft(p)),h=new fe,u=new pn,d=new mn,f=new D;t.forEach(([p,y,g,m],S)=>{let T=.9+Math.random()*.2;d.set(0,m+(Math.random()-.5)*.3,0),u.setFromEuler(d),f.set(T,T,T),h.compose(new D(p,y,g),u,f),o.setMatrixAt(S,h),o.setColorAt(S,c[Math.floor(Math.random()*c.length)]),l[S]=Math.random()}),r.setAttribute("aPhase",new xi(l,1)),o.frustumCulled=!1,this.root.add(o),this.crowdMesh=o}banners(t){let e=this.cfg,n=Math.floor(t.len/26);for(let i=0;i<n;i++){let[r,a]=ri(512,160),o=i%3;if(o===0){for(let h=0;h<8;h++)a.fillStyle=h%2?e.colors.blue:e.colors.yellow,a.fillRect(h*64,0,64,160);a.fillStyle="rgba(0,0,0,.25)",a.fillRect(0,0,512,160),ha(a,e.logo,256,80,140)}else o===1?(a.fillStyle=e.colors.navy,a.fillRect(0,0,512,160),a.fillStyle=e.colors.yellow,a.fillRect(0,0,512,14),a.fillRect(0,146,512,14),Ii(a,String(e.short||"").toUpperCase(),256,84,110,e.colors.yellow,{weight:900,ls:10,max:470})):(a.fillStyle=e.colors.yellow,a.fillRect(0,0,512,160),ha(a,e.logo,70,80,120),Ii(a,String(e.site||"").toUpperCase(),290,84,64,e.colors.navy,{weight:900,ls:3,max:380}));let l=new ut(new Ke(22,6.8),new Vt({map:ai(r),roughness:.8,side:He})),c=-t.len/2+13+i*26;l.position.set(t.base[0]-t.dir[0]*2+(t.dir[0]?0:c),this.ground+2.6,t.base[1]-t.dir[1]*2+(t.dir[0]?c:0)),l.rotation.y=t.ry,this.root.add(l)}}towers(){let t=this.box,e=new Vt({color:3818069,metalness:.6,roughness:.5}),[n,i]=ri(128,64);i.fillStyle="#1a1f2c",i.fillRect(0,0,128,64);for(let a=0;a<6;a++)for(let o=0;o<3;o++){let l=i.createRadialGradient(12+a*21,11+o*21,1,12+a*21,11+o*21,10);l.addColorStop(0,"#ffffff"),l.addColorStop(.5,"#fff2c8"),l.addColorStop(1,"rgba(255,240,200,0)"),i.fillStyle=l,i.fillRect(a*21+2,o*21+1,20,20)}let r=new $e({map:ai(n)});this.glows=[];for(let a of[-1,1]){let o=a*80,l=t.z1-62,c=new ut(new Ee(.9,1.4,70,10),e);c.position.set(o,this.ground+35,l),this.root.add(c);let h=new ut(new he(16,8,1),[e,e,e,e,r,e]);h.position.set(o,this.ground+72,l+1),h.rotation.x=.35,h.rotation.y=-a*.45,this.root.add(h);let[u,d]=ri(128,128),f=d.createRadialGradient(64,64,2,64,64,64);f.addColorStop(0,"rgba(255,250,235,1)"),f.addColorStop(.25,"rgba(255,240,200,.45)"),f.addColorStop(1,"rgba(255,240,200,0)"),d.fillStyle=f,d.fillRect(0,0,128,128);let p=new br(new Cs({map:ai(u),transparent:!0,depthWrite:!1,blending:Yi,fog:!1}));p.scale.set(40,26,1),p.position.set(o,this.ground+72,l+3),this.root.add(p),this.glows.push(p)}}screenBuild(){let t=this.box;[this.scrCanvas,this.scrG]=ri(1024,300),this.scrTex=ai(this.scrCanvas,8);let e=new Vt({map:this.scrTex,emissiveMap:this.scrTex,emissive:16777215,emissiveIntensity:.85,roughness:.4}),n=new Vt({color:659230,roughness:.6}),i=new ut(new he(50,14.6,1.2),[n,n,n,n,e,n]);i.position.set(0,this.ground+24,t.z1-20),i.rotation.x=-.12,this.root.add(i);for(let r of[-1,1]){let a=new ut(new he(1.2,18,1.2),n);a.position.set(r*20,this.ground+9,t.z1-20.5),this.root.add(a)}this.setScore(0,0,"",!0)}setScore(t,e,n,i=!1){let r=t+":"+e+":"+n;if(r===this.scrKey&&!i)return;this.scrKey=r;let a=this.scrG,o=this.cfg,l=o.colors.yellow;a.fillStyle="#050a18",a.fillRect(0,0,1024,300),a.fillStyle=o.colors.navy,a.fillRect(0,0,1024,70),ha(a,o.logo,50,35,58),ha(a,o.logo,974,35,58),Ii(a,String(o.title||"").toUpperCase(),512,38,50,l,{weight:900,ls:6,max:820,glow:"rgba(246,196,0,.6)",blur:10});let c=o.opp||{};a.fillStyle=l,a.fillRect(40,96,300,130),Ii(a,String(o.short||"").toUpperCase(),190,162,92,o.colors.navy,{weight:900,max:270}),a.fillStyle="#e9edf5",a.fillRect(684,96,300,130),Ii(a,String(c.ini||c.name||"").toUpperCase(),834,162,92,"#0a0f1e",{weight:900,max:270}),Ii(a,t+" \u2013 "+e,512,160,140,"#fff",{weight:900,glow:"rgba(255,255,255,.5)",blur:14}),n&&Ii(a,n,512,262,54,l,{weight:800,ls:4}),this.scrTex.needsUpdate=!0}figure(t){let e=this.cfg.kit,n=this.box,i=E=>new Vt({color:E,roughness:.55}),r=i(e.shirt),a=i(e.sleeves),o=i(e.shorts),l=i(e.socks),c=i("#d9a07a"),h=i(t<0?"#2a1a10":"#6b4423"),u=i("#111318"),d=new Je,f=1,p=(E,C,I,U,k,L=d)=>{let B=new ut(E,C);return B.position.set(I,U,k),B.castShadow=!0,L.add(B),B},y=E=>{let C=new Je;return C.position.set(E*1.25,10.2*f,0),d.add(C),p(new gn(.95,3.2,4,10),c,0,-2.4,0,C),p(new Ee(1.05,.95,4.6,12),l,0,-6.6,0,C),p(new he(1.6,1,2.8),u,0,-9.3,.6,C),C},g=y(-1),m=y(1);if(p(new Ee(2.55,2.4,3.4,16),o,0,10.4,0),p(new gn(2.45,4.4,6,16),r,0,14.6,0).scale.set(1.05,1,.72),this.cfg.logo){let[E,C]=ri(128,128);ha(C,this.cfg.logo,64,64,120);let I=new ut(new Ke(1.5,1.5),new Vt({map:ai(E),transparent:!0,roughness:.5}));I.position.set(t*-.9,16,1.8),d.add(I)}p(new Ee(.75,.9,1.2,10),c,0,18.6,0);let T=p(new sn(1.7,18,14),c,0,20.4,.1);T.scale.set(.92,1.08,.95);let v=p(new sn(1.78,18,10,0,Math.PI*2,0,Math.PI/2.1),h,0,20.75,-.05);v.rotation.x=-.25;let b=E=>{let C=new Je;return C.position.set(E*3.05,17.4,0),d.add(C),p(new gn(.85,1.8,4,10),a,0,-1.4,0,C),p(new gn(.7,2.6,4,10),c,0,-4.4,0,C),p(new sn(.75,10,8),c,0,-6.2,0,C),C.rotation.z=E*.18,C},w=b(-1),P=b(1),_=null;if(t<0){let[E,C]=ri(128,64);C.fillStyle="#f4f4f4",C.fillRect(0,0,128,64),C.fillStyle="#16181d";for(let I=0;I<6;I++)for(let U=0;U<3;U++)C.beginPath(),C.arc(10+I*22+U%2*11,10+U*22,6,0,Math.PI*2),C.fill();_=p(new sn(1.3,20,14),new Vt({map:ai(E),roughness:.45}),1.25,3,2.5)}return d.position.set(t<0?n.x1-15:n.x2+15,this.ground,n.z1+26),d.rotation.y=-t*.45,d.scale.setScalar(1.5),this.root.add(d),{root:d,legL:g,legR:m,armL:w,armR:P,head:T,ball:_,side:t,jump:0}}bench(){let t=this.box,e=new Je,n=this.cfg,i=new ut(new he(6,1,16),new Vt({color:2765122,roughness:.8}));i.position.y=.5,e.add(i);for(let a=0;a<4;a++){let o=a%2?n.colors.blue:n.colors.yellow,l=new Vt({color:o,roughness:.4}),c=new ut(new he(2.6,.6,2.8),l);c.position.set(.2,1.6,-5.4+a*3.6),e.add(c);let h=new ut(new he(.5,3.2,2.8),l);h.position.set(1.4,3.1,-5.4+a*3.6),h.rotation.z=-.12,e.add(h)}let r=new ut(new Ee(5.6,5.6,17,24,1,!0,0,Math.PI),new Vt({color:12572415,transparent:!0,opacity:.22,roughness:.1,metalness:.2,side:He,depthWrite:!1}));r.rotation.x=Math.PI/2,r.rotation.y=Math.PI/2,r.position.set(-.5,1,0),e.add(r),e.position.set(t.x2+16,this.ground,t.z2-26),this.root.add(e)}celebrate(t=1){this.hype=Math.max(this.hype,t);for(let e of this.figures)e.jump=1}update(t,e){this.time=e,this.hype=Math.max(0,this.hype-t*.22),this.crowdU.uTime.value=e,this.crowdU.uHype.value=Math.min(1,this.hype);for(let n of this.boardTexs||[])n.offset.x=e*.025%1;for(let n of this.figures){n.jump=Math.max(0,n.jump-t*.35);let i=n.jump>0,r=i?Math.abs(Math.sin(e*7))*1.6*Math.min(1,n.jump*3):0;n.root.position.y=this.ground+r;let a=i?Math.min(1,n.jump*4):0;if(n.side<0){let o=e*1.6%1,l=Math.max(0,Math.sin(o*Math.PI*2))*(1-a);n.legR.rotation.x=-l*1.1,n.ball&&(n.ball.position.y=3+7*Math.sin(o*Math.PI)*(1-a)+a*26,n.ball.position.z=2.5+l*1.5,n.ball.rotation.x+=t*4),n.armL.rotation.z=-.18-a*2.6-(1-a)*.25,n.armR.rotation.z=.18+a*2.6+(1-a)*.25}else{let o=Math.sin(e*6)*.25*(1-a);n.armL.rotation.x=-.9*(1-a)+o,n.armR.rotation.x=-.9*(1-a)+o,n.armL.rotation.z=-.18-a*2.7+(1-a)*.35,n.armR.rotation.z=.18+a*2.7-(1-a)*.35}n.head.rotation.y=Math.sin(e*.7+n.side)*.25}}setQuality(t){this.crowdMesh&&(this.crowdMesh.visible=t>=1)}};var Kt=50/400,bn=s=>(s-400/2)*Kt,Ve=s=>(s-874/2)*Kt,le=(s,t,e=0)=>new D(bn(s),e,Ve(t)),l_='"Big Shoulders Display", "Arial Narrow", sans-serif',Ne=56*Kt,Tl=700,Al=366;function Mn(s,t){let e=document.createElement("canvas");return e.width=s,e.height=t,[e,e.getContext("2d")]}var Pd=8;function Sn(s){let t=new Vi(s);return t.colorSpace=Be,t.anisotropy=Pd,t}function je(s,t,e,n,i,r,a={}){if(s.save(),s.translate(e,n),a.rot&&s.rotate(a.rot),s.font=`${a.weight||800} ${i}px ${l_}`,"letterSpacing"in s&&(s.letterSpacing=(a.ls||0)+"px"),s.textAlign=a.align||"center",s.textBaseline="middle",s.fillStyle=r,a.glow&&(s.shadowColor=a.glow,s.shadowBlur=a.blur||12),a.max){let o=s.measureText(t).width;o>a.max&&s.scale(a.max/o,1)}s.fillText(t,0,0),s.restore()}function Pl(s,t){s.beginPath(),t.forEach(([e,n],i)=>s[i?"lineTo":"moveTo"](e,n)),s.closePath()}function Zs(s,t,e,n){Pl(s,Array.from({length:6},(i,r)=>[t+Math.cos(r*Math.PI/3)*n,e+Math.sin(r*Math.PI/3)*n]))}function Ad(s,t,e,n){Pl(s,Array.from({length:5},(i,r)=>[t+Math.cos(-Math.PI/2+r*2*Math.PI/5)*n,e+Math.sin(-Math.PI/2+r*2*Math.PI/5)*n]))}function Rl(s,t,e,n,i){if(!t)return;let r=i*(t.width/t.height||.88);s.drawImage(t,e-r/2,n-i/2,r,i)}function Rd(s,t,e,n,i,r){je(s,t.replace(/[0-9]/g,"8"),e,n,i,"rgba(255,255,255,.07)",{weight:900,ls:4}),je(s,t,e,n,i,r,{weight:900,ls:4,glow:r,blur:14})}function Cd(s,t,e,n,i,r,a,o){s.save(),s.translate(t,e),s.scale(n,n),Pl(s,[[-14,-16],[-22,-8],[-17,0],[-13,-3],[-13,20],[13,20],[13,-3],[17,0],[22,-8],[14,-16],[6,-16],[0,-11],[-6,-16]]),s.fillStyle=i,s.fill(),s.fillStyle=r,s.fillRect(-22,-9,6,5),s.fillRect(16,-9,6,5),a&&je(s,String(a),0,5,18,o,{weight:900}),s.restore()}var Cl=class{constructor(t,e,n){this.t=e,this.cfg=n,this.lb=n.labels||{},this.el=t,this.col={navy:new Ft(n.colors.navy),yellow:new Ft(n.colors.yellow),blue:new Ft(n.colors.blue)},this.Y=n.colors.yellow,this.N=n.colors.navy,this.B=n.colors.blue,this.shake=0,this.quality=2,this.renderer=new gl({canvas:t,antialias:!0,powerPreference:"high-performance"}),this.renderer.setPixelRatio(Math.min(window.devicePixelRatio||1,2)),Pd=Math.min(16,this.renderer.capabilities.getMaxAnisotropy()||8),this.renderer.shadowMap.enabled=!0,this.renderer.shadowMap.type=qi,this.renderer.toneMapping=Ji,this.renderer.outputColorSpace=Be,this.scene=new zi;let i=new Gs(this.renderer);this.scene.environment=i.fromScene(new yl,.04).texture,this.scene.environmentIntensity=.45,this.camera=new Oe(39,1,1,1200),this.build()}build(){let t=this.scene,e=this.t,n=this.col.yellow;this.mat={wall:new Vt({color:726592,roughness:.5}),chrome:new Vt({color:14673390,metalness:1,roughness:.15}),neon:new $e({color:n.clone().multiplyScalar(1.5)}),rubber:new Vt({color:15921906,roughness:.6}),yellow:new Vt({color:n,roughness:.3,emissive:n,emissiveIntensity:.12}),blue:new Vt({color:this.col.blue,roughness:.35,emissive:this.col.blue,emissiveIntensity:.15}),hole:new $e({color:66314})},this.playfield(),this.lampsBuild();for(let p of e.walls)p.block||(p.wire?this.wire(p.pts,p.h):this.wall(p.pts,p.h,p.r*Kt*2.2,p.neon));this.bumpersBuild(),this.slingsBuild(),this.kickerBuild(),this.holesBuild(),this.flippersBuild();let i={passe:[this.lb.passe,.55],dribble:[this.lb.dribble,.72,!0],centre:[this.lb.centre,.2],"ballon-long":[this.lb.ballonLong,.22,!0]};this.t.ramps.forEach(p=>this.rampBuild(p,...i[p.name]||[""])),this.plungerBuild(),this.miniBuild(),this.apronBuild();let r=-9,a=new Vt({color:this.col.navy,roughness:.45,metalness:.2}),o=(Ve(0)+Ve(874))/2,l=874*Kt+4;for(let p of[-1,1]){let y=new ut(new he(3,7,l),a);y.position.set(p*(400*Kt/2+1.6),1.5,o),y.castShadow=y.receiveShadow=!0,t.add(y);let g=new ut(new he(.35,.3,l),this.mat.neon);g.position.set(p*(400*Kt/2+2.9),5.05,o),t.add(g)}let c=new ut(new he(400*Kt+6,-r-2,l),new Vt({color:528428,roughness:.6}));c.position.set(0,(r-2)/2,o),t.add(c);for(let p of[Ve(0)-1.5,Ve(874)+1.5]){let y=new ut(new he(400*Kt+6,7,3),a);y.position.set(0,1.5,p),t.add(y)}this.stadium=new wl(t,{colors:this.cfg.colors,kit:this.cfg.kit,logo:this.cfg.logo,title:this.cfg.title,site:this.cfg.site,short:this.cfg.short,opp:this.cfg.opp},{x1:-400*Kt/2-3,x2:400*Kt/2+3,z1:Ve(0)-3,z2:Ve(874)+3,ground:r});let h=new Vt({color:16777215,metalness:1,roughness:.06});this.balls=Array.from({length:5},()=>{let p=new ut(new sn(10*Kt,32,24),h);return p.castShadow=!0,p.visible=!1,t.add(p),p}),this.lockBalls=e.sensors.filter(p=>p.tag==="lock").map(p=>{let y=new ut(new sn(10*Kt,24,16),h);return y.position.copy(le(p.x,p.y,10*Kt-.3)),y.visible=!1,t.add(y),y}),t.add(new Vr(8425664,.16)),t.add(new kr(9414911,1919522,.35)),this.key=new Hr(16774368,500,0,.8,.6,1.5),this.key.position.set(0,82,30),this.key.target.position.set(0,0,-4),this.key.castShadow=!0,this.key.shadow.mapSize.set(2048,2048),this.key.shadow.bias=-5e-4,t.add(this.key,this.key.target);let u=new Ns(14674175,1.1);u.position.set(-60,90,-80),t.add(u);let d=new Ns(14674175,.7);d.position.set(70,80,-60),t.add(d);let f=new bi(16756768,40,70,1.6);f.position.set(18,14,26),t.add(f)}playfield(){let e=Math.min(screen.width||0,screen.height||0)*Math.min(window.devicePixelRatio||1,2)>=700?4:2.5,[n,i]=Mn(400*e,874*e),r=this.t,a=r.deco,o=this.Y,l=this.lb;i.scale(e,e);for(let g=0;g<22;g++)i.fillStyle=g%2?"#2f8a3b":"#3a9a45",i.fillRect(0,g*40,400,40);let c=i.createRadialGradient(185,520,60,185,520,560);c.addColorStop(0,"rgba(255,255,200,.10)"),c.addColorStop(.6,"rgba(0,0,0,0)"),c.addColorStop(1,"rgba(0,10,30,.55)"),i.fillStyle=c,i.fillRect(0,0,400,874);for(let g=0;g<16e3;g++)i.fillStyle=`rgba(${Math.random()<.5?"0,0,0":"255,255,255"},${.03+Math.random()*.04})`,i.fillRect(Math.random()*400,Math.random()*874,.6,1.4);i.fillStyle="rgba(6,14,40,.55)",i.fillRect(0,0,400,240),i.fillRect(366,240,34,874),i.strokeStyle="rgba(255,255,255,.75)",i.lineWidth=2,i.beginPath(),i.moveTo(10,a.circle.y),i.lineTo(358,a.circle.y),i.stroke(),i.beginPath(),i.arc(a.circle.x,a.circle.y,a.circle.r,0,Math.PI*2),i.stroke(),i.beginPath(),i.arc(a.circle.x,a.circle.y,2.5,0,Math.PI*2),i.fillStyle="rgba(255,255,255,.8)",i.fill();let h=a.box;i.beginPath(),i.moveTo(h.x1,874),i.lineTo(h.x1,h.y1),i.lineTo(h.x2,h.y1),i.lineTo(h.x2,874),i.stroke(),i.beginPath(),i.arc(a.circle.x,h.y1,26,Math.PI,0),i.stroke(),this.cfg.logo&&(i.globalAlpha=.2,Rl(i,this.cfg.logo,a.circle.x,a.circle.y,70),i.globalAlpha=1);let u=(g,m=o)=>{g(),i.fillStyle="rgba(6,12,34,.88)",i.fill(),i.strokeStyle=m,i.lineWidth=1.3,i.stroke()};for(let[g,m]of a.hexes)u(()=>Zs(i,g,m,18.5));u(()=>Zs(i,a.goalie[0],a.goalie[1],19));for(let[g,m]of a.smallHexes)u(()=>Zs(i,g,m,9.5),"rgba(255,255,255,.55)");for(let[g,m]of a.pentagons)u(()=>Ad(i,g,m,11),"rgba(255,255,255,.55)");for(let[g,m]of a.dots)u(()=>{i.beginPath(),i.arc(g,m,7.5,0,Math.PI*2)},"rgba(255,255,255,.55)");for(let[g,m]of a.modes)u(()=>{i.beginPath(),i.roundRect(g-17,m-5.5,34,11,5.5)});let d=a.plate;i.fillStyle="#d9dee6",i.beginPath(),i.roundRect(d.x1,d.y1,d.x2-d.x1,d.y2-d.y1,5),i.fill(),i.fillStyle="#05070c",i.beginPath(),i.roundRect(30,476,8,36,4),i.fill();let f=(g,m,S,T,v=0,b="#FFF3C2",w=1.5)=>je(i,String(g||"").toUpperCase(),m,S,T,b,{rot:v,ls:w,weight:900});f(l.bumpers,112,513,8,0,"rgba(255,243,194,.95)",2.5),f(l.trick,40,360,6.5,-Math.PI/2),f(l.trap,46,523,6),f(l.onetwo,202,300,6,-Math.PI/2),f(l.onetwo,340,392,6,-1.2),f(l.attack,68,600,5.5,-Math.PI/2),f(l.attack,302,600,5.5,Math.PI/2),f(l.tackle,28,712,6.5,.6),f(l.tackle,341,712,6.5,-.6),i.fillStyle="rgba(4,8,24,.6)",i.fillRect(369,480,24,330);let p=Sn(n),y=new ut(new Ke(400*Kt,874*Kt),new Vt({map:p,roughness:.45,metalness:.05}));y.rotation.x=-Math.PI/2,y.receiveShadow=!0,this.scene.add(y)}lamp(t,e,n,i,r=11,a="round",o=.03,l={}){let c=a==="pill"?256:128,[h,u]=Mn(c,128);u.fillStyle=l.color||this.Y,a==="hex"?Zs(u,64,64,62):a==="penta"?Ad(u,64,66,62):a==="pill"?(u.beginPath(),u.roundRect(4,24,248,80,40)):(u.beginPath(),u.arc(64,64,60,0,Math.PI*2)),u.fill(),i&&je(u,i,c/2,68,r*5.2,l.ink||this.N,{weight:900,max:c-20});let d=Sn(h),f=new Vt({map:d,emissiveMap:d,emissive:16777215,emissiveIntensity:0,roughness:.35,transparent:!0,color:6969888}),p=a==="pill"?new Ke(n*2*Kt*2,n*Kt*1.25):a==="hex"?new xn(n*Kt,6):a==="penta"?new xn(n*Kt,5,Math.PI/2):new xn(n*Kt,32);p.rotateX(-Math.PI/2);let y=new ut(p,f);return y.position.copy(le(t,e,o)),this.scene.add(y),{m:y,mat:f,c:h,g:u,t:d,on:0}}lampsBuild(){let t=this.t,e=t.deco,n=this.lb;if(this.L={hexes:e.hexes.map(([i,r])=>this.lamp(i,r,17.5,"",8,"hex")),save:this.lamp(e.goalie[0],e.goalie[1],18,"",8,"hex"),small:[...e.smallHexes.map(([i,r])=>this.lamp(i,r,9,"",8,"hex",.03,{color:"#ffffff"})),...e.pentagons.map(([i,r])=>this.lamp(i,r,10.5,"",8,"penta",.03,{color:"#ffffff"})),...e.dots.map(([i,r])=>this.lamp(i,r,7,"",8,"round",.03,{color:"#ffffff"}))],mode:this.lamp(e.modes[0][0],e.modes[0][1],8.6,"",8,"pill"),modeLabel:this.lamp(e.modes[1][0],e.modes[1][1],8.6,String(n.trick||"").toUpperCase(),8,"pill"),extra:this.lamp(e.lamp[0],e.lamp[1],8,"+1",9),mults:e.mults.map(([i,r])=>this.lamp(381,r,10,i,i.length>3?6:8.5,"hex")),locks:[[97,296],[246,392],[343,522]].map(([i,r],a)=>this.lamp(i,r,11.5,String(n.lines?n.lines[["att","mil","def"][a]]:"").toUpperCase(),3.6,"pill")),lockArrows:e.chevrons.map(([i,r,a])=>this.chevron(i,r,a)),shots:{r2:[this.chevron(112,392,-.35),this.chevron(118,408,-.35)],r3:[this.chevron(272,418,.4),this.chevron(266,434,.4)],r4:[this.chevron(146,302,0),this.chevron(146,318,0)],onetwo0:[this.chevron(202,352,0)],onetwo1:[this.chevron(322,420,-.3)]},inlanes:t.sensors.filter(i=>i.tag==="inlane").map(i=>this.lamp(i.x,i.y+22,8,"",8)),supers:[[42,609],[327,609]].map(([i,r])=>this.lamp(i,r+30,7,"SJ",7)),tackles:[[28.7,697],[340.5,697]].map(([i,r])=>this.lamp(i,r,8.6,"",8,"hex"))},this.paintHexes(),this.paintMode("jackpot"),this.cfg.logo){let[i,r]=Mn(128,128);Rl(r,this.cfg.logo,64,64,100);let a=new ut(new xn(12*Kt,32),new $e({map:Sn(i),transparent:!0}));a.rotation.x=-Math.PI/2,a.position.copy(le(e.goalie[0],e.goalie[1],.05)),this.scene.add(a)}}paintHexes(){let t=this.cfg.opp&&this.cfg.opp.kit?this.cfg.opp.kit:{shirt:"#e9edf5",sleeves:"#e9edf5"},e=new Ft(t.shirt).getHSL({}).l>.6,n=[9,10,6,8,7,11,2,4,5,3];this.L.hexes.forEach((i,r)=>{let a=i.g;a.clearRect(0,0,128,128),Zs(a,64,64,62),a.fillStyle="#f4f1e6",a.fill(),Zs(a,64,64,56),a.fillStyle="#1c2a4a",a.fill(),Cd(a,64,66,2.2,t.shirt,t.sleeves||t.shirt,n[r],e?"#14161b":"#ffffff"),i.t.needsUpdate=!0})}paintMode(t){if(t===this.modeShown)return;this.modeShown=t;let e=this.L.mode,n=e.g,i=this.lb.modes||{};n.clearRect(0,0,256,128),n.fillStyle=this.Y,n.beginPath(),n.roundRect(4,24,248,80,40),n.fill(),je(n,String(i[t]||t).toUpperCase(),128,68,40,this.N,{weight:900,max:230}),e.t.needsUpdate=!0}chevron(t,e,n=0){let i=new In;i.moveTo(-7,6),i.lineTo(0,-4),i.lineTo(7,6),i.lineTo(7,10),i.lineTo(0,0),i.lineTo(-7,10),i.closePath();let r=new Br(i);r.rotateZ(-n),r.scale(Kt,Kt,Kt),r.rotateX(-Math.PI/2);let a=new Vt({color:6969888,emissive:this.col.yellow,emissiveIntensity:0,roughness:.4,side:He}),o=new ut(r,a);return o.position.copy(le(t,e,.04)),this.scene.add(o),{m:o,mat:a,on:0}}wall(t,e,n,i,r=0,a=this.mat.wall){for(let o=0;o<t.length-1;o++){let l=le(...t[o]),c=le(...t[o+1]),h=l.distanceTo(c);if(h<.01)continue;let u=new ut(new he(n,e,h+n*.6),a);u.position.set((l.x+c.x)/2,r+e/2,(l.z+c.z)/2),u.rotation.y=Math.atan2(c.x-l.x,c.z-l.z),u.castShadow=u.receiveShadow=!0,this.scene.add(u)}if(i){let o=new Pn(t.map(l=>le(l[0],l[1],r+e+.05)),!1,"catmullrom",0);this.scene.add(new ut(new Xn(o,t.length*4,.2,8),this.mat.neon))}}wire(t,e=1.6,n=0){for(let i of[.55,e]){let r=new Pn(t.map(a=>le(a[0],a[1],n+i)),!1,"catmullrom",0);this.scene.add(new ut(new Xn(r,t.length*6,.14,6),this.mat.chrome))}for(let i of[t[0],t[t.length-1]]){let r=new ut(new Ee(.18,.18,e,8),this.mat.chrome);r.position.copy(le(i[0],i[1],n+e/2)),this.scene.add(r)}}bumpersBuild(){let[t,e]=Mn(256,256);e.fillStyle=this.N,e.beginPath(),e.arc(128,128,128,0,Math.PI*2),e.fill(),e.fillStyle=this.Y,e.beginPath(),e.arc(128,128,118,0,Math.PI*2),e.fill(),e.fillStyle=this.B,e.beginPath(),e.arc(128,128,100,0,Math.PI*2),e.fill(),this.cfg.logo?Rl(e,this.cfg.logo,128,128,170):je(e,this.lb.bumperCap||"",128,132,64,this.Y,{weight:900});let n=Sn(t),[i,r]=Mn(512,64);r.fillStyle=this.N,r.fillRect(0,0,512,64);for(let o=0;o<2;o++)je(r,String(this.cfg.site||"").toUpperCase(),128+o*256,34,34,this.Y,{weight:900,ls:3,max:240});let a=Sn(i);a.wrapS=zn,this.bumpers=this.t.bumpers.map(o=>{let l=le(o.x,o.y),c=o.r*Kt,h=c*1.75,u=new ut(new Ee(c*1.1,c*1.1,.5,40),this.mat.rubber);u.position.set(l.x,.25,l.z),this.scene.add(u);let d=new $e({color:this.col.yellow.clone().multiplyScalar(1.5)}),f=new ut(new Wi(c*1,.3,12,48),d);f.rotation.x=Math.PI/2,f.position.set(l.x,1,l.z),this.scene.add(f);let p=new bi(16763955,6,12,2);p.position.set(l.x,4,l.z),this.scene.add(p);let y=new ut(new Ee(c*.75,c*.75,3.4,32),new Vt({color:this.col.blue,emissive:this.col.blue,emissiveIntensity:.35,roughness:.3}));y.position.set(l.x,1.9,l.z),y.castShadow=!0,this.scene.add(y);let g=new ut(new Ee(h,h,1.3,48,1,!0),new Vt({map:a,roughness:.35,emissiveMap:a,emissive:16777215,emissiveIntensity:.3}));g.position.set(l.x,4.15,l.z),g.castShadow=!0,this.scene.add(g);let m=new Vt({map:n,emissiveMap:n,emissive:16777215,emissiveIntensity:.3,roughness:.3}),S=new ut(new xn(h,48),m);S.rotation.x=-Math.PI/2,S.position.set(l.x,4.81,l.z),this.scene.add(S);let T=new ut(new xn(h,48),this.mat.wall);return T.rotation.x=Math.PI/2,T.position.set(l.x,3.5,l.z),this.scene.add(T),{ringMat:d,discMat:m,light:p,flash:0}})}slingsBuild(){this.slings=this.t.slings.map(t=>{let e=[t.a,t.b,t.c].map(([l,c])=>new rt(bn(l),-Ve(c))),n=new In,i=4*Kt;e.forEach((l,c)=>{let h=e[(c+2)%3],u=e[(c+1)%3],d=l.clone().add(h.clone().sub(l).setLength(i)),f=l.clone().add(u.clone().sub(l).setLength(i));c===0?n.moveTo(d.x,d.y):n.lineTo(d.x,d.y),n.quadraticCurveTo(l.x,l.y,f.x,f.y)}),n.closePath();let r=new ni(n,{depth:2.2,bevelEnabled:!0,bevelSize:.25,bevelThickness:.2,bevelSegments:2}),a=new Vt({color:this.col.yellow,emissive:this.col.yellow,emissiveIntensity:.15,roughness:.3}),o=new ut(r,[a,this.mat.rubber]);return o.rotation.x=-Math.PI/2,o.castShadow=!0,this.scene.add(o),{mat:a,flash:0}}),this.leds=this.t.slings.map(t=>{let[e,n]=Mn(256,176),i=Sn(e),r=new Vt({map:i,emissiveMap:i,emissive:16777215,emissiveIntensity:.9,roughness:.4}),a=new Vt({color:1316379,roughness:.4}),o=new ut(new he(2.5,1.7,.6),[a,a,a,a,r,a]);return o.position.copy(le(t.screen[0],t.screen[1],3.1)),o.rotation.x=-.7,o.castShadow=!0,this.scene.add(o),{c:e,g:n,tx:i,side:t.side,val:null,flash:0}}),this.drawLeds(0,0,!0)}drawLeds(t,e,n=!1){for(let i of this.leds){let r=i.side==="L"?t:e,a=r+":"+(i.flash>0?Math.floor(i.flash*8)%2:0);if(a===i.val&&!n)continue;i.val=a;let o=i.g,l=this.cfg.opp||{};o.fillStyle="#05070c",o.fillRect(0,0,256,176);let c=i.side==="L"?this.cfg.short||this.lb.home||"":l.ini||this.lb.away||"";o.fillStyle=i.side==="L"?this.Y:"#e9edf5",o.fillRect(0,128,256,48),je(o,String(c).toUpperCase(),128,153,40,i.side==="L"?this.N:"#0a0f1e",{weight:900,ls:3,max:236});let h=i.flash>0&&Math.floor(i.flash*8)%2;Rd(o,String(r).padStart(2,"0"),128,66,110,h?"#ffffff":"#7ff0a0"),i.tx.needsUpdate=!0}}kickerBuild(){let t=this.t.kicker,e=le(t.a[0],t.a[1],1.1),n=le(t.b[0],t.b[1],1.1),i=e.distanceTo(n),r=new ut(new he(.9,1.4,i),this.mat.rubber);r.position.copy(e.clone().add(n).multiplyScalar(.5)),r.rotation.y=Math.atan2(n.x-e.x,n.z-e.z),r.castShadow=!0,this.scene.add(r),this.kickerMat=new Vt({color:this.col.yellow,emissive:this.col.yellow,emissiveIntensity:.2,roughness:.3});for(let a of[e,n]){let o=new ut(new Ee(.55,.55,2.2,16),this.kickerMat);o.position.set(a.x,1.1,a.z),o.castShadow=!0,this.scene.add(o)}this.kickFlash=0}holesBuild(){for(let t of this.t.sensors.filter(e=>["lock","trick"].includes(e.tag))){let e=new ut(new xn(t.r*Kt*1.15,32),this.mat.hole);e.rotation.x=-Math.PI/2,e.position.copy(le(t.x,t.y,.05)),this.scene.add(e);let n=new ut(new Wi(t.r*Kt*1.2,.12,8,32),this.mat.chrome);n.rotation.x=Math.PI/2,n.position.copy(le(t.x,t.y,.08)),this.scene.add(n)}for(let t of this.t.sensors.filter(e=>["inlane","outlane","onetwo"].includes(e.tag))){let e=new ut(new Ee(.42,.5,.25,16),this.mat.chrome);e.position.copy(le(t.x,t.y,.12)),this.scene.add(e)}}flippersBuild(){this.flippers=this.t.flippers.map(t=>{let e=t.len*Kt,n=t.r1*Kt,i=t.r2*Kt,r=t.lv?Ne:0,a=new In;a.absarc(0,0,n,Math.PI/2,Math.PI*1.5,!1),a.lineTo(e,-i),a.absarc(e,0,i,-Math.PI/2,Math.PI/2,!1),a.lineTo(0,n);let o=new ni(a,{depth:t.mini?.9:1.3,bevelEnabled:!0,bevelSize:.15,bevelThickness:.15,bevelSegments:2});o.rotateX(-Math.PI/2);let l=new ut(o,t.tackle?this.mat.blue:this.mat.yellow);l.position.copy(le(t.px,t.py,r+.2)),l.castShadow=!0,this.scene.add(l);let c=new ut(new ni(a,{depth:.35,bevelEnabled:!1}),t.tackle?this.mat.yellow:this.mat.blue);c.geometry.rotateX(-Math.PI/2),c.scale.set(1.04,1,1.12),c.position.y=.45,l.add(c);let h=new ut(new Ee(.4,.4,1.7,16),this.mat.chrome);return h.position.copy(le(t.px,t.py,r+.85)),this.scene.add(h),l})}rampBuild(t,e,n=.5,i=!1){let r=t.pts,a=t.width,o=t.wire===null?r.length-1:Math.min(r.length-1,t.wire),l=h=>{let u=r[h],d=r[Math.min(h+1,r.length-1)],f=r[Math.max(h-1,0)],p=new D(d.x-f.x,0,d.y-f.y).normalize(),y=new D(-p.z,0,p.x);return{c:le(u.x,u.y,u.h*Kt+.25),n:y}};if(o>1){let h=[],u=[],d=[],f=[],p=[],y=[],g=[];for(let P=0;P<=o;P++){let{c:_,n:E}=l(P),C=_.clone().addScaledVector(E,a),I=_.clone().addScaledVector(E,-a);if(h.push(C.x,C.y,C.z,I.x,I.y,I.z),u.push(1,r[P].s/r[o].s,0,r[P].s/r[o].s),f.push({c:C,n:E}),p.push({c:I,n:E.clone().negate()}),y.push(C.clone().addScaledVector(E,.3).setY(C.y+1.7)),g.push(I.clone().addScaledVector(E,-.3).setY(I.y+1.7)),P){let U=P*2;d.push(U-2,U-1,U,U-1,U+1,U)}}let m=new xe;m.setAttribute("position",new Zt(h,3)),m.setAttribute("uv",new Zt(u,2)),m.setIndex(d),m.computeVertexNormals();let[S,T]=Mn(128,1024);for(let P=0;P<16;P++)T.fillStyle=P%2?"#4fb84f":"#5fca5c",T.fillRect(0,P*64,128,64);T.fillStyle=this.N,T.fillRect(0,0,10,1024),T.fillRect(118,0,10,1024),T.fillStyle="rgba(255,255,255,.9)",T.fillRect(12,0,4,1024),T.fillRect(112,0,4,1024),e&&je(T,String(e).toUpperCase(),64,1024*(1-n),e.length>9?46:60,this.Y,{rot:i?Math.PI/2:-Math.PI/2,ls:10,weight:900,glow:"rgba(0,10,40,.9)",blur:8,max:520});let v=new ut(m,new Vt({map:Sn(S),side:He,roughness:.5}));v.castShadow=!0,v.receiveShadow=!0,this.scene.add(v);let b=new Vt({color:this.col.yellow,emissive:this.col.yellow,emissiveIntensity:.16,roughness:.35,side:He}),w=P=>{let _=[],E=[],C=[[0,-.6],[0,1.6],[.55,1.6],[.55,-.6]];P.forEach(({c:k,n:L})=>{for(let[B,W]of C){let X=k.clone().addScaledVector(L,B);_.push(X.x,X.y+W,X.z)}});for(let k=1;k<P.length;k++)for(let L=0;L<3;L++){let B=(k-1)*4+L,W=k*4+L;E.push(B,W,B+1,W,W+1,B+1)}let I=new xe;I.setAttribute("position",new Zt(_,3)),I.setIndex(E),I.computeVertexNormals();let U=new ut(I,b);U.castShadow=!0,this.scene.add(U)};w(f),w(p);for(let P of[y,g])this.scene.add(new ut(new Xn(new Pn(P),P.length*2,.36,8),this.mat.blue))}if(o<r.length-1){let h=Math.max(0,o-1);for(let[u,d]of[[-.5,.05],[.5,.05],[-.95,.8],[.95,.8]]){let f=[];for(let p=h;p<r.length;p++){let{c:y,n:g}=l(p);f.push(y.clone().addScaledVector(g,u).setY(y.y+d))}this.scene.add(new ut(new Xn(new Pn(f),f.length*3,.12,6),this.mat.chrome))}}let c=this.t.block;for(let h=6;h<r.length-3;h+=14){let u=r[h];if(u.h<14||u.x>c.x1&&u.x<c.x2&&u.y>c.y1&&u.y<c.y2)continue;let d=u.h*Kt,f=new ut(new Ee(.15,.15,d,8),this.mat.chrome);f.position.copy(le(u.x,u.y,d/2)),this.scene.add(f)}}plungerBuild(){let t=this.t.plunger,e=bn(t.x),n=Ve(t.top);this.knob=new ut(new Ee(1,1,2.4,24),this.mat.yellow),this.knob.rotation.x=Math.PI/2,this.knob.position.set(e,1.2,n+2.2),this.scene.add(this.knob);let i=new ut(new Xn(new Pn(Array.from({length:50},(r,a)=>new D(Math.cos(a*.8)*.75,1.2+Math.sin(a*.8)*.75,a*.08))),120,.09,6),this.mat.chrome);i.position.set(e,0,n+3.4),this.scene.add(i),this.plungerZ=n+2.2}miniBuild(){let t=this.t.mini,e=this.scene;if(!t)return;let n=this.t.walls.find(nt=>nt.block).pts.slice(0,-1),i=new In(n.map(([nt,ct])=>new rt(bn(nt),-Ve(ct)))),r=new ni(i,{depth:Ne,bevelEnabled:!1});r.rotateX(-Math.PI/2);let a=r.attributes.position,o=r.attributes.uv,l=bn(t.x1),c=bn(t.x2),h=Ve(t.y1),u=Ve(t.y2);for(let nt=0;nt<a.count;nt++)o.setXY(nt,(a.getX(nt)-l)/(c-l),1-(a.getZ(nt)-h)/(u-h));let[d,f]=Mn(512,640),p=nt=>(nt-t.x1)/(t.x2-t.x1)*512,y=nt=>(nt-t.y1)/(t.y2-t.y1)*640;for(let nt=0;nt<10;nt++)f.fillStyle=nt%2?"#2f8a3b":"#3d9e48",f.fillRect(0,nt*64,512,64);f.strokeStyle="rgba(255,255,255,.85)",f.lineWidth=4,f.strokeRect(p(148),y(98),p(310)-p(148),y(226)-y(98)),f.strokeRect(p(200),y(98),p(258)-p(200),y(131)-y(98)),f.beginPath(),f.arc(p(229.5),y(226),60,0,Math.PI),f.stroke(),f.fillStyle="rgba(255,255,255,.85)",f.beginPath(),f.arc(p(229.5),y(190),6,0,Math.PI*2),f.fill(),f.fillStyle="rgba(10,16,40,.55)";for(let nt of t.rows){let ct=Math.max(...nt.offs)+nt.amp+10;f.fillRect(p(t.center-ct),y(nt.y)-5,p(t.center+ct)-p(t.center-ct),10)}let g=new Vt({map:Sn(d),roughness:.55}),m=new Vt({color:this.col.navy,roughness:.45,metalness:.2}),S=new ut(r,[g,m]);S.castShadow=S.receiveShadow=!0,e.add(S);let T=n.concat([n[0]]).map(([nt,ct])=>le(nt,ct,Ne+.05));e.add(new ut(new Xn(new Pn(T,!0,"catmullrom",0),80,.18,6,!0),this.mat.neon));let[v,b]=Mn(512,96);this.miniFront={c:v,g:b,tx:Sn(v),key:""};let w=new ut(new Ke((245-212.6)*Kt*1.6,Ne*.55),new Vt({map:this.miniFront.tx,emissiveMap:this.miniFront.tx,emissive:16777215,emissiveIntensity:.9}));w.position.copy(le(228.8,299.4,Ne*.5)),e.add(w),this.drawMiniFront(!1);let P=new Vt({color:16777215,roughness:.3});for(let nt of t.walls)nt.net||this.wall(nt.pts,1.1,nt.r*Kt*2,!1,Ne,P);for(let nt of t.posts.slice(2)){let ct=new ut(new Ee(nt.r*Kt,nt.r*Kt,1.6,16),this.mat.rubber);ct.position.copy(le(nt.x,nt.y,Ne+.8)),e.add(ct)}for(let nt of t.holes){let ct=new ut(new xn(nt.r*Kt*1.15,32),this.mat.hole);ct.rotation.x=-Math.PI/2,ct.position.copy(le(nt.x,nt.y,Ne+.04)),e.add(ct);let Yt=new ut(new Wi(nt.r*Kt*1.25,.22,10,32),this.mat.chrome);Yt.rotation.x=Math.PI/2,Yt.position.copy(le(nt.x,nt.y,Ne+.1)),e.add(Yt)}let _=bn(t.posts[0].x),E=bn(t.posts[1].x),C=Ve(t.posts[0].y),I=Ve(t.y1+2),U=2.6,k=new Vt({color:16777215,roughness:.25,emissive:16777215,emissiveIntensity:.15});for(let nt of[_,E]){let ct=new ut(new Ee(.22,.22,U,12),k);ct.position.set(nt,Ne+U/2,C),ct.castShadow=!0,e.add(ct)}let L=new ut(new Ee(.22,.22,E-_,12),k);L.rotation.z=Math.PI/2,L.position.set((_+E)/2,Ne+U,C),e.add(L);let[B,W]=Mn(256,256);W.strokeStyle="rgba(255,255,255,.75)",W.lineWidth=3;for(let nt=0;nt<=256;nt+=16)W.beginPath(),W.moveTo(nt,0),W.lineTo(nt,256),W.stroke(),W.beginPath(),W.moveTo(0,nt),W.lineTo(256,nt),W.stroke();let X=Sn(B);X.wrapS=X.wrapT=zn;let st=new Vt({map:X,transparent:!0,side:He,depthWrite:!1,roughness:.8,alphaTest:.05}),Z=(nt,ct,Yt,Jt,Qt,J,$)=>{let mt=X.clone();mt.needsUpdate=!0,mt.repeat.set(nt/1.2,ct/1.2);let Lt=st.clone();Lt.map=mt;let gt=new ut(new Ke(nt,ct),Lt);return gt.position.set(Yt,Jt,Qt),gt.rotation.set(J,$,0),e.add(gt),gt};this.nets=[Z(E-_,U,(_+E)/2,Ne+U/2,I,0,0),Z(C-I,U,_,Ne+U/2,(C+I)/2,0,Math.PI/2),Z(C-I,U,E,Ne+U/2,(C+I)/2,0,Math.PI/2),Z(E-_,C-I,(_+E)/2,Ne+U,(C+I)/2,-Math.PI/2,0)],this.netBack=I,this.netShake=0,this.keeper=this.player({shirt:"#22b36b",sleeves:"#1a8d54",shorts:"#14161b",socks:"#22b36b"},.17,!0),this.keeper.position.copy(le((t.keeper.min+t.keeper.max)/2,t.keeper.y,Ne)),e.add(this.keeper);let Q=new In;[[-.5,-.5],[.5,-.5],[.5,.18],[.78,.05],[.95,.32],[.55,.62],[.2,.62],[0,.48],[-.2,.62],[-.55,.62],[-.95,.32],[-.78,.05],[-.5,.18]].forEach(([nt,ct],Yt)=>Q[Yt?"lineTo":"moveTo"](nt,ct));let it=[2,4,5,3,6,8,7,11,9,10];this.players=[],t.rows.forEach(nt=>nt.offs.forEach(()=>{let[ct,Yt]=Mn(128,128),Jt=Sn(ct),Qt=new ni(Q,{depth:.25,bevelEnabled:!1}),J=3.6/1.9,$=2.4;Qt.scale(J,$,1),Qt.translate(0,.5*$,-.12);let mt=Qt.attributes.position,Lt=Qt.attributes.uv;for(let Bt=0;Bt<mt.count;Bt++)Lt.setXY(Bt,mt.getX(Bt)/(1.9*J)+.5,mt.getY(Bt)/(1.12*$));let gt=new ut(Qt,new Vt({map:Jt,roughness:.5}));gt.position.copy(le(t.center,nt.y,Ne)),gt.castShadow=!0,e.add(gt),this.players.push({m:gt,y:Ne,up:Ne,c:ct,g:Yt,t:Jt,n:it[this.players.length]})})),this.paintPlayers()}paintPlayers(){let t=this.cfg.opp&&this.cfg.opp.kit?this.cfg.opp.kit:{shirt:"#e9edf5",sleeves:"#e9edf5",shorts:"#3a3a3a"},e=new Ft(t.shirt).getHSL({}).l>.6;for(let n of this.players||[]){let i=n.g;i.fillStyle=t.shirt,i.fillRect(0,0,128,128),i.fillStyle=t.sleeves||t.shirt,i.fillRect(0,0,18,128),i.fillRect(110,0,18,128),je(i,String(n.n),64,76,70,e?"#14161b":"#ffffff",{weight:900}),n.t.needsUpdate=!0}}setOpp(t){!t||t===this.cfg.opp||(this.cfg.opp=t,this.paintPlayers(),this.paintHexes(),this.drawLeds(0,0,!0),this.apron.key="",this.stadium.cfg.opp=t,this.stadium.setScore(0,0,"",!0))}drawMiniFront(t){let e=String(t);if(e===this.miniFront.key)return;this.miniFront.key=e;let n=this.miniFront.g;n.fillStyle="#04060c",n.fillRect(0,0,512,96),n.strokeStyle=this.Y,n.lineWidth=6,n.strokeRect(3,3,506,90),je(n,t?String(this.lb.goal||"BUT !").toUpperCase():String(this.cfg.short||"").toUpperCase(),256,50,64,t?"#fff3b0":this.Y,{weight:900,ls:8,glow:t?"#ffd23a":null,blur:18,max:470}),this.miniFront.tx.needsUpdate=!0}player(t,e,n=!1){let i=new Je,r=d=>new Vt({color:d,roughness:.55}),a=r(t.shirt),o=r(t.shorts),l=r(t.socks),c=r("#d9a07a"),h=r("#f2f2f2"),u=(d,f,p,y,g,m=i)=>{let S=new ut(d,f);return S.position.set(p,y,g),S.castShadow=!0,m.add(S),S};for(let d of[-1,1])u(new gn(.9,6,4,8),l,d*1.3,4,0).rotation.z=d*.25;u(new Ee(2.4,2.3,3,12),o,0,9.4,0),u(new gn(2.3,4.2,4,12),a,0,13.4,0).scale.set(1.05,1,.7),u(new sn(1.6,14,10),c,0,19,0),this.keeperArms=[];for(let d of[-1,1]){let f=new Je;f.position.set(d*2.8,16,0),i.add(f),u(new gn(.8,4.4,4,8),a,0,-2.6,0,f),u(new sn(1,10,8),n?h:c,0,-5.4,0,f),f.rotation.z=d*1,this.keeperArms.push(f)}return i.scale.setScalar(e),i}apronBuild(){let[t,e]=Mn(1024,Math.round(1024*(874-Tl)/Al));this.apron={c:t,g:e,tx:Sn(t),key:""};let n=new Vt({map:this.apron.tx,emissiveMap:this.apron.tx,emissive:16777215,emissiveIntensity:.5,roughness:.5,transparent:!0}),i=new ut(new Ke(Al*Kt,(874-Tl)*Kt),n);i.rotation.x=-Math.PI/2,i.position.set(bn(Al/2),.06,Ve((874+Tl)/2)),i.receiveShadow=!0,this.scene.add(i),this.drawApron({clock:"45:00",half:1,combo:0,comboMax:5,hexes:Array(10).fill(!0),ball:1,balls:3})}drawApron(t){let e=[t.clock,t.half,t.combo,t.hexes.join(""),t.ball,t.balls].join(":");if(e===this.apron.key)return;this.apron.key=e;let{c:n,g:i}=this.apron,r=n.width/Al,a=this.Y,o=this.lb,l=this.t.deco;i.setTransform(1,0,0,1,0,0),i.clearRect(0,0,n.width,n.height),i.setTransform(r,0,0,r,0,-Tl*r),Pl(i,[[0,708],[2,722],[30,744],[56,752],[162,810],[184.8,818],[207,810],[313,752],[339,744],[367,722],[366,874],[0,874]]);let c=i.createLinearGradient(0,740,0,874);c.addColorStop(0,"#0b1640"),c.addColorStop(1,"#050a1e"),i.fillStyle=c,i.fill(),i.strokeStyle=a,i.lineWidth=1.2,i.stroke();let[h,u,d]=l.clock;i.fillStyle="#2a3142",i.beginPath(),i.arc(h,u,d,0,Math.PI*2),i.fill(),i.fillStyle="#04060c",i.beginPath(),i.arc(h,u,d-3,0,Math.PI*2),i.fill(),Rd(i,t.clock,h,u-1,13,"#7ff0a0"),je(i,String((o.halves||[])[t.half-1]||"").toUpperCase(),h,u+12,5,"#9aa6c0",{ls:1}),Rl(i,this.cfg.logo,184.8,834,24),je(i,String(this.cfg.title||"").toUpperCase(),184.8,857,7,a,{weight:900,ls:1.5,max:110});let f=l.combo,p=t.comboMax||5,y=(f.x2-f.x1)/p;je(i,String(o.combo||"").toUpperCase(),f.x1,f.y1-5,6,a,{align:"left",weight:900,ls:2});for(let v=0;v<p;v++){let b=v<t.combo;i.fillStyle=b?a:"#1a2236",i.fillRect(f.x1+v*y+1,f.y1,y-2,f.y2-f.y1),b&&(i.fillStyle="rgba(255,255,255,.35)",i.fillRect(f.x1+v*y+1,f.y1,y-2,3))}let g=this.cfg.opp&&this.cfg.opp.kit?this.cfg.opp.kit:{shirt:"#e9edf5",sleeves:"#e9edf5"},m=l.jerseys,S=[[2,3],[0,1,2,3],[0,1,2,3]],T=[[0,1],[2,3,4,5],[6,7,8,9]];S.forEach((v,b)=>v.forEach((w,P)=>{let _=T[b][P],E=t.hexes[_];i.globalAlpha=E?1:.18,Cd(i,m.x+w*m.dx-6,m.y+b*m.dy,.24,g.shirt,g.sleeves||g.shirt,"","#000"),i.globalAlpha=1}));for(let v=0;v<t.balls;v++){let b=v>=t.ball-1;i.fillStyle=b?"#e9edf5":"#1a2236",i.beginPath(),i.arc(124+v*10,848,3.2,0,Math.PI*2),i.fill()}je(i,String(o.balls||"").toUpperCase(),119,838,5,"#9aa6c0",{align:"left",ls:1}),this.apron.tx.needsUpdate=!0}resize(t,e){this.w=t,this.h=e,this.renderer.setSize(t,e,!1),this.camera.aspect=t/e,this.fit(),this.composer?(this.composer.setSize(t,e),this.bloom.resolution.set(t,e)):this.makeComposer()}makeComposer(){let t=new Te(1,1,{type:Le,samples:4});this.composer=new Sl(this.renderer,t),this.composer.setSize(this.w||400,this.h||800),this.composer.addPass(new bl(this.scene,this.camera)),this.bloom=new Ys(new rt(this.w||400,this.h||800),.4,.5,.9),this.composer.addPass(this.bloom),this.composer.addPass(new El)}fit(){let t=this.camera,e=t.aspect,n=this.pitch??(e<.8?.98:e<1.3?.9:.74);t.fov=e<.8?39:36;let i=new D(0,0,Ve(874*.55)),r=[le(-6,-6,14),le(406,-6,14),le(-6,874),le(406,874),le(-6,874*.5),le(406,874*.5)],a=new D(0,Math.sin(n),Math.cos(n)),o=40,l=400;for(let c=0;c<30;c++){let h=(o+l)/2;t.position.copy(i).addScaledVector(a,h),t.lookAt(i),t.updateProjectionMatrix(),t.updateMatrixWorld(),r.every(d=>{let f=d.clone().project(t);return Math.abs(f.x)<.985&&Math.abs(f.y)<.985})?l=h:o=h}t.position.copy(i).addScaledVector(a,l),t.lookAt(i),t.updateProjectionMatrix(),this.camBase=t.position.clone(),this.camLook=i}setQuality(t){this.quality=t,this.renderer.shadowMap.enabled=t>=1,this.key.castShadow=t>=1,this.stadium.setQuality(t),t===0&&(this.renderer.setPixelRatio(1),this.resize(this.w,this.h)),this.scene.traverse(e=>{e.material&&[].concat(e.material).forEach(n=>{n.needsUpdate=!0})})}fx(t,e){t==="bumper"&&this.bumpers[e]?this.bumpers[e].flash=1:t==="sling"&&this.slings[e]?this.slings[e].flash=1:t==="kicker"?this.kickFlash=1:t==="nudge"?this.shake=.5:t==="tilt"?this.shake=1:t==="goal"?(this.strobe=2.4,this.netShake=1,this.leds[0].flash=2.4,this.goalShow=3,this.stadium.celebrate(1)):t==="awaygoal"?(this.leds[1].flash=1.6,this.stadium.hype=0):t==="save"?this.keeperDive=1:t==="win"||t==="cupwon"?this.stadium.celebrate(1):["jackpot","multiball","combo","skill","extra","super","locked"].includes(t)&&(this.strobe=1.6,(t==="multiball"||t==="jackpot")&&this.stadium.celebrate(.7))}frame(t,e,n,i,r=0){n=Math.max(0,n);let a=t.balls.filter(u=>u.state!=="gone");if(this.balls.forEach((u,d)=>{let f=a[d];u.visible=!!f,f&&u.position.set(bn(f.x),10*Kt+(f.h||0)*Kt+(f.state==="ramp"?.25:0),Ve(f.y))}),t.flippers.forEach((u,d)=>{this.flippers[d].rotation.y=-u.ang}),t.players&&this.players&&t.players.forEach((u,d)=>{let f=this.players[d],p=u.seg,y=p.on?f.up:f.up-2.8;f.y+=(y-f.y)*Math.min(1,n*14),f.m.position.set(bn((p.ax+p.bx)/2),f.y,Ve(p.ay))}),t.keeper&&this.keeper){let u=t.keeper;this.keeper.position.x=bn(u.x),this.keeperDive=Math.max(0,(this.keeperDive||0)-n*3),this.keeper.rotation.z=-Math.max(-.5,Math.min(.5,u.vx/160))-Math.sign(u.vx||1)*this.keeperDive*.6,this.keeperArms.forEach((d,f)=>{d.rotation.z=(f?1:-1)*(1+this.keeperDive*.9+Math.sin(i*5)*.08)})}this.nets&&(this.netShake=Math.max(0,this.netShake-n*1.5),this.nets[0].position.z=this.netBack-Math.abs(Math.sin(i*40)*.12*this.netShake)*3),this.goalShow=Math.max(0,(this.goalShow||0)-n),this.drawMiniFront(this.goalShow>0&&Math.sin(i*10)>0);let o=e&&e.lion;for(let u of this.bumpers){u.flash=Math.max(0,u.flash-n*5);let d=u.flash+(o?.4+.3*Math.sin(i*10):0);u.discMat.emissiveIntensity=.3+d*1.6,u.ringMat.color.copy(this.col.yellow).multiplyScalar(1.5+d*2.5),u.light.intensity=6+d*40}for(let u of this.slings)u.flash=Math.max(0,u.flash-n*6),u.mat.emissiveIntensity=.15+u.flash*2;this.kickFlash=Math.max(0,this.kickFlash-n*6),this.kickerMat.emissiveIntensity=.2+this.kickFlash*2,this.strobe=Math.max(0,(this.strobe||0)-n);let l=Math.sin(i*12)>0,c=this.strobe>0&&Math.sin(i*30)>0,h=(u,d)=>{let f=c?1.4:d==="blink"?l?1.4:.05:d?1.2:.02;u.mat.emissiveIntensity+=(f-u.mat.emissiveIntensity)*Math.min(1,n*20)};for(let u of this.leds)u.flash=Math.max(0,u.flash-n);if(this.L.small.forEach((u,d)=>h(u,Math.sin(i*3-d*.7)>.6)),e){this.L.hexes.forEach((u,d)=>h(u,e.hexes[d])),h(this.L.save,e.save?"blink":!1),this.paintMode(e.mode),h(this.L.mode,"blink"),h(this.L.modeLabel,!0),h(this.L.extra,!1),this.L.mults.forEach((u,d)=>h(u,d<5?e.mult>=5-d:e.hold)),this.L.locks.forEach((u,d)=>h(u,e.locked[d]?!0:e.lit[d]?"blink":!1)),this.L.lockArrows.forEach((u,d)=>h(u,e.lit[d]?"blink":!1)),this.lockBalls.forEach((u,d)=>{u.visible=e.locked[d]});for(let[u,d]of Object.entries(this.L.shots)){let f=e.skill===u||e.multiball&&(u==="r2"||u==="r3");d.forEach((p,y)=>h(p,f?"blink":Math.floor(i*4)%3===d.length-1-y))}this.L.inlanes.forEach(u=>h(u,e.attack?"blink":!1)),this.L.supers.forEach(u=>h(u,e.superLit?"blink":!1)),this.L.tackles.forEach(u=>h(u,!0)),this.drawLeds(e.home,e.away),this.drawApron(e),this.stadium.setScore(e.home,e.away,e.clock)}else this.drawLeds(0,0),this.L.hexes.forEach((u,d)=>h(u,Math.sin(i*2+d)>0));if(this.stadium.update(n,i),this.knob.position.z=this.plungerZ+r*3,this.shake=Math.max(0,this.shake-n*2.5),this.camBase){let u=this.shake*.6;this.camera.position.set(this.camBase.x+(Math.random()-.5)*u,this.camBase.y,this.camBase.z+(Math.random()-.5)*u),this.camera.lookAt(this.camLook)}}render(){this.quality>=2&&this.composer?this.composer.render():this.renderer.render(this.scene,this.camera)}};var Il=class{constructor(){this.on=!1,this.ctx=null,this.crowdOn=!1}enable(t){if(this.on=t,t&&!this.ctx&&this.init(),!this.ctx){this.on=!1;return}t&&this.ctx.state==="suspended"&&this.ctx.resume();let e=this.ctx.currentTime;this.master.gain.cancelScheduledValues(e),this.master.gain.setTargetAtTime(t?.7:0,e,.03)}init(){let t=window.AudioContext||window.webkitAudioContext;if(!t)return;let e=this.ctx=new t({latencyHint:"interactive"});this.master=e.createGain(),this.master.gain.value=0;let n=e.createDynamicsCompressor();n.threshold.value=-14,n.ratio.value=4,this.master.connect(n),n.connect(e.destination);let i=e.sampleRate*2;this.noiseBuf=e.createBuffer(1,i,e.sampleRate);let r=this.noiseBuf.getChannelData(0);for(let o=0;o<i;o++)r[o]=Math.random()*2-1;this.roll=this.loop(e.createBiquadFilter(),0),this.roll.f.type="lowpass",this.roll.f.frequency.value=300,this.roll.f.Q.value=.7,this.rampRoll=this.loop(e.createBiquadFilter(),0),this.rampRoll.f.type="bandpass",this.rampRoll.f.frequency.value=1400,this.rampRoll.f.Q.value=1.2,this.crowd=this.loop(e.createBiquadFilter(),0),this.crowd.f.type="bandpass",this.crowd.f.frequency.value=520,this.crowd.f.Q.value=.5;let a=e.createOscillator();this.crowdDepth=e.createGain(),a.frequency.value=.18,this.crowdDepth.gain.value=0,a.connect(this.crowdDepth),this.crowdDepth.connect(this.crowd.g.gain),a.start()}loop(t,e){let n=this.ctx,i=n.createBufferSource(),r=n.createGain();return i.buffer=this.noiseBuf,i.loop=!0,i.loopStart=Math.random(),r.gain.value=e,i.connect(t),t.connect(r),r.connect(this.master),i.start(),{s:i,f:t,g:r}}tone(t,e,n="square",i=.2,r=null,a=0){let o=this.ctx,l=o.currentTime+a,c=o.createOscillator(),h=o.createGain();c.type=n,c.frequency.setValueAtTime(t,l),r&&c.frequency.exponentialRampToValueAtTime(r,l+e),h.gain.setValueAtTime(1e-4,l),h.gain.exponentialRampToValueAtTime(i,l+.004),h.gain.exponentialRampToValueAtTime(1e-4,l+e),c.connect(h),h.connect(this.master),c.start(l),c.stop(l+e+.02)}noise(t,e=1200,n=1,i=.2,r=0,a="bandpass",o=null){let l=this.ctx,c=l.currentTime+r,h=l.createBufferSource(),u=l.createBiquadFilter(),d=l.createGain();h.buffer=this.noiseBuf,u.type=a,u.frequency.setValueAtTime(e,c),u.Q.value=n,o&&u.frequency.exponentialRampToValueAtTime(o,c+t),d.gain.setValueAtTime(i,c),d.gain.exponentialRampToValueAtTime(1e-4,c+t),h.connect(u),u.connect(d),d.connect(this.master),h.start(c,Math.random()),h.stop(c+t+.02)}arp(t,e=.09,n="square",i=.12){t.forEach((r,a)=>this.tone(r,e*1.8,n,i,null,a*e))}cheer(t=2.2,e=.32){let n=this.ctx,i=n.currentTime,r=n.createBufferSource(),a=n.createBiquadFilter(),o=n.createGain();r.buffer=this.noiseBuf,r.loop=!0,a.type="bandpass",a.Q.value=.6,a.frequency.setValueAtTime(600,i),a.frequency.linearRampToValueAtTime(1100,i+.4),o.gain.setValueAtTime(1e-4,i),o.gain.exponentialRampToValueAtTime(e,i+.25),o.gain.exponentialRampToValueAtTime(1e-4,i+t),r.connect(a),a.connect(o),o.connect(this.master),r.start(i),r.stop(i+t+.05)}whistle(t=.35,e=0){let n=this.ctx,i=n.currentTime+e,r=n.createOscillator(),a=n.createGain(),o=n.createOscillator(),l=n.createGain();r.type="sine",r.frequency.value=2900,o.frequency.value=34,l.gain.value=160,o.connect(l),l.connect(r.frequency),a.gain.setValueAtTime(1e-4,i),a.gain.exponentialRampToValueAtTime(.16,i+.02),a.gain.setValueAtTime(.16,i+t-.05),a.gain.exponentialRampToValueAtTime(1e-4,i+t),r.connect(a),a.connect(this.master),r.start(i),o.start(i),r.stop(i+t+.02),o.stop(i+t+.02)}groan(t=1.2,e=.2,n=900,i=300){let r=this.ctx,a=r.currentTime,o=r.createBufferSource(),l=r.createBiquadFilter(),c=r.createGain();o.buffer=this.noiseBuf,o.loop=!0,l.type="bandpass",l.Q.value=1.1,l.frequency.setValueAtTime(n,a),l.frequency.exponentialRampToValueAtTime(i,a+t),c.gain.setValueAtTime(1e-4,a),c.gain.exponentialRampToValueAtTime(e,a+.15),c.gain.exponentialRampToValueAtTime(1e-4,a+t),o.connect(l),l.connect(c),c.connect(this.master),o.start(a),o.stop(a+t+.05)}roar(){let t=this.ctx,e=t.currentTime,n=t.createBufferSource(),i=t.createBiquadFilter(),r=t.createGain(),a=t.createOscillator(),o=t.createGain();n.buffer=this.noiseBuf,n.loop=!0,i.type="lowpass",i.Q.value=6,i.frequency.setValueAtTime(220,e),i.frequency.linearRampToValueAtTime(900,e+.35),i.frequency.exponentialRampToValueAtTime(160,e+1.3),r.gain.setValueAtTime(.001,e),r.gain.exponentialRampToValueAtTime(.5,e+.25),r.gain.exponentialRampToValueAtTime(.001,e+1.4),a.type="sawtooth",a.frequency.setValueAtTime(70,e),a.frequency.linearRampToValueAtTime(110,e+.35),a.frequency.linearRampToValueAtTime(55,e+1.3),o.gain.setValueAtTime(.001,e),o.gain.exponentialRampToValueAtTime(.18,e+.25),o.gain.exponentialRampToValueAtTime(.001,e+1.4),n.connect(i),i.connect(r),r.connect(this.master),a.connect(o),o.connect(i),n.start(e),n.stop(e+1.5),a.start(e),a.stop(e+1.5),this.cheer(1.8,.2)}ambience(t){this.crowdOn=t,this.ctx&&(this.crowd.g.gain.setTargetAtTime(t?.035:0,this.ctx.currentTime,.6),this.crowdDepth.gain.setTargetAtTime(t?.012:0,this.ctx.currentTime,.6),t||(this.roll.g.gain.setTargetAtTime(0,this.ctx.currentTime,.05),this.rampRoll.g.gain.setTargetAtTime(0,this.ctx.currentTime,.05)))}update(t){if(!this.on||!this.ctx||!this.crowdOn)return;let e=0,n=0;for(let a of t)a.state==="play"&&a.y<830?e=Math.max(e,Math.hypot(a.vx,a.vy)):a.state==="ramp"&&(n=Math.max(n,Math.abs(a.rv)));let i=this.ctx.currentTime,r=Math.min(1,e/1800);this.roll.g.gain.setTargetAtTime(e>40?.05+.22*r:0,i,.05),this.roll.f.frequency.setTargetAtTime(180+700*r,i,.05),this.rampRoll.g.gain.setTargetAtTime(n>60?.05+.12*Math.min(1,n/1400):0,i,.05)}play(t,e){if(!(!this.on||!this.ctx))switch(t){case"flip":this.noise(.045,2400,.9,.22,0,"highpass"),this.tone(95,.09,"sine",.45,45),this.noise(.03,500,1.5,.15);break;case"flip-hit":this.tone(140,.05,"triangle",Math.min(.3,(e||0)/4e3));break;case"hit":(e||0)>200&&this.noise(.035,2600,3,Math.min(.16,(e||0)/5e3));break;case"bumper":this.tone(240,.11,"square",.16,110),this.noise(.05,3200,2,.16),this.tone(1320,.18,"sine",.07,null,.01);break;case"sling":case"kicker":this.noise(.04,1800,2,.2),this.tone(520,.07,"triangle",.16,260);break;case"lane":case"outlane":this.tone(660,.08,"square",.1,990);break;case"launch":this.noise(.08,300,1,.35,0,"lowpass"),this.noise(.3,400,1,.16,.02,"bandpass",2400);break;case"ramp-in":this.noise(.4,300,2,.12,0,"bandpass",1800);break;case"ramp":this.arp(e===1?[587,740,880,1175]:[523,659,784,1046],.07);break;case"ramp-fail":this.tone(300,.25,"triangle",.1,120);break;case"trap":case"hole":case"lock":this.tone(110,.15,"sine",.35),this.tone(880,.6,"sine",.08,null,.05);break;case"kickout":this.noise(.2,800,1,.25),this.arp([392,523,659],.06);break;case"lock-lit":this.arp([523,784,1046],.08,"square",.09);break;case"locked":this.tone(110,.15,"sine",.35),this.arp([392,523,659,784],.09,"sawtooth",.09);break;case"roar":this.roar();break;case"skill":case"extra":case"trick":this.arp([523,659,784,1046,1318],.07,"square",.1);break;case"jackpot":case"super":case"multiball":case"cupwon":this.arp([392,523,659,784,659,784,1046],.1,"sawtooth",.1),this.cheer(2.6,.36);break;case"drain":this.tone(440,.7,"triangle",.14,70),this.noise(.6,400,.8,.06,.1);break;case"tilt":this.tone(110,.8,"square",.18);break;case"nudge":this.tone(60,.08,"sine",.35),this.noise(.05,200,1,.2,0,"lowpass");break;case"bonus":this.arp([262,330,392],.12,"triangle",.1);break;case"start":this.whistle(.6),this.cheer(1.8,.2);break;case"goal":this.cheer(4,.5),this.arp([523,659,784,1046,784,1046,1318],.1,"sawtooth",.11),this.noise(.3,120,1,.4,0,"lowpass");break;case"awaygoal":this.groan(1.8,.2,700,220),this.tone(330,.8,"triangle",.1,160,.1);break;case"save":this.groan(.9,.14,1e3,500),this.tone(160,.08,"sine",.3);break;case"player":this.tone(130,.08,"sine",.35),this.noise(.06,2400,2,.12);break;case"tackle":this.noise(.12,900,1,.22),this.tone(90,.12,"sine",.4);break;case"eliminate":this.tone(1046,.06,"square",.1),this.tone(110,.06,"sine",.25);break;case"half":this.whistle(.3),this.whistle(.8,.4),this.cheer(1.4,.16);break;case"missed":this.groan(1.1,.16,1e3,380);break;case"tunnel":this.noise(.3,300,1,.16,0,"bandpass",1400);break;case"pass":this.tone(880,.07,"triangle",.1,1175);break;case"onetwo":this.arp([784,988,1175],.05,"triangle",.1);break;case"win":this.whistle(.3),this.whistle(.3,.4),this.whistle(.7,.8),this.cheer(3.5,.45),this.arp([523,659,784,1046,1318,1568],.12,"sawtooth",.1);break;case"draw":case"loss":this.whistle(.3),this.whistle(.3,.4),this.whistle(.7,.8),t==="loss"&&this.groan(2,.16,600,200);break;case"combo":this.arp([784,988,1175,1568],.06,"square",.09);break}}};var an=document.querySelector("[data-flipper]");an&&h_().catch(s=>{console.error(s),c_()});function c_(){an.querySelectorAll("[data-fl-ov]").forEach(s=>{s.hidden=s.dataset.flOv!=="nogl"})}function Js(s,t){try{if(t===void 0)return localStorage.getItem("fl:"+s);localStorage.setItem("fl:"+s,t)}catch{return null}return null}async function h_(){let s=JSON.parse(document.getElementById("fl-data").textContent),t=s.ui,e=z=>an.querySelector(z),n=z=>an.querySelectorAll("[data-fl-ov]").forEach(A=>{A.hidden=A.dataset.flOv!==z}),i=z=>String(Math.round(z)).replace(/\B(?=(\d{3})+(?!\d))/g,"\u202F"),r=z=>fetch(s.api,{method:"POST",headers:{"Content-Type":"application/json"},credentials:"same-origin",body:JSON.stringify({lang:s.lang,...z})}).then(A=>A.json()),a=document.createElement("canvas");if(!(a.getContext("webgl2")||a.getContext("webgl"))){n("nogl");return}await Promise.race([document.fonts.load('900 40px "Big Shoulders Display"'),new Promise(z=>setTimeout(z,2500))]);let o=await new Promise(z=>{let A=new Image;A.onload=()=>z(A),A.onerror=()=>z(null),A.src=s.logo}),l=yh(),c=new $s(l),h=new Il,u=s.opponents&&s.opponents.length?s.opponents:[{name:t.visitors,ini:t.visitors.slice(0,3).toUpperCase(),kit:null}],d=u[Math.floor(Math.random()*u.length)],f=new Cl(e("[data-fl-canvas]"),l,{colors:s.colors,kit:s.kit,opp:d,title:s.title,site:s.site,short:s.short,logo:o,labels:s.labels}),p=null,y=null,g=0,m=!1,S=e("[data-fl-stage]"),T=()=>{let z=S.getBoundingClientRect();f.resize(Math.max(1,z.width),Math.max(1,z.height))};new ResizeObserver(T).observe(S),T();let v={big:e("[data-fl-big]"),small:e("[data-fl-small]"),l:e("[data-fl-dl]"),r:e("[data-fl-dr]"),box:e("[data-fl-dmd]")},b="";function w(){if(!p||p.state==="attract"){v.big.textContent=s.title,v.l.textContent="",v.r.textContent="",P(Js("best")?t.record+" "+i(+Js("best")):t.insertCoin);return}if(p.state==="over"){let Et=p.players.reduce((R,x)=>Math.max(R,x.score),0);v.big.textContent=i(Et),v.box.dataset.kind="",v.l.textContent="",v.r.textContent="",P(t.over);return}let z=p.screen(),A=p.pl,Tt=p.match();v.big.textContent=z.big,v.box.dataset.kind=z.kind||"",v.l.textContent=(p.players.length>1?t.p+A.n+" \xB7 ":"")+s.short+" "+Tt.home+"\u2013"+Tt.away+" "+(Tt.opp.ini||Tt.opp.name||""),v.r.textContent=Tt.clock+" \xB7 "+t.ball+" "+Math.min(A.ball,p.cfg.balls)+"/"+p.cfg.balls+(is[A.mult]>1?" \xB7 \xD7"+is[A.mult]:""),P(z.small)}function P(z){z!==b&&(b=z,v.small.textContent=z,v.small.classList.remove("is-scroll"),requestAnimationFrame(()=>{let A=v.small.scrollWidth-v.small.parentElement.clientWidth;A>4&&(v.small.style.setProperty("--fl-scroll",-A-24+"px"),v.small.style.setProperty("--fl-dur",Math.max(4,A/40)+"s"),v.small.classList.add("is-scroll"))}))}let _=e("[data-fl-players]");function E(){if(!p||p.players.length<2){_.hidden=!0;return}_.hidden=!1,_.innerHTML=p.players.map((z,A)=>`<span class="${A===p.cur?"is-on":""}">${t.p}${z.n} <b>${i(z.score)}</b></span>`).join("")}function C(z){c=new $s(l);let A=u.slice().sort(()=>Math.random()-.5);p=new ma(c,l,{balls:3,text:s.text,facts:s.facts,opps:A},{fx:(Tt,Et)=>{if(Tt==="opp"){Et!==d&&(d=Et,f.setOpp(d));return}h.play(Tt,Et),f.fx(Tt,Et),Tt==="bumper"&&navigator.vibrate&&h.on&&navigator.vibrate(8)},over:Tt=>setTimeout(()=>mt(Tt),600)}),p.start(z),h.ambience(!0),h.play("start"),g=performance.now(),y=null,z===1&&r({action:"commencer"}).then(Tt=>{y=Tt.tok||null}).catch(()=>{}),n(""),m=!1}let I={L:new Set(["ShiftLeft","ArrowLeft","KeyZ","KeyA","KeyQ"]),R:new Set(["ShiftRight","ArrowRight","KeyM","Slash","KeyP"])},U=null,k=e("[data-fl-gauge]"),L=e("[data-fl-plunger]"),B=()=>U===null?0:Math.min(1,(performance.now()-U)/1100),W=()=>{p&&p.waiting&&!m&&U===null&&(U=performance.now())},X=()=>{if(U===null)return;let z=B();U=null,p&&p.launch(.55+z*.45)};window.addEventListener("keydown",z=>{!p||m||z.repeat||z.target.closest("input,textarea,select")||(I.L.has(z.code)?(p.input("L",!0),z.preventDefault()):I.R.has(z.code)?(p.input("R",!0),z.preventDefault()):["Space","Enter","ArrowDown"].includes(z.code)?(W(),z.preventDefault()):["ArrowUp","KeyN","KeyB"].includes(z.code)&&(p.nudge(0),z.preventDefault()))}),window.addEventListener("keyup",z=>{p&&(I.L.has(z.code)?p.input("L",!1):I.R.has(z.code)?p.input("R",!1):["Space","Enter","ArrowDown"].includes(z.code)&&X())});let st=new Map;for(let z of an.querySelectorAll("[data-fl-touch]")){let A=z.dataset.flTouch;z.addEventListener("pointerdown",Et=>{!p||m||Et.pointerType==="mouse"||(z.setPointerCapture(Et.pointerId),st.set(Et.pointerId,A),p.input(A,!0),z.classList.add("is-on"),Et.preventDefault())});let Tt=Et=>{st.has(Et.pointerId)&&(st.delete(Et.pointerId),[...st.values()].includes(A)||(p&&p.input(A,!1),z.classList.remove("is-on")))};z.addEventListener("pointerup",Tt),z.addEventListener("pointercancel",Tt),z.addEventListener("contextmenu",Et=>Et.preventDefault())}let Z={0:"L",2:"R"};an.addEventListener("mousedown",z=>{z.button===1&&z.preventDefault()}),an.addEventListener("wheel",z=>{z.target.closest(".fl__ov")||z.preventDefault()},{passive:!1}),S.addEventListener("mousedown",z=>{if(!(!p||m||z.target.closest("button,a,input,form,.fl__ov"))){if(z.button===1){z.preventDefault(),p.waiting?W():p.nudge(Math.random()<.5?-1:1);return}Z[z.button]&&(p.input(Z[z.button],!0),z.preventDefault())}}),window.addEventListener("mouseup",z=>{p&&(z.button===1?X():Z[z.button]&&p.input(Z[z.button],!1))}),S.addEventListener("contextmenu",z=>{z.target.closest(".fl__ov")||z.preventDefault()}),S.addEventListener("auxclick",z=>{z.button===1&&z.preventDefault()}),L.addEventListener("pointerdown",z=>{L.setPointerCapture(z.pointerId),W(),z.preventDefault(),z.stopPropagation()}),L.addEventListener("pointerup",z=>{X(),z.stopPropagation()}),L.addEventListener("pointercancel",()=>X()),e("[data-fl-nudge]").addEventListener("click",()=>p&&!m&&p.nudge(Math.random()<.5?-1:1));let Q=0,it=z=>{let A=z.acceleration||{};Math.hypot(A.x||0,A.y||0,A.z||0)>13&&performance.now()-Q>400&&p&&!m&&(Q=performance.now(),p.nudge(Math.sign(A.x||0)))};async function nt(){try{if(window.DeviceMotionEvent&&typeof DeviceMotionEvent.requestPermission=="function"&&await DeviceMotionEvent.requestPermission()!=="granted")return;window.addEventListener("devicemotion",it)}catch{}}let ct=e("[data-fl-sound]"),Yt=Js("son")!=="0",Jt=()=>{ct.setAttribute("aria-pressed",Yt?"true":"false"),ct.title=Yt?t.soundOff:t.soundOn,ct.querySelector("span").textContent=Yt?"\u{1F50A}":"\u{1F507}"},Qt=z=>{Yt=z,Js("son",z?"1":"0"),h.enable(z),z&&h.ambience(!!p&&p.state==="play"&&!m),Jt()};ct.addEventListener("click",()=>Qt(!Yt)),Jt(),e("[data-fl-full]").addEventListener("click",()=>{document.fullscreenElement?document.exitFullscreen():an.requestFullscreen&&an.requestFullscreen().catch(()=>{})}),e("[data-fl-pause]").addEventListener("click",()=>{p&&p.state!=="over"&&(m=!0,n("pause"),h.ambience(!1))}),an.querySelectorAll("[data-fl-go]").forEach(z=>z.addEventListener("click",()=>{let A=z.dataset.flGo;if(A==="resume"){m=!1,n(""),dt=performance.now(),h.ambience(!0);return}if(A==="quit"){p=null,n("start"),h.ambience(!1);return}if(A==="rules"){n("rules");return}if(A==="scores"){ot();return}if(A==="back"){n(p&&p.state==="over"?"over":p?"pause":"start");return}}));let J=1;an.querySelectorAll("[data-fl-n]").forEach(z=>z.addEventListener("click",()=>{J=+z.dataset.flN,an.querySelectorAll("[data-fl-n]").forEach(A=>A.setAttribute("aria-pressed",A===z?"true":"false"))})),an.querySelectorAll("[data-fl-play]").forEach(z=>z.addEventListener("click",()=>{Yt&&!h.on&&h.enable(!0),nt(),C(z.dataset.flPlay==="again"&&p?p.players.length:J)})),document.addEventListener("visibilitychange",()=>{document.hidden&&p&&p.state!=="over"&&p.state!=="attract"&&(m=!0,n("pause"),h.ambience(!1))});let $=e('[data-fl-ov="over"]');async function mt(z){n("over"),h.ambience(!1);let A=z.length===1,Tt=z[0],Et=+(Js("best")||0),R=$.querySelector("[data-fl-result]");if(R.hidden=!A,A){R.dataset.kind=Tt.cups?"win":"",R.querySelector("[data-fl-rkey]").textContent=Tt.cups?t.cups.replace("{n}",Tt.cups):t.cupRun;let q=(ft,K,et,xt,Ut,_t)=>`<li class="is-${_t}"><i>${j(s.text[ft]||"")}</i><span>${j(s.short)} ${et} \u2013 ${xt} ${j(K)}</span><em>${j(Ut)}</em></li>`,lt=Tt.current;R.querySelector("[data-fl-matches]").innerHTML=Tt.matches.map(ft=>q(ft.round,ft.opp,ft.home,ft.away,t[ft.result],ft.result)).join("")+q(lt.round,lt.opp,lt.home,lt.away,t.stopped,"stop")}A&&Tt.score>Et&&Js("best",String(Tt.score)),$.querySelector("[data-fl-final]").textContent=A?i(Tt.score):"",$.querySelector("[data-fl-multi]").innerHTML=A?"":z.slice().sort((q,lt)=>lt.score-q.score).map((q,lt)=>`<li><b>${lt+1}.</b> ${t.p}${q.n} <span>${i(q.score)}</span></li>`).join(""),$.querySelector("[data-fl-rec]").textContent=A&&Tt.score>Et&&Et>0?t.newRecord:A&&Et?t.record+" "+i(Math.max(Et,Tt.score)):"";let x=new Set(z.flatMap(q=>q.done));$.querySelector("[data-fl-missions]").innerHTML=Object.entries(t.missions).map(([q,lt])=>`<li class="${x.has(q)?"is-ok":""}"><i>${x.has(q)?"\u2713":"\xB7"}</i>${lt}</li>`).join("");let O=s.facts;$.querySelector("[data-fl-fact]").textContent=O.length?O[Math.floor(Math.random()*O.length)]:"";let G=$.querySelector("[data-fl-rank]");if(G.textContent="",$.querySelector("[data-fl-join]").hidden=!0,Bt=Tt.score,!A){G.textContent=t.multiNoRank;return}y&&(Lt={tok:y,score:Tt.score,missions:[...x],dur:Math.round((performance.now()-g)/1e3)},y=null,await gt())}let Lt=null;async function gt(){let z=$.querySelector("[data-fl-rank]");try{let A=await r({action:"score",...Lt});A.ranked?(z.textContent=A.label||"",Lt=null):A.error&&(z.textContent=A.error,Lt=null),$.querySelector("[data-fl-join]").hidden=!A.join,A.top&&ae($.querySelector("[data-fl-top]"),A.top,!0)}catch{}}let Bt=0;function ae(z,A,Tt){z.innerHTML=A.length?A.map(Et=>`<li class="${Tt&&Et.me?"is-me":""}"><b>${Et.rank}</b><span>${j(Et.pseudo)}</span><em>${i(Et.score)}</em></li>`).join(""):`<li class="is-empty">${t.empty}</li>`}function j(z){return String(z).replace(/[&<>"']/g,A=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[A])}async function ot(){n("scores");let z=e('[data-fl-ov="scores"]');z.querySelector("[data-fl-week]").innerHTML=`<li class="is-empty">${t.loading}</li>`;try{let A=await r({action:"classement"});ae(z.querySelector("[data-fl-week]"),A.week||[],!0),ae(z.querySelector("[data-fl-all]"),A.all||[],!0)}catch{z.querySelector("[data-fl-week]").innerHTML=`<li class="is-empty">${t.offline}</li>`}}e("[data-fl-share]").addEventListener("click",async()=>{let z=t.shareText.replace("{n}",i(Bt))+" "+s.url;try{navigator.share?await navigator.share({title:s.title,text:z,url:s.url}):(await navigator.clipboard.writeText(z),e("[data-fl-shared]").textContent=t.copied)}catch{}});let ht=e("[data-fl-join]");ht.addEventListener("submit",async z=>{z.preventDefault();let A=new FormData(ht),Tt=ht.querySelector("[data-fl-joinmsg]");Tt.textContent="\u2026";try{let Et=await r({action:"inscrire",email:A.get("email")||"",name:A.get("name")||""});Tt.textContent=Et.error||(Et.member?t.joined:t.mailed),Et.member&&Lt&&(ht.hidden=!0,await gt())}catch{Tt.textContent=t.offline}});let dt=performance.now(),pt=0,Ht=0,kt=()=>p&&p.waiting&&p.state==="play";function Gt(z){let A=Math.max(0,Math.min(.05,(z-dt)/1e3));dt=z,p&&!m&&p.update(A);let Tt=p?p.p:c;f.frame(Tt,p?p.lamps():null,A,z/1e3,B()),h.update(p&&!m?Tt.balls:[]),f.render(),w(),E();let Et=!!kt();L.hidden===Et&&(L.hidden=!Et),k.style.transform=`scaleY(${B()})`,p&&p.state==="play"&&!m&&z-g>2500&&(pt++,A>.028&&Ht++,pt>=120&&(Ht/pt>.5&&f.quality>0&&f.setQuality(f.quality-1),pt=0,Ht=0)),requestAnimationFrame(Gt)}n("start"),requestAnimationFrame(Gt),an.classList.add("is-ready")}})();
/*! Bundled license information:

three/build/three.core.js:
three/build/three.module.js:
  (**
   * @license
   * Copyright 2010-2026 Three.js Authors
   * SPDX-License-Identifier: MIT
   *)
*/
