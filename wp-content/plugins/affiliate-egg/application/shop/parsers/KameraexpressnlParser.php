<?php

namespace Keywordrush\AffiliateEgg;

defined('\ABSPATH') || exit;

/**
 * KameraexpressnlParser class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link http://www.keywordrush.com/
 * @copyright Copyright &copy; 2016 keywordrush.com
 */
class KameraexpressnlParser extends LdShopParser {

    protected $charset = 'utf-8';
    protected $currency = 'EUR';
    protected $_json;

    public function parseCatalog($max)
    {
        $urls = array_slice($this->xpathArray(".//ul[@class='productListing']//a[@class='productLink']/@href"), 0, $max);
        return $urls;
    }

    public function parseOldPrice()
    {
        if (preg_match('/"from_price":(.+?),/', $this->dom->saveHTML(), $matches))
            return $matches[1];
    }

}
