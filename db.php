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
?>
