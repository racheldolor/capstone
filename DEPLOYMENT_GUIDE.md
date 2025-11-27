# Deployment Guide

## Database Configuration

All database connections in this application use a centralized configuration file located at:
```
config/database.php
```

This makes it easy to deploy your application to any hosting environment by updating only ONE file.

## How to Deploy to Different Environments

### 1. Local Development (XAMPP/WAMP/MAMP)

Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'capstone_culture_arts');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 2. Shared Hosting (cPanel, Hostinger, GoDaddy, etc.)

1. Create a MySQL database through your hosting control panel
2. Note down the database name, username, and password
3. Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');  // Usually 'localhost' for shared hosting
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_username');
define('DB_PASS', 'your_database_password');
```

**Note:** Some shared hosting providers use a different host like `localhost:3306` or a specific server name. Check your hosting documentation.

### 3. Cloud Hosting (AWS RDS, Google Cloud SQL, DigitalOcean, etc.)

Edit `config/database.php`:
```php
define('DB_HOST', 'your-db-instance.region.rds.amazonaws.com');  // Your database endpoint
define('DB_NAME', 'capstone_culture_arts');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 4. Remote Database Server

Edit `config/database.php`:
```php
define('DB_HOST', '192.168.1.100');  // IP address or domain of your database server
define('DB_NAME', 'capstone_culture_arts');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

## Files Updated

All the following files now use the centralized configuration:

- ✅ `admin/register.php`
- ✅ `central/get_available_items.php`
- ✅ `central/get_inventory_items.php`
- ✅ `central/save_inventory_item.php`
- ✅ `head-staff/get_available_items.php`
- ✅ `head-staff/get_inventory_items.php`
- ✅ `head-staff/save_inventory_item.php`
- ✅ `student/db_connect.php`
- ✅ `student/get_borrowed_costumes.php`
- ✅ All other PHP files using `require_once` to include the config

## Security Best Practices

### For Production Environments:

1. **Never commit database credentials to version control**
   - Add `config/database.php` to your `.gitignore` file
   - Create a `config/database.example.php` with dummy values

2. **Use strong passwords**
   - Don't use 'root' with an empty password in production
   - Generate strong, unique passwords for your database

3. **Restrict database access**
   - Only allow connections from your web server's IP address
   - Don't expose your database port to the public internet

4. **File permissions**
   - Set `config/database.php` to read-only (644 or 640)
   - Ensure your web server user can read it but others cannot

## Testing Your Configuration

After updating the configuration, test the database connection by accessing any page that requires database access. If you see errors:

1. Check that the database credentials are correct
2. Verify that the database exists
3. Ensure the database user has the necessary privileges
4. Check your hosting provider's firewall settings

## Troubleshooting

### "Database connection failed" Error

- Verify DB_HOST, DB_USER, DB_PASS, and DB_NAME are correct
- Check if your hosting provider requires a port number (e.g., `localhost:3306`)
- Ensure your database user has privileges for the database

### "Access denied" Error

- Your database username or password is incorrect
- The database user doesn't have privileges for the database
- Check with your hosting provider for the correct credentials

### "Unknown database" Error

- The database name (DB_NAME) is incorrect
- The database hasn't been created yet
- Import your SQL file to create the database structure

## Migration Checklist

When moving from one hosting environment to another:

- [ ] Export your database from the old environment
- [ ] Update `config/database.php` with new credentials
- [ ] Import your database to the new environment
- [ ] Test all functionality (login, CRUD operations, etc.)
- [ ] Update any environment-specific settings
- [ ] Clear browser cache and test again

## Support

If you encounter issues during deployment, refer to your hosting provider's documentation for database connection requirements specific to their platform.
