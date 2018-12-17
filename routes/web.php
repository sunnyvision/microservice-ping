<?php
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/{any:.*}', function ($any) use ($router) {
	$return = array();
	$return['version'] = '(c) sunnyvision - ' . env('VERSION');
	$return['answered_at'] = date("Y-m-d H:i:s");
	$return['queried_by'] = empty($_SERVER['X_HTTP_FORWARDED_FOR']) ? $_SERVER['REMOTE_ADDR'] : $_SERVER['X_HTTP_FORWARDED_FOR'];
	return $return;
});

$router->post('/icmp/{host}', ['middleware' => 'auth', "uses" => "PingController@icmp"]);
$router->post('/tcp/{host}:{port}', ['middleware' => 'auth', "uses" => "PingController@tcp"]);
