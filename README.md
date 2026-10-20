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
7. [Command-line tools](#command-line-tools)
8. [Tests](#tests)
9. [Project layout](#project-layout)
10. [Deploying for real use](#deploying-for-real-use)
11. [Security and Privacy Notes](#security-and-privacy-notes)
12. [Accessibility and languages](#accessibility-and-languages)
13. [Known limitations](#known-limitations)

---

## Requirements

| | Minimum | Recommended |
|---|---|---|
| PHP | 8.0 (what current XAMPP 8.0.x ships) | **8.2 or newer**: PHP 8.0 and 8.1 no longer receive security fixes |
| Database | MariaDB 10.4 (XAMPP) or MySQL 8.0 | same |
| Web server | Apache 2.4 with `mod_rewrite` and `.htaccess` (`AllowOverride All`, the XAMPP default) | same |
| PHP extensions | `pdo_mysql`, `mbstring`, `json`, `fileinfo` | plus `curl` (live AI), `zip` (Word/PowerPoint materials), `openssl` |
| Composer | for PDF text extraction (`smalot/pdfparser`) and PHPUnit | |
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
| Invited (no password yet) | `carmela.reyes` (teacher), `rafael.domingo` (student) | print a sign-in slip or create a link |
| Deactivated | `angelica.santos` | cannot sign in |

The other 16 students follow the same `given.family` pattern. The demo school takes the real
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
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | XAMPP defaults | Database connection. |
| `AI_PROVIDER` | `demo` | `demo`, `anthropic` or `openai_compatible`. |
| `AI_API_KEY` | empty | Provider key. Read only on the server. |
| `AI_MODEL` | empty | Model name. Empty means `claude-opus-5-5` for `anthropic`. Required for `openai_compatible`. |
| `AI_BASE_URL` | empty | `openai_compatible` only, e.g. `https://api.groq.com/openai/v1`. |
| `AI_TIMEOUT_SECONDS` | `90` | Per-request timeout. |
| `AI_EFFORT` | `medium` | Claude only: `low`, `medium` or `high` reasoning effort. |
| `AI_FALLBACKS` | `default` | Claude only: `default` or `off` (see below). |
| `AI_PRICE_INPUT_PER_MTOK`, `AI_PRICE_OUTPUT_PER_MTOK` | empty | Your prices in USD per million tokens for the cost estimate. |
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
white background, and drops any camera or location data from the files. An administrator can also upload a
different logo in **School settings**, which replaces the seal inside the app.

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

## Command-line tools

```bat
C:\xampp\php\php.exe bin\sibol.php <command>
```

| Command | What it does |
|---|---|
| `check` | Configuration report: PHP version and extensions, database, folders, AI provider, cookies, DB user, email. |
| `db:create` | Create the database named in `.env`. |
| `migrate` / `migrate:status` | Apply or list migrations. |
| `db:seed` | Load the demo school into an empty database. |
| `db:reset` | Drop all tables and stored files, migrate and seed again (local only). |
| `schema:build` | Regenerate `database/schema.sql` for phpMyAdmin. |
| `materials:process` | Extract and index materials still waiting (normally done right after upload). |
| `privacy:purge` | Delete companion conversations past each school's retention period. It also runs automatically once a day when an administrator opens the overview, so cron is optional. |
| `lang:missing [fil]` | Count untranslated interface strings and write stubs to `storage/cache/`. |
| `school:create "Name" email Given Family [timezone]` | Create a real school and its first administrator; prints a one-time invitation link. |
| `user:link email-or-username` | Print a new invitation link (not yet accepted) or password reset link (active account). |
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
printed copies without answers or unapproved AI drafts).
Last run: **113 tests, all passing**.

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
for other teachers, phone-width layout of 25 screens and structural accessibility checks on 40 screens.
Last run: **67 tests, all passing**.

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
tools/             asset scripts (fonts and icons, school images with their originals in school-assets/), screenshots
```

`CLAUDE.md` has conventions for anyone (or any AI assistant) changing the code.

## Deploying for real use

1. Use **PHP 8.2+** and keep it updated.
2. In `.env` (or real environment variables): `APP_ENV=production`, `APP_DEMO_LOGIN=false`, the real `APP_URL`,
   `SESSION_SECURE_COOKIE=true`.
3. Serve over **HTTPS** only.
4. Create a dedicated database user instead of `root`:
   ```sql
   CREATE USER 'sibol'@'localhost' IDENTIFIED BY 'a-long-random-password';
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON sibol.* TO 'sibol'@'localhost';
   ```
   (`CREATE`, `ALTER`, `INDEX`, `DROP` and `REFERENCES` are only needed while running migrations.)
5. Point the web server's document root at `public/` if you can; otherwise keep the root `.htaccess`.
6. Make `storage/` writable by the web server and not by anyone else. Back up the database and
   `storage/uploads` together.
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
- **Quizzes on weak connections.** Answers are sent about a second after each change and when the page is hidden.
  Until the server confirms them they are also kept in the browser's `sessionStorage` for that tab only (never
  in cookies or long-term storage), restored after a reload and sent again on reconnect; they are removed once
  saved, on submit and on sign-out. Submitting while offline waits for the connection. If time runs out offline,
  the receipt lists which answers reached the server, and the teacher can reopen the attempt with a few minutes
  that start when the student opens it again.
- People can ask for a copy of their data, a correction or deletion from their account settings.
  Administrators prepare exports (downloadable only by the requester, for 14 days) or erase personal data
  (the account is anonymised; school records are kept unless the administrator chooses to delete them too).
- School-wide completion totals are hidden for groups of fewer than five expected submissions, so individual
  students cannot be picked out.
- The audit log records who did what and when, never passwords, tokens, keys or conversation text.
  Network addresses are stored only as hashes.
- Sibol does not send email. It shows invitation and reset links to administrators to share, or prints them
  on sign-in slips, and never claims an email was sent.
- Under the Philippine Data Privacy Act of 2012, the school is the personal information controller. Appoint a
  Data Protection Officer, enter their contact in **Policies**, and review the privacy notice at `/privacy`
  before enrolling real students.

## Accessibility and languages

- Targets WCAG 2.2 AA: colour contrast (ratios in `docs/design-system.md`), visible keyboard focus, skip link,
  labelled controls, error messages tied to their fields, status shown with words and icons, never colour alone,
  reduced-motion support, charts with table views, and layouts that work at 390 px.
- The browser tests check structure (one heading level 1 per page, unique ids, names for every control and
  link, image alternatives) on 40 screens and the absence of sideways scrolling on 25 phone screens.
  They are not a full audit; test with a screen reader before launch.
- **Filipino:** every interface string is translated (2,634 of 2,634), for students, teachers and school
  administrators. That includes text written by scripts (save status, card counters, companion progress), which
  the server translates and hands to the page in `data-i18n` attributes, so the JavaScript holds no English.
  Strings added later fall back to English until they are added to `lang/fil.php`; run
  `bin\sibol.php lang:missing` to list them. Have a Filipino teacher review the wording before launch.
- **Companion language:** answers follow the session's language, and a question written in Filipino (or
  asking for Filipino) is answered in Filipino, by the model and in demo mode alike. Class materials are
  quoted as written. Filipino questions find English notes through their subject terms ("Ano ang ginagawa ng
  diaphragm…"), since Filipino question words and request verbs are ignored when matching.

## Known limitations

- No email delivery, single sign-on or multi-factor authentication yet. People without email use printed
  sign-in slips.
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
- The notification badge updates live (every 90 seconds while the page is visible); everything else, such as
  grading updates and lists, refreshes on page load.
- The demo password and demo sign-in exist only for local evaluation; production settings disable them.
