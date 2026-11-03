<?php

namespace App\Models\Concerns;

/**
 * Archivage : l'élément quitte les listes courantes mais reste intact (et compté dans les états financiers).
 * Colonnes : archived_at (date) et archived_by (utilisateur).
 */
trait Archivable
{
    public function initializeArchivable(): void
    {
        $this->mergeCasts(['archived_at' => 'datetime']);
    }

    public function scopeNonArchive($query)
    {
        return $query->whereNull($this->getTable() . '.archived_at');
    }

    public function scopeArchives($query)
    {
        return $query->whereNotNull($this->getTable() . '.archived_at');
    }

    public function getEstArchiveAttribute(): bool
    {
        return $this->archived_at !== null;
    }
}
