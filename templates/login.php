<?php
/**
 * Login Page Template
 *
 * This template displays the user login form.
 *
 * @package    WuxiaReader
 * @subpackage Templates
 * @author     Anonymous
 * @license    LUCA Free License
 * @version    1.0
 */

$title = 'Login';
include 'header.php';
?>
    <section class="auth-page">
        <h1>Login</h1>
        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" class="form">
            <label>
                Username
                <input type="text" name="username" required>
            </label>
            <label>
                Password
                <input type="password" name="password" required>
            </label>
            <button type="submit">Login</button>
        </form>
    </section>
<?php include 'footer.php'; ?>
