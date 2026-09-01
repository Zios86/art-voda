<!-- Общие meta и CSS. При изменении ресурсов обновить assetVersion и кеш в sw.js. -->
<meta charset="utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<?php
$assetVersion = '20260901-1';
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$canonicalPaths = [
    '/index.php' => '/',
    '/contact.php' => '/contact.php',
    '/privacy.php' => '/privacy.php',
    '/consent.php' => '/consent.php',
    '/offer.php' => '/offer.php',
    '/documents.php' => '/documents.php',
];
$canonicalPath = $canonicalPaths[$requestPath] ?? $requestPath;
$canonicalUrl = 'https://киоскводы.рф' . $canonicalPath;
?>
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
<meta property="og:image" content="https://киоскводы.рф/img/og-water-v4.jpg" />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />
<meta property="og:image:alt" content="Чистое озеро на Карельском перешейке" />
<meta property="og:locale" content="ru_RU" />
<link href="/img/favicon.png?v=<?= $assetVersion ?>" rel="icon" type="image/png" />
<link rel="manifest" href="/manifest.webmanifest?v=<?= $assetVersion ?>" />
<meta name="theme-color" content="#092f4a" />
<link rel="apple-touch-icon" href="/img/pwa-icon-192.png" />
<link rel="stylesheet" href="/css/grid.css?v=<?= $assetVersion ?>" />
<link rel="stylesheet" href="/css/style.css?v=<?= $assetVersion ?>" />
<link rel="stylesheet" href="/css/media.css?v=<?= $assetVersion ?>" />
<link rel="stylesheet" href="/css/compliance.css?v=<?= $assetVersion ?>" />
<link rel="stylesheet" href="/css/showcase.css?v=<?= $assetVersion ?>" />
<link rel="stylesheet" href="/css/design-v14.css?v=<?= $assetVersion ?>" />
