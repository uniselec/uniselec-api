<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionCategory extends Model
{
    protected $table = 'admission_categories';
    use HasFactory, Filterable;

    protected $fillable = ['name', 'description'];
}
