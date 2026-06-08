<?php
// index.php
// Trust Dashboard Overview

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';

$db = getDb();
$org = $db->query("SELECT * FROM settings WHERE id = 'global_config'")->fetch();
$org_name = $org['community_name'] ?? 'Community Trust';
$current_fy = getFinancialYear(date('Y-m-d'));

// 1. All-time Metrics
$total_donations_all = (double)($db->query("SELECT SUM(amount) FROM donations")->fetchColumn() ?: 0);
$total_expenses_all = (double)($db->query("SELECT SUM(amount) FROM expenses")->fetchColumn() ?: 0);
$net_surplus_all = $total_donations_all - $total_expenses_all;

// 2. Current Financial Year Metrics
$stmt_don_fy = $db->prepare("SELECT SUM(amount) FROM donations WHERE financial_year = :fy");
$stmt_don_fy->execute([':fy' => $current_fy]);
$total_donations_fy = (double)($stmt_don_fy->fetchColumn() ?: 0);

$stmt_exp_fy = $db->prepare("SELECT SUM(amount) FROM expenses WHERE financial_year = :fy");
$stmt_exp_fy->execute([':fy' => $current_fy]);
$total_expenses_fy = (double)($stmt_exp_fy->fetchColumn() ?: 0);

$net_surplus_fy = $total_donations_fy - $total_expenses_fy;

// 3. Fetch Last 5 Donations
$recent_donations = $db->query("
    SELECT * FROM donations 
    ORDER BY donation_date DESC, created_at DESC 
    LIMIT 5
")->fetchAll();

// 4. Fetch Last 5 Expenses
$recent_expenses = $db->query("
    SELECT * FROM expenses 
    ORDER BY expense_date DESC, created_at DESC 
    LIMIT 5
")->fetchAll();

// 5. Monthly chart calculations for current FY (April -> March)
$fy_months = [
    '04' => 'Apr', '05' => 'May', '06' => 'Jun', 
    '07' => 'Jul', '08' => 'Aug', '09' => 'Sep', 
    '10' => 'Oct', '11' => 'Nov', '12' => 'Dec', 
    '01' => 'Jan', '02' => 'Feb', '03' => 'Mar'
];

$monthly_donations = array_fill_keys(array_keys($fy_months), 0);
$monthly_expenses = array_fill_keys(array_keys($fy_months), 0);

// Query donations for monthly trends in current FY
$stmt_chart_don = $db->prepare("
    SELECT strftime('%m', donation_date) as month, SUM(amount) as total 
    FROM donations 
    WHERE financial_year = :fy 
    GROUP BY month
");
$stmt_chart_don->execute([':fy' => $current_fy]);
$don_trend = $stmt_chart_don->fetchAll(PDO::FETCH_KEY_PAIR);

// Query expenses for monthly trends in current FY
$stmt_chart_exp = $db->prepare("
    SELECT strftime('%m', expense_date) as month, SUM(amount) as total 
    FROM expenses 
    WHERE financial_year = :fy 
    GROUP BY month
");
$stmt_chart_exp->execute([':fy' => $current_fy]);
$exp_trend = $stmt_chart_exp->fetchAll(PDO::FETCH_KEY_PAIR);

// Populate values, cleaning leading zeros where necessary
foreach ($don_trend as $m => $total) {
    if (isset($monthly_donations[$m])) {
        $monthly_donations[$m] = (double)$total;
    }
}
foreach ($exp_trend as $m => $total) {
    if (isset($monthly_expenses[$m])) {
        $monthly_expenses[$m] = (double)$total;
    }
}

// Convert monthly data arrays to plain values for Chart.js
$chart_labels = array_values($fy_months);
$chart_donations_data = array_values($monthly_donations);
$chart_expenses_data = array_values($monthly_expenses);

render_header('Trust Financial Dashboard', 'dashboard');
?>

<!-- ChartJS CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Welcome Trust Profile Card -->
<div class="glass-card rounded-2xl p-6 border border-slate-200/50 dark:border-stone-800/40 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-primary-500/5 via-transparent to-transparent">
    <div class="space-y-1">
        <h2 class="text-xl font-bold font-title text-stone-900 dark:text-stone-100 flex items-center gap-2">
            <span class="inline-flex h-2 w-2 rounded-full bg-primary-500 animate-ping"></span>
            Financial Dashboard: <?php echo htmlspecialchars($org_name); ?>
        </h2>
        <p class="text-xs text-slate-400 dark:text-stone-500">
            Active Financial Year: <span class="font-bold text-primary-600 dark:text-primary-400">FY <?php echo htmlspecialchars($current_fy); ?></span> 
            <?php if (!empty($org['registration_number'])): ?>
                • Reg No: <?php echo htmlspecialchars($org['registration_number']); ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <span class="text-xs text-slate-500 dark:text-stone-400 font-medium bg-white/70 dark:bg-stone-900/60 border border-slate-200/40 dark:border-stone-800/40 px-3.5 py-2 rounded-xl shadow-sm">
            <i data-lucide="calendar" class="h-3.5 w-3.5 inline mr-1 text-primary-500"></i>
            <?php echo date('d M Y'); ?>
        </span>
    </div>
</div>

<!-- Quick Stats Row (FY Metrics) -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- donations card -->
    <div class="glass-card p-6 rounded-2xl shadow-sm flex items-center justify-between border border-slate-200/50 dark:border-stone-800/40 glass-card-hover">
        <div class="flex items-center gap-4">
            <div class="bg-gradient-to-tr from-primary-500/10 to-primary-600/10 text-primary-600 dark:text-primary-400 p-3.5 rounded-2xl border border-primary-500/20">
                <i data-lucide="heart-handshake" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Donations (FY)</p>
                <h3 class="text-lg font-bold text-slate-900 dark:text-stone-50 mt-0.5"><?php echo formatCurrency($total_donations_fy); ?></h3>
                <p class="text-[10px] text-slate-400 dark:text-stone-500 mt-0.5">All-time: <?php echo formatCurrency($total_donations_all); ?></p>
            </div>
        </div>
        <a href="donations.php" class="text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors p-1" title="View donations">
            <i data-lucide="chevron-right" class="h-5 w-5"></i>
        </a>
    </div>

    <!-- expenses card -->
    <div class="glass-card p-6 rounded-2xl shadow-sm flex items-center justify-between border border-slate-200/50 dark:border-stone-800/40 glass-card-hover">
        <div class="flex items-center gap-4">
            <div class="bg-gradient-to-tr from-rose-500/10 to-rose-600/10 text-rose-600 dark:text-rose-400 p-3.5 rounded-2xl border border-rose-500/20">
                <i data-lucide="receipt" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Expenses (FY)</p>
                <h3 class="text-lg font-bold text-slate-900 dark:text-stone-50 mt-0.5"><?php echo formatCurrency($total_expenses_fy); ?></h3>
                <p class="text-[10px] text-slate-400 dark:text-stone-500 mt-0.5">All-time: <?php echo formatCurrency($total_expenses_all); ?></p>
            </div>
        </div>
        <a href="expenses.php" class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors p-1" title="View expenses">
            <i data-lucide="chevron-right" class="h-5 w-5"></i>
        </a>
    </div>

    <!-- surplus card -->
    <?php $is_sur = $net_surplus_fy >= 0; ?>
    <div class="glass-card p-6 rounded-2xl shadow-sm flex items-center justify-between border border-slate-200/50 dark:border-stone-800/40 glass-card-hover">
        <div class="flex items-center gap-4">
            <div class="<?php echo $is_sur ? 'bg-gradient-to-tr from-emerald-500/10 to-emerald-600/10 text-emerald-600 dark:text-emerald-450 border-emerald-500/20' : 'bg-gradient-to-tr from-rose-500/10 to-rose-600/10 text-rose-600 dark:text-rose-450 border-rose-500/20'; ?> p-3.5 rounded-2xl border">
                <i data-lucide="<?php echo $is_sur ? 'smile' : 'frown'; ?>" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-wider">Net Surplus (FY)</p>
                <h3 class="text-lg font-bold <?php echo $is_sur ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'; ?> mt-0.5"><?php echo formatCurrency($net_surplus_fy); ?></h3>
                <p class="text-[10px] text-slate-400 dark:text-stone-500 mt-0.5">All-time: <?php echo formatCurrency($net_surplus_all); ?></p>
            </div>
        </div>
        <a href="reports.php" class="text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors p-1" title="View financial reports">
            <i data-lucide="chevron-right" class="h-5 w-5"></i>
        </a>
    </div>
</div>

<!-- Chart & Graph section -->
<div class="glass-card border border-slate-200/50 dark:border-stone-800/40 rounded-2xl p-6 shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-stone-850 pb-4 mb-4">
        <div>
            <h3 class="font-title font-bold text-slate-950 dark:text-stone-50 text-base">Monthly Trend</h3>
            <p class="text-xs text-slate-400 dark:text-stone-500 mt-0.5">Income vs Expenditure mapping for Financial Year <?php echo htmlspecialchars($current_fy); ?>.</p>
        </div>
    </div>
    <div class="w-full relative h-[300px]">
        <canvas id="trendChart"></canvas>
    </div>
</div>

<!-- Split Tables Row: Recent Donations vs Expenses -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    
    <!-- Recent Donations -->
    <div class="glass-card border border-slate-200/50 dark:border-stone-800/40 rounded-2xl shadow-sm overflow-hidden flex flex-col">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-stone-850 flex items-center justify-between shrink-0">
            <div>
                <h3 class="font-title font-bold text-slate-950 dark:text-stone-50 text-base">Recent Donations</h3>
                <p class="text-xs text-slate-400 dark:text-stone-500 mt-0.5">Last 5 donations received by the trust.</p>
            </div>
            <a href="donations.php" class="text-xs font-bold text-primary-600 hover:text-primary-700 flex items-center gap-0.5">
                View All
                <i data-lucide="chevron-right" class="h-4 w-4"></i>
            </a>
        </div>
        
        <div class="flex-1 divide-y divide-slate-100/60 dark:divide-stone-800/40 overflow-y-auto">
            <?php if (empty($recent_donations)): ?>
                <div class="p-8 text-center text-slate-400 dark:text-stone-600 text-xs italic">No donations recorded yet.</div>
            <?php else: ?>
                <?php foreach ($recent_donations as $don): ?>
                    <div class="flex items-center justify-between p-4.5 hover:bg-primary-500/5 dark:hover:bg-primary-500/5 transition-all">
                        <div class="min-w-0 pr-4">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-stone-100 truncate"><?php echo htmlspecialchars($don['donor_name']); ?></h4>
                            <div class="flex items-center gap-2 mt-1 text-[11px] font-semibold text-slate-400 dark:text-stone-500">
                                <span class="bg-slate-100 dark:bg-stone-800 px-1.5 py-0.5 rounded font-mono"><?php echo htmlspecialchars($don['receipt_no']); ?></span>
                                <span>•</span>
                                <span><?php echo date('d M Y', strtotime($don['donation_date'])); ?></span>
                            </div>
                        </div>
                        <span class="text-sm font-bold text-slate-950 dark:text-stone-50 shrink-0"><?php echo formatCurrency($don['amount']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Expenses -->
    <div class="glass-card border border-slate-200/50 dark:border-stone-800/40 rounded-2xl shadow-sm overflow-hidden flex flex-col">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-stone-850 flex items-center justify-between shrink-0">
            <div>
                <h3 class="font-title font-bold text-slate-950 dark:text-stone-50 text-base">Recent Expenses</h3>
                <p class="text-xs text-slate-400 dark:text-stone-500 mt-0.5">Last 5 operational payouts recorded.</p>
            </div>
            <a href="expenses.php" class="text-xs font-bold text-rose-600 hover:text-rose-700 flex items-center gap-0.5">
                View All
                <i data-lucide="chevron-right" class="h-4 w-4"></i>
            </a>
        </div>

        <div class="flex-1 divide-y divide-slate-100/60 dark:divide-stone-800/40 overflow-y-auto">
            <?php if (empty($recent_expenses)): ?>
                <div class="p-8 text-center text-slate-400 dark:text-stone-600 text-xs italic">No expenses recorded yet.</div>
            <?php else: 
                $stmt_exp_imgs = $db->prepare("SELECT id, image_name FROM expense_images WHERE expense_id = :id");
            ?>
                <?php foreach ($recent_expenses as $exp): 
                    $stmt_exp_imgs->execute([':id' => $exp['id']]);
                    $exp_img_count = count($stmt_exp_imgs->fetchAll());
                ?>
                    <div class="flex items-center justify-between p-4.5 hover:bg-primary-500/5 dark:hover:bg-primary-500/5 transition-all">
                        <div class="min-w-0 pr-4">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-stone-100 truncate">Paid to: <?php echo htmlspecialchars($exp['paid_to']); ?></h4>
                            <div class="flex items-center gap-2 mt-1 text-[11px] font-semibold text-slate-400 dark:text-stone-500">
                                <span class="bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-400 border border-primary-100/50 dark:border-primary-900/30 px-1.5 py-0.5 rounded"><?php echo htmlspecialchars($exp['category']); ?></span>
                                <span>•</span>
                                <span><?php echo date('d M Y', strtotime($exp['expense_date'])); ?></span>
                                <?php if ($exp_img_count > 0): ?>
                                    <span>•</span>
                                    <span class="flex items-center gap-0.5 text-primary-600 dark:text-primary-400 font-medium" title="Receipt attachments"><i data-lucide="paperclip" class="h-3 w-3"></i> <?php echo $exp_img_count; ?> bills</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="text-sm font-bold text-slate-950 dark:text-stone-50 shrink-0"><?php echo formatCurrency($exp['amount']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
</div>

<!-- Setup ChartJS rendering script -->
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const ctx = document.getElementById('trendChart').getContext('2d');
        const labels = <?php echo json_encode($chart_labels); ?>;
        const donationsData = <?php echo json_encode($chart_donations_data); ?>;
        const expensesData = <?php echo json_encode($chart_expenses_data); ?>;

        // Theme adaptive helper colors
        const isDarkMode = document.documentElement.classList.contains('dark');
        const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.08)' : '#f1f5f9';
        const labelColor = isDarkMode ? '#a8a29e' : '#94a3b8';

        // Retrieve primary accent color from tailwind config color definitions if available
        let brandColor = '#f59e0b'; // Default Amber
        if (typeof tailwind !== 'undefined' && tailwind.config && tailwind.config.theme && tailwind.config.theme.extend && tailwind.config.theme.extend.colors && tailwind.config.theme.extend.colors.primary) {
            brandColor = tailwind.config.theme.extend.colors.primary['500'] || brandColor;
        }

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                adjustColors: true,
                datasets: [
                    {
                        label: 'Donations (Income)',
                        data: donationsData,
                        borderColor: brandColor, 
                        backgroundColor: 'rgba(245, 158, 11, 0.05)', // Amber base tint fallback
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true,
                        pointBackgroundColor: brandColor,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Expenses (Expenditure)',
                        data: expensesData,
                        borderColor: '#f43f5e', // Rose 500
                        backgroundColor: 'rgba(244, 63, 94, 0.05)',
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true,
                        pointBackgroundColor: '#f43f5e',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                family: 'Plus Jakarta Sans',
                                weight: '600',
                                size: 11
                            },
                            color: isDarkMode ? '#e7e5e4' : '#475569',
                            boxWidth: 12,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        padding: 12,
                        titleFont: {
                            family: 'Plus Jakarta Sans',
                            weight: 'bold',
                            size: 12
                        },
                        bodyFont: {
                            family: 'Plus Jakarta Sans',
                            size: 11
                        },
                        callbacks: {
                            label: function (context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: 'Plus Jakarta Sans',
                                weight: '500',
                                size: 10
                            },
                            color: labelColor
                        }
                    },
                    y: {
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            font: {
                                family: 'Plus Jakarta Sans',
                                weight: '500',
                                size: 10
                            },
                            color: labelColor,
                            callback: function (value) {
                                if (value >= 100000) return '₹' + (value / 100000).toFixed(1) + 'L';
                                if (value >= 1000) return '₹' + (value / 1000).toFixed(0) + 'k';
                                return '₹' + value;
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<?php
render_footer();
?>
