# Повторяющиеся действия без Codex

Запускать из корня репозитория в PowerShell. Скрипт не делает commit, push, удаление файлов или изменение сервера.

```powershell
.\tools\manna.ps1 status
```

Показывает незавершённые изменения в Git.

```powershell
.\tools\manna.ps1 ui-assets
```

Заново экспортирует отдельные PNG из утверждённых UI-макетов по `UI/assets/manifest.json`. Это подходит после изменения координат в манифесте или добавления нового элемента.

```powershell
.\tools\manna.ps1 ui-assets-check
```

Выполняет экспорт и проверяет, что отчёт содержит отдельные PNG.

Если PowerShell запрещает выполнение локального скрипта, запускать разово без изменения системной политики:

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\manna.ps1 ui-assets-check
```

Серверные процессы не запускаются с этого компьютера. На сервере остаются собственные короткие команды:

```bash
# YouTube: фоновый сбор или его состояние
cd /var/www/vhosts/mannavomhimmel.de/httpdocs
bash youtube/bin/plesk.sh start
bash youtube/bin/plesk.sh status

# Платформа: обновление из Git
cd /var/www/vhosts/mannavomhimmel.de/httpdocs
git pull --ff-only
/opt/plesk/php/8.4/bin/php platform/artisan view:clear
```
