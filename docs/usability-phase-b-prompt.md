You're working on Sibol in C:\xampp\htdocs\BEST. Read CLAUDE.md, README.md, docs/design-system.md and
**docs/usability-audit.md** first; CLAUDE.md's hard rules apply to everything below. The audit is Phase A of
docs/usability-prompt.md (commits 2199309 and aca3b3e): every main task walked as a student, teacher and
administrator at 390 and 1440 px, 27 ranked problems, a glossary and a plan. This brief is Phase B: make Sibol
easy to understand without removing anything it can do.

The test for every screen: a Grade 7 student on a low-end phone, a busy teacher and a principal can each tell
within five seconds what the page is for and what to do next. I'm the developer and I still got lost: on
"Create assignment" I could not find where multiple-choice questions go or what I was filling in.

## First: my defense
I defend Sibol on 22 October 2026 with this app's demo school on this computer. If today is 22 October 2026 or
earlier, ask me whether the defense is over before you change code, run `db:reset` or run the browser tests,
and wait for my answer.

## How to work
- Commit once per item below, with the item number and name in the message; don't push. Never commit this
  file, docs/usability-prompt.md or docs/remaining-work-prompt.md.
- Go through the items in order without asking me routine questions: pick sensible defaults and list them in
  your report. Stop only for the things under "Ask me first", and at the end.
- For each item: check the problem on the real screens, write a short plan, then code, tests, docs and a short
  report (what changed, which tests ran, anything not verified). Then start the next item.
- Change labels, layout and flows, not URLs or stored values: old links in notifications and emails must keep
  working.
- Only one session may change this folder at a time. If `git status` shows changes you didn't make, or another
  session is working here (for example on docs/remaining-work-prompt.md), stop and tell me.
- Judge real screens, not the code: the built-in browser and `node tools/screens.mjs` at 1440 and 390 px,
  signed in as each role (demo accounts in README).
- The browser tests and `db:reset` wipe the `sibol` database and stored uploads. `AI_PROVIDER` in `.env` is a
  live model: set it to `demo` only while the browser tests run, put it back afterwards, and never touch the
  `AI_API_KEY` line. `bash storage/cache/matrix.sh` runs both suites on PHP 8.0 and 8.3 and restores the demo
  school.

## Ask me first
Removing or hiding any feature, rule or permission check; adding a library or an outside service; sending more
student data to the AI service; anything that would let someone other than the student read a conversation;
any change to how grades are calculated (don't).

## Decisions already made
These override the audit's glossary where they differ. I may edit this section before you start.

1. **Nothing is removed.** Pages may move into a group, a tab or the class page, but every page stays reachable
   in at most two clicks from the menu, and old URLs keep working.
2. **Renames** (labels only; Filipino in the last column, one Filipino name per thing):

| Now | Use | Filipino |
|---|---|---|
| To review, Review queue, submissions to review | To check | Susuriin |
| Review, Review today (questions a student missed) | Try again | Subukan ulit |
| Gradebook | Class record | Class record |
| Insights | Class results | Resulta ng klase |
| Publish, Published, Unpublish, Move to draft | Post, Posted, Hide from students | I-post, Naka-post, Itago sa mag-aaral |
| Release, Save and release, Save without releasing, released, not released (grades and feedback) | Return, Save and return, Save, return later, Returned, Not returned yet | Ibalik, I-save at ibalik, I-save, ibalik mamaya, Naibalik na, Hindi pa naibabalik |
| Release answers, answers released | Show answers, answers shown | Ipakita ang mga sagot |
| Return for changes, Return to student | Ask for changes | Ipaayos |
| Adjust, Extensions and excused work, Current adjustments, overrides | More time or excuse (button), Own due dates and excused work, Students with their own dates | Dagdag na oras o excused, Sariling takdang araw at excused |
| Password slip, Sign-in slip | Sign-in slip | Slip sa pag-sign in |
| Academic period | School year | Taon-panuruan |
| Grading periods | Quarters and terms (each named Quarter 1… or Term 1…) | Mga markahan |
| Overview (administrator) | Home | Home |
| Subjects and terms | School setup | Ayos ng paaralan |
| Audit log | Activity log | Talaan ng aktibidad |
| Feedback and pilot report | Pilot and feedback | Pilot at feedback |
| Send feedback (about Sibol) | Report a problem or idea | Mag-ulat ng problema o mungkahi |
| Integrations and system checks | Technical checks | Teknikal na pagsusuri |
| Comment bank | Saved comments | Mga naka-save na komento |
| Keep offline | Save on this phone | I-save sa phone |
| Nudge, Who may need a nudge | Remind, Who may need a reminder | Paalalahanan |
| Roster | Students | Mga mag-aaral |
| Work (student class tab) | Assignments | Mga gawain |
| Upload material, Add material | Add material | Magdagdag ng materyal |
| Lessons, New lesson, Draft a lesson (teacher side) | Lesson plans, New lesson plan | Mga banghay-aralin |
| Sessions, New session, Start session (companion) | Chats, New chat, Ask | Mga usapan, Bagong usapan, Magtanong |
| Mode | Kind of help | Uri ng tulong |
| The companion can cite this, Ready for the companion, passages indexed | The companion can read this | Nababasa ito ng companion |
| Learning companion, Kasama sa pag-aaral | Companion | Companion |
| Pinalibre | Excused | Excused |

3. **Keep these words** (the school already uses them): Companion, Assignment, Quiz, Material, Module, Lesson
   (student side), Daily Lesson Log (DLL), Rubric, Written work, Performance tasks, Quarterly assessment (or the
   school's own category names), Transmutation table, Learning competencies, Excused, Absent, Class code, Draft,
   People, Policies, Privacy and safety, Two-step sign-in, Weekly pilot report.
4. **Menus** (main entries; Account, Help and "Report a problem or idea" stay one click away):
   - Student, 8 → 6: Home, Assignments (list and calendar views; Calendar moves here), Classes, Companion,
     Practice (Try again, plus today's practice questions and flashcards), Progress. A student whose AI is off: Home,
     Assignments, Classes, Practice (Try again only), Progress.
   - Teacher, 10 → 7: Home, Classes, To check, Assignments, Class record, Companion, Advisory (advisers only).
     Materials, Lesson plans and Class results become tabs of each class; the all-classes lists stay reachable
     from the Classes page and the Create menu.
   - Administrator, 10 → 7: Home, People, Classes, School setup (Subjects, School year and quarters, Sections
     and advisers, Learning competencies, Grading), Privacy and safety, Pilot and feedback (Feedback about
     Sibol, Weekly pilot report), Settings (School details, Policies, AI usage, Technical checks, Activity log).
   - Phones get one menu, not two (today the top-bar menu and the bottom bar's More show the same list): pick
     one, update docs/design-system.md, and make it fit a 390 × 844 screen without scrolling for every role.

## The items

### 1. One name per thing
- Apply the renames everywhere: menus, titles, tabs, buttons, badges, status words, toasts, hints, emails,
  notifications, Help, `Services/ScriptStrings.php` and `lang/fil.php`. If the privacy notice or the rules of use
  change, raise `Notices::VERSIONS`.
- Remove developer words from student and teacher pages: the provider and model name in "Configured provider
  (model)" (keep the "messages left today" count, keep the name on the administrator's AI pages, and keep
  "Demo mode (no AI model)" wherever it shows today: demo mode must stay labelled), file types like "MD, 4 KB" (say "Notes, 4 KB" or "PDF, 4 pages"), "passages
  indexed" and "Indexing" (say "Getting it ready for the companion"), raw setting keys such as "Category:
  written", "in your timezone, Asia/Manila" where it adds nothing, and formatting syntax like "**bold** …
  $x^2$" (a "Formatting help" link instead).
- Filipino: the last column of the table; when an account has no language yet, keep the one chosen on the
  sign-in page.
- Done when: `lang:missing` is 0, no retired label remains in user-facing text (list what you searched for),
  and the Playwright tests use the new labels.

### 2. Shorter menus
- Build the menus from Decisions (`app/Services/Navigation.php`, `layouts/app.php`, `pages/menu.php`). Teacher
  class page tabs: Overview, Assignments, Materials, Lesson plans, Students, Class record, Class results; keep
  today's Modules, Announcements and "Shared with you" (for example modules inside Materials, announcements and
  shared excerpts on Overview). Student class page tabs: Overview, Assignments, Lessons and files, Announcements (lessons get a visible
  place; file titles look like links, with an Open button next to Download).
- Moved pages keep their URLs and light up the right menu entry.
- Done when: entries per role match the targets; a Playwright check at 390 × 844 shows the phone menu without
  scrolling; a Playwright test reaches every former menu destination from the new menu in two clicks or fewer.

### 3. Making work, starting with the quiz
This is the screen that confused me (audit problem 26 and the developer's report under the problem table).
- One **Create** button on teacher Home, the Assignments page and each class, offering five kinds side by side,
  each with a one-line example: Assignment (students type or upload), Quiz (multiple choice, true or false,
  short answer, essay), Paper or in-class task (you type the scores later), Material, Lesson plan.
- New work form: a short first step (class, title, due date) with everything else under "More settings" and
  today's defaults; the module list shows only the chosen class's modules.
- Quiz: after "Create draft" the question editor is open, not folded, with Multiple choice first and the
  cursor in the question box. "Draft questions with AI" sits beside "Add a question", and its number boxes are
  labelled "How many questions should the AI draft?". The "before you post" checklist doesn't ask for points
  that the questions already set.
- **Post to several sections at once**: Add material, New assignment, New quiz and New lesson plan let a teacher
  tick several of their classes. Sibol makes one record per class (reuse `Services/SectionCopies`), posted or
  drafted together; each copy stays independent and permission-checked exactly as today.
- Done when: a multiple-choice quiz is Home → Create → Quiz → title and due date → Create draft → write the
  question → Post, with nothing folded; posting one material to two sections takes 3 clicks (audit: 7).
  Playwright covers both.

### 4. Ask the companion without choosing a mode first
- The companion opens with one question box and the class already chosen (the class, lesson or file you came
  from; otherwise the last class used; otherwise general help). Kinds of help become optional chips with plain
  names and a one-line example each; language and explanation level go under "More options". With no chip
  chosen, use "Explain a topic", or the first kind the school allows in Policies.
- From a lesson or file, the question is prefilled and the Ask button is on the first phone screen.
- Home's companion shortcuts use the same names as the chips.
- Phone fixes: the gap above the sticky chat header; hide "Enter to send, Shift+Enter…" on touch screens.
- Unchanged: conversation privacy, `AiAccess` (AI-off students never see it), retrieval, citations and the
  prompt rules. `ai:eval --demo` and `ai:retrieval` must pass as before.
- Done when: asking about a lesson takes 4 clicks (audit: 6) and the question box is on the first phone
  screen. Playwright covers it.

### 5. Home pages that answer "what do I do now?"
- Student: first "Today" (overdue, due today, next due), one button each; then the rest of the week; one
  companion question box; classes; then returned work, Try again, practice, new files and the study plan,
  shorter and lower. At most 2.5 phone screens (audit: about 5). The same action has the same button style.
- Teacher: the Create button from item 3; "Needs attention" first: To check (oldest first), AI drafts to
  check, graded work not returned yet (a link to exactly that list, not the assignments list), and work not
  handed in this week; then upcoming work, classes and the last 30 days.
- Administrator: title "Home"; needs attention first; a setup checklist (school year and quarters, grading,
  subjects, sections and advisers, classes, people) until each step is done; recent activity without
  sign-ins; technical warnings folded under "Technical checks".
- Every empty state says what to do next, with one button.

### 6. Fewer steps in teacher and administrator jobs
- Submissions: a "Not handed in" filter (not started plus working on it) with a count, linked from Home and the
  assignment page; a "More time or excuse" button on each student's row that opens the form for that student
  right there.
- Paper scores: the save buttons stay visible on phones (sticky bar); Absent and Excused as one compact choice
  per row.
- Grading setup is in School setup; splitting the year into quarters or terms happens in one place only, and
  the other page links to it.
- Sections: create a section with its grade level and adviser, then add its subject classes from the same page
  ("New class in this section", prefilled). `Sections::forClass()` keeps linking classes saved elsewhere.
- Administrator's person page: one form, "Can this student use the companion?", with the guardian's answer in
  the same form and one Save. The rules stay the same (a guardian's no switches AI off).
- Sign-in slips: advisers can print the whole section or only students not set up yet.
- Done when the audit's counts drop as its section 5 expects: who has not handed in 4 → 2 clicks, more time
  for one student 6 → 3, grading weights 4–5 → 2–3, a section with its classes on one page instead of three.

### 7. One main action per page, and phones
- Grading a submission: one primary button that follows the assignment's setting ("Save and return" or "Save,
  return later"), "Next student" beside it, everything else under "More"; the buttons stay visible on phones.
- Every page: one primary button, quieter secondary ones, rare or dangerous actions under "More" and confirmed.
- Phones: the demo school banner shrinks to one line (it stays on every page); class tabs that scroll with a visible cue;
  no bottom bar during a timed quiz; a "Take or choose a photo" button with thumbnails and Remove before
  handing in (check that iPhone photos arrive as JPEG; if one cannot be read, say how to fix it); the class
  record as one card per student below 760 px; no key button more than two phone screens down on the pages
  the audit walked.
- Student pages stay under 500 KB (`node tools/page-weight.mjs`).

### 8. Explain as you go
- One plain line under every page title (list the pages that had none).
- Dismissible first-time hints on each role's Home, the Companion, To check, Class record and School setup,
  remembered per user (the existing `users.preferences`), not per device.
- Field hints in plain words; every error message says how to fix the problem.
- Wording that follows the state (audit problem 19): no "Get a hint" or "shown when your teacher returns it"
  on returned work; paper tasks don't say "Submitted" in the calendar; "In class" and "Due" dates match across
  pages; Practice says "below" on phones; the class Students tab counts handed-in work correctly; a student
  whose AI is off sees one line saying the school switched the companion off for their account.
- Lessons shown to students use student headings ("What you will learn", "What you already know", "Try this",
  "Check yourself"); hide nothing.

### 9. Help that matches the tasks
- Rewrite `/help` as short "How do I…" guides per role, from the audit's task list (7 student, 10 teacher,
  7 administrator), each with its steps and a link to the page that does it, in English and Filipino.
- A "Help for this page" link in each page head opens the matching guide.
- The quiz guide answers my question: where multiple-choice questions go and what each box is for.

### 10. Walk it again and write it down
- Re-walk every task in docs/usability-audit.md at 390 and 1440 px and add an "After Phase B" section: new
  clicks and screens per task, menu entries per role, and the problems that remain.
- Update README, docs/implementation-checklist.md and docs/design-system.md (new components such as the Create
  menu, the sticky save bar, hints and chips).
- Run `storage/cache/matrix.sh` (both suites, PHP 8.0 and 8.3), `ai:eval --demo`, `ai:retrieval`,
  `lang:missing` and `tools/page-weight.mjs` for the student pages. Report honestly what passed, what failed
  and what you could not run.
- Then stop and send me: a before/after table (menu entries per role, clicks per task), screenshots of the new
  home pages at 390 px, and anything still hard to understand.

## Done means (every item)
- The CLAUDE.md hard rules hold: PHP 8.0 compatible and clean on 8.3, no inline JavaScript, design-system
  tokens only (update docs/design-system.md for any new component), routes declare roles, out-of-scope
  records give 404.
- Every changed or new string uses `t()`/`tp()` and has a Filipino line in `lang/fil.php`; `bin\sibol.php
  lang:missing` stays at 0. Script text goes through `Services/ScriptStrings.php`.
- Renamed labels are updated in the Playwright tests. The full PHPUnit and Playwright suites pass on PHP 8.0
  after every item, and on 8.3 before the end; `ai:eval --demo` passes whenever anything about the companion
  changed.
- New user flows get a Playwright check; new services and permission rules get PHPUnit coverage.
- Changed screens are checked with `tools/screens.mjs` at 1440 and 390 px; student pages stay under 500 KB.
- Companion privacy, teacher review of AI drafts and AI-off students work exactly as before.
- No new library without my OK (it must support PHP 8.0) and no outside service.
