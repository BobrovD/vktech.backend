<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 30.08.17
 * Time: 13:52
 */

function create_task($user_id, $data){
    $table = 'task';
    $server = 'master';
    $time = time();
    $query = 'INSERT INTO '.$table.' (customer_id, time_creation, title, description, reward) VALUES (\''.$user_id.'\', \''.$time.'\', \''.$data['task_title'].'\', \''.$data['task_text'].'\', \''.$data['task_reward'].'\') ';
    query($table, $server, $query);
    return last_insert_id($table, $server);
}

function get_tasks_by_id_type($user_id, $type){
    $table = 'task';
    $server = 'slave';
    $query = 'SELECT * FROM '.$table.' WHERE '.$type.'_id = \''.$user_id.'\' ORDER BY task_id DESC';
    $task_list = query_ass($table, $server, $query);
    if(count($task_list) === 0)
        return [];
    foreach($task_list as $k => $task){
        $task_list[$k] = format_task_names($task);
    }
    return $task_list;
}

function get_tasks_global_list($filter = false){

}

function get_task_by_id($task_id, $user_id, $account_type){
    $table = 'task';
    $server = 'slave';
    $query = 'SELECT * FROM ' . $table . ' WHERE task_id = \'' . $task_id . '\' LIMIT 1';
    $task = query_ass_one($table, $server, $query);
    $task = format_task_names($task);
    if(!$user_id)
        return $task;
    if($account_type === 'customer' && $user_id === $task['customer']['id']){
        //подгружаем всех, кто предложил себя в роли исполнителя
    }
    if($account_type === 'executor' && !$task['customer']['id']){
        //уточняем, подписан ли я на это задание
    }
    return $task;
}

function format_task_names($task){
    $task['executor']['id'] = $task['executor_id'];
    $task['executor']['name'] = $task['executor_id'] ? get_user_name($task['executor_id']) : null;
    $task['customer']['id'] = $task['customer_id'];
    $task['customer']['name'] = get_user_name($task['customer_id']);
    unset($task['executor_id']);
    unset($task['customer_id']);
    return $task;
}