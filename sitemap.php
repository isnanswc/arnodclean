<?php
/**
 * Dynamic XML Sitemap for Arno D Clean
 */
header("Content-Type: application/xml; charset=utf-8");

require_once 'db.php';

// Try to get base URL from server
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// If in a subdirectory like /adc/, detect it
$script_name = $_SERVER['SCRIPT_NAME'];
$base_dir = str_replace('sitemap.php', '', $script_name);
$baseUrl = $protocol . "://" . $host . $base_dir;

// Fetch Published Articles
$stmt = $pdo->query("SELECT slug, created_at FROM articles WHERE status = 'published' ORDER BY created_at DESC");
$articles = $stmt->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Main Pages -->
    <url>
        <loc><?php echo $baseUrl; ?></loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?php echo $baseUrl; ?>blog.php</loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>

    <!-- Articles -->
    <?php foreach ($articles as $art): ?>
    <url>
        <loc><?php echo $baseUrl; ?>blog/<?php echo htmlspecialchars($art['slug']); ?></loc>
        <lastmod><?php echo date('Y-m-d', strtotime($art['created_at'])); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    <?php endforeach; ?>
</urlset>
