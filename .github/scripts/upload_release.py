"""Upload just two release files over verified FTPS, resuming interrupted data."""

import hashlib
import os
from pathlib import Path
import re
import subprocess
import sys
from urllib.parse import quote

from resolve_ftps_host import normalize_hostname


def file_digest(path):
    with path.open('rb') as source:
        return hashlib.file_digest(source, 'sha256').hexdigest()


def lftp_quote(value):
    if any(ord(char) < 32 or ord(char) == 127 for char in value):
        raise ValueError('Caracteres de controle não são permitidos nos parâmetros FTPS.')
    for char in ('\\', '"', '$', '`'):
        value = value.replace(char, '\\' + char)
    return '"' + value + '"'


def build_commands(hostname, username, directory, local_dir, release_sha, extractor_sha):
    hostname = normalize_hostname(hostname)
    if not username or any(ord(char) < 32 or ord(char) == 127 for char in username):
        raise ValueError('Configure um usuário FTP válido.')
    if not re.fullmatch(r'[A-Za-z0-9_./-]+', directory) or '..' in directory.split('/'):
        raise ValueError('Diretório remoto inválido.')
    if any(not re.fullmatch(r'[a-f0-9]{64}', value) for value in (release_sha, extractor_sha)):
        raise ValueError('Hash inválido do pacote.')

    # Content-addressed partials cannot accidentally resume a different build.
    # Their .ftp- prefix is denied by the application's existing .htaccess.
    archive = f'.ftp-release-{release_sha}.zip.part'
    extractor = f'.ftp-extractor-{extractor_sha}.php.part'
    return '\n'.join([
        'set cmd:fail-exit true',
        'set net:connection-limit 1',
        'set net:timeout 25',
        'set net:max-retries 3',
        'set net:persist-retries 0',
        'set net:reconnect-interval-base 2',
        'set net:reconnect-interval-max 8',
        'set net:reconnect-interval-multiplier 2',
        'set dns:order inet',
        'set ftp:passive-mode true',
        'set ftp:prefer-epsv false',
        'set ftp:ignore-pasv-address true',
        'set ftp:ssl-force true',
        'set ftp:ssl-protect-data true',
        'set ftp:ssl-protect-list true',
        'set ssl:verify-certificate true',
        'set ssl:check-hostname true',
        'set ssl:use-sni true',
        'set ssl:priority "NORMAL:-VERS-SSL3.0:-VERS-TLS1.0:-VERS-TLS1.1"',
        f'open --env-password {lftp_quote("ftp://" + quote(username, safe="") + "@" + hostname + ":21")}',
        f'cd {lftp_quote(directory)}',
        f'lcd {lftp_quote(str(local_dir))}',
        f'put -c "release.zip" -o "{archive}"',
        f'mv "{archive}" "release.zip"',
        f'put -c "deploy_extractor.php" -o "{extractor}"',
        f'mv "{extractor}" "deploy_extractor.php"',
        'bye',
        '',
    ])


def upload_release(hostname, username, password, directory, local_dir, output_path, *, run=subprocess.run):
    if not password:
        raise ValueError('Configure a senha FTP.')
    local_dir = Path(local_dir).resolve()
    release_sha = file_digest(local_dir / 'release.zip')
    extractor_sha = file_digest(local_dir / 'deploy_extractor.php')
    commands = build_commands(hostname, username, directory, local_dir, release_sha, extractor_sha)
    env = os.environ.copy()
    env['LFTP_PASSWORD'] = password
    # Password stays in the child environment, never in commands or arguments.
    run(['lftp', '--norc'], input=commands, text=True, env=env, check=True, timeout=480)
    with open(output_path, 'a', encoding='utf-8') as output:
        output.write(f'release_sha256={release_sha}\n')
    print('Pacote e extrator enviados; SHA-256 será validado antes da extração.')


if __name__ == '__main__':
    try:
        upload_release(
            os.environ.get('FTPS_HOSTNAME', ''),
            os.environ.get('FTP_USERNAME', ''),
            os.environ.get('FTP_PASSWORD', ''),
            'domains/duozen.com.br/public_html/',
            '.deploy_upload',
            os.environ['GITHUB_OUTPUT'],
        )
    except (OSError, ValueError, subprocess.SubprocessError) as error:
        print(f'::error::Upload FTPS não concluído ({type(error).__name__}); extração bloqueada.', file=sys.stderr)
        sys.exit(1)
