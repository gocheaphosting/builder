<?php
namespace wapmorgan\Builder\Installers;

use wapmorgan\Builder\AbstractInstaller;

use Symfony\Component\Console\Logger\ConsoleLogger;
use Psr\Log\LogLevel;

use \Exception;

class WordpressInstaller extends AbstractInstaller {
    static public function createConfiguration(array $data, $directory, ConsoleLogger $logger) {
        if (isset($data['database'])) {
            if (!file_exists(self::path($directory, 'wp-config-sample.php')))
                throw new Exception('Inconsistency in wordpress directory "'.$directory.'". File wp-config-sample.php does not exist!');
            if (!copy(self::path($directory, 'wp-config-sample.php'), self::path($directory, 'wp-config.php')))
                throw new Exception('Could not copy sample configuration to original configuration in directory "'.$directory.'"!');
            $fp = file_get_contents(self::path($directory, 'wp-config.php'));
            $fp = preg_replace(array(
                    '~define\((\'|")DB_NAME(\'|"),\s+(\'|").+(\'|")\)\;~',
                    '~define\((\'|")DB_USER(\'|"),\s+(\'|").+(\'|")\)\;~',
                    '~define\((\'|")DB_PASSWORD(\'|"),\s+(\'|").+(\'|")\)\;~',
                    '~define\((\'|")DB_HOST(\'|"),\s+(\'|").+(\'|")\)\;~'
                ),
                array(
                    'define("DB_NAME", "'.$data['database']['database'].'");',
                    'define("DB_USER", "'.$data['database']['username'].'");',
                    'define("DB_PASSWORD", "'.$data['database']['password'].'");',
                    'define("DB_HOST", "'.$data['database']['host'].'");'
                ), $fp);
            file_put_contents(self::path($directory, 'wp-config.php'), $fp);
            $logger->log(LogLevel::INFO, 'Configuration saved');
        } else {
            $logger->log(LogLevel::NOTICE, 'Skipping step due to missing database configuration');
            return true;
        }
    }

    static public function installDatabase(array $data, $directory, ConsoleLogger $logger) {
        // need for wordpress database functionality
        global $wpdb;

        if (!isset($data['blog_title'])) {
            $logger->log(LogLevel::NOTICE, 'Skipping step due to missing blog configuration');
            return true;
        }

        define('WP_INSTALLING', true);
        require_once self::path($directory, 'wp-config.php');
        // require_once self::path($directory, 'includes', 'wp-db.php';
        require_once self::path($directory, 'wp-admin', 'upgrade-functions.php');

        if (!function_exists('wp_install')) {
            $logger->log(LogLevel::WARNING, 'Could not find function "wp_install" in file "'.self::path($directory, 'wp-admin', 'includes', 'upgrade.php"'));
        }
        if (isset($data['password'])) {
            $logger->log(LogLevel::INFO, 'Using password: '.$data['password']);
            $result = wp_install($data['blog_title'], $data['admin'], $data['admin_email'], true, '', $data['password']);
        } else {
            $result = wp_install($data['blog_title'], $data['admin'], $data['admin_email'], true);
        }

        if ($result) {
            $logger->log(LogLevel::INFO, 'Wordpress successfully installed. Password is '.$result['password']);
        } else {
            $logger->log(LogLevel::WARNING, 'Unexpected error occured during this step');
        }
        return $result;
    }
}
