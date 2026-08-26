<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalAccessTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        config()->set('oddradar.access.enabled', true);
        config()->set('oddradar.access.username', 'operator');
        config()->set('oddradar.access.password', 'secret');

        $this->get('/')->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Basic realm="OddRadar"');
        $this->withServerVariables(['PHP_AUTH_USER' => 'operator', 'PHP_AUTH_PW' => 'secret'])
            ->get('/')
            ->assertSee('OddRadar');
    }
}
