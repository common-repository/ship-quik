<?php
/**
 * Uninstall file.
 *
*/

// If plugin is not being uninstalled, exit (do nothing).
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settingOptions = array(
    'ship_quik_version',
    '_ship_quik_paymentMethod',
    '_ship_quik_pricingMode',
    '_ship_quik_chargeDUA',
    '_ship_quik_isFreeShipping',
    '_ship_quik_shippingTableRates',
    '_ship_quik_freeShippingPriceFrom',
    '_ship_quik_shopFeeType',
    '_ship_quik_shopFeeValue',
    '_ship_quik_comments',
    '_ship_quik_senderAddressCompany',
    '_ship_quik_senderAddressStreet',
    '_ship_quik_senderAddressCity',
    '_ship_quik_senderAddressPostalCode',
    '_ship_quik_senderAddressCountryCode',
    '_ship_quik_senderAddressPhoneNumber',
    '_ship_quik_senderAddressEmail',
    '_ship_quik_senderAddressCif',
    '_ship_quik_shippingProductListWithoutParcel',
    '_ship_quik_shippingPackageList',
    '_ship_quik_activationEmail',
    '_ship_quik_activationKey',
    '_ship_quik_suppliers',
    '_ship_quik_activation',
    '_ship_quik_printProducts',
    '_ship_quik_orderStatus',
    '_ship_quik_customerFieldDNI'
);

// Clear up our settings
foreach ($settingOptions as $settingName) {
    delete_option($settingName);
}
