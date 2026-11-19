# Gliztygems Event Center (PHP + MySQL)

Folders: `frontend/` (website), `backend/` (PHP API), `database/` (MySQL script).

## Run on XAMPP
1. Start Apache and MySQL in XAMPP.
2. In phpMyAdmin, open the Import tab and import `database/gliztygems_db.sql`. This fills the selected database (`gliztygemseventcenter`) with the tables, halls and the admin account.
3. Copy this whole `gliztygems` folder into `C:\xampp\htdocs\`.
4. `backend/config.php` is already set to the database `gliztygemseventcenter`. If your database has a different name or your MySQL has a password, edit it.
5. Test the API: `http://localhost/gliztygems/backend/api/halls.php` should show the three halls as JSON.
6. Open the website: `http://localhost/gliztygems/frontend/index.html`. The footer should say "Connected to the MySQL database."

Admin login: `admin` / `admin123` (change it in the `admin` table before real use).
Payments are simulated: any valid card is approved, and a card ending in 0002 is declined.
See `backend/README.md` for every endpoint.
