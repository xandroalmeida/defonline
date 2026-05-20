<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CounterBrowserTest extends DuskTestCase
{
    public function test_counter_increments_in_a_real_browser(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertSee('DEFOnline')
                ->assertSeeIn('@count', '0')
                ->click('@increment')
                ->waitForTextIn('@count', '1')
                ->click('@increment')
                ->waitForTextIn('@count', '2')
                ->assertSeeIn('@db-driver', 'pgsql');
        });
    }
}
