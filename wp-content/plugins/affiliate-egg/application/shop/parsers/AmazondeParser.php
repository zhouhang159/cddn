<?php

namespace Keywordrush\AffiliateEgg;

defined('\ABSPATH') || exit;

/**
 * AmazondeParser class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2020 keywordrush.com
 */
require_once dirname(__FILE__) . '/AmazoncomParser.php';

class AmazondeParser extends AmazoncomParser {

    protected $canonical_domain = 'https://www.amazon.de';
    protected $currency = 'EUR';
    protected $user_agent = array('DuckDuckBot', 'facebot', 'ia_archiver');

    //protected $user_agent = array('wget');
}
