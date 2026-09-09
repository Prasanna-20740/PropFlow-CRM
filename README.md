# PropFlow CRM

A web-based Real Estate CRM built to manage property projects, buildings, units, leads, sales pipelines, bookings and employees — developed as an end-to-end technical assignment submission.

PropFlow CRM lets an **Admin** manage the full property and employee catalogue while **Sales** employees work their assigned leads through a pipeline and convert them into unit bookings, with built-in protection against double-booking the same unit.

**Live Application:** https://propflowcrm.gt.tc/

---

## Table of Contents

- [Features](#features)
- [Technology Stack](#technology-stack)
- [Project Structure](#project-structure)
- [Database Overview](#database-overview)
- [API / Application Flow Overview](#api--application-flow-overview)
- [Setup Instructions](#setup-instructions)
- [Authentication & Roles](#authentication--roles)
- [Security](#security)
- [Key Decisions & Rationale](#key-decisions--rationale)
- [Testing Notes](#testing-notes)
- [Future Improvements](#future-improvements)
- [License](#license)

---

## Features

### Authentication
- Registration and login with hashed passwords
- Session-based authentication with session regeneration
- Role-based access control (Admin / Sales)
- CSRF protection on all forms
- Protected routes and secure logout

### Admin Module
- Dashboard with organisation-wide sales metrics
- Project, building and unit management (CRUD)
- Lead management and assignment to sales employees
- Booking management, including cancellation
- Employee management
- Search and filtering across all modules

### Sales Module
- Personal dashboard (assigned leads, follow-ups, bookings)
- "My Leads" view scoped to the logged-in employee
- Lead detail view with stage history and notes
- Lead stage updates and follow-up date tracking
- Sales pipeline view across all stages
- Booking creation and viewing
- Profile management

### Property Management
Hierarchy: **Project → Building → Unit**

Each unit stores: unit number, unit type, floor, price and availability status.

### Sales Pipeline
Lead stages: `New → Contacted → Site Visit → Interested → Negotiation → Booked` (or `Lost` at any point).

### Booking Flow
`Lead → Booking → Unit → Building → Project`

- Confirming a booking: `Available → Booked`
- Cancelling a booking: `Booked → Available`
- A unit that is already `Booked` cannot be selected for a new booking — this is enforced both in the UI (unavailable units are not selectable) and at the database/API layer (a unique/availability check runs before insert) to prevent two sales employees from booking the same unit in a race condition.

---

## Technology Stack

| Category | Technology |
|---|---|
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP |
| Database | MySQL |
| Database Access | PDO (prepared statements) |
| Authentication | PHP Sessions |
| Local Server | XAMPP |
| Database Management | phpMyAdmin |
| Deployment | InfinityFree |
| Version Control | Git & GitHub |

---

## Project Structure

```text
PropFlow-CRM/
│
├── admin/
│   ├── dashboard.php
│   ├── projects.php
│   ├── buildings.php
│   ├── units.php
│   ├── leads.php
│   ├── bookings.php
│   └── employees.php
│
├── sales/
│   ├── dashboard.php
│   ├── leads.php
│   ├── lead-view.php
│   ├── pipeline.php
│   ├── bookings.php
│   └── profile.php
│
├── config/
│   └── database.php
│
├── includes/
│   └── auth.php
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── database/
│   └── propflow_crm.sql
│
├── index.php
├── login.php
├── register.php
├── logout.php
└── README.md
```

---

## Database Overview

Relational **MySQL** schema.

| Table | Purpose |
|---|---|
| `users` | Admin and Sales user accounts (role, hashed password) |
| `projects` | Real-estate project information |
| `buildings` | Buildings associated with a project |
| `units` | Individual property units (price, type, floor, availability) |
| `leads` | Customer/lead information and current pipeline stage |
| `bookings` | Booking records linking a lead to a unit |

### Relationships

```text
projects.id        1 ─── ∞  buildings.project_id
buildings.id        1 ─── ∞  units.building_id
users.id (sales)    1 ─── ∞  leads.assigned_to
leads.id            1 ─── ∞  bookings.lead_id
units.id            1 ─── 1  bookings.unit_id   (one active booking per unit)
```

### End-to-end data flow

```text
Project → Building → Unit → Lead → Booking
```

```text
Sales User → Assigned Lead → Pipeline Stage → Booking
```

---

## API / Application Flow Overview

The application does not depend on any external third-party API. Core operations are handled server-side:

- PHP for server-side request handling and business logic
- PDO with prepared statements for all database operations
- MySQL for persistent storage
- HTML forms with HTTP `GET`/`POST` requests for client–server interaction
- PHP sessions for authenticating and authorising every request

Internally the app is organised around resource-style endpoints (leads, units/properties, bookings, employees), each performing validation, permission checks and then the database operation, mirroring how a REST API would be structured — making a future migration to a JSON API layer (see *Future Improvements*) straightforward.

---

## Setup Instructions

### Requirements
- XAMPP (Apache + PHP + MySQL)
- PHP 8.x
- MySQL / phpMyAdmin
- Git
- A modern web browser

### 1. Clone the repository
```bash
git clone https://github.com/YOUR-USERNAME/PropFlow-CRM.git
cd PropFlow-CRM
```

### 2. Start XAMPP
Open the XAMPP Control Panel and start:
```text
Apache
MySQL
```

### 3. Place the project in the web root
Copy (or clone directly into) the XAMPP `htdocs` folder:
```text
C:\xampp\htdocs\PropFlow-CRM
```

### 4. Create the database
Open `http://localhost/phpmyadmin` and create a database named:
```text
propflow_crm
```

### 5. Import the schema
In phpMyAdmin, select the `propflow_crm` database → **Import** → choose `database/propflow_crm.sql` → click **Go**.
This creates the `users`, `projects`, `buildings`, `units`, `leads` and `bookings` tables with sample seed data.

### 6. Configure the database connection
Edit `config/database.php`:
```php
<?php

$host     = "localhost";
$dbname   = "propflow_crm";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed.");
}
```
> For production, use the credentials supplied by your hosting provider. Never commit production credentials to a public repository — use an untracked `.env` or a config file excluded via `.gitignore`.

### 7. Run the application
Open:
```text
http://localhost/PropFlow-CRM/
```

### 8. Log in
Register a new account at `/register.php`, or use the seeded demo accounts below to log in as Admin or Sales and get redirected to the matching dashboard:
```text
Admin → admin/dashboard.php
Sales → sales/dashboard.php
```

---

## Demo Login Credentials

Use these seeded accounts (from `database/propflow_crm.sql`) to try out the live app or a local install without registering a new user.

| Role | Email | Password |
|---|---|---|
| Admin | `admin@propflow.com` | `Admin@123` |
| Sales | `sales@propflow.com` | `Sales@123` |

> These are demo credentials for evaluation purposes only. Change or remove them before using this schema in any real deployment.

### Adding a new employee (Admin)

From **Admin → Employees → + Add Employee**, an Admin can create additional Sales accounts. Example:

| Field | Value |
|---|---|
| Name | Sales Executive |
| Email | `sales@propflow.com` |
| Role | Sales |
| Status | Active |
| Password | `sales123` |

The password entered here is hashed before being stored and is used by that employee to log in going forward — it does not need to match the password shown in a screenshot or doc once changed.

---

## Authentication & Roles

**Admin** can manage: Dashboard, Projects, Buildings, Units, Leads, Bookings, Employees.

**Sales** can access: Dashboard, My Leads, Lead View, Pipeline, Bookings, Profile — scoped strictly to leads assigned to them; a sales user cannot view or edit another employee's leads.

---

## Security

- Passwords hashed with PHP's `password_hash()` / verified with `password_verify()`
- All queries via PDO prepared statements (no string-concatenated SQL)
- Session-based auth with session ID regeneration on login
- CSRF tokens on all state-changing forms
- Server-side role checks on every Admin/Sales route, not just UI hiding
- Input validation on all forms; output escaped with `htmlspecialchars()` to prevent XSS
- Logged-out users are redirected away from protected routes

---

## Key Decisions & Rationale

1. **Server-rendered PHP over a separate SPA/API split.**
   Given the assignment's emphasis on end-to-end ownership over framework novelty, a PHP + PDO + MySQL stack keeps the request lifecycle transparent (routing, auth, validation, query, render) in one readable codebase, and is trivial to host on free/shared hosting for the required live demo — while still being structured so each page's logic could be extracted into a JSON API later.

2. **Unit availability re-checked server-side at booking time, not just in the UI.**
   Hiding already-booked units in the dropdown is good UX, but it isn't sufficient to prevent a race condition where two sales employees submit a booking for the same unit almost simultaneously. The booking endpoint re-verifies `units.status = 'available'` inside the same transaction as the insert/update, so the second request fails safely with a clear "unit no longer available" error instead of creating a conflicting booking.

3. **Leads are scoped to `assigned_to` at the query level, not the view level.**
   Sales permission checks happen in the data-access layer (every lead/booking query for a Sales user is filtered by their user ID), rather than fetching everything and hiding rows in the UI. This avoids accidentally leaking other employees' lead data through a direct URL or API call.

4. **Cancelling a booking automatically restores unit availability.**
   Since bookings and unit status are two different tables, cancellation is implemented as a single operation that updates both the `bookings` row and the linked `units.status`, so the property list and pipeline never fall out of sync with actual booking state.

5. **Explicit `Lost` stage instead of deleting leads.**
   Real sales teams need historical visibility into why a lead didn't convert. Rather than deleting or hard-resetting a lead that goes cold, it's moved to a terminal `Lost` stage, keeping it searchable and reportable without cluttering the active pipeline.

---

## Testing Notes

Manually verified across the following areas:

- **Auth:** registration, duplicate-email registration, login, logout, invalid password handling, role-based redirect
- **Admin module:** CRUD on projects, buildings, units, leads, bookings, employees; search and filtering
- **Sales module:** dashboard metrics, My Leads scoping, stage updates, follow-up updates, pipeline view, bookings, profile edits
- **Booking flow:** booking creation, unit status flips to `Booked`, cancellation restores `Available`, concurrent-booking rejection
- **Security:** unauthenticated access to Admin/Sales routes is blocked; a Sales user cannot access another employee's lead via direct URL
- **Responsive design:** desktop, tablet and mobile breakpoints

---

## Future Improvements

- Lead activity history / audit log
- Automated follow-up reminders and email notifications
- Sales analytics and advanced reporting
- Payment/installment tracking on bookings
- Document management (attach brochures, agreements) per unit or lead
- Extracting current logic into a proper REST/JSON API layer
- Richer dashboard analytics (conversion rate by stage, per-employee performance)

---

## License

Developed for educational and technical-assignment / project-submission purposes.
