<?php

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

use Phalcon\DI;
use Phalcon\Loader;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Http\Response;
use Phalcon\Http\Request;
use Phalcon\Mvc\View;
use Phalcon\Db\Adapter\Pdo\Mysql as Database;
use Phalcon\Mvc\Application as BaseApplication;

class Application extends BaseApplication
{
	protected function registerAutoloaders()
	{
		$loader = new Loader();

		$loader->registerDirs(array(
			'../apps/base/',
			'../apps/controllers/',
			'../apps/models/'
		));

		$loader->register();
	}

	protected function registerServices()
	{
		$di = new DI();

		$di->set('router', function() {
			$router = new Router();

			$router->setUriSource(Router::URI_SOURCE_SERVER_REQUEST_URI);
			$router->add(
				'/orders/', [
					'controller' => 'index',
					'action'     => 'orders',
				]
			);

			return $router;
		});

		$di->set('dispatcher', function() {
			return new Dispatcher();
		});

		$di->set('response', function() {
			return new Response();
		});

		$di->set('request', function() {
			return new Request();
		});

		$di->set('view', function() {
			$view = new View();
			$view->setViewsDir('../apps/views/');
			$view->setLayout('main');
			return $view;
		});

		$di->set('db', function() {
			return new Database(array(
				"host" => "localhost",
				"username" => "orderist",
				"password" => "zeMjPeBKeEu5nSpmmVqh",
				"dbname" => "orderist"
			));
		});

		$this->setDI($di);
	}

	public function main()
	{
		$this->registerServices();
		$this->registerAutoloaders();

		echo $this->handle()->getContent();
	}
}

try {
	$application = new Application();
	$application->main();
} catch (\Exception $e) {
	echo $e->getMessage();
}
