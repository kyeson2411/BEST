# Sibol

Sibol ("to sprout") is the learning companion of **B.E.S.T. College of Polomolok**, Polomolok, South Cotabato.
Its sign-in page shows the school's seal and a photograph of its students. Students get their classes,
assignments, quizzes, feedback, a study calendar and a private AI companion that explains, quizzes
and cites their teachers' own materials. Teachers manage classes, materials, lessons, assignments,
quizzes, grading and class insights, with AI drafting that always waits for their review, and copy
what they build in one section to the other sections they teach.
School administrators manage people, classes, policies, AI limits, privacy requests and the audit log,
without access to students' private conversations.

It is plain PHP with MySQL/MariaDB and vanilla HTML, CSS and JavaScript, so it runs under XAMPP
with no build step and no Node.js at runtime.

> **Out of the box Sibol runs in demo mode.** The demo school, its people and its records are fictional,
> and the companion answers with passages from the class materials, labelled as demo answers, without
> calling any AI service. Connect a real AI provider in `.env` when you are ready (see
> [AI provider](#ai-provider)).

---

## Contents

1. [Requirements](#requirements)
2. [Install on XAMPP](#install-on-xampp)
3. [Database: command line, setup page or phpMyAdmin](#database)
4. [Demo accounts](#demo-accounts)
5. [Configuration (.env)](#configuration-env)
6. [AI provider](#ai-provider)
7. [Email (optional)](#email-optional)
8. [Command-line tools](#command-line-tools)
9. [Tests](#tests)
10. [Versions and performance](#versions-and-performance)
11. [Project layout](#project-layout)
12. [Deploying for real use](#deploying-for-real-use)
13. [Backups and restoring](#backups-and-restoring)
14. [Security and Privacy Notes](#security-and-privacy-notes)
15. [Accessibility and languages](#accessibility-and-languages)
16. [Known limitations](#known-limitations)

---

## Requirements

| | Minimum | Recommended |
|---|---|---|
| PHP | 8.0 (what current XAMPP 8.0.x ships) | **8.3** (both test suites pass on 8.0.30 and 8.3.35): PHP 8.0 and 8.1 no longer receive security fixes |
| Database | MariaDB 10.4 (XAMPP) | **MariaDB 10.11** (both test suites pass on 10.4 and 10.11.19); MySQL 8.4 should work but has not been tried |
| Web server | Apache 2.4 with `mod_rewrite` and `.htaccess` (`AllowOverride All`, the XAMPP default) | same |
| PHP extensions | `pdo_mysql`, `mbstring`, `json`, `fileinfo` | plus `curl` (live AI), `zip` (Word/PowerPoint materials), `openssl` |
| Composer | for PDF text extraction (`smalot/pdfparser`), optional email (`phpmailer/phpmailer`) and PHPUnit | |
| Node.js 18+ | only for browser tests and for re-copying fonts/icons | |

The interface works in current Chrome, Edge, Firefox and Safari, on phones from 360 px wide.

## Install on XAMPP

1. Put the project in `C:\xampp\htdocs\BEST` (any folder name works).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Copy `.env.example` to `.env`. The defaults suit a local XAMPP install (`root`, no password, demo mode).
4. Install the PHP libraries (needed to read PDF materials, and for the PHP tests). Install Composer from
   [getcomposer.org](https://getcomposer.org/download/) (on Windows, point its installer at
   `C:\xampp\php\php.exe`), then run:
   ```bat
   cd C:\xampp\htdocs\BEST
   composer install
   ```
   If the `vendor` folder is already present, this step is done. Without it Sibol still runs, but PDF
   materials are marked "could not be read" and PHPUnit is unavailable.
5. Create the database and the demo school (next section).
6. Open **http://localhost/BEST/** (it forwards to `/BEST/public/`). If you use another folder or host,
   set `APP_URL` in `.env` to the full URL of the `public` folder.

The project's root `.htaccess` blocks every folder except `public/`, so source code, `.env`, uploads and logs
cannot be fetched from a browser even though XAMPP serves all of `htdocs`.

## Database

Pick one of three ways. All of them create the same tables (`database/migrations/*.sql`).

**A. Command line (recommended)**

```bat
C:\xampp\php\php.exe bin\sibol.php migrate
C:\xampp\php\php.exe bin\sibol.php db:seed
```

`migrate` creates the database named in `DB_NAME` if it does not exist. `db:seed` loads the fictional demo
school into an empty database. `db:reset` drops everything (including uploaded files) and seeds again;
it only runs with `APP_ENV=local`.

**B. Setup page.** With `APP_ENV=local`, open **http://localhost/BEST/public/setup** from the same computer.
It shows configuration checks and buttons to create the tables, load the demo school or reset it. The page
returns "not found" in production and from other computers.

**C. phpMyAdmin import**

1. Regenerate the combined schema if you changed migrations: `C:\xampp\php\php.exe bin\sibol.php schema:build`.
2. In phpMyAdmin, create a database named `sibol` with collation **`utf8mb4_unicode_ci`**.
3. Open it, choose **Import**, select `database/schema.sql`, and run it.
4. Load the demo school with `bin\sibol.php db:seed` or the setup page (the seed creates files and indexes
   materials, so it is not a plain SQL file).

## Demo accounts

All demo accounts share the password **`Sibol-demo-2026`**. On a local install the sign-in page also has
one-click buttons for a student, a teacher and an administrator (only when `APP_ENV=local`,
`APP_DEMO_LOGIN=true` and you browse from the same computer).

Like many students at the school, the demo students have no email address: they sign in with a username.
Staff can sign in with their username or their email (`ramon.dizon@demo.sibol.test`, and so on).

| Role | Username | Notes |
|---|---|---|
| School administrator | `teresa.villanueva` | |
| Teacher | `ramon.dizon` | Science 9 – Narra and Molave; adviser of Grade 9 Narra |
| Teacher | `liza.mercado` | Mathematics 9 – Narra and Molave; adviser of Grade 9 Molave |
| Teacher | `joy.santiago` | English 9 – Narra |
| Student | `andrea.cruz` | Grade 9 Narra: graded work, feedback, companion history |
| Student | `janelle.ocampo` | Grade 9 Molave |
| Student | `luis.pascual` | Grade 9 Molave: AI companion switched off after his guardian said no |
| Student | `trisha.mendoza` | Grade 9 Molave: guardian said yes to the AI companion |
| Invited (no password yet) | `carmela.reyes` (teacher), `rafael.domingo` (student) | print a sign-in slip or create a link |
| Deactivated | `angelica.santos` | cannot sign in |

The other 14 students follow the same `given.family` pattern. The demo school takes the real
school's name with "(Demo)" added (from `SCHOOL_NAME`) so the screens look like the finished product, but every
person, class, grade and conversation in it is invented. Every demo record is flagged in the database and the
interface shows a "Demo school" band.

## Configuration (.env)

Real environment variables (for example Apache `SetEnv`) override `.env`, so production can keep secrets out
of files on disk.

| Variable | Default | Meaning |
|---|---|---|
| `APP_ENV` | `local` | `local` shows developer error details, the setup page and demo sign-in. Use `production` for real use. |
| `APP_URL` | `http://localhost/BEST/public` | Full URL of `public/`, used in invitation links. |
| `APP_NAME` | `Sibol` | Product name in the interface. |
| `APP_TIMEZONE` | `Asia/Manila` | Default timezone; each school and person can set their own. |
| `APP_LOCALE` | `en` | Default language (`en` or `fil`) before a school or person chooses. |
| `SCHOOL_NAME`, `SCHOOL_SHORT_NAME`, `SCHOOL_LOCATION` | B.E.S.T. College of Polomolok, B.E.S.T., Polomolok, South Cotabato | The school shown on the sign-in page and used to name the demo school. |
| `SCHOOL_FOUNDED`, `SCHOOL_MOTTO` | `1995`, "I am the Way, the Truth, the Life." | Shown on the photo's label on the sign-in page. Leave the motto empty to hide it. |
| `SCHOOL_PHOTO_ALT` | empty | Description of the sign-in photograph for screen readers; set it if you replace the photo. |
| `APP_DEMO_LOGIN` | `true` | One-click demo sign-in (local only, same computer only). |
| `SESSION_SECURE_COOKIE` | `false` | Set `true` when served over HTTPS. |
| `SESSION_IDLE_MINUTES` | `120` | Sign out after this long without activity. |
| `APP_KEY` | empty | Encrypts two-step sign-in secrets in the database. Make one with `bin\sibol.php key:generate` (it writes it into `.env`). Keep a copy apart from database backups; if it changes or is lost, everyone who uses two-step sign-in needs it switched off and set up again. |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | XAMPP defaults | Database connection. |
| `DB_DUMP_PATH` | empty | Full path to `mysqldump` (or `mariadb-dump`) for `backup`. Empty uses XAMPP's or one on the `PATH`. |
| `AI_PROVIDER` | `demo` | `demo`, `anthropic` or `openai_compatible`. |
| `AI_API_KEY` | empty | Provider key. Read only on the server. |
| `AI_MODEL` | empty | Model name. Empty means `claude-opus-5-5` for `anthropic`. Required for `openai_compatible`. |
| `AI_BASE_URL` | empty | `openai_compatible` only, e.g. `https://api.groq.com/openai/v1`. |
| `AI_TIMEOUT_SECONDS` | `90` | Per-request timeout. |
| `AI_EFFORT` | `medium` | Claude only: `low`, `medium` or `high` reasoning effort. |
| `AI_FALLBACKS` | `default` | Claude only: `default` or `off` (see below). |
| `AI_PRICE_INPUT_PER_MTOK`, `AI_PRICE_OUTPUT_PER_MTOK` | empty | Your prices in USD per million tokens for the cost estimate. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` | empty, `587`, `tls` | Optional SMTP server; empty host keeps email off (see [Email](#email-optional)). `ssl` for port 465; `none` only for a test server on this computer. |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | empty | SMTP sign-in. The password is read only on the server, never shown or logged. |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | empty, `Sibol` | The sender people see. |
| `UPLOAD_MAX_MB` | `20` | Largest upload; also raise `upload_max_filesize` and `post_max_size` in `php.ini`. |
| `LOG_LEVEL` | `info` | `debug`, `info`, `warning` or `error`. Logs go to `storage/logs/sibol.log`. |

School-level policies (AI on/off, companion modes, daily limits, monthly token budget, feature switches,
class codes, retention, notifications) are set by administrators in **Policies**, not in `.env`.

### School seal and photograph

The site root (`/BEST/`) is the school's sign-in page: the photograph of the students fills one side, the page
with the sign-in form the other, and the seal is pinned across the seam between them (across the photo's lower
edge on phones). The seal is also the logo in the app's sidebar and the browser tab icon.

To change either image, replace the originals in `tools/school-assets/` (`campus.jpg`, a landscape photo;
`seal.jpg`, the seal on a plain white background) and run:

```bat
node tools/school-assets.mjs
```

It writes the web sizes to `public/assets/school/` (WebP with a JPEG or PNG fallback), cuts the seal out of its
white background, and drops any camera or location data from the files. It also makes the icons used when Sibol
is installed as an app (`app-icon-192.png`, `app-icon-512.png`, and `app-icon-maskable-192.png` and
`app-icon-maskable-512.png` with extra room for phones that crop icons into a circle). An administrator can also upload a different logo in
**School settings**, which replaces the seal inside the app.

Only publish photographs of students that the school has consent to use for its website.

## AI provider

All AI calls happen on the server. The browser never sees a key, a provider address or a raw prompt.

**Demo mode (`AI_PROVIDER=demo`).** No external calls. The companion finds the most relevant passages in the
student's released materials and quotes them with page references, under a "Demo mode" label. Practice sets,
flashcards, question drafts and lesson drafts are built the same way. It never presents itself as a model.

**Claude (`AI_PROVIDER=anthropic`).**

```env
AI_PROVIDER=anthropic
AI_API_KEY=your-key-from-console.anthropic.com
AI_MODEL=claude-opus-5-5
```

- The integration calls the Anthropic Messages API over HTTPS with streaming. It does not use the official
  PHP SDK because that SDK needs PHP 8.1+, while XAMPP here ships PHP 8.0. After upgrading PHP, the adapter in
  `app/Services/Ai/AnthropicProvider.php` can be replaced by the SDK behind the same interface.
- Current Claude models decide their own thinking depth; Sibol sends `AI_EFFORT` as `output_config.effort`
  and sends no temperature.
- With `AI_FALLBACKS=default`, requests opt into the API's server-side fallback (beta header
  `server-side-fallback-2026-07-01`): if a safety classifier declines a request, the API can answer with a
  fallback model instead of returning nothing. If your account is not enabled for that beta, Sibol notices
  the error and continues without it. A declined answer is shown as declined and never half-displayed.
- **The live Claude path has not been exercised in this build** because no API key was available. It was
  written against the documented API and its error handling is covered by code review only. Run the AI check
  (below) with the key before students use it.

**Other providers (`AI_PROVIDER=openai_compatible`).** Any service with an OpenAI-style
`/chat/completions` endpoint (including local servers such as Ollama or LM Studio). Set `AI_BASE_URL`,
`AI_MODEL` and, if needed, `AI_API_KEY`. This path was also not tested against a live service.

**What is sent.** The question, the recent messages of that conversation, the numbered passages retrieved
from the student's own released materials, and instructions. Names, emails and grades are not sent.
Check your provider's data-retention terms and your school's agreement before enabling live AI.

**Cost and limits.** Every request is counted (feature, role, status, tokens, estimated cost; never text).
Administrators see totals in **AI usage** and set per-day limits and a monthly token budget in **Policies**.
The built-in price table in `app/Services/Ai/Usage.php` is an estimate; set `AI_PRICE_*` to your contract
prices. Your provider's invoice is the authority.

### Checking the AI before going live

`bin\sibol.php ai:eval` sends 30 fixed student and teacher prompts about the demo school's materials through the
real companion (limits, permission-filtered retrieval, prompt, provider, citation checks, storage) and prints a
pass/fail table with each case's tokens and estimated cost. It checks that:

- citations point only to passages that were supplied, cited passages are about the question, and quoted words
  and page numbers come from those passages; no passage comes from outside the person's classes;
- when the materials do not cover a question, the answer says so first, including when a passage only looks
  related and when the reading belongs to another section;
- asked to write a lab report, a reading response or a submission, or for quiz answers, the companion helps
  instead of doing the work;
- Filipino sessions, questions written in Filipino and requests for Filipino are answered in Filipino;
- a provider refusal shows the safe message and drops the partial text (simulated, because a refusal cannot be
  triggered safely on purpose), and the daily limit, the per-minute limit and the school's AI switch stop a
  request before it reaches the provider;
- teacher tools ground their drafts in the class materials.

It runs on the fictional demo school, so use a local copy (after `db:reset`) with the same `AI_*` settings as the
server. Each case's conversation, messages and usage are deleted when it finishes, policies are changed only in
memory, and the run is audit-logged with counts and token totals. It exits with status 1 if a case fails.

```bat
C:\xampp\php\php.exe bin\sibol.php ai:eval                         :: the provider in .env
C:\xampp\php\php.exe bin\sibol.php ai:eval --demo                  :: demo mode, whatever .env says
C:\xampp\php\php.exe bin\sibol.php ai:eval --case=B02,D04 --show   :: some cases, with their answers
```

In demo mode all 30 cases pass at no cost. With a model, 26 cases make one request each (the refusal and limit
cases never reach the provider); the cost of a run has not been measured yet, so read it from the table. The
checks are patterns, not judgement: read a live run's answers with `--show` before going live, and treat a pass
as a floor, not a guarantee about other questions.

## Email (optional)

Email is off until `MAIL_HOST` and `MAIL_FROM_ADDRESS` are set in `.env`. While it is off, Sibol works as before:
administrators copy invitation and password links (or print sign-in slips) and share them, and Sibol never claims
an email was sent. With email on:
- a new invitation or password link is also emailed to the person, in their own language, when their account has
  an address. The link is still shown once to copy, in case the email does not arrive. After a CSV import,
  **Email N invitations** sends the new links to everyone who has an address.
- people with an address can turn on **Email me a daily summary of new notifications** under **Account and
  preferences**. The summary lists the titles of their unread notifications, never bodies, grades, comments or
  conversations. `bin\sibol.php mail:digest` sends the summaries; run it once a day. Each person gets at most one in
  20 hours.
- `check`, `mail:test ADDRESS` and **School settings → Integrations** show whether email works. They show the
  host and the sender, never the password, and the password never reaches the logs. Sibol uses PHPMailer 6.

**Google Workspace.** Use a school account as the sender, for example `sibol@school.edu.ph`:
1. Turn on 2-Step Verification for that account, then create an app password for it (Google Account → Security
   → App passwords).
2. In `.env`, set `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`, `MAIL_USERNAME` and
   `MAIL_FROM_ADDRESS` to that account, and `MAIL_PASSWORD` to the app password. Gmail sends only as the
   account or one of its aliases.

   If your Workspace administrator set up the SMTP relay for this server instead, use
   `MAIL_HOST=smtp-relay.gmail.com` as the relay's settings say.
3. Run `C:\xampp\php\php.exe bin\sibol.php mail:test you@school.edu.ph` and check that the email arrives (and is not
   in spam).
4. For the daily summary, add a daily task in Windows Task Scheduler that runs
   `C:\xampp\php\php.exe C:\xampp\htdocs\BEST\bin\sibol.php mail:digest`, for example at 6:00 AM.

Google limits how many messages one account can send in a day; check its current limits before inviting a whole
school at once. This build was tested against a local SMTP test server, not Google's servers: run step 3 before
relying on it.

## Command-line tools

```bat
C:\xampp\php\php.exe bin\sibol.php <command>
```

| Command | What it does |
|---|---|
| `check` | Configuration report: PHP version and extensions, database, folders, AI provider, cookies, DB user, email. |
| `db:create` | Create the database named in `.env`. |
| `migrate` / `migrate:status` | Apply or list migrations. |
| `db:seed [--size=pilot]` | Load the demo school into an empty database; `--size=pilot` loads the pilot-size school instead (see [Versions and performance](#versions-and-performance)). |
| `db:reset [--size=pilot]` | Drop all tables and stored files, migrate and seed again (local only). |
| `schema:build` | Regenerate `database/schema.sql` for phpMyAdmin. |
| `materials:process` | Extract and index materials still waiting (normally done right after upload). |
| `privacy:purge` | Delete companion conversations past each school's retention period. It also runs automatically once a day when an administrator opens the overview, so cron is optional. |
| `lang:missing [fil]` | Count untranslated interface strings and write stubs to `storage/cache/`. |
| `school:create "Name" email Given Family [timezone]` | Create a real school and its first administrator; prints a one-time invitation link. |
| `user:link email-or-username` | Print a new invitation link (not yet accepted) or password reset link (active account). |
| `two-step:off email-or-username` | Switch off someone's two-step sign-in (a lost phone and lost recovery codes); they are told in Sibol. |
| `key:generate [--show]` | Write a new `APP_KEY` into `.env` when it has none (it never replaces one); `--show` only prints a key. |
| `backup [--to=FOLDER] [--keep=14]` | The database and the uploaded files in one dated zip, without `.env` or `APP_KEY`; keeps the newest 14 (see [Backups and restoring](#backups-and-restoring)). |
| `mail:test ADDRESS` | Send a test email with the `MAIL_` settings (see [Email](#email-optional)). |
| `mail:digest` | Send the daily notification summaries people turned on; run once a day. |
| `ai:eval [--demo] [--case=A01,B02] [--show]` | Run the AI check on the demo school: a pass/fail table with token cost (see [Checking the AI before going live](#checking-the-ai-before-going-live)). |

## Tests

**PHP tests (PHPUnit)** use a separate database, `sibol_test`, which is dropped and re-seeded on every run.
Your `sibol` database is not touched.

```bat
C:\xampp\php\php.exe vendor\bin\phpunit
```

They cover the Markdown sanitiser, citation checking, validation, automatic marking, chunking, prompt
source-wrapping, password rules, translations (including a check that the scripts hold no English text), and
against the database: class and record permissions,
permission-filtered retrieval (other sections, drafts and scheduled materials never reach a prompt),
a full demo companion turn, data export and erasure, daily limits and usage accounting, audit-log hygiene and
retention purging, copying work to other sections without exposing drafts, extensions and excused work,
reopening quiz attempts, the notification badge's count-only background check, retrieval of Filipino questions,
headings and numbers, the AI check (all 30 cases pass in demo mode and leave nothing behind; a scripted
model that invents citations, ignores the language or writes graded work fails; one that follows the rules
passes; its checks are unit-tested with good and bad answers), usernames made from names, sign-in slip codes and
who may print them, sections and advisers, the QR encoder (published Reed-Solomon examples, format and
version bits, and every symbol read back by an independent decoder), and scores for work on paper (held and
released scores in the gradebook, absent and excused students, make-up dates, online work left untouched, and
printed copies without answers or unapproved AI drafts), the privacy notice and rules (who must accept, versions
and editions, asking everyone again, audit entries without text) and the AI switch per student (the school's
starting value, a guardian's yes or no, only the section's adviser or an administrator may change it, refusals
before anything reaches a provider, reteaching topics without that student, and the data export), and pilot
feedback and the weekly report (only the page's path is kept, a reply is sent once as a notification, audit
entries without text, feedback stays inside its school, weeks from Monday in the school's time zone, and a test
school whose counts, rates and hidden small groups are checked number by number, in the page data and the CSV;
the pilot-size school loads only into an empty database), and grading periods (the 2015 table's 41 rows, reading
a pasted table and refusing a broken one, which period work counts in, overlapping or out-of-year dates,
splitting a year, and a test school whose period, final and transmuted grades are checked number by number,
with the table on and off, with subject-group weights, and without periods), and progress reports (released
results from one period only, missing and late counts, comments left out until released, the year so far
without periods, and an adviser's reports limited to the section's classes and its students), and the review
queue (intervals of 1, 3, 7, 14 and 30 days with the clock moved day by day, quiz questions added only once their
answers are released and only once, answers checked by Sibol, due reviews before new ones within the daily
number, reviewing early before a quiz, practice questions leaving while the student's AI is off or their
material is unpublished, and the queue in the data export and erasure), and the comment bank (comments matched to
a rubric's criteria by title, ordered by use, refused when empty or too long, and out of reach of other teachers),
and who may need a nudge (two missing or late in 14 days, by name; excused work, extensions, scored and unscored
work in class and absences handled as in the gradebook; one reminder a day, none when the student turned them off),
and email (off without `MAIL_HOST` and never showing the password; invitation and reset links emailed in the
person's language and not without an address; the daily summary with unread titles only, never bodies, at most once
in 20 hours and only what is new since the last one), and the Daily Lesson Log (every lesson section in exactly one
row, the rows Sibol has nothing for left blank, and labels in the lesson's language with the page's language put back),
and the installable app (the manifest's name, addresses and icon files, an offline page with nothing personal, and
Keep offline only for files), and two-step sign-in (the published RFC 6238 and RFC 4648 test values, one step
either side and each code once, recovery codes kept only as hashes and working once, the secret unreadable without
`APP_KEY`, a waiting sign-in ending after five minutes or when the person is signed out everywhere, and a person
told when someone else switches it off), and backups (the database, every uploaded file and no secrets in the zip,
and only older Sibol archives removed).
Last run: **168 tests, all passing** (on PHP 8.0 and 8.3, with MariaDB 10.4 and 10.11).

**Browser tests (Playwright)** drive the real app through Apache. **They reset the local `sibol` database
first** (`db:reset`), so do not run them against data you want to keep. They refuse to run unless
`APP_ENV=local`.

```bat
npm install
npx playwright test
```

They use the installed Microsoft Edge by default; set `SIBOL_BROWSER=chromium` and run
`npx playwright install chromium` to use Playwright's own browser, or `SIBOL_URL` for another address.
They cover the landing page (photo, seal and motto load; the seal stays pinned to the photo on a phone),
sign-in and sign-out, role homes, permission boundaries for every role (403 for other roles'
areas, 404 for records outside your classes, CSRF), a teacher building a quiz with AI drafts that must be
approved before publishing, a student taking it and receiving a receipt, grade release, the companion with
cited sources and answer reporting, administrator invitation and one-time links, CSV import with a preview,
class setup, a policy switch, a data export downloaded only by its owner, the audit log, Filipino text written
by the page scripts, copying a material, a quiz and a lesson to another section as drafts, an extension and an
excused student, quiz attempts in offline mode, the live notification badge, demo answers in the session's
language (including a question written in Filipino), students imported without email setting up their account
from a printed slip's typed code, an adviser's password slip, a subject teacher's roster slips (and a 404 for a
student outside their classes), choosing a section adviser, a teacher entering paper scores (a refused score,
absent and excused students, held then released, seen by the student), the blank copy and the answer key, 404s
for other teachers, a new student accepting the privacy notice and the rules before any other page (and again
after the administrator asks everyone, without interrupting work already under way), an adviser recording a
guardian's no so the companion and practice disappear for that student (403, other pages unaffected, 404 for a
teacher who is not the adviser), a student sending feedback from a class page and reading the administrator's
reply, a teacher's feedback without its page, the weekly pilot report as a page and CSV (no names), maths typeset
with KaTeX that is downloaded only when a page shows maths, an administrator loading the DepEd 2015 template
(the table is refused until it is checked, then switched on, and the page still fits a phone), a teacher's
quarter and final grades, CSV columns and work placed in another quarter, a student's quarter grades, a
student's own progress report and an adviser's reports for the section (a 404 for a teacher who is not the
adviser, and an audit entry), a student's review queue (a missed quiz question checked and rescheduled, reviewing
a class before its quiz, a missed practice set joining the queue, and a 403 for teachers), the comment bank
(inserted into the overall comment and into a question's comment, saved from the grading page without leaving it,
and a 404 for another teacher), a teacher nudging a student who missed two tasks (listed by name, reminded once a
day, the reminder in the student's notifications, a 403 for students), email switched off (links still copied, no
promise of an email, no password shown, no daily summary offered), a lesson printed as a Daily Lesson Log (and a
404 for a teacher of another class), installing Sibol as an app and reading a kept class file offline (other
pages, a quiz and files not kept show the offline page in the person's language; the offline page carries no
name, message or form token; signing out removes the kept file), a teacher turning on two-step sign-in (the code
step after the password and after a reset link, each app code and recovery code working once, and an
administrator switching it off with the teacher told), phone-width layout of 41 screens and structural
accessibility checks on 58 screens.
Last run: **103 tests, all passing** (on PHP 8.0 with MariaDB 10.4, and PHP 8.3 with MariaDB 10.11).

## Versions and performance

**PHP.** Both test suites pass on PHP 8.0.30 (XAMPP) and PHP 8.3.35 with every error, warning and deprecation
reported: PHPUnit turns deprecations into failures, and the app turns any reported PHP message into an error.
Sibol still runs on 8.0; production should use 8.3. To run the browser tests on another PHP without touching
Apache, serve the app with that PHP's built-in server in one window:

```bat
set APP_URL=http://127.0.0.1:8083
C:\tools\php83\php.exe -d error_reporting=-1 -S 127.0.0.1:8083 -t public tools\dev-server.php
```

Then run the tests in another window. PHPUnit on that PHP is simply `C:\tools\php83\php.exe vendor\bin\phpunit`.

```bat
set SIBOL_URL=http://127.0.0.1:8083
set SIBOL_PHP=C:/tools/php83/php.exe
npx playwright test
```

**Database.** Both suites pass on MariaDB 10.4 (XAMPP) and on MariaDB 10.11.19, the long-term release. On
10.11, PHPUnit ran on PHP 8.0 and 8.3 and the browser tests on PHP 8.3, all with the server's default settings.
PHPUnit also passes with `ONLY_FULL_GROUP_BY` forced on. MySQL 8 has that mode on by default, though Sibol sets
its own SQL mode on every connection anyway. MySQL 8.4 itself has not been tried.

To test against another database server, run it on another port and set `DB_HOST` and `DB_PORT` in the
environment; real environment variables win over `.env`. For PHPUnit that is enough:

```bat
set DB_HOST=127.0.0.1
set DB_PORT=3307
C:\xampp\php\php.exe vendor\bin\phpunit
```

The browser tests also need the built-in server above, started with the same two variables, because Apache
reads `.env`. The browser tests' setup deletes stored uploads, which the XAMPP demo database also uses: run
`bin\sibol.php db:reset` afterwards.

**A pilot-size school.** `bin\sibol.php db:reset --size=pilot` replaces the demo data with a larger fictional
school in about 15 seconds:
- 1,200 students in 24 sections (Grades 7 to 10), 60 teachers and 2 administrators;
- 192 classes and ten weeks of work: 2,304 assignments, quizzes and tasks on paper, and about 94,000 submissions;
- sign-ins, opened materials, notifications, companion and practice counts, and feedback.

The demo buttons sign in as its first student, teacher and administrator. Its materials are links, so the
companion has nothing to search in this school. `db:reset` brings back the normal demo school.

**Page times.** `node tools/timings.mjs role:/path …` prints the median server time of five loads. The numbers
below come from the pilot-size school on the development laptop (Apache, PHP 8.0, MariaDB 10.4), before and after
the fixes in this build. Runs vary by about a third.

| Page | Before | After | What changed |
|---|---|---|---|
| Administrator: classes | 393 ms | 37 ms | teacher names were found by scanning every account once per class |
| Administrator: weekly pilot report, 8 weeks | 1,214 ms | 659 ms | two covering indexes (migration 013), one pass over submissions, weeks found by binary search |
| Administrator: weekly pilot report, 26 weeks | 1,869 ms | 863 ms | the same |
| Administrator: overview | 223 ms | 113 ms | |
| Teacher: insights | 164 ms | 160 ms | |
| Student: progress | 137 ms | 86 ms | |
| Every other page timed (dashboards, gradebook, submissions, review, advisory, users, usage, audit) | 34–107 ms | 29–69 ms | |

Pages added later, on the same pilot-size school (measured on a slower day: the earlier code took 45–103 ms on the
small demo school that day, against 29–69 ms above):

| Page | Server time |
|---|---|
| Student: home (adds missed quiz questions to the review queue) | 122 ms |
| Student: review queue, progress, progress report | 90–105 ms |
| Teacher: class page with who may need a nudge | 68 ms |
| Teacher: comment bank | 45 ms |
| Adviser: progress reports for a whole section (50 students, 8 subjects) | 584 ms (1.3 s to load) |

**Page weight.** `node tools/page-weight.mjs role:/path …` loads pages at phone width over Lighthouse's "Slow 4G"
(1.6 Mbps, 150 ms round trip). The main student pages download:

| | Before | After |
|---|---|---|
| First visit: home, classes, lists, progress, calendar, companion, practice, settings, feedback | 255–307 KB, 1.7–1.8 s | 218–270 KB, 1.5–1.7 s |
| First visit: an assignment or quiz | 519–524 KB, 2.8 s (measured after the font fix below; about 37 KB more before it) | 224–230 KB, 1.5 s |
| Return visit (cached files) | 16–70 KB | 16–70 KB |

Two changes made the difference:
- **The maths library.** Assignment, quiz, lesson, class and companion pages loaded KaTeX (about 300 KB) whether
  or not they showed maths. Those pages now only name its files, and `app.js` fetches them when maths
  (`\( … \)` or `\[ … \]`) actually appears, including in companion answers that arrive later. A page that does
  show maths still downloads them once, about 90 KB with compression.
- **The menu count.** The count beside a menu item was drawn in italics, so every page downloaded a 37 KB italic
  font just for that.

Compression would save about 90 KB more on a first visit: the stylesheet goes from 76 to 15 KB, the main script
from 20 to 6 KB and the icons from 23 to 5 KB. XAMPP ships `mod_deflate` switched off; to turn it on, remove the
`#` in front of `LoadModule deflate_module modules/mod_deflate.so` in `C:\xampp\apache\conf\httpd.conf` and
restart Apache. Pages themselves stay uncompressed on purpose: they hold form tokens next to text people type,
which compression over HTTPS can leak (the BREACH attack).

## Project layout

```
public/            the only web-reachable folder: index.php, assets (CSS, JS, fonts, icons, KaTeX, school/ seal and photo)
app/Core/          router, request/response, sessions, CSRF, auth, validation, database, i18n, uploads
app/Controllers/   Student/, Teacher/, Admin/ and shared controllers (companion, files, settings)
app/Policies/      Access.php: every class, record and file permission check
app/Services/      assessments, grades, materials and ingestion, notifications, privacy, settings, demo seed
app/Services/Ai/   provider interface and adapters, retrieval, prompts, citations, usage, companion, generators
app/Views/         server-rendered templates (layouts, partials, student/, teacher/, admin/, companion/)
lang/fil.php       Filipino interface strings
database/          migrations, combined schema.sql, demo materials
storage/           uploads, exports, logs, cache (blocked from the web)
tests/php, tests/e2e   PHPUnit and Playwright tests
docs/              design-system.md, implementation-checklist.md
tools/             asset scripts (fonts and icons, school images with their originals in school-assets/), screenshots,
                   page timings and page weight, and a router for PHP's built-in server (dev-server.php)
```

`CLAUDE.md` has conventions for anyone (or any AI assistant) changing the code.

## Deploying for real use

1. Use **PHP 8.3** (tested) and keep it updated. Turn on compression for the stylesheet, scripts and icons
   (Apache `mod_deflate`; `public/.htaccess` already asks for it when the module is loaded).
2. In `.env` (or real environment variables): `APP_ENV=production`, `APP_DEMO_LOGIN=false`, the real `APP_URL`,
   `SESSION_SECURE_COOKIE=true`, and an `APP_KEY` (run `bin\sibol.php key:generate`). Keep a copy of `.env`
   somewhere safe, apart from database backups.
3. Serve over **HTTPS** only. Browsers run Sibol's service worker (installing it as an app, the offline page and
   files kept for offline reading) only over HTTPS or on the same computer (`localhost`). `public/.htaccess`
   sends `public/sw.js` with `Cache-Control: no-cache`; on another web server, do the same, so a new version
   reaches phones on their next visit.
4. Create a dedicated database user instead of `root`:
   ```sql
   CREATE USER 'sibol'@'localhost' IDENTIFIED BY 'a-long-random-password';
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON sibol.* TO 'sibol'@'localhost';
   ```
   (`CREATE`, `ALTER`, `INDEX`, `DROP` and `REFERENCES` are only needed while running migrations.)
5. Point the web server's document root at `public/` if you can; otherwise keep the root `.htaccess`.
6. Make `storage/` writable by the web server and not by anyone else. Back up the database and
   `storage/uploads` together every day with `bin\sibol.php backup` (see [Backups and restoring](#backups-and-restoring)).
7. In `my.ini`, set `max_allowed_packet=16M`. In `php.ini`, set `upload_max_filesize` and `post_max_size` to at
   least `UPLOAD_MAX_MB`.
8. Do **not** run `db:seed` in production. Create the school and its first administrator from the command line,
   then open the printed one-time link to set a password, and add everyone else from **People** (one by one or
   with a CSV import; students need no email address). Name each class's section (for example `Narra`) so the
   section appears under **Subjects and terms → Sections and advisers**, choose its adviser, and print sign-in
   slips from the import result, a class roster or the adviser's page:
   ```bat
   C:\xampp\php\php.exe bin\sibol.php migrate
   C:\xampp\php\php.exe bin\sibol.php school:create "Name of the School" principal@school.edu.ph Given Family
   ```
   If the only administrator loses their password, `bin\sibol.php user:link principal@school.edu.ph` prints a new
   one-time reset link.
9. Optional cron: `php bin/sibol.php privacy:purge` nightly and `materials:process` every few minutes.

## Backups and restoring

`bin\sibol.php backup` writes one zip to `storage/backups` (or `--to=FOLDER`), named by the school's local date
and time, for example `sibol-backup-2026-10-21-020000.zip`:

- `database.sql`: the whole database, dumped by `mysqldump` in one consistent snapshot. The database password
  reaches `mysqldump` in a temporary option file that only the server account can read and that is deleted
  straight away, never on its command line. The database user needs only `SELECT` for this.
- `uploads/`: every uploaded file (materials, submitted work, photos, exports waiting to be downloaded).
- `backup.json` (date, database server, number of tables and files, migrations) and `RESTORE.txt`.

It never contains `.env` or `APP_KEY`: keep those safe separately, for example on a USB drive the principal
keeps. Archives beyond the newest 14 (`--keep=N`) are removed; only files named `sibol-backup-*.zip` are ever
removed. A backup holds personal data, so keep copies only where staff are allowed to keep student records, and
copy them off the server (another computer or storage the school controls) so one failed disk cannot take both.
`check` reports how old the newest backup is.

Run it every day with Windows Task Scheduler (or cron on Linux), for example at 2:00:

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\BEST\bin\sibol.php backup --keep=14
```

**To restore** (tested on the demo school: every table, row and uploaded file came back):

1. Install the same or a newer version of Sibol and put your saved `.env` (with the same `APP_KEY`) in place.
2. Unzip the backup. Create an empty database and load the dump, in Command Prompt:
   ```bat
   C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE sibol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   C:\xampp\mysql\bin\mysql.exe -u root sibol < database.sql
   ```
   (or use phpMyAdmin's Import with `database.sql`).
3. Copy the contents of the archive's `uploads` folder into `storage/uploads`.
4. Run `bin\sibol.php migrate` (when the code is newer than the backup), then `bin\sibol.php check`.

Without the original `APP_KEY`, two-step sign-in cannot be checked: switch it off for each person who used it
(`bin\sibol.php two-step:off username`) and they set it up again.

## Security and Privacy Notes

**Accounts and sessions**
- Passwords are hashed with PHP's `password_hash`; reset and invitation links are random 256-bit tokens of
  which only a SHA-256 hash is stored. Links work once and expire (invitations 14 days, resets 48 hours).
- **Accounts without email.** Every account has a username that Sibol makes from the name (`juan.delacruz`,
  then `juan.delacruz2`); administrators can change it. Email is optional. Students sign in with the username;
  staff can use either. Usernames are unique across the database, so the sign-in page needs no school picker.
- **Sign-in slips.** Instead of a link, an administrator, the section's adviser or a teacher of one of the
  student's active classes can print a slip with the student's name, username, a one-time code and the same
  code as a QR code of the link, with cut lines (six to an A4 page). An invited account gets a setup slip, an
  active one a password slip (its current password keeps working until the slip is used). A student without
  a phone camera types the code at `/start`. Codes are 12 characters from a 31-letter alphabet without 0/O,
  1/I/L (about 59 bits), are stored only as SHA-256 hashes, work once, expire like links and cancel any earlier
  unused link of the same kind; the code page is rate-limited per address. The printable page shows the codes
  only to whoever issued them, for 30 minutes. Every slip is audit-logged with who printed it, never the code;
  nobody ever sees or sets another person's password. Sections (a grade level's classes in a period, such as
  Grade 9 Narra) come from the section name on each class; each can have one adviser.
- **Two-step sign-in (optional, administrators and teachers).** In **Account and preferences**, staff can add
  Sibol to an authenticator app (Google Authenticator, Microsoft Authenticator or any app for time-based codes,
  RFC 6238: six digits every 30 seconds) by scanning a QR code or typing its key, confirmed with a code from the
  app and their password. From then on the password alone signs nobody in: a code from the app follows, on its
  own page, and a password reset link also ends at that page. Each code works once (a code within 30 seconds
  either side of the server's clock is accepted). Ten single-use recovery codes are shown once, for a lost or
  flat phone; new ones replace them. Turning it off or making new codes needs the password. The app's secret is
  encrypted in the database with `APP_KEY` (AES-256-GCM) and recovery codes are kept only as keyed hashes, so a
  copy of the database alone (a backup, say) reveals neither. Wrong codes are rate-limited (six per 15 minutes)
  and audit-logged. An administrator can switch it off for someone else who has lost their phone, from that
  person's page; the person gets a notification. If the only administrator is locked out, run
  `bin\sibol.php two-step:off their-username` on the server. Demo sign-in on this computer skips it.
- The session ID is regenerated at sign-in and sign-out. Cookies are `HttpOnly`, `SameSite=Lax`, and `Secure`
  when configured. The signed-in user is reloaded on every request, so deactivation, role changes and
  "sign out other devices" take effect immediately. Idle sessions end after `SESSION_IDLE_MINUTES`; the
  notification badge's background check (count only) does not count as activity, so a page left open on a shared
  computer still signs out.
- Sign-in is rate-limited per address and per username or email; failures use the same message whether or not
  the account exists.

**Requests**
- Every POST needs a per-session CSRF token, and requests whose `Origin` is another site are refused.
- Every route declares its role, and every record is re-checked against class membership and school
  (`app/Policies/Access.php`). Records outside your scope return 404, so their existence is not revealed.
  Hidden links are never the boundary.
- Responses carry a strict Content-Security-Policy (no inline scripts), `X-Frame-Options: DENY`,
  `nosniff`, a same-origin referrer policy and a restrictive permissions policy.
- All SQL uses prepared statements with bound parameters.

**Files**
- Uploads are checked by extension and by content type, stored outside `public/` under random names,
  and served only by a controller that checks access. They are sent with `nosniff` and a sandboxing CSP
  (PDFs get a framing-only CSP so the browser's PDF viewer can run). Camera and location metadata is removed
  from uploaded photos and logos.
- Uploads are **not** virus-scanned. Add a scanner (for example ClamAV) before accepting files from the public.
- **Copy to other sections.** A teacher can copy a material, an assignment or quiz, or a lesson to their other
  active sections from its page. Copies arrive as drafts; modules are matched by name (otherwise the copy is
  listed under "Other materials"); questions keep their review state, so unapproved AI drafts stay unapproved;
  rubrics come along, and linked materials point to the section's own copy (copied as a draft if it has none).
  A copied file reuses the stored file and its extracted text, but its passages are stored again under the new
  section, so retrieval still filters by class inside the query and a copy follows its own section's draft,
  release date and companion settings.

**AI**
- Keys, prompts and provider addresses stay on the server.
- Retrieval applies permissions inside the same SQL query that ranks passages: only the student's own
  classes, only published and released materials with AI use allowed, only the current processed version.
  Nothing else can reach a prompt.
- Material text is wrapped as numbered reference blocks that cannot close their own tags, and the
  instructions tell the model to treat it as data and ignore instructions inside it (prompt-injection defence).
- Citations are checked after generation: markers for passages that were not supplied are removed, and each
  kept citation stores the real passage it points to.
- The companion is told not to do graded work for students; teachers' AI drafts (questions, lessons, grading
  suggestions) are never shown to students until the teacher reviews them.

**Privacy**
- Companion conversations are private to their owner. Teachers see an excerpt only when a student chooses
  to share it. Administrators see a companion answer only when someone reports it, and then only that answer,
  not the question or the rest of the conversation. There is no administrator tool to read conversations.
- Conversations are deleted after the school's retention period (default 365 days without activity);
  students can delete their own if the school allows it.
- **Extensions, extra time and excused work.** On an assignment's submissions page a teacher can give one
  student their own due date, extra minutes on a timed quiz, or excuse them. No reason is stored, so health or
  family details stay out of Sibol. The student's dashboard, calendar, reminders, timer and late rules use their
  own date; excused work is not counted and never shows as missing. Answers set to appear "after the due date"
  wait until the latest extension has passed, so they cannot reach a student who has not taken the quiz yet.
- **Work done in class or on paper.** Tests, performance tasks and activities done on paper are a third kind
  of work ("In class / on paper") with a date in class and no online submission. Afterwards the teacher types each
  student's score into a grid (`/teacher/assignments/{id}/scores`), ticks who was absent or excused, and saves the
  scores held or releases them. A score is stored as a submission marked `paper`, so it counts in the gradebook,
  the CSV export and student progress exactly like online work, and respects extensions (a make-up date) and
  excused work. Paper work never shows as missing by itself: until a score is entered it shows "—", and a student
  marked absent counts as missing until they make it up. The same grid records paper scores for students who did
  online work on a printed copy (their online submissions are never overwritten). Saving is audit-logged with
  counts only. Any assignment or quiz prints as a blank copy (no answers; AI drafts not yet approved are left
  out) or as the teacher's copy with the answer key, which only the class's teachers can open.
- **Grading periods, weights by subject group and transmutation.** Sibol does not decide a school's grading
  policy; an administrator sets it up under **School settings → Grading** (`/admin/grading`):
  - **Grading periods** are quarters or terms inside a school year (**Subjects and terms → Academic periods**:
    split a year into four quarters or three terms, then move the dates to the school calendar; at most 6, inside
    the year, without overlaps). Work counts in the period its due date falls in, in the school's time zone,
    unless its teacher picks another period in the assignment's settings. Each period's grade uses only that
    period's work, and the final grade is the average of the period grades so far. Classes in a year without
    grading periods keep one overall grade.
  - **Weights by subject group:** each group of subjects can weigh the categories differently; a subject without
    a group uses the default weights.
  - **Transmutation table:** pasted as two columns (or the order's ranges) and shown back as Sibol reads it. It
    stays off until someone ticks that they checked every line against the official order and switches it on,
    and any change to the table needs that tick again. When it is on, each period's initial grade (two
    decimals) is transmuted, and the final grade is their average as a whole number.
  - **Templates** load either DepEd order as a starting point, which replaces the categories, groups, table and
    descriptors and can split the current school year. **DepEd Order No. 8, s. 2015:** four quarters; written
    work, performance tasks and quarterly assessment at 30/50/20 (languages, Araling Panlipunan, EsP), 40/40/20
    (mathematics and science) and 20/60/20 (MAPEH, EPP/TLE); its transmutation table, switched off.
    **DepEd Order No. 015, s. 2026:** three terms; written or oral works, performance tasks and examinations at
    20/50/30, and 20/60/20 for EPP/TLE and MAPEH, as news reports gave them when the order came out; no table
    and no descriptors, which the school types in from the order. Check every number against the order
    before teachers rely on it.

  Teachers see a tab per grading period (each student's initial grade and period grade), **Final grades** and
  **All work**, with a warning that lists work due outside every period. The CSV export adds each period's
  initial grade, its transmuted grade when the table is on, and the final grade. Students see each period's
  grade and the final grade so far under **Progress**. Changes to periods, groups, templates and the table are
  audit-logged with counts and switches only.
- **Progress reports.** A printable page per student and grading period (print it, or save it as a PDF): the
  grades teachers have released in each subject, missing and late work, and released teacher comments for that
  period, or for the school year so far when the year has no grading periods. Every sheet says it is not an
  official report card (SF9). Students print their own from **Progress**; the section's adviser prints one
  student's report or the whole section, one page per student, from **Advisory**, covering the section's classes
  only. Other teachers get a 404. Opening reports as an adviser is audit-logged with the number of students only.
- **Review today.** Questions a student missed come back on a schedule in a private queue (**Review** in the
  student menu, with a card on the home page). These are wrong answers in practice sets, flashcards not known yet,
  and wrong or blank answers in quizzes once that student can see the answers.
  - **Checking:** Sibol checks choice and typed answers with the same rules as quiz marking; only flashcards are
    self-rated.
  - **Schedule:** a right answer moves a question to the next interval (1, 3, 7, 14, 30 days), and after the last
    one it leaves the queue. A miss moves it back one interval and brings it back the next day.
  - **Daily number:** questions that are due come before new ones, up to the school's daily number (**Policies →
    Features**, 20 by default; 0 for no limit).
  - **Before a quiz:** the queue lists quizzes due in the next two weeks, and a quiz page links to them too.
    Reviewing everything from that class, due or not, does not push questions further out.
  - **Privacy:** only the student's current classes count. Practice questions drop out while the student's AI is
    off or when their material is no longer released. Teachers never see reviews, and nothing is graded. The
    queue is in the student's data export and is erased with their other private study data.
- **Comment bank.** Teachers save feedback they write often (**Comment bank**, linked from **To review** and
  the grading page), optionally tied to a rubric criterion by its title. On the grading page the bank lists the
  comments for this rubric's criteria first. A click inserts a comment into the comment box the teacher was
  last writing in (the overall comment or a question's comment), to adjust before saving. **Save the comment you
  are writing** adds the selected text, or the whole box, without leaving the page. The most used comments come
  first. Only their owner sees them; another teacher gets a 404. They are in the teacher's data export and are
  erased with their account.
- **Daily Lesson Log.** A lesson's **Daily Lesson Log** button prints it in the DepEd DLL layout (the rows of
  DepEd Order No. 42, s. 2016), from the lesson's current draft, in the lesson's language.
  - **Filled rows:** objectives go under I.C. Prior knowledge, the worked example, the explanation, activities,
    checks for understanding and the exit ticket go under procedures A, C, D, E, F and I. The sources go under
    references.
  - **Blank rows:** content and performance standards, procedures B, G, H and J, remarks and the reflection are
    left blank to write in. The current quarter fills in when the school year has grading periods.
  - **Not done yet:** the school's own DLL template file has not been provided, so this is one lesson, not a week
    with a column per day. A DOCX or XLSX export waits for that file.
  - **Access:** teachers of the class only; others get a 404.
- **Who may need a nudge.** A class's overview lists, by name, students with two or more pieces of work missing
  or handed in late among those due in the last 14 days. Each student's own due dates count, excused work is left
  out, and a student marked absent from work done in class counts as missing, as in the gradebook. The list is
  never ranked or scored. **Send a reminder** puts one neutral note in that student's notifications, with no
  marks: some work in the class is past its due date or was handed in late, with a link to their assignments. A
  student gets at most one a day per class, and none if they turned reminders off. Sending is audit-logged with
  the student only.
- **Quizzes on weak connections.** Answers are sent about a second after each change and when the page is hidden.
  Until the server confirms them they are also kept in the browser's `sessionStorage` for that tab only (never
  in cookies or long-term storage), restored after a reload and sent again on reconnect; they are removed once
  saved, on submit and on sign-out. Submitting while offline waits for the connection. If time runs out offline,
  the receipt lists which answers reached the server, and the teacher can reopen the attempt with a few minutes
  that start when the student opens it again.
- **Installing Sibol and reading offline.** Browsers can install Sibol as an app (`/manifest.webmanifest`, icons
  from the seal). A service worker (`public/sw.js`) keeps the stylesheet, scripts, fonts and images and an
  offline page, which is the same for everyone: it holds no names, messages or form token, and lists the files
  kept on that device from the device itself. It follows the language of the pages the person sees (each page
  says its language in `Content-Language`). Pages, answers and quizzes are never stored, so they always need a
  connection. On a class's Materials tab a student can choose **Keep offline** for a file: a copy goes into the
  browser's cache for that site, is refreshed whenever the file is opened online, and opens without a
  connection. Every kept file is removed on sign-out and whenever a sign-in page opens (an idle sign-out ends
  there too). A kept copy is the file the student could already download; it is not sent anywhere else.
- People can ask for a copy of their data, a correction or deletion from their account settings.
  Administrators prepare exports (downloadable only by the requester, for 14 days) or erase personal data
  (the account is anonymised; school records are kept unless the administrator chooses to delete them too).
- School-wide completion totals are hidden for groups of fewer than five expected submissions, so individual
  students cannot be picked out.
- The audit log records who did what and when, never passwords, tokens, keys or conversation text.
  Network addresses are stored only as hashes.
- Email is optional. Without it, Sibol shows invitation and reset links to administrators to share, or prints
  them on sign-in slips, and never claims an email was sent. With it, links are also emailed to the person, and
  the daily summary carries notification titles only. The SMTP password stays in `.env` and is never shown or
  logged.
- **Privacy notice and rules.** On first sign-in, and again whenever either changes, students read the privacy
  notice and the rules for using Sibol (`/rules`) and accept both before any other page opens; the page they
  asked for opens afterwards, and they can read both in Filipino first. Each acceptance is stored with its
  version and time (`notice_acknowledgments`) and audit-logged without text. A notice's version is its wording
  version in the code (`Notices::VERSIONS`) plus the school's edition. **Policies → Notice and rules** says who
  must accept (students by default; teachers and administrators optional), shows how many have accepted (counts
  only) and has **Ask everyone again**, which starts a new edition after the school changes how it uses Sibol.
- **AI switched off for one student.** A student's companion and AI practice start on (a policy, so a school can
  start everyone off instead). The section's adviser (**Advisory**, then the student) or an administrator
  (**People**) can switch them off or on for one student and record a parent's or guardian's yes or no with its
  date; a no switches AI off and a yes switches it on. Sibol stores only the answer, its date and who entered it;
  signed forms stay in the school's own files. When AI is off, the companion and practice disappear from the
  student's menus and pages, their requests are refused before anything reaches a provider, their typed work is
  not offered for AI grading suggestions, and their answers are left out of the topic summaries behind reteaching
  ideas. Changes are audit-logged, and the student's data export lists their acceptances, their AI setting and
  the guardian's answers.
- **Feedback and the weekly pilot report.** Students and teachers use **Send feedback** in their menu to tell the
  school that something is not working or is confusing, or to share an idea: a kind, a short text and the page
  they came from. The page is kept as a path only, never its query string, what was on it or a conversation, and
  never for addresses that carry one-time codes. Administrators see each message with the sender's name under
  **Feedback and pilot report** and give it a status (new, looking into it, fixed, won't fix). They can reply once
  with a short note, which reaches the sender as a notification; it is not a chat. The **Weekly pilot report** shows each week from
  Monday to Sunday in the school's time zone:
  - active students and teachers;
  - materials and assignments published;
  - on-time, late and missing rates for online work due that week (each student's own due date; excused and paper
    work left out);
  - companion questions and practice sets (counts only);
  - feedback by kind.

  It is available as a page and as CSV. Any number that comes from fewer than 5 people is hidden, and there are
  no rankings or lists of people, so in the small demo school many cells say "hidden". Sending, replying and
  downloading are audit-logged without text. A person's feedback is in their data export and is deleted when
  they are erased.
- Under the Philippine Data Privacy Act of 2012, the school is the personal information controller. Appoint a
  Data Protection Officer, enter their contact in **Policies**, and review the privacy notice at `/privacy`
  before enrolling real students.

## Accessibility and languages

- Targets WCAG 2.2 AA: colour contrast (ratios in `docs/design-system.md`), visible keyboard focus, skip link,
  labelled controls, error messages tied to their fields, status shown with words and icons, never colour alone,
  reduced-motion support, charts with table views, and layouts that work at 390 px.
- The browser tests check structure (one heading level 1 per page, unique ids, names for every control and
  link, image alternatives) on 54 screens and the absence of sideways scrolling on 37 phone screens.
  They are not a full audit; test with a screen reader before launch.
- **Filipino:** every interface string is translated (3,150 of 3,150), for students, teachers and school
  administrators. That includes text written by scripts (save status, card counters, companion progress), which
  the server translates and hands to the page in `data-i18n` attributes, so the JavaScript holds no English.
  Strings added later fall back to English until they are added to `lang/fil.php`; run
  `bin\sibol.php lang:missing` to list them. Have a Filipino teacher review the wording before launch.
- **Companion language:** answers follow the session's language, and a question written in Filipino (or
  asking for Filipino) is answered in Filipino, by the model and in demo mode alike. Class materials are
  quoted as written. Filipino questions find English notes through their subject terms ("Ano ang ginagawa ng
  diaphragm…"), since Filipino question words and request verbs are ignored when matching.

## Known limitations

- No single sign-on yet. Two-step sign-in is optional, for administrators and teachers only; it was tested with
  codes computed the way authenticator apps compute them (RFC 6238 test values), not yet with a real phone app.
  People without email use printed sign-in slips.
- Uploaded files are not scanned for viruses (a scanning hook was not chosen for this phase). They are kept
  outside the web folder and served only as downloads or inside a sandbox.
- Email was tested with PHPMailer against a local SMTP test server and with messages kept in memory, not against
  Google's servers. Run `mail:test` once before relying on it.
- The QR codes on sign-in slips come from Sibol's own encoder (no library). The tests read every code back with
  an independent decoder and check it against values published in the QR standard, but nobody has scanned a
  printed slip with a phone yet: do that once on the school's printer before handing slips out.
- Retrieval uses MySQL full-text search, not embeddings, so it matches words rather than meaning, and scanned
  PDFs without a text layer cannot be read.
- Live AI providers were not tested in this build (no key was available); demo mode was tested end to end.
  `ai:eval` is ready to run once a key is added.
- Demo mode cannot judge meaning: it labels passages that share fewer than half of a question's key words as
  "may not cover this directly" rather than deciding whether they answer it.
- The Filipino translation covers the whole interface but was drafted with AI and has not yet been reviewed
  by a Filipino teacher.
- The DepEd grading templates were typed in by hand. The published copy of DepEd Order No. 8, s. 2015 is a
  scan, so its weights and table were checked against other published copies of the order rather than read
  from the file. The 2026 weights come from news reports of DepEd Order No. 015, s. 2026, not the order itself.
  A loaded template's weights apply at once, so check them first; its table stays off until someone confirms
  every line.
- The notification badge updates live (every 90 seconds while the page is visible); everything else, such as
  grading updates and lists, refreshes on page load.
- Offline reading covers the class files a student chooses to keep; lessons, other pages, quizzes and the
  companion need a connection. Installing and offline reading were tested in Microsoft Edge (Chromium) on a
  computer, not yet on a phone. Safari on iPhone may delete a site's stored files after seven days of browsing
  without a visit, unless Sibol was added to the Home Screen.
- The demo password and demo sign-in exist only for local evaluation; production settings disable them.
