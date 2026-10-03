<?php
require_once __DIR__.'/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    $role = strtolower((string)($_SESSION['role'] ?? 'user'));
    redirect_to($role === 'super_admin' ? 'admin/review_editor.php' : ($role === 'admin' ? 'admin/office_upload.php' : 'user/dashboard.php'));
}

$error = flash('error');
$success = flash('success');
clear_old();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $login = trim($_POST['username'] ?? '');
    $pw = (string)($_POST['password'] ?? '');

    if ($login === '' || $pw === '') {
        set_old(['username' => $login]);
        flash_redirect('auth/login.php', 'error', 'Please enter your username/email and password.');
    }

    if (str_contains($login, '@') && !filter_var($login, FILTER_VALIDATE_EMAIL)) {
        set_old(['username' => $login]);
        flash_redirect('auth/login.php', 'error', 'Enter a valid username or email address.');
    }

    try {
        // All account roles may sign in with either username or email.
        $q = db()->prepare('SELECT users.*, roles.role_name AS role, offices.office_name
            FROM users
            INNER JOIN roles ON roles.role_id = users.role_id
            LEFT JOIN offices ON offices.office_id = users.office_id
            WHERE users.username = ? OR users.email = ? LIMIT 1');
        $q->execute([$login, $login]);
        $user = $q->fetch();
    } catch (Throwable $e) {
        set_old(['username' => $login]);
        flash_redirect('auth/login.php', 'error', 'Unable to connect to the IRIS database. Check XAMPP MySQL and config/db.php.');
    }

    $validPassword = false;
    $needsRehash = false;

    if ($user) {
        $stored = (string)($user->password ?? '');
        $validPassword = password_verify($pw, $stored);

        // Compatibility with older local IRIS databases that stored passwords
        // before the current password_hash() implementation was introduced.
        if (!$validPassword && $stored !== '' && hash_equals($stored, $pw)) {
            $validPassword = true;
            $needsRehash = true;
        }
    }

    if (!$user || !$validPassword || (int)($user->is_active ?? 1) !== 1) {
        set_old(['username' => $login]);
        flash_redirect('auth/login.php', 'error', 'Invalid username/email or password.');
    }

    // Upgrade a legacy password to a secure password_hash() value after login.
    if ($needsRehash) {
        try {
            $update = db()->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            $update->execute([password_hash($pw, PASSWORD_DEFAULT), $user->user_id]);
        } catch (Throwable $e) {
            // Login can still continue if only the password upgrade fails.
        }
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user->user_id;
    $_SESSION['username'] = $user->username;
    $_SESSION['role'] = strtolower(trim((string)$user->role));

    $destination = $_SESSION['role'] === 'super_admin' ? 'admin/review_editor.php' : ($_SESSION['role'] === 'admin' ? 'admin/office_upload.php' : 'user/dashboard.php');
    flash_redirect($destination, 'success', 'Welcome back, '.$user->username.'!');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IRIS Institutional Observatory</title>
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
        .login-page { position: relative; isolation: isolate; overflow: hidden; background: #1E6031; }
        .login-page::before { content: ''; position: absolute; inset: 0; z-index: -1; background: linear-gradient(120deg, rgba(2, 6, 23, .76), rgba(30, 96, 49, .42)), radial-gradient(circle at 15% 20%, rgba(245, 158, 11, .22), transparent 32%), radial-gradient(circle at 85% 80%, rgba(16, 185, 129, .2), transparent 34%); }
        .login-page > * { position: relative; z-index: 1; }
        .bg-dots {
            background-image: radial-gradient(rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 18px 18px;
        }
    </style>
</head>
<body class="login-page min-h-screen flex items-center justify-center p-4 bg-dots">

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
                <h2 class="text-3xl font-extrabold leading-tight">A clearer view of<br>institutional performance.</h2>
                <p class="text-sm text-emerald-50/90 max-w-xs">CLSU Performance Observatory for the Office of International Affairs.</p>
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

            <p class="text-xs font-bold uppercase tracking-widest text-emerald-600 dark:text-emerald-400 mb-2">IRIS Login</p>
            <h1 class="text-2xl font-black text-gray-900 dark:text-white mb-1">Welcome back</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mb-6">Sign in to continue to your observatory.</p>

            <?php if ($success): ?>
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-xs font-medium text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200" role="status"><?= e($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="flex items-center p-3.5 mb-4 text-xs text-red-800 rounded-xl bg-red-50 dark:bg-red-500/10 dark:text-red-400 border border-red-200 dark:border-red-500/30" role="alert">
                    <i class="fa-solid fa-circle-exclamation text-base mr-2"></i>
                    <div class="font-medium"><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4" id="loginForm">
                <?= csrf_field() ?>
                <div>
                    <label for="username" class="block mb-2 text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-slate-300">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400 dark:text-slate-500">
                            <i class="fa-solid fa-user text-xs"></i>
                        </div>
                        <input type="text" id="username" name="username" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full pl-10 p-2.5 dark:bg-slate-800 dark:border-slate-700 dark:placeholder-slate-500 dark:text-white transition-colors" placeholder="faculty_user" required autofocus autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false" style="text-transform: none;">
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-slate-300">Password</label>
                        <button type="button" id="togglePassword" class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline">Show</button>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400 dark:text-slate-500">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </div>
                        <input type="password" id="password" name="password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full pl-10 p-2.5 dark:bg-slate-800 dark:border-slate-700 dark:placeholder-slate-500 dark:text-white transition-colors" placeholder="••••••••" required autocomplete="new-password" autocapitalize="none" autocorrect="off" spellcheck="false" style="text-transform: none;">
                    </div>
                </div>

                <button type="submit" class="w-full text-white bg-emerald-600 hover:bg-emerald-500 focus:ring-4 focus:ring-emerald-300 dark:focus:ring-emerald-800 font-bold rounded-xl text-sm px-5 py-3 text-center shadow-md shadow-emerald-600/20 transition-all disabled:opacity-60 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-right-to-bracket mr-2"></i> Log In
                </button>
            </form>

            <p class="text-xs text-center text-gray-500 dark:text-slate-400 mt-6">
                Need a normal user account? <a href="<?= e(base_url('auth/register.php')) ?>" class="font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Register</a>. New accounts require Super Admin activation.
            </p>
        </div>
    </div>

    <div id="privacyModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4">
        <div class="w-full max-w-2xl rounded-2xl border border-amber-300/40 bg-slate-900 text-slate-100 shadow-2xl shadow-slate-950/40">
            <div class="border-b border-slate-700 px-6 py-4 flex items-center justify-between">
                <h2 class="text-lg font-bold text-amber-300">Data Privacy Notice</h2>
                <button type="button" id="closePrivacyModal" class="text-slate-400 hover:text-white" aria-label="Close privacy notice">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto p-6 text-sm leading-relaxed text-slate-200">
                <p><strong>By logging in to IRIS, you acknowledge and agree to the collection, processing, and storage of your personal data in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173), its Implementing Rules and Regulations, and other issuances of the National Privacy Commission (NPC).</strong></p>

                <p class="mt-4 font-bold">1. Scope of the Notice</p>
                <p class="mt-2">This notice applies to all individuals who access or are recorded in IRIS, including:</p>
                <ul class="list-disc ml-5 mt-2 space-y-2">
                    <li>IAO staff and authorized CLSU personnel who log in to use the system.</li>
                    <li>CLSU officials and employees whose information appears in partnership, or performance records processed by the system.</li>
                    <li>Any other authorized user granted access credentials to IRIS.</li>
                </ul>

                <p class="mt-4 font-bold">2. Data We Collect</p>
                <ul class="list-disc ml-5 mt-2 space-y-2">
                    <li><strong>Login credentials</strong> — full name, institutional email address, and password, stored in encrypted or hashed form.</li>
                    <li><strong>International Affairs Office data</strong> — partnership records, ranking statistics, and other institutional metrics visualized on the dashboard.</li>
                </ul>

                <p class="mt-4 font-bold">3. Why We Collect It</p>
                <ul class="list-disc ml-5 mt-2 space-y-2">
                    <li>Authenticating and authorizing access to IRIS.</li>
                    <li>Monitoring and reporting on CLSU's international partnerships, rankings, and related performance metrics.</li>
                    <li>Generating dashboards and reports for internal administrative and decision-making use by the IAO.</li>
                    <li>Complying with institutional reporting requirements to CLSU administration and relevant government bodies.</li>
                </ul>

                <p class="mt-4 font-bold">4. Legal Basis for Processing</p>
                <p class="mt-2">Processing of personal data through IRIS is based on:</p>
                <ul class="list-disc ml-5 mt-2 space-y-2">
                    <li><strong>Consent</strong> — given by logging in and agreeing to this notice.</li>
                    <li><strong>Legitimate interest</strong> — CLSU's interest in managing institutional records and fulfilling its administrative and reporting functions.</li>
                </ul>

                <p class="mt-4 font-bold">5. Sharing and Disclosure</p>
                <p class="mt-2">Your data will not be shared, sold, or disclosed to third parties without your explicit consent, except:</p>
                <ul class="list-disc ml-5 mt-2 space-y-2">
                    <li>When required by law, court order, or a competent government authority.</li>
                    <li>When shared with authorized CLSU personnel strictly for the purposes stated in Section 4.</li>
                    <li>When necessary to protect the rights, property, or safety of CLSU, IRIS users, or the public.</li>
                </ul>

                <p class="mt-4 font-bold">6. Storage and Retention</p>
                <p class="mt-2">Your data is retained only for as long as necessary to fulfill the purposes stated in this notice, or as required by applicable university policy and law. Upon expiration of the retention period, data is securely disposed of, deleted, or anonymized in a manner that prevents recovery or reconstruction.</p>

                <p class="mt-4 font-bold">7. Security Measures</p>
                <p class="mt-2">CLSU implements reasonable organizational, physical, and technical safeguards to protect your personal data against unauthorized access, alteration, disclosure, accidental loss, or destruction. These include, but are not limited to:</p>
                <ul class="list-disc ml-5 mt-2 space-y-2">
                    <li>Encryption or hashing of stored credentials.</li>
                    <li>Access controls limiting data visibility to authorized personnel only.</li>
                </ul>
                <p class="mt-2">In the event of a data breach involving your personal data, CLSU will notify affected data subjects and the NPC in accordance with RA 10173 and its IRR, where required.</p>

                <p class="mt-4 font-bold">8. Your Rights as a Data Subject</p>
                <p class="mt-2">Under RA 10173, you have the right to:</p>
                <ul class="list-disc ml-5 mt-2 space-y-2">
                    <li>Be informed of how your data is processed.</li>
                    <li>Access your personal data held by the system.</li>
                    <li>Correct any inaccurate or outdated data.</li>
                    <li>Object to processing, subject to legal and contractual restrictions.</li>
                    <li>Request the deletion or blocking of your data under certain conditions.</li>
                    <li>Data portability, where applicable.</li>
                    <li>Be indemnified for damages sustained due to inaccurate, incomplete, outdated, false, unlawfully obtained, or unauthorized use of personal data.</li>
                    <li>File a complaint with the National Privacy Commission (NPC).</li>
                </ul>

                <p class="mt-4"><strong>By continuing to use IRIS, you confirm that you have read, understood, and agreed to the terms of this Data Privacy Notice.</strong></p>

                <label class="mt-5 flex items-start gap-3 rounded-xl border border-slate-700 bg-slate-800/70 px-3 py-3 text-sm text-slate-200">
                    <input id="privacyConsentCheckbox" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-500 bg-slate-900 text-emerald-500 focus:ring-emerald-500" />
                    <span>I agree to the terms and conditions of this Data Privacy Notice.</span>
                </label>
            </div>
            <div class="border-t border-slate-700 px-6 py-4 flex justify-end gap-3">
                <button type="button" id="declinePrivacy" class="rounded-xl border border-slate-600 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-800">Decline</button>
                <button type="button" id="acceptPrivacy" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed" disabled>I Agree</button>
            </div>
        </div>
    </div>

    <script>
        // ── Privacy-notice persistence ──────────────────────────────────
        // The acceptance flag is stored in localStorage so it survives
        // redirects (failed logins) and page reloads without re-showing
        // the modal or re-disabling the submit button.
        const PRIVACY_KEY = 'iris_privacy_accepted';

        const usernameInput  = document.getElementById('username');
        const privacyModal   = document.getElementById('privacyModal');
        const acceptPrivacy  = document.getElementById('acceptPrivacy');
        const declinePrivacy = document.getElementById('declinePrivacy');
        const closePrivacyModalBtn = document.getElementById('closePrivacyModal');
        const privacyConsentCheckbox = document.getElementById('privacyConsentCheckbox');
        const loginForm   = document.getElementById('loginForm');
        const submitButton = loginForm?.querySelector('button[type="submit"]');

        // Clear placeholder "admin" if accidentally filled in.
        if (usernameInput && usernameInput.value.trim().toLowerCase() === 'admin') {
            usernameInput.value = '';
        }

        // ── helpers ─────────────────────────────────────────────────────
        const hasAccepted = () => localStorage.getItem(PRIVACY_KEY) === '1';

        const updatePrivacyConsentState = () => {
            const isChecked = privacyConsentCheckbox?.checked;
            acceptPrivacy.disabled = !isChecked;
            acceptPrivacy.classList.toggle('opacity-50', !isChecked);
            acceptPrivacy.classList.toggle('cursor-not-allowed', !isChecked);
        };

        const openPrivacyModal = () => {
            privacyModal.classList.remove('hidden');
            privacyModal.classList.add('flex');
            updatePrivacyConsentState();
        };

        const closePrivacyModalFn = () => {
            privacyModal.classList.add('hidden');
            privacyModal.classList.remove('flex');
        };

        const setLoginBlocked = (blocked) => {
            if (!submitButton) return;
            submitButton.disabled = blocked;
            submitButton.classList.toggle('opacity-60', blocked);
            submitButton.classList.toggle('cursor-not-allowed', blocked);
        };

        // ── Initialise based on persisted state ─────────────────────────
        if (hasAccepted()) {
            // User already agreed — button active, modal stays hidden.
            setLoginBlocked(false);
        } else {
            // First visit — block the button and show the notice.
            setLoginBlocked(true);
            openPrivacyModal();
        }

        // ── Privacy-modal event handlers ────────────────────────────────
        privacyConsentCheckbox?.addEventListener('change', updatePrivacyConsentState);

        acceptPrivacy.addEventListener('click', () => {
            if (!privacyConsentCheckbox?.checked) return;
            localStorage.setItem(PRIVACY_KEY, '1');
            setLoginBlocked(false);
            closePrivacyModalFn();
        });

        declinePrivacy.addEventListener('click', () => {
            setLoginBlocked(true);
            closePrivacyModalFn();
            alert('You must agree to the data privacy notice before logging in.');
            usernameInput?.focus();
        });

        closePrivacyModalBtn.addEventListener('click', () => {
            // Closing without accepting keeps the user blocked only if
            // they have never accepted before.
            if (!hasAccepted()) setLoginBlocked(true);
            closePrivacyModalFn();
            usernameInput?.focus();
        });

        // ── Form submission ─────────────────────────────────────────────
        // Show per-field validation messages; prevent double-submit.
        loginForm.addEventListener('submit', (event) => {
            const login = (document.getElementById('username')?.value ?? '').trim();
            const pw    = document.getElementById('password')?.value ?? '';

            if (!hasAccepted()) {
                event.preventDefault();
                openPrivacyModal();
                return;
            }

            if (!login) {
                event.preventDefault();
                document.getElementById('username')?.focus();
                return;
            }

            if (!pw) {
                event.preventDefault();
                document.getElementById('password')?.focus();
                return;
            }

            // Prevent double-submit while the POST is in flight.
            if (submitButton && !submitButton.disabled) {
                submitButton.disabled = true;
                submitButton.classList.add('opacity-60', 'cursor-not-allowed');
                submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Signing in…';
            }
        });

        // ── Theme toggle ────────────────────────────────────────────────
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            const isDark = !document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', isDark);
            const mode = isDark ? 'dark' : 'light';
            localStorage.setItem('color-theme', mode);
            localStorage.setItem('iris-theme', mode);
        });

        // ── Password show/hide (login page only) ────────────────────────
        const toggleBtn    = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            toggleBtn.textContent = isPassword ? 'Hide' : 'Show';
        });
    </script>
</body>
</html>
