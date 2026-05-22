<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Feature tests usam a TestCase do Laravel + RefreshDatabase (Postgres real
| via docker compose, conforme princípio #3/#6 e quality-standards).
| Unit tests rodam só com PHPUnit base — rápidos, sem DB.
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
