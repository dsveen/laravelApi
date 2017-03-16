<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\IwmsApi\IwmsFactoryWmsService;

class IwmsProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Iwms:product {action}  {--wms= : 4px} {--debug= : 0 || 1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
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
        //
        $merchantId = "ESG-HK-TEST";
        $wmsPlatform = $this->option('wms');
        $debugOption = $this->option('debug');
        $debug = $debugOption ? 1 :0;
        $this->iwmsFactoryWmsService = new IwmsFactoryWmsService($wmsPlatform,$debug);
        $action = $this->argument('action');
        if($action == "create"){
            $this->iwmsFactoryWmsService->createProduct($merchantId);
        }
    }
}
