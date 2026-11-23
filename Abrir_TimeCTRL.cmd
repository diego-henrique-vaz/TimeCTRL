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
  if not defined TIMECTRL_NO_BROWSER start "" "http://127.0.0.1:%TIMECTRL_PORT%/"
  exit /b 0
)

set "TIMECTRL_SESSION_DIR=%CD%\var\sessions"
if not exist "%TIMECTRL_SESSION_DIR%" mkdir "%TIMECTRL_SESSION_DIR%"
if not exist "%TIMECTRL_SESSION_DIR%" (
  echo Nao foi possivel criar a pasta de sessoes. Confira a permissao da pasta TimeCTRL.
  pause
  exit /b 1
)
echo Iniciando TimeCTRL em http://127.0.0.1:%TIMECTRL_PORT%/
echo Mantenha esta janela aberta durante o uso. Pressione Ctrl+C para parar.
if not defined TIMECTRL_NO_BROWSER start "" /B powershell.exe -NoProfile -Command "$url = 'http://127.0.0.1:' + $env:TIMECTRL_PORT + '/'; for ($i = 0; $i -lt 25; $i++) { $page = curl.exe --noproxy '*' --silent --fail --max-time 1 $url; if ($page -match 'TimeCTRL</title>') { Start-Process $url; exit }; Start-Sleep -Milliseconds 300 }"
"%TIMECTRL_PHP_EXE%" -d "session.save_path=%TIMECTRL_SESSION_DIR%" -S "127.0.0.1:%TIMECTRL_PORT%" -t public
if errorlevel 1 pause
