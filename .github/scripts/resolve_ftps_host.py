"""Resolve Hostinger IPs to a certificate hostname without disabling TLS checks."""

import ipaddress
import os
import re
import socket
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


def resolve_host(server, hostname="", *, reverse=socket.gethostbyaddr, lookup=socket.getaddrinfo):
    server = server.strip()
    if not server:
        raise ValueError("Configure o secret FTP_SERVER.")

    try:
        target_ip = ipaddress.ip_address(server)
    except ValueError:
        if hostname.strip():
            raise ValueError("Use FTP_HOSTNAME somente quando FTP_SERVER for um IP.")
        return normalize_hostname(server)

    if hostname.strip():
        candidate = normalize_hostname(hostname)
    else:
        try:
            candidate = normalize_hostname(reverse(server)[0])
        except (OSError, ValueError) as error:
            raise ValueError("Sem hostname reverso válido; configure a variável FTP_HOSTNAME com o hostname FTPS da Hostinger.") from error
        # The observed Hostinger certificate covers *.hstgr.io. Do not trust an arbitrary PTR.
        if not re.fullmatch(r"[a-z0-9-]+\.hstgr\.io", candidate):
            raise ValueError("O DNS reverso não corresponde à Hostinger; confirme o hostname FTPS e configure FTP_HOSTNAME.")

    addresses = lookup(candidate, 21, type=socket.SOCK_STREAM)
    if not any(ipaddress.ip_address(address[4][0]) == target_ip for address in addresses):
        raise ValueError("O hostname FTPS não aponta para o IP de FTP_SERVER; confira a configuração na Hostinger.")
    return candidate


if __name__ == "__main__":
    try:
        hostname = resolve_host(os.environ.get("FTP_SERVER", ""), os.environ.get("FTP_HOSTNAME", ""))
        with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as output:
            output.write(f"hostname={hostname}\n")
        print("Hostname FTPS resolvido; o upload continuará com validação TLS estrita.")
    except (OSError, ValueError) as error:
        print(f"::error::{error}", file=sys.stderr)
        sys.exit(1)
