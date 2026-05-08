---
title: Usage
locale: en
status: stable
---

# Usage

```bash
# Aggregate hourly
php artisan pulse:aggregate

# Schedule it (Kernel.php):
$schedule->command('pulse:aggregate')->hourly();
```

Configure sample rate to reduce overhead in production:

```php
// config/admin-pulse.php
'sample_rate' => 0.1,    // 10%
'retention_days' => 30,
```

