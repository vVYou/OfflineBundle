ECHO Now we start Apache
ECHO Now we start MySQL
xampp_start.exe

ECHO Let's start chrome
GoogleChromePortable\GoogleChromePortable.exe --app="http://localhost/Claroline/web/app_offline.php"

ECHO Push any key to stop the program
pause

ECHO Shutting down Apache
ECHO Shutting down MySQL
xampp_stop.exe
