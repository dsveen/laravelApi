<?php

namespace App\Repository\FnacMws;

use Config;

class FnacCore
{
    protected $options;
    protected $mwsName = 'fnac-mws';
    protected $errorResponse = [];
    protected $fnacPartnerId;
    protected $fnacShopId;
    protected $fnacKey;
    protected $fnacToken;
    protected $fnacPath = 'auth';
    protected $requestXml;
    protected $authKeyWithToken;

    function __construct($storeName)
    {
        $this->setConfig();
        $this->setStore($storeName);
        $this->initFnacAuthToken();
    }

    public function query($requestXml)
    {
        $xmlResponse = $this->callFnacApi($requestXml);
        if ($xmlResponse) {
            $data = $this->convert($xmlResponse);
            $responseStatus = $data['@attributes']['status'];

            if ($responseStatus !== 'OK'
                && $responseStatus != "RUNNING"
                && $responseStatus != "ACTIVE"
            ) {
                if (isset($data["error"])) {
                    $this->errorResponse = $data["error"];
                }
            }

            return $this->prepare($data);
        }

        return null;
    }

    public function callFnacApi($requestXml)
    {
        libxml_use_internal_errors(true);
        if ($valid = $this->xmlSchemaValidation($requestXml, $this->getFnacPath())) {
            $xmlResponse  = $this->curl($requestXml);

            if ($xmlResponse === false) {
                $this->errorResponse = __LINE__ . libxml_get_errors();
            } else {
                return $xmlResponse;
            }
        }
    }

    public function xmlSchemaValidation($requestXml)
    {
        try {
            switch($this->getFnacPath()) {
                case 'auth':
                    $schema = "xsd/AuthenticationService.xsd";
                    break;

                case 'offers_update':
                    $schema = "xsd/OffersUpdateService.xsd";
                    break;

                case 'batch_status':
                    $schema = "xsd/BatchStatusService.xsd";
                    break;

                case 'orders_query':
                    $schema = "xsd/OrdersQueryService.xsd";
                    break;

                case 'orders_update':
                    $schema = "xsd/OrdersUpdateService.xsd";
                    break;

                case 'offers_query':
                    $schema = "xsd/OffersQueryService.xsd";
                    break;

                default:
                    return true;
            }

            $dom = new \DOMDocument;
            $dom->loadXML($requestXml);
            libxml_use_internal_errors(true);
            $tplPath = app_path() . '/Repository/FnacMws/';
            $valide = $dom->schemaValidate($tplPath . $schema);
            if( ! $valide)
            {
                $errorMessage = '';
                $errors = libxml_get_errors();
                foreach ($errors as $error) {
                    if ($error) {
                        switch ($error->level) {
                            case LIBXML_ERR_WARNING:
                                $errorMessage .= "<b>Warning $error->code</b>: ";
                                break;
                            case LIBXML_ERR_ERROR:
                                $errorMessage .= "<b>Error $error->code</b>: ";
                                break;
                            case LIBXML_ERR_FATAL:
                                $errorMessage .= "<b>Fatal Error $error->code</b>: ";
                                break;
                        }

                        $errorMessage .= trim($error->message) . " on line <b>{$error->line}</b><br>";
                    }
                }

                throw new \Exception("xml validation failed ! $errorMessage");
            }

            return true;
        } catch(Exception $e) {
            $this->errorResponse .= $e->getMessage();
        }

        return false;
    }

    /**
    * Make request to API url
    * @param $xml string
    * @param $info array - reference for curl status info
    * @return string
    */
    private function curl($xmlFeed, &$info = array())
    {
        if (empty($xmlFeed)) {
            return;
        }

        $ch = curl_init();
        // Open Curl connection
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $this->urlbase . $this->getFnacPath());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlFeed);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        return $data;
    }

    public function getFnacPath()
    {
        return $this->fnacPath;
    }

    /**
    * Convert response XML to associative array
    * @param $xml string
    * @return array
    */
    private function convert($xml)
    {
    if ($xml != "") {
        $obj = simplexml_load_string(trim($xml), null, LIBXML_NOCDATA);

        $array = json_decode(json_encode($obj), true);

        if (is_array($array)) {
            $array = $this->sanitize($array);

        }
            return $array;
    }

    return null;
    }

    /**
    * Clear array after convert. Remove empty arrays and change to string
    * @param $arr array
    * @return array
    */
    private function sanitize($arr)
    {
        foreach($arr AS $k => $v) {
            if (is_array($v)) {
                if (count($v) > 0) {
                    $arr[$k] = $this->sanitize($v);
                } else {
                    $arr[$k] = "";
                }
            }
        }

        return $arr;
    }

    public function setConfig()
    {
        $fnacServiceUrl = Config::get($this->mwsName . '.SERVICE_URL');
        if (isset($fnacServiceUrl)) {
            $this->urlbase = $fnacServiceUrl;
        } else {
            throw new Exception("Config file does not exist or cannot be read!");
        }
    }

    public function setStore($storeName)
    {
        $store = Config::get($this->mwsName . '.store');
        if (array_key_exists($storeName, $store)) {
            $this->storeName = $storeName;

            if (array_key_exists('partnerId', $store[$storeName])) {
                $this->fnacPartnerId = $store[$storeName]['partnerId'];
            } else {
                $this->log("Partner ID does not exist!", 'Warning');
            }

            if (array_key_exists('shopId', $store[$storeName])) {
                $this->fnacShopId = $store[$storeName]['shopId'];
            } else {
                $this->log("Shop ID does not exist!", 'Warning');
            }

            if (array_key_exists('key', $store[$storeName])) {
                $this->fnacKey = $store[$storeName]['key'];
            } else {
                $this->log("Key ID does not exist!", 'Warning');
            }

            if (array_key_exists('currency', $store[$storeName])) {
                $this->storeCurrency = $store[$storeName]['currency'];
            }

        } else {
            $this->log("Store $storeName does not exist", "Warning");
        }
    }

    public function  getStoreCurrency()
    {
        return $this->storeCurrency;
    }

    public function initFnacAuthToken()
    {
        if (!$this->fnacToken) {
            $this->setAuthRequestXml();
            $response    = $this->curl($this->getRequestXml());
            $xmlResponse = simplexml_load_string(trim($response));
            $responseStatus = (string) $xmlResponse->attributes()->status;
            if ($responseStatus == 'OK') {
                $this->fnacToken = $xmlResponse->token;
            }
        }

        if (isset($this->fnacToken)) {
            $this->setAuthKeyWithToken();
        }
    }

    private function setAuthRequestXml()
    {
            $xml = <<<XML
<?xml version='1.0' encoding='utf-8'?>
<auth xmlns='http://www.fnac.com/schemas/mp-dialog.xsd'>
    <partner_id>$this->fnacPartnerId</partner_id>
    <shop_id>$this->fnacShopId</shop_id>
    <key>$this->fnacKey</key>
</auth>
XML;

        $this->requestXml = $xml;
    }

    protected function getRequestXml()
    {
        return $this->requestXml;
    }

    public function getAuthKeyWithToken()
    {
        return $this->authKeyWithToken;
    }

    public function setAuthKeyWithToken()
    {
        $authKeyWithToken = "
            partner_id='$this->fnacPartnerId'
            shop_id='$this->fnacShopId'
            key='$this->fnacKey'
            token='$this->fnacToken'
            xmlns='http://www.fnac.com/schemas/mp-dialog.xsd'
        ";

        $this->authKeyWithToken = $authKeyWithToken;
    }

    /**
    * Extract data from response array
    * @param array $data
    * @return null|array
    */
    protected function prepare($data = array())
    {
        if (isset($data["order"])) {
            return $data["order"];
        } else {
            return null;
        }
    }

    /**
    * Fix issue with single result in response
    * @param array $arr
    * @return array
    */
    protected function fix($arr = array())
    {
        if (isset($arr[0])) {
            return $arr;
        }

        return array(0 => $arr);
    }
}