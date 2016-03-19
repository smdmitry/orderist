<?php

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
			'../apps/lib/',
			'../apps/controllers/',
			'../apps/dao/',
			'../apps/models/'
		));

		$loader->register();
	}

	protected function registerServices()
	{
		$di = $this->getDI();

		$settings = $di->get('settings');

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

		$di->set('config', function() use ($settings) {
			return $settings['config'];
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
		$di->set('tag', function () {
			return new BaseTag();
		});

		$di->set('db', function() use ($settings) {
			return new DbDriverBase($settings['database']);
		});
	}

	public function main()
	{
		$this->registerServices();
		$this->registerAutoloaders();
		echo $this->handle()->getContent();
	}
}

try {
	require '../apps/config/base.php';

	$di = new DI();
	$di->set('settings', function() use ($settings) {
		return $settings;
	});

	$application = new Application($di);
	$application->main();

	if (BackgroundWorker::i()->hasJob()) {
		BackgroundWorker::i()->doJob();
	}
} catch (\Exception $e) {
	$view = new \Phalcon\Mvc\View();
	$view->setViewsDir('../apps/views/layouts');
	$view->message = $e->getMessage();
	header('HTTP/1.1 503 Service Temporarily Unavailable');
	header('Status: 503 Service Temporarily Unavailable');
	echo $view->partial('error');
}
