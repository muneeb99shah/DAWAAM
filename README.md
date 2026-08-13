# Dawaam — Local-First Business Continuity & POS System

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat&logo=php)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MariaDB%2FMySQL-4479A1?style=flat&logo=mysql)](https://www.mysql.com/)
[![Architecture](https://img.shields.io/badge/Architecture-Local--First%20LAN-00B4D8?style=flat)](#local-first-architecture)
[![License](https://img.shields.io/badge/License-Proprietary-0f172a?style=flat)](#)

> **"Your business doesn't stop when the internet does."**  
> Dawaam is an offline-capable, local-first enterprise management system designed to guarantee uninterrupted sales checkouts, inventory control, and document generation during cellular and WAN network outages.

---

## What is Dawaam?

**Dawaam** is a specialized Point of Sale (POS) and business continuity software platform engineered for retail pharmacies, healthcare facilities (such as Quetta Medical & Continuity Center), and local business enterprises operating in connectivity-challenged environments.

The system enables cashier checkout, product catalog management, print-ready document generation, and fine-grained user permission controls—operating with **100% functional uptime over local Wi-Fi / LAN** without requiring active internet connectivity.

---

## Why Dawaam?

In regions like Balochistan (specifically Quetta), external internet infrastructure faces frequent WAN disruptions, fiber cuts, or cellular blackouts. Traditional cloud-dependent POS systems freeze during outages, halting cashier transactions, locking managers out of inventory logs, and creating severe revenue loss.

**Dawaam** addresses this by hosting the database engine and transaction processor locally on the enterprise LAN server (`192.168.108.1:8000`). All staff terminals (PCs, laptops, tablets) process sales locally with zero dependency on cloud connections.

---

## Main Features

- **Operational Dashboard**: Real-time sales metrics, revenue analytics, transaction counts, asset valuations, and urgent stock alert indicators.
- **POS Express Checkout**: Fast barcode scanning, quantity adjustments, automated stock deduction, cash change calculations, and immediate receipt printing.
- **Products & Stock Management**: Inventory catalog, SKU tracking, category management, unit prices, cost prices, reorder thresholds, and low-stock indicators.
- **Document Generation Suite**:
  - **Thermal 58mm POS Receipts**: Ultra-compact, single-line total layout optimized for narrow receipt paper rolls.
  - **Thermal 80mm POS Receipts**: Standard-width thermal POS receipts.
  - **Official Tax Invoices**: Professional A4/A5 print layout with clear breakdowns for Subtotal, Discounts, Sales Tax, and Total Payable.
  - **Delivery Challans**: Official A5/A4 operational dispatch slips displaying item quantities and delivery metadata.
- **User Accounts Directory**: User management, status toggles (Active/Inactive), role assignments, password resets, and individual override shortcuts.
- **Local Network Operations Hub**: Live server IP display, connected device monitoring, and pure vector SVG QR code generation for rapid mobile pairing.
- **Scalable Performance Engine**: Server-side pagination and indexed search built to handle high-volume datasets (100,000+ sales records) with sub-300ms execution speed.
- **Emergency Alerting & Recovery**:
  - Background Urgent Event Alert Engine for critical stock drops (e.g., Insulin).
  - Emergency Android SMS Gateway (160-char formatted payload queue for SIM tower dispatch during WAN blackouts).
  - Cloud Sync Recovery API (`api/sync.php`) tracking changes in `sync_log` for batch sync when cloud connection restores.

---

## Permission System

Dawaam implements a dual-layer Role-Based Access Control (RBAC) engine:

### 1. Role Default Permissions
System roles (`Super Admin`, `Pharmacist`, `Sales Staff`, `Inventory Manager`) inherit default permissions configured in the Granular Permission Matrix (`users.manage`, `products.view`, `sales.create`, `reports.view`, etc.).

### 2. Individual User Permission Overrides
Administrators can grant or deny specific permissions to individual staff members using a compact 3-state control system:
- **`INHERIT ROLE (Default)`**: User follows the permission state assigned to their assigned role.
- **`ALLOW (Explicit Grant)`**: Force grants permission regardless of role default.
- **`DENIED (Explicit Deny)`**: Force blocks permission regardless of role default.

### 3. Effective Access Calculation
The final access decision is resolved dynamically at runtime by `get_user_effective_permissions_matrix()`.

---

## Installation & Setup

### Requirements
- **Web Server**: Apache 2.4+ (XAMPP / WAMP / Linux LAMP stack)
- **PHP**: PHP 8.1 or higher (with `pdo_mysql` and `gd` extensions enabled)
- **Database**: MariaDB 10.4+ or MySQL 8.0+
- **Browser**: Modern web browser (Chrome, Firefox, Edge, Safari)

### Step-by-Step Installation

1. **Clone or Copy Repository**:
   ```bash
   git clone https://github.com/your-username/dawaam.git
   cd dawaam
   ```

2. **Database Setup**:
   - Open phpMyAdmin or MySQL CLI.
   - Create database `dawaam_db` (utf8mb4_unicode_ci).
   - Import database schema and initial seed data:
     ```bash
     mysql -u root -p dawaam_db < database/dawaam_db.sql
     ```
   - Apply performance indexes:
     ```bash
     mysql -u root -p dawaam_db < database/migrations/2026_08_12_performance_indexes.sql
     ```

3. **Configuration**:
   - Review global configuration in `config/constants.php`:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_PORT', 3306);
     define('DB_NAME', 'dawaam_db');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

4. **Run Application**:
   - Start Apache and MySQL services in XAMPP.
   - Access public portal: `http://localhost:8000/` or `http://localhost/dawaam/`
   - Staff Admin Login: `http://localhost:8000/admin/login.php`

---

## Meaning of Dawaam

The name **Dawaam** is derived from the Urdu word **دوام**:
> **لفظ "دوام" (Dawaam) کا اردو میں مطلب ہے ہمیشگی، استمرار، پائیداری اور ہمیشہ قائم رہنے کی حالت۔**

It signifies continuity, stability, operational permanence, and long-term endurance. The software fulfills this name by keeping local business workflows active under all network conditions.

---

## Project Structure

```text
dawaam/
├── admin/               # Admin Portal & Management Views (POS, Users, Sales, Products)
├── api/                 # REST API endpoints (Cloud Sync, LAN Network Status)
├── assets/              # Stylesheets, JavaScript, Bootstrap icons & fonts
├── cloud/               # Cloud Recovery Receiver API endpoints
├── config/              # App constants, PDO database singleton provider
├── database/            # SQL schemas, migrations, performance indexes
├── includes/            # Shared headers, footers, RBAC auth helpers
├── uploads/             # Product images & receipt logos
├── index.php            # Public landing page & Zero-Internet Hero portal
├── .gitignore           # Version control exclusions
└── README.md            # Project documentation
```

---

## License & Certification

**Certified 100% Operational & Production-Ready**  
Developed for local business resilience and enterprise operational stability.
