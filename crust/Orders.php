<?php
// Copyright 2017 Fleming Computer.
// All Rights Reserved.
?>
<?php
class Orders
{
    private $mode;

    function __construct()
    {
    }

    function getHtml()
    {
        if (!isAdministrator()) error404();
        if (hg('order-date')) $this->mode = 'order-date';
        else if (hg('end-date')) $this->mode = 'end-date';
        else $this->mode = 'start-date';
        $orders = $this->getOrders();
        ob_start();
        echo $this->showJavascript();
        ?>
        <h1>Order History</h1>
        <?php
        if (!empty($orders['start-date']) || !empty($orders['end-date']))
        {
            ?>
            <p>
                Sort by:
                &nbsp;&nbsp;<a href="?start-date">Deliver Date</a>
                &nbsp;&nbsp;<a href="?end-date">Return Date</a>
                &nbsp;&nbsp;<a href="?order-date">Order Date</a>
            </p>
            <?php
        }
        else $this->mode = 'order-date';
        $orders = $orders[$this->mode];
        ?>
        <?php
        if (empty($orders))
        {
            ?>
            <p>There are no orders at this time.</p>
            <?php
            return ob_get_clean();
        }
        ?>
        <div id="orders">
        <?php
        $boundary = false;
        $now = date('Y-m-d');
        foreach ($orders as $date => $dailyOrders)
        {
            if (($date < $now) && !$boundary && ($this->mode == 'start-date'))
            {
                $boundary = true;
                ?>
                <h2>Past Deliveries</h2>
                <?php
            }
            $header = date('M j, Y', strtotime($date));
            if ($this->mode == 'order-date') $header = "Ordered on $header";
            else if ($this->mode == 'end-date') $header = "Return due on $header";
            else $header = "Deliver on $header";
            ?>
            <h2><?=$header?></h2>
            <?php
            foreach ($dailyOrders as $orderNumber => $o)
            {
                // Convert old 'giftWrap = true' to $5.
                foreach ($o['cart'] as $k => $v)
                {
                    if (($v['giftWrap'] ?? false) === true)
                        $o['cart'][$k]['giftWrap'] = 5;
                }
                $orderDate = date('M j, Y @ g:i a', strtotime($o['date'] . ' UTC'));
                $email = $o['email'];
                $mEmail = myHtmlEntities($email);
                $promo = isset($o['promo-code']) ? $o['promo-code'] : false;
                $taxRate = isset($o['tax-rate']) ? $o['tax-rate'] : 0;
                $shipping = isset($o['shipping']) ? $o['shipping'] : 0;
                $cartHtml = $this->showCart($o['cart'], $promo, $taxRate, $shipping);
                ?>
                <div style="margin-bottom: 20px;">
                    <p><?=$mEmail?>
                    <br><a id="link-<?=$date?>-<?=$orderNumber?>" href="#">show details</a>
                    </p>
                    <div id="order-<?=$date?>-<?=$orderNumber?>">
                        <p>Ordered: <?=$orderDate?></p>
                        <?=$cartHtml?>
                    </div>
                </div>
                <?php
            }
        }
        ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function getOrders()
    {
        $cm = $GLOBALS['pizza']['cm'];
        $children = $cm->getChildren('/settings/orders', 'order');
        $orders = array();
        foreach ($children as $o)
        {
            $order = unserialize(trim($o['body']));
            $date = date('Y-m-d', strtotime($order['date'] . ' UTC'));
            $orders[$date][$order['order-number']] = $order;
        }
        $orderClasses = array();
        $orderClasses['order-date'] = $this->sortByOrderDate($orders);
        $orderClasses['end-date'] = $this->sortByStartOrEndDate($orders, 'end-date');
        $orderClasses['start-date'] = $this->sortByStartOrEndDate($orders, 'start-date');
        $orders = array('new' => array(), 'old' => array());
        $now = date('Y-m-d');
        foreach ($orderClasses['start-date'] as $date => $dailyOrders)
        {
            if ($date < $now) $orders['old'][$date] = $dailyOrders;
            else $orders['new'][$date] = $dailyOrders;
        }
        $orderClasses['start-date'] = array_merge($orders['new'], $orders['old']);
        unset($orders);
        return $orderClasses;
    }

    private function sortByOrderDate($orders)
    {
        foreach ($orders as $date => $dailyOrders)
        {
            uasort($orders[$date],
                function ($a, $b)
                {
                    if ($a['date'] < $b['date']) return 1;
                    if ($a['date'] > $b['date']) return -1;
                    return 0;
                }
            );
        }
        krsort($orders);
        return $orders;
    }

    private function sortByStartOrEndDate($orders, $index)
    {
        $orders2 = array();
        foreach ($orders as $date => $dailyOrders)
        {
            foreach ($dailyOrders as $orderNumber => $order)
            {
                $partialOrder = $order;
                unset($partialOrder['cart']);
                foreach ($order['cart'] as $k => $r)
                {
                    if (!isset($r[$index])) continue;
                    $startDate = date('Y-m-d', strtotime($r[$index]));
                    if (!isset($orders2[$startDate][$orderNumber]))
                        $orders2[$startDate][$orderNumber] = $partialOrder;
                    $orders2[$startDate][$orderNumber]['cart'][$k] = $r;
                }
            }
        }
        krsort($orders2);
        return $orders2;
    }

    private function showCart($cart, $promo, $taxRate, $shipping)
    {
        $urlRoot = $GLOBALS['pizza']['urlRoot'];
        $pagePath = $GLOBALS['pizza']['pagePath'];
        // Get any gift wrap amount from first such row.
        $giftWrapAmount = false;
        foreach ($cart as $r)
        {
            if (isset($r['giftWrap']))
            {
                $giftWrapAmount = $r['giftWrap'];
                break;
            }
        }
        ob_start();
        ?>
        <div class="cart">
            <table border="0" cellpadding="0" cellspacing="0" summary="Shopping Cart">
            <tr>
                <th class="leftmost">Item</th>
                <?php
                if ($giftWrapAmount)
                {
                    ?>
                    <th style="text-align: center;">Gift<br>Wrap<br>$<?=$giftWrapAmount?> ea.</th>
                    <?php
                }
                ?>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: center;">Total</th>
                <th style="text-align: center;">&nbsp;</th>
            </tr>
            <?php
            $i = 0;
            foreach ($cart as $key => $cartRow)
            {
                $giftWrap = '';
                if ($giftWrapAmount && isset($cartRow['giftWrap']))
                {
                    $giftWrap = 'checked="checked"';
                }
                $quantity = $cartRow['item-quantity'];
                $itemPrice = $cartRow['item-price'];
                $itemName = isset($cartRow['item-nameHtml'])
                    ? $cartRow['item-nameHtml']
                    : myHtmlEntities($cartRow['item-name']);
                $itemDescription = isset($cartRow['item-descriptionHtml'])
                    ? $cartRow['item-descriptionHtml']
                    : myHtmlEntities($cartRow['item-description']);

                $subtotal = $itemPrice * $quantity;
                if ($giftWrap != '') $subtotal += $cartRow['giftWrap'] * $quantity;

                $mItemPrice = number_format($itemPrice, 2);
                $mSubtotal = number_format($subtotal, 2);
                ?>
                <tr>
                <td>
                    <div>
                        <?=$itemName?><br>
                        <?=$itemDescription?>
                    </div>
                </td>
                <?php
                if ($giftWrapAmount)
                {
                    ?>
                    <td style="text-align: center;"><input class="cartInput" disabled="disabled" name="cartGiftWrap[<?=$i?>]" type="checkbox" <?=$giftWrap?> /></td>
                    <?php
                }
                ?>
                <td class="quantity"><?=$quantity?></td>
                <td class="subtotal">$<?=$mSubtotal?></td>
                <td>&nbsp;</td>
                </tr>
                <?php
                $i++;
            }
            $totalColSpan = 2;
            if ($giftWrapAmount) $totalColSpan++;
            $subtotal = 0;
            array_walk($cart, function ($r) use (&$subtotal) {
                $subtotal += $r['item-quantity'] * ($r['item-price'] + ($r['giftWrap'] ?? 0));
            });
            $subtotal = number_format($subtotal, 2);
            $discount = 0;
            if ($promo !== false)
            {
                $promoCode = $promo[0];
                $promoValue = $promo[1];
                if (substr($promoValue, 0, 1) == '$')
                    $discount = substr($promoValue, 1);
                else if (substr($promoValue, -1) == '%')
                    $discount = $subtotal * (substr($promoValue, 0, -1) / 100.0);
                if ($discount < 0) $discount = 0;
                else if ($discount > $subtotal) $discount = $subtotal;
            }
            $discount = number_format($discount, 2);
            $tax = ($subtotal - $discount) * ($taxRate / 100);
            $tax = number_format($tax, 2);
            $shipping = number_format($shipping, 2);
            $total = $subtotal - $discount + $tax + $shipping;
            $total = number_format($total, 2);
            if ((($promo !== false)
                || ($tax > 0)
                || ($shipping > 0))
                && ($this->mode != 'start-date')
                && ($this->mode != 'end-date'))
            {
                ?>
                <tr>
                    <td class="subtotalLabel" colspan="<?=$totalColSpan?>">Subtotal</td>
                    <td class="subtotalAmount">$<?=$subtotal?></td>
                    <td class="update">&nbsp;</td>
                </tr>
                <?php
                if ($promo !== false)
                {
                    ?>
                    <tr>
                        <td class="taxLabel" colspan="<?=$totalColSpan?>">Promo Code <?=myHtmlEntities($promoCode)?></td>
                        <td class="taxAmount">-$<?=$discount?></td>
                        <td>&nbsp;</td>
                    </tr>
                    <?php
                }
                if ($tax > 0)
                {
                    ?>
                    <tr>
                        <td class="taxLabel" colspan="<?=$totalColSpan?>"><?=$taxRate?>% Tax</td>
                        <td class="taxAmount">$<?=$tax?></td>
                        <td class="options">&nbsp;</td>
                    </tr>
                    <?php
                }
                if ($shipping > 0)
                {
                    ?>
                    <tr>
                        <td class="taxLabel" colspan="<?=$totalColSpan?>">Shipping</td>
                        <td class="taxAmount">$<?=$shipping?></td>
                        <td class="options">&nbsp;</td>
                    </tr>
                    <?php
                }
                ?>
                <?php
            }
            if (($this->mode != 'start-date') && ($this->mode != 'end-date'))
            {
                ?>
                <tr>
                    <td class="promoCode">&nbsp;</td>
                    <td class="totalLabel" colspan="<?=$totalColSpan - 1?>">Total</td>
                    <td class="totalAmount">$<?=$total?></td>
                    <td class="update">&nbsp;</td>
                </tr>
                <?php
            }
            else
            {
                ?>
                <tr>
                    <td class="promoCode">&nbsp;</td>
                    <td class="totalLabel" colspan="<?=$totalColSpan - 1?>">&nbsp;</td>
                    <td class="totalAmount">&nbsp;</td>
                    <td class="update">&nbsp;</td>
                </tr>
                <?php
            }
            ?>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    private function showJavascript()
    {
        ob_start();
?>
<script>
var old;
$(function() {
    $("div[id^=order-]").hide();
    $("a[id^=link-]").click(function(e){
        e.preventDefault();
        var date = $(this).attr("id").substring(5, 15);
        var id = $(this).attr("id").substring(16);
        toggleDetails(date, id);
    });
});
function toggleDetails(date, orderNumber) {
    if (old == null) {
        // All closed, expanding date-orderNumber.
        $("#order-"+date+"-"+orderNumber).show();
        $("#link-"+date+"-"+orderNumber).text("hide details");
        old = date+"-"+orderNumber;
    }
    else if (old == date+"-"+orderNumber) {
        // date-orderNumber is expanded, close it and done.
        $("#order-"+date+"-"+orderNumber).hide();
        $("#link-"+date+"-"+orderNumber).text("show details");
        old = null;
    }
    else { // old != date+"-"+orderNumber
        // Opening a different date-orderNumber, but close old first.
        // Get position of link clicked from top of window.
        var y = parseInt($("#link-"+date+"-"+orderNumber).offset().top) - $(window).scrollTop();
        $("#order-"+old).hide();
        $("#link-"+old).text("show details");
        $("#order-"+date+"-"+orderNumber).show();
        $("#link-"+date+"-"+orderNumber).text("hide details");
        // Scroll to link's new position minus its old position.
        $(window).scrollTop(parseInt($("#link-"+date+"-"+orderNumber).offset().top) - y);
        old = date+"-"+orderNumber;
    }
}
</script>
<?php
        return ob_get_clean();
    }
}
?>
