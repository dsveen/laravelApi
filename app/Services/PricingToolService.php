<?php

namespace App\Services;

use App\Models\CountryState;
use App\Models\CountryTax;
use App\Models\Declaration;
use App\Models\DeclarationException;
use App\Models\ExchangeRate;
use App\Models\HscodeDutyCountry;
use App\Models\Marketplace;
use App\Models\MerchantProductMapping;
use App\Models\MpCategoryCommission;
use App\Models\MpControl;
use App\Models\MpFixedFee;
use App\Models\MpListingFee;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\ProductComplementaryAcc;
use App\Models\Quotation;
use App\Models\SupplierProd;
use App\Models\Warehouse;
use App\Models\WeightCourier;
use Illuminate\Http\Request;

class PricingToolService
{
    private $product;
    private $destination;
    private $exchangeRate;
    private $marketplaceControl;
    private $adjustRate = 0.9725;

    public function __construct()
    {
    }

    public function getPricingInfo(Request $request)
    {
        $this->product = Product::findOrFail($request->input('sku'));
        $this->destination = CountryState::firstOrNew(['country_id' => $request->input('country'), 'is_default_state' => 1]);
        if ($this->destination->state_id === null) {
            $this->destination->state_id = '';
        }

        $this->marketplaceControl = MpControl::whereMarketplaceId($marketplaceId = $request->input('marketplace'))
            ->whereCountryId($this->destination->country_id)
            ->firstOrFail();

        $this->exchangeRate = ExchangeRate::whereFromCurrencyId('HKD')
            ->whereToCurrencyId($this->marketplaceControl->currency_id)
            ->firstOrFail();

        $declaredValue = $this->getDeclaredValue($request);
        $tax = $this->getTax($request, $declaredValue);
        $duty = $this->getDuty($request, $declaredValue);

        $targetMargin = $this->getTargetMargin($request);
        $marketplaceCommission = $this->getMarketplaceCommission($request);
        $marketplaceListingFee = $this->getMarketplaceListingFee($request);
        $marketplaceFixedFee = $this->getMarketplaceFixedFee($request);
        $paymentGatewayFee = $this->getPaymentGatewayFee($request);
        $paymentGatewayAdminFee = $this->getPaymentGatewayAdminFee($request);
        $freightCost = $this->getQuotationCost($request);
        $warehouseCost = $this->getWarehouseCost($request);
        $supplierCost = $this->getSupplierCost($request);
        $accessoryCost = $this->getAccessoryCost($request);
        $deliveryCharge = 0;

        $pricingType = $this->getMerchantType($request);

        $totalCharged = $request->input('price') + $deliveryCharge;

        $priceInfo = [];
        $deliveryOptions = ['STD', 'EXPED', 'EXP', 'FBA', 'MCF'];
        foreach ($deliveryOptions as $deliveryType) {
            if (in_array($deliveryType, array_keys($freightCost))) {
                $priceInfo[$deliveryType] = [];
                $priceInfo[$deliveryType]['tax'] = $tax;
                $priceInfo[$deliveryType]['duty'] = $duty;
                $priceInfo[$deliveryType]['marketplaceCommission'] = $marketplaceCommission;
                $priceInfo[$deliveryType]['marketplaceListingFee'] = $marketplaceListingFee;
                $priceInfo[$deliveryType]['marketplaceFixedFee'] = $marketplaceFixedFee;
                $priceInfo[$deliveryType]['paymentGatewayFee'] = $paymentGatewayFee;
                $priceInfo[$deliveryType]['paymentGatewayAdminFee'] = $paymentGatewayAdminFee;
                $priceInfo[$deliveryType]['freightCost'] = $freightCost[$deliveryType];
                $priceInfo[$deliveryType]['warehouseCost'] = $warehouseCost;
                $priceInfo[$deliveryType]['supplierCost'] = $supplierCost;
                $priceInfo[$deliveryType]['accessoryCost'] = $accessoryCost;
                $priceInfo[$deliveryType]['deliveryCharge'] = $deliveryCharge;
                $priceInfo[$deliveryType]['totalCost'] = array_sum($priceInfo[$deliveryType]);
                $priceInfo[$deliveryType]['targetMargin'] = $targetMargin;

                $priceInfo[$deliveryType]['price'] = $request->input('price');
                $priceInfo[$deliveryType]['declaredValue'] = $declaredValue;
                $priceInfo[$deliveryType]['totalCharged'] = $totalCharged;
                $priceInfo[$deliveryType]['marketplaceSku'] = $request->input('marketplaceSku');

                if ($request->input('selectedDeliveryType') == $deliveryType) {
                    $priceInfo[$deliveryType]['checked'] = 'checked';
                } else {
                    $priceInfo[$deliveryType]['checked'] = '';
                }

                if ($request->input('price') > 0) {
                    $priceInfo[$deliveryType]['profit'] = $priceInfo[$deliveryType]['totalCharged'] - $priceInfo[$deliveryType]['totalCost'];
                    $priceInfo[$deliveryType]['margin'] = round($priceInfo[$deliveryType]['profit'] / $request->input('price') * 100, 2);
                } else {
                    $priceInfo[$deliveryType]['profit'] = 'N/A';
                    $priceInfo[$deliveryType]['margin'] = 'N/A';
                }
            }
        }

        return $priceInfo;
    }

    public function getMerchantType(Request $request)
    {
        $merchantInfo = MerchantProductMapping::join('merchant_client_type', 'merchant_client_type.merchant_id', '=', 'merchant_product_mapping.merchant_id')
            ->select(['revenue_value', 'cost_value'])
            ->where('sku', '=', $request->sku)
            ->where('merchant_client_type.client_type', '=', 'ACCELERATOR')
            ->firstOrFail();
        if ($merchantInfo->revenue_value !== null) {
            return 'revenue';
        } elseif ($merchantInfo->cost_value !== null) {
            return 'cost';
        } else {
            return false;
        }
    }

    public function getTargetMargin(Request $request)
    {
        $targetMargin = 0;
        $merchantInfo = MerchantProductMapping::join('merchant_client_type', 'merchant_client_type.merchant_id', '=', 'merchant_product_mapping.merchant_id')
            ->select(['revenue_value', 'cost_value'])
            ->where('sku', '=', $request->sku)
            ->where('merchant_client_type.client_type', '=', 'ACCELERATOR')
            ->firstOrFail();

        if ($merchantInfo->revenue_value) {
            $targetMargin = $merchantInfo->revenue_value;
        }

        return round($targetMargin, 2);
    }

    public function getDeclaredValue(Request $request)
    {
        $exception = DeclarationException::where('platform_type', '=', 'ACCELERATOR')
            ->select(['absolute_value', 'declared_ratio', 'max_absolute_value'])
            ->where('delivery_country_id', '=', $this->destination->country_id)
            ->where('ref_from_amt', '>=', $request->input('price'))
            ->where('ref_to_amt_exclusive', '<', $request->input('price'))
            ->where('status', '=', 1)
            ->first();

        if ($exception) {
            if ($exception->absolute_value > 0) {
                $declaredValue = $exception->absolute_value;
            } else {
                $declaredValue = $exception->declared_ratio * $request->input('price') / 100;
                $declaredValue = ($exception->max_absolute_value > 0) ? min($declaredValue, $exception->max_absolute_value) : $declaredValue;
            }
        } else {
            $exception = Declaration::where('platform_type', '=', 'ACCELERATOR')
                ->select(['default_declaration_percent'])
                ->firstOrFail();
            $declaredValue = $exception->default_declaration_percent * $request->input('price') / 100;
        }

        return round($declaredValue, 2);
    }

    public function getTax(Request $request, $declaredValue)
    {
        $tax = 0;
        $countryTax = CountryTax::where('country_id', '=', $this->destination->country_id)
            ->select(['tax_percentage', 'critical_point_threshold'])
            ->where('state_id', '=', $this->destination->state_id)
            ->first();

        if ($countryTax) {
            $tax = $countryTax->tax_percentage * $declaredValue / 100;
            if ($request->input('price') > $countryTax->critical_point_threshold) {
                $tax = $countryTax->absolute_amount + $tax;
            }
        }

        return round($tax, 2);
    }

    public function getDuty(Request $request, $declaredValue)
    {
        $dutyInfo = HscodeDutyCountry::join('product', 'product.hscode_cat_id', '=', 'hscode_duty_country.hscode_cat_id')
            ->select(['hscode_duty_country.duty_in_percent'])
            ->where('sku', '=', $request->sku)
            ->where('hscode_duty_country.country_id', '=', $this->destination->country_id)
            ->firstOrFail();

        return round($declaredValue * $dutyInfo->duty_in_percent / 100, 2);
    }

    public function getMarketplaceCommission(Request $request)
    {
        $marketplaceCommission = 0;

        $categoryCommission = MpCategoryCommission::join('marketplace_sku_mapping', 'mp_id', '=', 'mp_sub_category_id')
            ->where('marketplace_sku', '=', $request->input('marketplaceSku'))
            ->where('marketplace_id', '=', $request->input('marketplace'))
            ->where('country_id', '=', $request->input('country'))
            ->select(['mp_commission', 'maximum'])
            ->first();
        if ($categoryCommission) {
            $marketplaceCommission = min($request->input('price') * $categoryCommission->mp_commission / 100, $categoryCommission->maximum);
        }

        return round($marketplaceCommission, 2);
    }

    public function getMarketplaceListingFee(Request $request)
    {
        $marketplaceListingFee = 0;
        $controlId = MpControl::select(['control_id'])
            ->where('marketplace_id', '=', $request->input('marketplace'))
            ->where('country_id', '=', $this->destination->country_id)
            ->firstOrFail()
            ->control_id;

        $mpListingFee = MpListingFee::select('mp_listing_fee')
            ->where('control_id', '=', $controlId)
            ->where('from_price', '<=', $request->input('price'))
            ->where('to_price', '>', $request->input('price'))
            ->first();

        if ($mpListingFee) {
            $marketplaceListingFee = $mpListingFee->mp_listing_fee;
        }

        return round($marketplaceListingFee, 2);
    }

    public function getMarketplaceFixedFee(Request $request)
    {
        $marketplaceFixedFee = 0;
        $controlId = MpControl::select(['control_id'])
            ->where('marketplace_id', '=', $request->input('marketplace'))
            ->where('country_id', '=', $this->destination->country_id)
            ->firstOrFail()
            ->control_id;

        $mpFixedFee = MpFixedFee::select('mp_fixed_fee')
            ->where('control_id', '=', $controlId)
            ->where('from_price', '<=', $request->input('price'))
            ->where('to_price', '>', $request->input('price'))
            ->first();

        if ($mpFixedFee) {
            $marketplaceFixedFee = $mpFixedFee->mp_fixed_fee;
        }

        return round($marketplaceFixedFee, 2);
    }

    public function getPaymentGatewayFee(Request $request)
    {
        $account = substr($request->input('marketplace'), 0, 2);
        $marketplaceId = substr($request->input('marketplace'), 2);
        $countryCode = $request->input('country');
        $countryCode = ($countryCode == 'GB') ? 'uk' : $countryCode;

        $paymentGatewayId = strtolower(implode('_', [$account, $marketplaceId, $countryCode]));
        $paymentGatewayRate = PaymentGateway::findOrFail($paymentGatewayId)->payment_gateway_rate;

        return round($request->input('price') * $paymentGatewayRate / 100, 2);
    }

    public function getPaymentGatewayAdminFee(Request $request)
    {
        $account = substr($request->input('marketplace'), 0, 2);
        $marketplaceId = substr($request->input('marketplace'), 2);
        $countryCode = $request->input('country');
        $countryCode = ($countryCode == 'GB') ? 'uk' : $countryCode;

        $paymentGatewayId = strtolower(implode('_', [$account, $marketplaceId, $countryCode]));
        $paymentGateway = PaymentGateway::findOrFail($paymentGatewayId);
        $paymentGatewayAdminFee = $paymentGateway->admin_fee_abs + $request->input('price') * $paymentGateway->admin_fee_percent / 100;

        return round($paymentGatewayAdminFee, 2);
    }

    public function getQuotationCost(Request $request)
    {
        $freightCost = [];
        $quotation = new Quotation();
        $quotationVersion = $quotation->getAcceleratorQuotationByProduct($this->product);

        $actualWeight = WeightCourier::getWeightId($this->product->weight);
        $volumeWeight = WeightCourier::getWeightId($this->product->vol_weight);
        $battery = $this->product->battery;

        if ($battery == 1) {
            $quotationVersion->forget('acc_external_postage');
        }

        $marketplace = $request->get('marketplace');
        $quotation = collect();
        foreach ($quotationVersion as $quotationType => $quotationVersionId) {
            // Lazada only use EXP.
            if (substr($marketplace, 2) === 'LAZADA' && $quotationType !== 'acc_courier_exp') {
                continue;
            }

            if (($quotationType == 'acc_builtin_postage') || ($quotationType == 'acc_external_postage')) {
                $weight = $actualWeight;
            } else {
                $weight = max($actualWeight, $volumeWeight);
            }

            $quotationItem = Quotation::getQuotation($this->destination, $weight, $quotationVersionId);
            if ($quotationItem) {
                $quotation->push($quotationItem);
            }
        }

        $availableQuotation = $quotation->filter(function ($quotationItem) use ($battery) {
            switch ($battery) {
                case '1':
                    if ($quotationItem->courierInfo->allow_builtin_battery) {
                        return true;
                    }
                    break;

                case '2':
                    if ($quotationItem->courierInfo->allow_external_battery) {
                        return true;
                    }
                    break;

                default:
                    return true;
                        break;
            }
        });

        // TODO: if $availableQuotation contains both built-in and external quotation, should choose the cheapest quotation.

        // convert HKD to target currency.
        $currencyRate = $this->exchangeRate->rate;
        $adjustRate = $this->adjustRate;
        $quotationCost = $availableQuotation->map(function ($item) use ($currencyRate, $adjustRate) {
            $item->cost = round($item->cost * $currencyRate / $adjustRate, 2);

            return $item;
        })->pluck('cost', 'quotation_type')->toArray();

        foreach ($quotationCost as $quotationType => $cost) {
            switch ($quotationType) {
                case 'acc_builtin_postage':
                case 'acc_external_postage':
                    $freightCost['STD'] = $cost;
                    break;

                case 'acc_courier':
                    $freightCost['EXPED'] = $cost;
                    break;

                case 'acc_courier_exp':
                    $freightCost['EXP'] = $cost;
                    break;

                case 'acc_fba':
                    $freightCost['FBA'] = $cost;
                    break;

                case 'acc_mcf':
                    $freightCost['MCF'] = $cost;
                    break;
            }
        }

        return $freightCost;
    }

    public function getSupplierCost(Request $request)
    {
        $supplierProd = SupplierProd::where('prod_sku', '=', $this->product->sku)
            ->where('order_default', '=', 1)
            ->firstOrFail();

        return round($supplierProd->pricehkd * $this->exchangeRate->rate / $this->adjustRate, 2);
    }

    public function getWarehouseCost(Request $request)
    {
        $cost = 0;
        $warehouseId = $this->product->default_ship_to_warehouse;
        if (!$warehouseId) {
            $warehouseId = $this->product->merchantProductMapping->merchant->default_ship_to_warehouse;
        }

        if ($warehouseId) {
            $warehouse = Warehouse::find($warehouseId);
            $currencyRate = ExchangeRate::whereFromCurrencyId($warehouse->currency_id)
                ->whereToCurrencyId($this->marketplaceControl->currency_id)
                ->first()->rate;

            $cost = ( $warehouse->warehouseCost->book_in_fixed
                    + $warehouse->warehouseCost->additional_book_in_per_kg * $this->product->weight
                    + $warehouse->warehouseCost->pnp_fixed
                    + $warehouse->warehouseCost->additional_pnp_per_kg * $this->product->weight )
                * $currencyRate * $this->adjustRate;
        }

        return round($cost, 2);
    }

    public function getAccessoryCost(Request $request)
    {
        $accessoryCost = 0;
        $accessoryProduct = ProductComplementaryAcc::whereMainprodSku($this->product->sku)
            ->whereDestCountryId($this->destination->country_id)
            ->whereStatus(1)
            ->first();

        if ($accessoryProduct) {
            $accessoryProductCost = SupplierProd::where('prod_sku', '=', $accessoryProduct->accessory_sku)
                ->where('order_default', '=', 1)
                ->firstOrFail();

            $accessoryCost = $accessoryProductCost->pricehkd * $this->exchangeRate->rate / $this->adjustRate;
        }

        return round($accessoryCost, 2);
    }
}
