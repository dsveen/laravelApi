<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    public $connection = 'mysql_esg';

    protected $table = 'brand';

    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = ['create_at'];
}