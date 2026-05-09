@echo off
setlocal EnableExtensions

set "XAMPP_DIR=C:\xampp"
set "APACHE_EXE=%XAMPP_DIR%\apache\bin\httpd.exe"
set "MYSQL_EXE=%XAMPP_DIR%\mysql\bin\mysqld.exe"
set "MYSQL_INI=%XAMPP_DIR%\mysql\bin\my.ini"

rem Build URL from current project folder name so launcher survives renames.
for %%I in ("%~dp0.") do set "PROJECT_NAME=%%~nxI"
for /f "usebackq delims=" %%E in (`powershell -NoProfile -Command "[uri]::EscapeDataString('%PROJECT_NAME%')"`) do set "PROJECT_PATH=%%E"
set "PROJECT_URL=http://localhost/%PROJECT_PATH%/"

if not exist "%APACHE_EXE%" goto ensure_xampp
if not exist "%MYSQL_EXE%" goto ensure_xampp
goto validate_install

:ensure_xampp
set "XAMPP_INSTALLER="
for /f "delims=" %%F in ('dir /b /a:-d "%~dp0xampp*-installer.exe" 2^>nul') do (
    if not defined XAMPP_INSTALLER set "XAMPP_INSTALLER=%~dp0%%F"
)
if not defined XAMPP_INSTALLER (
    for /f "delims=" %%F in ('dir /b /a:-d "%~dp0*xampp*.exe" 2^>nul') do (
        if not defined XAMPP_INSTALLER set "XAMPP_INSTALLER=%~dp0%%F"
    )
)

if not defined XAMPP_INSTALLER (
    echo [Launcher Error] XAMPP is missing and no installer was found in:
    echo %~dp0
    exit /b 1
)

echo [Launcher] XAMPP not found. Running installer:
echo %XAMPP_INSTALLER%

rem Try unattended install first; fall back to interactive installer if unsupported.
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$installer = '%XAMPP_INSTALLER%';" ^
    "$args = @('--mode','unattended','--unattendedmodeui','none','--prefix','%XAMPP_DIR%');" ^
    "$p = Start-Process -FilePath $installer -ArgumentList $args -Verb RunAs -PassThru -Wait;" ^
    "exit $p.ExitCode"
if errorlevel 1 (
    echo [Launcher] Unattended install failed or was canceled. Starting interactive installer...
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
        "$installer = '%XAMPP_INSTALLER%';" ^
        "$p = Start-Process -FilePath $installer -Verb RunAs -PassThru -Wait;" ^
        "exit $p.ExitCode"
    if errorlevel 1 (
        echo [Launcher Error] XAMPP installation failed or was canceled.
        exit /b 1
    )
)

:validate_install
if not exist "%APACHE_EXE%" (
    echo [Launcher Error] Apache executable not found after install: "%APACHE_EXE%"
    exit /b 1
)

if not exist "%MYSQL_EXE%" (
    echo [Launcher Error] MySQL executable not found after install: "%MYSQL_EXE%"
    exit /b 1
)

if not exist "%MYSQL_INI%" (
    echo [Launcher Error] MySQL config not found: "%MYSQL_INI%"
    exit /b 1
)

rem Start Apache only if not already running.
tasklist /FI "IMAGENAME eq httpd.exe" | find /I "httpd.exe" >nul
if errorlevel 1 (
    start "" "%APACHE_EXE%" >nul 2>&1
)

rem Start MySQL only if not already running.
tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul
if errorlevel 1 (
    start "" "%MYSQL_EXE%" --defaults-file="%MYSQL_INI%" --standalone >nul 2>&1
)

rem Wait up to ~15 seconds for ports to become ready.
set /a tries=0
:wait_loop
set /a tries+=1
set "apache_ready="
set "mysql_ready="

netstat -ano | findstr /R /C:":80 .*LISTENING" >nul && set "apache_ready=1"
netstat -ano | findstr /R /C:":3306 .*LISTENING" >nul && set "mysql_ready=1"

if defined apache_ready if defined mysql_ready goto open_app
if %tries% GEQ 15 goto timeout_error

timeout /t 1 /nobreak >nul
goto wait_loop

:open_app
start "" "%PROJECT_URL%"
exit /b 0

:timeout_error
echo [Launcher Error] Timed out waiting for Apache/MySQL to start.
echo Check whether ports 80 and 3306 are blocked or already in use.
exit /b 1
