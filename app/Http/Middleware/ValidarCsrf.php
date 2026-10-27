<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * CSRF do Laravel aceitando também o campo `_csrf` dos formulários do
 * sistema antigo (o JS manda o header X-CSRF-Token, que o Laravel já lê).
 */
class ValidarCsrf extends ValidateCsrfToken
{
    protected function getTokenFromRequest($request)
    {
        return $request->input('_csrf') ?: parent::getTokenFromRequest($request);
    }
}
