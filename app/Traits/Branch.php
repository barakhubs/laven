<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Branch {

    public static function bootBranch() {
        static::addGlobalScope('branch_id', function (Builder $builder) {
            if (auth()->check() && auth()->user()->user_type == 'user') {
                if (auth()->user()->all_branch_access == 1) {
                    if (session('branch_id') != '') {
                        $branch_id = session('branch_id') == 'default' ? null : session('branch_id');
                        return $builder->where('branch_id', $branch_id);
                    }
                } else {
                    return $builder->where('branch_id', auth()->user()->branch_id);
                }
            } else {
                if (session('branch_id') != '') {
                    $branch_id = session('branch_id') == 'default' ? null : session('branch_id');
                    return $builder->where('branch_id', $branch_id);
                }
            }
        });

    }

}