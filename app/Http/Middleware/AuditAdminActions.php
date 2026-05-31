<?php

namespace App\Http\Middleware;

use App\Models\AuditEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminActions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->shouldLog($request, $response)) {
            return $response;
        }

        try {
            AuditEvent::create([
                'actor_id' => $request->user()?->id,
                'action' => $this->detectAction($request),
                'entity_type' => $this->detectEntityType($request),
                'entity_id' => $this->detectEntityId($request),
                'payload' => $this->buildPayload($request, $response),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (!$request->user()) {
            return false;
        }

        if (!in_array($request->user()->account_type, ['nti_admin', 'superadmin'], true)) {
            return false;
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }

        if (!$request->is('api/admin/*')) {
            return false;
        }

        if ($request->is('api/admin/audit-events*')) {
            return false;
        }

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 400;
    }

    private function detectAction(Request $request): string
    {
        $method = strtolower($request->method());
        $path = strtolower($request->path());

        if (str_contains($path, '/approve')) {
            return 'approve_user';
        }

        if (str_contains($path, '/reject')) {
            return 'reject_user';
        }

        if (str_contains($path, '/assign-mentor')) {
            return 'assign_mentor';
        }

        if (str_contains($path, '/open')) {
            return 'open_call';
        }

        if (str_contains($path, '/close')) {
            return 'close_call';
        }

        if ($method === 'post') {
            return 'create_' . $this->detectEntityType($request);
        }

        if (in_array($method, ['patch', 'put'], true)) {
            return 'update_' . $this->detectEntityType($request);
        }

        if ($method === 'delete') {
            return 'delete_' . $this->detectEntityType($request);
        }

        return $method . '_' . $this->detectEntityType($request);
    }

    private function detectEntityType(Request $request): string
    {
        $path = strtolower($request->path());

        if (str_contains($path, '/users')) {
            return 'user';
        }

        if (str_contains($path, '/calls')) {
            return 'call';
        }

        if (str_contains($path, '/programs')) {
            return 'program';
        }

        if (str_contains($path, '/applications')) {
            return 'application';
        }

        return 'admin';
    }

    private function detectEntityId(Request $request): ?string
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (is_object($parameter) && isset($parameter->id)) {
                return (string) $parameter->id;
            }

            if (is_string($parameter) || is_numeric($parameter)) {
                return (string) $parameter;
            }
        }

        return null;
    }

    private function buildPayload(Request $request, Response $response): array
    {
        return [
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'input' => $this->safeInput($request),
        ];
    }

    private function safeInput(Request $request): array
    {
        return collect($request->except([
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'authorization',
        ]))
            ->take(10)
            ->toArray();
    }
}