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

Перед migration в production выполнить documented backup. Для ZIP PHP 8.4 должен иметь ZipArchive; для TAR — Phar. Очередь обслуживается существующей Plesk scheduled task, новые systemd/cron настройки не нужны.
