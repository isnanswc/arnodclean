<?php
require_once 'db.php';

echo "Scanning for dirty slugs...\n";
$stmt = $pdo->query("SELECT id, title, slug FROM articles WHERE slug LIKE '%-'");
$dirty = $stmt->fetchAll();

echo "Found " . count($dirty) . " articles with trailing hyphens.\n";

foreach ($dirty as $art) {
    $oldSlug = $art['slug'];
    $newSlug = rtrim($oldSlug, '-');
    
    // Ensure uniqueness? Usually okay unless duplicate exists.
    // Simple check
    $check = $pdo->prepare("SELECT id FROM articles WHERE slug = ? AND id != ?");
    $check->execute([$newSlug, $art['id']]);
    if ($check->fetch()) {
        echo "Skipping ID {$art['id']}: Target slug '$newSlug' already exists.\n";
        continue;
    }
    
    $update = $pdo->prepare("UPDATE articles SET slug = ? WHERE id = ?");
    $update->execute([$newSlug, $art['id']]);
    echo "Fixed ID {$art['id']}: '$oldSlug' -> '$newSlug'\n";
}

echo "Done.\n";
?>
