<?php

namespace Sidalex\SwooleApp\Classes\Constants;

enum CyclicJobsDistributionStrategy {
    case ALL_WORKERS;
    case DEDICATED_WORKER;
    case ROUND_ROBIN;
}