@echo off
title Arno D Clean - Auto Post Service
color 0A

set "PROJECT_DIR=%~dp0"

echo ========================================================
echo   AUTO POST SERVICE (ADC)
echo   Biarkan jendela ini terbuka (minimize saja).
echo   Bot akan mengecek jadwal setiap 1 menit.
echo   Direktori: %PROJECT_DIR%
echo ========================================================

:loop
echo.
echo [%DATE% %TIME%] Menjalankan tugas rutin...

:: 1. Auto Content (Cek Jadwal Posting)
c:\xampp\php\php.exe -f "%PROJECT_DIR%admin\api\cron_auto_content.php"

:: 2. Daily Report (Laporan Harian ke Telegram)
c:\xampp\php\php.exe -f "%PROJECT_DIR%admin\api\cron_daily_report.php"

:: 3. Auto Classify Leads (Filter Spam/Genuine)
c:\xampp\php\php.exe -f "%PROJECT_DIR%admin\api\cron_classify_leads.php"

:: Tunggu 60 detik sebelum cek lagi
timeout /t 60 /nobreak >nul
goto loop
