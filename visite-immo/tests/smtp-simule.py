# Serveur SMTP simulé pour les tests : enregistre chaque e-mail reçu dans un fichier .eml.
# python3 tests/smtp-simule.py <dossier>   (port 2525, identifiant agence@test.fr / secret)
import asyncio, sys, time
from aiosmtpd.controller import Controller
from aiosmtpd.smtp import AuthResult, LoginPassword

OUT = sys.argv[1]

class H:
    async def handle_DATA(self, server, session, envelope):
        open(f"{OUT}/{time.time():.4f}.eml", "wb").write(envelope.content)
        open(f"{OUT}/destinataires.log", "a").write(f"{envelope.mail_from} -> {envelope.rcpt_tos}\n")
        return "250 OK"

def auth(server, session, envelope, mechanism, data):
    ok = isinstance(data, LoginPassword) and data.login == b"agence@test.fr" and data.password == b"secret"
    return AuthResult(success=ok)

c = Controller(H(), hostname="127.0.0.1", port=2525, authenticator=auth, auth_require_tls=False, auth_required=True)
c.start()
print("smtp simulé prêt", flush=True)
asyncio.get_event_loop().run_forever()
