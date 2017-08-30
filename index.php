<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 19.08.17
 * Time: 0:06
 */
//регистрируем время начала скрипта
$time_start = microtime();

require_once $_SERVER['DOCUMENT_ROOT'].'/requires.php';

//запускаем обработчик ошибок
error_catcher();


$mem = new Memcached;
$mem_servers = [
    array('46.101.140.13', 11211),
    array('138.68.108.7', 11211)
];
$mem->AddServers($mem_servers);

//ещё будем собирать статистику по серверам и если что то писать в ошибки о недоступности memcached
//$mem->getStats();

//пытаемся защититься от DDOS на уровне php
check_connections();

$inputs = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_REQUEST : json_decode(file_get_contents('php://input'), true);

//Вместо куков читаем заголовки
//И если они не корректные, то больше не общаемся с клиентом
//А так же в дальнейшем избавит от кучи одинаковых проверок
$headers = apache_request_headers();
$token = $headers['Token'] ?? null;
$account_type = $headers['Account-type'] ?? null;
$id = null;
if(
    $token !== null &&
    $account_type !== null &&
    (
        !validate_input_data('token', $token) ||
        !validate_input_data('account_type', $account_type)
    )
){
    exit_with_code(defines\Codes::BAD_REQUEST, ['Error' => 'Bad headers']);
}else{
    $id = check_session($token, $account_type) ?? null;
}

switch($_GET['r'])
{
    case 'statistic':
        show_statistic();
        break;
    case 'errors':
        show_last_errors();
        break;
    case 'payment':
        switch($_GET['a']){
            case 'new':
                if(!$id){
                    exit_with_code(defines\Codes::UNAUTHORIZED);
                }
                if( !validate_input_data('summ', $inputs['summ']) || $inputs['summ'] < defines\Pay::PAYMENT_MINIMAL){
                    exit_with_code(defines\Codes::BAD_REQUEST, ['Error' =>'Bad summ; Minimal: '.defines\Pay::PAYMENT_MINIMAL]);
                }
                echo json_encode(generate_robo_payment($id, $inputs['summ']));
                exit_with_code(defines\Codes::OK);
                break;
            case 'result':
                $inputs = $_REQUEST;
                if(
                    !validate_input_data('int', $inputs['InvId']) ||
                    !validate_input_data('summ', $inputs['OutSum']) ||
                    !validate_input_data('md5', $inputs['SignatureValue']) ||
                    !check_robox_signature($inputs)
                ){
                    exit_with_code(defines\Codes::BAD_REQUEST);
                }
                approve_payment($inputs['InvId']);
                echo 'OK'.$inputs['InvId'];
                break;
            case 'approve':
                switch($_GET['res']){
                    //тут потом будут редиректы на сообщения об успешной/неуспешной оплате
                    case 'success':
                        echo '<meta http-equiv="refresh" content="0,/">';
                        exit_with_code(defines\Codes::OK);
                        break;
                    case 'fail':
                        $inputs = $_REQUEST;
                        if(
                            !validate_input_data('int', $inputs['InvId']) ||
                            !validate_input_data('summ', $inputs['OutSum'])
                        ){
                            exit_with_code(defines\Codes::BAD_REQUEST);
                        }
                        reject_payment($inputs['InvId']);
                        echo '<meta http-equiv="refresh" content="0,/">';
                        exit_with_code(defines\Codes::OK);
                        break;
                }
                break;
            case 'get_list':
                if(!$id){
                    exit_with_code(defines\Codes::UNAUTHORIZED);
                }
                $result['payments'] = load_payment_history($id);
                $result['count'] = count($result['payments']);
                echo json_encode($result);
                exit_with_code(defines\Codes::OK);
        }
        break;
    case 'payout':
        switch($_GET['a']){
            case 'new':
                if(!$id){
                    exit_with_code(defines\Codes::UNAUTHORIZED);
                }
                $summ = get_account_by_id($id)['salary'];
                if($summ < defines\Pay::PAYOUT_MINIMAL){
                    exit_with_code(defines\Codes::BAD_REQUEST, ['Error' =>'Minimal payout summ: '.defines\Pay::PAYOUT_MINIMAL]);
                }
                create_payout($id, $summ);
                exit_with_code(defines\Codes::OK);
                break;
            case 'get_list':
                if(!$id){
                    exit_with_code(defines\Codes::UNAUTHORIZED);
                }
                $result['payouts'] = load_payout_history($id);
                $result['count'] = count($result['payouts']);
                echo json_encode($result);
                exit_with_code(defines\Codes::OK);
                break;
            case 'remove':
                if(!$id){
                    exit_with_code(defines\Codes::UNAUTHORIZED);
                }
                if(!validate_input_data('int', $inputs['payout_id'])){
                    exit_with_code(defines\Codes::BAD_REQUEST, ['Error' =>'Bad payout_id']);
                }
                if(cansel_payout($inputs['payout_id'])){
                    exit_with_code(defines\Codes::OK);
                }
                exit_with_code(defines\Codes::BAD_REQUEST);
                break;
        }
        break;
    case 'auth':
        switch ($_GET['a'])
        {
            case 'validate_auth' :
                if(!$id){
                    exit_with_code(defines\Codes::UNAUTHORIZED);
                }
                //Для уменьшения числа запросов сразу отдадим данные пользователя
                if($account = get_account_by_id($id)) {
                    echo json_encode($account);
                    exit_with_code(defines\Codes::OK);
                }
                break;
            case 'sign_out' :
                if(
                    $id !== null &&
                    delete_session($token, $account_type)
                ){
                    exit_with_code(defines\Codes::OK);
                }
                exit_with_code(defines\Codes::BAD_REQUEST);
                break;
            case 'sign_in' :
                if(
                    !validate_input_data('account_type', $inputs['account_type']) ||
                    !validate_input_data('email', $inputs['email']) ||
                    !validate_input_data('password', $inputs['password'])
                ){
                    exit_with_code(defines\Codes::BAD_REQUEST, ['Error' => 'Bad inputs']);
                }
                $account = get_account_by_email($inputs['email']);
                if($inputs['password'] !== $account['password']){
                    exit_with_code(defines\Codes::UNAUTHORIZED, ['Error' => 'Wrong email or password']);
                }
                $result['token'] = get_session($account['id'], $inputs['account_type']) ?? set_new_token($account['id'], $inputs['account_type']);
                if(!$result['token']){
                    exit_with_code(defines\Codes::BAD_REQUEST, ['Error' => 'Creating token error']);
                }
                $result['account_type'] = $inputs['account_type'];
                echo json_encode($result);
                exit_with_code(defines\Codes::OK);
                break;
            case 'session_list' :
                if(!$id){
                    exit_with_code(defines\Codes::UNAUTHORIZED);
                }
                $result['session_list'] = get_session_list($id);
                $result['count'] = count($result['session_list']);
                echo json_encode($result);
                exit_with_code(defines\Codes::OK);
                break;
            case 'close_session':
                if(!$id){
                    exit_with_code(defines\Codes::UNAUTHORIZED);
                }
                if(!validate_input_data('int', $inputs['session_id'])){
                    exit_with_code(defines\Codes::BAD_REQUEST, ['Error' => 'Bad session id']);
                }
                if(!delete_session_by_id($id, $inputs['session_id'])){
                    exit_with_code(defines\Codes::BAD_REQUEST);
                }
                exit_with_code(defines\Codes::OK);
                break;
            case 'drop_pwd':
                remind_pwd($inputs['email']);
                break;
        }
        break;
    case 'user':
        switch($_GET['a'])
        {
            case 'get':
                if(!$id){
                    exit_with_code(defines\Codes::UNAUTHORIZED);
                }
                if($account = get_account_by_id($id)) {
                    echo json_encode($account);
                    exit_with_code(defines\Codes::OK);
                }
                exit_with_code(defines\Codes::BAD_REQUEST, ['Error' => 'Internal error; Unknown user id;']);
                break;
            case 'new':
                if(
                    !validate_input_data('fname', $inputs['fname']) ||
                    !validate_input_data('sname', $inputs['sname']) ||
                    !validate_input_data('email', $inputs['email']) ||
                    !validate_input_data('password', $inputs['password']) ||
                    !validate_input_data('phone', $inputs['phone'])
                ){
                    exit_with_code(defines\Codes::BAD_REQUEST, ['Error' => 'Bad inputs']);
                }
                if(account_exists($inputs['email'])){
                    exit_with_code(defines\Codes::BAD_REQUEST, ['Error' => 'Account already exists']);
                }
                if($result = create_account($inputs)){
                    exit_with_code(defines\Codes::OK);
                }
                exit_with_code(defines\Codes::BAD_REQUEST, ['Error' => 'Internal error']);
                break;
        }
        break;
    case 'task':
        switch($_GET['a']){
            case 'new':
                if(!$id){
                    exit_with_code(defines\Codes::UNAUTHORIZED);
                }
                $inputs['task_title'] = addslashes($inputs['task_title']);
                $inputs['task_text'] = addslashes($inputs['task_text']);
                if(
                    !validate_input_data('task_title', $inputs['task_title']) ||
                    !validate_input_data('task_text', $inputs['task_text']) ||
                    !validate_input_data('summ', $inputs['task_reward'])
                ){
                    exit_with_code(defines\Codes::BAD_REQUEST, ['Error' => 'Bad parameter list']);
                }
                $balance = get_account_by_id($id)['balance'];
                if($balance < $inputs['task_reward']){
                    exit_with_code(defines\Codes::BAD_REQUEST, ['Error' => 'Not enouth money']);
                }
                if(create_task($id, $inputs)){
                    up_balance($id, -$inputs['task_reward']);
                    exit_with_code(defines\Codes::OK);
                }
                exit_with_code(defines\Codes::BAD_REQUEST);
                break;
            case 'get_my':
                if(!$id){
                    exit_with_code(defines\Codes::UNAUTHORIZED);
                }
                $result['tasks'] = get_tasks_by_id_type($id, $account_type);
                $result['count'] = count($result['tasks']);
                echo json_encode($result);
                exit_with_code(defines\Codes::OK);
                break;
            case 'get_by_id':
                if(!validate_input_data('int', $inputs['task_id']))
                $result = get_task_by_id($inputs['task_id'], $id, $account_type);
        }
        break;
    case 'status':
        //тут сервер говорит, что доступен
        echo '42';
        exit_with_code(defines\Codes::OK);
        break;
    default:
        exit_with_code(defines\Codes::BAD_REQUEST, ['Error' => 'Did you want something?']);
        break;
}



//регистрируем время работы скрипта
register_script_time($_GET['r'].'/'.$_GET['a'], $_SERVER['REQUEST_METHOD'], $time_start);