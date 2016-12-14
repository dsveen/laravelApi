<?php

namespace App\Services\IwmsApi;

class IwmsFactoryWmsService extends IwmsCoreService
{
    use IwmsCreateDeliveryOrderService;
    protected $wmsPlatform;

    public function __construct($wmsPlatform = "4px",$debug = 0)
    {
        $this->$wmsPlatform = $wmsPlatform;
        parent::__construct($wmsPlatform,$debug);
    }

    public function createDeliveryOrder()
    {
        $warehouseToIwms = $this->getWarehouseId($this->wmsPlatform);
        $request = $this->getDeliveryCreationRequest($warehouseToIwms);
        $responseData = $this->curlIwmsApi('create-delivery-order', $request["requestBody"]);
        $this->_saveIwmsDeliveryOrderResponseData($request["batchId"],$responseData);
        $this->sendIwmsReport($request["batchId"],$type);
    }

    public function queryDeliveryOrder()
    {
        $warehouseId = "4PXDG_PL"; $merchantId = "ESG";
        $iwmsWarehouseCode = $this->getIwmsWarehouseCode($warehouseId,$merchantId);
        $requestBody = array(
            "iwms_warehouse_code" => $iwmsWarehouseCode,
            );
        $responseData = $this->curlIwmsApi('query-delivery-order', $requestBody);
        print_r($responseData);exit();
        return $responseData;
    }

    public function queryProduct()
    {
        $requestBody = array(
            "sku" => "21695-AA-WH"
            );
        $this->curlIwmsApi('query-product', $requestBody);
    }

    public function getWarehouseToIwms($wmsPlatform)
    {
        $warehouseIdArr = array(
            '4px' => array("4PXDG_PL"),
        );
        return $warehouseIdArr[$wmsPlatform];
    }

    public function sendIwmsReport($batchId,$type)
    {

    }

}