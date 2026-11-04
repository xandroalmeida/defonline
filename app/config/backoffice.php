<?php

declare(strict_types=1);

/*
 * STORY-065 / STORY-067 — backoffice.
 *
 * `equipe_emails`: admins com todas as permissões, definidos no servidor. Os demais
 * recebem permissões na tela de Administradores (PDR-013). Vem de BACKOFFICE_EMAILS
 * (separados por vírgula). Comparação sem diferenciar maiúsculas.
 */
return [
    'equipe_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => mb_strtolower(trim($email)),
        explode(',', (string) env('BACKOFFICE_EMAILS', '')),
    ))),
];
