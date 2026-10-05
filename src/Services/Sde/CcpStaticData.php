<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to present Leon Jacobs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Seat\Eveapi\Services\Sde;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Class CcpStaticData.
 *
 * CCP publishes the whole static data set as a single jsonl archive. The
 * `latest` alias is answered with a redirect which carries the build number
 * on `x-sde-build-number`; the archive itself comes from object storage and
 * does not repeat it, so the redirect must not be followed when asking.
 *
 * Files are extracted per build under storage/sde/<build>/ and reused when
 * that build is already complete on disk.
 *
 * @package Seat\Eveapi\Services\Sde
 */
class CcpStaticData
{
    /**
     * CCP's `latest` alias for the jsonl archive.
     */
    public const LATEST_URL = 'https://developers.eveonline.com/static-data/eve-online-static-data-latest-jsonl.zip';

    /**
     * Archive entries the core SDE import reads.
     *
     * @var string[]
     */
    public const ARCHIVE_FILES = [
        'types.jsonl',
        'typeDogma.jsonl',
        'groups.jsonl',
        'categories.jsonl',
        'marketGroups.jsonl',
        'metaGroups.jsonl',
        'factions.jsonl',
        'contrabandTypes.jsonl',
        'controlTowerResources.jsonl',
        'typeMaterials.jsonl',
        'industryActivities.jsonl',
        'npcStations.jsonl',
        'npcCorporations.jsonl',
        'stationOperations.jsonl',
        'mapRegions.jsonl',
        'mapConstellations.jsonl',
        'mapSolarSystems.jsonl',
        'mapStars.jsonl',
        'mapPlanets.jsonl',
        'mapMoons.jsonl',
        'mapAsteroidBelts.jsonl',
        'mapStargates.jsonl',
        'mapSecondarySuns.jsonl',
    ];

    /**
     * The SDE storage path.
     *
     * @var string
     */
    protected $storage_path;

    /**
     * CcpStaticData constructor.
     */
    public function __construct()
    {
        $this->storage_path = storage_path('sde/');
    }

    /**
     * Ask CCP which build the `latest` alias currently points at.
     *
     * @return int|null
     */
    public static function latestBuild(): ?int
    {
        try {
            $client = new Client(['timeout' => 30, 'connect_timeout' => 10]);

            $response = $client->request('HEAD', self::LATEST_URL, [
                'allow_redirects' => false,
                'headers' => ['User-Agent' => 'SeAT'],
            ]);

            $build = (int) $response->getHeaderLine('x-sde-build-number');

            if ($build > 0)
                return $build;

            // Fall back to the build number baked into the redirect target.
            return self::buildFromUrl(trim((string) $response->getHeaderLine('location')));
        } catch (\Exception $e) {
            logger()->error(sprintf(
                'Unable to determine the latest CCP static data build: %s', $e->getMessage()));
        }

        return null;
    }

    /**
     * Extract the build number from a build numbered archive url.
     *
     * @param  string  $url
     * @return int|null
     */
    public static function buildFromUrl(string $url): ?int
    {
        if (preg_match('/static-data-(\d+)-jsonl\.zip/', $url, $matches))
            return (int) $matches[1];

        return null;
    }

    /**
     * Directory a build has been extracted to.
     *
     * @param  int  $build
     * @return string
     */
    public function directory(int $build): string
    {
        return $this->storage_path . $build . '/';
    }

    /**
     * Determine if every file the import needs is already on disk for a build.
     *
     * A partial extraction is treated as no extraction at all, so a build is
     * either fully available or downloaded again.
     *
     * @param  int  $build
     * @return bool
     */
    public function isComplete(int $build): bool
    {
        return count($this->missingFiles($build)) === 0;
    }

    /**
     * Files which are missing from an extracted build.
     *
     * @param  int  $build
     * @return string[]
     */
    public function missingFiles(int $build): array
    {
        $directory = rtrim($this->directory($build), '/');

        return array_values(array_filter(
            self::ARCHIVE_FILES,
            fn ($file) => ! file_exists($directory . '/' . $file)
        ));
    }

    /**
     * Download the archive for a build and extract the files the import reads.
     *
     * @param  int       $build
     * @param  callable  $message  Receives progress messages.
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function download(int $build, ?callable $message = null): bool
    {
        $message = $message ?: function (string $line) {
        };

        $url = sprintf(
            'https://developers.eveonline.com/static-data/tranquility/eve-online-static-data-%d-jsonl.zip', $build);

        $archive = $this->storage_path . sprintf('eve-online-static-data-%d-jsonl.zip', $build);

        if (! File::isDirectory($this->storage_path))
            File::makeDirectory($this->storage_path, 0755, true);

        $message(sprintf('Downloading CCP static data build %d ...', $build));

        $client = new Client(['timeout' => 600, 'connect_timeout' => 30]);
        $client->get($url, ['sink' => $archive]);

        $extracted = $this->extract($archive, $this->directory($build), $message);

        // The archive is only a transport, the extracted files are the cache.
        if (file_exists($archive))
            unlink($archive);

        $missing = $this->missingFiles($build);

        if (count($missing) > 0)
            throw new RuntimeException(
                'CCP static data archive did not contain: ' . implode(', ', $missing));

        return $extracted;
    }

    /**
     * Extract the needed entries of a zip archive.
     *
     * @param  string    $archive
     * @param  string    $directory
     * @param  callable  $message
     * @return bool
     */
    protected function extract(string $archive, string $directory, callable $message): bool
    {
        $zip = new ZipArchive();

        if ($zip->open($archive) !== true)
            throw new RuntimeException(sprintf('Unable to open %s', $archive));

        if (! File::isDirectory($directory))
            File::makeDirectory($directory, 0755, true);

        $message(sprintf('Extracting %d files to %s ...', count(self::ARCHIVE_FILES), $directory));

        foreach (self::ARCHIVE_FILES as $file) {
            // The jsonl entries live at the root of the archive.
            if ($zip->locateName($file) === false)
                continue;

            $zip->extractTo($directory, $file);
        }

        $zip->close();

        return true;
    }
}
