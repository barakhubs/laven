<?php

namespace App\Models;

use App\Traits\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;

class Member extends Model {
    use Notifiable, Branch;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'members';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected static function booted() {
        static::addGlobalScope('status', function (Builder $builder) {
            return $builder->where('status', 1);
        });
    }

    public function getCreatedAtAttribute($value) {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

    public function getNameAttribute() {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Clients matching $term, for every search box that finds clients. Each
     * word must match the name, member no, email, business name, city,
     * state, address or phone. A phone number matches however it's typed
     * (0774 123456, 774123456, +256774123456) whether it's stored as
     * +256774123456 or, on older records, 0774123456.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $isPhone = fn ($s) => preg_match('/^[\d\s()+.-]+$/', $s) === 1;
        $digits  = preg_replace('/\D/', '', $term);
        // A number typed with spaces is one term, not several words.
        $words = $isPhone($term) && strlen($digits) >= 6 ? [$digits] : preg_split('/\s+/', $term);

        foreach ($words as $word) {
            $query->where(function ($q) use ($word, $isPhone) {
                // LOWER on both sides: the live tables don't all use a
                // case-insensitive collation, and names are stored in capitals.
                $like = '%' . addcslashes(mb_strtolower($word), '%_\\') . '%';
                foreach (['first_name', 'last_name', 'business_name', 'member_no', 'email', 'city', 'state', 'address', 'mobile'] as $column) {
                    $q->orWhereRaw("LOWER(members.$column) LIKE ?", [$like]);
                }

                $digits   = preg_replace('/\D/', '', $word);
                $national = ltrim($digits, '0');
                if ($isPhone($word) && strlen($digits) >= 3 && $national !== '') {
                    $q->orWhere('members.mobile', 'like', "%$national%")
                        ->orWhereRaw("CONCAT(IFNULL(members.country_code, ''), TRIM(LEADING '0' FROM members.mobile)) LIKE ?", ["%$digits%"]);
                }
            });
        }

        return $query;
    }

    public function branch() {
        return $this->belongsTo('App\Models\Branch', 'branch_id')->withDefault();
    }

    public function user() {
        return $this->belongsTo('App\Models\User', 'user_id')->withDefault();
    }

    public function loan_officer() {
        return $this->belongsTo('App\Models\User', 'loan_officer_id')->withDefault();
    }

    public function loans() {
        return $this->hasMany('App\Models\Loan', 'borrower_id');
    }

    public function documents() {
        return $this->hasMany('App\Models\MemberDocument', 'member_id');
    }
}

