# Architecture

This repository follows the Model-View-Controller (MVC) architectural pattern. This improves code organisation, maintainability, and security.

## Directory Structure

```
WuxiaReader/
├── public/                  Web root (point your server here)
│   ├── assets/
│   │   ├── fonts/           Locally-hosted web fonts (Lora, woff2)
│   │   ├── app.js           Front-end JavaScript (IIFE, no build step)
│   │   └── style.css        Main stylesheet (CSS custom properties + responsive)
│   ├── uploads/             User-uploaded cover images (created at runtime)
│   ├── .htaccess            Apache rewrite rules for the front controller
│   ├── autoload.php         PSR-4 autoloader (App\ → src/)
│   └── index.php            Front controller – bootstraps & routes all requests
│
├── src/                     Application source code (outside web root)
│   ├── Controllers/
│   │   ├── AdminController.php   Admin panel + novel/chapter import
│   │   ├── AuthController.php    Login, signup, logout
│   │   ├── HomeController.php    Home / novel listing
│   │   └── NovelController.php   Novel detail, chapter reader, like, comment (AJAX)
│   ├── Core/
│   │   ├── Config.php       .env loader (key=value parser)
│   │   ├── Database.php     PDO singleton
│   │   ├── Router.php       Lightweight regex/exact-match router
│   │   └── View.php         Template renderer + redirect helper
│   ├── Models/
│   │   ├── Chapter.php      Chapter CRUD + prev/next navigation
│   │   ├── Comment.php      Comment retrieval + creation
│   │   ├── Like.php         Toggle-like with transaction safety
│   │   ├── Novel.php        Novel listing + creation
│   │   └── User.php         User lookup + creation (password_hash)
│   └── Services/            Standalone scraper functions (no namespace)
│       ├── allnovel_scraper.php
│       ├── fanmtl_scraper.php
│       ├── novelfull_scraper.php
│       ├── novelhall_scraper.php
│       └── readnovelfull_scraper.php
│
├── templates/               PHP view templates
│   ├── admin/
│   │   ├── add_chapter.php
│   │   ├── add_novel.php
│   │   ├── import_form.php
│   │   ├── import_log_end.php
│   │   └── import_log_start.php
│   ├── partials/
│   │   └── comment.php      AJAX-rendered comment partial
│   ├── 404.php
│   ├── chapter.php
│   ├── footer.php
│   ├── header.php
│   ├── home.php
│   ├── login.php
│   ├── novel.php
│   └── signup.php
│
├── .env                     Runtime secrets (not committed)
├── .env.example             Template for .env
├── .htaccess                Root-level redirect into public/
├── init_db.sql              Database schema
└── README.md
```

## Key Design Decisions

### Front Controller
`public/index.php` is the single entry point. It bootstraps the session, loads
configuration, and hands every request to the `Router`.

### Router
`App\Core\Router` supports exact-path matches and `#regex#` patterns.  
On a 404 it calls `View::render('404')` so the proper layout is shown.

### Autoloader
A single `spl_autoload_register` in `public/autoload.php` maps `App\` → `src/`.  
No Composer is required.

### View
`App\Core\View::render($view, $data)` extracts `$data` into local variables and
`require`s the matching template from `templates/`.  
`View::redirect($path)` prepends `BASE_URL` for relative paths.

### Models
Static-method models wrap all SQL via PDO prepared statements. Database::connect()
returns a lazily-created singleton PDO instance.

### Front-End
* `style.css` – CSS custom properties, responsive grid, mobile hamburger nav,
  reading-progress bar, scroll-to-top, font-size controls.
* `app.js` – wrapped in an IIFE, reads `BASE_URL` from a `<meta>` tag so AJAX
  calls work correctly regardless of server path.  Features: mobile nav toggle,
  like/comment AJAX, distraction-free mode, reader font-size persistence
  (localStorage), scroll-to-top, reading progress bar.
* Lora font is served locally from `public/assets/fonts/` with Google Fonts as
  a `src()` fallback in `@font-face`.

## Setup

1. Copy `.env.example` → `.env` and fill in database credentials and `BASE_URL`.
2. Import `init_db.sql` into your MySQL database.
3. Point your web server document root to `public/`.
4. Ensure `mod_rewrite` (Apache) is enabled; the `.htaccess` files handle routing.
5. Make `public/uploads/` writable by the web server (created automatically on
   first upload).

## Deployment Checklist

* Web root → `public/`
* `src/` and `.env` are above the web root or blocked from direct access.
* `public/uploads/` is writable.
* PHP ≥ 8.0, MySQL ≥ 5.7, PDO, cURL, DOM/XML extensions enabled.
