# E-Signature QR Code Stamper

<div align="center">

Digital document management system with QR code-based verification, built with Laravel 11, Livewire Volt, and MaryUI.

[Features](#features) • [Screenshots](#screenshots) • [Tech Stack](#tech-stack) • [Installation](#installation)

</div>

## ✨ Features

- **Document Management** with comprehensive approval workflow
- **Digital Signatures** with secure QR code stamping
- **Online Document Verification** through QR code scanning
- **Multi-level Approval System** (First Approver & Final Approver)
- **Role-based Access Control** with department management
- **Complete Audit Trail** for document verification
- **Secure Document Storage** with version control
- **Real-time Updates** using Livewire

## 📸 Screenshots

<details>
<summary>Click to view screenshots</summary>

### Dashboard
![Dashboard](https://i.ibb.co.com/KqxbbVD/Screenshot-2025-01-03-at-21-35-59.png)

### Document Submission
![Document Submission](https://i.ibb.co.com/8bkBZLK/Screenshot-2025-01-03-at-21-33-41.png)

### Document Approvals
![Document Approval](https://i.ibb.co.com/QXZ4y83/Screenshot-2025-01-03-at-21-34-56.png)

### Digital Signing Process
![Signing Process](https://i.ibb.co.com/T22bf0x/Screenshot-2025-01-03-at-21-45-14.png)

### Verification Portal
![Verification](https://i.ibb.co.com/FzFKPgs/Screenshot-2025-01-03-at-21-44-01.png)

</details>

## 🔍 Online Document Verification

Our system provides a robust document verification process:

1. **QR Code Scanning**
   - Each signed document contains a unique QR code
   - Scan using any QR code reader or smartphone camera

2. **Instant Verification**
   - Access the verification portal directly through the QR code
   - View document authenticity status and metadata
   - Check digital signature validity

3. **Security Features**
   - Unique hash generation for each document
   - Tamper-evident verification system
   - Complete verification audit trail
   - IP address and device logging for security

## 🛠️ Tech Stack

- **Backend Framework**: Laravel 11
- **Frontend**: 
  - Livewire Volt for reactive components
  - MaryUI (DaisyUI TailwindCSS) for sleek user interface
- **Database**: MySQL/PostgreSQL
- **Additional Libraries**:
  - [Blade Laravel Stamper](https://github.com/TheArKaID/laravel-stamper) for qrCode document pdf stamping
  - TCPDF Fpdi for PDF generation

## 📦 Installation

```bash
# Clone the repository
git clone https://github.com/freditrihandoko/esign-qrcode-stamper.git

# Install dependencies
composer install
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Configure database and run migrations
php artisan migrate --seed

# Create storage symlink
php artisan storage:link

# Create admin user (via tinker or database)
php artisan tinker
User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);
```

## ⚙️ Requirements

- PHP >= 8.2
- Node.js >= 16
- Composer
- MySQL >= 5.7 or PostgreSQL >= 10


### Database Structure
```
├── Users (Roles: admin, pimpinan, approver, user)
├── Departments
├── Document Types
├── Documents
├── Document Approvals
├── QR Code Generations
├── QR Codes
└── Verification Logs
```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

Contributions are welcome! This project is far from perfect and continuously evolving. Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## 🙏 Acknowledgements

- [TheArKaID](https://github.com/TheArKaID) for the Laravel Stamper library
- The Laravel Community
- All contributors who have helped this project grow
