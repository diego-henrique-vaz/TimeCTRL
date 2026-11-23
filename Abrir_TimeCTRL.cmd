@echo off
setlocal
cd /d "%~dp0"
set "TIMECTRL_PHP_EXE=%TIMECTRL_PHP%"
if not defined TIMECTRL_PHP_EXE if exist "C:\xampp\php\php.exe" set "TIMECTRL_PHP_EXE=C:\xampp\php\php.exe"
if not defined TIMECTRL_PHP_EXE set "TIMECTRL_PHP_EXE=php"
"%TIMECTRL_PHP_EXE%" -r "exit(PHP_VERSION_ID >= 80200 && extension_loaded('mbstring') && (extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql')) ? 0 : 1);"
if errorlevel 1 (
  echo O TimeCTRL precisa de PHP 8.2 ou superior, mbstring e pdo_sqlite ou pdo_mysql.
  echo Confira a instalacao do XAMPP ou a variavel TIMECTRL_PHP.
  pause
  exit /b 1
)

if not defined TIMECTRL_PORT set "TIMECTRL_PORT=8082"
curl.exe --noproxy "*" --silent --fail --max-time 2 "http://127.0.0.1:%TIMECTRL_PORT%/" | findstr /L /C:"TimeCTRL</title>" >nul
if not errorlevel 1 (
  start "" "http://127.0.0.1:%TIMECTRL_PORT%/"
  exit /b 0
)

if not exist "var\sessions" mkdir "var\sessions"
echo Iniciando TimeCTRL em http://127.0.0.1:%TIMECTRL_PORT%/
echo Mantenha esta janela aberta durante o uso. Pressione Ctrl+C para parar.
"%TIMECTRL_PHP_EXE%" -d "session.save_path=var/sessions" -S "127.0.0.1:%TIMECTRL_PORT%" -t public
if errorlevel 1 pause
