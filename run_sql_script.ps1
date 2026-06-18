$mysqlPath = "C:\xampp\mysql\bin\mysql.exe"
$mysqlArgs = @(
    "-u", "root",
    "-p", "password",  # Remplacez par le mot de passe réel si nécessaire
    "-e", @"
    --init-command="SET FOREIGN_KEY_CHECKS=0;"
    SOURCE C:\xampp\htdocs\archive-test\create_db_user.sql;
    SET FOREIGN_KEY_CHECKS=1;
"@
)

Start-Process -FilePath $mysqlPath -ArgumentList $mysqlArgs -NoNewWindow -Wait