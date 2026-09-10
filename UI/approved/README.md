# UI Approved — Manna Vom Himmel

Эта папка содержит **утверждённый визуальный референс**, который должен использоваться при реализации Manna Vom Himmel.

## Статус

**APPROVED / SOURCE OF TRUTH FOR VISUAL IMPLEMENTATION**

Утверждённая публичная концепция: **Frontend Gold Editorial B**.

При создании интерфейса Codex должен ориентироваться прежде всего на файлы из этой папки, а не на старые концепты, лежащие в `/UI` или на устаревшие preview-файлы в корне репозитория.

## Что является утверждённым

### Public Website — Gold Editorial B

В `frontend-gold-b/` находятся утверждённые экраны:

1. `01-start.png` — Startseite
2. `02-videos.png` — Videos Übersicht
3. `03-video-detail.png` — Video Detail
4. `04-beitraege.png` — Beiträge Übersicht
5. `05-beitrag-detail.png` — Beitrag Detail
6. `06-buecher.png` — Bücher & PDF / Bibliothek
7. `07-buch-detail.png` — Buch Detail
8. `08-live.png` — Live / Livestreams
9. `09-podcast.png` — Podcast
10. `10-community.png` — Community

`preview.html` позволяет просматривать утверждённую серию как единый интерактивный visual reference.

### Design System

В `design-system/master-ui-kit.png` находится утверждённый Master UI Kit Manna Vom Himmel.

Он задаёт визуальное направление для:

- логотипа и mark/icon;
- цветов;
- Playfair Display + Inter;
- public header;
- Media Desktop sidebar/header;
- кнопок;
- карточек;
- таблиц;
- badges/statuses;
- форм;
- alert/toast;
- media controls;
- spacing/radius/shadow/icon principles.

## Правила для Codex

1. **Не смешивать утверждённый Gold Editorial B с другими экспериментальными UI-концептами.**
2. Старые изображения в `/UI` считать историческими материалами, если на них нет отдельной ссылки из этой папки.
3. Внешний сайт должен следовать approved screenshots по композиции, визуальной иерархии, типографике, цветовой логике, карточкам, spacing и общему ощущению.
4. Скриншоты — визуальный референс, а не источник точных текстов или фиктивных данных. Реальные тексты, данные и функциональность должны приходить из приложения.
5. Если визуальный референс и `MASTER-TZ.md` расходятся по функциональности, **`MASTER-TZ.md` определяет функциональность**, а эта папка определяет визуальное направление.
6. Если нужный экран ещё не нарисован (например Themen, Über uns, Search, Account), его следует строить из тех же approved компонентов и Design System, без создания нового стиля.
7. Public Website и Media Desktop должны выглядеть как одна бренд-система, но Public Website остаётся более editorial/emotional, а Media Desktop — более плотным и рабочим.
8. Основной домен Manna Vom Himmel: `mannavomhimmel.de`; `manna-vom-himmel.de` — только alias с 301 redirect.

## Бренд Manna Vom Himmel

Ключевой визуальный язык:

- открытая книга / стилизованная M;
- свет / лучи сверху;
- Dunkelblau / Navy;
- Gold;
- Himmelblau;
- Cremeweiß;
- Playfair Display для editorial headings;
- Inter для интерфейса и основного текста;
- спокойная, современная, христианская подача;
- фотографии и атмосферные backgrounds не должны ухудшать читаемость интерфейса.

## Важно

Эта папка создана специально для того, чтобы при разработке не выбирать заново между десятками ранних вариантов дизайна.

**Новые экраны Manna Vom Himmel должны продолжать этот дизайн, а не создавать новую визуальную концепцию.**
