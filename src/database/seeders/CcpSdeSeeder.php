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

namespace Seat\Eveapi\Database\Seeders;

use Illuminate\Database\Seeder;
use Seat\Eveapi\Database\Seeders\Sde\AbstractSdeSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\ChrFactionsSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\DgmTypeAttributesSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\DgmTypeEffectsSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\InvCategoriesSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\InvContrabandTypesSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\InvControlTowerResourcesSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\InvGroupsSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\InvMarketGroupsSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\InvMetaGroupsSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\InvMetaTypesSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\InvTypeMaterialsSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\InvTypesSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\MapDenormalizeSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\RamActivitiesSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\RetiredSdeTablesSeeder;
use Seat\Eveapi\Database\Seeders\Sde\Ccp\StaStationsSeeder;

/**
 * Class CcpSdeSeeder.
 *
 * Used as a facade to seed all SDE related tables from a CCP jsonl dump.
 *
 * The dump is expected to be extracted already and every table is created by
 * its own seeder. The order below is the order the tables depend on each other:
 * types and groups before the tables which reference them, the map before the
 * npc stations which take their name and security from it.
 *
 * @package Seat\Eveapi\Database\Seeders
 */
class CcpSdeSeeder extends Seeder
{
    /**
     * Seeders in the order their tables depend on each other.
     *
     * @var string[]
     */
    private const SEEDERS = [
        InvTypesSeeder::class,
        InvGroupsSeeder::class,
        InvCategoriesSeeder::class,
        InvMarketGroupsSeeder::class,
        InvMetaGroupsSeeder::class,
        InvMetaTypesSeeder::class,
        ChrFactionsSeeder::class,
        InvContrabandTypesSeeder::class,
        InvControlTowerResourcesSeeder::class,
        DgmTypeAttributesSeeder::class,
        DgmTypeEffectsSeeder::class,
        InvTypeMaterialsSeeder::class,
        RamActivitiesSeeder::class,
        MapDenormalizeSeeder::class,
        StaStationsSeeder::class,
        RetiredSdeTablesSeeder::class,
    ];

    /**
     * Directory the extracted jsonl dump is read from.
     *
     * @var string
     */
    protected string $directory;

    /**
     * Build number the dump belongs to.
     *
     * @var int
     */
    protected int $build;

    /**
     * @param string $directory
     * @param int    $build
     */
    public function __construct(string $directory, int $build = 0)
    {
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $this->build = $build;
    }

    /**
     * Seed every SDE table from the extracted dump.
     *
     * @return void
     *
     * @throws \RuntimeException when the dump is incomplete
     */
    public function run(): void
    {
        $missing = $this->missingFiles();

        if (count($missing) > 0)
            throw new \RuntimeException(sprintf(
                'The SDE dump in %1$s is incomplete, missing: %2$s.',
                $this->directory,
                implode(', ', $missing)));

        AbstractSdeSeeder::setSourceDirectory($this->directory);
        MapDenormalizeSeeder::setSourceDirectory($this->directory);

        $this->command->info(sprintf(
            'Seeding SDE build %s from %s', $this->build, $this->directory));

        foreach (self::SEEDERS as $seeder) {
            $instance = new $seeder();
            $instance->setCommand($this->command);
            $instance->run();
        }

        AbstractSdeSeeder::setSourceDirectory(null);
        MapDenormalizeSeeder::setSourceDirectory(null);
    }

    /**
     * Dump files the seeders need which are not present.
     *
     * @return string[]
     */
    public function missingFiles(): array
    {
        $missing = [];

        foreach (self::SEEDERS as $seeder) {
            if (! is_subclass_of($seeder, AbstractSdeSeeder::class))
                continue;

            $filename = $seeder::getSdeFilename();

            if ($filename !== '' && ! file_exists($this->directory . DIRECTORY_SEPARATOR . $filename))
                $missing[] = $filename;
        }

        return $missing;
    }
}
