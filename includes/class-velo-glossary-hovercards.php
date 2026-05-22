<?php

defined( 'ABSPATH' ) || exit;

class Velo_Glossary_Hovercards {
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'load_assets' ) );
	}

	public function load_assets() {
		if ( ! Velo_Glossary_Settings::should_enqueue_assets() ) {
			return;
		}

		wp_register_style( 'velo-glossary-tippy', plugins_url( '../css/tippy.css', __FILE__ ), array(), '6.3.7' );
		wp_register_style( 'velo-glossary-hovercards', plugins_url( '../css/glossary-hovercards.css', __FILE__ ), array( 'velo-glossary-tippy' ), '20260522' );
		wp_enqueue_style( 'velo-glossary-hovercards' );

		wp_register_script( 'velo-glossary-popper', plugins_url( '../js/popper.min.js', __FILE__ ), array(), '2.11.8', true );
		wp_register_script( 'velo-glossary-tippy', plugins_url( '../js/tippy.min.js', __FILE__ ), array( 'velo-glossary-popper' ), '6.3.7', true );
		wp_register_script( 'velo-glossary-hovercards', plugins_url( '../js/glossary-hovercards.js', __FILE__ ), array( 'velo-glossary-tippy', 'jquery', 'hoverintent-js' ), '20260522', true );
		wp_enqueue_script( 'velo-glossary-hovercards' );
	}
}
