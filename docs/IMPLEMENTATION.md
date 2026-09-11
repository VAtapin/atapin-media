# Реализация Atapin Media

Пользователь согласовал реализацию всего MASTER-ТЗ с проверками, commit/push и серверными командами после логических этапов. Дополнительная остановка после Foundation отменена этой инструкцией. Недоступные внешние подключения не подменяются имитациями.

- [ ] Foundation: Laravel, SQL, auth/RBAC, настройки, локализация, Desktop, media/storage, queue/cache/search/audit, deployment.
- [ ] Workflow: проекты, задачи, календарь, продолжение работы.
- [ ] Content: редактор, темы, книги/PDF, импорт, публичные страницы, поиск.
- [ ] Media: видео, Shorts, плейлисты, обработка, Podcast.
- [ ] Publishing: реальные подключения, начиная с YouTube.
- [ ] KI: провайдер, предложения с подтверждением.
- [ ] Audience: подписчики, аккаунты, Community, модерация, опросы.
- [ ] Live: OBS, провайдер, записи, Live, чат.
- [ ] Monetization: платежи, права, покупки, подписки, пожертвования.
- [ ] Mobile/vendor: PWA, управление установками.

## Архитектура

`platform/` — Laravel, PHP 8.4, MySQL/MariaDB, Blade с прогрессивным JavaScript. Сервисы содержат бизнес-операции, Jobs — длительные операции. Бренд — конфигурация; Core не зависит от Manna.

Production document root: `httpdocs/platform/public`. Git остаётся в `httpdocs`. Конфигурация и storage — `private/atapin-platform`. Оригиналы существующих архивов остаются на прежних местах. Entry points сохраняют `/upload/`. Document root меняется после успешного install/check.

Media хранит устойчивый ID, диск и относительный ключ. Импорт не публикует материалы. Права проверяются по разрешениям. Общий пароль загрузчика независим от аккаунтов редакции. Queue/cache первоначально database, Redis через config. Worker работает от пользователя сайта, scheduler — каждую минуту. В базе время UTC; UI — часовой пояс установки. Строки интерфейса — translation keys.

## Проверка

Локальный Windows блокирует установленный PHP. Ограничение не обходим: PHP runtime, SQL migrations и feature tests проверяются Linux CI. Локально — PHP parser и JavaScript. Каждый этап получает проверенный commit и воспроизводимую установку.
