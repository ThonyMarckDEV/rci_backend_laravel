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
        $perPage = $request->input('perPage', 10); // Número de elementos por página
    
        // Construir la consulta para obtener los productos con relaciones
        $query = Producto::with([
            'categoria:idCategoria,nombreCategoria,estado', // Incluir el campo 'estado' de la categoría
            'modelos' => function($query) {
                $query->with([
                    'imagenes:idImagen,urlImagen,idModelo'
                ]);
            }
        ]);
    
        // Filtrar por estado 'activo' en la tabla 'productos'
        $query->where('estado', 'activo');
    
        // Filtrar por estado 'activo' en la tabla 'categorias'
        $query->whereHas('categoria', function($q) {
            $q->where('estado', 'activo');
        });
    
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
    public function editarModeloyImagen(Request $request, $id)
    {
        try {
            // Validar los datos
            $request->validate([
                'nombreModelo' => 'required|string|max:255',
                'descripcion' => 'required|string|nullable',
                'imagen' => 'nullable|image|mimes:jpeg,png,jpg|max:5120'
            ]);
    
            // Buscar el modelo
            $modelo = Modelo::findOrFail($id);
            $producto = $modelo->producto;
    
            // Crear el directorio base si no existe
            $baseDirectory = 'imagenes/productos/' . $producto->nombreProducto . '/modelos';
            Storage::disk('public')->makeDirectory($baseDirectory);
    
            // Obtener el nombre del modelo actual y nuevo
            $oldModeloName = $modelo->nombreModelo;
            $newModeloName = $request->nombreModelo;
    
            // Actualizar datos básicos
            $modelo->nombreModelo = $newModeloName;
            $modelo->save();
    
            // Procesar imagen si se proporciona una nueva
            if ($request->hasFile('imagen')) {
                $imagen = $request->file('imagen');
    
                // Generar nombre único para la imagen
                $nombreImagen = time() . '_' . $imagen->getClientOriginalName();
                $rutaImagen = $baseDirectory . '/' . $newModeloName . '/' . $nombreImagen;
    
                // Eliminar imagen anterior si existe
                if ($modelo->imagenes()->exists()) {
                    $imagenAnterior = $modelo->imagenes()->first();
                    if ($imagenAnterior && Storage::disk('public')->exists($imagenAnterior->urlImagen)) {
                        Storage::disk('public')->delete($imagenAnterior->urlImagen);
                    }
                    $imagenAnterior?->delete();
                }
    
                // Guardar la nueva imagen
                try {
                    // Verificar si el directorio del nuevo modelo ya existe
                    $newModeloDirectory = $baseDirectory . '/' . $newModeloName;
                    if (!Storage::disk('public')->exists($newModeloDirectory)) {
                        Storage::disk('public')->makeDirectory($newModeloDirectory);
                    }
    
                    // Guardar la imagen en el directorio del nuevo modelo
                    Storage::disk('public')->putFileAs(
                        $newModeloDirectory,
                        $imagen,
                        $nombreImagen
                    );
    
                    // Crear o actualizar el registro de imagen
                    ImagenModelo::create([
                        'idModelo' => $modelo->idModelo,
                        'urlImagen' => $rutaImagen,
                        'descripcion' => 'Imagen del modelo ' . $newModeloName
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error al guardar la imagen: ' . $e->getMessage());
                    throw new \Exception('Error al procesar la imagen');
                }
            }
    
            // Si el nombre del modelo ha cambiado, solo renombrar el directorio
            if ($oldModeloName !== $newModeloName) {
                $oldModeloDirectory = $baseDirectory . '/' . $oldModeloName;
                $newModeloDirectory = $baseDirectory . '/' . $newModeloName;
    
                // Verificar si el directorio antiguo existe
                if (Storage::disk('public')->exists($oldModeloDirectory)) {
                    // Renombrar el directorio
                    Storage::disk('public')->move($oldModeloDirectory, $newModeloDirectory);
                }
            }
    
            return response()->json([
                'message' => 'Modelo actualizado correctamente',
                'modelo' => $modelo->load('imagenes')
            ]);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en editarModeloyImagen: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar el modelo',
                'error' => $e->getMessage()
            ], 500);
        }
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
    
        // Obtener todas las categorías sin paginación
        $categorias = $query->get();
    
        // Formatear la respuesta
        $response = $categorias->map(function ($categoria) {
            return [
                'idCategoria' => $categoria->idCategoria,
                'nombreCategoria' => $categoria->nombreCategoria,
                'descripcion' => $categoria->descripcion,
                'imagen' => $categoria->imagen, // Asegúrate de que la URL de la imagen sea completa si es necesario
            ];
        });
    
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
