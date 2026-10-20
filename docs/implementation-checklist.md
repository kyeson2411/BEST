# Implementation checklist

Status of the build brief. **Done** means implemented and exercised by an automated test or a manual run
in this build; **Done (untested)** means implemented but not exercised; **Partial** and **Not done** say what is missing.

## Platform

| Requirement | Status | Where / evidence |
|---|---|---|
| PHP framework-light MVC under XAMPP, no Node at runtime | Done | `public/index.php`, `app/Core`, `app/Controllers` |
| MySQL/MariaDB through PDO prepared statements | Done | `app/Core/Db.php`; 37 tables in `database/migrations` |
| Migrations, combined phpMyAdmin schema, deterministic demo seed | Done | `bin/sibol.php migrate`, `schema:build`, `db:seed`; `Services/DemoSeeder.php` |
| Sessions, password hashing, session regeneration, idle timeout | Done | `Core/Auth.php`, `Core/Session.php`; sign-in/out browser tests |
| CSRF protection and Origin check | Done | `Core/Csrf.php`; browser test posts without a token (403 "form expired") |
| Server-side authorization on every request | Done | `app/routes.php` roles + `Policies/Access.php`; PHPUnit `AccessAndRetrievalTest`, browser `permissions.spec` |
| Uploads outside the web root, served through access checks | Done | `Core/Uploads.php`, `FileController`; browser test for draft/scheduled files (404) |
| Security headers and CSP without inline scripts | Done | `App::securityHeaders`; no inline handlers in views |
| Real school bootstrap without demo data | Done | `bin/sibol.php school:create`, `user:link` (run against the test database) |
| PHPUnit and Playwright suites | Done | 59 PHP tests, 45 browser tests, all passing |
| README, `.env.example`, `CLAUDE.md`, design system | Done | repository root and `docs/` |
| School identity: seal as logo and favicon, campus photo on the sign-in page at the site root | Done | `layouts/auth.php`, `partials/seal.php`, `tools/school-assets.mjs`; browser checks for the landing page on desktop and phone |

## Roles and onboarding

| Requirement | Status | Notes |
|---|---|---|
| Student, teacher, school administrator roles | Done | |
| Super administrator | Documented only | Multiple schools can share a database; creating a school is a command-line task (`school:create`). There is no cross-school admin screen. |
| Invitation onboarding with one-time links | Done | Links are shown to the administrator (no email); browser test covers invite → set password → reuse refused |
| CSV import with preview and row-level problems | Done | browser test with valid, invalid, duplicate and existing rows |
| Time-limited, revocable class codes | Done | teacher class page; policy switch disables all codes |

## Students

| Requirement | Status | Notes |
|---|---|---|
| Dashboard, classes, assignments, calendar, progress | Done | screenshots at 1440/1024/390; phone and accessibility checks |
| Assignment submission (text and files), receipts | Done | |
| Quizzes: attempts, time limit, autosave, auto-marking | Done | browser quiz flow; `GradingTest` |
| Companion modes: explain, step-by-step, practice quiz, flashcards, summary, study plan, vocabulary EN/FIL, ask class materials | Done (demo mode) | live-model behaviour untested |
| Streaming, stop, regenerate | Done (demo mode) | |
| Citations to the exact source passage | Done | `Citations::resolve`; `CitationsTest`; browser test checks source tags |
| Private conversations, delete/archive, retention | Done | `PrivacyAndUsageTest` |
| Report an answer; share an excerpt with the teacher | Done | report → admin review → reporter notified (browser test) |
| AI disclaimer and "will not do graded work" rule | Done | composer note; system prompts in `Services/Ai/Prompts.php` |

## AI layer

| Requirement | Status | Notes |
|---|---|---|
| Provider abstraction, server-side only | Done | `Services/Ai/AiProvider.php` + Demo, Anthropic, OpenAI-compatible adapters |
| Demo provider: labelled, extractive, real citations, no fabrication | Done | tested end to end |
| Claude adapter (streaming, effort, refusals, server-side fallback, retries) | Done (untested) | no API key was available |
| OpenAI-compatible adapter | Done (untested) | |
| Permission-filtered retrieval before prompt construction | Done | `Retrieval::search`; PHPUnit checks other sections, drafts, scheduled materials, other teachers |
| Prompt-injection defences for material text | Done | `Prompts::sourceBlock`; `ChunkerAndPromptTest` |
| Daily limits, burst limit, monthly token budget, usage accounting | Done | `Usage.php`; limit refusals are counted; `PrivacyAndUsageTest` |
| Embedding (semantic) search | Not done | MySQL full-text search only |
| OCR for scanned PDFs | Not done | |

## Teachers

| Requirement | Status | Notes |
|---|---|---|
| Dashboard, classes, roster, modules, announcements | Done | |
| Materials library with processing states and versions | Done | PDF (per page), DOCX, PPTX (per slide), TXT, MD, links |
| Lesson studio with AI draft and publish | Done | |
| Copy materials, assignments/quizzes and lessons to other sections | Done | `Services/SectionCopies.php`; copies are drafts, modules matched by title, questions keep review state, linked materials remapped; copied passages carry the new class id so retrieval stays filtered in SQL (PHPUnit `SectionCopiesTest`, browser `section-copies.spec`) |
| Assignment and quiz builder; AI question drafts must be approved | Done | browser quiz flow blocks publishing until approval |
| Student preview, publish snapshots, locked questions after attempts | Done | |
| Review queue, rubric grading, AI grading suggestions (advice only), release | Done | browser test releases a grade |
| Gradebook with school grading model; CSV export with audit and formula-injection protection | Done | |
| Insights with sample sizes and small-group caution | Done | |

## Administrators

| Requirement | Status | Notes |
|---|---|---|
| Overview with pending actions, configuration warnings, aggregate completion | Done | small groups hidden |
| People: invite, edit, role change rules, deactivate/reactivate, links | Done | cannot deactivate self or the last administrator |
| Classes, members, archive; subjects; academic periods | Done | |
| Policies: AI on/off, modes, limits, budget, features, class codes, retention, contacts, notifications | Done | browser test switches a feature off and on |
| AI usage by day, feature and role with cost estimate | Done | |
| School details, grade levels, logo, grading model and templates | Done | |
| Integrations and system checks (no secrets shown) | Done | |
| Privacy requests: export, correction, erasure, decline | Done | export downloadable only by the requester (browser test); erasure in `PrivacyAndUsageTest` |
| Reported answers without conversation access | Done | browser test confirms the question is not shown |
| Audit log with filters | Done | |

## Quality

| Requirement | Status | Notes |
|---|---|---|
| Design direction ("field notes meets a learning studio"), Lucide icons, no fake analytics | Done | `docs/design-system.md`; usage and insight numbers come from real records only |
| Layouts at 1440, 1024 and 390 px | Done | screenshots; phone test on 20 screens |
| WCAG 2.2 AA structure and contrast | Partial | automated structural checks on 31 screens and contrast maths; no manual screen-reader test |
| Filipino interface | Done (needs review) | all 2,340 strings translated; drafted with AI, not yet reviewed by a Filipino teacher |
| Text written by scripts is translated | Done | `Services/ScriptStrings.php` via `data-i18n`; `ScriptStringsTest` fails on English literals in `public/assets/js`; browser `script-text.spec` checks Filipino flashcard and companion text |
| Email delivery, SSO, MFA | Not done | |
| Virus scanning of uploads | Not done | |
