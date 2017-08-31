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
    $id = last_insert_id($table, $server);
    if($id) {
        //обносить кэш надо сразу же
        update_mem_row('global_task_list_20');
    }
    return $id;
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

function get_tasks_global_list($page = 0, $filter = false){
    global $mem;
    $task_list = $mem->get('task_global_list_30');
    if(!$task_list){
        $task_list = get_tasks_global_list_from_bd($page, $filter);
        $mem->set('task_global_list_20', $task_list, 30);
    }
    return $task_list;
}

function get_tasks_global_list_from_bd($page = 0, $filter = false){
    $table = 'task';
    $server = 'slave';
    $query = 'SELECT * FROM '.$table.' ORDER BY task_id DESC LIMIT 0, 30';
    $task_list = query_ass($table, $server, $query);
    foreach($task_list as $k => $task){
        $task_list[$k] = format_task_names($task);
    }
    return $task_list;
}

function get_task_by_id($task_id, $user_id = false, $account_type = false){
    $table = 'task';
    $server = 'slave';
    $query = 'SELECT * FROM ' . $table . ' WHERE task_id = \'' . $task_id . '\' LIMIT 1';
    $task = query_ass_row($table, $server, $query);
    $task = format_task_names($task);
    if(!$user_id)
        return $task;
    if($account_type === 'customer' && $user_id === $task['customer']['id']){
        //подгружаем всех, кто предложил себя в роли исполнителя
        $task['executor']['list'] = get_executors_subscribes($task_id);
    }
    if($account_type === 'executor' && !$task['executor']['id']){
        //уточняем, подписан ли я на это задание
        $task['is_im_subscriber'] = is_im_subscribe_on_task($task_id, $user_id);
    }
    return $task;
}

function remove_task($task_id, $user_id){
    global $mem;
    $table = 'task';
    $server = 'master';
    $task = get_task_by_id($task_id);
    if($task['customer']['id'] !== $user_id)
        return false;
    $query = 'DELETE FROM '.$table.' WHERE customer_id = \''.$user_id.'\' AND task_id = \''.$task_id.'\' AND status = \'actual\' LIMIT 1';
    $result = query($table, $server, $query);
    if($result){
        $tasks = $mem->get('global_task_list_20');
        foreach ($tasks as $task){
            if($task['task_id'] === $task_id){
                //если задача была среди 20, то надо обновить кэш
                update_mem_row('global_task_list_20');
                break;
            }
        }
        up_balance($user_id, $task['reward']);
        return true;
    }
    return false;
}

function update_task($task_id, $params){
    $table = 'task';
    $server = 'master';
    $upd = '';
    foreach($params as $key => $param){
        if($upd !== ''){
            $upd .= ', ';
        }
        $upd .= ' '.$key.' = \''.$param.'\' ';
    }
    $query = 'UPDATE '.$table.' SET '.$upd.' WHERE task_id = \''.$task_id.'\'';
    echo $query;
    return query($table, $server, $query);
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

function get_executors_subscribes($task_id){
    $table = 'task_subscribes';
    $server = 'slave';
    $query = 'SELECT executor_id FROM '.$table.' WHERE task_id = \''.$task_id.'\'';
    $exec_list = query_ass($table, $server, $query);
    if(count($exec_list) === 0){
        return [];
    }
    $result = [];
    foreach ($exec_list as $key => $exec) {
        $result[$key]['id'] = $exec['executor_id'];
        $result[$key]['name'] = get_user_name($exec['executor_id']);
    }
    return $result;
}

function subscribe_on_task($user_id, $task_id){
    $task = get_task_by_id($task_id);
    if($task['customer']['id'] === $user_id){
        return false;
    }
    $table = 'task_subscribes';
    $server = 'master';
    $query = 'INSERT INTO '.$table.' (task_id, executor_id, mask) VALUES (\''.$task_id.'\', \''.$user_id.'\', \''.$task_id.'_'.$user_id.'\')';
    if(query($table, $server, $query)){
        $work['cat'] = 'notification';
        $work['data'] = [
            'action' => 'new_subscriber',
            'task_id' => $task_id,
            'customer_id' => $task['customer']['id'],
            'executor_id' => $user_id
        ];
        create_work($work);
        return true;
    }
    return false;
}

function unsubscribe_from_task($user_id, $task_id){
    $table = 'task_subscribes';
    $server = 'master';
    $query = 'DELETE FROM '.$table.' WHERE executor_id = \''.$user_id.'\' AND task_id = \''.$task_id.'\'';
    return query($table, $server, $query);
}

function is_im_subscribe_on_task($task_id, $user_id){
    $table = 'task_subscribes';
    $server = 'slave';
    $query = "SELECT executor_id FROM ".$table.' WHERE task_id = \''.$task_id.'\' AND executor_id = \''.$user_id.'\' LIMIT 1';
    return query_ass_one($table, $server, $query);
}