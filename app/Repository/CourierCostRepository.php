<?php

namespace App\Repository;

use App\Models\CourierCost;
use App\Models\WeightCourier;

class CourierCostRepository
{
    const MAX_WEIGHT = 30;

    public function getCourierCost($deliveryCountry, $deliveryState = '', $weight)
    {
        if ($weight <= self::MAX_WEIGHT) {
            $weightId = WeightCourier::where('weight', '>=', $weight)->first()->id;
        } else {
            $weightId = 1;
            $weight = ceil($weight);
        }

        $shippingOptions = CourierCost::with('courierInfo')
            ->where('dest_country_id', $deliveryCountry)
            ->where('dest_state_id', $deliveryState)
            ->where('weight_id', $weightId)
            ->get();

        $freightCost = $shippingOptions->map(function ($shippingOption) use ($weight) {
            $courierId = $shippingOption->courier_id;
            $freightCost = ($weight <= self::MAX_WEIGHT) ? $shippingOption->delivery_cost : $shippingOption->cost_per_kg * $weight;
            $fuelSurchargeInPercent = $shippingOption->courierInfo->surcharge / 100;
            $currency = $shippingOption->currency_id;
            $type = $shippingOption->courierInfo->type;
            $allowBuiltInBattery = $shippingOption->courierInfo->allow_builtin_battery;
            $allowExternalBattery = $shippingOption->courierInfo->allow_external_battery;
            $courierName = $shippingOption->courierInfo->courier_name;
            $allowDdp = $shippingOption->courierInfo->incoterms_ddp;
            $allowDdu = $shippingOption->courierInfo->incoterms_ddu;
            return compact('courierId', 'freightCost', 'fuelSurchargeInPercent', 'currency', 'type', 'allowBuiltInBattery', 'allowExternalBattery', 'courierName', 'allowDdp', 'allowDdu');
        });

        return $freightCost;
    }
}
