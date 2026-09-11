# Первая установка на Debian / Plesk

Фактический домен: `mannavomhimmel.de`. На сервере пока работают только загрузчик `/upload/` и фоновый сборщик YouTube. Основное Laravel-приложение ещё не установлено. Выполнять команды в интерактивном SSH-терминале под root, по блокам. При ошибке не переходить к следующему шагу.

## 1. Проверить YouTube

```bash
cd /var/www/vhosts/mannavomhimmel.de/httpdocs
bash youtube/bin/plesk.sh status
du -sh ../private/manna-youtube
df -h .
tail -n 60 ../private/manna-youtube/collection.log
```

Это просмотр текущего отчёта, занятого и свободного места и последних строк журнала. `active: true` означает, что сборщик ещё держит блокировку архива. `counts` показывает результаты обработанных материалов текущего запуска, а не число всех файлов на диске. Для оценки полноты нужны также `inventory`, `state` и сообщения журнала. Статус не проверяет заново содержимое всех видео. Команда не останавливает сборщик.

## 2. Подготовить системные утилиты и код

```bash
apt-get update
apt-get install -y ca-certificates curl unzip git
cd /var/www/vhosts/mannavomhimmel.de/httpdocs
git pull --ff-only
```

PHP 8.4 и SQL используем существующие, из Plesk. Установщик проверяет нужные PHP-модули. Не нужно ставить параллельный системный PHP/MySQL или повторно устанавливать работающий YouTube runtime. Node 22 нужен сборщику YouTube; текущему Blade-приложению не требуется Node-сборка.

## 3. Создать базу в Plesk

В домене `mannavomhimmel.de` открыть **Datenbanken → Datenbank hinzufügen**. Создать отдельную базу MySQL/MariaDB (например, `atapin_media`) и отдельного пользователя с доступом к ней. Сохранить фактические имя базы, имя пользователя, пароль и адрес SQL-сервера. Если Plesk добавляет префикс к именам, использовать полные имена.

## 4. Установить приложение

```bash
cd /var/www/vhosts/mannavomhimmel.de/httpdocs
bash platform/bin/plesk.sh install
```

Скрипт использует `/opt/plesk/php/8.4/bin/php`, переключается с root на владельца `httpdocs`, устанавливает PHP-зависимости по `composer.lock`. Он использует Composer из Plesk или загружает локальный Composer с проверкой подписи установщика.

В интерактивных вопросах указать:

- SQL host: `127.0.0.1`, если база размещена локально; иначе адрес из Plesk.
- SQL port: `3306`, если Plesk не указывает другой.
- Database name / Database user / Database password: данные созданной базы.
- Website URL: `https://mannavomhimmel.de`.

Подключение к базе проверяется до сохранения конфигурации. `.env` и рабочие файлы располагаются вне публичной папки, в `private/atapin-platform`. После этого выполняются миграции, создаются роли и разрешения, строятся кэши. Пароль базы вводится в терминале, не в Git.

Создать владельца и проверить установку:

```bash
bash platform/bin/plesk.sh owner
bash platform/bin/plesk.sh check
```

`owner` спрашивает имя, email и пароль для кабинета. Минимальной длины и требований сложности нет. Этот аккаунт независим от общего пароля `/upload/`.

После успешной проверки установить фоновые службы, всё ещё под root:

```bash
bash platform/bin/plesk.sh services
```

Команда создаёт systemd worker и таймер планировщика. Оба должны показать `active`. Дополнительный cron в Plesk для того же планировщика не нужен.

## 5. Подключить домен

Только после успешных install/owner/check открыть **Plesk → Hosting Settings / Hosting-Einstellungen** для этого домена и установить **Document root / Dokumentenstamm: `httpdocs/platform/public`**. Оставить PHP 8.4, FPM application served by Apache. Этот путь сохраняет существующий Git checkout в `httpdocs`.

Проверить по HTTPS:

- `https://mannavomhimmel.de/login` — вход в кабинет новым аккаунтом.
- `https://mannavomhimmel.de/upload/` — прежний загрузчик.
- `https://mannavomhimmel.de/up` — HTTP-проверка доступности Laravel (не заменяет проверку SQL/storage).

Разворачивается существующая основа приложения, а не готовая главная из `01-start.png`. Архивы `private/manna-youtube` и `private/manna-intake` остаются на своих местах. Установщик платформы не запускает и не останавливает YouTube-сборщик.

После этого вновь выполнить `bash youtube/bin/plesk.sh status` из `httpdocs` и сравнить результат с первым шагом.

Официальное описание выбора публичной папки Laravel: https://docs.plesk.com/en-US/obsidian/administrator-guide/website-management/laravel-toolkit.80010/
