# KAI — руководство по действиям и навигации

Этот документ служит проверенным источником для таблицы `kai_knowledge_entries`. `Status: implemented` означает работающий путь в текущем приложении, `Status: planned` — предусмотренную возможность без готовой кнопки/интеграции, `Status: unverified` — код уже существует, но реальное production/API поведение ещё не подтверждено. KAI обязан называть статус. Отдельные архивные, installation и Laravel vendor документы не превращаются в инструкции пользователю Desktop. Секреты, приватные медиа и необъявленные данные сюда не копируются.

Источники сверены с `README.md`, `MASTER-TZ.md`, `MANNA-VOM-HIMMEL.md`, `PROJECT_STATUS.md`, `platform/README.md`, `docs/{ADMIN-COMPLETION,CONTENT-ENHANCEMENTS,DESKTOP-WORKSPACES,IMPORT-CENTER,MEDIA-STORAGE,PUBLISHING,PUBLIC-LIVE,UI-IMPLEMENTATION,PLESK-FIRST-INSTALL,PLESK-TASKS}.md`, README отдельных intake/YouTube/tools и действующими Blade/JS/route/service файлами. ТЗ описывает также будущие функции; фактический статус взят из кода и `docs/ADMIN-COMPLETION.md`. После изменения интерфейса обновляют этот документ и выполняют `php artisan kai:sync-knowledge`; расписание делает это каждые шесть часов.

## public-navigation — Верхнее меню сайта
Status: implemented
Keywords: меню, навигация, oben, menu, start, videos, beiträge, bücher, livestreams, podcast, community, über uns, my trade
Sources: platform/config/public_ui.php; platform/resources/views/public/header.blade.php

В верхней полосе публичного сайта находятся `Start` (`/`), `Videos` (`/videos`), `Beiträge` (`/beitraege`), `Bücher` (`/buecher`), `Livestreams` (`/live`), `Podcast` (`/podcast`), `Community` (`/community`) и `Über uns` (`/ueber-uns`). На узком экране сначала нажимают значок `☰`, затем нужный пункт. `Mein Konto` справа ведёт в `/konto` после входа или в `/login` до входа. `Mitmachen` ведёт в Community. Поиск в шапке ведёт в `/suche`. Пункта `My Trade` в этом меню и Media Desktop текущего проекта нет; KAI не должен придумывать его место.

## public-search — Поиск на сайте
Status: implemented
Keywords: suche, search, найти, искать, книга, видео, подкаст, тема, категория, interview, lecture
Sources: platform/app/Services/PublicCatalog.php; platform/resources/views/public/section.blade.php

Поле поиска расположено справа в верхней полосе сайта и открывает `/suche?q=...`. Результаты относятся только к публичным опубликованным материалам, активным книгам и активным темам/категориям. В разделах `Videos`, `Beiträge` и `Bücher` собственное поле/фильтры уточняют текущий каталог. Поиск по книге включает название, публичное описание и оглавление; закрытый текст платной полной редакции и черновики не выдаются. Если результата нет, сначала проверьте статус публикации, затем точное название или тему.

## public-content — Что видно посетителю
Status: implemented
Keywords: сайт, frontend, опубликовано, visible, published, live, article, video, podcast, book
Sources: platform/app/Services/PublicContent.php; platform/app/Services/PublicBooks.php; docs/MEDIA-STORAGE.md

Website показывает записи со статусом `ready` и `public_published=true`, активные книги и активные темы/категории. Импортированный оригинал и редакционный draft не становятся публичной страницей автоматически. Бесплатные MP4/изображения/аудио в canonical public media являются прямыми файлами; скрытие страницы само по себе не ограничивает байты. Платные PDF и приватные документы защищены отдельным доступом. KAI на публичной форме отвечает из публичной базы, не из приватного архива или административных секретов.

## public-books — Книги и оглавление
Status: implemented
Keywords: buch, bücher, книга, книги, amazon, оглавление, содержание, leseprobe, pdf
Sources: platform/resources/views/public/buch.blade.php; platform/app/Services/PublicBooks.php; docs/DESKTOP-WORKSPACES.md

`Bücher` в верхнем меню открывает каталог активных книг; карточка ведёт на детальную страницу с названием, автором, публичным описанием и оглавлением. Доступные кнопки зависят от книги: `Leseprobe`/бесплатный PDF, запрос покупки, внешняя магазинная ссылка, покупка защищённой полной редакции, закладка и отметка чтения. Полный платный текст хранится отдельно и не становится контекстом публичного KAI. Если книга активна, но KAI её не назвал, это ошибка поиска, а не отсутствие книги.

## public-topics — Темы и категории
Status: implemented
Keywords: themen, kategorie, taxonomy, tag, темы, категории, книжная полка
Sources: platform/app/Services/PublicTaxonomy.php; platform/resources/views/public/categories.blade.php; PROJECT_STATUS.md

На Start и Beiträge книжная полка ведёт к активным темам и категориям; `Alle ansehen` открывает `/themen`. Каждая активная категория имеет свою полку даже без опубликованного материала. Нажатие книги темы ведёт в подходящий раздел через фильтр `taxonomy`; название категории на Start ведёт в `/themen?category=slug`. Темы могут объединять Beiträge, Videos и Bücher. Выбор категории или темы лишь фильтрует отображение, публикацию не меняет.

## public-live-ai — Публичный KAI в Live
Status: implemented
Keywords: KI-Assistent, ask, fragen, live, consent, ответ, вопрос
Sources: platform/resources/views/public/live.blade.php; platform/app/Services/PublicAiChat.php

На детальной странице Livestream под чатом есть отдельное поле `KI-Assistent` и кнопка `KI fragen`. Вошедший посетитель вводит вопрос и отмечает согласие на отправку провайдеру. Ответ показывается только ему, не публикуется как обычное сообщение в Live-Chat. KAI использует опубликованные записи, активные книги/темы и время сервера; неизвестные факты отмечает как неизвестные. Вызов отвечает в том же запросе либо показывает ошибку, без ожидания worker.

## public-book-controls — Кнопки на странице книги
Status: implemented
Keywords: buch, bücher, inhaltsverzeichnis, description, reviews, leseprobe, kaufen, bookmark, прочитать, оглавление
Sources: platform/resources/views/public/buch.blade.php; platform/app/Services/PublicBooks.php

На странице книги под заголовком вкладки `Beschreibung`, `Inhaltsverzeichnis`, `Rezensionen`, `Verwandte Materialien` переключают видимый блок; оглавление находится именно во второй вкладке. В `Rezensionen` вошедший читатель выбирает оценку и отправляет отзыв на модерацию. Справа `Kaufen` открывает Checkout только для подготовленной платной PDF; `Gekauft` открывает уже оплаченную редакцию, `Kaufanfrage` ведёт в контактную форму, внешняя магазинная ссылка — за пределы сайта. `PDF herunterladen`/`Leseprobe lesen` работают лишь при наличии открытого sample; выключенная кнопка означает отсутствие файла. Закладка и отметка прочтения сохраняются для вошедшего аккаунта и видны в `Mein Konto`.

## public-videos — Видео и вкладки записи
Status: implemented
Keywords: videos, lecture, interview, shorts, share, watch later, bookmark, tabs, player, видео, интервью, лекции
Sources: platform/resources/views/public/video.blade.php; platform/resources/views/public/video-tabs.blade.php

`Videos` сверху открывает каталог опубликованных видео/Shorts; поиск, темы и теги уточняют список, нажатие карточки — запись с player. На ней `Teilen` делится адресом, `Später ansehen` сохраняет закладку для вошедшего аккаунта. Вкладки `Beschreibung`, `Kapitel`, `Materialien`, `Kommentare` переключают описание, главы с переходом к указанному времени, связанные файлы и комментарии; пустая вкладка показывает отсутствие данных. Тег ведёт в отфильтрованный каталог, `Nächste Videos` — к другой опубликованной записи.

## public-articles — Beiträge и действия читателя
Status: implemented
Keywords: beiträge, artikel, pdf, vorlesen, teilen, bookmark, kommentar, bild, статьи, читать
Sources: platform/resources/views/public/beitrag.blade.php; platform/resources/views/public/comments.blade.php

`Beiträge` ведёт в каталог опубликованных текстов; карточка открывает статью. Вверху статьи `PDF herunterladen` активна только при приложенном публичном PDF, `Vorlesen` запускает чтение текста в браузере, `Teilen` делится адресом, закладка сохраняет материал в аккаунте. Нажатие на обложку/изображение открывает просмотр с крестиком и стрелками; теги открывают фильтр Beiträge. Под статьёй форма `Kommentar schreiben` отправляет сообщение на модерацию, а связанные статьи/видео находятся справа. Недоступная PDF-кнопка не создаёт файл.

## public-podcast — Podcast для слушателя
Status: implemented
Keywords: podcast, folge, serie, guest, staffel, weiterhören, player, bookmark, подкаст
Sources: platform/resources/views/public/podcast.blade.php; platform/app/Services/PublicContent.php

`Podcast` сверху открывает `/podcast`. Быстрые плитки `Katalog`, `Serien`, `Themen`, `Gäste`, `Alltag` прокручивают страницу к соответствующему блоку; фильтры/поиск и карточка выбирают опубликованный эпизод. У выбранного выпуска есть `Mehr anzeigen`, player, закладка и комментарии; `Serien` и темы переходят к соответствующим выпускам. `Weiterhören` показывает последнюю сохранённую позицию вошедшего слушателя, при её отсутствии — пустое состояние. Это не кнопка публикации нового Podcast.

## public-community — Community и голосование
Status: implemented
Keywords: community, frage, diskussion, umfrage, vote, guidelines, join, kommentar, сообщество, опрос
Sources: platform/resources/views/public/community.blade.php; platform/resources/views/public/poll.blade.php

`Community` ведёт в `/community`; верхние плитки перемещают к обсуждениям, вопросам, опросу и правилам, `Mitmachen` ведёт к форме либо регистрации. В `Neue Frage/Diskussion` посетитель вводит заголовок, выбирает тип, пишет текст и отправляет на модерацию. В опросе radio/checkbox зависят от разрешённого числа вариантов; `Abstimmen` работает при открытом опросе и выполненных audience/вход условиях, результаты показываются по установленному правилу. Внешний опрос открывает разрешённую HTTPS ссылку или iframe; его результаты контролирует провайдер.

## public-account — Mein Konto и история
Status: implemented
Keywords: mein konto, account, anmelden, registrieren, purchases, bookmarks, reminders, ai history, покупки, закладки
Sources: platform/resources/views/public/account.blade.php; platform/resources/views/public/header.blade.php

`Mein Konto` находится справа в шапке: до входа ведёт в `/login`, после входа — в `/konto`. Для обычного читателя аккаунт показывает закладки/прогресс, Live-напоминания, покупки/доступные PDF, подписки, собственные сообщения/отзывы и историю вопросов KAI. Заголовок записи открывает её публичную страницу; маленький крестик убирает сохранённую закладку или напоминание, не удаляет исходный материал. `Bestätigung erneut senden` повторно отправляет email-верификацию, если она требуется. Staff видит переход на `/desktop`, а не чужую читательскую историю.

## public-live-controls — Эфир, чат и напоминания
Status: implemented
Keywords: livestream, live-chat, senden, reminder, push, share, like, bookmark, запись, напоминание
Sources: platform/resources/views/public/live.blade.php; platform/resources/views/public/push-button.blade.php

`Livestreams` сверху ведёт в `/live`; опубликованное событие открывает player, дату/статус и чат. В поле Live-Chat `Senden` отправляет обычное сообщение, которое может ждать модерации; это отдельное действие от `KI fragen`. `Vormerken` сохраняет напоминание для вошедшего пользователя, Browser-уведомления включаются отдельной кнопкой и зависят от разрешения браузера; отмена доступна в `Mein Konto`. Внизу `Teilen`, like и bookmark относятся к выбранному событию. `Sendeplan` и `Aufzeichnungen` открывают будущие/завершённые записи, когда они опубликованы.

## public-newsletter — Подписка на письма
Status: implemented
Keywords: newsletter, abonnieren, consent, email, abmelden, подписка, письмо
Sources: platform/resources/views/public/newsletter.blade.php; platform/resources/views/public/newsletter-cancel.blade.php

Форма `Newsletter` встречается на Start и других публичных страницах: введите email, отметьте согласие, нажмите `Abonnieren`. Письмо подтверждения требуется до включения в рассылку; без согласия кнопка не активирует адрес. Ссылка отписки из письма отменяет подписку; `Mein Konto` показывает её текущий статус. Отправка формы не означает немедленную доставку очередного выпуска.

## desktop-navigation — Программы Media Desktop
Status: implemented
Keywords: desktop, programme, shortcut, starten, открыть, где найти, window, menu
Sources: platform/resources/views/desktop.blade.php; platform/public/assets/desktop-os.js

После входа `/desktop` значки программ находятся на рабочем столе и в меню `Programme` у нижней панели. Доступны в зависимости от прав: Übersicht, Umfragen, Videos, Beiträge, Bücher & PDF, Podcast, Live Studio, Media Library, Projekte, Aufgaben, Kalender, Newsletter, Themen & Kategorien, Publishing, Shop & Verkäufe, KI-Dashboard, Analytics, Import Center, Integrationen; Community и Einstellungen показываются при соответствующих правах. Нажатие значка открывает отдельное рабочее окно. Если программа отсутствует, проверьте право доступа; не обещайте скрытую кнопку.

## desktop-windows — Окна и ярлыки
Status: implemented
Keywords: fenster, window, öffnen, schließen, minimieren, shortcut, desktop
Sources: platform/public/assets/desktop-os.js; platform/resources/views/desktop.blade.php

Значок на рабочем столе или в `Programme` открывает приложение. Окно можно перемещать, менять размер, свернуть, развернуть и закрыть обычными действиями оконной оболочки. Контекстное меню ярлыка добавляет/убирает быстрый доступ. `Close All` закрывает неактивные рабочие окна; активная Browser-Live сессия защищена от случайного закрытия. Изменение расположения ярлыков не меняет данные проекта.

## overview — Übersicht и виджеты
Status: implemented
Keywords: übersicht, dashboard, widget, weiterarbeiten, systemstatus, aktuelle arbeit
Sources: README.md; docs/ADMIN-COMPLETION.md; platform/public/assets/desktop-overview.js

Откройте `Übersicht` на Desktop. Карточки показывают собственные проекты/задачи, новые/проблемные медиа, Review, публикации, Live, Community и собственные предложения KI по правам пользователя. Нажатие карточки открывает исходный материал или модуль. Виджет можно переставить мышью или клавиатурой, скрыть/показать и выбрать обычную/широкую ширину; порядок и размер сохраняются для аккаунта/устройства. Технические сбои очереди доступны только с правом настроек. Рекомендация KI сама ничего не меняет.

## projects — Projekte
Status: implemented
Keywords: projekt, project, neu, bearbeiten, timeline, phase, team, deadline
Sources: docs/ADMIN-COMPLETION.md; docs/DESKTOP-WORKSPACES.md; platform/public/assets/desktop-project-timeline.js

`Projekte` → `Neu` создаёт проект; открытие карточки/строки даёт редактирование названия, описания, типа, этапа, ответственного, команды, дат, тегов, обложки и следующего действия. Фильтры/поиск ограничивают список. Связанные задачи, материалы и книги открываются в собственных редакторах и постраничных списках. Timeline показывает реальные смены фаз/статусов, а не придуманный процент. Изменение проекта не публикует связанный контент.

## tasks — Aufgaben
Status: implemented
Keywords: aufgabe, task, board, liste, meine aufgaben, status, priorität, wiederholung, checklist
Sources: README.md; docs/ADMIN-COMPLETION.md; PROJECT_STATUS.md

`Aufgaben` → `Neu` создаёт задачу с описанием, проектом, материалом, исполнителем, приоритетом, сроком и checklist. Переключатели `Board`, `Liste`, `Meine Aufgaben` меняют представление/фильтр; они не создают копии задач. Перенос карточки в Board изменяет статус только показанной задачи, список Board ограничен текущей страницей фильтра. Дата и необязательное время HH:mm используются в системном часовом поясе. Повторение `once/daily/weekly/monthly/custom` вычисляет следующие сроки из одной записи.

## calendar — Kalender
Status: implemented
Keywords: kalender, monat, woche, liste, termin, veröffentlichung, schedule, cancel
Sources: docs/DESKTOP-WORKSPACES.md; docs/ADMIN-COMPLETION.md

`Kalender` переключает `Monat`, `Woche`, `Liste`; выбор записи открывает её исходную задачу, материал или Live. Фильтры по проекту/типу/назначению сужают экран и не меняют сроки. Планирование публикации требует готового материала и подключённых выбранных каналов; редактирование уже одобренного текста аннулирует прежнее подтверждение. `Abbrechen` отменяет ещё не захваченное запланированное задание, но не отзывает публикацию, уже сделанную внешним сервисом.

## posts — Beiträge
Status: implemented
Keywords: beiträge, beitrag, artikel, neu, editor, jodit, titel, cover, preview, publish
Sources: docs/ADMIN-COMPLETION.md; docs/CONTENT-ENHANCEMENTS.md; PROJECT_STATUS.md

`Beiträge` → `Neu` создаёт неопубликованный draft. Открытая карточка запускает отдельный редактор: заголовок, текст Jodit, краткое описание, автор, обложка/файлы, проект, темы, язык/slug, SEO, platform text, редакционная фаза и publication status. `Vorschau` показывает действующий публичный шаблон в авторизованном режиме без публикации или подсчёта просмотра. `Mit KI einordnen` предлагает классификацию для проверки; предложение не заменяет ручной текст без явного принятия. `Veröffentlichen` делает Website запись публичной и запускает выбранные внешние направления.

## videos — Videos и Shorts
Status: implemented
Keywords: videos, shorts, upload, thumbnail, filter, sort, table, grid, prüfen
Sources: docs/ADMIN-COMPLETION.md; docs/IMPORT-CENTER.md; MASTER-TZ.md §25–27

`Videos` показывает опубликованные и редакционные видео/Shorts с поиском, фильтрами по статусу/типу, сортировкой и постраничным списком; переключение карты/таблицы меняет только вид. Открытие материала ведёт к редактору заголовка, текста, обложки, файлов, тем, SEO, даты и направлений. Техническая проверка показывает размер, длительность и processing state локального оригинала; проверка/импорт не публикует. Замена файла меняет связь выбранного материала, другие использования оригинала сохраняются. Архив/корзина и восстановление не уничтожают оригинал физически.

## podcast — Podcast
Status: implemented
Keywords: podcast, episode, serie, audio, staffel, wav, weiterhören
Sources: docs/CONTENT-ENHANCEMENTS.md; docs/PUBLIC-LIVE.md; PROJECT_STATUS.md

`Podcast` → `Neu` создаёт неопубликованную эпизодную запись на общем SourceRecord. Редактор позволяет выбрать audio/video, файл/обложку, выпуск/сезон, гостя, текст и историческую дату; `Serien` открывает порядок и принадлежность выпусков. Публичный `/podcast` показывает опубликованные эпизоды и продолжение прослушивания для вошедшего пользователя. Browser Studio может сохранить локальный WAV в Media Library и создать неопубликованный Podcast draft; это не автоматическая публикация.

## books-admin — Bücher & PDF
Status: implemented
Keywords: bücher, pdf, shop, neu, upload, cover, leseprobe, price, contents, edition
Sources: docs/DESKTOP-WORKSPACES.md; docs/ADMIN-COMPLETION.md; platform/resources/views/desktop/books-pdf.blade.php

`Bücher & PDF` → `Neu`/загрузка PDF создаёт draft с названием, автором, публичным описанием/оглавлением, обложкой, темами/проектом, ценой, пробным и полным файлом. Отдельный текст полной редакции приватен. `Leseprobe` относится к открытому sample; собственный платный full PDF нельзя одновременно назначить sample. Кнопка создания PDF ставит длительный рендер в очередь. После активирования книга видна на сайте; платная полная редакция выдаётся только по entitlement. Рецензии открываются в native диалоге с фильтрами и approve/reject.

## media-library — Media Library
Status: implemented
Keywords: media, library, datei, upload, original, usage, archive, replace, unlink
Sources: docs/MEDIA-STORAGE.md; docs/IMPORT-CENTER.md; PROJECT_STATUS.md

`Media Library` показывает зарегистрированные оригиналы, metadata, теги/коллекции, usages и processing state. Поиск/фильтр/пагинация помогают найти файл, а открытие inspector показывает его связи. `Upload` добавляет новый оригинал, адресный выбор в редакторе связывает существующий. `Verbindung entfernen` снимает только связь с данным материалом; `Ersetzen` меняет выбранное вложение. `Datei löschen` архивирует запись, `Archiviert` + `Wiederherstellen` возвращает её; массовое физическое уничтожение originals не предоставлено. Публичные бесплатные файлы имеют canonical SHA-256 URL, приватные документы/платные PDF остаются private.

## import-center — Import Center
Status: implemented
Keywords: import, takeout, zip, server, url, rss, review, retry, stop, progress
Sources: docs/IMPORT-CENTER.md; docs/MEDIA-STORAGE.md; docs/ADMIN-COMPLETION.md

`Import Center` → выбор Quelle (локальный файл/ZIP, серверный Google Takeout, HTTPS URL/RSS/Atom, поддерживаемый архив) → запуск. `Import-Vorgänge` показывает stage, текущий файл, прочитанные байты, server message, ошибки и report даже после закрытия окна; `Stop`/`Retry` действуют между безопасными этапами. `Review` позволяет проверить, назначить и принять материал с явным подтверждением; принятие в `ready` не делает Website публикацию. Восьмиэтапный migration assistant отмечает персональный ход работы, но не угадывает legacy структуру или согласие подписчиков. Исходные архивы сохраняются.

## import-review — Review и версии импорта
Status: implemented
Keywords: review, prüfen, übernehmen, import version, löschen, undo
Sources: docs/ADMIN-COMPLETION.md; PROJECT_STATUS.md; platform/resources/views/desktop/import-center.blade.php

Список Review показывает материалы, требующие проверки; открытие ведёт в точный редактор. `Übernehmen` требует подтверждения и устанавливает допустимый редакционный ready state без автоматической Website публикации. Отдельные сохранённые import-version snapshots показывают дату и удаляются по одной после подтверждения; удаление версии не заменяет текущий SourceRecord и не уничтожает исходный media файл. Отменять массовые изменения без явного действия нельзя.

## topics-admin — Themen & Kategorien
Status: implemented
Keywords: thema, kategorien, zuordnen, active, inactive, hierarchy, slug, bulk
Sources: docs/DESKTOP-WORKSPACES.md; PROJECT_STATUS.md; platform/app/Services/Taxonomy.php

`Themen & Kategorien` → `Neu`/открыть термин: имя, slug, активность и иерархическое родительство. Назначение темы материалам выполняется в редакторе или через массовую панель карточки темы: выбрать Beiträge/Videos/Bücher, искать/фильтровать, выбрать страницу, добавить/снять назначения. Права проверяются сервером. `Aktiv` делает тему/категорию доступной публичным фильтрам/полкам, но не публикует материалы; более 100 затронутых назначений обновляются через очередь.

## polls-admin — Umfragen
Status: implemented
Keywords: umfrage, poll, vote, choices, multiple, result, iframe, placement
Sources: docs/ADMIN-COMPLETION.md; platform/public/assets/desktop-polls.js

`Umfragen` → `Neu` создаёт вопрос, варианты, срок, активность, audience, single/multiple выбор, правило видимости результатов и placement. Открытие редактирует не проголосованный poll; после голосов варианты нельзя менять. Admin результат показывает участников, счёт и проценты. Внешний poll — HTTPS ссылка либо sandboxed iframe на строго разрешённом host; само внешнее голосование/результаты контролирует провайдер, не Atapin Media.

## community-admin — Community
Status: implemented
Keywords: community, kommentare, fragen, inbox, moderation, antwort, youtube
Sources: docs/DESKTOP-WORKSPACES.md; docs/ADMIN-COMPLETION.md

`Community` открывает inbox локальных и импортированных вопросов/комментариев с источником, автором, датой, связанным материалом, прочтением и доступностью ответа. Website комментарий можно модерировать/ответить при разрешении; подключённый YouTube поддерживает соответствующий реальный reply API. Неподдерживаемый внешний канал показывает ограничение и переход к оригиналу, не создаёт фиктивную отправку. Синхронизация YouTube комментариев работает постранично в очереди и сохраняет ручные правки/read state.

## newsletter — Newsletter
Status: implemented
Keywords: newsletter, abonnent, subscriber, csv, segment, kampagne, send, cancel
Sources: docs/DESKTOP-WORKSPACES.md; docs/ADMIN-COMPLETION.md

`Newsletter` показывает подтверждённых double-opt-in подписчиков, поиск, сегменты/теги и CSV export по правам. Импорт CSV не подтверждает согласие автоматически. `Neu` создаёт draft кампании, `Vorschau` проверяет содержание, `Senden` требует явного подтверждения; подготовка адресатов/доставка идут в очереди и перепроверяют consent. Отправляемая кампания неизменяема; `Abbrechen` не отзывает email, который уже принят транспортом. Unsubscribe доступен подписчику.

## publishing — Publishing
Status: implemented
Keywords: publishing, website, ziel, destination, retry, activate, deactivate, delete
Sources: docs/PUBLISHING.md; docs/ADMIN-COMPLETION.md

Публикация Website запускается в редакторе материала или Live, выбранные подключённые направления выполняются отдельно через очередь. `Publishing` — реестр внешних передач: фильтр/поиск по цели, статус, remote URL/ID, дата, ошибка. Доступные действия по провайдеру: `Retry`, `Aktivieren`, `Deaktivieren`, explicit `Löschen`. Обычное снятие Website видимости не означает удаления внешней копии; удаление требует отдельного действия. Preview показывает destination-specific текст/медиа до отправки; неподдерживаемые операции не изображаются работающими.

## social-connections — Social Media подключения
Status: implemented
Keywords: social, youtube, x, meta, facebook, instagram, telegram, oauth, verbinden, trennen
Sources: docs/PUBLISHING.md; platform/resources/views/desktop/settings.blade.php

`Einstellungen → Social Media`: YouTube/X показывают требуемые app credentials и OAuth `Konto verbinden` после сохранения, Facebook/Instagram — account/Page ID и authorised Meta token, Telegram — Bot Token/Chat-ID; optional public URL не заменяет credentials. `Verbindung testen` читает сохранённый статус без раскрытия секретов, `Trennen` отключает будущие операции. Telegram Main Mini App требует отдельной регистрации в BotFather, затем bot username/checkbox. TikTok/LinkedIn здесь сохраняют только профильную ссылку, не publishing connector. Права/одобрение API провайдера проверяются отдельно.

## live-studio — Live Studio / OBS
Status: implemented
Keywords: live studio, obs, stream, rtmps, key, preview, disconnect
Sources: docs/PUBLIC-LIVE.md; platform/resources/views/desktop/live-studio.blade.php

`Live Studio` → выбрать/создать событие → сохранить/опубликовать и активировать OBS input → скопировать полный RTMPS адрес в OBS Server, Stream key оставить пустым. OBS сам запускает/останавливает encoder. Server выбирает активное подходящее событие; ежедневная смена key/SSH не нужна. Studio показывает реальный signal, таймер, Website presence, output/recording и публичный preview. `OBS trennen` требует confirmation и live.manage; оно отключает серверное соединение, не управляет OBS на компьютере ведущего. Права и hosting readiness обязательны.

## browser-studio — Live Studio / Browser
Status: implemented
Keywords: browser studio, kamera, mikrofon, bildschirm, live starten, pip, audio, aufnahme
Sources: docs/PUBLIC-LIVE.md; PROJECT_STATUS.md

`Live Studio → Browser-Studio`: явным образом выбрать будущий Live либо создать быстрый с названием, описанием и optional announcement image, подготовить microphone/camera, screen/window или до 20 локальных изображений, PiP и короткий title. Подготовка не передаёт видео. `Live starten` сохраняет/публикует выбранное событие и запускает Browser input; `Stop` завершает session. Изоляция одного input, heartbeat и права publisher защищают эфир. Локальная `Audio aufnehmen` сохраняет WAV, затем `In Media Library speichern` и `Podcast-Entwurf` создают неопубликованный выпуск. Результат в production зависит от настроенного MediaMTX/FFmpeg/WebRTC и должен быть проверен на сервере.

## analytics — Analytics
Status: implemented
Keywords: analytics, analyse, export, csv, besucher, statistik, playback
Sources: docs/DESKTOP-WORKSPACES.md; docs/ADMIN-COMPLETION.md

`Analytics` показывает реальные first-party события сайта: страницы/поиск/download, воспроизведения, регистрации, подтверждённые подписки и оплаченные продажи по валюте. Период/фильтры меняют выборку, раскрытие raw details объясняет агрегацию, `Export` выдаёт CSV при праве analytics. Это не статистика чужих каналов и не число уникальных людей за всё время; внешние значения не придумываются.

## shop-sales — Shop & Verkäufe / Stripe
Status: implemented
Keywords: shop, verkäufe, stripe, payment, test, live, entitlement, refund
Sources: docs/DESKTOP-WORKSPACES.md; docs/ADMIN-COMPLETION.md

`Shop & Verkäufe` показывает книги/продажи в постраничном виде. `Einstellungen → Integrationen → Stripe` выбирает Test/Live, сохраняет зашифрованные keys и webhook secret; `Verbindung testen` делает read-only проверку account. Покупка платного приватного PDF открывает real Checkout; сам возврат с Checkout доступ не выдаёт. Entitlement выдаёт только валидный signed webhook на соответствующую сумму/валюту; полный refund отзывает право. Архивированная книга остаётся доступной покупателю с действующим правом.

## integrations — Integrationen
Status: implemented
Keywords: integration, stripe, google, mailchimp, webhook, readiness, status
Sources: docs/DESKTOP-WORKSPACES.md; docs/ADMIN-COMPLETION.md

`Integrationen` показывает readiness и ссылки на защищённые настройки. Работающие publishing connectors описаны в `Social Media`, Stripe — отдельно. Сохранение общих Google/Mailchimp/Webhook полей само по себе не создаёт API adapter; KAI должен назвать это ограничение. Secret values никогда не возвращаются в dashboard/ответ.

## settings — Einstellungen, права и система
Status: implemented
Keywords: einstellungen, settings, benutzer, rollen, rechte, ki, desktop, system, speichern
Sources: docs/ADMIN-COMPLETION.md; platform/resources/views/desktop/settings.blade.php

`Einstellungen` доступен с соответствующим permission: вкладки Desktop/appearance, KI provider/model/consent, Social Media, Publishing, Integrationen, Benutzerrechte и System. Переключение вкладки лишь открывает форму; значения вступают в силу после `Speichern`. Создание пользователя/роли и изменение прав требует users.manage, не прячется только CSS. API key/token хранятся encrypted и не должны попадать в вопрос KAI. `Systemstatus`/`/desktop/health` даёт приватную проверку runtime, БД, storage, cache, queue/scheduler без секретов.

## kai-admin — KAI для администратора
Status: implemented
Keywords: kai, ki dashboard, hilfe, frage, knowledge, antwort, tokens, history
Sources: platform/public/assets/desktop-ai-dashboard.js; platform/app/Services/DesktopAi.php; docs/KAI-ADMIN-MANUAL.md

Маленькая кнопка `✦` на нижней панели открывает помощь; вопрос о кнопке, модуле или workflow отправляется сразу, результат/ошибка появляется в том же окне без worker queue. `KI-Dashboard` показывает приватную историю запросов, статус, model и фактические tokens; estimated cost отсутствует без надёжного тарифа. KAI читает таблицу `kai_knowledge_entries`, куда этот документ индексируется раз в шесть часов или вручную `kai:sync-knowledge`. Он не выполняет действий, не видит приватные credentials и обязан различать implemented/planned/unverified. Редакционные AI предложения отдельно применяются пользователем после проверки stale version.

## files-planned — Отдельный файловый workspace Dateien
Status: planned
Keywords: dateien, file explorer, folder, export, storage browser
Sources: README.md; MASTER-TZ.md §24; platform/resources/views/desktop.blade.php

Отдельное приложение `Dateien` предусмотрено как удобный вид папок/коллекций, storage usage, recent uploads и import/export. В текущем меню Desktop такой программы нет; файлы доступны через `Media Library` и `Import Center`. KAI должен предложить эти работающие пути и назвать `Dateien` будущим workspace.

## video-processing-planned — Дополнительная обработка видео
Status: planned
Keywords: transcoding, preview generation, thumbnail, audio extraction, video processor
Sources: MASTER-TZ.md §27; docs/ADMIN-COMPLETION.md

ТЗ предусматривает полный VideoProcessorInterface с thumbnails, transcoding, previews, resolution detection и optional audio extraction. Часть технических фактов/адаптаций уже существует, но универсальный набор этих кнопок/операций как завершённый Video Processing Layer ещё не подтверждён. Не обещайте нажатие отсутствующей кнопки; тяжёлые операции должны идти через очередь.

## platform-editor-planned — Индивидуальные внешние редакторы
Status: planned
Keywords: platform-specific editor, schedule, instagram, tiktok, caption, hashtags
Sources: README.md; MASTER-TZ.md §33; docs/PUBLISHING.md

ТЗ предусматривает индивидуальные поля Title/Description/Caption/Hashtags/Thumbnail/Visibility/Publish Time для каждой внешней платформы и отдельные редакторы. Текущий Editor/Preview уже имеет часть destination metadata и AI suggestions, но полный индивидуальный platform-specific редактор и schedule для каждого API пока не реализованы. `Publishing` показывает только поддерживаемые действия.

## inbound-connectors-planned — Остальные входящие каналы
Status: planned
Keywords: facebook comments, instagram messages, telegram inbox, x replies, inbound sync
Sources: MASTER-TZ.md §31–35; docs/ADMIN-COMPLETION.md

Единый Community inbox для разных провайдеров предусмотрен. Реальный синхронизатор YouTube комментариев и локальные сообщения работают; входящие Facebook/Instagram/Telegram/X adapters и их кнопки не подтверждены как реализованные. KAI должен сказать, что импорт этих сообщений предусмотрен, но пока не работает в этой установке.

## tiktok-linkedin-planned — TikTok и LinkedIn publishing
Status: planned
Keywords: tiktok, linkedin, direct post, publish, adapter
Sources: docs/PUBLISHING.md; docs/ADMIN-COMPLETION.md

В архитектуре допускаются новые destination adapters, но TikTok unattended Direct Post и LinkedIn publishing в текущей установке отсутствуют. В настройках возможно только сохранить публичную профильную ссылку. Заполнение ссылки не создаёт connector; KAI не должен говорить «подключить и публиковать».

## external-analytics-planned — Статистика внешних платформ
Status: planned
Keywords: youtube analytics, instagram statistik, facebook metrics, external reports
Sources: README.md; docs/ADMIN-COMPLETION.md; docs/DESKTOP-WORKSPACES.md

Импорт и объединение внешней статистики предусмотрены connector architecture, но текущий `Analytics` показывает только first-party Website факты. Значения YouTube/Instagram/Facebook не загружаются из их API и не должны выдаваться за реальные.

## live-advanced-planned — Дополнительные Live сцены и гости
Status: planned
Keywords: turn, guest, mixer, scene, lower third, replay merge, mobile live
Sources: MASTER-TZ.md §40–42; docs/PUBLIC-LIVE.md; docs/ADMIN-COMPLETION.md

В ТЗ есть guests, TURN, полноценный Audio Mixer, scenes/layouts, media-file inserts, overlays/lower thirds, mobile quick mode и server replay merge. Browser Studio уже имеет camera/screen/images/PiP/title/local WAV, но перечисленные расширенные возможности не завершены. KAI должен объяснять текущий путь OBS/Browser и явно говорить, что нужная расширенная кнопка предусмотрена, но отсутствует.

## restricted-media-planned — Платный или закрытый video original
Status: planned
Keywords: protected video, subscriber access, private original, migration
Sources: docs/MEDIA-STORAGE.md; docs/ADMIN-COMPLETION.md

Доступ к платному PDF реализован; общий закрытый режим для бесплатных canonical MP4/audio/image originals пока не реализован. Файлы прямо публичны по `/media/<sha256>`, поэтому скрытая страница не защищает байты. Перенос существующих original files требует отдельного согласованного плана совместимости и backup; кнопки «закрыть доступ к видео» сейчас нет.

## central-control-planned — Central Control и лицензирование
Status: planned
Keywords: central control, licensing, remote update, support access, vendor
Sources: MASTER-TZ.md; docs/ADMIN-COMPLETION.md; README.md

ТЗ предусматривает commercial licensing, remote update manager, Central Control и support-access service. Локальная проверка health уже работает, но удалённого control server и пользовательских кнопок для этих функций в этой установке нет. KAI обязан назвать их будущей vendor-фазой, а не доступным меню.

## browser-live-production-unverified — Browser Live на production
Status: unverified
Keywords: browser live, whip, mediamtx, plesk, production, real stream
Sources: docs/PUBLIC-LIVE.md; docs/ADMIN-COMPLETION.md; PROJECT_STATUS.md

Browser Studio, WHIP bridge, WAV и protected server settings реализованы в коде. Реальный Linux/Plesk media transport, FFmpeg normalization, внешние outputs и конечное Website playback на сервере ещё требуют smoke test владельца. KAI может объяснить интерфейс, но не должен утверждать, что Browser Live уже подтверждён как работающий production эфир.

## external-api-unverified — Реальные социальные API
Status: unverified
Keywords: youtube oauth, meta api, x api, telegram bot, provider approval, live publication
Sources: docs/PUBLISHING.md; docs/ADMIN-COMPLETION.md; PROJECT_STATUS.md

В коде есть YouTube/Facebook/Instagram/Telegram/X connectors, OAuth и проверка статусов. Реальные app credentials, права/квоты провайдеров и публикации в production должны быть сохранены/проверены владельцем. Без этого KAI не должен говорить «канал подключён» или «публикация проверена»; наличие адаптера в коде — отдельный факт.

## payment-mail-unverified — Реальная оплата и доставка писем
Status: unverified
Keywords: stripe payment, checkout, smtp, sendmail, mail delivery, webhook
Sources: docs/DESKTOP-WORKSPACES.md; docs/ADMIN-COMPLETION.md; PROJECT_STATUS.md

Checkout/webhook entitlement, Newsletter и Live reminders реализованы, но реальные Stripe mode credentials, signed webhook и доставка через конкретный Plesk mail transport не подтверждаются локальными тестами. KAI описывает настройки и последовательность, однако не утверждает, что платёж или письмо уже прошли на production без фактической проверки.
