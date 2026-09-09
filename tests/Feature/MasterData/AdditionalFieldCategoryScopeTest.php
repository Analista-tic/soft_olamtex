<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\Settings\Setting;
use App\Models\Settings\User;
use App\Services\MasterData\AdditionalFieldService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA SCOPE TEST']);
    $this->fabricSubGroup = CategorySubGroup::firstOrCreate([
        'name' => 'Tela Scope Test',
        'category_group_id' => $group->id,
    ]);
    $this->buttonSubGroup = CategorySubGroup::firstOrCreate([
        'name' => 'Boton Scope Test',
        'category_group_id' => $group->id,
    ]);

    $user = User::factory()->create();
    foreach (['ver campos adicionales', 'crear campos adicionales', 'editar campos adicionales'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);
});

it('creates an additional field scoped to a category sub group', function () {
    $response = $this->postJson(route('inventory.additional-fields.store'), [
        'name' => 'Ancho de rollo',
        'field_type' => 'text',
        'category_sub_group_ids' => [$this->fabricSubGroup->id],
    ]);

    $response->assertCreated();

    $field = Setting::fields()->where('label', 'Ancho de rollo')->firstOrFail();
    expect($field->categorySubGroups->pluck('id')->all())->toBe([$this->fabricSubGroup->id]);
});

it('does not return a scoped field in the options of an unrelated category sub group', function () {
    $field = app(AdditionalFieldService::class)->create([
        'name' => 'Ancho de rollo',
        'field_type' => 'text',
        'category_sub_group_ids' => [$this->fabricSubGroup->id],
    ]);

    $scoped = app(AdditionalFieldService::class)->options($this->buttonSubGroup->id);
    expect($scoped->pluck('id'))->not->toContain($field->id);

    $matching = app(AdditionalFieldService::class)->options($this->fabricSubGroup->id);
    expect($matching->pluck('id'))->toContain($field->id);
});

it('always returns a global field with no category association', function () {
    $field = app(AdditionalFieldService::class)->create([
        'name' => 'Observaciones generales',
        'field_type' => 'text',
    ]);

    $forFabric = app(AdditionalFieldService::class)->options($this->fabricSubGroup->id);
    $forButton = app(AdditionalFieldService::class)->options($this->buttonSubGroup->id);

    expect($forFabric->pluck('id'))->toContain($field->id)
        ->and($forButton->pluck('id'))->toContain($field->id);
});

it('updates the category sub group association of an existing field', function () {
    $field = app(AdditionalFieldService::class)->create([
        'name' => 'Ancho de rollo',
        'field_type' => 'text',
        'category_sub_group_ids' => [$this->fabricSubGroup->id],
    ]);

    $response = $this->putJson(route('inventory.additional-fields.update', ['additionalField' => $field->id]), [
        'name' => 'Ancho de rollo',
        'field_type' => 'text',
        'category_sub_group_ids' => [$this->buttonSubGroup->id],
    ]);

    $response->assertOk();

    $field->refresh();
    expect($field->categorySubGroups->pluck('id')->all())->toBe([$this->buttonSubGroup->id]);
});

it('exposes the associated category sub group ids through the options endpoint', function () {
    app(AdditionalFieldService::class)->create([
        'name' => 'Ancho de rollo',
        'field_type' => 'text',
        'category_sub_group_ids' => [$this->fabricSubGroup->id],
    ]);

    $response = $this->getJson(route('catalogos.additional-fields.options', ['category_sub_group_id' => $this->fabricSubGroup->id]));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Ancho de rollo');
});
