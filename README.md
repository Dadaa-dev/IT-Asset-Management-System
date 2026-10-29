# AssetVault: SPIST IT Asset Management System

A web-based IT Asset Management System with maintenance monitoring, physical asset tracking, and QR-based problem reporting designed for the **Southern Philippines Institute of Science and Technology (SPIST)**.

---

## 🚀 Key Features

- **Centralized Asset Inventory:** Track and manage computers, monitors, peripherals, printers, and network equipment across campus facilities (Main Building, Melloville Building, Electronics Laboratory, and Computer Laboratories).
- **Physical QR & Barcode Generation:** Generates printable 58mm × 30mm asset stickers containing Code 39 barcodes and QR codes.
- **Scan-to-Report Problem Submission:** Mobile scanning of physical asset tags dynamically routes to a pre-filled Google Form (`r.php`) with the Asset Tag, Name, and Room pre-populated.
- **Rule-Based Maintenance Monitoring:** Tracks equipment conditions (`good`, `fair`, `poor`), defect frequency, and servicing history with automated inspection/replacement alerts.
- **Role-Based Access Control:** Facilitator / IT Staff administration and faculty/student access secured via `@spist.edu.ph` institutional Google Authentication and local credentials.

---

## 🛠️ Technology Stack

- **Backend:** PHP 8.1+, MySQL / MariaDB (via XAMPP)
- **Frontend:** HTML5, CSS3, Tailwind CSS, JavaScript (ES6+)
- **Analysis Logic:** Python (Rule-based maintenance monitoring)
- **Reporting Engine:** Google Forms / Google Sheets API Integration
- **Authentication:** Firebase Auth / Google Identity Services (`@spist.edu.ph`)

---

## 📦 Installation & Setup

1. **Clone or Copy Repository:**
   Place this project folder into your web server root directory:
   ```bash
   C:\xampp\htdocs\asset_management\
   ```

2. **Database Setup:**
   - Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
   - Create a new database named `capstone_system` with `utf8mb4_unicode_ci`.
   - Import the database schema from:
     ```bash
     database/capstone_system.sql
     ```

3. **Configure Environment:**
   - Review and update database credentials and institutional domain in `config.php`:
     ```php
     define('ALLOWED_DOMAIN', 'spist.edu.ph');
     ```
   - Customize Google Form pre-fill settings via `constants.php` or through the admin **Settings** interface.

4. **Launch Application:**
   - Start **Apache** and **MySQL** in XAMPP Control Panel.
   - Access the system at:
     ```
     http://localhost/asset_management/
     ```

---

## 📄 License
Developed for Southern Philippines Institute of Science and Technology (SPIST) Capstone Project.
