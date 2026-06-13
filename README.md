# PawFect Home - Pet Adoption System

## Overview

PawFect Home is a web-based Pet Adoption Management System developed using PHP and MySQL. The system helps adoption centres manage pets, adoption applications, appointments, pet products, payments, and user accounts through a centralized platform.

The website allows users to browse available pets, submit adoption requests, schedule appointments, purchase pet-related products, and track their application status online.

---

## Features

### User Features

* User registration and login
* Profile management
* Browse available pets
* View detailed pet information
* Submit pet adoption applications
* Manage wishlist
* Book appointments with the adoption centre
* Purchase pet products and necessities
* View payment history
* Contact the adoption centre
* Password recovery via email

### Staff Features

* Review adoption applications
* Manage pet records
* Update pet health information
* Process appointments
* Monitor customer inquiries

### Administrator Features

* Manage users
* Manage staff accounts
* Manage pets
* Manage products
* Manage payments
* Generate reports
* Manage contact messages
* Monitor adoption activities

---

## Technologies Used

### Frontend

* HTML5
* CSS3
* Bootstrap
* JavaScript
* Font Awesome

### Backend

* PHP

### Database

* MySQL

### External Libraries

* PHPMailer (Email Notifications)
* ToyyibPay Payment Gateway Integration

---

## System Modules

### Authentication Module

Files:

* signin.php
* signup.php
* logout.php
* forgot-password.php
* reset-password.php
* change-password.php

Functions:

* User authentication
* Password reset via email
* Session management

### Pet Management Module

Files:

* pets.php
* pet_details.php
* pet_form.php
* manage_pets.php
* pet_health.php

Functions:

* Add, edit and delete pet records
* Display pet details
* Manage pet health information

### Adoption Module

Files:

* adoption_apply.php
* applications.php
* staff_applications.php

Functions:

* Submit adoption requests
* Review applications
* Track application status

### Appointment Module

Files:

* appointment.php

Functions:

* Appointment scheduling
* Appointment management

### Product & Payment Module

Files:

* products.php
* payment.php
* payment_history.php
* receipt.php
* qr_payment.php
* bank_payment.php
* manual_bank_payment.php

Functions:

* Product purchasing
* QR payment support
* Manual bank transfer verification
* Payment history tracking

### Reporting Module

Files:

* reports.php
* report_pdf.php

Functions:

* Generate reports
* Export PDF reports

---

## Database Configuration

Database connection settings are stored in:

db.php

Update the following values according to your MySQL configuration:

* Host
* Database Name
* Username
* Password

---

## Email Configuration

Email settings are stored in:

mail_config.php

Example:

define('SMTP_FROM_EMAIL', '[pawfecthome2@gmail.com](mailto:pawfecthome2@gmail.com)');
define('SMTP_FROM_NAME', 'PawFect Home');

Configure your SMTP credentials before deploying the system.

---

## Installation Guide

1. Install XAMPP or WAMP.
2. Place the project folder inside:

   * XAMPP: htdocs/
   * WAMP: www/
3. Create a MySQL database.
4. Import the project database SQL file.
5. Configure database credentials in db.php.
6. Configure email settings in mail_config.php.
7. Start Apache and MySQL services.
8. Open the browser and navigate to:

http://localhost/Project/

---

## Project Structure

Project/
├── css/
├── js/
├── img/
├── uploads/
├── PHPMailer-master/
├── db.php
├── mail_config.php
├── signin.php
├── signup.php
├── pets.php
├── adoption_apply.php
├── appointment.php
├── products.php
├── payment.php
├── reports.php
└── index.php

---

## Future Improvements

* Mobile application integration
* Online pet adoption interview system
* Real-time notifications
* AI-based pet recommendation system
* Enhanced analytics dashboard

---

## Authors

This project was created for academic / demonstration purposes. Add your preferred license before public release.
