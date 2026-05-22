<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E em browser real do hello world (STORY-007 CA-7).
 */
final class HelloWorldBrowserTest extends DuskTestCase
{
    public function test_visitor_sees_hello_page_with_version_and_ok_status(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertSee('hello DEFOnline')
                ->assertSee('Versão')
                ->assertSee('Healthcheck')
                ->assertPresent('@app-version')
                ->assertSeeIn('@health-status', 'OK');
        });
    }

    public function test_visitor_can_dispatch_the_demo_email(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->click('@disparar-email')
                ->waitFor('@mensagem-envio')
                ->assertSeeIn('@mensagem-envio', 'Job enfileirado');
        });
    }
}
