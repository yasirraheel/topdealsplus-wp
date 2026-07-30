<?php
define( 'WP_CACHE', true ); // Added by WP Rocket

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'topdealsplus-wp' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'tX_~?P$L:k8earB!; A=p7VIOH$}bM%1aK=$/m5%[U~zdU1q^K[KL@2z?3a$wo4]' );
define( 'SECURE_AUTH_KEY',  'W@V5;lZOi Y}GQET&0*xM-;B9mNP~h#Jm+{sF|)eY5mlyTw4Q<Q.>|-%DadVFAcm' );
define( 'LOGGED_IN_KEY',    'm#0FI2BM1]4!N$`,p+dX<j~QiuatEP+CL&|`?1zTuab~z81g(]iIg&o#y<9;c~eD' );
define( 'NONCE_KEY',        '/}zLU$2vEG7(-m>*, {h[OU]*?q(-<)A!fU/u}l=Eq(4rEYLIK:J51R?h g,A<It' );
define( 'AUTH_SALT',        'T/G1R[d#B6+h+zUa~oOSAx4Ik=>lpcpQ7F6pc5&yH*|y4M.U%1FXI)v=Arv`0^=,' );
define( 'SECURE_AUTH_SALT', 'u;TtrF&}$Lbl7 CB*=>4U,~3v|WZ@N_BTnK 7Q>HbrHVw!#hSCMi8CH=DG9}7_T>' );
define( 'LOGGED_IN_SALT',   'b+$ID{N>_XuRMa{-i~FaubK:eug^EZ|e_P-Kuv`GGd]PB9wi)zNOQ6 @Iy%!rq%)' );
define( 'NONCE_SALT',       'lG[TW`KQm~qcxnQ,^w,h=EKc,#o*joLixBH0l4$G[LqBU<ln9{8mDf>&mpQ,UTQk' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
