<?php

namespace Keywordrush\AffiliateEgg;

defined('\ABSPATH') || exit;

/**
 * SouqcomParser class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link http://www.keywordrush.com/
 * @copyright Copyright &copy; 2018 keywordrush.com
 */
class SouqcomParser extends LdShopParser {

    protected $charset = 'utf-8';
    protected $currency = 'AED';

    public function parseCatalog($max)
    {
        $urls = $this->xpathArray(".//a[contains(@class, 'itemLink') and string-length(text()) > 1]/@href");
        if (!$urls)
            $urls = $this->xpathArray(".//h6[@class='title']/a/@href");
        if (!$urls)
            $urls = $this->xpathArray(".//*[@class='itemTitle']/a/@href");

        $urls = array_unique($urls);
        return $urls;
    }

    public function parseOldPrice()
    {
        return $this->xpathScalar(".//*[@class='price-messaging']//*[@class='was']");
    }

    public function parseDescription()
    {
        return '';
    }

    public function parseImg()
    {
        $img = str_replace('/item_XL_', '/item_XXL_', parent::parseImg());
        $img = str_replace('/item_L_', '/item_XXL_', $img);
        return $img;
    }

    public function parseExtra()
    {
        $extra = parent::parseExtra();

        $names = $this->xpathArray(".//*[@id='specs-full']//dt");
        $values = $this->xpathArray(".//*[@id='specs-full']//dd");
        $feature = array();
        for ($i = 0; $i < count($names); $i++)
        {
            if (!empty($values[$i]))
            {
                $value = \sanitize_text_field($values[$i]);
                if (!$value)
                    continue;
                $feature['name'] = \sanitize_text_field($names[$i]);
                $feature['value'] = $value;
                $extra['features'][] = $feature;
            }
        }

        return $extra;
    }

    public function getCurrency()
    {
        if ($currency = parent::getCurrency())
            return $currency;

        if (strstr($this->getUrl(), '//egypt.souq.com/'))
            return 'EGP';
        $currency = \sanitize_text_field($this->xpathScalar(".//*[@class='currency-text sk-clr1']"));
        if ($currency && (strlen($currency) == 3 || strlen($currency) == 4))
            return $currency;
        else
            return $this->currency;
    }

    public function isInStock()
    {
        if ($this->xpathScalar(".//h5[@class='notice']"))
            return false;
        else
            return parent::isInStock();
    }

}
