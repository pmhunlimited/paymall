<?php
// update_assets.php - Run once to update all PHP files

$directories = [
    'admin',
    'user',
    'installer'
];

$updated = 0;

foreach ($directories as $dir) {
    if (!is_dir(__DIR__ . '/' . $dir)) continue;
    
    $files = glob(__DIR__ . "/{$dir}/*.php");
    foreach ($files as $file) {
        $content = file_get_contents($file);
        
        // Skip if already updated
        if (strpos($content, '/assets/css/main.css') !== false) {
            continue;
        }

        // Remove inline styles
        $content = preg_replace('#<style>.*?</style>#s', '', $content);
        
        // Remove inline scripts
        $content = preg_replace('#<script>.*?</script>#s', '', $content);
        
        // Add CSS links in head
        $content = str_replace(
            '<head>',
            '<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/main.css">',
            $content
        );
        
        // Add installer CSS if needed
        if (strpos($file, 'installer/') !== false) {
            $content = str_replace(
                '<link rel="stylesheet" href="/assets/css/main.css">',
                '<link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/installer.css">',
                $content
            );
        }
        
        // Add CSRF meta tag
        $content = str_replace(
            '<link rel="stylesheet" href="/assets/css/main.css">',
            '<link rel="stylesheet" href="/assets/css/main.css">
    <meta name="csrf-token" content="<?= bin2hex(random_bytes(32)) ?>">',
            $content
        );
        
        // Add JS before </body>
        $content = str_replace(
            '</body>',
            '<script src="/assets/js/main.js"></script>
</body>',
            $content
        );
        
        // Add installer JS if needed
        if (strpos($file, 'installer/') !== false) {
            $content = str_replace(
                '<script src="/assets/js/main.js"></script>',
                '<script src="/assets/js/main.js"></script>
    <script src="/assets/js/installer.js"></script>',
                $content
            );
        }
        
        // Update logo references
        $content = str_replace(
            '<div class="logo"><h2><i class="fas fa-bolt"></i>',
            '<div class="logo">
  <img src="/assets/images/logo.svg" alt="Logo" width="32" height="32" style="vertical-align: middle; margin-right: 8px;">
  <span>',
            $content
        );
        
        // Update "Back" buttons to use class
        $content = str_replace(
            'class="btn" style="background: #6c757d;"',
            'class="btn btn-secondary btn-back"',
            $content
        );
        
        // Save updated file
        file_put_contents($file, $content);
        $updated++;
    }
}

echo "✅ Updated {$updated} files to use external assets.\n";
echo "📁 Run: php update_assets.php\n";
?>