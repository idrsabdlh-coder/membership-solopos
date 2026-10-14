<?php
// app/Models/EpaperEdition.php
namespace App\Models;

use App\Enums\EditionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EpaperEdition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => EditionStatus::class,
            'publish_date' => 'date',
            'publish_end_date' => 'date',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EditionStatus::Published);
    }

    // Aturan inti PRD: edisi berhak diakses bila rentang tanggalnya
    // beririsan dengan masa aktif langganan.
    public function scopeOverlapping(Builder $query, $from, $to): Builder
    {
        return $query->whereDate('publish_date', '<=', $to)
            ->whereDate('publish_end_date', '>=', $from);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(EpaperPage::class, 'edition_id');
    }
}