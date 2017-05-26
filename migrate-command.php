<?php

const DUMP_FILENAME_PREFIX = 'wp-cli-migrate-mysql-dump';
const MYSQL_CONFIG_FILENAME_PREFIX = 'wp-cli-migrate-mysql-config';

function dsn($connection) {
    return $connection['host'].'/'.$connection['database'];
}

function migrate_db($sourceName, $targetName, $yes) {
    $config = WP_CLI::get_runner()->extra_config['databases'];

    if (!$source = $config[$sourceName]) {
        throw new RuntimeException("The connection '$sourceName' is not defined in the config.");
    }

    if (!$target = $config[$targetName]) {
        throw new RuntimeException("The connection '$targetName' is not defined in the config.");
    }

    if ($yes || readline('Are you sure you want to replace data from '.dsn($target).' with data from '.dsn($source).' (y/n)? ') == 'y') {

        $dumpFilename = tempnam('.', DUMP_FILENAME_PREFIX);
        $configFilename = tempnam('.', MYSQL_CONFIG_FILENAME_PREFIX);
        touch($dumpFilename);
        file_put_contents($configFilename,
'[client]
host = '.$source['host'].'
user = '.$source['user'].'
password = '.$source['password']);

        exec('mysqldump --defaults-extra-file="'.$configFilename.'" '.$source['database'].'> "'.$dumpFilename.'"');
        $code = file_get_contents($dumpFilename);
        unlink($dumpFilename);
        unlink($configFilename);

        $newCode = str_replace($source['domain-prefix'], $target['domain-prefix'], $code);

        $db = new \PDO('mysql:host=' . $target['host'], $target['user'], $target['password']);

        $db->exec('DROP DATABASE ' . $target['database'] . ' IF EXISTS');
        $db->exec('CREATE DATABASE ' . $target['database']);
        $db->exec('USE ' . $target['database']);
        $db->exec($newCode);
    }
};

function migrate_uploads($sourceName, $targetName, $yes) {
    $uploadsConfig = WP_CLI::get_runner()->extra_config['uploads'] ?: [];
    $aliases = WP_CLI::get_runner()->aliases;

    function getUploadsPath($alias, $uploadsConfig) {
        return (array_key_exists($alias, $uploadsConfig)) ? $uploadsConfig[$alias] : 'wp-content/uploads';
    }
    function getUploadsUrl($alias, $uploadsConfig, $aliases) {
        $uploadsPath = getUploadsPath($alias, $uploadsConfig);
        if (!array_key_exists($alias, $aliases)) {
            return $uploadsPath;
        }
        return $aliases[$alias]['ssh'].':'.$aliases[$alias]['path'].'/'.$uploadsPath;
    }
    $sourceUploadsUrl = getUploadsUrl($sourceName, $uploadsConfig, $aliases);
    $targetUploadsUrl = getUploadsUrl($targetName, $uploadsConfig, $aliases);

    if ($yes || readline('Are you sure you want to replace data from '.$targetUploadsUrl.' with data from '.$sourceUploadsUrl.' (y/n)? ') == 'y') {
        passthru('rsync -a -v --delete ' . $sourceUploadsUrl . '/ ' . $targetUploadsUrl);
    }
};

function migrate_command($args, $assoc_args) {

    $subcommandName = (count($args) > 2) ? array_shift($args) : null;

    $sourceName = $args[0];
    $targetName = $args[1];

    if ($subcommandName == 'db' || $subcommandName == null) {
        migrate_db($sourceName, $targetName, $assoc_args['yes']);
    }
    if ($subcommandName == 'up' || $subcommandName == null) {
        migrate_uploads($sourceName, $targetName, $assoc_args['yes']);
    }
}

WP_CLI::add_command('migrate', 'migrate_command');