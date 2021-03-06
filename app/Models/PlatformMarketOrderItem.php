<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformMarketOrderItem extends Model
{
    protected $table = 'platform_market_order_item';
    protected $primaryKey = 'id';
    protected $guarded = [];

    public function platformMarketOrder()
    {
        return $this->belongsTo('App\Models\PlatformMarketOrder');
    }

    public function marketplaceSkuMapping()
    {
        return $this->hasOne('App\Models\MarketplaceSkuMapping', 'marketplace_sku', 'seller_sku');
    }

    public function platformMarketInventory()
    {
        return $this->hasMany('App\Models\PlatformMarketInventory', 'marketplace_sku', 'seller_sku');
    }

}
