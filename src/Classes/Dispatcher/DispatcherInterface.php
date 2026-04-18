<?php

namespace Sidalex\SwooleApp\Classes\Dispatcher;

use Sidalex\SwooleApp\Application;

/**
 * Интерфейс для пользовательских диспатчеров
 *
 * Используется для кастомного распределения запросов между воркерами
 * Класс диспатчера указывается в конфиге через ключ "DISPATCHER"
 */
interface DispatcherInterface {
    /**
     * Возвращает callable функцию для dispatch_func в Swoole
     *
     * @param Application $app Экземпляр приложения (доступ к конфигурации и состоянию)
     * @return callable Функция с сигнатурой function($server, $fd, $type, $data): int
     *
     * Параметры:
     * - $server: Swoole\Server экземпляр
     * - $fd: int файловый дескриптор соединения
     * - $type: int тип данных
     * - $data: mixed данные запроса
     *
     * Возвращает:
     * - int ID воркера (от 0 до worker_num-1), который обработает запрос
     */
    public function getDispatchFunction(Application $app): callable;
}