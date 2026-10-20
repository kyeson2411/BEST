# Sibol usability audit

Phase A of `docs/usability-prompt.md`: look and report only, no code changed. Walked on 21 October 2026 on
the demo school (`main` at `a30a80d`), signed in as each role with the demo accounts, at 390 px (phone) and
1440 px (laptop), in the built-in browser and with scripted Microsoft Edge (`tools/screens.mjs` plus small
scripts that record page height and where each heading and button sits). The live AI provider was used for
one companion question and one set of AI-drafted quiz questions.

Click counts start on the role's home page after sign-in. "Screens" means pages loaded. "Scroll" is given in
phone screens (844 px) where it matters. Severity: **blocks** (a newcomer may not finish), **slows** (finishes,
but guesses or hunts), **annoys** (finishes, but it feels harder than it should).

**Demo data changed by this walk** (a `db:reset` puts it back): Andrea Cruz took "Heredity basics" (her one
attempt) and handed in the lab report with a photo; one companion conversation for Andrea; a draft quiz
"Usability check quiz: heredity" with 5 AI-drafted questions for Ramon Dizon; audit log entries. A spreadsheet
import was previewed but not confirmed. Two accounts were switched to Filipino for a few minutes and back.

## In one page

Sibol does a lot, and most single pages are well written: almost every page has a one-line lead, buttons say
what they do, confirm dialogs explain the consequence, and the receipt after handing in says what happens next.
What makes it hard is the **whole**, not the sentences:

1. **Too many doors.** 11 menu entries for students, 13 for advisers, 12 for administrators. On a phone the
   "More" page is taller than the screen for every role, and there are two different menus (the hamburger
   drawer and "More") holding the same list.
2. **One thing, many names.** Work is "Assignments", "Work", "Due soon", "open task" and "pieces of work";
   a deadline change is "Adjust", "Extensions and excused work", "Make-up dates", "Current adjustments" and
   "overrides"; a slip is a "Sign-in slip" or a "Password slip" depending on the account; "Review" means three
   different things. A newcomer cannot tell whether two labels are the same place.
3. **The companion asks before it listens.** Starting a conversation is a form: 8 modes, class, material,
   language and level, with "Start session" 2.5 phone screens down, even from "Ask the companion about this
   lesson".
4. **Home pages show everything.** The student home has 9 sections (about 5 phone screens); teacher and
   administrator homes are better but still lead with four or three equal buttons.
5. **Developer words leak** to students and teachers: "Configured provider (openai/gpt-oss-120b)",
   "MD, 4 KB", "9 passages indexed", "Category: written", "**bold** … $x^2$", "Asia/Manila", "CSV",
   `given_name`, "tokens", "burst limit", `.env` names.
6. **Common teacher jobs are split** across pages: posting to two sections (add, then copy as drafts on another
   page, then publish the copy), finding who has not handed in (two filters), giving one student more time (a
   form at the bottom of a long page), and administrators' grading weights (not in the menu at all).
7. **Phones carry long pages.** No page scrolls sideways (good), but key buttons sit far down: "Save scores" at
   4.5 screens, the grading buttons at 2 screens, the extension form at 3.5 screens, sign-in slips at 3 screens.

The fixes do not need any feature removed. The plan at the end brings the menus to 7 (students), 8–9
(teachers) and 9 (administrators) entries that fit a phone, and cuts the companion and teacher task clicks
by roughly a third to a half.

---

## 1. Map of each role

### Student (8 menu items + Account, Help, Send feedback; phone bottom bar: Home, Assignments, Companion, Calendar, More)

| Menu | What it is, in plain words |
|---|---|
| Home | Greeting, work in progress, the week's due work, classes, companion shortcuts, questions to try again, quick practice, recent scores, new files, study plan |
| Classes | Your classes; tap one for its tabs: Overview, Materials (files and lessons by module), Work, Announcements |
| Assignments | Everything your teachers gave you (assignments, quizzes, in-class/paper tasks), filtered by To do, Overdue, Submitted, Graded |
| Companion | The AI study helper: start a chat (choose one of 8 modes first) or reopen an earlier one |
| Practice | Practice questions and flashcards made from a class file; never graded |
| Review | Questions you missed come back on a schedule ("spaced review"); hidden when the school turns it off |
| Progress | Your released scores, on-time rate, a chart, and an estimated grade per class |
| Calendar | Due dates and your own study sessions by month |
| Account | Name, photo, password, language, text size, sign out other devices, ask for a copy of your data |
| Help | One page of explanations (not task guides) |
| Send feedback | Tell the school something is broken, confusing, or an idea |

A student whose AI is switched off (`luis.pascual`) sees 6 + 3 entries: no Companion, no Practice. Nothing on
the page says why they are gone (the adviser knows; the student may wonder).

### Teacher (10 menu items for an adviser, 9 otherwise, + Account, Help, Send feedback; bottom bar: Home, Classes, To review, Companion, More)

| Menu | What it is, in plain words |
|---|---|
| Home | What waits for you (work to check, results not shown to students, AI drafts to check), upcoming and recent work, your classes, last 30 days' results |
| Classes | Your classes; each has tabs Overview, Roster, Modules, Announcements, Shared with you, and buttons to Materials, Gradebook, Insights, New assignment |
| Advisory | Your advisory section: students, AI on/off and guardian's answer, progress reports, sign-in slips |
| To review | Handed-in work waiting to be checked, oldest first |
| Assignments | All assignments, quizzes and paper tasks across classes; make new ones |
| Materials | All files and links across classes; add one |
| Lessons | Lesson plans (blank or AI-drafted), the Daily Lesson Log print view |
| Gradebook | Pick a class, then the class record with category weights and descriptors |
| Insights | Pick a class, then topics and competencies with low scores, most-missed questions, reteaching ideas |
| Companion | AI help for planning: 5 modes (plan a lesson, explain, examples, adapt an activity, feedback wording) |

Pages reached only from inside other pages: Comment bank (from To review), Submissions and Paper scores
(from an assignment), Student preview and Print (from an assignment), Daily Lesson Log (from a lesson),
two-step sign-in (from Account), sign-in slips (Advisory or a class's Roster tab).

### Administrator (10 menu items + Account, Help; bottom bar: Overview, People, Classes, Privacy, More)

| Menu | What it is, in plain words |
|---|---|
| Overview | Headed with the school's name: counts, what needs attention, work handed in by subject, AI this month, recent activity, configuration warnings |
| People | Everyone's accounts; invite one person or import a spreadsheet; open a person to edit, reset, print a slip, switch AI off, record a guardian's answer |
| Classes | Classes (one subject in one section); create, add teachers and students, print slips |
| Subjects and terms | Four tabs: Subjects, Academic periods (school years and their quarters), Sections and advisers, Learning competencies |
| AI usage | Requests, tokens, cost estimate, limits |
| Policies | Six sections of school rules: AI companion, features, class codes, notice and rules, privacy and retention, notifications |
| Privacy and safety | Data requests and reported companion answers |
| Feedback and pilot report | What people reported about Sibol; second tab: the weekly pilot report |
| Audit log | Every important action, filterable |
| School settings | Name, language, timezone, logo; a link to the grading setup; a link to Integrations and system checks |

Pages reached only from inside other pages: **Grading** (weights, templates, transmutation; only through
School settings → Grading → "Open the grading setup"), Integrations and system checks, Import preview, invitation
links.

---

## 2. Task walkthroughs

### Student (walked at 390 px as `andrea.cruz`)

| Task | Steps | Clicks / screens | Where I had to guess |
|---|---|---|---|
| See what is due today | Home shows "You have 5 pieces of work due this week…" and "Due soon" | 0 / 1, but the list starts below the fold under "Continue where you left off" | "Due soon" is the week, not today; nothing groups "today"; Open/Continue/Start quiz buttons switch between filled and outlined for no visible reason |
| Open a lesson or a material | More → Classes → class → Materials tab → tap the title | 4 / 3 (or 1 from "New in your classes", 4 phone screens down the home page) | Lessons have no tab (they sit in Overview and inside Materials); the file title does not look like a link; the only visible button is a download icon; "MD, 4 KB" and "The companion can cite this" mean nothing to a student; the tab row cuts off "Announcements" |
| Take a quiz | Home "Start quiz" → "Start quiz" → confirm → answer → "Submit quiz" → confirm | 5 / 3 | Clear. The timer and progress stay at the top; confirm dialogs say the consequence. The bottom menu stays visible during a timed quiz; the receipt does not show the auto-marked score |
| Hand in an assignment with a photo | Home "Continue draft" → scroll past instructions and rubric (2 screens) → "Choose Files" → pick photo → "Submit assignment" → "Submit" | 5 / 2 | The file button is the browser's own English "Choose Files / No file chosen"; no preview of the photo; not clear whether "Save draft" keeps attached files; iPhone photos (HEIC) are not accepted |
| Read the teacher's feedback and score | Home "Recent feedback" item (5 screens down) or the bell | 1 / 1 | Score, rubric and comment are at the top: good. The graded page still says "Stuck? Get a hint" and "Feedback: Shown when your teacher releases it" |
| Ask the companion about a lesson | More → Classes → class → lesson → "Ask the companion about this lesson" → scroll 2.5 screens past 8 modes, class, material, language, level → "Start session" | 6 / 5 | Has to choose a "Mode" before asking; "Sessions"; footer "Configured provider (openai/gpt-oss-120b)"; on the chat page, "Enter to send, Shift+Enter for a new line" on a phone and a gap above the sticky chat header where text shows through |
| See their progress | More → Progress | 2 / 1 | Readable; the estimated grade by DepEd category is clear. "Average on released work" needs "released" explained |

### Teacher (walked at 1440 px as `ramon.dizon`, checked at 390 px)

| Task | Steps | Clicks / screens | Where I had to guess |
|---|---|---|---|
| Post a material to two sections | Materials → Add material → class, title, file, "Publish now" → Add → scroll to "Copy to other sections" (2 screens) → tick section → "Copy as drafts" → open the copy → publish it | 7 / 4 | The add form allows one class only; the copy arrives as a draft, so the second section sees nothing until you find and publish it |
| Make a quiz | Home "Build quiz" → 14 fields → "Create draft" → scroll past questions, rubric and settings to "Draft questions from materials" → tick a file → "Draft questions" → approve → "Publish" | 6–7 + one per question / 2 | Module list shows both classes' modules with identical names; "Simple formatting works: **bold** … $x^2$"; the checklist says "Set the points this assignment is worth" for a quiz whose points come from its questions; the AI panel is at the very bottom |
| Grade what was handed in | Home "Start with the oldest" → rubric scores → comment → "Save and open next student" | 2 per student / 1 | Three save buttons; the one you want in a queue ("Save and open next student") is the quietest; on a phone the buttons are 2 screens down |
| Enter paper scores | Assignments → the paper task → "Paper scores" → type → "Save scores" | 4 / 3 | "Save scores" vs "Save and release"; on a phone "Save" is 4.5 screens down, after Absent and Excused boxes on every row |
| See who has not handed in | Home upcoming row → assignment → "Submissions" → filter "Not started" (+ "Working on it") | 4 / 3 | No single "Not handed in" filter; the home page's "6 of 13 in" is not a link to the 7 who are not |
| Give one student more time | …from the list above → "Adjust" next to the name → form at the bottom → date → "Save for this student" | 6 / 3 | "Adjust" does not say "more time"; the section is "Extensions and excused work" here, "Make-up dates and excused work" on paper tasks, "Current adjustments" below; on a phone the form is 3.5 screens down |
| Write a lesson plan | Home "Draft a lesson" → class, module, title, objectives, grade, length, reading level, language, format, start from, materials → "Create lesson draft" → edit → "Save draft" → "Publish" | 4 + editing / 3 | "Reading level" and "Format" unexplained; Companion's "Plan a lesson" does a similar job elsewhere; DLL is a separate button "Daily Lesson Log" |
| Print sign-in slips | Advisory → "Sign-in slips" → "Print 1 slip" → print (or Classes → class → Roster tab) | 3 / 2 | Bulk print covers only students not yet set up; others get a per-row "Password slip"; one name for one paper would help. On a phone the slip section is 3 screens down |
| Check the gradebook | Gradebook → choose class | 2 / 2 | Early in the term most students show "Did not meet expectations" after one or two results; on a phone one assessment column shows at a time under a 7-line header |
| Review an AI draft before students see it | Home "5 AI-drafted questions to review" → read each → "Approve as is" / "Save and approve" or "I have checked them all: approve all" → "Publish" | 3+ / 1 | Works well: drafts are labelled, sourced ("Drafted from Heredity notes, p. 1") and cannot be published unreviewed |

### Administrator (walked at 1440 px as `teresa.villanueva`, checked at 390 px)

| Task | Steps | Clicks / screens | Where I had to guess |
|---|---|---|---|
| Add teachers and students from a spreadsheet | People → "Import from CSV" → "Download template" → fill in Excel → save as "CSV UTF-8" → choose file → "Check file" → "Import 3 people" → slips or links | 6 / 4 | "CSV", column names `given_name`, `family_name`, role values in English lowercase. The preview itself is excellent ("Nothing has been saved yet", per-row result) |
| Set up a section and its classes | Classes → New class → name, subject, academic period, section, grade, teacher → Create → add students; repeat per subject; then Subjects and terms → Sections and advisers → adviser → Save | 5 per class + 3 / 3 per class + 2 | A section is never created directly; it appears when a class has that section name. The adviser is set on a tab of "Subjects and terms" |
| Give a student a new sign-in slip | People → search → open → "Print password slip" | 4 / 3 | "Password slip" vs the "sign-in slip" everyone else says; "Create password reset link" next to it looks like the same thing |
| Switch AI off for one student | People → search → open → scroll → "Off for this student" → "Save setting" (guardian's answer is a second form below) | 5–7 / 3 | Three choices ("Follow the school", On, Off) and two save buttons for one decision; on a phone this is 2 screens down a 4.7-screen page |
| Set the grading weights | School settings → scroll to Grading → "Open the grading setup" → load a template or edit → "Save grading setup" | 4–5 / 2 | Not in the menu. Grading periods can be split on two pages (Academic periods and Grading). Template names quote DepEd orders; "Transmutation" |
| See AI usage | AI usage | 1 / 1 | "Tokens", "Burst limit", provider name, `AI_PRICE_INPUT_PER_MTOK` |
| Make the pilot report | "Feedback and pilot report" → "Weekly pilot report" tab → "Download CSV" | 3 / 2 | The menu label is long and shortened to "Feedback" on phones; the report itself is clear |

---

## 3. Problems, ranked

Ranked by people affected × how badly. "Students" covers several hundred people; teachers dozens; administrators a few.

| # | Severity | Who | Screen | What a newcomer thinks | Proposed fix |
|---|---|---|---|---|---|
| 1 | Blocks | Students, teachers | Companion start (`/companion`, `/companion/new`) | "I just want to ask something. Which of these 8 is right? Do I have to fill all this in?" On a phone "Start session" is 2.5 screens down, even when coming from a lesson that already chose class and topic | Open with one question box and the class already chosen; modes become optional chips with a one-line example; language and level move to "More options"; no provider line for students and teachers |
| 2 | Slows | Everyone | Menus (`Navigation.php`), `/menu` | "Where is X?" 11–13 entries; the More page scrolls on every role at 390 px; hamburger drawer and More show the same list twice | Group pages (plan below) so each role has 5–7 main entries; one menu on phones |
| 3 | Slows | Everyone | Labels across the app | "Is 'Work' the same as 'Assignments'? Is 'Review queue' the same as 'To review'?" | One name per thing, from the glossary (section 4), in menus, titles, buttons, hints, emails and Filipino |
| 4 | Slows | Students | Student home | "What do I do now?" 9 sections, ~5 phone screens; "Continue where you left off" sits above what is due | Top: what is due today and overdue, each with one button; then classes; companion as one box; practice, feedback and new files lower or folded |
| 5 | Slows | Teachers and admins | Many screens | Developer words: "Configured provider (openai/gpt-oss-120b)", "MD, 4 KB", "9 passages indexed", "Indexing", "Category: written" (a raw key), "**bold** … $x^2$", "in your timezone, Asia/Manila", a 400-entry timezone list, "CSV", `given_name`, "tokens", "burst limit", `.env` names | Plain words; technical details only on Integrations and system checks; show "Science Written work" from the setting, not its key |
| 6 | Slows | Teachers | Add material / New assignment / New lesson | "I teach three Science 9 sections; do I do this three times?" One class per form; copies arrive as drafts on another page | Let the add/new forms choose several sections and post them together (still one record per class, as now) |
| 7 | Slows | Teachers on phones | Grade, Paper scores, Submissions, Advisory, New lesson | Main buttons far below: Save scores at 3,864 px, grading buttons at 1,832 px, extension form at 2,920 px, slips at 2,686 px, "Create lesson draft" at 1,834 px | Sticky save bar on long forms; move per-student actions next to the student; collapse rarely used sections |
| 8 | Slows | Teachers | Submissions | "Who still owes me work?" "Not started" and "Working on it" are separate filters; "6 of 13 in" on Home is not a link | A "Not handed in" filter (with a count) linked from Home and the assignment page; a "More time" button per row |
| 9 | Slows | Admins | Grading | "Where do I set the 30/50/20?" Not in the menu; periods editable in two places | Put Grading in the admin menu (inside a "School setup" group); one place to split the year |
| 10 | Slows | Admins | Classes / Subjects and terms | "How do I make Grade 7 Narra?" Sections exist only through class names; adviser on a tab of another page | A Sections page: create a section, pick its adviser, add its subjects' classes and students in one flow |
| 11 | Slows | Students | Class → Materials, lesson | "How do I open this?" Title not styled as a link; only a download icon; lessons have no tab | Visible "Open" (read in the browser) and "Download"; a "Lessons and files" tab; plain file type ("Notes, 4 KB") |
| 12 | Slows | Teachers | Grade a submission | "Which save do I press?" Three buttons with equal weight; "release" not explained | One primary button that follows the assignment's release setting ("Save and show to student" or "Save, show later"), "Next student" beside it; others in a menu |
| 13 | Slows | Students | Hand in with a photo | "Did it take my photo?" Browser English button, no preview, no HEIC | A "Take or choose a photo" button with thumbnails and remove; accept phone photo formats (convert or accept HEIC) |
| 14 | Annoys | Students | Practice, Review, Companion modes, Home "Quick practice" | "Practice, Review, Practice quiz, Flashcards, Quick practice: which one?" | One "Practice" place with three choices (questions you missed, a new practice set, flashcards); the companion links to it |
| 15 | Annoys | Teachers | Teacher companion | "Is 'Plan a lesson' here the same as Lessons?" Also shows "Needed for summaries" where no summary mode exists | Companion "Plan a lesson" opens a lesson draft; remove the stray hint |
| 16 | Annoys | Teachers | Gradebook | "Most of my class did not meet expectations?" Descriptors appear after one or two results; phone shows one column at a time | Show descriptors only when the school says so or after enough results ("Too early to say"); a per-student card view on phones; Gradebook menu goes straight to the last class used |
| 17 | Annoys | Everyone | Help | "Help explains Sibol but not how to do my job." | Task guides per role ("How do I…"), each linking to the page; a "?" link from each page to its guide |
| 18 | Annoys | Students | Phone layout | Demo banner takes ~100 px on every page; class tabs cut off ("Annou…"); gap above the sticky chat header; keyboard hint on phones; bottom menu during a timed quiz | Shorter banner (one line, dismissible per session); scrollable tabs with a fade or fewer tabs; fix the chat header offset; hide the keyboard hint on touch; hide the bottom menu during a quiz attempt |
| 19 | Annoys | Students | Stale or contradictory text | Graded work still says "Stuck? Get a hint" and "Shown when your teacher releases it"; a paper test shows "Submitted" in the calendar; "In class Mon, Oct 26" on Home vs "Due Mon, Oct 26 at 1:00 PM" in the class; Practice says "Start a set on the right" on a phone | Wording that follows the state and the screen width |
| 20 | Annoys | Teachers | Advisory, Roster | "Password slip" vs "Sign-in slip" per row; Roster "Work handed in 4 / 2" (more handed in than due) | One "Sign-in slip" label; count "handed in of due" correctly or label it "on time / due" |
| 21 | Annoys | Admins | Overview | Heading is the school's name, not "Overview"; recent activity is mostly sign-ins; "Configuration to review" is for the ICT teacher | Title "Home"; "Needs attention" first; activity without sign-ins; technical warnings collapsed |
| 22 | Annoys | Admins | Person page (AI) | Three-way choice and two forms for one decision | One question: "Can this student use AI?" Yes / No, with the guardian's answer as part of the same form |
| 23 | Annoys | Teachers | Home "Find them" | Goes to the published assignments list, not to the unreleased results | Link to a filtered list of results not yet shown to students |
| 24 | Annoys | Filipino readers | Filipino strings | Menu keeps "Companion" but Home says "Kasama sa pag-aaral"; "Pinalibre" (Excused) reads as "treated / paid for"; "Naka-configure na provider"; "Mode"; "Mga insight"; "Pangkalahatang tanaw". Choosing Filipino on the sign-in page does not carry into an account whose language is unset | Apply the glossary's Filipino column; when an account has no language yet, keep the one chosen on the sign-in page |
| 25 | Annoys | Students | Lessons shown to students | Headings and text from the teacher's plan ("Prior knowledge: Students know…", "Pairs sketch…", "Exit ticket") | Student view uses student headings ("What you already know", "Try this", "Check yourself") and hides teacher-only parts |
| 26 | Annoys | Teachers | New quiz/assignment form | 14 fields before the first question; module list repeats names across classes | Short first step (class, title, due date); rules folded into "More settings" with sensible defaults; modules listed for the chosen class only |
| 27 | Annoys | AI-off students | Menu | Companion and Practice vanish with no word | A short note on Home or Help: "Your school has switched off the AI study helper for your account" |

**Reported by the developer (21 October 2026):** "Create assignment" is confusing: they saw multiple choice
mentioned but could not find where to write a multiple-choice question, or tell what the fields were for.
What causes it: Home "Create assignment" opens the written-work form, which has no questions; quizzes are a
separate kind behind a small "Make a quiz instead" link; the quiz form is rules only ("You add questions … on
the next screen"); on the next screen "Add a question" is a folded box, and the only visible "Multiple choice"
is a number box in the AI panel that means how many questions the AI should draft. This raises problem 26 to
**blocks**. Fix (with B4/B5): one "Create" choice that shows the three kinds side by side with a one-line
example each; the quiz's question editor open by default with "Multiple choice" as the first type; the AI
panel labelled "How many questions should the AI draft?".

Not problems (keep as they are): confirm dialogs that state the consequence; the submission receipt with
"What happens next"; the import preview ("Nothing has been saved yet"); AI draft labels with page sources;
the student's own due dates everywhere; no page scrolls sideways at 390 px.

---

## 4. Glossary

"Keep" means the school already uses the word (DepEd or classroom usage) and Sibol should keep it, with a
hint where needed. Filipino suggestions follow the Taglish teachers and students actually use; the DepEd
Filipino terms are used where they exist.

| Sibol says now | Where | Plain word to use | Filipino | Keep? |
|---|---|---|---|---|
| Companion, Learning companion, Kasama sa pag-aaral | Menu, Home | **Study helper** (students) / **AI helper** (teachers), or keep "Companion" everywhere | Katulong sa pag-aaral | Ask (rename) |
| Session, Sessions, conversation | Companion | **Chat**, **Past chats** | Usapan, Mga nakaraang usapan | Rename |
| Mode | Companion | **What kind of help?** | Anong klaseng tulong? | Rename |
| Assignments, Work, Due soon, open task, pieces of work | Menu, class tabs, Home | **Assignments** (all assigned work: assignments, quizzes, paper tasks) | Mga gawain | Keep "Assignments", drop "Work" |
| In class / on paper, paper scores | Assignments | **Paper or in-class task**, **Enter scores** | Gawain sa papel o sa klase, Ilagay ang iskor | Rename |
| Submit, hand in, submission | Student work | **Hand in**, **handed-in work** | Ipasa, ipinasang gawa | Use "Hand in" in text, "Submit" on the final button is fine |
| Receipt code | After handing in | **Receipt code** | Code ng resibo | Keep |
| Publish | Assignments, lessons, materials | **Post** (to students) | I-post | Rename |
| Release (grades, feedback, answers), not released | Grading, Home | **Show to students**, **hidden from students** | Ipakita sa mag-aaral, hindi pa nakikita | Rename |
| Draft | Everywhere | Draft | Draft | Keep |
| To review, Review queue, Needs attention | Teacher menu, Home | **To check** | Susuriin | Rename (one name) |
| Review, Review today, spaced review | Student menu | **Try again** (questions you missed) | Subukan ulit | Rename |
| Practice, Quick practice, Practice quiz, Flashcards | Student menu, Home, Companion | **Practice** (with "questions" and "flashcards") | Pagsasanay | Keep one place |
| Materials | Teacher menu, class | **Files and links** in hints; keep "Materials" as the label | Mga materyal | Keep |
| Upload material, Add material | Home, Materials | **Add material** | Magdagdag ng materyal | One name |
| Module | Classes, forms | Module (a group of lessons and files, as in DepEd modules) | Module | Keep |
| Lesson, Draft a lesson, New lesson, Plan a lesson | Lessons, Home, Companion | **Lesson plan**, **New lesson plan** | Banghay-aralin | Rename to "Lesson plan" for teachers; "Lesson" for students |
| Daily Lesson Log, DLL | Lessons | Daily Lesson Log (DLL) | DLL | Keep |
| Exit ticket, Worked example, Prior knowledge | Lesson plan | Keep for teachers; students see "Check yourself", "Example", "What you already know" | Subukin ang sarili, Halimbawa, Alam mo na | Keep (teachers) |
| Gradebook | Teacher menu | **Class record** | Class record | Rename (DepEd word) |
| Written work, Performance tasks, Quarterly assessment, Periodic assessment | Grading | Keep the school's words; use one of "quarterly"/"periodic" consistently | Sulating gawain, Gawaing pagganap, Markahang pagsusulit | Keep |
| Transmutation table, descriptors | Grading | Keep, with a one-line hint | Transmutation table | Keep |
| Academic period, term, school year | Admin | **School year** | Taon-panuruan | Rename |
| Grading period, quarter, term | Admin, gradebook | **Quarter** (or **Term** when the school uses three) | Markahan | Rename |
| Learning competencies, competency code | Admin, teacher | Keep (MELC / MATATAG) | Mga kasanayang pampagkatuto | Keep (fix Filipino) |
| Insights | Teacher menu | **Class results** | Resulta ng klase | Rename |
| Advisory | Teacher menu | **Advisory class** | Advisory class | Keep |
| Roster | Class tab | **Students** | Mga mag-aaral | Rename |
| Class code | Classes | Class code | Code ng klase | Keep |
| Sign-in slip, Password slip, password reset link | Advisory, roster, People | **Sign-in slip** (paper); **new password link** (for staff with email) | Slip sa pag-sign in | One name for the slip |
| Adjust, Extensions and excused work, Make-up dates, Current adjustments, override | Submissions, scores | **More time** (button), **Own due date**, **Make-up date** (paper tasks), **Excused** | Dagdag na oras, Sariling takdang araw, Make-up, Excused | Rename; keep "Excused" |
| Excused | Grading | Excused | Excused (not "Pinalibre") | Keep English in Filipino |
| Absent | Paper scores | Absent | Liban | Keep |
| Nudge | Class page | **Remind** | Paalalahanan | Rename |
| Comment bank | Grading | **Saved comments** | Mga naka-save na komento | Rename |
| Keep offline | Materials | **Save on this phone** | I-save sa phone | Rename |
| The companion can cite this, Ready for the companion, passages indexed | Materials | **The AI helper can read this** | Nababasa ito ng AI helper | Rename |
| Configured provider (model) | Companion, policies | Hide from students and teachers; "AI service" on admin pages | Serbisyo ng AI | Hide |
| Tokens, budget, burst limit | AI usage, policies | **AI allowance** (tokens in the hint), **per-minute limit** | Limitasyon ng AI | Rename |
| Overview | Admin menu | **Home** | Home | Rename |
| People | Admin menu | People | Mga tao | Keep |
| Subjects and terms | Admin menu | **School setup** (subjects, school year and quarters, sections and advisers, competencies, grading) | Ayos ng paaralan | Rename (group) |
| Policies | Admin menu | **Rules** (inside Settings) | Mga patakaran | Move |
| Privacy and safety | Admin menu | **Reports and data requests** | Mga ulat at kahilingan sa datos | Rename |
| Audit log | Admin menu | **Activity record** | Talaan ng aktibidad | Rename |
| Integrations and system checks | School settings | **Technical checks** (for the ICT teacher) | Teknikal na pagsusuri | Rename |
| CSV | Import | **Spreadsheet (CSV)** | Spreadsheet (CSV) | Explain |
| Send feedback, feedback (about Sibol) | Footer | **Report a problem or idea** (teacher feedback to students stays "Feedback") | Mag-ulat ng problema o mungkahi | Rename (one name for two things today) |
| Weekly pilot report | Admin | Weekly pilot report | Lingguhang ulat ng pilot | Keep |
| Two-step sign-in | Account | Two-step sign-in, with a hint | Dalawang-hakbang na pag-sign in | Keep |
| Guardian's answer | AI switch | **Parent's or guardian's answer** | Sagot ng magulang o tagapag-alaga | Rename |

---

## 5. Proposed Phase B plan

Each item: short plan, code, tests, docs, then a report, one at a time in the order you approve. Numbers are
what I expect; each will be re-walked and the real count written back into this file.

| Item | What changes | Before → after (expected) |
|---|---|---|
| **B1. Plain words** | Apply the agreed glossary to menus, titles, buttons, hints, emails, notices and `lang/fil.php`; remove developer words from student and teacher pages; one name per thing. Raise `Notices::VERSIONS` only if notice or rules wording changes | Names for "more time": 5 → 1; for slips: 2 → 1; developer terms on student pages: 6 → 0 |
| **B2. Shorter menus** | Student: Home, Assignments (list + calendar view), Classes, Study helper (chat, practice, try again), Progress. Teacher: Home, Classes (each class as the hub for its assignments, materials and lesson plans, students, class record, results), To check, Assignments, Class record, AI helper, Advisory (advisers). Admin: Home, People, Classes, School setup (subjects, school year and quarters, sections and advisers, competencies, grading), AI and privacy (usage, rules, reports and data requests), Reports (feedback, pilot report, activity record), Settings (school details, technical checks). Account and Help in the account menu; one phone menu. Every page stays reachable | Main entries: students 8 → 5 (AI-off 6 → 5, the study area showing only "Try again"); teachers 10 → 7 (6 non-advisers); admins 10 → 7. Total with footer: 11 → 7, 13 → 9, 12 → 9. Phone "More" fits one screen for every role |
| **B3. Home pages that answer "what now?"** | Student: due today and overdue first, each with one button, then the week; classes; one companion box; the rest lower or folded. Teacher: "To check", AI drafts to check, results not shown, missing work, each with one button; one "Create" button with a menu. Admin: what needs attention and a setup checklist; activity without sign-ins | Student home: 9 sections / ~5 phone screens → 4–5 sections / ~2.5 screens; header buttons: teacher 4 → 1, admin 3 → 1 |
| **B4. Shorter teacher and admin task flows** | Post to several sections at once (one record per class, as now); "Not handed in" filter linked from Home; "More time" button per student; grading setup in the menu; one place to split the year into quarters; a Sections page for section + adviser + classes | Post to 2 sections: 7 → 3 clicks; who has not handed in: 4 → 2; more time for one student: 6 → 3; set grading weights: 4–5 → 2–3; set up a section with its classes: 3 pages → 1 |
| **B5. One main action per page** | Grading page: one primary save that follows the release setting, "Next student" beside it, the rest in a menu; assignment, material and lesson pages: one primary, quieter secondary, rare or dangerous ones under "More" and confirmed; consistent Open/Continue buttons on the student home | Grading: 3 equal saves → 1 primary; paper scores and grading save visible without scrolling on phones (sticky bar) |
| **B6. Fewer choices in the companion** | Ask first: one question box with the class preselected (and the lesson or file when you come from one); modes as optional chips with plain names and one-line examples; language and level under "More options" | Ask about a lesson: 6 clicks / Start at 2.5 screens → 4 clicks / question box on the first screen; ask anything from Home: 2 → 1 |
| **B7. Explain as you go** | One line under every title (most already have one); dismissable first-time hints on Home, Companion, To check, Class record and School setup; field hints in plain words; error messages that say how to fix; stale state text fixed (problem 19) | Pages without a lead line: list in the item report → 0 |
| **B8. Help that matches the tasks** | `/help` rewritten as short task guides per role, each linking to the page that does it; a "Help for this page" link from each page head | Help: 1 general page → ~24 guides (7 student, 10 teacher, 7 admin) |
| **B9. Phone first** | Every phone problem above: shorter demo banner, scrollable class tabs, chat header gap, keyboard hint, bottom menu hidden during a quiz, photo picker with preview, sticky save bars, per-student cards for the class record on phones | Key buttons more than 2 phone screens down on the walked pages: 6 → 0; student pages stay under 500 KB |

Suggested order: B1 and B2 together first (names and menus decide everything else), then B6 (biggest single
student problem), B3, B4, B5, B9, B7, B8.

Expected clicks per task after Phase B:

| Task | Now | After |
|---|---|---|
| Student: see what is due today | 0 (below the fold) | 0 (first thing on screen) |
| Student: open a lesson or material | 4 | 2–3 |
| Student: take a quiz | 5 | 5 |
| Student: hand in with a photo | 5 | 4–5 (with a preview) |
| Student: read feedback and score | 1 (5 screens down) | 1 (top of Home when new) |
| Student: ask the companion about a lesson | 6 | 4 |
| Student: see progress | 2 | 1–2 |
| Teacher: post a material to two sections | 7 | 3 |
| Teacher: make a quiz (with AI drafts) | 6–7 | 5 |
| Teacher: grade handed-in work | 2 per student | 2 per student (button visible without scrolling) |
| Teacher: enter paper scores | 4 | 3 |
| Teacher: see who has not handed in | 4 | 2 |
| Teacher: give one student more time | 6 | 3 |
| Teacher: write a lesson plan | 4 + editing | 3 + editing |
| Teacher: print sign-in slips | 3 | 2 |
| Teacher: check the class record | 2 | 1 |
| Teacher: review an AI draft | 3+ | 3+ |
| Admin: import people from a spreadsheet | 6 | 6 (plainer words) |
| Admin: set up a section and its classes | 5 per class + 3, three pages | one page |
| Admin: new sign-in slip for a student | 4 | 3–4 |
| Admin: switch AI off for one student | 5–7 | 4 |
| Admin: set the grading weights | 4–5 | 2–3 |
| Admin: see AI usage | 1 | 2 (inside "AI and privacy") |
| Admin: make the pilot report | 3 | 3 |

## 6. Questions before Phase B

1. Which screens hurt you most when you tested? (Your list will be merged into section 3.)
2. May any feature be hidden by default or moved off the menu? Candidates: Calendar (into Assignments),
   Practice and Review (into the companion area), Materials, Lessons and Insights (into each class),
   Policies, AI usage and Audit log (into groups), the class "Shared with you" tab, spaced review (school
   setting already exists).
3. Which renames do you accept? In particular: "Companion" → "Study helper"/"AI helper" or keep;
   "Gradebook" → "Class record"; "Insights" → "Class results"; "Publish" → "Post"; "Release" → "Show to
   students"; "To review" → "To check"; "Review" → "Try again"; "Academic period" → "School year";
   "Grading period" → "Quarter"; "Overview" → "Home"; "Audit log" → "Activity record"; "Excused" kept in
   Filipino instead of "Pinalibre".
