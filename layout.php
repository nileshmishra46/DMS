<?php
// layout.php
// Shared premium responsive layout wrapper

require_once __DIR__ . '/db.php';

function render_header($title = 'Community Donation Manager', $current_page = 'dashboard') {
    $db = getDb();
    $org = $db->query("SELECT * FROM settings WHERE id = 'global_config'")->fetch();
    $org_name = $org['community_name'] ?? 'Community Trust';
    $has_logo = !empty($org['logo_data']);
    
    // Sidebar links
    $menu_items = [
        'dashboard' => ['label' => 'Dashboard', 'url' => 'index.php', 'icon' => 'layout-dashboard'],
        'donations' => ['label' => 'Donations', 'url' => 'donations.php', 'icon' => 'heart-handshake'],
        'expenses' => ['label' => 'Expenses', 'url' => 'expenses.php', 'icon' => 'receipt'],
        'reports' => ['label' => 'Reports', 'url' => 'reports.php', 'icon' => 'line-chart'],
        'settings' => ['label' => 'Settings', 'url' => 'settings.php', 'icon' => 'settings']
    ];
    
    ?>
    <?php
    $theme_mode = $org['theme_mode'] ?? 'light';
    $theme_accent = $org['theme_accent'] ?? 'amber';
    
    // Dynamic html class resolution
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
        <title><?php echo htmlspecialchars($title); ?> - <?php echo htmlspecialchars($org_name); ?></title>
        <!-- Manifest and Theme for PWA -->
        <link rel="manifest" href="manifest.json">
        <meta name="theme-color" content="<?php echo $theme_mode === 'dark' ? '#0c0a09' : '#f59e0b'; ?>">
        <link rel="apple-touch-icon" href="public/icon-192.png">
        <!-- Fonts & CSS -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            // Resolve Theme Mode class immediately before layout render
            const activeThemeMode = "<?php echo $theme_mode; ?>";
            if (activeThemeMode === 'dark' || (activeThemeMode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }

            // Theme Configuration
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
                            sans: ['Plus Jakarta Sans', 'Inter', 'sans-serif'],
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
                transition: background 0.3s ease, color 0.3s ease;
                <?php if ($theme_accent === 'amber'): ?>
                    background: radial-gradient(at 0% 0%, hsla(38, 100%, 98%, 1) 0, transparent 40%), 
                                radial-gradient(at 50% 0%, hsla(45, 100%, 97%, 1) 0, transparent 50%), 
                                radial-gradient(at 100% 0%, hsla(35, 100%, 96%, 1) 0, transparent 40%), 
                                #fffbeb;
                <?php elseif ($theme_accent === 'orange'): ?>
                    background: radial-gradient(at 0% 0%, hsla(16, 100%, 98%, 1) 0, transparent 40%), 
                                radial-gradient(at 50% 0%, hsla(24, 100%, 97%, 1) 0, transparent 50%), 
                                radial-gradient(at 100% 0%, hsla(12, 100%, 96%, 1) 0, transparent 40%), 
                                #fff8f5;
                <?php elseif ($theme_accent === 'blue'): ?>
                    background: radial-gradient(at 0% 0%, hsla(215, 100%, 98%, 1) 0, transparent 40%), 
                                radial-gradient(at 50% 0%, hsla(220, 100%, 97%, 1) 0, transparent 50%), 
                                radial-gradient(at 100% 0%, hsla(210, 100%, 96%, 1) 0, transparent 40%), 
                                #f8fafc;
                <?php elseif ($theme_accent === 'emerald'): ?>
                    background: radial-gradient(at 0% 0%, hsla(150, 80%, 98%, 1) 0, transparent 40%), 
                                radial-gradient(at 50% 0%, hsla(160, 80%, 97%, 1) 0, transparent 50%), 
                                radial-gradient(at 100% 0%, hsla(140, 80%, 96%, 1) 0, transparent 40%), 
                                #f7fee7;
                <?php elseif ($theme_accent === 'rose'): ?>
                    background: radial-gradient(at 0% 0%, hsla(350, 100%, 99%, 1) 0, transparent 40%), 
                                radial-gradient(at 50% 0%, hsla(355, 100%, 98%, 1) 0, transparent 50%), 
                                radial-gradient(at 100% 0%, hsla(345, 100%, 97%, 1) 0, transparent 40%), 
                                #fff1f2;
                <?php elseif ($theme_accent === 'indigo'): ?>
                    background: radial-gradient(at 0% 0%, hsla(230, 100%, 98%, 1) 0, transparent 40%), 
                                radial-gradient(at 50% 0%, hsla(235, 100%, 97%, 1) 0, transparent 50%), 
                                radial-gradient(at 100% 0%, hsla(225, 100%, 96%, 1) 0, transparent 40%), 
                                #f9fafb;
                <?php elseif ($theme_accent === 'violet'): ?>
                    background: radial-gradient(at 0% 0%, hsla(265, 100%, 98%, 1) 0, transparent 40%), 
                                radial-gradient(at 50% 0%, hsla(270, 100%, 97%, 1) 0, transparent 50%), 
                                radial-gradient(at 100% 0%, hsla(260, 100%, 96%, 1) 0, transparent 40%), 
                                #fbfbfe;
                <?php endif; ?>
            }
            .font-title {
                font-family: 'Outfit', sans-serif;
            }
            /* Premium Glassmorphic Cards */
            .glass-card {
                background: rgba(255, 255, 255, 0.55);
                backdrop-filter: blur(18px) saturate(180%);
                -webkit-backdrop-filter: blur(18px) saturate(180%);
                border: 1px solid rgba(255, 255, 255, 0.45);
                box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.02);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .glass-card-hover:hover {
                background: rgba(255, 255, 255, 0.75);
                box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.05);
                transform: translateY(-2px);
                border-color: rgba(255, 255, 255, 0.7);
            }
            
            /* Glass Form Inputs */
            .glass-input {
                background: rgba(255, 255, 255, 0.55) !important;
                border: 1px solid rgba(226, 232, 240, 0.7) !important;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
                color: #1c1917 !important;
            }
            .glass-input:focus {
                background: rgba(255, 255, 255, 0.85) !important;
                border-color: rgb(var(--primary-600, 217, 119, 6)) !important;
                box-shadow: 0 0 0 4px rgba(var(--primary-600, 217, 119, 6), 0.15) !important;
            }

            /* Custom scrollbar */
            ::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }
            ::-webkit-scrollbar-track {
                background: transparent;
            }
            ::-webkit-scrollbar-thumb {
                background: #d6d3d1;
                border-radius: 4px;
            }
            ::-webkit-scrollbar-thumb:hover {
                background: #a8a29e;
            }
            
            /* Fade-in-up Entry Animation */
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(12px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .animate-fade-in-up {
                animation: fadeInUp 0.45s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }

            /* Dark Mode Styling Overrides */
            html.dark body {
                color: #e7e5e4;
                <?php if ($theme_accent === 'amber'): ?>
                    background: radial-gradient(at 0% 0%, hsla(38, 50%, 10%, 1) 0, transparent 50%), 
                                radial-gradient(at 50% 0%, hsla(45, 40%, 8%, 1) 0, transparent 55%), 
                                radial-gradient(at 100% 0%, hsla(35, 50%, 9%, 1) 0, transparent 50%), 
                                #0c0a09;
                <?php elseif ($theme_accent === 'orange'): ?>
                    background: radial-gradient(at 0% 0%, hsla(16, 50%, 10%, 1) 0, transparent 50%), 
                                radial-gradient(at 50% 0%, hsla(24, 40%, 8%, 1) 0, transparent 55%), 
                                radial-gradient(at 100% 0%, hsla(12, 50%, 9%, 1) 0, transparent 50%), 
                                #0c0a09;
                <?php elseif ($theme_accent === 'blue'): ?>
                    background: radial-gradient(at 0% 0%, hsla(220, 40%, 12%, 1) 0, transparent 50%), 
                                radial-gradient(at 50% 0%, hsla(240, 30%, 10%, 1) 0, transparent 55%), 
                                radial-gradient(at 100% 0%, hsla(230, 40%, 11%, 1) 0, transparent 50%), 
                                #0b0f19;
                <?php elseif ($theme_accent === 'emerald'): ?>
                    background: radial-gradient(at 0% 0%, hsla(150, 40%, 10%, 1) 0, transparent 50%), 
                                radial-gradient(at 50% 0%, hsla(160, 30%, 8%, 1) 0, transparent 55%), 
                                radial-gradient(at 100% 0%, hsla(140, 40%, 9%, 1) 0, transparent 50%), 
                                #052e16;
                <?php elseif ($theme_accent === 'rose'): ?>
                    background: radial-gradient(at 0% 0%, hsla(350, 40%, 11%, 1) 0, transparent 50%), 
                                radial-gradient(at 50% 0%, hsla(355, 30%, 9%, 1) 0, transparent 55%), 
                                radial-gradient(at 100% 0%, hsla(345, 40%, 10%, 1) 0, transparent 50%), 
                                #0f0507;
                <?php elseif ($theme_accent === 'indigo'): ?>
                    background: radial-gradient(at 0% 0%, hsla(230, 40%, 12%, 1) 0, transparent 50%), 
                                radial-gradient(at 50% 0%, hsla(235, 30%, 10%, 1) 0, transparent 55%), 
                                radial-gradient(at 100% 0%, hsla(225, 40%, 11%, 1) 0, transparent 50%), 
                                #09090b;
                <?php elseif ($theme_accent === 'violet'): ?>
                    background: radial-gradient(at 0% 0%, hsla(265, 40%, 12%, 1) 0, transparent 50%), 
                                radial-gradient(at 50% 0%, hsla(270, 30%, 10%, 1) 0, transparent 55%), 
                                radial-gradient(at 100% 0%, hsla(260, 40%, 11%, 1) 0, transparent 50%), 
                                #09050d;
                <?php endif; ?>
            }

            html.dark .glass-card {
                background: rgba(28, 25, 23, 0.45);
                border: 1px solid rgba(255, 255, 255, 0.08);
                box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            }
            html.dark .glass-card-hover:hover {
                background: rgba(28, 25, 23, 0.6);
                border-color: rgba(255, 255, 255, 0.15);
                box-shadow: 0 12px 40px 0 rgba(0, 0, 0, 0.50);
            }
            
            html.dark .glass-input {
                background: rgba(28, 25, 23, 0.45) !important;
                border: 1px solid rgba(255, 255, 255, 0.1) !important;
                color: #f5f5f4 !important;
            }
            html.dark .glass-input:focus {
                background: rgba(28, 25, 23, 0.65) !important;
                border-color: rgb(var(--primary-500, 245, 158, 11)) !important;
                box-shadow: 0 0 0 4px rgba(var(--primary-500, 245, 158, 11), 0.15) !important;
            }
            html.dark ::-webkit-scrollbar-thumb {
                background: #44403c;
            }
            html.dark ::-webkit-scrollbar-thumb:hover {
                background: #57534e;
            }
        </style>
        <!-- Lucide Icons -->
        <script src="https://unpkg.com/lucide@latest"></script>
    </head>
    <body class="h-full flex flex-col md:flex-row text-slate-800 dark:text-stone-200 antialiased overflow-hidden bg-slate-50 transition-colors duration-300">
        
        <!-- Mobile Header / Top Nav -->
        <header class="md:hidden flex items-center justify-between bg-white/70 dark:bg-stone-900/75 backdrop-blur-xl border-b border-slate-200/50 dark:border-stone-800/40 px-4 py-3 shrink-0 w-full z-30">
            <div class="flex items-center gap-3">
                <?php if ($has_logo): ?>
                    <img class="h-8 w-8 rounded-lg object-cover" src="view_image.php?type=logo" alt="Logo">
                <?php else: ?>
                    <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-primary-600 to-primary-500 flex items-center justify-center text-white font-bold font-title">
                        <?php echo substr(htmlspecialchars($org_name), 0, 1); ?>
                    </div>
                <?php endif; ?>
                <span class="font-title font-extrabold text-sm bg-gradient-to-r from-primary-600 to-primary-500 dark:from-primary-400 dark:to-primary-300 bg-clip-text text-transparent tracking-tight max-w-[200px] truncate"><?php echo htmlspecialchars($org_name); ?></span>
            </div>
            <button id="mobile-menu-btn" class="p-1.5 rounded-lg border border-slate-200 dark:border-stone-700 text-slate-600 dark:text-stone-300 hover:bg-slate-50 dark:hover:bg-stone-800 focus:outline-none">
                <i data-lucide="menu" class="h-6 w-6"></i>
            </button>
        </header>

        <!-- Mobile Drawer Overlay -->
        <div id="mobile-menu-overlay" class="fixed inset-0 bg-slate-900/40 dark:bg-stone-950/60 opacity-0 pointer-events-none transition-opacity duration-300 z-40 md:hidden"></div>

        <!-- Mobile Drawer Navigation -->
        <aside id="mobile-drawer" class="fixed inset-y-0 left-0 w-72 bg-white/95 dark:bg-stone-900/95 backdrop-blur-xl border-r border-slate-200/50 dark:border-stone-800/50 flex flex-col transform -translate-x-full transition-transform duration-300 ease-in-out z-50 md:hidden">
            <div class="flex items-center justify-between p-4 border-b border-slate-100 dark:border-stone-800">
                <div class="flex items-center gap-3">
                    <?php if ($has_logo): ?>
                        <img class="h-8 w-8 rounded-lg object-cover" src="view_image.php?type=logo" alt="Logo">
                    <?php else: ?>
                        <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-primary-600 to-primary-500 flex items-center justify-center text-white font-bold font-title">
                            <?php echo substr(htmlspecialchars($org_name), 0, 1); ?>
                        </div>
                    <?php endif; ?>
                    <span class="font-title font-extrabold text-base bg-gradient-to-r from-primary-600 to-primary-500 dark:from-primary-400 dark:to-primary-300 bg-clip-text text-transparent tracking-tight max-w-[150px] truncate"><?php echo htmlspecialchars($org_name); ?></span>
                </div>
                <button id="mobile-menu-close" class="p-1.5 rounded-lg border border-slate-200 dark:border-stone-700 text-slate-600 dark:text-stone-300 hover:bg-slate-50 dark:hover:bg-stone-800">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1">
                <?php foreach ($menu_items as $key => $item): 
                    $is_active = ($current_page === $key);
                    $active_class = $is_active 
                        ? 'bg-primary-50 dark:bg-primary-950/45 text-primary-600 dark:text-primary-400 font-semibold shadow-sm' 
                        : 'text-slate-600 dark:text-stone-300 hover:bg-slate-50 dark:hover:bg-stone-800 hover:text-slate-900 dark:hover:text-stone-100';
                ?>
                    <a href="<?php echo $item['url']; ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200 <?php echo $active_class; ?>">
                        <i data-lucide="<?php echo $item['icon']; ?>" class="h-5 w-5 <?php echo $is_active ? 'text-primary-600 dark:text-primary-400' : 'text-slate-400 group-hover:text-slate-500'; ?>"></i>
                        <?php echo $item['label']; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="p-4 border-t border-slate-100 dark:border-stone-800 bg-slate-50 dark:bg-stone-900/50 flex flex-col items-center justify-center">
                <p class="text-xs text-slate-400 text-center font-medium">Community Donation Manager</p>
                <p class="text-[10px] text-slate-400 text-center">Version 1.1.0 (PHP + SQLite)</p>
            </div>
        </aside>

        <!-- Desktop Sidebar Navigation -->
        <aside class="hidden md:flex md:w-64 md:flex-col bg-white/65 dark:bg-stone-900/65 backdrop-blur-2xl border-r border-slate-200/40 dark:border-stone-800/40 h-full shrink-0 z-30 shadow-sm transition-colors duration-300">
            <!-- Sidebar Header -->
            <div class="flex items-center gap-3 px-6 py-5 border-b border-slate-100 dark:border-stone-800 shrink-0">
                <?php if ($has_logo): ?>
                    <img class="h-10 w-10 rounded-xl object-cover shadow-sm ring-1 ring-slate-100 dark:ring-stone-800" src="view_image.php?type=logo" alt="Logo">
                <?php else: ?>
                    <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-primary-600 to-primary-500 flex items-center justify-center text-white font-bold font-title text-xl shadow-md shadow-primary-500/10 shrink-0">
                        <?php echo substr(htmlspecialchars($org_name), 0, 1); ?>
                    </div>
                <?php endif; ?>
                <div class="flex flex-col min-w-0">
                    <span class="font-title font-extrabold text-sm bg-gradient-to-r from-primary-600 to-primary-500 dark:from-primary-400 dark:to-primary-300 bg-clip-text text-transparent leading-tight truncate"><?php echo htmlspecialchars($org_name); ?></span>
                    <span class="text-[10px] font-bold text-slate-400 dark:text-stone-500 uppercase tracking-widest mt-0.5">Community Trust</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
                <?php foreach ($menu_items as $key => $item): 
                    $is_active = ($current_page === $key);
                    $active_class = $is_active 
                        ? 'bg-gradient-to-r from-primary-600 to-primary-500 text-white shadow-lg shadow-primary-500/15 font-semibold hover:scale-[1.02] transform transition-all duration-200' 
                        : 'text-slate-600 dark:text-stone-300 hover:bg-white/60 dark:hover:bg-stone-800/50 hover:text-slate-900 dark:hover:text-stone-100 transition-all duration-250';
                ?>
                    <a href="<?php echo $item['url']; ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-all duration-200 group <?php echo $active_class; ?>">
                        <i data-lucide="<?php echo $item['icon']; ?>" class="h-5 w-5 <?php echo $is_active ? 'text-white' : 'text-slate-400 dark:text-stone-500 group-hover:text-slate-600 dark:group-hover:text-stone-300'; ?>"></i>
                        <span><?php echo $item['label']; ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Sidebar Footer -->
            <div class="p-4 border-t border-slate-100 dark:border-stone-800 bg-slate-50/50 dark:bg-stone-900/30 shrink-0">
                <div class="flex items-center justify-between text-xs text-slate-400 dark:text-stone-500 font-medium">
                    <span>Active FY: <?php echo htmlspecialchars(getFinancialYear(date('Y-m-d'))); ?></span>
                    <span class="bg-primary-100 dark:bg-primary-950/70 text-primary-700 dark:text-primary-400 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">Active</span>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-transparent">
            <!-- Top bar -->
            <div class="hidden md:flex items-center justify-between px-8 py-4 bg-white/55 dark:bg-stone-900/55 backdrop-blur-md border-b border-slate-200/40 dark:border-stone-800/40 shrink-0">
                <h1 class="text-lg font-bold font-title text-slate-900 dark:text-stone-100"><?php echo htmlspecialchars($title); ?></h1>
                <div class="flex items-center gap-4">
                    <span class="text-xs text-slate-400 dark:text-stone-500 font-medium bg-white/80 dark:bg-stone-900/80 border border-slate-200/50 dark:border-stone-850/50 px-3 py-1.5 rounded-full shadow-sm">
                        <?php echo date('l, d M Y'); ?>
                    </span>
                </div>
            </div>

            <!-- Page Body Content (Scrollable) -->
            <div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6 animate-fade-in-up">
    <?php
}

function render_footer() {
    ?>
            </div>
        </main>

        <!-- PWA Installation Toast -->
        <div id="pwa-install-banner" class="hidden fixed bottom-4 right-4 bg-white border border-slate-200 rounded-2xl shadow-xl p-4 max-w-sm z-50 flex items-start gap-3 border-l-4 border-l-blue-600">
            <div class="bg-blue-50 text-blue-600 p-2 rounded-xl">
                <i data-lucide="download" class="h-6 w-6"></i>
            </div>
            <div class="flex-1">
                <h4 class="font-bold text-slate-900 text-sm">Install App</h4>
                <p class="text-xs text-slate-500 mt-1">Add to your home screen for quick, offline financial access.</p>
                <div class="flex gap-2 mt-3">
                    <button id="pwa-install-btn" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1.5 rounded-lg font-bold">Install</button>
                    <button id="pwa-dismiss-btn" class="text-slate-500 hover:bg-slate-100 text-xs px-3 py-1.5 rounded-lg font-medium">Not now</button>
                </div>
            </div>
        </div>

        <script>
            // Mobile Menu Drawer Control
            const menuBtn = document.getElementById('mobile-menu-btn');
            const menuClose = document.getElementById('mobile-menu-close');
            const menuOverlay = document.getElementById('mobile-menu-overlay');
            const mobileDrawer = document.getElementById('mobile-drawer');

            function openMobileMenu() {
                mobileDrawer.classList.remove('-translate-x-full');
                menuOverlay.classList.remove('opacity-0', 'pointer-events-none');
            }

            function closeMobileMenu() {
                mobileDrawer.classList.add('-translate-x-full');
                menuOverlay.classList.add('opacity-0', 'pointer-events-none');
            }

            if (menuBtn) menuBtn.addEventListener('click', openMobileMenu);
            if (menuClose) menuClose.addEventListener('click', closeMobileMenu);
            if (menuOverlay) menuOverlay.addEventListener('click', closeMobileMenu);

            // Initialize Lucide Icons
            lucide.createIcons();

            // PWA Service Worker Registration
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('sw.js')
                        .then(reg => console.log('ServiceWorker registered:', reg.scope))
                        .catch(err => console.error('ServiceWorker failed:', err));
                });
            }

            // PWA Install Prompt handling
            let deferredPrompt;
            const pwaBanner = document.getElementById('pwa-install-banner');
            const pwaInstallBtn = document.getElementById('pwa-install-btn');
            const pwaDismissBtn = document.getElementById('pwa-dismiss-btn');

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                if (pwaBanner && !localStorage.getItem('pwa-dismissed')) {
                    pwaBanner.classList.remove('hidden');
                }
            });

            if (pwaInstallBtn) {
                pwaInstallBtn.addEventListener('click', () => {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        deferredPrompt.userChoice.then((choiceResult) => {
                            if (choiceResult.outcome === 'accepted') {
                                console.log('PWA installation accepted');
                            }
                            deferredPrompt = null;
                            pwaBanner.classList.add('hidden');
                        });
                    }
                });
            }

            if (pwaDismissBtn) {
                pwaDismissBtn.addEventListener('click', () => {
                    pwaBanner.classList.add('hidden');
                    localStorage.setItem('pwa-dismissed', 'true');
                });
            }
        </script>
    </body>
    </html>
    <?php
}
?>
