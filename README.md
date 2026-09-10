# Atapin Media

**Independent self-hosted media platform with a public website and a creator-focused Media Desktop.**

Первый реальный deployment проекта — **Manna Vom Himmel**.

> Media Platform должна стать для владельца основным рабочим местом: создать материал, отредактировать его, обработать с KI, сохранить, запланировать, опубликовать, провести Live и ответить аудитории — всё из одного интерфейса.

---

## Что мы создаём

Atapin Media — не обычная CMS и не SaaS-панель. Это самостоятельная **single-tenant Media Platform**, устанавливаемая отдельно для каждого клиента.

Каждая установка имеет собственные:

- домен и branding;
- приложение и базу данных;
- пользователей, роли и подписчиков;
- Media Library и storage;
- Social-Media подключения;
- AI-, mail- и payment-настройки;
- публичный сайт;
- Media Desktop;
- конфигурацию, категории, меню и дизайн-токены.

Внешние платформы вроде YouTube, Instagram, TikTok, Facebook или Telegram являются **каналами распространения**, а собственная Media Platform остаётся **Source of Truth** и архивом контента.

---

## Две стороны продукта

### 1. Public Website

Публичная лицевая часть для читателей, зрителей и слушателей. Она визуально отличается от админки и строится из настраиваемых модулей.

Для Manna Vom Himmel основная навигация:

- **Startseite**
- **Beiträge**
- **Videos**
- **Bücher & PDF**
- **Live**
- **Podcast**
- **Community**
- **Themen**
- **Über uns**

Дополнительно: глобальный поиск, Anmeldung/Account и Newsletter.

Публичный сайт должен быть живым: если идёт трансляция, сверху автоматически появляется Live-Hinweis; если активен опрос — он показывается на соответствующих страницах; новые материалы, комментарии, эфиры и подборки обновляются из собственных данных платформы.

### 2. Media Desktop

Административная часть воспринимается как **рабочий медиа-компьютер**, а не как CRUD Dashboard.

Рекомендуемая структура навигации:

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

Группы могут сворачиваться, а права пользователя определяют, какие пункты доступны.

---

## Media Desktop — главный экран

Desktop должен сразу отвечать на вопросы:

- над чем я сейчас работаю;
- что публикуется следующим;
- что загружается или обрабатывается;
- идёт ли Live;
- что пишут люди на разных платформах;
- есть ли проблемы с публикацией или интеграциями;
- что можно сделать прямо сейчас.

Ключевые элементы:

- **Weiterarbeiten / Aktuelles Projekt** с текущим workflow;
- Nächste Veröffentlichung;
- Live starten / Live Status;
- Uploads / Processing;
- Neue Videos;
- Letzte Beiträge;
- Kommentare / Fragen со значком платформы и быстрым ответом;
- Subscribers / Community growth;
- Social Status;
- Speicher;
- Systemstatus / Fehler;
- контекстные KI-Vorschläge;
- Schnellaktionen: Neuer Beitrag, Neues Video, Live starten, PDF hochladen, Veröffentlichen.

Widgets должны быть настраиваемыми: пользователь может скрывать, перемещать и при необходимости менять размер блоков. Технические ошибки обычному редактору показываются только тогда, когда они требуют действий; Admin/Owner может видеть полный Systemstatus.

---

## Основной рабочий процесс

```text
MEDIA DESKTOP
    |
    +-- Create
    +-- Edit
    +-- KI
    +-- Store
    +-- Plan
    +-- Live
    +-- Publish
    +-- Communicate
    |
    v
Website + External Platforms
```

Один **Projekt** может объединять несколько связанных материалов: Beitrag, Video, Podcast, PDF, Social Posts и Live.

Пример workflow видео:

```text
Idee -> Skript -> Produktion -> Feinschliff -> Veröffentlichung
```

Пример workflow статьи:

```text
Entwurf -> Redaktion -> Medien -> SEO/KI -> Vorschau -> Veröffentlichung
```

Пользователь должен продолжать работу с того места, где остановился, без поиска нужного раздела.

---

## Основные модули

### Projekte

Объединяет материалы в реальные рабочие проекты и серии. Показывает прогресс, текущую фазу, следующую задачу, дедлайн, ответственного и связанные материалы.

### Aufgaben

Kanban и табличное представление:

- Offen
- In Arbeit
- Warten
- Erledigt

Дополнительно: Meine Aufgaben, приоритеты, сроки, ответственные, checklist, фильтры по проекту и типу контента.

### Kalender / Planung

Редакционный календарь с представлениями:

- Monat
- Woche
- Liste

В одном календаре отображаются Beiträge, Videos, Podcasts, PDF, Social Posts и Live. Архитектура должна позволять drag & drop планирование.

### Beiträge

Полноценный редактор текстовых публикаций с Cover, Author, Kategorien, Tags, SEO, Access Rules, scheduling и generated PDF.

### Videos

Админка в логике Creator/YouTube Studio: таблица всех видео, thumbnails, status, platforms, playlists/series, views, comments, duration, publish date и быстрые действия.

### Podcast

Серии и эпизоды, audio player, cover, description, transcript, publish date, external destinations и "Weiterhören" для зарегистрированного пользователя.

### Bücher & PDF

Книги, брошюры, PDF-материалы и документы: Cover, Author, Preview, Download, external shop link, free/paid access и категории.

### Media Library

Единый каталог Video, Audio, Image, PDF, Document, Thumbnail, Recording и других файлов с metadata, Search, Filter, Tags, Collections и Usage References.

### Import Center

Импорт существующего архива из:

- DOCX/TXT/HTML/PDF;
- локальных Video/Audio/Image файлов;
- ZIP;
- URL/RSS;
- подключённых платформ, если это разрешено их API.

Импортированный контент проходит Review/Zuordnung перед публикацией.

### Publishing

Единый Publishing Workspace. Пользователь выбирает контент и destinations, например Website, YouTube, Instagram, TikTok, Facebook или Telegram. Для каждой платформы доступны собственные Title, Description, Caption, Hashtags, Thumbnail, Visibility и Publish Time.

### Community

Не отдельный "форум ради форума", а единое пространство общения:

- собственные Kommentare;
- Fragen;
- Diskussionen;
- Live Questions;
- Polls;
- Moderation;
- общий Inbox комментариев и вопросов из поддерживаемых внешних платформ.

На Media Desktop и в Community Inbox всегда должно быть видно, **с какой платформы пришло сообщение**, и при поддержке API пользователь отвечает прямо из Media Desktop.

### Subscribers

Собственная база аудитории: E-Mail Subscription, Account Registration, Double Opt-In, Preferences, Unsubscribe и сегменты.

### Live Studio

Профессиональный центр Live: Preview, Stream Status, destinations, Viewer Count, Recording, Chat/Questions, Start/Stop. Основной профессиональный вход — OBS/RTMP/SRT; Browser Live предусматривается как дополнительный режим.

### KI-Assistent

KI — не отдельная игрушка, а встроенный помощник во всех рабочих процессах.

Примеры действий:

- Titel verbessern;
- Zusammenfassung erstellen;
- SEO-Titel & Keywords;
- Meta-Beschreibung;
- Hashtags generieren;
- YouTube-Beschreibung;
- Social Caption;
- Bibelstellen strukturieren;
- Text kürzen/verlängern;
- in anderes Format umwandeln;
- Antwort auf Kommentar vorschlagen.

Результат KI всегда показывается пользователю до принятия.

### Analytics

Собственная статистика сайта и, где возможно, агрегированные данные внешних платформ: Views, Downloads, Subscribers, Registrations, Live Views, Popular Content/Search Terms, Kommentare и Outbound Social Clicks.

---

## Public Website — обязательные экраны

Для первой установки должны быть предусмотрены как минимум:

- Startseite;
- Beiträge overview + Einzelbeitrag;
- Videos overview + Video detail;
- Bücher & PDF / Bibliothek + detail;
- Live overview + active Live page + replay;
- Podcast overview + episode/series;
- Community overview + discussion/question views;
- Themen overview + cross-content topic page;
- Search results;
- Über uns;
- Login/Register/Account;
- Newsletter subscription/preferences.

Главная Manna Vom Himmel может показывать актуальный Live, Umfrage, Beitrag des Tages, Neue Videos, Bücher/PDF, Themen и Newsletter.

---

## Design System

Core должен поддерживать **theme/design tokens**, а не hard-coded branding первого клиента.

Для **Manna Vom Himmel** утверждён визуальный язык:

- Logo/Mark: стилизованная раскрытая книга / M + свет сверху;
- Brand palette: Dunkelblau, Gold, Himmelblau, Cremeweiß;
- Headings/Public Editorial: **Playfair Display**;
- Interface/Text: **Inter**;
- Public CTA: преимущественно Gold;
- Admin Primary Action: Blue/Navy;
- мягкие светлые cards, спокойная editorial-подача;
- Scripture/brand quotes могут использоваться как ненавязчивые фирменные элементы.

Public Website и Media Desktop должны выглядеть родственными, но не одинаковыми: Public — editorial и эмоциональный, Desktop — плотный и функциональный.

---

## Независимость и self-hosted принцип

Central Control, AI provider, YouTube или любой другой внешний сервис **не является runtime dependency**.

Если внешний сервис исчез или временно недоступен, собственные:

- Public Website;
- Media Desktop;
- пользователи;
- библиотека;
- ранее приобретённый/защищённый контент;
- локальный поиск;
- локальные файлы;
- основные очереди и scheduler

продолжают работать.

Central Control в будущем отвечает только за лицензирование, support access, update notifications, health monitoring и управление установками.

---

## Архитектурные принципы

- Laravel-based backend.
- API/Service-first business logic.
- Thin Controllers.
- Actions / Services / Jobs / Policies / Events / Listeners / DTO where useful.
- Queue для тяжёлых операций.
- Laravel Cache abstraction, Redis-ready.
- Laravel Filesystem abstraction, Local + S3-compatible storage.
- Search abstraction через `SearchProviderInterface` / Laravel Scout-compatible provider.
- Внешние сервисы только через Interfaces/Adapters.
- Никаких клиентских credentials в Central Control по умолчанию.
- Responsive UI и installable PWA foundation.
- 2FA-ready auth, RBAC, audit log, encrypted secrets, signed URLs и secure protected downloads.

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

## Multilanguage

Core мультиязычный с первого дня: UI-строки только через translation keys.

Первая установка **Manna Vom Himmel** полностью немецкоязычная:

```text
default_locale = de
enabled_locales = [de]
```

Сам Core не должен зависеть от немецкого языка. Архитектурно контент может иметь `locale` и optional `translation_group_id`.

---

## Manna Vom Himmel не является Core

Нельзя использовать `MannaVomHimmel` в именах generic Core-классов.

Manna — только первая клиентская конфигурация:

- German locale;
- Branding и Design Tokens;
- категории и темы;
- navigation;
- существующие тексты, видео и документы;
- Social Accounts;
- PDF Template;
- Newsletter и публичные блоки.

---

## Roadmap

1. **Foundation** — auth, roles, settings, i18n, Media Desktop shell, storage, Media Library, queue/cache/search/audit/API foundation.
2. **Workflow** — Projekte, Aufgaben, universal Content Editor, Kalender.
3. **Content** — Beiträge, Bücher/PDF, categories/tags/collections, imports, PDF generator.
4. **Media** — video upload/processing, Video Studio, Podcast, playlists/series.
5. **Publishing & Integrations** — Publishing Workspace, YouTube first, then other connectors through the same adapter layer.
6. **KI** — contextual assistant and platform-specific preparation.
7. **Audience** — Subscribers, Community, unified comments/questions Inbox, notifications and Polls.
8. **Live** — Live Studio, OBS/RTMP/SRT, recording, multistream architecture, Browser Live preparation.
9. **Monetization** — access policies, entitlements, subscription/purchase/donation providers.
10. **Mobile & Vendor Infrastructure** — PWA/mobile workflows first; Central Control/licensing/remote updates only after the platform is stable.

Не реализовывать весь roadmap одним проходом.

---

## Что не делать

- не превращать Media Desktop в обычную CRUD admin panel;
- не hard-code Manna branding/categories в Core;
- не делать YouTube специальным исключением архитектуры;
- не хранить только external URLs вместо собственного архива;
- не выдавать mock/fake integrations за готовые;
- не блокировать установку при недоступности license/update server;
- не пытаться в первом релизе воспроизвести весь OBS;
- не строить полноценную социальную сеть до того, как готовы базовые Community workflows;
- не запускать тяжёлые операции внутри обычного HTTP request.

---

## Подробное техническое задание

Полная актуальная спецификация находится в [`MASTER-TZ.md`](./MASTER-TZ.md).

---

## Статус

Проект находится на этапе архитектуры и реализации foundation. UI/UX-концепция Media Desktop и публичного Manna Vom Himmel определена и должна использоваться как ориентир при дальнейшей реализации.