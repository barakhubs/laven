<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    /**
     * Models whose changes we intentionally do NOT log —
     * they are either noisy, low-value, or contain sensitive tokens.
     */
    protected array $ignore = [
        \App\Models\PersonalAccessToken::class,
        \App\Models\ScheduleTaskHistory::class,
        \App\Models\PhoneOtp::class,
        \App\Models\NavigationItemTranslation::class,
        \App\Models\SettingTranslation::class,
        \App\Models\PageTranslation::class,
    ];

    /**
     * Fields that should never appear in old_values / new_values
     * (passwords, tokens, raw OTPs, etc.).
     */
    protected array $redact = [
        'password', 'remember_token', 'two_factor_code',
        'otp', 'otp_code', 'token', 'secret',
    ];

    // ----------------------------------------------------------------
    // Observer hooks
    // ----------------------------------------------------------------

    public function created(Model $model): void
    {
        $this->write('created', $model, null, $this->sanitize($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $dirty = $model->getDirty();
        if (empty($dirty)) {
            return;
        }

        $old = [];
        $new = [];
        foreach ($dirty as $field => $newVal) {
            if (in_array($field, ['updated_at'])) {
                continue; // skip pure timestamp-only updates
            }
            $old[$field] = $this->redactValue($field, $model->getOriginal($field));
            $new[$field] = $this->redactValue($field, $newVal);
        }

        // If the only dirty field was updated_at, skip entirely
        if (empty($old)) {
            return;
        }

        $this->write('updated', $model, $old, $new);
    }

    public function deleted(Model $model): void
    {
        $this->write('deleted', $model, $this->sanitize($model->getAttributes()), null);
    }

    // ----------------------------------------------------------------
    // Core writer
    // ----------------------------------------------------------------

    private function write(string $action, Model $model, ?array $old, ?array $new): void
    {
        if ($this->shouldIgnore($model)) {
            return;
        }

        $module = $this->moduleName($model);
        $label  = $this->recordLabel($model);

        $description = match ($action) {
            'created' => "{$module} #{$model->getKey()} created" . ($label ? ": {$label}" : ''),
            'updated' => "{$module} #{$model->getKey()} updated" . ($label ? " ({$label})" : ''),
            'deleted' => "{$module} #{$model->getKey()} deleted" . ($label ? ": {$label}" : ''),
            default   => "{$action} on {$module} #{$model->getKey()}",
        };

        try {
            AuditLog::log(
                $action,
                $module,
                $model->getKey(),
                $label,
                $old,
                $new,
                $description
            );
        } catch (\Throwable $e) {
            // Never let audit logging crash the application
            \Illuminate\Support\Facades\Log::warning('AuditObserver failed: ' . $e->getMessage(), [
                'model'  => get_class($model),
                'action' => $action,
                'id'     => $model->getKey(),
            ]);
        }
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function shouldIgnore(Model $model): bool
    {
        foreach ($this->ignore as $class) {
            if ($model instanceof $class) {
                return true;
            }
        }
        return false;
    }

    /**
     * Derive a clean module name from the model class, e.g.
     * App\Models\LoanPayment  →  "Loan Payment"
     * App\Models\SavingsAccount → "Savings Account"
     */
    private function moduleName(Model $model): string
    {
        // If the model exposes auditModuleName(), use it
        if (method_exists($model, 'auditModuleName')) {
            return $model->auditModuleName();
        }

        $short = class_basename($model);
        // Split CamelCase into words: LoanPayment → Loan Payment
        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $short);
    }

    /**
     * Build a short human-readable label for the record.
     * Models can implement auditLabel() to customise this.
     */
    private function recordLabel(Model $model): ?string
    {
        if (method_exists($model, 'auditLabel')) {
            return $model->auditLabel();
        }

        // Generic fallbacks based on common field names
        foreach (['name', 'title', 'first_name', 'account_number', 'member_no', 'loan_id'] as $field) {
            if (!empty($model->$field)) {
                // If first_name exists, try to append last_name
                if ($field === 'first_name') {
                    return trim($model->first_name . ' ' . ($model->last_name ?? ''));
                }
                return (string) $model->$field;
            }
        }

        return null;
    }

    private function sanitize(array $attributes): array
    {
        $out = [];
        foreach ($attributes as $key => $value) {
            $out[$key] = $this->redactValue($key, $value);
        }
        return $out;
    }

    private function redactValue(string $field, mixed $value): mixed
    {
        if (in_array(strtolower($field), $this->redact)) {
            return '[REDACTED]';
        }
        return $value;
    }
}