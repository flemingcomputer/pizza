<?php
// Copyright 2025 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Cart
{
    private $currentPromo;
    private $giftWrapAmount;
    private $id;
    private $paypalSelector;
    private $shipInternational;
    private $shipping;
    private $taxRate;

    // Form inputs.
    private $internationalShipping;
    private $promoCode;

    // Form inputs for manual order entry.
    private $manualDateTime;
    private $manualEmail;
    private $manualFirstName;
    private $manualLastName;
    private $manualAddress1;
    private $manualAddress2;
    private $manualCity;
    private $manualState;
    private $manualZip;
    private $manualCountry;
    private $manualNotifyAdmin;
    private $manualNotifyCustomer;

    function __construct()
    {
        $this->id = 'Cart';
    }

    function getHtml()
    {
        $this->initThis();
        if (!empty($_POST) && isset($_POST['formId'])
            && ($_POST['formId'] == $this->id)) $this->validateInput($_POST);

        $cart = $GLOBALS['pizza']['cart']->getCart();
        $transactionId = sge('cart-transaction-id', false);
        if (hg('checkout') && !empty($cart) && ($transactionId !== false))
        {
            $this->showCheckout($cart, $transactionId);
        }
        if (isAdministrator() && hg('manual') && !empty($cart) && ($transactionId !== false))
        {
            $this->showManualEntry($cart, $transactionId);
        }

        if (hg('successPayPal') && !empty($cart)
            && hs('cart-transaction-id') && hg('transactionId')
            && (sg('cart-transaction-id') == gg('transactionId'))
            && hg('email'))
        {
            sc('cart-transaction-id');
            sc('cart-no-shipping');
            $s = false;
            if (hg('dateTime')) $s = strtotime(gg('dateTime'));
            if ($s === false) $s = time();
            $dateTime = gmdate('Y-m-d H:i:s', $s);
            $GLOBALS['pizza']['cart']->finalizeOrder(
                gg('transactionId'), $dateTime,
                gg('email'), gg('firstName'), gg('lastName'),
                $this->currentPromo, $this->shipping);
            $this->showThankYou($cart);
        }

        return $this->showCart($cart);
    }

    private function getTotals($cart)
    {
        $subtotal = 0;
        array_walk($cart, function ($r) use (&$subtotal) {
            $subtotal += $r['item-quantity'] * ($r['item-price'] + ($r['giftWrap'] ?? 0));
        });
        $discount = 0;
        if ($this->currentPromo !== false)
        {
            // $promoCode = $this->currentPromo[0];
            $promoValue = $this->currentPromo[1];
            if (substr($promoValue, 0, 1) == '$')
                $discount = substr($promoValue, 1);
            else if (substr($promoValue, -1) == '%')
                $discount = $subtotal * (substr($promoValue, 0, -1) / 100.0);
            if ($discount < 0) $discount = 0;
            else if ($discount > $subtotal) $discount = $subtotal;
        }
        $discount = round($discount, 2);
        $tax = ($subtotal - $discount) * ($this->taxRate / 100);
        $tax = round($tax, 2);
        return array(
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax
        );
    }

    private function initThis()
    {
        $o = sge($this->id, false);
        $this->currentPromo = $o ? $o->currentPromo : false;
        $this->giftWrapAmount = $GLOBALS['pizza']['config']['cart']['gift-wrap'] ?? false;
        $this->internationalShipping = $o ? $o->internationalShipping : array('value' => '', 'error' => '');
        $this->manualDateTime = $o ? $o->manualDateTime : array('value' => '', 'error' => '');
        $this->manualEmail = $o ? $o->manualEmail : array('value' => '', 'error' => '');
        $this->manualFirstName = $o ? $o->manualFirstName : array('value' => '', 'error' => '');
        $this->manualLastName = $o ? $o->manualLastName : array('value' => '', 'error' => '');
        $this->manualAddress1 = $o ? $o->manualAddress1 : array('value' => '', 'error' => '');
        $this->manualAddress2 = $o ? $o->manualAddress2 : array('value' => '', 'error' => '');
        $this->manualCity = $o ? $o->manualCity : array('value' => '', 'error' => '');
        $this->manualState = $o ? $o->manualState : array('value' => '', 'error' => '');
        $this->manualZip = $o ? $o->manualZip : array('value' => '', 'error' => '');
        $this->manualCountry = $o ? $o->manualCountry : array('value' => '', 'error' => '');
        $this->manualNotifyAdmin = $o ? $o->manualNotifyAdmin : array('value' => true, 'error' => '');
        $this->manualNotifyCustomer = $o ? $o->manualNotifyCustomer : array('value' => false, 'error' => '');
        $this->paypalSelector = $GLOBALS['pizza']['config']['cart']['paypal-selector'] ?? '';
        $this->promoCode = $o ? $o->promoCode : array('value' => '', 'error' => '');
        $this->shipInternational = ($GLOBALS['pizza']['config']['cart']['ship-international'] ?? false) === true;
        $this->shipping = $GLOBALS['pizza']['cart']->shippingCost();
        // If an international shipping amount is given, make it the shipping charge.
        if ($this->internationalShipping['value'] > 0)
            $this->shipping = $this->internationalShipping['value'];
        $this->taxRate = $GLOBALS['pizza']['config']['cart']['taxRate'] ?? 0;
    }

    private function sendMail($replyTo, $to, $subject, $body)
    {
        require_once('MailTools.php');
        $mt = new MailTools();
        $mt->setReplyTo($replyTo);
        if ($GLOBALS['pizza']['config']['smtpAuth'] === true)
        {
            // Using, for example, Gmail's SMTP service.
            $mt->setFrom($GLOBALS['pizza']['config']['smtpUsername']);
        }
        else
        {
            // Using localhost.
            $mt->setFrom('info@' . strtolower($GLOBALS['pizza']['config']['siteName']));
        }
        $mt->setTo($to);
        $mt->setSubject($subject);
        $mt->setHtmlBody($body);
        return $mt->send();
    }

    private function showCart($cart, $changeable = true)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $disabled = $changeable ? '' : 'disabled="disabled"';

        if (hg('remove') && $changeable)
        {
            $key = gg('remove');
            $GLOBALS['pizza']['cart']->remove($key);
            if ($GLOBALS['pizza']['cart']->numRows() == 0)
            {
                $this->currentPromo = false;
                $this->internationalShipping = array('value' => '', 'error' => '');
                ss($this->id, $this);
            }
            relocateNow($urlRoot . $pagePath);
        }

        if (hg('removePromo'))
        {
            $this->currentPromo = false;
            ss($this->id, $this);
        }

        ob_start();
        if ($changeable)
        {
            ?>
            <script>
            $(function() {
                if (sessionStorage.getItem("cart-windowTop") != null) {
                    $(window).scrollTop(sessionStorage.getItem("cart-windowTop"));
                    sessionStorage.removeItem("cart-windowTop");
                }
                $(".cartInput").change(function() {
                    sessionStorage.setItem("cart-windowTop", $(window).scrollTop());
                    $(this.form).append($("<input>")
                        .attr("type", "hidden")
                        .attr("name", "update")
                    );
                    this.form.submit();
                    $(this.form).find(":input").prop("disabled", true);
                });
            });
            </script>
            <h1>Your Cart</h1>
            <?php
        }
        ?>
        <div class="cart">
            <form method="post" enctype="application/x-www-form-urlencoded"
            action="<?=$urlRoot?><?=$pagePath?>">
            <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>" />

            <?php
            if (count($cart) == 0)
            {
                ?>
                <p>Your cart is empty.</p>
                <div class="buttons">
                    <input class="submit" type="submit" name="continueShopping" value="Continue Shopping" />
                </div>
                <?php
            }
            else
            {
                ?>
                <table border="0" cellpadding="0" cellspacing="0" summary="Shopping Cart">
                <tr>
                    <th class="leftmost">Item</th>
                    <?php
                    if ($this->giftWrapAmount)
                    {
                        ?>
                        <th style="text-align: center;">Gift<br>Wrap<br>$<?=$this->giftWrapAmount?> ea.</th>
                        <?php
                    }
                    ?>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: center;">Price</th>
                    <th style="text-align: center;">&nbsp;</th>
                </tr>
                <?php
                $i = 0;
                foreach ($cart as $key => $cartRow)
                {
                    $giftWrap = '';
                    if ($this->giftWrapAmount && isset($cartRow['giftWrap']))
                    {
                        $giftWrap = 'checked="checked"';
                    }
                    $quantity = $cartRow['item-quantity'];
                    $itemPrice = $cartRow['item-price'];
                    $itemName = $cartRow['item-nameHtml']
                        ?? myHtmlEntities($cartRow['item-name']);
                    $itemDescription = $cartRow['item-descriptionHtml']
                        ?? myHtmlEntities($cartRow['item-description']);
                    $subtotal = $itemPrice * $quantity;
                    if ($giftWrap != '') $subtotal += $this->giftWrapAmount * $quantity;
                    ?>
                    <tr>
                    <td>
                        <div>
                            <?=$itemName?><br>
                            <?=$itemDescription?>
                        </div>
                    </td>
                    <?php
                    if ($this->giftWrapAmount)
                    {
                        ?>
                        <td style="text-align: center;"><input class="cartInput" <?=$disabled?> name="cartGiftWrap[<?=$i?>]" type="checkbox" <?=$giftWrap?> /></td>
                        <?php
                    }
                    if (!$disabled)
                    {
                        ?>
                        <td class="quantity"><input class="cartQuantity cartInput" name="cartQuantities[<?=$i?>]" type="text" value="<?=$quantity?>" /></td>
                        <?php
                    }
                    else
                    {
                        ?>
                        <td class="quantity"><?=$quantity?></td>
                        <?php
                    }
                    ?>
                    <td class="subtotal">$<?=number_format($subtotal, 2)?></td>
                    <?php
                    if (!$disabled)
                    {
                        ?>
                        <td class="options"><a href="<?=$urlRoot . $pagePath?>?remove=<?=urlencode($key)?>">✖</a></td>
                        <?php
                    }
                    else
                    {
                        ?>
                        <td>&nbsp;</td>
                        <?php
                    }
                    ?>
                    </tr>
                    <?php
                    $i++;
                }
                $totalColSpan = 2;
                if ($this->giftWrapAmount) $totalColSpan++;

                // Compute the subtotal, discount, and tax.
                $t = $this->getTotals($cart);
                $subtotal = $t['subtotal'];
                $discount = $t['discount'];
                $tax = $t['tax'];
                $total = $subtotal - $discount + $tax + $this->shipping;

                if (($this->currentPromo !== false)
                    || ($tax > 0)
                    || ($this->shipping > 0)
                    || $this->shipInternational)
                {
                    ?>
                    <tr>
                        <td class="subtotalLabel" colspan="<?=$totalColSpan?>">Subtotal</td>
                        <td class="subtotalAmount">$<?=number_format($subtotal, 2)?></td>
                        <td class="update">&nbsp;</td>
                    </tr>
                    <?php
                    if ($this->currentPromo !== false)
                    {
                        ?>
                        <tr>
                            <td class="taxLabel" colspan="<?=$totalColSpan?>">Promo Code <?=myHtmlEntities($this->currentPromo[0])?></td>
                            <td class="taxAmount">-$<?=number_format($discount, 2)?></td>
                            <?php
                            if (!$disabled)
                            {
                                ?>
                                <td class="options"><a href="<?=$urlRoot . $pagePath?>?removePromo">remove</a></td>
                                <?php
                            }
                            else
                            {
                                ?>
                                <td>&nbsp;</td>
                                <?php
                            }
                            ?>
                        </tr>
                        <?php
                    }
                    if ($tax > 0)
                    {
                        ?>
                        <tr>
                            <td class="taxLabel" colspan="<?=$totalColSpan?>"><?=$this->taxRate?>% Tax</td>
                            <td class="taxAmount">$<?=number_format($tax, 2)?></td>
                            <td class="options">&nbsp;</td>
                        </tr>
                        <?php
                    }
                    if (($this->shipping > 0) && ($this->internationalShipping['value'] == ''))
                    {
                        ?>
                        <tr>
                            <td class="taxLabel" colspan="<?=$totalColSpan?>">U.S. Shipping</td>
                            <td class="taxAmount">$<?=number_format($this->shipping, 2)?></td>
                            <td class="options">&nbsp;</td>
                        </tr>
                        <?php
                    }
                    if ($this->shipInternational)
                    {
                        if (!$disabled)
                        {
                            $quoteUrl = isset($GLOBALS['pizza']['config']['cart']['ship-international-quote-url'])
                                ? $GLOBALS['pizza']['config']['cart']['ship-international-quote-url']
                                : '/';
                            $quoteUrl = $urlRoot . $quoteUrl;
                            ?>
                            <tr>
                                <td class="taxLabel" colspan="<?=$totalColSpan?>">(<a href="<?=$quoteUrl?>">get quote</a>) Intl. Shipping</td>
                                <td class="taxAmount" style="white-space: nowrap;">$<input class="cartInput intlShipping" name="internationalShipping" type="text" value="<?=myHtmlEntities($this->internationalShipping['value'])?>" /></td>
                                <td class="options">&nbsp;</td>
                            </tr>
                            <?php
                        }
                        else if ($this->internationalShipping['value'] > 0)
                        {
                            ?>
                            <tr>
                                <td class="taxLabel" colspan="<?=$totalColSpan?>">Intl. Shipping</td>
                                <td class="taxAmount" style="white-space: nowrap;">
                                    $<?=myHtmlEntities($this->internationalShipping['value'])?>
                                </td>
                                <td class="options">&nbsp;</td>
                            </tr>
                            <?php
                        }
                    }
                }
                ?>
                <tr>
                    <td class="promoCode">
                    <?php
                    require_once('Promos.php');
                    $p = new Promos();
                    $promos = $p->getPromos();
                    if (!$disabled && !empty($promos['active']) && ($this->currentPromo === false))
                    {
                        $class = '';
                        $placeHolder = 'Promo Code?';
                        if ($this->promoCode['error'] != '')
                        {
                            $class = 'class="error-placeholder"';
                            $placeHolder = 'Invalid Promo';
                            $this->promoCode['value'] = '';
                            $this->promoCode['error'] = '';
                            ss($this->id, $this);
                        }
                        ?>
                        <input <?=$class?> name="promoCode" placeholder="<?=myHtmlEntities($placeHolder)?>" type="text" value="<?=myHtmlEntities($this->promoCode['value'])?>" />
                        <input class="submit" name="applyPromo" type="submit" value="Apply" />
                        <?php
                    }
                    ?>
                    </td>
                    <td class="totalLabel" colspan="<?=$totalColSpan - 1?>">Total</td>
                    <td class="totalAmount">$<?=number_format($total, 2)?></td>
                    <td class="update">&nbsp;</td>
                </tr>
                </table>

                <div class="buttons">
                    <?php
                    if (!$disabled)
                    {
                        ?>
                        <input class="submit" name="continueShopping" type="submit" value="Continue Shopping" />
                        <input class="submit" name="checkout" type="submit" value="Check Out" />
                        <?php
                    }
                    ?>
                </div>
                <?php
            }
            ?>
            </form>
        </div>
        <?php
        // Refresh the cart 2 seconds after session timeout to remove carted items.
        header('Refresh: ' . ($GLOBALS['pizza']['session']->timeout + 2));
        return ob_get_clean();
    }

    private function showCheckout($cart, $transactionId)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $t = $this->getTotals($cart);
        $subtotal = $t['subtotal'];
        $discount = $t['discount'];
        $tax = $t['tax'];
        ob_start();
        ?>
        <h1>Almost Done</h1>
        <p>
            Review your information and select a payment option to complete your order.
        </p>
        <?php
        $html = ob_get_clean();
        $html .= $this->showCart($cart, false);
        require_once('PayPal.php');
        $pp = new PayPal();
        $shipping = (sge('cart-no-shipping', false) === true) ? false : $this->shipping;
        $html .= $pp->getPayPalButtonV2($this->paypalSelector, $transactionId, $cart, $subtotal, $discount, $tax, $shipping);
        if (isAdministrator())
        {
            ob_start();
            ?>
            <a href="<?=$urlRoot . $pagePath?>?manual">complete as missing order</a>
            <?php
            $html .= ob_get_clean();
        }
        showFinal($html);
    }

    private function showManualEntry($cart, $transactionId)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $t = $this->getTotals($cart);
        $subtotal = $t['subtotal'];
        $discount = $t['discount'];
        $tax = $t['tax'];
        ob_start();
        ?>
        <h1>Manual Order Entry</h1>
        <p>
            Complete information below.
        </p>
        <?php
        echo $this->showCart($cart, false);
        ?>
        <div class="cart">
            <form action="<?=$urlRoot?><?=$pagePath?>" enctype="application/x-www-form-urlencoded" method="post">
                <input name="formId" type="hidden" value="<?=myHtmlEntities($this->id)?>" />
                <div>
                    <?php
                    $class = $this->manualDateTime['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="manualDateTime" placeholder="yyyy-mm-dd hh:mm" type="text" value="<?=myHtmlEntities($this->manualDateTime['value'])?>" />
                    <?php
                    if ($this->manualDateTime['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->manualDateTime['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->manualEmail['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="manualEmail" placeholder="Email" type="text" value="<?=myHtmlEntities($this->manualEmail['value'])?>" />
                    <?php
                    if ($this->manualEmail['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->manualEmail['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->manualFirstName['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="manualFirstName" placeholder="First Name" type="text" value="<?=myHtmlEntities($this->manualFirstName['value'])?>" />
                    <?php
                    if ($this->manualFirstName['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->manualFirstName['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->manualLastName['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="manualLastName" placeholder="Last Name" type="text" value="<?=myHtmlEntities($this->manualLastName['value'])?>" />
                    <?php
                    if ($this->manualLastName['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->manualLastName['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->manualAddress1['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="manualAddress1" placeholder="Address 1" type="text" value="<?=myHtmlEntities($this->manualAddress1['value'])?>" />
                    <?php
                    if ($this->manualAddress1['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->manualAddress1['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->manualAddress2['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="manualAddress2" placeholder="Address 2" type="text" value="<?=myHtmlEntities($this->manualAddress2['value'])?>" />
                    <?php
                    if ($this->manualAddress2['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->manualAddress2['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->manualCity['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="manualCity" placeholder="City" type="text" value="<?=myHtmlEntities($this->manualCity['value'])?>" />
                    <?php
                    if ($this->manualCity['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->manualCity['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->manualState['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="manualState" placeholder="State" type="text" value="<?=myHtmlEntities($this->manualState['value'])?>" />
                    <?php
                    if ($this->manualState['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->manualState['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->manualZip['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="manualZip" placeholder="Zip" type="text" value="<?=myHtmlEntities($this->manualZip['value'])?>" />
                    <?php
                    if ($this->manualZip['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->manualZip['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $class = $this->manualCountry['error'] != '' ? 'class="error-placeholder"' : '';
                    ?>
                    <input <?=$class?> name="manualCountry" placeholder="Country" type="text" value="<?=myHtmlEntities($this->manualCountry['value'])?>" />
                    <?php
                    if ($this->manualCountry['error'] != '')
                    {
                        ?>
                        <span class="error">←<?=myHtmlEntities($this->manualCountry['error'])?></span>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $checkedAdmin = $this->manualNotifyAdmin['value'] ? 'checked="checked"' : '';
                    $checkedCustomer = $this->manualNotifyCustomer['value'] ? 'checked="checked"' : '';
                    ?>
                    <input name="manualNotifyAdmin" <?=$checkedAdmin?> type="checkbox"> Notify Admin<br>
                    <input name="manualNotifyCustomer" <?=$checkedCustomer?> type="checkbox"> Notify Customer<br>
                </div>

                <div>
                    <input name="manualRecord" type="submit" value="Record" />
                    <input name="manualCancel" type="submit" value="Cancel" />
                </div>
            </form>
        </div>
        <?php
        $html = ob_get_clean();
        showFinal($html);
    }

    private function showThankYou($cart)
    {
        $custEmail = substr(gg('email'), 0, 254);
        $firstName = substr(gg('firstName'), 0, 254);
        $lastName = substr(gg('lastName'), 0, 254);
        $shippingLine1 = substr(gge('address1', ''), 0, 254);
        $shippingLine2 = substr(gge('address2', ''), 0, 254);
        $shippingCity = substr(gge('city', ''), 0, 254);
        $shippingState = substr(gge('state', ''), 0, 254);
        $shippingZip = substr(gge('zip', ''), 0, 254);
        $shippingCountry = substr(gge('country', ''), 0, 254);
        $mCustEmail = myHtmlEntities($custEmail);
        $mFirstName = myHtmlEntities($firstName);
        $mLastName = myHtmlEntities($lastName);
        $mShippingLine1 = myHtmlEntities($shippingLine1);
        $mShippingLine2 = $shippingLine2 != 'undefined' ? '<br>' . myHtmlEntities($shippingLine2) : '';
        $mShippingCity = myHtmlEntities($shippingCity);
        $mShippingState = myHtmlEntities($shippingState);
        $mShippingZip = myHtmlEntities($shippingZip);
        $mShippingCountry = $shippingCountry != 'US' ? '<br>' . myHtmlEntities($shippingCountry) : '';
        $siteName = $GLOBALS['pizza']['config']['siteName'];
        $cartHtml = $this->showCart($cart, false);

        $cartCss = <<<CARTCSS
<style>
.cart table {
    margin-bottom: 10px;
    /*width: 100%;*/
}

.cart table td {
    border-width: 1px;
    padding: 5px;
}

.cart table td.each {
    text-align: right;
}

.cart table td.options {
    text-align: center;
}

.cart table td.quantity {
    text-align: center;
}

.cart table td.subtotal {
    text-align: right;
}

.cart table td.subtotalAmount {
    border-style: solid none none none;
    padding-right: 10px;
    text-align: right;
}

.cart table td.subtotalLabel {
    border-style: solid none none none;
    padding-right: 30px;
    text-align: right;
}

.cart table td.taxAmount {
    border-style: none;
    padding-right: 10px;
    text-align: right;
}

.cart table td.taxLabel {
    border-style: none;
    padding-right: 30px;
    text-align: right;
}

.cart table td.totalAmount {
    border-style: solid none none none;
    font-size: larger;
    font-weight: bold;
    padding-right: 10px;
    text-align: right;
}

.cart table td.totalLabel {
    border-style: solid none none none;
    font-size: larger;
    padding-right: 30px;
    text-align: right;
}

.cart table td.update {
    border-style: solid none none none;
    text-align: center;
}

.cart table th {
    border-style: none none solid none;
    border-width: 1px;
    padding-left: 10px;
    padding-right: 10px;
    text-align: left;
    vertical-align: bottom;
    white-space: nowrap;
}

.cart table th.leftmost {
    /*width: 99%;*/
}
</style>
CARTCSS;

        if ($shippingLine1 == '') // Digital goods, no shipping address given.
        {
            $shippingHtml = <<<SHIPPING_HTML
            <p>
            For Customer:
            <br>$mFirstName $mLastName ($mCustEmail)
            </p>
            SHIPPING_HTML;
        }
        else
        {
            $shippingHtml = <<<SHIPPING_HTML
            <p>
            Ship To:
            <br>$mFirstName $mLastName ($mCustEmail)
            <br>$mShippingLine1
            $mShippingLine2
            <br>$mShippingCity, $mShippingState $mShippingZip
            $mShippingCountry
            </p>
            SHIPPING_HTML;
        }

        $webHtml = <<<WEB_HTML
<h1>Thank you for your order!</h1>
<p>
A confirmation has been emailed to:
<br>$mFirstName $mLastName ($mCustEmail).
</p>
<h2>Order Summary</h2>
$cartHtml
WEB_HTML;

        $emailCustomerHtml = <<<EMAIL_CUSTOMER_HTML
<html>
<head>$cartCss</head>
<body>
<h1>$siteName Order Confirmation</h1>
<p>Thank you for your order!</p>
<h2>Order Summary</h2>
$shippingHtml
$cartHtml
</body>
</html>
EMAIL_CUSTOMER_HTML;

        $emailAdminHtml = <<<EMAIL_ADMIN_HTML
<html>
<head>$cartCss</head>
<body>
<h1>$siteName Order Confirmation</h1>
<p>You received an order!</p>
<h2>Order Summary</h2>
$shippingHtml
$cartHtml
</body>
</html>
EMAIL_ADMIN_HTML;

        $subject = "$siteName Order Confirmation";
        $adminEmail = $GLOBALS['pizza']['config']['adminEmail'];
        if (!hg('skipNotifyCustomer'))
            $this->sendMail($adminEmail, $custEmail, $subject, $emailCustomerHtml);
        if (!hg('skipNotifyAdmin'))
            $this->sendMail($custEmail, $adminEmail, $subject, $emailAdminHtml);
        if (isset($GLOBALS['pizza']['config']['cart']['email'])
            && is_array($GLOBALS['pizza']['config']['cart']['email']))
        {
            foreach ($GLOBALS['pizza']['config']['cart']['email'] as $alsoTo)
            {
                if (!filter_var($alsoTo, FILTER_VALIDATE_EMAIL)) continue;
                $this->sendMail($custEmail, $alsoTo, $subject, $emailAdminHtml);
            }
        }
        sc($this->id);
        showFinal($webHtml);
    }

    private function validateInput($input)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        $cm = $GLOBALS['pizza']['cm'];
        $v = $GLOBALS['pizza']['v'];
        $v->setMethod('post');
        $v->reset();

        // If no cart or cart is empty, don't attempt any processing.
        $cart = $GLOBALS['pizza']['cart']->getCart();
        if (empty($cart))
        {
            if ($v->submitted('continueShopping'))
                relocateNow($urlRoot . previousPath());
            sleep(3);
            relocateNow($urlRoot . $pagePath);
        }

        // Hitting the RETURN key in IE6, for example, does not post the input button.
        // It's possible that quantity updates were submitted, so check for this.
        // This also presents an advantage in that if a quantity is changed and the
        // user hits another button such as check-out (without explicitly updating
        // first), then the cart can re-show with the new quantity; i.e. can only
        // proceed to check-out when quantities are stable.
        $cart = $GLOBALS['pizza']['cart']->getCart();
        $quantities = pge('cartQuantities', array());
        if (is_array($quantities) && !empty($quantities))
        {
            $cartUpdated = false;
            $giftWrap = pge('cartGiftWrap', array());
            $keys = array_keys($cart);
            foreach ($keys as $i => $key)
            {
                $oldGiftWrap = isset($cart[$key]['giftWrap']) ? true : false;
                $newGiftWrap = isset($giftWrap[$i]) ? true : false;
                $oldQty = $cart[$key]['item-quantity'];
                $newQty = $quantities[$i];
                if (($oldQty == $newQty) && ($oldGiftWrap == $newGiftWrap))
                    continue;
                $cartUpdated = true;
                if (!is_numeric($newQty)
                    || !(intval($newQty) == $newQty)
                    || ($newQty < 0))
                    continue;
                $itemPagePath = $cart[$key]['item-url'] ?? false;
                if ($itemPagePath !== false)
                {
                    $giftWrapAmount = $newGiftWrap ? $this->giftWrapAmount : 0;
                    $GLOBALS['pizza']['cart']->updateCart($itemPagePath, $cart[$key]['item-option'],
                        $newQty - $oldQty, $giftWrapAmount);
                }
                else
                {
                    $cart[$key]['item-quantity'] += $newQty - $oldQty;
                    $GLOBALS['pizza']['cart']->updateCartByKey($key, $cart[$key]);
                }
            }
            if ($cartUpdated)
                relocateNow($urlRoot . $pagePath);
        }

        if ($v->submitted('continueShopping'))
            relocateNow($urlRoot . previousPath());

        // Check if international shipping amount was provided and changed.
        if (hp('internationalShipping'))
        {
            // $currentIntlShipping = $this->internationalShipping['value'];
            $newIntlShipping = $v->checkMoney('internationalShipping');

            // An amount is optional, so clear error if no amount given.
            if ($newIntlShipping['value'] == '')
            {
                $newIntlShipping['error'] = '';
                $v->error = false;
            }
            // Interpret a zero amount as blank.
            if ($newIntlShipping['value'] == 0) $newIntlShipping['value'] = '';

            if ($v->error)
            {
                $this->internationalShipping = array('value' => '', 'error' => '');
                ss($this->id, $this);
                relocateNow($urlRoot . $pagePath);
            }
            if ($this->internationalShipping['value'] != $newIntlShipping['value'])
            {
                if ($newIntlShipping['value'] != '')
                    $this->internationalShipping['value'] = round($newIntlShipping['value'], 2);
                else
                    $this->internationalShipping['value'] = $newIntlShipping['value'];
                ss($this->id, $this);
                relocateNow($urlRoot . $pagePath);
            }
        }

        if ($v->submitted('applyPromo') || $v->submitted('checkout'))
        {
            require_once('Promos.php');
            $p = new Promos();
            $promos = $p->getPromos();
            if (!empty($promos['active']) && ($this->currentPromo === false))
                $this->promoCode = $v->checkLength('promoCode', 0, 200);
            if ($v->error)
            {
                ss($this->id, $this);
                sleep(3);
                relocateNow($urlRoot . $pagePath);
            }
            // Validate promo if given.
            if (($this->currentPromo === false) && ($this->promoCode['value'] != ''))
            {
                $this->promoCode['value'] = strtoupper($this->promoCode['value']);
                $this->currentPromo = $p->checkPromo($this->promoCode['value']);
                if ($this->currentPromo === false)
                {
                    $this->promoCode['error'] = 'invalid promo code';
                    ss($this->id, $this);
                    sleep(3);
                    relocateNow($urlRoot . $pagePath);
                }
                $this->promoCode['value'] = '';
                $this->promoCode['error'] = '';
                ss($this->id, $this);
            }
            if ($v->submitted('checkout'))
            {
                // Generate a transaction ID.
                ss('cart-transaction-id', mt_rand(1000000000, 9999999999));
                relocateNow($urlRoot . $pagePath . '?checkout');
            }
            relocateNow($urlRoot . $pagePath);
        }

        if ($v->submitted('manualCancel'))
        {
            $this->manualDateTime = array('value' => '', 'error' => '');
            $this->manualFirstName = array('value' => '', 'error' => '');
            $this->manualLastName = array('value' => '', 'error' => '');
            $this->manualEmail = array('value' => '', 'error' => '');
            $this->manualAddress1 = array('value' => '', 'error' => '');
            $this->manualAddress2 = array('value' => '', 'error' => '');
            $this->manualCity = array('value' => '', 'error' => '');
            $this->manualState = array('value' => '', 'error' => '');
            $this->manualZip = array('value' => '', 'error' => '');
            $this->manualCountry = array('value' => '', 'error' => '');
            $this->manualNotifyAdmin = array('value' => true, 'error' => '');
            $this->manualNotifyCustomer = array('value' => false, 'error' => '');
            ss($this->id, $this);
            relocateNow($urlRoot . $pagePath);
        }

        if ($v->submitted('manualRecord'))
        {
            $this->manualNotifyAdmin = $v->checkCheckbox('manualNotifyAdmin');
            $this->manualNotifyCustomer = $v->checkCheckbox('manualNotifyCustomer');
            $this->manualDateTime = $v->checkDateTime('Y-m-d H:i', 'manualDateTime');
            if (!$v->error)
            {
                $now = time();
                $then = strtotime($this->manualDateTime['value']);
                if ($now < $then)
                {
                    $this->manualDateTime['error'] = 'in the future';
                    $v->error = true;
                }
            }
            $this->manualEmail = $v->checkEmail('manualEmail', 1, 64);
            $this->manualFirstName = $v->checkLength('manualFirstName', 1, 64);
            $this->manualLastName = $v->checkLength('manualLastName', 1, 64);
            $this->manualAddress1 = $v->checkLength('manualAddress1', 2, 80, 'normalize');
            $this->manualAddress2 = $v->checkLength('manualAddress2', 0, 80, 'normalize');
            $this->manualCity = $v->checkLength('manualCity', 2, 40, 'normalize');
            $this->manualState = $v->checkLength('manualState', 2, 30, 'normalize');
            $this->manualZip = $v->checkLength('manualZip', 5, 10, 'normalize');
            $this->manualCountry = $v->checkLength('manualCountry', 2, 30, 'normalize');
            if ($v->error)
            {
                ss($this->id, $this);
                sleep(3);
                relocateNow($urlRoot . $pagePath . '?manual');
            }
            // Simulate a successful PayPal return, causing order to be recorded
            // and notification emails sent out.
            $url = $urlRoot . $pagePath . '?successPayPal';
            $url .= '&transactionId=' . urlencode(sge('cart-transaction-id', 'none'));
            $url .= '&dateTime=' . urlencode($this->manualDateTime['value'] . ':00');
            $url .= '&email=' . urlencode($this->manualEmail['value']);
            $url .= '&firstName=' . urlencode($this->manualFirstName['value']);
            $url .= '&lastName=' . urlencode($this->manualLastName['value']);
            $url .= '&address1=' . urlencode($this->manualAddress1['value']);
            $address2 = $this->manualAddress2['value'];
            if ($address2 == '') $address2 = 'undefined';
            $url .= '&address2=' . urlencode($address2);
            $url .= '&city=' . urlencode($this->manualCity['value']);
            $url .= '&state=' . urlencode($this->manualState['value']);
            $url .= '&zip=' . urlencode($this->manualZip['value']);
            $url .= '&country=' . urlencode($this->manualCountry['value']);
            if (!$this->manualNotifyAdmin['value'])
                $url .= '&skipNotifyAdmin';
            if (!$this->manualNotifyCustomer['value'])
                $url .= '&skipNotifyCustomer';
            relocateNow($url);
        }

        sleep(3);
    }
}
?>
