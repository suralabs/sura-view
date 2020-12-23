<?php

use Sura\Libs\Langs;
use Sura\Libs\Registry;
use Sura\Libs\Request;
use Sura\View\Blade;

/**
 * Run the blade engine. It returns the result of the code.
 *
 * @param string|null $view The name of the cache. Ex: "folder.folder.view" ("/folder/folder/view.blade")
 * @param array $variables An associative arrays with the values to display.
 * @return string
 * @throws Exception
 */
function view(?string $view, $variables = []): string
{
    $views = __DIR__ . '/views';
    $cache = __DIR__ . '/cache/views';

    class myBlade extends Blade
    {
        use Sura\View\Lang;
    }

    $blade = new myBlade($views,$cache,Blade::MODE_AUTO); // MODE_DEBUG allows to pinpoint troubles.
    $blade::$dictionary = langs::get_langs();

    $variables['url'] = 'https://'.$_SERVER['HTTP_HOST'];
    $blade->setBaseUrl('https://'.$_SERVER['HTTP_HOST']);

    $blade->csrfIsValid(true, '_mytoken');
    $logged = Registry::get('logged');
    if (!empty($logged)){
        $blade->setAuth('john_doe','user');
    }

    try {
        if (Request::ajax()){
            $json_content = $blade->run($view, $variables);
            $title = $variables['title'];
            if (!empty($logged)){
                $result_ajax = array(
                    'title' => $title,
//                        'new_notifications' => $params['notify_count'],
                    'content' => $json_content
                );
            }else{
                $result_ajax = array(
                    'title' => $title,
                    'content' => $json_content
                );
            }
            header('Content-Type: application/json');
            $response = json_encode($result_ajax);
        }else{

            header('Access-Control-Allow-Origin: *');
            $response = $blade->run($view, $variables);
        }
    } catch (Exception $e) {
        $response = "error found ".$e->getMessage()."<br>".$e->getTraceAsString();
    }
    return _e($response);
}

if (! function_exists('_e')) {
    /**
     * Encode HTML special characters in a string.
     *
     * @param string $value
     * @return string
     */
    function _e(string $value): string
    {
        return print($value);
    }
}