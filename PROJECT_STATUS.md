# Atapin Media — статус проекта

## Реализовано

- Временный `/upload/` окончательно отключён: index всегда redirect `/desktop`, API всегда 410; UI app.js/app.css/login.php удалены. Private archive не изменяется, прежний инструмент доступен в Git. CI сохраняет archive/auth проверки и проверяет retired HTTP contract вместо старой загрузки. Связанный commit — текущий `Retire the temporary upload interface`.

- Media Library, пункт 6: SourceRecord AI имеет собственный журнал running/applied/failed/insufficient/superseded, proposals и before/after snapshots; доступны просмотр и безопасная отмена в inspector. Отмена восстанавливает состояние до ручного запуска (включая прежний status), не удаляет originals и блокируется при последующих manual/import изменениях. Явно queued AI проверяет версию записи ещё до provider call, чтобы не заменить более новую правку. Старые AI результаты без snapshots нельзя откатить задним числом.

- Media Library, пункты 2/3/4: импортированные playlists имеют редактор title/description, добавление зарегистрированных video/short (включая другой source), перемещение соседей и удаление только membership. Manual playlist layout сохраняется при reimport, новый export сохраняется отдельно в metadata. Массовые действия до 100 SourceRecords добавляют tags/status/раздел без изменения body/title и assets; Library-only записи остаются в общей библиотеке, возвращаются в разделы. Канонический imported video возвращается через file assignment без дубля и замены принятого текста.

- Media Library, пункты 1/5/7: локальные assets находятся по существующим metadata/usages и точным YouTube IDs без угадывания названия; фоновая проверка восстанавливает связи и parent/cover, новые импорты используют тот же механизм. Есть ручное подключение зарегистрированного video original без потери прежних files/text. Usage references открывают локальный материал внутри Desktop, внешние кнопки inspector скрыты. Header-only image data и ffprobe format/stream data читаются локальным queue job без ИИ, сеть ffprobe запрещена; inspector показывает duration/resolution/format/codec и ошибки.

- Безопасное повторное чтение импортов: одинаковые originals сопоставляются по SHA-256/размеру/MIME, альтернативные private storage locations сохраняются отдельно без удаления файлов. SourceRecords объединяются по source/id: недостающие связи и metadata дополняются, более полное исходное описание сохраняется, ручные/ИИ-принятые title/body не заменяются. Исходные версии доступны в раскрываемой панели «Import-Versionen». Повторный идентичный импорт не создаёт лишние версии и не запускает повторный платный ИИ для уже существующего файла. Навигация и просмотр материалов/playlist positions не предлагают внешние кнопки; адреса источников остаются provenance, local media используют защищённый плеер.

- Привязка обложек: в деталях image можно раскрыть «Als Video-Cover zuordnen», найти video/short с pagination и выбрать конкретный SourceRecord, даже без локального video original. Выбранное cover используется для video-file thumbnail; originals/описания не копируются и не перезаписываются, чужой parent блокирует ошибочную привязку. Inspector учитывает прямые metadata-связи, не только usages. ИИ больше не создаёт самостоятельный материал из известного thumbnail без parent.

- Этап фоновых импортов: история показывает реальные processing stages, queued задания отменяются сразу, running переходят через stop_requested в cancelled. Downloads прерываются через Process stop, archive/file/metadata обработка проверяет остановку между операциями; originals не удаляются. Cancelled import можно повторить со стабильным ID/options, сохранённые файлы перечитываются. Worker claim защищён от повторного запуска, timeout/failure hook снимает running state. Progress исправлен на array, без выдуманного общего процента.

- Этап экономной ИИ-разметки: используются существующие descriptions/текстовые файлы/готовые SRT/VTT, без чтения audio/video originals; filename-only не вызывает provider. Для новых классификаций Media сохраняются before/after snapshots, доступна безопасная отмена без удаления originals и связанных материалов; последующие ручные изменения блокируют отмену. Незавершённая ручная форма сохраняется при обновлении списка и требует подтверждения при смене записи. Архивные файлы исключены из batch. Возврат собственных file-generated материалов в Media Library скрывает их в разделах, сохраняя данные; дочерние assets не назначаются ИИ как отдельные Beiträge.

- Этап загрузки: Media Library имеет queue с двумя параллельными файлами, drag-and-drop и выбор client folder, pause/resume/stop без удаления accepted data. Relative client paths проверяются и сохраняются только в metadata; одинаковые basename в разных папках имеют разные resume keys. Import Center получил pause/resume/stop для archive transfer (не для фонового ImportRun). Успешная очередь скрывается, неподтверждённые файлы остаются с подсказкой повторного выбора.

- Этап организации Media Library: дополнительные действия и фильтры раскрываются отдельно; выбор до 100 файлов, массовое добавление tags/status/раздела/коллекции и archive/restore без удаления originals/usages. Собственные Collections (не YouTube Playlists) создаются/переименовываются, имеют description, pagination, перестановку соседних файлов и удаление только связи. Фильтры tags/collections/active/archive и все service sources; раскрываемый file inspector показывает storage, parent/assets, collections, usage names и последние AI proposals.

- Этап удобства Import Center/Media Desktop: четыре понятных способа импорта вместо Quelle/Pfad/ID/URL, скрытые дополнительные настройки, серверный браузер с текущим путём/назад/явным выбором папки и сообщениями пустого/недоступного каталога. История пагинируется и открывает библиотеку. Кнопка ? во всех окнах открывает отдельную локализованную справку поверх рабочего окна; для ещё пустых программ справка не обещает готовую функциональность. Playlist отличает локальную структуру/описание, реально доступный video original и внешний YouTube link; убраны большие синие кнопки с названиями всех позиций. Защищены списки Media Library от устаревших параллельных ответов после upload.

- Основа платформы на Laravel 13 / PHP 8.4: авторизация, пользователи, RBAC, настройки и аудит.
- Media Library, импорт материалов, проекты, задачи и календарь.
- Media Library получила расширенную основу для единого входящего архива: связи основного материала с дочерними assets, теги, коллекции, usage references, архивирование и журнал AI-классификаций. Новые ручные загрузки и архивные intake/YouTube-импорты по умолчанию получают статус `unsorted`; оригиналы остаются в соответствующих private archives.
- Media Library теперь открывается как native-приложение внутри Media Desktop: приватный пагинируемый список поддерживает поиск, фильтры по источнику/типу/статусу, сортировку, детали записи и защищённое скачивание. Интерфейс показывает реальные записи intake и YouTube после запуска существующего Import Center.
- В окне Media Library обновлён presentation layer под единый визуальный язык Media Desktop: цвета, типографика, радиусы, кнопки, поля, карточки и отступы; убран встроенный повторный заголовок `ARCHIV` и заголовок окна.
- Детали Media Library безопасно показывают inline preview изображений, MP3/OGG, MP4/WebM и PDF только через авторизованный private-media route. Неподдерживаемые форматы сохраняют только детали и защищённое скачивание; preview-ответы запрещают активный контент через CSP sandbox.
- Защищённый resumable upload в `intake/` и сборщик публичного YouTube-архива в `youtube/`.
- Собственный resumable upload Media Library реализован и локально проверен (production-проверка ещё нужна): добавлены endpoints `/desktop/media/uploads` (start/chunk/finish), сервис сборки чанков с дедупликацией и проверкой SHA-256, модель и миграции для инвентаризации сессий и чанков, запись в `media` с `source='upload'`, статус `unsorted`, и интеграция в UI Media Library (кнопка, drag-нейтральный input, прогресс + ошибки).
- Оболочка Media Desktop с меню Start, ярлыками, панелью задач и пустыми окнами программ.
- Окна поддерживают фокус, закрепление, сворачивание, разворачивание, полноэкранный режим и изменение размера за края и углы.
- Snap Layouts содержат готовые схемы для 2–6 окон, включая крупное центральное окно с четырьмя вспомогательными.
- Выбранная Snap-схема остаётся активной: новые окна последовательно занимают свободные зоны, а занятые зоны повторно не используются.
- Одно окно может занимать прямоугольное объединение соседних Snap-ячеек; режим «Всегда поверх окон» влияет только на `z-index` и совместим с таким размещением.
- Состав открытых окон, их размеры, положение, свёрнутое состояние и Snap-зоны сохраняются в браузере отдельно для пользователя и восстанавливаются после перезагрузки.
- Компактная кнопка на панели задач закрывает все окна и очищает сохранённое состояние рабочего стола.
- Рабочий стол занимает весь viewport до нижней панели задач; доступ к аккаунту находится в меню Start.
- Start содержит постоянный каталог из 19 программ; Desktop хранит только пользовательские ярлыки, которые можно перемещать, удалять и вновь добавлять из Start или контекстного меню.
- Инициализация ярлыков использует только каталог программ, а не кнопку профиля: поэтому открытие Einstellungen не может отключить контекстное меню и перетягивание ярлыков.
- В Desktop & Design добавлена отдельная общая настройка размещения ярлыков: свободное позиционирование или сетка. Сетка выравнивает ярлыки, уплотняет их после удаления и поддерживает смену порядка перетягиванием; она не связана со Snap Layouts окон.
- Для Desktop подключены четыре полных approved-набора из 19 PNG: Manna Vom Himmel, Standard, Grün и Sol. В системных настройках выбираются набор значков, approved-фон, пользовательский фон и акцентный цвет без изменения ярлыков, окон или их расположения.
- `Einstellungen` является центральным разделом настроек: Desktop & Design, KI, Social Media, Publishing, Integrationen, Benutzer & Rechte и System. Настройки хранятся в таблице `settings`; API-ключи и токены хранятся там же в зашифрованном виде и никогда не возвращаются в форму.
- Social Media и Integrationen не показывают пустые поля: пользователь добавляет конкретный provider и получает только подходящую заготовку. Для Social Media доступны YouTube, Facebook, Instagram, TikTok, Telegram, LinkedIn и X; для Integrationen — Stripe, Google Drive, Google Calendar, Google Analytics, Mailchimp, Zapier и Webhook. Публичные ссылки и IDs хранятся отдельно от зашифрованных credentials.
- Benutzer & Rechte показывает пользователей, позволяет создать пользователя и сменить его роль, сохраняя существующие RBAC-защиты. В System есть отдельный выбор языка для Impressum, Datenschutz и редакционных текстов с визуальным редактором; каждый язык хранится отдельно в БД.
- Settings использует всю доступную ширину развёрнутого окна. У каждого пользователя есть отдельный профиль: фотография, имя, e-mail, телефон, место, описание, личный сайт и персональные ссылки на соцсети. Он открывается по имени в Start и не смешивается с официальными подключениями проекта. Администратор может открыть `Bearbeiten` у любого пользователя, изменить имя, e-mail, роль и при необходимости сбросить пароль; `Abbrechen` сбрасывает форму и закрывает её, а успешное сохранение формы создания или редактирования закрывает её автоматически.
- Einstellungen внутри Desktop использует компактные вкладки: одновременно видна только одна панель. В Benutzer & Rechte роль выбирается отдельно, а её права и форма создания роли раскрываются по запросу.
- Встроенные Einstellungen начинаются с вкладок без повторного branding/title/introduction. Форма использует плотную Desktop-компоновку; персональный масштаб 90–130 % применяется к Media Desktop и сохраняется в browser storage только после явного сохранения.
- Пользовательские язык, timezone и branding применяются на уровне запроса из БД. Einstellungen открывается внутри стандартного окна Media Desktop как чистое встроенное приложение без старой sidebar/header-оболочки. Desktop & Design даёт live preview обоев, набора значков, акцента, плотности, эффектов и масштаба; закрытие с несохранёнными изменениями запрашивает подтверждение и восстанавливает сохранённое состояние.
- Legacy UI Media Desktop полностью удалён: удалены standalone-страницы, общий layout, sidebar, header, breadcrumbs, навигация и все старые GET-маршруты модулей. `Einstellungen` использует самостоятельный native-view `desktop/settings.blade.php` и `desktop-settings.css`, без `app.css`, legacy partial или iframe.
- Каталог Start/Desktop содержит 19 программ: Media Library объединяет Bilder, Audio и Dateien; Newsletter включает Subscribers. Добавлены Podcast, Themen & Kategorien и Shop & Verkäufe. Shop имеет базовые таблицы товаров и продаж без фиктивного payment provider.

## Текущее состояние и решения

- Manna Vom Himmel — первый single-tenant deployment платформы.
- Desktop реализован как адаптивная Blade/JavaScript-оболочка без обязательной Node-сборки.
- Окна программ без нового native-интерфейса намеренно остаются пустыми; старый интерфейс не сохранён как fallback. Backend-операции модулей, защищённые загрузки, RBAC, settings, import jobs и workflow-сервисы сохранены для следующих native-интерфейсов.
- Проверен весь Media Desktop на дублирующие заголовки: Settings — окно с контентом и без повторного названия приложения; Media Library очищено от повторяющегося заголовка приложения (и `ARCHIV`) и начинается с рабочих элементов.
- Snap Layouts активируются курсором только в узкой зоне 14 px у верхней границы рабочего стола.
- Синяя подсветка Snap-зоны является только временным drag-preview и очищается после Drop, отмены, сворачивания, закрытия и восстановления Desktop.
- После закрытия последнего окна активная Snap-схема сбрасывается; свёрнутые окна продолжают удерживать схему и свои области.
- Короткое ручное перемещение не возвращает свободное или изменённое окно в Snap-схему; размещение в области сетки требует явного перемещения.
- Наборы значков и approved-обои описаны в `platform/config/desktop.php`; новый клиентский набор добавляется как запись конфигурации и папка с теми же 19 именами файлов.
- Оформление Desktop — общая настройка рабочей области, а позиции ярлыков и состояние окон — отдельные browser-настройки каждого пользователя.
- Загруженный пользовательский фон хранится на private disk и отдаётся только авторизованным пользователям с доступом к Desktop; принимаются PNG, JPEG и WebP до 10 MB.
- Профили пользователей находятся в отдельной таблице `user_profiles`; фотографии хранятся на private disk и доступны только владельцу профиля через авторизованный route.
- Publishing содержит только общие правила workflow: видимость по умолчанию, timezone для публикаций, обязательное подтверждение и автоматизацию. Реальные OAuth-авторизации и публикация во внешние сервисы пока не реализованы: разделы Social Media и Integrationen сохраняют безопасную конфигурацию и credentials как основу для их отдельных адаптеров. Podcast и Themen/Kategorien пока не имеют backend-модулей в Laravel; их ярлыки подготовлены для предусмотренных модулей.
- Production работает через Plesk; document root — `httpdocs/platform/public`.
- Стандартное production-обновление platform выполняется из `/var/www/vhosts/mannavomhimmel.de/httpdocs` через `git pull --ff-only` и только необходимые `config:clear`/`view:clear` c `/opt/plesk/php/8.4/bin/php`; без PATH exports и maintenance-скрипта.
- Публичный YouTube-сборщик сохранил частичный архив в `private/manna-youtube` (около 3.9 GB); полный личный архив владелец скачивает отдельно и хранит в `private/manna-youtube-manual/<дата-выгрузки>`. Текущий Import Center принимает структурированный архив сборщика; для YouTube Studio/Google Takeout нужен отдельный адаптер ручного формата. Полная инструкция: `youtube/MANUAL_ARCHIVE_IMPORT.md`.
- Миграция статуса: для Media Library первично добавлен собственный загрузочный путь на платформе; временно внешний `/upload/`-инструмент продолжает работать для существующих кейсов и будет выключен после приемки новой пайплайна.
- Import Center внутри Media Desktop: API `/desktop/imports`, intake, YouTube-архив, локальная папка/архив, выбор целевого профиля и история заданий. Этап 3 заменил заглушки реальным queued импортом публичных ссылок через установленный yt-dlp runtime; YouTube-каналы используют существующий collector с Beiträge/опросами/комментариями. Распознаются collector/yt-dlp JSON, видео CSV и atapin-content/v1; оригиналы неизвестных форматов сохраняются. Подробности и ограничения: docs/IMPORT-CENTER.md.
- Расширена модель импорта (`import_runs`): `source_kind`, `source_options`, `target_profile`, `discovered`, `progress`, `error`, `started_at`, `finished_at`, а также запись `target_profile` в metadata media/source-объектов.
- Этап 2 Import Center: загрузка ZIP/TAR/TAR.GZ/TGZ прямо с компьютера через существующий resumable upload до 20 GB, повторные попытки при сбоях и продолжение после повторного выбора файла. Папки регистрируют реальные пути; распакованные оригиналы остаются в private/import-inbox/archives, повторный импорт не дублирует записи. Добавлена недостающая source_ref migration, исправлена отправка ImportArchive в очередь. Проверяются traversal, ссылки и лимиты распаковки.
- UI Import Center в десктопе использует общий layout-макет без повторяющихся заголовков окон, с формой запуска и списком последних запусков.
- Этап 4: Media Library показывает файлы и отдельные Beiträge/metadata, поддерживает список/сетку с protected thumbnails, выбор серверных папок и запуск регистрации существующих intake/YouTube archives из UI. Окна Videos, Beiträge и Community получили native-просмотр соответствующих imported SourceRecords, текста, опросов, комментариев и связанных originals; импорт остаётся private/unsorted. Обновление списков после завершения импорта; pagination ограничена предыдущей/следующей страницей.

- Этап 5: native-формы ручной разметки файлов и SourceRecords, tags/status/раздел, reuse original без публикации; retry failed/partial imports. Фоновая OpenAI Responses-классификация новых unsorted записей и batch до 100 старых, строгий schema validation, confidence/история/аудит, защита конкурентных ручных правок. Первая успешно завершённая Desktop-загрузка создаёт private marker, который закрывает старый intake API (410) и перенаправляет страницу на Desktop. Поздние media в повторном YouTube archive attach к reviewed записи без потери правок.

- Доработан этап 3/4: collector/yt-dlp playlists импортируются в Collections и видны в native UI с pagination позиций и переходом к найденному SourceRecord. Неполный channel inventory больше не маскируется успешным сбором Beiträge. Structured exports поддерживают явные files без смешивания assets разных записей; service thumbnails/subtitles связываются с video parent.

## Известные ограничения

- Для Projekte, Aufgaben, Kalender и Shop ещё требуется отдельная разработка native-интерфейсов внутри Desktop.
- Production deployment/приёмка последних изменений не подтверждены: реальные service downloads, OpenAI-запросы, 10–20 GB transfer и automatic intake cutover локальными тестами не подтверждены. По решению владельца платное прослушивание/анализ аудио и видео не входит в ближайший план; ИИ использует имеющиеся тексты, метаданные, готовые субтитры и небольшие изображения; поддержан только OpenAI.
- Семь согласованных пунктов Media Library реализованы в коде; нужна серверная приёмка с реальными registered originals. Автоматическая привязка использует точные IDs/metadata/usages, не похожие названия; неизвестные files можно связать вручную, Takeout требует отдельного адаптера. Technical audio/video data требуют установленного ffprobe (MEDIA_FFPROBE_BINARY), установка/проверка бинарника на production не выполнялась. Отмена AI доступна только для новых журналов со snapshots, не для старых proposals. Import Center ещё требует полного адаптера личных YouTube exports и подробного per-item результата. Дедупликация используется при регистрации архивов/серверных папок; уже существующие дубли автоматически не удаляются, ручной upload пока имеет отдельный registry. Фоновый stop реализован, но текущая операция/hash/copy может завершиться до checkpoint; granular resume отсутствует, retry перечитывает сохранённые данные. Серверная приёмка не заменяется локальными тестами.
- Public Website начат, но ещё не завершён.
- Для ручных YouTube выгрузок поддержаны ZIP/TAR и распознаваемые JSON/видео CSV. Произвольные варианты Takeout, экспортные HTML и неизвестные schemas ещё требуют отдельного разбора; нельзя выдавать их регистрацию файлами за полный импорт содержания.

## Рекомендуемый следующий этап

- После backup базы в Plesk обновить production, выполнить migration/config:clear/view:clear и проверить реальную загрузку/импорт и доступность service runtime. В библиотеке нажать «Vorhandene Archive einlesen», чтобы зарегистрировать имеющиеся intake/YouTube originals.
- После получения личной выгрузки YouTube реализовать адаптер ручного архива по `youtube/MANUAL_ARCHIVE_IMPORT.md`, затем запустить импорт через Import Center.

## Проверки

- SourceRecord AI: 15 целевых RecordClassification/ContentAssignment/RecordOrganization tests / 92 assertions, JS syntax/Blade compilation и Edge workflow отмены с показом undone состояния прошли. Проверены восстановление pre-queue status/text/tags, scoped permissions, повторная отмена, intervening edits до provider call, отсутствие provider call при недостаточных данных и сохранность originals. Реальные платные запросы не выполнялись.

- Организация текстов/playlist: 17 целевых PlaylistEditor/RecordOrganization/ImportedContent/ContentAssignment tests / 118 assertions, JS syntax, Blade compilation и Edge workflow (bulk tags, playlist reorder с обратным восстановлением) прошли. Проверены cross-source membership, порядок, reimport preservation, scoped IDs/permissions, атомарность bulk и обратное назначение canonical video. Production migration требует backup.

- Локальные связи/технические данные: 9 целевых tests / 71 assertions для links/content/merge и 2 tests / 11 assertions для image headers, metadata preservation, video normalization и permissions прошли; JS syntax/Blade compilation и Edge workflow (queue probe, usage navigation, links repair) прошли. Реальный ffprobe на production и наличие файлов на сервере ещё не подтверждены. Техническая обработка не вызывает платный provider.

- Повторные импорты: 15 целевых ImportMerge/LocalImport/ArchiveImport tests / 72 assertions прошли; существующие описания/субтитры проверены отдельным тестом. JS syntax, Blade compilation и Edge workflow (включая исходные версии, отсутствие внешних кнопок и сохранность форм) прошли. Полные suite, реальные provider requests и production не запускались. Реальные 8 Takeout ZIP прочитаны локально только для структуры/малых CSV, без распаковки 55 GB: 244 video originals, 242 однозначных сопоставления по исходному названию, 2 неоднозначных требуют безопасного отчёта, а не автоматического угадывания. Отдельный takeout-20260911T193951Z-001.zip — отчёт, не часть видеоархива.

- Обложки: 3 целевых Laravel tests / 22 assertions, JS syntax/Blade compilation и Edge workflow ручного выбора cover прошли. Проверены отсутствие video original, связь с local video, thumbnail selection, сохранность originals/описаний и permissions/conflicting parent. Платные ИИ-запросы не запускались.

- Этап фонового управления: 4 ImportControl tests / 25 assertions, 2 целевых folder/ZIP regression tests / 7 assertions и 4 archive tests / 18 assertions прошли. Проверены stop queued/running, сохранность accepted data, retry options, duplicate claim, worker failure, Process stop (unit mock), permissions; Edge workflow кнопки остановки/повторного запуска и JS/Blade checks прошли. Реальный yt-dlp stop и production не проверялись.

- Этап ИИ/сохранности правок: 9 целевых Laravel tests / 48 assertions прошли (нет provider call по имени, готовые subtitles/descriptions, undo и concurrent manual edits). JS syntax, Blade compilation и Edge workflow сохранения незавершённой формы при refresh прошли. Full suite и платные provider requests не запускались.

- Этап загрузки: 1 целевой Laravel upload flow / 14 assertions прошёл (checksum/finish, unsafe client path rejection, сохранение client path только в metadata). Node control test проверил parallel pause/resume, stop без finish, продолжение прежней сессии и разные папки с одинаковыми basename; JS syntax, Blade и Edge workflow прошли. Реальные 10–20 GB и native folder picker на production не проверены; full suite не запускался.

- Этап организации: 2 целевых Laravel tests / 26 assertions прошли (collections, порядок, bulk filters, archive/restore, сохранность originals и permissions). Blade/JS syntax и Edge workflow создания коллекции/выбора файла/массового добавления/смены вида прошли. Полный suite не запускался.

- Этап удобства: 8 целевых Laravel tests / 52 assertions прошли (навигация/границы каталогов, auth, локальное наличие видео и безопасные external links). Blade compilation, JS syntax и Edge workflow справка/серверная навигация/история/Playlist/ручное сохранение проверены; desktop 1672x941 и mobile 390x844 screenshots просмотрены. Полный suite не запускался по просьбе владельца об экономных проверках.

- Доработка playlists: 30 целевых Laravel tests / 153 assertions прошли; native Edge browser workflow (playlist → найденный video/description) и desktop/mobile screenshots проверены. Проверяются отдельные asset links, video/thumbnail/subtitles roles и pagination после 100 позиций. Python: 12 passed / 1 skipped (ffmpeg), service_import --help прошёл.

- Этап 5: 27 целевых Laravel tests / 129 assertions прошли; 7 новых tests покрывают ручную разметку, retry, конфигурацию/permissions, AI success/error/concurrent edits и недостаточные данные. Проверен путь ZIP upload → Import Center → readable originals и Beiträge.
- Edge browser workflow прошёл: upload, ручное редактирование файла/Beitrag/tags/status, сетка, content sections, queue existing YouTube archive; screenshots desktop 1672x941/mobile 390x844 проверены.
- Intake: реальный HTTP workflow с cutover redirect/API 410 прошёл, archive 33 checks и auth прошли, JS/PHP/shell syntax проверены. Plesk-subfolder workflow недоступен в Windows/Git Bash из-за несовместимого преобразования путей; нужен Linux CI.
- Этапы 2–4: upload/archive/content tests, view:cache/route:cache и JS checks прошли. Python YouTube suite: 10 passed / 1 skipped (нет ffmpeg); service_import --help прошёл, реальный массовый сбор не запускался.
- Полный Laravel suite выявил старую отдельную ошибку повторного profile update: unique user_profiles.user_id. Это не исправлялось в задаче импортов; полный suite не считается зелёным.
- MySQL, реальные OpenAI/service requests и production не проверялись.

## Последние связанные commits

- Удобство и справка: 55fd044 — Simplify imports and explain local content in desktop windows.
- Организация: 08c1c59 — Organize library files with collections and reversible archiving.
- Загрузка: 43bb6c8 — Add pausable parallel uploads and client folder intake.
- Экономная ИИ-разметка: 76fd699 — Preserve manual edits and safely undo economical AI classification.
- Фоновые импорты: 4d92c41 — Stop background imports safely and show processing stages.
- Обложки: 9606bd5 — Link image covers to specific video content.
- Обогащение: 8e8f0d7 — Merge imported originals and preserve richer source versions.
- Локальные связи/технические данные: b222123 — Connect local video assets and expose technical file data.
- Плейлисты и массовая организация: 64a7a89 — Edit local playlists and organize imported content in bulk.
- Текущий этап: Track and safely undo AI classification of imported records (commit с этой записью).

- Этап 2: f55f340 — Fix archive imports and add resumable desktop intake.
- Этап 3: 7d787a3 — Import service links and structured archive content.
- Этап 4: dc6a618 — Show imported content across desktop libraries.
- Этап 5: df9d5a0 — Add reviewed content assignment and automatic AI classification.
- Доработка этапа 3/4: Preserve playlist structure and archive asset associations (hash — git log commit, содержащего эту запись).

