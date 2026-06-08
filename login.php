<?php
// login.php
// User Authentication Page

require_once __DIR__ . '/db.php';

// Redirect to dashboard if already logged in
if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$db = getDb();
$error = '';

// Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both UserID and Password.';
    } else {
        // Query user case-insensitively for the username
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(:username) LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            
            // Redirect to homepage
            header("Location: index.php");
            exit;
        } else {
            $error = 'Invalid UserID or Password. Please try again.';
        }
    }
}

// Fetch trust settings to apply matching appearance themes
$org = $db->query("SELECT * FROM settings WHERE id = 'global_config'")->fetch();
$org_name = $org['community_name'] ?? 'Community Trust';
$theme_mode = $org['theme_mode'] ?? 'light';
$theme_accent = $org['theme_accent'] ?? 'amber';
$has_logo = !empty($org['logo_data']);

$html_class = 'h-full';
if ($theme_mode === 'dark') {
    $html_class .= ' dark';
}
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $html_class; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($org_name); ?></title>
    <!-- Fonts & Styling -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Set Tailwind Config dynamically matching application's accent color
        const activeAccent = "<?php echo $theme_accent; ?>";
        const accentColors = {
            amber: {
                50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d', 400: '#fbbf24',
                500: '#f59e0b', 600: '#d97706', 700: '#b45309', 800: '#92400e', 900: '#78350f'
            },
            orange: {
                50: '#fff7ed', 100: '#ffedd5', 200: '#fed7aa', 300: '#fdba74', 400: '#fb923c',
                500: '#f97316', 600: '#ea580c', 700: '#c2410c', 800: '#9a3412', 900: '#7c2d12'
            },
            blue: {
                50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc', 400: '#38bdf8',
                500: '#0ea5e9', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a'
            },
            emerald: {
                50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7', 400: '#34d399',
                500: '#10b981', 600: '#059669', 700: '#047857', 800: '#065f46', 900: '#064e3b'
            },
            rose: {
                50: '#fff1f2', 100: '#ffe4e6', 200: '#fecdd3', 300: '#fda4af', 400: '#fb7185',
                500: '#f43f5e', 600: '#e11d48', 700: '#be123c', 800: '#9f1239', 900: '#881337'
            },
            indigo: {
                50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc', 400: '#818cf8',
                500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81'
            },
            violet: {
                50: '#f5f3ff', 100: '#ede9fe', 200: '#ddd6fe', 300: '#c084fc', 400: '#a78bfa',
                500: '#8b5cf6', 600: '#7c3aed', 700: '#6d28d9', 800: '#5b21b6', 900: '#4c1d95'
            }
        };
        const primaryPalette = accentColors[activeAccent] || accentColors.amber;
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        title: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        primary: primaryPalette
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            <?php if ($theme_accent === 'amber'): ?>
                background: radial-gradient(at 0% 0%, hsla(38, 100%, 98%, 1) 0, transparent 50%), 
                            radial-gradient(at 100% 100%, hsla(35, 100%, 96%, 1) 0, transparent 50%), 
                            #fffbeb;
            <?php elseif ($theme_accent === 'orange'): ?>
                background: radial-gradient(at 0% 0%, hsla(16, 100%, 98%, 1) 0, transparent 50%), 
                            radial-gradient(at 100% 100%, hsla(12, 100%, 96%, 1) 0, transparent 50%), 
                            #fff8f5;
            <?php elseif ($theme_accent === 'blue'): ?>
                background: radial-gradient(at 0% 0%, hsla(215, 100%, 98%, 1) 0, transparent 50%), 
                            radial-gradient(at 100% 100%, hsla(210, 100%, 96%, 1) 0, transparent 50%), 
                            #f8fafc;
            <?php elseif ($theme_accent === 'emerald'): ?>
                background: radial-gradient(at 0% 0%, hsla(150, 80%, 98%, 1) 0, transparent 50%), 
                            radial-gradient(at 100% 100%, hsla(140, 80%, 96%, 1) 0, transparent 50%), 
                            #f7fee7;
            <?php elseif ($theme_accent === 'rose'): ?>
                background: radial-gradient(at 0% 0%, hsla(350, 100%, 99%, 1) 0, transparent 50%), 
                            radial-gradient(at 100% 100%, hsla(345, 100%, 97%, 1) 0, transparent 50%), 
                            #fff1f2;
            <?php elseif ($theme_accent === 'indigo'): ?>
                background: radial-gradient(at 0% 0%, hsla(230, 100%, 98%, 1) 0, transparent 50%), 
                            radial-gradient(at 100% 100%, hsla(225, 100%, 96%, 1) 0, transparent 50%), 
                            #f9fafb;
            <?php elseif ($theme_accent === 'violet'): ?>
                background: radial-gradient(at 0% 0%, hsla(265, 100%, 98%, 1) 0, transparent 50%), 
                            radial-gradient(at 100% 100%, hsla(260, 100%, 96%, 1) 0, transparent 50%), 
                            #fbfbfe;
            <?php endif; ?>
        }
        
        html.dark body {
            <?php if ($theme_accent === 'amber'): ?>
                background: radial-gradient(at 0% 0%, hsla(38, 50%, 10%, 1) 0, transparent 60%), 
                            radial-gradient(at 100% 100%, hsla(35, 50%, 9%, 1) 0, transparent 60%), 
                            #0c0a09;
            <?php elseif ($theme_accent === 'orange'): ?>
                background: radial-gradient(at 0% 0%, hsla(16, 50%, 10%, 1) 0, transparent 60%), 
                            radial-gradient(at 100% 100%, hsla(12, 50%, 9%, 1) 0, transparent 60%), 
                            #0c0a09;
            <?php elseif ($theme_accent === 'blue'): ?>
                background: radial-gradient(at 0% 0%, hsla(220, 40%, 12%, 1) 0, transparent 60%), 
                            radial-gradient(at 100% 100%, hsla(230, 40%, 11%, 1) 0, transparent 60%), 
                            #0b0f19;
            <?php elseif ($theme_accent === 'emerald'): ?>
                background: radial-gradient(at 0% 0%, hsla(150, 40%, 10%, 1) 0, transparent 60%), 
                            radial-gradient(at 100% 100%, hsla(140, 40%, 9%, 1) 0, transparent 60%), 
                            #052e16;
            <?php elseif ($theme_accent === 'rose'): ?>
                background: radial-gradient(at 0% 0%, hsla(350, 40%, 11%, 1) 0, transparent 60%), 
                            radial-gradient(at 100% 100%, hsla(345, 40%, 10%, 1) 0, transparent 60%), 
                            #0f0507;
            <?php elseif ($theme_accent === 'indigo'): ?>
                background: radial-gradient(at 0% 0%, hsla(230, 40%, 12%, 1) 0, transparent 60%), 
                            radial-gradient(at 100% 100%, hsla(225, 40%, 11%, 1) 0, transparent 60%), 
                            #09090b;
            <?php elseif ($theme_accent === 'violet'): ?>
                background: radial-gradient(at 0% 0%, hsla(265, 40%, 12%, 1) 0, transparent 60%), 
                            radial-gradient(at 100% 100%, hsla(260, 40%, 11%, 1) 0, transparent 60%), 
                            #09050d;
            <?php endif; ?>
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.45);
            box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.04);
        }

        html.dark .glass-card {
            background: rgba(28, 25, 23, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 12px 40px 0 rgba(0, 0, 0, 0.4);
        }

        .glass-input {
            background: rgba(255, 255, 255, 0.55) !important;
            border: 1px solid rgba(226, 232, 240, 0.7) !important;
            transition: all 0.2s ease !important;
        }

        html.dark .glass-input {
            background: rgba(28, 25, 23, 0.45) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #f5f5f4 !important;
        }

        .glass-input:focus {
            background: rgba(255, 255, 255, 0.85) !important;
            border-color: rgb(var(--primary-600, 217, 119, 6)) !important;
            box-shadow: 0 0 0 4px rgba(var(--primary-600, 217, 119, 6), 0.15) !important;
        }

        html.dark .glass-input:focus {
            background: rgba(28, 25, 23, 0.65) !important;
            border-color: rgb(var(--primary-500, 245, 158, 11)) !important;
            box-shadow: 0 0 0 4px rgba(var(--primary-500, 245, 158, 11), 0.15) !important;
        }
    </style>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full flex items-center justify-center p-4 text-slate-800 dark:text-stone-200 antialiased bg-slate-50 transition-colors duration-300">

    <div class="w-full max-w-md space-y-6">
        <!-- Logo and Heading -->
        <div class="flex flex-col items-center text-center space-y-3">
            <?php if ($has_logo): ?>
                <img class="h-16 w-16 rounded-2xl object-cover shadow-md ring-1 ring-slate-100 dark:ring-stone-800" src="view_image.php?type=logo" alt="Logo">
            <?php else: ?>
                <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-primary-600 to-primary-500 flex items-center justify-center text-white font-bold font-title text-3xl shadow-lg shadow-primary-500/20 shrink-0">
                    <?php echo substr(htmlspecialchars($org_name), 0, 1); ?>
                </div>
            <?php endif; ?>
            
            <div class="space-y-1">
                <h1 class="font-title font-extrabold text-2xl text-slate-900 dark:text-stone-50 leading-tight"><?php echo htmlspecialchars($org_name); ?></h1>
                <p class="text-xs font-bold text-slate-400 dark:text-stone-500 uppercase tracking-widest font-title">Community Trust Portal</p>
            </div>
        </div>

        <!-- Login Card -->
        <div class="glass-card rounded-2xl p-6 sm:p-8 shadow-xl border border-slate-200/50 dark:border-stone-800/40">
            <h2 class="font-title font-bold text-slate-900 dark:text-stone-100 text-lg mb-6">Access Account</h2>

            <!-- Error Banner -->
            <?php if (!empty($error)): ?>
                <div class="flex items-center gap-3 p-4 mb-5 bg-rose-50/80 dark:bg-rose-950/20 border border-rose-200/50 dark:border-rose-800/40 text-rose-800 dark:text-rose-350 rounded-xl text-xs font-medium transition-all duration-200">
                    <i data-lucide="alert-triangle" class="h-4.5 w-4.5 shrink-0 text-rose-600 dark:text-rose-450"></i>
                    <span class="flex-1 leading-normal"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-4.5">
                <!-- User ID -->
                <div class="space-y-1.5">
                    <label for="username" class="block text-[10px] font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">User ID</label>
                    <div class="relative">
                        <i data-lucide="user" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4.5 w-4.5 text-slate-400 dark:text-stone-500"></i>
                        <input type="text" id="username" name="username" required placeholder="Enter UserID" autofocus
                               class="w-full glass-input rounded-xl pl-12 pr-4 py-3 text-sm focus:outline-none transition-all placeholder-slate-400 dark:placeholder-stone-500">
                    </div>
                </div>

                <!-- Password -->
                <div class="space-y-1.5">
                    <label for="password" class="block text-[10px] font-bold text-slate-500 dark:text-stone-400 uppercase tracking-wider">Password</label>
                    <div class="relative">
                        <i data-lucide="lock" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4.5 w-4.5 text-slate-400 dark:text-stone-500"></i>
                        <input type="password" id="password" name="password" required placeholder="Enter password"
                               class="w-full glass-input rounded-xl pl-12 pr-4 py-3 text-sm focus:outline-none transition-all placeholder-slate-400 dark:placeholder-stone-500">
                    </div>
                </div>

                <!-- Login Button -->
                <div class="pt-2">
                    <button type="submit" class="w-full bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white text-sm py-3 px-4 rounded-xl font-bold transition-all shadow-md shadow-primary-500/10 flex items-center justify-center gap-1.5 transform hover:scale-[1.01]">
                        <i data-lucide="log-in" class="h-4.5 w-4.5"></i>
                        Sign In
                    </button>
                </div>
            </form>
        </div>

        <div class="text-center">
            <p class="text-[10px] text-slate-400 dark:text-stone-500 font-semibold tracking-wider uppercase">Community Donation Manager • v1.1.0</p>
        </div>
    </div>

    <script>
        // Initialize Lucide Icons
        lucide.createIcons();
    </script>
</body>
</html>
