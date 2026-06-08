<?php
// view_image.php
// Serve binary images from SQLite BLOB storage

require_once __DIR__ . '/db.php';

$db = getDb();

if (isset($_GET['type']) && $_GET['type'] === 'logo') {
    // Serve trust logo
    $stmt = $db->prepare("SELECT logo_data, logo_mime FROM settings WHERE id = 'global_config' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    
    if ($row && !empty($row['logo_data'])) {
        header("Content-Type: " . $row['logo_mime']);
        header("Cache-Control: public, max-age=86400"); // Cache for 1 day
        
        if (is_resource($row['logo_data'])) {
            fpassthru($row['logo_data']);
        } else {
            echo $row['logo_data'];
        }
        exit;
    }
} elseif (isset($_GET['id'])) {
    // Serve expense bill image
    $stmt = $db->prepare("SELECT image_data, image_mime FROM expense_images WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $_GET['id']]);
    $row = $stmt->fetch();
    
    if ($row && !empty($row['image_data'])) {
        header("Content-Type: " . $row['image_mime']);
        header("Cache-Control: public, max-age=86400"); // Cache for 1 day
        
        if (is_resource($row['image_data'])) {
            fpassthru($row['image_data']);
        } else {
            echo $row['image_data'];
        }
        exit;
    }
}

// Fallback to a transparent 1x1 pixel image if requested image is not found
header("Content-Type: image/gif");
echo base64_decode("R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7");
exit;
?>
