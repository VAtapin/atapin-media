# MASTER-ТЗ — Atapin Media

**Independent Media Platform / Media Desktop**  
Актуальная спецификация продукта и разработки.

> Этот документ является главным источником требований к функциональности, архитектуре и поведению системы. Для первого клиента **Manna Vom Himmel** единственным визуальным источником является [`UI/approved/`](./UI/approved/).

---

# 1. Цель продукта

Создать универсальную **self-hosted single-tenant Media Platform** для авторов, издателей, проповедников, преподавателей, блогеров, небольших редакций и других создателей контента.

Atapin Media — это **не SaaS и не multi-tenant система**.

Каждый клиент получает отдельную самостоятельную установку:

- собственный домен;
- отдельный Laravel deployment;
- отдельную базу данных;
- собственное файловое/объектное хранилище;
- собственных пользователей, роли и подписчиков;
- собственные Social-Media Accounts;
- собственные AI/mail/payment credentials;
- собственный branding и design tokens;
- собственные categories, topics, navigation и настройки;
- собственные search index, queue, scheduler, cache и backups.

Платформа состоит из двух взаимосвязанных частей:

1. **Public Website** — публичная медиаплощадка для читателей, зрителей и слушателей;
2. **Media Desktop** — рабочая среда владельца, редакторов и медиакоманды.

Media Platform должна быть главным:

- **Source of Truth**;
- Content Archive;
- Media Library;
- Publishing Center;
- Live Center;
- Community Base;
- Subscriber Base;
- Search Base.

Внешние платформы являются каналами распространения и коммуникации, но не основным местом хранения собственного контента.

Первая реальная установка продукта — **Manna Vom Himmel**.

---

# 2. Первый клиент: Manna Vom Himmel

Manna Vom Himmel — первый deployment Atapin Media, но **не часть Core**.

## 2.1 Домены

```text
Primary / Canonical: mannavomhimmel.de
Alias:               manna-vom-himmel.de
```

Обязательное поведение:

- `mannavomhimmel.de` — единственный канонический публичный домен;
- `manna-vom-himmel.de` — только alias;
- любой URL alias-домена делает permanent `301` redirect на тот же path канонического домена;
- canonical URLs используют `mannavomhimmel.de`;
- sitemap и sitemap index содержат только `mannavomhimmel.de`;
- OpenGraph `og:url` использует только канонический домен;
- внутренние абсолютные ссылки используют только канонический домен;
- share links, QR codes и generated PDF links используют только канонический домен;
- alias не должен создавать индексируемые копии страниц.

## 2.2 Язык

```text
default_locale = de
enabled_locales = [de]
fallback_locale = de
```

Публичная часть и Media Desktop Manna Vom Himmel — немецкоязычные.

Core Atapin Media не должен зависеть от немецкого языка.

## 2.3 Branding

Manna-specific:

- logo/mark;
- colors;
- typography;
- navigation;
- categories/topics;
- homepage blocks;
- PDF templates;
- social accounts;
- newsletter settings;
- existing content import configuration.

Нельзя использовать `MannaVomHimmel` в generic Core class/service/model names.

---

# 3. Утверждённый UI — обязательное правило

## 3.1 Единственный visual source of truth

В репозитории существует **только один утверждённый набор визуальных материалов**:

```text
UI/approved/
```

Другие UI-концепты, старые preview-серии и альтернативные дизайны удалены и **не должны восстанавливаться или использоваться как reference** без отдельного решения владельца проекта.

Состав утверждённого набора:

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

## 3.2 Приоритет источников

При реализации Manna Vom Himmel действует следующий приоритет:

1. `MASTER-TZ.md` — функциональность, архитектура, поведение, permissions, workflows и acceptance criteria;
2. `MANNA-VOM-HIMMEL.md` — клиентская структура, смысл и публичное описание первой установки;
3. `UI/approved/` — внешний вид, visual hierarchy, spacing, composition, palette, typography и style language.

Если текст-заглушка на mockup противоречит ТЗ, приоритет имеет ТЗ.

Если в ТЗ описан новый экран, которого нет среди screenshots, он создаётся **в том же design system**, используя утверждённые patterns. Новый самостоятельный стиль придумывать нельзя.

## 3.3 Что значит «реализовать по screenshot»

Screenshot — не bitmap, который вставляется на страницу. Реализация должна быть:

- semantic HTML/UI;
- responsive;
- data-driven;
- reusable-component based;
- accessible where practical;
- адаптирована под реальные данные;
- устойчива к длинным заголовкам, пустым состояниям и большим спискам.

Можно адаптировать layout к mobile/tablet, сохраняя visual language и иерархию.

---

# 4. Design System Manna Vom Himmel

Для первого клиента утверждён визуальный язык:

- Logo/Mark: стилизованная раскрытая книга / буква `M` + свет сверху;
- **Dunkelblau / Navy** — основной тёмный цвет;
- **Gold** — брендовый public accent и public primary CTA;
- **Himmelblau / Blue** — interactive/admin accent;
- **Cremeweiß** — основной public background/surface;
- Green — success;
- Orange — warning;
- Red — danger/live;
- **Playfair Display** — public editorial headings;
- **Inter** — интерфейс, body text, forms, tables и metadata.

Public Website должен быть более editorial, эмоциональным и спокойным.

Media Desktop должен выглядеть родственно, но быть более плотным и функциональным.

Все новые компоненты обязаны использовать единые design tokens:

- colors;
- typography;
- spacing;
- border radii;
- shadows;
- icon sizing;
- component states;
- table/form/button patterns;
- responsive breakpoints.

---

# 5. Public Website

Public Website — самостоятельная лицевая часть продукта.

Core не задаёт жёсткое меню для всех клиентов, но предоставляет building blocks для:

- Home;
- Beiträge;
- Videos;
- Bücher & PDF;
- Live;
- Podcast;
- Community;
- Themen/Categories;
- Search;
- Author;
- Subscription;
- Account;
- About/Content Pages.

## 5.1 Navigation Manna Vom Himmel

```text
Startseite
Beiträge
Videos
Bücher & PDF
Live
Podcast
Community
Themen
Über uns
```

Также доступны:

- Global Search;
- Anmelden / Account;
- Newsletter.

## 5.2 Dynamic public states

Публичный сайт должен реагировать на текущие события.

Если идёт Live:

- появляется глобальный Live-Hinweis/Live-Bar;
- CTA ведёт на active Live page;
- homepage может поднимать active Live выше остальных блоков.

Если активен Poll:

- он может отображаться на Startseite;
- в Live;
- в Community;
- в других разрешённых layout slots.

Если контент новый/featured, соответствующие blocks обновляются автоматически из данных платформы.

---

# 6. Public Startseite

Startseite должна быстро отвечать посетителю:

- что нового;
- что сейчас live;
- что читать;
- что смотреть;
- что слушать;
- где участвовать.

Поддержать blocks:

- Hero / Featured Content;
- Active Live;
- Current Poll;
- Beitrag des Tages / Featured Beitrag;
- Neue Videos;
- Bücher & PDF;
- Podcast / latest episode;
- Themen;
- Community Highlights;
- Next Live;
- Newsletter.

Порядок и наличие blocks должны быть configurable для конкретной установки.

---

# 7. Public Beiträge

Нужны:

- Beiträge overview;
- featured Beitrag;
- latest/popular Beiträge;
- topic/category filters;
- author filters where needed;
- search;
- Einzelbeitrag;
- cover;
- author/date/reading time;
- related content;
- comments;
- sharing;
- generated PDF where allowed;
- SEO metadata.

Generated PDF может включать:

- Logo;
- Titel;
- Autor;
- Datum;
- Content;
- canonical URL;
- QR Code;
- Footer;
- tenant branding.

---

# 8. Public Videos

Нужны:

- Videos overview;
- featured video;
- Neue Videos;
- Popular Videos;
- Series/Playlists;
- Themen filters;
- Video Detail;
- player;
- description;
- duration;
- author/speaker;
- publication date;
- transcript where available;
- related content;
- comments/questions;
- share;
- next Live widget;
- Continue Watching for logged-in users where implemented.

---

# 9. Public Bücher & PDF / Bibliothek

Bibliothek — полноценный public module, не просто folder с downloads.

Поддержать:

- books;
- e-books;
- PDFs;
- brochures;
- studies;
- worksheets;
- flyers;
- other documents.

Overview:

- featured;
- new;
- popular;
- topic/category filters;
- free/paid/protected state;
- search.

Detail:

- cover;
- title/subtitle;
- author;
- description;
- preview;
- download/access/purchase;
- external shop link;
- related content;
- access state.

Personal area может хранить saved items и reading progress, если формат это позволяет.

---

# 10. Public Podcast

Podcast — отдельный полноценный content type.

Entities:

- Podcast Series;
- Episode.

Public UI:

- overview;
- featured/latest episode;
- series;
- episode detail;
- web audio player;
- duration;
- cover;
- description;
- transcript;
- topic/category;
- `Weiterhören`;
- external podcast links where configured.

Podcast может использовать standalone Audio или Audio, полученное из Video.

---

# 11. Public Live

Нужны:

- Live overview;
- active Live page;
- player;
- Live status;
- viewer count where available;
- own-site Live Chat;
- questions;
- current Poll;
- share;
- upcoming Live events;
- reminders;
- schedule;
- past livestreams/replays.

После завершения Live запись должна иметь путь:

```text
Recording -> Processing -> Media Library -> Video / Replay
```

---

# 12. Public Community

Community — не обязательная полноценная social network. Главная задача — общение вокруг материалов и тем.

Поддержать:

- discussions;
- questions;
- answers;
- comments;
- Polls;
- Live Questions;
- reactions where enabled;
- topic filters;
- moderation;
- reports;
- notifications;
- account-required actions.

---

# 13. Themen / Cross-content Discovery

`Themen` — отдельный discovery layer.

Одна тема может собирать одновременно:

- Beitrag;
- Video;
- Book/PDF;
- Podcast;
- Live/Replay;
- Community Discussion.

Topic page не должна быть простым alias категории одного content type.

---

# 14. Search

Search — обязательный Core Module.

Система должна масштабироваться на:

- тысячи videos;
- тысячи Beiträge;
- тысячи books/PDF;
- тысячи podcast episodes;
- десятки тысяч media records.

Обычного SQL `LIKE` недостаточно.

Создать:

`SearchProviderInterface`

Laravel Scout-compatible abstraction.

Индексировать:

- title;
- description/excerpt;
- content;
- tags;
- categories/topics;
- author;
- transcript;
- video metadata;
- podcast metadata;
- document metadata.

Поддержать:

- Full Reindex;
- Incremental Index;
- Single Item Reindex.

Public Search и Media Desktop Search могут использовать общий индекс с разными permission filters.

---

# 15. Media Desktop — UX concept

Media Desktop — **creator workspace / virtual media computer**, а не CRUD Dashboard.

Главный принцип:

> пользователь должен видеть, что происходит сейчас, продолжить текущую работу и взаимодействовать с аудиторией без постоянного перехода во внешние платформы.

## 15.1 Sidebar

### Workspace

- Desktop
- Projekte
- Aufgaben
- Kalender

### Inhalte

- Beiträge
- Videos
- Bücher & PDF
- Media Library

### Community

- Community
- Subscribers

### Tools

- Live Studio
- Publishing
- KI-Assistent
- Analytics

### Weitere

- Import Center
- Dateien
- Integrationen
- Einstellungen

Группы sidebar collapsible.

Видимость пунктов определяется permissions.

---

# 16. Desktop Home

Главный Desktop показывает состояние работы, а не просто ссылки на модули.

## 16.1 Quick Actions

- Neuer Beitrag;
- Neues Video;
- Live starten;
- PDF hochladen;
- Veröffentlichen.

## 16.2 Weiterarbeiten / Aktuelles Projekt

Центральный рабочий block:

- current/recent project;
- related content;
- cover/thumbnail;
- current stage;
- progress;
- next meaningful action;
- `Weiter bearbeiten`;
- preview;
- contextual KI;
- destinations where relevant.

Допускается 2–3 recent work items вместо одного.

## 16.3 Widgets

- Nächste Veröffentlichung;
- Live starten / Live Status;
- Uploads / Processing;
- Neue Videos;
- Letzte Beiträge;
- Kommentare / Fragen;
- Subscribers / Community Growth;
- Social Status;
- Speicher;
- Systemstatus / Fehler;
- KI-Vorschläge.

Widgets configurable:

- show/hide;
- order;
- position;
- optional size presets.

Обычный Editor/Mediengestalter не должен постоянно видеть низкоуровневые технические ошибки. Owner/Admin может видеть расширенный Systemstatus.

---

# 17. Roles & Permissions

Минимальные роли:

- Owner;
- Administrator;
- Mediengestalter;
- Editor;
- Moderator;
- Support.

`Mediengestalter` — основной рабочий пользователь.

RBAC должен быть granular. Не ограничиваться проверкой имени роли.

Permissions должны покрывать:

- view/edit/publish content;
- media upload/delete;
- live start/stop;
- social publishing;
- comment reply/moderation;
- subscriber access;
- analytics;
- settings;
- integrations;
- payment/access management;
- support access.

---

# 18. Projekte

`Projekt` объединяет связанную медиаработу.

Один Projekt может содержать:

- Beitrag;
- Video;
- Podcast Episode;
- Book/PDF;
- Social Posts;
- Live;
- Media Assets;
- Tasks.

Fields:

- title;
- description;
- type;
- status;
- owner/responsible;
- team;
- start date;
- due date;
- tags;
- cover;
- workflow/progress;
- current phase;
- next action.

UI:

- table/list;
- filters;
- progress;
- deadline;
- responsible;
- detail;
- timeline/workflow.

---

# 19. Aufgaben

Views:

- Board;
- Liste;
- Meine Aufgaben.

Base statuses:

- Offen;
- In Arbeit;
- Warten;
- Erledigt.

Fields:

- title;
- description;
- project;
- related content;
- assignee;
- due date/time;
- priority;
- status;
- checklist;
- tags.

Filters:

- project;
- content type;
- user;
- priority;
- status;
- due date.

---

# 20. Universal Content Editor / Workflow

Нужен единый UX pattern для создания и подготовки content.

Editor должен уметь объединять:

- main content;
- Cover/Thumbnail;
- Author;
- Topics/Categories;
- Tags;
- Project;
- Access Rules;
- SEO;
- KI tools;
- Preview;
- platform-specific metadata;
- schedule;
- destinations.

Workflow stages зависят от content type.

Video example:

```text
Idee -> Skript -> Produktion -> Feinschliff -> Veröffentlichung
```

Beitrag example:

```text
Entwurf -> Redaktion -> Medien -> SEO/KI -> Vorschau -> Veröffentlichung
```

Workflow stage и publication status — разные понятия и не должны искусственно объединяться.

---

# 21. Beiträge Admin

Поддержать:

- manual creation;
- DOCX import;
- TXT;
- Copy/Paste;
- HTML import;
- Bulk Import.

Minimum fields:

- title;
- slug;
- excerpt;
- content;
- cover;
- author;
- locale;
- publish date;
- status;
- categories/topics;
- tags;
- project;
- SEO;
- access rules;
- PDF/download rules.

Statuses:

- Draft;
- In Review / In Bearbeitung;
- Scheduled;
- Published;
- Archived.

Использовать полноценный Rich Content Editor.

---

# 22. Bücher & Dokumente Admin

Fields:

- title;
- subtitle optional;
- cover;
- description;
- author;
- file(s);
- preview;
- external shop link;
- price/display price optional;
- access policy;
- category/topic;
- tags;
- locale;
- publication date;
- project;
- SEO.

Поддержать free, registered, subscriber, paid и private content.

---

# 23. Media Library

Единая library для:

- Video;
- Audio;
- Image;
- PDF;
- Document;
- Thumbnail;
- Recording;
- Other.

Media entity должна хранить stable logical reference и metadata, а не только filesystem path.

Поддержать:

- Search;
- Filter;
- Sort;
- Pagination;
- Tags;
- Collections;
- Usage References;
- Processing State;
- Storage Location;
- File Metadata;
- Access Metadata.

Оригинал хранить отдельно от derivative/processed formats.

---

# 24. Dateien

`Dateien` — удобное файловое представление для пользователя, но не замена Media Library data model.

Может показывать:

- folders/collections;
- files;
- storage usage;
- recent uploads;
- import/export actions.

---

# 25. Video Module

Atapin Media — основное место загрузки нового видео.

Pipeline:

```text
Upload
 -> Processing Queue
 -> Media Library
 -> Website
 -> External Destinations
```

Поддержать:

- Landscape Video;
- Portrait Video;
- Short Video;
- Live Recording;
- source video suitable for audio/podcast derivation.

---

# 26. Video Studio Admin

Видео-админка должна быть рассчитана на сотни/тысячи videos и работать по логике creator studio, но не копировать YouTube Studio один в один.

Нужны:

- table/list view;
- optional grid view;
- thumbnail;
- title;
- status;
- platforms;
- playlist/series;
- project;
- publish date;
- views;
- comments;
- likes where available;
- duration;
- author/editor;
- last edited;
- bulk actions;
- filters;
- search;
- sorting;
- pagination.

Tabs:

- Alle Videos;
- Entwürfe;
- Geplant;
- Veröffentlicht;
- Livestreams;
- Playlists.

---

# 27. Video Processing Layer

Создать:

`VideoProcessorInterface`

Capabilities:

- metadata extraction;
- thumbnail generation;
- transcoding;
- preview generation;
- duration detection;
- resolution detection;
- format validation;
- optional audio extraction.

Все тяжёлые операции — только через Queue.

---

# 28. Podcast Admin

Entities:

- Podcast Series;
- Episode.

Episode fields:

- title;
- slug;
- description;
- cover;
- audio media;
- duration;
- transcript;
- episode number;
- season optional;
- publication date;
- author/host;
- category/topic;
- tags;
- project;
- SEO;
- external destinations.

---

# 29. Import Center

Отдельный module `Import Center`.

Sources:

- DOCX;
- TXT;
- HTML;
- PDF;
- local Video/Audio/Image;
- ZIP;
- URL/RSS where applicable;
- External Platforms through connectors.

UI workflow:

1. Quelle auswählen;
2. Import/Queue progress;
3. Überprüfen;
4. Zuordnen;
5. Übernehmen.

Imported content создаётся как Draft/Review state.

KI может помочь:

- определить title;
- создать short description;
- очистить formatting;
- предложить tags/topics;
- подготовить SEO;
- улучшить текст.

Нужны Import-Verlauf, Errors и retry/review states.

---

# 30. Migration Wizard

Для нового клиента предусмотреть assisted migration:

1. Branding;
2. Existing Documents;
3. Existing Media Archive;
4. External Platforms;
5. Categories/Themen;
6. Subscribers;
7. Payment settings optional;
8. Review & Publish.

Wizard помогает, но не предполагает одинаковую legacy structure у всех клиентов.

---

# 31. Connector Architecture

YouTube не является hard-coded special case.

Создать:

`MediaConnectorInterface`

Possible adapters:

- YouTubeConnector;
- TikTokConnector;
- InstagramConnector;
- FacebookConnector;
- VimeoConnector;
- TelegramConnector;
- OtherConnector.

Каждый connector объявляет capabilities, например:

- `can_import_metadata`;
- `can_import_media`;
- `can_publish_video`;
- `can_publish_short`;
- `can_publish_image`;
- `can_publish_text`;
- `can_schedule`;
- `can_update_metadata`;
- `can_read_comments`;
- `can_reply_comments`;
- `can_import_analytics`;
- `can_stream`.

Не предполагать одинаковые API у разных платформ.

---

# 32. External Platform Import

Если API и права платформы позволяют, система может:

- импортировать metadata;
- thumbnails;
- external IDs;
- original/canonical URL;
- media file только если это разрешено;
- предотвращать duplicates;
- связывать external item с local content.

Локальный архив пользователя импортируется независимо от Social API.

---

# 33. Publishing Workspace

`Publishing` — отдельное приложение Media Desktop.

Пользователь выбирает material и destinations:

```text
[x] Website
[x] YouTube
[x] Instagram
[x] TikTok
[ ] Facebook
[x] Telegram
```

Список зависит от configured connectors и capabilities.

Per destination:

- Title;
- Description;
- Caption;
- Hashtags;
- Thumbnail;
- Visibility;
- Publish Time;
- platform-specific options.

KI может подготовить отдельные metadata для каждой платформы.

Перед публикацией показывать final preview/summary.

Publishing выполняется через Queue.

Каждый destination имеет собственные:

- status;
- external ID;
- published URL;
- error;
- retry count;
- last attempt;
- next retry.

Website URLs используют canonical domain installation.

---

# 34. Kalender / Planung

Views:

- Monat;
- Woche;
- Liste.

В одном календаре:

- Beiträge;
- Videos;
- Shorts;
- Podcasts;
- Live;
- Social Posts;
- PDF/Materials;
- team tasks/events where enabled.

Поддержать:

- filters by project/type/destination;
- upcoming publication sidebar;
- today overview;
- quick actions;
- drag & drop architecture.

---

# 35. Unified Community Inbox

Одна из центральных функций продукта.

В одном месте показывать:

- own-site comments;
- questions;
- live questions;
- supported external platform comments/messages.

Каждая запись показывает:

- source platform;
- related local/external content;
- author/user;
- message;
- date/time;
- unread state;
- moderation state;
- reply availability.

Если connector поддерживает reply API, ответ отправляется прямо из Media Desktop.

Если reply API недоступен, UI честно показывает ограничение и даёт переход к original platform.

Desktop widget `Kommentare / Fragen` показывает source icons и quick reply.

---

# 36. Subscribers

Subscribers принадлежат владельцу конкретной установки.

Поддержать:

- E-Mail Subscription;
- Account Registration;
- Double Opt-In;
- Unsubscribe;
- Preferences;
- Segments/Tags;
- Consent Metadata;
- Notification Preferences;
- Export according to permissions.

---

# 37. Accounts / Personal Area

Архитектурно поддержать:

- profile;
- saved/favorite content;
- subscriptions/preferences;
- access entitlements;
- continue watching/listening/reading;
- notifications;
- community activity.

Не все функции обязаны войти в ранний MVP.

---

# 38. Poll / Survey

В продукт входит простой built-in Poll Module.

Minimum:

- question;
- choices;
- start/end;
- active/inactive;
- audience/access rule;
- single/multiple choice;
- result visibility;
- vote counts;
- placements/layout slots.

Дополнительно разрешить external interactive content:

- embed;
- iframe where allowed;
- external URL.

Сложный Quiz Engine на первом этапе не создавать.

---

# 39. Live Streaming

Live — один из основных модулей.

Первый профессиональный способ:

- OBS;
- RTMP/SRT-compatible encoder.

Platform предоставляет:

- Stream URL;
- Stream Key;
- Live Title;
- Description;
- Thumbnail;
- Start Time;
- Destinations.

Recording остаётся в собственной Media Platform.

---

# 40. Live Studio UI

Live Studio показывает:

- Preview;
- LIVE timer;
- Viewer Count;
- Stream Status;
- bitrate/quality where available;
- dropped frames/errors where available;
- Camera/Mic Status for Browser Live;
- Recording Status;
- Destinations;
- combined Chat/Questions;
- Start/Stop;
- emergency stop/mute where applicable.

Расширяемые возможности:

- Scenes;
- Layouts;
- Audio Mixer;
- Screen Share;
- Run of Show;
- Guests;
- Q&A;
- Poll action;
- Overlays;
- Titles;
- Logos;
- Lower Thirds;
- Media Inserts.

Не пытаться в первой версии воспроизвести весь OBS.

---

# 41. Multistream

Один Live архитектурно может иметь несколько outputs:

- Own Website;
- YouTube;
- другие supported destinations.

Использовать Stream Output Adapters / provider abstraction.

---

# 42. Browser / Mobile Live

Подготовить архитектуру для:

- Camera;
- Microphone;
- Screen;
- Start Live directly from browser/mobile.

Browser Live — дополнительный quick mode, а не замена OBS в первом профессиональном релизе.

---

# 43. KI Assistant

Создать:

`AiProviderInterface`

Core не связывать навсегда с одним provider.

KI должен быть action-oriented и context-aware.

Tools:

- Titel verbessern;
- Zusammenfassung erstellen;
- SEO-Titel & Keywords;
- Meta-Beschreibung;
- Hashtags generieren;
- YouTube-Beschreibung;
- Social Caption;
- Bibelstellen strukturieren;
- Text kürzen;
- Text verlängern;
- Stil/Ton anpassen;
- in anderes Format umwandeln;
- Ideen für weitere Inhalte;
- Kommentarantwort vorschlagen.

Оставить free prompt field.

Result actions:

- Regenerate;
- Anpassen;
- Kopieren;
- Übernehmen.

KI встроен в:

- Beitrag Editor;
- Video metadata;
- Podcast;
- Bücher/PDF metadata;
- Publishing;
- Community/Kommentare;
- Import Review;
- Desktop Recommendations.

KI не публикует пользовательский content автоматически без разрешения workflow.

---

# 44. Analytics

Own-platform analytics:

- Page Views;
- Video Views;
- Audio/Podcast Plays;
- Downloads;
- PDF Downloads;
- Subscribers;
- Registrations;
- Live Views;
- Popular Content;
- Popular Search Terms;
- Kommentare/Community Activity;
- Outbound Social Clicks.

Connectors могут добавлять external analytics.

UI должен различать own-platform и external-platform data.

---

# 45. SEO и canonical domain

Поддержать:

- SEO Title;
- Meta Description;
- Canonical URL;
- OpenGraph;
- Social Image;
- Sitemap;
- Robots;
- Structured Data where applicable.

Каждая installation имеет:

- `canonical_domain`;
- optional `alias_domains`.

Rules:

- canonical URL генерируется только из `canonical_domain`;
- sitemap содержит только canonical URLs;
- `og:url` содержит canonical URL;
- public share URLs используют canonical domain;
- alias domains делают 301 на тот же path canonical domain;
- generated PDF/QR links используют canonical domain.

Для Manna:

```text
canonical_domain = mannavomhimmel.de
alias_domains = [manna-vom-himmel.de]
```

---

# 46. Access Policies

Access levels:

- Public;
- Registered;
- Subscriber;
- Paid;
- Private.

Rules могут задаваться на уровнях:

- Global;
- Category/Topic;
- Collection/Series;
- Individual Content.

Более конкретное правило имеет приоритет.

---

# 47. Monetization

Архитектурно поддержать:

- Free;
- One-Time Purchase;
- Subscription;
- Membership;
- Donation;
- External Purchase.

Создать:

`PaymentProviderInterface`

Монетизация не обязана быть ранним MVP, но data model не должен блокировать её добавление.

---

# 48. Entitlements

Не использовать `paid=true` как единственный механизм доступа.

Entitlement определяет:

- кто;
- к какому resource;
- до какого времени;
- имеет доступ.

Это позволяет выдавать/продавать доступ к:

- Video;
- PDF;
- Book;
- Category/Topic;
- Collection/Series;
- full library;
- subscription.

---

# 49. Multilanguage Core

Все UI strings — только через translation keys.

Не размещать пользовательские строки напрямую в Blade/Vue/JS/PHP.

Поддержать:

- `default_locale`;
- `enabled_locales`;
- `fallback_locale`.

Content архитектурно имеет:

- `locale`;
- optional `translation_group_id`.

Translation variants могут быть связаны, но перевод не обязателен.

---

# 50. Responsive / Mobile First

Public Website и Media Desktop responsive.

С телефона должны быть доступны основные операции:

- create/edit text;
- Video/Photo/PDF upload;
- Publish;
- KI;
- Live preparation;
- Comments reply;
- Tasks;
- Calendar;
- Analytics.

Mobile UI должен иметь собственные compact layouts, а не быть просто уменьшенной desktop table.

---

# 51. PWA / Mobile App Foundation

Подготовить installable PWA:

- manifest;
- icons;
- standalone mode;
- service worker;
- push-ready architecture.

API/Auth/UI architecture должна позволять позже WebView/hybrid/native app без отдельного backend.

---

# 52. Storage

Использовать Laravel Filesystem abstraction.

Поддержать:

- Local Storage;
- S3-compatible Storage;
- Remote/Object Storage.

Не предполагать, что большие videos всегда хранятся на основном web disk.

---

# 53. Cache

Laravel Cache abstraction, Redis-ready.

Кэшировать минимум:

- popular/public fragments;
- category/topic trees;
- settings;
- navigation;
- search helper data;
- external API responses where safe.

При изменении relevant content выполнять корректную invalidation.

---

# 54. Queue

Все тяжёлые операции через Queue:

- Video Processing;
- Transcoding;
- Thumbnail Generation;
- Publishing;
- Social Import;
- PDF Generation;
- AI;
- E-Mail;
- Notifications;
- Indexing;
- Imports;
- Live Recording Processing.

Обычный HTTP request не должен ждать длительную операцию.

---

# 55. Scheduler

Использовать для:

- scheduled publishing;
- social publishing;
- sync;
- index maintenance;
- cache cleanup;
- newsletter;
- live notifications;
- update checks;
- backup checks;
- health checks.

---

# 56. Backup

Предусмотреть:

- Database Backup;
- Files Backup;
- Configuration Backup;
- Media Metadata Backup.

Backup работает независимо от Central Control.

---

# 57. Security

Обязательны:

- 2FA-ready authentication;
- rate limiting;
- CSRF protection;
- secure cookies;
- encrypted secrets;
- granular permissions;
- audit logging;
- signed URLs;
- temporary downloads;
- secure media access.

Protected/paid files нельзя защищать только скрытым URL.

---

# 58. Integration Credentials

OAuth/API credentials конкретного клиента хранятся только в его installation.

Central Control по умолчанию не хранит:

- social tokens;
- payment secrets;
- AI keys;
- SMTP passwords;
- subscriber data.

---

# 59. Audit Log

Логировать минимум:

- Login/Logout;
- Content Change/Delete;
- Publish;
- Social Publish;
- Live Start/Stop;
- Payment/Access Change;
- Role Change;
- Settings Change;
- Support Login;
- Update;
- KI Action;
- Critical Error.

---

# 60. API / Service First

Business logic не размещать непосредственно во Vue/Blade и не держать в Controllers.

Media Desktop, PWA и future apps используют один backend/service layer.

Controllers — thin.

Использовать по необходимости:

- Actions;
- Services;
- Jobs;
- Policies;
- Events;
- Listeners;
- DTOs;
- Interfaces/Adapters.

Внешние API не вызывать напрямую из Controller.

---

# 61. Core Abstraction Layers

Минимально предусмотреть:

- `AiProviderInterface`;
- `MediaConnectorInterface`;
- `VideoProcessorInterface`;
- `StreamProviderInterface`;
- `SearchProviderInterface`;
- `PaymentProviderInterface`;
- `NotificationProviderInterface`;
- Storage abstraction;
- `UpdateProviderInterface`;
- `LicenseProviderInterface`.

---

# 62. Независимость установки

Это критическое требование.

Public Website и Media Desktop не должны зависеть от постоянной доступности Central Control.

При недоступности:

- Central Control;
- License Server;
- Update Server;
- AI Provider;
- YouTube;
- Instagram;
- TikTok;
- других external services

локальная установка продолжает работать в пределах собственных функций.

Должны сохраняться:

- Public Website;
- Media Desktop;
- users;
- own content;
- media library;
- search;
- purchased/protected content;
- local queue;
- scheduler;
- local manual update path.

---

# 63. Licensing Fail-Open

License system не должна быть kill switch.

Если Central Control недоступен по timeout/DNS/network/server error, установка продолжает работать.

Только явно полученный и сохранённый статус `Suspended` может ограничивать административные функции согласно policy.

Лицензирование — коммерческий механизм управления установками, не DRM expiration приложения.

---

# 64. Installation Identity

При установке генерировать:

- `installation_uuid`;
- `installation_secret` или keypair;
- `product_version`.

---

# 65. Central Control Preparation

На текущем этапе полноценный Central Control не является приоритетом.

Подготовить service/API architecture:

- InstallationService;
- LicenseService;
- UpdateService;
- HealthService;
- SupportAccessService.

Central integration должна быть отключаемой.

---

# 66. Health Endpoint

Защищённый endpoint может отдавать:

- App Version;
- PHP/Laravel Version;
- DB Status;
- Queue Status;
- Scheduler Status;
- Storage Usage;
- Search Index Status;
- Cache Status;
- Last Backup;
- Connector Status;
- Update Status.

Не отдавать пользовательский content и secrets.

---

# 67. Support Access

Никакого общего master password.

Использовать temporary signed support token с expiry.

Все support logins записывать в Audit Log.

---

# 68. Update Manager

Поддержать:

- Current Version;
- Available Version;
- Update Channel;
- Manual Update;
- remote-update-ready architecture;
- optional automatic update later.

Update package должен иметь cryptographic verification.

Если update server исчезает, установленная версия продолжает работать.

Safe update flow:

1. pre-update health check;
2. backup/checkpoint;
3. maintenance mode;
4. package verification;
5. migrations;
6. cache rebuild;
7. index/reindex where required;
8. post-update health check;
9. rollback on failure.

---

# 69. Roadmap

## Phase 1 — Foundation

Реализовать:

- Laravel Core Foundation;
- Authentication;
- Roles/Permissions;
- Settings System;
- Multilanguage Foundation;
- Design Token/Theme Foundation;
- Media Desktop Shell;
- Storage Abstraction;
- Media Library Foundation;
- Queue;
- Cache;
- Search Architecture;
- Audit Log;
- API/Service Foundation;
- Connector Interfaces;
- Tests.

После Phase 1 остановиться и предоставить отчёт.

## Phase 2 — Workflow

- Projekte;
- Aufgaben;
- Universal Editor/Workflow Shell;
- Kalender/Planung;
- Desktop Weiterarbeiten.

## Phase 3 — Content

- Beiträge;
- Topics/Categories;
- Tags;
- Collections/Series;
- DOCX/TXT/HTML import;
- PDF Generator;
- Bücher & Dokumente;
- Public Beiträge;
- Public Bibliothek;
- Global Search.

## Phase 4 — Media

- Video Upload;
- Video Processing;
- Video Studio Admin;
- Playlists/Series;
- Podcast Series/Episodes;
- Public Videos;
- Public Podcast.

## Phase 5 — Publishing & Integrations

Сначала полноценно **YouTube** через Connector Layer.

Далее тем же механизмом:

- Instagram;
- TikTok;
- Facebook;
- Telegram;
- другие supported destinations.

Также:

- Publishing Workspace;
- per-platform metadata;
- schedule/retry/error states.

## Phase 6 — KI

- KI Workspace;
- Contextual KI;
- SEO;
- Titles;
- Descriptions;
- Hashtags;
- Platform-specific text;
- Import Assistance;
- Comment Reply Suggestions.

## Phase 7 — Audience

- Subscribers;
- Accounts;
- Community;
- Questions/Discussions;
- Comments;
- Unified Community Inbox;
- Notifications;
- Poll Module;
- External Survey embeds;
- Public Community/Themen.

## Phase 8 — Live

- Live Studio;
- OBS/RTMP/SRT;
- Streaming Provider Integration;
- Recording;
- Media Library Conversion;
- Public Live Page;
- Live Chat;
- Multistream Architecture;
- Browser Live Preparation.

## Phase 9 — Monetization

- Access Policies;
- Entitlements;
- Subscriptions;
- One-Time Purchases;
- Donations;
- Payment Provider;
- Protected Downloads.

## Phase 10 — Mobile & Vendor Infrastructure

Сначала:

- installable PWA;
- responsive Media Desktop;
- mobile upload/KI/comment workflows;
- push-ready architecture.

Только после стабильности продукта:

- Central Control;
- Licenses;
- Installation Management;
- Support Access;
- Health Monitoring;
- Remote Updates;
- Version Management.

---

# 70. Первая задача для Codex

Не реализовывать весь Master-ТЗ одной задачей.

На первом проходе:

1. проанализировать repository;
2. считать `MASTER-TZ.md` главным функциональным specification;
3. считать `UI/approved/` единственным visual reference для Manna Vom Himmel;
4. не искать и не восстанавливать удалённые альтернативные UI-концепты;
5. не удалять рабочий функционал без необходимости;
6. описать architecture и module boundaries;
7. подготовить Foundation migrations/models;
8. реализовать Multilanguage Foundation;
9. реализовать Settings System;
10. реализовать Authentication/RBAC Foundation;
11. реализовать Media Desktop Shell с утверждённой navigation architecture;
12. реализовать Theme/Design Token Foundation;
13. реализовать Media Library Foundation;
14. подготовить Storage Abstraction;
15. подготовить Queue/Cache/Search Architecture;
16. подготовить Audit Log Foundation;
17. подготовить Connector Interfaces;
18. подготовить Central-Control-ready service interfaces без Central Control;
19. добавить automated tests;
20. документировать архитектурные решения.

После Phase 1 остановиться и предоставить отчёт:

- что создано;
- какие migrations/models/services/interfaces добавлены;
- какие tests проходят;
- какие решения требуют подтверждения перед Phase 2.

---

# 71. Запреты для Codex

- Не создавать fake/mock integrations и не выдавать их за готовые.
- Не реализовывать external API без реальной authorization/error handling.
- Не hard-code Manna client data в Core.
- Не возвращать удалённые старые UI-концепты.
- Не использовать никакой visual reference вне `UI/approved/`, если это отдельно не разрешено.
- Не создавать новый визуальный стиль для каждого экрана.
- Не копировать конкретную OS или YouTube Studio один в один.
- Не создавать giant Controllers/Services, смешивающие независимые domains.
- Не запускать тяжёлые операции синхронно в обычном HTTP request.
- Не делать runtime dependency от Central Control.
- Не защищать paid/protected content только obscured URL.
- Не считать screenshot business logic specification, если его placeholder text конфликтует с ТЗ.

---

# 72. Acceptance Principles

## 72.1 UX

Владелец должен со временем почти перестать работать напрямую в YouTube/TikTok/Instagram для ежедневных операций.

Основной workflow:

```text
MEDIA DESKTOP
  |
  +-- Create
  +-- Edit
  +-- KI
  +-- Store
  +-- Schedule
  +-- Live
  +-- Publish
  +-- Communicate
  |
  v
Own Website + External Platforms
```

Ключевой критерий: **одна рабочая среда для создания контента, публикации и общения с аудиторией**.

## 72.2 Technical

Ни один внешний сервис не является обязательным для существования Media Platform.

При исчезновении external provider собственная library, users, public site и локальные данные остаются доступными согласно permissions.

## 72.3 UI

Все новые экраны используют общий Design System:

- typography tokens;
- buttons;
- cards;
- tables;
- forms;
- badges/statuses;
- platform icons;
- empty states;
- toasts/alerts;
- modal patterns;
- pagination/filter/search patterns;
- responsive behavior.

Для Manna Vom Himmel внешний вид должен соответствовать `UI/approved/`.

## 72.4 Scope discipline

MVP должен давать работающий foundation и реальные end-to-end workflows.

Лучше один полностью рабочий connector, processing pipeline и publishing flow, чем множество декоративных незавершённых интеграций.

---

# 73. Основные документы проекта

- [`README.md`](./README.md) — обзор Atapin Media.
- [`MASTER-TZ.md`](./MASTER-TZ.md) — главный technical/product specification.
- [`MANNA-VOM-HIMMEL.md`](./MANNA-VOM-HIMMEL.md) — немецкое описание первого клиента.
- [`UI/approved/`](./UI/approved/) — **единственный approved visual reference** Manna Vom Himmel.

---

# 74. Финальный принцип

Atapin Media — универсальный Core для многих самостоятельных клиентов.

Manna Vom Himmel — первая конкретная установка этого Core.

Для Manna уже зафиксированы:

- домены;
- язык;
- product structure;
- public information architecture;
- Media Desktop structure;
- workflows;
- visual design.

Следующая разработка должна расширять систему **внутри этой утверждённой архитектуры**, а не возвращаться к выбору между альтернативными дизайнами.