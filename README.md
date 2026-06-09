# PawFect Home

PawFect Home is a PHP and MySQL-based pet adoption system with a pet needs store, appointment booking, adoption applications, payment handling, and role-based dashboards for customers, staff, and administrators.

## Features

### Customer
- Browse available pets by type, breed, age, gender, and keyword
- Save pets to wishlist
- Submit adoption applications
- Book, edit, and cancel shelter appointments
- Shop for pet food and pet care products
- Make product payments and view payment history
- Update profile details

### Staff
- Manage pet records and health information
- Review adoption applications and discussion requests
- View upcoming appointments
- Monitor system activity related to adoption workflow

### Admin
- Manage customer accounts
- Manage staff accounts
- Manage pets and product listings
- Review contact messages
- Manage payments, reports, and announcements

## Tech Stack
- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript, Bootstrap
- **Libraries:** jQuery, Owl Carousel, Font Awesome, Tempus Dominus, PHPMailer

## Project Structure

```text
Project/
├── index.php
├── about.php
├── pets.php
├── products.php
├── adoption_apply.php
├── appointment.php
├── payment.php
├── contact.php
├── manage_*.php
├── css/
├── js/
├── img/
├── lib/
└── PHPMailer-master/
```

## Requirements
- PHP 7.4 or later
- MySQL / MariaDB
- Apache server
- XAMPP, WAMP, MAMP, or similar local server environment

## Setup Guide

### 1. Extract the project
Place the `Project` folder inside your web server directory, for example:

```text
htdocs/Project
```

### 2. Create the database
Create a MySQL database named:

```text
project2026
```

### 3. Import the database tables
Import the SQL dump for the project if you have one, or create the required tables manually based on the application logic.

### 4. Configure database connection
Open `db.php` and make sure the MySQL credentials match your local setup.

### 5. Configure email settings
Update `mail_config.php` with your SMTP details if you want password reset emails to work properly.

### 6. Configure payment settings
Update `payment_gateway_config.php` if you want to enable the ToyyibPay online banking flow.

### 7. Run the project
Open the project in your browser:

```text
http://localhost/Project/
```

## Notes
- The system uses role-based login routing for customers, staff, and admins.
- Some features depend on database tables being present, such as pets, products, applications, appointments, payments, and announcements.
- Before publishing to GitHub, remove any real credentials from configuration files and replace them with environment variables or placeholders.

## Screenshots
Add project screenshots here if needed.

## License
This project was created for academic / demonstration purposes. Add your preferred license before public release.
