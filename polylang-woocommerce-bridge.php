<?php
/**
 * Plugin Name: Polylang WooCommerce Bridge
 * Plugin URI:  https://github.com/hubCodesay/Polylang-woo-
 * Description: Integrates Polylang with WooCommerce: products, categories, tags, attributes translations, WooCommerce page mapping by language, and optional header language switcher.
 * Version:     1.2.2
 * Author:      hubCodesay
 * Author URI:  https://github.com/hubCodesay
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: polylang-woocommerce-bridge
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 */

defined( 'ABSPATH' ) || exit;

final class Polylang_WooCommerce_Bridge {
	const VERSION                        = '1.2.2';
	const OPTION_VERSION                 = 'plwc_bridge_version';
	const OPTION_NEEDS_FLUSH             = 'plwc_bridge_needs_rewrite_flush';
	const OPTION_SWITCHER_ENABLED        = 'plwc_bridge_switcher_enabled';
	const OPTION_SWITCHER_SHOW_FLAGS     = 'plwc_bridge_switcher_show_flags';
	const OPTION_SWITCHER_SHOW_FULL_NAME = 'plwc_bridge_switcher_show_full_name';

	/**
	 * Boot plugin.
	 *
	 * @return void
	 */
	public static function init() {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_woocommerce_compatibility' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'register' ) );
	}

	/**
	 * Declare compatibility with WooCommerce optional features.
	 *
	 * @return void
	 */
	public static function declare_woocommerce_compatibility() {
		if ( ! class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}

	/**
	 * Load translation files.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'polylang-woocommerce-bridge', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Mark rewrite rules for one-time flush and set defaults.
	 *
	 * @return void
	 */
	public static function activate() {
		add_option( self::OPTION_SWITCHER_ENABLED, '1' );
		add_option( self::OPTION_SWITCHER_SHOW_FLAGS, '0' );
		add_option( self::OPTION_SWITCHER_SHOW_FULL_NAME, '0' );

		update_option( self::OPTION_NEEDS_FLUSH, '1' );
		update_option( self::OPTION_VERSION, self::VERSION );
	}

	/**
	 * Flush rewrites on deactivation to avoid stale rules.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules( false );
	}

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public static function register() {
		self::maybe_schedule_flush_on_version_change();
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 100 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'pll_current_language' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'dependencies_notice' ) );
			return;
		}

		add_filter( 'pll_get_post_types', array( __CLASS__, 'register_post_types' ), 10, 2 );
		add_filter( 'pll_get_taxonomies', array( __CLASS__, 'register_taxonomies' ), 10, 2 );

		add_filter( 'woocommerce_get_shop_page_id', array( __CLASS__, 'translate_woocommerce_page_id' ) );
		add_filter( 'woocommerce_get_cart_page_id', array( __CLASS__, 'translate_woocommerce_page_id' ) );
		add_filter( 'woocommerce_get_checkout_page_id', array( __CLASS__, 'translate_woocommerce_page_id' ) );
		add_filter( 'woocommerce_get_myaccount_page_id', array( __CLASS__, 'translate_woocommerce_page_id' ) );
		add_filter( 'woocommerce_get_terms_page_id', array( __CLASS__, 'translate_woocommerce_page_id' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_product_queries_by_language' ), 20 );
		add_filter( 'woocommerce_shortcode_products_query', array( __CLASS__, 'filter_shortcode_products_query_by_language' ), 10, 3 );

		if ( self::is_switcher_enabled() ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			add_filter( 'render_block_core/navigation', array( __CLASS__, 'append_switcher_to_navigation_block' ), 10, 2 );
			add_filter( 'wp_nav_menu_items', array( __CLASS__, 'append_switcher_to_classic_menu' ), 10, 2 );
		}
	}

	/**
	 * Register plugin settings in WP General Settings page.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'general',
			self::OPTION_SWITCHER_ENABLED,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => '1',
			)
		);

		register_setting(
			'general',
			self::OPTION_SWITCHER_SHOW_FLAGS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => '0',
			)
		);

		register_setting(
			'general',
			self::OPTION_SWITCHER_SHOW_FULL_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => '0',
			)
		);

		add_settings_section(
			'plwc_bridge_settings_section',
			__( 'Polylang WooCommerce Bridge', 'polylang-woocommerce-bridge' ),
			'__return_false',
			'general'
		);

		add_settings_field(
			self::OPTION_SWITCHER_ENABLED,
			__( 'Header language switcher', 'polylang-woocommerce-bridge' ),
			array( __CLASS__, 'render_checkbox_field' ),
			'general',
			'plwc_bridge_settings_section',
			array(
				'option_name' => self::OPTION_SWITCHER_ENABLED,
				'label'       => __( 'Enable automatic switcher in header menus', 'polylang-woocommerce-bridge' ),
			)
		);

		add_settings_field(
			self::OPTION_SWITCHER_SHOW_FLAGS,
			__( 'Switcher flags', 'polylang-woocommerce-bridge' ),
			array( __CLASS__, 'render_checkbox_field' ),
			'general',
			'plwc_bridge_settings_section',
			array(
				'option_name' => self::OPTION_SWITCHER_SHOW_FLAGS,
				'label'       => __( 'Show language flags if available', 'polylang-woocommerce-bridge' ),
			)
		);

		add_settings_field(
			self::OPTION_SWITCHER_SHOW_FULL_NAME,
			__( 'Switcher labels', 'polylang-woocommerce-bridge' ),
			array( __CLASS__, 'render_checkbox_field' ),
			'general',
			'plwc_bridge_settings_section',
			array(
				'option_name' => self::OPTION_SWITCHER_SHOW_FULL_NAME,
				'label'       => __( 'Use full language names instead of code (EN/UK)', 'polylang-woocommerce-bridge' ),
			)
		);
	}

	/**
	 * Render a settings checkbox.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_checkbox_field( $args ) {
		$option_name = isset( $args['option_name'] ) ? (string) $args['option_name'] : '';
		$label       = isset( $args['label'] ) ? (string) $args['label'] : '';

		if ( '' === $option_name ) {
			return;
		}

		$value = get_option( $option_name, '0' );

		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $option_name ),
			checked( '1', (string) $value, false ),
			esc_html( $label )
		);
	}

	/**
	 * Sanitize checkbox values.
	 *
	 * @param mixed $value Input value.
	 * @return string
	 */
	public static function sanitize_checkbox( $value ) {
		return ( ! empty( $value ) && '0' !== (string) $value ) ? '1' : '0';
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
		 * Keep them hidden in settings UI to reduce noise.
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
		unset( $is_settings );

		$base_taxonomies = array(
			'product_cat',
			'product_tag',
			'product_shipping_class',
		);

		foreach ( $base_taxonomies as $taxonomy ) {
			$taxonomies[ $taxonomy ] = $taxonomy;
		}

		foreach ( self::get_attribute_taxonomies() as $taxonomy ) {
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

		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return $names;
		}

		$items = wc_get_attribute_taxonomies();

		if ( ! is_array( $items ) ) {
			return $names;
		}

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

		return array_values( array_unique( array_filter( $names ) ) );
	}

	/**
	 * Flush rewrite rules once after activation/update.
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrite_rules() {
		if ( '1' !== (string) get_option( self::OPTION_NEEDS_FLUSH ) ) {
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
		$saved_version = (string) get_option( self::OPTION_VERSION, '' );

		if ( self::VERSION === $saved_version ) {
			return;
		}

		update_option( self::OPTION_VERSION, self::VERSION );
		update_option( self::OPTION_NEEDS_FLUSH, '1' );
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

		return ! empty( $translated_id ) ? (int) $translated_id : $page_id;
	}

	/**
	 * Force current Polylang language in WooCommerce product loops.
	 *
	 * @param WP_Query $query Query instance.
	 * @return void
	 */
	public static function filter_product_queries_by_language( $query ) {
		if ( is_admin() || ! ( $query instanceof WP_Query ) ) {
			return;
		}

		if ( $query->get( 'lang' ) ) {
			return;
		}

		$is_product_query = false;
		$post_type        = $query->get( 'post_type' );

		if ( 'product' === $post_type ) {
			$is_product_query = true;
		}

		if ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) {
			$is_product_query = true;
		}

		if ( 'product_query' === $query->get( 'wc_query' ) ) {
			$is_product_query = true;
		}

		if ( $query->is_post_type_archive( 'product' ) || $query->is_tax( array( 'product_cat', 'product_tag', 'product_shipping_class' ) ) ) {
			$is_product_query = true;
		}

		if ( ! $is_product_query ) {
			return;
		}

		$current_lang = self::get_current_language();

		if ( '' !== $current_lang ) {
			$query->set( 'lang', $current_lang );
		}
	}

	/**
	 * Force current language in WooCommerce product shortcodes.
	 *
	 * @param array $query_args Query args.
	 * @param array $attributes Shortcode attributes.
	 * @param string $type Shortcode type.
	 * @return array
	 */
	public static function filter_shortcode_products_query_by_language( $query_args, $attributes, $type ) {
		unset( $attributes, $type );

		if ( ! empty( $query_args['lang'] ) ) {
			return $query_args;
		}

		$current_lang = self::get_current_language();

		if ( '' !== $current_lang ) {
			$query_args['lang'] = $current_lang;
		}

		return $query_args;
	}

	/**
	 * Get current Polylang language slug.
	 *
	 * @return string
	 */
	private static function get_current_language() {
		if ( ! function_exists( 'pll_current_language' ) ) {
			return '';
		}

		$lang = pll_current_language( 'slug' );

		return is_string( $lang ) ? $lang : '';
	}

	/**
	 * Determine whether switcher is enabled.
	 *
	 * @return bool
	 */
	private static function is_switcher_enabled() {
		return '1' === (string) get_option( self::OPTION_SWITCHER_ENABLED, '1' );
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
			'.plwc-lang-switcher-item{display:inline-flex;align-items:center;gap:8px}.plwc-lang-switcher-item img{width:16px;height:auto;border-radius:2px}.plwc-lang-switcher__link{text-decoration:none;opacity:.75}.plwc-lang-switcher__link.is-current{opacity:1;font-weight:600}.plwc-lang-switcher__link:hover{opacity:1}.plwc-lang-switcher-sep{opacity:.5;font-size:.9em}'
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

		if ( empty( $block_content ) || false === strpos( $block_content, 'wp-block-navigation' ) ) {
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
		unset( $args );

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
	 * Build switcher HTML using Polylang languages list.
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

			++$index;

			$classes = array( 'plwc-lang-switcher__link' );
			if ( ! empty( $language['current_lang'] ) ) {
				$classes[] = 'is-current';
			}

			$label = self::get_language_label( $language );
			$flag  = self::get_language_flag_markup( $language );

			if ( $is_block_nav ) {
				$items[] = sprintf(
					'<li class="wp-block-navigation-item menu-item plwc-lang-switcher-item"><a class="wp-block-navigation-item__content %1$s" href="%2$s">%3$s<span class="wp-block-navigation-item__label">%4$s</span></a>%5$s</li>',
					esc_attr( implode( ' ', $classes ) ),
					esc_url( $language['url'] ),
					$flag,
					esc_html( $label ),
					$index < $total ? '<span class="plwc-lang-switcher-sep">/</span>' : ''
				);
			} else {
				$items[] = sprintf(
					'<li class="menu-item plwc-lang-switcher-item"><a class="%1$s" href="%2$s">%3$s%4$s</a>%5$s</li>',
					esc_attr( implode( ' ', $classes ) ),
					esc_url( $language['url'] ),
					$flag,
					esc_html( $label ),
					$index < $total ? '<span class="plwc-lang-switcher-sep">/</span>' : ''
				);
			}
		}

		return empty( $items ) ? '' : implode( '', $items );
	}

	/**
	 * Build language label based on settings.
	 *
	 * @param array $language Polylang language item.
	 * @return string
	 */
	private static function get_language_label( $language ) {
		if ( '1' === (string) get_option( self::OPTION_SWITCHER_SHOW_FULL_NAME, '0' ) && ! empty( $language['name'] ) ) {
			return (string) $language['name'];
		}

		if ( ! empty( $language['slug'] ) ) {
			return strtoupper( (string) $language['slug'] );
		}

		return ! empty( $language['name'] ) ? (string) $language['name'] : '';
	}

	/**
	 * Build language flag markup if enabled.
	 *
	 * @param array $language Polylang language item.
	 * @return string
	 */
	private static function get_language_flag_markup( $language ) {
		if ( '1' !== (string) get_option( self::OPTION_SWITCHER_SHOW_FLAGS, '0' ) ) {
			return '';
		}

		if ( empty( $language['flag'] ) ) {
			return '';
		}

		return sprintf(
			'<img src="%1$s" alt="%2$s" loading="lazy" decoding="async" />',
			esc_url( $language['flag'] ),
			esc_attr( ! empty( $language['name'] ) ? $language['name'] : 'lang' )
		);
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
