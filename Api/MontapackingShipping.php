<?php

namespace Montapacking\MontaCheckout\Api;

use Montapacking\MontaCheckout\Api\Objects\Address as MontaCheckout_Address;
use Montapacking\MontaCheckout\Api\Objects\Order as MontaCheckout_Order;
use Montapacking\MontaCheckout\Api\Objects\Product as MontaCheckout_Product;
use Montapacking\MontaCheckout\Api\Objects\TimeFrame as MontaCheckout_TimeFrame;
use Montapacking\MontaCheckout\Api\Objects\TimeFrame as MontaCheckout_PickupPoint;

/**
 * Class MontapackingShipping
 *
 */
class MontapackingShipping
{

    /**
     * @var string
     */
    private $_user = '';
    /**
     * @var string
     */
    private $_pass = '';

    /**
     * @var string
     */
    private $_googlekey = '';

    /**
     * @var array
     */
    private $_basic = null;
    /**
     * @var null
     */
    private $_order = null;
    /**
     * @var null
     */
    private $_shippers = null;
    /**
     * @var null
     */
    private $_products = null;
    /**
     * @var null
     */
    private $_allowedshippers = null;
    /**
     * @var bool
     */
    private $_onstock = true;
    /**
     * @var null
     */
    public $address = null;

    /**
     * @var null
     */
    private $_logger = null;

    /**
     * @var
     */
    private $_carrierConfig;

    /**
     * MontapackingShipping constructor.
     *
     * @param      $origin
     * @param      $user
     * @param      $pass
     * @param      $googlekey
     * @param      $language
     * @param bool $test
     */
    public function __construct($origin, $user, $pass, $googlekey, $language, $test = false)
    {
        $this->origin = $origin;
        $this->_user = $user;
        $this->_pass = $pass;
        $this->_googlekey = $googlekey;

        $this->_basic = [
            'Origin' => $origin,
            'Currency' => 'EUR',
            'Language' => $language,
        ];
    }

    /**
     * @param $logger
     */
    public function setLogger($logger)
    {
        $this->_logger = $logger;
    }

    /**
     * @param $config
     */
    public function setCarrierConfig($config)
    {
        $this->_carrierConfig = $config;
    }

    /**
     * @return bool
     */
    public function checkConnection()
    {

        $result = $this->call('info', ['_basic']);

        if (null === $result) {
            return false;
        }
        return true;
    }

    /**
     * @param $value
     */
    public function setOnstock($value)
    {
        $this->_onstock = $value;
    }

    /**
     * @return bool
     */
    public function getOnstock()
    {
        return $this->_onstock;
    }

    /**
     * @param $total_incl
     * @param $total_excl
     */
    public function setOrder($total_incl, $total_excl)
    {

        $this->_order = new MontaCheckout_Order($total_incl, $total_excl);
    }

    /**
     * @param $street
     * @param $housenumber
     * @param $housenumberaddition
     * @param $postalcode
     * @param $city
     * @param $state
     * @param $countrycode
     */
    public function setAddress($street, $housenumber, $housenumberaddition, $postalcode, $city, $state, $countrycode)
    {

        $this->address = new MontaCheckout_Address(
            $street,
            $housenumber,
            $housenumberaddition,
            $postalcode,
            $city,
            $state,
            $countrycode,
            $this->_googlekey
        );
    }

    /**
     * @param null $shippers
     */
    public function setShippers($shippers = null)
    {

        if (is_array($shippers)) {
            $this->_shippers = $shippers;
        } else {
            $this->shippers[] = $shippers;
        }
    }

    /**
     * @param     $sku
     * @param     $quantity
     * @param int $length
     * @param int $width
     * @param int $height
     * @param int $weight
     */
    public function addProduct($sku, $quantity, $length = 0, $width = 0, $height = 0, $weight = 0)
    {

        $this->_products['products'][] = new MontaCheckout_Product($sku, $length, $width, $height, $weight, $quantity);
    }

    /**
     * @return array|null
     */
    public function getShippers()
    {

        $shippers = null;

        $result = $this->call('info', ['_basic']);
        if (isset($result->Origins)) {

            $origins = is_array($result->Origins) ? $result->Origins : [$result->Origins];

            // Array goedzetten
            if (is_array($result->Origins)) {
                $origins = $result->Origins;
            } else {
                $origins[] = $result->Origins;
            }

            // Shippers omzetten naar shipper object
            foreach ($origins as $origin) {

                // Check of shipper options object er is
                if (isset($origin->ShipperOptions)) {

                    foreach ($origin->ShipperOptions as $shipper) {

                        $shippers[] = new MontaCheckout_Shipper(
                            $shipper->ShipperDescription,
                            $shipper->ShipperCode
                        );

                    }

                }

            }

            return $origins;

        }

        return $shippers;
    }

    /**
     * @param $sku
     *
     * @return bool
     */
    public function checkStock($sku)
    {

        $url = "https://api.montapacking.nl/rest/v5/product/" . $sku . "/stock";

        $this->_pass = htmlspecialchars_decode($this->_pass);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $this->_user . ":" . $this->_pass);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $result = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($result);


        if (null !== $result && property_exists($result, 'Message') && $result->Message == 'Zero products found with sku ' . $sku) {
            return false;
        } else if (null !== $result && property_exists($result, 'Stock') && $result->Stock->StockAvailable <= 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param bool $onstock
     * @param bool $mailbox
     * @param bool $mailboxfit
     * @param bool $trackingonly
     * @param bool $insurance
     *
     * @return array
     */
    public function getPickupOptions($onstock = true, $mailbox = false, $mailboxfit = false, $trackingonly = false, $insurance = false) //phpcs:ignore
    {

        if ($this->_carrierConfig->getDisablePickupPoints()) {
            return [];
        }

        $pickups = [];

        $this->_basic = array_merge(
            $this->_basic,
            [
                'OnlyPickupPoints' => 'true',
                //'MaxNumberOfPickupPoints' => 3,
                'ProductsOnStock' => ($onstock) ? 'TRUE' : 'FALSE',
                'MaiboxShipperMandatory' => $mailbox,
                'TrackingMandatory' => $trackingonly,
                'InsuranceRequired' => $insurance,
                'ShipmentFitsThroughDutchMailbox' => $mailboxfit,
            ]
        );

        $this->_allowedshippers = ['PAK', 'DHLservicepunt', 'DPDparcelstore', 'AFH', 'DHLParcelConnectPickupPoint', 'UPSAP', 'GLSPickupPoint'];

        // Timeframes omzetten naar bruikbaar object
        $result = $this->call('ShippingOptions', ['_basic', '_shippers', '_order', 'address', '_products', '_allowedshippers']); //phpcs:ignore
        if (isset($result->Timeframes)) {

            // Shippers omzetten naar shipper object
            foreach ($result->Timeframes as $timeframe) {

                $pickups[] = new MontaCheckout_PickupPoint(
                    $timeframe->From,
                    $timeframe->To,
                    $timeframe->TypeCode,
                    $timeframe->PickupPointDetails,
                    $timeframe->ShippingOptions,
                    $timeframe->FromToTypeCode
                );

            }

        }

        return $pickups;
    }

    /**
     * @param bool $onstock
     * @param bool $mailbox
     * @param bool $mailboxfit
     * @param bool $trackingonly
     * @param bool $insurance
     *
     * @return array
     */
    public function getShippingOptions($onstock = true, $mailbox = false, $mailboxfit = false, $trackingonly = false, $insurance = false) //phpcs:ignore
    {
        $timeframes = [];

        if (trim($this->address->postalcode) && (trim($this->address->housenumber) || trim($this->address->street))) {

            // Basis gegevens uitbreiden met shipping option specifieke data
            $this->_basic = array_merge(
                $this->_basic,
                [
                    'ProductsOnStock' => ($onstock) ? 'TRUE' : 'FALSE',
                    'MailboxShipperMandatory' => $mailbox,
                    'TrackingMandatory' => $trackingonly,
                    'MaxNumberOfPickupPoints' => 0,
                    'InsuranceRequired' => $insurance,
                    'ShipmentFitsThroughDutchMailbox' => $mailboxfit,
                ]
            );

            // Timeframes omzetten naar bruikbaar object
            $result = $this->call('ShippingOptions', ['_basic', '_shippers', '_order', 'address', '_products']);

            if (isset($result->Timeframes)) {

                // Shippers omzetten naar shipper object
                foreach ($result->Timeframes as $timeframe) {

                    $timeframes[] = new MontaCheckout_TimeFrame(
                        $timeframe->From,
                        $timeframe->To,
                        $timeframe->TypeCode,
                        $timeframe->TypeDescription,
                        $timeframe->ShippingOptions,
                        $timeframe->FromToTypeCode
                    );

                }

            }
        }

        return $timeframes;
    }

    /**
     * @param      $method
     * @param null $send
     *
     * @return mixed
     */
    public function call($method, $send = null)
    {

        $request = '?';
        if ($send != null) {

            // Request neede data
            foreach ($send as $data) {

                if (isset($this->{$data}) && $this->{$data} != null) {

                    if (!is_array($this->{$data})) {

                        $request .= '&' . http_build_query($this->{$data}->toArray());

                    } else {
                        $request .= '&' . http_build_query($this->{$data});

                    }

                }

            }

        }

        $method = strtolower($method);

        $url = "https://api.montapacking.nl/rest/v5/" . $method;

        $ch = curl_init();

        $this->_pass = htmlspecialchars_decode($this->_pass);


        curl_setopt($ch, CURLOPT_URL, $url . '?' . $request);
        curl_setopt($ch, CURLOPT_USERPWD, $this->_user . ":" . $this->_pass);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            $logger = $this->_logger;
            $context = ['source' => 'Montapacking Checkout'];
            $logger->critical($error_msg . " (" . $url . ")", $context);
            $result = null;
        }

        curl_close($ch);

        if ($result == null) {

            sleep(1);
            $ch = curl_init();

            $this->_pass = htmlspecialchars_decode($this->_pass);

            curl_setopt($ch, CURLOPT_URL, $url . '?' . $request);
            curl_setopt($ch, CURLOPT_USERPWD, $this->_user . ":" . $this->_pass);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);

            $result = curl_exec($ch);

            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
                $logger = $this->_logger;
                $context = ['source' => 'Montapacking Checkout'];
                $logger->critical($error_msg . " (" . $url . ")", $context);
                $result = null;
            }

            curl_close($ch);
        }



        $result = json_decode($result);

        $url = "https://api.montapacking.nl/rest/v5/" . $method . $request;

        if (null !== $this->_logger && null === $result) {
            $logger = $this->_logger;
            $context = ['source' => 'Monta Checkout'];
            $logger->critical("Webshop was unable to connect to Monta REST api. Please check your username and password. Otherwise please contact Montapacking (" . $url . ")", $context); //phpcs:ignore
        } elseif (null !== $this->_logger) {
            $logger = $this->_logger;
            $context = ['source' => 'Monta Checkout'];
            $logger->notice("Connection logged (" . $url . ")", $context);
        }

        if ($this->_carrierConfig->getLogErrors()) {

            if (null !== $this->_logger && isset($result->Warnings)) {

                foreach ($result->Warnings as $warning) {

                    $logger = $this->_logger;
                    $context = ['source' => 'Montapacking Checkout'];

                    if (null !== $warning->ShipperCode) {
                        $logger->notice($warning->ShipperCode . " - " . $warning->Message, $context);
                    } else {
                        $logger->notice($warning->Message, $context);
                    }

                }
            }

            if (null !== $this->_logger && isset($result->Notices)) {

                foreach ($result->Notices as $notice) {
                    $logger = $this->_logger;
                    $context = ['source' => 'Montapacking Checkout'];

                    if (null !== $notice->ShipperCode) {
                        $logger->notice($notice->ShipperCode . " - " . $notice->Message, $context);
                    } else {
                        $logger->notice($notice->Message, $context);
                    }

                }
            }

            if (null !== $this->_logger && isset($result->ImpossibleShipperOptions)) {

                foreach ($result->ImpossibleShipperOptions as $impossibleoption) {
                    foreach ($impossibleoption->Reasons as $reason) {

                        $logger = $this->_logger;
                        $context = ['source' => 'Montapacking Checkout'];
                        $logger->notice($impossibleoption->ShipperCode . " - " . $reason->Code . " | " . $reason->Reason, $context); //phpcs:ignore
                    }
                }

            }

        }

        return $result;
    }
}
