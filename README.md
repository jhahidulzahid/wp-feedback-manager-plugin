# Feedback Manager - WordPress Plugin

A lightweight feedback management system with REST API, admin dashboard, and CSV export.

## Quick Installation

1. Upload `feedback-manager` folder to `/wp-content/plugins/`
2. Activate via WordPress Admin → Plugins
3. Database table auto-created on activation
4. Use shortcode `[feedback_form]` on any page

## Usage

**Frontend Form:**
```
[feedback_form]
[feedback_form title="Contact Us"]
```

**Admin Dashboard:**  
Navigate to **Tools → Feedback Manager**

## REST API Endpoint

**POST** `/wp-json/feedback-manager/v1/submit`
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "message": "Your feedback here",
  "nonce": "wp_nonce_value"
}
```

## WordPress Hooks Used

### Activation/Deactivation
- `register_activation_hook` - Creates database table
- `register_deactivation_hook` - Cleanup

### Admin
- `admin_menu` - Adds menu under Tools
- `admin_enqueue_scripts` - Loads admin assets
- `admin_init` - Handles CSV export

### Frontend
- `wp_enqueue_scripts` - Loads frontend assets
- `add_shortcode` - Registers `[feedback_form]`

### API & AJAX
- `rest_api_init` - Registers REST endpoints
- `wp_ajax_delete_feedback` - Handles delete requests

## Database Table

**Table:** `wp_feedback_manager` (prefix depends on installation)
```sql
CREATE TABLE wp_feedback_manager (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  email varchar(255) NOT NULL,
  message text NOT NULL,
  ip_address varchar(45) DEFAULT NULL,
  user_agent text DEFAULT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY email (email),
  KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Columns:**
- `id` - Unique identifier (auto-increment)
- `name` - Submitter name (max 255 chars)
- `email` - Submitter email (indexed)
- `message` - Feedback content
- `ip_address` - IP tracking for rate limiting
- `user_agent` - Browser info
- `created_at` - Timestamp (indexed for sorting)

## Security Features

- WordPress nonce verification
- Prepared SQL statements
- Input sanitization/validation
- XSS protection via escaping
- Rate limiting (30 second cooldown)
- Admin-only permissions

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+

## Version

**1.0.0** - Initial release

---
GPL v2 or later