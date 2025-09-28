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
define( 'AUTH_KEY',          'I^b5K],:R)~sk@9jRr3a90E(&/#7LQ+k#}QdCD.&[TAa-)`mdJ0EPI]e:!PD]:;1' );
define( 'SECURE_AUTH_KEY',   'Phda8}-8|9wP+de{S?Y{)dPQVKI!b.5w%Bw;RQ`UOt^C+b-o5*KB^EUmh5ld$m)U' );
define( 'LOGGED_IN_KEY',     '(R_f^<#IoBo+CQ6Vd.9x9+,4C2z8iS?wOV]v}>,q0YxCB!(1JqiHCMZh<=!T<8#2' );
define( 'NONCE_KEY',         '|y^isH)6ojko1 db!=Evg.Wz5r{#SQ@cXjAoR]@*.zsIQfZ*mnGp1B{^j4:^~HY{' );
define( 'AUTH_SALT',         'Rg-?b+Q0!gR,<x7KSm4,K)G:J*o<R.7a22tJxpR;7(Z6rD30`e+wKi*DHsV2g*4N' );
define( 'SECURE_AUTH_SALT',  '?UE/?mxDU6gGN?=l;0/+T!0oP3767rX&m8)|{^a&3=%^--}<aN<;4|^2)E~5<oy ' );
define( 'LOGGED_IN_SALT',    '_<Ohv.pUJq.jaVEK:ALksQBGs{>OhqGV wBQHz[x<ArbvJi^ne;qBZC,`6C?#IL.' );
define( 'NONCE_SALT',        'N8BXs<==zEJ)W{_oPaD1Kl.G,ge_lM<a~!?C1SpfWXm cZv9 $Vrcd_oC+DC#7g(' );
define( 'WP_CACHE_KEY_SALT', 'vuk-v9mVS+(sBy<5vWK6yO_i=H{U8#xwgiO+{@bH2k8c@1(D[%Qj@tce;z8[5x-p' );


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
