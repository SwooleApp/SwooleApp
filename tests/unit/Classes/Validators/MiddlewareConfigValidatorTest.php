<?php
namespace tests\Classes\Validators;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Validators\MiddlewareConfigValidator;
use Sidalex\SwooleApp\Classes\Middleware\MiddlewareInterface;

/**
 * @covers \Sidalex\SwooleApp\Classes\Validators\MiddlewareConfigValidator
 */
class MiddlewareConfigValidatorTest extends TestCase
{
    /**
     * @covers \Sidalex\SwooleApp\Classes\Validators\MiddlewareConfigValidator::validate
     */
    public function testValidateWithValidStringMiddlewares()
    {
        $config = new \stdClass();
        $config->globalMiddlewares = [
            TestValidMiddleware::class,
            AnotherTestValidMiddleware::class
        ];

        $validator = new MiddlewareConfigValidator();

        // Не должно быть исключения
        $validator->validate($config);

        $this->assertTrue(true); // Если дошли сюда - валидация прошла
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Validators\MiddlewareConfigValidator::validate
     */
    public function testValidateWithValidArrayMiddlewares()
    {
        $config = new \stdClass();
        $config->globalMiddlewares = [
            [
                'class' => TestValidMiddleware::class,
                'options' => ['key' => 'value']
            ],
            [
                'class' => AnotherTestValidMiddleware::class,
                'options' => []
            ]
        ];

        $validator = new MiddlewareConfigValidator();

        // Не должно быть исключения
        $validator->validate($config);

        $this->assertTrue(true); // Если дошли сюда - валидация прошла
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Validators\MiddlewareConfigValidator::validate
     */
    public function testValidateWithMixedValidMiddlewares()
    {
        $config = new \stdClass();
        $config->globalMiddlewares = [
            TestValidMiddleware::class,
            [
                'class' => AnotherTestValidMiddleware::class,
                'options' => ['test' => 'value']
            ]
        ];

        $validator = new MiddlewareConfigValidator();

        // Не должно быть исключения
        $validator->validate($config);

        $this->assertTrue(true); // Если дошли сюда - валидация прошла
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Validators\MiddlewareConfigValidator::validate
     */
    public function testValidateWithoutGlobalMiddlewares()
    {
        $config = new \stdClass();
        // Нет globalMiddlewares - это валидно

        $validator = new MiddlewareConfigValidator();

        // Не должно быть исключения
        $validator->validate($config);

        $this->assertTrue(true); // Если дошли сюда - валидация прошла
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Validators\MiddlewareConfigValidator::validate
     */
    public function testValidateWithEmptyGlobalMiddlewares()
    {
        $config = new \stdClass();
        $config->globalMiddlewares = [];

        $validator = new MiddlewareConfigValidator();

        // Не должно быть исключения
        $validator->validate($config);

        $this->assertTrue(true); // Если дошли сюда - валидация прошла
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Validators\MiddlewareConfigValidator::validate
     */
    public function testValidateWithInvalidMiddlewareConfiguration()
    {
        $config = new \stdClass();
        $config->globalMiddlewares = [
            123, // Невалидная конфигурация - число вместо строки или массива
        ];

        $validator = new MiddlewareConfigValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid middleware configuration in globalMiddlewares at index 0');

        $validator->validate($config);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Validators\MiddlewareConfigValidator::validate
     */
    public function testValidateWithArrayWithoutClassKey()
    {
        $config = new \stdClass();
        $config->globalMiddlewares = [
            [
                'not_class' => TestValidMiddleware::class, // Отсутствует ключ 'class'
                'options' => ['key' => 'value']
            ]
        ];

        $validator = new MiddlewareConfigValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid middleware configuration in globalMiddlewares at index 0');

        $validator->validate($config);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Validators\MiddlewareConfigValidator::validate
     */
    public function testValidateWithNonExistentMiddlewareClass()
    {
        $config = new \stdClass();
        $config->globalMiddlewares = [
            'NonExistentMiddlewareClass'
        ];

        $validator = new MiddlewareConfigValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Middleware class 'NonExistentMiddlewareClass' not found in globalMiddlewares at index 0");

        $validator->validate($config);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Validators\MiddlewareConfigValidator::validate
     */
    public function testValidateWithClassNotImplementingMiddlewareInterface()
    {
        $config = new \stdClass();
        $config->globalMiddlewares = [
            \stdClass::class // stdClass не реализует MiddlewareInterface
        ];

        $validator = new MiddlewareConfigValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Middleware class 'stdClass' must implement MiddlewareInterface in globalMiddlewares at index 0");

        $validator->validate($config);
    }
}

/**
 * Test Valid Middleware Classes
 */
class TestValidMiddleware implements MiddlewareInterface
{
    public function process(\Swoole\Http\Request $request, \Swoole\Http\Response $response, \Sidalex\SwooleApp\Application $application, callable $next): \Swoole\Http\Response
    {
        return $next($request, $response);
    }
}

class AnotherTestValidMiddleware implements MiddlewareInterface
{
    public function process(\Swoole\Http\Request $request, \Swoole\Http\Response $response, \Sidalex\SwooleApp\Application $application, callable $next): \Swoole\Http\Response
    {
        return $next($request, $response);
    }
}