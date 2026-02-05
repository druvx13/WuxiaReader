<?php
/**
 * Footer Template
 *
 * This template contains the page footer markup and closing HTML tags.
 * Included by all pages.
 *
 * @package    WuxiaReader
 * @subpackage Templates
 * @author     Anonymous
 * @license    LUCA Free License
 * @version    1.0
 */

use App\Core\Config;
$base = Config::get('BASE_URL');
?>
</main>
<script src="<?= $base ?>/assets/app.js"></script>
</body>
</html>
