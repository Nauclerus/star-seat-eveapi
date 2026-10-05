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


namespace Seat\Eveapi\Database\Seeders\Sde\Ccp;

use Illuminate\Database\Seeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Class MapDenormalizeSeeder.
 *
 * CCP retired the single map denormalize dump. The rows are published per
 * object type instead, so this seeder assembles the table from the map files
 * and the npc station dump.
 *
 * The columns keep the names every reader of this table expects: a moon stores
 * its parent planet in orbitID and that planet's number in celestialIndex, and
 * itemName holds the display name the retired table used to publish.
 *
 * @package Seat\Eveapi\Database\Seeders\Sde\Ccp
 */
class MapDenormalizeSeeder extends Seeder
{
    protected const BATCH_SIZE = 2000;

    /**
     * Groups published by the map dumps.
     */
    protected const GROUP_REGION = 3;
    protected const GROUP_CONSTELLATION = 4;
    protected const GROUP_SYSTEM = 5;
    protected const GROUP_SUN = 6;
    protected const GROUP_PLANET = 7;
    protected const GROUP_MOON = 8;
    protected const GROUP_BELT = 9;
    protected const GROUP_STARGATE = 10;
    protected const GROUP_STATION = 15;
    protected const GROUP_SECONDARY_SUN = 995;

    /**
     * Types the retired table used for regions, constellations and systems.
     */
    protected const TYPE_REGION = 3;
    protected const TYPE_CONSTELLATION = 4;
    protected const TYPE_SYSTEM = 5;

    /**
     * Secondary suns were published with a security of zero.
     */
    protected const SECONDARY_SUN_SECURITY = 0;

    /**
     * Directory the map dumps are read from.
     *
     * @var string|null
     */
    protected static ?string $source_directory = null;

    /**
     * solarSystemID => name, constellationID, regionID, security, starID.
     *
     * @var array
     */
    protected array $systems = [];

    /**
     * itemID => display name of a body a station orbits.
     *
     * @var array
     */
    protected array $station_bodies = [];

    /**
     * npcCorporationID => name.
     *
     * @var array
     */
    protected array $corporations = [];

    /**
     * operationID => operation name.
     *
     * @var array
     */
    protected array $operations = [];

    /**
     * npcStations.jsonl rows keyed by stationID.
     *
     * @var array
     */
    protected array $stations = [];

    /**
     * Set the directory the map dumps are read from.
     *
     * @param  string|null  $directory
     * @return void
     */
    public static function setSourceDirectory(?string $directory): void
    {
        static::$source_directory = $directory;
    }

    /**
     * Build the map denormalize table from the map dumps.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::dropIfExists('mapDenormalize');
            Schema::create('mapDenormalize', function (Blueprint $table) {
                $table->integer('itemID');
                $table->integer('typeID')->nullable();
                $table->integer('groupID')->nullable();
                $table->integer('solarSystemID')->nullable();
                $table->integer('constellationID')->nullable();
                $table->integer('regionID')->nullable();
                $table->integer('orbitID')->nullable();
                $table->double('x')->nullable();
                $table->double('y')->nullable();
                $table->double('z')->nullable();
                $table->double('radius')->nullable();
                $table->string('itemName', 100)->nullable();
                $table->double('security')->nullable();
                $table->integer('celestialIndex')->nullable();
                $table->integer('orbitIndex')->nullable();

                $table->primary('itemID');
                $table->index('orbitID', 'ix_mapDenormalize_orbitID');
                $table->index('solarSystemID', 'ix_mapDenormalize_solarSystemID');
                $table->index('constellationID', 'ix_mapDenormalize_constellationID');
                $table->index('typeID', 'ix_mapDenormalize_typeID');
                $table->index('regionID', 'ix_mapDenormalize_regionID');
                $table->index(
                    ['groupID', 'solarSystemID'], 'ix_mapDenormalize_groupID_solarSystemID');
                $table->index(
                    ['groupID', 'constellationID'], 'ix_mapDenormalize_groupID_constellationID');
                $table->index(
                    ['groupID', 'regionID'], 'ix_mapDenormalize_groupID_regionID');
            });

            $this->loadLookups();

            $bar = $this->command->getOutput()->createProgressBar($this->expectedRows());
            $bar->setFormat(
                ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% %memory:6s%');

            $this->seedRegions($bar);
            $this->seedConstellations($bar);
            $this->seedSolarSystems($bar);
            $this->seedSuns($bar);
            $this->seedPlanets($bar);
            $this->seedMoons($bar);
            $this->seedAsteroidBelts($bar);
            $this->seedStargates($bar);
            $this->seedStations($bar);
            $this->seedSecondarySuns($bar);

            $bar->finish();
            $this->command->getOutput()->newLine();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Read the dumps which describe where an object is and how it is called.
     *
     * @return void
     */
    protected function loadLookups(): void
    {
        foreach ($this->read('mapSolarSystems.jsonl') as $row)
            $this->systems[$row['_key']] = [
                'name' => $this->localized($row['name'] ?? null),
                'constellationID' => $row['constellationID'] ?? null,
                'regionID' => $row['regionID'] ?? null,
                'security' => $row['securityStatus'] ?? null,
                'starID' => $row['starID'] ?? null,
            ];

        foreach ($this->read('npcCorporations.jsonl') as $row)
            $this->corporations[$row['_key']] = $this->localized($row['name'] ?? null);

        foreach ($this->read('stationOperations.jsonl') as $row)
            $this->operations[$row['_key']] = $this->localized($row['operationName'] ?? null);

        foreach ($this->read('npcStations.jsonl') as $row) {
            $this->stations[$row['_key']] = $row;

            // Only the bodies a station actually orbits are needed for names.
            if (isset($row['orbitID']))
                $this->station_bodies[$row['orbitID']] = null;
        }
    }

    /**
     * Rows the seeded files are expected to produce.
     *
     * @return int
     */
    protected function expectedRows(): int
    {
        $files = [
            'mapRegions.jsonl', 'mapConstellations.jsonl', 'mapSolarSystems.jsonl',
            'mapStars.jsonl', 'mapPlanets.jsonl', 'mapMoons.jsonl',
            'mapAsteroidBelts.jsonl', 'mapStargates.jsonl', 'npcStations.jsonl',
            'mapSecondarySuns.jsonl',
        ];

        $rows = 0;

        foreach ($files as $file) {
            $handle = fopen($this->sourcePath($file), 'r');

            if (! $handle)
                continue;

            while (($line = fgets($handle)) !== false)
                if (trim($line) !== '')
                    $rows++;

            fclose($handle);
        }

        return $rows;
    }

    /**
     * Seed regions.
     */
    protected function seedRegions($bar): void
    {
        $this->seedFile('mapRegions.jsonl', function (array $row) {
            return $this->position($row) + [
                'itemID' => $row['_key'],
                'typeID' => self::TYPE_REGION,
                'groupID' => self::GROUP_REGION,
                'solarSystemID' => null,
                'constellationID' => null,
                'regionID' => $row['_key'],
                'orbitID' => null,
                'radius' => null,
                'itemName' => $this->localized($row['name'] ?? null),
                'security' => null,
                'celestialIndex' => null,
                'orbitIndex' => null,
            ];
        }, $bar);
    }

    /**
     * Seed constellations.
     */
    protected function seedConstellations($bar): void
    {
        $this->seedFile('mapConstellations.jsonl', function (array $row) {
            return $this->position($row) + [
                'itemID' => $row['_key'],
                'typeID' => self::TYPE_CONSTELLATION,
                'groupID' => self::GROUP_CONSTELLATION,
                'solarSystemID' => null,
                'constellationID' => $row['_key'],
                'regionID' => $row['regionID'] ?? null,
                'orbitID' => null,
                'radius' => null,
                'itemName' => $this->localized($row['name'] ?? null),
                'security' => null,
                'celestialIndex' => null,
                'orbitIndex' => null,
            ];
        }, $bar);
    }

    /**
     * Seed solar systems.
     */
    protected function seedSolarSystems($bar): void
    {
        $this->seedFile('mapSolarSystems.jsonl', function (array $row) {
            return $this->position($row) + [
                'itemID' => $row['_key'],
                'typeID' => self::TYPE_SYSTEM,
                'groupID' => self::GROUP_SYSTEM,
                'solarSystemID' => $row['_key'],
                'constellationID' => $row['constellationID'] ?? null,
                'regionID' => $row['regionID'] ?? null,
                'orbitID' => null,
                'radius' => $row['radius'] ?? null,
                'itemName' => $this->localized($row['name'] ?? null),
                'security' => $row['securityStatus'] ?? null,
                'celestialIndex' => null,
                'orbitIndex' => null,
            ];
        }, $bar);
    }

    /**
     * Seed the primary sun of every system.
     */
    protected function seedSuns($bar): void
    {
        $this->seedFile('mapStars.jsonl', function (array $row) {
            $system = $this->systems[$row['solarSystemID'] ?? 0] ?? null;

            return [
                'x' => 0,
                'y' => 0,
                'z' => 0,
                'itemID' => $row['_key'],
                'typeID' => $row['typeID'] ?? null,
                'groupID' => self::GROUP_SUN,
                'solarSystemID' => $row['solarSystemID'] ?? null,
                'constellationID' => $system['constellationID'] ?? null,
                'regionID' => $system['regionID'] ?? null,
                'orbitID' => null,
                'radius' => $row['radius'] ?? null,
                'itemName' => $system['name'] ?? null,
                'security' => $system['security'] ?? null,
                'celestialIndex' => null,
                'orbitIndex' => null,
            ];
        }, $bar);
    }

    /**
     * Seed planets.
     */
    protected function seedPlanets($bar): void
    {
        $this->seedFile('mapPlanets.jsonl', function (array $row) {
            $name = $this->bodyName($row, $row['celestialIndex'] ?? null, null);

            $this->rememberStationBody($row['_key'], $name);

            return $this->position($row) + [
                'itemID' => $row['_key'],
                'typeID' => $row['typeID'] ?? null,
                'groupID' => self::GROUP_PLANET,
                'solarSystemID' => $row['solarSystemID'] ?? null,
                'constellationID' => $this->constellationOf($row['solarSystemID'] ?? null),
                'regionID' => $this->regionOf($row['solarSystemID'] ?? null),
                'orbitID' => $row['orbitID'] ?? null,
                'radius' => $row['radius'] ?? null,
                'itemName' => $name,
                'security' => $this->securityOf($row['solarSystemID'] ?? null),
                'celestialIndex' => $row['celestialIndex'] ?? null,
                'orbitIndex' => null,
            ];
        }, $bar);
    }

    /**
     * Seed moons.
     */
    protected function seedMoons($bar): void
    {
        $this->seedFile('mapMoons.jsonl', function (array $row) {
            // The retired table never used a moon's uniqueName; it numbered the
            // moon after its system and planet like any other.
            $name = $this->bodyName(
                $row, $row['celestialIndex'] ?? null, $row['orbitIndex'] ?? null, null, false);

            $this->rememberStationBody($row['_key'], $name);

            return $this->position($row) + [
                'itemID' => $row['_key'],
                'typeID' => $row['typeID'] ?? null,
                'groupID' => self::GROUP_MOON,
                'solarSystemID' => $row['solarSystemID'] ?? null,
                'constellationID' => $this->constellationOf($row['solarSystemID'] ?? null),
                'regionID' => $this->regionOf($row['solarSystemID'] ?? null),
                'orbitID' => $row['orbitID'] ?? null,
                'radius' => $row['radius'] ?? null,
                'itemName' => $name,
                'security' => $this->securityOf($row['solarSystemID'] ?? null),
                'celestialIndex' => $row['celestialIndex'] ?? null,
                'orbitIndex' => $row['orbitIndex'] ?? null,
            ];
        }, $bar);
    }

    /**
     * Seed asteroid belts.
     */
    protected function seedAsteroidBelts($bar): void
    {
        $this->seedFile('mapAsteroidBelts.jsonl', function (array $row) {
            $name = $this->bodyName(
                $row, $row['celestialIndex'] ?? null, $row['orbitIndex'] ?? null,
                'Asteroid Belt');

            $this->rememberStationBody($row['_key'], $name);

            return $this->position($row) + [
                'itemID' => $row['_key'],
                'typeID' => $row['typeID'] ?? null,
                'groupID' => self::GROUP_BELT,
                'solarSystemID' => $row['solarSystemID'] ?? null,
                'constellationID' => $this->constellationOf($row['solarSystemID'] ?? null),
                'regionID' => $this->regionOf($row['solarSystemID'] ?? null),
                'orbitID' => $row['orbitID'] ?? null,
                'radius' => $row['radius'] ?? null,
                'itemName' => $name,
                'security' => $this->securityOf($row['solarSystemID'] ?? null),
                'celestialIndex' => $row['celestialIndex'] ?? null,
                'orbitIndex' => $row['orbitIndex'] ?? null,
            ];
        }, $bar);
    }

    /**
     * Seed stargates. A gate is named after the system it leads to.
     */
    protected function seedStargates($bar): void
    {
        $this->seedFile('mapStargates.jsonl', function (array $row) {
            $destination = $row['destination']['solarSystemID'] ?? null;
            $name = sprintf('Stargate (%s)', $this->systemName($destination));

            return $this->position($row) + [
                'itemID' => $row['_key'],
                'typeID' => $row['typeID'] ?? null,
                'groupID' => self::GROUP_STARGATE,
                'solarSystemID' => $row['solarSystemID'] ?? null,
                'constellationID' => $this->constellationOf($row['solarSystemID'] ?? null),
                'regionID' => $this->regionOf($row['solarSystemID'] ?? null),
                'orbitID' => null,
                'radius' => null,
                'itemName' => $name,
                'security' => $this->securityOf($row['solarSystemID'] ?? null),
                'celestialIndex' => null,
                'orbitIndex' => null,
            ];
        }, $bar);
    }

    /**
     * Seed npc stations.
     */
    protected function seedStations($bar): void
    {
        $this->seedFile('npcStations.jsonl', function (array $row) {
            return $this->position($row) + [
                'itemID' => $row['_key'],
                'typeID' => $row['typeID'] ?? null,
                'groupID' => self::GROUP_STATION,
                'solarSystemID' => $row['solarSystemID'] ?? null,
                'constellationID' => $this->constellationOf($row['solarSystemID'] ?? null),
                'regionID' => $this->regionOf($row['solarSystemID'] ?? null),
                'orbitID' => $row['orbitID'] ?? null,
                'radius' => null,
                'itemName' => $this->stationName($row),
                'security' => $this->securityOf($row['solarSystemID'] ?? null),
                'celestialIndex' => $row['celestialIndex'] ?? null,
                'orbitIndex' => $row['orbitIndex'] ?? null,
            ];
        }, $bar);
    }

    /**
     * Seed the secondary suns of a system.
     *
     * These were published as anomalies without a name, so the type name is
     * used instead.
     */
    protected function seedSecondarySuns($bar): void
    {
        $rows = [];

        foreach ($this->read('mapSecondarySuns.jsonl') as $row)
            $rows[] = $row;

        $names = DB::table('invTypes')
            ->whereIn('typeID', array_unique(array_column($rows, 'typeID')))
            ->pluck('typeName', 'typeID')
            ->all();

        $records = [];

        foreach ($rows as $row) {
            $records[] = $this->position($row) + [
                'itemID' => $row['_key'],
                'typeID' => $row['typeID'] ?? null,
                'groupID' => self::GROUP_SECONDARY_SUN,
                'solarSystemID' => $row['solarSystemID'] ?? null,
                'constellationID' => $this->constellationOf($row['solarSystemID'] ?? null),
                'regionID' => $this->regionOf($row['solarSystemID'] ?? null),
                'orbitID' => null,
                'radius' => null,
                'itemName' => $names[$row['typeID'] ?? 0] ?? null,
                'security' => self::SECONDARY_SUN_SECURITY,
                'celestialIndex' => null,
                'orbitIndex' => null,
            ];

            $bar->advance();
        }

        $this->insert($records);
    }

    /**
     * Seed one dump file using a closure which builds a row.
     *
     * @param  string    $filename
     * @param  callable  $build
     * @param  \Symfony\Component\Console\Helper\ProgressBar  $bar
     * @return void
     */
    protected function seedFile(string $filename, callable $build, $bar): void
    {
        $records = [];

        foreach ($this->read($filename) as $row) {
            $records[] = $build($row);

            if (count($records) >= self::BATCH_SIZE) {
                $this->insert($records);
                $records = [];
            }

            $bar->advance();
        }

        if (count($records) > 0)
            $this->insert($records);
    }

    /**
     * Insert a chunk of rows.
     *
     * @param  array  $records
     * @return void
     */
    protected function insert(array $records): void
    {
        if (count($records) === 0)
            return;

        DB::table('mapDenormalize')->insert($records);
    }

    /**
     * Read the json lines of a dump file.
     *
     * @param  string  $filename
     * @return \Generator
     *
     * @throws \RuntimeException
     */
    protected function read(string $filename): \Generator
    {
        $path = $this->sourcePath($filename);

        if (! file_exists($path))
            throw new \RuntimeException(sprintf('Unable to retrieve %s.', $path));

        $handle = fopen($path, 'r');

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '')
                    continue;

                $data = json_decode($line, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->command->error(sprintf('Invalid JSON in %s', $filename));
                    continue;
                }

                yield $data;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Path of a dump file.
     *
     * @param  string  $filename
     * @return string
     */
    protected function sourcePath(string $filename): string
    {
        if (! is_null(static::$source_directory))
            return rtrim(static::$source_directory, DIRECTORY_SEPARATOR) .
                DIRECTORY_SEPARATOR . $filename;

        return storage_path(sprintf('sde/%s', $filename));
    }

    /**
     * x, y and z of a dump row.
     *
     * @param  array  $row
     * @return array
     */
    protected function position(array $row): array
    {
        return [
            'x' => $row['position']['x'] ?? null,
            'y' => $row['position']['y'] ?? null,
            'z' => $row['position']['z'] ?? null,
        ];
    }

    /**
     * English value of a localized dump field.
     *
     * @param  mixed  $value
     * @return string|null
     */
    protected function localized($value): ?string
    {
        if (is_array($value))
            return $value['en'] ?? null;

        return is_null($value) ? null : (string) $value;
    }

    /**
     * Display name of a planet, moon or belt.
     *
     * CCP names only the bodies which have a real name, the rest are numbered
     * after their system the way the retired table did. The retired table used
     * a body's uniqueName for planets and belts, but numbered moons after their
     * system and planet even when the dump carried a uniqueName for them.
     *
     * @param  array       $row
     * @param  int|null    $celestialIndex
     * @param  int|null    $orbitIndex
     * @param  string|null $orbitLabel
     * @param  bool        $allowUniqueName
     * @return string|null
     */
    protected function bodyName(
        array $row, ?int $celestialIndex, ?int $orbitIndex,
        ?string $orbitLabel = null, bool $allowUniqueName = true): ?string
    {
        if ($allowUniqueName) {

            $unique = $this->localized($row['uniqueName'] ?? null);

            if (! is_null($unique))
                return $unique;
        }

        $system = $this->systems[$row['solarSystemID'] ?? 0] ?? null;

        if (is_null($system))
            return null;

        $name = sprintf('%s %s', $system['name'], $celestialIndex);

        if (! is_null($orbitIndex))
            $name .= sprintf(' - %s %s', $orbitLabel ?? 'Moon', $orbitIndex);

        return $name;
    }

    /**
     * Remember the name of a body a station orbits.
     *
     * @param  int    $itemID
     * @param  string  $name
     * @return void
     */
    protected function rememberStationBody(int $itemID, ?string $name): void
    {
        if (array_key_exists($itemID, $this->station_bodies))
            $this->station_bodies[$itemID] = $name;
    }

    /**
     * Display name of a npc station.
     *
     * @param  array  $row
     * @return string|null
     */
    protected function stationName(array $row): ?string
    {
        $name = $this->station_bodies[$row['orbitID'] ?? 0] ?? null;

        // A station which is not orbiting a body sits at the system itself.
        if (is_null($name)) {
            $system = $this->systems[$row['solarSystemID'] ?? 0] ?? null;
            $name = $system['name'] ?? null;
        }

        if (is_null($name))
            return null;

        $corporation = $this->corporations[$row['ownerID'] ?? 0] ?? '';

        // Without an operation the corporation still names the station.
        if (($row['useOperationName'] ?? true) === false)
            return $corporation === '' ? $name : sprintf('%s - %s', $name, $corporation);

        $operation = $this->operations[$row['operationID'] ?? 0] ?? '';

        return sprintf('%s - %s %s', $name, $corporation, $operation);
    }

    /**
     * Constellation a solar system belongs to.
     *
     * @param  int|null  $solarSystemID
     * @return int|null
     */
    protected function constellationOf(?int $solarSystemID): ?int
    {
        return $this->systems[$solarSystemID ?? 0]['constellationID'] ?? null;
    }

    /**
     * Region a solar system belongs to.
     *
     * @param  int|null  $solarSystemID
     * @return int|null
     */
    protected function regionOf(?int $solarSystemID): ?int
    {
        return $this->systems[$solarSystemID ?? 0]['regionID'] ?? null;
    }

    /**
     * Security of a solar system.
     *
     * @param  int|null  $solarSystemID
     * @return float|null
     */
    protected function securityOf(?int $solarSystemID): ?float
    {
        $security = $this->systems[$solarSystemID ?? 0]['security'] ?? null;

        return is_null($security) ? null : (float) $security;
    }

    /**
     * Name of a solar system.
     *
     * @param  int|null  $solarSystemID
     * @return string
     */
    protected function systemName(?int $solarSystemID): string
    {
        return $this->systems[$solarSystemID ?? 0]['name'] ?? '';
    }
}
