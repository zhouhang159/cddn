<?php

namespace Keywordrush\AffiliateEgg;

defined('\ABSPATH') || exit;

/**
 * LitresruParser class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link http://www.keywordrush.com/
 * @copyright Copyright &copy; 2014 keywordrush.com
 */
class LitresruParser extends ShopParser {

    protected $charset = 'utf-8';

    public function parseCatalog($max)
    {
        return $this->xpathArray(array(".//*[@class='art-item__name']/a/@href", ".//*[@class='booktitle']/div[1]/a/@href"));
    }

    public function parseTitle()
    {
        return $this->xpathScalar(".//h1[@itemprop='name']");
    }

    public function parseDescription()
    {
        return trim($this->xpathScalar(".//*[@itemprop='description']"));
    }

    public function parsePrice()
    {
        if ($p = $this->xpathScalar(".//button//*[@class='new-price']/text()"))
            return $p;

        return $this->xpathScalar("(.//*[@class='simple-price'])[2]");
    }

    public function parseOldPrice()
    {
        return $this->xpathScalar(".//button//*[@class='old-price']/text()");
    }

    public function parseManufacturer()
    {
        return trim($this->xpathScalar(".//*[@itemprop='author']"));
    }

    public function parseImg()
    {
        return $this->xpathScalar(".//meta[@property='og:image']/@content");
    }

    public function parseImgLarge()
    {
        return '';
    }

    public function parseExtra()
    {
        $extra = array();

        $extra['features'] = array();
        $names = $this->xpathArray(".//*[@class='biblio_book_info_detailed_left']//li/strong");
        $values = $this->xpathArray(".//*[@class='biblio_book_info_detailed_left']//li/text()");
        $feature = array();
        for ($i = 0; $i < count($names); $i++)
        {
            if (!empty($values[$i]))
            {
                $feature['name'] = sanitize_text_field(str_replace(":", "", $names[$i]));
                $feature['value'] = sanitize_text_field($values[$i]);
                $extra['features'][] = $feature;
            }
        }

        $extra['rating'] = TextHelper::ratingPrepare((int) $this->xpathScalar(".//*[@itemprop='ratingValue']/@content"));

        return $extra;
    }

    public function isInStock()
    {
        return true;
    }

}
