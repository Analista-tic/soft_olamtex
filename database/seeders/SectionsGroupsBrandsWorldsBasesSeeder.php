<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectionsGroupsBrandsWorldsBasesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insert data into sections table
        DB::table('sections')->insert([
            ['name' => 'Section 1'],
            ['name' => 'BERMUDA'],
            ['name' => 'BODY'],
            ['name' => 'BUZO'],
            ['name' => 'CAMISETA'],
            ['name' => 'CAPRY'],
            ['name' => 'CARDIGAN'],
            ['name' => 'CHALECO'],
            ['name' => 'CHAQUETA'],
            ['name' => 'CONJUNTO'],
            ['name' => 'ENTERIZO'],
            ['name' => 'FALDA'],
            ['name' => 'JOGGER'],
            ['name' => 'LEGGIN'],
            ['name' => 'OVEROL'],
            ['name' => 'PANTALON'],
            ['name' => 'SHORT'],
            ['name' => 'TOP'],
            ['name' => 'VESTIDO'],

        ]);

        // Insert data into groups table
        DB::table('groups')->insert([
            ['name' => 'LINEA'],
            ['name' => 'PRONTA MODA'],
        ]);

        // Insert data into brands table
        DB::table('brands')->insert([
            ['name' => 'EVA CASTILLO'],
            ['name' => 'AMERICAN EAST'],
            ['name' => 'HERPO'],
            ['name' => 'PASSARO'],
            ['name' => 'LOHARY'],
            ['name' => 'ATUENDOS'],
            ['name' => 'LAXO'],
        ]);

        // Insert data into worlds table
        DB::table('worlds')->insert([
            ['name' => 'World 1'],
            ['name' => 'DAMA'],
            ['name' => 'HOMBRE'],
            ['name' => 'JUNIOR'],
            ['name' => 'NIÑO'],
            ['name' => 'NIÑA'],
            ['name' => 'TEEN'],
        ]);

        // Insert data into bases table
        DB::table('bases')->insert([
            ['name' => 'BURDA'],
            ['name' => 'ALGODON'],
            ['name' => 'ANTIFLUIDO'],
            ['name' => 'BENGALINA'],
            ['name' => 'LYCRA'],
            ['name' => 'LYCRA ALGODON'],
            ['name' => 'PERCHADA'],
            ['name' => 'RIB'],
            ['name' => 'PUNTO ROMA'],
            ['name' => 'POLY LYCRA'],
            ['name' => 'FRANELA'],
            ['name' => 'DRILL'],
            ['name' => 'TWILL'],
            ['name' => 'PANA'],
            ['name' => 'LAME'],
            ['name' => 'MICRO CONFORT'],
            ['name' => 'PIEL DURAZNO'],
        ]);
    }
}
