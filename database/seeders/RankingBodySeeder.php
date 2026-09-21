<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RankingBodySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('ranking_bodies')->insert([
            ['name' => 'QS World University Rankings', 'short_name' => 'QS'],
            ['name' => 'Times Higher Education', 'short_name' => 'THE'],
            ['name' => 'Center for World University Rankings', 'short_name' => 'CWTS'],
            ['name' => 'Webometrics Ranking', 'short_name' => 'Webometrics'],
            ['name' => 'University Ranking by Academic Performance', 'short_name' => 'URAP'],
            ['name' => 'SCImago Institutions Rankings', 'short_name' => 'SCImago'],
            ['name' => 'The World University Rankings for Innovation', 'short_name' => 'WURI'],
            ['name' => 'AppliedHE', 'short_name' => 'AppliedHE'],
            ['name' => 'AD Scientific Index', 'short_name' => 'AD Scientific Index'],
            ['name' => 'EduRank', 'short_name' => 'EduRank'],
        ]);
    }
}
