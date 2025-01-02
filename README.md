# E-Signature QR Code Stamper

Digital document management system built with Laravel 11, Livewire Volt, and MaryUI.

## Features

- Document management with approval workflow
- Digital signatures with QR code stamping
- Online document verification
- Multiple approval levels (First Approver & Final Approver)
- Department and user role management
- Audit trail for document verification

## Tech Stack

- Laravel 11
- Livewire Volt
- MaryUI
- MySQL/PostgreSQL

## Database Structure

- Users (with roles: superadmin, admin, pimpinan, approver, user)
- Departments
- Document Types
- Documents
- Document Approvals
- QR Code Generations
- QR Codes
- Verification Logs

## Installation

```bash
git clone [repository-url]
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
```

## Requirements

- PHP >= 8.2
- Node.js >= 16
- Composer
- Database (MySQL/PostgreSQL)

## License

MIT License
