<?php
// database/migrations/2024_01_01_000001_create_documents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default documents
        $this->seedDocuments();
    }

    private function seedDocuments()
    {
        $documents = [
            ['code' => '1020', 'name' => 'Employment Application'],
            ['code' => '1021', 'name' => 'Equal Employment Opportunity'],
            ['code' => '1050', 'name' => 'Skills Checklist'],
            ['code' => '1060', 'name' => 'Request for Reference'],
            ['code' => '1070', 'name' => 'Background Check Authorization'],
            ['code' => '1204', 'name' => 'Care Associate Availability'],
            ['code' => '1010', 'name' => 'Employee Personal Action'],
            ['code' => '1201', 'name' => 'Handbook Acknowledgement'],
            ['code' => '1202', 'name' => 'Orientation Acknowledgement'],
            ['code' => '1203', 'name' => 'Orientation Curriculum'],
            ['code' => '1220', 'name' => 'Abuse_Neglect Policy'],
            ['code' => '1530', 'name' => 'Care Associate Schedule Acknowledgement'],
            ['code' => '1600', 'name' => 'Emergency Contact Information'],
            ['code' => '1720', 'name' => 'Hepatitis B_Consent-Declination'],
            ['code' => '1740', 'name' => 'Pre-Employment Drug Consent'],
            ['code' => '2900', 'name' => 'ID Agreement'],
            ['code' => '4000', 'name' => 'Nondiclosure_Noncompete Agreement'],
            ['code' => 'I-9', 'name' => 'I-9 Form'],
            ['code' => 'W-4', 'name' => 'W-4 Form'],
        ];

        foreach ($documents as $document) {
            DB::table('documents')->insert([
                'code' => $document['code'],
                'name' => $document['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('documents');
    }
}