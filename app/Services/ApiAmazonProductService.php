<?php

namespace App\Services;

use App\Contracts\ApiPlatformProductInterface;
use App\Models\MpControl;
use App\Models\So;
use App\Models\PlatformProductFeed;
use App\Models\WmsWarehouseMapping;
use Config;

//use fnac api package
use Peron\AmazonMws\AmazonFeed;
use Peron\AmazonMws\AmazonReportRequest;
use Peron\AmazonMws\AmazonReportScheduleManager;
use Peron\AmazonMws\AmazonReportScheduleList;
use Peron\AmazonMws\AmazonReportList;
use Peron\AmazonMws\AmazonReport;
use Peron\AmazonMws\AmazonReportAcknowledger;


class ApiAmazonProductService implements ApiPlatformProductInterface
{
    use ApiBaseProductTraitService;
    public function __construct()
    {
        $this->stores =  Config::get('amazon-mws.store');
    }

    public function getPlatformId()
    {
        return 'Amazon';
    }

    public function getProductList($storeName)
    {

    }

    public function submitProductPriceAndInventory($storeName)
    {
        $this->submitProductPrice($storeName);
        $this->submitProductInventory($storeName);
    }

    public function submitProductPrice($storeName)
    {
        $pendingSkuGroup = MarketplaceSkuMapping::ProcessStatusProduct($storeName, PlatformMarketConstService::PENDING_PRICE);
        if($pendingSkuGroup){
            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<AmazonEnvelope xsi:noNamespaceSchemaLocation="amzn-envelope.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
            $xml .= '<Header>';
            $xml .= '<DocumentVersion>1.01</DocumentVersion>';
            $xml .= '<MerchantIdentifier>'.$this->stores[$storeName]['merchantId'].'</MerchantIdentifier>';
            $xml .= '</Header>';
            $xml .= '<MessageType>Price</MessageType>';

            foreach ($pendingSkuGroup as $index => $pendingSku) {
                $messageDom = '<Message>';
                $messageDom .= '<MessageID>'.++$index.'</MessageID>';
                $messageDom .= '<Price>';
                $messageDom .= '<SKU>'.$pendingSku->marketplace_sku.'</SKU>';
                $messageDom .= '<StandardPrice currency="DEFAULT">'.$pendingSku->price.'</StandardPrice>';
                $messageDom .= '</Price>';
                $messageDom .= '</Message>';

                $xml .= $messageDom;
            }
            $xml .= '</AmazonEnvelope>';
            $platformProductFeed = new PlatformProductFeed();
            $platformProductFeed->platform = $storeName;
            $platformProductFeed->feed_type = '_POST_PRODUCT_PRICING_DATA_';

            $feed = new AmazonFeed($storeName);
            $feed->setFeedType('_POST_PRODUCT_PRICING_DATA_');
            $feed->setMarketplaceIds($this->stores[$storeName]['marketplaceId']);
            $feed->setFeedContent($xml);

            if ($feed->submitFeed() === false) {
                $platformProductFeed->feed_processing_status = '_SUBMITTED_FAILED';
            } else {
                $this->updatePendingProductProcessStatus($pendingSkuGroup,PlatformMarketConstService::PENDING_PRICE);
                $response = $feed->getResponse();
                $platformProductFeed->feed_submission_id = $response['FeedSubmissionId'];
                $platformProductFeed->submitted_date = $response['SubmittedDate'];
                $platformProductFeed->feed_processing_status = $response['FeedProcessingStatus'];
            }
            $platformProductFeed->save();
        }
    }

    public function submitProductInventory($storeName)
    {
        $pendingSkuGroup = MarketplaceSkuMapping::ProcessStatusProduct($storeName, PlatformMarketConstService::PENDING_INVENTORY);
        if($pendingSkuGroup){
            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<AmazonEnvelope xsi:noNamespaceSchemaLocation="amzn-envelope.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
            $xml .= '<Header>';
            $xml .= '<DocumentVersion>1.01</DocumentVersion>';
            $xml .= '<MerchantIdentifier>'.$this->stores[$storeName]['merchantId'].'</MerchantIdentifier>';
            $xml .= '</Header>';
            $xml .= '<MessageType>Product</MessageType>';

            foreach ($pendingSkuGroup as $index => $pendingSku) {
                try {
                    $messageNode = '<Message>';
                    $messageNode .= '<MessageID>'.++$index.'</MessageID>';
                    $messageNode .= '<OperationType>Update</OperationType>';
                    if ($pendingSku->fulfillment === 'AFN') {
                        $inventory = '';
                        $inventory .= '<Inventory>';
                        $inventory .= '<SKU>'.$pendingSku->marketplace_sku.'</SKU>';
                        $inventory .= '<FulfillmentCenterID>'.$pendingSku->fulfillmentCenter('AFN')->first()->name.'</FulfillmentCenterID>';
                        $inventory .= '<Lookup>FulfillmentNetwork</Lookup>';
                        $inventory .= '<SwitchFulfillmentTo>AFN</SwitchFulfillmentTo>';
                        $inventory .= '</Inventory>';
                    } else {
                        $inventory = '';
                        $inventory .= '<Inventory>';
                        $inventory .= '<SKU>'.$pendingSku->marketplace_sku.'</SKU>';
                        $inventory .= '<FulfillmentCenterID>DEFAULT</FulfillmentCenterID>';
                        $inventory .= '<Quantity>'.$pendingSku->inventory.'</Quantity>';
                        $inventory .= '<FulfillmentLatency>'.$pendingSku->fulfillment_latency.'</FulfillmentLatency>';
                        $inventory .= '<SwitchFulfillmentTo>MFN</SwitchFulfillmentTo>';
                        $inventory .= '</Inventory>';
                    }
                    $messageNode .= $inventory;
                    $messageNode .= '</Message>';

                    $xml .= $messageNode;
                } catch (\Exception $e) {
                    mail('jimmy@eservciesgroup.com', 'SOS', 'Inventory Feed Error');
                }
            }
            $xml .= '</AmazonEnvelope>';
            $platformProductFeed = new PlatformProductFeed();
            $platformProductFeed->platform = $storeName;
            $platformProductFeed->feed_type = '_POST_INVENTORY_AVAILABILITY_DATA_';

            $feed = new AmazonFeed($storeName);
            $feed->setFeedType('_POST_INVENTORY_AVAILABILITY_DATA_');
            $feed->setFeedContent($xml);

            if ($feed->submitFeed() === false) {
                $platformProductFeed->feed_processing_status = '_SUBMITTED_FAILED';
            } else {
                $this->updatePendingProductProcessStatus($pendingSkuGroup,PlatformMarketConstService::PENDING_INVENTORY);
                $response = $feed->getResponse();
                $platformProductFeed->feed_submission_id = $response['FeedSubmissionId'];
                $platformProductFeed->submitted_date = $response['SubmittedDate'];
                $platformProductFeed->feed_processing_status = $response['FeedProcessingStatus'];
            }
            $platformProductFeed->save();
        }
    }

    public function submitProductCreate($storeName,$productGroup)
    {

    }

    public function submitProductUpdate($storeName)
    {
        $this->runProductUpdate($storeName, 'pendingProduct');
    }

    public function warehouseInventoryReport()
    {
        $platformIds = null ;
        foreach($this->stores as $storeName => $store){
            $platformAccount = strtoupper(substr($storeName, 0, 2));
            $platformIds[$storeName] = 'AC-'.$platformAccount.'AZ-GROUP'.$store["platform"];
        }
        $platformStoreName = array_flip($platformIds);
        $warehouseIdList = WmsWarehouseMapping::whereIn("platform_id",$platformIds)
                            ->get()
                            ->pluck('warehouse_id', 'platform_id')
                            ->toArray();
        $warehouseIdList = array_unique($warehouseIdList);
        foreach($warehouseIdList as $platformId => $warehouseId){
            if(isset($platformStoreName[$platformId])){
                $this->fulfilledInventoryReport($platformStoreName[$platformId],$warehouseId);
            }
        }
    }

    public function fulfilledInventoryReport($storeName,$warehouseId)
    {
        //$this->setAmazonReportSchedule($storeName);
        //get exit report from Amazon
        $reportList = $this->getReportList($storeName);
        $reportIds = null;
        foreach($reportList as $report){
            if(in_array($report["ReportType"],$this->getReportType())){
                $reportIds[] = $this->getReport($storeName,$report["ReportId"],$warehouseId);
            }
        }
        if($reportIds){
            $this->updateReportAcknowledgements($storeName,$reportIds,"true");
        }
    }

    private function setAmazonReportSchedule($storeName)
    {
        $reportTypeList = $this->getReportType();
        $reportScheduleList = $this->getReportScheduleList($storeName,$reportTypeList);
        if(!empty($reportScheduleList)){
            foreach($reportScheduleList as $reportSchedule){
                if(($key = array_search($reportSchedule["ReportType"], $reportTypeList)) !== false) {
                    unset($reportTypeList[$key]);
                }
            }
        }
        if(!empty($reportTypeList)){
            foreach($reportTypeList as $reportType){
                $reportSchedule = $this->setManageReportSchedule($storeName,$reportType);
            }
        }
    }

    public function getReportRequest($storeName,$reportType)
    {
        $amazonReport = new AmazonReportRequest($storeName);
        $amazonReport->setReportType($reportType);
        $amazonReport->setMarketplaces($this->stores[$storeName]['marketplaceId']);
        $amazonReport->requestReport();
        $response = $amazonReport->getResponse();
    }

    public function setManageReportSchedule($storeName,$reportType)
    {
        $amazonReportScheduleManager = new AmazonReportScheduleManager($storeName);
        $amazonReportScheduleManager->setReportType($reportType);
        $amazonReportScheduleManager->setSchedule("_1_HOUR_");
        $amazonReportScheduleManager->manageReportSchedule();
        return $amazonReportScheduleManager->getList();
    }

    public function getReportScheduleList($storeName,$reportTypeList)
    {
        $amazonReportScheduleList = new AmazonReportScheduleList($storeName);
        $amazonReportScheduleList->setReportTypes($reportTypeList);
        $amazonReportScheduleList->fetchReportList();
        return $amazonReportScheduleList->getList();
    }

    public function getReportList($storeName,$reportTypeList="")
    {
        $amazonRepoatList = new AmazonReportList($storeName);
        $amazonRepoatList->setAcknowledgedFilter("false");
        if($reportTypeList){
            $amazonRepoatList->setReportTypes($reportTypeList);
        }
        $amazonRepoatList->fetchReportList();
        return $amazonRepoatList->getList();
    }

    public function getReport($storeName,$reportId,$warehouseId)
    {
        $path = $this->getDateReportPath("UNSUPPRESSED");
        if (!file_exists($path)) {
            mkdir($path, 0775, true);
        }
        $pathFile = $path.'/'.$warehouseId.".txt";
        $amazonReport = new AmazonReport($storeName);
        $amazonReport->setReportId($reportId);
        $amazonReport->fetchReport();
        $result = $amazonReport->saveReport($pathFile);
        return $result ? $reportId : null;
    }

    public function updateReportAcknowledgements($storeName,$reportIds,$acknowledger)
    {
        $amazonReportAcknowledger = new AmazonReportAcknowledger($storeName);
        $amazonReportAcknowledger->setReportIds($reportIds);
        $amazonReportAcknowledger->setAcknowledgedFilter($acknowledger);
        $amazonReportAcknowledger->acknowledgeReports();
        return $amazonReportAcknowledger->getList();
    }

    public function getReportType()
    {
        return $reportTypeList = array(
            "_GET_FBA_MYI_UNSUPPRESSED_INVENTORY_DATA_",
        );
    }

    public function getEsgUnSuppressedReport()
    {
        $reportPath = $this->getDateReportPath("UNSUPPRESSED");
        $cellDataArr = array();$orderSkuOrderedList =null;
        $reportFiles = \File::allFiles($reportPath);
        $marketOrderSkuOrderedList = $this->getAmazonFbaOrderSkuOrderedList();
        foreach($reportFiles as $reportFile){
            $warehouseId = basename($reportFile,'.txt');
            if(isset($marketOrderSkuOrderedList[$warehouseId])){
              $orderSkuOrderedList = $marketOrderSkuOrderedList[$warehouseId];
            }
            $row = 1;$cellData = null;
            if (($handle = fopen($reportFile, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) {
                    $num = count($data); $orderSkuOrdered= null;
                    //echo "<p> $num fields in line $row: <br /></p>\n";
                   if($row == 1){
                    $cellData[]=array(
                        "warehouse_id" => "Warehouse Id",
                        "marketplace_sku" => 'Marketplace Sku',
                        "amazon_inventory" => 'Amazon Inventory',
                        "esg_sku" => 'ESG Sku',
                        "master_sku" => 'ESG Master Sku',
                        "product_name" => 'ESG Product Name',
                        "brand_name" => 'ESG Brand Name',
                        "esg_ordered_qty" => 'ESG Ordered Qty',
                        "replenish" => 'Replenish',
                        );
                   }else {
                        if($orderSkuOrderedList && isset($orderSkuOrderedList[$data['0']])){
                            $orderSkuOrdered = $orderSkuOrderedList[$data['0']];
                        }
                        $reportCellData = $this->setReportCellData($warehouseId,$data,$orderSkuOrdered);
                        if($reportCellData){
                            $cellData[] = $reportCellData;
                        }
                    }
                    $row++;
                }
                fclose($handle);
            }
            $cellDataArr[$warehouseId] = $cellData;
        }
        $attachment = $this->generateMultipleSheetsExcel('amazonFbaInventoryReport',$cellDataArr,$this->getDateReportPath());
        //send attachment Mail
        if($attachment){
            $subject = "Amazon FBA Inventory Report!";
            $this->sendAttachmentMail('storemanager@brandsconnect.net,fiona@etradegroup.net',$subject,$attachment);
        }
    }

    public function getAmazonFbaOrderSkuOrderedList()
    {
        $noMappingPlatformId = array();
        $fromDate = date("Y-m-d 00:00:00",strtotime("-1 weeks"));
        $toDate = date("Y-m-d 00:00:00");
        $emailMessage = '';
        $amazonFbaOrders = So::where("so.biz_type","=","AMAZON")
                        ->where("so.delivery_type_id","=","FBA")
                        ->where("so.platform_group_order","=","1")
                        ->where("so.create_on",">",$fromDate)
                        ->where("so.create_on","<",$toDate)
                        ->with("soItem")
                        ->get();
        foreach ($amazonFbaOrders as $amazonFbaOrder) {
            $warehouseId = $this->getOrderWarehouseId($amazonFbaOrder);
            if($warehouseId){
                foreach ($amazonFbaOrder->soItem as $value) {
                    $FbaOrder = array(
                        "so_no" => $amazonFbaOrder->so_no,
                        "platform_id" => $amazonFbaOrder->platform_id,
                        "warehouse_id" => $warehouseId,
                        "prod_sku" => $value->prod_sku,
                        "qty" => $value->qty,
                    );
                    $FbaOrderList[] = $FbaOrder;
                }
                $amazonFbaOrderGroups[$warehouseId] = $FbaOrderList;
            }else{
                $noMappingPlatformId[] = $amazonFbaOrder->platform_id;
            }
        }
        if(!empty($noMappingPlatformId)){
            $errorMappingPlatformId = array_unique($noMappingPlatformId);
            foreach ($errorMappingPlatformId as $platformId) {
               $emailMessage .= " platform_id ".$platformId." need add warehouse mapping \r\n";
            }
            mail('jimmy@eservciesgroup.com', 'Amazon Warehouse mapping', $emailMessage);
        }
        return $this->getWmsWarehouseSkuOrderedList($amazonFbaOrderGroups);
    }

    public function setReportCellData($warehouseId,$data,$orderSkuOrdered="")
    {
        $amazonInventory = isset($data['10']) ? $data['10'] : "";
        $esgorderedQty = isset($orderSkuOrdered['qty']) ? $orderSkuOrdered['qty'] : "0";
        $productName = isset($orderSkuOrdered['product_name']) ? $orderSkuOrdered['product_name'] : "";
        $brandName = isset($orderSkuOrdered['brand_name']) ? $orderSkuOrdered['brand_name'] : "";
        $masterSku = isset($orderSkuOrdered['master_sku']) ? $orderSkuOrdered['master_sku'] : "";
        if($esgorderedQty >= $amazonInventory){
           $replenish = "Y";
        }else{
           $replenish = "N";
        }
        if($esgorderedQty || $amazonInventory){
            $cellData = array(
                "warehouse _id" => $warehouseId,
                "marketplace_sku" => $data['0'],
                "amazon_inventory" => $amazonInventory,
                "esg_sku" => isset($orderSkuOrdered['sku']) ? $orderSkuOrdered['sku'] : "",
                "master_sku" => $masterSku,
                "product_name" => $productName,
                "brand_name" => $brandName,
                "esg_ordered_qty" => $esgorderedQty,
                "replenish" => $replenish,
            );
            return $cellData;
        }
    }

    public function getDateReportPath($reportType="")
    {
        return \Storage::disk('report')->getDriver()->getAdapter()->getPathPrefix().date('Y').'/'.date("m").'/'.date("d")."/Amazon/".$reportType;
    }

    public function sendAttachmentMail($alertEmail,$subject,$attachment)
    {
        /* Attachment File */
        $fileName = $attachment["file_name"];
        $path = $attachment["path"];

        // Read the file content
        $file = $path.'/'.$fileName;
        $fileSize = filesize($file);
        $handle = fopen($file, "r");
        $content = fread($handle, $fileSize);
        fclose($handle);
        $content = chunk_split(base64_encode($content));

        /* Set the email header */
        // Generate a boundary
        $boundary = md5(uniqid(time()));

        // Email header
        $header = "From: admin@shop.eservciesgroup.com".PHP_EOL;
        $header .= "MIME-Version: 1.0".PHP_EOL;

        // Multipart wraps the Email Content and Attachment
        $header .= "Content-Type: multipart/mixed; boundary=\"".$boundary."\"".PHP_EOL;
        $header .= "This is a multi-part message in MIME format.".PHP_EOL;
        $header .= "--".$boundary.PHP_EOL;

        // Email content
        // Content-type can be text/plain or text/html
        $message = "The Attachment Is Amazon FBA  Unsuppressed Inventory Report In 2 Weeks Platform Sales!".PHP_EOL;
        $message .= "Thanks".PHP_EOL.PHP_EOL;
        $message .= "--".$boundary.PHP_EOL;

        // Attachment
        // Edit content type for different file extensions
        $message .= "Content-Type: application/xml; name=\"".$fileName."\"".PHP_EOL;
        $message .= "Content-Transfer-Encoding: base64".PHP_EOL;
        $message .= "Content-Disposition: attachment; filename=\"".$fileName."\"".PHP_EOL.PHP_EOL;
        $message .= $content.PHP_EOL;
        $message .= "--".$boundary."--";
        mail("{$alertEmail}, jimmy.gao@eservicesgroup.com", $subject, $message, $header);
    }

    public function getOrderWarehouseId($so)
    {
        $wmsWarehouse = WmsWarehouseMapping::where("platform_id",$so->platform_id)
                        ->where("status",1)
                        ->pluck("warehouse_id","delivery_type");
        if(!$wmsWarehouse->isEmpty()){
            if(isset($wmsWarehouse[$so->delivery_type_id])){
                $warehouseId = $wmsWarehouse[$so->delivery_type_id];
            }else{
                $warehouseId = $wmsWarehouse["ALL"];
            }
            return $warehouseId;
        }
    }

}
