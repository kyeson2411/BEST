# Presentation school

The school used for presentations and the defense demo. It is the same fictional school as the demo school,
cleaned so no screen says "demo" or "sample", with its AI-made parts written by the real AI model.

| File | What it is |
| --- | --- |
| `best-presentation.sql` | The whole `sibol` database. Importing it **deletes** the current `sibol` database first. Dates move to the day you import it. |
| `uploads/` | The class files the materials point to. They must go into `storage/uploads`. |
| `reset-presentation.bat` | Does both steps, then brings the database up to date for the app (`bin/sibol.php migrate`, which adds what newer versions need, such as anti-cheating). Double-click it to start the presentation again from the beginning. |
| `files-for-the-demo/` | A handout to upload during the demo (`Blood types and inheritance`, PDF and Word). |

All accounts use the password `Polomolok-2026`.

| Role | Username |
| --- | --- |
| Administrator | `teresa.villanueva` |
| Teacher (Science 9) | `ramon.dizon` |
| Student (Grade 9 Narra) | `andrea.cruz` |

## Reset by hand (phpMyAdmin)

1. Open http://localhost/phpmyadmin. Do not choose a database.
2. Click **Import**, choose `best-presentation.sql`, then click **Import** at the bottom.
3. In the project folder, run `C:\xampp\php\php.exe bin\sibol.php migrate`. The file was saved before some
   newer columns existed; without this step, pages that use them stop with a database error.
4. Copy everything inside `uploads` into `C:\xampp\htdocs\BEST\storage\uploads` (replace the files there).
5. Sign out of the app and sign in again.

## How it was made

Built on 25 October 2026 from `bin/sibol.php db:reset` in a separate copy of the project and database, then:
competency codes without the `DEMO-` prefix, staff email at `best-polomolok.edu.ph` (a domain that does not
exist, so no mail can reach anyone), a new password, the grading set-up of the live school (DepEd Order No. 8,
s. 2015, four quarters), and the demo-mode chats and practice replaced by ones made with the real AI. Every
record still has `is_demo = 1` in the database.
