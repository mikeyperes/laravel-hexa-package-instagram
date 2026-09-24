<?php

namespace hexa_package_instagram\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A story or feed post published through the package. Status: live, expired (story past 24 hours),
 * removed (deleted through the package) or gone (no longer on Instagram, removed elsewhere).
 */
class InstagramPublication extends Model
{
    protected $table = 'instagram_publications';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'posted_at' => 'datetime',
        'expires_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    /** Instagram's full media id (<pk>_<owner id>), used by its web data calls. */
    public function ownerId(): string
    {
        return (string) ($this->meta['owner_id'] ?? '');
    }
}
