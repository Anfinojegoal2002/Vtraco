@echo off
cd /d C:\xampp\htdocs\vtraco
C:\xampp\php\php.exe scripts\sync_etime_attendance.php >> storage\logs\etime-sync-output.log 2>&1
