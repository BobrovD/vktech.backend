<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 20.08.17
 * Time: 10:39
 */

function printr($var)
{
    echo '<pre>';
    print_r($var);
    echo '</pre>';
}

function validate_input_data($type, $data)
{
    $pattern = '/^$/';
    switch($type)
    {
        case 'fname' :
            $pattern = '/^[a-zA-Zа-яА-ЯёЁ]{2,30}$/u';
            break;
        case 'sname':
            $pattern = '/^[a-zA-Zа-яА-ЯёЁ\-]{2,30}$/u';
            break;
        case 'phone':
            $pattern = '/(^(?!\+.*\(.*\).*\-\-.*$)(?!\+.*\(.*\).*\-$)(\+[0-9]{1,3}\([0-9]{1,3}\)[0-9]{1}([-0-9]{0,8})?([0-9]{0,1})?)$)|(^[0-9]{1,4}$)/';
            break;
        case 'email' :
            $pattern = '/^(?!.*@.*@.*$)(?!.*@.*\-\-.*\..*$)(?!.*@.*\-\..*$)(?!.*@.*\-$)(.*@.+(\..{1,11})?)$/u';
            break;
        case 'password':
            $pattern = '/^[a-zA-Z0-9\-\!\_\#\%]{6,20}$/u';
            break;
        case 'account_type' :
            return ($data === 'executor' || $data === 'customer');
            break;
        case 'md5':
        case 'token' :
            //должно работать шустрее, чем регулярка /^[a-f0-9]{32}$/
            return (ctype_xdigit($data) && strlen($data) === 32);
            break;
        case 'int' :
            //или строка с INT или INT
            return (ctype_digit($data) || ((int)$data == $data));
        case 'summ' :
            //сумма должна быть указана с копейками,  от 0.00 до 999999.99
            //или просто int
            $summ_arr = explode('.', $data);
            if(count($summ_arr) == 1){
                return ctype_digit($data);
            }
            $r = $summ_arr[0];
            $k = $summ_arr[1];
            return (
                strlen($r) <= 6 &&
                strlen($k) == 2 &&
                ctype_digit($r) &&
                ctype_digit($k)
            );
            break;
        case 'task_title':
            return (strlen($data) > 4 && strlen($data) < 200);
            break;
        case 'task_text':
            return (strlen($data) > 10 && strlen($data) < 2000);
            break;
    }
    return preg_match($pattern, $data);
}

function exit_with_code($code, $headers = false, $register_query = true)
{
    global $time_start;
    if(is_array($headers)) {
        foreach ($headers as $key => $header) {
            header($key . ': ' . $header);
        }
    }
    http_response_code($code);
    if($register_query)
        register_script_time($_GET['r'].'/'.$_GET['a'], $_SERVER['REQUEST_METHOD'], $time_start);
    exit();
}

function log_text($text){
    $table = 'log';
    $server = 'master';
    $query = 'INSERT INTO '.$table.' (text) VALUES (\''.$text.'\')';
    query($table, $server, $query);
}

function get_user_os(){
    $a = strpos($_SERVER['HTTP_USER_AGENT'], '(') + 1;
    $b = strpos($_SERVER['HTTP_USER_AGENT'], ')');
    return substr($_SERVER['HTTP_USER_AGENT'], $a, $b - $a);
}

function check_connections(){
    global $mem;
    $time = time();
    $ip = $_SERVER['HTTP_X_REAL_IP'];

    $connections = $mem->get('ip_'.$ip);
    $connections[] = time();
    $mem->set('ip_'.$ip, $connections);
    $i = [];

    foreach (defines\Limit::LIMIT_CONNECTIONS as $duration => $limit){
        $i[$duration] = 0;
        foreach ($connections as $connection){
            if($connection + $duration > $time)
                $i[$duration] += 1;
        }
        if($i[$duration] > $limit){
            //не записываем запрос в список дабы не портить статистику по времени работы скрипта
            exit_with_code(defines\Codes::BAD_REQUEST, ['Error' => 'Limit '.$limit.' connections per '.$duration.' sec exceeded'], false);
        }
    }
    return true;
}