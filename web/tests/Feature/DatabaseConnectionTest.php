<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_connection_uses_the_mysql_driver(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
    }

    public function test_database_server_is_mysql_8(): void
    {
        $version = DB::selectOne('select version() as version')->version;

        // MariaDB also answers to the mysql driver but reports e.g. "10.11.6-MariaDB".
        $this->assertStringStartsWith('8.', $version);
        $this->assertStringNotContainsStringIgnoringCase('mariadb', $version);
    }

    public function test_migrations_create_the_users_table(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
    }
}
