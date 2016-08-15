@extends('layouts.pricing')
@section('sku-list')
  <div id="sku-list">
    <table class="table table-condensed table-responsive table-striped table-hover table-bordered">
      <thead>
      <tr>
        <th>Ref.SKU</th>
        <th>Name</th>
      </tr>
      </thead>
      <tbody>
      @forelse($skuList as $item)
        <tr class="marketplaceSku">
          <td class="col-md-4">
            <a data-pjax id="{{ $item->marketplace_sku }}" href="{{ url('/v2/pricing/index').'?marketplace='.old('marketplace').'&search='.old('search').'&marketplaceSku='.$item->marketplace_sku }}">
              {{ $item->marketplace_sku }}
            </a>
          </td>
          <td>{{ $item->name }}</td>
        </tr>
      @empty
        <tr><td>No SKU Found.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
@endsection
@section('content')

<div id="sku-listing-info">
  <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">

    @foreach($data as $platform => $platformInfo)
      <div class="panel panel-default">
        <div class="panel-heading" role="tab" id="head_{{ $platform }}">
          <h4 class="panel-title">
                        <span>
                            <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#{{ $platform }}" aria-expanded="false" aria-controls="collapseTwo">
                                {{ $platform }}
                            </a>
                        </span>
                        <span>
                            &nbsp;|&nbsp;
                          {{ $platformInfo['currency'] }}
                          &nbsp;|&nbsp;
                        </span>
                        <span>
                            {{ $platformInfo['price'] }}
                          &nbsp;|&nbsp;
                        </span>
                        <span>
                            {{ $platformInfo['delivery_type'] }}
                          &nbsp;|&nbsp;
                        </span>
                        <span>
                            {{ ($platformInfo['listingStatus'] === 'Y') ? 'Listed' : 'Not Listed' }}
                          &nbsp;|&nbsp;
                        </span>
                        <span class="price {{ ($platformInfo['margin'] > 0) ? 'text-success' : 'text-danger' }}">
                            {{ $platformInfo['margin'] }}%
                        </span>
                        @if($platformInfo['link'])
                        <span>
                            &nbsp;|&nbsp;
                            <a href="{{ $platformInfo['link'] }}" target="_blank">{{ $platformInfo['link'] }}</a>
                        </span>
                        @endif
          </h4>
        </div>
        <div id="{{ $platform }}" class="panel-collapse collapse" role="tabpanel" aria-labelledby="head_{{ $platform }}">
          <div class="panel-body">
            @if(count($platformInfo['deliveryOptions']) > 0)
              <table class="table table-bordered table-condensed" style="table-layout: fixed">
                <colgroup>
                  <col width="7%">
                  <col width="8%" class="hidden">
                  <col width="4%" class="hidden">
                  <col width="4%" class="hidden">
                  <col width="8%">
                  <col width="5%">
                  <col width="5%">
                  <col width="5%">
                  <col width="4%">
                  <col width="3%">
                  <col width="5%">
                  <col width="5%">
                  <col width="5%">
                  <col width="5%">
                  <col width="5%">
                  <col width="5%">
                  <col width="5%">
                  <col width="8%">
                  <col width="5%">
                </colgroup>

                <tbody>
                <tr class="info">
                  <th>Delivery Type</th>
                  <th>Price</th>
                  <th class="hidden">Decl.</th>
                  <th class="hidden">Tax</th>
                  <th class="hidden">Duty</th>
                  <th>esg COMM.</th>
                  <th>MP. COMM.</th>
                  <th>Listing Fee</th>
                  <th>Fixed Fee</th>
                  <th>PSP Fee</th>
                  <th>PSP Adm. Fee</th>
                  <th>Freight Cost</th>
                  <th>Supp. Cost</th>
                  <th>Acce. Cost</th>
                  <th>Total Cost</th>
                  <th>Delivery Charge</th>
                  <th>Total Charged</th>
                  <th>Profit</th>
                  <th>Margin</th>
                </tr>
                @foreach($platformInfo['deliveryOptions'] as $deliveryType => $item)
                  <tr>
                    <td>
                      <div class="radio">
                        <label><input type="radio" {{ $item['checked'] }} name="delivery_type_{{ $platform }}" value="{{ $deliveryType }}">{{ $deliveryType }}</label>
                      </div>
                    </td>
                    <td>
                      <input  name="price" style="width: 100%" value="{{ $item['price'] }}" data-marketplace-sku="{{ $item['marketplaceSku'] }}" data-selling-platform="{{ $platform }}">
                    </td>
                    <td class="hidden">{{ $item['declaredValue'] }}</td>
                    <td class="hidden">{{ $item['tax'] }}</td>
                    <td class="hidden">{{ $item['duty'] }}</td>
                    
                    <td>{{ $item['esgCommission'] }}</td>
                    <td>{{ $item['marketplaceCommission'] }}</td>
                    <td>{{ $item['marketplaceListingFee'] }}</td>
                    <td>{{ $item['marketplaceFixedFee'] }}</td>
                    <td>{{ $item['paymentGatewayFee'] }}</td>
                    <td>{{ $item['paymentGatewayAdminFee'] }}</td>
                    <td>{{ $item['freightCost'] }}</td>
                    <td>{{ $item['supplierCost'] }}</td>
                    <td>{{ $item['accessoryCost'] }}</td>
                    <td>{{ $item['totalCost'] }}</td>
                    <td>{{ $item['deliveryCharge'] }}</td>
                    <td>{{ $item['totalCharged'] }}</td>
                    <td data-name="profit">{{ $item['profit'] }}</td>
                    <td data-name="margin">{{ $item['margin'] }}</td>
                  </tr>
                @endforeach

                <tr>
                  <td colspan="2">Listing Status:</td>
                  <td colspan="4">
                    <select name="listingStatus" required="required">
                      <option value="">-- Select --</option>
                      <option value="Y" {{ ($platformInfo['listingStatus'] == 'Y') ? 'selected' : '' }}>Listed</option>
                      <option value="N" {{ ($platformInfo['listingStatus'] == 'N') ? 'selected' : '' }}>Not listed</option>
                    </select>
                  </td>
                  <td colspan="3">Inventory</td>
                  <td colspan="7">
                    <input type="text" name="inventory" id="inputInventory" value="{{ $platformInfo['inventory'] }}" required="required">
                  </td>
                </tr>
                <tr>
                  <td colspan="2">
                    <a href="/images/amazon_latency.jpg" target="_blank">
                      Latency
                    </a>
                  </td>
                  <td colspan="4">
                    <input type="text" name="latency" id="inputLatency" value="{{ $platformInfo['fulfillmentLatency'] }}" required="required">
                  </td>
                  <td colspan="3">Brand</td>
                  <td colspan="7">
                    <input type="text" name="platformBrand" id="inputPlatformBrand" value="{{ $platformInfo['platformBrand'] }}">
                  </td>
                </tr>
                <tr>
                  <td colspan="2">
                    Condition
                  </td>
                  <td colspan="4">
                    <select name="condition" id="condition">
                      <option value="New" {{ ($platformInfo['condition'] == 'New') ? 'selected' : '' }}>New</option>
                      <option value="UsedLikeNew" {{ ($platformInfo['condition'] == 'UsedLikeNew') ? 'selected' : '' }}>UsedLikeNew</option>
                      <option value="UsedVeryGood" {{ ($platformInfo['condition'] == 'UsedVeryGood') ? 'selected' : '' }}>UsedVeryGood</option>
                      <option value="UsedGood" {{ ($platformInfo['condition'] == 'UsedGood') ? 'selected' : '' }}>UsedGood</option>
                      <option value="UsedAcceptable" {{ ($platformInfo['condition'] == 'UsedAcceptable') ? 'selected' : '' }}>UsedAcceptable</option>
                      <option value="CollectibleLikeNew" {{ ($platformInfo['condition'] == 'CollectibleLikeNew') ? 'selected' : '' }}>CollectibleLikeNew</option>
                      <option value="CollectibleVeryGood" {{ ($platformInfo['condition'] == 'CollectibleVeryGood') ? 'selected' : '' }}>CollectibleVeryGood</option>
                      <option value="CollectibleGood" {{ ($platformInfo['condition'] == 'CollectibleGood') ? 'selected' : '' }}>CollectibleGood</option>
                      <option value="CollectibleAcceptable" {{ ($platformInfo['condition'] == 'CollectibleAcceptable') ? 'selected' : '' }}>CollectibleAcceptable</option>
                      <option value="Refurbished" {{ ($platformInfo['condition'] == 'Refurbished') ? 'selected' : '' }}>Refurbished</option>
                      <option value="Club" {{ ($platformInfo['condition'] == 'Club') ? 'selected' : '' }}>Club</option>
                    </select>
                  </td>
                  <td colspan="3">
                    Condition Note
                  </td>
                  <td colspan="7">
                    <input type="text" name="conditionNote" id="conditionNote" value="{{ $platformInfo['conditionNote'] }}">
                  </td>
                </tr>
                <tr>
                  <td colspan="2">Weight</td>
                  <td colspan="4">
                      {{ $platformInfo['weight'] }} kg
                  </td>
                  <td colspan="3">Volumetric Weight</td>
                  <td colspan="7">
                      {{ $platformInfo['vol_weight'] }} kg
                  </td>
                </tr>
                <tr>
                  <td colspan="16" align="center">
                    <button type="button" data-platform="{{ $platform }}" class="btn btn-danger save_price_info"> Save </button>
                  </td>
                </tr>
                </tbody>
              </table>
            @else
              Please complete all the data before list this sku.
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection
