"""Keep the configured FTP endpoint while verifying Hostinger's TLS identity."""

import ftplib
import ipaddress
import os
import re
import ssl
import sys


def normalize_hostname(value):
    hostname = value.strip().rstrip(".").lower()
    labels = hostname.split(".")
    if (
        len(hostname) > 253
        or len(labels) < 2
        or any(not re.fullmatch(r"[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?", label) for label in labels)
    ):
        raise ValueError("Informe somente o hostname FTPS, sem protocolo, porta ou caminho.")
    try:
        ipaddress.ip_address(hostname)
    except ValueError:
        return hostname
    raise ValueError("FTP_HOSTNAME deve ser um nome DNS coberto pelo certificado, não um IP.")


def resolve_target(server, hostname=""):
    server = server.strip()
    if not server:
        raise ValueError("Configure o secret FTP_SERVER.")

    try:
        target_ip = ipaddress.ip_address(server)
    except ValueError:
        if hostname.strip():
            raise ValueError("Use FTP_HOSTNAME somente quando FTP_SERVER for um IP.")
        return normalize_hostname(server), ""

    # The Hostinger FTP certificate includes hstgr.io. Only the temporary
    # runner maps this TLS name to the configured IP; no reverse DNS is needed.
    candidate = normalize_hostname(hostname.strip() or "hstgr.io")
    if candidate != "hstgr.io" and not re.fullmatch(r"[a-z0-9-]+\.hstgr\.io", candidate):
        raise ValueError("FTP_HOSTNAME deve corresponder ao domínio certificado da Hostinger.")
    return candidate, str(target_ip)


def verify_endpoint(hostname, address, *, ftp_factory=ftplib.FTP_TLS):
    # No login or uploads: verify CA chain, expiry and hostname before the
    # deployment action receives any FTP credentials.
    with ftp_factory(context=ssl.create_default_context()) as ftp:
        ftp.connect(address or hostname, 21, timeout=15)
        ftp.host = hostname
        ftp.auth()


def prepare_target(server, hostname, output_path, *, verify=verify_endpoint):
    hostname, address = resolve_target(server, hostname)
    verify(hostname, address)
    print(f"::add-mask::{hostname}")
    if address:
        print(f"::add-mask::{address}")
    with open(output_path, "a", encoding="utf-8") as output:
        output.write(f"hostname={hostname}\naddress={address}\n")


if __name__ == "__main__":
    try:
        prepare_target(
            os.environ.get("FTP_SERVER", ""),
            os.environ.get("FTP_HOSTNAME", ""),
            os.environ["GITHUB_OUTPUT"],
        )
        print("Destino FTPS confirmado com certificado e hostname válidos; TLS estrito mantido.")
    except (OSError, ValueError, ftplib.Error) as error:
        print(f"::error::Não foi possível validar o destino FTPS ({type(error).__name__}); upload bloqueado.", file=sys.stderr)
        sys.exit(1)
