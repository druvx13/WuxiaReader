# Wuxia Reader

```
Copyright (C) 2026 Druvx13

This Work is licensed under
the FFP (Freedom For People) License,
Version 1.0.

THE WORK IS PROVIDED "AS IS",
WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED.
```

---

A lightweight PHP MVC application for managing and reading novels. Users can browse novels, read chapters, leave comments, and like content. Admins can manage content and import novels from a variety of external sources.

## Features

* **User System:** Registration, login, and logout (reader role).
* **Novel Management:**
  * Browse novels in a responsive card grid.
  * Read chapters with previous/next navigation.
  * Adjustable reader font size (persisted via `localStorage`).
  * Distraction-free reading mode.
  * Reading progress bar.
* **Admin Panel:**
  * Add novels manually (with cover image upload or URL).
  * Add chapters manually.
  * Import from external sites:
    * FanMTL / Readwn-style clones
    * NovelHall
    * AllNovel.org
    * ReadNovelFull.com
    * NovelFull / NovelBin / Novel-Next and compatible clones
* **Interactions:** Like novels and chapters; post comments.
* **Responsive Design:** Mobile-first layout with hamburger navigation, works across phones, tablets, and desktops.
* **Local Fonts:** Lora served from `public/assets/fonts/` with Google Fonts CDN fallback.

## Architecture

The project follows a custom MVC pattern. See [ARCHITECTURE.md](ARCHITECTURE.md) for the full directory layout and design notes.

```
public/          Web root (assets, entry point, uploads)
src/Controllers  Request handling and business logic
src/Core         Router, Database, Config, View
src/Models       PDO-based data access
src/Services     Web scrapers
templates/       PHP view templates
```

## Requirements

* PHP 8.0 or higher
* MySQL 5.7 or higher (or MariaDB equivalent)
* Apache with `mod_rewrite` enabled
* PHP extensions: PDO (MySQL), cURL, DOM/XML

## Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/druvx13/WuxiaReader.git
   cd WuxiaReader
   ```

2. **Configure the database:**
   ```bash
   mysql -u username -p database_name < init_db.sql
   ```

3. **Environment setup:**
   ```bash
   cp .env.example .env
   ```
   Edit `.env`:
   ```ini
   DB_HOST=127.0.0.1
   DB_NAME=your_database_name
   DB_USER=your_username
   DB_PASS=your_password
   BASE_URL=http://localhost/WuxiaReader
   ```

4. **Web server:**
   Point your virtual host document root to the `public/` directory and ensure `.htaccess` overrides are allowed (`AllowOverride All`).

## Usage

### Reader

* **Sign Up / Login** – Create an account to like content and post comments.
* **Browse** – The home page lists all novels. Click any card to open the novel page.
* **Read** – Click a chapter link to start reading. Use the **A−** / **A+** buttons to adjust font size. Toggle **Distraction-free** to hide the header and comments.

### Admin

Promote a user to admin in the database:
```sql
UPDATE users SET role = 'admin' WHERE username = 'your_username';
```

Then visit `/admin/management` (or click **Management** in the navigation bar) to access:

| Tool | Description |
|---|---|
| Add Novel | Create a novel entry manually |
| Add Chapter | Attach a chapter to an existing novel |
| Import: FanMTL | Import from fanmtl.com and clones |
| Import: NovelHall | Import from novelhall.com |
| Import: AllNovel | Import from allnovel.org |
| Import: ReadNovelFull | Import from readnovelfull.com |
| Import: NovelFull | Import from novelfull.com, novelbin.com, novel-next.com and 30+ compatible clones |

## Development Notes

* **Autoloading:** PSR-4 compliant autoloader in `public/autoload.php` – no Composer needed.
* **Routing:** Routes defined in `public/index.php` using `App\Core\Router`.
* **Front-end:** No build step. Edit `public/assets/style.css` and `public/assets/app.js` directly.

## License

Licensed under the FFP (Freedom For People) License, Version 1.0 – see [LICENSE](LICENSE) for details.
