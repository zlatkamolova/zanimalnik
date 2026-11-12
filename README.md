# Занималник

Сайт с куестове от реалния живот: внимание, нестандартно мислене, интуиция и усет за думите.
Symfony 7.4 LTS · PHP 8.5 · MariaDB 10.11 · Twig + Symfony UX · AssetMapper + Tailwind (без Node).

> Пълните инструкции за деплой към SuperHosting ще бъдат добавени в етапа „Деплой“.

## Локална инсталация (Docker)

Нужни са само [Docker Desktop](https://www.docker.com/products/docker-desktop/) и `make`.
PHP на компютъра не се използва, затова версията му няма значение.

```bash
git clone git@github.com:zlatkamolova/zanimalnik.git
cd zanimalnik
make up        # стартира PHP 8.5 + Apache и MariaDB 10.11
make install   # composer install, importmap:install, tailwind:build
make db        # миграции + примерни данни
```

Сайтът е на http://localhost:8080. Базата е достъпна и от компютъра на `127.0.0.1:3307`
(потребител `zanimalnik`, парола `zanimalnik`), например за PhpStorm или TablePlus.

Всички команди: `make help`. Произволна Symfony команда: `make console c="debug:router"`.
Докато променяш стилове, пусни `make css` в отделен терминал.

## Админ панел

Адрес: http://localhost:8080/admin (на сайта: `/admin`). Достъп имат само администратори.

Първият администратор се създава с команда (пита за паролата, поне 12 знака):

```bash
make console c="app:create-admin you@example.com"
```

Смяна на забравена парола: същата команда с `--reset-password`.
`make db` презарежда примерните данни, но запазва администраторите.

| Раздел | Какво има |
|---|---|
| Куестове | Създаване, редакция, публикуване; PDF (до 10 MB) и корица (JPG/PNG/WebP, намалява се автоматично до 1600 px + копие 640 px WebP за картите) |
| Пътеки | Име, адрес, символ (печат), цвят; бутон „Подреди“ за drag & drop |
| Тагове, Роли за знака | Обикновени списъци |
| Предизвикателства на деня | Следващите 7 дни по пътеки; ръчен избор или връщане на автоматичен |
| Страница „За сайта“ | Заглавие и текст |

Качените файлове отиват в `public_html/uploads/quests/{pdf,covers}` (на сървъра: `/home/zaniskyu/public_html/uploads/…`).
След 5 грешни пароли за минута входът се блокира временно.

## Структура: защо `public_html`

На хостинга уеб коренът е заключен на `/home/zaniskyu/public_html`, а кодът е отделно
в `/home/zaniskyu/zanimalnik`. За да е същото локално:

- уеб директорията в проекта се казва `public_html` (`extra.public-dir` в `composer.json`);
- `public_html/index.php` търси `vendor/` първо в родителската папка (локално), после в `../zanimalnik` (сървъра);
- `App\Kernel::getPublicDir()` открива къде е уеб коренът и насочва AssetMapper и качените файлове там.
  На сървъра папката на проекта никога не съдържа `public_html`. По това Kernel разбира, че е на сървъра.

## Променливи на средата

| Променлива | Описание |
|---|---|
| `APP_ENV` | `dev` локално, `prod` на сървъра |
| `APP_SECRET` | Случаен низ от 32 знака, различен на сървъра |
| `DATABASE_URL` | `mysql://USER:PASS@HOST:3306/DB?serverVersion=10.11.19-MariaDB&charset=utf8mb4` |
| `DEFAULT_URI` | Пълен адрес на сайта, ползва се при генериране на линкове от конзолата (sitemap) |

Локалните стойности са в `.env` / `.env.dev`. Тайните стойности за сървъра стоят само в `.env.local` на сървъра.

## Cron задачи (на сървъра)

| Кога | Команда |
|---|---|
| Всеки ден 00:05 | `/opt/cpanel/ea-php85/root/usr/bin/php /home/zaniskyu/zanimalnik/bin/console app:daily-pick` |
| На всеки 5 минути | `/opt/cpanel/ea-php85/root/usr/bin/php /home/zaniskyu/zanimalnik/bin/console app:post-deploy` |
