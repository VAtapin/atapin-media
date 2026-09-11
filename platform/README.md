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

Под root установите worker и ежеминутный scheduler одной командой:

```bash
bash platform/bin/plesk.sh services
```

Она создаёт отдельные systemd service/timer, запускает обработку от пользователя сайта и включает запуск после перезагрузки. После этого вторую задачу scheduler в Plesk создавать не нужно. Ниже — ручная альтернатива.

Для постоянного worker используйте systemd с `User=` владельца подписки и `ExecStart=/bin/bash /var/www/vhosts/mannavomhimmel.de/httpdocs/platform/bin/plesk.sh worker`. Restart=always. Фоновая обработка не должна зависеть от открытого SSH-окна. Таймаут worker 3600 секунд требует `retry_after` больше 3600 (в config/queue.php установлено 3660).

В Plesk → Scheduled Tasks добавьте задачу пользователя подписки каждую минуту:

```bash
bash /var/www/vhosts/mannavomhimmel.de/httpdocs/platform/bin/plesk.sh schedule
```

Исходные YouTube/intake архивы остаются отдельно. На Foundation библиотека показывает файлы, добавленные непосредственно в неё; подключение ранее собранных архивов выполняется следующим этапом импорта.

## Проверка

GitHub Actions: PHP 8.4, SQLite и MySQL 8.4, auth/rate limits, permissions, media upload/download, отказ preview HTML, настройки/cache, render Blade, migrations, composer audit. Состояние worker и фактический запуск сервера проверяются после развёртывания.
