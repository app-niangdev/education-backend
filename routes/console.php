<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Menage des donnees de securite du login. La nuit, quand personne ne se
// connecte : la commande verrouille brievement des lignes que le login lit.
Schedule::command('login-security:prune')->dailyAt('03:30');
