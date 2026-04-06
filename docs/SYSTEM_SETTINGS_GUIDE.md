# System Settings - User Guide

## Overview
The System Settings feature provides a centralized, easy-to-use interface for managing application-wide configuration settings. All settings are stored in the database and can be accessed throughout your application.

## Features

✅ **Easy-to-Use Interface** - Simple, organized settings page grouped by category
✅ **Complete CRUD Operations** - Create, read, update, and delete settings
✅ **Helper Functions** - Convenient global helper functions to access settings anywhere
✅ **Batch Updates** - Update multiple settings at once
✅ **Default Values** - Predefined sensible defaults for common settings
✅ **Reset Functionality** - Reset all settings to default values

## Accessing Settings

### Via Admin Panel
Navigate to **System Settings** from the sidebar menu in the admin panel.

### Via Helper Functions

#### Get a setting value:
```php
// Get a single setting
$appName = setting('app_name');
$appName = setting('app_name', 'Default Name'); // With default value

// Or using settings() (same thing)
$currency = settings('system_currency', 'USD');
```

#### Set a setting value:
```php
// Set a single setting
setting(['app_name' => 'My Apartment System']);

// Set multiple settings at once
setting([
    'app_name' => 'My Apartment System',
    'company_name' => 'ABC Company',
    'system_currency' => 'USD'
]);
```

### Via Model Methods:
```php
use App\Models\Settings;

// Get a setting
$value = Settings::get('app_name', 'Default');

// Set a setting
Settings::set('app_name', 'New Name');
```

## Available Settings Categories

### 1. **Application Settings**
- `app_name` - Application name
- `app_timezone` - Application timezone
- `app_locale` - Application locale/language

### 2. **Company Information**
- `company_name` - Company/Organization name
- `company_address` - Company address
- `company_phone` - Company phone number
- `company_email` - Company email address

### 3. **Email Configuration**
- `email_from_name` - Email sender name
- `email_from_address` - Email sender address

### 4. **System Preferences**
- `system_currency` - Default currency (USD, EUR, GBP, JPY)
- `system_date_format` - Date format (Y-m-d, d/m/Y, etc.)
- `system_time_format` - Time format (H:i:s, h:i A, etc.)

### 5. **Fiscal Period Settings**
- `fiscal_year_start` - Fiscal year start date (MM-DD format)
- `fiscal_auto_close` - Auto-close periods (yes/no)

### 6. **Notification Settings**
- `notification_payment_reminder` - Enable payment reminders (yes/no)
- `notification_lease_expiry` - Enable lease expiry notifications (yes/no)

## Usage Examples

### In Controllers:
```php
public function index()
{
    $appName = setting('app_name', 'AMS');
    $currency = setting('system_currency', 'USD');
    
    return view('dashboard', compact('appName', 'currency'));
}
```

### In Blade Views:
```blade
<h1>{{ setting('app_name', 'Apartment Management System') }}</h1>

<p>Currency: {{ setting('system_currency', 'USD') }}</p>
```

### In Config Files:
```php
// config/app.php
'name' => env('APP_NAME', setting('app_name', 'Laravel')),
'timezone' => setting('app_timezone', 'UTC'),
```

## API Routes

The following routes are available (admin only):

- `GET /admin/settings` - View settings page
- `PUT /admin/settings/batch` - Update multiple settings
- `PUT /admin/settings` - Update a single setting
- `DELETE /admin/settings/reset` - Reset all settings
- `DELETE /admin/settings` - Delete a specific setting
- `GET /admin/settings/{key}` - Get a specific setting (JSON API)

## Adding Custom Settings

You can add your own custom settings from the admin interface or programmatically:

```php
// Add a new setting
Settings::set('custom_setting_key', 'custom_value');

// Access it anywhere
$value = setting('custom_setting_key');
```

## Best Practices

1. **Use descriptive keys**: Use category prefixes (e.g., `app_`, `system_`, `email_`)
2. **Provide defaults**: Always provide sensible default values
3. **Cache when needed**: For frequently accessed settings, consider caching
4. **Document custom settings**: Keep track of custom settings you add

## Tips

- Settings are automatically created when you set them
- You can reset all settings to defaults using the "Reset All" button
- Settings are stored as strings in the database
- Use the batch update feature to update multiple settings efficiently

## Security

- Settings management is restricted to admin role only
- All routes are protected by authentication and role middleware
- Setting keys are validated before storage

---

**Need help?** Check the controller at `app/Http/Controllers/Admin/SettingsController.php` or the model at `app/Models/Settings.php`
