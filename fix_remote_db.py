import os
import sys
import paramiko
import time

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')
if hasattr(sys.stderr, 'reconfigure'):
    sys.stderr.reconfigure(encoding='utf-8', errors='replace')

HOST = '100.119.52.45'
USER = 'arif'
PASS = '300311'

def inspect_db_pass():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASS, timeout=8)

    sftp = c.open_sftp()
    sftp.put(r'c:\laragon\www\target_archery\setup_db.php', '/home/arif/projects/target_archery/setup_db.php')
    sftp.close()

    # Run setup_db.php as arif
    stdin, stdout, stderr = c.exec_command('bash -c "cd /home/arif/projects/target_archery && DB_USER=arif DB_PASSWORD=codika php setup_db.php"', get_pty=True)
    out_setup = stdout.read().decode('utf-8', errors='replace')
    print("Run setup_db.php:\n" + out_setup)

    # Restart archery.service
    stdin, stdout, stderr = c.exec_command('sudo -S systemctl restart archery.service', get_pty=True)
    time.sleep(0.5)
    stdin.write(PASS + '\n')
    stdin.flush()
    stdout.read()

    time.sleep(2)
    # Test HTTP
    stdin, stdout, stderr = c.exec_command('curl -s -i http://127.0.0.1:3008/login.php | head -n 12', get_pty=True)
    print("HTTP Response:\n" + stdout.read().decode('utf-8', errors='replace'))

    c.close()

if __name__ == '__main__':
    inspect_db_pass()
