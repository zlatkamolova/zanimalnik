# Занималник

Сайт с куестове от реалния живот: внимание, нестандартно мислене, интуиция и усет за думите.
Symfony 7.4 LTS · PHP 8.5 · MariaDB 10.11 · Twig + Symfony UX · AssetMapper + Tailwind (без Node).

Деплой към SuperHosting: виж [„Деплой към SuperHosting“](#деплой-към-superhosting) по-долу.

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

## Публични страници

| Адрес | Съдържание |
|---|---|
| `/` | Предизвикателства на деня, четирите пътеки, последните куестове |
| `/pateki/{slug}` | Пътека и нейните публикувани куестове (основни и „на кръстопът“) |
| `/kuest/{slug}` | Куест; ако има PDF — бутон „Изтегли листа на куеста“ и сгъваем преглед |
| `/kuesti` | Всички куестове с филтър по пътека, трудност и таг (Live Component; работи и без JavaScript). Филтрите са в адреса: `/kuesti?pateka=slovo&trudnost=2&tag=navan` |
| `/za-sajta` | Текстът от админа |
| `/sitemap.xml`, `/robots.txt` | За търсачките |

Текстовете от админа минават през HTML sanitizer (`config/packages/html_sanitizer.yaml`): остават само абзаци, заглавия, списъци, удебеляване, курсив и връзки.
Страницата за грешка 404 се вижда локално на http://localhost:8080/_error/404.

## Предизвикателства на деня

Всеки ден за всяка пътека се избира по един куест (`App\Daily\DailyPickService`):

- кандидати са публикуваните куестове на пътеката (основна или допълнителна), отметнати като „Може да бъде предизвикателство на деня“;
- един куест не се повтаря в една пътека в рамките на 14 дни (броят се и дните, избрани на ръка напред във времето);
- ако куестовете не стигат за 14 дни, се взима този, който е използван най-отдавна;
- ако е възможно без да се наруши правилото за 14 дни, един куест не се пада на две пътеки в един ден;
- ден, избран на ръка от админа, никога не се презаписва.

Изборът става от cron (`app:daily-pick` в 00:05), а ако той не е тръгнал — при първото посещение на сайта за деня.
Админ страницата „Предизвикателства на деня“ попълва и показва следващите 7 дни.

```bash
make console c="app:daily-pick"                          # днес
make console c="app:daily-pick --date=2026-12-01 --days=7"
```

## Тестове

```bash
make test
```

Тестовете ползват отделна база `zanimalnik_test` и отделна папка за качвания (`var/test-uploads`),
така че не пипат локалните данни. Базата се създава автоматично при първото стартиране на Docker;
ако контейнерът с базата е създаден преди това: `docker compose exec -T db mariadb -uroot -proot < docker/db/init.sql`.

| Файл | Какво проверява |
|---|---|
| `tests/Unit/DailySignServiceTest.php` | Знакът на деня: еднакъв в рамките на деня, различен на следващия, часова зона на София, нормализиране на името, палитра и контраст, бисквитка |
| `tests/Integration/DailyPickServiceTest.php`, `DailyPickCommandTest.php` | `app:daily-pick`: по един куест на пътека, без повторение в рамките на 14 дни, ръчният избор се запазва, черновите не се избират |
| `tests/Functional/AdminAccessTest.php` | Достъп до админа: анонимен → вход, потребител без роля → 403, администратор → всички страници; вход, изход, грешна парола на български |
| `tests/Functional/AdminQuestUploadTest.php` | Създаване, редакция и публикуване на куест с PDF и корица; грешни файлове и празни полета с български съобщения |
| `tests/Functional/PublicPagesTest.php` | Всички публични страници, SEO таговете, 404, sitemap, без бисквитки, **без английски текст**, черновата не се вижда |
| `tests/Functional/LiveComponentsTest.php` | Филтърът на куестовете и знакът на деня през Live Components |
| `tests/Functional/FixturesTest.php` | Примерните данни: 4 пътеки, 3 роли, ≥ 8 куеста (по 2 на пътека), „Многоликата дума“ с PDF |
| `tests/Unit/SluggerTest.php`, `ContrastTest.php` | Транслитерация на адресите; контраст на символа върху всеки цвят на печат |

## Знак на деня

Посетителят избира основа — зодия, име (до 40 знака) или роля — и получава цвят и число за деня.

- Логиката е в `App\Sign\DailySignService`: `sha256("<дата>|<основа>|<нормализиран вход>")`;
  първите 4 байта избират един от 24 именувани цвята (`App\Sign\SignPalette`), следващите 4 — число 1–99.
  Денят се смята по часовата зона на София.
- Изборът се пази само в бисквитката `zanimalnik_znak` за 1 година (единствената бисквитка на публичната част);
  бутонът „Смени“ я изтрива. Името не се записва на сървъра.
- Тестове: `tests/Unit/DailySignServiceTest.php` (един и същ резултат в рамките на деня, различен на следващия,
  София срещу UTC, нормализиране, контраст на всеки цвят от палитрата).

## Визия

Основата е архаична (пергамент, восъчни печати, свитъци, орнаменти, Cormorant Garamond),
интерактивните елементи са модерни (меню, бутони, филтри, знакът на деня; Manrope и JetBrains Mono).

- Стилове: `assets/styles/` — `tokens.css` (цветове като CSS променливи, светъл и „нощен пергамент“ по
  `prefers-color-scheme`), `base.css`, `components.css`, `sign.css`, `fonts.css`. Текстурата на пергамента е малък
  вграден SVG (без файлове с изображения).
- Печатите на пътеките се рисуват като SVG (`templates/components/PathSeal.html.twig`); цветът на символа се избира
  автоматично за контраст (`App\Util\Contrast`), каквъвто и цвят да зададе админът.
- Достъпност: всички двойки текст/фон са ≥ 4,5:1 (WCAG AA) в двата режима, границите на полетата ≥ 3:1,
  видим focus, „Към съдържанието“, alt текстове, правилна йерархия на заглавията, без хоризонтален скрол на 390 px,
  анимациите се изключват при `prefers-reduced-motion`.

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
| `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_RESET_PASSWORD` | Само временно на сървъра: създаване на първия администратор или смяна на забравена парола (виж по-долу) |

Локалните стойности са в `.env` / `.env.dev`. Тайните стойности за сървъра стоят само в `.env.local` на сървъра.

## Деплой към SuperHosting

### Как работи

```
git push в main
   └─ GitHub Actions: тестове → composer install --no-dev → tailwind:build --minify → asset-map:compile
        ├─ FTP → /home/zaniskyu/zanimalnik/     кодът, vendor/, .env   (извън уеб корена)
        ├─ FTP → /home/zaniskyu/public_html/    index.php, .htaccess, assets/, bundles/   (uploads/ не се пипа)
        └─ FTP → /home/zaniskyu/zanimalnik/var/deploy.flag   (винаги последен)
cron на всеки 5 минути: app:post-deploy
   └─ ако има deploy.flag: миграции → начално съдържание (само в празна база) → cache:clear → лог → маркерът се изтрива
```

На сървъра не се пускат Composer, Node или SSH. Качват се само променените файлове.
Кодът заема около 11 000 файла (inodes) от лимита от 110 000.

Сигурност: в `public_html` има само front controller-ът, компилираните assets и качените файлове.
`.env`, `vendor/`, `var/` и кодът са в `/home/zaniskyu/zanimalnik` и не се виждат през уеба.
`.htaccess` позволява изпълнение само на `index.php` (качен `.php` файл в `uploads/` не може да се изпълни)
и забранява достъпа до скрити файлове.

### Първоначална настройка (еднократно, само през cPanel и браузър)

**1. Премахни WordPress.** cPanel → **File Manager** → `public_html` → отметни всичко **освен** папките
`.well-known` и `cgi-bin` (ако ги има) → **Delete**. Ако WordPress има отделна база, можеш да я изтриеш в
**MySQL Databases** (бекъп не е нужен).
Включи **Settings → Show Hidden Files**, за да видиш и `.htaccess` — той също трябва да се изтрие.

**2. PHP 8.5.** cPanel → **MultiPHP Manager** → отметни `zanimalnik.com` → **PHP 8.5** → Apply.
В **Select PHP Version → Extensions** (ако го има) провери, че са включени: `intl`, `pdo_mysql`, `gd`, `mbstring`, `zip`, `opcache`.

**3. База данни.** cPanel → **Database Wizard**:
- име на базата: `zanimalnik` → става `zaniskyu_zanimalnik`;
- потребител: `zanimalnik` → става `zaniskyu_zanimalnik`; парола: генерирай дълга парола **само с букви и цифри**
  (специалните знаци трябва да се кодират в адреса на базата — така се избягва грешка);
- права: **ALL PRIVILEGES**.

**4. FTP акаунт.** cPanel → **FTP Accounts** → Add FTP Account:
- Log in: напр. `deploy` → става `deploy@zanimalnik.com`;
- силна парола;
- **Directory: изтрий предложеното и остави празно** (или `/`), за да започва от `/home/zaniskyu` —
  акаунтът трябва да вижда и `zanimalnik/`, и `public_html/`;
- запиши сървъра от **Configure FTP Client** (обикновено `ftp.zanimalnik.com`).

**5. Папка за кода и `.env.local`.** File Manager → в `/home/zaniskyu` (горе, **не** в `public_html`) →
**+ Folder** → `zanimalnik`. Влез в нея → **+ File** → `.env.local` → десен бутон → **Edit** и постави:

```dotenv
APP_ENV=prod
APP_SECRET=ПОСТАВИ_32_СЛУЧАЙНИ_БУКВИ_И_ЦИФРИ
DATABASE_URL="mysql://zaniskyu_zanimalnik:ПАРОЛАТА_ОТ_СТЪПКА_3@localhost:3306/zaniskyu_zanimalnik?serverVersion=10.11.19-MariaDB&charset=utf8mb4"
DEFAULT_URI=https://zanimalnik.com

# Само за първия администратор — изтрий двата реда, след като влезеш в /admin
ADMIN_EMAIL=твоят@имейл.bg
ADMIN_PASSWORD=поне-12-знака-силна-парола
```

`APP_SECRET`: в 1Password → нова парола → 32 знака, само букви и цифри.

**6. GitHub.** Репозиторито → **Settings → Secrets and variables → Actions**:
- раздел **Secrets** → New repository secret: `FTP_SERVER` (напр. `ftp.zanimalnik.com`), `FTP_USERNAME`
  (`deploy@zanimalnik.com`), `FTP_PASSWORD`;
- раздел **Variables** → New repository variable: `FTP_PROJECT_DIR` = `zanimalnik/`, `FTP_PUBLIC_DIR` = `public_html/`
  (такива са и стойностите по подразбиране), а ако хостингът не поддържа FTPS — `FTP_PROTOCOL` = `ftp`.

FTP данните стоят само тук, никога в кода.

**7. Cron задачи.** cPanel → **Cron Jobs**. В **Cron Email** можеш да оставиш имейла си: командите не пишат нищо,
когато всичко е наред, така че писмо ще дойде само при проблем.

| Кога | Common Settings | Команда |
|---|---|---|
| Всеки ден 00:05 | минута `5`, час `0`, останалите `*` | `/opt/cpanel/ea-php85/root/usr/bin/php /home/zaniskyu/zanimalnik/bin/console app:daily-pick` |
| На всеки 5 минути | „Once Per Five Minutes“ (`*/5 * * * *`) | `/opt/cpanel/ea-php85/root/usr/bin/php /home/zaniskyu/zanimalnik/bin/console app:post-deploy` |

Ако пътят към PHP 8.5 е друг, cPanel го показва в MultiPHP Manager; часовата зона на сайта е винаги София.

**8. Първи деплой.** GitHub → **Settings → Secrets and variables → Actions → Variables** → добави
`DEPLOY_ENABLED` = `true`. После **Actions → Tests and deploy → Run workflow** (или просто `git push`).
Изчакай зелената отметка (около 3–5 минути) и още до 5 минути за cron.

**9. Провери.**
- File Manager → `zanimalnik/var/log/post-deploy.log`: трябва да завършва с `=== Готово.` и
  `Създаден е администратор …`;
- отвори https://zanimalnik.com — пътеките, примерните куестове и знакът на деня;
- влез в https://zanimalnik.com/admin с имейла и паролата от `.env.local`;
- **изтрий `ADMIN_EMAIL` и `ADMIN_PASSWORD` от `.env.local`.**

### Всеки следващ деплой

`git push` в `main`. GitHub качва само променените файлове; до 5 минути cron пуска миграциите и чисти кеша.
Съдържанието от админа и качените файлове никога не се презаписват.

### Ако нещо се обърка

| Проблем | Какво да направиш |
|---|---|
| Червен X в GitHub Actions | Отвори run-а: при неуспешни тестове нищо не се качва; при FTP грешка провери secrets и `FTP_PROTOCOL` |
| Сайтът показва „Нещо се обърка“ | File Manager → `zanimalnik/var/log/` → `prod-ДАТА.log` (грешките никога не се показват на екрана) |
| Деплоят е минал, но промените не се виждат | Виж `var/log/post-deploy.log`. За ново пускане: File Manager → `zanimalnik/var/` → **+ File** → `deploy.flag` и изчакай до 5 минути |
| Забравена парола за админа | В `.env.local` добави `ADMIN_EMAIL=…`, `ADMIN_PASSWORD=новата-парола` и `ADMIN_RESET_PASSWORD=1`; до 5 минути паролата е сменена (виж `post-deploy.log`). След това изтрий трите реда |
| Стотици писма от cron | Значи командата пише грешка — тя е и в писмото, и в `var/log/` |
