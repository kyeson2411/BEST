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
```

The app is served by XAMPP at `http://localhost/BEST/public`. Apache and MySQL must be running.

## Hard rules

- **PHP 8.0 compatible.** XAMPP ships 8.0.30. No enums, `readonly`, `never`, first-class callables,
  `new` in initialisers, or pure intersection types. Composer's platform is pinned to 8.0.30.
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
- **Database:** `Core\Db` uses native prepared statements, so a named parameter can appear only once per query
  (`:u1`, `:u2`). Use `Db::in($ids, 'x')` for IN lists (it returns the parentheses). All times are stored in UTC
  (`Db::now()`); convert with `Core\Dates` for display and input.
- **Schema changes:** add a new numbered file in `database/migrations/`, then run `bin/sibol.php migrate` and
  `schema:build`. Keep SQL valid on MariaDB 10.4 and MySQL 8 (JSON stored as TEXT; utf8mb4_unicode_ci).
- **Settings and policies:** school-level values come from `Services\Settings` (defaults merged with stored JSON).
  Add a default there before reading a new key.
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
