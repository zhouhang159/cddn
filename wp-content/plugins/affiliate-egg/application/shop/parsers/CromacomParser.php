<?php

namespace Keywordrush\AffiliateEgg;

defined('\ABSPATH') || exit;

/**
 * CromacomParser class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2020 keywordrush.com
 */
class CromacomParser extends LdShopParser {

    protected $charset = 'utf-8';
    protected $currency = 'INR';

    public function restPostGet($url, $fix_encoding = true)
    {
        //fix incorrect json
        $html = parent::restPostGet($url, $fix_encoding = true);
        $html = str_replace('"availability": inStock', '"availability": "inStock"', $html);
        $html = preg_replace('/"description": ".+?",/ims', '', $html);
        return $html;
    }

    public function parseCatalog($max)
    {
        return $this->xpathArray(array(".//a[@class='product__list--name']/@href", ".//a[@class='productMainLink']/@href", ".//a[@class='productMainLink']/@href"));
    }

    public function parseOldPrice()
    {
        $paths = array(
            ".//div[@class='product-details-price']//span[@class='pdpPriceMrp']",
        );

        return $this->xpathScalar($paths);
    }

    public function parseExtra()
    {
        $extra = parent::parseExtra();
        $extra['features'] = array();

        $names = $this->xpathArray(".//div[@class='product-classifications']//*[@class='attrib']");
        $values = $this->xpathArray(".//div[@class='product-classifications']//span[@class='attribvalue']");
        $feature = array();
        for ($i = 0; $i < count($names); $i++)
        {
            if (empty($values[$i]))
                continue;

            $feature['name'] = \sanitize_text_field($names[$i]);
            $feature['value'] = \sanitize_text_field($values[$i]);
            $extra['features'][] = $feature;
        }
        return $extra;
    }

    public function isInStock()
    {
        if ($this->xpathScalar(".//span[@id='outofstockmsg']") == 'This product is currently Out of Stock.')
            return false;
        else
            return true;
    }

}
