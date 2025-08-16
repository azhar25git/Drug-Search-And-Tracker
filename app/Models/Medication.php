<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medication extends Model
{
    protected $fillable = ['rxcui'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
