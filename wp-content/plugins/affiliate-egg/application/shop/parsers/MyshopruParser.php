<?php

namespace Keywordrush\AffiliateEgg;

defined('\ABSPATH') || exit;

/**
 * MyshopruParser class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link http://www.keywordrush.com/
 * @copyright Copyright &copy; 2014 keywordrush.com
 */
class MyshopruParser extends MicrodataShopParser {

    protected $charset = 'windows-1251';

    public function parseCatalog($max)
    {
        $urls = array();
        $urls = array_slice($this->xpathArray(".//*[@data-o='listgeneral']//td[1]/div/a/@href"), 0, $max);
        if (!$urls)
            $urls = array_slice($this->xpathArray(".//td[contains(@class,'w730')]//table//td[2]/a[1]/@href"), 0, $max);
        if (!$urls)
            $urls = array_slice($this->xpathArray(".//td[contains(@class,'w740s')]//div[@class='tal']/div[1]/a[1]/@href"), 0, $max);

        foreach ($urls as $i => $url)
        {
            if (!preg_match('/^https?:\/\//', $url))
                $urls[$i] = 'http://my-shop.ru' . $url;
        }
        return $urls;
    }

    public function parseOldPrice()
    {
        return $this->xpathScalar(".//td[@class='bgcolor_2 list_border']//span[@style='text-decoration:line-through']");
    }

}
