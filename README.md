### OptimaBank VoucherSystem (PHP)

A simple voucher e‑commerce system built with PHP, MySQL, and Composer libraries (Google OAuth, Cloudinary, Guzzle, Dotenv).

---

### Quick start

Prerequisites
- **XAMPP** (Apache + MySQL) on Windows
- **PHP 8.0+** (bundled with XAMPP is fine)
- **Composer** installed and in PATH

Project location
- Place the project under `C:\xampp\htdocs\vouchersystem`
- Local URL: `http://localhost/vouchersystem/`

---

### Setup (follow the image you shared)
1. Open terminal (Command Prompt).
2. Go to where you want the folder, e.g.:
   ```bash
   cd C:/xampp/htdocs
   ```
3. Clone the repo:
   ```bash
   git clone https://github.com/khaliszz/VoucherSystem.git vouchersystem
   ```
   Or use GitHub Desktop → Add local repo later.
4. If using GitHub Desktop, Add local repository and choose the project path you cloned above.
5. Open the project folder.
6. Put the `.env` file inside the project root (`vouchersystem/.env`).
7. From the terminal inside the project folder, install dependencies:
   ```bash
   composer install
   ```
9. Start Apache in XAMPP.
10. Open the site: `http://localhost/vouchersystem/`

---


Notes
- The app loads environment variables using `vlucas/phpdotenv`.
- Google OAuth console must include the exact redirect URI(localhost/vouchersystem) above.
---

### Project scripts and entry points
- Main site: `homepage.php` → `http://localhost/vouchersystem/`
- Auth pages: `login.php`, `signup.php`, `logout.php`
- Google OAuth config: `google-config.php` and `google-callback.php`
- Image upload service: `cloudinary_upload.php`
- Database connection: `connection.php`

---

### Troubleshooting
- If classes are not found, ensure `composer install` completed and `vendor/` exists.
- If `.env` values are not loading, confirm the file path is `vouchersystem/.env` and the web server user can read it.
- If images fail to upload, verify Cloudinary credentials and your machine has internet access.



