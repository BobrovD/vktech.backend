# vktech
  Сервис создавался для участия в конкурсе [VK Tech](https://vk.com/wall-147415323_957).
  
  Вся инфраструктура расположена на серверах, предоставленных компанией [DigitalOcean](https://digitalocean.com) (DO).
  
  Для удобства был выкуплен домен [execordervk.tech](https://execordervk.tech).
  
  За время конкурса я успел разработать следующую архитектуру сервиса:
  * DNS-balancer
  * Front server (x2)
  * Backend server (x2)
  * Mysql server (x2)
  * Memcached server (x2)
  * Worker server (x2)
## **DNS-balancer**
  Взят у DO в аренду и равномерно распределяет запросы к [https://execordervk.tech](https://execordervk.tech) по доступным **front** серверам.
## **Front**
  2 сервера с установленными на них nginx, в конфиге которых прописан редирект всех .php запросов на upstream из двух **backend** серверов, а вся статика отдаётся с самого **front**
  На каждом сервере настроен https (все http запросы перенаправляются на https). Сертификаты получены на доменное имя execordervk.tech при помощи **cretbot**
  
  Открыты 22, 80, 443 порты.
  
  [Ссылка на содержмое Front серверов](https://github.com/BobrovD/vktech.front)
## **Backend**
  2 сервера с установленными apache2 + php7.0, php7.0-mysql, php7.0-json, php-memcached, libapache2-mod-php7.0.
  
  Открыты 22, 80, 443 порты.
  
  [Ссылка на содержмое Backend серверов](https://github.com/BobrovD/vktech.backend)
## **Mysql**
  2 сервера с настроенной репликацией Master + Slave.
  
  Открыты 22, 3306 порты.
## **Memcached**
  2 сервера с установленным memcached.
  
  Открыт 22 порт для всех и 11211 для 4 ip адресов (2x**backend**, 2x**worker**).
## **Worker**
  2 сервера для медленных или регулярных задач.

  [Ссылка на содержмое Worker серверов](https://github.com/BobrovD/vktech.worker)
# А теперь немного подробнее о каждом

#### **DNS-balancer**
  Сервер распределяет запросы по принципу Round Robin.
  Необходимо было выбрать оптимальное решение для балансировки среди нескольких front-серверов. Выбор пал в пользу готового решения в связи со сжатыми сроками. Предпочёл отказаться от двух A-записей в пользу DNS балансера дабы не возникало проблем при отключении одного из **front** серверов, у которых закеширована A-запись этого сервера.
  
#### **Front**
  Клиент написан с использованием HTML, CSS, Javascript (без сторонних библиотек или иных готовых решений).
  
  На сайте используются cookies, но лишь для сохранения информации авторизации после закрытия вкладки. С отключенными cookies с сайтом всё-равно удастся работать.
  
  HTML разбит на несколько фрагментов, которые подгружаются при переходах на другие страницы.
  
  JavaScript разбит на несколько файлов. Изначально подгружается основной файл, дополнительные скрипты подгружаются при открытии страниц, в случае если не были загружены ранее.
  
  CSS сделан одним файлом.
#### **Backend**
  Сервер написан на php, без использования сторонних библиотек. Сервер написан без использования ООП (исключение составляет $memcached).
  
  Время работы скрипта в ms фиксируется в базе данных. Статистику по работе можно посмотреть по [ссылке](https://execordervk.tech/index.php?r=statistic).
  
  Критические ошибки php так же фиксируются в базе данных (за исключением ошибок, вызванных невозможностью интерпритировать php-код). Они доступны по этой [ссылке](https://execordervk.tech/index.php?r=errors).
  
  На сервере не используются данные Cookies, а так же не включаются сессии. Вся авторизация происходит через заголовки запроса.
  
  Попытка защититься от DDos-атак была реализована при помощи записи времени каждого обращения к серверу в memcached с дальнейшей проверкой кол-ва обращений в 1 секунду и 60 секунд с лимитом 5 и 180 обращений соответственно. Если это значение превышается, то сервер уведомляет об этом клиента ответом 400: Bad request и причиной в заголовках ответа.
  
  Все входящие данные проверяются на валидность. В противном случае сервер оповещает ответом 400: Bad request.
  
  Подключение к mysql-серверам написано с возможностью использования Master + Slave репликации:
  ```php
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
  ```
  В текущую реализацию не затруднительно добавить выбор сервера, на котором находится необходимая часть таблицы, в случае если одна таблица располагается на нескольких серверах.
  
  Memcached используется для кеширования имён пользователей по их ID, дабы исключить большое кол-во запросов к базам данных при отображении списка задач, в котором видно имя и заказчика и исполнителя.
  
###### Авторизация
  По https передаётся логин + пароль + тип аккаунта. В ответ в случае, если данные введены верно, выдаётся токен. Далее полученный токен отправляется в каждом запросе на сервер для идентификации.
  
  Список сессий можно посмотреть, кликнув по своему имени. При желании, можно закрыть сессию и на устройстве, где она сохранена, придётся проходить авторизацию заново.
  
  Под созданым аккаунтом можно авторизироваться как в роли исполнителя, так и в роли заказчика.
###### Пополнение баланса
  Подключена система Robokassa. На сервере хранятся пароли, сейчас активна тестовая версия, так что можно пополнять баланс не тратя средств.
  
  Пополнять баланс может только заказчик. Баланс заказчика не имеет ничего общего с заработком исполнителя.
###### Вывод заработка
  Вывод средств не автоматический. В базе создаётся запись на вывод, администратор проверяет, какие выполнялись этим работником задачи, не было ли какой технической ошибки, и переводит вручную средства.
  
  Заработок можно получить лишь выполняя задачи.
###### Финансы
  Все финансовые записи в базе хранятся в формате DECIMAL(8,2), что позволяет избежать погрешностей, которые могли быть при использовании FLOAT.
###### Задачи
  К сожалению, большая часть функционала задач не реализована по причине нехватки времени.
  
  Реализовано лишь
   * Добавление задачи
   * Просмотр списка задач заказчика
   
  В планах было
   * Отображение списка задач на главной странице
   * Отображение списка задач на главной странице исполнителя
   * Отображение списка задач исполнителя
   * Отображение списка задач всех заказчиков для заказчика
   * Создание категорий задач
   * Возможность исполнителям подписаться на новые задачи
   * Возможность заказчику выбирать исполнителя
 #### **Mysql**
```mysql
mysql> show tables;
+------------------+
| Tables_in_vktech |
+------------------+
| account          |
| error            |
| payment          |
| payout           |
| script_time      |
| session          |
| task             |
| work             |
+------------------+

mysql> show columns from account;
+---------------+--------------+------+-----+---------+----------------+
| Field         | Type         | Null | Key | Default | Extra          |
+---------------+--------------+------+-----+---------+----------------+
| id            | int(11)      | NO   | PRI | NULL    | auto_increment |
| email         | varchar(30)  | NO   | UNI | NULL    |                |
| password      | varchar(20)  | NO   |     | NULL    |                |
| fname         | varchar(30)  | NO   |     | NULL    |                |
| sname         | varchar(30)  | NO   |     | NULL    |                |
| balance       | decimal(8,2) | NO   |     | 0.00    |                |
| salary        | decimal(8,2) | NO   |     | 0.00    |                |
| phone         | varchar(20)  | NO   |     | NULL    |                |
| time_creation | int(11)      | NO   |     | NULL    |                |
+---------------+--------------+------+-----+---------+----------------+

mysql> show columns from session;
+--------------+-----------------------------+------+-----+---------+----------------+
| Field        | Type                        | Null | Key | Default | Extra          |
+--------------+-----------------------------+------+-----+---------+----------------+
| row_id       | int(11)                     | NO   | PRI | NULL    | auto_increment |
| token        | varchar(32)                 | NO   | UNI | NULL    |                |
| id           | int(11)                     | NO   |     | NULL    |                |
| ip           | int(10) unsigned            | NO   |     | NULL    |                |
| user_agent   | varchar(60)                 | NO   |     | NULL    |                |
| account_type | enum('executor','customer') | NO   |     | NULL    |                |
| last_active  | int(11)                     | NO   |     | NULL    |                |
+--------------+-----------------------------+------+-----+---------+----------------+

mysql> show columns from payment;
+------------+--------------------------------------------------+------+-----+---------+----------------+
| Field      | Type                                             | Null | Key | Default | Extra          |
+------------+--------------------------------------------------+------+-----+---------+----------------+
| payment_id | int(11)                                          | NO   | PRI | NULL    | auto_increment |
| user_id    | int(11)                                          | NO   |     | NULL    |                |
| user_ip    | int(11)                                          | NO   |     | NULL    |                |
| summ       | decimal(8,2)                                     | NO   |     | 0.00    |                |
| time       | int(11)                                          | NO   |     | NULL    |                |
| status     | enum('created','received','rejected','outdated') | NO   |     | created |                |
+------------+--------------------------------------------------+------+-----+---------+----------------+

mysql> show columns from payout;
+-----------+---------------------------------------+------+-----+---------+----------------+
| Field     | Type                                  | Null | Key | Default | Extra          |
+-----------+---------------------------------------+------+-----+---------+----------------+
| payout_id | int(11)                               | NO   | PRI | NULL    | auto_increment |
| user_id   | int(11)                               | NO   |     | NULL    |                |
| user_ip   | int(11)                               | NO   |     | NULL    |                |
| summ      | decimal(8,2)                          | NO   |     | NULL    |                |
| status    | enum('created','received','rejected') | NO   |     | NULL    |                |
| method    | enum('qiwi')                          | NO   |     | NULL    |                |
| comment   | varchar(250)                          | NO   |     |         |                |
| time      | int(11)                               | NO   |     | NULL    |                |
+-----------+---------------------------------------+------+-----+---------+----------------+

mysql> show columns from task;
+---------------+---------------------------------------------------+------+-----+---------+----------------+
| Field         | Type                                              | Null | Key | Default | Extra          |
+---------------+---------------------------------------------------+------+-----+---------+----------------+
| task_id       | int(11)                                           | NO   | PRI | NULL    | auto_increment |
| customer_id   | int(11)                                           | NO   |     | NULL    |                |
| time_creation | int(11)                                           | NO   |     | NULL    |                |
| executor_id   | int(11)                                           | NO   |     | 0       |                |
| status        | enum('actual','in_work','done','failed','frozen') | NO   |     | actual  |                |
| title         | varchar(100)                                      | NO   |     | NULL    |                |
| description   | varchar(1000)                                     | NO   |     | NULL    |                |
| reward        | decimal(8,2)                                      | NO   |     | NULL    |                |
+---------------+---------------------------------------------------+------+-----+---------+----------------+

mysql> show columns from work;
+----------+------------------------------------+------+-----+---------+----------------+
| Field    | Type                               | Null | Key | Default | Extra          |
+----------+------------------------------------+------+-----+---------+----------------+
| work_id  | int(10) unsigned zerofill          | NO   | PRI | NULL    | auto_increment |
| priority | tinyint(1)                         | NO   |     | 0       |                |
| status   | enum('new','in_progress','failed') | NO   |     | NULL    |                |
| cat      | enum('payment','notification')     | NO   |     | NULL    |                |
| die      | int(11)                            | NO   |     | NULL    |                |
| wait_for | int(11)                            | NO   |     | NULL    |                |
| data     | varchar(200)                       | NO   |     | NULL    |                |
+----------+------------------------------------+------+-----+---------+----------------+

mysql> show columns from script_time;
+--------+------------------------------------------+------+-----+-------------------+-----------------------------+
| Field  | Type                                     | Null | Key | Default           | Extra                       |
+--------+------------------------------------------+------+-----+-------------------+-----------------------------+
| id     | int(11)                                  | NO   | PRI | NULL              | auto_increment              |
| path   | varchar(20)                              | NO   |     | NULL              |                             |
| method | enum('GET','POST','PUT','DELETE','HEAD') | NO   |     | NULL              |                             |
| at     | timestamp                                | NO   |     | CURRENT_TIMESTAMP |                             |
| time   | decimal(5,3) unsigned                    | NO   |     | NULL              |                             |
+--------+------------------------------------------+------+-----+-------------------+-----------------------------+

mysql> show columns from error;
+-----------+--------------+------+-----+---------+----------------+
| Field     | Type         | Null | Key | Default | Extra          |
+-----------+--------------+------+-----+---------+----------------+
| id        | int(11)      | NO   | PRI | NULL    | auto_increment |
| timestamp | int(11)      | NO   |     | NULL    |                |
| message   | varchar(100) | NO   |     | NULL    |                |
+-----------+--------------+------+-----+---------+----------------+
```

#### **Memcached**
  В конфиге выдана память по 300мб на каждом сервере (серверы по 512мб).
  
#### **Worker**
  Серверы, которые занимаются рассылкой уведомлений (информацию о том, какое событие произошло, серверы узнают из таблицы **work**, после чего ставят ей статус in_progress во время выполнения задачи.
  
  На данный момент серверы рассылают только уведомления с новым паролем.
  
  Планировались рассылки по почте и в телеграме. Исполнителей планировалось оповещать о
   * новой задаче в категории, на которую он подписан
   * избрании пользователя исполнителем
   * подтверждении или отклонении выполненной работы
   * подтверждении или отклонении вывода зарплаты
   Заказчиков о
   * новом исполнителе задачи
   * выполнении задачи
   * пополнении баланса
   * списании баланса на задачу
   
   **Worker** сервер должен был заниматься чисткой баз данных и обновлением статусов платежей (просрочен).
   
   Ещё на его плечах должна была лежать ответственность за поддержание актуального кэша списка задач с главной страницы и со страниц исполнителей.
