<?php

namespace App\Services;

use App\User;
use App\Models\StoreWarehouse;
use App\Models\MattelSkuMapping;
use App\Models\PlatformMarketInventory;
use Illuminate\Http\Request;
use Excel;
use App\Repository\PlatformMarketOrderRepository;

class PlatformMarketInventoryService
{
    use ApiPlatformTraitService;

    public function getSkuInventorys(Request $request)
    {
        $stores = User::find(\Authorizer::getResourceOwnerId())->stores()->pluck('store_id')->all();
        $query = PlatformMarketInventory::with('MattelSkuMapping')->whereIn('store_id', $stores);
        if ($request->get('mattel_sku')) {
            $query = $query->where('mattel_sku', '=', $request->get('mattel_sku'));
        }
        return $query->paginate(30);
    }

    public function handleUploadFile($fileName = '')
    {
        if (file_exists($fileName)) {
            Excel::selectSheetsByIndex(0)->load($fileName, function ($reader) {
                $sheetItems = $reader->all();
                $sheetItems = $sheetItems->toArray();
                array_filter($sheetItems);
                foreach ($sheetItems as $item) {
                    \DB::beginTransaction();
                    try {
                        $storeId = User::find(\Authorizer::getResourceOwnerId())->stores()->pluck('store_id')->first();
                        $item['store_id'] = $storeId;
                        $this->createPlatformMarketInventory($item);
                        \DB::commit();
                    } catch (\Exception $e) {
                        \DB::rollBack();
                        mail('will.zhang@eservicesgroup.com', 'Platform Market Inventory Upload - Exception', $e->getMessage()."\r\n File: ".$e->getFile()."\r\n Line: ".$e->getLine());
                    }
                }
            });
        }
    }

    public function createPlatformMarketInventory($item = [])
    {
        $object = [];
        $object['store_id'] = $item['store_id'];
        $object['warehouse_id'] = $item['warehouse_id'];
        $object['marketplace_sku'] = $item['marketplace_sku'];
        $object['mattel_sku'] = $item['mattel_sku'];
        $object['dc_sku'] = $item['dc_sku'];
        $object['inventory'] = $item['quantity'];
        $object['threshold'] = $item['threshold'];
        $platformMarketInventory = PlatformMarketInventory::updateOrCreate(
                [
                    'store_id' => $object['store_id'],
                    'warehouse_id' => $object['warehouse_id'],
                    'marketplace_sku' => $object['marketplace_sku'],
                    'mattel_sku' => $object['mattel_sku'],
                ],
                $object
            );
    }

    public function sendLowStockAlert()
    {
        $result = PlatformMarketInventory::with('marketplaceLowStockAlertEmail')
                ->with('merchantProductMapping')
                ->whereColumn('threshold', '>', 'inventory')
                ->get();
        $new_arr = [];
        foreach ($result as $value) {
            $new_arr[$value->store_id][] = $value;
        }
        foreach ($new_arr as $row) {
            $merchant_id = $country_id = $email = $cc_email = $bcc_email = '';
            $message = "<html>
                        <head>
                        <style>
                        table { border-collapse: collapse; width: 100%; }
                        th, td {border: 1px solid #ddd;text-align: left;padding: 8px;color:black}
                        tr:nth-child(even){background-color: #f2f2f2}
                        th {background-color: #F7F7F7;}
                        </style>
                        </head>
                        <body>";
            $message .= "<p>This is to inform below Inventory listed has reached its SKU threshold settings</p>";
            $message .= "<table>
                            <thead>
                                <tr>
                                    <th style='width:40%'>Product Name</th>
                                    <th style='width:15%'>Mattel SKU</th>
                                    <th style='width:15%'>ESG SKU</th>
                                    <th style='width:15%'>Inventory</th>
                                    <th style='width:15%'>Threshold</th>
                                </tr>
                            </thead>
                            <tbody>";

            foreach ($row as $sRow) {
                $email = $sRow->marketplaceLowStockAlertEmail->to_mail;
                $cc_email = $sRow->marketplaceLowStockAlertEmail->cc_mail;
                $bcc_email = $sRow->marketplaceLowStockAlertEmail->bcc_mail;

                $merchant_id = $sRow->merchantProductMapping->merchant_id;
                $country_id = substr($sRow->warehouse_id, -5, 2);

                $message .= "<tr>";
                $message .=     "<td>".$sRow->merchantProductMapping->product->name."</td>";
                $message .=     "<td>".$sRow->mattel_sku."</td>";
                $message .=     "<td>".$sRow->merchantProductMapping->sku."</td>";
                $message .=     "<td>".$sRow->inventory."</td>";
                $message .=     "<td>".$sRow->threshold."</td>";
                $message .= "</tr>";
            }

            $message .= "</tbody></table>";
            $message .= "<p>Please arrange stock replenishment at your earliest convenience.</p>";
            $message .= "<p>Thank you.</p>";
            $message .= "</body></html>";

            $subject = $country_id.'_'.$merchant_id." Inventory Report";

            $headers = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
            $headers .= "From: ESG Admin <admin@shop.eservciesgroup.com>"."\r\n";
            if ($cc_email) {
                $headers .= "CC:".$cc_email."\r\n";
            }
            if ($bcc_email) {
                $headers .= "BCC:".$bcc_email."\r\n";
            }
            if ($email && $merchant_id && $country_id) {
                mail($email, $subject, $message, $headers);
            }
        }
    }

    public function exportOrdersToExcel()
    {
        $stores = User::find(\Authorizer::getResourceOwnerId())->stores()->pluck('store_id')->all();
        $lists = PlatformMarketInventory::with('MattelSkuMapping')->whereIn('store_id', $stores)->get();
        $path = \Storage::disk('platformMarketInventoryUpload')->getDriver()->getAdapter()->getPathPrefix()."excel/";

        $cellData[] = [
            "WAREHOUSE ID",
            "Mattel SKU",
            "DC SKU",
            "Inventory",
            "Threshold"
        ];

        foreach ($lists as $sku) {
            $cellData[] = [
                "WAREHOUSE ID" => $sku->warehouse_id,
                "Mattel SKU" => $sku->mattel_sku,
                "DC SKU" => $sku->dc_sku,
                "Inventory" => $sku->inventory,
                "Threshold" => $sku->threshold
            ];
        };
        $cellDataArr['inventory'] = $cellData;
        $excelFileName = "Inventory Report";
        $excelFile = $this->generateMultipleSheetsExcel($excelFileName,$cellDataArr,$path);
        return $excelFile["path"].$excelFile["file_name"];
    }
}