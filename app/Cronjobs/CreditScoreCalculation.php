<?php

namespace App\Cronjobs;

use App\Utilities\CreditScoreCalculator;

class CreditScoreCalculation {

    public function __invoke() {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        CreditScoreCalculator::recalculateAll();
    }

}