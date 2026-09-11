# Atapin Media

> **Первый рабочий модуль: [приём файлов](./intake/README.md).** Отдельная страница с одним общим паролем, без учётных записей, для пополнения архива до запуска основной платформы. В инструкции — команды установки на Debian/Plesk, настройки PHP, хранение оригиналов и обновление через Git. Этот предварительный этап согласован отдельно от основного roadmap.

> **Второй модуль: [сбор публичного YouTube-архива](./youtube/README.md).** Видео, Shorts, плейлисты, Beiträge, описания, комментарии и субтитры. Фоновый запуск на Debian/Plesk; данные хранятся вне сайта и Git для последующей обработки.

**Independent self-hosted Media Platform с публичным сайтом и рабочей средой Media Desktop.**

Atapin Media — универсальный продукт для авторов, издателей, проповедников, преподавателей, блогеров и небольших медиакоманд. Это не SaaS и не multi-tenant панель: каждый клиент получает отдельную самостоятельную установку со своим доменом, данными, пользователями, файлами, интеграциями и branding.

Первый реальный deployment проекта — **Manna Vom Himmel**.

Повторяющиеся локальные операции запускаются через [`tools/manna.ps1`](./tools/README.md), чтобы не тратить время на ручные действия или Codex.

- **Основной домен:** `mannavomhimmel.de`
- **Алиас:** `manna-vom-himmel.de`
- Алиас делает постоянный `301` redirect на соответствующий URL канонического домена.
- `mannavomhimmel.de` используется для canonical URLs, SEO, sitemap, OpenGraph, share links, QR-кодов и внутренних абсолютных ссылок.

> Главная идея: владелец должен выполнять основную медиаработу в одном месте — создать материал, отредактировать, обработать с KI, сохранить, запланировать, опубликовать, провести Live и ответить аудитории.

---

## Что мы создаём

Каждая установка Atapin Media имеет собственные:

- домен и branding;
- Laravel deployment и базу данных;
- пользователей, роли и подписчиков;
- Media Library и storage;
- Public Website;
- Media Desktop;
- Social-Media подключения;
- AI-, mail- и payment credentials;
- категории, темы, navigation и настройки;
- search/index, queue, scheduler, cache и audit log.

Внешние платформы — YouTube, Instagram, TikTok, Facebook, Telegram и другие — рассматриваются как **каналы распространения и коммуникации**. Собственная Media Platform остаётся **Source of Truth**, архивом контента, базой подписчиков, Community Base, Publishing Center и Live Center.

---

## Две стороны продукта

### Public Website

Лицевая часть для читателей, зрителей и слушателей. Она не выглядит как админка и строится из настраиваемых публичных модулей.

Для Manna Vom Himmel утверждена основная навигация:

- **Startseite**
- **Beiträge**
- **Videos**
- **Bücher & PDF**
- **Live**
- **Podcast**
- **Community**
- **Themen**
- **Über uns**

Дополнительно: глобальный поиск, Anmeldung/Account, Newsletter, сохранённые материалы и персональное продолжение просмотра/прослушивания/чтения там, где эта функция реализована.

Публичный сайт должен быть живым: активный Livestream автоматически отображается как заметное состояние сайта; активный Poll может появляться в заданных layout slots; контент разных типов связывается через Themen, Series, Collections и Projects.

### Media Desktop

Административная часть — не обычный CRUD Dashboard, а **creator workspace / виртуальный медиа-компьютер**.

Навигация:

**Workspace**

- Desktop
- Projekte
- Aufgaben
- Kalender

**Inhalte**

- Beiträge
- Videos
- Bücher & PDF
- Media Library

**Community**

- Community
- Subscribers

**Tools**

- Live Studio
- Publishing
- KI-Assistent
- Analytics

**Weitere**

- Import Center
- Dateien
- Integrationen
- Einstellungen

Пункты зависят от permissions, группы могут сворачиваться.

---

## Media Desktop — главный рабочий экран

Desktop должен сразу отвечать на вопросы:

- над чем я сейчас работаю;
- что будет опубликовано следующим;
- какие uploads/processing jobs идут;
- запланирован или уже идёт Live;
- что пишут люди на сайте и внешних платформах;
- есть ли ошибки публикации или интеграций;
- какие задачи требуют внимания;
- что можно сделать прямо сейчас.

Ключевой блок — **Weiterarbeiten / Aktuelles Projekt**. Пользователь должен продолжить работу с того места, где остановился, без поиска нужного модуля.

Widgets:

- Nächste Veröffentlichung;
- Live starten / Live Status;
- Uploads / Processing;
- Neue Videos;
- Letzte Beiträge;
- Kommentare / Fragen;
- Subscribers / Community growth;
- Social Status;
- Speicher;
- Systemstatus / Fehler;
- KI-Vorschläge.

Widgets должны быть настраиваемыми: show/hide, order, position и при необходимости size preset.

---

## Основные модули

### Projekte

Связывает одну медиаработу или серию с несколькими сущностями: Beitrag, Video, Podcast, PDF, Social Posts, Live, Assets и Tasks. Проект показывает workflow, текущую фазу, следующую задачу, сроки и ответственных.

### Aufgaben

Рабочие задачи с режимами Board, Liste и Meine Aufgaben. Базовые состояния: Offen, In Arbeit, Warten, Erledigt.

### Kalender / Planung

Единый редакционный календарь: Monat, Woche, Liste. В нём видны Beiträge, Videos, Shorts, Podcasts, PDF, Social Posts, Live и при необходимости team tasks/events.

### Beiträge

Полноценный Rich Content Editor с Cover, Author, Themen/Kategorien, Tags, Project, SEO, Access Rules, scheduling и generated PDF.

### Videos

Creator-Studio-подобная, но собственная админка: thumbnails, title, status, platforms, playlists/series, project, publish date, views, comments, duration, editor, bulk actions, filters, search, sorting и pagination.

### Podcast

Podcast Series и Episodes, standalone Audio или Audio, полученное из Video; web player, transcript, metadata, external destinations и public `Weiterhören`.

### Bücher & PDF

Книги, брошюры, PDF и документы с Cover, Author, Preview, Download, external shop link, free/protected/paid access и привязкой к Themen/Projects.

### Media Library

Единый каталог Video, Audio, Image, PDF, Document, Thumbnail, Recording и других media objects. Хранит metadata, stable references, tags, collections, usage references, processing state и storage location.

### Import Center

Импорт существующего архива из DOCX/TXT/HTML/PDF, локальных media files, ZIP, URL/RSS и внешних платформ через connectors. Импорт не публикуется автоматически: материал проходит Review/Zuordnung.

### Publishing

Единый workspace для публикации на Website и внешние destinations. Для каждой платформы можно иметь собственные title, description, caption, hashtags, thumbnail, visibility, schedule и platform-specific options.

### Community

Собственные Kommentare, Fragen, Diskussionen, Live Questions, Polls и Moderation плюс **Unified Community Inbox** для поддерживаемых внешних платформ. Всегда видно источник сообщения. Если connector умеет reply, ответ отправляется прямо из Media Desktop.

### Subscribers

Собственная база аудитории: E-Mail Subscription, Accounts, Double Opt-In, Preferences, Segments, Notifications и Unsubscribe.

### Live Studio

Preview, stream status, viewer count, destinations, recording, combined chat/questions, Start/Stop и дальнейшее расширение сценами, Audio Mixer, Run of Show, guests, Q&A, overlays и Browser Live. Профессиональный способ первого этапа — OBS/RTMP/SRT.

### KI-Assistent

KI встроен в workflows, а не существует только как отдельный chat. Он помогает с titles, summaries, SEO, descriptions, hashtags, platform-specific text, content transformation, import cleanup и предложениями ответов на комментарии. Результат показывается пользователю до применения.

### Analytics

Собственная статистика сайта и агрегированные внешние показатели там, где connector/API это разрешает. Own-platform analytics и external analytics должны различаться.

---

## Утверждённый UI Manna Vom Himmel

**Единственный утверждённый визуальный источник находится в:**

[`UI/approved/`](./UI/approved/)

В репозитории больше не должно быть альтернативных UI-концептов, старых preview-серий и конкурирующих вариантов дизайна.

Состав утверждённого reference set:

```text
UI/approved/
├── README.md
├── CODEX-REFERENCE.md
├── design-system/
│   └── master-ui-kit.png
└── frontend-gold-b/
    ├── preview.html
    ├── 01-start.png
    ├── 02-videos.png
    ├── 03-video-detail.png
    ├── 04-beitraege.png
    ├── 05-beitrag-detail.png
    ├── 06-buecher.png
    ├── 07-buch-detail.png
    ├── 08-live.png
    ├── 09-podcast.png
    └── 10-community.png
```

### Правило для Codex

- `MASTER-TZ.md` определяет **что система должна делать**.
- `UI/approved/` определяет **как Manna Vom Himmel должен выглядеть**.
- `master-ui-kit.png` определяет visual language, typography, palette, cards, buttons, statuses и общие компоненты.
- `frontend-gold-b/` определяет approved public-page composition и визуальную иерархию.
- Если для нового экрана нет отдельного screenshot, его нужно спроектировать из тех же компонентов и tokens — **не придумывать новый стиль**.
- Текст-заглушка внутри mockup не является business requirement, если она противоречит `MASTER-TZ.md` или `MANNA-VOM-HIMMEL.md`.
- Реальная реализация должна быть responsive, semantic и data-driven; screenshot — визуальный reference, а не картинка, которую нужно буквально вставить в страницу.

Для Manna утверждён visual language:

- Logo/Mark: стилизованная раскрытая книга / `M` + свет сверху;
- Dunkelblau / Navy;
- Gold;
- Himmelblau;
- Cremeweiß;
- Playfair Display для public editorial headings;
- Inter для интерфейса, forms, tables и metadata;
- Gold как основной public CTA;
- Blue/Navy как primary admin action;
- спокойные светлые cards и editorial public presentation.

---

## Архитектурные принципы

- Laravel backend.
- API/Service-first business logic.
- Thin Controllers.
- Actions / Services / Jobs / Policies / Events / Listeners / DTOs where useful.
- Queue для тяжёлых операций.
- Laravel Cache abstraction, Redis-ready.
- Laravel Filesystem abstraction, Local + S3-compatible storage.
- Search abstraction через `SearchProviderInterface`, Laravel Scout-compatible.
- Внешние сервисы только через Interfaces/Adapters.
- Responsive UI + PWA foundation.
- 2FA-ready auth, granular RBAC, audit log, encrypted secrets, signed URLs и secure protected downloads.

Ключевые abstraction layers:

- `AiProviderInterface`
- `MediaConnectorInterface`
- `VideoProcessorInterface`
- `StreamProviderInterface`
- `SearchProviderInterface`
- `PaymentProviderInterface`
- `NotificationProviderInterface`
- Storage abstraction
- `UpdateProviderInterface`
- `LicenseProviderInterface`

---

## Независимость установки

Central Control, AI provider, YouTube или любой другой внешний сервис **не является runtime dependency**.

При недоступности внешнего сервиса собственные Public Website, Media Desktop, users, media library, local search, protected content, queue и scheduler продолжают работать в пределах доступного локального функционала.

Central Control в будущем отвечает только за лицензирование, support access, update notifications, health monitoring и installation management. Недоступность Central Control сама по себе не блокирует клиентскую установку.

---

## Multilanguage

Core мультиязычный с первого дня: UI strings только через translation keys.

Manna Vom Himmel — немецкоязычный deployment:

```text
default_locale = de
enabled_locales = [de]
```

Core не должен зависеть от немецкого языка. Контент архитектурно может иметь `locale` и optional `translation_group_id`.

---

## Manna Vom Himmel не является Core

Нельзя использовать `MannaVomHimmel` в именах generic Core-классов.

Manna — только первая клиентская конфигурация: German locale, domains, branding, design tokens, categories/topics, navigation, existing content, social accounts, PDF template, newsletter и public homepage composition.

Подробное популярное описание первого клиента находится в [`MANNA-VOM-HIMMEL.md`](./MANNA-VOM-HIMMEL.md).

---

## Roadmap

1. **Foundation** — auth, roles, settings, i18n, theme tokens, Media Desktop shell, storage, Media Library, queue/cache/search/audit/API foundation.
2. **Workflow** — Projekte, Aufgaben, universal editor/workflow shell, Kalender, Weiterarbeiten.
3. **Content** — Beiträge, Bücher/PDF, Themen, Tags, Collections, imports, PDF generator, public content pages.
4. **Media** — Video upload/processing, Video Studio, Podcast, Playlists/Series.
5. **Publishing & Integrations** — Publishing Workspace, YouTube first, затем другие connectors через adapter layer.
6. **KI** — contextual assistant и platform-specific preparation.
7. **Audience** — Subscribers, Accounts, Community, unified inbox, notifications, Polls.
8. **Live** — Live Studio, OBS/RTMP/SRT, recording, multistream architecture, Browser Live preparation.
9. **Monetization** — Access Policies, Entitlements, subscriptions, purchases, donations, protected downloads.
10. **Mobile & Vendor Infrastructure** — PWA/mobile workflows; Central Control/licensing/remote updates только после стабильности основной платформы.

Не реализовывать весь roadmap одним проходом.

---

## Основные документы

- [`MASTER-TZ.md`](./MASTER-TZ.md) — главный технический и продуктовый specification.
- [`MANNA-VOM-HIMMEL.md`](./MANNA-VOM-HIMMEL.md) — подробное немецкое описание первого deployment.
- [`UI/approved/`](./UI/approved/) — единственный approved visual reference.

---

## Статус

Проект находится на этапе architecture/foundation. Функциональная концепция Atapin Media, первая установка Manna Vom Himmel и её утверждённый UI определены. Дальнейшая разработка должна опираться на `MASTER-TZ.md` и `UI/approved/` без возвращения к удалённым альтернативным дизайн-концептам.
