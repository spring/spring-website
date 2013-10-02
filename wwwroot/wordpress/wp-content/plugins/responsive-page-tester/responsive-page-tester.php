<?php
/*
Plugin Name: Responsive Page Tester
Plugin URI: http://wordpress.org/extend/plugins/responsive-page-tester
Description: Gives editors the ability to preview their content in a responsive testbed.
Author: Linchpin (Aaron Ware & Jonathan Desrosiers).
Version: 2.0
Author URI: http://linchpinagency.com/?utm_source=responsive-page-tester&utm_medium=plugin-admin-page&utm_campaign=product
*/

if ( ! class_exists( 'Responsive_Page_Tester' ) ) {

	/**
	 * Responsive_Page_Tester class.
	 */
	class Responsive_Page_Tester {

		/**
		 * Array of arrays containing screen sizes with dimensions and a description
		 *
		 * @var mixed
		 * @access private
		 */
		private $screen_sizes = array(
			'240x320' =>  array( 'width' => 240,  'height' => 320,  'description' => '(small phone)' ),
			'320x480' =>  array( 'width' => 320,  'height' => 480,  'description' => '(iPhone/android)' ),
			'480x640' =>  array( 'width' => 480,  'height' => 640,  'description' => '(small tablet)' ),
			'768x1024' => array( 'width' => 768,  'height' => 1024, 'description' => '(tablet - Portrait)' ),
			'1024x768' => array( 'width' => 1024, 'height' => 768,  'description' => '(tablet - Landscape)' ),
		);

		/**
		 * __construct function.
		 *
		 * @access public
		 * @return void
		 */
		function __construct() {
			add_action( 'init', array( $this, 'init' ) );
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 1010 );
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_styles' ) );

			add_filter( 'show_admin_bar', array( $this, 'show_admin_bar' ) );
			add_filter( 'wp_head', array( $this, 'wp_head' ), 99 );
		}

		/**
		 * Returns an array of screen sizes passed through the rpt_screen_sizes filter
		 *
		 * @access private
		 * @return void
		 */
		private function get_screen_sizes() {
			return apply_filters( 'rpt_screen_sizes', $this->screen_sizes );
		}

	    /**
	     * wp_head function.
	     *
	     * @access public
	     * @return void
	     */
	    function wp_head() { // css override for the frontend in the iframes
	    	if ( ! current_user_can( 'edit_posts' ) || ! isset( $_GET['rpt_responsive'] ) )
	    		return; ?>

			<style type="text/css" media="screen">
				html { margin-top: 0px !important; }
				* html body { margin-top: 0px !important; }
			</style><?php
	    }

		/**
		 * init function.
		 *
		 * @access public
		 * @return void
		 */
		function init() {
			//Remove the admin bar within the iframes
		    if ( ! current_user_can( 'edit_posts' ) || ! isset( $_GET['rpt_responsive'] ) )
		    	return;

	    	remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 ); // for the front end
		}

		/**
		 * admin_bar_menu function.
		 *
		 * @access public
		 * @return void
		 */
		function admin_bar_menu() {
			if ( ! current_user_can( 'edit_posts' ) || isset( $_GET['rpt_responsive'] ) )
				return;

			$this->add_root_menu( 'Responsive', 'linchpin-rpt' );

			$this->add_sub_menu( 'all', 'All Sizes', 'linchpin-rpt', 'all' );

			foreach( $this->get_screen_sizes() as $key => $size )
				$this->add_sub_menu( $key, $size['width'] . 'x' . $size['height'] . ' ' . $size['description'], 'linchpin-rpt', $key );
		}

		/**
		 * add_root_menu function.
		 * Adds new global menu, if $href is false menu is added but registred as submenuable
		 *
		 * @access public
		 * @param mixed $name
		 * @param mixed $id
		 * @param bool $href (default: false)
		 * @return void
		 */
		function add_root_menu( $name, $id, $href = false ) {
			if ( is_admin() || ! is_admin_bar_showing() || isset( $_GET['rpt_responsive'] ) )
			    return;

			global $wp_admin_bar;

			$wp_admin_bar->add_node( array(
			    'id'   	=> $id,
			    'title' => $name,
			    'parent' => 'top-secondary',
			    'href' 	=> '#',
			    'meta' 	=> array(
			    	'onclick' => 'linchpin.tester.toggleResponsiveDisplay(); return false;',
			    	'class'=> 'responsive-page-tester',
			    )
			) );
		}

		/**
		 * add_sub_menu function.
		 * Add in our submenu
		 *
		 * @param $name (String)
		 * @param $root_menu (String)
		 * @param $size (String)
		 *
		 * @return void
		 **/
		function add_sub_menu( $id, $name, $root_menu, $size ) {
			global $wp_admin_bar;
			if ( ! is_super_admin() || ! is_admin_bar_showing() )
				return;

			$wp_admin_bar->add_menu( array(
				'id' => $id,
				'parent' => $root_menu,
				'title' => $name,
				'href' => '#',
			    'meta' 	=> array(
			    	'onclick' => 'linchpin.tester.toggleResponsiveDisplay("' . $size . '"); return false;',
			    	'class'=> 'responsive-page-tester-sub-menu',
			    )
			) );
		}

		/**
		 * Enqueue scripts, and provide some info to our JS via wp_localize_script
		 *
		 * @access public
		 * @return void
		 */
		function wp_enqueue_scripts() {
			if ( ! current_user_can( 'edit_posts' ) )
				return;

        	wp_enqueue_script( 'responsive-page-tester', plugins_url( '/responsive-page-tester.js', __FILE__ ), array( 'jquery-ui-button' ) );

			$vars = array(
				'current_page' => get_permalink( get_the_ID() ),
				'includes' => includes_url(),
				'responsive' => ( isset( $_GET['rpt_responsive'] ) ) ? 1 : 0,
				'sizes' => $this->get_screen_sizes(),
				'logo_url' => plugins_url( 'images/linchpin.png', __FILE__ ),
			);

			wp_localize_script( 'responsive-page-tester', 'responsive_page_tester', $vars );
	    }

	    /**
	     * wp_enqueue_styles function.
	     *
	     * @access public
	     * @return void
	     */
	    function wp_enqueue_styles() {
			if ( ! current_user_can( 'edit_posts' ) || isset( $_GET['rpt_responsive'] ) )
				return;

        	wp_enqueue_style( 'responsive-page-tester', plugins_url( '/responsive-page-tester.css', __FILE__ )  );
	    }

	    /**
	     * show_admin_bar function.
	     *
	     * @access public
	     * @param mixed $content
	     * @return void
	     */
	    function show_admin_bar( $content ) {
			if ( isset( $_GET['rpt_responsive'] ) )
				return;

			return $content;
	    }
	}
}

$responsive_page_tester = new Responsive_Page_Tester();