<?php
// db.php - Database connection configuration using PDO

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'library_db';
$user = getenv('DB_USER') ?: 'libuser';
$pass = getenv('DB_PASS') ?: 'libpass';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // If database or user doesn't exist, attempt fallback to root or throw error
    try {
        $rootDsn = "mysql:host=$host;port=$port;charset=$charset";
        $pdoRoot = new PDO($rootDsn, 'root', '', $options);
        $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $pdo = new PDO($dsn, 'root', '', $options);
    } catch (\PDOException $ex) {
        die("Database Connection Failed: " . $e->getMessage());
    }
}

/**
 * Auto-initialize tables if they don't exist
 */
function initDatabaseSchema($pdo) {
    $sqlBookMaster = "
    CREATE TABLE IF NOT EXISTS ExcelBookMaster (
        BookNumber VARCHAR(50) NOT NULL PRIMARY KEY,
        BookName VARCHAR(255) NOT NULL,
        BookRemarks VARCHAR(255) DEFAULT NULL,
        AutherName VARCHAR(255) NOT NULL,
        BookClass VARCHAR(100) DEFAULT NULL,
        BookCode VARCHAR(100) DEFAULT NULL,
        CallNumber VARCHAR(100) DEFAULT NULL,
        BookPrice DECIMAL(10,2) DEFAULT 0.00,
        Language VARCHAR(50) DEFAULT 'Malayalam',
        CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sqlMember = "
    CREATE TABLE IF NOT EXISTS MstrMember (
        MemberNumber VARCHAR(50) NOT NULL PRIMARY KEY,
        MemberName VARCHAR(255) NOT NULL,
        PhoneHome VARCHAR(50) DEFAULT NULL,
        HouseName VARCHAR(255) DEFAULT NULL,
        StudentSex VARCHAR(20) DEFAULT NULL,
        MemBirthDate DATE DEFAULT NULL,
        MemberRemarks VARCHAR(255) DEFAULT NULL,
        CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sqlTrans = "
    CREATE TABLE IF NOT EXISTS TransBookIssue (
        SlNo INT AUTO_INCREMENT PRIMARY KEY,
        IssueDate DATE NOT NULL,
        DueDate DATE DEFAULT NULL,
        ReturnDate DATE DEFAULT NULL,
        BookNumber VARCHAR(50) NOT NULL,
        MemberNumber VARCHAR(50) NOT NULL,
        MemberName VARCHAR(255) NOT NULL,
        Status ENUM('Issued', 'Returned') DEFAULT 'Issued',
        FOREIGN KEY (BookNumber) REFERENCES ExcelBookMaster(BookNumber) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (MemberNumber) REFERENCES MstrMember(MemberNumber) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sqlBookMaster);
    $pdo->exec($sqlMember);
    $pdo->exec($sqlTrans);
}

initDatabaseSchema($pdo);
