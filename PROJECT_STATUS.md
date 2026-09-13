# Atapin Media — статус проекта

Актуализировано: 13 сентября 2026 года.

## Реализовано

- Основа: Laravel 13 / PHP 8.4, авторизация, пользователи и RBAC, настройки с зашифрованными секретами, аудит; backend проектов, задач и календаря.
- Media Desktop: 19 программ, окна и Snap Layouts, сохранение персонального расположения, четыре approved-набора значков, общие настройки оформления и отдельные профили пользователей. Einstellungen — native-интерфейс с локализованными редакционными текстами и управлением пользователями. Live Studio подключён к desktop-окну через JSON API: список, создание, редактирование, публикация, OBS-вход, ротация ключа и получение RTMPS-данных больше не требуют отдельной HTML-ссылки.
- Media Library: цельные материалы и отдельная файловая галерея; поиск, фильтры, protected preview/download с HTTP Range, resumable upload папок/файлов с pause/resume/stop, теги, коллекции, редактирование плейлистов и массовые действия. Удаление материалов обратимое; замена cover/video/attachments не перезаписывает originals и чужие связи.
- Import Center: серверные папки/архивы, загрузка архивов до 20 GB, существующие intake/YouTube archives и публичные ссылки через collector/yt-dlp. Фоновая очередь, stop/retry, checkpoints, отдельное окно Import-Vorgänge, пообъектный отчёт и индикация стадии/файла/байтов/давности сообщения. Активность процесса очереди не выдаётся за подтверждение завершения конкретного импорта.
- Takeout: единая распакованная папка private/Takeout либо multipart ZIP; CSV задают IDs, тексты и связи, HTML archive_browser.html проверяет файловый состав. При отсутствии отчёта сохранён fallback с предупреждением. Импортируются видео, Beiträge, изображения, опросы/Quiz, комментарии, Livechats, плейлисты и контоданные; неизвестные схемы сохраняются как originals с результатом в отчёте.
- Повторные импорты объединяют записи по source/ID и originals по SHA-256/размеру/MIME; дополняют metadata/assets, сохраняют ручные правки, исходные версии, trash и исключённые связи. Обложки/комментарии/playlist positions связываются по достоверным IDs и metadata; неоднозначности остаются в отчёте, а не угадываются.
- Lokale Videos prüfen: серверный отчёт по registered originals, размерам и ffprobe; отдельная браузерная проверка same-origin HTTP Range, декодированного кадра, seek и короткого воспроизведения с сохранённым результатом.
- ИИ-разметка импорта: экономная обработка имеющихся текстов/метаданных/готовых субтитров и небольших изображений; журналы, snapshots и защищённая отмена. Takeout не запускает автоматические платные ИИ-задания и не превращает отдельные картинки в самостоятельные Beiträge.
- Старый /upload/ отключён: страница перенаправляет в Desktop, API возвращает 410; приватный intake archive сохранён, прежний интерфейс доступен в Git.
- Public Website: все 10 approved-страниц — главная, Videos/detail, Beiträge/detail, Bücher/detail, Live, Podcast, Community. Каталоги, детали, поиск, фильтры, пагинация, вкладки и локальные плееры читают БД; пустые блоки видимы с обозначением отсутствия данных. Public CSS/JS изолированы от Desktop.
- Brand background: предоставленная владельцем панорама `manna-mountains.png` установлена как единый hero-фон главной и публичных разделов, а также стандартные горные обои Desktop; прежний временный фон больше не используется в этих местах.
- Über uns, Mission и юридические тексты редактируются в настройках. Контактная форма сохраняет обращения в защищённый inbox и пересылает через очередь на settings.contact_email; повторные попытки, видимый статус и ручной retry. Email Live-напоминания поддерживают PHP/sendmail Plesk без обязательного внешнего SMTP.
- Web Push Live: согласие и разрешение браузера, подписка/отмена на событие, encrypted subscriptions, приватные стабильные VAPID keys, scheduler и retryable jobs с проверками публикации/времени. Поддержаны ограниченные endpoints служб доставки Google/Mozilla/Apple/Microsoft.
- Live: обновляемый модерируемый чат и счётчик активных browser sessions. ИИ-помощник использует существующий ключ/provider/model, отдельный запрос с согласием, приватный ответ, опубликованный текстовый контекст и общий дневной лимит вызовов; включается владельцем, не отвечает автоматически на каждое сообщение.
- OBS → MediaMTX → локальный плеер: защищённое управление событиями и encrypted keys, ingest/HLS authorization, pinned installer/start/status, private recording hooks и регистрация MP4. Публичный OBS-вход использует RTMPS на TCP 1936 с per-event ключом; локальный RTMP/SSH остаётся административным fallback, зрители — через nginx /_live/. В /desktop/live показывается полная RTMPS-адреса события, без SSH-инструкций для стримера. Сертификат/ключ и firewall остаются одноразовой серверной настройкой.
- Посетители: регистрация без административной роли, email verification, личный /konto с собственными реакциями/закладками/прогрессом/напоминаниями, отменой подписок и изменением профиля; восстановление пароля через existing Laravel broker и encrypted queue.
- Newsletter: double opt-in, queued confirmation, подписанные ссылки подтверждения/отмены, согласие, cooldown и статусы доставки.
- Книги: защищённый редактор metadata/цены/статуса; verified читатели отправляют отзывы, изменения требуют повторной модерации. Community: вопросы/обсуждения посетителей, правила из настроек, защищённая publish/reject модерация с аудитом.

## Текущее состояние и решения

- Self-hosted single-tenant; Manna Vom Himmel — первая установка, не отдельный Core. Основной UI немецкий, новые строки локализованы.
- Blade и progressive JavaScript без обязательной Node-сборки. Composer используется для PHP-зависимостей Laravel, включая minishlink/web-push; не нужен при обычном обновлении текстов/Blade.
- Production checkout: /var/www/vhosts/mannavomhimmel.de/httpdocs; document root: httpdocs/platform/public; runtime: private/atapin-platform. PHP: /opt/plesk/php/8.4/bin/php. Queue/scheduler — Plesk scheduled tasks, без самостоятельной установки systemd.
- MediaMTX после reboot запускается отдельной Plesk command task каждую минуту; lock предотвращает второй процесс. RTMPS использует файлы private/atapin-live/rtmps.crt и rtmps.key, не входящие в Git.
- Предпочтительный Takeout источник: /var/www/vhosts/mannavomhimmel.de/private/Takeout; TAKEOUT_FOLDER позволяет переопределить путь. Старые private/manna-youtube и private/youtube_zip_alle сохранены совместимыми. Originals, ZIP-отчёт и восемь частей экспорта не удалять при обновлении каталога.
- Import регистрирует оригиналы, не означает автоматическую публикацию. Публичны только явно разрешённые материалы и опубликованные children с опубликованным родителем; архивные контоданные исключены из public и ИИ.
- Видео/файлы публикуются с нашего сервера, без YouTube fallback/кнопок перехода. Адреса источников остаются provenance; приватные originals не раскрываются.
- Платное прослушивание/анализ audio/video не входит в текущий план. Реальные production-письма, Push и вещание проверяет владелец; отсутствие этих проверок у агента не считается недоработкой реализации.
- Media Library и дальнейшая backend-доработка отложены владельцем; текущий приоритет — frontend. Секреты и клиентские данные не входят в Git.

## Известные ограничения

- 100% pixel match не подтверждён; фон главной временный по разрешению владельца. Пустые блоки пока намеренно включены.
- Оплачиваемый checkout не реализован: требуется выбор способа оплаты владельцем. Покупка пока через контакт; платные PDF не выдаются публично.
- Newsletter campaign editor и массовая рассылка не реализованы; подписка/подтверждение/отмена реализованы.
- Записи Live разбиваются на сегменты; объединённый replay отсутствует, текущий replay использует первый сегмент. Автозапуск MediaMTX требует одноразовой настройки Plesk command task, RTMPS — установки публичного сертификата и открытия TCP 1936 владельцем.
- Полные native Desktop-интерфейсы проектов, задач и календаря ещё отсутствуют; Shop имеет редактор товаров/отзывов, но не полный интерфейс продаж. Podcast и Themen/Kategorien не имеют отдельных backend-модулей. Ярлык или безопасная конфигурация интеграции не означает готовый модуль/OAuth/публикацию во внешнюю службу. Browser-native вещание исследовано, но не внедрено: для базовой камеры/микрофона возможен WebRTC/WHIP, а полноценный OBS-подобный микшер источников потребует отдельной клиентской media-архитектуры.
- Takeout проверен на немецкой схеме владельца и основных английских aliases, не на всех возможных языках/форматах. Отдельного достоверного Short-признака экспорт не гарантирует: прежние Shorts сохраняются, новые неопределённые видео требуют review.
- Checkpoints сохраняются между единицами работы с мягким бюджетом 30 секунд; большой ZIP entry/hash/предварительная индексация могут выполняться дольше. Это не побайтовое возобновление ZIP. Старые дубли originals физически не удаляются.
- ffprobe нужен для серверной проверки формата; наличие файла/metadata не доказывает браузерное воспроизведение.
- Ранее полный Laravel suite выявлял unique user_profiles.user_id при повторном profile update; исправление в задачах импорта не подтверждено. Полный suite не объявляется зелёным.

## Рекомендуемый следующий этап

- Выбрать способ оплаты и завершить реальный checkout отдельным ограниченным блоком.
- Затем, по приоритету владельца: newsletter campaigns, объединённый Live replay, визуальная доводка approved-страниц; к Media Library вернуться позже.
- Для диагностики Takeout использовать Ergebnis im Detail → связи/ошибки/манифест и Lokale Videos prüfen → Alle im Browser prüfen; не очищать originals и не заменять проверку ручным просмотром сотен записей.
- Инструкции стримера и администратора доступны непосредственно в /desktop/live через ?; установка MediaMTX/nginx, сертификата/порта RTMPS и production-проверки выполняются владельцем.

## Проверки

Результаты уже выполненных локальных проверок; это не утверждение о проверенном production.

- Public/auth: 40 tests / 337 assertions; после Push/Broadcast hardening — 8 tests / 52 assertions. Все 10 страниц проверены в браузере при 1672×941 и mobile 390px: assets, вкладки, JavaScript и отсутствие horizontal overflow.
- Последняя implementation-задача (Live help): 4 целевых Broadcast tests / 30 assertions, JS syntax, Blade compilation и browser dialog open/Escape/overflow на desktop/mobile прошли.
- Password recovery: 3 целевых Account/Password tests / 28 assertions и Blade compilation прошли.
- MediaMTX конфигурация принята официальным binary 1.21.0; shell syntax прошёл. Composer audit после добавления Web Push прошёл без advisories. ИИ проверялся через mocked HTTP, без платных вызовов.
- Полная папка Takeout: 38 целевых tests / 320 assertions; JSON-тексты 3 279 реальных CSV-ячеек разобраны read-only без ошибок. Манифест и ZIP directories: 1 815 ожидаемых/присутствующих файлов, missing/extra 0; 244/244 video originals однозначно сопоставлены без распаковки 55 GB.
- Импорт/Media Library: целевые tests и browser workflows покрывали retry/checkpoints, merge/manual preservation, trash/restore, replacement/cover, playlist/bulk, AI undo, progress/history и protected HTTP 206/frame/seek/playback. Полный массовый production import не запускался агентом.
- Intake archive/auth/retired HTTP contract проходили; Python collector suite — 12 passed / 1 skipped (ffmpeg). Linux-specific Plesk-subfolder и /proc checks недоступны локально Windows.
- Для Live Studio локально прошли `node --check` для новых и изменённых JavaScript-файлов и `git diff --check`; PHP/Laravel Feature tests недоступны на Windows, потому что PHP не установлен. Для direct RTMPS локально выполнены только read-only diff checks; MediaMTX binary validation на Windows недоступна.

## Последний связанный commit

- Последняя реализация: Connect Live Studio to desktop JSON API.
- Текущая ветка и upstream: main → origin/main. Сохранение этого статуса оформляется отдельным documentation commit: Consolidate current project status.

