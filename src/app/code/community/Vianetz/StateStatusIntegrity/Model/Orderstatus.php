<?php

class Vianetz_StateStatusIntegrity_Model_Orderstatus extends Mage_Core_Model_Abstract
{
    /**
     * Event: sales_order_save_before
     *
     * @param Varien_Event_Observer $observer
     *
     * @return Vianetz_StateStatusIntegrity_Model_Orderstatus
     */
    public function checkStateStatusIntegrity(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();

        $this->_applyOrderStateLogic($order);

        $orderState = $this->getOrderState();
        $orderStatus = $this->getOrderStatus();

        // Order is new so nothing to do here.
        if (empty($orderState) === true || empty($orderStatus) === true) {
            return $this;
        }

        if ($this->_isStatusAssignedToState($orderStatus, $orderState) === false) {
            $errorMessage = Mage::helper('vianetz_statestatusintegrity')->__('Error: Order status "%s" is not assigned to state "%s".', $orderStatus, $orderState);
            Mage::throwException($errorMessage);
        }

        return $this;
    }

    /**
     * Return all states for current status.
     *
     * @param string $statusCode
     *
     * @return Mage_Sales_Model_Resource_Order_Status_Collection
     */
    protected function _getStateCollectionForStatus($statusCode)
    {
        return Mage::getModel('sales/order_status')->getCollection()
                ->addFieldToFilter('main_table.status', $statusCode)
                ->joinStates();
    }

    /**
     * Check if there is an assignment of status to state.
     *
     * @param string $statusCode
     * @param string $stateCode
     *
     * @return bool
     */
    protected function _isStatusAssignedToState($statusCode, $stateCode)
    {
        $statusStateCollection = $this->_getStateCollectionForStatus($statusCode)
            ->addStateFilter($stateCode);

        return ($statusStateCollection->count() === 1);
    }

    /**
     * Because of the fact that the method _checkState() (which is called in
     * Mage_Sales_Model_Order::_beforeSave()) may change the state AFTER our observer is run we have to replicate
     * the logic here.
     * As an alternative one could create a rewrite for the order model.
     *
     * @see Mage_Sales_Model_Order::_checkState()
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return string
     */
    protected function _applyOrderStateLogic(Mage_Sales_Model_Order $order)
    {
        $newOrderState = null;

        if (!$order->isCanceled()
            && !$order->canUnhold()
            && !$order->canInvoice()
            && !$order->canShip()) {
            if (0 == $order->getBaseGrandTotal() || $order->canCreditmemo()) {
                if ($order->getState() !== Mage_Sales_Model_Order::STATE_COMPLETE) {
                    $newOrderState = Mage_Sales_Model_Order::STATE_COMPLETE;
                }
            } elseif (floatval($order->getTotalRefunded()) || (!$order->getTotalRefunded()
                && $order->hasForcedCanCreditmemo())
            ) {
                if ($order->getState() !== Mage_Sales_Model_Order::STATE_CLOSED) {
                    $newOrderState = Mage_Sales_Model_Order::STATE_CLOSED;
                }
            }
        }

        if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW && $order->getIsInProcess()) {
            $newOrderState = Mage_Sales_Model_Order::STATE_PROCESSING;
        }

        if (empty($newOrderState) === false) {
            $defaultStatus = $order->getConfig()->getStateDefaultStatus($newOrderState);

            $this->setOrderState($newOrderState);
            $this->setOrderStatus($defaultStatus);
        } else {
            $this->setOrderState($order->getState());
            $this->setOrderStatus($order->getStatus());
        }

        return $this;
    }
}