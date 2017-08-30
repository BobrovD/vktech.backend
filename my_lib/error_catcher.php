<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 19.08.17
 * Time: 0:32
 */

function error_catcher()
{
    // регистрация ошибок
    set_error_handler('other_error_catcher');

    // перехват критических ошибок
    register_shutdown_function('fatal_error_catcher');

    // создание буфера вывода
    ob_start();

}

function other_error_catcher($errno, $errstr)
{
    // - записать в лог
    //а стоит ли?
    //log_error($errno . ' / ' .$errstr);
    return false;
}

function fatal_error_catcher()
{
    $error = error_get_last();
    if (isset($error))
        if(
            $error['type'] == E_ERROR ||
            $error['type'] == E_PARSE ||
            $error['type'] == E_COMPILE_ERROR ||
            $error['type'] == E_CORE_ERROR
        ){
            ob_end_clean();	// сбросить буфер, завершить работу буфера

            global $error_message;

            $error['message'] .= $error_message;

            $error_output = $error_message ? $error_message : 'Code: '.$error['type'].'; File: '.$error['file'].'; Line: '.$error['line'];

            log_error($error_output);

            header('Error: '.$error_output);
            header("HTTP/1.0 500 Internal server error");
            // контроль критических ошибок:
            // - записать в лог
            // - вернуть заголовок 500
            // - вернуть после заголовка данные для пользователя
        }else{
            ob_end_flush();	// вывод буфера, завершить работу буфера
        }
    else{
        ob_end_flush();	// вывод буфера, завершить работу буфера
    }
}

function log_error($message)
{
    $query = 'INSERT INTO error VALUES (NULL, \'' . time() . '\', \'' . $message . '\')';
    $server = 'master';
    query(defines\MySQL::CONNECTION['error']['name'], $server, $query);
}

function show_last_errors()
{
    $query = 'SELECT * FROM error ORDER BY id DESC LIMIT 0, 1000';
    $server = 'slave';
    $errors = query_ass(defines\MySQL::CONNECTION['error']['name'], $server, $query);
    print_errors($errors);
}

function print_errors($errors)
{
    if(count($errors) === 0){
        echo 'no errors';
        return ;
    }
    foreach ($errors as $error){
        echo '<hr />' . gmdate("Y-m-d\TH:i:s", $error['timestamp']) . ' : ' . $error['message'];
    }
}