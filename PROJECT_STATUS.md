# Atapin Media — статус проекта

Актуализировано: 15 сентября 2026 года.

## Реализовано

- `platform/` работает на Laravel 13 / PHP 8.4 как self-hosted single-tenant приложение. Готовы foundation, auth/users/RBAC, encrypted settings, audit, Media Library, imports, проекты, задачи, календарь, publishing, community, analytics, public website и оболочка Media Desktop. Интерфейс построен на Blade и progressive JavaScript, обязательной production Node-сборки нет.
- Media Desktop использует единый native workflow: компактное создание и inline-редактирование проектов, задач, материалов, книг и Podcast без лишних Desktop-окон. Проекты показывают paginated связанные задачи, материалы, книги и внешние публикации; специализированные материалы открываются в соответствующем редакторе.
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
- Изменений Composer/npm dependencies нет. Новая forward-compatible migration добавляет только поля повторения задач.

## Известные ограничения

- Реальные OpenAI, Stripe, SMTP, Meta, YouTube, X, Telegram, OBS/WHIP и production credentials локально не использовались; интеграционные ответы проверены через HTTP fakes и контрактные тесты.
- TikTok и LinkedIn пока остаются profile-only, без фиктивного publishing API.
- Production deployment и post-deployment smoke-test агентом не выполнялись.
- Перед production migration обязателен backup базы данных.

## Проверки

- Полный локальный suite на PHP 8.4.25 / SQLite: **450 tests, 3546 assertions — passed**.
- PHP syntax: **67 изменённых/новых файлов — passed**. Node syntax: **20 изменённых/новых JS/MJS — passed**.
- Blade `view:cache` и Laravel `route:cache` — passed; generated caches после проверки очищены.
- Browser scenarios ранее на этом же итоговом workflow прошли для Desktop workspaces, targeted calendar open, books/PDF, native reviews, Podcast/Live и content enhancements; desktop/mobile screenshots просмотрены.
- `git diff --check` и содержательный diff проверяются перед commit. Dependencies не менялись, поэтому Composer audit и npm build не требуются.

## Что рекомендуется следующим

- После backup получить commit на production и применить migration повторяющихся задач; очистить config/routes/views.
- В настройках по очереди сохранить app credentials и пройти реальные OAuth/API checks Meta, YouTube, X, Telegram и Stripe test mode. Секреты в чат не присылать.
- Выполнить production smoke-test задач/календаря, книг/рецензий, Podcast/Live и `platform:check`.

## Последний связанный commit

- Текущий функциональный блок: этот commit — `Complete Media Desktop workflow overhaul`. Branch/upstream: `main` → `origin/main`.
- Предыдущий функциональный commit: `241f988` — `Fix AI workflow regressions`.
