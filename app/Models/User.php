<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'exam_track', 'managed_tracks', 'login_scope'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'managed_tracks' => 'array'];
    }

    public function canManageTrack(string $track): bool
    {
        return $this->role === 'admin' || ($this->role === 'teacher' && in_array($track, $this->managed_tracks ?? ['toeic'], true));
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'teacher'], true);
    }
}
