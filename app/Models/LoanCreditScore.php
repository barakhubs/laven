<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanCreditScore extends Model {

    protected $table = 'loan_credit_scores';

    protected $fillable = [
        'loan_id', 'borrower_id', 'score', 'on_time_count',
        'late_count', 'overdue_count', 'total_schedules', 'last_calculated_at',
    ];

    public function loan() {
        return $this->belongsTo('App\Models\Loan', 'loan_id')->withDefault();
    }

    public function borrower() {
        return $this->belongsTo('App\Models\Member', 'borrower_id')->withDefault();
    }

    public function getRatingAttribute() {
        if ($this->score >= 90) return 'Excellent';
        if ($this->score >= 75) return 'Good';
        if ($this->score >= 60) return 'Fair';
        if ($this->score >= 40) return 'Poor';
        return 'Very Poor';
    }

    public function getRatingColorAttribute() {
        if ($this->score >= 90) return 'success';
        if ($this->score >= 75) return 'info';
        if ($this->score >= 60) return 'warning';
        return 'danger';
    }

}

