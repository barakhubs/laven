<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'user_type',
        'action', 'module', 'record_id', 'record_label',
        'old_values', 'new_values', 'description',
        'ip_address', 'user_agent', 'url',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault(['name' => 'System']);
    }

    // ----------------------------------------------------------------
    // Static helper – call anywhere to write a log entry
    // ----------------------------------------------------------------

    /**
     * @param  string       $action      e.g. 'created', 'updated', 'deleted', 'approved', 'login'
     * @param  string       $module      e.g. 'Member', 'Loan', 'Transaction'
     * @param  int|null     $recordId
     * @param  string|null  $recordLabel human-readable, e.g. "John Doe (MEM-001)"
     * @param  array|null   $oldValues
     * @param  array|null   $newValues
     * @param  string|null  $description free-text summary
     */
    public static function log(
        string  $action,
        string  $module,
        ?int    $recordId    = null,
        ?string $recordLabel = null,
        ?array  $oldValues   = null,
        ?array  $newValues   = null,
        ?string $description = null
    ): self {
        $user = Auth::user();

        return static::create([
            'user_id'      => $user?->id,
            'user_name'    => $user?->name ?? 'System',
            'user_type'    => $user?->user_type ?? 'system',
            'action'       => $action,
            'module'       => $module,
            'record_id'    => $recordId,
            'record_label' => $recordLabel,
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'description'  => $description,
            'ip_address'   => Request::ip(),
            'user_agent'   => substr(Request::userAgent() ?? '', 0, 500),
            'url'          => substr(Request::fullUrl(), 0, 500),
        ]);
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('user_name', 'like', "%{$term}%")
              ->orWhere('record_label', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('ip_address', 'like', "%{$term}%");
        });
    }

    // ----------------------------------------------------------------
    // Accessors
    // ----------------------------------------------------------------

    public function getActionBadgeAttribute(): string
    {
        return match ($this->action) {
            'created'  => 'badge-success',
            'updated'  => 'badge-primary',
            'deleted'  => 'badge-danger',
            'approved' => 'badge-info',
            'rejected' => 'badge-warning',
            'login'    => 'badge-secondary',
            'logout'   => 'badge-light',
            default    => 'badge-dark',
        };
    }
}