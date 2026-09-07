@echo off
setlocal
php artisan optimize:clear
php artisan route:list --name=notifications.feed
endlocal
