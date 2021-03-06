<?php

namespace App\Repository\PriceMinisterMws;

use App\Repository\CommonMws;
use Config;

/**
 *   PriceMinisterMws Core.
 */
class PriceMinisterCore extends CommonMws
{
    protected $mwsName = 'priceminister-mws';
    protected $storeCurrency = 'EUR';
    protected $errorResponse = [];
    private $signRequestParams;

    public function __construct($storeName)
    {
        $this->initMwsName();
        parent::__construct();
        $this->setStore($storeName);
    }

    public function query($requestParams)
    {
        $this->signRequestParams = $this->signature($requestParams);
        $xml = $this->curl($this->signRequestParams);
        $data = $this->convert($xml);
        if (isset($data['error'])) {
            $this->ErrorResponse = $data['error'];
        } else {
            if (isset($data['response'])) {
                return $this->prepare($data);
            } else {
                $this->ErrorResponse['code'] = 'Unknow';
                $this->ErrorResponse['message'][] = 'Unknow ErrorResponse From PriceMinister';
                return null;
            }
        }
    }

    public function setStore($storeName)
    {
        $store = Config::get($this->mwsName.'.store');
        if (array_key_exists($storeName, $store)) {
            $this->storeName = $storeName;
        } else {
            $this->log("Store $storeName does not exist", 'Warning');
        }
    }

    /**
     * Return error message for last API call.
     *
     * @return string
     */
    public function errorMessage()
    {
        if (isset($this->ErrorResponse) && is_array($this->ErrorResponse) && isset($this->ErrorResponse['message'])) {
            return $this->ErrorResponse['message'];
        }

        return '';
    }

    /**
     * Return error code for last API call.
     *
     * @return string
     */
    public function errorCode()
    {
        if (isset($this->ErrorResponse) && is_array($this->ErrorResponse) && isset($this->ErrorResponse['code'])) {
            return $this->ErrorResponse['code'];
        }

        return '';
    }

    /**
     * Extract data from response array.
     *
     * @param array $data
     *
     * @return null|array
     */
    protected function prepare($data = array())
    {
        if (isset($data['response'])) {
            return $data['response'];
        } else {
            return null;
        }
    }

    /**
     * Init common params.
     *
     * @return array
     */
    protected function initRequestParams()
    {
        $requestParams = array(
            'login' => 'BrandConnect',
            'pwd' => '34ea992973ff43ada912664c856862ac',
        );

        return $requestParams;
    }

    /**
     * Sign request parameters.
     *
     * @param $params array
     *
     * @return array
     */
    public function signature($params)
    {
        ksort($params);

        return $params;
    }

    //ADD SANDBOX FUNCTION
    private function initMwsName()
    {
        $sandbox = 'sandbox.'.$this->mwsName;
        if (empty(Config::get($sandbox))) {
            return;
        }
        if (\App::environment('local') && env('APP_DEBUG')) {
            $this->mwsName = $sandbox;
        }
    }

    public function __destruct()
    {
        if($error = $this->errorMessage()){
            //test
            $message = "Results: " .$error." Params: ".print_r($this->signRequestParams,true);
            mail('jimmy.gao@eservicesgroup.com', $this->storeName.' error ', $message);
            //test end
        }
    }
}
