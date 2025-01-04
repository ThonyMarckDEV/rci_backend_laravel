<?php

// app/Models/Stock.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    protected $table = 'stock';
    protected $primaryKey = 'idStock';

    public $timestamps = false;

    protected $fillable = [
        'idModelo', // Clave foránea hacia modelos
        'cantidad',
    ];

    // Relación de muchos a uno hacia Modelo
    public function modelo()
    {
        return $this->belongsTo(Modelo::class, 'idModelo', 'idModelo');
    }
}