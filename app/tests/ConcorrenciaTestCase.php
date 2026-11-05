<?php

declare(strict_types=1);

namespace Tests;

/**
 * Base dos testes de concorrência (STORY-073 CA-6, CA-10): o banco é migrado como nos demais
 * testes, mas SEM a transação que os envolve. Os processos filhos são outras conexões e só
 * enxergam dados efetivados; por isso cada teste limpa o que criou.
 */
abstract class ConcorrenciaTestCase extends TestCase
{
    /** @var list<string|null> */
    protected array $connectionsToTransact = [];
}
