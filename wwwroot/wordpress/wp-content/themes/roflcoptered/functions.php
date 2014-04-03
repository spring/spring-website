<?php
/**
 * roflcoptered functions and definitions
 *
 * @package roflcoptered
 */

 /**
 * Add code to give first and last navigation items custom classes
 */
function wpb_first_and_last_menu_class($items) {
    $items[1]->classes[] = 'first';
    $items[count($items)]->classes[] = 'last';
    return $items;
}
add_filter('wp_nav_menu_objects', 'wpb_first_and_last_menu_class');
 
/**
 * Set the content width based on the theme's design and stylesheet.
 */
if ( ! isset( $content_width ) )
	$content_width = 640; /* pixels */

if ( ! function_exists( 'roflcoptered_setup' ) ) :
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which runs
 * before the init hook. The init hook is too late for some features, such as indicating
 * support post thumbnails.
 */
function roflcoptered_setup() {

	/**
	 * Make theme available for translation
	 * Translations can be filed in the /languages/ directory
	 * If you're building a theme based on roflcoptered, use a find and replace
	 * to change 'roflcoptered' to the name of your theme in all the template files
	 */
	load_theme_textdomain( 'roflcoptered', get_template_directory() . '/languages' );

	/**
	 * Add default posts and comments RSS feed links to head
	 */
	add_theme_support( 'automatic-feed-links' );

	/**
	 * Enable support for Post Thumbnails on posts and pages
	 *
	 * @link http://codex.wordpress.org/Function_Reference/add_theme_support#Post_Thumbnails
	 */
	//add_theme_support( 'post-thumbnails' );

	/**
	 * This theme uses wp_nav_menu() in one location.
	 */
	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'roflcoptered' ),
	) );

	/**
	 * Enable support for Post Formats
	 */
	add_theme_support( 'post-formats', array( 'aside', 'image', 'video', 'quote', 'link' ) );

	/**
	 * Setup the WordPress core custom background feature.
	 */
	add_theme_support( 'custom-background', apply_filters( 'roflcoptered_custom_background_args', array(
		'default-color' => 'ffffff',
		'default-image' => '',
	) ) );
}
endif; // roflcoptered_setup
add_action( 'after_setup_theme', 'roflcoptered_setup' );

/**
 * Register widgetized area and update sidebar with default widgets
 */
function roflcoptered_widgets_init() {
	register_sidebar(array(
    	'id' => 'footer-sidebar1',
    	'name' => __('Footer Sidebar 1', 'roflcoptered'),
    	'description' => __('The first footer sidebar.'),
    	'before_widget' => '<div id="%1$s" class="widget %2$s">',
    	'after_widget' => '</div>',
    	'before_title'  => '<h1 class="widget-title">',
		'after_title'   => '</h1>',
    ));
	register_sidebar(array(
    	'id' => 'footer-sidebar2',
    	'name' => __('Footer Sidebar 2', 'roflcoptered'),
    	'description' => __('The second footer sidebar.'),
    	'before_widget' => '<div id="%1$s" class="widget %2$s">',
    	'after_widget' => '</div>',
    	'before_title'  => '<h1 class="widget-title">',
		'after_title'   => '</h1>',
    ));
}
add_action( 'widgets_init', 'roflcoptered_widgets_init' );

/**
 * Enqueue scripts and styles
 */
function roflcoptered_scripts() {
	wp_enqueue_style( 'roflcoptered-style', get_stylesheet_uri() );

/*	wp_enqueue_script( 'roflcoptered-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '20120206', true ); */

	wp_enqueue_script( 'roflcoptered-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20130115', true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	if ( is_singular() && wp_attachment_is_image() ) {
		wp_enqueue_script( 'roflcoptered-keyboard-image-navigation', get_template_directory_uri() . '/js/keyboard-image-navigation.js', array( 'jquery' ), '20120202' );
	}
}
add_action( 'wp_enqueue_scripts', 'roflcoptered_scripts' );

/**
 * Implement the Custom Header feature.
 */
//require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Custom functions that act independently of the theme templates.
 */
require get_template_directory() . '/inc/extras.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
require get_template_directory() . '/inc/jetpack.php';

/* Add our custom logo to the wordpress login screen */
add_action("login_head", "my_login_head");
function my_login_head() {
	echo "
	<style>
		body.login #login h1 a {
			background: url('http://test.springrts.com/wordpress/wp-content/uploads/2014/04/SpringBanner234x60mkII.png') no-repeat scroll center top transparent;
			width: 234px !important;
			height: 60px;
		}
	</style>
	";
}

/* Change title for login screen */
add_filter('login_headertitle', create_function(false,"return 'Return to Spring RTS Engine';"));

/* Change url for login screen */
add_filter('login_headerurl', create_function(false,"return home_url();"));