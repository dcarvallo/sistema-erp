<?php

use Illuminate\Support\Facades\Route;

//admin

//usuarios


Route::middleware(['auth'])->group(function () {
	
	Route::get('admin', function(){
		return view('admin.index');
	});
	// Route::resource('usuarios', 'C_Admin\UserController');
	
	Route::get('obtenerusuarios', 'C_Admin\UserController@obtenerusuarios');

	Route::get('filemanager', 'C_Admin\AdminController@gestionararchivos')->name('admins.archivos')
		->middleware('can:admins.archivo');

	Route::put('importardatousuario/{id}', 'C_Admin\UserController@importardatousuario');
	
	//Roles
	Route::post('roles/store', 'C_Admin\RoleController@store')->name('roles.store')
		->middleware('can:roles.create');

	Route::get('roles', 'C_Admin\RoleController@index')->name('roles.index')
		->middleware('can:roles.index');

	Route::get('roles/create', 'C_Admin\RoleController@create')->name('roles.create')
		->middleware('can:roles.create');

	Route::put('roles/{role}', 'C_Admin\RoleController@update')->name('roles.update')
		->middleware('can:roles.edit');

	Route::get('roles/{role}', 'C_Admin\RoleController@show')->name('roles.show')
		->middleware('can:roles.show');

	Route::delete('roles/{role}', 'C_Admin\RoleController@destroy')->name('roles.destroy')
		->middleware('can:roles.destroy');

	Route::get('roles/{role}/edit', 'C_Admin\RoleController@edit')->name('roles.edit')
    ->middleware('can:roles.edit');
    
  Route::get('obtenerroles', 'C_Admin\RoleController@obtenerroles');


	//Users
	Route::get('users/create', 'C_Admin\UserController@create')->name('users.create')
		->middleware('can:users.create');

	Route::get('users/index', 'C_Admin\UserController@index')->name('users.index')
		->middleware('can:users.index');

	Route::put('users/{user}', 'C_Admin\UserController@update')->name('users.update')
    ->middleware('can:users.edit');
    
	Route::put('users/um/{user}', 'C_Admin\UserController@updateemail')->name('users.updatemail')
    ->middleware('can:users.edit');
    
	Route::put('users/up/{user}', 'C_Admin\UserController@updatepass')->name('users.updatepass')
    ->middleware('can:users.edit');
    
	Route::put('users/ur/{user}', 'C_Admin\UserController@updaterol')->name('users.updaterol')
		->middleware('can:users.edit');

	Route::get('users/{user}', 'C_Admin\UserController@show')->name('users.show')
		->middleware('can:users.show');

	Route::delete('users/{user}', 'C_Admin\UserController@destroy')->name('users.destroy')
		->middleware('can:users.destroy');

	Route::get('users/{user}/edit', 'C_Admin\UserController@edit')->name('users.edit')
		->middleware('can:users.edit');

	Route::post('users/store', 'C_Admin\UserController@store')->name('users.store')
		->middleware('can:users.store');
	


});