# c:\laragon\www\target_archery\deploy_target_archery.py
# Script Deployment Otomatis ke Laptopserver & Setup Cloudflare Tunnel

import os
import sys
import time
import paramiko

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')
if hasattr(sys.stderr, 'reconfigure'):
    sys.stderr.reconfigure(encoding='utf-8', errors='replace')

HOST = '100.119.52.45'
USER = 'arif'
PASS = '300311'
LOCAL_DIR = r'c:\laragon\www\target_archery'
REMOTE_DIR = '/home/arif/projects/target_archery'
SERVICE_NAME = 'archery'
PORT = 3008
HOSTNAME = 'archery.codika.my.id'

IGNORE_DIRS = {'.git', 'node_modules', '.vscode', 'scratch', '__pycache__'}
IGNORE_EXTS = {'.log', '.tmp'}

def get_client():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASS, timeout=10)
    return c

def run_cmd(c, cmd, use_sudo=False):
    print(f"\n[EXEC] {cmd}")
    stdin, stdout, stderr = c.exec_command(cmd, get_pty=True)
    if use_sudo:
        time.sleep(0.5)
        stdin.write(PASS + '\n')
        stdin.flush()
    stdin.channel.shutdown_write()
    
    out = ''
    while True:
        try:
            line = stdout.readline()
        except Exception:
            break
        if not line:
            break
        try:
            sys.stdout.write(line)
            sys.stdout.flush()
        except Exception:
            pass
        out += line
        
    return out

def sync_files(sftp, local_base, remote_base):
    print(f"\n[SYNC] Mengunggah file ke laptopserver ({remote_base})...")

    def ensure_remote_dir(path):
        parts = path.strip('/').split('/')
        cur = ''
        for p in parts:
            cur += '/' + p
            try:
                sftp.stat(cur)
            except IOError:
                try:
                    sftp.mkdir(cur)
                except Exception:
                    pass

    ensure_remote_dir(remote_base)

    count = 0
    for root, dirs, files in os.walk(local_base):
        dirs[:] = [d for d in dirs if d not in IGNORE_DIRS]
        rel = os.path.relpath(root, local_base)
        remote_cur = remote_base if rel == '.' else f"{remote_base}/{rel.replace(os.sep, '/')}"
        ensure_remote_dir(remote_cur)

        for f in files:
            ext = os.path.splitext(f)[1].lower()
            if ext in IGNORE_EXTS or f.endswith('.pyc'):
                continue
            local_file = os.path.join(root, f)
            remote_file = f"{remote_cur}/{f}"
            sftp.put(local_file, remote_file)
            count += 1

    print(f"[SYNC] Selesai mengunggah {count} file.")

def setup_remote():
    c = get_client()
    sftp = c.open_sftp()

    # 1. Sync code
    sync_files(sftp, LOCAL_DIR, REMOTE_DIR)

    # 2. Setup MySQL database archery on laptopserver
    print("\n[DB] Setup Database archery di laptopserver...")
    run_cmd(c, 'sudo -S mysql -e "CREATE DATABASE IF NOT EXISTS archery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"', use_sudo=True)
    
    # Jalankan migration script
    run_cmd(c, f'cd {REMOTE_DIR} && DB_USER=arif DB_PASSWORD=codika php setup_db.php')

    # 3. Buat Systemd Service
    print("\n[SYSTEMD] Membuat archery.service...")
    service_content = f"""[Unit]
Description=Target Archery Scoring & Monitoring System
After=network.target

[Service]
Type=simple
User={USER}
WorkingDirectory={REMOTE_DIR}
Environment=DB_HOST=127.0.0.1
Environment=DB_PORT=3306
Environment=DB_NAME=archery
Environment=DB_USER=arif
Environment=DB_PASSWORD=codika
ExecStart=/usr/bin/php -S 127.0.0.1:{PORT} -t {REMOTE_DIR}
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
"""
    temp_service = f'/home/{USER}/archery.service'
    with sftp.open(temp_service, 'w') as sf:
        sf.write(service_content)

    run_cmd(c, f'sudo -S mv {temp_service} /etc/systemd/system/archery.service', use_sudo=True)
    run_cmd(c, 'sudo -S systemctl daemon-reload', use_sudo=True)
    run_cmd(c, 'sudo -S systemctl enable archery.service', use_sudo=True)
    run_cmd(c, 'sudo -S systemctl restart archery.service', use_sudo=True)
    time.sleep(2)
    run_cmd(c, 'systemctl status archery.service --no-pager')

    # 4. Update Cloudflare Tunnel Config di Laptopserver
    print("\n[CLOUDFLARE] Memeriksa & Mengupdate Ingress Cloudflare Tunnel...")
    check_tunnel = run_cmd(c, 'grep -q "archery.codika.id" ~/.cloudflared/config.yml 2>/dev/null && echo "FOUND" || echo "NOT_FOUND"')
    
    # Python script di remote untuk menyusun ingress archery di atas *.codika.id
    update_cf_script = f"""python3 -c "
path = '/home/{USER}/.cloudflared/config.yml'
with open(path, 'r') as f:
    content = f.read()

# Hapus entri archery lama jika ada
import re
content = re.sub(r'\\s*# Target Archery[^\\n]*\\n\\s*- hostname: archery\\.codika\\.my\\.id\\n\\s*service: http://localhost:3008\\n?', '', content)

archery_ingress = '''  # Target Archery Scoring & Multi-Tenant SaaS (Above *.codika.id)
  - hostname: archery.codika.id
    service: http://localhost:{PORT}

  - hostname: \\"*.archery.codika.id\\"
    service: http://localhost:{PORT}

  - hostname: archery.codika.my.id
    service: http://localhost:{PORT}

  - hostname: \\"*.archery.codika.my.id\\"
    service: http://localhost:{PORT}

'''

if 'archery.codika.id' not in content:
    target_marker = '- hostname: \\"*.codika.id\\"'
    if target_marker in content:
        content = content.replace(target_marker, archery_ingress + '  ' + target_marker)
    else:
        # Fallback di atas catch-all
        fallback_marker = '- service: http://localhost:3002'
        content = content.replace(fallback_marker, archery_ingress + '  ' + fallback_marker)
    
    with open(path, 'w') as f:
        f.write(content)
    print('Ingress Archery Multi-Tenant berhasil diperbarui!')
else:
    print('Ingress Archery Multi-Tenant sudah lengkap terpasang.')
" """
    run_cmd(c, update_cf_script)
    run_cmd(c, f'sudo -S cp /home/{USER}/.cloudflared/config.yml /etc/cloudflared/config.yml 2>/dev/null', use_sudo=True)
    run_cmd(c, 'sudo -S systemctl restart cloudflared', use_sudo=True)
    print("[CLOUDFLARE] Cloudflared service berhasil di-restart.")

    # 5. Uji HTTP Respons di Laptopserver
    print(f"\n[TEST] Menguji respons server lokal di http://127.0.0.1:{PORT}/login.php...")
    test_res = run_cmd(c, f'curl -s -o /dev/null -w "%{{http_code}}" http://127.0.0.1:{PORT}/login.php')
    print(f"[TEST] HTTP Response Status Code: {test_res.strip()}")

    sftp.close()
    c.close()
    print("\n🎉 DEPLOYMENT KE LAPTOPSERVER & CLOUDFLARE SELESAI DENGAN SUKSES!")

if __name__ == '__main__':
    setup_remote()
