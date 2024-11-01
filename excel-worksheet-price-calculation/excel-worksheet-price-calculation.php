<?php
/*
Plugin Name: Excel Worksheet Price Calculation
Plugin URI:  https://altoswebsolutions.com/cms-plugins/woopricecalculator
Description: Price Calculator for WooCommerce
Version:     2.6.3
Author:      Altos Web Solutions Italia
Author URI:  https://www.altoswebsolutions.com
License:     
License URI: 
Domain Path: /lang
Text Domain: PoEdit
*/

/*
 * ATTENTION, if you update Version, also update the $ plugin_db_version variable
 * below for the database
 */

require 'awspricecalculator.php';
require_once( ABSPATH . 'wp-admin/includes/plugin.php');

/*
 * Check that WooCommerce is activated
 */
if(is_plugin_active( 'woocommerce/woocommerce.php')){
    $GLOBALS['woopricecalculator'] = AWSPriceCalculator::instance("2.6.3");
}
