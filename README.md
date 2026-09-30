# ECC Laundry 2.0 - Smart Campus Laundry Management

An enterprise-ready Laravel 11 web application for campus laundry reservation, IoT machine cycle tracking, credit management, and student facility scheduling.

---

## 🏗️ Architecture

```
ecc-laundry/
├── .env.example
├── compose.yaml
├── composer.json
├── package.json
├── vite.config.js
├── tailwind.config.js
├── artisan
├── bootstrap/
│   ├── app.php
│   └── providers.php
├── config/
│   ├── app.php
│   ├── database.php
│   └── laundry.php
├── app/
│   ├── Enums/
│   │   ├── MachineStatus.php
│   │   ├── MachineType.php
│   │   └── BookingStatus.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Machine.php
│   │   ├── Booking.php
│   │   └── Transaction.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── AuthController.php
│   │   │   ├── MachineController.php
│   │   │   ├── BookingController.php
│   │   │   └── DashboardController.php
│   │   ├── Requests/
│   │   │   ├── StoreBookingRequest.php
│   │   │   └── UpdateMachineStatusRequest.php
│   │   └── Middleware/
│   │       └── EnsureUserHasCredits.php
│   ├── Services/
│   │   ├── BookingService.php
│   │   └── MachineSchedulerService.php
│   ├── Events/
│   │   ├── CycleStarted.php
│   │   └── CycleCompleted.php
│   ├── Listeners/
│   │   └── SendCycleAlertNotification.php
│   └── Jobs/
│       ├── AutoReleaseExpiredBookings.php
│       └── CheckActiveCycles.php
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 2026_01_01_000001_create_machines_table.php
│   │   ├── 2026_01_01_000002_create_bookings_table.php
│   │   └── 2026_01_01_000003_create_transactions_table.php
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   └── MachineSeeder.php
│   └── factories/
│       ├── MachineFactory.php
│       └── BookingFactory.php
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   └── app.js
│   └── views/
│       ├── layouts/
│       │   ├── app.blade.php
│       │   └── navigation.blade.php
│       ├── components/
│       │   ├── machine-card.blade.php
│       │   ├── status-badge.blade.php
│       │   └── countdown-timer.blade.php
│       ├── bookings/
│       │   ├── index.blade.php
│       │   └── create.blade.php
│       └── dashboard.blade.php
├── routes/
│   ├── web.php
│   ├── console.php
│   └── channels.php
└── tests/
    ├── Feature/
    │   ├── BookingTest.php
    │   └── MachineAvailabilityTest.php
    └── Unit/
        └── BookingServiceTest.php
```

---

## ⚡ Quick Start

### 1. Requirements
- Docker & Docker Compose **or** PHP 8.2+ with Composer & Node.js 18+

### 2. Environment Setup
```bash
cp .env.example .env
npm install
```

### 3. Running with Docker Compose (Laravel Sail)
```bash
docker compose up -d
docker compose exec ecc-laundry.test composer install
docker compose exec ecc-laundry.test php artisan key:generate
docker compose exec ecc-laundry.test php artisan migrate --seed
npm run dev
```

### 4. Running Locally (Direct PHP)
```bash
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run dev
php artisan serve
```

---

## 🔑 Demo Credentials

| Role | Email | Password | Starting Credits |
|---|---|---|---|
| **Student** | `student@ecc.edu` | `password123` | 18 Credits |
| **Admin** | `admin@ecc.edu` | `admin123` | 100 Credits |

---

## 🧪 Running Automated Tests
```bash
php artisan test
```
- `tests/Feature/BookingTest.php` - Booking reservations, double booking prevention, balance deductions, cancellations
- `tests/Feature/MachineAvailabilityTest.php` - Slot scheduling, conflicting reservations, admin machine status controls
- `tests/Unit/BookingServiceTest.php` - Machine lifecycle transitions, credit calculations, event broadcasting
