<?php

declare(strict_types=1);

// Sets the feedback recipients of the dev instance, called by dev-setup.sh
$file = dirname(__DIR__) . '/config/system/settings.php';
$settings = require $file;
$settings['EXTENSIONS']['backend_feedback']['recipients'] = 'admin@example.org, support@example.org';
file_put_contents($file, "<?php\nreturn " . var_export($settings, true) . ";\n");
