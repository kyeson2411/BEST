@echo off
rem Puts the presentation school back as it was: loads best-presentation.sql into the "sibol" database
rem (dates moved to today) and makes storage\uploads match the "uploads" folder next to this file.
rem Start Apache and MySQL in the XAMPP Control Panel first. Add /y to skip the question.
setlocal
set "HERE=%~dp0"
set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
set "PHP=C:\xampp\php\php.exe"
set "UPLOADS=%HERE%..\..\storage\uploads"

echo.
echo This replaces EVERYTHING in the "sibol" database and in storage\uploads
echo with the presentation school. Anything you made while practising is removed.
echo.
if /i not "%~1"=="/y" (
  choice /M "Continue"
  if errorlevel 2 exit /b 1
)

if not exist "%MYSQL%" (
  echo Could not find %MYSQL%. Is XAMPP installed in C:\xampp?
  pause
  exit /b 1
)

echo Loading the database...
"%MYSQL%" -u root --default-character-set=utf8mb4 < "%HERE%best-presentation.sql"
if errorlevel 1 (
  echo The database could not be loaded. Check that MySQL is running in the XAMPP Control Panel.
  pause
  exit /b 1
)

rem Newer versions of the app add columns to the database: bring it up to date.
echo Bringing the database up to date for this version of the app...
"%PHP%" "%HERE%..\..\bin\sibol.php" migrate
if errorlevel 1 (
  echo The database could not be brought up to date. In the project folder, run: C:\xampp\php\php.exe bin\sibol.php migrate
  pause
  exit /b 1
)

echo Copying the class files...
robocopy "%HERE%uploads" "%UPLOADS%" /MIR /NFL /NDL /NJH /NJS /NP >nul
if errorlevel 8 (
  echo The class files could not be copied into storage\uploads.
  pause
  exit /b 1
)

echo.
echo Done. Sign out in every browser tab, then sign in again (password: Polomolok-2026).
if /i not "%~1"=="/y" pause
exit /b 0
