<style>
.tk-row{display:grid;grid-template-columns:minmax(0,1fr) 200px;gap:16px;align-items:start}
.tk-types{display:flex;flex-wrap:wrap;gap:8px}
.tk-type input{position:absolute;opacity:0}
.tk-type span{display:inline-block;padding:7px 12px;border:2px solid var(--navy);cursor:pointer;font-weight:700;font-size:14px}
.tk-type input:checked+span{background:var(--navy);color:var(--cream)}
.tk-type input:focus-visible+span{outline:3px solid var(--yellow)}
.tk-list{list-style:none;margin:0;padding:0}
.tk-list li{display:grid;grid-template-columns:64px minmax(0,1fr) auto;gap:14px;align-items:center;padding:12px 4px;border-bottom:1px solid rgba(0,0,0,.08)}
.tk-list li.is-new{background:rgba(255,205,0,.12)}
.tk-n{font-family:var(--display);font-weight:800;font-size:20px;color:var(--blue)}
.tk-t{font-weight:700}
.tk-t a{color:inherit}
.tk-meta{font-size:13px;color:var(--muted,#666)}
.tk-dot{display:inline-block;width:9px;height:9px;border-radius:50%;background:var(--yellow);margin-right:6px;vertical-align:middle;box-shadow:0 0 0 2px var(--navy)}
.tk-state{display:inline-block;padding:2px 8px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;border:2px solid currentColor}
.tk-state--nouveau{color:#b00}
.tk-state--pris{color:var(--blue)}
.tk-state--en-cours{color:#c76b00}
.tk-state--a-verifier{color:#7a3db8}
.tk-state--termine{color:#1f7a3a}
.tk-state--refuse{color:#777}
.tk-prio--urgente{color:#c76b00;font-weight:800}
.tk-prio--bloquante{color:#b00;font-weight:800}
.tk-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px;align-items:start}
.tk-body{white-space:pre-wrap;line-height:1.55}
.tk-prompt{white-space:pre-wrap;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;background:var(--navy);color:var(--cream);padding:14px 16px;border-left:6px solid var(--yellow);max-height:420px;overflow:auto}
.tk-com{border-left:4px solid var(--navy);padding:8px 12px;margin:0 0 10px;background:rgba(0,0,0,.03)}
.tk-com--admin{border-color:var(--yellow)}
.tk-com b{font-family:var(--display);text-transform:uppercase}
.tk-hist{list-style:none;margin:0;padding:0;font-size:13px}
.tk-hist li{padding:4px 0;border-bottom:1px solid rgba(0,0,0,.06)}
.tk-half{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;align-items:start;margin-top:16px}
.tk-new{position:sticky;top:120px;border-top:6px solid var(--yellow)}
.tk-half .tk-row{grid-template-columns:1fr}
.tk-pj{margin:4px 0 14px}
.tk-pj__zone{display:grid;grid-template-columns:auto minmax(0,1fr);gap:2px 10px;align-items:center;padding:12px 14px;border:2px dashed var(--navy);background:rgba(255,205,0,.08);cursor:pointer;font-size:14px}
.tk-pj__zone .xs{grid-column:2}
.tk-pj__zone input{position:absolute;width:1px;height:1px;opacity:0}
.tk-pj__zone:focus-within{outline:3px solid var(--yellow)}
.tk-pj.is-over .tk-pj__zone{background:rgba(255,205,0,.3);border-style:solid}
.tk-pj__ico{font-size:22px;grid-row:span 2}
.tk-pj--compact .tk-pj__zone{padding:8px 12px;font-size:13px}
.tk-pj__list{list-style:none;margin:8px 0 0;padding:0;display:flex;flex-wrap:wrap;gap:8px}
.tk-pj__list li{display:flex;align-items:center;gap:8px;padding:4px 6px 4px 4px;border:2px solid var(--navy);background:var(--cream,#fff);font-size:13px;max-width:100%}
.tk-pj__list img{width:44px;height:44px;object-fit:cover;display:block}
.tk-pj__list button{border:0;background:none;cursor:pointer;font-size:16px;line-height:1;padding:2px 4px}
.tk-pj__list .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px}
.tk-pj__err{color:#b00;font-weight:700;margin:6px 0 0}
.tk-files{list-style:none;margin:12px 0 0;padding:0;display:flex;flex-wrap:wrap;gap:10px}
.tk-file{display:flex;gap:8px;align-items:flex-start;max-width:100%}
.tk-file__thumb img{display:block;width:160px;max-height:120px;object-fit:cover;border:2px solid var(--navy)}
.tk-file__meta{display:flex;flex-direction:column;gap:2px;font-size:14px;min-width:0}
.tk-file__meta>a{overflow-wrap:anywhere;font-weight:700}
@media (max-width:1100px){.tk-half{grid-template-columns:1fr}.tk-new{position:static;order:-1}}
@media (max-width:900px){.tk-row,.tk-grid{grid-template-columns:1fr}.tk-list li{grid-template-columns:48px minmax(0,1fr)}.tk-list li>:last-child{grid-column:2}}
</style>
