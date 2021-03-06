<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformMarketInventory extends Model
{
    //
    protected $table = 'platform_market_inventory';
    protected $primaryKey = 'id';
    protected $guarded = ['create_at'];

    public function mattelSkuMapping()
    {
        return $this->hasOne('App\Models\MattelSkuMapping', 'mattel_sku', 'mattel_sku');
    }

    public function merchantProductMapping()
    {
        return $this->hasOne('App\Models\MerchantProductMapping', 'merchant_sku','mattel_sku');
    }

    public function marketplaceLowStockAlertEmail()
    {
        return $this->hasOne('App\Models\MarketplaceAlertEmail', 'store_id', 'store_id')
                    ->where('type', '=', 'low_stock_alert')
                    ->where('status', '=', 1);
    }
}
