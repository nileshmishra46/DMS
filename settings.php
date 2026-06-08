<?php
// settings.php
// Manage Trust Settings & Opening Balances

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';

$db = getDb();
$message = '';
$error = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $name = trim($_POST['community_name'] ?? '');
    $reg_no = trim($_POST['registration_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $theme_mode = trim($_POST['theme_mode'] ?? 'light');
    $theme_accent = trim($_POST['theme_accent'] ?? 'amber');
    
    if (empty($name)) {
        $error = 'Community Name is required.';
    } else {
        try {
            $db->beginTransaction();
            
            $logo_sql = "";
            $logo_params = [];
            
            if (isset($_POST['delete_logo']) && $_POST['delete_logo'] == '1') {
                $logo_sql = ", logo_data = NULL, logo_mime = NULL";
            } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['logo']['tmp_name'];
                $file_mime = mime_content_type($file_tmp);
                $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
                
                if (!in_array($file_mime, $allowed_types)) {
                    throw new Exception("Invalid image type. Only JPG, PNG, and WEBP are allowed.");
                }
                
                if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                    throw new Exception("Image size must be less than 2MB.");
                }
                
                $logo_data = file_get_contents($file_tmp);
                $logo_sql = ", logo_data = :logo_data, logo_mime = :logo_mime";
                $logo_params[':logo_data'] = $logo_data;
                $logo_params[':logo_mime'] = $file_mime;
            }
            
            $stmt = $db->prepare("
                UPDATE settings 
                SET community_name = :name, 
                    registration_number = :reg_no, 
                    address = :address, 
                    phone = :phone, 
                    email = :email,
                    theme_mode = :theme_mode,
                    theme_accent = :theme_accent,
                    updated_at = CURRENT_TIMESTAMP
                    $logo_sql
                WHERE id = 'global_config'
            ");
            
            $base_params = [
                ':name' => $name,
                ':reg_no' => $reg_no,
                ':address' => $address,
                ':phone' => $phone,
                ':email' => $email,
                ':theme_mode' => $theme_mode,
                ':theme_accent' => $theme_accent
            ];
            
            $stmt->execute(array_merge($base_params, $logo_params));
            $db->commit();
            $message = 'Settings updated successfully.';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// Handle Opening Balance Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_balance') {
    $fy = trim($_POST['financial_year'] ?? '');
    $balance = (double)($_POST['opening_balance'] ?? 0);
    
    if (!preg_match('/^\d{4}-\d{2}$/', $fy)) {
        $error = 'Financial year format must be YYYY-YY (e.g. 2026-27).';
    } elseif ($balance < 0) {
        $error = 'Opening balance cannot be negative.';
    } else {
        try {
            // UPSERT opening balance
            $stmt = $db->prepare("
                INSERT INTO opening_balances (id, financial_year, opening_balance)
                VALUES (:id, :fy, :balance)
                ON CONFLICT(financial_year) DO UPDATE SET opening_balance = :balance
            ");
            $stmt->execute([
                ':id' => generateUuid(),
                ':fy' => $fy,
                ':balance' => $balance
            ]);
            $message = 'Opening balance saved successfully.';
        } catch (Exception $e) {
            $error = 'Failed to save opening balance: ' . $e->getMessage();
        }
    }
}

// Handle Delete Opening Balance
if (isset($_GET['delete_balance_id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM opening_balances WHERE id = :id");
        $stmt->execute([':id' => $_GET['delete_balance_id']]);
        header("Location: settings.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $error = 'Failed to delete opening balance: ' . $e->getMessage();
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = 'Opening balance deleted successfully.';
}

// Fetch global settings
$org = $db->query("SELECT * FROM settings WHERE id = 'global_config'")->fetch();
$has_logo = !empty($org['logo_data']);

// Fetch all opening balances
$balances = $db->query("SELECT * FROM opening_balances ORDER BY financial_year DESC")->fetchAll();

render_header('Organization Settings', 'settings');
?>

<<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Left column: Settings & Appearance Form (Wraps entire left column) -->
    <form action="settings.php" method="POST" enctype="multipart/form-data" class="lg:col-span-2 space-y-6">
        <input type="hidden" name="action" value="update_settings">
        
        <!-- Alerts inside the form so they align perfectly -->
        <?php if (!empty($message)): ?>
            <div class="flex items-center gap-3 p-4 bg-emerald-50/80 dark:bg-emerald-950/35 border border-emerald-200 dark:border-emerald-900/50 text-emerald-800 dark:text-emerald-300 rounded-2xl text-sm transition-all duration-200">
                <i data-lucide="check-circle-2" class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
                <span class="flex-1 font-medium"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="flex items-center gap-3 p-4 bg-rose-50/80 dark:bg-rose-950/35 border border-rose-200 dark:border-rose-900/50 text-rose-800 dark:text-rose-300 rounded-2xl text-sm transition-all duration-200">
                <i data-lucide="alert-triangle" class="h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400"></i>
                <span class="flex-1 font-medium"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Trust Information Card -->
        <div class="glass-card rounded-2xl shadow-sm overflow-hidden border border-slate-200/50 dark:border-stone-800/40">
            <div class="px-6 py-5 border-b border-slate-100 dark:border-stone-850 flex items-center justify-between">
                <div>
                    <h3 class="font-title font-bold text-slate-950 dark:text-stone-50 text-base">Community Trust Profile</h3>
                    <p class="text-xs text-slate-400 dark:text-stone-500 mt-0.5">Define your trust details that will automatically appear on receipts and reports.</p>
                </div>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Logo Uploader -->
                <div class="flex flex-col sm:flex-row items-center gap-6 pb-2 border-b border-slate-100 dark:border-stone-850">
                    <div class="relative shrink-0 group">
                        <?php if ($has_logo): ?>
                            <img id="logo-preview" class="h-24 w-24 rounded-2xl object-cover ring-4 ring-primary-500/20 dark:ring-primary-500/30 shadow-sm" src="view_image.php?type=logo" alt="Logo">
                        <?php else: ?>
                            <div id="logo-fallback" class="h-24 w-24 rounded-2xl bg-slate-100/60 dark:bg-stone-800/60 border border-dashed border-slate-200 dark:border-stone-700 flex items-center justify-center text-slate-400 dark:text-stone-500 group-hover:bg-slate-200 dark:group-hover:bg-stone-750 transition-colors">
                                <i data-lucide="image" class="h-10 w-10"></i>
                            </div>
                            <img id="logo-preview" class="hidden h-24 w-24 rounded-2xl object-cover ring-4 ring-primary-500/20 dark:ring-primary-500/30 shadow-sm" alt="Logo">
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex-1 space-y-2 text-center sm:text-left">
                        <label class="block text-sm font-bold text-slate-900 dark:text-stone-100 font-title">Organization Logo</label>
                        <p class="text-xs text-slate-400 dark:text-stone-500">Allowed formats: JPG, PNG, WEBP. Max size: 2MB.</p>
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 pt-1">
                            <label class="cursor-pointer bg-primary-600 hover:bg-primary-700 text-white text-xs px-3.5 py-2 rounded-xl font-bold transition-all shadow-sm shadow-primary-500/10 inline-flex items-center gap-1.5">
                                <i data-lucide="upload-cloud" class="h-4 w-4"></i>
                                Upload New Logo
                                <input type="file" name="logo" id="logo-file-input" class="hidden" accept="image/jpeg,image/png,image/webp">
                            </label>
                            
                            <?php if ($has_logo): ?>
                                <button type="button" id="delete-logo-btn" class="bg-rose-50/70 hover:bg-rose-100/70 dark:bg-rose-950/20 dark:hover:bg-rose-950/40 dark:border-rose-900/30 border border-rose-200/50 text-rose-600 dark:text-rose-400 text-xs px-3.5 py-2 rounded-xl font-bold transition-all flex items-center gap-1.5">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                    Remove Logo
                                </button>
                                <input type="hidden" name="delete_logo" id="delete-logo-flag" value="0">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="community_name" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Community Trust Name <span class="text-rose-500">*</span></label>
                        <input type="text" id="community_name" name="community_name" required value="<?php echo htmlspecialchars($org['community_name']); ?>" 
                               class="w-full glass-input rounded-xl px-4 py-3 text-sm focus:outline-none transition-all placeholder-slate-400 dark:placeholder-stone-500">
                    </div>
                    
                    <div class="space-y-1.5">
                        <label for="registration_number" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Registration Number</label>
                        <input type="text" id="registration_number" name="registration_number" value="<?php echo htmlspecialchars($org['registration_number']); ?>" 
                               class="w-full glass-input rounded-xl px-4 py-3 text-sm focus:outline-none transition-all placeholder-slate-400 dark:placeholder-stone-500" placeholder="e.g. REG123456789">
                    </div>
                    
                    <div class="space-y-1.5">
                        <label for="phone" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Contact Phone</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($org['phone']); ?>" 
                               class="w-full glass-input rounded-xl px-4 py-3 text-sm focus:outline-none transition-all placeholder-slate-400 dark:placeholder-stone-500" placeholder="e.g. +91 98765 43210">
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label for="email" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($org['email']); ?>" 
                               class="w-full glass-input rounded-xl px-4 py-3 text-sm focus:outline-none transition-all placeholder-slate-400 dark:placeholder-stone-500" placeholder="e.g. contact@communitytrust.org">
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label for="address" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Physical Address</label>
                        <textarea id="address" name="address" rows="3" 
                                  class="w-full glass-input rounded-xl px-4 py-3 text-sm focus:outline-none transition-all placeholder-slate-400 dark:placeholder-stone-500" placeholder="Enter physical street address..."><?php echo htmlspecialchars($org['address']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appearance & Theme Settings Card -->
        <div class="glass-card rounded-2xl shadow-sm overflow-hidden border border-slate-200/50 dark:border-stone-800/40 p-6 space-y-6">
            <div>
                <h3 class="font-title font-bold text-slate-950 dark:text-stone-50 text-base">Appearance & Theme Settings</h3>
                <p class="text-xs text-slate-400 dark:text-stone-500 mt-0.5">Customize the interface layout color and dark/light viewing preference.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Theme Mode Option -->
                <div class="space-y-3">
                    <label class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Theme Mode</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" onclick="setThemeMode('light')" id="theme-btn-light" 
                                class="flex flex-col items-center gap-1.5 py-3.5 px-2 rounded-xl border text-xs font-bold transition-all">
                            <i data-lucide="sun" class="h-4.5 w-4.5"></i>
                            <span>Light</span>
                        </button>
                        <button type="button" onclick="setThemeMode('dark')" id="theme-btn-dark" 
                                class="flex flex-col items-center gap-1.5 py-3.5 px-2 rounded-xl border text-xs font-bold transition-all">
                            <i data-lucide="moon" class="h-4.5 w-4.5"></i>
                            <span>Dark</span>
                        </button>
                        <button type="button" onclick="setThemeMode('system')" id="theme-btn-system" 
                                class="flex flex-col items-center gap-1.5 py-3.5 px-2 rounded-xl border text-xs font-bold transition-all">
                            <i data-lucide="monitor" class="h-4.5 w-4.5"></i>
                            <span>System</span>
                        </button>
                    </div>
                    <input type="hidden" name="theme_mode" id="theme_mode_input" value="<?php echo htmlspecialchars($theme_mode); ?>">
                </div>

                <!-- Brand Color Option -->
                <div class="space-y-3">
                    <label class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Brand Highlight Accent</label>
                    <div class="grid grid-cols-4 sm:grid-cols-4 gap-2">
                        <?php 
                        $accents = [
                            'amber' => ['label' => 'Amber', 'bg' => 'bg-amber-500'],
                            'orange' => ['label' => 'Orange', 'bg' => 'bg-orange-500'],
                            'blue' => ['label' => 'Blue', 'bg' => 'bg-blue-600'],
                            'emerald' => ['label' => 'Green', 'bg' => 'bg-emerald-500'],
                            'rose' => ['label' => 'Rose', 'bg' => 'bg-rose-500'],
                            'indigo' => ['label' => 'Indigo', 'bg' => 'bg-indigo-600'],
                            'violet' => ['label' => 'Violet', 'bg' => 'bg-violet-600'],
                        ];
                        foreach ($accents as $key => $val):
                        ?>
                            <button type="button" onclick="setThemeAccent('<?php echo $key; ?>')" id="accent-btn-<?php echo $key; ?>" 
                                    class="flex items-center gap-1.5 p-2 border rounded-xl text-[10px] font-bold transition-all justify-start" title="<?php echo $val['label']; ?>">
                                <span class="h-3.5 w-3.5 rounded-full <?php echo $val['bg']; ?> border border-white/20 shadow-sm shrink-0"></span>
                                <span class="truncate"><?php echo $val['label']; ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="theme_accent" id="theme_accent_input" value="<?php echo htmlspecialchars($theme_accent); ?>">
                </div>
            </div>
        </div>

        <!-- Single unified Save button container at bottom of Left Column -->
        <div class="flex justify-end pt-2">
            <button type="submit" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-sm px-8 py-3.5 rounded-xl font-bold transition-all shadow-md shadow-primary-500/15 inline-flex items-center gap-2 transform hover:scale-[1.01]">
                <i data-lucide="save" class="h-4.5 w-4.5"></i>
                Save Configuration
            </button>
        </div>
    </form>

    <!-- Right column: Opening Balances & List -->
    <div class="space-y-6">
        
        <!-- Add Opening Balance Card -->
        <div class="glass-card rounded-2xl p-6 border border-slate-200/50 dark:border-stone-800/40 space-y-4 shadow-sm">
            <div>
                <h3 class="font-title font-bold text-slate-950 dark:text-stone-50 text-base">Add Opening Balance</h3>
                <p class="text-xs text-slate-400 dark:text-stone-500 mt-0.5">Used as starting funds for balance sheets and reports.</p>
            </div>
            
            <form action="settings.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save_balance">
                
                <div class="space-y-1.5">
                    <label for="financial_year" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Financial Year <span class="text-rose-500">*</span></label>
                    <input type="text" id="financial_year" name="financial_year" required placeholder="YYYY-YY (e.g. 2026-27)" 
                           class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all placeholder-slate-400 dark:placeholder-stone-500">
                </div>

                <div class="space-y-1.5">
                    <label for="opening_balance" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Opening Balance (INR) <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-stone-500 text-sm font-semibold">₹</span>
                        <input type="number" step="0.01" min="0" id="opening_balance" name="opening_balance" required placeholder="0.00" 
                               class="w-full glass-input rounded-xl pl-8 pr-4 py-2.5 text-sm focus:outline-none transition-all placeholder-slate-400 dark:placeholder-stone-500">
                    </div>
                </div>

                <button type="submit" class="w-full bg-slate-900 dark:bg-stone-850 hover:bg-slate-800 dark:hover:bg-stone-800 text-white text-xs px-4 py-3 rounded-xl font-bold transition-all flex items-center justify-center gap-1.5">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    Save Balance
                </button>
            </form>
        </div>

        <!-- Opening Balances List Card -->
        <div class="glass-card rounded-2xl p-6 border border-slate-200/50 dark:border-stone-800/40 space-y-4 shadow-sm">
            <div>
                <h3 class="font-title font-bold text-slate-950 dark:text-stone-50 text-base">Recorded Balances</h3>
                <p class="text-xs text-slate-400 dark:text-stone-500 mt-0.5">Opening balance list per financial year.</p>
            </div>
            
            <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                <?php if (empty($balances)): ?>
                    <div class="text-center py-6 border border-dashed border-slate-100 dark:border-stone-800 rounded-xl bg-slate-50/20 dark:bg-stone-900/10">
                        <i data-lucide="info" class="h-8 w-8 text-slate-350 dark:text-stone-600 mx-auto"></i>
                        <p class="text-xs text-slate-400 dark:text-stone-500 mt-1.5 font-medium">No opening balances set yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($balances as $bal): ?>
                        <div class="flex items-center justify-between p-3.5 bg-white/40 dark:bg-stone-900/25 border border-slate-150/40 dark:border-stone-800/35 rounded-xl group transition-all hover:bg-primary-500/5 dark:hover:bg-primary-500/5">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900 dark:text-stone-100">FY <?php echo htmlspecialchars($bal['financial_year']); ?></h4>
                                <p class="text-xs text-slate-500 font-semibold mt-0.5"><?php echo formatCurrency($bal['opening_balance']); ?></p>
                            </div>
                            
                            <a href="settings.php?delete_balance_id=<?php echo urlencode($bal['id']); ?>" 
                               onclick="return confirm('Are you sure you want to delete the opening balance for FY <?php echo htmlspecialchars($bal['financial_year']); ?>?')"
                               class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/35 rounded-xl transition-all opacity-0 group-hover:opacity-100 focus:opacity-100">
                                <i data-lucide="trash-2" class="h-4.5 w-4.5"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<script>
    // Logo Upload live preview
    const fileInput = document.getElementById('logo-file-input');
    const previewImg = document.getElementById('logo-preview');
    const fallbackDiv = document.getElementById('logo-fallback');
    const deleteFlag = document.getElementById('delete-logo-flag');
    const deleteBtn = document.getElementById('delete-logo-btn');

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) {
                        previewImg.src = e.target.result;
                        previewImg.classList.remove('hidden');
                    }
                    if (fallbackDiv) {
                        fallbackDiv.classList.add('hidden');
                    }
                    if (deleteFlag) {
                        deleteFlag.value = "0"; // Reset delete flag
                    }
                }
                reader.readAsDataURL(file);
            }
        });
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            if (confirm("Are you sure you want to remove the logo? Click save settings to commit changes.")) {
                if (previewImg) {
                    previewImg.classList.add('hidden');
                }
                if (fallbackDiv) {
                    fallbackDiv.classList.remove('hidden');
                }
                if (deleteFlag) {
                    deleteFlag.value = "1"; // Set delete flag
                }
            }
        });
    }

    // Live UI select handlers for Appearance Customization
    const modeInput = document.getElementById('theme_mode_input');
    const accentInput = document.getElementById('theme_accent_input');

    function selectModeUI(mode) {
        ['light', 'dark', 'system'].forEach(m => {
            const btn = document.getElementById('theme-btn-' + m);
            if (btn) {
                btn.className = "flex flex-col items-center gap-1.5 py-3.5 px-2 rounded-xl border text-xs font-bold transition-all border-slate-200 dark:border-stone-800 text-slate-500 dark:text-stone-400 bg-white/40 dark:bg-stone-900/30 hover:bg-white dark:hover:bg-stone-850/60";
            }
        });
        const activeBtn = document.getElementById('theme-btn-' + mode);
        if (activeBtn) {
            activeBtn.className = "flex flex-col items-center gap-1.5 py-3.5 px-2 rounded-xl border-2 text-xs font-extrabold transition-all border-primary-600 dark:border-primary-500 text-primary-600 dark:text-primary-400 bg-primary-500/5 dark:bg-primary-950/20";
        }
    }

    function selectAccentUI(accent) {
        const accents = ['amber', 'orange', 'blue', 'emerald', 'rose', 'indigo', 'violet'];
        accents.forEach(a => {
            const btn = document.getElementById('accent-btn-' + a);
            if (btn) {
                btn.className = "flex items-center gap-1.5 p-2 border rounded-xl text-[10px] font-bold transition-all border-slate-200 dark:border-stone-800 text-slate-500 dark:text-stone-400 bg-white/40 dark:bg-stone-900/30 hover:bg-white dark:hover:bg-stone-850/60 justify-start";
            }
        });
        const activeBtn = document.getElementById('accent-btn-' + accent);
        if (activeBtn) {
            activeBtn.className = "flex items-center gap-1.5 p-2 border-2 rounded-xl text-[10px] font-extrabold transition-all border-primary-600 dark:border-primary-500 text-primary-600 dark:text-primary-400 bg-primary-500/5 dark:bg-primary-950/20 shadow-sm justify-start";
        }
    }

    function setThemeMode(mode) {
        modeInput.value = mode;
        selectModeUI(mode);
        
        // Preview dark mode live
        if (mode === 'dark') {
            document.documentElement.classList.add('dark');
        } else if (mode === 'light') {
            document.documentElement.classList.remove('dark');
        } else if (mode === 'system') {
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
    }

    function setThemeAccent(accent) {
        accentInput.value = accent;
        selectAccentUI(accent);
        // Page reload will render the full background preview change.
        // Tapping Save Configuration writes to the DB.
    }

    // Initialize UI on load
    selectModeUI(modeInput.value);
    selectAccentUI(accentInput.value);
</script>

<?php
render_footer();
?>
