<?php
// Relative URLs also support installations under /SENA_Asset/.
$favicon_base = $favicon_prefix ?? '';
$favicon_version = (string) filemtime(__DIR__ . '/../favicon.ico');
?>
<link rel="icon" href="<?=htmlspecialchars($favicon_base)?>favicon.ico?v=<?=$favicon_version?>" sizes="16x16 32x32 48x48 64x64" type="image/x-icon">
<link rel="icon" href="<?=htmlspecialchars($favicon_base)?>assets/icons/favicon-32x32.png?v=<?=$favicon_version?>" sizes="32x32" type="image/png">
<link rel="icon" href="<?=htmlspecialchars($favicon_base)?>assets/icons/favicon-16x16.png?v=<?=$favicon_version?>" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="<?=htmlspecialchars($favicon_base)?>assets/icons/apple-touch-icon.png?v=<?=$favicon_version?>" sizes="180x180">
<link rel="manifest" href="<?=htmlspecialchars($favicon_base)?>site.webmanifest?v=<?=$favicon_version?>">
<?php unset($favicon_base, $favicon_version, $favicon_prefix); ?>
