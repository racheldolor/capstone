# Capstone Culture and Arts Management System

A PHP + MySQL web system for managing campus culture and arts operations. It covers student artist applications, events and participation, announcements, inventory and borrowing, and role-based dashboards for administrators, heads, directors, and students.

## Roles and Portals
- Admin: full system management and user administration.
- Head/Director: campus management, student profiles, events, announcements, and inventory/borrowing oversight.
- Student: profile management, event participation, announcements, and borrowing requests.

## Core Modules
- Authentication with role-based routing.
- Student artist applications and profiles.
- Events and participation tracking.
- Announcements with audience targeting.
- Inventory management and borrowing requests.
- PDF exports for student profiles (TCPDF).
- Admin action logging.

## Tech Stack
- PHP 8.x
- MySQL/MariaDB
- HTML/CSS/JavaScript
- TCPDF (PDF generation)

## Local Setup (XAMPP)
1. Place the project in your XAMPP htdocs folder (e.g., `C:\xampp\htdocs\capstone`).
2. Start Apache and MySQL in XAMPP.
3. Create a database named `capstone_culture_arts`.
4. Import the schema and seed data from `FINALDATABASE.sql`.
5. Update database credentials in `config/database.php`.
6. Ensure the `uploads/` directory is writable by the web server.
7. Open the app in a browser:
   - `http://localhost/capstone/`

## Database
The database schema is provided in `FINALDATABASE.sql` and includes tables for:
- Users and student artists
- Applications
- Announcements
- Events and participation
- Inventory and borrowing requests
- Logs and audit trails

## Entry Points
- `index.php`: login and role-based redirect
- `admin/`: admin dashboard and management actions
- `head-staff/`: head/director dashboard and operations
- `student/`: student dashboard and self-service flows

## Notes
- Sessions are used for authentication and access control.
- Some modules auto-create tables if missing (e.g., borrowing requests).

## License
Internal academic project (no public license specified).
