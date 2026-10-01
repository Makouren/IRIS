<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
if (!ALLOW_SUPER_ADMIN_UPLOAD) redirect_to('admin/review_editor.php');
$msg = flash('error');
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rule-Based Document Ingestion - IRIS Admin</title>
    <!-- Tailwind CSS CDN -->
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
                            900: '#064e3b',
                            gold: '#f59e0b'
                        }
                    }
                }
            }
        }
    </script>
    <!-- Flowbite CSS & JS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50 backdrop-blur-md bg-opacity-90 dark:bg-opacity-90">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Brand / Logo -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center text-white font-black text-xl shadow-md">
                        <img src="<?= e(base_url('images/iris-logo.png')) ?>" alt="IRIS Logo" class="w-10 h-10 object-contain">
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Rule-Based Document Ingestion</span>
                            <span class="text-xs px-2 py-0.5 font-bold rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300">CSV MAPPER</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">CSV Header Mapping &amp; Verification</p>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <a href="<?= e(base_url('admin/dashboard.php')) ?>" class="inline-flex items-center px-3.5 py-2 text-xs font-semibold text-gray-700 bg-gray-100 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                        <i class="fa-solid fa-arrow-left mr-1.5"></i> Back to Admin
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-8">
        
        <?php if ($msg): ?>
            <div id="alert-error" class="flex items-center p-4 mb-4 text-red-800 rounded-xl bg-red-50 dark:bg-gray-800 dark:text-red-400 border border-red-200 dark:border-red-800" role="alert">
                <i class="fa-solid fa-circle-exclamation text-lg mr-3"></i>
                <div class="text-sm font-medium">
                    <?= htmlspecialchars($msg) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Document Intake Card with Flowbite Dropzone -->
        <div class="bg-white dark:bg-gray-800 p-6 sm:p-8 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-6">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                    <i class="fa-solid fa-cloud-arrow-up text-emerald-600 mr-2.5"></i>
                    Upload CSV Document
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">
                    Upload CSV data files for rule-based header mapping. For PDF, DOCX, Excel, and image intake, use the browser-side IRIS Scanner tool.
                </p>
            </div>

            <!-- Format Badges -->
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                    <i class="fa-solid fa-file-csv mr-1.5 text-emerald-600"></i> Structured CSV Files
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
                    <i class="fa-solid fa-qrcode mr-1.5 text-blue-500"></i> PDF/DOCX/OCR Intake -> IRIS Scanner
                </span>
            </div>

            <!-- Upload Form -->
            <!-- UPLOAD MOVED TO ADMIN ROLE: retained former Super Admin CSV upload form. -->
            <!-- <form action="<?= e(base_url('admin/smart_upload_process.php')) ?>" method="POST" enctype="multipart/form-data" id="smartUploadForm" class="space-y-6">
                <?= csrf_field() ?>
                <div class="flex items-center justify-center w-full">
                    <label for="upload_file" id="dropZone" class="flex flex-col items-center justify-center w-full h-56 border-2 border-dashed rounded-2xl cursor-pointer bg-gray-50 dark:hover:bg-gray-700/50 dark:bg-gray-750 hover:bg-gray-100 border-gray-300 dark:border-gray-600 hover:border-emerald-500 dark:hover:border-emerald-500 transition-all group">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6 text-center px-4">
                            <div class="w-12 h-12 mb-3 rounded-full bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                            </div>
                            <p class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                <span class="text-emerald-600 dark:text-emerald-400">Click to upload</span> or drag and drop
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                CSV files with standard headers (MAX. 20MB)
                            </p>
                            <div id="dropZoneFilename" class="mt-3 text-xs font-bold px-3 py-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-900/80 dark:text-emerald-200 rounded-full hidden"></div>
                        </div>
                        <input id="upload_file" name="upload_file" type="file" class="hidden" accept=".csv" required />
                    </label>
                </div>

                <button type="submit" id="submitBtn" class="w-full inline-flex items-center justify-center px-5 py-3 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-500 focus:ring-4 focus:ring-emerald-300 dark:focus:ring-emerald-800 rounded-xl shadow-md transition-all">
                    <i class="fa-solid fa-file-import mr-2"></i> Upload &amp; Map CSV Data
                </button>
            </form> -->

            <div class="p-4 bg-emerald-50/50 dark:bg-gray-750 rounded-xl border border-emerald-100 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-300 flex items-start space-x-3">
                <i class="fa-solid fa-shield-halved text-emerald-600 dark:text-emerald-400 text-base mt-0.5"></i>
                <div>
                    <span class="font-bold text-gray-900 dark:text-white">Verification Guarantee:</span> No database writes occur automatically. After rule-based mapping, you will be shown an interactive staging table where you can inspect, edit, or deselect individual rows prior to publishing.
                </div>
            </div>
        </div>

    </main>

    <!-- UPLOAD MOVED TO ADMIN ROLE: the former upload form bindings are retained but disabled. -->
    <!-- <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('upload_file');
        const filenameLabel = document.getElementById('dropZoneFilename');

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) {
                const file = fileInput.files[0];
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                filenameLabel.textContent = `Selected: ${file.name} (${sizeMb} MB)`;
                filenameLabel.classList.remove('hidden');
            } else {
                filenameLabel.textContent = '';
                filenameLabel.classList.add('hidden');
            }
        });

        ['dragover', 'dragleave', 'drop'].forEach(evt => {
            dropZone.addEventListener(evt, e => e.preventDefault());
        });
        dropZone.addEventListener('dragover', () => dropZone.classList.add('border-emerald-500', 'bg-emerald-50/20'));
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-emerald-500', 'bg-emerald-50/20'));
        dropZone.addEventListener('drop', e => {
            dropZone.classList.remove('border-emerald-500', 'bg-emerald-50/20');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
    </script> -->
</body>
</html>

