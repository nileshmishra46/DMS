<?php
// db.php
// SQLite Database connection and auto-initialization script

$db_file = __DIR__ . '/database.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Enable foreign keys
    $pdo->exec("PRAGMA foreign_keys = ON;");
    
    // Create tables if they do not exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id TEXT PRIMARY KEY,
            community_name TEXT NOT NULL,
            registration_number TEXT,
            address TEXT,
            phone TEXT,
            email TEXT,
            logo_data BLOB,
            logo_mime TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS donations (
            id TEXT PRIMARY KEY,
            receipt_no TEXT UNIQUE NOT NULL,
            donor_id TEXT,
            donor_name TEXT NOT NULL,
            donor_phone TEXT,
            donor_pan TEXT,
            donation_date DATE NOT NULL,
            amount REAL NOT NULL,
            payment_mode TEXT NOT NULL,
            purpose TEXT NOT NULL,
            financial_year TEXT NOT NULL,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS expenses (
            id TEXT PRIMARY KEY,
            expense_date DATE NOT NULL,
            category TEXT NOT NULL,
            description TEXT NOT NULL,
            amount REAL NOT NULL,
            paid_to TEXT NOT NULL,
            financial_year TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS expense_images (
            id TEXT PRIMARY KEY,
            expense_id TEXT NOT NULL,
            image_data BLOB NOT NULL,
            image_mime TEXT NOT NULL,
            image_name TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(expense_id) REFERENCES expenses(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS opening_balances (
            id TEXT PRIMARY KEY,
            financial_year TEXT UNIQUE NOT NULL,
            opening_balance REAL NOT NULL
        );

        CREATE TABLE IF NOT EXISTS donors (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            phone TEXT,
            pan TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            allowed_pages TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Dynamic database migration check for existing tables
    try {
        $pdo->exec("ALTER TABLE donations ADD COLUMN donor_id TEXT;");
    } catch (PDOException $e) {
        // Ignore column already exists exception
    }
    
    try {
        $pdo->exec("ALTER TABLE settings ADD COLUMN theme_mode TEXT DEFAULT 'light';");
    } catch (PDOException $e) {
        // Ignore column already exists exception
    }
    
    try {
        $pdo->exec("ALTER TABLE settings ADD COLUMN theme_accent TEXT DEFAULT 'amber';");
    } catch (PDOException $e) {
        // Ignore column already exists exception
    }

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN allowed_pages TEXT;");
    } catch (PDOException $e) {
        // Ignore column already exists exception
    }
    
    // Seed default settings if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $stmt_insert = $pdo->prepare("
            INSERT INTO settings (id, community_name, registration_number, address, phone, email) 
            VALUES (:id, :name, :reg_no, :address, :phone, :email)
        ");
        $stmt_insert->execute([
            ':id' => 'global_config',
            ':name' => 'Community Trust Organization',
            ':reg_no' => 'TRUST/2026/8899',
            ':address' => '123 Heritage Temple Street, Community District, Pin: 400001',
            ':phone' => '+91 98765 43210',
            ':email' => 'contact@communitytrust.org'
        ]);
    }

    // Seed default admin user if empty
    $stmt_user = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt_user->fetchColumn() == 0) {
        $stmt_insert_user = $pdo->prepare("
            INSERT INTO users (id, username, password, role) 
            VALUES (:id, :username, :password, :role)
        ");
        $stmt_insert_user->execute([
            ':id' => 'admin_default',
            ':username' => 'Admin',
            ':password' => password_hash('Sumo@123', PASSWORD_BCRYPT),
            ':role' => 'admin'
        ]);
    }
    
} catch (PDOException $e) {
    die("Database Connection / Initialization Failed: " . $e->getMessage());
}

// Global helper functions
function getDb() {
    global $pdo;
    return $pdo;
}

// Financial Year Calculator (April 1 to March 31)
function getFinancialYear($dateString) {
    $date = new DateTime($dateString);
    $year = (int)$date->format('Y');
    $month = (int)$date->format('n'); // 1 to 12
    if ($month >= 4) {
        return $year . '-' . substr(($year + 1), -2);
    } else {
        return ($year - 1) . '-' . substr($year, -2);
    }
}

// Helper to generate UUIDs
function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Helper to check syntax or format currency in INR style
function formatCurrency($amount) {
    return '₹' . number_format((double)$amount, 2, '.', ',');
}

function showAccessDenied($message = "Access Denied") {
    $db = getDb();
    $org = $db->query("SELECT * FROM settings WHERE id = 'global_config'")->fetch();
    $theme_mode = $org['theme_mode'] ?? 'light';
    $theme_accent = $org['theme_accent'] ?? 'amber';
    $org_name = $org['community_name'] ?? 'Community Trust';
    ?>
    <!DOCTYPE html>
    <html lang="en" class="<?php echo $theme_mode === 'dark' ? 'dark' : ''; ?> h-full">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - <?php echo htmlspecialchars($org_name); ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;750&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <script src="https://unpkg.com/lucide@latest"></script>
        <style>
            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
                <?php if ($theme_accent === 'amber'): ?>
                    background: radial-gradient(at 0% 0%, hsla(38, 100%, 98%, 1) 0, transparent 40%), #fffbeb;
                <?php elseif ($theme_accent === 'orange'): ?>
                    background: radial-gradient(at 0% 0%, hsla(16, 100%, 98%, 1) 0, transparent 40%), #fff8f5;
                <?php elseif ($theme_accent === 'blue'): ?>
                    background: radial-gradient(at 0% 0%, hsla(215, 100%, 98%, 1) 0, transparent 40%), #f8fafc;
                <?php elseif ($theme_accent === 'emerald'): ?>
                    background: radial-gradient(at 0% 0%, hsla(150, 80%, 98%, 1) 0, transparent 40%), #f7fee7;
                <?php elseif ($theme_accent === 'rose'): ?>
                    background: radial-gradient(at 0% 0%, hsla(350, 100%, 99%, 1) 0, transparent 40%), #fff1f2;
                <?php elseif ($theme_accent === 'indigo'): ?>
                    background: radial-gradient(at 0% 0%, hsla(230, 100%, 98%, 1) 0, transparent 40%), #f9fafb;
                <?php elseif ($theme_accent === 'violet'): ?>
                    background: radial-gradient(at 0% 0%, hsla(265, 100%, 98%, 1) 0, transparent 40%), #fbfbfe;
                <?php endif; ?>
            }
            html.dark body {
                <?php if ($theme_accent === 'amber'): ?>
                    background: radial-gradient(at 0% 0%, hsla(38, 50%, 10%, 1) 0, transparent 50%), #0c0a09;
                <?php elseif ($theme_accent === 'orange'): ?>
                    background: radial-gradient(at 0% 0%, hsla(16, 50%, 10%, 1) 0, transparent 50%), #0c0a09;
                <?php elseif ($theme_accent === 'blue'): ?>
                    background: radial-gradient(at 0% 0%, hsla(220, 40%, 12%, 1) 0, transparent 50%), #0b0f19;
                <?php elseif ($theme_accent === 'emerald'): ?>
                    background: radial-gradient(at 0% 0%, hsla(150, 40%, 10%, 1) 0, transparent 50%), #052e16;
                <?php elseif ($theme_accent === 'rose'): ?>
                    background: radial-gradient(at 0% 0%, hsla(350, 40%, 11%, 1) 0, transparent 50%), #0f0507;
                <?php elseif ($theme_accent === 'indigo'): ?>
                    background: radial-gradient(at 0% 0%, hsla(230, 40%, 12%, 1) 0, transparent 50%), #09090b;
                <?php elseif ($theme_accent === 'violet'): ?>
                    background: radial-gradient(at 0% 0%, hsla(265, 40%, 12%, 1) 0, transparent 50%), #09050d;
                <?php endif; ?>
            }
            .glass-card {
                background: rgba(255, 255, 255, 0.65);
                backdrop-filter: blur(18px) saturate(180%);
                border: 1px solid rgba(255, 255, 255, 0.45);
            }
            html.dark .glass-card {
                background: rgba(28, 25, 23, 0.45);
                border: 1px solid rgba(255, 255, 255, 0.08);
            }
        </style>
    </head>
    <body class="h-full flex items-center justify-center p-6 text-slate-800 dark:text-stone-200 antialiased transition-colors duration-300">
        <div class="glass-card max-w-md w-full rounded-2xl p-8 shadow-xl text-center space-y-6 border border-slate-200/50 dark:border-stone-800/40">
            <div class="mx-auto h-16 w-16 bg-rose-500/10 dark:bg-rose-500/20 text-rose-600 dark:text-rose-450 border border-rose-500/20 rounded-full flex items-center justify-center">
                <i data-lucide="shield-alert" class="h-8 w-8"></i>
            </div>
            <div class="space-y-2">
                <h1 class="text-xl font-bold tracking-tight font-title text-slate-900 dark:text-stone-100">Access Denied</h1>
                <p class="text-sm text-slate-500 dark:text-stone-400 leading-relaxed"><?php echo htmlspecialchars($message); ?></p>
            </div>
            <div class="pt-4 border-t border-slate-200/30 dark:border-stone-800/30 flex flex-col gap-2">
                <a href="logout.php" class="bg-gradient-to-r from-rose-600 to-rose-500 hover:from-rose-700 hover:to-rose-600 text-white text-xs px-5 py-2.5 rounded-xl font-bold transition-all shadow-md shadow-rose-500/15 flex items-center justify-center gap-1.5">
                    <i data-lucide="log-out" class="h-4 w-4"></i>
                    Log Out & Switch User
                </a>
            </div>
        </div>
        <script>lucide.createIcons();</script>
    </body>
    </html>
    <?php
}

// Global Session check for page protection (redirect to login.php if not authenticated)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_script = basename($_SERVER['SCRIPT_NAME']);
if ($current_script !== 'login.php' && $current_script !== 'view_image.php' && $current_script !== 'logout.php') {
    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    try {
        $stmt_u_perm = $pdo->prepare("SELECT role, allowed_pages FROM users WHERE id = :id");
        $stmt_u_perm->execute([':id' => $_SESSION['user_id']]);
        $user_perm = $stmt_u_perm->fetch();
        
        if (!$user_perm) {
            session_destroy();
            header("Location: login.php");
            exit;
        }
        
        $_SESSION['user_role'] = $user_perm['role'];
        $_SESSION['allowed_pages'] = $user_perm['allowed_pages'];
        
        if ($user_perm['role'] !== 'admin') {
            if ($current_script === 'users.php') {
                header("Location: index.php");
                exit;
            }
            
            $script_map = [
                'index.php' => 'dashboard',
                'donations.php' => 'donations',
                'expenses.php' => 'expenses',
                'reports.php' => 'reports',
                'settings.php' => 'settings'
            ];
            
            $current_page = $script_map[$current_script] ?? '';
            
            if ($current_page !== '') {
                // If allowed_pages is strictly null (e.g. existing users after migration), default to all pages
                if ($user_perm['allowed_pages'] === null) {
                    $allowed_pages = ['dashboard', 'donations', 'expenses', 'reports', 'settings'];
                } else {
                    $allowed_pages = array_filter(array_map('trim', explode(',', $user_perm['allowed_pages'])));
                }
                
                if (empty($allowed_pages)) {
                    showAccessDenied("You do not have access to any pages. Please contact your Administrator.");
                    exit;
                }
                
                if (!in_array($current_page, $allowed_pages)) {
                    // Redirect standard user to their first allowed page if they hit an unauthorized route
                    $page_urls = [
                        'dashboard' => 'index.php',
                        'donations' => 'donations.php',
                        'expenses' => 'expenses.php',
                        'reports' => 'reports.php',
                        'settings' => 'settings.php'
                    ];
                    
                    foreach ($allowed_pages as $page) {
                        if (isset($page_urls[$page])) {
                            header("Location: " . $page_urls[$page]);
                            exit;
                        }
                    }
                    
                    showAccessDenied("Access Denied: You do not have permission to view this page.");
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        // Fallback for database error
    }
}
?>
