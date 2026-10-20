# Implementation checklist

Status of the build brief. **Done** means implemented and exercised by an automated test or a manual run
in this build; **Done (untested)** means implemented but not exercised; **Partial** and **Not done** say what is missing.

## Platform

| Requirement | Status | Where / evidence |
|---|---|---|
| PHP framework-light MVC under XAMPP, no Node at runtime | Done | `public/index.php`, `app/Core`, `app/Controllers` |
| MySQL/MariaDB through PDO prepared statements | Done | `app/Core/Db.php`; 41 tables in `database/migrations` |
| Migrations, combined phpMyAdmin schema, deterministic demo seed | Done | `bin/sibol.php migrate`, `schema:build`, `db:seed`; `Services/DemoSeeder.php` |
| Sessions, password hashing, session regeneration, idle timeout | Done | `Core/Auth.php`, `Core/Session.php`; sign-in/out browser tests |
| Live notification badge | Done | `GET /notifications/count` returns the count only; `app.js` polls every 90 s while the tab is visible and updates the badge and its accessible name; the poll (`X-Sibol-Poll`) does not refresh the idle timer. PHPUnit `NotificationBadgeTest`, browser `notification-badge.spec` |
| CSRF protection and Origin check | Done | `Core/Csrf.php`; browser test posts without a token (403 "form expired") |
| Server-side authorization on every request | Done | `app/routes.php` roles + `Policies/Access.php`; PHPUnit `AccessAndRetrievalTest`, browser `permissions.spec` |
| Uploads outside the web root, served through access checks | Done | `Core/Uploads.php`, `FileController`; browser test for draft/scheduled files (404) |
| Security headers and CSP without inline scripts | Done | `App::securityHeaders`; no inline handlers in views |
| Real school bootstrap without demo data | Done | `bin/sibol.php school:create`, `user:link` (run against the test database) |
| PHPUnit and Playwright suites | Done | 130 PHP tests, 75 browser tests, all passing |
| README, `.env.example`, `CLAUDE.md`, design system | Done | repository root and `docs/` |
| School identity: seal as logo and favicon, campus photo on the sign-in page at the site root | Done | `layouts/auth.php`, `partials/seal.php`, `tools/school-assets.mjs`; browser checks for the landing page on desktop and phone |

## Roles and onboarding

| Requirement | Status | Notes |
|---|---|---|
| Student, teacher, school administrator roles | Done | |
| Super administrator | Documented only | Multiple schools can share a database; creating a school is a command-line task (`school:create`). There is no cross-school admin screen. |
| Invitation onboarding with one-time links | Done | Links are shown to the administrator (no email); browser test covers invite → set password → reuse refused |
| Accounts without email: usernames, sign-in slips, advisers | Done | Migration 009: optional email, unique usernames made from names (`Services/Usernames.php`), sections from class section names with one adviser each (`Services/Sections.php`). Sign-in by username or email. Printable setup and password slips with a typeable one-time code and a QR code (`Services/SignInSlips.php`, `Services/Qr.php`, `/start`), from the import result, a class roster, the adviser's page or a person's page; teachers and advisers reach only students of their own classes or section (`Access::studentAccount`, 404 otherwise); audit-logged without codes. PHPUnit `AccountsWithoutEmailTest`, `UsernamesAndCodesTest`, `QrTest` (independent decoder, published test vectors); browser `signin-slips.spec`. Not yet scanned from paper with a phone |
| Privacy notice and rules accepted before first use; AI switched off per student with guardians' answers | Done | Migration 011: `notice_acknowledgments`, `guardian_consents`, `users.ai_enabled`. A router guard (`notices`) sends people the policy names (students by default) to `/welcome` until they accept the current versions (`Services/Notices.php`: wording version plus the school's edition; "Ask everyone again" in Policies); JSON requests get 403. Per-student switch and dated guardian yes/no for the section's adviser and administrators (`Services/AiAccess.php`, `Access::advisedStudent`, 404 for anyone else); when off, navigation and links hide the companion and practice, the `ai-student` guard returns 403, `Usage` refuses before any provider call, grading suggestions are refused and reteaching-idea topics leave the student out. Audit-logged without text; in the data export; acknowledgments erased with the account. PHPUnit `NoticesAndAiSwitchTest`; browser `notices-ai.spec`. Guardians do not answer in Sibol themselves |
| CSV import with preview and row-level problems | Done | browser test with valid, invalid, duplicate and existing rows |
| Time-limited, revocable class codes | Done | teacher class page; policy switch disables all codes |

## Students

| Requirement | Status | Notes |
|---|---|---|
| Dashboard, classes, assignments, calendar, progress | Done | screenshots at 1440/1024/390; phone and accessibility checks |
| Progress report per grading period, printed by the student or their section's adviser | Done | `Services/ProgressReports.php`: released grades by subject, missing and late work, released comments for one period (or the year so far without periods), labelled as not an official report card (SF9). Students: `/student/progress/report`; advisers: one student or the whole section, one A4 page each, only the section's classes, audit-logged with counts. PHPUnit `ProgressReportsTest`; browser `progress-report.spec` (404 for a teacher who is not the adviser) |
| Spaced review of missed questions ("Review today") | Done | Migration 015: `review_items` (a copy of each missed question, its step, due date in the school's time zone). `Services/SpacedReview.php`: misses from practice (on saving results) and from quizzes whose answers the student can see (added on visiting home or the queue); answers checked by Sibol except self-rated flashcards; intervals 1/3/7/14/30 days, a miss drops one step and returns tomorrow; due before new within `review.daily_cap`; "review everything before my quiz" per class without moving items further out; current classes only, practice items hidden while AI is off or their material is unpublished; no teacher access; in export and erasure. Every function takes "now". PHPUnit `SpacedReviewTest`; browser `spaced-review.spec` |
| Assignment submission (text and files), receipts | Done | |
| Extensions, extra quiz time and excused work per student | Done | `Services/Overrides.php`, set on the submissions page; own dates drive the dashboard, calendar, reminders, nav count, timers and late rules; excused work not counted or missing; "after the due date" answers wait for the latest extension (PHPUnit `OverridesTest`, browser `extensions.spec`) |
| Quizzes: attempts, time limit, autosave, auto-marking | Done | browser quiz flow; `GradingTest` |
| Quiz attempts that survive bad connections | Done | `quiz.js`: save after each change and on hide, unsent answers in `sessionStorage` (cleared on save, submit, sign-out), offline notice, resend on reconnect; receipt lists saved answers; teacher reopens with minutes (migration 008). PHPUnit `QuizAttemptsTest`; browser `quiz-offline.spec` in Playwright's offline mode |
| Companion modes: explain, step-by-step, practice quiz, flashcards, summary, study plan, vocabulary EN/FIL, ask class materials | Done (demo mode) | live-model behaviour untested; `ai:eval` checks it once a key is added |
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
| Claude adapter (streaming, effort, refusals, server-side fallback, retries) | Done (untested) | no API key was available; `ai:eval` is the test to run with one |
| AI check before going live | Done (demo mode) | `bin/sibol.php ai:eval`: 30 fixed student and teacher prompts on the demo school through the real companion turn; checks citations against the supplied passages (markers, quotations, pages, scope), saying so when the materials do not cover a question, no graded work, Filipino when asked, refusal (simulated) and limit handling, teacher drafts grounded in materials; pass/fail table with tokens and estimated cost; deletes each case's conversation and usage, policies changed in memory only, run audit-logged with counts. All 30 pass in demo mode (PHPUnit `AiEvalTest`, checks in `AiCheckTest`); a live run waits for the API key |
| OpenAI-compatible adapter | Done (untested) | |
| Permission-filtered retrieval before prompt construction | Done | `Retrieval::search`; PHPUnit checks other sections, drafts, scheduled materials, other teachers; Filipino questions over English notes, headings counted and ranked, numbers in questions (`AccessAndRetrievalTest`) |
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
| Comment bank for feedback | Done | Migration 016: `comment_bank` (owner, text, optional rubric criterion title, use count). `Services/CommentBank.php`; page `/teacher/comments`; on the grading page comments for this rubric's criteria come first, a click inserts into the comment box last written in, and the comment being written can be saved without leaving (`grading.js`, strings in `ScriptStrings::grading`). Owner only (404 otherwise); in export and erasure. PHPUnit `CommentBankTest`; browser `comment-bank.spec` |
| Gradebook with school grading model; CSV export with audit and formula-injection protection | Done | |
| Grading periods, weights by subject group, transmutation; DepEd 2015 and 2026 templates | Done | Migration 014: `grading_periods` (inside academic periods), `assignments.grading_period_id`, `subjects.grading_group`. Work counts in the period of its class due date in the school's time zone (`GradingPeriods::effective`) unless the teacher picks one; period grades from that period's work only, final grade = average so far. Transmutation (`Services/Transmutation.php`) applies only when the table is valid, checked line by line and switched on; changing it needs a new check. Gradebook tabs per period, Final grades, All work; CSV adds each period's initial and transmuted grade and the final grade; student progress shows period grades. 2026 weights are from news reports and its table is typed in by the school. PHPUnit `GradingPeriodsTest`; browser `grading-periods.spec` |
| Scores for work done in class or on paper; printed blank copies and answer keys | Done | Migration 010: assignment kind `paper`, submissions `source`, overrides `absent`. Score grid per assignment (`Services/PaperScores.php`): score, absent, excused, held or released; paper scores count in the gradebook, CSV and progress like online work and respect make-up dates and excused work; never "missing" unless marked absent; audit-logged with counts. Print view of any assignment or quiz, blank or with the key (`Assessments::printable`). PHPUnit `PaperScoresTest`; browser `paper-scores.spec` |
| Insights with sample sizes and small-group caution | Done | |

## Administrators

| Requirement | Status | Notes |
|---|---|---|
| Overview with pending actions, configuration warnings, aggregate completion | Done | small groups hidden |
| People: invite, edit, role change rules, deactivate/reactivate, links | Done | cannot deactivate self or the last administrator |
| Classes, members, archive; subjects; academic periods and their grading periods | Done | grading periods split a school year into quarters or terms (at most 6, inside the year, no overlaps) |
| Policies: AI on/off, modes, limits, budget, features, class codes, retention, contacts, notifications, who accepts the notice and rules, whether a student's AI starts on | Done | browser tests switch a feature off and on, and ask everyone to accept the notice again |
| AI usage by day, feature and role with cost estimate | Done | |
| School details, grade levels, logo, grading model and templates | Done | grading has its own page, `/admin/grading` |
| Integrations and system checks (no secrets shown) | Done | |
| Privacy requests: export, correction, erasure, decline | Done | export downloadable only by the requester (browser test); erasure in `PrivacyAndUsageTest` |
| Reported answers without conversation access | Done | browser test confirms the question is not shown |
| Audit log with filters | Done | |
| Pilot feedback and a weekly pilot report | Done | Migration 012 `site_feedback`. "Send feedback" for students and teachers (kind, short text, the page's path only; `Services/SiteFeedback.php`); administrators set a status and reply once, as a notification (`Admin\FeedbackController`, `Access::schoolFeedback`, 404 outside the school). Weekly report per Monday-to-Sunday week (`Services/PilotReport.php`): active people, publishing, on-time/late/missing for online work by each student's own due date, companion and practice counts, feedback by kind; page and CSV; numbers from fewer than 5 people hidden; no rankings or lists of people. Audit-logged without text; in the export; erased with the account. PHPUnit `PilotFeedbackTest` (a test school checked number by number); browser `pilot-feedback.spec` |

## Quality

| Requirement | Status | Notes |
|---|---|---|
| Design direction ("field notes meets a learning studio"), Lucide icons, no fake analytics | Done | `docs/design-system.md`; usage and insight numbers come from real records only |
| Layouts at 1440, 1024 and 390 px | Done | screenshots; phone test on 29 screens |
| WCAG 2.2 AA structure and contrast | Partial | automated structural checks on 44 screens and contrast maths; no manual screen-reader test |
| Filipino interface | Done (needs review) | all 2,830 strings translated; companion answers follow the session and question language, in demo mode too; drafted with AI, not yet reviewed by a Filipino teacher |
| Text written by scripts is translated | Done | `Services/ScriptStrings.php` via `data-i18n`; `ScriptStringsTest` fails on English literals in `public/assets/js`; browser `script-text.spec` checks Filipino flashcard and companion text |
| Current PHP: suites on PHP 8.3 while keeping 8.0 | Done | PHPUnit and Playwright pass on PHP 8.0.30 (XAMPP Apache) and PHP 8.3.35 (built-in server with `tools/dev-server.php`), with every error and deprecation reported; README "Versions and performance" |
| Current database: suites on MySQL 8.4 or MariaDB 10.11+ | Done (MariaDB) | PHPUnit (PHP 8.0 and 8.3) and Playwright (PHP 8.3) pass on a portable MariaDB 10.11.19 with default settings, and PHPUnit also with `ONLY_FULL_GROUP_BY`; MySQL 8.4 not tried; README "Versions and performance" |
| Pilot-size performance: `db:seed --size=pilot`, heaviest pages under 1 s | Done | `Services/PilotSeeder.php` (1,200 students, 60 teachers, ten weeks of work, about 94,000 submissions); `tools/timings.mjs`; slowest page 659 ms (weekly report) after migration 013 and query fixes; before and after in README |
| Student pages under 500 KB on throttled 4G | Done | `tools/page-weight.mjs` (Lighthouse "Slow 4G"): first visits 218–270 KB (assignment and quiz pages were 519–524 KB until KaTeX loaded only when maths appears), return visits 16–70 KB; a page that shows maths adds KaTeX once (about 300 KB, 90 KB compressed); compression rules in `public/.htaccess` for servers with `mod_deflate`; browser `maths.spec` |
| Email delivery, SSO, MFA | Not done | |
| Virus scanning of uploads | Not done | |
