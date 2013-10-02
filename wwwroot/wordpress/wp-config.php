<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

$curpath = dirname(__FILE__);
require($curpath."/../../springpw.php");

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
// //Added by WP-Cache Manager
define('WP_CACHE', true); //Added by WP-Cache Manager
define('WPCACHEHOME', $curpath.'/wp-content/plugins/wp-super-cache/' ); //Added by WP-Cache Manager
define('DB_NAME', $spring_dbname);

/** MySQL database username */
define('DB_USER', $spring_dbuser);

/** MySQL database password */
define('DB_PASSWORD', $spring_dbpass);

/** MySQL hostname */
define('DB_HOST', $spring_dbhost);

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '~H=3f.mUfD]h5C)?OvA)tfaT,/Ub!4A0$K9&w|Z@$mh{I~z;j.dB+%>ph$JjY .>');
define('SECURE_AUTH_KEY',  '6 0W-[N?Pe@2OL*W|)-76ie@L#rzp{47$(:U8:las;0vvrK?~^w5qV.ePP(4J,}A');
define('LOGGED_IN_KEY',    'W!?mGU$g@usW? kMw`HU|^oewz?SD}Q1[4&$fJf4@xLdd)1?X4kM-`}XvJ[Dgwyd');
define('NONCE_KEY',        'y/UMr.>[$}N:8YIt6%|U}Y*9W,Y%X/4fCvR^u|-H8YW<!11-KAK}k7#pi}UbC![B');
define('AUTH_SALT',        'r_T--MVqlK$juMgy5!-qztx5]M$%+`BgxjpFVVbG:I=p?hs]k.?q(l/oy?h-&62Z');
define('SECURE_AUTH_SALT', '|bk%-Q%;2++XXCiKD%xo`bAsOPlq|/=T#-2Uou7_0dmB>$@mdi_&.?w0awz=QZGJ');
define('LOGGED_IN_SALT',   ')`4#$<x|a(r]|h*St+:F27zb0c*Ag.LI9&JLtZM:vb=(ucBI7O])19%f2XO8t7.s');
define('NONCE_SALT',       'aap*ho(.L%|62:vg-.EXVHZ_1r>gqlwV/W^~|sm_iI&{Or|Njsr82*DDw6q]Y|}y');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define('WPLANG', '');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
