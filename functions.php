<?php

// -------------------------------------------------------------------------------------------------------------------
// Check minimum requirements
// -------------------------------------------------------------------------------------------------------------------

if ( ! function_exists( 'pb_meets_minimum_requirements' ) && ! @include_once( WP_PLUGIN_DIR . '/pressbooks/compatibility.php' ) ) { // @codingStandardsIgnoreLine
	return add_action( 'admin_notices', function () {
		echo '<div id="message" class="error fade"><p>' . __( 'Cannot find Pressbooks install.', 'pressbooks-stats' ) . '</p></div>';
	} );
} elseif ( ! pb_meets_minimum_requirements() ) {
	return;
}

// -------------------------------------------------------------------------------------------------------------------
// Class autoloader
// -------------------------------------------------------------------------------------------------------------------

\HM\Autoloader\register_class_path( 'PressbooksCustomCssTheme', __DIR__ . '/inc' );

// -------------------------------------------------------------------------------------------------------------------
// Requires
// -------------------------------------------------------------------------------------------------------------------

require( __DIR__ . '/inc/namespace.php' );

// -------------------------------------------------------------------------------------------------------------------
// Hooks
// -------------------------------------------------------------------------------------------------------------------

add_action( 'init', '\PressbooksCustomCssTheme\register_post_types' );

if ( is_admin() ) {
	add_action( 'init', '\PressbooksCustomCssTheme\form_submit', 50 );
	add_action( 'admin_menu', '\PressbooksCustomCssTheme\add_menu' );
	add_action( 'admin_enqueue_scripts', '\PressbooksCustomCssTheme\enqueue_style' );
	add_action( 'load-post.php', '\PressbooksCustomCssTheme\redirect_css_editor' );
	add_action( 'wp_ajax_pb_load_css_from', '\PressbooksCustomCssTheme\load_css_from' );
}
