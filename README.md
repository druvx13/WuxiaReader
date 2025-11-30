# Novel Library Application

A lightweight PHP MVC application for managing and reading novels. This application allows users to read novels, track chapters, leave comments, and like their favorite content. It includes an admin interface for managing content and importing novels from external sources.

## Features

*   **User System:** Registration, login, and profile management (reader role).
*   **Novel Management:**
    *   Browse and search novels.
    *   Read chapters with navigation.
    *   Admin panel to add novels and chapters manually.
*   **Importing:** Tools to import novels from external sites (FanMTL, NovelHall).
*   **Interactions:** Users can like novels/chapters and leave comments.
*   **Responsive Design:** Clean, simple interface for reading.

## Architecture

The project follows a custom MVC (Model-View-Controller) architecture:

*   **`src/Controllers`**: Handles incoming requests and application logic.
*   **`src/Models`**: Interaction with the MySQL database.
*   **`src/Core`**: Core system components (Router, Database, Config, View).
*   **`src/Services`**: Specialized services (e.g., web scrapers).
*   **`templates`**: PHP views for rendering HTML.
*   **`public`**: Web root directory (assets, entry point).

## Requirements

*   PHP 7.4 or higher
*   MySQL 5.7 or higher
*   Apache Web Server (with `mod_rewrite` enabled)
*   PDO PHP Extension
*   cURL PHP Extension (for importers)
*   DOM/XML PHP Extension (for importers)

## Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/druvx13/WuxiaReader.git
    cd WuxiaReader
    ```

2.  **Configure the database:**
    *   Create a new MySQL database.
    *   Import the schema from `init_db.sql`:
        ```bash
        mysql -u username -p database_name < init_db.sql
        ```

3.  **Environment Setup:**
    *   Copy `.env.example` to `.env`:
        ```bash
        cp .env.example .env
        ```
    *   Edit `.env` and update the database credentials and base URL:
        ```ini
        DB_HOST=127.0.0.1
        DB_NAME=your_database_name
        DB_USER=your_username
        DB_PASS=your_password
        BASE_URL=http://localhost/your-app-path
        ```

4.  **Web Server Configuration:**
    *   Point your virtual host to the `public/` directory.
    *   Ensure `.htaccess` overrides are allowed if using Apache.

## Usage

### User

*   **Sign Up/Login**: Create an account to like and comment.
*   **Read**: Browse the home page for novels, click to read chapters.

### Admin

*   The default database script does not create an admin user. You must manually promote a user in the database:
    ```sql
    UPDATE users SET role = 'admin' WHERE username = 'your_username';
    ```
*   Access the admin panel at `/admin/management` (or via the link in the footer/header if logged in as admin).
*   **Add Novel**: Manually create a novel entry.
*   **Add Chapter**: Add content to a novel.
*   **Import**: Use the importers to fetch content from supported external sites.

## Development

*   **Autoloading**: A simple PSR-4 compliant autoloader is defined in `public/autoload.php`.
*   **Routing**: Routes are defined in `public/index.php` using `App\Core\Router`.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
