<?php

namespace Sidalex\SwooleApp\Classes\Tasks;

class TaskResulted
{

    private bool $success;
    private mixed $result;

    public function __construct(mixed $inData, bool $success = true)
    {
        $this->success = $success;
        $this->result = $inData;
    }

    /**
     * @return mixed
     * @throws TaskException
     */
    public function getResult(): mixed {
        if (!$this->success) {
            throw new TaskException(
                $this->result === 'Task execution failed'
                    ? $this->result
                    : "Task execution failed: " . var_export($this->result, true)
            );
        }
        return $this->result;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }


}