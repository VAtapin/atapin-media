# Atapin Media — статус проекта

Актуализировано: 16 сентября 2026 года.

## Реализовано

- `platform/` работает на Laravel 13 / PHP 8.4 как self-hosted single-tenant приложение. Готовы foundation, auth/users/RBAC, encrypted settings, audit, Media Library, imports, проекты, задачи, календарь, publishing, community, analytics, public website и оболочка Media Desktop. Интерфейс построен на Blade и progressive JavaScript, обязательной production Node-сборки нет.
- Media Desktop использует единый native workflow: компактное создание и inline-редактирование проектов, задач, материалов, книг и Podcast без лишних Desktop-окон. Проекты показывают paginated связанные задачи, материалы, книги и внешние публикации; специализированные материалы открываются в соответствующем редакторе.
- Общие кнопки удаления в каталогах корректно работают и в карточках, и в списках: подтверждение не блокируется обработчиком родительской карточки, после успешного `DELETE` каталог обновляется. Исправление распространяется на проекты, темы и книги/PDF.
- Темы и категории стали общей иерархической системой для Beiträge, Videos и Bücher. В Media Desktop редакторы показывают заметный multi-select, а карточка темы позволяет массово искать, фильтровать, добавлять и снимать назначения по разделам с отдельными permission checks. Служебные, архивные, Community, Podcast и Live-записи не смешиваются с редакционными разделами.
- Public Website показывает на главной одну горизонтальную полосу тем с отдельными ссылками и счётчиками Videos/Beiträge/Bücher. Каталоги имеют data-driven категории и темы; выбор родительской категории рекурсивно включает материалы дочерних тем, но всегда остаётся внутри текущего типа контента. Legacy `?tag=` сохранён как отдельный metadata-фильтр.
- Задачи поддерживают состояния `open/planned/working/waiting/done`, приоритеты, сроки, проект, ответственного, checklist/tags и повторение `once/daily/weekly/monthly/custom`. Повторы вычисляются из одной задачи без создания дубликатов. Доска и список сохранены; календарь показывает задачи, публикации и Live и открывает точную исходную запись.
- Книги/PDF имеют title-first intake, inline editor, queued PDF analysis/generation, private originals, sample/full attachments и Stripe-entitlements. Рецензии перенесены в native Desktop-диалог с фильтрами, пагинацией и approve/reject; legacy `book-admin` UI удалён, старый маршрут безопасно перенаправляет в Bücher & PDF.
- Podcast создаётся и редактируется inline поверх существующих `SourceRecord`, `Media`, assignments/assets. Поддержаны audio/video podcast, cover/file, episode/season, историческая дата публикации и явный переход к Publishing для будущего расписания.
- Live Studio имеет явные OBS и Browser modes, responsive 30/70 layout, существующий Browser Studio/WHIP/recording pipeline и связанную OBS-Hilfe. Неподдерживаемая фиктивная Facebook Live-трансляция не добавлялась.
- Настройки разделены по назначению: KI cover-настройки находятся в `KI → Bilder & Cover`, а `Website & Autor` содержит данные автора и website sayings.
- Stripe получил отдельную Test/Live-конфигурацию, encrypted secrets, точный webhook URL, read-only `/v1/account` check и расширенные Checkout metadata для цифровых PDF. Подписанный webhook, amount/currency checks, refund и entitlement flow сохранены. Физическая доставка не добавлена, потому что текущий каталог продаёт цифровые файлы.
- Meta OAuth реализован через App ID/Secret, state, short-to-long token exchange, обязательные scopes, выбор Facebook Page и связанный Instagram professional account. Page token хранится encrypted один раз; check проверяет app/page permissions и Instagram, disconnect удаляет credentials.
- YouTube, X и Telegram имеют реальные read-only connection checks, понятные состояния, безопасные redacted logs и disconnect. YouTube/X обновляют истёкший access token через refresh flow; Telegram проверяет bot, публичный channel/supergroup, membership и право публикации. Пустые profile-only формы больше не создают фиктивные publishing connections.
- Public Website, protected downloads, newsletter, polls, first-party analytics, imports/Takeout, AI suggestions, community inbox, YouTube comments, Browser Studio, PWA и operational health остаются на существующих production-compatible контрактах.

## Текущее состояние и решения

- Generic Core остаётся независимым от Manna Vom Himmel; немецкий — язык первого deployment, UI-строки идут через translations.
- Import регистрирует existing original и не публикует автоматически. Защищённые файлы доступны только через authorization/entitlement contracts.
- Будущая публикация Podcast выполняется через Publishing; поле даты в Podcast хранит фактическую/историческую дату, а не создаёт скрытый scheduler.
- OAuth app configuration отделена от подключённого аккаунта. Секреты не возвращаются в браузер и не записываются в открытые settings/logs.
- Taxonomy assignments используют существующие canonical subject types `record` и `product`; новых migrations и изменений Composer/npm dependencies в этом блоке нет.

## Известные ограничения

- Реальные OpenAI, Stripe, SMTP, Meta, YouTube, X, Telegram, OBS/WHIP и production credentials локально не использовались; интеграционные ответы проверены через HTTP fakes и контрактные тесты.
- TikTok и LinkedIn пока остаются profile-only, без фиктивного publishing API.
- Production deployment и post-deployment smoke-test агентом не выполнялись.
- Перед production migration обязателен backup базы данных.
- Старый тест `DesktopWorkspacesTest::test_calendar_uses_local_time_and_respects_access` после перехода локальной даты воспроизводимо получает `null` вместо `10:30`; он не связан с taxonomy, но требует отдельной стабилизации календарного теста/границы timezone.

## Проверки

- Taxonomy targeted suite: **8 tests, 133 assertions — passed**.
- Полный локальный suite без указанного старого календарного теста на PHP 8.4.25 / SQLite: **455 tests, 3656 assertions — passed**. Полный запуск с ним: **456 tests, 3659 assertions, 1 failure** в старой проверке локального времени.
- PHP syntax: **14 изменённых/новых PHP-файлов — passed**. Node syntax: **4 изменённых JS/MJS-файла — passed**.
- Blade `view:cache` и Laravel `route:cache` — passed; generated caches после проверки очищены.
- Edge browser: полный Media Desktop workflow с массовой привязкой прошёл; public taxonomy проверена на `1672 × 941` и `390 × 844`, ссылки не смешивают разделы и horizontal overflow отсутствует.
- `git diff --check` и содержательный diff проверяются перед commit. Dependencies не менялись, поэтому Composer audit и npm build не требуются.

## Что рекомендуется следующим

- Получить taxonomy commit на production, очистить routes/views и выполнить `platform:check`; migrations, Composer и Node build для этого блока не нужны.
- В настройках по очереди сохранить app credentials и пройти реальные OAuth/API checks Meta, YouTube, X, Telegram и Stripe test mode. Секреты в чат не присылать.
- Выполнить production smoke-test задач/календаря, книг/рецензий, Podcast/Live и `platform:check`.

## Последний связанный commit

- Текущий функциональный блок: этот commit — `Add hierarchical taxonomy discovery`. Branch/upstream: `main` → `origin/main`.
- Предыдущий функциональный commit: `15faed1` — `Fix workspace delete actions`.
