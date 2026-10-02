<?php
// Copyright 2021 Fleming Computer.
// All Rights Reserved.
?>
<?php
class PayPal
{
    function getPayPalButtonV2($selector, $transactionId, $cart, $subtotal, $discount, $tax, $shipping)
    {
        // If PayPal account selector is not given but multiple PayPal are
        // configured, quit.
        if (($selector === '') && !isset($GLOBALS['pizza']['config']['paypal']['env']))
            return 'must select PayPal account';
        // If PayPal account selector is given but only one PayPal account is
        // configured, quit.
        if (($selector !== '') && !isset($GLOBALS['pizza']['config']['paypal'][$selector]))
            return 'requested PayPal account not configured';
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        // Select proper PayPal account.
        $paypal = $GLOBALS['pizza']['config']['paypal'];
        if ($selector !== '')
            $paypal = $paypal[$selector];
        $env = isset($paypal['env'])
            ? $paypal['env']
            : '';
        $noShipping = $shipping === false ? ", application_context: {shipping_preference: 'NO_SHIPPING'}"
            : '';
        $shippingAmount = $shipping === false ? 0 : $shipping;
        $sandbox = isset($paypal['sandbox'])
            ? $paypal['sandbox']
            : '';
        $production = isset($paypal['production'])
            ? $paypal['production']
            : '';
        $clientId = $env == 'production' ? $production : $sandbox;
        $total = $subtotal - $discount + $tax + $shippingAmount;
        $total = round($total, 2);
        $returnUrl = $urlRoot . $pagePath . '?successPayPal&transactionId=' . $transactionId;
        // Assemble the JavaScript items string.
        $itemsJs = 'items: [';
        foreach ($cart as $r)
        {
            $price = $r['item-price'];
            $quantity = $r['item-quantity'];
            $name = $r['item-name'];
            $description = $r['item-description'];
            // PayPal doesn't show description in receipt email, so tack it onto name.
            if ($description != '')
                $name .= ", $description";
            // Name must be no more than 127 characters.
            if (strlen($name) > 127)
                $name = substr($name, 0, 124) . '...';
            if (isset($r['giftWrap']))
            {
                // Maintain max 127 length.
                if (strlen($name) > 113)
                    $name = substr($name, 0, 110) . '...';
                $name .= ', Gift-Wrapped';
                $price += $r['giftWrap'];
            }
            // Escape single quotes.
            $name = str_replace("'", "\'", $name);
            $itemsJs .= <<<ITEM
            {
                name: '$name',
                unit_amount: {
                    currency_code: 'USD',
                    value: '$price'
                },
                quantity: '$quantity'
            },
            ITEM . "\n";
        }
        $itemsJs = substr($itemsJs, 0, -2) . "]"; // take off last ",\n"
        // Assemble the JavaScript onApprove shipping strings.
        $shippingInfo = '';
        $shippingUrl = '';
        if ($shipping !== false)
        {
            $shippingInfo = <<<SHIPPING_INFO
            var shippingLine1 = details.purchase_units[0].shipping.address.address_line_1;
            var shippingLine2 = details.purchase_units[0].shipping.address.address_line_2;
            var shippingCity = details.purchase_units[0].shipping.address.admin_area_2;
            var shippingState = details.purchase_units[0].shipping.address.admin_area_1;
            var shippingZip = details.purchase_units[0].shipping.address.postal_code;
            var shippingCountry = details.purchase_units[0].shipping.address.country_code;
            SHIPPING_INFO;
            $shippingUrl = <<<SHIPPING_URL
            url = url.concat('&address1=', encodeURIComponent(shippingLine1));
            url = url.concat('&address2=', encodeURIComponent(shippingLine2));
            url = url.concat('&city=', encodeURIComponent(shippingCity));
            url = url.concat('&state=', encodeURIComponent(shippingState));
            url = url.concat('&zip=', encodeURIComponent(shippingZip));
            url = url.concat('&country=', encodeURIComponent(shippingCountry));
            SHIPPING_URL;
        }
        $html = <<<HTML
        <script src="https://www.paypal.com/sdk/js?client-id=$clientId&disable-funding=credit&enable-funding=venmo"></script>
        <div id="paypal-button-container" style="text-align: center;"></div>
        <script>
            paypal.Buttons({
                createOrder: function(data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                currency_code: 'USD',
                                value: '$total',
                                breakdown: {
                                    item_total: {
                                        currency_code: 'USD',
                                        value: '$subtotal'
                                    },
                                    shipping: {
                                        currency_code: 'USD',
                                        value: '$shippingAmount'
                                    },
                                    tax_total: {
                                        currency_code: 'USD',
                                        value: '$tax'
                                    },
                                    discount: {
                                        currency_code: 'USD',
                                        value: '$discount'
                                    }
                                }
                            },
                            $itemsJs
                        }]
                        $noShipping
                    });
                },
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        var email = details.payer.email_address;
                        var firstName = details.payer.name.given_name;
                        var lastName = details.payer.name.surname;
                        $shippingInfo
                        var url = '$returnUrl'.concat('&email=', encodeURIComponent(email));
                        url = url.concat('&firstName=', encodeURIComponent(firstName));
                        url = url.concat('&lastName=', encodeURIComponent(lastName));
                        $shippingUrl
                        return actions.redirect(url);
                    });
                }
            }).render('#paypal-button-container');
        </script>
        HTML;
        return $html;
    }
}
