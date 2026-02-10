<?php
namespace App\Utilities;

class LoanCalculator
{
    public $payable_amount;
    private $amount;
    private $first_payment_date;
    private $interest_rate;
    private $term;
    private $term_period;
    private $late_payment_penalties;
    private $loan_amount;

    public function __construct($amount, $first_payment_date, $interest_rate, $term, $term_period, $late_payment_penalties, $loan_amount = null)
    {
        $this->amount                 = $amount;
        $this->first_payment_date     = $first_payment_date;
        $this->interest_rate          = $interest_rate;
        $this->term                   = $term;
        $this->term_period            = $term_period;
        $this->late_payment_penalties = $late_payment_penalties;
        $this->loan_amount            = $loan_amount ?? $amount; //It's used for flat rate and fixed rate
    }

    public function get_flat_rate()
    {
        $this->payable_amount = (($this->interest_rate / 100) * $this->amount) + $this->amount;

        $date             = $this->first_payment_date;
        $principal_amount = $this->amount / $this->term;
        $amount_to_pay    = $principal_amount + (($this->interest_rate / 100) * $principal_amount);
        $interest         = (($this->interest_rate / 100) * $this->loan_amount) / $this->term;
        $balance          = $this->amount;
        $penalty          = ($this->late_payment_penalties / 100) * $principal_amount;
        //$balance          = $this->payable_amount;
        //$interest         = (($this->interest_rate / 100) * $this->amount) / $this->term;
        //$penalty          = (($this->late_payment_penalties / 100) * $this->amount);

        $data = [];
        for ($i = 0; $i < $this->term; $i++) {
            $balance = $balance - $principal_amount;
            $data[]  = [
                'date'             => $date,
                'amount_to_pay'    => $amount_to_pay,
                'penalty'          => $penalty,
                'principal_amount' => $principal_amount,
                'interest'         => $interest,
                'balance'          => $balance,
            ];

            $date = date("Y-m-d", strtotime($this->term_period, strtotime($date)));
        }

        return $data;
    }

    public function get_fixed_rate()
    {
        $this->payable_amount = ((($this->interest_rate / 100) * $this->amount) * $this->term) + $this->amount;
        $date                 = $this->first_payment_date;
        $principal_amount     = $this->amount / $this->term;
        $amount_to_pay        = $principal_amount + (($this->interest_rate / 100) * $this->amount);
        $interest             = (($this->interest_rate / 100) * $this->loan_amount);
        $balance              = $this->amount;
        $penalty              = ($this->late_payment_penalties / 100) * $principal_amount;
        //$balance              = $this->payable_amount;
        //$interest             = (($this->interest_rate / 100) * $this->amount);
        //$penalty              = (($this->late_payment_penalties / 100) * $this->amount);

        $data = [];
        for ($i = 0; $i < $this->term; $i++) {
            $balance = $balance - $principal_amount;
            $data[]  = [
                'date'             => $date,
                'amount_to_pay'    => $amount_to_pay,
                'penalty'          => $penalty,
                'principal_amount' => $principal_amount,
                'interest'         => $interest,
                'balance'          => $balance,
            ];

            $date = date("Y-m-d", strtotime($this->term_period, strtotime($date)));
        }

        return $data;
    }

    public function get_mortgage()
    {
        $interestRate = $this->interest_rate / 100;

        //Calculate the per month interest rate
        $monthlyRate = $interestRate / 12;

        //Calculate the payment
        $payment = $this->amount * ($monthlyRate / (1 - pow(1 + $monthlyRate, -$this->term)));

        $this->payable_amount = $payment * $this->term;

        $date    = $this->first_payment_date;
        $balance = $this->amount;

        $data = [];
        for ($count = 0; $count < $this->term; $count++) {
            $interest         = $balance * $monthlyRate;
            $monthlyPrincipal = $payment - $interest;
            $amount_to_pay    = $interest + $monthlyPrincipal;
            $penalty          = ($this->late_payment_penalties / 100) * $monthlyPrincipal;

            $balance = $balance - $monthlyPrincipal;
            $data[]  = [
                'date'             => $date,
                'amount_to_pay'    => $amount_to_pay,
                'penalty'          => $penalty,
                'principal_amount' => $monthlyPrincipal,
                'interest'         => $interest,
                'balance'          => $balance,
            ];

            $date = date("Y-m-d", strtotime($this->term_period, strtotime($date)));
        }

        return $data;
    }

    public function get_one_time()
    {
        $this->payable_amount = (($this->interest_rate / 100) * $this->amount) + $this->amount;
        $date                 = $this->first_payment_date;
        $principal_amount     = $this->amount;
        $amount_to_pay        = $principal_amount + (($this->interest_rate / 100) * $principal_amount);
        $interest             = (($this->interest_rate / 100) * $this->amount);
        $balance              = $this->payable_amount;
        //$penalty              = (($this->late_payment_penalties / 100) * $this->amount);
        $penalty = ($this->late_payment_penalties / 100) * $principal_amount;

        $data    = [];
        $balance = $balance - $amount_to_pay;
        $data[]  = [
            'date'             => $date,
            'amount_to_pay'    => $amount_to_pay,
            'penalty'          => $penalty,
            'principal_amount' => $principal_amount,
            'interest'         => $interest,
            'balance'          => $balance,
        ];

        $date = date("Y-m-d", strtotime($this->term_period, strtotime($date)));

        return $data;
    }

    public function get_reducing_amount()
    {
        $interestRate = $this->interest_rate / 100;

        //Calculate the per month interest rate
        $monthlyRate = $interestRate / 12;

        //Calculate the payment
        $payment          = $this->amount * ($monthlyRate / (1 - pow(1 + $monthlyRate, -$this->term)));
        $monthlyPrincipal = $this->amount / $this->term;

        $this->payable_amount = $payment * $this->term;

        $date    = $this->first_payment_date;
        $balance = $this->amount;
        //$penalty = (($this->late_payment_penalties / 100) * $this->amount);
        $penalty = ($this->late_payment_penalties / 100) * $monthlyPrincipal;

        $data = [];
        for ($count = 0; $count < $this->term; $count++) {
            $interest      = $balance * $monthlyRate;
            $amount_to_pay = $interest + $monthlyPrincipal;

            $balance = $balance - $monthlyPrincipal;
            $data[]  = [
                'date'             => $date,
                'amount_to_pay'    => $amount_to_pay,
                'penalty'          => $penalty,
                'principal_amount' => $monthlyPrincipal,
                'interest'         => $interest,
                'balance'          => $balance,
            ];

            $date = date("Y-m-d", strtotime($this->term_period, strtotime($date)));
        }

        return $data;
    }

}
