<?php
/**
 * Plugin Name:     Velo Glossary
 * Description:     Interactive glossary with configurable loading rules and content associations.
 * Author:          MixBus Marketing
 * Author URI:      https://mixbusmarketing.com
 * Text Domain:     velo-glossary
 * Version:         1.9.4
 * Requires at least: 6.9
 * Requires PHP:    8.0
 * License:         GPLv2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:      false
 *
 * @package         Velo_Glossary
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-velo-glossary.php';
require_once __DIR__ . '/includes/class-velo-glossary-settings.php';
require_once __DIR__ . '/includes/class-velo-glossary-admin.php';
require_once __DIR__ . '/includes/class-velo-glossary-csv.php';
require_once __DIR__ . '/includes/class-velo-glossary-abilities.php';
require_once __DIR__ . '/includes/class-velo-glossary-hovercards.php';
require_once __DIR__ . '/includes/class-velo-glossary-handler.php';
require_once __DIR__ . '/includes/class-velo-glossary-updater.php';

new Velo_Glossary_Updater( __FILE__ );
new Velo_Glossary_Settings();
new Velo_Glossary_Admin();
new Velo_Glossary_CSV();
new Velo_Glossary_Abilities();
new Velo_Glossary_Hovercards();
new Velo_Glossary_Handler();
