<?php
declare(strict_types=1);

use Sura\Libs\Langs;
use Sura\Libs\Registry;
use Sura\Libs\Request;
use Sura\View\View;
use function Sura\resolve;

if (!function_exists('view')) {
    /**
     * Run the blade engine. It returns the result of the code.
     *
     * @param string|null $view The name of the cache. Ex: "folder.folder.view" ("/folder/folder/view.blade")
     * @param array $variables An associative arrays with the values to display.
     * @return int
     */
    function view(?string $view, mixed $variables = []): int
    {
        return _e(view_data($view, $variables));
    }
}

if (!function_exists('view_data')) {


    function view_data(?string $view, $variables = []): string
    {
        $views = resolve('app')->get('path.base') . DIRECTORY_SEPARATOR . 'app/views';
        $cache = resolve('app')->get('path.base') . DIRECTORY_SEPARATOR . 'app/cache/views';

        $blade = new myView($views, $cache, View::MODE_AUTO); // MODE_DEBUG allows to pinpoint troubles.
        $blade::$dictionary = langs::get_langs();

//        $variables['title'] = 'Sura';
        $variables['url'] = 'https://' . $_SERVER['HTTP_HOST'];
        $blade->setBaseUrl('https://' . $_SERVER['HTTP_HOST']);

        $blade->csrfIsValid(true, '_mytoken');
        $logged = Registry::get('logged');
        if ($logged) {
            $user = Registry::get('user_info');
            $variables['user'] = $user;
            $blade->setAuth($user['user_search_pref'], 'user');
//        $blade->setAuth('john_doe','user');
        }
        try {
            if (Request::ajax()) {
                $json_content = $blade->run($view, $variables);
                $title = $variables['title'];
                if ($logged) {
                    $result_ajax = array(
                        'title' => $title,
//                        'new_notifications' => $params['notify_count'],
                        'content' => $json_content
                    );
                } else {
                    $result_ajax = array(
                        'title' => $title,
                        'content' => $json_content
                    );
                }
                header('Content-Type: application/json');
                $json = json_encode($result_ajax, JSON_THROW_ON_ERROR);
                $response = $blade->run("app.json", ['json' => $json]);
            } else {

                header('Access-Control-Allow-Origin: *');
                $response = $blade->run($view, $variables);
            }
        } catch (Exception $e) {
            $response = "error found " . $e->getMessage() . "<br>" . $e->getTraceAsString();
        }
        return ($response);
    }
}

if (!function_exists('_e')) {
    /**
     * Encode HTML special characters in a string.
     *
     * @param string $value
     * @return int
     */
    function _e(string $value): int
    {
        return print($value);
    }
}