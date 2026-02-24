=== Polylang WooCommerce Bridge ===
Contributors: hubCodesay
Tags: polylang, woocommerce, multilingual, translation, language switcher
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects Polylang and WooCommerce so products, categories, tags, shipping classes, and attributes are translatable. Includes WooCommerce page language mapping and optional header language switcher.

== Description ==

This plugin makes Polylang + WooCommerce work together out of the box.

Features:
- Registers WooCommerce `product` post type for Polylang translation.
- Registers WooCommerce taxonomies for translation:
  - `product_cat`
  - `product_tag`
  - `product_shipping_class`
  - all attribute taxonomies (`pa_*`)
- Maps WooCommerce system pages by current language:
  - Shop
  - Cart
  - Checkout
  - My account
  - Terms and conditions
- Flushes rewrite rules on activation and updates to reduce multilingual 404 issues.
- Optional automatic header language switcher:
  - works with block navigation and classic menus
  - supports code labels (EN/UK) or full names
  - optional flags

== Installation ==

1. Upload plugin folder to `/wp-content/plugins/`.
2. Activate **Polylang WooCommerce Bridge** in WordPress admin.
3. Make sure **Polylang** and **WooCommerce** are active.
4. Go to `Settings > Permalinks` and click **Save Changes** once.
5. In Polylang, create translations for WooCommerce pages and products.

== Frequently Asked Questions ==

= Why do I see 404 on translated shop/product pages? =

Usually because rewrite rules or translation links were missing.
This plugin schedules rewrite flush on activation/update, but it is still recommended to save permalinks once.
Also make sure translated Shop page is linked in Polylang.

= Can I disable the automatic header language switcher? =

Yes. Go to `Settings > General` and disable **Header language switcher**.

== Changelog ==

= 1.2.2 =
- Added WooCommerce feature compatibility declarations (HPOS/custom order tables and cart/checkout blocks) to remove incompatibility warning in Woo admin.

= 1.2.1 =
- Fixed WooCommerce catalog language filtering: shop/archive/category/tag/shortcode product queries now respect current Polylang language.

= 1.2.0 =
- Added production-ready plugin metadata and lifecycle hooks.
- Added settings in General for switcher enable/flags/full labels.
- Added uninstall cleanup support.
- Kept WooCommerce + Polylang translation integration and page ID mapping.
- Improved structure for reuse on other websites.

= 1.1.0 =
- Added one-time rewrite flush and WooCommerce translated page mapping.
- Added automatic header language switcher support.

= 1.0.0 =
- Initial release.
