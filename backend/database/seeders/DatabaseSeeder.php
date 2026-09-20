<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Static PH-localized seeds only. No Faker, no factories (specs/12). Idempotent via firstOrCreate. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TeamUserSeeder::class,
            CrmCoreSeeder::class,
            CrmActivitySeeder::class,
            SettingsSeeder::class,
            InsightsCacheSeeder::class,
        ]);
    }
}
