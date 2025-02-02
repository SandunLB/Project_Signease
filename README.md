# PROJECT_SIGNEASE 🌟

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
![PHP Version](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php)
![MySQL Version](https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql)

**PROJECT_SIGNEASE** is a web application designed to streamline document sharing and management for digital signatures. Simplify your signing process with a secure, intuitive platform.

![SignEase Demo](https://via.placeholder.com/800x400.png?text=SignEase+Interface+Demo)

---

## Table of Contents
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Installation](#-installation)
- [Usage](#-usage)
- [Configuration](#-configuration)
- [Contributing](#-contributing)
- [License](#-license)
- [Acknowledgements](#-acknowledgements)
- [Contact](#-contact)

---

## 🚀 Features

- **Document Upload & Sharing**: Securely upload and share documents for digital signatures.
- **User Authentication**: Role-based access control for document management.
- **Signature Tracking**: Real-time tracking of document signing status.
- **Responsive Design**: Mobile-friendly interface using TailwindCSS.
- **Audit Trail**: Maintains history of document interactions.
- **PDF Support**: Optimized handling of PDF documents.

---

## 💻 Tech Stack

**Frontend**  
![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-06B6D4?logo=tailwind-css)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript)

**Backend**  
![PHP](https://img.shields.io/badge/PHP-777BB4?logo=php)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?logo=mysql)

**Tools**  
![Composer](https://img.shields.io/badge/Composer-885630?logo=composer)
![Git](https://img.shields.io/badge/Git-F05032?logo=git)

---

## 📥 Installation

1. **Clone the repository**
  ```bash
  git clone https://github.com/SandunLB/Project_Signease.git
  cd PROJECT_SIGNEASE
  ```

2. **Set up the database**
  ```sql
  CREATE DATABASE Project_signease;
  USE Project_signease;
  -- Import SQL file from database/Project_signease.sql
  ```

3. **Configure settings**
  Update `config.php` with your database credentials:
  ```php
  DB_HOST=localhost
  DB_NAME=signease_db
  DB_USER=root
  DB_PASS=your_password
  ```

4. **Start the PHP server**
  ```bash
  php -S localhost:8000
  ```

## 🖥️ Usage

1. **Access the application**
   - If using XAMPP: Visit `http://localhost/Project_Signease` in your browser
   - If using PHP built-in server: Visit `http://localhost:8000` in your browser

2. **Upload Documents**
   - Navigate to **Dashboard**
   - Click **Upload New Document**
   - Select PDF/document and add signer emails

3. **Track Signatures**
   - Monitor signing progress in real-time
   - Receive notifications when documents are signed

4. **Download Signed Documents**
   - Completed documents are automatically archived
   - Available in both PDF and audit log formats

# 🔧Configuration

## Email Setup
Configure SMTP in `config/mail.php` for email notifications:

```php
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'user@example.com');
define('SMTP_PASS', 'password');
```

## Security Settings
Update `config/security.php` for enhanced protection:

```php
// Enable HTTPS redirection
define('FORCE_HTTPS', true);

// Session encryption
define('SESSION_ENCRYPTION_KEY', 'your-secure-key-here');
```

## 🤝 Contributing

We welcome contributions! Please follow these steps:
1. Fork the repository
2. Create your feature branch (\`git checkout -b feature/AmazingFeature\`)
3. Commit your changes (\`git commit -m 'Add some AmazingFeature'\`)
4. Push to the branch (\`git push origin feature/AmazingFeature\`)
5. Open a Pull Request

**Development Guidelines**
- Follow PSR-12 coding standards
- Write unit tests for new features
- Update documentation accordingly

---

## 📄 License

Distributed under the MIT License. See \`LICENSE\` file for more information.

---

## 🙏 Acknowledgements

- [TailwindCSS](https://tailwindcss.com) for amazing utility-first CSS
- [PHP Mailer](https://github.com/PHPMailer/PHPMailer) for email functionality
- [PDF.js](https://mozilla.github.io/pdf.js/) for PDF rendering support

---

## 📧 Contact

**Project Maintainer**  
[Your Name] - [sandunlb2001@gmail.com](mailto:sandunlb2001@gmail.com)  
GitHub: [@SandunLB](https://github.com/SandunLB)

Project Link: [https://github.com/SandunLB/Project_Signease](https://github.com/SandunLB/Project_Signease)
