# Architectural Improvements

This repository has been refactored to follow the Model-View-Controller (MVC) architectural pattern. This improves code organization, maintainability, and security.

## Structure

*   `public/`: Web root directory. Contains the entry point `index.php` and static assets.
    *   `assets/`: CSS, JS, and images.
    *   `uploads/`: User uploaded content (e.g., covers).
    *   `index.php`: The front controller.
    *   `autoload.php`: Simple SPL autoloader.
*   `src/`: Application source code.
    *   `Core/`: Core framework components (Config, Database, Router, View).
    *   `Controllers/`: Controllers handling user input and application logic.
    *   `Models/`: Data models for interacting with the database.
    *   `Services/`: Helper services (e.g., scrapers).
    *   `Views/`: (Not used directly, templates are in `templates/` to keep them separate from logic).
*   `templates/`: HTML templates for views.
*   `.env`: Environment variables (database credentials, etc.). **Do not commit this file.**
*   `.env.example`: Example environment variables file.

## Improvements

1.  **MVC Architecture**: Separated logic (Controllers), data (Models), and presentation (Views/Templates).
2.  **Front Controller**: `public/index.php` serves as the single entry point, handling routing and bootstrapping.
3.  **Routing**: A `Router` class handles URL mapping to controllers, replacing the large `if/else` block in the original `index.php`.
4.  **Database Abstraction**: `Database` class manages the PDO connection (Singleton pattern), and Models encapsulate SQL queries.
5.  **Configuration Management**: `Config` class loads settings from a `.env` file, preventing hardcoded credentials in the code.
6.  **Security**:
    *   Moved credentials to `.env`.
    *   Input handling is done in Controllers.
    *   Views escape output using `htmlspecialchars` (via `h()` helper or direct calls).
    *   `p.php` (which exposed password hashing) was removed.
7.  **Autoloading**: A simple autoloader maps `App\` namespace to the `src/` directory.

## Setup

1.  Copy `.env.example` to `.env` and update the values with your database credentials.
2.  Configure your web server to point the document root to the `public/` directory.
3.  Ensure `mod_rewrite` (Apache) or equivalent is enabled to route all requests to `index.php`.

## Deployment

*   Ensure `public/` is the web root.
*   Ensure `src/` and `.env` are outside the web root or protected from direct access.
*   Ensure `public/uploads` is writable by the web server.
