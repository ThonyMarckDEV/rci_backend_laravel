<?php

namespace App\Http\Controllers;

use App\Models\ImagenModelo;
use App\Models\Talla;
use App\Models\Usuario;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Log as LogUser;
use Illuminate\Http\Request;
use App\Mail\NotificacionPagoCompletado;
use App\Mail\NotificacionPedidoEliminado;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use FPDF;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use App\Models\Facturacion;
use App\Models\Modelo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;


class SuperAdminController extends Controller
{
    // FUNCION PARA REGISTRAR UN ADMIN
    public function agregarUsuario(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'correo' => 'required|email|unique:usuarios,correo',
            'password' => 'required|string|min:6',
        ]);
        
        // Si la validación falla, retornar errores
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Obtener nombres y apellidos del nuevo usuario
        $nombres = $request->nombres;
        $apellidos = $request->apellidos;

        // Crear el usuario con valores predeterminados
        $user = Usuario::create([
            'rol' => 'admin', // Valor predeterminado
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'correo' => $request->correo,
            'password' => bcrypt($request->password), // Encriptar la contraseña
            'fecha_creado' => now(), // Fecha actual
            'status' => 'loggedOff', // Valor predeterminado
            'estado' => 'activo',
        ]);

        // Obtener el ID del usuario autenticado desde el token
        $usuarioId = auth()->id(); // Obtiene el ID del usuario autenticado

        // Obtener el nombre completo del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;

        // Definir la acción y mensaje para el log
        $accion = "$nombreUsuario agregó un nuevo administrador: $nombres $apellidos con correo: {$request->correo}";

        // Llamada a la función agregarLog para registrar el log
        $this->agregarLog($usuarioId, $accion);
        
        // Retornar una respuesta exitosa
        return response()->json([
            'success' => true,
            'message' => 'Usuario agregado exitosamente',
            'user' => $user,
        ], 201);
    }
        

     // Editar usuario
     public function editarUsuario(Request $request, $id)
     {
         Log::info('Iniciando actualización de usuario', ['usuario_id' => $id, 'data' => $request->all()]);
     
         // Traducción de campos
         $request->merge([
             'correo' => $request->input('email'),
             'rol' => $request->input('role'),
         ]);
     
         $validator = Validator::make($request->all(), [
             'nombres' => 'sometimes|string|max:255',
             'apellidos' => 'sometimes|string|max:255',
             'correo' => 'sometimes|email|unique:usuarios,correo,' . $id . ',idUsuario',
             'password' => 'sometimes|string|min:6',
             'rol' => 'sometimes|string|max:255',
         ]);
     
         if ($validator->fails()) {
             Log::error('Validación fallida', ['errors' => $validator->errors()]);
             return response()->json([
                 'success' => false,
                 'message' => 'Error de validación',
                 'errors' => $validator->errors(),
             ], 422);
         }
     
         $user = Usuario::find($id);
         if (!$user) {
             Log::warning('Usuario no encontrado', ['usuario_id' => $id]);
             return response()->json([
                 'success' => false,
                 'message' => 'Usuario no encontrado',
             ], 404);
         }
     
         $usuarioId = auth()->id();
         $usuario = Usuario::find($usuarioId);
         $nombreUsuario = $usuario ? $usuario->nombres . ' ' . $usuario->apellidos : 'Desconocido';
     
         $camposActualizados = false;
     
         // Nombres
         if ($request->filled('nombres') && $user->nombres !== $request->nombres) {
             $nombreAntiguo = $user->nombres;
             $user->nombres = $request->nombres;
             $user->save();
     
             $accion = "$nombreUsuario actualizó el nombre del usuario de '$nombreAntiguo' a '{$request->nombres}'";
             $this->agregarLog($usuarioId, $accion);
             Log::info('Nombre actualizado', ['nuevo' => $request->nombres]);
             $camposActualizados = true;
         }
     
         // Apellidos
         if ($request->filled('apellidos') && $user->apellidos !== $request->apellidos) {
             $apellidoAntiguo = $user->apellidos;
             $user->apellidos = $request->apellidos;
             $user->save();
     
             $accion = "$nombreUsuario actualizó el apellido del usuario de '$apellidoAntiguo' a '{$request->apellidos}'";
             $this->agregarLog($usuarioId, $accion);
             Log::info('Apellido actualizado', ['nuevo' => $request->apellidos]);
             $camposActualizados = true;
         }
     
         // Correo
         if ($request->filled('correo') && $user->correo !== $request->correo) {
             $correoAntiguo = $user->correo;
             $user->correo = $request->correo;
             $user->save();
     
             $accion = "$nombreUsuario actualizó el correo del usuario de '$correoAntiguo' a '{$request->correo}'";
             $this->agregarLog($usuarioId, $accion);
             Log::info('Correo actualizado', ['nuevo' => $request->correo]);
             $camposActualizados = true;
         }
     
         // Contraseña
         if ($request->filled('password')) {
             $user->password = bcrypt($request->password);
             $user->save();
     
             $accion = "$nombreUsuario actualizó la contraseña del usuario.";
             $this->agregarLog($usuarioId, $accion);
             Log::info('Contraseña actualizada');
             $camposActualizados = true;
         }
     
         // Rol
         if ($request->filled('rol') && $user->rol !== $request->rol) {
             $rolAntiguo = $user->rol;
             $user->rol = $request->rol;
             $user->save();
     
             $accion = "$nombreUsuario actualizó el rol del usuario de '$rolAntiguo' a '{$request->rol}'";
             $this->agregarLog($usuarioId, $accion);
             Log::info('Rol actualizado', ['nuevo' => $request->rol]);
             $camposActualizados = true;
         }
     
         // Respuesta
         if ($camposActualizados) {
             return response()->json([
                 'success' => true,
                 'message' => 'Usuario actualizado exitosamente',
                 'user' => $user,
             ]);
         }
     
         return response()->json([
             'success' => true,
             'message' => 'No se realizaron cambios',
             'user' => $user,
         ]);
     }
     

     public function listarUsuarios(Request $request)
     {
         try {
             // Obtener los parámetros de la solicitud
             $perPage = $request->input('per_page', 10); // Número de elementos por página (por defecto 10)
             $page = $request->input('page', 1); // Página actual (por defecto 1)
             $search = $request->input('search', ''); // Término de búsqueda general
             $filters = $request->only(['nombres', 'apellidos', 'correo', 'rol', 'estado']); // Filtros específicos
     
             // Construir la consulta
             $query = Usuario::where('rol', 'admin');
     
             // Aplicar filtros dinámicos
             foreach ($filters as $field => $value) {
                 if ($value) {
                     $query->where($field, 'like', "%{$value}%");
                 }
             }
     
             // Aplicar búsqueda general
             if ($search) {
                 $query->where(function ($q) use ($search) {
                     $q->where('nombres', 'like', "%{$search}%")
                       ->orWhere('apellidos', 'like', "%{$search}%")
                       ->orWhere('correo', 'like', "%{$search}%")
                       ->orWhere('rol', 'like', "%{$search}%")
                       ->orWhere('estado', 'like', "%{$search}%");
                 });
             }
     
             // Paginar los resultados
             $usuarios = $query->select('idUsuario', 'nombres', 'apellidos', 'correo', 'rol', 'estado')
                               ->paginate($perPage, ['*'], 'page', $page);
     
             return response()->json([
                 'success' => true,
                 'usuarios' => $usuarios->items(), // Lista de usuarios
                 'pagination' => [
                     'total' => $usuarios->total(), // Total de usuarios
                     'per_page' => $usuarios->perPage(), // Elementos por página
                     'current_page' => $usuarios->currentPage(), // Página actual
                     'last_page' => $usuarios->lastPage(), // Última página
                     'from' => $usuarios->firstItem(), // Primer elemento de la página
                     'to' => $usuarios->lastItem(), // Último elemento de la página
                 ]
             ]);
         } catch (\Exception $e) {
             return response()->json([
                 'success' => false,
                 'message' => 'Error al obtener los usuarios',
                 'error' => $e->getMessage()
             ], 500);
         }
     }
 
    // FUNCION PARA CAMBIAR EL ESTADO DE UN USUARIO
    public function cambiarEstado($id)
    {
        // Buscar el usuario por ID
        $user = Usuario::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        // Obtener el ID del usuario autenticado desde el token
        $usuarioId = auth()->id(); // Obtiene el ID del usuario autenticado

        // Obtener el nombre completo del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;

        // Cambiar el estado
        $user->estado = ($user->estado === 'activo') ? 'inactivo' : 'activo';
        $user->save();

        // Definir la acción y mensaje para el log
        $accion = "$nombreUsuario cambió el estado del usuario: {$user->nombres} {$user->apellidos} (Correo: {$user->correo}) a {$user->estado}";

        // Llamada a la función agregarLog para registrar el log
        $this->agregarLog($usuarioId, $accion);

        // Retornar una respuesta exitosa con el estado actualizado
        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado exitosamente',
            'user' => [
                'idUsuario' => $user->idUsuario,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'correo' => $user->correo,
                'rol' => $user->rol,
                'estado' => $user->estado, // Asegúrate de devolver el estado actualizado
            ],
        ]);
    }

    // Listar todos los productos con el nombre de la categoría y URL completa de la imagen
    public function obtenerProductos()
    {
        $productos = Producto::with('categoria:idCategoria,nombreCategoria')->get();

        // Mapeo para agregar el nombre de la categoría y la URL completa de la imagen
        $productos = $productos->map(function ($producto) {
            return [
                'idProducto' => $producto->idProducto,
                'nombreProducto' => $producto->nombreProducto,
                'descripcion' => $producto->descripcion,
                'precio' => $producto->precio,
                'stock' => $producto->stock,
                'imagen' => $producto->imagen ? url("storage/{$producto->imagen}") : null, // URL completa de la imagen
                'idCategoria' => $producto->idCategoria,
                'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : null,
            ];
        });

        return response()->json(['success' => true, 'data' => $productos], 200);
    }


    public function listarCategoriasProductos()
    {
        // Filtrar categorías con estado "activo"
        $categorias = Categoria::where('estado', 'activo')->get();
        return response()->json($categorias);
    }

    // public function agregarProducto(Request $request)
    // {
    //     $request->validate([
    //         'nombreProducto' => 'required',
    //         'descripcion' => 'nullable', // Descripción opcional
    //         'estado' => 'required',
    //         'idCategoria' => 'required|exists:categorias,idCategoria',
    //         'modelos' => 'required|array', // Asegúrate de que se envíe un array de modelos
    //         'modelos.*.nombreModelo' => 'required', // Nombre del modelo obligatorio
    //         'modelos.*.imagen' => 'required|image', // Imagen del modelo obligatoria
    //     ]);

    //     try {
    //         DB::beginTransaction();

    //         // Crear el producto
    //         $producto = Producto::create([
    //             'nombreProducto' => $request->nombreProducto,
    //             'descripcion' => $request->descripcion,
    //             'estado' => $request->estado,
    //             'idCategoria' => $request->idCategoria
    //         ]);

    //         // Crear los modelos
    //         foreach ($request->modelos as $modeloData) {
    //             $modelo = Modelo::create([
    //                 'idProducto' => $producto->idProducto,
    //                 'nombreModelo' => $modeloData['nombreModelo']
    //             ]);

    //             // Procesar la imagen de cada modelo
    //             if (isset($modeloData['imagen'])) {
    //                 $imagen = $modeloData['imagen'];
    //                 $nombreProducto = $producto->nombreProducto;
    //                 $nombreModelo = $modelo->nombreModelo;

    //                 // Ruta para guardar la imagen
    //                 $rutaImagen = 'imagenes/productos/' . $nombreProducto . '/modelos/' . $nombreModelo . '/' . $imagen->getClientOriginalName();

    //                 // Guardar en el disco 'public'
    //                 Storage::disk('public')->putFileAs(
    //                     'imagenes/productos/' . $nombreProducto . '/modelos/' . $nombreModelo,
    //                     $imagen,
    //                     $imagen->getClientOriginalName()
    //                 );

    //                 // Guardar solo la ruta relativa
    //                 $rutaImagenBD = str_replace('public/', '', $rutaImagen);

    //                 // Crear la imagen del modelo
    //                 ImagenModelo::create([
    //                     'idModelo' => $modelo->idModelo,
    //                     'urlImagen' => $rutaImagenBD,
    //                     'descripcion' => 'Imagen del modelo ' . $nombreModelo
    //                 ]);
    //             }
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Producto agregado correctamente',
    //             'producto' => $producto
    //         ], 201);

    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         // Imprime el error en los logs
    //         Log::error('Error al agregar el producto: ' . $e->getMessage());

    //         return response()->json([
    //             'message' => 'Error al agregar el producto',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function agregarProducto(Request $request)
    {
        $request->validate([
            'nombreProducto' => 'required',
            'descripcion' => 'nullable', // Descripción opcional
            'estado' => 'required',
            'idCategoria' => 'required|exists:categorias,idCategoria',
            'modelos' => 'required|array', // Asegúrate de que se envíe un array de modelos
            'modelos.*.nombreModelo' => 'required', // Nombre del modelo obligatorio
            'modelos.*.imagen' => 'required|image', // Imagen del modelo obligatoria
        ]);

        try {
            DB::beginTransaction();

            // Crear el producto
            $producto = Producto::create([
                'nombreProducto' => $request->nombreProducto,
                'descripcion' => $request->descripcion,
                'estado' => $request->estado,
                'idCategoria' => $request->idCategoria
            ]);

            // Crear los modelos
            foreach ($request->modelos as $modeloData) {
                $modelo = Modelo::create([
                    'idProducto' => $producto->idProducto,
                    'nombreModelo' => $modeloData['nombreModelo']
                ]);

                // Procesar la imagen de cada modelo
                if (isset($modeloData['imagen'])) {
                    $imagen = $modeloData['imagen'];
                    $nombreProducto = $producto->nombreProducto;
                    $nombreModelo = $modelo->nombreModelo;

                    // Ruta para guardar la imagen
                    $rutaImagen = 'imagenes/productos/' . $nombreProducto . '/modelos/' . $nombreModelo . '/' . $imagen->getClientOriginalName();

                    // Guardar en el disco 'public'
                    Storage::disk('public')->putFileAs(
                        'imagenes/productos/' . $nombreProducto . '/modelos/' . $nombreModelo,
                        $imagen,
                        $imagen->getClientOriginalName()
                    );

                    // Guardar solo la ruta relativa
                    $rutaImagenBD = str_replace('public/', '', $rutaImagen);

                    // Crear la imagen del modelo
                    ImagenModelo::create([
                        'idModelo' => $modelo->idModelo,
                        'urlImagen' => $rutaImagenBD,
                        'descripcion' => 'Imagen del modelo ' . $nombreModelo
                    ]);
                }
            }

            DB::commit();

            // Obtener el ID del usuario autenticado
            $usuarioId = auth()->id();

            // Obtener el nombre completo del usuario autenticado
            $usuario = Usuario::find($usuarioId);
            $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;

            // Definir la acción y mensaje para el log
            $accion = "$nombreUsuario agregó el producto: $producto->nombreProducto";

            // Llamada a la función agregarLog para registrar el log
            $this->agregarLog($usuarioId, $accion);

            return response()->json([
                'message' => 'Producto agregado correctamente',
                'producto' => $producto
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Imprime el error en los logs
            Log::error('Error al agregar el producto: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error al agregar el producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 
    public function listarProductos(Request $request)
    {
        // Obtener los parámetros de la solicitud
        $categoriaId = $request->input('categoria');
        $texto = $request->input('texto');
        $idProducto = $request->input('idProducto');
        $perPage = $request->input('perPage', 6); // Número de elementos por página
        $filters = json_decode($request->input('filters', '{}'), true); // Filtros adicionales

        // Construir la consulta para obtener los productos con relaciones
        $query = Producto::with([
            'categoria:idCategoria,nombreCategoria,estado', // Incluir el campo 'estado' de la categoría
            'modelos' => function($query) {
                $query->with([
                    'imagenes:idImagen,urlImagen,idModelo'
                ]);
            }
        ]);

        // Filtrar por idProducto si el parámetro 'idProducto' existe
        if ($idProducto) {
            $query->where('idProducto', $idProducto);
        }

        // Filtrar por categoría si el parámetro 'categoria' existe
        if ($categoriaId) {
            $query->where('idCategoria', $categoriaId);
        }

        // Filtrar por texto en el nombre del producto si el parámetro 'texto' existe
        if ($texto) {
            $query->where('nombreProducto', 'like', '%' . $texto . '%');
        }

        // Aplicar filtros adicionales
        if (!empty($filters)) {
            if (isset($filters['nombreProducto']) && $filters['nombreProducto'] !== '') {
                $query->where('nombreProducto', 'like', '%' . $filters['nombreProducto'] . '%');
            }
            if (isset($filters['descripcion']) && $filters['descripcion'] !== '') {
                $query->where('descripcion', 'like', '%' . $filters['descripcion'] . '%');
            }
            if (isset($filters['estado']) && $filters['estado'] !== '') {
                $query->where('estado', $filters['estado']);
            }
        }

        // Paginar los resultados
        $productos = $query->paginate($perPage);

        // Si se pasó un 'idProducto', se devuelve un solo producto
        if ($idProducto) {
            $producto = $productos->first();

            if ($producto) {
                $productoData = [
                    'idProducto' => $producto->idProducto,
                    'nombreProducto' => $producto->nombreProducto,
                    'descripcion' => $producto->descripcion,
                    'estado' => $producto->estado,
                    'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
                    'modelos' => $producto->modelos->map(function($modelo) {
                        return [
                            'idModelo' => $modelo->idModelo,
                            'nombreModelo' => $modelo->nombreModelo,
                            'imagenes' => $modelo->imagenes->map(function($imagen) {
                                return [
                                    'idImagen' => $imagen->idImagen,
                                    'urlImagen' => $imagen->urlImagen
                                ];
                            })
                        ];
                    })
                ];

                return response()->json(['data' => $productoData], 200);
            } else {
                return response()->json(['message' => 'Producto no encontrado'], 404);
            }
        }

        // Si no se pasó un 'idProducto', devolver todos los productos paginados
        $productosData = $productos->map(function($producto) {
            return [
                'idProducto' => $producto->idProducto,
                'nombreProducto' => $producto->nombreProducto,
                'descripcion' => $producto->descripcion ? : 'N/A',
                'estado' => $producto->estado,
                'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
                'modelos' => $producto->modelos->map(function($modelo) {
                    return [
                        'idModelo' => $modelo->idModelo,
                        'nombreModelo' => $modelo->nombreModelo,
                        'imagenes' => $modelo->imagenes->map(function($imagen) {
                            return [
                                'idImagen' => $imagen->idImagen,
                                'urlImagen' => $imagen->urlImagen
                            ];
                        })
                    ];
                })
            ];
        });

        return response()->json([
            'data' => $productosData,
            'current_page' => $productos->currentPage(),
            'last_page' => $productos->lastPage(),
            'per_page' => $productos->perPage(),
            'total' => $productos->total(),
        ], 200);
    }
    
    public function listarProductosCatalogo(Request $request)
    {
        // Validar parámetros de entrada
        $request->validate([
            'nombre' => 'nullable|string|max:255', // Filtro por nombre
            'categoria' => 'nullable|string|max:255', // Filtro por categoría
            'perPage' => 'nullable|integer|min:1|max:100', // Paginación
        ]);
    
        // Obtener parámetros validados
        $nombre = $request->input('nombre', '');  // Nombre del producto (si está presente)
        $categoriaNombre = $request->input('categoria', '');  // Nombre de la categoría (si está presente)
        $perPage = $request->input('perPage', 6);  // Cantidad de productos por página
    
        // Construir la consulta de productos con relaciones
        $query = Producto::with([
            'categoria:idCategoria,nombreCategoria',  // Relación con la categoría
            'modelos' => function($query) {
                $query->with([
                    'imagenes:idImagen,urlImagen,idModelo'  // Relación con las imágenes
                ]);
            }
        ]);
    
        // Filtrar solo productos activos
        $query->where('estado', 'activo');
    
        // Filtrar por nombre del producto (coincidencia exacta)
        if (!empty($nombre)) {
            $query->where('nombreProducto', '=', $nombre);  // Coincidencia exacta con 'nombreProducto'
        }
    
        // Filtrar por categoría (unión directa para evitar productos sin categoría)
        if (!empty($categoriaNombre)) {
            $query->whereHas('categoria', function($q) use ($categoriaNombre) {
                $q->where('nombreCategoria', '=', $categoriaNombre);  // Coincidencia exacta con 'nombreCategoria'
            });
        }
    
        // Ejecutar paginación
        $productos = $query->paginate($perPage);
    
        // Formatear la respuesta
        $productosData = $productos->map(function($producto) {
            return [
                'idProducto' => $producto->idProducto,
                'nombreProducto' => $producto->nombreProducto,
                'descripcion' => $producto->descripcion ?: 'N/A',
                'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
                'modelos' => $producto->modelos->map(function($modelo) {
                    return [
                        'idModelo' => $modelo->idModelo,
                        'nombreModelo' => $modelo->nombreModelo,
                        'imagenes' => $modelo->imagenes->map(function($imagen) {
                            return [
                                'idImagen' => $imagen->idImagen,
                                'urlImagen' => $imagen->urlImagen
                            ];
                        })
                    ];
                })
            ];
        });
    
        // Retornar JSON con productos filtrados
        return response()->json([
            'data' => $productosData,
            'current_page' => $productos->currentPage(),
            'last_page' => $productos->lastPage(),
            'per_page' => $productos->perPage(),
            'total' => $productos->total(),
        ], 200);
    }
    

    // public function editarModeloYImagen(Request $request, $idModelo)
    // {
    //     $modelo = Modelo::findOrFail($idModelo);
    //     $modelo->update([
    //         'nombreModelo' => $request->nombreModelo,
    //         'descripcion' => $request->descripcion,
    //     ]);
    
    //     // Procesar imágenes nuevas
    //     if ($request->hasFile('nuevasImagenes')) {
    //         foreach ($request->file('nuevasImagenes') as $imagen) {
    //             $nombreProducto = $modelo->producto->nombreProducto;
    //             $nombreModelo = $modelo->nombreModelo;
    
    //             $nombreArchivo = time() . '_' . $imagen->getClientOriginalName();
    //             $ruta = "imagenes/productos/{$nombreProducto}/modelos/{$nombreModelo}/{$nombreArchivo}";
                
    //             // Verificar si existe una imagen con el mismo nombre en la base de datos
    //             $imagenExistente = ImagenModelo::where('urlImagen', $ruta)->first();
    
    //             if ($imagenExistente) {
    //                 Log::info("Imagen existente encontrada: {$imagenExistente->urlImagen}");
    
    //                 // Intentar eliminar el archivo del almacenamiento
    //                 if (Storage::disk('public')->exists($imagenExistente->urlImagen)) {
    //                     Storage::disk('public')->delete($imagenExistente->urlImagen);
    //                     Log::info("Imagen eliminada: {$imagenExistente->urlImagen}");
    //                 } else {
    //                     Log::warning("La imagen no existe en el almacenamiento: {$imagenExistente->urlImagen}");
    //                 }
                    
    //                 // Eliminar el registro de la base de datos
    //                 $imagenExistente->delete();
    //                 Log::info("Registro eliminado de la base de datos: {$imagenExistente->idImagen}");
    //             }
    
    //             // Guardar la nueva imagen
    //             $imagen->storeAs("imagenes/productos/{$nombreProducto}/modelos/{$nombreModelo}", $nombreArchivo, 'public');
    //             Log::info("Nueva imagen guardada en: {$ruta}");
    
    //             // Crear el registro en la base de datos
    //             ImagenModelo::create([
    //                 'urlImagen' => $ruta,
    //                 'idModelo' => $modelo->idModelo,
    //                 'descripcion' => 'Nueva imagen añadida',
    //             ]);
    //         }
    //     }
    
    //     // Reemplazo de imágenes existentes
    //     if ($request->has('idImagenesReemplazadas')) {
    //         foreach ($request->idImagenesReemplazadas as $index => $idImagen) {
    //             $imagenModelo = ImagenModelo::findOrFail($idImagen);
    //             $rutaAntigua = $imagenModelo->urlImagen;
    
    //             Log::info("Iniciando reemplazo de imagen: {$rutaAntigua}");
    
    //             if ($request->hasFile("imagenesReemplazadas.{$index}")) {
    //                 $imagenReemplazada = $request->file("imagenesReemplazadas.{$index}");
    
    //                 // Eliminar la imagen anterior si existe
    //                 if (Storage::disk('public')->exists($rutaAntigua)) {
    //                     Storage::disk('public')->delete($rutaAntigua);
    //                     Log::info("Imagen reemplazada eliminada: {$rutaAntigua}");
    //                 } else {
    //                     Log::warning("No se encontró la imagen a reemplazar: {$rutaAntigua}");
    //                 }
    
    //                 $nombreProducto = $modelo->producto->nombreProducto;
    //                 $nombreModelo = $modelo->nombreModelo;
    
    //                 $nombreArchivoNuevo = time() . '_' . $imagenReemplazada->getClientOriginalName();
    //                 $rutaNueva = "imagenes/productos/{$nombreProducto}/modelos/{$nombreModelo}/{$nombreArchivoNuevo}";
    
    //                 // Guardar la nueva imagen
    //                 $imagenReemplazada->storeAs("imagenes/productos/{$nombreProducto}/modelos/{$nombreModelo}", $nombreArchivoNuevo, 'public');
    //                 Log::info("Nueva imagen guardada en: {$rutaNueva}");
    
    //                 // Actualizar la nueva ruta en la base de datos
    //                 $imagenModelo->update([
    //                     'urlImagen' => $rutaNueva,
    //                 ]);
    //                 Log::info("Ruta actualizada en la base de datos: {$rutaNueva}");
    //             }
    //         }
    //     }
    
    //     return response()->json(['message' => 'Modelo e imágenes actualizados correctamente']);
    // }

    public function editarModeloYImagen(Request $request, $idModelo)
    {
        $modelo = Modelo::findOrFail($idModelo);
        
        // Obtener el nombre del modelo antes de la actualización
        $nombreModeloAntiguo = $modelo->nombreModelo;
        
        // Actualizar los datos del modelo
        $modelo->update([
            'nombreModelo' => $request->nombreModelo,
            'descripcion' => $request->descripcion,
        ]);

        // Registrar la acción de edición del modelo en el log
        $usuarioId = auth()->id(); // Obtener el ID del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
        $accion = "$nombreUsuario editó el modelo: $nombreModeloAntiguo a $modelo->nombreModelo";
        $this->agregarLog($usuarioId, $accion);

        // Procesar nuevas imágenes
        if ($request->hasFile('nuevasImagenes')) {
            foreach ($request->file('nuevasImagenes') as $imagen) {
                $nombreProducto = $modelo->producto->nombreProducto;
                $nombreModelo = $modelo->nombreModelo;

                $nombreArchivo = time() . '_' . $imagen->getClientOriginalName();
                $ruta = "imagenes/productos/{$nombreProducto}/modelos/{$nombreModelo}/{$nombreArchivo}";
                
                // Verificar si existe una imagen con el mismo nombre en la base de datos
                $imagenExistente = ImagenModelo::where('urlImagen', $ruta)->first();

                if ($imagenExistente) {
                    Log::info("Imagen existente encontrada: {$imagenExistente->urlImagen}");

                    // Intentar eliminar el archivo del almacenamiento
                    if (Storage::disk('public')->exists($imagenExistente->urlImagen)) {
                        Storage::disk('public')->delete($imagenExistente->urlImagen);
                        Log::info("Imagen eliminada: {$imagenExistente->urlImagen}");
                    } else {
                        Log::warning("La imagen no existe en el almacenamiento: {$imagenExistente->urlImagen}");
                    }
                    
                    // Eliminar el registro de la base de datos
                    $imagenExistente->delete();
                    Log::info("Registro eliminado de la base de datos: {$imagenExistente->idImagen}");
                }

                // Guardar la nueva imagen
                $imagen->storeAs("imagenes/productos/{$nombreProducto}/modelos/{$nombreModelo}", $nombreArchivo, 'public');
                Log::info("Nueva imagen guardada en: {$ruta}");

                // Crear el registro en la base de datos
                ImagenModelo::create([
                    'urlImagen' => $ruta,
                    'idModelo' => $modelo->idModelo,
                    'descripcion' => 'Nueva imagen añadida',
                ]);
            }
        }

        // Reemplazo de imágenes existentes
        if ($request->has('idImagenesReemplazadas')) {
            foreach ($request->idImagenesReemplazadas as $index => $idImagen) {
                $imagenModelo = ImagenModelo::findOrFail($idImagen);
                $rutaAntigua = $imagenModelo->urlImagen;

                Log::info("Iniciando reemplazo de imagen: {$rutaAntigua}");

                if ($request->hasFile("imagenesReemplazadas.{$index}")) {
                    $imagenReemplazada = $request->file("imagenesReemplazadas.{$index}");

                    // Eliminar la imagen anterior si existe
                    if (Storage::disk('public')->exists($rutaAntigua)) {
                        Storage::disk('public')->delete($rutaAntigua);
                        Log::info("Imagen reemplazada eliminada: {$rutaAntigua}");
                    } else {
                        Log::warning("No se encontró la imagen a reemplazar: {$rutaAntigua}");
                    }

                    $nombreProducto = $modelo->producto->nombreProducto;
                    $nombreModelo = $modelo->nombreModelo;

                    $nombreArchivoNuevo = time() . '_' . $imagenReemplazada->getClientOriginalName();
                    $rutaNueva = "imagenes/productos/{$nombreProducto}/modelos/{$nombreModelo}/{$nombreArchivoNuevo}";

                    // Guardar la nueva imagen
                    $imagenReemplazada->storeAs("imagenes/productos/{$nombreProducto}/modelos/{$nombreModelo}", $nombreArchivoNuevo, 'public');
                    Log::info("Nueva imagen guardada en: {$rutaNueva}");

                    // Actualizar la nueva ruta en la base de datos
                    $imagenModelo->update([
                        'urlImagen' => $rutaNueva,
                    ]);
                    Log::info("Ruta actualizada en la base de datos: {$rutaNueva}");
                }
            }
        }

        return response()->json(['message' => 'Modelo e imágenes actualizados correctamente']);
    }


    
    // public function eliminarImagenModelo($idImagen)
    // {
    //     // Buscar la imagen en la base de datos
    //     $imagenModelo = ImagenModelo::findOrFail($idImagen);
    //     $rutaImagen = $imagenModelo->urlImagen;
    
    //     // Eliminar archivo físico si existe (especificando el disco 'public')
    //     if (Storage::disk('public')->exists($rutaImagen)) {
    //         Storage::disk('public')->delete($rutaImagen);
    //         Log::info("Imagen eliminada correctamente de la ruta: {$rutaImagen}");
    //     } else {
    //         Log::warning("La imagen no existe en el almacenamiento: {$rutaImagen}");
    //     }
    
    //     // Eliminar el registro de la imagen de la base de datos
    //     $imagenModelo->delete();
    //     Log::info("Registro eliminado de la base de datos: {$imagenModelo->idImagen}");
    
    //     return response()->json(['message' => 'Imagen eliminada correctamente']);
    // }

    public function eliminarImagenModelo($idImagen)
    {
        // Buscar la imagen en la base de datos
        $imagenModelo = ImagenModelo::findOrFail($idImagen);
        $rutaImagen = $imagenModelo->urlImagen;
    
        // Obtener el nombre del modelo y producto relacionados con la imagen
        $modelo = $imagenModelo->modelo;
        $producto = $modelo->producto;
        $nombreModelo = $modelo->nombreModelo;
        $nombreProducto = $producto->nombreProducto;
    
        // Eliminar archivo físico si existe (especificando el disco 'public')
        if (Storage::disk('public')->exists($rutaImagen)) {
            Storage::disk('public')->delete($rutaImagen);
            Log::info("Imagen eliminada correctamente de la ruta: {$rutaImagen}");
            
            // Registrar la acción en el log
            $usuarioId = auth()->id(); // Obtener el ID del usuario autenticado
            $usuario = Usuario::find($usuarioId);
            $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
            $accion = "$nombreUsuario eliminó la imagen del modelo $nombreModelo del producto $nombreProducto";
            $this->agregarLog($usuarioId, $accion);
        } else {
            Log::warning("La imagen no existe en el almacenamiento: {$rutaImagen}");
        }
    
        // Eliminar el registro de la imagen de la base de datos
        $imagenModelo->delete();
        Log::info("Registro eliminado de la base de datos: {$imagenModelo->idImagen}");
    
        return response()->json(['message' => 'Imagen eliminada correctamente']);
    }
    
    // public function actualizarProducto(Request $request, $idProducto)
    // {
    //     $producto = Producto::find($idProducto);
        
    //     if (!$producto) {
    //         return response()->json(['error' => 'Producto no encontrado'], 404);
    //     }

    //     $producto->nombreProducto = $request->nombreProducto;
    //     $producto->descripcion = $request->descripcion;
    //     $producto->save();

    //     return response()->json(['message' => 'Producto actualizado correctamente']);
    // }

    public function actualizarProducto(Request $request, $idProducto)
    {
        $producto = Producto::find($idProducto);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        // Obtener el nombre del producto antes de la actualización
        $nombreProductoAntiguo = $producto->nombreProducto;

        // Actualizar los datos del producto
        $producto->nombreProducto = $request->nombreProducto;
        $producto->descripcion = $request->descripcion;
        $producto->save();

        // Registrar la acción de actualización del producto en el log
        $usuarioId = auth()->id(); // Obtener el ID del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
        $accion = "$nombreUsuario actualizó el producto: $nombreProductoAntiguo a {$producto->nombreProducto}";
        $this->agregarLog($usuarioId, $accion);

        return response()->json(['message' => 'Producto actualizado correctamente']);
    }

    // public function cambiarEstadoProducto(Request $request, $id) {
    //     $producto = Producto::findOrFail($id);
    //     $producto->estado = $request->estado;
    //     $producto->save();
    //     return response()->json(['message' => 'Estado actualizado']);
    // }

    public function cambiarEstadoProducto(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);
    
        // Guardar el estado anterior para el log
        $estadoAnterior = $producto->estado;
    
        // Actualizar el estado del producto
        $producto->estado = $request->estado;
        $producto->save();
    
        // Registrar la acción de cambio de estado en el log
        $usuarioId = auth()->id(); // Obtener el ID del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
        $accion = "$nombreUsuario cambió el estado del producto {$producto->nombreProducto} de '$estadoAnterior' a '{$producto->estado}'";
        $this->agregarLog($usuarioId, $accion);
    
        return response()->json(['message' => 'Estado actualizado']);
    }
    
    public function agregarCategorias(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'nombreCategoria' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:60',
            'imagen' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5000', // Validación de imagen
        ]);

        // Obtener el nombre de la categoría
        $nombreCategoria = $request->input('nombreCategoria');
        $descripcion = $request->input('descripcion', null);

        // Guardar la imagen en el directorio correspondiente
        $imagen = $request->file('imagen');
        $rutaImagen = 'imagenes/categorias/' . $nombreCategoria . '/' . $imagen->getClientOriginalName();
        Storage::disk('public')->putFileAs('imagenes/categorias/' . $nombreCategoria, $imagen, $imagen->getClientOriginalName());

        // Crear la categoría en la base de datos
        $categoria = Categoria::create([
            'nombreCategoria' => $nombreCategoria,
            'descripcion' => $descripcion,
            'imagen' => $rutaImagen, // Guardar la ruta de la imagen
            'estado' => 'activo'
        ]);

        // Obtener el ID del usuario autenticado desde el token
        $usuarioId = auth()->id(); // Obtiene el ID del usuario autenticado

        // Obtener el nombre completo del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;

        // Definir la acción y mensaje para el log
        $accion = "$nombreUsuario agregó una nueva categoría: $nombreCategoria con descripción: $descripcion";

        // Llamada a la función agregarLog para registrar el log
        $this->agregarLog($usuarioId, $accion);

        // Retornar una respuesta exitosa con la categoría agregada
        return response()->json([
            'message' => 'Categoría agregada exitosamente',
            'categoria' => $categoria
        ], 200);
    }

    public function obtenerCategoriasProducto(Request $request)
    {
        // Obtener los parámetros de paginación
        $page = $request->input('page', 1); // Página actual, por defecto 1
        $limit = $request->input('limit', 5); // Límite de elementos por página, por defecto 5
    
        // Obtener los parámetros de filtro y búsqueda
        $idCategoria = $request->input('idCategoria', '');
        $nombreCategoria = $request->input('nombreCategoria', '');
        $descripcion = $request->input('descripcion', '');
        $estado = $request->input('estado', '');
        $searchTerm = $request->input('searchTerm', '');
    
        // Construir la consulta
        $query = Categoria::query();
    
        // Aplicar filtros
        if ($idCategoria) {
            $query->where('idCategoria', 'like', "%{$idCategoria}%");
        }
        if ($nombreCategoria) {
            $query->where('nombreCategoria', 'like', "%{$nombreCategoria}%");
        }
        if ($descripcion) {
            $query->where('descripcion', 'like', "%{$descripcion}%");
        }
        if ($estado) {
            $query->where('estado', 'like', "%{$estado}%");
        }
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('idCategoria', 'like', "%{$searchTerm}%")
                  ->orWhere('nombreCategoria', 'like', "%{$searchTerm}%")
                  ->orWhere('descripcion', 'like', "%{$searchTerm}%")
                  ->orWhere('estado', 'like', "%{$searchTerm}%");
            });
        }
    
        // Paginar los resultados
        $categorias = $query->paginate($limit, ['*'], 'page', $page);
    
        return response()->json([
            'data' => $categorias->items(), // Datos de la página actual
            'total' => $categorias->total(), // Total de registros
            'page' => $categorias->currentPage(), // Página actual
            'totalPages' => $categorias->lastPage(), // Total de páginas
        ]);
    }


    public function obtenerCategorias(Request $request)
    {
        // Obtener los parámetros de paginación
        $page = $request->input('page', 1); // Página actual, por defecto 1
        $limit = $request->input('limit', 5); // Límite de elementos por página, por defecto 5
    
        // Obtener los parámetros de filtro y búsqueda
        $idCategoria = $request->input('idCategoria', '');
        $nombreCategoria = $request->input('nombreCategoria', '');
        $descripcion = $request->input('descripcion', '');
        $estado = $request->input('estado', '');
        $searchTerm = $request->input('searchTerm', '');
    
        // Construir la consulta
        $query = Categoria::query();
    
        // Aplicar filtros
        if ($idCategoria) {
            $query->where('idCategoria', 'like', "%{$idCategoria}%");
        }
        if ($nombreCategoria) {
            $query->where('nombreCategoria', 'like', "%{$nombreCategoria}%");
        }
        if ($descripcion) {
            $query->where('descripcion', 'like', "%{$descripcion}%");
        }
        if ($estado) {
            $query->where('estado', 'like', "%{$estado}%");
        }
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('idCategoria', 'like', "%{$searchTerm}%")
                  ->orWhere('nombreCategoria', 'like', "%{$searchTerm}%")
                  ->orWhere('descripcion', 'like', "%{$searchTerm}%")
                  ->orWhere('estado', 'like', "%{$searchTerm}%");
            });
        }
    
        // Paginar los resultados
        $categorias = $query->paginate($limit, ['*'], 'page', $page);
    
        return response()->json([
            'data' => $categorias->items(), // Datos de la página actual
            'total' => $categorias->total(), // Total de registros
            'page' => $categorias->currentPage(), // Página actual
            'totalPages' => $categorias->lastPage(), // Total de páginas
        ]);
    }


    public function listarCategorias(Request $request)
    {
        // Obtener los parámetros de filtro y búsqueda
        $idCategoria = $request->input('idCategoria', '');
        $nombreCategoria = $request->input('nombreCategoria', '');
        $descripcion = $request->input('descripcion', '');
        $searchTerm = $request->input('searchTerm', '');

        // Parámetros de paginación
        $page = $request->input('page', 1); // Página actual, por defecto 1
        $perPage = $request->input('perPage', 10); // Elementos por página, por defecto 10

        // Construir la consulta
        $query = Categoria::query();

        // Filtrar solo categorías con estado "activo"
        $query->where('estado', 'activo');

        // Aplicar filtros adicionales
        if ($idCategoria) {
            $query->where('idCategoria', 'like', "%{$idCategoria}%");
        }
        if ($nombreCategoria) {
            $query->where('nombreCategoria', 'like', "%{$nombreCategoria}%");
        }
        if ($descripcion) {
            $query->where('descripcion', 'like', "%{$descripcion}%");
        }
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('idCategoria', 'like', "%{$searchTerm}%")
                ->orWhere('nombreCategoria', 'like', "%{$searchTerm}%")
                ->orWhere('descripcion', 'like', "%{$searchTerm}%");
            });
        }

        // Seleccionar solo los campos necesarios
        $query->select('idCategoria', 'nombreCategoria', 'descripcion', 'imagen');

        // Paginar los resultados
        $categorias = $query->paginate($perPage, ['*'], 'page', $page);

        // Formatear la respuesta
        $response = [
            'data' => $categorias->items(), // Datos de la página actual
            'pagination' => [
                'total' => $categorias->total(), // Total de registros
                'perPage' => $categorias->perPage(), // Elementos por página
                'currentPage' => $categorias->currentPage(), // Página actual
                'lastPage' => $categorias->lastPage(), // Última página
            ],
        ];

        return response()->json($response);
    }

    public function listarCategoriasFiltrador(Request $request)
    {
        // Construir la consulta
        $query = Categoria::query();
    
        // Filtrar solo categorías con estado "activo"
        $query->where('estado', 'activo');
    
        // Seleccionar solo los campos necesarios
        $query->select('idCategoria', 'nombreCategoria');
    
        // Obtener los resultados, puedes paginarlos si es necesario
        $categorias = $query->get(); // Si no deseas paginación, usa ->get()
    
        // Formatear la respuesta
        $response = [
            'data' => $categorias, // Datos de las categorías
        ];
    
        return response()->json($response);
    }
    
        
    public function cambiarEstadoCategoria($id, Request $request)
    {
        // Validar el estado recibido
        $request->validate([
            'estado' => 'required|in:activo,inactivo',
        ]);
    
        // Buscar la categoría por ID
        $categoria = Categoria::findOrFail($id);
    
        // Obtener el ID del usuario autenticado desde el token
        $usuarioId = auth()->id(); // Obtiene el ID del usuario autenticado
    
        // Obtener el nombre completo del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
    
        // Cambiar el estado de la categoría
        $estadoAnterior = $categoria->estado;
        $categoria->estado = $request->estado;
        $categoria->save();
    
        // Definir la acción y mensaje para el log
        $accion = "$nombreUsuario cambió el estado de la categoría: {$categoria->nombreCategoria} de $estadoAnterior a {$categoria->estado}";
    
        // Llamada a la función agregarLog para registrar el log
        $this->agregarLog($usuarioId, $accion);
    
        // Devolver una respuesta exitosa
        return response()->json(['message' => 'Estado actualizado correctamente']);
    }
    

    public function actualizarCategoria(Request $request, $id)
    {
        Log::info('Iniciando actualización de categoría', [
            'id' => $id,
            'request_all' => $request->all(),
            'files' => $request->hasFile('imagen') ? 'Tiene imagen' : 'No tiene imagen'
        ]);
    
        try {
            // Buscar la categoría
            $categoria = Categoria::where('idCategoria', $id)->first();
    
            if (!$categoria) {
                Log::error('Categoría no encontrada', ['id' => $id]);
                return response()->json(['error' => 'Categoría no encontrada'], 404);
            }
    
            // Obtener el usuario autenticado
            $usuarioId = auth()->id(); // Obtener el ID del usuario autenticado
            $usuario = Usuario::find($usuarioId);
            $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
    
            // Registro de la acción antes de la actualización
            $accionGeneral = "$nombreUsuario está actualizando los campos de la categoría: {$categoria->nombreCategoria}";
    
            // Actualización del nombre de la categoría
            if ($request->has('nombreCategoria') && $categoria->nombreCategoria !== $request->nombreCategoria) {
                $nombreCategoriaAntiguo = $categoria->nombreCategoria;
                $nuevoNombreCategoria = trim($request->nombreCategoria);
                $categoria->nombreCategoria = $nuevoNombreCategoria;
                $categoria->save();
    
                // Registro para el nombre de la categoría
                $accion = "$nombreUsuario actualizó el nombre de la categoría de '$nombreCategoriaAntiguo' a '$nuevoNombreCategoria'";
                $this->agregarLog($usuarioId, $accion);
                Log::info('Nombre de la categoría actualizado', ['nuevo_nombre' => $nuevoNombreCategoria]);
            }
    
            // Actualización de la descripción
            if ($request->has('descripcion') && $categoria->descripcion !== $request->descripcion) {
                $descripcionAntigua = $categoria->descripcion;
                $categoria->descripcion = $request->descripcion;
                $categoria->save();
    
                // Registro para la descripción
                $accion = "$nombreUsuario actualizó la descripción de la categoría de '$descripcionAntigua' a '{$categoria->descripcion}'";
                $this->agregarLog($usuarioId, $accion);
                Log::info('Descripción de la categoría actualizada', ['nueva_descripcion' => $categoria->descripcion]);
            }
    
            // Actualización de la imagen
            if ($request->hasFile('imagen')) {
                Log::info('Procesando nueva imagen');
    
                $oldFolder = str_replace(' ', '_', $categoria->nombreCategoria);
                $newFolder = str_replace(' ', '_', $categoria->nombreCategoria);
                $oldBasePath = "imagenes/categorias/{$oldFolder}";
                $newBasePath = "imagenes/categorias/{$newFolder}";
    
                // Eliminar la imagen anterior si existe
                if ($categoria->imagen && Storage::disk('public')->exists($categoria->imagen)) {
                    Storage::disk('public')->delete($categoria->imagen);
                    Log::info('Imagen anterior eliminada', ['path' => $categoria->imagen]);
                }
    
                // Guardar nueva imagen
                $imagePath = $request->file('imagen')->store("imagenes/categorias/{$newFolder}", 'public');
                $categoria->imagen = $imagePath;
                $categoria->save();
    
                // Registro para la imagen
                $accion = "$nombreUsuario actualizó la imagen de la categoría.";
                $this->agregarLog($usuarioId, $accion);
                Log::info('Nueva imagen guardada', ['path' => $categoria->imagen]);
            }
    
            // Respuesta exitosa
            return response()->json([
                'message' => 'Categoría actualizada exitosamente',
                'data' => $categoria
            ]);
    
        } catch (\Exception $e) {
            Log::error('Error al actualizar categoría', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'error' => 'Error al actualizar la categoría: ' . $e->getMessage()
            ], 500);
        }
    }
    

    // Función para agregar un log directamente desde el backend
    public function agregarLog($usuarioId, $accion)
    {
        // Obtener el usuario por id
        $usuario = Usuario::find($usuarioId);

        if ($usuario) {
            // Crear el log
            $log = LogUser::create([
                'idUsuario' => $usuario->idUsuario,
                'nombreUsuario' => $usuario->nombres . ' ' . $usuario->apellidos,
                'rol' => $usuario->rol,
                'accion' => $accion,
                'fecha' => now(),
            ]);

            return response()->json(['message' => 'Log agregado correctamente', 'log' => $log], 200);
        }

        return response()->json(['message' => 'Usuario no encontrado'], 404);
    }


}
