<?php

// app/Models/Producto.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';
    protected $primaryKey = 'idProducto';

    public $timestamps = false;

    protected $fillable = [
        'nombreProducto',
        'descripcion',
        'precio',
        'idCategoria', // Clave foránea hacia la tabla categorias
        'estado'
    ];

    // Relación de muchos a uno hacia Categoria
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'idCategoria', 'idCategoria');
    }

     // Relación de uno a muchos con Modelos
     public function modelos()
     {
         return $this->hasMany(Modelo::class, 'idProducto', 'idProducto');
     }


    public function stocks()
    {
        return $this->hasManyThrough(
            Stock::class, 
            Modelo::class,
            'idProducto', // Clave foránea de Producto en Modelo
            'idModelo',   // Clave foránea de Modelo en Stock
            'idProducto', // Clave primaria de Producto
            'idModelo'    // Clave primaria de Modelo
        );
    }


}
