<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['space_id', 'name'];

    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    public function presences()
    {
        return $this->hasMany(UserPresence::class);
    }
}
