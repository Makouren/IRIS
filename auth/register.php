<?php
require_once __DIR__.'/../includes/functions.php';
$error=flash('error');clear_old();
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$u=trim($_POST['username']??'');$em=trim($_POST['email']??'');$pw=$_POST['password']??'';$cp=$_POST['confirm_password']??'';$bad='';if(!preg_match('/^[A-Za-z0-9_-]{1,50}$/',$u))$bad='Username may contain letters, numbers, underscores, and hyphens only.';elseif(!preg_match('/^[A-Za-z0-9._%+-]+@clsu2\.edu\.ph$/i',$em))$bad='Please use a valid email address in the format name@clsu2.edu.ph.';elseif(strlen($pw)<8||$pw!==$cp)$bad='Passwords must match and contain at least 8 characters.';else{$q=db()->prepare('SELECT id FROM users WHERE username=? OR email=?');$q->execute([$u,$em]);if($q->fetch())$bad='Username or email already exists.';}if($bad){set_old($_POST);flash_redirect('auth/register.php','error',$bad);} $q=db()->prepare('INSERT INTO users(username,email,password,role) VALUES(?,?,?,?)');$q->execute([$u,$em,password_hash($pw,PASSWORD_DEFAULT),'user']);flash_redirect('auth/login.php','success','Account created successfully! You can now log in.');}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - IRIS Observatory</title>
    <script>
        (function() {
            const saved = localStorage.getItem('color-theme') || localStorage.getItem('iris-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = saved ? saved === 'dark' : prefersDark;
            if (isDark) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#053a2c',
                            950: '#04261d',
                            gold: '#f59e0b'
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(base_url('scanner/css/tokens.css')) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .register-page { position: relative; isolation: isolate; overflow: hidden; background: #1E6031; }
        .register-page::before { content: ''; position: absolute; inset: 0; z-index: -1; background: linear-gradient(120deg, rgba(2, 6, 23, .76), rgba(30, 96, 49, .42)), radial-gradient(circle at 15% 20%, rgba(245, 158, 11, .22), transparent 32%), radial-gradient(circle at 85% 80%, rgba(16, 185, 129, .2), transparent 34%); }
        .register-page > * { position: relative; z-index: 1; }
        .bg-dots {
            background-image: radial-gradient(rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 18px 18px;
        }
    </style>
</head>
<body class="register-page min-h-screen flex items-center justify-center p-4 bg-dots">

    <div class="w-full max-w-4xl grid md:grid-cols-2 rounded-3xl overflow-hidden shadow-2xl border border-white/10">

        <!-- Left brand panel -->
        <div class="hidden md:flex flex-col justify-between bg-gradient-to-br from-brand-600 to-brand-800 dark:from-brand-900 dark:to-brand-950 p-10 text-white relative overflow-hidden transition-colors">
            <div class="absolute inset-0 bg-dots opacity-40 pointer-events-none"></div>
            <div class="relative">
               <div class="inline-flex w-40 h-40 rounded-2xl items-center justify-center p-1 mb-8 shadow-lg bg-white">
               <img src="<?= e(base_url('images/iris-logo.png')) ?>" alt="IRIS Logo" class="w-full h-full object-contain">
            </div>
            </div>
            <div class="relative space-y-3">
                <h2 class="text-3xl font-extrabold leading-tight">Join the<br>performance observatory.</h2>
                <p class="text-sm text-emerald-50/90 max-w-xs">Create an account to explore CLSU institutional rankings and performance data.</p>
            </div>
            <div class="relative text-xs text-emerald-100/70 pt-8">
                International Affairs Office &middot; Central Luzon State University
            </div>
        </div>

        <!-- Right form panel -->
        <div class="bg-white dark:bg-slate-900 p-8 sm:p-10 flex flex-col justify-center relative transition-colors">
            <button id="themeToggle" type="button" class="absolute top-6 right-6 w-9 h-9 rounded-full flex items-center justify-center bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-amber-300 hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors">
                <i class="fa-solid fa-sun text-sm hidden dark:inline"></i>
                <i class="fa-solid fa-moon text-sm dark:hidden"></i>
            </button>

            <p class="text-xs font-bold uppercase tracking-widest text-emerald-600 dark:text-emerald-400 mb-2">IRIS Registration</p>
            <h1 class="text-2xl font-black text-gray-900 dark:text-white mb-1">Create an account</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mb-6">Set up your access to the observatory.</p>

            <?php if ($error): ?>
                <div class="flex items-center p-3.5 mb-4 text-xs text-red-800 rounded-xl bg-red-50 dark:bg-red-500/10 dark:text-red-400 border border-red-200 dark:border-red-500/30" role="alert">
                    <i class="fa-solid fa-circle-exclamation text-base mr-2"></i>
                    <div class="font-medium"><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label for="username" class="block mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-slate-300">Username</label>
                    <input type="text" id="username" name="username" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 dark:bg-slate-800 dark:border-slate-700 dark:placeholder-slate-500 dark:text-white transition-colors" placeholder="faculty_user" required autofocus>
                </div>

                <div>
                    <label for="email" class="block mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-slate-300">Email</label>
                    <input type="email" id="email" name="email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 dark:bg-slate-800 dark:border-slate-700 dark:placeholder-slate-500 dark:text-white transition-colors" placeholder="name@clsu2.edu.ph" pattern="[A-Za-z0-9._%+-]+@clsu2\.edu\.ph" title="Use the format name@clsu2.edu.ph" required>
                    <p id="email-hint" class="mt-1.5 text-[11px] text-gray-500 dark:text-slate-400">Accepted format: <span class="font-semibold text-emerald-600 dark:text-emerald-400">name@clsu2.edu.ph</span></p>
                </div>

                <div>
                    <label for="password" class="block mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-slate-300">Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full pr-10 p-2.5 dark:bg-slate-800 dark:border-slate-700 dark:placeholder-slate-500 dark:text-white transition-colors" placeholder="At least 8 characters" minlength="8" required autocomplete="new-password">
                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-700 dark:text-slate-500 dark:hover:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 rounded-r-xl" aria-label="Show password" aria-pressed="false">
                            <i class="fa-solid fa-eye text-sm" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="confirm_password" class="block mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-slate-300">Confirm Password</label>
                    <div class="relative">
                        <input type="password" id="confirm_password" name="confirm_password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full pr-10 p-2.5 dark:bg-slate-800 dark:border-slate-700 dark:placeholder-slate-500 dark:text-white transition-colors" placeholder="Re-enter your password" required autocomplete="new-password">
                        <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-700 dark:text-slate-500 dark:hover:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 rounded-r-xl" aria-label="Show confirm password" aria-pressed="false">
                            <i class="fa-solid fa-eye text-sm" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full text-white bg-emerald-600 hover:bg-emerald-500 focus:ring-4 focus:ring-emerald-300 dark:focus:ring-emerald-800 font-bold rounded-xl text-sm px-5 py-3 text-center shadow-md shadow-emerald-600/20 transition-all mt-2">
                    <i class="fa-solid fa-user-plus mr-2"></i> Register
                </button>
            </form>

            <p class="text-xs text-center text-gray-500 dark:text-slate-400 mt-6">
                Already have an account? <a href="<?= e(base_url('auth/login.php')) ?>" class="font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Log in</a>
            </p>
        </div>
    </div>

    <script>
        // ── Theme toggle ────────────────────────────────────────────────
        const themeToggle = document.getElementById('themeToggle');
        const emailInput  = document.getElementById('email');
        const emailHint   = document.getElementById('email-hint');

        themeToggle.addEventListener('click', () => {
            const isDark = !document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', isDark);
            const mode = isDark ? 'dark' : 'light';
            localStorage.setItem('color-theme', mode);
            localStorage.setItem('iris-theme', mode);
        });

        // ── Email hint ──────────────────────────────────────────────────
        emailInput.addEventListener('input', () => {
            const value = emailInput.value.trim();
            const valid = /^[A-Za-z0-9._%+\-]+@clsu2\.edu\.ph$/i.test(value);
            emailHint.classList.toggle('text-red-600', value && !valid);
            emailHint.classList.toggle('dark:text-red-400', value && !valid);
            emailHint.classList.toggle('text-emerald-600', valid);
            emailHint.classList.toggle('dark:text-emerald-400', valid);
            emailHint.innerHTML = value
                ? (valid ? 'Looks good: <span class="font-semibold">' + value + '</span>' : 'Invalid format. Use: <span class="font-semibold">name@clsu2.edu.ph</span>')
                : 'Accepted format: <span class="font-semibold text-emerald-600 dark:text-emerald-400">name@clsu2.edu.ph</span>';
        });

        // ── Show/hide password toggles ──────────────────────────────────
        function makePasswordToggle(inputId, buttonId) {
            const input  = document.getElementById(inputId);
            const button = document.getElementById(buttonId);
            if (!input || !button) return;
            const icon = button.querySelector('i');

            button.addEventListener('click', () => {
                const showing = input.type === 'text';
                // Preserve caret position across the type switch.
                const selStart = input.selectionStart;
                const selEnd   = input.selectionEnd;
                input.type = showing ? 'password' : 'text';
                // Restore selection (works in most browsers after type change).
                try { input.setSelectionRange(selStart, selEnd); } catch (_) {}

                button.setAttribute('aria-pressed', String(!showing));
                button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                if (icon) {
                    icon.classList.toggle('fa-eye',        showing);
                    icon.classList.toggle('fa-eye-slash', !showing);
                }
            });
        }

        makePasswordToggle('password',         'togglePassword');
        makePasswordToggle('confirm_password', 'toggleConfirmPassword');

        // Reset password fields to hidden when the form submits or the
        // browser restores the page from the back-forward cache.
        const registerForm = document.querySelector('form');
        const resetPasswordVisibility = () => {
            ['password', 'confirm_password'].forEach(id => {
                const input  = document.getElementById(id);
                const button = document.getElementById(id === 'password' ? 'togglePassword' : 'toggleConfirmPassword');
                if (!input || !button) return;
                input.type = 'password';
                button.setAttribute('aria-pressed', 'false');
                button.setAttribute('aria-label', id === 'password' ? 'Show password' : 'Show confirm password');
                const icon = button.querySelector('i');
                if (icon) { icon.classList.add('fa-eye'); icon.classList.remove('fa-eye-slash'); }
            });
        };
        registerForm?.addEventListener('submit', resetPasswordVisibility);
        window.addEventListener('pageshow', (event) => { if (event.persisted) resetPasswordVisibility(); });
    </script>
</body>
</html>