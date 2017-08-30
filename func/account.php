<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 21.08.17
 * Time: 12:40
 */

function create_account($account)
{
    $account['phone'] = isset($account['phone']) ? $account['phone'] : '';
    $table = 'account';
    $server = 'master';
    $query = 'INSERT INTO '.$table.' (email, password, fname, sname, phone, time_creation) VALUES ( \''. $account['email'] .'\', \''. $account['password'] .'\', \''. $account['fname'] .'\', \''. $account['sname'] .'\', \''. $account['phone'] .'\', UNIX_TIMESTAMP() )';
    return query($table, $server, $query);
}

function get_account_by_email($email, $auth_rows = true, $from_master = false)
{
    //на случай, если придётся доставать данные о пользователе сразу после изменения/создания записи
    $server = $from_master ? 'master' : 'slave';
    $rows = $auth_rows ? 'id, password, fname, sname' : '*';
    $table = 'account';
    $query = 'SELECT '.$rows.' FROM '.$table.' WHERE email = \''.$email.'\' LIMIT 1';
    return query_ass_row($table, $server, $query);
}

function get_account_by_id($id, $needed_rows = null, $from_master = false)
{
    $server = $from_master ? 'master' : 'slave';
    $table = 'account';
    $rows = $needed_rows ?? 'email, fname, sname, balance, salary';
    $query = 'SELECT '.$rows.' FROM '.$table.' WHERE id = \''.$id.'\' LIMIT 1';
    return query_ass_row($table, $server, $query);
}

function account_exists($email)
{
    $table = 'account';
    $server = 'slave';
    $query = 'SELECT id FROM account WHERE email = \''.$email.'\' LIMIT 1';
    return query($table, $server, $query)->num_rows;
}

function check_session($token, $account_type)
{
    if($token === null || $account_type === null)
        return false;
    $table = 'session';
    $server = 'slave';
    $query = 'SELECT id FROM '.$table.' WHERE token = \''.$token.'\' AND account_type = \''.$account_type.'\' LIMIT 1';
    if($id = query_ass_one($table, $server, $query)){
        update_last_online_ip($token);
        return $id;
    }
    return false;
}

function update_last_online_ip($token){
    $table = 'session';
    $server = 'master';
    $time = time();
    $ip = $_SERVER['HTTP_X_REAL_IP'];
    $query = 'UPDATE '.$table.' SET last_active = \''.$time.'\', ip = INET_ATON(\''.$ip.'\') WHERE token = \''.$token.'\'';
    //echo $query;
    return query($table, $server, $query);
}

function set_new_token($id, $type)
{
    $token = bin2hex(random_bytes(16));//Длина токена выходит в 2 раза длиннее.
    $ip = $_SERVER['HTTP_X_REAL_IP'];
    $time = time();
    $table = 'session';
    $server = 'master';
    $user_agent = get_user_os();
    $query = 'INSERT INTO '.$table.' (id, ip, user_agent, token, account_type, last_active) VALUES (\''.$id.'\', INET_ATON(\''.$ip.'\'), \''.$user_agent.'\', \''.$token.'\', \''.$type.'\', \''.$time.'\');';
    if(query($table, $server, $query)){
        return $token;
    }
    return false;
}

function get_session($id, $type)
{
    $ip = $_SERVER['HTTP_X_REAL_IP'];
    $user_agent = get_user_os();
    $table = 'session';
    $server = 'slave';
    $query = 'SELECT token FROM '.$table.' WHERE id = \''.$id.'\' AND account_type = \''.$type.'\' AND  user_agent = \''.$user_agent.'\' AND ip = INET_ATON(\''.$ip.'\');';
    return query_ass_one($table, $server, $query);
}

function delete_session($token, $account_type)
{
    $user_agent = get_user_os();
    $table = 'session';
    $server = 'master';
    $query = 'DELETE FROM '.$table.' WHERE token = \''.$token.'\' AND user_agent = \''.$user_agent.'\' AND account_type = \''.$account_type.'\' LIMIT 1;';
    query($table, $server, $query);
    return true;
}

function delete_session_by_id($id, $session_id)
{
    $table = 'session';
    $server = 'master';
    $query = 'DELETE FROM '.$table.' WHERE id = \''.$id.'\' AND row_id = \''.$session_id.'\' LIMIT 1;';
    echo $query;
    return query($table, $server, $query);
}

function get_session_list($user_id){
    $table = 'session';
    $server = 'slave';
    $query = 'SELECT row_id, INET_NTOA(ip) as ip, account_type, token, user_agent, last_active FROM '.$table.' WHERE id = \''.$user_id.'\'';
    return query_ass($table, $server, $query);
}

function up_balance($user_id, $summ){
    $table = 'account';
    $server = 'master';
    $query = 'UPDATE '.$table.' SET balance = balance + \''.$summ.'\' WHERE id = \''.$user_id.'\'';
    return query($table, $server, $query);
}

function up_salary($user_id, $summ){
    $table = 'account';
    $server = 'master';
    $query = 'UPDATE '.$table.' SET salary = salary + \''.$summ.'\' WHERE id = \''.$user_id.'\'';
    return query($table, $server, $query);
}

function remind_pwd($email){
    $pwd = generate_pwd();
    if(!update_account_pwd_by_email($email, $pwd)){
        return false;
    }
    $work = [];
    $work['cat'] = 'notification';
    $work['data'] = [
        'action' => 'send_new_pwd',
        'email' => $email,
        'pwd' => $pwd
    ];
    $work['die'] = 0;
    $work['wait_for'] = time();
    create_work($work);
    return true;
}

function generate_pwd(){
    return substr(md5(rand(0,9999)), rand(0,15), 16);
}

function update_account_pwd_by_email($email, $pwd){
    $table = 'account';
    $server = 'master';
    $query = 'UPDATE '.$table.' SET password = \''.$pwd.'\' WHERE email = \''.$email.'\' LIMIT 1';
    return query($table, $server, $query);
}

function get_user_name($user_id){
    global $mem;
    $name = $mem->get('name_'.$user_id);
    if(!$name){
        $table = 'account';
        $server = 'slave';
        $query = 'SELECT CONCAT(fname, \' \', sname) as name FROM '.$table.' WHERE id = \''.$user_id.'\' LIMIT 1';
        $name = query_ass_one($table, $server, $query);
        $mem->set('name_'.$user_id, $name, defines\Limit::REMEMBER_USER_NAME);
    }
    return $name;
}