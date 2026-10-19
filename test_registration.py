import paramiko

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('100.119.52.45', username='arif', password='300311', timeout=8)

cmd = (
    "curl -s -X POST 'http://127.0.0.1:3008/register_archer.php?club=annahl' "
    "-d 'student_name=Thoriq+Ziyad&student_nickname=Thoriq&gender=L&birth_date=2014-06-12"
    "&parent_name=Bapak+Santoso&parent_phone=081299998888&parent_email=santoso%40gmail.com"
    "&program_choice=Kelas+Junior+Foundation+(Pemula)&has_own_bow=1&notes=Sudah+pernah+belajar+dasar'"
)

_, stdout, _ = c.exec_command(cmd)
res = stdout.read().decode('utf-8', errors='replace')
print("REGISTRATION TEST RESULT:")
_, stdout, _ = c.exec_command("mysql -u arif -pcodika -e 'SELECT id, club_id, student_name, parent_name, parent_phone, status, created_at FROM archery.archery_cms_registrations;'")
print("\nDATABASE TABLE archery_cms_registrations:")
print(stdout.read().decode('utf-8', errors='replace'))

c.close()
