# Working on Sibol

Sibol is a server-rendered PHP + MySQL learning companion for a Philippine school (students, teachers,
school administrators). Read `README.md` for setup and `docs/design-system.md` before touching the UI.

## Run and test

```bat
C:\xampp\php\php.exe bin\sibol.php check          :: configuration report
C:\xampp\php\php.exe bin\sibol.php db:reset       :: fresh demo school (local only; deletes data and uploads)
C:\xampp\php\php.exe vendor\bin\phpunit           :: unit + integration tests on the sibol_test database
npx playwright test                               :: browser tests; RESET the sibol database first
C:\xampp\php\php.exe -l path\to\file.php          :: lint one file
node tools/screens.mjs admin:/admin:a-home        :: screenshots at 1440/1024/390 into storage/cache/screens
C:\xampp\php\php.exe bin\sibol.php ai:eval --demo :: AI check: 33 companion cases on the demo school (sibol database)
C:\xampp\php\php.exe bin\sibol.php ai:retrieval   :: passage search: 27 English, Filipino and Taglish questions (demo school)
C:\xampp\php\php.exe bin\sibol.php db:reset --size=pilot :: pilot-size school (1,200 students, 60 teachers) for timing
node tools/timings.mjs teacher:/teacher/gradebook/1      :: median server time per page (aim: under 1 s)
node tools/page-weight.mjs student:/student              :: bytes per page on "Slow 4G" (aim: under 500 KB)
C:\tools\php83\php.exe vendor\bin\phpunit              :: the same tests on PHP 8.3 (README: browser tests on 8.3)
```

The app is served by XAMPP at `http://localhost/BEST/public`. Apache and MySQL must be running.

## Hard rules

- **PHP 8.0 compatible, and clean on PHP 8.3.** XAMPP ships 8.0.30; production should run 8.3. No enums,
  `readonly`, `never`, first-class callables, `new` in initialisers, or pure intersection types, and no
  deprecation notices on 8.3 (dynamic properties, `${var}` in strings, null passed to string functions).
  Composer's platform is pinned to 8.0.30.
- **No inline JavaScript or event handlers.** The CSP is `script-src 'self'`. Behaviour goes in
  `public/assets/js/*.js` and hooks onto `data-*` attributes (`data-confirm`, `data-autosubmit`, `data-copy`,
  `data-toggle`, `data-dialog-open`, `data-goto`, …). Inline `style` attributes are allowed.
- **Authorisation on the server for every request.** Routes declare a role in `app/routes.php`; controllers then
  load records through `app/Policies/Access.php` (or an equivalent query scoped by school and class membership).
  Out-of-scope records throw `HttpException::notFound()`, never 403, so existence is not revealed.
- **Retrieval filters by permission inside the SQL** (`Services/Ai/Retrieval.php`). Never fetch passages first
  and filter later, and never send material text to a provider that did not come through it.
- **Secrets never reach the browser or the logs.** AI keys, DB credentials and tokens stay in `.env`/config.
  `Audit::log()` metadata must not contain content, passwords, tokens or keys (it strips some keys, but do not
  rely on that). Store only hashes of invitation/reset tokens.
- **Companion conversations are private.** Teachers see only excerpts a student shares; administrators see
  only reported answers (`ai_reports.answer_excerpt`). Do not add any screen that lists or reads conversations.
- **AI output is a draft.** Anything AI-generated that students could see (questions, lessons, feedback) must
  pass teacher review. Demo mode must stay labelled and must never pretend to be a model or invent a source.
- **A student's AI can be switched off** by their adviser or an administrator (`users.ai_enabled`, usually after a
  guardian says no). Check `AiAccess::allowed()` before offering any AI feature to or about a student, put student
  AI routes in a group with the `ai-student` guard, and never send an AI-off student's work to a provider.
- **Demo data is fictional** and flagged `is_demo`. Never import real names, grades or documents.

## Conventions

- Front controller `public/index.php` → `Core\App` → `Core\Router` → `Controllers\{Student,Teacher,Admin}\*`.
  Controllers extend `Controllers\Controller` (`validate()`, `fail()`, `view()`, `redirect()`, `flash()`).
- Views are plain PHP in `app/Views`, rendered inside `layouts/app.php`. Escape everything with `e()`.
  Use the helpers in `app/helpers.php`: `field()`, `checkbox()`, `badge()`, `icon()`, `partial()`, `time_tag()`,
  `fmt_date()`, `str_limit()`, `markdown()` (the only way to render user or AI text as rich text).
  On pages with several forms, pass `'isolated' => true` to `field()`/`checkbox()` for the forms that did not
  submit, so old input and errors land on the right form.
- **Strings:** write English in `t('…')` / `tp('singular', 'plural', $n)` with `:placeholders`. Filipino lives in
  `lang/fil.php`; `bin/sibol.php lang:missing` lists gaps and `TranslationTest` checks placeholders.
  Scripts carry no English: add their text to `Services/ScriptStrings.php` (plural pairs as `[t('…'), t('…')]`),
  pass it with `data-i18n="<?= json_attr(…) ?>"` on the script's root element, and read it with
  `var S = Sibol.strings(root); S('key', {count: n})`. `ScriptStringsTest` fails on English literals in `public/assets/js`.
- **Database:** `Core\Db` uses native prepared statements, so a named parameter can appear only once per query
  (`:u1`, `:u2`). Use `Db::in($ids, 'x')` for IN lists (it returns the parentheses). All times are stored in UTC
  (`Db::now()`); convert with `Core\Dates` for display and input.
- **Schema changes:** add a new numbered file in `database/migrations/`, then run `bin/sibol.php migrate` and
  `schema:build`. Keep SQL valid on MariaDB 10.4 and MySQL 8 (JSON stored as TEXT; utf8mb4_unicode_ci).
- **Accounts may have no email.** Students usually sign in with a username (`Services/Usernames.php` makes one
  from the name); show `username`, never assume `users.email`, and look people up by username or email. Sign-in
  slips (`Services/SignInSlips.php`) carry one-time codes; teachers reach a student's account only through
  `Access::studentAccount()` (their classes, or a section they advise). A class's section name links it to a
  `sections` row via `Sections::forClass()` whenever the class is saved.
- **A student's deadline is their own.** Teachers can give one student their own due date, extra quiz minutes or
  excused status (`assignment_overrides`). Load assignments for a student with `Assessments::forStudent()`, and in
  SQL use `Overrides::joinSql()` / `dueSql()` / `notExcusedSql()` instead of `a.due_at`. Answers released "after
  the due date" go through `Assessments::answersReleased()`, which waits for the latest extension.
- **Settings and policies:** school-level values come from `Services\Settings` (defaults merged with stored JSON).
  Add a default there before reading a new key. `Settings::override()` changes a value in memory only, for
  tests and the AI check; `Settings::flush()` undoes it.
- **AI behaviour:** after changing prompts, retrieval, citations or the demo responder, run `ai:eval --demo`; a
  new behaviour rule gets a case in `Services/Ai/Evaluation/Cases.php`. Its checks are patterns
  (`Evaluation/Checks.php`, unit-tested with good and bad answers). Demo replies follow the session and question
  language through `Ai\Language::answerLanguage()`, the same rule the model is given. After changing retrieval or
  the bilingual word list (`Ai\Synonyms`), run `ai:retrieval` too: every case must stay as expected
  (`AccessAndRetrievalTest` checks it).
- **Icons:** Lucide via the sprite `public/assets/icons/sprite.svg`. To add an icon, add its name in
  `tools/vendor-assets.mjs` and run `npm run assets`.
- **UI:** follow `docs/design-system.md` tokens and components; no new colours, shadows or fonts without
  updating it. Charts: single hairline grid, filled marks, 24px hit targets with `data-tip`, plus a table view.
- **School identity:** the school's name, place, founding year and motto come from `Config::get('school.*')`
  (`SCHOOL_*` in `.env`); never hard-code them in views. Render the seal with `partial('seal', ['size' => …])`.
  Images live in `public/assets/school/` and are generated by `node tools/school-assets.mjs` from the originals
  in `tools/school-assets/`; do not hand-edit the generated files.
- **Tests:** add PHPUnit coverage for services and permission rules (integration tests extend
  `IntegrationTestCase`), and a Playwright check for new user flows. Run both before calling work done.

## Gotchas

- Apache does not know HTTP 419; expired forms are sent as 403 (see `App::wireStatus`).
- `Response::file()` adds a sandbox CSP; inline PDFs replace it with `frame-ancestors 'none'` so the viewer works.
- Demo sign-in and `/setup` only work with `APP_ENV=local` from the same computer.
- `db:reset` and the browser tests delete stored uploads as well as tables.
- KaTeX is not in the page: a controller passes `'katex' => true` for pages that may show maths, and `app.js`
  fetches KaTeX only when `\( … \)` or `\[ … \]` appears. Scripts that insert content call
  `Sibol.renderMath(root)` afterwards. Keep student pages under 500 KB (`node tools/page-weight.mjs`).
- Students accept the privacy notice and the rules before any other page opens (router guard `notices`), so a new
  student in a browser test lands on `/welcome` first (`acceptNotices()` in `tests/e2e/helpers.mjs`). Raise
  `Notices::VERSIONS` when the wording of `partials/privacy_notice.php` or `partials/rules_of_use.php` changes.
- Materials copied to other sections share stored files (`material_versions.stored_path`). Anything that deletes a
  material file must first check that no other version still points to it.
- The service worker (`public/sw.js`) stores only files under `assets/`, the offline page (`/offline`, which must
  stay free of names, messages and form tokens) and class files a student keeps for offline reading (`offline.js`).
  Never let it store pages or other responses. `app.js` `forgetKept()` removes the kept files on sign-out.
- `quiz.js` keeps unsent answers in `sessionStorage` (`sibol.quiz.<submission id>`); `app.js` removes every
  `sibol.*` key on sign-out (`form[data-signout]`) and on the sign-in pages (`body[data-signed-out]`). Page scripts
  that handle a submit themselves call `preventDefault()`; `app.js` then skips its confirm and busy handling.
