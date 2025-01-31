<?php

namespace App\Http\Controllers\C_Usuario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Http\Requests\Usuarios\StoreUsuario;
use App\Http\Requests\Usuarios\ResetPassword;
use App\Http\Requests\Usuarios\UpdateUsuario;
use Intervention\Image\ImageManagerStatic as Image;
use App\Models\M_RRHH\Empleado;
use Barryvdh\DomPDF\Facade as PDF;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DatosExport;
use App\Exports\datosallExport;
use App\Bitacora;
use Storage;
use Session;
use Auth;

class UserController extends Controller
{

  public function index()
  {
    if (!Auth::user()->can('permisos', 'Navegar-usuarios') || Auth::user()->hasRole('Inactivo')) abort(403);
    // $usuarios = User::orderBy('name', 'asc')->with('roles')->get();
    return view('usuarios.index');
  }

  public function obtenerusuarios(Request $request)
  {
    if (!Auth::user()->can('permisos', 'Navegar-usuarios') || Auth::user()->hasRole('Inactivo')) abort(403);

    $columns = ['name', 'username', 'email', 'activo'];

    $length = $request->input('length');
    $column = $request->input('column');
    $dir = $request->input('dir');
    $searchValue = $request->input('search');
    $searchColumn = $request->input('searchColumn');

    $query = User::select('id', 'name', 'username', 'email', 'activo')->with('roles')->orderBy($columns[$column], $dir);

    if ($searchValue) {
        $query->where(function ($query) use ($searchValue, $searchColumn) {
          $query->where($searchColumn, 'like', '%' . $searchValue . '%');
        }); 
    }

    $usuarios = $query->paginate($length);
    return ['data' => $usuarios, 'draw' => $request->input('draw')];
  }

  //para bootstrap-table
  // public function obtenerusuarios(Request $request)
  // {
  //   if (!Auth::user()->can('permisos', 'Navegar-usuarios') || Auth::user()->hasRole('Inactivo')) abort(403);
    
    
  //   $usuarios = User::select('id', 'name', 'username', 'email', 'activo')->with('roles')->get();
    
  //   logger($usuarios);

  //   // $usuarios = $query->paginate($length);
  //   return ['data' => $usuarios];
  // }

  public function create()
  {
    if (!Auth::user()->can('permisos', 'Crear-usuarios') || Auth::user()->hasRole('Inactivo')) abort(403);

    $empleados = Empleado::select('id', 'nombres', 'ap_paterno', 'ap_materno','ci')->get();

    $roles = Role::all()->groupBy('category')->toArray();
    unset($roles['Inactivo']);
    unset($roles['Admin']);
    // ksort($roles);

    return view('usuarios.create', compact('roles', 'empleados'));
  }

  public function store(StoreUsuario $request)
  {
    if (!Auth::user()->can('permisos', 'Crear-usuarios') || Auth::user()->hasRole('Inactivo')) abort(403);

    try {
      $usuario = new User();
      $usuario->nombres = $request->nombres;
      $usuario->apellidos = $request->apellidos;
      $usuario->name = $request->nombres . ' ' . $request->apellidos;
      $usuario->username = $request->username;
      $usuario->email = $request->email;
      $usuario->activo = $request->activo;
      $usuario->password = Hash::make($request->password);
      if ($request->empleado_id) {
        $usuario->empleado_id = $request->empleado_id;
      }
      if ($request->imagen) {
        //laravel intervention

        $ext = $request->imagen->getClientOriginalExtension();
        $fileName = str_random() . '.' . $ext;
        //original
        $request->imagen->storeAs('usuarios/originales', $fileName);
        //modificado
        $imagenmod = Image::make($request->imagen)->fit(300, 300);
        Storage::put('usuarios/thumbnail/' . $fileName, $imagenmod->encode());

        $usuario->fotografia = 'usuarios/thumbnail/' . $fileName;
      } else {
        $usuario->fotografia = 'usuariodef/avatar.png';
      }

      $usuario->save();

      if ($usuario->activo == 0) {
        $usuario->assignRole('Inactivo');
      } else
        $usuario->removeRole('Inactivo');

      if ($request->roles) {
        $array = explode(",", $request->roles);
        $usuario->syncRoles($array);
        cache()->tags('permisos')->flush();
      }

      $bitacora = new Bitacora();
      $bitacora->mensaje = 'Se creó el usuario';
      $bitacora->registro_id = $usuario->id;
      $bitacora->user_id = Auth::user()->id;
      $bitacora->save();

      $toast = array(
        'title'   => 'Usuario creado: ',
        'message' => $request->username,
        'background' => '#e1f6d0',
        'type' => 'success'
      );

      return [$usuario, $toast];
    } catch (\Illuminate\Database\QueryException $e) {
      $toast = array(
        'title'   => 'Usuario no creado: ',
        'message' => $usuario->username,
        'type'    => 'error',
        'background' => '#edc3c3'
      );
      return [$usuario, $toast, $e->errorInfo];
    }
  }

  public function show($id)
  {
    if (!Auth::user()->can('permisos', 'Ver-usuarios') || Auth::user()->hasRole('Inactivo')) abort(403);

    $roles = Role::all()->groupBy('category')->toArray();
    unset($roles['Inactivo']);
    unset($roles['Admin']);
    $usuario = User::find($id);
    return view('usuarios.show', compact('usuario', 'roles'));
  }

  public function edit($id)
  {
    if (!Auth::user()->can('permisos', 'Editar-usuarios') || Auth::user()->hasRole('Inactivo')) abort(403);

    $roles = Role::all()->groupBy('category')->toArray();
    unset($roles['Inactivo']);
    unset($roles['Admin']);
    $usuario = User::find($id);
    $usuarioroles = $usuario->roles;
    return view('usuarios.edit', compact('usuario', 'usuarioroles', 'roles'));
  }

  public function update(UpdateUsuario $request, $id)
  {

    if (!Auth::user()->can('permisos', 'Editar-usuarios') || Auth::user()->hasRole('Inactivo')) abort(403);

    try {
      $usuario = User::find($id);
      $usuario->nombres = $request->nombres;
      $usuario->apellidos = $request->apellidos;
      $usuario->username = $request->username;
      $usuario->name = $request->nombres . ' ' . $request->apellidos;

      if ($request->activo == 0) {
        $usuario->assignRole('Inactivo');
      } else
        $usuario->removeRole('Inactivo');

      $usuario->activo = $request->activo;
      cache()->tags('permisos')->flush();

      if ($request->imagen) {
        //laravel intervention

        $ext = $request->imagen->getClientOriginalExtension();
        $fileName = str_random() . '.' . $ext;
        //original
        $request->imagen->storeAs('usuarios/originales', $fileName);
        //modificado
        $imagenmod = Image::make($request->imagen)->fit(300, 300);
        Storage::put('usuarios/thumbnail/' . $fileName, $imagenmod->encode());

        $usuario->fotografia = 'usuarios/thumbnail/' . $fileName;
      }
      $usuario->save();

      $bitacora = new Bitacora();
      $bitacora->mensaje = 'Se editó el usuario';
      $bitacora->registro_id = $usuario->id;
      $bitacora->user_id = Auth::user()->id;
      $bitacora->save();

      $toast = array(
        'title'   => 'Usuario modificado: ',
        'message' => $usuario->username,
        'background' => '#e1f6d0',
        'type' => 'success'
      );

      return [$usuario, $toast];
    } catch (\Throwable $th) {
      $toast = array(
        'title'   => 'Usuario no modificado: ',
        'message' => 'error',
        'type'    => 'error',
        'background' => '#edc3c3'
      );
      return response()->json($usuario, $toast);
    }
  }


  public function destroy($id)
  {
    if (!Auth::user()->can('permisos', 'Eliminar-usuarios') || Auth::user()->hasRole('Inactivo')) abort(403);

    $usuario = User::find($id);
    $usuario->delete();

    $bitacora = new Bitacora();
    $bitacora->mensaje = 'Se eliminó el usuario';
    $bitacora->registro_id = $usuario->id;
    $bitacora->user_id = Auth::user()->id;
    $bitacora->save();

    $toast = array(
      'title'   => 'usuario quitado. ',
      'message' => '',
      'background' => '#e1f6d0',
      'type' => 'success'
    );
    return $toast;
  }

  public function updatepass(Request $request, $id)
  {
    if (!Auth::user()->can('permisos', 'Editar-usuarios') || Auth::user()->hasRole('Inactivo')) abort(403);

    $this->validate($request, [
      'password' => 'string|min:6'
    ]);


    try {
      $usuario = User::find($id);
      if ($request->password) {
        $usuario->password = Hash::make($request->password);
      }

      $usuario->save();

      $bitacora = new Bitacora();
      $bitacora->mensaje = 'Se actualizó contraseña';
      $bitacora->registro_id = $usuario->id;
      $bitacora->user_id = Auth::user()->id;
      $bitacora->save();

      $toast = array(
        'title'   => 'Usuario modificado: ',
        'message' => $usuario->username,
        'background' => '#e1f6d0',
        'type' => 'success'
      );
      return [$usuario, $toast];
    } catch (\Throwable $th) {
      $toast = array(
        'title'   => 'Usuario no modificado: ',
        'message' => 'error',
        'type'    => 'error',
        'background' => '#edc3c3'
      );
      return response()->json($usuario, $toast);
    }
  }

  public function updateemail(Request $request, $id)
  {
    if (!Auth::user()->can('permisos', 'Editar-usuarios') || Auth::user()->hasRole('Inactivo')) abort(403);

    $this->validate($request, [
      'email' => 'email|unique:users',
    ]);

    try {
      $usuario = User::find($id);
      if ($request->email) {
        $usuario->email = $request->email;
      }

      $usuario->save();

      $bitacora = new Bitacora();
      $bitacora->mensaje = 'Se editó email';
      $bitacora->registro_id = $usuario->id;
      $bitacora->user_id = Auth::user()->id;
      $bitacora->save();

      $toast = array(
        'title'   => 'Email modificado: ',
        'message' => $usuario->email,
        'background' => '#e1f6d0',
        'type' => 'success'
      );
      return [$usuario, $toast];
    } catch (\Throwable $th) {
      $toast = array(
        'title'   => 'Usuario no modificado: ',
        'message' => 'error',
        'type'    => 'error',
        'background' => '#edc3c3'
      );
      return response()->json($usuario, $toast);
    }
  }

  public function updaterol(Request $request, $id)
  {
    if (!Auth::user()->can('permisos', 'Editar-usuarios') || Auth::user()->hasRole('Inactivo')) abort(403);

    try {
      $usuario = User::find($id);
      if ($request->roles) {
        if ($request->roles == "Super Admin") {
          $toast2 = array(
            'title'   => 'roles modificados para: ',
            'message' => $usuario->username,
            'type'    => 'error'
          );
          return [$usuario, $toast];
        }
        if ($request->roles == "Inactivo") {
          $usuario->syncRoles("Inactivo");
          $toast2 = array(
            'title'   => 'roles modificados para: ',
            'message' => $usuario->username,
            'background' => '#e1f6d0',
            'type' => 'success'
          );
        } else {
          $arrayderoles = explode(",", $request->roles);
          $usuario->syncRoles([$arrayderoles]);
          $toast2 = array(
            'title'   => 'roles modificados para: ',
            'message' => $usuario->username,
            'background' => '#e1f6d0',
            'type' => 'success'
          );
        }
      } else {
        $usuario->removeRoles($usuario->roles);
        $toast2 = array(
          'title'   => 'roles modificados para: ',
          'message' => $usuario->username,
          'background' => '#e1f6d0',
            'type' => 'success' 
        );
      }
      cache()->tags('permisos')->flush();

      $bitacora = new Bitacora();
      $bitacora->mensaje = 'Se editó rol';
      $bitacora->registro_id = $usuario->id;
      $bitacora->user_id = Auth::user()->id;
      $bitacora->save();

      return [$usuario, $toast2];
    } catch (\Throwable $th) {
      $toast = array(
        'title'   => 'Usuario no modificado: ',
        'message' => 'error',
        'type'    => 'error',
        'background' => '#edc3c3'
      );
      return [$usuario, $toast];
    }
  }

  public function contactos()
  {
    $usuarios = User::paginate(9);
    return view('rrhh.contactos', compact('usuarios'));
  }

  public function importardatousuario(Request $request, $id)
  {

    if (!Auth::user()->can('permisos', 'Editar-usuarios')) {
      abort(403);
    }
    try {
      $usuario = User::find($id);
      $usuario->activo = 1;
      $usuario->save();
      //colocar comando de importacion de active directory
      // $uno = "ldap";
      // $dos = $usuario->username;
      // Artisan::call('route:list');

    } catch (\Throwable $th) {
      //throw $th;
    }
  }

  public function perfilusuario()
  {

    $usuario = User::find(Auth::user()->id);
    return view('usuarios.perfil', compact('usuario'));
  }

  public function showResetForm()
  {
    return view('auth.passwords.reset');
  }

  public function reset(ResetPassword $request)
  {

    $user = User::find(Auth::user()->id);

    $user->password = Hash::make($request->nuevo);

    $user->save();

    $msg = 'Contraseña cambiada';

    Session::flash('resetpass', $msg);

    return redirect('perfil');
  }

  public function exportar(Request $request){
    logger($request);
    if($request->exportar == "pdf")
    {
      if($request->exportarpagina == "actual")
      {
        $datos = User::select('id','name','username','email','activo')->with('roles')->paginate();
        $datos->chunk(5);
        return PDF::loadView('usuarios.exportar', compact('datos'))->download('exportado.pdf');
      }
      if($request->exportarpagina == "todo")
      {
        $datos = User::select('id','name','username','email','activo')->with('roles')->get();
        $datos->chunk(1000);
        return PDF::loadView('usuarios.exportar', compact('datos'))->download('exportado.pdf');
      }

    }

    if($request->exportar == "excel")
    {
      if($request->exportarpagina == "actual")
      {
        $usuarios = User::paginate();
        $vista = (string) "usuarios.exportar";
         return Excel::download(new DatosExport($vista,$usuarios), 'exportado.xlsx');
      }
      if($request->exportarpagina == "todo")
      {
        return (new datosallExport)->download('exportado.xlsx');
      }
    }

  }
}
