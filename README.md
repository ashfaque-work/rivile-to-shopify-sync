Rivile CRM to Shopify Product Sync
This Laravel-based application automates the process of syncing products from Rivile CRM to a Shopify store. It retrieves products from Rivile, then creates or updates corresponding products in Shopify to ensure data consistency. The sync is automated via cron jobs to keep products up to date at regular intervals.

Features
- Automated Product Sync: Pulls products from Rivile CRM and updates or creates them in Shopify.
- Scheduled Sync: Uses cron jobs for regular sync intervals.
- Error Logging: Logs errors for any failed operations during the sync process.
- Customizable Frequency: The sync frequency can be adjusted by modifying the cron job schedule.

Tech Stack
- Backend: Laravel
- External Services:
    - Rivile CRM API
    - Shopify API

Prerequisites
- PHP: Version 8.x or higher
- Composer: Dependency manager for PHP
- MySQL: Or another compatible database for Laravel
- Laravel: Version 9.x or higher

Getting Started
1. Clone the Repository
git clone https://github.com/ashfaque-work/rivile-to-shopify-sync.git
cd rivile-to-shopify-sync

2. Install Dependencies
Install PHP dependencies using Composer.
composer install

3. Database Migration
Run migrations to set up the required tables in the database.
php artisan migrate

4. Set Up Cron Job for Sync
To enable regular syncing, set up a cron job on your server that runs the Laravel scheduler. Add the following line to your server’s crontab:

* * * * * php /path-to-your-project/artisan schedule:run >> /dev/null 2>&1
The sync job’s frequency can be adjusted in the app/Console/Kernel.php file.

6. Running the Application
Start the Laravel development server to run the application locally:

php artisan serve
The application will be available at http://localhost:8000.

Usage
The sync process is triggered automatically by the cron job. However, you can manually trigger it as follows:

php artisan sync:products
This command retrieves products from Rivile CRM, then updates or creates products in Shopify.

Error Logging
Errors during the sync process are logged in the storage/logs/laravel.log file. This can help track any API issues or failures during syncing.

Customization
- Sync Frequency: The sync frequency can be modified by updating the cron job schedule in app/Console/Kernel.php.
- Product Filtering: Customize the sync process to include/exclude specific product types by editing the logic in the Services directory.

Contributing
1. Fork the repository.
2. Create a new feature branch (git checkout -b feature/YourFeature).
3. Commit your changes (git commit -m 'Add YourFeature').
4. Push to the branch (git push origin feature/YourFeature).
5. Create a Pull Request.

License
This project is licensed under the MIT License.