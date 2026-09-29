<?php
/**
 * Brand marks shown next to a program's name, in their owners' colours,
 * drawn inline (no request, no file). Google, Microsoft and ChatGPT come
 * from their Wikimedia Commons files, Perplexity from Simple Icons (CC0)
 * in its brand colour. The marks stay the property of their owners and
 * only say which service a row is about.
 *
 * @package Lutecia\WC
 */

namespace Lutecia\WC;

defined( 'ABSPATH' ) || exit;

final class Brand_Icons {

	/** Program key => array( viewBox, SVG content ). Constants of this file, never input. */
	private const MARKS = array(
		'chatgpt' => array( '0 0 2406 2406', '<path d="M1 578.4C1 259.5 259.5 1 578.4 1h1249.1c319 0 577.5 258.5 577.5 577.4V2406H578.4C259.5 2406 1 2147.5 1 1828.6V578.4z" fill="#74aa9c"/> <path id="lutecia-mark-oai" d="M1107.3 299.1c-197.999 0-373.9 127.3-435.2 315.3L650 743.5v427.9c0 21.4 11 40.4 29.4 51.4l344.5 198.515V833.3h.1v-27.9L1372.7 604c33.715-19.52 70.44-32.857 108.47-39.828L1447.6 450.3C1361 353.5 1237.1 298.5 1107.3 299.1zm0 117.5-.6.6c79.699 0 156.3 27.5 217.6 78.4-2.5 1.2-7.4 4.3-11 6.1L952.8 709.3c-18.4 10.4-29.4 30-29.4 51.4V1248l-155.1-89.4V755.8c-.1-187.099 151.601-338.9 339-339.2z" fill="#fff"/> <use href="#lutecia-mark-oai" transform="rotate(60 1203 1203)"/> <use href="#lutecia-mark-oai" transform="rotate(120 1203 1203)"/> <use href="#lutecia-mark-oai" transform="rotate(180 1203 1203)"/> <use href="#lutecia-mark-oai" transform="rotate(240 1203 1203)"/> <use href="#lutecia-mark-oai" transform="rotate(300 1203 1203)"/>' ),
		'google' => array( '0 0 24 24', '<path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/><path d="M1 1h22v22H1z" fill="none"/>' ),
		'microsoft' => array( '0 0 23 23', '<path fill="#f35325" d="M1 1h10v10H1z"/><path fill="#81bc06" d="M12 1h10v10H12z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/><path fill="#ffba08" d="M12 12h10v10H12z"/>' ),
		'perplexity' => array( '0 0 24 24', '<path fill="#20808D" d="M22.3977 7.0896h-2.3106V.0676l-7.5094 6.3542V.1577h-1.1554v6.1966L4.4904 0v7.0896H1.6023v10.3976h2.8882V24l6.932-6.3591v6.2005h1.1554v-6.0469l6.9318 6.1807v-6.4879h2.8882V7.0896zm-3.4657-4.531v4.531h-5.355l5.355-4.531zm-13.2862.0676 4.8691 4.4634H5.6458V2.6262zM2.7576 16.332V8.245h7.8476l-6.1149 6.1147v1.9723H2.7576zm2.8882 5.0404v-3.8852h.0001v-2.6488l5.7763-5.7764v7.0111l-5.7764 5.2993zm12.7086.0248-5.7766-5.1509V9.0618l5.7766 5.7766v6.5588zm2.8882-5.0652h-1.733v-1.9723L13.3948 8.245h7.8478v8.087z"/>' ),
	);

	/**
	 * The inline SVG of a program's mark, '' when the program has none.
	 * Decorative: the name next to it carries the meaning.
	 */
	public static function svg( string $key ): string {
		if ( ! isset( self::MARKS[ $key ] ) ) {
			return '';
		}
		list( $view_box, $content ) = self::MARKS[ $key ];
		return '<svg class="lutecia-mark" viewBox="' . esc_attr( $view_box ) . '" width="16" height="16" aria-hidden="true" focusable="false">' . $content . '</svg>';
	}
}
