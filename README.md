# sidalex/swoole-app


[![Latest Stable Version](http://poser.pugx.org/sidalex/swoole-app/v)](https://packagist.org/packages/sidalex/swoole-app) [![Total Downloads](http://poser.pugx.org/sidalex/swoole-app/downloads)](https://packagist.org/packages/sidalex/swoole-app) [![Latest Unstable Version](http://poser.pugx.org/sidalex/swoole-app/v/unstable)](https://packagist.org/packages/sidalex/swoole-app) [![License](http://poser.pugx.org/sidalex/swoole-app/license)](https://packagist.org/packages/sidalex/swoole-app) [![PHP Version Require](http://poser.pugx.org/sidalex/swoole-app/require/php)](https://packagist.org/packages/sidalex/swoole-app)

[en](#sidalexswoole-app-framework-for-working-with-swoole) | [ru](#sidalexswoole-app-фреймворк-для-работы-со-swoole)
# sidalex/swoole-app Framework for Working with Swoole

1. [Install](#install)
1. [Config](#config)
1. [List of config parameters](#list-of-config-parameters)
1. [Task](#task)
   1. [Methods:](#methods-)
1. [BasicTaskData](#basictaskdata)
   1. [Parameters](#parameters)
1. [TaskResulted](#taskresulted)
   1. [Properties:](#properties:)
1. [Cyclic Job](#cyclic-job)
1. [Controller](#controller)
   1. [uri attribute parameter](#uri-attribute-parameter)
   1. [method attribute parameter](#method-attribute-parameter)
   1. [Request processing](#request-processing)
   1. [Response](#response)
   1. [Request](#request)
1. [notFoundController](#notfoundcontroller)
1. [State Containers](#state-containers)
   1. [Creation](#creating-a-state-container)
   1. [Usage](#accessing-state-containers)
   1. [Best Practices](#best-practices-state-containers)

## Install

To install, execute the following commands:

```
composer require sidalex/swoole-app
```

To run the Swoole application, you need to create a script named server.php with the following content:


```php
<?php
declare(strict_types=1);
require_once "./vendor/autoload.php";
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Constant;
$config = json_decode(file_get_contents('./config.json'));
$http = new Server("0.0.0.0", 9501);
$http->set(
    [
        Constant::OPTION_WORKER_NUM => 2,
        Constant::OPTION_TASK_WORKER_NUM => (swoole_cpu_num()) * 10,
    ]
);

$app = new \Sidalex\SwooleApp\Application($config);
$http->on(
    "start",
    function (Server $http) use ($app) {
        echo "Swoole HTTP server is started.\n";
        $app->initCyclicJobs($http);
    }
);
$http->on(
    "request",
    function (Request $request, Response $response) use ($app,$http) {
        $app->execute($request, $response,$http);
    }
);
$http->on(
    'task',
    function (Server $server, $taskId, $reactorId, $data) use ($app) {
        return $app->taskExecute($server, $taskId, $reactorId, $data);
    }
);
$http->start();
```
The $config variable should be \stdClass and can contain parameters described here.

To run background processes that should execute periodically (not triggered by user action but by scheduler), the CyclicJobsInterface is implemented. For a more detailed description of its usage, see here. To automatically start background cyclic processes, they need to be specified in the config here.

To create application endpoints, you need to create controllers classes. For each endpoint, you need to create your own class. Alternatively, create your own routing rule via notFoundController.

All operations inside CyclicJobs and controllers must be non-blocking. Otherwise, instead of increasing performance, you may lose it significantly, and the next request will not be processed until the blocking operation is completed.

All blocking operations must be wrapped in TaskExecutorInterface and executed as separate Task.

## Config
To run the application, you need to create a stdClass with a set of properties (described in more detail below).

The intended use is to initiate configuration data from a json file, see example server.php




```php
$config = json_decode(file_get_contents('./config.json'));
```

## List of config parameters
```php
$config = new stdClass();
$config->notFoundController = 'appNameSpaceMyApp\MyNotFoundController';
$config->controllers = [
'appNameSpaseMyApp\MyFirstControllerNamespace',
'appNameSpaseMyApp\MySecondControllerNamespace',
'appNameSpaseMyApp\MyThreeControllerNamespace',
];
$config->CyclicJobs =[
'appNameSpaseMyApp\MyFirstCyclicJobsClass',
'appNameSpaseMyApp\MySecondCyclicJobsClass',
'appNameSpaseMyApp\MyThreeCyclicJobsClass',
];
```


### .env File Format or environment variables in docker

Variables must start with `SWOOLE_APP_` prefix. Example:

```ini
SWOOLE_APP_DEBUG=true
SWOOLE_APP_DB__HOST=localhost
SWOOLE_APP_DB__PORT=3306
```

Gets converted to:

```php
object(stdClass) {
  ["DEBUG"] => true
  ["DB"] => object(stdClass) {
    ["HOST"] => "localhost"
    ["PORT"] => 3306
  }
}
```

### Configuration Validation

1. Create validator class:
```php
use Sidalex\SwooleApp\Classes\Validators\ConfigValidatorInterface;

class DatabaseConfigValidator implements ConfigValidatorInterface
{
    public function validate(\stdClass $config): void
    {
        if (!isset($config->DB->HOST)) {
            throw new \RuntimeException("Database host required");
        }
    }
}
```

2. Validate config:
```php
if (!$builder->validate([DatabaseConfigValidator::class])) {
    $errors = $builder->getErrors();
    // Handle errors
}
```

notFoundController - a string with the class that handles routes not found by the default flow. This class must implement Sidalex\SwooleApp\Classes\Controllers\ControllerInterface.

controllers - an array of namespaces in which the search for Controller classes (implementing the Sidalex\SwooleApp\Classes\Controllers\ControllerInterface interface) will be carried out recursively. Additionally, for controller class implementation, inheritance from AbstractController can be used. For more details, see here.

CyclicJobs - an array of classes implementing the CyclicJobsInterface interface which are launched when the application starts and executed cyclically at a certain interval of time. For more details, see here.


## Task

Tasks represent processes that run outside of the asynchronous execution process and can be invoked from any part of the application.

To simplify working with tasks and standardize their execution within the framework, a mechanism has been added to initiate these processes. To use this mechanism when starting the Swoole server (`server.php`), you need to add the following code block:

```php
$http->on(
    'task',
    function (Server $server, $taskId, $reactorId, $data) use ($app) {
        return $app->taskExecute($server, $taskId, $reactorId, $data);
    }
);
```
If this code block is not initiated, the framework won't be able to work with the BasicTaskData class and the TaskDataInterface interface.

These processes may contain blocking operations.

To run a task, you need to create an object of the BasicTaskData class or use your own class implementing the TaskDataInterface interface. Learn more here.

### Methods:
#### task
Task Runs a task without waiting for its completion.
```php
Swoole\Server->task(Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface $data, int $dstWorkerId = -1, callable $finishCallback = null);
```
$data: An object implementing the Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface. By default, the BasicTaskData class is recommended. Learn more here.

$dstWorkerId: Worker process ID. If not provided, Swoole server will choose a random and unoccupied worker process for you.

$finishCallback: A callback to be executed before the task finishes. This parameter is optional.

#### taskwait
Taskwait Runs a task with waiting for its completion and getting the result. Result waiting is done in a non-blocking manner. Tasks can be launched from the controller.
```php
$result = Swoole\Server->taskwait((Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface $data, float $timeout = 0.5, int $dstWorkerId = -1) :TaskResulted
```

$data: An object implementing the Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface. By default, the BasicTaskData class is recommended. Learn more here.

$timeout: Timeout period for task completion in seconds. If the timeout is reached, false will be returned. Minimum value is 1 ms.

$dstWorkerId: Worker process ID. If not provided, Swoole server will choose a random and unoccupied worker process for you.

$result: The result of the task execution, should be an instance of the TaskResulted class. Learn more here.

## BasicTaskData
The BasicTaskData class is a part of Sidalex\SwooleApp framework and can be found in the Classes\Tasks\Data directory. It is used for creating task data to be executed by Swoole Task Worker.

Usage
Create an instance of BasicTaskData and pass two parameters to the constructor:

The name of the class that will be created in the task for execution. This class must implement the TaskExecutorInterface interface.
An array of context data required for executing the logic contained in the task class.
```php
$taskData = new BasicTaskData('Sidalex\TestSwoole\Tasks\TestTaskExecutor', ['test' => 'test1']);
```

$taskData object can then be passed to the taskwait() method of the Swoole Server to execute the task synchronously and get the result.
```php
$taskResult = $this->server->taskwait($taskData);
var_export($taskResult->getResult());
```

### Parameters
Class Name - The first parameter is a string representing the name of the class that will be created in the task for execution. This class must implement the TaskExecutorInterface interface.

Context Data - The second parameter is an associative array of data required for executing the logic contained in the task class.

Example
```php
$taskData = new BasicTaskData('Sidalex\TestSwoole\Tasks\TestTaskExecutor', ['test' => 'test1']);
        /**
         * @var $taskResult TaskResulted
         */
        $taskResult =  $this->server->taskwait($taskData);
        var_export($taskResult->getResult());

```

## TaskResulted
Description: The TaskResulted class represents the result of task execution. It contains information about whether the task was successfully executed and its result.

### Properties:

$success - a private property containing information about the success of the task execution.

$result - a private property containing the result of the task execution.

__construct(mixed $inData, bool $success = true) - the class constructor, accepting input data and information about the success of the task execution.

Methods:

getResult(): mixed - a method returning the result of the task execution. It may throw a TaskException exception.

isSuccess(): bool - a method returning information about the success of the task execution.

Example:
```php
$taskData = new BasicTaskData('Sidalex\TestSwoole\Tasks\TestTaskExecutor', ['test' => 'test1']);
/**
* @var $taskResult TaskResulted
*/
$taskResult =  $this->server->taskwait($taskData);
var_export($taskResult->getResult());
```

## Cyclic Job
This is the code that requires periodic execution. This mechanism allows you to run scripts at regular intervals. It replaces cron.

To initialize Cyclic Job, you need to declare the CyclicJobs parameter in the configuration file.

Example initialization of config using stdClass:
```php
$config = new stdClass();
$config->CyclicJobs =[
"Sidalex\TestSwoole\CyclicJobs\TestCyclicJobs",
];
```
Example initialization of config using a json file:
```php
{
"CyclicJobs": [
"Sidalex\\TestSwoole\\CyclicJobs\\TestCyclicJobs"
]
}
```

Example class for Cyclic Job:
```php
class MyCyclicJob implements CyclicJobsInterface
{
private $application;
private $server;

    public function __construct(Application $application, Server $server)
    {
        $this->application = $application;
        $this->server = $server;
    }

    public function getTimeSleepSecond(): float
    {
        // Returns the sleep time in seconds
        return 5.0;
    }

    public function runJob(): void
    {
        $arr = [1,2,3,4,5,6,7,8,9];
        foreach ($arr as $value){
            if($value % 3 == 0)
            {
                echo "example";
            }
        }
    }
}
```
Any class specified in the config must implement the CyclicJobsInterface interface.

getTimeSleepSecond - returns the time in seconds at which the runJob method will be executed periodically.

runJob - a method that contains the useful payload in terms of business logic. This is the code that contains the main business logic that should be executed cyclically.

## Controller
To create new routes, the Controller class is used. To add a new route, you need to create a class that implements the ControllerInterface interface and add the namespace in which this class is contained to the configuration file with the key "controllers". More details here

Also, the framework has a special abstract class AbstractController that can simplify the creation of a Controller class.

To create a Route for the Controller class, you need to specify an attribute of the following form:
```php
#[\Sidalex\SwooleApp\Classes\Controllers\Route(uri: '/api/{v1}/get_resume',method:"POST")]
class TestController extends AbstractController
{
```
or
```php
use Sidalex\SwooleApp\Classes\Controllers\Route;

#[Route(uri: '/api/{v1}/get_resume',method:"POST")]
class TestController extends AbstractController
{
```
It is critically important that the attribute be specified first for this class.

If no suitable controller is found, the NotFoundController will be called.

### uri attribute parameter
uri - this parameter determines for which route this controller will be used.

If you specify a * in the route, for example, /test/version/*/items, then this controller will work for uris corresponding to /test/version/(any string)/items.

If you specify /test/version/{version_number}/items in the route, the behavior will be similar to the behavior with *, but $uri_params['version_number'] will be added to the controller constructor.

### method attribute parameter
method - shows which method will be relevant for calling this controller.

### Request processing
The Controller class execute method contains the main business logic that the application should perform for this request. This method must return a response (\Swoole\Http\Response), which is contained in

$this->response

### Response
In the Controller class:

$this->response

For a more detailed description of the methods of this class, see the official documentation Swoole

Example of use:
```php
$this->response->setHeader('Content-Type', 'application/json');
$this->response->end(
                json_encode(
                 [
                     'status' => 'error',
                     'message' => 'collection '.$this->uri_params['collection_name'] . 'not found in collectionList',
                 ]
                )
            );
```
### Request
In the Controller class:

$this->request;

For a more detailed description of the methods of this class, see the official documentation Swoole

Example of use:

$obj = json_decode($this->request->getContent());
## notFoundController

It is designed to process routes that have not been found by the framework router, to issue a 404 response, or to organize the unique routing logic of the application.

To initialize, you need to pass the class name in the config. more details about this [here](#config)

The notFoundController must implement the ControllerInterface

example:
```php
class NotFoundController implements ControllerInterface
{

    private \Swoole\Http\Request $request;
    private \Swoole\Http\Response $responce;
    /**
     * @var array|string[]
     */
    private array $uri_params;

    public function __construct(\Swoole\Http\Request $request, \Swoole\Http\Response $response, array $uri_params=[])
    {
        $this->request = $request;
        $this->responce = $response;
        $this->uri_params = $uri_params;
    }

    public function execute(): \Swoole\Http\Response
    {
        $this->responce->setStatusCode(404);
        $this->responce->setHeader('Content-Type', 'application/json');
        $this->responce->end(json_encode(
            [
                'codeStatus' => '404',
                'text' => 'Page not found'
            ]
        ));
        return $this->responce;
    }

    public function setApplication(Application $application, Server $server)
    {

    }
}
```

## State Containers

State containers provide a way to manage and share application state across different components. They are initialized during application startup and can be accessed throughout the application lifecycle.

### Creating a State Container
To create a new state container:
Create a class that extends AbstractContainerInitiator or implements StateContainerInitiationInterface

Implement the required methods:

init() - Initializes your state

getKey() - Returns the key under which your state will be stored

getResultInitiation() - Returns the initialized state

```php
<?php
namespace YourApp\StateContainers;

use Sidalex\SwooleApp\Classes\Initiation\AbstractContainerInitiator;
use Sidalex\SwooleApp\Application;

class DatabaseConnectionContainer extends AbstractContainerInitiator
{
    protected string $key = 'database';

    public function init(Application $app): void
    {
        // Initialize your database connection here
        $config = $app->getConfig()->getContainer('database_config');
        
        $this->result = new \PDO(
            $config['dsn'],
            $config['username'],
            $config['password']
        );
    }
}
```
JSON config:
```json
{
    "StateContainerInitiation": [
        "YourApp\\StateContainers\\DatabaseConnectionContainer",
        "YourApp\\StateContainers\\CacheConnectionContainer"
    ]
}
```

### Accessing State Containers
State containers can be accessed anywhere you have access to the Application instance:

```php
// In a controller:
$db = $this->application->getStateContainer()->getContainer('database');

// In a task executor:
$db = $this->app->getStateContainer()->getContainer('database');

// In a cyclic job:
$db = $this->application->getStateContainer()->getContainer('database');
```
### Best Practices State Containers
Initialization: State containers are initialized once during application startup. Make sure all required resources are available at this time.

Thread Safety: Since Swoole runs in a multi-process environment, ensure your state containers are either:

Stateless

Process-isolated

Properly synchronized for shared resources

Resource Management: If your container manages resources like database connections, implement proper cleanup in destructor if needed.

Key Naming: Use descriptive and unique keys for your containers to avoid collisions.




# sidalex/swoole-app фреймворк для работы со swoole

1. [Установка](#установка)
1. [Config](#конфиг)
   1. [Список параметров конфига](#список-параметров-конфига)
1. [Task](#task-задача)
   1. [Методы:](#методы-task-)
1. [BasicTaskData](#класс-basictaskdata)
1. [Класс TaskResulted](#класс-taskresulted)
   1. [Свойства:](#свойства-taskresulted-)
   1. [Методы:](#методы-taskresulted-)
1. [Cyclic Job](#класс-cyclic-job)
1. [Controller](#класс-controller)
   1. [uri атрибутивный параметр](#uri-атрибутивный-параметр)
   1. [параметр атрибута](#параметр-атрибута)
   1. [Обработка запроса](#обработка-запроса)
   1. [Response](#response-1)
   1. [Запрос](#запрос)
1. [notFoundController](#notfoundcontroller-1)
1. [State Containers](#контейнеры-состояний)
   - [Создание](#создание-контейнера-состояний)
   - [Использование](#доступ-к-контейнерам-состояний)
   - [Примеры](#лучшие-практики-использования-контейнеров-состояний)

## Установка

Для утсановки выполните следующие команды:

```
composer require sidalex/swoole-app
```
Для запуска приложения свули необходимо создать скрипт server.php следующего содержания:

```php
<?php
declare(strict_types=1);
require_once "./vendor/autoload.php";
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Constant;
$config = json_decode(file_get_contents('./config.json'));
$http = new Server("0.0.0.0", 9501);
$http->set(
    [
        Constant::OPTION_WORKER_NUM => 2,
        Constant::OPTION_TASK_WORKER_NUM => (swoole_cpu_num()) * 10,
    ]
);

$app = new \Sidalex\SwooleApp\Application($config);
$http->on(
    "start",
    function (Server $http) use ($app) {
        echo "Swoole HTTP server is started.\n";
        $app->initCyclicJobs($http);
    }
);
$http->on(
    "request",
    function (Request $request, Response $response) use ($app,$http) {
        $app->execute($request, $response,$http);
    }
);
$http->on(
    'task',
    function (Server $server, $taskId, $reactorId, $data) use ($app) {
        return $app->taskExecute($server, $taskId, $reactorId, $data);
    }
);
$http->start();
```
Переменная $config должна быть \stdClass и может содержать параметры писанные [тут](#config)

Для запуска фоновых процессов, которые должны исполняться периодичски (не от действия пользователя а по планировщику)
реализован интерфейс CyclicJobsInterface более подробное описание его использования [тут](#cyclic-job). Для Автоматического запуска фоновых циклических процессов их необходимо указать в конфиге [тут]().

Для создания Эндпойнов приложения необходимо создать классы [контроллеры](#controller), для каждого эндпойнта необходимо создать свой класс. Либо создать свое правило маршрутизации через [notFoundController](#notfoundcontroller).

Все операции внутри CyclicJobs и controllers должны быть не блокирующими в противном случае вместо прироста производительности вы можете сильно потерять в ней и следующий запрос не будет обработан, пока блокирующая операция не будет выполнена.

Все блокирующие операции необходимо оборачивать в TaskExecutorInterface и выполнять отдельными [Task](#task).
## Конфиг

Для запуска приложения необходимо создать stdClass с набором свойств(далее описаны более поробно)

Целевым использованием считается инициация конфигурационных данных из файла json смоттри пример server.php
```php
$config = json_decode(file_get_contents('./config.json'));
```

### Список параметров конфига

```php
$config = new stdClass();
$config->notFoundController = 'appNameSpaceMyApp\MyNotFoundController';
$config->controllers = [
    'appNameSpaseMyApp\MyFirstControllerNamespace',
    'appNameSpaseMyApp\MySecondControllerNamespace',
    'appNameSpaseMyApp\MyThreeControllerNamespace',
];
$config->CyclicJobs =[
    'appNameSpaseMyApp\MyFirstCyclicJobsClass',
    'appNameSpaseMyApp\MySecondCyclicJobsClass',
    'appNameSpaseMyApp\MyThreeCyclicJobsClass',
];
```
### Формат .env файла или переменных окружения в docker

Переменные должны начинаться с префикса `SWOOLE_APP_`. Пример:

```ini
SWOOLE_APP_DEBUG=true
SWOOLE_APP_DB__HOST=localhost
SWOOLE_APP_DB__PORT=3306
```

Преобразуется в структуру:

```php
object(stdClass) {
  ["DEBUG"] => true
  ["DB"] => object(stdClass) {
    ["HOST"] => "localhost"
    ["PORT"] => 3306
  }
}
```

### Валидация конфигурации

1. Создайте класс-валидатор:
```php
use Sidalex\SwooleApp\Classes\Validators\ConfigValidatorInterface;

class DatabaseConfigValidator implements ConfigValidatorInterface
{
    public function validate(\stdClass $config): void
    {
        if (!isset($config->DB->HOST)) {
            throw new \RuntimeException("Требуется хост базы данных");
        }
    }
}
```

2. Проверка конфигурации:
```php
if (!$builder->validate([DatabaseConfigValidator::class])) {
    $errors = $builder->getErrors();
    // Обработка ошибок
}
```


notFoundController - строка класс с классом , который обрабатывает роуты не найденные по стандартному флоу, данный клас должен имплементировать Sidalex\SwooleApp\Classes\Controllers\ControllerInterface 

controllers - массив namespace в которых рекурсивно удет осуществляться поиск классов Контроллеров(имплементирующих интерфейс Sidalex\SwooleApp\Classes\Controllers\ControllerInterface) так же для реализации класса контроллера можно испрользовать наследование AbstractController более подробно [тут](#controller).

CyclicJobs - мкассив слассов , которые имплементируют интерфейс CyclicJobsInterface которы запускаются при стапрте приложения и выполняются циклично раз в определенный интервал времени подробнее [тут](#cyclic-job)

## Task Задача
Tasks (задачи) представляют собой процессы, которые выполняются вне асинхронного процесса выполнения и могут быть вызваны в любой части приложения.

Для упрощения работы с задачами и стандартизации их выполнения в рамках фреймворка был добавлен механизм, позволяющий инициировать эти процессы. Для использования этого механизма при запуске сервера Swoole(server.php) необходимо добавить следующий блок кода:
```php
$http->on(
    'task',
    function (Server $server, $taskId, $reactorId, $data) use ($app) {
        return $app->taskExecute($server, $taskId, $reactorId, $data);
    }
);
```
Если данный блок кода не будет инициирован, то фреймворк не сможет работать с классом BasicTaskData и интерфейсом TaskDataInterface.

В этих процессах могут содержаться блокирующие операции.

Для запуска задачи необходимо создать объект класса BasicTaskData или использовать собственный класс, реализующий интерфейс TaskDataInterface. Подробнее [здесь](#basictaskdata).

### Методы Task:

task: запускает задачу без ожидания её завершения.
```php
Swoole\Server->task(Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface $data, int $dstWorkerId = -1, callable $finishCallback = null)
```

$data: объект, реализующий интерфейс Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface. По умолчанию предлагается использовать класс BasicTaskData. Подробнее здесь.

$dstWorkerId: идентификационный номер рабочего процесса. Если параметр не передан, сервер Swoole выберет для вас случайный и незанятый рабочий процесс.

$finishCallback: колбэк, который будет выполнен перед завершением задачи. Параметр необязателен.

taskwait - запуск ззадачи с ожитанием завершения и получения результата. ожидание результата происходит неблокирующим образом. задачи можно запускать из контоллера.
```php
$result = Swoole\Server->taskwait((Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface $data, float $timeout = 0.5, int $dstWorkerId = -1) :TaskResulted
```

$data: объект, реализующий интерфейс Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface. По умолчанию предлагается использовать класс BasicTaskData. Подробнее здесь.

$timeout: время ожидания завершения задачи в секундах. Если истекает время ожидания, будет возвращено значение false. Минимальное значение - 1 мс.

$dstWorkerId: идентификационный номер рабочего процесса. Если параметр не передан, сервер Swoole выберет для вас случайный и незанятый рабочий процесс.

$result: результат выполнения задачи, должен быть экземпляром класса TaskResulted. Подробнее здесь.

## Класс BasicTaskData

```php
#Sidalex\SwooleApp\Classes\Tasks\Data\BasicTaskData
$taskData = new BasicTaskData('Sidalex\TestSwoole\Tasks\TestTaskExecutor', ['test' => 'test1']);
```
В котором в конструкторе передается 2 параметра

1 параметр ('Sidalex\TestSwoole\Tasks\TestTaskExecutor') - это название класса, который будет создан в задаче для исполнения. Он должен имплементировать интерфейс TaskExecutorInterface

2 параметр ( ['test' => 'test1'] ) - это массив с данными контекста, который необходим для исполнения логики содержащейся в классе задачи ('Sidalex\TestSwoole\Tasks\TestTaskExecutor' для примера)

Пример применения например в контроллере или в Cyclic Job:

```php
$taskData = new BasicTaskData('Sidalex\TestSwoole\Tasks\TestTaskExecutor', ['test' => 'test1']);
        /**
         * @var $taskResult TaskResulted
         */
        $taskResult =  $this->server->taskwait($taskData);
        var_export($taskResult->getResult());
```

## Класс TaskResulted
Описание: Класс TaskResulted представляет собой результат выполнения задачи. Он содержит информацию о успешном выполнении задачи и её результате.

### Свойства TaskResulted:

$success - приватное свойство, содержащее информацию о успешном выполнении задачи.

$result - приватное свойство, содержащее результат выполнения задачи.

__construct(mixed $inData, bool $success = true) - конструктор класса, принимающий входные данные и информацию о успешном выполнении задачи.

### Методы TaskResulted:

getResult(): mixed - метод, возвращающий результат выполнения задачи. Может выбрасывать исключение типа TaskException.

isSuccess(): bool - метод, возвращающий информацию о успешном выполнении задачи.

Пример:
```php
$taskData = new BasicTaskData('Sidalex\TestSwoole\Tasks\TestTaskExecutor', ['test' => 'test1']);
/**
* @var $taskResult TaskResulted
*/
$taskResult =  $this->server->taskwait($taskData);
var_export($taskResult->getResult());
```
## Класс Cyclic Job
Этот код требует периодического выполнения. Этот механизм позволяет запускать скрипты с регулярными интервалами. Он заменяет cron.

Для инициализации Cyclic Job необходимо объявить параметр CyclicJobs в файле конфигурации.

Пример инициализации конфига с использованием stdClass:
```php
$config = new stdClass();
$config->CyclicJobs = [
    "Sidalex\TestSwoole\CyclicJobs\TestCyclicJobs",
];
```
Пример инициализации конфига с использованием файла json:
```php
{
  "CyclicJobs": [
    "Sidalex\\TestSwoole\\CyclicJobs\\TestCyclicJobs"
  ]
}
```
Пример класса для Cyclic Job:
```php
class MyCyclicJob implements CyclicJobsInterface
{
    private $application;
    private $server;

    public function __construct(Application $application, Server $server)
    {
        $this->application = $application;
        $this->server = $server;
    }

    public function getTimeSleepSecond(): float
    {
        // Возвращает время задержки в секундах
        return 5.0;
    }

    public function runJob(): void
    {
        $arr = [1,2,3,4,5,6,7,8,9];
        foreach ($arr as $value){
            if($value % 3 == 0)
            {
                echo "example";
            }
        }
    }
}
```
Любой класс, указанный в конфиге, должен реализовывать интерфейс CyclicJobsInterface.

Метод getTimeSleepSecond возвращает время в секундах, через которое будет выполняться метод runJob периодически.

Метод runJob содержит полезную нагрузку в плане бизнес-логики. Это метод содержит бизнес-логику, которая должна выполняться циклически.

## Класс Controller
Для создания новых маршрутов используется класс Controller. Чтобы добавить новый маршрут, вам необходимо создать класс, реализующий интерфейс ControllerInterface, и добавить пространство имен, в котором содержится этот класс, в файл конфигурации с ключом "controllers". Подробнее здесь

Также в фреймворке есть специальный абстрактный класс AbstractController, который может упростить создание класса Controller.

Чтобы создать маршрут для класса Controller, вам необходимо указать атрибут следующего вида:
```php
#[\Sidalex\SwooleApp\Classes\Controllers\Route(uri: '/api/{v1}/get_resume',method:"POST")]
class TestController extends AbstractController
{
```
или
```php
use Sidalex\SwooleApp\Classes\Controllers\Route;

#[Route(uri: '/api/{v1}/get_resume',method:"POST")]
class TestController extends AbstractController
{
```
Крайне важно, чтобы сначала был указан атрибут для этого класса.

Если подходящий контроллер не найден, будет вызван NotFoundController.

### uri атрибутивный параметр
uri - этот параметр определяет, для какого маршрута будет использоваться данный контроллер.

Если вы укажете * в маршруте, например, /test/version/*/items, то этот контроллер будет работать для uri, соответствующих /test/version/(любая строка)/items.

Если вы укажете /test/version/{version_number}/элементы в маршруте, поведение будет аналогично поведению с *, но $uri_params['version_number'] будет добавлен в конструктор контроллера.

### параметр атрибута
method - показывает, какой метод будет уместен для вызова данного контроллера.

### Обработка запроса
Метод execute класса Controller содержит основную бизнес-логику, которую приложение должно выполнить для этого запроса. Этот метод должен возвращать ответ (\Swoole\Http\Response), который содержится в

$this->response

### Response
В классе Controller:

$this->response

Более подробное описание методов этого класса смотрите в официальной документации Swoole

Пример использования:
```php
$this->response->setHeader('Content-Type', 'application/json');
$this->response->end(
                json_encode(
                 [
                     'status' => 'error',
                     'message' => 'collection '.$this->uri_params['collection_name'] . 'not found in collectionList',
                 ]
                )
            );
```
### Запрос
В классе контроллера:

$this->request;

Более подробное описание методов этого класса смотрите в официальной документации Swoole

Пример использования:
```php
$obj = json_decode($this->request->getContent());
```
## notFoundController

Предназначен для обработки роутов, которые не были найдены роутером фреймворка, для выдачи ответа 404 или организации уникальной логики роутинга приложения.

Для инициализации необходимо передать имя класса в конфиге более подробно об этом [тут](#config)

notFoundController должен имплементировать ControllerInterface

пример:
```php
class NotFoundController implements ControllerInterface
{

    private \Swoole\Http\Request $request;
    private \Swoole\Http\Response $responce;
    /**
     * @var array|string[]
     */
    private array $uri_params;

    public function __construct(\Swoole\Http\Request $request, \Swoole\Http\Response $response, array $uri_params=[])
    {
        $this->request = $request;
        $this->responce = $response;
        $this->uri_params = $uri_params;
    }

    public function execute(): \Swoole\Http\Response
    {
        $this->responce->setStatusCode(404);
        $this->responce->setHeader('Content-Type', 'application/json');
        $this->responce->end(json_encode(
            [
                'codeStatus' => '404',
                'text' => 'Page not found'
            ]
        ));
        return $this->responce;
    }

    public function setApplication(Application $application, Server $server)
    {

    }
}
```

## Контейнеры состояний

Контейнеры состояний предоставляют способ управления и совместного использования состояния приложения между различными компонентами. Они инициализируются во время запуска приложения и могут быть доступны на протяжении всего жизненного цикла приложения.

### Создание контейнера состояний
Чтобы создать новый контейнер состояний:
Создайте класс, который расширяет AbstractContainerInitiator или реализует StateContainerInitiationInterface

Реализуйте требуемые методы:

init() — инициализирует ваше состояние

getKey() — возвращает ключ, под которым будет храниться ваше состояние

getResultInitiation() — возвращает инициализированное состояние

```php
<?php
namespace YourApp\StateContainers;

use Sidalex\SwooleApp\Classes\Initiation\AbstractContainerInitiator;

use Sidalex\SwooleApp\Application;

class DatabaseConnectionContainer extends AbstractContainerInitiator
{
protected string $key = 'database';

public function init(Application $app): void
{
// Инициализируйте здесь подключение к базе данных
$config = $app->getConfig()->getContainer('database_config');

$this->result = new \PDO(
$config['dsn'],
$config['username'],
$config['password']
);
}
}
```
Конфигурация JSON:
```json
{
"StateContainerInitiation": [
"YourApp\\StateContainers\\DatabaseConnectionContainer",
"YourApp\\StateContainers\\CacheConnectionContainer"
]
}
```

### Доступ к контейнерам состояний
Доступ к контейнерам состояний возможен из любого места, где у вас есть доступ к экземпляру приложения:

```php
// В контроллере:
$db = $this->application->getStateContainer()->getContainer('database');

// В исполнителе задач:
$db = $this->app->getStateContainer()->getContainer('database');

// В циклическом задании:
$db = $this->application->getStateContainer()->getContainer('database');
```
### Лучшие практики использования Контейнеров состояний
Инициализация: Контейнеры состояний инициализируются один раз во время запуска приложения. Убедитесь, что все необходимые ресурсы доступны в это время.

Безопасность потоков: Поскольку Swoole работает в многопроцессорной среде, убедитесь, что ваши контейнеры состояний:

Без сохранения состояния

Изолированы от процессов

Правильно синхронизированы для общих ресурсов

Управление ресурсами: Если ваш контейнер управляет ресурсами, такими как подключения к базе данных, при необходимости реализуйте надлежащую очистку в деструкторе.

Именование ключей: Используйте описательные и уникальные ключи для ваших контейнеров, чтобы избежать конфликтов.
