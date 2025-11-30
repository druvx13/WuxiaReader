<?php
$title = 'Sign up';
include 'header.php';
?>
    <section class="auth-page">
        <h1>Sign up</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
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
            <label>
                Confirm password
                <input type="password" name="password2" required>
            </label>
            <button type="submit">Create account</button>
        </form>
    </section>
<?php include 'footer.php'; ?>
