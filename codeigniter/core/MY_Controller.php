<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Widget
{
    protected static $CI = false;

    public static final function run($widget, $params = array())
    {
        if (self::$CI == false) {
            self::$CI =& get_instance();
        }
        if (strpos($widget, '@') === false) {
            $widget .= '@index';
        }

        $widget = explode('@', $widget);
        $class = $widget[0];
        $class = strrpos($class, '/') !== false
            ? substr($class, 0, strrpos($class, '/')+1) . ucfirst(substr($class, strrpos($class, '/') + 1))
            : ucfirst($class);
        $method = $widget[1];
        $widgetFile = APPPATH .
            'widgets/' .
            $class .
            '.php';

        $class = strrpos($class, '/') !== false ? substr($class, strrpos($class, '/') + 1) : $class;

        if (!file_exists($widgetFile)) {
            show_error($widgetFile . ' is not exists');
            return;
        }

        include_once $widgetFile;

        if (
            !class_exists($class)
            || strncmp($method, '_', 1) == 0
            || !in_array(strtolower($method), array_map('strtolower', get_class_methods($class)))
        ) {
            show_error('Requested widget or method is not exists ('.$class.'::'.$method.')');
            return;
        }


        return call_user_func_array(array($class, $method), $params);
    }
}
