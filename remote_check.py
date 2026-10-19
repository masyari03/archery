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
    print("[REMOTE HTTP CHECK ON PORT 3008]:")
    for p in paths:
        stdin, stdout, stderr = c.exec_command(f'curl -s -o /dev/null -w "%{{http_code}}" http://127.0.0.1:3008{p}')
        code = stdout.read().decode('utf-8', errors='replace').strip()
        print(f"  {p} -> HTTP {code}")
        
    c.close()

if __name__ == '__main__':
    check_remote()
