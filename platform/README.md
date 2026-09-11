# Atapin Media — основное приложение

Laravel 13 / PHP 8.4 / MySQL или MariaDB. Интерфейс Blade, локальные CSS/JS/fonts, сборка Node для этого этапа не требуется. Node 22 из Plesk остаётся для YouTube.

## Первая установка

Для текущего сервера, где пока работают только загрузчик и YouTube-сборщик: [пошаговая первая установка с проверкой архива и зависимостей](../docs/PLESK-FIRST-INSTALL.md).

В Plesk для домена создайте отдельную SQL-базу и пользователя с доступом к ней. Имя базы/пользователя и пароль вводятся в терминале установщика; не присылайте пароль в чат и не добавляйте его в Git.

Из root или пользователя подписки:

```bash
cd /var/www/vhosts/mannavomhimmel.de/httpdocs
git pull --ff-only
bash platform/bin/plesk.sh install
bash platform/bin/plesk.sh owner
bash platform/bin/plesk.sh check
```

Установщик использует PHP `/opt/plesk/php/8.4/bin/php`, переключается с root на владельца httpdocs, сохраняет `.env` и runtime в `private/atapin-platform`. Composer использует зафиксированный lock. Повторный install не меняет существующую SQL-конфигурацию. `owner` спрашивает имя, email и пароль; существующий аккаунт не перезаписывает. Короткие пароли допустимы, минимальной длины и требований сложности нет.

После успешной проверки в **Plesk → Hosting Settings** измените **Document root** на `httpdocs/platform/public`. PHP оставьте 8.4 FPM Apache. Публичный сайт: `/`, кабинет: `/login`, загрузчик: `/upload/`. Исходники, vendor и `.env` не должны раздаваться nginx/Apache. Не создавайте symlink public/storage для приватных оригиналов.

Для Apache нужна стандартная обработка `.htaccess`; при nginx-only потребуется отдельная конфигурация Laravel. Текущий сервер использует Apache FPM.

## Обновление

```bash
cd /var/www/vhosts/mannavomhimmel.de/httpdocs
git pull --ff-only
bash platform/bin/plesk.sh update
bash platform/bin/plesk.sh check
```

Перед изменениями базы используйте backup базы через Plesk. Код не откатывает migrations автоматически. При неуспешной миграции не выполняйте migrate:fresh — это удалит данные.

## Queue и scheduler

На этом сервере используются **Geplante Aufgaben** в Plesk: две задачи типа **PHP-Skript ausführen**, версия PHP 8.4, путь `httpdocs/platform/bin/cron.php`, аргументы `schedule` и `queue` соответственно. Обе выполняются каждую минуту (`* * * * *`) от пользователя сайта. [Точные поля формы](../docs/PLESK-TASKS.md).

PHP-скрипт сам удерживает блокировку повторного запуска. Таймаут worker 3600 секунд меньше `retry_after=3660`. Команду `plesk.sh services` для этого способа не выполнять.

YouTube/intake архивы остаются отдельно; Import Center регистрирует их для работы редакции без публикации и копирования оригиналов.

## Проверка

GitHub Actions: PHP 8.4, SQLite и MySQL 8.4, auth/rate limits, permissions, media upload/download, отказ preview HTML, настройки/cache, render Blade, migrations, composer audit. Состояние worker и фактический запуск сервера проверяются после развёртывания.
