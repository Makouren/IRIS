# 🏛️ IRIS — CLSU Performance Observatory
> **Institutional Research & Information System (IRIS)**  
> Comprehensive ranking intelligence, accreditation analytics, and automated multi-format data ingestion for higher education.  
> **UI Stack:** Flowbite Components &bull; Tailwind CSS &bull; Apache ECharts &bull; Dark Mode

---

## 📖 Overview

**IRIS** is a centralized web platform engineered for the **International Affairs Office (IAO)** and institutional research teams at Central Luzon State University (CLSU). It tracks global university rankings (QS World, THE Impact, WURI), national program standings, college performance contributions, and international academic accreditations (AUN-QA).

With **Smart Upload**, IRIS uses AI-assisted document parsing to ingest unstructured reports (PDF, Word, Excel, and image certificates), staging the data in an interactive review interface before saving to the live observatory.

---

## ✨ Features & UI/UX Highlights

- **Flowbite & Tailwind CSS UI:** Modern enterprise interface featuring responsive cards, glassmorphic navigation bars, tabbed controllers, and theme toggle (Dark / Light mode).
- **Apache ECharts Visual Analytics Engine:**
  - **QS Multi-Year Trajectory:** Smooth area splines with inverted rank axis, custom tooltips, and dynamic theme reactivity.
  - **College Contribution Doughnut Chart:** Breakdown of academic and research performance weights across colleges with rounded corners and legend indicators.
  - **College Research Bar Chart & Category Breakdowns:** Horizontal bar visualizations showing department output and indicator scores (THE SDGs, WURI innovation, QS Stars).
- **Executive KPI Dashboard:** Real-time visibility into Best Global Rank, National (Philippines) Standing, Monitored Bodies, and Certified Programs.
- **Searchable & Filterable Data Tables:** Instant client-side search filtering across National Program rankings and AUN-QA accreditation records.
- **✨ IRIS Intelligence Summary:** AI-powered executive synthesis providing plain-language performance analysis on demand with shimmering skeleton loader.
- **Smart Multi-Format Ingestion:** Flowbite dropzone file intake supporting `.pdf`, `.docx`, `.xlsx`, `.csv`, `.jpg`, and `.png`.
- **Tabbed Extraction Review Staging:** Multi-category Flowbite tabbed interface allowing administrators to review, toggle, edit, and bulk-select extracted records before saving.

---

## 🏗️ Tech Stack

- **Backend:** PHP 8.x + MySQL / MariaDB (MySQLi)
- **Frontend UI Framework:** [Flowbite 2.3](https://flowbite.com/) & Tailwind CSS (via CDN)
- **Charting & Data Visualization:** [Apache ECharts 5.5](https://echarts.apache.org/)
- **Icons & Typography:** Font Awesome 6.4 + Google Fonts (`Inter`)
- **AI Extraction & OCR:** Google Gemini API integration (`includes/ai_extract.php`), with optional system CLI parsers (Poppler, Pandoc, Tesseract).

---

## 📁 Directory Structure

```text
iris/
├── admin/
│   ├── dashboard.php             # Admin control panel (Flowbite UI) & direct CSV intake
│   ├── review_extraction.php     # Tabbed staging & verification interface (Flowbite Tabs)
│   ├── smart_upload.php          # Drag-and-drop document upload interface (Flowbite Dropzone)
│   ├── smart_upload_process.php  # Parsing router & extraction trigger
│   ├── smart_upload_confirm.php  # Database commit handler
│   └── upload_process.php        # Direct CSV batch parser
├── api/
│   └── summary.php               # AI summary generator endpoint
├── assets/
│   └── css/
│       └── style.css             # Base styles & fallback classes
├── auth/
│   ├── login.php                 # Institutional login portal (Flowbite styled)
│   ├── logout.php                # Session termination
│   └── register.php              # User registration portal (Flowbite styled)
├── config/
│   └── db.php                    # MySQL database connection configuration
├── database/
│   └── iris_db.sql               # Database schema & sample seed data
├── includes/
│   ├── ai_extract.php            # Gemini API & document ingestion logic
│   ├── data_insert.php           # Batch database insertion routines
│   ├── extractors.php           # File parser adapters (PDF/DOCX/XLSX)
│   └── functions.php             # Core authentication & helper utilities
├── preview_dashboard.html        # Interactive Flowbite + Apache ECharts preview
├── sample_csv/                   # Reference CSV files for batch import
├── user/
│   └── dashboard.php             # Public CLSU Performance Observatory (Flowbite + ECharts)
├── index.php                     # Route redirector based on session role
└── README.md                     # Project documentation
```

---

## 🚀 Running & Viewing the App

### Option A: Instant Preview with Python (No PHP/MySQL Required)

If you don't have XAMPP or PHP installed yet, you can preview the fully styled Flowbite & Apache ECharts dashboard using Python's built-in HTTP server:

```powershell
python -m http.server 8000
```
Open your browser and navigate to:
👉 **[http://localhost:8000/preview_dashboard.html](http://localhost:8000/preview_dashboard.html)**

---

### Option B: Full Application Setup with XAMPP (PHP + MySQL)

1. **Move Project to `htdocs`:**
   Copy the `iris` project folder into your XAMPP `htdocs` directory:
   ```text
   C:\xampp\htdocs\iris
   ```

2. **Start Apache & MySQL:**
   Open the **XAMPP Control Panel** and click **Start** next to both **Apache** and **MySQL**.

3. **Import Database:**
   - Open `http://localhost/phpmyadmin`.
   - Create a database named `iris_db`.
   - Import `database/iris_db.sql`.

4. **Launch Portal:**
   - Public Observatory / User: `http://localhost/iris/user/dashboard.php`
   - Admin Ingestion Control Panel: `http://localhost/iris/admin/dashboard.php`
   - Default Admin Credentials: `admin` / `admin123`

---

## 📜 License & Accreditation
Built for **Central Luzon State University (CLSU)** &bull; International Affairs Office (IAO).
