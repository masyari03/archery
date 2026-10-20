import paramiko
import time

HOST = '100.119.52.45'
USER = 'arif'
PASS = '300311'

def check_remote():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASS, timeout=8)
    
    paths = [
        '/index.php',
        '/saas_marketing.php',
        '/sitemap.php',
        '/robots.txt',
        '/register_archer.php',
        '/login.php',
        '/admin_rbac.php',
        '/master_cms.php',
        '/app_dashboard.php',
        '/cms_admin.php',
        '/master_tenants.php',
        '/master_billing.php',
        '/settings.php',
        '/coach_dashboard.php',
        '/admin_distances.php',
        '/admin_approvals.php',
        '/bills.php',
        '/share_card.php',
        '/report_session.php'
    ]
    print("\n[LEVEL 1 SUBDOMAIN HOST ROUTING CHECK]:")
    hosts = [
        'archery.codika.my.id',
        'annahl.codika.my.id',
        'focus.codika.my.id',
        'randomclub.codika.my.id'
    ]
    for h in hosts:
        cmd = f"curl -s -H 'Host: {h}' http://127.0.0.1:3008/ | grep -i '<title>' | head -n 1"
        stdin, stdout, stderr = c.exec_command(cmd)
        title = stdout.read().decode('utf-8', errors='replace').strip()
        print(f"  Host: {h} -> {title}")

    print("\n[DIRECT /sitemap.xml CHECK VIA ROUTER]:")
    cmd_sm = "curl -s http://127.0.0.1:3008/sitemap.xml"
    stdin, stdout, stderr = c.exec_command(cmd_sm)
    sm = stdout.read().decode('utf-8', errors='replace')
    for line in sm.splitlines()[:10]:
        print(" ", line.strip())

    c.close()

if __name__ == '__main__':
    check_remote()
