<?php
/**
 * Plugin Name: Polylang WooCommerce Bridge
 * Description: Adds WooCommerce products, categories, tags, shipping classes, and attributes to Polylang translation workflows.
 * Version: 1.1.0
 * Author: Local
 * License: GPL-2.0-or-later
 * Text Domain: polylang-woocommerce-bridge
 */

defined( 'ABSPATH' ) || exit;

final class Polylang_WooCommerce_Bridge {
	const OPTION_NEEDS_FLUSH = 'plwc_bridge_needs_rewrite_flush';
	const OPTION_VERSION     = 'plwc_bridge_version';
	const VERSION            = '1.1.0';

	/**
	 * Boot plugin.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'register' ) );
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
	}

	/**
	 * Mark rewrite rules for one-time flush.
	 *
	 * @return void
	 */
	public static function activate() {
		update_option( self::OPTION_NEEDS_FLUSH, 1 );
	}

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public static function register() {
		self::maybe_schedule_flush_on_version_change();

		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'pll_current_language' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'dependencies_notice' ) );
			return;
		}

		add_filter( 'pll_get_post_types', array( __CLASS__, 'register_post_types' ), 10, 2 );
		add_filter( 'pll_get_taxonomies', array( __CLASS__, 'register_taxonomies' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'render_block_core/navigation', array( __CLASS__, 'append_switcher_to_navigation_block' ), 10, 2 );
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'append_switcher_to_classic_menu' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 100 );
		add_filter( 'woocommerce_get_shop_page_id', array( __CLASS__, 'translate_woocommerce_page_id' ) );
		add_filter( 'woocommerce_get_cart_page_id', array( __CLASS__, 'translate_woocommerce_page_id' ) );
		add_filter( 'woocommerce_get_checkout_page_id', array( __CLASS__, 'translate_woocommerce_page_id' ) );
		add_filter( 'woocommerce_get_myaccount_page_id', array( __CLASS__, 'translate_woocommerce_page_id' ) );
		add_filter( 'woocommerce_get_terms_page_id', array( __CLASS__, 'translate_woocommerce_page_id' ) );
	}

	/**
	 * Register WooCommerce post types as translatable in Polylang.
	 *
	 * @param array $post_types Current list.
	 * @param bool  $is_settings If true, we're in Polylang settings context.
	 * @return array
	 */
	public static function register_post_types( $post_types, $is_settings ) {
		$post_types['product'] = 'product';

		/*
		 * Product variations are internal child records.
		 * We keep them disabled in settings to avoid editor clutter.
		 */
		if ( ! $is_settings ) {
			$post_types['product_variation'] = 'product_variation';
		}

		return $post_types;
	}

	/**
	 * Register WooCommerce taxonomies as translatable in Polylang.
	 *
	 * @param array $taxonomies Current list.
	 * @param bool  $is_settings If true, we're in Polylang settings context.
	 * @return array
	 */
	public static function register_taxonomies( $taxonomies, $is_settings ) {
		$base_taxonomies = array(
			'product_cat',
			'product_tag',
			'product_shipping_class',
		);

		foreach ( $base_taxonomies as $taxonomy ) {
			$taxonomies[ $taxonomy ] = $taxonomy;
		}

		$attribute_taxonomies = self::get_attribute_taxonomies();

		foreach ( $attribute_taxonomies as $taxonomy ) {
			$taxonomies[ $taxonomy ] = $taxonomy;
		}

		return $taxonomies;
	}

	/**
	 * Build a list of attribute taxonomy names (pa_*).
	 *
	 * @return string[]
	 */
	private static function get_attribute_taxonomies() {
		$names = array();

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			$items = wc_get_attribute_taxonomies();

			if ( is_array( $items ) ) {
				foreach ( $items as $item ) {
					if ( empty( $item->attribute_name ) ) {
						continue;
					}

					if ( function_exists( 'wc_attribute_taxonomy_name' ) ) {
						$names[] = wc_attribute_taxonomy_name( $item->attribute_name );
					} else {
						$names[] = 'pa_' . sanitize_title( $item->attribute_name );
					}
				}
			}
		}

		return array_values( array_unique( array_filter( $names ) ) );
	}

	/**
	 * Flush rewrite rules once after activation.
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrite_rules() {
		if ( ! get_option( self::OPTION_NEEDS_FLUSH ) ) {
			return;
		}

		flush_rewrite_rules( false );
		delete_option( self::OPTION_NEEDS_FLUSH );
	}

	/**
	 * Ensure rewrite flush after plugin updates.
	 *
	 * @return void
	 */
	private static function maybe_schedule_flush_on_version_change() {
		$saved_version = get_option( self::OPTION_VERSION );

		if ( self::VERSION === $saved_version ) {
			return;
		}

		update_option( self::OPTION_VERSION, self::VERSION );
		update_option( self::OPTION_NEEDS_FLUSH, 1 );
	}

	/**
	 * Translate WooCommerce service page IDs for current language.
	 *
	 * @param int $page_id Source page ID.
	 * @return int
	 */
	public static function translate_woocommerce_page_id( $page_id ) {
		$page_id = absint( $page_id );

		if ( $page_id <= 0 || ! function_exists( 'pll_get_post' ) || ! function_exists( 'pll_current_language' ) ) {
			return $page_id;
		}

		$current_lang = pll_current_language( 'slug' );

		if ( empty( $current_lang ) ) {
			return $page_id;
		}

		$translated_id = pll_get_post( $page_id, $current_lang );

		if ( ! empty( $translated_id ) ) {
			return (int) $translated_id;
		}

		return $page_id;
	}

	/**
	 * Enqueue frontend styles for the language switcher.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		wp_register_style( 'plwc-bridge', false, array(), self::VERSION );
		wp_enqueue_style( 'plwc-bridge' );
		wp_add_inline_style(
			'plwc-bridge',
			'.plwc-lang-switcher-item{display:inline-flex;align-items:center;gap:8px}.plwc-lang-switcher__link{text-decoration:none;opacity:.7}.plwc-lang-switcher__link.is-current{opacity:1;font-weight:600}.plwc-lang-switcher__link:hover{opacity:1}.plwc-lang-switcher-sep{opacity:.5;font-size:.9em}'
		);
	}

	/**
	 * Append switcher into block theme navigation.
	 *
	 * @param string $block_content Rendered block html.
	 * @param array  $block Full block data.
	 * @return string
	 */
	public static function append_switcher_to_navigation_block( $block_content, $block ) {
		static $added = false;

		if ( $added ) {
			return $block_content;
		}

		if ( empty( $block['attrs']['className'] ) && false === strpos( $block_content, 'wp-block-navigation' ) ) {
			return $block_content;
		}

		$switcher = self::get_switcher_markup( true );

		if ( '' === $switcher ) {
			return $block_content;
		}

		$last_ul_close = strrpos( $block_content, '</ul>' );

		if ( false === $last_ul_close ) {
			return $block_content;
		}

		$added = true;

		return substr_replace( $block_content, $switcher . '</ul>', $last_ul_close, 5 );
	}

	/**
	 * Append switcher into classic wp_nav_menu output.
	 *
	 * @param string   $items Menu html.
	 * @param stdClass $args Menu args.
	 * @return string
	 */
	public static function append_switcher_to_classic_menu( $items, $args ) {
		static $added = false;

		if ( $added ) {
			return $items;
		}

		$switcher = self::get_switcher_markup( false );

		if ( '' === $switcher ) {
			return $items;
		}

		$added = true;

		return $items . $switcher;
	}

	/**
	 * Build switcher html using Polylang languages list.
	 *
	 * @param bool $is_block_nav Whether output is for block navigation.
	 * @return string
	 */
	private static function get_switcher_markup( $is_block_nav ) {
		if ( ! function_exists( 'pll_the_languages' ) ) {
			return '';
		}

		$languages = pll_the_languages(
			array(
				'raw'           => 1,
				'hide_if_empty' => 0,
				'hide_current'  => 0,
			)
		);

		if ( empty( $languages ) || ! is_array( $languages ) ) {
			return '';
		}

		$items = array();
		$total = count( $languages );
		$index = 0;

		foreach ( $languages as $language ) {
			if ( empty( $language['url'] ) ) {
				continue;
			}
			$index++;

			$classes = array( 'plwc-lang-switcher__link' );

			if ( ! empty( $language['current_lang'] ) ) {
				$classes[] = 'is-current';
			}

			$label = ! empty( $language['slug'] ) ? strtoupper( $language['slug'] ) : $language['name'];

			if ( $is_block_nav ) {
				$items[] = sprintf(
					'<li class="wp-block-navigation-item menu-item plwc-lang-switcher-item"><a class="wp-block-navigation-item__content %1$s" href="%2$s"><span class="wp-block-navigation-item__label">%3$s</span></a>%4$s</li>',
					esc_attr( implode( ' ', $classes ) ),
					esc_url( $language['url'] ),
					esc_html( $label ),
					$index < $total ? '<span class="plwc-lang-switcher-sep">/</span>' : ''
				);
			} else {
				$items[] = sprintf(
					'<li class="menu-item plwc-lang-switcher-item"><a class="%1$s" href="%2$s">%3$s</a>%4$s</li>',
					esc_attr( implode( ' ', $classes ) ),
					esc_url( $language['url'] ),
					esc_html( $label ),
					$index < $total ? '<span class="plwc-lang-switcher-sep">/</span>' : ''
				);
			}
		}

		if ( empty( $items ) ) {
			return '';
		}

		return implode( '', $items );
	}

	/**
	 * Display dependency warning in admin.
	 *
	 * @return void
	 */
	public static function dependencies_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$missing = array();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$missing[] = 'WooCommerce';
		}

		if ( ! function_exists( 'pll_current_language' ) ) {
			$missing[] = 'Polylang';
		}

		if ( empty( $missing ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: plugin names list. */
					__( 'Polylang WooCommerce Bridge requires: %s.', 'polylang-woocommerce-bridge' ),
					implode( ', ', $missing )
				)
			)
		);
	}
}

Polylang_WooCommerce_Bridge::init();
