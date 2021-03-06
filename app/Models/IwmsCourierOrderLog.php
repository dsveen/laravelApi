<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IwmsCourierOrderLog extends Model
{
    //
    protected $guarded = ["id"];

    public function batchRequest()
    {
        return $this->belongsTo('App\Models\BatchRequest', 'batch_id');
    }

    public function so()
    {
        return $this->belongsTo('App\Models\So', 'reference_no',"so_no");
    }
}
