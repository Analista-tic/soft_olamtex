<?php

namespace App\Models\Textil;

use Illuminate\Database\Eloquent\Model;

class FabricProvider extends Model
{
	protected $fillable = [
		'name',
		'contact',
		'email',
		'phone',
		'address',
		'status',
	];

	public function fabricOrders()
	{
		return $this->hasMany(FabricOrder::class, 'fabric_provider_id');
	}
}
