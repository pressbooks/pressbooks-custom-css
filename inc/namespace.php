<?php
/**
 * @author  Pressbooks <code@pressbooks.com>
 * @license GPLv2 (or any later version)
 */

namespace PressbooksCustomCssTheme;

use Pressbooks\Book;
use Pressbooks\Container;
use Pressbooks\CustomCss;

/**
 * Add Edit CSS menu.
 */
function add_menu() {

	if ( Book::isBook() && CustomCss::isCustomCss() ) {
		add_theme_page( __( 'Edit CSS', 'pressbooks' ), __( 'Edit CSS', 'pressbooks' ), 'edit_others_posts', 'pb_custom_css', __NAMESPACE__ . '\display_custom_css' );
	}
}

/**
 * Force the user to edit custom-css posts in our custom editor.
 */
function redirect_css_editor() {

	if ( ! empty( $_REQUEST['post'] ) ) {
		$post_id = absint( $_REQUEST['post'] );
	} else {
		return; // Do nothing
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return; // Do nothing
	}

	if ( 'custom-css' !== $post->post_type ) {
		return; // Do nothing
	}

	$redirect_url = get_admin_url( get_current_blog_id(), '/themes.php?page=pb_custom_css&slug=' . $post->post_name );
	\Pressbooks\Redirect\location( $redirect_url );
}


/**
 * Supported formats
 *
 * Array key is slug, array val is text (passed to _e() where necessary.)
 * 'web' is considered the default key because web isn't going anywhere.
 * All keys must match an *existing* WP post where post_name = __key__ and post_type = 'custom-css'
 * If the key is not 'web' then it must map to: themes-book/__SOME_THEME__/export/__key__/style.css
 *
 * @return array
 */
function get_supported() {
	return [
		'web' => 'Web',
		'epub' => 'Ebook',
		'prince' => 'PDF',
	];
}


/**
 * Displays the Edit CSS Page
 */
function display_custom_css() {

	$slug = isset( $_GET['slug'] ) ? $_GET['slug'] : get_transient( 'pb-last-custom-css-slug' );
	if ( ! $slug ) {
		$slug = 'web';
	}

	$supported = array_keys( get_supported() );
	if ( ! in_array( $slug, $supported, true ) ) {
		wp_die( "Unknown slug: $slug" );
	}

	$css_post = get_post( $slug );
	if ( false === $css_post ) {
		wp_die( sprintf( __( 'Unexpected Error: There was a problem trying to query slug: %s - Please contact technical support.', 'pressbooks' ), $slug ) );
	}

	$vars = [
		'slugs_dropdown' => render_dropdown_for_slugs( $slug ),
		'css_copy_dropdown' => render_dropdown_for_css_copy( $slug ),
		'revisions_table' => render_revisions_table( $slug, $css_post->ID ),
		'post_id' => absint( $css_post->ID ),
		'my_custom_css' => $css_post->post_content,
	];
	load_custom_css_template( $vars );

	set_transient( 'pb-last-custom-css-slug', $slug );
}

/**
 * Returns the latest "custom-css" post
 *
 * @see \Pressbooks\Activation::wpmuActivate
 * @see \Pressbooks\Metadata::upgradeCustomCss
 *
 * @param string $slug post_name
 *
 * @return \WP_Post|bool
 */
function get_post( $slug ) {

	// Supported post names (ie. slugs)
	$supported = array_keys( get_supported() );
	if ( ! in_array( $slug, $supported, true ) ) {
		return false;
	}

	$args = [
		'name' => $slug,
		'post_type' => 'custom-css',
		'posts_per_page' => 1,
		'post_status' => 'publish',
		'orderby' => 'modified',
		'no_found_rows' => true,
		'cache_results' => true,
	];

	$q = new \WP_Query();
	$results = $q->query( $args );

	if ( empty( $results ) ) {
		return false;
	}

	return $results[0];
}


/**
 * Simple templating function.
 *
 * @param array $vars
 */
function load_custom_css_template( $vars ) {
	extract( $vars ); // @codingStandardsIgnoreLine
	require( PB_PLUGIN_DIR . 'templates/admin/custom-css.php' );
}


/**
 * Render table for revisions.
 *
 * @param string $slug
 * @param int $post_id
 *
 * @return string
 */
function render_revisions_table( $slug, $post_id ) {

	$args = [
		'posts_per_page' => 10,
		'post_type' => 'revision',
		'post_status' => 'inherit',
		'post_parent' => $post_id,
		'orderby' => 'date',
		'order' => 'DESC',
	];

	$q = new \WP_Query();
	$results = $q->query( $args );

	$html = '<table class="widefat fixed" cellspacing="0">';
	$html .= '<thead><th>' . __( 'Last 10 CSS Revisions', 'pressbooks' ) . " <em>(" . get_supported()[ $slug ] . ")</em> </th></thead><tbody>";
	foreach ( $results as $post ) {
		$html .= '<tr><td>' . wp_post_revision_title( $post ) . ' ';
		$html .= __( 'by', 'pressbooks' ) . ' ' . get_userdata( $post->post_author )->user_login . '</td></tr>';
	}
	$html .= '</tbody></table>';

	return $html;
}


/**
 * Render dropdown and JavaScript for slugs.
 *
 * @param string $slug
 *
 * @return string
 */
function render_dropdown_for_slugs( $slug ) {

	$select_id = $select_name = 'slug';
	$redirect_url = get_admin_url( get_current_blog_id(), '/themes.php?page=pb_custom_css&slug=' );
	$html = '';

	$html .= "
	<script type='text/javascript'>
    // <![CDATA[
	jQuery.noConflict();
	jQuery(function ($) {
		$('#" . $select_id . "').change(function() {
		  window.location = '" . $redirect_url . "' + $(this).val();
		});
	});
	// ]]>
    </script>";

	$html .= '<select id="' . $select_id . '" name="' . $select_name . '">';
	foreach ( get_supported() as $key => $val ) {
		$html .= '<option value="' . $key . '"';
		if ( $key === $slug ) {
			$html .= ' selected="selected"';
		}
		if ( 'Web' === $val ) {
			$val = __( 'Web', 'pressbooks' );
		}
		$html .= '>' . $val . '</option>';
	}
	$html .= '</select>';

	return $html;
}


/**
 * Render dropdown and JavaScript for CSS copy.
 *
 * @param string $slug
 *
 * @return string
 */
function render_dropdown_for_css_copy( $slug ) {

	$select_id = $select_name = 'pb-load-css-from';
	$themes = wp_get_themes( [ 'allowed' => true ] );
	$ajax_nonce = wp_create_nonce( 'pb-load-css-from' );
	$html = '';

	$html .= "
	<script type='text/javascript'>
    // <![CDATA[
	jQuery.noConflict();
	jQuery(function ($) {
		$('#" . $select_id . "').change(function() {
			var enable = confirm('" . __( 'This will overwrite existing custom CSS. Are you sure?', 'pressbooks' ) . "');
			if (enable == true) {
				var my_slug = $(this).val();
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'pb_load_css_from',
						slug: my_slug,
						_ajax_nonce: '" . $ajax_nonce . "'
					},
					beforeSend: function() {
						$('input[type=\"submit\"]').attr('disabled', 'disabled');
					},
					success: function(data) {
						$('#my_custom_css').val(data.content);
						$('form#pb-custom-css-form').submit();
					}
				});
			}
			$('#" . $select_id . " option:first-child').attr('selected', 'selected');
		});
	});
	// ]]>
    </script>";

	$html .= '<select id="' . $select_id . '" name="' . $select_name . '">';
	$html .= '<option value="">---</option>';
	foreach ( $themes as $key => $theme ) {
		if ( 'pressbooks-custom-css' === $key ) {
			continue; // Skip
		}
		$html .= '<option value="' . "{$key}__{$slug}" . '"'; // Explode on __
		$html .= '>' . $theme->name . '</option>';
	}
	$html .= '</select>';

	return $html;
}


/**
 * WP_Ajax hook. Copy book style from an existing template.
 */
function load_css_from() {

	check_ajax_referer( 'pb-load-css-from' );
	if ( empty( current_user_can( 'edit_others_posts' ) ) ) {
		die( -1 );
	}

	$css = '';
	$themes = wp_get_themes( [ 'allowed' => true ] );
	list( $theme, $slug ) = explode( '__', $_POST['slug'] );

	if ( isset( $themes[ $theme ] ) ) {

		$theme = $themes[ $theme ]; // Get theme object
		/** @var $theme \WP_Theme */

		// TODO: SCSS is optional, what if the user wants to copy from an old theme that has not yet been covnerted? This file won't exist?

		$sass = Container::get( 'Sass' );

		$path_to_style = '';
		$uri_to_style = '';
		if ( $sass->isCurrentThemeCompatible( 1, $theme ) ) {
			if ( 'web' === $slug ) {
				$path_to_style = realpath( $theme->get_stylesheet_directory() . '/style.scss' );
				$uri_to_style = $theme->get_stylesheet_directory_uri();
			} else {
				$path_to_style = realpath( $theme->get_stylesheet_directory() . "/export/$slug/style.scss" );
				$uri_to_style = false; // We don't want a URI for EPUB or Prince exports
			}
		} elseif ( $sass->isCurrentThemeCompatible( 2, $theme ) ) {
			$path_to_style = realpath( $theme->get_stylesheet_directory() . "/assets/styles/$slug/style.scss" );
			$uri_to_style = false; // We don't want a URI for EPUB or Prince exports
			if ( 'web' === $slug ) {
				$uri_to_style = $theme->get_stylesheet_directory_uri();
			}
		}

		if ( $path_to_style ) {

			$scss = file_get_contents( $path_to_style );

			if ( $sass->isCurrentThemeCompatible( 1, $theme ) ) {
				$includes = [
					$sass->pathToUserGeneratedSass(),
					$sass->pathToPartials(),
					$sass->pathToFonts(),
					$theme->get_stylesheet_directory(),
				];
			} elseif ( $sass->isCurrentThemeCompatible( 2, $theme ) ) {
				$includes = $sass->defaultIncludePaths( $slug, $theme );
			} else {
				$includes = [];
			}

			$css = $sass->compile( $scss, $includes );

			$css = fix_url_paths( $css, $uri_to_style );

			$uri = $theme->get( 'ThemeURI' );

			$css = "/*\n Theme URI: {$uri}\n*/\n" . $css;
		}
	}

	// Send back JSON
	header( 'Content-Type: application/json' );
	$json = json_encode( [ 'content' => $css ] );
	echo $json;

	// @see http://codex.wordpress.org/AJAX_in_Plugins#Error_Return_Values
	// Will append 0 to returned json string if we don't die()
	die();
}


/**
 * Fix url() paths in CSS
 *
 * @param $css string
 * @param $style_uri string
 *
 * @return string
 */
function fix_url_paths( $css, $style_uri ) {

	if ( $style_uri ) {
		$style_uri = rtrim( trim( $style_uri ), '/' );
	}

	// Search for all possible permutations of CSS url syntax: url("*"), url('*'), and url(*)
	$url_regex = '/url\(([\s])?([\"|\'])?(.*?)([\"|\'])?([\s])?\)/i';
	$css = preg_replace_callback(
		$url_regex, function ( $matches ) use ( $style_uri ) {

		$url = $matches[3];
		$url = ltrim( trim( $url ), '/' );

		if ( preg_match( '#^https?://#i', $url ) ) {
			return $matches[0]; // No change
		}

		if ( $style_uri ) {
			return "url($style_uri/$url)";
		} else {
			return "url($url)";
		}
	}, $css
	);

	return $css;
}

/**
 * Save custom CSS to database (and filesystem)
 *
 * @see pressbooks/templates/admin/custom-css.php
 */
function form_submit() {

	if ( empty( is_form_submission() ) || empty( current_user_can( 'edit_others_posts' ) ) ) {
		// Don't do anything in this function, bail.
		return;
	}

	// Process form
	if ( isset( $_GET['customcss'] ) && $_GET['customcss'] === 'yes' && isset( $_POST['my_custom_css'] ) && check_admin_referer( 'pb-custom-css' ) ) {
		$slug = isset( $_POST['slug'] ) ? $_POST['slug'] : 'web';
		$redirect_url = get_admin_url( get_current_blog_id(), '/themes.php?page=pb_custom_css&slug=' . $slug );

		if ( isset( $_POST['post_id'] ) && isset( $_POST['post_id_integrity'] ) && md5( NONCE_KEY . $_POST['post_id'] ) !== $_POST['post_id_integrity'] ) {
			// A hacker trying to overwrite posts?.
			error_log( '\Pressbooks\CustomCss::formSubmit error: unexpected value for post_id_integrity' );
			\Pressbooks\Redirect\location( $redirect_url . '&customcss_error=true' );
		}

		// Write to database
		$my_post = [
			'ID' => absint( $_POST['post_id'] ),
			'post_content' => cleanup_css( $_POST['my_custom_css'] ),
		];
		$response = wp_update_post( $my_post, true );

		if ( is_wp_error( $response ) ) {
			// Something went wrong?
			error_log( '\Pressbooks\CustomCss::formSubmit error, wp_update_post(): ' . $response->get_error_message() );
			\Pressbooks\Redirect\location( $redirect_url . '&customcss_error=true' );
		}

		// Write to file
		$my_post['post_content'] = stripslashes( $my_post['post_content'] ); // We purposely send \\A0 to WordPress, but we want to send \A0 to the file system
		$filename = \Pressbooks\CustomCss::getCustomCssFolder() . sanitize_file_name( $slug . '.css' );
		file_put_contents( $filename, $my_post['post_content'] );

		// Update "version"
		update_option( 'pressbooks_last_custom_css', time() );

		// Ok!
		\Pressbooks\Redirect\location( $redirect_url );
	}

}


/**
 * Check if a user submitted something to themes.php?page=pb_custom_css
 *
 * @return bool
 */
function is_form_submission() {

	if ( empty( $_REQUEST['page'] ) ) {
		return false;
	}

	if ( 'pb_custom_css' !== $_REQUEST['page'] ) {
		return false;
	}

	if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
		return true;
	}

	if ( count( $_GET ) > 1 ) {
		return true;
	}

	return false;
}


/**
 * Clean up CSS.
 * Minimal intervention, but prevent users from injecting garbage.
 *
 * @param $css
 *
 * @return string
 */
function cleanup_css( $css ) {

	$css = stripslashes( $css );

	$css = preg_replace( '/\\\\([0-9a-fA-F]{2,4})/', '\\\\\\\\$1', $prev = $css );

	if ( $css !== $prev ) {
		$warnings[] = 'preg_replace() double escaped unicode escape sequences';
	}

	$css = str_replace( '<=', '&lt;=', $css ); // Some people put weird stuff in their CSS, KSES tends to be greedy
	$css = wp_kses_split( $prev = $css, [], [] );
	$css = str_replace( '&gt;', '>', $css ); // kses replaces lone '>' with &gt;
	$css = strip_tags( $css );

	if ( $css !== $prev ) {
		$warnings[] = 'kses() and strip_tags() do not match';
	}

	// TODO: Something with $warnings[]

	return $css;
}
