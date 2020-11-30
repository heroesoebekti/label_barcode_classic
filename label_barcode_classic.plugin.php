<?php
/**
 * Plugin Name: Label Barcode Lawasan
 * Plugin URI: https://github.com/heroesoebekti/label_barcode_classic
 * Description: Label Barcode Lawasan adalah plugin label barcode yang paling anda butuhkan
 * Version: 0.0.1
 * Author: Heru Subekti
 * Author URI: https://www.facebook.com/heroe.soebekti
 */

// get plugin instance
$plugin = \SLiMS\Plugins::getInstance();

// registering menus
$plugin->registerMenu('bibliography', 'Label Barcode Classic', __DIR__ . '/index.php');
