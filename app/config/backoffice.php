<?php

declare(strict_types=1);

/*
 * STORY-065 / PDR-011 — backoffice mínimo, só-leitura.
 *
 * `equipe_emails`: allowlist de e-mails da equipe EB Parcerias que acessam
 * `/backoffice/*`. Vem de BACKOFFICE_EMAILS (separados por vírgula). Vazia ou
 * ausente bloqueia todo mundo (CA-1). Comparação sem diferenciar maiúsculas.
 */
return [
    'equipe_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => mb_strtolower(trim($email)),
        explode(',', (string) env('BACKOFFICE_EMAILS', '')),
    ))),
];
