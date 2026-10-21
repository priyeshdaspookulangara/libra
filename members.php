<?php
// members.php - Member Registry Management
require_once __DIR__ . '/db.php';

$message = '';
$messageType = 'success';

// Handle Add / Edit Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_member'])) {
    $memNumber  = trim($_POST['MemberNumber'] ?? '');
    $memName    = trim($_POST['MemberName'] ?? '');
    $phone      = trim($_POST['PhoneHome'] ?? '');
    $house      = trim($_POST['HouseName'] ?? '');
    $sex        = trim($_POST['StudentSex'] ?? 'Male');
    $dob        = $_POST['MemBirthDate'] ?: null;
    $remarks    = trim($_POST['MemberRemarks'] ?? '');
    $isEdit     = !empty($_POST['is_edit']);

    if (empty($memNumber) || empty($memName)) {
        $message = 'Member Number and Member Name are required.';
        $messageType = 'danger';
    } else {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("UPDATE MstrMember SET MemberName = ?, PhoneHome = ?, HouseName = ?, StudentSex = ?, MemBirthDate = ?, MemberRemarks = ? WHERE MemberNumber = ?");
                $stmt->execute([$memName, $phone, $house, $sex, $dob, $remarks, $memNumber]);
                $message = "Member '$memNumber' updated successfully.";
            } else {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM MstrMember WHERE MemberNumber = ?");
                $chk->execute([$memNumber]);
                if ($chk->fetchColumn() > 0) {
                    $message = "Member Number '$memNumber' already exists!";
                    $messageType = 'danger';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO MstrMember (MemberNumber, MemberName, PhoneHome, HouseName, StudentSex, MemBirthDate, MemberRemarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$memNumber, $memName, $phone, $house, $sex, $dob, $remarks]);
                    $message = "Member '$memName' registered successfully.";
                }
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Handle Delete Member
if (isset($_GET['delete'])) {
    $delId = trim($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM MstrMember WHERE MemberNumber = ?");
        $stmt->execute([$delId]);
        $message = "Member '$delId' removed successfully.";
    } catch (PDOException $e) {
        $message = "Cannot delete member: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Fetch edit member
$editMember = null;
if (isset($_GET['edit'])) {
    $eId = trim($_GET['edit']);
    $eStmt = $pdo->prepare("SELECT * FROM MstrMember WHERE MemberNumber = ?");
    $eStmt->execute([$eId]);
    $editMember = $eStmt->fetch();
}

// Search members
$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM MstrMember WHERE MemberNumber LIKE ? OR MemberName LIKE ? OR PhoneHome LIKE ? ORDER BY MemberName ASC");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM MstrMember ORDER BY MemberNumber ASC");
}
$members = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fa-solid fa-users me-2 text-primary"></i>Member Registry (`MstrMember`)</h2>
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#addMemberForm">
        <i class="fa-solid fa-user-plus me-1"></i> <?= $editMember ? 'Edit Member Record' : 'Register New Member' ?>
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Form -->
<div class="collapse <?= ($editMember || ($message && $messageType === 'danger')) ? 'show' : '' ?> mb-4" id="addMemberForm">
    <div class="card card-body shadow-sm border-primary">
        <h4 class="card-title text-primary mb-3">
            <i class="fa-solid <?= $editMember ? 'fa-user-pen' : 'fa-user-plus' ?> me-2"></i>
            <?= $editMember ? 'Edit Member (' . htmlspecialchars($editMember['MemberNumber']) . ')' : 'Register New Member' ?>
        </h4>
        <form action="members.php" method="POST">
            <input type="hidden" name="action_save_member" value="1">
            <?php if ($editMember): ?>
                <input type="hidden" name="is_edit" value="1">
                <input type="hidden" name="MemberNumber" value="<?= htmlspecialchars($editMember['MemberNumber']) ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Member Number (ID) *</label>
                    <input type="text" name="MemberNumber" class="form-control" value="<?= htmlspecialchars($editMember['MemberNumber'] ?? '') ?>" <?= $editMember ? 'disabled' : 'required' ?> placeholder="e.g. M005">
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Full Name (MemberName) *</label>
                    <input type="text" name="MemberName" class="form-control" value="<?= htmlspecialchars($editMember['MemberName'] ?? '') ?>" required placeholder="e.g. Rahul Sharma">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Contact Phone (PhoneHome)</label>
                    <input type="text" name="PhoneHome" class="form-control" value="<?= htmlspecialchars($editMember['PhoneHome'] ?? '') ?>" placeholder="e.g. 9876543210">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Address (HouseName)</label>
                    <input type="text" name="HouseName" class="form-control" value="<?= htmlspecialchars($editMember['HouseName'] ?? '') ?>" placeholder="e.g. Rose Villa, Apt 4B">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Gender (StudentSex)</label>
                    <select name="StudentSex" class="form-select">
                        <option value="Male" <?= ($editMember['StudentSex'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($editMember['StudentSex'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($editMember['StudentSex'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Birth / Join Date</label>
                    <input type="date" name="MemBirthDate" class="form-control" value="<?= htmlspecialchars($editMember['MemBirthDate'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Remarks / Blood Grp</label>
                    <input type="text" name="MemberRemarks" class="form-control" value="<?= htmlspecialchars($editMember['MemberRemarks'] ?? '') ?>" placeholder="e.g. O+ve">
                </div>
            </div>

            <div class="mt-3 text-end">
                <?php if ($editMember): ?>
                    <a href="members.php" class="btn btn-secondary me-2">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-success px-4">
                    <i class="fa-solid fa-save me-1"></i> Save Member Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Search & Members List -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <form action="members.php" method="GET" class="d-flex gap-2 w-50">
            <input type="text" name="search" class="form-control" placeholder="Search member by Name, ID, or Phone..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-outline-primary"><i class="fa-solid fa-search"></i></button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Member ID</th>
                        <th>Member Name</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Gender</th>
                        <th>Birth / Join Date</th>
                        <th>Blood Grp / Remarks</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No members found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <td><span class="badge bg-primary"><?= htmlspecialchars($m['MemberNumber']) ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($m['MemberName']) ?></td>
                                <td><?= htmlspecialchars($m['PhoneHome'] ?? '-') ?></td>
                                <td><small><?= htmlspecialchars($m['HouseName'] ?? '-') ?></small></td>
                                <td><?= htmlspecialchars($m['StudentSex'] ?? '-') ?></td>
                                <td><small><?= htmlspecialchars($m['MemBirthDate'] ?? '-') ?></small></td>
                                <td><small class="badge bg-light text-dark border"><?= htmlspecialchars($m['MemberRemarks'] ?? '-') ?></small></td>
                                <td class="text-end">
                                    <a href="members.php?edit=<?= urlencode($m['MemberNumber']) ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <a href="members.php?delete=<?= urlencode($m['MemberNumber']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this member?');" title="Delete">
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
