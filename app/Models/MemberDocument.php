<?php

namespace App\Models;

use App\Traits\Member;
use Illuminate\Database\Eloquent\Model;

class MemberDocument extends Model {

    use Member;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'member_documents';

    public function member() {
        return $this->belongsTo('App\Models\Member', 'member_id')->withDefault();
    }
}