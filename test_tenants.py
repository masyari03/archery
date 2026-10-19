import paramiko

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('100.119.52.45', username='arif', password='300311', timeout=8)

for club in ['annahl', 'focus']:
    url = f'http://127.0.0.1:3008/index.php?club={club}'
    cmd = f"curl -s '{url}'"
    _, stdout, _ = c.exec_command(cmd)
    html = stdout.read().decode('utf-8', errors='replace')
    titles = [line.strip() for line in html.splitlines() if '<title>' in line]
    print(f"Tenant [{club}]: {titles[0] if titles else 'NO TITLE FOUND'}")

c.close()
