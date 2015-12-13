<?php
/*
** Copyright 2010-2015, Pye Brook Company, Inc.
**
**
** This software is provided under the GNU General Public License, version
** 2 (GPLv2), that covers its  copying, distribution and modification. The 
** GPLv2 license specifically states that it only covers only copying,
** distribution and modification activities. The GPLv2 further states that 
** all other activities are outside of the scope of the GPLv2.
**
** All activities outside the scope of the GPLv2 are covered by the Pye Brook
** Company, Inc. License. Any right not explicitly granted by the GPLv2, and 
** not explicitly granted by the Pye Brook Company, Inc. License are reserved
** by the Pye Brook Company, Inc.
**
** This software is copyrighted and the property of Pye Brook Company, Inc.
**
** Contact Pye Brook Company, Inc. at info@pyebrook.com for more information.
**
** This software is distributed in the hope that it will be useful, but WITHOUT ANY
** WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR 
** A PARTICULAR PURPOSE. 
**
*/


/*
 * A constant we use to adjust the priority of the filters this plugin sts up, override in wp-config.php or elsewhere
 */
define( 'WPCDN_FILTER_PRIORITY', 100 );

$jquery_scripts = false;

if ( defined( 'PBCI_CDN_FROM' ) && defined( 'PBCI_CDN_TO' ) ) {

	/**
	 * setup filters and actions that will adjust urls to static resources
	 */
	function wpcdn_setup_rewrites() {
		/*
		 * These are the filters that can intercept URLs to static resources before they are embedded in the
		 * page output
		 */
		add_filter( 'script_loader_src', 'wpcdn_script_loader_src', WPCDN_FILTER_PRIORITY, 2 );
		add_filter( 'style_loader_tag', 'wpcdn_style_loader_href', WPCDN_FILTER_PRIORITY, 3 );
		add_filter( 'admin_url', 'wpcdn_filter_admin_url', WPCDN_FILTER_PRIORITY, 3 );
		add_filter( 'plugins_url', 'wpcdn_plugins_url', WPCDN_FILTER_PRIORITY, 3 );
		add_filter( 'includes_url', 'wpcdn_includes_url', WPCDN_FILTER_PRIORITY, 2 );

		// we can do some special things if the autoptimize plugin is installed and enabled
		if ( function_exists( 'autoptimize_end_buffering' ) ) {
			add_filter( 'autoptimize_filter_base_replace_cdn', 'wpcdn_autoptimize_filter_base_replace_cdn', WPCDN_FILTER_PRIORITY, 1 );
			add_filter( 'autoptimize_filter_cssjs_multidomain', 'wpcdn_autoptimize_filter_cssjs_multidomain', WPCDN_FILTER_PRIORITY, 1 );

			/*
			 * This will re-process the entire output buffer
			 */
			//add_filter( 'autoptimize_html_after_minify', 'wpcdn_rewite_autoptimize', WPCDN_FILTER_PRIORITY, 1 );

		}

		global $jquery_scripts;
		$jquery_scripts = array();

	}

	add_action( 'plugins_loaded', 'wpcdn_setup_rewrites' );


	/**
	 * Filter the URL to the includes directory.
	 *
	 * @since 2.8.0
	 *
	 * @param string $url The complete URL to the includes directory including scheme and path.
	 * @param string $path Path relative to the URL to the wp-includes directory. Blank string
	 *                     if no path is specified.
	 */
	function wpcdn_includes_url( $url, $path ) {
		$rewritten_url = cdn_replace_direct_url_with_cdn( $url );

		if ( $rewritten_url != $url ) {
			$url = $rewritten_url;
			if ( defined( 'CDN_REWRITER_LOG' ) ) {
				error_log( __FUNCTION__ . ' ' . $rewritten_url );
			}
		}

		return $url;
	}


	/**
	 * Filter the admin area URL.
	 *
	 * @since 2.8.0
	 *
	 * @param string $url The complete admin area URL including scheme and path.
	 * @param string $path Path relative to the admin area URL. Blank string if no path is specified.
	 * @param int|null $blog_id Blog ID, or null for the current blog.
	 */
	function wpcdn_filter_admin_url( $url, $path, $blog_id ) {

		if ( 'admin-ajax.php' == $path ) {

			$url = get_site_url( $blog_id, 'wp-admin/', 'http' );

			if ( $path && is_string( $path ) ) {
				$url .= ltrim( $path, '/' );
			}

			$url = str_replace( array( 'http://', 'http://' ), '//', $url );

			if ( defined( 'CDN_REWRITER_LOG' ) ) {
				error_log( __FUNCTION__ . ' ' . $url );
			}
		}

		return $url;
	}

	/**
	 * Filter the URL to the plugins directory.
	 *
	 * @since 2.8.0
	 *
	 * @param string $url The complete URL to the plugins directory including scheme and path.
	 * @param string $path Path relative to the URL to the plugins directory. Blank string
	 *                       if no path is specified.
	 * @param string $plugin The plugin file path to be relative to. Blank string if no plugin
	 *                       is specified.
	 */
	function wpcdn_plugins_url( $url, $path, $plugin ) {
		$rewritten_url = cdn_replace_direct_url_with_cdn( $url );

		if ( $rewritten_url != $url ) {
			$url = $rewritten_url;
			if ( defined( 'CDN_REWRITER_LOG' ) ) {
				error_log( __FUNCTION__ . ' ' . $rewritten_url );
			}
		}

		return $url;
	}

	/**
	 * Filter the script loader source.
	 *
	 * @since 2.2.0
	 *
	 * @param string $url Script loader source path.
	 * @param string $handle Script handle.
	 */
	function wpcdn_script_loader_src( $url, $handle ) {
		$rewritten_url = cdn_replace_direct_url_with_cdn( $url );

		if ( $rewritten_url != $url ) {
			$url = $rewritten_url;
			if ( defined( 'CDN_REWRITER_LOG' ) ) {
				error_log( __FUNCTION__ . ' ' . $rewritten_url );
			}
		}

		$script_filename = pathinfo( parse_url( $url, PHP_URL_PATH ) , PATHINFO_BASENAME );

		if ( 0=== strpos( $script_filename, 'jquery.' ) ) {
			global $jquery_scripts;
			$jquery_scripts[] = $script_filename;
		}

		return $url;
	}

	/**
	 * Filter the HTML link tag of an enqueued style.
	 *
	 * @since 2.6.0
	 * @since 4.3.0 Introduced the `$href` parameter.
	 *
	 * @param string $html The link tag for the enqueued style.
	 * @param string $handle The style's registered handle.
	 * @param string $href The stylesheet's source URL.
	 */
	function wpcdn_style_loader_href( $html, $handle, $href ) {

		// $tag = apply_filters( 'style_loader_tag', "<link rel='$rel' id='$handle-css' $title href='$href' type='text/css' media='$media' />\n", $handle, $href );
		if ( defined( 'CDN_REWRITER_LOG' ) ) {
			error_log( __FUNCTION__ . ' ' . $href );
		}

		return cdn_replace_direct_url_with_cdn( $html );
	}

	/**
	 * attach the rewriter that will act on the complete page output
	 *
	 * NOTE: this is a pretty expensive action on a big page, a regex preg-match against a
	 *       page buffer that can be 500K or more will take some memory and a little bit of time.
	 *
	 *       If everything is working and there aren't any other plugins changing the urls to static
	 *       resournces this full buffer scan should not be necessary
	 */
	function wpcdn_rewriter_setup() {
		ob_start( 'wpcdn_rewriter_do_the_work', 0 );
	}

	/**
	 * create the url to the static resource
	 *
	 * @param $matches array
	 *
	 *   $matches[0] : http:// OR https://
	 *   $matches[1] : the source domain
	 *   $matches[2] : file path
	 *   $matches[3] : file extension with a "." ( non-capturing, so don't include in the replacement )
	 *
	 * @return string
	 */
	function cdn_rewriter_callback( $matches ) {

		if ( defined( 'CDN_REWRITER_LOG' ) ) {
			error_log( __FUNCTION__ . ' ' . $matches[1] . '+' . $matches[2] . '+' . $matches[3] . '+' . $matches[4] );
		}

		if ( defined( 'PBCI_CDN_TO' ) ) {
			$cdn_to = PBCI_CDN_TO;
		} else {
			$cdn_to = '';
		}

		if ( ! empty( $cdn_to ) ) {

			// some entirely unnecessary safety checks
			if ( false ) {
				//if ( false && empty ( $matches[1] ) || empty( $matches[2] ) || empty( $matches[3] ) || empty( $matches[4] ) ) {
				error_log( __FUNCTION__ . '::' . __LINE__ . ' ERROR url replacement callback found invalid arguments' );
				error_log( var_export( $matches, true ) );
				$s = $matches[0];
			} else {
				// put the url to the static resource back together
				$s = '//' . $cdn_to . $matches[3];
				if ( defined( 'CDN_REWRITER_LOG' ) ) {
					error_log( 'REWROTE: ' . $s );
				}
			}
		}

		return $s;
	}

	function cdn_rewriter_regex() {
		if ( ! defined( 'PBCI_CDN_FROM' ) ) {
			if ( defined( 'CDN_REWRITER_LOG' ) ) {
				error_log( __FILE__ . ' ' . __LINE__ . ' PBCI_CDN_FROM has not been defined' );
			}
			$cdn_from = '$a'; // a regex that will never match, looks for the character a after the and of the string
		} else {
			$cdn_from = quotemeta( PBCI_CDN_FROM );
		}

		$directories_to_rewrite = 'wp\-content|wp\-includes';
		$hosts_to_rewrite       = 'www.' . $cdn_from . '|' . $cdn_from;

		// "http://sparklegear.local/wp-includes/js/wp-emoji-release.min.js?ver=4.3.1"
		//1.	[1-8]	`http://`
		//2.	[8-25]	`sparklegear.local`
		//3.	[25-61]	`/wp-includes/js/wp-emoji-release.min`
		//4.	[61-64]	`.js` ( non-capturing, so don't include in the replacement )
		$regex = '#(?:^|(?<=[\s(\"\']))(http:\/\/|https:\/\/|\/\/)(' . $hosts_to_rewrite . ')(\/(?:' . $directories_to_rewrite . ')\/[^\s)\"\']*(?=(\.(?:jp?g|png|css|eot|woff|woff2|js|gif|ico|gif))))#i';

		return $regex;
	}

	add_filter( 'autoptimize_filter_base_replace_cdn', 'cdn_replace_direct_url_with_cdn', 10, 9999 );

	function cdn_replace_direct_url_with_cdn( $url ) {
		$new_url = preg_replace_callback( cdn_rewriter_regex(), 'cdn_rewriter_callback', $url );

		if ( ! empty( $new_url ) ) {
			$url = $new_url;
		}

		return $url;
	}

	/**
	 * match urls to static resurces in the html content being sent to the user browser
	 *
	 * @param $original_content
	 *
	 * @return string
	 */
	function wpcdn_rewriter_do_the_work( &$original_content ) {
		static $already_did_this = false;

		if ( defined( 'CDN_REWRITER_LOG' ) ) {
			error_log( __FUNCTION__ . ' rewriting page text on flush' );
		}

		if ( ! $already_did_this ) {
			$already_did_this = true;

			$regex       = cdn_rewriter_regex();
			$new_content = preg_replace_callback( $regex, 'cdn_rewriter_callback', $original_content, - 1, $count );

			if ( empty( $new_content ) ) {
				error_log( __FILE__ . ' ' . __LINE__ . ' no content rewrite returned' );
				$new_content = $original_content;
			} else {
				if ( defined( 'CDN_REWRITER_LOG' ) ) {
					error_log( __FUNCTION__ . ' ' . $count . ' replacements made' );
				}
			}
		} else {
			$new_content = $original_content;
		}

		return $new_content;
	}


	function wpcdn_rewite_autoptimize( $content ) {
		$content = wpcdn_rewriter_do_the_work( $content );

		return $content;
	}


	function wpcdn_relative_plugins_url( $url, $path, $plugin ) {
		$url = str_replace( array( 'http://', 'http://' ), '//', $url );

		return $url;
	}

	add_filter( 'plugins_url', 'wpcdn_relative_plugins_url', WPCDN_FILTER_PRIORITY, 3 );


	if ( ! is_admin() ) {
		function wpcdn_pre_option_autoptimize_cdn_url( $option_value ) {
			if ( defined( 'PBCI_CDN_TO' ) ) {
				$option_value = '//' . PBCI_CDN_TO;
			}

			return $option_value;
		}

		add_filter( 'wpcdn_pre_option_autoptimize_cdn_url', 'wpcdn_pre_option_autoptimize_cdn_url', WPCDN_FILTER_PRIORITY, 1 );
	}


	if ( ! function_exists( 'wpcdn_filter_site_icon_meta_tags' ) ) {
		/**
		 * Rewrite URLs that point to the page meta tags
		 *
		 * @param $meta_tags
		 *
		 * @return mixed
		 */
		function wpcdn_filter_site_icon_meta_tags( $meta_tags ) {
			foreach ( $meta_tags as $index => $meta_tag ) {

				$rewritten_url = cdn_replace_direct_url_with_cdn( $meta_tag );

				if ( $rewritten_url != $meta_tag ) {
					$url = $rewritten_url;
					if ( defined( 'CDN_REWRITER_LOG' ) ) {
						error_log( __FUNCTION__ . ' ' . $rewritten_url );
					}
				}
			}

			return $meta_tags;
		}

		add_filter( "site_icon_meta_tags", 'wpcdn_filter_site_icon_meta_tags', WPCDN_FILTER_PRIORITY, 1 );
	}

	if ( ! function_exists( 'wpcdn_rewriter_attachment_url' ) ) {
		/**
		 * Filter the image source attributes array
		 *
		 * @since 1.2.3.
		 *
		 * @param string $src The image source attributes.
		 * @param int $image_id The ID for the image.
		 * @param string|array $size The requested image size.
		 * @param bool Use a media icon to represent the attachment.
		 *
		 * @return array rewritten image source attributes array
		 */
		function wpcdn_rewriter_attachment_url( $image, $attachment_id, $size, $icon ) {
			if ( is_array( $image ) && isset( $image[0] ) ) {
				$image[0] = cdn_replace_direct_url_with_cdn( $image[0] );
				if ( defined( 'CDN_REWRITER_LOG' ) ) {
					error_log( __FUNCTION__ . ' ' . $image[0] );
				}
			}

			return $image;
		}

		add_filter( 'wp_get_attachment_image_src', 'wpcdn_rewriter_attachment_url', WPCDN_FILTER_PRIORITY, 4 );
	}

	if ( ! function_exists( 'wpcdn_rewriter_make_get_image_src' ) ) {
		/**
		 * Filter the image source attributes for Theme Foundry's Make family of themes.
		 *
		 * @since 1.2.3.
		 *
		 * @param string $src The image source attributes.
		 * @param int $image_id The ID for the image.
		 * @param bool $size The requested image size.
		 *
		 * @return array rewritten image source attributes array
		 */
		function wpcdn_rewriter_make_get_image_src( $src, $image_id, $size ) {

			if ( is_array( $src ) && isset( $src[0] ) ) {
				$src[0] = cdn_replace_direct_url_with_cdn( $src[0] );
				if ( defined( 'CDN_REWRITER_LOG' ) ) {
					error_log( __FUNCTION__ . ' ' . $src[0] );
				}
			}

			return $src;
		}

		add_filter( 'make_get_image_src', 'wpcdn_rewriter_make_get_image_src', WPCDN_FILTER_PRIORITY, 3 );
	}

	if ( ! function_exists( 'wpcdn_autoptimize_filter_base_replace_cdn' ) ) {
		/**
		 * Filter the URL that the autoptimize plugin uses for scripts
		 * see //github.com/zytzagoo/autoptimize
		 *
		 * @param string $url The complete URL to the plugins directory including scheme and path.
		 *
		 * @return string url to the script rewritten to come from the cdn
		 */
		function wpcdn_autoptimize_filter_base_replace_cdn( $url ) {

			if ( 0 === strpos( $url, 'data:image' ) ) {
				return $url;
			}

			if ( defined( 'CDN_REWRITER_LOG' ) ) {
				error_log( __FUNCTION__ . ' going to rewrite url ' . $url );
			}

			/*
			 * make sure the urls is rewritten to our cdn even if it was rewritten using the
			 * autoptimize cdn setting
			 */
			$h = parse_url( $url, PHP_URL_HOST );
			if ( ! empty( $h ) ) {
				$site_url = parse_url( get_site_url(), PHP_URL_HOST );
				if ( $site_url !== $h ) {
					$rewritten_url = str_replace( $h, $site_url, $url );
				}
			}

			$rewritten_url = cdn_replace_direct_url_with_cdn( $rewritten_url );

			if ( $rewritten_url != $url ) {
				$url = $rewritten_url;
				if ( defined( 'CDN_REWRITER_LOG' ) ) {
					error_log( __FUNCTION__ . ' ' . $rewritten_url );
				}
			}

			return $url;
		}
	}

	if ( ! function_exists( 'wpcdn_autoptimize_filter_cssjs_multidomain' ) ) {
		function wpcdn_autoptimize_filter_cssjs_multidomain( $multidomains ) {
			if ( ! in_array( PBCI_CDN_TO, $multidomains ) ) {
				$multidomains[] = PBCI_CDN_TO;
			}

			return $multidomains;
		}
	}

	/**
	 * JS optimization exclude strings, as configured in admin page.
	 *
	 * @param string $exclude : comma-seperated list of exclude strings
	 *
	 * @return string comma-seperated list of exclude strings
	 */
	function wpcdn_ao_override_jsexclude_jquery( $exclude = '' ) {
		global $jquery_scripts;

		if ( ! empty( $jquery_scripts ) ) {
			if ( ! empty( $exclude ) ) {
				$exclude .= ',';
			}
			$exclude .= implode( ',', $jquery_scripts );
		}

		if ( defined( 'CDN_REWRITER_LOG' ) ) {
			error_log( __FUNCTION__ . ' ' . $exclude );
		}

		return $exclude;
	}

	add_filter( 'autoptimize_filter_js_exclude', 'wpcdn_ao_override_jsexclude_jquery', WPCDN_FILTER_PRIORITY, 1 );

}