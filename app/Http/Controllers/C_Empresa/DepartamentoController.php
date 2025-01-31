<?php

namespace App\Http\Controllers\C_Empresa;

use App\Http\Controllers\Controller;
use App\Models\M_Empresa\Departamento;
use App\Models\M_Empresa\Ubicacion;
use App\Models\M_Empresa\Cargo;
use Illuminate\Http\Request;
use App\Bitacora;
use Auth;

class DepartamentoController extends Controller
{

    public function index()
    {
      if(!Auth::user()->can('permisos', 'Navegar-departamentos') || Auth::user()->hasRole('Inactivo')) abort(403);

      return view('empresa.departamento.index');
    }

    public function obtenerdepartamentos(Request $request)
    {
      if(!Auth::user()->can('permisos', 'Navegar-departamentos') || Auth::user()->hasRole('Inactivo')) abort(403);

      try {
      
        $columns = ['nombre', 'descripcion', 'encargado', 'ubicacion_id', 'ver','editar', 'eliminar'];

        $length = $request->input('length');
        $column = $request->input('column');
        $dir = $request->input('dir');
        $searchValue = $request->input('search');
        $searchColumn = $request->input('searchColumn');

        $query = Departamento::select('id', 'nombre', 'descripcion', 'encargado', 'ubicacion_id')->with('ubicacion')->orderBy($columns[$column], $dir);

        if ($searchValue) {
            $query->where(function($query) use ($searchValue, $searchColumn) {
                $query->where($searchColumn, 'like', '%' . $searchValue . '%');
            });
        }

        $departamentos = $query->paginate($length);
        return ['data' => $departamentos, 'draw' => $request->input('draw')];
         
      } catch (\Throwable $th) {
        //throw $th;
      }
    }

    public function create()
    {
      if(!Auth::user()->can('permisos', 'Crear-departamentos') || Auth::user()->hasRole('Inactivo')) abort(403);

      $cargos = Cargo::select('nombre')->get()->toArray();
      $ubicaciones = Ubicacion::select('id', 'nombre')->get()->toArray();
      return view('empresa.departamento.create', compact('ubicaciones', 'cargos'));
    }

    public function store(Request $request)
    {
      if(!Auth::user()->can('permisos', 'Crear-departamentos') || Auth::user()->hasRole('Inactivo')) abort(403);

      $this->validate($request, [
          'nombre' => 'required|string',
          'descripcion' => 'required|string',
          'ubicacion' => 'required'
      ]);
      try {
        
        $departamento = new Departamento();
        $departamento->nombre = $request->nombre;
        $departamento->descripcion = $request->descripcion;
        $departamento->encargado = $request->encargado;
        $departamento->ubicacion_id = $request->ubicacion;
        $departamento->save();

        $bitacora = new Bitacora();
        $bitacora->mensaje = 'Se creó el departamento';
        $bitacora->registro_id = $departamento->id;
        $bitacora->user_id = Auth::user()->id;
        $bitacora->save();
        
        $toast = array(
          'title'   => 'Departamento creado: ',
          'message' => $departamento->nombre,
          'background' => '#e1f6d0',
          'type' => 'success'
        );

        return [$departamento,$toast];
        
      } catch (\Throwable $th) {
          $toast = array(
            'title'   => 'Error',
            'message' => $th,
            'type'    => 'error',
          'background' => '#edc3c3'
          );
          return [$request,$toast];
      }

    }

    public function show(Departamento $departamento)
    {
      if(!Auth::user()->can('permisos', 'Ver-departamentos') || Auth::user()->hasRole('Inactivo')) abort(403);

      return view('empresa.departamento.show', compact('departamento'));
    }

    public function edit(Departamento $departamento)
    {
      if(!Auth::user()->can('permisos', 'Editar-departamentos') || Auth::user()->hasRole('Inactivo')) abort(403);

      $cargos = Cargo::select('nombre')->get()->toArray();
      $ubicaciones = Ubicacion::select('id', 'nombre')->get()->toArray();
      return view('empresa.departamento.edit', compact('departamento','cargos', 'ubicaciones'));
    }

    public function update(Request $request, Departamento $departamento)
    {
      if(!Auth::user()->can('permisos', 'Editar-departamentos') || Auth::user()->hasRole('Inactivo')) abort(403);

      $this->validate($request, [
        'nombre' => 'required|string',
        'descripcion' => 'required|string',
        'ubicacion_id' => 'required'
      ]);
      try {
        $departamento->nombre = $request->nombre;
        $departamento->descripcion = $request->descripcion;
        $departamento->encargado = $request->encargado;
        $departamento->ubicacion_id = $request->ubicacion_id;
        $departamento->save();
        
        $bitacora = new Bitacora();
        $bitacora->mensaje = 'Se editó el departamento';
        $bitacora->registro_id = $departamento->id;
        $bitacora->user_id = Auth::user()->id;
        $bitacora->save();

        $toast = array(
            'title'   => 'Departamento modificado: ',
            'message' => $departamento->nombre,
            'background' => '#e1f6d0',
            'type' => 'success'
        );

        return [$departamento ,$toast];
       
      } catch (\Throwable $th) {
        $toast = array(
          'title'   => 'Error: ',
          'message' => 'Error inesperado, contacte al administrdor',
          'type'    => 'error',
          'background' => '#edc3c3'
      );

      return [$request ,$toast];
      }
    }

    public function destroy(Departamento $departamento)
    {
      if(!Auth::user()->can('permisos', 'Eliminar-departamentos') || Auth::user()->hasRole('Inactivo')) abort(403);
      
      if($departamento->areas()->count())
      {
        $toast = array(
          'title'   => 'Error: ',
          'message' => 'No se puede quitar, departamento tiene areas dependientes',
          'type'    => 'error',
          'background' => '#edc3c3'
        );
        return $toast;
      }
      $departamento->delete();

      $bitacora = new Bitacora();
      $bitacora->mensaje = 'Se eliminó el departamento';
      $bitacora->registro_id = $departamento->id;
      $bitacora->user_id = Auth::user()->id;
      $bitacora->save();

      $toast = array(
        'background' => '#e1f6d0',
        'type' => 'success',
        'title'   => 'Departamento eliminado: ',
        'message' => '',
      );
      return $toast;
    }
}
