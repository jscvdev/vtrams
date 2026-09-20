<?php

/**
 * Protected-route bootstrap.
 *
 * Kept as the historical include path. Security work now lives in the
 * middleware pipeline (middleware.inc.php). Do not add new gates here;
 * add a layer under middleware/ instead.
 */
require_once __DIR__ . '/middleware.inc.php';

vtrams_middleware_run();
