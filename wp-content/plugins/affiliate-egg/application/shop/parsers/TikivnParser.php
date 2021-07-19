<?php

namespace Keywordrush\AffiliateEgg;

defined('\ABSPATH') || exit;

/**
 * TikivnParser class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link http://www.keywordrush.com/
 * @copyright Copyright &copy; 2018 keywordrush.com
 */
class TikivnParser extends ShopParser {

    protected $charset = 'utf-8';
    protected $currency = 'VND';

    public function parseCatalog($max)
    {
        $path = array(
            ".//a[@class='product-item']/@href",
            ".//div[contains(@class, 'product-item')]//a/@href",
            ".//*[@class='search-a-product-item']",
            ".//p[@class='title']/a/@href",
        );

        $urls = $this->xpathArray($path);
        foreach ($urls as $i => $url)
        {
            $urls[$i] = strtok($url, '?');
        }

        return $urls;
    }

    public function parseTitle()
    {
        return $this->xpathScalar(".//h1");
    }

    public function parseDescription()
    {
        $pieces = $this->xpathArray(".//*[@class='top-feature-item bullet-wrap']/p");
        $description = join('; ', $pieces);
        if (!$description)
            $description = $this->xpathScalar(".//*[@itemprop='description']");
        return $description;
    }

    public function parsePrice()
    {
        return $this->xpathScalar(".//*[@itemprop='price']/@content");
    }

    public function parseOldPrice()
    {
        if (preg_match('/,"list_price":(\d+),"/', $this->dom->saveHTML(), $matches))
            return $matches[1];

        $paths = array(
            ".//div[@class='summary']//p[@class='original-price']",
        );

        if ($price = $this->xpathScalar($paths))
            return $price;
    }

    public function parseManufacturer()
    {
        return $this->xpathScalar(".//*[@itemprop='brand']/*[@itemprop='name']/@content");
    }

    public function parseImg()
    {
        $img = $this->xpathScalar(".//meta[@property='og:image']/@content");
        if (!$img)
            $img = $this->xpathScalar(".//*[@class='product-image']//a/@data-image");
        $img = str_replace(' ', '%20', $img);
        return $img;
    }

    public function parseImgLarge()
    {
        return $this->xpathScalar(".//*[@class='product-image']//a/@data-zoom-image");
    }

    public function parseExtra()
    {
        $extra = array();

        $extra['images'] = $this->xpathArray(".//*[@id='product-images']//a/@data-image");

        $names = $this->xpathArray(".//div[contains(@class, 'ProductDescription__Wrapper')]//table//tr/td[1]");
        $values = $this->xpathArray(".//div[contains(@class, 'ProductDescription__Wrapper')]//table//tr/td[2]");
        $feature = array();
        for ($i = 0; $i < count($names); $i++)
        {
            if (!empty($values[$i]))
            {
                $feature['name'] = \sanitize_text_field($names[$i]);
                $feature['value'] = \sanitize_text_field($values[$i]);
                $extra['features'][] = $feature;
            }
        }
        $extra['rating'] = TextHelper::ratingPrepare($this->xpathScalar(".//*[@itemprop='aggregateRating']//*[@itemprop='ratingValue']/@content"));

        return $extra;
    }

    public function isInStock()
    {
        if ($this->xpathScalar(".//p[@class='product-status discontinued']"))
            return false;

        if ($this->xpathScalar(".//*[@itemprop='availability']/@href") == 'https://schema.org/OutOfStock')
            return false;
        else
            return true;
    }

}
