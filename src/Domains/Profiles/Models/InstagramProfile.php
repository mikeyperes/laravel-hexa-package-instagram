<?php

namespace hexa_package_instagram\Domains\Profiles\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramProfile extends Model
{
    protected $table = 'instagram_profiles';

    protected $fillable = ['slug', 'source', 'source_key', 'notes', 'catalog', 'synced_at', 'last_error'];

    protected $casts = ['catalog' => 'array', 'synced_at' => 'datetime'];
}
