<?php
// Copyright 2025 Fleming Computer.
// All Rights Reserved.
?>
<?php
class ShoppingCart
{
    private $carted;
    private $t;

    function __construct()
    {
        $this->t = $GLOBALS['pizza']['t'];
        $this->carted = $this->getCartedQuantities();
    }

    function finalizeOrder($transactionId, $dateTime, $email, $firstName, $lastName,
        $promo, $shipping)
    {
        $cart = sge('cart', array());
        // Record the order.
        $pagePath = '/settings/orders/';
        $order = array();
        $email = substr($email, 0, 254);
        $firstName = substr($firstName, 0, 254);
        $lastName = substr($lastName, 0, 254);
        $order['order-number'] = $transactionId;
        $order['date'] = $dateTime;
        $order['email'] = $email;
        $order['firstName'] = $firstName;
        $order['lastName'] = $lastName;
        $order['cart'] = $cart;
        if ($promo !== false)
        {
            $order['promo-code'] = $promo;
            require_once('Promos.php');
            $p = new Promos();
            $p->redeemPromo($promo[0]);
        }
        $order['tax-rate'] = $GLOBALS['pizza']['config']['cart']['taxRate'] ?? 0;
        $order['shipping'] = $shipping;
        $GLOBALS['pizza']['cm']->addPage($pagePath, $transactionId, serialize($order), 'order', 'override');
        // Remove sold quantities from inventory.
        $s = $GLOBALS['pizza']['settings'];
        foreach ($cart as $k => $v)
        {
            $itemUrl = $v['item-url'] ?? false;
            if ($itemUrl === false) continue;
            $quantity = $v['item-quantity'];
            $option = $v['item-option'];
            $quantities = $s->get($itemUrl, 'Store_quantities');
            $options = $s->get($itemUrl, 'Store_options');
            $i = 0;
            if ($option != '')
            {
                $i = array_search($option, $options);
                if ($i === false) continue; // shouldn't be possible
            }
            // The quantity might be empty for unlimited.
            if ($quantities[$i] === '') continue;
            $quantities[$i] = max($quantities[$i] - $quantity, 0);
            $s->set($itemUrl, 'Store_quantities', $quantities);
        }
        sc('cart');
    }

    function getCart()
    {
        return sge('cart', array());
    }

    function numRows()
    {
        return count(sge('cart', array()));
    }

    function quantitiesAvailable($itemPagePath)
    {
        $itemPage = $GLOBALS['pizza']['cm']->getPage($itemPagePath);
        $prices = $itemPage['settings']['Store_prices'] ?? false;
        $quantities = $itemPage['settings']['Store_quantities'] ?? false;
        $options = $itemPage['settings']['Store_options'] ?? false;
        if ($prices === false) return array();
        $availables = array();
        $carteds = array();
        for ($i = 0; $i < count($prices); $i++)
        {
            if (($prices[$i] === '') && ($options[$i] === '')) break;
            $carted = $this->carted[$itemPagePath . ':' . $options[$i]] ?? 0;
            $available = $quantities[$i] !== '' ? max($quantities[$i] - $carted, 0) : 99999;
            $availables[] = $available;
            $carteds[] = $carted;
        }
        return array($availables, $carteds);
    }

    function remove($key)
    {
        $cart = sge('cart', array());
        unset($cart[$key]);
        ss('cart', $cart);
    }

    function setCart($cart)
    {
        ss('cart', $cart);
    }

    function shippingCost()
    {
        $shipping = $GLOBALS['pizza']['config']['cart']['shipping'] ?? 0;
        if (!is_array($shipping)) return $shipping;
        // Must be given as rows in quantity => price format, with
        // first row having quantity of 1.
        if (empty($shipping)) return 0;
        if (array_key_first($shipping) !== 1) return 0;
        if (!empty(array_filter($shipping, function($v, $k) {
            if (!is_numeric($k) || (intval($k) != $k) || ($k < 1)) return true;
            if (!is_numeric($v) || (round($v, 2) != $v) || ($v < 0)) return true;
            return false;
        }, ARRAY_FILTER_USE_BOTH)))
            return 0;
        // Get total quantity of items in cart.
        $quantity = 0;
        foreach (sge('cart', array()) as $r)
            $quantity += $r['item-quantity'];
        $cost = 0;
        foreach ($shipping as $k => $v)
        {
            if ($quantity < $k) break;
            $cost = $v;
        }
        return $cost;
    }

    function updateCart($itemPagePath, $option, $qtyToAdd, $giftWrapAmount = 0)
    {
        $itemPage = $GLOBALS['pizza']['cm']->getPage($itemPagePath);
        if ($itemPage === false) return false;
        $settings = $itemPage['settings'];
        $prices = $settings['Store_prices'] ?? false;
        if ($prices === false) return false;
        $quantities = $settings['Store_quantities'] ?? false;
        $options = $settings['Store_options'] ?? false;
        // Quit if no option is given but options exist.
        if (($option == '') &&
            (count(array_filter($options, function($s) {return $s != '';})) > 0))
            return false;
        $i = 0;
        if ($option != '')
        {
            $i = array_search($option, $options);
            if ($i === false) return false;
        }
        // Find first non-empty price toward start of prices.
        for ($j = $i; $j >= 0; $j--)
        {
            $price = $prices[$j];
            if ($price !== '') break;
        }
        $maxQuantity = $quantities[$i];
        $item = $itemPage['pageName'];
        $cart = sge('cart', array());
        $key = $itemPagePath . ':' . $option . ':' . $price;
        if (($maxQuantity !== '') && ($qtyToAdd > 0))
        {
            // Are there really any available?
            $carted = $this->carted[$itemPagePath . ':' . $option] ?? 0;
            $available = $maxQuantity - $carted;
            if ($available <= 0) return false; // is less than zero possible ?
            if ($qtyToAdd > $available) $qtyToAdd = $available;
        }
        $itemHtml = myHtmlEntities($item);
        $description = '';
        $descriptionHtml = '';
        if ($option != '')
        {
            $description = $option;
            $mOption = myHtmlEntities($option);
            $descriptionHtml = '<span style="font-size: smaller;">' . $mOption . '</span>';
        }
        if (!isset($cart[$key]))
        {
            $cart[$key]['item-url'] = $itemPagePath;
            $cart[$key]['item-name'] = $item;
            $cart[$key]['item-price'] = $price;
            $cart[$key]['item-quantity'] = 0;
            $cart[$key]['item-option'] = $option;
            $cart[$key]['item-description'] = $description;
            $cart[$key]['item-nameHtml'] = $itemHtml;
            $cart[$key]['item-descriptionHtml'] = $descriptionHtml;
        }
        unset($cart[$key]['giftWrap']);
        if ($giftWrapAmount > 0) $cart[$key]['giftWrap'] = $giftWrapAmount;
        $cart[$key]['item-quantity'] += $qtyToAdd;
        if ($cart[$key]['item-quantity'] <= 0) unset($cart[$key]);
        ss('cart', $cart);
        return true;
    }

    function updateCartByKey($key, $cartRow)
    {
        $cart = sge('cart', array());
        $cart[$key] = $cartRow;
        if ($cart[$key]['item-quantity'] <= 0) unset($cart[$key]);
        ss('cart', $cart);
    }

    private function getCartedQuantities()
    {
        // Get quantities of all items claimed in all carts.
        $q = "SELECT data FROM sessions";
        $this->t->query($q);
        $n = $this->t->num_rows;
        $carted = array();
        for ($i = 0; $i < $n; $i++)
        {
            $r = $this->t->getNextRecord();
            $data = unserialize($r['data']);
            if (!isset($data['cart'])) continue;
            foreach ($data['cart'] as $k => $v)
            {
                // Change key from name:price:option to name:option.
                $parts = explode(':', $k);
                $k = $parts[count($parts) - 3] . ':' . $parts[count($parts) - 2];
                // Increment carted count for this item.
                $carted[$k] = ($carted[$k] ?? 0) + $v['item-quantity'];
            }
        }
        return $carted;
    }
}
?>
