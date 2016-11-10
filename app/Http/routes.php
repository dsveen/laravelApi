<?php

/*
|--------------------------------------------------------------------------
| Routes File
|--------------------------------------------------------------------------
|
| Here is where you will register all of the routes in an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/
Route::auth();

$api = app('Dingo\Api\Routing\Router');

Route::group(['middleware' => 'cors'], function () {
    Route::post('oauth/access_token', function () {
        return Response::json(Authorizer::issueAccessToken());
    });
});

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| This route group applies the "web" middleware group to every route
| it contains. The "web" middleware group is defined in your HTTP
| kernel and includes session state, CSRF protection, and more.
|
*/

Route::group(['middleware' => 'auth'], function () {
    Route::get('/', function () {
        return view('welcome');
    });
});

Route::group(['middleware' => 'auth'], function () {
    Route::resource('tracer', 'TracerSkuController');
});

Route::group(['prefix' => 'v2', 'namespace' => 'V2', 'middleware' => 'auth.basic.once'], function () {
    Route::get('pricing/index', 'PricingController@index');
    Route::get('pricing/simulate', 'PricingController@simulate');
    Route::get('pricing/info', 'PricingController@getPriceInfo');
    Route::post('listingSku/save', 'ListingSkuManagement@save');
});

Route::group(['middleware' => ['cors']], function () {
    Route::post('gateway/upload-settlement', 'GatewayController@uploadSettlement');
    Route::post('gateway/upload-transaction', 'GatewayController@uploadTransaction');

    Route::get('amazon/getASIN', 'AmazonProduct@getMatchProductForId');
    Route::get('listingSku/index', 'ListingSkuManagement@index');
    Route::resource('/marketplaceCategory', 'MarketplaceCategoryController');
    Route::get('marketplaceCategory/marketplace/{id}', 'MarketplaceCategoryController@showTopCategoriesForControlId');
    Route::get('listingSku/getCategory', 'ListingSkuManagement@getCategory');
    Route::post('listingSku/add', 'ListingSkuManagement@add');
    Route::get('listingSku/getData', 'ListingSkuManagement@getData');

    Route::get('platform-market/product', 'PlatformMarketProductManage@getProductList');
    Route::get('platform-market/update-product-price', 'PlatformMarketProductManage@submitProductPrice');
    Route::resource('platform-market/upload-mapping', 'PlatformMarketProductManage@uploadMarketplacdeSkuMapping');
    Route::resource('platform-market/export-lazada-pricing', 'PlatformMarketProductManage@exportLazadaPricingCsv');
    Route::get('platform-market/download-xlsx/{file}', 'PlatformMarketProductManage@getMarketplacdeSkuMappingFile');
    Route::get('product-upload/donwload-example-file/{file}', 'Api\ProductUploadController@donwloadExampleFile');
    Route::get('order-delivery-cost/{soNo}', 'Api\DeliveryController@getOrderDeiveryCostBySoNo');
    Route::get('order-delivery-options/{soNo}', 'Api\DeliveryController@getOrderDeliveryOptionsBySoNo');
    // Route::get('mattel-sku-mapping-list', 'Api\MattelSkuMappingController@index');
    Route::get('amazon-commission-charge-report/{flexBatchId}', 'Api\CommissionChargeController@getAmazonCommissionChargeReport');
});

$api->version('v1', ['namespace' => 'App\Http\Controllers\Api', 'middleware' => ['api.auth', 'cors']], function ($api) {
    $api->resource('marketplace', 'MarketplaceController');
    $api->resource('brand', 'BrandController');
    $api->resource('merchant', 'MerchantController');
    $api->resource('supplier', 'SupplierController');
    $api->resource('country', 'CountryController');
    $api->resource('colour', 'ColourController');
    $api->resource('version', 'VersionController');
    $api->resource('category', 'CategoryController');
    $api->resource('hscode-category', 'HscodeCategoryController');
    $api->resource('warehouse/default-warehouse', 'WarehouseController@defaultWarehouse');
    $api->resource('marketplace-content-field', 'MarketplaceContentFieldController');
    $api->post('marketplace-content-export/setting', 'MarketplaceContentExportController@setting');
    $api->resource('marketplace-content-export', 'MarketplaceContentExportController');
    $api->post('product/product-mapping', 'ProductController@productMapping');
    $api->post('product/supplier-product', 'ProductController@supplierProduct');
    $api->post('product/weight-dimension', 'ProductController@weightDimension');
    $api->post('product/product-content', 'ProductController@productContent');
    $api->post('product/product-content-extend', 'ProductController@productContentExtend');
    $api->post('product/product-code', 'ProductController@productCode');
    $api->post('product/product-features', 'ProductController@productFeatures');
    $api->post('product/upload-image', 'ProductController@uploadProductImage');
    $api->post('product/delete-image', 'ProductController@deleteImage');
    $api->post('product/{sku}', 'ProductController@update');
    $api->get('product/{sku}/{lang}', 'ProductController@show');
    $api->resource('product', 'ProductController');
    $api->get('marketplace-product/search', 'MarketplaceProductController@search');
    $api->get('marketplace-product/export', 'MarketplaceProductController@export');
    $api->get('marketplace-product/estimate', 'MarketplaceProductController@estimate');
    $api->post('marketplace-product/bulk-update', 'MarketplaceProductController@bulkUpdate');
    $api->post('marketplace-product/add-update', 'MarketplaceProductController@addOrUpdate');
    $api->resource('marketplace-product', 'MarketplaceProductController');
    $api->post('product-upload', 'ProductUploadController@upload');
    $api->get('product-upload', 'ProductUploadController@index');
    $api->get('mattel-sku-mapping-upload', 'MattelSkuMappingController@upload');
    $api->post('mattel-sku-mapping-upload', 'MattelSkuMappingController@upload');
    $api->get('mattel-sku-mapping-list', 'MattelSkuMappingController@index');
    $api->get('mattel-sku-mapping-upload/donwload-example-file/{file}', 'MattelSkuMappingController@donwloadExampleFile');

    $api->get('download-mattel-sku-mapping', 'MattelSkuMappingController@downloadReport');

    $api->get('platform-market-inventory-upload', 'PlatformMarketInventoryController@upload');
    $api->post('platform-market-inventory-upload', 'PlatformMarketInventoryController@upload');
    $api->get('platform-market-inventory', 'PlatformMarketInventoryController@index');
    $api->get('platform-market-inventory-upload/donwload-example-file/{file}', 'PlatformMarketInventoryController@donwloadExampleFile');

    $api->get('download-inventory-report', 'PlatformMarketInventoryController@downloadReport');

    $api->resource('orders', 'OrderController');
    $api->resource('export-orders', 'OrderController@exportOrdersToExcel');

    // quotation api (delivery charge)

    $api->get('shipping-options/{id}', 'DeliveryController@getDeliveryOptionForSku');
});

$api->version('v1', ['namespace' => 'App\Http\Controllers\Api\Marketplace', 'middleware' => ['api.auth', 'cors']], function ($api) {
    $api->post('merchant-api/order-fufillment', 'MerchantApiController@orderFufillmentAction');
    $api->post('merchant-api/order-cancel-reason', 'MerchantApiController@getPlatformMarkplaceReasons');
    $api->post('merchant-api/scan-tracking-no', 'MerchantApiController@scanMerchantTrackingNo');
    $api->post('merchant-api/order-picking-list', 'MerchantApiController@getPickingList');
    $api->resource('lazada-api/ready-to-ship', 'LazadaApiController@esgOrderReadyToShip');
});

$api->version('v1', ['namespace' => 'App\Http\Controllers\Api\Marketplace', 'middleware' => [ 'cors']], function ($api) {
    $api->get('merchant-api/download-label/{file}', 'MerchantApiController@donwloadLabel');
    $api->get('lazada-api/donwload-label/{file}','LazadaApiController@donwloadLabel');
});

Route::get('platform/test', 'InterfacePlatformOrder@index');
