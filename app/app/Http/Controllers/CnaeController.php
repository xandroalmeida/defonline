<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Cnae;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lista de subclasses CNAE para o widget de busca (carregada sob demanda,
 * só quando o usuário abre o campo). Cacheável no navegador; ETag pelo
 * conteúdo do arquivo.
 */
final class CnaeController extends Controller
{
    public function index(Request $request): Response
    {
        $conteudo = (string) file_get_contents(Cnae::arquivo());
        $etag = '"'.md5($conteudo).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response($conteudo, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'private, max-age=86400',
            'ETag' => $etag,
        ]);
    }
}
