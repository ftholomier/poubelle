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
@media (max-width:900px){.tk-row,.tk-grid{grid-template-columns:1fr}.tk-list li{grid-template-columns:48px minmax(0,1fr)}.tk-list li>:last-child{grid-column:2}}
</style>
