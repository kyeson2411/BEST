# Sample data (the demo school, ready to import)

`sibol-demo.sql` is the fictional demo school as it was on 23 October 2026: all accounts, classes, materials,
grades, practice sets, companion chats, and DepEd Order No. 8 set up in four quarters. `uploads/` holds the
files those materials and submissions point to. Use both together.

All demo accounts share the password `Sibol-demo-2026`. Nothing secret is inside: no `.env`, no AI key, no
`APP_KEY`, and no invitation or reset links (that table is left empty).

## Import on another computer

1. Put the project in `C:\xampp\htdocs\BEST`, copy `.env.example` to `.env`, and add your own `AI_API_KEY`
   (the key is never in this folder or on GitHub). Run `C:\xampp\php\php.exe bin\sibol.php key:generate`.
2. In phpMyAdmin, create a database named `sibol` with collation `utf8mb4_unicode_ci`, open it, choose
   **Import**, select `database/sample/sibol-demo.sql`, and run it. Or in Command Prompt:
   ```bat
   C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE sibol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 sibol < database\sample\sibol-demo.sql
   ```
3. Bring the database up to date: the file was saved before newer columns existed (such as anti-cheating).
   ```bat
   C:\xampp\php\php.exe bin\sibol.php migrate
   ```
4. Copy everything inside `database/sample/uploads` into `storage/uploads`:
   ```bat
   xcopy /E /I /Y database\sample\uploads storage\uploads
   ```
5. Open http://localhost/BEST/public and sign in.

Dates in this file stay as they were, so due dates move into the past as time goes on. For a fresh demo
school with dates around today, run `C:\xampp\php\php.exe bin\sibol.php db:reset` instead (it replaces the
imported data).
