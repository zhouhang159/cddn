<?php

namespace Rehub\Gutenberg\Blocks;

defined( 'ABSPATH' ) OR exit;

class ConsPros extends Basic {
	protected $name = 'conspros';

	protected $attributes = array(
		'prosTitle' => array(
			'type'    => 'string',
			'default' => 'Positive',
		),
		'positives' => array(
			'type'    => 'object',
			'default' => array(
				array( 'title' => 'Positive' )
			),
		),
		'consTitle' => array(
			'type'    => 'string',
			'default' => 'Negatives',
		),
		'negatives' => array(
			'type'    => 'object',
			'default' => array(
				array( 'title' => 'Negative' )
			),
		),
	);

	protected function list_content( $items ) {
		$html = '<ul>';

		foreach ( $items as $val ) {
			$html .= '<li>' . $val['title'] . '</li>';
		}

		$html .= '</ul>';

		return $html;
	}


	protected function render( $settings = array(), $inner_content = '' ) {
		$html           = '';
		$pros_content   = '';
		$cons_content   = '';
		$pros_title     = $settings['prosTitle'];
		$cons_title     = $settings['consTitle'];
		$positives      = $settings['positives'];
		$negatives      = $settings['negatives'];
		$column_classes = ( count( $positives ) === 0 || count( $negatives ) === 0 ) ? '' : 'wpsm-one-half';

		if ( ! empty( $positives ) ) {
			$pros_content .= '<div class="' . $column_classes . '">';
			$pros_content .= wpsm_pros_shortcode( array( 'title' => $pros_title ), $this->list_content( $positives ) );
			$pros_content .= '</div>';
		}

		if ( ! empty( $negatives ) ) {
			$cons_content .= '<div class="' . $column_classes . '">';
			$cons_content .= wpsm_cons_shortcode( array( 'title' => $cons_title ), $this->list_content( $negatives ) );
			$cons_content .= '</div>';
		}

		$html .= '<div class="rate_bar_wrap">' . $pros_content . $cons_content . '</div>';
		echo $html;
	}
}