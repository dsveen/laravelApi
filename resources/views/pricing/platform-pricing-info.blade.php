@foreach($data as $platform => $platformInfo)
    <div class="panel-heading" role="tab" id="head_{{ $platform }}">
        <h4 class="panel-title">
            <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#{{ $platform }}"
               aria-expanded="true" aria-controls="collapseTwo">
                {{ $platform }}
            </a>
        </h4>
    </div>
    <div id="{{ $platform }}" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="head_{{ $platform }}"
         aria-expanded="true">
        <div class="panel-body">
            @if(count($platformInfo['deliveryOptions']) > 0)
                <table class="table table-bordered table-condensed">
                    <tbody>
                    <tr class="info">
                        <th>Type</th>
                        <th>Delivery Type</th>
                        <th>Price</th>
                        <th>Decl.</th>
                        <th>Tax</th>
                        <th>Duty</th>
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
                        <th>Proft</th>
                        <th>Margin</th>
                    </tr>
                    @foreach($platformInfo['deliveryOptions'] as $deliveryType => $item)
                        <tr>
                            <td>Cost</td>
                            <td>
                                <div class="radio">
                                    <label>
                                        <input type="radio" {{ $item['checked'] }} name="delivery_type_{{ $platform }}" value="{{ $deliveryType }}">{{ $deliveryType }}
                                    </label>
                                </div>
                            </td>
                            <td>
                                <input name="price" style="width: 70px" value="{{ $item['price'] }}" data-marketplace-sku="{{ $item['marketplaceSku'] }}" data-selling-platform="{{ $platform }}">
                            </td>
                            <td>{{ $item['declaredValue'] }}</td>
                            <td>{{ $item['tax'] }}</td>
                            <td>{{ $item['duty'] }}</td>
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
                        <td>Listing Status</td>
                        <td>
                            <select name="listingStatus" required="required">
                                <option value="">-- Select --</option>
                                <option value="Y" {{ ($platformInfo['listingStatus'] == 'Y') ? 'selected' : '' }}>Listed</option>
                                <option value="N" {{ ($platformInfo['listingStatus'] == 'N') ? 'selected' : '' }}>Not listed</option>
                            </select>
                        </td>
                        <td>Inventory</td>
                        <td>
                            <input type="text" name="inventory" id="inputInventory" value="{{ $platformInfo['inventory'] }}" required="required">
                        </td>
                        <td>
                            Latency
                        </td>
                        <td>
                            <input type="text" name="latency" id="inputLatency" value="{{ $platformInfo['fulfillmentLatency'] }}" required="required">
                        </td>

                        <td>
                            Amazon Brand
                        </td>
                        <td>
                            <input type="text" name="platformBrand" id="inputPlatformBrand" value="{{ $platformInfo['platformBrand'] }}">
                        </td>
                        <td>
                            Condition
                        </td>
                        <td>
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
                    </tr>
                    <tr>
                        <td>
                            Condition Note
                        </td>
                        <td colspan="19">
                            <input type="text" name="conditionNote" id="conditionNote" value="{{ $platformInfo['conditionNote'] }}">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="20" align="center">
                            <button type="button" data-platform="{{ $platform }}"
                                    class="btn btn-danger save_price_info"> Save
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            @else
                Please complete all the data before list this sku.
            @endif
        </div>
    </div>
@endforeach