<?php
// expenses.php
// Manage Trust Expenses & Bills

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';

$db = getDb();
$error = '';
$success = '';

// Handle Delete Single Image
if (isset($_GET['delete_image_id'])) {
    try {
        $stmt_del = $db->prepare("DELETE FROM expense_images WHERE id = :id");
        $stmt_del->execute([':id' => $_GET['delete_image_id']]);
        header("Location: expenses.php?msg=img_deleted");
        exit;
    } catch (Exception $e) {
        $error = "Failed to delete bill image: " . $e->getMessage();
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'img_deleted') {
    $success = "Bill image deleted successfully.";
}

// Handle Create Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $expense_date = trim($_POST['expense_date'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (double)($_POST['amount'] ?? 0);
    $paid_to = trim($_POST['paid_to'] ?? '');

    if (empty($expense_date) || empty($category) || empty($description) || $amount <= 0 || empty($paid_to)) {
        $error = 'Please fill all required fields correctly.';
    } else {
        try {
            $db->beginTransaction();
            $expense_id = generateUuid();
            $fy = getFinancialYear($expense_date);
            
            // Insert expense
            $stmt = $db->prepare("
                INSERT INTO expenses (id, expense_date, category, description, amount, paid_to, financial_year)
                VALUES (:id, :expense_date, :category, :description, :amount, :paid_to, :financial_year)
            ");
            $stmt->execute([
                ':id' => $expense_id,
                ':expense_date' => $expense_date,
                ':category' => $category,
                ':description' => $description,
                ':amount' => $amount,
                ':paid_to' => $paid_to,
                ':financial_year' => $fy
            ]);
            
            // Handle multiple file uploads
            if (isset($_FILES['bills']) && !empty($_FILES['bills']['name'][0])) {
                $files = $_FILES['bills'];
                $file_count = count($files['name']);
                
                if ($file_count > 5) {
                    throw new Exception("Maximum of 5 images are allowed per expense.");
                }
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp_name = $files['tmp_name'][$i];
                        $mime = mime_content_type($tmp_name);
                        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                        
                        if (!in_array($mime, $allowed)) {
                            throw new Exception("File '{$files['name'][$i]}' is not supported. Only JPG, PNG, and WEBP images are allowed.");
                        }
                        
                        if ($files['size'][$i] > 2 * 1024 * 1024) {
                            throw new Exception("File '{$files['name'][$i]}' exceeds 2MB size limit.");
                        }
                        
                        $image_data = file_get_contents($tmp_name);
                        $stmt_img = $db->prepare("
                            INSERT INTO expense_images (id, expense_id, image_data, image_mime, image_name)
                            VALUES (:id, :expense_id, :image_data, :image_mime, :image_name)
                        ");
                        $stmt_img->execute([
                            ':id' => generateUuid(),
                            ':expense_id' => $expense_id,
                            ':image_data' => $image_data,
                            ':image_mime' => $mime,
                            ':image_name' => $files['name'][$i]
                        ]);
                    }
                }
            }
            
            $db->commit();
            $success = "Expense recorded successfully.";
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Failed to record expense: " . $e->getMessage();
        }
    }
}

// Handle Update Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $expense_id = trim($_POST['id'] ?? '');
    $expense_date = trim($_POST['expense_date'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (double)($_POST['amount'] ?? 0);
    $paid_to = trim($_POST['paid_to'] ?? '');

    if (empty($expense_id) || empty($expense_date) || empty($category) || empty($description) || $amount <= 0 || empty($paid_to)) {
        $error = 'Please fill all required fields correctly.';
    } else {
        try {
            $db->beginTransaction();
            $fy = getFinancialYear($expense_date);
            
            $stmt = $db->prepare("
                UPDATE expenses 
                SET expense_date = :expense_date,
                    category = :category,
                    description = :description,
                    amount = :amount,
                    paid_to = :paid_to,
                    financial_year = :financial_year
                WHERE id = :id
            ");
            $stmt->execute([
                ':expense_date' => $expense_date,
                ':category' => $category,
                ':description' => $description,
                ':amount' => $amount,
                ':paid_to' => $paid_to,
                ':financial_year' => $fy,
                ':id' => $expense_id
            ]);
            
            // Count current images
            $stmt_count = $db->prepare("SELECT COUNT(*) FROM expense_images WHERE expense_id = :id");
            $stmt_count->execute([':id' => $expense_id]);
            $current_count = (int)$stmt_count->fetchColumn();
            
            // Handle multiple file uploads
            if (isset($_FILES['bills']) && !empty($_FILES['bills']['name'][0])) {
                $files = $_FILES['bills'];
                $file_count = count($files['name']);
                
                if ($current_count + $file_count > 5) {
                    throw new Exception("Maximum of 5 images are allowed per expense. You already have {$current_count} images stored.");
                }
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp_name = $files['tmp_name'][$i];
                        $mime = mime_content_type($tmp_name);
                        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                        
                        if (!in_array($mime, $allowed)) {
                            throw new Exception("File '{$files['name'][$i]}' is not supported. Only JPG, PNG, and WEBP images are allowed.");
                        }
                        
                        if ($files['size'][$i] > 2 * 1024 * 1024) {
                            throw new Exception("File '{$files['name'][$i]}' exceeds 2MB size limit.");
                        }
                        
                        $image_data = file_get_contents($tmp_name);
                        $stmt_img = $db->prepare("
                            INSERT INTO expense_images (id, expense_id, image_data, image_mime, image_name)
                            VALUES (:id, :expense_id, :image_data, :image_mime, :image_name)
                        ");
                        $stmt_img->execute([
                            ':id' => generateUuid(),
                            ':expense_id' => $expense_id,
                            ':image_data' => $image_data,
                            ':image_mime' => $mime,
                            ':image_name' => $files['name'][$i]
                        ]);
                    }
                }
            }
            
            $db->commit();
            $success = "Expense updated successfully.";
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Failed to update expense: " . $e->getMessage();
        }
    }
}

// Handle Delete Expense
if (isset($_GET['delete_id'])) {
    try {
        $stmt_del = $db->prepare("DELETE FROM expenses WHERE id = :id");
        $stmt_del->execute([':id' => $_GET['delete_id']]);
        header("Location: expenses.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $error = 'Failed to delete expense record: ' . $e->getMessage();
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $success = 'Expense record deleted successfully.';
}

// Filter inputs
$search = trim($_GET['search'] ?? '');
$filter_category = trim($_GET['category'] ?? '');
$filter_fy = trim($_GET['financial_year'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

// Build query
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(category LIKE :search OR paid_to LIKE :search OR description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($filter_category)) {
    $where_clauses[] = "category = :category";
    $params[':category'] = $filter_category;
}

if (!empty($filter_fy)) {
    $where_clauses[] = "financial_year = :financial_year";
    $params[':financial_year'] = $filter_fy;
}

if (!empty($start_date)) {
    $where_clauses[] = "expense_date >= :start_date";
    $params[':start_date'] = $start_date;
}

if (!empty($end_date)) {
    $where_clauses[] = "expense_date <= :end_date";
    $params[':end_date'] = $end_date;
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Fetch categories list for filters
$categories = $db->query("SELECT DISTINCT category FROM expenses ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch financial years list for filters
$fys = $db->query("SELECT DISTINCT financial_year FROM expenses ORDER BY financial_year DESC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch expenses matching query
$stmt_exp = $db->prepare("SELECT * FROM expenses $where_sql ORDER BY expense_date DESC, created_at DESC");
$stmt_exp->execute($params);
$records = $stmt_exp->fetchAll();

render_header('Expenses Registry', 'expenses');
?>

<!-- Header Action Row -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <p class="text-xs text-slate-400 dark:text-stone-400 font-medium">Record operational costs, attach multiple bill photos, and track payout logs.</p>
    </div>
    <button onclick="openModal('add-expense-modal')" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-sm px-4 py-2.5 rounded-xl font-bold transition-all shadow-md shadow-primary-500/15 flex items-center justify-center gap-1.5 shrink-0 transform hover:scale-[1.01]">
        <i data-lucide="plus" class="h-4 w-4"></i>
        Record Expense
    </button>
</div>

<!-- Alerts -->
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
    <form action="expenses.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Search -->
        <div class="space-y-1.5">
            <label for="search" class="block text-[10px] font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Search</label>
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 dark:text-stone-500"></i>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Paid to, description..." 
                       class="w-full glass-input rounded-xl pl-9 pr-3 py-2 text-sm focus:outline-none transition-all placeholder-slate-400 dark:placeholder-stone-500">
            </div>
        </div>

        <!-- Category -->
        <div class="space-y-1.5">
            <label for="category" class="block text-[10px] font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Category</label>
            <select id="category" name="category" class="w-full glass-input rounded-xl px-3 py-2 text-sm focus:outline-none transition-all bg-white dark:bg-stone-900">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filter_category === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
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
        
        <!-- Action Row -->
        <div class="sm:col-span-2 lg:col-span-5 flex items-center justify-end gap-2 pt-2 border-t border-slate-100/50 dark:border-stone-800/40">
            <a href="expenses.php" class="bg-slate-50 dark:bg-stone-900 border border-slate-200 dark:border-stone-800 hover:bg-slate-100 dark:hover:bg-stone-800 text-slate-600 dark:text-stone-300 text-xs px-4 py-2 rounded-xl font-bold transition-all">Reset</a>
            <button type="submit" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-xs px-5 py-2 rounded-xl font-bold transition-all flex items-center gap-1.5 shadow-sm shadow-primary-500/15">
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
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Paid To & Description</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Bills (Max 5)</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-stone-800/40">
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 dark:text-stone-500">
                            <i data-lucide="info" class="h-10 w-10 mx-auto text-slate-300 dark:text-stone-700"></i>
                            <p class="font-medium mt-3 text-sm">No expense records found.</p>
                            <p class="text-xs mt-1 text-slate-400 dark:text-stone-550">Try adjusting your filters.</p>
                        </td>
                    </tr>
                <?php else: 
                    // Prepare images fetch statement
                    $stmt_imgs = $db->prepare("SELECT id, image_name FROM expense_images WHERE expense_id = :exp_id");
                ?>
                    <?php foreach ($records as $row): 
                        // Fetch images
                        $stmt_imgs->execute([':exp_id' => $row['id']]);
                        $imgs = $stmt_imgs->fetchAll();
                    ?>
                        <tr class="hover:bg-primary-500/5 transition-colors">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-slate-900 dark:text-stone-200"><?php echo date('d M Y', strtotime($row['expense_date'])); ?></div>
                                <div class="text-[10px] text-slate-400 dark:text-stone-500 font-bold uppercase mt-0.5">FY <?php echo htmlspecialchars($row['financial_year']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center text-xs font-bold text-primary-700 dark:text-primary-300 bg-primary-500/10 border border-primary-200/50 dark:border-primary-800/30 rounded-lg px-2.5 py-1">
                                    <?php echo htmlspecialchars($row['category']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-slate-900 dark:text-stone-200"><?php echo htmlspecialchars($row['paid_to']); ?></div>
                                <div class="text-xs text-slate-400 dark:text-stone-500 mt-0.5 max-w-xs truncate font-medium"><?php echo htmlspecialchars($row['description']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-bold text-slate-955 dark:text-stone-100"><?php echo formatCurrency($row['amount']); ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if (empty($imgs)): ?>
                                    <span class="text-xs text-slate-400 dark:text-stone-500 font-medium">No files</span>
                                <?php else: ?>
                                    <div class="flex flex-wrap gap-1.5">
                                        <?php foreach ($imgs as $img): ?>
                                            <button onclick="openLightbox('view_image.php?id=<?php echo urlencode($img['id']); ?>', '<?php echo htmlspecialchars($img['image_name']); ?>')" 
                                                    class="group relative block focus:outline-none shrink-0" title="Click to view full image">
                                                <img class="h-9 w-9 object-cover rounded-lg border border-slate-200/80 dark:border-stone-700 ring-1 ring-slate-100/50 dark:ring-stone-850" src="view_image.php?id=<?php echo urlencode($img['id']); ?>" alt="Bill">
                                                <span class="absolute inset-0 bg-stone-950/50 dark:bg-stone-950/70 opacity-0 group-hover:opacity-100 rounded-lg flex items-center justify-center transition-opacity">
                                                    <i data-lucide="zoom-in" class="h-3 w-3 text-white"></i>
                                                </span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right space-x-1 shrink-0 whitespace-nowrap">
                                <!-- Edit -->
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>, <?php echo htmlspecialchars(json_encode($imgs)); ?>)" 
                                        class="p-2 text-slate-400 dark:text-stone-500 hover:text-slate-700 dark:hover:text-stone-300 hover:bg-slate-100 dark:hover:bg-stone-800 rounded-xl transition-all" title="Edit Record">
                                    <i data-lucide="edit-3" class="h-4.5 w-4.5"></i>
                                </button>
                                
                                <!-- Delete -->
                                <a href="expenses.php?delete_id=<?php echo urlencode($row['id']); ?>" 
                                   onclick="return confirm('Are you sure you want to delete this expense of <?php echo htmlspecialchars(formatCurrency($row['amount'])); ?> paid to <?php echo htmlspecialchars($row['paid_to']); ?>?')" 
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

<!-- Add Expense Modal -->
<div id="add-expense-modal" class="hidden fixed inset-0 z-50 bg-stone-900/40 dark:bg-stone-950/60 backdrop-blur-md flex items-center justify-center p-4">
    <div class="glass-card border border-slate-200/50 dark:border-stone-800/40 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="px-6 py-4 border-b border-slate-150 dark:border-stone-800/40 flex items-center justify-between">
            <h3 class="font-title font-bold text-slate-950 dark:text-stone-100 text-base">Record Expense</h3>
            <button onclick="closeModal('add-expense-modal')" class="p-1.5 rounded-lg border border-slate-200 dark:border-stone-800 text-slate-400 dark:text-stone-500 hover:bg-slate-50 dark:hover:bg-stone-900 hover:text-slate-650">
                <i data-lucide="x" class="h-4.5 w-4.5"></i>
            </button>
        </div>
        
        <form action="expenses.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="action" value="create">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="expense_date" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Expense Date <span class="text-rose-500">*</span></label>
                    <input type="date" id="expense_date" name="expense_date" required value="<?php echo date('Y-m-d'); ?>" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                </div>

                <div class="space-y-1.5">
                    <label for="category_add" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Category <span class="text-rose-500">*</span></label>
                    <input type="text" id="category_add" name="category" required placeholder="e.g. Rent, Utility, Salary" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="amount" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Amount (INR) <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" required placeholder="0.00" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all font-bold">
                </div>

                <div class="space-y-1.5">
                    <label for="paid_to" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Paid To <span class="text-rose-500">*</span></label>
                    <input type="text" id="paid_to" name="paid_to" required placeholder="Vendor or Person" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="description" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Description <span class="text-rose-500">*</span></label>
                <textarea id="description" name="description" rows="2" required placeholder="What was this expense for?" 
                          class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all"></textarea>
            </div>

            <div class="space-y-1.5">
                <label for="bills" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Attach Bills (Max 5 files)</label>
                <div class="mt-1 border-2 border-dashed border-slate-200/50 dark:border-stone-700/50 rounded-xl px-4 py-5 bg-slate-50/50 dark:bg-stone-900/30 hover:bg-primary-500/5 transition-colors cursor-pointer relative">
                    <input type="file" name="bills[]" id="bills" multiple accept="image/jpeg,image/png,image/webp" 
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    <div class="text-center space-y-1">
                        <i data-lucide="upload-cloud" class="h-8 w-8 mx-auto text-primary-600 dark:text-primary-450 animate-pulse"></i>
                        <p class="text-xs font-bold text-slate-700 dark:text-stone-300">Click or Drag images to upload</p>
                        <p class="text-[10px] text-slate-400 dark:text-stone-500">JPG, PNG, WEBP up to 2MB per file</p>
                    </div>
                </div>
                <!-- File names preview -->
                <div id="file-list-preview" class="text-xs text-slate-500 dark:text-stone-400 mt-2 space-y-1 pl-1"></div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100/50 dark:border-stone-800/40">
                <button type="button" onclick="closeModal('add-expense-modal')" class="bg-slate-50 dark:bg-stone-900 border border-slate-200 dark:border-stone-800 text-slate-600 dark:text-stone-300 text-xs px-4.5 py-2.5 rounded-xl font-bold transition-all">Cancel</button>
                <button type="submit" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-xs px-5 py-2.5 rounded-xl font-bold transition-all shadow-md shadow-primary-500/15 flex items-center gap-1.5 transform hover:scale-[1.01]">
                    <i data-lucide="check-circle" class="h-4 w-4"></i>
                    Save Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Expense Modal -->
<div id="edit-expense-modal" class="hidden fixed inset-0 z-50 bg-stone-900/40 dark:bg-stone-950/60 backdrop-blur-md flex items-center justify-center p-4">
    <div class="glass-card border border-slate-200/50 dark:border-stone-800/40 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="px-6 py-4 border-b border-slate-150 dark:border-stone-800/40 flex items-center justify-between">
            <h3 class="font-title font-bold text-slate-950 dark:text-stone-100 text-base">Edit Expense</h3>
            <button onclick="closeModal('edit-expense-modal')" class="p-1.5 rounded-lg border border-slate-200 dark:border-stone-800 text-slate-400 dark:text-stone-500 hover:bg-slate-50 dark:hover:bg-stone-900 hover:text-slate-600">
                <i data-lucide="x" class="h-4.5 w-4.5"></i>
            </button>
        </div>
        
        <form action="expenses.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="edit-expense_date" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Expense Date <span class="text-rose-500">*</span></label>
                    <input type="date" id="edit-expense_date" name="expense_date" required 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                </div>

                <div class="space-y-1.5">
                    <label for="edit-category" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Category <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit-category" name="category" required 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="edit-amount" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Amount (INR) <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" min="0.01" id="edit-amount" name="amount" required 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all font-bold">
                </div>

                <div class="space-y-1.5">
                    <label for="edit-paid_to" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Paid To <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit-paid_to" name="paid_to" required 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="edit-description" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Description <span class="text-rose-500">*</span></label>
                <textarea id="edit-description" name="description" rows="2" required 
                          class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all"></textarea>
            </div>

            <!-- Existing Images Gallery -->
            <div id="edit-images-section" class="space-y-1.5 hidden">
                <label class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Stored Bill Photos</label>
                <div id="edit-images-gallery" class="flex flex-wrap gap-2 p-2 bg-slate-50/50 dark:bg-stone-900/40 border border-slate-200/50 dark:border-stone-800/40 rounded-xl">
                    <!-- Dynamic -->
                </div>
            </div>

            <!-- Add new uploads -->
            <div class="space-y-1.5">
                <label for="edit-bills" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Add More Bills (Up to 5 total)</label>
                <div class="mt-1 border border-dashed border-slate-200/50 dark:border-stone-700/50 rounded-xl px-4 py-3 bg-slate-50/50 dark:bg-stone-900/30 hover:bg-primary-500/5 transition-colors cursor-pointer relative">
                    <input type="file" name="bills[]" id="edit-bills" multiple accept="image/jpeg,image/png,image/webp" 
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    <div class="text-center">
                        <p class="text-xs font-semibold text-slate-700 dark:text-stone-300">Click to upload additional photos</p>
                    </div>
                </div>
                <!-- File names preview -->
                <div id="edit-file-list-preview" class="text-xs text-slate-500 dark:text-stone-400 mt-2 space-y-1 pl-1"></div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100/50 dark:border-stone-800/40">
                <button type="button" onclick="closeModal('edit-expense-modal')" class="bg-slate-50 dark:bg-stone-900 border border-slate-200 dark:border-stone-800 text-slate-600 dark:text-stone-300 text-xs px-4.5 py-2.5 rounded-xl font-bold transition-all">Cancel</button>
                <button type="submit" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-xs px-5 py-2.5 rounded-xl font-bold transition-all shadow-md shadow-primary-500/15 flex items-center gap-1.5 transform hover:scale-[1.01]">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    Update Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Image Lightbox Modal -->
<div id="lightbox-modal" class="hidden fixed inset-0 z-[100] bg-stone-950/90 backdrop-blur-xl flex items-center justify-center p-4">
    <div class="relative w-full max-w-3xl max-h-[85vh] flex flex-col items-center gap-4 animate-fade-in-up">
        <button onclick="closeLightbox()" class="absolute -top-12 right-0 bg-white/10 hover:bg-white/20 p-2 rounded-full text-white transition-all">
            <i data-lucide="x" class="h-6 w-6"></i>
        </button>
        <img id="lightbox-img" class="max-w-full max-h-[70vh] rounded-xl object-contain shadow-2xl bg-black/30 border border-stone-800" src="" alt="Full Receipt Image">
        <div class="flex items-center gap-3 text-white text-sm font-semibold shrink-0">
            <span id="lightbox-title" class="truncate max-w-[300px] text-stone-250">Receipt Bill</span>
            <a id="lightbox-download-link" href="" download="" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-md shadow-primary-500/10">
                <i data-lucide="download" class="h-4 w-4"></i>
                Download Receipt
            </a>
        </div>
    </div>
</div>

<script>
    // Modal management
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

    // Lightbox Controls
    function openLightbox(url, name) {
        const lightbox = document.getElementById('lightbox-modal');
        const img = document.getElementById('lightbox-img');
        const title = document.getElementById('lightbox-title');
        const download = document.getElementById('lightbox-download-link');
        
        if (lightbox && img && title && download) {
            img.src = url;
            title.textContent = name;
            download.href = url;
            download.download = name;
            
            lightbox.classList.remove('hidden');
        }
    }

    function closeLightbox() {
        const lightbox = document.getElementById('lightbox-modal');
        if (lightbox) {
            lightbox.classList.add('hidden');
        }
    }

    // File input previews
    const fileAdd = document.getElementById('bills');
    const previewAdd = document.getElementById('file-list-preview');
    if (fileAdd && previewAdd) {
        fileAdd.addEventListener('change', function() {
            previewAdd.innerHTML = "";
            for (let i = 0; i < this.files.length; i++) {
                previewAdd.innerHTML += `<div class="flex items-center gap-1.5 text-slate-600 dark:text-stone-300"><i data-lucide="paperclip" class="h-3 w-3 text-slate-400 dark:text-stone-550"></i> ${this.files[i].name} (${(this.files[i].size / (1024 * 1024)).toFixed(2)} MB)</div>`;
            }
            lucide.createIcons();
        });
    }

    const fileEdit = document.getElementById('edit-bills');
    const previewEdit = document.getElementById('edit-file-list-preview');
    if (fileEdit && previewEdit) {
        fileEdit.addEventListener('change', function() {
            previewEdit.innerHTML = "";
            for (let i = 0; i < this.files.length; i++) {
                previewEdit.innerHTML += `<div class="flex items-center gap-1.5 text-slate-600 dark:text-stone-300"><i data-lucide="paperclip" class="h-3 w-3 text-slate-400 dark:text-stone-550"></i> ${this.files[i].name} (${(this.files[i].size / (1024 * 1024)).toFixed(2)} MB)</div>`;
            }
            lucide.createIcons();
        });
    }

    // Populate and open edit modal
    function openEditModal(record, images) {
        document.getElementById('edit-id').value = record.id;
        document.getElementById('edit-expense_date').value = record.expense_date;
        document.getElementById('edit-category').value = record.category;
        document.getElementById('edit-amount').value = record.amount;
        document.getElementById('edit-paid_to').value = record.paid_to;
        document.getElementById('edit-description').value = record.description;
        
        const gallery = document.getElementById('edit-images-gallery');
        const section = document.getElementById('edit-images-section');
        
        if (gallery && section) {
            gallery.innerHTML = "";
            if (images && images.length > 0) {
                section.classList.remove('hidden');
                images.forEach(img => {
                    gallery.innerHTML += `
                        <div class="relative w-14 h-14 rounded-lg overflow-hidden border border-slate-200/80 dark:border-stone-750 shadow-sm shrink-0 group">
                            <img class="w-full h-full object-cover" src="view_image.php?id=${encodeURIComponent(img.id)}" alt="Receipt">
                            <a href="expenses.php?delete_image_id=${encodeURIComponent(img.id)}" 
                               onclick="return confirm('Are you sure you want to delete this bill photo?')"
                               class="absolute inset-0 bg-rose-600/80 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white transition-opacity duration-200" title="Delete photo">
                                <i data-lucide="trash-2" class="h-4.5 w-4.5"></i>
                            </a>
                        </div>
                    `;
                });
            } else {
                section.classList.add('hidden');
            }
        }
        
        // Clear new files preview
        if (previewEdit) previewEdit.innerHTML = "";
        
        openModal('edit-expense-modal');
        lucide.createIcons();
    }
</script>

<?php
render_footer();
?>
