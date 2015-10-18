<?php
namespace wapmorgan\Builder;

use Symfony\Component\Console\Logger\ConsoleLogger;
use Psr\Log\LogLevel;

use ZipArchive;

abstract class AbstractInstaller {
    public static function download($url_template, $version, $file, ConsoleLogger $logger) {
        $url = str_replace('{%version}', $version, $url_template);
        $logger->log(LogLevel::INFO, 'Generated download url: '.$url);
        $logger->log(LogLevel::INFO, 'Output local file: '.$file);
        $result = copy($url, $file);
        if ($result)
            $logger->log(LogLevel::INFO, 'Downloaded: '.filesize($file).' b');
        else
            $logger->log(LogLevel::ALERT, 'Could not successfully download file');
        return $result;
    }

    public static function checkHash($hashurl_template, $version, $file, ConsoleLogger $logger) {
        $url = str_replace('{%version}', $version, $hashurl_template);
        $logger->log(LogLevel::INFO, 'Generated hash url: '.$url);
        $hash = trim(file_get_contents($url));
        $logger->log(LogLevel::INFO, 'Hash: '.$hash);
        $actual_hash = md5_file($file);
        $logger->log(LogLevel::INFO, 'Actual hash: '.$actual_hash);
        if ($hash === $actual_hash)
            return true;
        else {
            $logger->log(LogLevel::WARNING, 'Hash mismatch!');
            return false;
        }
    }

    public static function extract($file, $directory, $prefix_folder, ConsoleLogger $logger) {
        $logger->log(LogLevel::INFO, 'Archive: '.$file);
        $logger->log(LogLevel::INFO, 'Output directory: '.$directory);
        $archive = new ZipArchive();
        $archive->open($file);
        if ($prefix_folder !== null) {
            for ($i = 0, $t = $archive->numFiles; $i < $t; $i++) {
                $_file = $archive->getNameIndex($i);
                if (stripos($_file, $prefix_folder) === 0 && $_file != $prefix_folder) {
                    $logger->log(LogLevel::DEBUG, 'Renaming '.$_file.' to '.substr($_file, strlen($prefix_folder)));
                    if (!$archive->renameIndex($i, substr($_file, strlen($prefix_folder))))
                        $logger->log(LogLevel::WARNING, 'Could not rename');
                }
            }
            if (!$archive->deleteName($prefix_folder))
                $logger->log(LogLevel::WARNING, 'Could not delete prefix folder');
            // reopen archive
            $archive->close();
            if (($code = $archive->open($file)) !== true)
                $logger->log(LogLevel::WARNING, 'Could not re-open archive: '.$code);
            $result = $archive->extractTo($directory);
        } else {
            $result = $archive->extractTo($directory);
        }
        if ($result)
            $logger->log(LogLevel::INFO, 'Extracted '.$archive->numFiles.' file(s)');
        else
            $logger->log(LogLevel::ALERT, 'Could not successfully extract file: '.$archive->getStatusString());
        $archive->close();
        return $result;
    }

    public static function clean($file, ConsoleLogger $logger) {
        if (unlink($file))
            $logger->log(LogLevel::INFO, 'Deleted file: '.$file);
        else
            $logger->log(LogLevel::NOTICE, 'Could not delete file "'.$file.'"');
    }
    
    protected static function path($segment)
    {
        return join(DIRECTORY_SEPARATOR, func_get_args());
    }
}
