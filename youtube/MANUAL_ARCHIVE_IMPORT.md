# Ручной архив YouTube и Google Takeout

## Рабочий импорт

Немецкая выгрузка владельца импортируется через Import Center → Google Takeout vom Server.
На сервере сохраняются восемь исходных ZIP:

```text
/var/www/vhosts/mannavomhimmel.de/private/youtube_zip_alle/
takeout-20260911T193951Z-1-001.zip … takeout-20260911T193951Z-1-008.zip
```

Выбрать экспорт и общее число частей (8), затем Import starten.
Маленький `takeout-20260911T193951Z-001.zip` — отчёт, не девятая часть.
Ручная распаковка и подмешивание к public collector archive не нужны.
ZIP не удаляются, распаковка идёт в private import-inbox; нужно место для originals.

Все части индексируются совместно: Videos/Videotexte CSV, Beiträge с image attachments,
опросы, Kommentare и Playlists становятся цельными материалами.
Сопоставление видео использует ID/точный оригинальный title, затем только уникальную
нормализованную связь. Реальный read-only просмотр нашёл 244/244 соответствия;
две почти одинаковые строки различаются исходными пробелами.
Неясные связи остаются в отчёте, не угадываются.
Известные Shorts сохраняются; новые варианты без достоверного признака требуют проверки типа.

Старый public archive `private/manna-youtube` и private intake originals сохраняются.
Перед пересозданием каталога воспользоваться **Alten Katalog zurücksetzen**:
очистка скрывает прежние YouTube records/playlists и ошибочные image-generated Beiträge,
но не удаляет Media, собственные документы/фото или ZIP.
Восстановление доступно в том же окне и блокируется при конфликтах с новыми IDs.
Повторное чтение новых Takeout records безопасно; metadata дополняются,
ручные/принятые тексты и playlist layout сохраняются.

## Проверка результата

**Ergebnis im Detail** показывает пообъектные результаты, ошибки и неясные связи.
Retry продолжает тот же run, пропуская завершённые extraction/file/metadata checkpoints.
Текущий compressed ZIP entry может распаковываться заново; это не побайтовый resume.

Media Library по умолчанию показывает полные материалы; **Dateien** — отдельная галерея.
Videos/Beiträge используют защищённые локальные originals, не YouTube players/links.
Отсутствующие playlist позиции остаются локальными metadata, не выдаются за доступное видео.
**Lokale Videos prüfen** проверяет серверные файлы/размер/ffprobe;
**Alle im Browser prüfen** отдельно проверяет HTTP 206, кадр, seek и короткое воспроизведение.
Реальный серверный импорт 55 GB и playback всех originals ещё требуют приёмки после deployment.

Подробности, ограничения схемы и настройки: [Import Center](../docs/IMPORT-CENTER.md).
Неизвестная структура YouTube Studio/другой язык не обещаются автоматически поддержанными.
История/GPS сохраняются как originals, не становятся статьями и не отправляются ИИ.
Пароли, cookies, дампы и приватные exports не добавлять в Git.
