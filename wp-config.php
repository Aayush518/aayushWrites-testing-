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
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'aayushWrites' );

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
define( 'AUTH_KEY',         'ip,_>-{@(2bM Qj)j0,Rrz-V]d6C9R@b(@jW^(*}n^9[pT^ia%|Au561`L- M|=W' );
define( 'SECURE_AUTH_KEY',  '!ny~(~N6,dw7~EQ|yu/1~9M.bjD5ia`1 b;xK{aOAW8ZKH4$I5j^iaCXIb=taH%^' );
define( 'LOGGED_IN_KEY',    's~Z b3eL@~U2`-XgR?0>u}=(Nr/G^r0/=_jL%laW#hSp&&Np4p&4!{<bQE+hr8(f' );
define( 'NONCE_KEY',        'tgo0c+k)2a}-p2G ;A*2,ask$1>R]_Pi+DY1#U6z|HiD|<~]L<6l[7q(&WV;wM)a' );
define( 'AUTH_SALT',        'bhB&|;O*6a(vsR=$fvU-v-#y1N:Zm;h(AG&m{5D5^=XWcGkzRe.3mKG%JH*#}>{r' );
define( 'SECURE_AUTH_SALT', 'R96?-+b?qn| YNc;JPK5k=z|n_N@9FC X $GJu>hY10a,!;zhEfIGn;:Ex(47Xf;' );
define( 'LOGGED_IN_SALT',   'E9<8sm6<:%z>~YrD? efj9iz6b.uV!_H8X00NdZA0jykSK8Gk&q*6g=yPg:C(3.l' );
define( 'NONCE_SALT',       ':Nd<j_[_}B`r+Yer.cQ%Jqp>gT&:{b3zgwqfPE7r%U}/I!Q4^AscO]7*6M)8yLVe' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'aayush_';

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
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
