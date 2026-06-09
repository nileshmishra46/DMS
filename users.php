<?php
// users.php
// Admin Panel: User Account Management

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';

// Verify that the logged-in user is an administrator
if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$db = getDb();
$message = '';
$error = '';

// Handle Create User Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'user');
    
    // Parse allowed pages
    $allowed_pages_arr = $_POST['allowed_pages'] ?? [];
    $valid_pages = ['dashboard', 'donations', 'expenses', 'reports', 'settings'];
    $allowed_pages_arr = array_intersect($allowed_pages_arr, $valid_pages);
    $allowed_pages_str = implode(',', $allowed_pages_arr);
    
    if ($role === 'admin') {
        $allowed_pages_str = 'dashboard,donations,expenses,reports,settings';
    }
    
    // Validations
    if (empty($username) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 4) {
        $error = 'Password must be at least 4 characters long.';
    } elseif (!in_array($role, ['admin', 'user'])) {
        $error = 'Invalid role selected.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = 'Username must be alphanumeric (3-20 characters, underscores allowed).';
    } else {
        try {
            // Check if username already exists case-insensitively
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(:username)");
            $stmt_check->execute([':username' => $username]);
            if ($stmt_check->fetchColumn() > 0) {
                $error = "Username '{$username}' is already taken.";
            } else {
                // Insert User
                $stmt_insert = $db->prepare("
                    INSERT INTO users (id, username, password, role, allowed_pages) 
                    VALUES (:id, :username, :password, :role, :allowed_pages)
                ");
                $stmt_insert->execute([
                    ':id' => generateUuid(),
                    ':username' => $username,
                    ':password' => password_hash($password, PASSWORD_BCRYPT),
                    ':role' => $role,
                    ':allowed_pages' => $allowed_pages_str
                ]);
                $message = "User account '{$username}' created successfully.";
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Edit User Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = trim($_POST['id'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'user');
    
    // Check if user exists
    $stmt_u = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt_u->execute([':id' => $id]);
    $user_record = $stmt_u->fetch();
    
    if (!$user_record) {
        $error = 'User not found.';
    } elseif (strtolower($user_record['username']) === 'admin' && $role !== 'admin') {
        $error = 'System Protection: Default admin account role cannot be changed.';
    } elseif ($id === $_SESSION['user_id'] && $role !== 'admin' && $user_record['role'] === 'admin') {
        $error = 'Security check: You cannot demote your own account from administrator.';
    } elseif (!in_array($role, ['admin', 'user'])) {
        $error = 'Invalid role selected.';
    } else {
        try {
            $allowed_pages_arr = $_POST['allowed_pages'] ?? [];
            $valid_pages = ['dashboard', 'donations', 'expenses', 'reports', 'settings'];
            $allowed_pages_arr = array_intersect($allowed_pages_arr, $valid_pages);
            $allowed_pages_str = implode(',', $allowed_pages_arr);
            
            if ($role === 'admin') {
                $allowed_pages_str = 'dashboard,donations,expenses,reports,settings';
            }
            
            if (!empty($password)) {
                if (strlen($password) < 4) {
                    $error = 'Password must be at least 4 characters long.';
                } else {
                    $stmt_up = $db->prepare("
                        UPDATE users 
                        SET password = :password, role = :role, allowed_pages = :allowed_pages
                        WHERE id = :id
                    ");
                    $stmt_up->execute([
                        ':password' => password_hash($password, PASSWORD_BCRYPT),
                        ':role' => $role,
                        ':allowed_pages' => $allowed_pages_str,
                        ':id' => $id
                    ]);
                    $message = "User '{$user_record['username']}' updated successfully (including password).";
                }
            } else {
                $stmt_up = $db->prepare("
                    UPDATE users 
                    SET role = :role, allowed_pages = :allowed_pages
                    WHERE id = :id
                ");
                $stmt_up->execute([
                    ':role' => $role,
                    ':allowed_pages' => $allowed_pages_str,
                    ':id' => $id
                ]);
                $message = "User '{$user_record['username']}' updated successfully.";
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Delete User Get
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    if ($delete_id === $_SESSION['user_id']) {
        $error = 'Security violation: You cannot delete your own active account.';
    } else {
        try {
            // Retrieve username details
            $stmt_lookup = $db->prepare("SELECT username FROM users WHERE id = :id");
            $stmt_lookup->execute([':id' => $delete_id]);
            $user_del = $stmt_lookup->fetch();
            
            if (!$user_del) {
                $error = 'User account not found.';
            } elseif (strtolower($user_del['username']) === 'admin') {
                $error = 'System Protection: The default Admin account cannot be deleted to avoid lockout.';
            } else {
                // Execute delete
                $stmt_delete = $db->prepare("DELETE FROM users WHERE id = :id");
                $stmt_delete->execute([':id' => $delete_id]);
                header("Location: users.php?msg=deleted&username=" . urlencode($user_del['username']));
                exit;
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Success message callback from redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted' && isset($_GET['username'])) {
    $message = "User account '" . htmlspecialchars($_GET['username']) . "' deleted successfully.";
}

// Retrieve list of users
$users = $db->query("SELECT id, username, role, allowed_pages, created_at FROM users ORDER BY username ASC")->fetchAll();

render_header('User Accounts Management', 'users');
?>

<!-- Header Action Row -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <p class="text-xs text-slate-400 dark:text-stone-400 font-medium">Create, list, and manage admin or standard staff user credentials for the database registry.</p>
    </div>
    <button onclick="openModal('add-user-modal')" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-sm px-4 py-2.5 rounded-xl font-bold transition-all shadow-md shadow-primary-500/15 flex items-center justify-center gap-1.5 shrink-0 transform hover:scale-[1.01]">
        <i data-lucide="user-plus" class="h-4 w-4"></i>
        New User Account
    </button>
</div>

<!-- Alerts -->
<?php if (!empty($message)): ?>
    <div class="flex items-center gap-3 p-4 bg-emerald-50/80 dark:bg-emerald-950/20 border border-emerald-200/50 dark:border-emerald-800/40 text-emerald-800 dark:text-emerald-200 rounded-2xl text-sm transition-all duration-200 animate-fade-in-up">
        <i data-lucide="check-circle-2" class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
        <span class="flex-1 font-medium"><?php echo htmlspecialchars($message); ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="flex items-center gap-3 p-4 bg-rose-50/80 dark:bg-rose-950/20 border border-rose-200/50 dark:border-rose-800/40 text-rose-800 dark:text-rose-200 rounded-2xl text-sm transition-all duration-200 animate-fade-in-up">
        <i data-lucide="alert-triangle" class="h-5 w-5 shrink-0 text-rose-600 dark:text-rose-450"></i>
        <span class="flex-1 font-medium"><?php echo htmlspecialchars($error); ?></span>
    </div>
<?php endif; ?>

<!-- Users List Grid/Table -->
<div class="glass-card border border-slate-200/50 dark:border-stone-800/40 rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/50 dark:bg-stone-900/30 border-b border-slate-150 dark:border-stone-800/40">
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">User ID (Username)</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">System Role</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider">Date Created</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 dark:text-stone-400 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-stone-800/40">
                <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-primary-500/5 transition-colors">
                        <!-- Username details -->
                        <td class="px-6 py-4.5">
                            <div class="flex items-center gap-3.5">
                                <div class="h-9 w-9 rounded-xl bg-slate-100 dark:bg-stone-800 flex items-center justify-center text-slate-500 dark:text-stone-400 font-bold font-title">
                                    <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-slate-900 dark:text-stone-100 flex items-center gap-1.5">
                                        <?php echo htmlspecialchars($u['username']); ?>
                                        <?php if ($u['id'] === $_SESSION['user_id']): ?>
                                            <span class="text-[9px] font-bold bg-primary-100 dark:bg-primary-950 text-primary-700 dark:text-primary-400 px-1.5 py-0.5 rounded-full uppercase">You</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        
                        <!-- Role details -->
                        <td class="px-6 py-4.5">
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 dark:text-amber-400 bg-amber-500/10 border border-amber-500/20 rounded-full px-2.5 py-1">
                                    <i data-lucide="shield-check" class="h-3 w-3"></i>
                                    Administrator
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-700 dark:text-blue-400 bg-blue-500/10 border border-blue-500/20 rounded-full px-2.5 py-1">
                                    <i data-lucide="user" class="h-3 w-3"></i>
                                    Standard Staff
                                </span>
                                <?php 
                                $upages = array_filter(array_map('trim', explode(',', $u['allowed_pages'] ?? '')));
                                if ($u['allowed_pages'] === null) {
                                    $upages = ['dashboard', 'donations', 'expenses', 'reports', 'settings'];
                                }
                                ?>
                                <div class="text-[10px] text-slate-400 dark:text-stone-500 mt-1 font-semibold">
                                    Allowed: <?php echo empty($upages) ? 'None' : implode(', ', array_map('ucfirst', $upages)); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Creation Date -->
                        <td class="px-6 py-4.5">
                            <div class="text-sm text-slate-500 dark:text-stone-400 font-medium"><?php echo date('d M Y, h:i A', strtotime($u['created_at'])); ?></div>
                        </td>
                        
                        <!-- Actions -->
                        <td class="px-6 py-4.5 text-right whitespace-nowrap">
                            <?php 
                            $is_default_admin = (strtolower($u['username']) === 'admin');
                            if ($is_default_admin): 
                            ?>
                                <button disabled class="p-2 text-slate-200 dark:text-stone-850 cursor-not-allowed" title="System Protected">
                                    <i data-lucide="trash-2" class="h-4.5 w-4.5"></i>
                                </button>
                            <?php else: ?>
                                <button onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($u)); ?>)"
                                        class="p-2 text-slate-400 dark:text-stone-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-500/10 rounded-xl transition-all inline-block" title="Edit User">
                                    <i data-lucide="edit-3" class="h-4.5 w-4.5"></i>
                                </button>
                                
                                <?php if ($u['id'] === $_SESSION['user_id']): ?>
                                    <button disabled class="p-2 text-slate-200 dark:text-stone-800/30 dark:text-stone-700 cursor-not-allowed inline-block" title="You cannot delete yourself">
                                        <i data-lucide="trash-2" class="h-4.5 w-4.5"></i>
                                    </button>
                                <?php else: ?>
                                    <a href="users.php?delete_id=<?php echo urlencode($u['id']); ?>"
                                       onclick="return confirm('Are you sure you want to delete user account \'<?php echo htmlspecialchars($u['username']); ?>\'?')"
                                       class="p-2 text-slate-400 dark:text-stone-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all inline-block" title="Delete User">
                                        <i data-lucide="trash-2" class="h-4.5 w-4.5"></i>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- Add User Modal -->
<div id="add-user-modal" class="hidden fixed inset-0 z-50 bg-stone-900/40 dark:bg-stone-950/60 backdrop-blur-md flex items-center justify-center p-4">
    <div class="glass-card border border-slate-200/50 dark:border-stone-800/40 rounded-2xl shadow-xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300 animate-fade-in-up">
        <div class="px-6 py-4 border-b border-slate-150 dark:border-stone-800/40 flex items-center justify-between">
            <h3 class="font-title font-bold text-slate-950 dark:text-stone-100 text-base">New User Account</h3>
            <button onclick="closeModal('add-user-modal')" class="p-1.5 rounded-lg border border-slate-200 dark:border-stone-800 text-slate-400 dark:text-stone-500 hover:bg-slate-50 dark:hover:bg-stone-900 hover:text-slate-600">
                <i data-lucide="x" class="h-4.5 w-4.5"></i>
            </button>
        </div>
        
        <form action="users.php" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="create">
            
            <!-- Username ID -->
            <div class="space-y-1.5">
                <label for="username_new" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">User ID (Username) <span class="text-rose-500">*</span></label>
                <input type="text" id="username_new" name="username" required placeholder="e.g. nilesh_m" 
                       class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
                <p class="text-[10px] text-slate-400 dark:text-stone-500 font-medium">Alphanumeric characters only, no spaces.</p>
            </div>

            <!-- Password -->
            <div class="space-y-1.5">
                <label for="password_new" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Access Password <span class="text-rose-500">*</span></label>
                <input type="password" id="password_new" name="password" required placeholder="Min 4 characters" 
                       class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
            </div>

            <!-- Role Selector -->
            <div class="space-y-1.5">
                <label for="role" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">System Role</label>
                <select id="role" name="role" class="w-full glass-input rounded-xl px-3 py-2.5 text-sm focus:outline-none transition-all bg-white dark:bg-stone-900">
                    <option value="user">Standard Staff (Add registry entries)</option>
                    <option value="admin">Administrator (Full panel access)</option>
                </select>
            </div>

            <!-- Page Permissions Checklist -->
            <div id="allowed_pages_section" class="space-y-2">
                <span class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Page Access Permissions</span>
                <div class="grid grid-cols-2 gap-2 bg-slate-50/50 dark:bg-stone-900/30 p-3 rounded-xl border border-slate-200/40 dark:border-stone-800/40">
                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-stone-300 cursor-pointer">
                        <input type="checkbox" name="allowed_pages[]" value="dashboard" checked class="rounded text-primary-650 focus:ring-primary-500 border-slate-350 dark:border-stone-700 bg-white dark:bg-stone-900">
                        <span>Dashboard</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-stone-300 cursor-pointer">
                        <input type="checkbox" name="allowed_pages[]" value="donations" checked class="rounded text-primary-650 focus:ring-primary-500 border-slate-350 dark:border-stone-700 bg-white dark:bg-stone-900">
                        <span>Donations</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-stone-300 cursor-pointer">
                        <input type="checkbox" name="allowed_pages[]" value="expenses" checked class="rounded text-primary-650 focus:ring-primary-500 border-slate-350 dark:border-stone-700 bg-white dark:bg-stone-900">
                        <span>Expenses</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-stone-300 cursor-pointer">
                        <input type="checkbox" name="allowed_pages[]" value="reports" checked class="rounded text-primary-650 focus:ring-primary-500 border-slate-350 dark:border-stone-700 bg-white dark:bg-stone-900">
                        <span>Reports</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-stone-300 cursor-pointer col-span-2">
                        <input type="checkbox" name="allowed_pages[]" value="settings" checked class="rounded text-primary-650 focus:ring-primary-500 border-slate-350 dark:border-stone-700 bg-white dark:bg-stone-900">
                        <span>Settings</span>
                    </label>
                </div>
            </div>

            <!-- Modal footer actions -->
            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100/50 dark:border-stone-800/40">
                <button type="button" onclick="closeModal('add-user-modal')" class="bg-slate-50 dark:bg-stone-900 border border-slate-200 dark:border-stone-800 text-slate-600 dark:text-stone-300 text-xs px-4.5 py-2.5 rounded-xl font-bold transition-all">Cancel</button>
                <button type="submit" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-xs px-5 py-2.5 rounded-xl font-bold transition-all shadow-md shadow-primary-500/15 flex items-center gap-1.5 transform hover:scale-[1.01]">
                    <i data-lucide="check-circle" class="h-4 w-4"></i>
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="edit-user-modal" class="hidden fixed inset-0 z-50 bg-stone-900/40 dark:bg-stone-950/60 backdrop-blur-md flex items-center justify-center p-4">
    <div class="glass-card border border-slate-200/50 dark:border-stone-800/40 rounded-2xl shadow-xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300 animate-fade-in-up">
        <div class="px-6 py-4 border-b border-slate-150 dark:border-stone-800/40 flex items-center justify-between">
            <h3 class="font-title font-bold text-slate-950 dark:text-stone-100 text-base">Edit User Account</h3>
            <button onclick="closeModal('edit-user-modal')" class="p-1.5 rounded-lg border border-slate-200 dark:border-stone-800 text-slate-400 dark:text-stone-500 hover:bg-slate-50 dark:hover:bg-stone-900 hover:text-slate-600">
                <i data-lucide="x" class="h-4.5 w-4.5"></i>
            </button>
        </div>
        
        <form action="users.php" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            
            <!-- Username ID (Read Only) -->
            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">User ID (Username)</label>
                <input type="text" id="edit-username" readonly 
                       class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all bg-slate-100/50 dark:bg-stone-900/50 opacity-70 cursor-not-allowed">
            </div>

            <!-- Password -->
            <div class="space-y-1.5">
                <label for="password_edit" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Access Password (Leave blank to keep current)</label>
                <input type="password" id="password_edit" name="password" placeholder="New Password (min 4 chars)" 
                       class="w-full glass-input rounded-xl px-4 py-2.5 text-sm focus:outline-none transition-all">
            </div>

            <!-- Role Selector -->
            <div class="space-y-1.5">
                <label for="role_edit" class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">System Role</label>
                <select id="role_edit" name="role" class="w-full glass-input rounded-xl px-3 py-2.5 text-sm focus:outline-none transition-all bg-white dark:bg-stone-900">
                    <option value="user">Standard Staff (Add registry entries)</option>
                    <option value="admin">Administrator (Full panel access)</option>
                </select>
            </div>

            <!-- Page Permissions Checklist -->
            <div id="edit_allowed_pages_section" class="space-y-2">
                <span class="block text-xs font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Page Access Permissions</span>
                <div class="grid grid-cols-2 gap-2 bg-slate-50/50 dark:bg-stone-900/30 p-3 rounded-xl border border-slate-200/40 dark:border-stone-800/40">
                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-stone-300 cursor-pointer">
                        <input type="checkbox" name="allowed_pages[]" value="dashboard" id="edit-perm-dashboard" class="rounded text-primary-650 focus:ring-primary-500 border-slate-350 dark:border-stone-700 bg-white dark:bg-stone-900">
                        <span>Dashboard</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-stone-300 cursor-pointer">
                        <input type="checkbox" name="allowed_pages[]" value="donations" id="edit-perm-donations" class="rounded text-primary-650 focus:ring-primary-500 border-slate-350 dark:border-stone-700 bg-white dark:bg-stone-900">
                        <span>Donations</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-stone-300 cursor-pointer">
                        <input type="checkbox" name="allowed_pages[]" value="expenses" id="edit-perm-expenses" class="rounded text-primary-650 focus:ring-primary-500 border-slate-350 dark:border-stone-700 bg-white dark:bg-stone-900">
                        <span>Expenses</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-stone-300 cursor-pointer">
                        <input type="checkbox" name="allowed_pages[]" value="reports" id="edit-perm-reports" class="rounded text-primary-650 focus:ring-primary-500 border-slate-350 dark:border-stone-700 bg-white dark:bg-stone-900">
                        <span>Reports</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-stone-300 cursor-pointer col-span-2">
                        <input type="checkbox" name="allowed_pages[]" value="settings" id="edit-perm-settings" class="rounded text-primary-650 focus:ring-primary-500 border-slate-350 dark:border-stone-700 bg-white dark:bg-stone-900">
                        <span>Settings</span>
                    </label>
                </div>
            </div>

            <!-- Modal footer actions -->
            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100/50 dark:border-stone-800/40">
                <button type="button" onclick="closeModal('edit-user-modal')" class="bg-slate-50 dark:bg-stone-900 border border-slate-200 dark:border-stone-800 text-slate-600 dark:text-stone-300 text-xs px-4.5 py-2.5 rounded-xl font-bold transition-all">Cancel</button>
                <button type="submit" class="bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-xs px-5 py-2.5 rounded-xl font-bold transition-all shadow-md shadow-primary-500/15 flex items-center gap-1.5 transform hover:scale-[1.01]">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    Update Account
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Modal management controls
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

    // Role selector change bindings to toggle permission checklist visibility
    document.addEventListener('DOMContentLoaded', function() {
        const roleNew = document.getElementById('role');
        const sectionNew = document.getElementById('allowed_pages_section');
        if (roleNew && sectionNew) {
            roleNew.addEventListener('change', function() {
                if (this.value === 'admin') {
                    sectionNew.classList.add('hidden');
                } else {
                    sectionNew.classList.remove('hidden');
                }
            });
        }

        const roleEdit = document.getElementById('role_edit');
        const sectionEdit = document.getElementById('edit_allowed_pages_section');
        if (roleEdit && sectionEdit) {
            roleEdit.addEventListener('change', function() {
                if (this.value === 'admin') {
                    sectionEdit.classList.add('hidden');
                } else {
                    sectionEdit.classList.remove('hidden');
                }
            });
        }
    });

    // Populate and trigger edit user modal
    function openEditUserModal(user) {
        document.getElementById('edit-id').value = user.id;
        document.getElementById('edit-username').value = user.username;
        document.getElementById('password_edit').value = '';
        document.getElementById('role_edit').value = user.role;

        // Clear checkboxes
        const checkboxKeys = ['dashboard', 'donations', 'expenses', 'reports', 'settings'];
        checkboxKeys.forEach(k => {
            document.getElementById('edit-perm-' + k).checked = false;
        });

        // Parse and check appropriate checkboxes
        let allowedPages = [];
        if (user.allowed_pages === null) {
            allowedPages = ['dashboard', 'donations', 'expenses', 'reports', 'settings'];
        } else if (user.allowed_pages) {
            allowedPages = user.allowed_pages.split(',').map(p => p.trim());
        }

        allowedPages.forEach(p => {
            const el = document.getElementById('edit-perm-' + p);
            if (el) el.checked = true;
        });

        // Hide section initially if user role is admin
        const sectionEdit = document.getElementById('edit_allowed_pages_section');
        if (user.role === 'admin') {
            sectionEdit.classList.add('hidden');
        } else {
            sectionEdit.classList.remove('hidden');
        }

        openModal('edit-user-modal');
    }
</script>

<?php
render_footer();
?>
