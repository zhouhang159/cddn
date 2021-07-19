<?php

namespace Rehub\Gutenberg;

use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) OR exit;

require_once( 'microdata-parser-master/src/Microdata.php' );
require_once( 'microdata-parser-master/src/MicrodataDOMDocument.php' );
require_once( 'microdata-parser-master/src/MicrodataDOMElement.php' );
require_once( 'microdata-parser-master/src/MicrodataParser.php' );
require_once( 'microdata-parser-master/src/XpathParser.php' );

require_once( 'vendor/autoload.php' );

use YusufKandemir\MicrodataParser\Microdata;
//use YusufKandemir\MicrodataParser\MicrodataDOMDocument;

class REST {
	private $rest_namespace = 'rehub/v2';


	private static $instance = null;

	/** @return Assets */
	public static function instance() {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'action_rest_api_init_trait' ) );
	}

	public function action_rest_api_init_trait() {
		//		if(!((is_user_logged_in() && is_admin()))) {
		//			return;
		//		}

		register_rest_route( $this->rest_namespace . '/posts',
			'/get',
			array(
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'permission_callback' => function ( WP_REST_Request $request ) {
						return current_user_can( 'editor' ) || current_user_can( 'administrator' );
					},
					'callback' => array( $this, 'rest_get_posts' ),
				)
			)
		);

		register_rest_route(
			$this->rest_namespace,
			"/offer-data/(?P<id>\d+)",
			array(
				'methods'  => WP_REST_Server::READABLE,
				'permission_callback' => function ( WP_REST_Request $request ) {
					return current_user_can( 'editor' ) || current_user_can( 'administrator' );
				},
				'callback' => array( $this, 'rest_offer_data_handler' ),
			)
		);

		register_rest_route(
			$this->rest_namespace,
			"/offer-listing/",
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'permission_callback' => function ( WP_REST_Request $request ) {
					return current_user_can( 'editor' ) || current_user_can( 'administrator' );
				},
				'callback' => array( $this, 'rest_offer_listing_handler' ),
			)
		);

		register_rest_route(
			$this->rest_namespace,
			"/parse-offer/",
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'permission_callback' => function ( WP_REST_Request $request ) {
					return current_user_can( 'editor' ) || current_user_can( 'administrator' );
				},
				'callback' => array( $this, 'rest_parse_offer_handler' ),
			)
		);

		register_rest_route(
			$this->rest_namespace,
			"/product/(?P<id>\d+)",
			array(
				'methods'  => WP_REST_Server::READABLE,
				'permission_callback' => function ( WP_REST_Request $request ) {
					return current_user_can( 'editor' ) || current_user_can( 'administrator' );
				},
				'callback' => array( $this, 'rest_product_handler' ),
			)
		);
	}

	public function rest_get_posts( WP_REST_Request $request ) {
		$params    = array_merge(
			array(
				's'         => '',
				'include'   => '',
				'exclude'   => '',
				'page'      => 1,
				'post_type' => 'post',
			), $request->get_params()
		);
		$isSelect2 = ( $request->get_param( 'typeQuery' ) === 'select2' );

		$args = array(
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'post_type'      => $params['post_type'],
			'paged'          => $params['page'],
		);

		if ( ! empty( $params['s'] ) ) {
			$args['s'] = $params['s'];
		}
		if ( ! empty( $params['include'] ) ) {
			$args['post__in'] = is_array( $params['include'] ) ? $params['include'] : array( $params['include'] );
		}
		if ( ! empty( $params['exclude'] ) ) {
			$args['post__not_in'] = is_array( $params['exclude'] ) ? $params['exclude'] : array( $params['exclude'] );
		}

		$response_array = array();
		$keys           = $isSelect2 ?
			[ 'label' => 'text', 'value' => 'id' ] :
			[ 'label' => 'label', 'value' => 'value' ];

		$posts = new \WP_Query( $args );
		if ( $posts->post_count > 0 ) {
			/* @var \WP_Post $gallery */
			foreach ( $posts->posts as $_post ) {
				$response_array[] = array(
					$keys['label'] => ! empty( $_post->post_title ) ? $_post->post_title : __( 'No Title', '' ),
					$keys['value'] => $_post->ID,
				);
			}
		}
		wp_reset_postdata();

		$return = array(
			'results'    => $response_array,
			'pagination' => array(
				'more' => $posts->max_num_pages >= ++ $params['page'],
			)
		);

		return rest_ensure_response( $return );
	}

	public function rest_offer_data_handler( WP_REST_Request $request ) {
		$id = $request->get_params()['id'];

		$product_url       = get_post_meta( $id, 'rehub_offer_product_url', true );
		$offer_post_url    = apply_filters( 'rehub_create_btn_url', $product_url );
		$offer_url         = apply_filters( 'rh_post_offer_url_filter', $offer_post_url );
		$offer_price       = get_post_meta( $id, 'rehub_offer_product_price', true );
		$offer_price_old   = get_post_meta( $id, 'rehub_offer_product_price_old', true );
		$offer_title       = get_post_meta( $id, 'rehub_offer_name', true );
		$offer_thumb       = get_post_meta( $id, 'rehub_offer_product_thumb', true );
		$offer_btn_text    = get_post_meta( $id, 'rehub_offer_btn_text', true );
		$offer_coupon      = get_post_meta( $id, 'rehub_offer_product_coupon', true );
		$offer_coupon_date = get_post_meta( $id, 'rehub_offer_coupon_date', true );
		$offer_coupon_mask = get_post_meta( $id, 'rehub_offer_coupon_mask', true );
		$offer_desc        = get_post_meta( $id, 'rehub_offer_product_desc', true );
		$disclaimer        = get_post_meta( $id, 'rehub_offer_disclaimer', true );
		$rating            = get_post_meta( $id, 'rehub_review_overall_score', true );
		$offer_mask_text   = '';
		//		$discount          = get_post_meta( $id, 'rehub_offer_discount', true );

		if ( $rating ) {
			$rating = $rating / 2;
		}

		if ( empty( $offer_title ) ) {
			$offer_title = get_the_title( $id );
		}

		if ( empty( $offer_thumb ) ) {
			$offer_thumb = get_the_post_thumbnail_url( $id );
		}

		if ( empty( $offer_btn_text ) ) {
			if ( ! empty( \REHub_Framework::get_option( 'rehub_btn_text' ) ) ) {
				$offer_btn_text = \REHub_Framework::get_option( 'rehub_btn_text' );
			} else {
				$offer_btn_text = 'Buy this item';
			}
		}

		if ( ! empty( \REHub_Framework::get_option( 'rehub_mask_text' ) ) ) {
			$offer_mask_text = \REHub_Framework::get_option( 'rehub_mask_text' );
		} else {
			$offer_mask_text = esc_html__( 'Reveal', 'rehub-framework' );
		}

		$data = array(
			'name'             => $offer_title,
			'description'      => $offer_desc,
			'disclaimer'       => $disclaimer,
			'old_price'        => $offer_price_old,
			'sale_price'       => $offer_price,
			'coupon_code'      => $offer_coupon,
			'expiration_date'  => $offer_coupon_date,
			'mask_coupon_code' => $offer_coupon_mask,
			'mask_coupon_text' => $offer_mask_text,
			'button_url'       => $offer_post_url,
			'button_text'      => $offer_btn_text,
			'thumbnail_url'    => $offer_thumb,
			'rating'           => $rating,
		);
		return rest_ensure_response( $data );
	}

	public function rest_product_handler( WP_REST_Request $request ) {
		$id   = $request->get_params()['id'];
		$data = array();

		if ( empty( $id ) ) {
			return new \WP_Error( 'empty_data', 'Pass empty data', array( 'status' => 404 ) );
		}

		$code_zone            = '';
		$price_label          = '';
		$mask_text            = '';
		$sync_items           = '';
		$video_thumbnails     = array();
		$gallery_images       = array();
		$is_coupon_expired    = false;
		$is_item_sync_enabled = false;
		$product              = wc_get_product( $id );
		$currency_symbol      = get_woocommerce_currency_symbol();
		$product_url          = $product->add_to_cart_url();
		$product_name         = $product->get_title();
		$product_desc         = $product->get_description();
		$image_id             = $product->get_image_id();
		$image_url            = wp_get_attachment_image_url( $image_id, 'full' );
		$gallery_ids          = $product->get_gallery_image_ids();
		$regular_price        = (float) $product->get_regular_price();
		$sale_price           = (float) $product->get_sale_price();
		$product_type         = $product->get_type();
		$product_on_sale      = $product->is_on_sale();
		$product_in_stock     = $product->is_in_stock();
		$add_to_cart_text     = $product->add_to_cart_text();
		$attributes           = $product->get_attributes();
		$product_videos       = get_post_meta( $id, 'rh_product_video', true );
		$coupon_expired_date  = get_post_meta( $id, 'rehub_woo_coupon_date', true );
		$is_expired           = get_post_meta( $id, 're_post_expired', true ) === '1';
		$coupon               = get_post_meta( $id, 'rehub_woo_coupon_code', true );
		$is_coupon_masked     = get_post_meta( $id, 'rehub_woo_coupon_mask', true ) === 'on' && ! empty( $coupon );
		$is_compare_enabled   = \REHub_Framework::get_option( 'compare_page' ) || \REHub_Framework::get_option( 'compare_multicats_textarea' );
		$loop_code_zone       = \REHub_Framework::get_option( 'woo_code_zone_loop' );
		$term_list            = strip_tags( get_the_term_list( $id, 'store', '', ', ', '' ) );

		if ( empty( $image_url ) ) {
			$image_url = rehub_woocommerce_placeholder_img_src( '' );
		}

		if ( ! empty( $product_desc ) ) {
			ob_start();
			kama_excerpt( 'maxchar=150&text=' . $product_desc . '' );
			$product_desc = ob_get_contents();
			ob_end_clean();
		}

		if ( $product_on_sale && $regular_price && $sale_price > 0 && $product_type !== 'variable' ) {
			$sale_proc   = 0 - ( 100 - ( $sale_price / $regular_price ) * 100 );
			$sale_proc   = round( $sale_proc );
			$price_label = $sale_proc . '%';
		}

		if ( $loop_code_zone ) {
			$code_zone = do_shortcode( $loop_code_zone );
		}

		if ( \REHub_Framework::get_option( 'rehub_mask_text' ) != '' ) {
			$mask_text = \REHub_Framework::get_option( 'rehub_mask_text' );
		} else {
			$mask_text = esc_html__( 'Reveal coupon', 'rehub-framework' );
		}

		if ( $coupon_expired_date ) {
			$timestamp1 = strtotime( $coupon_expired_date ) + 86399;
			$seconds    = $timestamp1 - (int) current_time( 'timestamp', 0 );
			$days       = floor( $seconds / 86400 );
			$seconds    %= 86400;

			if ( $days > 0 ) {
				$coupon_expired_date = $days . ' ' . esc_html__( 'days left', 'rehub-framework' );
				$is_coupon_expired   = false;
			} elseif ( $days == 0 ) {
				$coupon_expired_date = esc_html__( 'Last day', 'rehub-framework' );
				$is_coupon_expired   = false;
			} else {
				$coupon_expired_date = esc_html__( 'Expired', 'rehub-framework' );
				$is_coupon_expired   = true;
			}
		}

		if ( defined( '\ContentEgg\PLUGIN_PATH' ) ) {
			$itemsync = \ContentEgg\application\WooIntegrator::getSyncItem( $id );
			if ( ! empty( $itemsync ) ) {
				$is_item_sync_enabled = true;
				$sync_items           = do_shortcode( '[content-egg-block template=custom/all_offers_logo post_id="' . $id . '"]' );
			}
		}

		if ( ! empty( $attributes ) ) {
			ob_start();
			wc_display_product_attributes( $product );
			$attributes = ob_get_contents();
			ob_end_clean();
		}

		if ( ! empty( $gallery_ids ) ) {
			foreach ( $gallery_ids as $key => $value ) {
				$gallery_images[] = wp_get_attachment_url( $value );
			}
		}

		if ( ! empty( $product_videos ) ) {
			$product_videos = array_map( 'trim', explode( PHP_EOL, $product_videos ) );
			foreach ( $product_videos as $video ) {
				$video_thumbnails[] = parse_video_url( esc_url( $video ), "hqthumb" );
			}
		}

		$data['productUrl']        = $product_url;
		$data['productType']       = $product_type;
		$data['imageUrl']          = $image_url;
		$data['productName']       = $product_name;
		$data['description']       = $product_desc;
		$data['codeZone']          = $code_zone;
		$data['currencySymbol']    = $currency_symbol;
		$data['regularPrice']      = $regular_price;
		$data['salePrice']         = $sale_price;
		$data['priceLabel']        = $price_label;
		$data['coupon']            = $coupon;
		$data['addToCartText']     = $add_to_cart_text;
		$data['maskText']          = $mask_text;
		$data['couponExpiredDate'] = $coupon_expired_date;
		$data['brandList']         = $term_list;
		$data['productAttributes'] = $attributes;
		$data['galleryImages']     = $gallery_images;
		$data['videoThumbnails']   = $video_thumbnails;
		$data['syncItems']         = $sync_items;
		$data['isExpired']         = $is_expired;
		$data['couponMasked']      = $is_coupon_masked;
		$data['isCouponExpired']   = $is_coupon_expired;
		$data['isCompareEnabled']  = $is_compare_enabled;
		$data['isItemSyncEnabled'] = $is_item_sync_enabled;
		$data['productInStock']    = $product_in_stock;

		return json_encode( $data );
	}

	public function rest_offer_listing_handler( WP_REST_Request $request ) {
		$posts_id = $request['posts_id'];
		$data     = array();

		if ( empty( $posts_id ) || count( $posts_id ) === 0 ) {
			return new \WP_Error( 'empty_data', 'Pass empty data', array( 'status' => 404 ) );
		}


		foreach ( $posts_id as $index => $id ) {
			$button_text       = get_post_meta( (int) $id, 'rehub_offer_btn_text', true );
			$mask_text = '';
			$thumbnail_url     = get_the_post_thumbnail_url( (int) $id );
			$coupon_mask       = get_post_meta( (int) $id, 'rehub_offer_coupon_mask', true );
			$offer_coupon_date = get_post_meta( (int) $id, 'rehub_offer_coupon_date', true );
			$is_coupon_expired = false;
			$copy              = get_the_excerpt( (int) $id );

			if ( ! empty( $copy ) ) {
				ob_start();
				kama_excerpt( 'maxchar=120&text=' . $copy . '' );
				$copy = ob_get_contents();
				ob_end_clean();
			}

			if ( empty( $button_text ) ) {
				if ( ! empty( \REHub_Framework::get_option( 'rehub_btn_text' ) ) ) {
					$button_text = \REHub_Framework::get_option( 'rehub_btn_text' );
				} elseif ( $coupon_mask ) {
					$button_text = 'Reveal coupon';
				} else {
					$button_text = 'Buy this item';
				}
			}

			if ( ! empty( $button_text ) ) {
				$mask_text = $button_text;
			} elseif ( \REHub_Framework::get_option( 'rehub_mask_text' ) != '' ) {
				$mask_text = \REHub_Framework::get_option( 'rehub_mask_text' );
			} else {
				$mask_text = esc_html__( 'Reveal coupon', 'rehub-framework' );
			}

			if ( empty( $thumbnail_url ) ) {
				$thumbnail_url = plugin_dir_url( __FILE__ ) . 'assets/icons/noimage-placeholder.png';
			}

			if ( ! empty( $offer_coupon_date ) ) {
				$timestamp = strtotime( $offer_coupon_date ) + 86399;
				$seconds   = $timestamp - (int) current_time( 'timestamp', 0 );
				$days      = floor( $seconds / 86400 );

				if ( $days > 0 ) {
					$is_coupon_expired = false;
				} elseif ( $days == 0 ) {
					$is_coupon_expired = false;
				} else {
					$is_coupon_expired = true;
				}
			}

			
			$data[$index] = array(
				'score'          => get_post_meta( (int) $id, 'rehub_review_overall_score', true ),
				'thumbnail'      => array(
					'url' => $thumbnail_url,
				),
				'title'          => get_the_title( (int) $id ),
				'copy'           => $copy,
				'badge'          => re_badge_create( 'labelsmall', (int) $id ),
				'currentPrice'   => get_post_meta( (int) $id, 'rehub_offer_product_price', true ),
				'oldPrice'       => get_post_meta( (int) $id, 'rehub_offer_product_price_old', true ),
				'button'         => array(
					'text' => $button_text,
					'url'  => get_post_meta( (int) $id, 'rehub_offer_product_url', true ),
				),
				'coupon'         => get_post_meta( (int) $id, 'rehub_offer_product_coupon', true ),
				'maskCoupon'     => $coupon_mask,
				'expirationDate' => $offer_coupon_date,
				'maskCouponText' => $mask_text,
				'offerExpired'   => $is_coupon_expired,
				'readMore'       => 'Read full review',
				'readMoreUrl'    => '',
				'disclaimer'     => get_post_meta( (int) $id, 'rehub_offer_disclaimer', true ),
				'type'=> $request['type']
			);
			if($request['type'] === 'product'){
				$product = wc_get_product( $id );
				$data[$index]['currentPrice'] = $product->get_price();
				$data[$index]['oldPrice'] = $product->get_regular_price();
				$data[$index]['addToCartText'] = $product->add_to_cart_text();
				$data[$index]['priceHtml'] = $product->get_price_html();
			}
		}

		return json_encode( $data );
	}

	public function rest_parse_offer_handler( WP_REST_Request $request ) {
		$url = $request->get_params()['url'];

		if ( empty( $url ) || filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
			return new \WP_Error( 'invalid_url', 'Not valid url', array( 'status' => 404 ) );
		}
		
		$hostName = $this->get_host_name( $url );
		
		$xpathArray = array();
		
		if( $hostName == 'amazon' ){
			$xpathArray = array(
				'name' => '//h1[@id="title"]',
				'image'=> '//img[@id="landingImage"]',
				'description' => '//div[@id="productDescription"]/p',
				'priceCurrency' => '//div[@id="cerberus-data-metrics"]',
				'price' => '//span[@id="priceblock_ourprice"]%DELIMITER%//span[@id="priceblock_dealprice"]%DELIMITER%//div[@id="cerberus-data-metrics"]',
			);
		}
		
		if( !empty( $xpathArray ) ){ //we check if we have xpath ready
			return Microdata::fromXpathFile( $url )->toJSON( $xpathArray );
		}else{
			$args = array( 
				'timeout' => 30,
				'httpversion' => '1.0',
				'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.75 Safari/537.36'
			);
			$request = wp_safe_remote_get($url, $args);
			$html = wp_remote_retrieve_body( $request );
			$reader = new \Brick\StructuredData\Reader\ReaderChain(
				new \Brick\StructuredData\Reader\MicrodataReader(),
				new \Brick\StructuredData\Reader\JsonLdReader()
			);
			$htmlReader = new \Brick\StructuredData\HTMLReader($reader);
			$items = $htmlReader->read($html, $url);
			$itemarray = array();
			foreach ($items as $index => $item) {
				$itemarray['items'][$index]['type'] = $item->getTypes();
				foreach ($item->getProperties() as $name => $values) {
					$name = str_replace(array('http://schema.org/', 'https://schema.org/'), '', $name);
					foreach ($values as $valueindex=>$value) {
						if ($value instanceof \Brick\StructuredData\Item) {
							$itemarray['items'][$index]['properties'][$name][$valueindex]['type'] = $value->getTypes();
							foreach ($value->getProperties() as $innername => $innervalues) {
								$innername = str_replace(array('http://schema.org/', 'https://schema.org/'), '', $innername);
								$itemarray['items'][$index]['properties'][$name][$valueindex]['properties'][$innername] = $innervalues;
							}
		
						}else{
							$itemarray['items'][$index]['properties'][$name][$valueindex] = $value;
						}
					}
				}
			}
			return json_encode($itemarray);
		}
	}
	
    public function get_host_name( $url ) {
		$domain = strtolower(str_ireplace('www.', '', parse_url($url, PHP_URL_HOST)));
		
		// remove subdomain
        if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
            $domain = $regs['domain'];
        }
		
		$hostData = explode('.', $domain);
		
		return $hostData[0];
    }
}
