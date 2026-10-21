<?php
// books.php - Book Inventory Management & Multi-Parameter Search Engine
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/search_helper.php';

$message = '';
$messageType = 'success';

// Handle Add / Edit Book POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_book'])) {
    $bookNumber = trim($_POST['BookNumber'] ?? '');
    $bookName   = trim($_POST['BookName'] ?? '');
    $authorName = trim($_POST['AutherName'] ?? '');
    $bookClass  = trim($_POST['BookClass'] ?? '');
    $bookCode   = trim($_POST['BookCode'] ?? '');
    $callNumber = trim($_POST['CallNumber'] ?? '');
    $bookPrice  = floatval($_POST['BookPrice'] ?? 0);
    $remarks    = trim($_POST['BookRemarks'] ?? '');
    $language   = trim($_POST['Language'] ?? 'Malayalam');
    $isEdit     = !empty($_POST['is_edit']);

    if (empty($bookNumber) || empty($bookName) || empty($authorName)) {
        $message = 'Book Number, Title, and Author Name are required fields.';
        $messageType = 'danger';
    } else {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("UPDATE ExcelBookMaster
                    SET BookName = ?, AutherName = ?, BookClass = ?, BookCode = ?, CallNumber = ?, BookPrice = ?, BookRemarks = ?, Language = ?
                    WHERE BookNumber = ?");
                $stmt->execute([$bookName, $authorName, $bookClass, $bookCode, $callNumber, $bookPrice, $remarks, $language, $bookNumber]);
                $message = "Book '$bookNumber' updated successfully.";
            } else {
                // Check duplicate
                $chk = $pdo->prepare("SELECT COUNT(*) FROM ExcelBookMaster WHERE BookNumber = ?");
                $chk->execute([$bookNumber]);
                if ($chk->fetchColumn() > 0) {
                    $message = "Book Number '$bookNumber' already exists!";
                    $messageType = 'danger';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO ExcelBookMaster (BookNumber, BookName, AutherName, BookClass, BookCode, CallNumber, BookPrice, BookRemarks, Language)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$bookNumber, $bookName, $authorName, $bookClass, $bookCode, $callNumber, $bookPrice, $remarks, $language]);
                    $message = "Book '$bookName' added successfully.";
                }
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Handle Delete Book Request
if (isset($_GET['delete'])) {
    $delNum = trim($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM ExcelBookMaster WHERE BookNumber = ?");
        $stmt->execute([$delNum]);
        $message = "Book '$delNum' deleted successfully.";
    } catch (PDOException $e) {
        $message = "Cannot delete book: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Search & Filter Parameters
$titleQuery      = trim($_GET['title_query'] ?? '');
$titleMatchMode  = $_GET['title_match_mode'] ?? 'contains'; // starts_with, contains, phonetic
$authorQuery     = trim($_GET['author_query'] ?? '');
$authorMatchMode = $_GET['author_match_mode'] ?? 'contains'; // initial, full, phonetic
$bookNumberQuery = trim($_GET['book_number'] ?? '');
$barcodeQuery    = trim($_GET['barcode'] ?? '');
$callNumQuery    = trim($_GET['call_number'] ?? '');
$categoryQuery   = trim($_GET['category'] ?? '');
$languageQuery   = trim($_GET['language'] ?? '');
$availability    = $_GET['availability'] ?? 'all'; // all, available, issued

// Fetch distinct categories & languages for filter dropdowns
$categories = $pdo->query("SELECT DISTINCT BookClass FROM ExcelBookMaster WHERE BookClass IS NOT NULL AND BookClass != '' ORDER BY BookClass")->fetchAll(PDO::FETCH_COLUMN);
$languages  = $pdo->query("SELECT DISTINCT Language FROM ExcelBookMaster WHERE Language IS NOT NULL AND Language != '' ORDER BY Language")->fetchAll(PDO::FETCH_COLUMN);

// Build SQL Query dynamically
$sql = "SELECT b.*,
        (SELECT COUNT(*) FROM TransBookIssue t WHERE t.BookNumber = b.BookNumber AND t.Status = 'Issued') AS IsIssued,
        (SELECT MemberName FROM TransBookIssue t WHERE t.BookNumber = b.BookNumber AND t.Status = 'Issued' ORDER BY SlNo DESC LIMIT 1) AS IssuedTo
        FROM ExcelBookMaster b WHERE 1=1";

$params = [];

// Availability Filter
if ($availability === 'available') {
    $sql .= " AND b.BookNumber NOT IN (SELECT BookNumber FROM TransBookIssue WHERE Status = 'Issued')";
} elseif ($availability === 'issued') {
    $sql .= " AND b.BookNumber IN (SELECT BookNumber FROM TransBookIssue WHERE Status = 'Issued')";
}

// Identifiers & Category Filters
if (!empty($bookNumberQuery)) {
    $sql .= " AND b.BookNumber LIKE ?";
    $params[] = "%$bookNumberQuery%";
}
if (!empty($barcodeQuery)) {
    $sql .= " AND b.BookCode LIKE ?";
    $params[] = "%$barcodeQuery%";
}
if (!empty($callNumQuery)) {
    $sql .= " AND b.CallNumber LIKE ?";
    $params[] = "%$callNumQuery%";
}
if (!empty($categoryQuery)) {
    $sql .= " AND b.BookClass = ?";
    $params[] = $categoryQuery;
}
if (!empty($languageQuery)) {
    $sql .= " AND b.Language = ?";
    $params[] = $languageQuery;
}

// Title SQL condition for non-phonetic
if (!empty($titleQuery) && $titleMatchMode !== 'phonetic') {
    if ($titleMatchMode === 'starts_with') {
        $sql .= " AND b.BookName LIKE ?";
        $params[] = "$titleQuery%";
    } else { // contains
        $sql .= " AND b.BookName LIKE ?";
        $params[] = "%$titleQuery%";
    }
}

// Author SQL condition for non-phonetic
if (!empty($authorQuery) && $authorMatchMode !== 'phonetic') {
    if ($authorMatchMode === 'initial') {
        $sql .= " AND b.AutherName LIKE ?";
        $params[] = "$authorQuery%";
    } else { // full / contains
        $sql .= " AND b.AutherName LIKE ?";
        $params[] = "%$authorQuery%";
    }
}

$sql .= " ORDER BY b.BookNumber ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rawBooks = $stmt->fetchAll();

// Post-filter for Manglish & Phonetic Matching if enabled or if title/author phonetic is chosen
$filteredBooks = [];
foreach ($rawBooks as $b) {
    $titleMatch = true;
    if (!empty($titleQuery)) {
        if ($titleMatchMode === 'phonetic') {
            $titleMatch = isPhoneticMatch($titleQuery, $b['BookName']);
        }
    }

    $authorMatch = true;
    if (!empty($authorQuery)) {
        if ($authorMatchMode === 'phonetic') {
            $authorMatch = isPhoneticMatch($authorQuery, $b['AutherName']);
        }
    }

    if ($titleMatch && $authorMatch) {
        $filteredBooks[] = $b;
    }
}

// Fetch edit book data if requested
$editBook = null;
if (isset($_GET['edit'])) {
    $eNum = trim($_GET['edit']);
    $eStmt = $pdo->prepare("SELECT * FROM ExcelBookMaster WHERE BookNumber = ?");
    $eStmt->execute([$eNum]);
    $editBook = $eStmt->fetch();
}

require_once __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fa-solid fa-book me-2 text-primary"></i>Book Inventory & Search</h2>
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#addBookForm">
        <i class="fa-solid fa-plus me-1"></i> <?= $editBook ? 'Edit Book Record' : 'Add New Book' ?>
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Add / Edit Book Modal / Collapsible Form -->
<div class="collapse <?= ($editBook || ($message && $messageType === 'danger')) ? 'show' : '' ?> mb-4" id="addBookForm">
    <div class="card card-body shadow-sm border-primary">
        <h4 class="card-title text-primary mb-3">
            <i class="fa-solid <?= $editBook ? 'fa-pen-to-square' : 'fa-plus-circle' ?> me-2"></i>
            <?= $editBook ? 'Edit Book (' . htmlspecialchars($editBook['BookNumber']) . ')' : 'Add New Book to Inventory' ?>
        </h4>
        <form action="books.php" method="POST">
            <input type="hidden" name="action_save_book" value="1">
            <?php if ($editBook): ?>
                <input type="hidden" name="is_edit" value="1">
                <input type="hidden" name="BookNumber" value="<?= htmlspecialchars($editBook['BookNumber']) ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Book Number (ID) *</label>
                    <input type="text" name="BookNumber" class="form-control" value="<?= htmlspecialchars($editBook['BookNumber'] ?? '') ?>" <?= $editBook ? 'disabled' : 'required' ?>>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Book Title (BookName) *</label>
                    <input type="text" name="BookName" class="form-control" value="<?= htmlspecialchars($editBook['BookName'] ?? '') ?>" required placeholder="e.g. Aadujeevitham">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Author Name (AutherName) *</label>
                    <input type="text" name="AutherName" class="form-control" value="<?= htmlspecialchars($editBook['AutherName'] ?? '') ?>" required placeholder="e.g. Benyamin">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Category (BookClass)</label>
                    <input type="text" name="BookClass" class="form-control" value="<?= htmlspecialchars($editBook['BookClass'] ?? '') ?>" placeholder="e.g. Novel">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Barcode (BookCode)</label>
                    <input type="text" name="BookCode" class="form-control" value="<?= htmlspecialchars($editBook['BookCode'] ?? '') ?>" placeholder="e.g. BC101">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Call Number</label>
                    <input type="text" name="CallNumber" class="form-control" value="<?= htmlspecialchars($editBook['CallNumber'] ?? '') ?>" placeholder="e.g. 891.43 BEN">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Price (BookPrice)</label>
                    <input type="number" step="0.01" name="BookPrice" class="form-control" value="<?= htmlspecialchars($editBook['BookPrice'] ?? '0.00') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Language</label>
                    <select name="Language" class="form-select">
                        <option value="Malayalam" <?= ($editBook['Language'] ?? '') === 'Malayalam' ? 'selected' : '' ?>>Malayalam</option>
                        <option value="English" <?= ($editBook['Language'] ?? '') === 'English' ? 'selected' : '' ?>>English</option>
                        <option value="Hindi" <?= ($editBook['Language'] ?? '') === 'Hindi' ? 'selected' : '' ?>>Hindi</option>
                        <option value="Other" <?= ($editBook['Language'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-9">
                    <label class="form-label fw-semibold">Shelf Location / Remarks (BookRemarks)</label>
                    <input type="text" name="BookRemarks" class="form-control" value="<?= htmlspecialchars($editBook['BookRemarks'] ?? '') ?>" placeholder="e.g. Shelf A-1">
                </div>
            </div>

            <div class="mt-3 text-end">
                <?php if ($editBook): ?>
                    <a href="books.php" class="btn btn-secondary me-2">Cancel Edit</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-success px-4">
                    <i class="fa-solid fa-save me-1"></i> Save Book
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Search & Multi-Parameter Filter Engine -->
<div class="card mb-4 search-box">
    <h4 class="card-title text-dark mb-3">
        <i class="fa-solid fa-sliders me-2 text-primary"></i>Advanced Multi-Parameter Search
    </h4>
    <form action="books.php" method="GET">
        <div class="row g-3">
            <!-- Title Search & Matching Strategy -->
            <div class="col-md-6">
                <label class="form-label fw-bold">Title Search (Manglish / English / Phonetic)</label>
                <div class="input-group">
                    <input type="text" name="title_query" class="form-control" placeholder="Search by title (e.g., Aadujeevitham, aadu)..." value="<?= htmlspecialchars($titleQuery) ?>">
                    <select name="title_match_mode" class="form-select" style="max-width: 170px;">
                        <option value="contains" <?= $titleMatchMode === 'contains' ? 'selected' : '' ?>>Contains Word</option>
                        <option value="starts_with" <?= $titleMatchMode === 'starts_with' ? 'selected' : '' ?>>Starts With</option>
                        <option value="phonetic" <?= $titleMatchMode === 'phonetic' ? 'selected' : '' ?>>Phonetic / Manglish</option>
                    </select>
                </div>
            </div>

            <!-- Author Search & Matching Strategy -->
            <div class="col-md-6">
                <label class="form-label fw-bold">Author Search</label>
                <div class="input-group">
                    <input type="text" name="author_query" class="form-control" placeholder="Search author (e.g., Basheer, Benyamin)..." value="<?= htmlspecialchars($authorQuery) ?>">
                    <select name="author_match_mode" class="form-select" style="max-width: 170px;">
                        <option value="contains" <?= $authorMatchMode === 'contains' ? 'selected' : '' ?>>Full Name / Word</option>
                        <option value="initial" <?= $authorMatchMode === 'initial' ? 'selected' : '' ?>>Initial Characters</option>
                        <option value="phonetic" <?= $authorMatchMode === 'phonetic' ? 'selected' : '' ?>>Phonetic / Variations</option>
                    </select>
                </div>
            </div>

            <!-- Identifiers -->
            <div class="col-md-3">
                <label class="form-label fw-semibold">Book Number ID</label>
                <input type="text" name="book_number" class="form-control" placeholder="e.g. B101" value="<?= htmlspecialchars($bookNumberQuery) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Barcode / Book Code</label>
                <input type="text" name="barcode" class="form-control" placeholder="e.g. BC101" value="<?= htmlspecialchars($barcodeQuery) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Call Number</label>
                <input type="text" name="call_number" class="form-control" placeholder="e.g. 891.43" value="<?= htmlspecialchars($callNumQuery) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Category / Book Class</label>
                <select name="category" class="form-select">
                    <option value="">-- All Categories --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $categoryQuery === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">Language</label>
                <select name="language" class="form-select">
                    <option value="">-- All Languages --</option>
                    <?php foreach ($languages as $lang): ?>
                        <option value="<?= htmlspecialchars($lang) ?>" <?= $languageQuery === $lang ? 'selected' : '' ?>><?= htmlspecialchars($lang) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Availability Filter -->
            <div class="col-md-3">
                <label class="form-label fw-bold text-primary">Availability Status</label>
                <select name="availability" class="form-select border-primary fw-semibold">
                    <option value="all" <?= $availability === 'all' ? 'selected' : '' ?>>All Books</option>
                    <option value="available" <?= $availability === 'available' ? 'selected' : '' ?>>Available Books Only</option>
                    <option value="issued" <?= $availability === 'issued' ? 'selected' : '' ?>>Already Issued Books Only</option>
                </select>
            </div>

            <div class="col-md-6 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Search Inventory
                </button>
                <a href="books.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-rotate me-1"></i> Reset Filters
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Books Inventory Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-dark fw-bold">
            <i class="fa-solid fa-list me-2 text-primary"></i>
            Matching Books (<span id="bookCount"><?= count($filteredBooks) ?></span> results found)
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Book No</th>
                        <th>Title (BookName)</th>
                        <th>Author (AutherName)</th>
                        <th>Category</th>
                        <th>Barcode</th>
                        <th>Call No</th>
                        <th>Price</th>
                        <th>Shelf / Location</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredBooks)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-folder-open fa-2x mb-2 d-block"></i>
                                No books found matching your search criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredBooks as $b): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($b['BookNumber']) ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($b['BookName']) ?></td>
                                <td><?= htmlspecialchars($b['AutherName']) ?></td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($b['BookClass'] ?? 'N/A') ?></span></td>
                                <td><code><?= htmlspecialchars($b['BookCode'] ?? '-') ?></code></td>
                                <td><small class="text-muted"><?= htmlspecialchars($b['CallNumber'] ?? '-') ?></small></td>
                                <td>₹<?= number_format($b['BookPrice'], 2) ?></td>
                                <td><small><?= htmlspecialchars($b['BookRemarks'] ?? '-') ?></small></td>
                                <td>
                                    <?php if ($b['IsIssued'] > 0): ?>
                                        <span class="badge bg-warning text-dark badge-status">
                                            <i class="fa-solid fa-clock me-1"></i> Issued
                                        </span>
                                        <div class="small text-muted" style="font-size: 0.75rem;">
                                            To: <?= htmlspecialchars($b['IssuedTo'] ?? 'Member') ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-success badge-status">
                                            <i class="fa-solid fa-check-circle me-1"></i> Available
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($b['IsIssued'] == 0): ?>
                                        <a href="issue.php?book_number=<?= urlencode($b['BookNumber']) ?>" class="btn btn-sm btn-outline-success me-1" title="Issue Book">
                                            <i class="fa-solid fa-hand-holding"></i> Issue
                                        </a>
                                    <?php endif; ?>
                                    <a href="books.php?edit=<?= urlencode($b['BookNumber']) ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <a href="books.php?delete=<?= urlencode($b['BookNumber']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this book?');" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
