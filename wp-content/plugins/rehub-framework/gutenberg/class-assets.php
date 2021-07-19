<?php

namespace Rehub\Gutenberg;

defined('ABSPATH') OR exit;

final class Assets {
	private static $instance = null;

	/** @return Assets */
	public static function instance(){
		if(is_null(static::$instance)) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	protected $is_rest = false;

	/** @var \stdClass $assets */
	protected $assets = null;

	private function __construct(){
		add_action('enqueue_block_editor_assets', array( $this, 'editor_gutenberg' ));
		add_action('enqueue_block_assets', array( $this, 'common_assets' ));
		add_action('init', array( $this, 'init' ));

		$this->assets             = new \stdClass();
		$this->assets->path       = __DIR__.'/';
		$this->assets->path_css   = $this->assets->path.'assets/css/';
		$this->assets->path_js    = $this->assets->path.'assets/js/';
		$this->assets->path_image = $this->assets->path.'assets/images/';
		$this->assets->url        = plugins_url('/', __FILE__);
		$this->assets->url_css    = $this->assets->url.'assets/css/';
		$this->assets->url_js     = $this->assets->url.'assets/js/';
		$this->assets->url_image  = $this->assets->url.'assets/images/';

	}

	public function init(){
		$this->is_rest = defined('REST_REQUEST');

		wp_register_style('rhgutslider', $this->assets->url_css . 'slider.css', array(), '1.0');
		wp_register_style('rhgutreviewheading', $this->assets->url_css . 'review-heading.css', array(), '1.0');
		wp_register_style('rhgutcomparison', $this->assets->url_css . 'comparison-table.css', array(), '1.3');
		wp_register_style('rhgutswiper', $this->assets->url_css . 'swiper-bundle.min.css', array(), '1.1');
		wp_register_style( 'rhpb-video',  $this->assets->url_css . 'rhpb-video.css', array(), '1.1' );
		wp_register_style( 'rhpb-lightbox',  $this->assets->url_css . 'simpleLightbox.min.css', array(), '1.0' );
		wp_register_style( 'rhpb-howto',  $this->assets->url_css . 'howto.css', array(), '1.0' );
		wp_register_style( 'rhofferlistingfull',  $this->assets->url_css . 'offerlistingfull.css', array(), '1.1' );

		wp_register_script('rhgutslider', $this->assets->url_js . 'slider.js', array('jquery'), '1.1');
		wp_register_script('rhgutswiper', $this->assets->url_js . 'swiper-bundle.min.js', array(), true, '1.1');
		wp_register_script('rhgutequalizer', $this->assets->url_js . 'equalizer.js', array(), true, '1.1');	
		wp_register_script( 'rhpb-video',  $this->assets->url_js . 'rhpb-video.js', array(), true, '1.0' );
		wp_register_script( 'rhpb-lightbox',  $this->assets->url_js . 'simpleLightbox.min.js', array(), '1.0' );
		wp_register_script('lazysizes', $this->assets->url_js . 'lazysizes.js', array('jquery'), '5.2');
		wp_register_script( 'gctoggler',  $this->assets->url_js.'toggle.js', array(), '1.1', true );

		wp_register_script(
			'rehub-block-format',
			$this->assets->url_js . 'format.js',
			array('wp-rich-text', 'wp-element', 'wp-editor'),
			null,
			true
		);

		add_action( 'wp_ajax_check_youtube_url', array( $this, 'check_youtube_url') );

		//registering blocks with API 2 and without php rendering
		register_block_type_from_metadata( __DIR__ );

	}

	public function check_youtube_url(){
		$url = $_POST['url'];
		$max = wp_safe_remote_head($url);
		wp_send_json_success( wp_remote_retrieve_response_code($max) );
	}

	/**
	 * Enqueue Gutenberg block assets for backend editor.
	 */
	function editor_gutenberg(){
		static $loaded = false;
		if($loaded) {
			return;
		}
		$loaded = true;

		//add common editor js
		wp_enqueue_script(
			'rehub-blocks-editor',
			$this->assets->url_js.'editor.js',
			array('wp-api'),
			filemtime($this->assets->path_js.'editor.js'),
			true
		);
		$default_attributes = apply_filters('rehub/gutenberg/default_attributes', array());
		wp_localize_script('rehub-blocks-editor','RehubGutenberg', array(
			'blocks' => array(),
			'attributes' => $default_attributes,
			'pluginDirUrl' => trailingslashit(plugin_dir_url( __DIR__ )),
			'isRtl' => is_rtl(),
		));

		//add common editor css
		wp_enqueue_style(
			'rehub-blocks-editor',
			$this->assets->url_css.'editor.css',
			array(),
			'12.6'
		);
		wp_style_add_data( 'rehub-blocks-editor', 'rtl', true );

		//add formatting
		wp_enqueue_script( 'rehub-block-format' );

		//add editor block scripts
		wp_enqueue_script(
			'rehub-block-script',
			$this->assets->url_js . 'backend.js',
			array('wp-api'),
			null,
			true
		);
		wp_enqueue_style(
			'rehub-block-styles',
			$this->assets->url_css . 'backend.css',
			null,
			null
		);
		wp_style_add_data( 'rehub-block-styles', 'rtl', true );	
		wp_enqueue_script('lazysizes');

	}

	public function common_assets() {
		// conditional scripts
		if(!is_admin()){
			global $post;
			$wp_post = get_post( $post );
			if ( $wp_post ) {
				$content = $wp_post->post_content;
			}else{
				return false;
			}
			$blocks = parse_blocks( $content );
			$this->check_block_array($blocks); //check blocks to inject conditional scripts		
		}
	}

	public function check_block_array($blocks=array()){
		if ( empty( $blocks ) ) return;

		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === 'rehub/comparison-table' ) {
				wp_enqueue_style('rhgutcomparison');
				wp_enqueue_script('rhgutequalizer');
				if(isset( $block['attrs']['responsiveView']) && $block['attrs']['responsiveView'] == 'slide'){
					wp_enqueue_style('rhgutswiper');
					wp_enqueue_script('rhgutswiper');
				}
			}
			if ( $block['blockName'] === 'rehub/slider' ) {
				wp_enqueue_style('rhgutslider');
				wp_enqueue_script('rhgutslider');
			}
			if ( $block['blockName'] === 'rehub/review-heading' ) {
				wp_enqueue_style('rhgutreviewheading');
			}
			if ( $block['blockName'] === 'rehub/howto' ) {
				wp_enqueue_style('rhpb-howto');
			}
			if ( $block['blockName'] === 'rehub/offerlistingfull' ) {
				wp_enqueue_style('rhofferlistingfull');
				wp_enqueue_script('gctoggler');
			}
			if( $block['blockName'] === 'rehub/video' ){
				if( $block['attrs']['provider'] === "vimeo" ){
					wp_enqueue_script( 'vimeo-player', 'https://player.vimeo.com/api/player.js', array(), true, '1.0' );
				}
				wp_enqueue_style( 'rhpb-video' );
				wp_enqueue_script( 'rhpb-video');
				
				if( isset($block['attrs']['overlayLightbox']) && $block['attrs']['overlayLightbox'] ){
					wp_enqueue_style( 'rhpb-lightbox');
					wp_enqueue_script( 'rhpb-lightbox' );
				}
				$width = isset($block['attrs']['width']) ? $block['attrs']['width'] : '';
				$height = isset($block['attrs']['height']) ? $block['attrs']['height'] : '';
				$block_style = "#rhpb-video-" . $block['attrs']['blockId']. "{";
					if(!empty($width) && $width['desktop']['size'] > 0){
						$block_style .= "width: " . $width['desktop']['size'] . $width['desktop']['unit'] .";";
					}
					if(!empty($height) && $height['desktop']['size'] > 0){
						$block_style .= "height: " . $height['desktop']['size'] . $height['desktop']['unit'] .";";
					}
				$block_style .= "} @media (min-width: 1024px) and (max-width: 1140px) {";
				$block_style .= "#rhpb-video-" . $block['attrs']['blockId']. "{";
					if(!empty($width) && $width['landscape']['size'] > 0){
						$block_style .= "width: " . $width['landscape']['size'] . $width['landscape']['unit'] .";";
					}
					if(!empty($height) && $height['landscape']['size'] > 0){
						$block_style .= "height: " . $height['landscape']['size'] . $height['landscape']['unit'] .";";
					}
				$block_style .= "}";
				$block_style .= "} @media (min-width: 768px) and (max-width: 1023px) {";
				$block_style .= "#rhpb-video-" . $block['attrs']['blockId']. "{";
					if(!empty($width) && $width['tablet']['size'] > 0){
						$block_style .= "width: " . $width['tablet']['size'] . $width['tablet']['unit'] .";";
					}
					if(!empty($height) && $height['tablet']['size'] > 0){
						$block_style .= "height: " . $height['tablet']['size'] . $height['tablet']['unit'] .";";
					}
				$block_style .= "}";
				$block_style .= "} @media (max-width: 767px) {";
				$block_style .= "#rhpb-video-" . $block['attrs']['blockId']. "{";
					if(!empty($width) && $width['mobile']['size'] > 0){
						$block_style .= "width: " . $width['mobile']['size'] . $width['mobile']['unit'] .";";
					}
					if(!empty($height) && $height['mobile']['size'] > 0){
						$block_style .= "height: " . $height['mobile']['size'] . $height['mobile']['unit'] .";";
					}
				$block_style .= "} }";
				wp_add_inline_style( 'rhpb-video', $block_style );
			}
			//We check here reusable and inner blocks
			if ( $block['blockName'] === 'core/block' && ! empty( $block['attrs']['ref'] ) ) {
				$post_id = $block['attrs']['ref'];
				$content = get_post_field( 'post_content', $post_id );
				$blocks = parse_blocks( $content );
				$this->check_block_array($blocks);
			}
			if ( !empty($block['innerBlocks'])) {
				$blocks = $block['innerBlocks'];
				$this->check_block_array($blocks);
			}
		}
	}

}