#!/usr/bin/env php
<?php

$config = [
    'compressor' => [
        'enable' => true,
        'computerGroup' => 'Computers',
        'path' => '/Applications/Compressor.app'
    ],
    'sublerCli' => [
        'enable' => true,
        'path' => '/usr/local/bin/SublerCLI'
    ]
];

echo "Starting the convert.php post-processing script\n";

$compressorConfig = $config['compressor'];
$sublerCliConfig = $config['sublerCli'];

// If the configs aren't enabled, just die here
if (!$compressorConfig['enable'] && !$sublerCliConfig['enable']) die();

// Environment variables from the CLI are in $_SERVER
$envVars = $_SERVER;

$apps = [
    'sonarr' => [
        'fileIdEnvVar' => 'episodefile_id',
        'filePathEnvVar' => 'episodefile_path',
        'metadata' => [
            'Name' => 'episodefile_episodetitles',
            'Artist' => 'series_title',
            'Album Artist' => 'series_title',
            'Album' => 'series_title',
            'Release Date' => 'episodefile_episodeairdates',
            'Track #' => 'episodefile_episodenumbers',
            'TV Show' => 'series_title',
            'TV Episode #' => 'episodefile_episodenumbers',
            'TV Season' => 'episodefile_seasonnumber',
            'IMDb ID' => 'series_imdbid',
            'TVDb ID' => 'series_tvdbid',
            'TVMaze ID' => 'series_tvmazeid'
        ]
    ],
    'radarr' => [
        'fileIdEnvVar' => 'moviefile_id',
        'filePathEnvVar' => 'moviefile_path',
        'metadata' => [
            'name' => 'movie_title',
            'Release Date' => 'movie_physical_release_date',
            'IMDb ID' => 'movie_imdbid',
            'TMDb ID' => 'movie_tmdbid'
        ]
    ]
];

$app = null;
foreach ($apps as $appKey => $appData) {
    if (isset($envVars["{$appKey}_eventtype"])) {
        $app = $appKey;
        break;
    }
}

// If the app isn't set, just die here
if (is_null($app)) die();

$eventType = isset($envVars["{$app}_eventtype"]) ? $envVars["{$app}_eventtype"] : null;
$fileId = isset($envVars["{$app}_{$apps[$app]['fileIdEnvVar']}"]) ? $envVars["{$app}_{$$apps[$app]['fileIdEnvVar']}"] : null;
$filePath = isset($envVars["{$app}_{$apps[$app]['filePathEnvVar']}"]) ? $envVars["{$app}_{$$apps[$app]['filePathEnvVar']}"] : null;

file_put_contents("/opt/arr-scripts/convert.log", "EventType {$eventType}\n", FILE_APPEND);

// If the event isn't `Download`, just die here
if($eventType !== "Download") die();

// Make sure the file path is set
if(!is_null($filePath)) {
    // Make sure the file path is actually real
    if(file_exists($filePath)) {
        mkdir('/opt/arr-scripts');
        file_put_contents('/opt/arr-scripts/convert.log', "Converting {$filePath}", FILE_APPEND);

        $pathInfo = pathinfo($filePath);

        if ($sublerCliConfig['enabled']) {
            $metadata = [];
            foreach ($apps[$app]['metadata'] as $metadataKey => $metadataData) {
                if (isset($envVars["{$app}_{$metadataData}"])) $metadata[$metadataKey] = $envVars["{$app}_{$metadataData}"];
            }

            if (isset($metadata['Album'])) $metadata['Album'] .= ', Season';

            $dest = "{$pathInfo['dirname']}/{$pathInfo['filename']}.m4v";

            // Now execute the converter so we can convert the file
            exec("{$sublerCliConfig['path']} -source {$filePath} -64bitchunk -dest {$dest} -chapterspreview -optimize -organizegroups");
        }

        if ($compressorConfig['enabled']) {
            $computerGroup = '';

            if ($compressorConfig['computerGroup']) $computerGroup = "-computergroup \"{$compressorConfig['computerGroup']}\"";

            $locationPath = "{$pathInfo['dirname']}/{$pathInfo['filename']}.m4v";

            // Now execute the converter so we can convert the file
            exec("{$compressorConfig['path']}/Contents/MacOS/Compressor {$computerGroup} -batchname \"{$fileId}\" -jobpath {$filePath} -settingpath {$compressorConfig['path']}/Contents/Resources/Settings/Apple\ Devices/AppleDevice4KName.compressorsetting --locationpath {$locationPath} ‑renametrackswithlayouts");
        }
    }
}