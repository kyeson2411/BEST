You're working on Sibol in C:\xampp\htdocs\BEST. Read CLAUDE.md, README.md, docs/implementation-checklist.md
and docs/design-system.md first; their hard rules apply to everything below. Phase 1 of the pilot brief (items
1–6) is done and committed. This brief is everything still missing: Phases 2 and 3 of the first brief
(unchanged) and the gaps found after Phase 1. Item numbers continue from the first brief; they are labels, not
the order of work.

## How to work
- The folder is under git. Commit once per item, with the item in the message; don't push.
- For each item, first confirm the gap still exists (search the code). If it's already done, say so and skip it.
- One item at a time, in phase order: short plan, code, tests, docs. Report after each item: what changed,
  which tests ran, anything not verified.
- Items marked **Ask first** need my answers before you build. Ask all of a phase's questions in one message
  at the start of that phase.
- Stop at the end of every phase and wait for my go-ahead.
- The browser tests reset the `sibol` database; don't run them while another session may be using it.

## Done means (every item)
- PHP 8.0 compatible (and, once item 23 is done, no deprecation notices on PHP 8.3); no inline JavaScript
  (behaviour in public/assets/js on data-* attributes, script text through Services/ScriptStrings.php);
  design-system tokens only.
- Every new route declares its role; records load through Policies/Access.php; out-of-scope records return 404.
- Schema changes go in the next numbered migration (009_… onwards), valid on MariaDB 10.4 and MySQL 8; run
  `migrate` and `schema:build`. New school-level settings get a default in Services\Settings.
- Important actions are audit-logged without content, passwords, tokens or keys.
- New text uses t()/tp() and gets a Filipino line in lang/fil.php; `bin\sibol.php lang:missing` stays at 0.
- PHPUnit covers the service logic and permission rules; a Playwright test covers the new flow; both full
  suites pass. If prompts, retrieval or the demo responder change, `bin\sibol.php ai:eval --demo` still passes.
- New screens checked with tools/screens.mjs at 1440 and 390 px; printable pages also checked in print preview.
- No new library without my OK (it must support PHP 8.0) and no new outside service without asking.
- README and docs/implementation-checklist.md updated.

## Phase 1b: still needed before real students sign in
Found after Phase 1. Do this phase before Phase 2 unless I say otherwise.

19. **Accounts without email. Ask first.** Every account needs an email address (`users.email` is required),
    many students here have none, and only an administrator can issue a password reset. Let the school sign
    students in with a school-issued username (student number or LRN) instead; email becomes optional. Print
    sign-in slips per section (name, username, one-time setup link as a QR code, expiry date, cut lines) from
    the import result and from a class roster. Let a student's adviser or teachers issue a one-time reset slip
    for a student in their class (audit-logged; nobody ever sees a password). Ask me which identifier the
    school uses, whether students have school email accounts, and who may issue resets. If the answer is the
    LRN, add the LRN field from item 24 here.
20. **Scores for work done on paper.** Teachers give quizzes, periodical tests and performance tasks on paper
    or in class, but a score can only come from a submission made in Sibol, and the gradebook is read-only.
    Add an "in class / on paper" assignment type with no online submission and a score grid per class (one
    row per student: score, absent, excused; held or released). These scores count in the gradebook, the CSV
    export and student progress exactly like online work (and in grading periods once item 7 exists), respect
    extensions and excused status, and are audit-logged. Also add a print view of any assignment or quiz: a
    blank copy for learners without a device, and a teacher-only copy with the answer key.
21. **Privacy notice, consent and an AI switch per student. Ask first.** Nothing records that anyone has seen
    the privacy notice, and AI can only be switched on or off for the whole school (or per feature). On first
    sign-in, and whenever the notice changes, show the privacy notice and a new page of rules for using Sibol
    (academic honesty, what the companion will and won't do); record who acknowledged which version and when.
    Add a per-student AI setting an administrator or adviser can change (for example when a guardian
    declines): the companion and AI-made practice disappear for that student and nothing about them is sent
    to a provider; the rest of their work is unaffected. Record guardian consent as a dated yes or no with who
    entered it (no document uploads). The student's data export includes these records. Ask me, once the DPO
    has decided: which acknowledgments are required and from whom, and whether AI starts on or off for minors.
22. **Pilot feedback and a pilot report.** Students and teachers have no way to tell the school that something
    is broken or confusing. Add "Send feedback" to the menu: a category (problem, idea, confusing), a short
    text and the page path (no query string, never conversation text). Administrators see the list with a
    status (new, looking into it, fixed, won't fix) and can reply with a short note that arrives as a
    notification; it is not a chat. Add a pilot report for administrators, per week: active students and
    teachers, materials and assignments published, on-time and missing rates, companion and practice use
    (counts only) and feedback by category, as a page and CSV. Hide groups under 5 people; no rankings and no
    per-student lists.
23. **Supported PHP and database versions, and pilot-size performance.** README tells production to use PHP
    8.2+, but the suites have only run on PHP 8.0.30 and MariaDB 10.4, both past end of life, and MySQL 8 has
    never been tried. Run both suites on PHP 8.3 (a portable PHP is fine) and on MySQL 8.4 or MariaDB 10.11+;
    fix what breaks while keeping PHP 8.0 working. Add `db:seed --size=pilot`: a larger fictional school
    (about 1,200 students and 60 teachers with a quarter of work). Time the heaviest pages (dashboards,
    gradebook, insights, submissions, admin overview) and fix queries or add indexes; aim for under one
    second each on this computer. Measure what the main student pages download on a throttled 4G connection
    and aim for under 500 KB each. Report the numbers before and after.

## Phase 2: fit how the school grades and teaches (unchanged from the first brief)
7. **Grading periods, subject weights and transmutation. Ask first.** The gradebook gives one overall grade
   per class, but schools here report quarterly and final grades, weights can differ by subject group, and
   many use a transmutation table. Add grading periods inside an academic period (e.g. Q1–Q4 with dates); each
   assignment belongs to one (by due date, changeable); gradebook, student progress and CSV show per-period
   and final grades; weights can be set per subject group; an optional transmutation table the school enters
   or confirms. Setups without periods keep working. Ask me which policy the school follows (for example
   DepEd Order No. 8, s. 2015, or a newer order), the weights per subject group, and the exact table. Never
   invent table values.
8. **Progress report per grading period. Ask first.** A printable page per student per period (print
   stylesheet, browser "Save as PDF"): released grades by subject, missing and late counts, released teacher
   comments. Label it clearly as not an official report card (SF9). Ask me whether class advisers should see
   all subjects for their section (needs an adviser link on a class) or only students print their own.
9. **Spaced review for students.** Practice sets are one-off; nothing brings missed items back on a schedule.
   Add a private "Review today" queue fed by items the student missed in practice and in quizzes whose
   answers are released. Intervals grow when right (e.g. 1, 3, 7, 14, 30 days) and drop back gently when
   missed. Due reviews come before new items, with a daily cap the school can set. Multiple-choice and
   short-answer items are checked by the app, never self-marked; flashcards are self-rated. Add "review
   everything before my quiz". Never graded, never visible to teachers, only from the student's current
   classes' released materials. The scheduler takes "now" as a parameter so tests can move the clock.
10. **Comment bank.** Personal saved feedback comments (optionally tied to a rubric criterion) that a teacher
    can insert while grading.
11. **Who needs a nudge.** Nothing points a teacher to students falling behind. Per class, list students
    with two or more missing or late items in the last 14 days, alphabetically, never ranked or scored,
    respecting excused work and extensions. One click sends the student a neutral in-app reminder.
12. **Email delivery. Ask first.** Optional SMTP configured in .env (password never shown or logged) for
    invitation and reset links, plus an opt-in daily digest of notification titles (never conversation text).
    Keep "copy link" as the fallback; `check` reports email status. Any library must support PHP 8.0. Ask me
    which SMTP service.
13. **Lesson plan export. Ask first.** Export a lesson into the school's lesson-plan format (often DepEd
    DLL/DLP), mapping Sibol's sections to the template. Ask me for the template; start with a print view.

## Phase 3: scale and harden (unchanged from the first brief; ask before each)
14. **Installable app and offline reading.** Web manifest and icons from the seal (via
    tools/school-assets.mjs), a service worker in public/ that caches static assets and an offline page;
    later, opt-in offline copies of downloaded materials, wiped on sign-out. Quizzes stay online-only.
15. **Admin security and operations.** Two-factor sign-in (TOTP, hashed recovery codes) for administrators,
    optional for teachers; `bin\sibol.php backup` (database dump plus uploads in a dated archive, no
    secrets) with restore notes; an optional virus-scan hook for uploads.
16. **Single sign-on** with Google Workspace or Microsoft 365, for existing accounts only; roles stay
    assigned by the school.
17. **Learning competency tags** on questions and lessons (the school's list, e.g. MATATAG or MELC codes) so
    insights report by competency.
18. **Better Filipino and Taglish retrieval.** Measure with item 6 first, then add a bilingual synonym list
    or embedding search. The permission filter stays inside the SQL query.
    Note from item 6: Filipino question words and request verbs are already ignored when matching, and
    headings count once a passage is found; but the full-text index still leaves headings out (a passage
    that matches only by its heading is never found), and demo answers sometimes quote a bare heading. Fix
    both here.

## Phase 4: the school year (ask before each)
24. **Learner Reference Number. Ask first.** Students have no LRN in Sibol, so its records can't be matched
    with the school's. Add an optional 12-digit LRN per student: validated, unique within the school, shown to
    staff only, never sent to an AI provider, importable by CSV and included in the student's own data
    export. Ask me whether the registrar matches students by LRN or by a school number.
25. **Section changes during the year.** Moving a student to another section breaks the link between their
    old and new work: an administrator can only remove a member and add them elsewhere. Add "Transfer to
    another section": the student leaves the old class on a date (their work and grades there are kept,
    read-only), joins the new class, and work in the new class that closed before the transfer counts as
    excused, not missing. Teachers of both classes are told. Leaving the school works the same way (withdrawn
    on a date, records kept under item 32's rules).
26. **New school year. Ask first.** At year end, classes, students and content all belong to the old year and
    there's no way to move on. Add a year-end wizard for administrators: create the next academic period,
    archive the old classes (grades and submissions kept), create the new sections and advisers from a CSV,
    and let each teacher copy last year's materials, lessons and assignments into their new classes as drafts
    (reuse the section-copy logic; no student work comes along). Ask me how sections are formed each year and
    whether students move up a grade level automatically.
27. **Class record export. Ask first.** After item 7, teachers still copy scores by hand into the school's
    class record. Export one class's scores for one grading period in the layout of the school's class record
    (for DepEd schools, the E-Class Record: highest possible scores, raw scores, percentage and weighted
    scores per component, initial and quarterly grade), as CSV, or XLSX if I approve a PHP 8.0 library. Ask
    me for the exact template file; never guess its formulas.
28. **Reusable and shuffled questions.** Questions live inside one assignment, so building a periodical test
    means retyping them, and every student sees the same order. Add a personal question bank (save a question
    from any quiz, filter by topic and later by competency from item 17, add it to another quiz with its
    review state kept: an unapproved AI draft stays unapproved). Add quiz options to shuffle question order
    and choice order per student, stored with the attempt so the receipt and grading show that student's order.
29. **Group work for performance tasks. Ask first.** Performance tasks are often done in groups, but every
    submission belongs to one student. Let a teacher form groups; one member submits for the group, every
    member sees it, and the grade goes to all members with optional per-member adjustments and absent members
    left out. Ask me whether groups are fixed per class or set per task.
30. **Class suspensions and school notices. Ask first.** Typhoons, heat and holidays cancel classes, but due
    dates and reminders carry on regardless. Let an administrator declare no-class days for the school, a
    grade level or a section, with a reason: a banner shows on those days, reminders pause, and work due on
    them moves to the next class day (or its teacher is asked to confirm a new date). Add announcements from
    administrators to the whole school, a grade level or a role; class announcements stay with teachers. Ask
    me whether due dates move automatically.

## Phase 5: operations (ask before each)
31. **Day-to-day operations.** Add a `/health` endpoint for uptime checks (status only: no versions, paths or
    secrets); daily log rotation with a retention setting (today everything goes to one `storage/logs/sibol.log`);
    one `bin\sibol.php schedule:run` command for cron or Windows Task Scheduler that runs the privacy purge,
    pending material processing, the email digest (item 12) and backups (item 15) on their own schedules; an
    administrator action to sign everyone out; `composer audit` and `npm audit` clean. Write
    docs/operations.md: daily checks, restoring a backup, and what to do after a data breach (contain it, sign
    everyone out, rotate keys, tell the DPO; the Data Privacy Act requires notifying the National Privacy
    Commission within 72 hours).
32. **How long records are kept. Ask first.** Only companion conversations have a retention period. Add school
    settings for how long submissions, uploaded files, feedback and the accounts of students who have left
    are kept, and extend `privacy:purge` to delete or anonymise them afterwards (audit-logged with counts
    only). Ask me for the periods once the DPO has decided them.
33. **Scanned PDFs. Ask first.** A scanned module has no text layer, so the companion can't use it; today the
    teacher is only told to upload a text version. Let the teacher type or paste the text for a file instead,
    indexed like extracted text. Optionally add OCR: ask me whether to run it on the server (Tesseract) or
    through the AI provider, which sends the whole file to the provider (a privacy decision).
34. **Automatic test runs. Ask first.** Run PHPUnit (PHP 8.0 and 8.3, MariaDB and MySQL 8) and Playwright on
    every push with GitHub Actions. Ask me whether the code may go to a private GitHub repository.

## Later, only if the school asks
- **Guardian access**: a read-only guardian account linked to a student by the adviser, with consent recorded
  (item 21), showing released grades, upcoming and missing work, announcements and the progress report (item
  8). Never companion conversations, practice or review history.
- **Attendance**: daily attendance per section with monthly totals for the report card, only if the school
  wants it kept in Sibol rather than on its current forms.
- **Another interface language** (for example Hiligaynon or Cebuano), once someone can translate and review it.
- **A calendar feed** (ICS) of a student's due dates for their phone.
- **AI cost tuning** after the first live `ai:eval`: cache the fixed part of each prompt, review the effort
  level and the daily limits.

## Do not add
- Open chat between students and teachers (keep shared excerpts and submission notes).
- Leaderboards, rankings, streak pressure, or tracking when students leave a quiz page.
- Any way for teachers, administrators or guardians to read companion conversations.
- Publishing AI output without teacher review; unlabelled demo answers.
- Real student names, grades or documents in demo data.
- AI-writing detectors or "AI scores" on student work: they are unreliable and unfair to students.
- Class ranking or honor-roll computation; export grades and let the school compute honors in its own system.

## Needs me, not code
- Put the Claude key in .env, run `bin\sibol.php ai:eval --show` and read the answers before students use the
  companion. (The OpenAI-compatible path also needs a service to test against.)
- A Filipino teacher reviewing lang/fil.php.
- A screen-reader pass (NVDA on Windows is free).
- The school's Data Protection Officer reviewing /privacy, the consent and AI rules (item 21) and the retention
  periods (item 32); registration with the National Privacy Commission if the school must register; a data
  processing agreement with the AI provider.
- Consent to use the campus photo on the sign-in page.
- A production server: HTTPS, a domain, a supported PHP and database (item 23), backups stored off the server.
- The school's grading policy and transmutation table (item 7), report, lesson-plan and class-record templates
  (items 8, 13, 27), and its list of learning competencies (item 17).
- A pilot plan: which sections and teachers, for how long, how success will be judged, who answers feedback
  (item 22), and a short training session for teachers.
