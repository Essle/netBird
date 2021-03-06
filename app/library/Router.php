<?php

namespace App;

/**
 * netBird/Router
 *
 * Маршрутизатор
 * 
 * @package netBird
 * @author Essle Jaxcate <me@essle.ru>
 * @copyright Copyright (c) 2013 netBird, Inc
 */
class Router {
	
	/**
	 * Массив описания http ошибок
	 *
	 * @var array
	 */
	private const HTTP_ERRORS = [
		'400' => 'Bad Request',
		'401' => 'Unauthorized',
		'403' => 'Forbidden',
		'404' => 'Not Found',
		'500' => 'Internal Server Error',
		'502' => 'Bad Gateway',
		'503' => 'Service Unavailable',
		'504' => 'Gateway Timeout'
	];

	/**
	 * Cписок страниц сайта
	 *
	 * @var array
	 */
	private static $map = [];

	/**
	 * Запуск маршрутизатора с указанием страниц сайта
	 * 
	 * @param array $map - список страниц
	 * @return bool
	 */
	public static function run(array $map) : bool {

		$params = array_keys($_GET);
		if(isset($params[0]) && $params[0] != 'route') {
			return self::error(400);
		}

		if(isset($_GET['route'])) {
			if($_GET['route'][0] != '/') {
				$_GET['route'] = '/' . $_GET['route'];
			}
		} else {
			$_GET['route'] = '/';
		}

		$controller = null;
		$matches = [
			'args' => [],
			'values' => []
		];

		foreach($map as $url => $c) {

			// Автоподстановка слэша адреса страницы
			if($url[0] != '/') {
				unset($map[$url]);
				$url = '/' . $url;
				$map[$url] = $c;
			}

			if(preg_match('/{([a-zA-Z]+)}/', $url, $matches['args']) != 1) {
				// Статический адрес страницы
				if($url == $_GET['route']) {
					$controller = $c;
					break;
				}
			} else {
				// Адрес страницы содержит параметры
				unset($matches['args'][0]);
				if(preg_match('/^' . preg_replace('/{[a-zA-Z]+}/', '([^\/]+)', str_replace('/', '\/', $url)) . '$/', $_GET['route'], $matches['values']) == 1) {
					unset($matches['values'][0]);
					$controller = $c;
					break;
				}
			}

		}

		self::$map = array_change_key_case(array_flip($map));

		if(is_null($controller)) {
			return self::error(404);
		}

		// Генерация контроллера страницы
		Generate::parsePageController($controller, $matches['args']);

		App::getController($controller, ($class), ($method));

		// Загрузка класса контроллера
		$path = Explorer::path('controller', $class);
		if(!file_exists($path)) {
			throw new EngineException('Неизвестный контроллер страницы');
		}
		require($path);
		$controller = new \Page\Controller;
		if(!method_exists($controller, $method)) {
			throw new EngineException('Неизвестный метод контроллера страницы');
		}

		if(!method_exists($controller, '__onPrevent') || $controller->__onPrevent($_GET['route'], $method) === true) {
			// Вызов метода контроллера
			$result = call_user_func_array([ $controller, $method ], $matches['values']);
			if($result !== false) {
				Assets::register();
				echo App::$template->render(($controller->view ?? strtolower($class . '-' . $method)) . '.tpl', (is_array($result) ? $result : []));
			}
		}
		
		exit;

	}

	/**
	 * Перенаправление на другую страницу
	 * 
	 * @param string $location - адрес или контроллер страницы
	 * @return void
	 */
	public static function go(string $location) : void {

		header('Location: ' . (self::$map[strtolower($location)] ?? $location));
		exit;

	}

	/**
	 * Вывод http ошибки
	 * 
	 * @param int $code - код ошибки
	 * @param bool $render - флаг отображения страницы с ошибкой
	 * @return bool
	 */
	public static function error(int $code, bool $render = true) : bool {

		$errorName = self::HTTP_ERRORS[(string)$code] ?? 'Undefined Error';

		header($header = ($_SERVER['SERVER_PROTOCOL'] . ' ' . $code . ' ' . $errorName));

		if($_SERVER['REQUEST_METHOD'] === 'POST') {
			exit($header);
		} else if($render) {
			Assets::set('css', [ 'normalize', 'error' ], 'system/');
			Assets::set('js', []);
			Assets::register();
			exit(App::$template->render('components/error-http.tpl', [
				'error' => $errorName
			]));
		} else {
			exit;
		}

		return false;

	}

}