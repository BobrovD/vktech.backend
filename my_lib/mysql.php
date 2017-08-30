<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 19.08.17
 * Time: 0:44
 */

$connections = [];

function query($table, $server, $query)
{
    $connection = get_connection($table);
    return mysqli_query($connection[$server], $query);
}

//associative result
function query_ass($table, $server, $query, $result_type = MYSQLI_ASSOC)
{
    $mysqli_result = query($table, $server, $query);
    $result = [];
    while ($row = mysqli_fetch_array($mysqli_result, $result_type)) {
        $result[] = $row;
    }
    return $result;
}

function query_ass_row($table, $server, $query, $result_type = MYSQLI_ASSOC)
{
    return query_ass($table, $server, $query, $result_type)[0];
}

function query_ass_one($table, $server, $query)
{
    return query_ass_row($table, $server, $query, $result_type = MYSQLI_NUM)[0];
}

function last_insert_id($table, $server)
{
    return mysqli_insert_id(get_connection($table)[$server]);
}

function get_connection($table)
{
    global $connections;
    //если для этой таблицы ещё не установлены соединения
    if(!isset($connections[$table]))
    {
        //то коннектимся к базе и записываем в $connections
        $opts = get_options($table);
        $connections[$table] = connect($opts);
    }
    return $connections[$table];
}

//подключимся к master серверу, а если он лежит, то к slave
function connect($opts)
{
    if(!$connection['master'] = mysqli_connect($opts['master']['server'], $opts['master']['user'], $opts['master']['password'], $opts['database'])) {
        //если master недоступен, то коннектимся к slave как к master
        $connection['master'] = mysqli_connect($opts['slave']['server'], $opts['slave']['user'], $opts['slave']['password'], $opts['database']);
        $connection['slave']  = $connection['master'];
    }
    if(!$connection['slave'] = mysqli_connect($opts['slave']['server'], $opts['slave']['user'], $opts['slave']['password'], $opts['database'])) {
        //если slave недоступен, то коннектимся к master как к slave
        $connection['slave']  = $connection['master'];
    }
    mysqli_set_charset($connection['master'], 'utf8');
    mysqli_set_charset($connection['slave'], 'utf8');
    return $connection;
}

//выбираем к каким серверам подключаться
function get_options($table)
{
    return defines\MySQL::CONNECTION[$table];
}

//чистим за собой
function close_connections()
{
    global $connections;
    foreach ($connections as $key => $connection)
    {
        mysqli_close($connection);
        unset($connections[$key]);
    }
}