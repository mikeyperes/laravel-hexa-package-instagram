<?php
namespace hexa_package_instagram\Actions\Models;
use Illuminate\Database\Eloquent\Model;
final class StoredResult extends Model
{
    protected $table = 'instagram_action_results';
    protected $guarded = ['id'];
    protected $casts = ['record' => 'array', 'performed_at' => 'immutable_datetime'];
}
