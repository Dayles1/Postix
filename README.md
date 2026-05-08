````md
# Postix

A web platform built with Laravel and frontend assets, where users can sign in using their Telegram phone number and schedule messages in advance.

---

## 📌 Overview

Postix is a Laravel-based web application designed for Telegram account login, message scheduling, and background job processing. Users can authenticate with their Telegram phone number and manage planned messages through a simple web interface.

---

## 🚀 Features

- Telegram phone number login
- Message scheduling in advance
- Web-based user dashboard
- Background job processing with queues
- Admin management system
- Telegram MTProto integration
- Modern frontend built with Laravel tooling

---

## 🧰 Tech Stack

- Laravel 11+
- PHP 8.2+
- MySQL
- Telegram MTProto
- Laravel Queue
- Vite
- NPM

---

## 📦 Installation

Clone the repository and install dependencies:

```bash
composer install
npm install
````

Copy the environment file:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

Run migrations and seed default data:

```bash
php artisan migrate
php artisan db:seed
```

Build frontend assets:

```bash
npm run build
```

For local development, you can also use:

```bash
npm run dev
```

---

## 🌱 Database Seeding

Seeder creates initial data such as admin account and default roles.

Seeder file:

```text
database/seeders/DatabaseSeeder.php
```

After seeding, the default admin login and password are available from the seeder configuration.

---

## ⚙️ Queue System

This project uses Laravel Queue for background jobs such as Telegram messaging and scheduled task processing.

Start queue worker:

```bash
php artisan queue:work --queue=telegram
```

If needed, you can also run the worker continuously on the server using a process manager.

---

## 🧪 Environment Variables

Make sure to configure your `.env` file:

```env
TELEGRAM_API_ID=
TELEGRAM_API_HASH=
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_URL=

DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

---

## 🚨 Important Notes

* Never commit `.env` file to the repository
* Queue worker must always be running in production
* Keep Telegram credentials secure
* Run frontend build after changing frontend assets
* Seeded admin credentials should be changed after first login

---

## 📄 License

This project is private software. Unauthorized copying or distribution is prohibited.

```
```
