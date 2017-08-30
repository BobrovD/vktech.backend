<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 28.08.17
 * Time: 15:40
 */

function create_work($work){
    $table = 'work';
    $server = 'master';
    $query = 'INSERT INTO '.$table.' (cat, data, die, wait_for) VALUES (\''.$work['cat'].'\', \''.serialize($work['data']).'\', \''.$work['die'].'\', \''.$work['wait_for'].'\')';
    return query($table, $server, $query);
}