<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 20.08.17
 * Time: 18:44
 */

function show_statistic()
{
    //демо
    $query = 'SELECT * FROM script_time ORDER BY id DESC LIMIT 0, 10000';
    $server = 'slave';
    $mysqli_result = query(defines\MySQL::CONNECTION['script_time']['name'], $server, $query);
    $result = [];
    while($row = mysqli_fetch_array($mysqli_result))
    {
        $result[] = $row;
    }
    print_statistic($result);
}

function print_statistic($statistic)
{
    $result = [];
    foreach($statistic as $row) {
        $result['['.$row['method'].']'.$row['path']]['count'] += 1;
        $result['['.$row['method'].']'.$row['path']]['time'] += $row['time'];
    }

    foreach($result as $key => $res){
        echo '<hr />' . round($res['time'] / $res['count'], 2) . ' ms : ' . $key . ': total ' . $res['count'];
    }
}

function register_script_time($url, $method, $time_start)
{
    $time = round((microtime() - $time_start), 6) * 1000;
    if($time < 0)
        return;
    //игнорим HEAD запросы
    if($method === 'HEAD')
        return ;
    $query = 'INSERT INTO script_time (path, method, at, time) VALUES (\''.$url.'\', \''.$method.'\', CURRENT_TIMESTAMP, \''.$time.'\')';
    $server = 'master';
    query(defines\MySQL::CONNECTION['script_time']['name'], $server, $query);
}