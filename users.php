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
                    INSERT INTO users (id, username, password, role) 
                    VALUES (:id, :username, :password, :role)
                ");
                $stmt_insert->execute([
                    ':id' => generateUuid(),
                    ':username' => $username,
                    ':password' => password_hash($password, PASSWORD_BCRYPT),
                    ':role' => $role
                ]);
                $message = "User account '{$username}' created successfully.";
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
$users = $db->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC")->fetchAll();

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
                            <?php endif; ?>
                        </td>
                        
                        <!-- Creation Date -->
                        <td class="px-6 py-4.5">
                            <div class="text-sm text-slate-500 dark:text-stone-400 font-medium"><?php echo date('d M Y, h:i A', strtotime($u['created_at'])); ?></div>
                        </td>
                        
                        <!-- Actions -->
                        <td class="px-6 py-4.5 text-right whitespace-nowrap">
                            <?php 
                            // Disable delete for logged-in user and default admin
                            $is_protected = ($u['id'] === $_SESSION['user_id'] || strtolower($u['username']) === 'admin');
                            if ($is_protected): 
                            ?>
                                <button disabled class="p-2 text-slate-200 dark:text-stone-850 cursor-not-allowed" title="System Protected">
                                    <i data-lucide="trash-2" class="h-4.5 w-4.5"></i>
                                </button>
                            <?php else: ?>
                                <a href="users.php?delete_id=<?php echo urlencode($u['id']); ?>"
                                   onclick="return confirm('Are you sure you want to delete user account \'<?php echo htmlspecialchars($u['username']); ?>\'?')"
                                   class="p-2 text-slate-400 dark:text-stone-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all inline-block" title="Delete User">
                                    <i data-lucide="trash-2" class="h-4.5 w-4.5"></i>
                                </a>
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
</script>

<?php
render_footer();
?>
