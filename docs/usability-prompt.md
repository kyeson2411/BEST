You're working on Sibol in C:\xampp\htdocs\BEST. Read CLAUDE.md, README.md and docs/design-system.md first;
their hard rules apply to everything below. Phases 1–3 of the pilot brief are done. This brief is about one
problem only: **Sibol is hard to understand.** I'm the developer, and when I test it my head hurts trying to
work out where things are and what they do. If I struggle, a Grade 7 student on a phone, a busy teacher or a
principal will struggle more. Make it easy to understand without removing what it can do.

## Who uses it
- **Students** (Grade 7–12, many on low-end Android phones on slow mobile data, some reading English as a second
  language). They sign in with a username, often have no email, and see Sibol mostly at 390 px.
- **Teachers**: busy, comfortable with Facebook and Google Forms, not with software. They use a laptop at school
  and a phone at home. Several teach many sections.
- **School administrators**: the principal, a coordinator or the ICT teacher. They set things up once per
  term and check on things now and then.

## What is there now (so you can see the size of it)
- Menus: students up to 8 items (Home, Classes, Assignments, Companion, Practice, Review, Progress, Calendar),
  teachers up to 10 (Home, Classes, To review, Assignments, Materials, Lessons, Gradebook, Insights, Companion,
  Advisory), administrators 10 (Overview, People, Classes, Subjects and terms, AI usage, Policies, Privacy and
  safety, Feedback and pilot report, Audit log, School settings). Each role also has Account, Help and Send
  feedback (`app/Services/Navigation.php`).
- About 260 routes, 14 student views, 29 teacher views and 29 admin views in `app/Views`; the companion has 8
  student modes and 5 teacher modes.
- Features whose ideas a newcomer must learn: per-student due dates and excused work, grading periods and
  DepEd weights, learning competencies, DLL lesson plans, paper scores, spaced review, sign-in slips, AI on/off
  per student, companion sharing and reporting, keep-offline, two-step sign-in, comment bank, insights.

Demo accounts (password `Sibol-demo-2026`): administrator `teresa.villanueva`, teacher `ramon.dizon` (also an
adviser), student `andrea.cruz` (has graded work and feedback), student `luis.pascual` (AI switched off).

## How to work
- The folder is under git. Commit once per item, with the item in the message; don't push. Never commit this
  file or docs/remaining-work-prompt.md.
- **Phase A is look-and-report only: change no code.** Then stop and wait for my go-ahead.
- In Phase B, one item at a time: short plan, code, tests, docs, then report what changed, which tests ran and
  anything not verified. Stop at the end of Phase B and wait for me.
- Proceed without asking me routine questions. Ask me only where the brief says so, and group the questions.
- The browser tests reset the `sibol` database; don't run them while another session may be using it.
- Use the built-in browser and `node tools/screens.mjs` to look at real screens, as each role, at 390 and
  1440 px. Judge what you see, not what the code says it should look like.

## Phase A: find what makes it hard (no code)
1. **Walk the main tasks as a first-time user** of each role, starting from sign-in, at 390 px and at 1440 px.
   Count clicks and screens, and note every point where you had to guess. Tasks:
   - Student: see what is due today; open a lesson or material; take a quiz; hand in an assignment with a
     photo; read the teacher's feedback and score; ask the companion about a lesson; see their progress.
   - Teacher: post a material to two sections; make a quiz; grade what was handed in; enter paper scores;
     see who has not handed in; give one student more time; write a lesson plan; print sign-in slips;
     check the gradebook; review an AI draft before students see it.
   - Administrator: add teachers and students from a spreadsheet; set up a section and its classes; give a
     student a new sign-in slip; switch AI off for one student; set the grading weights; see AI usage; make
     the pilot report.
2. **Look at each screen for these problems**, and give each one a severity (blocks the task / slows it /
   annoys):
   - Can't tell within 5 seconds what the page is for or what to do first.
   - Words a student or teacher wouldn't use: developer or policy words, English-only jargon, two names for
     one thing (or one name for two things), labels that differ between the menu, the page title and the
     buttons. Check the Filipino text too.
   - Too many choices at once: menus, modes, settings and buttons that compete; several actions that look
     equally important.
   - No clear next step: dead ends, empty pages that don't say what to do, success messages that don't say what
     happens next, error messages that don't say how to fix it.
   - The same job in two places, or a job split across pages that should be one.
   - Things hidden where nobody would look (inside tabs, menus, `details`, footers, settings pages).
   - Phone problems: tiny targets, tables wider than the screen, long forms, important actions below the fold.
3. **Write `docs/usability-audit.md`** (commit it as item A):
   - A one-page map for each role: every page reachable from the menu, in plain words, one line each.
   - The task walkthroughs: steps and clicks per task, and where you got stuck.
   - The problems, ranked by how many people they hurt and how badly, each with the screen, what a newcomer
     would think, and a proposed fix.
   - A glossary of every term Sibol uses, the plain word you'd use instead (English and Filipino), and which
     terms to keep because the school already uses them (DepEd words such as DLL or written work).
   - A proposed plan for Phase B, grouped into items, with the before and after numbers you expect (menu items
     per role, clicks per task).
4. **Then stop and ask me, in one message:** which screens hurt me most when I tested (I'll add my own list),
   whether any feature may be hidden by default or moved off the menu, and which renames I accept.

## Phase B: fix it (after my go-ahead, in the order I approve)
Likely items, to be confirmed by the audit:
- **Plain words everywhere.** Apply the agreed glossary to menus, titles, buttons, help text, emails, notices
  and Filipino strings; one name per thing across the whole app.
- **Shorter menus.** Group related pages (for example Materials and Lessons under a class; Policies, AI usage
  and Audit log under Settings) so each role's menu fits a phone without scrolling. Keep every page reachable.
- **Home pages that answer "what do I do now?"** For each role: the few things that need attention today, each
  with one button, and nothing else above the fold. Empty states that say what to do next.
- **One main action per page.** One primary button; secondary actions visually quieter; rarely used or
  dangerous actions tucked away and confirmed.
- **Fewer choices in the companion.** A newcomer should be able to ask a question without choosing a mode
  first; modes become optional shortcuts with plain names and a one-line example each.
- **Explain as you go.** A short line under each page title saying what the page is for; first-time hints
  that can be dismissed; field hints in plain words; error messages that say how to fix the problem.
- **Help that matches the tasks.** Rewrite `/help` as short task guides per role ("How do I…"), each linking to
  the page that does it, and link to the right guide from each page.
- **Phone first.** Fix every phone problem from the audit.

Don't remove any feature, rule or permission check without asking me. Don't change how grades are
calculated. Companion privacy, teacher review of AI drafts and AI-off students work exactly as before.

## Done means (every item)
- The CLAUDE.md hard rules hold: PHP 8.0 compatible and clean on 8.3, no inline JavaScript, design-system
  tokens only (update docs/design-system.md if a new component is needed), routes declare roles, out-of-scope
  records give 404.
- Every changed or new string uses t()/tp() and has a Filipino line in lang/fil.php; `bin\sibol.php
  lang:missing` stays at 0. When privacy notice or rules wording changes, raise `Notices::VERSIONS`.
- Renamed labels are updated in the Playwright tests. Full PHPUnit and Playwright suites pass on PHP 8.0, and on
  8.3 before Phase B ends; `ai:eval --demo` passes if anything about the companion changed.
- Each task from Phase A is walked again after the change, with the new click count in the audit file.
- Changed screens checked with tools/screens.mjs at 1440 and 390 px; student pages stay under 500 KB.
- No new library without my OK (it must support PHP 8.0) and no outside service.
- README, docs/implementation-checklist.md and docs/usability-audit.md updated.
