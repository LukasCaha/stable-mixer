<?php

namespace Tests;

use App\Ai\Agents\QuestionAgent;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        QuestionAgent::fake(fn (): array => ['questions' => []]);
    }
}
