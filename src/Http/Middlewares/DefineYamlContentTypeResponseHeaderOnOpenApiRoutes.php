<?php

namespace AjCastro\ScribeTdd\Http\Middlewares;

use Illuminate\Http\Request;

class DefineYamlContentTypeResponseHeaderOnOpenApiRoutes
{
    public function __invoke(Request $request, $next)
    {
        $response = $next($request);

        if ($request->is('*.openapi')) {
            $response->headers->set('Content-Type', 'application/yaml');
        }
        return $response;
    }
}
