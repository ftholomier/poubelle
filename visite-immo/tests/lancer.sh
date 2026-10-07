#!/usr/bin/env bash
# Lance l'appli (port 8099) et les services simulés (port 8098) avec des données de test jetables.
#   ./tests/lancer.sh            → démarre, affiche les adresses
#   ./tests/lancer.sh stop       → arrête
# Les données de test vont dans $TMPDIR/visite-immo-test (effacées à chaque lancement).
set -e
cd "$(dirname "$0")/.."
T="${TMPDIR:-/tmp}/visite-immo-test"
pkill -f "S 127.0.0.1:809[89]" 2>/dev/null || true
pkill -f "smtp-simul[e]" 2>/dev/null || true
sleep 0.5
[ "$1" = "stop" ] && exit 0
rm -rf "$T" && mkdir -p "$T/data" "$T/mails"
M=http://127.0.0.1:8098
export VI_DATA_DIR="$T/data" VI_SETTINGS="$T/settings.json"
export VI_API_ADRESSE=$M/adresse VI_API_CADASTRE=$M/cadastre VI_API_GEORISQUES=$M/georisques VI_API_DPE=$M/dpe \
       VI_API_DVF=$M/dvf VI_API_GELS=$M/gels VI_API_SIGNATURE=$M/signature \
       VI_API_FIRMA=$M/firma VI_API_BOLDSIGN=$M/boldsign
[ -n "$MOCK_GEMINI" ] && export VI_API_GEMINI=$M/gemini
python3 tests/smtp-simule.py "$T/mails" > "$T/smtp.log" 2>&1 &
php -d enable_post_data_reading=0 -S 127.0.0.1:8098 tests/services-simules.php > "$T/services.log" 2>&1 &
php -d upload_max_filesize=30M -d post_max_size=40M -S 127.0.0.1:8099 -t public > "$T/php.log" 2>&1 &
sleep 0.6
echo "Appli : http://127.0.0.1:8099  ·  services simulés : $M  ·  SMTP : 127.0.0.1:2525  ·  données : $T"
