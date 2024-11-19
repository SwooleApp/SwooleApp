<?php

namespace Sidalex\SwooleApp\Classes\Validators;

class ValidatorUriArr
{

    /**
     * @param array<int, mixed> $url_arr
     * @return array<int, mixed>
     * @throws \Exception
     */
    public function validate(array $url_arr): array
    {
        if($url_arr[0] != ''){
            throw new \Exception('uri in Route is not valid. uri must started from "/" symbol',1);
    }
        return $url_arr;
    }
}