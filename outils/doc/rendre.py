from playwright.sync_api import sync_playwright
import pathlib
p = pathlib.Path("doc.html").resolve()
with sync_playwright() as pw:
    b = pw.chromium.launch(executable_path="/opt/pw-browsers/chromium")
    pg = b.new_page()
    pg.goto(p.as_uri()); pg.wait_for_timeout(600)
    pg.pdf(path="/home/user/poubelle/documentation-technique-angeot.pdf",
           format="A4", print_background=True,
           margin={"top":"18mm","bottom":"16mm","left":"17mm","right":"17mm"},
           display_header_footer=True,
           header_template="<div></div>",
           footer_template='<div style="width:100%;font:8pt Helvetica,sans-serif;color:#8a8a8a;padding:0 17mm;display:flex;justify-content:space-between"><span>Mairie d\'Angeot — documentation technique</span><span class="pageNumber"></span></div>')
    b.close()
print("done")
