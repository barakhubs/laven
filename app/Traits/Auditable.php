<?php

namespace App\Traits;

/**
 * Auditable trait
 *
 * Add this to any model to customise how it appears in audit logs.
 *
 * Usage — add to any model:
 *
 *   use App\Traits\Auditable;
 *
 *   class Loan extends Model {
 *       use Auditable;
 *
 *       public function auditLabel(): string {
 *           return "Loan #{$this->id} — {$this->borrower->first_name ?? ''}";
 *       }
 *   }
 *
 * If you don't add the trait the observer still works fine —
 * it just uses its generic fallback label logic.
 */
trait Auditable
{
    /**
     * Override in your model to return a human-readable label for this record.
     * Shown in the "Record" column of the audit trail table.
     *
     * Example return values:
     *   "John Doe (MEM-0042)"
     *   "Loan #17 — UGX 5,000,000"
     *   "Savings A/C: 1001-0023"
     */
    public function auditLabel(): string
    {
        // Generic fallback — tries common name fields
        foreach (['name', 'title', 'account_number', 'member_no'] as $field) {
            if (!empty($this->$field)) {
                return (string) $this->$field;
            }
        }
        if (!empty($this->first_name)) {
            return trim($this->first_name . ' ' . ($this->last_name ?? ''));
        }
        return class_basename($this) . ' #' . $this->getKey();
    }

    /**
     * Override in your model to change the module name shown in audit logs.
     * Defaults to a spaced version of the class name (LoanPayment → "Loan Payment").
     */
    public function auditModuleName(): string
    {
        $short = class_basename($this);
        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $short);
    }
}