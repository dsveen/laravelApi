<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Schedule;
use Config;

class PlatformMarketOrderRetrieve extends BaseApiPlatformCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platformMarket:orderRetrieve  {--api= : amazon or lazada}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrieve orders from platfrom market like(amazon,lazada,etc)';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info('Retrieve orders at . '.\Carbon\Carbon::now());
        $this->runPlatformMarketConsoleFunction();
    }

    public function runApiPlatformServiceFunction($stores, $apiName)
    {
        if ($stores) {
            foreach ($stores as $storeName => $store) {
                if(!in_array($storeName, array("BCLAZADASG","CFLAZADASG"))){
                    $previousSchedule = Schedule::where('store_name', '=', $storeName)
                                        ->where('status', '=', 'C')
                                        ->orderBy('last_access_time', 'desc')
                                        ->first();
                    $currentSchedule = Schedule::create([
                            'store_name' => $storeName,
                            'status' => 'N',
                            // MWS API requested: Must be no later than two minutes before the time that the request was submitted.
                            'last_access_time' => Carbon::now()->subMinutes(2),
                        ]);
                    if (!$previousSchedule) {
                        $previousSchedule = $currentSchedule;
                    }

                    //print_r($this->getApiPlatformFactoryService($apiName));break;
                    $result = $this->getApiPlatformFactoryService($apiName)->retrieveOrder($storeName, $previousSchedule);
                    if ($result) {
                        $currentSchedule->status = 'C';
                    } else {
                        $currentSchedule->status = 'F';
                        //$currentSchedule->remark = json_encode($amazonOrderList->getLastResponse());
                    }
                    $currentSchedule->save();
                }
            }
        }
    }

}
