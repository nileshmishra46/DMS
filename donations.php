<?php
// donations.php
// Manage Trust Donations

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';

$db = getDb();
$error = '';
$success = '';

// Handle Create Donation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $donor_id = trim($_POST['donor_id'] ?? '');
    $donor_name = trim($_POST['donor_name'] ?? '');
    $donor_phone = trim($_POST['donor_phone'] ?? '');
    $donor_pan = trim($_POST['donor_pan'] ?? '');
    $amount = (double)($_POST['amount'] ?? 0);
    $donation_date = trim($_POST['donation_date'] ?? '');
    $payment_mode = trim($_POST['payment_mode'] ?? 'Cash');
    $purpose = trim($_POST['purpose'] ?? 'General');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($donor_name) || $amount <= 0 || empty($donation_date)) {
        $error = 'Please fill all required fields correctly.';
    } else {
        try {
            $db->beginTransaction();
            
            // Autoregister donor if not selected/registered
            if (empty($donor_id) && !empty($donor_name)) {
                $stmt_d_check = $db->prepare("SELECT id FROM donors WHERE name = :name LIMIT 1");
                $stmt_d_check->execute([':name' => $donor_name]);
                $existing_d_id = $stmt_d_check->fetchColumn();
                if ($existing_d_id) {
                    $donor_id = $existing_d_id;
                    $stmt_up_d = $db->prepare("UPDATE donors SET phone = COALESCE(:phone, phone), pan = COALESCE(:pan, pan) WHERE id = :id");
                    $stmt_up_d->execute([
                        ':phone' => !empty($donor_phone) ? $donor_phone : null,
                        ':pan' => !empty($donor_pan) ? strtoupper($donor_pan) : null,
                        ':id' => $donor_id
                    ]);
                } else {
                    $donor_id = generateUuid();
                    $stmt_ins_d = $db->prepare("INSERT INTO donors (id, name, phone, pan) VALUES (:id, :name, :phone, :pan)");
                    $stmt_ins_d->execute([
                        ':id' => $donor_id,
                        ':name' => $donor_name,
                        ':phone' => !empty($donor_phone) ? $donor_phone : null,
                        ':pan' => !empty($donor_pan) ? strtoupper($donor_pan) : null
                    ]);
                }
            } else {
                // Keep selected donor phone/PAN updated in registry
                $stmt_up_d = $db->prepare("UPDATE donors SET phone = :phone, pan = :pan WHERE id = :id");
                $stmt_up_d->execute([
                    ':phone' => !empty($donor_phone) ? $donor_phone : null,
                    ':pan' => !empty($donor_pan) ? strtoupper($donor_pan) : null,
                    ':id' => $donor_id
                ]);
            }
            
            // Calculate Financial Year
            $fy = getFinancialYear($donation_date);
            $fy_start_year = explode('-', $fy)[0]; // e.g. 2026
            
            // Auto-generate receipt number (resets per FY)
            $prefix = "RCT-" . $fy_start_year . "-";
            $stmt_seq = $db->prepare("
                SELECT receipt_no FROM donations 
                WHERE receipt_no LIKE :prefix 
                ORDER BY receipt_no DESC LIMIT 1
            ");
            $stmt_seq->execute([':prefix' => $prefix . '%']);
            $last_receipt = $stmt_seq->fetchColumn();
            
            if ($last_receipt) {
                $parts = explode('-', $last_receipt);
                $seq = (int)end($parts);
                $next_seq = $seq + 1;
            } else {
                $next_seq = 1;
            }
            $receipt_no = $prefix . str_pad($next_seq, 4, '0', STR_PAD_LEFT);
            
            // Insert donation
            $stmt_insert = $db->prepare("
                INSERT INTO donations (id, receipt_no, donor_id, donor_name, donor_phone, donor_pan, donation_date, amount, payment_mode, purpose, financial_year, notes)
                VALUES (:id, :receipt_no, :donor_id, :donor_name, :donor_phone, :donor_pan, :donation_date, :amount, :payment_mode, :purpose, :financial_year, :notes)
            ");
            $stmt_insert->execute([
                ':id' => generateUuid(),
                ':receipt_no' => $receipt_no,
                ':donor_id' => $donor_id,
                ':donor_name' => $donor_name,
                ':donor_phone' => !empty($donor_phone) ? $donor_phone : null,
                ':donor_pan' => !empty($donor_pan) ? strtoupper($donor_pan) : null,
                ':donation_date' => $donation_date,
                ':amount' => $amount,
                ':payment_mode' => $payment_mode,
                ':purpose' => $purpose,
                ':financial_year' => $fy,
                ':notes' => !empty($notes) ? $notes : null
            ]);
            
            $db->commit();
            $success = 'Donation recorded successfully. Receipt No: ' . $receipt_no;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Failed to save donation: ' . $e->getMessage();
        }
    }
}

// Handle Update Donation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = trim($_POST['id'] ?? '');
    $donor_id = trim($_POST['donor_id'] ?? '');
    $donor_name = trim($_POST['donor_name'] ?? '');
    $donor_phone = trim($_POST['donor_phone'] ?? '');
    $donor_pan = trim($_POST['donor_pan'] ?? '');
    $amount = (double)($_POST['amount'] ?? 0);
    $donation_date = trim($_POST['donation_date'] ?? '');
    $payment_mode = trim($_POST['payment_mode'] ?? 'Cash');
    $purpose = trim($_POST['purpose'] ?? 'General');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($id) || empty($donor_name) || $amount <= 0 || empty($donation_date)) {
        $error = 'Please fill all required fields correctly.';
    } else {
        try {
            $db->beginTransaction();
            
            // Autoregister donor if not selected/registered
            if (empty($donor_id) && !empty($donor_name)) {
                $stmt_d_check = $db->prepare("SELECT id FROM donors WHERE name = :name LIMIT 1");
                $stmt_d_check->execute([':name' => $donor_name]);
                $existing_d_id = $stmt_d_check->fetchColumn();
                if ($existing_d_id) {
                    $donor_id = $existing_d_id;
                    $stmt_up_d = $db->prepare("UPDATE donors SET phone = COALESCE(:phone, phone), pan = COALESCE(:pan, pan) WHERE id = :id");
                    $stmt_up_d->execute([
                        ':phone' => !empty($donor_phone) ? $donor_phone : null,
                        ':pan' => !empty($donor_pan) ? strtoupper($donor_pan) : null,
                        ':id' => $donor_id
                    ]);
                } else {
                    $donor_id = generateUuid();
                    $stmt_ins_d = $db->prepare("INSERT INTO donors (id, name, phone, pan) VALUES (:id, :name, :phone, :pan)");
                    $stmt_ins_d->execute([
                        ':id' => $donor_id,
                        ':name' => $donor_name,
                        ':phone' => !empty($donor_phone) ? $donor_phone : null,
                        ':pan' => !empty($donor_pan) ? strtoupper($donor_pan) : null
                    ]);
                }
            } else {
                // Keep selected donor phone/PAN updated in registry
                $stmt_up_d = $db->prepare("UPDATE donors SET phone = :phone, pan = :pan WHERE id = :id");
                $stmt_up_d->execute([
                    ':phone' => !empty($donor_phone) ? $donor_phone : null,
                    ':pan' => !empty($donor_pan) ? strtoupper($donor_pan) : null,
                    ':id' => $donor_id
                ]);
            }
            
            // Check if donation exists
            $stmt_check = $db->prepare("SELECT receipt_no, donation_date FROM donations WHERE id = :id");
            $stmt_check->execute([':id' => $id]);
            $existing = $stmt_check->fetch();
            
            if (!$existing) {
                throw new Exception('Donation record not found.');
            }
            
            // Calculate Financial Year
            $new_fy = getFinancialYear($donation_date);
            $existing_fy = getFinancialYear($existing['donation_date']);
            
            $receipt_no = $existing['receipt_no'];
            // If financial year changed, we regenerate the receipt number to maintain FY sequence integrity!
            if ($new_fy !== $existing_fy) {
                $fy_start_year = explode('-', $new_fy)[0];
                $prefix = "RCT-" . $fy_start_year . "-";
                $stmt_seq = $db->prepare("
                    SELECT receipt_no FROM donations 
                    WHERE receipt_no LIKE :prefix 
                    ORDER BY receipt_no DESC LIMIT 1
                ");
                $stmt_seq->execute([':prefix' => $prefix . '%']);
                $last_receipt = $stmt_seq->fetchColumn();
                
                if ($last_receipt) {
                    $parts = explode('-', $last_receipt);
                    $seq = (int)end($parts);
                    $next_seq = $seq + 1;
                } else {
                    $next_seq = 1;
                }
                $receipt_no = $prefix . str_pad($next_seq, 4, '0', STR_PAD_LEFT);
            }
            
            $stmt_update = $db->prepare("
                UPDATE donations 
                SET receipt_no = :receipt_no,
                    donor_id = :donor_id,
                    donor_name = :donor_name,
                    donor_phone = :donor_phone,
                    donor_pan = :donor_pan,
                    donation_date = :donation_date,
                    amount = :amount,
                    payment_mode = :payment_mode,
                    purpose = :purpose,
                    financial_year = :financial_year,
                    notes = :notes
                WHERE id = :id
            ");
            $stmt_update->execute([
                ':receipt_no' => $receipt_no,
                ':donor_id' => $donor_id,
                ':donor_name' => $donor_name,
                ':donor_phone' => !empty($donor_phone) ? $donor_phone : null,
                ':donor_pan' => !empty($donor_pan) ? strtoupper($donor_pan) : null,
                ':donation_date' => $donation_date,
                ':amount' => $amount,
                ':payment_mode' => $payment_mode,
                ':purpose' => $purpose,
                ':financial_year' => $new_fy,
                ':notes' => !empty($notes) ? $notes : null,
                ':id' => $id
            ]);
            
            $db->commit();
            $success = 'Donation record updated successfully.';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Failed to update donation: ' . $e->getMessage();
        }
    }
}

// Handle Delete Donation
if (isset($_GET['delete_id'])) {
    try {
        $stmt_del = $db->prepare("DELETE FROM donations WHERE id = :id");
        $stmt_del->execute([':id' => $_GET['delete_id']]);
        header("Location: donations.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $error = 'Failed to delete donation record: ' . $e->getMessage();
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $success = 'Donation record deleted successfully.';
}

// Filter inputs
$search = trim($_GET['search'] ?? '');
$filter_mode = trim($_GET['payment_mode'] ?? '');
$filter_fy = trim($_GET['financial_year'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

// Build query
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "donor_name LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($filter_mode)) {
    $where_clauses[] = "payment_mode = :payment_mode";
    $params[':payment_mode'] = $filter_mode;
}

if (!empty($filter_fy)) {
    $where_clauses[] = "financial_year = :financial_year";
    $params[':financial_year'] = $filter_fy;
}

if (!empty($start_date)) {
    $where_clauses[] = "donation_date >= :start_date";
    $params[':start_date'] = $start_date;
}

if (!empty($end_date)) {
    $where_clauses[] = "donation_date <= :end_date";
    $params[':end_date'] = $end_date;
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Fetch financial years list for filter
$fys = $db->query("SELECT DISTINCT financial_year FROM donations ORDER BY financial_year DESC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch total records for pagination
$donations = $db->prepare("SELECT * FROM donations $where_sql ORDER BY donation_date DESC, created_at DESC");
$donations->execute($params);
$records = $donations->fetchAll();

// Fetch organization details for PDF receipts
$org = $db->query("SELECT community_name, registration_number, address, phone, email, logo_data FROM settings WHERE id = 'global_config'")->fetch();

// Fetch all registered donors in the system
$donors = $db->query("SELECT * FROM donors ORDER BY name ASC")->fetchAll();

render_header('Donations Registry', 'donations');
?>

<!-- jsPDF CDNs -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<!-- Header Action Row -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <p class="text-xs text-slate-400 dark:text-stone-400 font-medium">Record and track community trust donations, search details, and issue dynamic receipts.</p>
    </div>
    <button onclick="openModal('add-donation-modal')" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-sm px-4 py-2.5 rounded-xl font-bold transition-all shadow-md shadow-primary-500/15 flex items-center justify-center gap-1.5 shrink-0 transform hover:scale-[1.01]">
        <i data-lucide="plus" class="h-4 w-4"></i>
        New Donation Record
    </button>
</div>

<!-- Alert banners -->
<?php if (!empty($success)): ?>
    <div class="flex items-center gap-3 p-4 bg-emerald-50/80 dark:bg-emerald-950/20 border border-emerald-200/50 dark:border-emerald-800/40 text-emerald-800 dark:text-emerald-200 rounded-2xl text-sm transition-all duration-200 animate-fade-in-up">
        <i data-lucide="check-circle-2" class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
        <span class="flex-1 font-medium"><?php echo htmlspecialchars($success); ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="flex items-center gap-3 p-4 bg-rose-50/80 dark:bg-rose-950/20 border border-rose-200/50 dark:border-rose-800/40 text-rose-800 dark:text-rose-200 rounded-2xl text-sm transition-all duration-200 animate-fade-in-up">
        <i data-lucide="alert-triangle" class="h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400"></i>
        <span class="flex-1 font-medium"><?php echo htmlspecialchars($error); ?></span>
    </div>
<?php endif; ?>

<!-- Filtering Section -->
<div class="glass-card border border-slate-200/50 dark:border-stone-800/40 rounded-2xl p-5 shadow-sm">
    <form action="donations.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Search -->
        <div class="space-y-1.5">
            <label for="search" class="block text-[10px] font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Search Donor</label>
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 dark:text-stone-500"></i>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter name..." 
                       class="w-full glass-input rounded-xl pl-9 pr-3 py-2 text-sm focus:outline-none transition-all placeholder-slate-400 dark:placeholder-stone-500">
            </div>
        </div>

        <!-- Mode -->
        <div class="space-y-1.5">
            <label for="payment_mode" class="block text-[10px] font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Payment Mode</label>
            <select id="payment_mode" name="payment_mode" class="w-full glass-input rounded-xl px-3 py-2 text-sm focus:outline-none transition-all bg-white dark:bg-stone-900">
                <option value="">All Modes</option>
                <option value="Cash" <?php echo $filter_mode === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="Bank Transfer" <?php echo $filter_mode === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                <option value="UPI" <?php echo $filter_mode === 'UPI' ? 'selected' : ''; ?>>UPI</option>
                <option value="Cheque" <?php echo $filter_mode === 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                <option value="Other" <?php echo $filter_mode === 'Other' ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>

        <!-- Financial Year -->
        <div class="space-y-1.5">
            <label for="filter_fy" class="block text-[10px] font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Financial Year</label>
            <select id="filter_fy" name="financial_year" class="w-full glass-input rounded-xl px-3 py-2 text-sm focus:outline-none transition-all bg-white dark:bg-stone-900">
                <option value="">All Years</option>
                <?php foreach ($fys as $fy): ?>
                    <option value="<?php echo htmlspecialchars($fy); ?>" <?php echo $filter_fy === $fy ? 'selected' : ''; ?>>FY <?php echo htmlspecialchars($fy); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Date Range -->
        <div class="space-y-1.5">
            <label for="start_date" class="block text-[10px] font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">From Date</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" 
                   class="w-full glass-input rounded-xl px-3 py-2 text-sm focus:outline-none transition-all bg-white dark:bg-stone-900">
        </div>
        
        <div class="space-y-1.5">
            <label for="end_date" class="block text-[10px] font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">To Date</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" 
                   class="w-full glass-input rounded-xl px-3 py-2 text-sm focus:outline-none transition-all bg-white dark:bg-stone-900">
        </div>
        
        <!-- Filter Actions -->
        <div class="sm:col-span-2 lg:col-span-5 flex items-center justify-end gap-2 pt-2 border-t border-slate-100/50 dark:border-stone-800/40">
            <a href="donations.php" class="bg-slate-50 dark:bg-stone-900 border border-slate-200 dark:border-stone-800 hover:bg-slate-100 dark:hover:bg-stone-800 text-slate-600 dark:text-stone-300 text-xs px-4 py-2 rounded-xl font-bold transition-all">Reset</a>
            <button type="submit" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-xs px-5 py-2 rounded-xl font-bold transition-all flex items-center gap-1.5 shadow-sm shadow-primary-500/15 animate-fade-in-up">
                <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
                Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="glass-card border border-slate-200/50 dark:border-stone-800/40 rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/50 dark:bg-stone-900/30 border-b border-slate-150 dark:border-stone-800/40">
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Receipt No</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Donor Details</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Mode & Purpose</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-stone-800/40">
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 dark:text-stone-500">
                            <i data-lucide="info" class="h-10 w-10 mx-auto text-slate-300 dark:text-stone-700"></i>
                            <p class="font-medium mt-3 text-sm">No donation records found.</p>
                            <p class="text-xs mt-1 text-slate-400 dark:text-stone-500">Try adjusting your search query or filters.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $row): ?>
                        <tr class="hover:bg-primary-500/5 transition-colors">
                            <td class="px-6 py-4">
                                <span class="font-mono font-bold text-slate-900 dark:text-stone-200 bg-slate-100 dark:bg-stone-800 text-[11px] px-2 py-1 rounded-md border border-slate-200/50 dark:border-stone-700/30">
                                    <?php echo htmlspecialchars($row['receipt_no']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-slate-900 dark:text-stone-200"><?php echo date('d M Y', strtotime($row['donation_date'])); ?></div>
                                <div class="text-[10px] text-slate-400 dark:text-stone-500 font-bold uppercase mt-0.5">FY <?php echo htmlspecialchars($row['financial_year']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-slate-900 dark:text-stone-100"><?php echo htmlspecialchars($row['donor_name']); ?></div>
                                <div class="text-xs text-slate-400 dark:text-stone-500 flex flex-wrap gap-x-2 mt-0.5 font-medium">
                                    <?php if ($row['donor_phone']): ?>
                                        <span>Ph: <?php echo htmlspecialchars($row['donor_phone']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($row['donor_pan']): ?>
                                        <span class="text-primary-600 dark:text-primary-400">PAN: <?php echo htmlspecialchars($row['donor_pan']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-bold text-slate-950 dark:text-stone-100"><?php echo formatCurrency($row['amount']); ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-slate-900 dark:text-stone-200"><?php echo htmlspecialchars($row['purpose']); ?></div>
                                <span class="inline-flex items-center text-[10px] font-bold text-slate-500 dark:text-stone-400 bg-slate-100 dark:bg-stone-800 rounded-full px-2 py-0.5 mt-0.5">
                                    <?php echo htmlspecialchars($row['payment_mode']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-1 shrink-0 whitespace-nowrap">
                                <!-- Print PDF Receipt -->
                                <button onclick="generateReceiptPDF(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                                        class="p-2 text-slate-400 dark:text-stone-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-500/10 rounded-xl transition-all" title="Download Receipt PDF">
                                    <i data-lucide="file-down" class="h-4.5 w-4.5"></i>
                                </button>
                                
                                <!-- Edit -->
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                                        class="p-2 text-slate-400 dark:text-stone-500 hover:text-slate-700 dark:hover:text-stone-300 hover:bg-slate-100 dark:hover:bg-stone-800 rounded-xl transition-all" title="Edit Record">
                                    <i data-lucide="edit-3" class="h-4.5 w-4.5"></i>
                                </button>
                                
                                <!-- Delete -->
                                <a href="donations.php?delete_id=<?php echo urlencode($row['id']); ?>" 
                                   onclick="return confirm('Are you sure you want to delete receipt <?php echo htmlspecialchars($row['receipt_no']); ?>?')" 
                                   class="p-2 text-slate-400 dark:text-stone-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all inline-block" title="Delete Record">
                                    <i data-lucide="trash-2" class="h-4.5 w-4.5"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- Add Donation Modal -->
<div id="add-donation-modal" class="hidden fixed inset-0 z-50 bg-stone-900/40 dark:bg-stone-950/60 backdrop-blur-md flex items-center justify-center p-4">
    <div class="glass-card border border-slate-200/50 dark:border-stone-800/40 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="px-6 py-4 border-b border-slate-150 dark:border-stone-800/40 flex items-center justify-between">
            <h3 class="font-title font-bold text-slate-950 dark:text-stone-100 text-base">New Donation Record</h3>
            <button onclick="closeModal('add-donation-modal')" class="p-1.5 rounded-lg border border-slate-200 dark:border-stone-800 text-slate-400 dark:text-stone-500 hover:bg-slate-50 dark:hover:bg-stone-900 hover:text-slate-600">
                <i data-lucide="x" class="h-4.5 w-4.5"></i>
            </button>
        </div>
        
        <form action="donations.php" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="create">
            
            <div class="space-y-1.5">
                <label for="donor_select" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Select Registered Donor (or leave empty for new)</label>
                <select id="donor_select" class="w-full glass-input rounded-xl px-3 py-2.5 text-sm focus:outline-none transition-all bg-white dark:bg-stone-900">
                    <option value="">-- Add New Donor (type details below) --</option>
                    <?php foreach ($donors as $d): ?>
                        <option value="<?php echo htmlspecialchars($d['id']); ?>" 
                                data-name="<?php echo htmlspecialchars($d['name']); ?>" 
                                data-phone="<?php echo htmlspecialchars($d['phone'] ?? ''); ?>" 
                                data-pan="<?php echo htmlspecialchars($d['pan'] ?? ''); ?>">
                            <?php echo htmlspecialchars($d['name']); ?> <?php echo $d['phone'] ? '('.$d['phone'].')' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="donor_id" id="donor_id_input" value="">
            </div>
            
            <div class="space-y-1.5">
                <label for="donor_name" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Donor Name <span class="text-rose-500">*</span></label>
                <input type="text" id="donor_name" name="donor_name" required placeholder="Enter complete name" 
                       class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="donor_phone" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Phone Number</label>
                    <input type="tel" id="donor_phone" name="donor_phone" placeholder="e.g. 9876543210" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                </div>
                
                <div class="space-y-1.5">
                    <label for="donor_pan" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">PAN Card No</label>
                    <input type="text" id="donor_pan" name="donor_pan" placeholder="10 Digit Character code" maxlength="10" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all uppercase">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="amount" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Amount (INR) <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" required placeholder="0.00" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all font-bold">
                </div>
                
                <div class="space-y-1.5">
                    <label for="donation_date" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Donation Date <span class="text-rose-500">*</span></label>
                    <input type="date" id="donation_date" name="donation_date" required value="<?php echo date('Y-m-d'); ?>" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="payment_mode_add" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Payment Mode</label>
                    <select id="payment_mode_add" name="payment_mode" class="w-full glass-input rounded-xl px-3 py-2.5 text-sm focus:outline-none transition-all bg-white dark:bg-stone-900">
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label for="purpose_add" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Purpose</label>
                    <input type="text" id="purpose_add" name="purpose" placeholder="e.g. General, Building, Festival" value="General" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="notes" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Notes</label>
                <textarea id="notes" name="notes" rows="2" placeholder="Additional details..." 
                          class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100/50 dark:border-stone-800/40">
                <button type="button" onclick="closeModal('add-donation-modal')" class="bg-slate-50 dark:bg-stone-900 border border-slate-200 dark:border-stone-800 text-slate-600 dark:text-stone-300 text-xs px-4.5 py-2.5 rounded-xl font-bold transition-all">Cancel</button>
                <button type="submit" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-xs px-5 py-2.5 rounded-xl font-bold transition-all shadow-md shadow-primary-500/15 flex items-center gap-1.5 transform hover:scale-[1.01]">
                    <i data-lucide="check-circle" class="h-4 w-4"></i>
                    Save Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Donation Modal -->
<div id="edit-donation-modal" class="hidden fixed inset-0 z-50 bg-stone-900/40 dark:bg-stone-950/60 backdrop-blur-md flex items-center justify-center p-4">
    <div class="glass-card border border-slate-200/50 dark:border-stone-800/40 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="px-6 py-4 border-b border-slate-150 dark:border-stone-800/40 flex items-center justify-between">
            <h3 class="font-title font-bold text-slate-950 dark:text-stone-100 text-base">Edit Donation Record</h3>
            <button onclick="closeModal('edit-donation-modal')" class="p-1.5 rounded-lg border border-slate-200 dark:border-stone-800 text-slate-400 dark:text-stone-500 hover:bg-slate-50 dark:hover:bg-stone-900 hover:text-slate-600">
                <i data-lucide="x" class="h-4.5 w-4.5"></i>
            </button>
        </div>
        
        <form action="donations.php" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            
            <div class="space-y-1.5">
                <label for="edit-donor_select" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Change Registered Donor</label>
                <select id="edit-donor_select" class="w-full glass-input rounded-xl px-3 py-2.5 text-sm focus:outline-none transition-all bg-white dark:bg-stone-900">
                    <option value="">-- Custom Donor Details --</option>
                    <?php foreach ($donors as $d): ?>
                        <option value="<?php echo htmlspecialchars($d['id']); ?>" 
                                data-name="<?php echo htmlspecialchars($d['name']); ?>" 
                                data-phone="<?php echo htmlspecialchars($d['phone'] ?? ''); ?>" 
                                data-pan="<?php echo htmlspecialchars($d['pan'] ?? ''); ?>">
                            <?php echo htmlspecialchars($d['name']); ?> <?php echo $d['phone'] ? '('.$d['phone'].')' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="donor_id" id="edit-donor_id_input" value="">
            </div>
            
            <div class="space-y-1.5">
                <label for="edit-donor_name" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Donor Name <span class="text-rose-500">*</span></label>
                <input type="text" id="edit-donor_name" name="donor_name" required 
                       class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="edit-donor_phone" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Phone Number</label>
                    <input type="tel" id="edit-donor_phone" name="donor_phone" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                </div>
                
                <div class="space-y-1.5">
                    <label for="edit-donor_pan" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">PAN Card No</label>
                    <input type="text" id="edit-donor_pan" name="donor_pan" maxlength="10" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all uppercase">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="edit-amount" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Amount (INR) <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" min="0.01" id="edit-amount" name="amount" required 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all font-bold">
                </div>
                
                <div class="space-y-1.5">
                    <label for="edit-donation_date" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Donation Date <span class="text-rose-500">*</span></label>
                    <input type="date" id="edit-donation_date" name="donation_date" required 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="edit-payment_mode" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Payment Mode</label>
                    <select id="edit-payment_mode" name="payment_mode" class="w-full glass-input rounded-xl px-3 py-2.5 text-sm focus:outline-none transition-all bg-white dark:bg-stone-900">
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label for="edit-purpose" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Purpose</label>
                    <input type="text" id="edit-purpose" name="purpose" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="edit-notes" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Notes</label>
                <textarea id="edit-notes" name="notes" rows="2" 
                          class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100/50 dark:border-stone-800/40">
                <button type="button" onclick="closeModal('edit-donation-modal')" class="bg-slate-50 dark:bg-stone-900 border border-slate-200 dark:border-stone-800 text-slate-600 dark:text-stone-300 text-xs px-4.5 py-2.5 rounded-xl font-bold transition-all">Cancel</button>
                <button type="submit" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-xs px-5 py-2.5 rounded-xl font-bold transition-all shadow-md shadow-primary-500/15 flex items-center gap-1.5 transform hover:scale-[1.01]">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    Update Record
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Auto-populate donor details from registry dropdown
    document.addEventListener("DOMContentLoaded", function() {
        const donorSelect = document.getElementById('donor_select');
        const donorIdInput = document.getElementById('donor_id_input');
        const donorNameInput = document.getElementById('donor_name');
        const donorPhoneInput = document.getElementById('donor_phone');
        const donorPanInput = document.getElementById('donor_pan');
        
        if (donorSelect) {
            donorSelect.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                if (this.value) {
                    donorIdInput.value = this.value;
                    donorNameInput.value = opt.getAttribute('data-name');
                    donorPhoneInput.value = opt.getAttribute('data-phone');
                    donorPanInput.value = opt.getAttribute('data-pan');
                } else {
                    donorIdInput.value = "";
                    donorNameInput.value = "";
                    donorPhoneInput.value = "";
                    donorPanInput.value = "";
                }
            });
        }

        const editDonorSelect = document.getElementById('edit-donor_select');
        const editDonorIdInput = document.getElementById('edit-donor_id_input');
        const editDonorNameInput = document.getElementById('edit-donor_name');
        const editDonorPhoneInput = document.getElementById('edit-donor_phone');
        const editDonorPanInput = document.getElementById('edit-donor_pan');
        
        if (editDonorSelect) {
            editDonorSelect.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                if (this.value) {
                    editDonorIdInput.value = this.value;
                    editDonorNameInput.value = opt.getAttribute('data-name');
                    editDonorPhoneInput.value = opt.getAttribute('data-phone');
                    editDonorPanInput.value = opt.getAttribute('data-pan');
                } else {
                    editDonorIdInput.value = "";
                }
            });
        }
    });

    // Modal controls
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.firstElementChild.classList.remove('scale-95');
                modal.firstElementChild.classList.add('scale-100');
            }, 10);
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.firstElementChild.classList.remove('scale-100');
            modal.firstElementChild.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 150);
        }
    }

    // Populate Edit Modal
    function openEditModal(record) {
        document.getElementById('edit-id').value = record.id;
        document.getElementById('edit-donor_name').value = record.donor_name;
        document.getElementById('edit-donor_phone').value = record.donor_phone || '';
        document.getElementById('edit-donor_pan').value = record.donor_pan || '';
        document.getElementById('edit-amount').value = record.amount;
        document.getElementById('edit-donation_date').value = record.donation_date;
        document.getElementById('edit-payment_mode').value = record.payment_mode;
        document.getElementById('edit-purpose').value = record.purpose;
        document.getElementById('edit-notes').value = record.notes || '';
        
        const editDSelect = document.getElementById('edit-donor_select');
        const editDId = document.getElementById('edit-donor_id_input');
        if (editDSelect && editDId) {
            editDSelect.value = record.donor_id || '';
            editDId.value = record.donor_id || '';
        }
        
        openModal('edit-donation-modal');
    }

    // Number to Words Converter in Indian Format
    function convertNumberToWords(amount) {
        let words = "";
        let value = Math.floor(amount);
        let paise = Math.round((amount - value) * 100);
        
        function convertLessThanOneThousand(number) {
            const units = ["", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen"];
            const tens = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];
            
            let localWords = "";
            if (number % 100 < 20) {
                localWords = units[number % 100];
                number = Math.floor(number / 100);
            } else {
                localWords = units[number % 10];
                number = Math.floor(number / 10);
                localWords = tens[number % 10] + (localWords ? " " + localWords : "");
                number = Math.floor(number / 10);
            }
            if (number === 0) return localWords;
            return units[number] + " Hundred" + (localWords ? " and " + localWords : "");
        }
        
        if (value === 0) {
            words = "Zero";
        } else {
            let crore = Math.floor(value / 10000000);
            value = value % 10000000;
            let lakh = Math.floor(value / 100000);
            value = value % 100000;
            let thousand = Math.floor(value / 1000);
            value = value % 1000;
            let hundred = value;
            
            if (crore > 0) {
                words += convertLessThanOneThousand(crore) + " Crore ";
            }
            if (lakh > 0) {
                words += convertLessThanOneThousand(lakh) + " Lakh ";
            }
            if (thousand > 0) {
                words += convertLessThanOneThousand(thousand) + " Thousand ";
            }
            if (hundred > 0) {
                words += convertLessThanOneThousand(hundred) + " ";
            }
        }
        
        words = words.trim() + " Rupees";
        if (paise > 0) {
            words += " and " + convertLessThanOneThousand(paise) + " Paise";
        }
        return words + " Only";
    }

    // Generate Donation Receipt PDF using jsPDF
    function generateReceiptPDF(donation) {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a5' // A5 is perfect size for standard donation receipts!
        });

        // Config variables
        const orgName = <?php echo json_encode($org['community_name'] ?? 'Community Trust'); ?>;
        const orgReg = <?php echo json_encode($org['registration_number'] ?? ''); ?>;
        const orgAddress = <?php echo json_encode($org['address'] ?? ''); ?>;
        const orgPhone = <?php echo json_encode($org['phone'] ?? ''); ?>;
        const orgEmail = <?php echo json_encode($org['email'] ?? ''); ?>;
        const hasLogo = <?php echo json_encode(!empty($org['logo_data'])); ?>;

        // Colors
        const primaryColor = [37, 99, 235]; // Royal Blue
        const textColor = [30, 41, 59]; // Slate 800
        const lightBg = [248, 250, 252]; // Slate 50

        // Page Width & Height
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();

        // 1. Draw Decorative Border
        doc.setDrawColor(226, 232, 240); // slate-200
        doc.setLineWidth(0.5);
        doc.rect(4, 4, pageWidth - 8, pageHeight - 8);
        doc.rect(5, 5, pageWidth - 10, pageHeight - 10);

        // 2. Draw Receipt Banner Top Right
        doc.setFillColor(37, 99, 235);
        doc.rect(pageWidth - 40, 10, 30, 8);
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(255, 255, 255);
        doc.text("DONATION RECEIPT", pageWidth - 25, 15, { align: 'center' });

        // 3. Organization Header details
        let startY = 16;
        
        // Trust Name
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(14);
        doc.setTextColor(30, 41, 59);
        doc.text(orgName, 10, startY);
        
        // Registration
        if (orgReg) {
            startY += 4.5;
            doc.setFont('Helvetica', 'normal');
            doc.setFontSize(7.5);
            doc.setTextColor(100, 116, 139);
            doc.text("Regd. No: " + orgReg, 10, startY);
        }

        // Contact details
        let contactString = "";
        if (orgAddress) contactString += orgAddress;
        if (orgPhone) contactString += " | Ph: " + orgPhone;
        if (orgEmail) contactString += " | Email: " + orgEmail;

        if (contactString) {
            startY += 4.5;
            doc.setFontSize(7);
            doc.setTextColor(148, 163, 184);
            // Wrap text for address
            const lines = doc.splitTextToSize(contactString, pageWidth - 20);
            doc.text(lines, 10, startY);
            startY += (lines.length - 1) * 3 + 2;
        }

        // Solid divider
        startY += 2;
        doc.setDrawColor(37, 99, 235);
        doc.setLineWidth(1.2);
        doc.line(10, startY, pageWidth - 10, startY);

        // 4. Receipt details header block (Receipt No, Date, PAN)
        startY += 6;
        doc.setFillColor(248, 250, 252);
        doc.rect(10, startY, pageWidth - 20, 14);

        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(100, 116, 139);
        
        doc.text("RECEIPT NO", 14, startY + 5.5);
        doc.setFont('Helvetica', 'bold');
        doc.setTextColor(37, 99, 235);
        doc.text(donation.receipt_no, 14, startY + 10);

        doc.setFont('Helvetica', 'bold');
        doc.setTextColor(100, 116, 139);
        doc.text("DATE", pageWidth / 2 - 10, startY + 5.5);
        doc.setFont('Helvetica', 'bold');
        doc.setTextColor(30, 41, 59);
        doc.text(new Date(donation.donation_date).toLocaleDateString('en-GB'), pageWidth / 2 - 10, startY + 10);

        doc.setFont('Helvetica', 'bold');
        doc.setTextColor(100, 116, 139);
        doc.text("FINANCIAL YEAR", pageWidth - 45, startY + 5.5);
        doc.setFont('Helvetica', 'bold');
        doc.setTextColor(30, 41, 59);
        doc.text("FY " + donation.financial_year, pageWidth - 45, startY + 10);

        // 5. Donor & Amount details
        startY += 20;
        doc.setFont('Helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor(71, 85, 105);

        // Donor details block
        doc.text("Received with thanks from:", 10, startY);
        
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(11);
        doc.setTextColor(15, 23, 42);
        doc.text(donation.donor_name, 10, startY + 5);

        // Subtext phone / PAN
        let donorSubText = "";
        if (donation.donor_phone) donorSubText += "Mobile: " + donation.donor_phone;
        if (donation.donor_pan) {
            if (donorSubText) donorSubText += "   |   ";
            donorSubText += "PAN Card: " + donation.donor_pan;
        }
        if (donorSubText) {
            doc.setFont('Helvetica', 'medium');
            doc.setFontSize(8);
            doc.setTextColor(100, 116, 139);
            doc.text(donorSubText, 10, startY + 9);
        }

        // 6. Grid details (Amount, Purpose, Mode)
        startY += 15;
        
        // Draw auto table or simple boxes
        doc.autoTable({
            startY: startY,
            theme: 'grid',
            headStyles: {
                fillColor: [37, 99, 235],
                textColor: [255, 255, 255],
                fontSize: 8.5,
                fontStyle: 'bold',
                halign: 'left'
            },
            bodyStyles: {
                fontSize: 8.5,
                textColor: [30, 41, 59]
            },
            head: [['Purpose', 'Payment Mode', 'Donation Amount (INR)']],
            body: [[
                donation.purpose,
                donation.payment_mode,
                'Rs. ' + parseFloat(donation.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })
            ]],
            margin: { left: 10, right: 10 },
            tableWidth: pageWidth - 20
        });

        // 7. Amount in Words Box
        let finalY = doc.lastAutoTable.finalY + 5;
        doc.setFillColor(248, 250, 252);
        doc.rect(10, finalY, pageWidth - 20, 10);
        
        doc.setFont('Helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(100, 116, 139);
        doc.text("Amount in Words:", 13, finalY + 4);
        
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(30, 41, 59);
        const amtWords = convertNumberToWords(donation.amount);
        doc.text(amtWords, 13, finalY + 7.5);

        // 8. Signatures & Thanks
        finalY += 16;
        
        // Notes if any
        if (donation.notes) {
            doc.setFont('Helvetica', 'italic');
            doc.setFontSize(7.5);
            doc.setTextColor(148, 163, 184);
            const notesLines = doc.splitTextToSize("Note: " + donation.notes, (pageWidth - 20) / 2);
            doc.text(notesLines, 10, finalY);
        }

        // Thank You text
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(37, 99, 235);
        doc.text("Thank you for your generous contribution!", 10, pageHeight - 15);

        // Sign area
        doc.setFont('Helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(71, 85, 105);
        doc.text("For " + orgName, pageWidth - 55, finalY, { align: 'center' });

        doc.setDrawColor(203, 213, 225); // slate-300
        doc.line(pageWidth - 75, pageHeight - 18, pageWidth - 15, pageHeight - 18);
        
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(7.5);
        doc.text("Authorized Signatory", pageWidth - 45, pageHeight - 14, { align: 'center' });

        // Save PDF file
        const filename = "Receipt_" + donation.receipt_no + ".pdf";
        doc.save(filename);
    }
</script>

<?php
render_footer();
?>
