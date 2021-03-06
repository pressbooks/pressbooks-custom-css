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
		add_theme_page( __( 'Edit CSS', 'pressbooks-custom-css' ), __( 'Edit CSS', 'pressbooks-custom-css' ), 'edit_others_posts', 'pb_custom_css', __NAMESPACE__ . '\display_custom_css' );
	}
}

/**
 * CSS for Editor
 */
function enqueue_style() {
	if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] === 'pb_custom_css' ) {
		$assets = new \PressbooksMix\Assets( 'pressbooks-custom-css', 'theme' );
		$path = $assets->getPath( 'styles/custom-css.css' );
		wp_enqueue_style( 'pb-custom-css', $path );
	}
}

/**
 * Custom-css post types
 */
function register_post_types() {
	/* Custom CSS */
	$args = [
		'exclude_from_search' => true,
		'public' => false,
		'publicly_queryable' => false,
		'show_ui' => false,
		'supports' => [ 'revisions' ],
		'label' => 'Custom CSS',
		'can_export' => false,
		'rewrite' => false,
		'capabilities' => [
			'edit_post' => 'edit_others_posts',
			'read_post' => 'read',
			'delete_post' => 'edit_others_posts',
			'edit_posts' => 'edit_others_posts',
			'edit_others_posts' => 'edit_others_posts',
			'publish_posts' => 'edit_others_posts',
			'read_private_posts' => 'read',
		],
	];
	register_post_type( 'custom-css', $args );
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
 * 'epub' is considered the default key because alphabetical order.
 * All keys must match an *existing* WP post where post_name = __key__ and post_type = 'custom-css'
 * If the key is not 'web' then it must map to: themes-book/__SOME_THEME__/export/__key__/style.css
 *
 * @return array
 */
function get_supported() {
	return [
		'epub' => 'Ebook',
		'prince' => 'PDF',
		'web' => 'Web',
	];
}


/**
 * Displays the Edit CSS Page
 */
function display_custom_css() {

	$slug = isset( $_GET['slug'] ) ? $_GET['slug'] : get_transient( 'pb-last-custom-css-slug' );
	if ( ! $slug ) {
		$slug = 'epub';
	}

	$supported = array_keys( get_supported() );
	if ( ! in_array( $slug, $supported, true ) ) {
		wp_die( "Unknown slug: $slug" );
	}

	$css_post = get_post( $slug );
	if ( false === $css_post ) {
		wp_die( sprintf( __( 'Unexpected Error: There was a problem trying to query slug: %s - Please contact technical support.', 'pressbooks-custom-css' ), $slug ) );
	}

	load_custom_css_template( $slug, $css_post );

	set_transient( 'pb-last-custom-css-slug', $slug );
}

/**
 * Returns the latest "custom-css" post
 *
 * @see \Pressbooks\Activation::wpmuActivate
 * @see \Pressbooks\CustomCss::upgradeCustomCss
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
 * @param string $slug
 * @param \WP_Post $css_post
 */
function load_custom_css_template( $slug, $css_post ) {

	if ( ! empty( $_GET['customcss_error'] ) ) {
		// Conversion failed
		printf( '<div class="error">%s</div>', __( 'Error: Something went wrong. See logs for more details.', 'pressbooks-custom-css' ) );
	}

	$custom_form_url = wp_nonce_url( get_admin_url( get_current_blog_id(), '/themes.php?page=pb_custom_css&customcss=yes' ), 'pb-custom-css' );
	$slugs_dropdown = render_dropdown_for_slugs( $slug );
	$css_copy_dropdown = render_dropdown_for_css_copy( $slug );
	$revisions_table = render_revisions_table( $slug, $css_post->ID );
	$post_id = absint( $css_post->ID );
	$my_custom_css = $css_post->post_content;

	?>
	<div class="wrap">
		<h1><?php _e( 'Edit CSS', 'pressbooks-custom-css' ); ?></h1>
		<?php if ( $slug === 'web' ) : ?>
		<div class="notice notice-error">
			<p><?php _e( 'The Pressbooks Custom CSS theme no longer supports Custom CSS for webbooks.', 'pressbooks' ); ?></p>
			<p><?php _e( 'To customize your webbook, you will need to switch to another theme and use the Custom Styles feature. Be sure to make a copy of the stylesheet below before changing themes, as you will not be able to return to it once you do.', 'pressbooks-custom-css' ); ?></p>
		</div>
		<?php endif; ?>
		<div class="custom-css-page">
			<form id="pb-custom-css-form" action="<?php echo $custom_form_url ?>" method="post">
				<input type="hidden" name="post_id" value="<?php echo $post_id; ?>"/>
				<input type="hidden" name="post_id_integrity" value="<?php echo md5( NONCE_KEY . $post_id ); ?>"/>
				<div style="float:left;"><?php echo sprintf(
					__( 'You are currently %s CSS for', 'pressbooks-custom-css' ),
					( $slug === 'web' ) ? __( 'viewing previously saved', 'pressbooks' ) : __( 'editing', 'pressbooks' )
				) . ': ' . $slugs_dropdown; ?></div>
				<?php if ( $slug !== 'web' ) : ?>
				<div style="float:right;"><?php echo __( 'Copy CSS from', 'pressbooks-custom-css' ) . ': ' . $css_copy_dropdown; ?></div>
				<?php endif; ?>
				<label for="my_custom_css"></label>
				<textarea id="my_custom_css" name="my_custom_css" cols="70" rows="30"<?php echo ( $slug === 'web' ) ? ' disabled' : ''; ?>><?php echo esc_textarea( $my_custom_css ); ?></textarea>
				<?php if ( $slug !== 'web' ) : ?>
				<?php submit_button( __( 'Save', 'pressbooks-custom-css' ), 'primary', 'save' ); ?>
				<?php else : ?>
				<br />
				<br />
				<?php endif; ?>
			</form>
		</div>
		<?php echo $revisions_table; ?>
	</div>
	<?php
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
	$html .= '<thead><th>' . __( 'Last 10 CSS Revisions', 'pressbooks-custom-css' ) . " <em>(" . get_supported()[ $slug ] . ")</em> </th></thead><tbody>";
	foreach ( $results as $post ) {
		$html .= '<tr><td>' . wp_post_revision_title( $post ) . ' ';
		$html .= __( 'by', 'pressbooks-custom-css' ) . ' ' . get_userdata( $post->post_author )->user_login . '</td></tr>';
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
			$val = __( 'Web', 'pressbooks-custom-css' );
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
			var enable = confirm('" . __( 'This will overwrite existing custom CSS. Are you sure?', 'pressbooks-custom-css' ) . "');
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

		$styles = Container::get( 'Styles' );
		$sass = Container::get( 'Sass' );

		$path_to_style = '';
		$uri_to_style = '';
		if ( $styles->isCurrentThemeCompatible( 1, $theme ) ) {
			if ( 'web' === $slug ) {
				$path_to_style = realpath( $theme->get_stylesheet_directory() . '/style.scss' );
				$uri_to_style = $theme->get_stylesheet_directory_uri();
			} else {
				$path_to_style = realpath( $theme->get_stylesheet_directory() . "/export/$slug/style.scss" );
				$uri_to_style = false; // We don't want a URI for EPUB or Prince exports
			}
		} elseif ( $styles->isCurrentThemeCompatible( 2, $theme ) ) {
			$path_to_style = realpath( $theme->get_stylesheet_directory() . "/assets/styles/$slug/style.scss" );
			$uri_to_style = false; // We don't want a URI for EPUB or Prince exports
			if ( 'web' === $slug ) {
				$uri_to_style = $theme->get_stylesheet_directory_uri();
			}
		}

		if ( $path_to_style ) {

			$scss = file_get_contents( $path_to_style );

			if ( $styles->isCurrentThemeCompatible( 1, $theme ) ) {
				$includes = [
					$sass->pathToUserGeneratedSass(),
					$sass->pathToPartials(),
					$sass->pathToFonts(),
					$theme->get_stylesheet_directory(),
				];
			} elseif ( $styles->isCurrentThemeCompatible( 2, $theme ) ) {
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
