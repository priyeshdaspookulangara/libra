<?php
// seed.php - Seed sample data for testing

require_once __DIR__ . '/db.php';

try {
    // Insert sample members
    $stmtMem = $pdo->prepare("INSERT IGNORE INTO MstrMember (MemberNumber, MemberName, PhoneHome, HouseName, StudentSex, MemBirthDate, MemberRemarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $members = [
        ['M001', 'Anil Kumar', '9876543210', 'Green Valley, Plot 12', 'Male', '1995-04-12', 'O+ve'],
        ['M002', 'Lakshmi Nair', '9876543211', 'Rose Cottage', 'Female', '1998-08-23', 'A+ve'],
        ['M003', 'Rahul Menon', '9876543212', 'Lotus Villa', 'Male', '2001-01-15', 'B+ve'],
        ['M004', 'Sujatha Pillai', '9876543213', 'Sun N Shade', 'Female', '1992-11-05', 'AB+ve'],
    ];

    foreach ($members as $m) {
        $stmtMem->execute($m);
    }

    // Insert sample books (including Malayalam books transliterated into Manglish as well as titles)
    $stmtBook = $pdo->prepare("INSERT IGNORE INTO ExcelBookMaster (BookNumber, BookName, BookRemarks, AutherName, BookClass, BookCode, CallNumber, BookPrice, Language) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $books = [
        ['B101', 'Aadujeevitham (Goat Days)', 'Shelf A-1', 'Benyamin', 'Novel', 'BC101', '891.43 BEN', 350.00, 'Malayalam'],
        ['B102', 'Chemmeen', 'Shelf A-2', 'Thakazhi Sivasankara Pillai', 'Novel', 'BC102', '891.43 THA', 280.00, 'Malayalam'],
        ['B103', 'Randamoozham', 'Shelf A-3', 'M. T. Vasudevan Nair', 'Epic Novel', 'BC103', '891.43 MTV', 450.00, 'Malayalam'],
        ['B104', 'Khasakkinte Itihasam', 'Shelf B-1', 'O. V. Vijayan', 'Fiction', 'BC104', '891.43 OVV', 320.00, 'Malayalam'],
        ['B105', 'Balyakalasakhi', 'Shelf B-2', 'Vaikom Muhammad Basheer', 'Romance / Drama', 'BC105', '891.43 VMB', 200.00, 'Malayalam'],
        ['B106', 'Aarachar', 'Shelf B-3', 'K. R. Meera', 'Historical Fiction', 'BC106', '891.43 KRM', 500.00, 'Malayalam'],
        ['B107', 'The God of Small Things', 'Shelf C-1', 'Arundhati Roy', 'Novel', 'BC107', '823 ROY', 400.00, 'English'],
        ['B108', 'Pathummayude Aadu', 'Shelf C-2', 'Vaikom Muhammad Basheer', 'Humor', 'BC108', '891.43 VMB', 180.00, 'Malayalam'],
    ];

    foreach ($books as $b) {
        $stmtBook->execute($b);
    }

    // Insert sample issued transaction
    $stmtCheck = $pdo->query("SELECT COUNT(*) FROM TransBookIssue");
    if ($stmtCheck->fetchColumn() == 0) {
        $stmtIssue = $pdo->prepare("INSERT INTO TransBookIssue (IssueDate, DueDate, BookNumber, MemberNumber, MemberName, Status) VALUES (?, ?, ?, ?, ?, ?)");
        // B101 issued to M001
        $stmtIssue->execute(['2025-02-10', '2025-02-24', 'B101', 'M001', 'Anil Kumar', 'Issued']);
    }

    echo "Database seeded successfully.\n";
} catch (Exception $e) {
    echo "Error seeding database: " . $e->getMessage() . "\n";
}
