<?php
/**
 * Plugin Name: Classic Label & Barcode
 * Plugin URI: https://github.com/heroesoebekti/label_barcode_classic
 * Description: The colorfull label & barcode you'll like the most. Label & item barcode in one place. Available with QRcode as barcode replacement.
 * Version: 0.0.1
 * Author: Heru Subekti
 * Author URI: https://www.facebook.com/heroe.soebekti
 */

// get plugin instance
$plugin = \SLiMS\Plugins::getInstance();

// registering menus
$plugin->registerMenu('bibliography', 'Classic Label & Barcode', __DIR__ . '/index.php');
