<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bases permitidas para pruebas. RefreshDatabase vacía la BD, así que se aborta
     * antes de correr cualquier trait si la conexión apunta a otra (p. ej. desarrollo o producción).
     */
    private const ALLOWED_TEST_DATABASES = ['testing', 'soft_olamtex_test', 'soft_olamtex_testing'];

    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (! in_array($database, self::ALLOWED_TEST_DATABASES, true)) {
            throw new RuntimeException(
                "Pruebas abortadas: la BD '{$database}' no es de pruebas. Permitidas: ".implode(', ', self::ALLOWED_TEST_DATABASES).'.'
            );
        }

        return $app;
    }
}
