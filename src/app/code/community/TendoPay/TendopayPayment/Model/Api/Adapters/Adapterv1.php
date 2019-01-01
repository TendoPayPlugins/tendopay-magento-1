<?php

class TendoPay_TendopayPayment_Model_Api_Adapters_Adapterv1
{
    public function buildOrderTokenRequest($object)
    {
        // TODO: Add warning log in case if rounding changes amount, because it's potential problem
        $precision = 2;
        $this->_validateData($object);
        $shippingAddress = $object->getShippingAddress();
        $taxTotal = 0;
        $params['items'] = array();
        $catModel = Mage::getModel("catalog/product");

        foreach ($object->getAllVisibleItems() as $item) {
            /** @var Mage_Sales_Model_Order_Item $orderItem */
            $product_id = $item->getProductId(); //what is this $item->getData('product_id');
            $product = $catModel->load($product_id);

            $params['items'][] = array(
                'title'     => (string)$item->getName(),
                'description'     => (string)($product->getShortDescription() ? $product->getShortDescription() : $product->getDescription()),
                'SKU'      => $this->_truncateString( (string)$item->getSku() ),
                'price'    => round((float)$item->getPriceInclTax(), $precision)
            );

            //get the total discount amount
            $discount_amount = $item->getDiscountAmount();
            if ( !empty($discount_amount) && round((float)$discount_amount, $precision) > 0 ) {
                $discount_name = (string)$object->getCouponCode();
                if( empty($discount_name) || strlen(trim($discount_name)) == '' ) {
                    $discount_name = 'Discount:';
                }
                $params['discounts'][] =  array(
                    'displayName'   =>  substr( $discount_name . ' - ' . (string)$item->getName(), 0, 128 ),
                    'amount'        =>  array(
                        'amount'   => round((float)$item->getDiscountAmount(), $precision),
                        'currency' => (string)Mage::app()->getStore()->getCurrentCurrencyCode()
                    ),
                );
            }
            //get the total discount amount
            $taxTotal += $item->getTaxAmount();
        }

        $params['meta'] = array(
            'currency'         => (string)Mage::app()->getStore()->getCurrentCurrencyCode(),
            'thousand_separator'    => ",",
            'decimal_separator'       => ".",
            'version'   => 1
        );

        $params['order'] = array(
            'id'         => (string)Mage::app()->getStore()->getCurrentCurrencyCode(),
            'shipping'    => round((float)$shippingAddress->getShippingInclTax(), $precision),
            'subtotal'       => $object->getSubtotal(),
            'total'   => round((float)$object->getGrandTotal(), $precision)
        );

        if( !empty($object) && $object->getReservedOrderId() ) {
            $params['merchantReference'] = (string)$object->getReservedOrderId();
        }

        return $params;
    }
    /**
     * Since 0.12.7
     * Truncate the string in case of very long custom values
     *
     * @param string $string    string to truncate
     * @param string $length    string truncation length
     * @param string $appendStr    string to be appended after truncate
     * @return string
     */
    private function _truncateString($string, $length = 64, $appendStr = "") {
        $truncated_str = "";
        $useAppendStr = (strlen($string) > intval($length))? true:false;
        $truncated_str .= substr($string,0,$length);
        $truncated_str .= ($useAppendStr)? $appendStr:"";
        return $truncated_str;
    }

    private function _handleState( $object ) {
        $billing_country = $object->getBillingAddress()->getCountry();
        $shipping_country = $object->getShippingAddress()->getCountry();

        if( !empty($billing_country) ) {
            $list_state_required = $this->_getStateRequired();
            //if the country doesn't require state, make Suburb goes to State Field
            if( !in_array( $billing_country, $list_state_required ) ) {
                $object->getBillingAddress()->setRegion( $object->getBillingAddress()->getCity() )->save(); 
                $object->getShippingAddress()->setRegion( $object->getShippingAddress()->getCity() )->save(); 
            }
        }
    }

    private function _getStateRequired() {
        $destinations = (string)Mage::getStoreConfig('general/region/state_required');
        $state_required = !empty($destinations) ? explode(',', $destinations) : [];
        return $state_required; 
    }

    private function _validateData( $object ) {
        $errors = array();
        $this->_handleState( $object );
        $billingAddress = $object->getBillingAddress();
        $shippingAddress = $object->getShippingAddress();
    	$billing_postcode = $billingAddress->getPostcode();
    	$billing_state = $billingAddress->getRegion();
    	$billing_telephone = $billingAddress->getTelephone();
    	$billing_city = $billingAddress->getCity();
    	$billing_street = $billingAddress->getStreet1();

        if( empty($billing_postcode) ) {
            $errors[] = "Billing Postcode is required";
        }
        if( empty($billing_state) ) {
            $errors[] = "Billing State is required";
        }
        if( empty($billing_telephone) ) {
            $errors[] = "Billing Phone is required";
        }
        if( empty($billing_city) ) {
            $errors[] = "Billing City/Suburb is required";
        }
        if( empty($billing_street) ) {
            $errors[] = "Billing Address is required";
        }

        if( !empty($shippingAddress) ) {
        	$shipping_postcode = $shippingAddress->getPostcode();
        	$shipping_state = $shippingAddress->getRegion();
        	$shipping_telephone = $shippingAddress->getTelephone();
        	$shipping_city = $shippingAddress->getCity();
        	$shipping_street = $shippingAddress->getStreet1();

            if( empty($shipping_postcode) ) {
                $errors[] = "Shipping Postcode is required";
            }
            if( empty($shipping_state) ) {
                $errors[] = "Shipping State is required";
            }
            if( empty($shipping_telephone) ) {
                $errors[] = "Shipping Phone is required";
            }
            if( empty($shipping_city) ) {
                $errors[] = "Shipping City/Suburb is required";
            }
            if( empty($shipping_street) ) {
                $errors[] = "Shipping Address is required";
            }
        }

        if( !empty($errors) && count($errors) ) {
            throw new InvalidArgumentException( "<br/>" . implode($errors, '<br/>') );
        }
    }
}