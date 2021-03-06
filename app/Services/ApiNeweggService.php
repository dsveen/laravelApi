<?php

namespace App\Services;

use App\Contracts\ApiPlatformInterface;
use App\Models\PlatformMarketOrder;
use App\Models\PlatformMarketOrderItem;
use App\Models\PlatformMarketShippingAddress;
use App\Models\Schedule;

// Newegg API
use App\Repository\NeweggMws\NeweggOrderList;
use App\Repository\NeweggMws\NeweggOrderItemList;
use App\Repository\NeweggMws\NeweggOrderStatus;
// below not yet set up
use App\Models\CourierInfo;
use App\Models\So;
// test feature 1

class ApiNeweggService implements ApiPlatformInterface
{
    use ApiBaseOrderTraitService;
    private $storeCurrency;

    public function getPlatformId()
    {
        return "Newegg";
    }

    public function retrieveOrder($storeName,$schedule)
    {
        $this->setSchedule($schedule);
        $originOrderList = $this->getOrderList($storeName);
        $orderInfoList = $originOrderList["OrderInfoList"];

        if($orderInfoList){
            foreach($orderInfoList as $order){
                //check if order has been imported before
                $checkOrder = So::where('platform_order_id', '=', $order['OrderNumber'])->where('biz_type', '=', 'Newegg')->first();

                if(!$checkOrder){
                    if (isset($order['ShipToCountryCode'])) {
                        $addressId=$this->updateOrCreatePlatformMarketShippingAddress($order,$storeName);
                    }
                    $platformMarketOrder = $this->updateOrCreatePlatformMarketOrder($order,$addressId,$storeName);
                    if(isset($order["ItemInfoList"]) && !empty($order["ItemInfoList"])){
                        foreach($order["ItemInfoList"] as $orderItem){
                            $this->updateOrCreatePlatformMarketOrderItem($platformMarketOrder->id, $order, $orderItem ,$storeName);
                        }
                    }                   
                }else{
                        $orderno = $order['OrderNumber'];
                        $subject = "Duplicate Order Alert: MarketPlace: [{$storeName}] Order [{$orderno}]\r\n";
                        $message = "Duplicated Order found for [{$storeName}] Order no [{$orderno}]. \r\n";
                        $message .="Order will not be imported. Please check to ensure this order is the same as what we have\r\n";
                        $message .= "Thanks\r\n";
                        $email = 'jerry.lim@eservicesgroup.com';
                        $this->sendAlertMailMessage($email, $subject, $message);
                }
            }
            return true;
        }
    }

    public function getOrderList($storeName)
    {
        $this->neweggOrderList=new NeweggOrderList($storeName);
        // $this->storeCurrency=$this->neweggOrderList->getStoreCurrency();
        $lastAccessTime = $this->getSchedule()->last_access_time;

        $dateTime = $this->convertFromUtcToPst($lastAccessTime, "Y-m-d");
        $this->neweggOrderList->setOrderDateFrom($dateTime);
        $originOrderList=$this->neweggOrderList->fetchOrderList();
        $this->saveDataToFile(serialize($originOrderList),"getOrderList");
        return $originOrderList;
    }

    //update or insert data to database
    public function updateOrCreatePlatformMarketOrder($order,$addressId,$storeName)
    {
        # Newegg's time is in PST
        $utcOrderDate = $this->convertFromPstToUtc($order['OrderDate'], "m/d/Y H:i:s", "Y-m-d H:i:s");

         # Check if Ship by Newegg
        $fulfillment_channel = '';
        if($order['FulfillmentOption'] == 1)
        {
            $fulfillment_channel = 'SBN';
        }
        $platformStore = $this->getPlatformStore($storeName);
        $object = [
            //'platform' => $storeName,
            'biz_type' => "Newegg",
            'store_id' => $platformStore->id,
            'platform_order_id' => $order['OrderNumber'],
            'platform_order_no' => $order['OrderNumber'],
            'purchase_date' => $utcOrderDate,
            'last_update_date' => '0000-00-00 00:00:00',
            'order_status' => studly_case("({$order['OrderStatus']}) {$order["OrderStatusDescription"]}"),
            'esg_order_status'=>$this->getSoOrderStatus($order['OrderStatus']),
            'buyer_email' => $order['CustomerEmailAddress'],
            'currency' => $this->neweggOrderList->getOrderCurrency(),
            'shipping_address_id' => $addressId
        ];

        if (isset($order['OrderTotalAmount'])) {
            $object['total_amount'] = $order['OrderTotalAmount'];
        }

        $object['payment_method'] = '';

        if (isset($order['CustomerName'])) {
            $object['buyer_name'] = $order['CustomerName'];
        }

        if(isset($order["SalesChannel"])) {
            # 0 = Newegg order, 1 = Multi-channel order
            $object["sales_channel"] = $order["SalesChannel"];
        }

        if(isset($order["FulfillmentOption"])) {
            # SBN = Ship by Newegg
            $object["fulfillment_channel"] = $fulfillment_channel;
        }

        if(isset($order["ShipService"])) {
            $object["ship_service_level"] = $order["ShipService"];
        }

        if (isset($order['Memo'])){
            $object['remarks'] = $order['Memo'];
        }

        $platformMarketOrder = PlatformMarketOrder::updateOrCreate(
            [
                'platform_order_id' => $order['OrderNumber'],
                'platform' => $storeName
            ],
            $object
        );
        return $platformMarketOrder;
    }

    public function updateOrCreatePlatformMarketOrderItem($platformMarketOrderId,$order, $orderItem, $storeName)
    {
        $object = [
            'platform_market_order_id' => $platformMarketOrderId,
            'platform_order_id' => $order['OrderNumber'],
            'seller_sku' => $orderItem['SellerPartNumber'],
            'order_item_id' => $orderItem['NeweggItemNumber'],
            'title' => $orderItem['Description'],
            'quantity_ordered' => $orderItem["OrderedQty"]
        ];

        if (isset($orderItem['ShippedQty'])) {
            $object['quantity_shipped'] = $orderItem['ShippedQty'];
        }
        if (isset($orderItem['UnitPrice'])) {
            $object['item_price'] = number_format($orderItem['UnitPrice'] * $orderItem["OrderedQty"], 2, '.', '');            
        }
        if (isset($order['ShippingAmount'])) {
            $object['shipping_price'] = $order['ShippingAmount'];
        }

        $tax = 0;
        if (isset($orderItem['ExtendSalesTax'])) {
            $tax += $orderItem['ExtendSalesTax'];
        }
        if (isset($orderItem['ExtendVAT'])) {
            $tax += $orderItem['ExtendVAT'];
        }
        if (isset($orderItem['ExtendDuty'])) {
            $tax += $orderItem['ExtendDuty'];
        }

        if($tax) {
            $itemTax = number_format($tax/$orderItem["OrderedQty"], 2, '.', '');
        }

        $object['item_tax'] = $tax;
        if(isset($order["ShipService"])) {
            $object["ship_service_level"] = $order["ShipService"];
        }

        if (isset($orderItem['ExtendShippingCharge'])) {
            $object['shipping_price'] = $orderItem['ExtendShippingCharge'];
        }

        if (isset($orderItem['Status'])) {
            $object['status'] = studly_case("({$orderItem['Status']}) {$orderItem["StatusDescription"]}");
        }

        $platformMarketOrderItem = PlatformMarketOrderItem::updateOrCreate(
            [
                'platform_order_id' => $order['OrderNumber'],
                'order_item_id' => $orderItem['NeweggItemNumber'],
                'platform' => $storeName
            ],
            $object
        );
    }

    public function updateOrCreatePlatformMarketShippingAddress($order,$storeName)
    {
        $object=array();
        $object['platform_order_id']=$order['OrderNumber'];
        if(!trim($order['ShipToFirstName']) && !trim($order["ShipToFirstName"]))
            $shipName = $order['CustomerName'];
        else
            $shipName = $order['ShipToFirstName']." ".$order["ShipToFirstName"];

        $object['name'] = $shipName;
        if(trim($order["ShipToCompany"])) {
            $object['address_line_1'] = $order['ShipToCompany'];
            $object['address_line_2'] = $order['ShipToAddress1'];
            $object['address_line_3'] = $order['ShipToAddress2'];
        } else {
            $object['address_line_1'] = $order['ShipToAddress1'];
            $object['address_line_2'] = $order['ShipToAddress2'];
            $object['address_line_3'] = "";
        }

        $object['city'] = $order['ShipToCityName'];
        $object['county'] = $order['ShipToCountryCode'];
        $object['country_code'] = strtoupper(substr($storeName, -2));
        $object['district'] = '';
        $object['state_or_region'] = $order['ShipToStateCode'];
        $object['postal_code'] = $order['ShipToZipCode'];
        $object['phone'] = $order['CustomerPhoneNumber'];

        $object['bill_name'] = $order['CustomerName'];
        $object['bill_address_line_1'] = $order['ShipToAddress1'];
        $object['bill_address_line_2'] = $order['ShipToAddress2'];
        $object['bill_address_line_3'] = "";
        $object['bill_city'] = $order['ShipToCityName'];
        $object['bill_county'] = $order['ShipToCountryCode'];
        $object['bill_country_code'] = strtoupper(substr($storeName, -2));
        $object['bill_district'] = "";
        $object['bill_state_or_region'] = $order['ShipToStateCode'];
        $object['bill_postal_code'] = $order['ShipToZipCode'];
        $object['bill_phone'] = $order['CustomerPhoneNumber'];

        $platformMarketShippingAddress = PlatformMarketShippingAddress::updateOrCreate(
            [
                'platform_order_id' => $order['OrderNumber'],
                'platform' => $storeName,
            ],
            $object
        );
        return $platformMarketShippingAddress->id;
    }

    public function getSoOrderStatus($platformOrderStatus)
    {
        switch ($platformOrderStatus) {
            case '4': // voided
                $status=PlatformMarketConstService::ORDER_STATUS_CANCEL;
                break;
            case '2':
                $status=PlatformMarketConstService::ORDER_STATUS_SHIPPED;
                break;
            case '0': // unshipped
            case '1': // partially shipped
                $status=PlatformMarketConstService::ORDER_STATUS_UNSHIPPED;
                break;
            case '2': // shipped
            case '3': // invoiced
                $status=PlatformMarketConstService::ORDER_STATUS_DELIVERED;
                break;
            // case 'Failed':
            //  $status=PlatformMarketConstService::ORDER_STATUS_FAIL;
            //  break;
            default:
                return null;
        }
        return $status;
    }

    public function submitOrderFufillment($esgOrder,$esgOrderShipment,$platformOrderIdList)
    {
        $storeName = $platformOrderIdList[$esgOrder->platform_order_id];
        $orderItemIds = array();
        $extorderno = $esgOrder->platform_order_id;
        foreach($esgOrder->soItem as $item)
        {
            $eaitem['sellersku'] = $item->prod_sku;
            $eaitem['qty'] = $item->qty;
            $eaitem['ext_item_cd'] = $item->ext_item_cd;
            $selleritem[] = $eaitem;
        }

        if ($esgOrderShipment) {
            $response = $this->setStatusToShipped($storeName, $extorderno, $selleritem,$esgOrderShipment);
            if($response){
                $ship_status = $response['data']['Result']['OrderStatus'];
                if(!$response['error'])
                {
                    if($ship_status == 'Shipped')
                    {
                            $subject = "SUCCESS: UpdateShipment to MarketPlace: [{$storeName}]! ESG Order [{$esgOrder->so_no}], Newegg Order [{$esgOrder->platform_order_id}]\r\n";
                            $message = "Successful Update of shipment for ESG Order [{$esgOrder->so_no}], [{$storeName}] Order [{$esgOrder->platform_order_id}]\r\n";
                            $message .= "Thanks\r\n\r\n";
                            $message .= serialize($response);
                            $email = 'jerry.lim@eservicesgroup.com';
                            $this->sendAlertMailMessage($email, $subject, $message);
                            return true;
                    } else {
                            $subject = "FAIL: UpdateShipment to MarketPlace: [{$storeName}]! ESG Order [{$esgOrder->so_no}], Newegg Order [{$esgOrder->platform_order_id}]\r\n";
                            $message = "Update failure has occur for ESG Order [{$esgOrder->so_no}], [{$storeName}] Order [{$esgOrder->platform_order_id}]\r\n";
                            $message .="Please check and update manually to avoid order cancellation by [{$storeName}] \r\n";
                            $message .= "Thanks\r\n\r\n";
                            $message .= serialize($response);
                            $email = 'newegg@brandsconnect.net, jerry.lim@eservicesgroup.com';
                            $this->sendAlertMailMessage($email, $subject, $message);
                            return false;
                    }               
                }else{
                       $subject = "FAIL: Response Error in UpdateShipment to MarketPlace: [{$storeName}]! ESG Order [{$esgOrder->so_no}], Newegg Order [{$esgOrder->platform_order_id}]\r\n";
                       $message = "Update failure has occur for ESG Order [{$esgOrder->so_no}], [{$storeName}] Order [{$esgOrder->platform_order_id}]\r\n";
                            $message .="Please check and update manually to avoid order cancellation by [{$storeName}] \r\n";
                            $message .= "Thanks\r\n\r\n";
                            $message .= serialize($response);
                            $email = 'jerry.lim@eservicesgroup.com';
                            $this->sendAlertMailMessage($email, $subject, $message);
                            return false;
                }
                //return $response;
            }else{
                return false;
            }
        }
    }

    public function setStatusToShipped($storeName, $extorderno, $selleritem, $esgOrderShipment)
    {
        $shipmentProvider = CourierInfo::where('courier_id', '=', $esgOrderShipment->courier_id)->where('status', '=', '1')->first();
        $this->neweggOrderStatus = new NeweggOrderStatus($storeName);
        $this->neweggOrderStatus->setOrderNumber($extorderno);
        $orderStatus = $this->neweggOrderStatus->getOrderStatus();
        if($orderStatus['data']['OrderStatusCode'] != "0" && $orderStatus['data']['OrderStatusCode'] != "1"){
            $esgOrderStatus = $this->getSoOrderStatus($orderStatus['data']['OrderStatusCode']);
            $platformMarketOrder = PlatformMarketOrder::where("platform",$storeName)
                ->where('platform_order_id',$extorderno)
                ->first();
            $platformMarketOrder->update(array("esg_order_status" => $esgOrderStatus,"order_status" => $orderStatus['data']['OrderStatusName']));
        }else{
            $this->neweggOrderStatus->setOrderItemIds($selleritem);
            $this->neweggOrderStatus->setShipService("Air");
            $this->neweggOrderStatus->setTrackingNumber($esgOrderShipment->tracking_no);
            $this->neweggOrderStatus->setShipCarrier($shipmentProvider->courier_name);
            $orginOrderItemList=$this->neweggOrderStatus->setStatusToShipped();
            $this->saveDataToFile(serialize($orginOrderItemList),"setStatusToShipped");
            return $orginOrderItemList;
        }
        
    }

    public function getShipedOrderState()
    {
        return  "Shipped";
    }

    private function checkResultData($result)
    {
        if($result){
            $this->saveDataToFile(serialize($result),"setStatusToCanceled");
            return true;
        }else{
            $error["message"]=$this->neweggOrderStatus->errorMessage();
            $error["code"]=$this->neweggOrderStatus->errorCode();
            return $error;
        }
    }

    private function convertFromUtcToPst($timestamp, $format = "Y-m-d")
    {
        if($timestamp) {
             // change timezone to Pacific Standard
            $dt = new \DateTime($timestamp);
            $dt->setTimezone(new \DateTimeZone("PST"));
            $dateTime = $dt->format($format);
            return $dateTime;
        }

        return "";
    }

    private function convertFromPstToUtc($timestamp, $timestampFormat = "m/d/Y H:i:s", $format = "Y-m-d H:i:s")
    {
        if($timestamp) {
            # Newegg's time is in PST
            # let DateTime know the format of your $timestamp
            $dtOrderdate = \DateTime::createFromFormat($timestampFormat, $timestamp, new \DateTimeZone("PST"));
            $dtOrderdate->setTimezone(new \DateTimeZone("UTC"));
            $utcOrderDate = $dtOrderdate->format($format);
            return $utcOrderDate;
        }

        return "";
    }

     public function sendAlertMailMessage($email, $subject,$message)
    {
        $this->sendMailMessage($email, $subject, $message);
        return false;
    }

}