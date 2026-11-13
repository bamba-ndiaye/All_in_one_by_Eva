<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['cle', 'valeur'])]
class Parametre extends Model
{
    protected $table = 'parametres';

    protected $primaryKey = 'cle';

    public $incrementing = false;

    protected $keyType = 'string';
}
