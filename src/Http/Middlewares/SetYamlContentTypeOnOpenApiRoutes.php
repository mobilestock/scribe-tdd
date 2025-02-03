<?php

namespace AjCastro\ScribeTdd\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetYamlContentTypeOnOpenApiRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('*.openapi')) {
            $response->headers->set('Content-Type', 'application/yaml');
        }
        return $response;
    }
}
