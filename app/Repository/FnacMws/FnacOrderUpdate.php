<?php

namespace App\Repository\FnacMws;

class FnacOrderUpdate extends FnacOrderCore
{
    private $fnacOrderIds;
    private $orderAction;
    private $orderDetailAction;
    private $trackingNumber;
    private $courierName;

    public function __construct($store)
    {
        parent::__construct($store);
        $this->setFnacAction('orders_update');
    }

    public function updateTrackingNumber($xmlData="")
    {
        $this->setRequestTrackingNumberXml($xmlData);
        return parent::query($this->getRequestXml());
    }

    protected function setRequestTrackingNumberXml($xmlData)
    {
        $this->requestXml = '<?xml version="1.0" encoding="utf-8"?>';
        $this->requestXml .= '<orders_update '. $this->getAuthKeyWithToken() .'>';
        $this->requestXml .= $xmlData;
        $this->requestXml .= '</orders_update>';
    }

    public function updateFnacOrdersStatus()
    {
        $this->setRequestUpdateOrdersStatusXml();

        return parent::query($this->getRequestXml());
    }

    protected function setRequestUpdateOrdersStatusXml()
    {
        if ($orderIds = $this->getFnacOrderIds()) {
            $xmlData = '<?xml version="1.0" encoding="utf-8"?>';
            $xmlData .= '<orders_update '. $this->getAuthKeyWithToken() .'>';
            foreach ($orderIds as $orderId) {
                $msgDom =   '<order order_id="'. $orderId .'" action="'. $this->getOrderAction() .'">';
                $msgDom .=      '<order_detail>';
                $msgDom .=          '<action>'. $this->getOrderDetailAction() .'</action>';
                $msgDom .=      '</order_detail>';
                $msgDom .=  '</order>';

                $xmlData .= $msgDom;
            }

            $xmlData .= '</orders_update>';

            $this->requestXml = $xmlData;
        }
    }

    public function getShippingMethod()
    {
        return $this->shippingMethod;
    }

    public function getTrackingNumber()
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber($trackingNumber)
    {
        $this->trackingNumber = $trackingNumber;
    }

    public function getCourierName()
    {
        return $this->courierName;
    }

    public function setCourierName($courierName)
    {
        $this->courierName = $courierName;
    }

    public function getFnacOrderIds()
    {
        return $this->fnacOrderIds;
    }

    public function setFnacOrderIds($fnacOrderIds)
    {
        $this->fnacOrderIds = $fnacOrderIds;
    }

    public function getOrderAction()
    {
        return $this->orderAction;
    }

    public function setOrderAction($orderAction)
    {
        $this->orderAction = $orderAction;
    }

    public function getOrderDetailAction()
    {
        return $this->orderDetailAction;
    }

    public function setOrderDetailAction($orderDetailAction)
    {
        $this->orderDetailAction = $orderDetailAction;
    }

    protected function prepare($data = [])
    {
        if (isset($data['order'])) {
            return parent::fix($data['order']);
        }

        return null;
    }
}
