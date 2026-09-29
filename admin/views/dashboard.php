<?php
/**
 * The Lutecia screen: one sheet, three screens. Welcome (connect, or the
 * copy-of-a-store notice), Setup (the wizard: Store, Catalog, Stripe,
 * Assistants) and Home (Stripe, sales, Channels, Controls, Settings). This
 * shell picks the screen to open and defines what the partials share; the
 * scripts in assets/js/ fill the states from the store and the hub.
 *
 * Copy rules: merchant words from docs/textes-publics.md, every sentence in
 * merchant-plugins/shared/strings.json, no assistant ever presented as
 * already sending traffic.
 *
 * @var \Lutecia\WC\Connection $connection
 * @package Lutecia\WC
 */

defined( 'ABSPATH' ) || exit;

$lutecia_connected = $connection->is_connected();
$lutecia_duplicate = \Lutecia\WC\Site_Guard::in_duplicate_mode();
$lutecia_dup       = \Lutecia\WC\Site_Guard::duplicate_details();
$lutecia_wizard    = \Lutecia\WC\Wizard::state();
$lutecia_stripe    = \Lutecia\WC\Stripe_Strings::all();
$lutecia_screen_t  = \Lutecia\WC\Screen_Strings::all();
$lutecia_shop_name = (string) get_bloginfo( 'name' );
$lutecia_host      = (string) wp_parse_url( home_url(), PHP_URL_HOST );
$lutecia_products  = (int) wp_count_posts( 'product' )->publish;
$lutecia_count     = 1 === $lutecia_products
	? $lutecia_screen_t['products_one']
	: sprintf( $lutecia_screen_t['products'], number_format_i18n( $lutecia_products ) );
$lutecia_link_tags = array(
	'a' => array(
		'href'   => array(),
		'target' => array(),
		'rel'    => array(),
	),
);
if ( $lutecia_duplicate || ! $lutecia_connected ) {
	$lutecia_open = 'welcome';
} elseif ( $lutecia_wizard['step'] > 0 ) {
	$lutecia_open = 'setup';
} else {
	// Connected before the wizard existed, or setup left with Finish later or Done.
	$lutecia_open = 'home';
}
?>
<div class="wrap lutecia-wrap" id="lutecia-app"
	data-connected="<?php echo esc_attr( $lutecia_connected ? '1' : '0' ); ?>"
	data-duplicate="<?php echo esc_attr( $lutecia_duplicate ? '1' : '0' ); ?>"
	data-lutecia-current="<?php echo esc_attr( $lutecia_open ); ?>"
	data-lutecia-wizard="<?php echo esc_attr( (string) wp_json_encode( $lutecia_wizard ) ); ?>">
	<h1 class="screen-reader-text">Lutecia</h1>
	<div class="lutecia-sheet">
		<?php
		include __DIR__ . '/welcome.php';
		include __DIR__ . '/setup.php';
		include __DIR__ . '/home.php';
		?>
	</div>
</div>
