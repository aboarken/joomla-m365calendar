#!/usr/bin/env bash
#
# Creates the key pair for the Microsoft 365 Calendar extension's OWN Entra
# app registration. Part of the source repository only; never installed.
#
#   bin/generate-cert.sh [output-dir] [days]
#
# Output (default dir: ./m365calendar-cert):
#   m365calendar.key  private key (PEM, unencrypted, mode 600) - keep OUTSIDE the web root
#   m365calendar.pem  certificate (PEM)                        - path for "Certificate path"
#   m365calendar.cer  certificate (DER)                        - upload to Entra (Certificates & secrets)
#
# Copyright (C) 2026 M365 Calendar contributors
# License GNU General Public License version 2 or later; see LICENSE.txt

set -euo pipefail

OUT_DIR="${1:-./m365calendar-cert}"
DAYS="${2:-730}"
CN="${M365CALENDAR_CERT_CN:-joomla-m365calendar}"

command -v openssl >/dev/null 2>&1 || { echo "openssl is required" >&2; exit 1; }

if [[ -e "$OUT_DIR/m365calendar.key" ]]; then
  echo "Refusing to overwrite $OUT_DIR/m365calendar.key" >&2
  exit 1
fi

umask 077
mkdir -p "$OUT_DIR"

openssl req -x509 -newkey rsa:3072 -sha256 -nodes \
  -keyout "$OUT_DIR/m365calendar.key" \
  -out "$OUT_DIR/m365calendar.pem" \
  -days "$DAYS" \
  -subj "/CN=${CN}" >/dev/null 2>&1

openssl x509 -in "$OUT_DIR/m365calendar.pem" -outform DER -out "$OUT_DIR/m365calendar.cer"
chmod 600 "$OUT_DIR/m365calendar.key"
chmod 644 "$OUT_DIR/m365calendar.pem" "$OUT_DIR/m365calendar.cer"

SHA256_HEX="$(openssl x509 -in "$OUT_DIR/m365calendar.pem" -noout -fingerprint -sha256 | cut -d= -f2 | tr -d ':')"
SHA1_HEX="$(openssl x509 -in "$OUT_DIR/m365calendar.pem" -noout -fingerprint -sha1 | cut -d= -f2 | tr -d ':')"
X5T_S256="$(openssl x509 -in "$OUT_DIR/m365calendar.pem" -outform DER | openssl dgst -sha256 -binary | openssl base64 -A | tr '+/' '-_' | tr -d '=')"
EXPIRES="$(openssl x509 -in "$OUT_DIR/m365calendar.pem" -noout -enddate | cut -d= -f2)"

cat <<INFO

Created in $OUT_DIR:
  m365calendar.key  private key   -> move outside the web root, readable only by the web server user
  m365calendar.pem  certificate   -> its path goes into "Certificate path"
  m365calendar.cer  certificate   -> upload to Entra: App registrations > (this app) > Certificates & secrets

SHA-256 thumbprint : $SHA256_HEX
SHA-1 thumbprint   : $SHA1_HEX   (the "Thumbprint" column in Entra)
x5t#S256 (JWT)     : $X5T_S256
Expires            : $EXPIRES

Use this certificate ONLY for the Microsoft 365 Calendar extension.
INFO
