<?php
/*
 * Plugin Name: TMJ Blog Discussion
 * Description: Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam eget ante non diam pretium lacinia.
 * Author: TMJP DSO - Web and Multimedia Design ( Raye Anne De Grano and Ryan Codizal )
 * Version: 1.0.0.0
 */

//Plugin Security
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access.' );
}

//Define the plugin version and files
define( 'TMJ_BLOG_DISCUSSION_VERSION', '1.0.0.0' );
define( 'TMJ_FILE', __FILE__ );

//Load the autoloader and main class file
require_once 'autoloader.php';
require 'tmj-blog-discussion.php';
