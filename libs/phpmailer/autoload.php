<?php
/**
 * Minimal autoloader for the vendored PHPMailer 6 sources (no Composer at runtime).
 * Order matters: interfaces and exception class load before their consumers.
 */
require __DIR__ . '/src/Exception.php';
require __DIR__ . '/src/OAuthTokenProvider.php';
require __DIR__ . '/src/OAuth.php';
require __DIR__ . '/src/PHPMailer.php';
require __DIR__ . '/src/SMTP.php';
require __DIR__ . '/src/POP3.php';
require __DIR__ . '/src/DSNConfigurator.php';
