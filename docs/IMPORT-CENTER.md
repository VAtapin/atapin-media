# Import Center

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

## Разметка и ИИ

Детали файла позволяют менять title, status, tags и целевой раздел (Videos/Shorts/Beiträge). Создаётся ссылка на оригинал, а не копия и не public-публикация. В деталях SourceRecord редактируются оригинальный текст, тип, title, status и tags. Повторные импорты сохраняют ручные правки.

Автоматическая классификация новых unsorted записей включается в Einstellungen → KI: OpenAI, модель и зашифрованный API key. Можно отключить автоматический режим, запустить отдельный объект или до 100 старых unsorted объектов за раз. Используется Responses API с проверяемым Structured Output и store=false. В провайдер передаются текст/метаданные и небольшие JPEG/PNG/WebP; видео и аудио не просматриваются и не транскрибируются. Confidence ниже 0.85 или недостаточные данные оставляют needs_attention. Ручные изменения во время запроса защищены проверкой версии; ошибки отражаются в статусе и аудите. Anthropic/Azure-классификация пока не подключена.

Failed/partial imports можно повторить кнопкой в истории. Сохраняются источник, владелец, оригиналы и стабильный ID запуска; активный запуск повторно не ставится в очередь.

## Переключение временной загрузки

После первой успешно завершённой загрузки через Media Library сохраняется private marker `private/atapin-platform/storage/app/private/intake-retired.json`. На следующем запросе старая страница /upload/ (также intake/public) отвечает redirect /desktop, а старый API — 410 и больше не принимает uploads. Ничего в intake archive не удаляется. До подтверждённой загрузки старый intake остаётся доступным. При нестандартном runtime путь marker задаётся INTAKE_PLATFORM_READY в окружении intake. Если document root уже platform/public, /upload/ ведёт в Desktop непосредственно.

Production cutover не считается выполненным только из-за Git push: нужен deployment и реальная успешная загрузка через Desktop.
