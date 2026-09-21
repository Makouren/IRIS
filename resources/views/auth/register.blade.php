<?php
$error = $errors->first();
$success = session('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - IRIS Observatory</title>
    <script>
        if (localStorage.getItem('iris-theme') === 'dark' ||
            (!localStorage.getItem('iris-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dots {
            background-image: radial-gradient(rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 18px 18px;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-brand-950 via-brand-900 to-brand-800 bg-dots">

    <div class="w-full max-w-4xl grid md:grid-cols-2 rounded-3xl overflow-hidden shadow-2xl border border-white/10">

        <!-- Left brand panel -->
        <div class="hidden md:flex flex-col justify-between bg-gradient-to-br from-brand-600 to-brand-800 dark:from-brand-900 dark:to-brand-950 p-10 text-white relative overflow-hidden transition-colors">
            <div class="absolute inset-0 bg-dots opacity-40 pointer-events-none"></div>
            <div class="relative">
               <div class="inline-flex w-40 h-40 rounded-2xl items-center justify-center p-1 mb-8 shadow-lg bg-white">
               <img src="{{ asset('images/iris-logo.png') }}" alt="IRIS Logo" class="w-full h-full object-contain">
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

            <?php if ($success): ?>
                <div class="flex items-center p-3.5 mb-4 text-xs text-emerald-800 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30" role="alert">
                    <i class="fa-solid fa-circle-check text-base mr-2"></i>
                    <div class="font-medium"><?= htmlspecialchars($success) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="username" class="block mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-slate-300">Username</label>
                    <input type="text" id="username" name="username" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 dark:bg-slate-800 dark:border-slate-700 dark:placeholder-slate-500 dark:text-white transition-colors" placeholder="faculty_user" required autofocus>
                </div>

                <div>
                    <label for="email" class="block mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-slate-300">Email</label>
                    <input type="email" id="email" name="email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 dark:bg-slate-800 dark:border-slate-700 dark:placeholder-slate-500 dark:text-white transition-colors" placeholder="name@clsu.edu.ph" required>
                </div>

                <div>
                    <label for="password" class="block mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-slate-300">Password</label>
                    <input type="password" id="password" name="password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 dark:bg-slate-800 dark:border-slate-700 dark:placeholder-slate-500 dark:text-white transition-colors" placeholder="At least 8 characters" minlength="8" required>
                </div>

                <div>
                    <label for="confirm_password" class="block mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-slate-300">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 dark:bg-slate-800 dark:border-slate-700 dark:placeholder-slate-500 dark:text-white transition-colors" placeholder="Re-enter your password" required>
                </div>

                <button type="submit" class="w-full text-white bg-emerald-600 hover:bg-emerald-500 focus:ring-4 focus:ring-emerald-300 dark:focus:ring-emerald-800 font-bold rounded-xl text-sm px-5 py-3 text-center shadow-md shadow-emerald-600/20 transition-all mt-2">
                    <i class="fa-solid fa-user-plus mr-2"></i> Register
                </button>
            </form>

            <p class="text-xs text-center text-gray-500 dark:text-slate-400 mt-6">
                Already have an account? <a href="{{ route('login') }}" class="font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Log in</a>
            </p>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('iris-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        });
    </script>
</body>
</html>