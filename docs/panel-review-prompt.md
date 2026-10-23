You're working on Sibol in C:\xampp\htdocs\BEST. Read CLAUDE.md, README.md, docs/design-system.md,
docs/usability-audit.md and docs/usability-phase-b-prompt.md first; CLAUDE.md's hard rules apply to everything
below. Today's earlier fixes are in `git log` (companion Flashcards opens Practice; the companion form follows the
kind of help and materials follow the class; students and teachers no longer see the AI provider name; narrow
kinds of help no longer answer off-topic messages). Don't redo them.

## Who you are
Act as my thesis defense panel, three people who will question Sibol live while I demo it:
1. **IT professor**: logic and workflow. "Why does it do that?", "What happens if…?", wrong or empty input,
   things that contradict each other, error messages, security and roles.
2. **Senior high school teacher (DepEd)**: classroom reality. Would a Grade 7–10 student or a busy teacher
   understand this page in five seconds? Grading (DepEd weights, transmutation), AI misuse and cheating,
   Filipino and Taglish, phones on slow data.
3. **School administrator and data privacy officer**: the Data Privacy Act, who can see what, guardian consent
   and students whose AI is switched off, AI costs and limits, what happens when the AI service is down.

Be tough but fair: find what would make a panelist say "Huh, how does that make sense?" before they do.

## My defense
I defend on 22 October 2026 on this computer with the demo school. If today is 22 October or earlier, the demo
must keep working exactly as it does now: nothing may break, reset or disappear.

## Step 1: walk the demo like the panel would
Use the real app at http://localhost/BEST/public (built-in browser, or `node tools/screens.mjs` at 1440 and 390 px)
signed in as the demo student (andrea.cruz), teacher (ramon.dizon) and administrator (teresa.villanueva); see
README "Demo accounts". Do each role's main tasks from docs/usability-audit.md, then try what a panel tries live:
- odd input: one word, "1+1" or "hi" in every companion kind of help, an empty message, a very long one,
  Filipino and Taglish, a question about another class's material;
- wrong combinations: a class and another class's material, a quiz with no questions, posting without a due
  date, a student whose AI is off (luis.pascual), an expired or reused sign-in slip;
- the AI service failing or slow, the daily limit reached, the page on a phone.
AI_PROVIDER in .env is a live Groq model on a free plan: keep live AI calls to about 15 in total. Prefer reading
the prompts (Services/Ai/Prompts.php) and `ai:eval --demo`. For a live check use
`DB_NAME=sibol_test php bin/sibol.php ai:eval --case=… --show` (test database, cleans up after itself).

## Step 2: write the critique (send it to me before fixing)
A table, worst first: what the panelist says, where (page and clicks), why it is a problem, how bad it is for the
defense (would embarrass / confusing / minor), and the fix you propose. Then the 15 questions the panel is most
likely to ask, each with a short, honest answer I can say, and which screen to show. Then "what not to click
during the demo" with the reason. Plain words and click paths, not code terms: I am the developer and I still
get lost.

## Step 3: fix what is safe to fix today
After I reply, fix the items I choose, worst first, one at a time:
- Before the first change, back up the demo: `mysqldump -u root --single-transaction sibol` and a copy of
  storage/uploads, both into your scratchpad.
- Per item: check it on the real screens, a short plan, code, PHPUnit and Playwright tests, docs, a short report,
  one commit with the item in the message. Don't push.
- Never run `db:reset` or `npx playwright test` against the demo. Run the browser tests in a separate copy of the
  project (your scratchpad): its own `.env` without AI_API_KEY and APP_KEY lines, `AI_PROVIDER=demo`,
  `DB_NAME=sibol_e2e`, its own storage folder, a node_modules junction, `php -S 127.0.0.1:8090 -t public
  tools/dev-server.php`, then `SIBOL_URL=http://127.0.0.1:8090 npx playwright test`. Afterwards remove the
  junction with `cmd /c rmdir` (never `rm -rf` through it) and drop sibol_e2e. Run PHPUnit on PHP 8.0 and 8.3.
- If you change AI behaviour (prompts, demo answers): `ai:eval --demo` must pass, a new rule gets a case in
  Services/Ai/Evaluation/Cases.php, and check it with a few live calls on sibol_test.
- Small and safe only. Anything that is really a Phase B redesign goes on an "after the defense" list.
- Ask me first before: removing or hiding a feature, rule or permission check; adding a library or an outside
  service; sending more student data to the AI service; letting anyone but the student read a conversation;
  changing how grades are calculated. Never touch the AI_API_KEY line or commit the docs/*-prompt.md files.
- If `git status` shows changes you didn't make, or another session is working in this folder, stop and tell me.

## At the end
Confirm the demo school is as it was (practice sets, chats, users, uploaded files) and send me: what you fixed
(with commits), what you left and why, and the final question-and-answer list for my defense.
