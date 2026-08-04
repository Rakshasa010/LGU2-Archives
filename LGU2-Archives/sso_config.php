<?php
/**
 * SSO frontend configuration helper.
 * Reads Google OAuth settings from the project .env and emits a small
 * JS config object consumed by assets/js/google-sso.js.
 *
 * The base URL (APP_URL) is used to call google_auth.php and dynamically
 * switches between production (https://las.spvalenzuela.com) and local
 * development (http://localhost:8000) via the APP_URL environment variable.
 *
 * Usage inside a PHP page:
 *   include __DIR__ . '/sso_config.php';
 *   (outputs a <script> tag - place before loading assets/js/google-sso.js)
 */

if (basename($_SERVER['PHP_SELF'] ?? '') === 'sso_config.php') {
    http_response_code(403);
    exit('Direct access not allowed.');
}

$__ssoEnvPath = __DIR__ . '/../.env';
$__ssoEnv = [];
if (file_exists($__ssoEnvPath)) {
    foreach (file($__ssoEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $__ssoLine) {
        $__ssoLine = trim($__ssoLine);
        if ($__ssoLine === '' || strpos($__ssoLine, '#') === 0 || strpos($__ssoLine, '=') === false) {
            continue;
        }
        [$__ssoKey, $__ssoVal] = explode('=', $__ssoLine, 2);
        $__ssoEnv[trim($__ssoKey)] = trim($__ssoVal, " \t\n\r\0\"'");
    }
}

$__ssoClientId = trim((string)($__ssoEnv['GOOGLE_CLIENT_ID'] ?? ''));
$__ssoAppUrl = trim((string)($__ssoEnv['APP_URL'] ?? ''));

// Fallback: derive the base URL from the current request when APP_URL is unset
if ($__ssoAppUrl === '') {
    $__ssoScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $__ssoHost   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $__ssoDir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $__ssoAppUrl = $__ssoScheme . '://' . $__ssoHost . $__ssoDir;
}

$__ssoAppUrl = rtrim($__ssoAppUrl, '/');
?>
<script>
    window.LAS_GOOGLE_CONFIG = {
        clientId: <?php echo json_encode($__ssoClientId); ?>,
        appUrl: <?php echo json_encode($__ssoAppUrl); ?>
    };
</script>
