<?php
// app/Models/EpaperPage.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpaperPage extends Model
{
    protected $guarded = [];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(EpaperEdition::class, 'edition_id');
    }
}