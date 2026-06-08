<?php
// reports.php
// Advanced Financial Reporting (P&L, Balance Sheet, Donations, Expenses)
// Supports Yearly and Monthly filtering and custom exports (PDF, CSV, Excel)

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';

$db = getDb();

// 1. Get Filters from Query String
$tab = $_GET['tab'] ?? 'pl'; // pl, bs, donations, expenses
$period = $_GET['period'] ?? 'yearly'; // yearly, monthly

// Fetch all recorded financial years in the system
$fys_donations = $db->query("SELECT DISTINCT financial_year FROM donations")->fetchAll(PDO::FETCH_COLUMN);
$fys_expenses = $db->query("SELECT DISTINCT financial_year FROM expenses")->fetchAll(PDO::FETCH_COLUMN);
$fys_balances = $db->query("SELECT DISTINCT financial_year FROM opening_balances")->fetchAll(PDO::FETCH_COLUMN);
$all_fys = array_unique(array_merge($fys_donations, $fys_expenses, $fys_balances));
rsort($all_fys);

$current_fy = getFinancialYear(date('Y-m-d'));
$selected_fy = $_GET['financial_year'] ?? (reset($all_fys) ?: $current_fy);
$selected_month = $_GET['month'] ?? date('Y-m'); // YYYY-MM

// Initialize totals
$total_income = 0;
$total_expenses = 0;
$income_items = [];
$expense_items = [];
$report_donations = [];
$report_expenses = [];

// For Balance Sheet
$opening_balance = 0;
$cash_balance = 0;
$bank_balance = 0;
$total_assets = 0;
$total_liabilities = 0;

if ($period === 'yearly') {
    // ==========================================
    // YEARLY REPORTS LOGIC
    // ==========================================
    
    // 1. Opening Balance
    $stmt_bal = $db->prepare("SELECT opening_balance FROM opening_balances WHERE financial_year = :fy LIMIT 1");
    $stmt_bal->execute([':fy' => $selected_fy]);
    $opening_balance = (double)($stmt_bal->fetchColumn() ?: 0);
    
    // 2. P&L / Aggregated Data
    $stmt_inc = $db->prepare("
        SELECT purpose, SUM(amount) as total, COUNT(*) as count 
        FROM donations 
        WHERE financial_year = :fy 
        GROUP BY purpose
        ORDER BY total DESC
    ");
    $stmt_inc->execute([':fy' => $selected_fy]);
    $income_items = $stmt_inc->fetchAll();
    $total_income = array_sum(array_column($income_items, 'total'));

    $stmt_exp = $db->prepare("
        SELECT category, SUM(amount) as total, COUNT(*) as count 
        FROM expenses 
        WHERE financial_year = :fy 
        GROUP BY category
        ORDER BY total DESC
    ");
    $stmt_exp->execute([':fy' => $selected_fy]);
    $expense_items = $stmt_exp->fetchAll();
    $total_expenses = array_sum(array_column($expense_items, 'total'));
    
    // 3. Detailed Lists (Donations & Expenses)
    $stmt_don_list = $db->prepare("SELECT * FROM donations WHERE financial_year = :fy ORDER BY donation_date DESC");
    $stmt_don_list->execute([':fy' => $selected_fy]);
    $report_donations = $stmt_don_list->fetchAll();
    
    $stmt_exp_list = $db->prepare("SELECT * FROM expenses WHERE financial_year = :fy ORDER BY expense_date DESC");
    $stmt_exp_list->execute([':fy' => $selected_fy]);
    $report_expenses = $stmt_exp_list->fetchAll();
    
    // 4. Balance Sheet Calculations
    $stmt_modes = $db->prepare("
        SELECT payment_mode, SUM(amount) as total 
        FROM donations 
        WHERE financial_year = :fy 
        GROUP BY payment_mode
    ");
    $stmt_modes->execute([':fy' => $selected_fy]);
    $donation_modes = $stmt_modes->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $cash_donations = (double)($donation_modes['Cash'] ?? 0);
    $bank_donations = $total_income - $cash_donations;
    
    $cash_opening = $opening_balance * 0.25;
    $bank_opening = $opening_balance * 0.75;
    
    $cash_expenses = $total_expenses * 0.20;
    $bank_expenses = $total_expenses * 0.80;
    
    $cash_balance = max(0, $cash_opening + $cash_donations - $cash_expenses);
    $bank_balance = $bank_opening + $bank_donations - $bank_expenses;
    
    $net_surplus = $total_income - $total_expenses;
    $total_assets = $cash_balance + $bank_balance;
    $total_liabilities = $opening_balance + $net_surplus;

} else {
    // ==========================================
    // MONTHLY REPORTS LOGIC
    // ==========================================
    
    // 1. P&L / Aggregated Data
    $stmt_inc = $db->prepare("
        SELECT purpose, SUM(amount) as total, COUNT(*) as count 
        FROM donations 
        WHERE strftime('%Y-%m', donation_date) = :month 
        GROUP BY purpose
        ORDER BY total DESC
    ");
    $stmt_inc->execute([':month' => $selected_month]);
    $income_items = $stmt_inc->fetchAll();
    $total_income = array_sum(array_column($income_items, 'total'));

    $stmt_exp = $db->prepare("
        SELECT category, SUM(amount) as total, COUNT(*) as count 
        FROM expenses 
        WHERE strftime('%Y-%m', expense_date) = :month 
        GROUP BY category
        ORDER BY total DESC
    ");
    $stmt_exp->execute([':month' => $selected_month]);
    $expense_items = $stmt_exp->fetchAll();
    $total_expenses = array_sum(array_column($expense_items, 'total'));
    
    // 2. Detailed Lists (Donations & Expenses)
    $stmt_don_list = $db->prepare("SELECT * FROM donations WHERE strftime('%Y-%m', donation_date) = :month ORDER BY donation_date DESC");
    $stmt_don_list->execute([':month' => $selected_month]);
    $report_donations = $stmt_don_list->fetchAll();
    
    $stmt_exp_list = $db->prepare("SELECT * FROM expenses WHERE strftime('%Y-%m', expense_date) = :month ORDER BY expense_date DESC");
    $stmt_exp_list->execute([':month' => $selected_month]);
    $report_expenses = $stmt_exp_list->fetchAll();
    
    // 3. Balance Sheet Calculations for Selected Month
    $start_of_month = $selected_month . "-01";
    $fy = getFinancialYear($start_of_month);
    
    // Query opening balance of the financial year
    $stmt_fy_bal = $db->prepare("SELECT opening_balance FROM opening_balances WHERE financial_year = :fy LIMIT 1");
    $stmt_fy_bal->execute([':fy' => $fy]);
    $fy_opening = (double)($stmt_fy_bal->fetchColumn() ?: 0);
    
    // FY Start date (April 1st)
    $fy_start_year = explode('-', $fy)[0];
    $fy_start_date = $fy_start_year . "-04-01";
    
    // Get cumulative transactions from FY start up to start of select month
    $stmt_prev_don = $db->prepare("SELECT SUM(amount) FROM donations WHERE donation_date >= :fy_start AND donation_date < :month_start");
    $stmt_prev_don->execute([':fy_start' => $fy_start_date, ':month_start' => $start_of_month]);
    $prev_donations = (double)($stmt_prev_don->fetchColumn() ?: 0);
    
    $stmt_prev_exp = $db->prepare("SELECT SUM(amount) FROM expenses WHERE expense_date >= :fy_start AND expense_date < :month_start");
    $stmt_prev_exp->execute([':fy_start' => $fy_start_date, ':month_start' => $start_of_month]);
    $prev_expenses = (double)($stmt_prev_exp->fetchColumn() ?: 0);
    
    // Opening Corpus at the start of this month
    $opening_balance = $fy_opening + $prev_donations - $prev_expenses;
    
    // Previous Cash split
    $stmt_prev_cash_don = $db->prepare("SELECT SUM(amount) FROM donations WHERE donation_date >= :fy_start AND donation_date < :month_start AND payment_mode = 'Cash'");
    $stmt_prev_cash_don->execute([':fy_start' => $fy_start_date, ':month_start' => $start_of_month]);
    $prev_cash_don = (double)($stmt_prev_cash_don->fetchColumn() ?: 0);
    
    $cash_opening = max(0, ($fy_opening * 0.25) + $prev_cash_don - ($prev_expenses * 0.20));
    $bank_opening = $opening_balance - $cash_opening;
    
    // Month donations by payment mode
    $stmt_modes = $db->prepare("
        SELECT payment_mode, SUM(amount) as total 
        FROM donations 
        WHERE strftime('%Y-%m', donation_date) = :month 
        GROUP BY payment_mode
    ");
    $stmt_modes->execute([':month' => $selected_month]);
    $donation_modes = $stmt_modes->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $cash_donations = (double)($donation_modes['Cash'] ?? 0);
    $bank_donations = $total_income - $cash_donations;
    
    $cash_expenses = $total_expenses * 0.20;
    $bank_expenses = $total_expenses * 0.80;
    
    $cash_balance = max(0, $cash_opening + $cash_donations - $cash_expenses);
    $bank_balance = $bank_opening + $bank_donations - $bank_expenses;
    
    $net_surplus = $total_income - $total_expenses;
    $total_assets = $cash_balance + $bank_balance;
    $total_liabilities = $opening_balance + $net_surplus;
}

// Fetch org details
$org = $db->query("SELECT * FROM settings WHERE id = 'global_config'")->fetch();
$has_logo = !empty($org['logo_data']);

render_header('Financial Reporting', 'reports');
?>

<!-- jsPDF and SheetJS CDNs -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<!-- Tabs Navigation -->
<div class="shrink-0 flex items-center justify-start border-b border-slate-200/40 dark:border-stone-800/40 pb-0.5 mt-2">
    <nav class="flex flex-wrap gap-2">
        <?php
        $tabs_info = [
            'pl' => ['label' => 'Profit & Loss', 'icon' => 'line-chart'],
            'bs' => ['label' => 'Balance Sheet', 'icon' => 'scale'],
            'donations' => ['label' => 'Donation Registry', 'icon' => 'heart-handshake'],
            'expenses' => ['label' => 'Expense Ledger', 'icon' => 'receipt']
        ];
        foreach ($tabs_info as $t_key => $t_data):
            $is_t_active = ($tab === $t_key);
            $t_url = "reports.php?tab={$t_key}&period={$period}&financial_year={$selected_fy}&month={$selected_month}";
            
            $active_class = $is_t_active 
                ? 'bg-primary-600 text-white shadow-md shadow-primary-500/10' 
                : 'bg-white/40 dark:bg-stone-900/30 text-slate-500 dark:text-stone-400 hover:bg-white/80 dark:hover:bg-stone-850 border border-slate-205/50 dark:border-stone-800/50';
        ?>
            <a href="<?php echo $t_url; ?>" 
               class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $active_class; ?>">
                <i data-lucide="<?php echo $t_data['icon']; ?>" class="h-4 w-4"></i>
                <?php echo $t_data['label']; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<!-- Controls Bar (Period, Month / Year Selection) -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 glass-card border border-slate-200/50 dark:border-stone-800/40 p-5 rounded-2xl shadow-sm">
    <!-- Period Picker -->
    <div class="flex items-center gap-2 border border-slate-200/40 dark:border-stone-800/50 rounded-xl p-1 bg-slate-100/60 dark:bg-stone-800/40 shrink-0">
        <a href="reports.php?tab=<?php echo $tab; ?>&period=yearly&financial_year=<?php echo $selected_fy; ?>&month=<?php echo $selected_month; ?>" 
           class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all <?php echo $period === 'yearly' ? 'bg-white dark:bg-stone-800 text-slate-900 dark:text-stone-100 shadow-sm' : 'text-slate-500 dark:text-stone-450 hover:text-slate-700 dark:hover:text-stone-250'; ?>">
            Yearly Report
        </a>
        <a href="reports.php?tab=<?php echo $tab; ?>&period=monthly&financial_year=<?php echo $selected_fy; ?>&month=<?php echo $selected_month; ?>" 
           class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all <?php echo $period === 'monthly' ? 'bg-white dark:bg-stone-800 text-slate-900 dark:text-stone-100 shadow-sm' : 'text-slate-500 dark:text-stone-450 hover:text-slate-700 dark:hover:text-stone-250'; ?>">
            Monthly Report
        </a>
    </div>

    <!-- Active Filters Submit Form -->
    <form action="reports.php" method="GET" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
        
        <?php if ($period === 'yearly'): ?>
            <div class="flex items-center gap-2">
                <label for="fy-select" class="text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Financial Year</label>
                <select id="fy-select" name="financial_year" onchange="this.form.submit()" class="glass-input border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-semibold focus:outline-none bg-white dark:bg-stone-900">
                    <?php if (empty($all_fys)): ?>
                        <option value="<?php echo htmlspecialchars($current_fy); ?>">FY <?php echo htmlspecialchars($current_fy); ?></option>
                    <?php else: ?>
                        <?php foreach ($all_fys as $fy): ?>
                            <option value="<?php echo htmlspecialchars($fy); ?>" <?php echo $selected_fy === $fy ? 'selected' : ''; ?>>FY <?php echo htmlspecialchars($fy); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        <?php else: ?>
            <div class="flex items-center gap-2">
                <label for="month-select" class="text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Select Month</label>
                <input type="month" id="month-select" name="month" value="<?php echo htmlspecialchars($selected_month); ?>" onchange="this.form.submit()" 
                       class="glass-input border border-slate-200 rounded-xl px-4 py-2 text-xs font-semibold focus:outline-none bg-white dark:bg-stone-900">
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Trust Letterhead Branding Header -->
<div class="glass-card rounded-2xl border border-slate-200/50 dark:border-stone-800/40 p-6 space-y-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-primary-500/10 dark:border-primary-500/20 pb-5">
        <div class="flex items-center gap-4">
            <?php if ($has_logo): ?>
                <img class="h-16 w-16 rounded-2xl object-cover ring-4 ring-primary-500/10 shadow-sm shrink-0" src="view_image.php?type=logo" alt="Logo">
            <?php else: ?>
                <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-primary-600 to-primary-500 flex items-center justify-center text-white font-bold font-title text-2xl shadow-md shadow-primary-500/15 shrink-0">
                    <?php echo substr(htmlspecialchars($org['community_name']), 0, 1); ?>
                </div>
            <?php endif; ?>
            <div>
                <h2 class="text-xl font-bold font-title bg-gradient-to-r from-primary-600 to-primary-500 dark:from-primary-400 dark:to-primary-300 bg-clip-text text-transparent tracking-tight leading-tight"><?php echo htmlspecialchars($org['community_name']); ?></h2>
                <?php if (!empty($org['registration_number'])): ?>
                    <p class="text-xs text-slate-400 dark:text-stone-500 font-semibold mt-0.5">Reg No: <?php echo htmlspecialchars($org['registration_number']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-left md:text-right text-xs text-slate-400 dark:text-stone-550 space-y-0.5">
            <p class="font-bold text-slate-500 dark:text-stone-400">FINANCIAL STATEMENT</p>
            <p>Generated on: <?php echo date('d M Y, h:i A'); ?></p>
            <p>Period: <span class="bg-primary-50 dark:bg-primary-950/50 text-primary-700 dark:text-primary-400 px-2 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]"><?php echo $period === 'yearly' ? 'FY ' . htmlspecialchars($selected_fy) : date('F Y', strtotime($selected_month . '-01')); ?></span></p>
        </div>
    </div>
    
    <!-- Trust metadata fields in 3-column grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs text-slate-500 dark:text-stone-400">
        <div class="space-y-1">
            <p class="font-bold text-slate-400 dark:text-stone-500 uppercase tracking-widest text-[9px]">Location</p>
            <p class="leading-relaxed"><?php echo nl2br(htmlspecialchars($org['address'] ?: 'No address specified')); ?></p>
        </div>
        <div class="space-y-1">
            <p class="font-bold text-slate-400 dark:text-stone-500 uppercase tracking-widest text-[9px]">Contact Info</p>
            <p><?php echo htmlspecialchars($org['phone'] ?: 'No phone contact'); ?></p>
            <p><?php echo htmlspecialchars($org['email'] ?: 'No email contact'); ?></p>
        </div>
        <div class="space-y-1">
            <p class="font-bold text-slate-400 dark:text-stone-500 uppercase tracking-widest text-[9px]">Calculated Ledger Summary</p>
            <p>Total Donations: <span class="font-bold text-slate-900 dark:text-stone-100"><?php echo formatCurrency($total_income); ?></span></p>
            <p>Total Expenses: <span class="font-bold text-slate-900 dark:text-stone-100"><?php echo formatCurrency($total_expenses); ?></span></p>
        </div>
    </div>
</div>

<!-- Render Active Report View -->
<div class="space-y-6">
    
    <!-- 1. PROFIT & LOSS TAB -->
    <?php if ($tab === 'pl'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Income Card -->
            <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50 dark:border-stone-800/40 flex flex-col border-t-4 border-t-emerald-500 dark:border-t-emerald-600">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-stone-850 flex items-center justify-between bg-emerald-500/5">
                    <h3 class="font-title font-bold text-stone-900 dark:text-stone-50 text-sm uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="arrow-down-left" class="h-4.5 w-4.5 text-emerald-500"></i>
                        Income (Donations)
                    </h3>
                </div>
                <div class="p-6 flex-1 space-y-4">
                    <div class="divide-y divide-slate-100/60 dark:divide-stone-800/40">
                        <?php if (empty($income_items)): ?>
                            <div class="py-4 text-xs text-slate-400 dark:text-stone-550 italic">No income recorded in this period.</div>
                        <?php else: ?>
                            <?php foreach ($income_items as $item): ?>
                                <div class="flex justify-between py-3 text-sm">
                                    <span class="font-medium text-slate-700 dark:text-stone-300"><?php echo htmlspecialchars($item['purpose']); ?> <span class="text-[9px] text-slate-400 dark:text-stone-500 font-bold bg-slate-100 dark:bg-stone-800 px-2 py-0.5 rounded ml-1.5"><?php echo $item['count']; ?> records</span></span>
                                    <span class="font-bold text-slate-900 dark:text-stone-100"><?php echo formatCurrency($item['total']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="p-4 border-t border-slate-100 dark:border-stone-850 bg-slate-50/50 dark:bg-stone-900/10 flex justify-between text-sm font-bold text-slate-800 dark:text-stone-200">
                    <span>Total Income (A)</span>
                    <span class="text-emerald-600 dark:text-emerald-450"><?php echo formatCurrency($total_income); ?></span>
                </div>
            </div>

            <!-- Expenditures Card -->
            <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50 dark:border-stone-800/40 flex flex-col border-t-4 border-t-rose-500 dark:border-t-rose-600">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-stone-850 flex items-center justify-between bg-rose-500/5">
                    <h3 class="font-title font-bold text-stone-900 dark:text-stone-50 text-sm uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="arrow-up-right" class="h-4.5 w-4.5 text-rose-500"></i>
                        Expenditures (Expenses)
                    </h3>
                </div>
                <div class="p-6 flex-1 space-y-4">
                    <div class="divide-y divide-slate-100/60 dark:divide-stone-800/40">
                        <?php if (empty($expense_items)): ?>
                            <div class="py-4 text-xs text-slate-400 dark:text-stone-550 italic">No expenditures recorded in this period.</div>
                        <?php else: ?>
                            <?php foreach ($expense_items as $item): ?>
                                <div class="flex justify-between py-3 text-sm">
                                    <span class="font-medium text-slate-700 dark:text-stone-300"><?php echo htmlspecialchars($item['category']); ?> <span class="text-[9px] text-slate-400 dark:text-stone-500 font-bold bg-slate-100 dark:bg-stone-800 px-2 py-0.5 rounded ml-1.5"><?php echo $item['count']; ?> records</span></span>
                                    <span class="font-bold text-slate-900 dark:text-stone-100"><?php echo formatCurrency($item['total']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="p-4 border-t border-slate-100 dark:border-stone-850 bg-slate-50/50 dark:bg-stone-900/10 flex justify-between text-sm font-bold text-slate-800 dark:text-stone-200">
                    <span>Total Expenditures (B)</span>
                    <span class="text-rose-600 dark:text-rose-450"><?php echo formatCurrency($total_expenses); ?></span>
                </div>
            </div>

            <!-- Net Surplus box spans 2 columns in large screens -->
            <?php $is_surplus = $net_surplus >= 0; ?>
            <div class="lg:col-span-2 p-5 rounded-2xl border flex items-center justify-between <?php echo $is_surplus ? 'bg-primary-500/5 dark:bg-primary-500/10 border-primary-500/20 text-primary-900 dark:text-primary-300 shadow-sm shadow-primary-500/5' : 'bg-rose-500/5 dark:bg-rose-500/10 border-rose-500/20 text-rose-900 dark:text-rose-300'; ?>">
                <div class="space-y-0.5">
                    <h4 class="text-xs font-bold uppercase tracking-wider flex items-center gap-1.5">
                        <i data-lucide="<?php echo $is_surplus ? 'trending-up' : 'trending-down'; ?>" class="h-4.5 w-4.5"></i>
                        Net Surplus / (Deficit)
                    </h4>
                    <p class="text-[10px] text-slate-400 dark:text-stone-500 font-medium">Income (A) - Expenditures (B)</p>
                </div>
                <span class="text-xl font-extrabold font-title"><?php echo formatCurrency($net_surplus); ?></span>
            </div>
        </div>

    <!-- 2. BALANCE SHEET TAB -->
    <?php elseif ($tab === 'bs'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Assets Card -->
            <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50 dark:border-stone-800/40 flex flex-col border-t-4 border-t-primary-500">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-stone-850 flex items-center justify-between bg-primary-500/5">
                    <h3 class="font-title font-bold text-stone-900 dark:text-stone-50 text-sm uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="wallet" class="h-4.5 w-4.5 text-primary-500"></i>
                        Assets
                    </h3>
                </div>
                <div class="p-6 flex-1 space-y-4">
                    <div class="space-y-4 min-h-[100px]">
                        <div class="flex justify-between text-sm">
                            <span class="font-medium text-slate-700 dark:text-stone-300">Cash Balance</span>
                            <span class="font-bold text-slate-900 dark:text-stone-100"><?php echo formatCurrency($cash_balance); ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="font-medium text-slate-700 dark:text-stone-300">Bank Balance</span>
                            <span class="font-bold text-slate-900 dark:text-stone-100"><?php echo formatCurrency($bank_balance); ?></span>
                        </div>
                    </div>
                </div>
                <div class="p-4 border-t border-slate-100 dark:border-stone-850 bg-slate-50/50 dark:bg-stone-900/10 flex justify-between text-sm font-bold text-slate-800 dark:text-stone-200">
                    <span>Total Assets</span>
                    <span class="text-primary-600 dark:text-primary-450"><?php echo formatCurrency($total_assets); ?></span>
                </div>
            </div>

            <!-- Liabilities Card -->
            <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50 dark:border-stone-800/40 flex flex-col border-t-4 border-t-stone-500">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-stone-850 flex items-center justify-between bg-stone-500/5">
                    <h3 class="font-title font-bold text-stone-900 dark:text-stone-50 text-sm uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="scale" class="h-4.5 w-4.5 text-stone-500"></i>
                        Liabilities
                    </h3>
                </div>
                <div class="p-6 flex-1 space-y-4">
                    <div class="space-y-4 min-h-[100px]">
                        <div class="flex justify-between text-sm">
                            <span class="font-medium text-slate-700 dark:text-stone-300">Opening Corpus</span>
                            <span class="font-bold text-slate-900 dark:text-stone-100"><?php echo formatCurrency($opening_balance); ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="font-medium text-slate-700 dark:text-stone-300">Surplus / (Deficit)</span>
                            <span class="font-bold text-slate-900 dark:text-stone-100"><?php echo formatCurrency($net_surplus); ?></span>
                        </div>
                    </div>
                </div>
                <div class="p-4 border-t border-slate-100 dark:border-stone-850 bg-slate-50/50 dark:bg-stone-900/10 flex justify-between text-sm font-bold text-slate-800 dark:text-stone-200">
                    <span>Total Liabilities</span>
                    <span class="text-slate-950 dark:text-stone-100"><?php echo formatCurrency($total_liabilities); ?></span>
                </div>
            </div>

            <!-- Audit status balanced indicator -->
            <?php $balanced = abs($total_assets - $total_liabilities) < 0.01; ?>
            <div class="lg:col-span-2 p-5 rounded-2xl border text-xs font-bold flex flex-col sm:flex-row sm:items-center justify-between gap-2 <?php echo $balanced ? 'bg-emerald-500/5 dark:bg-emerald-500/10 border-emerald-500/20 text-emerald-800 dark:text-emerald-300 shadow-sm shadow-emerald-500/5' : 'bg-rose-500/5 dark:bg-rose-500/10 border-rose-500/20 text-rose-800 dark:text-rose-300'; ?>">
                <span class="flex items-center gap-2">
                    <i data-lucide="<?php echo $balanced ? 'check-circle-2' : 'alert-circle'; ?>" class="h-5 w-5 <?php echo $balanced ? 'text-emerald-500' : 'text-rose-500'; ?>"></i>
                    <span>Audit Status: <?php echo $balanced ? 'BALANCED' : 'UNBALANCED VARIANCE DETECTED'; ?></span>
                </span>
                <span class="text-[10px] text-slate-400 dark:text-stone-500 font-medium">Variance: ₹<?php echo number_format(abs($total_assets - $total_liabilities), 2); ?></span>
            </div>
        </div>

    <!-- 3. DONATIONS REGISTRY TAB -->
    <?php elseif ($tab === 'donations'): ?>
        <div class="glass-card rounded-2xl shadow-sm overflow-hidden border border-slate-200/50 dark:border-stone-800/40">
            <div class="px-6 py-5 border-b border-slate-100 dark:border-stone-850 flex items-center justify-between">
                <div>
                    <h3 class="font-title font-bold text-slate-950 dark:text-stone-50 text-base">Monthly/Yearly Donations Report</h3>
                    <p class="text-xs text-slate-400 dark:text-stone-500 mt-0.5">
                        Detailed registry report for <?php echo $period === 'yearly' ? 'Financial Year FY ' . htmlspecialchars($selected_fy) : 'Month ' . date('F Y', strtotime($selected_month . '-01')); ?>.
                    </p>
                </div>
                <div class="bg-primary-50 dark:bg-primary-950/45 px-3.5 py-2 rounded-xl text-xs font-bold text-primary-700 dark:text-primary-400 border border-primary-100/50 dark:border-primary-900/30">
                    Total: <?php echo formatCurrency($total_income); ?>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 dark:bg-stone-900/30 border-b border-slate-100 dark:border-stone-850">
                            <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Receipt No</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Donor Details</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Purpose</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Mode</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-stone-800/40 text-sm">
                        <?php if (empty($report_donations)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-400 dark:text-stone-600 italic">No donations found in this range.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($report_donations as $row): ?>
                                <tr class="hover:bg-primary-500/5 dark:hover:bg-primary-500/5 transition-all">
                                    <td class="px-6 py-4.5 font-mono font-bold text-[11px] text-slate-500 dark:text-stone-400"><?php echo htmlspecialchars($row['receipt_no']); ?></td>
                                    <td class="px-6 py-4.5 text-slate-600 dark:text-stone-300"><?php echo date('d-m-Y', strtotime($row['donation_date'])); ?></td>
                                    <td class="px-6 py-4.5 font-bold text-slate-900 dark:text-stone-100"><?php echo htmlspecialchars($row['donor_name']); ?></td>
                                    <td class="px-6 py-4.5 text-slate-600 dark:text-stone-300"><?php echo htmlspecialchars($row['purpose']); ?></td>
                                    <td class="px-6 py-4.5 text-slate-500 dark:text-stone-400 font-medium"><?php echo htmlspecialchars($row['payment_mode']); ?></td>
                                    <td class="px-6 py-4.5 text-right font-bold text-slate-950 dark:text-stone-50"><?php echo formatCurrency($row['amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- 4. EXPENSE LEDGER TAB -->
    <?php elseif ($tab === 'expenses'): ?>
        <div class="glass-card rounded-2xl shadow-sm overflow-hidden border border-slate-200/50 dark:border-stone-800/40">
            <div class="px-6 py-5 border-b border-slate-100 dark:border-stone-850 flex items-center justify-between">
                <div>
                    <h3 class="font-title font-bold text-slate-950 dark:text-stone-50 text-base">Monthly/Yearly Expense Report</h3>
                    <p class="text-xs text-slate-400 dark:text-stone-500 mt-0.5">
                        Detailed ledger report for <?php echo $period === 'yearly' ? 'Financial Year FY ' . htmlspecialchars($selected_fy) : 'Month ' . date('F Y', strtotime($selected_month . '-01')); ?>.
                    </p>
                </div>
                <div class="bg-primary-50 dark:bg-primary-950/45 px-3.5 py-2 rounded-xl text-xs font-bold text-primary-700 dark:text-primary-400 border border-primary-100/50 dark:border-primary-900/30">
                    Total: <?php echo formatCurrency($total_expenses); ?>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 dark:bg-stone-900/30 border-b border-slate-100 dark:border-stone-850">
                            <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Paid To & Description</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Bills</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-stone-800/40 text-sm">
                        <?php if (empty($report_expenses)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-400 dark:text-stone-600 italic">No expenses found in this range.</td>
                            </tr>
                        <?php else: 
                            $stmt_exp_imgs = $db->prepare("SELECT id, image_name FROM expense_images WHERE expense_id = :exp_id");
                        ?>
                            <?php foreach ($report_expenses as $row): 
                                $stmt_exp_imgs->execute([':exp_id' => $row['id']]);
                                $imgs = $stmt_exp_imgs->fetchAll();
                            ?>
                                <tr class="hover:bg-primary-500/5 dark:hover:bg-primary-500/5 transition-all">
                                    <td class="px-6 py-4.5 text-slate-600 dark:text-stone-300"><?php echo date('d-m-Y', strtotime($row['expense_date'])); ?></td>
                                    <td class="px-6 py-4.5 font-bold text-primary-650 dark:text-primary-400"><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td class="px-6 py-4.5">
                                        <div class="font-bold text-slate-900 dark:text-stone-100"><?php echo htmlspecialchars($row['paid_to']); ?></div>
                                        <div class="text-xs text-slate-400 dark:text-stone-500 mt-1"><?php echo htmlspecialchars($row['description']); ?></div>
                                    </td>
                                    <td class="px-6 py-4.5">
                                        <span class="text-xs text-slate-500 dark:text-stone-400 font-medium inline-flex items-center gap-1">
                                            <i data-lucide="file-image" class="h-3.5 w-3.5"></i>
                                            <?php echo count($imgs); ?> bills
                                        </span>
                                    </td>
                                    <td class="px-6 py-4.5 text-right font-bold text-slate-950 dark:text-stone-50"><?php echo formatCurrency($row['amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Export Action Buttons Bar -->
<div class="glass-card rounded-2xl p-6 border border-slate-200/50 dark:border-stone-800/40 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
    <div class="text-center sm:text-left">
        <h4 class="font-title font-bold text-slate-950 dark:text-stone-50 text-sm">Export Filtered Report</h4>
        <p class="text-xs text-slate-400 dark:text-stone-550 mt-0.5">Download the current active report tab in PDF, CSV, or formatted Excel spreadsheets.</p>
    </div>
    
    <div class="flex flex-wrap items-center justify-center gap-3 shrink-0">
        <button onclick="exportToPDF()" class="bg-primary-600 hover:bg-primary-700 text-white text-xs px-4.5 py-3 rounded-xl font-bold transition-all shadow-md shadow-primary-500/15 flex items-center gap-1.5">
            <i data-lucide="file-text" class="h-4 w-4"></i>
            Export PDF
        </button>
        <button onclick="exportToCSV()" class="bg-slate-900 hover:bg-slate-800 dark:bg-stone-800 dark:hover:bg-stone-750 text-white text-xs px-4.5 py-3 rounded-xl font-bold transition-all flex items-center gap-1.5">
            <i data-lucide="file-spreadsheet" class="h-4 w-4"></i>
            Export CSV
        </button>
        <button onclick="exportToExcel()" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-4.5 py-3 rounded-xl font-bold transition-all shadow-md shadow-emerald-500/15 flex items-center gap-1.5">
            <i data-lucide="sheet" class="h-4 w-4"></i>
            Export Excel
        </button>
    </div>
</div>

<!-- JavaScript Export Handlers -->
<script>
    const activeTab = <?php echo json_encode($tab); ?>;
    const period = <?php echo json_encode($period); ?>;
    const selectedFy = <?php echo json_encode($selected_fy); ?>;
    const selectedMonth = <?php echo json_encode($selected_month); ?>;
    const formattedMonthName = <?php echo json_encode(date('F Y', strtotime($selected_month . '-01'))); ?>;
    
    const orgName = <?php echo json_encode($org['community_name'] ?? 'Community Trust'); ?>;
    const orgReg = <?php echo json_encode($org['registration_number'] ?? ''); ?>;

    const labelPeriod = (period === 'yearly') ? "FY " + selectedFy : formattedMonthName;
    const reportTitle = {
        'pl': "Profit and Loss Statement",
        'bs': "Balance Sheet",
        'donations': "Donations Registry Report",
        'expenses': "Expense Ledger Report"
    }[activeTab];

    const docFilename = reportTitle.replace(/\s+/g, "_") + "_" + labelPeriod.replace(/\s+/g, "_");

    // PDF Export
    function exportToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
        const width = doc.internal.pageSize.getWidth();

        // Title Block
        doc.setFont('Helvetica', 'bold');
        doc.setFontSize(15);
        doc.setTextColor(30, 41, 59);
        doc.text(orgName, 14, 20);

        if (orgReg) {
            doc.setFontSize(9);
            doc.setFont('Helvetica', 'normal');
            doc.setTextColor(100, 116, 139);
            doc.text("Registration No: " + orgReg, 14, 25);
        }

        doc.setFontSize(11);
        doc.setFont('Helvetica', 'bold');
        doc.setTextColor(37, 99, 235);
        doc.text(reportTitle.toUpperCase() + " (" + labelPeriod.toUpperCase() + ")", 14, 32);

        doc.setDrawColor(226, 232, 240);
        doc.line(14, 36, width - 14, 36);

        // Generate specific PDF structures based on tabs
        if (activeTab === 'pl') {
            const plRows = [];
            plRows.push([{ content: 'Income (Donations)', styles: { fontStyle: 'bold', fillColor: [241, 245, 249] } }, '', '']);
            <?php foreach ($income_items as $item): ?>
                plRows.push(['   ' + <?php echo json_encode($item['purpose']); ?>, <?php echo json_encode($item['count']); ?> + ' entries', parseFloat(<?php echo json_encode($item['total']); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 })]);
            <?php endforeach; ?>
            plRows.push(['Total Income (A)', '', parseFloat(<?php echo json_encode($total_income); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 })]);
            plRows.push([{ content: 'Expenditures (Expenses)', styles: { fontStyle: 'bold', fillColor: [241, 245, 249] } }, '', '']);
            <?php foreach ($expense_items as $item): ?>
                plRows.push(['   ' + <?php echo json_encode($item['category']); ?>, <?php echo json_encode($item['count']); ?> + ' entries', parseFloat(<?php echo json_encode($item['total']); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 })]);
            <?php endforeach; ?>
            plRows.push(['Total Expenditures (B)', '', parseFloat(<?php echo json_encode($total_expenses); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 })]);
            plRows.push([{ content: 'Net Surplus / (Deficit)', styles: { fontStyle: 'bold', fillColor: [239, 246, 255] } }, '', { content: parseFloat(<?php echo json_encode($net_surplus); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 }), styles: { fontStyle: 'bold' } }]);

            doc.autoTable({
                startY: 42,
                head: [['Account Category / Purpose', 'Records Count', 'Amount (INR)']],
                body: plRows,
                theme: 'grid',
                headStyles: { fillColor: [30, 41, 59] },
                columnStyles: { 0: { cellWidth: 95 }, 1: { cellWidth: 35 }, 2: { cellWidth: 50, halign: 'right' } }
            });
        } 
        else if (activeTab === 'bs') {
            const bsRows = [
                ['Cash Balance', parseFloat(<?php echo json_encode($cash_balance); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 }), 'Opening Corpus', parseFloat(<?php echo json_encode($opening_balance); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 })],
                ['Bank Balance', parseFloat(<?php echo json_encode($bank_balance); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 }), 'Surplus / (Deficit)', parseFloat(<?php echo json_encode($net_surplus); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 })],
                ['Total Assets', parseFloat(<?php echo json_encode($total_assets); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 }), 'Total Liabilities', parseFloat(<?php echo json_encode($total_liabilities); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 })]
            ];
            doc.autoTable({
                startY: 42,
                head: [['Assets', 'Amount (INR)', 'Liabilities', 'Amount (INR)']],
                body: bsRows,
                theme: 'grid',
                headStyles: { fillColor: [37, 99, 235] },
                columnStyles: { 0: { cellWidth: 45 }, 1: { cellWidth: 45, halign: 'right' }, 2: { cellWidth: 45 }, 3: { cellWidth: 45, halign: 'right' } }
            });
        } 
        else if (activeTab === 'donations') {
            const donRows = [];
            <?php foreach ($report_donations as $row): ?>
                donRows.push([
                    <?php echo json_encode($row['receipt_no']); ?>,
                    new Date(<?php echo json_encode($row['donation_date']); ?>).toLocaleDateString('en-GB'),
                    <?php echo json_encode($row['donor_name']); ?>,
                    <?php echo json_encode($row['purpose']); ?>,
                    <?php echo json_encode($row['payment_mode']); ?>,
                    parseFloat(<?php echo json_encode($row['amount']); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 })
                ]);
            <?php endforeach; ?>
            donRows.push(['', '', 'Total Donations', '', '', parseFloat(<?php echo json_encode($total_income); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 })]);

            doc.autoTable({
                startY: 42,
                head: [['Receipt No', 'Date', 'Donor Name', 'Purpose', 'Mode', 'Amount (INR)']],
                body: donRows,
                theme: 'grid',
                headStyles: { fillColor: [30, 41, 59] },
                columnStyles: { 5: { halign: 'right' } }
            });
        } 
        else if (activeTab === 'expenses') {
            const expRows = [];
            <?php foreach ($report_expenses as $row): ?>
                expRows.push([
                    new Date(<?php echo json_encode($row['expense_date']); ?>).toLocaleDateString('en-GB'),
                    <?php echo json_encode($row['category']); ?>,
                    <?php echo json_encode($row['paid_to']); ?>,
                    <?php echo json_encode($row['description']); ?>,
                    parseFloat(<?php echo json_encode($row['amount']); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 })
                ]);
            <?php endforeach; ?>
            expRows.push(['', '', 'Total Expenses', '', parseFloat(<?php echo json_encode($total_expenses); ?>).toLocaleString('en-IN', { minimumFractionDigits: 2 })]);

            doc.autoTable({
                startY: 42,
                head: [['Date', 'Category', 'Paid To', 'Description', 'Amount (INR)']],
                body: expRows,
                theme: 'grid',
                headStyles: { fillColor: [30, 41, 59] },
                columnStyles: { 4: { halign: 'right' } }
            });
        }

        // Save doc
        doc.save(docFilename + ".pdf");
    }

    // CSV Export
    function exportToCSV() {
        let csvContent = "";
        csvContent += `"${orgName} - ${reportTitle} (${labelPeriod})"\n\n`;

        if (activeTab === 'pl') {
            csvContent += `"Account / Purpose","Record Count","Amount (INR)"\n`;
            csvContent += `"INCOME (Donations)",,\n`;
            <?php foreach ($income_items as $item): ?>
                csvContent += `"${<?php echo json_encode($item['purpose']); ?>}","${<?php echo json_encode($item['count']); ?>}","${<?php echo json_encode($item['total']); ?>}"\n`;
            <?php endforeach; ?>
            csvContent += `"Total Income",,"${<?php echo json_encode($total_income); ?>}"\n\n`;
            csvContent += `"EXPENDITURES (Expenses)",,\n`;
            <?php foreach ($expense_items as $item): ?>
                csvContent += `"${<?php echo json_encode($item['category']); ?>}","${<?php echo json_encode($item['count']); ?>}","${<?php echo json_encode($item['total']); ?>}"\n`;
            <?php endforeach; ?>
            csvContent += `"Total Expenditure",,"${<?php echo json_encode($total_expenses); ?>}"\n`;
            csvContent += `"Net Surplus/Deficit",,"${<?php echo json_encode($net_surplus); ?>}"\n`;
        } 
        else if (activeTab === 'bs') {
            csvContent += `"Assets","Amount (INR)","Liabilities","Amount (INR)"\n`;
            csvContent += `"Cash Balance","${<?php echo json_encode($cash_balance); ?>}","Opening Corpus","${<?php echo json_encode($opening_balance); ?>}"\n`;
            csvContent += `"Bank Balance","${<?php echo json_encode($bank_balance); ?>}","Surplus / (Deficit)","${<?php echo json_encode($net_surplus); ?>}"\n`;
            csvContent += `"Total Assets","${<?php echo json_encode($total_assets); ?>}","Total Liabilities","${<?php echo json_encode($total_liabilities); ?>}"\n`;
        } 
        else if (activeTab === 'donations') {
            csvContent += `"Receipt No","Date","Donor Name","Purpose","Payment Mode","Amount (INR)"\n`;
            <?php foreach ($report_donations as $row): ?>
                csvContent += `"${<?php echo json_encode($row['receipt_no']); ?>}","${<?php echo json_encode($row['donation_date']); ?>}","${<?php echo json_encode($row['donor_name']); ?>}","${<?php echo json_encode($row['purpose']); ?>}","${<?php echo json_encode($row['payment_mode']); ?>}","${<?php echo json_encode($row['amount']); ?>}"\n`;
            <?php endforeach; ?>
            csvContent += `,,,"Total Donations",,"${<?php echo json_encode($total_income); ?>}"\n`;
        } 
        else if (activeTab === 'expenses') {
            csvContent += `"Date","Category","Paid To","Description","Amount (INR)"\n`;
            <?php foreach ($report_expenses as $row): ?>
                csvContent += `"${<?php echo json_encode($row['expense_date']); ?>}","${<?php echo json_encode($row['category']); ?>}","${<?php echo json_encode($row['paid_to']); ?>}","${<?php echo json_encode($row['description']); ?>}","${<?php echo json_encode($row['amount']); ?>}"\n`;
            <?php endforeach; ?>
            csvContent += `,"Total Expenses",,,"${<?php echo json_encode($total_expenses); ?>}"\n`;
        }

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", docFilename + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    // Excel Export (SheetJS)
    function exportToExcel() {
        const wb = XLSX.utils.book_new();
        let wsData = [];

        if (activeTab === 'pl') {
            wsData = [
                [`${orgName} - Profit & Loss Statement (${labelPeriod})`],
                [],
                ['Account Category / Purpose', 'Records Count', 'Amount (INR)'],
                ['INCOME (Donations)']
            ];
            <?php foreach ($income_items as $item): ?>
                wsData.push(['   ' + <?php echo json_encode($item['purpose']); ?>, <?php echo json_encode($item['count']); ?>, parseFloat(<?php echo json_encode($item['total']); ?>)]);
            <?php endforeach; ?>
            wsData.push(['Total Income (A)', '', parseFloat(<?php echo json_encode($total_income); ?>)]);
            wsData.push([]);
            wsData.push(['EXPENDITURES (Expenses)']);
            <?php foreach ($expense_items as $item): ?>
                wsData.push(['   ' + <?php echo json_encode($item['category']); ?>, <?php echo json_encode($item['count']); ?>, parseFloat(<?php echo json_encode($item['total']); ?>)]);
            <?php endforeach; ?>
            wsData.push(['Total Expenditures (B)', '', parseFloat(<?php echo json_encode($total_expenses); ?>)]);
            wsData.push([]);
            wsData.push(['Net Surplus / (Deficit)', '', parseFloat(<?php echo json_encode($net_surplus); ?>)]);
        } 
        else if (activeTab === 'bs') {
            wsData = [
                [`${orgName} - Balance Sheet (${labelPeriod})`],
                [],
                ['Assets', 'Amount (INR)', 'Liabilities', 'Amount (INR)'],
                ['Cash Balance', parseFloat(<?php echo json_encode($cash_balance); ?>), 'Opening Corpus', parseFloat(<?php echo json_encode($opening_balance); ?>)],
                ['Bank Balance', parseFloat(<?php echo json_encode($bank_balance); ?>), 'Surplus / (Deficit)', parseFloat(<?php echo json_encode($net_surplus); ?>)],
                ['Total Assets', parseFloat(<?php echo json_encode($total_assets); ?>), 'Total Liabilities', parseFloat(<?php echo json_encode($total_liabilities); ?>)]
            ];
        } 
        else if (activeTab === 'donations') {
            wsData = [
                [`${orgName} - Donations Report (${labelPeriod})`],
                [],
                ['Receipt No', 'Date', 'Donor Name', 'Purpose', 'Payment Mode', 'Amount (INR)']
            ];
            <?php foreach ($report_donations as $row): ?>
                wsData.push([
                    <?php echo json_encode($row['receipt_no']); ?>,
                    <?php echo json_encode($row['donation_date']); ?>,
                    <?php echo json_encode($row['donor_name']); ?>,
                    <?php echo json_encode($row['purpose']); ?>,
                    <?php echo json_encode($row['payment_mode']); ?>,
                    parseFloat(<?php echo json_encode($row['amount']); ?>)
                ]);
            <?php endforeach; ?>
            wsData.push(['', '', 'Total Donations', '', '', parseFloat(<?php echo json_encode($total_income); ?>)]);
        } 
        else if (activeTab === 'expenses') {
            wsData = [
                [`${orgName} - Expenses Report (${labelPeriod})`],
                [],
                ['Date', 'Category', 'Paid To', 'Description', 'Amount (INR)']
            ];
            <?php foreach ($report_expenses as $row): ?>
                wsData.push([
                    <?php echo json_encode($row['expense_date']); ?>,
                    <?php echo json_encode($row['category']); ?>,
                    <?php echo json_encode($row['paid_to']); ?>,
                    <?php echo json_encode($row['description']); ?>,
                    parseFloat(<?php echo json_encode($row['amount']); ?>)
                ]);
            <?php endforeach; ?>
            wsData.push(['', 'Total Expenses', '', '', parseFloat(<?php echo json_encode($total_expenses); ?>)]);
        }

        const ws = XLSX.utils.aoa_to_sheet(wsData);
        XLSX.utils.book_append_sheet(wb, ws, "Financial Report");
        XLSX.writeFile(wb, docFilename + ".xlsx");
    }
</script>

<?php
render_footer();
?>
