# MASTER-ТЗ — Atapin Media

**Independent Media Platform / Media Desktop**  
Актуализировано под утверждённую UI/UX-концепцию Manna Vom Himmel.

---

## 1. Цель продукта

Создать универсальную **self-hosted single-tenant Media Platform** для авторов, издателей, проповедников, преподавателей, блогеров, медиакоманд и других создателей контента.

Это не SaaS и не multi-tenant система.

Каждый клиент получает самостоятельную установку:

- отдельный домен;
- отдельный Laravel-проект/deployment;
- отдельную БД;
- собственное файловое или объектное хранилище;
- собственных пользователей и подписчиков;
- собственные Social-Media Accounts;
- собственные payment/AI/mail credentials;
- собственный branding и design tokens;
- собственные категории, темы, navigation и configuration.

Платформа состоит из двух взаимосвязанных частей:

1. **Public Website** — лицевая медиаплощадка для читателей, зрителей и слушателей;
2. **Media Desktop** — рабочая среда владельца и команды.

Media Platform должна быть главным **Source of Truth**, Content Archive, Media Library, Community Base, Subscriber Base, Publishing Center и Live Center.

Внешние платформы — каналы распространения, а не основное место хранения контента.

Первая установка продукта — **Manna Vom Himmel**:

- canonical/public domain: `mannavomhimmel.de`;
- alias domain: `manna-vom-himmel.de`;
- alias обязан делать постоянный 301 redirect на соответствующий URL канонического домена.

---

## 2. Неизменяемые архитектурные принципы

### 2.1. Независимость установки

Клиентская платформа должна полностью работать без Central Control Server.

При недоступности Central Control, update server, AI provider или внешней социальной сети продолжают работать как минимум:

- Public Website;
- Media Desktop;
- авторизация;
- Beiträge;
- Videos;
- Podcast;
- Bücher & PDF;
- Media Library;
- Search/Index;
- локальные файлы и protected downloads;
- Subscribers/Accounts;
- локальная Community;
- локальные queues;
- Scheduler;
- локальное ручное обновление.

Central Control не является runtime dependency.

### 2.2. Fail-open licensing

Если Central Control просто недоступен из-за timeout, DNS, network error или server failure, установка продолжает работать.

Только явно полученный и сохранённый статус лицензии `Suspended` может ограничивать административные функции.

Лицензирование — коммерческий механизм управления клиентами, а не DRM kill switch.

### 2.3. External services are adapters

YouTube, TikTok, Instagram, Facebook, Telegram, Vimeo, AI providers, payment providers, mail providers, storage providers и streaming providers подключаются только через abstraction/adapters.

Ни один внешний сервис не должен быть жёстко встроен в core business logic.

---

# ЧАСТЬ I. UX / DESIGN / PRODUCT WORKSPACE

## 3. Design System Core

Core должен поддерживать theme/design tokens. Branding Manna Vom Himmel не hard-code в generic UI components.

Базовые token categories:

- brand primary;
- brand secondary;
- accent;
- neutral colors;
- success;
- warning;
- danger;
- info;
- live;
- typography;
- radii;
- spacing;
- shadows;
- icon sizing.

### Manna Vom Himmel Design System

Для первой установки использовать утверждённый стиль:

- знак: раскрытая книга / стилизованная `M` со светом/лучами сверху;
- Dunkelblau / Navy — главный тёмный цвет;
- Gold — брендовый public accent и primary CTA;
- Himmelblau — interactive/admin accent;
- Cremeweiß — public/background surface;
- зеленый — success;
- orange — warning;
- red — danger/live;
- **Playfair Display** — public editorial headings;
- **Inter** — интерфейс, формы, таблицы, metadata и admin.

Public Website должен быть более editorial/emotional. Media Desktop — более функциональным и плотным.

Scripture и brand quotes могут использоваться как ненавязчивые визуальные элементы, но не должны перегружать рабочие экраны.

### Утверждённый visual reference

Канонический визуальный референс для Manna Vom Himmel находится в:

`UI/approved/`

Для public frontend утверждена серия **Frontend Gold Editorial B** в `UI/approved/frontend-gold-b/`.

Master UI Kit находится в `UI/approved/design-system/master-ui-kit.png`.

Правила:

- Codex должен использовать `UI/approved/` как основной визуальный источник при реализации Manna Vom Himmel;
- старые изображения в `/UI` и другие preview-варианты являются историческими/экспериментальными;
- не смешивать разные ранние дизайн-концепции;
- если конкретный экран ещё не нарисован, строить его из approved Design System и уже утверждённых паттернов;
- screenshots определяют визуальный язык, а этот Master-ТЗ — функциональность и поведение.

---

## 4. Public Website

Public Website — самостоятельная лицевая часть продукта, конфигурируемая конкретным клиентом.

Core не задаёт обязательное меню, но должен предоставить building blocks для:

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
- Login/Register/Account;
- About/Content pages.

### Manna Vom Himmel navigation

Для первой установки:

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

Также в Header:

- Global Search;
- Anmelden / Account;
- Newsletter.

### Dynamic public states

Если идёт Live, на публичном сайте появляется глобальный Live-Hinweis/Live-Bar с переходом на эфир.

Если активен Poll/Survey, система может автоматически показать его в предусмотренных layout slots.

Public homepage должна уметь отображать:

- Hero / featured content;
- active Live;
- current Poll;
- Beitrag des Tages;
- Neue Videos;
- Bücher & PDF;
- Themen;
- Newsletter;
- community highlights;
- next Live.

---

## 5. Public Beiträge

Нужны:

- Beiträge overview;
- category/topic filters;
- featured Beitrag;
- latest Beiträge;
- popular Beiträge;
- latest Kommentare where enabled;
- Einzelbeitrag;
- related content;
- author data;
- reading time;
- sharing;
- generated PDF when allowed;
- SEO metadata;
- comments according to permissions.

---

## 6. Public Videos

Нужны:

- Videos overview;
- Neue Videos;
- Popular videos;
- Series/Playlists;
- Thema filters;
- Featured video;
- Video detail/player;
- description;
- transcript where available;
- related videos;
- comments/community integration;
- next Live widget;
- Continue Watching for registered users where implemented.

---

## 7. Public Bücher & PDF / Bibliothek

Нужны:

- library overview;
- books;
- PDF materials;
- categories/topics;
- recommendations;
- new items;
- popular items;
- free/paid state;
- detail page;
- preview;
- download/purchase/access action;
- external shop link;
- reader recommendations/reviews where enabled;
- reading/download progress where technically applicable.

---

## 8. Public Live

Нужны:

- Live overview;
- active Live page;
- player;
- own site Live Chat;
- aggregated or bridged questions where supported;
- Viewer Count;
- stream information;
- next livestreams;
- weekly schedule;
- active poll;
- past livestreams/replays;
- reminders/notifications;
- share action.

---

## 9. Public Podcast

Podcast является полноценным модулем, а не только “audio-ready media”.

Нужны:

- Podcast overview;
- series;
- episodes;
- episode detail;
- web audio player;
- cover;
- description;
- duration;
- transcript where available;
- categories/topics;
- `Weiterhören` for registered users;
- links to external podcast destinations when configured.

---

## 10. Public Community

Нужны:

- overview;
- discussions;
- questions;
- answers;
- Polls;
- topic filters;
- Community highlights;
- moderation states;
- account-required actions;
- optional public live community feed;
- registration CTA.

Community не обязана быть полноценной социальной сетью. Главная цель — общение вокруг контента и вопросов.

---

## 11. Themen / Cross-content Discovery

`Themen` — отдельный публичный discovery layer.

Пользователь может выбрать тему, например `Gebet`, и получить материалы разных типов одновременно:

- Beitrag;
- Video;
- Book/PDF;
- Podcast;
- Live/Replay.

Topic page должна быть cross-content, а не только alias категории одного типа материалов.

---

## 12. Media Desktop — UX concept

Media Desktop — не CRUD Dashboard, а **creator workspace / virtual media computer**.

Главный UX-принцип:

> пользователь должен видеть, что происходит сейчас, быстро продолжить текущую работу и взаимодействовать с аудиторией без перехода во внешние платформы.

### Основная sidebar structure

#### Workspace

- Desktop
- Projekte
- Aufgaben
- Kalender

#### Inhalte

- Beiträge
- Videos
- Bücher & PDF
- Media Library

#### Community

- Community
- Subscribers

#### Tools

- Live Studio
- Publishing
- KI-Assistent
- Analytics

#### Weitere

- Import Center
- Dateien
- Integrationen
- Einstellungen

Группы sidebar должны быть collapsible. Пункты зависят от permissions.

---

## 13. Media Desktop — Home/Desktop

Главный Desktop должен показывать состояние работы, а не просто ссылки на модули.

Обязательные quick actions:

- Neuer Beitrag;
- Neues Video;
- Live starten;
- PDF hochladen;
- Veröffentlichen.

Основной центральный block:

### Weiterarbeiten / Aktuelles Projekt

Показывает:

- текущий/последний проект;
- тип материала;
- cover/thumbnail;
- current stage;
- workflow progress;
- next meaningful action;
- `Weiter bearbeiten`;
- KI assistance;
- preview;
- destinations where relevant.

Допускается список 2–3 последних рабочих объектов вместо одного.

### Desktop Widgets

- Nächste Veröffentlichung;
- Live starten / Live Status;
- Uploads / Processing;
- Neue Videos;
- Letzte Beiträge;
- Kommentare / Fragen;
- Subscribers / Community growth;
- Social Status;
- Speicher;
- Systemstatus / Letzte Fehler;
- KI-Vorschläge.

Widgets должны быть user-configurable:

- show/hide;
- order;
- position;
- optional size presets.

Обычным Mediengestalter/Editor технические ошибки показывать только тогда, когда требуется действие. Owner/Admin может видеть подробный Systemstatus.

---

## 14. Пользователи и роли

Минимальные роли:

- Owner;
- Administrator;
- Mediengestalter;
- Editor;
- Moderator;
- Support.

`Mediengestalter` — основной рабочий пользователь.

Он должен иметь возможность:

- создавать и редактировать контент;
- загружать Video/Audio/Image/PDF;
- использовать KI;
- планировать публикации;
- управлять destinations;
- работать с Media Library;
- готовить и вести Live according to permissions;
- читать и отвечать на комментарии;
- работать с Projects/Tasks.

RBAC должен быть granular, а не только role-name checks.

---

# ЧАСТЬ II. WORKFLOW / CONTENT

## 15. Projekte

`Projekt` — рабочая сущность, объединяющая связанные материалы и задачи.

Один проект может содержать:

- Beitrag;
- Video;
- Podcast episode;
- Book/PDF;
- Social Posts;
- Live;
- Media assets;
- Tasks.

Project fields:

- title;
- description;
- type;
- status;
- owner/responsible;
- team;
- start date;
- due date;
- tags;
- project cover;
- workflow/progress;
- current phase;
- next action.

Project UI:

- table/list;
- filters;
- progress;
- deadlines;
- responsible person;
- project detail;
- timeline/workflow.

---

## 16. Aufgaben

Task management является частью Media Desktop.

Представления:

- Board;
- Liste;
- Meine Aufgaben.

Базовые columns:

- Offen;
- In Arbeit;
- Warten;
- Erledigt.

Task fields:

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

Фильтры:

- project;
- content type;
- user;
- priority;
- status;
- due date.

---

## 17. Universal Content Workflow / Editor

Нужен единый UX pattern для подготовки материалов.

Editor должен уметь объединять:

- main content;
- Cover/Thumbnail;
- Author;
- Categories/Themen;
- Tags;
- Project;
- Access Rules;
- SEO;
- KI tools;
- Preview;
- platform-specific metadata;
- schedule;
- publish destinations.

Workflow stage зависит от content type.

Пример Video:

```text
Idee -> Skript -> Produktion -> Feinschliff -> Veröffentlichung
```

Пример Beitrag:

```text
Entwurf -> Redaktion -> Medien -> SEO/KI -> Vorschau -> Veröffentlichung
```

Статусы и workflow stages не должны смешиваться в одну сущность без необходимости.

---

## 18. Beiträge

Поддержать:

- ручное создание;
- DOCX import;
- TXT;
- Copy/Paste;
- HTML import;
- Bulk Import.

Поля минимум:

- Titel;
- Slug;
- Excerpt;
- Content;
- Cover;
- Author;
- Locale;
- Publish Date;
- Status;
- Kategorien/Themen;
- Tags;
- Project;
- SEO;
- Access Rules;
- Download/PDF Rules.

Content statuses:

- Draft;
- In Review/In Bearbeitung where needed;
- Scheduled;
- Published;
- Archived.

Использовать нормальный Rich Content Editor.

---

## 19. Generated PDF

Для Beitrag система может автоматически создавать PDF.

Template поддерживает:

- Logo;
- Titel;
- Autor;
- Datum;
- Content;
- URL;
- QR Code;
- Footer;
- tenant branding.

Действия:

- herunterladen;
- drucken;
- teilen.

После изменения Beitrag PDF должен уметь regenerieren.

Все абсолютные URL и QR-коды в PDF должны использовать канонический домен конкретной установки.

---

## 20. Uploaded PDF / Documents

Пользователь может загружать готовый PDF как самостоятельный material.

Типы, например:

- Buch;
- Broschüre;
- Flyer;
- Arbeitsblatt;
- Studie;
- Sonstiges.

Не ограничивать Core этими типами — они configurable.

---

## 21. Bücher & Dokumente

Book/Document fields:

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

Нужно поддержать free и protected/paid materials.

---

## 22. Media Library

Единая library для:

- Video;
- Audio;
- Image;
- PDF;
- Document;
- Thumbnail;
- Recording;
- Other.

Каждый объект хранит metadata и stable logical reference, а не только path.

Поддержать:

- Search;
- Filter;
- Sort;
- Pagination;
- Tags;
- Collections;
- Usage References;
- processing state;
- storage location;
- file metadata;
- access metadata.

Оригинал хранить отдельно от derivative/processed formats.

---

## 23. Dateien

`Dateien` — файловое представление/менеджер для пользователей, но не замена Media Library data model.

Может показывать:

- folders/collections;
- files;
- storage usage;
- recent uploads;
- import/export actions.

Media entity должна оставаться источником metadata и usage references.

---

# ЧАСТЬ III. MEDIA / VIDEO / PODCAST

## 24. Video module

Media Platform — основное место загрузки нового Video.

Pipeline:

```text
Upload
 -> Media Platform
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
- source video suitable for podcast/audio derivation.

---

## 25. Video Studio Admin

Видео-админка должна работать в логике Creator Studio, рассчитанной на сотни/тысячи videos.

Нужны:

- table/list view;
- optional card/grid view;
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
- sort;
- pagination.

Tabs могут включать:

- Alle Videos;
- Entwürfe;
- Geplant;
- Veröffentlicht;
- Livestreams;
- Playlists.

---

## 26. Video Processing Layer

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

Тяжёлые операции только через Queue.

---

## 27. Podcast module

Podcast может использовать standalone Audio или Audio derived from Video.

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

# ЧАСТЬ IV. IMPORT / PUBLISHING / INTEGRATIONS

## 28. Import Center

Нужен отдельный module `Import Center`.

Источники:

- DOCX;
- TXT;
- HTML;
- PDF metadata/files;
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

После импорта материал создаётся как Draft/Review state.

KI может:

- определить title;
- создать short description;
- очистить formatting;
- предложить Tags/Themen;
- создать SEO;
- улучшить текст.

Показывать Import-Verlauf, Errors и Empfehlungen.

---

## 29. Migration Wizard

Для нового клиента предусмотреть assisted migration:

1. Branding;
2. Existing Documents;
3. Existing Media Archive;
4. External Platforms;
5. Categories/Themen;
6. Subscribers;
7. Payment settings optional;
8. Review & Publish.

Wizard помогает, но не предполагает, что все клиенты имеют одинаковую legacy-структуру.

---

## 30. Connector Architecture

Создать:

`MediaConnectorInterface`

Пример adapters:

- YouTubeConnector;
- TikTokConnector;
- InstagramConnector;
- FacebookConnector;
- VimeoConnector;
- TelegramConnector;
- OtherConnector.

Connector сообщает capabilities, например:

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
- `can_stream` where applicable.

Не предполагать одинаковые API у разных платформ.

---

## 31. Import from external platforms

При разрешении API система может:

- импортировать metadata;
- импортировать thumbnails;
- сохранять external IDs;
- сохранять canonical/original URLs;
- получать media file, только если это разрешено;
- предотвращать duplicates;
- связывать imported external item с local content.

Локальный архив пользователя можно импортировать независимо от Social API.

---

## 32. Publishing Workspace

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

Доступность destinations зависит от configured connectors и их capabilities.

Для каждой платформы можно открыть собственные настройки:

- Title;
- Description;
- Caption;
- Hashtags;
- Thumbnail;
- Visibility;
- Publish Time;
- platform-specific options.

KI может подготовить разные metadata варианты для каждой платформы.

Перед публикацией пользователь видит final preview/summary.

Publishing job выполняется через Queue и хранит per-destination status/error/retry data.

При публикации на собственный Website генерируемые публичные абсолютные URL используют canonical domain установки.

---

## 33. Kalender / Planung

Нужны представления:

- Monat;
- Woche;
- Liste.

Отображать в одном календаре:

- Beiträge;
- Videos;
- Shorts;
- Podcasts;
- Live;
- Social Posts;
- PDF/Materials;
- Team tasks/events where enabled.

Поддержать:

- filters by project/type/destination;
- upcoming publications sidebar;
- today overview;
- quick actions;
- drag & drop architecture.

---

# ЧАСТЬ V. COMMUNITY / AUDIENCE

## 34. Unified Community Inbox

Одна из центральных функций продукта.

Пользователь должен видеть в одном месте:

- own-site comments;
- questions;
- live questions;
- supported external platform comments/messages.

Каждая запись показывает:

- source platform;
- external/local content;
- author/user;
- message;
- date/time;
- unread state;
- moderation state;
- reply availability.

Если connector поддерживает reply API, ответ отправляется прямо из Media Desktop.

Если reply не поддерживается, UI должен честно показать ограничение и предложить переход на original platform.

На Desktop показывать краткий `Kommentare / Fragen` widget с platform icons и быстрым `Antworten`.

---

## 35. Community

Собственная Community поддерживает:

- Kommentare;
- Fragen;
- Antworten;
- Diskussionen;
- Live Questions;
- Reactions where enabled;
- Moderation;
- Reports;
- Poll participation;
- notifications.

Data model должен позволять дальнейшее развитие, но MVP не обязан становиться полноценной social network.

---

## 36. Poll / Survey

В актуальном UI Poll является важным public/community элементом, поэтому simple built-in Poll Module входит в продукт.

Минимально:

- question;
- choices;
- start/end;
- active/inactive;
- audience/access rule;
- single/multiple choice configuration;
- result visibility;
- vote counts;
- placements/layout slots.

Также поддержать external interactive content через:

- embed;
- iframe where allowed;
- external URL.

Сложный Quiz Engine не создавать на первом этапе.

---

## 37. Subscribers

Subscribers принадлежат владельцу Media Platform.

Поддержать:

- E-Mail subscription;
- Account registration;
- Double Opt-In;
- unsubscribe;
- preferences;
- segments/tags;
- consent metadata;
- export according to permissions;
- notification preferences.

---

## 38. Accounts / Personal area

Public Account должен поддерживать архитектурно:

- profile;
- saved/favorite content;
- subscriptions/preferences;
- access entitlements;
- continue watching/listening/reading where implemented;
- notifications;
- community activity.

Не все функции обязаны войти в первый MVP.

---

# ЧАСТЬ VI. LIVE

## 39. Live Streaming

Live — один из основных модулей.

Основной профессиональный способ первого этапа:

- OBS;
- RTMP/SRT-compatible encoder.

Media Platform предоставляет:

- Stream URL;
- Stream Key;
- Live Title;
- Description;
- Thumbnail;
- Start Time;
- Destinations.

После окончания:

```text
Recording -> Processing -> Media Library -> Video/Replay
```

Запись остаётся в собственной платформе.

---

## 40. Live Studio UI

Live Studio должен показывать:

- Preview;
- LIVE timer;
- viewer count;
- Stream Status;
- bitrate/quality where available;
- dropped frames/errors where available;
- Camera/Mic status in Browser Live;
- Recording status;
- Destinations;
- combined Chat/Questions;
- Start/Stop;
- emergency stop/mute where applicable.

Расширяемые функции:

- Scenes;
- Layouts;
- Audio Mixer;
- Screen Share;
- Run of Show;
- Guests;
- Q&A;
- Poll action;
- overlays;
- titles;
- logos;
- lower thirds;
- media inserts.

Не пытаться в первой версии полностью воспроизвести OBS.

---

## 41. Multistream

Один Live должен архитектурно поддерживать несколько outputs:

- Own Website;
- YouTube;
- other supported destinations.

Использовать Stream Output Adapters/Provider abstraction.

---

## 42. Browser / Mobile Live

Предусмотреть будущую возможность:

- Camera;
- Microphone;
- Screen;
- Start Live directly from browser/mobile.

Browser Live — дополнительный quick mode, а не замена OBS на первом этапе.

---

# ЧАСТЬ VII. KI

## 43. KI Assistant Architecture

Создать:

`AiProviderInterface`

Core не связывать навсегда с одним provider.

AI action должна иметь:

- input/context;
- provider/model metadata;
- result;
- user approval state where relevant;
- audit entry;
- error handling.

---

## 44. KI Workspace

Отдельное приложение `KI-Assistent` должно быть ориентировано на действия, а не только на пустой chat box.

Базовые tools:

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

Также оставить free prompt field.

Результат должен иметь действия:

- Regenerate;
- Anpassen;
- Kopieren;
- Übernehmen.

KI не должен автоматически публиковать пользовательский материал без явного разрешения соответствующего workflow.

---

## 45. Contextual KI

KI должен быть встроен в:

- Beitrag Editor;
- Video metadata;
- Podcast;
- Books/PDF metadata;
- Publishing;
- Kommentare/Community;
- Import Review;
- Desktop recommendations.

Desktop может показывать полезные предложения, например:

- 3 Videos ohne Beschreibung;
- 2 Beiträge ohne SEO;
- Kommentare, auf die noch nicht geantwortet wurde.

---

# ЧАСТЬ VIII. ANALYTICS / SEARCH / SEO

## 46. Analytics

Собственная базовая статистика:

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
- Kommentare/Community activity;
- Outbound Social Clicks.

Connectors могут добавлять внешнюю статистику, если API это разрешает.

В UI всегда различать own-platform analytics и external-platform analytics.

---

## 47. Search & Indexing

Обязательный Core Module.

Система должна масштабироваться на:

- тысячи videos;
- тысячи documents/PDF;
- тысячи Beiträge;
- десятки тысяч media records.

Обычного SQL `LIKE` недостаточно.

Создать:

`SearchProviderInterface`

Laravel Scout-compatible abstraction.

Индексировать:

- Titel;
- Description;
- Content;
- Tags;
- Kategorien/Themen;
- Author;
- Transcript;
- Document metadata;
- Video metadata;
- Podcast metadata.

Поддержать:

- Full Reindex;
- Incremental Index;
- Single Item Reindex.

Public Search и Media Desktop Search могут использовать один индекс с разными permissions/filtering.

---

## 48. SEO и canonical domain

Поддержать:

- SEO Title;
- Meta Description;
- Canonical URL;
- OpenGraph;
- Social Image;
- Sitemap;
- Robots;
- Structured Data where applicable.

Public content должен нормально индексироваться.

Каждая installation должна иметь явный `canonical_domain` и при необходимости список alias domains.

Общие правила:

- canonical URL генерируется только на основе `canonical_domain`;
- sitemap/sitemap index содержит только URL канонического домена;
- OpenGraph `og:url` содержит только канонический URL;
- внутренние absolute URLs и публичные share URLs используют canonical domain;
- alias domains должны делать permanent 301 redirect на соответствующий path канонического домена;
- alias не должен отдавать отдельные индексируемые копии страниц;
- generated PDF/QR links должны использовать canonical domain.

Для **Manna Vom Himmel**:

```text
canonical_domain = mannavomhimmel.de
alias_domains    = [manna-vom-himmel.de]
```

То есть:

```text
https://manna-vom-himmel.de/*
        -> 301 ->
https://mannavomhimmel.de/*
```

SEO metadata, Sitemap, OpenGraph и все внутренние публичные ссылки Manna Vom Himmel используют `mannavomhimmel.de`.

---

# ЧАСТЬ IX. ACCESS / MONETIZATION

## 49. Access Policies

Контент может иметь уровни:

- Public;
- Registered;
- Subscriber;
- Paid;
- Private.

Правила могут задаваться на уровнях:

- Global;
- Category/Topic;
- Collection/Series;
- Individual Content.

Более конкретное правило имеет приоритет.

---

## 50. Monetization modes

Архитектурно поддержать:

- Free;
- One-Time Purchase;
- Subscription;
- Membership;
- Donation;
- External Purchase.

Создать:

`PaymentProviderInterface`

Монетизация не обязана входить в ранний MVP, но data model не должен блокировать её добавление.

---

## 51. Entitlements

Не использовать простой `paid=true` как единственный механизм доступа.

Entitlement определяет:

- кто;
- к какому resource;
- до какого времени;
- имеет доступ.

Это позволяет продавать/выдавать доступ к:

- Video;
- PDF;
- Book;
- Category/Collection;
- course/series;
- full library;
- subscription.

---

# ЧАСТЬ X. MULTILANGUAGE / MOBILE

## 52. Multilanguage Core

UI strings только через translation keys.

Не размещать пользовательские строки напрямую в Blade/Vue/JS/PHP.

Поддержать:

- `default_locale`;
- `enabled_locales`;
- `fallback_locale`.

Manna Vom Himmel:

```text
default_locale = de
enabled_locales = [de]
```

Публичный сайт Manna полностью немецкий.

Core не должен зависеть от немецкого языка.

---

## 53. Multilanguage Content

Контент архитектурно имеет:

- `locale`;
- optional `translation_group_id`.

Несколько language variants могут быть связаны как translations одного материала.

Перевод не обязателен.

---

## 54. Responsive / Mobile First

Public Website и Media Desktop responsive.

С телефона должны быть доступны основные действия:

- Text erstellen;
- Video/Photo/PDF upload;
- Publish;
- KI;
- Live vorbereiten;
- Kommentare beantworten;
- Aufgaben;
- Kalender;
- Statistik ansehen.

Desktop UI на mobile может использовать compact navigation и отдельные responsive layouts, а не просто уменьшенную desktop-таблицу.

---

## 55. PWA / Mobile App Foundation

Подготовить installable PWA:

- manifest;
- icons;
- standalone mode;
- service worker;
- push-ready architecture.

API/Auth/UI architecture должна позволять позже WebView/hybrid/native app без отдельного backend.

---

# ЧАСТЬ XI. INFRASTRUCTURE

## 56. Storage

Использовать Laravel Filesystem abstraction.

Поддерживать:

- Local Storage;
- S3-compatible storage;
- Remote/Object Storage.

Не предполагать, что большие videos всегда находятся на основном web disk.

---

## 57. Cache

Laravel Cache abstraction, Redis-ready.

Кэшировать минимум:

- popular/public fragments;
- category/topic trees;
- settings;
- navigation;
- search helper data;
- external API responses where safe.

При изменении контента выполнять корректную invalidation.

---

## 58. Queue

Все тяжёлые операции через Queue:

- Video Processing;
- transcoding;
- thumbnail generation;
- Publishing;
- Social Import;
- PDF Generation;
- AI;
- E-Mail;
- Notifications;
- Indexing;
- Imports;
- Live Recording Processing.

HTTP request не должен ждать длительную операцию.

---

## 59. Scheduler

Использовать для:

- scheduled publishing;
- social publishing;
- sync;
- indexing maintenance;
- cache cleanup;
- newsletter;
- live notifications;
- update checks;
- backup checks;
- health checks.

---

## 60. Backup

Предусмотреть:

- Database backup;
- Files backup;
- Configuration backup;
- Media metadata backup.

Backup работает независимо от Central Control.

---

# ЧАСТЬ XII. SECURITY / AUDIT

## 61. Security

Обязательны:

- 2FA-ready authentication;
- rate limiting;
- CSRF protection;
- secure cookies;
- encrypted secrets;
- role permissions;
- audit logging;
- signed URLs;
- temporary downloads;
- secure media access.

Protected/paid PDF или media нельзя защищать только скрытым URL.

---

## 62. Integration Credentials

OAuth/API credentials конкретного клиента хранятся только в его Media Platform.

Central Control по умолчанию не хранит:

- Social tokens;
- payment secrets;
- AI keys;
- SMTP passwords;
- subscriber data.

---

## 63. Audit Log

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

# ЧАСТЬ XIII. LARAVEL / API ARCHITECTURE

## 64. API / Service First

Business logic не помещать непосредственно во Vue/Blade и не держать в Controllers.

Media Desktop, PWA и future apps должны работать с одним backend/service layer.

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

---

## 65. Core abstraction layers

Минимально:

- `AiProviderInterface`;
- `MediaConnectorInterface`;
- `VideoProcessorInterface`;
- `StreamProviderInterface`;
- `SearchProviderInterface`;
- `PaymentProviderInterface`;
- `NotificationProviderInterface`;
- storage abstraction;
- `UpdateProviderInterface`;
- `LicenseProviderInterface`.

Внешние API не вызывать напрямую из Controller.

---

# ЧАСТЬ XIV. CENTRAL CONTROL / SUPPORT / UPDATES

## 66. Installation Identity

При установке генерировать:

- `installation_uuid`;
- `installation_secret` или keypair;
- `product_version`.

---

## 67. Central Control preparation only

На текущем этапе полноценный Central Control не разрабатывать.

Подготовить service/API architecture, например:

- InstallationService;
- LicenseService;
- UpdateService;
- HealthService;
- SupportAccessService.

Central integration должна быть отключаемой.

---

## 68. Health Endpoint

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

## 69. Support Access

Никакого общего master password.

Использовать temporary signed support token с expiry.

Все support logins — в Audit Log.

---

## 70. Update Manager

Поддержать:

- Current Version;
- Available Version;
- Update Channel;
- Manual Update;
- Remote-update-ready architecture;
- optional automatic update later.

Update package должен поддерживать cryptographic verification.

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

# ЧАСТЬ XV. MANNA VOM HIMMEL CLIENT CONFIG

## 71. Manna Vom Himmel — первый deployment

Manna Vom Himmel — первый клиент, но не часть Core.

### Domains

Для deployment зафиксировано:

```text
Primary / Canonical: mannavomhimmel.de
Alias:               manna-vom-himmel.de
```

Обязательное поведение:

- `mannavomhimmel.de` — основной и единственный канонический публичный домен;
- `manna-vom-himmel.de` — только alias;
- любой path alias-домена перенаправляется permanent 301 на тот же path `mannavomhimmel.de`;
- SEO canonical URLs используют `mannavomhimmel.de`;
- Sitemap и Sitemap Index используют только `mannavomhimmel.de`;
- OpenGraph `og:url` использует только `mannavomhimmel.de`;
- внутренние ссылки, генерируемые системой абсолютные URL, share links, QR-коды и generated PDF links используют `mannavomhimmel.de`;
- alias не должен индексироваться как отдельный сайт и не должен создавать duplicate content.

Отдельно задаются:

- canonical domain `mannavomhimmel.de`;
- alias domain `manna-vom-himmel.de`;
- German locale;
- branding;
- logo;
- design tokens;
- public navigation;
- categories/topics;
- existing videos;
- existing texts;
- existing documents/PDF;
- podcast data;
- social accounts;
- PDF template;
- Newsletter configuration;
- public homepage blocks.

Утверждённый UI первой установки находится в `UI/approved/` и должен использоваться как визуальный reference implementation target.

Не использовать `MannaVomHimmel` в именах generic Core-классов.

---

# ЧАСТЬ XVI. ROADMAP

## 72. Phase 1 — Foundation

Реализовать:

- Laravel Core foundation;
- Authentication;
- Roles/Permissions;
- Settings System;
- Multilanguage Foundation;
- Design token/theme foundation;
- Media Desktop Shell;
- Storage abstraction;
- Media Library foundation;
- Queue;
- Cache;
- Search architecture;
- Audit Log;
- API/Service foundation;
- connector interfaces;
- tests.

После Phase 1 остановиться и предоставить отчёт.

---

## 73. Phase 2 — Workflow

- Projekte;
- Aufgaben;
- universal workflow/editor shell;
- Kalender/Planung;
- Desktop Weiterarbeiten/current project.

---

## 74. Phase 3 — Content

- Beiträge;
- Categories/Themen;
- Tags;
- Collections/Series;
- DOCX/TXT/HTML import;
- PDF Generator;
- Bücher & Dokumente;
- Public Beiträge;
- Public Bibliothek;
- Global Search.

---

## 75. Phase 4 — Media

- Video Upload;
- Video Processing;
- Video Studio Admin;
- playlists/series;
- Podcast series/episodes;
- public Videos;
- public Podcast.

---

## 76. Phase 5 — Publishing & Integrations

Сначала полноценно YouTube через Connector Layer.

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

---

## 77. Phase 6 — KI

- KI Workspace;
- contextual KI;
- SEO;
- Titles;
- Descriptions;
- Hashtags;
- platform-specific text;
- Import assistance;
- Comment reply suggestions.

---

## 78. Phase 7 — Audience

- Subscribers;
- Accounts;
- Community;
- Questions/Discussions;
- Comments;
- Unified Community Inbox;
- Notifications;
- simple Poll module;
- external Survey embeds;
- public Community/Themen.

---

## 79. Phase 8 — Live

- Live Studio;
- OBS/RTMP/SRT;
- Streaming Provider integration;
- Recording;
- Media Library conversion;
- public Live page;
- Live Chat;
- multistream architecture;
- Browser Live preparation.

---

## 80. Phase 9 — Monetization

- Access Policies;
- Entitlements;
- Subscriptions;
- One-Time Purchases;
- Donations;
- Payment Provider;
- protected downloads.

---

## 81. Phase 10 — Mobile & Vendor Infrastructure

Сначала:

- installable PWA;
- responsive Media Desktop;
- mobile upload/KI/comment workflows;
- push-ready architecture.

Только после стабильности продукта:

- Central Control;
- Licenses;
- Installation management;
- Support Access;
- Health Monitoring;
- Remote Updates;
- Version Management.

---

# ЧАСТЬ XVII. ПЕРВАЯ ЗАДАЧА ДЛЯ CODEX

## 82. Что сделать первым проходом

Не реализовывать весь Master-ТЗ одной задачей.

На первом этапе:

1. проанализировать существующий repository;
2. не удалять рабочий функционал;
3. описать текущую architecture и предложить module boundaries;
4. подготовить core migrations/models только для Foundation;
5. реализовать Multilanguage Foundation;
6. реализовать Settings System;
7. реализовать Authentication/RBAC foundation;
8. реализовать Media Desktop Shell с утверждённой sidebar architecture;
9. реализовать theme/design-token foundation без hard-coded Manna branding в Core;
10. реализовать Media Library foundation;
11. подготовить Storage abstraction;
12. подготовить Queue/Cache/Search architecture;
13. подготовить Audit Log foundation;
14. подготовить Connector Interfaces;
15. подготовить Central-Control-ready service/API interfaces без создания Central Control;
16. добавить automated tests;
17. документировать архитектурные решения.

Перед реализацией UI Codex должен открыть `UI/approved/README.md`, approved Master UI Kit и соответствующие approved screenshots. Не использовать ранние экспериментальные UI-варианты как источник визуальных решений.

После завершения Phase 1 остановиться и предоставить отчёт:

- что создано;
- какие migrations/models/services/interfaces добавлены;
- какие tests проходят;
- какие решения требуют подтверждения перед Phase 2.

---

## 83. Запреты для Codex

- Не создавать fake/mock integrations и не выдавать их за готовые.
- Не реализовывать внешние API без реальной authorization/error handling.
- Не hard-code Manna data в Core.
- Не удалять существующий рабочий функционал без необходимости и объяснения.
- Не создавать гигантские Controllers/Services, смешивающие независимые домены.
- Не запускать тяжёлые операции синхронно в HTTP request.
- Не делать прямую зависимость runtime от Central Control.
- Не хранить protected content только за obscured public URL.
- Не копировать интерфейс конкретной OS или YouTube Studio один в один — использовать собственный Media Desktop design system.
- Не смешивать approved UI Manna Vom Himmel с историческими preview-концептами из `/UI`.

---

# ЧАСТЬ XVIII. ACCEPTANCE PRINCIPLES

## 84. Главный UX acceptance principle

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
Website + External Platforms
```

Ключевой критерий: **одна рабочая среда для контента, публикации и общения с аудиторией**.

---

## 85. Главный technical acceptance principle

Ни один внешний сервис не должен быть обязательным для существования Media Platform.

При исчезновении YouTube, TikTok, Instagram, Central Control, AI Provider или Update Server собственная библиотека, users, public site и локальные данные продолжают существовать и быть доступными согласно правам.

---

## 86. UI acceptance principle

Все новые экраны должны использовать общий Design System:

- единые typography tokens;
- buttons;
- cards;
- tables;
- forms;
- badges/statuses;
- platform icons;
- empty states;
- toast/alerts;
- modal patterns;
- pagination/filter/search patterns;
- responsive behavior.

Для Manna Vom Himmel визуальным источником истины является `UI/approved/`.

Не создавать для каждого нового раздела независимый визуальный стиль.

---

## 87. Product scope discipline

MVP должен дать работающую основу Media Desktop и Manna Vom Himmel, а не имитацию всех будущих функций.

Лучше полностью реализованный один connector, один processing pipeline и один publishing flow, чем множество декоративных неработающих интеграций.