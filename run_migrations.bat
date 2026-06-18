@echo off
"C:\xampp\mysql\bin\mysql" -u archive_user -pSecurePassword123! db_archive -e "SET FOREIGN_KEY_CHECKS=0;"
"C:\xampp\mysql\bin\mysql" -u archive_user -pSecurePassword123! db_archive -e "USE db_archive;"
"C:\xampp\mysql\bin\mysql" -u archive_user -pSecurePassword123! db_archive < run_migrations.sql
"C:\xampp\mysql\bin\mysql" -u archive_user -pSecurePassword123! db_archive -e "SET FOREIGN_KEY_CHECKS=1;"