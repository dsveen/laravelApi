<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IwmsDeliveryOrderLog extends Model
{
    //
    protected $primaryKey = 'id';
    protected $guarded = [];

    public function batchRequest()
    {
        return $this->belongsTo('App\Models\BatchRequest', 'batch_id');
    }

    public function iwmsLgsOrderStatusLog()
    {
        return $this->hasOne('App\Models\IwmsLgsOrderStatusLog', 'reference_no', "reference_no");
    }
}
