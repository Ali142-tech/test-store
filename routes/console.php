<?php

use Illuminate\Support\Facades\Schedule;

// Requires a cron entry: * * * * * php /var/www/store/artisan schedule:run  (see docs/DEPLOYMENT.md)
Schedule::command('quotes:expire')->hourly();
Schedule::command('carts:remind')->hourly();
Schedule::command('stock:low-alert')->dailyAt('08:00');
Schedule::command('model:prune')->daily();
