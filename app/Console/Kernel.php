<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel {

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule) {
        $schedule->call(new \App\Cronjobs\YearlyMaintenanceFeePosting)->hourly();
        $schedule->call(new \App\Cronjobs\OverdueLoanNotification)->everyThirtyMinutes();
        $schedule->call(new \App\Cronjobs\UpcommingLoanNotification)->everyTenMinutes();
        $schedule->call(new \App\Cronjobs\CreditScoreCalculation)->dailyAt('01:00');
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     *
     * @return \DateTimeZone|string|null
     */
    protected function scheduleTimezone()
    {
        try {
            $timeZone = get_option('timezone', 'Asia/Dhaka');
        } catch (\Throwable $e) {
            $timeZone = 'Asia/Dhaka';
        }
        config(['app.timezone' =>  $timeZone ]);
        return $timeZone;
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands() {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}

