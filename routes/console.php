<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Shared hosting cron (Architecture.md section 9):
 *   * * * * * cd /home/user/quote && php artisan schedule:run >> /dev/null 2>&1
 *
 * The job is cheap and idempotent, so running it every minute is safe and gives
 * near-instant expiry. Running it daily instead is equally valid.
 */
Schedule::command('quotes:expire')->hourly();
