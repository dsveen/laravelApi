<?php

namespace App\Services\IwmsApi;

use Illuminate\Http\Request;
use App\Models\So;
use App\Models\IwmsFeedRequest;

class IwmsCallbackApiService
{
    private $callbackToken = "123";
    use IwmsBaseService;

    public function __construct()
    {
        
    }

    public function valid(Request $request)
    {
        $echoStr = $request->input("echostr");
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $tmpArr = array($this->callbackToken, $timestamp, $nonce);
        // use SORT_STRING rule
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        if( $tmpStr == $signature ){
            echo $echoStr;
        }else{
            exit;
        }
    }

    public function responseMsg(Request $request)
    {
        $echoStr = $request->input("echostr");
        $postContent = $request->getContent();
        //extract post data
        if (!empty($postContent)){
            $postMessage = json_decode($postContent);
            //run the own program jobs
            $this->responseMsgAction($postMessage);
            $responseMsg["signature"] = $this->checkSignature($postMessage,$echoStr);
            return $responseMsg;
        }
    }

    public function responseMsgAction($postMessage)
    {
        if($postMessage->action == "orderCreate"){
            $this->sendMsgCreateDeliveryOrderReport($postMessage);
        }
    }
        
    public function checkSignature($postMessage,$echoStr)
    {
        $signatureArr = array();
        foreach ($postMessage->responseMessage as $value) {
            if(isset($value->order_code)){
                $signatureArr[] = $value->order_code;
            }else if(isset($value->receiving_code)){
                $signatureArr[] = $value->receiving_code;
            }
        }
        $signature = implode($signatureArr);
        return base64_encode($this->callbackToken.$signature.$echoStr);
    }

    public function sendMsgCreateDeliveryOrderReport($postMessage)
    {
        $cellData = $this->getMsgCreateDeliveryOrderReport($postMessage);
        $filePath = \Storage::disk('iwms')->getDriver()->getAdapter()->getPathPrefix();
        $orderPath = $filePath."orderCreate/";
        $fileName = "deliveryOrderDetail-".time();
        if(!empty($cellData)){
            $excelFile = $this->createExcelFile($fileName, $orderPath, $cellData);
            if($excelFile){
                $subject = "WMS Delivery Order Create Report!";
                $attachment = array("path" => $orderPath,"file_name"=>$fileName.".xlsx");
                $this->sendAttachmentMail('privatelabel-log@eservicesgroup.com',$subject,$attachment);
            }
        }
    }   

    public function getMsgCreateDeliveryOrderReport($postMessage)
    {
        if(!empty($postMessage->responseMessage)){
            $cellData[] = array('Business Type', 'Merchant', 'Platform', 'Order ID', 'DELIVERY TYPE ID', 'Country', 'Battery Type', 'Rec. Courier', '4PX OMS delivery order ID', 'Pass to 4PX courier');
            foreach ($postMessage->responseMessage as $value) {
                $esgOrder = So::where("so_no",$value->merchant_order_id)
                        ->with("sellingPlatform")
                        ->first();     
                if(!empty($esgOrder)){
                   $cellRow = array(
                        'business_type' => $value->business_type,
                        'merchant' => $esgOrder->sellingPlatform->merchant_id,
                        'platform' => $esgOrder->platform_id,
                        'order_id' => $value->reference_no,
                        'delivery_type_id' => $esgOrder->delivery_type_id,
                        'country' => $value->country,
                        'battery_type' => "",
                        're_courier' => $esgOrder->recommend_courier_id,
                        'wms_order_code' => $value->wms_order_code,
                        'wms_courier' => $value->iwms_courier,
                    );
                    $cellData[] = $cellRow; 
                }
                IwmsFeedRequest::where("iwms_request_id",$value->request_id)->update(array("status"=> "1","response_log" => json_encode($postMessage->responseMessage)));
            }
            return $cellData;
        }
        return null;
    }
    
}
