<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeArea extends Model
{
    use HasFactory, Filterable;

    protected $fillable = ['name', 'slug', 'description'];
}
