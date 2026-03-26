<?php

use App\Models\Income\SaleDocument;
use App\Services\Income\SaleDocumentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('finance_document_sequences');

    Schema::create('finance_document_sequences', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('document_type', 80)->unique();
        $table->unsignedBigInteger('current_value')->default(0);
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('finance_document_sequences');
});

it('reserves quote numbers from finance document sequences', function () {
    DB::table('finance_document_sequences')->insert([
        'document_type' => SaleDocument::TYPE_QUOTE,
        'current_value' => 41,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(SaleDocumentService::class);

    expect($service->generateDocumentNumberForType(SaleDocument::TYPE_QUOTE))->toBe('COT-042');
    expect($service->generateDocumentNumberForType(SaleDocument::TYPE_QUOTE))->toBe('COT-043');

    expect(
        (int) DB::table('finance_document_sequences')
            ->where('document_type', SaleDocument::TYPE_QUOTE)
            ->value('current_value')
    )->toBe(43);
});

