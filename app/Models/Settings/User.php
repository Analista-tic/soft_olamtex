<?php

namespace App\Models\Settings;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements \App\Contracts\HasAuditLabel
{
    use HasFactory, Notifiable, HasRoles;
    use \App\Models\Concerns\HasAuditLabel;

    protected $fillable = [
        'name',
        'email',
        'area_id',
        'password',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return UserFactory::new();
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}