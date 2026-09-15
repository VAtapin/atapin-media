# Atapin Media — статус проекта

Актуализировано: 16 сентября 2026 года.

## Реализовано

- `platform/` работает на Laravel 13 / PHP 8.4 как self-hosted single-tenant приложение. Готовы foundation, auth/users/RBAC, encrypted settings, audit, Media Library, imports, проекты, задачи, календарь, publishing, community, analytics, public website и оболочка Media Desktop. Интерфейс построен на Blade и progressive JavaScript, обязательной production Node-сборки нет.
- Media Desktop использует единый native workflow: компактное создание и inline-редактирование проектов, задач, материалов, книг и Podcast без лишних Desktop-окон. Проекты показывают paginated связанные задачи, материалы, книги и внешние публикации; специализированные материалы открываются в соответствующем редакторе.
- Общие кнопки удаления в каталогах корректно работают и в карточках, и в списках: подтверждение не блокируется обработчиком родительской карточки, после успешного `DELETE` каталог обновляется. Исправление распространяется на проекты, темы и книги/PDF.
- Темы и категории стали общей иерархической системой для Beiträge, Videos и Bücher. В Media Desktop редакторы показывают заметный multi-select, а карточка темы позволяет массово искать, фильтровать, добавлять и снимать назначения по разделам с отдельными permission checks. Служебные, архивные, Community, Podcast и Live-записи не смешиваются с редакционными разделами.
- Public Website показывает на главной и в каталогах тонкую однострочную breadcrumb-навигацию по категориям и темам вместо больших карточек и повторных заголовков. Категории раскрываются компактным меню с маленькими обложками; на главной выбор категории показывает её темы, в каталогах выбор фильтрует текущий раздел. Лимит главной в 12 тем снят: при 10 категориях и 5–10 темах данные не скрываются. Ссылки и счётчики Videos/Beiträge/Bücher у тем остаются раздельными; выбор родительской категории рекурсивно включает материалы дочерних тем внутри текущего типа контента. Legacy `?tag=` сохранён как отдельный metadata-фильтр.
- Задачи поддерживают состояния `open/planned/working/waiting/done`, приоритеты, сроки, проект, ответственного, checklist/tags и повторение `once/daily/weekly/monthly/custom`. Повторы вычисляются из одной задачи без создания дубликатов. Доска и список сохранены; календарь показывает задачи, публикации и Live и открывает точную исходную запись.
- Книги/PDF имеют title-first intake, inline editor, queued PDF analysis/generation, private originals, sample/full attachments и Stripe-entitlements. Рецензии перенесены в native Desktop-диалог с фильтрами, пагинацией и approve/reject; legacy `book-admin` UI удалён, старый маршрут безопасно перенаправляет в Bücher & PDF.
- Jodit Editor 4.13.9 размещён локально и используется для `Beschreibung`, `Inhaltsverzeichnis`, текста полной PDF-редакции, длинных текстов Beiträge/Videos/Podcast и страниц `Website & Autor` (Impressum, Datenschutz, редакционные сведения, Über uns, Mission, правила Community). Самодельные rich-text панели заменены. Публичные книги, материалы, homepage book feature и информационные страницы отображают безопасную HTML-разметку вместо буквальных тегов; короткие карточки книг используют plain-text excerpt. PDF-генератор сохраняет разрешённое оформление текста.
- Редакторы Beiträge/Videos и Media Library открывают рабочую форму первой: длинный дубль текста/предпросмотра убран сверху, сведения об источнике и файлах свернуты ниже, селекты собраны в адаптивную сетку, темы остаются у основных полей. `Mit KI einordnen` сохранена с пояснением. У привязанного файла убрана отдельная кнопка `Ersetzen`; выбор существующего материала теперь не загружает полный архив и требует адресный поиск от двух символов.
- Сохранённые импорт-версии показывают дату и удаляются по одной красным крестом после подтверждения. Удаление требует `content.edit` (для опубликованной записи также `content.publish`), записывается в audit и не меняет текущий `SourceRecord` или оригинальные медиафайлы.
- Podcast создаётся и редактируется inline поверх существующих `SourceRecord`, `Media`, assignments/assets. Поддержаны audio/video podcast, cover/file, episode/season, историческая дата публикации и явный переход к Publishing для будущего расписания.
- Live Studio имеет явные OBS и Browser modes, responsive 30/70 layout, существующий Browser Studio/WHIP/recording pipeline и связанную OBS-Hilfe. В Browser mode превью уменьшено до компактной панели и по кнопке открывается отдельным окном: встроенная панель при этом исчезает, её колонка не оставляет пустоты, а настройки занимают доступную ширину окна. Устройства, изображение/экран, аудиозапись и трансляция сгруппированы, запуск выделен красным. Неподдерживаемая фиктивная Facebook Live-трансляция не добавлялась.
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
- Rich-text HTML при сохранении и публичном выводе проходит общий `RichContent` sanitizer: разрешены редакционные заголовки, списки, цитаты, таблицы, безопасные ссылки и ограниченное выравнивание. Скрипты, event-атрибуты, iframe и произвольные стили не допускаются. Jodit подключён как локальный статический vendor asset с MIT license; Composer/npm dependencies и схема БД не менялись.
- Маленькие обложки категорий в публичной taxonomy-навигации выводятся только из публичного canonical image storage; private media-preview URL не попадает в страницу. При отсутствии обложки навигация остаётся текстовой. Статическая полоса имеет рамку 1 px и не растёт от количества категорий или материалов; длинные списки открываются в прокручиваемых меню.
- Удаление импорт-версии касается только выбранной строки snapshot. Следующий повторный импорт того же источника может создать эту версию заново; подавление повторного импорта удалённых snapshot не добавлялось без отдельного решения по provenance.
- Отдельное окно Browser Studio показывает беззвучную локальную копию canvas, без второго публичного плеера или отдельной передачи. После закрытия окна встроенное превью восстанавливается; кнопка повторного открытия остаётся доступной в заголовке. Поле WebRTC hostname подставляет hostname текущей страницы, если серверное значение пусто, и объясняет требуемый формат; недоступность защищённого MediaMTX API и выключенная Browser-передача поясняются отдельно от поля.

## Известные ограничения

- Реальные OpenAI, Stripe, SMTP, Meta, YouTube, X, Telegram, OBS/WHIP и production credentials локально не использовались; интеграционные ответы проверены через HTTP fakes и контрактные тесты.
- TikTok и LinkedIn пока остаются profile-only, без фиктивного publishing API.
- Production deployment и post-deployment smoke-test агентом не выполнялись.
- Перед production migration обязателен backup базы данных.
- Вставка изображений непосредственно в rich-text пока отключена: её следует добавить отдельным блоком через защищённую Media Library и проверку public media URLs; arbitrary URL/base64 images не допускаются.
- Старый тест `DesktopWorkspacesTest::test_calendar_uses_local_time_and_respects_access` после перехода локальной даты воспроизводимо получает `null` вместо `10:30`; он не связан с taxonomy, но требует отдельной стабилизации календарного теста/границы timezone.
- Полный Playwright-сценарий приложения локально не завершился: Chrome/Edge не переходили на `127.0.0.1` из этой среды, хотя тестовый PHP-сервер отвечал. Сквозные проверки остаются в CI; изолированные браузерные проверки этого блока прошли.
- На production владелец проверил существующую конфигурацию MediaMTX: `api=false`, `webrtc=false`, локальный API `127.0.0.1:9997` не слушает и отвечает `HTTP 000`, хотя фоновый процесс держит server lock. Это объясняет `Server-API nicht erreichbar`; браузерная настройка ещё не применена. Публичный TCP/UDP 8189 открыт владельцем, но API не включается открытием firewall-порта. Результат защищённого применения конфигурации и реальной трансляции ещё не подтверждён.

## Проверки

- Полный локальный PHP suite на PHP 8.4 / SQLite без указанного старого календарного теста: **459 tests, 3716 assertions — passed**. Этот тест не заявлен как прошедший.
- Изолированные Chrome browser checks: форма Beiträge/Media Library и сетка селектов на `1672 × 941` и `390 × 844`, положение Jodit/taxonomy/действий с файлами, адресный поиск и индивидуальное удаление импорт-версий — passed. Сквозные проверки обновлены в CI, но локально не прошли по ограничению браузерного localhost.
- PHP/Node syntax затронутых файлов, Blade `view:cache`, routes `route:cache` — passed; generated caches очищены. Composer audit и npm build не требуются: manifest dependencies не менялись.
- Для Browser Studio изолированный Edge browser check на `1672 × 941` и `390 × 844` прошёл: группы, полноширинная панель, исчезновение встроенного превью и расширение настроек после открытия отдельного окна, восстановление после закрытия, работающий локальный canvas-поток, hostname/help и отсутствие horizontal overflow в обоих состояниях. PHP/Node syntax и Blade `view:cache` прошли, compiled views очищены. PHP Feature/реальный MediaMTX transport для этого блока локально не запускались: установленный PHP 8.4 не имеет `pdo_sqlite`, а pinned MediaMTX binary здесь отсутствует; проверка Browser Studio добавлена в CI.
- Для тонкой taxonomy-навигации **36 PHP tests, 320 assertions — passed** на локальном PHP 8.4 / SQLite; отдельный scale-тест проверил 10 категорий, 50 тем и 12 Beiträge в теме. Изолированный Edge browser проверил главную и каталоги Videos/Beiträge/Bücher на `1672 × 941` и `390 × 844`: полоса до 42 px, рамка до 2 px, смена категории, меню тем, переходы и отсутствие horizontal overflow/JavaScript errors — passed. PHP/Node syntax и Blade `view:cache` прошли; compiled views очищены. Полный suite для этой локальной правки не повторялся.

## Что рекомендуется следующим

- Применить опубликованные Public Website и Live Studio UI commits на production обычным `git pull --ff-only` и очистить compiled Blade views; после фактического deployment выполнить документированный `platform:check`. Migrations, Composer и Node build для UI-блоков не нужны.
- После обновления кода и в отсутствие активной трансляции в Live Studio указать публичный WebRTC hostname `mannavomhimmel.de`, включить Browser-Übertragung и применить защищённую серверную конфигурацию. Она требует установленного MediaMTX и FFmpeg с libx264/AAC; затем проверить локальный API и пройти реальный smoke-test трансляции. Публичные API/WHIP/RTSP порты открывать не требуется.
- При следующем запуске CI проверить обновлённые сквозные браузерные сценарии на штатном Linux/Chromium окружении.
- Отдельно спроектировать защищённый выбор изображений из Media Library для Jodit, если изображения в тексте нужны владельцу.
- В настройках по очереди сохранить app credentials и пройти реальные OAuth/API checks Meta, YouTube, X, Telegram и Stripe test mode. Секреты в чат не присылать.
- Выполнить production smoke-test задач/календаря, книг/рецензий, Podcast/Live и `platform:check`.

## Последний связанный commit

- Текущий функциональный блок: этот commit — `Expand Browser Studio after preview pop-out`. Branch/upstream: `main` → `origin/main`.
- Предыдущий функциональный commit: `c414a72` — `Replace taxonomy cards with thin breadcrumb navigation`.
