<?php

namespace App\Modules\Shared\Books;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Invitación pendiente a una contabilidad para un correo aún no registrado. */
class BookInvitation extends Model
{
    protected $fillable = ['book_id', 'email', 'invited_by'];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
