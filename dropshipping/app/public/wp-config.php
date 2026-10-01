<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'Yds^FYIhqQvU6s|rq[jw`HFXC=^QH|47r]haJoK@|4=_}`%/EmRrfKMZg%bT2_Us' );
define( 'SECURE_AUTH_KEY',   'EZlc(qY;34Aqg|$5,im-X5G4SNGNk:-3L8FDQAm]6R7HU;l`o$*qievv;^vxi`jN' );
define( 'LOGGED_IN_KEY',     '#:pEQf~/o9voWJ;S.KNoTys0 g1>!7AZ|#&Wz8(!M1bjq)`GaZ,=1_ hl;*uU&GI' );
define( 'NONCE_KEY',         'JAZxak_fMISg=E*I~1LyA?QtEAPGAE>7{Yur!7(|Td$Z9u_UM.,Gl%%nK6<7Fe_~' );
define( 'AUTH_SALT',         'mK[NCGf`(jYQ@|rZ%1Yl;)o]$q{).swEtfS]_?S,MoJ=$`&=>hKm~>P-Kg[t!&Sg' );
define( 'SECURE_AUTH_SALT',  'Yk[H?1;pG`)2e*/c,kVzmzI1Mc*&S?H7V?v4;ZY6+}Qiw)9]=QES,0JiwWrttio;' );
define( 'LOGGED_IN_SALT',    '8u >9:gbNjfjVv=K{A,ef)O}m0v]n#8&7= 8Vx[h+gd)l.=&K^SW3Ub(>9hJeX=`' );
define( 'NONCE_SALT',        'e$_<lyE}BnCVj$,pL08_tJ!7(!BPS4=</mA,abhk[,6L<z8GZ9jYTJPrki+:5a+o' );
define( 'WP_CACHE_KEY_SALT', 'qBAIDG)S-&8pJ}K7n(Lyo%:/Ho*%!pxX6jfp_oMk]DOA#bp:LTrU(q%hnAc=eEQb' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
