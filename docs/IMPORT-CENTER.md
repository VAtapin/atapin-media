# Import Center

## Приватный Google Takeout и новый каталог

В Import Center выбрать **Google Takeout vom Server**. По умолчанию каталог — `/var/www/vhosts/mannavomhimmel.de/private/youtube_zip_alle` (переопределяется `TAKEOUT_ROOT`). Выбрать export `takeout-20260911T193951Z-1`, указать **8** ZIP частей. Файл `takeout-20260911T193951Z-001.zip` — отдельный отчёт, не девятая часть. Пропуски частей, traversal/links, общий лимит распаковки и свободное место проверяются до начала extraction. Для распакованного экспорта нужно дополнительное private storage плюс reserve; ZIP и распакованные originals не удаляются после импорта.

Реализована фактическая немецкая схема личной выгрузки: `Video-Metadaten/Videos*.csv`, `Videotexte*.csv` с числовым порядком текстовых фрагментов, `Beiträge/Beiträge*.csv`, `Kommentare/Kommentare*.csv`, `Playlists/Playlists.csv` и `*-Videos.csv`. Все ZIP сначала индексируются вместе. Video IDs берутся из CSV; файлы сопоставляются с оригинальным экспортным названием (точное сравнение прежде нормализации), а не с переименованным ИИ заголовком. Только единственное соответствие разрешает связь. Существующие записи объединяются по source/id, originals — по SHA-256/bytes/MIME. Более полные исходные тексты сохраняются, ручные/принятые title/body и ручная playlist layout не перезаписываются. Неизвестная схема/неясная связь видна в результате, исходник сохраняется.

Takeout не содержит явного Short-признака в Videos.csv. Уже известные Shorts сохраняются, остальное не угадывается: новые неизвестные варианты имеют `format_needs_review`. GPS/история просмотров не превращаются в контент и не отправляются ИИ. Новые Takeout originals/records не запускают автоматическую ИИ-обработку; доступен явный экономный batch в UI.

**Alten Katalog zurücksetzen** показывает preview counts и требует подтверждения. Старые YouTube records/playlists и Beiträge, созданные из отдельных image Media, убираются из активного каталога; прежние IDs/metadata сохраняются для восстановления. Новые Takeout records не включаются в повторную очистку. Originals, ZIP, собственные фото/документы/статьи не удаляются. Restore целиком блокируется при конфликте с новыми records; ничего не перезаписывает. Импортировать Takeout после этой очистки — значит создать новые цельные материалы, сохраняя прежние physical originals для cover/asset reuse.

**Ergebnis im Detail** — пагинированный журнал files/content/connections с фильтром added/merged/duplicate/linked/unsupported/failed/ambiguous/unmatched и retry. Resume использует checkpoints завершённых ZIP entries, file registrations и metadata rows. Неизменённые completed files не хешируются/копируются повторно в том же запуске. Неполный текущий ZIP entry может распаковываться снова; это не побайтовое продолжение внутри compressed stream. Повторное чтение маленьких CSV для cross-part индекса/playlist order безопасно и не повторяет завершённые mutations. Старые незажурналированные imports не имеют ретроспективного per-item отчёта.

## Проверить локальное видео на сервере

Нажать **Lokale Videos prüfen** в Media Library / Import Center / Videos. Фоновая задача проверяет все зарегистрированные video originals (включая alternate locations), размер, container/stream данные через установленный `MEDIA_FFPROBE_BINARY` (по умолчанию `ffprobe`) и video/short records без связанного видео. Это ещё не подтверждает браузерное воспроизведение.

После server check нажать **Alle im Browser prüfen** или проверить отдельную строку: защищённый локальный HTTP 206, decoded frame, перемотка и короткая реальная playback-проба. Результат хранится отдельно с датой/пользователем, есть остановка, local preview/download и ошибки. URLs относительные same-origin: worker APP_URL не подменяет текущий сайт. Отсутствующий ffprobe — явная ошибка server check, не фиктивный успех. Тест подтверждает этот браузер и короткий playback sample, а не отсутствие повреждений во всех кадрах всего ролика. YouTube fallback/внешние кнопки не используются. ИИ-вызовов нет, но браузер получает video bytes со своего сервера.

Media Library начинается с цельных материалов (title, original text, files, polls/comments). **Dateien** открывает отдельную thumbnail gallery. ИИ больше не создаёт самостоятельные Beiträge/видео из классифицированного файла. Один повторно загруженный файл открывает canonical Media, даже если он был зарегистрирован раньше; Alle Dateien anzeigen возвращает общий список.

Окно Import Center принимает ZIP/TAR/TAR.GZ/TGZ с компьютера через resumable upload платформы (до 20 GB на файл), существующие intake/YouTube archives, папки/архивы внутри настроенного IMPORT_INBOX_ROOT и публичные HTTPS-ссылки YouTube, TikTok, Instagram и Facebook.

Ссылки обрабатываются в очереди через существующее private/manna-youtube-runtime/bin/python и yt-dlp. Для YouTube каналов используется существующий resumable collector, включая публичные Beiträge, опросы, playlist inventory, комментарии, thumbnails и subtitles. Видео/плейлисты и остальные сервисы используют их yt-dlp extractors. Сервис может отказать в доступе; приватные записи и полная история не гарантируются. Cookies, обход ограничений и OAuth-публикация здесь не используются. Задача показывает failed/partial, а не фиктивный успех.

IMPORT_PYTHON и IMPORT_NODE переопределяют пути runtime и Plesk Node 22. Runtime устанавливается существующей documented командой `bash youtube/bin/plesk.sh install`, только если ещё не установлен. Платформа не устанавливает пакеты и не меняет Plesk самостоятельно.

Распакованные файлы сохраняются в private/import-inbox/archives/<sha256>/files. Это постоянное хранилище: удалять его после импорта нельзя. Папки регистрируются без копирования. Повторная регистрация того же источника не перезаписывает отредактированный материал. Завершённые бинарные файлы доступны через защищённые media routes; незавершённые .part/.tmp не регистрируются.

Поддерживается metadata сборщика (`metadata.json`, `post.json`, `comments.json`), yt-dlp `.info.json` и CSV со столбцами Video ID, Video Title, Video Description (также id/title/description). Распознаются именно эти форматы; произвольный формат выгрузки не считается автоматически разобранным. Нераспознанные originals остаются в Media Library.

Для переносимых структурированных экспортов используется JSON:

```json
{
  "schema": "atapin-content/v1",
  "records": [
    {
      "source": "youtube",
      "id": "original-id",
      "kind": "post",
      "title": "Beitrag",
      "body": "Original text",
      "poll": {"choices": ["Ja", "Nein"]},
      "comments": [{"id": "comment-id", "text": "Kommentar", "author": "Name"}]
    }
  ]
}
```

Типы: video, short, post, poll, comment. Metadata и неизвестные поля сохраняются. Материалы остаются unsorted, их импорт не публикует на Website.

Для точной привязки originals добавьте в запись `files: ["relative/path.mp4", "relative/thumbnail.png"]` — пути относительно корня импортируемой папки/распакованного архива. Для нескольких записей без files применяется только совпадение имени с ID/title, а не привязка всего архива ко всем записям. Collector/yt-dlp playlists сохраняют порядок, повторы и отсутствующие позиции в Collections; просмотр доступен через тип Playlist в Media Library/окне Videos. Список позиций разбит по 100. Обложки/субтитры из одного video directory получают parent/role и protected preview/download.

Перед migration в production выполнить documented backup. Для ZIP PHP 8.4 должен иметь ZipArchive; для TAR — Phar. Очередь обслуживается существующей Plesk scheduled task, новые systemd/cron настройки не нужны.

## Понятный запуск и справка

Вместо технического Quelle/Pfad/ID/URL форма предлагает пять понятных способов, включая Google Takeout vom Server. Дополнительные поля раскрываются отдельно; новые материалы остаются private/unsorted. Серверный браузер ограничен IMPORT_INBOX_ROOT и показывает ошибки явно. История имеет пагинацию и подробный пообъектный отчёт; старые незажурналированные runs сохраняют прежние общие метрики.

Кнопка ? в title bar каждой программы открывает справку поверх рабочего окна. Videos/Playlist различают локальную структуру, описание и физически доступный video original. Внешний YouTube-переход не используется; отсутствие оригинала видно явно. SourceRecord AI history/undo, локальный playlist editor и немецкий Takeout adapter реализованы.

## Разметка и ИИ

Обложки: в деталях изображения раскрыть «Als Video-Cover zuordnen», найти конкретное video/short и подтвердить выбор. Связь создаётся с SourceRecord даже без локального video original; при наличии video file выбранное изображение используется как его thumbnail. Оригиналы и исходное описание не перезаписываются. Изображение с другим parent не переносится молча. Zielbereich Videos сам по себе не является cover linkage. Known thumbnails не создаются ИИ как отдельные материалы.

Media Library: дополнительные действия/фильтры раскрываются отдельно. До 100 файлов можно отметить для tags/status/target/collection и archive/restore; originals/usages не удаляются. Собственные Collections отделены от импортированных playlists, у которых есть локальный редактор с сохранением manual layout при reimport. В inspector доступны locations/assets/usages/AI proposals. Новые Media и SourceRecord AI journals имеют snapshots и безопасную отмену; старые журналы без snapshots не отменяются.

Детали файла позволяют менять title, status, tags и целевой раздел (Videos/Shorts/Beiträge). Создаётся ссылка на оригинал, а не копия и не public-публикация. В деталях SourceRecord редактируются оригинальный текст, тип, title, status и tags. Повторные импорты сохраняют ручные правки.

Автоматическая классификация новых unsorted записей включается в Einstellungen → KI: OpenAI, модель и зашифрованный API key. Можно отключить автоматический режим, запустить отдельный объект или до 100 старых unsorted объектов за раз. Используется Responses API с проверяемым Structured Output и store=false. В провайдер передаются текст/метаданные и небольшие JPEG/PNG/WebP; видео и аудио не просматриваются и не транскрибируются. Confidence ниже 0.85 или недостаточные данные оставляют needs_attention. Ручные изменения во время запроса защищены проверкой версии; ошибки отражаются в статусе и аудите. Anthropic/Azure-классификация пока не подключена.

Failed/partial/cancelled imports повторяются с сохранением run ID, источника, originals и checkpoints. Активный run повторно не ставится в очередь. История показывает этап, а не выдуманный общий процент. Stop отменяет queued сразу; running завершается cancelled на ближайшем checkpoint. Downloads используют Process stop. Завершённые ZIP entries/files/metadata не обрабатываются повторно; текущий entry/hash может начаться заново. Защищены claim и worker failure. Реальный yt-dlp stop требует серверной проверки.

Экономный evidence builder использует existing descriptions и SRT/VTT (ограниченный текст, без новой транскрипции). Файлы только с именем получают needs_attention без платного запроса. Batch не выбирает архивные Media. Несохранённая ручная форма не стирается при обновлении списка, требует подтверждения при переключении записи и сохранения перед ИИ/отменой. File-generated материалы при ручном возврате в библиотеку скрываются в Videos/Beiträge без удаления; обратное назначение canonical imported записи требует отдельной доработки.

## Переключение временной загрузки

Media Library поддерживает очередь с двумя параллельными файлами, drag-and-drop файлов и выбор папки с компьютера. Пауза действует между пакетами; остановка прерывает запросы, но не удаляет принятые оригиналы/части. Для продолжения нужно снова выбрать те же файлы. Client folder paths сохраняются как metadata, не используются как серверные storage paths; resume key различает одинаковые имена из разных client folders. Import Center имеет такие же pause/resume/stop controls именно на стадии загрузки архива, не для уже запущенного фонового ImportRun. Остановленный/неподтверждённый запрос finish мог успеть завершиться на сервере: библиотека обновляется, оригиналы не удаляются.

Временный `/upload/` полностью отключён: redirect `/desktop`, API 410, старые UI assets удалены. Marker успешной загрузки больше не является условием отключения. Private intake archive и оригиналы сохранены; старый инструмент можно восстановить из Git.

Git push не обновляет production. Для применения изменений требуется deployment; наличие/воспроизведение реальных серверных originals проверяется отдельно инструментом локального видео.
