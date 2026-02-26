@echo off
setlocal

set "PROJECT_URL=http://localhost/qrcode%%20+%%20facial%%20recognition%%20attendance%%20system/"
set "XAMPP_DIR=C:\xampp"
set "APACHE_EXE=%XAMPP_DIR%\apache\bin\httpd.exe"
set "MYSQL_EXE=%XAMPP_DIR%\mysql\bin\mysqld.exe"
set "MYSQL_INI=%XAMPP_DIR%\mysql\bin\my.ini"

if not exist "%APACHE_EXE%" (
    exit /b 1
)

if not exist "%MYSQL_EXE%" (
    exit /b 1
)

rem Start Apache in background. If already running, this exits harmlessly.
start "" /b "%APACHE_EXE%" >nul 2>&1

rem Start MySQL in background using XAMPP my.ini.
start "" /b "%MYSQL_EXE%" --defaults-file="%MYSQL_INI%" --standalone >nul 2>&1

timeout /t 2 /nobreak >nul

start "" "%PROJECT_URL%"
exit /b 0
