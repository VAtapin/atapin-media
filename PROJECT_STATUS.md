# Atapin Media — статус проекта

Актуализировано: 14 сентября 2026 года.

## Реализовано

- Laravel 13 / PHP 8.4, single-tenant foundation, auth/users/RBAC, encrypted settings, audit и Media Desktop с 19 программами, сохранением окон, Snap Layouts и персональным оформлением. Blade/progressive JavaScript; обязательной Node production-сборки нет.
- Native Desktop: проекты со связанными задачами/материалами/книгами, задачи с ответственными/сроками и доской, календарь month/week/list, темы/иерархические категории, книги/PDF, продажи, рассылки/подписчики, AI history, first-party аналитика и состояния интеграций. Поиск, фильтры и пагинация, в том числе зависимых списков проектов и серий. Пустой список означает отсутствие подходящих данных, а не заглушку программы.
- Existing редактор материалов дополнен проектом, темами, автором, SEO, обложкой/файлами, гостем/транскриптом/Podcast URL и отдельными текстами платформ. Beiträge поддерживают очищенный HTML и PDF; ручное создание сохраняет unpublished draft без автоматической AI-разметки. Серии используют Collection/CollectionItem и эпизоды разных источников; Podcast сохраняет SourceRecord/Media, без параллельной модели.
- Календарные публикации сохраняют UTC и одобренную версию материала, перепроверяют ready/status/permissions/connections через existing queue. Изменённый материал требует повторного планирования; unclaimed scheduled/queued jobs можно отменить. Existing Plesk scheduler запускает desktop:dispatch-due каждую минуту.
- Книги: каталог, author/ISBN/language, публичное оглавление и отдельный приватный полный текст PDF, cover/sample/full attachments и queued Dompdf generation. Полные PDF приватны; платный полный файл нельзя назначить своей же бесплатной Leseprobe. Цена обновляет download roles; originals сохраняются, stale PDF jobs не заменяют текущую редакцию.
- Реальный Stripe Checkout через официальный SDK, encrypted admin API key/Webhook Secret, price snapshot и повторное использование pending checkout. Только signed webhook с совпавшими session/amount/currency выдаёт entitlement; browser return не выдаёт доступ. Expired/failed sessions отмечаются failed; полный refund отзывает доступ без восстановления replay. Purchased download защищён entitlement, доступен после архивирования книги и связан из /konto.
- Newsletter campaign editor/preview/segments/scheduling/explicit confirmation и background recipient preparation/delivery. Только active double-opt-in subscribers; перед отправкой проверяются consent и права сотрудника. Campaign после старта immutable. Cancel не возобновляется после in-flight delivery; неизвестный результат транспорта не приводит к автоматическому повтору. Admin CSV защищён от formula injection; opt-out signed GET/POST.
- AI workspace: queued OpenAI Responses structured output/store=false, без tools/secret access/automatic publishing; per-user history и лимит 50/day. Title/summary/SEO применяются явно и только к неизменённому материалу. Отключённый AI не вызывает API; Content Library не посылает фоновый short-description request при явно недоступном сервисе.
- Аналитика: реальные first-party daily session-deduplicated page/search/PDF/audio events, existing video views, registrations, current confirmed subscribers и paid sales по валютам. Private paths, IP и поисковые запросы не сохраняются; CSV/reports защищены permissions. Внешние показатели не подменяются выдуманными числами.
- Community сохраняет existing human/AI moderation и добавляет native общий inbox импортированных/локальных сообщений, read state, Website replies и queued реальные YouTube comment replies. Website moderation actions не показываются импортированным сообщениям; duplicate external reply jobs защищены atomic claim.
- Publishing: реальные YouTube/Facebook/Instagram/Telegram/X connectors, independent RTMP Live outputs, selected-destination jobs, resumable YouTube uploads/processing/retries, Website edits/hide/delete по поддерживаемым API, daily reverse YouTube Review import. OAuth YouTube/X Client ID/Secret вводит только администратор в Einstellungen → Social Media; encrypted credentials не берутся из .env и не возвращаются браузеру. Partial integration save сохраняет другие secrets.
- Media Library/imports: protected preview/download/Range, resumable files/folders/archives, pause/resume/stop/retry, trash/restore, SHA-256 dedupe, playlist links, checkpoints и пообъектные отчёты. Импорт сам по себе не публикует материалы. Canonical public/media/SHA-256 и прямые original MP4 URLs сохранены, документы приватны. Takeout сохраняет originals/source revisions/manual edits/trash/exclusions и сообщает неизвестные schemas. Podcast audio, frame covers и explicit AI cover/short-description используют existing background jobs.
- Public Website: approved branding/owner assets, десять data-driven страниц, детали/каталоги/фильтры/поиск/пагинация, local video/audio, comments/reactions/newsletter/account, AJAX forms, Live HLS/heartbeat и афиша/fallback. Article covers/gallery показываются целиком и открываются в accessible lightbox. Mobile book overflow устранён; Public/Desktop CSS изолированы.
- Mobile Beiträge cards keep their two-column grid but switch each card to image-above-text, with a readable 4:5 preview and wider title/excerpt area; desktop card layout is unchanged. Video details keep a wide shell while contained portrait playback gets a blurred poster backdrop; public-pages stylesheet cache version is v19.
- Public article image containers keep full `contain` rendering and use a subtle blurred, translucent color backdrop derived from the same image.
- Live Studio: native events/edit/publication/OBS credentials/poster upload, MediaMTX/nginx hooks, recording segments и public statuses. Telegram announcements используют cover/text + Website URL без video upload; optional registered Main Mini App deep links — через protected public resolver.

## Важные решения

- OAuth/payment/AI credentials — защищённая админка/encrypted settings, не .env/Git/чат; обычные public users не получают admin routes. Existing SMTP/sendmail остаётся серверной mail-конфигурацией.
- Долгие PDF/AI/newsletter/taxonomy операции — existing queue. Не добавлены новые cron/systemd services, video optimization pipeline или параллельный admin shell. Task board — текущая страница результата, calendar сообщает лимиты; analytics — daily sessions, не точное число людей.
- Production: /var/www/vhosts/mannavomhimmel.de/httpdocs; document root platform/public; private runtime вне public root. Intake/YouTube/Takeout originals не затрагиваются deployment платформы.

## Известные ограничения

- Настоящие provider approvals/payment/SMTP/Live и production не проверялись агентом. YouTube public API требует разрешённый Google project; X/Meta/Telegram — account/API permissions. TikTok/LinkedIn profile-only; generic Google/Mailchimp/Zapier/Webhook credential forms не являются API adapters. External channel analytics не подключена.
- X metadata edit, Instagram edit/hide/delete, unsupported native social post types, universal reverse sync и browser-native OBS-like mixer не добавлены. Lost creation responses вне recoverable API могут дать дубли; expired YouTube sessions не перезапускаются автоматически. Подробнее docs/PUBLISHING.md и docs/DESKTOP-WORKSPACES.md.
- In-flight email/external publication нельзя отозвать отменой. Unknown delivery не пересылается автоматически; partial Stripe refund не отзывает весь доступ. При отсутствии/архивировании полного private PDF checkout недоступен.
- Live segment replay merge отсутствует. Задержка старта отдельного replay не диагностирована: ранее /live был пуст, playback-check URL давал 404; нужен доступный event URL. Remux/transcoding без подтверждённой причины не добавлялись.
- Takeout не проверен на всех языках/форматах и не гарантирует новый Short flag. Checkpoints межобъектные, не побайтовые ZIP; большой hash/extraction может превысить мягкий slice. Требуются disk reserve, ffprobe/ffmpeg и реальная browser playback проверка больших файлов.
- Existing homepage header overflow на 820 px не затрагивался. 100% pixel match всего проекта не заявляется; screenshots новых проектов и публичной книги desktop/mobile просмотрены.

## Проверки

- Финальный полный composer test, PHP 8.4.25 / SQLite: **340 tests / 2531 assertions passed**. Целевые DesktopWorkspaces/Foundation ранее 44 tests / 379 assertions; финальный full включает последующую readiness-регрессию.
- PHP syntax: 354 файла; JS syntax: 32 файла, затем повторены затронутые PHP/lang/fixture/JS/browser checks. Blade view:cache и route:cache passed; локальные caches очищены. Composer validate --strict и audit passed (no advisories).
- Edge native workspaces 1672×941 / 390×844: CRUD/dates/board/private edition/drafts/HTML/series, calendar/analytics/shop/integrations/AI, expandable community inbox/read/reply; никаких JS или HTTP >=400 ошибок. Отдельная синтетическая SQLite, без реальных API/писем. Desktop close/reload/login/deeplink persistence passed.
- Edge public-pages: десять страниц с пустыми и заполненными данными, desktop/mobile/tabs/assets/JS/overflow passed. Screenshots проектов и mobile-книги просмотрены. Existing publishing-browser и media-upload-controls passed; соответствующие scripts после проверки не менялись.
- PublicWebsiteTest: 6 tests / 54 assertions and PublicPagesTest: 20 tests / 166 assertions passed after the video backdrop change; Blade `view:cache` also passed. The isolated browser run remains unavailable because the local PHP development server reports blocked `mbstring` in that runner.
- Local MySQL/MariaDB suite, настоящие payment/SMTP/encoding/Live и production deployment не запускались. CI сохраняет MySQL job; rollback/legacy-data simulation намеренно SQLite-only, остальные forward migrations проверяются CI.

## Что рекомендуется следующим

- Сделать Plesk backup БД, применить release с Composer lock, двумя forward migrations, route/view clear и queue restart; затем platform:check. Новых Node build/cron не требуется. Production агентом не обновлялся.
- Через админку подключить платформы/OpenAI/Stripe, зарегистрировать Stripe webhook/events по docs/DESKTOP-WORKSPACES.md; проверить тестовую покупку/refund/protected download, newsletter delivery/opt-out и scheduled publication. Secrets в чат не присылать.
- Проверить реальные большие MP4/audio/Live/Telegram Mini App на desktop/mobile; media:prepare-missing при необходимости для уже импортированных материалов, без нового Takeout import.

## Последний связанный commit

- Предшествующий commit: 4f9f855 — Move OAuth app credentials to admin settings; article lightbox ранее в bdb3516.
- Последний функциональный commit: video poster backdrop; hash будет указан после commit/push. Предшествующий `7b4397c` — Improve mobile article card layout; branch/upstream main → origin/main.

