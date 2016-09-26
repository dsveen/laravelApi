<?php

namespace App\Services;

use App\Contracts\ApiPlatformInterface;
use App\Models\PlatformMarketOrder;
use App\Models\PlatformMarketOrderItem;
use App\Models\PlatformMarketShippingAddress;
use App\Models\Schedule;
use App\Models\So;
use PDF;
use Excel;
use Zipper;

//use lazada api package
use App\Repository\LazadaMws\LazadaOrder;
use App\Repository\LazadaMws\LazadaOrderList;
use App\Repository\LazadaMws\LazadaOrderItemList;
use App\Repository\LazadaMws\LazadaOrderStatus;
use App\Repository\LazadaMws\LazadaDocument;
use App\Repository\LazadaMws\LazadaShipmentProviders;
use App\Repository\LazadaMws\LazadaMultipleOrderItems;

class ApiLazadaService extends ApiBaseService  implements ApiPlatformInterface
{
	private $storeCurrency;
	function __construct()
	{

	}

	public function getPlatformId()
	{
		return "Lazada";
	}

	public function retrieveOrder($storeName)
	{
		$originOrderList=$this->getOrderList($storeName);
        if($originOrderList){
        	foreach($originOrderList as $order){
				if (isset($order['AddressShipping'])) {
					$addressId=$this->updateOrCreatePlatformMarketShippingAddress($order,$storeName);
				}
				$this->updateOrCreatePlatformMarketOrder($order,$addressId,$storeName);
				$originOrderItemList=$this->getOrderItemList($storeName,$order["OrderId"]);
				if($originOrderItemList){
					foreach($originOrderItemList as $orderItem){
						$this->updateOrCreatePlatformMarketOrderItem($order,$orderItem);
					}
				}
			}
			return true;
        }
	}

	public function getOrder($storeName,$orderId)
	{
		$this->lazadaOrder=new LazadaOrder($storeName);
		$this->storeCurrency=$this->lazadaOrder->getStoreCurrency();
		$this->lazadaOrder->setOrderId($orderId);
		$returnData=$this->lazadaOrder->fetchOrder();
		return $returnData;
	}

	public function getOrderList($storeName)
	{
		$this->lazadaOrderList=new LazadaOrderList($storeName);
		$this->storeCurrency=$this->lazadaOrderList->getStoreCurrency();
		$dateTime=date(\DateTime::ISO8601, strtotime($this->getSchedule()->last_access_time));
		$this->lazadaOrderList->setUpdatedAfter($dateTime);
		$originOrderList=$this->lazadaOrderList->fetchOrderList();
		$this->saveDataToFile(serialize($originOrderList),"getOrderList");
        return $originOrderList;
	}

    public function getPendingOrderList($storeName)
    {
        $this->lazadaOrderList=new LazadaOrderList($storeName);
        $this->lazadaOrderList->setStatus("pending");
        $originOrderList=$this->lazadaOrderList->fetchOrderList();
        return $originOrderList;
    }

	public function getOrderItemList($storeName,$orderId)
	{
		$this->lazadaOrderItemList = new LazadaOrderItemList($storeName);
		$this->lazadaOrderItemList->setOrderId($orderId);
		$orginOrderItemList=$this->lazadaOrderItemList->fetchOrderItemList();
		$this->saveDataToFile(serialize($orginOrderItemList),"getOrderItemList");
        return $orginOrderItemList;
	}

    //ESG SYSTEM SET ORDER TO READYSHIP AND GET DOCUMENT
    public function esgOrderReadyToShip($soNoList)
    {  
        $pdfFilePath = "/var/data/shop.eservicesgroup.com/marketplace/".date("Y")."/".date("m")."/".date("d")."/lazada/label/";
        $result = "";$returnData = "";
        $esgOrders = So::whereIn('so_no', $soNoList)
                ->where("biz_type","like","%Lazada")
                ->get();
        if(!$esgOrders->isEmpty()) {
            $returnData = $this->esgOrderApiReadyToShip($esgOrders,$pdfFilePath);
            return $result = array("response" => "success","message" => $returnData); 
        }else{
            return $result = array("response" => "failed","message" => "Invalid Order");
        }
    }

    public function merchantOrderFufillmentReadyToShip($orderGroup,$warehouse)
    {   
        $returnData = array();
        foreach($orderGroup as $order){   
            $warehouse = $this->checkWarehouseInventory($order,$warehouse);
            if($warehouse){
                $orderItemIds = array();
                foreach($order->platformMarketOrderItem as $orderItem){
                    $orderItemIds[] = $orderItem->order_item_id;
                }
                $shipmentProvider = "";
                $returnData[$order->so_no] = $this->setApiOrderReadyToShip($storeName,$orderItemIds,$shipmentProvider);
                $orderIdList[] = $order->platform_order_id;
            }
            $this->updateOrderStatusToShipped($storeName,$orderIdList);
        }
        $this->updateWarehouseInventory($warehouse);
        return $returnData;   
    }

    public function merchantOrderFufillmentGetDocument($orderGroups,$doucmentType)
    {
        $orderItemIds = array();$fileDate = date("h-i-s");
        $filePath = \Storage::disk('merchant')->getDriver()->getAdapter()->getPathPrefix();
        $pdfFilePath = $filePath.date("Y")."/".date("m")."/".date("d")."/label/";
        $doucmentFile = "";
        foreach($orderGroups as $storeName => $orderGroup){
            foreach($orderGroup as $order){
                $orderItem = $order->platformMarketOrderItem->first();
                $orderItemIds[] = $orderItem->order_item_id; 
            }
            $doucmentFile .= $this->getDocument($storeName,$orderItemIds,$doucmentType);
        }
        if($doucmentFile){
            $file = $doucmentType.$fileDate.'.pdf';
            PDF::loadHTML($doucmentFile)->save($pdfFilePath.$file);
            $pdfFile = url("api/merchant-api/download-label/".$file);
            return $pdfFile;
        }  
    }

    //run request to lazada api set order ready to ship one by one
    private function esgOrderApiReadyToShip($esgOrders,$pdfFilePath)
    {
        $doucmentTypeArr = ["invoice","carrierManifest","shippingLabel"];
        foreach($esgOrders as $esgOrder)
        {   
            $prefix = strtoupper(substr($platformId,3,2));
            $countryCode = strtoupper(substr($platformId, -2));
            $storeName = $prefix."LAZADA".$countryCode;
            //$shipmentProviders = $this->getShipmentProviders($storeName);

            $orderItemIds = array();$responseResult = "";
            $ordersIdList[] = $esgOrder->platform_order_no; 
            $extItemCd = $esgOrder->soItem->pluck("ext_item_cd");
            foreach($extItemCd as $extItem){
                $itemIds = explode("||",$extItem);
                foreach($itemIds as $itemId){
                    $orderItemIds[] = $itemId;
                }
            }
            $warehouseId = $esgOrder->soAllocate->first()->warehouse_id;
            $shipmentProvider = $this->getEsgShippingProvider($warehouseId,$countryCode);
            $returnData[$esgOrder->so_no] = $this->setApiOrderReadyToShip($storeName,$orderItemIds,$shipmentProvider);
            $orderItemId = array($orderItemIds[0]);
            foreach($doucmentTypeArr as $doucmentType ){
                $document[$doucmentType] .= $this->getDocument($storeName,$orderItemId,$doucmentType);
            } 
        }
        $this->updateOrderStatusToShipped($storeName,$esgOrders->plunk("txn_id"));
        $returnData["document"] = $this->getDocumentSaveToDirectory($document,$pdfFilePath);
        return $returnData;
    }

    private function setApiOrderReadyToShip($storeName,$orderItemIds,$shipmentProvider)
    {
        if ($orderItemIds) {
            $itemObject = array("orderItemIds" => $orderItemIds);
            $marketplacePacked = $this->setStatusToPackedByMarketplace($storeName,$orderItemIds,$shipmentProvider);
            if($marketplacePacked){
                $responseResult= $this->setStatusToReadyToShip($storeName,$itemObject);
            }
        }
        return $responseResult;
    }

    public function updateOrderStatusToShipped($storeName,$ordersIdList)
    {
        $orderList = $this->getMultipleOrderItems($storeName,$ordersIdList);
        foreach($orderList as $order){
            $orderObject = array(
                'order_status' => "ReadyToShip",
                'esg_order_status' => $this->getSoOrderStatus("ReadyToShip")
                );
            PlatformMarketOrder::update(
                ['platform_order_id' => $order['OrderId']],$orderObject
            );
            So::where('platform_order_id', "=",$order['OrderId'])->update(['status' => 5]);
            foreach($order["OrderItems"]["OrderItem"] as $orderItem){
                $object = array(
                    'platform_order_id' => $orderItem["OrderId"],
                    'order_item_id' => $orderItem["OrderItemId"],
                    'shipment_provider' => $orderItem["ShipmentProvider"],
                    'tracking_code' => $orderItem["TrackingCode"],
                    'status' => $orderItem["Status"],
                );
                PlatformMarketOrderItem::updateOrCreate(
                    [
                        'platform_order_id' => $orderItem['OrderId'],
                        'order_item_id' => $orderItem['OrderItemId']
                    ],$object
                );
            }
        }
    }

    public function exportTrackinNoCsvToDirectory($storeName,$orderList)
    {
        $filePath = "/var/data/shop.eservicesgroup.com/lazada/tracking/".date("Y")."/".date("m")."/".date("d")."/";
        $cellData[] = array('Marketplace', 'ESG SKU', 'SellerSku', 'OrderId', 'OrderItemId', 'Currency', 'ItemPrice', 'PaidPrice', 'TaxAmount', 'Name', 'PurchaseOrderNumber', 'PurchaseOrderId', 'PackageId');
        foreach($orderList as $order){
            foreach($order["OrderItems"]["OrderItem"] as $orderItem){
                //$orderItem["TrackingCode"];
                $cellRow = array(
                    'marketplace_id' => $storeName,
                    'sku' => $orderItem["Sku"],
                    'marketplace_sku' => $orderItem["Sku"],
                    'OrderId' => $orderItem["OrderId"],
                    'OrderItemId' => $orderItem["OrderItemId"],
                    'Currency' => $orderItem["Currency"],
                    'ItemPrice' => $orderItem["ItemPrice"],
                    'PaidPrice' => $orderItem["PaidPrice"],
                    'TaxAmount' => $orderItem["TaxAmount"],
                    'Name' => $orderItem["Name"],
                    'PurchaseOrderNumber' => $orderItem["PurchaseOrderNumber"],
                    'PurchaseOrderId' => $orderItem["PurchaseOrderId"],
                    'PackageId' => $orderItem["PackageId"],
                );
                $cellData[] = $cellRow;
            }
        }
        //Excel文件导出功能
        Excel::create('LazadaOrderTrackingNo', function ($excel) use ($cellData) {
            $excel->sheet('OrderTrackingNo', function ($sheet) use ($cellData) {
                $sheet->rows($cellData);
            });
        })->store('csv',$filePath);
    }

    private function getDocumentSaveToDirectory($document,$pdfFilePath)
    {
        $fileDate = date("h-i-s");
        if (!file_exists($pdfFilePath)) {
            mkdir($pdfFilePath, 0755, true);
        } 
        foreach($document as $documentType => $documentFile){
            $file = $pdfFilePath.$documentType.$fileDate.'.pdf';
            PDF::loadHTML($documentFile)->save($file);
            $doucmentPdf[$documentFile] = $file;
        }
        if($doucmentPdf) {
            $fileName ='readyToShipLabel'.date("H-i-s").'.zip';
            Zipper::make($pdfFilePath.$fileName)->add($doucmentPdf)->close();
            $zipperFile = url("lazada-api/donwload-label/".$fileName);
            return $zipperFile;
        }
    }

    public function getShipmentProviders($storeName)
    {
        $this->lazadaShipmentProviders=new LazadaShipmentProviders($storeName);
        $result = $this->lazadaShipmentProviders->fetchShipmentProviders();
        return $result;
    }

	public function setStatusToCanceled($storeName,$orderParam)
	{
		$this->lazadaOrderStatus = new LazadaOrderStatus($storeName);
		$this->lazadaOrderStatus->setOrderItemId($orderParam["orderItemId"]);
        if($orderParam["reason"])
		$this->lazadaOrderStatus->setReason($orderParam["reason"]);
        if($orderParam["reasonDetail"])
		$this->lazadaOrderStatus->setReasonDetail($orderParam["reasonDetail"]);
		$result = $this->lazadaOrderStatus->setStatusToCanceled();
		return $this->checkResultData($result,$this->lazadaOrderStatus);
	}

	public function setStatusToPackedByMarketplace($storeName,$orderItemIds,$shipmentProvider)
	{
		$this->lazadaOrderStatus = new LazadaOrderStatus($storeName);
		$this->lazadaOrderStatus->setOrderItemIds($orderItemIds);
		$this->lazadaOrderStatus->setDeliveryType("dropship");
		$this->lazadaOrderStatus->setShippingProvider($shipmentProvider);
		$orginOrderItemList=$this->lazadaOrderStatus->setStatusToPackedByMarketplace();
		$this->saveDataToFile(serialize($orginOrderItemList),"setStatusToPackedByMarketplace");
        return $orginOrderItemList;
	}

    public function getMultipleOrderItems($storeName,$orderIdList)
    {
        $this->lazadaMultipleOrderItems = new LazadaMultipleOrderItems($storeName);
        $this->lazadaMultipleOrderItems->setOrderIdList($orderIdList);
        $orginOrderItemList=$this->lazadaMultipleOrderItems->fetchMultipleOrderItems();
        $this->saveDataToFile(serialize($orginOrderItemList),"fetchMultipleOrderItems");
        return $orginOrderItemList;
    }

    public function getDocument($storeName,$orderItemIds,$documentType)
    {
        //$orderItemIds可以是不同的order中一个orderItemId
        $patterns = array('/class="logo"/');
        $replacements = array('class="page"');
        $this->lazadaDocument = new LazadaDocument($storeName);
        $this->lazadaDocument->setDocumentType($documentType);
        $this->lazadaDocument->setOrderItemIds($orderItemIds);
        $documents = $this->lazadaDocument->fetchDocument();
        if($documents){
            foreach($documents as $document){
                if(isset($document["File"]) && $document["DocumentType"] == $documentType){
                    $fileHtml = base64_decode($document["File"]);
                    //$filePdf = preg_replace($patterns, $replacements, $fileHtml,2);
                    return $fileHtml;
                }
            }
        }
    }

	public function setStatusToReadyToShip($storeName,$itemObject)
	{  
		$this->lazadaOrderStatus = new LazadaOrderStatus($storeName);
		$this->lazadaOrderStatus->setOrderItemIds($itemObject["orderItemIds"]);
		$this->lazadaOrderStatus->setDeliveryType("dropship");
        if(isset($itemObject["ShipmentProvider"]))
		$this->lazadaOrderStatus->setShippingProvider($itemObject["ShipmentProvider"]);
        if(isset($itemObject["TrackingNumber"]))
		$this->lazadaOrderStatus->setTrackingNumber($itemObject["TrackingNumber"]);
		$orginOrderItemList=$this->lazadaOrderStatus->setStatusToReadyToShip();
		$this->saveDataToFile(serialize($orginOrderItemList),"setStatusToReadyToShip");
        return $orginOrderItemList;
	}

	public function setStatusToShipped($storeName,$orderId)
	{
		$this->lazadaOrderStatus = new LazadaOrderStatus($storeName);
		$this->lazadaOrderStatus->setOrderItemId($orderItemId);
		$orginOrderItemList=$this->lazadaOrderStatus->setStatusToShipped();
		$this->saveDataToFile(serialize($orginOrderItemList),"setStatusToShipped");
        return $orginOrderItemList;
	}

	public function setStatusToFailedDelivery($storeName,$orderId)
	{
		$this->lazadaOrderStatus = new LazadaOrderStatus($storeName);
		$this->lazadaOrderStatus->setOrderItemId($orderItemId);
		$this->lazadaOrderStatus->setReason("reason");
		$this->lazadaOrderStatus->setReasonDetail("reasonDetail");
		$orginOrderItemList=$this->lazadaOrderStatus->setStatusToFailedDelivery();
		$this->saveDataToFile(serialize($orginOrderItemList),"setStatusToFailedDelivery");
        return $orginOrderItemList;
	}

	public function setStatusToDelivered($storeName,$orderId)
	{
		$this->lazadaOrderStatus = new LazadaOrderStatus($storeName);
		$this->lazadaOrderStatus->setOrderItemId($orderItemId);
		$orginOrderItemList=$this->lazadaOrderStatus->setStatusToDelivered();
		$this->saveDataToFile(serialize($orginOrderItemList),"setStatusToDelivered");
        return $orginOrderItemList;
	}

	//update or insert data to database
	public function updateOrCreatePlatformMarketOrder($order,$addressId,$storeName)
	{
		//orderStatus
		if(is_array($order['Statuses']["Status"])){
			$orderStatus=implode("||",$order['Statuses']["Status"]);
		}else{
			$orderStatus=studly_case($order['Statuses']["Status"]);
		}
		$object = [
            'platform' => $storeName,
            'biz_type' => "Lazada",
            'platform_order_id' => $order['OrderId'],
            'platform_order_no' => $order['OrderNumber'],
            'purchase_date' => $order['CreatedAt'],
            'last_update_date' => $order['UpdatedAt'],
            'order_status' => $orderStatus,
            'esg_order_status'=>$this->getSoOrderStatus($orderStatus),
            'buyer_email' => $order['OrderId']."@lazada-api.com",
            'currency' => $this->storeCurrency,
            'shipping_address_id' => $addressId
        ];
        if (isset($order['Price'])) {
            $object['total_amount'] = $order['Price'];
        }
        if (isset($order['OrderTotal']['CurrencyCode'])) {
            $object['currency'] = "US";
        }
        if (isset($order['PaymentMethod'])){
            $object['payment_method'] = $order['PaymentMethod'];
        }
        if (isset($order['CustomerFirstName'])) {
            $object['buyer_name'] = $order['CustomerFirstName'];
        }
		if (isset($order['CustomerLastName'])) {
            $object['buyer_name'] .=" ".$order['CustomerLastName'];
        }
        if (isset($order['PromisedShippingTime'])){
            $object['latest_ship_date'] = $order['PromisedShippingTime'];
        }
        if (isset($order['Remarks'])){
            $object['remarks'] = $order['Remarks'];
        }
        $platformMarketOrder = PlatformMarketOrder::updateOrCreate(
            ['platform_order_id' => $order['OrderId']],
            $object
        );
        return $platformMarketOrder;
	}

	public function updateOrCreatePlatformMarketOrderItem($order,$orderItem)
	{
		$object = [
	        'platform_order_id' => $order['OrderId'],
	        'seller_sku' => $orderItem['Sku'],
	        'order_item_id' => $orderItem['OrderItemId'],
	        'title' => $orderItem['Name'],
	        'quantity_ordered' => 1
	    ];
	    if (isset($orderItem['QuantityShipped'])) {
	        $object['quantity_shipped'] = $orderItem['QuantityShipped'];
	    }
	    if (isset($orderItem['ItemPrice'])) {
	        $object['item_price'] = $orderItem['ItemPrice'];
	    }
	    if (isset($orderItem['ShippingAmount'])) {
	        $object['shipping_price'] = $orderItem['ShippingAmount'];
	    }
	    if (isset($orderItem['ItemTax'])) {
	        $object['item_tax'] = $orderItem['TaxAmount'];
	    }
	    //need update
	    if (isset($orderItem['ShippingServiceCost'])) {
	        $object['shipping_tax'] = $orderItem['ShippingServiceCost'];
	    }
	    if (isset($orderItem['VoucherAmount'])) {
	        $object['promotion_discount'] = $orderItem['VoucherAmount'];
	    }
	    if (isset($orderItem['Status'])) {
	        $object['status'] = studly_case($orderItem['Status']);
	    }
	    if (isset($orderItem['ShippingProviderType'])) {
	        $object['ship_service_level'] = $orderItem['ShippingProviderType'];
	    }
	    if (isset($orderItem['ShipmentProvider'])) {
	        $object['shipment_provider'] = $orderItem['ShipmentProvider'];
	    }
	    if (isset($orderItem['TrackingCode'])) {
	        $object['tracking_code'] = $orderItem['TrackingCode'];
	    }
	    if (isset($orderItem['Reason'])) {
	        $object['reason'] = $orderItem['Reason'];
	    }
	    if (isset($orderItem['ReasonDetail'])) {
	        $object['reason_detail'] = $orderItem['ReasonDetail'];
	    }
	    if (isset($orderItem['PackageId'])) {
	        $object['package_id'] = $orderItem['PackageId'];
	    }
	    $platformMarketOrderItem = PlatformMarketOrderItem::updateOrCreate(
	        [
	            'platform_order_id' => $order['OrderId'],
	            'order_item_id' => $orderItem['OrderItemId']
	        ],
	        $object
	    );
	}

	public function updateOrCreatePlatformMarketShippingAddress($order,$storeName)
	{
		$object=array();
		$object['platform_order_id']=$order['OrderId'];
        $object['name'] = $order['AddressShipping']['FirstName']." ".$order['AddressShipping']['LastName'];
        $object['address_line_1'] = $order['AddressShipping']['Address1'];
        $object['address_line_2'] = $order['AddressShipping']['Address2'];
        $object['address_line_3'] = $order['AddressShipping']['Address3']."-".$order['AddressShipping']['Address4']."-".$order['AddressShipping']['Address5'];
        $object['city'] = $order['AddressShipping']['Address3'];
        $object['county'] = $order['AddressShipping']['Country'];
        $object['country_code'] = strtoupper(substr($storeName, -2));
        $object['district'] = $order['AddressShipping']['Ward'];
        $object['state_or_region'] = $order['AddressShipping']['Region'];
        $object['postal_code'] = $order['AddressShipping']['PostCode'];
        $object['phone'] = $order['AddressShipping']['Phone'];

        $object['bill_name'] = $order['AddressBilling']['FirstName']." ".$order['AddressBilling']['LastName'];
        $object['bill_address_line_1'] = $order['AddressBilling']['Address1'];
        $object['bill_address_line_2'] = $order['AddressBilling']['Address2'];
        $object['bill_address_line_3'] = $order['AddressBilling']['Address3']."-".$order['AddressBilling']['Address4']."-".$order['AddressBilling']['Address5'];
        $object['bill_city'] = $order['AddressBilling']['Address3'];
        $object['bill_county'] = $order['AddressBilling']['Country'];
        $object['bill_country_code'] = strtoupper(substr($storeName, -2));
        $object['bill_district'] = $order['AddressBilling']['Ward'];
        $object['bill_state_or_region'] = $order['AddressBilling']['Region'];
        $object['bill_postal_code'] = $order['AddressBilling']['PostCode'];
        $object['bill_phone'] = $order['AddressBilling']['Phone'];

        $platformMarketShippingAddress = PlatformMarketShippingAddress::updateOrCreate(['platform_order_id' => $order['OrderId']],$object
        );
        return $platformMarketShippingAddress->id;
	}


	private function checkResultData($result)
	{
		if($result){
			$this->saveDataToFile(serialize($result),"setStatusToCanceled");
            $result = array("status" => "success");
		}else{
            $result["status"] = "failed";
			$result["message"] = $this->lazadaOrderStatus->errorMessage();
			$result["code"] = $this->lazadaOrderStatus->errorCode();
		}
        return $result;
	}

	public function getSoOrderStatus($platformOrderStatus)
	{
		switch ($platformOrderStatus) {
			case 'Canceled':
				$status=PlatformMarketConstService::ORDER_STATUS_CANCEL;
				break;
			case 'Shipped':
				$status=PlatformMarketConstService::ORDER_STATUS_SHIPPED;
				break;
            case 'ReadyToShip':
                $status=PlatformMarketConstService::ORDER_STATUS_READYTOSHIP;
                break;
			case 'Unshipped':
			case 'Pending':
			case 'Processing':
				$status=PlatformMarketConstService::ORDER_STATUS_UNSHIPPED;
				break;
			case 'Delivered':
				$status=PlatformMarketConstService::ORDER_STATUS_DELIVERED;
				break;
			case 'Failed':
				$status=PlatformMarketConstService::ORDER_STATUS_FAIL;
				break;
			default:
				return null;
		}
		return $status;
	}

    /*
    public function getEsgShippingProvider($warehouseId,$shipmentProviders)
    {
        foreach ($shipmentProviders as $key => $shipmentProvider) {
            if(strstr($shipmentProvider['name'], 'HK'); ){
                return $shipmentProvider['name'];
            }
        }
    }*/

    public function getEsgShippingProvider($warehouseId,$countryCode)
    {
        $shipmentProvider = array(
            "ES_HK"=>array(
                "MY" => "AS-Poslaju-HK",      
                "SG" => "LGS-SG3",                
                "TH" => "LGS-TH3-HK",       
                "ID" => "LGS-LEX-ID-HK",
                "PH" => "AS-LBC-JZ-HK Sellers-LZ2"
            ),
            "ES_DGME"=>array(
                "MY" => "AS-Poslaju",      
                "SG" => "LGS-SG3",                
                "TH" => "LGS-TH3-HK",       
                "ID" => "LGS-Tiki-ID",
                "PH" => "LGS-PH1"
            )
        );
        if(isset($shipmentProvider[$warehouseId])){
            return $shipmentProvider[$warehouseId][$countryCode];
        }else{
            return null;
        }
    }

    public function alertSetOrderReadyToShip($storeName)
    {
        $pendingOrderList = $this->getPendingOrderList($storeName);
        $orderId = null;
        if($pendingOrderList){
            foreach($pendingOrderList as $pendingOrder){
                $expierDate = strtotime("+2 days",strtotime($pendingOrder["CreatedAt"]));
                $currentDate = strtotime(date("Y-m-d 23:59:59"));
                if($expierDate - $currentDate <= 0)  {
                    $orderId[] = $pendingOrder['OrderNumber'];
                }
            }
            return $orderId;
        }
    }

    public function sendAlertMailMessage($storeName,$esgOrders)
    {
        $subject = "MarketPlace: [{$storeName}] Order Ready To Ship Alert!\r\n";
        $message = "These order will be late for ready to ship. Please act now!\r\n";
        foreach($esgOrders as $esgOrder){
            $message .="ESG Order No ".$esgOrder->so_no." (Platform Order No ".$esgOrder->platform_order_id.") status is ".$esgOrder->status.".\r\n";
        }
        $message .= "Thanks\r\n";
        $this->sendMailMessage('storemanager@brandsconnect.net,fiona@etradegroup.net', $subject, $message);
        return false;
    }

}