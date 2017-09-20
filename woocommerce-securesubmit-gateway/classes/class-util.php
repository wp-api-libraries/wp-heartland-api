<?php

class WC_SecureSubmit_Util
{
    /**
     * Wraps WooCommerce data access APIs to ensure proper data is retrieved
     * without the merchant experiencing issues during the WooCommerce
     * 2.6 -> 3.0 transition.
     *
     * @param object $object   Data object to test
     * @param string $method   Method name for 3.0 API
     * @param string $property Property name for 2.6 API
     *
     * @return mixed
     */
    public static function getData($object, $method, $property)
    {
        if (method_exists($object, $method)) {
            return $object->{$method}();
        }

        if (property_exists($object, $property) || isset($object->{$property})) {
            return $object->{$property};
        }

        return null;
    }
}
