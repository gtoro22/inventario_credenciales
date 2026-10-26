<?php

use Illuminate\Support\Facades\Schedule;

// Marca como "expiradas" las credenciales activas cuya fecha de expiración pasó.
Schedule::command('credentials:expire')->dailyAt('00:10')->onOneServer();
