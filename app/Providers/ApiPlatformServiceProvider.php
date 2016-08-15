<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ApiLazadaService;
use Illuminate\Http\Request;
use Symfony\Component\Console\Input\ArgvInput;

class ApiPlatformServiceProvider extends ServiceProvider
{
    
    protected $defer = true;
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */

    protected $availableServices = array(
        'amazon'=>'ApiAmazonService',
        'lazada'=>'ApiLazadaService'
    );

    public function register()
    {
        $this->app->call([$this, 'registerMyService']);
    }

    public function registerMyService(Request $request)
    {
        $apiPlatform = strtolower($request->get('api_platform'));
        $this->service = $apiPlatform ? $this->availableServices[$apiPlatform]:'ApiLazadaService';
        //$this->app->bind('App\Contracts\ApiPlatformInterface', "App\Services\\{$this->service}"); 
        $this->app->bind('App\Services\ApiPlatformFactoryService', function($app,$parameters){ 
            //setcommand

            $this->service = $parameters ? $this->availableServices[$parameters["apiName"]]:$this->service;
            return new \App\Services\ApiPlatformFactoryService($app->make("App\Services\\{$this->service}"));
        });
    }

     /**
     * Get the services provided by the provider
     *
     * @return array
     */
    public function provides()
    {
        return [\App\Services\ApiPlatformFactoryService::class];
    }

}